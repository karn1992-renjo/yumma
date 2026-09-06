@extends('layouts.admin')

@section('title', 'Call History')
@section('header', 'Call History')

@section('content')
<div class="page-header">
    <div>
        <h1>Call History</h1>
        <p>Every masked/click-to-call attempt placed through Exotel, including graceful raw-number fallbacks.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Total Calls</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['total']) }}</h3>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Today</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['today']) }}</h3>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Raw Fallbacks</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['raw_fallback']) }}</h3>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Order Number</label>
            <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="ORD...">
        </div>
        <div class="col-md-3">
            <label class="form-label">Direction</label>
            <select name="direction" class="form-select">
                <option value="">All</option>
                <option value="dial_in" @selected(($filters['direction'] ?? '') === 'dial_in')>Dial-in</option>
                <option value="click_to_call" @selected(($filters['direction'] ?? '') === 'click_to_call')>Click-to-call</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Provider</label>
            <select name="provider" class="form-select">
                <option value="">All</option>
                <option value="exotel" @selected(($filters['provider'] ?? '') === 'exotel')>Exotel (masked)</option>
                <option value="raw_fallback" @selected(($filters['provider'] ?? '') === 'raw_fallback')>Raw fallback</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.call-history.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Direction</th>
                    <th>Leg</th>
                    <th>Provider</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Recording</th>
                    <th>Initiated</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->order?->order_number ?? '#'.$log->order_id }}</td>
                        <td><span class="badge bg-secondary">{{ $log->direction === 'dial_in' ? 'Dial-in' : 'Click-to-call' }}</span></td>
                        <td class="text-muted small">{{ ucfirst($log->leg_from) }} &rarr; {{ ucfirst($log->leg_to) }}</td>
                        <td><span class="badge bg-{{ $log->provider_used === 'exotel' ? 'success' : 'warning' }}">{{ $log->provider_used === 'exotel' ? 'Masked' : 'Raw fallback' }}</span></td>
                        <td class="text-muted small">{{ $log->status ?? '&mdash;' }}</td>
                        <td class="text-muted small">{{ $log->duration_seconds ? gmdate('i:s', $log->duration_seconds) : '&mdash;' }}</td>
                        <td>
                            @if($log->recording_url)
                                <a href="{{ $log->recording_url }}" target="_blank" rel="noopener">Play</a>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $log->initiated_at?->format('d M Y H:i') ?? $log->created_at?->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">No calls logged yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $logs->links() }}
    </div>
</div>
@endsection
