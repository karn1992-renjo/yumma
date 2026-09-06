<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body>
@include('admin.accounting.pdf._doc', ['entity' => $pl['entity']])
@php
    $sym = $pl['entity']['currency'];
    $f = function ($v) use ($sym) {
        $v = (float) $v;
        return ($v < 0 ? '(' : '') . $sym . number_format(abs($v), 2) . ($v < 0 ? ')' : '');
    };
    $cf = \Illuminate\Support\Carbon::parse($pl['period']['from'])->format('d M Y');
    $ct = \Illuminate\Support\Carbon::parse($pl['period']['to'])->format('d M Y');
    $pf = \Illuminate\Support\Carbon::parse($pl['prior_period']['from'])->format('d M Y');
    $pt = \Illuminate\Support\Carbon::parse($pl['prior_period']['to'])->format('d M Y');
@endphp

<h1 class="doc-title">Statement of Profit and Loss</h1>
<p class="doc-sub">for the period {{ $cf }} to {{ $ct }} &nbsp;·&nbsp; (Division I, Schedule III to the Companies Act, 2013 — amounts in {{ $sym }})</p>

<table class="stmt">
    <thead>
        <tr>
            <th style="width:54%">Particulars</th>
            <th style="width:8%" class="num">Note</th>
            <th style="width:19%" class="num">{{ $cf }}&ndash;{{ $ct }}</th>
            <th style="width:19%" class="num">{{ $pf }}&ndash;{{ $pt }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($pl['rows'] as $r)
            <tr class="{{ !empty($r['total']) ? 'rule-top' : '' }}">
                <td class="{{ empty($r['total']) ? 'indent' : '' }}">{{ !empty($r['total']) ? strtoupper($r['label']) : $r['label'] }}</td>
                <td class="num">{{ $r['note'] ?? '' }}</td>
                <td class="num">{!! !empty($r['total']) ? '<strong>'.$f($r['current']).'</strong>' : $f($r['current']) !!}</td>
                <td class="num">{!! !empty($r['total']) ? '<strong>'.$f($r['prior']).'</strong>' : $f($r['prior']) !!}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-top:16px; font-size:10px"><strong>Significant accounting policies and notes 1&ndash;{{ count($pl['notes']) }} form an integral part of the Statement of Profit and Loss.</strong></p>

<table class="sign-grid">
    <tr>
        <td>For and on behalf of the Board of Directors<br><br><br>_______________________<br>Director</td>
        <td style="text-align:right">As per our report of even date<br><br><br>_______________________<br>Statutory Auditor</td>
    </tr>
</table>

<div style="page-break-before: always"></div>
<h1 class="doc-title">Notes forming part of the Statement of Profit and Loss</h1>
<p class="doc-sub">Amounts in {{ $sym }}</p>

@foreach($pl['notes'] as $note)
    <div class="note-blk">
        <div class="nt">Note {{ $note['no'] }} &mdash; {{ $note['title'] }}</div>
        <table class="stmt">
            <thead><tr><th style="width:60%">Particulars</th><th class="num" style="width:20%">Current</th><th class="num" style="width:20%">Prior</th></tr></thead>
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
