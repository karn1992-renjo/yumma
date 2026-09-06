@extends('layouts.admin')
@section('title', 'Profit & Loss')
@section('header', 'Profit & Loss')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Profit &amp; Loss</h1><p>{{ $from }} to {{ $to }}</p></div></div>
    @include('admin.accounting._tabs')

    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-sm-3"><label class="form-label small">From</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
        <div class="col-sm-3"><label class="form-label small">To</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
        <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Apply</button></div>
        <div class="col-sm-2"><a class="btn btn-sm btn-outline-secondary w-100" href="{{ route('admin.accounting.gl.export', ['doc' => 'profit-loss', 'from' => $from, 'to' => $to]) }}">Excel</a></div>
        <div class="col-sm-2"><a class="btn btn-sm btn-outline-secondary w-100" href="{{ route('admin.accounting.gl.export', ['doc' => 'profit-loss', 'from' => $from, 'to' => $to, 'format' => 'pdf']) }}">PDF</a></div>
    </form>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="table-card"><div class="card-header"><h5 class="mb-0">Income</h5></div>
            <div class="table-responsive"><table class="table mb-0">
                @forelse($report['income'] as $x)<tr><td>{{ $x['name'] }}</td><td class="text-end">{{ $m($x['amount']) }}</td></tr>@empty<tr><td colspan="2" class="text-muted text-center py-3">None</td></tr>@endforelse
                <tr class="fw-bold"><td>Total income</td><td class="text-end">{{ $m($report['total_income']) }}</td></tr>
            </table></div></div>
        </div>
        <div class="col-md-6">
            <div class="table-card"><div class="card-header"><h5 class="mb-0">Expenses</h5></div>
            <div class="table-responsive"><table class="table mb-0">
                @forelse($report['expense'] as $x)<tr><td>{{ $x['name'] }}</td><td class="text-end">{{ $m($x['amount']) }}</td></tr>@empty<tr><td colspan="2" class="text-muted text-center py-3">None</td></tr>@endforelse
                <tr class="fw-bold"><td>Total expenses</td><td class="text-end">{{ $m($report['total_expense']) }}</td></tr>
            </table></div></div>
        </div>
    </div>

    <div class="table-card mt-3">
        <div class="p-3 d-flex justify-content-between align-items-center">
            <span class="h5 mb-0">Net {{ $report['net_profit'] >= 0 ? 'Profit' : 'Loss' }}</span>
            <span class="h4 mb-0 {{ $report['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $m(abs($report['net_profit'])) }}</span>
        </div>
    </div>
</div>
@endsection
