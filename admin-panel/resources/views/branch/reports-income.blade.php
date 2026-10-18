@extends('layouts.admin')

@section('title', $type === 'month' ? 'Month-wise Income' : 'Day-wise Income')
@section('header', 'Branch Reports')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $decimals = App\Models\AppSetting::currencyDecimals();
@endphp

@section('styles')
<style>
    .saas-dashboard { display: grid; gap: 26px; }
    .branch-intro {
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;
        padding: 18px 24px; border-radius: 24px; border: 1px solid rgba(226, 232, 240, .88);
        background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)), radial-gradient(circle at top right, rgba(249,115,22,.12), transparent 42%);
        box-shadow: 0 22px 55px rgba(15, 23, 42, .07);
    }
    .branch-intro-kicker {
        display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px;
        background: #fff7ed; color: #c2410c; font-size: 12px; font-weight: 900; margin-bottom: 8px;
    }
    .branch-intro h1 { font-size: 22px; font-weight: 950; letter-spacing: -.02em; color: #0f172a; margin: 0; }
    .branch-intro p { color: #64748b; font-size: 13px; font-weight: 600; margin: 4px 0 0; }

    .type-toggle { display: inline-flex; border: 1px solid rgba(226,232,240,.9); border-radius: 999px; padding: 4px; background: #fff; }
    .type-toggle a {
        padding: 8px 18px; border-radius: 999px; font-size: 13px; font-weight: 800; color: #64748b; text-decoration: none;
    }
    .type-toggle a.active { background: linear-gradient(135deg, #f97316, #ef4444); color: #fff; }

    .dash-panel {
        border: 1px solid rgba(226, 232, 240, .88); border-radius: 24px; overflow: hidden; min-width: 0;
        background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)), radial-gradient(circle at top right, rgba(249,115,22,.1), transparent 42%);
        box-shadow: 0 22px 55px rgba(15, 23, 42, .07);
    }
    .panel-head { padding: 20px 22px 14px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .panel-title { margin: 0; color: #0f172a; font-size: 17px; font-weight: 950; letter-spacing: -.02em; }
    .panel-subtitle { color: #64748b; font-size: 12px; margin-top: 4px; font-weight: 600; }

    .chart-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; padding: 0 22px 20px; }
    .summary-tile { border: 1px solid rgba(226,232,240,.78); border-radius: 16px; background: rgba(248,250,252,.82); padding: 14px; }
    .summary-label { color: #64748b; font-size: 11px; font-weight: 800; }
    .summary-value { color: #0f172a; font-weight: 950; margin-top: 4px; font-size: 18px; }

    .order-table { margin: 0; }
    .order-table thead th { border: 0; color: #64748b; background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 0; padding: 14px 18px; }
    .order-table tbody td { padding: 14px 18px; vertical-align: middle; border-color: #f1f5f9; }

    @media (max-width: 900px) {
        .chart-summary { grid-template-columns: 1fr; }
        .branch-intro { padding: 16px; }
    }
</style>
@endsection

@section('content')
<div class="saas-dashboard">
    <div class="branch-intro">
        <div>
            <div class="branch-intro-kicker"><i class="fas fa-chart-pie"></i> {{ $type === 'month' ? 'Month-wise' : 'Day-wise' }} Income</div>
            <h1>{{ $branch->name }}</h1>
            <p>{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <div class="type-toggle">
                <a href="{{ route('branch.reports.income', ['type' => 'day']) }}" class="{{ $type === 'day' ? 'active' : '' }}">Day-wise</a>
                <a href="{{ route('branch.reports.income', ['type' => 'month']) }}" class="{{ $type === 'month' ? 'active' : '' }}">Month-wise</a>
            </div>
            @if($capabilities['reports_export'] ?? false)
                <a href="{{ route('branch.reports.income.export', array_merge(request()->query(), ['type' => $type])) }}" class="btn btn-outline-dark rounded-pill fw-bold px-4">
                    <i class="fas fa-file-excel me-2"></i> Export
                </a>
            @endif
            <a href="{{ route('branch.reports') }}" class="btn btn-light rounded-pill fw-bold px-4">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>

    <div class="dash-panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Filter</h3>
                <div class="panel-subtitle">Defaults to the last {{ $type === 'month' ? '24 months' : '90 days' }} when left empty.</div>
            </div>
        </div>
        <form method="GET" action="{{ route('branch.reports.income') }}" class="row g-3 px-3 pb-4">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="col-md-4">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="{{ request('from', $from->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="{{ request('to', $to->format('Y-m-d')) }}">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Apply</button>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="{{ route('branch.reports.income', ['type' => $type]) }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="dash-panel">
        <div class="chart-summary pt-4">
            <div class="summary-tile"><div class="summary-label">Orders</div><div class="summary-value">{{ number_format($totals['orders']) }}</div></div>
            <div class="summary-tile"><div class="summary-label">Revenue</div><div class="summary-value">{{ $currencySymbol }}{{ number_format($totals['revenue'], $decimals) }}</div></div>
            <div class="summary-tile"><div class="summary-label">Branch Commission</div><div class="summary-value">{{ $currencySymbol }}{{ number_format($totals['commission'], $decimals) }}</div></div>
        </div>
    </div>

    <div class="dash-panel">
        <div class="panel-head">
            <h3 class="panel-title">{{ $type === 'month' ? 'Month-wise' : 'Day-wise' }} Detail</h3>
        </div>
        <div class="table-responsive">
            <table class="table order-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ $type === 'month' ? 'Month' : 'Date' }}</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Branch Commission</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($paginated as $row)
                    <tr>
                        <td>{{ $row->period_label }}</td>
                        <td class="text-end">{{ $row->orders }}</td>
                        <td class="text-end">{{ number_format($row->revenue, $decimals) }}</td>
                        <td class="text-end">{{ number_format($row->commission, $decimals) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-5">No delivered orders in this range.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent">{{ $paginated->links() }}</div>
    </div>
</div>
@endsection
