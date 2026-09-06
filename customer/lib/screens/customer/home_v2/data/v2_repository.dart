// All network access for the V2 experience lives here, so screens stay
// presentational. Endpoints match what the production home uses, so the same
// admin "Home Sections" layout drives both.

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import '../../../../services/location_service.dart';
import 'v2_models.dart';
import 'v2_util.dart';

class V2Repository {
  V2Repository._();
  static final V2Repository instance = V2Repository._();

  final ApiService _api = ApiService();
  final LocationService _location = LocationService();

  Future<({double? lat, double? lng})> savedLatLng() async {
    final saved = await _location.getSavedLocation();
    return (lat: v2DoubleOrNull(saved?['lat']), lng: v2DoubleOrNull(saved?['lng']));
  }

  Future<dynamic> _get(String url, {Map<String, dynamic>? query, bool auth = false}) {
    return _api
        .get(
          url,
          queryParams: query,
          includeAuth: auth,
          cacheResponse: true,
          cacheFirst: true,
        )
        .catchError((_) => null);
  }

  Future<V2HomeData> loadHome() async {
    final loc = await savedLatLng();
    final results = await Future.wait<dynamic>([
      _get(ApiConstants.homeSections, query: {
        'platform': 'app',
        if (loc.lat != null && loc.lng != null) 'lat': loc.lat,
        if (loc.lat != null && loc.lng != null) 'lng': loc.lng,
        if (loc.lat != null && loc.lng != null) 'radius': 100,
      }),
      _get(ApiConstants.popularCuisines),
      _get(ApiConstants.banners, query: {'type': 'home', 'platform': 'app'}),
    ]);

    final rawSections = v2ExtractList(results[0])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .where((e) => e['enabled'] != false)
        .toList(growable: false);

    var priceFilter = <String, dynamic>{};
    for (final s in rawSections) {
      if (s['type']?.toString() == 'menu_price_filter_config') {
        final items = s['items'];
        priceFilter = (items is List && items.isNotEmpty && items.first is Map)
            ? Map<String, dynamic>.from(items.first as Map)
            : Map<String, dynamic>.from(s);
      }
    }

    final banners = v2MapList(v2ExtractList(results[2]))
        .where((b) {
          final surface = (b['display_surface'] ?? 'both')
              .toString()
              .toLowerCase();
          return surface == 'both' || surface == 'app' || surface == 'home';
        })
        .toList(growable: false);

    return V2HomeData(
      sections: rawSections.map(SectionV2.fromJson).toList(),
      cuisines: v2MapList(v2ExtractList(results[1])),
      priceFilter: priceFilter,
      banners: banners,
    );
  }

  Future<({List<Map<String, dynamic>> nearby, List<Map<String, dynamic>> offers})>
      loadDeferred({required bool needNearby, required bool needOffers}) async {
    final loc = await savedLatLng();
    final res = await Future.wait<dynamic>([
      (needNearby && loc.lat != null && loc.lng != null)
          ? _get(ApiConstants.nearbyRestaurants,
              query: {'lat': loc.lat, 'lng': loc.lng, 'radius': 100})
          : Future<dynamic>.value(null),
      needOffers
          ? _get(ApiConstants.activeOffers)
          : Future<dynamic>.value(null),
    ]);
    return (
      nearby: v2MapList(v2ExtractList(res[0])),
      offers: v2MapList(v2ExtractList(res[1])),
    );
  }

  Future<List<Map<String, dynamic>>> nearbyRestaurants() async {
    final loc = await savedLatLng();
    if (loc.lat == null || loc.lng == null) return const [];
    final res = await _get(ApiConstants.nearbyRestaurants,
        query: {'lat': loc.lat, 'lng': loc.lng, 'radius': 100});
    return v2MapList(v2ExtractList(res));
  }

  Future<Map<String, dynamic>> restaurant(int id) async {
    final res = await _get('${ApiConstants.restaurantDetails}/$id');
    return v2ExtractMap(res);
  }

  /// Active offers / promotions attached to a restaurant (authed).
  Future<List<Map<String, dynamic>>> restaurantPromos(int id) async {
    final res = await _api
        .get(ApiConstants.customerRestaurantPromos(id))
        .catchError((_) => null);
    final list = v2ExtractList(res);
    return v2MapList(list);
  }

  Future<List<Map<String, dynamic>>> restaurantMenu(int id) async {
    // Same endpoint the production detail screen uses.
    final res = await _get('${ApiConstants.restaurantDetails}/$id/menu');
    final data = res is Map && res['data'] is Map
        ? Map<String, dynamic>.from(res['data'] as Map)
        : (res is Map ? Map<String, dynamic>.from(res) : const <String, dynamic>{});
    final items = data['menu_items'] ??
        data['items'] ??
        data['menu'] ??
        (res is Map ? res['menu_items'] : null);
    return v2MapList(items is List ? items : v2ExtractList(res));
  }

