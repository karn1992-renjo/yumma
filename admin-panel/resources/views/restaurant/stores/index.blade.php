@extends('layouts.restaurant')
@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
@endphp

@section('title', 'My Restaurants')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>My Restaurants</h1>
                <p class="text-muted">Manage all your restaurant locations from one dashboard</p>
            </div>
            <a href="{{ route('restaurant.stores.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add New Restaurant
            </a>
        </div>
    </div>

    <!-- Current Active Store -->
    <div class="alert alert-info mb-4">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <i class="fas fa-store me-2"></i>
                <strong>Currently Active:</strong> 
                {{ $currentRestaurant ? $currentRestaurant->name : 'No restaurant selected' }}
            </div>
            @if($currentRestaurant)
            <div>
                <span class="badge {{ $currentRestaurant->is_open ? 'bg-success' : 'bg-danger' }}">
                    {{ $currentRestaurant->is_open ? 'Online' : 'Offline' }}
                </span>
            </div>
            @endif
        </div>
    </div>

    <div class="row">
        @forelse($restaurants as $restaurant)
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon primary">
                            <i class="fas fa-store"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">{{ $restaurant->name }}</h5>
                            <small class="text-muted">{{ $restaurant->city }}, {{ $restaurant->state }}</small>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <form action="{{ route('restaurant.stores.switch') }}" method="POST" class="switch-store-form">
                                    @csrf
                                    <input type="hidden" name="restaurant_id" value="{{ $restaurant->id }}">
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-exchange-alt me-2"></i> Switch to this store
                                    </button>
                                </form>
                            </li>
                            <li>
                                <a href="{{ route('restaurant.stores.edit', $restaurant->id) }}" class="dropdown-item">
                                    <i class="fas fa-edit me-2"></i> Edit
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a href="{{ route('restaurant.menu.index', ['restaurant_id' => $restaurant->id]) }}" class="dropdown-item">
                                    <i class="fas fa-utensils me-2"></i> Manage Menu
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('restaurant.orders.index', ['restaurant_id' => $restaurant->id]) }}" class="dropdown-item">
                                    <i class="fas fa-shopping-cart me-2"></i> View Orders
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="bg-light rounded p-2 text-center">
                                <small class="text-muted">Total Orders</small>
                                <div class="fw-bold">{{ number_format($restaurant->delivered_orders_count ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded p-2 text-center">
                                <small class="text-muted">Revenue ({{ $currencySymbol }})</small>
                                <div class="fw-bold text-success">
                                    {{ $currencySymbol }}{{ number_format((float) ($restaurant->delivered_revenue ?? 0), $currencyDecimals) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        @if($restaurant->is_verified)
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i> Verified
                            </span>
                        @else
                            <span class="badge bg-warning">
                                <i class="fas fa-clock me-1"></i> Pending Approval
                            </span>
                        @endif
                    </div>
                    <div>
                        @if($restaurant->id == ($currentRestaurant->id ?? null))
                            <span class="badge bg-primary">Active</span>
                        @endif
                    </div>
                </div>

                @if(!$restaurant->is_verified)
                <div class="mt-3 alert alert-warning alert-sm mb-0">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        This restaurant is pending admin approval. Some features are limited.
                    </small>
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="table-card text-center py-5">
                <i class="fas fa-store-slash fa-3x text-muted mb-3"></i>
                <h5>No Restaurants Added Yet</h5>
                <p class="text-muted">Click the button above to add your first restaurant</p>
                <a href="{{ route('restaurant.stores.create') }}" class="btn btn-primary mt-2">
                    <i class="fas fa-plus me-2"></i> Add Restaurant
                </a>
            </div>
        </div>
        @endforelse
    </div>

    <!-- Quick Tips -->
    <div class="table-card mt-4">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">Quick Tips</h5>
        </div>
        <div class="p-4">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                        <div>
                            <h6 class="mb-0">Admin Approval Required</h6>
                            <small class="text-muted">New restaurants need approval before going live</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-exchange-alt fa-2x text-primary"></i>
                        <div>
                            <h6 class="mb-0">Switch Between Stores</h6>
                            <small class="text-muted">Use the dropdown to switch between your restaurants</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-utensils fa-2x text-warning"></i>
                        <div>
                            <h6 class="mb-0">Manage Each Store Separately</h6>
                            <small class="text-muted">Menu, orders, and settings are store-specific</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.switch-store-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    restaurant_id: this.querySelector('[name="restaurant_id"]').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    showToastMessage('Failed to switch store', 'error');
                }
            });
        });
    });
</script>
@endsection
