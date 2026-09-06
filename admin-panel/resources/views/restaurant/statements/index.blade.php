@extends('layouts.restaurant')

@section('title', 'Statements')

@section('content')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); @endphp

<div class="page-header">
    <div>
        <h1>Settlement Statements</h1>
        <p>Commission, GST on commission (your ITC), TDS &amp; TCS — {{ $from }} to {{ $to }}</p>
    </div>
</div>

@unless($gstOn)
    <div class="alert alert-info">The platform is not running GST invoicing right now, so these figures may be zero.</div>
@endunless

<form method="GET" class="row g-2 align-items-end mb-4">
    <div class="col-sm-3"><label class="form-label small">From</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
    <div class="col-sm-3"><label class="form-label small">To</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
    <div class="col-sm-2"><button class="btn btn-sm btn-primary w-100">Apply</button></div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><small class="text-muted d-block">Gross sales</small><span class="h4 fw-bold">{{ $m($agg->gross ?? 0) }}</span></div></div>
    <div class="col-md-3"><div class="stat-card"><small class="text-muted d-block">Commission</small><span class="h4 fw-bold">{{ $m($agg->commission ?? 0) }}</span></div></div>
    <div class="col-md-3"><div class="stat-card"><small class="text-muted d-block">GST on commission (ITC)</small><span class="h4 fw-bold text-success">{{ $m($agg->commission_gst ?? 0) }}</span></div></div>
    <div class="col-md-3"><div class="stat-card"><small class="text-muted d-block">Net received</small><span class="h4 fw-bold">{{ $m($agg->net ?? 0) }}</span></div></div>
</div>

<div class="stat-card mb-4">
    <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>
            <small class="text-muted d-block">GST paid by the platform on your food under Sec 9(5) ({{ $from }}–{{ $to }})</small>
            <span class="fw-bold">{{ $m($eco95) }}</span>
            <div class="small text-muted">You do not collect or report this — the platform (ECO) does.</div>
        </div>
        <div class="text-end">
            <small class="text-muted d-block">TDS 194-O withheld this FY ({{ $fy }})</small>
            <span class="fw-bold">{{ $m($tds194o->tds_ytd ?? 0) }}</span>
            @if($tds194o)
                <div><a class="btn btn-sm btn-outline-primary mt-1" href="{{ route('restaurant.statements.form16a', ['fy' => $fy]) }}"><i class="fas fa-file-download me-1"></i> Form 16A</a></div>
            @endif
        </div>
    </div>
</div>

<div class="stat-card">
    <h5 class="fw-bold mb-3">Payout cycles</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Date</th><th>Status</th>
                <th class="text-end">Gross</th><th class="text-end">Commission</th><th class="text-end">Comm. GST</th>
                <th class="text-end">Pre-tax</th><th class="text-end">TDS 194-O</th><th class="text-end">TCS</th><th class="text-end">Net</th>
            </tr></thead>
            <tbody>
            @forelse($payouts as $p)
                <tr>
                    <td class="small">{{ $p->created_at->format('d M Y') }}</td>
                    <td><span class="badge bg-{{ $p->status === 'completed' ? 'success' : ($p->status === 'pending' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($p->status) }}</span></td>
                    <td class="text-end">{{ $m($p->gross_amount) }}</td>
                    <td class="text-end">{{ $m($p->platform_commission) }}</td>
                    <td class="text-end text-success">{{ $m($p->gst_on_commission) }}</td>
                    <td class="text-end">{{ $m($p->pre_tax_amount ?: $p->net_amount) }}</td>
                    <td class="text-end">{{ $m($p->tds_amount) }}</td>
                    <td class="text-end">{{ $m($p->tcs_amount) }}</td>
                    <td class="text-end fw-bold">{{ $m($p->net_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No payouts in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $payouts->links() }}</div>
</div>
@endsection
