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
        $frontendBackgroundImage = App\Models\AppSetting::getValue('frontend_background_image', null);
        $frontendBackgroundImageUrl = $frontendBackgroundImage
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($frontendBackgroundImage)
            : null;
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
            --primary-rgb: {{ sscanf($primaryColor, '#%02x%02x%02x')[0] ?? 239 }}, {{ sscanf($primaryColor, '#%02x%02x%02x')[1] ?? 79 }}, {{ sscanf($primaryColor, '#%02x%02x%02x')[2] ?? 95 }};
            --secondary-rgb: {{ sscanf($secondaryColor, '#%02x%02x%02x')[0] ?? 255 }}, {{ sscanf($secondaryColor, '#%02x%02x%02x')[1] ?? 140 }}, {{ sscanf($secondaryColor, '#%02x%02x%02x')[2] ?? 66 }};
            --brand-gradient: linear-gradient(135deg, var(--primary), var(--secondary));
            --brand-gradient-hover: linear-gradient(135deg, color-mix(in srgb, var(--primary) 82%, #000), color-mix(in srgb, var(--secondary) 88%, #000));
            --brand-soft-shadow: 0 18px 40px rgba(var(--primary-rgb), 0.24);
            --frontend-bg-image: @if($frontendBackgroundImageUrl) url('{{ $frontendBackgroundImageUrl }}') @else none @endif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 8% 18%, rgba(var(--primary-rgb), 0.055), transparent 28%),
                radial-gradient(circle at 92% 12%, rgba(var(--secondary-rgb), 0.06), transparent 26%),
                #fff;
            color: #1C1C1C;
            perspective: 1100px;
        }

        .ambient-orb {
            position: absolute;
            z-index: -1;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            filter: blur(4px);
            pointer-events: none;
            opacity: 0.24;
            animation: floatOrb 9s ease-in-out infinite;
        }

        .ambient-orb.one {
            bottom: -68px;
            left: 7%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(var(--secondary-rgb), 0.28), rgba(255,255,255,0.08) 45%, transparent 68%);
        }

        .ambient-orb.two {
            right: 10%;
            top: 24%;
            background: radial-gradient(circle, rgba(var(--primary-rgb), 0.28), rgba(255,255,255,0.08) 45%, transparent 68%);
            animation-delay: -3s;
        }

        /* Zomato Style Navbar */
        .navbar {
            position: fixed;
            top: 22px;
            left: 0;
            right: 0;
            width: 100%;
            padding: 0;
            transition: all 0.3s ease;
            z-index: 1080;
            background: transparent;
            pointer-events: none;
        }

        .navbar > .container {
            min-height: 78px;
            padding: 10px 22px;
            border: 1px solid rgba(255,255,255,0.34);
            border-radius: 999px;
            background:
                linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06)),
                rgba(15, 23, 42, 0.08);
            box-shadow:
                0 22px 58px rgba(12, 10, 35, 0.22),
                inset 0 1px 0 rgba(255,255,255,0.28);
            backdrop-filter: blur(18px);
            transform: translateZ(42px);
            position: relative;
            z-index: 2;
            pointer-events: auto;
            transition:
                min-height .28s ease,
                padding .28s ease,
                border-color .28s ease,
                background .28s ease,
                box-shadow .28s ease;
        }

        .navbar.scrolled {
            top: 22px;
            background: transparent;
        }

        .navbar a,
        .navbar button,
        .navbar .dropdown-menu {
            pointer-events: auto;
        }

        .navbar .dropdown {
            position: relative;
        }

        .navbar .dropdown-menu {
            z-index: 12120;
            top: calc(100% + 12px) !important;
            right: 0 !important;
            left: auto !important;
            transform: none !important;
            margin-top: 0 !important;
            border: 0;
            border-radius: 18px;
            box-shadow: 0 22px 54px rgba(15, 23, 42, 0.18);
            overflow: hidden;
        }

        .offcanvas {
            z-index: 12110 !important;
        }

        .offcanvas-backdrop {
            z-index: 12100 !important;
        }

        .modal {
            z-index: 12050 !important;
        }

        .modal-backdrop {
            z-index: 12040 !important;
        }

        .navbar.scrolled > .container {
            min-height: 78px;
            padding: 10px 22px;
            border-color: rgba(var(--primary-rgb), 0.16);
            background: rgba(255, 255, 255, 0.9);
            box-shadow:
                0 18px 44px rgba(15, 23, 42, 0.13),
                inset 0 1px 0 rgba(255,255,255,0.95);
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

        .navbar-brand img {
            height: 58px !important;
            max-width: 180px;
            object-fit: contain;
            filter: drop-shadow(0 8px 18px rgba(0,0,0,0.2));
        }

        .navbar.scrolled .navbar-brand img {
            height: 58px !important;
        }

        .navbar-brand span {
            color: #fff;
            text-shadow: 0 10px 22px rgba(0,0,0,0.25);
        }

        .navbar.scrolled .navbar-brand span {
            color: var(--primary);
            text-shadow: none;
        }

        .nav-link {
            color: white !important;
            font-weight: 800;
            margin: 0 16px;
            transition: all 0.3s;
            font-size: 16px;
            text-shadow: 0 8px 18px rgba(0,0,0,0.18);
        }

        .nav-link:hover {
            color: color-mix(in srgb, var(--secondary) 72%, #fff) !important;
            transform: translateY(-1px);
        }

        .navbar.scrolled .nav-link {
            text-shadow: none;
        }

        .auth-buttons .btn {
            border-radius: 8px;
            padding: 8px 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-outline-light {
            border: 1px solid rgba(255,255,255,0.78);
            color: white;
            background: rgba(255,255,255,0.08);
            box-shadow:
                0 12px 26px rgba(12, 10, 35, 0.16),
                inset 0 1px 0 rgba(255,255,255,0.24);
            backdrop-filter: blur(10px);
        }

        .btn-outline-light:hover {
            background: white;
            color: var(--primary);
        }

        .navbar.scrolled .btn-outline-light {
            border-color: rgba(var(--primary-rgb), 0.18);
            color: #111827;
            background: rgba(var(--primary-rgb), 0.06);
            box-shadow: 0 10px 24px rgba(var(--primary-rgb), 0.12);
        }

        /* Hero Section - Zomato Style */
        .hero {
            min-height: 760px;
            background:
                radial-gradient(circle at 78% 52%, rgba(var(--secondary-rgb), 0.48), transparent 26%),
                linear-gradient(120deg, rgba(13, 18, 38, 0.92) 0%, rgba(var(--primary-rgb), 0.76) 45%, rgba(var(--secondary-rgb), 0.84) 100%);
            background-size: cover;
            background-position: center;
            position: relative;
            display: flex;
            align-items: flex-start;
            padding: 164px 0 72px;
            overflow: hidden;
            border-bottom-left-radius: 46px;
            border-bottom-right-radius: 46px;
            isolation: isolate;
            box-shadow:
                0 34px 80px rgba(var(--primary-rgb), 0.18),
                inset 0 -28px 70px rgba(0,0,0,0.16);
        }

        .hero-bg-image {
            position: absolute;
            inset: 0;
            z-index: -3;
            background-image: var(--frontend-bg-image);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            transform: scale(1.03);
            opacity: 0.72;
        }

        .hero-bg-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(120deg, rgba(13,18,38,0.78), rgba(var(--primary-rgb),0.52), rgba(var(--secondary-rgb),0.42)),
                radial-gradient(circle at 78% 52%, rgba(var(--secondary-rgb),0.36), transparent 28%);
        }

        .hero::before,
        .hero::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
        }

        .hero::before {
            width: 330px;
            height: 330px;
            right: 8%;
            top: 16%;
            background: radial-gradient(circle, rgba(var(--secondary-rgb),0.28), rgba(255,255,255,0.12) 38%, transparent 66%);
            animation: heroPulse 6s ease-in-out infinite;
        }

        .hero::after {
            width: 190px;
            height: 190px;
            left: 7%;
            bottom: -32px;
            width: 260px;
            height: 260px;
            border: 1px solid rgba(255,255,255,0.14);
            background: linear-gradient(135deg, rgba(var(--primary-rgb),0.18), rgba(var(--secondary-rgb),0.18));
            animation: floatOrb 8s ease-in-out infinite reverse;
        }

        .hero-content {
            text-align: center;
            color: white;
            max-width: 1120px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            transform: translateZ(34px);
        }

        .hero h1 {
            font-size: clamp(44px, 5vw, 76px);
            font-weight: 900;
            margin-bottom: 20px;
            letter-spacing: -2.2px;
            line-height: 1.06;
        }

        .hero p {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.78);
            margin-bottom: 34px;
            font-weight: 600;
        }

        /* Search Container - Zomato Style */
        .search-container {
            width: min(100%, 1060px);
            max-width: 1060px;
            margin: 0 auto;
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(255,255,255,0.82);
            border-radius: 30px;
            overflow: visible;
            box-shadow:
                0 30px 76px rgba(12, 10, 35, 0.28),
                inset 0 1px 0 rgba(255,255,255,0.92);
            backdrop-filter: blur(18px);
            transform-style: preserve-3d;
            transform: translateZ(46px);
            position: relative;
            padding: 10px;
        }

        .search-container::before {
            content: '';
            position: absolute;
            inset: 10px;
            border-radius: 20px;
            z-index: -1;
            background: var(--brand-gradient);
            filter: blur(28px);
            opacity: 0.26;
        }

        .search-row {
            display: flex;
            align-items: center;
            width: 100%;
            gap: 10px;
        }

        .search-divider {
            display: none;
        }

        .location-input-wrapper,
        .search-input-wrapper {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            height: 64px;
            background: #fff;
            border: 1px solid #E6E9F0;
            border-radius: 18px;
            padding: 0 16px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.9);
            transition: border-color .22s ease, box-shadow .22s ease, transform .22s ease;
        }

        .location-input-wrapper {
            flex: 1.15 1 0;
        }

        .search-input-wrapper {
            flex: 1 1 0;
        }

        .location-input-wrapper:focus-within,
        .search-input-wrapper:focus-within {
            border-color: rgba(var(--primary-rgb), 0.52);
            box-shadow: 0 10px 28px rgba(var(--primary-rgb), 0.14);
            transform: translateY(-1px);
        }

        .location-input-wrapper i,
        .search-input-wrapper i {
            color: var(--primary);
            font-size: 20px;
        }

        .location-input-wrapper input,
        .search-input-wrapper input {
            flex: 1;
            min-width: 0;
            border: none;
            padding: 0;
            font-size: 17px;
            outline: none;
            background: transparent;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .location-input-wrapper input::placeholder,
        .search-input-wrapper input::placeholder {
            color: #999;
        }

        .detect-btn {
            background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1), rgba(var(--secondary-rgb), 0.08));
            border: none;
            color: var(--primary);
            font-weight: 800;
            font-size: 13.5px;
            cursor: pointer;
            padding: 11px 13px;
            border-radius: 14px;
            transition: all 0.2s;
            white-space: nowrap;
            flex: 0 0 auto;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .detect-btn:hover {
            background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.18), rgba(var(--secondary-rgb), 0.13));
            transform: translateY(-1px);
        }

        .search-btn {
            background: var(--brand-gradient);
            border: none;
            flex: 0 0 164px;
            width: 164px;
            height: 64px;
            min-height: 64px;
            padding: 0 28px;
            font-weight: 900;
            font-size: 17px;
            border-radius: 999px;
            color: white;
            transition: all 0.3s;
            cursor: pointer;
            box-shadow: var(--brand-soft-shadow);
        }

        .search-btn:hover {
            background: var(--brand-gradient-hover);
            transform: translateY(-2px);
            box-shadow: 0 20px 42px rgba(var(--primary-rgb), 0.32);
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
            transform-style: preserve-3d;
        }

        .category-card:hover {
            transform: translateY(-8px) rotateX(4deg);
        }

        .category-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(145deg, #fff, #F8F8F8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            transition: all 0.3s;
        }

        .category-card:hover .category-icon {
            background: var(--brand-gradient);
            color: white;
            box-shadow: 0 18px 36px rgba(var(--primary-rgb), 0.24);
            transform: translateZ(22px);
        }

        .category-icon i {
            font-size: 40px;
            color: var(--primary);
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
            border: 1px solid rgba(255,255,255,0.82);
            background: rgba(255,255,255,0.96);
            color: #1C1C1C;
            border-radius: 999px;
            padding: 13px 21px;
            font-weight: 900;
            box-shadow: 0 12px 26px rgba(12, 10, 35, 0.16);
            backdrop-filter: blur(12px);
        }

        .quick-filter-chip.active {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(var(--primary-rgb), 0.1);
            box-shadow: 0 14px 30px rgba(var(--primary-rgb), 0.18);
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
            box-shadow: 0 20px 46px rgba(15, 23, 42, 0.12);
            transform-style: preserve-3d;
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
            box-shadow:
                0 18px 42px rgba(15, 23, 42, 0.08),
                0 1px 0 rgba(255,255,255,0.9) inset;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            margin-bottom: 24px;
        }

        .restaurant-card:hover {
            transform: translateY(-9px) rotateX(2deg);
            box-shadow:
                0 28px 62px rgba(var(--primary-rgb), 0.18),
                0 1px 0 rgba(255,255,255,0.9) inset;
        }

        .ff-reveal {
            opacity: 0;
            transform: translateY(28px);
            transition:
                opacity 0.76s cubic-bezier(.2,.7,.2,1),
                transform 0.76s cubic-bezier(.2,.7,.2,1);
            transition-delay: var(--ff-delay, 0ms);
        }

        .ff-reveal.in-view {
            opacity: 1;
            transform: translateY(0);
        }

        .ff-lift {
            will-change: transform;
        }

        .ff-lift:hover {
            transform: translateY(-7px) scale(1.01);
        }

        @keyframes floatOrb {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50% { transform: translate3d(16px, -20px, 0) scale(1.05); }
        }

        @keyframes heroPulse {
            0%, 100% { transform: scale(1); opacity: 0.72; }
            50% { transform: scale(1.08); opacity: 1; }
        }

        @media (prefers-reduced-motion: reduce) {
            .ff-reveal,
            .ambient-orb,
            .hero::before,
            .hero::after {
                animation: none !important;
                transition: none !important;
                transform: none !important;
            }

            .ff-reveal {
                opacity: 1;
            }
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
            background: var(--brand-gradient);
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
        .location-modal.modal {
            z-index: 12050 !important;
            padding-top: clamp(76px, 9vh, 118px);
        }

        .location-modal .modal-dialog-centered {
            align-items: flex-start;
            min-height: auto;
        }

        .location-modal .modal-dialog {
            max-width: 520px;
            margin-top: 0;
            margin-left: auto;
            margin-right: auto;
        }

        .location-modal .modal-content {
            border-radius: 30px;
            border: none;
            overflow: hidden;
            background:
                radial-gradient(circle at 18% 0%, rgba(var(--primary-rgb), 0.18), transparent 34%),
                radial-gradient(circle at 92% 14%, rgba(var(--secondary-rgb), 0.16), transparent 30%),
                linear-gradient(180deg, #ffffff, #fff7f3);
            box-shadow:
                0 34px 90px rgba(var(--primary-rgb), 0.22),
                inset 0 1px 0 rgba(255,255,255,0.95);
        }

        .location-modal .modal-body {
            padding: 34px !important;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }

        .location-modal-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 6px;
        }

        .location-modal-header h4 {
            margin: 18px 0 7px;
            color: #111827;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: -0.4px;
        }

        .location-modal-header p {
            margin: 0;
            max-width: 360px;
            color: #6B7280;
            font-size: 14.5px;
            line-height: 1.45;
        }

        .location-modal-icon {
            width: 76px;
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(var(--secondary-rgb), 0.14), rgba(var(--primary-rgb), 0.13));
            box-shadow: 0 16px 34px rgba(var(--primary-rgb), 0.16);
            margin: 0 auto;
        }

        .location-modal-icon i {
            color: var(--primary);
            font-size: 32px;
            line-height: 1;
        }

        .location-search-wrapper {
            position: relative;
            margin-bottom: 0;
            width: 100%;
            display: flex;
            align-items: center;
        }

        .location-search-wrapper i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 18px;
            line-height: 1;
            pointer-events: none;
            z-index: 2;
        }

        .location-search-wrapper input {
            width: 100%;
            height: 58px;
            padding: 0 18px 0 54px;
            border: 1px solid #E6E9F0;
            border-radius: 18px;
            font-size: 16px;
            outline: none;
            box-shadow: 0 10px 28px rgba(12, 10, 35, 0.06);
            transition: border-color .22s ease, box-shadow .22s ease, transform .22s ease;
            line-height: 58px;
        }

        .location-search-wrapper input:focus {
            border-color: rgba(var(--primary-rgb), 0.55);
            box-shadow: 0 16px 38px rgba(var(--primary-rgb), 0.14);
            transform: translateY(-1px);
        }

        .location-modal .btn-danger {
            border: 0;
            border-radius: 18px !important;
            background: var(--brand-gradient) !important;
            box-shadow: var(--brand-soft-shadow);
            font-weight: 900;
            transition: transform .22s ease, box-shadow .22s ease;
        }

        .location-detect-button {
            width: 100%;
            height: 56px;
            padding: 0 18px !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 !important;
            font-size: 15.5px;
        }

        .location-modal .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 42px rgba(var(--primary-rgb), 0.34);
        }

        .location-modal .btn-link {
            font-weight: 800;
            text-decoration: none;
            color: var(--primary);
            padding: 4px 10px;
        }

        .location-modal-footer {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: -2px;
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
            color: var(--secondary);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .search-container {
                width: min(100%, 760px);
            }

            .search-row {
                flex-wrap: wrap;
            }

            .location-input-wrapper {
                flex: 1 1 100%;
            }

            .search-input-wrapper {
                flex: 1 1 calc(100% - 174px);
            }
        }

        @media (max-width: 768px) {
            .navbar {
                top: 10px;
                padding: 0 10px;
            }
            .navbar > .container {
                min-height: 62px;
                padding: 8px 14px;
                border-radius: 24px;
            }
            .navbar.scrolled {
                top: 10px;
            }
            .navbar.scrolled > .container {
                min-height: 62px;
                padding: 8px 14px;
            }
            .navbar-brand img { height: 46px !important; max-width: 140px; }
            .navbar.scrolled .navbar-brand img { height: 46px !important; }
            .hero {
                min-height: 680px;
                padding: 100px 0 52px;
                border-bottom-left-radius: 28px;
                border-bottom-right-radius: 28px;
            }
            .hero h1 { font-size: 38px; letter-spacing: -1.2px; }
            .hero p { font-size: 16px; margin-bottom: 22px; }
            .search-container {
                border-radius: 24px;
                padding: 8px;
            }
            .search-row {
                display: flex;
                flex-direction: column;
                padding: 0;
                gap: 10px;
            }
            .search-divider { display: none; }
            .location-input-wrapper, .search-input-wrapper {
                width: 100%;
                height: 58px;
            }
            .search-btn {
                flex: 0 0 auto;
                width: 100%;
                height: 58px;
                min-height: 58px;
                margin-top: 2px;
            }
            .quick-filter-chip { padding: 11px 15px; font-size: 14px; }
            .location-modal .modal-dialog { margin: 12px; }
            .location-modal .modal-body { padding: 24px !important; gap: 14px; }
            .location-modal-icon { width: 66px; height: 66px; border-radius: 22px; }
            .location-modal-icon i { font-size: 28px; }
            .location-modal-header h4 { font-size: 21px; margin-top: 14px; }
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
            z-index: 12080 !important;
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
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar" id="navbar">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) }}" alt="{{ $appName }}">
            @endif
            @if($headerBrandingType === 'text' || $headerBrandingType === 'logo_text' || ! $appLogo)
                <span>{{ $appName }}</span>
            @endif
        </a>
        
        <div class="d-none d-md-flex align-items-center gap-4">
            <a href="#partnerModal" class="nav-link" data-partner-trigger>{{ $partnerNavText }}</a>
            @auth
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-offset="0,12" style="border-radius: 8px;">
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
                    <a href="{{ route('register') }}" class="btn" style="background: var(--brand-gradient); color: white; box-shadow: var(--brand-soft-shadow);">Sign Up</a>
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
            <a href="#partnerModal" class="btn btn-outline-danger" data-partner-trigger>{{ $partnerNavText }}</a>
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
    @if($frontendBackgroundImageUrl)
        <div class="hero-bg-image" aria-hidden="true"></div>
    @endif
    <div class="ambient-orb one" aria-hidden="true"></div>
    <div class="ambient-orb two" aria-hidden="true"></div>
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
                <div class="location-modal-header">
                    <div class="location-modal-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h4>Set Your Delivery Location</h4>
                    <p>Find restaurants near you by entering your location</p>
                </div>
                
                <div class="location-search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="modalLocationInput" placeholder="Search for your city or area..." autocomplete="off">
                </div>
                
                <button class="btn btn-danger location-detect-button" onclick="detectAndSetLocation()">
                    <i class="fas fa-location-dot me-2"></i> Detect Current Location
                </button>
                
                <div class="location-modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Skip for now</button>
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
                revealDynamicContent(container);
                
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
    function openPartnerModal(event) {
        if (event) event.preventDefault();
        const modalEl = document.getElementById('partnerModal');
        if (!modalEl) return;
        if (window.bootstrap?.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
    
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

    function updateNavbarState() {
        const navbar = document.getElementById('navbar');
        if (!navbar) return;
        navbar.classList.toggle('scrolled', window.scrollY > 16);
    }

    function initHeaderActions() {
        updateNavbarState();
        window.addEventListener('scroll', updateNavbarState, { passive: true });

        document.querySelectorAll('[data-partner-trigger]').forEach((trigger) => {
            trigger.addEventListener('click', openPartnerModal);
        });

        if (window.bootstrap?.Dropdown) {
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((toggle) => {
                bootstrap.Dropdown.getOrCreateInstance(toggle);
            });
        }

        if (window.bootstrap?.Offcanvas) {
            document.querySelectorAll('[data-bs-toggle="offcanvas"]').forEach((toggle) => {
                const targetSelector = toggle.getAttribute('data-bs-target');
                const target = targetSelector ? document.querySelector(targetSelector) : null;
                if (!target) return;
                const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(target);
                toggle.addEventListener('click', (event) => {
                    event.preventDefault();
                    offcanvas.show();
                });
            });
        }
    }

    // Infinite Scroll
    window.addEventListener('scroll', () => {
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 500) {
            loadMoreRestaurants();
        }
    });

    function initFrontendMotion() {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const revealTargets = [
            '.hero-content',
            '.search-container',
            '.quick-filter-chip',
            '.stats-section .stat-item',
            '.section-header',
            '.category-card',
            '.collection-card',
            '.restaurant-card',
            '.footer .col-md-4',
            '.footer-links',
            '.card'
        ].join(',');

        const targets = Array.from(document.querySelectorAll(revealTargets));
        targets.forEach((el, index) => {
            el.classList.add('ff-reveal');
            el.style.setProperty('--ff-delay', `${Math.min(index % 8, 7) * 70}ms`);
        });

        if (reduceMotion || !('IntersectionObserver' in window)) {
            targets.forEach((el) => el.classList.add('in-view'));
        } else {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('in-view');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

            targets.forEach((el) => observer.observe(el));
        }

        document.querySelectorAll('.restaurant-card, .collection-card, .category-card, .quick-filter-chip, .search-container')
            .forEach((el) => el.classList.add('ff-lift'));

        animateStats();
        initSoftParallax();
    }

    function revealDynamicContent(root = document) {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const items = Array.from(root.querySelectorAll('.restaurant-card:not(.ff-reveal), .collection-card:not(.ff-reveal), .category-card:not(.ff-reveal)'));
        items.forEach((el, index) => {
            el.classList.add('ff-reveal', 'ff-lift');
            el.style.setProperty('--ff-delay', `${Math.min(index % 8, 7) * 55}ms`);
            if (reduceMotion) {
                el.classList.add('in-view');
            } else {
                requestAnimationFrame(() => el.classList.add('in-view'));
            }
        });
    }

    function animateStats() {
        const statEls = Array.from(document.querySelectorAll('.stat-number'));
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function parseStat(text) {
            const raw = String(text || '').trim();
            const number = parseFloat(raw.replace(/[^0-9.]/g, ''));
            return {
                raw,
                number: Number.isFinite(number) ? number : null,
                prefix: raw.match(/^[^\d]*/)?.[0] || '',
                suffix: raw.match(/[^\d.]*$/)?.[0] || '',
            };
        }

        const run = (el) => {
            if (el.dataset.counted === '1') return;
            el.dataset.counted = '1';
            const parsed = parseStat(el.textContent);
            if (parsed.number === null || reduceMotion) return;

            const start = performance.now();
            const duration = 1050;
            const target = parsed.number;
            const decimals = String(target).includes('.') ? 1 : 0;

            function tick(now) {
                const progress = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - progress, 3);
                const value = target * eased;
                el.textContent = `${parsed.prefix}${value.toFixed(decimals)}${parsed.suffix}`;
                if (progress < 1) requestAnimationFrame(tick);
                else el.textContent = parsed.raw;
            }

            requestAnimationFrame(tick);
        };

        if (!('IntersectionObserver' in window)) {
            statEls.forEach(run);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                run(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.35 });

        statEls.forEach((el) => observer.observe(el));
    }

    function initSoftParallax() {
        const heroImage = document.querySelector('.hero-bg-image');
        if (!heroImage || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        let ticking = false;
        window.addEventListener('scroll', () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => {
                const offset = Math.min(28, window.scrollY * 0.028);
                heroImage.style.transform = `translate3d(0, ${offset}px, 0) scale(1.03)`;
                ticking = false;
            });
        }, { passive: true });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        initHeaderActions();
        initFrontendMotion();
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
