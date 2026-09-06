@extends('layouts.admin')

@section('title', 'Scratch Cards')
@section('header', 'Scratch Cards')

@section('content')
@php
    $status = $filters['status'] ?? '';
    $statusBadge = [
        'issued' => 'secondary',
        'viewed' => 'secondary',
        'scratched' => 'info',
        'reward_generated' => 'warning',
        'reward_credited' => 'success',
        'redeemed' => 'success',
        'expired' => 'danger',
    ];
@endphp

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Scratch Cards</h1>
            <p>Issued scratch-card rewards across all promotions, for support lookups and redemption tracking.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.promotion-engine.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Promotions
            </a>
            <a href="{{ route('admin.promotion-engine.coupons') }}" class="btn btn-outline-primary">
                <i class="fas fa-ticket me-2"></i>Coupon Library
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
                @foreach(['issued' => 'Issued', 'viewed' => 'Viewed', 'scratched' => 'Scratched', 'reward_generated' => 'Reward Generated', 'reward_credited' => 'Reward Credited', 'redeemed' => 'Redeemed', 'expired' => 'Expired'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">User Search</label>
            <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or phone">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.promotion-engine.scratch-cards') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-1">Scratch Cards</h5>
        <div class="text-muted small">One row per issued card. Use this to answer support tickets about reward reveal/redemption state.</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Card</th>
                    <th>Promotion</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Reward</th>
                    <th>Order</th>
                    <th>Issued / Expires</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cards as $card)
                    <tr>
                        <td>
                            <div class="fw-bold">#{{ $card->id }}</div>
                            @if($card->isRevealed())
                                <div class="text-muted small">Revealed</div>
                            @endif
                        </td>
                        <td>{{ $card->promotion?->title ?: 'N/A' }}</td>
                        <td>
                            @if($card->user)
                                <div class="fw-semibold">{{ $card->user->name ?: 'User #'.$card->user_id }}</div>
                                <div class="text-muted small">{{ $card->user->phone ?: $card->user->email }}</div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td><span class="badge bg-{{ $statusBadge[$card->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $card->status)) }}</span></td>
                        <td class="text-muted small">
                            @if($card->reward)
                                {{ $card->reward['title'] ?? ($card->reward['type'] ?? 'Reward pending') }}
                            @else
                                Not revealed
                            @endif
                        </td>
                        <td>
                            @if($card->order_id)
                                <a href="{{ route('admin.orders.show', $card->order_id) }}" target="_blank">#{{ $card->order_id }}</a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-muted small">
                            {{ $card->issued_at?->format('d M Y') ?: '-' }}
                            -
                            {{ $card->expires_at?->format('d M Y') ?: 'No expiry' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">No scratch cards found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $cards->links() }}
    </div>
</div>
@endsection
