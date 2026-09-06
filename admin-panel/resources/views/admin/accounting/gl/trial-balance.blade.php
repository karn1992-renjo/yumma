@extends('layouts.admin')
@section('title', 'Trial Balance')
@section('header', 'Trial Balance')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Trial Balance</h1><p>As of {{ $as_of }}</p></div></div>
    @include('admin.accounting._tabs')

    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-sm-3"><label class="form-label small">As of</label><input type="date" name="as_of" value="{{ $as_of }}" class="form-control form-control-sm"></div>
        <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Apply</button></div>
        <div class="col-sm-3"><a class="btn btn-sm btn-outline-secondary w-100" href="{{ route('admin.accounting.gl.export', ['doc' => 'trial-balance', 'as_of' => $as_of]) }}">Export</a></div>
    </form>

    <div class="table-card">
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Code</th><th>Account</th><th>Type</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
            <tbody>
            @forelse($report['rows'] as $r)
                <tr><td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td class="text-muted small">{{ ucfirst($r['type']) }}</td>
                    <td class="text-end">{{ $r['debit'] > 0 ? $m($r['debit']) : '' }}</td>
                    <td class="text-end">{{ $r['credit'] > 0 ? $m($r['credit']) : '' }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No journal activity.</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr class="fw-bold"><td colspan="3">Total</td>
                <td class="text-end">{{ $m($report['total_debit']) }}</td>
                <td class="text-end">{{ $m($report['total_credit']) }}</td></tr></tfoot>
        </table></div>
    </div>
</div>
@endsection
