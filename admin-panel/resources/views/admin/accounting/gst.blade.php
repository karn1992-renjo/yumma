@extends('layouts.admin')
@section('title', 'Accounting — GST')
@section('header', 'Accounting — GST')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); $qs = ['from' => $from, 'to' => $to]; @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>GST</h1><p>GSTR-1, GSTR-3B and GSTR-8 for the selected period.</p></div></div>
    @include('admin.accounting._tabs')

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm btn-primary" href="{{ route('admin.accounting.export', ['doc' => 'gstr1'] + $qs) }}">GSTR-1 (Excel)</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.accounting.export', ['doc' => 'gstr1-json'] + $qs) }}">GSTR-1 JSON</a>
        <a class="btn btn-sm btn-primary" href="{{ route('admin.accounting.export', ['doc' => 'gstr3b'] + $qs) }}">GSTR-3B (Excel)</a>
        <a class="btn btn-sm btn-primary" href="{{ route('admin.accounting.export', ['doc' => 'gstr8'] + $qs) }}">GSTR-8 (Excel)</a>
    </div>

    <div class="table-card mb-4">
        <div class="card-header"><h5 class="mb-0">GSTR-3B worksheet</h5></div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Section</th><th class="text-end">Taxable value</th><th class="text-end">CGST</th><th class="text-end">SGST</th></tr></thead>
            <tbody>
                <tr><td>3.1(a) Outward taxable (platform services + commission)</td><td class="text-end">{{ $m($gstr3b['outward']['taxable_value']) }}</td><td class="text-end">{{ $m($gstr3b['outward']['cgst']) }}</td><td class="text-end">{{ $m($gstr3b['outward']['sgst']) }}</td></tr>
                <tr><td>3.1.1(i) Supplies u/s 9(5) — tax paid by ECO</td><td class="text-end">{{ $m($gstr3b['eco_9_5']['taxable_value']) }}</td><td class="text-end">{{ $m($gstr3b['eco_9_5']['cgst']) }}</td><td class="text-end">{{ $m($gstr3b['eco_9_5']['sgst']) }}</td></tr>
            </tbody>
        </table></div>
    </div>

    <div class="table-card mb-4">
        <div class="card-header"><h5 class="mb-0">GSTR-1 — B2CS (Table 7)</h5><span class="text-muted small">{{ $gstr1['invoice_count'] }} tax invoices</span></div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Place of supply</th><th class="text-end">Rate %</th><th class="text-end">Taxable</th><th class="text-end">CGST</th><th class="text-end">SGST</th></tr></thead>
            <tbody>
            @forelse($gstr1['b2cs'] as $r)
                <tr><td>{{ $r['place_of_supply'] }}</td><td class="text-end">{{ rtrim(rtrim(number_format($r['rate'],2),'0'),'.') }}</td><td class="text-end">{{ $m($r['taxable_value']) }}</td><td class="text-end">{{ $m($r['cgst']) }}</td><td class="text-end">{{ $m($r['sgst']) }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No tax invoices in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="table-card mb-4">
        <div class="card-header"><h5 class="mb-0">GSTR-1 — B2B commission (Table 4)</h5></div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Restaurant GSTIN</th><th>Restaurant</th><th class="text-end">Taxable</th><th class="text-end">CGST</th><th class="text-end">SGST</th></tr></thead>
            <tbody>
            @forelse($gstr1['b2b_commission'] as $r)
                <tr><td>{{ $r['gstin'] }}</td><td>{{ $r['name'] }}</td><td class="text-end">{{ $m($r['taxable_value']) }}</td><td class="text-end">{{ $m($r['cgst']) }}</td><td class="text-end">{{ $m($r['sgst']) }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No commission invoices settled in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="table-card">
        <div class="card-header"><h5 class="mb-0">GSTR-8 — TCS by supplier</h5></div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Restaurant</th><th>GSTIN</th><th class="text-end">Gross value</th><th class="text-end">CGST TCS</th><th class="text-end">SGST TCS</th><th class="text-end">Total TCS</th></tr></thead>
            <tbody>
            @forelse($gstr8['suppliers'] as $r)
                <tr><td>{{ $r['restaurant'] }}</td><td>{{ $r['gstin'] }}</td><td class="text-end">{{ $m($r['gross_value']) }}</td><td class="text-end">{{ $m($r['cgst']) }}</td><td class="text-end">{{ $m($r['sgst']) }}</td><td class="text-end">{{ $m($r['tcs']) }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No TCS collected in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
