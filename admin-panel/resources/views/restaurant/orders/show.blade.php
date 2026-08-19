@extends('layouts.restaurant')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $canManageOrders = auth()->check()
        && (auth()->user()->hasRestaurantPermission('manage_orders') || auth()->user()->hasRestaurantPermission('update_order_status'));

    $statusMeta = [
        'pending' => ['label' => 'Pending', 'icon' => 'clock', 'tone' => 'warning'],
        'confirmed' => ['label' => 'Confirmed', 'icon' => 'check-circle', 'tone' => 'primary'],
        'preparing' => ['label' => 'Preparing', 'icon' => 'utensils', 'tone' => 'info'],
        'ready_for_pickup' => ['label' => 'Ready', 'icon' => 'box-open', 'tone' => 'success'],
        'picked_up' => ['label' => 'Picked Up', 'icon' => 'truck', 'tone' => 'dark'],
        'on_the_way' => ['label' => 'On The Way', 'icon' => 'route', 'tone' => 'info'],
        'delivered' => ['label' => 'Delivered', 'icon' => 'home', 'tone' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'ban', 'tone' => 'danger'],
    ];

    $currentStatus = (string) $order->status;
    $currentStatusMeta = $statusMeta[$currentStatus] ?? ['label' => ucfirst(str_replace('_', ' ', $currentStatus)), 'icon' => 'circle', 'tone' => 'secondary'];
    $statusFlow = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way', 'delivered'];
    $currentStatusIndex = array_search($currentStatus, $statusFlow, true);
    $currentStatusIndex = $currentStatusIndex === false ? -1 : $currentStatusIndex;

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

    $rawItems = [];

    if ($order->relationLoaded('orderItems') && $order->orderItems && $order->orderItems->count() > 0) {
        $rawItems = $order->orderItems->toArray();
    } elseif ($order->items) {
        $rawItems = is_array($order->items) ? $order->items : json_decode($order->items, true);
    }

    $rawItems = is_array($rawItems) ? $rawItems : [];
    $orderItems = collect($rawItems)->map(function ($item) use ($itemImageUrl) {
        $itemName = data_get($item, 'name')
            ?? data_get($item, 'item_name')
            ?? data_get($item, 'menu_item.name')
            ?? data_get($item, 'title')
            ?? 'Item';
        $itemQty = (int) (data_get($item, 'quantity') ?? data_get($item, 'qty') ?? 1);
        $itemQty = max(1, $itemQty);
        $itemPrice = (float) (data_get($item, 'unit_price') ?? data_get($item, 'price') ?? 0);
        $itemTotal = (float) (data_get($item, 'total_price') ?? data_get($item, 'total') ?? ($itemPrice * $itemQty));

        if ($itemPrice <= 0 && $itemTotal > 0) {
            $itemPrice = $itemTotal / $itemQty;
        }

        $variantFields = data_get($item, 'selected_variant.custom_fields', []);
        $lineType = data_get($item, 'line_type') ?? data_get($variantFields, 'line_type');
        $notes = data_get($item, 'special_instructions') ?? data_get($item, 'notes');
        $isPromotionReward = $lineType === 'promotion_reward'
            || str_contains(strtolower((string) $notes), 'promotion reward');
        $promotionTitle = data_get($item, 'promotion_title')
            ?? data_get($variantFields, 'promotion_title');
        $promotionFreeQuantity = (int) (data_get($item, 'promotion_free_quantity')
            ?? data_get($variantFields, 'promotion_free_quantity')
            ?? 0);
        $promotionPaidQuantity = (int) (data_get($item, 'promotion_paid_quantity')
            ?? data_get($variantFields, 'promotion_paid_quantity')
            ?? max(0, $itemQty - $promotionFreeQuantity));
        $displayTotal = $promotionFreeQuantity > 0
            ? max(0, $promotionPaidQuantity) * $itemPrice
            : ($itemTotal > 0 ? $itemTotal : ($itemPrice * $itemQty));

        return [
            'name' => $itemName,
            'qty' => $itemQty,
            'price' => $itemPrice,
            'total' => $itemTotal > 0 ? $itemTotal : ($itemPrice * $itemQty),
            'display_total' => $displayTotal,
            'image' => $itemImageUrl(
                data_get($item, 'image')
                    ?? data_get($item, 'image_url')
                    ?? data_get($item, 'thumbnail')
                    ?? data_get($item, 'photo')
                    ?? data_get($item, 'images')
                    ?? data_get($item, 'menu_item.image')
                    ?? data_get($item, 'menu_item.images')
            ),
            'notes' => $notes,
            'is_promotion_reward' => $isPromotionReward,
            'promotion_title' => $promotionTitle,
            'promotion_free_quantity' => $promotionFreeQuantity,
            'promotion_paid_quantity' => $promotionPaidQuantity,
        ];
    })->values();

    $deliveryAddress = $order->delivery_address;

    if (!$deliveryAddress && is_array($order->customer_address)) {
        $deliveryAddress = collect($order->customer_address)->filter()->implode(', ');
    }

    $deliveryAddress = $deliveryAddress ?: 'Address not provided';
    $paymentStatus = (string) ($order->payment_status ?: 'pending');
    $paymentTone = in_array($paymentStatus, ['success', 'paid', 'completed'], true)
        ? 'success'
        : (in_array($paymentStatus, ['failed', 'cancelled'], true) ? 'danger' : 'warning');

    $nextAction = null;

    if ($currentStatus === 'confirmed') {
        $nextAction = ['status' => 'preparing', 'label' => 'Start Preparing', 'icon' => 'utensils', 'class' => 'btn-info'];
    } elseif ($currentStatus === 'preparing') {
        $nextAction = ['status' => 'ready_for_pickup', 'label' => 'Mark Ready for Pickup', 'icon' => 'circle-check', 'class' => 'btn-success'];
    }

    $summaryTiles = [
        ['label' => 'Order Total', 'value' => $currencySymbol . number_format((float) $order->total, $currencyDecimals), 'icon' => 'receipt', 'tone' => 'success'],
        ['label' => 'Items', 'value' => $orderItems->count(), 'icon' => 'shopping-basket', 'tone' => 'primary'],
        ['label' => 'Payment', 'value' => ucfirst(str_replace('_', ' ', $paymentStatus)), 'icon' => 'credit-card', 'tone' => $paymentTone],
        ['label' => 'Order Type', 'value' => ucfirst(str_replace('_', ' ', $order->order_type ?: 'delivery')), 'icon' => 'motorcycle', 'tone' => 'info'],
    ];
