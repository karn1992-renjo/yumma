@extends('layouts.admin')

@section('title', 'GST Reports')
@section('header', 'GST Reports')

@section('content')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); @endphp

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>GST Reports</h1>
            <p class="text-muted small mb-0">GSTR-1 style summary from issued tax invoices. B2CS (Table 7) and HSN summary (Table 12).</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.gst-reports.gstr1', request()->only('from', 'to')) }}" class="btn btn-primary"><i class="fas fa-file-excel me-2"></i>Export GSTR-1 (Excel)</a>
            <a href="{{ route('admin.gst-reports.gstr1-json', request()->only('from', 'to')) }}" class="btn btn-outline-secondary">JSON</a>
        </div>
    </div>
</div>

@unless($gstEnabled)
    <div class="alert alert-warning">GST invoicing is currently disabled in <a href="{{ route('admin.settings.business') }}">Settings &rarr; Business</a>. This report only covers orders issued as tax invoices.</div>
@endunless

<div class="table-card mb-4">
    <form method="GET" class="p-3 row g-2 align-items-end">
        <div class="col-sm-3"><label class="form-label">From</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
        <div class="col-sm-3"><label class="form-label">To</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
        <div class="col-sm-3"><button class="btn btn-primary">Apply</button></div>
    </form>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Tax invoices', number_format($report['invoice_count'])],
        ['Taxable value', $m($report['totals']['taxable_value'])],
        ['CGST', $m($report['totals']['cgst'])],
        ['SGST', $m($report['totals']['sgst'])],
        ['Invoice value', $m($report['totals']['invoice_value'])],
    ] as [$label, $value])
        <div class="col-6 col-md">
            <div class="table-card p-3">
                <div class="text-muted small">{{ $label }}</div>
                <div class="h5 mb-0">{{ $value }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="table-card mb-4">
    <div class="card-header"><h5 class="mb-0">B2CS — by place of supply &amp; rate</h5></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Place of supply</th><th class="text-end">Rate %</th><th class="text-end">Taxable value</th><th class="text-end">CGST</th><th class="text-end">SGST</th></tr></thead>
            <tbody>
                @forelse($report['b2cs'] as $r)
                    <tr>
                        <td>{{ $r['place_of_supply'] }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($r['rate'], 2), '0'), '.') }}</td>
                        <td class="text-end">{{ $m($r['taxable_value']) }}</td>
                        <td class="text-end">{{ $m($r['cgst']) }}</td>
                        <td class="text-end">{{ $m($r['sgst']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No tax invoices in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="table-card">
    <div class="card-header"><h5 class="mb-0">HSN summary</h5></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>HSN/SAC</th><th class="text-end">Rate %</th><th class="text-end">Qty</th><th class="text-end">Taxable value</th><th class="text-end">CGST</th><th class="text-end">SGST</th></tr></thead>
            <tbody>
                @forelse($report['hsn'] as $r)
                    <tr>
                        <td>{{ $r['hsn'] }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($r['rate'], 2), '0'), '.') }}</td>
                        <td class="text-end">{{ $r['quantity'] }}</td>
                        <td class="text-end">{{ $m($r['taxable_value']) }}</td>
                        <td class="text-end">{{ $m($r['cgst']) }}</td>
                        <td class="text-end">{{ $m($r['sgst']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
