@extends('layouts.restaurant')
@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
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
@php
    $canManageOrders = auth()->user()->hasRestaurantPermission('manage_orders') || auth()->user()->hasRestaurantPermission('update_order_status');
@endphp

@section('title', 'Orders Management')

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
    
    .order-header {
        padding: 15px 20px;
        color: white;
    }
    
    .order-header.pending { background: linear-gradient(135deg, #f6ad55, #ed8936); }
    .order-header.confirmed { background: linear-gradient(135deg, #4299e1, #3182ce); }
    .order-header.preparing { background: linear-gradient(135deg, #9f7aea, #805ad5); }
    .order-header.ready_for_pickup { background: linear-gradient(135deg, #48bb78, #38a169); }
    .order-header.delivered { background: linear-gradient(135deg, #38b2ac, #319795); }
    .order-header.cancelled { background: linear-gradient(135deg, #fc8181, #f56565); }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .counter-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
        cursor: pointer;
        border: 2px solid transparent;
    }
    
    .counter-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .counter-card.active {
        border-color: #FF6B35;
        background: #FFF7F5;
    }
    
    .counter-number {
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 5px;
    }
    
    .item-badge {
        background: #f7fafc;
        padding: 8px 12px;
        border-radius: 12px;
        margin-bottom: 8px;
    }

    .order-item-thumb {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        object-fit: cover;
        flex: 0 0 42px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
    }

    .order-item-thumb-placeholder {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        flex: 0 0 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff7ed;
        color: #f97316;
        border: 1px solid #fed7aa;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="display-6 fw-bold">Orders</h1>
                <p class="text-muted">Manage and track all your restaurant orders</p>
            </div>
            <div>
                <button class="btn btn-outline-primary" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-2"></i> Refresh
                </button>
                <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-download me-2"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Status Counters -->
    <div class="row mb-4">
        <div class="col-md-3 col-6 mb-3">
            <div class="counter-card {{ !$currentStatus ? 'active' : '' }}" onclick="window.location.href='{{ route('restaurant.orders.index') }}'">
                <div class="counter-number">{{ array_sum($statusCounts) }}</div>
                <div class="text-muted">Total Orders</div>
                <i class="fas fa-shopping-bag mt-2 d-block text-primary"></i>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="counter-card {{ $currentStatus == 'pending' ? 'active' : '' }}" onclick="window.location.href='{{ route('restaurant.orders.index', ['status' => 'pending']) }}'">
                <div class="counter-number text-warning">{{ $statusCounts['pending'] ?? 0 }}</div>
                <div class="text-muted">Pending</div>
                <i class="fas fa-clock mt-2 d-block text-warning"></i>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="counter-card {{ $currentStatus == 'confirmed' ? 'active' : '' }}" onclick="window.location.href='{{ route('restaurant.orders.index', ['status' => 'confirmed']) }}'">
                <div class="counter-number text-primary">{{ $statusCounts['confirmed'] ?? 0 }}</div>
                <div class="text-muted">Confirmed</div>
                <i class="fas fa-check-circle mt-2 d-block text-primary"></i>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="counter-card {{ $currentStatus == 'preparing' ? 'active' : '' }}" onclick="window.location.href='{{ route('restaurant.orders.index', ['status' => 'preparing']) }}'">
                <div class="counter-number text-info">{{ $statusCounts['preparing'] ?? 0 }}</div>
                <div class="text-muted">Preparing</div>
                <i class="fas fa-utensils mt-2 d-block text-info"></i>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="table-card mb-4">
        <div class="card-header bg-white">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="btn-group flex-wrap gap-2">
                        <a href="{{ route('restaurant.orders.index') }}" class="btn btn-sm {{ !$currentStatus ? 'btn-primary' : 'btn-light' }}">All</a>
                        <a href="{{ route('restaurant.orders.index', ['status' => 'pending']) }}" class="btn btn-sm {{ $currentStatus == 'pending' ? 'btn-warning' : 'btn-light' }}">Pending</a>
                        <a href="{{ route('restaurant.orders.index', ['status' => 'confirmed']) }}" class="btn btn-sm {{ $currentStatus == 'confirmed' ? 'btn-primary' : 'btn-light' }}">Confirmed</a>
                        <a href="{{ route('restaurant.orders.index', ['status' => 'preparing']) }}" class="btn btn-sm {{ $currentStatus == 'preparing' ? 'btn-info' : 'btn-light' }}">Preparing</a>
                        <a href="{{ route('restaurant.orders.index', ['status' => 'ready_for_pickup']) }}" class="btn btn-sm {{ $currentStatus == 'ready_for_pickup' ? 'btn-success' : 'btn-light' }}">Ready for Pickup</a>
                        <a href="{{ route('restaurant.orders.index', ['status' => 'delivered']) }}" class="btn btn-sm {{ $currentStatus == 'delivered' ? 'btn-success' : 'btn-light' }}">Delivered</a>
                        <a href="{{ route('restaurant.orders.index', ['status' => 'cancelled']) }}" class="btn btn-sm {{ $currentStatus == 'cancelled' ? 'btn-danger' : 'btn-light' }}">Cancelled</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search order #, customer..." value="{{ $searchTerm }}">
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders List -->
    @forelse($orders as $order)
    <div class="order-card">
        <div class="order-header {{ $order->status }}">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-receipt fa-2x"></i>
                        <div>
                            <h5 class="mb-0 fw-bold">#{{ $order->order_number }}</h5>
                            <small>{{ $order->created_at->format('d M Y, h:i A') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <i class="fas fa-user me-2"></i> {{ $order->customer_name ?? 'Guest' }}
                    <br>
                    <i class="fas fa-phone me-2"></i> {{ $order->customer_phone ?? 'N/A' }}
                </div>
                <div class="col-md-2">
                    <span class="status-badge bg-white text-dark">
                        {{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}
                    </span>
                </div>
                <div class="col-md-3 text-md-end">
                    <span class="status-badge bg-white text-{{ $order->status == 'cancelled' ? 'danger' : 'success' }}">
                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                    </span>
                </div>
            </div>
        </div>
        
        <div class="p-4">
            <div class="row">
                <div class="col-md-7">
                    <h6 class="fw-bold mb-3"><i class="fas fa-box me-2"></i>Order Items</h6>
                    @php
                        $itemsList = [];

                        // Prefer orderItems relation so menu item images are available.
                        if ($order->orderItems && $order->orderItems->count() > 0) {
                            $itemsList = $order->orderItems->toArray();
                        } elseif ($order->items) {
                            if (is_string($order->items)) {
                                $itemsList = json_decode($order->items, true);
                            } elseif (is_array($order->items)) {
                                $itemsList = $order->items;
                            }
                        }
                        
                        // Ensure it's an array
                        if (!is_array($itemsList)) {
                            $itemsList = [];
                        }
                    @endphp
                    
                    @if(count($itemsList) > 0)
                        @foreach(array_slice($itemsList, 0, 3) as $item)
                            @php
                                // Get item details safely
                                $itemName = 'Item';
                                $itemQty = 1;
                                $itemPrice = 0;
                                $itemImage = null;
                                
                                if (is_array($item)) {
                                    $itemName = $item['name'] ?? $item['item_name'] ?? data_get($item, 'menu_item.name') ?? $item['title'] ?? 'Item';
                                    $itemQty = $item['quantity'] ?? $item['qty'] ?? 1;
                                    $itemPrice = $item['price'] ?? $item['unit_price'] ?? 0;
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
                            <div class="item-badge">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div class="d-flex align-items-center gap-3 min-w-0">
                                        @if($itemImage)
                                            <img src="{{ $itemImage }}" alt="{{ $itemName }}" class="order-item-thumb">
                                        @else
                                            <div class="order-item-thumb-placeholder">
                                                <i class="fas fa-utensils"></i>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-truncate">{{ $itemName }}</div>
                                            <small class="text-muted">x{{ $itemQty }}</small>
                                        </div>
                                    </div>
                                    <span class="fw-bold text-nowrap">{{ $currencySymbol }}{{ number_format($itemPrice * $itemQty, App\Models\AppSetting::currencyDecimals()) }}</span>
                                </div>
                            </div>
                        @endforeach
                        @if(count($itemsList) > 3)
                            <small class="text-muted">+{{ count($itemsList) - 3 }} more items</small>
                        @endif
                    @else
                        <div class="text-muted">No items found</div>
                    @endif
                </div>
                <div class="col-md-5">
                    <div class="bg-light rounded p-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>{{ $currencySymbol }}{{ number_format($order->subtotal, App\Models\AppSetting::currencyDecimals()) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Delivery Fee:</span>
                            <span>{{ $currencySymbol }}{{ number_format($order->delivery_fee, App\Models\AppSetting::currencyDecimals()) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Platform Fee:</span>
                            <span>{{ $currencySymbol }}{{ number_format($order->platform_fee ?? 0, App\Models\AppSetting::currencyDecimals()) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Taxes & Charges:</span>
                            <span>{{ $currencySymbol }}{{ number_format($order->tax, App\Models\AppSetting::currencyDecimals()) }}</span>
                        </div>
                        @if((float) $order->discount > 0)
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Coupon Discount:</span>
                            <span>-{{ $currencySymbol }}{{ number_format($order->discount, App\Models\AppSetting::currencyDecimals()) }}</span>
                        </div>
                        @endif
                        <hr>
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total Bill Payable:</span>
                            <span class="text-primary">{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</span>
                        </div>
                        @if(($order->payout_status ?? '') === 'Payout Released')
                            <div class="mt-2">
                                <span class="badge bg-success"><i class="fas fa-wallet me-1"></i>Payout Released</span>
                            </div>
                        @endif
                        <div class="mt-2">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-credit-card me-1"></i> {{ strtoupper($order->payment_method) }}
                            </span>
                            <span class="badge bg-light text-dark ms-2">
                                <i class="fas fa-circle me-1" style="color: {{ $order->payment_status == 'success' ? '#10b981' : '#f59e0b' }}"></i>
                                {{ ucfirst($order->payment_status) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('restaurant.orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-eye me-1"></i> View Details
                </a>
                @if($canManageOrders && $order->status == 'pending')
                    <button class="btn btn-sm btn-success accept-order" data-id="{{ $order->id }}">
                        <i class="fas fa-check me-1"></i> Accept Order
                    </button>
                    <button class="btn btn-sm btn-danger reject-order" data-id="{{ $order->id }}">
                        <i class="fas fa-times me-1"></i> Reject
                    </button>
                @elseif($canManageOrders && $order->status == 'confirmed')
                    <button class="btn btn-sm btn-info update-status" data-id="{{ $order->id }}" data-status="preparing">
                        <i class="fas fa-utensils me-1"></i> Start Preparing
                    </button>
                @elseif($canManageOrders && $order->status == 'preparing')
                    <button class="btn btn-sm btn-success update-status" data-id="{{ $order->id }}" data-status="ready_for_pickup">
                        <i class="fas fa-check-circle me-1"></i> Ready for Pickup
                    </button>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="table-card text-center py-5">
        <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
        <h4>No Orders Found</h4>
        <p class="text-muted">When customers place orders, they will appear here</p>
    </div>
    @endforelse

    <!-- Pagination -->
    <div class="mt-4">
        {{ $orders->withQueryString()->links() }}
    </div>
</div>

<!-- Export Modal -->
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
                        <input type="date" name="date_from" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
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

<!-- Reject Order Modal -->
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
                    <textarea name="reason" class="form-control" rows="3" required placeholder="e.g., Out of stock, Kitchen busy..."></textarea>
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
    let currentRejectId = null;
    
    // Accept Order
    document.querySelectorAll('.accept-order').forEach(btn => {
        btn.addEventListener('click', async function() {
            const orderId = this.dataset.id;
            const btnElement = this;
            
            btnElement.disabled = true;
            btnElement.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Accepting...';
            
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
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to accept order', 'error');
                    btnElement.disabled = false;
                    btnElement.innerHTML = '<i class="fas fa-check me-1"></i> Accept Order';
                }
            } catch (error) {
                showToast('Error accepting order', 'error');
                btnElement.disabled = false;
                btnElement.innerHTML = '<i class="fas fa-check me-1"></i> Accept Order';
            }
        });
    });
    
    // Reject Order
    document.querySelectorAll('.reject-order').forEach(btn => {
        btn.addEventListener('click', function() {
            currentRejectId = this.dataset.id;
            const form = document.getElementById('rejectForm');
            form.action = `/restaurant/orders/${currentRejectId}/reject`;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        });
    });
    
    // Update Status
    document.querySelectorAll('.update-status').forEach(btn => {
        btn.addEventListener('click', async function() {
            const orderId = this.dataset.id;
            const newStatus = this.dataset.status;
            
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
                    location.reload();
                } else {
                    showToast(data.message || 'Failed to update status', 'error');
                }
            } catch (error) {
                showToast('Error updating status', 'error');
            }
        });
    });
    
    function showToast(message, type) {
        const toastContainer = document.createElement('div');
        toastContainer.className = `position-fixed bottom-0 end-0 p-3`;
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
        setTimeout(() => toastContainer.remove(), 3000);
    }
</script>
@endsection
