<?php

namespace App\Services\Ai;

use App\Models\CommissionSetting;
use App\Models\Restaurant;

/**
 * Blocks the AI from creating a promotion/coupon that would give away more
 * than the real commission margin it's funded from. There is no fuel/
 * delivery-cost model in this codebase, so "margin" here means the real
 * commission percentage the platform or restaurant would otherwise earn on
 * an order (App\Services\PayoutCalculationService::restaurantCommissionAmount()
 * -- mirrored here rather than called directly since that method is
 * protected and tied to a real, persisted Order; this guard has to evaluate
 * a not-yet-real proposal against an estimated order value).
 */
class AiMarginGuardService
{
    public function __construct(private readonly AiSettingsService $settings)
    {
    }

    /**
     * @param  array  $rewards  ['type' => 'percentage'|'free_delivery'|'item_discount', 'value' => float, 'max_discount' => ?float]
     */
    public function checkPromotion(?int $restaurantId, string $fundingType, array $rewards, float $estimatedOrderValue): array
    {
        $estimatedOrderValue = max(0, $estimatedOrderValue);
        $availableMargin = $fundingType === 'restaurant'
            ? $this->restaurantMargin($restaurantId, $estimatedOrderValue)
            : $this->platformMargin($estimatedOrderValue);

        $projectedDiscount = $this->projectedDiscount($rewards, $estimatedOrderValue);

        $minMarginPercent = (float) $this->settings->get('ai_min_promotion_margin_percent', 15);
        $safeCeiling = $availableMargin * (1 - $minMarginPercent / 100);

        if ($projectedDiscount > $safeCeiling) {
            return [
                'allowed' => false,
                'reason' => sprintf(
                    'Projected discount (%.2f) would exceed the safe margin ceiling (%.2f, %.0f%% buffer kept of a %.2f commission margin on an estimated %.2f order).',
                    $projectedDiscount,
                    max(0, $safeCeiling),
                    $minMarginPercent,
                    $availableMargin,
                    $estimatedOrderValue
                ),
                'projected_discount' => $projectedDiscount,
                'available_margin' => $availableMargin,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'projected_discount' => $projectedDiscount,
            'available_margin' => $availableMargin,
        ];
    }

    private function projectedDiscount(array $rewards, float $estimatedOrderValue): float
    {
        $type = (string) ($rewards['type'] ?? '');
        $maxDiscount = isset($rewards['max_discount']) ? (float) $rewards['max_discount'] : null;

        $discount = match ($type) {
            'percentage', 'item_discount' => $estimatedOrderValue * ((float) ($rewards['value'] ?? 0) / 100),
            'free_delivery' => (float) \App\Models\DeliveryChargeSetting::getDeliveryCharge(null),
            default => 0.0,
        };

        if ($maxDiscount !== null) {
            $discount = min($discount, $maxDiscount);
        }

        return round(max(0, $discount), 2);
    }

    private function restaurantMargin(?int $restaurantId, float $estimatedOrderValue): float
    {
        $restaurant = $restaurantId ? Restaurant::find($restaurantId) : null;
        $rate = $restaurant?->commission_rate;
        $type = $restaurant?->commission_calculation_type;

        if ($type === 'global' || $rate === null || $rate === '') {
            return CommissionSetting::calculate('restaurant', $estimatedOrderValue, (float) CommissionSetting::getRate('restaurant') ?: 15.0);
        }

        $amount = $type === CommissionSetting::TYPE_FIXED
            ? (float) $rate
            : $estimatedOrderValue * ((float) $rate / 100);

        return round(min($estimatedOrderValue, max(0, $amount)), 2);
    }

    private function platformMargin(float $estimatedOrderValue): float
    {
        return CommissionSetting::calculate('restaurant', $estimatedOrderValue, 15.0);
    }
}
