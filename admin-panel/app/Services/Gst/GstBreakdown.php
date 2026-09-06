<?php

namespace App\Services\Gst;

/**
 * Immutable result of a GST computation for one order.
 *
 *  - `taxAdded`         amount added on top of the item subtotal (drives the
 *                       order total; same role as the old TaxSetting `tax`).
 *  - `ecoFood*`         5% GST on restaurant food that the PLATFORM must remit
 *                       under Sec 9(5) (collected from the customer, never
 *                       reaches the restaurant).
 *  - `service*`         18% GST on the platform's own delivery / platform fees.
 *  - `restaurant*`      GST the RESTAURANT itself is liable for (only when
 *                       Sec 9(5) mode is off, or for non-restaurant-service
 *                       lines) -> stored on `orders.cgst_amount/sgst_amount`.
 */
class GstBreakdown
{
    public function __construct(
        public readonly float $taxableTotal,
        public readonly float $cgstTotal,
        public readonly float $sgstTotal,
        public readonly float $igstTotal,
        public readonly float $taxAdded,
        public readonly float $ecoFoodCgst,
        public readonly float $ecoFoodSgst,
        public readonly float $serviceCgst,
        public readonly float $serviceSgst,
        public readonly float $restaurantCgst,
        public readonly float $restaurantSgst,
        public readonly array $lines,
        public readonly array $charges,
        public readonly array $rateSummary,
        public readonly array $meta,
    ) {
    }

    public static function build(array $lines, array $charges, int $decimals, array $meta): self
    {
        $round = static fn ($v) => round((float) $v, $decimals);

        $summary = [];
        $accumulate = static function (array $row) use (&$summary) {
            $key = number_format((float) $row['rate'], 2, '.', '');
            $summary[$key] ??= ['rate' => (float) $row['rate'], 'taxable_value' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0];
            $summary[$key]['taxable_value'] += (float) $row['taxable_value'];
            $summary[$key]['cgst'] += (float) $row['cgst'];
            $summary[$key]['sgst'] += (float) $row['sgst'];
        };

        foreach ($lines as $l) {
            $accumulate($l);
        }
        foreach ($charges as $c) {
            $accumulate($c);
        }
        foreach ($summary as &$s) {
            $s['taxable_value'] = $round($s['taxable_value']);
            $s['cgst'] = $round($s['cgst']);
            $s['sgst'] = $round($s['sgst']);
        }
        unset($s);
        ksort($summary);

        $all = array_merge($lines, $charges);
        $byLiability = static fn (string $lia, string $col) => $round(
            collect($all)->where('liability', $lia)->sum(fn ($r) => (float) $r[$col])
        );

        $cgst = $round(array_sum(array_column($summary, 'cgst')));
        $sgst = $round(array_sum(array_column($summary, 'sgst')));
        $taxable = $round(array_sum(array_column($summary, 'taxable_value')));

        // Everything the customer actually pays on top (inclusive lines already
        // carry their tax in the price, so they add nothing).
        $taxAdded = $round(
            collect($all)->where('inclusive', false)->sum(fn ($r) => (float) $r['cgst'] + (float) $r['sgst'])
        );

        return new self(
            $taxable, $cgst, $sgst, 0.0, $taxAdded,
            $byLiability('eco', 'cgst'), $byLiability('eco', 'sgst'),
            $byLiability('platform', 'cgst'), $byLiability('platform', 'sgst'),
            $byLiability('restaurant', 'cgst'), $byLiability('restaurant', 'sgst'),
            array_values($lines), array_values($charges), array_values($summary), $meta,
        );
    }

    public function toArray(): array
    {
        return [
            'supply_type' => $this->meta['supply_type'] ?? 'intra',
            'section_9_5' => (bool) ($this->meta['section_9_5'] ?? false),
            'place_of_supply' => $this->meta['place_of_supply'] ?? null,
            'supplier_gstin' => $this->meta['supplier_gstin'] ?? null,
            'taxable_total' => $this->taxableTotal,
            'cgst_total' => $this->cgstTotal,
            'sgst_total' => $this->sgstTotal,
            'igst_total' => $this->igstTotal,
            'tax_added' => $this->taxAdded,
            'tax_total' => round($this->cgstTotal + $this->sgstTotal + $this->igstTotal, 2),
            'eco_food' => ['cgst' => $this->ecoFoodCgst, 'sgst' => $this->ecoFoodSgst],
            'service' => ['cgst' => $this->serviceCgst, 'sgst' => $this->serviceSgst],
            'restaurant' => ['cgst' => $this->restaurantCgst, 'sgst' => $this->restaurantSgst],
            'lines' => $this->lines,
            'charges' => $this->charges,
            'rate_summary' => $this->rateSummary,
        ];
    }

    /**
     * Merge into an `Order::create([...])` attribute array. `tax` stays the
     * "amount added to the bill" so total math is unchanged.
     * `cgst_amount/sgst_amount` carry only the restaurant-own liability.
     */
    public function applyToOrderAttributes(array &$attrs): void
    {
        $attrs['tax'] = $this->taxAdded;
        $attrs['tax_breakdown'] = $this->toArray();
        $attrs['cgst_amount'] = $this->restaurantCgst;
        $attrs['sgst_amount'] = $this->restaurantSgst;
        $attrs['igst_amount'] = $this->igstTotal;
        $attrs['eco_gst_food_cgst'] = $this->ecoFoodCgst;
        $attrs['eco_gst_food_sgst'] = $this->ecoFoodSgst;
        $attrs['eco_gst_food'] = round($this->ecoFoodCgst + $this->ecoFoodSgst, 2);
        $attrs['service_gst_cgst'] = $this->serviceCgst;
        $attrs['service_gst_sgst'] = $this->serviceSgst;
        $attrs['service_gst'] = round($this->serviceCgst + $this->serviceSgst, 2);
        $attrs['place_of_supply'] = $this->meta['place_of_supply'] ?? null;
        $attrs['supplier_gstin'] = $this->meta['supplier_gstin'] ?? null;
        $attrs['invoice_type'] = $this->meta['invoice_type'] ?? 'tax_invoice';
    }
}
