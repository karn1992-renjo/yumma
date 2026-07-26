@php
    $restaurant = $restaurant ?? null;
    $owner = $restaurant?->owner;
    $panel = $panel ?? 'admin';
    $isEdit = (bool) $restaurant;
    $showOwnerLogin = $showOwnerLogin ?? (!$isEdit || $panel === 'branch');
    $showAdminStatus = $showAdminStatus ?? ($panel === 'admin' && $isEdit);
    $cuisineValueField = $cuisineValueField ?? ($panel === 'branch' ? 'name' : 'id');
    $selectedCuisines = (array) old('cuisine', $restaurant?->cuisine ?? []);
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $restaurantTypes = App\Models\Restaurant::validServiceTypes();
    $commissionType = old('commission_calculation_type', $restaurant?->commission_calculation_type ?? 'global');
    $field = fn ($name, $fallback = null) => old($name, $restaurant?->{$name} ?? $fallback);
@endphp

<div class="restaurant-form-grid">
    <section class="restaurant-form-panel span-8">
        <div class="restaurant-form-panel-header">
            <div>
                <h2>Restaurant Profile</h2>
                <p>Name, public contact details, cuisine and service mode.</p>
            </div>
        </div>
        <div class="restaurant-form-panel-body">
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label fw-bold">Restaurant Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ $field('name') }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ $field('email') }}" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ $field('phone') }}" required>
                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold">Restaurant Type <span class="text-danger">*</span></label>
                    <select name="restaurant_type" class="form-select @error('restaurant_type') is-invalid @enderror" required>
                        @foreach($restaurantTypes as $type)
                            <option value="{{ $type }}" @selected(old('restaurant_type', $restaurant?->restaurant_type ?? 'delivery') === $type)>
                                {{ ucfirst(str_replace('_', ' ', $type)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('restaurant_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold">Cuisine</label>
                    <select name="cuisine[]" class="form-select" multiple>
                        @forelse($cuisines as $cuisine)
                            @php($cuisineValue = (string) ($cuisineValueField === 'name' ? $cuisine->name : $cuisine->id))
                            <option value="{{ $cuisineValue }}" @selected(in_array($cuisineValue, array_map('strval', $selectedCuisines), true))>
                                {{ $cuisine->name }}
                            </option>
                        @empty
                            <option disabled>No cuisines available</option>
                        @endforelse
                    </select>
                    <div class="form-text">Hold Ctrl or Command to select multiple.</div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold">FSSAI License</label>
                    <input type="text" name="fssai_license_number" class="form-control @error('fssai_license_number') is-invalid @enderror" value="{{ $field('fssai_license_number') }}" maxlength="64">
                    @error('fssai_license_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Description</label>
                    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ $field('description') }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </section>

    <section class="restaurant-form-panel span-4">
        <div class="restaurant-form-panel-header">
            <div>
                <h2>Media</h2>
                <p>Logo and banner used across customer views.</p>
            </div>
        </div>
        <div class="restaurant-form-panel-body">
            <div class="restaurant-media-grid">
                <div>
                    <label class="form-label fw-bold">Logo</label>
                    @if($restaurant?->logo_image)
                        <img src="{{ Storage::url($restaurant->logo_image) }}" class="restaurant-media-preview" alt="Logo">
                    @endif
                    <input type="file" name="logo" class="form-control" accept="image/*">
                </div>
                <div>
                    <label class="form-label fw-bold">Banner</label>
                    @if($restaurant?->banner_image)
                        <img src="{{ Storage::url($restaurant->banner_image) }}" class="restaurant-media-preview wide" alt="Banner">
                    @endif
                    <input type="file" name="banner" class="form-control" accept="image/*">
                </div>
            </div>
        </div>
    </section>

    <section class="restaurant-form-panel span-12">
        <div class="restaurant-form-panel-header">
            <div>
                <h2>Location And Service Area</h2>
                <p>Search, pin, or use current location to set restaurant coordinates.</p>
            </div>
        </div>
        <div class="restaurant-form-panel-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-bold">Restaurant Location <span class="text-danger">*</span></label>
                    <div class="input-group mb-2">
                        <input type="text" id="searchAddress" class="form-control" placeholder="Search address or landmark" value="{{ old('address', $restaurant?->address) }}">
                        <button type="button" class="btn btn-outline-secondary" id="searchAddressBtn"><i class="fas fa-search me-1"></i>Search</button>
                        <button type="button" class="btn btn-outline-secondary" id="useLocationBtn"><i class="fas fa-location-crosshairs me-1"></i>Use Location</button>
                    </div>
                    <textarea id="address" name="address" rows="2" class="form-control @error('address') is-invalid @enderror" required>{{ old('address', $restaurant?->address) }}</textarea>
                    <div id="restaurantMap" class="border rounded mt-3"></div>
                    <div id="mapLegend" class="map-legend mt-2">Delivery radius: <strong>{{ old('delivery_radius', $restaurant?->delivery_radius ?? ($panel === 'branch' ? 5 : 10)) }} km</strong>. Drag the marker or click the map to move service area.</div>
                    <div class="zone-preview-card mt-3">
                        <div class="zone-preview-label">{{ $zoneTitle ?? ($panel === 'branch' ? 'Branch Delivery Zone' : 'Auto Delivery Zone') }}</div>
                        <div class="fw-bold" id="zonePreviewName">Use current location or search to detect zone</div>
                        <div class="text-muted small" id="zonePreviewMeta">{{ $zoneMeta ?? ($panel === 'branch' ? "Only restaurants inside this branch's active mapped zone can be saved or approved." : 'Zone preview is calculated from the selected restaurant location.') }}</div>
                    </div>
                    <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $restaurant?->latitude) }}">
                    <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $restaurant?->longitude) }}">
                    <div id="locationStatus" class="form-text">Use the map, geolocation or address search to set location and radius.</div>
                    @error('address') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    @error('latitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    @error('longitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold">City <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ $field('city') }}" required>
                    @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold">State <span class="text-danger">*</span></label>
                    <input type="text" name="state" class="form-control @error('state') is-invalid @enderror" value="{{ $field('state') }}" required>
                    @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold">Pincode <span class="text-danger">*</span></label>
                    <input type="text" name="pincode" class="form-control @error('pincode') is-invalid @enderror" value="{{ $field('pincode') }}" required>
                    @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </section>

    <section class="restaurant-form-panel span-12">
        <div class="restaurant-form-panel-header">
            <div>
                <h2>Commercials And Timing</h2>
                <p>Delivery, commission, opening hours and ordering rules.</p>
            </div>
        </div>
        <div class="restaurant-form-panel-body">
            <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Delivery Radius (km) <span class="text-danger">*</span></label>
                    <input type="number" name="delivery_radius" step="0.5" min="0" max="100" class="form-control @error('delivery_radius') is-invalid @enderror" value="{{ old('delivery_radius', $restaurant?->delivery_radius ?? ($panel === 'branch' ? 5 : 10)) }}" required>
                    @error('delivery_radius') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Minimum Order</label>
                    <input type="number" name="min_order_amount" class="form-control" min="0" step="0.01" value="{{ $field('min_order_amount', 0) }}">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Delivery Fee</label>
                    <input type="number" name="delivery_fee" class="form-control" min="0" step="0.01" value="{{ $field('delivery_fee', 0) }}">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Dining Charge ({{ $currencySymbol }})</label>
                    <input type="number" name="dining_charge" class="form-control @error('dining_charge') is-invalid @enderror" min="0" step="0.01" value="{{ $field('dining_charge', 0) }}">
                    @error('dining_charge') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Commission Type</label>
                    <select name="commission_calculation_type" class="form-select @error('commission_calculation_type') is-invalid @enderror" required>
                        <option value="global" @selected($commissionType === 'global')>Use global setting</option>
                        <option value="percentage" @selected($commissionType === 'percentage')>Percentage</option>
                        <option value="fixed" @selected($commissionType === 'fixed')>Fixed amount</option>
                    </select>
                    @error('commission_calculation_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Commission Value</label>
                    <input type="number" name="commission_rate" class="form-control @error('commission_rate') is-invalid @enderror" value="{{ old('commission_rate', $restaurant?->commission_rate) }}" min="0" step="0.01" placeholder="Global when blank">
                    @error('commission_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Delivery Time</label>
                    <input type="number" name="delivery_time" class="form-control @error('delivery_time') is-invalid @enderror" value="{{ $field('delivery_time', 30) }}" min="1" max="240">
                    @error('delivery_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Order Lead Time</label>
                    <input type="number" name="order_lead_time" class="form-control @error('order_lead_time') is-invalid @enderror" value="{{ $field('order_lead_time', 0) }}" min="0" max="240">
                    @error('order_lead_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Opening Time</label>
                    <input type="time" name="open_time" class="form-control @error('open_time') is-invalid @enderror" value="{{ old('open_time', $restaurant?->open_time ?? '09:00') }}">
                    @error('open_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Closing Time</label>
                    <input type="time" name="close_time" class="form-control @error('close_time') is-invalid @enderror" value="{{ old('close_time', $restaurant?->close_time ?? '22:00') }}">
                    @error('close_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Timezone</label>
                    <input type="text" name="timezone" class="form-control @error('timezone') is-invalid @enderror" value="{{ $field('timezone', 'Asia/Kolkata') }}">
                    @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="restaurant-toggle-box">
                        <input class="form-check-input" type="checkbox" name="is_pure_veg" value="1" id="isPureVeg" @checked(old('is_pure_veg', $restaurant?->is_pure_veg))>
                        <label class="form-check-label fw-bold" for="isPureVeg">Pure Veg</label>
                    </div>
                </div>

                @if($showAdminStatus)
                    <div class="col-lg-4 col-md-6">
                        <div class="restaurant-toggle-box">
                            <input class="form-check-input" type="checkbox" name="is_open" value="1" id="isOpen" @checked(old('is_open', $restaurant?->is_open))>
                            <label class="form-check-label fw-bold" for="isOpen">Restaurant Open</label>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="restaurant-toggle-box">
                            <input class="form-check-input" type="checkbox" name="is_verified" value="1" id="isVerified" @checked(old('is_verified', $restaurant?->is_verified))>
                            <label class="form-check-label fw-bold" for="isVerified">Verified</label>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="restaurant-toggle-box">
                            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="isFeatured" @checked(old('is_featured', $restaurant?->is_featured))>
                            <label class="form-check-label fw-bold" for="isFeatured">Featured</label>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @if($showOwnerLogin)
        <section class="restaurant-form-panel span-12">
            <div class="restaurant-form-panel-header">
                <div>
                    <h2>Owner Login</h2>
                    <p>Account used by the restaurant owner to access the seller panel.</p>
                </div>
            </div>
            <div class="restaurant-form-panel-body">
                <div class="row g-3">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold">Owner Name {{ !$isEdit ? '*' : '' }}</label>
                        <input type="text" name="owner_name" class="form-control @error('owner_name') is-invalid @enderror" value="{{ old('owner_name', $owner?->name) }}" {{ !$isEdit ? 'required' : '' }}>
                        @error('owner_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold">Owner Email {{ !$isEdit ? '*' : '' }}</label>
                        <input type="email" name="owner_email" class="form-control @error('owner_email') is-invalid @enderror" value="{{ old('owner_email', $owner?->email) }}" {{ !$isEdit ? 'required' : '' }}>
                        @error('owner_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold">Owner Phone {{ !$isEdit ? '*' : '' }}</label>
                        <input type="text" name="owner_phone" class="form-control @error('owner_phone') is-invalid @enderror" value="{{ old('owner_phone', $owner?->phone) }}" {{ !$isEdit ? 'required' : '' }}>
                        @error('owner_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-lg-6 col-md-6">
                        <label class="form-label fw-bold">{{ $isEdit ? 'New Owner Password' : 'Owner Password *' }}</label>
                        <input type="password" name="owner_password" class="form-control @error('owner_password') is-invalid @enderror" {{ !$isEdit ? 'required' : '' }}>
                        @error('owner_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-lg-6 col-md-6">
                        <label class="form-label fw-bold">{{ $isEdit ? 'Confirm New Password' : 'Confirm Password *' }}</label>
                        <input type="password" name="owner_password_confirmation" class="form-control @error('owner_password_confirmation') is-invalid @enderror" {{ !$isEdit ? 'required' : '' }}>
                        @error('owner_password_confirmation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="restaurant-form-panel span-12">
        <div class="restaurant-form-panel-header">
            <div>
                <h2>Payout Account</h2>
                <p>Bank, UPI and payment gateway settlement details.</p>
            </div>
        </div>
        <div class="restaurant-form-panel-body">
            <div class="row g-3">
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
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const visibleGatewayAccountField = document.getElementById('stripe_account_id');
        const hiddenGatewayAccountField = document.getElementById('gateway_account_id');

        if (visibleGatewayAccountField && hiddenGatewayAccountField) {
            const syncGatewayAccountId = () => {
                hiddenGatewayAccountField.value = visibleGatewayAccountField.value;
            };

            visibleGatewayAccountField.addEventListener('input', syncGatewayAccountId);
            syncGatewayAccountId();
        }
    });
</script>
