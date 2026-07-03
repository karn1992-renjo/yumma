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
    
    if (imageUrl != null && imageUrl.toString().isNotEmpty) {
      if (imageUrl.toString().startsWith('http')) {
        return imageUrl;
      }
      return '${AppConfig.apiBaseUrl}/storage/$imageUrl';
    }
    return '';
  }

  String _getName() {
    final data = _toMap();
    return data['name']?.toString() ?? 'Restaurant';
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
    final name = _getName();
    final rating = _getRating();
    final hasVisibleRating = _hasVisibleRating();
    final deliveryTime = _getDeliveryTime();
    final offerText = _getOfferText();
    final imageUrl = _getImageUrl();
    final searchHint = _getSearchHint();
    
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFF1E8E1)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.045),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Restaurant Image
            ClipRRect(
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(18),
                bottomLeft: Radius.circular(18),
              ),
              child: imageUrl.isNotEmpty
                  ? AppCachedImage(
                      imageUrl: imageUrl,
                      width: 104,
                      height: 112,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(
                        width: 104,
                        height: 112,
                        color: const Color(0xFFFFF4EC),
                        child: const Icon(
                          Icons.restaurant,
                          size: 36,
                          color: FoodFlowTheme.orange,
                        ),
                      ),
                    )
                  : Container(
                      width: 104,
                      height: 112,
                      color: const Color(0xFFFFF4EC),
                      child: const Icon(
                        Icons.restaurant,
                        size: 36,
                        color: FoodFlowTheme.orange,
                      ),
                    ),
            ),
            
            // Restaurant Info
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      name,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: FoodFlowTheme.ink,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    if (searchHint.isNotEmpty) ...[
                      Text(
                        searchHint,
                        style: TextStyle(
                          fontSize: 12,
                          color: FoodFlowTheme.muted,
                          height: 1.35,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 6),
                    ],
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
                                : const Color(0xFF0A9443),
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
                                    : Colors.white,
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
                                      : Colors.white,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '$deliveryTime mins',
                          style: TextStyle(
                            fontSize: 12,
                            color: FoodFlowTheme.ink.withOpacity(0.72),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    if (offerText.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF4EC),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          offerText,
                          style: const TextStyle(
                            color: FoodFlowTheme.orange,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
