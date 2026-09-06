<?php

namespace App\Services;

use App\Models\AppSetting;

/**
 * Fee charged to the restaurant when a delivery order is placed beyond the
 * admin-configured free radius. Not charged to the customer -- it is deducted
 * from the restaurant's earning in PayoutCalculationService.
 *
 * Config (admin Settings -> Delivery Radius):
 *   long_distance_charge_enabled  0|1
 *   long_distance_free_km         free radius in km (default 5)
 *   long_distance_charge_mode     "per_km" | "fixed"
 *   long_distance_charge_rate     rupees per excess km, or the flat amount
 */
class LongDistanceChargeService
{
    public function enabled(): bool
    {
        return filter_var(AppSetting::getValue('long_distance_charge_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function freeKm(): float
    {
        return max(0, (float) AppSetting::getValue('long_distance_free_km', 5));
    }

    public function mode(): string
    {
        return AppSetting::getValue('long_distance_charge_mode', 'per_km') === 'fixed' ? 'fixed' : 'per_km';
    }

    public function rate(): float
    {
        return max(0, (float) AppSetting::getValue('long_distance_charge_rate', 0));
    }

    /**
     * The charge for a delivery of [$distanceKm] kilometres. Zero unless the
     * feature is on, a positive rate is set, and the distance exceeds the free
     * radius.
     */
    public function chargeFor(?float $distanceKm): float
    {
        if (! $this->enabled() || $distanceKm === null) {
            return 0.0;
        }

        $rate = $this->rate();
        $freeKm = $this->freeKm();

        if ($rate <= 0 || $distanceKm <= $freeKm) {
            return 0.0;
        }

        if ($this->mode() === 'fixed') {
            return round($rate, 2);
        }

        return round(($distanceKm - $freeKm) * $rate, 2);
    }
}
