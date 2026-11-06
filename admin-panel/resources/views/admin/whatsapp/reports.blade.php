@extends('layouts.admin')

@section('title', 'WhatsApp Reports')
@section('header', 'WhatsApp Reports')

@section('content')
@include('admin.settings._style')
@php
    $rate = fn ($part, $whole) => $whole > 0 ? round($part * 100 / $whole) . '%' : '—';
    $max = max(1, (int) $daily->max(fn ($d) => max($d->sent, $d->received)));
@endphp
<div class="settings-shell">
    <div class="settings-hero">
        <div><span class="settings-eyebrow"><i class="fab fa-whatsapp"></i> Messaging</span><h1>Reports</h1><p>How your WhatsApp messages are performing.</p></div>
    </div>
    @include('admin.whatsapp._nav')

    <div class="row g-3 mb-4">
        @foreach([
            ['Sent', $totals['sent'], ''], ['Delivered', $totals['delivered'], $rate($totals['delivered'], $totals['sent']) . ' of sent'],
            ['Read', $totals['read'], $rate($totals['read'], $totals['delivered']) . ' of delivered'], ['Failed', $totals['failed'], ''],
            ['Received', $totals['inbound'], ''], ['Contacts', $totals['contacts'], $totals['optouts'] . ' opted out'],
        ] as [$label, $value, $sub])
            <div class="col-6 col-md-4 col-xl-2"><div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h4 class="mb-0">{{ number_format($value) }}</h4><div class="small text-muted">{{ $sub }}</div></div></div></div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100"><div class="card-body">
                <h5>By message type</h5>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Type</th><th class="text-end">Sent</th><th class="text-end">Delivered</th><th class="text-end">Read</th><th class="text-end">Failed</th></tr></thead>
                    <tbody>
                    @forelse($byEvent as $row)
                        <tr><td>{{ str_replace('_', ' ', $row->event) }}</td><td class="text-end">{{ $row->total }}</td>
                            <td class="text-end">{{ $row->delivered }} <span class="text-muted small">({{ $rate($row->delivered, $row->total) }})</span></td>
                            <td class="text-end">{{ $row->read_count }}</td><td class="text-end {{ $row->failed ? 'text-danger' : '' }}">{{ $row->failed }}</td></tr>
                    @empty<tr><td colspan="5" class="text-muted text-center">No messages yet.</td></tr>@endforelse
                    </tbody></table></div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-4"><div class="card-body">
                <h5>Last 14 days</h5>
                @forelse($daily as $d)
                    <div class="d-flex align-items-center gap-2 small mb-1">
                        <span style="width:54px" class="text-muted">{{ \Carbon\Carbon::parse($d->day)->format('d M') }}</span>
                        <div class="flex-grow-1">
                            <div style="height:7px;border-radius:4px;background:#16a34a;width:{{ round($d->sent * 100 / $max) }}%"></div>
                            <div style="height:7px;border-radius:4px;background:#3b82f6;margin-top:2px;width:{{ round($d->received * 100 / $max) }}%"></div>
                        </div>
                        <span style="width:70px" class="text-end">{{ $d->sent }} / {{ $d->received }}</span>
                    </div>
                @empty<div class="text-muted">No activity yet.</div>@endforelse
                <div class="small text-muted mt-2"><span style="color:#16a34a">■</span> sent &nbsp; <span style="color:#3b82f6">■</span> received</div>
            </div></div>
            <div class="card"><div class="card-body">
                <h5>Most common failures</h5>
                @forelse($topErrors as $e)<div class="small mb-1"><span class="badge bg-danger me-1">{{ $e->c }}</span>{{ \Illuminate\Support\Str::limit($e->error, 110) }}</div>@empty<div class="text-muted small">No failures. 🎉</div>@endforelse
            </div></div>
        </div>
    </div>
</div>
@endsection
