@extends('layouts.admin')

@section('title', 'Ad Campaigns')
@section('header', 'Ad Campaigns')

@section('content')
@php
    $status = $filters['status'] ?? '';
    $statusBadge = [
        'draft' => 'secondary',
        'pending_review' => 'warning',
        'active' => 'success',
        'paused' => 'secondary',
        'budget_exhausted' => 'warning',
        'rejected' => 'danger',
        'ended' => 'secondary',
    ];
@endphp

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Ad Campaigns</h1>
            <p>Restaurant-funded CPC campaigns competing for sponsored placement.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.ads-engine.click-log') }}" class="btn btn-outline-primary">
                <i class="fas fa-mouse-pointer me-2"></i>Click Log
            </a>
            <a href="{{ route('admin.ads-engine.fraud-signals') }}" class="btn btn-outline-secondary">
                <i class="fas fa-shield-halved me-2"></i>Fraud Signals
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Pending Review</p>
            <h3 class="mb-0 fw-bold">{{ $stats['pending_review'] }}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Active Campaigns</p>
            <h3 class="mb-0 fw-bold">{{ $stats['active'] }}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Open Fraud Signals</p>
            <h3 class="mb-0 fw-bold">{{ $stats['open_fraud_signals'] }}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Total Billed Spend</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['total_spend'], App\Models\AppSetting::currencyDecimals()) }}</h3>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Restaurant</label>
            <select name="restaurant_id" class="form-select">
                <option value="">All restaurants</option>
                @foreach($restaurants as $restaurant)
                    <option value="{{ $restaurant->id }}" @selected(($filters['restaurant_id'] ?? null) == $restaurant->id)>{{ $restaurant->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach(['draft' => 'Draft', 'pending_review' => 'Pending Review', 'active' => 'Active', 'paused' => 'Paused', 'budget_exhausted' => 'Budget Exhausted', 'rejected' => 'Rejected', 'ended' => 'Ended'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.ads-engine.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-1">Campaigns</h5>
        <div class="text-muted small">Approve or reject campaigns pending review before they can start spending.</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Restaurant</th>
                    <th>Bid / Budget</th>
                    <th>Spend</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($campaigns as $campaign)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $campaign->name }}</div>
                            <div class="text-muted small">#{{ $campaign->id }}</div>
                        </td>
                        <td>{{ $campaign->restaurant?->name ?: 'Restaurant #'.$campaign->restaurant_id }}</td>
                        <td class="text-muted small">
                            Max CPC {{ number_format((float) $campaign->max_cpc, App\Models\AppSetting::currencyDecimals()) }}<br>
                            Daily {{ $campaign->daily_budget !== null ? number_format((float) $campaign->daily_budget, App\Models\AppSetting::currencyDecimals()) : 'Unlimited' }} /
                            Total {{ $campaign->total_budget !== null ? number_format((float) $campaign->total_budget, App\Models\AppSetting::currencyDecimals()) : 'Unlimited' }}
                        </td>
                        <td class="text-muted small">
                            {{ number_format((float) $campaign->spent_total, App\Models\AppSetting::currencyDecimals()) }} total<br>
                            {{ number_format($campaign->spentToday(), App\Models\AppSetting::currencyDecimals()) }} today
                        </td>
                        <td class="text-muted small">
                            {{ $campaign->starts_at?->format('d M Y') }}
                            -
                            {{ $campaign->ends_at?->format('d M Y') ?: 'No end' }}
                        </td>
                        <td><span class="badge bg-{{ $statusBadge[$campaign->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $campaign->status)) }}</span></td>
                        <td class="text-end">
                            @if($campaign->status === 'pending_review')
                                <div class="d-flex justify-content-end gap-2">
                                    <form action="{{ route('admin.ads-engine.approve', $campaign) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.ads-engine.reject', $campaign) }}" method="POST" onsubmit="return confirm('Reject this campaign?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">No campaigns found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $campaigns->links() }}
    </div>
</div>
@endsection
