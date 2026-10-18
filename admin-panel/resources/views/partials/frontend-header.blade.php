@php
    $appName = App\Models\AppSetting::getValue('app_name', config('app.name', 'Swado'));
    $appLogo = App\Models\AppSetting::getValue('app_logo', null);
    $appFavicon = App\Models\AppSetting::getValue('app_favicon', null);
    $headerBrandingType = App\Models\AppSetting::getValue('header_branding_type', 'text');
    $headerBrandingType = in_array($headerBrandingType, ['text', 'logo', 'logo_text']) ? $headerBrandingType : 'text';
    $primaryColor = App\Models\AppSetting::getValue('primary_color', '#FC8019');
    $partnerNavText = App\Models\AppSetting::getValue('partner_nav_text', 'Partner with Us');
    $googleMapsKey = $googleMapsApiKey ?? App\Models\AppSetting::getValue('google_maps_api_key', App\Models\AppSetting::getValue('google_maps_key', ''));

    // Real cart item count from session
    $cartCount = 0;
    try {
        $cart = session()->get('cart', []);
        if (is_array($cart)) {
            foreach ($cart as $item) {
                $cartCount += is_array($item) ? ($item['quantity'] ?? 1) : 1;
            }
        }
    } catch (\Throwable $e) {}

    // Real Delivery Zones from Database
    $realDeliveryZones = [];
    try {
        $areas = App\Models\DeliveryArea::where('is_active', true)->get();
        foreach ($areas as $area) {
            $restCount = App\Models\Restaurant::where('is_verified', true)
                ->where(function($q) use ($area) {
                    $q->where('city', 'like', "%{$area->name}%")
                      ->orWhere('address', 'like', "%{$area->name}%");
                })->count();

            $realDeliveryZones[] = (object) [
                'id' => $area->id,
                'name' => $area->name,
                'area_type' => $area->area_type ?: 'circle',
                'radius_km' => $area->radius_km,
                'latitude' => $area->latitude,
                'longitude' => $area->longitude,
                'count' => $restCount ?: 1,
            ];
        }

        if (empty($realDeliveryZones)) {
            $branchZones = App\Models\BranchZone::where('is_active', true)->get();
            foreach ($branchZones as $bz) {
                $realDeliveryZones[] = (object) [
                    'id' => $bz->id,
                    'name' => $bz->name,
                    'area_type' => 'branch_zone',
                    'radius_km' => 10,
                    'latitude' => null,
                    'longitude' => null,
                    'count' => 1,
                ];
            }
        }

        if (empty($realDeliveryZones)) {
            $resCities = App\Models\Restaurant::where('is_verified', true)
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->select('city as name', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('city')
                ->orderByDesc('count')
                ->get();
            foreach ($resCities as $idx => $c) {
                $realDeliveryZones[] = (object) [
                    'id' => $idx + 1,
                    'name' => $c->name . ' Delivery Zone',
                    'area_type' => 'operational_zone',
                    'radius_km' => 15,
                    'latitude' => null,
                    'longitude' => null,
                    'count' => (int) $c->count,
                ];
            }
        }

        if (empty($realDeliveryZones)) {
            $bizCity = App\Models\AppSetting::getValue('business_city', 'Central Zone');
            $realDeliveryZones[] = (object) [
                'id' => 1,
                'name' => $bizCity . ' Delivery Zone',
                'area_type' => 'operational_zone',
                'radius_km' => 15,
                'latitude' => null,
                'longitude' => null,
                'count' => 1,
            ];
        }
    } catch (\Throwable $e) {}
@endphp

<!-- PWA Manifest & App Capability Meta Tags -->
<link rel="manifest" href="/manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $appName }}">
<meta name="theme-color" content="{{ $primaryColor }}">
@if($appFavicon)
    <link rel="apple-touch-icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appFavicon) }}">
@endif

<!-- Google Maps JS API with Places Library -->
@if(!empty($googleMapsKey))
    <script>
        window.initGlobalPlacesAutocomplete = function() {
            if (typeof initSwiggyDrawerAutocomplete === 'function') {
                initSwiggyDrawerAutocomplete();
            }
        };
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsKey }}&libraries=places,geometry&callback=initGlobalPlacesAutocomplete&loading=async" async defer></script>
@endif

