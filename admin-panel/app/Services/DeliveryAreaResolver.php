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

        return $containingArea ?: null;
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


}
