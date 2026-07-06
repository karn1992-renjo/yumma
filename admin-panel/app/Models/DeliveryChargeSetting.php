<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DeliveryChargeSetting extends Model
{
    protected $fillable = [
        'charge_type', 'base_charge', 'per_km_charge',
        'free_delivery_threshold', 'free_delivery_global',
        'free_delivery_days', 'free_delivery_area_ids',
        'platform_fee',
        'admin_contribution_percent', 'restaurant_contribution_percent'
    ];
    
    protected $casts = [
        'free_delivery_global' => 'boolean',
        'free_delivery_days' => 'array',
        'free_delivery_area_ids' => 'array',
    ];
    
    public static function getDeliveryCharge($distance = null)
    {
        $setting = self::safeFirst();
        
        if (!$setting) {
            return 40;
        }
        
        if ($setting->charge_type === 'per_km' && $distance) {
            return $setting->base_charge + ($distance * $setting->per_km_charge);
        }
        
        return $setting->base_charge;
    }

    public static function getPlatformFee(): float
    {
        $setting = self::safeFirst();

        return round((float) ($setting?->platform_fee ?? 0), 2);
    }
    
    public static function getFreeDeliveryThreshold($restaurantId = null, $deliveryLat = null, $deliveryLng = null)
    {
        $setting = self::safeFirst();
        
        if ($setting && $setting->free_delivery_global && $setting->isFreeDeliveryEligible($deliveryLat, $deliveryLng)) {
            return $setting->free_delivery_threshold;
        }
        
        return null;
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
