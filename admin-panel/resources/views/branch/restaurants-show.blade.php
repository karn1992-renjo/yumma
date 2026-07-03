@extends('layouts.admin')
@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $schedule = $restaurant->getFullWeekSchedule();
@endphp

@section('title', 'Branch Restaurant Details')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>{{ $restaurant->name }}</h1>
        <p>{{ $branch->name }} restaurant details, account, location, timing, and order summary.</p>
    </div>
    <div class="d-flex gap-2">
        @if(($capabilities['restaurants_edit'] ?? false) && ! $restaurant->is_verified)
            <form action="{{ route('branch.restaurants.approve', $restaurant) }}" method="POST">
                @csrf
                <button class="btn btn-success"><i class="fas fa-check me-2"></i> Approve</button>
            </form>
        @endif
        @if($capabilities['restaurants_edit'] ?? false)
            <a href="{{ route('branch.restaurants.edit', $restaurant) }}" class="btn btn-primary">
                <i class="fas fa-pen me-2"></i> Edit
            </a>
        @endif
        <a href="{{ route('branch.restaurants') }}" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i> Back
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Restaurant</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Email</strong><div>{{ $restaurant->email }}</div></div>
                    <div class="col-md-6"><strong>Phone</strong><div>{{ $restaurant->phone }}</div></div>
                    <div class="col-12"><strong>Address</strong><div>{{ $restaurant->formatted_address }}</div></div>
                    <div class="col-md-4"><strong>Coordinates</strong><div>{{ $restaurant->latitude }}, {{ $restaurant->longitude }}</div></div>
                    <div class="col-md-4"><strong>Delivery Radius</strong><div>{{ $restaurant->delivery_radius }} km</div></div>
                    <div class="col-md-4"><strong>Type</strong><div>{{ ucfirst(str_replace('_', ' ', $restaurant->restaurant_type)) }}</div></div>
                    <div class="col-md-4"><strong>Delivery Time</strong><div>{{ $restaurant->delivery_time ?? 30 }} minutes</div></div>
                    <div class="col-md-4"><strong>Order Lead Time</strong><div>{{ $restaurant->order_lead_time ?? 0 }} minutes</div></div>
                    <div class="col-md-4"><strong>Timezone</strong><div>{{ $restaurant->timezone ?? 'Asia/Kolkata' }}</div></div>
                    <div class="col-md-4"><strong>Open Status</strong><div><span class="badge bg-{{ $restaurant->isOpenNow() ? 'success' : 'secondary' }}">{{ $restaurant->isOpenNow() ? 'Open now' : 'Closed now' }}</span></div></div>
                    <div class="col-md-4"><strong>Verified</strong><div><span class="badge bg-{{ $restaurant->is_verified ? 'success' : 'warning' }}">{{ $restaurant->is_verified ? 'Approved' : 'Pending' }}</span></div></div>
                    <div class="col-md-4"><strong>Pure Veg</strong><div>{{ $restaurant->is_pure_veg ? 'Yes' : 'No' }}</div></div>
                    <div class="col-12"><strong>Description</strong><div>{{ $restaurant->description ?: 'No description added.' }}</div></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Weekly Timing</h5></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Day</th><th>Status</th><th>Open</th><th>Close</th><th>Break</th></tr></thead>
                    <tbody>
                    @foreach($schedule as $day)
                        <tr>
                            <td>{{ $day['day_name'] }}</td>
                            <td><span class="badge bg-{{ $day['is_open'] ? 'success' : 'secondary' }}">{{ $day['is_open'] ? 'Open' : 'Closed' }}</span></td>
                            <td>{{ $day['open_time_formatted'] }}</td>
                            <td>{{ $day['close_time_formatted'] }}</td>
                            <td>{{ $day['break_start'] && $day['break_end'] ? $restaurant->formatTime12Hour($day['break_start']) . ' - ' . $restaurant->formatTime12Hour($day['break_end']) : 'No break' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Recent Orders</h5></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Order</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
                    <tbody>
                    @forelse($restaurant->orders as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ ucfirst($order->status) }}</td>
                            <td>{{ $currencySymbol }}{{ number_format($order->total, 2) }}</td>
                            <td>{{ optional($order->created_at)->format('d M Y, h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4">No orders found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Summary</h5></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>Total Orders</span><strong>{{ $totalOrders }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Delivered Revenue</span><strong>{{ $currencySymbol }}{{ number_format($totalRevenue, 2) }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Menu Items</span><strong>{{ $totalMenuItems }}</strong></div>
                <div class="d-flex justify-content-between"><span>Average Rating</span><strong>{{ number_format($averageRating, 1) }}</strong></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Owner & Account</h5></div>
            <div class="card-body">
                <div class="mb-2"><strong>{{ $restaurant->owner?->name ?? 'N/A' }}</strong></div>
                <div class="text-muted">{{ $restaurant->owner?->email }}</div>
                <div class="text-muted mb-3">{{ $restaurant->owner?->phone }}</div>
                <div class="small"><strong>Account Holder:</strong> {{ $restaurant->owner?->account_holder_name ?: 'Not set' }}</div>
                <div class="small"><strong>Bank:</strong> {{ $restaurant->owner?->bank_name ?: 'Not set' }}</div>
                <div class="small"><strong>Account No:</strong> {{ $restaurant->owner?->account_number ?: 'Not set' }}</div>
                <div class="small"><strong>Routing:</strong> {{ $restaurant->owner?->routing_code ?: $restaurant->owner?->ifsc_code ?: 'Not set' }}</div>
                <div class="small"><strong>UPI:</strong> {{ $restaurant->owner?->upi_id ?: 'Not set' }}</div>
                <div class="small"><strong>Gateway:</strong> {{ $restaurant->owner?->gateway_account_id ?: $restaurant->owner?->stripe_account_id ?: 'Not set' }}</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Commercials</h5></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>Min Order</span><strong>{{ $currencySymbol }}{{ number_format($restaurant->min_order_amount, 2) }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Delivery Fee</span><strong>{{ $currencySymbol }}{{ number_format($restaurant->delivery_fee, 2) }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Dining Charge</span><strong>{{ $currencySymbol }}{{ number_format($restaurant->dining_charge ?? 0, 2) }}</strong></div>
                <div class="d-flex justify-content-between"><span>Commission</span><strong>{{ $restaurant->commission_calculation_type === 'global' ? 'Global' : (($restaurant->commission_rate ?? 0) . ($restaurant->commission_calculation_type === 'percentage' ? '%' : '')) }}</strong></div>
            </div>
        </div>
    </div>
</div>
@endsection
