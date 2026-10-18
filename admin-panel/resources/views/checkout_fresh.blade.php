{{-- resources/views/checkout_fresh.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Checkout - {{ $appName ?? config('app.name') }}</title>
    @php $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol(); @endphp

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>
    @if($gatewayEnabled && $gatewayProvider === 'razorpay')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    @elseif($gatewayEnabled && $gatewayProvider === 'cashfree')
        <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
    @endif
    {{-- Google Maps JS is loaded once by partials.frontend-header (shared
         across every page) -- this page's own map/autocomplete setup below
         hooks into that same load instead of loading the Maps API a second
         time, which Google's own JS throws a console warning for and can
         reset window.google mid-session. --}}

    <style>
        :root {
            --primary: {{ $primaryColor ?? '#2563EB' }};
            --primary-dark: {{ $primaryDark ?? '#1D4ED8' }};
            --app-ink: #11223A;
            --app-muted: #7A8699;
            --app-border: #E7EBF2;
            --app-bg: #F4F6FA;
            --app-success: #11924E;
            --app-danger: #E53935;
            --app-card-radius: 18px;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            font-family: 'Nunito Sans', sans-serif;
            background: var(--app-bg);
        }

        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .form-card,
        .order-summary {
            background: #fff;
            border: 1px solid var(--app-border);
            border-radius: var(--app-card-radius);
            padding: 1.5rem;
            box-shadow: 0 6px 14px rgba(17, 34, 58, 0.06);
        }

        .form-card {
            margin-bottom: 1.5rem;
        }

        .order-summary {
            position: sticky;
            top: 20px;
            /* public-blade-polish.blade.php applies backdrop-filter to .order-summary,
               which creates a new containing block for position:fixed descendants --
               neutralized here so .order-summary-cta can anchor to the viewport, not
               this card's own box. */
            backdrop-filter: none !important;
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

        .payment-method.selected,
        .address-card.selected {
            border-color: var(--primary);
            background: #FEE2E4;
        }

        .payment-method:hover,
        .address-card:hover {
            border-color: var(--primary);
        }

        .payment-method.disabled {
            opacity: 0.55;
            pointer-events: none;
            border-color: #E5E7EB;
            background: #F8F9FA;
        }

        .coupon-list {
            display: grid;
            gap: 12px;
        }

        .coupon-chip {
            position: relative;
            min-height: 116px;
            border: 1px solid #F3D5BD;
            border-radius: 18px;
            padding: 16px 104px 16px 18px;
            background: linear-gradient(135deg, #FFFAF4 0%, #FFFFFF 52%, #FFF1E5 100%);
            cursor: pointer;
            transition: all 0.2s;
            overflow: hidden;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.07);
        }

        .coupon-chip::before {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            width: 5px;
            background: linear-gradient(180deg, var(--primary), #F97316);
        }

        .coupon-chip::after {
            content: '';
            position: absolute;
            top: 16px;
            bottom: 16px;
            right: 78px;
            border-right: 1px dashed #F2B985;
        }

        .coupon-chip:hover,
        .coupon-chip.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, #FFF7ED 0%, #FFFFFF 52%, #FFE4E6 100%);
            transform: translateY(-1px);
        }

        .coupon-discount-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #FED7AA;
            color: #C2410C;
            font-size: 12px;
            font-weight: 800;
        }

        .coupon-code-orb {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #111827;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 9px;
            font-weight: 900;
            letter-spacing: .04em;
            padding: 6px;
            word-break: break-word;
        }

        .coupon-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            color: #6B7280;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            padding: 0.875rem;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            border-radius: 16px;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .order-summary-cta {
            margin-top: 0.5rem;
        }

        .mobile-pay-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: 600;
            color: var(--app-muted);
            margin-bottom: 10px;
        }

        .mobile-pay-row a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .checkout-place-btn {
            width: 100%;
            height: 54px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 30%, transparent);
            border: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            color: #fff;
            transition: box-shadow 0.15s ease, transform 0.15s ease;
        }

        .checkout-place-btn:hover {
            box-shadow: 0 14px 30px color-mix(in srgb, var(--primary) 36%, transparent);
            transform: translateY(-1px);
            color: #fff;
        }

        .cpb-total {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.15;
        }

        .cpb-total-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.05em;
            opacity: 0.85;
        }

        .cpb-total-amount {
            font-size: 16px;
            font-weight: 900;
        }

        .cpb-cta {
            font-size: 14px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .address-card {
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .address-summary-tile {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            border: 1px solid var(--app-border, #E5E7EB);
            border-radius: 14px;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .address-summary-tile:hover {
            border-color: var(--primary);
        }

        .address-summary-tile > i.fa-location-dot {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: color-mix(in srgb, var(--primary) 10%, white);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .address-summary-tile .min-w-0 {
            min-width: 0;
        }

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

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #E5E7EB;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(239, 79, 95, 0.1);
        }

        .btn-detect {
            background: #3B82F6;
            color: #fff;
            border: none;
            padding: 0.625rem 1rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .btn-detect:hover {
            background: #2563EB;
        }

        .location-loading,
        .loading-spinner {
            display: inline-block;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .location-loading {
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3B82F6;
        }

        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary);
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

        #map,
        #confirmMap {
            width: 100%;
            height: 100%;
        }

        .pac-container {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 8px;
            font-family: 'Nunito Sans', sans-serif;
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

        @media (max-width: 768px) {
            .checkout-container {
                padding: 1rem;
            }
        }

        @media (max-width: 991.98px) {
            .order-summary {
                position: static;
            }

            .order-summary-cta {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 1035;
                background: #fff;
                border-radius: 24px 24px 0 0;
                box-shadow: 0 -8px 24px rgba(17, 34, 58, 0.12);
                padding: 14px 18px calc(14px + env(safe-area-inset-bottom, 0px));
                margin: 0;
            }

            body {
                padding-bottom: calc(132px + env(safe-area-inset-bottom, 0px));
            }
        }
    </style>
@include('partials.public-blade-polish')
</head>
<body>

@include('partials.frontend-header')

<div class="checkout-container">
    <div class="row g-4">
        <div class="col-lg-7">
            @if($restaurant->acceptsService('delivery') || $restaurant->acceptsService('takeaway'))
                <div class="form-card">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-bag-shopping text-primary me-2"></i>Order Type
                    </h5>
                    <div class="row g-3">
                        @if($restaurant->acceptsService('delivery'))
                            <div class="col-md-6">
                                <div class="payment-method selected" data-order-type="delivery" onclick="selectOrderType('delivery')">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="fas fa-motorcycle fa-2x text-primary"></i>
                                        <div>
                                            <div class="fw-semibold">Delivery</div>
                                            <small class="text-muted">Bring it to my address</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if($restaurant->acceptsService('takeaway'))
                            <div class="col-md-6">
                                <div class="payment-method{{ !$restaurant->acceptsService('delivery') ? ' selected' : '' }}" data-order-type="takeaway" onclick="selectOrderType('takeaway')">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="fas fa-store fa-2x text-success"></i>
                                        <div>
                                            <div class="fw-semibold">Takeaway</div>
                                            <small class="text-muted">I will pick it up</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="form-card" id="addressSummaryCard">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-map-marker-alt text-primary me-2"></i>Delivery Address
                </h5>
                <div class="address-summary-tile" onclick="openAddressModal()">
                    <i class="fas fa-location-dot"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold" id="addressSummaryTitle">Select delivery address</div>
                        <div class="text-muted small text-truncate" id="addressSummarySubtitle">Tap to choose or add an address</div>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </div>

            <div class="modal fade" id="addressModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content rounded-4 border-0">
                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold">Select delivery address</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
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
                                        <small id="mapHint" class="text-muted d-none">Tap or click on the map to place the pin and fetch the full address.</small>
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
                    </div>
                </div>
            </div>

            <div class="form-card" id="paymentMethodCard">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-credit-card text-primary me-2"></i>Payment Method
                </h5>
                <div class="row g-3">
                    @if($gatewayEnabled)
                        <div class="col-md-6">
                            <div class="payment-method selected" data-payment="card" onclick="selectPayment('card')">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-globe fa-2x text-primary"></i>
                                    <div>
                                        <div class="fw-semibold">Online Payment</div>
                                        <small class="text-muted">Checkout via {{ ucfirst($gatewayProvider) }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="col-md-6">
                        <div class="payment-method{{ !$gatewayEnabled ? ' selected' : '' }}" data-payment="cod" onclick="selectPayment('cod')">
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
                        <div class="payment-method" data-payment="wallet" onclick="selectPayment('wallet')">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fas fa-wallet fa-2x text-warning"></i>
                                <div>
                                    <div class="fw-semibold">Wallet</div>
                                    <small class="text-muted">Balance: {{ $currencySymbol }}{{ number_format($walletBalance, App\Models\AppSetting::currencyDecimals()) }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @if(!$gatewayEnabled)
                    <div class="alert alert-warning mt-3 mb-0">
                        Online payments are currently disabled by admin settings. You can still use Cash on Delivery or Wallet.
                    </div>
                @endif
            </div>

            <div class="form-card">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-tag text-primary me-2"></i>Apply Coupon
                </h5>
                <div class="input-group">
                    <input type="text" id="couponCode" class="form-control" placeholder="Enter coupon code">
                    <button class="btn btn-outline-primary" onclick="applyCoupon()">Apply</button>
                </div>
                @if(($availableCoupons ?? collect())->isNotEmpty())
                    <div class="mt-3 coupon-list">
                        @foreach($availableCoupons as $coupon)
                            <div class="coupon-chip" data-coupon="{{ $coupon->code }}" onclick="selectCoupon('{{ $coupon->code }}')">
                                <div class="coupon-discount-tag">
                                    <i class="fas fa-bolt"></i>
                                    @if($coupon->discount_type === 'percentage')
                                        {{ number_format((float) $coupon->discount_value, 0) }}% OFF
                                    @else
                                        {{ $currencySymbol }}{{ number_format((float) $coupon->discount_value, App\Models\AppSetting::currencyDecimals()) }} OFF
                                    @endif
                                </div>
                                <div class="fw-bold mt-2">{{ $coupon->title ?: 'Available offer' }}</div>
                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($coupon->description ?: 'Tap to apply this coupon on your bill.', 72) }}</div>
                                <div class="coupon-meta">
                                    @if((float) $coupon->min_order_amount > 0)
                                        <span><i class="fas fa-bag-shopping"></i> Min {{ $currencySymbol }}{{ number_format((float) $coupon->min_order_amount, App\Models\AppSetting::currencyDecimals()) }}</span>
                                    @endif
                                    @if($coupon->end_date)
                                        <span><i class="far fa-clock"></i> Ends {{ \Carbon\Carbon::parse($coupon->end_date)->format('M j') }}</span>
                                    @endif
                                </div>
                                <div class="coupon-code-orb">{{ $coupon->code }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
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
                    <span class="fw-bold">{{ $currencySymbol }} <span id="summarySubtotal">0</span></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Delivery Fee</span>
                    <span class="fw-bold">{{ $currencySymbol }} <span id="summaryDeliveryFee">0</span></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Platform Fee</span>
                    <span class="fw-bold">{{ $currencySymbol }} <span id="summaryPlatformFee">0</span></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span id="summaryTaxLabel">Taxes</span>
                    <span class="fw-bold">{{ $currencySymbol }} <span id="summaryTax">0</span></span>
                </div>
                <div class="d-flex justify-content-between mb-2 text-success" id="discountRow" style="display: none;">
                    <span>Coupon Discount</span>
                    <span class="fw-bold">-{{ $currencySymbol }} <span id="summaryDiscount">0</span></span>
                </div>
                <div class="order-summary-cta">
                    <div class="mobile-pay-row">
                        Pay via <strong id="mobilePayMethodLabel">{{ $gatewayEnabled ? 'Online ('.ucfirst($gatewayProvider).')' : 'Cash on Delivery' }}</strong>
                        <a href="javascript:void(0)" onclick="scrollToPaymentMethod()">Change</a>
                    </div>
                    <button class="checkout-place-btn" onclick="placeOrder()" id="placeOrderBtn">
                        <span class="cpb-total">
                            <span class="cpb-total-label">TOTAL</span>
                            <span class="cpb-total-amount">{{ $currencySymbol }} <span id="summaryTotal">0</span></span>
                        </span>
                        <span class="cpb-cta">Place order <i class="fas fa-arrow-right ms-1"></i></span>
                    </button>
                </div>
                <div class="text-center mt-3 d-none d-lg-block">
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
    @php
        $checkoutTaxes = App\Models\TaxSetting::getActiveTaxes();
        $checkoutTaxRate = $checkoutTaxes->sum('rate');
        $checkoutTaxLabel = $checkoutTaxes->count()
            ? $checkoutTaxes->pluck('name')->implode(', ') . ' (' . $checkoutTaxRate . '%)'
            : 'Taxes';
    @endphp
    const gatewayEnabled = @json($gatewayEnabled);
    const gatewayProvider = @json($gatewayProvider);
    const currencySymbol = '{{ $currencySymbol }}';
    const checkoutTaxRate = {{ $checkoutTaxRate ?: 0 }};
    const checkoutTaxLabel = @json($checkoutTaxLabel);
    let cart = [];

    let restaurantId = null;
    let selectedAddressId = null;
    let selectedOrderType = @json($restaurant->acceptsService('delivery') ? 'delivery' : 'takeaway');
    let selectedPayment = @json($gatewayEnabled ? 'card' : 'cod');
    let appliedCoupon = null;
    let locationConfirmed = false;
    let confirmMap;
    let confirmMarker;
    let savedAddresses = @auth {!! json_encode(Auth::user()->addresses->keyBy('id')) !!} @else {} @endauth;
    let autocomplete;
    let map;
    let marker;
    let addressGeocoder;

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
    } catch (e) {
        console.error('Error parsing cart:', e);
        localStorage.removeItem('checkout_cart');
        localStorage.removeItem('checkout_restaurant_id');
    }

    function initCheckoutGoogleMaps() {
        const input = document.getElementById('autocompleteInput');
        if (input && typeof google !== 'undefined') {
            autocomplete = new google.maps.places.Autocomplete(input, {
                types: ['address'],
                componentRestrictions: { country: 'in' }
            });
            autocomplete.addListener('place_changed', onPlaceChanged);
        }

        const mapContainer = document.getElementById('map');
        if (mapContainer && typeof google !== 'undefined') {
            addressGeocoder = new google.maps.Geocoder();
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
                setAddressLocation(event.latLng.lat(), event.latLng.lng(), true);
            });

            google.maps.event.addListener(map, 'click', function(event) {
                setAddressLocation(event.latLng.lat(), event.latLng.lng(), true);
            });
        }
    }

    // partials.frontend-header loads the Google Maps JS API once (shared
    // across every page) and calls window.initGlobalPlacesAutocomplete when
    // it's ready. If Maps has already finished loading by the time this
    // script runs (header's callback fired first), set up immediately;
    // otherwise hook into that same callback for when it does fire.
    if (typeof google !== 'undefined' && google.maps && google.maps.places) {
        initCheckoutGoogleMaps();
    } else {
        const existingGlobalPlacesCallback = window.initGlobalPlacesAutocomplete;
        window.initGlobalPlacesAutocomplete = function() {
            if (typeof existingGlobalPlacesCallback === 'function') existingGlobalPlacesCallback();
            initCheckoutGoogleMaps();
        };
    }

    function onPlaceChanged() {
        const place = autocomplete.getPlace();
        if (place && place.geometry) {
            const lat = place.geometry.location.lat();
            const lng = place.geometry.location.lng();

            setAddressLocation(lat, lng, false);

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

                setAddressLocation(lat, lng, true);
                detectBtn.innerHTML = originalText;
                detectBtn.disabled = false;
            }, function() {
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

    function ensureAddressMapVisible() {
        document.getElementById('mapContainer').style.display = 'block';
        document.getElementById('mapHint').classList.remove('d-none');
    }

    function setAddressLocation(lat, lng, shouldReverseGeocode) {
        document.getElementById('addressLat').value = lat;
        document.getElementById('addressLng').value = lng;
        ensureAddressMapVisible();

        if (map && marker) {
            const target = { lat: lat, lng: lng };
            map.setCenter(target);
            marker.setPosition(target);
        }

        if (shouldReverseGeocode) {
            reverseGeocode(lat, lng);
        }
    }

    function reverseGeocode(lat, lng) {
        if (!addressGeocoder) {
            return;
        }

        addressGeocoder.geocode({ location: { lat: lat, lng: lng } }, function(results, status) {
            if (status === 'OK' && results[0]) {
                const address = results[0].formatted_address;
                document.getElementById('addressLine').value = address;

                let city = '';
                let state = '';
                let pincode = '';
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

    function openAddressModal() {
        new bootstrap.Modal(document.getElementById('addressModal')).show();
    }

    function selectAddress(addressId) {
        selectedAddressId = addressId;
        if (selectedOrderType === 'takeaway') {
            selectOrderType('delivery');
        }
        locationConfirmed = false;
        document.querySelectorAll('.address-card').forEach(function(card) {
            card.classList.remove('selected');
        });
        var selectedCard = document.querySelector('.address-card[data-address-id="' + addressId + '"]');
        if (selectedCard) selectedCard.classList.add('selected');
        document.querySelector('input[name="address_id"][value="' + addressId + '"]').checked = true;

        if (selectedCard) {
            var titleEl = selectedCard.querySelector('.fw-semibold');
            var subtitleEl = selectedCard.querySelector('.text-muted.small');
            var summaryTitle = document.getElementById('addressSummaryTitle');
            var summarySubtitle = document.getElementById('addressSummarySubtitle');
            if (summaryTitle) summaryTitle.textContent = 'Deliver to: ' + (titleEl ? titleEl.textContent : 'Selected address');
            if (summarySubtitle && subtitleEl) summarySubtitle.textContent = subtitleEl.textContent;
        }
        var addressModalEl = document.getElementById('addressModal');
        var addressModalInstance = bootstrap.Modal.getInstance(addressModalEl);
        if (addressModalInstance) addressModalInstance.hide();

        refreshCheckoutSummary();
    }

    function selectOrderType(type) {
        selectedOrderType = type;
        document.querySelectorAll('[data-order-type]').forEach(function(card) {
            card.classList.toggle('selected', card.getAttribute('data-order-type') === type);
        });

        const addressSection = document.getElementById('addressSummaryCard');
        if (addressSection) {
            addressSection.style.display = type === 'takeaway' ? 'none' : '';
        }

        refreshCheckoutSummary();
    }

    function selectPayment(method) {
        if ((method === 'card' || method === 'upi') && !gatewayEnabled) {
            alert('Online payments are disabled by admin settings. Please choose Cash on Delivery or Wallet.');
            return;
        }
        selectedPayment = method;
        document.querySelectorAll('.payment-method[data-payment]').forEach(function(el) {
            el.classList.remove('selected');
        });
        document.querySelector('.payment-method[data-payment="' + method + '"]').classList.add('selected');

        var payLabel = document.getElementById('mobilePayMethodLabel');
        if (payLabel) {
            payLabel.textContent = method === 'card' ? 'Online (' + (gatewayProvider ? gatewayProvider.charAt(0).toUpperCase() + gatewayProvider.slice(1) : 'Gateway') + ')'
                : method === 'wallet' ? 'Wallet'
                : 'Cash on Delivery';
        }
    }

    function scrollToPaymentMethod() {
        var card = document.getElementById('paymentMethodCard');
        if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
        document.getElementById('addressLat').value = '';
        document.getElementById('addressLng').value = '';
        document.getElementById('mapContainer').style.display = 'block';
        document.getElementById('mapHint').classList.remove('d-none');

        if (map && marker) {
            setAddressLocation(28.6139, 77.2090, false);
            map.setZoom(15);
        }
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
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to save address');
            }
        })
        .catch(function() {
            alert('Failed to save address');
        });
    }

    function updatePricingSummary(summary) {
        const decimals = Math.max(2, Number(window.currencyDecimals) || 2);
        document.getElementById('summarySubtotal').innerText = Number(summary.subtotal || 0).toFixed(decimals);
        document.getElementById('summaryDeliveryFee').innerText = Number(summary.delivery_fee || 0).toFixed(decimals);
        document.getElementById('summaryPlatformFee').innerText = Number(summary.platform_fee || 0).toFixed(decimals);
        document.getElementById('summaryTax').innerText = Number(summary.tax || 0).toFixed(decimals);
        if (document.getElementById('summaryTaxLabel')) {
            document.getElementById('summaryTaxLabel').innerText = summary.tax_label || checkoutTaxLabel;
        }

        const discount = Number(summary.discount || 0);
        document.getElementById('summaryDiscount').innerText = discount.toFixed(decimals);
        document.getElementById('discountRow').style.display = discount > 0 ? 'flex' : 'none';
        document.getElementById('summaryTotal').innerText = Number(summary.total || 0).toFixed(decimals);
    }

    function refreshCheckoutSummary() {
        if (!restaurantId || cart.length === 0) {
            updatePricingSummary({
                subtotal: 0,
                delivery_fee: 0,
                platform_fee: 0,
                tax: 0,
                discount: 0,
                total: 0,
                tax_label: checkoutTaxLabel
            });
            return Promise.resolve();
        }

        return fetch('{{ route("checkout.summary") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                restaurant_id: restaurantId,
                order_type: selectedOrderType,
                delivery_address_id: selectedAddressId ? parseInt(selectedAddressId, 10) : null,
                coupon_code: appliedCoupon || null,
                items: cart.map(function(item) {
                    return {
                        id: item.id,
                        quantity: item.quantity,
                        selected_variant: item.selected_variant || null,
                        selected_add_ons: item.selected_add_ons || []
                    };
                })
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.data) {
                updatePricingSummary(data.data);
            } else if (data.message) {
                document.getElementById('couponMessage').innerHTML = '<span class="text-danger">' + data.message + '</span>';
            }
        })
        .catch(function() {
            console.error('Unable to refresh checkout summary');
        });
    }

    function loadCartSummary() {
        const cartContainer = document.getElementById('cartItemsSummary');
        if (cart.length === 0) {
            cartContainer.innerHTML = '<div class="text-center py-3 text-muted">No items in cart</div>';
            document.getElementById('placeOrderBtn').disabled = true;
        } else {
            cartContainer.innerHTML = cart.map(function(item) {
                const variantHtml = item.selected_variant
                    ? '<div class="small text-muted">' + escapeHtml(item.selected_variant.name) + '</div>'
                    : '';
                const extrasHtml = item.selected_add_ons && item.selected_add_ons.length
                    ? '<div class="small text-muted">Extras: ' + item.selected_add_ons.map(function(addon) {
                        return escapeHtml(addon.name);
                    }).join(', ') + '</div>'
                    : '';

                return '<div class="d-flex justify-content-between mb-2">' +
                    '<div>' +
                    '<span class="fw-semibold">' + item.quantity + 'x</span> ' + escapeHtml(item.name) +
                    variantHtml +
                    extrasHtml +
                    '</div>' +
                    '<span>' + currencySymbol + ' ' + (((item.price || 0) * (item.quantity || 0)).toFixed(Math.max(2, Number(window.currencyDecimals) || 2))) + '</span>' +
                    '</div>';
            }).join('');
            document.getElementById('placeOrderBtn').disabled = false;
        }

        return refreshCheckoutSummary();
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
                code: code,
                restaurant_id: restaurantId,
                subtotal: parseFloat(document.getElementById('summarySubtotal').innerText)
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                appliedCoupon = data.coupon_code;
                document.getElementById('couponMessage').innerHTML = '<span class="text-success">' + data.message + '</span>';
                return refreshCheckoutSummary();
            } else {
                document.getElementById('couponMessage').innerHTML = '<span class="text-danger">' + data.message + '</span>';
            }
        })
        .finally(function() {
            applyBtn.disabled = false;
            applyBtn.innerHTML = 'Apply';
        });
    }

    function selectCoupon(code) {
        document.getElementById('couponCode').value = code;
        document.querySelectorAll('.coupon-chip').forEach(function(chip) {
            chip.classList.toggle('selected', chip.dataset.coupon === code);
        });
        applyCoupon();
    }

    function placeOrder() {
        if (selectedOrderType === 'delivery' && !selectedAddressId) {
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
        if (selectedOrderType === 'delivery' && !locationConfirmed) {
            showLocationConfirm();
            return;
        }

        const orderData = {
            restaurant_id: restaurantId,
            items: cart.map(function(item) {
                return {
                    id: item.id,
                    quantity: item.quantity,
                    selected_variant: item.selected_variant || null,
                    selected_add_ons: item.selected_add_ons || []
                };
            }),
            order_type: selectedOrderType,
            delivery_address_id: selectedOrderType === 'delivery' ? parseInt(selectedAddressId, 10) : null,
            payment_method: selectedPayment,
            coupon_code: appliedCoupon || null
        };

        const placeBtn = document.getElementById('placeOrderBtn');
        setPlaceOrderLoading(true, 'Placing Order...');

        fetch('{{ route("checkout.process") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(orderData)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                if (data.requires_payment && data.payment) {
                    startOnlinePayment(data.order_id, data.payment);
                    return;
                }

                clearCheckoutStorage();
                alert(data.message || 'Order placed successfully!');
                window.location.href = data.redirect_url || ('/customer/orders/' + data.order_id + '/track');
            } else {
                alert(data.message || 'Failed to place order');
                setPlaceOrderLoading(false);
            }
        })
        .catch(function() {
            alert('Failed to place order. Please try again.');
            setPlaceOrderLoading(false);
        });
    }

    function startOnlinePayment(orderId, payment) {
        if (!payment || !payment.provider) {
            alert('Payment session could not be created.');
            setPlaceOrderLoading(false);
            return;
        }

        if (payment.redirect_url) {
            window.location.href = payment.redirect_url;
            return;
        }

        if (payment.provider === 'razorpay') {
            startRazorpayPayment(orderId, payment);
            return;
        }

        if (payment.provider === 'cashfree') {
            startCashfreePayment(orderId, payment);
            return;
        }

        alert('Unsupported payment gateway: ' + payment.provider);
        setPlaceOrderLoading(false);
    }

    function startRazorpayPayment(orderId, payment) {
        if (typeof Razorpay === 'undefined') {
            alert('Razorpay checkout is unavailable right now.');
            failCheckoutPayment(orderId, 'Razorpay checkout script failed to load.');
            return;
        }

        const options = {
            key: payment.key,
            amount: payment.amount,
            currency: payment.currency || '{{ App\Models\AppSetting::getValue('currency_code', 'INR') }}',
            name: payment.name || 'FoodFlow',
            description: payment.description || 'Order payment',
            order_id: payment.order_id,
            prefill: payment.prefill || {},
            theme: payment.theme || {},
            handler: function (response) {
                verifyCheckoutPayment(orderId, {
                    payment_method: 'razorpay',
                    payment_id: response.razorpay_payment_id,
                    razorpay_order_id: response.razorpay_order_id,
                    razorpay_signature: response.razorpay_signature
                });
            },
            modal: {
                ondismiss: function () {
                    failCheckoutPayment(orderId, 'Payment was cancelled before confirmation.');
                }
            }
        };

        const razorpay = new Razorpay(options);
        razorpay.on('payment.failed', function () {
            failCheckoutPayment(orderId, 'Razorpay payment failed.');
        });
        razorpay.open();
    }

    function startCashfreePayment(orderId, payment) {
        if (typeof Cashfree === 'undefined') {
            alert('Cashfree checkout is unavailable right now.');
            failCheckoutPayment(orderId, 'Cashfree checkout script failed to load.');
            return;
        }

        if (!payment.payment_session_id || !payment.order_id) {
            alert('Cashfree payment session is incomplete.');
            failCheckoutPayment(orderId, 'Cashfree payment session was incomplete.');
            return;
        }

        const cashfree = Cashfree({
            mode: payment.environment === 'sandbox' ? 'sandbox' : 'production'
        });

        cashfree.checkout({
            paymentSessionId: payment.payment_session_id,
            redirectTarget: '_modal'
        }).then(function(result) {
            // The SDK result shape varies between payment methods and versions.
            // Always ask our server/Cashfree for the authoritative status after
            // the modal resolves instead of relying on optional result fields.
            verifyCheckoutPayment(orderId, {
                payment_method: 'cashfree',
                payment_id: payment.order_id
            });
        }).catch(function() {
            // A modal/SDK error can race with a successful bank confirmation.
            // Server-side verification prevents a paid order being cancelled.
            verifyCheckoutPayment(orderId, {
                payment_method: 'cashfree',
                payment_id: payment.order_id
            });
        });
    }

    function verifyCheckoutPayment(orderId, payload, attempt) {
        attempt = attempt || 0;
        setPlaceOrderLoading(true, 'Verifying Payment...');
        fetch('{{ route("checkout.payment.verify") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(Object.assign({ order_id: orderId }, payload))
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                clearCheckoutStorage();
                window.location.href = data.redirect_url || ('/customer/orders/' + orderId + '/track');
                return;
            }

            const verificationMessage = String(data.message || '');
            const shouldRetryCashfree = verificationMessage.includes('not confirmed yet') ||
                verificationMessage.includes('Unable to verify Cashfree payment');
            if (payload.payment_method === 'cashfree' && shouldRetryCashfree && attempt < 3) {
                setTimeout(function() {
                    verifyCheckoutPayment(orderId, payload, attempt + 1);
                }, 1500);
                return;
            }

            alert(data.message || 'Payment verification failed.');
            setPlaceOrderLoading(false);
        })
        .catch(function() {
            alert('Payment verification failed. Please contact support if money was debited.');
            setPlaceOrderLoading(false);
        });
    }

    function failCheckoutPayment(orderId, message) {
        fetch('{{ route("checkout.payment.fail") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderId,
                message: message
            })
        }).finally(function() {
            alert(message || 'Payment failed or was cancelled.');
            setPlaceOrderLoading(false);
        });
    }

    function clearCheckoutStorage() {
        localStorage.removeItem('checkout_cart');
        localStorage.removeItem('checkout_restaurant_id');
        if (restaurantId) {
            localStorage.removeItem('cart_' + restaurantId);
        }
        if (window.updateHeaderCartCount) window.updateHeaderCartCount();
    }

    function setPlaceOrderLoading(isLoading, label) {
        const placeBtn = document.getElementById('placeOrderBtn');
        if (!placeBtn) return;
        placeBtn.disabled = isLoading;
        placeBtn.innerHTML = isLoading
            ? '<div class="loading-spinner me-2"></div> ' + (label || 'Processing...')
            : '<i class="fas fa-check-circle me-2"></i>Place Order';
    }

    function showLocationConfirm() {
        const address = savedAddresses[selectedAddressId] || {};
        const lat = parseFloat(address.latitude || address.lat || 28.6139);
        const lng = parseFloat(address.longitude || address.lng || 77.2090);
        new bootstrap.Modal(document.getElementById('locationConfirmModal')).show();
        setTimeout(function() {
            initializeConfirmMap(lat, lng);
        }, 300);
    }

    function initializeConfirmMap(lat, lng) {
        if (typeof google === 'undefined') return;
        const target = { lat: lat, lng: lng };
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
        navigator.geolocation.getCurrentPosition(function(position) {
            initializeConfirmMap(position.coords.latitude, position.coords.longitude);
        }, function() {
            alert('Unable to detect location.');
        });
    }

    function confirmLocationAndOrder() {
        locationConfirmed = true;
        const modal = bootstrap.Modal.getInstance(document.getElementById('locationConfirmModal'));
        if (modal) {
            modal.hide();
        }
        placeOrder();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    loadCartSummary();

    const firstAddress = document.querySelector('input[name="address_id"]');
    if (firstAddress) {
        firstAddress.checked = true;
        selectedAddressId = firstAddress.value;
        document.querySelector('.address-card[data-address-id="' + selectedAddressId + '"]').classList.add('selected');
        refreshCheckoutSummary();
    }
</script>
@include('partials.frontend-footer')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.web-visit-tracker', ['panel' => 'checkout'])
</body>
</html>



