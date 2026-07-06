@extends('layouts.admin')

@section('title', 'Delivery Charges Settings')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
@endphp

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <h1 class="mt-4">Delivery Charges Configuration</h1>
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">Delivery Charges</li>
        </ol>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Global Delivery Settings</h5>
                </div>
                <div class="p-4">
                    <form action="{{ route('admin.delivery-charges.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Charge Type</label>
                            <select name="charge_type" class="form-select" required>
                                <option value="fixed" {{ ($settings->charge_type ?? 'fixed') == 'fixed' ? 'selected' : '' }}>Fixed Charge</option>
                                <option value="per_km" {{ ($settings->charge_type ?? '') == 'per_km' ? 'selected' : '' }}>Per KM Charge</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Base Charge ({{ $currencySymbol }})</label>
                            <input type="number" step="0.01" name="base_charge" class="form-control" 
                                   value="{{ $settings->base_charge ?? 40 }}" required>
                        </div>

                        <div class="mb-3 per-km-fields" style="{{ ($settings->charge_type ?? 'fixed') == 'fixed' ? 'display:none' : '' }}">
                            <label class="form-label fw-semibold">Per KM Charge ({{ $currencySymbol }})</label>
                            <input type="number" step="0.01" name="per_km_charge" class="form-control" 
                                   value="{{ $settings->per_km_charge ?? 10 }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Platform Charge - Fixed Amount ({{ $currencySymbol }})</label>
                            <input type="number" step="0.01" name="platform_fee" class="form-control"
                                   value="{{ $settings->platform_fee ?? 0 }}" placeholder="0.00">
                            <small class="text-muted">Fixed platform charge added once per order. This is not a percentage.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Free Delivery Threshold ({{ $currencySymbol }})</label>
                            <input type="number" step="0.01" name="free_delivery_threshold" class="form-control" 
                                   value="{{ $settings->free_delivery_threshold ?? '' }}" placeholder="Leave empty for no free delivery">
                            <small class="text-muted">Order amount above which delivery is free</small>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" name="free_delivery_global" value="1" class="form-check-input" id="freeDeliveryGlobal"
                                   {{ ($settings->free_delivery_global ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="freeDeliveryGlobal">
                                Enable free delivery using this threshold
                            </label>
                        </div>

                        @php
                            $selectedFreeDeliveryDays = collect(old('free_delivery_days', $settings->free_delivery_days ?? []))->all();
                            $selectedFreeDeliveryAreas = collect(old('free_delivery_area_ids', $settings->free_delivery_area_ids ?? []))
                                ->map(fn ($value) => (string) $value)
                                ->all();
                            $weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        @endphp

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Free Delivery Days</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($weekDays as $day)
                                    <label class="form-check-label border rounded px-3 py-2 bg-light">
                                        <input class="form-check-input me-2" type="checkbox" name="free_delivery_days[]" value="{{ $day }}"
                                               {{ in_array($day, $selectedFreeDeliveryDays, true) ? 'checked' : '' }}>
                                        {{ $day }}
                                    </label>
                                @endforeach
                            </div>
                            <small class="text-muted">Leave all days unchecked to allow free delivery every day.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Free Delivery Zones</label>
                            <select name="free_delivery_area_ids[]" class="form-select" multiple size="6">
                                @foreach($deliveryAreas as $area)
                                    <option value="{{ $area->id }}" {{ in_array((string) $area->id, $selectedFreeDeliveryAreas, true) ? 'selected' : '' }}>
                                        {{ $area->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Leave empty to apply globally. Select zones to limit free delivery to matching customer delivery locations.</small>
                        </div>

                        <hr>

                        <h6 class="fw-bold mb-3">Cost Sharing (For Free Delivery)</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Admin Contribution (%)</label>
                                <input type="number" step="0.01" name="admin_contribution_percent" class="form-control" 
                                       value="{{ $settings->admin_contribution_percent ?? 50 }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Restaurant Contribution (%)</label>
                                <input type="number" step="0.01" name="restaurant_contribution_percent" class="form-control" 
                                       value="{{ $settings->restaurant_contribution_percent ?? 50 }}" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i> Save Delivery Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Restaurant-wise Minimum Order Amount</h5>
                </div>
                <div class="p-0">
                    <form action="{{ route('admin.delivery-charges.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Restaurant</th>
                                        <th>Minimum Order Amount ({{ $currencySymbol }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($restaurants as $restaurant)
                                    <tr>
                                        <td>{{ $restaurant->name }}</td>
                                        <td>
                                            <input type="number" step="0.01" name="restaurant_min_orders[{{ $restaurant->id }}]" 
                                                   class="form-control form-control-sm" 
                                                   value="{{ $restaurant->min_order_amount ?? '' }}" 
                                                   style="width: 120px" placeholder="Not set">
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i> Save Restaurant Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Calculation Preview -->
    <div class="table-card">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">Delivery Charge Calculator Preview</h5>
        </div>
        <div class="p-4">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Distance (km)</label>
                    <input type="number" id="calcDistance" class="form-control" value="5" step="0.5">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Order Amount ({{ $currencySymbol }})</label>
                    <input type="number" id="calcOrderAmount" class="form-control" value="300" step="10">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Calculated Charges</label>
                    <div class="display-5 fw-bold text-primary" id="calcDeliveryFee">{{ $currencySymbol }}0</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chargeType = document.querySelector('[name="charge_type"]');
        const perKmFields = document.querySelector('.per-km-fields');
        
        if (chargeType) {
            chargeType.addEventListener('change', function() {
                perKmFields.style.display = this.value === 'per_km' ? 'block' : 'none';
            });
        }
        
        // Calculator
        const distanceInput = document.getElementById('calcDistance');
        const orderAmountInput = document.getElementById('calcOrderAmount');
        const deliveryFeeSpan = document.getElementById('calcDeliveryFee');
        
        function calculateDelivery() {
            const distance = parseFloat(distanceInput.value) || 0;
            const orderAmount = parseFloat(orderAmountInput.value) || 0;
            const freeThreshold = parseFloat('{{ $settings->free_delivery_threshold ?? 0 }}');
            const isFreeGlobal = {{ $settings->free_delivery_global ?? 'false' ? 'true' : 'false' }};
            const platformFee = parseFloat('{{ $settings->platform_fee ?? 0 }}') || 0;
            
            let fee = 0;
            
            if (isFreeGlobal && freeThreshold > 0 && orderAmount >= freeThreshold) {
                fee = 0;
            } else {
                const chargeTypeValue = '{{ $settings->charge_type ?? "fixed" }}';
                const baseCharge = parseFloat('{{ $settings->base_charge ?? 40 }}');
                const perKmCharge = parseFloat('{{ $settings->per_km_charge ?? 10 }}');
                
                if (chargeTypeValue === 'per_km') {
                    fee = baseCharge + (distance * perKmCharge);
                    fee = Math.min(fee, 150); // Max cap
                } else {
                    fee = baseCharge;
                }
            }
            
            deliveryFeeSpan.textContent = '{{ $currencySymbol }}' + fee.toFixed(window.currencyDecimals) + (platformFee > 0 ? ' + {{ $currencySymbol }}' + platformFee.toFixed(window.currencyDecimals) + ' fixed platform charge' : '');
        }
        
        distanceInput.addEventListener('input', calculateDelivery);
        orderAmountInput.addEventListener('input', calculateDelivery);
        calculateDelivery();
    });
</script>
@endsection

