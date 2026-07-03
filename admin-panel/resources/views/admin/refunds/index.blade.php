@extends('layouts.admin')

@section('title', 'Refund Management')
@section('header', 'Refund Management')
@php
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $currencyStep = number_format(1 / pow(10, $currencyDecimals), $currencyDecimals, '.', '');
@endphp

@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹'); @endphp

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Refund Management</h1>
            <p>Track refund requests and create refunds against orders.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#refundModal">
            <i class="fas fa-rotate-left me-2"></i>Create Refund
        </button>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <form class="row g-3">
            <div class="col-md-9">
                <select name="status" class="form-select">
                    <option value="">All refund statuses</option>
                    @foreach(['pending', 'processing', 'completed', 'failed'] as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
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
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($refunds as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}">#{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer?->name ?? $order->customer_name }}</td>
                        <td>{{ $order->restaurant?->name ?? 'N/A' }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($order->refund_amount ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                        <td><span class="badge badge-info">{{ ucfirst($order->refund_status) }}</span></td>
                        <td>{{ $order->refund_reason }}</td>
                        <td>{{ $order->updated_at?->format('d M Y h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No refunds found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">{{ $refunds->withQueryString()->links() }}</div>
</div>

<div class="modal fade" id="refundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('admin.refunds.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Create Refund Against Order</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Order ID</label>
                    <input class="form-control" name="order_id" type="number" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Refund Amount</label>
                    <input class="form-control" name="refund_amount" type="number" step="{{ $currencyStep }}" placeholder="Leave empty for full eligible amount">
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" name="refund_reason" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Create Refund</button>
            </div>
        </form>
    </div>
</div>
@endsection
