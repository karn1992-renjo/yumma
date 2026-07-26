// lib/widgets/customer/search_result_card.dart
import 'package:flutter/material.dart';
import '../common/app_cached_image.dart';
import '../../config/app_config.dart';
import '../../models/restaurant.dart';
import '../../theme/foodflow_theme.dart';

class SearchResultCard extends StatelessWidget {
  final dynamic restaurant;
  final VoidCallback onTap;

  const SearchResultCard({
    Key? key,
    required this.restaurant,
    required this.onTap,
  }) : super(key: key);

  Map<String, dynamic> _toMap() {
    if (restaurant is Map) {
      return restaurant as Map<String, dynamic>;
    } else if (restaurant is Restaurant) {
      return (restaurant as Restaurant).toJson();
    }
    return {};
  }

  String? _firstString(Map<String, dynamic> data, List<String> keys) {
    for (final key in keys) {
      final value = data[key];
      if (value is String && value.trim().isNotEmpty) {
        return value.trim();
      }
    }
    return null;
  }

  String? _firstNestedString(
    Map<String, dynamic> data,
    List<String> parents,
    List<String> keys,
  ) {
    for (final parent in parents) {
      final value = data[parent];
      if (value is Map) {
        final found = _firstString(Map<String, dynamic>.from(value), keys);
        if (found != null) return found;
      }
    }
    return null;
  }

  double _parseDouble(dynamic value, {double defaultValue = 0.0}) {
    if (value == null) return defaultValue;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? defaultValue;
    return defaultValue;
  }

  int _parseInt(dynamic value, {int defaultValue = 0}) {
    if (value == null) return defaultValue;
    if (value is int) return value;
    if (value is double) return value.toInt();
    if (value is String) return int.tryParse(value) ?? defaultValue;
    return defaultValue;
  }

  String _getOfferText() {
    final data = _toMap();

    final offer = data['offer'] ?? data['discount'] ?? data['promotion'];
    if (offer != null && offer.toString().isNotEmpty) {
      return offer.toString();
    }

    final discountPercent = data['discount_percent'];
    if (discountPercent != null) {
      final percent = _parseDouble(discountPercent);
      if (percent > 0) {
        return '${percent.toStringAsFixed(0)}% OFF';
      }
    }

    return '';
  }

  String _getImageUrl() {
    final data = _toMap();

    final imageUrl = data['logo_image'] ??
        data['logo'] ??
        data['image_url'] ??
        data['banner_url'] ??
        data['banner_image'] ??
        data['image'];

    return AppConfig.resolveMediaUrl(imageUrl);
  }

  String _getName() {
    final data = _toMap();
    final directName = _firstString(data, const [
      'restaurant_name',
      'restaurantName',
      'store_name',
      'storeName',
      'business_name',
      'businessName',
      'vendor_name',
      'vendorName',
      'merchant_name',
      'merchantName',
    ]);
    final nestedName = _firstNestedString(data, const [
      'restaurant',
      'store',
      'vendor',
      'merchant',
    ], const [
      'name',
      'restaurant_name',
      'restaurantName',
      'title',
      'store_name',
      'business_name',
    ]);
    final fallbackName = _firstString(data, const ['title', 'name']);
    final resolved = directName ??
        nestedName ??
        (fallbackName != null && fallbackName.toLowerCase() != 'restaurant'
            ? fallbackName
            : null) ??
        'Restaurant';
    assert(() {
      debugPrint(
        'SEARCH_CARD_NAME resolved="$resolved" direct="$directName" '
        'nested="$nestedName" fallback="$fallbackName" '
        'id=${data['id']} keys=${data.keys.take(28).join(',')}',
      );
      return true;
    }());
    return resolved;
  }

  double _getRating() {
    final data = _toMap();
    return _parseDouble(data['rating'], defaultValue: 0.0);
  }

  bool _hasVisibleRating() {
    final data = _toMap();
    return _parseInt(
          data['total_ratings'] ?? data['review_count'] ?? 0,
          defaultValue: 0,
        ) >=
        3;
  }

  int _getDeliveryTime() {
    final data = _toMap();
    return _parseInt(data['delivery_time'], defaultValue: 35);
  }

  String _getSearchHint() {
    if (restaurant is Restaurant) {
      final matched = (restaurant as Restaurant).matchedItemNames;
      if (matched.isNotEmpty) {
        return 'Matches: ${matched.take(3).join(', ')}';
      }
      final cuisine = (restaurant as Restaurant).cuisineText;
      return cuisine.isNotEmpty ? cuisine : '';
    }

    final data = _toMap();
    final matched = data['matched_item_names'];
    if (matched is List && matched.isNotEmpty) {
      return 'Matches: ${matched.take(3).map((item) => item.toString()).join(', ')}';
    }

    return _resolveCuisineText(data);
  }

  String _resolveCuisineText(Map<String, dynamic> data) {
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
      final values = namesFrom(data[key]);
      if (values.isNotEmpty) return values.join(', ');
    }
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    final name = _getName();
    final rating = _getRating();
    final hasVisibleRating = _hasVisibleRating();
    final deliveryTime = _getDeliveryTime();
    final offerText = _getOfferText();
    final imageUrl = _getImageUrl();
    final searchHint = _getSearchHint();

    Widget placeholder() => Container(
          color: FoodFlowTheme.tagOrangeSoft,
          child: const Icon(
            Icons.restaurant_rounded,
            size: 34,
            color: FoodFlowTheme.tagOrange,
          ),
        );

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        padding: const EdgeInsets.fromLTRB(14, 13, 14, 13),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE9EDF3)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: SizedBox(
                width: 88,
                height: 82,
                child: imageUrl.isNotEmpty
                    ? AppCachedImage(
                        imageUrl: imageUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => placeholder(),
                      )
                    : placeholder(),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(
                        Icons.storefront_rounded,
                        size: 14,
                        color: primary,
                      ),
                      const SizedBox(width: 8),
                      const Text(
                        'Restaurant',
                        style: TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 15,
                      height: 1.2,
                      fontWeight: FontWeight.w700,
                      color: FoodFlowTheme.ink,
                    ),
                  ),
                  if (searchHint.isNotEmpty) ...[
                    const SizedBox(height: 5),
                    Text(
                      searchHint,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        height: 1.35,
                      ),
                    ),
                  ],
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: hasVisibleRating
                              ? const Color(0xFFEAF9EF)
                              : primary.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.star_rounded,
                              size: 13,
                              color: hasVisibleRating
                                  ? FoodFlowTheme.success
                                  : primary,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              hasVisibleRating
                                  ? rating.toStringAsFixed(1)
                                  : 'New',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: hasVisibleRating
                                    ? FoodFlowTheme.success
                                    : primary,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '$deliveryTime mins',
                        style: const TextStyle(
                          fontSize: 12,
                          color: FoodFlowTheme.inkSoft,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const Spacer(),
                      if (offerText.isNotEmpty)
                        Flexible(
                          child: Text(
                            offerText,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            textAlign: TextAlign.right,
                            style: const TextStyle(
                              color: FoodFlowTheme.tagOrange,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
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
      ),
    );
  }
}
