@extends('layouts.admin')
@section('title', 'Cash Flow')
@section('header', 'Cash Flow')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Cash Flow</h1><p>{{ $from }} to {{ $to }} &mdash; movement on bank / cash accounts.</p></div></div>
    @include('admin.accounting._tabs')

    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-sm-3"><label class="form-label small">From</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
        <div class="col-sm-3"><label class="form-label small">To</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
        <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Apply</button></div>
        <div class="col-sm-3"><a class="btn btn-sm btn-outline-secondary w-100" href="{{ route('admin.accounting.gl.export', ['doc' => 'cash-flow', 'from' => $from, 'to' => $to]) }}">Export</a></div>
    </form>

    <div class="table-card">
        <div class="table-responsive"><table class="table mb-0">
            <tr><td>Opening cash</td><td class="text-end">{{ $m($report['opening_cash']) }}</td></tr>
            <tr><td>Inflows</td><td class="text-end">{{ $m($report['inflows']) }}</td></tr>
            <tr><td>Outflows</td><td class="text-end">({{ $m($report['outflows']) }})</td></tr>
            <tr class="fw-bold"><td>Net change</td><td class="text-end">{{ $m($report['net_change']) }}</td></tr>
            <tr class="fw-bold"><td>Closing cash</td><td class="text-end">{{ $m($report['closing_cash']) }}</td></tr>
        </table></div>
    </div>

    @if(!empty($report['by_activity']))
    <div class="table-card mt-3">
        <div class="card-header"><h5 class="mb-0">By activity</h5></div>
        <div class="table-responsive"><table class="table mb-0">
            @foreach($report['by_activity'] as $activity => $amount)
                <tr><td>{{ ucfirst($activity) }}</td><td class="text-end">{{ $m($amount) }}</td></tr>
            @endforeach
        </table></div>
    </div>
    @endif
</div>
@endsection
