{{-- resources/views/restaurant/settings/index.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Restaurant Settings')

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
    .restaurant-settings-page .stat-card,
    .restaurant-settings-page .input-group {
        min-width: 0;
    }
    .restaurant-settings-page code {
        white-space: normal;
        word-break: break-word;
    }
    @media (max-width: 575.98px) {
        .restaurant-settings-page .input-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .restaurant-settings-page .input-group .form-control,
        .restaurant-settings-page .input-group .btn {
            width: 100%;
            border-radius: 0.375rem !important;
        }
        #restaurantMap {
            height: 260px;
        }
    }
</style>
@endsection

@section('content')
<div class="restaurant-settings-page">
<div class="page-header">
    <div>
        <h1>Restaurant Settings</h1>
        <p>Manage your restaurant profile and preferences</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Basic Information -->
        <div class="stat-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-store me-2 text-primary"></i> Basic Information
                </h5>
                @if($restaurant->exists)
                    <span class="badge {{ $restaurant->is_verified ? 'bg-success' : 'bg-warning' }} bg-opacity-10 
                                  text-{{ $restaurant->is_verified ? 'success' : 'warning' }}">
                        {{ $restaurant->is_verified ? 'âœ“ Verified' : 'Pending Verification' }}
                    </span>
                @endif
            </div>
            
            <form action="{{ route('restaurant.settings.update') }}" method="POST">
                @csrf
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Restaurant Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" 
                               class="form-control @error('name') is-invalid @enderror" 
                               value="{{ old('name', $restaurant->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" 
                               class="form-control @error('phone') is-invalid @enderror" 
                               value="{{ old('phone', $restaurant->phone) }}" required>
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" 
                               class="form-control @error('email') is-invalid @enderror" 
                               value="{{ old('email', $restaurant->email) }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pincode</label>
                        <input type="text" name="pincode" 
                               class="form-control @error('pincode') is-invalid @enderror" 
                               value="{{ old('pincode', $restaurant->pincode) }}">
                        @error('pincode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                        <input type="text" name="city" 
                               class="form-control @error('city') is-invalid @enderror" 
                               value="{{ old('city', $restaurant->city) }}" required>
                        @error('city')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">State <span class="text-danger">*</span></label>
                        <input type="text" name="state" 
                               class="form-control @error('state') is-invalid @enderror" 
                               value="{{ old('state', $restaurant->state) }}" required>
                        @error('state')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label fw-semibold">Restaurant Location <span class="text-danger">*</span></label>
                        <div class="input-group mb-2">
                            <input type="text" id="searchAddress" class="form-control" placeholder="Search address or landmark" value="{{ old('address', $restaurant->address) }}">
                            <button type="button" class="btn btn-outline-secondary" id="searchAddressBtn">Search</button>
                            <button type="button" class="btn btn-outline-secondary" id="useLocationBtn">Use My Location</button>
                        </div>
                        <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" 
                                  rows="3" required>{{ old('address', $restaurant->address) }}</textarea>
                        <div id="restaurantMap" class="border rounded mt-3"></div>
                        <div id="mapLegend" class="map-legend mt-2">Click the map or drag the pin to reposition your restaurant address.</div>
                        <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $restaurant->latitude) }}">
                        <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $restaurant->longitude) }}">
                        <div id="locationStatus" class="form-text">Allow location or search manually to populate your address and location coordinates.</div>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @error('latitude')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @error('longitude')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="text-end">
                    <button type="submit" class="btn btn-primary rounded-3 btn-lg">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Restaurant Info Card -->
        <div class="stat-card mb-4">
            <h5 class="mb-4 fw-bold">
                <i class="fas fa-info-circle me-2 text-primary"></i> Restaurant Info
            </h5>
            
            <div class="mb-3">
                <small class="text-muted d-block">Status</small>
                <span class="badge {{ $restaurant->is_open ? 'bg-success' : 'bg-secondary' }} bg-opacity-10 
                              text-{{ $restaurant->is_open ? 'success' : 'secondary' }} fs-6">
                    {{ $restaurant->is_open ? 'Open' : 'Closed' }}
                </span>
            </div>
            
            @if($restaurant->exists)
                <div class="mb-3">
                    <small class="text-muted d-block">Member Since</small>
                    <span class="fw-semibold">{{ $restaurant->created_at->format('F d, Y') }}</span>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted d-block">Slug</small>
                    <code class="text-primary">{{ $restaurant->slug }}</code>
                </div>
            @endif
            
            <div class="mb-0">
                <small class="text-muted d-block">Rating</small>
                <div class="d-flex align-items-center gap-2">
                    <span class="h4 mb-0 fw-bold">{{ number_format($restaurant->rating ?? 0, 1) }}</span>
                    <div class="text-warning">
                        @for($i = 1; $i <= 5; $i++)
                            @if(($restaurant->rating ?? 0) >= $i)
                                <i class="fas fa-star"></i>
                            @elseif(($restaurant->rating ?? 0) >= $i - 0.5)
                                <i class="fas fa-star-half-alt"></i>
                            @else
                                <i class="far fa-star"></i>
                            @endif
                        @endfor
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="stat-card mb-4">
            <h5 class="mb-3 fw-bold">
                <i class="fas fa-bolt me-2 text-primary"></i> Quick Actions
            </h5>
            <div class="d-grid gap-2">
                <a href="{{ route('restaurant.menu.create') }}" class="btn btn-outline-primary btn-sm rounded-3">
                    <i class="fas fa-plus me-2"></i> Add Menu Item
                </a>
                <a href="{{ route('restaurant.categories.create') }}" class="btn btn-outline-primary btn-sm rounded-3">
                    <i class="fas fa-folder-plus me-2"></i> Add Category
                </a>
                <a href="{{ route('restaurant.promos.create') }}" class="btn btn-outline-primary btn-sm rounded-3">
                    <i class="fas fa-tag me-2"></i> Create Promo
                </a>
                <a href="{{ route('restaurant.analytics.index') }}" class="btn btn-outline-primary btn-sm rounded-3">
                    <i class="fas fa-chart-bar me-2"></i> View Analytics
                </a>
            </div>
        </div>
    </div>
