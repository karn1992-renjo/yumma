<?php

namespace App\Services;

use App\Models\DeliveryArea;
use Illuminate\Support\Collection;

class DeliveryAreaResolver
{
    public function resolve(?float $latitude, ?float $longitude): ?DeliveryArea
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        /** @var Collection<int, DeliveryArea> $areas */
        $areas = DeliveryArea::query()
            ->active()
            ->get();

        if ($areas->isEmpty()) {
            return null;
        }

        $containingArea = $areas
            ->filter(fn (DeliveryArea $area) => $area->containsPoint($latitude, $longitude))
            ->sortBy(fn (DeliveryArea $area) => $this->areaFootprint($area))
            ->first();

        if ($containingArea) {
            return $containingArea;
        }

        return $areas
            ->filter(fn (DeliveryArea $area) => $area->latitude !== null && $area->longitude !== null)
            ->sortBy(fn (DeliveryArea $area) => $this->distanceKm(
                $latitude,
                $longitude,
                (float) $area->latitude,
                (float) $area->longitude
            ))
            ->first();
    }

    private function areaFootprint(DeliveryArea $area): float
    {
        if ($area->area_type === 'polygon') {
            $polygonArea = (float) $area->getPolygonArea();
            return $polygonArea > 0 ? $polygonArea : PHP_FLOAT_MAX;
        }

        $radius = (float) ($area->radius_km ?? 0);
        return $radius > 0 ? $radius : PHP_FLOAT_MAX;
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
