@extends('layouts.admin')

@section('title', 'Commission Settings')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $adminSetting = $settings->where('type', 'admin')->first();
    $restaurantSetting = $settings->where('type', 'restaurant')->first();
    $driverSetting = $settings->where('type', 'delivery_partner')->first();
    $gstOnCommissionRate = (float) App\Models\AppSetting::getValue('gst_rate', 18);
    $gatewayFeeRate = (float) App\Models\AppSetting::getValue('gateway_fee_rate', 2);
    $commissionLabels = [
        'admin' => 'Admin Delivery Commission',
        'restaurant' => 'Platform Commission Charged to Restaurant',
        'delivery_partner' => 'Driver Deduction',
    ];
    $formatCommission = fn ($setting, $fallback) => ($setting?->calculation_type ?? 'percentage') === 'fixed'
        ? $currencySymbol . number_format((float) ($setting?->rate ?? $fallback), $currencyDecimals)
        : number_format((float) ($setting?->rate ?? $fallback), 2) . '%';
    $commissionAmount = fn ($setting, $base, $fallback) => min($base, ($setting?->calculation_type ?? 'percentage') === 'fixed'
        ? (float) ($setting?->rate ?? $fallback)
        : $base * ((float) ($setting?->rate ?? $fallback) / 100));
    $sampleSubtotal = 500;
    $sampleRestaurantCommission = $commissionAmount($restaurantSetting, $sampleSubtotal, 15);
    $sampleGst = $sampleRestaurantCommission * ($gstOnCommissionRate / 100);
    $sampleDeliveryFee = 40;
    $samplePlatformFee = App\Models\DeliveryChargeSetting::getPlatformFee();
    $sampleCustomerTax = App\Models\TaxSetting::calculateTax($sampleSubtotal, $sampleDeliveryFee);
    $sampleCustomerTotal = $sampleSubtotal + $sampleDeliveryFee + $samplePlatformFee + $sampleCustomerTax;
    $sampleGatewayFee = $sampleCustomerTotal * ($gatewayFeeRate / 100);
    $sampleRestaurantPayout = $sampleSubtotal - $sampleRestaurantCommission - $sampleGst - $sampleGatewayFee;
    $sampleAdminDeliveryCommission = $commissionAmount($adminSetting, $sampleDeliveryFee, 15);
    $sampleDriverDeduction = min(
        max(0, $sampleDeliveryFee - $sampleAdminDeliveryCommission),
        $commissionAmount($driverSetting, $sampleDeliveryFee, 5)
    );
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1>Commission Settings</h1>
        <p class="text-muted mb-0">Configure platform commission rates</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card p-3 text-center">
            <div class="icon primary mx-auto mb-3" style="width: 60px; height: 60px;">
                <i class="fas fa-percent fa-2x"></i>
            </div>
            <h6 class="text-muted mb-1">Admin Delivery Commission</h6>
            <div class="h2 fw-bold text-primary">{{ $formatCommission($adminSetting, 15) }}</div>
            <small class="text-muted">Applied on delivery fees</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-3 text-center">
            <div class="icon success mx-auto mb-3" style="width: 60px; height: 60px;">
                <i class="fas fa-utensils fa-2x"></i>
            </div>
            <h6 class="text-muted mb-1">Platform Commission Charged to Restaurant</h6>
            <div class="h2 fw-bold text-success">{{ $formatCommission($restaurantSetting, 15) }}</div>
            <small class="text-muted">Applied on order subtotal</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-3 text-center">
            <div class="icon warning mx-auto mb-3" style="width: 60px; height: 60px;">
                <i class="fas fa-motorcycle fa-2x"></i>
            </div>
            <h6 class="text-muted mb-1">Driver Deduction</h6>
            <div class="h2 fw-bold text-warning">{{ $formatCommission($driverSetting, 5) }}</div>
            <small class="text-muted">Applied on delivery fees</small>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Commission Rates Configuration</h5>
        <button type="button" class="btn btn-primary rounded-3 btn-sm" data-bs-toggle="modal" data-bs-target="#editCommissionModal">
            <i class="fas fa-edit me-1"></i> Edit Rates
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Calculation Type</th>
                    <th>Last Updated</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($settings as $setting)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $commissionLabels[$setting->type] ?? ucfirst(str_replace('_', ' ', $setting->type)) }}</span>
                    </td>
                    <td>{{ $formatCommission($setting, 0) }}</td>
                    <td>{{ ucfirst($setting->calculation_type) }}</td>
                    <td>{{ $setting->updated_at->format('d M Y, h:i A') }}</td>
                    <td>
                        <span class="badge {{ $setting->is_active ? 'bg-success' : 'bg-secondary' }} rounded-3">
                            {{ $setting->is_active ? 'Active' : 'Inactive' }}
                        </span>
                     </td>
                 </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="stat-card">
            <h5 class="mb-3 fw-bold">
                <i class="fas fa-chart-pie me-2 text-primary"></i> Commission Calculation Example
            </h5>
            <div class="bg-light rounded-3 p-3 mb-3">
                <h6 class="mb-2">Food Subtotal: {{ $currencySymbol }}{{ number_format($sampleSubtotal, $currencyDecimals) }}</h6>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Platform Commission Charged to Restaurant ({{ $formatCommission($restaurantSetting, 15) }}):</span>
                    <span class="fw-bold">{{ $currencySymbol }}{{ number_format($sampleRestaurantCommission, $currencyDecimals) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>GST on Platform Commission:</span>
                    <span class="fw-bold">{{ $currencySymbol }}{{ number_format($sampleGst, $currencyDecimals) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Online Payment Gateway Fee:</span>
                    <span class="fw-bold">{{ $currencySymbol }}{{ number_format($sampleGatewayFee, $currencyDecimals) }}</span>
                </div>
                <div class="d-flex justify-content-between pt-2 border-top">
                    <span>Net Restaurant Payout:</span>
                    <span class="fw-bold text-success">{{ $currencySymbol }}{{ number_format($sampleRestaurantPayout, $currencyDecimals) }}</span>
                </div>
            </div>
            <div class="bg-light rounded-3 p-3">
                <h6 class="mb-2">Delivery Fee: {{ $currencySymbol }}{{ number_format($sampleDeliveryFee, $currencyDecimals) }}</h6>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Admin Delivery Commission ({{ $formatCommission($adminSetting, 15) }}):</span>
                    <span class="fw-bold">{{ $currencySymbol }}{{ number_format($sampleAdminDeliveryCommission, $currencyDecimals) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Driver Deduction ({{ $formatCommission($driverSetting, 5) }}):</span>
                    <span class="fw-bold">{{ $currencySymbol }}{{ number_format($sampleDriverDeduction, $currencyDecimals) }}</span>
                </div>
                <div class="d-flex justify-content-between pt-2 border-top">
                    <span>Driver Earning:</span>
                    <span class="fw-bold text-success">{{ $currencySymbol }}{{ number_format(max(0, $sampleDeliveryFee - $sampleAdminDeliveryCommission - $sampleDriverDeduction), $currencyDecimals) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="stat-card">
            <h5 class="mb-3 fw-bold">
                <i class="fas fa-bolt me-2 text-primary"></i> Quick Actions
            </h5>
            <div class="d-grid gap-2">
                <a href="{{ route('admin.payouts.index') }}" class="btn btn-outline-primary rounded-3">
                    <i class="fas fa-history me-2"></i> View Payouts
                </a>
                <button type="button" class="btn btn-outline-success rounded-3" data-bs-toggle="modal" data-bs-target="#generatePayoutModal">
                    <i class="fas fa-chart-line me-2"></i> Generate Payouts
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Commission Modal -->
<div class="modal fade" id="editCommissionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form action="{{ route('admin.commissions.settings') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="modal-title fw-bold">Edit Commission Rates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Admin Delivery Commission</label>
                        <select name="admin_calculation_type" class="form-select mb-2" required>
                            <option value="percentage" @selected(old('admin_calculation_type', $adminSetting?->calculation_type ?? 'percentage') === 'percentage')>Percentage</option>
                            <option value="fixed" @selected(old('admin_calculation_type', $adminSetting?->calculation_type) === 'fixed')>Fixed amount</option>
                        </select>
                        <input type="number" step="0.01" name="admin_commission_rate" 
                               class="form-control @error('admin_commission_rate') is-invalid @enderror" min="0" required
                               value="{{ old('admin_commission_rate', $adminSetting?->rate ?? 15) }}">
                        @error('admin_commission_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Applied on delivery fees</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Platform Commission Charged to Restaurant</label>
                        <select name="restaurant_calculation_type" class="form-select mb-2" required>
                            <option value="percentage" @selected(old('restaurant_calculation_type', $restaurantSetting?->calculation_type ?? 'percentage') === 'percentage')>Percentage</option>
                            <option value="fixed" @selected(old('restaurant_calculation_type', $restaurantSetting?->calculation_type) === 'fixed')>Fixed amount</option>
                        </select>
                        <input type="number" step="0.01" name="restaurant_commission_rate" 
                               class="form-control @error('restaurant_commission_rate') is-invalid @enderror" min="0" required
                               value="{{ old('restaurant_commission_rate', $restaurantSetting?->rate ?? 15) }}">
                        @error('restaurant_commission_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Deducted from the restaurant's food subtotal before payout</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Driver Deduction</label>
                        <select name="delivery_partner_calculation_type" class="form-select mb-2" required>
                            <option value="percentage" @selected(old('delivery_partner_calculation_type', $driverSetting?->calculation_type ?? 'percentage') === 'percentage')>Percentage</option>
                            <option value="fixed" @selected(old('delivery_partner_calculation_type', $driverSetting?->calculation_type) === 'fixed')>Fixed amount</option>
                        </select>
                        <input type="number" step="0.01" name="delivery_partner_commission_rate" 
                               class="form-control @error('delivery_partner_commission_rate') is-invalid @enderror" min="0" required
                               value="{{ old('delivery_partner_commission_rate', $driverSetting?->rate ?? 5) }}">
                        @error('delivery_partner_commission_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Applied on delivery fees</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">GST on Platform Commission (%)</label>
                        <input type="number" step="0.01" name="gst_on_commission_rate"
                               class="form-control @error('gst_on_commission_rate') is-invalid @enderror" min="0" max="100" required
                               value="{{ old('gst_on_commission_rate', $gstOnCommissionRate) }}">
                        @error('gst_on_commission_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Applied only to the platform commission charged to the restaurant</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Online Payment Gateway Fee (%)</label>
                        <input type="number" step="0.01" name="gateway_fee_rate"
                               class="form-control @error('gateway_fee_rate') is-invalid @enderror" min="0" max="100" required
                               value="{{ old('gateway_fee_rate', $gatewayFeeRate) }}">
                        @error('gateway_fee_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="text-muted">Applied to the customer total for online payments; set 0 to disable</small>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generate Payout Modal -->
<div class="modal fade" id="generatePayoutModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <form action="{{ route('admin.payouts.generate') }}" method="POST">
                @csrf
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="modal-title fw-bold">Generate Payouts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Period Type</label>
                        <select name="period_type" class="form-select" required>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3">Generate Payouts</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

