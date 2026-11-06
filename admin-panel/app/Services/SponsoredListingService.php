<?php

namespace App\Services;

use App\Models\AdImpression;

/**
 * Runs the live ad auction over restaurant cards that are already in a result list (never adds a
 * restaurant that is not in it), marks the winners is_sponsored with their ad_campaign_id, logs the
 * impressions and pins them to the top. Used by every customer-facing restaurant list that does not
 * go through the StampsSponsoredRestaurants trait (saved, dining, theatre, universal search, similar).
 */
class SponsoredListingService
{
    /**
     * @param array<int, array<string, mixed>> $cards restaurant cards, each with an "id"
     * @return array<int, array<string, mixed>> the same cards, sponsored ones first
     */
    public function stampCards(array $cards, string $surface, int $slots = 2): array
    {
        $cards = array_values($cards);
        $ids = array_values(array_filter(array_map(fn ($c) => isset($c['id']) ? (int) $c['id'] : null, $cards)));
        if (! $ids) {
            return $cards;
        }

        try {
            $winners = app(AdAuctionService::class)->winnersFor(['restaurant_ids' => $ids, 'surface' => $surface], $slots)->keyBy('restaurant_id');
        } catch (\Throwable $e) {
            report($e);

            return $cards;            // ads must never break a listing
        }
        if ($winners->isEmpty()) {
            return $cards;
        }

        $impressions = [];
        foreach ($cards as &$card) {
            $winner = $winners->get((int) ($card['id'] ?? 0));
            if (! $winner) {
                continue;
            }
            $card['is_sponsored'] = true;
            $card['ad_campaign_id'] = $winner['campaign_id'];
            $impressions[] = [
                'restaurant_ad_campaign_id' => $winner['campaign_id'], 'restaurant_id' => (int) $card['id'],
                'user_id' => auth()->id(), 'session_id' => null, 'surface' => $surface, 'created_at' => now(),
            ];
        }
        unset($card);

        if ($impressions) {
            try {
                AdImpression::insert($impressions);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $sponsored = array_values(array_filter($cards, fn ($c) => $c['is_sponsored'] ?? false));
        $organic = array_values(array_filter($cards, fn ($c) => ! ($c['is_sponsored'] ?? false)));

        return array_merge($sponsored, $organic);
    }
}