  Future<({List<Map<String, dynamic>> restaurants, List<Map<String, dynamic>> dishes})>
      search(String query) async {
    final loc = await savedLatLng();
    // Run the universal search (dishes + some restaurants) AND the
    // restaurant-only search together, then merge — the universal index rarely
    // returns restaurants for a dish-name query, which is what V1 does too.
    final results = await Future.wait<dynamic>([
      _get(ApiConstants.universalSearch, query: {
        'q': query,
        'query': query,
        'keyword': query,
        if (loc.lat != null) 'lat': loc.lat,
        if (loc.lng != null) 'lng': loc.lng,
      }),
      _get(ApiConstants.searchRestaurants, query: {
        'q': query,
        'query': query,
        if (loc.lat != null) 'lat': loc.lat,
        if (loc.lng != null) 'lng': loc.lng,
      }),
    ]);

    final restaurants = <Map<String, dynamic>>[];
    final dishes = <Map<String, dynamic>>[];
    final seenR = <int>{};

    void addRestaurants(Iterable<Map<String, dynamic>> list) {
      for (final r in list) {
        final id = v2RestaurantId(r);
        if (id > 0 && seenR.add(id)) restaurants.add(r);
      }
    }

    final uni = (results[0] is Map ? results[0]['data'] : null);
    if (uni is Map) {
      addRestaurants(v2MapList(uni['restaurants']));
      dishes.addAll(v2MapList(
          uni['foods'] ?? uni['dishes'] ?? uni['menu_items']));
    }

    final r = results[1];
    if (r is Map) {
      final rd = r['data'];
      addRestaurants(v2MapList(
          (rd is Map ? rd['restaurants'] : null) ??
              r['restaurants'] ??
              (rd is List ? rd : null)));
      dishes.addAll(v2MapList(
          rd is Map ? rd['dishes'] ?? rd['menu_items'] : null));
      if (restaurants.isEmpty && dishes.isEmpty) {
        for (final m in v2MapList(v2ExtractList(r))) {
          if (m.containsKey('price') || m['restaurant'] is Map) {
            dishes.add(m);
          } else {
            addRestaurants([m]);
          }
        }
      }
    }
    return (restaurants: restaurants, dishes: dishes);
  }

  Future<List<Map<String, dynamic>>> taxonomyResults({
    required String filterType,
    int? filterId,
    String? label,
  }) async {
    final loc = await savedLatLng();
    final res = await _get(ApiConstants.nearbyRestaurants, query: {
      if (loc.lat != null) 'lat': loc.lat,
      if (loc.lng != null) 'lng': loc.lng,
      'radius': 100,
      if (filterId != null) '${filterType}_id': filterId,
      if (label != null) filterType: label,
    });
    return v2MapList(v2ExtractList(res));
  }

  /// Menu items across nearby restaurants that match a cuisine / category, like
  /// the production taxonomy screen. Each row carries a nested `restaurant`.
  Future<List<Map<String, dynamic>>> taxonomyDishes({
    required String filterType,
    int? filterId,
    String? label,
  }) async {
    final restaurants = await nearbyRestaurants();
    if (restaurants.isEmpty) return const [];

    final isCuisine = filterType.contains('cuisine');
    final isSub =
        filterType.contains('subcategory') || filterType.contains('sub_category');
    final needle = (label ?? '').toLowerCase().trim();
    bool labelHit(String? v) {
      if (needle.isEmpty || v == null) return false;
      final s = v.toLowerCase();
      return s == needle || s.contains(needle) || needle.contains(s);
    }

    final menus = await Future.wait(
      restaurants.take(20).map((r) async {
        final id = v2RestaurantId(r);
        if (id <= 0) return const <Map<String, dynamic>>[];
        try {
          final items = await restaurantMenu(id);
          final slim = <String, dynamic>{
            'id': id,
            'name': r['name'],
            'logo_image': r['logo_image'] ?? r['banner_image'] ?? r['image'],
            'delivery_fee': r['delivery_fee'] ?? 0,
            'rating': v2RatingOf(r),
            'delivery_time': r['delivery_time'],
          };
          return items
              .where((it) {
                if (filterId != null && filterId > 0) {
                  final key = isCuisine
                      ? 'cuisine_id'
                      : isSub
                          ? 'subcategory_id'
                          : 'category_id';
                  if (v2Int(it[key]) == filterId) return true;
                }
                return labelHit(it['cuisine_name']?.toString()) ||
                    labelHit(it['category_name']?.toString()) ||
                    labelHit(it['subcategory_name']?.toString());
              })
              .map((it) => {...it, 'restaurant': slim, 'restaurant_id': id})
              .toList();
        } catch (_) {
          return const <Map<String, dynamic>>[];
        }
      }),
    );

    final seen = <String>{};
    final out = <Map<String, dynamic>>[];
    for (final list in menus) {
      for (final it in list) {
        final key = '${it['restaurant_id']}:${it['id']}';
        if (v2Double(it['discounted_price'] ?? it['price']) <= 0) continue;
        if (seen.add(key)) out.add(it);
      }
    }
    out.sort((a, b) {
      final o = v2Int(b['total_orders']).compareTo(v2Int(a['total_orders']));
      if (o != 0) return o;
      return v2Double(a['price']).compareTo(v2Double(b['price']));
    });
    return out;
  }
}
