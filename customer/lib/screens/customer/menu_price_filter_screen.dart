import 'dart:async';

import 'package:flutter/material.dart';
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

const _priceFilterText = FoodFlowTheme.ink;
const _priceFilterSubtext = FoodFlowTheme.muted;
const _priceFilterLine = FoodFlowTheme.line;
const _priceFilterSuccess = FoodFlowTheme.success;
const _priceFilterBg = Colors.white;

Color _priceFilterPrimary(BuildContext context) =>
    FoodFlowTheme.brandPrimary(context);

Color _priceFilterSecondary(BuildContext context) =>
    FoodFlowTheme.brandSecondary(context);

enum _PriceSort { lowHigh, highLow }

class MenuPriceFilterScreen extends StatefulWidget {
  const MenuPriceFilterScreen({
    super.key,
    required this.title,
    required this.subtitle,
    this.minPrice,
    this.maxPrice,
  });

  final String title;
  final String subtitle;
  final double? minPrice;
  final double? maxPrice;

  @override
  State<MenuPriceFilterScreen> createState() => _MenuPriceFilterScreenState();
}

class _MenuPriceFilterScreenState extends State<MenuPriceFilterScreen> {
  final ApiService _api = ApiService();
  final LocationService _locationService = LocationService();

  bool _isLoading = true;
  String? _error;
  List<_FilteredMenuHit> _items = const <_FilteredMenuHit>[];
  _PriceSort _sort = _PriceSort.lowHigh;
  String? _selectedCategory;
  final Map<int, List<_FilteredMenuHit>> _menuHitsByRestaurant = {};

