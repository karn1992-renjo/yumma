<?php

namespace App\Console\Commands;

use App\Services\WeatherSurgeService;
use Illuminate\Console\Command;

/**
 * Reconciles every active delivery area's zone surge fee with its live
 * weather. No-op unless AppSetting `weather_surge_enabled` is on.
 */
class ApplyWeatherSurge extends Command
{
    protected $signature = 'weather:apply-surge';

    protected $description = 'Auto-apply / clear the zone surge fee based on current weather in each delivery area';

    public function handle(WeatherSurgeService $service): int
    {
        $result = $service->sync();
        $this->info('weather:apply-surge ' . json_encode($result));

        return self::SUCCESS;
    }
}
