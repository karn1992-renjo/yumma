<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscription\SubscriptionGatewayResolver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubscriptionService
{
    public function __construct(private readonly SubscriptionGatewayResolver $gateways)
    {
    }

    /**
     * Begins a new restaurant subscription: creates our local row in
     * `pending_authorization`, provisions the gateway customer/plan/
     * subscription objects, and returns the checkout payload the Flutter
     * app needs to open the gateway's mandate-authorization UI. Nothing is
     * charged here -- activation happens via the webhook once the customer
     * completes that UI.
     */
    public function startSubscription(Restaurant $restaurant, SubscriptionPlan $plan, string $scope = 'full_plan', string $gatewayName = 'razorpay'): array
    {
        if (! in_array($gatewayName, SubscriptionGatewayResolver::SUPPORTED, true)) {
            throw new RuntimeException("Unsupported subscription gateway: {$gatewayName}");
        }
        if (! in_array($gatewayName, $this->gateways->availableGateways(), true)) {
            throw new RuntimeException("{$gatewayName} is not available right now. Please choose a different payment method.");
        }

        $existing = $restaurant->subscriptions()
            ->where('scope', $scope)
            ->whereIn('status', ['pending_authorization', 'trialing', 'active', 'past_due', 'billing_choice_required'])
            ->first();

        if ($existing) {
            throw new RuntimeException('This restaurant already has a live subscription for this scope.');
        }

        $gateway = $this->gateways->resolve($gatewayName);
        $trialEndsAt = $plan->hasTrial() ? now()->addDays($plan->trial_days) : null;

        $subscription = RestaurantSubscription::create([
            'restaurant_id' => $restaurant->id,
            'subscription_plan_id' => $plan->id,
            'scope' => $scope,
            'status' => 'pending_authorization',
            'trial_ends_at' => $trialEndsAt,
            'gateway' => $gatewayName,
        ]);

        $owner = $restaurant->owner;
        $gatewayCustomerId = $gateway->resolveCustomerId(
            $owner?->name ?? $restaurant->name,
            $owner?->email ?? $restaurant->email,
            $owner?->phone ?? $restaurant->phone,
            null
        );
        $gatewayPlanId = $gateway->resolvePlanId($plan);

        $result = $gateway->createSubscription($subscription, $gatewayCustomerId, $gatewayPlanId);

        $subscription->update([
            'gateway_customer_id' => $gatewayCustomerId,
            'gateway_subscription_id' => $result['gateway_subscription_id'],
        ]);

        return [
            'subscription' => $subscription->fresh(),
            'checkout' => $result['checkout'],
        ];
    }

    /**
     * Called from the webhook when the gateway confirms the mandate is
     * authorized and the (possibly trial-deferred) first charge succeeded.
     * Idempotent: a duplicate webhook delivery for the same charge is a
     * no-op if the period has already been extended past it.
     */
    public function activateFromCharge(RestaurantSubscription $subscription, array $chargeData): void
    {
        DB::transaction(function () use ($subscription, $chargeData) {
            $locked = RestaurantSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $periodStart = now();
            $periodEnd = $locked->plan?->nextPeriodEndFrom($periodStart) ?? $periodStart->copy()->addMonth();

            if ($locked->current_period_end && $periodStart->lt($locked->current_period_end)) {
                // Already-processed charge replayed by the gateway; nothing to do.
                return;
            }

            $locked->update([
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'orders_used_in_period' => 0,
            ]);

            SubscriptionPayment::create([
                'restaurant_subscription_id' => $locked->id,
                'amount' => $chargeData['amount'] ?? $locked->plan?->price ?? 0,
                'currency' => $chargeData['currency'] ?? $locked->plan?->currency ?? 'INR',
                'status' => 'success',
                'gateway' => $locked->gateway,
                'gateway_charge_id' => $chargeData['payment_id'] ?? null,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'meta' => $chargeData,
            ]);

            if ($locked->scope === 'full_plan') {
                // billing_mode/active_subscription_id are deliberately absent
                // from Restaurant::$fillable (mass-assignment protected, same
                // as the suspension fields) -- forceFill(), not update().
                $locked->restaurant->forceFill([
                    'billing_mode' => 'subscription',
                    'active_subscription_id' => $locked->id,
                ])->save();
            }
        });
    }

    public function markPastDue(RestaurantSubscription $subscription, ?string $reason = null): void
    {
        // Only stamp past_due_at the first time -- a second failed-charge
        // webhook while already past_due must not restart the grace clock.
        $subscription->update([
            'status' => 'past_due',
            'past_due_at' => $subscription->past_due_at ?? now(),
        ]);

        if ($reason) {
            SubscriptionPayment::create([
                'restaurant_subscription_id' => $subscription->id,
                'amount' => $subscription->plan?->price ?? 0,
                'currency' => $subscription->plan?->currency ?? 'INR',
                'status' => 'failed',
                'gateway' => $subscription->gateway,
                'failure_reason' => $reason,
            ]);
        }
    }

    /**
     * Fires once the past-due grace window elapses with no successful
     * charge. Deliberately does NOT fall back to commission billing --
     * the restaurant must explicitly choose (see switchToCommissionWithConsent).
     */
    public function markBillingChoiceRequired(RestaurantSubscription $subscription): void
    {
        $subscription->update(['status' => 'billing_choice_required']);
    }

    public function switchToCommissionWithConsent(Restaurant $restaurant, float $rateShown, User $actor): void
    {
        DB::transaction(function () use ($restaurant, $rateShown) {
            $activeSub = $restaurant->activeSubscription;
            if ($activeSub) {
                $activeSub->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }

            $restaurant->forceFill([
                'billing_mode' => 'commission',
                'active_subscription_id' => null,
                'commission_consent_accepted_at' => now(),
                'commission_consent_rate_shown' => $rateShown,
            ])->save();
        });
    }

    public function cancel(RestaurantSubscription $subscription): void
    {
        if ($subscription->gateway) {
            $this->gateways->resolve($subscription->gateway)->cancel($subscription);
        }
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]);

        if ($subscription->scope === 'full_plan' && $subscription->restaurant?->active_subscription_id === $subscription->id) {
            $subscription->restaurant->forceFill(['active_subscription_id' => null])->save();
        }
    }

    public function incrementOrderUsage(RestaurantSubscription $subscription): void
    {
        $subscription->increment('orders_used_in_period');
    }
}
