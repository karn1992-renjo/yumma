@extends('layouts.admin')

@section('title', 'Promotion Fraud Attempts')
@section('header', 'Promotion Fraud Attempts')

@section('content')
@php
    $filters = $filters ?? [];
@endphp
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1>Promotion Fraud Attempts</h1>
            <p>Blocked promotion attempts by configured fraud and abuse rules.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.promotion-engine.fraud-attempts.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-download me-2"></i>Export CSV</a>
            <a href="{{ route('admin.promotion-engine.analytics') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Analytics</a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Blocked Attempts</div><div class="fs-3 fw-bold">{{ number_format($summary['attempts'] ?? 0) }}</div></div></div>
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Users</div><div class="fs-3 fw-bold">{{ number_format($summary['users'] ?? 0) }}</div></div></div>
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Devices</div><div class="fs-3 fw-bold">{{ number_format($summary['devices'] ?? 0) }}</div></div></div>
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Addresses</div><div class="fs-3 fw-bold">{{ number_format($summary['addresses'] ?? 0) }}</div></div></div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Promotion</label><select name="promotion_id" class="form-select"><option value="">All promotions</option>@foreach($promotions as $promotion)<option value="{{ $promotion->id }}" @selected(($filters['promotion_id'] ?? null) == $promotion->id)>{{ $promotion->title }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Restaurant</label><select name="restaurant_id" class="form-select"><option value="">All restaurants</option>@foreach($restaurants as $restaurant)<option value="{{ $restaurant->id }}" @selected(($filters['restaurant_id'] ?? null) == $restaurant->id)>{{ $restaurant->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Reason</label><select name="reason_code" class="form-select"><option value="">All reasons</option>@foreach($reasonCodes as $code)<option value="{{ $code }}" @selected(($filters['reason_code'] ?? null) === $code)>{{ $code }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Apply</button><a href="{{ route('admin.promotion-engine.fraud-attempts') }}" class="btn btn-outline-secondary">Reset</a></div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Time</th><th>Promotion</th><th>User</th><th>Restaurant</th><th>Rule</th><th>Reason</th><th>Device</th><th>Address</th><th>Payment</th></tr></thead>
            <tbody>
                @forelse($attempts as $attempt)
                    <tr>
                        <td class="text-muted small">{{ $attempt->created_at?->format('d M Y H:i') }}</td>
                        <td>{{ $attempt->promotion?->title ?? ('#'.$attempt->promotion_id) }}<div class="small text-muted">{{ $attempt->coupon_code ?: 'No coupon' }}</div></td>
                        <td>{{ $attempt->user?->name ?? ('#'.$attempt->user_id) }}<div class="small text-muted">{{ $attempt->user?->phone ?: $attempt->user?->email }}</div></td>
                        <td>{{ $attempt->restaurant?->name ?? ($attempt->restaurant_id ? '#'.$attempt->restaurant_id : 'N/A') }}</td>
                        <td><span class="badge bg-warning text-dark">{{ $attempt->rule_key }}</span></td>
                        <td><div class="fw-semibold">{{ $attempt->reason_code }}</div><div class="small text-muted">{{ $attempt->reason }}</div></td>
                        <td class="small text-muted">{{ $attempt->device_id ?: '-' }}</td>
                        <td class="small text-muted">{{ $attempt->address_hash ?: '-' }}</td>
                        <td class="small text-muted">{{ $attempt->payment_instrument_hash ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No blocked attempts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $attempts->links() }}</div>
</div>
@endsection