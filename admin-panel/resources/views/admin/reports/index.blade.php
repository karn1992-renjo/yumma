@extends('layouts.admin')

@section('title', 'Analytics')

@section('styles')
<style>
    .aa-shell { display: flex; flex-direction: column; gap: 18px; }
    .aa-head,
    .aa-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
    }
    .aa-head { padding: 18px; }
    .aa-title h1 { margin: 0; color: #0f172a; font-size: 1.55rem; font-weight: 850; letter-spacing: 0; }
    .aa-title p { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
    .aa-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 11px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: .78rem;
        font-weight: 850;
        white-space: nowrap;
    }
    .aa-chip.success { background: #dcfce7; color: #047857; }
    .aa-chip.warning { background: #fef3c7; color: #92400e; }
    .aa-chip.danger { background: #fee2e2; color: #b91c1c; }
    .aa-chip.info { background: #dbeafe; color: #1d4ed8; }
    .aa-filter { padding: 16px; }
    .aa-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .aa-stat {
        min-height: 118px;
        padding: 17px;
        border: 1px solid var(--border);
        border-radius: 18px;
        background: #fff;
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }
    .aa-stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--stat-color);
        background: color-mix(in srgb, var(--stat-color) 13%, #fff);
        flex: 0 0 auto;
    }
    .aa-stat-label { color: #64748b; font-size: .72rem; font-weight: 850; text-transform: uppercase; letter-spacing: .05em; }
    .aa-stat-value { color: #0f172a; font-size: 1.45rem; font-weight: 900; line-height: 1.15; }
    .aa-stat-sub { color: #64748b; font-size: .8rem; font-weight: 650; margin-top: 4px; }
    .aa-card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #e2e8f0;
    }
    .aa-card-head h2 { margin: 0; color: #0f172a; font-size: 1.02rem; font-weight: 850; }
    .aa-card-head p { margin: 3px 0 0; color: #64748b; font-size: .82rem; font-weight: 650; }
    .aa-grid-2 { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, .6fr); gap: 18px; }
    .aa-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .aa-chart { height: 320px; padding: 18px; }
    .aa-chart.small { height: 320px; }
    .aa-list { padding: 8px 18px 18px; }
    .aa-list-row {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid #edf2f7;
    }
    .aa-list-row:last-child { border-bottom: 0; }
    .aa-list-row span { color: #64748b; font-weight: 700; }
    .aa-list-row strong { color: #0f172a; font-weight: 850; text-align: right; }
    .aa-table { margin: 0; }
    .aa-table thead th {
        padding: 13px 18px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #64748b;
        font-size: .73rem;
        font-weight: 850;
        text-transform: uppercase;
        letter-spacing: .05em;
        white-space: nowrap;
    }
    .aa-table tbody td { padding: 15px 18px; vertical-align: middle; border-color: #edf2f7; }
    .aa-primary { color: #0f172a; font-weight: 850; }
    .aa-muted { color: #64748b; font-size: .82rem; font-weight: 650; }

    @media (max-width: 1300px) {
        .aa-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .aa-grid-2,
        .aa-grid-3 { grid-template-columns: 1fr; }
    }
    @media (max-width: 767px) {
        .aa-title h1 { font-size: 1.25rem; }
        .aa-stat-grid { grid-template-columns: 1fr; }
        .aa-card-head { align-items: flex-start; flex-direction: column; }
        .aa-chart, .aa-chart.small { height: 280px; padding: 12px; }
        .aa-table thead { display: none; }
        .aa-table, .aa-table tbody, .aa-table tr, .aa-table td { display: block; width: 100%; }
        .aa-table tbody tr {
            margin: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }
        .aa-table tbody td {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 14px;
            border-bottom: 1px solid #edf2f7;
        }
        .aa-table tbody td::before {
            content: attr(data-label);
            flex: 0 0 96px;
            color: #64748b;
            font-size: .72rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .aa-table tbody td:last-child { border-bottom: 0; }
    }
</style>
@endsection

@section('content')
@php
    $currencySymbol = \App\Models\AppSetting::getValue('currency_symbol', 'Rs');
    $decimals = \App\Models\AppSetting::currencyDecimals();
    $statusLabels = \App\Models\Order::getStatuses();
    $paymentStatusLabels = \App\Models\Order::getPaymentStatuses();
    $pageRoute = request()->routeIs('admin.analytics') ? route('admin.analytics') : route('admin.reports.index');
    $csvRoute = request()->routeIs('admin.analytics') ? route('admin.analytics', array_merge(request()->query(), ['export' => 'csv'])) : route('admin.reports.index', array_merge(request()->query(), ['export' => 'csv']));
    $money = fn ($value) => $currencySymbol . number_format((float) $value, $decimals);
@endphp

<div class="aa-shell">
    <section class="aa-head">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="aa-title">
                <h1>Admin Analytics</h1>
                <p>Revenue, order health, dispatch performance, payouts, and partner contribution for the selected period.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span class="aa-chip info"><i class="fas fa-calendar"></i>{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</span>
                <a href="{{ $csvRoute }}" class="btn btn-primary fw-bold"><i class="fas fa-download me-2"></i>Export CSV</a>
            </div>
        </div>
    </section>

    <section class="aa-card aa-filter">
        <form method="GET" action="{{ $pageRoute }}" class="row g-3 align-items-end">
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-bold">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-bold">End Date</label>
                <input type="date" name="end_date" class="form-control" value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-bold">Restaurant</label>
                <select name="restaurant_id" class="form-select">
                    <option value="">All Restaurants</option>
                    @foreach($restaurantOptions as $restaurant)
                        <option value="{{ $restaurant->id }}" @selected((string) request('restaurant_id') === (string) $restaurant->id)>{{ $restaurant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-bold">Order Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach($statusLabels as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected(request('status') === $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-bold">Payment</label>
                <select name="payment_status" class="form-select">
                    <option value="">All Payments</option>
                    @foreach($paymentStatusLabels as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected(request('payment_status') === $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-1 col-md-6 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit"><i class="fas fa-filter"></i></button>
                <a class="btn btn-light border" href="{{ $pageRoute }}">Reset</a>
            </div>
        </form>
    </section>

    <section class="aa-stat-grid">
        <div class="aa-stat" style="--stat-color:#f97316;">
            <div><div class="aa-stat-label">Gross Sales</div><div class="aa-stat-value">{{ $money($summary->gross_sales ?? 0) }}</div><div class="aa-stat-sub">{{ number_format($summary->total_orders ?? 0) }} orders</div></div>
            <div class="aa-stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
        <div class="aa-stat" style="--stat-color:#10b981;">
            <div><div class="aa-stat-label">Admin Revenue</div><div class="aa-stat-value">{{ $money($summary->admin_commission_total ?? 0) }}</div><div class="aa-stat-sub">Commission and platform fees</div></div>
            <div class="aa-stat-icon"><i class="fas fa-percent"></i></div>
        </div>
        <div class="aa-stat" style="--stat-color:#2563eb;">
            <div><div class="aa-stat-label">Restaurant Earnings</div><div class="aa-stat-value">{{ $money($summary->restaurant_earning_total ?? 0) }}</div><div class="aa-stat-sub">Partner settlement side</div></div>
            <div class="aa-stat-icon"><i class="fas fa-store"></i></div>
        </div>
        <div class="aa-stat" style="--stat-color:#7c3aed;">
            <div><div class="aa-stat-label">Driver Earnings</div><div class="aa-stat-value">{{ $money($summary->driver_earning_total ?? 0) }}</div><div class="aa-stat-sub">Delivery partner payouts</div></div>
            <div class="aa-stat-icon"><i class="fas fa-truck"></i></div>
        </div>
        <div class="aa-stat" style="--stat-color:#14b8a6;">
            <div><div class="aa-stat-label">Avg Order Value</div><div class="aa-stat-value">{{ $money($summary->avg_order_value ?? 0) }}</div><div class="aa-stat-sub">{{ number_format($summary->successful_payments ?? 0) }} paid orders</div></div>
            <div class="aa-stat-icon"><i class="fas fa-receipt"></i></div>
        </div>
        <div class="aa-stat" style="--stat-color:#f59e0b;">
            <div><div class="aa-stat-label">Delivery Charges</div><div class="aa-stat-value">{{ $money($summary->delivery_fee_total ?? 0) }}</div><div class="aa-stat-sub">Distance collections</div></div>
            <div class="aa-stat-icon"><i class="fas fa-route"></i></div>
        </div>
        <div class="aa-stat" style="--stat-color:#ef4444;">
            <div><div class="aa-stat-label">Discounts</div><div class="aa-stat-value">{{ $money($summary->discount_total ?? 0) }}</div><div class="aa-stat-sub">Promo impact</div></div>
            <div class="aa-stat-icon"><i class="fas fa-tags"></i></div>
        </div>
        <div class="aa-stat" style="--stat-color:#64748b;">
            <div><div class="aa-stat-label">Refunds</div><div class="aa-stat-value">{{ $money($summary->refund_total ?? 0) }}</div><div class="aa-stat-sub">{{ number_format($summary->cancelled_orders ?? 0) }} cancelled orders</div></div>
            <div class="aa-stat-icon"><i class="fas fa-rotate-left"></i></div>
        </div>
    </section>

    <section class="aa-grid-2">
        <div class="aa-card">
            <div class="aa-card-head">
                <div><h2>Daily Sales Trend</h2><p>Sales and order count across the selected window.</p></div>
            </div>
            <div class="aa-chart"><canvas id="salesTrendChart"></canvas></div>
        </div>
        <div class="aa-card">
            <div class="aa-card-head">
                <div><h2>Status Mix</h2><p>Order lifecycle split.</p></div>
            </div>
            <div class="aa-chart small"><canvas id="statusBreakdownChart"></canvas></div>
        </div>
    </section>

    <section class="aa-grid-3">
        <div class="aa-card">
            <div class="aa-card-head"><div><h2>Dispatch Health</h2><p>Assignment quality indicators.</p></div></div>
            <div class="aa-list">
                <div class="aa-list-row"><span>Assigned Orders</span><strong>{{ number_format($dispatchMetrics['assigned_orders'] ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Accepted Orders</span><strong>{{ number_format($dispatchMetrics['accepted_orders'] ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Acceptance Rate</span><strong>{{ number_format((float) ($dispatchMetrics['acceptance_rate'] ?? 0), 1) }}%</strong></div>
                <div class="aa-list-row"><span>Avg Acceptance Time</span><strong>{{ number_format((float) ($dispatchMetrics['avg_acceptance_minutes'] ?? 0), 1) }} min</strong></div>
                <div class="aa-list-row"><span>Route Matched Batches</span><strong>{{ number_format($dispatchMetrics['route_matched_batches'] ?? 0) }}</strong></div>
            </div>
        </div>
        <div class="aa-card">
            <div class="aa-card-head"><div><h2>Platform Users</h2><p>Current user base by role.</p></div></div>
            <div class="aa-list">
                <div class="aa-list-row"><span>Customers</span><strong>{{ number_format($userCounts['customers'] ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Drivers</span><strong>{{ number_format($userCounts['drivers'] ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Restaurant Owners</span><strong>{{ number_format($userCounts['restaurant_owners'] ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Restaurant Staff</span><strong>{{ number_format($userCounts['restaurant_staff'] ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Successful Payments</span><strong>{{ number_format($summary->successful_payments ?? 0) }}</strong></div>
            </div>
        </div>
        <div class="aa-card">
            <div class="aa-card-head"><div><h2>Payout Overview</h2><p>Settlement processing exposure.</p></div></div>
            <div class="aa-list">
                <div class="aa-list-row"><span>Total Payouts</span><strong>{{ number_format($payoutSummary->total_payouts ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Total Amount</span><strong>{{ $money($payoutSummary->total_amount ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Processed</span><strong>{{ number_format($payoutSummary->processed_count ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Pending</span><strong>{{ number_format($payoutSummary->pending_count ?? 0) }}</strong></div>
                <div class="aa-list-row"><span>Failed</span><strong>{{ number_format($payoutSummary->failed_count ?? 0) }}</strong></div>
            </div>
        </div>
    </section>

    <section class="aa-grid-3">
        <div class="aa-card">
            <div class="aa-card-head"><div><h2>Top Restaurants</h2><p>Highest sales in this window.</p></div></div>
            <div class="table-responsive">
                <table class="table aa-table">
                    <thead><tr><th>Restaurant</th><th>Orders</th><th>Sales</th></tr></thead>
                    <tbody>
                        @forelse($topRestaurants as $restaurant)
                            <tr>
                                <td data-label="Restaurant" class="aa-primary">{{ $restaurant->name }}</td>
                                <td data-label="Orders">{{ number_format($restaurant->orders_count) }}</td>
                                <td data-label="Sales" class="aa-primary">{{ $money($restaurant->sales_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No restaurant data found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="aa-card">
            <div class="aa-card-head"><div><h2>Top Drivers</h2><p>Highest delivery contribution.</p></div></div>
            <div class="table-responsive">
                <table class="table aa-table">
                    <thead><tr><th>Driver</th><th>Orders</th><th>Earnings</th></tr></thead>
                    <tbody>
                        @forelse($topDrivers as $driver)
                            <tr>
                                <td data-label="Driver" class="aa-primary">{{ $driver->name }}</td>
                                <td data-label="Orders">{{ number_format($driver->orders_count) }}</td>
                                <td data-label="Earnings" class="aa-primary">{{ $money($driver->earnings_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No driver data found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="aa-card">
            <div class="aa-card-head"><div><h2>Payment Mix</h2><p>Checkout channel preference.</p></div></div>
            <div class="aa-list">
                @forelse($paymentBreakdown as $method => $count)
                    <div class="aa-list-row"><span>{{ strtoupper($method ?: 'N/A') }}</span><strong>{{ number_format($count) }}</strong></div>
                @empty
                    <div class="text-muted p-3">No payment data found.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="aa-card">
        <div class="aa-card-head">
            <div><h2>Recent Orders</h2><p>Operational records matching the selected analytics filters.</p></div>
        </div>
        <div class="table-responsive">
            <table class="table aa-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Restaurant</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Admin Commission</th>
                        <th>Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $statusClass = match ($order->status) {
                                'delivered', 'completed' => 'success',
                                'cancelled', 'failed' => 'danger',
                                'pending' => 'warning',
                                default => 'info',
                            };
                            $paymentClass = $order->payment_status === 'success' ? 'success' : ($order->payment_status === 'failed' ? 'danger' : 'warning');
                        @endphp
                        <tr>
                            <td data-label="Order">
                                <div class="aa-primary">{{ $order->order_number }}</div>
                                <div class="aa-muted">{{ strtoupper($order->payment_method ?? 'N/A') }}</div>
                            </td>
                            <td data-label="Restaurant">{{ $order->restaurant?->name ?? 'N/A' }}</td>
                            <td data-label="Customer">{{ $order->customer?->name ?? $order->customer_name ?? 'N/A' }}</td>
                            <td data-label="Status"><span class="aa-chip {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></td>
                            <td data-label="Payment"><span class="aa-chip {{ $paymentClass }}">{{ ucfirst($order->payment_status ?? 'pending') }}</span></td>
                            <td data-label="Commission">{{ $money($order->admin_commission ?? 0) }}</td>
                            <td data-label="Total" class="aa-primary">{{ $money($order->total ?? 0) }}</td>
                            <td data-label="Date">{{ optional($order->created_at)->format('d M Y, h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5">No orders found for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top">
            {{ $orders->withQueryString()->links() }}
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
const salesTrendLabels = @json($dailyPerformance->pluck('report_date')->map(fn ($date) => \Carbon\Carbon::parse($date)->format('d M'))->values());
const salesTrendOrders = @json($dailyPerformance->pluck('orders_count')->map(fn ($value) => (int) $value)->values());
const salesTrendAmounts = @json($dailyPerformance->pluck('sales_total')->map(fn ($value) => round((float) $value, 2))->values());
const statusLabels = @json(collect($statusBreakdown)->keys()->map(fn ($label) => ucfirst(str_replace('_', ' ', $label)))->values());
const statusValues = @json(collect($statusBreakdown)->values()->map(fn ($value) => (int) $value)->values());

const salesTrendCanvas = document.getElementById('salesTrendChart');
if (salesTrendCanvas && window.Chart) {
    new Chart(salesTrendCanvas, {
        type: 'line',
        data: {
            labels: salesTrendLabels,
            datasets: [
                {
                    label: 'Sales',
                    data: salesTrendAmounts,
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, .14)',
                    fill: true,
                    tension: .35,
                    pointRadius: 3,
                    yAxisID: 'sales',
                },
                {
                    label: 'Orders',
                    data: salesTrendOrders,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, .12)',
                    fill: false,
                    tension: .35,
                    pointRadius: 3,
                    yAxisID: 'orders',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { usePointStyle: true, boxWidth: 8 } } },
            scales: {
                sales: { beginAtZero: true, position: 'left', grid: { color: '#edf2f7' } },
                orders: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } },
            }
        }
    });
}

const statusCanvas = document.getElementById('statusBreakdownChart');
if (statusCanvas && window.Chart) {
    new Chart(statusCanvas, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusValues,
                backgroundColor: ['#10b981', '#f97316', '#2563eb', '#f59e0b', '#ef4444', '#7c3aed', '#14b8a6', '#64748b'],
                borderColor: '#ffffff',
                borderWidth: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '64%',
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } }
        }
    });
}
</script>
@endsection
