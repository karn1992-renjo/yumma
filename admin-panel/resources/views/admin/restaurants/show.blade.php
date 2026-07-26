@extends('layouts.admin')

@section('title', 'Restaurant Details')
@section('header', 'Restaurant Details')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $money = fn ($value) => $currencySymbol . number_format((float) $value, $currencyDecimals);
    $formatCommissionRule = $commissionRule['type'] === 'fixed'
        ? $money($commissionRule['value'])
        : number_format($commissionRule['value'], 2) . '%';
    $statusChip = $restaurant->is_open ? ['Open', 'success', 'store'] : ['Closed', 'neutral', 'store-slash'];
    $verificationChip = $restaurant->is_verified ? ['Verified', 'success', 'circle-check'] : ['Pending Verification', 'warning', 'clock'];
    $statTiles = [
        ['label' => 'Total Orders', 'value' => number_format($totalOrders), 'icon' => 'receipt', 'tone' => '#8b5cf6'],
        ['label' => 'Delivered Revenue', 'value' => $money($totalRevenue), 'icon' => 'indian-rupee-sign', 'tone' => '#16a34a'],
        ['label' => 'Average Rating', 'value' => number_format($averageRating, 1), 'icon' => 'star', 'tone' => '#f59e0b'],
        ['label' => 'Menu Items', 'value' => number_format($totalMenuItems), 'icon' => 'utensils', 'tone' => '#3b82f6'],
    ];
@endphp

