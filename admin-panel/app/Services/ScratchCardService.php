<?php

namespace App\Services;

use App\Models\CustomerScratchCard;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use App\Models\PromotionUsage;
use App\Models\RewardPointTransaction;
use App\Models\RewardRedemption;
use App\Models\ScratchCardRewardLog;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScratchCardService
{
    public function issueForOrder(Order $order, array $result = [], string $event = 'order_placed'): array
    {
        $issued = [];
        $lines = collect($result['discount_lines'] ?? [])
            ->filter(fn (array $line) => ($line['type'] ?? null) === 'scratch_card');

        foreach ($lines as $line) {
            $card = $this->issueFromLine($order, $line, null, $event);
            if ($card) {
                $issued[] = $card;
            }
        }

        return $issued;
    }

    public function issueForRecordedUsage(Order $order, string $event = 'payment_success'): array
    {
        $issued = [];
        $usages = PromotionUsage::query()
            ->where('order_id', $order->id)
            ->get();

        foreach ($usages as $usage) {
            foreach ((array) ($usage->discount_lines ?? []) as $line) {
                if (($line['type'] ?? null) !== 'scratch_card') {
                    continue;
                }

                $card = $this->issueFromLine($order, $line, $usage, $event);
                if ($card) {
                    $issued[] = $card;
                }
            }
        }

        return $issued;
    }

    public function reveal(CustomerScratchCard $card): CustomerScratchCard
    {
        return DB::transaction(function () use ($card) {
            $card = CustomerScratchCard::query()
                ->with(['promotion', 'order.restaurant', 'restaurant', 'user'])
                ->whereKey($card->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($card->isRevealed()) {
                $this->log($card, 'duplicate_reveal_blocked', $card->reward);

                return $card->fresh(['promotion', 'order.restaurant', 'restaurant', 'walletTransaction', 'redemptions']);
            }

            if ($card->expires_at && $card->expires_at->isPast()) {
                $card->update(['status' => 'expired']);
                $this->log($card, 'expired');

                return $card->fresh(['promotion', 'order.restaurant', 'restaurant', 'walletTransaction', 'redemptions']);
            }

            if (! $this->canReveal($card)) {
                abort(422, $this->revealLockedReason($card));
            }

            $card->update([
                'status' => 'scratched',
                'scratched_at' => $card->scratched_at ?? now(),
            ]);
            $this->log($card, 'scratched');

            $pool = $this->normalizeRewardPool((array) $card->reward_pool);
            $selected = $this->pickWeightedReward($pool);
            $reward = $this->normalizeReward($selected);

            if (! $this->rewardIsWithinLimits($card, $reward)) {
                $reward = [
                    'key' => 'limit_reached',
                    'type' => 'no_reward',
                    'title' => 'Better luck next time',
                    'amount' => 0,
                    'source' => 'limit_fallback',
                ];
            }

            $this->log($card, 'reward_generated', $reward);
            $settled = app(RewardFulfillmentService::class)->fulfillScratchReward($card, $reward);
            $reward = array_filter(array_merge($reward, $settled), fn ($value) => $value !== null && $value !== []);
            $credited = in_array(($settled['fulfillment_status'] ?? null), ['credited', 'empty'], true);

            $card->update([
                'status' => $credited ? 'reward_credited' : 'reward_generated',
                'reward' => $reward,
                'wallet_transaction_id' => $reward['wallet_transaction_id'] ?? null,
                'revealed_at' => now(),
                'reward_generated_at' => now(),
                'reward_credited_at' => $credited ? now() : null,
            ]);

            $this->log($card, $credited ? 'reward_credited' : 'revealed', $reward);
            $this->notify($card, 'Reward revealed', $reward['title'] ?? 'Your scratch card reward is ready.', [
                'event' => 'scratch_card_revealed',
                'reward_type' => $reward['type'] ?? null,
            ]);

            return $card->fresh(['promotion', 'order.restaurant', 'restaurant', 'walletTransaction', 'redemptions']);
        });
    }

    public function markViewed(CustomerScratchCard $card): CustomerScratchCard
    {
        if (! $card->viewed_at && $card->status === 'issued') {
            $card->update([
                'status' => 'viewed',
                'viewed_at' => now(),
            ]);
            $this->log($card->fresh(), 'viewed');
        }

        return $card->fresh(['promotion', 'order.restaurant', 'restaurant', 'walletTransaction', 'redemptions']);
    }

    public function payload(CustomerScratchCard $card): array
    {
        $reward = (array) ($card->reward ?? []);
        $restaurant = $card->relationLoaded('restaurant')
            ? $card->restaurant
            : ($card->order?->restaurant);

        $canReveal = $this->canReveal($card);

        return [
            'id' => $card->id,
            'promotion_id' => $card->promotion_id,
            'order_id' => $card->order_id,
            'order_number' => $card->order?->order_number,
            'restaurant_id' => $card->restaurant_id,
            'restaurant_name' => $restaurant?->name,
            'restaurant_logo_url' => $restaurant ? MediaStorage::url($restaurant->logo_image) : null,
            'order_status' => $card->order?->status,
            'payment_method' => $card->order?->payment_method,
            'title' => $card->title,
            'status' => $card->status,
            'is_revealed' => $card->isRevealed(),
            'can_reveal' => $canReveal,
            'reveal_locked_reason' => $canReveal ? null : $this->revealLockedReason($card),
            'reward' => $card->isRevealed() ? $reward : null,
            'reward_title' => $card->isRevealed() ? ($reward['title'] ?? 'Reward') : 'Scratch to reveal',
            'reward_type' => $card->isRevealed() ? ($reward['type'] ?? 'custom') : null,
            'coupon_code' => $card->isRevealed() ? ($reward['coupon_code'] ?? null) : null,
            'gift_card_code' => $card->isRevealed() ? ($reward['gift_card_code'] ?? null) : null,
            'issued_at' => $card->issued_at,
            'viewed_at' => $card->viewed_at,
            'scratched_at' => $card->scratched_at,
            'revealed_at' => $card->revealed_at,
            'reward_generated_at' => $card->reward_generated_at,
            'reward_credited_at' => $card->reward_credited_at,
            'redeemed_at' => $card->redeemed_at,
            'expires_at' => $card->expires_at,
        ];
    }

    private function canReveal(CustomerScratchCard $card): bool
    {
        if ($card->isRevealed()) {
            return true;
        }

        $order = $card->relationLoaded('order') ? $card->order : $card->order()->first();
        if (! $order || ! $order->isCashOnDelivery()) {
            return true;
        }

        return (string) $order->status === 'delivered';
    }

    private function revealLockedReason(CustomerScratchCard $card): string
    {
        $order = $card->relationLoaded('order') ? $card->order : $card->order()->first();
        if ($order?->isCashOnDelivery() && (string) $order->status !== 'delivered') {
            return 'Scratch card reward can be claimed after this COD order is delivered.';
        }

        return 'Scratch card reward is not available yet.';
    }

    private function issueFromLine(Order $order, array $line, ?PromotionUsage $usage = null, string $event = 'order_placed'): ?CustomerScratchCard
    {
        $promotionId = $line['promotion_id'] ?? null;
        $promotion = $promotionId ? Promotion::query()->find($promotionId) : null;
        $pool = $this->normalizeRewardPool(
            (array) data_get($line, 'reward_payload.scratch_card_pool', data_get($promotion, 'rewards.pool', []))
        );

        if ($pool === []) {
            return null;
        }

        $settings = (array) data_get($promotion, 'rewards.settings', []);
        $expiresInDays = max(1, (int) ($settings['expiry_days'] ?? data_get($promotion, 'rewards.expiry_days', 30)));
        if (! $this->shouldIssueForEvent($order, $settings, $event)) {
            return null;
        }

        $usage ??= PromotionUsage::query()
            ->where('order_id', $order->id)
            ->where('promotion_id', $promotionId)
            ->first();

        $card = CustomerScratchCard::firstOrCreate(
            [
                'promotion_id' => $promotionId,
                'order_id' => $order->id,
                'user_id' => $order->customer_id,
            ],
            [
                'promotion_usage_id' => $usage?->id,
                'restaurant_id' => $order->restaurant_id,
                'title' => $line['title'] ?? $promotion?->title ?? 'Scratch & Win',
                'status' => 'issued',
                'reward_pool' => $pool,
                'metadata' => [
                    'trigger' => data_get($settings, 'trigger', 'payment_success'),
                    'generate_reward_timing' => data_get($settings, 'generate_reward_timing', 'during_scratch'),
                ],
                'issued_at' => now(),
                'expires_at' => now()->addDays($expiresInDays),
            ]
        );

        if ($card->wasRecentlyCreated) {
            $this->log($card, 'issued', [
                'promotion_id' => $promotionId,
                'pool_count' => count($pool),
                'expires_in_days' => $expiresInDays,
            ]);
            $this->notify($card, 'Scratch card unlocked', 'You unlocked 1 scratch card from your order.', [
                'event' => 'scratch_card_unlocked',
            ]);
        }

        return $card->fresh(['promotion', 'order.restaurant', 'restaurant']);
    }

    private function shouldIssueForEvent(Order $order, array $settings, string $event): bool
    {
        $trigger = (string) ($settings['trigger'] ?? 'payment_success');

        if ($trigger === 'manual') {
            return false;
        }

        if ($trigger === 'payment_success') {
            return $event === 'payment_success' || ($event === 'order_placed' && $order->isCashOnDelivery());
        }

        if ($trigger === 'every_order') {
            return in_array($event, ['order_placed', 'payment_success'], true);
        }

        if ($trigger === 'every_n_orders') {
            if (! in_array($event, ['order_placed', 'payment_success'], true)) {
                return false;
            }

            $interval = max(1, (int) ($settings['every_n_orders'] ?? 1));
            $eligibleOrders = Order::query()
                ->where('customer_id', $order->customer_id)
                ->where('restaurant_id', $order->restaurant_id)
                ->whereKeyNot($order->id)
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->count() + 1;

            return $eligibleOrders % $interval === 0;
        }

        return $trigger === $event;
    }

    private function normalizeRewardPool(array $pool): array
    {
        return collect($pool)
            ->map(function ($entry, int $index) {
                if (is_array($entry)) {
                    $name = trim((string) ($entry['name'] ?? $entry['title'] ?? $entry['reward_name'] ?? ''));
                    $type = strtolower((string) ($entry['type'] ?? $entry['reward_type'] ?? 'custom'));
                    $value = $entry['value'] ?? $entry['amount'] ?? $entry['points'] ?? null;

                    return array_filter([
                        'key' => $entry['key'] ?? 'reward_' . ($index + 1),
                        'name' => $name !== '' ? $name : ucwords(str_replace('_', ' ', $type)),
                        'type' => $type,
                        'value' => $value !== null && $value !== '' ? (float) $value : null,
                        'probability' => isset($entry['probability']) ? (float) $entry['probability'] : null,
                        'max_redemptions' => isset($entry['max_redemptions']) ? (int) $entry['max_redemptions'] : null,
                        'daily_limit' => isset($entry['daily_limit']) ? (int) $entry['daily_limit'] : null,
                        'budget' => isset($entry['budget']) ? (float) $entry['budget'] : null,
                        'expiry_days' => isset($entry['expiry_days']) ? (int) $entry['expiry_days'] : null,
                        'priority' => isset($entry['priority']) ? (int) $entry['priority'] : null,
                        'metadata' => (array) ($entry['metadata'] ?? []),
                    ], fn ($value) => $value !== null && $value !== []);
                }

                $reward = $this->parseLegacyRewardLine((string) $entry);
                $reward['key'] = 'legacy_' . ($index + 1);

                return $reward;
            })
            ->filter(fn (array $entry) => trim((string) ($entry['name'] ?? $entry['title'] ?? '')) !== '')
            ->values()
            ->all();
    }

    private function pickWeightedReward(array $pool): array
    {
        if ($pool === []) {
            return ['type' => 'no_reward', 'name' => 'Better luck next time', 'value' => 0];
        }

        usort($pool, fn (array $a, array $b) => (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0));
        $fallbackWeight = 100 / max(1, count($pool));
        $total = array_sum(array_map(fn (array $entry) => (float) ($entry['probability'] ?? $fallbackWeight), $pool));
        $cursor = random_int(1, max(1, (int) round($total * 100))) / 100;

        foreach ($pool as $entry) {
            $cursor -= (float) ($entry['probability'] ?? $fallbackWeight);
            if ($cursor <= 0) {
                return $entry;
            }
        }

        return Arr::last($pool);
    }

    private function normalizeReward(array $entry): array
    {
        $type = strtolower((string) ($entry['type'] ?? 'custom'));
        $amount = (float) ($entry['value'] ?? $entry['amount'] ?? 0);
        $title = (string) ($entry['name'] ?? $entry['title'] ?? ucwords(str_replace('_', ' ', $type)));

        if (in_array($type, ['empty', 'no_reward', 'better_luck'], true)) {
            $type = 'no_reward';
            $amount = 0;
        }

        return array_filter([
            'key' => $entry['key'] ?? Str::slug($title) ?: 'reward',
            'type' => $type,
            'title' => $title,
            'amount' => $amount,
            'points' => $type === 'reward_points' ? (int) ($entry['points'] ?? $amount) : null,
            'probability' => $entry['probability'] ?? null,
            'priority' => $entry['priority'] ?? null,
            'max_redemptions' => $entry['max_redemptions'] ?? null,
            'daily_limit' => $entry['daily_limit'] ?? null,
            'budget' => $entry['budget'] ?? null,
            'expiry_days' => $entry['expiry_days'] ?? null,
            'metadata' => (array) ($entry['metadata'] ?? []),
        ], fn ($value) => $value !== null && $value !== []);
    }

    private function settleReward(CustomerScratchCard $card, array $reward): array
    {
        $type = (string) ($reward['type'] ?? 'custom');
        if ($type === 'no_reward') {
            return ['redemption_id' => $this->recordRedemption($card, $reward, ['status' => 'empty'])->id];
        }

        if ($type === 'wallet_cashback' || $type === 'wallet_credit' || $type === 'cashback') {
            $transaction = $this->creditWallet($card, (float) ($reward['amount'] ?? 0));
            $redemption = $this->recordRedemption($card, $reward, [
                'wallet_transaction_id' => $transaction?->id,
                'status' => $transaction ? 'credited' : 'failed',
            ]);

            return [
                'wallet_transaction_id' => $transaction?->id,
                'redemption_id' => $redemption->id,
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
            ];
        }

        if ($type === 'gift_voucher' || $type === 'gift_card') {
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
            ];
        }

        $redemption = $this->recordRedemption($card, $reward, ['status' => 'issued']);

        return ['redemption_id' => $redemption->id];
    }

    private function rewardIsWithinLimits(CustomerScratchCard $card, array $reward): bool
    {
        $key = (string) ($reward['key'] ?? $reward['title'] ?? '');
        $type = (string) ($reward['type'] ?? 'custom');
        $maxRedemptions = (int) ($reward['max_redemptions'] ?? 0);
        $dailyLimit = (int) ($reward['daily_limit'] ?? 0);

        $query = RewardRedemption::query()
            ->where('promotion_id', $card->promotion_id)
            ->where('reward_type', $type)
            ->where('payload->reward_key', $key);

        if ($maxRedemptions > 0 && (clone $query)->count() >= $maxRedemptions) {
            return false;
        }

        if ($dailyLimit > 0 && (clone $query)->whereDate('created_at', now()->toDateString())->count() >= $dailyLimit) {
            return false;
        }

        return true;
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
            'meta' => [
                'reward_key' => $reward['key'] ?? null,
            ],
        ]);

        $this->notify($card, 'Reward points credited', $points . ' reward points were added to your account.', [
            'event' => 'reward_points_credited',
            'points' => $points,
        ]);

        return $transaction;
    }

    private function createGiftCard(CustomerScratchCard $card, array $reward): GiftCard
    {
        $expiresAt = $this->rewardExpiry($card, $reward);

        return GiftCard::create([
            'code' => $this->uniqueCode('GIFT'),
            'title' => $reward['title'] ?? 'Scratch card gift voucher',
            'amount' => max(0, (float) ($reward['amount'] ?? 0)),
            'max_redemptions' => 1,
            'expires_at' => $expiresAt,
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
            'owner_id' => null,
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

    private function parseLegacyRewardLine(string $line): array
    {
        $label = trim($line);
        $normalized = Str::lower($label);
        preg_match('/(\d+(?:\.\d+)?)/', $label, $matches);
        $amount = isset($matches[1]) ? (float) $matches[1] : 0.0;

        if (str_contains($normalized, 'better luck') || str_contains($normalized, 'try again')) {
            return ['type' => 'no_reward', 'name' => $label !== '' ? $label : 'Better luck next time', 'value' => 0];
        }

        if (str_contains($normalized, 'point')) {
            return ['type' => 'reward_points', 'name' => $label, 'value' => $amount];
        }

        if (str_contains($normalized, 'free delivery')) {
            return ['type' => 'free_delivery', 'name' => $label, 'value' => 0];
        }

        if (str_contains($normalized, 'wallet') || str_contains($normalized, 'cashback') || str_contains($normalized, 'cash back')) {
            return ['type' => 'wallet_cashback', 'name' => $label, 'value' => $amount];
        }

        if (str_contains($normalized, 'voucher') || str_contains($normalized, 'gift card')) {
            return ['type' => 'gift_voucher', 'name' => $label, 'value' => $amount];
        }

        if (str_contains($normalized, 'coupon')) {
            return ['type' => 'flat_discount', 'name' => $label, 'value' => $amount];
        }

        return [
            'type' => $amount > 0 ? 'wallet_cashback' : 'custom',
            'name' => $label !== '' ? $label : 'Surprise reward',
            'value' => $amount,
        ];
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

    private function log(CustomerScratchCard $card, string $eventType, ?array $payload = null): void
    {
        ScratchCardRewardLog::create([
            'scratch_card_id' => $card->id,
            'promotion_id' => $card->promotion_id,
            'order_id' => $card->order_id,
            'user_id' => $card->user_id,
            'event_type' => $eventType,
            'reward_type' => $payload['type'] ?? null,
            'payload' => $payload,
        ]);
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
            // Notifications must not block reward settlement.
        }
    }
}
