<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Models\Order;
use App\Models\PromotionCouponCode;
use App\Models\PromotionUsage;
use App\Models\RewardPointTransaction;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Models\UserReferral;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromotionRewardSettlementService
{
    public function settleForOrder(Order $order, string $event = 'order_confirmed'): array
    {
        $order = $order->fresh(['customer']);
        if (! $order || ! $order->isVisibleToRestaurant()) {
            return [];
        }

        $settled = [];
        $usages = PromotionUsage::query()
            ->where('order_id', $order->id)
            ->get();

        foreach ($usages as $usage) {
            foreach ((array) ($usage->discount_lines ?? []) as $index => $line) {
                $result = $this->settleLine($order, $usage, (array) $line, (int) $index, $event);
                if ($result !== null) {
                    $settled[] = $result;
                }
            }
        }

        return $settled;
    }

    private function settleLine(Order $order, PromotionUsage $usage, array $line, int $index, string $event): ?array
    {
        $type = strtolower((string) ($line['type'] ?? ''));
        if ($type === 'scratch_card') {
            return null;
        }

        $promotionId = (int) ($line['promotion_id'] ?? $usage->promotion_id ?? 0);
        $cashback = round((float) ($line['cashback_amount'] ?? 0), 2);
        $points = max(0, (int) ($line['reward_points'] ?? 0));
        $giftVoucher = round((float) ($line['gift_voucher_amount'] ?? 0), 2);

        if ($cashback <= 0 && $points <= 0 && $giftVoucher <= 0) {
            return null;
        }

        $isReferralBonus = $type === 'referral_bonus'
            || strtolower((string) ($line['bucket'] ?? '')) === 'referral_bonus';
        $referral = null;
        $recipient = $order->customer;

        if ($isReferralBonus) {
            $referral = UserReferral::query()
                ->where('referred_user_id', $order->customer_id)
                ->with('referrer')
                ->first();

            if (! $referral || ! $referral->referrer) {
                return null;
            }

            $recipient = $referral->referrer;
        }

        if (! $recipient) {
            return null;
        }

        $results = [];
        if ($cashback > 0) {
            $results[] = $this->settleWalletCredit($recipient, $order, $usage, $line, $index, $cashback, $isReferralBonus, $event);
        }

        if ($points > 0) {
            $results[] = $this->settleRewardPoints($recipient, $order, $usage, $line, $index, $points, $isReferralBonus, $event);
        }

        if ($giftVoucher > 0) {
            $results[] = $this->settleGiftVoucher($recipient, $order, $usage, $line, $index, $giftVoucher, $isReferralBonus, $event);
        }

        $results = array_values(array_filter($results));
        if ($isReferralBonus && $referral && $results !== []) {
            $firstRedemption = collect($results)
                ->pluck('redemption_id')
                ->filter()
                ->first();

            $referral->update(array_filter([
                'status' => 'credited',
                'qualified_order_id' => $order->id,
                'promotion_id' => $promotionId ?: null,
                'referrer_reward_redemption_id' => $firstRedemption,
                'bonus_type' => $this->referralBonusType($line),
                'amount' => $cashback > 0 || $giftVoucher > 0 ? max($cashback, $giftVoucher) : null,
                'points' => $points > 0 ? $points : null,
                'qualified_at' => $referral->qualified_at ?? now(),
                'credited_at' => now(),
                'metadata' => [
                    'event' => $event,
                    'order_id' => $order->id,
                    'promotion_id' => $promotionId ?: null,
                    'reward_results' => $results,
                ],
            ], fn ($value) => $value !== null));
        }

        return [
            'promotion_id' => $promotionId ?: null,
            'type' => $type,
            'referral_bonus' => $isReferralBonus,
            'results' => $results,
        ];
    }

    private function settleWalletCredit(
        User $user,
        Order $order,
        PromotionUsage $usage,
        array $line,
        int $index,
        float $amount,
        bool $isReferralBonus,
        string $event
    ): ?array {
        return DB::transaction(function () use ($user, $order, $usage, $line, $index, $amount, $isReferralBonus, $event) {
            $key = $this->settlementKey($order, $usage, $line, $index, 'wallet');
            $existing = RewardRedemption::query()->where('settlement_key', $key)->first();
            if ($existing) {
                return ['redemption_id' => $existing->id, 'status' => $existing->status, 'already_settled' => true];
            }

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0, 'locked_balance' => 0, 'currency' => 'INR', 'is_active' => true]
            );
            $wallet->increment('balance', $amount);
            $wallet->refresh();

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reference_type' => $isReferralBonus ? 'referral_bonus' : 'promotion_reward',
                'reference_id' => $order->id,
                'description' => ($isReferralBonus ? 'Referral bonus' : 'Promotion cashback') . ' for order #' . $order->order_number,
                'meta' => $this->settlementMeta($order, $usage, $line, $event),
            ]);

            $redemption = RewardRedemption::create([
                'user_id' => $user->id,
                'promotion_id' => $line['promotion_id'] ?? $usage->promotion_id,
                'order_id' => $order->id,
                'wallet_transaction_id' => $transaction->id,
                'reward_type' => $isReferralBonus ? 'referral_bonus' : 'wallet_cashback',
                'status' => 'credited',
                'amount' => $amount,
                'settlement_key' => $key,
                'payload' => $this->settlementMeta($order, $usage, $line, $event),
                'issued_at' => now(),
            ]);

            $this->notify($user, 'Wallet credited', 'Your reward of ' . $this->money($amount) . ' has been added to wallet.', [
                'event' => $event,
                'reward_type' => $redemption->reward_type,
                'order_id' => $order->id,
            ]);

            return [
                'redemption_id' => $redemption->id,
                'wallet_transaction_id' => $transaction->id,
                'status' => 'credited',
                'amount' => $amount,
            ];
        });
    }

    private function settleRewardPoints(
        User $user,
        Order $order,
        PromotionUsage $usage,
        array $line,
        int $index,
        int $points,
        bool $isReferralBonus,
        string $event
    ): ?array {
        return DB::transaction(function () use ($user, $order, $usage, $line, $index, $points, $isReferralBonus, $event) {
            $key = $this->settlementKey($order, $usage, $line, $index, 'points');
            $existing = RewardRedemption::query()->where('settlement_key', $key)->first();
            if ($existing) {
                return ['redemption_id' => $existing->id, 'status' => $existing->status, 'already_settled' => true];
            }

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if (! $lockedUser) {
                return null;
            }

            $lockedUser->increment('reward_points_balance', $points);
            $lockedUser->refresh();

            $transaction = RewardPointTransaction::create([
                'user_id' => $lockedUser->id,
                'promotion_id' => $line['promotion_id'] ?? $usage->promotion_id,
                'order_id' => $order->id,
                'type' => 'credit',
                'points' => $points,
                'balance_after' => (int) $lockedUser->reward_points_balance,
                'reference_type' => $isReferralBonus ? 'referral_bonus' : 'promotion_reward',
                'reference_id' => $order->id,
                'description' => ($isReferralBonus ? 'Referral bonus' : 'Promotion reward') . ' points for order #' . $order->order_number,
                'meta' => $this->settlementMeta($order, $usage, $line, $event),
            ]);

            $redemption = RewardRedemption::create([
                'user_id' => $lockedUser->id,
                'promotion_id' => $line['promotion_id'] ?? $usage->promotion_id,
                'order_id' => $order->id,
                'reward_type' => $isReferralBonus ? 'referral_bonus' : 'reward_points',
                'status' => 'credited',
                'points' => $points,
                'settlement_key' => $key,
                'payload' => array_merge($this->settlementMeta($order, $usage, $line, $event), [
                    'reward_point_transaction_id' => $transaction->id,
                ]),
                'issued_at' => now(),
            ]);

            $this->notify($lockedUser, 'Reward points credited', $points . ' points have been added to your account.', [
                'event' => $event,
                'reward_type' => $redemption->reward_type,
                'order_id' => $order->id,
            ]);

            return [
                'redemption_id' => $redemption->id,
                'reward_point_transaction_id' => $transaction->id,
                'status' => 'credited',
                'points' => $points,
            ];
        });
    }

    private function settleGiftVoucher(
        User $user,
        Order $order,
        PromotionUsage $usage,
        array $line,
        int $index,
        float $amount,
        bool $isReferralBonus,
        string $event
    ): ?array {
        return DB::transaction(function () use ($user, $order, $usage, $line, $index, $amount, $isReferralBonus, $event) {
            $key = $this->settlementKey($order, $usage, $line, $index, 'gift_voucher');
            $existing = RewardRedemption::query()->where('settlement_key', $key)->first();
            if ($existing) {
                return ['redemption_id' => $existing->id, 'status' => $existing->status, 'already_settled' => true];
            }

            $giftCard = GiftCard::create([
                'code' => $this->uniqueGiftCode(),
                'title' => ($isReferralBonus ? 'Referral gift voucher' : 'Promotion gift voucher') . ' - Order #' . $order->order_number,
                'amount' => $amount,
                'max_redemptions' => 1,
                'expires_at' => now()->addDays(30),
                'is_active' => true,
                'created_by' => null,
            ]);

            $redemption = RewardRedemption::create([
                'user_id' => $user->id,
                'promotion_id' => $line['promotion_id'] ?? $usage->promotion_id,
                'order_id' => $order->id,
                'gift_card_id' => $giftCard->id,
                'reward_type' => $isReferralBonus ? 'referral_bonus' : 'gift_voucher',
                'status' => 'issued',
                'amount' => $amount,
                'code' => $giftCard->code,
                'settlement_key' => $key,
                'payload' => $this->settlementMeta($order, $usage, $line, $event),
                'issued_at' => now(),
                'expires_at' => $giftCard->expires_at,
            ]);

            $this->notify($user, 'Gift voucher ready', 'Your gift voucher code is ' . $giftCard->code . '.', [
                'event' => $event,
                'reward_type' => $redemption->reward_type,
                'order_id' => $order->id,
                'gift_card_code' => $giftCard->code,
            ]);

            return [
                'redemption_id' => $redemption->id,
                'gift_card_id' => $giftCard->id,
                'gift_card_code' => $giftCard->code,
                'status' => 'issued',
                'amount' => $amount,
            ];
        });
    }

    private function settlementKey(Order $order, PromotionUsage $usage, array $line, int $index, string $rewardKind): string
    {
        return implode(':', [
            'promotion_reward',
            $order->id,
            $line['promotion_id'] ?? $usage->promotion_id ?? 0,
            $line['type'] ?? 'reward',
            $index,
            $rewardKind,
        ]);
    }

    private function settlementMeta(Order $order, PromotionUsage $usage, array $line, string $event): array
    {
        return [
            'event' => $event,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'promotion_usage_id' => $usage->id,
            'promotion_id' => $line['promotion_id'] ?? $usage->promotion_id,
            'promotion_title' => $line['title'] ?? null,
            'reward_payload' => $line['reward_payload'] ?? [],
            'source_type' => $line['source_type'] ?? null,
            'source_id' => $line['source_id'] ?? null,
        ];
    }

    private function referralBonusType(array $line): string
    {
        return (string) data_get($line, 'reward_payload.referral_bonus_type', match (true) {
            ((int) ($line['reward_points'] ?? 0)) > 0 => 'reward_points',
            ((float) ($line['gift_voucher_amount'] ?? 0)) > 0 => 'gift_voucher',
            default => 'wallet_credit',
        });
    }

    private function uniqueGiftCode(): string
    {
        do {
            $code = 'GIFT' . strtoupper(Str::random(8));
        } while (
            GiftCard::where('code', $code)->exists()
            || PromotionCouponCode::where('code', $code)->exists()
        );

        return $code;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function notify(User $user, string $title, string $body, array $data = []): void
    {
        try {
            $user->notify(new AppDatabaseNotification($title, $body, array_merge($data, [
                'target_app' => 'customer',
            ])));
        } catch (\Throwable) {
            // Reward settlement should not fail because notification delivery failed.
        }
    }
}
