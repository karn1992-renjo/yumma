<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RewardPointTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderRewardPointService
{
    public const REFERENCE_EARNING = 'order_earning';
    public const REFERENCE_REVERSAL = 'order_earning_reversal';

    public function creditForDeliveredOrder(Order $order): ?RewardPointTransaction
    {
        if ($order->status !== 'delivered' || ! $order->customer_id) {
            return null;
        }

        $points = (int) floor(max(0, (float) $order->total));
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

            $order->newQuery()->whereKey($order->id)->update([
                'reward_points_earned' => $points,
            ]);

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

            $order->newQuery()->whereKey($order->id)->update([
                'reward_points_earned' => 0,
            ]);

            return $transaction;
        });
    }

    public function earnedPointsForOrder(Order $order): int
    {
        $points = RewardPointTransaction::query()
            ->where('reference_type', self::REFERENCE_EARNING)
            ->where('reference_id', $order->id)
            ->value('points');

        return (int) ($points ?? $order->reward_points_earned ?? 0);
    }
}
