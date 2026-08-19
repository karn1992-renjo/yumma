import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:food_delivery_customer/utils/app_text.dart';

import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/appsflyer_deep_link_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/phone_number_utils.dart';
import 'a1paso_auth_widgets.dart';
import 'otp_verification_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({
    super.key,
    this.initialPhone,
    this.initialEmail,
    this.verifiedPhoneToken,
  });

  final String? initialPhone;
  final String? initialEmail;
  final String? verifiedPhoneToken;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  static const _text = A1PasoAuthColors.text;
  static const _subtext = A1PasoAuthColors.subtext;
  static const _line = A1PasoAuthColors.border;

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

  String get _countryCode => _branding.defaultMobileCountryCode;

  @override
  void initState() {
    super.initState();
    _phoneController.text =
        _stripCountryCode(widget.initialPhone?.trim() ?? '');
    _emailController.text = widget.initialEmail?.trim() ?? '';
    _verifiedPhoneToken = widget.verifiedPhoneToken;
    _verifiedPhoneNumber = widget.initialPhone;
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
        _phoneController.text = _stripCountryCode(widget.initialPhone!.trim());
      }
    });
  }

  String _normalizedPhone() {
    return PhoneNumberUtils.normalizeMobile(
      _phoneController.text,
      countryCode: _countryCode,
      log: true,
    ).normalizedNumber;
  }

  String _stripCountryCode(String phone) {
    if (phone.isEmpty) return '';
    try {
      return PhoneNumberUtils.localMobile(
        phone,
        countryCode: _countryCode,
      );
    } on FormatException {
      return PhoneNumberUtils.sanitizedDigits(phone);
    }
  }

  Future<void> _verifyMobile() async {
    if (_isSendingOtp) return;
    if (_phoneController.text.trim().isEmpty) {
      _showMessage(appText('Enter your mobile number first.'), isError: true);
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
          authProvider.error ??
              appText('Unable to validate your mobile number.'),
          isError: true,
        );
        return;
      }

      if (status['exists'] == true) {
        _showMessage(
          appText(
              'An account already exists with this mobile number. Please sign in.'),
          isError: true,
        );
        return;
      }

      String? firebaseVerificationId;

      if (_branding.usesFirebasePhoneAuth) {
        try {
          firebaseVerificationId = await _firebasePhoneAuthService.sendOtp(
            phone: phone,
            countryCode: _countryCode,
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
          _showMessage(authProvider.error ?? appText('Failed to send OTP'),
              isError: true);
          return;
        }
      }

      final result = await Navigator.of(context).push<Map<String, dynamic>>(
        MaterialPageRoute(
          builder: (_) => OtpVerificationScreen(
            phoneNumber: phone,
            countryCode: _countryCode,
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

      _showMessage(appText('Mobile number verified successfully.'));
    } finally {
      if (mounted) {
        setState(() => _isSendingOtp = false);
      }
    }
  }

  Future<void> _handleRegister() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_agreeTerms) {
      _showMessage(appText('Please agree to the terms and conditions'),
          isError: true);
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
        authProvider.error ?? appText('Registration failed'),
        isError: true,
      );
      return;
    }

    if (!authProvider.canUseCurrentApp || !authProvider.isCustomer) {
      await authProvider.logout();
      if (!mounted) return;
      _showMessage(
        appText('Please register with a customer account.'),
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
    BoxConstraints? prefixIconConstraints,
    Widget? suffixIcon,
  }) {
    final primary = FoodFlowTheme.brandPrimary(context);
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(
        fontSize: 13,
        fontWeight: FontWeight.w500,
        color: Color(0xFF8B919B),
      ),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 19),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: _line, width: 1.2),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: BorderSide(color: primary, width: 1.4),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Colors.red),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Colors.red, width: 1.4),
      ),
      prefixIcon: prefixIcon,
      prefixIconConstraints: prefixIconConstraints,
      suffixIcon: suffixIcon,
    );
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        final isVerified = _verifiedPhoneToken != null;
        final busy = auth.isLoading || _isLoadingBranding || _isSendingOtp;

        return Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final compact = constraints.maxHeight < 760;
                return SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: EdgeInsets.only(bottom: bottomInset + 22),
                  child: ConstrainedBox(
                    constraints:
                        BoxConstraints(minHeight: constraints.maxHeight),
                    child: Column(
                      children: [
                        _HeroBackground(compact: compact),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 28),
                          child: Form(
                            key: _formKey,
                            child: Column(
                              children: [
                                SizedBox(height: compact ? 16 : 28),
                                Text(
                                  isVerified
                                      ? appText('Almost There!')
                                      : appText('Create Account'),
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                    color: _text,
                                    fontSize: compact ? 23 : 26,
                                    fontWeight: FontWeight.w900,
                                    height: 1,
                                  ),
                                ),
                                const SizedBox(height: 16),
                                Text(
                                  isVerified
                                      ? appText(
                                          'Your number is verified. Complete your details to continue')
                                      : appText(
                                          'Sign up to start your deliveries'),
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                    color: _subtext,
                                    fontSize: 13,
                                    fontWeight: FontWeight.w500,
                                    height: 1.2,
                                  ),
                                ),
                                const SizedBox(height: 30),
                                TextFormField(
                                  controller: _nameController,
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: _text,
                                  ),
                                  decoration: _fieldDecoration(
                                    hint: appText('Full name'),
                                    prefixIcon: const Padding(
                                      padding: EdgeInsets.only(
                                          left: 18, right: 12),
                                      child: Icon(
                                        Icons.person_outline_rounded,
                                        color: _subtext,
                                        size: 20,
                                      ),
                                    ),
                                    prefixIconConstraints:
                                        const BoxConstraints(minWidth: 52),
                                  ),
                                  validator: (value) {
                                    if (value == null ||
                                        value.trim().isEmpty) {
                                      return appText('Name is required');
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 14),
                                TextFormField(
                                  controller: _phoneController,
                                  keyboardType: TextInputType.phone,
                                  readOnly: isVerified,
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
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
                                  decoration: _fieldDecoration(
                                    hint: appText('Enter your mobile number'),
                                    prefixIcon: Padding(
                                      padding: const EdgeInsets.only(
                                          left: 18, right: 12),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          CountryFlagBadge(
                                              countryCode: _countryCode),
                                          const SizedBox(width: 12),
                                          Text(
                                            _countryCode,
                                            style: const TextStyle(
                                              color: _text,
                                              fontSize: 13,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          const SizedBox(width: 3),
                                          const Icon(
                                            Icons.keyboard_arrow_down_rounded,
                                            color: _subtext,
                                            size: 18,
                                          ),
                                          const SizedBox(width: 10),
                                        ],
                                      ),
                                    ),
                                    prefixIconConstraints:
                                        const BoxConstraints(minWidth: 116),
                                    suffixIcon: Padding(
                                      padding: const EdgeInsets.only(right: 6),
                                      child: TextButton(
                                        onPressed: busy || isVerified
                                            ? null
                                            : _verifyMobile,
                                        child: Text(
                                          isVerified
                                              ? appText('Verified')
                                              : _isSendingOtp
                                                  ? appText('Sending...')
                                                  : appText('Verify'),
                                          style: TextStyle(
                                            color: isVerified
                                                ? A1PasoAuthColors.green
                                                : FoodFlowTheme.brandPrimary(
                                                    context),
                                            fontSize: 13,
                                            fontWeight: FontWeight.w800,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                  validator: (value) {
                                    return PhoneNumberUtils.validateIndianMobile(
                                      value,
                                      countryCode: _countryCode,
                                    );
                                  },
                                ),
                                const SizedBox(height: 14),
                                TextFormField(
                                  controller: _emailController,
                                  keyboardType: TextInputType.emailAddress,
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: _text,
                                  ),
                                  decoration: _fieldDecoration(
                                    hint: appText('Email address'),
                                    prefixIcon: const Padding(
                                      padding: EdgeInsets.only(
                                          left: 18, right: 12),
                                      child: Icon(
                                        Icons.mail_outline_rounded,
                                        color: _subtext,
                                        size: 20,
                                      ),
                                    ),
                                    prefixIconConstraints:
                                        const BoxConstraints(minWidth: 52),
                                  ),
                                  validator: (value) {
                                    if (value == null ||
                                        value.trim().isEmpty) {
                                      return appText('Email is required');
                                    }
                                    if (!value.contains('@')) {
                                      return appText('Enter a valid email');
                                    }
                                    return null;
                                  },
                                ),
                                const SizedBox(height: 18),
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: A1PasoAuthColors.wash,
                                    borderRadius: BorderRadius.circular(8),
                                    border: Border.all(color: _line),
                                  ),
                                  child: Row(
                                    children: [
                                      Icon(
                                        isVerified
                                            ? Icons.verified_rounded
                                            : Icons.info_outline_rounded,
                                        color: isVerified
                                            ? A1PasoAuthColors.green
                                            : FoodFlowTheme.brandPrimary(
                                                context),
                                        size: 20,
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Text(
                                          isVerified
                                              ? appText(
                                                  'Your mobile number is verified. You can finish creating your account.')
                                              : appText(
                                                  'Verify your mobile number first. Your login will stay OTP only.'),
                                          style: const TextStyle(
                                            color: _text,
                                            fontSize: 12,
                                            fontWeight: FontWeight.w500,
                                            height: 1.4,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 6),
                                CheckboxListTile(
                                  value: _agreeTerms,
                                  onChanged: (value) {
                                    setState(() {
                                      _agreeTerms = value ?? false;
                                    });
                                  },
                                  activeColor:
                                      FoodFlowTheme.brandPrimary(context),
                                  contentPadding: EdgeInsets.zero,
                                  controlAffinity:
                                      ListTileControlAffinity.leading,
                                  title: Text(
                                    appText(
                                        'I agree to the Terms & Conditions and Privacy Policy'),
                                    style: const TextStyle(
                                      color: _subtext,
                                      fontSize: 12,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 8),
                                _ThreeDButton(
                                  height: 58,
                                  onPressed: busy ? null : _handleRegister,
                                  backgroundColor:
                                      FoodFlowTheme.brandPrimary(context),
                                  disabledColor:
                                      FoodFlowTheme.brandPrimary(context)
                                          .withOpacity(0.38),
                                  shadowColor:
                                      FoodFlowTheme.brandPrimary(context),
                                  child: Text(
                                    _isSendingOtp
                                        ? appText('Sending OTP...')
                                        : auth.isLoading
                                            ? appText('Creating account...')
                                            : isVerified
                                                ? appText('Create Account')
                                                : appText(
                                                    'Verify Mobile & Continue'),
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 17,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                                SizedBox(height: compact ? 24 : 32),
                                _SignInPrompt(),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        );
      },
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(appText(message)),
        backgroundColor:
            isError ? Colors.red : FoodFlowTheme.brandPrimary(context),
      ),
    );
  }
}

class _ThreeDButton extends StatefulWidget {
  const _ThreeDButton({
    required this.child,
    required this.onPressed,
    required this.backgroundColor,
    required this.disabledColor,
    required this.shadowColor,
    this.border,
    this.height = 58,
  });

  final Widget child;
  final VoidCallback? onPressed;
  final Color backgroundColor;
  final Color disabledColor;
  final Color shadowColor;
  final BorderSide? border;
  final double height;

  @override
  State<_ThreeDButton> createState() => _ThreeDButtonState();
}

class _ThreeDButtonState extends State<_ThreeDButton> {
  bool _pressed = false;

  bool get _enabled => widget.onPressed != null;

  @override
  Widget build(BuildContext context) {
    final surfaceColor =
        _enabled ? widget.backgroundColor : widget.disabledColor;
    final y = _pressed && _enabled ? 3.0 : 0.0;

    return GestureDetector(
      onTapDown: _enabled ? (_) => setState(() => _pressed = true) : null,
      onTapCancel: _enabled ? () => setState(() => _pressed = false) : null,
      onTapUp: _enabled
          ? (_) {
              setState(() => _pressed = false);
              widget.onPressed?.call();
            }
          : null,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 110),
        curve: Curves.easeOut,
        width: double.infinity,
        height: widget.height,
        padding: EdgeInsets.only(top: y),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8),
          boxShadow: [
            BoxShadow(
              color: widget.shadowColor.withOpacity(_enabled ? 0.28 : 0.12),
              blurRadius: _pressed ? 7 : 18,
              offset: Offset(0, _pressed ? 4 : 10),
            ),
          ],
        ),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: surfaceColor,
            borderRadius: BorderRadius.circular(8),
            border: widget.border == null
                ? null
                : Border.fromBorderSide(widget.border!),
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                Color.lerp(surfaceColor, Colors.white, _enabled ? 0.13 : 0.05)!,
                surfaceColor,
                Color.lerp(surfaceColor, Colors.black, _enabled ? 0.08 : 0.02)!,
              ],
              stops: const [0, 0.58, 1],
            ),
          ),
          child: Stack(
            children: [
              Positioned(
                left: 1,
                right: 1,
                top: 1,
                height: 15,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    borderRadius:
                        const BorderRadius.vertical(top: Radius.circular(7)),
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Colors.white.withOpacity(_enabled ? 0.30 : 0.12),
                        Colors.white.withOpacity(0),
                      ],
                    ),
                  ),
                ),
              ),
              Center(child: widget.child),
            ],
          ),
        ),
      ),
    );
  }
}

class _HeroBackground extends StatelessWidget {
  const _HeroBackground({required this.compact});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: compact ? 398 : 474,
      child: Image.asset(
        'assets/images/background.png',
        fit: BoxFit.cover,
        alignment: Alignment.topCenter,
      ),
    );
  }
}

class _SignInPrompt extends StatelessWidget {
  const _SignInPrompt();

  @override
  Widget build(BuildContext context) {
    final linkStyle = TextStyle(
      color: FoodFlowTheme.brandPrimary(context),
      fontSize: 13,
      fontWeight: FontWeight.w900,
    );

    return Center(
      child: Text.rich(
        TextSpan(
          text: appText('Already have an account? '),
          style: const TextStyle(
            color: A1PasoAuthColors.subtext,
            fontSize: 13,
            fontWeight: FontWeight.w500,
          ),
          children: [
            TextSpan(
              text: appText('Sign In'),
              style: linkStyle,
              recognizer: TapGestureRecognizer()
                ..onTap = () =>
                    Navigator.of(context).pushReplacementNamed('/login/form'),
            ),
          ],
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}
