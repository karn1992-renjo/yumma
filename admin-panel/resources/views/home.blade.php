<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $appName = App\Models\AppSetting::getValue('app_name', 'FoodFlow');
        $appLogo = App\Models\AppSetting::getValue('app_logo', null);
        $appFavicon = App\Models\AppSetting::getValue('app_favicon', null);
        $headerBrandingType = App\Models\AppSetting::getValue('header_branding_type', 'text');
        $headerBrandingType = in_array($headerBrandingType, ['text', 'logo', 'logo_text']) ? $headerBrandingType : 'text';
        $primaryColor = App\Models\AppSetting::getValue('primary_color', '#EF4F5F');
        $secondaryColor = App\Models\AppSetting::getValue('secondary_color', '#FF8C42');
        $googleMapsKey = $googleMapsApiKey ?? App\Models\AppSetting::getValue('google_maps_api_key', App\Models\AppSetting::getValue('google_maps_key', ''));
        $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');

        $heroTitle = App\Models\AppSetting::getValue('hero_title', 'Where do you want to order from?');
        $heroSubtitle = App\Models\AppSetting::getValue('hero_subtitle', 'Discover the best restaurants in your neighborhood');
        $heroLocationPlaceholder = App\Models\AppSetting::getValue('hero_location_placeholder', 'Enter delivery location');
        $heroSearchPlaceholder = App\Models\AppSetting::getValue('hero_search_placeholder', 'Search for restaurant, cuisine or dish');
        $heroSearchButtonText = App\Models\AppSetting::getValue('hero_search_button_text', 'Search');

        $quickFilterVegOnly = App\Models\AppSetting::getValue('quick_filter_veg_only', 'Veg only');
        $quickFilterUnder99 = App\Models\AppSetting::getValue('quick_filter_under_99', "Under {$currencySymbol} 99");
        $quickFilterUnder199 = App\Models\AppSetting::getValue('quick_filter_under_199', "Under {$currencySymbol} 199");
        $quickFilterBestsellers = App\Models\AppSetting::getValue('quick_filter_bestsellers', 'Bestsellers');

        $statRestaurantCount = App\Models\AppSetting::getValue('stat_restaurant_count', '500+');
        $statRestaurantLabel = App\Models\AppSetting::getValue('stat_restaurant_label', 'Partner Restaurants');
        $statOrderCount = App\Models\AppSetting::getValue('stat_order_count', '10K+');
        $statOrderLabel = App\Models\AppSetting::getValue('stat_order_label', 'Orders Delivered');
        $statUserCount = App\Models\AppSetting::getValue('stat_user_count', '50K+');
        $statUserLabel = App\Models\AppSetting::getValue('stat_user_label', 'Happy Users');
        $statCityCount = App\Models\AppSetting::getValue('stat_city_count', '25+');
        $statCityLabel = App\Models\AppSetting::getValue('stat_city_label', 'Cities Covered');

        $partnerNavText = App\Models\AppSetting::getValue('partner_nav_text', 'Partner with Us');
        $partnerModalTitle = App\Models\AppSetting::getValue('partner_modal_title', 'Partner with ' . $appName);
        $partnerModalSubtitle = App\Models\AppSetting::getValue('partner_modal_subtitle', 'Choose a partner journey and grow with our delivery network.');
        $partnerRestaurantTitle = App\Models\AppSetting::getValue('partner_restaurant_title', 'Restaurant Partner');
        $partnerRestaurantText = App\Models\AppSetting::getValue('partner_restaurant_text', 'List your restaurant & reach thousands of customers');
        $partnerDriverTitle = App\Models\AppSetting::getValue('partner_driver_title', 'Delivery Partner');
        $partnerDriverText = App\Models\AppSetting::getValue('partner_driver_text', 'Earn money by delivering on your own schedule');

        $footerDescription = App\Models\AppSetting::getValue('footer_description', 'Order food from the best restaurants in your city. Fast delivery, great taste!');
        $footerCompanyTitle = App\Models\AppSetting::getValue('footer_company_title', 'Company');
        $footerSupportTitle = App\Models\AppSetting::getValue('footer_support_title', 'Support');
        $footerLegalTitle = App\Models\AppSetting::getValue('footer_legal_title', 'Legal');
        $footerLinkAbout = App\Models\AppSetting::getValue('footer_link_about', 'About Us');
        $footerLinkCareers = App\Models\AppSetting::getValue('footer_link_careers', 'Careers');
        $footerLinkBlog = App\Models\AppSetting::getValue('footer_link_blog', 'Blog');
        $footerLinkHelp = App\Models\AppSetting::getValue('footer_link_help', 'Help Center');
        $footerLinkContact = App\Models\AppSetting::getValue('footer_link_contact', 'Contact Us');
        $footerLinkFaqs = App\Models\AppSetting::getValue('footer_link_faqs', 'FAQs');
        $footerCopyright = App\Models\AppSetting::getValue('footer_copyright', 'All rights reserved.');
    @endphp
    <title>{{ $appName }} - Order food from best restaurants near you</title>
    <link rel="icon" href="{{ $appFavicon ? \Illuminate\Support\Facades\Storage::disk('public')->url($appFavicon) : asset('favicon.ico') }}">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Google Places API -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsKey }}&libraries=places&callback=initAutocomplete&v=weekly&loading=async" async defer></script>
    
    <style>
        :root {
            --primary: {{ $primaryColor }};
            --secondary: {{ $secondaryColor }};
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            background: #fff;
        }

        /* Zomato Style Navbar */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            padding: 20px 0;
            transition: all 0.3s ease;
            z-index: 1000;
            background: transparent;
        }

        .navbar.scrolled {
            background: white;
            padding: 12px 0;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.08);
        }

        .navbar.scrolled .nav-link,
        .navbar.scrolled .navbar-brand {
            color: #1C1C1C !important;
        }

        .navbar-brand {
            font-size: 28px;
            font-weight: 800;
            color: white !important;
            letter-spacing: -0.5px;
        }

        .navbar-brand span {
            color: var(--primary);
        }

        .nav-link {
            color: white !important;
            font-weight: 500;
            margin: 0 16px;
            transition: all 0.3s;
            font-size: 16px;
        }

        .nav-link:hover {
            color: var(--primary) !important;
        }

        .auth-buttons .btn {
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-outline-light {
            border: 1px solid white;
            color: white;
        }

        .btn-outline-light:hover {
            background: white;
            color: var(--primary);
        }

        /* Hero Section - Zomato Style */
        .hero {
            min-height: 85vh;
            background: linear-gradient(135deg, #111 0%, rgba(0,0,0,0.72) 45%, rgba(0,0,0,0.9) 100%);
            position: relative;
            display: flex;
            align-items: center;
            padding: 100px 0 60px;
        }

        .hero-content {
            text-align: center;
            color: white;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }

        .hero p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 40px;
        }

        /* Search Container - Zomato Style */
        .search-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .search-row {
            display: flex;
            align-items: center;
            padding: 8px 16px;
            gap: 16px;
        }

        .search-divider {
            width: 1px;
            height: 30px;
            background: #E8E8E8;
        }

        .location-input-wrapper,
        .search-input-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .location-input-wrapper i,
        .search-input-wrapper i {
            color: #FF6B35;
            font-size: 18px;
        }

        .location-input-wrapper input,
        .search-input-wrapper input {
            flex: 1;
            border: none;
            padding: 14px 0;
            font-size: 16px;
            outline: none;
            background: transparent;
        }

        .location-input-wrapper input::placeholder,
        .search-input-wrapper input::placeholder {
            color: #999;
        }

        .detect-btn {
            background: none;
            border: none;
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .detect-btn:hover {
            background: rgba(255, 111, 53, 0.12);
        }

        .search-btn {
            background: var(--primary);
            border: none;
            padding: 14px 32px;
            font-weight: 700;
            border-radius: 8px;
            color: white;
            transition: all 0.3s;
            cursor: pointer;
        }

        .search-btn:hover {
            background: #E55A2B;
        }

        /* Stats Section */
        .stats-section {
            padding: 60px 0;
            background: white;
            border-bottom: 1px solid #F0F0F0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: #1C1C1C;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #696969;
            font-size: 14px;
        }

        /* Categories Section */
        .categories-section {
            padding: 60px 0;
            background: white;
        }

        .section-header {
            text-align: center;
            margin-bottom: 48px;
        }

        .section-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .section-subtitle {
            color: #696969;
            font-size: 16px;
        }

        .category-card {
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .category-card:hover {
            transform: translateY(-5px);
        }

        .category-icon {
            width: 100px;
            height: 100px;
            background: #F8F8F8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            transition: all 0.3s;
        }

        .category-card:hover .category-icon {
            background: #FF6B35;
            color: white;
        }

        .category-icon i {
            font-size: 40px;
            color: #FF6B35;
            transition: all 0.3s;
        }

        .category-card:hover .category-icon i {
            color: white;
        }

        .category-name {
            font-weight: 600;
            font-size: 16px;
        }

        .quick-filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 22px;
        }

        .quick-filter-chip {
            border: 1px solid #E8E8E8;
            background: white;
            color: #1C1C1C;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 700;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .quick-filter-chip.active {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(255, 107, 53, 0.1);
        }

        /* Collection Section - Zomato Style */
        .collection-section {
            padding: 60px 0;
            background: #F8F8F8;
        }

        .collection-card {
            border-radius: 16px;
            overflow: hidden;
            position: relative;
            height: 300px;
            cursor: pointer;
        }

        .collection-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s;
        }

        .collection-card:hover img {
            transform: scale(1.05);
        }

        .collection-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
        }

        .collection-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .collection-places {
            font-size: 13px;
            opacity: 0.8;
        }

        /* Restaurants Section */
        .restaurants-section {
            padding: 60px 0;
            background: white;
        }

        .restaurant-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            margin-bottom: 24px;
        }

        .restaurant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .restaurant-img {
            position: relative;
            height: 180px;
            overflow: hidden;
        }

        .restaurant-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s;
        }

        .restaurant-card:hover .restaurant-img img {
            transform: scale(1.05);
        }

        .restaurant-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .promoted-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #FF6B35;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .restaurant-info {
            padding: 16px;
        }

        .restaurant-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .restaurant-cuisine {
            font-size: 13px;
            color: #696969;
            margin-bottom: 8px;
        }

        .restaurant-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .rating {
            background: #267C3A;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .delivery-time {
            font-size: 12px;
            color: #696969;
        }

        /* Location Modal - Zomato Style */
        .location-modal .modal-content {
            border-radius: 20px;
            border: none;
        }

        .location-search-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        .location-search-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #FF6B35;
        }

        .location-search-wrapper input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 1px solid #E8E8E8;
            border-radius: 12px;
            font-size: 16px;
            outline: none;
        }

        .location-search-wrapper input:focus {
            border-color: #FF6B35;
        }

        .saved-address-item {
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .saved-address-item:hover {
            background: #F8F8F8;
        }

        /* Footer */
        .footer {
            background: #1C1C1C;
            color: white;
            padding: 48px 0 24px;
        }

        .footer-logo {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .footer-logo span {
            color: var(--primary);
        }

        .footer-links h5 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .footer-links ul {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .footer-links a:hover {
            color: #FF6B35;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 { font-size: 36px; }
            .search-row { flex-direction: column; padding: 16px; }
            .search-divider { display: none; }
            .location-input-wrapper, .search-input-wrapper { width: 100%; }
            .search-btn { width: 100%; margin-top: 8px; }
            .section-title { font-size: 28px; }
            .category-icon { width: 70px; height: 70px; }
            .category-icon i { font-size: 28px; }
        }

        /* Autocomplete Styles */
        .pac-container {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-top: 8px;
            font-family: 'Inter', sans-serif;
            z-index: 10000 !important;
        }

        .pac-item {
            padding: 12px 16px;
            border-bottom: 1px solid #F0F0F0;
            cursor: pointer;
        }

        .pac-item:hover {
            background: #F8F8F8;
        }

        .pac-icon {
            margin-right: 12px;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #FF6B35;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
@include('partials.public-blade-polish')
</head>
<body>

<!-- Navbar -->
<nav class="navbar" id="navbar">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) }}" alt="{{ $appName }}" style="height: 38px; width: auto; object-fit: contain;">
            @endif
            @if($headerBrandingType === 'text' || $headerBrandingType === 'logo_text' || ! $appLogo)
                <span>{{ $appName }}</span>
            @endif
        </a>
        
        <div class="d-none d-md-flex align-items-center gap-4">
            <a href="#" class="nav-link" onclick="openPartnerModal(event)">{{ $partnerNavText }}</a>
            @auth
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" style="border-radius: 8px;">
                        <i class="fas fa-user me-1"></i> {{ Auth::user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('customer.orders.index') }}"><i class="fas fa-shopping-bag me-2"></i>My Orders</a></li>
                        <li><a class="dropdown-item" href="{{ route('customer.addresses.index') }}"><i class="fas fa-map-marker-alt me-2"></i>Addresses</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @else
                <div class="auth-buttons d-flex gap-2">
                    <a href="{{ route('login') }}" class="btn btn-outline-light">Login</a>
                    <a href="{{ route('register') }}" class="btn" style="background: #FF6B35; color: white;">Sign Up</a>
                </div>
            @endauth
        </div>
        
        <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" style="color: white; border: none;">
            <i class="fas fa-bars fa-2x"></i>
        </button>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">{{ $appName }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="d-flex flex-column gap-3">
            <a href="#" class="btn btn-outline-danger" onclick="openPartnerModal(event)">{{ $partnerNavText }}</a>
            @auth
                <a href="{{ route('customer.orders.index') }}" class="btn btn-outline-danger">My Orders</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger w-100">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-outline-danger">Login</a>
                <a href="{{ route('register') }}" class="btn btn-danger">Sign Up</a>
            @endauth
        </div>
    </div>
</div>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>{!! $heroTitle !!}</h1>
            <p>{{ $heroSubtitle }}</p>
            
            <div class="search-container">
                <div class="search-row">
                    <div class="location-input-wrapper">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" id="locationInput" placeholder="{{ $heroLocationPlaceholder }}" autocomplete="off">
                        <button type="button" class="detect-btn" onclick="detectUserLocation()">
                            <i class="fas fa-location-dot me-1"></i> Detect
                        </button>
                    </div>
                    <div class="search-divider"></div>
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="{{ $heroSearchPlaceholder }}" onkeypress="if(event.key === 'Enter') searchRestaurants()">
                    </div>
                    <button class="search-btn" onclick="searchRestaurants()">{{ $heroSearchButtonText }}</button>
                </div>
            </div>
            <div class="quick-filter-row">
                <button class="quick-filter-chip" id="vegOnlyChip" onclick="toggleVegOnly()">
                    <i class="fas fa-leaf me-1"></i> {{ $quickFilterVegOnly }}
                </button>
                <button class="quick-filter-chip" onclick="quickSearch('under 99')">{{ $quickFilterUnder99 }}</button>
                <button class="quick-filter-chip" onclick="quickSearch('under 199')">{{ $quickFilterUnder199 }}</button>
                <button class="quick-filter-chip" onclick="quickSearch('bestsellers')">{{ $quickFilterBestsellers }}</button>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <div class="stat-number" id="restaurantCount">{{ $statRestaurantCount }}</div>
                    <div class="stat-label">{{ $statRestaurantLabel }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <div class="stat-number" id="orderCount">{{ $statOrderCount }}</div>
                    <div class="stat-label">{{ $statOrderLabel }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <div class="stat-number" id="userCount">{{ $statUserCount }}</div>
                    <div class="stat-label">{{ $statUserLabel }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-item">
                    <div class="stat-number" id="cityCount">{{ $statCityCount }}</div>
                    <div class="stat-label">{{ $statCityLabel }}</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
@foreach($homepageSections as $section)
    @if($section['type'] === 'categories' || $section['type'] === 'cuisine_grid')
        @include('partials.home-sections.cuisine-grid', ['section' => $section])
    @elseif($section['type'] === 'banner_carousel')
        @include('partials.home-sections.banner-carousel', ['section' => $section])
    @elseif(in_array($section['type'], ['restaurant_grid', 'popular_restaurants', 'new_arrivals']))
        @include('partials.home-sections.restaurant-grid', ['section' => $section])
    @elseif(in_array($section['type'], ['recommended_for_you', 'featured_restaurants', 'trending_near_you']))
        @include('partials.home-sections.restaurant-featured', ['section' => $section])
    @elseif($section['type'] === 'shop_by_brand')
        @include('partials.home-sections.brand-grid', ['section' => $section])
    @elseif($section['type'] === 'restaurant_discovery')
        <section class="restaurants-section" id="restaurantsSection">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
                    <div>
                        <h2 class="section-title" style="text-align: left; margin-bottom: 0;">{!! $section['title'] !!}</h2>
                        @if($section['subtitle'])
                            <p class="text-muted">{{ $section['subtitle'] }}</p>
                        @endif
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-sort"></i> Sort by
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="sortRestaurants('rating')">Rating: High to Low</a></li>
                            <li><a class="dropdown-item" href="#" onclick="sortRestaurants('delivery_time')">Delivery Time: Fastest</a></li>
                            <li><a class="dropdown-item" href="#" onclick="sortRestaurants('price_low')">Price: Low to High</a></li>
                        </ul>
                    </div>
                </div>
                <div class="loader text-center py-4" id="loader" style="display: none;">
                    <div class="loading-spinner"></div>
                </div>
                <div class="row" id="restaurantsContainer"></div>
                <div class="text-center mt-4">
                    <button class="btn btn-outline-danger btn-lg" id="loadMoreBtn" onclick="loadMoreRestaurants()" style="display: none; border-radius: 8px;">
                        Load More <i class="fas fa-arrow-down ms-2"></i>
                    </button>
                </div>
            </div>
        </section>
    @endif
@endforeach

<!-- Location Modal (First Time) -->
<div class="modal fade location-modal" id="locationModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-map-marker-alt" style="font-size: 48px; color: #FF6B35;"></i>
                    <h4 class="mt-3 fw-bold">Set Your Delivery Location</h4>
                    <p class="text-muted">Find restaurants near you by entering your location</p>
                </div>
                
                <div class="location-search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="modalLocationInput" placeholder="Search for your city or area..." autocomplete="off">
                </div>
                
                <button class="btn btn-danger w-100 mb-3" onclick="detectAndSetLocation()" style="padding: 12px; border-radius: 12px;">
                    <i class="fas fa-location-dot me-2"></i> Detect Current Location
                </button>
                
                <div class="text-center">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal" style="color: #FF6B35;">Skip for now</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Partner Modal -->
<div class="modal fade" id="partnerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header border-0">
                <h4 class="modal-title fw-bold">{{ $partnerModalTitle }}</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-4">{{ $partnerModalSubtitle }}</p>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm text-center p-4" style="cursor: pointer;" onclick="location.href='/partner/register?type=restaurant'">
                            <i class="fas fa-store fa-3x text-danger mb-3"></i>
                            <h5>{{ $partnerRestaurantTitle }}</h5>
                            <p class="text-muted small">{{ $partnerRestaurantText }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm text-center p-4" style="cursor: pointer;" onclick="location.href='/partner/register?type=driver'">
                            <i class="fas fa-motorcycle fa-3x text-warning mb-3"></i>
                            <h5>{{ $partnerDriverTitle }}</h5>
                            <p class="text-muted small">{{ $partnerDriverText }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="footer-logo">{{ $appName }}</div>
                <p class="text-muted small">{{ $footerDescription }}</p>
                <div class="d-flex gap-3 mt-3">
                    <a href="#" class="text-white"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="footer-links">
                    <h5>{{ $footerCompanyTitle }}</h5>
                    <ul>
                        <li><a href="{{ route('about') }}">{{ $footerLinkAbout }}</a></li>
                        <li><a href="{{ route('careers') }}">{{ $footerLinkCareers }}</a></li>
                        <li><a href="{{ route('blog') }}">{{ $footerLinkBlog }}</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="footer-links">
                    <h5>{{ $footerSupportTitle }}</h5>
                    <ul>
                        <li><a href="{{ route('help') }}">{{ $footerLinkHelp }}</a></li>
                        <li><a href="{{ route('contact') }}">{{ $footerLinkContact }}</a></li>
                        <li><a href="{{ route('faqs') }}">{{ $footerLinkFaqs }}</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-md-2 mb-4">
                <div class="footer-links">
                    <h5>{{ $footerLegalTitle }}</h5>
                    <ul>
                        <li><a href="{{ route('legal.terms') }}">Terms</a></li>
                        <li><a href="{{ route('legal.privacy') }}">Privacy</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
        <div class="text-center text-muted small">
            &copy; {{ date('Y') }} {{ $appName }}. {{ $footerCopyright }}
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Global Variables
    let autocomplete;
    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    let currentSort = 'rating';
    let currentLocation = '';
    let currentSearch = '';
    let currentLat = null;
    let currentLng = null;
    let vegOnly = false;

    // Google Places Autocomplete
    function initAutocomplete() {
        const locationInput = document.getElementById('locationInput');
        const modalLocationInput = document.getElementById('modalLocationInput');
        
        if (locationInput) {
            autocomplete = new google.maps.places.Autocomplete(locationInput, {
                types: ['(cities)'],
                componentRestrictions: { country: 'in' }
            });
            
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (place.geometry) {
                    currentLat = place.geometry.location.lat();
                    currentLng = place.geometry.location.lng();
                    currentLocation = place.formatted_address || place.name;
                    searchRestaurants();
                }
            });
        }
        
        if (modalLocationInput) {
            const modalAutocomplete = new google.maps.places.Autocomplete(modalLocationInput, {
                types: ['(cities)'],
                componentRestrictions: { country: 'in' }
            });
            
            modalAutocomplete.addListener('place_changed', function() {
                const place = modalAutocomplete.getPlace();
                if (place.geometry) {
                    currentLat = place.geometry.location.lat();
                    currentLng = place.geometry.location.lng();
                    currentLocation = place.formatted_address || place.name;
                    document.getElementById('locationInput').value = currentLocation;
                    bootstrap.Modal.getInstance(document.getElementById('locationModal')).hide();
                    searchRestaurants();
                    saveLocationToStorage(currentLat, currentLng, currentLocation);
                }
            });
        }
    }

    // Detect User Location
    function detectUserLocation() {
        const detectBtn = document.querySelector('.detect-btn');
        const originalText = detectBtn.innerHTML;
        detectBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Detecting...';
        detectBtn.disabled = true;
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    currentLat = position.coords.latitude;
                    currentLng = position.coords.longitude;
                    await reverseGeocode(currentLat, currentLng);
                    detectBtn.innerHTML = originalText;
                    detectBtn.disabled = false;
                },
                (error) => {
                    showToast('Unable to detect location. Please enter manually.', 'error');
                    detectBtn.innerHTML = originalText;
                    detectBtn.disabled = false;
                }
            );
        } else {
            showToast('Geolocation is not supported by your browser', 'error');
            detectBtn.innerHTML = originalText;
            detectBtn.disabled = false;
        }
    }

    function detectAndSetLocation() {
        const btn = document.querySelector('#locationModal .btn-danger');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Detecting...';
        btn.disabled = true;
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    currentLat = position.coords.latitude;
                    currentLng = position.coords.longitude;
                    await reverseGeocode(currentLat, currentLng);
                    bootstrap.Modal.getInstance(document.getElementById('locationModal')).hide();
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    searchRestaurants();
                },
                (error) => {
                    showToast('Unable to detect location. Please enter manually.', 'error');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            );
        } else {
            showToast('Geolocation is not supported by your browser', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    async function reverseGeocode(lat, lng) {
        try {
            const response = await fetch(`https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key={{ $googleMapsKey }}`);
            const data = await response.json();
            if (data.results && data.results[0]) {
                currentLocation = data.results[0].formatted_address;
                document.getElementById('locationInput').value = currentLocation;
                saveLocationToStorage(lat, lng, currentLocation);
                showToast('Location detected successfully!', 'success');
            }
        } catch (error) {
            console.error('Reverse geocoding error:', error);
        }
    }

    function saveLocationToStorage(lat, lng, location) {
        localStorage.setItem('userLocation', JSON.stringify({ lat, lng, location }));
    }

    function loadSavedLocation() {
        const saved = localStorage.getItem('userLocation');
        if (saved) {
            const { lat, lng, location } = JSON.parse(saved);
            currentLat = lat;
            currentLng = lng;
            currentLocation = location;
            document.getElementById('locationInput').value = location;
            return true;
        }
        return false;
    }

    // Load Restaurants
    async function loadRestaurants(reset = true) {
        if (isLoading) return;
        if (reset) { currentPage = 1; hasMore = true; document.getElementById('restaurantsContainer').innerHTML = ''; }
        if (!hasMore && !reset) return;
        
        isLoading = true;
        document.getElementById('loader').style.display = 'block';
        
        try {
            let url = `/api/restaurants/search?page=${currentPage}&search=${encodeURIComponent(currentSearch)}&sort=${currentSort}`;
            if (currentLat && currentLng) {
                url += `&lat=${currentLat}&lng=${currentLng}`;
            } else if (currentLocation) {
                url += `&location=${encodeURIComponent(currentLocation)}`;
            }
            if (vegOnly) {
                url += '&pure_veg=true';
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.restaurants && data.restaurants.length) {
                const container = document.getElementById('restaurantsContainer');
                const newHtml = data.restaurants.map(r => `
                    <div class="col-md-4 col-lg-3">
                        <a href="/restaurants/${r.id}" class="restaurant-card">
                            <div class="restaurant-img">
                                <img src="${r.image || 'https://placehold.co/400x300/E8E8E8/9C9C9C?text=No+Image'}" alt="${escapeHtml(r.name)}">
                                ${r.is_featured ? '<span class="promoted-badge">Featured</span>' : ''}
                                ${r.is_pure_veg ? '<span class="restaurant-badge" style="top:46px;background:#267C3A;">Pure Veg</span>' : ''}
                                <span class="restaurant-badge">${r.is_open ? 'Open Now' : 'Closed'}</span>
                            </div>
                            <div class="restaurant-info">
                                <div class="restaurant-name">${escapeHtml(r.name)}</div>
                                <div class="restaurant-cuisine">${r.cuisine || 'Various Cuisines'}</div>
                                <div class="restaurant-meta">
                                    <div class="rating"><i class="fas fa-star me-1"></i> ${r.rating || '4.0'}</div>
                                    <div class="delivery-time"><i class="far fa-clock me-1"></i> ${r.delivery_time || '30-40'} min</div>
                                </div>
                            </div>
                        </a>
                    </div>
                `).join('');
                
                if (reset) container.innerHTML = newHtml;
                else container.insertAdjacentHTML('beforeend', newHtml);
                
                if (data.has_more) { currentPage++; hasMore = true; document.getElementById('loadMoreBtn').style.display = 'inline-block'; }
                else { hasMore = false; document.getElementById('loadMoreBtn').style.display = 'none'; }
            } else if (reset) {
                document.getElementById('restaurantsContainer').innerHTML = '<div class="col-12 text-center py-5">No restaurants found</div>';
            }
        } catch(e) {
            console.error('Error loading restaurants:', e);
        } finally {
            isLoading = false;
            document.getElementById('loader').style.display = 'none';
        }
    }

    // Search Functions
    async function searchRestaurants() {
        currentLocation = document.getElementById('locationInput')?.value || '';
        currentSearch = document.getElementById('searchInput')?.value || '';
        
        if (currentLocation) {
            if (!currentLat || !currentLng) {
                try {
                    const response = await fetch(`https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(currentLocation)}&key={{ $googleMapsKey }}`);
                    const data = await response.json();
                    if (data.results && data.results[0]) {
                        currentLat = data.results[0].geometry.location.lat;
                        currentLng = data.results[0].geometry.location.lng;
                        saveLocationToStorage(currentLat, currentLng, currentLocation);
                    }
                } catch(e) { console.error(e); }
            }
        }
        loadRestaurants(true);
    }

    function sortRestaurants(sort) { currentSort = sort; loadRestaurants(true); }
    function loadMoreRestaurants() { if (!isLoading && hasMore) loadRestaurants(false); }
    function searchByCategory(category) { document.getElementById('searchInput').value = category; searchRestaurants(); }
    function quickSearch(query) { document.getElementById('searchInput').value = query; searchRestaurants(); }
    function toggleVegOnly() {
        vegOnly = !vegOnly;
        document.getElementById('vegOnlyChip')?.classList.toggle('active', vegOnly);
        loadRestaurants(true);
    }
    function openPartnerModal(event) { if(event) event.preventDefault(); new bootstrap.Modal(document.getElementById('partnerModal')).show(); }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `position-fixed bottom-0 end-0 p-3`;
        toast.style.zIndex = '1100';
        toast.innerHTML = `
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // Navbar Scroll Effect
    window.addEventListener('scroll', () => {
        const navbar = document.getElementById('navbar');
        if (window.scrollY > 50) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
    });

    // Infinite Scroll
    window.addEventListener('scroll', () => {
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 500) {
            loadMoreRestaurants();
        }
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        const saved = loadSavedLocation();
        if (!saved) {
            new bootstrap.Modal(document.getElementById('locationModal')).show();
        } else {
            loadRestaurants(true);
        }
    });
</script>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
