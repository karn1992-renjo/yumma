@extends('layouts.admin')
@section('title', 'Accounting — Tax Ledger')
@section('header', 'Accounting — Tax Ledger')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); $qs = ['from' => $from, 'to' => $to]; @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Tax Ledger</h1><p>Every GST / TDS / TCS accrual &mdash; the single source for all reports.</p></div></div>
    @include('admin.accounting._tabs')

    <form method="GET" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}">
        <div class="col-sm-3">
            <label class="form-label small">Kind</label>
            <select name="kind" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach($kinds as $k)<option value="{{ $k }}" @selected(($filters['kind'] ?? '') === $k)>{{ $k }}</option>@endforeach
            </select>
        </div>
        <div class="col-sm-3">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select form-select-sm">
                @foreach(['' => 'All', 'accrued' => 'Accrued', 'deposited' => 'Deposited', 'filed' => 'Filed'] as $v => $l)
                    <option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
        <div class="col-sm-2"><a class="btn btn-sm btn-outline-secondary w-100" href="{{ route('admin.accounting.export', ['doc' => 'ledger'] + $qs) }}">Export</a></div>
    </form>

    <div class="table-card">
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Date</th><th>Kind</th><th>Party</th><th>Order</th><th>Payout</th><th class="text-end">Taxable</th><th class="text-end">Rate</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">Amount</th><th>Period</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($entries as $e)
                <tr>
                    <td class="small">{{ $e->created_at->format('d M, H:i') }}</td>
                    <td>{{ $e->kind }}</td>
                    <td>{{ $e->party_type ? class_basename($e->party_type) . ' #' . $e->party_id : 'Platform' }}</td>
                    <td>{{ $e->order_id ?: '—' }}</td>
                    <td>{{ $e->payout_id ?: '—' }}</td>
                    <td class="text-end">{{ $m($e->taxable_value) }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($e->rate, 3), '0'), '.') }}</td>
                    <td class="text-end">{{ $m($e->cgst) }}</td>
                    <td class="text-end">{{ $m($e->sgst) }}</td>
                    <td class="text-end fw-bold">{{ $m($e->amount) }}</td>
                    <td>{{ $e->period }}</td>
                    <td>{{ ucfirst($e->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center text-muted py-4">No ledger entries in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-3">{{ $entries->links() }}</div>
    </div>
</div>
@endsection
