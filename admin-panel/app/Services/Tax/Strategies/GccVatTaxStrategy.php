<?php

namespace App\Services\Tax\Strategies;

use App\Models\CountryTaxProfile;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\Tax\Contracts\TaxStrategy;
use App\Services\Tax\Strategies\Concerns\BuildsGenericInvoiceSummary;
use App\Services\Tax\TaxResult;

/**
 * GCC unified VAT framework — UAE, Saudi Arabia, Bahrain, Oman. One class
 * serves all four deliberately: they share the same VAT mechanics (single
 * rate, no CGST/SGST-style split, VAT-inclusive consumer pricing required by
 * law — UAE Federal Decree-Law No. 8/2017 Art. 33 and the equivalent KSA/
 * Bahrain/Oman executive regulations all mandate it) — only the rate and the
 * registration-number format actually differ per member state, which is
 * exactly what a "unified framework" means. Registered once per member state
 * in CountryTaxRegistry::STRATEGY_MAP; at calculation time it reads the
 * RESTAURANT's own resolved country to pick the right member state's rate
 * from that state's own CountryTaxProfile row, the same way a country code
 * would be threaded through if this were four near-identical classes.
 *
 * Qatar and Kuwait are deliberately NOT included — as of this build neither
 * has implemented VAT, so there is nothing to model; add them here (and to
 * the registry) if/when they do.
 */
class GccVatTaxStrategy implements TaxStrategy
{
    use BuildsGenericInvoiceSummary;

    private const FALLBACK_RATES = [
        'AE' => 5.0,
        'SA' => 15.0,
        'BH' => 10.0,
        'OM' => 5.0,
    ];

    private const TRN_LABELS = [
        'AE' => 'Tax Registration Number (TRN)',
        'SA' => 'VAT Registration Number (15 digits)',
        'BH' => 'VAT Account Number',
        'OM' => 'VAT Registration Number',
    ];

    public function countryCode(): string
    {
        return 'AE'; // representative default; actual market is read per-restaurant at calculate() time
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

        $code = strtoupper($restaurant->resolvedCountryCode() ?: 'AE');
        $profile = CountryTaxProfile::where('country_code', $code)->first();
        $rate = (float) ($profile?->configValue('rate') ?? self::FALLBACK_RATES[$code] ?? self::FALLBACK_RATES['AE']);
        // Consumer-facing prices are VAT-inclusive by law across the GCC framework,
        // same as the UK — not an admin preference.
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
                'trn' => $restaurant->vat_number,
                'trn_label' => self::TRN_LABELS[$code] ?? 'Tax Registration Number',
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
            ['key' => 'vat_number', 'label' => 'Tax Registration Number (TRN)', 'required' => true],
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
        $label = $detailed['trn_label'] ?? self::TRN_LABELS[$detailed['country_code'] ?? ''] ?? 'Tax Registration Number';

        return $this->genericInvoiceSummary($detailed, 'VAT', $label, 'trn', 'vat_total');
    }
}
