// lib/models/restaurant.dart
import '../config/app_config.dart';

class Restaurant {
  final int id;
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
  final double? amountForOne;
  final double deliveryFee;
  final int deliveryTime;
  final int? etaMinutes;
  final String? etaRange;
  final int? travelMinutes;
  final double? travelDistanceKm;
  final int? preparationMinutes;
  final List<String> cuisine;
  final String? logoImage;
  final String? bannerImage;
  final double rating;
  final int reviewCount;
  final bool isOpen;
  final bool isVerified;
  final bool isFeatured;
  final bool isPureVeg;
  final String? restaurantType;
  final double? diningCharge;
  final double? distance;
  final List<String> matchedItemNames;
  final dynamic weeklyTimings;
  final String? nextOpeningLabel;
  final String? fssaiLicenseNumber;
  final int reviewCommentCount;
  final List<Map<String, dynamic>> reviewHighlights;
  final List<Map<String, dynamic>> similarRestaurants;
  final DateTime createdAt;

  Restaurant({
    required this.id,
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
    this.amountForOne,
    required this.deliveryFee,
    required this.deliveryTime,
    this.etaMinutes,
    this.etaRange,
    this.travelMinutes,
    this.travelDistanceKm,
    this.preparationMinutes,
    required this.cuisine,
    this.logoImage,
    this.bannerImage,
    this.rating = 0.0,
    this.reviewCount = 0,
    required this.isOpen,
    this.isVerified = false,
    this.isFeatured = false,
    this.isPureVeg = false,
    this.restaurantType,
    this.diningCharge,
    this.distance,
    this.matchedItemNames = const [],
    this.weeklyTimings,
    this.nextOpeningLabel,
    this.fssaiLicenseNumber,
    this.reviewCommentCount = 0,
    this.reviewHighlights = const [],
    this.similarRestaurants = const [],
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
      return normalized == 'true' || normalized == '1' || normalized == 'yes' || normalized == 'y';
    }
    return fallback;
  }

  static String? _resolveImageString(Map<String, dynamic> json, List<String> keys) {
    for (final key in keys) {
      final value = json[key];
      if (value is String && value.isNotEmpty) {
        return value;
      }
    }
    return null;
  }

  static String _resolveFullImageUrl(String? image) {
    if (image == null || image.isEmpty) return '';
    final trimmed = image.trim();
    if (trimmed.startsWith('http')) return trimmed;
    if (trimmed.startsWith('/')) return '${AppConfig.apiBaseUrl}$trimmed';
    return '${AppConfig.apiBaseUrl}/storage/$trimmed';
  }

