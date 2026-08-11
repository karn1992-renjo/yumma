@extends('layouts.admin')

@section('title', 'Bank Partner Settlements')
@section('header', 'Bank Partner Settlements')

@section('content')
@php
    $currencySymbol = \App\Models\AppSetting::getValue('currency_symbol', 'Rs');
    $currencyDecimals = \App\Models\AppSetting::currencyDecimals();
    $filters = $filters ?? [];
@endphp
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1>Bank Partner Settlements</h1>
            <p>Partner-funded promotion liabilities ready for finance reconciliation.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.promotion-engine.bank-partner-settlements.api', request()->query()) }}" class="btn btn-outline-secondary"><i class="fas fa-code me-2"></i>JSON API</a>
            <a href="{{ route('admin.promotion-engine.bank-partner-settlements.export', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-download me-2"></i>Export CSV</a>
            <a href="{{ route('admin.promotion-engine.analytics') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Analytics</a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Orders</div><div class="fs-3 fw-bold">{{ number_format($summary['orders'] ?? 0) }}</div></div></div>
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Redemptions</div><div class="fs-3 fw-bold">{{ number_format($summary['redemptions'] ?? 0) }}</div></div></div>
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Partner Liability</div><div class="fs-3 fw-bold">{{ $currencySymbol }}{{ number_format((float) ($summary['partner_liability'] ?? 0), $currencyDecimals) }}</div></div></div>
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Discount Given</div><div class="fs-3 fw-bold">{{ $currencySymbol }}{{ number_format((float) ($summary['discount_given'] ?? 0), $currencyDecimals) }}</div></div></div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Promotion</label><select name="promotion_id" class="form-select"><option value="">All promotions</option>@foreach($promotions as $promotion)<option value="{{ $promotion->id }}" @selected(($filters['promotion_id'] ?? null) == $promotion->id)>{{ $promotion->title }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Restaurant</label><select name="restaurant_id" class="form-select"><option value="">All restaurants</option>@foreach($restaurants as $restaurant)<option value="{{ $restaurant->id }}" @selected(($filters['restaurant_id'] ?? null) == $restaurant->id)>{{ $restaurant->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Partner</label><select name="partner_name" class="form-select"><option value="">All partners</option>@foreach($partners as $partner)<option value="{{ $partner }}" @selected(($filters['partner_name'] ?? null) === $partner)>{{ $partner }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Apply</button><a href="{{ route('admin.promotion-engine.bank-partner-settlements') }}" class="btn btn-outline-secondary">Reset</a></div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Time</th><th>Partner</th><th>Order</th><th>Promotion</th><th>Restaurant</th><th>Coupon</th><th>Discount</th><th>Partner Liability</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="text-muted small">{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                        <td class="fw-semibold">{{ $row->partner_name ?: 'Bank partner' }}</td>
                        <td>{{ $row->order_number ?: '#'.$row->order_id }}</td>
                        <td>{{ $row->promotion_title ?: '#'.$row->promotion_id }}</td>
                        <td>{{ $row->restaurant_name ?: '#'.$row->restaurant_id }}</td>
                        <td>{{ $row->coupon_code ?: 'N/A' }}</td>
                        <td>{{ $currencySymbol }}{{ number_format((float) $row->discount_amount, $currencyDecimals) }}</td>
                        <td class="fw-bold">{{ $currencySymbol }}{{ number_format((float) $row->partner_liability_amount, $currencyDecimals) }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($row->settlement_status ?? 'pending') }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No bank partner liabilities found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection