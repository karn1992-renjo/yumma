// lib/screens/splash_screen.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/app_config.dart';
import '../models/app_branding.dart';
import '../providers/auth_provider.dart';
import '../services/app_branding_service.dart';
import '../services/order_alert_startup_permission_service.dart';
import '../theme/foodflow_theme.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  static const Color _launcherBackgroundColor = Color(0xFF550388);

  AppBranding _branding = AppBranding.fallback();
  late final AnimationController _logoController;
  late final Animation<double> _logoScale;
  late final Animation<double> _logoOpacity;

  @override
  void initState() {
    super.initState();
    _logoController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);

    _logoScale = Tween<double>(begin: 0.94, end: 1.04).animate(
      CurvedAnimation(parent: _logoController, curve: Curves.easeInOut),
    );
    _logoOpacity = Tween<double>(begin: 0.86, end: 1).animate(
      CurvedAnimation(parent: _logoController, curve: Curves.easeInOut),
    );

    _loadBranding();
    _navigateToApp();
  }

  @override
  void dispose() {
    _logoController.dispose();
    super.dispose();
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    FoodFlowTheme.applyBrandColors(
      primary: _colorFromHex(
        branding.restaurantPrimaryColorHex,
        AppConfig.primaryColor,
      ),
      secondary: _colorFromHex(
        branding.restaurantSecondaryColorHex,
        AppConfig.secondaryColor,
      ),
    );
    setState(() {
      _branding = branding;
    });
  }

  Color _colorFromHex(String? value, Color fallback) {
    final normalized = value?.trim().replaceFirst('#', '') ?? '';
    if (normalized.length != 6) return fallback;
    final parsed = int.tryParse(normalized, radix: 16);
    return parsed == null ? fallback : Color(0xFF000000 | parsed);
  }

  Future<void> _navigateToApp() async {
    await Future.delayed(const Duration(seconds: 3));

    final prefs = await SharedPreferences.getInstance();
    final onboardingComplete = prefs.getBool('onboarding_complete') ?? false;

    if (!mounted) return;

    if (!onboardingComplete) {
      Navigator.pushReplacementNamed(context, '/onboarding');
      return;
    }

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    await authProvider.loadUser();

    if (!mounted) return;

    if (!authProvider.isAuthenticated || !authProvider.canUseCurrentApp) {
      if (authProvider.isAuthenticated) {
        await authProvider.logout();
      }
      Navigator.pushReplacementNamed(context, '/login');
      return;
    }

    await OrderAlertStartupPermissionService.ensureForOrderAlerts(
      enabled: authProvider.isRestaurantMember,
    );

    if (!mounted) return;
    Navigator.pushReplacementNamed(context, '/restaurant/dashboard');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _launcherBackgroundColor,
      body: Container(
        color: _launcherBackgroundColor,
        alignment: Alignment.center,
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final shortestSide = constraints.biggest.shortestSide;
              final logoSize = (shortestSide * 0.58).clamp(150.0, 360.0);

              return Center(
                child: AnimatedBuilder(
                  animation: _logoController,
                  builder: (context, child) {
                    return Opacity(
                      opacity: _logoOpacity.value,
                      child: Transform.scale(
                        scale: _logoScale.value,
                        child: child,
                      ),
                    );
                  },
                  child: _SplashLogo(
                    logoUrl: _branding.preferredLogoUrl,
                    color: Colors.white,
                    size: logoSize.toDouble(),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _SplashLogo extends StatelessWidget {
  static const String _launcherIconAsset =
      'android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png';

  final String logoUrl;
  final Color color;
  final double size;

  const _SplashLogo({
    required this.logoUrl,
    required this.color,
    required this.size,
  });

  @override
  Widget build(BuildContext context) {
    if (logoUrl.isNotEmpty) {
      return Image.network(
        logoUrl,
        width: size,
        height: size,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => _fallback(),
      );
    }

    return _fallback();
  }

  Widget _fallback() {
    return Image.asset(
      _launcherIconAsset,
      width: size,
      height: size,
      fit: BoxFit.contain,
      errorBuilder: (_, __, ___) => _iconFallback(),
    );
  }

  Widget _iconFallback() {
    return Icon(
      Icons.restaurant_rounded,
      color: color,
      size: size * 0.62,
    );
  }
}
