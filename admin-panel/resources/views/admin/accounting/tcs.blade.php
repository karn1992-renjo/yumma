@extends('layouts.admin')
@section('title', 'Accounting — TCS')
@section('header', 'Accounting — TCS')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); $qs = ['from' => $from, 'to' => $to]; @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>GST TCS (Sec 52)</h1><p>Collected on registered restaurants&rsquo; supplies where the restaurant &mdash; not the platform under 9(5) &mdash; is liable for GST.</p></div></div>
    @include('admin.accounting._tabs')

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm btn-primary" href="{{ route('admin.accounting.export', ['doc' => 'gstr8'] + $qs) }}">GSTR-8 (Excel)</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.accounting.export', ['doc' => 'gstr8-json'] + $qs) }}">GSTR-8 JSON</a>
    </div>

    <div class="table-card">
        <div class="card-header"><h5 class="mb-0">Supplier-wise TCS</h5><span class="text-muted small">Total {{ $m($gstr8['total_tcs']) }}</span></div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Restaurant</th><th>GSTIN</th><th class="text-end">Gross value</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">Total TCS</th></tr></thead>
            <tbody>
            @forelse($gstr8['suppliers'] as $r)
                <tr><td>{{ $r['restaurant'] }}</td><td>{{ $r['gstin'] }}</td><td class="text-end">{{ $m($r['gross_value']) }}</td><td class="text-end">{{ $m($r['cgst']) }}</td><td class="text-end">{{ $m($r['sgst']) }}</td><td class="text-end">{{ $m($r['tcs']) }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No TCS collected in this period (expected under pure Sec 9(5)).</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
