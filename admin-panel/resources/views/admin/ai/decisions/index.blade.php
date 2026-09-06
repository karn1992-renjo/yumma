@extends('layouts.admin')

@section('title', 'AI Decision History')
@section('header', 'AI Decision History')

@section('styles')
@include('admin.ai._partials.styles')
@endsection

@section('content')
<div class="ai-shell">
    <div class="ai-hero">
        <div>
            <h1>Decision History</h1>
            <p>Complete audit trail of every AI observation, recommendation, and action.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="ai-activity-status" class="ai-status-pill info">connecting&hellip;</span>
            <a href="{{ route('admin.ai.index') }}" class="ai-btn ai-btn-outline">Control center</a>
        </div>
    </div>

    <section class="ai-grid ai-kpi-grid">
        <div class="ai-kpi-card" style="--accent:#7c3aed;--card-glow:rgba(124,58,237,.16);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-layer-group"></i></div></div>
            <div class="ai-kpi-label">Total Decisions</div>
            <div class="ai-kpi-value">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="ai-kpi-card" style="--accent:#3b82f6;--card-glow:rgba(59,130,246,.15);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-calendar-day"></i></div></div>
            <div class="ai-kpi-label">Today</div>
            <div class="ai-kpi-value">{{ number_format($stats['today']) }}</div>
        </div>
        <div class="ai-kpi-card" style="--accent:#f59e0b;--card-glow:rgba(245,158,11,.16);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-hourglass-half"></i></div></div>
            <div class="ai-kpi-label">Pending Approval</div>
            <div class="ai-kpi-value">{{ number_format($stats['pending_approval']) }}</div>
        </div>
        <div class="ai-kpi-card" style="--accent:#22c55e;--card-glow:rgba(34,197,94,.14);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-bolt"></i></div></div>
            <div class="ai-kpi-label">Executed</div>
            <div class="ai-kpi-value">{{ number_format($stats['executed']) }}</div>
        </div>
    </section>

    <div class="ai-panel">
        <div class="ai-panel-body pt-3">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3">
                    <select name="agent_key" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All agents</option>
                        @foreach($agentOptions as $agentOption)
                            <option value="{{ $agentOption }}" {{ ($filters['agent_key'] ?? '') === $agentOption ? 'selected' : '' }}>{{ ucfirst($agentOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="risk_level" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All risk levels</option>
                        @foreach(['low', 'medium', 'high', 'critical'] as $riskOption)
                            <option value="{{ $riskOption }}" {{ ($filters['risk_level'] ?? '') === $riskOption ? 'selected' : '' }}>{{ ucfirst($riskOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="execution_status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All execution statuses</option>
                        @foreach(['not_required', 'pending', 'simulated', 'executed', 'blocked', 'failed'] as $statusOption)
                            <option value="{{ $statusOption }}" {{ ($filters['execution_status'] ?? '') === $statusOption ? 'selected' : '' }}>{{ \Illuminate\Support\Str::headline($statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    @if(array_filter($filters))
                        <a href="{{ route('admin.ai.decisions.index') }}" class="ai-btn ai-btn-outline w-100">Clear filters</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>ID</th><th>Agent</th><th>Type</th><th>Risk</th><th>Policy</th><th>Execution</th><th>Created</th><th></th></tr></thead>
                    <tbody id="ai-decisions-tbody">
                    @forelse($decisions as $decision)
                        <tr>
                            <td class="text-muted small">#{{ $decision->id }}</td>
                            <td class="fw-semibold">{{ ucfirst($decision->agent_key) }}</td>
                            <td>{{ \Illuminate\Support\Str::headline($decision->decision_type) }}</td>
                            <td><span class="ai-status-pill {{ $decision->risk_level }}">{{ $decision->risk_level }}</span></td>
                            <td><span class="ai-status-pill {{ $decision->policy_status }}">{{ \Illuminate\Support\Str::headline($decision->policy_status) }}</span></td>
                            <td><span class="ai-status-pill {{ $decision->execution_status }}">{{ \Illuminate\Support\Str::headline($decision->execution_status) }}</span></td>
                            <td class="text-muted small">{{ $decision->created_at?->diffForHumans() }}</td>
                            <td><a href="{{ route('admin.ai.decisions.show', $decision) }}" class="ai-panel-link">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="ai-empty">No AI decisions found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="ai-panel-body pt-0">{{ $decisions->links() }}</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var statusBadge = document.getElementById('ai-activity-status');
    var tbody = document.getElementById('ai-decisions-tbody');
    var isFiltered = {{ array_filter($filters) ? 'true' : 'false' }} || {{ $decisions->currentPage() > 1 ? 'true' : 'false' }};

    if (! window.Echo) {
        if (statusBadge) {
            statusBadge.textContent = 'offline';
        }
        return;
    }

    function riskPill(risk) {
        return '<span class="ai-status-pill ' + risk + '">' + risk + '</span>';
    }

    function headline(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function renderRow(decision) {
        var tr = document.createElement('tr');
        tr.dataset.decisionId = decision.id;

        var createdAt = decision.created_at ? new Date(decision.created_at) : null;
        var showUrl = @json(route('admin.ai.decisions.show', ['decision' => '__ID__'])).replace('__ID__', decision.id);

        tr.innerHTML = '<td class="text-muted small">#' + decision.id + '</td>'
            + '<td class="fw-semibold"></td>'
            + '<td></td>'
            + '<td>' + riskPill(decision.risk_level || 'low') + '</td>'
            + '<td>' + riskPill(decision.policy_status || 'pending') + '</td>'
            + '<td>' + riskPill(decision.execution_status || 'pending') + '</td>'
            + '<td class="text-muted small"></td>'
            + '<td><a href="' + showUrl + '" class="ai-panel-link">Open</a></td>';

        tr.children[1].textContent = headline(decision.agent_key);
        tr.children[2].textContent = headline(decision.decision_type);
        tr.children[6].textContent = createdAt ? createdAt.toLocaleString() : 'just now';

        return tr;
    }

    window.Echo.private('admin.ai-activity')
        .listen('.ai-decision-logged', function (event) {
            var decision = event.data || {};

            if (statusBadge) {
                statusBadge.textContent = 'live';
                statusBadge.className = 'ai-status-pill low';
            }

            if (isFiltered || ! tbody) {
                return;
            }

            var existing = tbody.querySelector('[data-decision-id="' + decision.id + '"]');
            if (existing) {
                existing.replaceWith(renderRow(decision));
                return;
            }

            var emptyRow = tbody.querySelector('.ai-empty');
            if (emptyRow) {
                emptyRow.closest('tr').remove();
            }

            tbody.prepend(renderRow(decision));

            while (tbody.children.length > 25) {
                tbody.removeChild(tbody.lastElementChild);
            }
        })
        .subscribed(function () {
            if (statusBadge) {
                statusBadge.textContent = 'live';
                statusBadge.className = 'ai-status-pill low';
            }
        })
        .error(function () {
            if (statusBadge) {
                statusBadge.textContent = 'error';
                statusBadge.className = 'ai-status-pill high';
            }
        });
});
</script>
@endsection
