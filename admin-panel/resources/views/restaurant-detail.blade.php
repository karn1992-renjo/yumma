{{-- resources/views/restaurant-detail.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $restaurant->name }} - Order Online | {{ $appName ?? config('app.name') }}</title>
    @php
        $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');
        $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    @endphp
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: {{ $primaryColor ?? '#EF4F5F' }};
            --primary-dark: {{ $primaryDark ?? '#E03546' }};
            --secondary: {{ $secondaryColor ?? '#FF8C42' }};
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--gray-50); color: var(--gray-900); overflow-x: hidden; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }

        .navbar { background: white; box-shadow: 0 1px 8px rgba(0,0,0,0.08); padding: 12px 0; position: sticky; top: 0; z-index: 1000; }
        .navbar-brand { font-size: 28px; font-weight: 800; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .hero-section { position: relative; height: 340px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); overflow: hidden; }
        .hero-section img { width: 100%; height: 100%; object-fit: cover; filter: brightness(0.85); }
        .hero-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.6)); }

        .restaurant-info-card { background: white; border-radius: 24px; margin-top: -60px; position: relative; z-index: 10; padding: 24px 32px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .restaurant-logo { width: 100px; height: 100px; border-radius: 20px; object-fit: cover; border: 4px solid white; box-shadow: 0 4px 16px rgba(0,0,0,0.1); margin-top: -70px; }
        .rating-badge { background: var(--success); color: white; padding: 6px 12px; border-radius: 30px; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }
        .info-chip { background: var(--gray-100); padding: 8px 16px; border-radius: 40px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; color: var(--gray-700); }

        .nav-tabs-custom { background: white; border-bottom: 1px solid var(--gray-200); position: sticky; top: 76px; z-index: 99; }
        .nav-tabs-custom .nav-link { border: none; padding: 16px 24px; font-weight: 600; color: var(--gray-600); transition: all 0.3s; position: relative; }
        .nav-tabs-custom .nav-link:hover { color: var(--primary); }
        .nav-tabs-custom .nav-link.active { color: var(--primary); background: transparent; }
        .nav-tabs-custom .nav-link.active::after { content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 3px; background: var(--primary); border-radius: 3px 3px 0 0; }

        .menu-section { padding: 32px 0 64px; }
        .menu-category { margin-bottom: 48px; scroll-margin-top: 120px; }
        .menu-item-card { background: white; border-radius: 16px; padding: 16px; margin-bottom: 16px; transition: all 0.3s ease; cursor: pointer; border: 1px solid var(--gray-200); }
        .menu-item-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); border-color: transparent; }
        .menu-item-img { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; }
        .veg-badge { width: 12px; height: 12px; border-radius: 2px; background: #32CD32; display: inline-block; margin-right: 6px; }
        .non-veg-badge { width: 12px; height: 12px; border-radius: 2px; background: #DC143C; display: inline-block; margin-right: 6px; }
        .add-btn { background: white; border: 1px solid var(--gray-300); padding: 8px 24px; border-radius: 30px; font-weight: 600; font-size: 14px; transition: all 0.3s; }
        .add-btn:hover { background: var(--primary); border-color: var(--primary); color: white; }

        .cart-sidebar { position: fixed; right: -420px; top: 0; width: 420px; height: 100vh; background: white; box-shadow: -8px 0 32px rgba(0,0,0,0.1); z-index: 1050; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; }
        .cart-sidebar.open { right: 0; }
        .cart-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1040; display: none; }
        .cart-overlay.show { display: block; }
        .cart-item { background: var(--gray-50); border-radius: 12px; padding: 12px; margin-bottom: 12px; }
        .cart-quantity-control { display: flex; align-items: center; gap: 10px; background: white; padding: 4px; border-radius: 40px; border: 1px solid var(--gray-200); }
        .cart-qty-btn { width: 28px; height: 28px; border-radius: 50%; border: none; background: var(--primary); color: white; font-weight: bold; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .cart-qty-btn.minus { background: var(--gray-300); color: var(--gray-700); }

        .cart-floating-btn { position: fixed; bottom: 24px; right: 24px; background: var(--primary); color: white; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 8px 20px rgba(239,79,95,0.4); z-index: 100; transition: all 0.3s; }
        .cart-floating-btn:hover { transform: scale(1.1); }
        .cart-badge { position: absolute; top: -5px; right: -5px; background: white; color: var(--primary); border-radius: 50%; width: 22px; height: 22px; font-size: 12px; font-weight: bold; display: flex; align-items: center; justify-content: center; }

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
            .cart-sidebar { width: 100%; right: -100%; }
            .nav-tabs-custom .nav-link { padding: 12px 16px; font-size: 14px; }
        }
    </style>
@include('partials.public-blade-polish')
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="container">
        <a class="navbar-brand text-decoration-none" href="{{ route('home') }}">
            <i class="fas fa-utensils me-2"></i>{{ $appName ?? config('app.name') }}
        </a>
        <div class="d-flex align-items-center gap-3">
            @auth
                <div class="dropdown">
                    <button class="btn btn-link text-dark text-decoration-none dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle fs-5"></i> {{ Auth::user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('customer.orders.index') }}"><i class="fas fa-shopping-bag me-2"></i>My Orders</a></li>
                        <li><a class="dropdown-item" href="{{ route('customer.addresses.index') }}"><i class="fas fa-map-marker-alt me-2"></i>Addresses</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">@csrf</form>
            @else
                <a href="{{ route('login') }}" class="btn btn-outline-primary rounded-pill">Login</a>
                <a href="{{ route('register') }}" class="btn btn-primary rounded-pill">Sign Up</a>
            @endauth
        </div>
    </div>
</nav>

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
    <div class="hero-overlay"></div>
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
                    <div class="rating-badge">
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
                    <div class="col-lg-8">
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
                                    @foreach($items as $item)
                                        <div class="menu-item-card" data-item-name="{{ strtolower($item->name) }}">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <div class="d-flex gap-3">
                                                        @if($item->image)
                                                            <img src="{{ \App\Services\MediaStorage::url($item->image) }}" alt="{{ $item->name }}" class="menu-item-img">
                                                        @else
                                                            <div class="menu-item-img bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-utensils fa-2x text-muted"></i>
                                                            </div>
                                                        @endif
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex align-items-center gap-2 mb-1">
                            @php
                                $foodType = $item->food_type ?: ($item->is_veg ? 'veg' : 'non_veg');
                            @endphp
                                                                <span class="{{ $foodType === 'veg' ? 'veg-badge' : 'non-veg-badge' }}" style="{{ $foodType === 'egg' ? 'background:#F59E0B;' : '' }}"></span>
                                                                <h5 class="fw-bold mb-0">{{ $item->name }}</h5>
                                                                <span class="badge {{ $foodType === 'veg' ? 'bg-success' : ($foodType === 'egg' ? 'bg-warning text-dark' : 'bg-danger') }}">{{ $item->diet_label }}</span>
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
                                                                <p class="text-muted small mb-2">{{ $item->description }}</p>
                                                            @endif
                                                            <div class="d-flex align-items-center gap-3">
                                                                <span class="fw-bold h5 mb-0 text-primary">
                                                                    {{ $currencySymbol }}{{ number_format($item->discounted_price ?? $item->price, App\Models\AppSetting::currencyDecimals()) }}
                                                                </span>
                                                                @if(isset($item->discounted_price) && $item->discounted_price && $item->discounted_price < $item->price)
                                                                    <span class="text-muted text-decoration-line-through">
                                                                        {{ $currencySymbol }}{{ number_format($item->price, App\Models\AppSetting::currencyDecimals()) }}
                                                                    </span>
                                                                    <span class="badge bg-warning">
                                                                        {{ round((($item->price - $item->discounted_price) / $item->price) * 100) }}% OFF
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <button class="add-btn" data-variants='@json($item->variants ?? [])' data-addons='@json($item->add_ons ?? [])' onclick="addToCart(this, {{ $item->id }}, '{{ addslashes($item->name) }}', {{ $item->discounted_price ?? $item->price }})">
                                                        <i class="fas fa-plus me-2"></i>Add
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
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

                    <!-- Cart Desktop -->
                    <div class="col-lg-4">
                        <div class="position-sticky" style="top: 140px;">
                            <div class="bg-white rounded-4 p-4 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0"><i class="fas fa-shopping-bag text-primary me-2"></i>Your Cart</h5>
                                    <button class="btn btn-sm btn-link text-danger" onclick="clearCart()">Clear All</button>
                                </div>
                                <div id="cartItemsDesktop">
                                    <div class="text-center py-5">
                                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Your cart is empty</p>
                                        <small class="text-muted">Add items from the menu</small>
                                    </div>
                                </div>
                                <div id="cartTotalDesktop" style="display: none;">
                                    <div class="border-top pt-3 mt-3">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal</span>
                                            <span class="fw-bold">{{ $currencySymbol }}<span id="subtotal">0</span></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span id="taxLabel">Taxes</span>
                                            <span class="fw-bold">{{ $currencySymbol }}<span id="tax">0</span></span>
                                        </div>
                                        @guest
                                            <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
                                                <i class="fas fa-lock"></i>
                                                <div>You need to login before checkout. <a href="{{ route('login') }}" class="text-decoration-underline">Login now</a>.</div>
                                            </div>
                                        @endguest
                                        @if(!$restaurant->is_open)
                                            <div class="alert alert-warning mb-3">
                                                This restaurant is currently closed. Orders can only be placed when it reopens.
                                            </div>
                                        @endif
                                        <div class="d-flex justify-content-between mb-3 pt-2 border-top">
                                            <span class="fw-bold">Total</span>
                                            <span class="fw-bold text-primary h5 mb-0">{{ $currencySymbol }}<span id="total">0</span></span>
                                        </div>
                                        <button class="btn btn-primary w-100 py-3 rounded-pill" onclick="proceedToCheckout()" @if(!$restaurant->is_open) disabled @endif>
                                            @guest 
                                                Login to Checkout 
                                            @else 
                                                Proceed to Checkout 
                                            @endguest 
                                            <i class="fas fa-arrow-right ms-2"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                            <div class="similar-restaurant-card" onclick="window.location.href='{{ route('restaurant.show', $similar->id) }}'">
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

<!-- Mobile Cart -->
<div class="cart-floating-btn d-lg-none" onclick="toggleCart()">
    <i class="fas fa-shopping-bag fa-xl"></i>
    <span class="cart-badge" id="mobileCartBadge" style="display: none;">0</span>
</div>

<div class="cart-sidebar" id="mobileCartSidebar">
    <div class="d-flex justify-content-between align-items-center p-4 border-bottom">
        <h5 class="fw-bold mb-0"><i class="fas fa-shopping-bag text-primary me-2"></i>Your Cart</h5>
        <button class="btn-close" onclick="toggleCart()"></button>
    </div>
    <div class="flex-grow-1 overflow-auto p-4" id="mobileCartItems">
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
            <p class="text-muted">Your cart is empty</p>
        </div>
    </div>
    <div class="p-4 border-top" id="mobileCartFooter" style="display: none;">
        <div class="d-flex justify-content-between mb-2">
            <span>Subtotal</span>
            <span class="fw-bold">{{ $currencySymbol }}<span id="mobileSubtotal">0</span></span>
        </div>
        @guest
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
                <i class="fas fa-lock"></i>
                <div>Login is required to complete checkout. <a href="{{ route('login') }}" class="text-decoration-underline">Login now</a>.</div>
            </div>
        @endguest
        @if(!$restaurant->is_open)
            <div class="alert alert-warning mb-3">
                This restaurant is currently closed. Orders can only be placed when it reopens.
            </div>
        @endif
        <div class="d-flex justify-content-between mb-3">
            <span class="fw-bold">Total</span>
            <span class="fw-bold text-primary">{{ $currencySymbol }}<span id="mobileTotal">0</span></span>
        </div>
        <button class="btn btn-primary w-100 py-3 rounded-pill" onclick="proceedToCheckout()" @if(!$restaurant->is_open) disabled @endif>
            @guest 
                Login to Checkout 
            @else 
                Proceed to Checkout 
            @endguest 
            <i class="fas fa-arrow-right ms-2"></i>
        </button>
    </div>
</div>
<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>

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
            button.innerHTML = '<i class="fas fa-check me-2"></i>Added';
            button.style.background = '#10B981';
            button.style.borderColor = '#10B981';
            setTimeout(function() { 
                button.innerHTML = originalHtml;
                button.style.background = '';
                button.style.borderColor = '';
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
    
    function updateQuantity(index, change) {
        index = Number(index);
        var item = cart[index];
        if(item) {
            item.quantity += change;
            if(item.quantity <= 0) {
                cart.splice(index, 1);
            }
            saveCart();
        }
    }
    
    function removeFromCart(index) {
        index = Number(index);
        if (index >= 0 && index < cart.length) {
            cart.splice(index, 1);
            saveCart();
            showToast('Item removed', 'info');
        }
    }
    
    function clearCart() { 
        if(confirm('Clear your entire cart?')) {
            cart = []; 
            saveCart(); 
            showToast('Cart cleared', 'info');
        }
    }
    
    function updateCartUI() {
        var subtotal = 0;
        for (var i = 0; i < cart.length; i++) {
            subtotal += (cart[i].price || 0) * (cart[i].quantity || 0);
        }
        var tax = subtotal * (cartTaxRate / 100);
        var total = subtotal + tax;
        var itemCount = 0;
        for (var i = 0; i < cart.length; i++) {
            itemCount += (cart[i].quantity || 0);
        }
        
        // Desktop
        var desktopContainer = document.getElementById('cartItemsDesktop');
        var desktopTotal = document.getElementById('cartTotalDesktop');
        if(desktopContainer) {
            if(cart.length === 0) {
                desktopContainer.innerHTML = '<div class="text-center py-5"><i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i><p class="text-muted">Your cart is empty</p><small class="text-muted">Add items from the menu</small></div>';
                if(desktopTotal) desktopTotal.style.display = 'none';
            } else {
                var desktopHtml = '';
                for (var i = 0; i < cart.length; i++) {
                    var item = cart[i];
                    var addOnsHtml = '';
                    if (item.selected_add_ons && item.selected_add_ons.length) {
                        addOnsHtml = '<div class="small text-muted">Extras: ';
                        for (var j = 0; j < item.selected_add_ons.length; j++) {
                            if (j > 0) addOnsHtml += ', ';
                            addOnsHtml += escapeHtml(item.selected_add_ons[j].name);
                        }
                        addOnsHtml += '</div>';
                    }
                    desktopHtml += '<div class="cart-item">' +
                        '<div class="d-flex justify-content-between align-items-start mb-2">' +
                            '<div>' +
                                '<h6 class="fw-bold mb-1">' + escapeHtml(item.name) + '</h6>' +
                                (item.selected_variant ? '<div class="small text-muted">' + escapeHtml(item.selected_variant.name) + '</div>' : '') +
                                addOnsHtml +
                                '<span class="text-primary fw-bold">' + window.currencySymbol + (item.price || 0).toFixed(window.currencyDecimals) + '</span>' +
                            '</div>' +
                            '<button class="btn btn-sm btn-link text-danger p-0" onclick="removeFromCart(' + i + ')">' +
                                '<i class="fas fa-trash-alt"></i>' +
                            '</button>' +
                        '</div>' +
                        '<div class="d-flex justify-content-between align-items-center">' +
                            '<div class="cart-quantity-control">' +
                                '<button class="cart-qty-btn minus" onclick="updateQuantity(' + i + ', -1)">-</button>' +
                                '<span class="fw-semibold">' + item.quantity + '</span>' +
                                '<button class="cart-qty-btn" onclick="updateQuantity(' + i + ', 1)">+</button>' +
                            '</div>' +
                            '<span class="fw-bold">' + window.currencySymbol + ((item.price || 0) * (item.quantity || 0)).toFixed(window.currencyDecimals) + '</span>' +
                        '</div>' +
                    '</div>';
                }
                desktopContainer.innerHTML = desktopHtml;
                if(desktopTotal) desktopTotal.style.display = 'block';
            }
        }
        
        if(document.getElementById('subtotal')) document.getElementById('subtotal').innerText = subtotal.toFixed(window.currencyDecimals);
        if(document.getElementById('tax')) document.getElementById('tax').innerText = tax.toFixed(window.currencyDecimals);
        if(document.getElementById('taxLabel')) document.getElementById('taxLabel').innerText = cartTaxLabel;
        if(document.getElementById('total')) document.getElementById('total').innerText = total.toFixed(window.currencyDecimals);
        
        // Mobile
        var mobileContainer = document.getElementById('mobileCartItems');
        var mobileFooter = document.getElementById('mobileCartFooter');
        if(mobileContainer) {
            if(cart.length === 0) {
                mobileContainer.innerHTML = '<div class="text-center py-5"><i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i><p class="text-muted">Your cart is empty</p></div>';
                if(mobileFooter) mobileFooter.style.display = 'none';
            } else {
                var mobileHtml = '';
                for (var i = 0; i < cart.length; i++) {
                    var item = cart[i];
                    var addOnsHtml = '';
                    if (item.selected_add_ons && item.selected_add_ons.length) {
                        addOnsHtml = '<div class="small text-muted">Extras: ';
                        for (var j = 0; j < item.selected_add_ons.length; j++) {
                            if (j > 0) addOnsHtml += ', ';
                            addOnsHtml += escapeHtml(item.selected_add_ons[j].name);
                        }
                        addOnsHtml += '</div>';
                    }
                    mobileHtml += '<div class="cart-item">' +
                        '<div class="d-flex justify-content-between align-items-start mb-2">' +
                            '<div>' +
                                '<h6 class="fw-bold mb-1">' + escapeHtml(item.name) + '</h6>' +
                                (item.selected_variant ? '<div class="small text-muted">' + escapeHtml(item.selected_variant.name) + '</div>' : '') +
                                addOnsHtml +
                                '<span class="text-primary fw-bold">' + window.currencySymbol + (item.price || 0).toFixed(window.currencyDecimals) + '</span>' +
                            '</div>' +
                            '<button class="btn btn-sm btn-link text-danger p-0" onclick="removeFromCart(' + i + ')">' +
                                '<i class="fas fa-trash-alt"></i>' +
                            '</button>' +
                        '</div>' +
                        '<div class="d-flex justify-content-between align-items-center">' +
                            '<div class="cart-quantity-control">' +
                                '<button class="cart-qty-btn minus" onclick="updateQuantity(' + i + ', -1)">-</button>' +
                                '<span class="fw-semibold">' + item.quantity + '</span>' +
                                '<button class="cart-qty-btn" onclick="updateQuantity(' + i + ', 1)">+</button>' +
                            '</div>' +
                            '<span class="fw-bold">' + window.currencySymbol + ((item.price || 0) * (item.quantity || 0)).toFixed(window.currencyDecimals) + '</span>' +
                        '</div>' +
                    '</div>';
                }
                mobileContainer.innerHTML = mobileHtml;
                if(mobileFooter) mobileFooter.style.display = 'block';
            }
        }
        
        if(document.getElementById('mobileSubtotal')) document.getElementById('mobileSubtotal').innerText = subtotal.toFixed(window.currencyDecimals);
        if(document.getElementById('mobileTotal')) document.getElementById('mobileTotal').innerText = total.toFixed(window.currencyDecimals);
        
        var badge = document.getElementById('mobileCartBadge');
        if(badge) {
            if(itemCount > 0) {
                badge.style.display = 'flex';
                badge.innerText = itemCount > 99 ? '99+' : itemCount;
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    function proceedToCheckout() {
        if (!restaurantIsOpen) {
            showToast('Restaurant is currently closed. You can place an order only when it reopens.', 'error');
            return;
        }
        if(cart.length === 0) {
            showToast('Your cart is empty!', 'error');
            return;
        }
        @guest 
            showToast('Please login to continue', 'warning');
            setTimeout(function() { 
                window.location.href = "{{ route('login', ['redirect' => url()->current()]) }}";
            }, 1000);
            return;
        @endguest
        
        var validCart = [];
        for (var i = 0; i < cart.length; i++) {
            var item = cart[i];
            if (item.id && item.name && item.price && item.quantity > 0) {
                validCart.push(item);
            }
        }
        
        if(validCart.length === 0) {
            showToast('Invalid cart data', 'error');
            return;
        }
        
        var checkoutData = [];
        for (var i = 0; i < validCart.length; i++) {
            checkoutData.push({
                id: validCart[i].id,
                name: validCart[i].name,
                price: parseFloat(validCart[i].price),
                quantity: parseInt(validCart[i].quantity),
                selected_variant: validCart[i].selected_variant || null,
                selected_add_ons: validCart[i].selected_add_ons || [],
                restaurant_id: restaurantId
            });
        }
        
        try {
            localStorage.setItem('checkout_cart', JSON.stringify(checkoutData));
            localStorage.setItem('checkout_restaurant_id', restaurantId);
            showToast('Redirecting to checkout...', 'info');
            setTimeout(function() {
                window.location.href = '/checkout?cart=' + encodeURIComponent(JSON.stringify(checkoutData));
            }, 500);
        } catch(e) {
            showToast('Failed to proceed', 'error');
        }
    }
    
    function toggleCart() {
        var sidebar = document.getElementById('mobileCartSidebar');
        var overlay = document.getElementById('cartOverlay');
        if(sidebar && overlay) {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('partials.web-visit-tracker', ['panel' => 'frontend'])
</body>
</html>
