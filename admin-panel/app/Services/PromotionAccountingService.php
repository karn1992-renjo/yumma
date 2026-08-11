<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionSettlementLedger;
use App\Models\PromotionUsage;
use Illuminate\Support\Facades\DB;

class PromotionAccountingService
{
    public function decorateLine(Promotion $promotion, array $line, array $context = []): array
    {
        $breakdown = $this->fundingBreakdown($promotion, $line);

        return array_merge($line, [
            'funding_breakdown' => $breakdown,
            'budget_remaining' => $this->remainingBudget($promotion),
            'offer_applicability' => [
                'can_apply_now' => true,
                'reason' => null,
                'reason_code' => null,
                'min_order_remaining' => $this->minOrderRemaining($promotion, $context),
            ],
        ]);
    }

    public function budgetCheck(Promotion $promotion, array $line, array $context = []): array
    {
        $amount = $this->grossLiability($line);
        if ($amount <= 0) {
            return ['eligible' => true, 'reason' => null, 'reason_code' => null];
        }

        $checks = [
            ['budget' => $this->totalBudget($promotion), 'used' => (float) ($promotion->budget_used ?? 0), 'code' => 'campaign_budget_exhausted', 'label' => 'campaign'],
            ['budget' => $this->dailyBudget($promotion), 'used' => $this->ledgerUsed($promotion, today(), null), 'code' => 'daily_budget_exhausted', 'label' => 'daily campaign'],
        ];

        $restaurantId = (int) ($context['restaurant_id'] ?? 0);
        if ($restaurantId > 0) {
            $checks[] = [
                'budget' => $this->perRestaurantBudget($promotion),
                'used' => $this->ledgerUsed($promotion, null, $restaurantId),
                'code' => 'restaurant_budget_exhausted',
                'label' => 'restaurant campaign',
            ];
        }

        foreach ($checks as $check) {
            if ($check['budget'] === null) {
                continue;
            }

            $remaining = round((float) $check['budget'] - (float) $check['used'], 2);
            if ($remaining < $amount) {
                return [
                    'eligible' => false,
                    'reason' => 'Promotion ' . $check['label'] . ' budget is exhausted',
                    'reason_code' => $check['code'],
                    'budget_remaining' => max(0, $remaining),
                ];
            }
        }

        return ['eligible' => true, 'reason' => null, 'reason_code' => null];
    }

