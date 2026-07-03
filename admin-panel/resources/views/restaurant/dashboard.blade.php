{{-- resources/views/restaurant/dashboard.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Dashboard')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8'));
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    if (str_contains($currencySymbol, '{{') || str_contains($currencySymbol, 'currencySymbol')) {
        $currencySymbol = html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8');
    }
@endphp

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>Welcome back, {{ auth()->user()->name }}!</h1>
            <p>{{ $selectedScope === 'all' ? 'All restaurants dashboard summary.' : $restaurant->name . ' dashboard summary.' }}</p>
            @if($selectedScope === 'all')
                <span class="badge bg-primary-subtle text-primary rounded-3">
                    <i class="fas fa-layer-group me-1"></i> Viewing all restaurants
                </span>
            @endif
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <div class="text-muted small">{{ now()->format('l, F d, Y') }}</div>
                <div class="text-muted small">{{ now()->format('h:i A') }}</div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="icon success">
                    <i class="fas fa-indian-rupee-sign"></i>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light rounded-3" data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                </div>
            </div>
            <h3 class="text-muted mb-2" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Total Revenue</h3>
            <div class="d-flex align-items-baseline gap-2 mb-2">
                <span class="h2 mb-0 fw-bold">{{ $currencySymbol }}{{ number_format($totalRevenue, $currencyDecimals) }}</span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="badge bg-success bg-opacity-10 text-success">
                    {{ $currencySymbol }}{{ number_format($todayRevenue, $currencyDecimals) }}
                </span>
                <small class="text-muted">today delivered revenue</small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="icon primary">
                    <i class="fas fa-shopping-bag"></i>
                </div>
            </div>
            <h3 class="text-muted mb-2" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Total Orders</h3>
            <div class="d-flex align-items-baseline gap-2 mb-2">
                <span class="h2 mb-0 fw-bold">{{ $totalOrders }}</span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="badge bg-primary bg-opacity-10 text-primary">
                    {{ $todayOrders }} today
                </span>
                <small class="text-muted">{{ $deliveredOrdersCount }} delivered, {{ $pendingOrders }} active</small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="icon info">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <h3 class="text-muted mb-2" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Total Customers</h3>
            <div class="d-flex align-items-baseline gap-2 mb-2">
                <span class="h2 mb-0 fw-bold">{{ $totalCustomers }}</span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <span class="badge bg-success bg-opacity-10 text-success">
                    {{ $todayOrders }} orders
                </span>
                <small class="text-muted">created today</small>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="icon warning">
                    <i class="fas fa-star"></i>
                </div>
            </div>
            <h3 class="text-muted mb-2" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Average Rating</h3>
            <div class="d-flex align-items-baseline gap-2 mb-2">
                <span class="h2 mb-0 fw-bold">{{ number_format($avgRating, 1) }}</span>
                <small class="text-muted">/ 5.0</small>
            </div>
            <div class="text-warning">
                @for($i = 1; $i <= 5; $i++)
                    @if($avgRating >= $i)
                        <i class="fas fa-star"></i>
                    @elseif($avgRating >= $i - 0.5)
                        <i class="fas fa-star-half-alt"></i>
                    @else
                        <i class="far fa-star"></i>
                    @endif
                @endfor
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="table-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold">Revenue Trend</h5>
                    <small class="text-muted">Last 14 days, delivered orders</small>
                </div>
            </div>
            <div class="p-4">
                <canvas id="dashboardRevenueTrend" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="row g-4">
            <div class="col-12">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="icon info"><i class="fas fa-clock"></i></div>
                    </div>
                    <h3 class="text-muted mb-2" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Best Order Time</h3>
                    <div class="h3 fw-bold mb-1">{{ $bestOrderTime['label'] }}</div>
                    <div class="text-muted small">
                        {{ $bestOrderTime['orders'] }} orders · {{ $currencySymbol }}{{ number_format($bestOrderTime['revenue'], $currencyDecimals) }} delivered revenue
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="icon success"><i class="fas fa-wallet"></i></div>
                    </div>
                    <h3 class="text-muted mb-2" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Payouts</h3>
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <div>
                            <div class="small text-muted">Last payout</div>
                            <div class="fw-bold">
                                @if($payoutSummary['last'])
                                    {{ $currencySymbol }}{{ number_format($payoutSummary['last']->amount, $currencyDecimals) }}
                                @else
                                    No payout yet
                                @endif
                            </div>
                            @if($payoutSummary['last'])
                                <div class="small text-muted">{{ ucfirst($payoutSummary['last']->status) }} · {{ $payoutSummary['last']->created_at->format('d M Y') }}</div>
                            @endif
                        </div>
                        <div class="text-end">
                            <div class="small text-muted">Upcoming</div>
                            <div class="fw-bold">{{ $payoutSummary['next_date']->format('d M Y') }}</div>
                            <div class="small text-muted">{{ $payoutSummary['frequency'] }} schedule</div>
                        </div>
                    </div>
                    <div class="badge bg-warning bg-opacity-10 text-warning">
                        {{ $payoutSummary['pending_count'] }} pending · {{ $currencySymbol }}{{ number_format($payoutSummary['pending_amount'], $currencyDecimals) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold">Restaurant-wise Revenue Bifurcation</h5>
            <small class="text-muted">Delivered order revenue split</small>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th style="padding-left: 24px;">Restaurant</th>
                    <th>Delivered Orders</th>
                    <th>Revenue</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse($restaurantBreakdown as $row)
                    <tr>
                        <td style="padding-left: 24px;">
                            <div class="fw-bold">{{ $row['name'] }}</div>
                            <small class="text-muted">{{ $row['city'] ?: 'Location not set' }}</small>
                        </td>
                        <td>{{ number_format($row['orders']) }}</td>
                        <td class="fw-bold text-success">{{ $currencySymbol }}{{ number_format($row['revenue'], $currencyDecimals) }}</td>
                        <td style="min-width: 180px;">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-fill" style="height: 8px;">
                                    <div class="progress-bar" style="width: {{ $row['share'] }}%;"></div>
                                </div>
                                <span class="small fw-semibold">{{ number_format($row['share'], 1) }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No delivered revenue yet</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Orders & Popular Items -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Recent Orders</h5>
                <a href="{{ route('restaurant.orders.index') }}" class="btn btn-outline-primary btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th style="padding-left: 24px;">Order ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th style="padding-right: 24px;">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                        <tr>
                            <td style="padding-left: 24px;">
                                <a href="{{ route('restaurant.orders.show', $order->id) }}" class="fw-bold text-decoration-none">
                                    #{{ $order->id }}
                                </a>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" 
                                         style="width: 32px; height: 32px; font-size: 12px; font-weight: 600; color: var(--primary);">
                                        {{ strtoupper(substr($order->customer->name ?? 'G', 0, 1)) }}
                                    </div>
                                    <span>{{ $order->customer->name ?? 'Guest' }}</span>
                                </div>
                            </td>
                            @php
                                $itemsCount = $order->order_items_count;
                                if (!$itemsCount && is_array($order->items)) {
                                    $itemsCount = collect($order->items)->sum(fn ($item) => (int) ($item['quantity'] ?? $item['qty'] ?? 1));
                                }
                            @endphp
                            <td>{{ $itemsCount }} items</td>
                            <td><strong>{{ $currencySymbol }}{{ number_format($order->total, $currencyDecimals) }}</strong></td>
                            <td>
                                <span class="badge badge-{{ $order->status }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                            <td style="padding-right: 24px;">
                                <small class="text-muted">{{ $order->created_at->diffForHumans() }}</small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                No orders yet
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="table-card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Popular Items</h5>
            </div>
            <div class="p-4">
                @forelse($popularItems as $item)
                <div class="d-flex align-items-center gap-3 mb-3 p-3 bg-light rounded-3">
                    <div class="rounded-3 bg-white d-flex align-items-center justify-content-center" 
                         style="width: 48px; height: 48px; font-size: 18px; color: var(--primary);">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="flex-fill">
                        <h6 class="mb-1 fw-semibold">{{ $item->name }}</h6>
                        <small class="text-muted">{{ $item->total_orders }} orders</small>
                    </div>
                    <div class="text-end">
                        <strong>{{ $currencySymbol }}{{ number_format($item->price, $currencyDecimals) }}</strong>
                    </div>
                </div>
                @empty
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-utensils fa-2x mb-2 d-block opacity-50"></i>
                    No items yet
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const trendCanvas = document.getElementById('dashboardRevenueTrend');
    if (!trendCanvas || typeof Chart === 'undefined') {
        return;
    }

    const trend = @json($revenueTrend);
    new Chart(trendCanvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: trend.labels,
            datasets: [{
                label: 'Revenue',
                data: trend.revenue,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.12)',
                fill: true,
                tension: 0.35,
                yAxisID: 'y'
            }, {
                label: 'Orders',
                data: trend.orders,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.12)',
                tension: 0.35,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
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
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: {
                        callback: function(value) { return window.formatCurrency(value); }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { precision: 0 }
                }
            }
        }
    });
});
</script>
@endsection

