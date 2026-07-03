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
    @endphp
    <title>Login - {{ $appName }}</title>
    <link rel="icon" href="{{ $appFavicon ? \Illuminate\Support\Facades\Storage::disk('public')->url($appFavicon) : asset('favicon.ico') }}">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #fff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .page {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            min-height: 100vh;
        }

        .hero {
            position: relative;
            background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
            color: white;
            padding: 48px;
            display: flex;
            align-items: center;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.1;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 540px;
            margin: 0 auto;
        }

        .hero-logo {
            font-size: 72px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 18px;
            width: auto;
            margin: 0 0 32px 0;
        }

        .hero-logo img {
            height: 72px;
            width: auto;
            object-fit: contain;
        }

        .hero-logo span {
            color: #FFE066;
            font-size: inherit;
            line-height: 1;
        }

        .hero-title {
            font-size: 48px;
            line-height: 1.15;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .hero-subtitle {
            font-size: 18px;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 40px;
        }

        .hero-points {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .hero-point {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .hero-point-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .hero-point h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .hero-point p {
            font-size: 14px;
            opacity: 0.82;
        }

        .panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 28px;
            background: #fff;
        }

        .card {
            width: 100%;
            max-width: 460px;
            animation: fadeInUp 0.5s ease;
        }

        .header {
            margin-bottom: 28px;
        }

        .header h2 {
            font-size: 32px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 14px;
            color: #6B7280;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 22px;
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 14px;
        }

        .alert-danger {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #B91C1C;
        }

        .alert-success {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            color: #15803D;
        }

        .toggle {
            display: flex;
            gap: 10px;
            background: #F3F4F6;
            padding: 4px;
            border-radius: 999px;
            margin-bottom: 28px;
        }

        .toggle-btn {
            flex: 1;
            border: 0;
            background: transparent;
            border-radius: 999px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .toggle-btn.active {
            background: #fff;
            color: #667EEA;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .auth-form {
            display: none;
        }

        .auth-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap > i:not(.toggle-password) {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
            font-size: 18px;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #667EEA !important;
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #E5E7EB;
            border-radius: 14px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s ease;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: #667EEA;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
        }

        .error-text {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #DC2626;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 22px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #6B7280;
            font-size: 14px;
        }

        .checkbox-label input {
            accent-color: #667EEA;
        }

        .link {
            color: #667EEA;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .link:hover {
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.25);
            transition: all 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
        }

        .submit-btn:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
        }

        .footer-text {
            margin-top: 20px;
            text-align: center;
            color: #6B7280;
            font-size: 14px;
        }

        .spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 980px) {
            .page {
                grid-template-columns: 1fr;
            }

            .hero {
                display: none;
            }

            .panel {
                min-height: 100vh;
            }
        }
    </style>
