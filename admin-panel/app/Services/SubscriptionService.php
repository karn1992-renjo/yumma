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
    public function startSubscription(Restaurant $restaurant, SubscriptionPlan $plan, string $scope = 'full_plan', string $gatewayName = 'razorpay', ?RestaurantSubscription $replacing = null): array
    {
        // Stories-only plans are pay-as-you-go: one payment per period, no gateway mandate.
        if (RestaurantPrepaidSubscriptionService::isPrepaidPlan($plan)) {
            $result = app(RestaurantPrepaidSubscriptionService::class)->start($restaurant, $plan, $gatewayName, $replacing);

            return ['subscription' => $result['subscription'], 'checkout' => $result['checkout'], 'payment_mode' => 'prepaid'];
        }

        if (! in_array($gatewayName, SubscriptionGatewayResolver::SUPPORTED, true)) {
            throw new RuntimeException("Unsupported subscription gateway: {$gatewayName}");
        }
        if (! in_array($gatewayName, $this->gateways->availableGateways(), true)) {
            throw new RuntimeException("{$gatewayName} is not available right now. Please choose a different payment method.");
        }

        // A checkout that was started but never paid (failed or abandoned payment) must not block trying again.
        $this->discardUnpaidAttempts($restaurant, $scope);

        $existing = $restaurant->subscriptions()
            ->where('scope', $scope)
            ->whereIn('status', ['trialing', 'active', 'past_due', 'billing_choice_required'])
            ->first();

        if ($existing && ! $replacing) {
            throw new RuntimeException('This restaurant already has a live subscription for this scope. Use upgrade to change plan.');
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
            'replaces_subscription_id' => $replacing?->id,
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
            'payment_mode' => 'recurring',
        ];
    }

    /**
     * Moves a restaurant to a higher-priced plan of the same kind. The new plan is paid first; the current one keeps
     * running (and keeps its benefits) until that payment succeeds, then it is ended. A failed payment changes nothing.
     * There is no proration: the new plan's period starts when it is paid.
     */
    public function startUpgrade(Restaurant $restaurant, SubscriptionPlan $target, string $gatewayName = 'razorpay'): array
    {
        $current = $restaurant->subscriptions()->where('scope', $target->scope)
            ->whereIn('status', ['trialing', 'active', 'past_due'])->latest()->first();
        if (! $current || ! $current->isLive()) {
            throw new RuntimeException('There is no active plan to upgrade. Choose a plan to subscribe instead.');
        }
        if ((int) $current->subscription_plan_id === (int) $target->id) {
            throw new RuntimeException('You are already on this plan.');
        }
        if ((float) $target->price <= (float) ($current->plan?->price ?? 0)) {
            throw new RuntimeException('Choose a plan with a higher price to upgrade.');
        }

        return $this->startSubscription($restaurant, $target, $target->scope, $gatewayName, $current);
    }

    /** Ends the subscription this one was bought to replace (stopping its gateway charges). */
    private function endReplaced(RestaurantSubscription $new): void
    {
        if (! $new->replaces_subscription_id) {
            return;
        }
        $old = RestaurantSubscription::find($new->replaces_subscription_id);
        if (! $old || ! in_array($old->status, ['trialing', 'active', 'past_due', 'billing_choice_required'], true)) {
            return;
        }
        try {
            if ($old->gateway && $old->gateway_subscription_id) {
                $this->gateways->resolve($old->gateway)->cancel($old);
            }
        } catch (\Throwable $e) {
            report($e);
        }
        $old->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]);
    }

    /**
     * The gateway confirmed the mandate (before any charge). A plan with a free trial starts its trial now, so
     * the restaurant has access straight away instead of seeing "Setting up" until the first charge.
     */
    public function markAuthorized(RestaurantSubscription $subscription): void
    {
        if ($subscription->status !== 'pending_authorization' || ! $subscription->plan?->hasTrial()) {
            return;       // without a trial the first charge activates it
        }

        DB::transaction(function () use ($subscription) {
            $locked = RestaurantSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_authorization') {
                return;
            }
            $trialEnds = $locked->trial_ends_at ?: now()->addDays((int) $locked->plan->trial_days);
            $locked->update(['status' => 'trialing', 'trial_ends_at' => $trialEnds, 'current_period_start' => now(), 'current_period_end' => $trialEnds]);

            if ($locked->scope === 'full_plan') {
                $locked->restaurant->forceFill(['billing_mode' => 'subscription', 'active_subscription_id' => $locked->id])->save();
            }
            $this->endReplaced($locked);
        });
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

            $this->endReplaced($locked);
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

    /** Subscriptions whose payment never completed: still waiting for the mandate/first charge. */
    public function discardUnpaidAttempts(Restaurant $restaurant, string $scope = 'full_plan'): void
    {
        $restaurant->subscriptions()->where('scope', $scope)->where('status', 'pending_authorization')->get()
            ->each(function (RestaurantSubscription $sub) {
                try {
                    if ($sub->gateway && $sub->gateway_subscription_id) {
                        $this->gateways->resolve($sub->gateway)->cancel($sub);
                    }
                } catch (\Throwable) {
                    // an unpaid gateway subscription may not be cancellable; it is closed locally either way
                }
                $sub->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]);
            });
    }

    public function switchToCommissionWithConsent(Restaurant $restaurant, float $rateShown, User $actor): void
    {
        DB::transaction(function () use ($restaurant, $rateShown) {
            // Every live plan subscription ends, including a payment that was never completed or one that
            // has lapsed, so nothing keeps showing as the restaurant's plan after the switch.
            $restaurant->subscriptions()->where('scope', 'full_plan')
                ->whereIn('status', ['pending_authorization', 'trialing', 'active', 'past_due', 'billing_choice_required'])
                ->get()
                ->each(function (RestaurantSubscription $sub) {
                    if ($sub->status !== 'pending_authorization' && $sub->gateway && $sub->gateway_subscription_id) {
                        try {
                            $this->gateways->resolve($sub->gateway)->cancel($sub);
                        } catch (\Throwable $e) {
                            report($e);        // stop future charges best-effort; the switch itself must not fail
                        }
                    }
                    $sub->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]);
                });

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
