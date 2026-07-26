@extends('layouts.admin')

@section('title', 'Coupon Library')
@section('header', 'Coupon Library')

@section('content')
@php
    $status = $filters['status'] ?? '';
@endphp

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Coupon Library</h1>
            <p>Generated, migrated, and user-earned coupon codes from the promotion engine.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.promotion-engine.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Promotions
            </a>
            <a href="{{ route('admin.promotion-engine.logs') }}" class="btn btn-outline-primary">
                <i class="fas fa-clipboard-list me-2"></i>Engine Logs
            </a>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Promotion</label>
            <select name="promotion_id" class="form-select">
                <option value="">All promotions</option>
                @foreach($promotions as $promotion)
                    <option value="{{ $promotion->id }}" @selected(($filters['promotion_id'] ?? null) == $promotion->id)>{{ $promotion->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'used' => 'Used', 'unused' => 'Unused', 'expired' => 'Expired'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Code Search</label>
            <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="SAVE100">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.promotion-engine.coupons') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-1">Coupons</h5>
        <div class="text-muted small">User-owned rewards are listed here, not on the main promotion index.</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Promotion</th>
                    <th>Assigned User</th>
                    <th>Usage</th>
                    <th>Validity</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($coupons as $coupon)
                    @php
                        $expired = $coupon->ends_at && $coupon->ends_at->isPast();
                        $limitReached = $coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit;
                        $badge = ! $coupon->is_active || $expired || $limitReached ? 'secondary' : 'success';
                        $label = $expired ? 'Expired' : ($limitReached ? 'Used' : ($coupon->is_active ? 'Active' : 'Inactive'));
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $coupon->code }}</div>
                            <div class="text-muted small">#{{ $coupon->id }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $coupon->promotion?->title ?: 'N/A' }}</div>
                            <div class="text-muted small">{{ ucwords(str_replace('_', ' ', $coupon->promotion?->promotion_type ?? '')) }}</div>
                        </td>
                        <td>
                            @if($coupon->user)
                                <div class="fw-semibold">{{ $coupon->user->name ?: 'User #'.$coupon->user_id }}</div>
                                <div class="text-muted small">{{ $coupon->user->phone ?: $coupon->user->email }}</div>
                            @else
                                <span class="text-muted">Any eligible user</span>
                            @endif
                        </td>
                        <td>{{ $coupon->used_count }} / {{ $coupon->usage_limit ?: 'Unlimited' }}</td>
                        <td class="text-muted small">
                            {{ $coupon->starts_at?->format('d M Y') ?: 'Now' }}
                            -
                            {{ $coupon->ends_at?->format('d M Y') ?: 'No end' }}
                        </td>
                        <td><span class="badge bg-{{ $badge }}">{{ $label }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">No coupons found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $coupons->links() }}
    </div>
</div>
@endsection
