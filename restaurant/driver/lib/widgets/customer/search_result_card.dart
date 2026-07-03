// lib/widgets/customer/search_result_card.dart
import 'package:flutter/material.dart';
import '../../config/app_config.dart';
import '../../models/restaurant.dart';

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
    
    final imageUrl = data['image_url'] ?? 
                     data['banner_url'] ?? 
                     data['logo_image'] ??
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

  @override
  Widget build(BuildContext context) {
    final name = _getName();
    final rating = _getRating();
    final hasVisibleRating = _hasVisibleRating();
    final deliveryTime = _getDeliveryTime();
    final offerText = _getOfferText();
    final imageUrl = _getImageUrl();
    
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Restaurant Image
            ClipRRect(
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(12),
                bottomLeft: Radius.circular(12),
              ),
              child: imageUrl.isNotEmpty
                  ? Image.network(
                      imageUrl,
                      width: 100,
                      height: 100,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(
                        width: 100,
                        height: 100,
                        color: Colors.grey.shade200,
                        child: const Icon(Icons.restaurant, size: 40, color: Colors.grey),
                      ),
                    )
                  : Container(
                      width: 100,
                      height: 100,
                      color: Colors.grey.shade200,
                      child: const Icon(Icons.restaurant, size: 40, color: Colors.grey),
                    ),
            ),
            
            // Restaurant Info
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        const Icon(Icons.star, size: 14, color: Colors.green),
                        const SizedBox(width: 4),
                        Text(
                          hasVisibleRating ? rating.toStringAsFixed(1) : 'New',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '$deliveryTime mins',
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.grey.shade600,
                          ),
                        ),
                      ],
                    ),
                    if (offerText.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFF6B6B).withOpacity(0.1),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          offerText,
                          style: const TextStyle(
                            color: Color(0xFFFF6B6B),
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
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
