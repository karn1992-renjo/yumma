<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Carbon;

/**
 * Platform-wide late-night delivery surcharge.
 *
 * The customer pays a flat extra fee on delivery orders placed inside the
 * configured night window; the identical amount is credited to the driver
 * who completes the order as a night bonus (see
 * GigIncentiveService::calculateGigEarnings, which sums Order::night_surcharge).
 * Self-funding -- no platform subsidy.
 *
 * Config (admin Settings -> Driver Assignment):
 *   night_surcharge_enabled  0|1
 *   night_surcharge_amount   flat rupee amount
 *   night_surcharge_start    "HH:MM" (default 23:00)
 *   night_surcharge_end      "HH:MM" (default 06:00, may wrap past midnight)
 */
class NightSurchargeService
{
    public function enabled(): bool
    {
        return filter_var(AppSetting::getValue('night_surcharge_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function amount(): float
    {
        return round((float) AppSetting::getValue('night_surcharge_amount', 0), 2);
    }

    /** @return array{start: string, end: string} */
    public function window(): array
    {
        return [
            'start' => $this->normalizeTime(AppSetting::getValue('night_surcharge_start', '23:00'), '23:00'),
            'end' => $this->normalizeTime(AppSetting::getValue('night_surcharge_end', '06:00'), '06:00'),
        ];
    }

    /**
     * The surcharge that applies to an order placed at [$when] (defaults to
     * now, Asia/Kolkata). Zero unless enabled, a positive amount is set, and
     * the time is inside the window.
     */
    public function amountFor(?Carbon $when = null): float
    {
        if (! $this->enabled()) {
            return 0.0;
        }

        $amount = $this->amount();
        if ($amount <= 0) {
            return 0.0;
        }

        $when ??= Carbon::now('Asia/Kolkata');
        $window = $this->window();

        return $this->within($when->format('H:i'), $window['start'], $window['end']) ? $amount : 0.0;
    }

    public function isNightNow(?Carbon $when = null): bool
    {
        return $this->amountFor($when) > 0;
    }

    private function within(string $current, string $start, string $end): bool
    {
        $c = $this->toMinutes($current);
        $s = $this->toMinutes($start);
        $e = $this->toMinutes($end);

        if ($s === $e) {
            return false;
        }

        return $e > $s
            ? ($c >= $s && $c < $e)
            : ($c >= $s || $c < $e); // window wraps past midnight
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }

    private function normalizeTime($value, string $fallback): string
    {
        $value = trim((string) $value);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $fallback;
    }
}
