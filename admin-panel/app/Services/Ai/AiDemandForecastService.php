<?php

namespace App\Services\Ai;

use App\Models\AiForecast;
use App\Models\DeliveryArea;
use App\Services\GigDemandForecastService;
use Carbon\Carbon;

class AiDemandForecastService
{
    public function __construct(private readonly GigDemandForecastService $forecastService)
    {
    }

    public function forecast(?int $areaId = null, ?Carbon $date = null, ?int $hour = null): array
    {
        $date ??= today()->addDay();
        $hour ??= (int) now()->addHour()->format('G');
        $forecast = $this->forecastService->forecastAreaHour($areaId, $date, $hour);
        $expectedOrders = (int) ($forecast['forecasted_orders'] ?? 0);
        $requiredDrivers = (int) ($forecast['recommended_capacity'] ?? 0);
        $availableDrivers = $areaId ? $this->availableDrivers($areaId) : 0;
        $windowStart = $date->copy()->setTime($hour, 0);

        $aiForecast = AiForecast::updateOrCreate(
            [
                'area_id' => $areaId,
                'forecast_type' => 'demand',
                'window_start' => $windowStart,
            ],
            [
                'window_end' => $windowStart->copy()->addHour(),
                'window_minutes' => 60,
                'expected_orders' => $expectedOrders,
                'lower_bound' => max(0, $expectedOrders - 2),
                'upper_bound' => $expectedOrders + 3,
                'confidence' => min(0.95, max(0.45, ((float) ($forecast['demand_score'] ?? 1)) / max(1, $expectedOrders + 5))),
                'required_drivers' => $requiredDrivers,
                'available_drivers' => $availableDrivers,
                'shortage' => max(0, $requiredDrivers - $availableDrivers),
                'signals' => $forecast['signals'] ?? [],
            ]
        );

        return $aiForecast->toArray() + ['source_forecast' => $forecast];
    }

    private function availableDrivers(int $areaId): int
    {
        return app(AiBusinessSnapshotService::class)
            ->zoneStatus($areaId)[0]['available_drivers'] ?? 0;
    }
}
