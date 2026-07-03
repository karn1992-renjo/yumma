@extends('layouts.admin')

@section('title', 'Branch Dashboard')
@section('header', 'Branch Dashboard')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', 'Rs');
    $decimals = App\Models\AppSetting::currencyDecimals();
    $avgOrderValue = $stats['orders'] > 0 ? $stats['revenue'] / max($stats['orders'], 1) : 0;
    $statusClass = fn ($status) => str_replace('_', '-', strtolower($status ?? 'pending'));
@endphp

@section('styles')
<style>
    .dashboard-shell {
        display: grid;
        gap: 24px;
    }

    .dash-hero {
        position: relative;
        overflow: hidden;
        border-radius: 32px;
        padding: 30px;
        color: #fff;
        background:
            radial-gradient(circle at 12% 20%, rgba(255,255,255,.28), transparent 28%),
            radial-gradient(circle at 88% 12%, rgba(251,191,36,.32), transparent 24%),
            linear-gradient(135deg, #111827 0%, #7c2d12 48%, #f97316 100%);
        box-shadow: 0 24px 70px rgba(249, 115, 22, .26);
    }

    .dash-hero::after {
        content: "";
        position: absolute;
        inset: auto -80px -120px auto;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        background: rgba(255,255,255,.16);
        filter: blur(2px);
    }

    .dash-hero-content {
        position: relative;
        z-index: 1;
    }

    .hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        border-radius: 999px;
        background: rgba(255,255,255,.16);
        border: 1px solid rgba(255,255,255,.22);
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 16px;
    }

    .dash-hero h1 {
        font-size: clamp(30px, 4vw, 48px);
        line-height: 1.05;
        font-weight: 900;
        letter-spacing: 0;
        margin: 0;
    }

    .dash-hero p {
        color: rgba(255,255,255,.82);
        max-width: 760px;
        margin: 14px 0 0;
        font-size: 15px;
    }

    .metric-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .metric-card {
        position: relative;
        overflow: hidden;
        min-height: 150px;
        padding: 22px;
        border-radius: 26px;
        border: 1px solid rgba(15, 23, 42, .07);
        background: #fff;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .06);
    }

    .metric-card::after {
        content: "";
        position: absolute;
        right: -36px;
        top: -36px;
        width: 112px;
        height: 112px;
        border-radius: 50%;
        opacity: .12;
        background: var(--metric-color);
    }

    .metric-icon {
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 17px;
        color: #fff;
        background: var(--metric-color);
        box-shadow: 0 12px 25px rgba(15, 23, 42, .14);
    }

    .metric-label {
        color: #64748b;
        font-size: 13px;
        font-weight: 700;
        margin-top: 18px;
    }

    .metric-value {
        color: #0f172a;
        font-size: 28px;
        font-weight: 900;
        letter-spacing: 0;
        margin-top: 4px;
    }

    .metric-foot {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .fw-black {
        font-weight: 900;
    }

    .glass-card {
        border-radius: 28px;
        border: 1px solid rgba(15, 23, 42, .08);
        background: rgba(255,255,255,.86);
        box-shadow: 0 18px 50px rgba(15, 23, 42, .07);
        overflow: hidden;
    }

    .panel-head {
        padding: 22px 24px;
        border-bottom: 1px solid rgba(15, 23, 42, .07);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
    }

    .panel-title {
        margin: 0;
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        letter-spacing: 0;
    }

    .panel-subtitle {
        color: #64748b;
        font-size: 12px;
        margin-top: 4px;
    }

    .chart-wrap {
        padding: 24px;
        min-height: 370px;
    }

    .rank-row,
    .activity-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 15px;
        border-radius: 20px;
        background: #f8fafc;
        margin-bottom: 12px;
    }

    .rank-badge {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        color: #fff;
        font-weight: 900;
        background: linear-gradient(135deg, #f97316, #ef4444);
    }

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

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        background: #eef2ff;
        color: #4338ca;
    }

    .status-pill.delivered,
    .status-pill.paid,
    .status-pill.approved { background: #dcfce7; color: #166534; }
    .status-pill.cancelled,
    .status-pill.rejected { background: #fee2e2; color: #991b1b; }
    .status-pill.pending { background: #fef3c7; color: #92400e; }
    .status-pill.confirmed,
    .status-pill.generated { background: #dbeafe; color: #1d4ed8; }
    .status-pill.preparing,
    .status-pill.requested { background: #ffedd5; color: #9a3412; }

    @media (max-width: 1199px) {
        .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 767px) {
        .dash-hero { padding: 24px; border-radius: 24px; }
        .metric-grid { grid-template-columns: 1fr; }
        .panel-head { align-items: flex-start; flex-direction: column; }
    }
</style>
@endsection

@section('content')
<div class="dashboard-shell">
    <section class="dash-hero">
        <div class="dash-hero-content">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="hero-kicker">
                        <i class="fas fa-code-branch"></i>
                        {{ $branch->code }} Branch Command Center
                    </div>
                    <h1>{{ $branch->name }}</h1>
                    <p>
                        {{ ucfirst($branch->status) }} branch covering restaurants, drivers, territories, wallet,
                        settlements, and live branch orders.
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @if($capabilities['orders_view'] ?? false)
                        <a href="{{ route('branch.orders') }}" class="btn btn-light rounded-pill fw-bold px-4">
                            Orders
                        </a>
                    @endif
                    @if($capabilities['reports_view'] ?? false)
                        <a href="{{ route('branch.reports') }}" class="btn btn-outline-light rounded-pill fw-bold px-4">
                            Reports
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="metric-grid">
        <div class="metric-card" style="--metric-color:#f97316;">
            <div class="metric-icon"><i class="fas fa-sack-dollar"></i></div>
            <div class="metric-label">Branch Revenue</div>
            <div class="metric-value">{{ $currencySymbol }}{{ number_format($stats['revenue'], $decimals) }}</div>
            <div class="metric-foot"><i class="fas fa-arrow-trend-up"></i> Delivered order value</div>
        </div>
        <div class="metric-card" style="--metric-color:#10b981;">
            <div class="metric-icon"><i class="fas fa-bag-shopping"></i></div>
            <div class="metric-label">Branch Orders</div>
            <div class="metric-value">{{ number_format($stats['orders']) }}</div>
            <div class="metric-foot"><i class="fas fa-check-circle"></i> {{ number_format($stats['completed']) }} delivered</div>
        </div>
        <div class="metric-card" style="--metric-color:#3b82f6;">
            <div class="metric-icon"><i class="fas fa-wallet"></i></div>
            <div class="metric-label">Wallet Balance</div>
            <div class="metric-value">{{ $currencySymbol }}{{ number_format($stats['wallet'], $decimals) }}</div>
            <div class="metric-foot"><i class="fas fa-coins"></i> {{ $currencySymbol }}{{ number_format($stats['commission'], $decimals) }} credited commission</div>
        </div>
        <div class="metric-card" style="--metric-color:#8b5cf6;">
            <div class="metric-icon"><i class="fas fa-store"></i></div>
            <div class="metric-label">Network</div>
            <div class="metric-value">{{ number_format($stats['restaurants']) }} / {{ number_format($stats['drivers']) }}</div>
            <div class="metric-foot"><i class="fas fa-map-location-dot"></i> {{ number_format($stats['zones']) }} zones, avg {{ $currencySymbol }}{{ number_format($avgOrderValue, $decimals) }}</div>
        </div>
    </section>

    <section class="row g-4">
        <div class="col-xl-8">
            <div class="glass-card h-100">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Revenue Pulse</h3>
                        <div class="panel-subtitle">Delivered revenue trend across the last 30 days for this branch.</div>
                    </div>
                    @if($capabilities['reports_view'] ?? false)
                        <a href="{{ route('branch.reports') }}" class="btn btn-outline-primary rounded-pill fw-bold">
                            Full Report <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    @endif
                </div>
                <div class="chart-wrap">
                    <canvas id="branchRevenueChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="glass-card h-100">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Top Restaurants</h3>
                        <div class="panel-subtitle">Best branch performers.</div>
                    </div>
                    <i class="fas fa-trophy text-warning fs-4"></i>
                </div>
                <div class="p-3">
                    @forelse($topRestaurants as $index => $restaurant)
                        <div class="rank-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rank-badge">{{ $index + 1 }}</div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $restaurant->name }}</div>
                                    <div class="small text-muted">{{ number_format($restaurant->orders_count) }} orders</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-black text-success">{{ $currencySymbol }}{{ number_format($restaurant->revenue ?? 0, $decimals) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-store-slash fa-2x mb-3 opacity-50"></i>
                            <div>No restaurant performance yet.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="row g-4">
        <div class="col-xl-8">
            <div class="glass-card">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Recent Branch Orders</h3>
                        <div class="panel-subtitle">Latest branch activity and order state.</div>
                    </div>
                    @if($capabilities['orders_view'] ?? false)
                        <a href="{{ route('branch.orders') }}" class="btn btn-outline-primary rounded-pill fw-bold">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
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
            </div>
        </div>

        <div class="col-xl-4">
            <div class="glass-card mb-4">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Top Drivers</h3>
                        <div class="panel-subtitle">Delivery volume leaders.</div>
                    </div>
                    <i class="fas fa-motorcycle text-primary fs-4"></i>
                </div>
                <div class="p-3">
                    @forelse($topDrivers as $index => $driver)
                        <div class="rank-row">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rank-badge">{{ $index + 1 }}</div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $driver->name }}</div>
                                    <div class="small text-muted">{{ $driver->email }}</div>
                                </div>
                            </div>
                            <div class="fw-black">{{ number_format($driver->orders_count) }}</div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">No driver activity yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="glass-card">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Wallet Activity</h3>
                        <div class="panel-subtitle">Latest credits, debits, and adjustments.</div>
                    </div>
                    @if($capabilities['wallet_view'] ?? false)
                        <a href="{{ route('branch.wallet') }}" class="btn btn-sm btn-light rounded-pill fw-bold">Wallet</a>
                    @endif
                </div>
                <div class="p-3">
                    @forelse($walletTransactions as $transaction)
                        <div class="activity-row">
                            <div>
                                <div class="fw-bold text-dark">{{ ucfirst(str_replace('_', ' ', $transaction->type)) }}</div>
                                <div class="small text-muted">{{ $transaction->created_at?->format('d M Y, h:i A') }}</div>
                            </div>
                            <div class="fw-black {{ $transaction->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $transaction->amount >= 0 ? '+' : '-' }}{{ $currencySymbol }}{{ number_format(abs($transaction->amount), $decimals) }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">No wallet activity yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="glass-card">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Recent Settlements</h3>
                <div class="panel-subtitle">Settlement requests and payout progress for this branch.</div>
            </div>
            @if($capabilities['settlements_view'] ?? false)
                <a href="{{ route('branch.settlements') }}" class="btn btn-outline-primary rounded-pill fw-bold">
                    Settlements <i class="fas fa-arrow-right ms-1"></i>
                </a>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table order-table">
                <thead>
                    <tr>
                        <th>Settlement</th>
                        <th>Period</th>
                        <th>Gross</th>
                        <th>Branch Earnings</th>
                        <th>Net</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($settlements as $settlement)
                        <tr>
                            <td class="fw-black text-dark">{{ $settlement->settlement_number }}</td>
                            <td>{{ $settlement->period_start?->format('d M Y') }} - {{ $settlement->period_end?->format('d M Y') }}</td>
                            <td>{{ $currencySymbol }}{{ number_format($settlement->gross_orders, $decimals) }}</td>
                            <td>{{ $currencySymbol }}{{ number_format($settlement->branch_commission, $decimals) }}</td>
                            <td class="fw-bold">{{ $currencySymbol }}{{ number_format($settlement->amount, $decimals) }}</td>
                            <td>
                                <span class="status-pill {{ $statusClass($settlement->status) }}">
                                    <i class="fas fa-circle" style="font-size:7px;"></i>
                                    {{ ucfirst(str_replace('_', ' ', $settlement->status)) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No settlements generated yet.</td>
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

    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('branchRevenueChart');
        if (!canvas || typeof Chart === 'undefined') return;

        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 320);
        gradient.addColorStop(0, 'rgba(249, 115, 22, .32)');
        gradient.addColorStop(1, 'rgba(249, 115, 22, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: branchChartLabels,
                datasets: [{
                    data: branchChartData,
                    borderColor: '#f97316',
                    backgroundColor: gradient,
                    borderWidth: 4,
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
                                return @json($currencySymbol) + Number(context.raw || 0).toLocaleString(undefined, {
                                    minimumFractionDigits: {{ $decimals }},
                                    maximumFractionDigits: {{ $decimals }},
                                });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { weight: 700 } },
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: 'rgba(148, 163, 184, .18)' },
                        ticks: {
                            color: '#64748b',
                            font: { weight: 700 },
                            callback: function(value) {
                                return @json($currencySymbol) + Number(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    });
</script>
@endsection
