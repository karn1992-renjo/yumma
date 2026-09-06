<?php

namespace App\Services;

use App\Models\AdClick;
use App\Models\AdFraudSignal;
use App\Models\RestaurantAdCampaign;
use Illuminate\Support\Facades\Cache;

class AdFraudGuardService
{
    /**
     * Fast, synchronous pre-check: blocks a click before it ever reaches the
     * billing transaction if the same user/session clicked this campaign too
     * recently. Cache-backed, not a DB hit.
     */
    public function checkRateLimit(int $campaignId, ?int $userId, ?string $sessionId): bool
    {
        $identity = $userId ? "user:{$userId}" : "session:" . ($sessionId ?: 'anonymous');
        $key = "ad_click_rl:{$campaignId}:{$identity}";

        if (Cache::has($key)) {
            return false;
        }

        $minutes = (int) config('ads.click_rate_limit_minutes', 5);
        Cache::put($key, true, now()->addMinutes($minutes));

        return true;
    }

    /**
     * Deferred, low-priority signal scan -- meant to be called via
     * dispatch(fn () => ...)->afterResponse() so it never adds latency to the
     * click response. Writes AdFraudSignal rows for admin review only; never
     * blocks or reverses a click that already billed successfully.
     */
    public function recordSignalsAsync(AdClick $click): void
    {
        $campaign = RestaurantAdCampaign::find($click->restaurant_ad_campaign_id);
        if (! $campaign) {
            return;
        }

        $recentClicks = AdClick::where('restaurant_ad_campaign_id', $campaign->id)
            ->where('ip_address', $click->ip_address)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($click->ip_address && $recentClicks >= 5) {
            $this->upsert($campaign, $click, 'ip_click_burst', 'medium', 55, [
                'ip_address' => $click->ip_address,
                'recent_clicks' => $recentClicks,
            ]);
        }

        $impressions = \App\Models\AdImpression::where('restaurant_ad_campaign_id', $campaign->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $clicksToday = AdClick::where('restaurant_ad_campaign_id', $campaign->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($impressions > 0 && ($clicksToday / max(1, $impressions)) > 0.5 && $clicksToday >= 10) {
            $this->upsert($campaign, $click, 'abnormal_click_through_rate', 'high', 75, [
                'impressions_24h' => $impressions,
                'clicks_24h' => $clicksToday,
            ]);
        }
    }

    private function upsert(RestaurantAdCampaign $campaign, AdClick $click, string $type, string $severity, int $score, array $evidence): void
    {
        AdFraudSignal::updateOrCreate(
            [
                'restaurant_ad_campaign_id' => $campaign->id,
                'signal_type' => $type,
            ],
            [
                'ad_click_id' => $click->id,
                'severity' => $severity,
                'score' => $score,
                'status' => 'open',
                'evidence' => $evidence,
            ]
        );
    }
}
