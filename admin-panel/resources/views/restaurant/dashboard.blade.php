{{-- resources/views/restaurant/dashboard.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Dashboard')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8'));
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    if (str_contains($currencySymbol, '{{') || str_contains($currencySymbol, 'currencySymbol')) {
        $currencySymbol = html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8');
    }
    $activeRestaurantCount = $selectedScope === 'all'
        ? $restaurants->where('is_open', true)->count()
        : (($restaurant?->is_open ?? false) ? 1 : 0);
    $restaurantLabel = $selectedScope === 'all'
        ? 'All Restaurants'
        : ($restaurant->name ?? 'Restaurant');
    $displayOrders = $activeOrders->isNotEmpty() ? $activeOrders : $recentOrders->take(5);
@endphp

@section('styles')
<style>
    .seller-command {
        display: grid;
        gap: 26px;
    }

    .seller-hero {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        padding: 28px;
        color: #fff;
        background:
            radial-gradient(circle at 12% 18%, rgba(255,255,255,.28), transparent 28%),
            radial-gradient(circle at 90% 18%, rgba(34,197,94,.22), transparent 24%),
            linear-gradient(135deg, #1e1b4b 0%, #6d28d9 48%, #f97316 100%);
        box-shadow: 0 28px 75px rgba(109, 40, 217, .22);
    }

    .seller-hero::after {
        content: "";
        position: absolute;
        right: -90px;
        bottom: -130px;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        background: rgba(255,255,255,.14);
    }

    .hero-content {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 24px;
        align-items: end;
    }

    .hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.22);
        background: rgba(255,255,255,.15);
        font-size: 12px;
        font-weight: 900;
        margin-bottom: 14px;
    }

    .seller-hero h1 {
        font-size: clamp(30px, 4vw, 50px);
        font-weight: 950;
        letter-spacing: -.04em;
        line-height: 1.05;
        margin: 0;
    }

    .hero-copy {
        color: rgba(255,255,255,.78);
        max-width: 760px;
        margin: 12px 0 0;
        font-weight: 600;
    }

    .hero-stats {
        display: grid;
        grid-template-columns: repeat(3, 150px);
        gap: 10px;
    }

    .hero-stat {
        border: 1px solid rgba(255,255,255,.2);
        background: rgba(255,255,255,.13);
        border-radius: 18px;
        padding: 14px;
    }

    .hero-stat-label {
        color: rgba(255,255,255,.72);
        font-size: 11px;
        font-weight: 800;
    }

    .hero-stat-value {
        margin-top: 6px;
        font-size: 20px;
        font-weight: 950;
    }

    .seller-grid {
        display: grid;
        gap: 18px;
    }

    .seller-kpis {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .seller-main {
        grid-template-columns: minmax(0, 1.3fr) minmax(360px, .92fr) minmax(320px, .82fr);
    }

    .seller-secondary {
        grid-template-columns: minmax(320px, 1fr) minmax(320px, 1fr) minmax(300px, .86fr);
    }

    .seller-kpi,
    .seller-panel,
    .seller-mini {
        border: 1px solid rgba(226,232,240,.88);
        background:
            linear-gradient(180deg, rgba(255,255,255,.97), rgba(255,255,255,.9)),
            radial-gradient(circle at top right, var(--glow, rgba(249,115,22,.12)), transparent 44%);
        box-shadow: 0 22px 55px rgba(15, 23, 42, .07);
    }

    .seller-kpi {
        position: relative;
        overflow: hidden;
        min-height: 172px;
        border-radius: 24px;
        padding: 21px;
    }

    .seller-kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--accent);
        background: color-mix(in srgb, var(--accent) 14%, white);
        font-size: 19px;
    }

    .seller-kpi-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }

    .seller-trend {
        color: #16a34a;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .seller-label {
        color: #64748b;
        font-size: 13px;
        font-weight: 800;
        margin-top: 17px;
    }

    .seller-value {
        color: #0f172a;
        font-size: 27px;
        font-weight: 950;
        letter-spacing: -.03em;
        margin-top: 4px;
    }

    .seller-sub {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        margin-top: 10px;
    }

    .sparkline {
        width: 100%;
        height: 32px;
        margin-top: 10px;
    }

    .seller-panel {
        border-radius: 24px;
        overflow: hidden;
        min-width: 0;
    }

    .panel-head {
        padding: 20px 22px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .panel-title {
        margin: 0;
        color: #0f172a;
        font-size: 17px;
        font-weight: 950;
        letter-spacing: -.02em;
    }

    .panel-link {
        color: #6d28d9;
        text-decoration: none;
        font-size: 12px;
        font-weight: 900;
    }

    .chart-shell {
        height: 310px;
        padding: 8px 22px 22px;
    }

    .order-list,
    .item-list,
    .split-list,
    .summary-list {
        display: grid;
        gap: 10px;
        padding: 0 14px 16px;
    }

    .seller-order,
    .seller-item,
    .split-row,
    .summary-row {
        border: 1px solid rgba(226,232,240,.78);
        background: rgba(255,255,255,.86);
        border-radius: 18px;
        padding: 13px;
    }

    .seller-order {
        display: grid;
        grid-template-columns: 48px 1fr auto;
        gap: 12px;
        align-items: center;
        color: inherit;
        text-decoration: none;
    }

    .avatar-tile,
    .item-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(135deg, #f97316, #7c3aed);
        font-weight: 950;
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

    .seller-item,
    .split-row,
    .summary-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .split-bar {
        height: 8px;
        border-radius: 999px;
        background: #ede9fe;
        overflow: hidden;
        margin-top: 8px;
    }

    .split-bar > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #7c3aed, #f97316);
    }

    .seller-mini-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        padding: 0 14px 16px;
    }

    .seller-mini {
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

    .health-pill {
        margin-left: auto;
        border-radius: 999px;
        padding: 6px 10px;
        background: #dcfce7;
        color: #15803d;
        font-size: 11px;
        font-weight: 900;
    }

    .health-pill.warn {
        background: #fef3c7;
        color: #b45309;
    }

    @media (max-width: 1500px) {
        .seller-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .seller-main { grid-template-columns: minmax(0, 1fr) minmax(340px, .86fr); }
        .seller-main .seller-panel:last-child { grid-column: 1 / -1; }
        .seller-secondary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 900px) {
        .hero-content,
        .seller-kpis,
        .seller-main,
        .seller-secondary {
            grid-template-columns: 1fr;
        }
        .hero-stats {
            grid-template-columns: 1fr;
        }
        .chart-shell { height: 260px; }
    }
</style>
@endsection

@section('content')
<div class="seller-command">
    <section class="seller-hero">
        <div class="hero-content">
            <div>
                <div class="hero-kicker">
                    <i class="fas fa-bolt"></i>
                    Live Seller Command Center
                </div>
                <h1>{{ $restaurantLabel }}</h1>
                <p class="hero-copy">
                    Track revenue, orders, payout health, popular items, and live kitchen flow from one focused operations view.
                </p>
                @if($selectedScope === 'all')
                    <div class="mt-3">
                        <span class="badge bg-light text-primary rounded-pill px-3 py-2">
                            <i class="fas fa-layer-group me-1"></i> Combined dashboard
                        </span>
                    </div>
                @endif
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-label">Today Revenue</div>
                    <div class="hero-stat-value">{{ $currencySymbol }}{{ number_format($todayRevenue, $currencyDecimals) }}</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-label">Active Orders</div>
                    <div class="hero-stat-value">{{ number_format($pendingOrders) }}</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-label">Open Stores</div>
                    <div class="hero-stat-value">{{ number_format($activeRestaurantCount) }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="seller-grid seller-kpis">
        <div class="seller-kpi" style="--accent:#f97316;--glow:rgba(249,115,22,.16);">
            <div class="seller-kpi-top">
                <div class="seller-kpi-icon"><i class="fas fa-indian-rupee-sign"></i></div>
                <div class="seller-trend">{{ $currencySymbol }}{{ number_format($todayRevenue, $currencyDecimals) }} today</div>
            </div>
            <div class="seller-label">Total Revenue</div>
            <div class="seller-value">{{ $currencySymbol }}{{ number_format($totalRevenue, $currencyDecimals) }}</div>
            <canvas class="sparkline" data-color="#f97316" data-values='@json($revenueTrend["revenue"])'></canvas>
            <div class="seller-sub">Delivered revenue</div>
        </div>

        <div class="seller-kpi" style="--accent:#3b82f6;--glow:rgba(59,130,246,.15);">
            <div class="seller-kpi-top">
                <div class="seller-kpi-icon"><i class="fas fa-bag-shopping"></i></div>
                <div class="seller-trend">{{ $todayOrders }} today</div>
            </div>
            <div class="seller-label">Total Orders</div>
            <div class="seller-value">{{ number_format($totalOrders) }}</div>
            <canvas class="sparkline" data-color="#3b82f6" data-values='@json($revenueTrend["orders"])'></canvas>
            <div class="seller-sub">{{ number_format($pendingOrders) }} active, {{ number_format($deliveredOrdersCount) }} delivered</div>
        </div>

        <div class="seller-kpi" style="--accent:#8b5cf6;--glow:rgba(139,92,246,.16);">
            <div class="seller-kpi-top">
                <div class="seller-kpi-icon"><i class="fas fa-users"></i></div>
                <div class="seller-trend">Customer base</div>
            </div>
            <div class="seller-label">Total Customers</div>
            <div class="seller-value">{{ number_format($totalCustomers) }}</div>
            <canvas class="sparkline" data-color="#8b5cf6" data-values='{{ json_encode([8,12,14,18,22,25,28,32,35,39,42,46]) }}'></canvas>
            <div class="seller-sub">Unique ordering customers</div>
        </div>

        <div class="seller-kpi" style="--accent:#f59e0b;--glow:rgba(245,158,11,.14);">
            <div class="seller-kpi-top">
                <div class="seller-kpi-icon"><i class="fas fa-star"></i></div>
                <div class="seller-trend">{{ number_format($successRate, 1) }}% success</div>
            </div>
            <div class="seller-label">Average Rating</div>
            <div class="seller-value">{{ number_format($avgRating, 1) }}</div>
            <canvas class="sparkline" data-color="#f59e0b" data-values='{{ json_encode([3.8,4.1,4.0,4.2,4.4,4.3,4.5,4.6,4.5,4.7]) }}'></canvas>
            <div class="seller-sub">Customer experience score</div>
        </div>

        <div class="seller-kpi" style="--accent:#22c55e;--glow:rgba(34,197,94,.14);">
            <div class="seller-kpi-top">
                <div class="seller-kpi-icon"><i class="fas fa-wallet"></i></div>
                <div class="seller-trend">{{ $payoutSummary['frequency'] }}</div>
            </div>
            <div class="seller-label">Pending Payouts</div>
            <div class="seller-value">{{ $currencySymbol }}{{ number_format($payoutSummary['pending_amount'], $currencyDecimals) }}</div>
            <canvas class="sparkline" data-color="#22c55e" data-values='{{ json_encode([12,14,16,13,18,22,20,24,26,30]) }}'></canvas>
            <div class="seller-sub">{{ $payoutSummary['pending_count'] }} queued</div>
        </div>
    </section>

    <section class="seller-grid seller-main">
        <div class="seller-panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Revenue Trend</h3>
                    <div class="text-muted small fw-semibold">Delivered orders and revenue across the last 14 days.</div>
                </div>
            </div>
            <div class="chart-shell">
                <canvas id="dashboardRevenueTrend"></canvas>
            </div>
        </div>

        <div class="seller-panel">
            <div class="panel-head">
                <h3 class="panel-title">Live Orders</h3>
                <a href="{{ route('restaurant.orders.index') }}" class="panel-link">View All</a>
            </div>
            <div class="order-list">
                @forelse($displayOrders as $order)
                    @php
                        $statusClass = str_replace('_', '-', strtolower($order->status ?? 'pending'));
                        $itemsCount = $order->order_items_count;
                        if (!$itemsCount && is_array($order->items)) {
                            $itemsCount = collect($order->items)->sum(fn ($item) => (int) ($item['quantity'] ?? $item['qty'] ?? 1));
                        }
                    @endphp
                    <a href="{{ route('restaurant.orders.show', $order->id) }}" class="seller-order">
                        <div class="avatar-tile">{{ strtoupper(substr($order->customer->name ?? 'G', 0, 1)) }}</div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark">#{{ $order->order_number ?? $order->id }}</div>
                            <div class="text-muted small text-truncate">{{ $order->customer->name ?? 'Guest' }} - {{ $itemsCount }} items</div>
                            <div class="text-muted small">{{ $order->created_at->diffForHumans() }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($order->total, $currencyDecimals) }}</div>
                            <span class="status-pill {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        </div>
                    </a>
                @empty
                    <div class="text-center text-muted py-5">No orders yet.</div>
                @endforelse
            </div>
        </div>

        <div class="seller-panel">
            <div class="panel-head">
                <h3 class="panel-title">Popular Items</h3>
                @if(auth()->user()->hasRestaurantPermission('manage_menu'))
                    <a href="{{ route('restaurant.menu.index') }}" class="panel-link">Manage</a>
                @endif
            </div>
            <div class="item-list">
                @forelse($popularItems as $item)
                    <div class="seller-item">
                        <div class="item-icon"><i class="fas fa-utensils"></i></div>
                        <div class="min-w-0 flex-fill">
                            <div class="fw-bold text-dark text-truncate">{{ $item->name }}</div>
                            <div class="small text-muted">{{ number_format($item->total_orders ?? 0) }} orders</div>
                        </div>
                        <div class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($item->price, $currencyDecimals) }}</div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted">No popular items yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="seller-grid seller-secondary">
        <div class="seller-panel">
            <div class="panel-head">
                <h3 class="panel-title">Restaurant Revenue Split</h3>
            </div>
            <div class="split-list">
                @forelse($restaurantBreakdown as $row)
                    <div class="split-row">
                        <div class="avatar-tile">{{ strtoupper(substr($row['name'], 0, 1)) }}</div>
                        <div class="min-w-0 flex-fill">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="fw-bold text-dark text-truncate">{{ $row['name'] }}</div>
                                <div class="fw-bold text-dark">{{ $currencySymbol }}{{ number_format($row['revenue'], $currencyDecimals) }}</div>
                            </div>
                            <div class="small text-muted">{{ $row['city'] ?: 'Location not set' }} - {{ number_format($row['orders']) }} delivered</div>
                            <div class="split-bar"><span style="width: {{ min(100, max(0, $row['share'])) }}%;"></span></div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No delivered revenue yet.</div>
                @endforelse
            </div>
        </div>

        <div class="seller-panel">
            <div class="panel-head">
                <h3 class="panel-title">Operations Summary</h3>
            </div>
            <div class="seller-mini-grid">
                <div class="seller-mini" style="--glow:rgba(59,130,246,.14);">
                    <div class="mini-label">Best Order Time</div>
                    <div class="mini-value">{{ $bestOrderTime['label'] }}</div>
                    <div class="small text-muted">{{ $bestOrderTime['orders'] }} orders</div>
                </div>
                <div class="seller-mini" style="--glow:rgba(34,197,94,.14);">
                    <div class="mini-label">Success Rate</div>
                    <div class="mini-value">{{ number_format($successRate, 1) }}%</div>
                    <div class="small text-success fw-bold">{{ number_format($deliveredOrdersCount) }} delivered</div>
                </div>
                <div class="seller-mini" style="--glow:rgba(239,68,68,.12);">
                    <div class="mini-label">Cancellation</div>
                    <div class="mini-value">{{ number_format($cancellationRate, 1) }}%</div>
                    <div class="small text-danger fw-bold">{{ number_format($cancelledOrders) }} cancelled</div>
                </div>
                <div class="seller-mini" style="--glow:rgba(249,115,22,.12);">
                    <div class="mini-label">Avg Delivery</div>
                    <div class="mini-value">{{ $avgDeliveryTime ? number_format($avgDeliveryTime) : 0 }} mins</div>
                    <div class="small text-muted">Delivered orders</div>
                </div>
            </div>
        </div>

        <div class="seller-panel">
            <div class="panel-head">
                <h3 class="panel-title">Payout & Store Health</h3>
            </div>
            <div class="summary-list">
                <div class="summary-row">
                    <div class="fw-bold text-dark">Restaurant Status</div>
                    <span class="health-pill {{ $activeRestaurantCount > 0 ? '' : 'warn' }}">{{ $activeRestaurantCount > 0 ? 'Online' : 'Offline' }}</span>
                </div>
                <div class="summary-row">
                    <div class="fw-bold text-dark">Next Payout</div>
                    <span class="health-pill">{{ $payoutSummary['next_date']->format('d M Y') }}</span>
                </div>
                <div class="summary-row">
                    <div class="fw-bold text-dark">Last Payout</div>
                    <span class="health-pill {{ $payoutSummary['last'] ? '' : 'warn' }}">
                        @if($payoutSummary['last'])
                            {{ ucfirst($payoutSummary['last']->status) }}
                        @else
                            None
                        @endif
                    </span>
                </div>
                <div class="summary-row">
                    <div class="fw-bold text-dark">Pending Queue</div>
                    <span class="health-pill {{ $payoutSummary['pending_count'] > 0 ? 'warn' : '' }}">{{ $payoutSummary['pending_count'] }} payouts</span>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function drawMiniSparkline(canvas) {
        const values = JSON.parse(canvas.dataset.values || '[]').map(Number).filter(Number.isFinite);
        const ctx = canvas.getContext('2d');
        const width = canvas.clientWidth || 180;
        const height = canvas.clientHeight || 32;
        const ratio = window.devicePixelRatio || 1;
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        ctx.scale(ratio, ratio);
        ctx.clearRect(0, 0, width, height);
        const data = values.length ? values : [0, 1, 0, 2, 1, 3];
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

    document.querySelectorAll('.sparkline').forEach(drawMiniSparkline);
    window.addEventListener('resize', () => document.querySelectorAll('.sparkline').forEach(drawMiniSparkline));

    const trendCanvas = document.getElementById('dashboardRevenueTrend');
    if (!trendCanvas || typeof Chart === 'undefined') {
        return;
    }

    const trend = @json($revenueTrend);
    const revenueGradient = trendCanvas.getContext('2d').createLinearGradient(0, 0, 0, 300);
    revenueGradient.addColorStop(0, 'rgba(124, 58, 237, .26)');
    revenueGradient.addColorStop(1, 'rgba(124, 58, 237, 0)');

    new Chart(trendCanvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: trend.labels,
            datasets: [{
                label: 'Revenue',
                data: trend.revenue,
                borderColor: '#7c3aed',
                backgroundColor: revenueGradient,
                fill: true,
                tension: 0.42,
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#7c3aed',
                pointBorderWidth: 3,
                pointRadius: 4,
                yAxisID: 'y'
            }, {
                label: 'Orders',
                data: trend.orders,
                borderColor: '#f97316',
                backgroundColor: 'rgba(249, 115, 22, 0.08)',
                tension: 0.42,
                borderWidth: 3,
                pointRadius: 3,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, boxWidth: 8, font: { weight: 800 } }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 12,
                    cornerRadius: 14,
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label === 'Revenue') {
                                return 'Revenue: ' + window.formatCurrency(context.parsed.y);
                            }
                            return 'Orders: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: 800 } } },
                y: {
                    beginAtZero: true,
                    position: 'left',
                    border: { display: false },
                    grid: { color: 'rgba(148, 163, 184, .18)' },
                    ticks: {
                        color: '#64748b',
                        font: { weight: 800 },
                        callback: function(value) { return window.formatCurrency(value); }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    border: { display: false },
                    grid: { drawOnChartArea: false },
                    ticks: { precision: 0, color: '#64748b', font: { weight: 800 } }
                }
            }
        }
    });
});
</script>
@endsection
