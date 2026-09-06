// Customer Home — V2 (glassmorphism).
// Customer Home — V2 (glassmorphism).
//
// A second, opt-in home experience. It is driven by the SAME admin-managed
// layout as the production home (`GET /home/sections`), so section order,
// titles and content match V1 — only the presentation is new: an aurora
// gradient backdrop with frosted-glass chrome, cards and navigation.
//
// Production home stays the default; users switch to this from
// Profile → Appearance. See `home_experience.dart` + `home_screen.dart`.

import 'dart:async';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../providers/order_provider.dart';
import '../../services/api_service.dart';
import '../../services/app_image_cache.dart';
import '../../services/location_service.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/customer/floating_cart_bar.dart';
import 'menu_price_filter_screen.dart';
import 'menu_taxonomy_filter_screen.dart';
import 'orders_screen.dart';
import 'profile_screen.dart';
import 'restaurant_detail_screen.dart';
import 'search_screen.dart';

// ---------------------------------------------------------------------------
// Palette
// ---------------------------------------------------------------------------

const Color _v2Base = Color(0xFF0B1020);
const Color _v2BaseSoft = Color(0xFF131A2E);
const Color _v2Ink = Color(0xFFF8FAFF);
const Color _v2InkSoft = Color(0xFFC3CCE4);
const Color _v2InkFaint = Color(0xFF8E99BC);
const Color _v2Accent = Color(0xFF6C8BFF);
const Color _v2Glow1 = Color(0xFF2563EB);
const Color _v2Glow2 = Color(0xFF7C3AED);
const Color _v2Glow3 = Color(0xFF06B6D4);
const Color _v2Glow4 = Color(0xFFFB923C);
const Color _v2Green = Color(0xFF34D399);

const String _vegModePrefsKey = 'customer_home_veg_only_mode';

// ===========================================================================
// Outer screen: aurora backdrop + tabs + glass bottom nav
// ===========================================================================

class CustomerHomeScreenV2 extends StatefulWidget {
  const CustomerHomeScreenV2({super.key});

  @override
  State<CustomerHomeScreenV2> createState() => _CustomerHomeScreenV2State();
}

class _CustomerHomeScreenV2State extends State<CustomerHomeScreenV2> {
  final LocationService _locationService = LocationService();
  final ApiService _api = ApiService();

  int _currentIndex = 0;
  int _locationRevision = 0;
  bool _showDiningMode = false;
  int _notificationCount = 0;
  DateTime? _lastBackPress;

