@extends('layouts.admin')

@section('title', 'Reports')

@section('styles')
<style>
    .reports-shell {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .reports-hero {
        position: relative;
        overflow: hidden;
        padding: 2rem;
        border-radius: 28px;
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, 0.2), transparent 34%),
            linear-gradient(135deg, #171717 0%, #2b1f16 42%, #f97316 100%);
        color: #fff;
        box-shadow: 0 22px 48px rgba(15, 23, 42, 0.16);
    }

    .reports-hero::after {
        content: '';
        position: absolute;
        right: -36px;
        bottom: -42px;
        width: 180px;
        height: 180px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
    }

    .reports-hero h1 {
        margin-bottom: 0.65rem;
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -0.03em;
    }

    .reports-hero p {
        max-width: 780px;
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 1rem;
        font-size: 0.96rem;
    }

    .hero-chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
    }

    .hero-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.6rem 0.9rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        color: #fff;
        font-size: 0.85rem;
        font-weight: 600;
        backdrop-filter: blur(12px);
    }

    .hero-chip i {
        color: #fdba74;
    }

    .filter-panel,
    .report-surface {
        border: 0;
        border-radius: 24px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    }

    .filter-panel .card-header,
    .report-surface .card-header {
        border: 0;
        background: transparent;
        padding: 1.2rem 1.35rem 0.35rem;
    }

    .filter-panel .card-header h5,
    .report-surface .card-header h5 {
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .filter-panel .card-body,
    .report-surface .card-body {
        padding: 1.35rem;
    }

    .filter-panel .form-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .filter-panel .form-control,
    .filter-panel .form-select {
        min-height: 48px;
        border-radius: 14px;
        border-color: #e5e7eb;
        box-shadow: none;
    }

    .section-kicker {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .section-kicker h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #111827;
    }

    .section-kicker span {
        color: #6b7280;
        font-size: 0.84rem;
        font-weight: 600;
    }

    .metric-card {
        position: relative;
        overflow: hidden;
        min-height: 165px;
        padding: 1.25rem;
        border-radius: 24px;
        border: 1px solid rgba(249, 115, 22, 0.08);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(255, 247, 237, 0.98) 100%);
        box-shadow: 0 16px 32px rgba(15, 23, 42, 0.06);
        transition: transform 0.22s ease, box-shadow 0.22s ease;
    }

    .metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 22px 36px rgba(15, 23, 42, 0.1);
    }

    .metric-card::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #fb923c 0%, #f97316 55%, #ea580c 100%);
    }

    .metric-card .text-muted.small {
        font-size: 0.74rem !important;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9ca3af !important;
    }

    .metric-card h3 {
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #111827;
    }

    .metric-card .icon {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        color: #fff;
    }

    .metric-card .icon.primary { background: linear-gradient(135deg, #f97316, #fb923c); }
    .metric-card .icon.success { background: linear-gradient(135deg, #10b981, #34d399); }
    .metric-card .icon.info { background: linear-gradient(135deg, #0f766e, #14b8a6); }
    .metric-card .icon.warning { background: linear-gradient(135deg, #f59e0b, #f97316); }
    .metric-card .icon.danger { background: linear-gradient(135deg, #ef4444, #fb7185); }

    .report-list-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.9rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .report-list-row:first-child {
        padding-top: 0;
    }

    .report-list-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .report-list-row strong {
        color: #111827;
        font-weight: 800;
    }

    .report-table thead th {
        border-bottom: 1px solid #f1f5f9;
        color: #6b7280;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 800;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    .report-table tbody td {
        vertical-align: middle;
        padding-top: 0.95rem;
        padding-bottom: 0.95rem;
    }

    #salesTrendChart,
    #statusBreakdownChart {
        min-height: 280px;
    }

    @media (max-width: 991px) {
        .reports-hero {
            padding: 1.45rem;
        }

        .reports-hero h1 {
            font-size: 1.55rem;
        }

        .section-kicker {
            flex-direction: column;
            align-items: flex-start;
        }

        #salesTrendChart,
        #statusBreakdownChart {
            min-height: 240px;
        }
    }
</style>
@endsection

@section('content')
@php
    $currencySymbol = \App\Models\AppSetting::getValue('currency_symbol', '₹');
    $statusLabels = \App\Models\Order::getStatuses();
    $paymentStatusLabels = \App\Models\Order::getPaymentStatuses();
@endphp

<div class="reports-shell">
    <div class="reports-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-4 position-relative" style="z-index: 1;">
            <div>
                <h1>Growth, Earnings and Dispatch Intelligence</h1>
                <p>Track platform revenue, restaurant momentum, payout exposure, driver batching, and real order behavior across your selected reporting window.</p>
                <div class="hero-chip-row">
                    <div class="hero-chip"><i class="fas fa-calendar-alt"></i>{{ $startDate->format('d M Y') }} - {{ $endDate->format('d M Y') }}</div>
                    <div class="hero-chip"><i class="fas fa-receipt"></i>{{ number_format((int) ($summary->total_orders ?? 0)) }} total orders</div>
                    <div class="hero-chip"><i class="fas fa-bolt"></i>{{ number_format((float) ($dispatchMetrics['acceptance_rate'] ?? 0), 1) }}% dispatch acceptance</div>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.reports.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-light">
                    <i class="fas fa-download me-2"></i>Export CSV
                </a>
            </div>
        </div>
    </div>

    <div class="table-card filter-panel">
        <div class="card-header">
            <div class="section-kicker w-100">
                <h4>Filter Window</h4>
                <span>Refine the report by date, restaurant, order status, and payment state.</span>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reports.index') }}">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Restaurant</label>
                        <select name="restaurant_id" class="form-select">
                            <option value="">All Restaurants</option>
                            @foreach($restaurantOptions as $restaurant)
                                <option value="{{ $restaurant->id }}" @selected((string) request('restaurant_id') === (string) $restaurant->id)>
                                    {{ $restaurant->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Order Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            @foreach($statusLabels as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected(request('status') === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select">
                            <option value="">All Payments</option>
                            @foreach($paymentStatusLabels as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected(request('payment_status') === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-9 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                        <a href="{{ route('admin.reports.index') }}" class="btn btn-light border">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="section-kicker">
        <h4>Revenue Overview</h4>
        <span>Core sales, fee, and partner earning performance for this reporting window</span>
    </div>
    <div class="row g-4">
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Gross Sales</div>
                        <h3 class="mb-1">{{ $currencySymbol }}{{ number_format((float) ($summary->gross_sales ?? 0), App\Models\AppSetting::currencyDecimals()) }}</h3>
                        <div class="small text-muted">{{ $summary->total_orders ?? 0 }} total orders</div>
                    </div>
                    <div class="icon primary"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Admin Revenue</div>
                        <h3 class="mb-1">{{ $currencySymbol }}{{ number_format((float) ($summary->admin_commission_total ?? 0), App\Models\AppSetting::currencyDecimals()) }}</h3>
                        <div class="small text-muted">Commission, platform fee and delivery-side cut</div>
                    </div>
                    <div class="icon success"><i class="fas fa-percent"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Restaurant Earnings</div>
                        <h3 class="mb-1">{{ $currencySymbol }}{{ number_format((float) ($summary->restaurant_earning_total ?? 0), App\Models\AppSetting::currencyDecimals()) }}</h3>
                        <div class="small text-muted">Partner settlement side</div>
                    </div>
                    <div class="icon info"><i class="fas fa-store"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Driver Earnings</div>
                        <h3 class="mb-1">{{ $currencySymbol }}{{ number_format((float) ($summary->driver_earning_total ?? 0), App\Models\AppSetting::currencyDecimals()) }}</h3>
                        <div class="small text-muted">Delivery-side payouts</div>
                    </div>
                    <div class="icon warning"><i class="fas fa-truck"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Delivery Charges</div>
                        <h3 class="mb-1">{{ $currencySymbol }}{{ number_format((float) ($summary->delivery_fee_total ?? 0), App\Models\AppSetting::currencyDecimals()) }}</h3>
                        <div class="small text-muted">Distance-based collections</div>
                    </div>
                    <div class="icon primary"><i class="fas fa-route"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Tax Collected</div>
                        <h3 class="mb-1">{{ $currencySymbol }}{{ number_format((float) ($summary->tax_total ?? 0), App\Models\AppSetting::currencyDecimals()) }}</h3>
                        <div class="small text-muted">Checkout tax totals</div>
                    </div>
                    <div class="icon danger"><i class="fas fa-file-invoice-dollar"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Discount Given</div>
                        <h3 class="mb-1">{{ $currencySymbol }}{{ number_format((float) ($summary->discount_total ?? 0), App\Models\AppSetting::currencyDecimals()) }}</h3>
                        <div class="small text-muted">Coupon and promo impact</div>
                    </div>
                    <div class="icon warning"><i class="fas fa-tags"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Refunds</div>
                        <h3 class="mb-1">{{ $currencySymbol }}{{ number_format((float) ($summary->refund_total ?? 0), App\Models\AppSetting::currencyDecimals()) }}</h3>
                        <div class="small text-muted">{{ $summary->cancelled_orders ?? 0 }} cancelled orders</div>
                    </div>
                    <div class="icon danger"><i class="fas fa-rotate-left"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-kicker">
        <h4>Dispatch and Fulfilment</h4>
        <span>Assignment quality, batching behavior, and route efficiency signals</span>
    </div>
    <div class="row g-4">
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Assigned Orders</div>
                        <h3 class="mb-1">{{ $dispatchMetrics['assigned_orders'] }}</h3>
                        <div class="small text-muted">Driver assignment attempts tracked</div>
                    </div>
                    <div class="icon info"><i class="fas fa-user-check"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Acceptance Rate</div>
                        <h3 class="mb-1">{{ number_format((float) $dispatchMetrics['acceptance_rate'], 1) }}%</h3>
                        <div class="small text-muted">{{ $dispatchMetrics['accepted_orders'] }} accepted orders</div>
                    </div>
                    <div class="icon success"><i class="fas fa-thumbs-up"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Avg Acceptance Time</div>
                        <h3 class="mb-1">{{ number_format((float) $dispatchMetrics['avg_acceptance_minutes'], 1) }} min</h3>
                        <div class="small text-muted">Avg attempts {{ number_format((float) $dispatchMetrics['avg_assignment_attempts'], 2) }}</div>
                    </div>
                    <div class="icon warning"><i class="fas fa-stopwatch"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="stat-card metric-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Route-Matched Batches</div>
                        <h3 class="mb-1">{{ $dispatchMetrics['route_matched_batches'] }}</h3>
                        <div class="small text-muted">{{ $dispatchMetrics['route_matched_orders'] }} orders inside {{ number_format((float) $dispatchMetrics['route_match_radius_km'], 1) }} km radius</div>
                    </div>
                    <div class="icon primary"><i class="fas fa-route"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-card report-surface h-100">
                <div class="card-header">
                    <div class="section-kicker w-100">
                        <h4>Daily Sales Trend</h4>
                        <span>Sales amount and order volume across the selected window</span>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="salesTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="table-card report-surface h-100">
                <div class="card-header">
                    <div class="section-kicker w-100">
                        <h4>Order Status Mix</h4>
                        <span>Delivered, cancelled, and in-flight composition</span>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="statusBreakdownChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="table-card report-surface h-100">
                <div class="card-header">
                    <div class="section-kicker w-100">
                        <h4>Dispatch Health</h4>
                        <span>Assignment quality indicators</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="report-list-row"><span>Assigned Orders</span><strong>{{ $dispatchMetrics['assigned_orders'] }}</strong></div>
                    <div class="report-list-row"><span>Accepted Orders</span><strong>{{ $dispatchMetrics['accepted_orders'] }}</strong></div>
                    <div class="report-list-row"><span>Acceptance Rate</span><strong>{{ number_format((float) $dispatchMetrics['acceptance_rate'], 1) }}%</strong></div>
                    <div class="report-list-row"><span>Auto-cancelled Unassigned</span><strong>{{ $dispatchMetrics['auto_cancelled_unassigned'] }}</strong></div>
                    <div class="report-list-row"><span>Avg Assignment Attempts</span><strong>{{ number_format((float) $dispatchMetrics['avg_assignment_attempts'], 2) }}</strong></div>
                    <div class="report-list-row"><span>Route Match Radius</span><strong>{{ number_format((float) $dispatchMetrics['route_match_radius_km'], 1) }} km</strong></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="table-card report-surface h-100">
                <div class="card-header">
                    <div class="section-kicker w-100">
                        <h4>Platform Snapshot</h4>
                        <span>User base and order economics</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="report-list-row"><span>Total Customers</span><strong>{{ $userCounts['customers'] }}</strong></div>
                    <div class="report-list-row"><span>Total Drivers</span><strong>{{ $userCounts['drivers'] }}</strong></div>
                    <div class="report-list-row"><span>Restaurant Owners</span><strong>{{ $userCounts['restaurant_owners'] }}</strong></div>
                    <div class="report-list-row"><span>Restaurant Staff</span><strong>{{ $userCounts['restaurant_staff'] }}</strong></div>
                    <div class="report-list-row"><span>Successful Payments</span><strong>{{ $summary->successful_payments ?? 0 }}</strong></div>
                    <div class="report-list-row"><span>Average Order Value</span><strong>{{ $currencySymbol }}{{ number_format((float) ($summary->avg_order_value ?? 0), App\Models\AppSetting::currencyDecimals()) }}</strong></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="table-card report-surface h-100">
                <div class="card-header">
                    <div class="section-kicker w-100">
                        <h4>Payout Overview</h4>
                        <span>Settlement and processing exposure</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="report-list-row"><span>Total Payouts</span><strong>{{ $payoutSummary->total_payouts ?? 0 }}</strong></div>
                    <div class="report-list-row"><span>Payout Amount</span><strong>{{ $currencySymbol }}{{ number_format((float) ($payoutSummary->total_amount ?? 0), App\Models\AppSetting::currencyDecimals()) }}</strong></div>
                    <div class="report-list-row"><span>Processed</span><strong>{{ $payoutSummary->processed_count ?? 0 }}</strong></div>
                    <div class="report-list-row"><span>Pending</span><strong>{{ $payoutSummary->pending_count ?? 0 }}</strong></div>
                    <div class="report-list-row"><span>Failed</span><strong>{{ $payoutSummary->failed_count ?? 0 }}</strong></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="table-card report-surface h-100">
                <div class="card-header">
                    <div class="section-kicker w-100">
                        <h4>Payment Method Mix</h4>
                        <span>Checkout channel preference</span>
                    </div>
                </div>
                <div class="card-body">
                    @forelse($paymentBreakdown as $method => $count)
                        <div class="report-list-row">
                            <span>{{ strtoupper($method ?: 'N/A') }}</span>
                            <strong>{{ $count }}</strong>
                        </div>
                    @empty
                        <div class="text-muted">No payment data found for the selected range.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="table-card report-surface h-100">
                <div class="card-header">
                    <div class="section-kicker w-100">
                        <h4>Top Batched Drivers</h4>
                        <span>Strongest grouped-route operators</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 report-table">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Route-Matched Orders</th>
                                <th>Batch Groups</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dispatchMetrics['top_batched_drivers'] as $driverMetric)
                                <tr>
                                    <td>{{ $driverMetric['driver_name'] }}</td>
                                    <td>{{ $driverMetric['route_matched_orders'] }}</td>
                                    <td>{{ $driverMetric['bundles'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No route-matched batches found for the selected range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="table-card report-surface h-100">
                <div class="card-header">
                    <div class="section-kicker w-100">
                        <h4>Top Restaurants</h4>
                        <span>Restaurants leading this sales window</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 report-table">
                        <thead>
                            <tr>
                                <th>Restaurant</th>
                                <th>Orders</th>
                                <th>Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topRestaurants as $restaurant)
                                <tr>
                                    <td>{{ $restaurant->name }}</td>
                                    <td>{{ $restaurant->orders_count }}</td>
                                    <td>{{ $currencySymbol }}{{ number_format((float) $restaurant->sales_total, App\Models\AppSetting::currencyDecimals()) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No restaurants found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="table-card report-surface h-100">
                <div class="card-header">
                    <div class="section-kicker w-100">
                        <h4>Top Drivers</h4>
                        <span>Highest delivery contribution in the range</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 report-table">
                        <thead>
                            <tr>
                                <th>Driver</th>
                                <th>Orders</th>
                                <th>Earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topDrivers as $driver)
                                <tr>
                                    <td>{{ $driver->name }}</td>
                                    <td>{{ $driver->orders_count }}</td>
                                    <td>{{ $currencySymbol }}{{ number_format((float) $driver->earnings_total, App\Models\AppSetting::currencyDecimals()) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No driver data found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card report-surface">
        <div class="card-header">
            <div class="section-kicker w-100">
                <h4>Recent Orders</h4>
                <span>Detailed operational records for the active report selection</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 report-table">
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
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $order->order_number }}</div>
                                <div class="small text-muted">{{ strtoupper($order->payment_method ?? 'N/A') }}</div>
                            </td>
                            <td>{{ $order->restaurant?->name ?? 'N/A' }}</td>
                            <td>{{ $order->customer?->name ?? $order->customer_name ?? 'N/A' }}</td>
                            <td><span class="badge badge-{{ $order->status }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></td>
                            <td><span class="badge badge-{{ $order->payment_status === 'success' ? 'success' : ($order->payment_status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst($order->payment_status) }}</span></td>
                            <td>{{ $currencySymbol }}{{ number_format((float) ($order->admin_commission ?? 0), App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td class="fw-semibold">{{ $currencySymbol }}{{ number_format((float) $order->total, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td>{{ optional($order->created_at)->format('d M Y, h:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">No orders found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">
            {{ $orders->links() }}
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const salesTrendLabels = @json($dailyPerformance->pluck('report_date')->map(fn ($date) => \Carbon\Carbon::parse($date)->format('d M'))->values());
    const salesTrendOrders = @json($dailyPerformance->pluck('orders_count')->map(fn ($value) => (int) $value)->values());
    const salesTrendAmounts = @json($dailyPerformance->pluck('sales_total')->map(fn ($value) => round((float) $value, 2))->values());
    const statusLabels = @json(collect($statusBreakdown)->keys()->map(fn ($label) => ucfirst(str_replace('_', ' ', $label)))->values());
    const statusValues = @json(collect($statusBreakdown)->values()->map(fn ($value) => (int) $value)->values());

    const salesTrendCtx = document.getElementById('salesTrendChart');
    if (salesTrendCtx) {
        new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: salesTrendLabels,
                datasets: [
                    {
                        label: 'Sales',
                        data: salesTrendAmounts,
                        borderColor: '#F97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.16)',
                        tension: 0.35,
                        fill: true,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Orders',
                        data: salesTrendOrders,
                        borderColor: '#0F766E',
                        backgroundColor: 'rgba(15, 118, 110, 0.12)',
                        tension: 0.35,
                        fill: false,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });
    }

    const statusCtx = document.getElementById('statusBreakdownChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: ['#F97316', '#0F766E', '#F59E0B', '#2563EB', '#EF4444', '#14B8A6', '#FB7185', '#7C3AED'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
</script>
@endsection
