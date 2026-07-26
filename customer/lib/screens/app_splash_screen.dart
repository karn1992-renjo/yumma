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
      Future<void>.delayed(const Duration(seconds: 5)),
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

        return const _SplashView();
      },
    );
  }
}

class _SplashView extends StatelessWidget {
  const _SplashView();

  static const Color _splashPurple = Color(0xFF5F008C);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _splashPurple,
      body: SafeArea(
        child: Center(
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
                  color: Colors.white.withOpacity(0.82),
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0,
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
    );
  }

  static String get _tagline {
    if (AppConfig.isDriverApp) return 'Delivering orders faster';
    if (AppConfig.isRestaurantApp) return 'Managing orders smoothly';
    return 'Fresh food, fast delivery';
  }
}