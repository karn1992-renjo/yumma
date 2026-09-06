<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdImpression;
use App\Models\RestaurantAdCampaign;
use App\Services\AdBillingService;
use App\Services\AdFraudGuardService;
use Illuminate\Http\Request;

class AdTrackingController extends Controller
{
    public function trackImpression(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|integer|exists:restaurant_ad_campaigns,id',
            'surface' => 'required|string|max:32',
            'session_id' => 'nullable|string|max:100',
        ]);

        $campaign = RestaurantAdCampaign::find($validated['campaign_id']);

        AdImpression::create([
            'restaurant_ad_campaign_id' => $validated['campaign_id'],
            'restaurant_id' => $campaign?->restaurant_id,
            'user_id' => auth()->id(),
            'session_id' => $validated['session_id'] ?? null,
            'surface' => $validated['surface'],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Billable click. The customer-facing response always succeeds -- a
     * failed charge (lost a budget race, campaign paused between serve and
     * click, etc.) is never the customer's problem, only the restaurant's.
     * Fraud signal scanning is deferred until after the response is sent.
     */
    public function trackClick(Request $request, AdFraudGuardService $fraudGuard, AdBillingService $billing)
    {
        $validated = $request->validate([
            'campaign_id' => 'required|integer|exists:restaurant_ad_campaigns,id',
            'surface' => 'required|string|max:32',
            'session_id' => 'nullable|string|max:100',
        ]);

        $campaign = RestaurantAdCampaign::find($validated['campaign_id']);
        if (! $campaign) {
            return response()->json(['success' => true, 'restaurant_id' => null]);
        }

        $userId = auth()->id();
        $sessionId = $validated['session_id'] ?? null;

        if (! $fraudGuard->checkRateLimit($campaign->id, $userId, $sessionId)) {
            return response()->json(['success' => true, 'restaurant_id' => $campaign->restaurant_id]);
        }

        $click = $billing->chargeClick($campaign, $userId, $sessionId, $validated['surface'], $request->ip());

        dispatch(function () use ($fraudGuard, $click) {
            $fraudGuard->recordSignalsAsync($click);
        })->afterResponse();

        return response()->json([
            'success' => true,
            'restaurant_id' => $campaign->restaurant_id,
            'billed' => (bool) $click->is_billed,
        ]);
    }
}
