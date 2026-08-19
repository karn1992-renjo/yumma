import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../models/menu_item.dart';
import '../../models/restaurant.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import '../../services/app_image_cache.dart';
import '../../services/location_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/common/app_skeleton.dart';
import '../../widgets/customer/floating_cart_bar.dart';

const _taxonomyText = FoodFlowTheme.ink;
const _taxonomySubtext = FoodFlowTheme.muted;
const _taxonomyLine = FoodFlowTheme.line;
const _taxonomySuccess = FoodFlowTheme.success;
const _taxonomyBg = Colors.white;

Color _taxonomyPrimary(BuildContext context) =>
    FoodFlowTheme.brandPrimary(context);

Color _taxonomySecondary(BuildContext context) =>
    FoodFlowTheme.brandSecondary(context);

enum _TaxonomySort { popular, priceLowHigh, priceHighLow }

enum _VegFilter { all, veg, nonVeg }

enum _PriceBand { all, under99, from99to199, from199to299, above299 }

enum _DeliveryBand { any, under20, under30, under45 }

class MenuTaxonomyFilterScreen extends StatefulWidget {
  const MenuTaxonomyFilterScreen({
    super.key,
    required this.title,
    required this.subtitle,
    required this.filterType,
    this.filterId,
    this.imageUrl,
  });

  final String title;
  final String subtitle;
  final String filterType;
  final int? filterId;
  final String? imageUrl;

  @override
  State<MenuTaxonomyFilterScreen> createState() =>
      _MenuTaxonomyFilterScreenState();
}

class _MenuTaxonomyFilterScreenState extends State<MenuTaxonomyFilterScreen> {
  final ApiService _api = ApiService();
  final LocationService _locationService = LocationService();
  final TextEditingController _searchController = TextEditingController();

  TextTheme? _cachedBaseTextTheme;
  TextTheme? _cachedPoppinsTextTheme;

  TextTheme _poppinsTextTheme(TextTheme base) {
    if (identical(_cachedBaseTextTheme, base) &&
        _cachedPoppinsTextTheme != null) {
      return _cachedPoppinsTextTheme!;
    }
    _cachedBaseTextTheme = base;
    _cachedPoppinsTextTheme = GoogleFonts.poppinsTextTheme(base);
    return _cachedPoppinsTextTheme!;
  }

  bool _isLoading = true;
  String? _error;
  String _searchQuery = '';
  bool _searchVisible = false;
  _TaxonomySort _sort = _TaxonomySort.popular;
  _VegFilter _vegFilter = _VegFilter.all;
  _PriceBand _priceBand = _PriceBand.all;
  _DeliveryBand _deliveryBand = _DeliveryBand.any;
  List<_FilteredMenuHit> _items = const <_FilteredMenuHit>[];
  final Map<int, List<_FilteredMenuHit>> _menuHitsByRestaurant = {};

