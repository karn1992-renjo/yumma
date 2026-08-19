// lib/models/menu_item.dart
import 'dart:convert';
import '../config/app_config.dart';
import '../utils/json_utils.dart';

class MenuItem {
  final int id;
  final int restaurantId;
  final int? categoryId;
  final String name;
  final String? description;
  final double price;
  final double? discountedPrice;
  final List<String> images;
  final bool isVeg;
  final bool isAvailable;
  final int? preparationTime;
  final int totalOrders;
  final double? rating;
  final String? categoryName;
  final DateTime createdAt;

  MenuItem({
    required this.id,
    required this.restaurantId,
    this.categoryId,
    required this.name,
    this.description,
    required this.price,
    this.discountedPrice,
    required this.images,
    this.isVeg = true,
    this.isAvailable = true,
    this.preparationTime,
    this.totalOrders = 0,
    this.rating,
    this.categoryName,
    required this.createdAt,
  });

  factory MenuItem.fromJson(Map<String, dynamic> json) {
    final imageList = _parseImages(json);

    return MenuItem(
      id: parseIntValue(json['id']),
      restaurantId: parseIntValue(json['restaurant_id']),
      categoryId: parseNullableInt(json['category_id']),
      name: json['name'] ?? '',
      description: json['description'],
      price: parseDoubleValue(json['price']),
      discountedPrice: parseNullableDouble(json['discounted_price']),
      images: imageList,
      isVeg: parseBoolValue(json['is_veg'], true),
      isAvailable: parseBoolValue(json['is_available'], true),
      preparationTime: parseNullableInt(json['preparation_time']),
      totalOrders: parseIntValue(json['total_orders']),
      rating: parseNullableDouble(json['rating']),
      categoryName: json['category_name'],
      createdAt: DateTime.parse(
          json['created_at'] ?? DateTime.now().toIso8601String()),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'restaurant_id': restaurantId,
      'category_id': categoryId,
      'name': name,
      'description': description,
      'price': price,
      'discounted_price': discountedPrice,
      'images': images,
      'is_veg': isVeg,
      'is_available': isAvailable,
      'preparation_time': preparationTime,
      'total_orders': totalOrders,
      'rating': rating,
      'category_name': categoryName,
      'created_at': createdAt.toIso8601String(),
    };
  }

  double get finalPrice => discountedPrice ?? price;
  String get imageUrl => images.isNotEmpty ? _resolveImageUrl(images[0]) : '';
  bool get hasDiscount => discountedPrice != null && discountedPrice! < price;
  double get discountPercent => hasDiscount
      ? ((price - discountedPrice!) / price * 100).roundToDouble()
      : 0;

  String _resolveImageUrl(String rawValue) {
    final value = rawValue.trim();
    if (value.isEmpty) return '';
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }

    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final origin = '${apiUri.scheme}://${apiUri.host}';
    final normalized = value.startsWith('/') ? value.substring(1) : value;

    if (normalized.startsWith('storage/')) {
      return '$origin/$normalized';
    }

    if (normalized.startsWith('uploads/') ||
        normalized.startsWith('menu_items/') ||
        normalized.startsWith('restaurants/')) {
      return '$origin/storage/$normalized';
    }

    return '$origin/storage/$normalized';
  }

  static List<String> _parseImages(Map<String, dynamic> json) {
    final images = <String>[];

    void addImage(dynamic value) {
      if (value == null) return;
      if (value is String) {
        final trimmed = value.trim();
        if (trimmed.isEmpty || trimmed == 'null') return;
        images.add(trimmed);
        return;
      }
      if (value is Map) {
        for (final key in ['url', 'path', 'image', 'image_url', 'file']) {
          addImage(value[key]);
        }
        return;
      }
      if (value is List) {
        for (final item in value) {
          addImage(item);
        }
      }
    }

    final rawImages = json['images'];
    if (rawImages is String) {
      try {
        addImage(jsonDecode(rawImages));
      } catch (_) {
        addImage(rawImages);
      }
    } else {
      addImage(rawImages);
    }

    for (final key in [
      'image_url',
      'image',
      'photo',
      'thumbnail',
      'thumb',
      'picture',
    ]) {
      addImage(json[key]);
    }

    return images.toSet().toList();
  }
}
