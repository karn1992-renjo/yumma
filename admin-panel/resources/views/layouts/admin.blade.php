<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');
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
    <title>@yield('title') | Super Admin Panel</title>
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
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: {{ $primaryColor ?? '#8B5CF6' }};
            --primary-light: {{ $primaryLight ?? '#A78BFA' }};
            --primary-dark: {{ $primaryDark ?? '#7C3AED' }};
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
                radial-gradient(circle at top left, rgba(139, 92, 246, 0.13), transparent 32%),
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.12), transparent 28%),
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

        .top-header.scrolled {
            background: var(--primary);
            border-color: transparent;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
        }

        .top-header.scrolled .menu-toggle,
        .top-header.scrolled .header-icon-btn,
        .top-header.scrolled .user-profile-wrapper {
            border-color: rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.10);
            color: #fff;
        }

        .top-header.scrolled .header-search-wrapper input {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.22);
            color: #fff;
        }

        .top-header.scrolled .header-search-wrapper .search-icon,
        .top-header.scrolled .header-divider,
        .top-header.scrolled .user-profile-wrapper .user-name,
        .top-header.scrolled .user-profile-wrapper .user-role {
            color: #fff;
        }

        .top-header.scrolled .header-divider {
            background: rgba(255, 255, 255, 0.32);
        }

        .top-header.scrolled .header-search-wrapper input::placeholder {
            color: rgba(255, 255, 255, 0.72);
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
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
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
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.35) transparent;
        }

        .sidebar::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 999px;
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
            overflow: hidden;
        }

        .sidebar-logo-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .sidebar-logo-text h2 {
            font-size: 20px;
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
            font-size: 10px;
            color: #64748B;
            font-weight: 500;
        }

        .sidebar-nav-wrapper {
            flex: 1;
            padding: 16px 12px 32px;
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
        }

        .sidebar-nav-link:hover {
            background: #F8FAFC;
            color: #334155;
        }

        .sidebar-nav-link.active {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05));
            color: var(--primary);
            font-weight: 600;
        }

        .sidebar-parent-link {
            cursor: pointer;
        }

        .sidebar-submenu {
            list-style: none;
            padding-left: 16px;
            margin-top: 4px;
            display: none;
        }

        .sidebar-submenu.open {
            display: block;
        }

        .sidebar-submenu .sidebar-nav-link {
            padding-left: 34px;
            font-size: 13px;
            color: #475569;
        }

        .sidebar-submenu .sidebar-nav-link:hover {
            background: #EFF6FF;
            color: #1D4ED8;
        }

        .sidebar-submenu .sidebar-nav-link.active {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
        }

        .sidebar-submenu-toggle {
            margin-left: auto;
            transition: transform 0.2s ease;
        }

        .sidebar-parent-link.open .sidebar-submenu-toggle {
            transform: rotate(180deg);
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

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--topbar-height);
            min-height: calc(100vh - var(--topbar-height));
            transition: all 0.3s ease;
            position: relative;
            z-index: auto;
            width: calc(100% - var(--sidebar-width));
            overflow-x: hidden;
        }

        .page-content {
            padding: 28px;
            min-width: 0;
        }

        /* ========== CUSTOM TOAST ========== */
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
                width: 100%;
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
                pointer-events: none;
            }

            .sidebar-overlay.show {
                display: block;
                pointer-events: auto;
            }
        }

        @media (max-width: 640px) {
            .page-content {
                padding: 16px;
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

        .stat-card .icon.primary { background: rgba(139, 92, 246, 0.1); color: var(--primary); }
        .stat-card .icon.success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .stat-card .icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-card .icon.info { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        .stat-card .icon.danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

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

        /* ========== FOODFLOW ADMIN DESIGN SYSTEM ========== */
        .ff-page-shell {
            display: grid;
            gap: 24px;
        }

        .ff-page-hero {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px;
            color: #fff;
            background:
                radial-gradient(circle at 10% 18%, rgba(255,255,255,.24), transparent 24%),
                radial-gradient(circle at 92% 12%, rgba(251,191,36,.28), transparent 24%),
                linear-gradient(135deg, #111827 0%, #7c2d12 48%, #f97316 100%);
            box-shadow: 0 22px 60px rgba(249, 115, 22, .22);
        }

        .ff-page-hero::after {
            content: "";
            position: absolute;
            right: -80px;
            bottom: -120px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,.15);
        }

        .ff-page-hero > * {
            position: relative;
            z-index: 1;
        }

        .ff-hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px;
            border-radius: 999px;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.22);
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .ff-page-hero h1 {
            font-size: clamp(28px, 3vw, 40px);
            line-height: 1.08;
            font-weight: 900;
            letter-spacing: -.04em;
            margin: 0;
        }

        .ff-page-hero p {
            color: rgba(255,255,255,.78);
            max-width: 680px;
            margin: 10px 0 0;
        }

        .ff-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .ff-stat-tile {
            position: relative;
            overflow: hidden;
            min-height: 138px;
            padding: 20px;
            border-radius: 24px;
            border: 1px solid rgba(15, 23, 42, .07);
            background: #fff;
            box-shadow: 0 16px 42px rgba(15, 23, 42, .06);
        }

        .ff-stat-tile::after {
            content: "";
            position: absolute;
            right: -34px;
            top: -34px;
            width: 104px;
            height: 104px;
            border-radius: 50%;
            opacity: .12;
            background: var(--tile-color, #f97316);
        }

        .ff-stat-icon {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            color: #fff;
            background: var(--tile-color, #f97316);
            box-shadow: 0 12px 24px rgba(15, 23, 42, .12);
        }

        .ff-stat-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            margin-top: 16px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .ff-stat-value {
            color: #0f172a;
            font-size: 27px;
            font-weight: 900;
            letter-spacing: -.03em;
            margin-top: 3px;
        }

        .ff-card {
            border-radius: 26px;
            border: 1px solid rgba(15, 23, 42, .08);
            background: rgba(255,255,255,.9);
            box-shadow: 0 18px 50px rgba(15, 23, 42, .07);
            overflow: hidden;
        }

        .ff-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 20px 22px;
            border-bottom: 1px solid rgba(15, 23, 42, .07);
            background: rgba(255,255,255,.74);
        }

        .ff-card-title {
            margin: 0;
            color: #0f172a;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: -.02em;
        }

        .ff-card-subtitle {
            color: #64748b;
            font-size: 12px;
            margin-top: 3px;
        }

        .ff-filter-card {
            padding: 20px;
        }

        .ff-table {
            margin: 0;
        }

        .ff-table thead th {
            border: 0;
            color: #64748b;
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 14px 18px;
            white-space: nowrap;
        }

        .ff-table tbody td {
            padding: 16px 18px;
            vertical-align: middle;
            border-color: #f1f5f9;
        }

        .ff-table tbody tr {
            transition: background .2s ease, transform .2s ease;
        }

        .ff-table tbody tr:hover {
            background: #fff7ed;
        }

        .ff-action-btn {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            text-decoration: none;
            transition: all .2s ease;
        }

        .ff-action-btn:hover {
            transform: translateY(-1px);
            border-color: #fdba74;
            color: #ea580c;
            box-shadow: 0 10px 22px rgba(249, 115, 22, .14);
        }

        .ff-action-btn.danger:hover {
            border-color: #fecaca;
            color: #dc2626;
            box-shadow: 0 10px 22px rgba(239, 68, 68, .12);
        }

        .ff-soft-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            background: #f1f5f9;
            color: #475569;
        }

        .ff-soft-badge.success { background: #dcfce7; color: #166534; }
        .ff-soft-badge.warning { background: #fef3c7; color: #92400e; }
        .ff-soft-badge.danger { background: #fee2e2; color: #991b1b; }
        .ff-soft-badge.info { background: #dbeafe; color: #1d4ed8; }

        .ff-empty-state {
            padding: 56px 20px;
            text-align: center;
            color: #64748b;
        }

        .ff-empty-state i {
            font-size: 42px;
            opacity: .45;
            margin-bottom: 14px;
        }

        .fw-black {
            font-weight: 900;
        }

        @media (max-width: 1199px) {
            .ff-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 767px) {
            .ff-page-hero { padding: 22px; border-radius: 22px; }
            .ff-stat-grid { grid-template-columns: 1fr; }
            .ff-card-header { align-items: flex-start; flex-direction: column; }
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
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-warning { background: #FEF3C7; color: #92400E; }
        .badge-danger { background: #FEE2E2; color: #991B1B; }
        .badge-info { background: #E0E7FF; color: #3730A3; }
        .badge-primary { background: #E0E7FF; color: #3730A3; }

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
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
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
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        /* Pagination */
        .pagination {
            gap: 5px;
        }
        .pagination .page-link {
            border-radius: 10px;
            color: #64748B;
            border: 1px solid var(--border);
            padding: 8px 14px;
        }
        .pagination .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* ========== GLOBAL LEGACY BLADE MODERNIZATION ========== */
        .page-content {
            background:
                radial-gradient(circle at 12% 0%, rgba(249, 115, 22, .08), transparent 26%),
                radial-gradient(circle at 88% 12%, rgba(59, 130, 246, .07), transparent 24%),
                #f8fafc;
        }

        .page-header:not(.ff-page-hero) {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 26px;
            color: #fff;
            background:
                radial-gradient(circle at 12% 18%, rgba(255,255,255,.22), transparent 24%),
                radial-gradient(circle at 88% 12%, rgba(251,191,36,.26), transparent 24%),
                linear-gradient(135deg, #111827 0%, #7c2d12 48%, #f97316 100%);
            box-shadow: 0 22px 60px rgba(249, 115, 22, .20);
        }

        .page-header:not(.ff-page-hero)::after {
            content: "";
            position: absolute;
            right: -80px;
            bottom: -120px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,.14);
        }

        .page-header:not(.ff-page-hero) > * {
            position: relative;
            z-index: 1;
        }

        .page-header:not(.ff-page-hero) h1,
        .page-header:not(.ff-page-hero) .display-5 {
            color: #fff !important;
            background: none !important;
            -webkit-text-fill-color: #fff !important;
            font-size: clamp(28px, 3vw, 40px) !important;
            line-height: 1.08;
            font-weight: 900 !important;
            letter-spacing: -.04em;
            margin-bottom: 8px;
        }

        .page-header:not(.ff-page-hero) p,
        .page-header:not(.ff-page-hero) .text-muted {
            color: rgba(255,255,255,.78) !important;
        }

        .page-header:not(.ff-page-hero) .btn:not(.btn-light) {
            border-color: rgba(255,255,255,.35);
            background: rgba(255,255,255,.14);
            color: #fff;
            backdrop-filter: blur(12px);
        }

        .page-header:not(.ff-page-hero) .btn-light {
            color: #111827;
            border: 0;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .16);
        }

        .stat-card:not(.ff-stat-tile),
        .stat-card-modern,
        .filter-section,
        .table-container,
        .card:not(.modal-content):not(.profile-dropdown-menu),
        .card-modern {
            border-radius: 26px !important;
            border: 1px solid rgba(15, 23, 42, .08) !important;
            background: rgba(255,255,255,.92) !important;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .07) !important;
            overflow: hidden;
        }

        .stat-card:not(.ff-stat-tile),
        .stat-card-modern {
            position: relative;
        }

        .stat-card:not(.ff-stat-tile)::after,
        .stat-card-modern::after {
            content: "";
            position: absolute;
            right: -36px;
            top: -36px;
            width: 108px;
            height: 108px;
            border-radius: 50%;
            background: rgba(249, 115, 22, .12);
            pointer-events: none;
        }

        .stat-card:not(.ff-stat-tile):hover,
        .stat-card-modern:hover,
        .table-card:hover,
        .filter-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 60px rgba(15, 23, 42, .10) !important;
        }

        .table-card:not(.ff-card) {
            border-radius: 26px !important;
            border: 1px solid rgba(15, 23, 42, .08) !important;
            background: rgba(255,255,255,.92) !important;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .07) !important;
            overflow: hidden;
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

        .table:not(.ff-table) {
            margin-bottom: 0;
        }

        .table:not(.ff-table) thead th {
            border: 0;
            color: #64748b;
            background: #f8fafc !important;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 14px 18px;
            white-space: nowrap;
        }

        .table:not(.ff-table) tbody td {
            padding: 16px 18px;
            vertical-align: middle;
            border-color: #f1f5f9;
        }

        .table:not(.ff-table) tbody tr {
            transition: background .2s ease;
        }

        .table:not(.ff-table) tbody tr:hover {
            background: #fff7ed;
            transform: none !important;
        }

        .btn-primary,
        .btn-modern-primary {
            background: linear-gradient(135deg, #f97316, #ef4444) !important;
            border: 0 !important;
            color: #fff !important;
            box-shadow: 0 12px 24px rgba(249, 115, 22, .20);
        }

        .btn-primary:hover,
        .btn-modern-primary:hover {
            background: linear-gradient(135deg, #ea580c, #dc2626) !important;
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(249, 115, 22, .26);
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

        .btn-sm {
            border-radius: 11px;
        }

        .form-control,
        .form-select,
        .input-group-text {
            border-radius: 13px !important;
            border-color: #e2e8f0;
            background-color: #fff;
        }

        .input-group .form-control {
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
        }

        .input-group .input-group-text {
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
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

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.18) !important;
        }

        .modal-backdrop.show {
            opacity: 1 !important;
        }

        .modal-header {
            background: linear-gradient(135deg, #111827, #f97316) !important;
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
            background: linear-gradient(135deg, #f97316, #ef4444) !important;
            border-color: transparent !important;
            color: #fff;
        }
        /* ========== MODERN SIDEBAR + HEADER REFRESH ========== */
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
                linear-gradient(180deg, #0f172a 0%, #111827 54%, #1e293b 100%) !important;
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

        .sidebar-submenu {
            border-left: 1px solid rgba(255, 255, 255, 0.12);
            margin-left: 22px !important;
            padding-left: 10px !important;
        }

        .sidebar-submenu .sidebar-nav-link {
            min-height: 38px;
            font-size: 13px !important;
            color: rgba(203, 213, 225, 0.78) !important;
            padding-left: 12px !important;
        }

        .sidebar-badge,
        .badge-notification {
            border-radius: 999px !important;
            background: linear-gradient(135deg, var(--danger), #fb7185) !important;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.24);
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
    @if(auth()->check() && request()->routeIs('branch.*') && auth()->user()->hasAnyRole(['branch_owner', 'branch_manager', 'branch_staff']))
        @include('branch._sidebar')
    @else
        @include('admin.partials.sidebar')
    @endif
    
    <!-- Top Header -->
    @include('admin.partials.header')
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-content">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            
            @yield('content')
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
        
        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.transition = 'opacity 0.3s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 4000);
        
        // Toast Message Helper
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
        
        // Confirm delete
        function confirmDelete(formId, message = 'Are you sure you want to delete this item?') {
            if (confirm(message)) {
                document.getElementById(formId).submit();
            }
        }

        function toggleSettingsSubmenu(event) {
            event.preventDefault();
            const parentItem = event.currentTarget.closest('.sidebar-nav-item');
            const submenu = parentItem?.querySelector('.sidebar-submenu');
            if (!submenu) return;
            submenu.classList.toggle('open');
            event.currentTarget.classList.toggle('open');
        }

        function updateHeaderScrollState() {
            const header = document.querySelector('.top-header');
            if (!header) return;
            if (window.scrollY > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }

        window.addEventListener('scroll', updateHeaderScrollState);
        document.addEventListener('DOMContentLoaded', updateHeaderScrollState);
    </script>
    
    @include('partials.web-visit-tracker', ['panel' => 'admin'])
    @include('partials.direct-chat-widget')
    @yield('scripts')
</body>
</html>
