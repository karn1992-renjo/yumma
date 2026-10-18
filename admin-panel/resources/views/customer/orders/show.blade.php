@extends('layouts.app')

@section('title', 'Order #' . $order->order_number)

@section('styles')
<style>
    .status-chip {
        display: inline-block;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 11.5px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .status-chip.status-cancelled { color: #E11D28; background: #FFECEC; }
    .status-chip.status-delivered { color: #169B3A; background: #E8F8EA; }
    .status-chip.status-live { color: #11832D; background: #E6F7E7; }
    .status-chip.status-confirmed { color: #2563EB; background: #EAF2FF; }

    .order-timeline {
        display: flex;
        flex-direction: column;
    }

    .timeline-step {
        display: flex;
        gap: 14px;
    }

    .timeline-marker {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .timeline-circle {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: white;
        flex-shrink: 0;
        background: #F2F2F2;
        border: 2px solid #D8D8D8;
        box-sizing: border-box;
    }

    .timeline-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary);
    }

    .timeline-step.done .timeline-circle {
        background: var(--primary);
        border-color: var(--primary);
    }

    .timeline-step.current .timeline-circle {
        background: white;
        border-color: var(--primary);
    }

    .timeline-line {
        width: 2px;
        flex: 1;
        min-height: 24px;
        background: #E4E4E4;
    }

    .timeline-step.done .timeline-line {
        background: var(--primary);
    }

    .timeline-content {
        padding-bottom: 22px;
    }

    .timeline-label {
        font-size: 14px;
        font-weight: 800;
        color: #9A9A9A;
    }

    .timeline-step.done .timeline-label {
        color: #171717;
    }

    .timeline-step.current .timeline-label {
        color: var(--primary);
    }

    .timeline-sub {
        font-size: 12px;
        color: var(--app-muted, #6B7280);
        margin-top: 2px;
        font-weight: 500;
    }

    .item-card {
        background: #F8FAFC;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 10px;
    }
    
    .delivery-partner-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 20px;
        color: white;
    }
    
    .help-card {
        background: #FEF3C7;
        border-radius: 16px;
        padding: 20px;
    }
</style>
@endsection

@section('content')
@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $headerChipClass = match(true) {
        $order->status === 'cancelled' => 'status-cancelled',
        in_array($order->status, ['delivered', 'completed']) => 'status-delivered',
        in_array($order->status, ['on_the_way', 'picked_up', 'out_for_delivery']) => 'status-live',
        default => 'status-confirmed',
    };
@endphp
<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="{{ route('customer.orders.index') }}" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i> Back to Orders
                </a>
            </div>
            
            <!-- Order Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <h4 class="fw-bold mb-1">Order #{{ $order->order_number }}</h4>
                            <p class="text-muted mb-0">Placed on {{ $order->created_at->format('d M Y, h:i A') }}</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if(in_array($order->status, ['delivered', 'completed']))
                            <form action="{{ route('customer.orders.reorder', $order->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary rounded-pill reorder-btn">
                                    <i class="fas fa-redo-alt me-1"></i> Reorder
                                </button>
                            </form>
                            @endif
                            <span class="status-chip {{ $headerChipClass }}">
                                {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Tracking Timeline -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-chart-line me-2 text-primary"></i> Order Tracking
                    </h5>
                </div>
                <div class="card-body p-4">
                    @php
                        $statuses = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'reached_pickup', 'picked_up', 'on_the_way', 'delivered'];
                        $statusLabels = [
                            'pending' => 'Order Placed',
                            'confirmed' => 'Order Confirmed',
                            'preparing' => 'Preparing Your Food',
                            'ready_for_pickup' => 'Ready for Pickup',
                            'reached_pickup' => 'Reached Pickup',
                            'picked_up' => 'Picked Up',
                            'on_the_way' => 'On The Way',
                            'delivered' => 'Delivered'
                        ];
                        $currentIndex = array_search($order->status, $statuses);
                        $statusIcons = [
                            'pending' => 'fa-clock',
                            'confirmed' => 'fa-check-circle',
                            'preparing' => 'fa-utensils',
                            'ready_for_pickup' => 'fa-box-open',
                            'reached_pickup' => 'fa-map-marker-alt',
                            'picked_up' => 'fa-truck',
                            'on_the_way' => 'fa-road',
                            'delivered' => 'fa-home'
                        ];
                    @endphp
                    
                    <div class="order-timeline">
                    @foreach($statuses as $index => $status)
                        @php
                            $isDone = $index < $currentIndex;
                            $isCurrent = $index == $currentIndex;
                            $stepState = $isDone ? 'done' : ($isCurrent ? 'current' : 'pending');
                        @endphp
                        <div class="timeline-step {{ $stepState }}">
                            <div class="timeline-marker">
                                <span class="timeline-circle">
                                    @if($isDone)
                                        <i class="fas fa-check"></i>
                                    @elseif($isCurrent)
                                        <span class="timeline-dot"></span>
                                    @endif
                                </span>
                                @if(!$loop->last)<span class="timeline-line"></span>@endif
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-label">{{ $statusLabels[$status] }}</div>
                                @if($isCurrent && $order->status == 'pending')
                                    <div class="timeline-sub">Waiting for restaurant confirmation</div>
                                @elseif($isCurrent && $order->status == 'preparing')
                                    <div class="timeline-sub">Your food is being prepared</div>
                                @elseif($isCurrent && $order->status == 'on_the_way')
                                    <div class="timeline-sub">Your order is out for delivery</div>
                                @elseif($isDone)
                                    <div class="timeline-sub">Completed</div>
                                @elseif($isCurrent)
                                    <div class="timeline-sub">In progress</div>
                                @else
                                    <div class="timeline-sub">Pending</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-box me-2 text-primary"></i> Order Items
                    </h5>
                </div>
                <div class="card-body p-4">
                    @php
                        $items = [];
                        if (is_string($order->items)) {
                            $items = json_decode($order->items, true);
                        } elseif (is_array($order->items)) {
                            $items = $order->items;
                        }
                        
                        if (empty($items) && $order->orderItems) {
                            $items = $order->orderItems->toArray();
                        }
                    @endphp
                    
                    @foreach($items as $item)
                        @php
                            $itemName = $item['name'] ?? $item['item_name'] ?? 'Item';
                            $itemQty = $item['quantity'] ?? $item['qty'] ?? 1;
                            $itemPrice = $item['price'] ?? $item['unit_price'] ?? 0;
                        @endphp
                        <div class="item-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-white rounded p-2">
                                        <i class="fas fa-utensils fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0">{{ $itemName }}</h6>
                                        <small class="text-muted">Quantity: {{ $itemQty }}</small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-primary">{{ $currencySymbol }}{{ number_format($itemPrice * $itemQty, App\Models\AppSetting::currencyDecimals()) }}</span>
                                    <br>
                                    <small class="text-muted">{{ $currencySymbol }}{{ number_format($itemPrice, App\Models\AppSetting::currencyDecimals()) }} each</small>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    
                    <hr class="my-3">
                    
                    <div class="row">
                        <div class="col-md-6 offset-md-6">
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
                            @if($order->discount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span>Coupon Discount:</span>
                                <span class="text-danger">-{{ $currencySymbol }}{{ number_format($order->discount, App\Models\AppSetting::currencyDecimals()) }}</span>
                            </div>
                            @endif
                            <hr>
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span>Total Bill Payable:</span>
                                <span class="text-primary">{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Restaurant Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-store me-2 text-primary"></i> Restaurant
                    </h5>
                    <div class="d-flex gap-3">
                        <div class="bg-light rounded p-2">
                            <i class="fas fa-store fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">{{ $order->restaurant->name ?? 'Restaurant' }}</h6>
                            <p class="text-muted small mb-1">{{ $order->restaurant->address ?? '' }}</p>
                            <div class="rating-star">
                                <i class="fas fa-star"></i>
                                <span class="fw-bold">{{ number_format($order->restaurant->rating ?? 4.5, 1) }}</span>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('restaurant.show', $order->restaurant->slug ?? $order->restaurant_id) }}" class="btn btn-outline-primary w-100 mt-3 rounded-pill">
                        <i class="fas fa-eye me-2"></i> View Restaurant
                    </a>
                </div>
            </div>
            
            <!-- Delivery Partner Info -->
            @if($order->driver)
            <div class="delivery-partner-card mb-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-25 rounded-circle p-2">
                        <i class="fas fa-motorcycle fa-2x"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">Delivery Partner</div>
                        <div>{{ $order->driver->name ?? 'Partner' }}</div>
                        <small class="opacity-75">Contact: {{ $order->driver->phone ?? 'N/A' }}</small>
                    </div>
                    @if($order->driver->phone)
                    <a href="tel:{{ $order->driver->phone }}" class="btn btn-light rounded-circle p-2">
                        <i class="fas fa-phone text-primary"></i>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            @if($order->delivery_otp && !in_array($order->status, ['delivered', 'cancelled']))
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-lock me-2 text-warning"></i> Delivery OTP
                    </h5>
                    <div class="bg-warning bg-opacity-10 rounded-4 p-3 border border-warning border-opacity-25">
                        <div class="display-5 fw-bold text-warning mb-1" style="letter-spacing: 8px;">
                            {{ $order->delivery_otp }}
                        </div>
                        <small class="text-muted">
                            Share this OTP with the delivery partner only when the order reaches you.
                        </small>
                    </div>
                </div>
            </div>
            @endif
            
            <!-- Delivery Address -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-location-dot me-2 text-primary"></i> Delivery Address
                    </h5>
                    <div class="bg-light rounded p-3">
                        <p class="mb-1 fw-semibold">{{ $order->customer_name ?? 'Customer' }}</p>
                        <p class="mb-0 text-muted small">{{ $order->delivery_address }}</p>
                        <p class="mb-0 text-muted small">{{ $order->customer_phone ?? '' }}</p>
                    </div>
                    <button class="btn btn-outline-primary w-100 mt-3 rounded-pill" onclick="openInMaps()">
                        <i class="fas fa-map-marker-alt me-2"></i> View in Maps
                    </button>
                </div>
            </div>
            
            <!-- Payment Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-credit-card me-2 text-primary"></i> Payment
                    </h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Method:</span>
                        <span class="fw-semibold">{{ strtoupper($order->payment_method) }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Status:</span>
                        <span class="badge {{ $order->payment_status == 'success' ? 'bg-success' : 'bg-warning' }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Need Help? -->
            <div class="help-card">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-headset fa-2x text-warning"></i>
                    <div>
                        <h6 class="fw-bold mb-0">Need Help?</h6>
                        <small>Contact our support team</small>
                        <div class="mt-2">
                            <a href="tel:18001234567" class="text-dark text-decoration-none">
                                <i class="fas fa-phone me-1"></i> 1800-123-4567
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openInMaps() {
        const address = @json($order->delivery_address);
        if (address) {
            window.open(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`, '_blank');
        }
    }
</script>
@endsection
