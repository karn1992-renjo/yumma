@extends('layouts.admin')

@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹'); @endphp

@section('title', 'Orders')
@section('header', 'Order Management')

@section('content')
<div class="ff-page-shell">
    <section class="ff-page-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="ff-hero-kicker">
                    <i class="fas fa-bag-shopping"></i>
                    Order Operations
                </div>
                <h1>Order Management</h1>
                <p>Track live order states, filter by restaurant or date, export reports, and bulk update operational statuses.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-light rounded-pill fw-bold px-4" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-download me-2"></i> Export
                </button>
                <a href="{{ route('admin.orders.statistics') }}" class="btn btn-outline-light rounded-pill fw-bold px-4">
                    <i class="fas fa-chart-line me-2"></i> Analytics
                </a>
            </div>
        </div>
    </section>

    <section class="ff-stat-grid">
        <div class="ff-stat-tile" style="--tile-color:#f59e0b;">
            <div class="ff-stat-icon"><i class="fas fa-clock"></i></div>
            <div class="ff-stat-label">Pending Orders</div>
            <div class="ff-stat-value">{{ number_format($statusCounts['pending'] ?? 0) }}</div>
        </div>
        <div class="ff-stat-tile" style="--tile-color:#3b82f6;">
            <div class="ff-stat-icon"><i class="fas fa-spinner"></i></div>
            <div class="ff-stat-label">Processing</div>
            <div class="ff-stat-value">{{ number_format(($statusCounts['confirmed'] ?? 0) + ($statusCounts['preparing'] ?? 0)) }}</div>
        </div>
        <div class="ff-stat-tile" style="--tile-color:#10b981;">
            <div class="ff-stat-icon"><i class="fas fa-circle-check"></i></div>
            <div class="ff-stat-label">Delivered</div>
            <div class="ff-stat-value">{{ number_format($statusCounts['delivered'] ?? 0) }}</div>
        </div>
        <div class="ff-stat-tile" style="--tile-color:#ef4444;">
            <div class="ff-stat-icon"><i class="fas fa-circle-xmark"></i></div>
            <div class="ff-stat-label">Cancelled</div>
            <div class="ff-stat-value">{{ number_format($statusCounts['cancelled'] ?? 0) }}</div>
        </div>
    </section>

    <section class="ff-card ff-filter-card">
        <form method="GET" action="{{ route('admin.orders.index') }}" id="filterForm" class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label class="form-label fw-bold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Order #, customer or phone..." value="{{ request('search') }}">
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="all">All Status</option>
                    @foreach(['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'delivered', 'cancelled'] as $status)
                        <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-3 col-md-6">
                <label class="form-label fw-bold">Restaurant</label>
                <select name="restaurant_id" class="form-select">
                    <option value="">All Restaurants</option>
                    @foreach($restaurants as $restaurant)
                        <option value="{{ $restaurant->id }}" {{ request('restaurant_id') == $restaurant->id ? 'selected' : '' }}>
                            {{ $restaurant->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="form-label fw-bold">From Date</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="form-label fw-bold">To Date</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">
                    Reset
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i> Filter Orders
                </button>
            </div>
        </form>
    </section>

    <section class="ff-card">
        <div class="ff-card-header">
            <div>
                <h3 class="ff-card-title">Order Queue</h3>
                <div class="ff-card-subtitle">Select orders for bulk updates or open each order for full operational details.</div>
            </div>
            <button class="btn btn-outline-primary rounded-pill fw-bold" id="bulkStatusBtn" onclick="showBulkStatusModal()" disabled>
                <i class="fas fa-edit me-2"></i> Bulk Update
                <span id="selectedCount" class="badge bg-primary ms-2">0</span>
            </button>
        </div>
        <div class="table-responsive">
            <table class="table ff-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="form-check-input" id="selectAll"></th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Restaurant</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $status = $order->status ?? 'pending';
                            $statusType = in_array($status, ['delivered']) ? 'success' : (in_array($status, ['cancelled']) ? 'danger' : (in_array($status, ['pending']) ? 'warning' : 'info'));
                            $paymentStatus = $order->payment_status ?? 'pending';
                            $paymentType = in_array($paymentStatus, ['success', 'paid', 'completed']) ? 'success' : ($paymentStatus === 'pending' ? 'warning' : 'danger');
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input order-checkbox" value="{{ $order->id }}">
                            </td>
                            <td>
                                <div class="fw-black text-dark">#{{ $order->order_number ?? $order->id }}</div>
                                <div class="small text-muted">Order ID {{ $order->id }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $order->customer_name ?? 'N/A' }}</div>
                                @if($order->customer_phone)
                                    <div class="small text-muted"><i class="fas fa-phone-alt me-1"></i>{{ $order->customer_phone }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="ff-soft-badge">
                                    <i class="fas fa-store"></i>
                                    {{ $order->restaurant->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="fw-black text-success">{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td>
                                <span class="ff-soft-badge {{ $statusType }}">
                                    <i class="fas fa-circle" style="font-size:7px;"></i>
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </span>
                            </td>
                            <td>
                                <span class="ff-soft-badge {{ $paymentType }}">
                                    <i class="fas {{ $paymentType === 'success' ? 'fa-check-circle' : ($paymentType === 'warning' ? 'fa-clock' : 'fa-times-circle') }}"></i>
                                    {{ ucfirst(str_replace('_', ' ', $paymentStatus)) }}
                                </span>
                                @if(($order->payout_status ?? '') === 'Payout Released')
                                    <div class="small text-success mt-1"><i class="fas fa-wallet me-1"></i>Payout Released</div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $order->created_at->format('d M Y') }}</div>
                                <div class="small text-muted">{{ $order->created_at->format('h:i A') }}</div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="ff-action-btn" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.orders.invoice', $order->id) }}" class="ff-action-btn" title="Download Invoice">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="ff-empty-state">
                                    <i class="fas fa-inbox d-block"></i>
                                    <h5>No orders found</h5>
                                    <p class="mb-0">Try adjusting your filters.</p>
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
                    Showing {{ $orders->firstItem() ?? 0 }} to {{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders
                </div>
                {{ $orders->withQueryString()->links() }}
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="bulkStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #111827, #f97316);">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fas fa-edit me-2"></i> Bulk Update Status
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="bulkOrderIds">
                <div class="alert alert-info rounded-4 border-0">
                    You have selected <strong id="selectedOrdersCount">0</strong> order(s).
                </div>
                <label class="form-label fw-bold">Select New Status</label>
                <select id="bulkStatus" class="form-select form-select-lg">
                    <option value="confirmed">Confirm Orders</option>
                    <option value="preparing">Start Preparing</option>
                    <option value="ready_for_pickup">Ready for Pickup</option>
                    <option value="cancelled">Cancel Orders</option>
                </select>
                <div class="form-text mt-2">
                    Cancelled paid orders may trigger refund processing according to your backend rules.
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="bulkUpdateConfirmBtn" onclick="bulkUpdateStatus()">
                    <i class="fas fa-save me-2"></i> Update Orders
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #111827, #f97316);">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fas fa-download me-2"></i> Export Orders
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" action="{{ route('admin.orders.export') }}">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">From Date</label>
                            <input type="date" name="date_from" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">To Date</label>
                            <input type="date" name="date_to" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Filter by Status</label>
                            <select name="status" class="form-select">
                                <option value="all">All Orders</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="preparing">Preparing</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-download me-2"></i> Export Excel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="spinner-overlay" id="loadingSpinner" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.72); z-index:9999; align-items:center; justify-content:center;">
    <div class="text-center text-white">
        <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status"></div>
        <p class="mt-3 mb-0">Updating orders...</p>
    </div>
</div>

<script>
    let selectedOrders = [];

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.order-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedOrders);
        });

        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = this.checked);
                updateSelectedOrders();
            });
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            [].slice.call(document.querySelectorAll('[title]')).map(el => new bootstrap.Tooltip(el));
        }
    });

    function updateSelectedOrders() {
        selectedOrders = Array.from(document.querySelectorAll('.order-checkbox:checked')).map(cb => cb.value);

        const bulkBtn = document.getElementById('bulkStatusBtn');
        const selectedCountSpan = document.getElementById('selectedCount');

        if (bulkBtn) bulkBtn.disabled = selectedOrders.length === 0;
        if (selectedCountSpan) selectedCountSpan.textContent = selectedOrders.length;

        const selectAllCheckbox = document.getElementById('selectAll');
        const allCheckboxes = document.querySelectorAll('.order-checkbox');

        if (selectAllCheckbox && allCheckboxes.length > 0) {
            const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(allCheckboxes).some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = !allChecked && someChecked;
        }
    }

    function showBulkStatusModal() {
        if (selectedOrders.length === 0) {
            showToastMessage('Please select at least one order', 'warning');
            return;
        }

        document.getElementById('bulkOrderIds').value = JSON.stringify(selectedOrders);
        document.getElementById('selectedOrdersCount').textContent = selectedOrders.length;

        const modalElement = document.getElementById('bulkStatusModal');
        if (modalElement && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(modalElement).show();
        }
    }

    function bulkUpdateStatus() {
        const bulkOrderIdsInput = document.getElementById('bulkOrderIds');
        const statusSelect = document.getElementById('bulkStatus');

        let orderIds;
        try {
            orderIds = JSON.parse(bulkOrderIdsInput.value);
        } catch (e) {
            showToastMessage('Invalid order data', 'error');
            return;
        }

        const spinner = document.getElementById('loadingSpinner');
        if (spinner) spinner.style.display = 'flex';

        const updateBtn = document.getElementById('bulkUpdateConfirmBtn');
        const originalBtnHtml = updateBtn ? updateBtn.innerHTML : '';
        if (updateBtn) {
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
        }

        fetch('{{ route("admin.orders.bulk-status") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                order_ids: orderIds,
                status: statusSelect.value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (spinner) spinner.style.display = 'none';
            if (updateBtn) {
                updateBtn.disabled = false;
                updateBtn.innerHTML = originalBtnHtml;
            }

            if (data.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('bulkStatusModal'));
                if (modal) modal.hide();
                showToastMessage(data.message || 'Orders updated successfully!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToastMessage(data.message || 'Failed to update orders', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (spinner) spinner.style.display = 'none';
            if (updateBtn) {
                updateBtn.disabled = false;
                updateBtn.innerHTML = originalBtnHtml;
            }
            showToastMessage('Network error occurred while updating orders', 'error');
        });
    }
</script>
@endsection
