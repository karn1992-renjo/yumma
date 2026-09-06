<?php

namespace App\Services;

use App\Models\DeliveryArea;

/**
 * A flat, self-funding surge fee: activating a zone surge charges customers
 * ordering into that zone a fixed extra delivery fee (see
 * App\Http\Controllers\Api\OrderController::buildPricingSummary()), and the
 * exact same amount is credited to drivers per delivered order as a
 * "zone_surge_bonus" (see App\Services\GigIncentiveService::
 * calculateGigEarnings(), which sums Order::surge_fee for orders the driver
 * actually delivered). No platform subsidy: what a customer is charged in
 * a surging zone is exactly what funds the matching driver bonus.
 */
class ZoneSurgeService
{
    public function activate(DeliveryArea $area, float $amount, ?string $reason = null, ?int $aiDecisionId = null): DeliveryArea
    {
        $area->forceFill([
            'surge_fee_active' => true,
            'surge_fee_amount' => round(max(0, $amount), 2),
            'surge_fee_reason' => $reason,
            'surge_fee_activated_at' => now(),
            'surge_fee_ai_decision_id' => $aiDecisionId,
        ])->save();

        return $area->fresh();
    }

    public function deactivate(DeliveryArea $area): DeliveryArea
    {
        $area->forceFill([
            'surge_fee_active' => false,
            'surge_fee_amount' => null,
            'surge_fee_reason' => null,
        ])->save();

        return $area->fresh();
    }
}
