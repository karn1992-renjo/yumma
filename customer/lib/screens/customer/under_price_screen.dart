// lib/screens/customer/under_price_screen.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../models/menu_item.dart';
import '../../models/restaurant.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import '../../services/location_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/common/app_skeleton.dart';
import '../../widgets/customer/menu_item_card.dart';
import 'restaurant_detail_screen.dart';

/// Real, in-stock menu items priced under the threshold the backend returns
/// (default ₹99) -- see RestaurantController::underPriceMenuItems() in the
/// admin-panel. Items, category chips and sort order are entirely
/// auto-computed from live menu data on every load; only the promotional
/// banner at the top is admin-managed (Banner Settings, type "under_99").
class UnderPriceScreen extends StatefulWidget {
  const UnderPriceScreen({super.key, this.initialLat, this.initialLng});

  final double? initialLat;
  final double? initialLng;

  @override
  State<UnderPriceScreen> createState() => _UnderPriceScreenState();
}

class _UnderPriceScreenState extends State<UnderPriceScreen> {
  static const String _allCategoriesLabel = 'All';

  final ApiService _api = ApiService();
  List<MenuItem> _items = [];
  final Map<int, Restaurant> _restaurantsById = {};
  List<String> _categories = [];
  String _selectedCategory = _allCategoriesLabel;
  double _threshold = 99;
  bool _isLoading = true;
  String? _error;
  List<Map<String, dynamic>> _banners = [];

  @override
  void initState() {
    super.initState();
    _load();
    unawaited(_loadBanners());
  }

  List<MenuItem> get _filteredItems {
    if (_selectedCategory == _allCategoriesLabel) return _items;
    return _items
        .where((item) =>
            (item.categoryName ?? '').trim().toLowerCase() ==
            _selectedCategory.toLowerCase())
        .toList(growable: false);
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      var lat = widget.initialLat;
      var lng = widget.initialLng;
      if (lat == null || lng == null) {
        final position =
            await LocationService().getCurrentLocation(requestPermission: false);
        lat = position?.latitude;
        lng = position?.longitude;
      }

      if (lat == null || lng == null) {
        setState(() {
          _isLoading = false;
          _error = 'Enable location to see budget-friendly dishes near you.';
        });
        return;
      }

      final response = await _api.get(
        ApiConstants.underPriceMenuItems,
        queryParams: {'lat': lat, 'lng': lng},
      );

      if (response['success'] == true && mounted) {
        final rows = (response['data'] as List? ?? [])
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList();

        final items = <MenuItem>[];
        final restaurantsById = <int, Restaurant>{};
        for (final row in rows) {
          final restaurantJson = row['restaurant'];
          if (restaurantJson is! Map) continue;
          try {
            final restaurant = Restaurant.fromJson(
              Map<String, dynamic>.from(restaurantJson),
            );
            restaurantsById[restaurant.id] = restaurant;
            items.add(MenuItem.fromJson(row));
          } catch (_) {
            // Skip a malformed row rather than failing the whole screen.
          }
        }

        setState(() {
          _items = items;
          _restaurantsById
            ..clear()
            ..addAll(restaurantsById);
          _categories = (response['categories'] as List? ?? [])
              .map((c) => c.toString())
              .where((c) => c.trim().isNotEmpty)
              .toList(growable: false);
          if (_selectedCategory != _allCategoriesLabel &&
              !_categories.contains(_selectedCategory)) {
            _selectedCategory = _allCategoriesLabel;
          }
          _threshold = (response['threshold'] as num?)?.toDouble() ?? 99;
          _isLoading = false;
        });
      } else if (mounted) {
        setState(() {
          _isLoading = false;
          _error =
              response['message']?.toString() ?? 'Could not load dishes right now.';
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _error = 'Could not load dishes right now.';
      });
    }
  }

