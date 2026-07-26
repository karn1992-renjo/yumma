@extends('layouts.admin')

@section('title', 'Promotion Analytics')
@section('header', 'Promotion Analytics')

@section('content')
@php
    $currencySymbol = \App\Models\AppSetting::getValue('currency_symbol', 'Rs');
    $currencyDecimals = \App\Models\AppSetting::currencyDecimals();
    $dailyUsage = collect($summary['daily_usage'] ?? [])->values();
    $topPromotions = collect($summary['top_promotions'] ?? [])->values();
    $topRestaurants = collect($summary['top_restaurants'] ?? [])->values();
    $zoneRows = collect($zoneBreakdown ?? [])->values();
@endphp

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Promotion Analytics</h1>
            <p>Usage, conversion, reward cost, and zone-wise performance from the unified engine.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.promotion-engine.coupons') }}" class="btn btn-outline-primary">
                <i class="fas fa-ticket-alt me-2"></i>Coupon Library
            </a>
            <a href="{{ route('admin.promotion-engine.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Promotions
            </a>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Promotion</label>
            <select name="promotion_id" class="form-select">
                <option value="">All promotions</option>
                @foreach($promotions as $promotion)
                    <option value="{{ $promotion->id }}" @selected(($filters['promotion_id'] ?? null) == $promotion->id)>{{ $promotion->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Restaurant</label>
            <select name="restaurant_id" class="form-select">
                <option value="">All restaurants</option>
                @foreach($restaurants as $restaurant)
                    <option value="{{ $restaurant->id }}" @selected(($filters['restaurant_id'] ?? null) == $restaurant->id)>{{ $restaurant->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Delivery Zone</label>
            <select name="delivery_area_id" class="form-select">
                <option value="">All delivery zones</option>
                @foreach($deliveryAreas as $area)
                    <option value="{{ $area->id }}" @selected(($filters['delivery_area_id'] ?? null) == $area->id)>
                        {{ $area->name }} @if($area->area_type)({{ ucwords(str_replace('_', ' ', $area->area_type)) }})@endif
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.promotion-engine.analytics') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Orders Generated</div><div class="fs-3 fw-bold">{{ $summary['orders_generated'] }}</div></div></div>
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Revenue Tracked</div><div class="fs-3 fw-bold">{{ $currencySymbol }}{{ number_format((float) $summary['revenue'], $currencyDecimals) }}</div></div></div>
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Discount Cost</div><div class="fs-3 fw-bold">{{ $currencySymbol }}{{ number_format((float) $summary['discount_given'], $currencyDecimals) }}</div></div></div>
    <div class="col-md-3"><div class="table-card p-4 h-100"><div class="text-muted small">Conversion / ROI</div><div class="fs-3 fw-bold">{{ number_format((float) $summary['conversion'], 1) }}% / {{ $summary['roi'] ?? 'N/A' }}</div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="table-card h-100">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-1">Daily Promotion Performance</h5>
                <div class="text-muted small">Usage count and discount spend over time.</div>
            </div>
            <div class="p-4" style="height: 320px;">
                <canvas id="promotionDailyChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="table-card h-100">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-1">Zone Wise Usage</h5>
                <div class="text-muted small">Calculated from delivery coordinates and active delivery zones.</div>
            </div>
            <div class="p-4" style="height: 320px;">
                <canvas id="promotionZoneChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="table-card h-100">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-1">Top Promotions</h5>
                <div class="text-muted small">Ranked by checkout usage.</div>
            </div>
            <div class="p-4" style="height: 300px;">
                <canvas id="topPromotionChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="table-card h-100">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-1">Top Restaurants</h5>
                <div class="text-muted small">Restaurant usage and promotion-funded discount cost.</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Restaurant</th><th>Usage</th><th>Discount</th></tr></thead>
                    <tbody>
                        @forelse($topRestaurants as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row->name }}</td>
                                <td>{{ $row->usage_count }}</td>
                                <td>{{ $currencySymbol }}{{ number_format((float) $row->discount_given, $currencyDecimals) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No restaurant usage yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="table-card h-100">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-1">Scratch & Reward Analysis</h5>
                <div class="text-muted small">Reveal rate and issued reward cost.</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        <tr><td>Scratch cards issued</td><td class="fw-bold text-end">{{ $summary['scratch_cards']['issued'] }}</td></tr>
                        <tr><td>Scratched</td><td class="fw-bold text-end">{{ $summary['scratch_cards']['scratched'] }} ({{ number_format((float) $summary['scratch_cards']['reveal_rate'], 1) }}%)</td></tr>
                        <tr><td>Wallet cashback</td><td class="fw-bold text-end">{{ $currencySymbol }}{{ number_format((float) $summary['scratch_cards']['wallet_cashback'], $currencyDecimals) }}</td></tr>
                        <tr><td>Points issued</td><td class="fw-bold text-end">{{ $summary['scratch_cards']['points_issued'] }}</td></tr>
                        <tr><td>Coupons generated</td><td class="fw-bold text-end">{{ $summary['scratch_cards']['coupons_generated'] }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="table-card h-100">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-1">Zone Breakdown</h5>
                <div class="text-muted small">Active delivery zones with recorded promotion usage.</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Zone</th><th>Usage</th><th>Discount Cost</th></tr></thead>
                    <tbody>
                        @forelse($zoneRows as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['name'] }}</td>
                                <td>{{ $row['usage_count'] }}</td>
                                <td>{{ $currencySymbol }}{{ number_format((float) $row['discount_given'], $currencyDecimals) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No zone usage for the current filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="p-4 border-bottom">
        <h5 class="fw-bold mb-1">Usage Records</h5>
        <div class="text-muted small">Checkout-applied promotions and coupon usage audit.</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Promotion</th><th>Order</th><th>Coupon</th><th>Discount</th><th>Cashback</th><th>Time</th></tr></thead>
            <tbody>
                @forelse($usageRows as $row)
                    <tr>
                        <td>{{ $row->promotion?->title ?: 'N/A' }}</td>
                        <td>{{ $row->order?->order_number ?: ($row->order_id ? '#'.$row->order_id : 'N/A') }}</td>
                        <td>{{ $row->coupon_code ?: 'N/A' }}</td>
                        <td>{{ $currencySymbol }}{{ number_format((float) $row->discount_amount, $currencyDecimals) }}</td>
                        <td>{{ $currencySymbol }}{{ number_format((float) $row->cashback_amount, $currencyDecimals) }}</td>
                        <td class="text-muted small">{{ $row->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No usage records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $usageRows->links() }}</div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const daily = @json($dailyUsage);
    const topPromotions = @json($topPromotions);
    const zones = @json($zoneRows);

    const emptyPlugin = {
        id: 'emptyMessage',
        afterDraw(chart) {
            const hasData = chart.data.datasets.some((dataset) => dataset.data.some((value) => Number(value || 0) > 0));
            if (hasData) return;
            const {ctx, chartArea} = chart;
            if (!chartArea) return;
            ctx.save();
            ctx.fillStyle = '#667085';
            ctx.font = '600 13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('No data for current filters', (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2);
            ctx.restore();
        },
    };

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { boxWidth: 10, usePointStyle: true } } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    };

    new Chart(document.getElementById('promotionDailyChart'), {
        type: 'line',
        data: {
            labels: daily.map((row) => row.period),
            datasets: [
                {
                    label: 'Usage',
                    data: daily.map((row) => Number(row.usage_count || 0)),
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, .12)',
                    tension: .35,
                    fill: true,
                },
                {
                    label: 'Discount',
                    data: daily.map((row) => Number(row.discount_given || 0)),
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22, 163, 74, .10)',
                    tension: .35,
                    yAxisID: 'discount',
                },
            ],
        },
        options: {
            ...baseOptions,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                discount: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } },
            },
        },
        plugins: [emptyPlugin],
    });

    new Chart(document.getElementById('promotionZoneChart'), {
        type: 'doughnut',
        data: {
            labels: zones.map((row) => row.name),
            datasets: [{ data: zones.map((row) => Number(row.usage_count || 0)), backgroundColor: ['#f97316', '#16a34a', '#7c3aed', '#0ea5e9', '#ef4444', '#f59e0b'] }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        plugins: [emptyPlugin],
    });

    new Chart(document.getElementById('topPromotionChart'), {
        type: 'bar',
        data: {
            labels: topPromotions.map((row) => row.title),
            datasets: [{ label: 'Usage', data: topPromotions.map((row) => Number(row.usage_count || 0)), backgroundColor: '#f97316', borderRadius: 8 }],
        },
        options: baseOptions,
        plugins: [emptyPlugin],
    });
});
</script>
@endsection
