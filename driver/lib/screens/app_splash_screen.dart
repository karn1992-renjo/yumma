import 'dart:async';

import 'package:flutter/material.dart';

import '../config/app_config.dart';
import '../models/app_branding.dart';

class AppSplashScreen extends StatefulWidget {
  const AppSplashScreen({
    super.key,
    this.branding,
    required this.startupFuture,
    required this.builder,
  });

  final AppBranding? branding;
  final Future<void> startupFuture;
  final WidgetBuilder builder;

  @override
  State<AppSplashScreen> createState() => _AppSplashScreenState();
}

class _AppSplashScreenState extends State<AppSplashScreen> {
  late final Future<void> _startup;

  @override
  void initState() {
    super.initState();
    _startup = Future.wait<void>([
      widget.startupFuture,
      Future<void>.delayed(const Duration(milliseconds: 1200)),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<void>(
      future: _startup,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.done) {
          return widget.builder(context);
        }

        return _SplashView(branding: widget.branding);
      },
    );
  }
}

class _SplashView extends StatelessWidget {
  const _SplashView({this.branding});

  final AppBranding? branding;

  // Yumma! logo purple (sampled from assets/images/logo.png).
  static const Color _fallbackSplashBackground = Color(0xFF5C0298);

  @override
  Widget build(BuildContext context) {
    final base = _colorFromHex(branding?.driverPrimaryColorHex) ??
        _fallbackSplashBackground;
    final dark = _darken(base, 0.16);

    return Scaffold(
      backgroundColor: base,
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [base, dark],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: 1),
              duration: const Duration(milliseconds: 650),
              curve: Curves.easeOutCubic,
              builder: (context, t, child) => Opacity(
                opacity: t.clamp(0, 1),
                child: Transform.scale(scale: 0.94 + 0.06 * t, child: child),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Image.asset(
                    'assets/images/splash.png',
                    width: 230,
                    height: 110,
                    fit: BoxFit.contain,
                  ),
                  const SizedBox(height: 18),
                  Text(
                    _tagline,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.85),
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 34),
                  const SizedBox(
                    width: 26,
                    height: 26,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.6,
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  static Color _darken(Color c, double amount) {
    final hsl = HSLColor.fromColor(c);
    return hsl
        .withLightness((hsl.lightness - amount).clamp(0.0, 1.0))
        .toColor();
  }

  static String get _tagline {
    if (AppConfig.isDriverApp) return 'Delivering orders faster';
    if (AppConfig.isRestaurantApp) return 'Managing orders smoothly';
    return 'Fresh food, fast delivery';
  }

  static Color? _colorFromHex(String? value) {
    final normalized = value?.trim().replaceFirst('#', '') ?? '';
    if (normalized.length != 6) return null;
    final parsed = int.tryParse(normalized, radix: 16);
    return parsed == null ? null : Color(0xFF000000 | parsed);
  }
}
