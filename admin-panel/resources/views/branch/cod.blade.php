@extends('layouts.admin')
@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $canSettleCod = $capabilities['cod_settle'] ?? false;
@endphp

@section('title', 'COD Management')

@section('styles')
<style>
    .cod-kpi-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; margin-bottom:22px; }
    .kpi-card { position:relative; min-height:150px; padding:22px; border-radius:24px; overflow:hidden; border:1px solid rgba(226,232,240,.88); background:linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)), radial-gradient(circle at top right, var(--card-glow, rgba(124,58,237,.12)), transparent 42%); box-shadow:0 22px 55px rgba(15,23,42,.07); }
    .kpi-card::after { content:""; position:absolute; inset:auto -34px -56px auto; width:120px; height:120px; border-radius:50%; background:var(--accent); opacity:.09; }
    .kpi-top { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; position:relative; z-index:1; }
    .kpi-icon { width:48px; height:48px; border-radius:18px; display:inline-flex; align-items:center; justify-content:center; color:var(--accent); background:color-mix(in srgb, var(--accent) 14%, white); box-shadow:inset 0 1px 0 rgba(255,255,255,.8); font-size:19px; }
    .kpi-trend { color:#64748b; font-size:12px; font-weight:900; white-space:nowrap; }
    .kpi-label { color:#64748b; font-weight:800; font-size:13px; margin-top:18px; position:relative; z-index:1; }
    .kpi-value { color:#0f172a; font-size:28px; font-weight:950; letter-spacing:-.03em; line-height:1.05; margin-top:4px; position:relative; z-index:1; }
    .kpi-sub { color:#64748b; font-size:12px; font-weight:700; margin-top:12px; position:relative; z-index:1; }

    .dash-panel { border-radius:24px; overflow:hidden; min-width:0; border:1px solid rgba(226,232,240,.88); background:linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)), radial-gradient(circle at top right, rgba(124,58,237,.08), transparent 42%); box-shadow:0 22px 55px rgba(15,23,42,.07); margin-bottom:22px; }
    .panel-head { padding:20px 22px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; border-bottom:1px solid rgba(226,232,240,.7); }
    .panel-title { margin:0; color:#0f172a; font-size:17px; font-weight:950; letter-spacing:-.02em; }
    .panel-sub { color:#64748b; font-size:12.5px; font-weight:600; margin-top:2px; }

    .cod-filter-bar, .collect-panel-body { padding:16px 22px; border-bottom:1px solid rgba(226,232,240,.7); background:rgba(248,250,252,.6); }
    .collect-grid { display:grid; grid-template-columns:minmax(260px,1.2fr) minmax(170px,.7fr) minmax(170px,.8fr) minmax(180px,.8fr) auto; gap:12px; align-items:end; }
    .balance-preview { border:1px solid rgba(245,158,11,.28); border-radius:16px; background:#fffbeb; padding:11px 14px; min-height:48px; }
    .balance-preview span { display:block; color:#92400e; font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
    .balance-preview strong { color:#0f172a; font-size:18px; font-weight:950; }

    .cod-table-wrap { overflow-x:auto; }
    .cod-table { width:100%; border-collapse:separate; border-spacing:0; }
    .cod-table thead th { text-align:left; padding:12px 22px; font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; color:#94a3b8; border-bottom:1px solid rgba(226,232,240,.7); white-space:nowrap; }
    .cod-table tbody td { padding:14px 22px; border-bottom:1px solid rgba(226,232,240,.55); vertical-align:middle; }
    .cod-table tbody tr:last-child td { border-bottom:none; }
    .cod-table tbody tr:hover { background:rgba(124,58,237,.03); }

    .driver-cell { display:flex; align-items:center; gap:12px; min-width:0; }
    .driver-avatar { width:42px; height:42px; border-radius:14px; color:#fff; background:linear-gradient(135deg,#111827,#7c3aed); display:inline-flex; align-items:center; justify-content:center; font-weight:950; font-size:15px; flex-shrink:0; }
    .driver-name { font-weight:800; color:#0f172a; }
    .driver-sub { color:#94a3b8; font-size:12.5px; font-weight:600; }

    .amount-pill { font-weight:950; color:#b45309; font-size:14.5px; }

    .status-pill { display:inline-flex; align-items:center; gap:6px; padding:7px 10px; border-radius:999px; font-size:11px; font-weight:900; white-space:nowrap; }
    .status-pill.urgent { background:#fee2e2; color:#991b1b; }
    .status-pill.warn { background:#ffedd5; color:#9a3412; }
    .status-pill.ok { background:#dcfce7; color:#166534; }
    .status-pill.neutral { background:#f1f5f9; color:#475569; }

    .cod-empty { padding:60px 24px; text-align:center; color:#94a3b8; }
    .cod-empty i { font-size:34px; margin-bottom:10px; display:block; color:#cbd5e1; }

    .btn-settle { background:linear-gradient(135deg,#16a34a,#22c55e); border:none; color:#fff; font-weight:900; padding:10px 20px; border-radius:14px; box-shadow:0 12px 24px rgba(34,197,94,.28); transition:filter .15s ease, opacity .15s ease; white-space:nowrap; }
    .btn-settle:hover:not(:disabled) { color:#fff; filter:brightness(1.06); }
    .btn-settle:disabled { opacity:.45; cursor:not-allowed; box-shadow:none; }

    .cod-search-input, .cod-select, .cod-amount-input { border-radius:14px; border:1px solid rgba(226,232,240,.9); }

    @media (max-width: 1200px) { .collect-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width: 900px) { .cod-kpi-grid, .collect-grid { grid-template-columns:1fr; } }
</style>
@endsection

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>COD Management</h1>
        <p>Cash collected by your drivers on delivery, pending deposit reconciliation.</p>
    </div>
    <a href="{{ route('branch.cod.history') }}" class="btn btn-light border">
        <i class="fas fa-clock-rotate-left me-2"></i> Reconciliation History
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="cod-kpi-grid">
    <div class="kpi-card" style="--accent:#f59e0b;--card-glow:rgba(245,158,11,.16);">
        <div class="kpi-top">
            <div class="kpi-icon"><i class="fas fa-sack-dollar"></i></div>
            <div class="kpi-trend"><i class="fas fa-circle"></i> Awaiting deposit</div>
        </div>
        <div class="kpi-label">Pending COD Amount</div>
        <div class="kpi-value">{{ $currencySymbol }}{{ number_format($totals['amount'], $currencyDecimals) }}</div>
        <div class="kpi-sub">Cash held by your drivers</div>
    </div>

    <div class="kpi-card" style="--accent:#3b82f6;--card-glow:rgba(59,130,246,.15);">
        <div class="kpi-top">
            <div class="kpi-icon"><i class="fas fa-receipt"></i></div>
        </div>
        <div class="kpi-label">Pending Orders</div>
        <div class="kpi-value">{{ number_format($totals['orders']) }}</div>
        <div class="kpi-sub">Delivered COD orders not yet settled</div>
    </div>

    <div class="kpi-card" style="--accent:#8b5cf6;--card-glow:rgba(139,92,246,.16);">
        <div class="kpi-top">
            <div class="kpi-icon"><i class="fas fa-truck-fast"></i></div>
        </div>
        <div class="kpi-label">Drivers Holding Cash</div>
        <div class="kpi-value">{{ number_format($totals['drivers']) }}</div>
        <div class="kpi-sub">Need to deposit collected cash</div>
    </div>
</div>

@if($canSettleCod)
    <div class="dash-panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Collect Driver Cash</h3>
                <div class="panel-sub">Select a mapped driver, confirm balance, and enter the amount received.</div>
            </div>
        </div>
        <div class="collect-panel-body">
            @php $pendingDrivers = $drivers->where('pending_amount', '>', 0); @endphp
            @if($pendingDrivers->isEmpty())
                <div class="cod-empty py-4">
                    <i class="fas fa-circle-check"></i>
                    No driver has pending COD cash right now.
                </div>
            @else
                <form method="POST" action="{{ route('branch.cod.settle') }}" class="collect-grid" id="codCollectForm">
                    @csrf
                    <div>
                        <label class="form-label fw-bold small text-muted">Driver</label>
                        <select name="driver_id" id="codDriverSelect" class="form-select cod-select" required>
                            <option value="">Select driver</option>
                            @foreach($pendingDrivers as $driver)
                                <option value="{{ $driver->id }}" data-balance="{{ number_format($driver->pending_amount, 2, '.', '') }}" @selected((string) old('driver_id') === (string) $driver->id)>
                                    {{ $driver->name }} - {{ $currencySymbol }}{{ number_format($driver->pending_amount, $currencyDecimals) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="balance-preview">
                        <span>COD Balance</span>
                        <strong id="codSelectedBalance">{{ $currencySymbol }}{{ number_format(0, $currencyDecimals) }}</strong>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-muted">Amount Collected</label>
                        <input type="number" name="collected_amount" id="codCollectedAmount" class="form-control cod-amount-input" min="0.01" step="0.01" value="{{ old('collected_amount') }}" required>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-muted">Reference</label>
                        <input type="text" name="reference" class="form-control cod-search-input" value="{{ old('reference') }}" placeholder="Receipt or note">
                    </div>
                    <button type="submit" id="codCollectBtn" class="btn btn-settle">
                        <i class="fas fa-check me-2"></i> Collect Cash
                    </button>
                </form>
                <div class="small text-muted fw-semibold mt-2">If collected amount is greater than COD balance, the extra amount is credited to the driver wallet.</div>
            @endif
        </div>
    </div>
@endif

<div class="dash-panel">
    <div class="cod-filter-bar">
        <form method="GET" action="{{ route('branch.cod') }}" class="row g-2 align-items-center">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control cod-search-input" placeholder="Search driver by name or phone..." value="{{ $search }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('branch.cod') }}" class="btn btn-light w-100 border">Clear</a>
            </div>
        </form>
    </div>

    @if($drivers->isEmpty())
        <div class="cod-empty">
            <i class="fas fa-user-slash"></i>
            No delivery partners match this filter.
        </div>
    @else
        <div class="cod-table-wrap">
            <table class="cod-table">
                <thead>
                    <tr>
                        <th>Driver</th>
                        <th>Pending Orders</th>
                        <th>COD Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($drivers as $driver)
                        @php
                            $hasPending = $driver->pending_amount > 0;
                            $hoursPending = $driver->oldest_collected_at
                                ? \Illuminate\Support\Carbon::parse($driver->oldest_collected_at)->diffInHours(now())
                                : null;
                            $ageClass = $hoursPending === null ? 'neutral' : ($hoursPending >= 72 ? 'urgent' : ($hoursPending >= 24 ? 'warn' : 'ok'));
                        @endphp
                        <tr>
                            <td>
                                <div class="driver-cell">
                                    <div class="driver-avatar">{{ strtoupper(substr($driver->name ?: '?', 0, 1)) }}</div>
                                    <div class="min-w-0">
                                        <div class="driver-name">{{ $driver->name }}</div>
                                        <div class="driver-sub">{{ $driver->phone }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $driver->pending_orders }}</td>
                            <td>
                                @if($hasPending)
                                    <span class="amount-pill">{{ $currencySymbol }}{{ number_format($driver->pending_amount, $currencyDecimals) }}</span>
                                @else
                                    <span class="text-muted">{{ $currencySymbol }}{{ number_format(0, $currencyDecimals) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($hasPending)
                                    <span class="status-pill {{ $ageClass }}">
                                        <i class="fas fa-clock"></i>
                                        @if($driver->oldest_collected_at)
                                            {{ \Illuminate\Support\Carbon::parse($driver->oldest_collected_at)->diffForHumans(null, true) }} ago
                                        @else
                                            Holding cash
                                        @endif
                                    </span>
                                @else
                                    <span class="status-pill ok"><i class="fas fa-circle-check"></i> Reconciled</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
(function () {
    const select = document.getElementById('codDriverSelect');
    const balance = document.getElementById('codSelectedBalance');
    const amount = document.getElementById('codCollectedAmount');
    const currencySymbol = @json($currencySymbol);
    const decimals = @json($currencyDecimals);

    function formatAmount(value) {
        return currencySymbol + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    function refreshDriverBalance() {
        const option = select?.selectedOptions?.[0];
        const driverBalance = Number(option?.dataset?.balance || 0);
        if (balance) balance.textContent = formatAmount(driverBalance);
        if (amount && !amount.value) amount.value = driverBalance > 0 ? driverBalance.toFixed(2) : '';
    }

    select?.addEventListener('change', refreshDriverBalance);
    refreshDriverBalance();
})();
</script>
@endsection
