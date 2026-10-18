<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $appName = App\Models\AppSetting::getValue('app_name', config('app.name', 'Swaad'));
        $appFavicon = App\Models\AppSetting::getValue('app_favicon', null);
        $frontendBackgroundImage = App\Models\AppSetting::getValue('frontend_background_image', null);
        $frontendBackgroundImageUrl = $frontendBackgroundImage
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($frontendBackgroundImage)
            : null;
        $primaryColor = App\Models\AppSetting::getValue('primary_color', '#2563EB');
        $secondaryColor = App\Models\AppSetting::getValue('secondary_color', '#111827');
        $googleMapsKey = $googleMapsApiKey ?? App\Models\AppSetting::getValue('google_maps_api_key', App\Models\AppSetting::getValue('google_maps_key', ''));
        $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();

        $heroTitle = App\Models\AppSetting::getValue('hero_title', 'Where do you want to order from?');
        $heroSubtitle = App\Models\AppSetting::getValue('hero_subtitle', 'Discover the best restaurants in your neighborhood');
        $heroLocationPlaceholder = App\Models\AppSetting::getValue('hero_location_placeholder', 'Enter delivery location');
        $heroSearchPlaceholder = App\Models\AppSetting::getValue('hero_search_placeholder', 'Search for restaurant, cuisine or dish');
        $heroSearchButtonText = App\Models\AppSetting::getValue('hero_search_button_text', 'Search');

        $quickFilterVegOnly = App\Models\AppSetting::getValue('quick_filter_veg_only', 'Veg only');
        $quickFilterUnder99 = App\Models\AppSetting::getValue('quick_filter_under_99', "Under {$currencySymbol} 99");
        $quickFilterUnder199 = App\Models\AppSetting::getValue('quick_filter_under_199', "Under {$currencySymbol} 199");
        $quickFilterBestsellers = App\Models\AppSetting::getValue('quick_filter_bestsellers', 'Bestsellers');

        // Counts are real, live data (HomeController::homepageStats()) -- not
        // admin-typed marketing placeholders. Only the labels stay
        // admin-configurable copy.
        $statRestaurantCount = $homepageStats['restaurant_count'] ?? '0';
        $statRestaurantLabel = App\Models\AppSetting::getValue('stat_restaurant_label', 'Partner Restaurants');
        $statOrderCount = $homepageStats['order_count'] ?? '0';
        $statOrderLabel = App\Models\AppSetting::getValue('stat_order_label', 'Orders Delivered');
        $statUserCount = $homepageStats['customer_count'] ?? '0';
        $statUserLabel = App\Models\AppSetting::getValue('stat_user_label', 'Happy Users');
        $statCityCount = $homepageStats['city_count'] ?? '0';
        $statCityLabel = App\Models\AppSetting::getValue('stat_city_label', 'Cities Covered');

    @endphp
    <title>{{ $appName }} - Order food from best restaurants near you</title>
    @include('partials.seo-meta', ['canonicalUrl' => route('home')])
    <link rel="icon" href="{{ $appFavicon ? \Illuminate\Support\Facades\Storage::disk('public')->url($appFavicon) : asset('favicon.ico') }}">
    
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></noscript>
    
    <!-- Font Awesome -->
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <!-- Google Fonts -- matches the Flutter app: Plus Jakarta Sans app-wide,
         Nunito Sans for home-feed body/card text (lib/main.dart:349,365-369;
         home_screen_production.dart:4509-4510). -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>
    
    {{-- Google Maps JS is loaded once by partials.frontend-header (shared
         across every page) -- this page's own initAutocomplete() hooks into
         that same load instead of loading the Maps API a second time, which
         Google's own JS throws a console warning for and can reset
         window.google mid-session. --}}

    <style>
        :root {
            --primary: {{ $primaryColor }};
            --secondary: {{ $secondaryColor }};
            --primary-rgb: {{ sscanf($primaryColor, '#%02x%02x%02x')[0] ?? 37 }}, {{ sscanf($primaryColor, '#%02x%02x%02x')[1] ?? 99 }}, {{ sscanf($primaryColor, '#%02x%02x%02x')[2] ?? 235 }};
            --secondary-rgb: {{ sscanf($secondaryColor, '#%02x%02x%02x')[0] ?? 17 }}, {{ sscanf($secondaryColor, '#%02x%02x%02x')[1] ?? 24 }}, {{ sscanf($secondaryColor, '#%02x%02x%02x')[2] ?? 39 }};
            --brand-gradient: linear-gradient(135deg, var(--primary), var(--secondary));
            --brand-gradient-hover: linear-gradient(135deg, color-mix(in srgb, var(--primary) 82%, #000), color-mix(in srgb, var(--secondary) 88%, #000));
            --brand-soft-shadow: 0 18px 40px rgba(var(--primary-rgb), 0.24);
            --frontend-bg-image: @if($frontendBackgroundImageUrl) url('{{ $frontendBackgroundImageUrl }}') @else none @endif;

            /* Matches the Flutter customer app's actual design tokens
               (lib/theme/brand_palette.dart, lib/main.dart) so the web
               experience feels like the same product. */
            --app-ink: #111827;
            --app-muted: #6B7280;
            --app-border: #E5E7EB;
            --app-bg: #FAFAFA;
            --app-success: #22C55E;
            --app-danger: #E53935;
            --app-card-radius: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Nunito Sans', sans-serif;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 8% 18%, rgba(var(--primary-rgb), 0.055), transparent 28%),
                radial-gradient(circle at 92% 12%, rgba(var(--secondary-rgb), 0.06), transparent 26%),
                #fff;
            color: #1C1C1C;
        }

        /* `perspective` used to live on `body`, but a `perspective` (like a
           `transform`) on any ancestor creates a new containing block for
           `position: fixed` descendants -- it silently turned the fixed
           navbar, offcanvas menu, and modals into elements positioned
           relative to `body` instead of the viewport, so they scrolled away
           with the page instead of staying put. Scoped instead to just the
           specific elements below whose children actually use a 3D
           translateZ/rotateX tilt effect. */
        .hero,
        .hero-content,
        .categories-section,
        .category-card,
        #restaurantsContainer {
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
            font-family: 'Plus Jakarta Sans', sans-serif;
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
            transition: all 0.2s;
        }

        .category-card:hover {
            transform: translateY(-4px);
        }

        .category-icon {
            width: 64px;
            height: 64px;
            background: #fff;
            border: 1px solid #F2F2F2;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            transition: all 0.2s;
        }

        .category-card:hover .category-icon {
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
        }

        .category-icon i {
            font-size: 26px;
            color: var(--primary);
        }

        .category-name {
            font-family: 'Nunito Sans', sans-serif;
            font-weight: 600;
            font-size: 12px;
            color: #1C1C1C;
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
            border-radius: var(--app-card-radius, 24px);
            border: 1px solid #F1F1F1;
            overflow: hidden;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.25s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            margin-bottom: 24px;
            position: relative;
        }

        .restaurant-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
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
            height: 210px;
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

        /* "Ad" sponsored-placement disclosure only -- kept regardless of app
           parity, this is an ad-disclosure requirement, not a design choice. */
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

        /* Circular logo overlay straddling the image/info seam, matching the
           app's _RestaurantListTileModern card. */
        .restaurant-logo-overlay {
            position: absolute;
            left: 14px;
            bottom: -28px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: white;
            border: 3px solid white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            z-index: 2;
        }

        .restaurant-logo-overlay img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .restaurant-info {
            padding: 38px 16px 16px;
        }

        .restaurant-name {
            font-family: 'Nunito Sans', sans-serif;
            font-size: 17px;
            font-weight: 900;
            color: #1C1C1C;
            margin-bottom: 4px;
        }

        .restaurant-cuisine {
            font-size: 13px;
            font-weight: 500;
            color: #686B78;
            margin-bottom: 8px;
        }

        .restaurant-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--app-success);
            font-size: 13px;
            font-weight: 700;
        }

        .delivery-time {
            font-size: 13px;
            font-weight: 500;
            color: #686B78;
        }

        .restaurant-closed-text {
            font-size: 13px;
            font-weight: 600;
            color: var(--app-danger);
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
            .section-title { font-size: 28px; }
        }

        /* Autocomplete Styles */
        .pac-container {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-top: 8px;
            font-family: 'Nunito Sans', sans-serif;
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
@include('partials.google-tag-manager')

@include('partials.frontend-header')

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
    @elseif($section['type'] === 'promotion_type_section')
        @include('partials.home-sections.promotion-section', ['section' => $section])
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

<!-- Footer -->
{{-- Same shared header+footer used on restaurant-detail.blade.php and every
     other public page now, for a consistent header across the whole site.
     The page's own "Delivery Zone" state (currentLat/currentLng/currentLocation
     below) is kept in sync with the shared header's zone drawer via
     window.onSwiggyZoneChanged(). --}}
