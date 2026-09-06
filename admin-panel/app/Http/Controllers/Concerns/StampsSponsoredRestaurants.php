<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AdImpression;
use App\Services\AdAuctionService;
use Illuminate\Support\Collection;

/**
 * Runs the live CPC auction against restaurants already present in a result
 * set (never introduces one outside it), stamps is_sponsored/ad_campaign_id
 * onto the winning models, logs ad_impressions, and pins sponsored rows to
 * the front of an already-built response payload. Shared by every
 * customer-facing restaurant listing endpoint (home sections, nearby,
 * search, and the legacy website) so the same auction/impression logic
 * isn't reimplemented per controller.
 */
trait StampsSponsoredRestaurants
{
    private function stampSponsoredRestaurants(Collection $restaurants, string $surface, int $slots = 2): void
    {
        if ($restaurants->isEmpty()) {
            return;
        }

        $winners = app(AdAuctionService::class)->winnersFor([
            'restaurant_ids' => $restaurants->pluck('id')->all(),
            'surface' => $surface,
        ], $slots);

        if ($winners->isEmpty()) {
            return;
        }

        $winnersByRestaurantId = $winners->keyBy('restaurant_id');
        $impressionRows = [];

        foreach ($restaurants as $restaurant) {
            $winner = $winnersByRestaurantId->get($restaurant->id);
            if (! $winner) {
                continue;
            }

            $restaurant->setAttribute('is_sponsored', true);
            $restaurant->setAttribute('ad_campaign_id', $winner['campaign_id']);

            $impressionRows[] = [
                'restaurant_ad_campaign_id' => $winner['campaign_id'],
                'restaurant_id' => $restaurant->id,
                'user_id' => auth()->id(),
                'session_id' => null,
                'surface' => $surface,
                'created_at' => now(),
            ];
        }

        if (! empty($impressionRows)) {
            AdImpression::insert($impressionRows);
        }
    }

    private function pinSponsoredResultsToTop(Collection $data): Collection
    {
        $sponsored = $data->filter(fn ($row) => $row['is_sponsored'] ?? false)->values();
        if ($sponsored->isEmpty()) {
            return $data;
        }

        $organic = $data->reject(fn ($row) => $row['is_sponsored'] ?? false)->values();

        return $sponsored->merge($organic)->values();
    }
}
