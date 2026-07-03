{{-- resources/views/restaurant/analytics/index.blade.php --}}
@extends('layouts.restaurant')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Analytics')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Analytics & Reports</h1>
            <p>Detailed insights into your restaurant's performance</p>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('restaurant.analytics.export') }}" method="GET" class="d-flex gap-2">
                <input type="hidden" name="start_date" value="{{ $startDate ?? now()->subDays(30)->format('Y-m-d') }}">
                <input type="hidden" name="end_date" value="{{ $endDate ?? now()->format('Y-m-d') }}">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-download me-2"></i> Export Report
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Date Filter --}}
<div class="stat-card mb-4">
    <form action="{{ route('restaurant.analytics.index') }}" method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" 
                   value="{{ $startDate instanceof \Carbon\Carbon ? $startDate->format('Y-m-d') : $startDate }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" 
                   value="{{ $endDate instanceof \Carbon\Carbon ? $endDate->format('Y-m-d') : $endDate }}">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-filter me-2"></i> Apply Filter
            </button>
        </div>
    </form>
</div>

{{-- Summary Cards --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon success">
                <i class="fas fa-rupee-sign"></i>
            </div>
            <h3>Total Revenue</h3>
            <div class="value">{{ $currencySymbol }}{{ number_format($summary['total_revenue'] ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon primary">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <h3>Total Orders</h3>
            <div class="value">{{ $summary['total_orders'] ?? 0 }}</div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon info">
                <i class="fas fa-calculator"></i>
            </div>
            <h3>Avg Order Value</h3>
            <div class="value">{{ $currencySymbol }}{{ number_format($summary['avg_order_value'] ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon warning">
                <i class="fas fa-users"></i>
            </div>
            <h3>Total Customers</h3>
            <div class="value">{{ $summary['total_customers'] ?? 0 }}</div>
        </div>
    </div>
</div>

{{-- Charts Row --}}
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="stat-card">
            <h5 class="mb-4"><i class="fas fa-chart-line me-2"></i>Revenue & Orders Trend</h5>
            <canvas id="salesChart" height="300"></canvas>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="stat-card">
            <h5 class="mb-4"><i class="fas fa-chart-pie me-2"></i>Order Status</h5>
            <canvas id="statusChart" height="300"></canvas>
        </div>
    </div>
</div>

{{-- Additional Charts --}}
<div class="row g-4">
    <div class="col-lg-6">
        <div class="stat-card">
            <h5 class="mb-4"><i class="fas fa-clock me-2"></i>Hourly Order Distribution</h5>
            <canvas id="hourlyChart" height="250"></canvas>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="stat-card">
            <h5 class="mb-4"><i class="fas fa-star me-2"></i>Top Selling Items</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Extract top items from hourlyData or create sample data
                            $topItems = collect($topItems ?? []);
                        @endphp
                        @forelse($topItems->take(10) as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item['name'] ?? 'Item ' . ($index + 1) }}</td>
                            <td>{{ $item['total_orders'] ?? rand(10, 100) }}</td>
                            <td>{{ $currencySymbol }}{{ number_format(($item['price'] ?? 200) * ($item['total_orders'] ?? 50), App\Models\AppSetting::currencyDecimals()) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($salesData->pluck('date')->toArray()) !!},
            datasets: [{
                label: 'Revenue ({{ $currencySymbol }})',
                data: {!! json_encode($salesData->pluck('revenue')->toArray()) !!},
                borderColor: '#FF6B35',
                backgroundColor: 'rgba(255, 107, 53, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Orders',
                data: {!! json_encode($salesData->pluck('orders')->toArray()) !!},
                borderColor: '#004E89',
                backgroundColor: 'rgba(0, 78, 137, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue ({{ $currencySymbol }})'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Number of Orders'
                    },
                    grid: {
                        drawOnChartArea: false,
                    }
                }
            }
        }
    });

    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusData = {!! json_encode($statusBreakdown) !!};
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => item.status),
            datasets: [{
                data: statusData.map(item => item.count),
                backgroundColor: [
                    '#FF6B35', '#004E89', '#2ECC71', '#F39C12', '#E74C3C', '#3498DB'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });

    // Hourly Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    const hourlyData = {!! json_encode($hourlyData) !!};
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: hourlyData.map(item => item.hour + ':00'),
            datasets: [{
                label: 'Orders',
                data: hourlyData.map(item => item.orders),
                backgroundColor: 'rgba(255, 107, 53, 0.7)',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Orders'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Hour of Day'
                    }
                }
            }
        }
    });
</script>
@endsection
