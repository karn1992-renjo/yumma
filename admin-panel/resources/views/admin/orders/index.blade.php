@extends('layouts.admin')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $money = fn ($value) => $currencySymbol . number_format((float) $value, $currencyDecimals);

    $statusMeta = [
        'pending' => ['label' => 'Pending', 'icon' => 'clock', 'tone' => 'warning'],
        'confirmed' => ['label' => 'Confirmed', 'icon' => 'circle-check', 'tone' => 'primary'],
        'preparing' => ['label' => 'Preparing', 'icon' => 'utensils', 'tone' => 'info'],
        'ready_for_pickup' => ['label' => 'Ready', 'icon' => 'box-open', 'tone' => 'success'],
        'picked_up' => ['label' => 'Picked Up', 'icon' => 'person-biking', 'tone' => 'dark'],
        'on_the_way' => ['label' => 'On The Way', 'icon' => 'route', 'tone' => 'info'],
        'delivered' => ['label' => 'Delivered', 'icon' => 'house-circle-check', 'tone' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'ban', 'tone' => 'danger'],
    ];

    $paymentMeta = [
        'success' => ['label' => 'Paid', 'icon' => 'circle-check', 'tone' => 'success'],
        'paid' => ['label' => 'Paid', 'icon' => 'circle-check', 'tone' => 'success'],
        'completed' => ['label' => 'Paid', 'icon' => 'circle-check', 'tone' => 'success'],
        'pending' => ['label' => 'Pending', 'icon' => 'clock', 'tone' => 'warning'],
        'failed' => ['label' => 'Failed', 'icon' => 'circle-xmark', 'tone' => 'danger'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'circle-xmark', 'tone' => 'danger'],
    ];

    $processingCount = ($statusCounts['confirmed'] ?? 0) + ($statusCounts['preparing'] ?? 0) + ($statusCounts['ready_for_pickup'] ?? 0);
    $paidCount = ($paymentStatusCounts['success'] ?? 0) + ($paymentStatusCounts['paid'] ?? 0) + ($paymentStatusCounts['completed'] ?? 0);
    $refundCount = array_sum($refundStatusCounts ?? []);

    $statTiles = [
        ['label' => 'Total Orders', 'value' => number_format($orders->total()), 'icon' => 'receipt', 'tone' => '#8b5cf6'],
        ['label' => 'Pending', 'value' => number_format($statusCounts['pending'] ?? 0), 'icon' => 'clock', 'tone' => '#f59e0b'],
        ['label' => 'Processing', 'value' => number_format($processingCount), 'icon' => 'spinner', 'tone' => '#3b82f6'],
        ['label' => 'Delivered', 'value' => number_format($statusCounts['delivered'] ?? 0), 'icon' => 'circle-check', 'tone' => '#10b981'],
        ['label' => 'Cancelled', 'value' => number_format($statusCounts['cancelled'] ?? 0), 'icon' => 'circle-xmark', 'tone' => '#ef4444'],
    ];
@endphp

@section('title', 'Orders')
@section('header', 'Order Management')

