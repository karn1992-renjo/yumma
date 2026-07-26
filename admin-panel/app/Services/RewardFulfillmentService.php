<?php

namespace App\Services;

use App\Models\CustomerScratchCard;
use App\Models\GiftCard;
use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use App\Models\RewardPointTransaction;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RewardFulfillmentService
{
    public function fulfillScratchReward(CustomerScratchCard $card, array $reward): array
    {
        return DB::transaction(function () use ($card, $reward) {
            $type = (string) ($reward['type'] ?? 'custom');

            if ($type === 'no_reward') {
                return [
                    'redemption_id' => $this->recordRedemption($card, $reward, ['status' => 'empty'])->id,
                    'fulfillment_status' => 'empty',
                ];
            }

            if (in_array($type, ['wallet_cashback', 'wallet_credit', 'cashback'], true)) {
                $transaction = $this->creditWallet($card, (float) ($reward['amount'] ?? 0));
                $redemption = $this->recordRedemption($card, $reward, [
                    'wallet_transaction_id' => $transaction?->id,
                    'status' => $transaction ? 'credited' : 'failed',
                ]);

                return [
                    'wallet_transaction_id' => $transaction?->id,
                    'redemption_id' => $redemption->id,
                    'fulfillment_status' => $transaction ? 'credited' : 'failed',
                ];
            }

            if ($type === 'reward_points') {
                $points = max(0, (int) ($reward['points'] ?? $reward['amount'] ?? 0));
                $transaction = $this->creditRewardPoints($card, $points, $reward);
                $redemption = $this->recordRedemption($card, $reward, [
                    'points' => $points,
                    'status' => $transaction ? 'credited' : 'failed',
                ]);

                return [
                    'reward_point_transaction_id' => $transaction?->id,
                    'redemption_id' => $redemption->id,
                    'fulfillment_status' => $transaction ? 'credited' : 'failed',
                ];
            }

            if (in_array($type, ['gift_voucher', 'gift_card'], true)) {
                $giftCard = $this->createGiftCard($card, $reward);
                $redemption = $this->recordRedemption($card, $reward, [
                    'gift_card_id' => $giftCard->id,
                    'code' => $giftCard->code,
                    'status' => 'issued',
                ]);

                return [
                    'gift_card_id' => $giftCard->id,
                    'gift_card_code' => $giftCard->code,
                    'redemption_id' => $redemption->id,
                    'fulfillment_status' => 'issued',
                ];
            }

            if ($this->isCouponReward($type)) {
                [$coupon, $generatedPromotion] = $this->createGeneratedCoupon($card, $reward);
                $redemption = $this->recordRedemption($card, $reward, [
                    'promotion_coupon_code_id' => $coupon->id,
                    'code' => $coupon->code,
                    'status' => 'issued',
                    'payload' => [
                        'generated_promotion_id' => $generatedPromotion->id,
                        'coupon_code' => $coupon->code,
                    ],
                ]);

                return [
                    'generated_promotion_id' => $generatedPromotion->id,
                    'promotion_coupon_code_id' => $coupon->id,
                    'coupon_code' => $coupon->code,
                    'redemption_id' => $redemption->id,
                    'fulfillment_status' => 'issued',
                ];
            }

            $redemption = $this->recordRedemption($card, $reward, ['status' => 'issued']);

            return [
                'redemption_id' => $redemption->id,
                'fulfillment_status' => 'issued',
            ];
        });
    }

    private function creditWallet(CustomerScratchCard $card, float $amount): ?WalletTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $card->user_id],
            ['balance' => 0, 'locked_balance' => 0, 'currency' => 'INR', 'is_active' => true]
        );
        $wallet->increment('balance', $amount);
        $wallet->refresh();

        $transaction = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => 'scratch_card',
            'reference_id' => $card->id,
            'description' => 'Scratch card reward: ' . $card->title,
            'meta' => [
                'scratch_card_id' => $card->id,
                'promotion_id' => $card->promotion_id,
                'order_id' => $card->order_id,
            ],
        ]);

        $this->notify($card, 'Wallet credited', 'Your scratch card cashback has been credited to wallet.', [
            'event' => 'wallet_credited',
            'amount' => $amount,
        ]);

        return $transaction;
    }

    private function creditRewardPoints(CustomerScratchCard $card, int $points, array $reward): ?RewardPointTransaction
    {
        if ($points <= 0) {
            return null;
        }

        $user = User::query()->whereKey($card->user_id)->lockForUpdate()->first();
        if (! $user) {
            return null;
        }

        $user->increment('reward_points_balance', $points);
        $user->refresh();

        $transaction = RewardPointTransaction::create([
            'user_id' => $user->id,
            'promotion_id' => $card->promotion_id,
            'scratch_card_id' => $card->id,
            'order_id' => $card->order_id,
            'type' => 'credit',
            'points' => $points,
            'balance_after' => (int) $user->reward_points_balance,
            'reference_type' => 'scratch_card',
            'reference_id' => $card->id,
            'description' => $reward['title'] ?? 'Scratch card reward points',
            'meta' => ['reward_key' => $reward['key'] ?? null],
        ]);

        $this->notify($card, 'Reward points credited', $points . ' reward points were added to your account.', [
            'event' => 'reward_points_credited',
            'points' => $points,
        ]);

        return $transaction;
    }

    private function createGiftCard(CustomerScratchCard $card, array $reward): GiftCard
    {
        return GiftCard::create([
            'code' => $this->uniqueCode('GIFT'),
            'title' => $reward['title'] ?? 'Scratch card gift voucher',
            'amount' => max(0, (float) ($reward['amount'] ?? 0)),
            'max_redemptions' => 1,
            'expires_at' => $this->rewardExpiry($card, $reward),
            'is_active' => true,
            'created_by' => null,
        ]);
    }

    private function createGeneratedCoupon(CustomerScratchCard $card, array $reward): array
    {
        $expiresAt = $this->rewardExpiry($card, $reward);
        $type = (string) ($reward['type'] ?? 'flat_discount');
        $promotionType = match ($type) {
            'free_delivery' => 'free_delivery',
            'percentage_discount', 'percentage' => 'percentage_discount',
            'buy_x_get_y', 'bogo', 'buy_2_get_1', 'buy_3_get_1', 'buy_3_get_2' => 'buy_x_get_y',
            'free_item', 'free_product', 'free_drink', 'free_dessert' => 'free_item',
            default => 'flat_discount',
        };
        $rewardType = match ($promotionType) {
            'free_delivery' => 'free_delivery',
            'percentage_discount' => 'percentage',
            'buy_x_get_y' => 'buy_x_get_y',
            'free_item' => 'free_item',
            default => 'flat',
        };
        $metadata = (array) ($reward['metadata'] ?? []);

        $promotion = Promotion::create([
            'owner_type' => 'system',
            'restaurant_id' => $card->restaurant_id,
            'title' => 'Scratch reward - ' . ($reward['title'] ?? 'Coupon'),
            'description' => 'Generated from scratch card #' . $card->id,
            'promotion_type' => $promotionType,
            'application_mode' => 'coupon',
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 900,
            'starts_at' => now(),
            'ends_at' => $expiresAt,
            'targets' => [
                'restaurant_ids' => $card->restaurant_id ? [(int) $card->restaurant_id] : [],
                'order_types' => (array) ($metadata['order_types'] ?? []),
                'platforms' => ['android', 'ios', 'web', 'pwa'],
            ],
            'conditions' => (array) ($metadata['conditions'] ?? []),
            'rewards' => array_filter([
                'type' => $rewardType,
                'value' => max(0, (float) ($reward['amount'] ?? 0)),
                'max_discount' => $metadata['max_discount'] ?? null,
                'buy_quantity' => $metadata['buy_quantity'] ?? null,
                'free_quantity' => $metadata['free_quantity'] ?? null,
                'free_item_id' => $metadata['free_item_id'] ?? null,
                'item_ids' => (array) ($metadata['item_ids'] ?? []),
                'category_ids' => (array) ($metadata['category_ids'] ?? []),
            ], fn ($value) => $value !== null && $value !== []),
            'visibility' => [
                'source' => 'scratch_card',
                'scratch_card_id' => $card->id,
            ],
            'total_usage_limit' => 1,
            'per_user_usage_limit' => 1,
            'migrated_from_type' => 'scratch_card',
            'migrated_from_id' => $card->id,
        ]);

        $coupon = PromotionCouponCode::create([
            'promotion_id' => $promotion->id,
            'code' => $this->uniqueCode('SCRATCH'),
            'user_id' => $card->user_id,
            'usage_limit' => 1,
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => $expiresAt,
            'metadata' => [
                'coupon_type' => 'scratch_card_reward',
                'scratch_card_id' => $card->id,
                'source_promotion_id' => $card->promotion_id,
                'reward_key' => $reward['key'] ?? null,
            ],
        ]);

        $this->notify($card, 'Coupon generated', 'Your scratch card coupon is ready: ' . $coupon->code, [
            'event' => 'scratch_coupon_generated',
            'coupon_code' => $coupon->code,
        ]);

        return [$coupon, $promotion];
    }

    private function recordRedemption(CustomerScratchCard $card, array $reward, array $extra = []): RewardRedemption
    {
        $payload = array_merge((array) ($extra['payload'] ?? []), [
            'reward_key' => $reward['key'] ?? null,
            'reward_title' => $reward['title'] ?? null,
            'metadata' => (array) ($reward['metadata'] ?? []),
        ]);

        return RewardRedemption::create([
            'user_id' => $card->user_id,
            'promotion_id' => $card->promotion_id,
            'scratch_card_id' => $card->id,
            'order_id' => $card->order_id,
            'promotion_coupon_code_id' => $extra['promotion_coupon_code_id'] ?? null,
            'wallet_transaction_id' => $extra['wallet_transaction_id'] ?? null,
            'gift_card_id' => $extra['gift_card_id'] ?? null,
            'reward_type' => $reward['type'] ?? 'custom',
            'status' => $extra['status'] ?? 'issued',
            'amount' => $reward['amount'] ?? null,
            'points' => $extra['points'] ?? ($reward['points'] ?? null),
            'code' => $extra['code'] ?? null,
            'payload' => $payload,
            'issued_at' => now(),
            'expires_at' => $this->rewardExpiry($card, $reward),
        ]);
    }

    private function isCouponReward(string $type): bool
    {
        return in_array($type, [
            'coupon',
            'free_delivery',
            'flat_discount',
            'percentage_discount',
            'percentage',
            'buy_x_get_y',
            'bogo',
            'buy_2_get_1',
            'buy_3_get_1',
            'buy_3_get_2',
            'free_item',
            'free_product',
            'free_drink',
            'free_dessert',
        ], true);
    }

    private function rewardExpiry(CustomerScratchCard $card, array $reward)
    {
        $days = (int) ($reward['expiry_days'] ?? data_get($card->promotion, 'rewards.settings.reward_expiry_days', 30));

        return now()->addDays(max(1, $days));
    }

    private function uniqueCode(string $prefix): string
    {
        do {
            $code = $prefix . strtoupper(Str::random(8));
        } while (
            PromotionCouponCode::where('code', $code)->exists()
            || GiftCard::where('code', $code)->exists()
        );

        return $code;
    }

    private function notify(CustomerScratchCard $card, string $title, string $body, array $data = []): void
    {
        try {
            $user = $card->relationLoaded('user') ? $card->user : User::query()->find($card->user_id);
            $user?->notify(new AppDatabaseNotification($title, $body, array_merge($data, [
                'target_app' => 'customer',
                'scratch_card_id' => $card->id,
                'promotion_id' => $card->promotion_id,
                'order_id' => $card->order_id,
            ])));
        } catch (\Throwable) {
            // Reward settlement should not fail because notification delivery failed.
        }
    }
}
