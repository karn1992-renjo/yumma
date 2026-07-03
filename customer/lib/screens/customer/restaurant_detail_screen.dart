import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:lottie/lottie.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../models/menu_item.dart';
import '../../models/restaurant.dart';
import '../../providers/auth_provider.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import '../../services/app_image_cache.dart';
import '../../services/location_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/customer/menu_item_card.dart';
import '../../widgets/customer/account_chrome.dart';
import 'dining_booking_screen.dart';
import 'restaurant_reviews_screen.dart';

enum _MenuSort { recommended, priceLowToHigh, priceHighToLow }

enum _MenuFilter {
  bestSeller,
  highlyReordered,
  kidsChoice,
  pureVeg,
  customisable,
}

const int _highOrderThreshold = 20;
const String _allMenuCategoriesLabel = 'All menu';

class RestaurantDetailScreen extends StatefulWidget {
  final int restaurantId;
  final int? initialMenuItemId;

  const RestaurantDetailScreen({
    super.key,
    required this.restaurantId,
    this.initialMenuItemId,
  });

  @override
  State<RestaurantDetailScreen> createState() => _RestaurantDetailScreenState();
}

class _RestaurantDetailScreenState extends State<RestaurantDetailScreen> {
  final ApiService _api = ApiService();
  final LocationService _locationService = LocationService();
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();

  Restaurant? _restaurant;
  Map<String, List<MenuItem>> _itemsByCategory = <String, List<MenuItem>>{};
  List<Map<String, dynamic>> _restaurantPromos = <Map<String, dynamic>>[];
  final Map<String, GlobalKey> _categoryKeys = <String, GlobalKey>{};
  final Set<int> _savedMenuItemIds = <int>{};

  double? _distanceKm;
  bool _isLoading = true;
  bool _isLoadingPromos = false;
  String? _error;
  bool _isFavorite = false;
  bool _didHandleInitialMenuItem = false;
  String _selectedCategory = '';
  String? _selectedTag;
  String _searchQuery = '';
  _MenuSort _sort = _MenuSort.recommended;
  final Set<_MenuFilter> _activeFilters = <_MenuFilter>{};

  @override
  void initState() {
    super.initState();
    _loadRestaurantDetails();
    _loadFavoriteState();
    _loadSavedMenuState();
    _searchController.addListener(() {
      if (_searchQuery == _searchController.text.trim()) return;
      setState(() {
        _searchQuery = _searchController.text.trim();
      });
      _ensureSelectedCategory();
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadFavoriteState() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getStringList('saved_restaurant_ids') ?? <String>[];
    if (!mounted) return;
    setState(() {
      _isFavorite = saved.contains(widget.restaurantId.toString());
    });
  }

  Future<void> _loadSavedMenuState() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getStringList('saved_menu_item_ids') ?? <String>[];
    if (!mounted) return;
    setState(() {
      _savedMenuItemIds
        ..clear()
        ..addAll(saved.map(int.tryParse).whereType<int>());
    });
  }

  Future<void> _toggleFavorite() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getStringList('saved_restaurant_ids') ?? <String>[];
    final id = widget.restaurantId.toString();

    setState(() => _isFavorite = !_isFavorite);
    if (_isFavorite) {
      if (!saved.contains(id)) saved.add(id);
    } else {
      saved.remove(id);
    }
    await prefs.setStringList('saved_restaurant_ids', saved);

    try {
      if (_isFavorite) {
        await _api.post(ApiConstants.favoriteRestaurant(widget.restaurantId));
      } else {
        await _api.post(
          ApiConstants.removeFavoriteRestaurant(widget.restaurantId),
        );
      }
    } catch (_) {}

    if (!mounted) return;
    _showMessage(_isFavorite ? 'Restaurant saved' : 'Removed from saved');
  }

  Future<void> _loadRestaurantDetails({bool forceRefresh = false}) async {
    setState(() {
      _isLoading = _restaurant == null;
      _error = null;
    });

    try {
      final response = await _api.get(
        '${ApiConstants.restaurantDetails}/${widget.restaurantId}',
        includeAuth: false,
        cachePolicy: ApiCachePolicy.discovery,
        cacheFirst: !forceRefresh,
        refreshCached: !forceRefresh,
        onCacheRefreshed: (_) {
          if (mounted) _loadRestaurantDetails(forceRefresh: true);
        },
      );
      if (response['success'] == true) {
        _restaurant = Restaurant.fromJson(response['data']);
        await _loadDistanceToRestaurant();
        await _loadMenuItems(forceRefresh: forceRefresh);
        await _loadRestaurantPromos();
        if (mounted) {
          await AppImageCache.precacheVisible(
            context,
            <String>[
              _restaurant!.logoUrl,
              _restaurant!.bannerUrl,
              ..._itemsByCategory.values
                  .expand((items) => items)
                  .map((item) => item.imageUrl),
            ],
          );
        }
      } else {
        throw Exception(response['message'] ?? 'Failed to load restaurant');
      }
    } catch (e) {
      _error = _cleanError(e);
    }

    if (!mounted) return;
    setState(() => _isLoading = false);
    _ensureSelectedCategory();
    _openInitialMenuItemIfNeeded();
  }

  String _cleanError(Object error) {
    final message = error.toString().trim();
    if (message.startsWith('Exception: ')) {
      return message.substring('Exception: '.length);
    }
    return message.isEmpty
        ? 'Please check your internet connection and try again.'
        : message;
  }

  Future<void> _loadMenuItems({bool forceRefresh = false}) async {
    try {
      final response = await _api
          .get(
            '${ApiConstants.restaurantDetails}/${widget.restaurantId}/menu',
            includeAuth: false,
            cachePolicy: ApiCachePolicy.discovery,
            cacheFirst: !forceRefresh,
            refreshCached: false,
          )
          .timeout(const Duration(seconds: 15));

      final menuData = response['data'] is Map<String, dynamic>
          ? response['data'] as Map<String, dynamic>
          : response;
      final itemsList =
          menuData['menu_items'] ?? menuData['items'] ?? menuData['menu'] ?? [];

      final menuItems = (itemsList as List)
          .whereType<Map<String, dynamic>>()
          .map((item) => MenuItem.fromJson(item))
          .toList();

      final grouped = <String, List<MenuItem>>{};
      for (final item in menuItems) {
        final category = (item.categoryName?.trim().isNotEmpty ?? false)
            ? item.categoryName!.trim()
            : 'Recommended for you';
        grouped.putIfAbsent(category, () => <MenuItem>[]).add(item);
      }

      if (grouped.isEmpty && menuItems.isNotEmpty) {
        grouped['Menu'] = menuItems;
      }

      _itemsByCategory = grouped;
    } catch (_) {}
  }