  @override
  void initState() {
    super.initState();
    _loadItems();
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
          final price = hit.item.finalPrice;
          if (price <= 0) continue;
          if (widget.minPrice != null && price < widget.minPrice!) continue;
          if (widget.maxPrice != null && price > widget.maxPrice!) continue;
          if (seen.add('${hit.restaurant.id}:${hit.item.id}')) {
            hits.add(hit);
          }
        }
      }

      hits.sort((a, b) {
        final priceCompare = a.item.finalPrice.compareTo(b.item.finalPrice);
        if (priceCompare != 0) return priceCompare;
        return b.item.totalOrders.compareTo(a.item.totalOrders);
      });

      if (!mounted) return;
      setState(() {
        _items = hits;
        if (_selectedCategory != null &&
            !_availableCategories.contains(_selectedCategory)) {
          _selectedCategory = null;
        }
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

  String get _priceTag {
    final min = widget.minPrice;
    final max = widget.maxPrice;
    if (min != null && max != null) {
      return '${formatCurrency(context, min)} - ${formatCurrency(context, max)}';
    }
    if (max != null) return 'Under ${formatCurrency(context, max)}';
    if (min != null) return 'Above ${formatCurrency(context, min)}';
    return 'All prices';
  }

  List<String> get _availableCategories {
    final seen = <String>{};
    final categories = <String>[];
    for (final hit in _items) {
      final label = _categoryLabel(hit.item);
      if (seen.add(label.toLowerCase())) categories.add(label);
    }
    categories.sort((a, b) => a.toLowerCase().compareTo(b.toLowerCase()));
    return categories;
  }

  List<_FilteredMenuHit> get _visibleItems {
    final category = _selectedCategory;
    final hits = _items
        .where(
            (hit) => category == null || _categoryLabel(hit.item) == category)
        .toList(growable: true);

    hits.sort((a, b) {
      final priceCompare = a.item.finalPrice.compareTo(b.item.finalPrice);
      final resolvedPriceCompare =
          _sort == _PriceSort.lowHigh ? priceCompare : -priceCompare;
      if (resolvedPriceCompare != 0) return resolvedPriceCompare;
      return b.item.totalOrders.compareTo(a.item.totalOrders);
    });

    return hits;
  }

  List<_PriceCategoryGroup> get _categoryGroups {
    final grouped = <String, List<_FilteredMenuHit>>{};
    for (final hit in _visibleItems) {
      final label = _categoryLabel(hit.item);
      grouped.putIfAbsent(label, () => <_FilteredMenuHit>[]).add(hit);
    }
    return grouped.entries
        .map((entry) => _PriceCategoryGroup(
              title: entry.key,
              items: entry.value,
            ))
        .toList(growable: false);
  }

  String _categoryLabel(MenuItem item) {
    final category = item.categoryName?.trim();
    if (category != null && category.isNotEmpty) {
      final parts = category
          .split('/')
          .map((part) => part.trim())
          .where((part) => part.isNotEmpty)
          .toList(growable: false);
      if (parts.isNotEmpty) return parts.first;
      return category;
    }

    final subcategory = item.subcategoryName?.trim();
    if (subcategory != null && subcategory.isNotEmpty) return subcategory;

    final cuisine = item.cuisineName?.trim();
    if (cuisine != null && cuisine.isNotEmpty) return cuisine;

    return 'More Items';
  }

  Future<void> _showFilterSheet() async {
    var nextSort = _sort;
    var nextCategory = _selectedCategory;
    var selectedTab = 0;
    final categories = _availableCategories;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            final tabs = <String>[
              'Sort',
              if (categories.isNotEmpty) 'Category'
            ];
            final showingCategory = tabs[selectedTab] == 'Category';

            return _PriceFilterPopup(
              tabs: tabs,
              selectedTab: selectedTab,
              onTabChanged: (index) => setSheetState(() => selectedTab = index),
              onClear: () {
                setSheetState(() {
                  nextSort = _PriceSort.lowHigh;
                  nextCategory = null;
                });
              },
              onApply: () {
                setState(() {
                  _sort = nextSort;
                  _selectedCategory = nextCategory;
                });
                Navigator.pop(sheetContext);
              },
              child: showingCategory
                  ? _PriceCategoryFilterList(
                      categories: categories,
                      selectedCategory: nextCategory,
                      onChanged: (category) =>
                          setSheetState(() => nextCategory = category),
                    )
                  : _PriceSortFilterList(
                      sort: nextSort,
                      onChanged: (sort) => setSheetState(() => nextSort = sort),
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
        widget.title.trim().isEmpty ? 'Filtered items' : widget.title.trim();
    final subtitle = widget.subtitle.trim().isEmpty
        ? 'Menu items matched from restaurants near you'
        : widget.subtitle.trim();
    final visibleItems = _visibleItems;
    final categoryGroups = _categoryGroups;

    return Scaffold(
      backgroundColor: _priceFilterBg,
      bottomNavigationBar: const CustomerFloatingCartBar(),
      body: RefreshIndicator(
        onRefresh: () => _loadItems(forceRefresh: true),
        child: CustomScrollView(
          physics: const BouncingScrollPhysics(
            parent: AlwaysScrollableScrollPhysics(),
          ),
          slivers: [
            SliverToBoxAdapter(
              child: _PriceFilterHero(
                title: title,
                subtitle: subtitle,
                itemCount: visibleItems.length,
                topInset: topInset,
                activeFilterCount: (_sort == _PriceSort.highLow ? 1 : 0) +
                    (_selectedCategory == null ? 0 : 1),
                onFilterTap: _showFilterSheet,
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
                      'No menu items are available in this price range right now.',
                  onRetry: () => _loadItems(forceRefresh: true),
                ),
              )
            else if (visibleItems.isEmpty)
              SliverFillRemaining(
                child: _EmptyState(
                  title: 'No matching items',
                  message: 'Try another category.',
                  onRetry: () => setState(() => _selectedCategory = null),
                ),
              )
            else
              SliverList.separated(
                itemCount: categoryGroups.length,
                separatorBuilder: (_, __) => const SizedBox(height: 20),
                itemBuilder: (context, index) {
                  final group = categoryGroups[index];
                  return _PriceCategorySection(
                    group: group,
                    priceTag: _priceTag,
                    bottomPadding: index == categoryGroups.length - 1
                        ? 104 + MediaQuery.paddingOf(context).bottom
                        : 0,
                    onOpen: (hit) {
                      Navigator.pushNamed(
                        context,
                        '/restaurant/detail',
                        arguments: hit.restaurant.id,
                      );
                    },
                    onQuantityChanged: _setCartQuantity,
                  );
                },
              ),
          ],
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

class _FilterButton extends StatelessWidget {
  const _FilterButton({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final secondary = _priceFilterSecondary(context);
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          height: 40,
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: secondary.withOpacity(0.22)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.05),
                blurRadius: 12,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.tune_rounded, color: secondary, size: 18),
              const SizedBox(width: 7),
              Text(
                label,
                style: TextStyle(
                  color: secondary,
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PriceFilterPopup extends StatelessWidget {
  const _PriceFilterPopup({
    required this.tabs,
    required this.selectedTab,
    required this.onTabChanged,
    required this.onClear,
    required this.onApply,
    required this.child,
  });

  final List<String> tabs;
  final int selectedTab;
  final ValueChanged<int> onTabChanged;
  final VoidCallback onClear;
  final VoidCallback onApply;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.paddingOf(context).bottom;
    final primary = _priceFilterPrimary(context);
    final secondary = _priceFilterSecondary(context);
    return SafeArea(
      top: false,
      child: Container(
        height: MediaQuery.sizeOf(context).height * 0.72,
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(22, 18, 18, 18),
              child: Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Filters',
                      style: TextStyle(
                        color: _priceFilterText,
                        fontSize: 24,
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
            const Divider(height: 1, color: _priceFilterLine),
            Expanded(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SizedBox(
                    width: 102,
                    child: DecoratedBox(
                      decoration: const BoxDecoration(
                        border: Border(
                          right: BorderSide(color: _priceFilterLine),
                        ),
                      ),
                      child: ListView.builder(
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        itemCount: tabs.length,
                        itemBuilder: (context, index) => _FilterTabButton(
                          label: tabs[index],
                          selected: selectedTab == index,
                          color: primary,
                          onTap: () => onTabChanged(index),
                        ),
                      ),
                    ),
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(30, 26, 20, 16),
                      child: child,
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 1, color: _priceFilterLine),
            Padding(
              padding: EdgeInsets.fromLTRB(20, 18, 20, 18 + bottom),
              child: Row(
                children: [
                  Expanded(
                    child: TextButton(
                      onPressed: onClear,
                      style: TextButton.styleFrom(
                        foregroundColor: secondary,
                        textStyle: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      child: const Text('Clear Filter'),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    flex: 2,
                    child: SizedBox(
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
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FilterTabButton extends StatelessWidget {
  const _FilterTabButton({
    required this.label,
    required this.selected,
    required this.color,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        height: 74,
        decoration: BoxDecoration(
          border: Border(
            left: BorderSide(
              color: selected ? color : Colors.transparent,
              width: 5,
            ),
          ),
        ),
        alignment: Alignment.centerLeft,
        padding: const EdgeInsets.only(left: 10, right: 8),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: selected ? color : _priceFilterText,
            fontSize: 13,
            fontWeight: selected ? FontWeight.w900 : FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

class _PriceSortFilterList extends StatelessWidget {
  const _PriceSortFilterList({required this.sort, required this.onChanged});

  final _PriceSort sort;
  final ValueChanged<_PriceSort> onChanged;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _PopupSectionTitle('SORT BY PRICE'),
        const SizedBox(height: 18),
        _PopupRadioRow(
          title: 'Low to High',
          subtitle: 'Lowest priced items first',
          selected: sort == _PriceSort.lowHigh,
          onTap: () => onChanged(_PriceSort.lowHigh),
        ),
        _PopupRadioRow(
          title: 'High to Low',
          subtitle: 'Highest priced items first',
          selected: sort == _PriceSort.highLow,
          onTap: () => onChanged(_PriceSort.highLow),
        ),
      ],
    );
  }
}

class _PriceCategoryFilterList extends StatelessWidget {
  const _PriceCategoryFilterList({
    required this.categories,
    required this.selectedCategory,
    required this.onChanged,
  });

  final List<String> categories;
  final String? selectedCategory;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        const _PopupSectionTitle('FILTER BY CATEGORY'),
        const SizedBox(height: 18),
        _PopupRadioRow(
          title: 'All',
          subtitle: 'Show every category',
          selected: selectedCategory == null,
          onTap: () => onChanged(null),
        ),
        for (final category in categories)
          _PopupRadioRow(
            title: category,
            selected: selectedCategory == category,
            onTap: () => onChanged(category),
          ),
      ],
    );
  }
}

class _PopupSectionTitle extends StatelessWidget {
  const _PopupSectionTitle(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: const TextStyle(
        color: _priceFilterText,
        fontSize: 13,
        letterSpacing: 0,
        fontWeight: FontWeight.w900,
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
    final primary = _priceFilterPrimary(context);
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
                      color: _priceFilterText,
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
                        color: _priceFilterSubtext,
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

class _PriceCategoryGroup {
  const _PriceCategoryGroup({
    required this.title,
    required this.items,
  });

  final String title;
  final List<_FilteredMenuHit> items;
}

class _PriceCategorySection extends StatelessWidget {
  const _PriceCategorySection({
    required this.group,
    required this.priceTag,
    required this.onOpen,
    required this.onQuantityChanged,
    this.bottomPadding = 0,
  });

  final _PriceCategoryGroup group;
  final String priceTag;
  final ValueChanged<_FilteredMenuHit> onOpen;
  final void Function(_FilteredMenuHit hit, int quantity) onQuantityChanged;
  final double bottomPadding;
  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final crossAxisCount = width >= 720 ? 3 : 2;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomPadding),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _PriceCategoryHeader(
            title: group.title,
            subtitle:
                '${group.items.length} item${group.items.length == 1 ? '' : 's'} in $priceTag',
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 0),
            child: GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              padding: EdgeInsets.zero,
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: crossAxisCount,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 0.72,
              ),
              itemCount: group.items.length,
              itemBuilder: (context, index) {
                final hit = group.items[index];
                return _FilteredMenuCard(
                  hit: hit,
                  onOpen: () => onOpen(hit),
                  onQuantityChanged: (quantity) =>
                      onQuantityChanged(hit, quantity),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _PriceCategoryHeader extends StatelessWidget {
  const _PriceCategoryHeader({
    required this.title,
    required this.subtitle,
  });

  final String title;
  final String subtitle;

  InlineSpan _styledTitle() {
    final words =
        title.split(RegExp(r'\s+')).where((word) => word.isNotEmpty).toList();
    if (words.isEmpty) return const TextSpan();

    final highlightedCount = words.length >= 3 ? 2 : 1;
    final splitIndex = words.length - highlightedCount;
    final leading = words.take(splitIndex).join(' ');
    final trailing = words.skip(splitIndex).join(' ');
    return TextSpan(
      children: [
        if (leading.isNotEmpty)
          TextSpan(
            text: '$leading ',
            style: const TextStyle(
              color: _priceFilterText,
              fontSize: 21,
              fontWeight: FontWeight.w800,
            ),
          ),
        TextSpan(
          text: trailing,
          style: const TextStyle(
            color: Color(0xFFFF6B00),
            fontSize: 21,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 30,
            child: RichText(
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              text: TextSpan(children: <InlineSpan>[_styledTitle()]),
            ),
          ),
          if (subtitle.trim().isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: _priceFilterSubtext,
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _PriceFilterHero extends StatelessWidget {
  const _PriceFilterHero({
    required this.title,
    required this.subtitle,
    required this.itemCount,
    required this.topInset,
    required this.activeFilterCount,
    required this.onFilterTap,
  });

  final String title;
  final String subtitle;
  final int itemCount;
  final double topInset;
  final int activeFilterCount;
  final VoidCallback onFilterTap;
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 236 + topInset,
      child: Padding(
        padding: EdgeInsets.fromLTRB(18, topInset + 14, 18, 18),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Positioned.fill(
              child: CustomPaint(
                painter: _PriceFilterHeroPainter(_priceFilterPrimary(context)),
              ),
            ),
            Align(
              alignment: Alignment.topLeft,
              child: _CircleIconButton(
                icon: Icons.arrow_back_rounded,
                onTap: () => Navigator.maybePop(context),
              ),
            ),
            Positioned(
              right: -6,
              top: -2,
              child: _FloatingFilterIcon(
                icon: Icons.restaurant_menu_rounded,
                color: _priceFilterPrimary(context),
                assetPath: 'assets/images/budget.png',
                size: 112,
                iconSize: 54,
              ),
            ),
            Positioned(
              left: 0,
              right: 124,
              bottom: 42,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _priceFilterText,
                      fontSize: 34,
                      height: 1.02,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 9),
                  Text(
                    subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _priceFilterSubtext,
                      fontSize: 14.5,
                      height: 1.28,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Row(
                children: [
                  _FilterButton(
                    label: activeFilterCount > 0
                        ? 'Filter ($activeFilterCount)'
                        : 'Filter',
                    onTap: onFilterTap,
                  ),
                  const SizedBox(width: 10),
                  Text(
                    '$itemCount items',
                    style: const TextStyle(
                      color: _priceFilterSubtext,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
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
          child: Icon(icon, color: _priceFilterText, size: 22),
        ),
      ),
    );
  }
}

class _FloatingFilterIcon extends StatelessWidget {
  const _FloatingFilterIcon({
    required this.icon,
    required this.color,
    this.assetPath,
    this.size = 54,
    this.iconSize = 28,
  });

  final IconData icon;
  final Color color;
  final String? assetPath;
  final double size;
  final double iconSize;
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
      child: assetPath == null
          ? Icon(icon, color: color, size: iconSize)
          : Padding(
              padding: EdgeInsets.all(size * 0.03),
              child: Image.asset(
                assetPath!,
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) =>
                    Icon(icon, color: color, size: iconSize),
              ),
            ),
    );
  }
}

class _PriceFilterHeroPainter extends CustomPainter {
  const _PriceFilterHeroPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final wash = Paint()
      ..color = color.withOpacity(0.045)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(Offset(size.width * 0.88, size.height * 0.45), 120, wash);

    final dotPaint = Paint()
      ..color = color.withOpacity(0.06)
      ..style = PaintingStyle.fill;
    for (var row = 0; row < 7; row++) {
      for (var col = 0; col < 3; col++) {
        canvas.drawCircle(Offset(4 + col * 20.0, 34 + row * 20.0), 4, dotPaint);
      }
    }

    final linePaint = Paint()
      ..color = color.withOpacity(0.13)
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;
    final baseY = size.height * 0.80;
    canvas.drawLine(
      Offset(size.width * 0.45, baseY),
      Offset(size.width * 0.98, baseY),
      linePaint,
    );
    _drawCloud(canvas, linePaint, Offset(size.width * 0.68, 58), 22);
    _drawCloud(canvas, linePaint, Offset(size.width * 0.88, 38), 28);
    _drawCloud(canvas, linePaint, Offset(size.width * 0.54, 110), 18);
  }

  void _drawCloud(Canvas canvas, Paint paint, Offset center, double width) {
    final path = Path()
      ..moveTo(center.dx - width * 0.50, center.dy)
      ..quadraticBezierTo(center.dx - width * 0.28, center.dy - width * 0.22,
          center.dx - width * 0.08, center.dy - width * 0.06)
      ..quadraticBezierTo(center.dx + width * 0.08, center.dy - width * 0.36,
          center.dx + width * 0.30, center.dy - width * 0.08)
      ..quadraticBezierTo(center.dx + width * 0.48, center.dy - width * 0.06,
          center.dx + width * 0.55, center.dy);
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _PriceFilterHeroPainter oldDelegate) {
    return oldDelegate.color != color;
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
    final savings = item.hasDiscount ? item.price - item.finalPrice : 0.0;
    final quantity = _quantityForHit(context.watch<CartProvider>(), hit);

    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(22),
      child: Container(
        decoration: _priceMenuCardDecoration(context, radius: 22),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              flex: 12,
              child: Stack(
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
                  if (item.hasDiscount)
                    Positioned(
                      right: 8,
                      bottom: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 7,
                        ),
                        decoration: BoxDecoration(
                          color: FoodFlowTheme.success,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          '${item.discountPercent.round()}%\nOFF',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            height: 1,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            Expanded(
              flex: 9,
              child: Container(
                color: Colors.white,
                padding: const EdgeInsets.fromLTRB(10, 7, 10, 7),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        _VegMarker(isVeg: item.isVeg),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            item.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: _priceFilterText,
                              fontSize: 13,
                              height: 1.1,
                              fontWeight: FontWeight.w900,
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
                        color: _priceFilterSubtext,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (item.description?.trim().isNotEmpty == true) ...[
                      const SizedBox(height: 4),
                      Text(
                        item.description!.trim(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _priceFilterSubtext,
                          fontSize: 10,
                          height: 1.2,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                    const SizedBox(height: 7),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Flexible(
                                    child: Text(
                                      formatCurrency(context, item.finalPrice),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        color: _priceFilterText,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                  ),
                                  if (item.hasDiscount) ...[
                                    const SizedBox(width: 4),
                                    Text(
                                      formatCurrency(context, item.price),
                                      style: const TextStyle(
                                        color: FoodFlowTheme.faint,
                                        fontSize: 10,
                                        fontWeight: FontWeight.w700,
                                        decoration: TextDecoration.lineThrough,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              if (savings > 0) ...[
                                const SizedBox(height: 2),
                                Text(
                                  'Save ${formatCurrency(context, savings)}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: _priceFilterSuccess,
                                    fontSize: 9,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(width: 7),
                        _MenuQuantityControl(
                          quantity: quantity,
                          enabled: item.isAvailable,
                          compact: true,
                          onChanged: onQuantityChanged,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MenuQuantityControl extends StatelessWidget {
  const _MenuQuantityControl({
    required this.quantity,
    required this.enabled,
    required this.onChanged,
    this.compact = false,
  });

  final int quantity;
  final bool enabled;
  final ValueChanged<int> onChanged;
  final bool compact;
  @override
  Widget build(BuildContext context) {
    final isActive = quantity > 0;
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: enabled
              ? [_priceFilterPrimary(context), _priceFilterSecondary(context)]
              : const [FoodFlowTheme.disabledFill, FoodFlowTheme.disabledText],
        ),
        borderRadius: BorderRadius.circular(15),
        boxShadow: [
          BoxShadow(
            color: _priceFilterSuccess.withOpacity(enabled ? 0.22 : 0.08),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: SizedBox(
        width: compact ? 78 : double.infinity,
        height: compact ? 31 : 34,
        child: isActive
            ? Row(
                children: [
                  _QuantityTapZone(
                    icon: Icons.remove_rounded,
                    enabled: enabled,
                    compact: compact,
                    onTap: () => onChanged(quantity - 1),
                  ),
                  Expanded(
                    child: Text(
                      '$quantity',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: compact ? 12 : 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  _QuantityTapZone(
                    icon: Icons.add_rounded,
                    enabled: enabled,
                    compact: compact,
                    onTap: () => onChanged(quantity + 1),
                  ),
                ],
              )
            : FilledButton(
                onPressed: enabled ? () => onChanged(1) : null,
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.transparent,
                  disabledBackgroundColor: Colors.transparent,
                  foregroundColor: Colors.white,
                  shadowColor: Colors.transparent,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(15),
                  ),
                ),
                child: const Text(
                  'Add +',
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
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
    this.compact = false,
  });

  final IconData icon;
  final bool enabled;
  final VoidCallback onTap;
  final bool compact;
  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: enabled ? onTap : null,
      child: SizedBox(
        width: compact ? 25 : 42,
        height: double.infinity,
        child: Center(
          child: Container(
            width: compact ? 18 : 20,
            height: compact ? 18 : 20,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.18),
              shape: BoxShape.circle,
            ),
            child: Icon(
              icon,
              color: Colors.white,
              size: compact ? 14 : 16,
            ),
          ),
        ),
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
    return Container(
      color: const Color(0xFFEAF4FF),
      alignment: Alignment.center,
      child: Icon(
        Icons.restaurant_menu_rounded,
        color: _priceFilterPrimary(context),
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
          decoration: _pricePanelDecoration(context, radius: 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _FloatingFilterIcon(
                icon: Icons.search_off_rounded,
                color: _priceFilterPrimary(context),
              ),
              const SizedBox(height: 14),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: _priceFilterText,
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: _priceFilterSubtext,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 18),
              DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [
                      _priceFilterPrimary(context),
                      _priceFilterSecondary(context),
                    ],
                  ),
                  borderRadius: BorderRadius.circular(15),
                  boxShadow: [
                    BoxShadow(
                      color: _priceFilterPrimary(context).withOpacity(0.24),
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

BoxDecoration _priceMenuCardDecoration(BuildContext context,
    {double radius = 28}) {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: const Color(0xFFCFE3FF)),
    boxShadow: [
      BoxShadow(
        color: const Color(0xFF4F8FD9).withOpacity(0.12),
        blurRadius: 20,
        spreadRadius: -5,
        offset: const Offset(0, 12),
      ),
      BoxShadow(
        color: Colors.black.withOpacity(0.05),
        blurRadius: 18,
        spreadRadius: -5,
        offset: const Offset(0, 10),
      ),
    ],
  );
}

BoxDecoration _pricePanelDecoration(BuildContext context,
    {double radius = 28}) {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: _priceFilterLine),
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
