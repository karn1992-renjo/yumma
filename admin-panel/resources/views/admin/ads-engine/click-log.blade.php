@extends('layouts.admin')

@section('title', 'Ad Click Log')
@section('header', 'Ad Click Log')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Ad Click Log</h1>
            <p>Every tracked click, billed or not, with the price actually charged.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.ads-engine.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Campaigns
            </a>
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
            <label class="form-label">Billed</label>
            <select name="billed" class="form-select">
                <option value="">All</option>
                <option value="yes" @selected(($filters['billed'] ?? '') === 'yes')>Billed</option>
                <option value="no" @selected(($filters['billed'] ?? '') === 'no')>Unbilled</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.ads-engine.click-log') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Restaurant</th>
                    <th>Surface</th>
                    <th>Price Paid</th>
                    <th>Billed</th>
                    <th>User</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clicks as $click)
                    <tr>
                        <td>{{ $click->campaign?->name ?: 'Campaign #'.$click->restaurant_ad_campaign_id }}</td>
                        <td>{{ $click->restaurant?->name ?: 'Restaurant #'.$click->restaurant_id }}</td>
                        <td class="text-muted small">{{ ucwords(str_replace('_', ' ', $click->surface)) }}</td>
                        <td>{{ number_format((float) $click->price_paid, App\Models\AppSetting::currencyDecimals()) }}</td>
                        <td><span class="badge bg-{{ $click->is_billed ? 'success' : 'secondary' }}">{{ $click->is_billed ? 'Billed' : 'Unbilled' }}</span></td>
                        <td class="text-muted small">{{ $click->user_id ? '#'.$click->user_id : 'Guest' }}</td>
                        <td class="text-muted small">{{ $click->created_at?->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">No clicks found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $clicks->links() }}
    </div>
</div>
@endsection
