<?php

namespace App\Services\Tax\Strategies;

use App\Models\CountryTaxProfile;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\Tax\Contracts\TaxStrategy;
use App\Services\Tax\Strategies\Concerns\BuildsGenericInvoiceSummary;
use App\Services\Tax\TaxResult;

/**
 * Australia + Singapore — both call their consumption tax "GST" (like
 * India), but structurally it is a standard single-rate VAT: ONE national
 * rate (no state/regional variation the way US sales tax has, no dual
 * CGST/SGST-style split the way India's GST has), consumer prices GST-
 * INCLUSIVE by law (Australian Consumer Law; Singapore's GST Act), and
 * liability sits with the RESTAURANT'S OWN registration — not a marketplace-
 * facilitator rule the way US sales tax works, and not a Section-9(5)-style
 * platform override the way India's does. One class serves both countries
 * for the same reason GccVatTaxStrategy/EuVatTaxStrategy do: the mechanic is
 * genuinely identical, only the rate (AU 10%, SG 9%) and the registration-
 * number terminology (ABN vs GST Registration Number) differ.
 */
class AuSgGstTaxStrategy implements TaxStrategy
{
    use BuildsGenericInvoiceSummary;

    private const FALLBACK_RATES = [
        'AU' => 10.0,
        'SG' => 9.0,
    ];

    private const REGISTRATION_LABELS = [
        'AU' => 'ABN (Australian Business Number) / GST Registration',
        'SG' => 'GST Registration Number',
    ];

    public function countryCode(): string
    {
        return 'AU'; // representative default; actual market is read per-restaurant at calculate() time
    }

    public function taxName(): string
    {
        return 'GST';
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

        $code = strtoupper($restaurant->resolvedCountryCode() ?: 'AU');
        $profile = CountryTaxProfile::where('country_code', $code)->first();
        $rate = (float) ($profile?->configValue('rate') ?? self::FALLBACK_RATES[$code] ?? self::FALLBACK_RATES['AU']);
        // Consumer-facing prices are GST-inclusive by law in both AU and SG -- not an
        // admin preference, same principle as the UK/EU/GCC VAT strategies.
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
            $tax = round($taxable * $lineRate / 100, $decimals);

            $lineRows[] = [
                'name' => (string) ($line['name'] ?? 'Item'),
                'qty' => (int) ($line['quantity'] ?? 1),
                'rate' => $lineRate,
                'inclusive' => $lineInclusive,
                'gross' => round($gross, $decimals),
                'taxable_value' => round($taxable, $decimals),
                'gst' => $tax,
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
            $tax = round($amt * $rate / 100, $decimals);
            $chargeRows[] = [
                'label' => $label,
                'rate' => $rate,
                'inclusive' => false,
                'taxable_value' => $amt,
                'gst' => $tax,
            ];
        }

        $all = array_merge($lineRows, $chargeRows);
        $rateSummary = [];
        foreach ($all as $row) {
            $key = number_format((float) $row['rate'], 2, '.', '');
            $rateSummary[$key] ??= ['rate' => (float) $row['rate'], 'taxable_value' => 0.0, 'tax' => 0.0];
            $rateSummary[$key]['taxable_value'] = round($rateSummary[$key]['taxable_value'] + $row['taxable_value'], $decimals);
            $rateSummary[$key]['tax'] = round($rateSummary[$key]['tax'] + $row['gst'], $decimals);
        }
        ksort($rateSummary);
        $rateSummary = array_values($rateSummary);

        $gstAdded = round(collect($all)->where('inclusive', false)->sum('gst'), $decimals);
        $gstTotal = round(collect($all)->sum('gst'), $decimals);
        $taxableTotal = round(collect($all)->sum('taxable_value'), $decimals);

        return new TaxResult(
            tax: $gstAdded,
            taxRate: (float) collect($rateSummary)->max('rate'),
            taxLabel: 'GST',
            taxBreakdown: $rateSummary,
            detailed: [
                'country_code' => $code,
                'tax_name' => 'GST',
                'prices_inclusive' => $inclusive,
                'registration_number' => $restaurant->vat_number,
                'registration_label' => self::REGISTRATION_LABELS[$code] ?? 'GST Registration Number',
                'taxable_total' => $taxableTotal,
                'gst_total' => $gstTotal,
                'gst_added' => $gstAdded,
                'lines' => $lineRows,
                'charges' => $chargeRows,
                'rate_summary' => $rateSummary,
            ],
        );
    }

    public function registrationFields(): array
    {
        return [
            ['key' => 'vat_number', 'label' => 'GST Registration Number', 'required' => true],
        ];
    }

    public function complianceFeatures(): array
    {
        return ['gst_invoice'];
    }

    public function customSettingsRoute(): ?string
    {
        return null;
    }

    public function invoiceSummary(array $detailed): array
    {
        $label = $detailed['registration_label'] ?? self::REGISTRATION_LABELS[$detailed['country_code'] ?? ''] ?? 'GST Registration Number';

        return $this->genericInvoiceSummary($detailed, 'GST', $label, 'registration_number', 'gst_total');
    }
}
