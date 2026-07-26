@extends('layouts.admin')

@section('title', 'POS Dashboard')
@section('header', 'POS Dashboard')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
@endphp

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card p-3">
            <div class="text-muted fw-semibold">Today POS Revenue</div>
            <div class="h3 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($summary['today_revenue'], $currencyDecimals) }}</div>
            <small>{{ number_format($summary['today_orders']) }} orders today</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card p-3">
            <div class="text-muted fw-semibold">Total POS Revenue</div>
            <div class="h3 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($summary['total_revenue'], $currencyDecimals) }}</div>
            <small>{{ number_format($summary['total_orders']) }} total POS orders</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card p-3">
            <div class="text-muted fw-semibold">Cash Revenue</div>
            <div class="h3 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($summary['cash_revenue'], $currencyDecimals) }}</div>
            <small>Cash/COD POS payments</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card p-3">
            <div class="text-muted fw-semibold">Online Revenue</div>
            <div class="h3 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($summary['online_revenue'], $currencyDecimals) }}</div>
            <small>Card/UPI/wallet POS payments</small>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-bold">Store</label>
                <select name="store_id" class="form-select">
                    <option value="">All stores</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
            @if($storeId)
                <div class="col-md-2">
                    <a href="{{ route('admin.pos.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            @endif
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Recent POS Orders</h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Store</th>
                            <th>Payment</th>
                            <th>Total</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td>
                                    <div class="fw-bold">#{{ $order->order_number }}</div>
                                    <div class="small text-muted">{{ ucfirst($order->order_type ?? 'takeaway') }}</div>
                                </td>
                                <td>{{ $order->restaurant?->name ?? '-' }}</td>
                                <td>{{ strtoupper($order->payment_method ?? '-') }}</td>
                                <td class="fw-bold">{{ $currencySymbol }}{{ number_format((float) $order->total, $currencyDecimals) }}</td>
                                <td>{{ $order->created_at?->format('d M, h:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-5">No POS orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Top POS Stores</h5>
            </div>
            <div class="card-body">
                @forelse($topStores as $store)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <div class="fw-bold">{{ $store->name }}</div>
                            <div class="small text-muted">{{ number_format($store->pos_orders_count) }} POS orders</div>
                        </div>
                        <div class="fw-bold">{{ $currencySymbol }}{{ number_format((float) ($store->pos_revenue ?? 0), $currencyDecimals) }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No POS store performance yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
