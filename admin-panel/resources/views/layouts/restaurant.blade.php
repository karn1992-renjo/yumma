{{-- resources/views/layouts/restaurant.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
        $currencyDecimals = App\Models\AppSetting::currencyDecimals();
        $appFavicon = App\Models\AppSetting::getValue('app_favicon');
        $brandingUrl = function (?string $path) {
            if (!$path) {
                return asset('favicon.ico');
            }

            return str_starts_with($path, 'branding/')
                ? route('media.branding', ['file' => basename($path)])
                : \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        };
        $pusherCluster = config('broadcasting.connections.pusher.options.cluster', 'mt1');
        $savedPusherHost = App\Models\AppSetting::getValue('pusher_host', '');
        $frontendPusherHost = $savedPusherHost
            ? preg_replace('/^api-/', 'ws-', $savedPusherHost)
            : 'ws-' . ($pusherCluster ?: 'mt1') . '.pusher.com';
        $pusherRuntimeConfig = [
            'enabled' => config('broadcasting.default') === 'pusher',
            'key' => config('broadcasting.connections.pusher.key'),
            'cluster' => $pusherCluster,
            'host' => $frontendPusherHost,
            'port' => (int) config('broadcasting.connections.pusher.options.port', 443),
            'scheme' => config('broadcasting.connections.pusher.options.scheme', 'https'),
        ];
    @endphp
    <meta name="restaurant-id" content="{{ auth()->user()->activeRestaurant()?->id ?? '' }}">
    <title>@yield('title', 'Restaurant Dashboard') - {{ $appName ?? config('app.name') }}</title>
    <link rel="icon" href="{{ $appFavicon ? $brandingUrl($appFavicon) : asset('favicon.ico') }}">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Vite-built app assets -->
        <script>
            window.currencySymbol = @json($currencySymbol);
            window.currencyDecimals = @json($currencyDecimals);
            window.AppBroadcastConfig = @json($pusherRuntimeConfig);
            window.formatCurrency = function(value) {
                const decimals = Number.isFinite(Number(window.currencyDecimals))
                    ? Number(window.currencyDecimals)
                    : 2;
                const amount = Number.parseFloat(value);
                const safeAmount = Number.isFinite(amount) ? amount : 0;

                return `${window.currencySymbol || '₹'}${safeAmount.toFixed(decimals)}`;
            };
            window.cleanCurrencyPlaceholders = function(root = document.body) {
                const symbol = window.currencySymbol || '₹';
                const placeholder = '{' + '{ $currencySymbol }}';
                const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
                let node;

                while ((node = walker.nextNode())) {
                    if (node.nodeValue && node.nodeValue.includes(placeholder)) {
                        node.nodeValue = node.nodeValue.replaceAll(placeholder, symbol);
                    }
                }
            };

            document.addEventListener('DOMContentLoaded', function() {
                window.cleanCurrencyPlaceholders();

                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            const placeholder = '{' + '{ $currencySymbol }}';
                            if (node.nodeType === Node.TEXT_NODE && node.nodeValue.includes(placeholder)) {
                                node.nodeValue = node.nodeValue.replaceAll(placeholder, window.currencySymbol || '₹');
                            } else if (node.nodeType === Node.ELEMENT_NODE) {
                                window.cleanCurrencyPlaceholders(node);
                            }
                        });
                    });
                });

                observer.observe(document.body, { childList: true, subtree: true });
            });
        </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: {{ $primaryColor ?? '#FF6B35' }};
            --primary-light: {{ $primaryLight ?? '#FF8F65' }};
            --primary-dark: {{ $primaryDark ?? '#E55A2B' }};
            --secondary: {{ $secondaryColor ?? '#1E293B' }};
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
            --dark: #0F172A;
            --light: #F8FAFC;
            --border: #E2E8F0;
            --sidebar-width: 286px;
            --topbar-height: 78px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(255, 107, 53, 0.14), transparent 32%),
                radial-gradient(circle at top right, rgba(16, 185, 129, 0.12), transparent 28%),
                #F1F5F9;
            color: #334155;
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* ========== TOP HEADER ========== */
        .top-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 22px;
            color: #64748B;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .menu-toggle:hover {
            background: #F1F5F9;
            color: var(--primary);
        }

        .header-search-wrapper {
            position: relative;
            max-width: 400px;
            width: 100%;
        }

        .header-search-wrapper .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 16px;
            pointer-events: none;
        }

        .header-search-wrapper input {
            width: 100%;
            padding: 10px 16px 10px 44px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            background: #F8FAFC;
            transition: all 0.3s;
            outline: none;
        }

        .header-search-wrapper input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        .header-search-wrapper input::placeholder {
            color: #94A3B8;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-icon-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1.5px solid var(--border);
            background: #fff;
            color: #64748B;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            text-decoration: none;
        }

        .header-icon-btn:hover {
            background: #F8FAFC;
            border-color: var(--primary);
            color: var(--primary);
        }

        .header-icon-btn .badge-notification {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--danger);
            color: #fff;
            font-size: 10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border: 2px solid #fff;
        }

        .header-divider {
            width: 1px;
            height: 32px;
            background: var(--border);
        }

        /* Restaurant Status Toggle */
        .status-toggle-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px;
            background: #F8FAFC;
            border-radius: 12px;
            border: 1.5px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
        }

        .status-toggle-wrapper:hover {
            background: #F1F5F9;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            position: relative;
        }

        .status-indicator.online {
            background: var(--success);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            animation: pulse 2s infinite;
        }

        .status-indicator.offline {
            background: #94A3B8;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .status-text {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }

        /* User Profile */
        .user-profile-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 4px 16px 4px 4px;
            border-radius: 14px;
            transition: all 0.2s;
            position: relative;
        }

        .user-profile-wrapper:hover {
            background: #F8FAFC;
        }

        .user-avatar-lg {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            position: relative;
        }

        .user-avatar-lg::after {
            content: '';
            position: absolute;
            bottom: -1px;
            right: -1px;
            width: 12px;
            height: 12px;
            background: var(--success);
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .user-info-text {
            display: flex;
            flex-direction: column;
        }

        .user-info-text .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #1E293B;
            line-height: 1.2;
        }

        .user-info-text .user-role {
            font-size: 12px;
            color: #64748B;
            line-height: 1.2;
        }

        .user-dropdown-arrow {
            color: #64748B;
            font-size: 12px;
            margin-left: 4px;
        }

        /* User Dropdown Menu */
        .profile-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            border: 1px solid var(--border);
            min-width: 280px;
            padding: 8px;
            display: none;
            z-index: 1050;
        }

        .profile-dropdown-menu.show {
            display: block;
            animation: slideDown 0.2s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-user-header {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 4px;
        }

        .dropdown-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #334155;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .dropdown-menu-item:hover {
            background: #F8FAFC;
            color: var(--primary);
        }

        .dropdown-menu-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .dropdown-menu-item.text-danger {
            color: #EF4444;
        }

        .dropdown-menu-item.text-danger:hover {
            background: #FEF2F2;
        }

        .dropdown-divider {
            border-top: 1px solid var(--border);
            margin: 4px 0;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid var(--border);
            z-index: 1040;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-logo-section {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: var(--topbar-height);
        }

        .sidebar-logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 20px;
            flex-shrink: 0;
        }

        .sidebar-logo-text h2 {
            font-size: 22px;
            font-weight: 800;
            color: #1E293B;
            margin: 0;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .sidebar-logo-text h2 span {
            color: var(--primary);
        }

        .sidebar-logo-text small {
            font-size: 11px;
            color: #64748B;
            font-weight: 500;
        }

        .sidebar-nav-wrapper {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .sidebar-section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94A3B8;
            font-weight: 700;
            padding: 8px 12px;
            margin-top: 8px;
        }

        .sidebar-section-title:first-child {
            margin-top: 0;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav-item {
            margin-bottom: 2px;
        }

        .sidebar-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            color: #64748B;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            position: relative;
            white-space: nowrap;
        }

        .sidebar-nav-link:hover {
            background: #F8FAFC;
            color: #334155;
        }

        .sidebar-nav-link.active {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.1), rgba(255, 107, 53, 0.05));
            color: var(--primary);
            font-weight: 600;
        }

        .sidebar-nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: var(--primary);
            border-radius: 0 3px 3px 0;
        }

        .sidebar-nav-link i {
            width: 20px;
            text-align: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .sidebar-badge {
            margin-left: auto;
            background: var(--danger);
            color: #fff;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
        }

        .sidebar-restaurant-card {
            background: #F8FAFC;
            border-radius: 12px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-restaurant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667EEA, #764BA2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }

        .sidebar-restaurant-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-restaurant-info .restaurant-name {
            font-size: 13px;
            font-weight: 600;
            color: #1E293B;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-restaurant-info .restaurant-status {
            font-size: 11px;
            color: var(--success);
            font-weight: 500;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--topbar-height);
            min-height: calc(100vh - var(--topbar-height));
            transition: all 0.3s ease;
        }

        .page-content {
            padding: 28px;
        }

        /* ========== ORDER TOAST NOTIFICATIONS ========== */
        .order-toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            width: 380px;
        }

        .order-toast {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            margin-bottom: 16px;
            overflow: hidden;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        .order-toast-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-toast-icon {
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .order-toast-title {
            font-weight: 600;
            font-size: 14px;
        }

        .order-toast-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .order-toast-close:hover {
            opacity: 1;
        }

        .order-toast-body {
            padding: 16px;
        }

        .order-toast-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .btn-accept-order {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-accept-order:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-reject-order {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-reject-order:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        /* Custom Toast Messages */
        .custom-toast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 12px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .toast-success { background: #10b981; }
        .toast-error { background: #ef4444; }
        .toast-warning { background: #f59e0b; }
        .toast-info { background: #3b82f6; }

        /* Animations */
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

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .toast-slide-out {
            animation: slideOutRight 0.3s ease forwards;
        }

        /* Pending Orders Badge Pulse */
        @keyframes badgePulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .badge-pulse {
            animation: badgePulse 0.5s ease;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 10px 0 30px rgba(0,0,0,0.15);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .top-header {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: flex;
            }

            .header-search-wrapper {
                display: none;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1035;
            }

            .sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 640px) {
            .page-content {
                padding: 16px;
            }

            .status-toggle-wrapper {
                display: none;
            }

            .order-toast-container {
                width: calc(100% - 40px);
                right: 20px;
                left: 20px;
            }

            .header-actions .header-icon-btn:nth-child(2),
            .header-actions .header-icon-btn:nth-child(3) {
                display: none;
            }
        }

        /* Page Header */
        .page-header {
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 4px;
        }

        .page-header p {
            color: #64748B;
            margin: 0;
            font-size: 14px;
        }

        /* Cards */
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            height: 100%;
        }

        .stat-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-color: #CBD5E1;
        }

        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-card .icon.primary { background: rgba(255, 107, 53, 0.1); color: var(--primary); }
        .stat-card .icon.success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stat-card .icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-card .icon.info { background: rgba(59, 130, 246, 0.1); color: var(--info); }

        /* Table Card */
        .table-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            overflow: hidden;
        }

        .table-card .card-header {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 20px 24px;
        }

        /* Badges */
        .badge {
            padding: 5px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
        }

        .badge-pending { background: #FEF3C7; color: #92400E; }
        .badge-confirmed { background: #DBEAFE; color: #1E40AF; }
        .badge-preparing { background: #D1FAE5; color: #065F46; }
        .badge-ready_for_pickup { background: #E0E7FF; color: #3730A3; }
        .badge-delivered { background: #D1FAE5; color: #065F46; }
        .badge-cancelled { background: #FEE2E2; color: #991B1B; }

        /* Buttons */
        .btn {
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 20px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .btn-outline-primary {
            border: 1.5px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: #fff;
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
        }

        /* ========== GLOBAL RESTAURANT PANEL MODERNIZATION ========== */
        .page-content {
            background:
                radial-gradient(circle at 12% 0%, rgba(255, 107, 53, .08), transparent 26%),
                radial-gradient(circle at 88% 12%, rgba(16, 185, 129, .07), transparent 24%),
                #f8fafc;
        }

        .page-header {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px;
            color: #fff;
            background:
                radial-gradient(circle at 12% 18%, rgba(255,255,255,.22), transparent 24%),
                radial-gradient(circle at 88% 12%, rgba(16,185,129,.22), transparent 24%),
                linear-gradient(135deg, #111827 0%, #14532d 45%, var(--primary) 100%);
            box-shadow: 0 22px 60px rgba(255, 107, 53, .18);
        }

        .page-header::after {
            content: "";
            position: absolute;
            right: -80px;
            bottom: -120px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,.14);
        }

        .page-header > * {
            position: relative;
            z-index: 1;
        }

        .page-header h1,
        .page-header .display-5 {
            color: #fff !important;
            background: none !important;
            -webkit-text-fill-color: #fff !important;
            font-size: clamp(28px, 3vw, 40px) !important;
            line-height: 1.08;
            font-weight: 900 !important;
            letter-spacing: -.04em;
            margin-bottom: 8px;
        }

        .page-header p,
        .page-header .text-muted {
            color: rgba(255,255,255,.78) !important;
        }

        .page-header .btn:not(.btn-light) {
            border-color: rgba(255,255,255,.35);
            background: rgba(255,255,255,.14);
            color: #fff;
            backdrop-filter: blur(12px);
        }

        .stat-card,
        .filter-section,
        .table-container,
        .table-card,
        .card:not(.modal-content):not(.profile-dropdown-menu) {
            border-radius: 26px !important;
            border: 1px solid rgba(15, 23, 42, .08) !important;
            background: rgba(255,255,255,.92) !important;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .07) !important;
            overflow: hidden;
        }

        .stat-card {
            position: relative;
        }

        .stat-card::after {
            content: "";
            position: absolute;
            right: -36px;
            top: -36px;
            width: 108px;
            height: 108px;
            border-radius: 50%;
            background: rgba(255, 107, 53, .12);
            pointer-events: none;
        }

        .stat-card:hover,
        .table-card:hover,
        .filter-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 60px rgba(15, 23, 42, .10) !important;
        }

        .table-card .card-header,
        .table-container .card-header,
        .card-header {
            background: rgba(255,255,255,.76) !important;
            border-bottom: 1px solid rgba(15, 23, 42, .07) !important;
            padding: 20px 22px !important;
        }

        .table-card .card-header h5,
        .table-container .card-header h5,
        .card-header h5 {
            color: #0f172a;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: -.02em;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border: 0;
            color: #64748b;
            background: #f8fafc !important;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 14px 18px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 16px 18px;
            vertical-align: middle;
            border-color: #f1f5f9;
        }

        .table tbody tr {
            transition: background .2s ease;
        }

        .table tbody tr:hover {
            background: #fff7ed;
            transform: none !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #ef4444) !important;
            border: 0 !important;
            color: #fff !important;
            box-shadow: 0 12px 24px rgba(255, 107, 53, .20);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #dc2626) !important;
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(255, 107, 53, .26);
        }

        .btn-outline-primary,
        .btn-outline-info,
        .btn-outline-secondary,
        .btn-outline-danger,
        .btn-outline-success,
        .btn-outline-warning {
            border-radius: 12px;
            border-width: 1.5px;
            background: #fff;
        }

        .form-control,
        .form-select,
        .input-group-text {
            border-radius: 13px !important;
            border-color: #e2e8f0;
            background-color: #fff;
        }

        .badge,
        .badge-modern {
            border-radius: 999px !important;
            padding: 7px 11px;
            font-weight: 800;
        }

        .modal-content {
            border-radius: 26px !important;
            overflow: hidden;
            box-shadow: 0 30px 90px rgba(15, 23, 42, .24);
        }

        .modal-backdrop.show {
            background-color: rgba(0, 0, 0, 0.16) !important;
        }

        .modal-header {
            background: linear-gradient(135deg, #111827, var(--primary)) !important;
            color: #fff;
            border-bottom: 0;
        }

        .modal-header .modal-title,
        .modal-header h5 {
            color: #fff !important;
            font-weight: 900;
        }

        .alert {
            border-radius: 18px !important;
            border: 0 !important;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
        }

        .card-footer {
            border-top: 1px solid rgba(15, 23, 42, .07) !important;
            background: rgba(255,255,255,.82) !important;
        }

        .pagination .page-link {
            border-radius: 12px !important;
            margin: 0 3px;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 800;
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary), #ef4444) !important;
            border-color: transparent !important;
            color: #fff;
        }
        /* ========== MODERN RESTAURANT SIDEBAR + HEADER REFRESH ========== */
        .top-header {
            background: rgba(255, 255, 255, 0.84) !important;
            border-bottom: 1px solid rgba(226, 232, 240, 0.78) !important;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.07) !important;
            backdrop-filter: blur(18px);
            padding-inline: 28px;
        }

        .menu-toggle {
            width: 44px;
            height: 44px;
            border: 1px solid rgba(226, 232, 240, 0.9) !important;
            border-radius: 16px !important;
            background: rgba(255, 255, 255, 0.9) !important;
            color: #475569 !important;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .menu-toggle:hover,
        .header-icon-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px color-mix(in srgb, var(--primary) 14%, transparent);
        }

        .header-search-wrapper {
            max-width: 480px;
        }

        .header-search-wrapper input {
            min-height: 48px;
            border: 1px solid rgba(203, 213, 225, 0.9) !important;
            border-radius: 999px !important;
            background: rgba(248, 250, 252, 0.88) !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72);
        }

        .header-search-wrapper input:focus {
            background: #fff !important;
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 14%, transparent) !important;
        }

        .header-icon-btn {
            width: 46px;
            height: 46px;
            border: 1px solid rgba(203, 213, 225, 0.82) !important;
            border-radius: 16px !important;
            background: rgba(255, 255, 255, 0.86) !important;
        }

        .header-divider {
            background: linear-gradient(180deg, transparent, rgba(148, 163, 184, 0.46), transparent) !important;
        }

        .status-toggle-wrapper {
            border: 1px solid rgba(226, 232, 240, 0.82);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            padding: 8px 12px;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.06);
        }

        .user-profile-wrapper {
            border: 1px solid rgba(226, 232, 240, 0.82);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.72);
            padding: 6px 16px 6px 6px;
        }

        .user-profile-wrapper:hover {
            background: #fff;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
        }

        .user-avatar-lg {
            border-radius: 17px !important;
            background:
                radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.42), transparent 28%),
                linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
            box-shadow: 0 14px 28px color-mix(in srgb, var(--primary) 22%, transparent);
        }

        .sidebar {
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 20%, transparent), transparent 34%),
                linear-gradient(180deg, #111827 0%, #0f172a 54%, #1f2937 100%) !important;
            border-right: 1px solid rgba(255, 255, 255, 0.08) !important;
            box-shadow: 22px 0 60px rgba(15, 23, 42, 0.16);
        }

        .sidebar-logo-section {
            min-height: var(--topbar-height);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding-inline: 22px !important;
        }

        .sidebar-logo-icon {
            width: 50px !important;
            height: 50px !important;
            border-radius: 19px !important;
            background:
                radial-gradient(circle at 30% 18%, rgba(255, 255, 255, 0.45), transparent 28%),
                linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
            box-shadow: 0 18px 34px color-mix(in srgb, var(--primary) 26%, transparent);
        }

        .sidebar-logo-text h2 {
            color: #F8FAFC !important;
            font-size: 21px !important;
        }

        .sidebar-logo-text h2 span {
            color: #CBD5E1 !important;
        }

        .sidebar-logo-text small,
        .sidebar-section-title {
            color: #94A3B8 !important;
        }

        .sidebar-nav-wrapper {
            padding: 18px 14px 24px !important;
        }

        .sidebar-section-title {
            margin-top: 12px !important;
            padding: 10px 14px 6px !important;
            font-size: 10px !important;
            letter-spacing: 0.16em !important;
        }

        .sidebar-nav-item {
            margin-bottom: 6px !important;
        }

        .sidebar-nav-link {
            min-height: 46px;
            border-radius: 17px !important;
            color: rgba(226, 232, 240, 0.88) !important;
            font-weight: 700 !important;
            letter-spacing: -0.01em;
            padding: 12px 14px !important;
        }

        .sidebar-nav-link i {
            width: 24px !important;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: rgba(203, 213, 225, 0.9);
        }

        .sidebar-nav-link:hover {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #fff !important;
            transform: translateX(4px);
        }

        .sidebar-nav-link.active {
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 88%, #ffffff), color-mix(in srgb, var(--primary-dark) 82%, #111827)) !important;
            color: #fff !important;
            box-shadow: 0 16px 34px color-mix(in srgb, var(--primary) 26%, transparent);
        }

        .sidebar-nav-link.active i {
            color: #fff;
            background: rgba(255, 255, 255, 0.14);
        }

        .sidebar-nav-link.active::before {
            display: none;
        }

        .sidebar-badge,
        .badge-notification {
            border-radius: 999px !important;
            background: linear-gradient(135deg, var(--danger), #fb7185) !important;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.24);
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
        }

        .sidebar-restaurant-card {
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px !important;
            background: rgba(255, 255, 255, 0.08) !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .sidebar-restaurant-avatar {
            border-radius: 16px !important;
            background:
                radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.42), transparent 28%),
                linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
        }

        .sidebar-restaurant-info .restaurant-name,
        .restaurant-name {
            color: #F8FAFC !important;
        }

        .sidebar-restaurant-info .restaurant-status,
        .restaurant-status {
            color: #CBD5E1 !important;
        }

        .profile-dropdown-menu,
        .dropdown-menu {
            border: 1px solid rgba(226, 232, 240, 0.88) !important;
            border-radius: 22px !important;
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.16) !important;
        }

        @media (max-width: 992px) {
            .menu-toggle {
                display: inline-flex;
            }

            .top-header {
                padding-inline: 16px;
            }

            .user-profile-wrapper {
                padding: 4px;
            }
        }
    </style>
    
    @yield('styles')
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    
    <!-- Sidebar -->
    @include('restaurant.partials.sidebar')
    
    <!-- Top Header -->
    @include('restaurant.partials.header')
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-content">
            @yield('content')
        </div>
    </div>

    <!-- Order Toast Container -->
    <div id="orderToastContainer" class="order-toast-container"></div>
    @include('partials.direct-chat-widget')

    <!-- Reject Order Modal -->
    <div class="modal fade" id="rejectOrderModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 px-4 pt-4">
                    <h5 class="modal-title fw-bold">Reject Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Rejection</label>
                        <textarea id="rejectReason" class="form-control" rows="3" 
                                  placeholder="Please provide a reason for rejecting this order..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmRejectBtn" class="btn btn-danger rounded-3">
                        <i class="fas fa-times-circle me-2"></i> Reject Order
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ========== UI Helpers ==========
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdownMenu');
            dropdown.classList.toggle('show');
        }
        
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdownMenu');
            const userProfile = document.getElementById('userProfileButton');
            if (dropdown && userProfile && !userProfile.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        function toggleRestaurantStatus() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const indicator = document.getElementById('statusIndicator');
            const text = document.getElementById('statusLabel');
            
            fetch('{{ route("restaurant.toggle-status") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.is_open) {
                    indicator.className = 'status-indicator online';
                    text.textContent = 'Online';
                    showToastMessage('Restaurant is now Online', 'success');
                } else {
                    indicator.className = 'status-indicator offline';
                    text.textContent = 'Offline';
                    showToastMessage('Restaurant is now Offline', 'warning');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.transition = 'opacity 0.3s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 4000);
        
        // ========== Toast Message Helper ==========
        function showToastMessage(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `custom-toast-message toast-${type}`;
            toast.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="fas ${getToastIcon(type)}"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('toast-slide-out');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function getToastIcon(type) {
            switch(type) {
                case 'success': return 'fa-check-circle';
                case 'error': return 'fa-exclamation-circle';
                case 'warning': return 'fa-exclamation-triangle';
                default: return 'fa-info-circle';
            }
        }
        
        // ========== Real-time Order Manager ==========
        class RealTimeOrderManager {
            constructor() {
                this.pollingInterval = null;
                this.lastCheckTime = null;
                this.pendingOrders = new Map();
                this.pollingFrequency = 5000; // 5 seconds
                this.audioContext = null;
                this.toastContainer = document.getElementById('orderToastContainer');
                this.currentOrderForReject = null;
                this.init();
            }
            
            init() {
                // Initialize last check time
                this.lastCheckTime = new Date();
                this.lastCheckTime.setMinutes(this.lastCheckTime.getMinutes() - 5);
                
                // Initialize audio
                this.initAudio();
                
                // Start polling
                this.startPolling();
                
                // Listen for page visibility
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        this.refreshCounts();
                    }
                });
                
                // Request notification permission
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
                
                // Initialize reject modal handler
                this.initRejectModal();
                
                console.log('Real-time order manager initialized');
            }
            
            initAudio() {
                // Create a beep sound using Web Audio API
                this.audioContext = null;
                this.useWebAudio = true;
            }
            
            initAudioContext() {
                if (this.audioContext) return;
                try {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                } catch(e) {
                    this.useWebAudio = false;
                    console.log('Web Audio API not supported');
                }
            }
            
            playNotificationSound() {
                if (!this.useWebAudio) return;
                
                if (!this.audioContext) {
                    this.initAudioContext();
                }
                
                if (this.audioContext && this.audioContext.state === 'suspended') {
                    this.audioContext.resume();
                }
                
                try {
                    const oscillator = this.audioContext.createOscillator();
                    const gainNode = this.audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(this.audioContext.destination);
                    
                    oscillator.frequency.value = 880;
                    gainNode.gain.value = 0.3;
                    
                    oscillator.start();
                    gainNode.gain.exponentialRampToValueAtTime(0.00001, this.audioContext.currentTime + 0.5);
                    oscillator.stop(this.audioContext.currentTime + 0.5);
                } catch(e) {
                    console.log('Could not play sound:', e);
                }
            }
            
            startPolling() {
                this.pollingInterval = setInterval(() => {
                    this.checkNewOrders();
                }, this.pollingFrequency);
            }
            
            async checkNewOrders() {
                try {
                    const response = await fetch(`/restaurant/orders/check-new?last_check=${encodeURIComponent(this.lastCheckTime.toISOString())}`, {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    
                    if (!response.ok) throw new Error('Network response was not ok');
                    
                    const data = await response.json();
                    
                    if (data.success && data.new_orders && data.new_orders.length > 0) {
                        data.new_orders.forEach(order => {
                            if (!this.pendingOrders.has(order.id)) {
                                this.pendingOrders.set(order.id, order);
                                this.showOrderNotification(order);
                                this.playNotificationSound();
                            }
                        });
                    }
                    
                    if (data.server_time) {
                        this.lastCheckTime = new Date(data.server_time);
                    }
                    
                    this.updatePendingBadge(data.pending_count || 0);
                    
                } catch (error) {
                    console.error('Error checking orders:', error);
                }
            }
            
            async refreshCounts() {
                try {
                    const response = await fetch('/restaurant/orders/counts', {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    
                    if (!response.ok) throw new Error('Network response was not ok');
                    
                    const counts = await response.json();
                    this.updateDashboardStats(counts);
                    
                } catch (error) {
                    console.error('Error refreshing counts:', error);
                }
            }
            
            showOrderNotification(order) {
                // Show browser notification if page is hidden
                if (document.hidden && Notification.permission === 'granted') {
                    new Notification('New Order Received!', {
                        body: `Order #${order.id} from ${order.customer_name} - ${this.formatCurrency(order.total)}`,
                        icon: @json(App\Models\AppSetting::getValue('app_favicon') ? \Illuminate\Support\Facades\Storage::disk('public')->url(App\Models\AppSetting::getValue('app_favicon')) : asset('favicon.ico')),
                        tag: `order-${order.id}`
                    });
                }
                
                // Create toast notification
                const toast = document.createElement('div');
                toast.className = 'order-toast';
                toast.dataset.orderId = order.id;
                toast.innerHTML = `
                    <div class="order-toast-header">
                        <div class="d-flex align-items-center gap-2">
                            <div class="order-toast-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <strong class="order-toast-title">New Order Received!</strong>
                        </div>
                        <button class="order-toast-close" onclick="this.closest('.order-toast').remove()">&times;</button>
                    </div>
                    <div class="order-toast-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-6">Order #${order.id}</div>
                                <div class="small text-muted">${this.escapeHtml(order.customer_name)} • ${order.items_count} items</div>
                                ${order.items_preview ? `<div class="small text-muted mt-1">${this.escapeHtml(order.items_preview)}</div>` : ''}
                                <div class="fw-bold text-primary mt-2">${this.formatCurrency(order.total)}</div>
                            </div>
                            <div class="order-toast-actions">
                                <button class="btn-accept-order" data-order-id="${order.id}">
                                    <i class="fas fa-check me-1"></i> Accept
                                </button>
                                <button class="btn-reject-order" data-order-id="${order.id}">
                                    <i class="fas fa-times me-1"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Add event listeners
                const acceptBtn = toast.querySelector('.btn-accept-order');
                const rejectBtn = toast.querySelector('.btn-reject-order');
                
                acceptBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.acceptOrder(order.id, toast);
                });
                
                rejectBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.showRejectModal(order.id, toast);
                });
                
                // Add to container
                if (this.toastContainer) {
                    this.toastContainer.appendChild(toast);
                } else {
                    document.body.appendChild(toast);
                }
                
                // Auto remove after 25 seconds
                setTimeout(() => {
                    if (toast && toast.parentNode) {
                        toast.classList.add('toast-slide-out');
                        setTimeout(() => toast.remove(), 300);
                    }
                }, 25000);
            }
            
            async acceptOrder(orderId, toastElement) {
                try {
                    const response = await fetch(`/restaurant/orders/${orderId}/accept`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        if (toastElement) toastElement.remove();
                        this.pendingOrders.delete(orderId);
                        showToastMessage('Order accepted successfully!', 'success');
                        
                        // Refresh current page if on orders page
                        if (window.location.pathname.includes('/restaurant/orders')) {
                            setTimeout(() => location.reload(), 500);
                        } else {
                            this.refreshCounts();
                        }
                    } else {
                        showToastMessage(data.message || 'Failed to accept order', 'error');
                    }
                } catch (error) {
                    console.error('Error accepting order:', error);
                    showToastMessage('Failed to accept order. Please try again.', 'error');
                }
            }
            
            showRejectModal(orderId, toastElement) {
                this.currentOrderForReject = { orderId, toastElement };
                const modalElement = document.getElementById('rejectOrderModal');
                const modal = new bootstrap.Modal(modalElement);
                const reasonTextarea = document.getElementById('rejectReason');
                if (reasonTextarea) reasonTextarea.value = '';
                modal.show();
            }
            
            initRejectModal() {
                const confirmBtn = document.getElementById('confirmRejectBtn');
                if (confirmBtn) {
                    confirmBtn.addEventListener('click', async () => {
                        if (!this.currentOrderForReject) return;
                        
                        const reason = document.getElementById('rejectReason')?.value.trim();
                        if (!reason) {
                            showToastMessage('Please provide a reason for rejection', 'warning');
                            return;
                        }
                        
                        try {
                            const response = await fetch(`/restaurant/orders/${this.currentOrderForReject.orderId}/reject`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ reason: reason })
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                if (this.currentOrderForReject.toastElement) {
                                    this.currentOrderForReject.toastElement.remove();
                                }
                                this.pendingOrders.delete(this.currentOrderForReject.orderId);
                                
                                const modal = bootstrap.Modal.getInstance(document.getElementById('rejectOrderModal'));
                                modal.hide();
                                
                                showToastMessage('Order rejected successfully!', 'warning');
                                
                                if (window.location.pathname.includes('/restaurant/orders')) {
                                    setTimeout(() => location.reload(), 500);
                                } else {
                                    this.refreshCounts();
                                }
                            } else {
                                showToastMessage(data.message || 'Failed to reject order', 'error');
                            }
                        } catch (error) {
                            console.error('Error rejecting order:', error);
                            showToastMessage('Failed to reject order. Please try again.', 'error');
                        } finally {
                            this.currentOrderForReject = null;
                        }
                    });
                }
            }
            
            updatePendingBadge(count) {
                const badge = document.getElementById('pendingOrdersBadge');
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.style.display = 'flex';
                        badge.classList.add('badge-pulse');
                        setTimeout(() => badge.classList.remove('badge-pulse'), 500);
                    } else {
                        badge.style.display = 'none';
                    }
                }
                
                // Update title
                if (count > 0) {
                    document.title = `(${count}) ${document.title.replace(/^\(\d+\)\s/, '')}`;
                } else {
                    document.title = document.title.replace(/^\(\d+\)\s/, '');
                }
            }
            
            updateDashboardStats(counts) {
                // Update pending orders card count
                const pendingCard = document.querySelector('.stat-card .pending-count');
                if (pendingCard && counts.pending !== undefined) {
                    pendingCard.textContent = counts.pending;
                }
                
                // Update today's orders count
                const todayOrdersCard = document.querySelector('.stat-card .today-orders');
                if (todayOrdersCard && counts.total_today !== undefined) {
                    todayOrdersCard.textContent = counts.total_today;
                }
            }
            
            escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            formatCurrency(value) {
                let symbol = window.currencySymbol || '₹';
                if (String(symbol).includes('{{') || String(symbol).includes('currencySymbol') || String(symbol).includes('â')) {
                    symbol = '₹';
                }

                if (String(symbol).includes('â') || String(symbol).includes('Ã¢')) {
                    symbol = String.fromCharCode(0x20B9);
                }

                if (String(symbol).charCodeAt(0) === 226 || String(symbol).charCodeAt(0) === 195) {
                    symbol = String.fromCharCode(0x20B9);
                }

                    const amount = Number.parseFloat(value);
                    const decimals = Number.isInteger(window.currencyDecimals) ? parseInt(window.currencyDecimals) : 2;
                    const pad = (n, d) => n.toFixed(d);
                    return `${symbol}${Number.isFinite(amount) ? amount.toFixed(decimals) : (decimals ? (0).toFixed(decimals) : '0')}`;
            }
            
            stopPolling() {
                if (this.pollingInterval) {
                    clearInterval(this.pollingInterval);
                }
            }
        }
    </script>
    
    @include('partials.web-visit-tracker', ['panel' => 'restaurant'])
    @yield('scripts')
</body>
</html>
