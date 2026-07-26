<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $appName = App\Models\AppSetting::getValue('app_name', config('app.name', 'FoodFlow'));
        $appLogo = App\Models\AppSetting::getValue('app_logo');
        $appFavicon = App\Models\AppSetting::getValue('app_favicon');
        $headerBrandingType = App\Models\AppSetting::getValue('header_branding_type', 'text');
        $headerBrandingType = in_array($headerBrandingType, ['text', 'logo', 'logo_text'], true) ? $headerBrandingType : 'text';
        $brandingUrl = function (?string $path) {
            if (!$path) {
                return null;
            }

            return str_starts_with($path, 'branding/')
                ? route('media.branding', ['file' => basename($path)])
                : \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        };
        $appLogoUrl = $brandingUrl($appLogo);
        $appFaviconUrl = $brandingUrl($appFavicon);
        $activeForm = session('otp_phone') ? 'otp' : (old('active_form') ?? 'login');
    @endphp
    <title>Login - {{ $appName }}</title>
    <link rel="icon" href="{{ $appFaviconUrl ?: asset('favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            width: 100%;
            max-width: 100%;
            min-height: 100%;
            overflow-x: hidden;
        }

        body {
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #0f172a;
            background:
                radial-gradient(circle at 10% 10%, rgba(139, 92, 246, .16), transparent 30%),
                radial-gradient(circle at 90% 10%, rgba(249, 115, 22, .14), transparent 28%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 48%, #fff7ed 100%);
        }

        .auth-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(420px, .92fr);
            min-height: 100vh;
            width: 100%;
        }

        .auth-story {
            position: relative;
            display: flex;
            align-items: center;
            min-width: 0;
            padding: 56px;
            color: #fff;
            background:
                linear-gradient(135deg, rgba(15, 23, 42, .94), rgba(88, 28, 135, .86)),
                linear-gradient(135deg, #111827, #7c3aed);
            overflow: hidden;
        }

        .auth-story::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(rgba(255, 255, 255, .07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .07) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(135deg, #000 0%, transparent 76%);
        }

        .auth-story::after {
            content: '';
            position: absolute;
            right: -120px;
            bottom: -120px;
            width: 360px;
            height: 360px;
            border-radius: 50%;
            background: rgba(249, 115, 22, .28);
            filter: blur(8px);
        }

        .story-content {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 640px;
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 13px;
            margin-bottom: 42px;
        }

        .brand-icon {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            border-radius: 20px;
            background:
                radial-gradient(circle at 30% 18%, rgba(255, 255, 255, .48), transparent 30%),
                linear-gradient(135deg, #f97316, #7c3aed);
            box-shadow: 0 18px 42px rgba(0, 0, 0, .22);
            overflow: hidden;
        }

        .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 8px;
        }

        .brand-name {
            font-size: 26px;
            font-weight: 950;
            line-height: 1;
            letter-spacing: 0;
        }

        .brand-name span {
            color: #fdba74;
        }

        .story-kicker {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 8px 12px;
            margin-bottom: 18px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 999px;
            background: rgba(255, 255, 255, .1);
            color: #fde68a;
            font-size: 12px;
            font-weight: 900;
        }

        .story-title {
            max-width: 620px;
            margin: 0;
            font-size: clamp(36px, 4.2vw, 58px);
            line-height: 1.02;
            font-weight: 950;
            letter-spacing: 0;
        }

        .story-copy {
            max-width: 560px;
            margin-top: 18px;
            color: rgba(255, 255, 255, .78);
            font-size: 16px;
            font-weight: 600;
            line-height: 1.7;
        }

        .story-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 34px;
        }

        .story-metric {
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 20px;
            background: rgba(255, 255, 255, .1);
            backdrop-filter: blur(14px);
        }

        .story-metric strong {
            display: block;
            color: #fff;
            font-size: 20px;
            font-weight: 950;
        }

        .story-metric span {
            display: block;
            margin-top: 4px;
            color: rgba(255, 255, 255, .72);
            font-size: 12px;
            font-weight: 800;
        }

        .auth-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            padding: 36px 28px;
        }

        .auth-card {
            width: min(100%, 480px);
            border: 1px solid rgba(226, 232, 240, .92);
            border-radius: 30px;
            background: rgba(255, 255, 255, .88);
            box-shadow: 0 32px 80px rgba(15, 23, 42, .14);
            backdrop-filter: blur(20px);
            overflow: hidden;
        }

        .auth-card-header {
            padding: 26px 28px 18px;
        }

        .mobile-brand {
            display: none;
            align-items: center;
            gap: 11px;
            margin-bottom: 18px;
        }

        .auth-title {
            margin: 0;
            color: #0f172a;
            font-size: 28px;
            font-weight: 950;
            line-height: 1.1;
            letter-spacing: 0;
        }

        .auth-subtitle {
            margin-top: 8px;
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.5;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 16px;
            padding: 12px 13px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 750;
        }

        .alert-danger {
            color: #991b1b;
            border: 1px solid #fecaca;
            background: #fef2f2;
        }

        .alert-success {
            color: #166534;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
        }

        .auth-toggle {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 5px;
            margin: 0 28px 18px;
            padding: 5px;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #f8fafc;
        }

        .toggle-btn {
            min-height: 40px;
            border: 0;
            border-radius: 14px;
            background: transparent;
            color: #64748b;
            font: inherit;
            font-size: 13px;
            font-weight: 900;
            cursor: pointer;
            transition: all .18s ease;
        }

        .toggle-btn.active {
            color: #fff;
            background: linear-gradient(135deg, #7c3aed, #f97316);
            box-shadow: 0 12px 24px rgba(124, 58, 237, .2);
        }

        .auth-card-body {
            padding: 0 28px 28px;
        }

        .auth-form {
            display: none;
        }

        .auth-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 7px;
            color: #334155;
            font-size: 13px;
            font-weight: 900;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap > i:not(.toggle-password) {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7c3aed;
            cursor: pointer;
        }

        .form-control {
            width: 100%;
            min-height: 48px;
            border: 1.5px solid #dbe4f0;
            border-radius: 16px;
            background: rgba(255, 255, 255, .92);
            color: #0f172a;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            outline: none;
            padding: 12px 44px 12px 44px;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }

        select.form-control {
            appearance: none;
        }

        .form-control:focus {
            border-color: #8b5cf6;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, .13);
        }

        .error-text {
            display: block;
            margin-top: 6px;
            color: #dc2626;
            font-size: 12px;
            font-weight: 750;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin: 4px 0 18px;
        }

        .checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
        }

        .checkbox-label input {
            accent-color: #7c3aed;
        }

        .link {
            color: #7c3aed;
            text-decoration: none;
            font-size: 13px;
            font-weight: 900;
        }

        .link:hover {
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            min-height: 50px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            border: 0;
            border-radius: 16px;
            color: #fff;
            background: linear-gradient(135deg, #7c3aed, #f97316);
            box-shadow: 0 18px 34px rgba(124, 58, 237, .22);
            font: inherit;
            font-size: 15px;
            font-weight: 950;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 22px 40px rgba(124, 58, 237, .28);
        }

        .submit-btn:disabled {
            opacity: .75;
            cursor: not-allowed;
            transform: none;
        }

        .spinner {
            width: 16px;
            height: 16px;
            display: inline-block;
            border: 2px solid rgba(255, 255, 255, .45);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .75s linear infinite;
        }

        .footer-text {
            margin-top: 18px;
            color: #64748b;
            text-align: center;
            font-size: 13px;
            font-weight: 750;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 980px) {
            .auth-shell {
                grid-template-columns: 1fr;
            }

            .auth-story {
                display: none;
            }

            .auth-panel {
                min-height: 100vh;
                padding: 22px 14px;
            }

            .mobile-brand {
                display: flex;
            }

            .auth-card {
                border-radius: 24px;
            }
        }

        @media (max-width: 520px) {
            body {
                background: #f8fafc;
            }

            .auth-panel {
                align-items: flex-start;
                padding: 14px;
            }

            .auth-card {
                width: 100%;
                border-radius: 22px;
                box-shadow: 0 16px 42px rgba(15, 23, 42, .1);
            }

            .auth-card-header,
            .auth-card-body {
                padding-left: 18px;
                padding-right: 18px;
            }

            .auth-card-header {
                padding-top: 20px;
            }

            .auth-toggle {
                margin-left: 18px;
                margin-right: 18px;
            }

            .auth-title {
                font-size: 24px;
            }

            .form-control {
                min-height: 46px;
            }
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="auth-story" aria-hidden="true">
            <div class="story-content">
                <div class="brand-mark">
                    <div class="brand-icon">
                        @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogoUrl)
                            <img src="{{ $appLogoUrl }}" alt="{{ $appName }}">
                        @else
                            <i class="fas fa-utensils"></i>
                        @endif
                    </div>
                    <div class="brand-name">{{ $appName }} <span>Access</span></div>
                </div>

                <div class="story-kicker">
                    <i class="fas fa-shield-halved"></i>
                    Secure operations login
                </div>
                <h1 class="story-title">Run customers, restaurants, drivers and orders from one clean console.</h1>
                <p class="story-copy">Use password login, OTP login, or quick sign up while keeping the experience fast across admin, branch, restaurant and customer workflows.</p>

                <div class="story-metrics">
                    <div class="story-metric">
                        <strong>Live</strong>
                        <span>Order operations</span>
                    </div>
                    <div class="story-metric">
                        <strong>OTP</strong>
                        <span>Phone access</span>
                    </div>
                    <div class="story-metric">
                        <strong>Secure</strong>
                        <span>Role routing</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card-header">
                    <div class="mobile-brand">
                        <div class="brand-icon">
                            @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogoUrl)
                                <img src="{{ $appLogoUrl }}" alt="{{ $appName }}">
                            @else
                                <i class="fas fa-utensils"></i>
                            @endif
                        </div>
                        <div class="brand-name" style="color:#0f172a; font-size:20px;">{{ $appName }}</div>
                    </div>

                    <h2 class="auth-title">Welcome back</h2>
                    <p class="auth-subtitle">Sign in with your password, request an OTP, or create a new customer account.</p>

                    @if(session('success'))
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif
                </div>

                <div class="auth-toggle" id="authToggle">
                    <button type="button" class="toggle-btn {{ $activeForm === 'login' ? 'active' : '' }}" data-form="login">Login</button>
                    <button type="button" class="toggle-btn {{ $activeForm === 'otp' ? 'active' : '' }}" data-form="otp">OTP</button>
                    <button type="button" class="toggle-btn {{ $activeForm === 'register' ? 'active' : '' }}" data-form="register">Sign Up</button>
                </div>

                <div class="auth-card-body">
                    <form id="loginForm" class="auth-form {{ $activeForm === 'login' ? 'active' : '' }}" method="POST" action="{{ route('login') }}">
                        @csrf
                        <input type="hidden" name="active_form" value="login">
                        @if(request()->has('redirect'))
                            <input type="hidden" name="redirect" value="{{ request()->input('redirect') }}">
                        @endif

                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <div class="input-wrap">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" value="{{ old('email') }}" required autocomplete="username">
                            </div>
                            @error('email') <span class="error-text">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <div class="input-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="loginPassword" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                                <i class="fas fa-eye toggle-password" data-target="loginPassword"></i>
                            </div>
                            @error('password') <span class="error-text">{{ $message }}</span> @enderror
                        </div>

                        <div class="checkbox-row">
                            <label class="checkbox-label">
                                <input type="checkbox" name="remember">
                                <span>Remember me</span>
                            </label>
                            <a href="{{ route('password.request') }}" class="link">Forgot password?</a>
                        </div>

                        <button type="submit" class="submit-btn" id="loginSubmitBtn">
                            <span>Login</span>
                        </button>
                    </form>

                    <form id="otpForm" class="auth-form {{ $activeForm === 'otp' ? 'active' : '' }}" method="POST" action="{{ session('otp_phone') ? route('login.otp.verify') : route('login.otp.send') }}">
                        @csrf
                        <input type="hidden" name="active_form" value="otp">

                        <div class="form-group">
                            <label class="form-label">Login Role</label>
                            <div class="input-wrap">
                                <i class="fas fa-user-tag"></i>
                                <select name="role" class="form-control" required>
                                    <option value="customer" {{ session('otp_role', 'customer') === 'customer' ? 'selected' : '' }}>Customer</option>
                                    <option value="restaurant" {{ session('otp_role') === 'restaurant' ? 'selected' : '' }}>Restaurant</option>
                                    <option value="restaurant_staff" {{ session('otp_role') === 'restaurant_staff' ? 'selected' : '' }}>Restaurant Staff</option>
                                    <option value="driver" {{ session('otp_role') === 'driver' ? 'selected' : '' }}>Driver</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mobile Number</label>
                            <div class="input-wrap">
                                <i class="fas fa-phone"></i>
                                <input type="tel" name="phone" class="form-control" placeholder="Enter mobile number" value="{{ session('otp_phone') }}" required autocomplete="tel" inputmode="tel" maxlength="20">
                            </div>
                            @error('phone') <span class="error-text">{{ $message }}</span> @enderror
                        </div>

                        @if(session('otp_phone'))
                            <div class="form-group">
                                <label class="form-label">OTP</label>
                                <div class="input-wrap">
                                    <i class="fas fa-key"></i>
                                    <input type="text" name="otp" class="form-control" placeholder="Enter OTP" maxlength="8" required inputmode="numeric">
                                </div>
                                @error('otp') <span class="error-text">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <button type="submit" class="submit-btn" id="otpSubmitBtn">
                            <span>{{ session('otp_phone') ? 'Verify OTP' : 'Send OTP' }}</span>
                        </button>
                    </form>

                    <form id="registerForm" class="auth-form {{ $activeForm === 'register' ? 'active' : '' }}" method="POST" action="{{ route('register') }}">
                        @csrf
                        <input type="hidden" name="active_form" value="register">

                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <div class="input-wrap">
                                <i class="fas fa-user"></i>
                                <input type="text" name="name" class="form-control" placeholder="Enter your full name" value="{{ old('name') }}" required autocomplete="name">
                            </div>
                            @error('name') <span class="error-text">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <div class="input-wrap">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" value="{{ old('email') }}" required autocomplete="username">
                            </div>
                            @error('email') <span class="error-text">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <div class="input-wrap">
                                <i class="fas fa-phone"></i>
                                <input type="tel" name="phone" class="form-control" placeholder="Enter your phone number" value="{{ old('phone') }}" required autocomplete="tel">
                            </div>
                            @error('phone') <span class="error-text">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <div class="input-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="registerPassword" name="password" class="form-control" placeholder="Create a password" required autocomplete="new-password">
                                <i class="fas fa-eye toggle-password" data-target="registerPassword"></i>
                            </div>
                            @error('password') <span class="error-text">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="registerPasswordConfirmation" name="password_confirmation" class="form-control" placeholder="Confirm your password" required autocomplete="new-password">
                                <i class="fas fa-eye toggle-password" data-target="registerPasswordConfirmation"></i>
                            </div>
                        </div>

                        <button type="submit" class="submit-btn" id="registerSubmitBtn">
                            <span>Create Account</span>
                        </button>
                    </form>

                    <div class="footer-text" id="loginFooter" style="{{ $activeForm === 'register' ? 'display:none;' : '' }}">
                        Don't have an account? <a href="#" class="link" data-switch="register">Sign up</a>
                    </div>
                    <div class="footer-text" id="registerFooter" style="{{ $activeForm === 'register' ? 'display:block;' : 'display:none;' }}">
                        Already have an account? <a href="#" class="link" data-switch="login">Login</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        (function() {
            const forms = {
                login: document.getElementById('loginForm'),
                otp: document.getElementById('otpForm'),
                register: document.getElementById('registerForm')
            };
            const toggleButtons = document.querySelectorAll('.toggle-btn');
            const loginFooter = document.getElementById('loginFooter');
            const registerFooter = document.getElementById('registerFooter');
            let isSubmitting = false;

            function switchForm(target) {
                Object.keys(forms).forEach(function(formKey) {
                    forms[formKey]?.classList.toggle('active', formKey === target);
                });

                toggleButtons.forEach(function(button) {
                    button.classList.toggle('active', button.dataset.form === target);
                });

                if (loginFooter && registerFooter) {
                    loginFooter.style.display = target === 'register' ? 'none' : 'block';
                    registerFooter.style.display = target === 'register' ? 'block' : 'none';
                }

                setTimeout(function() {
                    const firstInput = document.querySelector('.auth-form.active input:not([type="hidden"]), .auth-form.active select');
                    firstInput?.focus();
                }, 80);
            }

            document.querySelectorAll('.toggle-password').forEach(function(icon) {
                icon.addEventListener('click', function(event) {
                    event.preventDefault();
                    const field = document.getElementById(this.dataset.target);
                    if (!field) return;
                    const show = field.type === 'password';
                    field.type = show ? 'text' : 'password';
                    this.classList.toggle('fa-eye', !show);
                    this.classList.toggle('fa-eye-slash', show);
                });
            });

            function validatePasswordMatch() {
                const password = document.getElementById('registerPassword');
                const confirmPassword = document.getElementById('registerPasswordConfirmation');
                if (!password || !confirmPassword) return;

                const checkMatch = function() {
                    confirmPassword.setCustomValidity(password.value !== confirmPassword.value ? 'Passwords do not match' : '');
                };

                password.addEventListener('input', checkMatch);
                confirmPassword.addEventListener('input', checkMatch);
            }

            function initializeFormHandler(formId, buttonId, loadingText, originalText) {
                const form = document.getElementById(formId);
                const button = document.getElementById(buttonId);
                if (!form || !button) return;

                form.addEventListener('submit', function(event) {
                    if (isSubmitting) {
                        event.preventDefault();
                        return false;
                    }

                    if (formId === 'registerForm') {
                        const password = document.getElementById('registerPassword');
                        const confirmPassword = document.getElementById('registerPasswordConfirmation');
                        if (password && confirmPassword && password.value !== confirmPassword.value) {
                            event.preventDefault();
                            confirmPassword.reportValidity();
                            return false;
                        }
                    }

                    isSubmitting = true;
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner"></span>' + loadingText;

                    setTimeout(function() {
                        if (!isSubmitting) return;
                        isSubmitting = false;
                        button.disabled = false;
                        button.innerHTML = '<span>' + originalText + '</span>';
                    }, 30000);
                });
            }

            toggleButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    switchForm(this.dataset.form);
                });
            });

            document.querySelectorAll('[data-switch]').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    switchForm(this.dataset.switch);
                });
            });

            validatePasswordMatch();
            initializeFormHandler('loginForm', 'loginSubmitBtn', 'Logging in...', 'Login');
            initializeFormHandler('otpForm', 'otpSubmitBtn', @json(session('otp_phone') ? 'Verifying OTP...' : 'Sending OTP...'), @json(session('otp_phone') ? 'Verify OTP' : 'Send OTP'));
            initializeFormHandler('registerForm', 'registerSubmitBtn', 'Creating account...', 'Create Account');
            switchForm(@json($activeForm));
        })();
    </script>
    @include('partials.web-visit-tracker', ['panel' => 'auth'])
</body>
</html>
