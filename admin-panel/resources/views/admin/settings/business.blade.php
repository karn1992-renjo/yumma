@extends('layouts.admin')

@section('title', 'Business Settings')
@section('header', 'Business Settings')

@section('content')
@include('admin.settings._style')

@php
    $gstOn = (string) ($settings['business_gst_enabled'] ?? '0') === '1';
    $einvoiceOn = (string) ($settings['einvoice_enabled'] ?? '0') === '1';
    $sigUrl = !empty($settings['invoice_signature_image']) ? \App\Services\MediaStorage::url($settings['invoice_signature_image']) : null;
@endphp

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-building"></i> Legal Entity</span>
            <h1>Business Settings</h1>
            <p>Company identity, invoice presentation, and the GST workflow. These values appear on customer invoices and GST reports.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Company &amp; Invoicing</h2>
                <p class="settings-card-subtitle">Used on the order invoice PDF emailed to customers and on GST filings.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.business">

                <div class="settings-section-title">Business Identity</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Legal Name</label>
                        <input type="text" name="business_legal_name" class="form-control" value="{{ $settings['business_legal_name'] ?? ($settings['invoice_company_name'] ?? $settings['app_name'] ?? $settings['site_name'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Trade Name (as displayed)</label>
                        <input type="text" name="business_trade_name" class="form-control" value="{{ $settings['business_trade_name'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">GSTIN</label>
                        <input type="text" name="business_gstin" class="form-control" maxlength="20" value="{{ $settings['business_gstin'] ?? ($settings['invoice_company_tax_id'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">PAN</label>
                        <input type="text" name="business_pan" class="form-control" maxlength="15" value="{{ $settings['business_pan'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">CIN / Registration No</label>
                        <input type="text" name="business_cin" class="form-control" value="{{ $settings['business_cin'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Registered Address</label>
                        <textarea name="business_reg_address" class="form-control" rows="2">{{ $settings['business_reg_address'] ?? ($settings['invoice_company_address'] ?? '') }}</textarea>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">City</label>
                        <input type="text" name="business_city" class="form-control" value="{{ $settings['business_city'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">State</label>
                        <input type="text" name="business_state" class="form-control" value="{{ $settings['business_state'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">GST State Code</label>
                        <input type="text" name="business_state_code" class="form-control" maxlength="2" placeholder="e.g. 27" value="{{ $settings['business_state_code'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">PIN Code</label>
                        <input type="text" name="business_pincode" class="form-control" value="{{ $settings['business_pincode'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Country</label>
                        <input type="text" name="business_country" class="form-control" value="{{ $settings['business_country'] ?? 'India' }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Contact</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Business Email</label>
                        <input type="email" name="business_email" class="form-control" value="{{ $settings['business_email'] ?? ($settings['contact_email'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Business Phone</label>
                        <input type="text" name="business_phone" class="form-control" value="{{ $settings['business_phone'] ?? ($settings['contact_phone'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Support Email</label>
                        <input type="email" name="support_email" class="form-control" value="{{ $settings['support_email'] ?? ($settings['contact_email'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Website</label>
                        <input type="text" name="business_website" class="form-control" placeholder="https://" value="{{ $settings['business_website'] ?? ($settings['invoice_company_website'] ?? '') }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Invoice Presentation</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Invoice Number Prefix</label>
                        <input type="text" name="invoice_number_prefix" class="form-control" maxlength="16" placeholder="INV" value="{{ $settings['invoice_number_prefix'] ?? 'INV' }}">
                    </div>
                    <div class="settings-field settings-span-5">
                        <label class="form-label">Authorised Signatory</label>
                        <input type="text" name="invoice_authorised_signatory" class="form-control" value="{{ $settings['invoice_authorised_signatory'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Signature Image (PNG)</label>
                        <input type="file" name="invoice_signature_image" class="form-control" accept="image/png,image/jpeg">
                        @if($sigUrl)<div class="small text-muted mt-1"><img src="{{ $sigUrl }}" alt="signature" style="height:34px;"></div>@endif
                    </div>
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Declaration</label>
                        <input type="text" name="invoice_declaration" class="form-control" maxlength="500" placeholder="e.g. We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct." value="{{ $settings['invoice_declaration'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-12">
                        <label class="form-label">Terms &amp; Footer Note</label>
                        <textarea name="invoice_terms" class="form-control" rows="2">{{ $settings['invoice_terms'] ?? ($settings['invoice_footer_note'] ?? '') }}</textarea>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Bank Name</label>
                        <input type="text" name="invoice_bank_name" class="form-control" value="{{ $settings['invoice_bank_name'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Bank Account No</label>
                        <input type="text" name="invoice_bank_account" class="form-control" value="{{ $settings['invoice_bank_account'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">IFSC</label>
                        <input type="text" name="invoice_bank_ifsc" class="form-control" value="{{ $settings['invoice_bank_ifsc'] ?? '' }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Entity &amp; Compliance Profile</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Entity Type</label>
                        <select name="business_entity_type" class="form-select">
                            @php $et = old('business_entity_type', $settings['business_entity_type'] ?? 'pvt_ltd'); @endphp
                            <option value="pvt_ltd" @selected($et === 'pvt_ltd')>Private Limited Company</option>
                            <option value="opc" @selected($et === 'opc')>One Person Company (OPC)</option>
                            <option value="llp" @selected($et === 'llp')>LLP</option>
                            <option value="partnership" @selected($et === 'partnership')>Partnership Firm</option>
                            <option value="proprietorship" @selected($et === 'proprietorship')>Proprietorship</option>
                        </select>
                        <div class="small text-muted mt-1">Drives the compliance register (ROC / LLP filings, audit, etc.).</div>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Annual Turnover Band</label>
                        <select name="business_turnover_band" class="form-select">
                            @php $tb = old('business_turnover_band', $settings['business_turnover_band'] ?? 'below_1cr'); @endphp
                            <option value="below_1cr" @selected($tb === 'below_1cr')>Below ₹1 Cr</option>
                            <option value="1cr_5cr" @selected($tb === '1cr_5cr')>₹1 Cr – ₹5 Cr</option>
                            <option value="5cr_10cr" @selected($tb === '5cr_10cr')>₹5 Cr – ₹10 Cr</option>
                            <option value="above_10cr" @selected($tb === 'above_10cr')>Above ₹10 Cr</option>
                        </select>
                        <div class="small text-muted mt-1">Used to flag tax audit (44AB), GSTR-9C, e-invoicing applicability.</div>
                    </div>
                    <div class="settings-field settings-span-4">
                        <input type="hidden" name="business_has_employees" value="0">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="business_has_employees" name="business_has_employees" value="1" @checked((string) ($settings['business_has_employees'] ?? '0') === '1')>
                            <label class="form-check-label fw-semibold" for="business_has_employees">Has employees on payroll</label>
                        </div>
                        <div class="small text-muted mt-1">Turns on PF / ESIC / Professional Tax items in the compliance register.</div>
                    </div>
                </div>

                <div class="settings-section-title mt-4">GST Workflow</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="business_gst_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="business_gst_enabled" name="business_gst_enabled" value="1" @checked($gstOn)>
                            <label class="form-check-label fw-semibold" for="business_gst_enabled">
                                Enable GST invoicing — issue tax invoices with CGST/SGST split, HSN, and rate-wise summary
                            </label>
                        </div>
                        <div class="small text-muted mt-1">
                            When off, orders use the existing Tax Settings and a simple receipt. When on, only GST-registered restaurants issue tax invoices; others get a Bill of Supply.
                        </div>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Default GST Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="28" name="business_default_gst_rate" class="form-control" value="{{ $settings['business_default_gst_rate'] ?? '5' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Default HSN / SAC</label>
                        <input type="text" name="business_default_hsn" class="form-control" placeholder="e.g. 996331" value="{{ $settings['business_default_hsn'] ?? '996331' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Place of Supply</label>
                        <select name="business_gst_supply_type" class="form-select">
                            <option value="intra" @selected(($settings['business_gst_supply_type'] ?? 'intra') === 'intra')>Intra-state (CGST + SGST)</option>
                            <option value="inter" @selected(($settings['business_gst_supply_type'] ?? 'intra') === 'inter')>Inter-state (IGST)</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Rounding</label>
                        <select name="business_gst_rounding" class="form-select">
                            <option value="line" @selected(($settings['business_gst_rounding'] ?? 'line') === 'line')>Per line</option>
                            <option value="invoice" @selected(($settings['business_gst_rounding'] ?? 'line') === 'invoice')>Per invoice</option>
                        </select>
                    </div>
                </div>

                <div class="settings-section-title mt-4">Marketplace GST — Sec 9(5) &amp; TCS</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="gst_9_5_mode" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="gst_9_5_mode" name="gst_9_5_mode" value="1" @checked((string) ($settings['gst_9_5_mode'] ?? '1') === '1')>
                            <label class="form-check-label fw-semibold" for="gst_9_5_mode">Platform pays GST on restaurant food (Sec 9(5) ECO)</label>
                        </div>
                        <div class="small text-muted mt-1">On: the platform collects the food GST from the customer and remits it — the restaurant is paid only the food value and does not report it. Off: the GST-registered restaurant charges its own GST.</div>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Food GST Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="28" name="gst_eco_food_rate" class="form-control" value="{{ $settings['gst_eco_food_rate'] ?? '5' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Service GST Rate (%) <span class="text-muted small">— delivery / platform fee</span></label>
                        <input type="number" step="0.01" min="0" max="28" name="gst_service_rate" class="form-control" value="{{ $settings['gst_service_rate'] ?? '18' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <div class="form-check form-switch mt-4">
                            <input type="hidden" name="gst_tcs_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="gst_tcs_enabled" name="gst_tcs_enabled" value="1" @checked((string) ($settings['gst_tcs_enabled'] ?? '0') === '1')>
                            <label class="form-check-label fw-semibold" for="gst_tcs_enabled">Collect GST TCS (Sec 52)</label>
                        </div>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">TCS Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="5" name="gst_tcs_rate" class="form-control" value="{{ $settings['gst_tcs_rate'] ?? '0.5' }}">
                    </div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="gst_tcs_registered" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="gst_tcs_registered" name="gst_tcs_registered" value="1" @checked((string) ($settings['gst_tcs_registered'] ?? '0') === '1')>
                            <label class="form-check-label" for="gst_tcs_registered">We are registered as a TCS collector (Sec 52) with a valid GSTIN</label>
                        </div>
                        <div class="small text-muted mt-1">TCS collection stays off until this is confirmed <em>and</em> a valid GSTIN is on file.</div>
                    </div>
                </div>

                <div class="settings-section-title mt-4">Income-Tax TDS &amp; TAN</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">TAN</label>
                        <input type="text" name="business_tan" class="form-control" maxlength="15" placeholder="Required to deduct TDS" value="{{ $settings['business_tan'] ?? '' }}">
                        <div class="small text-muted mt-1">Mandatory before enabling either TDS section below. Not needed for GST TCS.</div>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Financial Year Starts</label>
                        <select name="tax_financial_year_start_month" class="form-select">
                            @foreach(['1'=>'January','4'=>'April','7'=>'July','10'=>'October'] as $mn => $ml)
                                <option value="{{ $mn }}" @selected((string) ($settings['tax_financial_year_start_month'] ?? '4') === $mn)>{{ $ml }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="settings-field settings-span-12"><hr class="my-1"></div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="tds_194o_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="tds_194o_enabled" name="tds_194o_enabled" value="1" @checked((string) ($settings['tds_194o_enabled'] ?? '0') === '1')>
                            <label class="form-check-label fw-semibold" for="tds_194o_enabled">Sec 194-O — deduct TDS on restaurant sales</label>
                        </div>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">194-O Rate (%)</label>
                        <input type="number" step="0.001" min="0" max="5" name="tds_194o_rate" class="form-control" value="{{ $settings['tds_194o_rate'] ?? '0.1' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">No-PAN Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="20" name="tds_194o_nopan_rate" class="form-control" value="{{ $settings['tds_194o_nopan_rate'] ?? '5' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">FY Exemption Threshold (₹)</label>
                        <input type="number" step="1" min="0" name="tds_194o_threshold" class="form-control" value="{{ $settings['tds_194o_threshold'] ?? '500000' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Deduct</label>
                        <select name="tds_194o_after_threshold_only" class="form-select">
                            <option value="1" @selected((string) ($settings['tds_194o_after_threshold_only'] ?? '1') === '1')>On amount above threshold</option>
                            <option value="0" @selected((string) ($settings['tds_194o_after_threshold_only'] ?? '1') === '0')>On whole amount once crossed</option>
                        </select>
                    </div>

                    <div class="settings-field settings-span-12"><hr class="my-1"></div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="tds_194c_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="tds_194c_enabled" name="tds_194c_enabled" value="1" @checked((string) ($settings['tds_194c_enabled'] ?? '0') === '1')>
                            <label class="form-check-label fw-semibold" for="tds_194c_enabled">Sec 194-C — deduct TDS on driver payouts</label>
                        </div>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">194-C Rate — Individual (%)</label>
                        <input type="number" step="0.01" min="0" max="10" name="tds_194c_rate_individual" class="form-control" value="{{ $settings['tds_194c_rate_individual'] ?? '1' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">194-C Rate — Other (%)</label>
                        <input type="number" step="0.01" min="0" max="10" name="tds_194c_rate_other" class="form-control" value="{{ $settings['tds_194c_rate_other'] ?? '2' }}">
                    </div>
                    <div class="settings-field settings-span-2">
                        <label class="form-label">No-PAN Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="20" name="tds_194c_nopan_rate" class="form-control" value="{{ $settings['tds_194c_nopan_rate'] ?? '20' }}">
                    </div>
                    <div class="settings-field settings-span-2">
                        <label class="form-label">Single-txn Threshold (₹)</label>
                        <input type="number" step="1" min="0" name="tds_194c_threshold_single" class="form-control" value="{{ $settings['tds_194c_threshold_single'] ?? '30000' }}">
                    </div>
                    <div class="settings-field settings-span-2">
                        <label class="form-label">Annual Threshold (₹)</label>
                        <input type="number" step="1" min="0" name="tds_194c_threshold_annual" class="form-control" value="{{ $settings['tds_194c_threshold_annual'] ?? '100000' }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Gig-Worker Welfare Cess</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="gig_welfare_cess_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="gig_welfare_cess_enabled" name="gig_welfare_cess_enabled" value="1" @checked((string) ($settings['gig_welfare_cess_enabled'] ?? '0') === '1')>
                            <label class="form-check-label fw-semibold" for="gig_welfare_cess_enabled">Accrue gig-worker welfare cess per delivered order</label>
                        </div>
                        <div class="small text-muted mt-1">Where a state gig-workers welfare act / Social Security Code levy applies. Accrued to a payable and shown on the Cess tab for remittance.</div>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Cess Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="10" name="gig_welfare_cess_rate" class="form-control" value="{{ $settings['gig_welfare_cess_rate'] ?? '1' }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Applied On</label>
                        <select name="gig_welfare_cess_base" class="form-select">
                            @php $cb = $settings['gig_welfare_cess_base'] ?? 'order_value'; @endphp
                            <option value="order_value" @selected($cb === 'order_value')>Order value</option>
                            <option value="driver_payout" @selected($cb === 'driver_payout')>Driver payout</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Borne By</label>
                        <select name="gig_cess_borne_by" class="form-select">
                            @php $bb = $settings['gig_cess_borne_by'] ?? 'platform'; @endphp
                            <option value="platform" @selected($bb === 'platform')>Platform (expense)</option>
                            <option value="driver" @selected($bb === 'driver')>Driver (settlement deduction)</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">State</label>
                        <input type="text" name="gig_welfare_cess_state" class="form-control" placeholder="e.g. Karnataka" value="{{ $settings['gig_welfare_cess_state'] ?? '' }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Double-Entry Ledger</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="accounting_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="accounting_enabled" name="accounting_enabled" value="1" @checked((string) ($settings['accounting_enabled'] ?? '0') === '1')>
                            <label class="form-check-label fw-semibold" for="accounting_enabled">Enable the general ledger — auto-post journals from orders, payouts, taxes &amp; refunds</label>
                        </div>
                        <div class="small text-muted mt-1">
                            Powers the Balance Sheet, Profit &amp; Loss, Cash Flow and Trial Balance under <strong>Business Accounting</strong>.
                            When off, nothing is posted and every existing figure is unchanged. After enabling, run
                            <code>php artisan ledger:reconcile</code> once to backfill historical entries.
                        </div>
                    </div>
                </div>

                <div class="settings-section-title mt-4">E-Invoicing (IRN / QR)</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="einvoice_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="einvoice_enabled" name="einvoice_enabled" value="1" @checked($einvoiceOn)>
                            <label class="form-check-label fw-semibold" for="einvoice_enabled">Enable e-invoice capture on orders</label>
                        </div>
                        <div class="small text-muted mt-1">
                            <strong>Manual</strong> mode is ready to use — paste the IRN and signed QR from the government portal per order.
                            Automated GSP submission requires your provider's base URL and credentials and a vendor-specific integration.
                        </div>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Provider</label>
                        <select name="einvoice_provider" class="form-select">
                            <option value="manual" @selected(($settings['einvoice_provider'] ?? 'manual') === 'manual')>Manual entry</option>
                            <option value="gsp" @selected(($settings['einvoice_provider'] ?? 'manual') === 'gsp')>GSP API (advanced)</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">E-invoice GSTIN</label>
                        <input type="text" name="einvoice_gstin" class="form-control" value="{{ $settings['einvoice_gstin'] ?? ($settings['business_gstin'] ?? '') }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">GSP API Base URL</label>
                        <input type="text" name="einvoice_api_base" class="form-control" placeholder="https://..." value="{{ $settings['einvoice_api_base'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">GSP Username / Client ID</label>
                        <input type="text" name="einvoice_username" class="form-control" value="{{ $settings['einvoice_username'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">GSP Password / Secret</label>
                        <input type="password" name="einvoice_password" class="form-control" placeholder="Leave blank to keep the saved secret">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save Business Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
