@extends('layouts.admin')

@section('title', 'AI Control Center')
@section('header', 'AI Control Center')

@section('styles')
@include('admin.ai._partials.styles')
<style>
    .ai-kill-banner {
        border-radius: 18px; padding: 14px 18px; background: linear-gradient(135deg, #fef2f2, #fff);
        border: 1px solid #fecaca; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
    }
    .ai-hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; }
    .ai-hero h1 { font-size: 1.55rem; font-weight: 950; color: #0f172a; letter-spacing: -.02em; margin-bottom: 2px; }
    .ai-hero p { color: #64748b; font-weight: 600; font-size: 13.5px; margin: 0; }
    .ai-btn { border-radius: 12px; font-weight: 800; font-size: 13px; padding: 9px 16px; border: 0; }
    .ai-btn-primary { background: linear-gradient(135deg, #7c3aed, #4c1d95); color: #fff; }
    .ai-btn-danger { background: #fee2e2; color: #991b1b; }
    .ai-btn-success { background: #dcfce7; color: #166534; }
    .ai-btn-outline { background: #f1f5f9; color: #334155; }
    .ai-dashboard-row { grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); }
    .ai-side-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
</style>
@endsection

@section('content')
<div class="ai-shell">
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
    @endif

    @if($settings['kill_switch'])
        <div class="ai-kill-banner">
            <div><i class="fas fa-power-off me-2 text-danger"></i><strong>Kill switch is active.</strong> <span class="text-muted">Automatic execution is disabled; analysis, monitoring, and chat continue to run.</span></div>
            <form action="{{ route('admin.ai.resume') }}" method="POST" onsubmit="return confirm('Resume AI automatic execution?')">
                @csrf
                <button class="ai-btn ai-btn-danger"><i class="fas fa-play me-1"></i>Resume AI</button>
            </form>
        </div>
    @endif

    <div class="ai-hero">
        <div>
            <h1>AI Control Center</h1>
            <p>Autonomous operations, fleet, finance, accounting, and promotion management &middot; {{ ucfirst($settings['autonomy_mode']) }} mode</p>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('admin.ai.run') }}" method="POST">
                @csrf
                <button class="ai-btn ai-btn-primary"><i class="fas fa-play me-2"></i>Run cycle</button>
            </form>
            @if($settings['kill_switch'])
                <form action="{{ route('admin.ai.resume') }}" method="POST" onsubmit="return confirm('Resume AI automatic execution?')">
                    @csrf
                    <button class="ai-btn ai-btn-success"><i class="fas fa-play me-2"></i>Resume AI</button>
                </form>
            @else
                <form action="{{ route('admin.ai.kill-switch') }}" method="POST" onsubmit="return confirm('Activate AI kill switch now? Automatic execution will stop until you resume it.')">
                    @csrf
                    <button class="ai-btn ai-btn-danger"><i class="fas fa-power-off me-2"></i>Kill switch</button>
                </form>
            @endif
        </div>
    </div>

    <section class="ai-grid ai-kpi-grid">
        <div class="ai-kpi-card" style="--accent:#7c3aed;--card-glow:rgba(124,58,237,.16);">
            <div class="ai-kpi-top">
                <div class="ai-kpi-icon"><i class="fas fa-microchip"></i></div>
                <span class="ai-kpi-badge {{ $settings['simulation_mode'] ? 'ai-status-pill medium' : 'ai-status-pill low' }}">{{ $settings['simulation_mode'] ? 'Simulation' : 'Live' }}</span>
            </div>
            <div class="ai-kpi-label">Autonomy Mode</div>
            <div class="ai-kpi-value">{{ strtoupper($settings['autonomy_mode']) }}</div>
            <div class="ai-kpi-hint">Provider: {{ ucfirst($settings['provider']) }}</div>
        </div>
        <div class="ai-kpi-card" style="--accent:#3b82f6;--card-glow:rgba(59,130,246,.15);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-brain"></i></div></div>
            <div class="ai-kpi-label">Decisions Today</div>
            <div class="ai-kpi-value">{{ number_format($todayDecisions) }}</div>
            <div class="ai-kpi-hint">{{ number_format($recentActions->count()) }} recent actions</div>
        </div>
        <div class="ai-kpi-card" style="--accent:#f59e0b;--card-glow:rgba(245,158,11,.16);">
            <div class="ai-kpi-top"></div>
            <div class="ai-kpi-icon" style="margin-bottom:8px;"><i class="fas fa-clipboard-check"></i></div>
            <div class="ai-kpi-label">Pending Approvals</div>
            <div class="ai-kpi-value">{{ number_format($pendingApprovals->count()) }}</div>
            <a href="{{ route('admin.ai.approvals.index') }}" class="ai-panel-link">Review approvals &rarr;</a>
        </div>
        <div class="ai-kpi-card" style="--accent:#22c55e;--card-glow:rgba(34,197,94,.14);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-dollar-sign"></i></div></div>
            <div class="ai-kpi-label">AI Cost Today (USD)</div>
            <div class="ai-kpi-value">${{ number_format($todayCost, 4) }}</div>
            <div class="ai-kpi-hint">Budget ${{ number_format($routerHealth['daily_budget_usd'], 2) }} USD &middot; billed by the AI provider, not {{ $currencySymbol ?? '' }}</div>
        </div>
        <div class="ai-kpi-card" style="--accent:#14b8a6;--card-glow:rgba(20,184,166,.14);">
            <div class="ai-kpi-top"><div class="ai-kpi-icon"><i class="fas fa-receipt"></i></div></div>
            <div class="ai-kpi-label">Orders Today</div>
            <div class="ai-kpi-value">{{ number_format((int) data_get($business, 'orders_today', 0)) }}</div>
            <div class="ai-kpi-hint">{{ $currencySymbol ?? '' }}{{ number_format((float) data_get($business, 'gmv_today', 0), 2) }} GMV</div>
        </div>
    </section>

    <section class="ai-grid ai-dashboard-row">
        <div class="ai-panel">
            <div class="ai-panel-head">
                <div>
                    <h3 class="ai-panel-title">Decision Activity</h3>
                    <div class="ai-panel-sub">AI decisions logged per day, last 14 days</div>
                </div>
            </div>
            <div class="ai-chart-shell"><canvas id="aiDecisionTrendChart"></canvas></div>
        </div>
        <div class="ai-panel">
            <div class="ai-panel-head">
                <div>
                    <h3 class="ai-panel-title">Risk &amp; Outcome</h3>
                    <div class="ai-panel-sub">All-time decision breakdown</div>
                </div>
            </div>
            <div class="ai-chart-shell" style="height: 230px;"><canvas id="aiRiskChart"></canvas></div>
        </div>
    </section>

    <section class="ai-grid ai-side-row">
        <div class="ai-panel">
            <div class="ai-panel-head"><h3 class="ai-panel-title">Provider health</h3></div>
            <div class="ai-panel-body">
                <div class="ai-row-list">
                    @foreach($routerHealth['providers'] as $provider)
                        <div class="ai-row-card">
                            <div class="ai-row-icon"><i class="fas fa-plug"></i></div>
                            <div class="ai-row-body">
                                <div class="ai-row-title">{{ ucfirst($provider['provider']) }}</div>
                            </div>
                            <span class="ai-status-pill {{ $provider['configured'] ? 'low' : 'high' }}">{{ $provider['configured'] ? 'Configured' : 'Missing key' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="ai-panel">
            <div class="ai-panel-head"><h3 class="ai-panel-title">Critical alerts</h3></div>
            <div class="ai-panel-body">
                <div class="ai-row-list">
                    @forelse($criticalAlerts as $alert)
                        <div class="ai-row-card">
                            <div class="ai-row-icon" style="background: linear-gradient(135deg,#dc2626,#7c1d1d);"><i class="fas fa-triangle-exclamation"></i></div>
                            <div class="ai-row-body">
                                <div class="ai-row-title">{{ $alert->title }}</div>
                                <div class="ai-row-meta">{{ $alert->message }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="ai-empty">No open critical alerts.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <hr class="my-2">

    @php
        $exceptions = data_get($anomalies, 'exceptions', []);
        $fleet = data_get($business, 'fleet', []);
    @endphp

    <div class="ai-hero">
        <div>
            <h1 style="font-size:1.25rem;">Business &amp; Financial Reports</h1>
            <p>Live snapshot compiled by the AI tool layer</p>
        </div>
        <a href="{{ url()->current() }}" class="ai-btn ai-btn-outline"><i class="fas fa-rotate me-2"></i>Refresh</a>
    </div>

    @if(! empty($exceptions))
        <section>
            <div class="ai-section-title text-danger"><i class="fas fa-triangle-exclamation me-1"></i>Requires attention ({{ count($exceptions) }})</div>
            <div class="ai-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
                @foreach($exceptions as $exception)
                    <div class="ai-exception-card severity-{{ $exception['severity'] ?? 'medium' }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="fw-bold">{{ \Illuminate\Support\Str::headline($exception['type'] ?? 'Exception') }}</div>
                            <span class="ai-status-pill {{ ($exception['severity'] ?? '') === 'high' ? 'high' : 'medium' }}">{{ ucfirst($exception['severity'] ?? 'medium') }}</span>
                        </div>
                        <div class="text-muted small mt-1">{{ $exception['orders'] ?? 0 }} orders &middot; {{ $currencySymbol ?? '' }}{{ number_format((float) ($exception['amount'] ?? 0), 2) }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <div class="ai-section-title">Business snapshot</div>
        <div class="ai-grid ai-kpi-grid">
            @include('admin.ai._partials.stat-tile', ['label' => 'Orders today', 'value' => number_format((int) data_get($business, 'orders_today', 0)), 'icon' => 'fa-receipt', 'color' => 'primary'])
            @include('admin.ai._partials.stat-tile', ['label' => 'GMV today', 'value' => ($currencySymbol ?? '').number_format((float) data_get($business, 'gmv_today', 0), 2), 'icon' => 'fa-sack-dollar', 'color' => 'success'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Active orders', 'value' => number_format((int) data_get($business, 'active_orders', 0)), 'icon' => 'fa-bolt', 'color' => 'info'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Delivered today', 'value' => number_format((int) data_get($business, 'delivered_today', 0)), 'icon' => 'fa-circle-check', 'color' => 'success'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Cancelled today', 'value' => number_format((int) data_get($business, 'cancelled_today', 0)), 'icon' => 'fa-circle-xmark', 'color' => 'danger'])
            @include('admin.ai._partials.stat-tile', ['label' => 'GMV this month', 'value' => ($currencySymbol ?? '').number_format((float) data_get($business, 'gmv_month', 0), 2), 'icon' => 'fa-calendar-days', 'color' => 'secondary'])
        </div>
    </section>

    @if(! empty($fleet))
        <section>
            <div class="ai-section-title">Fleet</div>
            <div class="ai-grid ai-kpi-grid">
                @include('admin.ai._partials.stat-tile', ['label' => 'Total drivers', 'value' => number_format((int) data_get($fleet, 'total_drivers', 0)), 'icon' => 'fa-motorcycle', 'color' => 'primary'])
                @include('admin.ai._partials.stat-tile', ['label' => 'Online drivers', 'value' => number_format((int) data_get($fleet, 'online_drivers', 0)), 'hint' => 'active GPS ping, last 15 min', 'icon' => 'fa-signal', 'color' => 'success'])
                @include('admin.ai._partials.stat-tile', ['label' => 'On active orders', 'value' => number_format((int) data_get($fleet, 'drivers_with_active_orders', 0)), 'icon' => 'fa-route', 'color' => 'info'])
                @include('admin.ai._partials.stat-tile', ['label' => 'Open gigs today', 'value' => number_format((int) data_get($fleet, 'open_gigs_today', 0)), 'icon' => 'fa-briefcase', 'color' => 'secondary'])
                @include('admin.ai._partials.stat-tile', ['label' => 'Bookable tomorrow', 'value' => number_format((int) data_get($fleet, 'bookable_gigs_tomorrow', 0)), 'icon' => 'fa-calendar-plus', 'color' => 'secondary'])
            </div>
        </section>
    @endif

    <section>
        <div class="ai-section-title">Finance</div>
        <div class="ai-grid ai-kpi-grid">
            @include('admin.ai._partials.stat-tile', ['label' => 'Gross sales today', 'value' => ($currencySymbol ?? '').number_format((float) data_get($finance, 'gross_sales_today', 0), 2), 'icon' => 'fa-cash-register', 'color' => 'primary'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Admin commission', 'value' => ($currencySymbol ?? '').number_format((float) data_get($finance, 'admin_commission_today', 0), 2), 'icon' => 'fa-percent', 'color' => 'success'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Driver earnings', 'value' => ($currencySymbol ?? '').number_format((float) data_get($finance, 'driver_earning_today', 0), 2), 'icon' => 'fa-user-tie', 'color' => 'info'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Restaurant earnings', 'value' => ($currencySymbol ?? '').number_format((float) data_get($finance, 'restaurant_earning_today', 0), 2), 'icon' => 'fa-store', 'color' => 'info'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Promotion liability', 'value' => ($currencySymbol ?? '').number_format((float) data_get($finance, 'promotion_liability_today', 0), 2), 'icon' => 'fa-tags', 'color' => 'warning'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Gateway fees', 'value' => ($currencySymbol ?? '').number_format((float) data_get($finance, 'payment_gateway_fee_today', 0), 2), 'icon' => 'fa-credit-card', 'color' => 'secondary'])
        </div>
    </section>

    <section>
        <div class="ai-section-title">Accounting</div>
        <div class="ai-grid ai-kpi-grid">
            @include('admin.ai._partials.stat-tile', ['label' => 'COD not deposited', 'value' => ($currencySymbol ?? '').number_format((float) data_get($accounting, 'cod_collected_not_deposited', 0), 2), 'icon' => 'fa-money-bill-wave', 'color' => 'warning'])
            @include('admin.ai._partials.stat-tile', ['label' => 'COD orders pending', 'value' => number_format((int) data_get($accounting, 'cod_orders_pending_deposit', 0)), 'icon' => 'fa-hourglass-half', 'color' => 'warning'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Payouts pending', 'value' => data_get($accounting, 'payout_pending_orders') === null ? 'Not tracked' : number_format((int) data_get($accounting, 'payout_pending_orders')), 'icon' => 'fa-clock', 'color' => 'secondary'])
        </div>
    </section>

    <section>
        <div class="ai-section-title">Promotion</div>
        <div class="ai-grid ai-kpi-grid">
            @include('admin.ai._partials.stat-tile', ['label' => 'Active promotions', 'value' => number_format((int) data_get($promotion, 'active_promotion_count', 0)), 'hint' => number_format((int) data_get($promotion, 'promotion_count', 0)).' total', 'icon' => 'fa-tags', 'color' => 'primary'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Orders generated', 'value' => number_format((int) data_get($promotion, 'orders_generated', 0)), 'icon' => 'fa-receipt', 'color' => 'info'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Incremental revenue', 'value' => ($currencySymbol ?? '').number_format((float) data_get($promotion, 'incremental_revenue', 0), 2), 'icon' => 'fa-chart-line', 'color' => 'success'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Discount given', 'value' => ($currencySymbol ?? '').number_format((float) data_get($promotion, 'discount_given', 0), 2), 'icon' => 'fa-percent', 'color' => 'warning'])
            @include('admin.ai._partials.stat-tile', ['label' => 'Budget used', 'value' => ($currencySymbol ?? '').number_format((float) data_get($promotion, 'budget_used', 0), 2), 'icon' => 'fa-wallet', 'color' => 'warning'])
            @include('admin.ai._partials.stat-tile', ['label' => 'ROI', 'value' => data_get($promotion, 'roi') === null ? 'No promotions yet' : number_format((float) data_get($promotion, 'roi'), 2).'x', 'hint' => data_get($promotion, 'roi') === null ? null : 'revenue per unit spent', 'icon' => 'fa-scale-balanced', 'color' => 'secondary'])
        </div>
        @if(! empty(data_get($promotion, 'top_promotions')))
            <div class="ai-panel mt-3">
                <div class="ai-panel-head"><h3 class="ai-panel-title">Top promotions by usage</h3></div>
                <div class="ai-panel-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Title</th><th>Usage</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach(data_get($promotion, 'top_promotions') as $topPromotion)
                                <tr>
                                    <td>{{ $topPromotion['title'] ?? '—' }}</td>
                                    <td>{{ $topPromotion['usage_count'] ?? 0 }}</td>
                                    <td><span class="ai-status-pill info">{{ $topPromotion['status'] ?? '—' }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <details class="mt-2">
        <summary class="text-muted small" style="cursor: pointer;">Developer data (raw tool output)</summary>
        <div class="ai-panel mt-2">
            <div class="ai-panel-body pt-3">
                <div class="row g-4">
                    @foreach(['business' => $business, 'finance' => $finance, 'accounting' => $accounting, 'promotion' => $promotion, 'anomalies' => $anomalies] as $title => $data)
                        <div class="col-lg-6">
                            <details>
                                <summary class="fw-semibold" style="cursor: pointer;">{{ ucfirst($title) }}</summary>
                                <div class="mt-2">
                                    @include('admin.ai._partials.value-table', ['data' => $data])
                                    <pre class="bg-light p-3 rounded mt-2 small mb-0">{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            </details>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </details>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Decision trend line chart
    const trendCanvas = document.getElementById('aiDecisionTrendChart');
    if (trendCanvas && typeof Chart !== 'undefined') {
        const trendData = @json($decisionTrend);
        const labels = Object.keys(trendData).map(d => new Date(d).toLocaleDateString('en-US', { day: 'numeric', month: 'short' }));
        const values = Object.values(trendData);
        const ctx = trendCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 260);
        gradient.addColorStop(0, 'rgba(124, 58, 237, .28)');
        gradient.addColorStop(1, 'rgba(124, 58, 237, 0)');

        new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [{ data: values, borderColor: '#7c3aed', backgroundColor: gradient, borderWidth: 3, tension: 0.4, fill: true, pointRadius: 3, pointBackgroundColor: '#fff', pointBorderColor: '#7c3aed', pointBorderWidth: 2 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: 700, size: 11 } } },
                    y: { beginAtZero: true, ticks: { color: '#64748b', font: { weight: 700, size: 11 }, precision: 0 }, grid: { color: 'rgba(148,163,184,.18)' } },
                },
            },
        });
    }

    // Risk / outcome donut chart
    const riskCanvas = document.getElementById('aiRiskChart');
    if (riskCanvas && typeof Chart !== 'undefined') {
        const riskData = @json($riskDistribution);
        const riskColors = { low: '#22c55e', medium: '#f59e0b', high: '#ef4444', critical: '#7c1d1d' };
        const labels = Object.keys(riskData);
        const values = Object.values(riskData);

        if (labels.length) {
            new Chart(riskCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels.map(l => l.charAt(0).toUpperCase() + l.slice(1)),
                    datasets: [{ data: values, backgroundColor: labels.map(l => riskColors[l] || '#64748b'), borderWidth: 0 }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '68%',
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { weight: 700, size: 11 }, color: '#475569' } } },
                },
            });
        } else {
            riskCanvas.parentElement.innerHTML = '<div class="ai-empty">No decisions logged yet.</div>';
        }
    }
});
</script>
@endsection
