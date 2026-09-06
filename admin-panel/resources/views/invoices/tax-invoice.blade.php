<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $meta['title'] }} {{ $meta['invoice_no'] }}</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
    .wrap { padding: 26px 30px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .muted { color: #6b7280; }
    .small { font-size: 9.5px; }
    .right { text-align: right; }
    .center { text-align: center; }
    .b { font-weight: bold; }

    .head td { vertical-align: top; }
    .brand-name { font-size: 16px; font-weight: bold; color: #111827; }
    .logo { max-height: 42px; margin-bottom: 5px; }
    .doc-title { font-size: 17px; font-weight: bold; color: #111827; letter-spacing: 1px; }
    .doc-meta td { padding: 1px 0; font-size: 10px; }
    .doc-meta td:first-child { color: #6b7280; padding-right: 10px; }

    .box { border: 1px solid #d1d5db; }
    .box td { padding: 8px 10px; border-right: 1px solid #d1d5db; width: 50%; }
    .box td:last-child { border-right: none; }
    .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 2px; }

    .items { margin-top: 12px; border: 1px solid #d1d5db; }
    .items th { background: #111827; color: #fff; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; padding: 6px 7px; text-align: left; }
    .items th.num, .items td.num { text-align: right; }
    .items td { padding: 6px 7px; border-top: 1px solid #e5e7eb; }
    .item-sub { color: #6b7280; font-size: 8.5px; }

    .summary { margin-top: 12px; }
    .summary .gst-table { width: 62%; border: 1px solid #d1d5db; }
    .summary .gst-table th { background: #f3f4f6; font-size: 8.5px; text-transform: uppercase; padding: 5px 7px; text-align: right; }
    .summary .gst-table th:first-child { text-align: left; }
    .summary .gst-table td { padding: 5px 7px; border-top: 1px solid #e5e7eb; text-align: right; }
    .summary .gst-table td:first-child { text-align: left; }
    .summary .totals { width: 36%; }
    .summary .totals td { padding: 3px 8px; }
    .summary .totals .grand td { border-top: 2px solid #111827; font-size: 13px; font-weight: bold; color: #111827; padding-top: 6px; }

    .words { margin-top: 8px; font-size: 10px; }
    .paidbox { margin-top: 8px; padding: 6px 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-weight: bold; display: inline-block; }

    .foot { margin-top: 18px; }
    .foot td { vertical-align: top; width: 50%; padding-right: 16px; }
    .sign { height: 40px; margin-bottom: 2px; }
    .decl { margin-top: 14px; padding-top: 8px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 9px; }
    .einv { margin-top: 12px; border: 1px solid #d1d5db; padding: 8px 10px; }
    .einv img { height: 92px; }
</style>
</head>
<body>
<div class="wrap">

    <table class="head">
        <tr>
            <td style="width:60%;">
                @if($logo)<img src="{{ $logo }}" class="logo" alt=""><br>@endif
                <span class="brand-name">{{ $supplier['name'] ?: $company['name'] }}</span>
                <div class="small muted" style="margin-top:3px;">
                    @if($supplier['address'])<div>{{ $supplier['address'] }}</div>@endif
                    @if($supplier['gstin'])<div><span class="b">GSTIN:</span> {{ $supplier['gstin'] }}</div>@endif
                    @if($supplier['pan'])<div><span class="b">PAN:</span> {{ $supplier['pan'] }}</div>@endif
                    @if($supplier['state'])<div><span class="b">State:</span> {{ $supplier['state'] }}@if($supplier['state_code']) ({{ $supplier['state_code'] }})@endif</div>@endif
                    @if($supplier['fssai'])<div><span class="b">FSSAI:</span> {{ $supplier['fssai'] }}</div>@endif
                </div>
            </td>
            <td style="width:40%;" class="right">
                <div class="doc-title">{{ $meta['title'] }}</div>
                <table class="doc-meta" style="width:auto;margin-left:auto;">
                    <tr><td>Invoice No</td><td class="b">{{ $meta['invoice_no'] }}</td></tr>
                    <tr><td>Order No</td><td>{{ $meta['order_no'] }}</td></tr>
                    <tr><td>Date</td><td>{{ $meta['date'] }}</td></tr>
                    <tr><td>Reverse charge</td><td>{{ $meta['reverse_charge'] }}</td></tr>
                    <tr><td>Payment</td><td>{{ $meta['payment_method'] }} &middot; {{ $meta['payment_status'] }}</td></tr>
                    @if($meta['place_of_supply'])<tr><td>Place of supply</td><td>{{ $meta['place_of_supply'] }}</td></tr>@endif
                </table>
                @if($section95)
                    <div class="small muted" style="margin-top:5px;">Invoiced by <span class="b">{{ $company['name'] }}</span> as Electronic Commerce Operator@if($company['gstin']) &middot; GSTIN {{ $company['gstin'] }}@endif</div>
                @else
                    <div class="small muted" style="margin-top:5px;">Sold through {{ $company['name'] }}@if($company['gstin']) &middot; GSTIN {{ $company['gstin'] }}@endif</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="box" style="margin-top:12px;">
        <tr>
            <td>
                <div class="label">Billed to</div>
                <div class="b">{{ $customer['name'] }}</div>
                @if($customer['phone'])<div class="small">{{ $customer['phone'] }}</div>@endif
                @if($customer['email'])<div class="small">{{ $customer['email'] }}</div>@endif
                @if($customer['gstin'])<div class="small"><span class="b">GSTIN:</span> {{ $customer['gstin'] }}</div>@endif
                @if($customer['address'])<div class="small muted" style="margin-top:2px;">{{ $customer['address'] }}</div>@endif
            </td>
            <td>
                <div class="label">Shipped to</div>
                <div class="small">{{ $customer['address'] ?: $customer['name'] }}</div>
                @if($meta['place_of_supply'])<div class="small muted" style="margin-top:2px;">Place of supply: {{ $meta['place_of_supply'] }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:22px;">#</th>
                <th>Item</th>
                <th style="width:56px;">HSN/SAC</th>
                <th class="num" style="width:34px;">Qty</th>
                <th class="num" style="width:60px;">Taxable</th>
                <th class="num" style="width:66px;">CGST</th>
                <th class="num" style="width:66px;">SGST</th>
                <th class="num" style="width:66px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item['name'] }}
                        @if($item['variant'] || $item['addons'])
                            <div class="item-sub">{{ $item['variant'] }}@if($item['variant'] && $item['addons']) &middot; @endif{{ $item['addons'] }}</div>
                        @endif
                    </td>
                    <td>{{ $item['hsn'] ?? '—' }}</td>
                    <td class="num">{{ $item['qty'] }}</td>
                    <td class="num">{{ $item['taxable_value'] !== null ? $money($item['taxable_value']) : $money($item['total']) }}</td>
                    <td class="num">
                        @if($item['cgst'] !== null)
                            {{ $money($item['cgst']) }}<div class="item-sub">{{ rtrim(rtrim(number_format(($item['gst_rate'] ?? 0) / 2, 2), '0'), '.') }}%</div>
                        @else — @endif
                    </td>
                    <td class="num">
                        @if($item['sgst'] !== null)
                            {{ $money($item['sgst']) }}<div class="item-sub">{{ rtrim(rtrim(number_format(($item['gst_rate'] ?? 0) / 2, 2), '0'), '.') }}%</div>
                        @else — @endif
                    </td>
                    <td class="num">{{ $money($item['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="center muted">No items recorded.</td></tr>
            @endforelse

            @foreach(($gst['charges'] ?? []) as $charge)
                <tr>
                    <td></td>
                    <td>{{ $charge['label'] }}</td>
                    <td>{{ $charge['hsn'] ?? '—' }}</td>
                    <td class="num">—</td>
                    <td class="num">{{ $money($charge['taxable_value']) }}</td>
                    <td class="num">{{ $money($charge['cgst']) }}</td>
                    <td class="num">{{ $money($charge['sgst']) }}</td>
                    <td class="num">{{ $money($charge['taxable_value']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td style="width:62%;padding-right:14px;">
                @if(!empty($gst['rate_summary']))
                    <table class="gst-table">
                        <thead>
                            <tr>
                                <th>GST Rate</th>
                                <th>Taxable Value</th>
                                <th>CGST</th>
                                <th>SGST</th>
                                <th>Total Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gst['rate_summary'] as $r)
                                <tr>
                                    <td>{{ rtrim(rtrim(number_format($r['rate'], 2), '0'), '.') }}%</td>
                                    <td>{{ $money($r['taxable_value']) }}</td>
                                    <td>{{ $money($r['cgst']) }}</td>
                                    <td>{{ $money($r['sgst']) }}</td>
                                    <td>{{ $money($r['cgst'] + $r['sgst']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="b">
                                <td>Total</td>
                                <td>{{ $money($gst['taxable_total'] ?? 0) }}</td>
                                <td>{{ $money($gst['cgst_total'] ?? 0) }}</td>
                                <td>{{ $money($gst['sgst_total'] ?? 0) }}</td>
                                <td>{{ $money(($gst['cgst_total'] ?? 0) + ($gst['sgst_total'] ?? 0)) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </td>
            <td style="width:38%;">
                <table class="totals">
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

    @if($section95 && $eco95)
        <div class="small" style="margin-top:8px;padding:6px 10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;">
            Tax on restaurant service ({{ rtrim(rtrim(number_format($eco95['rate'], 2), '0'), '.') }}%) —
            CGST {{ $money($eco95['cgst']) }} + SGST {{ $money($eco95['sgst']) }} —
            is payable by the Electronic Commerce Operator under section 9(5) of the CGST Act, 2017.
        </div>
    @endif

    <div class="words"><span class="muted">Amount in words:</span> <span class="b">{{ $amountInWords }}</span></div>
    <div class="paidbox">{{ $meta['paid_label'] }}</div>

    @if($einvoice)
        <div class="einv">
            <table>
                <tr>
                    <td style="width:70%;">
                        <div class="label">E-Invoice</div>
                        <div class="small"><span class="b">IRN:</span> {{ $einvoice['irn'] }}</div>
                        @if($einvoice['ack_no'])<div class="small"><span class="b">Ack No:</span> {{ $einvoice['ack_no'] }} @if($einvoice['acked_at'])&middot; {{ $einvoice['acked_at'] }}@endif</div>@endif
                    </td>
                    <td class="right">
                        @if($einvoice['qr'])<img src="{{ $einvoice['qr'] }}" alt="e-invoice QR">@endif
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <table class="foot">
        <tr>
            <td>
                @if($company['bank_name'] || $company['bank_account'])
                    <div class="label">Bank details</div>
                    <div class="small">
                        @if($company['bank_name']){{ $company['bank_name'] }}<br>@endif
                        @if($company['bank_account'])A/C: {{ $company['bank_account'] }}<br>@endif
                        @if($company['bank_ifsc'])IFSC: {{ $company['bank_ifsc'] }}@endif
                    </div>
                @endif
            </td>
            <td class="right">
                @if($signature)<img src="{{ $signature }}" class="sign" alt=""><br>@endif
                <div class="small b">For {{ $company['name'] }}</div>
                <div class="small muted">{{ $company['signatory'] ?: 'Authorised Signatory' }}</div>
            </td>
        </tr>
    </table>

    <div class="decl">
        @if($company['declaration']){{ $company['declaration'] }}<br>@endif
        {{ $company['footer'] }}
        @if($company['cin']) &middot; CIN: {{ $company['cin'] }}@endif
    </div>

</div>
</body>
</html>
