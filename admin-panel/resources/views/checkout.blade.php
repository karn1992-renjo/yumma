@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp
{{-- resources/views/checkout.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Checkout - {{ $appName ?? config('app.name') }}</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    
    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey ?? App\Models\AppSetting::getValue('google_maps_api_key', App\Models\AppSetting::getValue('google_maps_key', '')) }}&libraries=places&callback=initGoogleMaps" async defer></script>
    
    <style>
        :root {
            --primary: {{ $primaryColor ?? '#EF4F5F' }};
            --primary-dark: {{ $primaryDark ?? '#E03546' }};
            --success: #10B981;
            --warning: #F59E0B;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #F8F9FA;
        }
        
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .order-summary {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            position: sticky;
            top: 20px;
        }
        
        .payment-method {
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            height: 100%;
            background: #fff;
        }
        
        .payment-method.selected {
            border-color: var(--primary);
            background: #FEE2E4;
        }
        
        .payment-method:hover {
            border-color: var(--primary);
        }
        
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            padding: 0.875rem;
            font-weight: 600;
            border-radius: 12px;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .address-card {
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .address-card.selected {
            border-color: var(--primary);
            background: #FEE2E4;
        }
        
        .address-card:hover {
            border-color: var(--primary);
        }
        
        /* Address Form Styles */
        .address-form-container {
            margin-top: 1rem;
            padding: 1rem;
            background: #F8F9FA;
            border-radius: 12px;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #E5E7EB;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(239,79,95,0.1);
        }
        
        .btn-detect {
            background: #3B82F6;
            color: white;
            border: none;
            padding: 0.625rem 1rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .btn-detect:hover {
            background: #2563EB;
        }
        
        .location-loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3B82F6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .map-container {
            height: 250px;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        #map {
            width: 100%;
            height: 100%;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @media (max-width: 768px) {
            .checkout-container {
                padding: 1rem;
            }
        }
        
        /* Autocomplete Styles */
        .pac-container {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-top: 8px;
            font-family: 'Inter', sans-serif;
            z-index: 2000;
        }
        
        .pac-item {
            padding: 10px 16px;
            border-bottom: 1px solid #F0F0F0;
            cursor: pointer;
        }
        
        .pac-item:hover {
            background: #F8F8F8;
        }
        
        .pac-icon {
            margin-right: 12px;
        }
    </style>
@include('partials.public-blade-polish')
</head>
<body>

<nav class="navbar navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold fs-3" href="{{ route('home') }}">
            <i class="fas fa-utensils text-danger me-2"></i>{{ $appName ?? config('app.name') }}
        </a>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary rounded-pill">
            <i class="fas fa-arrow-left me-2"></i>Back to Home
        </a>
    </div>
</nav>

<div class="checkout-container">
    <div class="row g-4">
        <div class="col-lg-7">
            <!-- Saved Addresses -->
            <div class="form-card">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-map-marker-alt text-primary me-2"></i>Saved Addresses
                </h5>
                <div id="savedAddresses">
                    @auth
                        @foreach(Auth::user()->addresses ?? array() as $address)
                            <div class="address-card" data-address-id="{{ $address->id }}" onclick="selectAddress({{ $address->id }})">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ $address->name ?? 'Home' }}</div>
                                        <div class="text-muted small">{{ $address->address }}, {{ $address->city }}, {{ $address->pincode }}</div>
                                        <div class="text-muted small mt-1">{{ $address->phone }}</div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="address_id" value="{{ $address->id }}" onchange="selectAddress({{ $address->id }})">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endauth
                </div>
                
                <button class="btn btn-outline-primary w-100 mt-3" onclick="showNewAddressForm()">
                    <i class="fas fa-plus me-2"></i>Add New Address
                </button>
                
                <!-- New Address Form with Google Maps -->
                <div id="newAddressForm" style="display: none;" class="address-form-container">
                    <h6 class="fw-bold mb-3">Add New Delivery Address</h6>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Search Address</label>
                            <div class="input-group">
                                <input type="text" id="autocompleteInput" class="form-control" placeholder="Search for your address...">
                                <button class="btn btn-detect" type="button" onclick="detectMyLocation()">
                                    <i class="fas fa-location-dot me-1"></i> Detect
                                </button>
                            </div>
                            <small class="text-muted">Start typing your address or click detect to use current location</small>
                        </div>
                        
                        <div class="col-12">
                            <div class="map-container" id="mapContainer" style="display: none;">
                                <div id="map"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Address Name</label>
                            <select id="addressName" class="form-select">
                                <option value="Home">Home</option>
                                <option value="Work">Work</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" id="addressPhone" class="form-control" value="{{ Auth::user()->phone ?? '' }}" placeholder="Phone number">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Full Address</label>
                            <textarea id="addressLine" class="form-control" rows="2" placeholder="Complete address"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" id="addressCity" class="form-control" placeholder="City">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" id="addressState" class="form-control" placeholder="State">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pincode</label>
                            <input type="text" id="addressPincode" class="form-control" placeholder="Pincode">
                        </div>
                        <div class="col-12">
                            <input type="hidden" id="addressLat" value="">
                            <input type="hidden" id="addressLng" value="">
                            <button class="btn btn-primary w-100" onclick="saveNewAddress()">Save Address</button>
                            <button class="btn btn-light w-100 mt-2" onclick="hideNewAddressForm()">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Method -->
            <div class="form-card">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-credit-card text-primary me-2"></i>Payment Method
                </h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="payment-method selected" data-payment="cod" onclick="selectPayment('cod')">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                                <div>
                                    <div class="fw-semibold">Cash on Delivery</div>
                                    <small class="text-muted">Pay when you receive</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="payment-method" data-payment="upi" onclick="selectPayment('upi')">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fas fa-mobile-screen-button fa-2x text-primary"></i>
                                <div>
                                    <div class="fw-semibold">UPI</div>
                                    <small class="text-muted">Google Pay, PhonePe, Paytm</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="payment-method" data-payment="card" onclick="selectPayment('card')">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fas fa-credit-card fa-2x text-danger"></i>
                                <div>
                                    <div class="fw-semibold">Card</div>
                                    <small class="text-muted">Credit or debit card</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="payment-method" data-payment="wallet" onclick="selectPayment('wallet')">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fas fa-wallet fa-2x text-warning"></i>
                                <div>
                                    <div class="fw-semibold">Wallet</div>
                                    <small class="text-muted">Use saved wallet balance</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Coupon Code -->
            <div class="form-card">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-tag text-primary me-2"></i>Apply Coupon
                </h5>
                <div class="input-group">
                    <input type="text" id="couponCode" class="form-control" placeholder="Enter coupon code">
                    <button class="btn btn-outline-primary" onclick="applyCoupon()">Apply</button>
                </div>
                <div id="couponMessage" class="mt-2 small"></div>
            </div>
        </div>
        
        <div class="col-lg-5">
            <div class="order-summary">
                <h5 class="fw-bold mb-3">Order Summary</h5>
                <div id="cartItemsSummary"></div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span>
                    <span class="fw-bold">{{ $currencySymbol }}<span id="summarySubtotal">0</span></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Delivery Fee</span>
                    <span class="fw-bold">{{ $currencySymbol }}<span id="summaryDeliveryFee">0</span></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Taxes</span>
                    <span class="fw-bold">{{ $currencySymbol }}<span id="summaryTax">0</span></span>
                </div>
                <div class="d-flex justify-content-between mb-2 text-success" id="discountRow" style="display: none;">
                    <span>Coupon Discount</span>
                    <span class="fw-bold">-{{ $currencySymbol }}<span id="summaryDiscount">0</span></span>
                </div>
                <div class="d-flex justify-content-between mb-3 pt-2 border-top">
                    <span class="fw-bold fs-5">Total</span>
                    <span class="fw-bold text-primary fs-4">{{ $currencySymbol }}<span id="summaryTotal">0</span></span>
                </div>
                <button class="btn btn-primary w-100 py-3" onclick="placeOrder()" id="placeOrderBtn">
                    <i class="fas fa-check-circle me-2"></i>Place Order
                </button>
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>Secure payment protected
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="locationConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title fw-bold">Confirm delivery location</h5>
                    <div class="text-muted small">Move the pin or detect location before final checkout.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="confirmMap" style="height:320px;border-radius:16px;overflow:hidden;background:#F3F4F6;"></div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-outline-primary" onclick="detectConfirmLocation()">
                        <i class="fas fa-location-crosshairs me-1"></i> Detect location
                    </button>
                    <button type="button" class="btn btn-primary ms-auto" onclick="confirmLocationAndOrder()">Confirm & place order</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Global Variables
    let cart = [];
    let restaurantId = null;
    let selectedAddressId = null;
    let selectedPayment = 'cod';
    let appliedCoupon = null;
    let locationConfirmed = false;
    let confirmMap;
    let confirmMarker;
    let savedAddresses = @auth {!! json_encode(Auth::user()->addresses->keyBy('id')) !!} @else {} @endauth;
    let autocomplete;
    let map;
    let marker;
    
    // Get cart from localStorage
    try {
        const savedCart = localStorage.getItem('checkout_cart');
        if (savedCart) cart = JSON.parse(savedCart);
        const savedRestaurantId = localStorage.getItem('checkout_restaurant_id');
        if (savedRestaurantId) restaurantId = savedRestaurantId;

        if (cart.length === 0) {
            const params = new URLSearchParams(window.location.search);
            if (params.has('cart')) {
                cart = JSON.parse(params.get('cart'));
                localStorage.setItem('checkout_cart', JSON.stringify(cart));
            }
        }

        if (!restaurantId && cart.length > 0 && cart[0].restaurant_id) {
            restaurantId = cart[0].restaurant_id;
            localStorage.setItem('checkout_restaurant_id', restaurantId);
        }
    } catch(e) {
        console.error('Error parsing cart:', e);
        localStorage.removeItem('checkout_cart');
        localStorage.removeItem('checkout_restaurant_id');
    }
    
    // Initialize Google Maps
    function initGoogleMaps() {
        const input = document.getElementById('autocompleteInput');
        if (input && typeof google !== 'undefined') {
            autocomplete = new google.maps.places.Autocomplete(input, {
                types: ['address'],
                componentRestrictions: { country: 'in' }
            });
            
            autocomplete.addListener('place_changed', onPlaceChanged);
        }
        
        // Initialize map
        const mapContainer = document.getElementById('map');
        if (mapContainer && typeof google !== 'undefined') {
            map = new google.maps.Map(mapContainer, {
                center: { lat: 28.6139, lng: 77.2090 },
                zoom: 15
            });
            
            marker = new google.maps.Marker({
                map: map,
                draggable: true,
                position: { lat: 28.6139, lng: 77.2090 }
            });
            
            google.maps.event.addListener(marker, 'dragend', function(event) {
                const lat = event.latLng.lat();
                const lng = event.latLng.lng();
                document.getElementById('addressLat').value = lat;
                document.getElementById('addressLng').value = lng;
                reverseGeocode(lat, lng);
            });
        }
    }
    
    function onPlaceChanged() {
        const place = autocomplete.getPlace();
        if (place && place.geometry) {
            const lat = place.geometry.location.lat();
            const lng = place.geometry.location.lng();
            
            document.getElementById('addressLat').value = lat;
            document.getElementById('addressLng').value = lng;
            
            // Show map and update marker
            document.getElementById('mapContainer').style.display = 'block';
            if (map) {
                map.setCenter({ lat: lat, lng: lng });
                marker.setPosition({ lat: lat, lng: lng });
            }
            
            // Extract address components
            let street = '';
            let city = '';
            let state = '';
            let pincode = '';
            
            if (place.address_components) {
                for (const component of place.address_components) {
                    const types = component.types;
                    if (types.includes('street_number')) street = component.long_name;
                    if (types.includes('route')) street = street ? street + ', ' + component.long_name : component.long_name;
                    if (types.includes('locality')) city = component.long_name;
                    if (types.includes('administrative_area_level_1')) state = component.long_name;
                    if (types.includes('postal_code')) pincode = component.long_name;
                }
            }
            
            document.getElementById('addressLine').value = place.formatted_address || street;
            document.getElementById('addressCity').value = city;
            document.getElementById('addressState').value = state;
            document.getElementById('addressPincode').value = pincode;
        }
    }
    
    function detectMyLocation() {
        const detectBtn = document.querySelector('.btn-detect');
        const originalText = detectBtn.innerHTML;
        detectBtn.innerHTML = '<div class="location-loading me-1"></div> Detecting...';
        detectBtn.disabled = true;
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                document.getElementById('addressLat').value = lat;
                document.getElementById('addressLng').value = lng;
                
                // Show map
                document.getElementById('mapContainer').style.display = 'block';
                if (map) {
                    map.setCenter({ lat: lat, lng: lng });
                    marker.setPosition({ lat: lat, lng: lng });
                }
                
                reverseGeocode(lat, lng);
                detectBtn.innerHTML = originalText;
                detectBtn.disabled = false;
            }, function(error) {
                alert('Unable to detect location. Please enter manually.');
                detectBtn.innerHTML = originalText;
                detectBtn.disabled = false;
            });
        } else {
            alert('Geolocation is not supported by your browser');
            detectBtn.innerHTML = originalText;
            detectBtn.disabled = false;
        }
    }
    
    function reverseGeocode(lat, lng) {
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: { lat: lat, lng: lng } }, function(results, status) {
            if (status === 'OK' && results[0]) {
                const address = results[0].formatted_address;
                document.getElementById('addressLine').value = address;
                
                // Extract components
                let city = '', state = '', pincode = '';
                for (const component of results[0].address_components) {
                    const types = component.types;
                    if (types.includes('locality')) city = component.long_name;
                    if (types.includes('administrative_area_level_1')) state = component.long_name;
                    if (types.includes('postal_code')) pincode = component.long_name;
                }
                document.getElementById('addressCity').value = city;
                document.getElementById('addressState').value = state;
                document.getElementById('addressPincode').value = pincode;
            }
        });
    }
    
    function selectAddress(addressId) {
        selectedAddressId = addressId;
        document.querySelectorAll('.address-card').forEach(card => card.classList.remove('selected'));
        document.querySelector(`.address-card[data-address-id="${addressId}"]`).classList.add('selected');
        document.querySelector(`input[name="address_id"][value="${addressId}"]`).checked = true;
    }
    
    function selectPayment(method) {
        selectedPayment = method;
        document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
        document.querySelector(`.payment-method[data-payment="${method}"]`).classList.add('selected');
    }
    
    function showNewAddressForm() {
        document.getElementById('newAddressForm').style.display = 'block';
        document.getElementById('addressName').value = 'Home';
        document.getElementById('addressPhone').value = '{{ Auth::user()->phone ?? '' }}';
        document.getElementById('addressLine').value = '';
        document.getElementById('addressCity').value = '';
        document.getElementById('addressState').value = '';
        document.getElementById('addressPincode').value = '';
        document.getElementById('autocompleteInput').value = '';
        document.getElementById('mapContainer').style.display = 'none';
    }
    
    function hideNewAddressForm() {
        document.getElementById('newAddressForm').style.display = 'none';
    }
    
    function saveNewAddress() {
        const addressData = {
            name: document.getElementById('addressName').value,
            address: document.getElementById('addressLine').value,
            city: document.getElementById('addressCity').value,
            state: document.getElementById('addressState').value,
            pincode: document.getElementById('addressPincode').value,
            phone: document.getElementById('addressPhone').value,
            latitude: document.getElementById('addressLat').value,
            longitude: document.getElementById('addressLng').value
        };
        
        if (!addressData.address) {
            alert('Please enter your address');
            return;
        }
        
        fetch('{{ route("customer.addresses.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(addressData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to save address');
            }
        })
        .catch(error => {
            alert('Failed to save address');
        });
    }
    
    function loadCartSummary() {
        const subtotal = cart.reduce((sum, item) => sum + ((item.price || 0) * (item.quantity || 0)), 0);
        const deliveryFee = {{ $deliveryFee ?? 0 }};
        const tax = 0;
        let total = subtotal + deliveryFee + tax;
        
        const cartContainer = document.getElementById('cartItemsSummary');
        if (cart.length === 0) {
            cartContainer.innerHTML = '<div class="text-center py-3 text-muted">No items in cart</div>';
            document.getElementById('placeOrderBtn').disabled = true;
        } else {
            cartContainer.innerHTML = cart.map(item => 
                `<div class="d-flex justify-content-between mb-2">
                    <div>
                        <span class="fw-semibold">${item.quantity}x</span> ${escapeHtml(item.name)}
                        ${item.selected_variant ? `<div class="small text-muted">${escapeHtml(item.selected_variant.name)}</div>` : ''}
                        ${item.selected_add_ons?.length ? `<div class="small text-muted">Extras: ${item.selected_add_ons.map(addon => escapeHtml(addon.name)).join(', ')}</div>` : ''}
                    </div>
                    <span>{{ $currencySymbol }}${((item.price || 0) * (item.quantity || 0)).toFixed(window.currencyDecimals)}</span>
                </div>`
            ).join('');
            document.getElementById('placeOrderBtn').disabled = false;
        }
        
        document.getElementById('summarySubtotal').innerText = subtotal.toFixed(window.currencyDecimals);
        document.getElementById('summaryDeliveryFee').innerText = deliveryFee;
        document.getElementById('summaryTax').innerText = tax.toFixed(window.currencyDecimals);
        document.getElementById('summaryTotal').innerText = total.toFixed(window.currencyDecimals);
    }

    function addSuggestedItem(item) {
        const variant = Array.isArray(item.variants) && item.variants.length ? item.variants[0] : null;
        item.selected_variant = variant;
        item.selected_add_ons = [];
        item.price = Number(item.price || 0) + Number(variant?.price || 0);
        delete item.variants;
        delete item.add_ons;
        cart.push(item);
        localStorage.setItem('checkout_cart', JSON.stringify(cart));
        loadCartSummary();
    }

    function addSuggestedItemFromButton(button) {
        const item = {
            id: Number(button.dataset.id || 0),
            name: button.dataset.name || '',
            price: Number(button.dataset.price || 0),
            quantity: 1,
            restaurant_id: Number(button.dataset.restaurantId || 0),
            variants: [],
            add_ons: []
        };

        addSuggestedItem(item);
    }
    
    function applyCoupon() {
        const code = document.getElementById('couponCode').value;
        if (!code) return;
        
        const applyBtn = document.querySelector('#couponCode + button');
        applyBtn.disabled = true;
        applyBtn.innerHTML = '<div class="loading-spinner"></div>';
        
        fetch('{{ route("coupon.apply") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                code,
                restaurant_id: restaurantId,
                subtotal: parseFloat(document.getElementById('summarySubtotal').innerText)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appliedCoupon = data.coupon_code;
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('summaryDiscount').innerText = data.discount;
                const total = parseFloat(document.getElementById('summarySubtotal').innerText) + 
                             parseFloat(document.getElementById('summaryDeliveryFee').innerText) + 
                             parseFloat(document.getElementById('summaryTax').innerText) - data.discount;
                document.getElementById('summaryTotal').innerText = total.toFixed(window.currencyDecimals);
                document.getElementById('couponMessage').innerHTML = `<span class="text-success">${data.message}</span>`;
            } else {
                document.getElementById('couponMessage').innerHTML = `<span class="text-danger">${data.message}</span>`;
            }
        })
        .finally(() => {
            applyBtn.disabled = false;
            applyBtn.innerHTML = 'Apply';
        });
    }
    
    function placeOrder() {
        if (!selectedAddressId) {
            alert('Please select a delivery address');
            return;
        }
        if (!selectedPayment) {
            alert('Please select a payment method');
            return;
        }
        if (cart.length === 0) {
            alert('Your cart is empty');
            return;
        }
        if (!locationConfirmed) {
            showLocationConfirm();
            return;
        }
        
        const orderData = {
            restaurant_id: restaurantId,
            items: cart.map(item => ({
                id: item.id,
                quantity: item.quantity,
                selected_variant: item.selected_variant || null,
                selected_add_ons: item.selected_add_ons || []
            })),
            delivery_address_id: parseInt(selectedAddressId),
            payment_method: selectedPayment,
            coupon_code: appliedCoupon || null
        };
        
        const placeBtn = document.getElementById('placeOrderBtn');
        placeBtn.disabled = true;
        placeBtn.innerHTML = '<div class="loading-spinner me-2"></div> Placing Order...';
        
        fetch('{{ route("checkout.process") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(orderData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                localStorage.removeItem('checkout_cart');
                localStorage.removeItem('checkout_restaurant_id');
                if (restaurantId) {
                    localStorage.removeItem('cart_' + restaurantId);
                }
                alert('Order placed successfully!');
                window.location.href = `/customer/orders/${data.order_id}/track`;
            } else {
                alert(data.message || 'Failed to place order');
                placeBtn.disabled = false;
                placeBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Place Order';
            }
        })
        .catch(error => {
            alert('Failed to place order. Please try again.');
            placeBtn.disabled = false;
            placeBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Place Order';
        });
    }

    function showLocationConfirm() {
        const address = savedAddresses[selectedAddressId] || {};
        const lat = parseFloat(address.latitude || address.lat || 28.6139);
        const lng = parseFloat(address.longitude || address.lng || 77.2090);
        new bootstrap.Modal(document.getElementById('locationConfirmModal')).show();
        setTimeout(() => initializeConfirmMap(lat, lng), 300);
    }

    function initializeConfirmMap(lat, lng) {
        if (typeof google === 'undefined') return;
        const target = { lat, lng };
        if (!confirmMap) {
            confirmMap = new google.maps.Map(document.getElementById('confirmMap'), { center: target, zoom: 16 });
            confirmMarker = new google.maps.Marker({ map: confirmMap, position: target, draggable: true });
        } else {
            confirmMap.setCenter(target);
            confirmMarker.setPosition(target);
        }
    }

    function detectConfirmLocation() {
        if (!navigator.geolocation) {
            alert('Location detection is not available.');
            return;
        }
        navigator.geolocation.getCurrentPosition(position => {
            initializeConfirmMap(position.coords.latitude, position.coords.longitude);
        }, () => alert('Unable to detect location.'));
    }

    function confirmLocationAndOrder() {
        locationConfirmed = true;
        bootstrap.Modal.getInstance(document.getElementById('locationConfirmModal'))?.hide();
        placeOrder();
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize
    loadCartSummary();
    
    // Pre-select first address if available
    const firstAddress = document.querySelector('input[name="address_id"]');
    if (firstAddress) {
        firstAddress.checked = true;
        selectedAddressId = firstAddress.value;
        document.querySelector(`.address-card[data-address-id="${selectedAddressId}"]`).classList.add('selected');
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.web-visit-tracker', ['panel' => 'checkout'])
</body>
</html>
