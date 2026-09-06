<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ \App\Models\AppSetting::getValue('primary_color', '#4f46e5') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    @php
        $appName = \App\Models\AppSetting::getValue('app_name', config('app.name', 'App'));
        $appLogo = \App\Models\AppSetting::getValue('app_logo', null);
        $appFavicon = \App\Models\AppSetting::getValue('app_favicon', null);
        $appIcon = \App\Models\AppSetting::getValue('app_icon', null);
        $headerBrandingType = \App\Models\AppSetting::getValue('header_branding_type', 'text');
        $primaryColor = \App\Models\AppSetting::getValue('primary_color', '#4f46e5');
        $primaryColorDark = \App\Models\AppSetting::getValue('primary_color_dark', '#4338ca');
        $headerBrandingType = in_array($headerBrandingType, ['text', 'logo', 'logo_text']) ? $headerBrandingType : 'text';

        // Resolve asset URLs
        $logoUrl = $appLogo ? \App\Services\MediaStorage::url($appLogo) : asset('images/logo.png');
        $faviconUrl = $appFavicon ? \App\Services\MediaStorage::url($appFavicon) : asset('favicon.ico');
        $iconUrl = $appIcon ? \App\Services\MediaStorage::url($appIcon) : asset('icon/icon-192x192.png');
    @endphp
    <meta name="apple-mobile-web-app-title" content="{{ $appName }}">

    <title>Login - {{ $appName }}</title>
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $iconUrl }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ==========================================
           LOGIN PAGE
           ========================================== */
        :root {
            --fm-primary: {{ $primaryColor }};
            --fm-primary-dark: {{ $primaryColorDark }};
            --fm-primary-light: #EEF2FF;
            --fm-bg: #F1F5F9;
            --fm-card-bg: #ffffff;
            --fm-panel-bg: #FAFBFC;
            --fm-text: #0F172A;
            --fm-text-secondary: #64748B;
            --fm-text-light: #94A3B8;
            --fm-border: #E2E8F0;
            --fm-shadow: 0 1px 3px rgba(15, 23, 42, .06);
            --fm-shadow-lg: 0 30px 70px rgba(15, 23, 42, .14);
            --fm-radius: 12px;
            --fm-radius-sm: 8px;
            --fm-radius-lg: 24px;
            --fm-radius-full: 9999px;
            --fm-transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --fm-safe-top: env(safe-area-inset-top, 0px);
            --fm-safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--fm-bg);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ==========================================
           PAGE LAYOUT (centered card, split panels)
           ========================================== */
        .fm-auth-page {
            width: 100%;
            max-width: 1180px;
            min-height: 640px;
            display: grid;
            grid-template-columns: 5fr 7fr;
            background: var(--fm-card-bg);
            border: 1px solid var(--fm-border);
            border-radius: var(--fm-radius-lg);
            box-shadow: var(--fm-shadow-lg);
            overflow: hidden;
        }

        /* ==========================================
           HERO / BRANDING SECTION (left)
           ========================================== */
        .fm-auth-hero {
            position: relative;
            background: var(--fm-card-bg);
            color: var(--fm-text);
            padding: 56px 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-right: 1px solid var(--fm-border);
        }

        .fm-auth-hero::before,
        .fm-auth-hero::after {
            content: '';
            position: absolute;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            filter: blur(60px);
            opacity: .7;
            pointer-events: none;
        }

        .fm-auth-hero::before {
            top: -90px;
            left: -90px;
            background: var(--fm-primary-light);
        }

        .fm-auth-hero::after {
            bottom: -90px;
            right: -90px;
            background: #ECFDF5;
        }

        .fm-auth-hero-content {
            position: relative;
            z-index: 1;
            max-width: 380px;
            margin: 0 auto;
            width: 100%;
            text-align: center;
        }

        .fm-auth-hero-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }

        .fm-auth-hero-logo img {
            height: 84px;
            width: auto;
            object-fit: contain;
            max-width: 220px;
        }

        .fm-auth-hero-logo span {
            font-size: 28px;
            font-weight: 800;
            color: var(--fm-text);
            letter-spacing: -0.02em;
        }

        .fm-auth-hero-logo span small {
            font-weight: 400;
            color: var(--fm-text-light);
        }

        .fm-auth-hero h1 {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.35;
            color: var(--fm-text);
            margin-bottom: 10px;
            letter-spacing: -0.01em;
        }

        .fm-auth-hero p {
            font-size: 14px;
            color: var(--fm-text-secondary);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .fm-auth-hero-features {
            display: flex;
            flex-direction: column;
            gap: 18px;
            text-align: left;
        }

        .fm-auth-hero-feature {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .fm-auth-hero-feature .fm-feature-icon {
            width: 38px;
            height: 38px;
            border-radius: var(--fm-radius-sm);
            background: var(--fm-primary-light);
            color: var(--fm-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 15px;
        }

        .fm-auth-hero-feature h3 {
            font-size: 14px;
            font-weight: 600;
            color: var(--fm-text);
            margin-bottom: 2px;
        }

        .fm-auth-hero-feature p {
            font-size: 12.5px;
            color: var(--fm-text-secondary);
            margin-bottom: 0;
        }

        /* ==========================================
           PANEL SECTION (right, form)
           ========================================== */
        .fm-auth-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 44px;
            background: var(--fm-panel-bg);
        }

        .fm-auth-card {
            width: 100%;
            max-width: 400px;
            animation: fmFadeInUp 0.4s ease;
        }

        @keyframes fmFadeInUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fm-auth-card-header {
            margin-bottom: 24px;
        }

        .fm-auth-card-header h2 {
            font-size: 24px;
            font-weight: 800;
            color: var(--fm-text);
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }

        .fm-auth-card-header p {
            font-size: 13.5px;
            color: var(--fm-text-secondary);
        }

        /* ==========================================
           ALERTS
           ========================================== */
        .fm-auth-alert {
            padding: 12px 16px;
            border-radius: var(--fm-radius);
            margin-bottom: 18px;
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 13px;
            font-weight: 500;
        }

        .fm-auth-alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #B91C1C;
        }

        .fm-auth-alert-success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #15803D;
        }

        .fm-auth-alert i {
            font-size: 16px;
            flex-shrink: 0;
        }

        /* ==========================================
           TOGGLE
           ========================================== */
        .fm-auth-toggle {
            display: flex;
            gap: 4px;
            background: var(--fm-bg);
            padding: 4px;
            border-radius: var(--fm-radius-full);
            margin-bottom: 22px;
        }

        .fm-auth-toggle-btn {
            flex: 1;
            border: none;
            background: transparent;
            border-radius: var(--fm-radius-full);
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--fm-text-secondary);
            cursor: pointer;
            transition: all var(--fm-transition);
            font-family: inherit;
        }

        .fm-auth-toggle-btn.active {
            background: var(--fm-card-bg);
            color: var(--fm-primary);
            box-shadow: var(--fm-shadow);
        }

        .fm-auth-toggle-btn:active {
            transform: scale(0.96);
        }

        /* ==========================================
           FORMS
           ========================================== */
        .fm-auth-form {
            display: none;
        }

        .fm-auth-form.active {
            display: block;
        }

        .fm-auth-form-group {
            margin-bottom: 16px;
        }

        .fm-auth-form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .fm-auth-input-wrap {
            position: relative;
        }

        .fm-auth-input-wrap > i:not(.fm-toggle-password) {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--fm-text-light);
            font-size: 15px;
        }

        .fm-auth-input-wrap select {
            padding-left: 44px !important;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            cursor: pointer;
        }

        .fm-auth-input {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 1px solid #D1D5DB;
            border-radius: var(--fm-radius);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: var(--fm-text);
            background: #fff;
            transition: all var(--fm-transition);
            outline: none;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .fm-auth-input:focus {
            border-color: var(--fm-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--fm-primary) 16%, transparent);
        }

        .fm-auth-input::placeholder {
            color: var(--fm-text-light);
        }

        .fm-auth-input.is-invalid {
            border-color: #ef4444;
        }

        .fm-auth-input.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
        }

        .fm-auth-error {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: #ef4444;
        }

        .fm-toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--fm-text-light);
            font-size: 15px;
            transition: color var(--fm-transition);
            z-index: 2;
        }

        .fm-toggle-password:hover {
            color: var(--fm-primary);
        }

        /* ==========================================
           CHECKBOX ROW
           ========================================== */
        .fm-auth-checkbox-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .fm-auth-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            color: var(--fm-text-secondary);
        }

        .fm-auth-checkbox input[type="checkbox"] {
            accent-color: var(--fm-primary);
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .fm-auth-link {
            color: var(--fm-primary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color var(--fm-transition);
        }

        .fm-auth-link:hover {
            color: var(--fm-primary-dark);
            text-decoration: underline;
        }

        /* ==========================================
           SUBMIT BUTTON
           ========================================== */
        .fm-auth-submit {
            width: 100%;
            border: none;
            border-radius: var(--fm-radius);
            padding: 13px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            color: white;
            background: linear-gradient(135deg, var(--fm-primary), var(--fm-primary-dark));
            cursor: pointer;
            transition: all var(--fm-transition);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--fm-primary) 28%, transparent);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fm-auth-submit span::after {
            content: '\2192';
            margin-left: 8px;
            display: inline-block;
            transition: transform var(--fm-transition);
        }

        .fm-auth-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 30px color-mix(in srgb, var(--fm-primary) 34%, transparent);
        }

        .fm-auth-submit:hover span::after {
            transform: translateX(3px);
        }

        .fm-auth-submit:active {
            transform: scale(0.98);
        }

        .fm-auth-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .fm-auth-submit .fm-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: fmSpin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }

        @keyframes fmSpin {
            to { transform: rotate(360deg); }
        }

        /* ==========================================
           FOOTER TEXT
           ========================================== */
        .fm-auth-footer {
            margin-top: 18px;
            text-align: center;
            color: var(--fm-text-secondary);
            font-size: 13px;
        }

        /* ==========================================
           MOBILE LOGO (visible on small screens)
           ========================================== */
        .fm-auth-mobile-logo {
            display: none;
            text-align: center;
            margin-bottom: 24px;
        }

        .fm-auth-mobile-logo img {
            height: 52px;
            width: auto;
            max-width: 160px;
            object-fit: contain;
        }

        .fm-auth-mobile-logo span {
            font-size: 26px;
            font-weight: 800;
            color: var(--fm-text);
            letter-spacing: -0.02em;
        }

        .fm-auth-mobile-logo span small {
            font-weight: 400;
            color: var(--fm-text-light);
        }

        /* ==========================================
           RESPONSIVE
           ========================================== */
        @media (max-width: 968px) {
            body { padding: 0; }

            .fm-auth-page {
                grid-template-columns: 1fr;
                border-radius: 0;
                border: none;
                box-shadow: none;
                min-height: 100vh;
                min-height: 100dvh;
            }

            .fm-auth-hero {
                display: none;
            }

            .fm-auth-mobile-logo {
                display: block;
            }

            .fm-auth-panel {
                min-height: 100vh;
                min-height: 100dvh;
                padding: 24px 16px;
                background: var(--fm-bg);
            }

            .fm-auth-card {
                background: var(--fm-card-bg);
                border: 1px solid var(--fm-border);
                border-radius: var(--fm-radius-lg);
                padding: 28px 22px;
                box-shadow: var(--fm-shadow);
            }

            .fm-auth-card-header h2 {
                font-size: 22px;
            }
        }

        @media (max-width: 380px) {
            .fm-auth-card {
                padding: 20px 16px;
            }

            .fm-auth-card-header h2 {
                font-size: 19px;
            }

            .fm-auth-toggle-btn {
                font-size: 11px;
                padding: 6px 10px;
            }

            .fm-auth-input {
                font-size: 13px;
                padding: 10px 12px 10px 40px;
            }

            .fm-auth-mobile-logo img {
                height: 44px;
                max-width: 120px;
            }

            .fm-auth-mobile-logo span {
                font-size: 22px;
            }
        }

        @media (min-width: 969px) and (max-height: 760px) {
            .fm-auth-hero,
            .fm-auth-panel {
                padding-top: 32px;
                padding-bottom: 32px;
            }

            .fm-auth-hero-logo img { height: 64px; }
            .fm-auth-hero h1 { font-size: 19px; }

            .fm-auth-hero p {
                font-size: 13px;
                margin-bottom: 20px;
            }

            .fm-auth-hero-features {
                gap: 12px;
            }

            .fm-auth-hero-feature h3 {
                font-size: 13px;
            }

            .fm-auth-hero-feature p {
                font-size: 12px;
            }
        }

        /* ==========================================
           PWA SPECIFIC
           ========================================== */
        @media (display-mode: standalone) {
            .fm-auth-page {
                padding-top: var(--fm-safe-top);
            }

            .fm-auth-panel {
                padding-top: calc(32px + var(--fm-safe-top));
                padding-bottom: calc(32px + var(--fm-safe-bottom));
            }

            .fm-auth-card {
                padding-bottom: calc(24px + var(--fm-safe-bottom));
            }
        }

        /* ==========================================
           UTILITY
           ========================================== */
        .fm-hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="fm-auth-page">
        {{-- HERO SECTION --}}
        <section class="fm-auth-hero">
            <div class="fm-auth-hero-content">
                <div class="fm-auth-hero-logo">
                    @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogo)
                        <img src="{{ $logoUrl }}" alt="{{ $appName }}">
                    @endif
                    @if($headerBrandingType === 'text' || $headerBrandingType === 'logo_text' || ! $appLogo)
                        <span>{{ $appName }} <small>•</small></span>
                    @endif
                </div>

                <h1>Manage orders, customers, and growth in one place</h1>
                <p>Use password login, OTP login, or quick sign up without losing the smoother {{ $appName }} experience.</p>

                <div class="fm-auth-hero-features">
                    <div class="fm-auth-hero-feature">
                        <span class="fm-feature-icon"><i class="fas fa-chart-line"></i></span>
                        <div>
                            <h3>Track performance</h3>
                            <p>Watch orders, sales, and engagement in real time.</p>
                        </div>
                    </div>
                    <div class="fm-auth-hero-feature">
                        <span class="fm-feature-icon"><i class="fas fa-bag-shopping"></i></span>
                        <div>
                            <h3>Handle orders faster</h3>
                            <p>Stay on top of incoming requests and fulfillment.</p>
                        </div>
                    </div>
                    <div class="fm-auth-hero-feature">
                        <span class="fm-feature-icon"><i class="fas fa-users"></i></span>
                        <div>
                            <h3>Keep customers close</h3>
                            <p>Support repeat ordering with a simpler auth flow.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- PANEL SECTION --}}
        <section class="fm-auth-panel">
            <div class="fm-auth-card">
                {{-- MOBILE LOGO --}}
                <div class="fm-auth-mobile-logo">
                    @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogo)
                        <img src="{{ $logoUrl }}" alt="{{ $appName }}">
                    @else
                        <span>{{ $appName }} <small>•</small></span>
                    @endif
                </div>

                {{-- HEADER --}}
                <div class="fm-auth-card-header">
                    <h2>Welcome back</h2>
                    <p>Sign in or create your {{ $appName }} account.</p>
                </div>

                {{-- ALERTS --}}
                @if(session('success'))
                    <div class="fm-auth-alert fm-auth-alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="fm-auth-alert fm-auth-alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                {{-- DEMO CREDENTIALS LINKS --}}
                @if($demoCredentials->isNotEmpty())
                    <div style="margin-bottom: 18px; padding: 12px 14px; background: var(--fm-primary-light); border: 1px solid color-mix(in srgb, var(--fm-primary) 30%, transparent); border-radius: var(--fm-radius); display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-circle-info" style="color: var(--fm-primary-dark); flex-shrink: 0;"></i>
                        <div style="font-size: 12px; color: var(--fm-primary-dark);">
                            <strong>Demo Accounts:</strong>
                            <div style="margin-top: 6px; display: flex; gap: 8px; flex-wrap: wrap;">
                                @foreach($demoCredentials as $credential)
                                    <a href="#" class="demo-login-link" data-email="{{ $credential['email'] }}" data-password="{{ $credential['password'] }}" style="color: var(--fm-primary-dark); text-decoration: underline; font-weight: 600; font-size: 11px;">{{ $credential['label'] ?? 'Demo' }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- TOGGLE --}}
                @php
                    $activeForm = session('otp_phone') ? 'otp' : (old('active_form') ?? 'login');
                @endphp

                <div class="fm-auth-toggle" id="authToggle">
                    <button type="button" class="fm-auth-toggle-btn {{ $activeForm === 'login' ? 'active' : '' }}" data-form="login">Login</button>
                    <button type="button" class="fm-auth-toggle-btn {{ $activeForm === 'otp' ? 'active' : '' }}" data-form="otp">OTP</button>
                    <button type="button" class="fm-auth-toggle-btn {{ $activeForm === 'register' ? 'active' : '' }}" data-form="register">Sign Up</button>
                </div>

                {{-- LOGIN FORM --}}
                <form id="loginForm" class="fm-auth-form {{ $activeForm === 'login' ? 'active' : '' }}" method="POST" action="{{ route('login') }}">
                    @csrf
                    <input type="hidden" name="active_form" value="login">
                    @if(request()->has('redirect'))
                        <input type="hidden" name="redirect" value="{{ request()->input('redirect') }}">
                    @endif

                    <div class="fm-auth-form-group">
                        <label for="loginEmail">Email Address</label>
                        <div class="fm-auth-input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="loginEmail" name="email" class="fm-auth-input {{ $errors->has('email') ? 'is-invalid' : '' }}" placeholder="Enter your email" value="{{ old('email') }}" required autocomplete="username">
                        </div>
                        @error('email')
                            <span class="fm-auth-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="fm-auth-form-group">
                        <label for="loginPassword">Password</label>
                        <div class="fm-auth-input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="loginPassword" name="password" class="fm-auth-input {{ $errors->has('password') ? 'is-invalid' : '' }}" placeholder="Enter your password" required autocomplete="current-password">
                            <i class="fas fa-eye fm-toggle-password" data-target="loginPassword"></i>
                        </div>
                        @error('password')
                            <span class="fm-auth-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="fm-auth-checkbox-row">
                        <label class="fm-auth-checkbox">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="{{ route('password.request') }}" class="fm-auth-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="fm-auth-submit" id="loginSubmitBtn">
                        <span>Login</span>
                    </button>
                </form>

                {{-- OTP FORM --}}
                <form id="otpForm" class="fm-auth-form {{ $activeForm === 'otp' ? 'active' : '' }}" method="POST" action="{{ session('otp_phone') ? route('login.otp.verify') : route('login.otp.send') }}">
                    @csrf
                    <input type="hidden" name="active_form" value="otp">

                    <div class="fm-auth-form-group">
                        <label for="otpRole">Login Role</label>
                        <div class="fm-auth-input-wrap">
                            <i class="fas fa-user-tag"></i>
                            <select id="otpRole" name="role" class="fm-auth-input" required>
                                <option value="customer" {{ session('otp_role', 'customer') === 'customer' ? 'selected' : '' }}>Customer</option>
                                <option value="restaurant" {{ session('otp_role') === 'restaurant' ? 'selected' : '' }}>Store</option>
                                <option value="restaurant_staff" {{ session('otp_role') === 'restaurant_staff' ? 'selected' : '' }}>Store Staff</option>
                                <option value="driver" {{ session('otp_role') === 'driver' ? 'selected' : '' }}>Driver</option>
                            </select>
                        </div>
                    </div>

                    <div class="fm-auth-form-group">
                        <label for="otpPhone">Mobile Number</label>
                        <div class="fm-auth-input-wrap">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="otpPhone" name="phone" class="fm-auth-input" placeholder="Enter 10 digit mobile number" value="{{ session('otp_phone') }}" required autocomplete="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10">
                        </div>
                        @error('phone')
                            <span class="fm-auth-error">{{ $message }}</span>
                        @enderror
                    </div>

                    @if(session('otp_phone'))
                        <div class="fm-auth-form-group">
                            <label for="otpCode">OTP</label>
                            <div class="fm-auth-input-wrap">
                                <i class="fas fa-key"></i>
                                <input type="text" id="otpCode" name="otp" class="fm-auth-input {{ $errors->has('otp') ? 'is-invalid' : '' }}" placeholder="Enter 6 digit OTP" maxlength="6" required inputmode="numeric">
                            </div>
                            @error('otp')
                                <span class="fm-auth-error">{{ $message }}</span>
                            @enderror
                            <div style="margin-top:6px;font-size:12px;color:var(--fm-text-light);">
                                <a href="#" id="resendOtpBtn" class="fm-auth-link" style="font-size:12px;">Resend OTP</a>
                            </div>
                        </div>
                    @endif

                    <button type="submit" class="fm-auth-submit" id="otpSubmitBtn">
                        <span>{{ session('otp_phone') ? 'Verify OTP' : 'Send OTP' }}</span>
                    </button>
                </form>

                {{-- REGISTER FORM --}}
                <form id="registerForm" class="fm-auth-form {{ $activeForm === 'register' ? 'active' : '' }}" method="POST" action="{{ route('register') }}">
                    @csrf
                    <input type="hidden" name="active_form" value="register">

                    <div class="fm-auth-form-group">
                        <label for="registerName">Full Name</label>
                        <div class="fm-auth-input-wrap">
                            <i class="fas fa-user"></i>
                            <input type="text" id="registerName" name="name" class="fm-auth-input {{ $errors->has('name') ? 'is-invalid' : '' }}" placeholder="Enter your full name" value="{{ old('name') }}" required autocomplete="name">
                        </div>
                        @error('name')
                            <span class="fm-auth-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="fm-auth-form-group">
                        <label for="registerEmail">Email Address</label>
                        <div class="fm-auth-input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="registerEmail" name="email" class="fm-auth-input {{ $errors->has('email') ? 'is-invalid' : '' }}" placeholder="Enter your email" value="{{ old('email') }}" required autocomplete="username">
                        </div>
                        @error('email')
                            <span class="fm-auth-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="fm-auth-form-group">
                        <label for="registerPhone">Phone Number</label>
                        <div class="fm-auth-input-wrap">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="registerPhone" name="phone" class="fm-auth-input {{ $errors->has('phone') ? 'is-invalid' : '' }}" placeholder="Enter your phone number" value="{{ old('phone') }}" required autocomplete="tel">
                        </div>
                        @error('phone')
                            <span class="fm-auth-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="fm-auth-form-group">
                        <label for="registerPassword">Password</label>
                        <div class="fm-auth-input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="registerPassword" name="password" class="fm-auth-input {{ $errors->has('password') ? 'is-invalid' : '' }}" placeholder="Create a password" required autocomplete="new-password">
                            <i class="fas fa-eye fm-toggle-password" data-target="registerPassword"></i>
                        </div>
                        @error('password')
                            <span class="fm-auth-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="fm-auth-form-group">
                        <label for="registerPasswordConfirmation">Confirm Password</label>
                        <div class="fm-auth-input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="registerPasswordConfirmation" name="password_confirmation" class="fm-auth-input" placeholder="Confirm your password" required autocomplete="new-password">
                            <i class="fas fa-eye fm-toggle-password" data-target="registerPasswordConfirmation"></i>
                        </div>
                    </div>

                    <button type="submit" class="fm-auth-submit" id="registerSubmitBtn">
                        <span>Create Account</span>
                    </button>
                </form>

                {{-- FOOTER --}}
                <div class="fm-auth-footer" id="loginFooter" style="{{ $activeForm === 'register' ? 'display:none;' : '' }}">
                    Don't have an account? <a href="#" class="fm-auth-link" data-switch="register">Sign up</a>
                </div>
                <div class="fm-auth-footer" id="registerFooter" style="{{ $activeForm === 'register' ? 'display:block;' : 'display:none;' }}">
                    Already have an account? <a href="#" class="fm-auth-link" data-switch="login">Login</a>
                </div>
            </div>
        </section>
    </div>

    <script>
        (function() {
            'use strict';

            // ==========================================
            // DOM REFS
            // ==========================================
            var forms = {
                login: document.getElementById('loginForm'),
                otp: document.getElementById('otpForm'),
                register: document.getElementById('registerForm')
            };

            var toggleButtons = document.querySelectorAll('.fm-auth-toggle-btn');
            var loginFooter = document.getElementById('loginFooter');
            var registerFooter = document.getElementById('registerFooter');
            var isSubmitting = false;

            // ==========================================
            // SWITCH FORM
            // ==========================================
            function switchForm(target) {
                // Update forms
                Object.keys(forms).forEach(function(formKey) {
                    if (forms[formKey]) {
                        forms[formKey].classList.toggle('active', formKey === target);
                    }
                });

                // Update toggle buttons
                toggleButtons.forEach(function(button) {
                    button.classList.toggle('active', button.dataset.form === target);
                });

                // Update footers
                if (loginFooter && registerFooter) {
                    loginFooter.style.display = target === 'register' ? 'none' : 'block';
                    registerFooter.style.display = target === 'register' ? 'block' : 'none';
                }

                // Focus first input
                setTimeout(function() {
                    var activeForm = document.querySelector('.fm-auth-form.active');
                    if (activeForm) {
                        var firstInput = activeForm.querySelector('input:not([type="hidden"])');
                        if (firstInput) {
                            firstInput.focus();
                        }
                    }
                }, 100);
            }

            // ==========================================
            // TOGGLE PASSWORD VISIBILITY
            // ==========================================
            function initPasswordToggles() {
                document.querySelectorAll('.fm-toggle-password').forEach(function(icon) {
                    icon.addEventListener('click', function(e) {
                        e.preventDefault();
                        var fieldId = this.dataset.target;
                        var field = document.getElementById(fieldId);
                        if (field) {
                            var isPassword = field.type === 'password';
                            field.type = isPassword ? 'text' : 'password';
                            this.classList.toggle('fa-eye', !isPassword);
                            this.classList.toggle('fa-eye-slash', isPassword);
                        }
                    });
                });
            }

            // ==========================================
            // PASSWORD MATCH VALIDATION
            // ==========================================
            function initPasswordMatch() {
                var password = document.getElementById('registerPassword');
                var confirmPassword = document.getElementById('registerPasswordConfirmation');

                if (password && confirmPassword) {
                    function checkMatch() {
                        if (password.value !== confirmPassword.value) {
                            confirmPassword.setCustomValidity('Passwords do not match');
                        } else {
                            confirmPassword.setCustomValidity('');
                        }
                    }

                    password.addEventListener('input', checkMatch);
                    confirmPassword.addEventListener('input', checkMatch);
                }
            }

            // ==========================================
            // FORM SUBMIT HANDLER
            // ==========================================
            function initFormHandler(formId, buttonId, loadingText) {
                var form = document.getElementById(formId);
                var button = document.getElementById(buttonId);

                if (!form || !button) return;

                form.addEventListener('submit', function(e) {
                    if (isSubmitting) {
                        e.preventDefault();
                        return false;
                    }

                    // Password match check for register
                    if (formId === 'registerForm') {
                        var password = document.getElementById('registerPassword');
                        var confirmPassword = document.getElementById('registerPasswordConfirmation');
                        if (password && confirmPassword && password.value !== confirmPassword.value) {
                            e.preventDefault();
                            alert('Passwords do not match.');
                            return false;
                        }
                    }

                    isSubmitting = true;
                    button.disabled = true;
                    button.innerHTML = '<span class="fm-spinner"></span> ' + loadingText;

                    // Safety timeout
                    setTimeout(function() {
                        if (isSubmitting) {
                            isSubmitting = false;
                            if (button) {
                                button.disabled = false;
                                button.innerHTML = '<span>' + loadingText.replace('...', '') + '</span>';
                            }
                        }
                    }, 30000);
                });
            }

            // ==========================================
            // RESEND OTP
            // ==========================================
            function initResendOtp() {
                var resendBtn = document.getElementById('resendOtpBtn');
                if (!resendBtn) return;

                resendBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var phoneInput = document.getElementById('otpPhone');
                    var roleSelect = document.getElementById('otpRole');

                    if (!phoneInput || !phoneInput.value || phoneInput.value.length < 10) {
                        alert('Please enter a valid 10 digit mobile number.');
                        return;
                    }

                    var form = document.getElementById('otpForm');
                    var submitBtn = document.getElementById('otpSubmitBtn');

                    if (form) {
                        // Change action to send OTP again
                        form.action = '{{ route('login.otp.send') }}';
                        // Remove OTP field if exists
                        var otpField = document.querySelector('#otpForm .fm-auth-form-group:has(#otpCode)');
                        if (otpField) {
                            otpField.remove();
                        }
                        // Update button text
                        if (submitBtn) {
                            submitBtn.innerHTML = '<span>Send OTP</span>';
                        }
                        // Submit the form
                        form.submit();
                    }
                });
            }

            // ==========================================
            // INIT
            // ==========================================
            function init() {
                // Toggle buttons
                toggleButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        switchForm(this.dataset.form);
                    });
                });

                // Footer switch links
                document.querySelectorAll('[data-switch]').forEach(function(link) {
                    link.addEventListener('click', function(event) {
                        event.preventDefault();
                        switchForm(this.dataset.switch);
                    });
                });

                initPasswordToggles();
                initPasswordMatch();
                initResendOtp();

                initFormHandler('loginForm', 'loginSubmitBtn', 'Logging in...');
                initFormHandler('otpForm', 'otpSubmitBtn',
                    '{{ session('otp_phone') ? 'Verifying OTP...' : 'Sending OTP...' }}'
                );
                initFormHandler('registerForm', 'registerSubmitBtn', 'Creating account...');

                // Demo credentials auto-fill
                document.querySelectorAll('.demo-login-link').forEach(function(link) {
                    link.addEventListener('click', function(event) {
                        event.preventDefault();
                        var email = this.dataset.email;
                        var password = this.dataset.password;

                        // Switch to login form if not already active
                        switchForm('login');

                        // Fill login form
                        setTimeout(function() {
                            var loginEmailInput = document.getElementById('loginEmail');
                            var loginPasswordInput = document.getElementById('loginPassword');

                            if (loginEmailInput && loginPasswordInput) {
                                loginEmailInput.value = email;
                                loginPasswordInput.value = password;
                                loginPasswordInput.type = 'text'; // Show the password
                                loginEmailInput.focus();

                                // Trigger input event for any validation
                                loginEmailInput.dispatchEvent(new Event('input', { bubbles: true }));
                                loginPasswordInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        }, 300);
                    });
                });

                // Set initial form
                var initialForm = '{{ $activeForm }}';
                switchForm(initialForm);
            }

            // Run on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>

    @include('partials.web-visit-tracker', ['panel' => 'auth'])
</body>
</html>
