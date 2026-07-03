// lib/widgets/customer/restaurant_card.dart
import 'package:flutter/material.dart';
import '../../config/app_config.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

class RestaurantCard extends StatelessWidget {
  final dynamic restaurant;
  final VoidCallback onTap;

  const RestaurantCard({
    Key? key,
    required this.restaurant,
    required this.onTap,
  }) : super(key: key);

  static double _parseDouble(dynamic value, {required double fallback}) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) {
      return double.tryParse(value) ?? fallback;
    }
    return fallback;
  }

  static int _parseInt(dynamic value, {required int fallback}) {
    if (value is int) return value;
    if (value is double) return value.toInt();
    if (value is String) {
      return int.tryParse(value) ?? double.tryParse(value)?.toInt() ?? fallback;
    }
    return fallback;
  }

  static bool _hasVisibleRating(dynamic restaurant) {
    final totalRatings = _parseInt(
      restaurant['total_ratings'] ?? restaurant['review_count'] ?? 0,
      fallback: 0,
    );
    return totalRatings >= 3;
  }

  static String _resolveImageUrl(dynamic item, List<String> keys) {
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

  static String _resolveCuisineText(dynamic restaurant) {
    if (restaurant == null) return '';

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
      final names = namesFrom(restaurant[key]);
      if (names.isNotEmpty) return names.join(', ');
    }

    return '';
  }

  @override
  Widget build(BuildContext context) {
    final name = restaurant['name']?.toString() ?? '';
    final hasVisibleRating = _hasVisibleRating(restaurant);
    final rating = _parseDouble(
      restaurant['rating'] ??
          restaurant['avg_rating'] ??
          restaurant['review_rating'] ??
          restaurant['rating_value'] ??
          restaurant['restaurant_rating'],
      fallback: 0.0,
    );
    final deliveryTime = _parseInt(restaurant['delivery_time'], fallback: 30);
    final minOrderAmount = _parseDouble(restaurant['min_order_amount'], fallback: 99.0);
    final cuisineText = _resolveCuisineText(restaurant);
    final imageUrl = _resolveImageUrl(restaurant, [
      'image_url',
      'banner_url',
      'banner_image',
      'image',
      'photo',
    ]);
    final isOpen = restaurant['is_open'] is bool ? restaurant['is_open'] as bool : restaurant['is_open']?.toString().toLowerCase() != 'false';
    final discount = restaurant['discount']?.toString() ?? '';

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(14),
                  child: imageUrl.isNotEmpty
                      ? Image.network(
                          imageUrl,
                          height: 124,
                          width: 124,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => _imageFallback(),
                        )
                      : _imageFallback(),
                ),
                Positioned.fill(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(14),
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [Colors.transparent, Colors.black.withOpacity(0.58)],
                      ),
                    ),
                  ),
                ),
                if (discount.isNotEmpty)
                  Positioned(
                    left: 8,
                    right: 8,
                    bottom: 8,
                    child: Text(
                      discount.toUpperCase(),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        height: 0.95,
                      ),
                    ),
                  ),
                if (!isOpen)
                  Positioned.fill(
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.62),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      alignment: Alignment.center,
                      child: const Text(
                        'CLOSED',
                        style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 14),
            Expanded(
              child: SizedBox(
                height: 124,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                        color: FoodFlowTheme.ink,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Container(
                          width: 18,
                          height: 18,
                          decoration: const BoxDecoration(color: Color(0xFF48C479), shape: BoxShape.circle),
                          child: const Icon(Icons.star, size: 12, color: Colors.white),
                        ),
                        const SizedBox(width: 5),
                        Text(
                          '${hasVisibleRating ? rating.toStringAsFixed(1) : 'New'} - $deliveryTime mins',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                          color: FoodFlowTheme.ink,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 7),
                    Text(
                      cuisineText.isEmpty ? 'North Indian, Fast Food' : cuisineText,
                      style: const TextStyle(fontSize: 13, color: FoodFlowTheme.muted),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${restaurant['area'] ?? 'Nearby'} - Min ${formatCurrency(context, minOrderAmount)}',
                      style: const TextStyle(fontSize: 12, color: FoodFlowTheme.faint),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const Spacer(),
                    Row(
                      children: [
                        Icon(Icons.local_offer, size: 15, color: FoodFlowTheme.orange),
                        const SizedBox(width: 5),
                        Expanded(
                          child: Text(
                            discount.isNotEmpty ? discount : 'Free delivery on select orders',
                            style: TextStyle(
                              fontSize: 12,
                              color: FoodFlowTheme.orange,
                              fontWeight: FontWeight.w700,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
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
    );
  }

  Widget _imageFallback() {
    return Container(
      height: 124,
      width: 124,
      color: const Color(0xFFF2F2F2),
      child: const Icon(Icons.restaurant, size: 42, color: AppConfig.primaryColor),
    );
  }
}
