@extends('layouts.restaurant')

@section('title', 'My Restaurants')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1>My Restaurants</h1>
                <p class="text-muted">Manage all your restaurant locations from one dashboard</p>
            </div>
            <a href="{{ route('restaurant.stores.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add New Restaurant
            </a>
        </div>
    </div>

    <div class="alert alert-info mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <i class="fas fa-store me-2"></i>
                <strong>Currently Active:</strong>
                {{ $currentStore['name'] }}
            </div>
            @if($currentStore['has_store'])
                <span class="badge {{ $currentStore['status_class'] }}">{{ $currentStore['status_label'] }}</span>
            @endif
        </div>
    </div>

    <div class="row">
        @forelse($storeRows as $store)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="icon primary">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="min-w-0">
                                <h5 class="mb-0 text-truncate">{{ $store['name'] }}</h5>
                                <small class="text-muted">{{ $store['location'] }}</small>
                            </div>
                        </div>

                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <form action="{{ route('restaurant.stores.switch') }}" method="POST" class="switch-store-form">
                                        @csrf
                                        <input type="hidden" name="restaurant_id" value="{{ $store['id'] }}">
                                        <button type="submit" class="dropdown-item">
                                            <i class="fas fa-exchange-alt me-2"></i> Switch to this store
                                        </button>
                                    </form>
                                </li>
                                <li>
                                    <a href="{{ route('restaurant.stores.edit', $store['id']) }}" class="dropdown-item">
                                        <i class="fas fa-edit me-2"></i> Edit
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a href="{{ route('restaurant.menu.index') }}" class="dropdown-item">
                                        <i class="fas fa-utensils me-2"></i> Manage Menu
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('restaurant.orders.index') }}" class="dropdown-item">
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
                                    <div class="fw-bold">{{ $store['orders'] }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-light rounded p-2 text-center">
                                    <small class="text-muted">Revenue</small>
                                    <div class="fw-bold text-success">{{ $store['revenue'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            @if($store['is_verified'])
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i> Verified
                                </span>
                            @else
                                <span class="badge bg-warning">
                                    <i class="fas fa-clock me-1"></i> Pending Approval
                                </span>
                            @endif

                            @if($store['is_open'])
                                <span class="badge bg-success ms-1">Online</span>
                            @else
                                <span class="badge bg-secondary ms-1">Offline</span>
                            @endif
                        </div>

                        @if($store['is_active'])
                            <span class="badge bg-primary">Active</span>
                        @endif
                    </div>

                    @if(!$store['is_verified'])
                        <div class="mt-3 alert alert-warning mb-0">
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
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('.switch-store-form');

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    restaurant_id: form.querySelector('[name="restaurant_id"]').value
                })
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        window.location.reload();
                        return;
                    }

                    if (typeof showToastMessage === 'function') {
                        showToastMessage(data.message || 'Failed to switch store', 'error');
                    } else {
                        alert(data.message || 'Failed to switch store');
                    }
                })
                .catch(function () {
                    if (typeof showToastMessage === 'function') {
                        showToastMessage('Failed to switch store', 'error');
                    } else {
                        alert('Failed to switch store');
                    }
                });
        });
    });
});
</script>
@endsection
