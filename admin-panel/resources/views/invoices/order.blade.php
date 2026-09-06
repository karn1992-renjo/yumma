<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $meta['invoice_no'] }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1f2937; margin: 0; }
    .wrap { padding: 32px 34px; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #6b7280; }
    .small { font-size: 10.5px; }
    .right { text-align: right; }
    .b { font-weight: bold; }

    .top td { vertical-align: top; }
    .brand-name { font-size: 18px; font-weight: bold; color: #111827; }
    .logo { max-height: 46px; margin-bottom: 6px; }
    .doc-title { font-size: 20px; font-weight: bold; color: #111827; letter-spacing: 1px; }
    .doc-meta { margin-top: 8px; }
    .doc-meta td { padding: 1px 0; font-size: 11px; }
    .doc-meta td:first-child { color: #6b7280; padding-right: 12px; }

    .rule { border: none; border-top: 2px solid #111827; margin: 16px 0; }

    .parties { margin-top: 4px; }
    .parties td { vertical-align: top; width: 50%; padding-right: 18px; }
    .parties .label { font-size: 10px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; margin-bottom: 3px; }

    .items { margin-top: 20px; }
    .items th { background: #111827; color: #fff; font-size: 10.5px; text-transform: uppercase; letter-spacing: .5px; padding: 8px 10px; text-align: left; }
    .items th.num, .items td.num { text-align: right; }
    .items td { padding: 9px 10px; border-bottom: 1px solid #e5e7eb; }
    .items tr:nth-child(even) td { background: #f9fafb; }
    .item-sub { color: #6b7280; font-size: 10px; }

    .totals { margin-top: 14px; }
    .totals td { padding: 4px 10px; }
    .totals .tbl-right { width: 240px; margin-left: auto; }
    .totals .grand td { border-top: 2px solid #111827; font-size: 14px; font-weight: bold; color: #111827; padding-top: 8px; }
    .paidbox { margin-top: 10px; padding: 8px 12px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-weight: bold; border-radius: 4px; display: inline-block; }

    .footer { margin-top: 34px; padding-top: 12px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 10.5px; }
</style>
</head>
<body>
<div class="wrap">

    <table class="top">
        <tr>
            <td style="width:58%;">
                @if($logo)
                    <img src="{{ $logo }}" class="logo" alt="{{ $company['name'] }}"><br>
                @endif
                <span class="brand-name">{{ $company['name'] }}</span>
                <div class="small muted" style="margin-top:4px;">
                    @if($company['address'])<div>{!! nl2br(e($company['address'])) !!}</div>@endif
                    @if($company['phone'])<div>Phone: {{ $company['phone'] }}</div>@endif
                    @if($company['email'])<div>Email: {{ $company['email'] }}</div>@endif
                    @if($company['website'])<div>{{ $company['website'] }}</div>@endif
                    @if($company['tax_id'])<div>Tax ID / GSTIN: {{ $company['tax_id'] }}</div>@endif
                </div>
            </td>
            <td style="width:42%;" class="right">
                <div class="doc-title">TAX INVOICE</div>
                <table class="doc-meta" style="width:auto; margin-left:auto;">
                    <tr><td>Invoice No</td><td class="b">{{ $meta['invoice_no'] }}</td></tr>
                    <tr><td>Order No</td><td class="b">{{ $meta['order_no'] }}</td></tr>
                    <tr><td>Date</td><td>{{ $meta['date'] }}</td></tr>
                    <tr><td>Payment</td><td>{{ $meta['payment_method'] }} &middot; {{ $meta['payment_status'] }}</td></tr>
                    <tr><td>Status</td><td>{{ $meta['order_status'] }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <hr class="rule">

    <table class="parties">
        <tr>
            <td>
                <div class="label">Billed to</div>
                <div class="b">{{ $customer['name'] }}</div>
                @if($customer['phone'])<div class="small">{{ $customer['phone'] }}</div>@endif
                @if($customer['email'])<div class="small">{{ $customer['email'] }}</div>@endif
                @if($customer['address'])<div class="small muted" style="margin-top:3px;">{{ $customer['address'] }}</div>@endif
            </td>
            <td>
                <div class="label">Restaurant</div>
                <div class="b">{{ $restaurant['name'] ?: '—' }}</div>
                @if($restaurant['address'])<div class="small muted" style="margin-top:3px;">{{ $restaurant['address'] }}</div>@endif
                @if($restaurant['gstin'])<div class="small">FSSAI: {{ $restaurant['gstin'] }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Unit</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item['name'] }}
                        @if($item['variant'] || $item['addons'])
                            <div class="item-sub">
                                {{ $item['variant'] }}@if($item['variant'] && $item['addons']) &middot; @endif{{ $item['addons'] }}
                            </div>
                        @endif
                    </td>
                    <td class="num">{{ $item['qty'] }}</td>
                    <td class="num">{{ $money($item['unit']) }}</td>
                    <td class="num">{{ $money($item['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted" style="text-align:center;">No items recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td></td>
            <td>
                <table class="tbl-right">
                    @foreach($feeRows as $row)
                        <tr>
                            <td class="muted">{{ $row['label'] }}</td>
                            <td class="right">{{ $row['negative'] ? '-' : '' }}{{ $money($row['value']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="grand">
                        <td>Total</td>
                        <td class="right">{{ $money($order->total) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="paidbox">{{ $meta['paid_label'] }}</div>

    <div class="footer">
        {{ $company['footer'] }}
        @if($company['name'])<div style="margin-top:4px;">&copy; {{ date('Y') }} {{ $company['name'] }}</div>@endif
    </div>

</div>
</body>
</html>
