import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/appsflyer_deep_link_service.dart';
import '../../theme/brand_palette.dart';
import 'login_screen.dart';

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
  static const _text = Color(0xFF111827);
  static const _subtext = Color(0xFF6B7280);
  static const _line = Color(0xFFE5E7EB);
  static const _success = Color(0xFF16A34A);

  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _referralController = TextEditingController();

  AppBranding _branding = AppBranding.fallback();
  bool _isLoadingBranding = true;

  BrandPalette get _palette => BrandPalette.fromBranding(_branding);
  Color get _primary => _palette.primary;
  Color get _secondary => _palette.secondary;

  String get _phone => (widget.initialPhone ?? '').trim();
  String get _verifiedPhoneToken => (widget.verifiedPhoneToken ?? '').trim();
  bool get _canComplete => _phone.isNotEmpty && _verifiedPhoneToken.isNotEmpty;

  @override
  void initState() {
    super.initState();
    _loadBranding();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _referralController.dispose();
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

  Future<void> _completeRegistration() async {
    if (!_canComplete) {
      _showMessage('Please verify your mobile number first.', isError: true);
      return;
    }
    if (!_formKey.currentState!.validate()) return;

    final authProvider = context.read<AuthProvider>();
    final prefs = await SharedPreferences.getInstance();
    final storedReferralCode =
        prefs.getString(AppsFlyerDeepLinkService.pendingReferralCodeKey);
    final manualReferralCode = _referralController.text.trim();
    final referralCode =
        manualReferralCode.isNotEmpty ? manualReferralCode : storedReferralCode;

    final success = await authProvider.register(
      name: _nameController.text.trim(),
      email: '',
      phone: _phone,
      verifiedPhoneToken: _verifiedPhoneToken,
      role: 'customer',
      referralCode: referralCode,
    );

    if (!mounted) return;

    if (!success) {
      _showMessage(
        authProvider.error?.replaceFirst('Exception: ', '') ??
            'Registration failed',
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
    if (!mounted) return;
    Navigator.pushNamedAndRemoveUntil(context, '/home', (_) => false);
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        return Scaffold(
          backgroundColor: Color.lerp(_primary, Colors.white, 0.96),
          body: SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                return SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: EdgeInsets.fromLTRB(20, 20, 20, bottomInset + 26),
                  child: ConstrainedBox(
                    constraints:
                        BoxConstraints(minHeight: constraints.maxHeight),
                    child: Column(
                      children: [
                        Align(
                          alignment: Alignment.centerLeft,
                          child: IconButton(
                            onPressed: () => Navigator.pushAndRemoveUntil(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const LoginScreen(),
                              ),
                              (_) => false,
                            ),
                            icon: const Icon(Icons.arrow_back_ios_new_rounded),
                          ),
                        ),
                        _NameHero(primary: _primary, secondary: _secondary),
                        const SizedBox(height: 18),
                        _canComplete
                            ? _nameCard(auth)
                            : _missingVerificationCard(),
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

  Widget _nameCard(AuthProvider auth) {
    final busy = auth.isLoading || _isLoadingBranding;
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 22),
      decoration: _panelDecoration(),
      child: Form(
        key: _formKey,
        child: Column(
          children: [
            Container(
              width: 54,
              height: 54,
              decoration: BoxDecoration(
                color: _success.withOpacity(0.12),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: _success.withOpacity(0.18),
                    blurRadius: 18,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: const Icon(
                Icons.verified_user_outlined,
                color: _success,
                size: 28,
              ),
            ),
            const SizedBox(height: 14),
            const Text(
              'Tell us your name',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: _text,
                fontSize: 21,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              _phone,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: _subtext,
                fontSize: 13,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 22),
            TextFormField(
              controller: _nameController,
              textInputAction: TextInputAction.done,
              textCapitalization: TextCapitalization.words,
              onFieldSubmitted: (_) => _completeRegistration(),
              style: const TextStyle(
                color: _text,
                fontSize: 15,
                fontWeight: FontWeight.w800,
              ),
              decoration: _inputDecoration(),
              validator: (value) {
                if ((value ?? '').trim().isEmpty) {
                  return 'Name is required';
                }
                if ((value ?? '').trim().length < 2) {
                  return 'Enter your full name';
                }
                return null;
              },
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _referralController,
              textInputAction: TextInputAction.done,
              textCapitalization: TextCapitalization.characters,
              onFieldSubmitted: (_) => _completeRegistration(),
              decoration: _inputDecoration(
                label: 'Referral code (optional)',
                icon: Icons.card_giftcard_outlined,
              ),
            ),
            const SizedBox(height: 18),
            _primaryButton(
              label: busy ? 'Creating account...' : 'Start Ordering',
              onPressed: busy ? null : _completeRegistration,
            ),
          ],
        ),
      ),
    );
  }

  Widget _missingVerificationCard() {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: _panelDecoration(),
      child: Column(
        children: [
          Icon(
            Icons.phone_locked_rounded,
            color: _primary,
            size: 42,
          ),
          const SizedBox(height: 14),
          const Text(
            'Verify mobile first',
            style: TextStyle(
              color: _text,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Start from the login screen so we can verify your mobile number before creating your account.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: _subtext,
              fontSize: 14,
              height: 1.4,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 18),
          _primaryButton(
            label: 'Go to Login',
            onPressed: () => Navigator.pushAndRemoveUntil(
              context,
              MaterialPageRoute(builder: (_) => const LoginScreen()),
              (_) => false,
            ),
          ),
        ],
      ),
    );
  }

  InputDecoration _inputDecoration({
    String label = 'Your full name',
    IconData icon = Icons.person_outline_rounded,
  }) {
    return InputDecoration(
      hintText: label,
      hintStyle: const TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w700,
        color: Color(0xFF9CA3AF),
      ),
      prefixIcon: Icon(
        icon,
        color: _primary,
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
    );
  }

  Widget _primaryButton({
    required String label,
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
          icon: const Icon(Icons.arrow_forward_rounded, size: 20),
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

class _NameHero extends StatelessWidget {
  const _NameHero({
    required this.primary,
    required this.secondary,
  });

  final Color primary;
  final Color secondary;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 248,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            child: CustomPaint(
              painter: _NameHeroPainter(primary),
            ),
          ),
          Positioned(
            top: 22,
            left: 0,
            child: Image.asset(
              'assets/images/login.png',
              width: 138,
              height: 64,
              fit: BoxFit.contain,
            ),
          ),
          Positioned(
            top: 48,
            right: -8,
            child: Image.asset(
              'assets/images/customer.png',
              width: 158,
              height: 128,
              fit: BoxFit.contain,
            ),
          ),
          Positioned(
            left: 0,
            right: 128,
            bottom: 4,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Almost done,',
                  style: TextStyle(
                    color: primary,
                    fontSize: 22,
                    height: 1.05,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const Text(
                  'Welcome!',
                  style: TextStyle(
                    color: _RegisterScreenState._text,
                    fontSize: 40,
                    height: 1.02,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  'Add your name and start ordering.',
                  style: TextStyle(
                    color: _RegisterScreenState._subtext,
                    fontSize: 15,
                    height: 1.35,
                    fontWeight: FontWeight.w800,
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

class _NameHeroPainter extends CustomPainter {
  const _NameHeroPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final wash = Paint()
      ..color = color.withOpacity(0.045)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(Offset(size.width * 0.86, size.height * 0.44), 108, wash);

    final dotPaint = Paint()
      ..color = color.withOpacity(0.06)
      ..style = PaintingStyle.fill;
    for (var row = 0; row < 6; row++) {
      for (var col = 0; col < 3; col++) {
        canvas.drawCircle(Offset(col * 20.0, 24 + row * 20.0), 4, dotPaint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant _NameHeroPainter oldDelegate) {
    return oldDelegate.color != color;
  }
}
