<?php

namespace App\Support;

/**
 * Formats an amount in a SPECIFIC currency code -- as opposed to
 * `AppSetting::sanitizedCurrencySymbol()`/`currencyDecimals()`, which always
 * format in the platform's single global currency. Exists so an order that
 * was actually charged in a different currency (a UK/GCC/EU/US/AU-SG order,
 * via its own resolved CountryTaxProfile.currency_code) can display its own
 * amounts correctly on its own invoice, without retrofitting the 30+
 * existing call sites that legitimately want the platform's global currency
 * (the admin dashboard, restaurant payouts, etc. all still bill/settle in
 * one platform currency -- this is a per-order DISPLAY concern only, not a
 * full multi-currency ledger/settlement system).
 */
class CurrencyFormatter
{
    /** ISO 4217 code => symbol, for the currencies this platform's tax modules actually use. */
    private const SYMBOLS = [
        'INR' => '₹',
        'GBP' => '£',
        'EUR' => '€',
        'USD' => '$',
        'AUD' => 'A$',
        'SGD' => 'S$',
        'AED' => 'د.إ',
        'SAR' => '﷼',
        'BHD' => '.د.ب',
        'OMR' => '﷼',
    ];

    /** Currencies conventionally quoted to 3 decimal places (Bahraini/Omani dinar/rial), not 2. */
    private const THREE_DECIMAL_CURRENCIES = ['BHD', 'OMR'];

    public static function symbolFor(string $currencyCode): string
    {
        $code = strtoupper(trim($currencyCode));

        return self::SYMBOLS[$code] ?? ($code !== '' ? $code . ' ' : '');
    }

    public static function decimalsFor(string $currencyCode): int
    {
        return in_array(strtoupper(trim($currencyCode)), self::THREE_DECIMAL_CURRENCIES, true) ? 3 : 2;
    }

    public static function format(float $amount, string $currencyCode): string
    {
        return self::symbolFor($currencyCode) . number_format($amount, self::decimalsFor($currencyCode));
    }
}
