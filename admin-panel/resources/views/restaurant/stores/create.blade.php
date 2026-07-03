@extends('layouts.restaurant')
@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $mapsApiKey = $googleMapsApiKey ?? App\Models\AppSetting::getValue('google_maps_api_key', App\Models\AppSetting::getValue('google_maps_key', ''));
@endphp

@section('title', 'Add New Restaurant')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Add New Restaurant</h1>
                <p class="text-muted">Create a new restaurant location. It will be active after admin approval.</p>
            </div>
            <a href="{{ route('restaurant.stores.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back to Stores
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Restaurant Information</h5>
                </div>
                <div class="p-4">
                    <form action="{{ route('restaurant.stores.store') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Restaurant Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                       value="{{ old('name') }}" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Restaurant Type <span class="text-danger">*</span></label>
                                <select name="restaurant_type" class="form-select @error('restaurant_type') is-invalid @enderror" required>
                                    <option value="delivery" {{ old('restaurant_type') == 'delivery' ? 'selected' : '' }}>Delivery Only</option>
                                    <option value="dining" {{ old('restaurant_type') == 'dining' ? 'selected' : '' }}>Dining Only</option>
                                    <option value="takeaway" {{ old('restaurant_type') == 'takeaway' ? 'selected' : '' }}>Takeaway Only</option>
                                    <option value="both" {{ old('restaurant_type') == 'both' ? 'selected' : '' }}>Delivery & Dining</option>
                                    <option value="delivery_takeaway" {{ old('restaurant_type') == 'delivery_takeaway' ? 'selected' : '' }}>Delivery & Takeaway</option>
                                    <option value="dining_takeaway" {{ old('restaurant_type') == 'dining_takeaway' ? 'selected' : '' }}>Dining & Takeaway</option>
                                    <option value="all" {{ old('restaurant_type') == 'all' ? 'selected' : '' }}>Delivery, Dining & Takeaway</option>
                                </select>
                                @error('restaurant_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Dining Charge ({{ $currencySymbol }})</label>
                                <input type="number" name="dining_charge" class="form-control @error('dining_charge') is-invalid @enderror" value="{{ old('dining_charge', 0) }}" min="0" step="0.5">
                                @error('dining_charge') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                       value="{{ old('email') }}" required>
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                                       value="{{ old('phone') }}" required>
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
                            <textarea name="address" id="address" class="form-control @error('address') is-invalid @enderror" rows="2" required>{{ old('address') }}</textarea>
                            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                                <input type="text" name="city" id="city" class="form-control @error('city') is-invalid @enderror" 
                                       value="{{ old('city') }}" required>
                                @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">State <span class="text-danger">*</span></label>
                                <input type="text" name="state" id="state" class="form-control @error('state') is-invalid @enderror" 
                                       value="{{ old('state') }}" required>
                                @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Pincode <span class="text-danger">*</span></label>
                                <input type="text" name="pincode" id="pincode" class="form-control @error('pincode') is-invalid @enderror" 
                                       value="{{ old('pincode') }}" required>
                                @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Latitude <span class="text-danger">*</span></label>
                                <input type="number" step="any" name="latitude" id="latitude" class="form-control @error('latitude') is-invalid @enderror" 
                                       value="{{ old('latitude') }}" required>
                                @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Longitude <span class="text-danger">*</span></label>
                                <input type="number" step="any" name="longitude" id="longitude" class="form-control @error('longitude') is-invalid @enderror" 
                                       value="{{ old('longitude') }}" required>
                                @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            After submission, your restaurant will be reviewed by admin. You will be notified once approved.
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i> Create Restaurant
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Map Location</h5>
                </div>
                <div class="p-3">
                    <label class="form-label fw-semibold">Search Location</label>
                    <div class="input-group mb-3">
                        <input type="text" id="locationSearch" class="form-control" placeholder="Search restaurant address or landmark" value="{{ old('address') }}">
                        <button type="button" id="searchLocationBtn" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div id="map" style="height: 300px; border-radius: 12px;"></div>
                    <div id="locationStatus" class="form-text mt-2">
                        Search an address, click the map, drag the pin, or use current location.
                    </div>
                    <button type="button" id="getLocationBtn" class="btn btn-sm btn-outline-primary w-100 mt-3">
                        <i class="fas fa-location-dot me-2"></i> Use My Current Location
                    </button>
                    @if(empty($mapsApiKey))
                        <div class="alert alert-warning small mt-3 mb-0">
                            Google Maps API key is not configured in admin map settings.
                        </div>
                    @endif
                </div>
            </div>

            <div class="table-card mt-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Important Notes</h5>
                </div>
                <div class="p-4">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Admin approval required before going live</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> You can add menu items after approval</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Each restaurant has separate menu and orders</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> You can switch between restaurants anytime</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@if(!empty($mapsApiKey))
<script>
    let map;
    let marker;
    let geocoder;
    let autocomplete;

    const defaultLocation = { lat: 28.6139, lng: 77.2090 };

    function field(id) {
        return document.getElementById(id);
    }

    function setStatus(message) {
        const status = field('locationStatus');
        if (status) status.textContent = message;
    }

    function setCoordinates(lat, lng) {
        field('latitude').value = Number(lat).toFixed(7);
        field('longitude').value = Number(lng).toFixed(7);
    }

    function movePicker(latLng, zoom = 16) {
        map.setCenter(latLng);
        map.setZoom(zoom);
        marker.setPosition(latLng);
        setCoordinates(latLng.lat(), latLng.lng());
    }

    function componentValue(place, types) {
        const components = place.address_components || [];
        const component = components.find((item) => types.some((type) => item.types.includes(type)));
        return component?.long_name || '';
    }

    function fillAddressFields(place) {
        if (!place) return;

        const formattedAddress = place.formatted_address || place.name || '';
        if (formattedAddress) {
            field('address').value = formattedAddress;
            field('locationSearch').value = formattedAddress;
        }

        const city = componentValue(place, ['locality', 'postal_town', 'administrative_area_level_3', 'sublocality']);
        const state = componentValue(place, ['administrative_area_level_1']);
        const pincode = componentValue(place, ['postal_code']);

        if (city) field('city').value = city;
        if (state) field('state').value = state;
        if (pincode) field('pincode').value = pincode;
    }

    function reverseGeocode(latLng) {
        geocoder.geocode({ location: latLng }, function(results, status) {
            if (status === 'OK' && results[0]) {
                fillAddressFields(results[0]);
                setStatus('Location selected from map.');
            } else {
                setStatus('Location selected. Address could not be resolved automatically.');
            }
        });
    }

    function applyPlace(place) {
        if (!place?.geometry?.location) {
            setStatus('Select a suggested address or try a more specific search.');
            return;
        }

        movePicker(place.geometry.location);
        fillAddressFields(place);
        setStatus('Location selected from address search.');
    }

    function initMap() {
        const oldLat = parseFloat(field('latitude').value);
        const oldLng = parseFloat(field('longitude').value);
        const start = Number.isFinite(oldLat) && Number.isFinite(oldLng)
            ? { lat: oldLat, lng: oldLng }
            : defaultLocation;
        
        map = new google.maps.Map(field('map'), {
            center: start,
            zoom: Number.isFinite(oldLat) && Number.isFinite(oldLng) ? 16 : 12,
            mapTypeControl: false,
            streetViewControl: false,
        });

        geocoder = new google.maps.Geocoder();
        
        marker = new google.maps.Marker({
            map: map,
            draggable: true,
            position: start
        });

        if (Number.isFinite(oldLat) && Number.isFinite(oldLng)) {
            setCoordinates(oldLat, oldLng);
        }
        
        google.maps.event.addListener(marker, 'dragend', function(event) {
            setCoordinates(event.latLng.lat(), event.latLng.lng());
            reverseGeocode(event.latLng);
        });

        map.addListener('click', function(event) {
            movePicker(event.latLng);
            reverseGeocode(event.latLng);
        });

        autocomplete = new google.maps.places.Autocomplete(field('locationSearch'), {
            fields: ['address_components', 'formatted_address', 'geometry', 'name'],
        });
        autocomplete.bindTo('bounds', map);
        autocomplete.addListener('place_changed', function() {
            applyPlace(autocomplete.getPlace());
        });

        field('searchLocationBtn')?.addEventListener('click', function() {
            const query = field('locationSearch').value.trim()
                || field('address').value.trim()
                || [field('city').value, field('state').value, field('pincode').value].filter(Boolean).join(', ');

            if (!query) {
                setStatus('Enter an address or landmark to search.');
                return;
            }

            geocoder.geocode({ address: query }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    applyPlace(results[0]);
                } else {
                    setStatus('No location found for that search.');
                }
            });
        });
        
        field('locationSearch')?.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                field('searchLocationBtn')?.click();
            }
        });
    }
    
    field('getLocationBtn')?.addEventListener('click', function() {
        if (navigator.geolocation) {
            setStatus('Detecting current location...');
            navigator.geolocation.getCurrentPosition(function(position) {
                const latLng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
                movePicker(latLng);
                reverseGeocode(latLng);
            }, function() {
                setStatus('Unable to detect current location. Please allow browser location access.');
            }, { timeout: 10000 });
        } else {
            setStatus('Geolocation is not supported by this browser.');
        }
    });
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ $mapsApiKey }}&libraries=places&callback=initMap&loading=async" async defer></script>
@else
<script>
    document.getElementById('locationStatus').textContent = 'Google Maps is disabled because the admin map key is missing.';
</script>
@endif
@endsection
