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

@section('title', 'Order #' . $order->order_number)

@section('styles')
<style>
    .detail-card {
        background: white;
        border-radius: 20px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .status-stepper {
        display: flex;
        justify-content: space-between;
        position: relative;
        margin: 30px 0;
    }
    
    .status-stepper::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 2px;
        background: #e2e8f0;
        z-index: 0;
    }
    
    .step {
        text-align: center;
        position: relative;
        z-index: 1;
        flex: 1;
    }
    
    .step-icon {
        width: 40px;
        height: 40px;
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        color: #a0aec0;
    }
    
    .step.completed .step-icon {
        background: #48bb78;
        border-color: #48bb78;
        color: white;
    }
    
    .step.active .step-icon {
        background: #FF6B35;
        border-color: #FF6B35;
        color: white;
    }
    
    .step-label {
        font-size: 12px;
        font-weight: 600;
        color: #718096;
    }

    .order-item-thumb {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        object-fit: cover;
        flex: 0 0 52px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
    }

    .order-item-thumb-placeholder {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        flex: 0 0 52px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff7ed;
        color: #f97316;
        border: 1px solid #fed7aa;
    }
    
    .step.completed .step-label,
    .step.active .step-label {
        color: #2d3748;
    }
    
    .item-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .item-row:last-child {
        border-bottom: none;
    }
    
    .print-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        border-radius: 25px;
        background: #FF6B35;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(255,107,53,0.3);
        cursor: pointer;
        transition: all 0.3s;
        z-index: 100;
    }
    
    .print-btn:hover {
        transform: scale(1.1);
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #invoicePrintArea,
        #invoicePrintArea * {
            visibility: visible;
        }

        #invoicePrintArea {
            position: absolute;
            top: 0;
            left: 0;
            width: 80mm;
            padding: 4mm;
            font-size: 12px;
            color: #111;
        }

        #invoicePrintArea .status-stepper,
        #invoicePrintArea .detail-card:last-child,
        #invoicePrintArea .detail-card .btn,
        #invoicePrintArea .no-print {
            display: none !important;
        }

        #invoicePrintArea .detail-card {
            box-shadow: none !important;
            border-radius: 0 !important;
            margin-bottom: 0 !important;
            padding: 0 !important;
        }

        .modal,
        .modal-backdrop,
        .toast,
        .print-btn {
            display: none !important;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div id="invoicePrintArea">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-6 fw-bold">Order Details</h1>
                    <p class="text-muted">Order #{{ $order->order_number }}</p>
                </div>
                <div>
                    <a href="{{ route('restaurant.orders.index') }}" class="btn btn-light no-print">
                        <i class="fas fa-arrow-left me-2"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>

    <!-- Status Stepper -->
    <div class="detail-card">
        <h5 class="fw-bold mb-4"><i class="fas fa-chart-line me-2 text-primary"></i>Order Status Timeline</h5>
        <div class="status-stepper">
            @php
                $statusFlow = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way', 'delivered'];
                $currentIndex = array_search($order->status, $statusFlow);
                $statusLabels = [
                    'pending' => 'Order Placed',
                    'confirmed' => 'Confirmed',
                    'preparing' => 'Preparing',
                    'ready_for_pickup' => 'Ready for Pickup',
                    'picked_up' => 'Picked Up',
                    'on_the_way' => 'On The Way',
                    'delivered' => 'Delivered'
                ];
            @endphp
            @foreach($statusFlow as $index => $status)
                @php
                    $isCompleted = $index <= $currentIndex;
                    $isActive = $index == $currentIndex;
                @endphp
                <div class="step {{ $isCompleted ? 'completed' : '' }} {{ $isActive ? 'active' : '' }}">
                    <div class="step-icon">
                        @if($status == 'pending') <i class="fas fa-clock"></i>
                        @elseif($status == 'confirmed') <i class="fas fa-check-circle"></i>
                        @elseif($status == 'preparing') <i class="fas fa-utensils"></i>
                        @elseif($status == 'ready_for_pickup') <i class="fas fa-box-open"></i>
                        @elseif($status == 'reached_pickup') <i class="fas fa-map-marker-alt"></i>
                        @elseif($status == 'picked_up') <i class="fas fa-truck"></i>
                        @elseif($status == 'on_the_way') <i class="fas fa-road"></i>
                        @elseif($status == 'delivered') <i class="fas fa-home"></i>
                        @endif
                    </div>
                    <div class="step-label">{{ $statusLabels[$status] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Order Items -->
        <div class="col-lg-7">
            <div class="detail-card">
                <h5 class="fw-bold mb-3"><i class="fas fa-box me-2 text-primary"></i>Order Items</h5>
                @php
                    $orderItems = [];

                    // Prefer orderItems relation so menu item images are available.
                    if ($order->orderItems && $order->orderItems->count() > 0) {
                        $orderItems = $order->orderItems->toArray();
                    } elseif ($order->items) {
                        if (is_string($order->items)) {
                            $orderItems = json_decode($order->items, true);
                        } elseif (is_array($order->items)) {
                            $orderItems = $order->items;
                        }
                    }
                    
                    if (!is_array($orderItems)) {
                        $orderItems = [];
                    }
                @endphp
                
                @if(count($orderItems) > 0)
                    @foreach($orderItems as $item)
                        @php
                            // Safe extraction of item details
                            $itemName = 'Item';
                            $itemQty = 1;
                            $itemPrice = 0;
                            $itemTotal = 0;
                            $itemImage = null;
                            
                            if (is_array($item)) {
                                $itemName = $item['name']
                                    ?? $item['item_name']
                                    ?? data_get($item, 'menu_item.name')
                                    ?? $item['title']
                                    ?? 'Item';
                                $itemQty = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
                                $itemPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
                                $itemTotal = (float) ($item['total_price'] ?? $item['total'] ?? ($itemPrice * $itemQty));
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

                                if ($itemPrice <= 0 && $itemQty > 0 && $itemTotal > 0) {
                                    $itemPrice = $itemTotal / $itemQty;
                                }
                            }
                        @endphp
                        <div class="item-row">
                            <div class="d-flex align-items-center gap-3">
                                @if($itemImage)
                                    <img src="{{ $itemImage }}" alt="{{ $itemName }}" class="order-item-thumb">
                                @else
                                    <div class="order-item-thumb-placeholder">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                @endif
                                <div>
                                    <div class="fw-semibold">{{ $itemName }}</div>
                                    <small class="text-muted">Qty: {{ $itemQty }} x {{ $currencySymbol }}{{ number_format($itemPrice, App\Models\AppSetting::currencyDecimals()) }}</small>
                                </div>
                            </div>
                            <div class="fw-bold">{{ $currencySymbol }}{{ number_format($itemTotal ?? ($itemPrice * $itemQty), App\Models\AppSetting::currencyDecimals()) }}</div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                        No items found for this order
                    </div>
                @endif
                
                <hr>
                
                <div class="info-row">
                    <span>Subtotal</span>
                    <span>{{ $currencySymbol }}{{ number_format($order->subtotal, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                <div class="info-row">
                    <span>Delivery Fee</span>
                    <span>{{ $currencySymbol }}{{ number_format($order->delivery_fee, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                <div class="info-row">
                    <span>Platform Fee</span>
                    <span>{{ $currencySymbol }}{{ number_format($order->platform_fee ?? 0, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                <div class="info-row">
                    <span>Taxes & Charges</span>
                    <span>{{ $currencySymbol }}{{ number_format($order->tax, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                @if($order->discount > 0)
                <div class="info-row">
                    <span>Discount</span>
                    <span class="text-danger">-{{ $currencySymbol }}{{ number_format($order->discount, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                @endif
                <div class="info-row fw-bold fs-5">
                    <span>Total Bill Payable</span>
                    <span class="text-primary">{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
            </div>
        </div>

        <!-- Right Column - Order Details -->
        <div class="col-lg-5">
            <!-- Customer Info -->
            <div class="detail-card">
                <h5 class="fw-bold mb-3"><i class="fas fa-user me-2 text-primary"></i>Customer Information</h5>
                <div class="info-row">
                    <span>Name</span>
                    <span class="fw-semibold">{{ $order->customer_name ?? 'Guest' }}</span>
                </div>
                <div class="info-row">
                    <span>Phone</span>
                    <span>{{ $order->customer_phone ?? 'N/A' }}</span>
                </div>
                @if($order->customer)
                <div class="info-row">
                    <span>Email</span>
                    <span>{{ $order->customer->email ?? 'N/A' }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span>Delivery Address</span>
                    <span class="text-muted">{{ $order->delivery_address ?? 'Address not provided' }}</span>
                </div>
                @if($order->scheduled_time)
                <div class="info-row">
                    <span>Scheduled For</span>
                    <span>{{ $order->scheduled_time->format('d M Y, h:i A') }}</span>
                </div>
                @endif
                @if($order->special_instructions)
                <div class="mt-3 p-3 bg-light rounded">
                    <div class="fw-semibold small mb-1">Special instructions</div>
                    <div class="small text-muted">{{ $order->special_instructions }}</div>
                </div>
                @endif
            </div>

            <!-- Payment Info -->
            <div class="detail-card">
                <h5 class="fw-bold mb-3"><i class="fas fa-credit-card me-2 text-primary"></i>Payment Information</h5>
                <div class="info-row">
                    <span>Payment Method</span>
                    <span class="fw-semibold">{{ strtoupper($order->payment_method) }}</span>
                </div>
                <div class="info-row">
                    <span>Payment Status</span>
                    <span class="badge {{ $order->payment_status == 'success' ? 'bg-success' : 'bg-warning' }}">
                        {{ ucfirst($order->payment_status) }}
                    </span>
                </div>
                @if($order->payment_id)
                <div class="info-row">
                    <span>Transaction ID</span>
                    <span class="small">{{ $order->payment_id }}</span>
                </div>
                @endif
            </div>

            <!-- Delivery Info -->
            <div class="detail-card">
                <h5 class="fw-bold mb-3"><i class="fas fa-truck me-2 text-primary"></i>Delivery Information</h5>
                <div class="info-row">
                    <span>Order Date</span>
                    <span>{{ $order->created_at->format('d M Y, h:i A') }}</span>
                </div>
                @if($order->delivered_at)
                <div class="info-row">
                    <span>Delivered At</span>
                    <span>{{ $order->delivered_at->format('d M Y, h:i A') }}</span>
                </div>
                @endif
                @if($order->cancelled_at)
                <div class="info-row">
                    <span>Cancelled At</span>
                    <span>{{ $order->cancelled_at->format('d M Y, h:i A') }}</span>
                </div>
                <div class="info-row">
                    <span>Cancellation Reason</span>
                    <span class="text-danger">{{ $order->cancellation_reason ?? 'No reason provided' }}</span>
                </div>
                @endif
                @if($order->driver)
                <div class="info-row">
                    <span>Delivery Partner</span>
                    <span>{{ $order->driver->name ?? 'Not assigned' }}</span>
                </div>
                @endif
            </div>

            <!-- Action Buttons -->
            <div class="detail-card">
                <h5 class="fw-bold mb-3"><i class="fas fa-cog me-2 text-primary"></i>Actions</h5>
                <div class="d-grid gap-2">
                    @if($order->status == 'pending')
                        <button class="btn btn-success btn-lg accept-order" data-id="{{ $order->id }}">
                            <i class="fas fa-check-circle me-2"></i> Accept Order
                        </button>
                        <button class="btn btn-danger btn-lg reject-order" data-id="{{ $order->id }}">
                            <i class="fas fa-times-circle me-2"></i> Reject Order
                        </button>
                    @elseif($order->status == 'confirmed')
                        <button class="btn btn-info btn-lg update-status" data-status="preparing">
                            <i class="fas fa-utensils me-2"></i> Start Preparing
                        </button>
                    @elseif($order->status == 'preparing')
                        <button class="btn btn-success btn-lg update-status" data-status="ready_for_pickup">
                            <i class="fas fa-check-circle me-2"></i> Mark Ready for Pickup
                        </button>
                    @endif
                    
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i> Print Order
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Reject Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm">
                @csrf
                <div class="modal-body">
                    <p>Please provide a reason for rejecting this order:</p>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Floating Action Button -->
<div class="print-btn" id="fabPrint">
    <i class="fas fa-print fa-lg"></i>
</div>

<script>
    let currentOrderId = {{ $order->id }};
    
    // Accept Order
    document.querySelector('.accept-order')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Accepting...';
        
        try {
            const response = await fetch(`/restaurant/orders/${currentOrderId}/accept`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            });
            const data = await response.json();
            
            if (data.success) {
                showToast('Order accepted!', 'success');
                location.reload();
            } else {
                showToast(data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Accept Order';
            }
        } catch (error) {
            showToast('Error accepting order', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Accept Order';
        }
    });
    
    // Update Status
    document.querySelectorAll('.update-status').forEach(btn => {
        btn.addEventListener('click', async function() {
            const newStatus = this.dataset.status;
            
            try {
                const response = await fetch(`/restaurant/orders/${currentOrderId}/update-status`, {
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
                    showToast('Status updated!', 'success');
                    location.reload();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Error updating status', 'error');
            }
        });
    });
    
    // Reject Order
    document.querySelector('.reject-order')?.addEventListener('click', function() {
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    });
    
    document.getElementById('rejectForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const reason = this.querySelector('[name="reason"]').value;
        
        try {
            const response = await fetch(`/restaurant/orders/${currentOrderId}/reject`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ reason: reason })
            });
            const data = await response.json();
            
            if (data.success) {
                showToast('Order rejected!', 'warning');
                location.reload();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            showToast('Error rejecting order', 'error');
        }
    });
    
    // FAB Print
    document.getElementById('fabPrint')?.addEventListener('click', function() {
        window.print();
    });
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `position-fixed bottom-0 end-0 p-3`;
        toast.style.zIndex = '1100';
        const bgColor = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-warning');
        toast.innerHTML = `
            <div class="toast align-items-center text-white ${bgColor} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast.querySelector('.toast'), { delay: 3000 });
        bsToast.show();
        setTimeout(() => toast.remove(), 3000);
    }
</script>
@endsection
