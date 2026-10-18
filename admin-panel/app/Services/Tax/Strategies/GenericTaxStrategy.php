<?php

namespace App\Services\Tax\Strategies;

use App\Models\CountryTaxProfile;
use App\Models\Restaurant;
use App\Services\Tax\Contracts\TaxStrategy;
use App\Services\Tax\Strategies\Concerns\BuildsGenericInvoiceSummary;
use App\Services\Tax\TaxResult;
use App\Support\CurrencyFormatter;

/**
 * Honest fallback for a country that has an active CountryTaxProfile row but
 * no dedicated module (class) yet: a single, correctly-labeled flat-rate tax
 * line — no fabricated filings, no invented compliance features. This is
 * NEVER meant to be "the VAT engine" for every non-India country; it exists
 * so an admin who activates a country before its real module ships gets a
 * correct simple calculation instead of silently getting India's rules or no
 * tax at all.
 */
class GenericTaxStrategy implements TaxStrategy
{
    use BuildsGenericInvoiceSummary;

    public function __construct(private readonly CountryTaxProfile $profile)
    {
    }

    public function countryCode(): string
    {
        return $this->profile->country_code;
    }

    public function taxName(): string
    {
        return $this->profile->tax_name ?: 'Tax';
    }

    public function appliesTo(?Restaurant $restaurant): bool
    {
        return $this->profile->is_active && $this->rate() > 0;
    }

    public function calculate(?Restaurant $restaurant, array $lines, array $charges = []): ?TaxResult
    {
        if (! $this->appliesTo($restaurant)) {
            return null;
        }

        $rate = $this->rate();
        $includeFeesInBase = (bool) $this->profile->configValue('taxable_base_includes_fees', false);
        $decimals = CurrencyFormatter::decimalsFor((string) ($this->profile->currency_code ?? 'USD'));

        $itemsTotal = round((float) collect($lines)->sum('line_total'), $decimals);
        $feesTotal = $includeFeesInBase
            ? round((float) array_sum(array_map('floatval', $charges)), $decimals)
            : 0.0;
        $taxableValue = $itemsTotal + $feesTotal;
        $tax = round($taxableValue * $rate / 100, $decimals);

        $breakdownRow = [
            'rate' => $rate,
            'taxable_value' => $taxableValue,
            'tax' => $tax,
        ];

        return new TaxResult(
            tax: $tax,
            taxRate: $rate,
            taxLabel: $this->taxName(),
            taxBreakdown: [$breakdownRow],
            detailed: [
                'country_code' => $this->countryCode(),
                'tax_name' => $this->taxName(),
                'rate' => $rate,
                'taxable_value' => $taxableValue,
                'tax_total' => $tax,
                'rate_summary' => [$breakdownRow],
            ],
        );
    }

    public function registrationFields(): array
    {
        return [
            ['key' => 'tax_registration_number', 'label' => $this->taxName() . ' Registration Number', 'required' => false],
        ];
    }

    public function complianceFeatures(): array
    {
        return [];
    }

    public function customSettingsRoute(): ?string
    {
        return null;
    }

    public function invoiceSummary(array $detailed): array
    {
        $taxName = $detailed['tax_name'] ?? $this->taxName();

        return $this->genericInvoiceSummary($detailed, $taxName, $taxName . ' Registration Number', 'tax_registration_number', 'tax_total');
    }

    private function rate(): float
    {
        return max(0.0, (float) $this->profile->configValue('rate', 0));
    }
}
