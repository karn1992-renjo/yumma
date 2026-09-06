<?php

namespace App\Services\Tax;

class TaxDeduction
{
    public function __construct(
        public readonly float $amount,       // tax to deduct on this payout
        public readonly float $rate,         // % applied
        public readonly string $section,     // 194O | 194C | 52
        public readonly float $taxableValue, // base the rate was applied to
        public readonly float $grossForYtd = 0.0, // full gross to add to the deductee's YTD (TDS only)
        public readonly ?string $reason = null,
    ) {
    }

    public static function none(string $section, string $reason = 'not applicable', float $grossForYtd = 0.0): self
    {
        return new self(0.0, 0.0, $section, 0.0, $grossForYtd, $reason);
    }

    public function applies(): bool
    {
        return $this->amount > 0;
    }
}