  Future<void> _loadBanners() async {
    try {
      final response = await _api.get(
        '${ApiConstants.bannersByType}/under_99',
        includeAuth: false,
      );
      if (response['success'] == true && mounted) {
        setState(() {
          _banners = (response['data'] as List? ?? [])
              .whereType<Map>()
              .map((row) => Map<String, dynamic>.from(row))
              .toList();
        });
      }
    } catch (_) {
      // Banner is a nice-to-have on this screen -- never block the item
      // feed on it.
    }
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), duration: const Duration(milliseconds: 900)),
    );
  }

  void _setCartQuantity(MenuItem item, int quantity) {
    final restaurant = _restaurantsById[item.restaurantId];
    if (restaurant == null) return;
    if (!restaurant.isOpen) {
      _showMessage('${restaurant.name} is closed right now');
      return;
    }
    Provider.of<CartProvider>(context, listen: false)
        .setItemQuantity(item, restaurant, quantity);
  }

  void _openItemDetails(MenuItem item) {
    final restaurant = _restaurantsById[item.restaurantId];
    if (restaurant == null) return;
    if (!restaurant.isOpen) {
      _showMessage('${restaurant.name} is closed right now');
      return;
    }
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (sheetContext) => MenuCustomizationSheet(
        item: item,
        onAdd: (result) {
          final cart = Provider.of<CartProvider>(context, listen: false);
          for (var i = 0; i < result.quantity; i++) {
            cart.addItem(
              item,
              restaurant,
              selectedVariant: result.variant,
              selectedAddOns: result.addOns,
            );
          }
          Navigator.of(context).pop();
          _showMessage('${item.name} added to cart');
        },
      ),
    );
  }

  void _handleBannerTap(Map<String, dynamic> banner) {
    final redirect = banner['redirect'] is Map
        ? Map<String, dynamic>.from(banner['redirect'] as Map)
        : <String, dynamic>{};
    final type = redirect['type']?.toString().toLowerCase();
    final id = int.tryParse((redirect['id'] ?? '').toString());

    if (type == 'restaurant' && id != null) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => RestaurantDetailScreen(restaurantId: id)),
      );
      return;
    }
    if (type == 'menu_item' && id != null) {
      final restaurantId =
          int.tryParse((redirect['restaurant_id'] ?? '').toString());
      if (restaurantId != null) {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => RestaurantDetailScreen(
              restaurantId: restaurantId,
              initialMenuItemId: id,
            ),
          ),
        );
        return;
      }
    }
    if (type == 'offer' || type == 'offers') {
      Navigator.of(context).pushNamed('/offers');
      return;
    }
    if (type == 'category' && id != null) {
      final name = (redirect['name'] ?? banner['title'] ?? '').toString();
      Navigator.of(context).pushNamed(
        '/menu-taxonomy-filter',
        arguments: {
          'title': name.isNotEmpty ? name : 'Category',
          'filter_type': 'category',
          'category_id': id,
        },
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F7FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        foregroundColor: FoodFlowTheme.ink,
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Under ${getCurrencySymbol(context)}${_threshold.toInt()}',
              style: const TextStyle(
                  fontSize: 17, fontWeight: FontWeight.w900, color: FoodFlowTheme.ink),
            ),
            Text(
              'Budget-friendly dishes near you',
              style: TextStyle(
                  fontSize: 11.5, color: FoodFlowTheme.muted, fontWeight: FontWeight.w500),
            ),
          ],
        ),
      ),
      body: _isLoading
          ? const AppSkeletonListView(itemCount: 5, itemHeight: 130)
          : _error != null
              ? _UnderPriceErrorState(message: _error!, onRetry: _load)
              : _items.isEmpty
                  ? _UnderPriceEmptyState(threshold: _threshold)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: CustomScrollView(
                        slivers: [
                          if (_banners.isNotEmpty)
                            SliverToBoxAdapter(
                              child: _UnderPriceBannerCarousel(
                                banners: _banners,
                                onTapBanner: _handleBannerTap,
                              ),
                            ),
                          if (_categories.isNotEmpty)
                            SliverToBoxAdapter(
                              child: _UnderPriceCategoryChips(
                                categories: [_allCategoriesLabel, ..._categories],
                                selected: _selectedCategory,
                                onSelect: (category) =>
                                    setState(() => _selectedCategory = category),
                              ),
                            ),
                          SliverPadding(
                            padding: const EdgeInsets.only(bottom: 24),
                            sliver: SliverList.separated(
                              itemCount: _filteredItems.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 2),
                              itemBuilder: (context, index) {
                                final item = _filteredItems[index];
                                final restaurant = _restaurantsById[item.restaurantId];
                                return Consumer<CartProvider>(
                                  builder: (context, cart, _) => MenuItemCard(
                                    item: item,
                                    quantity: cart.quantityForItemInRestaurant(
                                        item.restaurantId, item),
                                    orderingEnabled: restaurant?.isOpen ?? true,
                                    onTap: () => _openItemDetails(item),
                                    onQuantityChanged: item.hasCustomizations
                                        ? (_) => _openItemDetails(item)
                                        : (quantity) =>
                                            _setCartQuantity(item, quantity),
                                  ),
                                );
                              },
                            ),
                          ),
                        ],
                      ),
                    ),
    );
  }
}

class _UnderPriceEmptyState extends StatelessWidget {
  const _UnderPriceEmptyState({required this.threshold});

  final double threshold;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.sell_outlined, size: 48, color: Color(0xFFB8BDC9)),
            const SizedBox(height: 14),
            Text(
              'No dishes under ${getCurrencySymbol(context)}${threshold.toInt()} nearby right now.',
              textAlign: TextAlign.center,
              style: TextStyle(color: FoodFlowTheme.muted, fontSize: 13.5, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}

class _UnderPriceErrorState extends StatelessWidget {
  const _UnderPriceErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline_rounded, size: 44, color: Color(0xFFB8BDC9)),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: TextStyle(color: FoodFlowTheme.muted, fontSize: 13.5)),
            const SizedBox(height: 14),
            OutlinedButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

