<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsEtaService
{
    private const DEFAULT_SPEED_KMPH = 25.0;
    private const DEFAULT_TRAFFIC_MULTIPLIER = 1.2;
    private const DEFAULT_MINIMUM_MINUTES = 5;
    private const DEFAULT_LOCAL_CACHE_MINUTES = 15;
    private const DEFAULT_ROAD_CACHE_MINUTES = 360;

    public function estimateDelivery(
        ?float $originLat,
        ?float $originLng,
        ?float $destinationLat,
        ?float $destinationLng,
        ?int $preparationMinutes = null,
        ?float $riderLat = null,
        ?float $riderLng = null,
        bool $allowBillableLookup = false
    ): array {
        $prepMinutes = max(0, (int) ($preparationMinutes ?? 0));
        $travel = $this->distanceMatrix(
            $originLat,
            $originLng,
            $destinationLat,
            $destinationLng,
            $allowBillableLookup
        );

        $riderTravel = null;
        if ($riderLat !== null && $riderLng !== null && $originLat !== null && $originLng !== null) {
            $riderTravel = $this->distanceMatrix(
                $riderLat,
                $riderLng,
                $originLat,
                $originLng,
                $allowBillableLookup
            );
        }

        $travelMinutes = (int) ($travel['duration_in_traffic_minutes'] ?? $travel['duration_minutes'] ?? 0);
        $totalMinutes = $prepMinutes + $travelMinutes;

        return [
            'preparation_minutes' => $prepMinutes,
            'travel_minutes' => $travel['duration_minutes'],
            'traffic_travel_minutes' => $travel['duration_in_traffic_minutes'] ?? $travel['duration_minutes'],
            'travel_distance_km' => $travel['distance_km'],
            'rider_to_restaurant_minutes' => $riderTravel['duration_in_traffic_minutes'] ?? $riderTravel['duration_minutes'] ?? null,
            'rider_to_restaurant_distance_km' => $riderTravel['distance_km'] ?? null,
            'eta_minutes' => $totalMinutes > 0 ? $totalMinutes : null,
            'eta_range' => $this->buildRangeLabel($totalMinutes),
            'source' => $travel['source'],
        ];
    }

    public function distanceMatrix(
        ?float $originLat,
        ?float $originLng,
        ?float $destinationLat,
        ?float $destinationLng,
        bool $allowBillableLookup = false
    ): array {
        if (
            $originLat === null || $originLng === null ||
            $destinationLat === null || $destinationLng === null
        ) {
            return $this->emptyResult('missing_coordinates');
        }

        $roadCacheKey = $this->routeCacheKey('google_route_distance', $originLat, $originLng, $destinationLat, $destinationLng);
        $cachedRoadResult = Cache::get($roadCacheKey);
        if (is_array($cachedRoadResult)) {
            return $cachedRoadResult;
        }

        if (! $allowBillableLookup || ! $this->billableDistanceMatrixEnabled()) {
            return $this->cachedFallbackResult(
                $originLat,
                $originLng,
                $destinationLat,
                $destinationLng,
                'haversine_estimate'
            );
        }

        $apiKey = trim((string) AppSetting::getValue('google_maps_api_key', AppSetting::getValue('google_maps_key', '')));
        if ($apiKey === '') {
            return $this->cachedFallbackResult($originLat, $originLng, $destinationLat, $destinationLng, 'fallback_no_api_key');
        }

        if (Cache::has('google_maps_distance_matrix_circuit_open')) {
            return $this->cachedFallbackResult(
                $originLat,
                $originLng,
                $destinationLat,
                $destinationLng,
                'fallback_circuit_open'
            );
        }

        try {
            $response = Http::connectTimeout(1)->timeout(2)->get(
                'https://maps.googleapis.com/maps/api/distancematrix/json',
                [
                    'origins' => $originLat . ',' . $originLng,
                    'destinations' => $destinationLat . ',' . $destinationLng,
                    'departure_time' => 'now',
                    'traffic_model' => 'best_guess',
                    'mode' => 'driving',
                    'key' => $apiKey,
                ]
            );

            if (! $response->ok()) {
                Cache::put('google_maps_distance_matrix_circuit_open', true, now()->addMinutes(2));
                return $this->cachedFallbackResult($originLat, $originLng, $destinationLat, $destinationLng, 'fallback_http_error');
            }

            $payload = $response->json();
            $element = $payload['rows'][0]['elements'][0] ?? null;

            if (($payload['status'] ?? null) !== 'OK' || ! is_array($element) || ($element['status'] ?? null) !== 'OK') {
                Cache::put('google_maps_distance_matrix_circuit_open', true, now()->addMinutes(2));
                return $this->cachedFallbackResult($originLat, $originLng, $destinationLat, $destinationLng, 'fallback_api_status');
            }

            $distanceMeters = (int) ($element['distance']['value'] ?? 0);
            $durationSeconds = (int) ($element['duration']['value'] ?? 0);
            $trafficSeconds = (int) ($element['duration_in_traffic']['value'] ?? $durationSeconds);

            $result = [
                'distance_km' => round($distanceMeters / 1000, 2),
                'duration_minutes' => (int) ceil($durationSeconds / 60),
                'duration_in_traffic_minutes' => (int) ceil($trafficSeconds / 60),
                'source' => 'google_distance_matrix',
            ];

            Cache::put($roadCacheKey, $result, now()->addMinutes($this->roadDistanceCacheMinutes()));

            return $result;
        } catch (\Throwable $e) {
            Cache::put('google_maps_distance_matrix_circuit_open', true, now()->addMinutes(2));
            $this->logDistanceMatrixFailure($e);

            return $this->cachedFallbackResult($originLat, $originLng, $destinationLat, $destinationLng, 'fallback_exception');
        }
    }

    private function logDistanceMatrixFailure(\Throwable $exception): void
    {
        $cacheKey = 'google_maps_distance_matrix_failure_warning';
        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, now()->addMinutes(10));
        Log::warning('Distance Matrix lookup failed; using fallback ETA: ' . $this->sanitizeLogMessage($exception->getMessage()));
    }

    private function sanitizeLogMessage(string $message): string
    {
        return preg_replace('/([?&]key=)[^&\s]+/i', '$1[redacted]', $message) ?? $message;
    }

    private function cachedFallbackResult(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        string $source
    ): array {
        $cacheKey = $this->routeCacheKey('haversine_route_estimate', $originLat, $originLng, $destinationLat, $destinationLng);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->fallbackResult($originLat, $originLng, $destinationLat, $destinationLng, $source);
        Cache::put($cacheKey, $result, now()->addMinutes($this->localEstimateCacheMinutes()));

        return $result;
    }

    private function fallbackResult(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        string $source
    ): array {
        $distanceKm = $this->haversineDistance($originLat, $originLng, $destinationLat, $destinationLng);
        $speedKmph = max(5.0, (float) AppSetting::getValue('estimated_delivery_speed_kmph', self::DEFAULT_SPEED_KMPH));
        $trafficMultiplier = max(1.0, (float) AppSetting::getValue('estimated_delivery_traffic_multiplier', self::DEFAULT_TRAFFIC_MULTIPLIER));
        $minimumMinutes = max(1, (int) AppSetting::getValue('estimated_delivery_min_minutes', self::DEFAULT_MINIMUM_MINUTES));
        $minutes = max($minimumMinutes, (int) ceil(($distanceKm / $speedKmph) * 60 * $trafficMultiplier));

        return [
            'distance_km' => round($distanceKm, 2),
            'duration_minutes' => $minutes,
            'duration_in_traffic_minutes' => $minutes,
            'source' => $source,
        ];
    }

    private function emptyResult(string $source): array
    {
        return [
            'distance_km' => null,
            'duration_minutes' => null,
            'duration_in_traffic_minutes' => null,
            'source' => $source,
        ];
    }

    private function buildRangeLabel(?int $minutes): ?string
    {
        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        $upper = $minutes <= 10 ? $minutes + 5 : $minutes + 7;

        return $minutes . '-' . $upper . ' mins';
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $angle = sin($latDelta / 2) * sin($latDelta / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lonDelta / 2) * sin($lonDelta / 2);

        return $earthRadius * (2 * atan2(sqrt($angle), sqrt(1 - $angle)));
    }

    private function billableDistanceMatrixEnabled(): bool
    {
        return filter_var(
            AppSetting::getValue('google_maps_distance_matrix_enabled', '0'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function localEstimateCacheMinutes(): int
    {
        return max(1, (int) AppSetting::getValue('haversine_eta_cache_minutes', self::DEFAULT_LOCAL_CACHE_MINUTES));
    }

    private function roadDistanceCacheMinutes(): int
    {
        return max(1, (int) AppSetting::getValue('google_maps_distance_matrix_cache_minutes', self::DEFAULT_ROAD_CACHE_MINUTES));
    }

    private function routeCacheKey(
        string $prefix,
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng
    ): string {
        $coordinates = array_map(
            static fn (float $value) => number_format($value, 4, '.', ''),
            [$originLat, $originLng, $destinationLat, $destinationLng]
        );

        return $prefix . ':v1:' . implode(':', $coordinates);
    }
}