  Future<void> _loadRestaurantPromos() async {
    _isLoadingPromos = true;
    try {
      final response = await _api
          .get(ApiConstants.customerRestaurantPromos(widget.restaurantId))
          .timeout(const Duration(seconds: 10));

      if (response['success'] == true && response['data'] is List) {
        _restaurantPromos = (response['data'] as List)
            .whereType<Map>()
            .map((promo) => Map<String, dynamic>.from(promo))
            .where((promo) {
          final status = promo['status']?.toString().toLowerCase();
          return promo['is_active'] == true || status == 'active';
        }).toList();
      }
    } catch (_) {
    } finally {
      _isLoadingPromos = false;
    }
  }

  Future<void> _loadDistanceToRestaurant() async {
    if (_restaurant == null) return;
    final savedLocation = await _locationService.getSavedLocation();
    final lat = savedLocation?['lat'];
    final lng = savedLocation?['lng'];
    if (lat is! num || lng is! num) return;
    if (_restaurant!.latitude == 0 || _restaurant!.longitude == 0) return;

    _distanceKm = await _locationService.calculateDistance(
      lat.toDouble(),
      lng.toDouble(),
      _restaurant!.latitude,
      _restaurant!.longitude,
    );
  }

  Map<String, List<MenuItem>> get _filteredItemsByCategory {
    final filtered = <String, List<MenuItem>>{};

    for (final entry in _itemsByCategory.entries) {
      final items = entry.value.where(_matchesFilters).toList();
      if (items.isEmpty) continue;
      items.sort(_sortComparator);
      filtered[entry.key] = items;
    }

    return filtered;
  }

  Map<String, List<MenuItem>> get _visibleItemsByCategory {
    final filtered = _filteredItemsByCategory;
    if (_selectedCategory.isEmpty ||
        _selectedCategory == _allMenuCategoriesLabel) {
      return filtered;
    }

    final items = filtered[_selectedCategory];
    if (items == null) return filtered;
    return <String, List<MenuItem>>{_selectedCategory: items};
  }

  bool _matchesFilters(MenuItem item) {
    final query = _searchQuery.toLowerCase();
    final matchesSearch = query.isEmpty ||
        item.name.toLowerCase().contains(query) ||
        (item.description?.toLowerCase().contains(query) ?? false);

    if (!matchesSearch) return false;

    final selectedTag = _selectedTag;
    if (selectedTag != null &&
        !item.displayTags.any(
          (tag) => tag.toLowerCase() == selectedTag.toLowerCase(),
        )) {
      return false;
    }

    if (_activeFilters.contains(_MenuFilter.bestSeller) &&
        !_isBestSellerItem(item)) {
      return false;
    }
    if (_activeFilters.contains(_MenuFilter.pureVeg) && !item.isVeg) {
      return false;
    }
    if (_activeFilters.contains(_MenuFilter.highlyReordered) &&
        !_isPopularityHighlight(item)) {
      return false;
    }
    if (_activeFilters.contains(_MenuFilter.customisable) &&
        !item.hasCustomizations) {
      return false;
    }
    if (_activeFilters.contains(_MenuFilter.kidsChoice) &&
        !_looksLikeKidsChoice(item)) {
      return false;
    }

    return true;
  }

  bool _isBestSellerItem(MenuItem item) => item.isBestseller;

  bool _isHighlyOrderedItem(MenuItem item) =>
      item.totalOrders >= _highOrderThreshold;

  bool _isPopularityHighlight(MenuItem item) =>
      _isBestSellerItem(item) || _isHighlyOrderedItem(item);

  int _menuPopularityScore(MenuItem item) =>
      (_isBestSellerItem(item) ? 200 : 0) +
      (_isHighlyOrderedItem(item) ? 120 : 0) +
      (item.isRecommended ? 40 : 0) +
      item.totalOrders;

  bool _looksLikeKidsChoice(MenuItem item) {
    final text = '${item.name} ${item.description ?? ''}'.toLowerCase().trim();
    return text.contains('kid') ||
        text.contains('mini') ||
        text.contains('cupcake') ||
        text.contains('muffin') ||
        text.contains('choco') ||
        text.contains('dessert') ||
        text.contains('cake');
  }

  int _sortComparator(MenuItem a, MenuItem b) {
    switch (_sort) {
      case _MenuSort.priceLowToHigh:
        return a.finalPrice.compareTo(b.finalPrice);
      case _MenuSort.priceHighToLow:
        return b.finalPrice.compareTo(a.finalPrice);
      case _MenuSort.recommended:
        final aScore = _menuPopularityScore(a);
        final bScore = _menuPopularityScore(b);
        return bScore.compareTo(aScore);
    }
  }

  List<String> get _categoryNames => _filteredItemsByCategory.keys.toList();

  List<String> get _tagOptions {
    final seen = <String>{};
    final tags = _itemsByCategory.values
        .expand((items) => items)
        .expand((item) => item.displayTags)
        .where((tag) => seen.add(tag.toLowerCase()))
        .toList();
    tags.sort((a, b) => a.toLowerCase().compareTo(b.toLowerCase()));
    return tags;
  }

  void _ensureSelectedCategory() {
    final categories = _categoryNames;
    if (categories.isEmpty) {
      _selectedCategory = '';
      return;
    }
    if (_selectedCategory.isEmpty) {
      _selectedCategory = _allMenuCategoriesLabel;
      return;
    }
    if (_selectedCategory != _allMenuCategoriesLabel &&
        !_filteredItemsByCategory.containsKey(_selectedCategory)) {
      setState(() => _selectedCategory = _allMenuCategoriesLabel);
    }
    final selectedTag = _selectedTag;
    if (selectedTag != null &&
        !_tagOptions
            .any((tag) => tag.toLowerCase() == selectedTag.toLowerCase())) {
      setState(() => _selectedTag = null);
    }
  }

  void _selectCategory(String category) {
    if (_selectedCategory != category) {
      setState(() => _selectedCategory = category);
    }
    if (category == _allMenuCategoriesLabel) {
      _scrollController.animateTo(
        0,
        duration: const Duration(milliseconds: 240),
        curve: Curves.easeOutCubic,
      );
      return;
    }
    final target = _categoryKeys[category]?.currentContext;
    if (target == null) return;
    Scrollable.ensureVisible(
      target,
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
      alignment: 0.06,
    );
  }

  void _selectTag(String? tag) {
    if (_selectedTag == tag) return;
    setState(() => _selectedTag = tag);
    _ensureSelectedCategory();
  }

