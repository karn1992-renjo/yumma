<?php

namespace App\Services\Tax\Strategies;

use App\Models\CountryTaxProfile;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\Tax\Contracts\TaxStrategy;
use App\Services\Tax\Strategies\Concerns\BuildsGenericInvoiceSummary;
use App\Services\Tax\TaxResult;

/**
 * EU domestic VAT — one class, per member-state rate, same mechanical shape
 * as GccVatTaxStrategy (single VAT line, no CGST/SGST-style split, VAT-
 * inclusive consumer pricing required across the EU by Directive 2011/83/EU
 * and each state's own implementing rules).
 *
 * Deliberately does NOT implement OSS (One-Stop-Shop) cross-border reporting.
 * OSS exists for a supplier selling B2C into ANOTHER member state (the
 * customer's country's rate applies, remitted through one return in the
 * seller's home state instead of registering everywhere). That scenario does
 * not arise for hyperlocal food delivery: this platform's own delivery-fee
 * calculation (CheckoutController::calculateDeliveryFee) is a straight-line
 * distance between the restaurant and the customer, so a restaurant and its
 * customer are always in the same country in practice — VAT is always
 * "domestic" here, at the restaurant's own member state's rate, exactly like
 * every other line of business a restaurant already has. If this platform
 * ever adds genuine cross-border delivery (e.g. a restaurant near a land
 * border serving another country), OSS reporting would need to be modeled
 * as its own feature at that point — not fabricated here ahead of need.
 */
class EuVatTaxStrategy implements TaxStrategy
{
    use BuildsGenericInvoiceSummary;

    private const FALLBACK_RATES = [
        'DE' => 19.0,
        'FR' => 20.0,
        'ES' => 21.0,
        'IT' => 22.0,
        'NL' => 21.0,
        'IE' => 23.0,
    ];

    public function countryCode(): string
    {
        return 'DE'; // representative default; actual member state is read per-restaurant at calculate() time
    }

    public function taxName(): string
    {
        return 'VAT';
    }

    public function appliesTo(?Restaurant $restaurant): bool
    {
        return (bool) $restaurant?->is_vat_registered;
    }

    public function calculate(?Restaurant $restaurant, array $lines, array $charges = []): ?TaxResult
    {
        if (! $this->appliesTo($restaurant)) {
            return null;
        }

        $code = strtoupper($restaurant->resolvedCountryCode() ?: 'DE');
        $profile = CountryTaxProfile::where('country_code', $code)->first();
        $rate = (float) ($profile?->configValue('rate') ?? self::FALLBACK_RATES[$code] ?? self::FALLBACK_RATES['DE']);
        $inclusive = true;
        $decimals = 2;

        $ids = collect($lines)->pluck('menu_item_id')->filter()->unique()->values()->all();
        $items = $ids ? MenuItem::whereKey($ids)->get()->keyBy('id') : collect();

        $lineRows = [];
        foreach ($lines as $line) {
            $mi = $items->get($line['menu_item_id'] ?? null);
            $lineRate = max(0.0, (float) ($line['vat_rate'] ?? $line['gst_rate'] ?? $mi?->gst_rate ?? $rate));
            $lineInclusive = array_key_exists('price_inclusive', $line) ? (bool) $line['price_inclusive'] : $inclusive;

            $gross = max(0.0, (float) ($line['line_total'] ?? 0));
            $taxable = ($lineInclusive && $lineRate > 0) ? $gross * 100 / (100 + $lineRate) : $gross;
            $vat = round($taxable * $lineRate / 100, $decimals);

            $lineRows[] = [
                'name' => (string) ($line['name'] ?? 'Item'),
                'qty' => (int) ($line['quantity'] ?? 1),
                'rate' => $lineRate,
                'inclusive' => $lineInclusive,
                'gross' => round($gross, $decimals),
                'taxable_value' => round($taxable, $decimals),
                'vat' => $vat,
            ];
        }

        $chargeRows = [];
        foreach ([
            'delivery_fee' => 'Delivery fee',
            'platform_fee' => 'Platform fee',
            'service_charge' => 'Service charge',
            'packaging_charge' => 'Packaging charge',
        ] as $key => $label) {
            $amt = round((float) ($charges[$key] ?? 0), $decimals);
            if ($amt <= 0) {
                continue;
            }
            $vat = round($amt * $rate / 100, $decimals);
            $chargeRows[] = [
                'label' => $label,
                'rate' => $rate,
                'inclusive' => false,
                'taxable_value' => $amt,
                'vat' => $vat,
            ];
        }

        $all = array_merge($lineRows, $chargeRows);
        $rateSummary = [];
        foreach ($all as $row) {
            $key = number_format((float) $row['rate'], 2, '.', '');
            $rateSummary[$key] ??= ['rate' => (float) $row['rate'], 'taxable_value' => 0.0, 'tax' => 0.0];
            $rateSummary[$key]['taxable_value'] = round($rateSummary[$key]['taxable_value'] + $row['taxable_value'], $decimals);
            $rateSummary[$key]['tax'] = round($rateSummary[$key]['tax'] + $row['vat'], $decimals);
        }
        ksort($rateSummary);
        $rateSummary = array_values($rateSummary);

        $vatAdded = round(collect($all)->where('inclusive', false)->sum('vat'), $decimals);
        $vatTotal = round(collect($all)->sum('vat'), $decimals);
        $taxableTotal = round(collect($all)->sum('taxable_value'), $decimals);

        return new TaxResult(
            tax: $vatAdded,
            taxRate: (float) collect($rateSummary)->max('rate'),
            taxLabel: 'VAT',
            taxBreakdown: $rateSummary,
            detailed: [
                'country_code' => $code,
                'tax_name' => 'VAT',
                'prices_inclusive' => $inclusive,
                'vat_number' => $restaurant->vat_number,
                'supply_type' => 'domestic', // always -- see class docblock on why OSS/cross-border isn't modeled
                'taxable_total' => $taxableTotal,
                'vat_total' => $vatTotal,
                'vat_added' => $vatAdded,
                'lines' => $lineRows,
                'charges' => $chargeRows,
                'rate_summary' => $rateSummary,
            ],
        );
    }

    public function registrationFields(): array
    {
        return [
            ['key' => 'vat_number', 'label' => 'VAT Identification Number (VAT ID)', 'required' => true],
        ];
    }

    public function complianceFeatures(): array
    {
        return ['vat_invoice'];
    }

    public function customSettingsRoute(): ?string
    {
        return null;
    }

    public function invoiceSummary(array $detailed): array
    {
        return $this->genericInvoiceSummary($detailed, 'VAT', 'VAT Identification Number', 'vat_number', 'vat_total');
    }
}
