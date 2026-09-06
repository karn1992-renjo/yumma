<?php

namespace App\Services;

use App\Models\AdClick;
use App\Models\Restaurant;
use App\Models\RestaurantAdCampaign;
use Illuminate\Support\Collection;

/**
 * Stateless auction decision logic: given a pool of candidate restaurant IDs,
 * ranks their active ad campaigns by bid x quality and computes second-price
 * (GSP) billing amounts. Never touches money -- AdBillingService owns that.
 */
class AdAuctionService
{
    /**
     * Select up to $slots winning campaigns among the restaurants already
     * present in $context['restaurant_ids']. Never introduces a restaurant
     * that wasn't already in that pool.
     *
     * @param array{restaurant_ids: array<int>, surface: string} $context
     * @return Collection<int, array{restaurant_id:int, campaign_id:int, rank:float, competitor_rank:?float, price_to_charge:float}>
     */
    public function winnersFor(array $context, int $slots): Collection
    {
        $restaurantIds = $context['restaurant_ids'] ?? [];
        if (empty($restaurantIds) || $slots <= 0) {
            return collect();
        }

        $surface = $context['surface'] ?? null;
        $candidates = $this->rankedCandidates($restaurantIds, is_string($surface) ? $surface : null);

        $winners = collect();
        foreach ($candidates->take($slots)->values() as $index => $candidate) {
            $nextRank = $candidates->get($index + 1)['rank'] ?? null;
            $winners->push([
                'restaurant_id' => $candidate['campaign']->restaurant_id,
                'campaign_id' => $candidate['campaign']->id,
                'rank' => $candidate['rank'],
                'competitor_rank' => $nextRank,
                'price_to_charge' => $this->gspPrice($candidate, $nextRank),
            ]);
        }

        return $winners;
    }

    /**
     * Recompute the GSP price for a single campaign at click time, ranked
     * against all other currently-eligible campaigns (not scoped to a
     * specific result set, since the click event doesn't carry one).
     */
    public function priceForClick(RestaurantAdCampaign $campaign, ?string $surface = null): ?float
    {
        $allEligibleRestaurantIds = RestaurantAdCampaign::active()->pluck('restaurant_id')->all();
        $candidates = $this->rankedCandidates($allEligibleRestaurantIds, $surface);

        $position = $candidates->search(fn (array $candidate) => $candidate['campaign']->id === $campaign->id);
        if ($position === false) {
            return null;
        }

        $winner = $candidates->get($position);
        $nextRank = $candidates->get($position + 1)['rank'] ?? null;

        return $this->gspPrice($winner, $nextRank);
    }

    /**
     * @param array<int> $restaurantIds
     * @return Collection<int, array{campaign: RestaurantAdCampaign, quality: float, rank: float}>
     */
    private function rankedCandidates(array $restaurantIds, ?string $surface = null): Collection
    {
        return RestaurantAdCampaign::active()
            ->whereIn('restaurant_id', array_unique($restaurantIds))
            ->with('restaurant:id,rating,total_ratings')
            ->get()
            ->map(function (RestaurantAdCampaign $campaign) {
                $quality = $this->qualityScore($campaign->restaurant);

                return [
                    'campaign' => $campaign,
                    'quality' => $quality,
                    'rank' => (float) $campaign->max_cpc * $quality,
                ];
            })
            ->filter(fn (array $candidate) => $this->isEligible($candidate['campaign'], $surface))
            ->sortByDesc('rank')
            ->values();
    }

    private function qualityScore(?Restaurant $restaurant): float
    {
        $ceiling = max(1.0, (float) config('ads.quality_score_ceiling', 500));
        $raw = $restaurant
            ? ((float) ($restaurant->rating ?? 0) * 10) + (int) ($restaurant->total_ratings ?? 0)
            : 0.0;

        return min(1.0, max(0.1, $raw / $ceiling));
    }

    private function floorCpc(): float
    {
        return (float) config('ads.floor_cpc', 1.0);
    }

    private function gspPrice(array $winner, ?float $nextRank): float
    {
        $maxCpc = (float) $winner['campaign']->max_cpc;
        $floor = $this->floorCpc();

        if ($nextRank === null) {
            return min($maxCpc, $floor);
        }

        $price = round(($nextRank / max($winner['quality'], 0.0001)) + 0.01, 2);

        return min($maxCpc, max($floor, $price));
    }

    private function isEligible(RestaurantAdCampaign $campaign, ?string $surface = null): bool
    {
        if (! $campaign->allowsPlacement($surface)) {
            return false;
        }

        if (! $campaign->hasFundedWallet()) {
            return false;
        }

        $floor = $this->floorCpc();

        if ($campaign->total_budget !== null) {
            $remaining = (float) $campaign->total_budget - $this->totalSpent($campaign);
            if ($remaining < $floor) {
                return false;
            }
        }

        if ($campaign->daily_budget !== null) {
            $remainingToday = (float) $campaign->daily_budget - $this->dailySpent($campaign);
            if ($remainingToday < $floor) {
                return false;
            }
        }

        return true;
    }

    private function dailySpent(RestaurantAdCampaign $campaign): float
    {
        return $campaign->spentToday();
    }

    private function totalSpent(RestaurantAdCampaign $campaign): float
    {
        return (float) AdClick::where('restaurant_ad_campaign_id', $campaign->id)
            ->where('is_billed', true)
            ->sum('price_paid');
    }
}
