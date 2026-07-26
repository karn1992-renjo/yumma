<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DeliveryChargeSetting extends Model
{
    protected $fillable = [
        'charge_type', 'base_charge', 'per_km_charge',
        'free_delivery_threshold', 'free_delivery_global',
        'free_delivery_days', 'free_delivery_area_ids',
        'platform_fee',
        'order_acceptance_timeout_seconds',
        'admin_contribution_percent', 'restaurant_contribution_percent'
    ];
    
    protected $casts = [
        'free_delivery_global' => 'boolean',
        'free_delivery_days' => 'array',
        'free_delivery_area_ids' => 'array',
        'order_acceptance_timeout_seconds' => 'integer',
    ];
    
    public static function getDeliveryCharge($distance = null)
    {
        $setting = self::safeFirst();
        
        if (!$setting) {
            return 40;
        }
        
        if ($setting->charge_type === 'per_km' && $distance) {
            $distance = (float) $distance;
            // Base charge covers the first 1 km; charge per_km only for distance beyond 1 km
            if ($distance <= 1.0) {
                return round((float) $setting->base_charge, 2);
            }

            $additionalKm = max(0, $distance - 1.0);
            return round((float) $setting->base_charge + ($additionalKm * (float) $setting->per_km_charge), 2);
        }
        
        return $setting->base_charge;
    }

    public static function getPlatformFee(): float
    {
        $setting = self::safeFirst();

        return round((float) ($setting?->platform_fee ?? 0), 2);
    }

    public static function getOrderAcceptanceTimeoutSeconds(): int
    {
        $setting = self::safeFirst();
        $seconds = (int) ($setting?->order_acceptance_timeout_seconds ?? 180);

        return max(30, min(600, $seconds));
    }
    
    public static function getFreeDeliveryThreshold($restaurantId = null, $deliveryLat = null, $deliveryLng = null)
    {
        $zoneThreshold = self::getZoneFreeDeliveryThreshold($deliveryLat, $deliveryLng);
        if ($zoneThreshold !== null) {
            return $zoneThreshold;
        }

        return null;
    }

    private static function getZoneFreeDeliveryThreshold($deliveryLat = null, $deliveryLng = null): ?float
    {
        if ($deliveryLat === null || $deliveryLng === null || $deliveryLat === '' || $deliveryLng === '') {
            return null;
        }

        try {
            if (! Schema::hasColumn('delivery_areas', 'free_delivery_enabled')
                || ! Schema::hasColumn('delivery_areas', 'free_delivery_threshold')) {
                return null;
            }

            $threshold = DeliveryArea::query()
                ->active()
                ->where('free_delivery_enabled', true)
                ->whereNotNull('free_delivery_threshold')
                ->get()
                ->filter(fn (DeliveryArea $area) => $area->containsPoint((float) $deliveryLat, (float) $deliveryLng))
                ->pluck('free_delivery_threshold')
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => (float) $value)
                ->min();

            if ($threshold === null) {
                return null;
            }

            return (float) $threshold;
        } catch (\Throwable $e) {
            Log::warning('Zone free delivery lookup failed.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function isFreeDeliveryEligible($deliveryLat = null, $deliveryLng = null): bool
    {
        $days = collect($this->free_delivery_days ?? [])->filter()->values();
        if ($days->isNotEmpty() && ! $days->contains(Carbon::now()->format('l'))) {
            return false;
        }

        $areaIds = collect($this->free_delivery_area_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        if ($areaIds->isEmpty()) {
            return true;
        }

        if ($deliveryLat === null || $deliveryLng === null || $deliveryLat === '' || $deliveryLng === '') {
            return false;
        }

        return DeliveryArea::query()
            ->active()
            ->whereIn('id', $areaIds)
            ->get()
            ->contains(fn (DeliveryArea $area) => $area->containsPoint((float) $deliveryLat, (float) $deliveryLng));
    }

    private static function safeFirst(): ?self
    {
        try {
            return self::query()->oldest('id')->first();
        } catch (\Throwable $e) {
            Log::warning('Delivery charge settings unavailable; using defaults.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
