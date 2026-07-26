{{-- resources/views/restaurant/dashboard.blade.php --}}
@extends('layouts.restaurant')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8'));
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();

    if (strpos((string) $currencySymbol, '{{') !== false || strpos((string) $currencySymbol, 'currencySymbol') !== false) {
        $currencySymbol = html_entity_decode('&#8377;', ENT_QUOTES, 'UTF-8');
    }

    $canManageMenu = auth()->check() && auth()->user()->hasRestaurantPermission('manage_menu');
    $canViewOrders = auth()->check() && (auth()->user()->hasRestaurantPermission('view_orders') || auth()->user()->hasRestaurantPermission('manage_orders'));
    $isOwner = auth()->check() && auth()->user()->hasRole('restaurant_owner');
    $restaurantLabel = $selectedScope === 'all' ? 'All Restaurants' : ($restaurant->name ?? 'Restaurant');
    $activeRestaurantCount = 0;

    if ($selectedScope === 'all') {
        foreach ($restaurants as $ownedRestaurant) {
            if ($ownedRestaurant->is_open) {
                $activeRestaurantCount++;
            }
        }
    } elseif ($restaurant && $restaurant->is_open) {
        $activeRestaurantCount = 1;
    }

    $displayOrders = $activeOrders->isNotEmpty() ? $activeOrders : $recentOrders->take(5);
    $statusLabels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready_for_pickup' => 'Ready',
        'picked_up' => 'Picked Up',
        'on_the_way' => 'On The Way',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];

    $orderRows = [];

    foreach ($displayOrders as $order) {
        $itemsCount = (int) ($order->order_items_count ?? 0);

        if ($itemsCount === 0 && is_array($order->items)) {
            foreach ($order->items as $item) {
                if (is_array($item)) {
                    $itemsCount += (int) ($item['quantity'] ?? $item['qty'] ?? 1);
                }
            }
        }

        $customerName = $order->customer->name ?? $order->customer_name ?? 'Guest';
        $statusKey = strtolower((string) ($order->status ?? 'pending'));

        $orderRows[] = [
            'id' => $order->id,
            'number' => $order->order_number ?? $order->id,
            'customer' => $customerName,
            'initial' => strtoupper(substr($customerName, 0, 1)),
            'items' => $itemsCount,
            'status' => $statusKey,
            'status_label' => $statusLabels[$statusKey] ?? ucfirst(str_replace('_', ' ', $statusKey)),
            'created' => $order->created_at ? $order->created_at->diffForHumans() : '',
            'total' => $currencySymbol . number_format((float) $order->total, $currencyDecimals),
        ];
    }

    $popularRows = [];

    foreach ($popularItems as $item) {
        $imageUrl = $item->image_url ?? null;
        $price = $item->discounted_price !== null ? $item->discounted_price : $item->price;

        $popularRows[] = [
            'name' => $item->name,
            'orders' => (int) ($item->total_orders ?? 0),
            'price' => $currencySymbol . number_format((float) $price, $currencyDecimals),
            'image' => $imageUrl,
            'diet' => $item->diet_label ?? ($item->is_veg ? 'Veg' : 'Non-Veg'),
            'available' => (bool) $item->is_available,
        ];
    }

    $revenueSeries = is_array($revenueTrend['revenue'] ?? null) ? $revenueTrend['revenue'] : [];
    $ordersSeries = is_array($revenueTrend['orders'] ?? null) ? $revenueTrend['orders'] : [];
    $labelsSeries = is_array($revenueTrend['labels'] ?? null) ? $revenueTrend['labels'] : [];
    $customerSpark = [];
    $baseCustomerValue = max(0, (int) $totalCustomers - 18);

    for ($i = 0; $i < 12; $i++) {
        $customerSpark[] = min((int) $totalCustomers, $baseCustomerValue + ($i * 2));
    }

    $ratingSpark = [3.8, 4.1, 4.0, 4.2, 4.4, 4.3, 4.5, 4.6, 4.5, 4.7];
    $payoutSpark = [12, 14, 16, 13, 18, 22, 20, 24, 26, 30];

    $kpiCards = [
        [
            'label' => 'Total Revenue',
            'value' => $currencySymbol . number_format((float) $totalRevenue, $currencyDecimals),
            'sub' => 'Delivered revenue',
            'trend' => $currencySymbol . number_format((float) $todayRevenue, $currencyDecimals) . ' today',
            'icon' => 'rupee-sign',
            'color' => '#f97316',
            'spark' => $revenueSeries,
        ],
        [
            'label' => 'Total Orders',
            'value' => number_format((int) $totalOrders),
            'sub' => number_format((int) $pendingOrders) . ' active, ' . number_format((int) $deliveredOrdersCount) . ' delivered',
            'trend' => number_format((int) $todayOrders) . ' today',
            'icon' => 'shopping-bag',
            'color' => '#3b82f6',
            'spark' => $ordersSeries,
        ],
        [
            'label' => 'Customers',
            'value' => number_format((int) $totalCustomers),
            'sub' => 'Unique ordering customers',
            'trend' => 'Customer base',
            'icon' => 'users',
            'color' => '#8b5cf6',
            'spark' => $customerSpark,
        ],
        [
            'label' => 'Average Rating',
            'value' => number_format((float) $avgRating, 1),
            'sub' => 'Customer experience score',
            'trend' => number_format((float) $successRate, 1) . '% success',
            'icon' => 'star',
            'color' => '#f59e0b',
            'spark' => $ratingSpark,
        ],
        [
            'label' => 'Pending Payouts',
            'value' => $currencySymbol . number_format((float) ($payoutSummary['pending_amount'] ?? 0), $currencyDecimals),
            'sub' => number_format((int) ($payoutSummary['pending_count'] ?? 0)) . ' queued',
            'trend' => $payoutSummary['frequency'] ?? 'Weekly',
            'icon' => 'wallet',
            'color' => '#22c55e',
            'spark' => $payoutSpark,
        ],
    ];

    $operationCards = [
        ['label' => 'Best Order Time', 'value' => $bestOrderTime['label'] ?? 'No orders yet', 'sub' => number_format((int) ($bestOrderTime['orders'] ?? 0)) . ' orders', 'color' => '#3b82f6'],
        ['label' => 'Success Rate', 'value' => number_format((float) $successRate, 1) . '%', 'sub' => number_format((int) $deliveredOrdersCount) . ' delivered', 'color' => '#22c55e'],
        ['label' => 'Cancellation', 'value' => number_format((float) $cancellationRate, 1) . '%', 'sub' => number_format((int) $cancelledOrders) . ' cancelled', 'color' => '#ef4444'],
        ['label' => 'Avg Delivery', 'value' => ($avgDeliveryTime ? number_format((float) $avgDeliveryTime) : '0') . ' mins', 'sub' => 'Delivered orders', 'color' => '#f97316'],
    ];

    $chartData = [
        'labels' => $labelsSeries,
        'revenue' => $revenueSeries,
        'orders' => $ordersSeries,
        'currency' => $currencySymbol,
        'decimals' => $currencyDecimals,
    ];
