<?php

namespace App\Services\Tax\Strategies;

use App\Models\CountryTaxProfile;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\Tax\Contracts\TaxStrategy;
use App\Services\Tax\Strategies\Concerns\BuildsGenericInvoiceSummary;
use App\Services\Tax\TaxResult;

/**
 * UK VAT — the first real, dedicated non-India module (see
 * country-tax-compliance-foundation memory for the priority order). Modeled
 * on the same shape as IndiaGstTaxStrategy/GstTaxService but genuinely
 * different where UK VAT law actually differs:
 *
 *  - ONE tax line per rate (no CGST/SGST-style split — that's an India dual-
 *    GST artifact, not a UK concept).
 *  - Consumer-facing UK prices are VAT-INCLUSIVE by law, so menu prices are
 *    treated as inclusive by default (opposite of India's exclusive default).
 *  - No Section 9(5)-equivalent "marketplace is deemed liable" override: that
 *    UK rule only applies to overseas sellers selling goods through a UK
 *    marketplace, not to a domestic restaurant's own food-delivery service —
 *    so, correctly, the RESTAURANT stays liable for its own item VAT, and the
 *    platform is liable only for its own delivery/platform-fee VAT (a
 *    perfectly ordinary B2C supply of a service).
 *  - Only a VAT-registered restaurant charges VAT at all — an unregistered
 *    restaurant (below the UK VAT registration threshold) cannot legally
 *    charge it, so orders from it fall back to the flat TaxSetting baseline,
 *    exactly like an unregistered India restaurant does today.
 *
 * Rate configuration (standard/reduced/zero) lives on the 'GB'
 * CountryTaxProfile row, not scattered AppSetting keys — keeps per-country
 * config in one place as more countries are added.
 */
class UkVatTaxStrategy implements TaxStrategy
{
    use BuildsGenericInvoiceSummary;

    private const DEFAULT_STANDARD_RATE = 20.0;

    public function countryCode(): string
    {
        return 'GB';
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

        $profile = $this->profile();
        // Reuses the same 'rate' config key the generic single-rate editor
        // writes — for the UK this IS the standard VAT rate (20% default).
        $standardRate = (float) ($profile?->configValue('rate') ?? self::DEFAULT_STANDARD_RATE);
        // UK consumer-facing prices are VAT-inclusive by law; not an admin
        // preference the way Generic's "tax fees too?" toggle is.
        $inclusive = true;
        $decimals = 2;

        $ids = collect($lines)->pluck('menu_item_id')->filter()->unique()->values()->all();
        $items = $ids ? MenuItem::whereKey($ids)->get()->keyBy('id') : collect();

        $lineRows = [];
        foreach ($lines as $line) {
            $mi = $items->get($line['menu_item_id'] ?? null);
            $rate = max(0.0, (float) ($line['vat_rate'] ?? $line['gst_rate'] ?? $mi?->gst_rate ?? $standardRate));
            $lineInclusive = array_key_exists('price_inclusive', $line) ? (bool) $line['price_inclusive'] : $inclusive;

            $gross = max(0.0, (float) ($line['line_total'] ?? 0));
            $taxable = ($lineInclusive && $rate > 0) ? $gross * 100 / (100 + $rate) : $gross;
            $vat = round($taxable * $rate / 100, $decimals);

            $lineRows[] = [
                'name' => (string) ($line['name'] ?? 'Item'),
                'qty' => (int) ($line['quantity'] ?? 1),
                'rate' => $rate,
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
            $vat = round($amt * $standardRate / 100, $decimals);
            $chargeRows[] = [
                'label' => $label,
                'rate' => $standardRate,
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

        // Inclusive lines already carry their VAT in the displayed price —
        // only exclusive lines/charges add anything on top of the bill.
        $vatAdded = round(collect($all)->where('inclusive', false)->sum('vat'), $decimals);
        $vatTotal = round(collect($all)->sum('vat'), $decimals);
        $taxableTotal = round(collect($all)->sum('taxable_value'), $decimals);

        return new TaxResult(
            tax: $vatAdded,
            taxRate: (float) collect($rateSummary)->max('rate'),
            taxLabel: 'VAT',
            taxBreakdown: $rateSummary,
            detailed: [
                'country_code' => 'GB',
                'tax_name' => 'VAT',
                'prices_inclusive' => $inclusive,
                'vat_number' => $restaurant?->vat_number,
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
            ['key' => 'vat_number', 'label' => 'VAT Registration Number', 'required' => true],
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
        return $this->genericInvoiceSummary($detailed, 'VAT', 'VAT Registration Number', 'vat_number', 'vat_total');
    }

    private function profile(): ?CountryTaxProfile
    {
        return CountryTaxProfile::where('country_code', 'GB')->first();
    }
}
