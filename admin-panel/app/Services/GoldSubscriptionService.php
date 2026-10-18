<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscription\SubscriptionGatewayResolver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Customer "Gold" membership -- same recurring-billing shape as
 * SubscriptionService (restaurant subscriptions), reusing the same
 * SubscriptionGatewayInterface/SubscriptionGatewayResolver. Unlike a
 * restaurant subscription there's no menu-item cap or billing_choice_required
 * state -- a lapsed membership just stops waiving delivery fees until the
 * customer renews. It can still optionally cap how many orders per period
 * get the waiver (plan.order_limit, NULL = unlimited) -- see
 * Api\OrderController::buildPricingSummary().
 */
class GoldSubscriptionService
{
    public function __construct(private readonly SubscriptionGatewayResolver $gateways)
    {
    }

    public function startSubscription(User $user, SubscriptionPlan $plan, string $gatewayName = 'razorpay'): array
    {
        if (! in_array($gatewayName, SubscriptionGatewayResolver::SUPPORTED, true)) {
            throw new RuntimeException("Unsupported subscription gateway: {$gatewayName}");
        }
        if (! in_array($gatewayName, $this->gateways->availableGateways(), true)) {
            throw new RuntimeException("{$gatewayName} is not available right now. Please choose a different payment method.");
        }

        $existing = CustomerSubscription::where('user_id', $user->id)
            ->whereIn('status', ['pending_authorization', 'trialing', 'active', 'past_due'])
            ->first();

        if ($existing) {
            throw new RuntimeException('You already have an active Gold membership.');
        }

        $gateway = $this->gateways->resolve($gatewayName);
        $trialEndsAt = $plan->hasTrial() ? now()->addDays($plan->trial_days) : null;

        $subscription = CustomerSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'pending_authorization',
            'trial_ends_at' => $trialEndsAt,
            'gateway' => $gatewayName,
        ]);

        $gatewayCustomerId = $gateway->resolveCustomerId(
            $user->name ?? 'Customer',
            $user->email ?? '',
            $user->phone ?? null,
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

    public function activateFromCharge(CustomerSubscription $subscription, array $chargeData): void
    {
        DB::transaction(function () use ($subscription, $chargeData) {
            $locked = CustomerSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $periodStart = now();
            $periodEnd = $locked->plan?->nextPeriodEndFrom($periodStart) ?? $periodStart->copy()->addMonth();

            if ($locked->current_period_end && $periodStart->lt($locked->current_period_end)) {
                return;
            }

            $locked->update([
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'orders_used_in_period' => 0,
            ]);

            SubscriptionPayment::create([
                'restaurant_subscription_id' => null,
                'customer_subscription_id' => $locked->id,
                'amount' => $chargeData['amount'] ?? $locked->plan?->price ?? 0,
                'currency' => $chargeData['currency'] ?? $locked->plan?->currency ?? 'INR',
                'status' => 'success',
                'gateway' => $locked->gateway,
                'gateway_charge_id' => $chargeData['payment_id'] ?? null,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'meta' => $chargeData,
            ]);
        });
    }

    public function markPastDue(CustomerSubscription $subscription, ?string $reason = null): void
    {
        $subscription->update([
            'status' => 'past_due',
            'past_due_at' => $subscription->past_due_at ?? now(),
        ]);

        if ($reason) {
            SubscriptionPayment::create([
                'restaurant_subscription_id' => null,
                'customer_subscription_id' => $subscription->id,
                'amount' => $subscription->plan?->price ?? 0,
                'currency' => $subscription->plan?->currency ?? 'INR',
                'status' => 'failed',
                'gateway' => $subscription->gateway,
                'failure_reason' => $reason,
            ]);
        }
    }

    public function markExpired(CustomerSubscription $subscription): void
    {
        $subscription->update(['status' => 'expired']);
    }

    public function cancel(CustomerSubscription $subscription): void
    {
        if ($subscription->gateway) {
            $this->gateways->resolve($subscription->gateway)->cancel($subscription);
        }
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]);
    }

    /**
     * Cheap exists() check used at checkout/delivery-fee-calculation time --
     * NOT eager-loaded, call sparingly (once per pricing calculation). Only
     * reflects membership status, not order-limit capacity -- see
     * activeSubscriptionFor() when the caller also needs to check/consume
     * the order cap.
     */
    public function isActiveFor(int $userId): bool
    {
        return CustomerSubscription::where('user_id', $userId)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->exists();
    }

    /**
     * The live membership row itself (with its plan loaded), for callers
     * that need to check hasOrderCapacity() before waiving a fee and later
     * incrementOrderUsage() once the order is actually placed.
     */
    public function activeSubscriptionFor(int $userId): ?CustomerSubscription
    {
        return CustomerSubscription::where('user_id', $userId)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->with('plan')
            ->first();
    }

    public function incrementOrderUsage(CustomerSubscription $subscription): void
    {
        $subscription->increment('orders_used_in_period');
    }
}
