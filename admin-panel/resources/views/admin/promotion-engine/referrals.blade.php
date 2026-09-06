@extends('layouts.admin')

@section('title', 'Referral Ledger')
@section('header', 'Referral Ledger')

@section('content')
@php
    $status = $filters['status'] ?? '';
    $statusBadge = [
        'registered' => 'secondary',
        'qualified' => 'warning',
        'credited' => 'success',
    ];
@endphp

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Referral Ledger</h1>
            <p>Referrer &rarr; referred-user chains and their qualification/payout status.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.promotion-engine.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Promotions
            </a>
            <a href="{{ route('admin.promotion-engine.scratch-cards') }}" class="btn btn-outline-primary">
                <i class="fas fa-gift me-2"></i>Scratch Cards
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
                @foreach(['registered' => 'Registered', 'qualified' => 'Qualified', 'credited' => 'Credited'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">User Search</label>
            <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Referrer or referred user">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.promotion-engine.referrals') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-1">Referrals</h5>
        <div class="text-muted small">One row per referral relationship, from signup through bonus payout.</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Referrer</th>
                    <th>Referred User</th>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Bonus</th>
                    <th>Qualified Order</th>
                    <th>Qualified / Credited</th>
                </tr>
            </thead>
            <tbody>
                @forelse($referrals as $referral)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $referral->referrer?->name ?: 'User #'.$referral->referrer_id }}</div>
                            <div class="text-muted small">{{ $referral->referrer?->phone ?: $referral->referrer?->email }}</div>
                        </td>
                        <td>
                            @if($referral->referredUser)
                                <div class="fw-semibold">{{ $referral->referredUser->name ?: 'User #'.$referral->referred_user_id }}</div>
                                <div class="text-muted small">{{ $referral->referredUser->phone ?: $referral->referredUser->email }}</div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $referral->referral_code }}</td>
                        <td><span class="badge bg-{{ $statusBadge[$referral->status] ?? 'secondary' }}">{{ ucfirst($referral->status) }}</span></td>
                        <td class="text-muted small">
                            @if($referral->amount)
                                {{ number_format((float) $referral->amount, App\Models\AppSetting::currencyDecimals()) }}
                            @elseif($referral->points)
                                {{ $referral->points }} pts
                            @else
                                -
                            @endif
                            <div>{{ ucwords(str_replace('_', ' ', $referral->bonus_type ?? '')) }}</div>
                        </td>
                        <td>
                            @if($referral->qualified_order_id)
                                <a href="{{ route('admin.orders.show', $referral->qualified_order_id) }}" target="_blank">#{{ $referral->qualified_order_id }}</a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-muted small">
                            {{ $referral->qualified_at?->format('d M Y') ?: '-' }}
                            /
                            {{ $referral->credited_at?->format('d M Y') ?: '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">No referrals found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $referrals->links() }}
    </div>
</div>
@endsection
