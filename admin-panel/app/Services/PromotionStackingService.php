<?php

namespace App\Services;

use Illuminate\Support\Collection;

class PromotionStackingService
{
    public function select(Collection $eligible): Collection
    {
        if ($eligible->isEmpty()) {
            return collect();
        }

        $exclusive = $eligible->first(fn (array $candidate) => (bool) $candidate['promotion']->is_exclusive);
        if ($exclusive) {
            return collect([$exclusive]);
        }

        if ($eligible->contains(fn (array $candidate) => (bool) data_get($candidate, 'promotion.stacking.best_offer_only', false))) {
            return $eligible
                ->sortByDesc(fn (array $candidate) => $this->candidateValue($candidate))
                ->take(1)
                ->values();
        }

        $explicitMultiple = $eligible->contains(function (array $candidate) {
            return (bool) data_get($candidate, 'promotion.stacking.allow_multiple', false);
        });

        if ($explicitMultiple) {
            $allowCouponPromotion = $eligible->contains(fn (array $candidate) => (bool) data_get($candidate, 'promotion.stacking.allow_coupon_promotion', true));
            $allowPromotionCashback = $eligible->contains(fn (array $candidate) => (bool) data_get($candidate, 'promotion.stacking.allow_promotion_cashback', true));
            $maxPromotions = $eligible
                ->map(fn (array $candidate) => data_get($candidate, 'promotion.stacking.max_promotions'))
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => max(1, (int) $value))
                ->min() ?: null;

            return $eligible
                ->when(! $allowCouponPromotion, function (Collection $collection) {
                    $hasCoupon = $collection->contains(fn (array $candidate) => $candidate['promotion']->isCouponBased());

                    return $hasCoupon
                        ? $collection->filter(fn (array $candidate) => $candidate['promotion']->isCouponBased())
                        : $collection;
                })
                ->when(! $allowPromotionCashback, function (Collection $collection) {
                    $hasDiscount = $collection->contains(fn (array $candidate) => (float) ($candidate['line']['discount_amount'] ?? 0) > 0);

                    return $hasDiscount
                        ? $collection->reject(fn (array $candidate) => ($candidate['line']['bucket'] ?? null) === 'cashback')
                        : $collection;
                })
                ->sortBy(fn (array $candidate) => [
                    (int) ($candidate['promotion']->priority ?? 100),
                    -1 * $this->candidateValue($candidate),
                ])
                ->when($maxPromotions, fn (Collection $collection) => $collection->take($maxPromotions))
                ->values();
        }

        return $eligible
            ->sortByDesc(fn (array $candidate) => $this->candidateValue($candidate))
            ->take(1)
            ->values();
    }

    private function candidateValue(array $candidate): float
    {
        $rewardLineValue = collect(
            (array) data_get($candidate, 'line.reward_payload.workflow.reward_lines', [])
        )->sum(function ($line) {
            if (! is_array($line) || (bool) ($line['included_in_cart'] ?? false)) {
                return 0;
            }

            $quantity = max(1, (int) ($line['quantity'] ?? $line['qty'] ?? 1));
            $unitPrice = max(0, (float) ($line['unit_price'] ?? $line['price'] ?? 0));

            return $quantity * $unitPrice;
        });

        return (float) ($candidate['line']['discount_amount'] ?? 0)
            + (float) ($candidate['line']['cashback_amount'] ?? 0)
            + (float) ($candidate['line']['gift_voucher_amount'] ?? 0)
            + (float) $rewardLineValue
            + ((int) ($candidate['line']['reward_points'] ?? 0) / 100);
    }
}
