<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
    @page { margin: 34px 30px; }
    * { font-family: "DejaVu Sans", Arial, sans-serif; }
    body { font-size: 9px; margin: 0; }
    .cert { page-break-after: always; }
    .cert:last-child { page-break-after: auto; }
    .center { text-align: center; }
    .b { font-weight: bold; }
    .title { font-size: 13px; font-weight: bold; letter-spacing: .05em; }
    .sub { font-size: 8.5px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    td, th { border: 0.7px solid #000; padding: 3px 5px; vertical-align: top; }
    .noborder td { border: none; padding: 1px 3px; }
    .num { text-align: right; font-family: "DejaVu Sans Mono", monospace; }
    .hd th { background: #e8e8e8; font-size: 8px; text-transform: uppercase; }
    .sec-h { font-weight: bold; font-size: 9px; margin: 12px 0 0; }
    .small { font-size: 8px; color: #333; }
    .sign td { border: none; padding-top: 22px; font-size: 8.5px; }
</style></head>
<body>
@foreach($certs as $cert)
<div class="cert">
    <div class="center">
        <div class="title">FORM NO. 16A</div>
        <div class="sub">[See rule 31(1)(b)]</div>
        <div class="sub">Certificate under section 203 of the Income-tax Act, 1961 for tax deducted at source</div>
    </div>

    <table class="noborder" style="margin-top:8px">
        <tr>
            <td>Certificate No. <span class="b">{{ $cert['certificate_no'] }}</span></td>
            <td style="text-align:right">Last updated on <span class="b">{{ $cert['last_updated'] }}</span></td>
        </tr>
    </table>

    <table>
        <tr><td class="b" style="width:50%">Name and address of the Deductor</td><td class="b" style="width:50%">Name and address of the Deductee</td></tr>
        <tr>
            <td>{{ $deductor['name'] }}<br>{{ $deductor['address'] ?: '-' }}</td>
            <td>{{ $cert['deductee']['name'] }}<br>{{ $cert['deductee']['address'] }}</td>
        </tr>
    </table>

    <table>
        <tr class="hd"><th>PAN of the Deductor</th><th>TAN of the Deductor</th><th>PAN of the Deductee</th></tr>
        <tr class="center"><td>{{ $deductor['pan'] ?: '-' }}</td><td>{{ $deductor['tan'] ?: '-' }}</td><td>{{ $cert['deductee']['pan'] }}</td></tr>
    </table>

    <table>
        <tr class="hd"><th style="width:52%">CIT (TDS)</th><th style="width:16%">Assessment Year</th><th style="width:16%">Period From</th><th style="width:16%">Period To</th></tr>
        <tr>
            <td>{{ $deductor['cit_tds'] ?: 'The Commissioner of Income Tax (TDS)' }}</td>
            <td class="center">{{ $cert['assessment_year'] }}</td>
            <td class="center">{{ $cert['period']['from'] }}</td>
            <td class="center">{{ $cert['period']['to'] }}</td>
        </tr>
    </table>

    <div class="sec-h">Summary of payment</div>
    <table>
        <tr class="hd"><th style="width:8%">Sl.</th><th style="width:24%">Amount paid / credited ({{ $sym }})</th><th style="width:36%">Nature of payment</th><th style="width:16%">Deductee Ref.</th><th style="width:16%">Section</th></tr>
        <tr>
            <td class="center">1</td>
            <td class="num">{{ number_format($cert['summary_of_payment']['amount_paid'], 2) }}</td>
            <td>{{ $cert['nature_of_payment'] }}</td>
            <td class="center">{{ $cert['deductee']['pan'] }}</td>
            <td class="center">{{ $cert['section'] === '194C' ? '194C' : '194-O' }}</td>
        </tr>
    </table>

    <div class="sec-h">Summary of tax deducted at source in respect of Deductee</div>
    <table>
        <tr class="hd"><th style="width:12%">Quarter</th><th style="width:44%">Receipt Numbers of original quarterly TDS statements u/s 200(3)</th><th style="width:22%">Tax deducted ({{ $sym }})</th><th style="width:22%">Tax deposited ({{ $sym }})</th></tr>
        @foreach($cert['quarters'] as $q)
            <tr>
                <td class="center">{{ $q['quarter'] }}</td>
                <td>{{ $q['receipt_no'] }}</td>
                <td class="num">{{ number_format($q['tds_deducted'], 2) }}</td>
                <td class="num">{{ number_format($q['tds_deposited'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="b">
            <td colspan="2" class="center">Total</td>
            <td class="num">{{ number_format($cert['summary_of_payment']['tds_total'], 2) }}</td>
            <td class="num">{{ number_format($cert['summary_of_payment']['tds_deposited'], 2) }}</td>
        </tr>
    </table>

    <div class="sec-h">PART II &mdash; Tax deducted and deposited through CHALLAN</div>
    <table>
        <tr class="hd"><th style="width:6%">Sl.</th><th style="width:20%">Tax deposited ({{ $sym }})</th><th style="width:20%">BSR Code</th><th style="width:20%">Date deposited</th><th style="width:20%">Challan Serial No.</th><th style="width:14%">OLTAS</th></tr>
        @forelse($cert['challans'] as $i => $c)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td class="num">{{ number_format($c['amount'], 2) }}</td>
                <td class="center">{{ $c['bsr_code'] ?: '-' }}</td>
                <td class="center">{{ $c['deposit_date'] ?: '-' }}</td>
                <td class="center">{{ $c['challan_no'] ?: '-' }}</td>
                <td class="center">{{ $c['status'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="center small">Deposit / 26Q filing pending &mdash; amounts above are as accrued.</td></tr>
        @endforelse
    </table>

    <div style="margin-top:12px; font-size:8.5px; line-height:1.5">
        <span class="b">Verification</span><br>
        I, <span class="b">{{ $deductor['responsible_person'] ?: '__________' }}</span>, working in the capacity of
        <span class="b">{{ $deductor['designation'] }}</span>, certify that a sum of
        <span class="b">{{ $sym }} {{ number_format($cert['summary_of_payment']['tds_total'], 2) }}</span> has been deducted and
        <span class="b">{{ $sym }} {{ number_format($cert['summary_of_payment']['tds_deposited'], 2) }}</span> deposited to the credit
        of the Central Government, and that the information above is true, complete and correct based on the books of account and TDS statements.
    </div>

    <table class="sign">
        <tr>
            <td style="width:50%">Place: {{ $deductor['place'] ?: '__________' }}<br>Date: {{ now()->format('d-M-Y') }}</td>
            <td style="width:50%; text-align:right">
                _____________________________<br>
                (Signature of person responsible for deduction of tax)<br>
                {{ $deductor['responsible_person'] ?: '__________' }} &mdash; {{ $deductor['designation'] }}
            </td>
        </tr>
    </table>

    <div class="small" style="margin-top:10px; border-top:0.7px solid #999; padding-top:4px">
        System-generated from operating books. The statutory certificate is issued from TRACES (traces.gov.in) after Form 26Q is filed.
    </div>
</div>
@endforeach
</body>
</html>
