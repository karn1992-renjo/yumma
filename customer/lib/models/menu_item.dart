// lib/models/menu_item.dart
import 'dart:convert';
import '../config/app_config.dart';
import '../utils/json_utils.dart';

class MenuOption {
  final String name;
  final double price;
  final bool isAvailable;
  final Map<String, String> customFields;

  const MenuOption({
    required this.name,
    this.price = 0,
    this.isAvailable = true,
    this.customFields = const {},
  });

  factory MenuOption.fromJson(dynamic value) {
    if (value is Map<String, dynamic>) {
      return MenuOption(
        name: (value['name'] ?? value['title'] ?? '').toString(),
        price: parseDoubleValue(value['price']),
        isAvailable: parseBoolValue(value['is_available'], true),
        customFields: _parseCustomFields(value['custom_fields']),
      );
    }

    return MenuOption(name: value?.toString() ?? '');
  }

  Map<String, dynamic> toJson() => {
        'name': name,
        'price': price,
        'is_available': isAvailable,
        'custom_fields': customFields,
      };

  static Map<String, String> _parseCustomFields(dynamic value) {
    if (value is! Map) return const {};

    return value.map((key, fieldValue) {
      return MapEntry(key.toString(), fieldValue?.toString() ?? '');
    });
  }
}

class MenuItem {
  final int id;
  final int restaurantId;
  final int? categoryId;
  final int? cuisineId;
  final String name;
  final String? description;
  final double price;
  final double? discountedPrice;
  final List<String> images;
  final bool isVeg;
  final String foodType;
  final String dietLabel;
  final bool isAvailable;
  final int? preparationTime;
  final int totalOrders;
  final double? rating;
  final String? categoryName;
  final String? cuisineName;
  final bool isRecommended;
  final bool isBestseller;
  final bool isNew;
  final bool isSpicy;
  final bool isCombo;
  final List<String> tags;
  final List<MenuOption> variants;
  final List<MenuOption> addOns;
  final DateTime createdAt;

  MenuItem({
    required this.id,
    required this.restaurantId,
    this.categoryId,
    this.cuisineId,
    required this.name,
    this.description,
    required this.price,
    this.discountedPrice,
    required this.images,
    this.isVeg = true,
    this.foodType = 'veg',
    this.dietLabel = 'Veg',
    this.isAvailable = true,
    this.preparationTime,
    this.totalOrders = 0,
    this.rating,
    this.categoryName,
    this.cuisineName,
    this.isRecommended = false,
    this.isBestseller = false,
    this.isNew = false,
    this.isSpicy = false,
    this.isCombo = false,
    this.tags = const [],
    this.variants = const [],
    this.addOns = const [],
    required this.createdAt,
  });

  factory MenuItem.fromJson(Map<String, dynamic> json) {
    final imageList = _parseImages(json);

    return MenuItem(
      id: parseIntValue(json['menu_item_id'] ?? json['id']),
      restaurantId: parseIntValue(json['restaurant_id']),
      categoryId: parseNullableInt(json['category_id']),
      cuisineId: parseNullableInt(json['cuisine_id']),
      name: json['name'] ?? '',
      description: json['description'],
      price: _parsePrice(json, discounted: false),
      discountedPrice: _parseDiscountedPrice(json),
      images: imageList,
      isVeg: parseBoolValue(json['is_veg'], true),
      foodType: (json['food_type'] ??
              (parseBoolValue(json['is_veg'], true) ? 'veg' : 'non_veg'))
          .toString(),
      dietLabel: (json['diet_label'] ??
              _labelForFoodType(json['food_type']?.toString(),
                  parseBoolValue(json['is_veg'], true)))
          .toString(),
      isAvailable: parseBoolValue(json['is_available'], true),
      preparationTime: parseNullableInt(json['preparation_time']),
      totalOrders: parseIntValue(json['total_orders']),
      rating: parseNullableDouble(json['rating']),
      categoryName: json['category_name'] ?? json['category']?['name'],
      cuisineName: json['cuisine_name'] ?? json['cuisine']?['name'],
      isRecommended: parseBoolValue(json['is_recommended'], false),
      isBestseller: parseBoolValue(json['is_bestseller'], false),
      isNew: parseBoolValue(json['is_new'], false),
      isSpicy: parseBoolValue(json['is_spicy'], false),
      isCombo: parseBoolValue(json['is_combo'], false),
      tags: _parseTags(json['tags']),
      variants: _parseOptions(json['variants']),
      addOns: _parseOptions(json['add_ons'] ?? json['addons']),
      createdAt: DateTime.parse(
          json['created_at'] ?? DateTime.now().toIso8601String()),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'restaurant_id': restaurantId,
      'category_id': categoryId,
      'cuisine_id': cuisineId,
      'name': name,
      'description': description,
      'price': price,
      'discounted_price': discountedPrice,
      'images': images,
      'is_veg': isVeg,
      'food_type': foodType,
      'diet_label': dietLabel,
      'is_available': isAvailable,
      'preparation_time': preparationTime,
      'total_orders': totalOrders,
      'rating': rating,
      'category_name': categoryName,
      'cuisine_name': cuisineName,
      'is_recommended': isRecommended,
      'is_bestseller': isBestseller,
      'is_new': isNew,
      'is_spicy': isSpicy,
      'is_combo': isCombo,
      'tags': tags,
      'variants': variants.map((option) => option.toJson()).toList(),
      'add_ons': addOns.map((option) => option.toJson()).toList(),
      'created_at': createdAt.toIso8601String(),
    };
  }

