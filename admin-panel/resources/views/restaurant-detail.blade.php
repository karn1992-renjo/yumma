{{-- resources/views/restaurant-detail.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $restaurant->name }} - Order Online | {{ $appName ?? config('app.name') }}</title>
    @php
        $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
        $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    @endphp
    @include('partials.seo-meta', [
        'metaTitle' => $restaurant->name,
        'metaDescription' => trim((string) $restaurant->description) !== ''
            ? \Illuminate\Support\Str::limit(trim($restaurant->description), 160)
            : trim($restaurant->name.' - order online from '.($restaurant->cuisine_text ?: 'a variety of cuisines').' in '.($restaurant->city ?: 'your city').'. Fast delivery and live order tracking.'),
        'metaImage' => $restaurant->banner_image ?: $restaurant->logo_image,
        'canonicalUrl' => route('restaurant.show', $restaurant->slug ?? $restaurant->id),
    ])

    @php
        $restaurantCityCanonical = $restaurant->city
            ? route('city.restaurants', \Illuminate\Support\Str::slug($restaurant->city))
            : null;
        $restaurantSchema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            'name' => $restaurant->name,
            'image' => $restaurant->banner_image ? \App\Services\MediaStorage::url($restaurant->banner_image) : (\App\Services\MediaStorage::url($restaurant->logo_image) ?: null),
            'servesCuisine' => $restaurant->cuisine_names ?: null,
            'url' => route('restaurant.show', $restaurant->slug ?? $restaurant->id),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $restaurant->address,
                'addressLocality' => $restaurant->city,
                'addressRegion' => $restaurant->state,
                'postalCode' => $restaurant->pincode,
                'addressCountry' => $restaurant->country_code ?: 'IN',
            ]),
            'geo' => ($restaurant->latitude && $restaurant->longitude) ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $restaurant->latitude,
                'longitude' => (float) $restaurant->longitude,
            ] : null,
            'aggregateRating' => ($restaurant->rating && $restaurant->total_ratings) ? [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $restaurant->rating,
                'reviewCount' => (int) $restaurant->total_ratings,
            ] : null,
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($restaurantSchema, JSON_UNESCAPED_SLASHES) !!}</script>
    @if($restaurantCityCanonical)
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => "Restaurants in {$restaurant->city}", 'item' => $restaurantCityCanonical],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $restaurant->name, 'item' => route('restaurant.show', $restaurant->slug ?? $restaurant->id)],
        ],
    ], JSON_UNESCAPED_SLASHES) !!}
    </script>
    @endif

    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Nunito+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: {{ $primaryColor ?? '#2563EB' }};
            --swiggy-orange: {{ $primaryColor ?? '#2563EB' }};
            --swiggy-dark: #02060c;
            --swiggy-muted: #686b78;
            --swiggy-green: #1ba672;
            --swiggy-veg-green: #0f8a65;
            --swiggy-nonveg-red: #e43b4f;
            --primary-dark: #e56d0d;
            --secondary: {{ $secondaryColor ?? '#111827' }};
            --success: #10B981;
            --warning: #F59E0B;
            --info: #3B82F6;
            --gray-50: #F8F9FA;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;

            /* Matches the Flutter customer app's actual design tokens,
               used by the elements redesigned to match it on this page. */
            --app-success: #22C55E;
            --app-danger: #E53935;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito Sans', sans-serif; background: var(--gray-50); color: var(--gray-900); overflow-x: hidden; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }

        .navbar { background: white; box-shadow: 0 1px 8px rgba(0,0,0,0.08); padding: 12px 0; position: sticky; top: 0; z-index: 1000; }
        .navbar-brand { font-size: 28px; font-weight: 800; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* Matches the app's banner: 240px, bottom-only rounded corners, no
           heavy dark overlay -- the circular icon buttons carry their own
           semi-transparent background instead. */
        .hero-section { position: relative; height: 240px; border-radius: 0 0 28px 28px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); overflow: hidden; }
        .hero-section img { width: 100%; height: 100%; object-fit: cover; }
        .hero-icon-btn { position: absolute; top: 16px; width: 38px; height: 38px; border-radius: 50%; background: rgba(0,0,0,0.45); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; z-index: 5; }
        .hero-icon-btn.back { left: 16px; }
        .hero-icon-btn.share { right: 16px; }

        .restaurant-info-card { background: white; border-radius: 24px; margin-top: -60px; position: relative; z-index: 10; padding: 24px 32px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .restaurant-logo { width: 100px; height: 100px; border-radius: 20px; object-fit: cover; border: 4px solid white; box-shadow: 0 4px 16px rgba(0,0,0,0.1); margin-top: -70px; }
        .rating-badge { background: var(--app-success); color: white; padding: 6px 12px; border-radius: 30px; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .info-chip { background: var(--gray-100); padding: 8px 16px; border-radius: 40px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; color: var(--gray-700); }

        .nav-tabs-custom { background: white; border-bottom: 1px solid var(--gray-200); position: sticky; top: 76px; z-index: 99; }
        .nav-tabs-custom .nav-link { border: none; padding: 16px 24px; font-weight: 600; color: var(--gray-600); transition: all 0.3s; position: relative; }
        .nav-tabs-custom .nav-link:hover { color: var(--primary); }
        .nav-tabs-custom .nav-link.active { color: var(--primary); background: transparent; }
        .nav-tabs-custom .nav-link.active::after { content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 3px; background: var(--primary); border-radius: 3px 3px 0 0; }

        .menu-section { padding: 32px 0 64px; }
        .menu-category { margin-bottom: 48px; scroll-margin-top: 120px; }
        /* Grid of cards, matching the app's own Wrap-grid menu layout --
           also what keeps a card's image from stretching edge-to-edge on
           wide desktop viewports now that the sidebar cart panel is gone. */
        .menu-items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
        /* Menu item card -- matches the app's RestaurantMenuGridCard: a
           full-width image on top, no heavy card border, text block below. */
        .menu-item-card { background: white; border-radius: 16px; overflow: hidden; transition: box-shadow 0.2s ease; cursor: default; border: 1px solid #F1F1F1; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
        .menu-item-card:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
        .menu-item-img-wrap { position: relative; width: 100%; aspect-ratio: 3/2; overflow: hidden; }
        .menu-item-img { width: 100%; height: 100%; object-fit: cover; }
        .menu-item-body { padding: 14px 16px 16px; position: relative; }
        .food-type-badge { width: 16px; height: 16px; border: 1.5px solid; border-radius: 3px; display: inline-flex; align-items: center; justify-content: center; margin-right: 6px; flex-shrink: 0; }
        .food-type-badge .dot { width: 8px; height: 8px; border-radius: 50%; }
        .food-type-badge.veg { border-color: var(--app-success); }
        .food-type-badge.veg .dot { background: var(--app-success); }
        .food-type-badge.non-veg,
        .food-type-badge.egg { border-color: var(--app-danger); }
        .food-type-badge.non-veg .dot,
        .food-type-badge.egg .dot { background: var(--app-danger); }
        .veg-badge, .non-veg-badge { display: none; } /* superseded by .food-type-badge */

        /* Circular filled Add button, matching the app's _GridAddButton. */
        .add-btn { position: absolute; top: -20px; right: 16px; width: 36px; height: 36px; border-radius: 50%; background: var(--app-success); border: 3px solid white; color: white; display: flex; align-items: center; justify-content: center; font-size: 14px; box-shadow: 0 4px 10px rgba(0,0,0,0.18); transition: transform 0.15s ease; padding: 0; }
        .add-btn:hover { transform: scale(1.06); background: var(--app-success); color: white; }

        /* Persistent cart bar -- replaces the old desktop sidebar, mobile
           floating button, and mobile off-canvas sidebar with one component
           matching the app's bottomNavigationBar cart bar. */
        .cart-bar { position: fixed; left: 14px; right: 14px; bottom: 14px; max-width: 480px; margin: 0 auto; background: white; border-radius: 22px; box-shadow: 0 8px 24px rgba(109,40,217,0.08), 0 4px 16px rgba(0,0,0,0.12); padding: 10px 14px; display: none; align-items: center; gap: 12px; z-index: 1040; }
        .cart-bar.show { display: flex; }
        .cart-bar-logo { width: 48px; height: 48px; border-radius: 16px; object-fit: cover; flex-shrink: 0; background: var(--gray-100); }
        .cart-bar-info { flex-grow: 1; min-width: 0; }
        .cart-bar-name { font-weight: 800; font-size: 14px; color: var(--gray-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cart-bar-meta { font-size: 12px; color: var(--gray-500); font-weight: 600; }
        .cart-bar-cta { background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, #000)); color: white; border: none; padding: 10px 18px; border-radius: 16px; font-weight: 700; font-size: 13px; white-space: nowrap; flex-shrink: 0; }

        @media (max-width: 991.98px) {
            .cart-bar { bottom: 102px; }
        }

        .review-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 16px; border: 1px solid var(--gray-200); }
        .review-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 18px; }
        .rating-stars { color: #FFB800; }

        .similar-restaurant-card { background: white; border-radius: 16px; overflow: hidden; transition: all 0.3s; cursor: pointer; border: 1px solid var(--gray-200); }
        .similar-restaurant-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .similar-restaurant-img { width: 100%; height: 160px; object-fit: cover; }
        .offer-rail { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(280px, 360px); gap: 14px; overflow-x: auto; padding: 2px 2px 10px; scroll-snap-type: x proximity; }
        .offer-rail::-webkit-scrollbar { height: 4px; }
        .offer-card-premium { position: relative; min-height: 138px; border: 1px solid #f3d5bd; border-radius: 18px; overflow: hidden; background: linear-gradient(135deg, #fffaf4 0%, #fff 52%, #fff1e5 100%); box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08); cursor: default; scroll-snap-align: start; }
        .offer-card-premium::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 5px; background: linear-gradient(180deg, var(--primary), var(--secondary)); }
        .offer-card-premium::after { content: ''; position: absolute; top: 18px; bottom: 18px; right: 82px; border-right: 1px dashed #f2b985; }
        .offer-content { padding: 18px 112px 18px 20px; position: relative; z-index: 1; }
        .offer-tag { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 999px; background: #fff; border: 1px solid #fed7aa; color: #c2410c; font-size: 12px; font-weight: 800; letter-spacing: .02em; }
        .offer-code-pill { position: absolute; top: 50%; right: 18px; transform: translateY(-50%); width: 72px; min-height: 72px; border-radius: 50%; background: #111827; color: #fff; display: flex; align-items: center; justify-content: center; text-align: center; padding: 8px; font-size: 11px; font-weight: 900; letter-spacing: .04em; box-shadow: 0 12px 28px rgba(17, 24, 39, .24); }
        .offer-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; color: #6b7280; font-size: 12px; font-weight: 700; }
        .offer-meta span { display: inline-flex; align-items: center; gap: 5px; }

        .toast-notification { position: fixed; bottom: 100px; right: 24px; background: var(--gray-800); color: white; padding: 12px 20px; border-radius: 12px; z-index: 1100; animation: slideIn 0.3s ease; display: flex; align-items: center; gap: 12px; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .loading-spinner { display: inline-block; width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .restaurant-info-card { padding: 16px; margin-top: -40px; }
            .restaurant-logo { width: 70px; height: 70px; margin-top: -50px; }
            .hero-section { height: 200px; }
            .nav-tabs-custom .nav-link { padding: 12px 16px; font-size: 14px; }
        }
    </style>
@include('partials.public-blade-polish')
<style>
    body {
        font-family: 'Nunito Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        background: #ffffff !important;
        color: #02060c !important;
    }
</style>
</head>
<body>

<!-- Unified Frontend Header -->
@include('partials.frontend-header')

@php
    // Safe banner URL with null checks
    $restaurantBannerUrl = 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&h=400&fit=crop';
    if ($restaurant->banner_image) {
        $restaurantBannerUrl = \App\Services\MediaStorage::url($restaurant->banner_image);
    } elseif ($restaurant->cover_image) {
        $restaurantBannerUrl = \App\Services\MediaStorage::url($restaurant->cover_image);
    }
    
    // Safe logo URL
    $restaurantLogoUrl = null;
    if ($restaurant->logo_image) {
        $restaurantLogoUrl = \App\Services\MediaStorage::url($restaurant->logo_image);
    }
    
    // Safe weekly schedule handling
    $weekSchedule = $restaurant->weekly_schedule;
    $todayName = \Carbon\Carbon::now()->format('l');
    $todaySchedule = (is_array($weekSchedule) && isset($weekSchedule[$todayName])) ? $weekSchedule[$todayName] : null;
    $openTimeLabel = null;
    $closeTimeLabel = null;
    
    if ($todaySchedule && is_array($todaySchedule)) {
        $openTimeLabel = isset($todaySchedule['open']) && $todaySchedule['open'] 
            ? \Carbon\Carbon::parse($todaySchedule['open'])->format('h:i A') 
            : null;
        $closeTimeLabel = isset($todaySchedule['close']) && $todaySchedule['close'] 
            ? \Carbon\Carbon::parse($todaySchedule['close'])->format('h:i A') 
            : null;
    }
    
    // Safe cuisine display
    $cuisineDisplay = !empty($restaurant->cuisine_names)
        ? implode(' • ', $restaurant->cuisine_names)
        : 'Various Cuisines';
    
    // Safe rating
    $rating = $restaurant->rating ?? 4.5;
    $totalReviews = $restaurant->reviews_count ?? 0;
    
    // Restaurant hours display
    $openStatusText = $restaurant->is_open ? 'Open Now' : 'Closed';
    $openStatusClass = $restaurant->is_open ? '#D1FAE5' : '#FEE2E2';
    $openStatusTextClass = $restaurant->is_open ? '#065F46' : '#991B1B';
    
    // Group menu items by category safely
    $menuItemsByCategory = collect();
    if ($restaurant->menuItems && $restaurant->menuItems->count()) {
        $menuItemsByCategory = $restaurant->menuItems
            ->where('is_available', true)
            ->where('is_scheduled_available', true)
            ->groupBy(function($item) {
                return optional($item->category)->name ?? 'Uncategorized';
            });
    }
@endphp

<!-- Hero Section -->
<div class="hero-section">
    <img src="{{ $restaurantBannerUrl }}" alt="{{ $restaurant->name }}">
    <a href="javascript:void(0)" onclick="history.back()" class="hero-icon-btn back" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
    <a href="javascript:void(0)" onclick="shareRestaurant()" class="hero-icon-btn share" aria-label="Share"><i class="fas fa-share-alt"></i></a>
</div>

<div class="container">
    <!-- Restaurant Info Card -->
    <div class="restaurant-info-card">
        <div class="row align-items-end">
            <div class="col-auto">
                @if($restaurantLogoUrl)
                    <img src="{{ $restaurantLogoUrl }}" alt="{{ $restaurant->name }}" class="restaurant-logo">
                @else
                    <div class="restaurant-logo bg-gradient d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-store fa-3x text-white"></i>
                    </div>
                @endif
            </div>
            <div class="col">
                <h1 class="fw-bold mb-2">{{ $restaurant->name }}</h1>
                <p class="text-muted mb-3">{{ $cuisineDisplay }}</p>
                <div class="d-flex flex-wrap gap-3">
                    <div class="rating-badge" onclick="openReviewsTab()">
                        <i class="fas fa-star"></i>
                        <span>{{ number_format($rating, 1) }}</span>
                        <span style="font-size: 12px;">({{ $totalReviews }}+ ratings)</span>
                    </div>
                    @php
                        $amountForOne = $restaurant->amountForOne();
                    @endphp
                    @if($amountForOne !== null && $amountForOne > 0)
                        <div class="info-chip">
                            <i class="fas fa-rupee-sign"></i>
                            <span>{{ $currencySymbol }}{{ number_format($amountForOne, App\Models\AppSetting::currencyDecimals()) }} for one</span>
                        </div>
                    @endif
                    <div class="info-chip">
                        <i class="far fa-clock"></i> 
                        <span>{{ $restaurant->delivery_time ?? '30-40' }} min</span>
                    </div>
                    <div class="info-chip">
                        <i class="fas fa-map-marker-alt"></i> 
                        <span>{{ $restaurant->city ?? 'Your City' }}</span>
                    </div>
                    @if($restaurant->is_pure_veg)
                        <div class="info-chip" style="background:#ECFDF3;color:#067647;">
                            <i class="fas fa-leaf"></i>
                            <span>Pure Veg</span>
                        </div>
                    @endif
                    <div id="distanceChip" class="info-chip d-none">
                        <i class="fas fa-location-arrow"></i> 
                        <span>Calculating distance...</span>
                    </div>
                    @if($restaurant->is_open)
                        <div class="info-chip" style="background: #D1FAE5; color: #065F46;">
                            <i class="fas fa-circle" style="font-size: 8px;"></i> 
                            <span>Open Now</span>
                        </div>
                    @else
                        <div class="info-chip" style="background: #FEE2E2; color: #991B1B;">
                            <i class="fas fa-circle" style="font-size: 8px;"></i> 
                            <span>Closed</span>
                        </div>
                    @endif
                    @if($openTimeLabel && $closeTimeLabel)
                        <div class="info-chip">
                            <i class="far fa-clock"></i> 
                            <span>{{ $restaurant->is_open ? 'Open until '.$closeTimeLabel : 'Opens at '.$openTimeLabel }}</span>
                        </div>
                    @elseif($openTimeLabel)
                        <div class="info-chip">
                            <i class="far fa-clock"></i> 
                            <span>Opens at {{ $openTimeLabel }}</span>
                        </div>
                    @elseif($closeTimeLabel)
                        <div class="info-chip">
                            <i class="far fa-clock"></i> 
                            <span>Closes at {{ $closeTimeLabel }}</span>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-auto">
                <div class="text-end">
                    {{-- Delivery fee removed --}}
                </div>
            </div>
        </div>
    </div>
    
    @if(!$restaurant->is_open)
        <div class="alert alert-warning rounded-4 mt-4">
            <i class="fas fa-clock me-2"></i>
            <strong>Restaurant is currently closed.</strong>
            @if($openTimeLabel && $closeTimeLabel)
                Orders can only be placed between {{ $openTimeLabel }} and {{ $closeTimeLabel }}.
            @elseif($openTimeLabel)
                Opens at {{ $openTimeLabel }}.
            @elseif($closeTimeLabel)
                Closes at {{ $closeTimeLabel }}.
            @else
                Orders can only be placed when the restaurant reopens.
            @endif
        </div>
    @endif

    @if(!empty($activePromos) && $activePromos->isNotEmpty())
        <div class="bg-white rounded-4 p-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-0">Offers For You</h5>
                    <small class="text-muted">Tap a code at checkout to unlock savings</small>
                </div>
                <span class="badge bg-light text-dark rounded-pill">{{ $activePromos->count() }} live</span>
            </div>
            <div class="offer-rail">
                @foreach($activePromos as $promo)
                    <div class="offer-card-premium">
                        <div class="offer-content">
                            <div class="offer-tag">
                                <i class="fas fa-bolt"></i>
                                {{ $promo->discount_type === 'percentage' ? rtrim(rtrim($promo->discount_value, '0'), '.') . '% OFF' : $currencySymbol . number_format((float) $promo->discount_value, $currencyDecimals) . ' OFF' }}
                            </div>
                            <h6 class="fw-black mt-3 mb-1">{{ $promo->title ?: 'Special restaurant offer' }}</h6>
                            <p class="small text-muted mb-0">{{ \Illuminate\Support\Str::limit($promo->description ?: 'Apply this code during checkout and save on your order.', 78) }}</p>
                            <div class="offer-meta">
                                @if((float) $promo->min_order_amount > 0)
                                    <span><i class="fas fa-bag-shopping"></i> Min {{ $currencySymbol }}{{ number_format((float) $promo->min_order_amount, $currencyDecimals) }}</span>
                                @endif
                                @if($promo->end_date)
                                    <span><i class="far fa-clock"></i> Ends {{ \Carbon\Carbon::parse($promo->end_date)->format('M j') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="offer-code-pill">{{ $promo->code }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($weekSchedule) && is_array($weekSchedule) && count($weekSchedule))
        <div class="bg-white rounded-4 p-4 mt-4">
            <h5 class="fw-bold mb-3">Weekly Kitchen Hours</h5>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                @foreach($weekSchedule as $day => $hours)
                    <div class="col">
                        <div class="d-flex justify-content-between align-items-center p-3 border rounded-3 @if($day === $todayName) bg-primary text-white @else bg-light @endif">
                            <span>{{ $day }}</span>
                            <span>
                                @if(is_array($hours) && isset($hours['open']) && isset($hours['close']) && $hours['open'] && $hours['close'])
                                    {{ \Carbon\Carbon::parse($hours['open'])->format('h:i A') }} - {{ \Carbon\Carbon::parse($hours['close'])->format('h:i A') }}
                                @else
                                    Closed
                                @endif
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Navigation Tabs -->
    <div class="nav-tabs-custom">
        <div class="container">
            <ul class="nav" id="restaurantTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#menu" type="button" role="tab">
                        <i class="fas fa-utensils me-2"></i>Menu
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                        <i class="fas fa-star me-2"></i>Reviews
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                        <i class="fas fa-info-circle me-2"></i>Restaurant Info
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#similar" type="button" role="tab">
                        <i class="fas fa-store me-2"></i>Similar Places
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Menu Tab -->
        <div class="tab-pane fade show active" id="menu" role="tabpanel">
            <div class="menu-section">
                <div class="row">
                    <div class="col-12">
                        <!-- Category Navigation -->
                        @if($menuItemsByCategory->count() > 0)
                            <div class="category-nav mb-4" style="position: sticky; top: 130px; background: var(--gray-50); padding: 12px 0; z-index: 9;">
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($menuItemsByCategory->keys() as $categoryName)
                                        <a href="#category-{{ \Illuminate\Support\Str::slug($categoryName) }}" class="btn btn-outline-secondary rounded-pill btn-sm category-link">
                                            {{ $categoryName }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Menu Items -->
                        @if($menuItemsByCategory->count() > 0)
                            @foreach($menuItemsByCategory as $categoryName => $items)
                                <div id="category-{{ \Illuminate\Support\Str::slug($categoryName) }}" class="menu-category">
                                    <h3 class="fw-bold mb-3">{{ $categoryName }}</h3>
                                    <div class="menu-items-grid">
                                    @foreach($items as $item)
                                        @php
                                            $foodType = $item->food_type ?: ($item->is_veg ? 'veg' : 'non_veg');
                                            $foodTypeClass = $foodType === 'veg' ? 'veg' : ($foodType === 'egg' ? 'egg' : 'non-veg');
                                        @endphp
                                        <div class="menu-item-card" data-item-name="{{ strtolower($item->name) }}">
                                            <div class="menu-item-img-wrap">
                                                @if($item->image)
                                                    <img src="{{ \App\Services\MediaStorage::url($item->image) }}" alt="{{ $item->name }}" class="menu-item-img">
                                                @else
                                                    <div class="menu-item-img bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-utensils fa-2x text-muted"></i>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="menu-item-body">
                                                <button class="add-btn" data-variants='@json($item->variants ?? [])' data-addons='@json($item->add_ons ?? [])' onclick="addToCart(this, {{ $item->id }}, '{{ addslashes($item->name) }}', {{ number_format((float) ($item->discounted_price ?? $item->price), App\Models\AppSetting::currencyDecimals(), '.', '') }})" aria-label="Add {{ $item->name }} to cart">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <div class="d-flex align-items-start gap-2 mb-1">
                                                    <span class="food-type-badge {{ $foodTypeClass }}"><span class="dot"></span></span>
                                                    <h5 class="fw-bold mb-0" style="font-size: 16px;">{{ $item->name }}</h5>
                                                </div>
                                                <div class="d-flex flex-wrap gap-1 mb-1">
                                                    @if($item->is_recommended)
                                                        <span class="badge bg-success">Recommended</span>
                                                    @endif
                                                    @foreach([
                                                        'is_bestseller' => 'Bestseller',
                                                        'is_new' => 'New',
                                                        'is_spicy' => 'Spicy',
                                                        'is_combo' => 'Combo',
                                                    ] as $flag => $label)
                                                        @if($item->{$flag})
                                                            <span class="badge bg-light text-dark">{{ $label }}</span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                                @if($item->description)
                                                    <p class="text-muted small mb-2">{{ Illuminate\Support\Str::limit($item->description, 80) }}</p>
                                                @endif
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold mb-0 text-primary" style="font-size: 15px;">
                                                        {{ $currencySymbol }}{{ number_format($item->discounted_price ?? $item->price, App\Models\AppSetting::currencyDecimals()) }}
                                                    </span>
                                                    @if(isset($item->discounted_price) && $item->discounted_price && $item->discounted_price < $item->price)
                                                        <span class="text-muted text-decoration-line-through small">
                                                            {{ $currencySymbol }}{{ number_format($item->price, App\Models\AppSetting::currencyDecimals()) }}
                                                        </span>
                                                        <span class="badge bg-warning">
                                                            {{ round((($item->price - $item->discounted_price) / $item->price) * 100) }}% OFF
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-5">
                                <i class="fas fa-utensils fa-4x text-muted mb-3"></i>
                                <h5>No menu items available</h5>
                                <p class="text-muted">Check back later for updated menu</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews Tab -->
        <div class="tab-pane fade" id="reviews" role="tabpanel">
            <div class="py-4">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="bg-white rounded-4 p-4 mb-4">
                            <div class="row align-items-center">
                                <div class="col-auto text-center">
                                    <div class="display-1 fw-bold text-primary">{{ number_format($rating, 1) }}</div>
                                    <div class="rating-stars mb-1">
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="fas fa-star {{ $i <= floor($rating) ? 'text-warning' : 'text-muted' }}"></i>
                                        @endfor
                                    </div>
                                    <div class="text-muted small">{{ $totalReviews }} ratings</div>
                                </div>
                                <div class="col">
                                    @php
                                        $ratingBreakdown = $restaurant->rating_breakdown ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
                                        $maxRating = max(array_sum($ratingBreakdown), 1);
                                    @endphp
                                    @foreach([5,4,3,2,1] as $star)
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="small" style="width:30px;">{{ $star }}★</span>
                                            <div class="flex-grow-1 bg-light rounded-pill" style="height:6px;">
                                                <div class="bg-warning rounded-pill" style="width: {{ ($ratingBreakdown[$star] ?? 0) / $maxRating * 100 }}%; height:6px;"></div>
                                            </div>
                                            <span class="small text-muted">{{ $ratingBreakdown[$star] ?? 0 }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sample Reviews Section -->
                        <div class="bg-white rounded-4 p-4">
                            <h5 class="fw-bold mb-3">Customer Reviews</h5>
                            @if($restaurant->reviews && $restaurant->reviews->count())
                                @foreach($restaurant->reviews->take(5) as $review)
                                    <div class="review-card">
                                        <div class="d-flex gap-3">
                                            <div class="review-avatar">
                                                {{ substr($review->user->name ?? 'U', 0, 1) }}
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="fw-bold mb-0">{{ $review->user->name ?? 'Anonymous User' }}</h6>
                                                        <div class="rating-stars small">
                                                            @for($i = 1; $i <= 5; $i++)
                                                                <i class="fas fa-star {{ $i <= ($review->rating ?? 0) ? 'text-warning' : 'text-muted' }}"></i>
                                                            @endfor
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">{{ $review->created_at->diffForHumans() ?? '' }}</small>
                                                </div>
                                                <p class="mb-0">{{ $review->comment ?? 'No comment provided.' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center py-4">
                                    <i class="fas fa-comment fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No reviews yet</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Tab -->
        <div class="tab-pane fade" id="info" role="tabpanel">
            <div class="py-4">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="bg-white rounded-4 p-4 mb-4">
                            <h5 class="fw-bold mb-3">About this restaurant</h5>
                            <p class="text-muted">{{ $restaurant->description ?? 'We serve authentic and delicious food made with fresh ingredients. Our restaurant is committed to providing the best dining experience with quality food and excellent service.' }}</p>
                        </div>
                        <div class="bg-white rounded-4 p-4 mb-4">
                            <h5 class="fw-bold mb-3">Contact Information</h5>
                            <div class="d-flex flex-column gap-3">
                                <div>
                                    <i class="fas fa-phone-alt text-primary me-3" style="width: 24px;"></i>
                                    {{ $restaurant->phone ?? 'N/A' }}
                                </div>
                                <div>
                                    <i class="fas fa-envelope text-primary me-3" style="width: 24px;"></i>
                                    {{ $restaurant->email ?? 'N/A' }}
                                </div>
                                <div>
                                    <i class="fas fa-map-marker-alt text-primary me-3" style="width: 24px;"></i>
                                    {{ $restaurant->address ?? 'Address not available' }}
                                    @if($restaurant->city || $restaurant->state)
                                        , {{ $restaurant->city ?? '' }} {{ $restaurant->state ?? '' }} - {{ $restaurant->pincode ?? '' }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Similar Tab -->
        <div class="tab-pane fade" id="similar" role="tabpanel">
            <div class="py-4">
                <div class="row g-4">
                    @forelse($similarRestaurants ?? [] as $similar)
                        <div class="col-md-6 col-lg-3">
                            <div class="similar-restaurant-card" onclick="window.location.href='{{ route('restaurant.show', $similar->slug ?? $similar->id) }}'">
                                @php
                                    $similarImage = $similar->logo_image
                                        ? \App\Services\MediaStorage::url($similar->logo_image)
                                        : 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=300&h=160&fit=crop';
                                    $similarCuisine = !empty($similar->cuisine_names)
                                        ? implode(', ', array_slice($similar->cuisine_names, 0, 3))
                                        : 'Various';
                                @endphp
                                <img src="{{ $similarImage }}" class="similar-restaurant-img" alt="{{ $similar->name }}">
                                <div class="p-3">
                                    <h6 class="fw-bold mb-1">{{ $similar->name }}</h6>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="rating-badge" style="background: var(--success); font-size:11px; padding:2px 8px;">
                                            {{ number_format($similar->rating ?? 4.5, 1) }}
                                        </span>
                                        <span class="text-muted small">{{ $similar->delivery_time ?? '30' }} min</span>
                                    </div>
                                    <div class="text-muted small">{{ $similarCuisine }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No similar restaurants found</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Persistent Cart Bar -- matches the app's bottomNavigationBar cart bar:
     appears once the cart has items, shows restaurant + item count + total,
     taps straight through to the existing checkout flow. Replaces the old
     desktop sidebar + mobile floating button + mobile off-canvas sidebar. -->
<div class="cart-bar" id="cartBar">
    @if($restaurantLogoUrl)
        <img src="{{ $restaurantLogoUrl }}" alt="" class="cart-bar-logo">
    @else
        <div class="cart-bar-logo d-flex align-items-center justify-content-center"><i class="fas fa-store text-muted"></i></div>
    @endif
    <div class="cart-bar-info">
        <div class="cart-bar-name">{{ $restaurant->name }}</div>
        <div class="cart-bar-meta" id="cartBarMeta">0 items</div>
    </div>
    <button class="cart-bar-cta" onclick="window.location.href = '{{ route('cart.index') }}?restaurant_id={{ $restaurant->id }}'">
        View Cart <i class="fas fa-chevron-right ms-1"></i>
    </button>
</div>

<script>
    @php
        $activeTaxes = App\Models\TaxSetting::getActiveTaxes();
        $totalTaxRate = $activeTaxes->sum('rate');
        $taxLabel = $activeTaxes->count()
            ? $activeTaxes->pluck('name')->implode(', ') . ' (' . $totalTaxRate . '%)'
            : 'Taxes';
    @endphp
    const restaurantIsOpen = @json($restaurant->is_open);
    const restaurantId = @json($restaurant->id);
    const currencySymbol = '{{ $currencySymbol }}';
    window.currencySymbol = currencySymbol;
    window.currencyDecimals = {{ $currencyDecimals }};
    const cartTaxRate = {{ $totalTaxRate ?: 0 }};
    const cartTaxLabel = @json($taxLabel);
    
    // Cart Management
    let cart = [];
    
    try {
        const savedCart = localStorage.getItem('cart_' + restaurantId);
        if (savedCart) cart = JSON.parse(savedCart);
    } catch(e) { 
        localStorage.removeItem('cart_' + restaurantId); 
        cart = []; 
    }
    
    function saveCart() {
        localStorage.setItem('cart_' + restaurantId, JSON.stringify(cart));
        updateCartUI();
        if (window.updateHeaderCartCount) window.updateHeaderCartCount();
    }
    
    function addToCart(button, id, name, price) {
        if (!restaurantIsOpen) {
            showToast('This restaurant is currently closed. Orders cannot be placed until it opens.', 'error');
            return;
        }
        var variants = safeJson(button?.dataset?.variants, []);
        var addOns = safeJson(button?.dataset?.addons, []);
        var selectedVariant = chooseVariant(variants);
        if (selectedVariant === false) return;
        var selectedAddOns = chooseAddOns(addOns);
        if (selectedAddOns === false) return;
        var optionPrice = (Number(selectedVariant?.price) || 0) + selectedAddOns.reduce(function(sum, item) { return sum + (Number(item.price) || 0); }, 0);
        var finalPrice = Number(price) + optionPrice;
        var optionKey = JSON.stringify({ variant: selectedVariant?.name || '', add_ons: selectedAddOns.map(function(item) { return item.name; }) });
        var existingItem = cart.find(function(item) { return item.id === id && item.option_key === optionKey; });
        if (existingItem) {
            existingItem.quantity++;
        } else {
            cart.push({ id: id, name: name, price: finalPrice, base_price: Number(price), quantity: 1, selected_variant: selectedVariant, selected_add_ons: selectedAddOns, option_key: optionKey });
        }
        saveCart();
        showToast(name + ' added to cart!', 'success');
        
        if(button) {
            var originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(function() {
                button.innerHTML = originalHtml;
            }, 1000);
        }
    }

    function safeJson(value, fallback) {
        try { return value ? JSON.parse(value) : fallback; } catch (e) { return fallback; }
    }

    function chooseVariant(variants) {
        if (!Array.isArray(variants) || variants.length === 0) return null;
        var label = '';
        for (var i = 0; i < variants.length; i++) {
            label += (i + 1) + '. ' + variants[i].name + (Number(variants[i].price || 0) ? ' (+' + window.currencySymbol + Number(variants[i].price).toFixed(window.currencyDecimals) + ')' : '') + '\n';
        }
        var answer = prompt('Choose size / quantity:\n' + label, '1');
        if (answer === null) return false;
        var selectedIndex = Math.max(0, parseInt(answer, 10) - 1);
        return variants[selectedIndex] || variants[0];
    }

    function chooseAddOns(addOns) {
        if (!Array.isArray(addOns) || addOns.length === 0) return [];
        var label = '';
        for (var i = 0; i < addOns.length; i++) {
            label += (i + 1) + '. ' + addOns[i].name + (Number(addOns[i].price || 0) ? ' (+' + window.currencySymbol + Number(addOns[i].price).toFixed(window.currencyDecimals) + ')' : '') + '\n';
        }
        var answer = prompt('Add extras? Enter comma-separated numbers or leave blank:\n' + label, '');
        if (answer === null) return false;
        var selectedIndexes = answer.split(',').map(function(value) { return parseInt(value.trim(), 10) - 1; });
        var selected = [];
        for (var i = 0; i < selectedIndexes.length; i++) {
            if (selectedIndexes[i] >= 0 && selectedIndexes[i] < addOns.length) {
                selected.push(addOns[selectedIndexes[i]]);
            }
        }
        return selected;
    }
    
    function updateCartUI() {
        var subtotal = 0, itemCount = 0;
        for (var i = 0; i < cart.length; i++) {
            subtotal += (cart[i].price || 0) * (cart[i].quantity || 0);
            itemCount += (cart[i].quantity || 0);
        }
        var tax = subtotal * (cartTaxRate / 100);
        var total = subtotal + tax;

        var bar = document.getElementById('cartBar');
        var meta = document.getElementById('cartBarMeta');
        if (bar) {
            if (cart.length === 0) {
                bar.classList.remove('show');
            } else {
                bar.classList.add('show');
                if (meta) {
                    meta.innerText = itemCount + (itemCount === 1 ? ' item' : ' items') + ' · ' + window.currencySymbol + total.toFixed(window.currencyDecimals);
                }
            }
        }
    }


    function shareRestaurant() {
        var shareData = { title: document.title, url: window.location.href };
        if (navigator.share) {
            navigator.share(shareData).catch(function() {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(window.location.href);
            showToast('Link copied to clipboard', 'info');
        }
    }

    function openReviewsTab() {
        var trigger = document.querySelector('[data-bs-target="#reviews"]');
        if (trigger && window.bootstrap) {
            new bootstrap.Tab(trigger).show();
            trigger.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.background = type === 'success' ? '#10B981' : (type === 'error' ? '#EF4444' : '#3B82F6');
        toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')) + ' me-2"></i><span>' + escapeHtml(message) + '</span>';
        document.body.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function() { toast.remove(); }, 300);
        }, 3000);
    }
    
    function escapeHtml(text) { 
        if(!text) return ''; 
        var div = document.createElement('div'); 
        div.textContent = text; 
        return div.innerHTML; 
    }

    // Distance calculation
    var restaurantLat = parseFloat('{{ $restaurant->latitude ?? 0 }}');
    var restaurantLng = parseFloat('{{ $restaurant->longitude ?? 0 }}');

    function updateDistanceChip(text) {
        var chip = document.getElementById('distanceChip');
        if (!chip) return;
        chip.classList.remove('d-none');
        var span = chip.querySelector('span');
        if (span) span.innerText = text;
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        function toRad(value) { return value * Math.PI / 180; }
        var R = 6371;
        var dLat = toRad(lat2 - lat1);
        var dLon = toRad(lon2 - lon1);
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) + 
                  Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * 
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    function loadDistanceFromLocation() {
        if (!restaurantLat || !restaurantLng || !navigator.geolocation) {
            updateDistanceChip('Location unavailable');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(position) {
                var distance = calculateDistance(position.coords.latitude, position.coords.longitude, restaurantLat, restaurantLng);
                updateDistanceChip(distance.toFixed(1) + ' km away');
            }, 
            function() {
                updateDistanceChip('Location unavailable');
            }, 
            { timeout: 5000 }
        );
    }

    // Category navigation
    var categoryLinks = document.querySelectorAll('.category-link');
    for (var i = 0; i < categoryLinks.length; i++) {
        categoryLinks[i].addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(this.getAttribute('href'));
            if(target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }
    
    // Initialize
    loadDistanceFromLocation();
    updateCartUI();
</script>
<!-- Unified Frontend Footer -->
@include('partials.frontend-footer')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
