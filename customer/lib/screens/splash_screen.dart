// lib/screens/splash_screen.dart
import 'package:flutter/material.dart';
import '../widgets/common/app_cached_image.dart';
import 'package:provider/provider.dart';
import '../models/app_branding.dart';
import '../providers/auth_provider.dart';
import '../services/app_branding_service.dart';
import '../theme/brand_palette.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  AppBranding _branding = AppBranding.fallback();
  late final AnimationController _logoController;
  late final Animation<double> _logoScale;
  late final Animation<double> _logoOpacity;

  BrandPalette get _palette => BrandPalette.fromBranding(_branding);

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
    _startSplash();
  }

  @override
  void dispose() {
    _logoController.dispose();
    super.dispose();
  }

  Future<void> _loadBranding({bool forceRefresh = false}) async {
    final branding = await AppBrandingService.instance.loadBranding(
      forceRefresh: forceRefresh,
    );
    if (!mounted) return;
    setState(() {
      _branding = branding;
    });
  }

  Future<void> _startSplash() async {
    final startedAt = DateTime.now();
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    await Future.wait([
      _loadBranding(forceRefresh: false),
      authProvider.loadUser(forceRefresh: false),
    ]);

    final elapsed = DateTime.now().difference(startedAt);
    final remaining = const Duration(seconds: 3) - elapsed;
    if (remaining > Duration.zero) {
      await Future.delayed(remaining);
    }

    if (!mounted) return;

    if (!authProvider.isAuthenticated || !authProvider.canUseCurrentApp) {
      if (authProvider.isAuthenticated) {
        await authProvider.logout();
      }
      Navigator.pushReplacementNamed(context, '/login');
      return;
    }

    Navigator.pushReplacementNamed(context, '/home');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        color: _palette.primary,
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
      return AppCachedImage(
        imageUrl: logoUrl,
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