    public function recordLedger(Order $order, PromotionUsage $usage, array $line): ?PromotionSettlementLedger
    {
        $promotion = $usage->promotion ?: Promotion::find($usage->promotion_id);
        if (! $promotion) {
            return null;
        }

        $breakdown = $line['funding_breakdown'] ?? $this->fundingBreakdown($promotion, $line);
        $gross = round((float) ($breakdown['gross_liability_amount'] ?? 0), 2);

        return DB::transaction(function () use ($order, $usage, $line, $promotion, $breakdown, $gross) {
            $ledger = PromotionSettlementLedger::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'promotion_id' => $promotion->id,
                    'coupon_code' => $usage->coupon_code,
                ],
                [
                    'promotion_usage_id' => $usage->id,
                    'restaurant_id' => $order->restaurant_id,
                    'user_id' => $order->customer_id,
                    'funding_type' => $breakdown['funding_type'] ?? $promotion->resolvedFundingType(),
                    'partner_name' => $breakdown['partner_name'] ?? null,
                    'discount_amount' => round((float) ($line['discount_amount'] ?? 0), 2),
                    'cashback_amount' => round((float) ($line['cashback_amount'] ?? 0), 2),
                    'gift_voucher_amount' => round((float) ($line['gift_voucher_amount'] ?? 0), 2),
                    'gross_liability_amount' => $gross,
                    'platform_liability_amount' => round((float) ($breakdown['platform_liability_amount'] ?? 0), 2),
                    'restaurant_liability_amount' => round((float) ($breakdown['restaurant_liability_amount'] ?? 0), 2),
                    'partner_liability_amount' => round((float) ($breakdown['partner_liability_amount'] ?? 0), 2),
                    'settlement_status' => 'pending',
                    'line_payload' => $line,
                    'funding_breakdown' => $breakdown,
                ]
            );

            if ($ledger->wasRecentlyCreated && $gross > 0) {
                Promotion::whereKey($promotion->id)->lockForUpdate()->increment('budget_used', $gross);
                $this->refreshOrderLiabilities($order);
            }

            return $ledger;
        });
    }

    public function refreshOrderLiabilities(Order $order): void
    {
        $totals = PromotionSettlementLedger::query()
            ->where('order_id', $order->id)
            ->selectRaw('COALESCE(SUM(platform_liability_amount), 0) as platform_total')
            ->selectRaw('COALESCE(SUM(restaurant_liability_amount), 0) as restaurant_total')
            ->selectRaw('COALESCE(SUM(partner_liability_amount), 0) as partner_total')
            ->first();

        $order->newQuery()->whereKey($order->id)->update([
            'promotion_platform_liability' => round((float) ($totals->platform_total ?? 0), 2),
            'promotion_restaurant_liability' => round((float) ($totals->restaurant_total ?? 0), 2),
            'promotion_partner_liability' => round((float) ($totals->partner_total ?? 0), 2),
        ]);
    }

    public function fundingBreakdown(Promotion $promotion, array $line): array
    {
        $gross = $this->grossLiability($line);
        $type = $promotion->resolvedFundingType();
        $platform = 0.0;
        $restaurant = 0.0;
        $partner = 0.0;

        if ($type === 'restaurant') {
            $restaurant = $gross;
        } elseif ($type === 'bank_partner') {
            $partner = $gross;
        } elseif ($type === 'shared') {
            $restaurantPercent = $promotion->restaurant_share_percent !== null
                ? (float) $promotion->restaurant_share_percent
                : 50.0;
            $platformPercent = $promotion->platform_share_percent !== null
                ? (float) $promotion->platform_share_percent
                : max(0, 100 - $restaurantPercent);
            $totalPercent = max(0.01, $platformPercent + $restaurantPercent);
            $platform = round($gross * ($platformPercent / $totalPercent), 2);
            $restaurant = round($gross * ($restaurantPercent / $totalPercent), 2);
        } else {
            $platform = $gross;
        }

        $assigned = round($platform + $restaurant + $partner, 2);
        if ($gross > 0 && $assigned !== $gross) {
            $platform = round($platform + ($gross - $assigned), 2);
        }

        return [
            'funding_type' => $type,
            'partner_name' => $promotion->partner_name,
            'gross_liability_amount' => $gross,
            'platform_liability_amount' => max(0, round($platform, 2)),
            'restaurant_liability_amount' => max(0, round($restaurant, 2)),
            'partner_liability_amount' => max(0, round($partner, 2)),
            'platform_share_percent' => $promotion->platform_share_percent,
            'restaurant_share_percent' => $promotion->restaurant_share_percent,
        ];
    }

    public function remainingBudget(Promotion $promotion): ?float
    {
        return $promotion->budgetRemaining();
    }

    public function grossLiability(array $line): float
    {
        return max(0, round(
            (float) ($line['discount_amount'] ?? 0)
            + (float) ($line['cashback_amount'] ?? 0)
            + (float) ($line['gift_voucher_amount'] ?? 0),
            2
        ));
    }

    private function totalBudget(Promotion $promotion): ?float
    {
        $value = $promotion->total_budget ?? data_get($promotion->visibility ?? [], 'campaign_budget');

        return $value !== null && $value !== '' ? (float) $value : null;
    }

    private function dailyBudget(Promotion $promotion): ?float
    {
        return $promotion->daily_budget !== null ? (float) $promotion->daily_budget : null;
    }

    private function perRestaurantBudget(Promotion $promotion): ?float
    {
        return $promotion->per_restaurant_budget !== null ? (float) $promotion->per_restaurant_budget : null;
    }

    private function ledgerUsed(Promotion $promotion, $date = null, ?int $restaurantId = null): float
    {
        return round((float) PromotionSettlementLedger::query()
            ->where('promotion_id', $promotion->id)
            ->when($date, fn ($query) => $query->whereDate('created_at', $date))
            ->when($restaurantId, fn ($query) => $query->where('restaurant_id', $restaurantId))
            ->sum('gross_liability_amount'), 2);
    }

    private function minOrderRemaining(Promotion $promotion, array $context): float
    {
        $min = data_get($promotion->conditions ?? [], 'min_order_amount');
        if ($min === null) {
            return 0.0;
        }

        return max(0, round((float) $min - (float) ($context['subtotal'] ?? 0), 2));
    }
}
