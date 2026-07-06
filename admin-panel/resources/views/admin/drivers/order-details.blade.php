@extends('layouts.admin')
@php $currencySymbol = $currencySymbol ?? App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Order Details')
@section('header', 'Complete Order Details')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Order #{{ $order->order_number }}</h1>
            <p class="text-muted">Driver: {{ $driver->name }}</p>
        </div>
        <a href="{{ route('admin.drivers.orders-history', $driver->id) }}" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i> Back to Orders
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Order Value</div>
            <div class="fw-bold fs-3">{{ $currencySymbol }}{{ number_format($order->total ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Delivery Fee</div>
            <div class="fw-bold fs-3 text-success">{{ $currencySymbol }}{{ number_format($order->delivery_fee ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Driver Income</div>
            <div class="fw-bold fs-3 text-primary">{{ $currencySymbol }}{{ number_format($order->driver_earning ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Admin Revenue</div>
            <div class="fw-bold fs-3 text-warning">{{ $currencySymbol }}{{ number_format($order->admin_commission ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Order Information</h5>
            </div>
            <div class="p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Status</label>
                        <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Payment Method</label>
                        <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $order->payment_method ?? 'N/A')) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Payment Status</label>
                        <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'N/A')) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Delivery Mode</label>
                        <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $order->delivery_payment_mode ?? 'N/A')) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Restaurant</label>
                        <div class="fw-semibold">{{ $order->restaurant->name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Customer</label>
                        <div class="fw-semibold">{{ $order->customer_name ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold text-muted small">Delivery Address</label>
                        <div class="fw-semibold">{{ is_array($order->customer_address) ? ($order->customer_address['address'] ?? json_encode($order->customer_address)) : ($order->delivery_address ?? 'N/A') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Financial Breakdown</h5>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Subtotal</label>
                        <div class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->subtotal ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Customer Taxes & Charges</label>
                        <div class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->tax ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Discount</label>
                        <div class="fw-semibold">-{{ $currencySymbol }}{{ number_format($order->discount ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Platform Commission</label>
                        <div class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->platform_commission ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Online Payment Gateway Fee</label>
                        <div class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->payment_gateway_fee ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">GST on Platform Commission</label>
                        <div class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->gst_on_commission ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                    </div>
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Net Restaurant Earning</label>
                        <div class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->restaurant_earning ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Driver Income</label>
                        <div class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->driver_earning ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Items</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 small">
                    <thead class="bg-light">
                        <tr>
                            <th>Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(is_array($order->items) && count($order->items))
                            @foreach($order->items as $item)
                            <tr>
                                <td>{{ $item['name'] ?? 'Item' }}</td>
                                <td>{{ $item['quantity'] ?? 1 }}</td>
                                <td>{{ $currencySymbol }}{{ number_format($item['price'] ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                            </tr>
                            @endforeach
                        @else
                        <tr>
                            <td colspan="3" class="text-center text-muted">No items data available</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Restaurant Details</h5>
            </div>
            <div class="p-4">
                <div class="mb-3">
                    <div class="fw-semibold">{{ $order->restaurant->name ?? 'N/A' }}</div>
                    <small class="text-muted">{{ $order->restaurant->email ?? '' }}</small>
                </div>
                <div>
                    <small class="text-muted">Address</small>
                    <div class="fw-semibold">{{ $order->restaurant->address ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Transaction Summary</h5>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <small class="text-muted">Payment ID</small>
                        <div class="fw-semibold">{{ $order->payment_id ?? '—' }}</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Cash Collected</small>
                        <div class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->cash_collected_amount ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Delivery OTP</small>
                        <div class="fw-semibold">{{ $order->delivery_otp ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
