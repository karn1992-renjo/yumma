@extends('layouts.admin')
@section('title', 'Accounting — Settlements')
@section('header', 'Accounting — Settlements')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); $qs = ['from' => $from, 'to' => $to]; @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Settlement Statements</h1><p>Itemised payout breakdown incl. commission GST, TDS and TCS.</p></div></div>
    @include('admin.accounting._tabs')

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm {{ $type === 'restaurant' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.accounting.settlements', ['type' => 'restaurant'] + $qs) }}">Restaurants</a>
        <a class="btn btn-sm {{ $type === 'driver' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.accounting.settlements', ['type' => 'driver'] + $qs) }}">Drivers</a>
        <a class="btn btn-sm btn-primary ms-auto" href="{{ route('admin.accounting.export', ['doc' => 'settlement-' . $type] + $qs) }}">Export (Excel)</a>
    </div>

    <div class="table-card">
        <div class="table-responsive"><table class="table mb-0">
            <thead>
                @if($type === 'driver')
                    <tr><th>Payout</th><th>Driver</th><th>Date</th><th class="text-end">Gross</th><th class="text-end">Commission</th><th class="text-end">Pre-tax</th><th class="text-end">TDS 194C</th><th class="text-end">Net</th><th>Status</th></tr>
                @else
                    <tr><th>Payout</th><th>Restaurant</th><th>Date</th><th class="text-end">Gross</th><th class="text-end">Commission</th><th class="text-end">Comm. GST</th><th class="text-end">Gateway</th><th class="text-end">Pre-tax</th><th class="text-end">TDS 194O</th><th class="text-end">TCS</th><th class="text-end">Net</th><th>Status</th></tr>
                @endif
            </thead>
            <tbody>
            @forelse($settlement['rows'] as $r)
                <tr>
                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit($r['uuid'], 8, '') }}</td>
                    <td>{{ $r['party'] }}</td>
                    <td>{{ $r['created_at'] }}</td>
                    <td class="text-end">{{ $m($r['gross_amount']) }}</td>
                    <td class="text-end">{{ $m($r['platform_commission']) }}</td>
                    @if($type !== 'driver')
                        <td class="text-end">{{ $m($r['gst_on_commission']) }}</td>
                        <td class="text-end">{{ $m($r['payment_gateway_fee']) }}</td>
                    @endif
                    <td class="text-end">{{ $m($r['pre_tax_amount']) }}</td>
                    <td class="text-end">{{ $m($r['tds_amount']) }}</td>
                    @if($type !== 'driver')<td class="text-end">{{ $m($r['tcs_amount']) }}</td>@endif
                    <td class="text-end fw-bold">{{ $m($r['net_amount']) }}</td>
                    <td>{{ ucfirst($r['status']) }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center text-muted py-4">No settlements in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
