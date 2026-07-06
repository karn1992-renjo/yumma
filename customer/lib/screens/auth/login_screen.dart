import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:provider/provider.dart';

import '../../config/app_config.dart';
import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../services/social_auth_service.dart';
import '../../theme/brand_palette.dart';
import '../../utils/phone_number_utils.dart';
import 'otp_verification_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  static const _text = Color(0xFF111827);
  static const _subtext = Color(0xFF6B7280);
  static const _line = Color(0xFFE5E7EB);

  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final FirebasePhoneAuthService _firebasePhoneAuthService =
      FirebasePhoneAuthService();
  final SocialAuthService _socialAuthService = SocialAuthService();

  AppBranding _branding = AppBranding.fallback();
  bool _isLoadingBranding = true;
  bool _isSocialLoading = false;
  bool _isSendingOtp = false;

  BrandPalette get _palette => BrandPalette.fromBranding(_branding);
  Color get _primary => _palette.primary;
  Color get _secondary => _palette.secondary;

  @override
  void initState() {
    super.initState();
    _loadBranding();
  }

  @override
  void dispose() {
    _phoneController.dispose();
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
    return PhoneNumberUtils.normalizeMobile(
      _phoneController.text,
      countryCode: _branding.defaultMobileCountryCode,
      log: true,
    ).normalizedNumber;
  }

  Future<void> _requestOtp() async {
    if (_isSendingOtp) return;
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSendingOtp = true);
    try {
      final latestBranding = await AppBrandingService.instance.loadBranding(
        forceRefresh: true,
      );
      if (!mounted) return;
      setState(() {
        _branding = latestBranding;
      });

      final authProvider = context.read<AuthProvider>();
      final phone = _normalizedPhone();
      final status = await authProvider.getPhoneStatus(
        phone: phone,
        role: 'customer',
      );

      if (!mounted) return;

      if (status != null) {
        if (status['exists'] != true) {
          Navigator.pushReplacementNamed(
            context,
            '/register',
            arguments: {'phone': phone},
          );
          return;
        }

        if (status['matches_role'] == false) {
          _showMessage(
            'This mobile number is not registered for a customer account.',
            isError: true,
          );
          return;
        }
      }

      String? firebaseVerificationId;

      if (_branding.usesFirebasePhoneAuth) {
        try {
          firebaseVerificationId = await _firebasePhoneAuthService.sendOtp(
            phone: phone,
            countryCode: _branding.defaultMobileCountryCode,
          );
        } catch (error) {
          if (!mounted) return;
          _showMessage(
            error.toString().replaceFirst('Exception: ', ''),
            isError: true,
          );
          return;
        }
      } else {
        final success = await authProvider.sendLoginOtp(
          phone: phone,
          role: 'customer',
        );

        if (!mounted) return;

        if (!success) {
          final error = authProvider.error ?? 'Failed to send OTP';
          if (_isAccountMissingError(error)) {
            Navigator.pushReplacementNamed(
              context,
              '/register',
              arguments: {'phone': phone},
            );
            return;
          }
          _showMessage(error, isError: true);
          return;
        }
      }

      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => OtpVerificationScreen(
            phoneNumber: phone,
            countryCode: _branding.defaultMobileCountryCode,
            appName: _branding.displayName,
            role: 'customer',
            flow: 'login',
            useFirebasePhoneAuth: _branding.usesFirebasePhoneAuth,
            initialFirebaseVerificationId: firebaseVerificationId,
          ),
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isSendingOtp = false);
      }
    }
  }

  bool _isAccountMissingError(String error) {
    final normalized = error.toLowerCase();
    return normalized.contains('no account found') ||
        normalized.contains('no matching account') ||
        normalized.contains('not registered') ||
        normalized.contains('account_not_found');
  }

  Future<void> _handleSocialLogin(String provider) async {
    if (_isSocialLoading || _isLoadingBranding) return;

    final latestBranding = await AppBrandingService.instance.loadBranding(
      forceRefresh: true,
    );
    if (!mounted) return;
    setState(() {
      _branding = latestBranding;
      _isSocialLoading = true;
    });

    try {
      if (provider == 'google' && !_branding.usesGoogleLogin) {
        throw Exception('Google login is disabled.');
      }
      if (provider == 'apple' && !_branding.usesAppleLogin) {
        throw Exception('Apple login is disabled.');
      }

      final socialResult = provider == 'google'
          ? await _socialAuthService.signInWithGoogle(
              webClientId: _branding.googleWebClientId,
            )
          : await _socialAuthService.signInWithApple();

      final authProvider = context.read<AuthProvider>();
      final success = await authProvider.loginWithSocial(
        provider: socialResult.provider,
        firebaseIdToken: socialResult.firebaseIdToken,
        role: 'customer',
        displayName: socialResult.displayName,
      );

      if (!mounted) return;
      if (!success) {
        _showMessage(
          authProvider.error?.replaceFirst('Exception: ', '') ??
              'Social login failed',
          isError: true,
        );
        return;
      }

      Navigator.pushReplacementNamed(context, _homeRoute(authProvider));
    } catch (error) {
      if (!mounted) return;
      _showMessage(
        error.toString().replaceFirst('Exception: ', ''),
        isError: true,
      );
    } finally {
      if (mounted) {
        setState(() {
          _isSocialLoading = false;
        });
      }
    }
  }

  String _homeRoute(AuthProvider authProvider) {
    if (AppConfig.isRestaurantApp || authProvider.isRestaurantOwner) {
      return '/restaurant/dashboard';
    }
    if (AppConfig.isDriverApp || authProvider.isDriver) {
      return '/driver/dashboard';
    }
    return AppConfig.isRoleLocked ? '/home' : '/customer/home';
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
              const SizedBox(height: 26),
              const Text(
                'Mobile Number',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: _subtext,
                ),
              ),
              const SizedBox(height: 10),
              Form(
                key: _formKey,
                child: TextFormField(
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w500,
                    color: _text,
                  ),
                  decoration: InputDecoration(
                    hintText: 'Enter mobile number',
                    hintStyle: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                      color: Color(0xFF9CA3AF),
                    ),
                    filled: true,
                    fillColor: Colors.white,
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 18,
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: const BorderSide(color: _line),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: BorderSide(color: _primary, width: 1.4),
                    ),
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
                              border:
                                  Border.all(color: const Color(0xFFE5E7EB)),
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
                          const Icon(Icons.keyboard_arrow_down_rounded,
                              size: 20, color: _subtext),
                          const SizedBox(width: 10),
                          Container(width: 1, height: 24, color: _line),
                        ],
                      ),
                    ),
                    prefixIconConstraints: const BoxConstraints(minWidth: 148),
                  ),
                  validator: (value) {
                    return PhoneNumberUtils.validateIndianMobile(
                      value,
                      countryCode: _branding.defaultMobileCountryCode,
                    );
                  },
                ),
              ),
              const SizedBox(height: 22),
              SizedBox(
                width: double.infinity,
                child: Consumer<AuthProvider>(
                  builder: (context, auth, _) {
                    return DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            _primary,
                            Color.lerp(_primary, _secondary, 0.24) ?? _primary,
                          ],
                        ),
                        borderRadius: BorderRadius.circular(18),
                        boxShadow: [
                          BoxShadow(
                            color: _primary.withOpacity(0.2),
                            blurRadius: 18,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: ElevatedButton(
                        onPressed: auth.isLoading ||
                                _isLoadingBranding ||
                                _isSendingOtp
                            ? null
                            : _requestOtp,
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
                              ? 'Sending OTP...'
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
              if (_branding.usesGoogleLogin || _branding.usesAppleLogin) ...[
                const SizedBox(height: 24),
                Row(
                  children: [
                    const Expanded(child: Divider(color: _line)),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Text(
                        'or continue with',
                        style: TextStyle(
                          color: _subtext.withOpacity(0.9),
                          fontSize: 15,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                    const Expanded(child: Divider(color: _line)),
                  ],
                ),
                const SizedBox(height: 18),
                if (_branding.usesGoogleLogin) ...[
                  _socialButton(
                    'Continue with Google',
                    'google',
                    onPressed: () => _handleSocialLogin('google'),
                  ),
                  const SizedBox(height: 14),
                ],
                if (_branding.usesAppleLogin) ...[
                  _socialButton(
                    'Continue with Apple',
                    'apple',
                    onPressed: () => _handleSocialLogin('apple'),
                  ),
                  const SizedBox(height: 14),
                ],
                const SizedBox(height: 10),
              ] else
                const SizedBox(height: 24),
              Center(
                child: TextButton(
                  onPressed: () => Navigator.pushNamed(context, '/register'),
                  child: const Text.rich(
                    TextSpan(
                      text: 'New here? ',
                      style: TextStyle(
                        color: _subtext,
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                      ),
                      children: [
                        TextSpan(
                          text: 'Create Account',
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

  Widget _buildHero() {
    return SizedBox(
      height: 330,
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
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    Color.lerp(_primary, Colors.white, 0.18) ?? _primary,
                    _primary,
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: const BorderRadius.only(
                  topRight: Radius.circular(36),
                  bottomLeft: Radius.circular(120),
                  bottomRight: Radius.circular(26),
                ),
              ),
            ),
          ),
          Positioned(
            right: 20,
            top: 34,
            child: Column(
              children: [
                Container(
                  width: 150,
                  height: 150,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.18),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.delivery_dining_rounded,
                    size: 108,
                    color: Colors.white,
                  ),
                ),
              ],
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
                  const SizedBox(height: 90),
                  const Text(
                    'Welcome 👋',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w600,
                      color: _text,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _branding.displayName,
                    style: const TextStyle(
                      fontSize: 38,
                      height: 1.08,
                      fontWeight: FontWeight.w700,
                      color: _text,
                    ),
                  ),
                  const SizedBox(height: 12),
                  const SizedBox(
                    width: 176,
                    child: Text(
                      'Order faster with secure OTP based sign in.',
                      style: TextStyle(
                        fontSize: 18,
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

  Widget _socialButton(
    String label,
    String provider, {
    required VoidCallback onPressed,
  }) {
    return SizedBox(
      height: 58,
      width: double.infinity,
      child: OutlinedButton(
        onPressed: _isSocialLoading ? null : onPressed,
        style: OutlinedButton.styleFrom(
          backgroundColor: Colors.white,
          side: const BorderSide(color: _line),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 18),
        ),
        child: Row(
          children: [
            SizedBox(
              width: 36,
              child: Center(
                child: SvgPicture.asset(
                  provider == 'google'
                      ? 'assets/icons/google-icon-logo-svgrepo-com.svg'
                      : 'assets/icons/apple-black-logo-svgrepo-com.svg',
                  width: provider == 'google' ? 24 : 26,
                  height: provider == 'google' ? 24 : 26,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                _isSocialLoading ? 'Signing in...' : label,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                  color: _text,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : _primary,
      ),
    );
  }
}
