<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $phone, ?string $defaultCountryCode = null): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        $defaultCountryCode = self::normalizeCountryCode($defaultCountryCode);
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if ($defaultCountryCode !== '') {
            $countryDigits = substr($defaultCountryCode, 1);

            if ($hasPlus || str_starts_with($digits, $countryDigits)) {
                return '+' . $digits;
            }

            return $defaultCountryCode . $digits;
        }

        return '+' . $digits;
    }

    public static function isValidIndianMobile(?string $phone, ?string $defaultCountryCode = null): bool
    {
        $countryCode = self::normalizeCountryCode($defaultCountryCode ?: '+91');
        $normalized = self::normalize($phone, $countryCode);

        return $countryCode !== ''
            && (bool) preg_match('/^' . preg_quote($countryCode, '/') . '\d+$/', $normalized);
    }

    public static function normalizeCountryCode(?string $countryCode): string
    {
        $countryCode = trim((string) $countryCode);
        if ($countryCode === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $countryCode) ?? '';

        return $digits === '' ? '' : '+' . $digits;
    }
}
