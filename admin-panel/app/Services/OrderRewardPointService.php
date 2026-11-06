<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RewardPointTransaction;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderRewardPointService
{
    public const REFERENCE_EARNING = 'order_earning';
    public const REFERENCE_REVERSAL = 'order_earning_reversal';

    /** Who earns points on orders: every customer ('all') or only active Gold members ('gold'). */
    public function earningAudience(): string
    {
        return AppSetting::getValue('reward_points_earning_audience', 'all') === 'gold' ? 'gold' : 'all';
    }

    public function isEligibleToEarn(?int $userId): bool
    {
        if ($this->earningAudience() !== 'gold') {
            return true;
        }

        return $userId ? app(GoldSubscriptionService::class)->isActiveFor($userId) : false;
    }

    /** Points an order of this total earns for this customer (0 when they are not eligible). */
    public function pointsFor(float $total, ?int $userId): int
    {
        return $this->isEligibleToEarn($userId) ? (int) floor(max(0, $total)) : 0;
    }

    public function creditForDeliveredOrder(Order $order): ?RewardPointTransaction
    {
        if ($order->status !== 'delivered' || ! $order->customer_id) {
            return null;
        }

        $points = $this->pointsFor((float) $order->total, (int) $order->customer_id);
        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $points) {
            $existing = RewardPointTransaction::query()
                ->where('reference_type', self::REFERENCE_EARNING)
                ->where('reference_id', $order->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $user = User::query()->whereKey($order->customer_id)->lockForUpdate()->first();
            if (! $user) {
                return null;
            }

            $user->increment('reward_points_balance', $points);
            $user->refresh();

            $transaction = RewardPointTransaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'credit',
                'points' => $points,
                'balance_after' => (int) $user->reward_points_balance,
                'reference_type' => self::REFERENCE_EARNING,
                'reference_id' => $order->id,
                'description' => 'Reward points earned for order #' . ($order->order_number ?? $order->id),
                'meta' => [
                    'order_total' => (float) $order->total,
                    'earning_rule' => 'floor_final_paid_total',
                ],
            ]);

            $this->syncEarnedPoints($order, $points);

            return $transaction;
        });
    }

    public function reverseForRefundedOrder(Order $order): ?RewardPointTransaction
    {
        if (! $order->customer_id) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $earning = RewardPointTransaction::query()
                ->where('reference_type', self::REFERENCE_EARNING)
                ->where('reference_id', $order->id)
                ->first();
            if (! $earning) {
                return null;
            }

            $existing = RewardPointTransaction::query()
                ->where('reference_type', self::REFERENCE_REVERSAL)
                ->where('reference_id', $order->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $user = User::query()->whereKey($order->customer_id)->lockForUpdate()->first();
            if (! $user) {
                return null;
            }

            $originalPoints = (int) $earning->points;
            if ($originalPoints <= 0) {
                return null;
            }

            $pointsToDebit = min($originalPoints, (int) ($user->reward_points_balance ?? 0));
            if ($pointsToDebit > 0) {
                $user->decrement('reward_points_balance', $pointsToDebit);
                $user->refresh();
            }

            $transaction = RewardPointTransaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'debit',
                'points' => $pointsToDebit,
                'balance_after' => (int) $user->reward_points_balance,
                'reference_type' => self::REFERENCE_REVERSAL,
                'reference_id' => $order->id,
                'description' => 'Reward points reversed for refunded order #' . ($order->order_number ?? $order->id),
                'meta' => [
                    'original_reward_point_transaction_id' => $earning->id,
                    'original_points' => $originalPoints,
                    'source' => 'refund',
                    'reversed_points' => $pointsToDebit,
                ],
            ]);

            $this->syncEarnedPoints($order, 0);

            return $transaction;
        });
    }

    public function earnedPointsForOrder(Order $order): int
    {
        $points = RewardPointTransaction::query()
            ->where('reference_type', self::REFERENCE_EARNING)
            ->where('reference_id', $order->id)
            ->value('points');

        if ($points !== null) {
            return (int) $points;
        }

        return Schema::hasColumn($order->getTable(), 'reward_points_earned')
            ? (int) ($order->getAttribute('reward_points_earned') ?? 0)
            : 0;
    }

    private function syncEarnedPoints(Order $order, int $points): void
    {
        if (! Schema::hasColumn($order->getTable(), 'reward_points_earned')) {
            return;
        }

        $order->newQuery()->whereKey($order->id)->update([
            'reward_points_earned' => $points,
        ]);
    }
}
