@php
    $currency = \App\Models\AppSetting::sanitizedCurrencySymbol();
    $money = fn ($value) => $currency . number_format((float) $value, \App\Models\AppSetting::currencyDecimals());
@endphp

<h2>Business Report: {{ $restaurant->name }}</h2>
<p>Period: {{ $report['period_label'] }}</p>
<p>The attached Excel workbook includes a Summary sheet and an Orders sheet with order-wise financial details.</p>

<table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
    <tr><th align="left">Total orders</th><td>{{ $report['total_orders'] }}</td></tr>
    <tr><th align="left">Delivered orders</th><td>{{ $report['delivered_orders'] }}</td></tr>
    <tr><th align="left">Cancelled orders</th><td>{{ $report['cancelled_orders'] }}</td></tr>
    <tr><th align="left">Delivered gross sales</th><td>{{ $money($report['gross_sales']) }}</td></tr>
    <tr><th align="left">Delivered item sales</th><td>{{ $money($report['item_sales']) }}</td></tr>
    <tr><th align="left">Discounts</th><td>{{ $money($report['discounts']) }}</td></tr>
    <tr><th align="left">Tax</th><td>{{ $money($report['tax']) }}</td></tr>
    <tr><th align="left">Platform commission</th><td>{{ $money($report['commission']) }}</td></tr>
    <tr><th align="left">Estimated net earnings</th><td>{{ $money($report['net_earnings']) }}</td></tr>
    <tr><th align="left">Average delivered order value</th><td>{{ $money($report['average_order_value']) }}</td></tr>
</table>

@if($report['top_orders']->isNotEmpty())
    <h3>Top delivered orders</h3>
    <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
        <thead>
            <tr>
                <th align="left">Order</th>
                <th align="left">Status</th>
                <th align="right">Total</th>
                <th align="left">Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['top_orders'] as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                    <td align="right">{{ $money($order->total) }}</td>
                    <td>{{ optional($order->created_at)->format('d M Y, h:i A') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<p style="color: #666;">This report is generated automatically from your restaurant order records.</p>
