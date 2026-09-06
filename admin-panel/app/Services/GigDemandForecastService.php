<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\DriverGig;
use App\Models\GigDemandForecast;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Services\GigExternalSignalService;

class GigDemandForecastService
{
    public function forecastDay(?Carbon $date = null): array
    {
        $date ??= today()->addDay();
        $areas = DeliveryArea::where('is_active', true)->get();
        $hours = range(6, 23);
        $results = [];

        foreach ($areas as $area) {
            foreach ($hours as $hour) {
                $results[] = $this->forecastAreaHour($area->id, $date, $hour);
            }
        }

        $results[] = $this->forecastAreaHour(null, $date, 12);
        $this->applyForecastsToSlots($date);

        return $results;
    }

    public function forecastAreaHour(?int $areaId, Carbon $date, int $hour): array
    {
        $lookbackDays = max(7, (int) \App\Models\AppSetting::getValue('gig_forecast_lookback_days', 28));
        $windowStart = $date->copy()->subDays($lookbackDays)->startOfDay();
        $windowEnd = $date->copy()->subDay()->endOfDay();

        $orders = Order::query()
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$windowStart, $windowEnd])
            ->get(['id', 'delivery_lat', 'delivery_lng', 'delivered_at'])
            ->filter(fn (Order $order) => $order->delivered_at && (int) $order->delivered_at->format('G') === $hour);
        if ($areaId) {
            $area = DeliveryArea::find($areaId);
            $orders = $area
                ? $orders->filter(fn (Order $order) => $order->delivery_lat !== null && $order->delivery_lng !== null && $area->containsPoint((float) $order->delivery_lat, (float) $order->delivery_lng))
                : collect();
        }

        $historicalOrders = $orders->count();
        $dailyAverage = $historicalOrders / max(1, $lookbackDays / 7);
        $dayBoost = $date->isWeekend() ? 1.15 : 1.0;
        $peakBoost = in_array($hour, [12, 13, 19, 20, 21], true) ? 1.25 : 1.0;
        $externalSignals = app(GigExternalSignalService::class)->signalsFor($areaId, $date, $hour);
        $externalScore = (float) ($externalSignals['score'] ?? 0);
        $forecastedOrders = (int) ceil(($dailyAverage * $dayBoost * $peakBoost) + $externalScore);
        $ordersPerDriver = max(1, (int) \App\Models\AppSetting::getValue('gig_target_orders_per_driver_per_hour', 3));
        $recommendedCapacity = max(1, (int) ceil($forecastedOrders / $ordersPerDriver));
        $demandScore = round($forecastedOrders * $peakBoost, 2);
        $surgeMultiplier = $this->surgeForDemand($forecastedOrders, $recommendedCapacity);

        $forecast = GigDemandForecast::updateOrCreate(
            [
                'area_id' => $areaId,
                'date' => $date->toDateString(),
                'hour' => $hour,
            ],
            [
                'historical_orders' => $historicalOrders,
                'forecasted_orders' => $forecastedOrders,
                'recommended_capacity' => $recommendedCapacity,
                'demand_score' => $demandScore,
                'surge_multiplier' => $surgeMultiplier,
                'signals' => [
                    'lookback_days' => $lookbackDays,
                    'day_boost' => $dayBoost,
                    'peak_boost' => $peakBoost,
                    'orders_per_driver' => $ordersPerDriver,
                    'external_score' => $externalScore,
                    'external_signals' => $externalSignals['signals'] ?? [],
                ],
            ]
        );

        return $forecast->toArray();
    }

    public function applyForecastsToSlots(?Carbon $date = null): int
    {
        if (! Schema::hasColumn('driver_gigs', 'forecasted_orders')) {
            return 0;
        }

        $date ??= today()->addDay();
        $updated = 0;

        DriverGig::whereDate('date', $date->toDateString())
            ->get()
            ->each(function (DriverGig $gig) use (&$updated) {
                $hour = (int) $this->slotHour($gig);
                $forecast = GigDemandForecast::where('area_id', $gig->area_id)
                    ->whereDate('date', $gig->date)
                    ->where('hour', $hour)
                    ->first();

                if (! $forecast) {
                    return;
                }

                $updates = [
                    'forecasted_orders' => $forecast->forecasted_orders,
                    'recommended_capacity' => $forecast->recommended_capacity,
                    'demand_score' => $forecast->demand_score,
                    'surge_multiplier' => $gig->auto_pricing_enabled ? $forecast->surge_multiplier : ($gig->surge_multiplier ?: 1),
                    'forecast_meta' => $forecast->signals,
                ];

                foreach (array_keys($updates) as $column) {
                    if (! Schema::hasColumn('driver_gigs', $column)) {
                        unset($updates[$column]);
                    }
                }

                if ($updates) {
                    $gig->forceFill($updates)->save();
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Upcoming area/hour slots where the demand forecast recommends more
     * driver capacity than the gig slots currently published for that hour
     * provide. Shared by App\Services\Ai\Managers\AiGigProvisioningManager
     * (which fills the gap) and App\Services\Ai\AiOrchestrator (which surfaces
     * it to the management cycle).
     *
     * @return array<int, array{area_id:int, area_name:?string, date:string, hour:int,
     *     forecasted_orders:int, recommended_capacity:int, existing_capacity:int,
     *     gap:int, surge_multiplier:float, demand_score:float}>
     */
    public function upcomingShortages(int $horizonHours = 24, int $minForecastOrders = 4): array
    {
        $now = now();
        $horizonEnd = $now->copy()->addHours(max(1, $horizonHours));

        $forecasts = GigDemandForecast::query()
            ->whereNotNull('area_id')
            ->whereDate('date', '>=', $now->toDateString())
            ->whereDate('date', '<=', $horizonEnd->toDateString())
            ->where('forecasted_orders', '>=', $minForecastOrders)
            ->where('recommended_capacity', '>=', 1)
            ->get();

        if ($forecasts->isEmpty()) {
            return [];
        }

        $areaNames = DeliveryArea::whereIn('id', $forecasts->pluck('area_id')->unique())
            ->pluck('name', 'id');

        $shortages = [];

        foreach ($forecasts as $forecast) {
            $slotStart = Carbon::parse($forecast->date->toDateString())->setTime((int) $forecast->hour, 0);
            if ($slotStart->lt($now) || $slotStart->gt($horizonEnd)) {
                continue;
            }

            $existingCapacity = $this->publishedCapacityForHour((int) $forecast->area_id, $slotStart);
            $gap = (int) $forecast->recommended_capacity - $existingCapacity;
            if ($gap < 1) {
                continue;
            }

            $shortages[] = [
                'area_id' => (int) $forecast->area_id,
                'area_name' => $areaNames[$forecast->area_id] ?? null,
                'date' => $forecast->date->toDateString(),
                'hour' => (int) $forecast->hour,
                'forecasted_orders' => (int) $forecast->forecasted_orders,
                'recommended_capacity' => (int) $forecast->recommended_capacity,
                'existing_capacity' => $existingCapacity,
                'gap' => $gap,
                'surge_multiplier' => (float) ($forecast->surge_multiplier ?? 1),
                'demand_score' => (float) ($forecast->demand_score ?? 0),
            ];
        }

        usort($shortages, fn ($a, $b) => [$b['gap'], $b['forecasted_orders']] <=> [$a['gap'], $a['forecasted_orders']]);

        return $shortages;
    }

    /**
     * Total published seat capacity (available + booked gig slots) that
     * already covers a given area at a given hour.
     */
    public function publishedCapacityForHour(int $areaId, Carbon $slotStart): int
    {
        $hour = (int) $slotStart->format('G');

        return (int) DriverGig::where('area_id', $areaId)
            ->whereDate('date', $slotStart->toDateString())
            ->whereIn('status', ['available', 'booked'])
            ->get(['start_time', 'end_time', 'capacity'])
            ->filter(function (DriverGig $gig) use ($hour) {
                if (! $gig->start_time || ! $gig->end_time) {
                    return false;
                }
                $startHour = (int) $gig->start_time->format('G');
                $endHour = (int) $gig->end_time->format('G');
                if ($endHour <= $startHour) {
                    $endHour = 24;
                }

                return $hour >= $startHour && $hour < $endHour;
            })
            ->sum('capacity');
    }

    private function surgeForDemand(int $forecastedOrders, int $recommendedCapacity): float
    {
        $pressure = $forecastedOrders / max(1, $recommendedCapacity * 3);
        if ($pressure >= 1.5) {
            return 1.5;
        }
        if ($pressure >= 1.2) {
            return 1.3;
        }
        if ($pressure >= 1.0) {
            return 1.15;
        }

        return 1.0;
    }

    private function slotHour(DriverGig $gig): int
    {
        return $gig->start_time ? (int) $gig->start_time->format('G') : 0;
    }
}