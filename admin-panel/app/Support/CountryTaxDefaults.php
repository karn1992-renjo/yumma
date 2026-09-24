<?php

namespace App\Support;

/**
 * Reference data for the "Add Country" form's dynamic auto-fill (name,
 * currency, tax name, suggested rate) — display/defaults only, never used
 * for actual tax calculation (that's CountryTaxRegistry + TaxStrategy).
 * Deliberately not exhaustive; countries not listed here can still be added
 * with manually-typed values.
 */
class CountryTaxDefaults
{
    public const COUNTRIES = [
        'IN' => ['name' => 'India', 'currency' => 'INR', 'tax_name' => 'GST', 'rate' => 5],
        'GB' => ['name' => 'United Kingdom', 'currency' => 'GBP', 'tax_name' => 'VAT', 'rate' => 20],
        'AE' => ['name' => 'United Arab Emirates', 'currency' => 'AED', 'tax_name' => 'VAT', 'rate' => 5],
        'SA' => ['name' => 'Saudi Arabia', 'currency' => 'SAR', 'tax_name' => 'VAT', 'rate' => 15],
        'BH' => ['name' => 'Bahrain', 'currency' => 'BHD', 'tax_name' => 'VAT', 'rate' => 10],
        'OM' => ['name' => 'Oman', 'currency' => 'OMR', 'tax_name' => 'VAT', 'rate' => 5],
        'DE' => ['name' => 'Germany', 'currency' => 'EUR', 'tax_name' => 'VAT', 'rate' => 19],
        'FR' => ['name' => 'France', 'currency' => 'EUR', 'tax_name' => 'VAT', 'rate' => 20],
        'ES' => ['name' => 'Spain', 'currency' => 'EUR', 'tax_name' => 'VAT', 'rate' => 21],
        'IT' => ['name' => 'Italy', 'currency' => 'EUR', 'tax_name' => 'VAT', 'rate' => 22],
        'NL' => ['name' => 'Netherlands', 'currency' => 'EUR', 'tax_name' => 'VAT', 'rate' => 21],
        'IE' => ['name' => 'Ireland', 'currency' => 'EUR', 'tax_name' => 'VAT', 'rate' => 23],
        'US' => ['name' => 'United States', 'currency' => 'USD', 'tax_name' => 'Sales Tax', 'rate' => 0],
        'AU' => ['name' => 'Australia', 'currency' => 'AUD', 'tax_name' => 'GST', 'rate' => 10],
        'SG' => ['name' => 'Singapore', 'currency' => 'SGD', 'tax_name' => 'GST', 'rate' => 9],
        'NZ' => ['name' => 'New Zealand', 'currency' => 'NZD', 'tax_name' => 'GST', 'rate' => 15],
        'CA' => ['name' => 'Canada', 'currency' => 'CAD', 'tax_name' => 'GST/HST', 'rate' => 5],
        'MY' => ['name' => 'Malaysia', 'currency' => 'MYR', 'tax_name' => 'Service Tax', 'rate' => 6],
        'PK' => ['name' => 'Pakistan', 'currency' => 'PKR', 'tax_name' => 'Sales Tax', 'rate' => 17],
        'BD' => ['name' => 'Bangladesh', 'currency' => 'BDT', 'tax_name' => 'VAT', 'rate' => 15],
        'NG' => ['name' => 'Nigeria', 'currency' => 'NGN', 'tax_name' => 'VAT', 'rate' => 7.5],
        'ZA' => ['name' => 'South Africa', 'currency' => 'ZAR', 'tax_name' => 'VAT', 'rate' => 15],
    ];

    public static function nameFor(string $countryCode): string
    {
        return self::COUNTRIES[strtoupper($countryCode)]['name'] ?? strtoupper($countryCode);
    }

    /** Countries with a real, dedicated compliance module (not just the generic fallback). */
    public static function dedicated(): array
    {
        return app(\App\Services\Tax\CountryTaxRegistry::class)->dedicatedCountryCodes();
    }
}
