@extends('layouts.admin')

@section('title', 'Return Management')
@section('header', 'Return Management')
@php
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $currencyStep = number_format(1 / pow(10, $currencyDecimals), $currencyDecimals, '.', '');
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1>Return Management</h1>
        <p>Review and process customer return requests filed after delivery.</p>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <form class="row g-3">
            <div class="col-md-9">
                <select name="status" class="form-select">
                    <option value="">All return statuses</option>
                    @foreach(App\Models\Order::getReturnStatuses() as $value => $label)
                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Restaurant</th>
                    <th>Order Total</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($returns as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}">#{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer?->name ?? $order->customer_name }}</td>
                        <td>{{ $order->restaurant?->name ?? 'N/A' }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($order->total, $currencyDecimals) }}</td>
                        <td>{{ $order->return_reason }}</td>
                        <td><span class="badge badge-info">{{ App\Models\Order::getReturnStatuses()[$order->return_status] ?? ucfirst($order->return_status) }}</span></td>
                        <td>{{ $order->updated_at?->format('d M Y h:i A') }}</td>
                        <td>
                            @if($order->return_status === 'requested')
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal{{ $order->id }}">Approve</button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $order->id }}">Reject</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No return requests found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">{{ $returns->withQueryString()->links() }}</div>
</div>

@foreach($returns as $order)
    @if($order->return_status === 'requested')
        <div class="modal fade" id="approveModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('admin.returns.approve', $order) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Approve Return for Order #{{ $order->order_number }}</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Customer's stated reason: {{ $order->return_reason }}</p>
                        <div class="mb-3">
                            <label class="form-label">Refund Amount</label>
                            <input class="form-control" name="return_amount" type="number" step="{{ $currencyStep }}" max="{{ $order->total }}" value="{{ $order->total }}" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-success" type="submit">Approve &amp; Refund</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="rejectModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('admin.returns.reject', $order) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Return for Order #{{ $order->order_number }}</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger" type="submit">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
@endsection
