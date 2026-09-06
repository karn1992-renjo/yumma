@extends('layouts.admin')
@section('title', 'Accounting — Gig Cess')
@section('header', 'Accounting — Gig Cess')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); $qs = ['from' => $from, 'to' => $to]; @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Gig-Worker Welfare Cess</h1><p>Per-delivered-order accrual to the cess payable. Mark a period remitted once the challan is paid.</p></div></div>
    @include('admin.accounting._tabs')

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @unless($enabled)
        <div class="alert alert-warning">Gig cess is currently <strong>off</strong>. Turn it on in <a href="{{ route('admin.settings.taxation-setup') }}">Taxation Setup</a> — historical orders are not back-charged.</div>
    @else
        <div class="alert alert-info py-2">Accruing <strong>{{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%</strong> on <strong>{{ str_replace('_', ' ', $cessBase) }}</strong> per delivered order.</div>
    @endunless

    <div class="table-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">By period</h5>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.accounting.export', ['doc' => 'ledger'] + $qs) }}">Export ledger</a>
        </div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Period</th><th class="text-end">Orders</th><th class="text-end">Accrued</th><th class="text-end">Remitted</th><th class="text-end">Outstanding</th><th></th></tr></thead>
            <tbody>
            @forelse($byPeriod as $p)
                <tr>
                    <td>{{ $p->period }}</td>
                    <td class="text-end">{{ number_format($p->orders) }}</td>
                    <td class="text-end">{{ $m($p->amount) }}</td>
                    <td class="text-end">{{ $m($p->remitted) }}</td>
                    <td class="text-end fw-bold">{{ $m($p->amount - $p->remitted) }}</td>
                    <td class="text-end">
                        @if($p->amount - $p->remitted > 0.009)
                        <form method="POST" action="{{ route('admin.accounting.mark-filed') }}" class="d-inline" onsubmit="return confirm('Mark {{ $p->period }} gig cess as remitted?')">
                            @csrf
                            <input type="hidden" name="kind" value="gig_cess">
                            <input type="hidden" name="period" value="{{ $p->period }}">
                            <button class="btn btn-sm btn-outline-success">Mark remitted</button>
                        </form>
                        @else
                            <span class="badge bg-success">Settled</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No cess accrued in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="table-card">
        <div class="card-header"><h5 class="mb-0">Order-level accruals</h5></div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Date</th><th>Order</th><th>Driver</th><th class="text-end">Base</th><th class="text-end">Rate</th><th class="text-end">Cess</th><th>Borne by</th><th>Period</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($rows as $e)
                <tr>
                    <td class="small">{{ $e->created_at->format('d M, H:i') }}</td>
                    <td>{{ $e->order_id ?: '—' }}</td>
                    <td>{{ data_get($e->meta, 'driver_id') ?: '—' }}</td>
                    <td class="text-end">{{ $m($e->taxable_value) }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($e->rate, 3), '0'), '.') }}%</td>
                    <td class="text-end fw-bold">{{ $m($e->amount) }}</td>
                    <td>{{ ucfirst(data_get($e->meta, 'borne_by', 'platform')) }}</td>
                    <td>{{ $e->period }}</td>
                    <td>{{ ucfirst($e->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No accruals.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-3">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
