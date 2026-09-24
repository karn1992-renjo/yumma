<?php

namespace App\Services\Tax;

use App\Models\AppSetting;
use App\Models\Restaurant;
use App\Models\TaxSetting;

/**
 * Single entry point for computing order tax, replacing the two copies of
 * this exact same "flat TaxSetting baseline, then let GST override it" block
 * that used to live inline in CheckoutController and Api\OrderController.
 *
 * Behavior is unchanged for India: the baseline flat-tax calculation runs
 * first (same as before), then the resolved country strategy is asked to
 * override it (GstTaxService did exactly this before — it's now just reached
 * through IndiaGstTaxStrategy instead of being called directly).
 */
class TaxCalculationService
{
    public function __construct(private readonly CountryTaxRegistry $registry = new CountryTaxRegistry())
    {
    }

    /**
     * The flat, country-neutral baseline — unchanged from what both
     * controllers used to compute inline before any country-strategy concept
     * existed. Callers use this first (it doesn't need the discount amount),
     * then call `override()` once the discount is known, exactly matching
     * the two-step order the original inline code already had to follow.
     */
    public function baseline(float $subtotal, float $deliveryFee): array
    {
        $activeTaxes = TaxSetting::getActiveTaxes();
        if ($activeTaxes->isEmpty()) {
            return ['tax' => 0.0, 'tax_rate' => 0.0, 'tax_label' => 'Taxes & charges', 'tax_breakdown' => []];
        }

        $taxBreakdown = TaxSetting::calculateBreakdown($subtotal, $deliveryFee);

        return [
            'tax' => round((float) collect($taxBreakdown)->sum('amount'), 2),
            'tax_rate' => (float) $activeTaxes->sum('rate'),
            'tax_label' => 'Taxes & charges',
            'tax_breakdown' => $taxBreakdown,
        ];
    }

    /**
     * The country-strategy override — replaces the old inline
     * "$gstService->appliesTo()... computeForOrder()..." block. Returns null
     * when no strategy applies, in which case the caller keeps whatever
     * `baseline()` (or its own already-adjusted flat calc) produced, exactly
     * as the old code kept the TaxSetting result when GST didn't apply.
     *
     * @param  array  $lines    the same shape GstTaxService::computeForOrder() / linesFromRequestItems() expects
     * @param  array  $charges  delivery_fee, platform_fee, service_charge, packaging_charge
     */
    public function override(?Restaurant $restaurant, array $lines, array $charges = []): ?TaxResult
    {
        $strategy = $this->registry->resolveFor($restaurant);
        if (! $strategy || ! $strategy->appliesTo($restaurant)) {
            return null;
        }

        return $strategy->calculate($restaurant, $lines, $charges);
    }

    public function resolveCountryCode(?Restaurant $restaurant): string
    {
        return $this->registry->resolveCountryCode($restaurant);
    }
}