@endphp

@section('title', 'Dashboard')

@section('styles')
<style>
    .dashboard-shell {
        display: grid;
        gap: 16px;
        max-width: 100%;
        min-width: 0;
        overflow-x: hidden;
    }

    .dashboard-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 16px;
        border: 1px solid rgba(226, 232, 240, .9);
        border-radius: 22px;
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 16px 42px rgba(15, 23, 42, .06);
    }

    .dashboard-title {
        color: #0f172a;
        font-size: 22px;
        font-weight: 950;
        line-height: 1.1;
        margin: 0;
    }

    .dashboard-subtitle {
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
        margin-top: 5px;
    }

    .toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }

    .scope-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 12px;
        border-radius: 999px;
        color: #475569;
        text-decoration: none;
        font-size: 12px;
        font-weight: 900;
        background: #f8fafc;
        border: 1px solid rgba(226, 232, 240, .95);
    }

    .scope-pill.active {
        color: #fff;
        background: var(--primary);
        border-color: var(--primary);
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 14px;
    }

    .kpi-card,
    .dashboard-panel,
    .mini-card {
        border: 1px solid rgba(226, 232, 240, .9);
        background:
            linear-gradient(180deg, rgba(255,255,255,.98), rgba(255,255,255,.92)),
            radial-gradient(circle at top right, color-mix(in srgb, var(--accent, #f97316) 13%, transparent), transparent 46%);
        box-shadow: 0 18px 48px rgba(15, 23, 42, .07);
    }

    .kpi-card {
        min-width: 0;
        min-height: 150px;
        padding: 16px;
        border-radius: 22px;
        overflow: hidden;
    }

    .kpi-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .kpi-icon,
    .avatar-tile,
    .item-thumb,
    .item-thumb-placeholder {
        width: 44px;
        height: 44px;
        border-radius: 15px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
    }

    .kpi-icon {
        color: var(--accent);
        background: color-mix(in srgb, var(--accent) 13%, white);
    }

    .kpi-trend {
        color: #059669;
        font-size: 12px;
        font-weight: 900;
        text-align: right;
    }

    .kpi-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 850;
        margin-top: 15px;
    }

    .kpi-value {
        color: #0f172a;
        font-size: 25px;
        font-weight: 950;
        line-height: 1.05;
        margin-top: 5px;
        overflow-wrap: anywhere;
    }

    .kpi-sub {
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
        margin-top: 8px;
    }

    .sparkline {
        display: block;
        width: 100%;
        height: 30px;
        margin-top: 9px;
    }

    .dashboard-main {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, .8fr);
        gap: 16px;
        align-items: stretch;
    }

    .dashboard-lower {
        display: grid;
        grid-template-columns: minmax(310px, .9fr) minmax(0, 1.1fr) minmax(300px, .8fr);
        gap: 16px;
        align-items: start;
    }

    .dashboard-panel {
        min-width: 0;
        overflow: hidden;
        border-radius: 24px;
    }

    .panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px 13px;
        border-bottom: 1px solid rgba(226, 232, 240, .86);
    }

    .panel-title {
        color: #0f172a;
        font-size: 16px;
        font-weight: 950;
        margin: 0;
    }

    .panel-sub {
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
        margin-top: 3px;
    }

    .panel-link {
        color: var(--primary);
        text-decoration: none;
        font-size: 12px;
        font-weight: 950;
        white-space: nowrap;
    }

    .chart-shell {
        height: 312px;
        padding: 14px 18px 18px;
    }

    .dashboard-main > .dashboard-panel:first-child {
        display: flex;
        flex-direction: column;
        min-height: 100%;
    }

    .dashboard-main > .dashboard-panel:first-child .chart-shell {
        flex: 1 1 auto;
        min-height: 312px;
        height: auto;
    }

    .chart-canvas {
        width: 100%;
        height: 100%;
        display: block;
    }

    .orders-list,
    .items-list,
    .split-list,
    .health-list {
        display: grid;
    }

    .order-row,
    .item-row,
    .split-row,
    .health-row {
        display: grid;
        gap: 12px;
        align-items: center;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(226, 232, 240, .82);
    }

    .order-row {
        grid-template-columns: 44px minmax(0, 1fr) auto;
        color: inherit;
        text-decoration: none;
    }

    .order-row:last-child,
    .item-row:last-child,
    .split-row:last-child,
    .health-row:last-child {
        border-bottom: 0;
    }

    .avatar-tile {
        color: #fff;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        font-weight: 950;
    }

    .row-title {
        color: #0f172a;
        font-size: 14px;
        font-weight: 950;
        line-height: 1.2;
    }

    .row-meta {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        margin-top: 3px;
    }

    .row-amount {
        color: #0f172a;
        font-size: 14px;
        font-weight: 950;
        text-align: right;
        white-space: nowrap;
    }

    .status-pill,
    .health-pill,
    .item-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 6px 9px;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }

    .status-pill {
        margin-top: 6px;
        background: #eef2ff;
        color: #4338ca;
    }

    .status-pill.pending { background: #fffbeb; color: #92400e; }
    .status-pill.confirmed,
    .status-pill.preparing,
    .status-pill.ready_for_pickup,
    .status-pill.picked_up,
    .status-pill.on_the_way { background: #eff6ff; color: #1d4ed8; }
    .status-pill.delivered { background: #dcfce7; color: #166534; }
    .status-pill.cancelled,
    .status-pill.refunded { background: #fee2e2; color: #991b1b; }

    .item-row {
        grid-template-columns: 50px minmax(0, 1fr) auto;
    }

    .item-thumb {
        object-fit: cover;
        border: 1px solid rgba(226, 232, 240, .95);
        background: #f8fafc;
    }

    .item-thumb-placeholder {
        color: var(--primary);
        border: 1px solid rgba(226, 232, 240, .95);
        background: color-mix(in srgb, var(--primary) 10%, white);
    }

    .item-pill {
        margin-top: 6px;
        color: #047857;
        background: #ecfdf5;
    }

    .item-pill.off {
        color: #991b1b;
        background: #fee2e2;
    }

    .split-row {
        grid-template-columns: 44px minmax(0, 1fr);
    }

    .split-bar {
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: #e2e8f0;
        margin-top: 8px;
    }

    .split-bar span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--primary), #22c55e);
    }

    .mini-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        padding: 16px;
    }

    .mini-card {
        min-height: 106px;
        padding: 15px;
        border-radius: 18px;
        --accent: #3b82f6;
    }

    .mini-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 850;
    }

    .mini-value {
        color: #0f172a;
        font-size: 21px;
        font-weight: 950;
        line-height: 1.1;
        margin-top: 8px;
    }

    .mini-sub {
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
        margin-top: 7px;
    }

    .health-row {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .health-pill {
        color: #047857;
        background: #dcfce7;
    }

    .health-pill.warn {
        color: #92400e;
        background: #fef3c7;
    }

    .empty-state {
        padding: 34px 16px;
        color: #64748b;
        text-align: center;
        font-size: 13px;
        font-weight: 800;
    }

    @media (max-width: 1400px) {
        .dashboard-lower {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }

        .dashboard-lower .dashboard-panel:last-child {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 1180px) {
        .dashboard-main,
        .dashboard-lower {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .dashboard-toolbar {
            grid-template-columns: 1fr;
        }

        .toolbar-actions {
            justify-content: flex-start;
        }

        .mini-grid {
            grid-template-columns: 1fr;
        }

        .order-row,
        .item-row {
            grid-template-columns: 44px minmax(0, 1fr);
        }

        .row-amount {
            grid-column: 2;
            text-align: left;
        }

        .chart-shell {
            height: 260px;
        }
    }
</style>
@endsection

@section('content')
<div class="dashboard-shell">
    <section class="dashboard-toolbar">
        <div class="min-w-0">
            <h1 class="dashboard-title">{{ $restaurantLabel }}</h1>
            <div class="dashboard-subtitle">
                Revenue, orders, payout health and kitchen activity in one compact view.
            </div>
        </div>

        <div class="toolbar-actions">
            @if($isOwner && $restaurants->count() > 1)
                <a href="{{ route('restaurant.dashboard') }}" class="scope-pill {{ $selectedScope === 'single' ? 'active' : '' }}">
                    <i class="fas fa-store"></i>Current
                </a>
                <a href="{{ route('restaurant.dashboard', ['scope' => 'all']) }}" class="scope-pill {{ $selectedScope === 'all' ? 'active' : '' }}">
                    <i class="fas fa-layer-group"></i>All
                </a>
            @endif

            @if($canViewOrders)
                <a href="{{ route('restaurant.orders.index') }}" class="btn btn-primary">
                    <i class="fas fa-list-check me-2"></i>Orders
                </a>
            @endif

            @if($canManageMenu)
                <a href="{{ route('restaurant.menu.index') }}" class="btn btn-light">
                    <i class="fas fa-utensils me-2"></i>Menu
                </a>
            @endif
        </div>
    </section>

    <section class="kpi-grid">
        @foreach($kpiCards as $card)
            <div class="kpi-card" style="--accent: {{ $card['color'] }};">
                <div class="kpi-top">
                    <div class="kpi-icon">
                        <i class="fas fa-{{ $card['icon'] }}"></i>
                    </div>
                    <div class="kpi-trend">{{ $card['trend'] }}</div>
                </div>
                <div class="kpi-label">{{ $card['label'] }}</div>
                <div class="kpi-value">{{ $card['value'] }}</div>
                <canvas class="sparkline" data-color="{{ $card['color'] }}" data-values="{{ e(json_encode($card['spark'])) }}"></canvas>
                <div class="kpi-sub">{{ $card['sub'] }}</div>
            </div>
        @endforeach
    </section>

    <section class="dashboard-main">
        <div class="dashboard-panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Revenue Trend</h2>
                    <div class="panel-sub">Delivered revenue and orders across the last 14 days.</div>
                </div>
            </div>
            <div class="chart-shell">
                <canvas id="dashboardRevenueChart" class="chart-canvas"></canvas>
            </div>
        </div>

        <div class="dashboard-panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Live Orders</h2>
                    <div class="panel-sub">Active orders first, latest orders as fallback.</div>
                </div>
                @if($canViewOrders)
                    <a href="{{ route('restaurant.orders.index') }}" class="panel-link">View All</a>
                @endif
            </div>

            <div class="orders-list">
                @forelse($orderRows as $row)
                    <a href="{{ route('restaurant.orders.show', $row['id']) }}" class="order-row">
                        <div class="avatar-tile">{{ $row['initial'] }}</div>
                        <div class="min-w-0">
                            <div class="row-title">#{{ $row['number'] }}</div>
                            <div class="row-meta text-truncate">{{ $row['customer'] }} - {{ $row['items'] }} items</div>
                            <div class="row-meta">{{ $row['created'] }}</div>
                        </div>
                        <div class="row-amount">
                            {{ $row['total'] }}
                            <div><span class="status-pill {{ $row['status'] }}">{{ $row['status_label'] }}</span></div>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">No orders yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="dashboard-lower">
        <div class="dashboard-panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Popular Items</h2>
                    <div class="panel-sub">Best sellers from your menu.</div>
                </div>
                @if($canManageMenu)
                    <a href="{{ route('restaurant.menu.index') }}" class="panel-link">Manage</a>
                @endif
            </div>

            <div class="items-list">
                @forelse($popularRows as $item)
                    <div class="item-row">
                        @if($item['image'])
                            <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="item-thumb">
                        @else
                            <div class="item-thumb-placeholder"><i class="fas fa-utensils"></i></div>
                        @endif

                        <div class="min-w-0">
                            <div class="row-title text-truncate">{{ $item['name'] }}</div>
                            <div class="row-meta">{{ number_format($item['orders']) }} orders - {{ $item['diet'] }}</div>
                            <span class="item-pill {{ $item['available'] ? '' : 'off' }}">{{ $item['available'] ? 'Available' : 'Unavailable' }}</span>
                        </div>

                        <div class="row-amount">{{ $item['price'] }}</div>
                    </div>
                @empty
                    <div class="empty-state">No popular items yet.</div>
                @endforelse
            </div>
        </div>

        <div class="dashboard-panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Operations Summary</h2>
                    <div class="panel-sub">Timing, fulfilment and reliability metrics.</div>
                </div>
            </div>

            <div class="mini-grid">
                @foreach($operationCards as $card)
                    <div class="mini-card" style="--accent: {{ $card['color'] }};">
                        <div class="mini-label">{{ $card['label'] }}</div>
                        <div class="mini-value">{{ $card['value'] }}</div>
                        <div class="mini-sub">{{ $card['sub'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="dashboard-panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Store Health</h2>
                    <div class="panel-sub">Restaurant availability and payout state.</div>
                </div>
            </div>

            <div class="health-list">
                <div class="health-row">
                    <div>
                        <div class="row-title">Restaurant Status</div>
                        <div class="row-meta">{{ $selectedScope === 'all' ? $activeRestaurantCount . ' open restaurants' : 'Current store availability' }}</div>
                    </div>
                    <span class="health-pill {{ $activeRestaurantCount > 0 ? '' : 'warn' }}">{{ $activeRestaurantCount > 0 ? 'Online' : 'Offline' }}</span>
                </div>

                <div class="health-row">
                    <div>
                        <div class="row-title">Next Payout</div>
                        <div class="row-meta">{{ $payoutSummary['frequency'] ?? 'Weekly' }} schedule</div>
                    </div>
                    <span class="health-pill">{{ $payoutSummary['next_date']->format('d M Y') }}</span>
                </div>

                <div class="health-row">
                    <div>
                        <div class="row-title">Last Payout</div>
                        <div class="row-meta">Latest payout record</div>
                    </div>
                    <span class="health-pill {{ $payoutSummary['last'] ? '' : 'warn' }}">
                        {{ $payoutSummary['last'] ? ucfirst($payoutSummary['last']->status) : 'None' }}
                    </span>
                </div>

                <div class="health-row">
                    <div>
                        <div class="row-title">Pending Queue</div>
                        <div class="row-meta">{{ $currencySymbol }}{{ number_format((float) ($payoutSummary['pending_amount'] ?? 0), $currencyDecimals) }} pending</div>
                    </div>
                    <span class="health-pill {{ ($payoutSummary['pending_count'] ?? 0) > 0 ? 'warn' : '' }}">
                        {{ number_format((int) ($payoutSummary['pending_count'] ?? 0)) }} payouts
                    </span>
                </div>
            </div>
        </div>

        <div class="dashboard-panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Revenue Split</h2>
                    <div class="panel-sub">Delivered revenue by restaurant.</div>
                </div>
            </div>

            <div class="split-list">
                @forelse($restaurantBreakdown as $row)
                    <div class="split-row">
                        <div class="avatar-tile">{{ strtoupper(substr($row['name'], 0, 1)) }}</div>
                        <div class="min-w-0">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="row-title text-truncate">{{ $row['name'] }}</div>
                                <div class="row-amount">{{ $currencySymbol }}{{ number_format((float) $row['revenue'], $currencyDecimals) }}</div>
                            </div>
                            <div class="row-meta">{{ $row['city'] ?: 'Location not set' }} - {{ number_format((int) $row['orders']) }} delivered</div>
                            <div class="split-bar">
                                <span style="width: {{ min(100, max(0, (float) $row['share'])) }}%;"></span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">No delivered revenue yet.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dashboardChartData = @json($chartData);

    function normalizeValues(values) {
        if (!Array.isArray(values)) {
            return [];
        }

        return values.map(Number).filter(Number.isFinite);
    }

    function drawSparkline(canvas) {
        const values = normalizeValues(JSON.parse(canvas.dataset.values || '[]'));
        const ctx = canvas.getContext('2d');
        const width = canvas.clientWidth || 180;
        const height = canvas.clientHeight || 30;
        const ratio = window.devicePixelRatio || 1;
        const data = values.length ? values : [0, 1, 0, 2, 1, 3];
        const min = Math.min(...data);
        const max = Math.max(...data);
        const spread = Math.max(max - min, 1);
        const step = data.length > 1 ? width / (data.length - 1) : width;

        canvas.width = width * ratio;
        canvas.height = height * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, width, height);
        ctx.beginPath();

        data.forEach(function (value, index) {
            const x = index * step;
            const y = height - ((value - min) / spread) * (height - 7) - 3.5;

            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });

        ctx.strokeStyle = canvas.dataset.color || '#f97316';
        ctx.lineWidth = 2.4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
    }

    function formatCurrency(value) {
        const decimals = Number(dashboardChartData.decimals || 0);
        return String(dashboardChartData.currency || '') + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function drawRevenueChart() {
        const canvas = document.getElementById('dashboardRevenueChart');

        if (!canvas) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const bounds = canvas.getBoundingClientRect();
        const width = Math.max(bounds.width, 320);
        const height = Math.max(bounds.height, 240);
        const ratio = window.devicePixelRatio || 1;
        const labels = Array.isArray(dashboardChartData.labels) ? dashboardChartData.labels : [];
        const revenue = normalizeValues(dashboardChartData.revenue);
        const orders = normalizeValues(dashboardChartData.orders);
        const length = Math.max(labels.length, revenue.length, orders.length, 1);
        const pad = { top: 18, right: 42, bottom: 40, left: 72 };
        const plotWidth = width - pad.left - pad.right;
        const plotHeight = height - pad.top - pad.bottom;
        const maxRevenue = Math.max(...revenue, 1);
        const maxOrders = Math.max(...orders, 1);

        canvas.width = width * ratio;
        canvas.height = height * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, width, height);
        ctx.font = '700 11px Inter, system-ui, sans-serif';
        ctx.lineWidth = 1;

        for (let i = 0; i <= 4; i++) {
            const y = pad.top + (plotHeight / 4) * i;
            const revenueTick = maxRevenue - (maxRevenue / 4) * i;
            ctx.strokeStyle = 'rgba(148, 163, 184, .22)';
            ctx.beginPath();
            ctx.moveTo(pad.left, y);
            ctx.lineTo(width - pad.right, y);
            ctx.stroke();
            ctx.fillStyle = '#64748b';
            ctx.textAlign = 'right';
            ctx.fillText(formatCurrency(revenueTick), pad.left - 10, y + 4);
        }

        function point(index, value, maxValue) {
            const x = pad.left + (length > 1 ? (plotWidth / (length - 1)) * index : plotWidth / 2);
            const y = pad.top + plotHeight - ((Number(value || 0) / maxValue) * plotHeight);

            return { x, y };
        }

        function drawLine(values, maxValue, color, fill) {
            const safeValues = values.length ? values : [0];
            const gradient = ctx.createLinearGradient(0, pad.top, 0, height - pad.bottom);
            gradient.addColorStop(0, fill);
            gradient.addColorStop(1, 'rgba(255,255,255,0)');

            ctx.beginPath();
            safeValues.forEach(function (value, index) {
                const pos = point(index, value, maxValue);

                if (index === 0) {
                    ctx.moveTo(pos.x, pos.y);
                } else {
                    ctx.lineTo(pos.x, pos.y);
                }
            });

            if (safeValues.length > 1) {
                const last = point(safeValues.length - 1, safeValues[safeValues.length - 1], maxValue);
                const first = point(0, safeValues[0], maxValue);
                ctx.lineTo(last.x, pad.top + plotHeight);
                ctx.lineTo(first.x, pad.top + plotHeight);
                ctx.closePath();
                ctx.fillStyle = gradient;
                ctx.fill();
            }

            ctx.beginPath();
            safeValues.forEach(function (value, index) {
                const pos = point(index, value, maxValue);

                if (index === 0) {
                    ctx.moveTo(pos.x, pos.y);
                } else {
                    ctx.lineTo(pos.x, pos.y);
                }
            });
            ctx.strokeStyle = color;
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.stroke();
        }

        drawLine(revenue, maxRevenue, '#7c3aed', 'rgba(124, 58, 237, .24)');
        drawLine(orders, maxOrders, '#f97316', 'rgba(249, 115, 22, .14)');

        ctx.fillStyle = '#64748b';
        ctx.textAlign = 'center';
        const labelStep = Math.max(1, Math.ceil(length / 7));
        labels.forEach(function (label, index) {
            if (index % labelStep === 0 || index === labels.length - 1) {
                const pos = point(index, 0, 1);
                ctx.fillText(label, pos.x, height - 16);
            }
        });

        ctx.textAlign = 'left';
        ctx.fillStyle = '#7c3aed';
        ctx.fillRect(pad.left, 8, 10, 10);
        ctx.fillText('Revenue', pad.left + 16, 17);
        ctx.fillStyle = '#f97316';
        ctx.fillRect(pad.left + 92, 8, 10, 10);
        ctx.fillText('Orders', pad.left + 108, 17);
    }

    function redraw() {
        document.querySelectorAll('.sparkline').forEach(drawSparkline);
        drawRevenueChart();
    }

    redraw();
    window.addEventListener('resize', redraw);
});
</script>
@endsection
