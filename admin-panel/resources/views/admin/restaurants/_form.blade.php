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
    $payoutValues = [
        'account_holder_name' => old('account_holder_name', $owner?->account_holder_name),
        'bank_name' => old('bank_name', $owner?->bank_name),
        'account_number' => old('account_number', $owner?->account_number),
        'routing_code' => old('routing_code', $owner?->routing_code ?? $owner?->ifsc_code),
        'ifsc_code' => old('ifsc_code', $owner?->ifsc_code),
        'upi_id' => old('upi_id', $owner?->upi_id),
        'stripe_account_id' => old('stripe_account_id', $owner?->stripe_account_id ?? $owner?->gateway_account_id),
        'gateway_account_id' => old('gateway_account_id', $owner?->gateway_account_id ?? $owner?->stripe_account_id),
    ];
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
                            @php
                                $cuisineValue = (string) ($cuisineValueField === 'name' ? $cuisine->name : $cuisine->id);
                            @endphp
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
                    <div class="mt-3">
                        <label class="form-label fw-bold">Delivery Zone</label>
                        <select name="delivery_area_id" id="deliveryAreaSelect" class="form-select @error('delivery_area_id') is-invalid @enderror">
                            <option value="">Auto-detect from location above</option>
                            @foreach($deliveryAreas ?? [] as $area)
                                <option value="{{ $area['id'] ?? $area->id }}" @selected((string) old('delivery_area_id', $restaurant?->delivery_area_id) === (string) ($area['id'] ?? $area->id))>{{ $area['name'] ?? $area->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Leave as "Auto-detect" to match by coordinates, or pick the zone directly if detection didn't find the right one.</div>
                        @error('delivery_area_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
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
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-bold">Country</label>
                    @php $countryRegistrationMap = $countryRegistrationMap ?? []; $selectedCountry = strtoupper((string) $field('country_code', '')); @endphp
                    <select name="country_code" id="restaurantCountryCode" class="form-select">
                        <option value="" @selected($selectedCountry === '')>Platform Default</option>
                        @foreach($countryRegistrationMap as $code => $info)
                            <option value="{{ $code }}" @selected($selectedCountry === $code)>{{ $info['name'] }} ({{ $info['tax_name'] }})</option>
                        @endforeach
                    </select>
                    <div class="small text-muted mt-1">Which country's tax rules apply to this restaurant's orders. Leave as "Platform Default" to follow the platform's default country.</div>
                </div>
            </div>

            <hr class="my-3">
            <h3 class="h6 fw-bold mb-2">Tax Registration</h3>
            <p class="text-muted small" id="taxRegHint">Applies only when GST invoicing is enabled in Settings &rarr; Business. A GST-registered restaurant is the supplier of record on its customer invoices.</p>

            <div class="row g-3" data-country-kind-block="india">
                <div class="col-lg-3 col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="is_gst_registered" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_gst_registered" name="is_gst_registered" value="1" @checked((bool) $field('is_gst_registered'))>
                        <label class="form-check-label fw-semibold" for="is_gst_registered">GST registered</label>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">GSTIN</label>
                    <input type="text" name="gstin" class="form-control @error('gstin') is-invalid @enderror" maxlength="20" value="{{ $field('gstin') }}">
                    @error('gstin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">PAN</label>
                    <input type="text" name="pan" class="form-control @error('pan') is-invalid @enderror" maxlength="15" value="{{ $field('pan') }}">
                    @error('pan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Deductee Type (TDS 194-O)</label>
                    <select name="tax_deductee_type" class="form-select">
                        <option value="individual" @selected($field('tax_deductee_type', 'individual') === 'individual')>Individual / HUF</option>
                        <option value="company" @selected($field('tax_deductee_type') === 'company')>Company / Firm</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="form-check mt-4">
                        <input type="hidden" name="tds_pan_verified" value="0">
                        <input class="form-check-input" type="checkbox" id="tds_pan_verified" name="tds_pan_verified" value="1" @checked((bool) $field('tds_pan_verified'))>
                        <label class="form-check-label" for="tds_pan_verified">PAN verified</label>
                    </div>
                </div>
            </div>

            <div class="row g-3" data-country-kind-block="vat" style="display:none;">
                <div class="col-lg-4 col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="is_vat_registered" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_vat_registered" name="is_vat_registered" value="1" @checked((bool) $field('is_vat_registered'))>
                        <label class="form-check-label fw-semibold" for="is_vat_registered"><span id="vatRegisteredLabel">VAT registered</span></label>
                    </div>
                    <div class="small text-muted mt-1">An unregistered restaurant cannot legally charge this tax -- its orders fall back to the flat platform tax rate.</div>
                </div>
                <div class="col-lg-8 col-md-6">
                    <label class="form-label fw-bold" id="vatNumberLabel">VAT Registration Number</label>
                    <input type="text" name="vat_number" class="form-control @error('vat_number') is-invalid @enderror" maxlength="40" value="{{ $field('vat_number') }}">
                    @error('vat_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row g-3" data-country-kind-block="us" style="display:none;">
                <div class="col-lg-8 col-md-6">
                    <div class="small text-muted mt-1">US sales tax applies under marketplace-facilitator law regardless of this restaurant's own registration -- only the state (below) matters for the rate.</div>
                </div>
            </div>

            <div class="row g-3" id="stateCodeSharedBlock">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold" id="stateCodeLabel">GST State Code</label>
                    <input type="text" name="state_code" id="stateCodeShared" class="form-control @error('state_code') is-invalid @enderror" maxlength="2" placeholder="e.g. 27" value="{{ $field('state_code') }}">
                    @error('state_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </section>

    <script>
        (function () {
            var countryMap = @json(collect($countryRegistrationMap)->map(fn ($i) => ['kind' => $i['kind'], 'tax_name' => $i['tax_name'], 'label' => $i['label']]));
            var select = document.getElementById('restaurantCountryCode');
            var hint = document.getElementById('taxRegHint');
            if (!select) return;

            var stateBlock = document.getElementById('stateCodeSharedBlock');
            var stateLabel = document.getElementById('stateCodeLabel');
            var stateInput = document.getElementById('stateCodeShared');

            function applyKind() {
                var code = select.value;
                var info = countryMap[code] || { kind: 'india', tax_name: 'GST', label: null };
                document.querySelectorAll('[data-country-kind-block]').forEach(function (el) {
                    el.style.display = (el.getAttribute('data-country-kind-block') === info.kind) ? '' : 'none';
                });
                if (info.kind === 'vat') {
                    document.getElementById('vatRegisteredLabel').textContent = info.tax_name + ' registered';
                    document.getElementById('vatNumberLabel').textContent = info.label || (info.tax_name + ' Registration Number');
                }
                // state_code is shared by India (GST state code) and US (sales-tax
                // state) -- ONE input, relabeled, so only one value is ever
                // actually submitted (two same-named inputs would silently
                // collide on submit).
                if (info.kind === 'us') {
                    stateBlock.style.display = '';
                    stateLabel.textContent = 'State';
                    stateInput.placeholder = 'e.g. CA';
                } else if (info.kind === 'india') {
                    stateBlock.style.display = '';
                    stateLabel.textContent = 'GST State Code';
                    stateInput.placeholder = 'e.g. 27';
                } else {
                    stateBlock.style.display = 'none';
                }
                hint.textContent = code === '' || info.kind === 'india'
                    ? 'Applies only when GST invoicing is enabled in Settings → Business. A GST-registered restaurant is the supplier of record on its customer invoices.'
                    : (info.tax_name + ' rules for this restaurant’s selected country -- configured platform-wide on Settings → Country & Tax.');
            }

            select.addEventListener('change', applyKind);
            applyKind();
        })();
    </script>

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
                @php
                    $mondayTiming = $restaurant?->weekly_timings['monday'] ?? [];
                @endphp
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Break Start <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="time" name="break_start" class="form-control @error('break_start') is-invalid @enderror" value="{{ old('break_start', $mondayTiming['break_start'] ?? '') }}">
                    <div class="form-text">Kitchen closed between these times each day.</div>
                    @error('break_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold">Break End</label>
                    <input type="time" name="break_end" class="form-control @error('break_end') is-invalid @enderror" value="{{ old('break_end', $mondayTiming['break_end'] ?? '') }}">
                    @error('break_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                @endif
            </div>
        </div>
    </section>

    @if($showOwnerLogin)
        <section class="restaurant-form-panel span-12">
            <div class="restaurant-form-panel-header">
                <div>
                    <h2>Owner Login</h2>
                    <p>Account used by the restaurant owner to access the seller panel. An owner can run more than one restaurant -- map this one to their existing account instead of creating a new login.</p>
                </div>
            </div>
            <div class="restaurant-form-panel-body">
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <div class="btn-group" role="group" aria-label="Owner mode">
                            <input type="radio" class="btn-check" name="owner_mode" id="ownerModeNew" value="new" {{ old('owner_mode', 'new') === 'new' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="ownerModeNew">Create New Owner</label>
                            <input type="radio" class="btn-check" name="owner_mode" id="ownerModeExisting" value="existing" {{ old('owner_mode') === 'existing' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="ownerModeExisting">Use Existing Owner</label>
                        </div>
                        @error('owner_mode') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @error('existing_owner_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div id="ownerModeExistingFields" class="row g-3 mb-3" style="display: {{ old('owner_mode') === 'existing' ? 'flex' : 'none' }};">
                    <div class="col-lg-6">
                        <label class="form-label fw-bold">Existing Restaurant Owner</label>
                        <select name="existing_owner_id" class="form-select @error('existing_owner_id') is-invalid @enderror">
                            <option value="">Select an owner...</option>
                            @foreach(\App\Models\User::role('restaurant_owner')->orderBy('name')->get() as $existingOwner)
                                <option value="{{ $existingOwner->id }}" {{ (string) old('existing_owner_id') === (string) $existingOwner->id ? 'selected' : '' }}>
                                    {{ $existingOwner->name }} ({{ $existingOwner->email }}) -- {{ $existingOwner->restaurants()->count() }} restaurant{{ $existingOwner->restaurants()->count() === 1 ? '' : 's' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">This restaurant will be added to that owner's "My Stores" list and go through the normal approval queue, same as any other restaurant.</div>
                    </div>
                </div>

                <div id="ownerModeNewFields" class="row g-3" style="display: {{ old('owner_mode') === 'existing' ? 'none' : 'flex' }};">
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

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const newRadio = document.getElementById('ownerModeNew');
                const existingRadio = document.getElementById('ownerModeExisting');
                const newFields = document.getElementById('ownerModeNewFields');
                const existingFields = document.getElementById('ownerModeExistingFields');
                if (!newRadio || !existingRadio) return;

                function toggle() {
                    const isExisting = existingRadio.checked;
                    newFields.style.display = isExisting ? 'none' : 'flex';
                    existingFields.style.display = isExisting ? 'flex' : 'none';
                    newFields.querySelectorAll('input[name="owner_name"], input[name="owner_email"], input[name="owner_phone"], input[name="owner_password"], input[name="owner_password_confirmation"]')
                        .forEach(el => { el.required = !isExisting; });
                    const existingSelect = existingFields.querySelector('select[name="existing_owner_id"]');
                    if (existingSelect) existingSelect.required = isExisting;
                }

                newRadio.addEventListener('change', toggle);
                existingRadio.addEventListener('change', toggle);
                toggle();
            });
        </script>
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
                @include('admin.partials.payout-account-fields', ['values' => $payoutValues])
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
