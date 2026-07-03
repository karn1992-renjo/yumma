import 'package:flutter/material.dart';
import '../common/app_cached_image.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../config/app_config.dart';
import '../../providers/cart_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

class RestaurantCard extends StatelessWidget {
  final dynamic restaurant;
  final VoidCallback onTap;
  final bool isSaved;
  final VoidCallback? onSaveToggle;

  const RestaurantCard({
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

  static String _distanceText(dynamic restaurant) {
    final distance = _parseDouble(restaurant['distance'], fallback: -1);
    if (distance < 0) return '';
    if (distance < 1) return '${(distance * 1000).round()} m';
    return '${distance.toStringAsFixed(distance >= 10 ? 0 : 1)} km';
  }

  static String _deliveryFeeText(BuildContext context, dynamic restaurant) {
    final rawFee = restaurant['delivery_fee'] ?? restaurant['deliveryFee'];
    if (rawFee == null) return '';
    final fee = _parseDouble(rawFee, fallback: 0);
    if (fee <= 0) return 'Free delivery';
    return '${getCurrencySymbol(context)}${fee.toStringAsFixed(fee == fee.roundToDouble() ? 0 : 2)} delivery';
  }

  static String _offerText(dynamic restaurant) {
    final discount = restaurant['discount']?.toString().trim() ?? '';
    if (discount.isNotEmpty) return discount;
    final offer = restaurant['offer']?.toString().trim() ?? '';
    if (offer.isNotEmpty) return offer;
    final promos = restaurant['active_promos'];
    if (promos is List && promos.isNotEmpty) {
      final first = promos.first;
      if (first is Map<String, dynamic>) {
        final text = first['title']?.toString().trim() ?? '';
        if (text.isNotEmpty) return text;
        final value = first['discount_value']?.toString().trim() ?? '';
        final type = first['discount_type']?.toString().trim() ?? 'percentage';
        if (value.isNotEmpty) {
          return type == 'percentage' ? '$value% OFF' : '$value OFF';
        }
      }
    }
    return '';
  }

  static String _closedMessage(dynamic restaurant) {
    for (final key in const [
      'next_opening_label',
      'next_opening_text',
      'nextOpenLabel',
    ]) {
      final value = restaurant[key]?.toString().trim();
      if (value != null && value.isNotEmpty) return value;
    }
    return 'Closed - ordering unavailable';
  }

  static Map<String, dynamic>? _menuAnalysis(dynamic restaurant) {
    final value = restaurant['_menu_analysis'];
    if (value is Map<String, dynamic>) return value;
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }
    return null;
  }

  static List<String> _signalTags(BuildContext context, dynamic restaurant) {
    final tags = <String>[];
    final analysis = _menuAnalysis(restaurant);
    final isPureVeg = _parseBool(
      restaurant['is_pure_veg'] ??
          restaurant['pure_veg'] ??
          restaurant['is_veg'] ??
          analysis?['is_pure_veg_menu'],
    );
    if (isPureVeg) {
      tags.add('Pure Veg');
    }

    if (analysis != null) {
      if (_parseBool(analysis['has_best_seller'])) {
        tags.add('Best seller');
      } else if (_parseBool(analysis['has_highly_ordered'])) {
        tags.add('Highly ordered');
      }

      final minPrice =
          _parseDouble(analysis['min_price'], fallback: double.infinity);
      if (minPrice <= 99) {
        tags.add('Under ${getCurrencySymbol(context)}99');
      } else if (minPrice <= 199) {
        tags.add('Under ${getCurrencySymbol(context)}199');
      }
    }

    return tags.take(3).toList(growable: false);
  }

  @override
  Widget build(BuildContext context) {
    final palette = Theme.of(context).colorScheme;
    final restaurantId =
        _parseInt(restaurant['id'] ?? restaurant['restaurant_id']);
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
    final cuisines = _resolveCuisineText(restaurant);
    final deliveryTime = _parseInt(
      restaurant['delivery_time'] ?? restaurant['deliveryTime'],
      fallback: 30,
    );
    final distanceText = _distanceText(restaurant);
    final rating = _parseDouble(
      restaurant['rating'] ??
          restaurant['avg_rating'] ??
          restaurant['review_rating'] ??
          restaurant['rating_value'],
      fallback: 0,
    );
    final reviewCount = _parseInt(
      restaurant['total_ratings'] ?? restaurant['review_count'] ?? 0,
    );
    final isOpen = restaurant['is_open'] == null
        ? true
        : _parseBool(
            restaurant['is_open_now'] ?? restaurant['is_open'],
            fallback: true,
          );
    final offerText = _offerText(restaurant);
    final signalTags = _signalTags(context, restaurant);
    final deliveryFeeText = _deliveryFeeText(context, restaurant);

    return Consumer<CartProvider>(
      builder: (context, cart, _) {
        final hasCart = cart.restaurant?.id == restaurantId && cart.isNotEmpty;

        return Opacity(
          opacity: isOpen ? 1 : 0.78,
          child: InkWell(
            onTap: isOpen ? onTap : null,
            borderRadius: BorderRadius.circular(18),
            child: Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFFE9EDF3)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.05),
                      blurRadius: 14,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                clipBehavior: Clip.antiAlias,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Stack(
                      children: [
                        SizedBox(
                          height: 152,
                          width: double.infinity,
                          child: imageUrl.isNotEmpty
                              ? AppCachedImage(
                                  imageUrl: imageUrl,
                                  fit: BoxFit.cover,
                                  errorBuilder: (_, __, ___) =>
                                      _imageFallback(context),
                                )
                              : _imageFallback(context),
                        ),
                        Positioned(
                          left: 12,
                          top: 12,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 5,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.black.withOpacity(0.76),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Container(
                                  width: 14,
                                  height: 14,
                                  padding: const EdgeInsets.all(2),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(4),
                                    border: Border.all(
                                      color: palette.primary,
                                      width: 1.2,
                                    ),
                                  ),
                                  child: DecoratedBox(
                                    decoration: BoxDecoration(
                                      color: palette.primary,
                                      borderRadius: BorderRadius.circular(999),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  offerText.isNotEmpty
                                      ? offerText
                                      : '${name.split(' ').first} · ${restaurant['price_range'] ?? ''}'
                                          .trim(),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        Positioned(
                          right: 14,
                          top: 14,
                          child: InkWell(
                            onTap: onSaveToggle,
                            borderRadius: BorderRadius.circular(999),
                            child: Icon(
                              isSaved
                                  ? Icons.bookmark_rounded
                                  : Icons.bookmark_border_rounded,
                              color: Colors.white,
                              size: 26,
                            ),
                          ),
                        ),
                        Positioned(
                          left: 12,
                          bottom: 12,
                          child: _statusBadge(isOpen),
                        ),
                      ],
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Text(
                                  name,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 16,
                                    height: 1.1,
                                    fontWeight: FontWeight.w800,
                                    color: FoodFlowTheme.ink,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              if (rating > 0)
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 10,
                                        vertical: 6,
                                      ),
                                      decoration: BoxDecoration(
                                        color: palette.primary,
                                        borderRadius: BorderRadius.circular(18),
                                        boxShadow: [
                                          BoxShadow(
                                            color: palette.primary
                                                .withOpacity(0.2),
                                            blurRadius: 8,
                                            offset: const Offset(0, 2),
                                          ),
                                        ],
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          const Icon(
                                            Icons.star_rounded,
                                            size: 16,
                                            color: Colors.white,
                                          ),
                                          const SizedBox(width: 6),
                                          Text(
                                            rating.toStringAsFixed(1),
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontSize: 13,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      reviewCount > 0
                                          ? 'By ${reviewCount >= 1000 ? '${(reviewCount / 1000).toStringAsFixed(1)}K+' : '$reviewCount+'}'
                                          : 'New',
                                      style: const TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w600,
                                        color: FoodFlowTheme.muted,
                                      ),
                                    ),
                                  ],
                                ),
                              if (rating <= 0) _newBadge(),
                            ],
                          ),
                          const SizedBox(height: 5),
                          Text(
                            [
                              '${deliveryTime - 5}-$deliveryTime mins',
                              if (distanceText.isNotEmpty) distanceText,
                              if (deliveryFeeText.isNotEmpty) deliveryFeeText,
                            ].join(' • '),
                            style: const TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          if (offerText.isNotEmpty) ...[
                            const SizedBox(height: 7),
                            Row(
                              children: [
                                const Icon(
                                  Icons.discount_rounded,
                                  color: Color(0xFF4B76E5),
                                  size: 20,
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    offerText,
                                    style: const TextStyle(
                                      color: Color(0xFF626C81),
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                          if (cuisines.isNotEmpty) ...[
                            const SizedBox(height: 7),
                            Text(
                              cuisines,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: FoodFlowTheme.inkSoft,
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                          const SizedBox(height: 8),
                          const Divider(color: Color(0xFFF0F2F6), height: 1),
                          const SizedBox(height: 8),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: signalTags.isEmpty
                                    ? const SizedBox.shrink()
                                    : Wrap(
                                        spacing: 8,
                                        runSpacing: 8,
                                        children: signalTags
                                            .map(
                                              (label) => Container(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                  horizontal: 10,
                                                  vertical: 6,
                                                ),
                                                decoration: BoxDecoration(
                                                  color:
                                                      const Color(0xFFF5F7FC),
                                                  borderRadius:
                                                      BorderRadius.circular(18),
                                                ),
                                                child: Text(
                                                  label,
                                                  style: const TextStyle(
                                                    color: Color(0xFF3F4B61),
                                                    fontSize: 11,
                                                    fontWeight: FontWeight.w700,
                                                  ),
                                                ),
                                              ),
                                            )
                                            .toList(growable: false),
                                      ),
                              ),
                              if (hasCart) ...[
                                const SizedBox(width: 10),
                                GestureDetector(
                                  onTap: () =>
                                      Navigator.pushNamed(context, '/cart'),
                                  child: const _MiniViewCartButton(),
                                ),
                              ],
                            ],
                          ),
                          if (!isOpen) ...[
                            const SizedBox(height: 8),
                            Text(
                              _closedMessage(restaurant),
                              style: const TextStyle(
                                color: Color(0xFFD14343),
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ],
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

  Widget _imageFallback(BuildContext context) {
    return Container(
      color: const Color(0xFFF7EFE7),
      child: Icon(
        Icons.restaurant_rounded,
        color: Theme.of(context).colorScheme.primary,
        size: 46,
      ),
    );
  }

  Widget _newBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: const Color(0xFF0A9443),
        borderRadius: BorderRadius.circular(18),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.star_rounded,
            size: 12,
            color: Colors.white,
          ),
          SizedBox(width: 4),
          Text(
            'New',
            style: TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _statusBadge(bool isOpen) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: BoxDecoration(
        color: isOpen ? const Color(0xFF0A9443) : const Color(0xFFD14343),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.18),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isOpen ? Icons.check_circle_rounded : Icons.lock_clock_rounded,
            color: Colors.white,
            size: 14,
          ),
          const SizedBox(width: 5),
          Text(
            isOpen ? 'Open' : 'Closed',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _MiniViewCartButton extends StatelessWidget {
  const _MiniViewCartButton();

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final secondary = Theme.of(context).colorScheme.secondary;
    final cart = context.watch<CartProvider>();
    final itemCount = cart.itemCount;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: <Color>[
            primary,
            Color.lerp(primary, secondary, 0.24) ?? primary,
          ],
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: primary.withOpacity(0.24),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.shopping_cart_rounded,
              color: Colors.white, size: 18),
          const SizedBox(width: 8),
          Text(
            itemCount.toString(),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 13,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(width: 12),
          Text(
            'View Cart',
            style: GoogleFonts.nunitoSans(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
