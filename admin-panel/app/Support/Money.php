<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Rounds/formats a platform-currency amount to the admin-configured decimal
 * places (Settings -> currency_decimals, see AppSetting::currencyDecimals(),
 * clamped 2-5) instead of a hardcoded 2 -- both places matter: backend order
 * pricing/payout math that gets SAVED with the wrong precision, and blade
 * views that DISPLAY a raw float with excess/inconsistent precision.
 *
 * Not for per-order multi-currency display (an order actually billed in
 * GBP/EUR/etc via the country-tax feature) -- that's App\Support\CurrencyFormatter,
 * which resolves decimals from the order's own currency code, not this setting.
 */
class Money
{
    public static function decimals(): int
    {
        return AppSetting::currencyDecimals();
    }

    public static function round(int|float|string|null $value): float
    {
        return round((float) $value, self::decimals());
    }

    public static function format(int|float|string|null $value): string
    {
        return AppSetting::sanitizedCurrencySymbol() . number_format((float) $value, self::decimals());
    }
}
