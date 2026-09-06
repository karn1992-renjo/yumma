<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\AdClick;
use App\Models\AdImpression;
use App\Models\AppSetting;
use App\Models\RestaurantAdCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdCampaignController extends Controller
{
    use ResolvesRestaurantContext;

    public function index(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $campaigns = RestaurantAdCampaign::where('restaurant_id', $restaurant->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(20);

        $campaigns->setCollection(
            $campaigns->getCollection()->map(fn (RestaurantAdCampaign $campaign) => $this->campaignPayload($campaign))
        );

        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    public function store(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $validated = $this->validatedCampaignPayload($request);

        $campaign = RestaurantAdCampaign::create(array_merge($validated, [
            'restaurant_id' => $restaurant->id,
            'status' => RestaurantAdCampaign::STATUS_DRAFT,
        ]));

        return response()->json(['success' => true, 'data' => $this->campaignPayload($campaign)], 201);
    }

    public function show(Request $request, $id)
    {
        $campaign = $this->ownedCampaign($request, $id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->campaignPayload($campaign)]);
    }

    public function update(Request $request, $id)
    {
        $campaign = $this->ownedCampaign($request, $id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }

        if (! in_array($campaign->status, [RestaurantAdCampaign::STATUS_DRAFT, RestaurantAdCampaign::STATUS_REJECTED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft or rejected campaigns can be edited. Pause the campaign first to make changes.',
            ], 422);
        }

        $campaign->update($this->validatedCampaignPayload($request));

        return response()->json(['success' => true, 'data' => $this->campaignPayload($campaign)]);
    }

    public function submit(Request $request, $id)
    {
        $campaign = $this->ownedCampaign($request, $id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }

        if (! in_array($campaign->status, [RestaurantAdCampaign::STATUS_DRAFT, RestaurantAdCampaign::STATUS_REJECTED], true)) {
            return response()->json(['success' => false, 'message' => 'Only draft or rejected campaigns can be submitted for review.'], 422);
        }

        if (! $campaign->hasFundedWallet()) {
            $balance = $campaign->wallet()?->balance ?? 0;

            return response()->json([
                'success' => false,
                'message' => "Your ad wallet balance is too low to run this campaign. It needs at least this campaign's max CPC ({$campaign->max_cpc}) available; current balance is {$balance}. Top up your ad wallet before submitting for review.",
            ], 422);
        }

        $campaign->forceFill([
            'status' => RestaurantAdCampaign::STATUS_PENDING_REVIEW,
            'rejected_reason' => null,
        ])->save();

        return response()->json(['success' => true, 'data' => $this->campaignPayload($campaign), 'message' => 'Submitted for review.']);
    }

    public function pause(Request $request, $id)
    {
        $campaign = $this->ownedCampaign($request, $id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }

        if ($campaign->status !== RestaurantAdCampaign::STATUS_ACTIVE) {
            return response()->json(['success' => false, 'message' => 'Only active campaigns can be paused.'], 422);
        }

        $campaign->forceFill([
            'status' => RestaurantAdCampaign::STATUS_PAUSED,
            'paused_reason' => 'Paused by restaurant owner',
        ])->save();

        return response()->json(['success' => true, 'data' => $this->campaignPayload($campaign)]);
    }

    public function resume(Request $request, $id)
    {
        $campaign = $this->ownedCampaign($request, $id);
        if (! $campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }

        if (! in_array($campaign->status, [RestaurantAdCampaign::STATUS_PAUSED, RestaurantAdCampaign::STATUS_BUDGET_EXHAUSTED], true)) {
            return response()->json(['success' => false, 'message' => 'Only paused or budget-exhausted campaigns can be resumed.'], 422);
        }

        if (! $campaign->hasFundedWallet()) {
            $balance = $campaign->wallet()?->balance ?? 0;

            return response()->json([
                'success' => false,
                'message' => "Your ad wallet balance is too low to resume this campaign. It needs at least this campaign's max CPC ({$campaign->max_cpc}) available; current balance is {$balance}. Top up your ad wallet first.",
            ], 422);
        }

        $campaign->forceFill([
            'status' => RestaurantAdCampaign::STATUS_ACTIVE,
            'paused_reason' => null,
        ])->save();

        return response()->json(['success' => true, 'data' => $this->campaignPayload($campaign)]);
    }

    public function performance(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $startDate = $request->filled('start_date') ? $request->date('start_date') : now()->subDays(29)->startOfDay();
        $endDate = $request->filled('end_date') ? $request->date('end_date') : now()->endOfDay();
        $currencySymbol = AppSetting::sanitizedCurrencySymbol();
        $currencyDecimals = AppSetting::currencyDecimals();

        $campaignIds = RestaurantAdCampaign::where('restaurant_id', $restaurant->id)->pluck('id');

        $clicksQuery = AdClick::whereIn('restaurant_ad_campaign_id', $campaignIds)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $impressions = AdImpression::whereIn('restaurant_ad_campaign_id', $campaignIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $billedClicks = (clone $clicksQuery)->where('is_billed', true);
        $totalSpend = round((float) (clone $billedClicks)->sum('price_paid'), 2);
        $totalClicks = (clone $clicksQuery)->count();
        $ctr = $impressions > 0 ? round(($totalClicks / $impressions) * 100, 2) : 0.0;
        $avgCpc = $totalClicks > 0 ? round($totalSpend / max(1, $billedClicks->count()), 2) : 0.0;

        $topCampaigns = DB::table('ad_clicks')
            ->join('restaurant_ad_campaigns', 'ad_clicks.restaurant_ad_campaign_id', '=', 'restaurant_ad_campaigns.id')
            ->whereIn('ad_clicks.restaurant_ad_campaign_id', $campaignIds)
            ->whereBetween('ad_clicks.created_at', [$startDate, $endDate])
            ->where('ad_clicks.is_billed', true)
            ->groupBy('restaurant_ad_campaigns.id', 'restaurant_ad_campaigns.name')
            ->selectRaw('restaurant_ad_campaigns.id, restaurant_ad_campaigns.name')
            ->selectRaw('COUNT(*) as clicks')
            ->selectRaw('SUM(ad_clicks.price_paid) as spend')
            ->orderByDesc('spend')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'clicks' => (int) $row->clicks,
                'spend' => round((float) $row->spend, 2),
                'spend_label' => $currencySymbol . number_format((float) $row->spend, $currencyDecimals),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'active_campaigns' => RestaurantAdCampaign::where('restaurant_id', $restaurant->id)
                    ->where('status', RestaurantAdCampaign::STATUS_ACTIVE)->count(),
                'total_campaigns' => $campaignIds->count(),
                'impressions' => (int) $impressions,
                'clicks' => (int) $totalClicks,
                'ctr' => $ctr,
                'spend' => $totalSpend,
                'spend_label' => $currencySymbol . number_format($totalSpend, $currencyDecimals),
                'avg_cpc' => $avgCpc,
                'avg_cpc_label' => $currencySymbol . number_format($avgCpc, $currencyDecimals),
                'top_campaigns' => $topCampaigns->values()->all(),
            ],
        ]);
    }

    private function ownedCampaign(Request $request, $id): ?RestaurantAdCampaign
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return null;
        }

        return RestaurantAdCampaign::where('restaurant_id', $restaurant->id)->find($id);
    }

    private function campaignPayload(RestaurantAdCampaign $campaign): array
    {
        $campaign->refresh();

        $impressions = AdImpression::where('restaurant_ad_campaign_id', $campaign->id)->count();
        $clicks = AdClick::where('restaurant_ad_campaign_id', $campaign->id)->count();
        $billedClicks = AdClick::where('restaurant_ad_campaign_id', $campaign->id)
            ->where('is_billed', true);
        $spend = round((float) (clone $billedClicks)->sum('price_paid'), 2);
        $billedClickCount = (clone $billedClicks)->count();
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
        $avgCpc = $billedClickCount > 0 ? round($spend / $billedClickCount, 2) : 0.0;
        $dailyImpressions = DB::table('ad_impressions')
            ->where('restaurant_ad_campaign_id', $campaign->id)
            ->groupBy('day')
            ->orderBy('day')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as impressions')
            ->pluck('impressions', 'day');
        $dailyClicks = DB::table('ad_clicks')
            ->where('restaurant_ad_campaign_id', $campaign->id)
            ->groupBy('day')
            ->orderBy('day')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as clicks')
            ->selectRaw('SUM(CASE WHEN is_billed = 1 THEN price_paid ELSE 0 END) as spend')
            ->get()
            ->keyBy('day');
        $dailyMetrics = $dailyImpressions->keys()
            ->merge($dailyClicks->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $day) use ($dailyImpressions, $dailyClicks) {
                $clickRow = $dailyClicks->get($day);

                return [
                    'date' => $day,
                    'impressions' => (int) ($dailyImpressions[$day] ?? 0),
                    'clicks' => (int) ($clickRow?->clicks ?? 0),
                    'spend' => round((float) ($clickRow?->spend ?? 0), 2),
                ];
            });
        $wallet = $campaign->wallet();

        return array_merge($campaign->toArray(), [
            'metrics' => [
                'impressions' => (int) $impressions,
                'clicks' => (int) $clicks,
                'billed_clicks' => (int) $billedClickCount,
                'spend' => $spend,
                'ctr' => $ctr,
                'avg_cpc' => $avgCpc,
            ],
            'daily_metrics' => $dailyMetrics->all(),
            'budget_remaining' => $campaign->budgetRemaining(),
            'wallet_balance' => $wallet?->balance !== null ? (float) $wallet->balance : 0.0,
            'has_funded_wallet' => $campaign->hasFundedWallet(),
        ]);
    }
    private function validatedCampaignPayload(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'max_cpc' => 'required|numeric|min:0.5|max:1000',
            'daily_budget' => 'nullable|numeric|min:1|max:1000000',
            'total_budget' => 'nullable|numeric|min:1|max:1000000',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'targeting' => 'nullable|array',
        ]);
    }
}


