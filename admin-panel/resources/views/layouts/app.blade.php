<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', 'â‚¹');
        $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    @endphp
    <meta name="description" content="Order food from your favorite restaurants">
    <meta name="theme-color" content="#FF6B35">
    
    @php
        $appName = App\Models\AppSetting::getValue('app_name', 'Food Delivery');
        $primaryColor = App\Models\AppSetting::getValue('primary_color', '#FF6B35');
        $secondaryColor = App\Models\AppSetting::getValue('secondary_color', '#FF8C42');
        $appFavicon = App\Models\AppSetting::getValue('app_favicon');
        $faviconUrl = $appFavicon ? \Illuminate\Support\Facades\Storage::disk('public')->url($appFavicon) : asset('favicon.ico');
    @endphp
    
    <title>@yield('title', $appName)</title>
    
    <!-- Favicon -->
    <link rel="icon" href="{{ $faviconUrl }}">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <script>
        window.currencySymbol = @json($currencySymbol);
        window.currencyDecimals = @json($currencyDecimals);
    </script>

    <style>
        :root {
            --primary: {{ $primaryColor }};
            --primary-dark: {{ $primaryColor }}dd;
            --primary-light: {{ $primaryColor }}33;
            --secondary: {{ $secondaryColor }};
            --secondary-dark: {{ $secondaryColor }}dd;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
            --dark: #0F172A;
            --light: #F8FAFC;
            --border: #E2E8F0;
            --gray-50: #F9FAFB;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #FFFFFF;
            color: var(--gray-800);
            overflow-x: hidden;
        }

        /* ========== TOP NAVBAR STYLES ========== */
        .navbar {
            background: #FFFFFF;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        
        .navbar-brand {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        
        .navbar-brand span {
            color: var(--gray-800);
        }
        
        .nav-link {
            font-weight: 500;
            color: var(--gray-600);
            transition: all 0.3s;
            padding: 8px 16px !important;
            border-radius: 30px;
        }
        
        .nav-link:hover,
        .nav-link.active {
            color: var(--primary);
            background: var(--primary-light);
        }
        
        .cart-icon {
            position: relative;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--primary);
            color: white;
            font-size: 10px;
            font-weight: 700;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border-radius: 16px;
            padding: 8px;
            margin-top: 12px;
        }
        
        .dropdown-item {
            border-radius: 10px;
            padding: 8px 16px;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background: var(--gray-100);
            color: var(--primary);
        }
        
        .dropdown-item.text-danger:hover {
            background: #FEE2E2;
            color: var(--danger);
        }
        
        /* Location Bar */
        .location-bar {
            background: var(--gray-50);
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .location-selector {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            background: white;
            padding: 8px 16px;
            border-radius: 40px;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }
        
        .location-selector:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .location-icon {
            color: var(--primary);
            font-size: 16px;
        }
        
        .location-text {
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-700);
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Search Bar */
        .search-wrapper {
            flex: 1;
            max-width: 500px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 20px;
            border: 1px solid var(--border);
            border-radius: 50px;
            font-size: 14px;
            transition: all 0.3s;
            background: var(--gray-50);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 60px 0;
            color: white;
        }
        
        .hero-title {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 30px;
        }
        
        /* Footer */
        .footer {
            background: var(--gray-900);
            color: var(--gray-400);
            padding: 60px 0 30px;
            margin-top: 60px;
        }
        
        .footer h5 {
            color: white;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: var(--gray-400);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .footer-links a:hover {
            color: var(--primary);
        }
        
        .social-icons {
            display: flex;
            gap: 16px;
        }
        
        .social-icons a {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s;
        }
        
        .social-icons a:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }
        
        .copyright {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 30px;
            margin-top: 30px;
            text-align: center;
            font-size: 14px;
        }
        
        /* Buttons */
        .btn-primary {
            background: var(--primary);
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255,107,53,0.3);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            border-radius: 40px;
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Cards */
        .restaurant-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid var(--border);
            margin-bottom: 24px;
        }
        
        .restaurant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        /* Toast Messages */
        .custom-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 280px;
            animation: slideInRight 0.3s ease;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 32px;
            }
            
            .search-wrapper {
                max-width: 100%;
                margin: 16px 0;
            }
            
            .location-selector {
                margin: 10px 0;
            }
        }
        
        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 20px;
            }
            
            .hero-section {
                padding: 40px 0;
            }
            
            .hero-title {
                font-size: 28px;
            }
        }
        
        /* Loading Spinner */
        .spinner-custom {
            width: 40px;
            height: 40px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ========== GLOBAL CUSTOMER WEB MODERNIZATION ========== */
        body {
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 18%, transparent) 0, transparent 34%),
                radial-gradient(circle at top right, color-mix(in srgb, var(--secondary) 18%, transparent) 0, transparent 30%),
                linear-gradient(180deg, #fffaf7 0%, #ffffff 42%, #f8fafc 100%);
            color: var(--gray-800);
        }

        main {
            min-height: 58vh;
            padding-block: 28px;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.88);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
            backdrop-filter: blur(18px);
        }

        .navbar-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.04em;
        }

        .navbar-brand::before {
            content: "";
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 12px 24px color-mix(in srgb, var(--primary) 34%, transparent);
        }

        .location-bar {
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(16px);
        }

        .container > .alert,
        main > .alert,
        .alert {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .page-header,
        .profile-header,
        .orders-header,
        .support-header,
        .checkout-header,
        .tracking-header {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 34px;
            padding: clamp(24px, 4vw, 42px);
            color: #ffffff;
            background:
                radial-gradient(circle at 14% 18%, rgba(255, 255, 255, 0.24), transparent 28%),
                linear-gradient(135deg, var(--gray-900) 0%, color-mix(in srgb, var(--primary) 58%, #111827) 55%, var(--secondary) 100%);
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.2);
        }

        .page-header::after,
        .profile-header::after,
        .orders-header::after,
        .support-header::after,
        .checkout-header::after,
        .tracking-header::after {
            content: "";
            position: absolute;
            right: -56px;
            bottom: -72px;
            width: 210px;
            height: 210px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
        }

        .page-header h1,
        .profile-header h1,
        .orders-header h1,
        .support-header h1,
        .checkout-header h1,
        .tracking-header h1 {
            position: relative;
            z-index: 1;
            margin-bottom: 8px;
            font-weight: 900;
            letter-spacing: -0.045em;
        }

        .page-header p,
        .profile-header p,
        .orders-header p,
        .support-header p,
        .checkout-header p,
        .tracking-header p {
            position: relative;
            z-index: 1;
            max-width: 680px;
            margin-bottom: 0;
            color: rgba(255, 255, 255, 0.82);
        }

        .card,
        .table-card,
        .stat-card,
        .order-card,
        .address-card,
        .support-card,
        .checkout-card,
        .tracking-card,
        .profile-card,
        .ticket-card,
        .summary-card {
            border: 1px solid rgba(226, 232, 240, 0.82) !important;
            border-radius: 28px !important;
            background: rgba(255, 255, 255, 0.9) !important;
            box-shadow: 0 22px 55px rgba(15, 23, 42, 0.08) !important;
            backdrop-filter: blur(16px);
        }

        .card-header,
        .table-card .card-header {
            border-bottom: 1px solid rgba(226, 232, 240, 0.75);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.76));
            border-radius: 28px 28px 0 0 !important;
            padding: 18px 22px;
        }

        .card-body {
            padding: clamp(18px, 3vw, 28px);
        }

        .stat-card {
            transition: transform 0.24s ease, box-shadow 0.24s ease;
        }

        .stat-card:hover,
        .order-card:hover,
        .address-card:hover,
        .support-card:hover,
        .ticket-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.12) !important;
        }

        .form-control,
        .form-select,
        textarea.form-control,
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="tel"],
        input[type="date"],
        input[type="time"] {
            min-height: 48px;
            border: 1px solid rgba(203, 213, 225, 0.9);
            border-radius: 16px;
            background-color: rgba(255, 255, 255, 0.92);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus,
        textarea.form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 16%, transparent);
        }

        .form-label {
            color: var(--gray-700);
            font-weight: 750;
            letter-spacing: -0.01em;
        }

        .btn {
            border-radius: 999px;
            font-weight: 750;
            letter-spacing: -0.01em;
            transition: transform 0.22s ease, box-shadow 0.22s ease, background 0.22s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 16px 34px color-mix(in srgb, var(--primary) 25%, transparent);
        }

        .btn-light,
        .btn-outline-secondary,
        .btn-outline-primary,
        .btn-outline-danger,
        .btn-outline-success {
            background: rgba(255, 255, 255, 0.78);
            border-width: 1px;
        }

        .table {
            margin-bottom: 0;
            vertical-align: middle;
        }

        .table thead th {
            border: 0;
            background: #fff7ed;
            color: #9a3412;
            font-size: 12px;
            font-weight: 850;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .table tbody td {
            border-color: rgba(226, 232, 240, 0.78);
            padding-block: 15px;
        }

        .badge,
        .status-badge,
        .payment-badge {
            border-radius: 999px;
            padding: 7px 12px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 34px 90px rgba(15, 23, 42, 0.24);
            backdrop-filter: blur(20px);
        }

        .modal-header {
            border-bottom: 1px solid rgba(226, 232, 240, 0.76);
            background: linear-gradient(135deg, #ffffff, #fff7ed);
        }

        .modal-footer {
            border-top: 1px solid rgba(226, 232, 240, 0.76);
            background: rgba(248, 250, 252, 0.88);
        }

        .pagination {
            gap: 8px;
        }

        .page-link {
            border: 0;
            border-radius: 999px !important;
            color: var(--gray-700);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.07);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .empty-state,
        .no-data,
        .no-orders,
        .no-addresses,
        .no-tickets {
            border: 1px dashed rgba(251, 146, 60, 0.48);
            border-radius: 30px;
            background: linear-gradient(135deg, rgba(255, 247, 237, 0.9), rgba(255, 255, 255, 0.94));
            padding: clamp(28px, 5vw, 54px);
            text-align: center;
        }

        .footer {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background:
                radial-gradient(circle at top left, rgba(255, 107, 53, 0.22), transparent 30%),
                linear-gradient(135deg, #0f172a, #111827 52%, #1f2937);
        }

        @media (max-width: 768px) {
            main {
                padding-block: 18px;
            }

            .page-header,
            .profile-header,
            .orders-header,
            .support-header,
            .checkout-header,
            .tracking-header,
            .card,
            .table-card,
            .stat-card,
            .order-card,
            .address-card,
            .support-card,
            .checkout-card,
            .tracking-card,
            .profile-card,
            .ticket-card,
            .summary-card {
                border-radius: 22px !important;
            }

            .table-responsive {
                border-radius: 22px;
            }
        }
    </style>
    
    @yield('styles')
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                {{ $appName }}
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('restaurant*') ? 'active' : '' }}" href="{{ route('home') }}#restaurants">
                            <i class="fas fa-utensils me-1"></i> Restaurants
                        </a>
                    </li>
                    @auth
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('customer.orders*') ? 'active' : '' }}" href="{{ route('customer.orders.index') }}">
                            <i class="fas fa-shopping-bag me-1"></i> My Orders
                        </a>
                    </li>
                    @endauth
                </ul>
                
                <div class="d-flex gap-2 align-items-center">
                    @auth
                        <a href="{{ route('checkout.index') }}" class="position-relative me-2">
                            <i class="fas fa-shopping-cart fa-lg" style="color: var(--gray-600);"></i>
                            @php
                                $cartCount = session()->get('cart_count', 0);
                            @endphp
                            @if($cartCount > 0)
                                <span class="cart-count">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
                            @endif
                        </a>
                        
                        <div class="dropdown">
                            <div class="user-avatar" data-bs-toggle="dropdown" style="cursor: pointer;">
                                {{ substr(auth()->user()->name, 0, 1) }}
                            </div>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('customer.orders.index') }}">
                                    <i class="fas fa-shopping-bag me-2"></i> My Orders
                                </a></li>
                                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="fas fa-user me-2"></i> Profile Settings
                                </a></li>
                                <li><a class="dropdown-item" href="{{ route('customer.addresses.index') }}">
                                    <i class="fas fa-map-marker-alt me-2"></i> Saved Addresses
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4">Login</a>
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm rounded-pill px-4">Sign Up</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Location Bar (if on home page or restaurant pages) -->
    @if(request()->routeIs('home') || request()->routeIs('restaurant.show'))
    <div class="location-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="location-selector" id="locationSelector">
                        <i class="fas fa-map-marker-alt location-icon"></i>
                        <span class="location-text" id="selectedLocation">
                            @if(session('delivery_location'))
                                {{ session('delivery_location') }}
                            @else
                                Detect my location
                            @endif
                        </span>
                        <i class="fas fa-chevron-down text-muted" style="font-size: 12px;"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="search-wrapper ms-auto">
                        <input type="text" class="search-input" id="globalSearch" placeholder="Search for restaurants or dishes..." autocomplete="off">
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Main Content -->
    <main>
        @yield('content')
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>{{ $appName }}</h5>
                    <p class="small">Order food from the best restaurants in your city. Fast delivery, great taste!</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <h5>Company</h5>
                    <ul class="footer-links">
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Press</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-4">
                    <h5>Support</h5>
                    <ul class="footer-links">
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Download App</h5>
                    <p class="small">Get the best food experience with our mobile app</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="btn btn-dark rounded-pill">
                            <i class="fab fa-google-play me-2"></i> Google Play
                        </a>
                        <a href="#" class="btn btn-dark rounded-pill">
                            <i class="fab fa-apple me-2"></i> App Store
                        </a>
                    </div>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Location Modal -->
    <div class="modal fade" id="locationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Select Delivery Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <button class="btn btn-primary w-100 mb-3" id="detectLocationBtn">
                        <i class="fas fa-location-dot me-2"></i> Detect My Location
                    </button>
                    <div class="position-relative">
                        <input type="text" id="locationSearch" class="form-control rounded-pill py-3" placeholder="Search for area, street or city...">
                        <i class="fas fa-search position-absolute top-50 end-0 translate-middle-y me-3 text-muted"></i>
                    </div>
                    <div id="locationSuggestions" class="mt-3" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
    <script>
        // Dynamic app name from PHP
        const appName = "{{ $appName }}";
        const primaryColor = "{{ $primaryColor }}";
        
        // Location detection
        const locationSelector = document.getElementById('locationSelector');
        const locationModal = new bootstrap.Modal(document.getElementById('locationModal'));
        
        if (locationSelector) {
            locationSelector.addEventListener('click', () => {
                locationModal.show();
            });
        }
        
        // Detect Location
        document.getElementById('detectLocationBtn')?.addEventListener('click', function() {
            if ('geolocation' in navigator) {
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Detecting...';
                this.disabled = true;
                
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    // Reverse geocoding to get address
                    fetch(`google-maps-shim/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`)
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.display_name) {
                                const location = data.address?.city || data.address?.town || data.address?.village || 'Your Location';
                                document.getElementById('selectedLocation').textContent = location;
                                
                                // Save to session via AJAX
                                fetch('/set-location', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                    },
                                    body: JSON.stringify({ location: location, lat: lat, lng: lng })
                                });
                                
                                locationModal.hide();
                                showToast('Location updated!', 'success');
                            }
                        });
                }, function(error) {
                    showToast('Unable to detect location. Please enter manually.', 'error');
                    this.innerHTML = '<i class="fas fa-location-dot me-2"></i> Detect My Location';
                    this.disabled = false;
                });
            } else {
                showToast('Geolocation is not supported by your browser', 'error');
            }
        });
        
        // Location Search
        const locationSearch = document.getElementById('locationSearch');
        const suggestionsDiv = document.getElementById('locationSuggestions');
        
        let searchTimeout;
        if (locationSearch) {
            locationSearch.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value;
                
                if (query.length < 2) {
                    suggestionsDiv.innerHTML = '';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    fetch(`google-maps-shim/search?format=json&q=${encodeURIComponent(query)}&limit=5`)
                        .then(response => response.json())
                        .then(data => {
                            suggestionsDiv.innerHTML = data.map(item => `
                                <div class="p-3 border-bottom suggestion-item" style="cursor: pointer;" 
                                     data-lat="${item.lat}" data-lon="${item.lon}" data-name="${item.display_name}">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="fas fa-map-marker-alt text-primary"></i>
                                        <div>
                                            <div class="fw-semibold">${item.display_name.split(',')[0]}</div>
                                            <small class="text-muted">${item.display_name}</small>
                                        </div>
                                    </div>
                                </div>
                            `).join('');
                            
                            document.querySelectorAll('.suggestion-item').forEach(el => {
                                el.addEventListener('click', function() {
                                    const location = this.dataset.name.split(',')[0];
                                    document.getElementById('selectedLocation').textContent = location;
                                    
                                    fetch('/set-location', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                        },
                                        body: JSON.stringify({ 
                                            location: location, 
                                            lat: this.dataset.lat, 
                                            lng: this.dataset.lon 
                                        })
                                    });
                                    
                                    locationModal.hide();
                                    showToast('Location updated!', 'success');
                                });
                            });
                        });
                }, 500);
            });
        }
        
        // Global Search
        const globalSearch = document.getElementById('globalSearch');
        if (globalSearch) {
            globalSearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const query = this.value.trim();
                    if (query) {
                        window.location.href = `/search?q=${encodeURIComponent(query)}`;
                    }
                }
            });
        }
        
        // Toast Message
        function showToast(message, type = 'info') {
            const toastHtml = `
                <div class="custom-toast">
                    <div class="toast align-items-center text-white bg-${type} border-0 show" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')} me-2"></i>
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', toastHtml);
            setTimeout(() => {
                const toast = document.querySelector('.custom-toast');
                if (toast) toast.remove();
            }, 3000);
        }
        
        // Cart Count Update
        function updateCartCount() {
            fetch('/cart/count')
                .then(response => response.json())
                .then(data => {
                    const cartBadge = document.querySelector('.cart-count');
                    if (cartBadge) {
                        if (data.count > 0) {
                            cartBadge.textContent = data.count > 99 ? '99+' : data.count;
                            cartBadge.style.display = 'flex';
                        } else {
                            cartBadge.style.display = 'none';
                        }
                    }
                });
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.3s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 4000);
        
        // Initialize Swiper on home page
        if (document.querySelector('.swiper')) {
            new Swiper('.swiper', {
                loop: true,
                autoplay: { delay: 3000 },
                pagination: { el: '.swiper-pagination', clickable: true },
                navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' }
            });
        }
    </script>
    
    @include('partials.web-visit-tracker', ['panel' => 'customer_web'])
    @yield('scripts')
@include('partials.google-maps-shim')
</body>
</html>


