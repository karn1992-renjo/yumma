@extends('layouts.admin')
@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
@endphp

@section('title', 'COD Reconciliation History')

@section('styles')
<style>
    .dash-panel { border-radius:24px; overflow:hidden; min-width:0; border:1px solid rgba(226,232,240,.88); background:linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)), radial-gradient(circle at top right, rgba(124,58,237,.08), transparent 42%); box-shadow:0 22px 55px rgba(15,23,42,.07); }

    .cod-table-wrap { overflow-x:auto; }
    .cod-table { width:100%; border-collapse:separate; border-spacing:0; }
    .cod-table thead th { text-align:left; padding:12px 22px; font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; color:#94a3b8; border-bottom:1px solid rgba(226,232,240,.7); white-space:nowrap; }
    .cod-table tbody td { padding:14px 22px; border-bottom:1px solid rgba(226,232,240,.55); vertical-align:middle; }
    .cod-table tbody tr:last-child td { border-bottom:none; }
    .cod-table tbody tr:hover { background:rgba(124,58,237,.03); }

    .driver-cell { display:flex; align-items:center; gap:12px; min-width:0; }
    .driver-avatar { width:38px; height:38px; border-radius:12px; color:#fff; background:linear-gradient(135deg,#111827,#7c3aed); display:inline-flex; align-items:center; justify-content:center; font-weight:950; font-size:14px; flex-shrink:0; }
    .driver-name { font-weight:800; color:#0f172a; }

    .amount-pill { font-weight:950; color:#166534; font-size:14px; }
    .settled-by-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:900; background:#eef2ff; color:#4338ca; white-space:nowrap; }

    .cod-empty { padding:60px 24px; text-align:center; color:#94a3b8; }
    .cod-empty i { font-size:34px; margin-bottom:10px; display:block; color:#cbd5e1; }
</style>
@endsection

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>COD Reconciliation History</h1>
        <p>Record of your drivers' cash deposits marked settled.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('branch.cod') }}" class="btn btn-light border"><i class="fas fa-arrow-left me-2"></i> Pending Collection</a>
        <a href="{{ route('branch.cod.export') }}" class="btn btn-light border"><i class="fas fa-file-excel me-2"></i> Export</a>
    </div>
</div>

<div class="dash-panel">
    @if($transactions->isEmpty())
        <div class="cod-empty">
            <i class="fas fa-box-archive"></i>
            No COD deposits recorded yet.
        </div>
    @else
        <div class="cod-table-wrap">
            <table class="cod-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Driver</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Settled By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $transaction)
                        <tr>
                            <td class="text-muted small">{{ optional($transaction->created_at)->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="driver-cell">
                                    <div class="driver-avatar">{{ strtoupper(substr($transaction->user?->name ?: '?', 0, 1)) }}</div>
                                    <div class="driver-name">{{ $transaction->user?->name ?? '—' }}</div>
                                </div>
                            </td>
                            <td><span class="amount-pill">{{ $currencySymbol }}{{ number_format($transaction->amount, $currencyDecimals) }}</span></td>
                            <td class="text-muted small">{{ $transaction->description }}</td>
                            <td><span class="settled-by-pill"><i class="fas fa-user-check"></i> {{ $transaction->creator?->name ?? 'System' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
</div>
@endsection
