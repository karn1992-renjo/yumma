@extends('layouts.admin')

@section('title', 'Dashboard')
@section('header', 'Dashboard')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');
    $deliveredOrders = $recentOrders->where('status', 'delivered')->count();
    $pendingOrders = $recentOrders->whereIn('status', ['pending', 'confirmed'])->count();
    $avgOrderValue = $totalOrders > 0 ? $totalRevenue / max($totalOrders, 1) : 0;
    $chartTotal = collect($dailyRevenue)->sum('revenue');
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
        letter-spacing: -.04em;
        margin: 0;
    }

    .dash-hero p {
        color: rgba(255,255,255,.82);
        max-width: 650px;
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
        letter-spacing: -.03em;
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
        letter-spacing: -.02em;
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

    .restaurant-rank {
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
        letter-spacing: .06em;
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

    .status-pill.delivered { background: #dcfce7; color: #166534; }
    .status-pill.cancelled { background: #fee2e2; color: #991b1b; }
    .status-pill.pending { background: #fef3c7; color: #92400e; }
    .status-pill.confirmed { background: #dbeafe; color: #1d4ed8; }
    .status-pill.preparing { background: #ffedd5; color: #9a3412; }

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
                        <i class="fas fa-bolt"></i>
                        Live Platform Command Center
                    </div>
                    <h1>Welcome back,<br>{{ auth()->user()->name }}.</h1>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-light rounded-pill fw-bold px-4">
                        Orders
                    </a>
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-light rounded-pill fw-bold px-4">
                        Reports
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="metric-grid">
        <div class="metric-card" style="--metric-color:#f97316;">
            <div class="metric-icon"><i class="fas fa-sack-dollar"></i></div>
            <div class="metric-label">Total Revenue</div>
            <div class="metric-value">{{ $currencySymbol }}{{ number_format($totalRevenue, App\Models\AppSetting::currencyDecimals()) }}</div>
            <div class="metric-foot"><i class="fas fa-arrow-trend-up"></i> Delivered order value</div>
        </div>
        <div class="metric-card" style="--metric-color:#10b981;">
            <div class="metric-icon"><i class="fas fa-bag-shopping"></i></div>
            <div class="metric-label">Total Orders</div>
            <div class="metric-value">{{ number_format($totalOrders) }}</div>
            <div class="metric-foot"><i class="fas fa-clock"></i> All time platform orders</div>
        </div>
        <div class="metric-card" style="--metric-color:#3b82f6;">
            <div class="metric-icon"><i class="fas fa-store"></i></div>
            <div class="metric-label">Restaurants</div>
            <div class="metric-value">{{ number_format($totalRestaurants) }}</div>
            <div class="metric-foot"><i class="fas fa-utensils"></i> Active supply network</div>
        </div>
        <div class="metric-card" style="--metric-color:#8b5cf6;">
            <div class="metric-icon"><i class="fas fa-users"></i></div>
            <div class="metric-label">Customers</div>
            <div class="metric-value">{{ number_format($totalUsers) }}</div>
            <div class="metric-foot"><i class="fas fa-receipt"></i> Avg {{ $currencySymbol }}{{ number_format($avgOrderValue, App\Models\AppSetting::currencyDecimals()) }} / order</div>
        </div>
    </section>

    <section class="row g-4">
        <div class="col-xl-8">
            <div class="glass-card h-100">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Revenue Pulse</h3>
                        <div class="panel-subtitle">Delivered revenue trend across the last 30 days.</div>
                    </div>
                    <select id="chartPeriod" class="form-select rounded-pill w-auto" onchange="updateChart()">
                        <option value="7">Last 7 Days</option>
                        <option value="30" selected>Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                    </select>
                </div>
                <div class="chart-wrap">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="glass-card h-100">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Top Restaurants</h3>
                        <div class="panel-subtitle">Best revenue performers.</div>
                    </div>
                    <i class="fas fa-trophy text-warning fs-4"></i>
                </div>
                <div class="p-3">
                    @forelse($topRestaurants as $index => $restaurant)
                        <div class="restaurant-rank">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rank-badge">{{ $index + 1 }}</div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $restaurant->name }}</div>
                                    <div class="small text-muted">{{ number_format($restaurant->orders_count) }} orders</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-black text-success">{{ $currencySymbol }}{{ number_format($restaurant->revenue ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
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

    <section class="glass-card">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Recent Orders</h3>
                <div class="panel-subtitle">Latest platform activity and order state.</div>
            </div>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-primary rounded-pill fw-bold">
                View All <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table order-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Restaurant</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        @php $statusClass = str_replace('_', '-', strtolower($order->status ?? 'pending')); @endphp
                        <tr>
                            <td>
                                <div class="fw-black text-dark">#{{ $order->order_number ?? $order->id }}</div>
                                <div class="small text-muted">Order ID {{ $order->id }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $order->customer_name ?? $order->customer?->name ?? 'N/A' }}</div>
                                <div class="small text-muted">{{ $order->customer_phone ?? '' }}</div>
                            </td>
                            <td>{{ $order->restaurant->name ?? 'N/A' }}</td>
                            <td class="fw-bold">{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td>
                                <span class="status-pill {{ $statusClass }}">
                                    <i class="fas fa-circle" style="font-size:7px;"></i>
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                            <td>
                                <div>{{ $order->created_at->format('d M Y') }}</div>
                                <div class="small text-muted">{{ $order->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-sm btn-light rounded-circle">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x d-block mb-3 opacity-50"></i>
                                No orders found yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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

    function initChart() {
        const canvas = document.getElementById('revenueChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 320);
        gradient.addColorStop(0, 'rgba(249, 115, 22, .32)');
        gradient.addColorStop(1, 'rgba(249, 115, 22, 0)');

        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
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
                                return currencySymbol + Number(context.raw || 0).toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
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
                                return currencySymbol + Number(value).toLocaleString();
                            }
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
</script>
@endsection