<style>
    :root {
        --swiggy-orange: {{ $primaryColor }};
        --swiggy-orange-light: #fff5ed;
        --swiggy-text-dark: #02060c;
        --swiggy-text-muted: #686b78;
        --swiggy-border: #e8e8e8;
        --swiggy-green: #1ba672;
    }

    .swiggy-navbar {
        position: sticky;
        top: 0;
        left: 0;
        right: 0;
        width: 100%;
        height: 80px;
        background: #ffffff;
        border-bottom: 1px solid rgba(2, 6, 12, 0.08);
        box-shadow: 0 15px 40px -20px rgba(40, 44, 63, 0.12);
        z-index: 1040;
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }

    .swiggy-nav-container {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Left: Logo & Location */
    .swiggy-left-group {
        display: flex;
        align-items: center;
        gap: 28px;
    }

    .swiggy-logo-link {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        transition: transform 0.15s ease;
    }

    .swiggy-logo-link:hover {
        transform: scale(1.02);
    }

    .swiggy-logo-img {
        height: 48px;
        width: auto;
        max-width: 150px;
        object-fit: contain;
    }

    .swiggy-logo-text {
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -0.8px;
        color: var(--swiggy-orange);
        margin: 0;
    }

    /* Location Trigger */
    .swiggy-loc-trigger {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        padding: 6px 10px;
        border-radius: 8px;
        transition: background 0.15s ease;
        max-width: 420px;
    }

    .swiggy-loc-trigger:hover {
        background: rgba(2, 6, 12, 0.04);
    }

    .swiggy-loc-area {
        font-size: 14px;
        font-weight: 700;
        color: var(--swiggy-text-dark);
        border-bottom: 2px solid var(--swiggy-text-dark);
        padding-bottom: 1px;
        white-space: nowrap;
        transition: border-color 0.2s, color 0.2s;
    }

    .swiggy-loc-trigger:hover .swiggy-loc-area {
        color: var(--swiggy-orange);
        border-bottom-color: var(--swiggy-orange);
    }

    .swiggy-loc-address {
        font-size: 13px;
        font-weight: 400;
        color: var(--swiggy-text-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .swiggy-loc-arrow {
        color: var(--swiggy-orange);
        font-size: 12px;
        transition: transform 0.2s ease;
    }

    /* Right: Navigation Items */
    .swiggy-nav-right {
        display: flex;
        align-items: center;
        gap: 36px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .swiggy-nav-item {
        display: inline-flex;
        align-items: center;
    }

    .swiggy-nav-link {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
        color: #3d4152;
        text-decoration: none;
        transition: color 0.15s ease;
        cursor: pointer;
        background: transparent;
        border: none;
        padding: 0;
    }

    .swiggy-nav-link:hover {
        color: var(--swiggy-orange);
    }

    .swiggy-nav-link i {
        font-size: 18px;
        color: #686b78;
        transition: color 0.15s ease;
    }

    .swiggy-nav-link:hover i {
        color: var(--swiggy-orange);
    }

    /* Cart Badge */
    .swiggy-cart-badge {
        background: var(--swiggy-orange);
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 9999px;
        min-width: 20px;
        height: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-left: 2px;
    }

    .swiggy-badge-new {
        background: #fdf0e6;
        color: var(--swiggy-orange);
        font-size: 10px;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Slide-In Location Drawer */
    .swiggy-drawer-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(40, 44, 63, 0.6);
        z-index: 1055;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s cubic-bezier(0.2, 0, 0, 1), visibility 0.3s;
        backdrop-filter: blur(2px);
    }

    .swiggy-drawer-backdrop.active {
        opacity: 1;
        visibility: visible;
    }

    .swiggy-drawer {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 100%;
        max-width: 480px;
        background: #ffffff;
        z-index: 1060;
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.2, 0, 0, 1);
        display: flex;
        flex-direction: column;
        box-shadow: 10px 0 40px rgba(40, 44, 63, 0.2);
    }

    .swiggy-drawer.active {
        transform: translateX(0);
    }

    .swiggy-drawer-header {
        padding: 24px 28px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .swiggy-drawer-title {
        font-size: 18px;
        font-weight: 800;
        color: var(--swiggy-text-dark);
        margin: 0;
    }

    .swiggy-drawer-close {
        background: none;
        border: none;
        font-size: 20px;
        color: #02060c;
        cursor: pointer;
        padding: 6px;
        border-radius: 50%;
        transition: background 0.15s;
    }

    .swiggy-drawer-close:hover {
        background: #f0f0f5;
    }

    .swiggy-drawer-content {
        padding: 0 28px 32px;
        overflow-y: auto;
        flex: 1;
    }

    .swiggy-drawer-searchbox {
        position: relative;
        margin-bottom: 20px;
    }

    .swiggy-drawer-searchbox input {
        width: 100%;
        height: 52px;
        padding: 0 46px 0 18px;
        font-size: 15px;
        font-weight: 600;
        border: 1px solid #d4d5d9;
        border-radius: 12px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-shadow: 0 2px 8px rgba(40, 44, 63, 0.05);
    }

    .swiggy-drawer-searchbox input:focus {
        border-color: var(--swiggy-orange);
        box-shadow: 0 4px 14px rgba(252, 128, 25, 0.15);
    }

    .swiggy-drawer-searchbox i.search-icon {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #93959f;
        font-size: 16px;
    }

    /* Live Suggestions dropdown inside drawer */
    .swiggy-live-suggestions {
        background: #ffffff;
        border: 1px solid #e8e8e8;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        margin-top: -12px;
        margin-bottom: 20px;
        overflow: hidden;
        max-height: 280px;
        overflow-y: auto;
    }

    .swiggy-suggestion-item {
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f5;
        transition: background 0.15s ease;
    }

    .swiggy-suggestion-item:last-child {
        border-bottom: none;
    }

    .swiggy-suggestion-item:hover {
        background: #fff8f5;
    }

    .swiggy-suggestion-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #f0f0f5;
        color: var(--swiggy-orange);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    .swiggy-gps-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px 18px;
        border: 1px solid #d4d5d9;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-bottom: 24px;
        background: #ffffff;
    }

    .swiggy-gps-card:hover {
        border-color: var(--swiggy-orange);
        background: var(--swiggy-orange-light);
    }

    .swiggy-gps-icon {
        font-size: 20px;
        color: var(--swiggy-orange);
    }

    .swiggy-gps-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--swiggy-text-dark);
        margin-bottom: 2px;
    }

    .swiggy-gps-subtitle {
        font-size: 13px;
        color: var(--swiggy-text-muted);
    }

    .swiggy-drawer-zones-title {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #7e808c;
        margin-bottom: 14px;
    }

    .swiggy-drawer-zone-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease;
        border: 1px solid transparent;
    }

    .swiggy-drawer-zone-item:hover {
        background: #f8f8f9;
        border-color: #e8e8e8;
    }

    .swiggy-zone-icon-box {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #fff5ed;
        color: var(--swiggy-orange);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    .swiggy-drawer-zone-name {
        font-size: 15px;
        font-weight: 700;
        color: var(--swiggy-text-dark);
    }

    .swiggy-drawer-zone-info {
        font-size: 12.5px;
        color: var(--swiggy-text-muted);
    }

    /* Google Places Autocomplete Fix for Modal/Drawer */
    .pac-container {
        z-index: 999999 !important;
        position: absolute !important;
        border-radius: 12px !important;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.18) !important;
        border: 1px solid #e8e8e8 !important;
        font-family: inherit !important;
        margin-top: 6px !important;
    }

    .pac-item {
        padding: 11px 16px !important;
        cursor: pointer !important;
        font-size: 14px !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
    }

    .pac-item:hover {
        background-color: #fff8f5 !important;
    }

    .pac-item-query {
        font-size: 14.5px !important;
        font-weight: 700 !important;
        color: #02060c !important;
    }

    /* Floating pill nav matching the Flutter app's _HomeBottomNavBar
       (home_screen_production.dart:14904-14944). */
    .swiggy-pwa-bottom-bar {
        position: fixed;
        bottom: 14px;
        left: 14px;
        right: 14px;
        height: 74px;
        background: #ffffff;
        border-radius: 28px;
        display: flex;
        align-items: center;
        justify-content: space-around;
        z-index: 1045;
        margin-bottom: env(safe-area-inset-bottom, 0px);
        box-shadow:
            0 8px 24px rgba(109, 40, 217, 0.08),
            0 4px 12px rgba(0, 0, 0, 0.12);
    }

    .swiggy-pwa-tab {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #6B7280;
        font-size: 10.5px;
        font-weight: 600;
        gap: 4px;
        flex: 1;
        height: 56px;
        margin: 0 4px;
        border-radius: 18px;
        transition: all 0.15s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .swiggy-pwa-tab i {
        font-size: 18px;
        transition: transform 0.15s ease;
    }

    .swiggy-pwa-tab.active {
        color: var(--primary);
        background: #F1E7FF;
    }

    .swiggy-pwa-tab:hover {
        color: var(--primary);
    }

    .swiggy-pwa-tab.active i {
        transform: scale(1.1);
    }

    @media (max-width: 991.98px) {
        body {
            padding-bottom: calc(102px + env(safe-area-inset-bottom, 0px));
        }
    }

    /* PWA Install Banner */
    .swiggy-pwa-install-banner {
        position: fixed;
        bottom: 100px;
        left: 12px;
        right: 12px;
        max-width: 440px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        border: 1px solid #e8e8e8;
        padding: 12px 16px;
        z-index: 1044;
        animation: pwaSlideUp 0.3s ease;
    }

    @keyframes pwaSlideUp {
        from { transform: translateY(100px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .swiggy-pwa-install-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--swiggy-orange-light);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--swiggy-orange);
        font-size: 18px;
        overflow: hidden;
    }

    .swiggy-pwa-install-icon img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    @media (max-width: 768px) {
        body {
            padding-bottom: 74px !important;
        }
        .swiggy-navbar {
            height: 68px;
        }
        .swiggy-logo-img {
            height: 38px;
        }
        .swiggy-loc-trigger {
            max-width: 180px;
        }
        .swiggy-loc-address {
            display: none;
        }
    }
</style>

<header class="swiggy-navbar">
    <div class="container swiggy-nav-container">
        <!-- Left: Logo & Delivery Zone Trigger -->
        <div class="swiggy-left-group">
            <a href="{{ route('home') }}" class="swiggy-logo-link" title="{{ $appName }}">
                @if($appLogo && in_array($headerBrandingType, ['logo', 'logo_text']))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) }}" alt="{{ $appName }}" class="swiggy-logo-img">
                @endif
                @if(!$appLogo || in_array($headerBrandingType, ['text', 'logo_text']))
                    <span class="swiggy-logo-text">{{ $appName }}</span>
                @endif
            </a>

            <!-- Delivery Zone Trigger -->
            <div class="swiggy-loc-trigger" onclick="openSwiggyLocationDrawer()" role="button" tabindex="0" title="Select Delivery Zone">
                <i class="fas fa-map-marked-alt text-primary" style="font-size: 16px;"></i>
                <span class="swiggy-loc-area" id="unifiedNavLocationArea">Select Zone</span>
                <span class="swiggy-loc-address" id="unifiedNavLocationAddress">Choose active delivery area</span>
                <i class="fas fa-chevron-down swiggy-loc-arrow"></i>
            </div>
        </div>

        <!-- Right: Desktop Navigation -->
        <ul class="swiggy-nav-right d-none d-lg-flex">
            <!-- Search -->
            <li class="swiggy-nav-item">
                <a href="{{ route('home') }}#restaurantsSection" class="swiggy-nav-link" onclick="focusMainSearch(event)">
                    <i class="fas fa-search"></i>
                    <span>Search</span>
                </a>
            </li>

            <!-- Offers / Delivery Zones -->
            <li class="swiggy-nav-item">
                <a href="javascript:void(0)" onclick="openSwiggyLocationDrawer()" class="swiggy-nav-link">
                    <i class="fas fa-percent"></i>
                    <span>Offers & Zones</span>
                    <span class="swiggy-badge-new">NEW</span>
                </a>
            </li>

            <!-- Help -->
            <li class="swiggy-nav-item">
                <a href="{{ Route::has('help') ? route('help') : url('/help') }}" class="swiggy-nav-link">
                    <i class="far fa-question-circle"></i>
                    <span>Help</span>
                </a>
            </li>

            <!-- User Auth Profile -->
            <li class="swiggy-nav-item">
                @auth
                    <div class="dropdown">
                        <button class="swiggy-nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="far fa-user"></i>
                            <span>{{ Str::limit(Auth::user()->name, 12) }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 py-2 mt-2">
                            <li><h6 class="dropdown-header text-truncate">{{ Auth::user()->email }}</h6></li>
                            <li><a class="dropdown-item py-2 fw-semibold" href="{{ route('customer.orders.index') }}"><i class="fas fa-box-open text-muted me-2"></i>Orders</a></li>
                            <li><a class="dropdown-item py-2 fw-semibold" href="{{ route('customer.addresses.index') }}"><i class="fas fa-map-marker-alt text-muted me-2"></i>Addresses</a></li>
                            <li><a class="dropdown-item py-2 fw-semibold" href="{{ route('profile.show') }}"><i class="fas fa-cog text-muted me-2"></i>Profile Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger py-2 fw-semibold"><i class="fas fa-sign-out-alt me-2"></i>Log Out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="swiggy-nav-link">
                        <i class="far fa-user"></i>
                        <span>Sign In</span>
                    </a>
                @endauth
            </li>

            <!-- Cart -->
            <li class="swiggy-nav-item">
                <a href="{{ route('checkout.index') }}" class="swiggy-nav-link">
                    <i class="fas fa-shopping-bag" style="color: var(--swiggy-orange);"></i>
                    <span>Cart</span>
                    <span class="swiggy-cart-badge" id="navCartCount">{{ $cartCount }}</span>
                </a>
            </li>
        </ul>

        <!-- Mobile Header Icons -->
        <div class="d-flex align-items-center gap-3 d-lg-none">
            <a href="{{ route('checkout.index') }}" class="text-dark position-relative text-decoration-none">
                <i class="fas fa-shopping-bag" style="font-size: 20px; color: var(--swiggy-orange);"></i>
                <span class="swiggy-cart-badge position-absolute top-0 start-100 translate-middle" id="navCartCountMobile" style="font-size: 10px; min-width: 18px; height: 18px;">{{ $cartCount }}</span>
            </a>
            <button class="swiggy-drawer-close" type="button" onclick="openSwiggyLocationDrawer()" title="Delivery Zones">
                <i class="fas fa-map-marked-alt text-primary" style="font-size: 20px;"></i>
            </button>
        </div>
    </div>
</header>

<!-- Slide-Out Location & Delivery Zone Drawer -->
<div class="swiggy-drawer-backdrop" id="swiggyDrawerBackdrop" onclick="closeSwiggyLocationDrawer()"></div>
<div class="swiggy-drawer" id="swiggyDrawer" aria-hidden="true">
    <div class="swiggy-drawer-header">
        <h3 class="swiggy-drawer-title">Choose Delivery Zone</h3>
        <button type="button" class="swiggy-drawer-close" onclick="closeSwiggyLocationDrawer()" title="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="swiggy-drawer-content">
        <!-- Search Input -->
        <div class="swiggy-drawer-searchbox">
            <input type="text" id="swiggyDrawerLocationInput" placeholder="Search zone, area, or street name..." autocomplete="off">
            <i class="fas fa-search search-icon"></i>
        </div>

        <!-- Live In-Drawer Suggestions -->
        <div id="swiggyDrawerLiveSuggestions" class="swiggy-live-suggestions" style="display: none;"></div>

        <!-- Current Location GPS Option -->
        <div class="swiggy-gps-card" onclick="detectLocationFromSwiggyDrawer()">
            <i class="fas fa-crosshairs swiggy-gps-icon"></i>
            <div>
                <div class="swiggy-gps-title" id="swiggyGpsText">Detect current location</div>
                <div class="swiggy-gps-subtitle">Find delivery zones servicing your area</div>
            </div>
        </div>

        <!-- Real Delivery Zones from Database -->
        @if(!empty($realDeliveryZones) && count($realDeliveryZones) > 0)
            <div class="swiggy-drawer-zones-title">Active Delivery Zones</div>
            <div class="d-flex flex-column gap-2" id="swiggyDrawerZonesList">
                @foreach($realDeliveryZones as $zoneObj)
                    <div class="swiggy-drawer-zone-item" onclick="selectDrawerDeliveryZone('{{ e($zoneObj->name) }}', {{ $zoneObj->latitude ?? 'null' }}, {{ $zoneObj->longitude ?? 'null' }}, {{ $zoneObj->id ?? 'null' }})">
                        <div class="swiggy-zone-icon-box">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="swiggy-drawer-zone-name">{{ $zoneObj->name }}</div>
                                <span class="badge rounded-pill bg-success-subtle text-success fw-bold" style="font-size: 10px;">ACTIVE ZONE</span>
                            </div>
                            <div class="swiggy-drawer-zone-info">
                                @if(!empty($zoneObj->radius_km))
                                    Servicing {{ $zoneObj->radius_km }}km delivery radius
                                @else
                                    Servicing {{ $zoneObj->count ?? 1 }} {{ Str::plural('restaurant', $zoneObj->count ?? 1) }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<!-- PWA Mobile Bottom Navigation Bar -- matches the Flutter app's 4-tab
     floating pill nav (_HomeBottomNavBar, home_screen_production.dart:14904-15043).
     Cart stays visible via the cart icon already in the mobile header above. -->
<nav class="swiggy-pwa-bottom-bar d-lg-none" aria-label="Mobile App Navigation">
    <a href="{{ route('home') }}" class="swiggy-pwa-tab {{ request()->routeIs('home') ? 'active' : '' }}">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="javascript:void(0)" onclick="triggerPwaSearch()" class="swiggy-pwa-tab">
        <i class="fas fa-search"></i>
        <span>Search</span>
    </a>
    <a href="{{ route('customer.orders.index') }}" class="swiggy-pwa-tab {{ request()->routeIs('customer.orders.*') ? 'active' : '' }}">
        <i class="fas fa-receipt"></i>
        <span>Orders</span>
    </a>
    <a href="{{ auth()->check() ? route('profile.show') : route('login') }}" class="swiggy-pwa-tab {{ request()->routeIs('profile.show') ? 'active' : '' }}">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
</nav>

<!-- PWA Install Prompt Banner for Mobile Devices -->
<div id="swiggyPwaInstallBanner" class="swiggy-pwa-install-banner" style="display: none;">
    <div class="d-flex align-items-center gap-3">
        <div class="swiggy-pwa-install-icon">
            @if($appLogo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) }}" alt="{{ $appName }}">
            @else
                <i class="fas fa-utensils"></i>
            @endif
        </div>
        <div class="flex-grow-1">
            <div class="fw-bold text-dark" style="font-size: 13px;">Install {{ $appName }} App</div>
            <div class="text-muted" style="font-size: 11.5px;">Faster delivery & instant tracking</div>
        </div>
        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" id="swiggyPwaInstallBtn">Install</button>
        <button type="button" class="btn-close ms-1" style="font-size: 10px;" onclick="dismissPwaInstallBanner()"></button>
    </div>
