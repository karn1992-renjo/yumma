@extends('layouts.admin')

@section('title', 'Branch Order Details')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Order #{{ $order->order_number ?? $order->id }}</h1>
        <p>{{ $branch->name }} order details, customer, restaurant, driver, and branch earnings.</p>
    </div>
    <a href="{{ route('branch.orders') }}" class="btn btn-light">
        <i class="fas fa-arrow-left me-2"></i> Back
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Order Summary</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><div class="text-muted small">Status</div><strong>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</strong></div>
                    <div class="col-md-4"><div class="text-muted small">Payment</div><strong>{{ ucfirst($order->payment_status ?? 'pending') }}</strong></div>
                    <div class="col-md-4"><div class="text-muted small">Created</div><strong>{{ $order->created_at?->format('d M Y H:i') }}</strong></div>
                    <div class="col-md-4"><div class="text-muted small">Subtotal</div><strong>{{ number_format($order->subtotal ?? 0, 2) }}</strong></div>
                    <div class="col-md-4"><div class="text-muted small">Total</div><strong>{{ number_format($order->total ?? 0, 2) }}</strong></div>
                    <div class="col-md-4">
                        <div class="text-muted small">Credited Branch Earnings</div>
                        <strong class="text-success">{{ number_format($order->branch_commission_settled ? $order->branch_commission : 0, 2) }}</strong>
                        @unless($order->branch_commission_settled)
                            <div class="text-muted small">Credits after successful delivery.</div>
                        @endunless
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Items</h5></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Item</th><th>Qty</th><th>Total</th></tr></thead>
                    <tbody>
                    @foreach((array) (is_string($order->items) ? json_decode($order->items, true) : ($order->items ?? [])) as $item)
                        <tr>
                            <td>{{ $item['name'] ?? $item['item_name'] ?? 'Item' }}</td>
                            <td>{{ $item['quantity'] ?? $item['qty'] ?? 1 }}</td>
                            <td>{{ number_format((float) ($item['total'] ?? $item['price'] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Restaurant</h5></div>
            <div class="card-body">
                <div class="fw-bold">{{ $order->restaurant?->name ?? 'N/A' }}</div>
                <div class="text-muted small">{{ $order->restaurant?->address }}</div>
                <div class="small mt-2">{{ $order->restaurant?->phone }}</div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Customer</h5></div>
            <div class="card-body">
                <div class="fw-bold">{{ $order->customer_name ?? $order->customer?->name ?? 'Guest' }}</div>
                <div class="small">{{ $order->customer_phone ?? $order->customer?->phone }}</div>
                <div class="text-muted small mt-2">{{ $order->delivery_address ?? $order->customer_address }}</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Driver</h5></div>
            <div class="card-body">
                @if($order->driver)
                    <div class="fw-bold">{{ $order->driver->name }}</div>
                    <div class="small">{{ $order->driver->phone }}</div>
                    <div class="text-muted small mt-2">{{ $order->driver->vehicle_type }} {{ $order->driver->vehicle_number }}</div>
                @elseif($canAssignDriver)
                    <form action="{{ route('branch.orders.assign-driver', $order) }}" method="POST">
                        @csrf
                        <label class="form-label">Assign Branch Driver</label>
                        <select name="driver_id" class="form-select mb-3" required>
                            <option value="">Select available driver</option>
                            @foreach($availableDrivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->name }} - {{ $driver->phone }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-primary w-100" @disabled($availableDrivers->isEmpty())>
                            <i class="fas fa-user-check me-1"></i> Assign Driver
                        </button>
                        @if($availableDrivers->isEmpty())
                            <div class="form-text text-danger">No eligible branch drivers are available right now.</div>
                        @endif
                    </form>
                @else
                    <div class="text-muted">No driver assigned.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
