<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body>
@include('admin.accounting.pdf._doc', ['entity' => $entity])
@php
    $catLabels = [
        'gst' => 'Goods and Services Tax', 'tds' => 'Income-tax — Tax Deducted at Source',
        'income_tax' => 'Income-tax Act, 1961', 'roc' => 'Companies Act, 2013 / LLP Act, 2008',
        'labour' => 'Labour & Social Security', 'licence' => 'Licences & Registrations',
        'cess' => 'Welfare Cess', 'other' => 'Other',
    ];
    $statusStyle = [
        'filed' => 'color:#1a7f37;font-weight:bold', 'pending' => 'color:#8a6d00;font-weight:bold',
        'overdue' => 'color:#b42318;font-weight:bold', 'not_applicable' => 'color:#777',
    ];
    $sr = 0;
@endphp

<h1 class="doc-title">Statutory Compliance Calendar</h1>
<p class="doc-sub">as on {{ now()->format('d F Y') }} &nbsp;·&nbsp; Entity classification: {{ ucwords(str_replace('_',' ',$entity['entity_type'] ?? 'private limited company')) }}</p>

@foreach($grouped as $cat => $rows)
    <div class="note-blk" style="margin-top:12px">
        <div class="nt">{{ $catLabels[$cat] ?? ucfirst($cat) }}</div>
        <table class="grid">
            <thead>
                <tr style="background:#33475b;color:#fff">
                    <td style="width:4%">#</td>
                    <td style="width:26%">Nature of Compliance</td>
                    <td style="width:12%">Form</td>
                    <td style="width:14%">Authority</td>
                    <td style="width:9%">Frequency</td>
                    <td style="width:17%">Statutory Due (rule)</td>
                    <td style="width:8%">Status</td>
                    <td style="width:10%">Filed On</td>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $item)
                    @php $sr++; @endphp
                    <tr>
                        <td class="num">{{ $sr }}</td>
                        <td>{{ $item->name }}
                            @if($item->reference_no)<div class="muted" style="font-size:8px">Ref: {{ $item->reference_no }}</div>@endif
                            @if($item->notes)<div class="muted" style="font-size:8px">{{ $item->notes }}</div>@endif
                        </td>
                        <td>{{ $forms[$item->code] ?? '—' }}</td>
                        <td>{{ $item->authority }}</td>
                        <td>{{ ucfirst($item->frequency) }}</td>
                        <td>{{ $item->due_rule ?: '—' }}</td>
                        <td style="{{ $statusStyle[$item->status] ?? '' }}">{{ ucwords(str_replace('_',' ',$item->status)) }}</td>
                        <td>{{ $item->filed_on ? $item->filed_on->format('d-M-Y') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

<p class="muted" style="margin-top:18px; font-size:8px">
    This calendar is filtered to obligations applicable to the entity's current classification, GST / TDS registration status and payroll profile.
    It is a management tracking tool and does not substitute advice from a practising Chartered Accountant / Company Secretary.
</p>

<table class="sign-grid">
    <tr>
        <td>Prepared by<br><br>_______________________</td>
        <td style="text-align:right">Reviewed by (CA / CS)<br><br>_______________________</td>
    </tr>
</table>
</body>
</html>
