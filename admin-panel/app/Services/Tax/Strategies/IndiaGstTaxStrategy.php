<?php

namespace App\Services\Tax\Strategies;

use App\Models\Restaurant;
use App\Services\Gst\GstTaxService;
use App\Services\Tax\Contracts\TaxStrategy;
use App\Services\Tax\Strategies\Concerns\BuildsGenericInvoiceSummary;
use App\Services\Tax\TaxConfig;
use App\Services\Tax\TaxResult;

/**
 * Wraps the existing, already-complete India GST engine (GstTaxService +
 * TaxConfig, with Section 9(5), TCS Sec 52 and TDS 194-O/194-C handled by
 * TcsService/TdsService downstream of the persisted breakdown) behind the
 * TaxStrategy contract. Deliberately does NOT reimplement anything — every
 * call here delegates straight to the pre-existing service so India's
 * behavior is byte-for-byte unchanged by this refactor.
 */
class IndiaGstTaxStrategy implements TaxStrategy
{
    use BuildsGenericInvoiceSummary;

    public function __construct(
        private readonly GstTaxService $gst = new GstTaxService(),
        private readonly TaxConfig $config = new TaxConfig(),
    ) {
    }

    public function countryCode(): string
    {
        return 'IN';
    }

    public function taxName(): string
    {
        return 'GST';
    }

    public function appliesTo(?Restaurant $restaurant): bool
    {
        return $this->gst->appliesTo($restaurant);
    }

    public function calculate(?Restaurant $restaurant, array $lines, array $charges = []): ?TaxResult
    {
        $breakdown = $this->gst->computeForOrder($restaurant, $lines, $charges);
        if (! $breakdown) {
            return null;
        }

        return new TaxResult(
            tax: $breakdown->taxAdded,
            taxRate: (float) collect($breakdown->rateSummary)->max('rate'),
            taxLabel: 'GST',
            taxBreakdown: $breakdown->rateSummary,
            detailed: $breakdown->toArray(),
        );
    }

    public function registrationFields(): array
    {
        return [
            ['key' => 'business_gstin', 'label' => 'GSTIN', 'required' => true],
            ['key' => 'business_pan', 'label' => 'PAN', 'required' => false],
            ['key' => 'business_tan', 'label' => 'TAN (for TDS)', 'required' => false],
            ['key' => 'business_state_code', 'label' => 'GST State Code', 'required' => true],
        ];
    }

    public function complianceFeatures(): array
    {
        $features = ['section_9_5'];
        if ($this->config->tcsEnabled()) {
            $features[] = 'tcs';
        }
        if ($this->config->tds194oEnabled()) {
            $features[] = 'tds_194o';
        }
        if ($this->config->tds194cEnabled()) {
            $features[] = 'tds_194c';
        }
        $features[] = 'gstr_filings';
        $features[] = 'e_invoice';

        return $features;
    }

    public function customSettingsRoute(): ?string
    {
        return 'admin.settings.taxation-setup';
    }

    /**
     * Interface completeness only -- InvoiceService keeps using its own
     * pre-existing tax_invoice/bill_of_supply CGST/SGST templates for 'IN'
     * orders unchanged; nothing currently calls this for India.
     */
    public function invoiceSummary(array $detailed): array
    {
        return $this->genericInvoiceSummary($detailed, 'GST', 'GSTIN', 'supplier_gstin', 'tax_added');
    }
}
