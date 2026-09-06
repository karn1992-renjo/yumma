@extends('layouts.admin')
@section('title', 'Accounting — TDS')
@section('header', 'Accounting — TDS')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); $qs = ['from' => $from, 'to' => $to]; @endphp

<div class="settings-shell">
    <div class="settings-hero"><div><h1>Income-Tax TDS</h1><p>Sec 194-O (restaurants) and Sec 194-C (drivers), deducted at settlement.</p></div></div>
    @include('admin.accounting._tabs')

    @unless($tan)
        <div class="alert alert-warning">No TAN set in <a href="{{ route('admin.settings.business') }}">Settings &rarr; Business</a>. TDS cannot be deducted without a TAN.</div>
    @endunless

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm btn-primary" href="{{ route('admin.accounting.export', ['doc' => 'form26q'] + $qs) }}">Form 26Q — Annexure I (Excel)</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.accounting.export', ['doc' => 'form16a-194o'] + $qs) }}">Form 16A — 194-O (PDF booklet)</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.accounting.export', ['doc' => 'form16a-194c'] + $qs) }}">Form 16A — 194-C (PDF booklet)</a>
    </div>
    <p class="small text-muted">Form 16A generates one TRACES-format certificate per deductee for the financial year covering the selected "to" date. Add <code>&amp;party_id=</code> to the URL for a single certificate.</p>

    @foreach([['194-O — Restaurants', $s194o], ['194-C — Drivers', $s194c]] as [$title, $st])
        <div class="table-card mb-4">
            <div class="card-header"><h5 class="mb-0">Sec {{ $title }}</h5><span class="text-muted small">Total TDS {{ $m($st['total_tds']) }}</span></div>
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>Deductee</th><th>PAN</th><th class="text-end">Amount paid</th><th class="text-end">Rate %</th><th class="text-end">TDS</th><th class="text-end">Deductions</th></tr></thead>
                <tbody>
                @forelse($st['rows'] as $r)
                    <tr>
                        <td>{{ class_basename($r['party_type'] ?? '') }} #{{ $r['party_id'] }}</td>
                        <td>{{ $r['pan'] ?: '—' }}</td>
                        <td class="text-end">{{ $m($r['gross']) }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($r['rate'],3),'0'),'.') }}</td>
                        <td class="text-end">{{ $m($r['tds']) }}</td>
                        <td class="text-end">{{ $r['deductions'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No deductions in this period.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    @endforeach
</div>
@endsection
