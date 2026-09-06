@extends('layouts.admin')

@section('title', 'AI Decision #' . $decision->id)
@section('header', 'AI Decision')

@section('styles')
@include('admin.ai._partials.styles')
<style>
    .aid-wrap { display: grid; gap: 20px; }

    .aid-hero {
        position: relative; overflow: hidden; border-radius: 22px; padding: 22px 24px; color: #fff;
        background:
            radial-gradient(circle at 12% 15%, rgba(255,255,255,.18), transparent 26%),
            radial-gradient(circle at 90% 10%, rgba(124,58,237,.45), transparent 30%),
            linear-gradient(135deg, #111827 0%, #4c1d95 55%, #7c3aed 100%);
        box-shadow: 0 22px 50px rgba(76, 29, 149, .28);
    }
    .aid-hero::after { content: ""; position: absolute; right: -70px; bottom: -110px; width: 240px; height: 240px; border-radius: 50%; background: rgba(255,255,255,.10); }
    .aid-hero > * { position: relative; z-index: 1; }
    .aid-hero-top { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 14px; }
    .aid-hero h1 { font-size: 1.55rem; font-weight: 950; letter-spacing: -.02em; margin: 0 0 4px; }
    .aid-hero-sub { color: rgba(255,255,255,.82); font-size: 12.5px; font-weight: 600; }
    .aid-hero-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
    .aid-hero-pill {
        display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px;
        background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.2);
        font-size: 11.5px; font-weight: 800; letter-spacing: .01em;
    }
    .aid-hero .ai-btn-outline { background: rgba(255,255,255,.16); color: #fff; }
    .aid-hero .ai-btn-outline:hover { background: rgba(255,255,255,.26); }

    .aid-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; }
    .aid-kpi {
        border: 1px solid rgba(226,232,240,.9); border-radius: 16px; background: #fff;
        padding: 14px 16px; box-shadow: 0 10px 26px rgba(15,23,42,.05);
    }
    .aid-kpi .k { font-size: 10.5px; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; }
    .aid-kpi .v { font-size: 18px; font-weight: 950; color: #0f172a; letter-spacing: -.01em; margin-top: 5px; line-height: 1.15; }
    .aid-kpi .v.money.pos { color: #166534; }
    .aid-kpi .v.money.neg { color: #991b1b; }

    .aid-cols { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (max-width: 1100px) { .aid-cols { grid-template-columns: minmax(0, 1fr); } }
    .aid-col { display: grid; gap: 16px; min-width: 0; }

    .aid-action { border: 1px solid rgba(226,232,240,.85); border-radius: 16px; padding: 14px 16px; background: rgba(248,250,252,.6); }
    .aid-action + .aid-action { margin-top: 12px; }
    .aid-action-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
    .aid-action-key { font-weight: 900; color: #0f172a; font-size: 13.5px; }
    .aid-sub { font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin: 12px 0 6px; }
    .aid-sub:first-child { margin-top: 0; }

    .aid-json { margin-top: 10px; }
    .aid-json summary { list-style: none; cursor: pointer; font-size: 11.5px; font-weight: 800; color: #6d28d9; display: inline-flex; align-items: center; gap: 6px; }
    .aid-json summary::-webkit-details-marker { display: none; }
    .aid-json summary::before { content: "\f0da"; font-family: "Font Awesome 6 Free"; font-weight: 900; transition: transform .15s ease; }
    .aid-json[open] summary::before { transform: rotate(90deg); }
    .aid-json pre {
        margin: 8px 0 0; background: #0f172a; color: #e2e8f0; border-radius: 12px; padding: 14px 16px;
        font-size: 11.5px; line-height: 1.55; max-height: 340px; overflow: auto; white-space: pre-wrap; word-break: break-word;
    }

    .aid-summary-text { font-size: 13.5px; line-height: 1.6; color: #334155; margin: 0; }
    .aid-empty { color: #94a3b8; text-align: center; padding: 26px 12px; font-size: 12.5px; font-weight: 700; }

    .aid-approval { display: flex; align-items: flex-start; gap: 10px; padding: 12px 0; border-bottom: 1px dashed #e2e8f0; }
    .aid-approval:last-child { border-bottom: 0; padding-bottom: 0; }
    .aid-approval:first-child { padding-top: 0; }
    .aid-approval-body { min-width: 0; flex: 1; }
    .aid-approval-title { font-weight: 800; font-size: 12.5px; color: #0f172a; }
    .aid-approval-meta { font-size: 11.5px; color: #64748b; margin-top: 2px; }
</style>
@endsection

@section('content')
@php
    $riskLevel = $decision->risk_level ?: 'info';
    $execStatus = $decision->execution_status ?: 'logged';
    $confidencePct = $decision->confidence !== null ? round(((float) $decision->confidence) * (($decision->confidence <= 1) ? 100 : 1)) : null;
    $impact = $decision->expected_financial_impact;
    $roi = $decision->roi;
@endphp

<div class="aid-wrap">

    <div class="aid-hero">
        <div class="aid-hero-top">
            <div>
                <h1>Decision #{{ $decision->id }}</h1>
                <div class="aid-hero-sub">
                    {{ \Illuminate\Support\Str::headline($decision->agent_key ?? 'Agent') }}
                    &middot; {{ \Illuminate\Support\Str::headline($decision->decision_type ?? 'decision') }}
                    @if($decision->provider) &middot; {{ $decision->provider }}{{ $decision->model ? ' / '.$decision->model : '' }} @endif
                    &middot; {{ $decision->created_at?->format('d M Y, h:i A') }} ({{ $decision->created_at?->diffForHumans() }})
                </div>
            </div>
            <a href="{{ route('admin.ai.decisions.index') }}" class="ai-btn ai-btn-outline ai-btn-sm"><i class="fas fa-arrow-left"></i> Back to decisions</a>
        </div>
        <div class="aid-hero-pills">
            <span class="aid-hero-pill"><i class="fas fa-gauge-high"></i> Risk: {{ ucfirst($riskLevel) }}</span>
            <span class="aid-hero-pill"><i class="fas fa-bolt"></i> {{ \Illuminate\Support\Str::headline($execStatus) }}</span>
            @if($decision->policy_status)<span class="aid-hero-pill"><i class="fas fa-scale-balanced"></i> Policy: {{ \Illuminate\Support\Str::headline($decision->policy_status) }}</span>@endif
            <span class="aid-hero-pill"><i class="fas {{ $decision->requires_approval ? 'fa-user-check' : 'fa-robot' }}"></i> {{ $decision->requires_approval ? 'Needs approval' : 'No approval needed' }}</span>
            @if($decision->auto_executed)<span class="aid-hero-pill"><i class="fas fa-wand-magic-sparkles"></i> Auto-executed</span>@endif
            @if($decision->trigger)<span class="aid-hero-pill"><i class="fas fa-satellite-dish"></i> {{ \Illuminate\Support\Str::headline($decision->trigger) }}</span>@endif
        </div>
    </div>

    <div class="aid-kpis">
        <div class="aid-kpi"><div class="k">Risk level</div><div class="v"><span class="ai-status-pill {{ $riskLevel }}">{{ ucfirst($riskLevel) }}</span></div></div>
        <div class="aid-kpi"><div class="k">Confidence</div><div class="v">{{ $confidencePct !== null ? $confidencePct.'%' : '—' }}</div></div>
        <div class="aid-kpi"><div class="k">Execution</div><div class="v"><span class="ai-status-pill {{ $execStatus }}">{{ \Illuminate\Support\Str::headline($execStatus) }}</span></div></div>
        <div class="aid-kpi">
            <div class="k">Expected impact</div>
            <div class="v money {{ $impact === null ? '' : ((float) $impact >= 0 ? 'pos' : 'neg') }}">
                {{ $impact === null ? '—' : ($currencySymbol ?? '₹').number_format((float) $impact, $currencyDecimals ?? 2) }}
            </div>
        </div>
        <div class="aid-kpi">
            <div class="k">ROI</div>
            <div class="v">{{ $roi === null ? '—' : number_format((float) $roi, 2).'x' }}</div>
        </div>
        <div class="aid-kpi"><div class="k">Actions</div><div class="v">{{ $decision->actions->count() }}</div></div>
    </div>

    <div class="aid-cols">
        <div class="aid-col">
            <div class="ai-panel">
                <div class="ai-panel-head"><h3 class="ai-panel-title">Why the AI proposed this</h3></div>
                <div class="ai-panel-body">
                    <p class="aid-summary-text">{{ $decision->reason_summary ?: 'No summary recorded.' }}</p>
                </div>
            </div>

            <div class="ai-panel">
                <div class="ai-panel-head"><h3 class="ai-panel-title">Proposed action</h3></div>
                <div class="ai-panel-body">
                    @include('admin.ai._partials.value-table', ['data' => $decision->proposed_action])
                    @if(! empty($decision->expected_result))
                        <div class="aid-sub" style="margin-top:16px">Expected result</div>
                        @include('admin.ai._partials.value-table', ['data' => $decision->expected_result])
                    @endif
                    <details class="aid-json">
                        <summary>Raw JSON</summary>
                        <pre>{{ json_encode(['proposed_action' => $decision->proposed_action, 'expected_result' => $decision->expected_result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                </div>
            </div>

            <div class="ai-panel">
                <div class="ai-panel-head">
                    <h3 class="ai-panel-title">Actions</h3>
                    <span class="ai-panel-sub">{{ $decision->actions->count() }} attached</span>
                </div>
                <div class="ai-panel-body">
                    @forelse($decision->actions as $action)
                        <div class="aid-action">
                            <div class="aid-action-head">
                                <span class="aid-action-key">{{ $action->action_key }}</span>
                                <span class="ai-status-pill {{ $action->status }}">{{ \Illuminate\Support\Str::headline($action->status) }}</span>
                                @if($action->risk_level)<span class="ai-status-pill {{ $action->risk_level }}">{{ ucfirst($action->risk_level) }} risk</span>@endif
                                @if($action->executed_at)<span class="aid-approval-meta">executed {{ $action->executed_at->diffForHumans() }}</span>@endif
                            </div>

                            <div class="aid-sub">Parameters</div>
                            @include('admin.ai._partials.value-table', ['data' => $action->parameters])

                            @if(! empty($action->policy_result))
                                <div class="aid-sub">Policy evaluation</div>
                                @include('admin.ai._partials.value-table', ['data' => $action->policy_result])
                            @endif

                            @if(! empty($action->result))
                                <div class="aid-sub">Result</div>
                                @include('admin.ai._partials.value-table', ['data' => $action->result])
                            @endif

                            <details class="aid-json">
                                <summary>Raw JSON</summary>
                                <pre>{{ json_encode([
                                    'parameters' => $action->parameters,
                                    'policy_result' => $action->policy_result,
                                    'result' => $action->result,
                                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        </div>
                    @empty
                        <div class="aid-empty">No action attached to this decision.</div>
                    @endforelse
                </div>
            </div>

            @if(! empty($decision->execution_result) || ! empty($decision->actual_result))
                <div class="ai-panel">
                    <div class="ai-panel-head"><h3 class="ai-panel-title">Execution outcome</h3></div>
                    <div class="ai-panel-body">
                        @if(! empty($decision->execution_result))
                            <div class="aid-sub">Execution result</div>
                            @include('admin.ai._partials.value-table', ['data' => $decision->execution_result])
                        @endif
                        @if(! empty($decision->actual_result))
                            <div class="aid-sub">Actual result</div>
                            @include('admin.ai._partials.value-table', ['data' => $decision->actual_result])
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="aid-col">
            <div class="ai-panel">
                <div class="ai-panel-head">
                    <h3 class="ai-panel-title">Approvals</h3>
                    <span class="ai-panel-sub">{{ $decision->approvals->count() }}</span>
                </div>
                <div class="ai-panel-body">
                    @forelse($decision->approvals as $approval)
                        <div class="aid-approval">
                            <span class="ai-status-pill {{ $approval->status }}">{{ ucfirst($approval->status) }}</span>
                            <div class="aid-approval-body">
                                <div class="aid-approval-title">Approval #{{ $approval->id }}</div>
                                <div class="aid-approval-meta">
                                    @if($approval->reviewed_by)
                                        Reviewed by {{ $approval->reviewer?->name ?: 'Admin #'.$approval->reviewed_by }}
                                        @if($approval->reviewed_at) &middot; {{ $approval->reviewed_at->diffForHumans() }} @endif
                                    @else
                                        Awaiting review
                                    @endif
                                </div>
                                @if($approval->admin_note)<div class="aid-approval-meta" style="margin-top:4px">“{{ $approval->admin_note }}”</div>@endif
                            </div>
                        </div>
                    @empty
                        <div class="aid-empty">No approval requested.</div>
                    @endforelse
                </div>
            </div>

            <div class="ai-panel">
                <div class="ai-panel-head"><h3 class="ai-panel-title">Decision metadata</h3></div>
                <div class="ai-panel-body">
                    @include('admin.ai._partials.value-table', ['data' => [
                        'agent' => \Illuminate\Support\Str::headline($decision->agent_key ?? '—'),
                        'decision_type' => \Illuminate\Support\Str::headline($decision->decision_type ?? '—'),
                        'trigger' => $decision->trigger ? \Illuminate\Support\Str::headline($decision->trigger) : '—',
                        'provider' => $decision->provider ?: '—',
                        'model' => $decision->model ?: '—',
                        'policy_status' => \Illuminate\Support\Str::headline($decision->policy_status ?? '—'),
                        'requires_approval' => (bool) $decision->requires_approval,
                        'auto_executed' => (bool) $decision->auto_executed,
                        'created_at' => $decision->created_at?->format('d M Y, h:i A'),
                        'updated_at' => $decision->updated_at?->format('d M Y, h:i A'),
                    ]])
                </div>
            </div>

            <div class="ai-panel">
                <div class="ai-panel-head"><h3 class="ai-panel-title">Input snapshot</h3></div>
                <div class="ai-panel-body">
                    <div style="max-height: 360px; overflow: auto;">
                        @include('admin.ai._partials.value-table', ['data' => $decision->input_snapshot])
                    </div>
                    <details class="aid-json">
                        <summary>Raw JSON</summary>
                        <pre>{{ json_encode($decision->input_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
