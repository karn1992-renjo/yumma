<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdClick;
use App\Models\AdFraudSignal;
use App\Models\Restaurant;
use App\Models\RestaurantAdCampaign;
use Illuminate\Http\Request;

class AdsEngineController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'status' => ['nullable', 'in:draft,pending_review,active,paused,budget_exhausted,rejected,ended'],
        ]);

        $campaigns = RestaurantAdCampaign::query()
            ->with('restaurant:id,name')
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.ads-engine.index', [
            'campaigns' => $campaigns,
            'restaurants' => Restaurant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'filters' => $filters,
            'stats' => [
                'pending_review' => RestaurantAdCampaign::where('status', RestaurantAdCampaign::STATUS_PENDING_REVIEW)->count(),
                'active' => RestaurantAdCampaign::where('status', RestaurantAdCampaign::STATUS_ACTIVE)->count(),
                'open_fraud_signals' => AdFraudSignal::where('status', 'open')->count(),
                'total_spend' => round((float) AdClick::where('is_billed', true)->sum('price_paid'), 2),
            ],
        ]);
    }

    public function approve(Request $request, RestaurantAdCampaign $campaign)
    {
        if ($campaign->status !== RestaurantAdCampaign::STATUS_PENDING_REVIEW) {
            return back()->with('error', 'Only campaigns pending review can be approved.');
        }

        if (! $campaign->hasFundedWallet()) {
            $balance = $campaign->wallet()?->balance ?? 0;
            $message = "Can't approve: the restaurant's ad wallet balance ({$balance}) is below this campaign's max CPC ({$campaign->max_cpc}), so it would never win an auction slot. Ask the restaurant to top up first.";

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $campaign->forceFill([
            'status' => RestaurantAdCampaign::STATUS_ACTIVE,
            'admin_reviewed_by' => auth()->id(),
            'admin_reviewed_at' => now(),
            'rejected_reason' => null,
        ])->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Campaign approved and is now live.');
    }

    public function reject(Request $request, RestaurantAdCampaign $campaign)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($campaign->status !== RestaurantAdCampaign::STATUS_PENDING_REVIEW) {
            return back()->with('error', 'Only campaigns pending review can be rejected.');
        }

        $campaign->forceFill([
            'status' => RestaurantAdCampaign::STATUS_REJECTED,
            'admin_reviewed_by' => auth()->id(),
            'admin_reviewed_at' => now(),
            'rejected_reason' => $validated['reason'] ?? 'Rejected by admin',
        ])->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Campaign rejected.');
    }

    public function clickLog(Request $request)
    {
        $filters = $request->validate([
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'billed' => ['nullable', 'in:yes,no'],
        ]);

        $clicks = AdClick::query()
            ->with(['campaign:id,name', 'restaurant:id,name'])
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id))
            ->when(($filters['billed'] ?? null) === 'yes', fn ($query) => $query->where('is_billed', true))
            ->when(($filters['billed'] ?? null) === 'no', fn ($query) => $query->where('is_billed', false))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.ads-engine.click-log', [
            'clicks' => $clicks,
            'restaurants' => Restaurant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function fraudSignals(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,reviewed,dismissed'],
            'severity' => ['nullable', 'in:low,medium,high'],
        ]);

        $signals = AdFraudSignal::query()
            ->with(['campaign:id,name,restaurant_id', 'campaign.restaurant:id,name'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return view('admin.ads-engine.fraud-signals', [
            'signals' => $signals,
            'filters' => $filters,
        ]);
    }

    public function resolveFraudSignal(Request $request, AdFraudSignal $signal)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:reviewed,dismissed'],
        ]);

        $signal->forceFill([
            'status' => $validated['status'],
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ])->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Fraud signal marked as ' . $validated['status'] . '.');
    }
}
