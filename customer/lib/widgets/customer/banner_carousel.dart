import 'dart:async';

import 'package:flutter/material.dart';
import '../common/app_cached_image.dart';

import '../../config/app_config.dart';
import '../../theme/foodflow_theme.dart';

class BannerCarousel extends StatefulWidget {
  final List<dynamic> banners;

  const BannerCarousel({
    super.key,
    required this.banners,
  });

  @override
  State<BannerCarousel> createState() => _BannerCarouselState();
}

class _BannerCarouselState extends State<BannerCarousel> {
  late final PageController _pageController;
  Timer? _autoScrollTimer;
  int _currentPage = 0;

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
    _startAutoScrollIfNeeded();
  }

  @override
  void didUpdateWidget(covariant BannerCarousel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.banners.length != widget.banners.length) {
      _currentPage = 0;
      _autoScrollTimer?.cancel();
      _startAutoScrollIfNeeded();
    }
  }

  void _startAutoScrollIfNeeded() {
    if (widget.banners.length <= 1) return;
    _autoScrollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!mounted || !_pageController.hasClients) return;
      final nextPage = (_currentPage + 1) % widget.banners.length;
      _pageController.animateToPage(
        nextPage,
        duration: const Duration(milliseconds: 420),
        curve: Curves.easeInOutCubic,
      );
    });
  }

  @override
  void dispose() {
    _autoScrollTimer?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  String _resolveImageUrl(dynamic item, List<String> keys) {
    if (item is! Map) return '';
    for (final key in keys) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) {
        if (value.startsWith('http')) return value;
        if (value.startsWith('/')) return '${AppConfig.apiBaseUrl}$value';
        return value;
      }
    }
    return '';
  }

  Future<void> _handleBannerTap(BuildContext context, dynamic banner) async {
    if (banner is Map) {
      final redirect = banner['redirect'] is Map
          ? Map<String, dynamic>.from(banner['redirect'] as Map)
          : <String, dynamic>{
              'type': banner['redirect_type'],
              'id': banner['redirect_menu_item_id'] ??
                  banner['redirect_restaurant_id'] ??
                  banner['redirect_category_id'],
            };

      final redirectType = redirect['type']?.toString();
      final redirectId = _parseInt(redirect['id']);
      if (redirectType == 'restaurant' && redirectId != null) {
        Navigator.pushNamed(
          context,
          '/restaurant/detail',
          arguments: {'restaurantId': redirectId},
        );
        return;
      }

      if (redirectType == 'menu_item' && redirectId != null) {
        final restaurantId = _parseInt(redirect['restaurant_id']);
        if (restaurantId != null) {
          Navigator.pushNamed(
            context,
            '/restaurant/detail',
            arguments: {
              'restaurantId': restaurantId,
              'menuItemId': redirectId,
            },
          );
          return;
        }
      }

      if (redirectType == 'category' && redirectId != null) {
        final name = (redirect['name'] ?? banner['title'] ?? '').toString();
        Navigator.pushNamed(
          context,
          '/search',
          arguments: {
            'source': 'category',
            'browseMode': 'category',
            'category': name.isNotEmpty ? name : 'Category',
            'category_id': redirectId,
          },
        );
        return;
      }
    }

    final rawLink =
        banner is Map ? banner['link']?.toString().trim() ?? '' : '';
    if (rawLink.isEmpty) {
      Navigator.pushNamed(context, '/search');
      return;
    }

    final restaurantMatch = RegExp(r'/restaurants/(\d+)').firstMatch(rawLink);
    if (restaurantMatch != null) {
      final restaurantId = int.tryParse(restaurantMatch.group(1) ?? '');
      if (restaurantId != null) {
        Navigator.pushNamed(
          context,
          '/restaurant/detail',
          arguments: {'restaurantId': restaurantId},
        );
        return;
      }
    }

    if (rawLink.startsWith('/search')) {
      Navigator.pushNamed(context, '/search');
      return;
    }

    Navigator.pushNamed(context, '/search', arguments: rawLink);
  }

  int? _parseInt(dynamic value) {
    if (value is int) return value;
    return int.tryParse(value?.toString() ?? '');
  }

  String _bannerEyebrow(Map<String, dynamic> banner) {
    final title = banner['title']?.toString().trim() ?? '';
    if (title.toUpperCase().contains('OFF')) {
      return 'HOT DEAL';
    }
    if (title.isNotEmpty) {
      return title.split(' ').take(2).join(' ').toUpperCase();
    }
    return 'HOT DEAL';
  }

  String _bannerHeadline(Map<String, dynamic> banner) {
    final title = banner['title']?.toString().trim() ?? '';
    if (title.isNotEmpty) return title;
    return '50% OFF';
  }

  String _bannerSubtitle(Map<String, dynamic> banner) {
    final description = banner['description']?.toString().trim() ?? '';
    if (description.isNotEmpty) return description;
    return 'Up to 120';
  }

  bool _isImageOnlyBanner(Map<String, dynamic> banner) {
    final layoutMode = banner['layout_mode']?.toString().trim().toLowerCase();
    return layoutMode == 'full_image' || layoutMode == 'image_only';
  }

  @override
  Widget build(BuildContext context) {
    if (widget.banners.isEmpty) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: SizedBox(
        height: 160,
        child: Stack(
          children: [
            PageView.builder(
              controller: _pageController,
              itemCount: widget.banners.length,
              onPageChanged: (index) {
                if (!mounted) return;
                setState(() => _currentPage = index);
              },
              itemBuilder: (context, index) {
                final banner = widget.banners[index] is Map<String, dynamic>
                    ? widget.banners[index] as Map<String, dynamic>
                    : Map<String, dynamic>.from(widget.banners[index] as Map);
                final imageUrl = _resolveImageUrl(
                  banner,
                  const ['image', 'banner_image', 'photo', 'image_url'],
                );

                return GestureDetector(
                  onTap: () => _handleBannerTap(context, banner),
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(24),
                      gradient: const LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [Color(0xFFFFF2E8), Color(0xFFFFDECA)],
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0x1AFF7A00),
                          blurRadius: 18,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(24),
                      child: _isImageOnlyBanner(banner)
                          ? (imageUrl.isNotEmpty
                              ? AppCachedImage(
                                  imageUrl: imageUrl,
                                  width: double.infinity,
                                  height: double.infinity,
                                  fit: BoxFit.cover,
                                  errorBuilder: (_, __, ___) =>
                                      _fallbackBanner(),
                                )
                              : _fallbackBanner())
                          : Stack(
                              children: [
                                Positioned(
                                  right: -20,
                                  top: -10,
                                  child: Container(
                                    width: 180,
                                    height: 180,
                                    decoration: const BoxDecoration(
                                      shape: BoxShape.circle,
                                      color: Color(0x14FFFFFF),
                                    ),
                                  ),
                                ),
                                Row(
                                  children: [
                                    Expanded(
                                      flex: 11,
                                      child: Padding(
                                        padding: const EdgeInsets.fromLTRB(
                                          22,
                                          18,
                                          12,
                                          18,
                                        ),
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              _bannerEyebrow(banner),
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                              style: const TextStyle(
                                                color: FoodFlowTheme.ink,
                                                fontSize: 14,
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                            const SizedBox(height: 6),
                                            Text(
                                              _bannerHeadline(banner),
                                              maxLines: 2,
                                              overflow: TextOverflow.ellipsis,
                                              style: const TextStyle(
                                                color: Color(0xFFFF6B00),
                                                fontSize: 38,
                                                fontWeight: FontWeight.w800,
                                                height: 0.96,
                                              ),
                                            ),
                                            const SizedBox(height: 4),
                                            Expanded(
                                              child: Align(
                                                alignment: Alignment.topLeft,
                                                child: Text(
                                                  _bannerSubtitle(banner),
                                                  maxLines: 2,
                                                  overflow:
                                                      TextOverflow.ellipsis,
                                                  style: const TextStyle(
                                                    color: FoodFlowTheme.ink,
                                                    fontSize: 22,
                                                    fontWeight: FontWeight.w600,
                                                    height: 1.05,
                                                  ),
                                                ),
                                              ),
                                            ),
                                            Container(
                                              width: 120,
                                              height: 40,
                                              decoration: BoxDecoration(
                                                gradient: const LinearGradient(
                                                  colors: [
                                                    Color(0xFFFF6B00),
                                                    Color(0xFFFF8A23),
                                                  ],
                                                ),
                                                borderRadius:
                                                    BorderRadius.circular(14),
                                              ),
                                              alignment: Alignment.center,
                                              child: const Text(
                                                'ORDER NOW',
                                                style: TextStyle(
                                                  color: Colors.white,
                                                  fontSize: 13,
                                                  fontWeight: FontWeight.w800,
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                    Expanded(
                                      flex: 10,
                                      child: Padding(
                                        padding: const EdgeInsets.only(
                                          right: 6,
                                          top: 8,
                                          bottom: 8,
                                        ),
                                        child: imageUrl.isNotEmpty
                                            ? AppCachedImage(
                                                imageUrl: imageUrl,
                                                fit: BoxFit.contain,
                                                alignment:
                                                    Alignment.bottomCenter,
                                                errorBuilder: (_, __, ___) =>
                                                    _fallbackBanner(),
                                              )
                                            : _fallbackBanner(),
                                      ),
                                    ),
                                  ],
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
                left: 0,
                right: 0,
                bottom: 10,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(
                    widget.banners.length,
                    (index) => AnimatedContainer(
                      duration: const Duration(milliseconds: 220),
                      margin: const EdgeInsets.symmetric(horizontal: 3),
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: _currentPage == index
                            ? const Color(0xFFFF8B24)
                            : const Color(0xFFD9C9BD),
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _fallbackBanner() {
    return const Center(
      child: Icon(
        Icons.lunch_dining_rounded,
        size: 108,
        color: Color(0xFFFFA14C),
      ),
    );
  }
}
