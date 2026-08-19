// lib/models/restaurant.dart
import '../config/app_config.dart';
import 'branch_info.dart';

class Restaurant {
  final int id;
  final int? branchId;
  final BranchInfo? branch;
  final String name;
  final String slug;
  final String email;
  final String phone;
  final String? description;
  final String address;
  final String city;
  final String state;
  final String pincode;
  final double latitude;
  final double longitude;
  final double deliveryRadius;
  final double minOrderAmount;
  final double deliveryFee;
  final int deliveryTime;
  final List<String> cuisine;
  final String? logoImage;
  final String? bannerImage;
  final double rating;
  final int reviewCount;
  final bool isOpen;
  final bool isVerified;
  final bool isFeatured;
  final String? restaurantType;
  final double? diningCharge;
  final dynamic weeklyTimings;
  final DateTime createdAt;

  Restaurant({
    required this.id,
    this.branchId,
    this.branch,
    required this.name,
    required this.slug,
    required this.email,
    required this.phone,
    this.description,
    required this.address,
    required this.city,
    required this.state,
    required this.pincode,
    required this.latitude,
    required this.longitude,
    required this.deliveryRadius,
    required this.minOrderAmount,
    required this.deliveryFee,
    required this.deliveryTime,
    required this.cuisine,
    this.logoImage,
    this.bannerImage,
    this.rating = 0.0,
    this.reviewCount = 0,
    required this.isOpen,
    this.isVerified = false,
    this.isFeatured = false,
    this.restaurantType,
    this.diningCharge,
    this.weeklyTimings,
    required this.createdAt,
  });

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

  static bool _parseBool(dynamic value, {required bool fallback}) {
    if (value is bool) return value;
    if (value is int) return value != 0;
    if (value is String) {
      final normalized = value.toLowerCase().trim();
      return normalized == 'true' ||
          normalized == '1' ||
          normalized == 'yes' ||
          normalized == 'y';
    }
    return fallback;
  }

  factory Restaurant.fromJson(Map<String, dynamic> json) {
    final cuisineList = _parseCuisineNames(json);

    // Handle different field names from backend
    final minOrder = json['min_order_amount'] ?? json['min_order'] ?? 0;
    final delRadius = json['delivery_radius'] ?? 15.0;
    final branch = json['branch'] is Map<String, dynamic>
        ? json['branch'] as Map<String, dynamic>
        : json['branch'] is Map
        ? Map<String, dynamic>.from(json['branch'] as Map)
        : null;

    return Restaurant(
      id: _parseInt(json['id'], fallback: 0),
      branchId: _parseInt(json['branch_id'] ?? branch?['id'], fallback: 0) == 0
          ? null
          : _parseInt(json['branch_id'] ?? branch?['id'], fallback: 0),
      branch: branch != null ? BranchInfo.fromJson(branch) : null,
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'] ?? '',
      description: json['description'],
      address: json['address'] ?? '',
      city: json['city'] ?? '',
      state: json['state'] ?? '',
      pincode: json['pincode'] ?? '',
      latitude: _parseDouble(json['latitude'], fallback: 0.0),
      longitude: _parseDouble(json['longitude'], fallback: 0.0),
      deliveryRadius: _parseDouble(delRadius, fallback: 10.0),
      minOrderAmount: _parseDouble(minOrder, fallback: 0.0),
      deliveryFee: _parseDouble(json['delivery_fee'], fallback: 40.0),
      deliveryTime: _parseInt(json['delivery_time'], fallback: 30),
      cuisine: cuisineList,
      logoImage: json['logo_image'] ?? json['logo'] ?? json['image_url'],
      bannerImage:
          json['banner_image'] ?? json['banner_url'] ?? json['image_url'],
      rating: _parseDouble(
        json['rating'] ??
            json['avg_rating'] ??
            json['review_rating'] ??
            json['rating_value'] ??
            json['restaurant_rating'],
        fallback: 0.0,
      ),
      reviewCount: _parseInt(
        json['total_ratings'] ??
            json['review_count'] ??
            json['reviews'] ??
            json['rating_count'],
        fallback: 0,
      ),
      isOpen: _parseBool(json['is_open'], fallback: false),
      isVerified: _parseBool(json['is_verified'], fallback: false),
      isFeatured: _parseBool(json['is_featured'], fallback: false),
      restaurantType: json['restaurant_type'],
      diningCharge: json['dining_charge'] != null
          ? _parseDouble(json['dining_charge'], fallback: 0.0)
          : null,
      weeklyTimings: json['weekly_timings'],
      createdAt:
          DateTime.tryParse(json['created_at']?.toString() ?? '') ??
          DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'branch_id': branchId,
      'branch': branch?.toJson(),
      'name': name,
      'slug': slug,
      'email': email,
      'phone': phone,
      'description': description,
      'address': address,
      'city': city,
      'state': state,
      'pincode': pincode,
      'latitude': latitude,
      'longitude': longitude,
      'delivery_radius': deliveryRadius,
      'min_order_amount': minOrderAmount,
      'delivery_fee': deliveryFee,
      'delivery_time': deliveryTime,
      'cuisine': cuisine,
      'logo_image': logoImage,
      'banner_image': bannerImage,
      'rating': rating,
      'review_count': reviewCount,
      'is_open': isOpen,
      'is_verified': isVerified,
      'is_featured': isFeatured,
      'restaurant_type': restaurantType,
      'dining_charge': diningCharge,
      'weekly_timings': weeklyTimings,
      'created_at': createdAt.toIso8601String(),
    };
  }

  String _resolveFullImageUrl(String? image) {
    if (image == null || image.isEmpty) return '';
    final trimmed = image.trim();
    if (trimmed.startsWith('http')) return trimmed;
    if (trimmed.startsWith('/')) return '${AppConfig.apiBaseUrl}$trimmed';
    return '${AppConfig.apiBaseUrl}/storage/$trimmed';
  }

  String get logoUrl => _resolveFullImageUrl(logoImage);
  String get bannerUrl => _resolveFullImageUrl(bannerImage);
  String get cuisineText => cuisine.join(', ');
  String get branchLabel => branch?.label ?? 'Unassigned Branch';
  bool get hasBranch => branchId != null || branch != null;
  bool get isDining => restaurantType == 'dining' || restaurantType == 'both';
  bool get isDelivery =>
      restaurantType == 'delivery' || restaurantType == 'both';
  bool get hasVisibleRating => reviewCount >= 3;
  double? get visibleRating => hasVisibleRating ? rating : null;
}

List<String> _parseCuisineNames(Map<String, dynamic> json) {
  List<String> namesFrom(dynamic value) {
    if (value is String && value.trim().isNotEmpty) {
      return value
          .split(',')
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty && int.tryParse(e) == null)
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
          .where((e) => e.isNotEmpty)
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
    final names = namesFrom(json[key]);
    if (names.isNotEmpty) return names;
  }

  return const [];
}
