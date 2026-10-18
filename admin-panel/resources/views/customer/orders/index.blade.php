@extends('layouts.app')

@section('title', 'My Orders')

@section('styles')
<style>
    .order-card {
        transition: all 0.2s ease;
        border-radius: 14px;
        overflow: hidden;
        background: white;
        border: 1px solid #E8E8EE;
        box-shadow: 0 7px 18px rgba(15, 23, 42, 0.06);
        margin-bottom: 16px;
    }

    .order-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.1);
    }

    .order-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px 0;
    }

    .status-chip {
        display: inline-block;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 11.5px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .status-chip.status-cancelled { color: #E11D28; background: #FFECEC; }
    .status-chip.status-delivered { color: #169B3A; background: #E8F8EA; }
    .status-chip.status-live { color: #11832D; background: #E6F7E7; }
    .status-chip.status-confirmed { color: #2563EB; background: #EAF2FF; }

    .order-timestamp {
        font-size: 12px;
        color: var(--app-muted, #6B7280);
        font-weight: 500;
    }

    .order-card-body {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        text-decoration: none;
        color: inherit;
    }

    .order-restaurant-thumb {
        width: 74px;
        height: 74px;
        border-radius: 13px;
        object-fit: cover;
        background: #F3F4F6;
        flex-shrink: 0;
    }

    .order-card-info {
        flex: 1;
        min-width: 0;
    }

    .order-restaurant-name {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-weight: 800;
        font-size: 15px;
        color: var(--app-ink, #111827);
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .order-meta {
        font-size: 12.5px;
        color: var(--app-muted, #6B7280);
        font-weight: 500;
        margin-bottom: 4px;
    }

    .order-total {
        font-size: 14px;
        font-weight: 800;
        color: var(--app-ink, #111827);
    }

    .order-chevron {
        color: #9CA3AF;
        flex-shrink: 0;
    }

    .order-card-actions {
        display: flex;
        gap: 10px;
        padding: 0 16px 16px;
    }

    .order-action-btn {
        flex: 1;
        text-align: center;
        padding: 10px 14px;
        border-radius: 999px;
        font-weight: 800;
        font-size: 13px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: transform 0.15s ease;
    }

    .order-action-btn:hover {
        transform: translateY(-1px);
    }

    .order-action-btn.outlined {
        background: transparent;
        border: 1.5px solid var(--primary);
        color: var(--primary);
    }

    .order-action-btn.filled {
        background: color-mix(in srgb, var(--primary) 13%, white);
        color: var(--primary);
    }

    .orders-pagination .pagination {
        flex-wrap: wrap;
        justify-content: center;
        gap: 6px;
        margin-bottom: 0;
    }

    .orders-pagination .page-link {
        border-radius: 10px;
        min-width: 38px;
        text-align: center;
    }

    @media (max-width: 767.98px) {
        .order-card .row > [class*="col-"] {
            margin-bottom: 12px;
        }

        .order-card .row > [class*="col-"]:last-child {
            margin-bottom: 0;
        }

        .order-card .btn {
            width: 100%;
            margin: 4px 0 !important;
        }
    }
</style>
@endsection

@section('content')
@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
@endphp
<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-1">My Orders</h2>
                    <p class="text-muted">Track and manage your food orders</p>
                </div>
                <a href="{{ route('home') }}" class="btn btn-primary rounded-pill">
                    <i class="fas fa-utensils me-2"></i> Order Food
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center p-3" style="border-radius: var(--app-card-radius, 24px);">
                <h3 class="fw-bold text-primary mb-0">{{ $orders->total() }}</h3>
                <small class="text-muted">Total Orders</small>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center p-3" style="border-radius: var(--app-card-radius, 24px);">
                <h3 class="fw-bold text-success mb-0">{{ $orders->where('status', 'delivered')->count() }}</h3>
                <small class="text-muted">Delivered</small>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center p-3" style="border-radius: var(--app-card-radius, 24px);">
                <h3 class="fw-bold text-warning mb-0">{{ $orders->whereIn('status', ['pending', 'confirmed', 'preparing'])->count() }}</h3>
                <small class="text-muted">In Progress</small>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center p-3" style="border-radius: var(--app-card-radius, 24px);">
                <h3 class="fw-bold text-danger mb-0">{{ $orders->where('status', 'cancelled')->count() }}</h3>
                <small class="text-muted">Cancelled</small>
            </div>
        </div>
    </div>

    <!-- Orders List -->
    @if($orders->isEmpty())
    <div class="card shadow-sm text-center py-5">
        <div class="card-body">
            <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
            <h4>No Orders Yet</h4>
            <p class="text-muted">You haven't placed any orders yet.</p>
            <a href="{{ route('home') }}" class="btn btn-primary rounded-pill mt-3">
                <i class="fas fa-utensils me-2"></i> Start Ordering
            </a>
        </div>
    </div>
    @else
    <div>
        @foreach($orders as $order)
        @php
            $terminal = in_array($order->status, ['delivered', 'completed', 'cancelled']);
            $chipClass = match(true) {
                $order->status === 'cancelled' => 'status-cancelled',
                in_array($order->status, ['delivered', 'completed']) => 'status-delivered',
                in_array($order->status, ['on_the_way', 'picked_up', 'out_for_delivery']) => 'status-live',
                default => 'status-confirmed',
            };
            $restaurantLogo = $order->restaurant?->logo_image ? \App\Services\MediaStorage::url($order->restaurant->logo_image) : null;
        @endphp
        <div class="order-card">
            <div class="order-card-top">
                <span class="status-chip {{ $chipClass }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                <span class="order-timestamp">{{ $order->created_at->format('d M Y, h:i A') }}</span>
            </div>
            <a href="{{ route('customer.orders.show', $order->id) }}" class="order-card-body">
                @if($restaurantLogo)
                    <img src="{{ $restaurantLogo }}" alt="" class="order-restaurant-thumb">
                @else
                    <div class="order-restaurant-thumb d-flex align-items-center justify-content-center">
                        <i class="fas fa-store text-muted"></i>
                    </div>
                @endif
                <div class="order-card-info">
                    <div class="order-restaurant-name">{{ $order->restaurant->name ?? 'Order #'.substr($order->order_number, -8) }}</div>
                    <div class="order-meta">#{{ substr($order->order_number, -8) }} &middot; {{ $order->order_items_count }} {{ \Illuminate\Support\Str::plural('item', $order->order_items_count) }}</div>
                    <div class="order-total">{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</div>
                </div>
                <i class="fas fa-chevron-right order-chevron"></i>
            </a>
            <div class="order-card-actions">
                <a href="{{ route('customer.orders.show', $order->id) }}" class="order-action-btn outlined">View Details</a>
                @if(in_array($order->status, ['delivered', 'completed']))
                <form action="{{ route('customer.orders.reorder', $order->id) }}" method="POST" class="d-flex flex-grow-1">
                    @csrf
                    <button type="submit" class="order-action-btn filled w-100">Reorder</button>
                </form>
                @elseif(!$terminal)
                <a href="{{ route('customer.orders.track', $order->id) }}" class="order-action-btn filled">Track Order</a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    
    <div class="orders-pagination d-flex justify-content-center mt-4">
        {{ $orders->links() }}
    </div>
    @endif
</div>
@endsection
