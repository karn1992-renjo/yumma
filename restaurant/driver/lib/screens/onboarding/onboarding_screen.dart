// lib/screens/onboarding/onboarding_screen.dart
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:smooth_page_indicator/smooth_page_indicator.dart';
import '../auth/modern_login_screen.dart';

class OnboardingModel {
  final String title;
  final String description;
  final IconData icon;
  final Color color;

  OnboardingModel({
    required this.title,
    required this.description,
    required this.icon,
    required this.color,
  });
}

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({Key? key}) : super(key: key);

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  late PageController _pageController;
  int _currentIndex = 0;

  final List<OnboardingModel> screens = [
    OnboardingModel(
      title: 'Earn Money Fast',
      description: 'Deliver orders and earn money on your own schedule, with flexible working hours',
      icon: Icons.monetization_on,
      color: Color(0xFF667EEA),
    ),
    OnboardingModel(
      title: 'Real-time Orders',
      description: 'Receive orders in real-time and complete them efficiently with detailed navigation',
      icon: Icons.receipt_long,
      color: Color(0xFF764BA2),
    ),
    OnboardingModel(
      title: 'Track Earnings',
      description: 'Keep track of your daily earnings, tips, and performance metrics',
      icon: Icons.trending_up,
      color: Color(0xFFD71E64),
    ),
    OnboardingModel(
      title: 'Safe & Secure',
      description: 'Secure payments, real-time tracking, and 24/7 support for your peace of mind',
      icon: Icons.security,
      color: Color(0xFFFF6B35),
    ),
  ];

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _completeOnboarding() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('onboarding_complete', true);
    
    if (mounted) {
      Navigator.of(context).pushReplacementNamed('/login');
    }
  }

  void _skipOnboarding() {
    _completeOnboarding();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          PageView.builder(
            controller: _pageController,
            onPageChanged: (index) {
              setState(() => _currentIndex = index);
            },
            itemCount: screens.length,
            itemBuilder: (context, index) {
              return OnboardingPage(
                model: screens[index],
                index: index,
              );
            },
          ),
          // Skip Button
          Positioned(
            top: 40,
            right: 20,
            child: SafeArea(
              child: TextButton(
                onPressed: _skipOnboarding,
                child: Text(
                  'Skip',
                  style: GoogleFonts.poppins(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF667EEA),
                  ),
                ),
              ),
            ),
          ),
          // Navigation Controls
          Positioned(
            bottom: 40,
            left: 20,
            right: 20,
            child: Column(
              children: [
                // Page Indicator
                SmoothPageIndicator(
                  controller: _pageController,
                  count: screens.length,
                  effect: WormEffect(
                    dotHeight: 12,
                    dotWidth: 12,
                    activeDotColor: Color(0xFF667EEA),
                    dotColor: Color(0xFF667EEA).withOpacity(0.2),
                  ),
                ),
                const SizedBox(height: 32),
                // Navigation Buttons
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    // Previous Button
                    _currentIndex > 0
                        ? GestureDetector(
                            onTap: () {
                              _pageController.previousPage(
                                duration: const Duration(milliseconds: 500),
                                curve: Curves.easeInOut,
                              );
                            },
                            child: Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                border: Border.all(
                                  color: Color(0xFF667EEA),
                                  width: 2,
                                ),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Icon(
                                Icons.arrow_back,
                                color: Color(0xFF667EEA),
                                size: 24,
                              ),
                            ),
                          )
                        : SizedBox(width: 48),
                    // Next/Get Started Button
                    GestureDetector(
                      onTap: () {
                        if (_currentIndex == screens.length - 1) {
                          _completeOnboarding();
                        } else {
                          _pageController.nextPage(
                            duration: const Duration(milliseconds: 500),
                            curve: Curves.easeInOut,
                          );
                        }
                      },
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 32,
                          vertical: 12,
                        ),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [
                              Color(0xFF667EEA),
                              Color(0xFF764BA2),
                            ],
                          ),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              _currentIndex == screens.length - 1
                                  ? 'Get Started'
                                  : 'Next',
                              style: GoogleFonts.poppins(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                                color: Colors.white,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Icon(
                              Icons.arrow_forward,
                              color: Colors.white,
                              size: 20,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class OnboardingPage extends StatelessWidget {
  final OnboardingModel model;
  final int index;

  const OnboardingPage({
    Key? key,
    required this.model,
    required this.index,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            model.color.withOpacity(0.1),
            model.color.withOpacity(0.05),
          ],
        ),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Animated Icon
          TweenAnimationBuilder(
            tween: Tween<double>(begin: 0, end: 1),
            duration: Duration(milliseconds: 800 + (index * 100)),
            builder: (context, value, child) {
              return Transform.scale(
                scale: value,
                child: child,
              );
            },
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  colors: [
                    model.color,
                    model.color.withOpacity(0.7),
                  ],
                ),
                boxShadow: [
                  BoxShadow(
                    color: model.color.withOpacity(0.3),
                    blurRadius: 20,
                    spreadRadius: 5,
                  ),
                ],
              ),
              child: Icon(
                model.icon,
                color: Colors.white,
                size: 60,
              ),
            ),
          ),
          const SizedBox(height: 48),
          // Title
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 32),
            child: Text(
              model.title,
              textAlign: TextAlign.center,
              style: GoogleFonts.poppins(
                fontSize: 28,
                fontWeight: FontWeight.bold,
                color: Colors.black87,
              ),
            ),
          ),
          const SizedBox(height: 16),
          // Description
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 32),
            child: Text(
              model.description,
              textAlign: TextAlign.center,
              style: GoogleFonts.poppins(
                fontSize: 16,
                fontWeight: FontWeight.w400,
                color: Colors.grey[600],
                height: 1.5,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
