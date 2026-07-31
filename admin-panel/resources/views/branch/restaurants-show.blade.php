@extends('layouts.admin')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $money = fn ($value) => $currencySymbol . number_format((float) $value, $currencyDecimals);
    $schedule = $restaurant->getFullWeekSchedule();
    $statusChip = $restaurant->isOpenNow() ? ['Open now', 'success', 'store'] : ['Closed now', 'neutral', 'store-slash'];
    $verificationChip = $restaurant->is_verified ? ['Approved', 'success', 'circle-check'] : ['Pending', 'warning', 'clock'];
    $statTiles = [
        ['label' => 'Total Orders', 'value' => number_format($totalOrders), 'icon' => 'receipt', 'tone' => '#8b5cf6'],
        ['label' => 'Delivered Revenue', 'value' => $money($totalRevenue), 'icon' => 'indian-rupee-sign', 'tone' => '#16a34a'],
        ['label' => 'Menu Items', 'value' => number_format($totalMenuItems), 'icon' => 'utensils', 'tone' => '#3b82f6'],
        ['label' => 'Average Rating', 'value' => number_format($averageRating, 1), 'icon' => 'star', 'tone' => '#f59e0b'],
    ];
@endphp

@section('title', 'Branch Restaurant Details')

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
<div class="restaurant-page-shell">
    <section class="restaurant-page-hero">
        <div>
            <h1 class="restaurant-page-title">{{ $restaurant->name }}</h1>
            <div class="restaurant-page-subtitle">{{ $branch->name }} restaurant profile, location, owner account and order summary.</div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <span class="restaurant-chip {{ $statusChip[1] }}"><i class="fas fa-{{ $statusChip[2] }}"></i>{{ $statusChip[0] }}</span>
                <span class="restaurant-chip {{ $verificationChip[1] }}"><i class="fas fa-{{ $verificationChip[2] }}"></i>{{ $verificationChip[0] }}</span>
                <span class="restaurant-chip neutral"><i class="fas fa-truck-fast"></i>{{ ucfirst(str_replace('_', ' ', $restaurant->restaurant_type ?? 'delivery')) }}</span>
            </div>
        </div>
        <div class="restaurant-page-actions">
            @if(($capabilities['restaurants_edit'] ?? false) && ! $restaurant->is_verified)
                <form action="{{ route('branch.restaurants.approve', $restaurant) }}" method="POST">
                    @csrf
                    <button class="btn btn-success"><i class="fas fa-check me-2"></i>Approve</button>
                </form>
            @endif
            @if($capabilities['restaurants_update_status'] ?? false)
                <form action="{{ route('branch.restaurants.toggle-status', $restaurant) }}" method="POST">
                    @csrf
                    <button class="btn {{ $restaurant->is_open ? 'btn-outline-secondary' : 'btn-success' }}">
                        <i class="fas fa-{{ $restaurant->is_open ? 'store-slash' : 'store' }} me-2"></i>{{ $restaurant->is_open ? 'Set Offline' : 'Set Online' }}
                    </button>
                </form>
            @endif
            @if($capabilities['restaurants_edit'] ?? false)
                <a href="{{ route('branch.restaurants.edit', $restaurant) }}" class="btn btn-primary">
                    <i class="fas fa-pen me-2"></i>Edit
                </a>
            @endif
            <a href="{{ route('branch.restaurants') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </section>

    <section class="restaurant-stat-grid">
        @foreach($statTiles as $tile)
            <div class="restaurant-stat-tile" style="--tile-color: {{ $tile['tone'] }};">
                <div>
                    <div class="restaurant-stat-label">{{ $tile['label'] }}</div>
                    <div class="restaurant-stat-value">{{ $tile['value'] }}</div>
                </div>
                <div class="restaurant-stat-icon"><i class="fas fa-{{ $tile['icon'] }}"></i></div>
            </div>
        @endforeach
    </section>

    <div class="restaurant-show-grid">
        <div class="restaurant-page-shell">
            <section class="restaurant-info-panel">
                <div class="restaurant-info-header">
                    <div>
                        <h2>Restaurant Details</h2>
                        <p>Contact, address, coverage and service configuration.</p>
                    </div>
                </div>
                <div class="restaurant-info-body">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Email</span><span class="restaurant-line-value">{{ $restaurant->email }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Phone</span><span class="restaurant-line-value">{{ $restaurant->phone }}</span></div></div>
                        <div class="col-12"><div class="restaurant-line"><span class="restaurant-line-label">Address</span><span class="restaurant-line-value">{{ $restaurant->formatted_address }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Coordinates</span><span class="restaurant-line-value">{{ $restaurant->latitude }}, {{ $restaurant->longitude }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Delivery Radius</span><span class="restaurant-line-value">{{ $restaurant->delivery_radius }} km</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Delivery Time</span><span class="restaurant-line-value">{{ $restaurant->delivery_time ?? 30 }} minutes</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Order Lead Time</span><span class="restaurant-line-value">{{ $restaurant->order_lead_time ?? 0 }} minutes</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Timezone</span><span class="restaurant-line-value">{{ $restaurant->timezone ?? 'Asia/Kolkata' }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Pure Veg</span><span class="restaurant-line-value">{{ $restaurant->is_pure_veg ? 'Yes' : 'No' }}</span></div></div>
                        <div class="col-12"><div class="restaurant-line"><span class="restaurant-line-label">Description</span><span class="restaurant-line-value">{{ $restaurant->description ?: 'No description added.' }}</span></div></div>
                    </div>
                </div>
            </section>

            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Weekly Timing</h2><p>Open, close and break schedule.</p></div></div>
                <div class="table-responsive">
                    <table class="table restaurant-table">
                        <thead><tr><th>Day</th><th>Status</th><th>Open</th><th>Close</th><th>Break</th></tr></thead>
                        <tbody>
                        @foreach($schedule as $day)
                            <tr>
                                <td>{{ $day['day_name'] }}</td>
                                <td>{{ $day['is_open'] ? 'Open' : 'Closed' }}</td>
                                <td>{{ $day['open_time_formatted'] }}</td>
                                <td>{{ $day['close_time_formatted'] }}</td>
                                <td>{{ $day['break_start'] && $day['break_end'] ? $restaurant->formatTime12Hour($day['break_start']) . ' - ' . $restaurant->formatTime12Hour($day['break_end']) : 'No break' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Recent Orders</h2><p>Latest orders visible to this branch.</p></div></div>
                <div class="table-responsive">
                    <table class="table restaurant-table">
                        <thead><tr><th>Order</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
                        <tbody>
                        @forelse($restaurant->orders as $order)
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                                <td>{{ $money($order->total) }}</td>
                                <td>{{ optional($order->created_at)->format('d M Y, h:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No orders found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="restaurant-page-shell">
            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Owner And Account</h2><p>Seller login and settlement account.</p></div></div>
                <div class="restaurant-info-body">
                    <div class="restaurant-line"><span class="restaurant-line-label">Owner</span><span class="restaurant-line-value">{{ $restaurant->owner?->name ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Email</span><span class="restaurant-line-value">{{ $restaurant->owner?->email ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Phone</span><span class="restaurant-line-value">{{ $restaurant->owner?->phone ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Account Holder</span><span class="restaurant-line-value">{{ $restaurant->owner?->account_holder_name ?: 'Not set' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Bank</span><span class="restaurant-line-value">{{ $restaurant->owner?->bank_name ?: 'Not set' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Account No</span><span class="restaurant-line-value">{{ $restaurant->owner?->account_number ?: 'Not set' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Routing</span><span class="restaurant-line-value">{{ $restaurant->owner?->routing_code ?: $restaurant->owner?->ifsc_code ?: 'Not set' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">UPI</span><span class="restaurant-line-value">{{ $restaurant->owner?->upi_id ?: 'Not set' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Gateway</span><span class="restaurant-line-value">{{ $restaurant->owner?->gateway_account_id ?: $restaurant->owner?->stripe_account_id ?: 'Not set' }}</span></div>
                </div>
            </section>

            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Commercials</h2><p>Fees and commission settings.</p></div></div>
                <div class="restaurant-info-body">
                    <div class="restaurant-line"><span class="restaurant-line-label">Min Order</span><span class="restaurant-line-value">{{ $money($restaurant->min_order_amount) }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Delivery Fee</span><span class="restaurant-line-value">{{ $money($restaurant->delivery_fee) }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Dining Charge</span><span class="restaurant-line-value">{{ $money($restaurant->dining_charge ?? 0) }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Commission</span><span class="restaurant-line-value">{{ $restaurant->commission_calculation_type === 'global' ? 'Global' : (($restaurant->commission_rate ?? 0) . ($restaurant->commission_calculation_type === 'percentage' ? '%' : '')) }}</span></div>
                </div>
            </section>

            @if($restaurant->logo_image || $restaurant->banner_image)
                <section class="restaurant-info-panel">
                    <div class="restaurant-info-header"><div><h2>Media</h2><p>Logo and banner.</p></div></div>
                    <div class="restaurant-info-body d-grid gap-3">
                        @if($restaurant->logo_image)
                            <img src="{{ Storage::url($restaurant->logo_image) }}" class="restaurant-media-preview" alt="Logo">
                        @endif
                        @if($restaurant->banner_image)
                            <img src="{{ Storage::url($restaurant->banner_image) }}" class="restaurant-media-preview wide" alt="Banner">
                        @endif
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection

@include('branch._restaurant_map_assets', ['deliveryAreas' => []])
