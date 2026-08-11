{{-- resources/views/restaurant/analytics/index.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Analytics')

@section('styles')
<style>
    .analytics-shell {
        display: grid;
        gap: 18px;
        max-width: 100%;
        min-width: 0;
    }

    .analytics-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        align-items: end;
        padding: 18px;
    }

    .analytics-filter-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(160px, 220px)) auto;
        gap: 12px;
        align-items: end;
    }

    .analytics-kpi-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(180px, 1fr));
        gap: 14px;
    }

    .analytics-kpi {
        min-height: 126px;
        padding: 18px;
        border-radius: 22px;
    }

    .analytics-kpi .icon {
        width: 44px;
        height: 44px;
        border-radius: 16px;
        font-size: 17px;
    }

    .analytics-kpi .icon.danger {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    .analytics-kpi-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        margin-top: 14px;
    }

    .analytics-kpi-value {
        color: #0f172a;
        font-size: 25px;
        font-weight: 950;
        letter-spacing: -0.03em;
        margin-top: 3px;
        overflow-wrap: anywhere;
    }

    .analytics-kpi-note {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        margin-top: 6px;
    }

    .analytics-grid {
        display: grid;
        gap: 16px;
        min-width: 0;
    }

    .analytics-grid-main {
        grid-template-columns: minmax(0, 1.45fr) minmax(300px, .72fr);
    }

    .analytics-grid-secondary {
        grid-template-columns: minmax(0, .96fr) minmax(340px, 1.04fr);
    }

    .analytics-panel {
        min-width: 0;
        overflow: hidden;
    }

    .analytics-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        padding: 18px 20px 8px;
    }

    .analytics-panel-title {
        color: #0f172a;
        font-size: 17px;
        font-weight: 950;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .analytics-panel-subtitle {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        margin-top: 2px;
    }

    .analytics-chart-shell {
        position: relative;
        height: 320px;
        padding: 10px 18px 18px;
    }

    .analytics-chart-shell.compact {
        height: 286px;
    }

    .analytics-chart-shell.donut {
        height: 286px;
        max-width: 360px;
        margin: 0 auto;
    }

    .analytics-chart-shell canvas {
        width: 100% !important;
        height: 100% !important;
        display: block;
    }

    .analytics-empty {
        display: grid;
        place-items: center;
        min-height: 220px;
        color: #64748b;
        font-weight: 700;
        text-align: center;
        padding: 28px;
    }

    .status-legend {
        display: grid;
        gap: 8px;
        padding: 0 18px 18px;
    }

    .status-legend-row,
    .top-item-row {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        padding: 12px;
        border: 1px solid rgba(226, 232, 240, .82);
        border-radius: 16px;
        background: rgba(255, 255, 255, .84);
    }

    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--dot);
    }

    .top-items-list {
        display: grid;
        gap: 10px;
        padding: 0 14px 16px;
    }

    .top-item-rank {
        position: absolute;
        right: -6px;
        bottom: -6px;
        width: 22px;
        height: 22px;
        border: 2px solid #fff;
        border-radius: 999px;
        display: grid;
        place-items: center;
        color: #fff;
        font-size: 11px;
        font-weight: 900;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        box-shadow: 0 8px 18px rgba(15, 23, 42, .16);
    }

    .top-item-image {
        position: relative;
        width: 36px;
        height: 36px;
        border-radius: 12px;
        overflow: visible;
        flex-shrink: 0;
    }

    .top-item-thumb {
        width: 100%;
        height: 100%;
        border-radius: inherit;
        border: 1px solid rgba(148, 163, 184, .24);
        background: #f8fafc;
        display: grid;
        place-items: center;
        overflow: hidden;
        color: #94a3b8;
    }

    .top-item-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .promotion-performance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        padding: 0 14px 16px;
    }

    .promotion-performance-metric {
        border: 1px solid rgba(226, 232, 240, .82);
        border-radius: 16px;
        padding: 14px;
        background: rgba(255, 255, 255, .86);
    }

    .promotion-performance-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .promotion-performance-value {
        color: #0f172a;
        font-size: 22px;
        font-weight: 900;
        margin-top: 4px;
    }

    .promotion-performance-list {
        display: grid;
        gap: 10px;
        padding: 0 14px 16px;
    }

    .promotion-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        padding: 12px;
        border: 1px solid rgba(226, 232, 240, .82);
        border-radius: 16px;
        background: rgba(255, 255, 255, .84);
    }

    @media (max-width: 1500px) {
        .analytics-kpi-grid {
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        }

        .analytics-grid-main,
        .analytics-grid-secondary {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .analytics-toolbar,
        .analytics-filter-grid {
            grid-template-columns: 1fr;
        }

        .analytics-toolbar .btn,
        .analytics-filter-grid .btn {
            width: 100%;
        }

        .analytics-chart-shell,
        .analytics-chart-shell.compact,
        .analytics-chart-shell.donut {
            height: 250px;
        }
    }
</style>
@endsection

@section('content')
<div class="analytics-shell">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1>Analytics & Reports</h1>
                <p>Detailed insights into restaurant performance.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <form action="{{ route('restaurant.analytics.export') }}" method="GET" class="d-flex gap-2">
                    <input type="hidden" name="start_date" value="{{ $startDateValue }}">
                    <input type="hidden" name="end_date" value="{{ $endDateValue }}">
                    <button type="submit" class="btn btn-outline-primary rounded-3">
                        <i class="fas fa-download me-2"></i> Export Report
                    </button>
                </form>
                <form action="{{ route('restaurant.analytics.promo-spend.export') }}" method="GET" class="d-flex gap-2">
                    <input type="hidden" name="start_date" value="{{ $startDateValue }}">
                    <input type="hidden" name="end_date" value="{{ $endDateValue }}">
                    <button type="submit" class="btn btn-outline-primary rounded-3">
                        <i class="fas fa-tags me-2"></i> Promo Spend CSV
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="table-card analytics-toolbar">
        <form action="{{ route('restaurant.analytics.index') }}" method="GET" class="analytics-filter-grid">
            <div>
                <label class="form-label fw-semibold">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="{{ $startDateValue }}">
            </div>
            <div>
                <label class="form-label fw-semibold">End Date</label>
                <input type="date" name="end_date" class="form-control" value="{{ $endDateValue }}">
            </div>
            <button type="submit" class="btn btn-primary rounded-3">
                <i class="fas fa-filter me-2"></i> Apply
            </button>
        </form>
        <div class="text-end">
            <div class="small text-muted fw-semibold">Range</div>
            <div class="fw-bold text-dark">{{ $dateRangeLabel }}</div>
        </div>
    </div>

    <section class="analytics-kpi-grid">
        @foreach($kpiCards as $card)
            <div class="stat-card analytics-kpi">
                <div class="icon {{ $card['tone'] }}"><i class="fas fa-{{ $card['icon'] }}"></i></div>
                <div class="analytics-kpi-label">{{ $card['label'] }}</div>
                <div class="analytics-kpi-value">{{ $card['value'] }}</div>
                <div class="analytics-kpi-note">{{ $card['note'] }}</div>
            </div>
        @endforeach
    </section>

    <section class="table-card analytics-panel">
        <div class="analytics-panel-head">
            <div>
                <h3 class="analytics-panel-title">Promotion Performance</h3>
                <div class="analytics-panel-subtitle">Coupon usage and discount impact for the selected range.</div>
            </div>
        </div>
        <div class="promotion-performance-grid">
            <div class="promotion-performance-metric">
                <div class="promotion-performance-label">Active Promos</div>
                <div class="promotion-performance-value">{{ number_format($promotionPerformance['active_promotions'] ?? 0) }}</div>
            </div>
            <div class="promotion-performance-metric">
                <div class="promotion-performance-label">Promo Orders</div>
                <div class="promotion-performance-value">{{ number_format($promotionPerformance['coupon_orders'] ?? 0) }}</div>
            </div>
            <div class="promotion-performance-metric">
                <div class="promotion-performance-label">Discount Given</div>
                <div class="promotion-performance-value">{{ $promotionPerformance['discount_given_label'] ?? ($currencySymbol . number_format(0, $currencyDecimals)) }}</div>
            </div>
            <div class="promotion-performance-metric">
                <div class="promotion-performance-label">Avg Discount</div>
                <div class="promotion-performance-value">{{ $promotionPerformance['avg_discount_label'] ?? ($currencySymbol . number_format(0, $currencyDecimals)) }}</div>
            </div>
            <div class="promotion-performance-metric">
                <div class="promotion-performance-label">Restaurant Funded</div>
                <div class="promotion-performance-value">{{ $promotionPerformance['restaurant_funded_spend_label'] ?? ($currencySymbol . number_format(0, $currencyDecimals)) }}</div>
            </div>
            <div class="promotion-performance-metric">
                <div class="promotion-performance-label">Platform Funded</div>
                <div class="promotion-performance-value">{{ $promotionPerformance['platform_funded_spend_label'] ?? ($currencySymbol . number_format(0, $currencyDecimals)) }}</div>
            </div>
            <div class="promotion-performance-metric">
                <div class="promotion-performance-label">Partner Funded</div>
                <div class="promotion-performance-value">{{ $promotionPerformance['partner_funded_spend_label'] ?? ($currencySymbol . number_format(0, $currencyDecimals)) }}</div>
            </div>
        </div>
        <div class="promotion-performance-list">
            @forelse(($promotionPerformance['top_promos'] ?? []) as $promo)
                <div class="promotion-row">
                    <div class="min-w-0">
                        <div class="fw-bold text-dark text-truncate">{{ $promo['title'] ?? 'Promotion' }}</div>
                        <div class="small text-muted">
                            {{ $promo['code'] ?? 'Auto promotion' }} · {{ number_format($promo['usage_count'] ?? 0) }} uses
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-success">{{ $promo['discount_label'] ?? ($currencySymbol . number_format($promo['discount_given'] ?? 0, $currencyDecimals)) }}</div>
                        <div class="small text-muted">discount</div>
                        @if(isset($promo['restaurant_liability_label']))
                            <div class="small text-danger fw-semibold">{{ $promo['restaurant_liability_label'] }} restaurant funded</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="analytics-empty">Promotion usage will appear after customers redeem offers.</div>
            @endforelse
        </div>
    </section>

    <section class="analytics-grid analytics-grid-main">
        <div class="table-card analytics-panel">
            <div class="analytics-panel-head">
                <div>
                    <h3 class="analytics-panel-title">Revenue & Orders Trend</h3>
                    <div class="analytics-panel-subtitle">Daily revenue with order volume on a separate axis.</div>
                </div>
            </div>
            <div class="analytics-chart-shell">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <div class="table-card analytics-panel">
            <div class="analytics-panel-head">
                <div>
                    <h3 class="analytics-panel-title">Order Status</h3>
                    <div class="analytics-panel-subtitle">Status mix for selected range.</div>
                </div>
            </div>
            <div class="analytics-chart-shell donut">
                <canvas id="statusChart"></canvas>
            </div>
            <div class="status-legend" id="statusLegend"></div>
        </div>
    </section>

    <section class="analytics-grid analytics-grid-secondary">
        <div class="table-card analytics-panel">
            <div class="analytics-panel-head">
                <div>
                    <h3 class="analytics-panel-title">Hourly Orders</h3>
                    <div class="analytics-panel-subtitle">Order concentration by time of day.</div>
                </div>
            </div>
            <div class="analytics-chart-shell compact">
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>

        <div class="table-card analytics-panel">
            <div class="analytics-panel-head">
                <div>
                    <h3 class="analytics-panel-title">Top Selling Items</h3>
                    <div class="analytics-panel-subtitle">Ranked by tracked order count.</div>
                </div>
            </div>
            <div class="top-items-list">
                @forelse($topItemRows as $item)
                    <div class="top-item-row">
                        <div class="top-item-image">
                            <div class="top-item-thumb">
                                @if($item['image'])
                                    <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}">
                                @else
                                    <i class="fas fa-utensils"></i>
                                @endif
                            </div>
                            <div class="top-item-rank">{{ $item['rank'] }}</div>
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate">{{ $item['name'] }}</div>
                            <div class="small text-muted">
                                {{ $item['orders'] }} orders
                                @if($item['category'])
                                    <span class="mx-1">-</span>{{ $item['category'] }}
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-success">{{ $item['estimated_revenue'] }}</div>
                            <div class="small text-muted">est. revenue</div>
                        </div>
                    </div>
                @empty
                    <div class="analytics-empty">No top selling item data available.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        var currencySymbol = @json($currencySymbol);
        var currencyDecimals = Number(@json($currencyDecimals));
        var salesData = @json($salesChartData);
        var statusData = @json($statusChartData);
        var hourlyData = @json($hourlyChartData);
        var css = getComputedStyle(document.documentElement);
        var primary = css.getPropertyValue('--primary').trim() || '#ff6b35';
        var success = css.getPropertyValue('--success').trim() || '#10b981';
        var warning = css.getPropertyValue('--warning').trim() || '#f59e0b';
        var danger = css.getPropertyValue('--danger').trim() || '#ef4444';
        var info = css.getPropertyValue('--info').trim() || '#3b82f6';
        var slate = '#64748b';
        var palette = [primary, info, success, warning, danger, '#8b5cf6', '#14b8a6', '#f97316'];
        var commonGrid = {
            color: 'rgba(148, 163, 184, .18)',
            drawBorder: false
        };
        var commonTicks = {
            color: slate,
            font: { weight: 700 }
        };

        function money(value) {
            return String(currencySymbol) + Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: currencyDecimals,
                maximumFractionDigits: currencyDecimals
            });
        }

        function pluck(rows, key) {
            return rows.map(function (row) {
                return row[key];
            });
        }

        function ticksWithMoney() {
            return {
                color: slate,
                font: { weight: 700 },
                callback: function (value) {
                    return money(value);
                }
            };
        }

        function ticksInteger() {
            return {
                color: slate,
                font: { weight: 700 },
                precision: 0
            };
        }

        function emptyChartMessage(canvasId, message) {
            var canvas = document.getElementById(canvasId);
            var shell = canvas ? canvas.closest('.analytics-chart-shell') : null;
            if (shell) {
                shell.innerHTML = '<div class="analytics-empty">' + message + '</div>';
            }
        }

        if (salesData.length && typeof Chart !== 'undefined') {
            var salesCtx = document.getElementById('salesChart').getContext('2d');
            var gradient = salesCtx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(255, 107, 53, .22)');
            gradient.addColorStop(1, 'rgba(255, 107, 53, 0)');

            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: pluck(salesData, 'date'),
                    datasets: [
                        {
                            label: 'Revenue',
                            data: pluck(salesData, 'revenue'),
                            borderColor: primary,
                            backgroundColor: gradient,
                            borderWidth: 3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            tension: .38,
                            fill: true,
                            yAxisID: 'revenue',
                        },
                        {
                            label: 'Orders',
                            data: pluck(salesData, 'orders'),
                            borderColor: info,
                            backgroundColor: 'rgba(59, 130, 246, .12)',
                            borderWidth: 2.5,
                            pointRadius: 3,
                            tension: .38,
                            fill: false,
                            yAxisID: 'orders',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: { usePointStyle: true, boxWidth: 8, color: slate, font: { weight: 800 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.dataset.yAxisID === 'revenue'
                                        ? 'Revenue: ' + money(context.parsed.y)
                                        : 'Orders: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: commonTicks },
                        revenue: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            grid: commonGrid,
                            ticks: ticksWithMoney()
                        },
                        orders: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: ticksInteger()
                        }
                    }
                }
            });
        } else {
            emptyChartMessage('salesChart', 'No sales data for this date range.');
        }

        if (statusData.length && typeof Chart !== 'undefined') {
            var statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: pluck(statusData, 'status'),
                    datasets: [{
                        data: pluck(statusData, 'count'),
                        backgroundColor: statusData.map(function (row, index) {
                            return palette[index % palette.length];
                        }),
                        borderColor: '#fff',
                        borderWidth: 4,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': ' + context.parsed + ' orders';
                                }
                            }
                        }
                    }
                }
            });

            var legend = document.getElementById('statusLegend');
            legend.innerHTML = statusData.map(function (row, index) {
                return '<div class="status-legend-row">'
                    + '<span class="status-dot" style="--dot:' + palette[index % palette.length] + '"></span>'
                    + '<div class="fw-bold text-dark">' + row.status + '</div>'
                    + '<div class="fw-bold text-muted">' + row.count + '</div>'
                    + '</div>';
            }).join('');
        } else {
            emptyChartMessage('statusChart', 'No status data available.');
        }

        if (hourlyData.length && typeof Chart !== 'undefined') {
            var hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
            new Chart(hourlyCtx, {
                type: 'bar',
                data: {
                    labels: pluck(hourlyData, 'hour'),
                    datasets: [{
                        label: 'Orders',
                        data: pluck(hourlyData, 'orders'),
                        backgroundColor: 'rgba(255, 107, 53, .72)',
                        borderColor: primary,
                        borderWidth: 1,
                        borderRadius: 10,
                        maxBarThickness: 34
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterLabel: function (context) {
                                    var row = hourlyData[context.dataIndex] || {};
                                    return 'Revenue: ' + money(row.revenue || 0);
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: commonTicks },
                        y: { beginAtZero: true, grid: commonGrid, ticks: ticksInteger() }
                    }
                }
            });
        } else {
            emptyChartMessage('hourlyChart', 'No hourly order data available.');
        }
    }());
</script>
@endsection
