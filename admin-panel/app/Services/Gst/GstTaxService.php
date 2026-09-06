<?php

namespace App\Services\Gst;

use App\Models\AppSetting;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\Tax\TaxConfig;

/**
 * Per-order GST computation, gated by `business_gst_enabled`.
 *
 *  - Sec 9(5) mode ON  (default): the platform (ECO) is liable for GST on the
 *    restaurant food -> food lines get the ECO food rate, `liability => 'eco'`;
 *    this applies whether or not the restaurant is GST-registered.
 *  - Sec 9(5) mode OFF: only a GST-registered restaurant charges its own GST
 *    on food, `liability => 'restaurant'` (the original behaviour).
 *  - Delivery / platform fees are always the platform's own supply,
 *    `liability => 'platform'`, at the service rate.
 *
 * `computeForOrder()` returns null (⇒ callers keep the TaxSetting path) only
 * when GST is off, or 9(5) is off AND the restaurant is not registered.
 */
class GstTaxService
{
    public function __construct(private readonly TaxConfig $config = new TaxConfig())
    {
    }

    public function enabled(): bool
    {
        return $this->config->gstEnabled();
    }

    public function appliesTo(?Restaurant $restaurant): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        if ($this->config->section95Mode()) {
            return true; // ECO is liable regardless of restaurant registration
        }

        return $restaurant instanceof Restaurant && (bool) $restaurant->is_gst_registered;
    }

    public function defaultRate(): float
    {
        return max(0.0, (float) AppSetting::getValue('business_default_gst_rate', 5));
    }

    public function defaultHsn(): string
    {
        return (string) (AppSetting::getValue('business_default_hsn') ?: '996331');
    }

    /**
     * @param  array  $lines    menu_item_id?, name, quantity, line_total, gst_rate?, hsn_code?, price_inclusive?
     * @param  array  $charges  delivery_fee, platform_fee, service_charge, packaging_charge
     */
    public function computeForOrder(?Restaurant $restaurant, array $lines, array $charges = []): ?GstBreakdown
    {
        if (! $this->appliesTo($restaurant)) {
            return null;
        }

        $decimals = AppSetting::currencyDecimals();
        $section95 = $this->config->section95Mode();
        $ecoFoodRate = $this->config->ecoFoodRate();
        $serviceRate = $this->config->serviceRate();
        $defaultRate = $this->defaultRate();
        $defaultHsn = $this->defaultHsn();
        $foodHsn = $section95 ? '9963' : $defaultHsn;

        $ids = collect($lines)->pluck('menu_item_id')->filter()->unique()->values()->all();
        $items = $ids
            ? MenuItem::with('masterMenuItem:id,gst,hsn_code')->whereKey($ids)->get()->keyBy('id')
            : collect();

        $lineRows = [];
        foreach ($lines as $line) {
            $mi = $items->get($line['menu_item_id'] ?? null);

            // A line can opt out of "restaurant service" (packaged goods etc.);
            // default = it IS a restaurant service.
            $isRestaurantService = ! array_key_exists('is_restaurant_service', $line) || (bool) $line['is_restaurant_service'];

            if ($section95 && $isRestaurantService) {
                $rate = $ecoFoodRate;
                $liability = 'eco';
                $hsn = $foodHsn;
            } else {
                $rate = max(0.0, (float) ($line['gst_rate'] ?? $mi?->gst_rate ?? $mi?->masterMenuItem?->gst ?? $defaultRate));
                $liability = 'restaurant';
                $hsn = (string) ($line['hsn_code'] ?? $mi?->hsn_code ?? $mi?->masterMenuItem?->hsn_code ?? $defaultHsn);
            }

            $inclusive = array_key_exists('price_inclusive', $line)
                ? (bool) $line['price_inclusive']
                : (bool) ($mi?->is_price_inclusive_gst);

            $gross = max(0.0, (float) ($line['line_total'] ?? 0));
            $taxable = ($inclusive && $rate > 0) ? $gross * 100 / (100 + $rate) : $gross;
            $tax = $taxable * $rate / 100;

            $lineRows[] = [
                'name' => (string) ($line['name'] ?? 'Item'),
                'hsn' => $hsn,
                'qty' => (int) ($line['quantity'] ?? 1),
                'rate' => $rate,
                'liability' => $liability,
                'inclusive' => $inclusive,
                'gross' => round($gross, $decimals),
                'taxable_value' => round($taxable, $decimals),
                'cgst' => round($tax / 2, $decimals),
                'sgst' => round($tax / 2, $decimals),
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
            $tax = $amt * $serviceRate / 100;
            $chargeRows[] = [
                'label' => $label,
                'hsn' => $key === 'delivery_fee' ? '9968' : '9985',
                'rate' => $serviceRate,
                'liability' => 'platform',
                'inclusive' => false,
                'taxable_value' => $amt,
                'cgst' => round($tax / 2, $decimals),
                'sgst' => round($tax / 2, $decimals),
            ];
        }

        $stateCode = trim((string) ($restaurant?->state_code ?? ''));
        $place = trim(($stateCode !== '' ? $stateCode . '-' : '') . (string) ($restaurant?->state ?? ''));

        return GstBreakdown::build($lineRows, $chargeRows, $decimals, [
            'supply_type' => 'intra',
            'section_9_5' => $section95,
            'place_of_supply' => $place !== '' ? $place : null,
            'supplier_gstin' => $restaurant?->gstin ?: null,
            'invoice_type' => 'tax_invoice',
        ]);
    }

    public function linesFromRequestItems(array $items): array
    {
        return collect($items)->map(function ($item) {
            $qty = max(1, (int) ($item['quantity'] ?? $item['qty'] ?? 1));
            $dealPrice = isset($item['promotion_deal_price']) ? (float) $item['promotion_deal_price'] : null;
            $unit = $dealPrice !== null && $dealPrice > 0 ? $dealPrice : (float) ($item['price'] ?? 0);

            return [
                'menu_item_id' => $item['id'] ?? $item['menu_item_id'] ?? null,
                'name' => $item['name'] ?? null,
                'quantity' => $qty,
                'line_total' => round($unit * $qty, AppSetting::currencyDecimals()),
            ];
        })->all();
    }
}
