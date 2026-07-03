<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->order_number }}</title>
    @php
        $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
        $decimals = App\Models\AppSetting::currencyDecimals();
        $orderItems = is_array($order->items) ? $order->items : (json_decode((string) $order->items, true) ?: []);
    @endphp
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #7c3aed;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #7c3aed;
        }
        .company-info {
            text-align: center;
            margin-bottom: 30px;
        }
        .order-info {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .order-info table {
            width: 100%;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .items-table th {
            background: #f3f4f6;
        }
        .totals {
            text-align: right;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>FOOD DELIVERY</h1>
            <p>Your Food Delivery Partner</p>
        </div>
        
        <div class="company-info">
            <p>123 Delivery Street, City, State - 123456<br>
            Phone: +91 9876543210 | Email: support@fooddelivery.com</p>
        </div>
        
        <div class="order-info">
            <table>
                <tr>
                    <td><strong>Order Number:</strong> {{ $order->order_number }}</td>
                    <td><strong>Order Date:</strong> {{ $order->created_at->format('d M Y, h:i A') }}</td>
                </tr>
                <tr>
                    <td><strong>Payment Method:</strong> {{ ucfirst($order->payment_method) }}</td>
                    <td><strong>Payment Status:</strong> {{ ucfirst($order->payment_status) }}</td>
                </tr>
                <tr>
                    <td><strong>Order Status:</strong> {{ ucfirst($order->status) }}</td>
                    <td><strong></strong></td>
                </tr>
            </table>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3>Customer Information</h3>
            <p>
                <strong>Name:</strong> {{ $order->customer_name }}<br>
                <strong>Phone:</strong> {{ $order->customer_phone }}<br>
                <strong>Delivery Address:</strong> {{ $order->delivery_address }}
            </p>
        </div>
        
        <div>
            <h3>Order Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orderItems as $item)
                    @php
                        $itemName = $item['name'] ?? $item['item_name'] ?? data_get($item, 'menu_item.name') ?? 'Item';
                        $quantity = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
                        $price = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
                        $lineTotal = (float) ($item['total_price'] ?? $item['total'] ?? ($price * $quantity));
                        if ($price <= 0 && $quantity > 0 && $lineTotal > 0) {
                            $price = $lineTotal / $quantity;
                        }
                    @endphp
                    <tr>
                        <td>{{ $itemName }}</td>
                        <td>{{ $quantity }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($price, $decimals) }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($lineTotal, $decimals) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center;">No items found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="totals">
            <p><strong>Subtotal:</strong> {{ $currencySymbol }}{{ number_format($order->subtotal, $decimals) }}</p>
            <p><strong>Delivery Fee:</strong> {{ $currencySymbol }}{{ number_format($order->delivery_fee, $decimals) }}</p>
            <p><strong>Platform Fee:</strong> {{ $currencySymbol }}{{ number_format($order->platform_fee ?? 0, $decimals) }}</p>
            <p><strong>Taxes & Charges:</strong> {{ $currencySymbol }}{{ number_format($order->tax, $decimals) }}</p>
            @if($order->discount > 0)
            <p><strong>Coupon Discount:</strong> -{{ $currencySymbol }}{{ number_format($order->discount, $decimals) }}</p>
            @endif
            <h3>Total Bill Payable: {{ $currencySymbol }}{{ number_format($order->total, $decimals) }}</h3>
        </div>
        
        <div class="footer">
            <p>Thank you for ordering with us!</p>
            <p>This is a system generated invoice, no signature required.</p>
        </div>
    </div>
</body>
</html>

