<?php

namespace App\Services;

use App\Models\AdClick;
use App\Models\RestaurantAdCampaign;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Support\Facades\DB;

class AdBillingService
{
    /**
     * Locks the campaign row, recomputes the GSP price under lock, debits the
     * restaurant's ad wallet, and writes the click row -- all inside one
     * transaction, mirroring FlashResaleController::claim()'s lock -> validate
     * -> debit -> ledger shape. Never throws on a billing failure: the click
     * is still recorded (unbilled) so the caller can still let the customer
     * through to the restaurant regardless of outcome.
     */
    public function chargeClick(
        RestaurantAdCampaign $campaign,
        ?int $userId,
        ?string $sessionId,
        string $surface,
        ?string $ipAddress
    ): AdClick {
        return DB::transaction(function () use ($campaign, $userId, $sessionId, $surface, $ipAddress) {
            $locked = RestaurantAdCampaign::whereKey($campaign->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== RestaurantAdCampaign::STATUS_ACTIVE) {
                return $this->createUnbilledClick($locked ?? $campaign, $userId, $sessionId, $surface, $ipAddress);
            }

            $price = app(AdAuctionService::class)->priceForClick($locked, $surface);
            if ($price === null) {
                return $this->createUnbilledClick($locked, $userId, $sessionId, $surface, $ipAddress);
            }

            $click = $this->createUnbilledClick($locked, $userId, $sessionId, $surface, $ipAddress);

            $walletTransaction = app(AdWalletService::class)->debit(
                $locked->restaurant,
                $price,
                'ad_click',
                $click->id,
                "Ad click - {$locked->restaurant->name}"
            );

            if (! $walletTransaction) {
                // Lost the race to a concurrent click that just exhausted the
                // budget/balance -- restaurant gets an unbilled click rather
                // than a broken customer experience.
                return $click;
            }

            $click->forceFill([
                'price_paid' => $price,
                'is_billed' => true,
                'restaurant_ad_wallet_transaction_id' => $walletTransaction->id,
            ])->save();

            $locked->increment('spent_total', $price);
            $locked->increment('spent_today', $price);
            $locked->refresh();

            $this->maybeAutoPause($locked);

            return $click;
        });
    }

    private function createUnbilledClick(
        RestaurantAdCampaign $campaign,
        ?int $userId,
        ?string $sessionId,
        string $surface,
        ?string $ipAddress
    ): AdClick {
        return AdClick::create([
            'restaurant_ad_campaign_id' => $campaign->id,
            'restaurant_id' => $campaign->restaurant_id,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'surface' => $surface,
            'price_paid' => 0,
            'is_billed' => false,
            'ip_address' => $ipAddress,
        ]);
    }

    private function maybeAutoPause(RestaurantAdCampaign $campaign): void
    {
        if ($campaign->status !== RestaurantAdCampaign::STATUS_ACTIVE) {
            return;
        }

        $exhausted = ($campaign->daily_budget !== null && $campaign->spentToday() >= (float) $campaign->daily_budget)
            || ($campaign->total_budget !== null && (float) $campaign->spent_total >= (float) $campaign->total_budget);

        if (! $exhausted) {
            return;
        }

        $campaign->forceFill(['status' => RestaurantAdCampaign::STATUS_BUDGET_EXHAUSTED])->save();

        DB::afterCommit(function () use ($campaign) {
            $owner = $campaign->restaurant?->owner;
            if (! $owner) {
                return;
            }

            $owner->notify(new AppDatabaseNotification(
                'Ad campaign paused',
                "Your campaign '{$campaign->name}' paused - daily/total budget exhausted.",
                ['type' => 'ad_campaign_paused', 'role' => 'restaurant', 'campaign_id' => $campaign->id]
            ));
        });
    }
}
