@extends('layouts.admin')
@section('title', 'Business Accounting')
@section('header', 'Business Accounting')

@section('content')
@include('admin.settings._style')
@php $m = fn ($v) => $symbol . number_format((float) $v, $decimals); $o = $overview; @endphp

<div class="settings-shell">
    <div class="settings-hero"><div>
        <span class="settings-eyebrow"><i class="fas fa-scale-balanced"></i> Tax &amp; Compliance</span>
        <h1>Business Accounting</h1>
        <p>GST (Sec 9(5) + service + commission), GST TCS (Sec 52) and income-tax TDS (194-O / 194-C), rolled up from every order and settlement.</p>
    </div></div>

    @include('admin.accounting._tabs')

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach([
            ['GST — Sec 9(5) food (ECO pays)', $o['gst_9_5']],
            ['GST — platform services (18%)', $o['gst_service']],
            ['GST — on commission (18%)', $o['gst_commission']],
            ['GST output — total', $o['gst_output_total']],
            ['GST TCS (Sec 52)', $o['tcs']],
            ['TDS 194-O (restaurants)', $o['tds_194o']],
            ['TDS 194-C (drivers)', $o['tds_194c']],
        ] as [$label, $value])
            <div class="col-6 col-md-3">
                <div class="table-card p-3">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="h5 mb-0">{{ $m($value) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @php
        $accountsReady = \App\Services\Integration\WebhookDispatcher::enabled('accounts');
        $lastSync = json_decode((string) \App\Models\AppSetting::getValue('integration_accounts_last_sync', ''), true) ?: null;
    @endphp
    <div class="table-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fas fa-rotate me-2 text-primary"></i>Sync existing data to the Accounts app</h5>
            @if($lastSync)
                <span class="small text-muted">Last backfill {{ \Illuminate\Support\Carbon::parse($lastSync['at'])->diffForHumans() }} — {{ $lastSync['journals'] ?? 0 }} journals, {{ $lastSync['tax_rows'] ?? 0 }} tax rows</span>
            @endif
        </div>
        <div class="p-3">
            <p class="text-muted small mb-3">Replays every journal entry (with lines + order/payout snapshot) and every tax-ledger row already in these books to the standalone Accounts app, so its ledger and GST/TDS reports start from history instead of empty. Queued &amp; retried; safe to run again.</p>
            <form method="POST" action="{{ route('admin.settings.integrations.sync', 'accounts') }}" class="row g-2 align-items-end"
                  onsubmit="return confirm('Queue a full backfill to the Accounts app? Make sure a queue worker is running.');">
                @csrf
                <div class="col-sm-4">
                    <label class="form-label small mb-1">Only entries on/after (optional)</label>
                    <input type="date" name="since" class="form-control form-control-sm" {{ $accountsReady ? '' : 'disabled' }}>
                </div>
                <div class="col-sm-auto">
                    <button class="btn btn-primary btn-sm" {{ $accountsReady ? '' : 'disabled' }}><i class="fas fa-rotate me-1"></i> Sync existing data</button>
                </div>
            </form>
            @unless($accountsReady)
                <div class="small text-muted mt-2"><i class="fas fa-circle-info me-1"></i> Turn on the Accounts integration (URL + secret + toggle) in <a href="{{ route('admin.settings.integrations') }}">Settings &rarr; Integrations</a> to enable this.</div>
            @endunless
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="table-card">
                <div class="card-header"><h5 class="mb-0">Financial year to date ({{ $o['period']['fy'] }})</h5></div>
                <div class="table-responsive"><table class="table mb-0">
                    <tr><td>GST output</td><td class="text-end">{{ $m($o['ytd']['gst']) }}</td></tr>
                    <tr><td>GST TCS</td><td class="text-end">{{ $m($o['ytd']['tcs']) }}</td></tr>
                    <tr><td>TDS 194-O</td><td class="text-end">{{ $m($o['ytd']['tds_194o']) }}</td></tr>
                    <tr><td>TDS 194-C</td><td class="text-end">{{ $m($o['ytd']['tds_194c']) }}</td></tr>
                </table></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-card">
                <div class="card-header"><h5 class="mb-0">Upcoming due dates</h5></div>
                <div class="table-responsive"><table class="table mb-0">
                    <tr><td>GSTR-1</td><td class="text-end">{{ $o['due_dates']['gstr1'] }}</td></tr>
                    <tr><td>GSTR-3B</td><td class="text-end">{{ $o['due_dates']['gstr3b'] }}</td></tr>
                    <tr><td>GSTR-8 (TCS)</td><td class="text-end">{{ $o['due_dates']['gstr8'] }}</td></tr>
                    <tr><td>Form 26Q (TDS)</td><td class="text-end">{{ $o['due_dates']['form26q'] }}</td></tr>
                </table></div>
            </div>
        </div>
    </div>
</div>
@endsection
