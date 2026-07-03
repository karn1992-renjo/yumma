@extends('layouts.admin')

@section('title', 'Branch Orders')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>{{ $branch->name }} Orders</h1>
        <p>Orders from this branch and restaurants inside assigned delivery zones are shown.</p>
    </div>
    @if($capabilities['orders_export'] ?? false)
        <a href="{{ route('branch.orders.export', request()->query()) }}" class="btn btn-light">
            <i class="fas fa-file-excel me-2"></i> Export Excel
        </a>
    @endif
</div>
<div class="card">
    <div class="card-header">
        <form class="row g-2">
            <div class="col-md-4"><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Order number, customer, phone"></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\Order::getStatuses() as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Order</th><th>Restaurant</th><th>Customer</th><th>Driver</th><th>Status</th><th>Total</th><th>Credited Earnings</th><th>Created</th><th></th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $order->restaurant?->name }}</td>
                    <td>{{ $order->customer_name }}<div class="text-muted small">{{ $order->customer_phone }}</div></td>
                    <td>{{ $order->driver?->name ?? 'Unassigned' }}</td>
                    <td><span class="badge bg-secondary">{{ str_replace('_', ' ', ucfirst($order->status)) }}</span></td>
                    <td>{{ number_format($order->total, 2) }}</td>
                    <td>
                        {{ number_format($order->branch_commission_settled ? $order->branch_commission : 0, 2) }}
                        @unless($order->branch_commission_settled)
                            <div class="text-muted small">Pending delivery</div>
                        @endunless
                    </td>
                    <td>{{ $order->created_at->format('d M Y H:i') }}</td>
                    <td class="text-end">
                        <a href="{{ route('branch.orders.show', $order) }}" class="btn btn-sm btn-light">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-4">No branch orders found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
