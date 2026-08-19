import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'package:food_delivery_restaurant/utils/app_text.dart';

import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/phone_number_utils.dart';
import 'a1paso_auth_widgets.dart';
import 'otp_verification_screen.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  static const _text = A1PasoAuthColors.text;
  static const _subtext = A1PasoAuthColors.subtext;
  static const _line = A1PasoAuthColors.border;

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

  String get _countryCode => _branding.defaultMobileCountryCode;

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
    return PhoneNumberUtils.normalizeMobile(
      _phoneController.text,
      countryCode: _countryCode,
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
        role: 'restaurant',
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
        if (status['matches_role'] == false) {
          _showMessage(
            appText(
                'This mobile number is not registered for a restaurant account.'),
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

      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => RegisterScreen(initialPhone: phone),
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
    if (_isSendingOtp || !_formKey.currentState!.validate()) return;
    final authProvider = context.read<AuthProvider>();
    final success = await authProvider.login(
      email: _emailController.text.trim(),
      password: _passwordController.text,
      role: 'restaurant',
    );
    if (!mounted) return;
    if (!success) {
      _showMessage(
        authProvider.error?.replaceFirst('Exception: ', '') ??
            appText('Login failed'),
        isError: true,
      );
      return;
    }
    Navigator.pushNamedAndRemoveUntil(
      context,
      '/restaurant/dashboard',
      (_) => false,
    );
  }

  void _submitLogin() {
    if (_usePasswordLogin) {
      _loginWithPassword();
    } else {
      _continueWithPhone();
    }
  }

  Future<Map<String, dynamic>?> _sendOtpAndOpenVerification({
    required String phone,
    required String flow,
  }) async {
    final authProvider = context.read<AuthProvider>();
    String? firebaseVerificationId;

    if (_branding.usesFirebasePhoneAuth) {
      firebaseVerificationId = await _firebasePhoneAuthService.sendOtp(
        phone: phone,
        countryCode: _countryCode,
      );
    } else {
      final sent = await authProvider.sendLoginOtp(
        phone: phone,
        flow: flow,
        role: 'restaurant',
      );

      if (!mounted) return null;

      if (!sent) {
        _showMessage(authProvider.error ?? appText('Failed to send OTP'),
            isError: true);
        return null;
      }
    }

    if (!mounted) return null;

    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => OtpVerificationScreen(
          phoneNumber: phone,
          countryCode: _countryCode,
          appName: _branding.displayName,
          role: 'restaurant',
          flow: flow,
          useFirebasePhoneAuth: _branding.usesFirebasePhoneAuth,
          otpServiceProvider: _branding.resolvedOtpServiceProvider,
          initialFirebaseVerificationId: firebaseVerificationId,
        ),
      ),
    );

    return result;
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxHeight < 760;
            return SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: EdgeInsets.only(bottom: bottomInset + 22),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Column(
                  children: [
                    _HeroBackground(compact: compact),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 28),
                      child: Column(
                        children: [
                          SizedBox(height: compact ? 16 : 28),
                          Text(
                            'Welcome Back!',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: _text,
                              fontSize: compact ? 23 : 26,
                              fontWeight: FontWeight.w900,
                              height: 1,
                            ),
                          ),
                          const SizedBox(height: 16),
                          const Text(
                            'Login to continue your deliveries',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: _subtext,
                              fontSize: 13,
                              fontWeight: FontWeight.w500,
                              height: 1.2,
                            ),
                          ),
                          const SizedBox(height: 30),
                          Form(
                            key: _formKey,
                            child: AnimatedSwitcher(
                              duration: const Duration(milliseconds: 180),
                              child: _usePasswordLogin
                                  ? _passwordFields()
                                  : _phoneField(),
                            ),
                          ),
                          const SizedBox(height: 18),
                          Consumer<AuthProvider>(
                            builder: (context, auth, _) {
                              final busy = auth.isLoading ||
                                  _isLoadingBranding ||
                                  _isSendingOtp;
                              return _continueButton(
                                label: busy
                                    ? (_usePasswordLogin
                                        ? appText('Logging in...')
                                        : appText('Checking...'))
                                    : (_usePasswordLogin
                                        ? appText('Login')
                                        : appText('Continue')),
                                onPressed: busy ? null : _submitLogin,
                              );
                            },
                          ),
                          const SizedBox(height: 20),
                          const _DividerLabel(),
                          const SizedBox(height: 8),
                          TextButton(
                            onPressed: () => setState(
                              () => _usePasswordLogin = !_usePasswordLogin,
                            ),
                            child: Text(
                              _usePasswordLogin
                                  ? 'Use mobile OTP'
                                  : 'Use email and password',
                              style: TextStyle(
                                color: FoodFlowTheme.orange,
                                fontSize: 13,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Text(
                                'New to the partner portal?',
                                style: TextStyle(
                                  color: _subtext,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              TextButton(
                                onPressed: () =>
                                    Navigator.pushNamed(context, '/register'),
                                child: Text(
                                  'Register',
                                  style: TextStyle(
                                    color: FoodFlowTheme.orange,
                                    fontSize: 13,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          SizedBox(height: compact ? 12 : 20),
                          const _TermsText(),
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

  Widget _phoneField() {
    return TextFormField(
      key: const ValueKey('phone-login'),
      controller: _phoneController,
      keyboardType: TextInputType.phone,
      textInputAction: TextInputAction.done,
      onFieldSubmitted: (_) => _continueWithPhone(),
      style: const TextStyle(
        fontSize: 13,
        fontWeight: FontWeight.w700,
        color: _text,
      ),
      decoration: _inputDecoration(),
      validator: (value) => PhoneNumberUtils.validateIndianMobile(
        value,
        countryCode: _countryCode,
      ),
    );
  }

  Widget _passwordFields() {
    return Column(
      key: const ValueKey('password-login'),
      children: [
        TextFormField(
          controller: _emailController,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: _text,
          ),
          decoration: _credentialDecoration(
            hint: 'Enter your email address',
            icon: Icons.mail_outline_rounded,
          ),
          validator: (value) {
            final email = value?.trim() ?? '';
            if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
              return 'Enter a valid email address.';
            }
            return null;
          },
        ),
        const SizedBox(height: 12),
        TextFormField(
          controller: _passwordController,
          obscureText: !_isPasswordVisible,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _loginWithPassword(),
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: _text,
          ),
          decoration: _credentialDecoration(
            hint: 'Enter your password',
            icon: Icons.lock_outline_rounded,
          ).copyWith(
            suffixIcon: IconButton(
              tooltip: _isPasswordVisible ? 'Hide password' : 'Show password',
              onPressed: () => setState(
                () => _isPasswordVisible = !_isPasswordVisible,
              ),
              icon: Icon(
                _isPasswordVisible
                    ? Icons.visibility_off_outlined
                    : Icons.visibility_outlined,
                size: 20,
              ),
            ),
          ),
          validator: (value) =>
              (value ?? '').isEmpty ? 'Enter your password.' : null,
        ),
      ],
    );
  }

  InputDecoration _credentialDecoration({
    required String hint,
    required IconData icon,
  }) {
    final primary = FoodFlowTheme.orange;
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(
        fontSize: 13,
        fontWeight: FontWeight.w500,
        color: Color(0xFF8B919B),
      ),
      prefixIcon: Icon(icon, size: 20, color: _subtext),
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
    );
  }

  InputDecoration _inputDecoration() {
    final primary = FoodFlowTheme.orange;
    return InputDecoration(
      hintText: 'Enter your mobile number',
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
      prefixIcon: Padding(
        padding: const EdgeInsets.only(left: 18, right: 12),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            CountryFlagBadge(countryCode: _countryCode),
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
      prefixIconConstraints: const BoxConstraints(minWidth: 116),
    );
  }

  Widget _continueButton({
    required String label,
    required VoidCallback? onPressed,
  }) {
    return _ThreeDButton(
      height: 58,
      onPressed: onPressed,
      backgroundColor: FoodFlowTheme.orange,
      disabledColor: FoodFlowTheme.orange.withOpacity(0.38),
      shadowColor: FoodFlowTheme.orange,
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 17,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(appText(message)),
        backgroundColor: isError ? Colors.red : FoodFlowTheme.orange,
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
    this.height = 58,
  });

  final Widget child;
  final VoidCallback? onPressed;
  final Color backgroundColor;
  final Color disabledColor;
  final Color shadowColor;
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

class _DividerLabel extends StatelessWidget {
  const _DividerLabel();

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Expanded(child: Divider(color: _LoginScreenState._line)),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Text(
            appText('or'),
            style: const TextStyle(
              color: _LoginScreenState._subtext,
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
        const Expanded(child: Divider(color: _LoginScreenState._line)),
      ],
    );
  }
}

class _TermsText extends StatelessWidget {
  const _TermsText();

  void _openLegal(BuildContext context) {
    Navigator.of(context).pushNamed('/restaurant/profile/legal');
  }

  @override
  Widget build(BuildContext context) {
    final baseStyle = const TextStyle(
      color: _LoginScreenState._subtext,
      fontSize: 13,
      fontWeight: FontWeight.w500,
      height: 1.65,
    );
    final linkStyle = baseStyle.copyWith(
      color: FoodFlowTheme.orange,
      fontWeight: FontWeight.w900,
    );

    return Text.rich(
      TextSpan(
        text: appText('By continuing, you agree to our'),
        style: baseStyle,
        children: [
          const TextSpan(text: '\n'),
          TextSpan(
            text: appText('Terms of Service'),
            style: linkStyle,
            recognizer: TapGestureRecognizer()
              ..onTap = () => _openLegal(context),
          ),
          TextSpan(text: appText(' and our ')),
          TextSpan(
            text: appText('Privacy Policy'),
            style: linkStyle,
            recognizer: TapGestureRecognizer()
              ..onTap = () => _openLegal(context),
          ),
        ],
      ),
      textAlign: TextAlign.center,
    );
  }
}
