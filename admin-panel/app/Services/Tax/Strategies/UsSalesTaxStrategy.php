<?php

namespace App\Services\Tax\Strategies;

use App\Models\CountryTaxProfile;
use App\Models\Restaurant;
use App\Services\Tax\Contracts\TaxStrategy;
use App\Services\Tax\Strategies\Concerns\BuildsGenericInvoiceSummary;
use App\Services\Tax\TaxResult;

/**
 * US sales tax — structurally different from every VAT-style strategy built
 * so far, not just a renamed copy:
 *
 *  - There is no national rate. Sales tax is set at the STATE level (this
 *    strategy reads the restaurant's `state_code`, reused for a US 2-letter
 *    abbreviation the same way it holds an India GST state code when the
 *    resolved country is 'IN' — a restaurant is only ever in one country's
 *    scheme at a time, so there is no collision). Rates live in the 'US'
 *    CountryTaxProfile's `config.rates` map, one entry per state.
 *  - Only the STATE base rate is modeled. Real US sales tax often has an
 *    additional county/city/district layer on top (e.g. a Los Angeles order
 *    is ~9.5%+, not California's 7.25% state rate alone) — that requires a
 *    real address-to-jurisdiction rate lookup (what services like Avalara/
 *    TaxJar exist for) which is out of scope here. This is a DELIBERATE,
 *    flagged simplification, not an oversight — `detailed.rate_scope` is
 *    always 'state_only' so this is a checkable fact, never silently assumed
 *    to be the full rate.
 *  - Prices are tax-EXCLUSIVE by US retail convention (menu price excludes
 *    tax, tax is added at checkout) — the opposite of the VAT-style
 *    strategies' inclusive-by-law default.
 *  - Liability is via marketplace-facilitator law, not restaurant
 *    registration: since South Dakota v. Wayfair (2018) essentially every
 *    state with a sales tax requires a marketplace facilitator (this
 *    platform) to collect and remit tax on orders it facilitates, regardless
 *    of whether the individual restaurant itself has nexus/registration. So,
 *    unlike GstTaxService/UkVat/GccVat/EuVat, this strategy does NOT gate on
 *    a restaurant-level registration flag — it applies whenever the
 *    resolved state has a configured rate greater than zero (covering the
 *    five states with no state sales tax at all: AK, DE, MT, NH, OR).
 */
class UsSalesTaxStrategy implements TaxStrategy
{
    use BuildsGenericInvoiceSummary;

    /** State sales tax collects/remits under marketplace-facilitator law -- see class docblock. */
    public function countryCode(): string
    {
        return 'US';
    }

    public function taxName(): string
    {
        return 'Sales Tax';
    }

    public function appliesTo(?Restaurant $restaurant): bool
    {
        return $this->rateFor($restaurant) > 0;
    }

    public function calculate(?Restaurant $restaurant, array $lines, array $charges = []): ?TaxResult
    {
        $rate = $this->rateFor($restaurant);
        if ($rate <= 0) {
            return null;
        }

        $decimals = 2;
        $state = $this->stateCode($restaurant);

        $itemsTotal = round((float) collect($lines)->sum('line_total'), $decimals);
        $feesTotal = round((float) array_sum(array_map('floatval', $charges)), $decimals);

        // Exclusive pricing: the full item + fee total is the taxable base, and
        // the computed tax is entirely "added on top" -- there is no inclusive
        // portion to net out the way there is for VAT-style strategies.
        $itemTax = round($itemsTotal * $rate / 100, $decimals);
        $feeTax = round($feesTotal * $rate / 100, $decimals);
        $totalTax = round($itemTax + $feeTax, $decimals);

        $rateSummary = [[
            'rate' => $rate,
            'taxable_value' => round($itemsTotal + $feesTotal, $decimals),
            'tax' => $totalTax,
        ]];

        return new TaxResult(
            tax: $totalTax,
            taxRate: $rate,
            taxLabel: 'Sales Tax',
            taxBreakdown: $rateSummary,
            detailed: [
                'country_code' => 'US',
                'tax_name' => 'Sales Tax',
                'state' => $state,
                'rate_scope' => 'state_only', // see class docblock -- local/county/city surtax not included
                'liability' => 'marketplace_facilitator',
                'prices_inclusive' => false,
                'items_taxable_value' => $itemsTotal,
                'items_tax' => $itemTax,
                'fees_taxable_value' => $feesTotal,
                'fees_tax' => $feeTax,
                'tax_total' => $totalTax,
                'rate_summary' => $rateSummary,
            ],
        );
    }

    public function registrationFields(): array
    {
        return [
            // Optional: US sales tax is collected under marketplace-facilitator law
            // (the platform's own multi-state registrations), not the restaurant's
            // own -- unlike GSTIN/VAT-ID this is not required per-restaurant.
            ['key' => 'sales_tax_permit_number', 'label' => "Seller's Permit / Sales Tax Number (optional)", 'required' => false],
        ];
    }

    public function complianceFeatures(): array
    {
        return ['marketplace_facilitator', 'sales_tax_invoice'];
    }

    public function customSettingsRoute(): ?string
    {
        // Per-state rates don't fit the generic single-rate editor -- needs its own page.
        return 'admin.settings.us-sales-tax';
    }

    public function invoiceSummary(array $detailed): array
    {
        // No registration key: US sales tax is collected under marketplace-facilitator
        // law (the platform's own registrations), not a per-restaurant number -- see
        // registrationFields(), which marks the seller's-permit field optional/informational.
        $notes = isset($detailed['state']) ? ["State: {$detailed['state']}"] : [];

        return $this->genericInvoiceSummary($detailed, 'Sales Tax', "Seller's Permit / Sales Tax Number", null, 'tax_total', $notes);
    }

    private function stateCode(?Restaurant $restaurant): ?string
    {
        $code = $restaurant?->state_code;

        return $code ? strtoupper(trim($code)) : null;
    }

    private function rateFor(?Restaurant $restaurant): float
    {
        $state = $this->stateCode($restaurant);
        if (! $state) {
            return 0.0;
        }

        $profile = CountryTaxProfile::where('country_code', 'US')->first();

        return max(0.0, (float) ($profile?->configValue("rates.{$state}") ?? 0.0));
    }
}
