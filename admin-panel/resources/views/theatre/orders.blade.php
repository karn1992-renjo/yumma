@extends('layouts.admin')

@section('title', 'Theatre Orders')

@section('content')
<div class="page-header">
    <h1>Orders</h1>
    <p>Every order placed to a seat at {{ $theatre->name }}, across every mapped stall.</p>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Order</th><th>Restaurant</th><th>Screen / Seat</th><th>When</th><th>Status</th><th>Total</th></tr></thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>#{{ $order->order_number ?? $order->id }}</td>
                        <td>{{ $order->restaurant?->name ?? 'N/A' }}</td>
                        <td>{{ $order->screen?->name ?? '—' }} @if($order->seat_label) · Seat {{ $order->seat_label }} @endif</td>
                        <td>
                            @if($order->delivery_timing === 'interval' && $order->scheduled_for)
                                <span class="badge bg-warning text-dark">Interval {{ $order->scheduled_for->format('g:i A') }}</span>
                                @if($order->isHeldForInterval())
                                    {{-- Paid for, but deliberately not shown to the counter yet. --}}
                                    <div class="text-muted small">Held from the kitchen</div>
                                @endif
                            @else
                                <span class="text-muted small">Now</span>
                            @endif
                        </td>
                        <td>{{ ucfirst(str_replace('_', ' ', $order->status ?? 'pending')) }}</td>
                        <td>{{ number_format($order->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-5 text-muted">No theatre orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
