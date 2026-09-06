@extends('layouts.admin')
@section('title', 'Accounting — Documents')
@section('header', 'Accounting — Documents')

@section('content')
@include('admin.settings._style')
@php $qs = ['from' => $from, 'to' => $to]; @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Compliance Documents</h1><p>Every statutory export for {{ $from }} to {{ $to }}. Files are offline-utility-ingestible, not portal-validated returns.</p></div></div>
    @include('admin.accounting._tabs')

    <div class="row g-3">
        @foreach([
            ['GSTR-1', 'B2CS + B2B commission + HSN', 'gstr1'],
            ['GSTR-3B', 'Outward + Sec 9(5) worksheet', 'gstr3b'],
            ['GSTR-8', 'TCS by supplier', 'gstr8'],
            ['Form 26Q', 'Quarterly TDS — 194-O + 194-C', 'form26q'],
            ['Restaurant settlements', 'Itemised payout statements', 'settlement-restaurant'],
            ['Driver settlements', 'Itemised payout statements', 'settlement-driver'],
            ['Tax ledger', 'All accruals for the period', 'ledger'],
        ] as $row)
            <div class="col-md-4">
                <div class="table-card p-3 h-100 d-flex flex-column">
                    <div class="fw-bold">{{ $row[0] }}</div>
                    <div class="text-muted small flex-grow-1">{{ $row[1] }}</div>
                    <a class="btn btn-sm btn-primary mt-2" href="{{ route('admin.accounting.export', ['doc' => $row[2]] + $qs) }}">Download</a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
