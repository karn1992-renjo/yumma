<?php

namespace App\Support;

/**
 * Minimal amount-to-words for invoices. No package needed.
 *
 *  - `inr()`      Indian numbering (Crore/Lakh) -- India's invoice path only.
 *  - `generic()`  Western numbering (Thousand/Million/Billion) -- every
 *                 non-India country-tax invoice. Indian numbering is
 *                 genuinely wrong for a GBP/AED/USD/EUR/AUD/SGD amount, so
 *                 this is a separate method rather than a shared one with a
 *                 flag -- keeps `inr()` (already used by the revenue-critical
 *                 India invoice) completely untouched.
 */
class AmountInWords
{
    public static function generic(float $amount, string $currency = 'Units', string $fraction = 'Cents'): string
    {
        $amount = round($amount, 2);
        $whole = (int) floor($amount);
        $cents = (int) round(($amount - $whole) * 100);

        $words = self::westernWords($whole) . ' ' . $currency;
        if ($cents > 0) {
            $words .= ' and ' . self::westernWords($cents) . ' ' . $fraction;
        }

        return trim($words) . ' Only';
    }

    private static function westernWords(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $twoDigits = function (int $x) use ($ones, $tens): string {
            if ($x < 20) {
                return $ones[$x];
            }

            return trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]);
        };

        $threeDigits = function (int $x) use ($ones, $twoDigits): string {
            $hundred = intdiv($x, 100);
            $rest = $x % 100;
            $parts = [];
            if ($hundred) {
                $parts[] = $ones[$hundred] . ' Hundred';
            }
            if ($rest) {
                $parts[] = $twoDigits($rest);
            }

            return implode(' ', $parts);
        };

        $groupLabels = ['', ' Thousand', ' Million', ' Billion'];
        $parts = [];
        $groupIndex = 0;
        while ($n > 0) {
            $chunk = $n % 1000;
            $n = intdiv($n, 1000);
            if ($chunk > 0) {
                array_unshift($parts, $threeDigits($chunk) . $groupLabels[$groupIndex]);
            }
            $groupIndex++;
        }

        return implode(' ', $parts);
    }

    public static function inr(float $amount, string $currency = 'Rupees', string $fraction = 'Paise'): string
    {
        $amount = round($amount, 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $words = self::words($rupees) . ' ' . $currency;
        if ($paise > 0) {
            $words .= ' and ' . self::words($paise) . ' ' . $fraction;
        }

        return trim($words) . ' Only';
    }

    private static function words(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $twoDigits = function (int $x) use ($ones, $tens): string {
            if ($x < 20) {
                return $ones[$x];
            }
            return trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]);
        };

        $parts = [];
        $crore = intdiv($n, 10000000);
        $n %= 10000000;
        $lakh = intdiv($n, 100000);
        $n %= 100000;
        $thousand = intdiv($n, 1000);
        $n %= 1000;
        $hundred = intdiv($n, 100);
        $rest = $n % 100;

        if ($crore) {
            $parts[] = self::words($crore) . ' Crore';
        }
        if ($lakh) {
            $parts[] = $twoDigits($lakh) . ' Lakh';
        }
        if ($thousand) {
            $parts[] = $twoDigits($thousand) . ' Thousand';
        }
        if ($hundred) {
            $parts[] = $ones[$hundred] . ' Hundred';
        }
        if ($rest) {
            $parts[] = $twoDigits($rest);
        }

        return implode(' ', $parts);
    }
}
