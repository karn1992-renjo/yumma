@php
    $restaurant = $restaurant ?? null;
    $selectedCuisines = old('cuisine', $restaurant?->cuisine ?? []);
    $owner = $restaurant?->owner;
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Restaurant Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $restaurant?->name) }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $restaurant?->email) }}" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $restaurant?->phone) }}" required>
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
        <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $restaurant?->city) }}" required>
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">State <span class="text-danger">*</span></label>
        <input type="text" name="state" class="form-control @error('state') is-invalid @enderror" value="{{ old('state', $restaurant?->state) }}" required>
        @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Pincode <span class="text-danger">*</span></label>
        <input type="text" name="pincode" class="form-control @error('pincode') is-invalid @enderror" value="{{ old('pincode', $restaurant?->pincode) }}" required>
        @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Restaurant Location <span class="text-danger">*</span></label>
        <div class="input-group mb-2">
            <input type="text" id="searchAddress" class="form-control" placeholder="Search address or landmark" value="{{ old('address', $restaurant?->address) }}">
            <button type="button" class="btn btn-outline-secondary" id="searchAddressBtn">Search</button>
            <button type="button" class="btn btn-outline-secondary" id="useLocationBtn">Use My Location</button>
        </div>
        <textarea id="address" name="address" rows="2" class="form-control @error('address') is-invalid @enderror" required>{{ old('address', $restaurant?->address) }}</textarea>
        <div id="restaurantMap" class="border rounded mt-3"></div>
        <div id="mapLegend" class="map-legend mt-2">Delivery radius: <strong>{{ old('delivery_radius', $restaurant?->delivery_radius ?? 5) }} km</strong>. Drag the marker or click the map to move service area.</div>
        <div class="zone-preview-card mt-3">
            <div class="zone-preview-label">Branch Delivery Zone</div>
            <div class="fw-bold" id="zonePreviewName">Use current location or search to detect zone</div>
            <div class="text-muted small" id="zonePreviewMeta">Only restaurants inside this branch's active mapped zone can be saved or approved.</div>
        </div>
        <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $restaurant?->latitude) }}">
        <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $restaurant?->longitude) }}">
        <div id="locationStatus" class="form-text">Use the map, geolocation or address search to set location and radius.</div>
        @error('address') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        @error('latitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        @error('longitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Delivery Radius <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" max="100" name="delivery_radius" class="form-control @error('delivery_radius') is-invalid @enderror" value="{{ old('delivery_radius', $restaurant?->delivery_radius ?? 5) }}" required>
        @error('delivery_radius') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
        <select name="restaurant_type" class="form-select @error('restaurant_type') is-invalid @enderror" required>
            @foreach(App\Models\Restaurant::validServiceTypes() as $type)
                <option value="{{ $type }}" @selected(old('restaurant_type', $restaurant?->restaurant_type ?? 'delivery') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
            @endforeach
        </select>
        @error('restaurant_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Min Order</label>
        <input type="number" step="0.01" min="0" name="min_order_amount" class="form-control" value="{{ old('min_order_amount', $restaurant?->min_order_amount ?? 0) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Delivery Fee</label>
        <input type="number" step="0.01" min="0" name="delivery_fee" class="form-control" value="{{ old('delivery_fee', $restaurant?->delivery_fee ?? 0) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Dining Charge ({{ $currencySymbol }})</label>
        <input type="number" step="0.01" min="0" name="dining_charge" class="form-control" value="{{ old('dining_charge', $restaurant?->dining_charge ?? 0) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">FSSAI License</label>
        <input type="text" name="fssai_license_number" class="form-control" value="{{ old('fssai_license_number', $restaurant?->fssai_license_number) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Commission Type</label>
        <select name="commission_calculation_type" class="form-select @error('commission_calculation_type') is-invalid @enderror" required>
            @php($commissionType = old('commission_calculation_type', $restaurant?->commission_calculation_type ?? 'global'))
            <option value="global" @selected($commissionType === 'global')>Use global setting</option>
            <option value="percentage" @selected($commissionType === 'percentage')>Percentage</option>
            <option value="fixed" @selected($commissionType === 'fixed')>Fixed amount</option>
        </select>
        @error('commission_calculation_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Commission Value</label>
        <input type="number" name="commission_rate" class="form-control @error('commission_rate') is-invalid @enderror" value="{{ old('commission_rate', $restaurant?->commission_rate) }}" min="0" step="0.01" placeholder="Uses global setting when blank">
        @error('commission_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Delivery Time (minutes)</label>
        <input type="number" name="delivery_time" class="form-control @error('delivery_time') is-invalid @enderror" value="{{ old('delivery_time', $restaurant?->delivery_time ?? 30) }}" min="1" max="240">
        @error('delivery_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Order Lead Time (minutes)</label>
        <input type="number" name="order_lead_time" class="form-control @error('order_lead_time') is-invalid @enderror" value="{{ old('order_lead_time', $restaurant?->order_lead_time ?? 0) }}" min="0" max="240">
        @error('order_lead_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Opening Time</label>
        <input type="time" name="open_time" class="form-control @error('open_time') is-invalid @enderror" value="{{ old('open_time', $restaurant?->open_time ?? '09:00') }}">
        @error('open_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Closing Time</label>
        <input type="time" name="close_time" class="form-control @error('close_time') is-invalid @enderror" value="{{ old('close_time', $restaurant?->close_time ?? '22:00') }}">
        @error('close_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Timezone</label>
        <input type="text" name="timezone" class="form-control @error('timezone') is-invalid @enderror" value="{{ old('timezone', $restaurant?->timezone ?? 'Asia/Kolkata') }}">
        @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Cuisine</label>
        <select name="cuisine[]" class="form-select" multiple>
            @foreach($cuisines as $cuisine)
                <option value="{{ $cuisine->name }}" @selected(in_array($cuisine->name, $selectedCuisines, true))>{{ $cuisine->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" rows="3" class="form-control">{{ old('description', $restaurant?->description) }}</textarea>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Logo</label>
        @if($restaurant?->logo_image)
            <div class="mb-2"><img src="{{ Storage::url($restaurant->logo_image) }}" height="60" alt="Logo"></div>
        @endif
        <input type="file" name="logo" class="form-control" accept="image/*">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Banner</label>
        @if($restaurant?->banner_image)
            <div class="mb-2"><img src="{{ Storage::url($restaurant->banner_image) }}" height="60" alt="Banner"></div>
        @endif
        <input type="file" name="banner" class="form-control" accept="image/*">
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_pure_veg" value="1" id="isPureVeg" @checked(old('is_pure_veg', $restaurant?->is_pure_veg))>
            <label class="form-check-label fw-semibold" for="isPureVeg">Pure Veg</label>
        </div>
    </div>

    <div class="col-12"><hr><h5 class="fw-bold mb-0">Owner Login</h5></div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Owner Name <span class="text-danger">*</span></label>
        <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name', $owner?->name) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Owner Email <span class="text-danger">*</span></label>
        <input type="email" name="owner_email" class="form-control" value="{{ old('owner_email', $owner?->email) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Owner Phone <span class="text-danger">*</span></label>
        <input type="text" name="owner_phone" class="form-control" value="{{ old('owner_phone', $owner?->phone) }}" required>
    </div>
    @unless($restaurant)
        <div class="col-md-6">
            <label class="form-label fw-semibold">Owner Password <span class="text-danger">*</span></label>
            <input type="password" name="owner_password" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="owner_password_confirmation" class="form-control" required>
        </div>
    @else
        <div class="col-md-6">
            <label class="form-label fw-semibold">New Owner Password</label>
            <input type="password" name="owner_password" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Confirm New Password</label>
            <input type="password" name="owner_password_confirmation" class="form-control">
        </div>
    @endunless

    @include('admin.partials.payout-account-fields', ['values' => [
        'account_holder_name' => old('account_holder_name', $owner?->account_holder_name),
        'bank_name' => old('bank_name', $owner?->bank_name),
        'account_number' => old('account_number', $owner?->account_number),
        'ifsc_code' => old('ifsc_code', $owner?->ifsc_code),
        'upi_id' => old('upi_id', $owner?->upi_id),
        'stripe_account_id' => old('stripe_account_id', $owner?->stripe_account_id ?? $owner?->gateway_account_id),
        'gateway_account_id' => old('gateway_account_id', $owner?->gateway_account_id ?? $owner?->stripe_account_id),
    ]])
</div>
