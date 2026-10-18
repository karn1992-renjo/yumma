@extends('layouts.admin')

@section('title', 'Add Driver')
@section('header', 'Add New Driver')

@section('styles')
<style>
    #driverMap { height: 320px; width: 100%; }
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
    <h1>Add New Driver</h1>
    <p>Create a new delivery driver account</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Driver Information</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.drivers.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vehicle Type <span class="text-danger">*</span></label>
                            <select name="vehicle_type" class="form-select @error('vehicle_type') is-invalid @enderror" required>
                                <option value="">Select Type</option>
                                <option value="bike" {{ old('vehicle_type') == 'bike' ? 'selected' : '' }}>Bike</option>
                                <option value="scooter" {{ old('vehicle_type') == 'scooter' ? 'selected' : '' }}>Scooter</option>
                                <option value="ev_scooter" {{ old('vehicle_type') == 'ev_scooter' ? 'selected' : '' }}>EV Scooter</option>
                                <option value="bicycle" {{ old('vehicle_type') == 'bicycle' ? 'selected' : '' }}>Bicycle</option>
                                <option value="car" {{ old('vehicle_type') == 'car' ? 'selected' : '' }}>Car</option>
                            </select>
                            @error('vehicle_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vehicle Number <span class="text-danger">*</span></label>
                            <input type="text" name="vehicle_number" class="form-control @error('vehicle_number') is-invalid @enderror" value="{{ old('vehicle_number') }}" required>
                            @error('vehicle_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vehicle Model</label>
                            <input type="text" name="vehicle_model" class="form-control @error('vehicle_model') is-invalid @enderror" value="{{ old('vehicle_model') }}" placeholder="e.g. Hero Splendor, Honda Activa">
                            @error('vehicle_model') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fuel / Engine Type</label>
                            <select name="fuel_type" class="form-select @error('fuel_type') is-invalid @enderror">
                                <option value="">Not set</option>
                                <option value="petrol" @selected(old('fuel_type') === 'petrol')>Petrol</option>
                                <option value="electric" @selected(old('fuel_type') === 'electric')>Electric</option>
                                <option value="manual" @selected(old('fuel_type') === 'manual')>Manual / Pedal</option>
                            </select>
                            @error('fuel_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">License Number <span class="text-danger">*</span></label>
                            <input type="text" name="license_number" class="form-control @error('license_number') is-invalid @enderror" value="{{ old('license_number') }}" required>
                            @error('license_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth') }}">
                            @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                                <option value="">Not set</option>
                                <option value="Male" @selected(old('gender') === 'Male')>Male</option>
                                <option value="Female" @selected(old('gender') === 'Female')>Female</option>
                                <option value="Other" @selected(old('gender') === 'Other')>Other</option>
                            </select>
                            @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payout Mode</label>
                            <select name="earning_mode" class="form-select @error('earning_mode') is-invalid @enderror">
                                <option value="commission" @selected(old('earning_mode', 'commission') === 'commission')>Per-delivery commission</option>
                                <option value="salary" @selected(old('earning_mode') === 'salary')>Fixed monthly salary</option>
                            </select>
                            @error('earning_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Monthly Salary</label>
                            <input type="number" step="0.01" min="0" name="monthly_salary" class="form-control @error('monthly_salary') is-invalid @enderror" value="{{ old('monthly_salary') }}" placeholder="Only for salary mode">
                            @error('monthly_salary') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Salary Effective From</label>
                            <input type="date" name="salary_effective_from" class="form-control @error('salary_effective_from') is-invalid @enderror" value="{{ old('salary_effective_from') }}">
                            @error('salary_effective_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Branch</label>
                            <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                                <option value="">Not mapped to a branch</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Required for a salaried driver's delivery earning and COD to route to their branch instead of admin.</div>
                            @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Salary Paid By</label>
                            <select name="salary_payer" class="form-select @error('salary_payer') is-invalid @enderror">
                                <option value="" @selected(old('salary_payer') === null)>Not set</option>
                                <option value="admin" @selected(old('salary_payer') === 'admin')>Admin</option>
                                <option value="branch" @selected(old('salary_payer') === 'branch')>Branch</option>
                            </select>
                            <div class="form-text">Only for salary mode. Bookkeeping only — records who is financially responsible; does not move any wallet money in this app.</div>
                            @error('salary_payer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city') }}">
                            @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Landmark</label>
                            <input type="text" name="landmark" class="form-control @error('landmark') is-invalid @enderror" value="{{ old('landmark') }}">
                            @error('landmark') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Driver Location</label>
                            <div class="input-group mb-2">
                                <input type="text" id="searchAddress" class="form-control" placeholder="Search address, landmark, or pincode" value="{{ old('address') }}">
                                <button type="button" class="btn btn-outline-secondary" id="searchAddressBtn">Search</button>
                                <button type="button" class="btn btn-outline-secondary" id="useLocationBtn">Use My Location</button>
                            </div>
                            <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="2" placeholder="Driver address or operating point">{{ old('address') }}</textarea>
                            <div id="driverMap" class="border rounded mt-3"></div>
                            <div class="zone-preview-card mt-3">
                                <div class="zone-preview-label">Auto Delivery Zone</div>
                                <div class="fw-bold" id="zonePreviewName">Use current location or search to detect zone</div>
                                <div class="text-muted small" id="zonePreviewMeta">Zone will be fetched automatically from the selected map location.</div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label fw-bold">Delivery Zone</label>
                                <select name="delivery_area_id" id="deliveryAreaSelect" class="form-select @error('delivery_area_id') is-invalid @enderror">
                                    <option value="">Auto-detect from location above</option>
                                    @foreach($deliveryAreas as $area)
                                        <option value="{{ $area->id }}" @selected((string) old('delivery_area_id') === (string) $area->id)>{{ $area->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Leave as "Auto-detect" to match by coordinates, or pick the zone directly if detection didn't find the right one.</div>
                                @error('delivery_area_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude') }}">
                            <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude') }}">
                            <div id="locationStatus" class="form-text">Search manually, use browser location, or drag the map marker to set driver location.</div>
                            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @error('latitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @error('longitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Active Orders</label>
                            <input type="number" name="max_active_orders" min="1" max="50" class="form-control @error('max_active_orders') is-invalid @enderror" value="{{ old('max_active_orders') }}" placeholder="Use global setting">
                            <small class="text-muted">Leave blank to use global limit: {{ $globalMaxActiveOrders }} active order{{ $globalMaxActiveOrders == 1 ? '' : 's' }}.</small>
                            @error('max_active_orders') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" role="switch" name="background_location_enabled" value="1" id="backgroundLocationEnabled" @checked(old('background_location_enabled', '1') == '1')>
                                <label class="form-check-label fw-semibold" for="backgroundLocationEnabled">Background Location Enabled</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" role="switch" name="notification_permission_enabled" value="1" id="notificationPermissionEnabled" @checked(old('notification_permission_enabled', '1') == '1')>
                                <label class="form-check-label fw-semibold" for="notificationPermissionEnabled">Notification Permission Enabled</label>
                            </div>
                        </div>

                        <div class="col-12"><hr class="my-2"></div>
                        <div class="col-12">
                            <label class="form-label fw-bold mb-0">Documents</label>
                            <div class="form-text mb-2">Same documents collected during driver-app signup — optional here, but admin can upload or replace them.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Profile Photo</label>
                            <input type="file" name="profile_photo" accept="image/*" class="form-control @error('profile_photo') is-invalid @enderror">
                            @error('profile_photo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Vehicle Image</label>
                            <input type="file" name="vehicle_image" accept="image/*" class="form-control @error('vehicle_image') is-invalid @enderror">
                            @error('vehicle_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">License Document</label>
                            <input type="file" name="license_document" accept=".pdf,image/*" class="form-control @error('license_document') is-invalid @enderror">
                            @error('license_document') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Vehicle RC</label>
                            <input type="file" name="vehicle_rc" accept=".pdf,image/*" class="form-control @error('vehicle_rc') is-invalid @enderror">
                            @error('vehicle_rc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Aadhar Card</label>
                            <input type="file" name="aadhar_card" accept=".pdf,image/*" class="form-control @error('aadhar_card') is-invalid @enderror">
                            @error('aadhar_card') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">PAN Card</label>
                            <input type="file" name="pan_card" accept=".pdf,image/*" class="form-control @error('pan_card') is-invalid @enderror">
                            @error('pan_card') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Insurance Document</label>
                            <input type="file" name="insurance_document" accept=".pdf,image/*" class="form-control @error('insurance_document') is-invalid @enderror">
                            @error('insurance_document') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12"><hr class="my-2"></div>

                        @include('admin.partials.payout-account-fields', ['values' => [
                            'account_holder_name' => old('account_holder_name'),
                            'bank_name' => old('bank_name'),
                            'account_number' => old('account_number'),
                            'ifsc_code' => old('ifsc_code'),
                            'upi_id' => old('upi_id'),
                            'stripe_account_id' => old('stripe_account_id'),
                            'gateway_account_id' => old('gateway_account_id', old('stripe_account_id')),
                        ]])
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Create Driver
                        </button>
                        <a href="{{ route('admin.drivers.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Information</h5>
            </div>
            <div class="p-4">
                <div class="text-center mb-3">
                    <i class="fas fa-truck fa-3x text-primary"></i>
                </div>
                <p class="small text-muted">
                    Drivers will receive login credentials via email. They can access the driver app using their email and password.
                </p>
                <hr>
                <div class="small text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Vehicle number and license details are required for verification purposes.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@php
    $deliveryAreaPayload = $deliveryAreas->map(function ($area) {
        return [
            'id' => $area->id,
            'name' => $area->name,
            'area_type' => $area->area_type,
            'description' => $area->description,
            'latitude' => $area->latitude,
            'longitude' => $area->longitude,
            'radius_km' => $area->radius_km,
            'polygon_coordinates' => $area->polygon_coordinates,
        ];
    })->values();
@endphp
@include('partials.google-maps-shim')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deliveryAreas = @json($deliveryAreaPayload);
        const visibleGatewayAccountField = document.getElementById('stripe_account_id');
        const hiddenGatewayAccountField = document.getElementById('gateway_account_id');
        const searchBtn = document.getElementById('searchAddressBtn');
        const useLocationBtn = document.getElementById('useLocationBtn');
        const searchInput = document.getElementById('searchAddress');
        const addressField = document.getElementById('address');
        const latField = document.getElementById('latitude');
        const lngField = document.getElementById('longitude');
        const statusEl = document.getElementById('locationStatus');
        const zonePreviewName = document.getElementById('zonePreviewName');
        const zonePreviewMeta = document.getElementById('zonePreviewMeta');
        const deliveryAreaSelect = document.getElementById('deliveryAreaSelect');

        let map;
        let marker;

        if (visibleGatewayAccountField && hiddenGatewayAccountField) {
            const syncGatewayAccountId = () => {
                hiddenGatewayAccountField.value = visibleGatewayAccountField.value;
            };

            visibleGatewayAccountField.addEventListener('input', syncGatewayAccountId);
            syncGatewayAccountId();
        }

        function updateStatus(text) {
            if (statusEl) {
                statusEl.textContent = text;
            }
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
                zonePreviewMeta.textContent = 'Zone will be fetched automatically from the selected map location.';
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
            // Keep the manual zone selector in sync with what auto-detect
            // found, so it's correct by default and only needs touching
            // when detection picked the wrong one.
            if (deliveryAreaSelect) deliveryAreaSelect.value = String(zone.id);
        }

        function createMap() {
            const initialLat = parseFloat(latField.value) || 20;
            const initialLng = parseFloat(lngField.value) || 0;
            const initialZoom = latField.value && lngField.value ? 13 : 2;

            map = L.map('driverMap').setView([initialLat, initialLng], initialZoom);

            if (latField.value && lngField.value) {
                updateMarker(initialLat, initialLng);
            }

            map.on('click', function (e) {
                updateMarker(e.latlng.lat, e.latlng.lng);
                reverseGeocode(e.latlng.lat, e.latlng.lng);
            });
        }

        function updateMarker(lat, lng) {
            const latLng = L.latLng(lat, lng);

            if (!marker) {
                marker = L.marker(latLng, { draggable: true }).addTo(map);
                marker.on('moveend', function (event) {
                    const position = event.target.getLatLng();
                    updateMarker(position.lat, position.lng);
                    reverseGeocode(position.lat, position.lng);
                });
            } else {
                marker.setLatLng(latLng);
            }

            map.setView(latLng, 14);
            latField.value = Number(lat).toFixed(6);
            lngField.value = Number(lng).toFixed(6);
            updateZonePreview(Number(lat), Number(lng));
        }

        async function fillLocation(result) {
            if (!result) {
                updateStatus('Location could not be resolved.');
                return;
            }

            addressField.value = result.display_name || addressField.value;
            if (result.lat && result.lon) {
                updateMarker(Number(result.lat), Number(result.lon));
            }
            updateStatus('Driver location set from address search or geolocation.');
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
                await fillLocation(data);
            } catch (error) {
                console.error(error);
                updateStatus('Unable to resolve location from the map point.');
            }
        }

        async function lookupAddress(query) {
            if (!query) {
                updateStatus('Enter an address, landmark, or pincode to search.');
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

        function detectLocation() {
            if (!navigator.geolocation) {
                updateStatus('Geolocation is not available in your browser.');
                return;
            }

            updateStatus('Detecting current location...');
            navigator.geolocation.getCurrentPosition(async (position) => {
                // Capture the real GPS reading immediately -- don't depend on
                // the address reverse-lookup succeeding just to set lat/lng.
                updateMarker(position.coords.latitude, position.coords.longitude);
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

        createMap();
        updateZonePreview(parseFloat(latField.value), parseFloat(lngField.value));
    });
</script>
@endsection




