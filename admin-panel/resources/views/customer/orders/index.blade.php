@extends('layouts.app')

@section('title', 'My Orders')

@section('styles')
<style>
    .order-card {
        transition: all 0.3s ease;
        border-radius: 20px;
        overflow: hidden;
        background: white;
        border: 1px solid rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    
    .order-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }
    
    .order-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-delivered { background: #D1FAE5; color: #065F46; }
    .status-pending { background: #FEF3C7; color: #92400E; }
    .status-confirmed { background: #DBEAFE; color: #1E40AF; }
    .status-preparing { background: #E0E7FF; color: #3730A3; }
    .status-cancelled { background: #FEE2E2; color: #991B1B; }
    .status-on-the-way { background: #FEF3C7; color: #92400E; }
    
    .restaurant-image {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        object-fit: cover;
    }
    
    .rating-star {
        color: #FFB800;
        font-size: 12px;
    }
    
    .reorder-btn {
        background: #FF6B35;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .reorder-btn:hover {
        background: #E55A2B;
        transform: translateY(-2px);
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
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');
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
            <div class="card border-0 shadow-sm text-center p-3">
                <h3 class="fw-bold text-primary mb-0">{{ $orders->total() }}</h3>
                <small class="text-muted">Total Orders</small>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <h3 class="fw-bold text-success mb-0">{{ $orders->where('status', 'delivered')->count() }}</h3>
                <small class="text-muted">Delivered</small>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <h3 class="fw-bold text-warning mb-0">{{ $orders->whereIn('status', ['pending', 'confirmed', 'preparing'])->count() }}</h3>
                <small class="text-muted">In Progress</small>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="card border-0 shadow-sm text-center p-3">
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
    <div class="row">
        @foreach($orders as $order)
        @php $statusClass = str_replace('_', '-', $order->status); @endphp
        <div class="col-12 mb-4">
            <div class="order-card">
                <div class="p-4">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <div class="bg-light rounded-3 p-2 text-center">
                                <i class="fas fa-receipt fa-3x text-primary"></i>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <h6 class="fw-bold mb-1">Order #{{ substr($order->order_number, -8) }}</h6>
                            <small class="text-muted">{{ $order->created_at->format('d M Y, h:i A') }}</small>
                        </div>
                        <div class="col-md-2">
                            <span class="order-status status-{{ $statusClass }}">
                                {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                            </span>
                        </div>
                        <div class="col-md-2">
                            <span class="fw-bold text-primary fs-5">{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</span>
                        </div>
                        <div class="col-md-3 text-md-end">
                            <a href="{{ route('customer.orders.show', $order->id) }}" class="btn btn-outline-primary rounded-pill me-2">
                                <i class="fas fa-eye me-1"></i> View
                            </a>
                            @if(in_array($order->status, ['delivered', 'completed']))
                            <form action="{{ route('customer.orders.reorder', $order->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary rounded-pill reorder-btn">
                                    <i class="fas fa-redo-alt me-1"></i> Reorder
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
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
