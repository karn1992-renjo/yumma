import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import 'firebase_phone_login_screen.dart';
import 'otp_verification_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  static const _red = Color(0xFFEF4F5F);
  static const _orange = Color(0xFFFF7A00);
  static const _text = Color(0xFF111827);
  static const _subtext = Color(0xFF6B7280);
  static const _line = Color(0xFFE5E7EB);

  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  AppBranding _branding = AppBranding.fallback();
  bool _isLoadingBranding = true;
  bool _isSendingOtp = false;
  bool _usePasswordLogin = false;
  bool _isPasswordVisible = false;

  @override
  void initState() {
    super.initState();
    _loadBranding();
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() {
      _branding = branding;
      _isLoadingBranding = false;
    });
  }

  String _normalizedPhone() {
    final raw = _phoneController.text.trim();
    final digits = raw.replaceAll(RegExp(r'\D'), '');
    final dialCode = _branding.defaultMobileCountryCode;
    final dialDigits = dialCode.replaceAll(RegExp(r'\D'), '');
    if (raw.startsWith('+')) return '+$digits';
    if (digits.startsWith(dialDigits)) return '+$digits';
    return '$dialCode$digits';
  }

  Future<void> _requestOtp() async {
    if (_isSendingOtp) return;
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSendingOtp = true);
    try {
      final authProvider = context.read<AuthProvider>();
      final phone = _normalizedPhone();

      if (_branding.usesFirebasePhoneAuth) {
        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => FirebasePhoneLoginScreen(
              initialPhone: phone,
              countryCode: _branding.defaultMobileCountryCode,
              appName: _branding.displayName,
              role: 'restaurant',
            ),
          ),
        );
        return;
      }

      final success = await authProvider.sendLoginOtp(
        phone: phone,
        role: 'restaurant',
      );

      if (!mounted) return;

      if (!success) {
        _showMessage(authProvider.error ?? 'Failed to send OTP', isError: true);
        return;
      }

      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => OtpVerificationScreen(
            phoneNumber: phone,
            countryCode: _branding.defaultMobileCountryCode,
            appName: _branding.displayName,
            role: 'restaurant',
          ),
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isSendingOtp = false);
      }
    }
  }

  Future<void> _loginWithPassword() async {
    if (!_formKey.currentState!.validate()) return;

    final authProvider = context.read<AuthProvider>();
    final success = await authProvider.login(
      email: _emailController.text.trim(),
      password: _passwordController.text,
      role: 'restaurant',
    );

    if (!mounted) return;
    if (!success) {
      _showMessage(authProvider.error ?? 'Login failed', isError: true);
      return;
    }

    Navigator.pushReplacementNamed(context, '/restaurant/dashboard');
  }

  void _submitLogin() {
    if (_usePasswordLogin) {
      _loginWithPassword();
    } else {
      _requestOtp();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 4),
              Center(
                child: Container(
                  width: 96,
                  height: 6,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE9EEF5),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              _buildHero(),
              const SizedBox(height: 20),
              _loginModeToggle(),
              const SizedBox(height: 18),
              Form(
                key: _formKey,
                child: _usePasswordLogin
                    ? Column(
                        children: [
                          _emailField(),
                          const SizedBox(height: 12),
                          _passwordField(),
                        ],
                      )
                    : _phoneField(),
              ),
              const SizedBox(height: 22),
              SizedBox(
                width: double.infinity,
                child: Consumer<AuthProvider>(
                  builder: (context, auth, _) {
                    return DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [_orange, Color(0xFFFFC21A)],
                        ),
                        borderRadius: BorderRadius.circular(18),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x33FF7A00),
                            blurRadius: 18,
                            offset: Offset(0, 10),
                          ),
                        ],
                      ),
                      child: ElevatedButton(
                        onPressed: auth.isLoading ||
                                _isLoadingBranding ||
                                _isSendingOtp
                            ? null
                            : _submitLogin,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.transparent,
                          shadowColor: Colors.transparent,
                          minimumSize: const Size.fromHeight(58),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(18),
                          ),
                        ),
                        child: Text(
                          auth.isLoading || _isSendingOtp
                              ? (_usePasswordLogin
                                  ? 'Logging in...'
                                  : 'Sending OTP...')
                              : 'Login',
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w600,
                            color: Colors.black,
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 24),
              Center(
                child: TextButton(
                  onPressed: () => Navigator.pushNamed(context, '/register'),
                  child: const Text.rich(
                    TextSpan(
                      text: 'New to partner portal? ',
                      style: TextStyle(
                        color: _subtext,
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                      ),
                      children: [
                        TextSpan(
                          text: 'Register as Partner',
                          style: TextStyle(
                            color: Color(0xFF1D4ED8),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _loginModeToggle() {
    return Container(
      height: 46,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _line),
      ),
      child: Row(
        children: [
          _modeButton('OTP', !_usePasswordLogin),
          _modeButton('Email', _usePasswordLogin),
        ],
      ),
    );
  }

  Widget _modeButton(String label, bool selected) {
    return Expanded(
      child: InkWell(
        onTap: () => setState(() => _usePasswordLogin = label == 'Email'),
        borderRadius: BorderRadius.circular(12),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? Colors.white : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
            boxShadow: selected
                ? const [
                    BoxShadow(
                      color: Color(0x10000000),
                      blurRadius: 10,
                      offset: Offset(0, 4),
                    ),
                  ]
                : null,
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? _text : _subtext,
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }

  Widget _phoneField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Mobile Number',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
            color: _subtext,
          ),
        ),
        const SizedBox(height: 10),
        TextFormField(
          controller: _phoneController,
          keyboardType: TextInputType.phone,
          style: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w500,
            color: _text,
          ),
          decoration: _inputDecoration(
            hintText: 'Enter mobile number',
            prefixIcon: Padding(
              padding: const EdgeInsets.only(left: 14, right: 10),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 26,
                    height: 18,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(4),
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                      gradient: const LinearGradient(
                        colors: [
                          Color(0xFFFF9933),
                          Color(0xFFFF9933),
                          Colors.white,
                          Colors.white,
                          Color(0xFF138808),
                          Color(0xFF138808),
                        ],
                        stops: [0, 0.33, 0.33, 0.66, 0.66, 1],
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Text(
                    _branding.defaultMobileCountryCode,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: _text,
                    ),
                  ),
                  const SizedBox(width: 6),
                  const Icon(
                    Icons.keyboard_arrow_down_rounded,
                    size: 20,
                    color: _subtext,
                  ),
                  const SizedBox(width: 10),
                  Container(width: 1, height: 24, color: _line),
                ],
              ),
            ),
            prefixIconConstraints: const BoxConstraints(minWidth: 148),
          ),
          validator: (value) {
            final digits = (value ?? '').replaceAll(RegExp(r'\D'), '');
            if (digits.length < 8) return 'Enter a valid mobile number.';
            return null;
          },
        ),
      ],
    );
  }

  Widget _emailField() {
    return TextFormField(
      controller: _emailController,
      keyboardType: TextInputType.emailAddress,
      textInputAction: TextInputAction.next,
      decoration: _inputDecoration(
        hintText: 'Email address',
        prefixIcon: const Icon(Icons.email_outlined, color: _subtext),
      ),
      validator: (value) {
        final text = value?.trim() ?? '';
        if (text.isEmpty) return 'Email is required.';
        if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(text)) {
          return 'Enter a valid email.';
        }
        return null;
      },
    );
  }

  Widget _passwordField() {
    return TextFormField(
      controller: _passwordController,
      obscureText: !_isPasswordVisible,
      textInputAction: TextInputAction.done,
      onFieldSubmitted: (_) => _loginWithPassword(),
      decoration: _inputDecoration(
        hintText: 'Password',
        prefixIcon: const Icon(Icons.lock_outline_rounded, color: _subtext),
        suffixIcon: IconButton(
          icon: Icon(
            _isPasswordVisible
                ? Icons.visibility_off_outlined
                : Icons.visibility_outlined,
            color: _subtext,
          ),
          onPressed: () =>
              setState(() => _isPasswordVisible = !_isPasswordVisible),
        ),
      ),
      validator: (value) {
        if ((value ?? '').isEmpty) return 'Password is required.';
        return null;
      },
    );
  }

  InputDecoration _inputDecoration({
    required String hintText,
    Widget? prefixIcon,
    Widget? suffixIcon,
    BoxConstraints? prefixIconConstraints,
  }) {
    return InputDecoration(
      hintText: hintText,
      hintStyle: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w500,
        color: Color(0xFF9CA3AF),
      ),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: _line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: _orange, width: 1.4),
      ),
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      prefixIconConstraints: prefixIconConstraints,
    );
  }

  Widget _buildHero() {
    return SizedBox(
      height: 300,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(32),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x14000000),
                  blurRadius: 24,
                  offset: Offset(0, 12),
                ),
              ],
            ),
          ),
          Positioned(
            top: -18,
            right: -12,
            child: Container(
              width: 250,
              height: 220,
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [Color(0xFFFFC61B), _orange],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.only(
                  topRight: Radius.circular(36),
                  bottomLeft: Radius.circular(120),
                  bottomRight: Radius.circular(26),
                ),
              ),
            ),
          ),
          const Positioned(
            right: 28,
            top: 42,
            child: Icon(
              Icons.storefront_rounded,
              size: 132,
              color: Colors.white,
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            top: 0,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(22, 28, 22, 22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 70),
                  const Text(
                    'Welcome 👋',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w600,
                      color: _text,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Restaurant Partner',
                    style: TextStyle(
                      fontSize: 34,
                      height: 1.08,
                      fontWeight: FontWeight.w700,
                      color: _text,
                    ),
                  ),
                  const SizedBox(height: 12),
                  const SizedBox(
                    width: 190,
                    child: Text(
                      'Manage orders, menus and operations with secure access.',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w400,
                        color: _subtext,
                        height: 1.45,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : null,
      ),
    );
  }
}
