@extends('layouts.admin')
@section('title', 'Balance Sheet')
@section('header', 'Balance Sheet')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Balance Sheet</h1><p>As of {{ $as_of }} &mdash; from the general ledger.</p></div></div>
    @include('admin.accounting._tabs')

    @unless($accounting_on)
        <div class="alert alert-warning">The general ledger is off (<code>accounting_enabled</code>). Turn it on in <a href="{{ route('admin.settings.business') }}">Settings &rarr; Business</a> and run <code>php artisan ledger:reconcile</code> to backfill.</div>
    @endunless

    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-sm-3"><label class="form-label small">As of</label><input type="date" name="as_of" value="{{ $as_of }}" class="form-control form-control-sm"></div>
        <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Apply</button></div>
        <div class="col-sm-2"><a class="btn btn-sm btn-outline-secondary w-100" href="{{ route('admin.accounting.gl.export', ['doc' => 'balance-sheet', 'as_of' => $as_of]) }}">Excel</a></div>
        <div class="col-sm-2"><a class="btn btn-sm btn-outline-secondary w-100" href="{{ route('admin.accounting.gl.export', ['doc' => 'balance-sheet', 'as_of' => $as_of, 'format' => 'pdf']) }}">PDF</a></div>
    </form>

    <div class="alert {{ $report['balanced'] ? 'alert-success' : 'alert-warning' }} py-2">
        {{ $report['balanced'] ? 'Balanced.' : 'Not fully journalled — the "Unreconciled" line shows what needs manual journals (bank, capital, opex, fixed assets).' }}
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="table-card">
                <div class="card-header"><h5 class="mb-0">Assets</h5></div>
                <div class="table-responsive"><table class="table mb-0">
                    @foreach($report['assets'] as $x)
                        <tr><td>{{ $x['name'] }}</td><td class="text-end">{{ $m($x['amount']) }}</td></tr>
                    @endforeach
                    <tr class="fw-bold"><td>Total assets</td><td class="text-end">{{ $m($report['total_assets']) }}</td></tr>
                </table></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-card mb-3">
                <div class="card-header"><h5 class="mb-0">Liabilities</h5></div>
                <div class="table-responsive"><table class="table mb-0">
                    @foreach($report['liabilities'] as $x)
                        <tr><td>{{ $x['name'] }}</td><td class="text-end">{{ $m($x['amount']) }}</td></tr>
                    @endforeach
                </table></div>
            </div>
            <div class="table-card">
                <div class="card-header"><h5 class="mb-0">Equity</h5></div>
                <div class="table-responsive"><table class="table mb-0">
                    @foreach($report['equity'] as $x)
                        <tr><td>{{ $x['name'] }}</td><td class="text-end">{{ $m($x['amount']) }}</td></tr>
                    @endforeach
                    <tr class="fw-bold"><td>Total liabilities + equity</td><td class="text-end">{{ $m($report['total_liabilities_equity']) }}</td></tr>
                </table></div>
            </div>
        </div>
    </div>
</div>
@endsection
