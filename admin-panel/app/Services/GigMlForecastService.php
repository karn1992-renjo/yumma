<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\GigExternalSignal;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class GigMlForecastService
{
    public function trainAndServe(?Carbon $date = null): int
    {
        if (! Schema::hasTable('gig_external_signals')) {
            return 0;
        }

        $date ??= today()->addDay();
        $written = 0;

        foreach (DeliveryArea::where('is_active', true)->get() as $area) {
            foreach (range(6, 23) as $hour) {
                $prediction = $this->predict($area, $date, $hour);
                GigExternalSignal::updateOrCreate(
                    [
                        'area_id' => $area->id,
                        'date' => $date->toDateString(),
                        'hour' => $hour,
                        'source' => 'ml_prediction',
                    ],
                    [
                        'score' => $prediction['score'],
                        'payload' => $prediction,
                        'fetched_at' => now(),
                    ]
                );
                $written++;
            }
        }

        return $written;
    }

    public function predict(DeliveryArea $area, Carbon $date, int $hour): array
    {
        $samples = $this->samples($area, $date, $hour);
        $baseline = $samples ? array_sum(array_column($samples, 'orders')) / count($samples) : 0;
        $sameWeekday = array_values(array_filter($samples, fn ($sample) => $sample['weekday'] === (int) $date->dayOfWeek));
        $weekdayMean = $sameWeekday ? array_sum(array_column($sameWeekday, 'orders')) / count($sameWeekday) : $baseline;
        $recent = array_slice($samples, -7);
        $recentMean = $recent ? array_sum(array_column($recent, 'orders')) / count($recent) : $baseline;
        $externalScore = $this->externalScore($area->id, $date, $hour);

        $predictedOrders = max(0, (0.45 * $baseline) + (0.35 * $weekdayMean) + (0.20 * $recentMean) + $externalScore);
        $score = round($predictedOrders / 3, 2);

        return [
            'provider' => 'local_ml_ensemble',
            'model' => 'weighted_weekday_recent_external_v1',
            'area_id' => $area->id,
            'area' => $area->name,
            'date' => $date->toDateString(),
            'hour' => $hour,
            'predicted_orders' => round($predictedOrders, 2),
            'score' => $score,
            'training_samples' => count($samples),
            'features' => [
                'baseline_orders' => round($baseline, 2),
                'weekday_orders' => round($weekdayMean, 2),
                'recent_orders' => round($recentMean, 2),
                'external_score' => round($externalScore, 2),
            ],
        ];
    }

    private function samples(DeliveryArea $area, Carbon $date, int $hour): array
    {
        $lookbackDays = max(7, (int) \App\Models\AppSetting::getValue('gig_ml_forecast_lookback_days', 56));
        $start = $date->copy()->subDays($lookbackDays)->startOfDay();
        $end = $date->copy()->subDay()->endOfDay();
        $orders = Order::query()
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$start, $end])
            ->get(['id', 'delivery_lat', 'delivery_lng', 'delivered_at'])
            ->filter(fn (Order $order) => $order->delivered_at && (int) $order->delivered_at->format('G') === $hour)
            ->filter(fn (Order $order) => $order->delivery_lat !== null && $order->delivery_lng !== null && $area->containsPoint((float) $order->delivery_lat, (float) $order->delivery_lng));

        return $orders
            ->groupBy(fn (Order $order) => $order->delivered_at->toDateString())
            ->map(fn ($dayOrders, string $day) => [
                'date' => $day,
                'weekday' => (int) Carbon::parse($day)->dayOfWeek,
                'orders' => $dayOrders->count(),
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }

    private function externalScore(?int $areaId, Carbon $date, int $hour): float
    {
        return (float) GigExternalSignal::where('area_id', $areaId)
            ->whereDate('date', $date->toDateString())
            ->where('hour', $hour)
            ->whereIn('source', ['weather', 'traffic', 'events'])
            ->sum('score');
    }
}