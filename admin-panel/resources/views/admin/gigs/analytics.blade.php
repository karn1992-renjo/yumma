@extends('layouts.admin')

@section('title', 'Driver Gig Analytics')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div><h1>Driver Gig Analytics</h1><p>Operational capacity, forecast, payout, and risk snapshot.</p></div>
        <form method="GET" action="{{ route('admin.gigs.analytics') }}" class="d-flex gap-2">
            <input type="date" name="date" class="form-control" value="{{ $selectedDate }}">
            <button class="btn btn-outline-primary" type="submit">View</button>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    @foreach([
        'Fill Rate' => number_format((float) ($operations['fill_rate'] ?? 0), 1) . '%',
        'Open Seats' => number_format((int) ($operations['open'] ?? 0)),
        'Forecast Orders' => number_format((int) ($operations['forecasted_orders'] ?? 0)),
        'Recommended Capacity' => number_format((int) ($operations['recommended_capacity'] ?? 0)),
        'Fraud Signals' => number_format((int) ($operations['open_fraud_signals'] ?? 0)),
        'Pending Payouts' => number_format((int) ($operations['pending_payouts'] ?? 0)),
    ] as $label => $value)
        <div class="col-lg-2 col-md-4 col-sm-6"><div class="stat-card"><p class="text-muted mb-1 small">{{ $label }}</p><h3 class="mb-0 fw-bold">{{ $value }}</h3></div></div>
    @endforeach
</div>

<div class="table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div><h5 class="mb-0">Capacity Heatmap</h5><div class="small text-muted">Area and hour-wise fill pressure for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</div></div>
        <form action="{{ route('admin.gigs.forecast') }}" method="POST">@csrf <input type="hidden" name="date" value="{{ $selectedDate }}"><button class="btn btn-sm btn-outline-primary" type="submit">Refresh Forecast</button></form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Area</th><th>Hour</th><th>Capacity</th><th>Booked</th><th>Open</th><th>Fill</th></tr></thead>
            <tbody>
                @forelse(($heatmap ?? []) as $slot)
                    @php $fillRate = (float) ($slot['fill_rate'] ?? 0); @endphp
                    <tr>
                        <td>{{ $slot['area_name'] ?? 'Global' }}</td><td>{{ $slot['hour'] ?? '-' }}</td><td>{{ $slot['capacity'] ?? 0 }}</td><td>{{ $slot['booked'] ?? 0 }}</td><td>{{ $slot['available'] ?? 0 }}</td>
                        <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px;min-width:120px"><div class="progress-bar {{ $fillRate >= 90 ? 'bg-danger' : ($fillRate >= 65 ? 'bg-warning' : 'bg-success') }}" style="width:{{ min(100, max(0, $fillRate)) }}%"></div></div><span class="small text-muted">{{ number_format($fillRate, 1) }}%</span></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">No heatmap data for this date.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header"><h5 class="mb-0">Forecast Tuning</h5><div class="small text-muted">Controls used by the hourly demand-forecast and ML-prediction jobs.</div></div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form action="{{ route('admin.gigs.forecast-settings.update') }}" method="POST" class="row g-3 align-items-end">
            @csrf
            <input type="hidden" name="redirect_date" value="{{ $selectedDate }}">
            <div class="col-md-3">
                <label class="form-label">Forecast Lookback (days)</label>
                <input type="number" min="7" max="180" name="gig_forecast_lookback_days" class="form-control" value="{{ old('gig_forecast_lookback_days', $forecastSettings['gig_forecast_lookback_days']) }}">
                <div class="form-text">Historical order window for the primary demand forecast. Default 28.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">ML Lookback (days)</label>
                <input type="number" min="7" max="365" name="gig_ml_forecast_lookback_days" class="form-control" value="{{ old('gig_ml_forecast_lookback_days', $forecastSettings['gig_ml_forecast_lookback_days']) }}">
                <div class="form-text">Historical order window for the ML-ensemble prediction. Default 56.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Target Orders / Driver / Hour</label>
                <input type="number" min="1" max="50" name="gig_target_orders_per_driver_per_hour" class="form-control" value="{{ old('gig_target_orders_per_driver_per_hour', $forecastSettings['gig_target_orders_per_driver_per_hour']) }}">
                <div class="form-text">Used to derive recommended driver capacity from forecasted orders. Default 3.</div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Save Forecast Settings</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4"><a class="table-card d-block p-4 text-decoration-none h-100" href="{{ route('admin.gigs.payout-approvals') }}"><h5>Pending Payout Review</h5><p class="text-muted mb-0">Open incentive approvals from completed gigs.</p></a></div>
    <div class="col-md-4"><a class="table-card d-block p-4 text-decoration-none h-100" href="{{ route('admin.gigs.fraud-signals') }}"><h5>Fraud Signals</h5><p class="text-muted mb-0">Review risk flags and clear confirmed issues.</p></a></div>
    <div class="col-md-4"><a class="table-card d-block p-4 text-decoration-none h-100" href="{{ route('admin.gigs.disputes') }}"><h5>Driver Disputes</h5><p class="text-muted mb-0">Resolve contested outcomes and payout disputes.</p></a></div>
</div>
@endsection