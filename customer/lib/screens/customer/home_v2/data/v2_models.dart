import 'v2_util.dart';

class SectionV2 {
  SectionV2({
    required this.type,
    required this.title,
    required this.subtitle,
    required this.clientFeed,
    required this.items,
  });

  final String type;
  final String? title;
  final String? subtitle;
  final bool clientFeed;
  final List<Map<String, dynamic>> items;

  static const Set<String> restaurantTypes = {
    'nearby_restaurants',
    'restaurant_discovery',
    'popular_restaurants',
    'new_arrivals',
    'trending_near_you',
    'featured_restaurants',
    'restaurant_grid',
    'recommended_for_you',
  };

  bool get isPromotion =>
      type == 'deals_for_you' || type == 'promotion_type_section';

  bool get needsRestaurantFeed {
    if (!restaurantTypes.contains(type)) return false;
    return clientFeed || items.isEmpty;
  }

  factory SectionV2.fromJson(Map<String, dynamic> json) {
    final raw = json['items'];
    return SectionV2(
      type: json['type']?.toString() ?? '',
      title: json['title']?.toString(),
      subtitle: json['subtitle']?.toString(),
      clientFeed: json['client_feed'] == true,
      items: raw is List
          ? raw
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList(growable: false)
          : const <Map<String, dynamic>>[],
    );
  }
}

class DishV2 {
  DishV2({
    required this.id,
    required this.name,
    required this.imageUrl,
    required this.price,
    required this.restaurantId,
    required this.restaurantName,
    required this.isVeg,
  });

  final int id;
  final String name;
  final String imageUrl;
  final double price;
  final int restaurantId;
  final String restaurantName;
  final bool isVeg;

  static DishV2? tryFrom(Map<String, dynamic> item) {
    final name = (item['name'] ?? item['title'] ?? '').toString().trim();
    if (name.isEmpty) return null;
    final hasDishSignal = item.containsKey('price') ||
        item.containsKey('discounted_price') ||
        item.containsKey('restaurant_id') ||
        item.containsKey('master_menu_item_id') ||
        item['restaurant'] is Map;
    if (!hasDishSignal) return null;

    final nested = item['restaurant'];
    final rMap = nested is Map ? Map<String, dynamic>.from(nested) : const {};
    return DishV2(
      id: v2Int(item['id'] ??
          item['menu_item_id'] ??
          item['master_menu_item_id']),
      name: name,
      imageUrl: v2ImageUrl(item, kDishImageKeys),
      price: v2Double(item['discounted_price'] ??
          item['price'] ??
          item['min_price'] ??
          item['base_price']),
      restaurantId: v2Int(item['restaurant_id'] ??
          item['restaurantId'] ??
          rMap['id'] ??
          rMap['restaurant_id']),
      restaurantName: (item['restaurant_name'] ??
              item['restaurantName'] ??
              rMap['name'] ??
              '')
          .toString(),
      isVeg: v2Bool(item['is_veg'] ?? item['veg'], fallback: true),
    );
  }
}

class V2HomeData {
  V2HomeData({
    required this.sections,
    required this.cuisines,
    required this.priceFilter,
    required this.banners,
  });

  final List<SectionV2> sections;
  final List<Map<String, dynamic>> cuisines;
  final Map<String, dynamic> priceFilter;
  final List<Map<String, dynamic>> banners;
}