@include('partials.public-blade-polish')
</head>
<body>
    <div class="page">
        <section class="hero">
            <div class="hero-content">
                <div class="hero-logo">
                    @if(($headerBrandingType === 'logo' || $headerBrandingType === 'logo_text') && $appLogo)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($appLogo) }}" alt="{{ $appName }}" style="height: 42px; width: auto; object-fit: contain;">
                    @endif
                    @if($headerBrandingType === 'text' || $headerBrandingType === 'logo_text' || ! $appLogo)
                        <span>{{ $appName }}</span>
                    @endif
                </div>

                <h1 class="hero-title">Manage orders, customers, and growth in one place</h1>
                <p class="hero-subtitle">Use password login, OTP login, or quick sign up without losing the smoother {{ $appName }} experience.</p>

                <div class="hero-points">
                    <div class="hero-point">
                        <div class="hero-point-icon"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <h3>Track performance</h3>
                            <p>Watch orders, sales, and engagement in real time.</p>
                        </div>
                    </div>
                    <div class="hero-point">
                        <div class="hero-point-icon"><i class="fas fa-bag-shopping"></i></div>
                        <div>
                            <h3>Handle orders faster</h3>
                            <p>Stay on top of incoming requests and fulfillment.</p>
                        </div>
                    </div>
                    <div class="hero-point">
                        <div class="hero-point-icon"><i class="fas fa-users"></i></div>
                        <div>
                            <h3>Keep customers close</h3>
                            <p>Support repeat ordering with a simpler auth flow.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="card">
                <div class="header">
                    <h2>Welcome back</h2>
                    <p>Sign in or create your {{ $appName }} account.</p>
                </div>

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

                @php
                    $activeForm = session('otp_phone') ? 'otp' : (old('active_form') ?? 'login');
                @endphp

                <div class="toggle" id="authToggle">
                    <button type="button" class="toggle-btn {{ $activeForm === 'login' ? 'active' : '' }}" data-form="login">Login</button>
                    <button type="button" class="toggle-btn {{ $activeForm === 'otp' ? 'active' : '' }}" data-form="otp">OTP</button>
                    <button type="button" class="toggle-btn {{ $activeForm === 'register' ? 'active' : '' }}" data-form="register">Sign Up</button>
                </div>

                <!-- Login Form -->
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
                        @error('email')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="loginPassword" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                            <i class="fas fa-eye toggle-password" data-target="loginPassword"></i>
                        </div>
                        @error('password')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
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

                <!-- OTP Form -->
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
                            <input type="tel" name="phone" class="form-control" placeholder="Enter 10 digit mobile number" value="{{ session('otp_phone') }}" required autocomplete="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10">
                        </div>
                    </div>

                    @if(session('otp_phone'))
                        <div class="form-group">
                            <label class="form-label">OTP</label>
                            <div class="input-wrap">
                                <i class="fas fa-key"></i>
                                <input type="text" name="otp" class="form-control" placeholder="Enter 6 digit OTP" maxlength="6" required inputmode="numeric">
                            </div>
                            @error('otp')
                                <span class="error-text">{{ $message }}</span>
                            @enderror
                        </div>
                    @endif

                    <button type="submit" class="submit-btn" id="otpSubmitBtn">
                        <span>{{ session('otp_phone') ? 'Verify OTP' : 'Send OTP' }}</span>
                    </button>
                </form>

                <!-- Register Form -->
                <form id="registerForm" class="auth-form {{ $activeForm === 'register' ? 'active' : '' }}" method="POST" action="{{ route('register') }}">
                    @csrf
                    <input type="hidden" name="active_form" value="register">

                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <div class="input-wrap">
                            <i class="fas fa-user"></i>
                            <input type="text" name="name" class="form-control" placeholder="Enter your full name" value="{{ old('name') }}" required autocomplete="name">
                        </div>
                        @error('name')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" class="form-control" placeholder="Enter your email" value="{{ old('email') }}" required autocomplete="username">
                        </div>
                        @error('email')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <div class="input-wrap">
                            <i class="fas fa-phone"></i>
                            <input type="tel" name="phone" class="form-control" placeholder="Enter your phone number" value="{{ old('phone') }}" required autocomplete="tel">
                        </div>
                        @error('phone')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="registerPassword" name="password" class="form-control" placeholder="Create a password" required autocomplete="new-password">
                            <i class="fas fa-eye toggle-password" data-target="registerPassword"></i>
                        </div>
                        @error('password')
                            <span class="error-text">{{ $message }}</span>
                        @enderror
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
        </section>
    </div>

    <script>
        (function() {
            // DOM Elements
            const forms = {
                login: document.getElementById('loginForm'),
                otp: document.getElementById('otpForm'),
                register: document.getElementById('registerForm')
            };
            
            const toggleButtons = document.querySelectorAll('.toggle-btn');
            const loginFooter = document.getElementById('loginFooter');
            const registerFooter = document.getElementById('registerFooter');
            
            // Form submission state tracking
            let isSubmitting = false;
            
            // Switch between forms
            function switchForm(target) {
                // Update forms visibility
                Object.keys(forms).forEach(function(formKey) {
                    if (forms[formKey]) {
                        forms[formKey].classList.toggle('active', formKey === target);
                    }
                });
                
                // Update toggle buttons
                toggleButtons.forEach(function(button) {
                    button.classList.toggle('active', button.dataset.form === target);
                });
                
                // Update footer visibility
                if (loginFooter && registerFooter) {
                    loginFooter.style.display = target === 'register' ? 'none' : 'block';
                    registerFooter.style.display = target === 'register' ? 'block' : 'none';
                }
                
                // Focus first input in active form
                setTimeout(function() {
                    const activeForm = document.querySelector('.auth-form.active');
                    if (activeForm) {
                        const firstInput = activeForm.querySelector('input:not([type="hidden"])');
                        if (firstInput) {
                            firstInput.focus();
                        }
                    }
                }, 100);
            }
            
            // Toggle password visibility
            function initializePasswordToggles() {
                document.querySelectorAll('.toggle-password').forEach(function(icon) {
                    icon.addEventListener('click', function(e) {
                        e.preventDefault();
                        const fieldId = this.dataset.target;
                        const field = document.getElementById(fieldId);
                        if (field) {
                            const isPassword = field.type === 'password';
                            field.type = isPassword ? 'text' : 'password';
                            this.classList.toggle('fa-eye', !isPassword);
                            this.classList.toggle('fa-eye-slash', isPassword);
                        }
                    });
                });
            }
            
            // Validate password match for registration
            function validatePasswordMatch() {
                const password = document.getElementById('registerPassword');
                const confirmPassword = document.getElementById('registerPasswordConfirmation');
                
                if (password && confirmPassword) {
                    const checkMatch = function() {
                        if (password.value !== confirmPassword.value) {
                            confirmPassword.setCustomValidity('Passwords do not match');
                        } else {
                            confirmPassword.setCustomValidity('');
                        }
                    };
                    
                    password.addEventListener('input', checkMatch);
                    confirmPassword.addEventListener('input', checkMatch);
                }
            }
            
            // Handle form submission with loading state
            function initializeFormHandler(formId, buttonId, loadingText) {
                const form = document.getElementById(formId);
                const button = document.getElementById(buttonId);
                
                if (!form || !button) return;
                
                form.addEventListener('submit', function(e) {
                    // Prevent double submission
                    if (isSubmitting) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // For registration form, validate password match before submission
                    if (formId === 'registerForm') {
                        const password = document.getElementById('registerPassword');
                        const confirmPassword = document.getElementById('registerPasswordConfirmation');
                        
                        if (password && confirmPassword && password.value !== confirmPassword.value) {
                            e.preventDefault();
                            alert('Passwords do not match.');
                            return false;
                        }
                    }
                    
                    // Set submitting flag and update button
                    isSubmitting = true;
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner"></span> ' + loadingText;
                    
                    // Re-enable after 30 seconds (safety measure)
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
            
            // Event Listeners
            function initializeEventListeners() {
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
                
                // Handle Enter key globally - ensure form submission works
                document.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const activeForm = document.querySelector('.auth-form.active');
                        if (activeForm && (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT')) {
                            // Let the normal form submission happen
                            return true;
                        }
                    }
                });
            }
            
            // Initialize everything
            function init() {
                initializeEventListeners();
                initializePasswordToggles();
                validatePasswordMatch();
                
                // Initialize form handlers
                initializeFormHandler('loginForm', 'loginSubmitBtn', 'Logging in...');
                initializeFormHandler('otpForm', 'otpSubmitBtn', '{{ session('otp_phone') ? 'Verifying OTP...' : 'Sending OTP...' }}');
                initializeFormHandler('registerForm', 'registerSubmitBtn', 'Creating account...');
                
                // Set initial active form based on server-side variable
                const initialForm = '{{ $activeForm }}';
                switchForm(initialForm);
            }
            
            // Run when DOM is ready
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
