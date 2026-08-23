<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\DriverDeviceAttestation;
use App\Models\DriverLocationEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DriverLocationTrustService
{
    public function record(User $driver, array $payload): array
    {
        $recordedAt = ! empty($payload['recorded_at']) ? \Carbon\Carbon::parse($payload['recorded_at']) : now();
        $attestation = $this->verifyLocalAttestation($driver, $payload);
        $previous = Schema::hasTable('driver_location_events')
            ? DriverLocationEvent::where('driver_id', $driver->id)->latest('recorded_at')->first()
            : null;
        $risk = $this->scoreLocation($payload, $previous, $recordedAt, $attestation);

        if (Schema::hasTable('driver_location_events')) {
            DriverLocationEvent::create([
                'driver_id' => $driver->id,
                'lat' => (float) $payload['lat'],
                'lng' => (float) $payload['lng'],
                'accuracy_meters' => $payload['accuracy_meters'] ?? $payload['accuracy'] ?? null,
                'speed_mps' => $payload['speed_mps'] ?? $payload['speed'] ?? null,
                'heading' => $payload['heading'] ?? null,
                'is_mock_location' => (bool) ($payload['is_mock_location'] ?? false),
                'device_id' => $payload['device_id'] ?? null,
                'attestation_status' => $attestation['status'],
                'risk_score' => $risk['score'],
                'risk_reasons' => $risk['reasons'],
                'recorded_at' => $recordedAt,
            ]);
        }

        Cache::put("driver_location_risk_{$driver->id}", $risk + ['attestation_status' => $attestation['status']], 900);

        return $risk + ['attestation_status' => $attestation['status']];
    }

    public function isTrusted(int $driverId): bool
    {
        $risk = Cache::get("driver_location_risk_{$driverId}", ['score' => 0]);
        $threshold = (int) AppSetting::getValue('driver_location_block_risk_score', 85);

        return (int) ($risk['score'] ?? 0) < $threshold;
    }

    private function verifyLocalAttestation(User $driver, array $payload): array
    {
        $token = trim((string) ($payload['attestation_token'] ?? ''));
        $status = $token !== '' ? 'present_unverified' : 'missing';
        $risk = $token !== '' ? 10 : 30;

        if (Schema::hasTable('driver_device_attestations')) {
            DriverDeviceAttestation::updateOrCreate(
                ['driver_id' => $driver->id, 'device_id' => $payload['device_id'] ?? null],
                [
                    'platform' => $payload['platform'] ?? null,
                    'provider' => 'local',
                    'status' => $status,
                    'risk_score' => $risk,
                    'claims' => [
                        'has_token' => $token !== '',
                        'app_version' => $payload['app_version'] ?? null,
                    ],
                    'verified_at' => now(),
                ]
            );
        }

        return ['status' => $status, 'risk_score' => $risk];
    }

    private function scoreLocation(array $payload, ?DriverLocationEvent $previous, $recordedAt, array $attestation): array
    {
        $score = (int) ($attestation['risk_score'] ?? 0);
        $reasons = [];

        if ((bool) ($payload['is_mock_location'] ?? false)) {
            $score += 80;
            $reasons[] = 'mock_location_flag';
        }

        $accuracy = $payload['accuracy_meters'] ?? $payload['accuracy'] ?? null;
        if ($accuracy !== null && (float) $accuracy > 150) {
            $score += 20;
            $reasons[] = 'low_location_accuracy';
        }

        if ($previous && $previous->recorded_at) {
            $minutes = max(0.01, $previous->recorded_at->diffInSeconds($recordedAt, false) / 60);
            $km = $this->distanceKm((float) $previous->lat, (float) $previous->lng, (float) $payload['lat'], (float) $payload['lng']);
            $speedKmh = ($km / $minutes) * 60;
            $maxSpeed = (float) AppSetting::getValue('driver_location_max_speed_kmh', 95);
            if ($speedKmh > $maxSpeed) {
                $score += min(60, (int) (($speedKmh - $maxSpeed) / 2));
                $reasons[] = 'impossible_travel_speed';
            }
        }

        $score = min(100, $score);

        return [
            'score' => $score,
            'status' => $score >= 85 ? 'blocked' : ($score >= 55 ? 'review' : 'trusted'),
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = max(-1, min(1, $dist));
        return rad2deg(acos($dist)) * 60 * 1.1515 * 1.609344;
    }
}