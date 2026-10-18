<?php
// app/Http/Controllers/Restaurant/BillingController.php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\CommissionSetting;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ResolvesRestaurantContext;

    /**
     * Shown both as a normal panel page and as the blocking view's
     * "choose a plan" destination -- subscribing to a new plan itself
     * still happens from the restaurant mobile app (the only surface with
     * a gateway checkout flow today); this page's own action is limited
     * to switching to commission billing with consent.
     */
    public function index()
    {
        $restaurant = $this->currentRestaurant();

        $subscription = $restaurant?->subscriptions()
            ->where('scope', 'full_plan')
            ->whereIn('status', ['pending_authorization', 'trialing', 'active', 'past_due', 'billing_choice_required'])
            ->latest()
            ->first();

        $commissionDefinition = $restaurant ? $this->resolveCommissionDefinition($restaurant) : null;

        return view('restaurant.billing.index', [
            'restaurant' => $restaurant,
            'subscription' => $subscription,
            'commissionDefinition' => $commissionDefinition,
        ]);
    }

    public function switchToCommission(Request $request, SubscriptionService $subscriptionService): RedirectResponse
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return back()->with('error', 'Restaurant not found.');
        }

        $request->validate([
            'consent' => 'required|accepted',
        ]);

        $definition = $this->resolveCommissionDefinition($restaurant);

        $subscriptionService->switchToCommissionWithConsent($restaurant, $definition['value'], $request->user());

        return redirect()->route('restaurant.dashboard')->with('success', 'Switched to commission billing.');
    }

    /**
     * Mirrors Api\SubscriptionBillingController::resolveCommissionDefinition()
     * -- kept as a separate copy since one is a JSON endpoint for the
     * Flutter app and this is a Blade controller, and the computation is
     * a thin, read-only restaurant-override-vs-global lookup, not worth
     * sharing via a third file for two call sites.
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
}
