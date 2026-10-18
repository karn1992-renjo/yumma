<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Services\GoldSubscriptionService;
use App\Services\Subscription\SubscriptionGatewayResolver;
use Illuminate\Http\Request;

/**
 * Customer "Gold" membership -- waives delivery fees on orders (see
 * Api\OrderController::buildPricingSummary()). Mirrors
 * Api\SubscriptionBillingController's shape for restaurant subscriptions,
 * but there is no commission/billing-choice concept here: a lapsed
 * membership just stops waiving fees until renewed.
 */
class CustomerGoldController extends Controller
{
    public function plans(Request $request, SubscriptionGatewayResolver $gateways)
    {
        $plans = SubscriptionPlan::where('audience', 'customer')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans->map(fn (SubscriptionPlan $plan) => $this->formatPlan($plan)),
            // Which gateways a customer may actually pick -- see
            // SubscriptionGatewayResolver::availableGateways().
            'available_gateways' => $gateways->availableGateways(),
        ]);
    }

    public function current(Request $request)
    {
        $subscription = CustomerSubscription::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending_authorization', 'trialing', 'active', 'past_due'])
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'is_gold_member' => $subscription?->isLive() ?? false,
                'subscription' => $subscription ? $this->formatSubscription($subscription) : null,
            ],
        ]);
    }

    public function subscribe(Request $request, GoldSubscriptionService $goldService)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'nullable|in:razorpay,cashfree',
        ]);

        $plan = SubscriptionPlan::where('audience', 'customer')
            ->where('is_active', true)
            ->findOrFail($validated['plan_id']);

        try {
            $result = $goldService->startSubscription(
                $request->user(),
                $plan,
                $validated['payment_method'] ?? 'razorpay'
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Unable to start Gold membership right now. Please try again shortly.'], 502);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $this->formatSubscription($result['subscription']),
                'checkout' => $result['checkout'],
            ],
        ]);
    }

    public function cancel(Request $request, GoldSubscriptionService $goldService)
    {
        $subscription = CustomerSubscription::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending_authorization', 'trialing', 'active', 'past_due'])
            ->first();

        if (! $subscription) {
            return response()->json(['success' => false, 'message' => 'No active Gold membership found.'], 404);
        }

        $goldService->cancel($subscription);

        return response()->json(['success' => true, 'message' => 'Gold membership cancelled.']);
    }

    private function formatPlan(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'billing_period' => $plan->billing_period,
            'validity_days' => $plan->validity_days,
            'price' => (float) $plan->price,
            'compare_at_price' => $plan->compare_at_price !== null ? (float) $plan->compare_at_price : null,
            'currency' => $plan->currency,
            'trial_days' => $plan->trial_days,
            'order_limit' => $plan->order_limit,
            'description' => $plan->description,
        ];
    }

    private function formatSubscription(CustomerSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'plan' => $subscription->plan ? $this->formatPlan($subscription->plan) : null,
            'trial_ends_at' => optional($subscription->trial_ends_at)->toIso8601String(),
            'current_period_start' => optional($subscription->current_period_start)->toIso8601String(),
            'current_period_end' => optional($subscription->current_period_end)->toIso8601String(),
            'auto_renew' => (bool) $subscription->auto_renew,
            'orders_used_in_period' => $subscription->orders_used_in_period,
            'order_limit' => $subscription->plan?->order_limit,
        ];
    }
}