@include('partials.frontend-footer')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
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

    // Google Places Autocomplete for the hero search bar. partials.frontend-header
    // loads the Google Maps JS API once (shared across every page) and calls
    // window.initGlobalPlacesAutocomplete when it's ready -- hook into that
    // same callback instead of loading the Maps API a second time here.
    function initAutocomplete() {
        const locationInput = document.getElementById('locationInput');
        if (!locationInput) return;

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
                saveLocationToStorage(currentLat, currentLng, currentLocation);
                searchRestaurants();
            }
        });
    }

    (function hookIntoSharedPlacesCallback() {
        const existingCallback = window.initGlobalPlacesAutocomplete;
        window.initGlobalPlacesAutocomplete = function() {
            if (typeof existingCallback === 'function') existingCallback();
            initAutocomplete();
        };
    })();

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
        // Keep partials.frontend-header's own "Delivery Zone" state (and its
        // nav button label) in sync -- it reads/writes userDeliveryZone, and
        // without this the header keeps showing "Select Zone" even after this
        // page successfully auto-detects or geocodes a location.
        localStorage.setItem('userDeliveryZone', JSON.stringify({ lat, lng, zone: location, zone_id: null }));
        if (typeof updateSwiggyNavZoneDisplay === 'function') {
            updateSwiggyNavZoneDisplay(location);
        }
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

    // partials.frontend-header's own "Delivery Zone" drawer calls
    // window.onSwiggyZoneChanged(zoneName, lat, lng, zoneId) after a zone is
    // picked, if defined -- this is that hook, keeping the hero search bar's
    // own location state (and the restaurant list) in sync with whatever the
    // shared header's drawer selects.
    window.onSwiggyZoneChanged = function(zoneName, lat, lng) {
        currentLat = lat ?? null;
        currentLng = lng ?? null;
        currentLocation = zoneName;
        const input = document.getElementById('locationInput');
        if (input) input.value = zoneName;
        saveLocationToStorage(currentLat, currentLng, currentLocation);
        searchRestaurants();
    };

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
                        <a href="/restaurants/${r.slug || r.id}" class="restaurant-card">
                            <div class="restaurant-img">
                                <img src="${r.image || 'https://placehold.co/400x300/E8E8E8/9C9C9C?text=No+Image'}" alt="${escapeHtml(r.name)}">
                                ${r.is_sponsored ? '<span class="promoted-badge">Ad</span>' : ''}
                                ${r.logo ? `<div class="restaurant-logo-overlay"><img src="${r.logo}" alt=""></div>` : ''}
                            </div>
                            <div class="restaurant-info">
                                <div class="restaurant-name">${escapeHtml(r.name)}</div>
                                <div class="restaurant-cuisine">${r.cuisine || 'Various Cuisines'}</div>
                                <div class="restaurant-meta">
                                    <div class="rating"><i class="fas fa-star me-1"></i> ${r.rating || '4.0'}</div>
                                    ${r.is_open === false
                                        ? '<div class="restaurant-closed-text">Closed</div>'
                                        : `<div class="delivery-time"><i class="far fa-clock me-1"></i> ${r.delivery_time || '30-40'} min</div>`}
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
        initFrontendMotion();
        // Auto-detect on first visit instead of leaving the generic
        // "no location" state until the user taps Detect manually.
        // detectUserLocation() already degrades gracefully (toast + reset
        // button state) if the user denies the permission prompt or
        // geolocation fails/isn't supported, so the page is never left in a
        // broken state -- it just stays in the same no-location state it
        // would've shown without this call.
        if (!loadSavedLocation()) {
            detectUserLocation();
        }
        loadRestaurants(true);
    });
</script>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
