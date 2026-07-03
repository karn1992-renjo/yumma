<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsEtaService
{
    public function estimateDelivery(
        ?float $originLat,
        ?float $originLng,
        ?float $destinationLat,
        ?float $destinationLng,
        ?int $preparationMinutes = null,
        ?float $riderLat = null,
        ?float $riderLng = null
    ): array {
        $prepMinutes = max(0, (int) ($preparationMinutes ?? 0));
        $travel = $this->distanceMatrix(
            $originLat,
            $originLng,
            $destinationLat,
            $destinationLng
        );

        $riderTravel = null;
        if ($riderLat !== null && $riderLng !== null && $originLat !== null && $originLng !== null) {
            $riderTravel = $this->distanceMatrix(
                $riderLat,
                $riderLng,
                $originLat,
                $originLng
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
        ?float $destinationLng
    ): array {
        if (
            $originLat === null || $originLng === null ||
            $destinationLat === null || $destinationLng === null
        ) {
            return $this->emptyResult('missing_coordinates');
        }

        $apiKey = trim((string) AppSetting::getValue('google_maps_api_key', AppSetting::getValue('google_maps_key', '')));
        if ($apiKey === '') {
            return $this->fallbackResult($originLat, $originLng, $destinationLat, $destinationLng, 'fallback_no_api_key');
        }

        // Avoid making every restaurant card wait on an unreachable upstream.
        // The first failed lookup opens this short circuit; subsequent cards use
        // the local distance estimate immediately while Google recovers.
        if (Cache::has('google_maps_distance_matrix_circuit_open')) {
            return $this->fallbackResult(
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
                return $this->fallbackResult($originLat, $originLng, $destinationLat, $destinationLng, 'fallback_http_error');
            }

            $payload = $response->json();
            $element = $payload['rows'][0]['elements'][0] ?? null;

            if (($payload['status'] ?? null) !== 'OK' || ! is_array($element) || ($element['status'] ?? null) !== 'OK') {
                Cache::put('google_maps_distance_matrix_circuit_open', true, now()->addMinutes(2));
                return $this->fallbackResult($originLat, $originLng, $destinationLat, $destinationLng, 'fallback_api_status');
            }

            $distanceMeters = (int) ($element['distance']['value'] ?? 0);
            $durationSeconds = (int) ($element['duration']['value'] ?? 0);
            $trafficSeconds = (int) ($element['duration_in_traffic']['value'] ?? $durationSeconds);

            return [
                'distance_km' => round($distanceMeters / 1000, 2),
                'duration_minutes' => (int) ceil($durationSeconds / 60),
                'duration_in_traffic_minutes' => (int) ceil($trafficSeconds / 60),
                'source' => 'google_distance_matrix',
            ];
        } catch (\Throwable $e) {
            Cache::put('google_maps_distance_matrix_circuit_open', true, now()->addMinutes(2));
            $this->logDistanceMatrixFailure($e);

            return $this->fallbackResult($originLat, $originLng, $destinationLat, $destinationLng, 'fallback_exception');
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

    private function fallbackResult(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        string $source
    ): array {
        $distanceKm = $this->haversineDistance($originLat, $originLng, $destinationLat, $destinationLng);
        $minutes = max(5, (int) ceil(($distanceKm / 25) * 60));

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
}