</div>
</div>

@endsection

@section('scripts')
@include('partials.google-maps-shim')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchBtn = document.getElementById('searchAddressBtn');
        const useLocationBtn = document.getElementById('useLocationBtn');
        const searchInput = document.getElementById('searchAddress');
        const addressField = document.getElementById('address');
        const cityField = document.querySelector('input[name="city"]');
        const stateField = document.querySelector('input[name="state"]');
        const pincodeField = document.querySelector('input[name="pincode"]');
        const latField = document.getElementById('latitude');
        const lngField = document.getElementById('longitude');
        const statusEl = document.getElementById('locationStatus');
        const mapContainer = document.getElementById('restaurantMap');

        let map;
        let marker;

        function updateStatus(text) {
            if (statusEl) statusEl.textContent = text;
        }

        function createMap() {
            if (!mapContainer) {
                return;
            }

            const initialLat = parseFloat(latField.value) || 20;
            const initialLng = parseFloat(lngField.value) || 0;
            const initialZoom = latField.value && lngField.value ? 13 : 2;

            map = L.map('restaurantMap').setView([initialLat, initialLng], initialZoom);

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
            latField.value = lat;
            lngField.value = lng;
            updateLegend();
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
            updateLegend();
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
        useLocationBtn?.addEventListener('click', detectLocation);

        function updateLegend() {
            const legendEl = document.getElementById('mapLegend');
            if (legendEl) {
                const lat = latField.value ? parseFloat(latField.value).toFixed(4) : 'not set';
                const lng = lngField.value ? parseFloat(lngField.value).toFixed(4) : 'not set';
                legendEl.innerHTML = `Map location: <strong>${lat}, ${lng}</strong>. Click on the map or drag the marker to adjust your address.`;
            }
        }

        createMap();
        updateLegend();
    });
</script>
@endsection