</div>

<script>
    // Global delivery zones data for client-side search
    window.allDeliveryZones = @json($realDeliveryZones);

    let swiggyDrawerAutocomplete = null;
    let deferredPwaPrompt = null;

    function openSwiggyLocationDrawer() {
        const backdrop = document.getElementById('swiggyDrawerBackdrop');
        const drawer = document.getElementById('swiggyDrawer');
        if (backdrop) backdrop.classList.add('active');
        if (drawer) {
            drawer.classList.add('active');
            drawer.setAttribute('aria-hidden', 'false');
            setTimeout(() => {
                const inp = document.getElementById('swiggyDrawerLocationInput');
                if (inp) {
                    inp.focus();
                    if (!swiggyDrawerAutocomplete && typeof google !== 'undefined' && google.maps && google.maps.places) {
                        initSwiggyDrawerAutocomplete();
                    }
                }
            }, 250);
        }
    }

    function closeSwiggyLocationDrawer() {
        const backdrop = document.getElementById('swiggyDrawerBackdrop');
        const drawer = document.getElementById('swiggyDrawer');
        if (backdrop) backdrop.classList.remove('active');
        if (drawer) {
            drawer.classList.remove('active');
            drawer.setAttribute('aria-hidden', 'true');
        }
        const sug = document.getElementById('swiggyDrawerLiveSuggestions');
        if (sug) sug.style.display = 'none';
    }

    // Google Maps Places Autocomplete setup (no restrictive (cities) filter)
    function initSwiggyDrawerAutocomplete() {
        const drawerInput = document.getElementById('swiggyDrawerLocationInput');
        if (!drawerInput) return;

        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            try {
                swiggyDrawerAutocomplete = new google.maps.places.Autocomplete(drawerInput, {
                    fields: ['formatted_address', 'geometry', 'name', 'address_components'],
                });

                swiggyDrawerAutocomplete.addListener('place_changed', function() {
                    const place = swiggyDrawerAutocomplete.getPlace();
                    if (place && place.geometry) {
                        const lat = place.geometry.location.lat();
                        const lng = place.geometry.location.lng();
                        const address = place.formatted_address || place.name;
                        selectDrawerDeliveryZone(address, lat, lng);
                    }
                });
            } catch(e) {
                console.warn('Google Places Autocomplete error:', e);
            }
        }

        // Live input search combining database Delivery Zones + suggestions
        drawerInput.addEventListener('input', function(e) {
            const query = e.target.value.trim().toLowerCase();
            handleLiveLocationSearch(query);
        });
    }

    // Live search handler for delivery zones & location
    function handleLiveLocationSearch(query) {
        const sugBox = document.getElementById('swiggyDrawerLiveSuggestions');
        if (!sugBox) return;

        if (!query || query.length < 2) {
            sugBox.innerHTML = '';
            sugBox.style.display = 'none';
            return;
        }

        let html = '';

        // 1. Search Real Delivery Zones in memory
        if (window.allDeliveryZones && Array.isArray(window.allDeliveryZones)) {
            const matchingZones = window.allDeliveryZones.filter(z =>
                z.name.toLowerCase().includes(query)
            );

            matchingZones.forEach(z => {
                html += `
                    <div class="swiggy-suggestion-item" onclick="selectDrawerDeliveryZone('${escapeAttr(z.name)}', ${z.latitude || 'null'}, ${z.longitude || 'null'}, ${z.id || 'null'})">
                        <div class="swiggy-suggestion-icon"><i class="fas fa-map-marked-alt"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark d-flex align-items-center justify-content-between" style="font-size:14px;">
                                <span>${escapeHtml(z.name)}</span>
                                <span class="badge bg-success-subtle text-success" style="font-size:9px;">ZONE</span>
                            </div>
                            <small class="text-muted">${z.radius_km ? z.radius_km + 'km delivery radius' : 'Active delivery service'}</small>
                        </div>
                    </div>
                `;
            });
        }

        // 2. Google AutocompleteService Predictions (if available)
        if (typeof google !== 'undefined' && google.maps && google.maps.places && google.maps.places.AutocompleteService) {
            try {
                const service = new google.maps.places.AutocompleteService();
                service.getPlacePredictions({ input: query }, (predictions, status) => {
                    if (status === google.maps.places.PlacesServiceStatus.OK && predictions) {
                        predictions.slice(0, 4).forEach(p => {
                            html += `
                                <div class="swiggy-suggestion-item" onclick="selectPredictionAddress('${escapeAttr(p.description)}')">
                                    <div class="swiggy-suggestion-icon"><i class="fas fa-map-pin"></i></div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark" style="font-size:14px;">${escapeHtml(p.structured_formatting ? p.structured_formatting.main_text : p.description)}</div>
                                        <small class="text-muted">${escapeHtml(p.structured_formatting ? p.structured_formatting.secondary_text : '')}</small>
                                    </div>
                                </div>
                            `;
                        });
                        sugBox.innerHTML = html;
                        sugBox.style.display = html ? 'block' : 'none';
                    }
                });
            } catch(e) {}
        } else {
            // Fallback: Geocode query via OpenStreetMap Nominatim
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=in&limit=4`)
                .then(r => r.json())
                .then(results => {
                    if (Array.isArray(results) && results.length > 0) {
                        results.forEach(res => {
                            html += `
                                <div class="swiggy-suggestion-item" onclick="selectDrawerDeliveryZone('${escapeAttr(res.display_name)}', ${res.lat}, ${res.lon})">
                                    <div class="swiggy-suggestion-icon"><i class="fas fa-map-pin"></i></div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark" style="font-size:13.5px;">${escapeHtml(res.name || res.display_name.split(',')[0])}</div>
                                        <small class="text-muted text-truncate d-block" style="max-width:320px;">${escapeHtml(res.display_name)}</small>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    sugBox.innerHTML = html || '<div class="p-3 text-muted text-center small">No matching delivery zones found.</div>';
                    sugBox.style.display = 'block';
                })
                .catch(() => {
                    if (html) {
                        sugBox.innerHTML = html;
                        sugBox.style.display = 'block';
                    }
                });
        }

        if (html) {
            sugBox.innerHTML = html;
            sugBox.style.display = 'block';
        }
    }

    function selectPredictionAddress(address) {
        if (typeof google !== 'undefined' && google.maps && google.maps.Geocoder) {
            new google.maps.Geocoder().geocode({ address: address }, (results, status) => {
                if (status === 'OK' && results && results[0]) {
                    const loc = results[0].geometry.location;
                    selectDrawerDeliveryZone(results[0].formatted_address || address, loc.lat(), loc.lng());
                } else {
                    selectDrawerDeliveryZone(address);
                }
            });
        } else {
            selectDrawerDeliveryZone(address);
        }
    }

    // Select delivery zone
    function selectDrawerDeliveryZone(zoneName, lat = null, lng = null, zoneId = null) {
        saveSwiggyDeliveryZone(lat, lng, zoneName, zoneId);
        closeSwiggyLocationDrawer();
        if (window.onSwiggyZoneChanged) {
            window.onSwiggyZoneChanged(zoneName, lat, lng, zoneId);
        } else if (window.onSwiggyLocationChanged) {
            window.onSwiggyLocationChanged(zoneName, lat, lng);
        } else {
            if (window.location.pathname !== '/' && !window.location.pathname.endsWith('home')) {
                window.location.href = '/?zone=' + encodeURIComponent(zoneName);
            }
        }
    }

    // Legacy alias
    function selectDrawerQuickCity(cityName, lat = null, lng = null) {
        selectDrawerDeliveryZone(cityName, lat, lng);
    }

    function detectLocationFromSwiggyDrawer() {
        const gpsText = document.getElementById('swiggyGpsText');
        const originalText = gpsText ? gpsText.innerHTML : 'Detect current location';
        if (gpsText) gpsText.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Detecting zone...';

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    let detectedZone = 'My Delivery Zone';

                    try {
                        const googleKey = '{{ $googleMapsKey }}';
                        if (googleKey) {
                            const res = await fetch(`https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${googleKey}`);
                            const data = await res.json();
                            if (data.results && data.results[0]) {
                                detectedZone = data.results[0].formatted_address;
                            }
                        } else {
                            // Fallback reverse geocode via OSM
                            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
                            const data = await res.json();
                            if (data && data.display_name) {
                                detectedZone = data.display_name;
                            }
                        }
                    } catch(e) {
                        console.warn(e);
                    }

                    if (gpsText) gpsText.innerHTML = originalText;
                    selectDrawerDeliveryZone(detectedZone, lat, lng);
                },
                (error) => {
                    if (gpsText) gpsText.innerHTML = originalText;
                    alert('Unable to detect location automatically. Please pick your delivery zone from the list.');
                },
                { timeout: 10000 }
            );
        } else {
            if (gpsText) gpsText.innerHTML = originalText;
            alert('Geolocation is not supported by your browser.');
        }
    }

    function saveSwiggyDeliveryZone(lat, lng, zoneName, zoneId = null) {
        if (!zoneName) return;
        localStorage.setItem('userDeliveryZone', JSON.stringify({ lat, lng, zone: zoneName, zone_id: zoneId }));
        localStorage.setItem('userLocation', JSON.stringify({ lat, lng, location: zoneName }));
        updateSwiggyNavZoneDisplay(zoneName);
    }

    function updateSwiggyNavZoneDisplay(zoneStr) {
        if (!zoneStr) return;
        const parts = zoneStr.split(',');
        const area = parts[0].trim();
        const address = parts.slice(1).join(',').trim() || zoneStr;

        const navArea = document.getElementById('unifiedNavLocationArea');
        const navAddress = document.getElementById('unifiedNavLocationAddress');

        if (navArea) navArea.textContent = area;
        if (navAddress) navAddress.textContent = address;
    }

    function focusMainSearch(e) {
        const inp = document.getElementById('swiggyLiveSearch');
        if (inp) {
            inp.focus();
            inp.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function triggerPwaSearch() {
        const mainSearch = document.getElementById('swiggyLiveSearch');
        if (mainSearch) {
            mainSearch.focus();
            mainSearch.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            openSwiggyLocationDrawer();
        }
    }

    // PWA Install prompt banner logic
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPwaPrompt = e;
        const banner = document.getElementById('swiggyPwaInstallBanner');
        if (banner && !sessionStorage.getItem('pwaPromptDismissed')) {
            banner.style.display = 'block';
        }
    });

    // The cart lives client-side in localStorage (cart_{restaurantId} keys, one
    // per restaurant) -- the server-rendered $cartCount above is only a
    // best-effort initial value from the session cart, which this app doesn't
    // actually use. This keeps the header badges in sync with the real cart.
    window.updateHeaderCartCount = function() {
        let total = 0;
        try {
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.indexOf('cart_') === 0) {
                    const items = JSON.parse(localStorage.getItem(key) || '[]');
                    if (Array.isArray(items)) {
                        items.forEach(item => { total += item.quantity || 1; });
                    }
                }
            }
        } catch (e) {}
        const desktopBadge = document.getElementById('navCartCount');
        const mobileBadge = document.getElementById('navCartCountMobile');
        if (desktopBadge) desktopBadge.textContent = total;
        if (mobileBadge) mobileBadge.textContent = total;
    };
    window.addEventListener('storage', (e) => {
        if (e.key && e.key.indexOf('cart_') === 0) window.updateHeaderCartCount();
    });

    document.addEventListener('DOMContentLoaded', () => {
        window.updateHeaderCartCount();
        const installBtn = document.getElementById('swiggyPwaInstallBtn');
        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (deferredPwaPrompt) {
                    deferredPwaPrompt.prompt();
                    const { outcome } = await deferredPwaPrompt.userChoice;
                    deferredPwaPrompt = null;
                    dismissPwaInstallBanner();
                }
            });
        }

        try {
            const saved = localStorage.getItem('userDeliveryZone') || localStorage.getItem('userLocation');
            if (saved) {
                const data = JSON.parse(saved);
                if (data && (data.zone || data.location)) {
                    updateSwiggyNavZoneDisplay(data.zone || data.location);
                }
            }
        } catch(e) {}

        initSwiggyDrawerAutocomplete();
    });

    function dismissPwaInstallBanner() {
        const banner = document.getElementById('swiggyPwaInstallBanner');
        if (banner) banner.style.display = 'none';
        sessionStorage.setItem('pwaPromptDismissed', 'true');
    }

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
</script>
