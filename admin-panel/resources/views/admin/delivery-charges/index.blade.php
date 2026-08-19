@extends('layouts.admin')

@section('title', 'Delivery Charges Settings')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
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
                            <label class="form-label fw-semibold">Order Acceptance Time (seconds)</label>
                            <input type="number" name="order_acceptance_timeout_seconds" class="form-control"
                                   value="{{ old('order_acceptance_timeout_seconds', $settings->order_acceptance_timeout_seconds ?? 180) }}"
                                   min="30" max="600" required>
                            <small class="text-muted">Time given to restaurant and driver apps to accept an incoming order. Default is 180 seconds (3 minutes).</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Zone-wise Free Delivery</label>
                            <div class="border rounded-3 overflow-hidden">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Delivery Zone</th>
                                                <th style="width: 130px;">Free</th>
                                                <th style="width: 180px;">Free Above ({{ $currencySymbol }})</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($deliveryAreas as $area)
                                                @php
                                                    $zoneInput = old("zone_free_delivery.{$area->id}", []);
                                                    $zoneEnabled = array_key_exists('enabled', $zoneInput)
                                                        ? (bool) $zoneInput['enabled']
                                                        : (bool) $area->free_delivery_enabled;
                                                    $zoneThreshold = array_key_exists('threshold', $zoneInput)
                                                        ? $zoneInput['threshold']
                                                        : $area->free_delivery_threshold;
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold">{{ $area->name }}</div>
                                                        <div class="small text-muted">{{ ucfirst($area->area_type ?? 'circle') }} zone</div>
                                                    </td>
                                                    <td>
                                                        <input type="hidden" name="zone_free_delivery[{{ $area->id }}][enabled]" value="0">
                                                        <div class="form-check form-switch mb-0">
                                                            <input class="form-check-input" type="checkbox"
                                                                   name="zone_free_delivery[{{ $area->id }}][enabled]"
                                                                   value="1"
                                                                   id="freeDeliveryZone{{ $area->id }}"
                                                                   {{ $zoneEnabled ? 'checked' : '' }}>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="number"
                                                               step="0.01"
                                                               min="0"
                                                               name="zone_free_delivery[{{ $area->id }}][threshold]"
                                                               class="form-control form-control-sm"
                                                               value="{{ $zoneThreshold }}"
                                                               placeholder="Not set">
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-4">
                                                        Create delivery zones first to enable zone-wise free delivery.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <small class="text-muted">Free delivery applies only when the customer delivery address falls inside an enabled zone and the order reaches that zone's amount.</small>
                        </div>

                        <hr>

                        <h6 class="fw-bold mb-3">Cost Sharing (For Free Delivery)</h6>
                        <p class="text-muted small mb-3">
                            This split is used for every free delivery source: zone threshold, promotion engine, scratch card coupon, delivery discount, and custom rules. Total must be 100%.
                        </p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Admin Contribution (%)</label>
                                <input type="number" step="0.01" name="admin_contribution_percent" class="form-control" 
                                       value="{{ $settings->admin_contribution_percent ?? 50 }}" required>
                                @error('admin_contribution_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Restaurant Contribution (%)</label>
                                <input type="number" step="0.01" name="restaurant_contribution_percent" class="form-control" 
                                       value="{{ $settings->restaurant_contribution_percent ?? 50 }}" required>
                                @error('restaurant_contribution_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
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
                <div class="col-md-3 mb-3">
                    <label class="form-label">Distance (km)</label>
                    <input type="number" id="calcDistance" class="form-control" value="5" step="0.5">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Order Amount ({{ $currencySymbol }})</label>
                    <input type="number" id="calcOrderAmount" class="form-control" value="300" step="10">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Delivery Zone</label>
                    <select id="calcDeliveryArea" class="form-select">
                        <option value="">No zone selected</option>
                        @foreach($deliveryAreas as $area)
                            <option value="{{ $area->id }}"
                                    data-free-enabled="{{ $area->free_delivery_enabled ? '1' : '0' }}"
                                    data-free-threshold="{{ $area->free_delivery_threshold ?? '' }}">
                                {{ $area->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
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
        const deliveryAreaInput = document.getElementById('calcDeliveryArea');
        const deliveryFeeSpan = document.getElementById('calcDeliveryFee');
        
        function calculateDelivery() {
            const distance = parseFloat(distanceInput.value) || 0;
            const orderAmount = parseFloat(orderAmountInput.value) || 0;
            const platformFee = parseFloat('{{ $settings->platform_fee ?? 0 }}') || 0;
            const selectedZone = deliveryAreaInput?.selectedOptions?.[0];
            const isZoneFree = selectedZone?.dataset?.freeEnabled === '1';
            const zoneThreshold = parseFloat(selectedZone?.dataset?.freeThreshold || '0');
            
            let fee = 0;
            
            if (isZoneFree && zoneThreshold > 0 && orderAmount >= zoneThreshold) {
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
        deliveryAreaInput?.addEventListener('change', calculateDelivery);
        calculateDelivery();
    });
</script>
@endsection

