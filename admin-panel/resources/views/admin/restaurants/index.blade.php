@extends('layouts.admin')

@section('title', 'Restaurants')

@section('content')
<div class="ff-page-shell">
    <section class="ff-page-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="ff-hero-kicker">
                    <i class="fas fa-store"></i>
                    Restaurant Network
                </div>
                <h1>Restaurant Management</h1>
                <p>Manage restaurant partners, payout readiness, availability, and verification status from one clean workspace.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-toggle="modal" data-bs-target="#restaurantBulkUploadModal">
                    <i class="fas fa-file-arrow-up me-2"></i> Bulk Upload
                </button>
                <a href="{{ route('admin.restaurants.create') }}" class="btn btn-light rounded-pill fw-bold px-4">
                    <i class="fas fa-plus me-2"></i> Add Restaurant
                </a>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('upload_errors'))
        <div class="alert alert-warning alert-dismissible fade show border-0 rounded-4 shadow-sm" role="alert">
            <div class="fw-bold mb-2"><i class="fas fa-triangle-exclamation me-2"></i> Some rows were skipped:</div>
            <ul class="mb-0">
                @foreach(session('upload_errors') as $uploadError)
                    <li>{{ $uploadError }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="modal fade" id="restaurantBulkUploadModal" tabindex="-1" aria-labelledby="restaurantBulkUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="restaurantBulkUploadModalLabel">Bulk Upload Restaurants</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('admin.restaurants.bulk-upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <p class="text-muted">Upload a CSV, XLS, or XLSX file. Each row creates a restaurant and its owner account.</p>
                        <div class="mb-3">
                            <label for="upload_file" class="form-label fw-bold">Upload File</label>
                            <input id="upload_file" type="file" name="upload_file" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                        </div>
                        <div class="alert alert-light rounded-4">
                            Required columns include <code>Restaurant Name</code>, <code>Restaurant Email</code>, <code>Restaurant Phone</code>, <code>Address</code>, <code>City</code>, <code>State</code>, <code>Pincode</code>, <code>Owner Name</code>, <code>Owner Email</code>, <code>Owner Phone</code>, and <code>Owner Password</code>.
                        </div>
                        <a href="{{ route('admin.restaurants.template') }}" class="btn btn-outline-secondary rounded-pill">
                            <i class="fas fa-download me-2"></i> Download Sample
                        </a>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Restaurants</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <section class="ff-stat-grid">
        <div class="ff-stat-tile" style="--tile-color:#f97316;">
            <div class="ff-stat-icon"><i class="fas fa-store"></i></div>
            <div class="ff-stat-label">Total Restaurants</div>
            <div class="ff-stat-value">{{ number_format($totalRestaurants ?? 0) }}</div>
        </div>
        <div class="ff-stat-tile" style="--tile-color:#10b981;">
            <div class="ff-stat-icon"><i class="fas fa-door-open"></i></div>
            <div class="ff-stat-label">Active Restaurants</div>
            <div class="ff-stat-value">{{ number_format($activeRestaurants ?? 0) }}</div>
        </div>
        <div class="ff-stat-tile" style="--tile-color:#f59e0b;">
            <div class="ff-stat-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="ff-stat-label">Pending Verification</div>
            <div class="ff-stat-value">{{ number_format($pendingVerification ?? 0) }}</div>
        </div>
        <div class="ff-stat-tile" style="--tile-color:#3b82f6;">
            <div class="ff-stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="ff-stat-label">Total Orders</div>
            <div class="ff-stat-value">{{ number_format($totalOrders ?? 0) }}</div>
        </div>
    </section>

    <section class="ff-card ff-filter-card">
        <form method="GET" action="{{ route('admin.restaurants.index') }}" class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label fw-bold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search name, email or phone..." value="{{ request('search') }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active (Open)</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive (Closed)</option>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold">Verification</label>
                <select name="verification" class="form-select">
                    <option value="">All Verification</option>
                    <option value="verified" {{ request('verification') == 'verified' ? 'selected' : '' }}>Verified</option>
                    <option value="unverified" {{ request('verification') == 'unverified' ? 'selected' : '' }}>Unverified</option>
                </select>
            </div>
            <div class="col-lg-2 d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i> Filter
                </button>
            </div>
        </form>
    </section>

    <section class="ff-card">
        <div class="ff-card-header">
            <div>
                <h3 class="ff-card-title">Restaurant List</h3>
                <div class="ff-card-subtitle">Showing partners, owners, payout readiness and live status.</div>
            </div>
            <span class="ff-soft-badge info">{{ number_format($restaurants->total()) }} Restaurants</span>
        </div>
        <div class="table-responsive">
            <table class="table ff-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Restaurant</th>
                        <th>Owner</th>
                        <th>Location</th>
                        <th>Payout</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Orders</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($restaurants as $restaurant)
                        @php
                            $owner = $restaurant->owner;
                            $hasAccountHolder = filled($owner?->account_holder_name);
                            $hasGatewayAccount = filled($owner?->gateway_account_id) || filled($owner?->stripe_account_id);
                            $hasBankAccount = filled($owner?->bank_name) && filled($owner?->account_number) && filled($owner?->ifsc_code);
                            $hasUpi = filled($owner?->upi_id);
                            $isPayoutReady = $hasAccountHolder && ($hasGatewayAccount || $hasBankAccount || $hasUpi);
                            $hasAnyPayoutData = $hasAccountHolder || $hasGatewayAccount || $hasBankAccount || $hasUpi || filled($owner?->bank_name) || filled($owner?->account_number) || filled($owner?->ifsc_code);
                        @endphp
                        <tr>
                            <td class="fw-black text-dark">#{{ $restaurant->id }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if($restaurant->logo_image)
                                        <img src="{{ Storage::url($restaurant->logo_image) }}" width="46" height="46" class="rounded-4" style="object-fit: cover;">
                                    @else
                                        <div class="rounded-4 bg-light d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;">
                                            <i class="fas fa-store text-muted"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-black text-dark">{{ $restaurant->name }}</div>
                                        <div class="small text-muted">{{ $restaurant->email }}</div>
                                        <div class="small text-muted">{{ $restaurant->phone }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $owner->name ?? 'N/A' }}</div>
                                <div class="small text-muted">{{ $owner->email ?? '' }}</div>
                            </td>
                            <td>
                                <div>{{ $restaurant->city }}, {{ $restaurant->state }}</div>
                                <div class="small text-muted">{{ $restaurant->pincode }}</div>
                            </td>
                            <td>
                                @if($isPayoutReady)
                                    <span class="ff-soft-badge success"><i class="fas fa-check-circle"></i> Ready</span>
                                @elseif($hasAnyPayoutData)
                                    <span class="ff-soft-badge warning"><i class="fas fa-triangle-exclamation"></i> Partial</span>
                                @else
                                    <span class="ff-soft-badge"><i class="fas fa-ban"></i> Missing</span>
                                @endif
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           data-id="{{ $restaurant->id }}"
                                           data-url="{{ route('admin.restaurants.toggle-status', $restaurant) }}"
                                           onchange="toggleRestaurantStatus(this)"
                                           {{ $restaurant->is_open ? 'checked' : '' }}>
                                    <label class="form-check-label">
                                        <span class="ff-soft-badge {{ $restaurant->is_open ? 'success' : '' }}">
                                            {{ $restaurant->is_open ? 'Open' : 'Closed' }}
                                        </span>
                                    </label>
                                </div>
                            </td>
                            <td>
                                @if($restaurant->is_verified)
                                    <span class="ff-soft-badge success"><i class="fas fa-check-circle"></i> Verified</span>
                                @else
                                    <span class="ff-soft-badge warning"><i class="fas fa-clock"></i> Pending</span>
                                @endif
                            </td>
                            <td class="fw-black">{{ number_format($restaurant->orders_count ?? 0) }}</td>
                            <td>
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.restaurants.show', $restaurant) }}" class="ff-action-btn" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="ff-action-btn" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.restaurants.destroy', $restaurant) }}" method="POST" id="deleteForm{{ $restaurant->id }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="ff-action-btn danger" onclick="confirmDelete('deleteForm{{ $restaurant->id }}', 'Are you sure you want to delete {{ $restaurant->name }}?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="ff-empty-state">
                                    <i class="fas fa-store-slash d-block"></i>
                                    <h5>No Restaurants Found</h5>
                                    <p class="mb-0">Click Add Restaurant to create your first restaurant partner.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 px-4 py-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="text-muted small">
                    Showing {{ $restaurants->firstItem() ?? 0 }} to {{ $restaurants->lastItem() ?? 0 }} of {{ $restaurants->total() }} restaurants
                </div>
                {{ $restaurants->withQueryString()->links() }}
            </div>
        </div>
    </section>
</div>

<script>
    function toggleRestaurantStatus(checkbox) {
        const url = checkbox.dataset.url;
        const isOpen = checkbox.checked;
        const statusBadge = checkbox.closest('td').querySelector('.ff-soft-badge');

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ is_open: isOpen })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (statusBadge) {
                    statusBadge.textContent = data.is_open ? 'Open' : 'Closed';
                    statusBadge.className = `ff-soft-badge ${data.is_open ? 'success' : ''}`;
                }
                showToastMessage(`Restaurant is now ${data.is_open ? 'Open for business' : 'Closed'}`, 'success');
            } else {
                checkbox.checked = !isOpen;
                showToastMessage(data.message || 'Failed to update status', 'error');
            }
        })
        .catch(error => {
            checkbox.checked = !isOpen;
            showToastMessage('Error updating restaurant status', 'error');
            console.error('Error:', error);
        });
    }
</script>
@endsection
