@extends('layouts.admin')

@section('title', 'Taxation Setup')
@section('header', 'Taxation Setup')

@section('content')
@include('admin.settings._style')

@php
    /** @var \App\Services\Tax\TaxConfig $config */
    $s = $settings;
    $v = fn ($k, $d = '') => $s[$k] ?? $d;
@endphp

<style>
    .wiz-steps { display:flex; gap:8px; flex-wrap:wrap; margin:18px 0 24px; }
    .wiz-steps button { border:1px solid var(--settings-border,#dcdcdc); background:#fff; border-radius:999px; padding:6px 14px; font-size:12px; font-weight:600; color:#666; cursor:pointer; }
    .wiz-steps button.active { background:#1a7f37; border-color:#1a7f37; color:#fff; }
    .wiz-steps button.done { border-color:#1a7f37; color:#1a7f37; }
    .wiz-panel { display:none; }
    .wiz-panel.active { display:block; }
    .wiz-nav { display:flex; justify-content:space-between; margin-top:22px; }
    .wiz-review dt { color:#666; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
    .wiz-review dd { margin:0 0 12px; font-size:14px; }
    .id-ok { color:#1a7f37; font-weight:600; }
    .id-bad { color:#b42318; font-weight:600; }
</style>

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-magic"></i> Guided registration</span>
            <h1>Taxation Setup</h1>
            <p>Walk through what your business is registered for. A service turns on <strong>only</strong> when its identifier is on file and correctly formatted — otherwise orders keep billing with no tax.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="wiz-steps" id="wizSteps">
        <button type="button" data-step="0" class="active">1&nbsp;·&nbsp;Entity</button>
        <button type="button" data-step="1">2&nbsp;·&nbsp;GST</button>
        <button type="button" data-step="2">3&nbsp;·&nbsp;GST TCS</button>
        <button type="button" data-step="3">4&nbsp;·&nbsp;Income-tax TDS</button>
        <button type="button" data-step="4">5&nbsp;·&nbsp;Gig cess</button>
        <button type="button" data-step="5">6&nbsp;·&nbsp;Review</button>
    </div>

    <form action="{{ route('admin.settings.taxation-setup.save') }}" method="POST" id="wizForm">
        @csrf

        {{-- STEP 1 — ENTITY --}}
        <div class="wiz-panel active" data-panel="0">
            <div class="settings-card"><div class="settings-card-body">
                <div class="settings-section-title">Entity type</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Legal structure</label>
                        <select name="business_entity_type" class="form-select" id="entityType">
                            @php $et = $v('business_entity_type', 'pvt_ltd'); @endphp
                            <option value="pvt_ltd" @selected($et==='pvt_ltd')>Private Limited Company</option>
                            <option value="opc" @selected($et==='opc')>One Person Company (OPC)</option>
                            <option value="llp" @selected($et==='llp')>LLP</option>
                            <option value="partnership" @selected($et==='partnership')>Partnership Firm</option>
                            <option value="proprietorship" @selected($et==='proprietorship')>Proprietorship</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Annual turnover band</label>
                        <select name="business_turnover_band" class="form-select">
                            @php $tb = $v('business_turnover_band', 'below_1cr'); @endphp
                            <option value="below_1cr" @selected($tb==='below_1cr')>Below ₹1 Cr</option>
                            <option value="1cr_5cr" @selected($tb==='1cr_5cr')>₹1 Cr – ₹5 Cr</option>
                            <option value="5cr_10cr" @selected($tb==='5cr_10cr')>₹5 Cr – ₹10 Cr</option>
                            <option value="above_10cr" @selected($tb==='above_10cr')>Above ₹10 Cr</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="business_has_employees" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="hasEmp" name="business_has_employees" value="1" @checked($v('business_has_employees')==='1')>
                            <label class="form-check-label" for="hasEmp">Employees on payroll (adds PF / ESIC / PT to the compliance register)</label>
                        </div>
                    </div>
                </div>
            </div></div>
        </div>

        {{-- STEP 2 — GST --}}
        <div class="wiz-panel" data-panel="1">
            <div class="settings-card"><div class="settings-card-body">
                <div class="settings-section-title">GST registration</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">GSTIN</label>
                        <input type="text" name="business_gstin" id="gstin" maxlength="15" class="form-control text-uppercase" value="{{ old('business_gstin', $v('business_gstin', $v('invoice_company_tax_id'))) }}" placeholder="27AAECY1234A1Z5">
                        <div class="small mt-1" id="gstinHint">15 characters. State code + PAN are derived automatically.</div>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">State code</label>
                        <input type="text" class="form-control" id="stateCode" value="{{ $v('business_state_code') }}" readonly>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">PAN (from GSTIN)</label>
                        <input type="text" class="form-control" id="panFromGstin" value="{{ $v('business_pan') }}" readonly>
                    </div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="business_gst_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="gstEnabled" name="business_gst_enabled" value="1" @checked($v('business_gst_enabled')==='1')>
                            <label class="form-check-label fw-semibold" for="gstEnabled">Enable GST invoicing (tax invoices, CGST/SGST split, rate-wise summary)</label>
                        </div>
                    </div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="gst_9_5_mode" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="mode95" name="gst_9_5_mode" value="1" @checked($v('gst_9_5_mode','1')==='1')>
                            <label class="form-check-label" for="mode95">Platform pays GST on restaurant food — Sec 9(5) ECO</label>
                        </div>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Food GST rate (%)</label>
                        <input type="number" step="0.01" min="0" max="28" name="gst_eco_food_rate" class="form-control" value="{{ $v('gst_eco_food_rate','5') }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Service GST rate (%) — delivery / platform fee / commission</label>
                        <input type="number" step="0.01" min="0" max="28" name="gst_service_rate" class="form-control" value="{{ $v('gst_service_rate','18') }}">
                    </div>
                </div>
            </div></div>
        </div>

        {{-- STEP 3 — TCS --}}
        <div class="wiz-panel" data-panel="2">
            <div class="settings-card"><div class="settings-card-body">
                <div class="settings-section-title">GST TCS — Sec 52</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">TCS GSTIN <span class="text-muted small">(blank = same as above)</span></label>
                        <input type="text" name="einvoice_gstin" id="tcsGstin" maxlength="15" class="form-control text-uppercase" value="{{ old('einvoice_gstin', $v('einvoice_gstin')) }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">TCS rate (%)</label>
                        <input type="number" step="0.01" min="0" max="5" name="gst_tcs_rate" class="form-control" value="{{ $v('gst_tcs_rate','0.5') }}">
                    </div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="gst_tcs_registered" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="tcsReg" name="gst_tcs_registered" value="1" @checked($v('gst_tcs_registered')==='1')>
                            <label class="form-check-label" for="tcsReg">We are registered as a TCS collector under Sec 52</label>
                        </div>
                    </div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="gst_tcs_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="tcsEnabled" name="gst_tcs_enabled" value="1" @checked($v('gst_tcs_enabled')==='1')>
                            <label class="form-check-label fw-semibold" for="tcsEnabled">Collect GST TCS on orders (requires GST invoicing + collector registration)</label>
                        </div>
                    </div>
                </div>
            </div></div>
        </div>

        {{-- STEP 4 — TDS --}}
        <div class="wiz-panel" data-panel="3">
            <div class="settings-card"><div class="settings-card-body">
                <div class="settings-section-title">Income-tax TDS</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">TAN</label>
                        <input type="text" name="business_tan" id="tan" maxlength="10" class="form-control text-uppercase" value="{{ old('business_tan', $v('business_tan')) }}" placeholder="MUMY99999A">
                        <div class="small mt-1" id="tanHint">10 characters — 4 letters, 5 digits, 1 letter.</div>
                    </div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="tds_194o_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="tds194o" name="tds_194o_enabled" value="1" @checked($v('tds_194o_enabled')==='1')>
                            <label class="form-check-label fw-semibold" for="tds194o">Sec 194-O — deduct TDS on restaurant sales</label>
                        </div>
                    </div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="tds_194c_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="tds194c" name="tds_194c_enabled" value="1" @checked($v('tds_194c_enabled')==='1')>
                            <label class="form-check-label fw-semibold" for="tds194c">Sec 194-C — deduct TDS on driver payouts</label>
                        </div>
                    </div>
                    <div class="settings-field settings-span-12">
                        <p class="small text-muted mb-0">Rates, thresholds and no-PAN rates stay on <a href="{{ route('admin.settings.business') }}">Settings &rarr; Business</a>. Both toggles need a valid TAN.</p>
                    </div>
                </div>
            </div></div>
        </div>

        {{-- STEP 5 — GIG CESS --}}
        <div class="wiz-panel" data-panel="4">
            <div class="settings-card"><div class="settings-card-body">
                <div class="settings-section-title">Gig-worker welfare cess</div>
                <div class="settings-grid">
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="gig_welfare_cess_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="cessOn" name="gig_welfare_cess_enabled" value="1" @checked($v('gig_welfare_cess_enabled')==='1')>
                            <label class="form-check-label fw-semibold" for="cessOn">Accrue a welfare cess per delivered order</label>
                        </div>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="10" name="gig_welfare_cess_rate" class="form-control" value="{{ $v('gig_welfare_cess_rate','1') }}">
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Applied on</label>
                        <select name="gig_welfare_cess_base" class="form-select">
                            @php $cb = $v('gig_welfare_cess_base','order_value'); @endphp
                            <option value="order_value" @selected($cb==='order_value')>Order value</option>
                            <option value="driver_payout" @selected($cb==='driver_payout')>Driver payout</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Borne by</label>
                        <select name="gig_cess_borne_by" class="form-select">
                            @php $bb = $v('gig_cess_borne_by','platform'); @endphp
                            <option value="platform" @selected($bb==='platform')>Platform</option>
                            <option value="driver" @selected($bb==='driver')>Driver</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">State</label>
                        <input type="text" name="gig_welfare_cess_state" class="form-control" value="{{ $v('gig_welfare_cess_state') }}">
                    </div>
                    <div class="settings-field settings-span-12">
                        <input type="hidden" name="accounting_enabled" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="acctOn" name="accounting_enabled" value="1" @checked($v('accounting_enabled')==='1')>
                            <label class="form-check-label" for="acctOn">Also enable the double-entry ledger (Balance Sheet / P&amp;L / Cash Flow)</label>
                        </div>
                    </div>
                </div>
            </div></div>
        </div>

        {{-- STEP 6 — REVIEW --}}
        <div class="wiz-panel" data-panel="5">
            <div class="settings-card"><div class="settings-card-body">
                <div class="settings-section-title">What will turn on</div>
                <dl class="wiz-review row" id="wizReview">
                    <div class="col-md-4"><dt>GST invoicing / 9(5)</dt><dd data-rv="gst">—</dd></div>
                    <div class="col-md-4"><dt>GST TCS (Sec 52)</dt><dd data-rv="tcs">—</dd></div>
                    <div class="col-md-4"><dt>TDS 194-O</dt><dd data-rv="tdso">—</dd></div>
                    <div class="col-md-4"><dt>TDS 194-C</dt><dd data-rv="tdsc">—</dd></div>
                    <div class="col-md-4"><dt>Gig welfare cess</dt><dd data-rv="cess">—</dd></div>
                    <div class="col-md-4"><dt>Double-entry ledger</dt><dd data-rv="acct">—</dd></div>
                </dl>
                <p class="small text-muted">Anything showing <span class="id-bad">no valid ID</span> stays off and those orders bill with no tax.</p>
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check me-1"></i> Save taxation setup</button>
            </div></div>
        </div>

        <div class="wiz-nav">
            <button type="button" class="btn btn-outline-secondary" id="wizPrev">&larr; Back</button>
            <button type="button" class="btn btn-primary" id="wizNext">Next &rarr;</button>
        </div>
    </form>
</div>

<script>
(function () {
    const GSTIN_RE = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
    const TAN_RE = /^[A-Z]{4}[0-9]{5}[A-Z]$/;
    const panels = [...document.querySelectorAll('.wiz-panel')];
    const stepBtns = [...document.querySelectorAll('#wizSteps button')];
    const prev = document.getElementById('wizPrev');
    const next = document.getElementById('wizNext');
    let step = 0;

    const gstin = document.getElementById('gstin');
    const tcsGstin = document.getElementById('tcsGstin');
    const tan = document.getElementById('tan');

    function show(i) {
        step = Math.max(0, Math.min(panels.length - 1, i));
        panels.forEach((p, idx) => p.classList.toggle('active', idx === step));
        stepBtns.forEach((b, idx) => {
            b.classList.toggle('active', idx === step);
            b.classList.toggle('done', idx < step);
        });
        prev.style.visibility = step === 0 ? 'hidden' : 'visible';
        next.style.visibility = step === panels.length - 1 ? 'hidden' : 'visible';
        if (step === panels.length - 1) refreshReview();
    }

    function gstinFeedback() {
        const val = (gstin.value || '').toUpperCase().trim();
        const hint = document.getElementById('gstinHint');
        if (!val) { hint.className = 'small mt-1 text-muted'; hint.textContent = '15 characters. State code + PAN are derived automatically.'; document.getElementById('stateCode').value = ''; document.getElementById('panFromGstin').value = ''; return; }
        const ok = GSTIN_RE.test(val);
        hint.className = 'small mt-1 ' + (ok ? 'id-ok' : 'id-bad');
        hint.textContent = ok ? 'Valid GSTIN.' : 'Not a valid GSTIN yet.';
        document.getElementById('stateCode').value = ok ? val.slice(0, 2) : '';
        document.getElementById('panFromGstin').value = ok ? val.slice(2, 12) : '';
    }
    function tanFeedback() {
        const val = (tan.value || '').toUpperCase().trim();
        const hint = document.getElementById('tanHint');
        if (!val) { hint.className = 'small mt-1 text-muted'; hint.textContent = '10 characters — 4 letters, 5 digits, 1 letter.'; return; }
        const ok = TAN_RE.test(val);
        hint.className = 'small mt-1 ' + (ok ? 'id-ok' : 'id-bad');
        hint.textContent = ok ? 'Valid TAN.' : 'Not a valid TAN yet.';
    }
    function refreshReview() {
        const g = (gstin.value || '').toUpperCase().trim();
        const t = (tan.value || '').toUpperCase().trim();
        const tg = ((tcsGstin.value || '').toUpperCase().trim()) || g;
        const gOk = GSTIN_RE.test(g), tOk = TAN_RE.test(t), tgOk = GSTIN_RE.test(tg);
        const yes = '<span class="id-ok">Will activate</span>';
        const noId = '<span class="id-bad">no valid ID — stays off</span>';
        const offToggle = '<span class="text-muted">toggle off</span>';
        const set = (k, html) => document.querySelector(`[data-rv="${k}"]`).innerHTML = html;

        set('gst', !gOk ? noId : (document.getElementById('gstEnabled').checked ? yes : offToggle));
        set('tcs', !(gOk && tgOk) ? noId : ((document.getElementById('tcsReg').checked && document.getElementById('tcsEnabled').checked && document.getElementById('gstEnabled').checked) ? yes : offToggle));
        set('tdso', !tOk ? noId : (document.getElementById('tds194o').checked ? yes : offToggle));
        set('tdsc', !tOk ? noId : (document.getElementById('tds194c').checked ? yes : offToggle));
        set('cess', document.getElementById('cessOn').checked ? yes : offToggle);
        set('acct', document.getElementById('acctOn').checked ? yes : offToggle);
    }

    gstin.addEventListener('input', gstinFeedback);
    tan.addEventListener('input', tanFeedback);
    stepBtns.forEach(b => b.addEventListener('click', () => show(+b.dataset.step)));
    prev.addEventListener('click', () => show(step - 1));
    next.addEventListener('click', () => show(step + 1));
    gstinFeedback(); tanFeedback(); show(0);
})();
</script>
@endsection
