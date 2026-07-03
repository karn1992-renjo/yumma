import 'package:flutter/material.dart';
import 'common/app_cached_image.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../providers/cart_provider.dart';
import '../theme/foodflow_theme.dart';
import '../utils/currency_utils.dart';

class RestaurantCardAlt extends StatelessWidget {
  final dynamic restaurant;
  final VoidCallback onTap;
  final bool isSaved;
  final VoidCallback? onSaveToggle;

  const RestaurantCardAlt({
    super.key,
    required this.restaurant,
    required this.onTap,
    this.isSaved = false,
    this.onSaveToggle,
  });

  static double _parseDouble(dynamic value, {double fallback = 0}) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? fallback;
    return fallback;
  }

  static int _parseInt(dynamic value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is double) return value.toInt();
    if (value is String) {
      return int.tryParse(value) ?? double.tryParse(value)?.toInt() ?? fallback;
    }
    return fallback;
  }

  static bool _parseBool(dynamic value, {bool fallback = false}) {
    if (value is bool) return value;
    if (value is int) return value != 0;
    if (value is String) {
      final normalized = value.trim().toLowerCase();
      return normalized == 'true' ||
          normalized == '1' ||
          normalized == 'yes' ||
          normalized == 'y';
    }
    return fallback;
  }

  static String _resolveImageUrl(dynamic item, List<String> keys) {
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

  static String _resolveCuisineText(dynamic restaurant) {
    List<String> namesFrom(dynamic value) {
      if (value is String && value.trim().isNotEmpty) {
        return value
            .split(',')
            .map((item) => item.trim())
            .where((item) => item.isNotEmpty && int.tryParse(item) == null)
            .take(3)
            .toList();
      }
      if (value is List) {
        return value
            .map((item) {
              if (item is Map) {
                return (item['name'] ??
                        item['title'] ??
                        item['cuisine_name'] ??
                        '')
                    .toString()
                    .trim();
              }
              final text = item?.toString().trim() ?? '';
              return int.tryParse(text) == null ? text : '';
            })
            .where((item) => item.isNotEmpty)
            .take(3)
            .toList();
      }
      return const <String>[];
    }

    for (final key in const [
      'cuisine_text',
      'cuisine_names',
      'cuisines',
      'cuisine',
    ]) {
      final values = namesFrom(restaurant[key]);
      if (values.isNotEmpty) return values.join(', ');
    }
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final name = (restaurant['name'] ?? 'Restaurant').toString();
    final imageUrl = _resolveImageUrl(restaurant, const [
      'logo_image',
      'logo',
      'banner_url',
      'banner_image',
      'image_url',
      'image',
      'photo',
    ]);
    final deliveryTime = _parseInt(
      restaurant['delivery_time'] ?? restaurant['deliveryTime'],
      fallback: 30,
    );
    double rating = 0;
    final rVal = restaurant['rating'] ??
        restaurant['avg_rating'] ??
        restaurant['review_rating'] ??
        restaurant['rating_value'] ??
        restaurant['avgRating'] ??
        restaurant['ratingValue'];
    if (rVal != null) {
      if (rVal is num) {
        rating = rVal.toDouble();
      } else if (rVal is String) {
        rating = double.tryParse(rVal.trim()) ?? 0;
      }
    }
    final isOpen = restaurant['is_open'] == null
        ? true
        : _parseBool(restaurant['is_open'], fallback: true);
    final deliveryFeeText =
        restaurant['delivery_fee'] ?? restaurant['deliveryFee'];
    final fee = _parseDouble(deliveryFeeText, fallback: 0);
    final cuisines = _resolveCuisineText(restaurant);

    return Consumer<CartProvider>(
      builder: (context, cart, _) {
        return Opacity(
          opacity: isOpen ? 1 : 0.78,
          child: InkWell(
            onTap: isOpen ? onTap : null,
            borderRadius: BorderRadius.circular(16),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE9EDF3)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.05),
                      blurRadius: 12,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                clipBehavior: Clip.antiAlias,
                child: Row(
                  children: [
                    // Image-first compact alt layout
                    SizedBox(
                      width: 140,
                      height: double.infinity,
                      child: Stack(
                        children: [
                          Positioned.fill(
                            child: imageUrl.isNotEmpty
                                ? AppCachedImage(imageUrl: imageUrl,
                                    fit: BoxFit.cover,
                                    errorBuilder: (_, __, ___) =>
                                        _imageFallback())
                                : _imageFallback(),
                          ),
                          Positioned(
                            right: 8,
                            top: 8,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 5),
                              decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.9),
                                  borderRadius: BorderRadius.circular(10)),
                              child: Text(
                                  '${deliveryTime - 5}-${deliveryTime}m',
                                  style: const TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700)),
                            ),
                          ),
                          Positioned(
                            left: 8,
                            bottom: 8,
                            child: _statusBadge(isOpen),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                    child: Text(name,
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                            fontSize: 15,
                                            fontWeight: FontWeight.w800,
                                            color: FoodFlowTheme.ink))),
                                const SizedBox(width: 8),
                                if (rating > 0)
                                  FoodFlowTheme.ratingBadge(rating,
                                      compact: true),
                                if (rating <= 0) _newBadge(),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(cuisines,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                    color: FoodFlowTheme.muted, fontSize: 12)),
                            const Spacer(),
                            if (!isOpen) ...[
                              const Text(
                                'Closed - ordering unavailable',
                                style: TextStyle(
                                  color: Color(0xFFD14343),
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 6),
                            ],
                            Row(
                              children: [
                                Text(
                                  restaurant['price_for_two'] != null
                                      ? formatCurrency(
                                          context, restaurant['price_for_two'])
                                      : '',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: FoodFlowTheme.ink),
                                ),
                                const SizedBox(width: 8),
                                const Text('·',
                                    style:
                                        TextStyle(color: FoodFlowTheme.muted)),
                                const SizedBox(width: 8),
                                Text(
                                    '${(restaurant['distance'] ?? restaurant['distance_km'] ?? '').toString()} km',
                                    style: const TextStyle(
                                        color: FoodFlowTheme.muted)),
                                const Spacer(),
                                Text(
                                    fee <= 0
                                        ? 'Free'
                                        : '${getCurrencySymbol(context)}${fee.toStringAsFixed(fee == fee.roundToDouble() ? 0 : 2)}',
                                    style: const TextStyle(
                                        color: FoodFlowTheme.muted,
                                        fontWeight: FontWeight.w700)),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _imageFallback() {
    return Container(
      color: const Color(0xFFFFF3E8),
      child: const Center(
        child: Icon(
          Icons.restaurant_rounded,
          size: 42,
          color: FoodFlowTheme.orange,
        ),
      ),
    );
  }

  Widget _newBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFF0A9443),
        borderRadius: BorderRadius.circular(999),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.star_rounded, color: Colors.white, size: 12),
          SizedBox(width: 4),
          Text(
            'New',
            style: TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _statusBadge(bool isOpen) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: isOpen ? const Color(0xFF0A9443) : const Color(0xFFD14343),
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.18),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Text(
        isOpen ? 'Open' : 'Closed',
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _MiniViewCartButton extends StatelessWidget {
  const _MiniViewCartButton();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: FoodFlowTheme.orange,
        borderRadius: BorderRadius.circular(10),
        boxShadow: [
          BoxShadow(
            color: FoodFlowTheme.orange.withOpacity(0.15),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: const Text(
        'View cart',
        style: TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