  factory Restaurant.fromJson(Map<String, dynamic> json) {
    final cuisineList = _parseCuisineNames(json);

    // Handle different field names from backend
    final minOrder = json['min_order_amount'] ?? json['min_order'] ?? 0;
    final amountForOne = json['amount_for_one'] ??
        json['amountForOne'] ??
        json['price_for_one'] ??
        json['cost_for_one'] ??
        json['lowest_price'];
    final delRadius = json['delivery_radius'] ?? 15.0;

    return Restaurant(
      id: _parseInt(json['id'], fallback: 0),
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
      amountForOne: amountForOne != null
          ? _parseDouble(amountForOne, fallback: 0.0)
          : null,
      deliveryFee: _parseDouble(json['delivery_fee'], fallback: 40.0),
      deliveryTime: _parseInt(json['delivery_time'], fallback: 30),
      etaMinutes: (json['eta_minutes'] ?? json['estimated_delivery_minutes']) !=
              null
          ? _parseInt(
              json['eta_minutes'] ?? json['estimated_delivery_minutes'],
              fallback: 0,
            )
          : null,
      etaRange: json['eta_range']?.toString() ??
          json['estimated_delivery_label']?.toString(),
      travelMinutes: json['travel_minutes'] != null
          ? _parseInt(json['travel_minutes'], fallback: 0)
          : null,
      travelDistanceKm: json['travel_distance_km'] != null
          ? _parseDouble(json['travel_distance_km'], fallback: 0)
          : null,
      preparationMinutes: json['preparation_minutes'] != null
          ? _parseInt(json['preparation_minutes'], fallback: 0)
          : null,
      cuisine: cuisineList,
      logoImage: _resolveImageString(json, [
        'logo_image',
        'logo',
        'image_url',
        'image',
        'photo',
      ]),
      bannerImage: _resolveImageString(json, [
        'banner_image',
        'banner_url',
        'image_url',
        'image',
        'photo',
      ]),
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
      isOpen: _parseBool(json['is_open_now'] ?? json['is_open'], fallback: false),
      isVerified: _parseBool(json['is_verified'], fallback: false),
      isFeatured: _parseBool(json['is_featured'], fallback: false),
      isPureVeg: _parseBool(
        json['is_pure_veg'] ?? json['pure_veg'] ?? json['is_veg'],
        fallback: false,
      ),
      restaurantType: json['restaurant_type'],
      diningCharge: json['dining_charge'] != null
          ? _parseDouble(json['dining_charge'], fallback: 0.0)
          : null,
      distance: json['distance'] != null
          ? _parseDouble(json['distance'], fallback: 0.0)
          : null,
      matchedItemNames: (json['matched_item_names'] is List)
          ? List<String>.from(
              (json['matched_item_names'] as List)
                  .map((item) => item.toString())
                  .where((item) => item.trim().isNotEmpty),
            )
          : const [],
      weeklyTimings: json['weekly_timings'],
      nextOpeningLabel: json['next_opening_label']?.toString(),
      fssaiLicenseNumber: json['fssai_license_number']?.toString(),
      reviewCommentCount: _parseInt(json['review_comment_count'], fallback: 0),
      reviewHighlights: (json['review_highlights'] as List?)
              ?.whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList() ??
          const [],
      similarRestaurants: (json['similar_restaurants'] as List?)
              ?.whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList() ??
          const [],
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? '') ?? DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
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
      'amount_for_one': amountForOne,
      'delivery_fee': deliveryFee,
      'delivery_time': deliveryTime,
      'eta_minutes': etaMinutes,
      'eta_range': etaRange,
      'travel_minutes': travelMinutes,
      'travel_distance_km': travelDistanceKm,
      'preparation_minutes': preparationMinutes,
      'cuisine': cuisine,
      'logo_image': logoImage,
      'banner_image': bannerImage,
      'rating': rating,
      'review_count': reviewCount,
      'is_open': isOpen,
      'is_verified': isVerified,
      'is_featured': isFeatured,
      'is_pure_veg': isPureVeg,
      'restaurant_type': restaurantType,
      'dining_charge': diningCharge,
      'distance': distance,
      'matched_item_names': matchedItemNames,
      'weekly_timings': weeklyTimings,
      'next_opening_label': nextOpeningLabel,
      'fssai_license_number': fssaiLicenseNumber,
      'review_comment_count': reviewCommentCount,
      'review_highlights': reviewHighlights,
      'similar_restaurants': similarRestaurants,
      'created_at': createdAt.toIso8601String(),
    };
  }

  String get logoUrl => _resolveFullImageUrl(logoImage);
  String get bannerUrl => _resolveFullImageUrl(bannerImage);
  String get cuisineText => cuisine.join(', ');
  bool get isDining =>
      restaurantType != null &&
      (restaurantType == 'dining' ||
          restaurantType == 'both' ||
          restaurantType == 'dining_takeaway' ||
          restaurantType == 'all' ||
          restaurantType == 'dine');
  bool get isDelivery =>
      restaurantType == null ||
      restaurantType == 'delivery' ||
      restaurantType == 'both' ||
      restaurantType == 'delivery_takeaway' ||
      restaurantType == 'all' ||
      restaurantType == 'food';
  bool get isTakeaway =>
      restaurantType == 'takeaway' ||
      restaurantType == 'delivery_takeaway' ||
      restaurantType == 'dining_takeaway' ||
      restaurantType == 'all';
  bool get hasVisibleRating => reviewCount >= 3;
  double? get visibleRating => hasVisibleRating ? rating : null;
  String get deliveryEtaLabel =>
      etaRange?.trim().isNotEmpty == true ? etaRange! : '$deliveryTime mins';
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
    if (names.isNotEmpty) {
      return names;
    }
  }

  return const [];
}


