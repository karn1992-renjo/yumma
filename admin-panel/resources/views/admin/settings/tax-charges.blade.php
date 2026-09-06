@extends('layouts.admin')

@section('title', 'Tax & Charges')
@section('header', 'Tax & Charges')

@section('content')
@include('admin.settings._style')

@php
    /** @var \App\Services\Tax\TaxConfig $config */
    $s = $settings;
    $dc = $deliveryChargeSetting;
    $sym = $currencySymbol;

    $chip = function (bool $on, string $onLabel = 'Active', string $offLabel = 'Off') {
        $cls = $on ? 'background:#dff5e3;color:#1a7f37' : 'background:#eee;color:#666';
        $txt = $on ? $onLabel : $offLabel;
        return '<span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;' . $cls . '">' . $txt . '</span>';
    };
    $commissionGst = $s['gst_rate'] ?? null;
@endphp

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-receipt"></i> One place for everything billable</span>
            <h1>Tax &amp; Charges</h1>
            <p>Delivery &amp; platform charges, the GST/TDS/TCS registration status, commission GST, and the fallback tax rules used when GST invoicing is off — all consolidated here.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- ============ TAXATION STATUS ============ --}}
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Taxation Status</h2>
                <p class="settings-card-subtitle">A tax service only runs when the business is registered <em>and</em> the required identifier is on file and valid. Otherwise orders bill normally with no tax.</p>
            </div>
            <div class="d-flex gap-2">
                @if(\Illuminate\Support\Facades\Route::has('admin.settings.taxation-setup'))
                    <a href="{{ route('admin.settings.taxation-setup') }}" class="btn btn-primary btn-sm"><i class="fas fa-magic me-1"></i> Set up taxation</a>
                @endif
                <a href="{{ route('admin.settings.business') }}" class="btn btn-outline-secondary btn-sm">Company &amp; identity</a>
            </div>
        </div>
        <div class="settings-card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Service</th><th>Status</th><th>Identifier on file</th><th></th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold">GST invoicing / Sec 9(5)</td>
                            <td>{!! $chip($config->gstEnabled(), 'Live', $config->gstRegistered() ? 'Registered, toggle off' : 'Not registered') !!}</td>
                            <td class="small text-muted">GSTIN: {{ $config->gstin() ?: '—' }}</td>
                            <td class="text-end"><a class="small" href="{{ route('admin.accounting.gst') }}">GST reports &rarr;</a></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">TDS — Sec 194-O (restaurant sales)</td>
                            <td>{!! $chip($config->tds194oEnabled(), 'Live', $config->tdsRegistered() ? 'TAN ok, toggle off' : 'No valid TAN') !!}</td>
                            <td class="small text-muted">TAN: {{ $config->tan() ?: '—' }}</td>
                            <td class="text-end"><a class="small" href="{{ route('admin.accounting.tds') }}">26Q &rarr;</a></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">TDS — Sec 194-C (driver payouts)</td>
                            <td>{!! $chip($config->tds194cEnabled(), 'Live', $config->tdsRegistered() ? 'TAN ok, toggle off' : 'No valid TAN') !!}</td>
                            <td class="small text-muted">TAN: {{ $config->tan() ?: '—' }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">GST TCS — Sec 52</td>
                            <td>{!! $chip($config->tcsEnabled(), 'Live', $config->tcsRegistered() ? 'Registered, toggle off' : 'Not registered') !!}</td>
                            <td class="small text-muted">{{ ($s['gst_tcs_registered'] ?? '0') === '1' ? 'Collector confirmed' : 'Collector not confirmed' }}</td>
                            <td class="text-end"><a class="small" href="{{ route('admin.accounting.tcs') }}">GSTR-8 &rarr;</a></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Gig-worker welfare cess</td>
                            <td>{!! $chip($config->gigCessEnabled()) !!}</td>
                            <td class="small text-muted">{{ $config->gigCessEnabled() ? rtrim(rtrim(number_format($config->gigCessRate(), 2), '0'), '.') . '% on ' . str_replace('_', ' ', $config->gigCessBase()) : '—' }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Double-entry ledger</td>
                            <td>{!! $chip($config->accountingEnabled()) !!}</td>
                            <td class="small text-muted">Entity: {{ str_replace('_', ' ', $config->entityType()) }}</td>
                            <td class="text-end"><a class="small" href="{{ route('admin.accounting.gl.balance-sheet') }}">Balance sheet &rarr;</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-3 mb-0">Rates &amp; toggles for all of the above live on <a href="{{ route('admin.settings.business') }}">Settings &rarr; Business</a>. This panel is read-only status.</p>
        </div>
    </div>

    {{-- ============ DELIVERY & PLATFORM CHARGES ============ --}}
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Delivery &amp; Platform Charges</h2>
                <p class="settings-card-subtitle">Applied to every order. Zone-wise free delivery and per-restaurant minimum order live on the full editor.</p>
            </div>
            <a href="{{ route('admin.delivery-charges') }}" class="btn btn-outline-secondary btn-sm">Zones &amp; min-order &rarr;</a>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.delivery-charges.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Charge Type</label>
                        <select name="charge_type" class="form-select">
                            <option value="fixed" @selected(($dc->charge_type ?? 'fixed') === 'fixed')>Fixed</option>
                            <option value="per_km" @selected(($dc->charge_type ?? '') === 'per_km')>Per KM</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Base Charge ({{ $sym }})</label>
                        <input type="number" step="0.01" min="0" name="base_charge" class="form-control" value="{{ $dc->base_charge ?? 40 }}" required>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Per KM Charge ({{ $sym }})</label>
                        <input type="number" step="0.01" min="0" name="per_km_charge" class="form-control" value="{{ $dc->per_km_charge ?? 10 }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Fixed Platform Charge ({{ $sym }})</label>
                        <input type="number" step="0.01" min="0" name="platform_fee" class="form-control" value="{{ $dc->platform_fee ?? 0 }}">
                        <div class="small text-muted mt-1">Added once per order — not a percentage.</div>
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Order Acceptance Time (sec)</label>
                        <input type="number" min="30" max="600" name="order_acceptance_timeout_seconds" class="form-control" value="{{ $dc->order_acceptance_timeout_seconds ?? 180 }}" required>
                    </div>
                    <div class="settings-field settings-span-12"><hr class="my-1"></div>
                    <div class="settings-field settings-span-12">
                        <div class="settings-section-title">Free-delivery cost sharing <span class="text-muted small">(must total 100%)</span></div>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Admin Contribution (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="admin_contribution_percent" class="form-control" value="{{ $dc->admin_contribution_percent ?? 50 }}" required>
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Restaurant Contribution (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="restaurant_contribution_percent" class="form-control" value="{{ $dc->restaurant_contribution_percent ?? 50 }}" required>
                    </div>
                </div>
                <div class="mt-3"><button class="btn btn-primary">Save Charges</button></div>
            </form>
        </div>
    </div>

    {{-- ============ COMMISSION GST ============ --}}
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Commission GST</h2>
                <p class="settings-card-subtitle">18% GST on the platform commission is B2B — the restaurant claims it as input tax credit. The rate is managed with the commission structure.</p>
            </div>
            <a href="{{ route('admin.commissions') }}" class="btn btn-outline-secondary btn-sm">Open Commissions &rarr;</a>
        </div>
        <div class="settings-card-body">
            <div class="settings-grid">
                <div class="settings-field settings-span-4">
                    <label class="form-label">GST on Commission (%)</label>
                    <input type="text" class="form-control" value="{{ $commissionGst !== null ? rtrim(rtrim((string) $commissionGst, '0'), '.') . '%' : 'Not set (defaults to 18%)' }}" readonly>
                    <div class="small text-muted mt-1">Read-only here. Edit under Commissions.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ FALLBACK TAX RULES ============ --}}
    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Fallback Tax Rules</h2>
                <p class="settings-card-subtitle"><strong>Applied only when GST invoicing is OFF.</strong> Percentage taxes use their taxable base; fixed charges are added directly. Once GST invoicing is on, the GST engine takes over and these are ignored.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <div class="row g-4">
                <div class="col-lg-5">
                    <form action="{{ route('admin.taxes.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Service Charge" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="type" class="form-select" required>
                                <option value="gst">GST</option>
                                <option value="service_charge">Service Charge</option>
                                <option value="packaging_charge">Packaging Charge</option>
                                <option value="delivery_charge_tax">Delivery Charge Tax</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Calculation</label>
                            <select name="calculation_type" class="form-select" required>
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Rate / Amount</label>
                            <input type="number" step="0.01" min="0" name="rate" class="form-control" placeholder="e.g. 5" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i> Add Rule</button>
                    </form>
                </div>
                <div class="col-lg-7">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Name</th><th>Type</th><th>Rate</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @forelse($taxes as $tax)
                                    <tr>
                                        <td class="fw-semibold">{{ $tax->name }}</td>
                                        <td class="small text-muted">{{ ucwords(str_replace('_', ' ', $tax->type)) }}</td>
                                        <td class="fw-bold">
                                            @if(($tax->calculation_type ?? 'percentage') === 'fixed')
                                                {{ $sym }}{{ number_format((float) $tax->rate, App\Models\AppSetting::currencyDecimals()) }}
                                            @else
                                                {{ rtrim(rtrim((string) $tax->rate, '0'), '.') }}%
                                            @endif
                                        </td>
                                        <td>
                                            <form action="{{ route('admin.taxes.update', $tax->id) }}" method="POST" class="d-inline">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="name" value="{{ $tax->name }}">
                                                <input type="hidden" name="type" value="{{ $tax->type }}">
                                                <input type="hidden" name="calculation_type" value="{{ $tax->calculation_type ?? 'percentage' }}">
                                                <input type="hidden" name="rate" value="{{ $tax->rate }}">
                                                <input type="hidden" name="description" value="{{ $tax->description }}">
                                                <input type="hidden" name="is_active" value="{{ $tax->is_active ? 0 : 1 }}">
                                                <button class="btn btn-sm {{ $tax->is_active ? 'btn-success' : 'btn-secondary' }}">{{ $tax->is_active ? 'Active' : 'Inactive' }}</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form action="{{ route('admin.taxes.destroy', $tax->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this rule?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">No fallback rules. That's fine when GST invoicing is on.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