// Same circular-icon-chip shape as the home screen's cuisine/category rail
// (_CategoryPill in home_screen_production.dart) -- auto-derived category
// names here have no uploaded image, so each gets a deterministic accent
// color + generic icon instead, with a colored ring marking the selection.
class _UnderPriceCategoryChips extends StatelessWidget {
  const _UnderPriceCategoryChips({
    required this.categories,
    required this.selected,
    required this.onSelect,
  });

  final List<String> categories;
  final String selected;
  final ValueChanged<String> onSelect;

  static const List<Color> _palette = [
    Color(0xFFFF7A00),
    Color(0xFFFFC857),
    Color(0xFFB8E986),
    Color(0xFFDAB6FC),
    Color(0xFFFFC6D9),
    Color(0xFFBDE7FF),
  ];

  Color _accentFor(String seed) {
    if (seed.isEmpty) return _palette.first;
    return _palette[
        seed.codeUnits.fold<int>(0, (sum, code) => sum + code) % _palette.length];
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 96,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.fromLTRB(14, 6, 14, 10),
        itemCount: categories.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final category = categories[index];
          final isSelected = category == selected;
          final isAll = category == 'All';
          final accent = _accentFor(category);

          return GestureDetector(
            onTap: () => onSelect(category),
            child: SizedBox(
              width: 72,
              child: Column(
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    width: 64,
                    height: 64,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: isSelected
                            ? FoodFlowTheme.primaryColor
                            : const Color(0xFFF2F2F2),
                        width: isSelected ? 2.5 : 1,
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.06),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Icon(
                      isAll ? Icons.grid_view_rounded : Icons.restaurant_menu_rounded,
                      color: isSelected ? FoodFlowTheme.primaryColor : accent,
                      size: 26,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    category,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: isSelected ? FoodFlowTheme.primaryColor : const Color(0xFF1C1C1C),
                      fontSize: 12,
                      fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _UnderPriceBannerCarousel extends StatefulWidget {
  const _UnderPriceBannerCarousel({
    required this.banners,
    required this.onTapBanner,
  });

  final List<Map<String, dynamic>> banners;
  final ValueChanged<Map<String, dynamic>> onTapBanner;

  @override
  State<_UnderPriceBannerCarousel> createState() =>
      _UnderPriceBannerCarouselState();
}

class _UnderPriceBannerCarouselState extends State<_UnderPriceBannerCarousel> {
  final PageController _controller = PageController();
  Timer? _autoScrollTimer;
  int _page = 0;

  @override
  void initState() {
    super.initState();
    if (widget.banners.length > 1) {
      final seconds =
          (widget.banners.first['duration_seconds'] as num?)?.toInt() ?? 5;
      _autoScrollTimer = Timer.periodic(Duration(seconds: seconds.clamp(2, 15)), (_) {
        if (!mounted || !_controller.hasClients) return;
        final next = (_page + 1) % widget.banners.length;
        _controller.animateToPage(
          next,
          duration: const Duration(milliseconds: 320),
          curve: Curves.easeOutCubic,
        );
      });
    }
  }

  @override
  void dispose() {
    _autoScrollTimer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 4),
      child: AspectRatio(
        aspectRatio: 16 / 7,
        child: Stack(
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: PageView.builder(
                controller: _controller,
                itemCount: widget.banners.length,
                onPageChanged: (index) => setState(() => _page = index),
                itemBuilder: (context, index) {
                  final banner = widget.banners[index];
                  final imageUrl = banner['image_url']?.toString() ?? '';
                  return InkWell(
                    onTap: () => widget.onTapBanner(banner),
                    child: SizedBox.expand(
                      child: imageUrl.isNotEmpty
                          ? AppCachedImage(
                              imageUrl: imageUrl,
                              fit: BoxFit.cover,
                              width: double.infinity,
                              height: double.infinity,
                            )
                          : Container(color: const Color(0xFFFFE9DD)),
                    ),
                  );
                },
              ),
            ),
            if (widget.banners.length > 1)
              Positioned(
                bottom: 8,
                left: 0,
                right: 0,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(widget.banners.length, (index) {
                    final active = index == _page;
                    return AnimatedContainer(
                      duration: const Duration(milliseconds: 150),
                      margin: const EdgeInsets.symmetric(horizontal: 2.5),
                      width: active ? 16 : 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: active ? Colors.white : Colors.white54,
                        borderRadius: BorderRadius.circular(999),
                      ),
                    );
                  }),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
