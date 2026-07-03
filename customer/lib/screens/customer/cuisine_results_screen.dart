import 'dart:async';

import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';

import '../../config/api_constants.dart';
import '../../models/menu_item.dart';
import '../../models/restaurant.dart';
import '../../services/api_service.dart';
import '../../services/location_service.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/search_result_card.dart';

class CuisineResultsScreen extends StatefulWidget {
  const CuisineResultsScreen({
    super.key,
    required this.title,
    this.cuisineId,
  });

  final String title;
  final int? cuisineId;

  @override
  State<CuisineResultsScreen> createState() => _CuisineResultsScreenState();
}

class _CuisineResultsScreenState extends State<CuisineResultsScreen> {
  final ApiService _api = ApiService();
  final LocationService _locationService = LocationService();

  bool _isLoading = true;
  bool _showItems = true;
  String? _error;
  List<Restaurant> _restaurants = [];
  List<_CuisineMenuHit> _items = [];

  @override
  void initState() {
    super.initState();
    unawaited(_loadResults());
  }

  Future<void> _loadResults() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final location = await _locationService.getSavedLocation();
      final lat = _parseDouble(location?['lat']);
      final lng = _parseDouble(location?['lng']);

      final response = await _api.get(
        ApiConstants.searchRestaurants,
        queryParams: <String, dynamic>{
          'q': widget.title,
          'query': widget.title,
          'cuisine': widget.title,
          if (widget.cuisineId != null) ...{
            'cuisine_id': widget.cuisineId,
            'type': 'cuisine',
          } else ...{
            'query': widget.title,
            'type': 'category',
          },
          if (lat != null && lng != null) ...{
            'delivery_zone_only': true,
            'lat': lat,
            'lng': lng,
          },
          'radius': 15,
        },
      ).timeout(const Duration(seconds: 15));

      final restaurantMaps = _extractRestaurantMaps(response);
      final restaurants =
          restaurantMaps.map(Restaurant.fromJson).toList(growable: false);
      final itemHits = <_CuisineMenuHit>[
        ..._itemHitsFromSearchPayload(restaurantMaps, restaurants),
        ...await _loadMatchingItems(restaurants),
      ];
      final seenHits = <String>{};
      final uniqueItemHits = itemHits
          .where((hit) => seenHits.add('${hit.restaurant.id}:${hit.item.id}'))
          .toList(growable: false)
        ..sort((a, b) => b.item.totalOrders.compareTo(a.item.totalOrders));
      final uniqueRestaurants = _mergeUniqueRestaurants(restaurants, [
        for (final hit in uniqueItemHits) hit.restaurant,
      ]);

