@extends('layouts.admin')

@section('title', 'Integrations')
@section('header', 'Integrations')

@section('content')
@include('admin.settings._style')

@php
    $s = $settings;
    $v = fn ($k, $d = '') => $s[$k] ?? $d;
    $on = fn ($k) => (string) ($s[$k] ?? '0') === '1';
@endphp

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-plug"></i> Standalone apps</span>
            <h1>Integrations</h1>
            <p>Wire the separate <strong>Accounts</strong> and <strong>HRMS</strong> applications. When on, admin streams money events + identity changes to them over signed webhooks (queued, retried). Off = nothing is sent and admin is unaffected.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf
        <input type="hidden" name="redirect_to" value="admin.settings.integrations">

        @foreach([['accounts','Accounts app','Receives order / payout / tax / COD / refund / wallet events → builds its own general ledger. Also receives payroll journals from HRMS.'],['hrms','HRMS app','Receives identity changes for hr_manager & employee users. Pushes payroll journals to Accounts.']] as [$key, $title, $desc])
            <div class="settings-card">
                <div class="settings-card-header">
                    <div><h2 class="settings-card-title">{{ $title }}</h2><p class="settings-card-subtitle">{{ $desc }}</p></div>
                </div>
                <div class="settings-card-body">
                    <div class="settings-grid">
                        <div class="settings-field settings-span-12">
                            <input type="hidden" name="integration_{{ $key }}_enabled" value="0">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="integration_{{ $key }}_enabled" name="integration_{{ $key }}_enabled" value="1" @checked($on("integration_{$key}_enabled"))>
                                <label class="form-check-label fw-semibold" for="integration_{{ $key }}_enabled">Send events to the {{ $title }}</label>
                            </div>
                        </div>
                        <div class="settings-field settings-span-6">
                            <label class="form-label">Base URL</label>
                            <input type="url" name="integration_{{ $key }}_url" class="form-control" placeholder="https://{{ $key }}.swado.online" value="{{ $v("integration_{$key}_url") }}">
                        </div>
                        <div class="settings-field settings-span-6">
                            <label class="form-label">Shared secret</label>
                            <input type="text" name="integration_{{ $key }}_secret" class="form-control" value="{{ $v("integration_{$key}_secret") }}">
                            <div class="small text-muted mt-1" style="word-break: normal;">Must match <code>INTEGRATION_ADMIN_SECRET</code> in that app&rsquo;s <code>.env</code> file.</div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex align-items-center gap-2">
                        @php $st = $stats[$key] ?? collect(); @endphp
                        <span class="badge bg-success">sent {{ $st['sent'] ?? 0 }}</span>
                        <span class="badge bg-warning text-dark">failed {{ $st['failed'] ?? 0 }}</span>
                        <span class="badge bg-danger">dead {{ $st['dead'] ?? 0 }}</span>
                        <span class="badge bg-secondary">pending {{ $st['pending'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="mt-3"><button class="btn btn-primary">Save Integrations</button></div>
    </form>

    <div class="d-flex gap-2 mt-3">
        <form method="POST" action="{{ route('admin.settings.integrations.ping', 'accounts') }}">@csrf<button class="btn btn-outline-secondary btn-sm">Ping Accounts</button></form>
        <form method="POST" action="{{ route('admin.settings.integrations.ping', 'hrms') }}">@csrf<button class="btn btn-outline-secondary btn-sm">Ping HRMS</button></form>
    </div>

    @if($on('integration_accounts_enabled'))
        <p class="small text-muted mt-3 mb-0"><i class="fas fa-circle-info me-1"></i> To push the history already in your books to Accounts, use <strong>Sync existing data</strong> on <a href="{{ route('admin.accounting.overview') }}">Business Accounting &rarr; Overview</a>.</p>
    @endif

    <div class="table-card mt-4">
        <div class="card-header"><h5 class="mb-0">Recent deliveries</h5></div>
        <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>When</th><th>Target</th><th>Topic</th><th>Status</th><th>Attempts</th><th>Error</th><th></th></tr></thead>
            <tbody>
            @forelse($deliveries as $d)
                <tr>
                    <td class="small">{{ $d->created_at->format('d M H:i') }}</td>
                    <td>{{ ucfirst($d->target) }}</td>
                    <td class="small">{{ $d->topic }}</td>
                    <td><span class="badge bg-{{ ['sent'=>'success','failed'=>'warning text-dark','dead'=>'danger','pending'=>'secondary'][$d->status] ?? 'secondary' }}">{{ $d->status }}</span></td>
                    <td class="text-center">{{ $d->attempts }}</td>
                    <td class="small text-danger">{{ \Illuminate\Support\Str::limit($d->last_error, 60) }}</td>
                    <td>@if(in_array($d->status, ['failed','dead']))<form method="POST" action="{{ route('admin.settings.integrations.retry', $d) }}">@csrf<button class="btn btn-sm btn-outline-primary">Retry</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No deliveries yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
