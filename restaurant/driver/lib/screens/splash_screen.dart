// lib/screens/splash_screen.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
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

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    FoodFlowTheme.applyBrandColors(
      primary: _colorFromHex(
        branding.driverPrimaryColorHex,
        AppConfig.primaryColor,
      ),
      secondary: _colorFromHex(
        branding.driverSecondaryColorHex,
        AppConfig.secondaryColor,
      ),
    );
    setState(() => _branding = branding);
  }

  Color _colorFromHex(String? value, Color fallback) {
    final normalized = value?.trim().replaceFirst('#', '') ?? '';
    if (normalized.length != 6) return fallback;
    final parsed = int.tryParse(normalized, radix: 16);
    return parsed == null ? fallback : Color(0xFF000000 | parsed);
  }

  @override
  void dispose() {
    _logoController.dispose();
    super.dispose();
  }

  Future<void> _navigateToApp() async {
    await Future.delayed(const Duration(seconds: 3));

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
      enabled: authProvider.isDriver,
    );

    if (!mounted) return;

    if (authProvider.isCustomer) {
      Navigator.pushReplacementNamed(context, '/customer/home');
    } else if (authProvider.isRestaurantOwner) {
      Navigator.pushReplacementNamed(context, '/restaurant/dashboard');
    } else if (authProvider.isDriver) {
      Navigator.pushReplacementNamed(context, '/driver/dashboard');
    }
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
                  child: _SplashLogo(size: logoSize.toDouble()),
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

  final double size;

  const _SplashLogo({
    required this.size,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.18),
        borderRadius: BorderRadius.circular(size * 0.26),
      ),
      child: Image.asset(
        _launcherIconAsset,
        width: size * 0.72,
        height: size * 0.72,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => Icon(
          Icons.delivery_dining_rounded,
          size: size * 0.52,
          color: Colors.white,
        ),
      ),
    );
  }
}
