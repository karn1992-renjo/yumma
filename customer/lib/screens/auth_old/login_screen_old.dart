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
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  static const _text = Color(0xFF111827);
  static const _subtext = Color(0xFF6B7280);
  static const _line = Color(0xFFE5E7EB);
  static const _success = Color(0xFF16A34A);

  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final FirebasePhoneAuthService _firebasePhoneAuthService =
      FirebasePhoneAuthService();
  final SocialAuthService _socialAuthService = SocialAuthService();

  AppBranding _branding = AppBranding.fallback();
  bool _isLoadingBranding = true;
  bool _isSendingOtp = false;
  bool _isSocialLoading = false;

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

  Future<void> _continueWithPhone() async {
    if (_isSendingOtp) return;
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSendingOtp = true);
    try {
      final latestBranding = await AppBrandingService.instance.loadBranding(
        forceRefresh: true,
      );
      if (!mounted) return;
      setState(() => _branding = latestBranding);

      final authProvider = context.read<AuthProvider>();
      final phone = _normalizedPhone();
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
        if (status['matches_role'] == false) {
          _showMessage(
            'This mobile number is not registered for a customer account.',
            isError: true,
          );
          return;
        }

        await _sendOtpAndOpenVerification(
          phone: phone,
          flow: 'login',
        );
        return;
      }

      final signupResult = await _sendOtpAndOpenVerification(
        phone: phone,
        flow: 'signup',
      );

      if (!mounted || signupResult == null) return;

      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => RegisterScreen(
            initialPhone: signupResult['phone']?.toString() ?? phone,
            verifiedPhoneToken:
                signupResult['verified_phone_token']?.toString(),
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      _showMessage(
        error.toString().replaceFirst('Exception: ', ''),
        isError: true,
      );
    } finally {
      if (mounted) setState(() => _isSendingOtp = false);
    }
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

      Navigator.pushNamedAndRemoveUntil(
        context,
        _homeRoute(authProvider),
        (_) => false,
      );
    } catch (error) {
      if (!mounted) return;
      _showMessage(
        error.toString().replaceFirst('Exception: ', ''),
        isError: true,
      );
    } finally {
      if (mounted) setState(() => _isSocialLoading = false);
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

  Future<Map<String, dynamic>?> _sendOtpAndOpenVerification({
    required String phone,
    required String flow,
  }) async {
    final authProvider = context.read<AuthProvider>();
    String? firebaseVerificationId;
    Map<String, dynamic>? autoVerificationResult;
    var autoVerificationHandled = false;
    var verificationScreenOpen = false;

    Future<void> handleFirebaseAutoVerified(String firebaseIdToken) async {
      if (autoVerificationHandled || !mounted) return;
      autoVerificationHandled = true;

      autoVerificationResult = await _completeFirebasePhoneVerification(
        phone: phone,
        flow: flow,
        firebaseIdToken: firebaseIdToken,
      );

      if (!mounted) return;
      if (flow == 'signup' && verificationScreenOpen) {
        Navigator.of(context).pop(autoVerificationResult);
      }
    }

    if (_branding.usesFirebasePhoneAuth) {
      final firebaseOtp =
          await _firebasePhoneAuthService.sendOtpWithAutoVerification(
        phone: phone,
        countryCode: _branding.defaultMobileCountryCode,
        onAutoVerified: handleFirebaseAutoVerified,
      );
      firebaseVerificationId = firebaseOtp.verificationId;

      if (firebaseOtp.autoVerified) {
        await handleFirebaseAutoVerified(firebaseOtp.firebaseIdToken!);
      }

      if (autoVerificationHandled) {
        return autoVerificationResult;
      }
    } else {
      final sent = await authProvider.sendLoginOtp(
        phone: phone,
        flow: flow,
        role: 'customer',
      );

      if (!mounted) return null;

      if (!sent) {
        _showMessage(authProvider.error ?? 'Failed to send OTP', isError: true);
        return null;
      }
    }

    if (!mounted) return null;

    verificationScreenOpen = true;
    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => OtpVerificationScreen(
          phoneNumber: phone,
          countryCode: _branding.defaultMobileCountryCode,
          appName: _branding.displayName,
          role: 'customer',
          flow: flow,
          useFirebasePhoneAuth: _branding.usesFirebasePhoneAuth,
          otpServiceProvider: _branding.resolvedOtpServiceProvider,
          initialFirebaseVerificationId: firebaseVerificationId,
        ),
      ),
    );
    verificationScreenOpen = false;

    return result;
  }

  Future<Map<String, dynamic>?> _completeFirebasePhoneVerification({
    required String phone,
    required String flow,
    required String firebaseIdToken,
  }) async {
    final authProvider = context.read<AuthProvider>();

    if (flow == 'signup') {
      final result = await authProvider.verifyFirebasePhone(
        phone: phone,
        firebaseIdToken: firebaseIdToken,
        flow: flow,
        role: 'customer',
      );

      if (!mounted) return null;
      if (result == null) {
        _showMessage(
          authProvider.error ?? 'OTP verification failed',
          isError: true,
        );
      }
      return result;
    }

    final success = await authProvider.loginWithPhone(
      phone: phone,
      firebaseIdToken: firebaseIdToken,
      role: 'customer',
    );

    if (!mounted) return null;

    if (!success) {
      _showMessage(authProvider.error ?? 'OTP verification failed',
          isError: true);
      return null;
    }

    if (!authProvider.canUseCurrentApp || !authProvider.isCustomer) {
      await authProvider.logout();
      if (!mounted) return null;
      _showMessage(
        'This mobile number is not linked to a customer account.',
        isError: true,
      );
      return null;
    }

    Navigator.pushNamedAndRemoveUntil(
      context,
      _homeRoute(authProvider),
      (_) => false,
    );
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Scaffold(
      backgroundColor: Color.lerp(_primary, Colors.white, 0.96),
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: EdgeInsets.fromLTRB(20, 24, 20, bottomInset + 26),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Column(
                  children: [
                    _CustomerHero(
                      primary: _primary,
                      secondary: _secondary,
                    ),
                    const SizedBox(height: 18),
                    _loginCard(),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _loginCard() {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 22),
      decoration: _panelDecoration(),
      child: Form(
        key: _formKey,
        child: Column(
          children: [
            _floatingIcon(
              icon: Icons.phone_iphone_rounded,
              color: _success,
            ),
            const SizedBox(height: 14),
            const Text(
              'Continue with mobile',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: _text,
                fontSize: 21,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'We will check your number and send a secure OTP.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: _subtext,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 22),
            TextFormField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => _continueWithPhone(),
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: _text,
              ),
              decoration: _inputDecoration(),
              validator: (value) {
                return PhoneNumberUtils.validateIndianMobile(
                  value,
                  countryCode: _branding.defaultMobileCountryCode,
                );
              },
            ),
            const SizedBox(height: 18),
            Consumer<AuthProvider>(
              builder: (context, auth, _) {
                final busy =
                    auth.isLoading || _isLoadingBranding || _isSendingOtp;
                return _primaryButton(
                  label: busy ? 'Checking...' : 'Continue',
                  icon: Icons.arrow_forward_rounded,
                  onPressed: busy ? null : _continueWithPhone,
                );
              },
            ),
            if (_branding.usesGoogleLogin || _branding.usesAppleLogin) ...[
              const SizedBox(height: 20),
              const _DividerLabel(),
              const SizedBox(height: 16),
              _socialIconRow(),
            ],
            const SizedBox(height: 20),
            const _TrustStrip(),
          ],
        ),
      ),
    );
  }

  Widget _floatingIcon({
    required IconData icon,
    required Color color,
  }) {
    return Container(
      width: 54,
      height: 54,
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.18),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Icon(icon, color: color, size: 28),
    );
  }

  Widget _socialIconRow() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        if (_branding.usesGoogleLogin)
          _SocialIconButton(
            tooltip: 'Continue with Google',
            assetPath: 'assets/icons/google-icon-logo-svgrepo-com.svg',
            isLoading: _isSocialLoading,
            onTap: () => _handleSocialLogin('google'),
          ),
        if (_branding.usesGoogleLogin && _branding.usesAppleLogin)
          const SizedBox(width: 14),
        if (_branding.usesAppleLogin)
          _SocialIconButton(
            tooltip: 'Continue with Apple',
            assetPath: 'assets/icons/apple-black-logo-svgrepo-com.svg',
            isLoading: _isSocialLoading,
            onTap: () => _handleSocialLogin('apple'),
          ),
      ],
    );
  }

  InputDecoration _inputDecoration() {
    return InputDecoration(
      hintText: 'Enter mobile number',
      hintStyle: const TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w700,
        color: Color(0xFF9CA3AF),
      ),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(15),
        borderSide: const BorderSide(color: _line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(15),
        borderSide: BorderSide(color: _primary, width: 1.4),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(15),
        borderSide: const BorderSide(color: Colors.red),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(15),
        borderSide: const BorderSide(color: Colors.red, width: 1.4),
      ),
      prefixIcon: Padding(
        padding: const EdgeInsets.only(left: 14, right: 10),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.phone_iphone_rounded,
              color: _primary,
              size: 19,
            ),
            const SizedBox(width: 8),
            Text(
              _branding.defaultMobileCountryCode,
              style: const TextStyle(
                color: _text,
                fontSize: 14,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(width: 6),
            const Icon(
              Icons.keyboard_arrow_down_rounded,
              color: _subtext,
              size: 18,
            ),
            const SizedBox(width: 8),
            Container(width: 1, height: 28, color: _line),
          ],
        ),
      ),
      prefixIconConstraints: const BoxConstraints(minWidth: 122),
    );
  }

  Widget _primaryButton({
    required String label,
    required IconData icon,
    required VoidCallback? onPressed,
  }) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: _palette.primaryGradient,
        borderRadius: BorderRadius.circular(15),
        boxShadow: [
          BoxShadow(
            color: _primary.withOpacity(0.26),
            blurRadius: 20,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: SizedBox(
        width: double.infinity,
        height: 56,
        child: ElevatedButton.icon(
          onPressed: onPressed,
          iconAlignment: IconAlignment.end,
          icon: Icon(icon, size: 20),
          label: Text(label),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.transparent,
            disabledBackgroundColor: Colors.transparent,
            shadowColor: Colors.transparent,
            foregroundColor: Colors.white,
            textStyle: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(15),
            ),
          ),
        ),
      ),
    );
  }

  BoxDecoration _panelDecoration() {
    return BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(28),
      border: Border.all(color: _line),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withOpacity(0.07),
          blurRadius: 26,
          offset: const Offset(0, 16),
        ),
        BoxShadow(
          color: Colors.white.withOpacity(0.90),
          blurRadius: 1,
          offset: const Offset(-2, -2),
        ),
      ],
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

class _DividerLabel extends StatelessWidget {
  const _DividerLabel();

  @override
  Widget build(BuildContext context) {
    return const Row(
      children: [
        Expanded(child: Divider(color: _LoginScreenState._line)),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 14),
          child: Text(
            'or',
            style: TextStyle(
              color: _LoginScreenState._subtext,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        Expanded(child: Divider(color: _LoginScreenState._line)),
      ],
    );
  }
}

class _SocialIconButton extends StatelessWidget {
  const _SocialIconButton({
    required this.tooltip,
    required this.assetPath,
    required this.isLoading,
    required this.onTap,
  });

  final String tooltip;
  final String assetPath;
  final bool isLoading;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: InkWell(
        onTap: isLoading ? null : onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          width: 54,
          height: 54,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: _LoginScreenState._line),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.055),
                blurRadius: 16,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Center(
            child: isLoading
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2.2),
                  )
                : SvgPicture.asset(
                    assetPath,
                    width: 25,
                    height: 25,
                  ),
          ),
        ),
      ),
    );
  }
}

class _CustomerHero extends StatelessWidget {
  const _CustomerHero({
    required this.primary,
    required this.secondary,
  });

  final Color primary;
  final Color secondary;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 282,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            child: CustomPaint(
              painter: _CustomerHeroPainter(primary),
            ),
          ),
          Positioned(
            top: 30,
            left: 0,
            child: Image.asset(
              'assets/images/login.png',
              width: 144,
              height: 68,
              fit: BoxFit.contain,
            ),
          ),
          Positioned(
            top: 54,
            right: -10,
            child: Image.asset(
              'assets/images/customer.png',
              width: 188,
              height: 154,
              fit: BoxFit.contain,
            ),
          ),
          Positioned(
            left: 0,
            right: 142,
            bottom: 10,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Welcome back,',
                  style: TextStyle(
                    color: primary,
                    fontSize: 22,
                    height: 1.05,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const Text(
                  'Foodie!',
                  style: TextStyle(
                    color: _LoginScreenState._text,
                    fontSize: 42,
                    height: 1.02,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 16),
                Text.rich(
                  TextSpan(
                    text: 'Order favorites,\ntrack delivery & ',
                    style: const TextStyle(
                      color: _LoginScreenState._subtext,
                      fontSize: 16,
                      height: 1.35,
                      fontWeight: FontWeight.w800,
                    ),
                    children: [
                      TextSpan(
                        text: 'eat happy.',
                        style: TextStyle(
                          color: primary,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CustomerHeroPainter extends CustomPainter {
  const _CustomerHeroPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final wash = Paint()
      ..color = color.withOpacity(0.045)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(Offset(size.width * 0.88, size.height * 0.43), 122, wash);

    final dotPaint = Paint()
      ..color = color.withOpacity(0.06)
      ..style = PaintingStyle.fill;
    for (var row = 0; row < 7; row++) {
      for (var col = 0; col < 3; col++) {
        canvas.drawCircle(Offset(col * 20.0, 26 + row * 20.0), 4, dotPaint);
      }
    }

    final linePaint = Paint()
      ..color = color.withOpacity(0.13)
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final baseY = size.height * 0.78;
    canvas.drawLine(
      Offset(size.width * 0.52, baseY),
      Offset(size.width * 0.98, baseY),
      linePaint,
    );
    _drawCloud(canvas, linePaint, Offset(size.width * 0.64, 58), 22);
    _drawCloud(canvas, linePaint, Offset(size.width * 0.84, 36), 28);
    _drawCloud(canvas, linePaint, Offset(size.width * 0.52, 108), 18);
  }

  void _drawCloud(Canvas canvas, Paint paint, Offset center, double width) {
    final path = Path()
      ..moveTo(center.dx - width * 0.50, center.dy)
      ..quadraticBezierTo(center.dx - width * 0.28, center.dy - width * 0.22,
          center.dx - width * 0.08, center.dy - width * 0.06)
      ..quadraticBezierTo(center.dx + width * 0.08, center.dy - width * 0.36,
          center.dx + width * 0.30, center.dy - width * 0.08)
      ..quadraticBezierTo(center.dx + width * 0.48, center.dy - width * 0.06,
          center.dx + width * 0.55, center.dy);
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _CustomerHeroPainter oldDelegate) {
    return oldDelegate.color != color;
  }
}

class _TrustStrip extends StatelessWidget {
  const _TrustStrip();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: BoxDecoration(
        color: const Color(0xFFF8F5FF),
        borderRadius: BorderRadius.circular(14),
      ),
      child: const Row(
        children: [
          Expanded(
            child: _TrustTile(
              icon: Icons.verified_user_outlined,
              title: 'Secure OTP',
              subtitle: 'Protected login',
            ),
          ),
          _TrustDivider(),
          Expanded(
            child: _TrustTile(
              icon: Icons.restaurant_menu_rounded,
              title: 'Fast Orders',
              subtitle: 'Saved details',
            ),
          ),
          _TrustDivider(),
          Expanded(
            child: _TrustTile(
              icon: Icons.delivery_dining_rounded,
              title: 'Live Tracking',
              subtitle: 'Order updates',
            ),
          ),
        ],
      ),
    );
  }
}

class _TrustDivider extends StatelessWidget {
  const _TrustDivider();

  @override
  Widget build(BuildContext context) {
    return Container(width: 1, height: 72, color: _LoginScreenState._line);
  }
}

class _TrustTile extends StatelessWidget {
  const _TrustTile({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: Column(
        children: [
          Icon(icon, color: primary, size: 28),
          const SizedBox(height: 10),
          Text(
            title,
            textAlign: TextAlign.center,
            maxLines: 2,
            style: const TextStyle(
              color: _LoginScreenState._text,
              fontSize: 11,
              height: 1.1,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _LoginScreenState._subtext,
              fontSize: 10,
              height: 1.25,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
