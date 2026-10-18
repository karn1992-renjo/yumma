@extends('layouts.admin')

@section('title', 'Payment Recovery Queue')
@section('header', 'Payment Recovery Queue')

@php $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol(); @endphp

@section('content')
<div class="page-header">
    <div>
        <h1>Payment Recovery Queue</h1>
        <p>Payments the gateway confirmed as charged, but where order creation failed even after automatic retries. Re-verify and replay order creation from here.</p>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <form class="row g-3">
            <div class="col-md-9">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach(['pending', 'resolved', 'not_paid', 'failed'] as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
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
                    <th>Customer</th>
                    <th>Gateway</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->user?->name ?? 'User #' . $row->user_id }}</td>
                        <td>{{ ucfirst($row->payment_method) }}</td>
                        <td class="small text-muted">
                            {{ $row->payment_id ?: '-' }}
                            @if($row->gateway_reference)
                                <br>{{ $row->gateway_reference }}
                            @endif
                        </td>
                        <td>{{ $row->amount !== null ? $currencySymbol . number_format((float) $row->amount, App\Models\AppSetting::currencyDecimals()) : '-' }}</td>
                        <td>
                            @if($row->status === 'resolved')
                                <span class="badge badge-success">Resolved</span>
                                @if($row->resolvedOrder)
                                    <br><a href="{{ route('admin.orders.show', $row->resolvedOrder) }}" class="small">Order #{{ $row->resolvedOrder->order_number }}</a>
                                @endif
                            @elseif($row->status === 'not_paid')
                                <span class="badge badge-secondary">Not Paid</span>
                            @elseif($row->status === 'failed')
                                <span class="badge badge-danger">Failed</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                            @if($row->last_error && $row->status !== 'resolved')
                                <div class="small text-muted mt-1" title="{{ $row->last_error }}">{{ Illuminate\Support\Str::limit($row->last_error, 60) }}</div>
                            @endif
                        </td>
                        <td>{{ $row->created_at?->format('d M Y h:i A') }}</td>
                        <td class="text-end">
                            @if($row->status !== 'resolved')
                                <form method="POST" action="{{ route('admin.payment-recovery.resolve', $row) }}" onsubmit="return confirm('Re-verify this payment with the gateway and, if still paid, create the order now?');">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">Verify &amp; Create Order</button>
                                </form>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No payment recovery entries</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">{{ $rows->withQueryString()->links() }}</div>
</div>
@endsection
