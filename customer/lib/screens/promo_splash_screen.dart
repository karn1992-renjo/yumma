// lib/screens/promo_splash_screen.dart
import 'dart:async';

import 'package:flutter/material.dart';

import '../models/splash_campaign.dart';
import '../theme/brand_palette.dart';
import '../widgets/common/app_cached_image.dart';

/// Full-screen, remotely-configured promotional splash. Shown once per cold
/// start, right after the native/Flutter loading splash, before the user
/// reaches Home. [onFinished] is called exactly once, either when the
/// configured duration elapses, the user taps Skip, or the user taps the
/// artwork itself (with [tapped] = true so the caller can run the campaign's
/// tap action before continuing).
class PromoSplashScreen extends StatefulWidget {
  const PromoSplashScreen({
    super.key,
    required this.campaign,
    required this.onFinished,
  });

  final SplashCampaign campaign;
  final void Function({required bool tapped}) onFinished;

  @override
  State<PromoSplashScreen> createState() => _PromoSplashScreenState();
}

class _PromoSplashScreenState extends State<PromoSplashScreen>
    with SingleTickerProviderStateMixin {
  Timer? _dismissTimer;
  bool _finished = false;
  late final AnimationController _fadeController;

  @override
  void initState() {
    super.initState();
    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 260),
    )..forward();

    final seconds = widget.campaign.displayDurationSeconds.clamp(2, 15);
    _dismissTimer = Timer(Duration(seconds: seconds), () => _finish(false));
  }

  @override
  void dispose() {
    _dismissTimer?.cancel();
    _fadeController.dispose();
    super.dispose();
  }

  void _finish(bool tapped) {
    if (_finished) return;
    _finished = true;
    _dismissTimer?.cancel();
    widget.onFinished(tapped: tapped);
  }

  @override
  Widget build(BuildContext context) {
    final campaign = widget.campaign;
    final background =
        colorFromHex(campaign.backgroundColorHex) ?? const Color(0xFF5C0298);
    final hasText =
        (campaign.headline?.trim().isNotEmpty ?? false) ||
            (campaign.subtitle?.trim().isNotEmpty ?? false);

    return FadeTransition(
      opacity: _fadeController,
      child: Scaffold(
        backgroundColor: background,
        body: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: campaign.tapAction == 'none' ? null : () => _finish(true),
          child: Stack(
            fit: StackFit.expand,
            children: [
              AppCachedImage(
                imageUrl: campaign.imageUrl,
                fit: BoxFit.cover,
                fadeIn: true,
                errorWidget: const SizedBox.shrink(),
              ),
              if (hasText)
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: Container(
                    padding: const EdgeInsets.fromLTRB(24, 40, 24, 48),
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [Colors.transparent, Color(0xCC000000)],
                      ),
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        if (campaign.headline?.trim().isNotEmpty ?? false)
                          Text(
                            campaign.headline!.trim(),
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 24,
                              fontWeight: FontWeight.w900,
                              height: 1.2,
                              shadows: [
                                Shadow(
                                  color: Colors.black45,
                                  blurRadius: 10,
                                ),
                              ],
                            ),
                          ),
                        if (campaign.subtitle?.trim().isNotEmpty ?? false) ...[
                          const SizedBox(height: 8),
                          Text(
                            campaign.subtitle!.trim(),
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: Colors.white70,
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              shadows: [
                                Shadow(
                                  color: Colors.black38,
                                  blurRadius: 8,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              if (campaign.logoUrl?.trim().isNotEmpty ?? false)
                Positioned(
                  top: 0,
                  left: 0,
                  right: 0,
                  child: SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 20),
                      child: Center(
                        child: AppCachedImage(
                          imageUrl: campaign.logoUrl!,
                          width: 56,
                          height: 56,
                          fit: BoxFit.contain,
                          errorWidget: const SizedBox(width: 56, height: 56),
                        ),
                      ),
                    ),
                  ),
                ),
              Positioned(
                top: 0,
                right: 0,
                child: SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 12, right: 12),
                    child: Material(
                      color: Colors.black26,
                      shape: const StadiumBorder(),
                      child: InkWell(
                        customBorder: const StadiumBorder(),
                        onTap: () => _finish(false),
                        child: const Padding(
                          padding: EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 8,
                          ),
                          child: Text(
                            'Skip',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
