// lib/widgets/customer/banner_carousel.dart
import 'package:flutter/material.dart';
import '../../config/app_config.dart';
import '../../theme/foodflow_theme.dart';

class BannerCarousel extends StatefulWidget {
  final List<dynamic> banners;

  const BannerCarousel({
    Key? key,
    required this.banners,
  }) : super(key: key);

  @override
  State<BannerCarousel> createState() => _BannerCarouselState();
}

class _BannerCarouselState extends State<BannerCarousel> {
  final PageController _pageController = PageController();
  int _currentPage = 0;

  @override
  void initState() {
    super.initState();
    // Auto-scroll timer
    if (widget.banners.length > 1) {
      Future.delayed(const Duration(seconds: 3), _startAutoScroll);
    }
  }

  void _startAutoScroll() {
    Future.doWhile(() async {
      await Future.delayed(const Duration(seconds: 5));
      if (!mounted) return false;
      if (_pageController.hasClients && widget.banners.length > 1) {
        int nextPage = _currentPage + 1;
        if (nextPage >= widget.banners.length) {
          nextPage = 0;
        }
        _pageController.animateToPage(
          nextPage,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeInOut,
        );
      }
      return mounted;
    });
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  String _resolveImageUrl(dynamic item, List<String> keys) {
    if (item == null) return '';
    for (final key in keys) {
      final value = item[key];
      if (value is String && value.isNotEmpty) {
        if (value.startsWith('http')) return value;
        if (value.startsWith('/')) return '${AppConfig.apiBaseUrl}$value';
        return value;
      }
    }
    return '';
  }

  @override
  Widget build(BuildContext context) {
    if (widget.banners.isEmpty) {
      return const SizedBox.shrink();
    }

    return SizedBox(
      height: 174,
      child: Stack(
        children: [
          PageView.builder(
            controller: _pageController,
            onPageChanged: (page) {
              setState(() {
                _currentPage = page;
              });
            },
            itemCount: widget.banners.length,
            itemBuilder: (context, index) {
              final banner = widget.banners[index];
              final imageUrl = _resolveImageUrl(banner, [
                'image_url',
                'banner_url',
                'banner_image',
                'image',
              ]);

              return Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                child: DecoratedBox(
                  decoration: FoodFlowTheme.surface(radius: 14),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        imageUrl.isNotEmpty
                            ? Image.network(
                                imageUrl,
                                width: double.infinity,
                                height: 166,
                                fit: BoxFit.cover,
                                errorBuilder: (context, error, stackTrace) {
                                  return _fallbackBanner();
                                },
                              )
                            : _fallbackBanner(),
                        DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.bottomCenter,
                              end: Alignment.topCenter,
                              colors: [
                                Colors.black.withOpacity(0.28),
                                Colors.transparent,
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
          if (widget.banners.length > 1)
            Positioned(
              bottom: 8,
              left: 0,
              right: 0,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(
                  widget.banners.length,
                  (index) => AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    width: _currentPage == index ? 20 : 6,
                    height: 4,
                    decoration: BoxDecoration(
                      color: _currentPage == index
                          ? Colors.white
                          : Colors.white.withOpacity(0.55),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _fallbackBanner() {
    return Container(
      decoration: FoodFlowTheme.orangeBand(radius: 0),
      child: const Center(
        child: Icon(Icons.local_offer, size: 52, color: Colors.white),
      ),
    );
  }
}