  double get finalPrice => discountedPrice ?? price;
  bool get hasCustomizations => variants.isNotEmpty || addOns.isNotEmpty;
  String get imageUrl => images.isNotEmpty ? _resolveImageUrl(images[0]) : '';
  bool get isEgg => foodType == 'egg';
  bool get isNonVeg => foodType == 'non_veg';
  bool get hasDiscount => discountedPrice != null && discountedPrice! < price;
  List<String> get displayTags {
    final values = <String>[
      ...tags,
      if (isBestseller) 'Best seller',
      if (isRecommended) 'Recommended',
      if (isNew) 'New',
      if (isSpicy) 'Spicy',
      if (isCombo) 'Combo',
    ];
    final seen = <String>{};
    return values
        .map(_formatTag)
        .where((tag) => tag.isNotEmpty)
        .where((tag) => seen.add(tag.toLowerCase()))
        .toList(growable: false);
  }
  double get discountPercent => hasDiscount
      ? ((price - discountedPrice!) / price * 100).roundToDouble()
      : 0;

  static double _parsePrice(Map<String, dynamic> json, {required bool discounted}) {
    final keys = discounted
        ? const [
            'discounted_price',
            'discountedPrice',
            'final_price',
            'finalPrice',
            'sale_price',
            'offer_price',
          ]
        : const [
            'price',
            'base_price',
            'regular_price',
            'item_price',
            'menu_price',
            'unit_price',
            'selling_price',
            'amount',
            'mrp',
            'final_price',
            'finalPrice',
            'discounted_price',
            'discountedPrice',
            'sale_price',
            'offer_price',
          ];

    for (final key in keys) {
      final parsed = _parsePriceValue(json[key]);
      if (parsed != null && parsed > 0) return parsed;
    }

    for (final key in const ['variant', 'default_variant', 'selected_variant']) {
      final value = json[key];
      if (value is Map) {
        final parsed = _parsePriceValue(value['price'] ?? value['final_price']);
        if (parsed != null && parsed > 0) return parsed;
      }
    }

    final variants = json['variants'];
    if (variants is List) {
      final prices = variants
          .whereType<Map>()
          .map((item) => _parsePriceValue(item['price'] ?? item['final_price']))
          .whereType<double>()
          .where((value) => value > 0)
          .toList();
      if (prices.isNotEmpty) {
        prices.sort();
        return prices.first;
      }
    }

    return 0;
  }

  static double? _parseDiscountedPrice(Map<String, dynamic> json) {
    final value = _parsePrice(json, discounted: true);
    return value > 0 ? value : null;
  }

  static double? _parsePriceValue(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    if (value is String) {
      final cleaned = value.replaceAll(RegExp(r'[^0-9.\-]'), '');
      if (cleaned.isEmpty || cleaned == '-' || cleaned == '.') return null;
      return double.tryParse(cleaned);
    }
    if (value is Map) {
      for (final key in const ['amount', 'value', 'price', 'final_price']) {
        final parsed = _parsePriceValue(value[key]);
        if (parsed != null) return parsed;
      }
    }
    return null;
  }

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

  static List<MenuOption> _parseOptions(dynamic rawValue) {
    dynamic value = rawValue;
    if (value is String) {
      if (value.trim().isEmpty) return [];
      try {
        value = jsonDecode(value);
      } catch (_) {
        return [];
      }
    }

    if (value is! List) return [];

    return value
        .map((item) => MenuOption.fromJson(item))
        .where((option) => option.name.trim().isNotEmpty)
        .where((option) => option.isAvailable)
        .toList();
  }

  static List<String> _parseTags(dynamic rawValue) {
    final tags = <String>[];

    void addTag(dynamic value) {
      if (value == null) return;
      if (value is String) {
        final trimmed = value.trim();
        if (trimmed.isEmpty || trimmed == 'null') return;
        tags.add(trimmed);
        return;
      }
      if (value is Map) {
        value.forEach((key, tagValue) {
          if (tagValue == true || tagValue == 1 || tagValue == '1') {
            addTag(key);
          } else {
            addTag(tagValue);
          }
        });
        return;
      }
      if (value is List) {
        for (final item in value) {
          addTag(item);
        }
      }
    }

    dynamic value = rawValue;
    if (value is String) {
      if (value.trim().isEmpty) return const [];
      try {
        value = jsonDecode(value);
      } catch (_) {
        value = value.split(RegExp(r'[\n,]+'));
      }
    }

    addTag(value);

    final seen = <String>{};
    return tags
        .map(_formatTag)
        .where((tag) => tag.isNotEmpty)
        .where((tag) => seen.add(tag.toLowerCase()))
        .toList(growable: false);
  }

  static String _formatTag(String rawValue) {
    final normalized = rawValue
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .trim()
        .replaceAll(RegExp(r'\s+'), ' ');
    if (normalized.isEmpty) return '';
    return normalized
        .split(' ')
        .map((word) {
          if (word.isEmpty) return '';
          return word[0].toUpperCase() + word.substring(1).toLowerCase();
        })
        .join(' ');
  }

  static String _labelForFoodType(String? value, bool isVeg) {
    if (value == 'egg') return 'Egg';
    if (value == 'non_veg') return 'Non-Veg';
    return isVeg ? 'Veg' : 'Non-Veg';
  }
}
