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
            <thead><tr><th>Order</th><th>Restaurant</th><th>Screen / Seat</th><th>Status</th><th>Total</th></tr></thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>#{{ $order->order_number ?? $order->id }}</td>
                        <td>{{ $order->restaurant?->name ?? 'N/A' }}</td>
                        <td>{{ $order->screen?->name ?? '—' }} @if($order->seat_label) · Seat {{ $order->seat_label }} @endif</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $order->status ?? 'pending')) }}</td>
                        <td>{{ number_format($order->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">No theatre orders yet — the ordering flow ships in a later phase.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
