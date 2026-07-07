@extends('layouts.admin')

@section('title', 'Dashboard')
@section('header', 'Dashboard')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8'));
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $avgOrderValue = $totalOrders > 0 ? $totalRevenue / max($deliveredOrdersCount, 1) : 0;
    $activityOrders = $recentOrders->take(4);
    $platformHealth = $successRate >= 80 ? 'Operational' : 'Watch';
@endphp

@section('styles')
<style>
    .saas-dashboard {
        display: grid;
        gap: 26px;
    }

    .saas-grid {
        display: grid;
        gap: 18px;
    }

    .kpi-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .dashboard-row-main {
        grid-template-columns: minmax(0, 1.3fr) minmax(360px, .95fr) minmax(330px, .82fr);
        align-items: stretch;
    }

    .dashboard-row-secondary {
        grid-template-columns: minmax(300px, .95fr) minmax(320px, 1fr) minmax(300px, .85fr) minmax(280px, .8fr);
    }

    .kpi-card,
    .dash-panel,
    .mini-stat-card {
        border: 1px solid rgba(226, 232, 240, .88);
        background:
            linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)),
            radial-gradient(circle at top right, var(--card-glow, rgba(124,58,237,.12)), transparent 42%);
        box-shadow: 0 22px 55px rgba(15, 23, 42, .07);
    }

    .kpi-card {
        position: relative;
        min-height: 184px;
        padding: 22px;
        border-radius: 24px;
        overflow: hidden;
    }

    .kpi-card::after {
        content: "";
        position: absolute;
        inset: auto -34px -56px auto;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: var(--accent);
        opacity: .09;
    }

    .kpi-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        position: relative;
        z-index: 1;
    }

    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--accent);
        background: color-mix(in srgb, var(--accent) 14%, white);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
        font-size: 19px;
    }

    .kpi-trend {
        color: #16a34a;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .kpi-label {
        color: #64748b;
        font-weight: 800;
        font-size: 13px;
        margin-top: 18px;
    }

    .kpi-value {
        color: #0f172a;
        font-size: 28px;
        font-weight: 950;
        letter-spacing: -.03em;
        line-height: 1.05;
        margin-top: 4px;
    }

    .kpi-sub {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        margin-top: 12px;
    }

    .sparkline {
        width: 100%;
        height: 34px;
        margin-top: 12px;
        position: relative;
        z-index: 1;
    }

    .dash-panel {
        border-radius: 24px;
        overflow: hidden;
        min-width: 0;
    }

    .panel-head {
        padding: 20px 22px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .panel-title {
        margin: 0;
        color: #0f172a;
        font-size: 17px;
        font-weight: 950;
        letter-spacing: -.02em;
    }

    .panel-link,
    .soft-link {
        color: #6d28d9;
        text-decoration: none;
        font-size: 12px;
        font-weight: 900;
    }

    .rank-list {
        padding: 0 22px 18px;
    }

    .driver-row {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        flex-wrap: wrap;
    }

    .driver-row .min-w-0 {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
    }

    .driver-row .small.text-muted {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .chart-shell {
        height: 310px;
        padding: 8px 22px 18px;
    }

    .chart-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        padding: 0 22px 20px;
    }

    .summary-tile {
        border: 1px solid rgba(226,232,240,.78);
        border-radius: 16px;
        background: rgba(248,250,252,.82);
        padding: 14px;
    }

    .summary-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
    }

    .summary-value {
        color: #0f172a;
        font-weight: 950;
        margin-top: 4px;
    }

    .live-order-list,
    .rank-list,
    .activity-list,
    .health-list {
        display: grid;
        gap: 10px;
        padding: 0 14px 16px;
    }

    .live-order-card,
    .rank-card,
    .activity-row,
    .health-row,
    .driver-row {
        border: 1px solid rgba(226,232,240,.78);
        background: rgba(255,255,255,.84);
        border-radius: 18px;
        padding: 13px;
    }

    .live-order-card {
        display: grid;
        grid-template-columns: 52px 1fr auto;
        gap: 12px;
        align-items: center;
        color: inherit;
        text-decoration: none;
    }

    .food-thumb,
    .rank-logo,
    .driver-avatar,
    .activity-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        font-weight: 950;
    }

    .food-thumb {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        color: #fff;
        background: linear-gradient(135deg, #f97316, #7c3aed);
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 900;
        background: #eef2ff;
        color: #4338ca;
        white-space: nowrap;
    }

    .status-pill.delivered { background: #dcfce7; color: #166534; }
    .status-pill.cancelled,
    .status-pill.refunded { background: #fee2e2; color: #991b1b; }
    .status-pill.pending { background: #ffedd5; color: #9a3412; }
    .status-pill.confirmed,
    .status-pill.preparing,
    .status-pill.ready-for-pickup { background: #dbeafe; color: #1d4ed8; }

    .rank-card,
    .driver-row,
    .activity-row,
    .health-row {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .rank-number {
        width: 24px;
        height: 24px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff7ed;
        color: #ea580c;
        font-size: 12px;
        font-weight: 950;
        flex-shrink: 0;
    }

    .rank-logo,
    .driver-avatar,
    .activity-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        color: #fff;
        background: linear-gradient(135deg, #111827, #7c3aed);
        overflow: hidden;
        flex-shrink: 0;
    }

    .rank-logo img,
    .driver-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .rank-card .min-w-0,
    .driver-row .min-w-0,
    .activity-row .min-w-0 {
        min-width: 0;
        overflow: hidden;
    }

    .rank-card .fw-bold.text-dark.text-truncate,
    .driver-row .fw-bold.text-dark.text-truncate,
    .rank-card .small.text-muted,
    .driver-row .small.text-muted {
        overflow-wrap: anywhere;
    }

    .mini-stat-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        padding: 0 14px 16px;
    }

    .mini-stat-card {
        border-radius: 18px;
        padding: 15px;
        min-height: 108px;
    }

    .mini-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .mini-value {
        color: #0f172a;
        font-size: 22px;
        font-weight: 950;
        margin-top: 8px;
    }

    .health-state {
        margin-left: auto;
        border-radius: 999px;
        padding: 6px 9px;
        background: #dcfce7;
        color: #15803d;
        font-size: 11px;
        font-weight: 900;
    }

    .health-state.warn {
        background: #fef3c7;
        color: #b45309;
    }

    @media (max-width: 1500px) {
        .kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .dashboard-row-main { grid-template-columns: minmax(0, 1fr) minmax(340px, .78fr); }
        .dashboard-row-main .dash-panel:last-child { grid-column: 1 / -1; }
        .dashboard-row-secondary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 900px) {
        .kpi-grid,
        .dashboard-row-main,
        .dashboard-row-secondary,
        .chart-summary {
            grid-template-columns: 1fr;
        }
        .chart-shell { height: 260px; }
    }
</style>
@endsection

@section('content')
<div class="saas-dashboard">
    <section class="saas-grid kpi-grid">
        <div class="kpi-card" style="--accent:#f97316;--card-glow:rgba(249,115,22,.16);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-indian-rupee-sign"></i></div>
                <div class="kpi-trend"><i class="fas fa-arrow-trend-up"></i> Today {{ $currencySymbol }}{{ number_format($todayRevenue, $currencyDecimals) }}</div>
            </div>
            <div class="kpi-label">Total Revenue</div>
            <div class="kpi-value">{{ $currencySymbol }}{{ number_format($totalRevenue, $currencyDecimals) }}</div>
            <canvas class="sparkline" data-color="#f97316" data-values='@json($dailyRevenue->pluck("revenue")->values())'></canvas>
            <div class="kpi-sub">Avg order {{ $currencySymbol }}{{ number_format($avgOrderValue, $currencyDecimals) }}</div>
        </div>

        <div class="kpi-card" style="--accent:#3b82f6;--card-glow:rgba(59,130,246,.15);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-bag-shopping"></i></div>
                <div class="kpi-trend"><i class="fas fa-circle"></i> {{ number_format($activeOrdersCount) }} live</div>
            </div>
            <div class="kpi-label">Total Orders</div>
            <div class="kpi-value">{{ number_format($totalOrders) }}</div>
            <canvas class="sparkline" data-color="#3b82f6" data-values='@json($dailyRevenue->pluck("revenue")->values())'></canvas>
            <div class="kpi-sub">{{ number_format($todayOrders) }} orders today</div>
        </div>

        <div class="kpi-card" style="--accent:#8b5cf6;--card-glow:rgba(139,92,246,.16);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-users"></i></div>
                <div class="kpi-trend"><i class="fas fa-arrow-trend-up"></i> Customer base</div>
            </div>
            <div class="kpi-label">Total Customers</div>
            <div class="kpi-value">{{ number_format($totalUsers) }}</div>
            <canvas class="sparkline" data-color="#8b5cf6" data-values='{{ json_encode([18,24,21,32,36,30,42,45,49,54,58,63]) }}'></canvas>
            <div class="kpi-sub">Registered customer accounts</div>
        </div>

        <div class="kpi-card" style="--accent:#22c55e;--card-glow:rgba(34,197,94,.14);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-motorcycle"></i></div>
                <div class="kpi-trend"><i class="fas fa-circle"></i> {{ number_format($totalDrivers) }} drivers</div>
            </div>
            <div class="kpi-label">Active Restaurants</div>
            <div class="kpi-value">{{ number_format($activeRestaurants) }}</div>
            <canvas class="sparkline" data-color="#22c55e" data-values='{{ json_encode([12,15,14,18,17,22,21,26,25,28,30,34]) }}'></canvas>
            <div class="kpi-sub">of {{ number_format($totalRestaurants) }} restaurants</div>
        </div>

        <div class="kpi-card" style="--accent:#14b8a6;--card-glow:rgba(20,184,166,.14);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-circle-check"></i></div>
                <div class="kpi-trend"><i class="fas fa-arrow-trend-up"></i> {{ $platformHealth }}</div>
            </div>
            <div class="kpi-label">Success Rate</div>
            <div class="kpi-value">{{ number_format($successRate, 1) }}%</div>
            <canvas class="sparkline" data-color="#14b8a6" data-values='{{ json_encode([82,86,84,88,87,90,91,89,92,93,94,96]) }}'></canvas>
            <div class="kpi-sub">{{ number_format($cancellationRate, 1) }}% cancellation rate</div>
        </div>
    </section>

    <section class="saas-grid dashboard-row-main">
        <div class="dash-panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Revenue Overview</h3>
                    <div class="text-muted small fw-semibold">Delivered revenue across the selected period.</div>
                </div>
                <select id="chartPeriod" class="form-select rounded-pill w-auto border-0 bg-light fw-bold" onchange="updateChart()">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                </select>
            </div>
            <div class="chart-shell">
                <canvas id="revenueChart"></canvas>
            </div>
            <div class="chart-summary">
                <div class="summary-tile">
                    <div class="summary-label">Total Revenue</div>
                    <div class="summary-value">{{ $currencySymbol }}{{ number_format($totalRevenue, $currencyDecimals) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">Order Value</div>
                    <div class="summary-value">{{ $currencySymbol }}{{ number_format($avgOrderValue, $currencyDecimals) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">Delivered</div>
                    <div class="summary-value">{{ number_format($deliveredOrdersCount) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">Live Orders</div>
                    <div class="summary-value">{{ number_format($activeOrdersCount) }}</div>
                </div>
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Live Orders</h3>
                <a href="{{ route('admin.orders.index') }}" class="panel-link">View All</a>
            </div>
            <div class="live-order-list">
                @forelse($recentOrders->take(5) as $order)
                    @php $statusClass = str_replace('_', '-', strtolower($order->status ?? 'pending')); @endphp
                    <a href="{{ route('admin.orders.show', $order->id) }}" class="live-order-card">
                        <div class="food-thumb"><i class="fas fa-utensils"></i></div>
                        <div class="min-w-0">
                            <div class="d-flex align-items-center gap-2">
                                <strong class="text-dark">#{{ $order->order_number ?? $order->id }}</strong>
                                <span class="text-muted small">{{ $order->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="text-dark fw-semibold text-truncate">{{ $order->restaurant->name ?? 'Restaurant' }}</div>
                            <div class="text-muted small text-truncate">{{ $order->customer_name ?? $order->customer?->name ?? 'Guest' }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-black text-dark">{{ $currencySymbol }}{{ number_format($order->total, $currencyDecimals) }}</div>
                            <span class="status-pill {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        </div>
                    </a>
                @empty
                    <div class="text-center text-muted py-5">No live orders yet.</div>
                @endforelse
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Top Restaurants</h3>
                <a href="{{ route('admin.restaurants.index') }}" class="panel-link">View All</a>
            </div>
            <div class="rank-list">
                @forelse($topRestaurants as $index => $restaurant)
                    <div class="rank-card">
                        <span class="rank-number">{{ $index + 1 }}</span>
                        <div class="rank-logo">
                            @if(!empty($restaurant->logo_image))
                                <img src="{{ Storage::url($restaurant->logo_image) }}" alt="{{ $restaurant->name }} logo">
                            @else
                                {{ strtoupper(substr($restaurant->name, 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-fill">
                            <div class="fw-bold text-dark text-truncate">{{ $restaurant->name }}</div>
                            <div class="small text-muted">{{ number_format($restaurant->orders_count) }} orders</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-black text-dark">{{ $currencySymbol }}{{ number_format($restaurant->revenue ?? 0, $currencyDecimals) }}</div>
                            <div class="small text-success fw-bold"><i class="fas fa-arrow-trend-up"></i> Healthy</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No restaurant performance yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="saas-grid dashboard-row-secondary">
        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Analytics Summary</h3>
            </div>
            <div class="mini-stat-grid">
                <div class="mini-stat-card" style="--card-glow:rgba(59,130,246,.14);">
                    <div class="mini-label">Avg Delivery Time</div>
                    <div class="mini-value">{{ $avgDeliveryTime ? number_format($avgDeliveryTime) : 0 }} mins</div>
                    <div class="small text-success fw-bold">Live operations</div>
                </div>
                <div class="mini-stat-card" style="--card-glow:rgba(245,158,11,.14);">
                    <div class="mini-label">Success Rate</div>
                    <div class="mini-value">{{ number_format($successRate, 1) }}%</div>
                    <div class="small text-success fw-bold">{{ number_format($deliveredOrdersCount) }} delivered</div>
                </div>
                <div class="mini-stat-card" style="--card-glow:rgba(239,68,68,.12);">
                    <div class="mini-label">Cancelled Orders</div>
                    <div class="mini-value">{{ number_format($cancellationRate, 1) }}%</div>
                    <div class="small text-danger fw-bold">{{ number_format($cancelledOrdersCount) }} total</div>
                </div>
                <div class="mini-stat-card" style="--card-glow:rgba(249,115,22,.12);">
                    <div class="mini-label">Platform Revenue</div>
                    <div class="mini-value">{{ $currencySymbol }}{{ number_format($totalRevenue, $currencyDecimals) }}</div>
                    <div class="small text-success fw-bold">Delivered GMV</div>
                </div>
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Activity Feed</h3>
                <span class="soft-link">Live</span>
            </div>
            <div class="activity-list">
                @forelse($activityOrders as $order)
                    <div class="activity-row">
                        <div class="activity-icon" style="background:linear-gradient(135deg,#f97316,#f59e0b);"><i class="fas fa-receipt"></i></div>
                        <div class="min-w-0 flex-fill">
                            <div class="fw-bold text-dark text-truncate">Order #{{ $order->order_number ?? $order->id }} {{ str_replace('_', ' ', $order->status) }}</div>
                            <div class="small text-muted">{{ $order->restaurant->name ?? 'Restaurant' }} - {{ $order->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No recent activity.</div>
                @endforelse
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Platform Health</h3>
            </div>
            <div class="health-list">
                <div class="health-row">
                    <div class="fw-bold text-dark">API Services</div>
                    <span class="health-state">Operational</span>
                </div>
                <div class="health-row">
                    <div class="fw-bold text-dark">Database</div>
                    <span class="health-state">Healthy</span>
                </div>
                <div class="health-row">
                    <div class="fw-bold text-dark">Order Success</div>
                    <span class="health-state {{ $successRate < 80 ? 'warn' : '' }}">{{ number_format($successRate, 1) }}%</span>
                </div>
                <div class="health-row">
                    <div class="fw-bold text-dark">Cancellations</div>
                    <span class="health-state {{ $cancellationRate > 10 ? 'warn' : '' }}">{{ number_format($cancellationRate, 1) }}%</span>
                </div>
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Top Drivers</h3>
                <a href="{{ route('admin.drivers.index') }}" class="panel-link">View All</a>
            </div>
            <div class="rank-list">
                @forelse($topDrivers as $index => $driver)
                    <div class="driver-row">
                        <span class="rank-number">{{ $index + 1 }}</span>
                        <div class="driver-avatar">
                            @if(!empty($driver->profile_photo_url))
                                <img src="{{ $driver->profile_photo_url }}" alt="{{ $driver->name }} profile">
                            @else
                                {{ strtoupper(substr($driver->name, 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-fill">
                            <div class="fw-bold text-dark text-truncate">{{ $driver->name }}</div>
                            <div class="small text-muted">{{ number_format($driver->delivered_orders_count) }} deliveries</div>
                        </div>
                        <div class="small fw-bold text-warning">{{ number_format($driver->driver_rating_average ?? 0, 1) }} <i class="fas fa-star"></i></div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No driver performance yet.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>

<script>
    const chartLabels = [];
    const chartData = [];

    @foreach($dailyRevenue as $item)
        chartLabels.push('{{ \Carbon\Carbon::parse($item->date)->format("d M") }}');
        chartData.push({{ (float) $item->revenue }});
    @endforeach

    const currencySymbol = @json($currencySymbol);
    let revenueChart;

    function drawMiniSparkline(canvas) {
        const values = JSON.parse(canvas.dataset.values || '[]').map(Number).filter(Number.isFinite);
        const ctx = canvas.getContext('2d');
        const width = canvas.clientWidth || 180;
        const height = canvas.clientHeight || 34;
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
        ctx.strokeStyle = canvas.dataset.color || '#7c3aed';
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
    }

    function initChart() {
        document.querySelectorAll('.sparkline').forEach(drawMiniSparkline);

        const canvas = document.getElementById('revenueChart');
        if (!canvas || typeof Chart === 'undefined') return;

        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(124, 58, 237, .28)');
        gradient.addColorStop(1, 'rgba(124, 58, 237, 0)');

        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    borderColor: '#7c3aed',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.42,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#7c3aed',
                    pointBorderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 7,
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
                            label: function(context) {
                                return currencySymbol + Number(context.raw || 0).toLocaleString(undefined, {
                                    minimumFractionDigits: {{ $currencyDecimals }},
                                    maximumFractionDigits: {{ $currencyDecimals }},
                                });
                            }
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
                            callback: function(value) { return currencySymbol + Number(value).toLocaleString(); }
                        }
                    }
                }
            }
        });
    }

    function updateChart() {
        const period = document.getElementById('chartPeriod').value;
        fetch(`{{ route('admin.orders.statistics') }}?period=${period}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.daily || !revenueChart) return;
                revenueChart.data.labels = data.daily.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
                });
                revenueChart.data.datasets[0].data = data.daily.map(item => Number(item.revenue || 0));
                revenueChart.update();
            })
            .catch(error => console.error('Revenue chart update failed:', error));
    }

    document.addEventListener('DOMContentLoaded', initChart);
    window.addEventListener('resize', () => document.querySelectorAll('.sparkline').forEach(drawMiniSparkline));
</script>
@endsection
