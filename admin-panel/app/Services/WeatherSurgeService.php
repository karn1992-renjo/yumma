<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\DeliveryArea;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Automatically raises the flat zone surge fee (see App\Services\ZoneSurgeService)
 * for delivery areas whose current weather would slow a rider down -- rain,
 * thunderstorm, snow, fog, high winds -- and clears it again once the weather
 * improves.
 *
 * It only ever touches surges it created itself, identified by the
 * "Weather surge: " reason prefix, so a manual or AI-driven zone surge that is
 * already active is left untouched.
 *
 * Opt-in and self-funding: does nothing unless AppSetting `weather_surge_enabled`
 * is true, and the amount charged flows through the existing surge pipeline
 * (Order::surge_fee -> driver zone_surge_bonus) with no platform subsidy.
 *
 * Weather data: Open-Meteo (https://open-meteo.com) -- free, no API key.
 */
class WeatherSurgeService
{
    public const REASON_PREFIX = 'Weather surge: ';

    public function __construct(private readonly ZoneSurgeService $zoneSurge)
    {
    }

    /**
     * Reconcile every active delivery area's surge state with its live weather.
     *
     * @return array{skipped?: string, activated?: int, cleared?: int, unchanged?: int}
     */
    public function sync(): array
    {
        $enabled = filter_var(AppSetting::getValue('weather_surge_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $amount = round((float) AppSetting::getValue('weather_surge_amount', 15), 2);

        // Feature off (or misconfigured) -> make sure we leave no weather surge
        // of our own still active, then stop.
        if (! $enabled || $amount <= 0) {
            $cleared = $this->clearManagedSurges();
            Log::info('WeatherSurgeService.sync', ['skipped' => $enabled ? 'amount<=0' : 'disabled', 'cleared' => $cleared]);

            return ['skipped' => $enabled ? 'amount<=0' : 'disabled', 'cleared' => $cleared];
        }

        $activated = 0;
        $cleared = 0;
        $unchanged = 0;

        $areas = DeliveryArea::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        foreach ($areas as $area) {
            $condition = $this->badWeatherCondition((float) $area->latitude, (float) $area->longitude);
            $managedByUs = str_starts_with((string) $area->surge_fee_reason, self::REASON_PREFIX);
            $reason = self::REASON_PREFIX . ($condition ?? '');

            if ($condition !== null) {
                // A manual / AI surge already covers this zone -- don't override it.
                if ($area->surge_fee_active && ! $managedByUs) {
                    $unchanged++;
                    continue;
                }

                $sameAmount = abs((float) $area->surge_fee_amount - $amount) < 0.01;
                if ($area->surge_fee_active && $managedByUs && $sameAmount
                    && $area->surge_fee_reason === $reason) {
                    $unchanged++;
                    continue;
                }

                $this->zoneSurge->activate($area, $amount, $reason);
                $activated++;
                continue;
            }

            // Weather is fine -- clear the surge only if we are the ones who set it.
            if ($area->surge_fee_active && $managedByUs) {
                $this->zoneSurge->deactivate($area);
                $cleared++;
            } else {
                $unchanged++;
            }
        }

        Log::info('WeatherSurgeService.sync', compact('activated', 'cleared', 'unchanged'));

        return compact('activated', 'cleared', 'unchanged');
    }

    /** Deactivate every surge this service is responsible for. */
    private function clearManagedSurges(): int
    {
        $cleared = 0;
        DeliveryArea::query()
            ->where('surge_fee_active', true)
            ->where('surge_fee_reason', 'like', self::REASON_PREFIX . '%')
            ->get()
            ->each(function (DeliveryArea $area) use (&$cleared) {
                $this->zoneSurge->deactivate($area);
                $cleared++;
            });

        return $cleared;
    }

    /**
     * A short human label for the current adverse condition at a point, or null
     * when the weather is unremarkable.
     */
    private function badWeatherCondition(float $lat, float $lng): ?string
    {
        try {
            $response = Http::timeout(8)->retry(1, 400)->get(
                'https://api.open-meteo.com/v1/forecast',
                [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'current' => 'weather_code,temperature_2m,wind_speed_10m',
                    'wind_speed_unit' => 'kmh',
                    'timezone' => 'auto',
                ]
            );

            if (! $response->ok()) {
                return null;
            }

            $current = $response->json('current');
            if (! is_array($current)) {
                return null;
            }

            $code = (int) ($current['weather_code'] ?? 0);
            $wind = (float) ($current['wind_speed_10m'] ?? 0);

            // WMO weather interpretation codes.
            return match (true) {
                $code >= 95 => 'Thunderstorm',
                ($code >= 71 && $code <= 77) || $code === 85 || $code === 86 => 'Snow',
                in_array($code, [65, 67, 82], true) => 'Heavy rain',
                ($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 82) => 'Rain',
                $code === 45 || $code === 48 => 'Fog',
                $wind >= 45 => 'High winds',
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('WeatherSurgeService weather lookup failed: ' . $e->getMessage());

            return null;
        }
    }
}
