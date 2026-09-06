// Defensive JSON helpers shared by the V2 data layer + cards.

import 'package:flutter/widgets.dart';

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import '../../../../services/app_image_cache.dart';
import '../../../../utils/currency_utils.dart';

/// Fire-and-forget CPC ad-click tracking for a sponsored restaurant, matching
/// the production home screen's `POST /ads/clicks`.
void v2TrackAdClick(Map<String, dynamic> data, {String surface = 'home'}) {
  final sponsored = v2Bool(data['is_sponsored'] ?? data['isSponsored']);
  final campaignId = v2Int(data['ad_campaign_id'] ?? data['adCampaignId']);
  if (!sponsored || campaignId <= 0) return;
  ApiService()
      .post(ApiConstants.adClicks,
          data: {'campaign_id': campaignId, 'surface': surface})
      .catchError((_) => null);
}

bool v2IsSponsored(Map<String, dynamic> data) =>
    v2Bool(data['is_sponsored'] ?? data['isSponsored']);

/// The admin-configured "free delivery over ₹X" threshold. Learned from
/// `/orders/summary` (which returns `free_delivery_threshold`) and reused on
/// restaurant cards in place of the per-order delivery fee.
double? v2FreeDeliveryThreshold;

void setV2FreeDeliveryThreshold(dynamic value) {
  final v = v2DoubleOrNull(value);
  if (v != null && v > 0) v2FreeDeliveryThreshold = v;
}

/// "Free delivery" text for a restaurant card: a per-restaurant threshold if
/// the payload carries one, else the global admin threshold, else null.
String? v2FreeDeliveryText(BuildContext context, Map<String, dynamic> data) {
  final perRestaurant = v2DoubleOrNull(data['free_delivery_threshold'] ??
      data['free_delivery_min_order'] ??
      data['min_order_free_delivery'] ??
      data['free_delivery_above']);
  final threshold =
      (perRestaurant != null && perRestaurant > 0) ? perRestaurant : v2FreeDeliveryThreshold;
  if (threshold == null || threshold <= 0) return null;
  return 'Free delivery over ${formatCurrency(context, threshold)}';
}

int v2Int(dynamic v, {int fallback = 0}) {
  if (v is int) return v;
  if (v is double) return v.toInt();
  if (v is String) {
    return int.tryParse(v) ?? double.tryParse(v)?.toInt() ?? fallback;
  }
  return fallback;
}

double? v2DoubleOrNull(dynamic v) {
  if (v is double) return v;
  if (v is int) return v.toDouble();
  if (v is String) return double.tryParse(v);
  return null;
}

double v2Double(dynamic v, {double fallback = 0}) => v2DoubleOrNull(v) ?? fallback;

bool v2Bool(dynamic v, {bool fallback = false}) {
  if (v is bool) return v;
  if (v is int) return v != 0;
  if (v is String) {
    final s = v.trim().toLowerCase();
    return s == 'true' || s == '1' || s == 'yes' || s == 'y';
  }
  return fallback;
}

List<dynamic> v2ExtractList(dynamic response) {
  if (response is List) return response;
  if (response is Map) {
    final data = response['data'];
    if (data is List) return data;
    if (data is Map) {
      for (final k in const ['data', 'items', 'sections', 'restaurants', 'results']) {
        if (data[k] is List) return data[k] as List;
      }
    }
    for (final k in const [
      'items',
      'sections',
      'restaurants',
      'results',
      'categories',
      'banners',
      'offers',
      'menu',
    ]) {
      if (response[k] is List) return response[k] as List;
    }
  }
  return const <dynamic>[];
}

Map<String, dynamic> v2ExtractMap(dynamic response) {
  if (response is Map) {
    final data = response['data'];
    if (data is Map) return Map<String, dynamic>.from(data);
    return Map<String, dynamic>.from(response);
  }
  return const <String, dynamic>{};
}

List<Map<String, dynamic>> v2MapList(dynamic value) {
  if (value is! List) return const <Map<String, dynamic>>[];
  return value
      .whereType<Map>()
      .map((e) => Map<String, dynamic>.from(e))
      .toList(growable: false);
}

int v2RestaurantId(Map<String, dynamic> r) => v2Int(r['id'] ??
    r['restaurant_id'] ??
    r['restaurantId'] ??
    (r['restaurant'] is Map ? (r['restaurant'] as Map)['id'] : null));

double v2RatingOf(Map<String, dynamic> r) => v2Double(r['rating'] ??
    r['avg_rating'] ??
    r['average_rating'] ??
    r['review_rating']);

bool v2IsOpen(Map<String, dynamic> r) {
  final v = r['is_open'] ?? r['is_open_now'] ?? r['open'] ?? r['isOpen'];
  if (v == null) return true;
  return v2Bool(v, fallback: true);
}

bool v2LooksVeg(Map<String, dynamic> m) =>
    v2Bool(m['is_pure_veg'] ?? m['pure_veg'] ?? m['is_veg']);

String v2EtaText(Map<String, dynamic> r) {
  final mins = v2Int(r['delivery_time'] ??
      r['deliveryTime'] ??
      r['eta'] ??
      r['preparation_time']);
  return mins <= 0 ? '' : '$mins min';
}

String v2CuisineText(Map<String, dynamic> r) {
  List<String> namesFrom(dynamic value) {
    if (value is String) {
      return value
          .split(',')
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty && int.tryParse(e) == null)
          .toList();
    }
    if (value is List) {
      return value
          .map((e) {
            if (e is Map) {
              return (e['name'] ?? e['title'] ?? e['cuisine_name'] ?? '')
                  .toString()
                  .trim();
            }
            final t = e?.toString().trim() ?? '';
            return int.tryParse(t) == null ? t : '';
          })
          .where((e) => e.isNotEmpty)
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
    final v = namesFrom(r[key]);
    if (v.isNotEmpty) return v.join(', ');
  }
  return '';
}

String v2ImageUrl(Map<String, dynamic> item, List<String> keys) {
  for (final key in keys) {
    final value = item[key];
    if (value is String && value.trim().isNotEmpty) {
      return AppImageCache.resolveUrl(value.trim());
    }
    if (value is Map) {
      final nested = value['url'] ?? value['src'] ?? value['path'];
      if (nested is String && nested.trim().isNotEmpty) {
        return AppImageCache.resolveUrl(nested.trim());
      }
    }
  }
  return '';
}

const List<String> kRestaurantImageKeys = [
  'banner_image',
  'image_url',
  'image',
  'logo_image',
  'cover_image',
  'photo',
];

const List<String> kDishImageKeys = [
  'image_url',
  'image',
  'photo',
  'thumbnail',
  'banner_image',
];
