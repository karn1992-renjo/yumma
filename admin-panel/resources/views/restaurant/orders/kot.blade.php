@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $decimals = App\Models\AppSetting::currencyDecimals();
    $orderItems = [];

    if ($order->items) {
        if (is_string($order->items)) {
            $orderItems = json_decode($order->items, true) ?: [];
        } elseif (is_array($order->items)) {
            $orderItems = $order->items;
        }
    }

    if (empty($orderItems) && method_exists($order, 'orderItems')) {
        $orderItems = $order->orderItems?->toArray() ?? [];
    }
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>KOT Bill #{{ $order->order_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111;
            margin: 0;
            background: #fff;
        }

        .ticket {
            width: 320px;
            margin: 0 auto;
            padding: 12px;
        }

        .center { text-align: center; }
        .muted { color: #555; font-size: 12px; }
        .line { border-top: 1px dashed #111; margin: 10px 0; }
        .row { display: flex; justify-content: space-between; gap: 12px; }
        .bold { font-weight: 700; }
        .item { margin: 8px 0; }
        .item-name { font-weight: 700; }
        .small { font-size: 12px; }

        @media print {
            @page { margin: 0; size: 80mm auto; }
            .ticket { width: 76mm; padding: 4mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="ticket">
        <div class="center">
            <div class="bold">{{ $restaurant->name }}</div>
            <div class="muted">{{ $restaurant->address }}</div>
            <div class="line"></div>
            <div class="bold">KOT BILL</div>
            <div class="muted">Order #{{ $order->order_number }}</div>
        </div>

        <div class="line"></div>
        <div class="row small"><span>Date</span><span>{{ optional($order->created_at)->format('d M Y h:i A') }}</span></div>
        <div class="row small"><span>Customer</span><span>{{ $order->customer_name ?? 'Guest' }}</span></div>
        <div class="row small"><span>Type</span><span>{{ ucfirst($order->order_type ?? 'delivery') }}</span></div>
        <div class="row small"><span>Payment</span><span>{{ $order->payment_label ?? ucfirst($order->payment_method ?? 'cod') }}</span></div>

        <div class="line"></div>
        @forelse($orderItems as $item)
            @php
                $name = $item['name'] ?? $item['item_name'] ?? data_get($item, 'menu_item.name') ?? $item['title'] ?? 'Item';
                $qty = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
                $price = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
                $total = (float) ($item['total_price'] ?? $item['total'] ?? ($price * $qty));
                if ($price <= 0 && $qty > 0 && $total > 0) {
                    $price = $total / $qty;
                }
            @endphp
            <div class="item">
                <div class="row">
                    <span class="item-name">{{ $qty }} x {{ $name }}</span>
                    <span>{{ $currencySymbol }}{{ number_format($total, $decimals) }}</span>
                </div>
                <div class="muted">{{ $currencySymbol }}{{ number_format($price, $decimals) }} each</div>
            </div>
        @empty
            <div class="center muted">No items found</div>
        @endforelse

        <div class="line"></div>
        <div class="row"><span>Subtotal</span><span>{{ $currencySymbol }}{{ number_format($order->subtotal, $decimals) }}</span></div>
        <div class="row"><span>Delivery</span><span>{{ $currencySymbol }}{{ number_format($order->delivery_fee, $decimals) }}</span></div>
        <div class="row"><span>Platform</span><span>{{ $currencySymbol }}{{ number_format($order->platform_fee ?? 0, $decimals) }}</span></div>
        <div class="row"><span>Tax</span><span>{{ $currencySymbol }}{{ number_format($order->tax, $decimals) }}</span></div>
        @if((float) $order->discount > 0)
            <div class="row"><span>Discount</span><span>-{{ $currencySymbol }}{{ number_format($order->discount, $decimals) }}</span></div>
        @endif
        <div class="line"></div>
        <div class="row bold"><span>Total</span><span>{{ $currencySymbol }}{{ number_format($order->total, $decimals) }}</span></div>
        <div class="line"></div>
        <div class="center muted">Thank you</div>
        <p class="center no-print"><button onclick="window.print()">Print</button></p>
    </div>
</body>
</html>