@section('styles')
<style>
    .ao-shell {
        display: grid;
        gap: 16px;
        max-width: 100%;
        min-width: 0;
    }

    .ao-panel,
    .ao-stat,
    .ao-order-card {
        border: 1px solid rgba(226, 232, 240, .92);
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 16px 42px rgba(15, 23, 42, .06);
    }

    .ao-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 16px 18px;
        border-radius: 22px;
    }

    .ao-title {
        margin: 0;
        color: #0f172a;
        font-size: 24px;
        font-weight: 950;
        line-height: 1.1;
        letter-spacing: 0;
    }

    .ao-subtitle {
        margin-top: 5px;
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
    }

    .ao-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 9px;
    }

    .ao-stat-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
    }

    .ao-stat {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 86px;
        padding: 14px;
        border-radius: 20px;
    }

    .ao-stat-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .ao-stat-value {
        margin-top: 5px;
        color: #0f172a;
        font-size: 24px;
        font-weight: 950;
        line-height: 1;
    }

    .ao-stat-icon {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        border-radius: 15px;
        color: var(--tile-color);
        background: color-mix(in srgb, var(--tile-color) 12%, white);
    }

    .ao-panel {
        overflow: hidden;
        border-radius: 22px;
    }

    .ao-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 15px 18px;
        border-bottom: 1px solid rgba(226, 232, 240, .88);
    }

    .ao-panel-title {
        margin: 0;
        color: #0f172a;
        font-size: 16px;
        font-weight: 950;
    }

    .ao-panel-subtitle {
        margin-top: 3px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .ao-filter-body {
        padding: 16px 18px 18px;
    }

    .ao-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border: 1px solid rgba(226, 232, 240, .92);
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .ao-chip.neutral { color: #475569; background: #f8fafc; }
    .ao-chip.success { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
    .ao-chip.warning { color: #92400e; background: #fffbeb; border-color: #fde68a; }
    .ao-chip.danger { color: #991b1b; background: #fef2f2; border-color: #fecaca; }
    .ao-chip.info { color: #075985; background: #f0f9ff; border-color: #bae6fd; }
    .ao-chip.primary { color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; }
    .ao-chip.dark { color: #334155; background: #f1f5f9; border-color: #cbd5e1; }

    .ao-order-list {
        display: grid;
        gap: 10px;
        padding: 12px;
    }

    .ao-order-card {
        display: grid;
        grid-template-columns: 32px minmax(180px, 1.05fr) minmax(180px, 1fr) minmax(190px, 1.1fr) minmax(110px, .55fr) minmax(135px, .75fr) 104px;
        gap: 14px;
        align-items: center;
        padding: 14px;
        border-radius: 18px;
        box-shadow: none;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .ao-order-card:hover {
        border-color: color-mix(in srgb, var(--primary) 30%, #e2e8f0);
        box-shadow: 0 14px 30px rgba(15, 23, 42, .08);
        transform: translateY(-1px);
    }

    .ao-order-number {
        color: #0f172a;
        font-size: 15px;
        font-weight: 950;
        line-height: 1.15;
        white-space: nowrap;
    }

    .ao-muted {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .ao-strong {
        color: #0f172a;
        font-size: 13px;
        font-weight: 900;
        line-height: 1.25;
    }

    .ao-amount {
        color: #047857;
        font-size: 15px;
        font-weight: 950;
        white-space: nowrap;
    }

    .ao-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .ao-icon-btn {
        width: 40px;
        height: 38px;
        display: inline-grid;
        place-items: center;
        border: 1px solid #dbe4f0;
        border-radius: 12px;
        color: #334155;
        background: #fff;
        text-decoration: none;
        transition: all .2s ease;
    }

    .ao-icon-btn:hover {
        color: #fff;
        border-color: var(--primary);
        background: var(--primary);
    }

    .ao-empty {
        padding: 38px 18px;
        text-align: center;
        color: #64748b;
        font-weight: 800;
    }

    .ao-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 18px;
        border-top: 1px solid rgba(226, 232, 240, .88);
        background: #fff;
    }

    .ao-loading {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, .72);
    }

    @media (max-width: 1399.98px) {
        .ao-stat-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .ao-order-card {
            grid-template-columns: 30px minmax(170px, 1fr) minmax(170px, 1fr) minmax(170px, 1fr);
        }

        .ao-order-card > :nth-child(5),
        .ao-order-card > :nth-child(6),
        .ao-order-card > :nth-child(7) {
            grid-column: span 1;
        }
    }

    @media (max-width: 991.98px) {
        .ao-toolbar {
            grid-template-columns: 1fr;
        }

        .ao-toolbar-actions {
            justify-content: flex-start;
        }

        .ao-stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ao-order-list {
            padding: 10px;
        }

        .ao-order-card {
            grid-template-columns: 26px minmax(0, 1fr);
            gap: 10px 12px;
            align-items: start;
            padding: 14px;
        }

        .ao-order-card > :nth-child(2),
        .ao-order-card > :nth-child(3),
        .ao-order-card > :nth-child(4),
        .ao-order-card > :nth-child(5),
        .ao-order-card > :nth-child(6),
        .ao-order-card > :nth-child(7) {
            grid-column: 2;
        }

        .ao-order-card > :nth-child(1) {
            grid-row: 1;
            padding-top: 3px;
        }

        .ao-order-card > :nth-child(2) {
            grid-row: 1;
            min-width: 0;
        }

        .ao-order-card > :nth-child(3),
        .ao-order-card > :nth-child(4) {
            padding-top: 2px;
        }

        .ao-order-card > :nth-child(5) {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 14px;
            background: #f8fafc;
        }

        .ao-order-card > :nth-child(6) {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: wrap;
            gap: 7px;
        }

        .ao-actions {
            justify-content: flex-start;
            padding-top: 2px;
        }

        .ao-icon-btn {
            width: 42px;
            height: 40px;
        }
    }

    @media (max-width: 575.98px) {
        .ao-stat-grid {
            grid-template-columns: 1fr;
        }

        .ao-title {
            font-size: 21px;
        }

        .ao-panel-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .ao-filter-body {
            padding: 14px;
        }

        .ao-chip {
            max-width: 100%;
            font-size: 11px;
            padding: 6px 9px;
        }

        .ao-order-number {
            font-size: 14px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .ao-muted {
            font-size: 11px;
            line-height: 1.35;
        }

        .ao-strong {
            font-size: 12px;
        }

        .ao-amount {
            font-size: 15px;
        }

        .ao-order-card {
            border-radius: 18px;
        }
    }
</style>
@endsection

@section('content')
<div class="ao-shell">
    <section class="ao-toolbar ao-panel">
        <div>
            <h1 class="ao-title">Order Management</h1>
            <div class="ao-subtitle">
                {{ number_format($orders->total()) }} orders found
                @if(request()->hasAny(['search', 'status', 'restaurant_id', 'date_from', 'date_to', 'payment_status', 'refund_status']))
                    with current filters applied
                @endif
            </div>
        </div>
        <div class="ao-toolbar-actions">
            <a href="{{ route('admin.orders.statistics') }}" class="btn btn-outline-primary">
                <i class="fas fa-chart-line me-2"></i>Analytics
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="fas fa-download me-2"></i>Export
            </button>
        </div>
    </section>

    <section class="ao-stat-grid">
        @foreach($statTiles as $tile)
            <div class="ao-stat" style="--tile-color: {{ $tile['tone'] }};">
                <div>
                    <div class="ao-stat-label">{{ $tile['label'] }}</div>
                    <div class="ao-stat-value">{{ $tile['value'] }}</div>
                </div>
                <div class="ao-stat-icon">
                    <i class="fas fa-{{ $tile['icon'] }}"></i>
                </div>
            </div>
        @endforeach
    </section>

    <section class="ao-panel">
        <div class="ao-panel-header">
            <div>
                <h2 class="ao-panel-title">Filters</h2>
                <div class="ao-panel-subtitle">Search by order, customer, restaurant, date or payment state.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="ao-chip neutral"><i class="fas fa-credit-card"></i>{{ number_format($paidCount) }} paid</span>
                <span class="ao-chip neutral"><i class="fas fa-rotate-left"></i>{{ number_format($refundCount) }} refunds</span>
            </div>
        </div>
        <div class="ao-filter-body">
            <form method="GET" action="{{ route('admin.orders.index') }}" id="filterForm" class="row g-3 align-items-end">
                <div class="col-xxl-3 col-xl-4 col-md-6">
                    <label class="form-label fw-bold">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Order #, customer or phone..." value="{{ request('search') }}">
                </div>
                <div class="col-xxl-2 col-xl-4 col-md-6">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="all">All Status</option>
                        @foreach($statusMeta as $status => $meta)
                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xxl-2 col-xl-4 col-md-6">
                    <label class="form-label fw-bold">Payment</label>
                    <select name="payment_status" class="form-select">
                        <option value="">All Payments</option>
                        @foreach(['success' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed'] as $status => $label)
                            <option value="{{ $status }}" {{ request('payment_status') === $status ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xxl-3 col-xl-4 col-md-6">
                    <label class="form-label fw-bold">Restaurant</label>
                    <select name="restaurant_id" class="form-select">
                        <option value="">All Restaurants</option>
                        @foreach($restaurants as $restaurant)
                            <option value="{{ $restaurant->id }}" {{ (string) request('restaurant_id') === (string) $restaurant->id ? 'selected' : '' }}>
                                {{ $restaurant->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xxl-1 col-xl-4 col-md-6">
                    <label class="form-label fw-bold">From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-xxl-1 col-xl-4 col-md-6">
                    <label class="form-label fw-bold">To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-light">
                        Reset
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="ao-panel">
        <div class="ao-panel-header">
            <div>
                <h2 class="ao-panel-title">Order Queue</h2>
                <div class="ao-panel-subtitle">Select orders for bulk updates or open details for full operations.</div>
            </div>
            <button class="btn btn-outline-primary" id="bulkStatusBtn" onclick="showBulkStatusModal()" disabled>
                <i class="fas fa-pen-to-square me-2"></i>Bulk Update
                <span id="selectedCount" class="badge bg-primary ms-2">0</span>
            </button>
        </div>

        <div class="ao-order-list">
            @if($orders->count() > 0)
                <div class="px-2 pb-1">
                    <label class="ao-chip neutral mb-0">
                        <input type="checkbox" class="form-check-input mt-0" id="selectAll">
                        Select page
                    </label>
                </div>
            @endif

            @forelse($orders as $order)
                @php
                    $status = $order->status ?? 'pending';
                    $statusInfo = $statusMeta[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'icon' => 'circle', 'tone' => 'secondary'];
                    $paymentStatus = $order->payment_status ?? 'pending';
                    $paymentInfo = $paymentMeta[$paymentStatus] ?? ['label' => ucfirst(str_replace('_', ' ', $paymentStatus)), 'icon' => 'circle', 'tone' => 'secondary'];
                    $orderType = ucfirst(str_replace('_', ' ', $order->order_type ?: 'delivery'));
                @endphp
                <article class="ao-order-card">
                    <div>
                        <input type="checkbox" class="form-check-input order-checkbox" value="{{ $order->id }}" aria-label="Select order {{ $order->order_number ?? $order->id }}">
                    </div>

                    <div class="min-w-0">
                        <div class="ao-order-number">#{{ $order->order_number ?? $order->id }}</div>
                        <div class="ao-muted">
                            {{ $order->created_at->format('d M Y, h:i A') }}
                        </div>
                    </div>

                    <div class="min-w-0">
                        <div class="ao-strong text-truncate">{{ $order->customer_name ?: optional($order->customer)->name ?: 'Guest' }}</div>
                        <div class="ao-muted text-truncate">
                            <i class="fas fa-phone-alt me-1"></i>{{ $order->customer_phone ?: optional($order->customer)->phone ?: 'N/A' }}
                        </div>
                    </div>

                    <div class="min-w-0">
                        <div class="ao-strong text-truncate">{{ optional($order->restaurant)->name ?? 'N/A' }}</div>
                        <div class="ao-muted text-truncate">
                            <i class="fas fa-truck-fast me-1"></i>{{ $orderType }}
                            @if($order->branch)
                                <span class="ms-2"><i class="fas fa-code-branch me-1"></i>{{ $order->branch->name }}</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="ao-amount">{{ $money($order->total) }}</div>
                        <div class="ao-muted">{{ $order->orderItems_count ?? $order->items_count ?? '' }}</div>
                    </div>

                    <div class="d-flex flex-column align-items-start gap-1">
                        <span class="ao-chip {{ $statusInfo['tone'] }}">
                            <i class="fas fa-{{ $statusInfo['icon'] }}"></i>{{ $statusInfo['label'] }}
                        </span>
                        <span class="ao-chip {{ $paymentInfo['tone'] }}">
                            <i class="fas fa-{{ $paymentInfo['icon'] }}"></i>{{ $paymentInfo['label'] }}
                        </span>
                    </div>

                    <div class="ao-actions">
                        <a href="{{ route('admin.orders.show', $order->id) }}" class="ao-icon-btn" title="View Details" aria-label="View order {{ $order->order_number ?? $order->id }}">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('admin.orders.invoice', $order->id) }}" class="ao-icon-btn" title="Download Invoice" aria-label="Download invoice for order {{ $order->order_number ?? $order->id }}">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                </article>
            @empty
                <div class="ao-empty">
                    <i class="fas fa-inbox d-block mb-2 fa-2x"></i>
                    <div class="fw-black text-dark">No orders found</div>
                    <div class="ao-muted">Try adjusting search, status, restaurant or date filters.</div>
                </div>
            @endforelse
        </div>

        <div class="ao-pagination">
            <div class="ao-muted">
                Showing {{ $orders->firstItem() ?? 0 }} to {{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders
            </div>
            {{ $orders->withQueryString()->links() }}
        </div>
    </section>
</div>

<div class="modal fade" id="bulkStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #111827, var(--primary));">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fas fa-pen-to-square me-2"></i> Bulk Update Status
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
                    Cancelled paid orders may trigger refund processing according to backend rules.
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="bulkUpdateConfirmBtn" onclick="bulkUpdateStatus()">
                    <i class="fas fa-save me-2"></i>Update Orders
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #111827, var(--primary));">
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
                            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="all">All Orders</option>
                                @foreach($statusMeta as $status => $meta)
                                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-download me-2"></i>Export Excel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ao-loading" id="loadingSpinner">
    <div class="text-center text-white">
        <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status"></div>
        <p class="mt-3 mb-0 fw-bold">Updating orders...</p>
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
            [].slice.call(document.querySelectorAll('[title]')).forEach(el => new bootstrap.Tooltip(el));
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
            notifyOrderIndex('Please select at least one order', 'warning');
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
            notifyOrderIndex('Invalid order data', 'error');
            return;
        }

        const spinner = document.getElementById('loadingSpinner');
        if (spinner) spinner.style.display = 'flex';

        const updateBtn = document.getElementById('bulkUpdateConfirmBtn');
        const originalBtnHtml = updateBtn ? updateBtn.innerHTML : '';
        if (updateBtn) {
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
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
                notifyOrderIndex(data.message || 'Orders updated successfully.', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                notifyOrderIndex(data.message || 'Failed to update orders', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (spinner) spinner.style.display = 'none';
            if (updateBtn) {
                updateBtn.disabled = false;
                updateBtn.innerHTML = originalBtnHtml;
            }
            notifyOrderIndex('Network error occurred while updating orders', 'error');
        });
    }

    function notifyOrderIndex(message, type) {
        if (typeof showToastMessage === 'function') {
            showToastMessage(message, type);
            return;
        }

        alert(message);
    }
</script>
@endsection