@endphp

@section('title', 'Order #' . $order->order_number)

@section('styles')
<style>
    .order-detail-shell {
        display: grid;
        gap: 18px;
        max-width: 100%;
        min-width: 0;
    }

    .order-topline {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        align-items: center;
        padding: 18px;
    }

    .order-title-row {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .order-detail-avatar {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        color: #fff;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        box-shadow: 0 18px 34px color-mix(in srgb, var(--primary) 22%, transparent);
    }

    .order-detail-title {
        color: #0f172a;
        font-size: 24px;
        font-weight: 950;
        line-height: 1.12;
        margin: 0;
    }

    .order-detail-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
        margin-top: 6px;
    }

    .order-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 9px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .summary-tile {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        min-height: 94px;
        padding: 16px;
        border: 1px solid rgba(226, 232, 240, .9);
        border-radius: 22px;
        background: rgba(255, 255, 255, .95);
        box-shadow: 0 16px 42px rgba(15, 23, 42, .06);
    }

    .summary-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 850;
    }

    .summary-value {
        color: #0f172a;
        font-size: 22px;
        font-weight: 950;
        line-height: 1.05;
        margin-top: 6px;
    }

    .summary-icon {
        width: 44px;
        height: 44px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        color: var(--summary-color);
        background: color-mix(in srgb, var(--summary-color) 12%, white);
    }

    .status-chip,
    .soft-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
        border: 1px solid rgba(226, 232, 240, .9);
    }

    .soft-chip {
        color: #475569;
        background: #f8fafc;
    }

    .status-chip.status-pending { color: #92400e; background: #fffbeb; border-color: #fde68a; }
    .status-chip.status-confirmed { color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; }
    .status-chip.status-preparing { color: #6d28d9; background: #f5f3ff; border-color: #ddd6fe; }
    .status-chip.status-ready_for_pickup { color: #047857; background: #ecfdf5; border-color: #bbf7d0; }
    .status-chip.status-picked_up,
    .status-chip.status-on_the_way { color: #075985; background: #f0f9ff; border-color: #bae6fd; }
    .status-chip.status-delivered { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
    .status-chip.status-cancelled { color: #991b1b; background: #fef2f2; border-color: #fecaca; }

    .detail-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(330px, .75fr);
        gap: 18px;
        align-items: start;
    }

    .detail-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, .9);
        border-radius: 24px;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 18px 52px rgba(15, 23, 42, .07);
    }

    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 17px 18px;
        border-bottom: 1px solid rgba(226, 232, 240, .88);
    }

    .panel-title {
        color: #0f172a;
        font-size: 16px;
        font-weight: 950;
        margin: 0;
    }

    .panel-subtitle {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        margin-top: 3px;
    }

    .panel-body {
        padding: 18px;
    }

    .status-track {
        display: grid;
        grid-template-columns: repeat(7, minmax(82px, 1fr));
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .status-step {
        position: relative;
        min-width: 82px;
        padding: 12px 9px;
        border-radius: 18px;
        color: #94a3b8;
        background: #f8fafc;
        border: 1px solid rgba(226, 232, 240, .92);
        text-align: center;
    }

    .status-step.done {
        color: #047857;
        background: #ecfdf5;
        border-color: #bbf7d0;
    }

    .status-step.active {
        color: #fff;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-color: transparent;
        box-shadow: 0 14px 30px color-mix(in srgb, var(--primary) 22%, transparent);
    }

    .status-step-icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        margin: 0 auto 8px;
        border-radius: 13px;
        background: rgba(255, 255, 255, .72);
    }

    .status-step.active .status-step-icon {
        background: rgba(255, 255, 255, .2);
    }

    .status-step-label {
        font-size: 11px;
        font-weight: 900;
        line-height: 1.2;
    }

    .order-items-list {
        display: grid;
    }

    .order-item-row {
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 15px 18px;
        border-bottom: 1px solid rgba(226, 232, 240, .86);
    }

    .order-item-row:last-child {
        border-bottom: 0;
    }

    .order-item-thumb,
    .order-item-placeholder {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        border: 1px solid rgba(226, 232, 240, .95);
        background: #f8fafc;
        flex: 0 0 auto;
    }

    .order-item-thumb {
        object-fit: cover;
        display: block;
    }

    .order-item-placeholder {
        display: grid;
        place-items: center;
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 9%, white);
    }

    .item-name {
        color: #0f172a;
        font-size: 14px;
        font-weight: 950;
        line-height: 1.22;
        margin: 0;
    }

    .item-meta,
    .muted-line {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .item-total {
        color: #0f172a;
        font-size: 15px;
        font-weight: 950;
        white-space: nowrap;
    }

    .bill-lines,
    .info-lines,
    .timeline-list {
        display: grid;
        gap: 0;
    }

    .bill-line,
    .info-line {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 11px 0;
        border-bottom: 1px solid rgba(226, 232, 240, .78);
    }

    .bill-line:last-child,
    .info-line:last-child {
        border-bottom: 0;
    }

    .bill-line span:first-child,
    .info-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .bill-line span:last-child,
    .info-value {
        color: #0f172a;
        font-size: 13px;
        font-weight: 900;
        text-align: right;
        word-break: break-word;
    }

    .bill-total {
        margin-top: 10px;
        padding: 14px;
        border-radius: 18px;
        color: #fff;
        background: linear-gradient(135deg, #0f766e, #16a34a);
    }

    .bill-total .bill-line {
        padding: 0;
        border: 0;
    }

    .bill-total span {
        color: #fff !important;
        font-size: 18px !important;
    }

    .timeline-item {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: 10px;
        padding: 11px 0;
    }

    .timeline-dot {
        width: 34px;
        height: 34px;
        border-radius: 13px;
        display: grid;
        place-items: center;
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 10%, white);
        border: 1px solid color-mix(in srgb, var(--primary) 18%, #e2e8f0);
    }

    .action-stack {
        display: grid;
        gap: 10px;
    }

    .empty-state {
        padding: 32px 18px;
        text-align: center;
        color: #64748b;
        font-weight: 800;
    }

    .print-btn {
        position: fixed;
        right: 28px;
        bottom: 28px;
        z-index: 99;
        width: 54px;
        height: 54px;
        border: 0;
        border-radius: 50%;
        display: grid;
        place-items: center;
        color: #fff;
        background: var(--primary);
        box-shadow: 0 18px 38px color-mix(in srgb, var(--primary) 28%, transparent);
    }

    @media (max-width: 1199.98px) {
        .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .detail-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 767.98px) {
        .order-topline {
            grid-template-columns: 1fr;
        }

        .order-actions {
            justify-content: flex-start;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }

        .order-item-row {
            grid-template-columns: 50px minmax(0, 1fr);
        }

        .order-item-thumb,
        .order-item-placeholder {
            width: 50px;
            height: 50px;
        }

        .item-total {
            grid-column: 2;
        }
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
            color: #111;
            font-size: 12px;
            background: #fff;
        }

        #invoicePrintArea .page-header,
        #invoicePrintArea .order-actions,
        #invoicePrintArea .status-track,
        #invoicePrintArea .summary-grid,
        #invoicePrintArea .side-column,
        #invoicePrintArea .panel-header .soft-chip,
        .modal,
        .modal-backdrop,
        .toast,
        .print-btn,
        .no-print {
            display: none !important;
        }

        #invoicePrintArea .order-detail-shell,
        #invoicePrintArea .detail-grid {
            display: block !important;
        }

        #invoicePrintArea .detail-panel,
        #invoicePrintArea .summary-tile {
            box-shadow: none !important;
            border: 0 !important;
            border-radius: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }

        #invoicePrintArea .panel-header,
        #invoicePrintArea .panel-body,
        #invoicePrintArea .order-item-row {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div id="invoicePrintArea" class="order-detail-shell">
        <div class="page-header mb-0">
            <div class="order-topline glass-card">
                <div class="order-title-row">
                    <div class="order-detail-avatar">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="min-w-0">
                        <h1 class="order-detail-title">Order #{{ $order->order_number }}</h1>
                        <div class="order-detail-meta">
                            <span><i class="fas fa-calendar me-1"></i>{{ $order->created_at->format('d M Y, h:i A') }}</span>
                            <span><i class="fas fa-user me-1"></i>{{ $order->customer_name ?: 'Guest' }}</span>
                            <span class="status-chip status-{{ $currentStatus }}">
                                <i class="fas fa-{{ $currentStatusMeta['icon'] }}"></i>
                                {{ $currentStatusMeta['label'] }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="order-actions">
                    <a href="{{ route('restaurant.orders.index') }}" class="btn btn-light no-print">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                    <button type="button" class="btn btn-outline-primary no-print" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>
            </div>
        </div>

        <div class="summary-grid">
            @foreach($summaryTiles as $tile)
                <div class="summary-tile">
                    <div>
                        <div class="summary-label">{{ $tile['label'] }}</div>
                        <div class="summary-value">{{ $tile['value'] }}</div>
                    </div>
                    <div class="summary-icon" style="--summary-color: var(--{{ $tile['tone'] }});">
                        <i class="fas fa-{{ $tile['icon'] }}"></i>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="detail-panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Order Timeline</h2>
                    <div class="panel-subtitle">Kitchen and delivery progress</div>
                </div>
                @if($currentStatus === 'cancelled')
                    <span class="status-chip status-cancelled">
                        <i class="fas fa-ban"></i>Cancelled
                    </span>
                @endif
            </div>
            <div class="panel-body">
                <div class="status-track">
                    @foreach($statusFlow as $index => $status)
                        @php
                            $stepMeta = $statusMeta[$status];
                            $stepClass = $currentStatus === $status ? 'active' : ($index < $currentStatusIndex ? 'done' : '');
                        @endphp
                        <div class="status-step {{ $stepClass }}">
                            <div class="status-step-icon">
                                <i class="fas fa-{{ $stepMeta['icon'] }}"></i>
                            </div>
                            <div class="status-step-label">{{ $stepMeta['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="detail-grid">
            <div class="main-column">
                <div class="detail-panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Order Items</h2>
                            <div class="panel-subtitle">{{ $orderItems->count() }} item{{ $orderItems->count() === 1 ? '' : 's' }} in this order</div>
                        </div>
                        <span class="soft-chip">
                            <i class="fas fa-basket-shopping"></i>{{ $orderItems->sum('qty') }} qty
                        </span>
                    </div>

                    <div class="order-items-list">
                        @forelse($orderItems as $item)
                            <div class="order-item-row">
                                @if($item['image'])
                                    <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="order-item-thumb">
                                @else
                                    <div class="order-item-placeholder">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                @endif

                                <div class="min-w-0">
                                    <h3 class="item-name">{{ $item['name'] }}</h3>
                                    <div class="item-meta">
                                        Qty {{ $item['qty'] }} x {{ $item['is_promotion_reward'] ? 'FREE' : $currencySymbol . number_format($item['price'], $currencyDecimals) }}
                                    </div>
                                    @if($item['promotion_free_quantity'] > 0)
                                        <div class="muted-line text-success fw-bold">
                                            <i class="fas fa-gift me-1"></i>
                                            {{ $item['promotion_free_quantity'] }} free with {{ $item['promotion_title'] ?: 'promotion' }}
                                        </div>
                                    @endif
                                    @if($item['is_promotion_reward'])
                                        <div class="muted-line text-success fw-bold">
                                            <i class="fas fa-gift me-1"></i>
                                            Promotion reward{{ $item['promotion_title'] ? ': ' . $item['promotion_title'] : '' }}
                                        </div>
                                    @endif
                                    @if($item['notes'])
                                        <div class="muted-line text-truncate">{{ $item['notes'] }}</div>
                                    @endif
                                </div>

                                <div class="item-total">
                                    @if($item['is_promotion_reward'])
                                        <span class="text-success">FREE</span>
                                    @else
                                        {{ $currencySymbol }}{{ number_format($item['display_total'], $currencyDecimals) }}
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="empty-state">
                                <i class="fas fa-box-open d-block mb-2"></i>
                                No items found for this order.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="detail-panel mt-3">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Customer And Delivery</h2>
                            <div class="panel-subtitle">Contact, address and delivery notes</div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="info-lines">
                            <div class="info-line">
                                <span class="info-label">Customer</span>
                                <span class="info-value">{{ $order->customer_name ?: 'Guest' }}</span>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Phone</span>
                                <span class="info-value">{{ $order->customer_phone ?: 'N/A' }}</span>
                            </div>
                            @if($order->customer)
                                <div class="info-line">
                                    <span class="info-label">Email</span>
                                    <span class="info-value">{{ $order->customer->email ?: 'N/A' }}</span>
                                </div>
                            @endif
                            <div class="info-line">
                                <span class="info-label">Delivery Address</span>
                                <span class="info-value">{{ $deliveryAddress }}</span>
                            </div>
                            @if($order->scheduled_time)
                                <div class="info-line">
                                    <span class="info-label">Scheduled For</span>
                                    <span class="info-value">{{ $order->scheduled_time->format('d M Y, h:i A') }}</span>
                                </div>
                            @endif
                            @if($order->special_instructions)
                                <div class="info-line">
                                    <span class="info-label">Instructions</span>
                                    <span class="info-value">{{ $order->special_instructions }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="side-column">
                <div class="detail-panel">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Bill Summary</h2>
                            <div class="panel-subtitle">Charges and payable amount</div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="bill-lines">
                            <div class="bill-line">
                                <span>Subtotal</span>
                                <span>{{ $currencySymbol }}{{ number_format((float) $order->subtotal, $currencyDecimals) }}</span>
                            </div>
                            <div class="bill-line">
                                <span>Delivery Fee</span>
                                <span>{{ $currencySymbol }}{{ number_format((float) $order->delivery_fee, $currencyDecimals) }}</span>
                            </div>
                            <div class="bill-line">
                                <span>Platform Fee</span>
                                <span>{{ $currencySymbol }}{{ number_format((float) ($order->platform_fee ?? 0), $currencyDecimals) }}</span>
                            </div>
                            <div class="bill-line">
                                <span>Taxes & Charges</span>
                                <span>{{ $currencySymbol }}{{ number_format((float) $order->tax, $currencyDecimals) }}</span>
                            </div>
                            @if((float) $order->discount > 0)
                                <div class="bill-line">
                                    <span>Discount</span>
                                    <span class="text-danger">-{{ $currencySymbol }}{{ number_format((float) $order->discount, $currencyDecimals) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="bill-total">
                            <div class="bill-line">
                                <span>Total Bill</span>
                                <span>{{ $currencySymbol }}{{ number_format((float) $order->total, $currencyDecimals) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-panel mt-3">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Payment</h2>
                            <div class="panel-subtitle">Method and transaction state</div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="info-lines">
                            <div class="info-line">
                                <span class="info-label">Method</span>
                                <span class="info-value">{{ strtoupper($order->payment_method ?: 'N/A') }}</span>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Status</span>
                                <span class="badge bg-{{ $paymentTone }}">{{ ucfirst(str_replace('_', ' ', $paymentStatus)) }}</span>
                            </div>
                            @if($order->payment_id)
                                <div class="info-line">
                                    <span class="info-label">Transaction ID</span>
                                    <span class="info-value">{{ $order->payment_id }}</span>
                                </div>
                            @endif
                            @if($order->delivery_otp)
                                <div class="info-line">
                                    <span class="info-label">Delivery OTP</span>
                                    <span class="info-value">{{ $order->delivery_otp }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="detail-panel mt-3">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Fulfilment</h2>
                            <div class="panel-subtitle">Driver and order event times</div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="timeline-list">
                            <div class="timeline-item">
                                <div class="timeline-dot"><i class="fas fa-plus"></i></div>
                                <div>
                                    <div class="item-name">Order placed</div>
                                    <div class="muted-line">{{ $order->created_at->format('d M Y, h:i A') }}</div>
                                </div>
                            </div>
                            @if($order->confirmed_at)
                                <div class="timeline-item">
                                    <div class="timeline-dot"><i class="fas fa-check"></i></div>
                                    <div>
                                        <div class="item-name">Confirmed</div>
                                        <div class="muted-line">{{ $order->confirmed_at->format('d M Y, h:i A') }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($order->preparing_at)
                                <div class="timeline-item">
                                    <div class="timeline-dot"><i class="fas fa-utensils"></i></div>
                                    <div>
                                        <div class="item-name">Preparing</div>
                                        <div class="muted-line">{{ $order->preparing_at->format('d M Y, h:i A') }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($order->ready_at)
                                <div class="timeline-item">
                                    <div class="timeline-dot"><i class="fas fa-box-open"></i></div>
                                    <div>
                                        <div class="item-name">Ready for pickup</div>
                                        <div class="muted-line">{{ $order->ready_at->format('d M Y, h:i A') }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($order->delivered_at)
                                <div class="timeline-item">
                                    <div class="timeline-dot"><i class="fas fa-home"></i></div>
                                    <div>
                                        <div class="item-name">Delivered</div>
                                        <div class="muted-line">{{ $order->delivered_at->format('d M Y, h:i A') }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($order->cancelled_at)
                                <div class="timeline-item">
                                    <div class="timeline-dot text-danger"><i class="fas fa-ban"></i></div>
                                    <div>
                                        <div class="item-name">Cancelled</div>
                                        <div class="muted-line">{{ $order->cancelled_at->format('d M Y, h:i A') }}</div>
                                        @if($order->cancellation_reason)
                                            <div class="muted-line">{{ $order->cancellation_reason }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="info-lines mt-2">
                            <div class="info-line">
                                <span class="info-label">Delivery Partner</span>
                                <span class="info-value">{{ $order->driver->name ?? 'Not assigned' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-panel mt-3 no-print">
                    <div class="panel-header">
                        <div>
                            <h2 class="panel-title">Actions</h2>
                            <div class="panel-subtitle">Update this order from the restaurant panel</div>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="action-stack">
                            @if($canManageOrders && $currentStatus === 'pending')
                                <button class="btn btn-success btn-lg accept-order" data-id="{{ $order->id }}">
                                    <i class="fas fa-check-circle me-2"></i>Accept Order
                                </button>
                                <button class="btn btn-danger btn-lg reject-order" data-id="{{ $order->id }}">
                                    <i class="fas fa-times-circle me-2"></i>Reject Order
                                </button>
                            @elseif($canManageOrders && $nextAction)
                                <button class="btn {{ $nextAction['class'] }} btn-lg update-status" data-status="{{ $nextAction['status'] }}">
                                    <i class="fas fa-{{ $nextAction['icon'] }} me-2"></i>{{ $nextAction['label'] }}
                                </button>
                            @else
                                <div class="empty-state py-2">
                                    <i class="fas fa-check-circle d-block mb-2"></i>
                                    No manual action available.
                                </div>
                            @endif

                            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print me-2"></i>Print Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Reject Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm">
                @csrf
                <div class="modal-body">
                    <p class="mb-2">Please provide a reason for rejecting this order:</p>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<button type="button" class="print-btn no-print" id="fabPrint" aria-label="Print order">
    <i class="fas fa-print"></i>
</button>

<script>
    let currentOrderId = {{ $order->id }};

    document.querySelector('.accept-order')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Accepting...';

        try {
            const response = await fetch(`/restaurant/orders/${currentOrderId}/accept`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            const data = await response.json();

            if (data.success) {
                showToast('Order accepted!', 'success');
                location.reload();
            } else {
                showToast(data.message || 'Unable to accept order', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Accept Order';
            }
        } catch (error) {
            showToast('Error accepting order', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Accept Order';
        }
    });

    document.querySelectorAll('.update-status').forEach(btn => {
        btn.addEventListener('click', async function() {
            const newStatus = this.dataset.status;
            this.disabled = true;

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
                    showToast(data.message || 'Unable to update status', 'error');
                    this.disabled = false;
                }
            } catch (error) {
                showToast('Error updating status', 'error');
                this.disabled = false;
            }
        });
    });

    document.querySelector('.reject-order')?.addEventListener('click', function() {
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    });

    document.getElementById('rejectForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('[type="submit"]');
        const reason = this.querySelector('[name="reason"]').value;
        submitBtn.disabled = true;

        try {
            const response = await fetch(`/restaurant/orders/${currentOrderId}/reject`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ reason: reason })
            });
            const data = await response.json();

            if (data.success) {
                showToast('Order rejected!', 'warning');
                location.reload();
            } else {
                showToast(data.message || 'Unable to reject order', 'error');
                submitBtn.disabled = false;
            }
        } catch (error) {
            showToast('Error rejecting order', 'error');
            submitBtn.disabled = false;
        }
    });

    document.getElementById('fabPrint')?.addEventListener('click', function() {
        window.print();
    });

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
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
        setTimeout(() => toast.remove(), 3200);
    }
</script>
@endsection
