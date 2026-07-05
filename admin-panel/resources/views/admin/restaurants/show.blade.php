@extends('layouts.admin')

@section('title', 'Restaurant Details')
@section('header', 'Restaurant Details')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');
@endphp

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>{{ $restaurant->name }}</h1>
            <p class="text-muted mb-0">View restaurant details and statistics</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#increaseMenuPricesModal">
                <i class="fas fa-arrow-trend-up me-2"></i> Increase Menu Prices
            </button>
            <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Edit
            </a>
            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-circle-exclamation me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-primary">{{ $totalOrders }}</div>
            <small class="text-muted">Total Orders</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-success">{{ $currencySymbol }}{{ number_format($totalRevenue, App\Models\AppSetting::currencyDecimals()) }}</div>
            <small class="text-muted">Total Revenue</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-warning">{{ number_format($averageRating, 1) }} ⭐</div>
            <small class="text-muted">Avg Rating</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-info">{{ $totalMenuItems }}</div>
            <small class="text-muted">Menu Items</small>
        </div>
    </div>
</div>

<div class="row">
    <!-- Restaurant Info -->
    <div class="col-lg-8">
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-store me-2 text-primary"></i> Basic Information
                </h5>
            </div>
            <div class="p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Restaurant Name</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->name }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Email</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->email }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Phone</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->phone }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Status</label>
                            <p class="mb-0">
                                <span class="badge {{ $restaurant->is_open ? 'bg-success' : 'bg-secondary' }} rounded-3">
                                    {{ $restaurant->is_open ? 'Open' : 'Closed' }}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Verification Status</label>
                            <p class="mb-0">
                                @if($restaurant->is_verified)
                                    <span class="badge bg-success rounded-3">
                                        <i class="fas fa-check-circle me-1"></i> Verified
                                    </span>
                                @else
                                    <span class="badge bg-warning rounded-3">
                                        <i class="fas fa-clock me-1"></i> Pending Verification
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Featured</label>
                            <p class="mb-0">
                                <span class="badge {{ $restaurant->is_featured ? 'bg-primary' : 'bg-light text-dark' }} rounded-3">
                                    {{ $restaurant->is_featured ? 'Featured' : 'Not Featured' }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Info -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-map-marker-alt me-2 text-primary"></i> Location Information
                </h5>
            </div>
            <div class="p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Address</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->address }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">City</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->city }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">State</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->state }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Pincode</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->pincode }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Latitude</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->latitude }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Longitude</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->longitude }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Delivery Radius</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->delivery_radius }} km</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery & Orders Info -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-cog me-2 text-primary"></i> Configuration
                </h5>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Minimum Order Amount</label>
                            <p class="mb-0 fw-bold">{{ $currencySymbol }}{{ number_format($restaurant->min_order_amount, App\Models\AppSetting::currencyDecimals()) }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Delivery Fee</label>
                            <p class="mb-0 fw-bold">{{ $currencySymbol }}{{ number_format($restaurant->delivery_fee, App\Models\AppSetting::currencyDecimals()) }}</p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Description</label>
                            <p class="mb-0">{{ $restaurant->description ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-building-columns me-2 text-primary"></i> Payout Details
                </h5>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Account Holder Name</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->owner->account_holder_name ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Bank Name</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->owner->bank_name ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Account Number</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->owner->account_number ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">IFSC / Routing Code</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->owner->ifsc_code ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">UPI ID</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->owner->upi_id ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <label class="text-muted small fw-semibold">Gateway Account ID</label>
                            <p class="mb-0 fw-bold">{{ $restaurant->owner->stripe_account_id ?? $restaurant->owner->gateway_account_id ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-receipt me-2 text-primary"></i> Recent Orders
                </h5>
                <small class="text-muted">Last 10 orders</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($restaurant->orders as $order)
                        <tr>
                            <td>#{{ $order->id }}</td>
                            <td>{{ $order->customer->name ?? 'Guest' }}</td>
                            <td>{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td>
                                <span class="badge 
                                    @if($order->status == 'delivered') bg-success
                                    @elseif($order->status == 'cancelled') bg-danger
                                    @elseif($order->status == 'pending') bg-warning
                                    @elseif($order->status == 'confirmed') bg-info
                                    @else bg-secondary @endif
                                    rounded-3">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td><small class="text-muted">{{ $order->created_at->format('d M Y') }}</small></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No orders found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Menu Items -->
        <div class="table-card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-utensils me-2 text-primary"></i> Recent Menu Items
                </h5>
                <small class="text-muted">Last 10 items</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($restaurant->menuItems as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td>{{ $item->category->name ?? 'N/A' }}</td>
                            <td>{{ $currencySymbol }}{{ number_format($item->price, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td>
                                <span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }} rounded-3">
                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No menu items found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Owner Info -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-user me-2 text-primary"></i> Owner Information
                </h5>
            </div>
            <div class="p-4">
                <div class="text-center mb-3">
                    @if($restaurant->owner->avatar)
                        <img src="{{ Storage::url($restaurant->owner->avatar) }}" class="rounded-circle" width="80" height="80" style="object-fit: cover;">
                    @else
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-user-circle fa-2x text-muted"></i>
                        </div>
                    @endif
                </div>
                <div class="info-group">
                    <label class="text-muted small fw-semibold">Owner Name</label>
                    <p class="mb-0 fw-bold">{{ $restaurant->owner->name }}</p>
                </div>
                <div class="info-group mt-3">
                    <label class="text-muted small fw-semibold">Email</label>
                    <p class="mb-0 fw-bold">{{ $restaurant->owner->email }}</p>
                </div>
                <div class="info-group mt-3">
                    <label class="text-muted small fw-semibold">Phone</label>
                    <p class="mb-0 fw-bold">{{ $restaurant->owner->phone }}</p>
                </div>
                <div class="info-group mt-3">
                    <label class="text-muted small fw-semibold">Status</label>
                    <p class="mb-0">
                        <span class="badge {{ $restaurant->owner->email_verified_at ? 'bg-success' : 'bg-warning' }} rounded-3">
                            {{ $restaurant->owner->email_verified_at ? 'Verified' : 'Unverified' }}
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Images -->
        @if($restaurant->logo_image || $restaurant->banner_image)
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-images me-2 text-primary"></i> Media
                </h5>
            </div>
            <div class="p-4">
                @if($restaurant->logo_image)
                <div class="mb-3">
                    <label class="text-muted small fw-semibold d-block mb-2">Logo</label>
                    <img src="{{ Storage::url($restaurant->logo_image) }}" class="img-fluid rounded" style="max-height: 150px;">
                </div>
                @endif
                @if($restaurant->banner_image)
                <div>
                    <label class="text-muted small fw-semibold d-block mb-2">Banner</label>
                    <img src="{{ Storage::url($restaurant->banner_image) }}" class="img-fluid rounded" style="max-height: 150px;">
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

<div class="modal fade" id="increaseMenuPricesModal" tabindex="-1" aria-labelledby="increaseMenuPricesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.restaurants.increase-menu-prices', $restaurant) }}" id="increaseMenuPricesForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="increaseMenuPricesModalLabel">Increase Menu Prices</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Increase type</label>
                        <div class="btn-group w-100" role="group" aria-label="Price increase type">
                            <input type="radio" class="btn-check" name="adjustment_type" id="adjustmentPercentage" value="percentage" autocomplete="off" @checked(old('adjustment_type', 'percentage') === 'percentage')>
                            <label class="btn btn-outline-primary" for="adjustmentPercentage">Percentage</label>
                            <input type="radio" class="btn-check" name="adjustment_type" id="adjustmentFixed" value="fixed" autocomplete="off" @checked(old('adjustment_type') === 'fixed')>
                            <label class="btn btn-outline-primary" for="adjustmentFixed">Fixed amount</label>
                        </div>
                    </div>
                    <div>
                        <label for="adjustmentValue" class="form-label fw-semibold">Increase value</label>
                        <div class="input-group">
                            <span class="input-group-text" id="adjustmentValuePrefix">{{ old('adjustment_type') === 'fixed' ? $currencySymbol : '%' }}</span>
                            <input type="number" class="form-control" id="adjustmentValue" name="adjustment_value" min="0.00001" max="1000000" step="0.00001" required value="{{ old('adjustment_value') }}">
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0">
                        This updates all {{ $totalMenuItems }} menu items, including discounted prices, variants, and add-ons. This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-arrow-trend-up me-2"></i> Apply Increase
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .info-group {
        padding: 1rem 0;
        border-bottom: 1px solid #e9ecef;
    }
    .info-group:last-child {
        border-bottom: none;
    }
</style>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('increaseMenuPricesForm');
    const prefix = document.getElementById('adjustmentValuePrefix');
    const typeInputs = form.querySelectorAll('input[name="adjustment_type"]');

    typeInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            prefix.textContent = input.value === 'percentage' ? '%' : @json($currencySymbol);
        });
    });

    form.addEventListener('submit', function (event) {
        const type = form.querySelector('input[name="adjustment_type"]:checked').value;
        const value = document.getElementById('adjustmentValue').value;
        const label = type === 'percentage' ? value + '%' : @json($currencySymbol) + value;

        if (!window.confirm(@json("Increase every menu price for {$restaurant->name} by ") + label + '?')) {
            event.preventDefault();
        }
    });

    @if($errors->has('adjustment_type') || $errors->has('adjustment_value'))
        bootstrap.Modal.getOrCreateInstance(document.getElementById('increaseMenuPricesModal')).show();
    @endif
});
</script>
@endsection
