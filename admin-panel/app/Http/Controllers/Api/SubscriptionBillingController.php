<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\CommissionSetting;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Services\RestaurantPrepaidSubscriptionService;
use App\Services\Subscription\SubscriptionGatewayResolver;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionBillingController extends Controller
{
    use ResolvesRestaurantContext;

    public function plans(Request $request, SubscriptionGatewayResolver $gateways)
    {
        $scope = in_array($request->get('scope'), ['full_plan', 'stories_addon'], true)
            ? $request->get('scope')
            : 'full_plan';

        $plans = SubscriptionPlan::where('audience', 'restaurant')
            ->where('scope', $scope)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans->map(fn (SubscriptionPlan $plan) => $this->formatPlan($plan)),
            // Which gateways a restaurant may actually pick -- admin-enabled
            // for customer payments AND carrying real API credentials. The
            // app should never offer one that isn't in this list.
            'available_gateways' => $gateways->availableGateways(),
        ]);
    }

    public function current(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $fullPlanSub = $restaurant->subscriptions()
            ->where('scope', 'full_plan')
            ->whereIn('status', ['trialing', 'active', 'past_due', 'billing_choice_required'])
            ->latest()
            ->first();

        // A checkout that was started but not paid (failed/abandoned) is not a subscription the restaurant has.
        $pendingSub = $fullPlanSub ? null : $restaurant->subscriptions()
            ->where('scope', 'full_plan')->where('status', 'pending_authorization')
            ->where('created_at', '>=', now()->subHours(2))->latest()->first();

        $storiesAddonSub = $restaurant->storiesAddonSubscription;
        if ($storiesAddonSub && ! $storiesAddonSub->isLive()) {
            $storiesAddonSub = null;          // a pay-as-you-go period that has ended
        }

        // Plans this restaurant could move up to (same kind, higher price).
        $upgradeFor = function (?RestaurantSubscription $sub) {
            if (! $sub || ! $sub->isLive()) {
                return [];
            }

            return SubscriptionPlan::where('audience', 'restaurant')->where('scope', $sub->scope)->where('is_active', true)
                ->where('price', '>', (float) ($sub->plan?->price ?? 0))->orderBy('price')->get()
                ->map(fn (SubscriptionPlan $p) => $this->formatPlan($p))->values();
        };

        return response()->json([
            'success' => true,
            'data' => [
                'billing_mode' => $restaurant->billing_mode,
                'needs_billing_choice' => $restaurant->needsBillingChoice(),
                'subscription' => $fullPlanSub ? $this->formatSubscription($fullPlanSub) : null,
                'pending_subscription' => $pendingSub ? $this->formatSubscription($pendingSub) : null,
                'stories_addon_subscription' => $storiesAddonSub ? $this->formatSubscription($storiesAddonSub) : null,
                'can_post_stories' => app(\App\Services\StoriesAccessService::class)->canPost($restaurant),
                'upgrade_plans' => $upgradeFor($fullPlanSub),
                'stories_upgrade_plans' => $upgradeFor($storiesAddonSub),
            ],
        ]);
    }

    public function subscribe(Request $request, SubscriptionService $subscriptionService)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'nullable|in:razorpay,cashfree',
        ]);

        $plan = SubscriptionPlan::where('audience', 'restaurant')
            ->where('is_active', true)
            ->findOrFail($validated['plan_id']);

        try {
            $result = $subscriptionService->startSubscription(
                $restaurant,
                $plan,
                $plan->scope,
                $validated['payment_method'] ?? 'razorpay'
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Unable to start subscription right now. Please try again shortly.'], 502);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $this->formatSubscription($result['subscription']),
                'checkout' => $result['checkout'],
                'payment_mode' => $result['payment_mode'] ?? 'recurring',
            ],
        ]);
    }

    /** Moves to a higher-priced plan of the same kind; the current plan ends only once the new one is paid. */
    public function upgrade(Request $request, SubscriptionService $subscriptionService)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'nullable|in:razorpay,cashfree',
        ]);
        $plan = SubscriptionPlan::where('audience', 'restaurant')->where('is_active', true)->findOrFail($validated['plan_id']);

        try {
            $result = $subscriptionService->startUpgrade($restaurant, $plan, $validated['payment_method'] ?? 'razorpay');
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Unable to start the upgrade right now. Please try again shortly.'], 502);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $this->formatSubscription($result['subscription']),
                'checkout' => $result['checkout'],
                'payment_mode' => $result['payment_mode'] ?? 'recurring',
            ],
        ]);
    }

    /** Confirms a pay-as-you-go payment right after the app's payment SDK reports success. */
    public function verify(Request $request, RestaurantPrepaidSubscriptionService $prepaid)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $validated = $request->validate([
            'subscription_id' => 'required|integer',
            'payment_method' => 'required|in:razorpay,cashfree',
            'payment_id' => 'nullable|string',
            'razorpay_order_id' => 'nullable|string',
            'razorpay_signature' => 'nullable|string',
        ]);
        $subscription = RestaurantSubscription::where('restaurant_id', $restaurant->id)->where('payment_mode', 'prepaid')->findOrFail($validated['subscription_id']);

        try {
            $subscription = $prepaid->verifyAndActivate($subscription, $validated);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Unable to confirm the payment right now. If money was deducted it will be applied shortly; please try again.'], 502);
        }

        return response()->json(['success' => true, 'data' => ['subscription' => $this->formatSubscription($subscription)]]);
    }

    public function cancel(Request $request, SubscriptionService $subscriptionService)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $validated = $request->validate([
            'subscription_id' => 'required|exists:restaurant_subscriptions,id',
        ]);

        $subscription = RestaurantSubscription::where('restaurant_id', $restaurant->id)
            ->findOrFail($validated['subscription_id']);

        $subscriptionService->cancel($subscription);

        return response()->json(['success' => true, 'message' => 'Subscription cancelled.']);
    }

    /**
     * Lets the Flutter app show the exact commission terms before the
     * restaurant checks the consent box -- switchToCommission() itself
     * requires that consent to actually be accepted.
     */
    public function commissionPreview(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->resolveCommissionDefinition($restaurant),
        ]);
    }

    public function switchToCommission(Request $request, SubscriptionService $subscriptionService)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $validated = $request->validate([
            'consent' => 'required|accepted',
        ]);

        $definition = $this->resolveCommissionDefinition($restaurant);

        $subscriptionService->switchToCommissionWithConsent($restaurant, $definition['value'], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Switched to commission billing.',
            'data' => $definition,
        ]);
    }

    /**
     * Mirrors PayoutCalculationService::restaurantCommissionDefinition()'s
     * resolution (restaurant override vs global CommissionSetting) for
     * display purposes -- that method is protected on the payout service
     * and scoped to a real Order, so this is a thin, read-only equivalent
     * for showing the terms up front rather than reusing it directly.
     */
    private function resolveCommissionDefinition($restaurant): array
    {
        $type = $restaurant->commission_calculation_type;
        $value = $restaurant->commission_rate;

        if ($type === 'global' || $value === null || $value === '') {
            return [
                'type' => CommissionSetting::getCalculationType('restaurant'),
                'value' => (float) (CommissionSetting::getRate('restaurant') ?: 0),
            ];
        }

        return [
            'type' => $type ?: CommissionSetting::TYPE_PERCENTAGE,
            'value' => (float) $value,
        ];
    }

    private function formatPlan(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'scope' => $plan->scope,
            'billing_period' => $plan->billing_period,
            'validity_days' => $plan->validity_days,
            'price' => (float) $plan->price,
            'currency' => $plan->currency,
            'order_limit' => $plan->order_limit,
            'menu_item_limit' => $plan->menu_item_limit,
            'stories_per_day' => $plan->stories_per_day,
            'story_duration_hours' => $plan->story_duration_hours,
            'story_menu_link' => (bool) $plan->story_menu_link,
            'trial_days' => $plan->trial_days,
            'includes_stories' => $plan->scope === 'stories_addon' ? true : (bool) $plan->includes_stories,
            'description' => $plan->description,
        ];
    }

    private function formatSubscription(RestaurantSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'scope' => $subscription->scope,
            'status' => $subscription->status,
            'plan' => $subscription->plan ? $this->formatPlan($subscription->plan) : null,
            'trial_ends_at' => optional($subscription->trial_ends_at)->toIso8601String(),
            'current_period_start' => optional($subscription->current_period_start)->toIso8601String(),
            'current_period_end' => optional($subscription->current_period_end)->toIso8601String(),
            'orders_used_in_period' => $subscription->orders_used_in_period,
            'order_limit' => $subscription->plan?->order_limit,
            'menu_item_limit' => $subscription->plan?->menu_item_limit,
            'auto_renew' => (bool) $subscription->auto_renew,
        ];
    }
}