  @override
  void initState() {
    super.initState();
    _loadItems();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadItems({bool forceRefresh = false}) async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final savedLocation = await _locationService.getSavedLocation();
      final lat = savedLocation?['lat'];
      final lng = savedLocation?['lng'];
      if (lat is! num || lng is! num) {
        throw Exception('Select a delivery location to view menu items.');
      }

      final response = await _api
          .get(
            ApiConstants.nearbyRestaurants,
            queryParams: {
              'lat': lat.toDouble(),
              'lng': lng.toDouble(),
              'radius': 100,
            },
            includeAuth: false,
            cachePolicy: ApiCachePolicy.discovery,
            cacheFirst: !forceRefresh,
            refreshCached: !forceRefresh,
          )
          .timeout(const Duration(seconds: 15));

      final restaurants = _extractRestaurantMaps(response)
          .map((json) {
            try {
              return Restaurant.fromJson(json);
            } catch (_) {
              return null;
            }
          })
          .whereType<Restaurant>()
          .toList(growable: false);

      final seen = <String>{};
      final hits = <_FilteredMenuHit>[];
      final grouped = await Future.wait(
        restaurants.take(24).map((restaurant) => _loadRestaurantMenuItems(
              restaurant,
              forceRefresh: forceRefresh,
            )),
      );

      for (final restaurantItems in grouped) {
        for (final hit in restaurantItems) {
          if (hit.item.finalPrice <= 0) continue;
          if (!_matchesTaxonomy(hit)) continue;
          if (seen.add('${hit.restaurant.id}:${hit.item.id}')) {
            hits.add(hit);
          }
        }
      }

      hits.sort((a, b) {
        final orderCompare = b.item.totalOrders.compareTo(a.item.totalOrders);
        if (orderCompare != 0) return orderCompare;
        return a.item.finalPrice.compareTo(b.item.finalPrice);
      });

      if (!mounted) return;
      setState(() {
        _items = hits;
        _isLoading = false;
      });
      unawaited(AppImageCache.precacheVisible(
        context,
        hits.take(12).map((hit) => hit.item.imageUrl).toList(growable: false),
      ));
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString().replaceFirst('Exception: ', '');
        _items = const <_FilteredMenuHit>[];
        _isLoading = false;
      });
    }
  }

  Future<List<_FilteredMenuHit>> _loadRestaurantMenuItems(
    Restaurant restaurant, {
    bool forceRefresh = false,
  }) async {
    if (!forceRefresh && _menuHitsByRestaurant.containsKey(restaurant.id)) {
      return _menuHitsByRestaurant[restaurant.id]!;
    }

    try {
      final response = await _api
          .get(
            '${ApiConstants.restaurantDetails}/${restaurant.id}/menu',
            includeAuth: false,
            cachePolicy: ApiCachePolicy.discovery,
            cacheFirst: !forceRefresh,
            refreshCached: false,
          )
          .timeout(const Duration(seconds: 10));
      final payload = response is Map
          ? Map<String, dynamic>.from(response)
          : <String, dynamic>{'data': response};
      final hits = _parseMenuItems(_extractMenuItemMaps(payload), restaurant.id)
          .where((item) => item.isAvailable)
          .map((item) => _FilteredMenuHit(restaurant: restaurant, item: item))
          .toList(growable: false);
      _menuHitsByRestaurant[restaurant.id] = hits;
      return hits;
    } catch (_) {
      if (!forceRefresh && _menuHitsByRestaurant.containsKey(restaurant.id)) {
        return _menuHitsByRestaurant[restaurant.id]!;
      }
      return const <_FilteredMenuHit>[];
    }
  }

  List<Map<String, dynamic>> _extractRestaurantMaps(dynamic response) {
    final lists = <dynamic>[];
    if (response is List) lists.add(response);
    if (response is Map) {
      lists.addAll([
        response['data'],
        response['restaurants'],
        response['items'],
        response['results'],
      ]);
      final data = response['data'];
      if (data is Map) {
        lists.addAll([
          data['data'],
          data['restaurants'],
          data['items'],
          data['results'],
        ]);
      }
    }
    return lists
        .whereType<List>()
        .expand((list) => list)
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
  }

  List<Map<String, dynamic>> _extractMenuItemMaps(
    Map<String, dynamic> payload,
  ) {
    final lists = <dynamic>[
      payload['data'],
      payload['menu_items'],
      payload['menuItems'],
      payload['items'],
      payload['menu'],
      payload['dishes'],
      payload['categories'],
    ];
    final data = payload['data'];
    if (data is Map) {
      lists.addAll([
        data['data'],
        data['menu_items'],
        data['menuItems'],
        data['items'],
        data['menu'],
        data['dishes'],
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
      final hasIdentity = map['id'] != null && map['name'] != null;
      final hasMenuFields = map.containsKey('price') ||
          map.containsKey('discounted_price') ||
          map.containsKey('final_price') ||
          map.containsKey('restaurant_id') ||
          map.containsKey('image') ||
          map.containsKey('image_url') ||
          map.containsKey('images') ||
          map.containsKey('is_veg');
      if (hasIdentity && hasMenuFields) {
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

  List<MenuItem> _parseMenuItems(
    List<Map<String, dynamic>> rawItems,
    int restaurantId,
  ) {
    final items = <MenuItem>[];
    for (final rawItem in rawItems) {
      try {
        final json = <String, dynamic>{
          ...rawItem,
          if (rawItem['id'] == null && rawItem['entity_id'] != null)
            'id': rawItem['entity_id'],
          if (rawItem['name'] == null && rawItem['title'] != null)
            'name': rawItem['title'],
          if (rawItem['price'] == null) 'price': 0,
          if (rawItem['images'] == null) 'images': const <dynamic>[],
          if (rawItem['is_available'] == null) 'is_available': true,
          if (rawItem['restaurant_id'] == null) 'restaurant_id': restaurantId,
          if (rawItem['created_at'] == null)
            'created_at': DateTime.now().toIso8601String(),
        };
        final item = MenuItem.fromJson(json);
        if (item.name.trim().isNotEmpty) items.add(item);
      } catch (_) {}
    }
    return items;
  }

  bool _matchesTaxonomy(_FilteredMenuHit hit) {
    final filterId = widget.filterId;
    final title = widget.title;
    final filterType = widget.filterType.trim().toLowerCase();
    final hasFilterId = filterId != null && filterId > 0;
    final isCuisineFilter = filterType.contains('cuisine');
    final isSubcategoryFilter = filterType.contains('subcategory') ||
        filterType.contains('sub_category');
    final isCategoryFilter = !isSubcategoryFilter &&
        (filterType.contains('category') || filterType.trim().isEmpty);

    if (isCuisineFilter) {
      if (hasFilterId) return hit.item.cuisineId == filterId;
      return _matchesTaxonomyLabel(hit.item.cuisineName ?? '', title);
    }

    if (isSubcategoryFilter) {
      if (hasFilterId) return hit.item.subcategoryId == filterId;
      return _matchesTaxonomyLabel(hit.item.subcategoryName ?? '', title);
    }

    if (isCategoryFilter) {
      if (hasFilterId) return hit.item.categoryId == filterId;
      return _matchesTaxonomyLabel(hit.item.categoryName ?? '', title);
    }

    return _matchesTaxonomyLabel(hit.item.categoryName ?? '', title) ||
        _matchesTaxonomyLabel(hit.item.subcategoryName ?? '', title) ||
        _matchesTaxonomyLabel(hit.item.cuisineName ?? '', title);
  }

  bool _matchesTaxonomyLabel(String value, String filter) {
    final normalizedValue = _normalize(value);
    final normalizedFilter = _normalize(filter);
    if (normalizedFilter.isEmpty || normalizedValue.isEmpty) return false;
    return normalizedValue == normalizedFilter;
  }

  bool _matchesText(String value, String filter) {
    final normalizedValue = _normalize(value);
    final normalizedFilter = _normalize(filter);
    if (normalizedFilter.isEmpty) return false;
    return normalizedValue.contains(normalizedFilter);
  }

  String _normalize(String value) {
    return value.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), ' ').trim();
  }

  String get _taxonomyTag {
    final type = widget.filterType.trim().toLowerCase();
    if (type.contains('cuisine')) return 'Cuisine';
    if (type.contains('subcategory') || type.contains('sub_category')) {
      return 'Subcategory';
    }
    if (type.contains('category')) return 'Category';
    return 'Menu';
  }

  List<_FilteredMenuHit> get _visibleItems {
    final query = _searchQuery.trim();
    var hits = query.isEmpty
        ? List<_FilteredMenuHit>.from(_items)
        : _items.where((hit) {
            return _matchesText(hit.item.name, query) ||
                _matchesText(hit.item.description ?? '', query) ||
                _matchesText(hit.item.categoryName ?? '', query) ||
                _matchesText(hit.item.subcategoryName ?? '', query) ||
                _matchesText(hit.item.cuisineName ?? '', query) ||
                _matchesText(hit.restaurant.name, query);
          }).toList(growable: true);

    if (_vegFilter != _VegFilter.all) {
      final wantsVeg = _vegFilter == _VegFilter.veg;
      hits = hits.where((hit) => hit.item.isVeg == wantsVeg).toList();
    }

    if (_priceBand != _PriceBand.all) {
      hits = hits
          .where((hit) => _matchesPriceBand(hit.item.finalPrice, _priceBand))
          .toList();
    }

    if (_deliveryBand != _DeliveryBand.any) {
      hits = hits
          .where((hit) =>
              _matchesDeliveryBand(hit.restaurant.deliveryTime, _deliveryBand))
          .toList();
    }

    switch (_sort) {
      case _TaxonomySort.priceLowHigh:
        hits.sort((a, b) => a.item.finalPrice.compareTo(b.item.finalPrice));
        break;
      case _TaxonomySort.priceHighLow:
        hits.sort((a, b) => b.item.finalPrice.compareTo(a.item.finalPrice));
        break;
      case _TaxonomySort.popular:
        hits.sort((a, b) {
          final orderCompare = b.item.totalOrders.compareTo(a.item.totalOrders);
          if (orderCompare != 0) return orderCompare;
          return a.item.finalPrice.compareTo(b.item.finalPrice);
        });
        break;
    }

    return hits;
  }

  bool _matchesPriceBand(double price, _PriceBand band) {
    switch (band) {
      case _PriceBand.all:
        return true;
      case _PriceBand.under99:
        return price < 99;
      case _PriceBand.from99to199:
        return price >= 99 && price < 199;
      case _PriceBand.from199to299:
        return price >= 199 && price < 299;
      case _PriceBand.above299:
        return price >= 299;
    }
  }

  bool _matchesDeliveryBand(int deliveryMinutes, _DeliveryBand band) {
    switch (band) {
      case _DeliveryBand.any:
        return true;
      case _DeliveryBand.under20:
        return deliveryMinutes < 20;
      case _DeliveryBand.under30:
        return deliveryMinutes < 30;
      case _DeliveryBand.under45:
        return deliveryMinutes < 45;
    }
  }

  String get _sortLabel {
    switch (_sort) {
      case _TaxonomySort.popular:
        return 'Sort';
      case _TaxonomySort.priceLowHigh:
        return 'Price: Low to High';
      case _TaxonomySort.priceHighLow:
        return 'Price: High to Low';
    }
  }

  String get _vegLabel {
    switch (_vegFilter) {
      case _VegFilter.all:
        return 'Veg/Non-Veg';
      case _VegFilter.veg:
        return 'Veg';
      case _VegFilter.nonVeg:
        return 'Non Veg';
    }
  }

  String get _priceLabel {
    switch (_priceBand) {
      case _PriceBand.all:
        return 'Price';
      case _PriceBand.under99:
        return 'Under ₹99';
      case _PriceBand.from99to199:
        return '₹99 - ₹199';
      case _PriceBand.from199to299:
        return '₹199 - ₹299';
      case _PriceBand.above299:
        return 'Above ₹299';
    }
  }

  String get _deliveryLabel {
    switch (_deliveryBand) {
      case _DeliveryBand.any:
        return 'Delivery time';
      case _DeliveryBand.under20:
        return 'Under 20 mins';
      case _DeliveryBand.under30:
        return 'Under 30 mins';
      case _DeliveryBand.under45:
        return 'Under 45 mins';
    }
  }

  Future<void> _showSortSheet() async {
    var next = _sort;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return _SimpleFilterSheet(
              title: 'Sort',
              onClear: () => setSheetState(() => next = _TaxonomySort.popular),
              onApply: () {
                setState(() => _sort = next);
                Navigator.pop(sheetContext);
              },
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _PopupRadioRow(
                    title: 'Recommended',
                    subtitle: 'Popular items first',
                    selected: next == _TaxonomySort.popular,
                    onTap: () =>
                        setSheetState(() => next = _TaxonomySort.popular),
                  ),
                  _PopupRadioRow(
                    title: 'Price Low to High',
                    subtitle: 'Lowest priced items first',
                    selected: next == _TaxonomySort.priceLowHigh,
                    onTap: () =>
                        setSheetState(() => next = _TaxonomySort.priceLowHigh),
                  ),
                  _PopupRadioRow(
                    title: 'Price High to Low',
                    subtitle: 'Highest priced items first',
                    selected: next == _TaxonomySort.priceHighLow,
                    onTap: () =>
                        setSheetState(() => next = _TaxonomySort.priceHighLow),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _showVegSheet() async {
    var next = _vegFilter;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return _SimpleFilterSheet(
              title: 'Veg/Non-Veg',
              onClear: () => setSheetState(() => next = _VegFilter.all),
              onApply: () {
                setState(() => _vegFilter = next);
                Navigator.pop(sheetContext);
              },
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _PopupRadioRow(
                    title: 'Veg',
                    selected: next == _VegFilter.veg,
                    onTap: () => setSheetState(() => next = _VegFilter.veg),
                  ),
                  _PopupRadioRow(
                    title: 'Non Veg',
                    selected: next == _VegFilter.nonVeg,
                    onTap: () => setSheetState(() => next = _VegFilter.nonVeg),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _showPriceSheet() async {
    var next = _priceBand;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return _SimpleFilterSheet(
              title: 'Price',
              onClear: () => setSheetState(() => next = _PriceBand.all),
              onApply: () {
                setState(() => _priceBand = next);
                Navigator.pop(sheetContext);
              },
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _PopupRadioRow(
                    title: 'Under ₹99',
                    selected: next == _PriceBand.under99,
                    onTap: () => setSheetState(() => next = _PriceBand.under99),
                  ),
                  _PopupRadioRow(
                    title: '₹99 - ₹199',
                    selected: next == _PriceBand.from99to199,
                    onTap: () =>
                        setSheetState(() => next = _PriceBand.from99to199),
                  ),
                  _PopupRadioRow(
                    title: '₹199 - ₹299',
                    selected: next == _PriceBand.from199to299,
                    onTap: () =>
                        setSheetState(() => next = _PriceBand.from199to299),
                  ),
                  _PopupRadioRow(
                    title: 'Above ₹299',
                    selected: next == _PriceBand.above299,
                    onTap: () =>
                        setSheetState(() => next = _PriceBand.above299),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _showDeliverySheet() async {
    var next = _deliveryBand;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return _SimpleFilterSheet(
              title: 'Delivery time',
              onClear: () => setSheetState(() => next = _DeliveryBand.any),
              onApply: () {
                setState(() => _deliveryBand = next);
                Navigator.pop(sheetContext);
              },
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _PopupRadioRow(
                    title: 'less than 20 minutes',
                    selected: next == _DeliveryBand.under20,
                    onTap: () =>
                        setSheetState(() => next = _DeliveryBand.under20),
                  ),
                  _PopupRadioRow(
                    title: 'less than 30 minutes',
                    selected: next == _DeliveryBand.under30,
                    onTap: () =>
                        setSheetState(() => next = _DeliveryBand.under30),
                  ),
                  _PopupRadioRow(
                    title: 'less than 45 minutes',
                    selected: next == _DeliveryBand.under45,
                    onTap: () =>
                        setSheetState(() => next = _DeliveryBand.under45),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final topInset = MediaQuery.paddingOf(context).top;
    final title =
        widget.title.trim().isEmpty ? 'Menu Items' : widget.title.trim();
    final visibleItems = _visibleItems;

    return Theme(
      data: Theme.of(context).copyWith(
        textTheme: _poppinsTextTheme(Theme.of(context).textTheme),
      ),
      child: Scaffold(
        backgroundColor: _taxonomyBg,
        bottomNavigationBar: const CustomerFloatingCartBar(),
        body: RefreshIndicator(
          onRefresh: () => _loadItems(forceRefresh: true),
          child: CustomScrollView(
            physics: const BouncingScrollPhysics(
              parent: AlwaysScrollableScrollPhysics(),
            ),
            slivers: [
              SliverToBoxAdapter(
                child: _TaxonomyHeroBanner(
                  title: title,
                  topInset: topInset,
                  imageUrl: widget.imageUrl,
                  onBack: () => Navigator.maybePop(context),
                  onSearchTap: () => setState(() {
                    _searchVisible = !_searchVisible;
                    if (!_searchVisible) {
                      _searchController.clear();
                      _searchQuery = '';
                    }
                  }),
                ),
              ),
              if (_searchVisible)
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
                  sliver: SliverToBoxAdapter(
                    child: _TaxonomySearchBar(
                      controller: _searchController,
                      query: _searchQuery,
                      hintText: 'Search inside $title',
                      onChanged: (value) =>
                          setState(() => _searchQuery = value),
                      onClear: () {
                        _searchController.clear();
                        setState(() => _searchQuery = '');
                      },
                    ),
                  ),
                ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
                sliver: SliverToBoxAdapter(
                  child: SizedBox(
                    height: 40,
                    child: ListView(
                      scrollDirection: Axis.horizontal,
                      children: [
                        _FilterPill(
                          label: _sortLabel,
                          active: _sort != _TaxonomySort.popular,
                          onTap: _showSortSheet,
                        ),
                        const SizedBox(width: 10),
                        _FilterPill(
                          label: _vegLabel,
                          active: _vegFilter != _VegFilter.all,
                          onTap: _showVegSheet,
                        ),
                        const SizedBox(width: 10),
                        _FilterPill(
                          label: _priceLabel,
                          active: _priceBand != _PriceBand.all,
                          onTap: _showPriceSheet,
                        ),
                        const SizedBox(width: 10),
                        _FilterPill(
                          label: _deliveryLabel,
                          active: _deliveryBand != _DeliveryBand.any,
                          onTap: _showDeliverySheet,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              if (!_isLoading && _error == null && _items.isNotEmpty)
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(18, 16, 18, 4),
                  sliver: SliverToBoxAdapter(
                    child: Text(
                      'All ${visibleItems.length} items',
                      style: const TextStyle(
                        color: _taxonomyText,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
              if (_isLoading)
                const AppSkeletonSliverList(
                  itemCount: 5,
                  itemHeight: 112,
                )
              else if (_error != null)
                SliverFillRemaining(
                  child: _EmptyState(
                    title: 'Unable to load items',
                    message: _error!,
                    onRetry: () => _loadItems(forceRefresh: true),
                  ),
                )
              else if (_items.isEmpty)
                SliverFillRemaining(
                  child: _EmptyState(
                    title: 'No items found',
                    message:
                        'No menu items are available for this ${_taxonomyTag.toLowerCase()} right now.',
                    onRetry: () => _loadItems(forceRefresh: true),
                  ),
                )
              else if (visibleItems.isEmpty)
                SliverFillRemaining(
                  child: _EmptyState(
                    title: 'No matching items',
                    message: 'Try another search inside $title.',
                    onRetry: () {
                      _searchController.clear();
                      setState(() => _searchQuery = '');
                    },
                  ),
                )
              else
                SliverPadding(
                  padding: EdgeInsets.fromLTRB(
                    14,
                    4,
                    14,
                    104 + MediaQuery.paddingOf(context).bottom,
                  ),
                  sliver: SliverGrid.builder(
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount:
                          MediaQuery.sizeOf(context).width >= 720 ? 3 : 2,
                      mainAxisSpacing: 16,
                      crossAxisSpacing: 12,
                      childAspectRatio: 0.74,
                    ),
                    itemCount: visibleItems.length,
                    itemBuilder: (context, index) => _FilteredMenuCard(
                      hit: visibleItems[index],
                      onOpen: () => _openItemDetails(visibleItems[index]),
                      onQuantityChanged: (quantity) =>
                          _setCartQuantity(visibleItems[index], quantity),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  void _openItemDetails(_FilteredMenuHit hit) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => Theme(
        data: Theme.of(sheetContext).copyWith(
          textTheme:
              GoogleFonts.poppinsTextTheme(Theme.of(sheetContext).textTheme),
        ),
        child: _MenuItemDetailSheet(
          hit: hit,
          onQuantityChanged: (quantity) => _setCartQuantity(hit, quantity),
        ),
      ),
    );
  }

  void _setCartQuantity(_FilteredMenuHit hit, int quantity) {
    context.read<CartProvider>().setItemQuantity(
          hit.item,
          hit.restaurant,
          quantity,
        );
  }
}

class _FilteredMenuHit {
  const _FilteredMenuHit({
    required this.restaurant,
    required this.item,
  });

  final Restaurant restaurant;
  final MenuItem item;
}

String _titleCase(String value) {
  final trimmed = value.trim();
  if (trimmed.isEmpty) return trimmed;
  return trimmed.split(RegExp(r'\s+')).map((word) {
    if (word.isEmpty) return word;
    final lower = word.toLowerCase();
    return lower[0].toUpperCase() + lower.substring(1);
  }).join(' ');
}

class _TaxonomyHeroBanner extends StatelessWidget {
  const _TaxonomyHeroBanner({
    required this.title,
    required this.topInset,
    required this.onBack,
    required this.onSearchTap,
    this.imageUrl,
  });

  final String title;
  final double topInset;
  final VoidCallback onBack;
  final VoidCallback onSearchTap;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final hasImage = imageUrl != null && imageUrl!.trim().isNotEmpty;
    final primary = _taxonomyPrimary(context);
    final secondary = _taxonomySecondary(context);

    return SizedBox(
      height: 210 + topInset,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (hasImage)
            AppCachedImage(
              imageUrl: AppImageCache.resolveUrl(imageUrl!),
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [primary, secondary],
                  ),
                ),
              ),
            )
          else
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [primary, secondary],
                ),
              ),
            ),
          DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Colors.black.withOpacity(0.32),
                  Colors.black.withOpacity(0.12),
                  Colors.black.withOpacity(0.38),
                ],
              ),
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(16, topInset + 10, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    _CircleIconButton(
                      icon: Icons.arrow_back_rounded,
                      onTap: onBack,
                    ),
                    const Spacer(),
                    _CircleIconButton(
                      icon: Icons.search_rounded,
                      onTap: onSearchTap,
                    ),
                  ],
                ),
                const Spacer(),
                Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    height: 1.05,
                    fontWeight: FontWeight.w900,
                    shadows: [
                      Shadow(
                        color: Colors.black45,
                        blurRadius: 10,
                        offset: Offset(0, 2),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CircleIconButton extends StatelessWidget {
  const _CircleIconButton({
    required this.icon,
    required this.onTap,
  });

  final IconData icon;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: const CircleBorder(),
      elevation: 10,
      shadowColor: Colors.black.withOpacity(0.16),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(icon, color: _taxonomyText, size: 22),
        ),
      ),
    );
  }
}

class _FloatingTaxonomyIcon extends StatelessWidget {
  const _FloatingTaxonomyIcon({
    required this.icon,
    required this.color,
  });

  final IconData icon;
  final Color color;
  static const double size = 54;
  static const double iconSize = 28;
  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.18),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Icon(icon, color: color, size: iconSize),
    );
  }
}

class _FilterPill extends StatelessWidget {
  const _FilterPill({
    required this.label,
    required this.active,
    required this.onTap,
  });

  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final primary = _taxonomyPrimary(context);
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          height: 40,
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            color: active ? primary.withOpacity(0.08) : Colors.white,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: active ? primary : _taxonomyLine,
              width: active ? 1.4 : 1,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: active ? primary : _taxonomyText,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(width: 4),
              Icon(
                Icons.keyboard_arrow_down_rounded,
                color: active ? primary : _taxonomySubtext,
                size: 18,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SimpleFilterSheet extends StatelessWidget {
  const _SimpleFilterSheet({
    required this.title,
    required this.onClear,
    required this.onApply,
    required this.child,
  });

  final String title;
  final VoidCallback onClear;
  final VoidCallback onApply;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.paddingOf(context).bottom;
    final secondary = _taxonomySecondary(context);
    return SafeArea(
      top: false,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.72,
        ),
        child: DecoratedBox(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(22, 18, 18, 18),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        title,
                        style: const TextStyle(
                          color: _taxonomyText,
                          fontSize: 22,
                          height: 1.05,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    InkWell(
                      onTap: () => Navigator.pop(context),
                      borderRadius: BorderRadius.circular(999),
                      child: Container(
                        width: 28,
                        height: 28,
                        decoration: const BoxDecoration(
                          color: Color(0xFFE1E2E5),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.close_rounded,
                          color: Colors.white,
                          size: 20,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1, color: _taxonomyLine),
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(22, 20, 22, 8),
                  child: child,
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(20, 12, 20, 18 + bottom),
                child: Column(
                  children: [
                    SizedBox(
                      width: double.infinity,
                      height: 54,
                      child: ElevatedButton(
                        onPressed: onApply,
                        style: FoodFlowTheme.zomatoPrimaryButton(
                          color: secondary,
                          foregroundColor: Colors.white,
                          radius: 14,
                        ),
                        child: const Text('Apply'),
                      ),
                    ),
                    const SizedBox(height: 6),
                    TextButton(
                      onPressed: onClear,
                      style: TextButton.styleFrom(
                        foregroundColor: secondary,
                        textStyle: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      child: const Text('Clear Filters'),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PopupRadioRow extends StatelessWidget {
  const _PopupRadioRow({
    required this.title,
    required this.selected,
    required this.onTap,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final primary = _taxonomyPrimary(context);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.only(bottom: 24),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 22,
              height: 22,
              margin: const EdgeInsets.only(top: 1),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: selected ? primary : const Color(0xFF777777),
                  width: 1.7,
                ),
              ),
              alignment: Alignment.center,
              child: selected
                  ? Container(
                      width: 10,
                      height: 10,
                      decoration: BoxDecoration(
                        color: primary,
                        shape: BoxShape.circle,
                      ),
                    )
                  : null,
            ),
            const SizedBox(width: 20),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: _taxonomyText,
                      fontSize: 16,
                      height: 1.15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Text(
                      subtitle!,
                      style: const TextStyle(
                        color: _taxonomySubtext,
                        fontSize: 13,
                        height: 1.2,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FilteredMenuCard extends StatelessWidget {
  const _FilteredMenuCard({
    required this.hit,
    required this.onOpen,
    required this.onQuantityChanged,
  });

  final _FilteredMenuHit hit;
  final VoidCallback onOpen;
  final ValueChanged<int> onQuantityChanged;
  @override
  Widget build(BuildContext context) {
    final item = hit.item;
    final quantity = _quantityForHit(context.watch<CartProvider>(), hit);
    final isPopular =
        item.isBestseller || item.isRecommended || item.totalOrders >= 50;
    final rating = item.rating;
    final hasRating = rating != null && rating > 0;

    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: AspectRatio(
              aspectRatio: 1.15,
              child: Stack(
                clipBehavior: Clip.none,
                fit: StackFit.expand,
                children: [
                  item.imageUrl.isNotEmpty
                      ? AppCachedImage(
                          imageUrl: item.imageUrl,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) =>
                              const _MenuImageFallback(),
                        )
                      : const _MenuImageFallback(),
                  const _MenuCardImageHighlight(),
                  if (isPopular)
                    const Positioned(
                      left: 8,
                      top: 8,
                      child: _PopularBadge(),
                    ),
                  if (hasRating)
                    Positioned(
                      left: 8,
                      bottom: 8,
                      child: _RatingBadge(rating: rating),
                    ),
                  Positioned(
                    right: 8,
                    bottom: 8,
                    child: _CardAddButton(
                      quantity: quantity,
                      enabled: item.isAvailable,
                      onChanged: onQuantityChanged,
                    ),
                  ),
                ],
              ),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(2, 8, 2, 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    hit.restaurant.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF6B7280),
                      fontSize: 11.5,
                      height: 1.15,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.only(top: 3),
                        child: _VegMarker(isVeg: item.isVeg),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          _titleCase(item.name),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _taxonomyText,
                            fontSize: 14,
                            height: 1.2,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      if (item.hasDiscount) ...[
                        Text(
                          formatCurrency(context, item.price),
                          style: const TextStyle(
                            color: FoodFlowTheme.faint,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                        const SizedBox(width: 6),
                      ],
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 9,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: _taxonomySecondary(context)
                              .withOpacity(item.isAvailable ? 1 : 0.4),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          formatCurrency(context, item.finalPrice),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MenuItemDetailSheet extends StatelessWidget {
  const _MenuItemDetailSheet({
    required this.hit,
    required this.onQuantityChanged,
  });

  final _FilteredMenuHit hit;
  final ValueChanged<int> onQuantityChanged;

  @override
  Widget build(BuildContext context) {
    final item = hit.item;
    final quantity = _quantityForHit(context.watch<CartProvider>(), hit);
    final isPopular =
        item.isBestseller || item.isRecommended || item.totalOrders >= 50;
    final rating = item.rating;
    final hasRating = rating != null && rating > 0;
    final description = item.description?.trim();

    return SafeArea(
      top: false,
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.86,
        ),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        clipBehavior: Clip.antiAlias,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  AspectRatio(
                    aspectRatio: 1.3,
                    child: item.imageUrl.isNotEmpty
                        ? AppCachedImage(
                            imageUrl: item.imageUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) =>
                                const _MenuImageFallback(),
                          )
                        : const _MenuImageFallback(),
                  ),
                  if (isPopular)
                    const Positioned(
                      left: 14,
                      top: 14,
                      child: _PopularBadge(),
                    ),
                  Positioned(
                    left: 0,
                    right: 0,
                    top: -18,
                    child: Center(
                      child: _SheetCloseButton(
                        onTap: () => Navigator.of(context).pop(),
                      ),
                    ),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 22, 20, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Padding(
                                    padding: const EdgeInsets.only(top: 4),
                                    child: _VegMarker(isVeg: item.isVeg),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      _titleCase(item.name),
                                      style: const TextStyle(
                                        color: _taxonomyText,
                                        fontSize: 19,
                                        height: 1.2,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 5),
                              Text(
                                hit.restaurant.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Color(0xFF6B7280),
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 12),
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                children: [
                                  if (item.hasDiscount) ...[
                                    Text(
                                      formatCurrency(context, item.price),
                                      style: const TextStyle(
                                        color: FoodFlowTheme.faint,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                        decoration: TextDecoration.lineThrough,
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                  ],
                                  Text(
                                    formatCurrency(context, item.finalPrice),
                                    style: const TextStyle(
                                      color: _taxonomyText,
                                      fontSize: 18,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ],
                              ),
                              if (hasRating) ...[
                                const SizedBox(height: 8),
                                Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      Icons.star_rounded,
                                      color: FoodFlowTheme.tagGreenDark,
                                      size: 17,
                                    ),
                                    const SizedBox(width: 4),
                                    Text(
                                      rating.toStringAsFixed(1),
                                      style: const TextStyle(
                                        color: _taxonomyText,
                                        fontSize: 13.5,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(width: 14),
                        _SheetAddButton(
                          quantity: quantity,
                          enabled: item.isAvailable,
                          onChanged: onQuantityChanged,
                        ),
                      ],
                    ),
                    if (description != null && description.isNotEmpty) ...[
                      const SizedBox(height: 18),
                      Text(
                        description,
                        style: GoogleFonts.plusJakartaSans(
                          color: _taxonomySubtext,
                          fontSize: 13.5,
                          height: 1.55,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SheetCloseButton extends StatelessWidget {
  const _SheetCloseButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.black.withOpacity(0.55),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: const SizedBox(
          width: 36,
          height: 36,
          child: Icon(Icons.close_rounded, color: Colors.white, size: 20),
        ),
      ),
    );
  }
}

class _SheetAddButton extends StatelessWidget {
  const _SheetAddButton({
    required this.quantity,
    required this.enabled,
    required this.onChanged,
  });

  final int quantity;
  final bool enabled;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final isActive = quantity > 0;
    final primary = _taxonomyPrimary(context);
    final secondary = _taxonomySecondary(context);
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: enabled
              ? [primary, secondary]
              : const [FoodFlowTheme.disabledFill, FoodFlowTheme.disabledText],
        ),
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(enabled ? 0.2 : 0.08),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: SizedBox(
        height: 40,
        child: isActive
            ? Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _QuantityTapZone(
                    icon: Icons.remove_rounded,
                    enabled: enabled,
                    onTap: () => onChanged(quantity - 1),
                  ),
                  SizedBox(
                    width: 26,
                    child: Text(
                      '$quantity',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  _QuantityTapZone(
                    icon: Icons.add_rounded,
                    enabled: enabled,
                    onTap: () => onChanged(quantity + 1),
                  ),
                ],
              )
            : InkWell(
                onTap: enabled ? () => onChanged(1) : null,
                borderRadius: BorderRadius.circular(999),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 22),
                  child: Center(
                    child: Text(
                      'ADD',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.4,
                      ),
                    ),
                  ),
                ),
              ),
      ),
    );
  }
}

class _PopularBadge extends StatelessWidget {
  const _PopularBadge();
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: FoodFlowTheme.tagGreenDark,
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.14),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: const Text(
        'Popular',
        style: TextStyle(
          color: Colors.white,
          fontSize: 10.5,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _RatingBadge extends StatelessWidget {
  const _RatingBadge({required this.rating});

  final double rating;
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: FoodFlowTheme.tagGreenDark,
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.14),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.star_rounded, color: Colors.white, size: 12),
          const SizedBox(width: 3),
          Text(
            rating.toStringAsFixed(1),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _CardAddButton extends StatelessWidget {
  const _CardAddButton({
    required this.quantity,
    required this.enabled,
    required this.onChanged,
  });

  final int quantity;
  final bool enabled;
  final ValueChanged<int> onChanged;
  @override
  Widget build(BuildContext context) {
    final isActive = quantity > 0;
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: enabled
              ? [_taxonomyPrimary(context), _taxonomySecondary(context)]
              : const [FoodFlowTheme.disabledFill, FoodFlowTheme.disabledText],
        ),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white, width: 3),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(enabled ? 0.22 : 0.1),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: isActive
          ? SizedBox(
              height: 34,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _QuantityTapZone(
                    icon: Icons.remove_rounded,
                    enabled: enabled,
                    onTap: () => onChanged(quantity - 1),
                  ),
                  SizedBox(
                    width: 22,
                    child: Text(
                      '$quantity',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  _QuantityTapZone(
                    icon: Icons.add_rounded,
                    enabled: enabled,
                    onTap: () => onChanged(quantity + 1),
                  ),
                ],
              ),
            )
          : InkWell(
              onTap: enabled ? () => onChanged(1) : null,
              borderRadius: BorderRadius.circular(999),
              child: const SizedBox(
                width: 34,
                height: 34,
                child: Icon(Icons.add_rounded, color: Colors.white, size: 20),
              ),
            ),
    );
  }
}

class _QuantityTapZone extends StatelessWidget {
  const _QuantityTapZone({
    required this.icon,
    required this.enabled,
    required this.onTap,
  });

  final IconData icon;
  final bool enabled;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: enabled ? onTap : null,
      child: SizedBox(
        width: 25,
        height: 34,
        child: Icon(icon, color: Colors.white, size: 15),
      ),
    );
  }
}

class _MenuCardImageHighlight extends StatelessWidget {
  const _MenuCardImageHighlight();
  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Colors.white.withOpacity(0.16),
              Colors.transparent,
              Colors.black.withOpacity(0.07),
            ],
            stops: const [0, 0.48, 1],
          ),
        ),
      ),
    );
  }
}

int _quantityForHit(CartProvider cart, _FilteredMenuHit hit) {
  var quantity = 0;
  final normalizedName = hit.item.name.trim().toLowerCase();

  for (final restaurantCart in cart.carts) {
    if (restaurantCart.restaurant.id != hit.restaurant.id) continue;
    for (final cartItem in restaurantCart.items) {
      final menuItem = cartItem.menuItem;
      final sameId = menuItem.id == hit.item.id;
      final sameName = menuItem.restaurantId == hit.item.restaurantId &&
          menuItem.name.trim().toLowerCase() == normalizedName;
      if (sameId || sameName) {
        quantity += cartItem.quantity;
      }
    }
  }

  if (quantity > 0 || cart.restaurant?.id != hit.restaurant.id) {
    return quantity;
  }

  for (final cartItem in cart.items) {
    final menuItem = cartItem.menuItem;
    final sameId = menuItem.id == hit.item.id;
    final sameName = menuItem.restaurantId == hit.item.restaurantId &&
        menuItem.name.trim().toLowerCase() == normalizedName;
    if (sameId || sameName) {
      quantity += cartItem.quantity;
    }
  }

  return quantity;
}

class _TaxonomySearchBar extends StatelessWidget {
  const _TaxonomySearchBar({
    required this.controller,
    required this.query,
    required this.hintText,
    required this.onChanged,
    required this.onClear,
  });

  final TextEditingController controller;
  final String query;
  final String hintText;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;
  @override
  Widget build(BuildContext context) {
    return Container(
      height: 52,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: _taxonomyPanelDecoration(context, radius: 18),
      child: Row(
        children: [
          Icon(
            Icons.search_rounded,
            color: _taxonomyPrimary(context),
            size: 21,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: TextField(
              controller: controller,
              onChanged: onChanged,
              textInputAction: TextInputAction.search,
              style: const TextStyle(
                color: _taxonomyText,
                fontSize: 14,
                fontWeight: FontWeight.w800,
              ),
              decoration: InputDecoration(
                hintText: hintText,
                hintStyle: const TextStyle(
                  color: FoodFlowTheme.faint,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w700,
                ),
                border: InputBorder.none,
                isDense: true,
              ),
            ),
          ),
          if (query.trim().isNotEmpty)
            IconButton(
              visualDensity: VisualDensity.compact,
              onPressed: onClear,
              icon: const Icon(
                Icons.close_rounded,
                color: _taxonomySubtext,
                size: 19,
              ),
            ),
        ],
      ),
    );
  }
}

class _VegMarker extends StatelessWidget {
  const _VegMarker({required this.isVeg});

  final bool isVeg;
  @override
  Widget build(BuildContext context) {
    final color = isVeg ? FoodFlowTheme.success : FoodFlowTheme.danger;
    return Container(
      width: 12,
      height: 12,
      decoration: BoxDecoration(
        border: Border.all(color: color, width: 1.4),
      ),
      alignment: Alignment.center,
      child: Container(
        width: 5,
        height: 5,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      ),
    );
  }
}

class _MenuImageFallback extends StatelessWidget {
  const _MenuImageFallback();
  @override
  Widget build(BuildContext context) {
    final primary = _taxonomyPrimary(context);
    return Container(
      color: primary.withOpacity(0.08),
      alignment: Alignment.center,
      child: Icon(
        Icons.restaurant_menu_rounded,
        color: primary.withOpacity(0.55),
        size: 36,
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.title,
    required this.message,
    required this.onRetry,
  });

  final String title;
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 22),
          decoration: _taxonomyPanelDecoration(context, radius: 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _FloatingTaxonomyIcon(
                icon: Icons.search_off_rounded,
                color: _taxonomyPrimary(context),
              ),
              const SizedBox(height: 14),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: _taxonomyText,
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: _taxonomySubtext,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 18),
              DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      _taxonomyPrimary(context),
                      _taxonomySecondary(context),
                    ],
                  ),
                  borderRadius: BorderRadius.circular(15),
                  boxShadow: [
                    BoxShadow(
                      color: _taxonomyPrimary(context).withOpacity(0.24),
                      blurRadius: 18,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: SizedBox(
                  height: 46,
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: onRetry,
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.transparent,
                      foregroundColor: Colors.white,
                      shadowColor: Colors.transparent,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(15),
                      ),
                    ),
                    child: const Text(
                      'Retry',
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

BoxDecoration _taxonomyPanelDecoration(BuildContext context,
    {double radius = 28}) {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: _taxonomyLine),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withOpacity(0.06),
        blurRadius: 22,
        spreadRadius: -4,
        offset: const Offset(0, 14),
      ),
    ],
  );
}