      if (!mounted) return;
      setState(() {
        _restaurants = uniqueRestaurants;
        _items = uniqueItemHits;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Unable to load ${widget.title} right now.';
        _isLoading = false;
      });
    }
  }

  Future<List<_CuisineMenuHit>> _loadMatchingItems(
    List<Restaurant> restaurants,
  ) async {
    final results = await Future.wait(
      restaurants.map((restaurant) async {
        try {
          final response = await _api
              .get('${ApiConstants.restaurantDetails}/${restaurant.id}/menu')
              .timeout(const Duration(seconds: 6));
          final data = response['data'] is Map<String, dynamic>
              ? response['data'] as Map<String, dynamic>
              : response;
          final rawItems = _extractMenuItemMaps(data);

          final availableItems = rawItems
              .map(MenuItem.fromJson)
              .where((item) => item.isAvailable)
              .toList(growable: false);
          final matchedItems = availableItems
              .where((item) => _menuItemBelongsToCuisine(
                    item,
                    restaurant: restaurant,
                  ))
              .toList(growable: false);

          return matchedItems
              .map(
                  (item) => _CuisineMenuHit(restaurant: restaurant, item: item))
              .toList(growable: false);
        } catch (_) {
          return const <_CuisineMenuHit>[];
        }
      }),
    );

    final seen = <String>{};
    return results
        .expand((items) => items)
        .where((hit) => seen.add('${hit.restaurant.id}:${hit.item.id}'))
        .toList(growable: false)
      ..sort((a, b) => b.item.totalOrders.compareTo(a.item.totalOrders));
  }

  List<_CuisineMenuHit> _itemHitsFromSearchPayload(
    List<Map<String, dynamic>> restaurantPayloads,
    List<Restaurant> restaurants,
  ) {
    final restaurantsById = {
      for (final restaurant in restaurants) restaurant.id: restaurant,
    };
    final hits = <_CuisineMenuHit>[];

    for (final payload in restaurantPayloads) {
      final restaurantId = payload['id'] is int
          ? payload['id'] as int
          : int.tryParse(payload['id']?.toString() ?? '') ?? 0;
      final restaurant = restaurantsById[restaurantId];
      final rawItems = _extractMenuItemMaps(payload);
      if (restaurant == null || rawItems.isEmpty) continue;

      for (final rawItem in rawItems) {
        try {
          final item = MenuItem.fromJson(rawItem);
          if (item.isAvailable &&
              _menuItemBelongsToCuisine(item, restaurant: restaurant)) {
            hits.add(_CuisineMenuHit(restaurant: restaurant, item: item));
          }
        } catch (_) {}
      }
    }

    return hits;
  }

  List<Map<String, dynamic>> _extractMenuItemMaps(
      Map<String, dynamic> payload) {
    final lists = <dynamic>[
      payload['data'],
      payload['matched_menu_items'],
      payload['matchedMenuItems'],
      payload['menu_items'],
      payload['menuItems'],
      payload['items'],
      payload['menu'],
      payload['dishes'],
      payload['matched_items'],
      payload['food_items'],
      payload['foodItems'],
      payload['categories'],
    ];

    final data = payload['data'];
    if (data is Map) {
      lists.addAll([
        data['data'],
        data['matched_menu_items'],
        data['matchedMenuItems'],
        data['menu_items'],
        data['menuItems'],
        data['items'],
        data['menu'],
        data['dishes'],
        data['foodItems'],
        data['categories'],
      ]);
    }

    final results = <Map<String, dynamic>>[];

    void collect(dynamic value) {
      if (value is List) {
        for (final item in value) {
          collect(item);
        }
        return;
      }
      if (value is! Map) return;

      final map = Map<String, dynamic>.from(value);
      final hasItemIdentity = map['id'] != null && map['name'] != null;
      final hasMenuFields = map.containsKey('price') ||
          map.containsKey('discounted_price') ||
          map.containsKey('final_price') ||
          map.containsKey('restaurant_id') ||
          map.containsKey('description') ||
          map.containsKey('images') ||
          map.containsKey('image') ||
          map.containsKey('image_url') ||
          map.containsKey('is_veg') ||
          map.containsKey('food_type');
      final looksLikeMenuItem = hasItemIdentity && hasMenuFields;
      if (looksLikeMenuItem && map['name'] != null) {
        results.add(map);
      }

      for (final key in const [
        'menu_items',
        'menuItems',
        'items',
        'menu',
        'dishes',
        'food_items',
        'foodItems',
      ]) {
        collect(map[key]);
      }
    }

    for (final list in lists) {
      collect(list);
    }

    final seen = <String>{};
    return results
        .where((item) =>
            seen.add('${item['restaurant_id']}:${item['id']}:${item['name']}'))
        .toList(growable: false);
  }

  List<Map<String, dynamic>> _extractRestaurantMaps(dynamic response) {
    if (response is List) {
      return response
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false);
    }
    if (response is! Map) return const <Map<String, dynamic>>[];

    for (final candidate in <dynamic>[
      response['data'],
      response['restaurants'],
      response['results'],
      response['items'],
    ]) {
      if (candidate is List) {
        return candidate
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList(growable: false);
      }
      if (candidate is Map) {
        for (final key in const ['data', 'restaurants', 'results', 'items']) {
          final nested = candidate[key];
          if (nested is List) {
            return nested
                .whereType<Map>()
                .map((item) => Map<String, dynamic>.from(item))
                .toList(growable: false);
          }
        }
      }
    }
    return const <Map<String, dynamic>>[];
  }

  List<Restaurant> _mergeUniqueRestaurants(
    List<Restaurant> primary,
    List<Restaurant> secondary,
  ) {
    final seen = <int>{};
    return <Restaurant>[
      ...primary,
      ...secondary,
    ].where((restaurant) => seen.add(restaurant.id)).toList(growable: false);
  }

  bool _menuItemBelongsToCuisine(MenuItem item, {Restaurant? restaurant}) {
    if (widget.cuisineId != null && item.cuisineId == widget.cuisineId) {
      return true;
    }

    final normalizedQuery = _normalize(widget.title);
    final itemHasCuisineInfo = item.cuisineId != null ||
        (item.cuisineName?.trim().isNotEmpty ?? false) ||
        (item.categoryName?.trim().isNotEmpty ?? false);
    final itemMatches =
        _normalize(item.categoryName ?? '').contains(normalizedQuery) ||
            _normalize(item.cuisineName ?? '').contains(normalizedQuery);
    if (itemMatches) return true;

    if (!itemHasCuisineInfo && restaurant != null) {
      return _normalize(restaurant.cuisineText).contains(normalizedQuery);
    }

    return false;
  }

  bool _restaurantMapBelongsToCuisine(Map<String, dynamic> restaurant) {
    final cuisineId = widget.cuisineId;
    final normalizedQuery = _normalize(widget.title);

    bool matchesValue(dynamic value) {
      if (value == null) return false;
      if (cuisineId != null && value.toString() == cuisineId.toString()) {
        return true;
      }
      return _normalize(value.toString()).contains(normalizedQuery);
    }

    bool matchesCollection(dynamic value) {
      if (value is List) {
        return value.any((item) {
          if (item is Map) {
            return matchesValue(item['id']) ||
                matchesValue(item['cuisine_id']) ||
                matchesValue(item['name']) ||
                matchesValue(item['title']) ||
                matchesValue(item['cuisine_name']) ||
                matchesValue(item['slug']);
          }
          return matchesValue(item);
        });
      }
      if (value is String) {
        return value.split(',').any(matchesValue) || matchesValue(value);
      }
      return matchesValue(value);
    }

    for (final key in const [
      'cuisine_ids',
      'cuisine_id',
      'cuisine_text',
      'cuisine_names',
      'cuisines',
      'cuisine',
    ]) {
      if (matchesCollection(restaurant[key])) {
        return true;
      }
    }

    return _extractMenuItemMaps(restaurant).any((item) {
      if (cuisineId != null &&
          (item['cuisine_id']?.toString() == cuisineId.toString() ||
              item['cuisine'] is Map &&
                  (item['cuisine'] as Map)['id']?.toString() ==
                      cuisineId.toString())) {
        return true;
      }
      return matchesValue(item['cuisine_name']) ||
          matchesValue(item['category_name']) ||
          (item['cuisine'] is Map &&
              (matchesValue((item['cuisine'] as Map)['name']) ||
                  matchesValue((item['cuisine'] as Map)['slug'])));
    });
  }

  String _normalize(String value) {
    return value.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), ' ').trim();
  }

  double? _parseDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final activeCount = _showItems ? _items.length : _restaurants.length;

    return Scaffold(
      backgroundColor: const Color(0xFFFAFAFA),
      appBar: AppBar(
        title: Text(widget.title),
        centerTitle: false,
      ),
      body: RefreshIndicator(
        onRefresh: _loadResults,
        color: primary,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
          children: <Widget>[
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Row(
                children: <Widget>[
                  _toggleButton('Items', _items.length, _showItems, () {
                    setState(() => _showItems = true);
                  }),
                  _toggleButton('Restaurants', _restaurants.length, !_showItems,
                      () {
                    setState(() => _showItems = false);
                  }),
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (_isLoading)
              Padding(
                padding: const EdgeInsets.only(top: 120),
                child: Center(
                  child: CircularProgressIndicator(color: primary),
                ),
              )
            else if (_error != null)
              _emptyState(_error!)
            else if (activeCount == 0)
              _emptyState('No data found')
            else if (_showItems)
              ..._items.map(_itemCard)
            else
              ..._restaurants.map(
                (restaurant) => SearchResultCard(
                  restaurant: restaurant,
                  onTap: () => Navigator.pushNamed(
                    context,
                    '/restaurant/detail',
                    arguments: restaurant.id,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _toggleButton(
    String label,
    int count,
    bool active,
    VoidCallback onTap,
  ) {
    final primary = Theme.of(context).colorScheme.primary;
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: active ? primary : Colors.transparent,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Text(
            '$label ($count)',
            style: TextStyle(
              color: active ? Colors.white : const Color(0xFF111827),
              fontWeight: FontWeight.w800,
              fontSize: 13,
            ),
          ),
        ),
      ),
    );
  }

  Widget _itemCard(_CuisineMenuHit hit) {
    final primary = Theme.of(context).colorScheme.primary;
    final imageUrl = hit.item.imageUrl.isNotEmpty
        ? hit.item.imageUrl
        : hit.restaurant.logoUrl;
    return InkWell(
      onTap: () => Navigator.pushNamed(
        context,
        '/restaurant/detail',
        arguments: hit.restaurant.id,
      ),
      borderRadius: BorderRadius.circular(18),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Row(
          children: <Widget>[
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: imageUrl.isNotEmpty
                  ? AppCachedImage(
                      imageUrl: imageUrl,
                      width: 76,
                      height: 76,
                      fit: BoxFit.cover,
                    )
                  : Container(
                      width: 76,
                      height: 76,
                      color: primary.withOpacity(0.08),
                      child: Icon(Icons.fastfood_rounded, color: primary),
                    ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    hit.item.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    hit.restaurant.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF6B7280),
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    formatCurrency(context, hit.item.finalPrice),
                    style: TextStyle(
                      color: primary,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _emptyState(String message) {
    return Padding(
      padding: const EdgeInsets.only(top: 120),
      child: Center(
        child: Text(
          message,
          style: const TextStyle(
            color: Color(0xFF6B7280),
            fontSize: 15,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

class _CuisineMenuHit {
  const _CuisineMenuHit({
    required this.restaurant,
    required this.item,
  });

  final Restaurant restaurant;
  final MenuItem item;
}
