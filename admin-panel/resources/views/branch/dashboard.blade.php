@extends('layouts.admin')

@section('title', 'Branch Dashboard')
@section('header', 'Branch Dashboard')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $decimals = App\Models\AppSetting::currencyDecimals();
    $avgOrderValue = $stats['orders'] > 0 ? $stats['revenue'] / max($stats['completed'], 1) : 0;
    $statusClass = fn ($status) => str_replace('_', '-', strtolower($status ?? 'pending'));
    $branchHealth = $stats['success_rate'] >= 80 ? 'Operational' : 'Watch';
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

    .branch-intro {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        padding: 18px 24px;
        border-radius: 24px;
        border: 1px solid rgba(226, 232, 240, .88);
        background:
            linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)),
            radial-gradient(circle at top right, rgba(249,115,22,.12), transparent 42%);
        box-shadow: 0 22px 55px rgba(15, 23, 42, .07);
    }

    .branch-intro-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        background: #fff7ed;
        color: #c2410c;
        font-size: 12px;
        font-weight: 900;
        margin-bottom: 8px;
    }

    .branch-intro h1 {
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -.02em;
        color: #0f172a;
        margin: 0;
    }

    .branch-intro p {
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
        margin: 4px 0 0;
    }

    .kpi-card,
    .dash-panel,
    .mini-stat-card {
        border: 1px solid rgba(226, 232, 240, .88);
        background:
            linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)),
            radial-gradient(circle at top right, var(--card-glow, rgba(249,115,22,.12)), transparent 42%);
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

    .panel-subtitle {
        color: #64748b;
        font-size: 12px;
        margin-top: 4px;
        font-weight: 600;
    }

    .panel-link,
    .soft-link {
        color: #c2410c;
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
    .activity-list {
        display: grid;
        gap: 10px;
        padding: 0 14px 16px;
    }

    .live-order-card,
    .rank-card,
    .activity-row,
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
        background: linear-gradient(135deg, #f97316, #ef4444);
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

    .status-pill.delivered,
    .status-pill.paid,
    .status-pill.approved { background: #dcfce7; color: #166534; }
    .status-pill.cancelled,
    .status-pill.rejected,
    .status-pill.refunded { background: #fee2e2; color: #991b1b; }
    .status-pill.pending { background: #ffedd5; color: #9a3412; }
    .status-pill.confirmed,
    .status-pill.generated,
    .status-pill.preparing,
    .status-pill.requested,
    .status-pill.ready-for-pickup { background: #dbeafe; color: #1d4ed8; }

    .rank-card,
    .driver-row,
    .activity-row {
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
        background: linear-gradient(135deg, #111827, #f97316);
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

    .fw-black { font-weight: 900; }

    .order-table {
        margin: 0;
    }

    .order-table thead th {
        border: 0;
        color: #64748b;
        background: #f8fafc;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0;
        padding: 14px 18px;
    }

    .order-table tbody td {
        padding: 16px 18px;
        vertical-align: middle;
        border-color: #f1f5f9;
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
        .branch-intro { padding: 16px; }
        .chart-shell { height: 260px; }
    }
</style>
@endsection

@section('content')
<div class="saas-dashboard">
    <div class="branch-intro">
        <div>
            <div class="branch-intro-kicker"><i class="fas fa-code-branch"></i> {{ $branch->code }} · {{ ucfirst($branch->status) }}</div>
            <h1>{{ $branch->name }}</h1>
            <p>Restaurants, drivers, wallet, and live orders scoped to this branch only.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($capabilities['orders_view'] ?? false)
                <a href="{{ route('branch.orders.live') }}" class="btn btn-warning rounded-pill fw-bold px-4">
                    <i class="fas fa-bolt me-1"></i> Live Orders
                </a>
            @endif
            @if($capabilities['reports_view'] ?? false)
                <a href="{{ route('branch.reports') }}" class="btn btn-outline-dark rounded-pill fw-bold px-4">
                    Reports
                </a>
            @endif
        </div>
    </div>

    <section class="saas-grid kpi-grid">
        <div class="kpi-card" style="--accent:#f97316;--card-glow:rgba(249,115,22,.16);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-sack-dollar"></i></div>
                <div class="kpi-trend"><i class="fas fa-arrow-trend-up"></i> Today {{ $currencySymbol }}{{ number_format($stats['today_revenue'], $decimals) }}</div>
            </div>
            <div class="kpi-label">Branch Revenue</div>
            <div class="kpi-value">{{ $currencySymbol }}{{ number_format($stats['revenue'], $decimals) }}</div>
            <canvas class="sparkline" data-color="#f97316" data-values='@json($dailyRevenue->pluck("revenue")->values())'></canvas>
            <div class="kpi-sub">Avg order {{ $currencySymbol }}{{ number_format($avgOrderValue, $decimals) }}</div>
        </div>

        <div class="kpi-card" style="--accent:#3b82f6;--card-glow:rgba(59,130,246,.15);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-bag-shopping"></i></div>
                <div class="kpi-trend"><i class="fas fa-circle"></i> {{ number_format($stats['active']) }} live</div>
            </div>
            <div class="kpi-label">Branch Orders</div>
            <div class="kpi-value">{{ number_format($stats['orders']) }}</div>
            <canvas class="sparkline" data-color="#3b82f6" data-values='@json($dailyOrderCount->pluck("count")->values())'></canvas>
            <div class="kpi-sub">{{ number_format($stats['today_orders']) }} orders today</div>
        </div>

        <div class="kpi-card" style="--accent:#8b5cf6;--card-glow:rgba(139,92,246,.16);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
                <div class="kpi-trend"><i class="fas fa-coins"></i> Commission</div>
            </div>
            <div class="kpi-label">Wallet Balance</div>
            <div class="kpi-value">{{ $currencySymbol }}{{ number_format($stats['wallet'], $decimals) }}</div>
            <canvas class="sparkline" data-color="#8b5cf6" data-values='@json($dailyCommission->pluck("commission")->values())'></canvas>
            <div class="kpi-sub">{{ $currencySymbol }}{{ number_format($stats['commission'], $decimals) }} credited commission</div>
        </div>

        <div class="kpi-card" style="--accent:#22c55e;--card-glow:rgba(34,197,94,.14);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-store"></i></div>
                <div class="kpi-trend"><i class="fas fa-motorcycle"></i> {{ number_format($stats['drivers']) }} drivers</div>
            </div>
            <div class="kpi-label">Network</div>
            <div class="kpi-value">{{ number_format($stats['restaurants']) }}</div>
            <canvas class="sparkline" data-color="#22c55e" data-values='@json($dailyActiveRestaurants->pluck("count")->values())'></canvas>
            <div class="kpi-sub">restaurants across {{ number_format($stats['zones']) }} zones</div>
        </div>

        <div class="kpi-card" style="--accent:#14b8a6;--card-glow:rgba(20,184,166,.14);">
            <div class="kpi-top">
                <div class="kpi-icon"><i class="fas fa-circle-check"></i></div>
                <div class="kpi-trend"><i class="fas fa-arrow-trend-up"></i> {{ $branchHealth }}</div>
            </div>
            <div class="kpi-label">Success Rate</div>
            <div class="kpi-value">{{ number_format($stats['success_rate'], 1) }}%</div>
            <canvas class="sparkline" data-color="#14b8a6" data-values='@json($dailySuccessRate->pluck("rate")->values())'></canvas>
            <div class="kpi-sub">{{ number_format($stats['cancellation_rate'], 1) }}% cancellation rate</div>
        </div>
    </section>

    <section class="saas-grid dashboard-row-main">
        <div class="dash-panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Revenue Pulse</h3>
                    <div class="panel-subtitle">Delivered revenue trend for this branch over the last 30 days.</div>
                </div>
            </div>
            <div class="chart-shell">
                <canvas id="branchRevenueChart"></canvas>
            </div>
            <div class="chart-summary">
                <div class="summary-tile">
                    <div class="summary-label">Total Revenue</div>
                    <div class="summary-value">{{ $currencySymbol }}{{ number_format($stats['revenue'], $decimals) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">Order Value</div>
                    <div class="summary-value">{{ $currencySymbol }}{{ number_format($avgOrderValue, $decimals) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">Delivered</div>
                    <div class="summary-value">{{ number_format($stats['completed']) }}</div>
                </div>
                <div class="summary-tile">
                    <div class="summary-label">Live Orders</div>
                    <div class="summary-value">{{ number_format($stats['active']) }}</div>
                </div>
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Recent Orders</h3>
                @if($capabilities['orders_view'] ?? false)
                    <a href="{{ route('branch.orders.live') }}" class="panel-link">View Live</a>
                @endif
            </div>
            <div class="live-order-list">
                @forelse($orders->take(5) as $order)
                    <a href="{{ route('branch.orders.show', $order->id) }}" class="live-order-card">
                        <div class="food-thumb"><i class="fas fa-utensils"></i></div>
                        <div class="min-w-0">
                            <div class="d-flex align-items-center gap-2">
                                <strong class="text-dark">#{{ $order->order_number ?? $order->id }}</strong>
                                <span class="text-muted small">{{ $order->created_at?->diffForHumans() }}</span>
                            </div>
                            <div class="text-dark fw-semibold text-truncate">{{ $order->restaurant->name ?? 'Restaurant' }}</div>
                            <div class="text-muted small text-truncate">{{ $order->driver->name ?? 'Unassigned driver' }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-black text-dark">{{ $currencySymbol }}{{ number_format($order->total, $decimals) }}</div>
                            <span class="status-pill {{ $statusClass($order->status) }}">{{ ucfirst(str_replace('_', ' ', $order->status ?? 'pending')) }}</span>
                        </div>
                    </a>
                @empty
                    <div class="text-center text-muted py-5">No branch orders yet.</div>
                @endforelse
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Top Restaurants</h3>
                @if($capabilities['restaurants_view'] ?? false)
                    <a href="{{ route('branch.restaurants') }}" class="panel-link">View All</a>
                @endif
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
                            <div class="fw-black text-dark">{{ $currencySymbol }}{{ number_format($restaurant->revenue ?? 0, $decimals) }}</div>
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
                    <div class="mini-label">Success Rate</div>
                    <div class="mini-value">{{ number_format($stats['success_rate'], 1) }}%</div>
                    <div class="small text-success fw-bold">{{ number_format($stats['completed']) }} delivered</div>
                </div>
                <div class="mini-stat-card" style="--card-glow:rgba(239,68,68,.12);">
                    <div class="mini-label">Cancelled Orders</div>
                    <div class="mini-value">{{ number_format($stats['cancellation_rate'], 1) }}%</div>
                    <div class="small text-danger fw-bold">{{ number_format($stats['cancelled']) }} total</div>
                </div>
                <div class="mini-stat-card" style="--card-glow:rgba(249,115,22,.12);">
                    <div class="mini-label">Branch Revenue</div>
                    <div class="mini-value">{{ $currencySymbol }}{{ number_format($stats['revenue'], $decimals) }}</div>
                    <div class="small text-success fw-bold">Delivered GMV</div>
                </div>
                <div class="mini-stat-card" style="--card-glow:rgba(139,92,246,.12);">
                    <div class="mini-label">Commission Credited</div>
                    <div class="mini-value">{{ $currencySymbol }}{{ number_format($stats['commission'], $decimals) }}</div>
                    <div class="small text-muted fw-bold">This branch's share</div>
                </div>
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Wallet Activity</h3>
                @if($capabilities['wallet_view'] ?? false)
                    <a href="{{ route('branch.wallet') }}" class="panel-link">View All</a>
                @endif
            </div>
            <div class="activity-list">
                @forelse($walletTransactions as $transaction)
                    <div class="activity-row">
                        <div class="activity-icon" style="background:linear-gradient(135deg,#f97316,#f59e0b);"><i class="fas fa-wallet"></i></div>
                        <div class="min-w-0 flex-fill">
                            <div class="fw-bold text-dark text-truncate">{{ ucfirst(str_replace('_', ' ', $transaction->type)) }}</div>
                            <div class="small text-muted">{{ $transaction->created_at?->diffForHumans() }}</div>
                        </div>
                        <div class="fw-black {{ $transaction->amount >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $transaction->amount >= 0 ? '+' : '-' }}{{ $currencySymbol }}{{ number_format(abs($transaction->amount), $decimals) }}
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No wallet activity yet.</div>
                @endforelse
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Top Drivers</h3>
                @if($capabilities['drivers_view'] ?? false)
                    <a href="{{ route('branch.drivers') }}" class="panel-link">View All</a>
                @endif
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
                            <div class="small text-muted">{{ number_format($driver->orders_count) }} deliveries</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No driver activity yet.</div>
                @endforelse
            </div>
        </div>

        <div class="dash-panel">
            <div class="panel-head">
                <h3 class="panel-title">Settlements</h3>
                @if($capabilities['settlements_view'] ?? false)
                    <a href="{{ route('branch.settlements') }}" class="panel-link">View All</a>
                @endif
            </div>
            <div class="activity-list">
                @forelse($settlements as $settlement)
                    <div class="activity-row">
                        <div class="activity-icon" style="background:linear-gradient(135deg,#111827,#f97316);"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="min-w-0 flex-fill">
                            <div class="fw-bold text-dark text-truncate">{{ $settlement->settlement_number }}</div>
                            <div class="small text-muted">{{ $settlement->period_start?->format('d M') }} - {{ $settlement->period_end?->format('d M') }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-black text-dark">{{ $currencySymbol }}{{ number_format($settlement->amount, $decimals) }}</div>
                            <span class="status-pill {{ $statusClass($settlement->status) }}">{{ ucfirst(str_replace('_', ' ', $settlement->status)) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">No settlements yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="dash-panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Recent Branch Orders</h3>
                <div class="panel-subtitle">Latest branch activity and order state.</div>
            </div>
            @if($capabilities['orders_view'] ?? false)
                <a href="{{ route('branch.orders') }}" class="panel-link">View All</a>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table order-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Restaurant</th>
                        <th>Driver</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Credited Earnings</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <div class="fw-black text-dark">#{{ $order->order_number ?? $order->id }}</div>
                                <div class="small text-muted">{{ $order->created_at?->format('d M Y, h:i A') }}</div>
                            </td>
                            <td>{{ $order->restaurant?->name ?? 'N/A' }}</td>
                            <td>{{ $order->driver?->name ?? 'Unassigned' }}</td>
                            <td>
                                <span class="status-pill {{ $statusClass($order->status) }}">
                                    <i class="fas fa-circle" style="font-size:7px;"></i>
                                    {{ ucfirst(str_replace('_', ' ', $order->status ?? 'pending')) }}
                                </span>
                            </td>
                            <td class="fw-bold">{{ $currencySymbol }}{{ number_format($order->total, $decimals) }}</td>
                            <td class="fw-bold text-success">
                                {{ $currencySymbol }}{{ number_format($order->branch_commission_settled ? $order->branch_commission : 0, $decimals) }}
                                @unless($order->branch_commission_settled)
                                    <div class="small text-muted fw-normal">Pending delivery</div>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x d-block mb-3 opacity-50"></i>
                                No branch orders found yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    const branchChartLabels = [];
    const branchChartData = [];

    @foreach($dailyRevenue as $item)
        branchChartLabels.push('{{ \Carbon\Carbon::parse($item->date)->format("d M") }}');
        branchChartData.push({{ (float) $item->revenue }});
    @endforeach

    const branchCurrencySymbol = @json($currencySymbol);
    let branchRevenueChart;

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
        ctx.strokeStyle = canvas.dataset.color || '#f97316';
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
    }

    function initBranchDashboard() {
        document.querySelectorAll('.sparkline').forEach(drawMiniSparkline);

        const canvas = document.getElementById('branchRevenueChart');
        if (!canvas || typeof Chart === 'undefined') return;

        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(249, 115, 22, .28)');
        gradient.addColorStop(1, 'rgba(249, 115, 22, 0)');

        branchRevenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: branchChartLabels,
                datasets: [{
                    data: branchChartData,
                    borderColor: '#f97316',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.42,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#f97316',
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
                                return branchCurrencySymbol + Number(context.raw || 0).toLocaleString(undefined, {
                                    minimumFractionDigits: {{ $decimals }},
                                    maximumFractionDigits: {{ $decimals }},
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
                            callback: function(value) { return branchCurrencySymbol + Number(value).toLocaleString(); }
                        }
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initBranchDashboard);
    window.addEventListener('resize', () => document.querySelectorAll('.sparkline').forEach(drawMiniSparkline));
</script>
@endsection
