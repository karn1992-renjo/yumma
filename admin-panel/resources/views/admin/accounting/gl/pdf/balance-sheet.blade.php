<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body>
@include('admin.accounting.pdf._doc', ['entity' => $bs['entity']])
@php
    $sym = $bs['entity']['currency'];
    $f = function ($v) use ($sym) {
        $v = (float) $v;
        return ($v < 0 ? '(' : '') . $sym . number_format(abs($v), 2) . ($v < 0 ? ')' : '');
    };
    $asOf = \Illuminate\Support\Carbon::parse($bs['as_of'])->format('d F Y');
    $prior = \Illuminate\Support\Carbon::parse($bs['prior_as_of'])->format('d F Y');
@endphp

<h1 class="doc-title">Balance Sheet as at {{ $asOf }}</h1>
<p class="doc-sub">(Prepared under Division I of Schedule III to the Companies Act, 2013 — amounts in {{ $sym }})</p>

<table class="stmt">
    <thead>
        <tr>
            <th style="width:52%">Particulars</th>
            <th style="width:8%" class="num">Note</th>
            <th style="width:20%" class="num">As at {{ $asOf }}</th>
            <th style="width:20%" class="num">As at {{ $prior }}</th>
        </tr>
    </thead>
    <tbody>
        <tr class="head-row"><td colspan="4">I. EQUITY AND LIABILITIES</td></tr>
        @foreach($bs['equity_and_liabilities'] as $section)
            <tr><td class="indent" colspan="4"><em>{{ $loop->iteration }}. {{ $section['head'] }}</em></td></tr>
            @foreach($section['lines'] as $l)
                <tr>
                    <td class="indent2">{{ $l['label'] }}</td>
                    <td class="num">{{ $l['note'] }}</td>
                    <td class="num">{{ $f($l['current']) }}</td>
                    <td class="num">{{ $f($l['prior']) }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr class="rule-dbl">
            <td><strong>TOTAL EQUITY AND LIABILITIES</strong></td>
            <td></td>
            <td class="num"><strong>{{ $f($bs['totals']['total_equity_liabilities']['current']) }}</strong></td>
            <td class="num"><strong>{{ $f($bs['totals']['total_equity_liabilities']['prior']) }}</strong></td>
        </tr>

        <tr class="head-row"><td colspan="4">II. ASSETS</td></tr>
        @foreach($bs['assets'] as $section)
            <tr><td class="indent" colspan="4"><em>{{ $loop->iteration }}. {{ $section['head'] }}</em></td></tr>
            @foreach($section['lines'] as $l)
                <tr>
                    <td class="indent2">{{ $l['label'] }}</td>
                    <td class="num">{{ $l['note'] }}</td>
                    <td class="num">{{ $f($l['current']) }}</td>
                    <td class="num">{{ $f($l['prior']) }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr class="rule-dbl">
            <td><strong>TOTAL ASSETS</strong></td>
            <td></td>
            <td class="num"><strong>{{ $f($bs['totals']['total_assets']['current']) }}</strong></td>
            <td class="num"><strong>{{ $f($bs['totals']['total_assets']['prior']) }}</strong></td>
        </tr>
    </tbody>
</table>

@unless($bs['balanced'])
    <p class="muted" style="margin-top:8px">Note: "Other current liabilities" includes an unreconciled balancing amount pending entry of manual journals (bank, capital, opening balances). The books are operational and not yet audited.</p>
@endunless

<p style="margin-top:16px; font-size:10px"><strong>Significant accounting policies and notes 1&ndash;{{ count($bs['notes']) }} form an integral part of the Balance Sheet.</strong></p>

<table class="sign-grid">
    <tr>
        <td>For and on behalf of the Board of Directors<br><br><br>_______________________<br>Director</td>
        <td style="text-align:right">As per our report of even date<br><br><br>_______________________<br>Statutory Auditor</td>
    </tr>
</table>

<div style="page-break-before: always"></div>
<h1 class="doc-title">Notes forming part of the Balance Sheet</h1>
<p class="doc-sub">Amounts in {{ $sym }}</p>

@foreach($bs['notes'] as $note)
    <div class="note-blk">
        <div class="nt">Note {{ $note['no'] }} &mdash; {{ $note['title'] }}</div>
        <table class="stmt">
            <thead><tr><th style="width:60%">Particulars</th><th class="num" style="width:20%">As at {{ $asOf }}</th><th class="num" style="width:20%">As at {{ $prior }}</th></tr></thead>
            <tbody>
                @php $curBy = collect($note['current'])->keyBy('name'); $prBy = collect($note['prior'])->keyBy('name'); $names = $curBy->keys()->merge($prBy->keys())->unique(); @endphp
                @forelse($names as $name)
                    <tr>
                        <td>{{ $name }}</td>
                        <td class="num">{{ $f(optional($curBy->get($name))['amount'] ?? 0) }}</td>
                        <td class="num">{{ $f(optional($prBy->get($name))['amount'] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td class="muted" colspan="3">Nil</td></tr>
                @endforelse
                <tr class="rule-top">
                    <td><strong>Total</strong></td>
                    <td class="num"><strong>{{ $f($note['current_total']) }}</strong></td>
                    <td class="num"><strong>{{ $f($note['prior_total']) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
