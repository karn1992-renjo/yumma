@extends('layouts.admin')

@section('title', 'Branch Reports')
@section('header', 'Branch Reports')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $decimals = App\Models\AppSetting::currencyDecimals();
    $dailyIncomeAsc = $dailyIncome->sortBy('date')->values();
    $maxPeakOrders = $peakHours->max('orders_count') ?: 1;
@endphp

@section('styles')
<style>
    .saas-dashboard { display: grid; gap: 26px; }
    .saas-grid { display: grid; gap: 18px; }
    .kpi-grid { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .reports-row { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: stretch; }

    .branch-intro {
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;
        padding: 18px 24px; border-radius: 24px; border: 1px solid rgba(226, 232, 240, .88);
        background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)), radial-gradient(circle at top right, rgba(249,115,22,.12), transparent 42%);
        box-shadow: 0 22px 55px rgba(15, 23, 42, .07);
    }
    .branch-intro-kicker {
        display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px;
        background: #fff7ed; color: #c2410c; font-size: 12px; font-weight: 900; margin-bottom: 8px;
    }
    .branch-intro h1 { font-size: 22px; font-weight: 950; letter-spacing: -.02em; color: #0f172a; margin: 0; }
    .branch-intro p { color: #64748b; font-size: 13px; font-weight: 600; margin: 4px 0 0; }

    .kpi-card, .dash-panel {
        border: 1px solid rgba(226, 232, 240, .88);
        background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)), radial-gradient(circle at top right, var(--card-glow, rgba(249,115,22,.12)), transparent 42%);
        box-shadow: 0 22px 55px rgba(15, 23, 42, .07);
    }
    .kpi-card { position: relative; min-height: 150px; padding: 20px; border-radius: 24px; overflow: hidden; }
    .kpi-card::after { content: ""; position: absolute; inset: auto -34px -56px auto; width: 120px; height: 120px; border-radius: 50%; background: var(--accent); opacity: .09; }
    .kpi-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; position: relative; z-index: 1; }
    .kpi-icon {
        width: 44px; height: 44px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center;
        color: var(--accent); background: color-mix(in srgb, var(--accent) 14%, white); box-shadow: inset 0 1px 0 rgba(255,255,255,.8); font-size: 17px;
    }
    .kpi-label { color: #64748b; font-weight: 800; font-size: 12px; margin-top: 16px; }
    .kpi-value { color: #0f172a; font-size: 24px; font-weight: 950; letter-spacing: -.03em; line-height: 1.05; margin-top: 4px; }
    .sparkline { width: 100%; height: 30px; margin-top: 10px; position: relative; z-index: 1; }

    .dash-panel { border-radius: 24px; overflow: hidden; min-width: 0; }
    .panel-head { padding: 20px 22px 14px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .panel-title { margin: 0; color: #0f172a; font-size: 17px; font-weight: 950; letter-spacing: -.02em; }
    .panel-subtitle { color: #64748b; font-size: 12px; margin-top: 4px; font-weight: 600; }
    .panel-link { color: #c2410c; text-decoration: none; font-size: 12px; font-weight: 900; }

    .chart-shell { height: 280px; padding: 8px 22px 18px; }
    .chart-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; padding: 0 22px 20px; }
    .summary-tile { border: 1px solid rgba(226,232,240,.78); border-radius: 16px; background: rgba(248,250,252,.82); padding: 14px; }
    .summary-label { color: #64748b; font-size: 11px; font-weight: 800; }
    .summary-value { color: #0f172a; font-weight: 950; margin-top: 4px; }

    .rank-list, .activity-list { display: grid; gap: 10px; padding: 0 14px 16px; }
    .rank-card, .activity-row {
        border: 1px solid rgba(226,232,240,.78); background: rgba(255,255,255,.84); border-radius: 18px; padding: 13px;
        display: flex; align-items: center; gap: 12px; min-width: 0;
    }
    .rank-number {
        width: 24px; height: 24px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center;
        background: #fff7ed; color: #ea580c; font-size: 12px; font-weight: 950; flex-shrink: 0;
    }
    .rank-logo, .activity-icon {
        width: 42px; height: 42px; border-radius: 14px; color: #fff; background: linear-gradient(135deg, #111827, #f97316);
        overflow: hidden; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; font-weight: 950;
    }
    .rank-logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .rank-card .min-w-0, .activity-row .min-w-0 { min-width: 0; overflow: hidden; }
    .fw-black { font-weight: 900; }

    .peak-bar-track { flex: 1 1 auto; min-width: 0; height: 8px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
    .peak-bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #f97316, #ef4444); }

    .order-table { margin: 0; }
    .order-table thead th { border: 0; color: #64748b; background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 0; padding: 14px 18px; }
    .order-table tbody td { padding: 14px 18px; vertical-align: middle; border-color: #f1f5f9; }

    @media (max-width: 1200px) {
        .reports-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 900px) {
        .kpi-grid, .chart-summary { grid-template-columns: 1fr; }
        .branch-intro { padding: 16px; }
        .chart-shell { height: 240px; }
    }
</style>
@endsection

@section('content')
<div class="saas-dashboard">
    <div class="branch-intro">
        <div>
            <div class="branch-intro-kicker"><i class="fas fa-chart-pie"></i> Performance</div>
            <h1>{{ $branch->name }} Reports</h1>
            <p>Branch performance, revenue, credited commission, refunds, restaurants and peak hours.</p>
        </div>
        @if($capabilities['reports_export'] ?? false)
            <a href="{{ route('branch.reports.export') }}" class="btn btn-outline-dark rounded-pill fw-bold px-4">
                <i class="fas fa-file-excel me-2"></i> Export Excel
            </a>
        @endif
    </div>

    <section class="saas-grid kpi-grid">
        <div class="kpi-card" style="--accent:#f97316;--card-glow:rgba(249,115,22,.16);">
            <div class="kpi-top"><div class="kpi-icon"><i class="fas fa-bag-shopping"></i></div></div>
            <div class="kpi-label">Orders</div>
            <div class="kpi-value">{{ number_format($summary['orders']) }}</div>
            <canvas class="sparkline" data-color="#f97316" data-values='@json($dailyIncomeAsc->pluck("orders")->values())'></canvas>
        </div>
        <div class="kpi-card" style="--accent:#22c55e;--card-glow:rgba(34,197,94,.14);">
            <div class="kpi-top"><div class="kpi-icon"><i class="fas fa-circle-check"></i></div></div>
            <div class="kpi-label">Completed</div>
            <div class="kpi-value">{{ number_format($summary['completed_orders']) }}</div>
        </div>
        <div class="kpi-card" style="--accent:#ef4444;--card-glow:rgba(239,68,68,.14);">
            <div class="kpi-top"><div class="kpi-icon"><i class="fas fa-ban"></i></div></div>
            <div class="kpi-label">Cancelled</div>
            <div class="kpi-value">{{ number_format($summary['cancelled_orders']) }}</div>
        </div>
        <div class="kpi-card" style="--accent:#64748b;--card-glow:rgba(100,116,139,.14);">
            <div class="kpi-top"><div class="kpi-icon"><i class="fas fa-rotate-left"></i></div></div>
            <div class="kpi-label">Refunded</div>
            <div class="kpi-value">{{ number_format($summary['refunded_orders']) }}</div>
        </div>
        <div class="kpi-card" style="--accent:#3b82f6;--card-glow:rgba(59,130,246,.15);">
            <div class="kpi-top"><div class="kpi-icon"><i class="fas fa-sack-dollar"></i></div></div>
            <div class="kpi-label">Revenue</div>
            <div class="kpi-value">{{ $currencySymbol }}{{ number_format($summary['revenue'], $decimals) }}</div>
            <canvas class="sparkline" data-color="#3b82f6" data-values='@json($dailyIncomeAsc->pluck("revenue")->values())'></canvas>
        </div>
        <div class="kpi-card" style="--accent:#8b5cf6;--card-glow:rgba(139,92,246,.16);">
            <div class="kpi-top"><div class="kpi-icon"><i class="fas fa-wallet"></i></div></div>
            <div class="kpi-label">Credited Branch Earnings</div>
            <div class="kpi-value">{{ $currencySymbol }}{{ number_format($summary['branch_commission'], $decimals) }}</div>
            <canvas class="sparkline" data-color="#8b5cf6" data-values='@json($dailyIncomeAsc->pluck("commission")->values())'></canvas>
        </div>
        <div class="kpi-card" style="--accent:#14b8a6;--card-glow:rgba(20,184,166,.14);">
            <div class="kpi-top"><div class="kpi-icon"><i class="fas fa-coins"></i></div></div>
            <div class="kpi-label">Wallet</div>
            <div class="kpi-value">{{ $currencySymbol }}{{ number_format($summary['wallet_balance'], $decimals) }}</div>
        </div>
    </section>

    <section class="saas-grid reports-row">
        <div class="dash-panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Day-wise Income</h3>
                    <div class="panel-subtitle">Delivered revenue and credited commission, last 30 days.</div>
                </div>
            </div>
            <div class="chart-shell"><canvas id="dailyIncomeChart"></canvas></div>
            <div class="chart-summary">
                <div class="summary-tile"><div class="summary-label">Revenue</div><div class="summary-value">{{ $currencySymbol }}{{ number_format($dailyIncomeAsc->sum('revenue'), $decimals) }}</div></div>
                <div class="summary-tile"><div class="summary-label">Commission</div><div class="summary-value">{{ $currencySymbol }}{{ number_format($dailyIncomeAsc->sum('commission'), $decimals) }}</div></div>
                <div class="summary-tile"><div class="summary-label">Orders</div><div class="summary-value">{{ number_format($dailyIncomeAsc->sum('orders')) }}</div></div>
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Month-wise Income</h3>
                    <div class="panel-subtitle">Delivered revenue and credited commission, last 12 months.</div>
                </div>
            </div>
            <div class="chart-shell"><canvas id="monthlyIncomeChart"></canvas></div>
            <div class="chart-summary">
                <div class="summary-tile"><div class="summary-label">Revenue</div><div class="summary-value">{{ $currencySymbol }}{{ number_format($monthlyIncome->sum('revenue'), $decimals) }}</div></div>
                <div class="summary-tile"><div class="summary-label">Commission</div><div class="summary-value">{{ $currencySymbol }}{{ number_format($monthlyIncome->sum('commission'), $decimals) }}</div></div>
                <div class="summary-tile"><div class="summary-label">Orders</div><div class="summary-value">{{ number_format($monthlyIncome->sum('orders')) }}</div></div>
            </div>
        </div>
    </section>

    <section class="saas-grid reports-row">
        <div class="dash-panel">
            <div class="panel-head"><h3 class="panel-title">Top Restaurants</h3></div>
            <div class="rank-list">
                @forelse($topRestaurants as $index => $restaurant)
                    <div class="rank-card">
                        <span class="rank-number">{{ $index + 1 }}</span>
                        <div class="rank-logo">
                            @if(!empty($restaurant->logo_image))
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($restaurant->logo_image) }}" alt="{{ $restaurant->name }} logo">
                            @else
                                {{ strtoupper(substr($restaurant->name, 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-fill">
                            <div class="fw-bold text-dark text-truncate">{{ $restaurant->name }}</div>
                            <div class="small text-muted">{{ number_format($restaurant->orders_count) }} orders</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-black text-dark">{{ $currencySymbol }}{{ number_format($restaurant->revenue ?? 0, $decimals) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No restaurant orders yet.</div>
                @endforelse
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head"><h3 class="panel-title">Peak Hours</h3></div>
            <div class="activity-list">
                @forelse($peakHours as $hour)
                    <div class="activity-row">
                        <div class="activity-icon"><i class="fas fa-clock"></i></div>
                        <div class="min-w-0 flex-fill">
                            <div class="fw-bold text-dark">{{ str_pad($hour->hour, 2, '0', STR_PAD_LEFT) }}:00</div>
                            <div class="peak-bar-track"><div class="peak-bar-fill" style="width: {{ round(($hour->orders_count / $maxPeakOrders) * 100) }}%;"></div></div>
                        </div>
                        <div class="fw-black text-dark">{{ number_format($hour->orders_count) }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No peak-hour data yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="dash-panel">
        <div class="panel-head">
            <h3 class="panel-title">Day-wise Detail</h3>
            <a href="{{ route('branch.reports.income', ['type' => 'day']) }}" class="panel-link">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table order-table align-middle mb-0">
                <thead><tr><th>Date</th><th class="text-end">Orders</th><th class="text-end">Revenue</th><th class="text-end">Branch Commission</th></tr></thead>
                <tbody>
                @forelse($dailyIncome->take(5) as $row)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($row->date)->format('d M Y') }}</td>
                        <td class="text-end">{{ $row->orders }}</td>
                        <td class="text-end">{{ number_format($row->revenue, $decimals) }}</td>
                        <td class="text-end">{{ number_format($row->commission, $decimals) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-4">No delivered orders in the last 30 days.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="dash-panel">
        <div class="panel-head">
            <h3 class="panel-title">Month-wise Detail</h3>
            <a href="{{ route('branch.reports.income', ['type' => 'month']) }}" class="panel-link">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table order-table align-middle mb-0">
                <thead><tr><th>Month</th><th class="text-end">Orders</th><th class="text-end">Revenue</th><th class="text-end">Branch Commission</th></tr></thead>
                <tbody>
                @forelse($monthlyIncome->take(5) as $row)
                    <tr>
                        <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $row->month)->format('F Y') }}</td>
                        <td class="text-end">{{ $row->orders }}</td>
                        <td class="text-end">{{ number_format($row->revenue, $decimals) }}</td>
                        <td class="text-end">{{ number_format($row->commission, $decimals) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-4">No delivered orders in the last 12 months.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    function drawMiniSparkline(canvas) {
        const values = JSON.parse(canvas.dataset.values || '[]').map(Number).filter(Number.isFinite);
        const ctx = canvas.getContext('2d');
        const width = canvas.clientWidth || 180;
        const height = canvas.clientHeight || 30;
        const ratio = window.devicePixelRatio || 1;
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        ctx.scale(ratio, ratio);
        ctx.clearRect(0, 0, width, height);
        const data = values.length ? values : [0, 1, 0, 1, 2, 1, 3];
        const min = Math.min(...data);
        const max = Math.max(...data);
        const spread = Math.max(max - min, 1);
        const step = data.length > 1 ? width / (data.length - 1) : width;
        ctx.beginPath();
        data.forEach((value, index) => {
            const x = index * step;
            const y = height - ((value - min) / spread) * (height - 6) - 3;
            if (index === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        ctx.strokeStyle = canvas.dataset.color || '#f97316';
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
    }

    const branchReportsCurrencySymbol = @json($currencySymbol);

    function buildLineChart(canvasId, labels, data, color) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;
        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, color + '48');
        gradient.addColorStop(1, color + '00');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data,
                    borderColor: color,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.42,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: color,
                    pointBorderWidth: 3,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        cornerRadius: 14,
                        callbacks: {
                            label: (context) => branchReportsCurrencySymbol + Number(context.raw || 0).toLocaleString(undefined, {
                                minimumFractionDigits: {{ $decimals }},
                                maximumFractionDigits: {{ $decimals }},
                            })
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: 800 } } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: 'rgba(148, 163, 184, .18)' },
                        ticks: {
                            color: '#64748b',
                            font: { weight: 800 },
                            callback: (value) => branchReportsCurrencySymbol + Number(value).toLocaleString()
                        }
                    }
                }
            }
        });
    }

    function buildBarChart(canvasId, labels, data, color) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;
        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{ data, backgroundColor: color, borderRadius: 8, maxBarThickness: 34 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        cornerRadius: 14,
                        callbacks: {
                            label: (context) => branchReportsCurrencySymbol + Number(context.raw || 0).toLocaleString(undefined, {
                                minimumFractionDigits: {{ $decimals }},
                                maximumFractionDigits: {{ $decimals }},
                            })
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: 800 } } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: 'rgba(148, 163, 184, .18)' },
                        ticks: {
                            color: '#64748b',
                            font: { weight: 800 },
                            callback: (value) => branchReportsCurrencySymbol + Number(value).toLocaleString()
                        }
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.sparkline').forEach(drawMiniSparkline);

        buildLineChart(
            'dailyIncomeChart',
            @json($dailyIncomeAsc->map(fn ($r) => \Carbon\Carbon::parse($r->date)->format('d M'))->values()),
            @json($dailyIncomeAsc->pluck('revenue')->values()),
            '#f97316'
        );
        buildBarChart(
            'monthlyIncomeChart',
            @json($monthlyIncome->sortBy('month')->map(fn ($r) => \Carbon\Carbon::createFromFormat('Y-m', $r->month)->format('M Y'))->values()),
            @json($monthlyIncome->sortBy('month')->pluck('revenue')->values()),
            '#3b82f6'
        );
    });
    window.addEventListener('resize', () => document.querySelectorAll('.sparkline').forEach(drawMiniSparkline));
</script>
@endsection
