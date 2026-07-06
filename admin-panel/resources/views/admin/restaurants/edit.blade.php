@extends('layouts.admin')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Edit Restaurant')
@section('header', 'Edit Restaurant')

@section('styles')
<style>
    #restaurantMap { height: 320px; width: 100%; }
    .map-legend {
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid #dfe3e8;
        border-radius: 0.75rem;
        padding: 0.85rem 1rem;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        font-size: 0.92rem;
        color: #1f2937;
    }
    .map-radius-tooltip {
        background: rgba(0, 123, 255, 0.85) !important;
        color: white !important;
        border: 0 !important;
        border-radius: 0.5rem !important;
        padding: 0.3rem 0.6rem !important;
        font-size: 0.85rem !important;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important;
    }
    .zone-preview-card {
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid #dfe3e8;
        border-radius: 0.9rem;
        padding: 0.9rem 1rem;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    }
    .zone-preview-label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #6b7280;
        font-weight: 700;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Edit Restaurant</h1>
            <p>Update restaurant information</p>
        </div>
        <a href="{{ route('admin.restaurants.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Restaurants
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Restaurant Information</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.restaurants.update', $restaurant) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Restaurant Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $restaurant->name) }}" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $restaurant->email) }}" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $restaurant->phone) }}" required>
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">FSSAI License Number</label>
                            <input type="text" name="fssai_license_number" class="form-control @error('fssai_license_number') is-invalid @enderror" value="{{ old('fssai_license_number', $restaurant->fssai_license_number) }}" maxlength="64">
                            @error('fssai_license_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Minimum Order Amount</label>
                            <input type="number" name="min_order_amount" class="form-control" value="{{ old('min_order_amount', $restaurant->min_order_amount) }}">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Delivery Fee</label>
                            <input type="number" name="delivery_fee" class="form-control" value="{{ old('delivery_fee', $restaurant->delivery_fee) }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Platform Commission Type</label>
                            <select name="commission_calculation_type" class="form-select @error('commission_calculation_type') is-invalid @enderror" required>
                                @php($restaurantCommissionType = old('commission_calculation_type', $restaurant->commission_calculation_type ?? 'percentage'))
                                <option value="global" @selected($restaurantCommissionType === 'global')>Use global setting</option>
                                <option value="percentage" @selected($restaurantCommissionType === 'percentage')>Percentage</option>
                                <option value="fixed" @selected($restaurantCommissionType === 'fixed')>Fixed amount</option>
                            </select>
                            @error('commission_calculation_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Restaurant Earning Commission</label>
                            <input type="number" name="commission_rate" class="form-control @error('commission_rate') is-invalid @enderror" value="{{ old('commission_rate', $restaurant->commission_rate) }}" min="0" step="0.01" placeholder="Uses global setting when blank">
                            @error('commission_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Delivery Time (minutes)</label>
                            <input type="number" name="delivery_time" class="form-control @error('delivery_time') is-invalid @enderror" value="{{ old('delivery_time', $restaurant->delivery_time ?? 30) }}" min="1" max="240">
                            @error('delivery_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Order Lead Time (minutes)</label>
                            <input type="number" name="order_lead_time" class="form-control @error('order_lead_time') is-invalid @enderror" value="{{ old('order_lead_time', $restaurant->order_lead_time ?? 0) }}" min="0" max="240">
                            @error('order_lead_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Opening Time</label>
                            <input type="time" name="open_time" class="form-control @error('open_time') is-invalid @enderror" value="{{ old('open_time', $restaurant->open_time) }}">
                            @error('open_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Closing Time</label>
                            <input type="time" name="close_time" class="form-control @error('close_time') is-invalid @enderror" value="{{ old('close_time', $restaurant->close_time) }}">
                            @error('close_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Timezone</label>
                            <input type="text" name="timezone" class="form-control @error('timezone') is-invalid @enderror" value="{{ old('timezone', $restaurant->timezone ?? 'Asia/Kolkata') }}">
                            @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">Restaurant Location <span class="text-danger">*</span></label>
                            <div class="input-group mb-2">
                                <input type="text" id="searchAddress" class="form-control" placeholder="Search address or landmark" value="{{ old('address', $restaurant->address) }}">
                                <button type="button" class="btn btn-outline-secondary" id="searchAddressBtn">Search</button>
                                <button type="button" class="btn btn-outline-secondary" id="useLocationBtn">Use My Location</button>
                            </div>
                            <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="2" required>{{ old('address', $restaurant->address) }}</textarea>
                            <div id="restaurantMap" class="border rounded mt-3"></div>
                            <div id="mapLegend" class="map-legend mt-2">Delivery radius: <strong>{{ old('delivery_radius', $restaurant->delivery_radius ?? 10) }} km</strong>. Drag the marker or click the map to move service area.</div>
                            <div class="zone-preview-card mt-3">
                                <div class="zone-preview-label">Auto Delivery Zone</div>
                                <div class="fw-bold" id="zonePreviewName">Use current location or search to detect zone</div>
                                <div class="text-muted small" id="zonePreviewMeta">Zone preview is calculated from the selected restaurant location.</div>
                            </div>
                            <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $restaurant->latitude) }}">
                            <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $restaurant->longitude) }}">
                            <div id="locationStatus" class="form-text">Use the map, geolocation or address search to set location and radius.</div>
                            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $restaurant->city) }}" required>
                            @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">State <span class="text-danger">*</span></label>
                            <input type="text" name="state" class="form-control @error('state') is-invalid @enderror" value="{{ old('state', $restaurant->state) }}" required>
                            @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Pincode <span class="text-danger">*</span></label>
                            <input type="text" name="pincode" class="form-control @error('pincode') is-invalid @enderror" value="{{ old('pincode', $restaurant->pincode) }}" required>
                            @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Restaurant Type <span class="text-danger">*</span></label>
                            <select name="restaurant_type" class="form-select @error('restaurant_type') is-invalid @enderror" required>
                                <option value="delivery" {{ old('restaurant_type', $restaurant->restaurant_type) == 'delivery' ? 'selected' : '' }}>Delivery Only</option>
                                <option value="dining" {{ old('restaurant_type', $restaurant->restaurant_type) == 'dining' ? 'selected' : '' }}>Dining Only</option>
                                <option value="takeaway" {{ old('restaurant_type', $restaurant->restaurant_type) == 'takeaway' ? 'selected' : '' }}>Takeaway Only</option>
                                <option value="both" {{ old('restaurant_type', $restaurant->restaurant_type) == 'both' ? 'selected' : '' }}>Delivery & Dining</option>
                                <option value="delivery_takeaway" {{ old('restaurant_type', $restaurant->restaurant_type) == 'delivery_takeaway' ? 'selected' : '' }}>Delivery & Takeaway</option>
                                <option value="dining_takeaway" {{ old('restaurant_type', $restaurant->restaurant_type) == 'dining_takeaway' ? 'selected' : '' }}>Dining & Takeaway</option>
                                <option value="all" {{ old('restaurant_type', $restaurant->restaurant_type) == 'all' ? 'selected' : '' }}>Delivery, Dining & Takeaway</option>
                            </select>
                            @error('restaurant_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Dining Charge ({{ $currencySymbol }})</label>
                            <input type="number" name="dining_charge" class="form-control @error('dining_charge') is-invalid @enderror" value="{{ old('dining_charge', $restaurant->dining_charge ?? 0) }}" min="0" step="0.5">
                            @error('dining_charge') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Delivery Radius (km) <span class="text-danger">*</span></label>
                            <input type="number" name="delivery_radius" step="0.5" min="0" class="form-control @error('delivery_radius') is-invalid @enderror" value="{{ old('delivery_radius', $restaurant->delivery_radius ?? 10) }}" required>
                            @error('delivery_radius') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch pt-4">
                                <input class="form-check-input" type="checkbox" name="is_pure_veg" value="1" id="isPureVeg" {{ old('is_pure_veg', $restaurant->is_pure_veg) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="isPureVeg">Pure veg restaurant</label>
                                <div class="form-text">Egg and non-veg items are blocked for pure veg stores.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cuisine Type</label>
                            <select name="cuisine[]" class="form-select" multiple>
                                @forelse($cuisines as $cuisine)
                                    <option value="{{ $cuisine->id }}" @if(in_array($cuisine->id, (array) old('cuisine', $restaurant->cuisine ?? []))) selected @endif>
                                        {{ $cuisine->name }}
                                    </option>
                                @empty
                                    <option disabled>No cuisines available</option>
                                @endforelse
                            </select>
                            <div class="form-text">Hold Ctrl (Windows) or Command (Mac) to select multiple.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Restaurant Logo</label>
                            @if($restaurant->logo_image)
                                <div class="mb-2">
                                    <img src="{{ Storage::url($restaurant->logo_image) }}" height="60" alt="Logo">
                                </div>
                            @endif
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Banner Image</label>
                            @if($restaurant->banner_image)
                                <div class="mb-2">
                                    <img src="{{ Storage::url($restaurant->banner_image) }}" height="60" alt="Banner">
                                </div>
                            @endif
                            <input type="file" name="banner" class="form-control" accept="image/*">
                        </div>

                        @include('admin.partials.payout-account-fields', ['values' => [
                            'account_holder_name' => old('account_holder_name', $restaurant->owner->account_holder_name ?? ''),
                            'bank_name' => old('bank_name', $restaurant->owner->bank_name ?? ''),
                            'account_number' => old('account_number', $restaurant->owner->account_number ?? ''),
                            'ifsc_code' => old('ifsc_code', $restaurant->owner->ifsc_code ?? ''),
                            'upi_id' => old('upi_id', $restaurant->owner->upi_id ?? ''),
                            'stripe_account_id' => old('stripe_account_id', $restaurant->owner->stripe_account_id ?? $restaurant->owner->gateway_account_id ?? ''),
                            'gateway_account_id' => old('gateway_account_id', $restaurant->owner->gateway_account_id ?? $restaurant->owner->stripe_account_id ?? ''),
                        ]])
                        
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_open" value="1" id="isOpen" {{ old('is_open', $restaurant->is_open) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="isOpen">Restaurant Open</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_verified" value="1" id="isVerified" {{ old('is_verified', $restaurant->is_verified) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="isVerified">Verified</label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="isFeatured" {{ old('is_featured', $restaurant->is_featured) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="isFeatured">Featured</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Restaurant
                        </button>
                        <a href="{{ route('admin.restaurants.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
@endsection

@section('scripts')
@include('partials.google-maps-shim')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deliveryAreas = @json($deliveryAreas);
        const searchBtn = document.getElementById('searchAddressBtn');
        const useLocationBtn = document.getElementById('useLocationBtn');
        const searchInput = document.getElementById('searchAddress');
        const addressField = document.getElementById('address');
        const cityField = document.querySelector('input[name="city"]');
        const stateField = document.querySelector('input[name="state"]');
        const pincodeField = document.querySelector('input[name="pincode"]');
        const radiusField = document.querySelector('input[name="delivery_radius"]');
        const latField = document.getElementById('latitude');
        const lngField = document.getElementById('longitude');
        const statusEl = document.getElementById('locationStatus');
        const zonePreviewName = document.getElementById('zonePreviewName');
        const zonePreviewMeta = document.getElementById('zonePreviewMeta');
        const mapContainer = document.getElementById('restaurantMap');

        let map;
        let marker;
        let circle;

        function updateStatus(text) {
            if (statusEl) statusEl.textContent = text;
        }

        function degreesToRadians(degrees) {
            return degrees * Math.PI / 180;
        }

        function distanceKm(lat1, lon1, lat2, lon2) {
            const earthRadius = 6371;
            const dLat = degreesToRadians(lat2 - lat1);
            const dLon = degreesToRadians(lon2 - lon1);
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
                + Math.cos(degreesToRadians(lat1)) * Math.cos(degreesToRadians(lat2))
                * Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return earthRadius * c;
        }

        function pointInPolygon(polygon, latitude, longitude) {
            let intersections = 0;
            for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
                const current = polygon[i];
                const previous = polygon[j];
                if (
                    (current.lat > latitude) !== (previous.lat > latitude) &&
                    longitude < ((previous.lng - current.lng) * (latitude - current.lat)) / (previous.lat - current.lat) + current.lng
                ) {
                    intersections++;
                }
            }
            return intersections % 2 === 1;
        }

        function resolveZone(latitude, longitude) {
            const containing = deliveryAreas.filter((area) => {
                if (area.area_type === 'polygon' && Array.isArray(area.polygon_coordinates) && area.polygon_coordinates.length >= 3) {
                    return pointInPolygon(area.polygon_coordinates, latitude, longitude);
                }

                if (area.latitude === null || area.longitude === null || area.radius_km === null) {
                    return false;
                }

                return distanceKm(latitude, longitude, Number(area.latitude), Number(area.longitude)) <= Number(area.radius_km);
            });

            if (containing.length > 0) {
                return containing.sort((left, right) => Number(left.radius_km || 0) - Number(right.radius_km || 0))[0];
            }

            return deliveryAreas
                .filter((area) => area.latitude !== null && area.longitude !== null)
                .sort((left, right) => {
                    const leftDistance = distanceKm(latitude, longitude, Number(left.latitude), Number(left.longitude));
                    const rightDistance = distanceKm(latitude, longitude, Number(right.latitude), Number(right.longitude));
                    return leftDistance - rightDistance;
                })[0] ?? null;
        }

        function updateZonePreview(latitude, longitude) {
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                zonePreviewName.textContent = 'Use current location or search to detect zone';
                zonePreviewMeta.textContent = 'Zone preview is calculated from the selected restaurant location.';
                return;
            }

            const zone = resolveZone(latitude, longitude);
            if (!zone) {
                zonePreviewName.textContent = 'No active delivery zone matched';
                zonePreviewMeta.textContent = 'Adjust the location or add a delivery area covering this point.';
                return;
            }

            zonePreviewName.textContent = zone.name;
            zonePreviewMeta.textContent = zone.description || `Auto-matched within ${zone.radius_km ?? '-'} km coverage.`;
        }

        function getRadiusMeters() {
            const value = parseFloat(radiusField?.value);
            return Number.isFinite(value) && value > 0 ? value * 1000 : 10000;
        }

        function createMap() {
            if (!mapContainer) {
                return;
            }

            const initialLat = parseFloat(latField.value) || 20;
            const initialLng = parseFloat(lngField.value) || 0;
            const initialZoom = latField.value && lngField.value ? 13 : 2;

            if (!window.google || !google.maps) {
                updateStatus('Google Maps could not be loaded. Check the API key in map settings.');
                return;
            }

            map = new google.maps.Map(mapContainer, {
                center: { lat: initialLat, lng: initialLng },
                zoom: initialZoom,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
            });

            if (latField.value && lngField.value) {
                updateMarker(initialLat, initialLng);
            }

            map.addListener('click', function (e) {
                updateMarker(e.latLng.lat(), e.latLng.lng());
                reverseGeocode(e.latLng.lat(), e.latLng.lng());
            });
        }

        function updateMarker(lat, lng) {
            const latLng = { lat: Number(lat), lng: Number(lng) };

            if (!marker) {
                marker = new google.maps.Marker({
                    position: latLng,
                    map,
                    draggable: true,
                });
                marker.addListener('dragend', function (event) {
                    updateMarker(event.latLng.lat(), event.latLng.lng());
                    reverseGeocode(event.latLng.lat(), event.latLng.lng());
                });
            } else {
                marker.setPosition(latLng);
            }

            if (!circle) {
                circle = new google.maps.Circle({
                    map,
                    center: latLng,
                    radius: getRadiusMeters(),
                    strokeColor: '#1d4ed8',
                    strokeOpacity: 1,
                    strokeWeight: 2,
                    fillColor: '#3b82f6',
                    fillOpacity: 0.12,
                });
            } else {
                circle.setCenter(latLng);
                circle.setRadius(getRadiusMeters());
            }

            map.setCenter(latLng);
            map.setZoom(14);
            latField.value = lat;
            lngField.value = lng;
            updateLegend();
            updateZonePreview(Number(lat), Number(lng));
        }

        function getRadiusLabel() {
            return `Service radius: ${radiusField?.value || '10'} km`;
        }

        function updateLegend() {
            const legendEl = document.getElementById('mapLegend');
            if (legendEl) {
                legendEl.innerHTML = `Delivery radius: <strong>${radiusField?.value || '10'} km</strong>. Click the map or drag the pin to move the service area.`;
            }
        }

        async function reverseGeocode(lat, lng) {
            try {
                const params = new URLSearchParams({
                    lat,
                    lon: lng,
                    format: 'json',
                    addressdetails: '1',
                });
                const response = await fetch(`google-maps-shim/reverse?${params}`);
                const data = await response.json();
                fillLocation(data);
            } catch (error) {
                console.error(error);
                updateStatus('Unable to resolve location from the map point.');
            }
        }

        async function fillLocation(result) {
            if (!result) {
                updateStatus('Location could not be resolved.');
                return;
            }

            addressField.value = result.display_name || addressField.value;
            const address = result.address || {};
            cityField.value = address.city || address.town || address.village || cityField.value;
            stateField.value = address.state || stateField.value;
            pincodeField.value = address.postcode || pincodeField.value;
            latField.value = result.lat || latField.value;
            lngField.value = result.lon || lngField.value;
            if (result.lat && result.lon && map) {
                updateMarker(result.lat, result.lon);
            }
            updateStatus('Location set from geolocation or address lookup.');
        }

        async function lookupAddress(query) {
            if (!query) {
                updateStatus('Enter an address or landmark to search.');
                return;
            }
            updateStatus('Searching address...');

            try {
                const params = new URLSearchParams({
                    q: query,
                    format: 'json',
                    addressdetails: '1',
                    limit: '1',
                });
                const response = await fetch(`google-maps-shim/search?${params}`);
                const data = await response.json();

                if (Array.isArray(data) && data.length) {
                    await fillLocation(data[0]);
                } else {
                    updateStatus('No location found for that query.');
                }
            } catch (error) {
                console.error(error);
                updateStatus('Unable to search address right now.');
            }
        }

        async function detectLocation() {
            if (!navigator.geolocation) {
                updateStatus('Geolocation is not available in your browser.');
                return;
            }

            updateStatus('Detecting current location...');
            navigator.geolocation.getCurrentPosition(async (position) => {
                try {
                    const params = new URLSearchParams({
                        lat: position.coords.latitude,
                        lon: position.coords.longitude,
                        format: 'json',
                        addressdetails: '1',
                    });
                    const response = await fetch(`google-maps-shim/reverse?${params}`);
                    const data = await response.json();
                    await fillLocation(data);
                } catch (error) {
                    console.error(error);
                    updateStatus('Unable to resolve current location.');
                }
            }, (error) => {
                console.error(error);
                updateStatus('Permission denied or unable to detect location.');
            }, { timeout: 10000 });
        }

        searchBtn?.addEventListener('click', function () {
            lookupAddress(searchInput.value.trim() || addressField.value.trim());
        });
        searchInput?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                lookupAddress(searchInput.value.trim() || addressField.value.trim());
            }
        });

        useLocationBtn?.addEventListener('click', detectLocation);
        radiusField?.addEventListener('change', function () {
            if (circle) {
                circle.setRadius(getRadiusMeters());
                updateLegend();
            }
        });

        createMap();
        updateLegend();
        updateZonePreview(parseFloat(latField.value), parseFloat(lngField.value));
    });
</script>
@endsection




