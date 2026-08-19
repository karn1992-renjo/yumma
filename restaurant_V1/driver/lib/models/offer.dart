// lib/models/offer.dart
import '../utils/json_utils.dart';

class Offer {
  final int id;
  final String title;
  final String? subtitle;
  final String? description;
  final String discountType; // 'percentage', 'flat', 'buy_x_get_y'
  final double discountValue;
  final double? maxDiscount;
  final double minOrderAmount;
  final String? code;
  final String? imageUrl;
  final String currencySymbol;
  final int currencyDecimals;
  final DateTime validFrom;
  final DateTime validUntil;
  final bool isActive;
  final List<int>? applicableRestaurantIds;
  final List<String>? applicableCategories;

  Offer({
    required this.id,
    required this.title,
    this.subtitle,
    this.description,
    required this.discountType,
    required this.discountValue,
    this.maxDiscount,
    required this.minOrderAmount,
    this.code,
    this.imageUrl,
    this.currencySymbol = 'Rs',
    this.currencyDecimals = 2,
    required this.validFrom,
    required this.validUntil,
    required this.isActive,
    this.applicableRestaurantIds,
    this.applicableCategories,
  });

  factory Offer.fromJson(Map<String, dynamic> json) {
    return Offer(
      id: parseIntValue(json['id']),
      title: json['title'] ?? '',
      subtitle: json['subtitle'],
      description: json['description'],
      discountType: json['discount_type'] ?? 'percentage',
      discountValue: parseDoubleValue(json['discount_value']),
      maxDiscount: parseNullableDouble(json['max_discount']),
      minOrderAmount: parseDoubleValue(json['min_order_amount']),
      code: json['code'],
      imageUrl: json['image_url'],
      currencySymbol: (json['currency_symbol']?.toString().trim().isNotEmpty == true)
          ? json['currency_symbol'].toString().trim()
          : 'Rs',
      currencyDecimals: _normalizeCurrencyDecimals(json['currency_decimals']),
      validFrom: DateTime.parse(json['valid_from'] ?? DateTime.now().toIso8601String()),
      validUntil: DateTime.parse(json['valid_until'] ?? DateTime.now().toIso8601String()),
      isActive: parseBoolValue(json['is_active'], true),
      applicableRestaurantIds: json['applicable_restaurant_ids'] is List 
          ? List<int>.from(json['applicable_restaurant_ids'])
          : null,
      applicableCategories: json['applicable_categories'] is List
          ? List<String>.from(json['applicable_categories'])
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'subtitle': subtitle,
      'description': description,
      'discount_type': discountType,
      'discount_value': discountValue,
      'max_discount': maxDiscount,
      'min_order_amount': minOrderAmount,
      'code': code,
      'image_url': imageUrl,
      'currency_symbol': currencySymbol,
      'currency_decimals': currencyDecimals,
      'valid_from': validFrom.toIso8601String(),
      'valid_until': validUntil.toIso8601String(),
      'is_active': isActive,
      'applicable_restaurant_ids': applicableRestaurantIds,
      'applicable_categories': applicableCategories,
    };
  }

  String get displayDiscount {
    if (discountType == 'percentage') {
      return '${discountValue.toStringAsFixed(0)}%';
    } else if (discountType == 'flat') {
      return '$currencySymbol${discountValue.toStringAsFixed(currencyDecimals)}';
    }
    return '$discountValue';
  }

  static int _normalizeCurrencyDecimals(dynamic value) {
    final decimals = value is int ? value : int.tryParse(value?.toString() ?? '');
    return (decimals ?? 2).clamp(2, 5).toInt();
  }
}
