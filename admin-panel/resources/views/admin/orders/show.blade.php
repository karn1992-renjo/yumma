@extends('layouts.admin')

@section('title', 'Order #' . ($order->order_number ?? $order->id))
@section('header', 'Order Details')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $currencyStep = number_format(1 / pow(10, $currencyDecimals), $currencyDecimals, '.', '');
    $money = fn ($value) => $currencySymbol . number_format((float) $value, $currencyDecimals);
    $formatRule = fn ($type, $value) => $type === 'fixed'
        ? $money($value)
        : number_format((float) $value, 2) . '%';

    $statusMeta = [
        'pending' => ['label' => 'Pending', 'icon' => 'clock', 'tone' => 'warning'],
        'confirmed' => ['label' => 'Confirmed', 'icon' => 'circle-check', 'tone' => 'primary'],
        'preparing' => ['label' => 'Preparing', 'icon' => 'utensils', 'tone' => 'info'],
        'ready_for_pickup' => ['label' => 'Ready', 'icon' => 'box-open', 'tone' => 'success'],
        'reached_pickup' => ['label' => 'Reached', 'icon' => 'location-dot', 'tone' => 'info'],
        'picked_up' => ['label' => 'Picked Up', 'icon' => 'person-biking', 'tone' => 'dark'],
        'on_the_way' => ['label' => 'On The Way', 'icon' => 'route', 'tone' => 'info'],
        'delivered' => ['label' => 'Delivered', 'icon' => 'house-circle-check', 'tone' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'ban', 'tone' => 'danger'],
    ];

    $currentStatus = (string) ($order->status ?: 'pending');
    $currentStatusMeta = $statusMeta[$currentStatus] ?? [
        'label' => ucfirst(str_replace('_', ' ', $currentStatus)),
        'icon' => 'circle',
        'tone' => 'secondary',
    ];

    $paymentStatus = (string) ($order->payment_status ?: 'pending');
    $paymentTone = in_array($paymentStatus, ['success', 'paid', 'completed'], true)
        ? 'success'
        : (in_array($paymentStatus, ['failed', 'cancelled'], true) ? 'danger' : 'warning');
    $canAssignDriver = ($order->order_type ?? 'delivery') !== 'takeaway'
        && in_array($currentStatus, ['confirmed', 'preparing', 'ready_for_pickup'], true);

    $itemImageUrl = function ($image) {
        if (is_array($image)) {
            $image = collect($image)->filter()->first();
        }

        if (!$image) {
            return null;
        }

        $image = (string) $image;

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return $image;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($image);
    };

    if ($order->orderItems && $order->orderItems->count() > 0) {
        $orderItems = $order->orderItems->map(function ($item) use ($itemImageUrl) {
            $qty = max(1, (int) ($item->quantity ?? 1));
            $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
            $totalPrice = (float) ($item->total_price ?? ($unitPrice * $qty));

            if ($unitPrice <= 0 && $totalPrice > 0) {
                $unitPrice = $totalPrice / $qty;
            }

            return [
                'name' => optional($item->menuItem)->name ?? $item->item_name ?? 'Item',
                'qty' => $qty,
                'price' => $unitPrice,
                'total' => $totalPrice,
                'image' => $itemImageUrl(optional($item->menuItem)->image_url ?? optional($item->menuItem)->image ?? optional($item->menuItem)->images),
                'notes' => $item->special_instructions,
                'variant' => $item->selected_variant,
                'addons' => $item->selected_add_ons,
            ];
        })->values();
    } else {
        $rawItems = is_array($order->items) ? $order->items : [];
        $orderItems = collect($rawItems)->map(function ($item) use ($itemImageUrl) {
            if (!is_array($item)) {
                return null;
            }

            $qty = max(1, (int) ($item['quantity'] ?? $item['qty'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
            $totalPrice = (float) ($item['total_price'] ?? $item['total'] ?? ($unitPrice * $qty));

            if ($unitPrice <= 0 && $totalPrice > 0) {
                $unitPrice = $totalPrice / $qty;
            }

            return [
                'name' => $item['name'] ?? $item['item_name'] ?? $item['title'] ?? data_get($item, 'menu_item.name') ?? 'Item',
                'qty' => $qty,
                'price' => $unitPrice,
                'total' => $totalPrice,
                'image' => $itemImageUrl(
                    $item['image_url']
                    ?? $item['image']
                    ?? $item['thumbnail']
                    ?? $item['images']
                    ?? data_get($item, 'menu_item.image_url')
                    ?? data_get($item, 'menu_item.images')
                ),
                'notes' => $item['special_instructions'] ?? $item['notes'] ?? null,
                'variant' => $item['selected_variant'] ?? $item['variant'] ?? null,
                'addons' => $item['selected_add_ons'] ?? $item['addons'] ?? null,
            ];
        })->filter()->values();
    }

    $deliveryAddress = $order->delivery_address;

    if (!$deliveryAddress && is_array($order->customer_address)) {
        $deliveryAddress = collect($order->customer_address)->filter()->implode(', ');
    }

    $deliveryAddress = $deliveryAddress ?: 'Address not provided';
    $orderType = ucfirst(str_replace('_', ' ', $order->order_type ?: 'delivery'));
    $serviceType = ucfirst(str_replace('_', ' ', $order->service_type ?: 'food'));

    $summaryTiles = [
        ['label' => 'Grand Total', 'value' => $money($order->total), 'icon' => 'receipt', 'tone' => '#16a34a'],
        ['label' => 'Items', 'value' => $orderItems->sum('qty'), 'icon' => 'basket-shopping', 'tone' => '#2563eb'],
        ['label' => 'Payment', 'value' => ucfirst(str_replace('_', ' ', $paymentStatus)), 'icon' => 'credit-card', 'tone' => $paymentTone === 'success' ? '#10b981' : ($paymentTone === 'danger' ? '#ef4444' : '#f59e0b')],
        ['label' => 'Delivery KM', 'value' => $deliveryDistanceKm !== null ? number_format($deliveryDistanceKm, 2) . ' km' : 'N/A', 'icon' => 'route', 'tone' => '#8b5cf6'],
    ];

    $formatChoiceList = function ($value) {
        if (!is_array($value)) {
            return $value;
        }

        return collect($value)->map(function ($entry, $key) {
            if (is_array($entry)) {
                return collect($entry)->filter()->implode(' ');
            }

            return is_string($key) ? $key . ': ' . $entry : $entry;
        })->filter()->implode(', ');
    };
@endphp

@section('styles')
<style>
    .admin-order-shell {
        display: grid;
        gap: 18px;
        max-width: 100%;
        min-width: 0;
    }

    .admin-order-hero,
    .admin-order-card,
    .admin-order-tile {
        border: 1px solid rgba(226, 232, 240, .9);
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 18px 48px rgba(15, 23, 42, .07);
    }

    .admin-order-hero {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        align-items: center;
        padding: 18px;
        border-radius: 24px;
    }

    .admin-order-title-row {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .admin-order-avatar {
        width: 54px;
        height: 54px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        border-radius: 18px;
        color: #fff;
        background: linear-gradient(135deg, #111827, var(--primary));
    }

    .admin-order-title {
        margin: 0;
        color: #0f172a;
        font-size: 24px;
        font-weight: 950;
        line-height: 1.12;
        letter-spacing: 0;
    }

    .admin-order-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 7px;
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
    }

    .admin-order-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 9px;
    }

    .admin-order-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 999px;
        padding: 7px 11px;
        border: 1px solid rgba(226, 232, 240, .9);
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .admin-order-chip.neutral { color: #475569; background: #f8fafc; }
    .admin-order-chip.status-pending { color: #92400e; background: #fffbeb; border-color: #fde68a; }
    .admin-order-chip.status-confirmed { color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; }
    .admin-order-chip.status-preparing { color: #6d28d9; background: #f5f3ff; border-color: #ddd6fe; }
    .admin-order-chip.status-ready_for_pickup { color: #047857; background: #ecfdf5; border-color: #bbf7d0; }
    .admin-order-chip.status-reached_pickup,
    .admin-order-chip.status-picked_up,
    .admin-order-chip.status-on_the_way { color: #075985; background: #f0f9ff; border-color: #bae6fd; }
    .admin-order-chip.status-delivered { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
    .admin-order-chip.status-cancelled { color: #991b1b; background: #fef2f2; border-color: #fecaca; }

    .admin-order-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .admin-order-tile {
        min-height: 96px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 16px;
        border-radius: 22px;
    }

    .admin-order-tile-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 850;
    }

    .admin-order-tile-value {
        margin-top: 6px;
        color: #0f172a;
        font-size: 22px;
        font-weight: 950;
        line-height: 1.05;
    }

    .admin-order-tile-icon {
        width: 46px;
        height: 46px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        border-radius: 16px;
        color: var(--tile-color);
        background: color-mix(in srgb, var(--tile-color) 12%, white);
    }

    .admin-order-card {
        min-width: 0;
        overflow: hidden;
        border-radius: 24px;
    }

    .admin-order-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 17px 18px;
        border-bottom: 1px solid rgba(226, 232, 240, .88);
    }

    .admin-order-card-title {
        margin: 0;
        color: #0f172a;
        font-size: 16px;
        font-weight: 950;
    }

    .admin-order-card-subtitle {
        margin-top: 3px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .admin-order-card-body {
        padding: 18px;
    }

    .admin-order-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(340px, .78fr);
        gap: 18px;
        align-items: start;
    }

    .admin-status-track {
        display: grid;
        grid-template-columns: repeat(8, minmax(86px, 1fr));
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .admin-status-step {
        min-width: 86px;
        padding: 12px 9px;
        border: 1px solid rgba(226, 232, 240, .92);
        border-radius: 18px;
        color: #94a3b8;
        background: #f8fafc;
        text-align: center;
    }

    .admin-status-step.done {
        color: #047857;
        background: #ecfdf5;
        border-color: #bbf7d0;
    }

    .admin-status-step.active {
        color: #fff;
        border-color: transparent;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        box-shadow: 0 14px 30px rgba(139, 92, 246, .2);
    }

    .admin-status-step-icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        margin: 0 auto 8px;
        border-radius: 13px;
        background: rgba(255, 255, 255, .72);
    }

    .admin-status-step.active .admin-status-step-icon {
        background: rgba(255, 255, 255, .2);
    }

    .admin-status-step-label {
        font-size: 11px;
        font-weight: 900;
        line-height: 1.2;
    }

    .admin-status-step-time {
        margin-top: 5px;
        font-size: 10px;
        font-weight: 800;
        opacity: .78;
    }

    .admin-status-step-date {
        margin-top: 2px;
        font-size: 9px;
        font-weight: 800;
        opacity: .62;
    }

    .admin-item-row {
        display: grid;
        grid-template-columns: 60px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 15px 18px;
        border-bottom: 1px solid rgba(226, 232, 240, .86);
    }

    .admin-item-row:last-child {
        border-bottom: 0;
    }

    .admin-item-thumb,
    .admin-item-placeholder {
        width: 60px;
        height: 60px;
        border-radius: 18px;
        border: 1px solid rgba(226, 232, 240, .95);
        background: #f8fafc;
    }

    .admin-item-thumb {
        display: block;
        object-fit: cover;
    }

    .admin-item-placeholder {
        display: grid;
        place-items: center;
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 9%, white);
    }

    .admin-item-name {
        margin: 0;
        color: #0f172a;
        font-size: 14px;
        font-weight: 950;
        line-height: 1.22;
    }

    .admin-muted,
    .admin-item-meta {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .admin-item-total {
        color: #0f172a;
        font-size: 15px;
        font-weight: 950;
        white-space: nowrap;
    }

    .admin-line-list {
        display: grid;
        gap: 0;
    }

    .admin-line {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 11px 0;
        border-bottom: 1px solid rgba(226, 232, 240, .78);
    }

    .admin-line:last-child {
        border-bottom: 0;
    }

    .admin-line-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .admin-line-value {
        color: #0f172a;
        font-size: 13px;
        font-weight: 900;
        text-align: right;
        word-break: break-word;
    }

    .admin-bill-total {
        margin-top: 10px;
        padding: 14px;
        border-radius: 18px;
        color: #fff;
        background: linear-gradient(135deg, #0f766e, #16a34a);
    }

    .admin-bill-total .admin-line {
        padding: 0;
        border: 0;
    }

    .admin-bill-total span {
        color: #fff !important;
        font-size: 18px !important;
    }

    .admin-timeline-list {
        display: grid;
        gap: 4px;
    }

    .admin-timeline-item {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: 10px;
        padding: 10px 0;
    }

    .admin-timeline-dot {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 13px;
        color: var(--primary);
        background: color-mix(in srgb, var(--primary) 10%, white);
        border: 1px solid color-mix(in srgb, var(--primary) 18%, #e2e8f0);
    }

    .admin-action-stack {
        display: grid;
        gap: 10px;
    }

    .admin-empty-state {
        padding: 30px 18px;
        text-align: center;
        color: #64748b;
        font-weight: 800;
    }

    .admin-order-table {
        margin: 0;
    }

    .admin-order-table th {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
        background: #f8fafc;
        border-color: #e2e8f0;
    }

    .admin-order-table td {
        color: #0f172a;
        font-size: 12px;
        font-weight: 750;
        vertical-align: middle;
        border-color: #e2e8f0;
    }

    @media (max-width: 1199.98px) {
        .admin-order-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .admin-order-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 767.98px) {
        .admin-order-hero {
            grid-template-columns: 1fr;
        }

        .admin-order-actions {
            justify-content: flex-start;
        }

        .admin-order-summary {
            grid-template-columns: 1fr;
        }

        .admin-item-row {
            grid-template-columns: 52px minmax(0, 1fr);
        }

        .admin-item-thumb,
        .admin-item-placeholder {
            width: 52px;
            height: 52px;
        }

        .admin-item-total {
            grid-column: 2;
        }
    }
</style>
@endsection

@section('content')
<div class="admin-order-shell">
    <section class="admin-order-hero">
        <div class="admin-order-title-row">
            <div class="admin-order-avatar">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="min-w-0">
                <h1 class="admin-order-title">Order #{{ $order->order_number ?? $order->id }}</h1>
                <div class="admin-order-meta">
                    <span><i class="fas fa-calendar me-1"></i>{{ $order->created_at->format('d M Y, h:i A') }}</span>
                    <span><i class="fas fa-store me-1"></i>{{ optional($order->restaurant)->name ?? 'No store' }}</span>
                    <span class="admin-order-chip status-{{ $currentStatus }}">
                        <i class="fas fa-{{ $currentStatusMeta['icon'] }}"></i>{{ $currentStatusMeta['label'] }}
                    </span>
                    <span class="admin-order-chip neutral">
                        <i class="fas fa-truck-fast"></i>{{ $orderType }}
                    </span>
                    <span class="admin-order-chip neutral">
                        <i class="fas fa-layer-group"></i>{{ $serviceType }}
                    </span>
                </div>
            </div>
        </div>

        <div class="admin-order-actions">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <a href="{{ route('admin.orders.invoice', $order->id) }}" class="btn btn-outline-primary">
                <i class="fas fa-download me-2"></i>Invoice
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print
            </button>
        </div>
    </section>

    <section class="admin-order-summary">
        @foreach($summaryTiles as $tile)
            <div class="admin-order-tile">
                <div>
                    <div class="admin-order-tile-label">{{ $tile['label'] }}</div>
                    <div class="admin-order-tile-value">{{ $tile['value'] }}</div>
                </div>
                <div class="admin-order-tile-icon" style="--tile-color: {{ $tile['tone'] }};">
                    <i class="fas fa-{{ $tile['icon'] }}"></i>
                </div>
            </div>
        @endforeach
    </section>

    <section class="admin-order-card">
        <div class="admin-order-card-header">
            <div>
                <h2 class="admin-order-card-title">Order Timeline</h2>
                <div class="admin-order-card-subtitle">Operational progress from placement to delivery.</div>
            </div>
            @if($order->payout_status)
                <span class="admin-order-chip neutral">
                    <i class="fas fa-wallet"></i>{{ $order->payout_status }}
                </span>
            @endif
        </div>
        <div class="admin-order-card-body">
            <div class="admin-status-track">
                @foreach($timeline as $step)
                    @php
                        $stepStatus = $step['status'] ?? 'pending';
                        $stepMeta = $statusMeta[$stepStatus] ?? ['label' => $step['label'] ?? ucfirst($stepStatus), 'icon' => 'circle'];
                        $stepClass = $currentStatus === $stepStatus ? 'active' : (!empty($step['completed']) ? 'done' : '');
                    @endphp
                    <div class="admin-status-step {{ $stepClass }}">
                        <div class="admin-status-step-icon">
                            <i class="fas fa-{{ $stepMeta['icon'] }}"></i>
                        </div>
                        <div class="admin-status-step-label">{{ $step['label'] ?? $stepMeta['label'] }}</div>
                        @if(!empty($step['timestamp']))
                            <div class="admin-status-step-time">{{ \Carbon\Carbon::parse($step['timestamp'])->format('h:i A') }}</div>
                            <div class="admin-status-step-date">{{ \Carbon\Carbon::parse($step['timestamp'])->format('d M Y') }}</div>
                        @else
                            <div class="admin-status-step-time">--</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="admin-order-grid">
        <div class="main-column">
            <section class="admin-order-card">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Order Items</h2>
                        <div class="admin-order-card-subtitle">{{ $orderItems->count() }} line item{{ $orderItems->count() === 1 ? '' : 's' }} and {{ $orderItems->sum('qty') }} total quantity.</div>
                    </div>
                    <span class="admin-order-chip neutral">
                        <i class="fas fa-basket-shopping"></i>{{ $orderItems->sum('qty') }} qty
                    </span>
                </div>

                <div>
                    @forelse($orderItems as $item)
                        <div class="admin-item-row">
                            @if($item['image'])
                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="admin-item-thumb">
                            @else
                                <div class="admin-item-placeholder">
                                    <i class="fas fa-utensils"></i>
                                </div>
                            @endif

                            <div class="min-w-0">
                                <h3 class="admin-item-name">{{ $item['name'] }}</h3>
                                <div class="admin-item-meta">Qty {{ $item['qty'] }} x {{ $money($item['price']) }}</div>
                                @if($item['notes'])
                                    <div class="admin-muted text-truncate">{{ $item['notes'] }}</div>
                                @endif
                                @if($formatChoiceList($item['variant']))
                                    <div class="admin-muted text-truncate">Variant: {{ $formatChoiceList($item['variant']) }}</div>
                                @endif
                            </div>

                            <div class="admin-item-total">{{ $money($item['total']) }}</div>
                        </div>
                    @empty
                        <div class="admin-empty-state">
                            <i class="fas fa-box-open d-block mb-2"></i>
                            No items found for this order.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="admin-order-card mt-3">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Customer And Delivery</h2>
                        <div class="admin-order-card-subtitle">Customer contact, destination, instructions and schedule.</div>
                    </div>
                </div>
                <div class="admin-order-card-body">
                    <div class="admin-line-list">
                        <div class="admin-line">
                            <span class="admin-line-label">Customer</span>
                            <span class="admin-line-value">{{ $order->customer_name ?: optional($order->customer)->name ?: 'Guest' }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Phone</span>
                            <span class="admin-line-value">{{ $order->customer_phone ?: optional($order->customer)->phone ?: 'N/A' }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Email</span>
                            <span class="admin-line-value">{{ $order->customer_email ?: optional($order->customer)->email ?: 'N/A' }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Delivery Address</span>
                            <span class="admin-line-value">{{ $deliveryAddress }}</span>
                        </div>
                        @if($order->scheduled_time)
                            <div class="admin-line">
                                <span class="admin-line-label">Scheduled For</span>
                                <span class="admin-line-value">{{ $order->scheduled_time->format('d M Y, h:i A') }}</span>
                            </div>
                        @endif
                        @if($order->special_instructions)
                            <div class="admin-line">
                                <span class="admin-line-label">Instructions</span>
                                <span class="admin-line-value">{{ $order->special_instructions }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="admin-order-card mt-3">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Transactions</h2>
                        <div class="admin-order-card-subtitle">Payment gateway and ledger rows recorded for this order.</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table admin-order-table">
                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Type</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->transactions as $transaction)
                                <tr>
                                    <td>{{ $transaction->transaction_id ?? $transaction->razorpay_id ?? 'N/A' }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $transaction->type ?? 'payment')) }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $transaction->payment_method ?? $order->payment_method ?? 'N/A')) }}</td>
                                    <td>
                                        <span class="badge bg-{{ ($transaction->status ?? '') === 'success' ? 'success' : (($transaction->status ?? '') === 'failed' ? 'danger' : 'secondary') }}">
                                            {{ ucfirst($transaction->status ?? 'N/A') }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ $money($transaction->amount ?? 0) }}</td>
                                    <td>{{ optional($transaction->created_at)->format('d M Y, h:i A') ?: 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No transaction rows found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-order-card mt-3">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Payment Attempts</h2>
                        <div class="admin-order-card-subtitle">Links, retries, COD collection or driver-created attempts.</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table admin-order-table">
                        <thead>
                            <tr>
                                <th>Attempt</th>
                                <th>Source</th>
                                <th>Gateway</th>
                                <th>Status</th>
                                <th>Reference</th>
                                <th>Collected By</th>
                                <th class="text-end">Amount</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->paymentAttempts->sortByDesc('created_at') as $attempt)
                                <tr>
                                    <td>#{{ $attempt->id }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $attempt->source)) }}</td>
                                    <td>{{ ucfirst($attempt->gateway) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $attempt->status === 'success' ? 'success' : (in_array($attempt->status, ['failed', 'expired', 'cancelled'], true) ? 'danger' : 'warning text-dark') }}">
                                            {{ ucfirst($attempt->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $attempt->transaction_id ?: ($attempt->payment_link_id ?: ($attempt->gateway_reference ?: 'N/A')) }}
                                        @if($attempt->payment_link)
                                            <div><a href="{{ $attempt->payment_link }}" target="_blank" rel="noopener">Payment link</a></div>
                                        @endif
                                    </td>
                                    <td>{{ optional($attempt->driver ?? $attempt->creator)->name ?? 'Customer' }}</td>
                                    <td class="text-end">{{ $money($attempt->amount) }}</td>
                                    <td>{{ optional($attempt->created_at)->format('d M Y, h:i A') ?: 'N/A' }}</td>
                                </tr>
                                @if($attempt->collection_notes)
                                    <tr>
                                        <td colspan="8" class="text-muted">Notes: {{ $attempt->collection_notes }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No payment attempts recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="side-column">
            <section class="admin-order-card">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Bill Summary</h2>
                        <div class="admin-order-card-subtitle">Charges and payable amount.</div>
                    </div>
                </div>
                <div class="admin-order-card-body">
                    <div class="admin-line-list">
                        <div class="admin-line">
                            <span class="admin-line-label">Subtotal</span>
                            <span class="admin-line-value">{{ $money($order->subtotal ?? $order->total) }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Delivery Fee</span>
                            <span class="admin-line-value">{{ $money($order->delivery_fee ?? 0) }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Platform Charge</span>
                            <span class="admin-line-value">{{ $money($order->platform_fee ?? 0) }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Taxes & Charges</span>
                            <span class="admin-line-value">{{ $money($order->tax ?? 0) }}</span>
                        </div>
                        @if((float) ($order->discount ?? 0) > 0)
                            <div class="admin-line">
                                <span class="admin-line-label">Discount</span>
                                <span class="admin-line-value text-danger">-{{ $money($order->discount) }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="admin-bill-total">
                        <div class="admin-line">
                            <span>Grand Total</span>
                            <span>{{ $money($order->total) }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="admin-order-card mt-3">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Payment</h2>
                        <div class="admin-order-card-subtitle">Method, source and verification details.</div>
                    </div>
                    <span class="badge bg-{{ $paymentTone }}">{{ ucfirst(str_replace('_', ' ', $paymentStatus)) }}</span>
                </div>
                <div class="admin-order-card-body">
                    <div class="admin-line-list">
                        <div class="admin-line">
                            <span class="admin-line-label">Method</span>
                            <span class="admin-line-value">{{ strtoupper($order->payment_method ?: 'N/A') }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Gateway</span>
                            <span class="admin-line-value">{{ ucfirst(str_replace('_', ' ', $order->payment_gateway ?? 'N/A')) }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Source</span>
                            <span class="admin-line-value">{{ ucfirst(str_replace('_', ' ', $order->payment_source ?? 'N/A')) }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Gateway Payment ID</span>
                            <span class="admin-line-value">{{ $order->payment_id ?: 'N/A' }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Paid At</span>
                            <span class="admin-line-value">{{ $order->paid_at ? $order->paid_at->format('d M Y, h:i A') : 'N/A' }}</span>
                        </div>
                        @if($order->delivery_otp)
                            <div class="admin-line">
                                <span class="admin-line-label">Delivery OTP</span>
                                <span class="admin-line-value">{{ $order->delivery_otp }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="admin-order-card mt-3">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Store And Branch</h2>
                        <div class="admin-order-card-subtitle">Fulfilment ownership.</div>
                    </div>
                </div>
                <div class="admin-order-card-body">
                    <div class="admin-line-list">
                        <div class="admin-line">
                            <span class="admin-line-label">Restaurant</span>
                            <span class="admin-line-value">{{ optional($order->restaurant)->name ?? 'N/A' }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Restaurant Phone</span>
                            <span class="admin-line-value">{{ optional($order->restaurant)->phone ?? 'N/A' }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Branch</span>
                            <span class="admin-line-value">{{ optional($order->branch)->name ?? 'N/A' }}</span>
                        </div>
                        <div class="admin-line">
                            <span class="admin-line-label">Address</span>
                            <span class="admin-line-value">{{ trim((optional($order->restaurant)->address ?? '') . ', ' . (optional($order->restaurant)->city ?? ''), ', ') ?: 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="admin-order-card mt-3">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Delivery Driver</h2>
                        <div class="admin-order-card-subtitle">Assignment and vehicle information.</div>
                    </div>
                </div>
                <div class="admin-order-card-body">
                    @if($order->driver)
                        <div class="admin-line-list">
                            <div class="admin-line">
                                <span class="admin-line-label">Driver</span>
                                <span class="admin-line-value">{{ $order->driver->name }}</span>
                            </div>
                            <div class="admin-line">
                                <span class="admin-line-label">Phone</span>
                                <span class="admin-line-value">{{ $order->driver->phone ?? 'N/A' }}</span>
                            </div>
                            <div class="admin-line">
                                <span class="admin-line-label">Vehicle</span>
                                <span class="admin-line-value">{{ trim(($order->driver->vehicle_type ?? 'N/A') . ' ' . ($order->driver->vehicle_number ?? '')) }}</span>
                            </div>
                            <div class="admin-line">
                                <span class="admin-line-label">Accepted</span>
                                <span class="admin-line-value">{{ $order->driver_accepted_at ? $order->driver_accepted_at->format('d M Y, h:i A') : 'Waiting for driver' }}</span>
                            </div>
                        </div>
                    @endif

                    @if($canAssignDriver)
                        <form action="{{ route('admin.orders.assign-driver', $order->id) }}" method="POST" id="assignDriverForm">
                            @csrf
                            <div class="{{ $order->driver ? 'mt-3' : '' }} mb-3">
                                <label class="form-label fw-bold">Select Driver</label>
                                <select name="driver_id" id="driverSelect" class="form-select" required>
                                    <option value="">Loading available drivers...</option>
                                </select>
                                @if($order->driver)
                                    <small class="text-muted d-block mt-2">
                                        Reassigning will send a fresh incoming-order alert to the selected driver and reset acceptance.
                                    </small>
                                @endif
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus me-2"></i>{{ $order->driver ? 'Reassign Driver' : 'Assign Driver' }}
                            </button>
                        </form>
                    @elseif(! $order->driver)
                        <div class="admin-empty-state py-2">
                            <i class="fas fa-user-clock d-block mb-2"></i>
                            No driver assigned.
                        </div>
                    @endif
                </div>
            </section>

            <section class="admin-order-card mt-3">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Admin Actions</h2>
                        <div class="admin-order-card-subtitle">Status control and refund management.</div>
                    </div>
                </div>
                <div class="admin-order-card-body">
                    <div class="admin-action-stack">
                        @if(in_array($currentStatus, ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way'], true))
                            <form action="{{ route('admin.orders.update-status', $order->id) }}" method="POST" class="admin-action-stack">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="form-label fw-bold">Update Status</label>
                                    <select name="status" class="form-select" id="statusSelect">
                                        @foreach(['confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way', 'delivered'] as $status)
                                            <option value="{{ $status }}" {{ $currentStatus === $status ? 'selected disabled' : '' }}>
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                                            </option>
                                        @endforeach
                                        <option value="cancelled">Cancel Order</option>
                                    </select>
                                </div>
                                <div class="d-none" id="cancellationReasonDiv">
                                    <label class="form-label fw-bold">Cancellation Reason</label>
                                    <input type="text" name="cancellation_reason" class="form-control" placeholder="Reason for cancellation">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-rotate me-2"></i>Update Status
                                </button>
                            </form>
                        @else
                            <div class="admin-empty-state py-2">
                                <i class="fas fa-circle-check d-block mb-2"></i>
                                No status action available.
                            </div>
                        @endif

                        <hr class="my-2">

                        <div class="admin-line-list">
                            <div class="admin-line">
                                <span class="admin-line-label">Refund Status</span>
                                <span class="admin-line-value">{!! $order->refund_status_badge !!}</span>
                            </div>
                            @if($order->refund_amount)
                                <div class="admin-line">
                                    <span class="admin-line-label">Refund Amount</span>
                                    <span class="admin-line-value">{{ $money($order->refund_amount) }}</span>
                                </div>
                            @endif
                            @if($order->refund_reason)
                                <div class="admin-line">
                                    <span class="admin-line-label">Refund Reason</span>
                                    <span class="admin-line-value">{{ $order->refund_reason }}</span>
                                </div>
                            @endif
                        </div>

                        @if($order->payment_status === 'success' && $order->refund_status !== 'completed')
                            <form action="{{ route('admin.orders.refund', $order->id) }}" method="POST" class="admin-action-stack mt-2">
                                @csrf
                                <div>
                                    <label class="form-label fw-bold">Refund Reason</label>
                                    <input type="text" name="refund_reason" class="form-control" required value="{{ old('refund_reason') }}">
                                </div>
                                <div>
                                    <label class="form-label fw-bold">Refund Amount</label>
                                    <input type="number" name="refund_amount" class="form-control" step="{{ $currencyStep }}" max="{{ number_format((float) $order->total, $currencyDecimals, '.', '') }}" value="{{ old('refund_amount', $order->refund_amount ?? $order->total) }}">
                                    <div class="form-text">Leave empty to refund the full order total.</div>
                                </div>
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-rotate-left me-2"></i>Process Refund
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </section>

            <section class="admin-order-card mt-3">
                <div class="admin-order-card-header">
                    <div>
                        <h2 class="admin-order-card-title">Financial Breakdown</h2>
                        <div class="admin-order-card-subtitle">{{ $financials['source'] }}</div>
                    </div>
                    <span class="badge bg-{{ $order->payout_processed ? 'success' : 'warning text-dark' }}">{{ $financials['source'] }}</span>
                </div>
                <div class="admin-order-card-body">
                    <h6 class="fw-bold mb-2">Store Settlement</h6>
                    <div class="admin-line-list">
                        <div class="admin-line"><span class="admin-line-label">Food subtotal</span><span class="admin-line-value">{{ $money($order->subtotal) }}</span></div>
                        <div class="admin-line"><span class="admin-line-label">Store commission ({{ $formatRule($financials['restaurant_commission_type'], $financials['restaurant_commission_value']) }})</span><span class="admin-line-value text-danger">-{{ $money($financials['restaurant_commission']) }}</span></div>
                        <div class="admin-line"><span class="admin-line-label">GST on commission</span><span class="admin-line-value text-danger">-{{ $money($financials['gst_on_commission']) }}</span></div>
                        <div class="admin-line"><span class="admin-line-label">Gateway fee</span><span class="admin-line-value text-danger">-{{ $money($financials['payment_gateway_fee']) }}</span></div>
                        <div class="admin-line"><span class="admin-line-label">Net store earning</span><span class="admin-line-value text-success">{{ $money($financials['restaurant_earning']) }}</span></div>
                    </div>

                    <h6 class="fw-bold mt-4 mb-2">Driver Settlement</h6>
                    <div class="admin-line-list">
                        <div class="admin-line"><span class="admin-line-label">Delivery earning base</span><span class="admin-line-value">{{ $money($financials['driver_base']) }}</span></div>
                        <div class="admin-line"><span class="admin-line-label">Driver commission ({{ $formatRule($financials['driver_commission_type'], $financials['driver_commission_value']) }})</span><span class="admin-line-value text-danger">-{{ $money($financials['driver_commission']) }}</span></div>
                        <div class="admin-line"><span class="admin-line-label">Batch bonus</span><span class="admin-line-value text-success">+{{ $money($financials['batch_bonus']) }}</span></div>
                        <div class="admin-line"><span class="admin-line-label">Net driver earning</span><span class="admin-line-value text-success">{{ $money($financials['driver_earning']) }}</span></div>
                    </div>

                    <h6 class="fw-bold mt-4 mb-2">Platform Allocation</h6>
                    <div class="admin-line-list">
                        <div class="admin-line"><span class="admin-line-label">Customer platform charge</span><span class="admin-line-value">{{ $money($order->platform_fee) }}</span></div>
                        <div class="admin-line"><span class="admin-line-label">Branch commission</span><span class="admin-line-value">{{ $money($financials['branch_commission']) }}</span></div>
                        <div class="admin-line"><span class="admin-line-label">Admin earning</span><span class="admin-line-value text-primary">{{ $money($financials['admin_earning']) }}</span></div>
                    </div>

                    <div class="admin-muted mt-3">
                        Restaurant payout: {{ $order->restaurant_payout_id ? '#' . $order->restaurant_payout_id : 'Pending' }} |
                        Driver payout: {{ $order->driver_payout_id ? '#' . $order->driver_payout_id : 'Pending' }}
                    </div>
                </div>
            </section>
        </aside>
    </div>
</div>

<script>
    document.getElementById('statusSelect')?.addEventListener('change', function() {
        const reasonDiv = document.getElementById('cancellationReasonDiv');
        reasonDiv?.classList.toggle('d-none', this.value !== 'cancelled');
    });

    const driverSelect = document.getElementById('driverSelect');
    if (driverSelect) {
        fetch('{{ route("admin.orders.available-drivers", $order->id) }}')
            .then(response => response.json())
            .then(data => {
                driverSelect.innerHTML = '<option value="">-- Select Driver --</option>';

                if (data.success && Array.isArray(data.drivers) && data.drivers.length) {
                    data.drivers.forEach(driver => {
                        const option = document.createElement('option');
                        option.value = driver.id;
                        option.textContent = `${driver.name} - ${driver.phone || 'No phone'}`;
                        if (Number(driver.id) === Number('{{ $order->driver_id ?? 0 }}')) {
                            option.textContent += ' (current)';
                        }
                        driverSelect.appendChild(option);
                    });
                } else {
                    driverSelect.innerHTML = '<option value="">No available drivers found</option>';
                }
            })
            .catch(() => {
                driverSelect.innerHTML = '<option value="">Unable to load drivers</option>';
            });
    }
</script>
@endsection
