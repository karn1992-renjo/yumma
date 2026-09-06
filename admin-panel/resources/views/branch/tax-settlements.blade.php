@extends('layouts.admin')

@section('title', 'Branch Tax & Settlements')

@section('content')
@php
    $m = fn ($v) => $symbol . number_format((float) $v, $decimals);
    $kindLabels = [
        'gst_9_5' => 'GST — Sec 9(5) food (platform-borne)',
        'gst_service' => 'GST — platform services (18%)',
        'gst_commission' => 'GST — commission (18%)',
        'tcs' => 'GST TCS — Sec 52',
        'tds_194o' => 'TDS — Sec 194-O',
        'tds_194c' => 'TDS — Sec 194-C',
        'gig_cess' => 'Gig-worker welfare cess',
    ];
@endphp

<div class="page-header">
    <div>
        <h1>{{ $branch->name }} — Tax &amp; Settlements</h1>
        <p>Tax accrued on orders routed through this branch. {{ $from }} to {{ $to }}</p>
    </div>
</div>

<div class="alert {{ $branchTan ? 'alert-success' : 'alert-secondary' }} py-2">
    Branch TAN: <strong>{{ $branchTan ?: 'not set' }}</strong>
    @unless($branchTan) — add it under <a href="{{ route('branch.settings') }}">Settings</a> to deduct TDS for this branch. @endunless
</div>

@unless($gstOn)
    <div class="alert alert-info">Platform GST invoicing is currently off, so most rows will be zero.</div>
@endunless

<form method="GET" class="row g-2 align-items-end mb-4">
    <div class="col-sm-3"><label class="form-label small">From</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
    <div class="col-sm-3"><label class="form-label small">To</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
    <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Apply</button></div>
</form>

@forelse($byKind as $kind => $rows)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0">{{ $kindLabels[$kind] ?? $kind }}</h5>
            <span class="fw-bold">{{ $m($rows->sum('amount')) }}</span>
        </div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Period</th><th class="text-end">Orders</th><th class="text-end">Taxable</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr>
                    <td>{{ $r->period }}</td>
                    <td class="text-end">{{ number_format($r->n) }}</td>
                    <td class="text-end">{{ $m($r->taxable) }}</td>
                    <td class="text-end">{{ $m($r->cgst) }}</td>
                    <td class="text-end">{{ $m($r->sgst) }}</td>
                    <td class="text-end fw-bold">{{ $m($r->amount) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
@empty
    <div class="alert alert-secondary">No tax entries for this branch in the selected period.</div>
@endforelse
@endsection
