<?php

namespace App\Support;

class PhoneNumber
{
    // Indian mobile numbers are always exactly 10 digits. Used below to
    // tell a number that already carries its country code apart from a
    // bare local number that merely happens to start with the same digits
    // (e.g. 9155308530 starts with "91" but is NOT "+91" + "55308530").
    private const LOCAL_NUMBER_LENGTH = 10;

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

            // A number already carries the country code only if it was
            // given with a literal '+', or its digit count matches
            // country-code-length + local-number-length AND it starts with
            // those country digits. A bare prefix check (str_starts_with)
            // alone is wrong: a 10-digit Indian mobile number can
            // legitimately start with "91" (e.g. 9155308530), which would
            // otherwise be mistaken for an already-prefixed "+91" number
            // and lose its real country code (producing "+9155308530"
            // instead of the correct "+919155308530").
            $alreadyHasCountryCode = $hasPlus
                || (
                    str_starts_with($digits, $countryDigits)
                    && strlen($digits) === strlen($countryDigits) + self::LOCAL_NUMBER_LENGTH
                );

            if ($alreadyHasCountryCode) {
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
