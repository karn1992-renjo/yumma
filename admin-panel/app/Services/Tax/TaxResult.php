<?php

namespace App\Services\Tax;

/**
 * Normalized output of any country's TaxStrategy::calculate() — the shape
 * every caller (checkout, order API, POS, invoice) reads regardless of which
 * country actually computed it.
 *
 *  - `tax`/`taxRate`/`taxLabel`/`taxBreakdown` feed the existing pricing-
 *    summary keys the same way the old inline GstTaxService override did.
 *  - `detailed` is a free-form, country-specific structure persisted as-is
 *    on `orders.tax_breakdown` (and, for India, is byte-identical to the old
 *    GstBreakdown::toArray() shape so nothing downstream has to change).
 */
class TaxResult
{
    public function __construct(
        public readonly float $tax,
        public readonly float $taxRate,
        public readonly string $taxLabel,
        public readonly array $taxBreakdown,
        public readonly array $detailed,
    ) {
    }
}
