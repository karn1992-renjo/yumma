@extends('layouts.admin')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Order History')
@section('header', 'Driver Order History')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>{{ $driver->name }}</h1>
            <p class="text-muted">Review and filter driver orders</p>
        </div>
        <a href="{{ route('admin.drivers.show', $driver->id) }}" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i> Back to Driver
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Total Orders</div>
            <div class="fw-bold fs-3">{{ $stats['total_orders'] }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Delivered Earnings</div>
            <div class="fw-bold fs-3 text-success">{{ $currencySymbol }}{{ number_format($stats['total_earnings'], App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Cancelled Orders</div>
            <div class="fw-bold fs-3 text-danger">{{ $stats['cancelled_orders'] }}</div>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <h5 class="mb-0 fw-bold">Filters</h5>
    </div>
    <div class="p-4">
        <form method="GET" action="{{ route('admin.drivers.orders-history', $driver->id) }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="preparing" {{ request('status') == 'preparing' ? 'selected' : '' }}>Preparing</option>
                    <option value="ready" {{ request('status') == 'ready' ? 'selected' : '' }}>Ready</option>
                    <option value="on_the_way" {{ request('status') == 'on_the_way' ? 'selected' : '' }}>On the way</option>
                    <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Delivery Fee Range</label>
                <div class="input-group">
                    <input type="number" name="min_fee" step="0.01" class="form-control" placeholder="Min" value="{{ request('min_fee') }}">
                    <input type="number" name="max_fee" step="0.01" class="form-control" placeholder="Max" value="{{ request('max_fee') }}">
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Restaurant</th>
                    <th>Order Value</th>
                    <th>Delivery Fee</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td><strong>#{{ $order->order_number }}</strong></td>
                    <td>{{ $order->customer_name ?? 'N/A' }}</td>
                    <td>{{ $order->restaurant->name ?? 'N/A' }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($order->total ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($order->delivery_fee ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                    <td>
                        @php
                            $statusClass = [
                                'pending' => 'warning',
                                'confirmed' => 'info',
                                'preparing' => 'info',
                                'ready' => 'primary',
                                'on_the_way' => 'info',
                                'delivered' => 'success',
                                'cancelled' => 'danger',
                                'returned' => 'danger',
                            ][$order->status] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                    </td>
                    <td>{{ $order->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('admin.drivers.order-details', [$driver->id, $order->id]) }}" class="btn btn-sm btn-outline-primary">
                            View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No orders found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">
        {{ $orders->withQueryString()->links() }}
    </div>
</div>
@endsection
