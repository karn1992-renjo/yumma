<?php

namespace App\Support;

/**
 * Minimal Indian-numbering amount-to-words for invoices. No package needed.
 */
class AmountInWords
{
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
