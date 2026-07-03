import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/appsflyer_deep_link_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../theme/brand_palette.dart';
import '../../utils/phone_number_utils.dart';
import 'otp_verification_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({
    super.key,
    this.initialPhone,
    this.initialEmail,
  });

  final String? initialPhone;
  final String? initialEmail;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  static const _text = Color(0xFF111827);
  static const _subtext = Color(0xFF6B7280);
  static const _line = Color(0xFFE5E7EB);

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final FirebasePhoneAuthService _firebasePhoneAuthService =
      FirebasePhoneAuthService();

  AppBranding _branding = AppBranding.fallback();
  String? _verifiedPhoneToken;
  String? _verifiedPhoneNumber;
  bool _agreeTerms = true;
  bool _isLoadingBranding = true;
  bool _isSendingOtp = false;

  BrandPalette get _palette => BrandPalette.fromBranding(_branding);
  Color get _primary => _palette.primary;
  Color get _secondary => _palette.secondary;

  @override
  void initState() {
    super.initState();
    _phoneController.text = _stripCountryCode(widget.initialPhone?.trim() ?? '');
    _emailController.text = widget.initialEmail?.trim() ?? '';
    _loadBranding();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() {
      _branding = branding;
      _isLoadingBranding = false;
      if ((widget.initialPhone ?? '').isNotEmpty) {
        _phoneController.text =
            _stripCountryCode(widget.initialPhone!.trim());
      }
    });
  }

  String _normalizedPhone() {
    return PhoneNumberUtils.normalizeMobile(
      _phoneController.text,
      countryCode: _branding.defaultMobileCountryCode,
      log: true,
    ).normalizedNumber;
  }

  String _stripCountryCode(String phone) {
    if (phone.isEmpty) return '';
    try {
      return PhoneNumberUtils.localMobile(
        phone,
        countryCode: _branding.defaultMobileCountryCode,
      );
    } on FormatException {
      return PhoneNumberUtils.sanitizedDigits(phone);
    }
  }

  Future<void> _verifyMobile() async {
    if (_isSendingOtp) return;
    if (_phoneController.text.trim().isEmpty) {
      _showMessage('Enter your mobile number first.', isError: true);
      return;
    }

    setState(() => _isSendingOtp = true);
    try {
      final authProvider = context.read<AuthProvider>();
      late final String phone;
      try {
        phone = _normalizedPhone();
      } on FormatException catch (error) {
        _showMessage(error.message, isError: true);
        return;
      }
      final status = await authProvider.getPhoneStatus(
        phone: phone,
        role: 'customer',
      );

      if (!mounted) return;

      if (status == null) {
        _showMessage(
          authProvider.error ?? 'Unable to validate your mobile number.',
          isError: true,
        );
        return;
      }

      if (status['exists'] == true) {
        _showMessage(
          'An account already exists with this mobile number. Please sign in.',
          isError: true,
        );
        return;
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
        final sent = await authProvider.sendLoginOtp(
          phone: phone,
          flow: 'signup',
          role: 'customer',
        );

        if (!mounted) return;

        if (!sent) {
          _showMessage(authProvider.error ?? 'Failed to send OTP', isError: true);
          return;
        }
      }

      final result = await Navigator.of(context).push<Map<String, dynamic>>(
        MaterialPageRoute(
          builder: (_) => OtpVerificationScreen(
            phoneNumber: phone,
            countryCode: _branding.defaultMobileCountryCode,
            appName: _branding.displayName,
            role: 'customer',
            flow: 'signup',
            useFirebasePhoneAuth: _branding.usesFirebasePhoneAuth,
            initialFirebaseVerificationId: firebaseVerificationId,
          ),
        ),
      );

      if (!mounted || result == null) return;

      setState(() {
        _verifiedPhoneToken = result['verified_phone_token']?.toString();
        _verifiedPhoneNumber = result['phone']?.toString() ?? phone;
        _phoneController.text = _stripCountryCode(_verifiedPhoneNumber!);
      });

      _showMessage('Mobile number verified successfully.');
    } finally {
      if (mounted) {
        setState(() => _isSendingOtp = false);
      }
    }
  }

  Future<void> _handleRegister() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_agreeTerms) {
      _showMessage('Please agree to the terms and conditions', isError: true);
      return;
    }

    if (_verifiedPhoneToken == null || _verifiedPhoneNumber == null) {
      await _verifyMobile();
      if (_verifiedPhoneToken == null || _verifiedPhoneNumber == null) {
        return;
      }
    }

    final authProvider = context.read<AuthProvider>();
    final prefs = await SharedPreferences.getInstance();
    final referralCode =
        prefs.getString(AppsFlyerDeepLinkService.pendingReferralCodeKey);
    final success = await authProvider.register(
      name: _nameController.text.trim(),
      email: _emailController.text.trim(),
      phone: _verifiedPhoneNumber!,
      verifiedPhoneToken: _verifiedPhoneToken,
      role: 'customer',
      referralCode: referralCode,
    );

    if (!mounted) return;

    if (!success) {
      _showMessage(
        authProvider.error ?? 'Registration failed',
        isError: true,
      );
      return;
    }

    if (!authProvider.canUseCurrentApp || !authProvider.isCustomer) {
      await authProvider.logout();
      if (!mounted) return;
      _showMessage(
        'Please register with a customer account.',
        isError: true,
      );
      return;
    }

    await prefs.remove(AppsFlyerDeepLinkService.pendingReferralCodeKey);
    Navigator.pushNamedAndRemoveUntil(context, '/home', (_) => false);
  }

  InputDecoration _fieldDecoration({
    required String hint,
    Widget? prefixIcon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w500,
        color: Color(0xFF9CA3AF),
      ),
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: _line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide(color: _primary, width: 1.4),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: Colors.red),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: Colors.red, width: 1.4),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        final isVerified = _verifiedPhoneToken != null;

        return Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
              child: Form(
                key: _formKey,
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
                    _buildHero(isVerified),
                    const SizedBox(height: 26),
                    const Text(
                      'Create account',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: _subtext,
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: _nameController,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                        color: _text,
                      ),
                      decoration: _fieldDecoration(
                        hint: 'Full name',
                        prefixIcon: const Icon(
                          Icons.person_outline_rounded,
                          color: _subtext,
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Name is required';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _phoneController,
                      keyboardType: TextInputType.phone,
                      readOnly: isVerified,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                        color: _text,
                      ),
                      onChanged: (_) {
                        if (isVerified) return;
                        if (_verifiedPhoneToken != null) {
                          setState(() {
                            _verifiedPhoneToken = null;
                            _verifiedPhoneNumber = null;
                          });
                        }
                      },
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
                                  border: Border.all(
                                    color: const Color(0xFFE5E7EB),
                                  ),
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
                        prefixIconConstraints:
                            const BoxConstraints(minWidth: 148),
                        suffixIcon: Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: TextButton(
                            onPressed:
                                auth.isLoading || _isSendingOtp || isVerified
                                    ? null
                                    : _verifyMobile,
                            child: Text(
                              isVerified
                                  ? 'Verified'
                                  : _isSendingOtp
                                      ? 'Sending...'
                                      : 'Verify',
                            ),
                          ),
                        ),
                      ),
                      validator: (value) {
                        return PhoneNumberUtils.validateIndianMobile(
                          value,
                          countryCode: _branding.defaultMobileCountryCode,
                        );
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                        color: _text,
                      ),
                      decoration: _fieldDecoration(
                        hint: 'Email address',
                        prefixIcon: const Icon(
                          Icons.mail_outline_rounded,
                          color: _subtext,
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Email is required';
                        }
                        if (!value.contains('@')) {
                          return 'Enter a valid email';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF8EE),
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: const Color(0xFFFFE0B2)),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            isVerified
                                ? Icons.verified_rounded
                                : Icons.info_outline_rounded,
                            color: isVerified ? Colors.green : _primary,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              isVerified
                                  ? 'Your mobile number is verified. You can finish creating your account.'
                                  : 'Verify your mobile number first. Your customer login will stay OTP only.',
                              style: const TextStyle(
                                color: _text,
                                fontSize: 14,
                                fontWeight: FontWeight.w500,
                                height: 1.45,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    CheckboxListTile(
                      value: _agreeTerms,
                      onChanged: (value) {
                        setState(() {
                          _agreeTerms = value ?? false;
                        });
                      },
                      contentPadding: EdgeInsets.zero,
                      controlAffinity: ListTileControlAffinity.leading,
                      title: const Text(
                        'I agree to the Terms & Conditions and Privacy Policy',
                        style: TextStyle(
                          color: _subtext,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    SizedBox(
                      width: double.infinity,
                      child: DecoratedBox(
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
                              : _handleRegister,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.transparent,
                            shadowColor: Colors.transparent,
                            minimumSize: const Size.fromHeight(58),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(18),
                            ),
                          ),
                          child: Text(
                            _isSendingOtp
                                ? 'Sending OTP...'
                                : auth.isLoading
                                    ? 'Creating account...'
                                    : isVerified
                                        ? 'Create Account'
                                        : 'Verify Mobile & Continue',
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w600,
                              color: Colors.black,
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    Center(
                      child: TextButton(
                        onPressed: () =>
                            Navigator.pushReplacementNamed(context, '/login/form'),
                        child: const Text.rich(
                          TextSpan(
                            text: 'Already have an account? ',
                            style: TextStyle(
                              color: _subtext,
                              fontSize: 16,
                              fontWeight: FontWeight.w500,
                            ),
                            children: [
                              TextSpan(
                                text: 'Sign In',
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
          ),
        );
      },
    );
  }

  Widget _buildHero(bool isVerified) {
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
            child: Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.18),
                shape: BoxShape.circle,
              ),
              child: Icon(
                isVerified
                    ? Icons.verified_user_rounded
                    : Icons.person_add_alt_1_rounded,
                size: 98,
                color: Colors.white,
              ),
            ),
          ),
          Positioned.fill(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(22, 28, 22, 22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 90),
                  Text(
                    isVerified ? 'Almost there' : 'Join us',
                    style: const TextStyle(
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
                  SizedBox(
                    width: 190,
                    child: Text(
                      isVerified
                          ? 'Your number is verified. Complete your profile and start ordering.'
                          : 'Create your customer account with secure OTP based sign up.',
                      style: const TextStyle(
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

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : _primary,
      ),
    );
  }
}
