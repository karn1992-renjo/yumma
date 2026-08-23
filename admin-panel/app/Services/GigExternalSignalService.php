<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\GigExternalSignal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class GigExternalSignalService
{
    public function refresh(?Carbon $date = null): int
    {
        if (! Schema::hasTable('gig_external_signals')) {
            return 0;
        }

        $date ??= today();
        $count = 0;

        foreach (DeliveryArea::where('is_active', true)->get() as $area) {
            foreach (range(6, 23) as $hour) {
                foreach (['weather', 'events', 'traffic'] as $source) {
                    $this->storeSignal($area->id, $date, $hour, $source, $this->fallbackSignal($area, $date, $hour, $source));
                    $count++;
                }
            }
        }

        return $count;
    }

    public function ingest(array $payload): GigExternalSignal
    {
        $date = Carbon::parse($payload['date'] ?? today());

        return $this->storeSignal(
            $payload['area_id'] ?? null,
            $date,
            (int) ($payload['hour'] ?? 0),
            (string) ($payload['source'] ?? 'manual'),
            [
                'score' => (float) ($payload['score'] ?? 0),
                'payload' => $payload['payload'] ?? $payload,
            ]
        );
    }

    public function signalsFor(?int $areaId, Carbon $date, int $hour): array
    {
        if (! Schema::hasTable('gig_external_signals')) {
            return ['score' => 0, 'signals' => []];
        }

        $signals = GigExternalSignal::where('area_id', $areaId)
            ->whereDate('date', $date->toDateString())
            ->where('hour', $hour)
            ->get();

        return [
            'score' => (float) $signals->sum('score'),
            'signals' => $signals->mapWithKeys(fn ($signal) => [$signal->source => [
                'score' => (float) $signal->score,
                'payload' => $signal->payload,
            ]])->all(),
        ];
    }

    private function fallbackSignal(DeliveryArea $area, Carbon $date, int $hour, string $source): array
    {
        $score = 0.0;
        if ($source === 'weather' && in_array($hour, [12, 13, 19, 20, 21], true)) {
            $score = 0.5;
        }
        if ($source === 'events' && $date->isWeekend()) {
            $score = 0.75;
        }
        if ($source === 'traffic' && in_array($hour, [9, 18, 19, 20], true)) {
            $score = 0.6;
        }

        return [
            'score' => $score,
            'payload' => [
                'source' => $source,
                'mode' => 'local_baseline',
                'area_id' => $area->id,
                'area' => $area->name,
                'date' => $date->toDateString(),
                'hour' => $hour,
            ],
        ];
    }

    private function storeSignal(?int $areaId, Carbon $date, int $hour, string $source, array $signal): GigExternalSignal
    {
        return GigExternalSignal::updateOrCreate(
            [
                'area_id' => $areaId,
                'date' => $date->toDateString(),
                'hour' => $hour,
                'source' => $source,
            ],
            [
                'score' => $signal['score'] ?? 0,
                'payload' => $signal['payload'] ?? [],
                'fetched_at' => now(),
            ]
        );
    }
}