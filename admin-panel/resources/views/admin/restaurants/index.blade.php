@extends('layouts.admin')

@section('title', 'Restaurants')

@section('styles')
<style>
    .ar-shell { display: flex; flex-direction: column; gap: 18px; }
    .ar-toolbar,
    .ar-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
    }
    .ar-toolbar { padding: 18px; }
    .ar-title h1 { margin: 0; color: #0f172a; font-size: 1.55rem; font-weight: 850; letter-spacing: 0; }
    .ar-title p { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
    .ar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .ar-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .ar-stat {
        min-height: 112px;
        padding: 18px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
        display: flex;
        justify-content: space-between;
        gap: 14px;
    }
    .ar-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--stat-color);
        background: color-mix(in srgb, var(--stat-color) 13%, #fff);
        flex: 0 0 auto;
    }
    .ar-stat-label { color: #64748b; font-size: .76rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
    .ar-stat-value { color: #0f172a; font-size: 1.55rem; font-weight: 900; line-height: 1.1; }
    .ar-filter { padding: 16px; }
    .ar-card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 18px 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    .ar-card-head h2 { margin: 0; color: #0f172a; font-size: 1.05rem; font-weight: 850; }
    .ar-card-head span { color: #64748b; font-size: .82rem; font-weight: 700; }
    .ar-table { margin: 0; }
    .ar-table thead th {
        padding: 14px 18px;
        color: #64748b;
        font-size: .73rem;
        font-weight: 850;
        text-transform: uppercase;
        letter-spacing: .05em;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .ar-table tbody td { padding: 16px 18px; vertical-align: middle; border-color: #edf2f7; }
    .ar-logo {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        object-fit: cover;
        background: #f1f5f9;
        flex: 0 0 auto;
    }
    .ar-logo-placeholder {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: #f1f5f9;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }
    .ar-name { color: #0f172a; font-weight: 850; line-height: 1.25; }
    .ar-muted { color: #64748b; font-size: .82rem; font-weight: 650; }
    .ar-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 850;
        color: #475569;
        background: #f1f5f9;
        white-space: nowrap;
    }
    .ar-pill.success { color: #047857; background: #dcfce7; }
    .ar-pill.warning { color: #92400e; background: #fef3c7; }
    .ar-pill.danger { color: #b91c1c; background: #fee2e2; }
    .ar-action {
        width: 38px;
        height: 38px;
        border: 1px solid #dbeafe;
        border-radius: 12px;
        background: #f8fbff;
        color: #2563eb;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    .ar-action.danger { color: #ef4444; border-color: #fee2e2; background: #fff7f7; }
    .ar-empty { padding: 44px 20px; text-align: center; color: #64748b; }

    @media (max-width: 1200px) {
        .ar-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .ar-toolbar .d-flex { align-items: flex-start !important; }
        .ar-title h1 { font-size: 1.25rem; }
        .ar-actions, .ar-actions .btn { width: 100%; }
        .ar-stat-grid { grid-template-columns: 1fr; }
        .ar-card-head { align-items: flex-start; flex-direction: column; }
        .ar-table thead { display: none; }
        .ar-table, .ar-table tbody, .ar-table tr, .ar-table td { display: block; width: 100%; }
        .ar-table tbody tr {
            margin: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }
        .ar-table tbody td {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 14px;
            border-bottom: 1px solid #edf2f7;
        }
        .ar-table tbody td::before {
            content: attr(data-label);
            color: #64748b;
            font-size: .72rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: .04em;
            flex: 0 0 92px;
        }
        .ar-table tbody td:first-child { justify-content: flex-start; }
        .ar-table tbody td:first-child::before { display: none; }
        .ar-table tbody td:last-child { border-bottom: 0; }
    }
</style>
@endsection

@section('content')
<div class="ar-shell">
    <section class="ar-toolbar">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="ar-title">
                <h1>Restaurants</h1>
                <p>Manage partner stores, owner accounts, verification, payout readiness, and live availability.</p>
            </div>
            <div class="ar-actions">
                <button type="button" class="btn btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#restaurantBulkUploadModal">
                    <i class="fas fa-file-arrow-up me-2"></i>Bulk Upload
                </button>
                <a href="{{ route('admin.restaurants.create') }}" class="btn btn-primary fw-bold">
                    <i class="fas fa-plus me-2"></i>Add Restaurant
                </a>
            </div>
        </div>
    </section>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('upload_errors'))
        <div class="alert alert-warning alert-dismissible fade show rounded-4 border-0 shadow-sm">
            <strong>Some rows were skipped.</strong>
            <ul class="mb-0 mt-2">
                @foreach(session('upload_errors') as $uploadError)
                    <li>{{ $uploadError }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <section class="ar-stat-grid">
        <div class="ar-stat" style="--stat-color:#f97316;">
            <div>
                <div class="ar-stat-label">Total Restaurants</div>
                <div class="ar-stat-value">{{ number_format($totalRestaurants ?? 0) }}</div>
            </div>
            <div class="ar-stat-icon"><i class="fas fa-store"></i></div>
        </div>
        <div class="ar-stat" style="--stat-color:#10b981;">
            <div>
                <div class="ar-stat-label">Open Now</div>
                <div class="ar-stat-value">{{ number_format($activeRestaurants ?? 0) }}</div>
            </div>
            <div class="ar-stat-icon"><i class="fas fa-door-open"></i></div>
        </div>
        <div class="ar-stat" style="--stat-color:#f59e0b;">
            <div>
                <div class="ar-stat-label">Pending Verify</div>
                <div class="ar-stat-value">{{ number_format($pendingVerification ?? 0) }}</div>
            </div>
            <div class="ar-stat-icon"><i class="fas fa-shield-halved"></i></div>
        </div>
        <div class="ar-stat" style="--stat-color:#2563eb;">
            <div>
                <div class="ar-stat-label">Total Orders</div>
                <div class="ar-stat-value">{{ number_format($totalOrders ?? 0) }}</div>
            </div>
            <div class="ar-stat-icon"><i class="fas fa-receipt"></i></div>
        </div>
    </section>

    <section class="ar-card ar-filter">
        <form method="GET" action="{{ route('admin.restaurants.index') }}" class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label fw-bold">Search</label>
                <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, email or phone">
            </div>
            <div class="col-lg-2">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Open</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Closed</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label fw-bold">Verification</label>
                <select name="verification" class="form-select">
                    <option value="">All</option>
                    <option value="verified" @selected(request('verification') === 'verified')>Verified</option>
                    <option value="unverified" @selected(request('verification') === 'unverified')>Pending</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label fw-bold">City</label>
                <input type="text" name="city" class="form-control" value="{{ request('city') }}" placeholder="City">
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit"><i class="fas fa-filter me-2"></i>Filter</button>
                <a class="btn btn-light border" href="{{ route('admin.restaurants.index') }}">Reset</a>
            </div>
        </form>
    </section>

    <section class="ar-card">
        <div class="ar-card-head">
            <div>
                <h2>Restaurant Queue</h2>
                <span>{{ number_format($restaurants->total()) }} partners found</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table ar-table">
                <thead>
                    <tr>
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
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if($restaurant->logo_image)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($restaurant->logo_image) }}" alt="{{ $restaurant->name }}" class="ar-logo">
                                    @else
                                        <span class="ar-logo-placeholder"><i class="fas fa-store"></i></span>
                                    @endif
                                    <div>
                                        <div class="ar-name">{{ $restaurant->name }}</div>
                                        <div class="ar-muted">{{ $restaurant->email }}</div>
                                        <div class="ar-muted">{{ $restaurant->phone }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Owner">
                                <div>
                                    <div class="ar-name">{{ $owner->name ?? 'Unassigned' }}</div>
                                    <div class="ar-muted">{{ $owner->email ?? 'No owner email' }}</div>
                                </div>
                            </td>
                            <td data-label="Location">
                                <div>
                                    <div class="ar-name">{{ trim(($restaurant->city ?? '') . ', ' . ($restaurant->state ?? ''), ', ') ?: 'No city' }}</div>
                                    <div class="ar-muted">{{ $restaurant->pincode ?: 'No pincode' }}</div>
                                </div>
                            </td>
                            <td data-label="Payout">
                                @if($isPayoutReady)
                                    <span class="ar-pill success"><i class="fas fa-check-circle"></i>Ready</span>
                                @elseif($hasAnyPayoutData)
                                    <span class="ar-pill warning"><i class="fas fa-triangle-exclamation"></i>Partial</span>
                                @else
                                    <span class="ar-pill danger"><i class="fas fa-ban"></i>Missing</span>
                                @endif
                            </td>
                            <td data-label="Status">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" data-url="{{ route('admin.restaurants.toggle-status', $restaurant) }}" onchange="toggleRestaurantStatus(this)" @checked($restaurant->is_open)>
                                    <label class="form-check-label">
                                        <span class="ar-pill {{ $restaurant->is_open ? 'success' : '' }}">{{ $restaurant->is_open ? 'Open' : 'Closed' }}</span>
                                    </label>
                                </div>
                            </td>
                            <td data-label="Verification">
                                <span class="ar-pill {{ $restaurant->is_verified ? 'success' : 'warning' }}">
                                    <i class="fas {{ $restaurant->is_verified ? 'fa-check-circle' : 'fa-clock' }}"></i>
                                    {{ $restaurant->is_verified ? 'Verified' : 'Pending' }}
                                </span>
                            </td>
                            <td data-label="Orders"><strong>{{ number_format($restaurant->orders_count ?? $restaurant->orders?->count() ?? 0) }}</strong></td>
                            <td data-label="Actions">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.restaurants.show', $restaurant) }}" class="ar-action" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="ar-action" title="Edit"><i class="fas fa-pen"></i></a>
                                    <form action="{{ route('admin.restaurants.destroy', $restaurant) }}" method="POST" id="deleteForm{{ $restaurant->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="ar-action danger" onclick="confirmDelete('deleteForm{{ $restaurant->id }}', 'Delete {{ addslashes($restaurant->name) }}?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="ar-empty">
                                    <i class="fas fa-store-slash fa-2x mb-3"></i>
                                    <div class="fw-bold text-dark">No restaurants found</div>
                                    <div>Adjust filters or add a new restaurant partner.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="ar-muted">Showing {{ $restaurants->firstItem() ?? 0 }} to {{ $restaurants->lastItem() ?? 0 }} of {{ $restaurants->total() }}</div>
            {{ $restaurants->withQueryString()->links() }}
        </div>
    </section>
</div>

<div class="modal fade" id="restaurantBulkUploadModal" tabindex="-1" aria-labelledby="restaurantBulkUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="restaurantBulkUploadModalLabel">Bulk Upload Restaurants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.restaurants.bulk-upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p class="text-muted">Upload CSV, XLS, or XLSX. Each row creates a restaurant and owner account.</p>
                    <label for="upload_file" class="form-label fw-bold">Upload File</label>
                    <input id="upload_file" type="file" name="upload_file" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                    <div class="alert alert-light border rounded-4 mt-3 mb-0">
                        Required columns: Restaurant Name, Restaurant Email, Restaurant Phone, Address, City, State, Pincode, Owner Name, Owner Email, Owner Phone, Owner Password.
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('admin.restaurants.template') }}" class="btn btn-outline-secondary me-auto"><i class="fas fa-download me-2"></i>Sample</a>
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRestaurantStatus(checkbox) {
    const currentState = checkbox.checked;
    const badge = checkbox.closest('td')?.querySelector('.ar-pill');

    fetch(checkbox.dataset.url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ is_open: currentState })
    }).then(response => response.json()).then(data => {
        if (!data.success) {
            checkbox.checked = !currentState;
            showToastMessage(data.message || 'Failed to update restaurant status', 'error');
            return;
        }

        if (badge) {
            badge.textContent = data.is_open ? 'Open' : 'Closed';
            badge.className = `ar-pill ${data.is_open ? 'success' : ''}`;
        }
        showToastMessage(`Restaurant is now ${data.is_open ? 'open' : 'closed'}`, 'success');
    }).catch(() => {
        checkbox.checked = !currentState;
        showToastMessage('Error updating restaurant status', 'error');
    });
}
</script>
@endsection
