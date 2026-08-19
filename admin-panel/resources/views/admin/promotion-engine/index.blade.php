@extends('layouts.admin')

@section('title', 'Promotion Engine')
@section('header', 'Promotion Engine')

@section('content')
@php
    $currencySymbol = \App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = \App\Models\AppSetting::currencyDecimals();
    $statusBadge = [
        'active' => 'success',
        'draft' => 'secondary',
        'paused' => 'warning',
    ];
@endphp

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Promotion Engine</h1>
            <p>Create and manage platform and restaurant promotion rules.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.promotion-engine.analytics') }}" class="btn btn-outline-primary">
                <i class="fas fa-chart-line me-2"></i>Analytics
            </a>
            <a href="{{ route('admin.promotion-engine.coupons') }}" class="btn btn-outline-primary">
                <i class="fas fa-ticket-alt me-2"></i>Coupon Library
            </a>
            <a href="{{ route('admin.promotion-engine.logs') }}" class="btn btn-outline-secondary">
                <i class="fas fa-clipboard-list me-2"></i>Logs
            </a>
            <a href="{{ route('admin.promotion-engine.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Promotion
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="table-card p-4 h-100">
            <div class="text-muted small">All Promotions</div>
            <div class="fs-3 fw-bold">{{ $summary['promotion_count'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 h-100">
            <div class="text-muted small">Active</div>
            <div class="fs-3 fw-bold text-success">{{ $summary['active_promotion_count'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 h-100">
            <div class="text-muted small">Orders Generated</div>
            <div class="fs-3 fw-bold">{{ $summary['orders_generated'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 h-100">
            <div class="text-muted small">Discount Given</div>
            <div class="fs-3 fw-bold">{{ $currencySymbol }}{{ number_format((float) $summary['discount_given'], $currencyDecimals) }}</div>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <div class="p-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="fw-bold mb-1">Promotions</h5>
            <div class="text-muted small">Engine campaigns only. User-earned coupons live in Coupon Library.</div>
        </div>
        <div class="btn-group">
            <a class="btn btn-sm {{ !$status ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.promotion-engine.index') }}">All</a>
            @foreach(['active', 'draft', 'paused'] as $filter)
                <a class="btn btn-sm {{ $status === $filter ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.promotion-engine.index', ['status' => $filter]) }}">{{ ucfirst($filter) }}</a>
            @endforeach
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Promotion</th>
                    <th>Owner</th>
                    <th>Mode</th>
                    <th>Reward</th>
                    <th>Usage</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($promotions as $promotion)
                    @php $reward = $promotion->rewards ?? []; @endphp
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $promotion->title }}</div>
                            <div class="text-muted small">{{ ucwords(str_replace('_', ' ', $promotion->promotion_type)) }}</div>
                        </td>
                        <td>{{ ucfirst($promotion->owner_type) }}</td>
                        <td>{{ ucfirst($promotion->application_mode) }}</td>
                        <td>
                            @if(($reward['type'] ?? null) === 'percentage')
                                {{ number_format((float) ($reward['value'] ?? 0), 0) }}% OFF
                            @elseif(isset($reward['value']))
                                {{ $currencySymbol }}{{ number_format((float) $reward['value'], $currencyDecimals) }} OFF
                            @else
                                {{ ucwords(str_replace('_', ' ', $reward['type'] ?? 'Custom')) }}
                            @endif
                        </td>
                        <td>{{ $promotion->used_count }} / {{ $promotion->total_usage_limit ?: 'Unlimited' }}</td>
                        <td><span class="badge bg-{{ $statusBadge[$promotion->status] ?? 'secondary' }}">{{ ucfirst($promotion->status) }}</span></td>
                        <td class="text-end">
                            <div class="btn-group">
                                <a href="{{ route('admin.promotion-engine.edit', $promotion) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.promotion-engine.toggle', $promotion) }}" method="POST">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" type="submit" title="Toggle status">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.promotion-engine.destroy', $promotion) }}" method="POST"
                                      onsubmit="return confirm('Delete promotion &quot;{{ addslashes($promotion->title) }}&quot;? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete promotion">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">No promotions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $promotions->links() }}
    </div>
</div>
@endsection
