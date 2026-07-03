import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../models/app_branding.dart';
import '../../services/app_branding_service.dart';
import '../../theme/brand_palette.dart';
import '../../widgets/auth/auth_lottie_accent.dart';
import '../../widgets/auth/brand_mark.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final PageController _pageController = PageController();
  AppBranding _branding = AppBranding.fallback();
  int _currentIndex = 0;

  BrandPalette get _palette => BrandPalette.fromBranding(_branding);

  List<AppOnboardingSlide> get _slides => _branding.resolvedOnboardingSlides;

  @override
  void initState() {
    super.initState();
    _loadBranding();
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() {
      _branding = branding;
    });
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _completeOnboarding() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('onboarding_complete', true);

    if (!mounted) return;
    Navigator.pushReplacementNamed(context, '/login');
  }

  void _next() {
    if (_currentIndex == _slides.length) {
      _completeOnboarding();
      return;
    }

    _pageController.nextPage(
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(28, 20, 28, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  BrandMark(branding: _branding, size: 30),
                  const Spacer(),
                  if (_currentIndex != 0) const AuthLottieAccent(height: 94),
                ],
              ),
              const SizedBox(height: 24),
              Expanded(
                child: PageView.builder(
                  controller: _pageController,
                  itemCount: _slides.length + 1,
                  onPageChanged: (index) {
                    setState(() {
                      _currentIndex = index;
                    });
                  },
                  itemBuilder: (context, index) {
                    if (index == 0) {
                      return _IntroPage(branding: _branding);
                    }
                    return _SlidePage(
                      branding: _branding,
                      slide: _slides[index - 1],
                    );
                  },
                ),
              ),
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(
                  _slides.length + 1,
                  (index) => AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    width: _currentIndex == index ? 22 : 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: _currentIndex == index
                          ? _palette.primary
                          : const Color(0xFFD5C5BC),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: TextButton(
                      onPressed: _completeOnboarding,
                      child: const Text(
                        'Skip',
                        style: TextStyle(
                          color: Color(0xFF7D736E),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: _next,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _palette.primary,
                        foregroundColor: Colors.white,
                        minimumSize: const Size.fromHeight(54),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                      child: Text(
                        _currentIndex == _slides.length ? 'Get Started' : 'Next',
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _IntroPage extends StatelessWidget {
  final AppBranding branding;

  const _IntroPage({required this.branding});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: BrandPalette.fromBranding(branding).primary,
      padding: const EdgeInsets.fromLTRB(24, 28, 24, 28),
      child: Column(
        children: [
          const Spacer(),
          BrandMark(
            branding: branding,
            size: 72,
            fallbackBackground: Colors.white,
            fallbackForeground: BrandPalette.fromBranding(branding).primary,
          ),
          const SizedBox(height: 18),
          const AuthLottieAccent(height: 92),
          const SizedBox(height: 14),
          Text(
            branding.resolvedOnboardingIntroTitle.toUpperCase(),
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 34,
              fontWeight: FontWeight.w900,
              letterSpacing: 1.6,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            branding.resolvedOnboardingIntroSubtitle,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white.withOpacity(0.92),
              fontSize: 14,
              fontWeight: FontWeight.w600,
              height: 1.45,
            ),
          ),
          const Spacer(),
        ],
      ),
    );
  }
}

class _SlidePage extends StatelessWidget {
  final AppBranding branding;
  final AppOnboardingSlide slide;

  const _SlidePage({
    required this.branding,
    required this.slide,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          SizedBox(
            height: 310,
            width: double.infinity,
            child: Stack(
              fit: StackFit.expand,
              children: [
                AppCachedImage(
                  imageUrl: slide.imageUrl,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => Container(
                    decoration: const BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [Color(0xFF3A2A28), Color(0xFF161213)],
                      ),
                    ),
                  ),
                ),
                Positioned(
                  left: 16,
                  top: 16,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.9),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 8,
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          BrandMark(branding: branding, size: 24),
                          const SizedBox(width: 8),
                          Text(
                            branding.displayName,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF2A2626),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 28),
          Text(
            slide.title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 22,
              height: 1.3,
              fontWeight: FontWeight.w700,
              color: Color(0xFF2C2727),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            slide.description,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 13,
              height: 1.55,
              color: Color(0xFF8A817C),
            ),
          ),
        ],
      ),
    );
  }
}
