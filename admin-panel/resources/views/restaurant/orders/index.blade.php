@extends('layouts.restaurant')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8'));
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $canManageOrders = auth()->user()->hasRestaurantPermission('manage_orders') || auth()->user()->hasRestaurantPermission('update_order_status');
    $totalOrders = array_sum($statusCounts);
    $activeOrders = collect(['pending', 'confirmed', 'preparing', 'ready_for_pickup'])
        ->sum(fn ($status) => (int) ($statusCounts[$status] ?? 0));
    $statusMeta = [
        'pending' => ['label' => 'Pending', 'icon' => 'clock', 'tone' => 'warning'],
        'confirmed' => ['label' => 'Confirmed', 'icon' => 'check-circle', 'tone' => 'primary'],
        'preparing' => ['label' => 'Preparing', 'icon' => 'utensils', 'tone' => 'info'],
        'ready_for_pickup' => ['label' => 'Ready', 'icon' => 'box-open', 'tone' => 'success'],
        'delivered' => ['label' => 'Delivered', 'icon' => 'circle-check', 'tone' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'ban', 'tone' => 'danger'],
    ];
    $statusTiles = collect(['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'delivered'])
        ->map(function ($statusKey) use ($statusMeta, $statusCounts) {
            $meta = $statusMeta[$statusKey];

            return [
                'key' => $statusKey,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'tone' => $meta['tone'],
                'tile_color' => 'var(--' . $meta['tone'] . ')',
                'count' => (int) ($statusCounts[$statusKey] ?? 0),
            ];
        });
    $itemImageUrl = function ($image) {
        if (is_array($image)) {
            $image = collect($image)->filter()->first();
        }

        if (!$image) {
            return null;
        }

        return str_starts_with((string) $image, 'http://') || str_starts_with((string) $image, 'https://')
            ? $image
            : \Illuminate\Support\Facades\Storage::disk('public')->url($image);
    };
@endphp

@section('title', 'Orders Management')

@section('styles')
<style>
    .orders-shell {
        display: grid;
        gap: 18px;
        max-width: 100%;
        min-width: 0;
    }

    .orders-actionbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 16px;
    }

    .orders-filter-form {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) repeat(2, minmax(145px, 180px)) auto;
        gap: 10px;
        align-items: center;
    }

    .orders-status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 14px;
    }

    .order-status-tile {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        min-height: 102px;
        padding: 16px;
        color: inherit;
        text-decoration: none;
        border: 1px solid rgba(226, 232, 240, .88);
        border-radius: 22px;
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 16px 42px rgba(15, 23, 42, .06);
        transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }

    .order-status-tile:hover {
        transform: translateY(-2px);
        border-color: color-mix(in srgb, var(--primary) 34%, #e2e8f0);
        box-shadow: 0 22px 54px rgba(15, 23, 42, .09);
    }

    .order-status-tile.active {
        border-color: color-mix(in srgb, var(--primary) 54%, #e2e8f0);
        background:
            linear-gradient(180deg, rgba(255,255,255,.98), rgba(255,255,255,.92)),
            radial-gradient(circle at top right, color-mix(in srgb, var(--primary) 16%, transparent), transparent 42%);
    }

    .order-status-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .order-status-value {
        color: #0f172a;
        font-size: 27px;
        font-weight: 950;
        line-height: 1;
        margin-top: 6px;
    }

    .order-status-icon {
        width: 44px;
        height: 44px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        color: var(--tile-color);
        background: color-mix(in srgb, var(--tile-color) 13%, white);
        flex: 0 0 auto;
    }

    .orders-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(226, 232, 240, .88);
    }

    .orders-tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        color: #64748b;
        text-decoration: none;
        font-size: 12px;
        font-weight: 900;
        background: #f8fafc;
        border: 1px solid rgba(226, 232, 240, .9);
    }

    .orders-tab.active {
        color: #fff;
        background: var(--primary);
        border-color: var(--primary);
    }

    .orders-list {
        display: grid;
    }

    .order-row {
        display: grid;
        grid-template-columns: minmax(260px, .9fr) minmax(0, 1.35fr) minmax(220px, .72fr) auto;
        gap: 16px;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid rgba(226, 232, 240, .88);
    }

    .order-row:last-child {
        border-bottom: 0;
    }

    .order-id-block {
        min-width: 0;
    }

    .order-avatar {
        width: 48px;
        height: 48px;
        border-radius: 17px;
        display: grid;
        place-items: center;
        color: #fff;
        font-weight: 900;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        flex: 0 0 auto;
    }

    .order-number {
        color: #0f172a;
        font-size: 15px;
        font-weight: 950;
    }

    .order-meta {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .order-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 900;
        background: #f8fafc;
        color: #475569;
        border: 1px solid rgba(226, 232, 240, .9);
        white-space: nowrap;
    }

    .order-chip.status-pending { background: #fffbeb; color: #92400e; border-color: #fde68a; }
    .order-chip.status-confirmed { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .order-chip.status-preparing { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
    .order-chip.status-ready_for_pickup { background: #ecfdf5; color: #047857; border-color: #bbf7d0; }
    .order-chip.status-delivered { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .order-chip.status-cancelled { background: #fef2f2; color: #991b1b; border-color: #fecaca; }

    .order-items-preview {
        display: grid;
        gap: 8px;
        min-width: 0;
    }

    .order-item-line {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) auto;
        gap: 10px;
        align-items: center;
        min-width: 0;
    }

    .order-item-thumb,
    .order-item-thumb-placeholder {
        width: 42px;
        height: 42px;
        border-radius: 13px;
        border: 1px solid rgba(226, 232, 240, .96);
        background: #f8fafc;
        flex: 0 0 auto;
    }

    .order-item-thumb {
        object-fit: cover;
        display: block;
    }

    .order-item-thumb-placeholder {
        display: grid;
        place-items: center;
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 9%, white);
    }

    .order-money-card {
        padding: 13px;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid rgba(226, 232, 240, .9);
    }

    .order-total {
        color: #0f172a;
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .order-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
        min-width: 190px;
    }

    .order-empty {
        display: grid;
        place-items: center;
        min-height: 280px;
        padding: 34px;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 1400px) {
        .order-row {
            grid-template-columns: minmax(240px, .9fr) minmax(0, 1.1fr) minmax(210px, .8fr);
        }

        .order-actions {
            grid-column: 1 / -1;
            justify-content: flex-end;
            min-width: 0;
        }
    }

    @media (max-width: 900px) {
        .orders-actionbar,
        .orders-filter-form,
        .order-row {
            grid-template-columns: 1fr;
        }

        .orders-actionbar .d-flex,
        .order-actions {
            justify-content: stretch !important;
        }

        .orders-actionbar .btn,
        .orders-filter-form .btn,
        .order-actions .btn {
            width: 100%;
        }
    }
</style>
@endsection

@section('content')
<div class="orders-shell">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1>Orders</h1>
                <p>Manage and track restaurant orders.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-primary rounded-3" onclick="window.location.reload()">
                    <i class="fas fa-rotate me-2"></i> Refresh
                </button>
                <button class="btn btn-primary rounded-3" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-download me-2"></i> Export
                </button>
            </div>
        </div>
    </div>

    <section class="orders-status-grid">
        <a href="{{ route('restaurant.orders.index') }}" class="order-status-tile {{ !$currentStatus ? 'active' : '' }}" style="--tile-color: var(--primary);">
            <div>
                <div class="order-status-label">Total Orders</div>
                <div class="order-status-value">{{ number_format($totalOrders) }}</div>
            </div>
            <div class="order-status-icon"><i class="fas fa-bag-shopping"></i></div>
        </a>

        @foreach($statusTiles as $tile)
            <a href="{{ route('restaurant.orders.index', ['status' => $tile['key']]) }}"
               class="order-status-tile {{ $currentStatus === $tile['key'] ? 'active' : '' }}"
               style="--tile-color: {{ $tile['tile_color'] }};">
                <div>
                    <div class="order-status-label">{{ $tile['label'] }}</div>
                    <div class="order-status-value">{{ number_format($tile['count']) }}</div>
                </div>
                <div class="order-status-icon"><i class="fas fa-{{ $tile['icon'] }}"></i></div>
            </a>
        @endforeach
    </section>

    <div class="table-card overflow-hidden">
        <div class="orders-actionbar">
            <form method="GET" class="orders-filter-form">
                @if($currentStatus)
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif
                <input type="text" name="search" class="form-control" placeholder="Search order #, customer, phone" value="{{ $searchTerm }}">
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                <button type="submit" class="btn btn-primary rounded-3">
                    <i class="fas fa-search me-2"></i> Search
                </button>
            </form>
            <div class="text-end">
                <div class="small text-muted fw-semibold">Showing</div>
                <div class="fw-bold text-dark">{{ number_format($orders->total()) }} orders</div>
            </div>
        </div>

        <nav class="orders-tabs">
            <a href="{{ route('restaurant.orders.index') }}" class="orders-tab {{ !$currentStatus ? 'active' : '' }}">
                All <span>{{ number_format($totalOrders) }}</span>
            </a>
            @foreach($statuses as $statusKey => $statusLabel)
                <a href="{{ route('restaurant.orders.index', ['status' => $statusKey]) }}"
                   class="orders-tab {{ $currentStatus === $statusKey ? 'active' : '' }}">
                    {{ $statusLabel }} <span>{{ number_format($statusCounts[$statusKey] ?? 0) }}</span>
                </a>
            @endforeach
        </nav>

        <div class="orders-list">
            @forelse($orders as $order)
                @php
                    $itemsList = [];
                    if ($order->orderItems && $order->orderItems->count() > 0) {
                        $itemsList = $order->orderItems->toArray();
                    } elseif ($order->items) {
                        if (is_string($order->items)) {
                            $itemsList = json_decode($order->items, true) ?: [];
                        } elseif (is_array($order->items)) {
                            $itemsList = $order->items;
                        }
                    }
                    $itemsList = is_array($itemsList) ? $itemsList : [];
                    $statusClass = 'status-' . str_replace('-', '_', $order->status);
                    $customerInitial = strtoupper(substr($order->customer_name ?? $order->customer?->name ?? 'G', 0, 1));
                    $paymentOk = in_array($order->payment_status, ['success', 'paid', 'completed'], true);
                @endphp
                <article class="order-row">
                    <div class="d-flex align-items-center gap-3 order-id-block">
                        <div class="order-avatar">{{ $customerInitial }}</div>
                        <div class="min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <a href="{{ route('restaurant.orders.show', $order->id) }}" class="order-number text-decoration-none">#{{ $order->order_number }}</a>
                                <span class="order-chip {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            </div>
                            <div class="order-meta text-truncate">
                                <i class="fas fa-user me-1"></i>{{ $order->customer_name ?? $order->customer?->name ?? 'Guest' }}
                            </div>
                            <div class="order-meta">
                                <i class="fas fa-clock me-1"></i>{{ $order->created_at->format('d M Y, h:i A') }}
                            </div>
                            <div class="d-flex gap-2 flex-wrap mt-2">
                                <span class="order-chip"><i class="fas fa-phone"></i>{{ $order->customer_phone ?? 'N/A' }}</span>
                                <span class="order-chip"><i class="fas fa-credit-card"></i>{{ strtoupper($order->payment_method ?? 'N/A') }}</span>
                                <span class="order-chip">
                                    <i class="fas fa-circle" style="color: {{ $paymentOk ? '#10b981' : '#f59e0b' }}"></i>
                                    {{ ucfirst($order->payment_status ?? 'pending') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="order-items-preview">
                        @forelse(array_slice($itemsList, 0, 2) as $item)
                            @php
                                $itemName = 'Item';
                                $itemQty = 1;
                                $itemPrice = 0;
                                $itemImage = null;

                                if (is_array($item)) {
                                    $itemName = $item['name'] ?? $item['item_name'] ?? data_get($item, 'menu_item.name') ?? $item['title'] ?? 'Item';
                                    $itemQty = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
                                    $itemPrice = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                                    $itemImage = $itemImageUrl(
                                        $item['image']
                                        ?? $item['image_url']
                                        ?? $item['thumbnail']
                                        ?? $item['photo']
                                        ?? $item['images']
                                        ?? data_get($item, 'menu_item.image')
                                        ?? data_get($item, 'menu_item.images')
                                        ?? null
                                    );
                                }
                            @endphp
                            <div class="order-item-line">
                                @if($itemImage)
                                    <img src="{{ $itemImage }}" alt="{{ $itemName }}" class="order-item-thumb">
                                @else
                                    <div class="order-item-thumb-placeholder"><i class="fas fa-utensils"></i></div>
                                @endif
                                <div class="min-w-0">
                                    <div class="fw-bold text-dark text-truncate">{{ $itemName }}</div>
                                    <div class="small text-muted">Qty {{ $itemQty }}</div>
                                </div>
                                <div class="fw-bold text-nowrap">{{ $currencySymbol }}{{ number_format($itemPrice * $itemQty, $currencyDecimals) }}</div>
                            </div>
                        @empty
                            <div class="text-muted small fw-semibold">No items found</div>
                        @endforelse
                        @if(count($itemsList) > 2)
                            <div class="small text-muted fw-semibold">+{{ count($itemsList) - 2 }} more items</div>
                        @endif
                    </div>

                    <div class="order-money-card">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Subtotal</span>
                            <span>{{ $currencySymbol }}{{ number_format($order->subtotal, $currencyDecimals) }}</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Fees & tax</span>
                            <span>{{ $currencySymbol }}{{ number_format(((float) $order->delivery_fee + (float) ($order->platform_fee ?? 0) + (float) $order->tax), $currencyDecimals) }}</span>
                        </div>
                        @if((float) $order->discount > 0)
                            <div class="d-flex justify-content-between small text-success mb-1">
                                <span>Discount</span>
                                <span>-{{ $currencySymbol }}{{ number_format($order->discount, $currencyDecimals) }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between align-items-end mt-2 pt-2 border-top">
                            <span class="small text-muted fw-bold">Total</span>
                            <span class="order-total">{{ $currencySymbol }}{{ number_format($order->total, $currencyDecimals) }}</span>
                        </div>
                        @if(($order->payout_status ?? '') === 'Payout Released')
                            <span class="order-chip mt-2"><i class="fas fa-wallet"></i>Payout Released</span>
                        @endif
                    </div>

                    <div class="order-actions">
                        <a href="{{ route('restaurant.orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary rounded-3">
                            <i class="fas fa-eye me-1"></i> Details
                        </a>
                        @if($canManageOrders && $order->status == 'pending')
                            <button class="btn btn-sm btn-success rounded-3 accept-order" data-id="{{ $order->id }}">
                                <i class="fas fa-check me-1"></i> Accept
                            </button>
                            <button class="btn btn-sm btn-danger rounded-3 reject-order" data-id="{{ $order->id }}">
                                <i class="fas fa-times me-1"></i> Reject
                            </button>
                        @elseif($canManageOrders && $order->status == 'confirmed')
                            <button class="btn btn-sm btn-info rounded-3 update-status" data-id="{{ $order->id }}" data-status="preparing">
                                <i class="fas fa-utensils me-1"></i> Preparing
                            </button>
                        @elseif($canManageOrders && $order->status == 'preparing')
                            <button class="btn btn-sm btn-success rounded-3 update-status" data-id="{{ $order->id }}" data-status="ready_for_pickup">
                                <i class="fas fa-check-circle me-1"></i> Ready
                            </button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="order-empty">
                    <div>
                        <i class="fas fa-shopping-bag fa-4x text-muted opacity-25 mb-3"></i>
                        <h4 class="fw-bold text-dark">No Orders Found</h4>
                        <p class="mb-0">When customers place orders, they will appear here.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    <div class="d-flex justify-content-center">
        {{ $orders->withQueryString()->links() }}
    </div>
</div>

<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-download me-2"></i>Export Orders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" action="{{ route('restaurant.orders.export') }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" @selected($currentStatus === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Export CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Reject Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Please provide a reason for rejecting this order:</p>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="e.g., Out of stock, kitchen busy..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Order</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.accept-order').forEach((btn) => {
        btn.addEventListener('click', async function() {
            const orderId = this.dataset.id;
            const original = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Accepting';

            try {
                const response = await fetch(`/restaurant/orders/${orderId}/accept`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();

                if (data.success) {
                    showToast('Order accepted successfully!', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message || 'Failed to accept order', 'error');
                    this.disabled = false;
                    this.innerHTML = original;
                }
            } catch (error) {
                showToast('Error accepting order', 'error');
                this.disabled = false;
                this.innerHTML = original;
            }
        });
    });

    document.querySelectorAll('.reject-order').forEach((btn) => {
        btn.addEventListener('click', function() {
            const form = document.getElementById('rejectForm');
            form.action = `/restaurant/orders/${this.dataset.id}/reject`;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        });
    });

    document.querySelectorAll('.update-status').forEach((btn) => {
        btn.addEventListener('click', async function() {
            const orderId = this.dataset.id;
            const newStatus = this.dataset.status;
            const original = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Updating';

            try {
                const response = await fetch(`/restaurant/orders/${orderId}/update-status`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                });
                const data = await response.json();

                if (data.success) {
                    showToast('Status updated successfully!', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message || 'Failed to update status', 'error');
                    this.disabled = false;
                    this.innerHTML = original;
                }
            } catch (error) {
                showToast('Error updating status', 'error');
                this.disabled = false;
                this.innerHTML = original;
            }
        });
    });

    function showToast(message, type) {
        const toastContainer = document.createElement('div');
        toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '1100';
        const bgColor = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-warning');

        toastContainer.innerHTML = `
            <div class="toast align-items-center text-white ${bgColor} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toastContainer);
        const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'), { delay: 3000 });
        toast.show();
        setTimeout(() => toastContainer.remove(), 3300);
    }
</script>
@endsection