  String _currentCity = 'Home';
  String _currentAddress = 'Select your delivery address';
  double? _lat;
  double? _lng;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadLocation();
      _loadNotificationCount();
    });
  }

  Future<void> _loadLocation() async {
    final saved = await _locationService.getSavedLocation();
    if (!mounted || saved == null) return;
    final city = (saved['city']?.toString().trim().isNotEmpty ?? false)
        ? saved['city'].toString().trim()
        : 'Home';
    final address = saved['address']?.toString().trim();
    setState(() {
      _currentCity = city;
      if (address != null && address.isNotEmpty) _currentAddress = address;
      _lat = _toDouble(saved['lat']);
      _lng = _toDouble(saved['lng']);
      _locationRevision++;
    });
  }

  Future<void> _loadNotificationCount() async {
    try {
      final res = await _api.get(
        ApiConstants.notifications,
        queryParams: const {'limit': 1, 'target_app': 'customer'},
      );
      final data = res is Map ? res['data'] : null;
      if (!mounted || data is! Map) return;
      setState(() {
        _notificationCount =
            int.tryParse('${data['unread_count'] ?? 0}') ?? 0;
      });
    } catch (_) {
      // Non-critical.
    }
  }

  Future<void> _openLocationPicker() async {
    await Navigator.pushNamed(context, '/addresses');
    if (mounted) await _loadLocation();
  }

  Widget _buildTab() {
    switch (_currentIndex) {
      case 1:
        return const SearchScreen(embedded: true);
      case 2:
        return const OrdersScreen();
      case 3:
        return ProfileScreen(
          onBackToHome: () => setState(() => _currentIndex = 0),
        );
      case 0:
      default:
        return _HomeFeedV2(
          currentCity: _currentCity,
          currentAddress: _currentAddress,
          locationRevision: _locationRevision,
          showDiningMode: _showDiningMode,
          notificationCount: _notificationCount,
          onLocationTap: _openLocationPicker,
          onWalletTap: () => Navigator.pushNamed(context, '/wallet'),
          onNotificationTap: () async {
            await Navigator.pushNamed(context, '/notifications');
            _loadNotificationCount();
          },
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).padding.bottom;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop) return;
        if (_currentIndex != 0) {
          setState(() => _currentIndex = 0);
          return;
        }
        final now = DateTime.now();
        if (_lastBackPress == null ||
            now.difference(_lastBackPress!) > const Duration(seconds: 2)) {
          _lastBackPress = now;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Press back again to exit'),
              duration: Duration(seconds: 2),
            ),
          );
          return;
        }
        SystemNavigator.pop();
      },
      child: Scaffold(
        extendBody: true,
        backgroundColor: _v2Base,
        body: Stack(
          children: [
            const Positioned.fill(child: _AuroraBackground()),
            Positioned.fill(
              child: DefaultTextStyle.merge(
                style: GoogleFonts.nunitoSans(color: _v2Ink),
                child: _buildTab(),
              ),
            ),
            Positioned(
              left: 0,
              right: 0,
              bottom: bottomInset + 92,
              child: CustomerFloatingCartBar(
                onTap: () => Navigator.pushNamed(context, '/cart'),
              ),
            ),
            // Matches production: the nav rides the home feed only; from another
            // tab the system back gesture returns to the feed (see PopScope).
            if (_currentIndex == 0)
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                child: _GlassBottomNav(
                  currentIndex: _currentIndex,
                  onTap: (i) => setState(() => _currentIndex = i),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

// ===========================================================================
// Aurora background
// ===========================================================================

class _AuroraBackground extends StatelessWidget {
  const _AuroraBackground();

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [_v2Base, _v2BaseSoft, _v2Base],
        ),
      ),
      child: Stack(
        children: [
          _blob(-120, -80, 320, _v2Glow1.withOpacity(0.55)),
          _blob(180, 40, 260, _v2Glow2.withOpacity(0.40)),
          _blob(-90, 360, 300, _v2Glow3.withOpacity(0.28)),
          _blob(220, 620, 320, _v2Glow4.withOpacity(0.22)),
          Positioned.fill(
            child: BackdropFilter(
              filter: ui.ImageFilter.blur(sigmaX: 90, sigmaY: 90),
              child: const SizedBox.expand(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _blob(double left, double top, double size, Color color) {
    return Positioned(
      left: left,
      top: top,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(colors: [color, color.withOpacity(0)]),
        ),
      ),
    );
  }
}

// ===========================================================================
// Glass bottom navigation
// ===========================================================================

class _GlassBottomNav extends StatelessWidget {
  const _GlassBottomNav({required this.currentIndex, required this.onTap});

  final int currentIndex;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).padding.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 10 + bottomInset),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(28),
        child: BackdropFilter(
          filter: ui.ImageFilter.blur(sigmaX: 22, sigmaY: 22),
          child: Container(
            height: 66,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.10),
              borderRadius: BorderRadius.circular(28),
              border: Border.all(color: Colors.white.withOpacity(0.16)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.35),
                  blurRadius: 24,
                  offset: const Offset(0, 12),
                ),
              ],
            ),
            child: Row(
              children: [
                _navItem(0, Icons.home_rounded, 'Home'),
                _navItem(1, Icons.search_rounded, 'Search'),
                _navItem(2, Icons.receipt_long_rounded, 'Orders'),
                _navItem(3, Icons.person_rounded, 'Profile'),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _navItem(int index, IconData icon, String label) {
    final active = currentIndex == index;
    return Expanded(
      child: InkWell(
        onTap: () => onTap(index),
        borderRadius: BorderRadius.circular(20),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          margin: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
          decoration: BoxDecoration(
            color: active ? Colors.white.withOpacity(0.16) : Colors.transparent,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: active
                  ? Colors.white.withOpacity(0.22)
                  : Colors.transparent,
            ),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon,
                  size: 21, color: active ? _v2Ink : _v2InkFaint),
              const SizedBox(height: 2),
              Text(
                label,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                  color: active ? _v2Ink : _v2InkFaint,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ===========================================================================
// Home feed
// ===========================================================================

class _HomeFeedV2 extends StatefulWidget {
  const _HomeFeedV2({
    required this.currentCity,
    required this.currentAddress,
    required this.locationRevision,
    required this.showDiningMode,
    required this.notificationCount,
    required this.onLocationTap,
    required this.onWalletTap,
    required this.onNotificationTap,
  });

  final String currentCity;
  final String currentAddress;
  final int locationRevision;
  final bool showDiningMode;
  final int notificationCount;
  final VoidCallback onLocationTap;
  final VoidCallback onWalletTap;
  final VoidCallback onNotificationTap;

  @override
  State<_HomeFeedV2> createState() => _HomeFeedV2State();
}

class _HomeFeedV2State extends State<_HomeFeedV2> {
  final ApiService _api = ApiService();
  final LocationService _locationService = LocationService();

  bool _loading = true;
  bool _failed = false;
  bool _vegOnly = false;

  List<_SectionV2> _sections = const [];
  List<Map<String, dynamic>> _cuisines = const [];
  List<Map<String, dynamic>> _nearby = const [];
  List<Map<String, dynamic>> _offers = const [];
  Map<String, dynamic> _priceFilter = const {};

  @override
  void initState() {
    super.initState();
    _restoreVegMode();
    _load();
  }

  @override
  void didUpdateWidget(covariant _HomeFeedV2 oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.locationRevision != widget.locationRevision ||
        oldWidget.showDiningMode != widget.showDiningMode) {
      _load();
    }
  }

  Future<void> _restoreVegMode() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final v = prefs.getBool(_vegModePrefsKey) ?? false;
      if (mounted && v != _vegOnly) setState(() => _vegOnly = v);
    } catch (_) {}
  }

  Future<void> _setVegMode(bool value) async {
    setState(() => _vegOnly = value);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_vegModePrefsKey, value);
    } catch (_) {}
  }

  // -- Loading ---------------------------------------------------------------

  Future<void> _load() async {
    if (mounted && _sections.isEmpty) setState(() => _loading = true);
    try {
      final saved = await _locationService.getSavedLocation();
      final lat = _toDouble(saved?['lat']);
      final lng = _toDouble(saved?['lng']);

      final results = await Future.wait<dynamic>([
        _safeGet(
          ApiConstants.homeSections,
          queryParams: {
            'platform': 'app',
            if (lat != null && lng != null) 'lat': lat,
            if (lat != null && lng != null) 'lng': lng,
            if (lat != null && lng != null) 'radius': 100,
          },
        ),
        _safeGet(ApiConstants.popularCuisines),
      ]);

      final rawSections = _extractList(results[0])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .where((e) => e['enabled'] != false)
          .toList(growable: false);

      _cuisines = _extractList(results[1])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(growable: false);

      for (final s in rawSections) {
        if (s['type']?.toString() == 'menu_price_filter_config') {
          final items = s['items'];
          if (items is List && items.isNotEmpty && items.first is Map) {
            _priceFilter = Map<String, dynamic>.from(items.first as Map);
          } else {
            _priceFilter = Map<String, dynamic>.from(s);
          }
        }
      }

      if (mounted) {
        setState(() {
          _sections = rawSections.map(_SectionV2.fromJson).toList();
          _loading = false;
          _failed = false;
        });
      }

      // Deferred: restaurant feed + promotions for client-feed / empty sections.
      unawaited(_loadDeferred(lat, lng));
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _failed = _sections.isEmpty;
        });
      }
    }
  }

  Future<void> _loadDeferred(double? lat, double? lng) async {
    final needsRestaurants = _sections.any((s) => s.needsRestaurantFeed);
    final needsOffers = _sections.any((s) => s.isPromotion);
    final futures = <Future<dynamic>>[];
    futures.add((needsRestaurants && lat != null && lng != null)
        ? _safeGet(
            widget.showDiningMode
                ? ApiConstants.diningRestaurants
                : ApiConstants.nearbyRestaurants,
            queryParams: {
              'lat': lat,
              'lng': lng,
              if (!widget.showDiningMode) 'radius': 100,
            },
          )
        : Future<dynamic>.value(null));
    futures.add(needsOffers
        ? _safeGet(ApiConstants.activeOffers)
        : Future<dynamic>.value(null));

    final res = await Future.wait(futures);
    if (!mounted) return;
    setState(() {
      _nearby = _extractList(res[0])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(growable: false);
      _offers = _extractList(res[1])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(growable: false);
    });
  }

  Future<dynamic> _safeGet(String url, {Map<String, dynamic>? queryParams}) {
    return _api
        .get(
          url,
          queryParams: queryParams,
          includeAuth: false,
          cacheResponse: true,
          cacheFirst: true,
        )
        .catchError((_) => null);
  }

  List<dynamic> _extractList(dynamic response) {
    if (response is List) return response;
    if (response is Map) {
      final data = response['data'];
      if (data is List) return data;
      if (data is Map) {
        for (final k in const ['data', 'items', 'sections', 'restaurants']) {
          if (data[k] is List) return data[k] as List;
        }
      }
      for (final k in const [
        'items',
        'sections',
        'restaurants',
        'categories',
        'banners',
        'offers',
      ]) {
        if (response[k] is List) return response[k] as List;
      }
    }
    return const <dynamic>[];
  }

  // -- Per-section item resolution (simplified port of V1) ------------------

  List<Map<String, dynamic>> _restaurantFeed() {
    final list = _nearby.isNotEmpty ? _nearby : const <Map<String, dynamic>>[];
    if (!_vegOnly) return list;
    return list.where(_looksVeg).toList(growable: false);
  }

  List<Map<String, dynamic>> _serverRestaurants(_SectionV2 s) {
    final items = s.items
        .where((e) => !e.containsKey('price') && !e.containsKey('restaurant_id'))
        .toList(growable: false);
    if (!_vegOnly) return items;
    return items.where(_looksVeg).toList(growable: false);
  }

  bool _looksVeg(Map<String, dynamic> m) {
    return _toBool(m['is_pure_veg'] ?? m['pure_veg'] ?? m['is_veg']);
  }

  List<Map<String, dynamic>> _resolveRestaurants(_SectionV2 s) {
    final server = _serverRestaurants(s);
    if (server.isNotEmpty) return server;
    return _restaurantFeed();
  }

  List<_DishV2> _resolveDishes(_SectionV2 s) {
    final dishes = s.items
        .map(_DishV2.tryFrom)
        .whereType<_DishV2>()
        .toList(growable: false);
    if (!_vegOnly) return dishes;
    return dishes.where((d) => d.isVeg).toList(growable: false);
  }

  List<Map<String, dynamic>> _resolveOffers(_SectionV2 s) {
    final server = s.items
        .where((e) => (e['source_type'] ?? 'promotion') == 'promotion')
        .toList(growable: false);
    if (server.isNotEmpty) return server;
    return _offers;
  }

  List<Map<String, dynamic>> _resolveCuisines(_SectionV2 s) {
    return s.items.isNotEmpty ? s.items : _cuisines;
  }

  // -- Navigation ----------------------------------------------------------

  void _openRestaurant(Map<String, dynamic> r) {
    final id = _restaurantId(r);
    if (id <= 0) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RestaurantDetailScreen(restaurantId: id),
      ),
    );
  }

  void _openRestaurantById(int id, {int? menuItemId}) {
    if (id <= 0) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RestaurantDetailScreen(
          restaurantId: id,
          initialMenuItemId: menuItemId,
        ),
      ),
    );
  }

  void _openCuisine(Map<String, dynamic> c) {
    final title = (c['name'] ?? c['title'] ?? c['cuisine_name'] ?? '')
        .toString()
        .trim();
    if (title.isEmpty) return;
    final typeText =
        (c['type'] ?? c['source'] ?? 'category').toString().toLowerCase();
    final isCuisine = typeText.contains('cuisine') ||
        c.containsKey('cuisine_id') ||
        c.containsKey('cuisine_name');
    final id = _toInt(c['cuisine_id'] ??
        c['category_id'] ??
        c['subcategory_id'] ??
        c['id']);
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => MenuTaxonomyFilterScreen(
          title: title,
          subtitle: 'Restaurants and dishes for $title',
          filterType: isCuisine ? 'cuisine' : 'category',
          filterId: id > 0 ? id : null,
          imageUrl: _imageUrl(c, const [
            'image_url',
            'image',
            'icon',
            'icon_url',
            'thumbnail',
            'photo',
          ]),
        ),
      ),
    );
  }

  void _openPriceFilter() {
    final cfg = _priceFilter;
    final label =
        (cfg['label'] ?? cfg['title'] ?? 'Filter').toString().trim();
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => MenuPriceFilterScreen(
          title: (cfg['screen_title'] ?? cfg['title'] ?? label).toString(),
          subtitle: (cfg['screen_subtitle'] ??
                  cfg['subtitle'] ??
                  'Menu items matched from restaurants near you')
              .toString(),
          minPrice: _toDouble(cfg['min_price']),
          maxPrice: _toDouble(cfg['max_price']) ?? 250,
        ),
      ),
    );
  }

  Future<void> _openBanner(Map<String, dynamic> b) async {
    final redirect = b['redirect'];
    if (redirect is Map) {
      final type = redirect['type']?.toString();
      final id = _toInt(redirect['id'] ?? redirect['restaurant_id']);
      if (type == 'restaurant' && id > 0) {
        _openRestaurantById(id);
        return;
      }
      if (type == 'menu_item' && id > 0) {
        _openRestaurantById(
          _toInt(redirect['restaurant_id']),
          menuItemId: id,
        );
        return;
      }
    }
    final rId = _toInt(b['redirect_restaurant_id']);
    if (rId > 0) {
      _openRestaurantById(rId);
      return;
    }
    final link = (b['link'] ?? b['url'] ?? '').toString().trim();
    if (link.isNotEmpty) {
      final uri = Uri.tryParse(link);
      if (uri != null && await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    }
  }

  // -- Build -------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _load,
      color: _v2Accent,
      backgroundColor: _v2BaseSoft,
      child: CustomScrollView(
        physics: const BouncingScrollPhysics(
          parent: AlwaysScrollableScrollPhysics(),
        ),
        slivers: [
          SliverAppBar(
            pinned: true,
            automaticallyImplyLeading: false,
            backgroundColor: Colors.transparent,
            surfaceTintColor: Colors.transparent,
            elevation: 0,
            toolbarHeight: 128,
            flexibleSpace: _GlassHeader(
              city: widget.currentCity,
              address: widget.currentAddress,
              notificationCount: widget.notificationCount,
              vegOnly: _vegOnly,
              onLocationTap: widget.onLocationTap,
              onWalletTap: widget.onWalletTap,
              onNotificationTap: widget.onNotificationTap,
              onSearchTap: () => Navigator.pushNamed(context, '/search'),
              onVegChanged: _setVegMode,
            ),
          ),
          if (_loading && _sections.isEmpty)
            const SliverFillRemaining(
              hasScrollBody: false,
              child: Center(
                child: CircularProgressIndicator(color: _v2Accent),
              ),
            )
          else if (_failed && _sections.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: _ErrorState(onRetry: _load),
            )
          else ...[
            SliverToBoxAdapter(
              child: Consumer<OrderProvider>(
                builder: (context, provider, _) {
                  final running = provider.orders
                      .where(_isRunningOrder)
                      .toList(growable: false);
                  if (running.isEmpty) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                    child: _GlassRunningOrderCard(
                      order: running.first,
                      onTap: () => Navigator.pushNamed(
                        context,
                        '/order/track',
                        arguments: running.first.id,
                      ),
                    ),
                  );
                },
              ),
            ),
            for (final section in _sections)
              SliverToBoxAdapter(child: _buildSection(section)),
            SliverToBoxAdapter(
              child: SizedBox(
                height: MediaQuery.of(context).padding.bottom + 150,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildSection(_SectionV2 s) {
    switch (s.type) {
      case 'banner_carousel':
      case 'hero_banner':
        {
          final banners = s.items;
          if (banners.isEmpty) return const SizedBox.shrink();
          return Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
            child: _GlassBannerCarousel(
              banners: banners,
              onTap: _openBanner,
              imageUrl: (m) => _imageUrl(m, const [
                'image_url',
                'image',
                'banner_image',
                'photo',
              ]),
            ),
          );
        }

      case 'cuisine_grid':
      case 'categories':
        {
          final cuisines = _resolveCuisines(s);
          if (cuisines.isEmpty) return const SizedBox.shrink();
          return _SectionShell(
            title: s.title ?? 'Explore Cuisines',
            subtitle: s.subtitle,
            child: SizedBox(
              height: 104,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: cuisines.length,
                separatorBuilder: (_, __) => const SizedBox(width: 12),
                itemBuilder: (_, i) => _GlassCuisineChip(
                  data: cuisines[i],
                  imageUrl: _imageUrl(cuisines[i], const [
                    'image_url',
                    'image',
                    'icon',
                    'icon_url',
                    'thumbnail',
                    'photo',
                  ]),
                  onTap: () => _openCuisine(cuisines[i]),
                ),
              ),
            ),
          );
        }

      case 'popular_dishes':
      case 'recommended_for_you':
        {
          final dishes = _resolveDishes(s);
          if (dishes.isNotEmpty) {
            return _SectionShell(
              title: s.title ??
                  (s.type == 'popular_dishes'
                      ? 'Popular Dishes'
                      : 'Recommended For You'),
              subtitle: s.subtitle,
              child: SizedBox(
                height: 182,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: dishes.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 12),
                  itemBuilder: (_, i) => _GlassDishCard(
                    dish: dishes[i],
                    onTap: () => _openRestaurantById(
                      dishes[i].restaurantId,
                      menuItemId: dishes[i].id > 0 ? dishes[i].id : null,
                    ),
                  ),
                ),
              ),
            );
          }
          // Fall back to restaurants when the section carries no dish items.
          final recRestaurants = _resolveRestaurants(s);
          if (recRestaurants.isEmpty) return const SizedBox.shrink();
          return _restaurantCarousel(
            s.title ?? 'Recommended For You',
            s.subtitle,
            recRestaurants,
          );
        }

      case 'shop_by_brand':
        {
          final brands = _serverRestaurants(s);
          if (brands.isEmpty) return const SizedBox.shrink();
          return _SectionShell(
            title: s.title ?? 'Shop By Brand',
            subtitle: s.subtitle,
            child: SizedBox(
              height: 118,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: brands.length,
                separatorBuilder: (_, __) => const SizedBox(width: 12),
                itemBuilder: (_, i) => _GlassBrandBadge(
                  data: brands[i],
                  imageUrl: _imageUrl(brands[i], const [
                    'logo_image',
                    'image_url',
                    'image',
                    'banner_image',
                  ]),
                  onTap: () => _openRestaurantById(
                    _toInt(brands[i]['restaurant_id'] ?? brands[i]['id']),
                  ),
                ),
              ),
            ),
          );
        }

      case 'deals_for_you':
      case 'promotion_type_section':
        {
          final offers = _resolveOffers(s);
          if (offers.isEmpty) return const SizedBox.shrink();
          return _SectionShell(
            title: s.title ?? 'Deals for You',
            subtitle: s.subtitle,
            child: SizedBox(
              height: 150,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: offers.length,
                separatorBuilder: (_, __) => const SizedBox(width: 12),
                itemBuilder: (_, i) => _GlassOfferCard(
                  data: offers[i],
                  onTap: () {
                    final rId = _toInt(offers[i]['restaurant_id']);
                    if (rId > 0) _openRestaurantById(rId);
                  },
                ),
              ),
            ),
          );
        }

      case 'menu_price_filter_config':
        {
          if (_priceFilter.isEmpty) return const SizedBox.shrink();
          return Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
            child: _GlassPriceFilterTile(
              label: (_priceFilter['label'] ??
                      _priceFilter['title'] ??
                      'Budget picks')
                  .toString(),
              onTap: _openPriceFilter,
            ),
          );
        }

      case 'quick_filter_shortcuts':
      case 'admin_offers':
      case 'promotion_types':
        return const SizedBox.shrink();

      case 'nearby_restaurants':
      case 'restaurant_discovery':
        {
          final list = _resolveRestaurants(s);
          if (list.isEmpty) return const SizedBox.shrink();
          return _restaurantList(
            s.title ?? 'Restaurants Near You',
            s.subtitle,
            list,
          );
        }

      case 'featured_restaurants':
      case 'trending_near_you':
        {
          final list = _resolveRestaurants(s);
          if (list.isEmpty) return const SizedBox.shrink();
          return _restaurantCarousel(
            s.title ?? 'Featured Restaurants',
            s.subtitle,
            list,
          );
        }

      case 'popular_restaurants':
      case 'new_arrivals':
      case 'restaurant_grid':
      default:
        {
          final list = _resolveRestaurants(s);
          if (list.isEmpty) return const SizedBox.shrink();
          return _restaurantList(s.title ?? 'Restaurants', s.subtitle, list);
        }
    }
  }

  Widget _restaurantCarousel(
    String title,
    String? subtitle,
    List<Map<String, dynamic>> list,
  ) {
    return _SectionShell(
      title: title,
      subtitle: subtitle,
      child: SizedBox(
        height: 202,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: list.length.clamp(0, 12),
          separatorBuilder: (_, __) => const SizedBox(width: 12),
          itemBuilder: (_, i) => _GlassRestaurantCard(
            data: list[i],
            imageUrl: _restaurantImage(list[i]),
            cuisineText: _cuisineText(list[i]),
            onTap: () => _openRestaurant(list[i]),
          ),
        ),
      ),
    );
  }

  Widget _restaurantList(
    String title,
    String? subtitle,
    List<Map<String, dynamic>> list,
  ) {
    final shown = list.take(6).toList(growable: false);
    return _SectionShell(
      title: title,
      subtitle: subtitle,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Column(
          children: [
            for (var i = 0; i < shown.length; i++)
              Padding(
                padding: EdgeInsets.only(bottom: i == shown.length - 1 ? 0 : 12),
                child: _GlassRestaurantRow(
                  data: shown[i],
                  imageUrl: _restaurantImage(shown[i]),
                  cuisineText: _cuisineText(shown[i]),
                  onTap: () => _openRestaurant(shown[i]),
                ),
              ),
          ],
        ),
      ),
    );
  }

  String _restaurantImage(Map<String, dynamic> r) => _imageUrl(r, const [
        'banner_image',
        'image_url',
        'image',
        'logo_image',
        'cover_image',
        'photo',
      ]);
}