  void _showFloatingMenuSheet() {
    final categoryCounts = Map<String, int>.fromEntries(
      _filteredItemsByCategory.entries.map(
        (entry) => MapEntry(entry.key, entry.value.length),
      ),
    );
    if (categoryCounts.isEmpty) return;

    final totalCount = categoryCounts.values.fold<int>(
      0,
      (sum, count) => sum + count,
    );

    showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Close menu',
      barrierColor: Colors.black.withOpacity(0.58),
      transitionDuration: const Duration(milliseconds: 180),
      pageBuilder: (dialogContext, animation, secondaryAnimation) {
        return _FloatingMenuDialog(
          categories: categoryCounts,
          totalCount: totalCount,
          selectedCategory: _selectedCategory,
          onSelect: (category) {
            Navigator.of(dialogContext).pop();
            _selectCategory(category);
          },
          onClose: () => Navigator.of(dialogContext).pop(),
        );
      },
      transitionBuilder: (context, animation, secondaryAnimation, child) {
        return FadeTransition(
          opacity: animation,
          child: ScaleTransition(
            scale: Tween<double>(begin: 0.96, end: 1).animate(
              CurvedAnimation(parent: animation, curve: Curves.easeOutCubic),
            ),
            child: child,
          ),
        );
      },
    );
  }

  String _withMenuItemQuery(String link, int? menuItemId) {
    if (menuItemId == null) {
      return link
          .replaceAll('{menu_item_id}', '')
          .replaceAll('{menuItemId}', '')
          .replaceAll('{item_id}', '');
    }
    if (link.contains('{menu_item_id}')) {
      return link.replaceAll('{menu_item_id}', '$menuItemId');
    }
    if (link.contains('{menuItemId}')) {
      return link.replaceAll('{menuItemId}', '$menuItemId');
    }
    if (link.contains('{item_id}')) {
      return link.replaceAll('{item_id}', '$menuItemId');
    }

    final uri = Uri.tryParse(link);
    if (uri == null) return link;
    return uri.replace(
      queryParameters: {
        ...uri.queryParameters,
        'menu_item_id': '$menuItemId',
      },
    ).toString();
  }

  String _expandRestaurantLinkTemplate(
    String template,
    String restaurantId, {
    int? menuItemId,
  }) {
    final expanded = template
        .replaceAll('{restaurant_id}', restaurantId)
        .replaceAll('{restaurantId}', restaurantId)
        .replaceAll('{id}', restaurantId);
    return _withMenuItemQuery(expanded, menuItemId);
  }

  String? _buildAppsFlyerOneLink({
    required String restaurantId,
    int? menuItemId,
  }) {
    final rawDomain = AppConfig.appsFlyerOneLinkDomain.trim();
    final domain = rawDomain.isNotEmpty
        ? rawDomain
            .replaceFirst(RegExp(r'^https?://'), '')
            .replaceFirst(RegExp(r'/$'), '')
        : AppConfig.appsFlyerOneLinkId.trim().isNotEmpty
            ? '${AppConfig.appsFlyerOneLinkId.trim()}.onelink.me'
            : '';
    if (domain.isEmpty) return null;

    final route = menuItemId == null ? 'restaurant' : 'product';
    final webDeepLink = Uri.https(
      'foodflow.in',
      '/restaurants/$restaurantId',
      menuItemId == null
          ? null
          : <String, String>{'menu_item_id': '$menuItemId'},
    ).toString();

    final params = <String, String>{
      'pid': 'share',
      'c': menuItemId == null ? 'restaurant_share' : 'menu_share',
      'link': webDeepLink,
      'screen': route,
      'type': route,
      'deep_link_value': route,
      'deep_link_sub1': menuItemId == null ? restaurantId : '$menuItemId',
      if (menuItemId != null) 'deep_link_sub2': restaurantId,
    };

    final oneLinkId = AppConfig.appsFlyerOneLinkId.trim();
    final pathSegments = <String>[];
    if (oneLinkId.isNotEmpty) pathSegments.add(oneLinkId);
    pathSegments.add(route);

    final oneLinkBase = Uri.parse('https://$domain');
    return oneLinkBase
        .replace(
          pathSegments: pathSegments,
          queryParameters: params,
        )
        .toString();
  }

  Future<String?> _buildRestaurantDeepLink({int? menuItemId}) async {
    final restaurantId = widget.restaurantId.toString();
    return _buildAppsFlyerOneLink(
      restaurantId: restaurantId,
      menuItemId: menuItemId,
    );
  }

  Future<void> _shareRestaurant() async {
    if (_restaurant == null) return;
    final restaurant = _restaurant!;
    final link = await _buildRestaurantDeepLink();
    if (link == null || link.isEmpty) {
      _showMessage('Unable to create share link.');
      return;
    }

    final message =
        'Check out ${restaurant.name}\n${restaurant.cuisineText.isEmpty ? 'Great food and fast delivery' : restaurant.cuisineText}\n$link';
    await Share.share(message, subject: restaurant.name);
  }

  Future<void> _shareMenuItem(MenuItem item) async {
    final restaurantName = _restaurant?.name ?? 'this restaurant';
    final description = item.description?.trim().isNotEmpty == true
        ? item.description!.trim()
        : 'Available now at $restaurantName';
    final link = await _buildRestaurantDeepLink(menuItemId: item.id);
    if (link == null || link.isEmpty) {
      _showMessage('Unable to create share link.');
      return;
    }

    await Share.share(
      '${item.name}\n$description\n${formatCurrency(context, item.finalPrice)}\n$link',
      subject: item.name,
    );
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
          content: Text(message), duration: const Duration(milliseconds: 900)),
    );
  }

  void _setCartQuantity(
    MenuItem item,
    int quantity, {
    MenuOption? selectedVariant,
    List<MenuOption> selectedAddOns = const [],
  }) {
    if (_restaurant == null) return;
    if (!_restaurant!.isOpen) {
      _showMessage(_restaurantClosedMessage(_restaurant!));
      return;
    }
    Provider.of<CartProvider>(context, listen: false).setItemQuantity(
      item,
      _restaurant!,
      quantity,
      selectedVariant: selectedVariant,
      selectedAddOns: selectedAddOns,
    );
  }

  void _openItemDetails(MenuItem item) {
    if (_restaurant?.isOpen == false) {
      _showMessage(_restaurantClosedMessage(_restaurant!));
      return;
    }
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: accountCanvas,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (_) => MenuCustomizationSheet(
        item: item,
        isSaved: _savedMenuItemIds.contains(item.id),
        onSave: () => _toggleSavedMenuItem(item),
        onShare: () => _shareMenuItem(item),
        onAdd: (result) {
          final cart = Provider.of<CartProvider>(context, listen: false);
          if (_restaurant == null) return;
          if (!_restaurant!.isOpen) {
            _showMessage(_restaurantClosedMessage(_restaurant!));
            return;
          }
          for (var i = 0; i < result.quantity; i++) {
            cart.addItem(
              item,
              _restaurant!,
              selectedVariant: result.variant,
              selectedAddOns: result.addOns,
            );
          }
          _showMessage('${item.name} added to cart');
        },
      ),
    );
  }

  void _openInitialMenuItemIfNeeded() {
    if (_didHandleInitialMenuItem || widget.initialMenuItemId == null) return;
    _didHandleInitialMenuItem = true;

    MenuItem? targetItem;
    String? targetCategory;
    for (final entry in _itemsByCategory.entries) {
      for (final item in entry.value) {
        if (item.id == widget.initialMenuItemId) {
          targetItem = item;
          targetCategory = entry.key;
          break;
        }
      }
      if (targetItem != null) break;
    }

    if (targetItem == null) return;
    if (targetCategory != null && targetCategory.isNotEmpty) {
      setState(() => _selectedCategory = targetCategory!);
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _openItemDetails(targetItem!);
    });
  }

  void _openDiningBooking() {
    final restaurant = _restaurant;
    if (restaurant == null) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => DiningBookingScreen(
          restaurantId: restaurant.id,
          restaurantName: restaurant.name,
          diningCharge: restaurant.diningCharge ?? 0,
        ),
      ),
    );
  }

  void _openReviews() {
    final restaurant = _restaurant;
    if (restaurant == null) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RestaurantReviewsScreen(restaurant: restaurant),
      ),
    );
  }

  String _distanceLabel() {
    final distance = _distanceKm ?? _restaurant?.distance;
    if (distance == null) return '';
    if (distance < 1) return '${(distance * 1000).round()} m';
    return '${distance.toStringAsFixed(distance >= 10 ? 0 : 1)} km';
  }

  String _reviewText() {
    final count = _restaurant?.reviewCount ?? 0;
    if (count >= 1000) {
      return '${(count / 1000).toStringAsFixed(count >= 10000 ? 0 : 1)}K+';
    }
    return '$count';
  }

  String _reviewCommentText() {
    final count = _restaurant?.reviewCommentCount ?? 0;
    if (count <= 0) return 'No comments yet';
    if (count >= 1000) {
      return '${(count / 1000).toStringAsFixed(count >= 10000 ? 0 : 1)}K comments';
    }
    return '$count comments';
  }

  Future<void> _openMenuIssueSheet() async {
    final auth = Provider.of<AuthProvider>(context, listen: false);
    if (auth.currentUser == null) {
      _showMessage('Please login to report a menu issue');
      return;
    }

    final controller = TextEditingController();
    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) => Padding(
        padding: EdgeInsets.fromLTRB(
          18,
          18,
          18,
          18 + MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Report an issue with the menu',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            const Text(
              'Tell us what looks wrong so the admin and restaurant can review it.',
              style: TextStyle(
                color: FoodFlowTheme.muted,
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: controller,
              minLines: 4,
              maxLines: 6,
              decoration: const InputDecoration(
                hintText:
                    'Missing item, wrong price, unavailable dish still visible, duplicate menu item...',
              ),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.pop(context, true),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0A9443),
                  foregroundColor: Colors.white,
                ),
                child: const Text('Send report'),
              ),
            ),
          ],
        ),
      ),
    );

    if (submitted != true || _restaurant == null) return;

    final message = controller.text.trim();
    if (message.isEmpty) {
      _showMessage('Please describe the issue first');
      return;
    }

    try {
      await _api.post(
        ApiConstants.supportTickets,
        data: {
          'restaurant_id': _restaurant!.id,
          'subject': 'Menu issue at ${_restaurant!.name}',
          'message': message,
          'category': 'menu_issue',
          'priority': 'medium',
        },
      );
      _showMessage('Issue reported to support');
    } catch (_) {
      _showMessage('Unable to report issue right now');
    }
  }

  void _openSimilarRestaurant(Map<String, dynamic> restaurant) {
    final id = restaurant['id'] is int
        ? restaurant['id'] as int
        : int.tryParse(restaurant['id']?.toString() ?? '');
    if (id == null || id == widget.restaurantId) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => RestaurantDetailScreen(restaurantId: id),
      ),
    );
  }

  Future<void> _toggleSavedMenuItem(MenuItem item) async {
    final prefs = await SharedPreferences.getInstance();
    final nextSaved = Set<int>.from(_savedMenuItemIds);
    final isSaving = !nextSaved.remove(item.id);
    if (isSaving) {
      nextSaved.add(item.id);
    }

    await prefs.setStringList(
      'saved_menu_item_ids',
      nextSaved.map((id) => id.toString()).toList(growable: false),
    );

    if (!mounted) return;
    setState(() {
      _savedMenuItemIds
        ..clear()
        ..addAll(nextSaved);
    });
    _showMessage(isSaving ? 'Saved for later' : 'Removed from saved items');
  }

  Future<void> _showFilterSheet() async {
    final nextFilters = Set<_MenuFilter>.from(_activeFilters);
    var nextSort = _sort;

    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.white,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
      ),
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            Widget toggleChip({
              required String label,
              required bool selected,
              required VoidCallback onTap,
            }) {
              return Padding(
                padding: const EdgeInsets.only(right: 10, bottom: 10),
                child: InkWell(
                  onTap: onTap,
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 12,
                    ),
                    decoration: BoxDecoration(
                      color: selected ? const Color(0xFFEDFAF2) : Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: selected
                            ? const Color(0xFF83D4A5)
                            : const Color(0xFFE7EAF0),
                      ),
                    ),
                    child: Text(
                      label,
                      style: TextStyle(
                        color: selected
                            ? const Color(0xFF0A9443)
                            : FoodFlowTheme.ink,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
              );
            }

            return SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 18),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Filters and Sorting',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(context),
                          icon: const Icon(Icons.close_rounded, size: 20),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    _FilterSection(
                      title: 'Sort by',
                      child: Wrap(
                        children: [
                          toggleChip(
                            label: 'Recommended',
                            selected: nextSort == _MenuSort.recommended,
                            onTap: () => setSheetState(
                              () => nextSort = _MenuSort.recommended,
                            ),
                          ),
                          toggleChip(
                            label: 'Price - low to high',
                            selected: nextSort == _MenuSort.priceLowToHigh,
                            onTap: () => setSheetState(
                              () => nextSort = _MenuSort.priceLowToHigh,
                            ),
                          ),
                          toggleChip(
                            label: 'Price - high to low',
                            selected: nextSort == _MenuSort.priceHighToLow,
                            onTap: () => setSheetState(
                              () => nextSort = _MenuSort.priceHighToLow,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                    _FilterSection(
                      title: 'Top picks',
                      child: Wrap(
                        children: [
                          toggleChip(
                            label: 'Best seller',
                            selected:
                                nextFilters.contains(_MenuFilter.bestSeller),
                            onTap: () => setSheetState(() {
                              if (!nextFilters.remove(_MenuFilter.bestSeller)) {
                                nextFilters.add(_MenuFilter.bestSeller);
                              }
                            }),
                          ),
                          toggleChip(
                            label: 'Highly ordered',
                            selected: nextFilters
                                .contains(_MenuFilter.highlyReordered),
                            onTap: () => setSheetState(() {
                              if (!nextFilters.remove(
                                _MenuFilter.highlyReordered,
                              )) {
                                nextFilters.add(_MenuFilter.highlyReordered);
                              }
                            }),
                          ),
                          toggleChip(
                            label: 'Kid\'s choice',
                            selected:
                                nextFilters.contains(_MenuFilter.kidsChoice),
                            onTap: () => setSheetState(() {
                              if (!nextFilters.remove(_MenuFilter.kidsChoice)) {
                                nextFilters.add(_MenuFilter.kidsChoice);
                              }
                            }),
                          ),
                          toggleChip(
                            label: 'Pure veg',
                            selected: nextFilters.contains(_MenuFilter.pureVeg),
                            onTap: () => setSheetState(() {
                              if (!nextFilters.remove(_MenuFilter.pureVeg)) {
                                nextFilters.add(_MenuFilter.pureVeg);
                              }
                            }),
                          ),
                          toggleChip(
                            label: 'Customisable',
                            selected:
                                nextFilters.contains(_MenuFilter.customisable),
                            onTap: () => setSheetState(() {
                              if (!nextFilters.remove(
                                _MenuFilter.customisable,
                              )) {
                                nextFilters.add(_MenuFilter.customisable);
                              }
                            }),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        Expanded(
                          child: TextButton(
                            onPressed: () {
                              setSheetState(() {
                                nextFilters.clear();
                                nextSort = _MenuSort.recommended;
                              });
                            },
                            child: const Text(
                              'Clear all',
                              style: TextStyle(
                                color: Color(0xFF0A9443),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: FilledButton(
                            onPressed: () {
                              setState(() {
                                _activeFilters
                                  ..clear()
                                  ..addAll(nextFilters);
                                _sort = nextSort;
                              });
                              _ensureSelectedCategory();
                              Navigator.pop(context);
                            },
                            style: FilledButton.styleFrom(
                              backgroundColor: const Color(0xFF0A9443),
                            ),
                            child: Text(
                              'Apply (${nextFilters.length + 1})',
                              style:
                                  const TextStyle(fontWeight: FontWeight.w700),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null || _restaurant == null) {
      return Scaffold(
        appBar: AppBar(),
        body: NetworkErrorView(
          title: 'Unable to load restaurant',
          message: _error ?? 'Restaurant not found',
          onRetry: _loadRestaurantDetails,
        ),
      );
    }

    final restaurant = _restaurant!;
    final filtered = _visibleItemsByCategory;
    final categories = filtered.keys.toList();
    final categoryOptions = _categoryNames;
    final tagOptions = _tagOptions;
    for (final category in categoryOptions) {
      _categoryKeys.putIfAbsent(category, () => GlobalKey());
    }

    return Scaffold(
      backgroundColor: accountCanvas,
      body: SafeArea(
        bottom: false,
        child: CustomScrollView(
          controller: _scrollController,
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 4, 10, 6),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        _CircleIconButton(
                          icon: Icons.arrow_back_rounded,
                          onTap: () => Navigator.of(context).maybePop(),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _SearchPill(
                            controller: _searchController,
                            hintText: 'Search menu',
                          ),
                        ),
                        const SizedBox(width: 6),
                        _CircleIconButton(
                          icon: Icons.share_outlined,
                          onTap: _shareRestaurant,
                        ),
                        const SizedBox(width: 6),
                        _CircleIconButton(
                          icon: _isFavorite
                              ? Icons.favorite_rounded
                              : Icons.favorite_border_rounded,
                          iconColor: _isFavorite
                              ? const Color(0xFFE34949)
                              : FoodFlowTheme.ink,
                          onTap: _toggleFavorite,
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    if (restaurant.isPureVeg)
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE9F8EE),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: const Text(
                          'Pure Veg',
                          style: TextStyle(
                            color: Color(0xFF189B50),
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    if (restaurant.isPureVeg) const SizedBox(height: 6),
                    _RestaurantStatusChip(isOpen: restaurant.isOpen),
                    if (!restaurant.isOpen) ...[
                      const SizedBox(height: 8),
                      _ClosedRestaurantBanner(restaurant: restaurant),
                    ],
                    const SizedBox(height: 8),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                restaurant.name,
                                style: const TextStyle(
                                  fontSize: 16,
                                  height: 1.05,
                                  fontWeight: FontWeight.w800,
                                  color: FoodFlowTheme.ink,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                [
                                  '${restaurant.deliveryTime}-${restaurant.deliveryTime + 5} mins',
                                  if (_distanceLabel().isNotEmpty)
                                    _distanceLabel(),
                                  restaurant.city.isEmpty
                                      ? restaurant.address
                                      : restaurant.city,
                                ].join(' · '),
                                style: const TextStyle(
                                  fontSize: 11,
                                  color: FoodFlowTheme.inkSoft,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 5,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Icon(
                                      Icons.check_rounded,
                                      color: Color(0xFF0A9443),
                                      size: 15,
                                    ),
                                    const SizedBox(width: 6),
                                    Text(
                                      'Min order ${formatCurrency(context, restaurant.minOrderAmount)}',
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        InkWell(
                          onTap: _openReviews,
                          borderRadius: BorderRadius.circular(14),
                          child: Container(
                            constraints: const BoxConstraints(minWidth: 68),
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 8,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Column(
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 7,
                                    vertical: 4,
                                  ),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF0A9443),
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Icon(
                                        Icons.star_rounded,
                                        size: 12,
                                        color: Colors.white,
                                      ),
                                      const SizedBox(width: 4),
                                      Text(
                                        restaurant.visibleRating
                                                ?.toStringAsFixed(1) ??
                                            'New',
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  restaurant.reviewCount > 0
                                      ? 'By ${_reviewText()}+'
                                      : 'New restaurant',
                                  style: const TextStyle(
                                    color: FoodFlowTheme.inkSoft,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (_restaurantPromos.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      _OfferBanner(offer: _restaurantPromos.first),
                    ],
                    if (restaurant.isDining ||
                        (restaurant.diningCharge ?? 0) > 0) ...[
                      const SizedBox(height: 10),
                      InkWell(
                        onTap: _openDiningBooking,
                        borderRadius: BorderRadius.circular(16),
                        child: Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFF3E8),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 34,
                                height: 34,
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: const Icon(
                                  Icons.event_seat_rounded,
                                  color: FoodFlowTheme.orange,
                                  size: 18,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  restaurant.diningCharge != null &&
                                          restaurant.diningCharge! > 0
                                      ? 'Book a table from ${formatCurrency(context, restaurant.diningCharge!)} per cover'
                                      : 'Reserve a table at this restaurant',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                    color: FoodFlowTheme.ink,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _QuickChip(
                        label: 'Filters',
                        icon: Icons.tune_rounded,
                        selected: _selectedTag != null ||
                            _activeFilters.isNotEmpty ||
                            _sort != _MenuSort.recommended,
                        onTap: _showFilterSheet,
                      ),
                      const SizedBox(width: 10),
                      _QuickChip(
                        label: 'Best seller',
                        icon: Icons.local_fire_department_rounded,
                        selected:
                            _activeFilters.contains(_MenuFilter.bestSeller),
                        onTap: () {
                          setState(() {
                            if (!_activeFilters
                                .remove(_MenuFilter.bestSeller)) {
                              _activeFilters.add(_MenuFilter.bestSeller);
                            }
                          });
                        },
                      ),
                      const SizedBox(width: 10),
                      _QuickChip(
                        label: 'Highly ordered',
                        icon: Icons.refresh_rounded,
                        selected: _activeFilters.contains(
                          _MenuFilter.highlyReordered,
                        ),
                        onTap: () {
                          setState(() {
                            if (!_activeFilters.remove(
                              _MenuFilter.highlyReordered,
                            )) {
                              _activeFilters.add(_MenuFilter.highlyReordered);
                            }
                          });
                        },
                      ),
                      const SizedBox(width: 10),
                      _QuickChip(
                        label: 'Pure veg',
                        icon: Icons.eco_rounded,
                        selected: _activeFilters.contains(_MenuFilter.pureVeg),
                        onTap: () {
                          setState(() {
                            if (!_activeFilters.remove(_MenuFilter.pureVeg)) {
                              _activeFilters.add(_MenuFilter.pureVeg);
                            }
                          });
                        },
                      ),
                      const SizedBox(width: 10),
                      _QuickChip(
                        label: 'Kid\'s choice',
                        icon: Icons.face_retouching_natural_rounded,
                        selected:
                            _activeFilters.contains(_MenuFilter.kidsChoice),
                        onTap: () {
                          setState(() {
                            if (!_activeFilters
                                .remove(_MenuFilter.kidsChoice)) {
                              _activeFilters.add(_MenuFilter.kidsChoice);
                            }
                          });
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),
            if (tagOptions.isNotEmpty)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(right: 10),
                          child: _QuickChip(
                            label: 'All tags',
                            icon: Icons.sell_outlined,
                            selected: _selectedTag == null,
                            onTap: () => _selectTag(null),
                          ),
                        ),
                        ...tagOptions.map((tag) {
                          return Padding(
                            padding: const EdgeInsets.only(right: 10),
                            child: _QuickChip(
                              label: tag,
                              icon: Icons.local_offer_outlined,
                              selected: _selectedTag == tag,
                              onTap: () => _selectTag(tag),
                            ),
                          );
                        }),
                      ],
                    ),
                  ),
                ),
              ),
            if (filtered.isEmpty)
              const SliverFillRemaining(
                child: Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('No menu items match your current filters.'),
                  ),
                ),
              )
            else
              SliverList(
                delegate: SliverChildListDelegate(
                  [
                    ...categories.map((category) {
                      final items = filtered[category] ?? <MenuItem>[];
                      return Container(
                        key: _categoryKeys[category],
                        margin: const EdgeInsets.only(bottom: 8),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Padding(
                              padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
                              child: Text(
                                category,
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w700,
                                  color: FoodFlowTheme.ink,
                                ),
                              ),
                            ),
                            ...items.map(
                              (item) => Consumer<CartProvider>(
                                builder: (context, cart, _) => MenuItemCard(
                                  item: item,
                                  isSaved: _savedMenuItemIds.contains(item.id),
                                  quantity: cart.quantityForItem(item),
                                  onTap: () => _openItemDetails(item),
                                  onSave: () => _toggleSavedMenuItem(item),
                                  onShare: () => _shareMenuItem(item),
                                  orderingEnabled: restaurant.isOpen,
                                  onQuantityChanged: item.hasCustomizations
                                      ? (_) => _openItemDetails(item)
                                      : (quantity) {
                                          _setCartQuantity(item, quantity);
                                        },
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                    if (restaurant.similarRestaurants.isNotEmpty)
                      _RestaurantDetailsSection(
                        child: _SimilarRestaurantsSection(
                          restaurants: restaurant.similarRestaurants,
                          onTap: _openSimilarRestaurant,
                        ),
                      ),
                    _RestaurantDetailsSection(
                      child: _RestaurantInfoSection(
                        fssaiLicenseNumber: restaurant.fssaiLicenseNumber,
                        onReportIssue: _openMenuIssueSheet,
                      ),
                    ),
                    if (restaurant.reviewHighlights.isNotEmpty)
                      _RestaurantDetailsSection(
                        child: _ReviewHighlightsSection(
                          rating: restaurant.visibleRating ?? restaurant.rating,
                          reviewCount: restaurant.reviewCount,
                          commentText: _reviewCommentText(),
                          reviews: restaurant.reviewHighlights,
                          onViewAll: _openReviews,
                        ),
                      ),
                  ],
                ),
              ),
            const SliverPadding(padding: EdgeInsets.only(bottom: 120)),
          ],
        ),
      ),
      bottomNavigationBar: Consumer<CartProvider>(
        builder: (context, cart, _) {
          if (cart.isEmpty || cart.restaurant?.id != restaurant.id) {
            return const SizedBox.shrink();
          }

          return SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(10, 0, 10, 8),
              child: InkWell(
                onTap: () => Navigator.pushNamed(context, '/cart'),
                borderRadius: BorderRadius.circular(22),
                child: Container(
                  padding: const EdgeInsets.fromLTRB(10, 10, 10, 10),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(22),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.08),
                        blurRadius: 16,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8EFE7),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: restaurant.logoUrl.isNotEmpty
                            ? AppCachedImage(
                                imageUrl: restaurant.logoUrl,
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) => const Icon(
                                  Icons.restaurant_rounded,
                                  color: FoodFlowTheme.orange,
                                ),
                              )
                            : const Icon(
                                Icons.restaurant_rounded,
                                color: FoodFlowTheme.orange,
                              ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              restaurant.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: FoodFlowTheme.ink,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${cart.itemCount} item${cart.itemCount == 1 ? '' : 's'} · ${formatCurrency(context, cart.total)}',
                              style: const TextStyle(
                                color: FoodFlowTheme.muted,
                                fontSize: 11,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [
                              Theme.of(context).colorScheme.primary,
                              Color.lerp(
                                    Theme.of(context).colorScheme.primary,
                                    Colors.black,
                                    0.14,
                                  ) ??
                                  Theme.of(context).colorScheme.primary,
                            ],
                          ),
                          borderRadius: BorderRadius.circular(16),
                          boxShadow: [
                            BoxShadow(
                              color: Theme.of(context)
                                  .colorScheme
                                  .primary
                                  .withOpacity(0.22),
                              blurRadius: 12,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'View Cart',
                              style: GoogleFonts.nunitoSans(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                fontSize: 14,
                                letterSpacing: 0.1,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${cart.itemCount} item${cart.itemCount == 1 ? '' : 's'}',
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.86),
                                fontSize: 10,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
      floatingActionButton: categoryOptions.isEmpty
          ? null
          : _FloatingMenuButton(
              onTap: _showFloatingMenuSheet,
              activeLabel: _selectedCategory == _allMenuCategoriesLabel ||
                      _selectedCategory.isEmpty
                  ? 'Menu'
                  : _selectedCategory,
            ),
    );
  }
}

class _FloatingMenuButton extends StatelessWidget {
  final VoidCallback onTap;
  final String activeLabel;

  const _FloatingMenuButton({
    required this.onTap,
    required this.activeLabel,
  });

  @override
  Widget build(BuildContext context) {
    final label = activeLabel.length > 18
        ? '${activeLabel.substring(0, 17)}...'
        : activeLabel;

    return SafeArea(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          constraints: const BoxConstraints(minHeight: 48, maxWidth: 168),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: const Color(0xFF1F2937),
            borderRadius: BorderRadius.circular(12),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.22),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.restaurant_menu_rounded,
                color: Colors.white,
                size: 21,
              ),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
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

class _FloatingMenuDialog extends StatelessWidget {
  final Map<String, int> categories;
  final int totalCount;
  final String selectedCategory;
  final ValueChanged<String> onSelect;
  final VoidCallback onClose;

  const _FloatingMenuDialog({
    required this.categories,
    required this.totalCount,
    required this.selectedCategory,
    required this.onSelect,
    required this.onClose,
  });

  @override
  Widget build(BuildContext context) {
    final allSelected =
        selectedCategory.isEmpty || selectedCategory == _allMenuCategoriesLabel;
    final screen = MediaQuery.of(context).size;
    final maxHeight = screen.height * 0.64;

    return SafeArea(
      child: Material(
        color: Colors.transparent,
        child: Stack(
          children: [
            Align(
              alignment: Alignment.center,
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxWidth: screen.width - 32,
                  maxHeight: maxHeight,
                ),
                child: Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.16),
                        blurRadius: 28,
                        offset: const Offset(0, 14),
                      ),
                    ],
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(24, 12, 24, 18),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        _FloatingMenuRow(
                          label: 'Recommended for you',
                          count: totalCount,
                          selected: allSelected,
                          onTap: () => onSelect(_allMenuCategoriesLabel),
                        ),
                        ...categories.entries.map(
                          (entry) => _FloatingMenuRow(
                            label: entry.key,
                            count: entry.value,
                            selected: selectedCategory == entry.key,
                            onTap: () => onSelect(entry.key),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              right: 18,
              bottom: 76,
              child: InkWell(
                onTap: onClose,
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF30343D),
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.2),
                        blurRadius: 14,
                        offset: const Offset(0, 7),
                      ),
                    ],
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.close_rounded, color: Colors.white, size: 22),
                      SizedBox(width: 8),
                      Text(
                        'Close',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FloatingMenuRow extends StatelessWidget {
  final String label;
  final int count;
  final bool selected;
  final VoidCallback onTap;

  const _FloatingMenuRow({
    required this.label,
    required this.count,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: selected
                      ? const Color(0xFF0A9443)
                      : const Color(0xFF5E6473),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Text(
              '$count',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w900,
                color: selected
                    ? const Color(0xFF0A9443)
                    : const Color(0xFF5E6473),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RestaurantDetailsSection extends StatelessWidget {
  final Widget child;

  const _RestaurantDetailsSection({required this.child});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 14,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: child,
      ),
    );
  }
}

class _SimilarRestaurantsSection extends StatelessWidget {
  final List<Map<String, dynamic>> restaurants;
  final ValueChanged<Map<String, dynamic>> onTap;

  const _SimilarRestaurantsSection({
    required this.restaurants,
    required this.onTap,
  });

  String _cuisineText(Map<String, dynamic> restaurant) {
    List<String> namesFrom(dynamic value) {
      if (value is String && value.trim().isNotEmpty) {
        return value
            .split(',')
            .map((item) => item.trim())
            .where((item) => item.isNotEmpty && int.tryParse(item) == null)
            .take(2)
            .toList();
      }
      if (value is List) {
        return value
            .map((item) {
              if (item is Map) {
                return (item['name'] ??
                        item['title'] ??
                        item['cuisine_name'] ??
                        '')
                    .toString()
                    .trim();
              }
              final text = item?.toString().trim() ?? '';
              return int.tryParse(text) == null ? text : '';
            })
            .where((item) => item.isNotEmpty)
            .take(2)
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
      final values = namesFrom(restaurant[key]);
      if (values.isNotEmpty) return values.join(', ');
    }
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final cards = restaurants.take(4).toList(growable: false);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Try these similar restaurants',
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w900,
            color: FoodFlowTheme.ink,
          ),
        ),
        const SizedBox(height: 12),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: cards.length,
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.68,
          ),
          itemBuilder: (context, index) {
            final item = cards[index];
            final image =
                (item['banner_image'] ?? item['logo_image'] ?? '').toString();
            double? rating;
            final rVal = item['rating'] ??
                item['avg_rating'] ??
                item['review_rating'] ??
                item['rating_value'];
            if (rVal != null) {
              if (rVal is num) {
                rating = rVal.toDouble();
              } else if (rVal is String) {
                rating = double.tryParse(rVal);
              }
            }
            final reviewCount = item['total_ratings'] is num
                ? (item['total_ratings'] as num).toInt()
                : int.tryParse(
                      (item['total_ratings'] ?? item['review_count'] ?? 0)
                          .toString(),
                    ) ??
                    0;
            final hasVisibleRating =
                rating != null && rating > 0 && reviewCount > 0;
            return InkWell(
              onTap: () => onTap(item),
              borderRadius: BorderRadius.circular(18),
              child: Container(
                decoration: BoxDecoration(
                  color: const Color(0xFFFCFCFD),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFFE9EDF3)),
                ),
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: const BorderRadius.horizontal(
                          left: Radius.circular(18)),
                      child: SizedBox(
                        width: 82,
                        height: double.infinity,
                        child: image.isNotEmpty
                            ? AppCachedImage(
                                imageUrl: image,
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) =>
                                    _similarFallback(),
                              )
                            : _similarFallback(),
                      ),
                    ),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.all(10),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              item['name']?.toString() ?? 'Restaurant',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: FoodFlowTheme.ink,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              _cuisineText(item),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 11,
                                color: FoodFlowTheme.muted,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                if (hasVisibleRating) ...[
                                  FoodFlowTheme.ratingBadge(rating,
                                      compact: true),
                                  const SizedBox(width: 6),
                                ],
                                if (hasVisibleRating)
                                  Expanded(
                                    child: Text(
                                      '$reviewCount ratings',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w600,
                                        color: FoodFlowTheme.muted,
                                      ),
                                    ),
                                  )
                                else
                                  _newBadge(),
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
          },
        ),
      ],
    );
  }

  Widget _similarFallback() {
    return Container(
      color: const Color(0xFFFFF0E6),
      alignment: Alignment.center,
      child: const Icon(
        Icons.restaurant_rounded,
        color: FoodFlowTheme.orange,
      ),
    );
  }

  Widget _newBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFF0A9443),
        borderRadius: BorderRadius.circular(999),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.star_rounded,
            color: Colors.white,
            size: 12,
          ),
          SizedBox(width: 4),
          Text(
            'New',
            style: TextStyle(
              color: Colors.white,
              fontSize: 10,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _RestaurantInfoSection extends StatelessWidget {
  final String? fssaiLicenseNumber;
  final VoidCallback onReportIssue;

  const _RestaurantInfoSection({
    required this.fssaiLicenseNumber,
    required this.onReportIssue,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFFDDFBF0), Color(0xFFF3F8FF)],
            ),
            borderRadius: BorderRadius.circular(22),
          ),
          child: const Row(
            children: [
              Expanded(
                child: Text(
                  'Delivering for people and planet',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                    color: Color(0xFF168C43),
                  ),
                ),
              ),
              Icon(Icons.eco_rounded, size: 28, color: Color(0xFF168C43)),
            ],
          ),
        ),
        const SizedBox(height: 14),
        const Text(
          '• Menu items, nutritional information and prices are set directly by the restaurant.',
          style: TextStyle(
              fontSize: 12, height: 1.45, color: FoodFlowTheme.inkSoft),
        ),
        const SizedBox(height: 8),
        const Text(
          '• Nutritional information values displayed are indicative and may vary depending on ingredients and portion size.',
          style: TextStyle(
              fontSize: 12, height: 1.45, color: FoodFlowTheme.inkSoft),
        ),
        const SizedBox(height: 8),
        const Text(
          '• Additional taxes and charges including delivery, platform and packaging fees may be applicable on cart.',
          style: TextStyle(
              fontSize: 12, height: 1.45, color: FoodFlowTheme.inkSoft),
        ),
        const SizedBox(height: 18),
        InkWell(
          onTap: onReportIssue,
          borderRadius: BorderRadius.circular(12),
          child: const Padding(
            padding: EdgeInsets.symmetric(vertical: 4),
            child: Text(
              'Report an issue with the menu ›',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: Color(0xFF168C43),
              ),
            ),
          ),
        ),
        const SizedBox(height: 18),
        Text(
          'FSSAI Lic. No. ${fssaiLicenseNumber?.trim().isNotEmpty == true ? fssaiLicenseNumber!.trim() : 'Not available'}',
          style: const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w600,
            color: FoodFlowTheme.muted,
          ),
        ),
      ],
    );
  }
}

class _ReviewHighlightsSection extends StatelessWidget {
  final double rating;
  final int reviewCount;
  final String commentText;
  final List<Map<String, dynamic>> reviews;
  final VoidCallback onViewAll;

  const _ReviewHighlightsSection({
    required this.rating,
    required this.reviewCount,
    required this.commentText,
    required this.reviews,
    required this.onViewAll,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            FoodFlowTheme.ratingBadge(rating <= 0 ? 0 : rating, compact: false),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Customer reviews and comments',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                      color: FoodFlowTheme.ink,
                    ),
                  ),
                  Text(
                    '$reviewCount ratings • $commentText',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: FoodFlowTheme.muted,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        ...reviews.take(3).map(
              (review) => Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFD),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFFE7ECF4)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEAF8EF),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            '${review['rating'] ?? 0} ★',
                            style: const TextStyle(
                              color: Color(0xFF0A9443),
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            review['user_name']?.toString() ?? 'Customer',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: FoodFlowTheme.ink,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      review['comment']?.toString() ?? '',
                      style: const TextStyle(
                        fontSize: 12,
                        height: 1.45,
                        color: FoodFlowTheme.inkSoft,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        if (reviewCount > 0) ...[
          const SizedBox(height: 2),
          InkWell(
            onTap: onViewAll,
            borderRadius: BorderRadius.circular(12),
            child: const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text(
                'View all ratings and reviews',
                style: TextStyle(
                  color: Color(0xFF168C43),
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _RestaurantStatusChip extends StatelessWidget {
  final bool isOpen;

  const _RestaurantStatusChip({required this.isOpen});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: isOpen ? const Color(0xFFE9F8EE) : const Color(0xFFFFEDED),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: isOpen ? const Color(0xFF9AD9B4) : const Color(0xFFFFB9B9),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isOpen ? Icons.check_circle_rounded : Icons.lock_clock_rounded,
            size: 15,
            color: isOpen ? const Color(0xFF0A9443) : const Color(0xFFD14343),
          ),
          const SizedBox(width: 5),
          Text(
            isOpen ? 'Open' : 'Closed',
            style: TextStyle(
              color: isOpen ? const Color(0xFF0A9443) : const Color(0xFFD14343),
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _ClosedRestaurantBanner extends StatelessWidget {
  final Restaurant restaurant;

  const _ClosedRestaurantBanner({required this.restaurant});

  @override
  Widget build(BuildContext context) {
    final message = _restaurantClosedMessage(restaurant);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F1),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFFFD0D0)),
      ),
      child: Row(
        children: [
          Lottie.asset(
            'assets/animations/closed.json',
            width: 56,
            height: 56,
            fit: BoxFit.contain,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: Color(0xFFD14343),
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

String _restaurantClosedMessage(Restaurant restaurant) {
  final nextOpening = restaurant.nextOpeningLabel?.trim();
  if (nextOpening != null && nextOpening.isNotEmpty) {
    return nextOpening;
  }
  return 'Ordering is unavailable until this restaurant reopens.';
}

class _CircleIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final Color? iconColor;

  const _CircleIconButton({
    required this.icon,
    required this.onTap,
    this.iconColor,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: 32,
        height: 32,
        decoration: const BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
        ),
        child: Icon(
          icon,
          size: 18,
          color: iconColor,
        ),
      ),
    );
  }
}

class _SearchPill extends StatelessWidget {
  final TextEditingController controller;
  final String hintText;

  const _SearchPill({
    required this.controller,
    required this.hintText,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 34,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: TextField(
        controller: controller,
        decoration: InputDecoration(
          hintText: hintText,
          prefixIcon: const Padding(
            padding: EdgeInsets.all(8),
            child: Icon(Icons.search_rounded, size: 18),
          ),
          filled: false,
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(vertical: 8),
        ),
      ),
    );
  }
}

class _QuickChip extends StatelessWidget {
  final String label;
  final IconData? icon;
  final bool selected;
  final VoidCallback onTap;

  const _QuickChip({
    required this.label,
    this.icon,
    this.selected = false,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFEDFAF2) : Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: selected ? const Color(0xFF83D4A5) : const Color(0xFFE7EAF0),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(
                icon,
                size: 14,
                color: selected ? const Color(0xFF0A9443) : FoodFlowTheme.ink,
              ),
              const SizedBox(width: 6),
            ],
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: selected ? const Color(0xFF0A9443) : FoodFlowTheme.ink,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OfferBanner extends StatelessWidget {
  final Map<String, dynamic> offer;

  const _OfferBanner({required this.offer});

  @override
  Widget build(BuildContext context) {
    final code = (offer['code'] ?? offer['coupon_code'] ?? '').toString();
    final title = code.isNotEmpty
        ? code
        : (offer['title'] ?? offer['name'] ?? 'Special offer').toString();
    final description = (offer['description'] ??
            offer['subtitle'] ??
            offer['offer_text'] ??
            'Restaurant offer available on this order.')
        .toString();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: const Color(0xFFF0F5FF),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(
              Icons.discount_rounded,
              color: Color(0xFF4B76E5),
              size: 18,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  description,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11,
                    color: FoodFlowTheme.inkSoft,
                  ),
                ),
              ],
            ),
          ),
          Text(
            'Offers',
            style: TextStyle(
              color: Colors.grey.shade500,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterSection extends StatelessWidget {
  final String title;
  final Widget child;

  const _FilterSection({
    required this.title,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8F9FD),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          child,
        ],
      ),
    );
  }
}
