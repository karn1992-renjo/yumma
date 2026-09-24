<?php

namespace App\Services\Tax\Contracts;

use App\Models\Restaurant;
use App\Services\Tax\TaxResult;

/**
 * One country's complete tax/compliance module. `IndiaGstTaxStrategy` is the
 * reference implementation — GST + Section 9(5) + TCS/TDS + GSTR filings.
 * A new country gets its own class implementing this same contract; it does
 * NOT have to implement every method meaningfully (e.g. `complianceReports()`
 * can return [] for a country with no periodic-filing feature yet) but every
 * strategy must be able to calculate tax and describe its own registration
 * fields so the admin UI can render itself dynamically per country.
 */
interface TaxStrategy
{
    /** ISO 3166-1 alpha-2, e.g. 'IN', 'GB', 'US'. */
    public function countryCode(): string;

    /** Short tax name shown in UI/invoices, e.g. "GST", "VAT", "Sales Tax". */
    public function taxName(): string;

    /** Whether this strategy is actually configured+enabled for the given restaurant right now. */
    public function appliesTo(?Restaurant $restaurant): bool;

    /**
     * @param  array  $lines    menu_item_id?, name, quantity, line_total, ...country-specific optional keys
     * @param  array  $charges  delivery_fee, platform_fee, service_charge, packaging_charge
     */
    public function calculate(?Restaurant $restaurant, array $lines, array $charges = []): ?TaxResult;

    /**
     * Fields the admin/business settings UI should render for this country's
     * registration (e.g. GSTIN/PAN/TAN for India, "VAT Number" for the UK).
     * Each entry: ['key' => string, 'label' => string, 'required' => bool].
     *
     * @return array<int, array{key: string, label: string, required: bool}>
     */
    public function registrationFields(): array;

    /**
     * Compliance sub-features this country's module actually supports, used
     * by the admin UI to decide which toggles/sections to show. India returns
     * things like ['section_9_5','tcs','tds_194o','tds_194c','gstr_filings',
     * 'e_invoice']; a country with only basic tax calculation returns [].
     *
     * @return string[]
     */
    public function complianceFeatures(): array;

    /**
     * A named route for this country's OWN dedicated settings page, when its
     * configuration is too rich for the generic CountryTaxProfile rate editor
     * (India's GSTIN/PAN/TAN/Section-9(5)/TCS/TDS toggles need
     * admin.settings.taxation-setup). Return null when the country's
     * calculation config genuinely fits the generic single-rate editor (a
     * simple standard-VAT country like the UK does) — the admin UI then lets
     * the CountryTaxProfile row be edited inline instead of redirecting.
     */
    public function customSettingsRoute(): ?string;

    /**
     * Normalizes this strategy's own `detailed` breakdown shape (whatever
     * calculate() persisted — CGST/SGST for India, a single rate_summary for
     * everyone else) into the ONE generic shape InvoiceService's country-
     * agnostic invoice template actually renders. India is the one exception
     * in practice: InvoiceService keeps using its own pre-existing
     * tax_invoice/bill_of_supply templates for 'IN' orders unchanged, so this
     * method exists on IndiaGstTaxStrategy for interface completeness rather
     * than because anything currently calls it for India.
     *
     * @return array{tax_name: string, registration_label: string, registration_number: ?string,
     *               lines: array<int, array{rate: float, taxable_value: float, tax: float}>,
     *               total_tax: float, inclusive: bool, notes: string[]}
     */
    public function invoiceSummary(array $detailed): array;
}
