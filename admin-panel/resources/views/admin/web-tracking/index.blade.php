@extends('layouts.admin')

@section('title', 'Web Tracking')
@section('header', 'Web Tracking')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Web Tracking</h1>
            <p class="text-muted mb-0">Track frontend, checkout, admin, and restaurant panel visits with country, timezone, local time, and allowed browser location.</p>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card p-3">
            <div class="text-muted small">Today Visits</div>
            <div class="h3 fw-bold mb-0">{{ number_format($summary['today']) }}</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3">
            <div class="text-muted small">Unique Sessions</div>
            <div class="h3 fw-bold mb-0">{{ number_format($summary['unique_sessions']) }}</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3">
            <div class="text-muted small">With GPS</div>
            <div class="h3 fw-bold mb-0">{{ number_format($summary['with_location']) }}</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3">
            <div class="text-muted small">Countries</div>
            <div class="h3 fw-bold mb-0">{{ number_format($summary['countries']) }}</div>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <div class="p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Search</label>
                <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="User, path, URL, IP">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Panel</label>
                <select name="panel" class="form-select">
                    <option value="">All panels</option>
                    @foreach($panels as $panel)
                        <option value="{{ $panel }}" @selected(request('panel') === $panel)>{{ ucfirst(str_replace('_', ' ', $panel)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Country Code</label>
                <input type="text" name="country" class="form-control" value="{{ request('country') }}" placeholder="IN, US">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Filter</button>
                <a href="{{ route('admin.web-tracking.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="table-card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Latest Web Activity</h5>
        <span class="badge bg-primary rounded-3">{{ number_format($tracks->total()) }} records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th>User</th>
                    <th>Panel / Page</th>
                    <th>Country</th>
                    <th>Time</th>
                    <th>Location</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tracks as $track)
                    <tr>
                        <td style="min-width: 180px;">
                            <div class="fw-semibold">{{ $track->user?->name ?? 'Guest' }}</div>
                            <div class="small text-muted">{{ $track->user?->email ?? $track->session_id }}</div>
                        </td>
                        <td style="min-width: 280px;">
                            <span class="badge bg-dark rounded-3">{{ ucfirst(str_replace('_', ' ', $track->panel ?? 'web')) }}</span>
                            <div class="fw-semibold mt-1">{{ $track->path }}</div>
                            @if($track->referrer)
                                <div class="small text-muted text-truncate" style="max-width: 360px;">From: {{ $track->referrer }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $track->country ?: 'Unknown' }}</div>
                            <div class="small text-muted">{{ $track->country_code ?: '-' }}</div>
                        </td>
                        <td style="min-width: 190px;">
                            <div>{{ $track->created_at?->format('d M Y, h:i A') }}</div>
                            <div class="small text-muted">{{ $track->timezone ?: 'Timezone unknown' }}</div>
                            @if($track->local_time)
                                <div class="small text-muted">Local: {{ $track->local_time->format('d M Y, h:i A') }}</div>
                            @endif
                        </td>
                        <td style="min-width: 180px;">
                            @if($track->latitude && $track->longitude)
                                <div class="fw-semibold">{{ $track->latitude }}, {{ $track->longitude }}</div>
                                <div class="small text-muted">
                                    Source: {{ ucfirst(str_replace('_', ' ', $track->metadata['location_source'] ?? 'browser_or_saved_location')) }}
                                </div>
                                <div class="small text-muted">Accuracy: {{ $track->location_accuracy ? number_format((float) $track->location_accuracy) . 'm' : '-' }}</div>
                                <a class="small" target="_blank" href="https://www.google.com/maps?q={{ $track->latitude }},{{ $track->longitude }}">Open map</a>
                            @else
                                <span class="text-muted small">No browser or selected delivery location yet</span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $track->ip_address ?: '-' }}</div>
                            <div class="small text-muted text-truncate" style="max-width: 240px;">{{ $track->user_agent }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fas fa-location-crosshairs fa-3x d-block mb-3 opacity-50"></i>
                            No web tracking records yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        {{ $tracks->links() }}
    </div>
</div>
@endsection
