@extends('layouts.admin')

@section('title', 'Promotion Logs')
@section('header', 'Promotion Logs')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Promotion Logs</h1>
            <p>Decision audit trail for listing, calculation, coupon validation, checkout, and AI actions.</p>
        </div>
        <a href="{{ route('admin.promotion-engine.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Promotion Engine
        </a>
    </div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Promotion</label>
            <select name="promotion_id" class="form-select">
                <option value="">All promotions</option>
                @foreach($promotions as $promotion)
                    <option value="{{ $promotion->id }}" @selected(($filters['promotion_id'] ?? null) == $promotion->id)>{{ $promotion->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Restaurant</label>
            <select name="restaurant_id" class="form-select">
                <option value="">All restaurants</option>
                @foreach($restaurants as $restaurant)
                    <option value="{{ $restaurant->id }}" @selected(($filters['restaurant_id'] ?? null) == $restaurant->id)>{{ $restaurant->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Event</label>
            <select name="event_type" class="form-select">
                <option value="">All events</option>
                @foreach($eventTypes as $eventType)
                    <option value="{{ $eventType }}" @selected(($filters['event_type'] ?? null) === $eventType)>{{ $eventType }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.promotion-engine.logs') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-1">Engine Decision Logs</h5>
        <div class="text-muted small">Use this during the wrapper release to monitor mismatches and invalid coupon reasons.</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Event</th><th>Promotion</th><th>Coupon</th><th>Restaurant</th><th>Decision</th><th>Time</th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $result = $log->result ?? [];
                        $invalid = collect($result['invalid_reasons'] ?? [])->pluck('reason')->filter()->take(2)->implode('; ');
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $log->event_type }}</td>
                        <td>{{ $log->promotion?->title ?: 'N/A' }}</td>
                        <td>{{ $log->coupon_code ?: 'N/A' }}</td>
                        <td>{{ $log->restaurant_id ?: 'N/A' }}</td>
                        <td>
                            <div>Discount: {{ number_format((float) ($result['discount'] ?? 0), 2) }}</div>
                            @if($invalid)
                                <div class="text-danger small">{{ $invalid }}</div>
                            @else
                                <div class="text-muted small">Applied: {{ $result['applied_count'] ?? 0 }}</div>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $log->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No logs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $logs->links() }}</div>
</div>
@endsection
