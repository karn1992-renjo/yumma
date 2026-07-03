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

            if (str_starts_with($digits, '0')) {
                $digits = ltrim($digits, '0');
            }

            return $defaultCountryCode . $digits;
        }

        return '+' . ltrim($digits, '0');
    }

    public static function isValidIndianMobile(?string $phone, ?string $defaultCountryCode = null): bool
    {
        $normalized = self::normalize($phone, $defaultCountryCode ?: '+91');

        return (bool) preg_match('/^\+91[6-9]\d{9}$/', $normalized);
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