// ===========================================================================
// Pinned glass header
// ===========================================================================

class _GlassHeader extends StatelessWidget {
  const _GlassHeader({
    required this.city,
    required this.address,
    required this.notificationCount,
    required this.vegOnly,
    required this.onLocationTap,
    required this.onWalletTap,
    required this.onNotificationTap,
    required this.onSearchTap,
    required this.onVegChanged,
  });

  final String city;
  final String address;
  final int notificationCount;
  final bool vegOnly;
  final VoidCallback onLocationTap;
  final VoidCallback onWalletTap;
  final VoidCallback onNotificationTap;
  final VoidCallback onSearchTap;
  final ValueChanged<bool> onVegChanged;

  @override
  Widget build(BuildContext context) {
    final topInset = MediaQuery.of(context).padding.top;
    return ClipRect(
      child: BackdropFilter(
        filter: ui.ImageFilter.blur(sigmaX: 20, sigmaY: 20),
        child: Container(
          padding: EdgeInsets.fromLTRB(16, topInset + 8, 16, 12),
          decoration: BoxDecoration(
            color: _v2Base.withOpacity(0.55),
            border: Border(
              bottom: BorderSide(color: Colors.white.withOpacity(0.10)),
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: onLocationTap,
                      borderRadius: BorderRadius.circular(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.location_on_rounded,
                                  size: 16, color: _v2Accent),
                              const SizedBox(width: 4),
                              Flexible(
                                child: Text(
                                  city,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: _v2Ink,
                                    fontSize: 15,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              const Icon(Icons.keyboard_arrow_down_rounded,
                                  size: 18, color: _v2InkSoft),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text(
                            address,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: _v2InkFaint,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  _GlassIconButton(
                    icon: Icons.account_balance_wallet_rounded,
                    onTap: onWalletTap,
                  ),
                  const SizedBox(width: 8),
                  _GlassIconButton(
                    icon: Icons.notifications_rounded,
                    badge: notificationCount,
                    onTap: onNotificationTap,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _GlassSearchField(onTap: onSearchTap),
                  ),
                  const SizedBox(width: 8),
                  _GlassTogglePill(
                    label: 'Veg',
                    active: vegOnly,
                    activeColor: _v2Green,
                    onChanged: onVegChanged,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ===========================================================================
// Glass building blocks
// ===========================================================================

class _GlassCard extends StatelessWidget {
  const _GlassCard({
    required this.child,
    this.radius = 22,
    this.padding = EdgeInsets.zero,
    this.blur = 16,
    this.opacity = 0.10,
    this.onTap,
  });

  final Widget child;
  final double radius;
  final EdgeInsetsGeometry padding;
  final double blur;
  final double opacity;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: BackdropFilter(
        filter: ui.ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: Colors.white.withOpacity(opacity),
            borderRadius: BorderRadius.circular(radius),
            border: Border.all(color: Colors.white.withOpacity(0.16)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.28),
                blurRadius: 20,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Material(
            type: MaterialType.transparency,
            child: InkWell(
              onTap: onTap,
              borderRadius: BorderRadius.circular(radius),
              child: Padding(padding: padding, child: child),
            ),
          ),
        ),
      ),
    );
  }
}

class _SectionShell extends StatelessWidget {
  const _SectionShell({
    required this.title,
    required this.child,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 0, 18, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: _v2Ink,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.2,
                  ),
                ),
                if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    subtitle!,
                    style: const TextStyle(
                      color: _v2InkFaint,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ],
            ),
          ),
          child,
        ],
      ),
    );
  }
}

class _GlassIconButton extends StatelessWidget {
  const _GlassIconButton({
    required this.icon,
    required this.onTap,
    this.badge = 0,
  });

  final IconData icon;
  final VoidCallback onTap;
  final int badge;

  @override
  Widget build(BuildContext context) {
    return _GlassCard(
      radius: 14,
      opacity: 0.12,
      blur: 10,
      onTap: onTap,
      padding: const EdgeInsets.all(9),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Icon(icon, size: 20, color: _v2Ink),
          if (badge > 0)
            Positioned(
              right: -6,
              top: -6,
              child: Container(
                padding: const EdgeInsets.all(3),
                constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                decoration: const BoxDecoration(
                  color: Color(0xFFEF4444),
                  shape: BoxShape.circle,
                ),
                child: Text(
                  badge > 9 ? '9+' : '$badge',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _GlassSearchField extends StatelessWidget {
  const _GlassSearchField({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return _GlassCard(
      radius: 16,
      opacity: 0.12,
      blur: 10,
      onTap: onTap,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: const [
          Icon(Icons.search_rounded, size: 19, color: _v2InkSoft),
          SizedBox(width: 8),
          Text(
            'Search restaurants & dishes',
            style: TextStyle(
              color: _v2InkSoft,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _GlassTogglePill extends StatelessWidget {
  const _GlassTogglePill({
    required this.label,
    required this.active,
    required this.onChanged,
    required this.activeColor,
  });

  final String label;
  final bool active;
  final ValueChanged<bool> onChanged;
  final Color activeColor;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => onChanged(!active),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(
          color: active
              ? activeColor.withOpacity(0.22)
              : Colors.white.withOpacity(0.10),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: active
                ? activeColor.withOpacity(0.7)
                : Colors.white.withOpacity(0.16),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 12,
              height: 12,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(3),
                border: Border.all(
                  color: active ? activeColor : _v2InkFaint,
                  width: 1.5,
                ),
              ),
              child: active
                  ? Center(
                      child: Container(
                        width: 6,
                        height: 6,
                        decoration: BoxDecoration(
                          color: activeColor,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    )
                  : null,
            ),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                color: active ? _v2Ink : _v2InkSoft,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// -- Restaurant cards -------------------------------------------------------

class _GlassRestaurantCard extends StatelessWidget {
  const _GlassRestaurantCard({
    required this.data,
    required this.imageUrl,
    required this.cuisineText,
    required this.onTap,
  });

  final Map<String, dynamic> data;
  final String imageUrl;
  final String cuisineText;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final rating = _ratingOf(data);
    final eta = _etaText(data);
    final open = _isOpen(data);
    return SizedBox(
      width: 220,
      child: _GlassCard(
        radius: 22,
        onTap: onTap,
        padding: const EdgeInsets.all(10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: Stack(
                children: [
                  AppCachedImage(
                    imageUrl: imageUrl,
                    width: 200,
                    height: 110,
                    fit: BoxFit.cover,
                    errorWidget: _imageFallback(200, 110),
                  ),
                  if (!open)
                    Positioned.fill(
                      child: Container(
                        color: Colors.black.withOpacity(0.45),
                        alignment: Alignment.center,
                        child: const Text(
                          'Closed',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            Text(
              (data['name'] ?? 'Restaurant').toString(),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _v2Ink,
                fontSize: 14.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (cuisineText.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                cuisineText,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: _v2InkFaint,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
            const SizedBox(height: 8),
            Row(
              children: [
                if (rating > 0) ...[
                  _RatingBadge(rating: rating),
                  const SizedBox(width: 8),
                ],
                if (eta.isNotEmpty)
                  Text(
                    eta,
                    style: const TextStyle(
                      color: _v2InkSoft,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _GlassRestaurantRow extends StatelessWidget {
  const _GlassRestaurantRow({
    required this.data,
    required this.imageUrl,
    required this.cuisineText,
    required this.onTap,
  });

  final Map<String, dynamic> data;
  final String imageUrl;
  final String cuisineText;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final rating = _ratingOf(data);
    final eta = _etaText(data);
    final open = _isOpen(data);
    return _GlassCard(
      radius: 20,
      onTap: onTap,
      padding: const EdgeInsets.all(10),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: Stack(
              children: [
                AppCachedImage(
                  imageUrl: imageUrl,
                  width: 84,
                  height: 84,
                  fit: BoxFit.cover,
                  errorWidget: _imageFallback(84, 84),
                ),
                if (!open)
                  Positioned.fill(
                    child: Container(
                      color: Colors.black.withOpacity(0.45),
                      alignment: Alignment.center,
                      child: const Text(
                        'Closed',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  (data['name'] ?? 'Restaurant').toString(),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _v2Ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (cuisineText.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    cuisineText,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _v2InkFaint,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Row(
                  children: [
                    if (rating > 0) ...[
                      _RatingBadge(rating: rating),
                      const SizedBox(width: 10),
                    ],
                    if (eta.isNotEmpty)
                      Text(
                        eta,
                        style: const TextStyle(
                          color: _v2InkSoft,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: _v2InkFaint),
        ],
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
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
      decoration: BoxDecoration(
        color: _v2Green.withOpacity(0.22),
        borderRadius: BorderRadius.circular(7),
        border: Border.all(color: _v2Green.withOpacity(0.55)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            rating.toStringAsFixed(1),
            style: const TextStyle(
              color: _v2Ink,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(width: 2),
          const Icon(Icons.star_rounded, size: 12, color: _v2Green),
        ],
      ),
    );
  }
}

// -- Cuisine / brand / dish / offer / banner -----------------------------

class _GlassCuisineChip extends StatelessWidget {
  const _GlassCuisineChip({
    required this.data,
    required this.imageUrl,
    required this.onTap,
  });

  final Map<String, dynamic> data;
  final String imageUrl;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final name =
        (data['name'] ?? data['title'] ?? data['cuisine_name'] ?? '—')
            .toString();
    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: 76,
        child: Column(
          children: [
            _GlassCard(
              radius: 22,
              padding: const EdgeInsets.all(4),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: AppCachedImage(
                  imageUrl: imageUrl,
                  width: 60,
                  height: 60,
                  fit: BoxFit.cover,
                  errorWidget: _imageFallback(60, 60),
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: _v2InkSoft,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GlassBrandBadge extends StatelessWidget {
  const _GlassBrandBadge({
    required this.data,
    required this.imageUrl,
    required this.onTap,
  });

  final Map<String, dynamic> data;
  final String imageUrl;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final name = (data['name'] ?? data['title'] ?? '').toString();
    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: 92,
        child: Column(
          children: [
            _GlassCard(
              radius: 20,
              padding: const EdgeInsets.all(6),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: AppCachedImage(
                  imageUrl: imageUrl,
                  width: 76,
                  height: 76,
                  fit: BoxFit.cover,
                  errorWidget: _imageFallback(76, 76),
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: _v2InkSoft,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GlassDishCard extends StatelessWidget {
  const _GlassDishCard({required this.dish, required this.onTap});

  final _DishV2 dish;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 160,
      child: _GlassCard(
        radius: 20,
        onTap: onTap,
        padding: const EdgeInsets.all(9),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: AppCachedImage(
                imageUrl: dish.imageUrl,
                width: 142,
                height: 92,
                fit: BoxFit.cover,
                errorWidget: _imageFallback(142, 92),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _vegDot(dish.isVeg),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    dish.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _v2Ink,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            if (dish.restaurantName.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                dish.restaurantName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: _v2InkFaint,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
            const SizedBox(height: 6),
            Text(
              dish.price > 0
                  ? formatCurrency(context, dish.price)
                  : 'View menu',
              style: const TextStyle(
                color: _v2Accent,
                fontSize: 12.5,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GlassOfferCard extends StatelessWidget {
  const _GlassOfferCard({required this.data, required this.onTap});

  final Map<String, dynamic> data;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final title = (data['title'] ??
            data['name'] ??
            data['reward_text'] ??
            data['code'] ??
            'Offer')
        .toString();
    final subtitle = (data['subtitle'] ??
            data['description'] ??
            data['restaurant_name'] ??
            data['validity_text'] ??
            '')
        .toString();
    return SizedBox(
      width: 240,
      child: _GlassCard(
        radius: 20,
        onTap: onTap,
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: _v2Glow4.withOpacity(0.24),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: _v2Glow4.withOpacity(0.5)),
              ),
              child: const Text(
                'OFFER',
                style: TextStyle(
                  color: _v2Ink,
                  fontSize: 9.5,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 1,
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _v2Ink,
                fontSize: 14,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (subtitle.trim().isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                subtitle,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: _v2InkFaint,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _GlassPriceFilterTile extends StatelessWidget {
  const _GlassPriceFilterTile({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return _GlassCard(
      radius: 18,
      onTap: onTap,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          const Icon(Icons.tune_rounded, size: 18, color: _v2Accent),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                color: _v2Ink,
                fontSize: 13.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: _v2InkFaint),
        ],
      ),
    );
  }
}

class _GlassBannerCarousel extends StatefulWidget {
  const _GlassBannerCarousel({
    required this.banners,
    required this.onTap,
    required this.imageUrl,
  });

  final List<Map<String, dynamic>> banners;
  final ValueChanged<Map<String, dynamic>> onTap;
  final String Function(Map<String, dynamic>) imageUrl;

  @override
  State<_GlassBannerCarousel> createState() => _GlassBannerCarouselState();
}

class _GlassBannerCarouselState extends State<_GlassBannerCarousel> {
  final PageController _controller = PageController(viewportFraction: 0.92);
  int _index = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    if (widget.banners.length > 1) {
      _timer = Timer.periodic(const Duration(seconds: 5), (_) {
        if (!mounted || !_controller.hasClients) return;
        final next = (_index + 1) % widget.banners.length;
        _controller.animateToPage(
          next,
          duration: const Duration(milliseconds: 450),
          curve: Curves.easeOutCubic,
        );
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          height: 158,
          child: PageView.builder(
            controller: _controller,
            onPageChanged: (i) => setState(() => _index = i),
            itemCount: widget.banners.length,
            itemBuilder: (_, i) {
              final banner = widget.banners[i];
              final title = (banner['title'] ?? '').toString();
              final desc = (banner['description'] ?? '').toString();
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: _GlassCard(
                  radius: 22,
                  padding: const EdgeInsets.all(6),
                  onTap: () => widget.onTap(banner),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(17),
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        AppCachedImage(
                          imageUrl: widget.imageUrl(banner),
                          fit: BoxFit.cover,
                          errorWidget: _imageFallback(400, 158),
                        ),
                        if (title.isNotEmpty || desc.isNotEmpty)
                          Positioned(
                            left: 0,
                            right: 0,
                            bottom: 0,
                            child: Container(
                              padding: const EdgeInsets.fromLTRB(14, 20, 14, 12),
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  begin: Alignment.topCenter,
                                  end: Alignment.bottomCenter,
                                  colors: [
                                    Colors.transparent,
                                    Colors.black.withOpacity(0.6),
                                  ],
                                ),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  if (title.isNotEmpty)
                                    Text(
                                      title,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  if (desc.isNotEmpty)
                                    Text(
                                      desc,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        color: Colors.white.withOpacity(0.85),
                                        fontSize: 11.5,
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
        if (widget.banners.length > 1) ...[
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(widget.banners.length, (i) {
              final active = i == _index;
              return AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: active ? 18 : 6,
                height: 6,
                decoration: BoxDecoration(
                  color: active ? _v2Accent : Colors.white.withOpacity(0.25),
                  borderRadius: BorderRadius.circular(3),
                ),
              );
            }),
          ),
        ],
      ],
    );
  }
}

class _GlassRunningOrderCard extends StatelessWidget {
  const _GlassRunningOrderCard({required this.order, required this.onTap});

  final Order order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return _GlassCard(
      radius: 20,
      onTap: onTap,
      opacity: 0.14,
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: _v2Accent.withOpacity(0.22),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _v2Accent.withOpacity(0.5)),
            ),
            child: const Icon(Icons.delivery_dining_rounded,
                size: 22, color: _v2Ink),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Order #${order.orderNumber}',
                  style: const TextStyle(
                    color: _v2Ink,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  order.statusText,
                  style: const TextStyle(
                    color: _v2InkSoft,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: _v2InkFaint),
        ],
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.onRetry});
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48, color: _v2InkFaint),
          const SizedBox(height: 12),
          const Text(
            'Could not load home',
            style: TextStyle(
              color: _v2Ink,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Check your connection and try again.',
            style: TextStyle(color: _v2InkFaint, fontSize: 12),
          ),
          const SizedBox(height: 16),
          OutlinedButton(
            onPressed: () => onRetry(),
            style: OutlinedButton.styleFrom(
              foregroundColor: _v2Ink,
              side: BorderSide(color: Colors.white.withOpacity(0.3)),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }
}

// ===========================================================================
// Models
// ===========================================================================

class _SectionV2 {
  _SectionV2({
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

  static const Set<String> _restaurantTypes = {
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
    if (!_restaurantTypes.contains(type)) return false;
    if (clientFeed) return true;
    return items.isEmpty;
  }

  factory _SectionV2.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    return _SectionV2(
      type: json['type']?.toString() ?? '',
      title: json['title']?.toString(),
      subtitle: json['subtitle']?.toString(),
      clientFeed: json['client_feed'] == true,
      items: rawItems is List
          ? rawItems
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList(growable: false)
          : const <Map<String, dynamic>>[],
    );
  }
}

class _DishV2 {
  _DishV2({
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

  static _DishV2? tryFrom(Map<String, dynamic> item) {
    final name = (item['name'] ?? item['title'] ?? '').toString().trim();
    if (name.isEmpty) return null;
    // A dish item carries a price / restaurant linkage; a plain restaurant
    // item does not. Skip anything that clearly isn't a dish.
    final hasDishSignal = item.containsKey('price') ||
        item.containsKey('discounted_price') ||
        item.containsKey('restaurant_id') ||
        item.containsKey('master_menu_item_id') ||
        item['restaurant'] is Map;
    if (!hasDishSignal) return null;

    final nested = item['restaurant'];
    final rMap = nested is Map ? Map<String, dynamic>.from(nested) : const {};
    return _DishV2(
      id: _toInt(item['id'] ?? item['menu_item_id'] ?? item['master_menu_item_id']),
      name: name,
      imageUrl: _imageUrl(item, const [
        'image_url',
        'image',
        'photo',
        'thumbnail',
        'banner_image',
      ]),
      price: _toDouble(item['discounted_price'] ??
              item['price'] ??
              item['min_price'] ??
              item['base_price']) ??
          0,
      restaurantId: _toInt(item['restaurant_id'] ??
          item['restaurantId'] ??
          rMap['id'] ??
          rMap['restaurant_id']),
      restaurantName: (item['restaurant_name'] ??
              item['restaurantName'] ??
              rMap['name'] ??
              '')
          .toString(),
      isVeg: _toBool(item['is_veg'] ?? item['veg'], fallback: true),
    );
  }
}

// ===========================================================================
// Shared helpers
// ===========================================================================

bool _isRunningOrder(Order order) {
  return !order.isDelivered &&
      !order.isCancelled &&
      const <String>{
        'pending',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'reached_pickup',
        'picked_up',
        'on_the_way',
      }.contains(order.status);
}

int _toInt(dynamic v, {int fallback = 0}) {
  if (v is int) return v;
  if (v is double) return v.toInt();
  if (v is String) {
    return int.tryParse(v) ?? double.tryParse(v)?.toInt() ?? fallback;
  }
  return fallback;
}

double? _toDouble(dynamic v) {
  if (v is double) return v;
  if (v is int) return v.toDouble();
  if (v is String) return double.tryParse(v);
  return null;
}

bool _toBool(dynamic v, {bool fallback = false}) {
  if (v is bool) return v;
  if (v is int) return v != 0;
  if (v is String) {
    final s = v.trim().toLowerCase();
    return s == 'true' || s == '1' || s == 'yes' || s == 'y';
  }
  return fallback;
}

int _restaurantId(Map<String, dynamic> r) {
  return _toInt(r['id'] ??
      r['restaurant_id'] ??
      r['restaurantId'] ??
      (r['restaurant'] is Map ? (r['restaurant'] as Map)['id'] : null));
}

double _ratingOf(Map<String, dynamic> r) {
  return _toDouble(r['rating'] ??
          r['avg_rating'] ??
          r['average_rating'] ??
          r['review_rating']) ??
      0;
}

bool _isOpen(Map<String, dynamic> r) {
  final v = r['is_open'] ?? r['is_open_now'] ?? r['open'] ?? r['isOpen'];
  if (v == null) return true;
  return _toBool(v, fallback: true);
}

String _etaText(Map<String, dynamic> r) {
  final mins = _toInt(r['delivery_time'] ??
      r['deliveryTime'] ??
      r['eta'] ??
      r['preparation_time']);
  if (mins <= 0) return '';
  return '$mins min';
}

String _cuisineText(Map<String, dynamic> r) {
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

String _imageUrl(Map<String, dynamic> item, List<String> keys) {
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

Widget _imageFallback(double w, double h) {
  return Container(
    width: w,
    height: h,
    color: Colors.white.withOpacity(0.06),
    alignment: Alignment.center,
    child: Icon(
      Icons.restaurant_rounded,
      color: Colors.white.withOpacity(0.3),
      size: 22,
    ),
  );
}

Widget _vegDot(bool isVeg) {
  final color = isVeg ? _v2Green : const Color(0xFFEF4444);
  return Container(
    width: 12,
    height: 12,
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(3),
      border: Border.all(color: color, width: 1.5),
    ),
    child: Center(
      child: Container(
        width: 5,
        height: 5,
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(2),
        ),
      ),
    ),
  );
}
