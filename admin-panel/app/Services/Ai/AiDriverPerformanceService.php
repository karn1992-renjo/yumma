<?php

namespace App\Services\Ai;

use App\Models\DriverPerformanceScore;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AiDriverPerformanceService
{
    public function score(?int $driverId = null): array
    {
        $drivers = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['driver', 'delivery_partner']))
            ->when($driverId, fn (Builder $query) => $query->whereKey($driverId))
            ->limit($driverId ? 1 : 50)
            ->get();

        return $drivers->map(fn (User $driver) => $this->scoreDriver($driver))->values()->toArray();
    }

    private function scoreDriver(User $driver): array
    {
        $orders = Order::query()
            ->where('driver_id', $driver->id)
            ->where('created_at', '>=', now()->subDays(30));

        $total = (clone $orders)->count();
        $delivered = (clone $orders)->where('status', 'delivered')->count();
        $cancelled = (clone $orders)->whereIn('status', ['cancelled', 'delivery_failed'])->count();
        $rating = (float) (clone $orders)->whereNotNull('driver_rating')->avg('driver_rating');
        $acceptance = $total > 0 ? $delivered / $total : 0;
        $completionScore = $acceptance * 55;
        $qualityScore = ($rating ?: 4.0) / 5 * 30;
        $reliabilityScore = max(0, 15 - ($cancelled * 2));
        $score = round(min(100, $completionScore + $qualityScore + $reliabilityScore), 2);

        $classification = match (true) {
            $score >= 85 => 'excellent',
            $score >= 70 => 'good',
            $score >= 50 => 'review',
            default => 'risk',
        };

        DriverPerformanceScore::updateOrCreate(
            ['driver_id' => $driver->id, 'score_date' => today()->toDateString()],
            [
                'score' => $score,
                'classification' => $classification,
                'factors' => [
                    'completion_score' => round($completionScore, 2),
                    'quality_score' => round($qualityScore, 2),
                    'reliability_score' => round($reliabilityScore, 2),
                ],
                'raw_metrics' => compact('total', 'delivered', 'cancelled', 'rating'),
            ]
        );

        return [
            'driver_id' => $driver->id,
            'name' => $driver->name,
            'score' => $score,
            'classification' => $classification,
            'metrics' => compact('total', 'delivered', 'cancelled', 'rating'),
        ];
    }
}