@section('styles')
<style>
    .restaurant-page-shell { display: grid; gap: 16px; max-width: 100%; min-width: 0; }
    .restaurant-page-hero,
    .restaurant-info-panel,
    .restaurant-stat-tile {
        border: 1px solid rgba(226, 232, 240, .92);
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 16px 42px rgba(15, 23, 42, .06);
    }
    .restaurant-page-hero {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 16px 18px;
        border-radius: 22px;
    }
    .restaurant-page-title { margin: 0; color: #0f172a; font-size: 24px; font-weight: 950; line-height: 1.1; letter-spacing: 0; }
    .restaurant-page-subtitle { margin-top: 6px; color: #64748b; font-size: 12px; font-weight: 750; }
    .restaurant-page-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 9px; }
    .restaurant-chip {
        display: inline-flex; align-items: center; gap: 7px; border-radius: 999px; padding: 7px 11px;
        border: 1px solid rgba(226, 232, 240, .92); font-size: 12px; font-weight: 900; white-space: nowrap;
    }
    .restaurant-chip.success { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
    .restaurant-chip.warning { color: #92400e; background: #fffbeb; border-color: #fde68a; }
    .restaurant-chip.neutral { color: #475569; background: #f8fafc; }
    .restaurant-chip.primary { color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; }
    .restaurant-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
    .restaurant-stat-tile { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 92px; padding: 15px; border-radius: 22px; }
    .restaurant-stat-label { color: #64748b; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
    .restaurant-stat-value { margin-top: 6px; color: #0f172a; font-size: 23px; font-weight: 950; line-height: 1; }
    .restaurant-stat-icon { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 15px; color: var(--tile-color); background: color-mix(in srgb, var(--tile-color) 12%, white); }
    .restaurant-show-grid { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(330px, .75fr); gap: 16px; align-items: start; }
    .restaurant-info-panel { overflow: hidden; border-radius: 22px; min-width: 0; }
    .restaurant-info-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 15px 18px; border-bottom: 1px solid rgba(226, 232, 240, .88); }
    .restaurant-info-header h2 { margin: 0; color: #0f172a; font-size: 16px; font-weight: 950; }
    .restaurant-info-header p { margin: 3px 0 0; color: #64748b; font-size: 12px; font-weight: 700; }
    .restaurant-info-body { padding: 18px; }
    .restaurant-line { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding: 11px 0; border-bottom: 1px solid rgba(226, 232, 240, .78); }
    .restaurant-line:last-child { border-bottom: 0; }
    .restaurant-line-label { color: #64748b; font-size: 12px; font-weight: 800; }
    .restaurant-line-value { color: #0f172a; font-size: 13px; font-weight: 900; text-align: right; word-break: break-word; }
    .restaurant-table { margin: 0; }
    .restaurant-table th { color: #64748b; font-size: 11px; font-weight: 900; text-transform: uppercase; background: #f8fafc; border-color: #e2e8f0; }
    .restaurant-table td { color: #0f172a; font-size: 12px; font-weight: 750; vertical-align: middle; border-color: #e2e8f0; }
    .restaurant-media { width: 100%; max-height: 160px; object-fit: cover; border-radius: 18px; border: 1px solid #e2e8f0; background: #f8fafc; }
    @media (max-width: 1199.98px) {
        .restaurant-show-grid,
        .restaurant-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767.98px) {
        .restaurant-page-hero,
        .restaurant-show-grid,
        .restaurant-stat-grid { grid-template-columns: 1fr; }
        .restaurant-page-actions { justify-content: flex-start; }
    }
</style>
@endsection

@section('content')
<div class="restaurant-page-shell">
    <section class="restaurant-page-hero">
        <div>
            <h1 class="restaurant-page-title">{{ $restaurant->name }}</h1>
            <div class="restaurant-page-subtitle">
                {{ $restaurant->email }} | {{ $restaurant->phone }} | {{ ucfirst(str_replace('_', ' ', $restaurant->restaurant_type ?? 'delivery')) }}
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <span class="restaurant-chip {{ $statusChip[1] }}"><i class="fas fa-{{ $statusChip[2] }}"></i>{{ $statusChip[0] }}</span>
                <span class="restaurant-chip {{ $verificationChip[1] }}"><i class="fas fa-{{ $verificationChip[2] }}"></i>{{ $verificationChip[0] }}</span>
                @if($restaurant->is_featured)
                    <span class="restaurant-chip primary"><i class="fas fa-star"></i>Featured</span>
                @endif
            </div>
        </div>
        <div class="restaurant-page-actions">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adjustPricesModal">
                <i class="fas fa-percent me-2"></i>Adjust Prices
            </button>
            <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-light">
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

    <section class="restaurant-info-panel">
        <div class="restaurant-info-header">
            <div>
                <h2>Financial Summary</h2>
                <p>{{ $commissionRule['source'] }}: {{ $formatCommissionRule }}</p>
            </div>
        </div>
        <div class="restaurant-info-body">
            <div class="row g-3">
                @foreach([
                    'Delivered Orders' => number_format((int) ($financialSummary['delivered_orders'] ?? 0)),
                    'Food Subtotal' => $money($financialSummary['food_subtotal'] ?? 0),
                    'Restaurant Commission' => $money($financialSummary['restaurant_commission'] ?? 0),
                    'Net Restaurant Earning' => $money($financialSummary['restaurant_earning'] ?? 0),
                    'GST On Commission' => $money($financialSummary['gst_on_commission'] ?? 0),
                    'Gateway Fees' => $money($financialSummary['gateway_fees'] ?? 0),
                    'Pending Payout' => $money($financialSummary['pending_earning'] ?? 0),
                    'Released Payout' => $money($financialSummary['released_earning'] ?? 0),
                    'Branch Commission' => $money($financialSummary['branch_commission'] ?? 0),
                    'Admin Earning' => $money($financialSummary['admin_earning'] ?? 0),
                    'Platform Charges' => $money($financialSummary['platform_charges'] ?? 0),
                    'Customer Order Total' => $money($financialSummary['customer_total'] ?? 0),
                ] as $label => $value)
                    <div class="col-xl-3 col-md-4 col-sm-6">
                        <div class="restaurant-line d-block border-0 p-0">
                            <div class="restaurant-line-label">{{ $label }}</div>
                            <div class="restaurant-line-value text-start mt-1">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="restaurant-show-grid">
        <div class="restaurant-page-shell">
            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Restaurant Profile</h2><p>Contact, status and service configuration.</p></div></div>
                <div class="restaurant-info-body">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Name</span><span class="restaurant-line-value">{{ $restaurant->name }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Email</span><span class="restaurant-line-value">{{ $restaurant->email }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Phone</span><span class="restaurant-line-value">{{ $restaurant->phone }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Type</span><span class="restaurant-line-value">{{ ucfirst(str_replace('_', ' ', $restaurant->restaurant_type ?? 'delivery')) }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Pure Veg</span><span class="restaurant-line-value">{{ $restaurant->is_pure_veg ? 'Yes' : 'No' }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">FSSAI</span><span class="restaurant-line-value">{{ $restaurant->fssai_license_number ?: 'N/A' }}</span></div></div>
                        <div class="col-12"><div class="restaurant-line"><span class="restaurant-line-label">Description</span><span class="restaurant-line-value">{{ $restaurant->description ?: 'N/A' }}</span></div></div>
                    </div>
                </div>
            </section>

            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Location And Configuration</h2><p>Address, coverage, timing and fees.</p></div></div>
                <div class="restaurant-info-body">
                    <div class="row g-3">
                        <div class="col-12"><div class="restaurant-line"><span class="restaurant-line-label">Address</span><span class="restaurant-line-value">{{ $restaurant->address }}, {{ $restaurant->city }}, {{ $restaurant->state }} {{ $restaurant->pincode }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Coordinates</span><span class="restaurant-line-value">{{ $restaurant->latitude }}, {{ $restaurant->longitude }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Delivery Radius</span><span class="restaurant-line-value">{{ $restaurant->delivery_radius }} km</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Min Order</span><span class="restaurant-line-value">{{ $money($restaurant->min_order_amount) }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Delivery Fee</span><span class="restaurant-line-value">{{ $money($restaurant->delivery_fee) }}</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Delivery Time</span><span class="restaurant-line-value">{{ $restaurant->delivery_time ?? 30 }} min</span></div></div>
                        <div class="col-md-6"><div class="restaurant-line"><span class="restaurant-line-label">Order Lead Time</span><span class="restaurant-line-value">{{ $restaurant->order_lead_time ?? 0 }} min</span></div></div>
                    </div>
                </div>
            </section>

            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Recent Orders</h2><p>Last 10 orders for this restaurant.</p></div></div>
                <div class="table-responsive">
                    <table class="table restaurant-table">
                        <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Net Earning</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        @forelse($restaurant->orders as $order)
                            <tr>
                                <td>#{{ $order->order_number ?? $order->id }}</td>
                                <td>{{ $order->customer->name ?? $order->customer_name ?? 'Guest' }}</td>
                                <td>{{ $money($order->total) }}</td>
                                <td class="text-success fw-bold">{{ $money($order->restaurant_earning) }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                                <td>{{ $order->created_at->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No orders found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Recent Menu Items</h2><p>Last 10 menu records.</p></div></div>
                <div class="table-responsive">
                    <table class="table restaurant-table">
                        <thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($restaurant->menuItems as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->category->name ?? 'N/A' }}</td>
                                <td>{{ $money($item->price) }}</td>
                                <td>{{ ($item->is_available ?? $item->is_active ?? false) ? 'Available' : 'Unavailable' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No menu items found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="restaurant-page-shell">
            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Owner</h2><p>Restaurant owner account.</p></div></div>
                <div class="restaurant-info-body">
                    <div class="restaurant-line"><span class="restaurant-line-label">Name</span><span class="restaurant-line-value">{{ $restaurant->owner->name ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Email</span><span class="restaurant-line-value">{{ $restaurant->owner->email ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Phone</span><span class="restaurant-line-value">{{ $restaurant->owner->phone ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Status</span><span class="restaurant-line-value">{{ optional($restaurant->owner)->email_verified_at ? 'Verified' : 'Unverified' }}</span></div>
                </div>
            </section>

            <section class="restaurant-info-panel">
                <div class="restaurant-info-header"><div><h2>Payout Account</h2><p>Settlement details.</p></div></div>
                <div class="restaurant-info-body">
                    <div class="restaurant-line"><span class="restaurant-line-label">Account Holder</span><span class="restaurant-line-value">{{ $restaurant->owner->account_holder_name ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Bank</span><span class="restaurant-line-value">{{ $restaurant->owner->bank_name ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Account No</span><span class="restaurant-line-value">{{ $restaurant->owner->account_number ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">IFSC / Routing</span><span class="restaurant-line-value">{{ $restaurant->owner->routing_code ?? $restaurant->owner->ifsc_code ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">UPI</span><span class="restaurant-line-value">{{ $restaurant->owner->upi_id ?? 'N/A' }}</span></div>
                    <div class="restaurant-line"><span class="restaurant-line-label">Gateway</span><span class="restaurant-line-value">{{ $restaurant->owner->gateway_account_id ?? $restaurant->owner->stripe_account_id ?? 'N/A' }}</span></div>
                </div>
            </section>

            @if($restaurant->logo_image || $restaurant->banner_image)
                <section class="restaurant-info-panel">
                    <div class="restaurant-info-header"><div><h2>Media</h2><p>Current logo and banner.</p></div></div>
                    <div class="restaurant-info-body d-grid gap-3">
                        @if($restaurant->logo_image)
                            <div>
                                <div class="restaurant-line-label mb-2">Logo</div>
                                <img src="{{ Storage::url($restaurant->logo_image) }}" class="restaurant-media" alt="Logo">
                            </div>
                        @endif
                        @if($restaurant->banner_image)
                            <div>
                                <div class="restaurant-line-label mb-2">Banner</div>
                                <img src="{{ Storage::url($restaurant->banner_image) }}" class="restaurant-media" alt="Banner">
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>

<div class="modal fade" id="adjustPricesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form method="POST" action="{{ route('admin.restaurants.increase-menu-prices', $restaurant) }}">
                @csrf
                <div class="modal-header border-0" style="background: linear-gradient(135deg, #111827, var(--primary));">
                    <h5 class="modal-title text-white fw-bold">Adjust {{ $restaurant->name }} Prices</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Direction</label>
                            <select class="form-select" name="direction"><option value="increase">Increase</option><option value="decrease">Decrease</option></select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Method</label>
                            <select class="form-select" name="adjustment_type"><option value="percentage">Percentage</option><option value="fixed">Fixed amount</option></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Value</label>
                            <input class="form-control" type="number" name="value" min="0.01" step="0.01" required>
                            <div class="form-text">Applies to every base and discounted menu price.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
