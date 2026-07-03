import 'package:flutter/material.dart';
import 'common/app_cached_image.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../providers/cart_provider.dart';
import '../theme/foodflow_theme.dart';
import '../utils/currency_utils.dart';

class FoodFlowRestaurantCard extends StatelessWidget {
  final dynamic restaurant;
  final VoidCallback onTap;
  final bool isSaved;
  final VoidCallback? onSaveToggle;

  const FoodFlowRestaurantCard({
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

    return Consumer<CartProvider>(
      builder: (context, cart, _) {
        // Image-first card layout with floating badges and reduced text
        return Opacity(
          opacity: isOpen ? 1 : 0.78,
          child: InkWell(
            onTap: isOpen ? onTap : null,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              height: 140,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                      color: Colors.black.withOpacity(0.03),
                      blurRadius: 8,
                      offset: const Offset(0, 4)),
                ],
              ),
              clipBehavior: Clip.antiAlias,
              child: Row(
                children: [
                  // Left: large image with overlays
                  SizedBox(
                    width: 150,
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
                        // Offer / Sponsored overlay
                        if ((restaurant['offer'] ??
                                restaurant['has_offer'] ??
                                false) !=
                            false)
                          Positioned(
                            left: 8,
                            top: 8,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: const Color(0xFF4B76E5),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Text(
                                (restaurant['offer_text'] ?? 'OFFER')
                                    .toString(),
                                style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w800,
                                    fontSize: 11),
                              ),
                            ),
                          ),
                        // Delivery time badge
                        Positioned(
                          right: 8,
                          top: 8,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 5),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.9),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Text(
                              '${deliveryTime - 5}-${deliveryTime}m',
                              style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.black87),
                            ),
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
                  // Right: details
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Text(name,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                        fontSize: 16,
                                        fontWeight: FontWeight.w800,
                                        color: FoodFlowTheme.ink)),
                              ),
                              const SizedBox(width: 8),
                              if (rating > 0)
                                FoodFlowTheme.ratingBadge(rating,
                                    compact: true),
                              if (rating <= 0) _newBadge(),
                            ],
                          ),
                          const SizedBox(height: 8),
                          // Cuisine tags
                          Wrap(
                            spacing: 6,
                            runSpacing: 6,
                            children: _cuisineNames(restaurant)
                                .take(3)
                                .map<Widget>((c) => _chipFor(c))
                                .toList(),
                          ),
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
                                    : (restaurant['avg_cost_for_two'] != null
                                        ? formatCurrency(context,
                                            restaurant['avg_cost_for_two'])
                                        : ''),
                                style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                    color: FoodFlowTheme.ink),
                              ),
                              const SizedBox(width: 8),
                              if ((restaurant['distance'] ??
                                      restaurant['distance_km']) !=
                                  null) ...[
                                const Text('·',
                                    style:
                                        TextStyle(color: FoodFlowTheme.muted)),
                                const SizedBox(width: 6),
                                Text(
                                    '${(restaurant['distance'] ?? restaurant['distance_km']).toString()} km',
                                    style: const TextStyle(
                                        color: FoodFlowTheme.muted,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w600)),
                              ],
                              const Spacer(),
                              Text(
                                fee <= 0
                                    ? 'Free'
                                    : '${getCurrencySymbol(context)}${fee.toStringAsFixed(fee == fee.roundToDouble() ? 0 : 2)}',
                                style: const TextStyle(
                                    color: FoodFlowTheme.muted,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600),
                              ),
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
        );
      },
    );
  }

  Widget _chipFor(String label) {
    final text = label.trim();
    if (text.isEmpty) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F9FB),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(text,
          style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: FoodFlowTheme.ink)),
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

  List<String> _cuisineNames(dynamic restaurant) {
    List<String> namesFrom(dynamic value) {
      if (value is String && value.trim().isNotEmpty) {
        return value
            .split(',')
            .map((item) => item.trim())
            .where((item) => item.isNotEmpty && int.tryParse(item) == null)
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
      if (values.isNotEmpty) return values;
    }
    return const [];
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
