@extends('layouts.admin')

@section('title', 'AI Approvals')
@section('header', 'AI Approvals')

@section('styles')
@include('admin.ai._partials.styles')
<style>
    .ai-tab-link { padding: 8px 14px; border-radius: 10px; font-weight: 800; font-size: 12px; letter-spacing: .01em; color: #64748b; text-decoration: none; transition: background .15s ease, color .15s ease; }
    .ai-tab-link:hover { color: #4338ca; }
    .ai-tab-link.active { background: #ede9fe; color: #6d28d9; }

    /* Approval list ------------------------------------------------------- */
    .ai-appr-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .ai-appr-table thead th {
        font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em;
        color: #94a3b8; padding: 10px 14px; text-align: left; white-space: nowrap;
        border-bottom: 1px solid #eef2f7;
    }
    .ai-appr-table thead th.text-end { text-align: right; }
    .ai-appr-table tbody td { padding: 13px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 13px; color: #0f172a; }
    .ai-appr-table tbody tr:last-child td { border-bottom: 0; }
    .ai-appr-table tbody tr:hover td { background: #fafafe; }

    .ai-appr-id { font-weight: 800; font-size: 12.5px; color: #6d28d9; text-decoration: none; }
    .ai-appr-id:hover { text-decoration: underline; }
    .ai-appr-action { font-weight: 700; font-size: 13px; color: #0f172a; line-height: 1.3; }
    .ai-appr-key { color: #94a3b8; font-size: 11px; font-weight: 600; margin-top: 2px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .ai-appr-agent { font-size: 12.5px; font-weight: 600; color: #475569; }
    .ai-appr-time { font-size: 12.5px; font-weight: 600; color: #475569; white-space: nowrap; }
    .ai-appr-time small { display: block; color: #94a3b8; font-weight: 600; font-size: 10.5px; margin-top: 1px; }
    .ai-appr-muted { color: #cbd5e1; }

    /* Detail modal ------------------------------------------------------- */
    .ai-modal .modal-content { border: 0; border-radius: 20px; overflow: hidden; box-shadow: 0 30px 70px rgba(15, 23, 42, .22); }
    .ai-modal .modal-header { align-items: flex-start; gap: 12px; padding: 18px 22px; border-bottom: 1px solid #eef2f7; }
    .ai-modal .modal-title { font-size: 15px; font-weight: 900; letter-spacing: -.01em; color: #0f172a; line-height: 1.2; }
    .ai-modal-sub { font-size: 11.5px; font-weight: 600; color: #94a3b8; margin-top: 3px; }
    .ai-modal .modal-body { padding: 20px 22px; }
    .ai-modal .modal-footer { padding: 14px 22px; border-top: 1px solid #eef2f7; gap: 8px; }

    .ai-def-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 20px; margin: 0; }
    .ai-def { min-width: 0; }
    .ai-def dt { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8 !important; margin-bottom: 3px; }
    .ai-def dd { margin: 0; font-size: 13px; font-weight: 600; color: #0f172a; word-break: break-word; }

    .ai-block-title { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin: 20px 0 8px; }
    .ai-reason { font-size: 12.5px; line-height: 1.6; color: #334155; background: #f8fafc; border: 1px solid #eef2f7; border-radius: 12px; padding: 12px 14px; }

    .ai-modal .value-table-wrap table.table { font-size: 12.5px; margin-bottom: 0; }
    .ai-modal .value-table-wrap table.table td { padding: 7px 10px; }
</style>
@endsection

@section('content')
<div class="ai-shell">
    <div class="ai-hero">
        <div>
            <h1>Approval Center</h1>
            <p>Actions the AI proposed that need a human decision before they run.</p>
        </div>
        <a href="{{ route('admin.ai.index') }}" class="ai-btn ai-btn-outline">Control center</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
    @endif

    <section class="ai-grid ai-kpi-grid">
        <div class="ai-kpi-card" style="--accent:#f59e0b;--card-glow:rgba(245,158,11,.16);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-hourglass-half"></i></div></div>
            <div class="ai-kpi-label">Pending</div>
            <div class="ai-kpi-value">{{ number_format($stats['pending']) }}</div>
        </div>
        <div class="ai-kpi-card" style="--accent:#22c55e;--card-glow:rgba(34,197,94,.14);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-check"></i></div></div>
            <div class="ai-kpi-label">Approved Today</div>
            <div class="ai-kpi-value">{{ number_format($stats['approved_today']) }}</div>
        </div>
        <div class="ai-kpi-card" style="--accent:#ef4444;--card-glow:rgba(239,68,68,.14);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-xmark"></i></div></div>
            <div class="ai-kpi-label">Rejected Today</div>
            <div class="ai-kpi-value">{{ number_format($stats['rejected_today']) }}</div>
        </div>
        <div class="ai-kpi-card" style="--accent:#7c3aed;--card-glow:rgba(124,58,237,.16);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-layer-group"></i></div></div>
            <div class="ai-kpi-label">Total Tracked</div>
            <div class="ai-kpi-value">{{ number_format($stats['total']) }}</div>
        </div>
    </section>

    <div class="ai-panel">
        <div class="ai-panel-head">
            <div class="d-flex gap-1">
                @foreach($allowedStatuses as $tabStatus)
                    <a href="{{ route('admin.ai.approvals.index', ['status' => $tabStatus]) }}" class="ai-tab-link {{ $status === $tabStatus ? 'active' : '' }}">{{ ucfirst($tabStatus) }}</a>
                @endforeach
            </div>
        </div>
        <div class="ai-panel-body pt-2">
            <div class="table-responsive">
                <table class="ai-appr-table">
                    <thead>
                        <tr>
                            <th>Decision</th>
                            <th>Action</th>
                            <th>Agent</th>
                            <th>Risk</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th class="text-end">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($approvals as $approval)
                        @php
                            $agentKey = $approval->decision?->agent_key;
                            $riskLevel = $approval->decision?->risk_level ?? $approval->action?->risk_level;
                            $actionKey = $approval->action?->action_key;
                            $actionLabel = $actionKey ? \Illuminate\Support\Str::headline($actionKey) : 'Decision only';
                        @endphp
                        <tr>
                            <td><a href="{{ route('admin.ai.decisions.show', $approval->ai_decision_id) }}" class="ai-appr-id">#{{ $approval->ai_decision_id }}</a></td>
                            <td>
                                <div class="ai-appr-action">{{ $actionLabel }}</div>
                                @if($actionKey)<div class="ai-appr-key">{{ $actionKey }}</div>@endif
                            </td>
                            <td><span class="ai-appr-agent">{{ $agentKey ? \Illuminate\Support\Str::headline($agentKey) : '—' }}</span></td>
                            <td>
                                @if($riskLevel)
                                    <span class="ai-status-pill {{ $riskLevel }}">{{ ucfirst($riskLevel) }}</span>
                                @else
                                    <span class="ai-appr-muted">—</span>
                                @endif
                            </td>
                            <td><span class="ai-status-pill {{ $approval->status }}">{{ ucfirst($approval->status) }}</span></td>
                            <td class="ai-appr-time">
                                @if($approval->created_at)
                                    {{ $approval->created_at->format('d M Y, h:i A') }}
                                    <small>{{ $approval->created_at->diffForHumans() }}</small>
                                @else
                                    <span class="ai-appr-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="ai-btn ai-btn-outline ai-btn-sm" data-bs-toggle="modal" data-bs-target="#aiApprovalModal{{ $approval->id }}">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="ai-empty">No {{ $status }} approvals found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ai-panel-body pt-0">{{ $approvals->links() }}</div>
    </div>
</div>

@foreach($approvals as $approval)
    @php
        $agentKey = $approval->decision?->agent_key;
        $riskLevel = $approval->decision?->risk_level ?? $approval->action?->risk_level;
        $actionKey = $approval->action?->action_key;
        $actionLabel = $actionKey ? \Illuminate\Support\Str::headline($actionKey) : 'Decision only';
        $confidence = $approval->decision?->confidence;
        $requestedPayload = is_array($approval->requested_payload) ? $approval->requested_payload : [];
        $modifiedPayload = is_array($approval->modified_payload) ? $approval->modified_payload : [];
        $reviewerName = $approval->reviewer?->name ?: ($approval->reviewed_by ? 'Admin #'.$approval->reviewed_by : null);
    @endphp
    <div class="modal fade ai-modal" id="aiApprovalModal{{ $approval->id }}" tabindex="-1" aria-labelledby="aiApprovalModalLabel{{ $approval->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="flex-grow-1">
                        <h5 class="modal-title" id="aiApprovalModalLabel{{ $approval->id }}">Approval #{{ $approval->id }} &middot; {{ $actionLabel }}</h5>
                        <div class="ai-modal-sub">Decision #{{ $approval->ai_decision_id }}@if($agentKey) &middot; {{ \Illuminate\Support\Str::headline($agentKey) }} agent @endif</div>
                    </div>
                    <span class="ai-status-pill {{ $approval->status }}">{{ ucfirst($approval->status) }}</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="ai-def-grid">
                        <div class="ai-def">
                            <dt>Action</dt>
                            <dd>{{ $actionLabel }}@if($actionKey)<div class="ai-appr-key">{{ $actionKey }}</div>@endif</dd>
                        </div>
                        <div class="ai-def">
                            <dt>Agent</dt>
                            <dd>{{ $agentKey ? \Illuminate\Support\Str::headline($agentKey) : '—' }}</dd>
                        </div>
                        <div class="ai-def">
                            <dt>Risk level</dt>
                            <dd>{{ $riskLevel ? ucfirst($riskLevel) : '—' }}</dd>
                        </div>
                        <div class="ai-def">
                            <dt>Confidence</dt>
                            <dd>{{ $confidence !== null ? number_format((float) $confidence * 100, 1).'%' : '—' }}</dd>
                        </div>
                        <div class="ai-def">
                            <dt>Requested</dt>
                            <dd>{{ $approval->created_at ? $approval->created_at->format('d M Y, h:i A') : '—' }}</dd>
                        </div>
                        <div class="ai-def">
                            <dt>Reviewed</dt>
                            <dd>
                                @if($approval->reviewed_at)
                                    {{ $approval->reviewed_at->format('d M Y, h:i A') }}
                                    <span class="text-muted">({{ $approval->reviewed_at->diffForHumans() }})</span>
                                @else
                                    Not yet reviewed
                                @endif
                            </dd>
                        </div>
                        @if($reviewerName)
                            <div class="ai-def">
                                <dt>Reviewed by</dt>
                                <dd>{{ $reviewerName }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if($approval->decision?->reason_summary)
                        <div class="ai-block-title">Why the AI proposed this</div>
                        <div class="ai-reason">{{ $approval->decision->reason_summary }}</div>
                    @endif

                    <div class="ai-block-title">Requested changes</div>
                    <div class="value-table-wrap">
                        @include('admin.ai._partials.value-table', ['data' => $requestedPayload])
                    </div>

                    @if(!empty($modifiedPayload))
                        <div class="ai-block-title">Modified payload (applied instead)</div>
                        <div class="value-table-wrap">
                            @include('admin.ai._partials.value-table', ['data' => $modifiedPayload])
                        </div>
                    @endif

                    @if($approval->admin_note)
                        <div class="ai-block-title">Admin note</div>
                        <div class="ai-reason">{{ $approval->admin_note }}</div>
                    @endif
                </div>
                <div class="modal-footer">
                    @if($approval->status === 'pending')
                        <form action="{{ route('admin.ai.approvals.reject', $approval) }}" method="POST" class="me-auto">
                            @csrf
                            <button class="ai-btn ai-btn-danger ai-btn-sm"><i class="fas fa-xmark"></i> Reject</button>
                        </form>
                        <form action="{{ route('admin.ai.approvals.approve', $approval) }}" method="POST" class="d-flex align-items-center gap-2">
                            @csrf
                            <input name="admin_note" class="form-control form-control-sm" placeholder="Optional note" style="min-width: 200px;">
                            <button class="ai-btn ai-btn-success ai-btn-sm"><i class="fas fa-check"></i> Approve</button>
                        </form>
                    @else
                        <span class="text-muted small me-auto">
                            {{ ucfirst($approval->status) }}@if($approval->reviewed_at) on {{ $approval->reviewed_at->format('d M Y, h:i A') }}@endif
                        </span>
                        <button type="button" class="ai-btn ai-btn-outline ai-btn-sm" data-bs-dismiss="modal">Close</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection
