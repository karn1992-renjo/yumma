import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../theme/foodflow_theme.dart';
import 'otp_verification_screen.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final FirebasePhoneAuthService _firebasePhoneAuthService =
      FirebasePhoneAuthService();

  AppBranding _branding = AppBranding.fallback();
  bool _isLoadingBranding = true;
  bool _isSendingOtp = false;
  bool _usePasswordLogin = false;
  bool _isPasswordVisible = false;

  Color get _brand => FoodFlowTheme.orange;

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
      final latestBranding = await AppBrandingService.instance.loadBranding(
        forceRefresh: true,
      );
      if (!mounted) return;
      setState(() => _branding = latestBranding);

      final authProvider = context.read<AuthProvider>();
      final phone = _normalizedPhone();
      final status = await authProvider.getPhoneStatus(
        phone: phone,
        role: 'driver',
      );

      if (!mounted) return;

      if (status == null) {
        _showMessage(
          authProvider.error ?? 'Unable to validate your mobile number.',
          isError: true,
        );
        return;
      }

      if (status['exists'] != true) {
        final pendingApplication = status['pending_application'];
        if (pendingApplication is Map &&
            (pendingApplication['application_number']?.toString().isNotEmpty ??
                false)) {
          Navigator.pushNamed(
            context,
            '/application-status',
            arguments: pendingApplication['application_number']?.toString(),
          );
          return;
        }

        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => RegisterScreen(initialPhone: phone),
          ),
        );
        return;
      }

      if (status['matches_role'] == false) {
        _showMessage(
          'This mobile number is not registered for a driver account.',
          isError: true,
        );
        return;
      }

      String? firebaseVerificationId;

      if (_branding.usesFirebasePhoneAuth) {
        firebaseVerificationId = await _firebasePhoneAuthService.sendOtp(
          phone: phone,
          countryCode: _branding.defaultMobileCountryCode,
        );
      } else {
        final success = await authProvider.sendLoginOtp(
          phone: phone,
          role: 'driver',
        );

        if (!mounted) return;

        if (!success) {
          _showMessage(authProvider.error ?? 'Failed to send OTP',
              isError: true);
          return;
        }
      }

      if (!mounted) return;

      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => OtpVerificationScreen(
            phoneNumber: phone,
            countryCode: _branding.defaultMobileCountryCode,
            appName: _branding.displayName,
            role: 'driver',
            flow: 'login',
            useFirebasePhoneAuth: _branding.usesFirebasePhoneAuth,
            otpServiceProvider: _branding.resolvedOtpServiceProvider,
            initialFirebaseVerificationId: firebaseVerificationId,
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

  Future<void> _loginWithPassword() async {
    if (!_formKey.currentState!.validate()) return;

    final authProvider = context.read<AuthProvider>();
    final success = await authProvider.login(
      email: _emailController.text.trim(),
      password: _passwordController.text,
      role: 'driver',
    );

    if (!mounted) return;
    if (!success) {
      _showMessage(authProvider.error ?? 'Login failed', isError: true);
      return;
    }

    Navigator.pushReplacementNamed(context, '/driver/dashboard');
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
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Scaffold(
      backgroundColor: _brand.withOpacity(0.04),
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
                    const _DriverLoginHero(),
                    const SizedBox(height: 18),
                    _signInCard(),
                    const SizedBox(height: 24),
                    Center(
                      child: Column(
                        children: [
                          const Text(
                            'New to delivery partner portal?',
                            style: TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          TextButton.icon(
                            onPressed: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const RegisterScreen(),
                              ),
                            ),
                            iconAlignment: IconAlignment.end,
                            icon: Icon(
                              Icons.chevron_right_rounded,
                              color: _brand,
                              size: 22,
                            ),
                            label: Text(
                              'Register as Driver',
                              style: TextStyle(
                                color: _brand,
                                fontSize: 17,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ],
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
  }

  Widget _signInCard() {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 26, 20, 22),
      decoration: _largePanelDecoration(),
      child: Column(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: FoodFlowTheme.success.withOpacity(0.10),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.verified_user_outlined,
              color: FoodFlowTheme.success,
              size: 28,
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'Sign in to your account',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Use mobile OTP or staff email credentials.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 13,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 22),
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
          const SizedBox(height: 18),
          Consumer<AuthProvider>(
            builder: (context, auth, _) {
              final isBusy =
                  auth.isLoading || _isLoadingBranding || _isSendingOtp;
              return _brandedActionButton(
                isBusy: isBusy,
                label: auth.isLoading || _isSendingOtp
                    ? (_usePasswordLogin ? 'Logging in...' : 'Sending OTP...')
                    : _usePasswordLogin
                        ? 'Login'
                        : 'Send OTP',
                icon:
                    _usePasswordLogin ? Icons.login_rounded : Icons.sms_rounded,
                onPressed: isBusy ? null : _submitLogin,
              );
            },
          ),
          const SizedBox(height: 22),
          _DividerLabel(),
          const SizedBox(height: 20),
          const _FeatureStrip(),
        ],
      ),
    );
  }

  Widget _brandedActionButton({
    required bool isBusy,
    required String label,
    required IconData icon,
    required VoidCallback? onPressed,
  }) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: FoodFlowTheme.brandGradient,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: _brand.withOpacity(0.24),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: SizedBox(
        width: double.infinity,
        height: 56,
        child: ElevatedButton.icon(
          onPressed: onPressed,
          icon: Icon(icon, size: 20),
          label: Text(label),
          style: ElevatedButton.styleFrom(
            disabledBackgroundColor: Colors.transparent,
            backgroundColor: Colors.transparent,
            foregroundColor: Colors.white,
            shadowColor: Colors.transparent,
            textStyle: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
      ),
    );
  }

  Widget _loginModeToggle() {
    return Container(
      height: 50,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: _brand.withOpacity(0.06),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        children: [
          _modeButton(
            'Mobile OTP',
            !_usePasswordLogin,
            Icons.phone_iphone_rounded,
            false,
          ),
          _modeButton(
            'Email Login',
            _usePasswordLogin,
            Icons.mail_outline_rounded,
            true,
          ),
        ],
      ),
    );
  }

  Widget _modeButton(
    String label,
    bool selected,
    IconData icon,
    bool emailMode,
  ) {
    return Expanded(
      child: InkWell(
        onTap: () => setState(() => _usePasswordLogin = emailMode),
        borderRadius: BorderRadius.circular(10),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? Colors.white : Colors.transparent,
            borderRadius: BorderRadius.circular(10),
            boxShadow: selected
                ? [
                    BoxShadow(
                      color: _brand.withOpacity(0.12),
                      blurRadius: 12,
                      offset: const Offset(0, 5),
                    ),
                  ]
                : null,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 16,
                color: selected ? _brand : FoodFlowTheme.muted,
              ),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(
                  color: selected ? FoodFlowTheme.ink : FoodFlowTheme.muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _phoneField() {
    return TextFormField(
      controller: _phoneController,
      keyboardType: TextInputType.phone,
      textInputAction: TextInputAction.done,
      onFieldSubmitted: (_) => _requestOtp(),
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w800,
        color: FoodFlowTheme.ink,
      ),
      decoration: _inputDecoration(
        hintText: 'Enter mobile number',
        prefixIcon: Padding(
          padding: const EdgeInsets.only(left: 14, right: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.phone_iphone_rounded,
                size: 18,
                color: _brand,
              ),
              const SizedBox(width: 8),
              Text(
                _branding.defaultMobileCountryCode,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                  color: FoodFlowTheme.ink,
                ),
              ),
              const SizedBox(width: 6),
              const Icon(
                Icons.keyboard_arrow_down_rounded,
                size: 18,
                color: FoodFlowTheme.muted,
              ),
              const SizedBox(width: 8),
              Container(width: 1, height: 28, color: FoodFlowTheme.line),
            ],
          ),
        ),
        prefixIconConstraints: const BoxConstraints(minWidth: 122),
      ),
      validator: (value) {
        final digits = (value ?? '').replaceAll(RegExp(r'\D'), '');
        if (digits.length < 8) return 'Enter a valid mobile number.';
        return null;
      },
    );
  }

  Widget _emailField() {
    return TextFormField(
      controller: _emailController,
      keyboardType: TextInputType.emailAddress,
      textInputAction: TextInputAction.next,
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w800,
        color: FoodFlowTheme.ink,
      ),
      decoration: _inputDecoration(
        hintText: 'Email address',
        prefixIcon:
            const Icon(Icons.email_outlined, color: FoodFlowTheme.muted),
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
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w800,
        color: FoodFlowTheme.ink,
      ),
      decoration: _inputDecoration(
        hintText: 'Password',
        prefixIcon:
            const Icon(Icons.lock_outline_rounded, color: FoodFlowTheme.muted),
        suffixIcon: IconButton(
          icon: Icon(
            _isPasswordVisible
                ? Icons.visibility_off_outlined
                : Icons.visibility_outlined,
            color: FoodFlowTheme.muted,
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
        fontSize: 15,
        fontWeight: FontWeight.w700,
        color: FoodFlowTheme.faint,
      ),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: FoodFlowTheme.line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: _brand, width: 1.4),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: FoodFlowTheme.danger),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: FoodFlowTheme.danger),
      ),
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      prefixIconConstraints: prefixIconConstraints,
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? FoodFlowTheme.danger : FoodFlowTheme.success,
      ),
    );
  }
}

class _DriverLoginHero extends StatelessWidget {
  const _DriverLoginHero();

  @override
  Widget build(BuildContext context) {
    final brand = FoodFlowTheme.orange;
    return SizedBox(
      height: 278,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            child: CustomPaint(
              painter: _DriverHeroPainter(brand),
            ),
          ),
          Positioned(
            top: 28,
            left: 0,
            child: Image.asset(
              'assets/images/login.png',
              width: 144,
              height: 68,
              fit: BoxFit.contain,
            ),
          ),
          Positioned(
            top: 58,
            right: -8,
            child: Image.asset(
              'assets/images/scooter.png',
              width: 206,
              height: 154,
              fit: BoxFit.contain,
            ),
          ),
          Positioned(
            left: 0,
            right: 138,
            bottom: 10,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Welcome back,',
                  style: TextStyle(
                    color: brand,
                    fontSize: 22,
                    height: 1.05,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const Text(
                  'Driver!',
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 42,
                    height: 1.02,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 16),
                Text.rich(
                  TextSpan(
                    text: 'Accept trips,\ntrack earnings & ',
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 16,
                      height: 1.35,
                      fontWeight: FontWeight.w800,
                    ),
                    children: [
                      TextSpan(
                        text: 'deliver faster.',
                        style: TextStyle(
                          color: brand,
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

class _DriverHeroPainter extends CustomPainter {
  const _DriverHeroPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final wash = Paint()
      ..color = color.withOpacity(0.045)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(Offset(size.width * 0.88, size.height * 0.42), 122, wash);

    final roadPaint = Paint()
      ..color = color.withOpacity(0.16)
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final baseY = size.height * 0.77;
    canvas.drawLine(
      Offset(size.width * 0.50, baseY),
      Offset(size.width * 0.98, baseY),
      roadPaint,
    );
    for (var i = 0; i < 4; i++) {
      final start = size.width * (0.52 + i * 0.11);
      canvas.drawLine(
        Offset(start, baseY + 18),
        Offset(start + 26, baseY + 18),
        roadPaint,
      );
    }

    final cloudPaint = Paint()
      ..color = color.withOpacity(0.10)
      ..strokeWidth = 1.5
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;
    _drawCloud(canvas, cloudPaint, Offset(size.width * 0.66, 58), 22);
    _drawCloud(canvas, cloudPaint, Offset(size.width * 0.85, 38), 28);
    _drawCloud(canvas, cloudPaint, Offset(size.width * 0.54, 106), 18);

    final dotPaint = Paint()
      ..color = color.withOpacity(0.06)
      ..style = PaintingStyle.fill;
    for (var row = 0; row < 7; row++) {
      for (var col = 0; col < 3; col++) {
        canvas.drawCircle(Offset(col * 20.0, 26 + row * 20.0), 4, dotPaint);
      }
    }
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
  bool shouldRepaint(covariant _DriverHeroPainter oldDelegate) {
    return oldDelegate.color != color;
  }
}

class _DividerLabel extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return const Row(
      children: [
        Expanded(child: Divider(color: FoodFlowTheme.line)),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 14),
          child: Text(
            'or',
            style: TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        Expanded(child: Divider(color: FoodFlowTheme.line)),
      ],
    );
  }
}

class _FeatureStrip extends StatelessWidget {
  const _FeatureStrip();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: BoxDecoration(
        color: FoodFlowTheme.orange.withOpacity(0.06),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: const [
          Expanded(
            child: _FeatureTile(
              icon: Icons.verified_user_outlined,
              title: 'Secure Login',
              subtitle: 'Your account stays protected',
            ),
          ),
          _FeatureDivider(),
          Expanded(
            child: _FeatureTile(
              icon: Icons.bolt_outlined,
              title: 'Quick Trips',
              subtitle: 'Get online and start earning',
            ),
          ),
          _FeatureDivider(),
          Expanded(
            child: _FeatureTile(
              icon: Icons.support_agent_rounded,
              title: '24/7 Support',
              subtitle: 'Help is always available',
            ),
          ),
        ],
      ),
    );
  }
}

class _FeatureDivider extends StatelessWidget {
  const _FeatureDivider();

  @override
  Widget build(BuildContext context) {
    return Container(width: 1, height: 76, color: FoodFlowTheme.line);
  }
}

class _FeatureTile extends StatelessWidget {
  const _FeatureTile({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: Column(
        children: [
          Icon(icon, color: FoodFlowTheme.orange, size: 30),
          const SizedBox(height: 10),
          Text(
            title,
            textAlign: TextAlign.center,
            maxLines: 2,
            style: const TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 11,
              height: 1.1,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: FoodFlowTheme.muted,
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

BoxDecoration _largePanelDecoration() {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(28),
    border: Border.all(color: FoodFlowTheme.line),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withOpacity(0.055),
        blurRadius: 22,
        offset: const Offset(0, 12),
      ),
    ],
  );
}
