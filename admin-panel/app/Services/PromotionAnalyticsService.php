<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use App\Models\PromotionUsage;
use App\Models\CustomerScratchCard;
use App\Models\RewardRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromotionAnalyticsService
{
    public function summary(array $filters = []): array
    {
        $usage = PromotionUsage::query()
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id))
            ->when($filters['order_ids'] ?? null, fn ($query, $ids) => $query->whereIn('order_id', $ids));

        $ordersGenerated = (clone $usage)->whereNotNull('order_id')->distinct('order_id')->count('order_id');
        $discountGiven = round((float) (clone $usage)->sum('discount_amount'), 2);
        $cashbackGiven = round((float) (clone $usage)->sum('cashback_amount'), 2);
        $orderIds = (clone $usage)->whereNotNull('order_id')->pluck('order_id')->unique()->values();
        $revenue = $orderIds->isEmpty()
            ? 0.0
            : round((float) DB::table('orders')->whereIn('id', $orderIds)->sum('total'), 2);
        $promotionLogs = DB::table('promotion_logs')
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id));
        $calculationCount = (clone $promotionLogs)->where('event_type', 'calculate')->count();
        $scratchCards = CustomerScratchCard::query()
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id));
        $scratchIssued = (clone $scratchCards)->count();
        $scratchRevealed = (clone $scratchCards)->where('status', 'revealed')->count();
        $scratchRedemptions = RewardRedemption::query()
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id));
        $ledger = Schema::hasTable('promotion_settlement_ledgers')
            ? DB::table('promotion_settlement_ledgers')
                ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
                ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id))
                ->when($filters['order_ids'] ?? null, fn ($query, $ids) => $query->whereIn('order_id', $ids))
            : null;

        return [
            'promotion_count' => Promotion::query()->count(),
            'active_promotion_count' => Promotion::query()->active()->count(),
            'orders_generated' => $ordersGenerated,
            'revenue' => $revenue,
            'conversion' => $calculationCount > 0 ? round(($ordersGenerated / $calculationCount) * 100, 2) : 0.0,
            'roi' => $discountGiven > 0 ? round($revenue / $discountGiven, 2) : null,
            'discount_given' => $discountGiven,
            'cashback_given' => $cashbackGiven,
            'budget_used' => $ledger ? round((float) (clone $ledger)->sum('gross_liability_amount'), 2) : $discountGiven,
            'platform_burn' => $ledger ? round((float) (clone $ledger)->sum('platform_liability_amount'), 2) : 0.0,
            'restaurant_burn' => $ledger ? round((float) (clone $ledger)->sum('restaurant_liability_amount'), 2) : 0.0,
            'partner_burn' => $ledger ? round((float) (clone $ledger)->sum('partner_liability_amount'), 2) : 0.0,
            'scratch_cards' => [
                'issued' => $scratchIssued,
                'scratched' => $scratchRevealed,
                'expired' => (clone $scratchCards)->where('status', 'expired')->count(),
                'reveal_rate' => $scratchIssued > 0 ? round(($scratchRevealed / $scratchIssued) * 100, 2) : 0.0,
                'wallet_cashback' => round((float) (clone $scratchRedemptions)->whereIn('reward_type', ['wallet_cashback', 'wallet_credit', 'cashback'])->sum('amount'), 2),
                'coupons_generated' => (clone $scratchRedemptions)->whereNotNull('promotion_coupon_code_id')->count(),
                'points_issued' => (int) (clone $scratchRedemptions)->where('reward_type', 'reward_points')->sum('points'),
                'gift_vouchers' => (clone $scratchRedemptions)->whereNotNull('gift_card_id')->count(),
                'most_popular_reward' => (clone $scratchRedemptions)
                    ->select('reward_type', DB::raw('COUNT(*) as total'))
                    ->groupBy('reward_type')
                    ->orderByDesc('total')
                    ->first(),
            ],
            'top_promotions' => Promotion::query()
                ->withCount('usages')
                ->orderByDesc('usages_count')
                ->limit(10)
                ->get()
                ->map(fn (Promotion $promotion) => [
                    'id' => $promotion->id,
                    'title' => $promotion->title,
                    'usage_count' => $promotion->usages_count,
                    'status' => $promotion->status,
                ])
                ->values(),
            'top_coupons' => PromotionCouponCode::query()
                ->orderByDesc('used_count')
                ->limit(10)
                ->get(['id', 'promotion_id', 'code', 'used_count'])
                ->map(fn (PromotionCouponCode $coupon) => [
                    'id' => $coupon->id,
                    'promotion_id' => $coupon->promotion_id,
                    'code' => $coupon->code,
                    'used_count' => $coupon->used_count,
                ])
                ->values(),
            'top_restaurants' => DB::table('promotion_usage')
                ->join('restaurants', 'promotion_usage.restaurant_id', '=', 'restaurants.id')
                ->select('restaurants.id', 'restaurants.name', DB::raw('COUNT(*) as usage_count'), DB::raw('SUM(promotion_usage.discount_amount) as discount_given'))
                ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('promotion_usage.restaurant_id', $id))
                ->when($filters['order_ids'] ?? null, fn ($query, $ids) => $query->whereIn('promotion_usage.order_id', $ids))
                ->groupBy('restaurants.id', 'restaurants.name')
                ->orderByDesc('usage_count')
                ->limit(10)
                ->get(),
            'top_cities' => DB::table('promotion_usage')
                ->join('restaurants', 'promotion_usage.restaurant_id', '=', 'restaurants.id')
                ->select('restaurants.city', DB::raw('COUNT(*) as usage_count'), DB::raw('SUM(promotion_usage.discount_amount) as discount_given'))
                ->whereNotNull('restaurants.city')
                ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('promotion_usage.restaurant_id', $id))
                ->when($filters['order_ids'] ?? null, fn ($query, $ids) => $query->whereIn('promotion_usage.order_id', $ids))
                ->groupBy('restaurants.city')
                ->orderByDesc('usage_count')
                ->limit(10)
                ->get(),
            'hourly_usage' => $this->usageSeries($filters, '%H'),
            'daily_usage' => $this->usageSeries($filters, '%Y-%m-%d'),
            'monthly_usage' => $this->usageSeries($filters, '%Y-%m'),
        ];
    }

    private function usageSeries(array $filters, string $format)
    {
        $driver = DB::connection()->getDriverName();
        $expression = $driver === 'sqlite'
            ? "strftime(?, created_at)"
            : "DATE_FORMAT(created_at, ?)";

        return DB::table('promotion_usage')
            ->selectRaw("{$expression} as period, COUNT(*) as usage_count, SUM(discount_amount) as discount_given", [$format])
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_id', $id))
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('restaurant_id', $id))
            ->when($filters['order_ids'] ?? null, fn ($query, $ids) => $query->whereIn('order_id', $ids))
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }
}

