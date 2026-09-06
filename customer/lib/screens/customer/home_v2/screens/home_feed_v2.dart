import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../providers/order_provider.dart';
import '../../../../widgets/common/app_cached_image.dart';
import '../../../../widgets/customer/offer_tile.dart';
import '../../../../widgets/customer/promotion_detail_sheet.dart';
import '../data/v2_models.dart';
import '../data/v2_repository.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_cards.dart';
import '../widgets/v2_dish_popup.dart';
import '../widgets/v2_glass.dart';
import 'network_error_v2.dart';

const String _vegModePrefsKey = 'customer_home_veg_only_mode';

class HomeFeedV2 extends StatefulWidget {
  const HomeFeedV2({
    super.key,
    required this.city,
    required this.address,
    required this.locationRevision,
    required this.notificationCount,
    required this.onLocationTap,
    required this.onWalletTap,
    required this.onNotificationTap,
  });

  final String city;
  final String address;
  final int locationRevision;
  final int notificationCount;
  final VoidCallback onLocationTap;
  final VoidCallback onWalletTap;
  final VoidCallback onNotificationTap;

  @override
  State<HomeFeedV2> createState() => _HomeFeedV2State();
}

class _HomeFeedV2State extends State<HomeFeedV2> {
  final V2Repository _repo = V2Repository.instance;

  bool _loading = true;
  bool _failed = false;
  bool _vegOnly = false;

  bool _searchPinnedInset = false;

  List<SectionV2> _sections = const [];
  List<Map<String, dynamic>> _cuisines = const [];
  List<Map<String, dynamic>> _nearby = const [];
  List<Map<String, dynamic>> _offers = const [];
  List<Map<String, dynamic>> _banners = const [];
  Map<String, dynamic> _priceFilter = const {};

  @override
  void initState() {
    super.initState();
    _restoreVeg();
    _load();
  }

  bool _onScroll(ScrollNotification n) {
    // Only toggles the status-bar inset on the pinned search bar, once per
    // direction (wide hysteresis so the extent change can't bounce it).
    if (n.metrics.axis != Axis.vertical) return false;
    final px = n.metrics.pixels;
    final inset = _searchPinnedInset ? px > 40 : px > 320;
    if (inset != _searchPinnedInset) {
      _searchPinnedInset = inset;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) setState(() {});
      });
    }
    return false;
  }

  @override
  void didUpdateWidget(covariant HomeFeedV2 old) {
    super.didUpdateWidget(old);
    if (old.locationRevision != widget.locationRevision) _load();
  }

  Future<void> _restoreVeg() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final v = prefs.getBool(_vegModePrefsKey) ?? false;
      if (mounted && v != _vegOnly) setState(() => _vegOnly = v);
    } catch (_) {}
  }

  Future<void> _setVeg(bool v) async {
    setState(() => _vegOnly = v);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(_vegModePrefsKey, v);
    } catch (_) {}
  }

  Future<void> _load() async {
    if (mounted && _sections.isEmpty) setState(() => _loading = true);
    try {
      final data = await _repo.loadHome();
      if (!mounted) return;
      setState(() {
        _sections = _consolidatePromoSections(data.sections);
        _cuisines = data.cuisines;
        _priceFilter = data.priceFilter;
        _banners = data.banners;
        _loading = false;
        _failed = false;
      });
      unawaited(_loadDeferred());
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _failed = _sections.isEmpty;
        });
      }
    }
  }

  Future<void> _loadDeferred() async {
    // Always pull the nearby feed so we can render a "Restaurants Near You"
    // list even when the admin layout has no restaurant section.
    final needOffers = _sections.any((s) => s.isPromotion);
    final res = await _repo.loadDeferred(needNearby: true, needOffers: needOffers);
    if (!mounted) return;
    setState(() {
      _nearby = res.nearby;
      _offers = res.offers;
    });
  }

  /// True once at least one rendered section is a restaurant list/grid.
  bool get _hasRestaurantSection => _sections.any((s) =>
      SectionV2.restaurantTypes.contains(s.type) &&
      !_isHeroBannerSection(s) &&
      (_serverRestaurants(s).isNotEmpty || _nearby.isNotEmpty));

  // -- resolution ----------------------------------------------------------

  List<Map<String, dynamic>> get _feed =>
      _vegOnly ? _nearby.where(v2LooksVeg).toList() : _nearby;

  List<Map<String, dynamic>> _serverRestaurants(SectionV2 s) {
    final items = s.items
        .where((e) => !e.containsKey('price') && !e.containsKey('restaurant_id'))
        .toList(growable: false);
    return _vegOnly ? items.where(v2LooksVeg).toList() : items;
  }

  List<Map<String, dynamic>> _resolveRestaurants(SectionV2 s) {
    final server = _serverRestaurants(s);
    return server.isNotEmpty ? server : _feed;
  }

  /// Raw dish maps for a section (so cards can build a MenuItem for inline add).
  List<Map<String, dynamic>> _resolveDishRaws(SectionV2 s) {
    final d = s.items
        .where((e) => DishV2.tryFrom(e) != null)
        .toList(growable: false);
    return _vegOnly
        ? d.where((e) => v2Bool(e['is_veg'] ?? e['veg'], fallback: true)).toList()
        : d;
  }

  /// Collapse every promo section into a single "Offers & Coupons" section,
  /// keeping the position of the first one.
  List<SectionV2> _consolidatePromoSections(List<SectionV2> sections) {
    final firstIdx = sections.indexWhere((s) => s.isPromotion);
    if (firstIdx < 0) return sections;

    final merged = <Map<String, dynamic>>[];
    final seen = <String>{};
    String? subtitle;
    for (final s in sections) {
      if (!s.isPromotion) continue;
      subtitle ??= s.subtitle;
      for (final o in s.items) {
        final key = (o['display_id'] ??
                o['id'] ??
                o['coupon_code'] ??
                o['title'] ??
                o.hashCode)
            .toString();
        if (seen.add(key)) merged.add(o);
      }
    }

    final out = <SectionV2>[];
    var injected = false;
    for (final s in sections) {
      if (!s.isPromotion) {
        out.add(s);
        continue;
      }
      if (injected) continue;
      injected = true;
      out.add(SectionV2(
        type: 'deals_for_you',
        title: 'Offers & Coupons',
        subtitle: subtitle?.trim().isNotEmpty == true
            ? subtitle
            : 'All active offers and coupons in one place',
        clientFeed: s.clientFeed,
        items: merged,
      ));
    }
    return out;
  }

  List<Map<String, dynamic>> _resolveOffers(SectionV2 s) {
    final server = s.items
        .where((e) => (e['source_type'] ?? 'promotion') == 'promotion')
        .toList(growable: false);
    return server.isNotEmpty ? server : _offers;
  }

  List<Map<String, dynamic>> _resolveCuisines(SectionV2 s) =>
      s.items.isNotEmpty ? s.items : _cuisines;

  // -- navigation --------------------------------------------------------

  void _openOfferV2(Map<String, dynamic> o, SectionV2 s) {
    bool hasRealItems(dynamic v) {
      if (v is! List) return false;
      return v.whereType<Map>().any((m) {
        final id = m['menu_item_id'] ?? m['id'] ?? m['item_id'];
        final name = (m['name'] ?? m['title'] ?? '').toString().trim();
        return (int.tryParse('${id ?? ''}') ?? 0) > 0 || name.isNotEmpty;
      });
    }

    final hasItems =
        hasRealItems(o['menu_items']) || hasRealItems(o['reward_menu_items']);
    final rId = v2Int(o['restaurant_id']);

    if (hasItems) {
      Navigator.pushNamed(context, '/promotion-products', arguments: {
        'title': (o['title'] ?? s.title ?? 'Promotion Items').toString(),
        'subtitle': (o['subtitle'] ?? o['description'] ?? s.subtitle)?.toString(),
        'promotion_type': o['promotion_type']?.toString(),
        'offers': [o],
      });
      return;
    }

    // Banner-only offer -> detail sheet (never an empty product screen).
    showPromotionDetailSheet(
      context,
      o,
      onPrimaryAction: rId > 0 ? () => v2OpenRestaurant(context, rId) : null,
    );
  }

  void _openCuisine(Map<String, dynamic> c) {
    final title =
        (c['name'] ?? c['title'] ?? c['cuisine_name'] ?? '').toString().trim();
    if (title.isEmpty) return;
    final typeText =
        (c['type'] ?? c['source'] ?? 'category').toString().toLowerCase();
    final isCuisine = typeText.contains('cuisine') ||
        c.containsKey('cuisine_id') ||
        c.containsKey('cuisine_name');
    final id = v2Int(c['cuisine_id'] ??
        c['category_id'] ??
        c['subcategory_id'] ??
        c['id']);
    v2OpenTaxonomy(
      context,
      title: title,
      filterType: isCuisine ? 'cuisine' : 'category',
      filterId: id > 0 ? id : null,
      label: title,
    );
  }

  Future<void> _openBanner(Map<String, dynamic> b) async {
    final redirect = b['redirect'];
    if (redirect is Map) {
      final type = redirect['type']?.toString();
      final id = v2Int(redirect['id'] ?? redirect['restaurant_id']);
      if (type == 'restaurant' && id > 0) return v2OpenRestaurant(context, id);
      if (type == 'menu_item' && id > 0) {
        return v2OpenRestaurant(context, v2Int(redirect['restaurant_id']),
            menuItemId: id);
      }
    }
    final rId = v2Int(b['redirect_restaurant_id']);
    if (rId > 0) return v2OpenRestaurant(context, rId);
    final link = (b['link'] ?? b['url'] ?? '').toString().trim();
    if (link.isNotEmpty) {
      final uri = Uri.tryParse(link);
      if (uri != null && await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    }
  }

  // -- build -----------------------------------------------------------

  static const Set<String> _bannerSectionTypes = {
    'banner_carousel',
    'hero_banner',
  };

  static const Set<String> _promoSectionTypes = {
    'promo',
    'promo_card',
    'promotion_banner',
    'promo_banner',
    'mid_banner',
  };

  /// A "Promo Widgets" section: admin stores these as a `banner_carousel`
  /// section titled "Promo Widgets" whose slides are `layout_mode: promo_card`
  /// / `banner_type: promo`. They belong mid-feed, never in the hero.
  bool _isPromoSection(SectionV2 s) {
    if (_promoSectionTypes.contains(s.type)) return true;
    if (!_bannerSectionTypes.contains(s.type)) return false;
    final title = (s.title ?? '').toLowerCase();
    if (title.contains('promo')) return true;
    if (s.items.isEmpty) return false;
    return s.items.every((b) =>
        (b['layout_mode'] ?? '').toString() == 'promo_card' ||
        (b['banner_type'] ?? '').toString() == 'promo');
  }

  bool _isRealHeroSection(SectionV2 s) =>
      _bannerSectionTypes.contains(s.type) && !_isPromoSection(s);

  bool _isCuisineSection(SectionV2 s) =>
      s.type == 'cuisine_grid' || s.type == 'categories';

  /// Banner list for the pinned hero: the first NON-promo banner section's
  /// slides, else the standalone /banners feed.
  List<Map<String, dynamic>> get _heroBanners {
    for (final s in _sections) {
      if (_isRealHeroSection(s)) {
        if (_banners.length > s.items.length) return _banners;
        return s.items.isNotEmpty ? s.items : _banners;
      }
    }
    return _banners;
  }

  bool _isHeroBannerSection(SectionV2 s) {
    if (!_isRealHeroSection(s)) return false;
    // Only the first real hero section is promoted; any others render in-feed.
    return identical(
      s,
      _sections.firstWhere(_isRealHeroSection, orElse: () => s),
    );
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return NotificationListener<ScrollNotification>(
      onNotification: _onScroll,
      child: RefreshIndicator(
      onRefresh: _load,
      color: p.accent,
      backgroundColor: p.bgMid,
      child: CustomScrollView(
        physics: const BouncingScrollPhysics(
          parent: AlwaysScrollableScrollPhysics(),
        ),
        // Modest look-ahead — a large cacheExtent means more image decode work
        // per scroll frame, which is what was causing the lag.
        cacheExtent: 300,
        slivers: [
          SliverToBoxAdapter(
            child: _HeroBannerBlock(
              banners: _heroBanners,
              city: widget.city,
              address: widget.address,
              notificationCount: widget.notificationCount,
              onLocationTap: widget.onLocationTap,
              onWalletTap: widget.onWalletTap,
              onNotificationTap: widget.onNotificationTap,
              onBannerTap: _openBanner,
            ),
          ),
          const SliverToBoxAdapter(child: SizedBox(height: 6)),
          SliverPersistentHeader(
            pinned: true,
            delegate: _SearchBarDelegate(
              topInset: MediaQuery.of(context).padding.top,
              reserveInset: _searchPinnedInset,
              vegOnly: _vegOnly,
              onSearchTap: () => v2OpenSearch(context),
              onVegChanged: _setVeg,
            ),
          ),
          if (_loading && _sections.isEmpty)
            const SliverToBoxAdapter(child: _FeedSkeleton())
          else if (_failed && _sections.isEmpty)
            SliverFillRemaining(
              hasScrollBody: false,
              child: NetworkErrorV2(embedded: true, onRetry: _load),
            )
          else ...[
            // Active orders surface in the floating V2OrderTrackerBar, not here.
            for (var i = 0; i < _sections.length; i++)
              if (!_isHeroBannerSection(_sections[i]))
                ..._sectionSlivers(_sections[i], i),
            // Fallback: always surface nearby restaurants if no section did.
            if (!_hasRestaurantSection && _feed.isNotEmpty)
              SliverToBoxAdapter(
                child: V2Entrance(
                  child: _shell(
                    title: 'Restaurants Near You',
                    subtitle: 'Discover the best restaurants in your area',
                    child: _vList(_feed),
                  ),
                ),
              ),
            SliverToBoxAdapter(
              child: SizedBox(
                // clears the bottom nav + floating cart bar + order-tracker bar
                height: MediaQuery.of(context).padding.bottom + 240,
              ),
            ),
          ],
        ],
      ),
      ),
    );
  }

  /// One feed section → one or more slivers. The cuisine section becomes a
  /// scrolling title + a pinned chip row; everything else is a plain adapter.
  List<Widget> _sectionSlivers(SectionV2 s, int i) {
    if (_isCuisineSection(s)) {
      final cuisines = _resolveCuisines(s);
      if (cuisines.isEmpty) return const [];
      return [
        SliverToBoxAdapter(
          child: V2Entrance(
            child: Padding(
              padding: const EdgeInsets.only(top: 22),
              child: V2SectionHeader(
                title: s.title ?? 'Explore Categories',
                subtitle: s.subtitle,
              ),
            ),
          ),
        ),
        SliverPersistentHeader(
          pinned: true,
          delegate: _CuisineChipsDelegate(
            cuisines: cuisines,
            onTap: _openCuisine,
          ),
        ),
      ];
    }
    return [
      SliverToBoxAdapter(
        child: RepaintBoundary(
          child: V2Entrance(
            delay: Duration(milliseconds: 40 * (i.clamp(0, 6))),
            child: _buildSection(s),
          ),
        ),
      ),
    ];
  }

  Widget _shell({
    required String title,
    String? subtitle,
    required Widget child,
  }) {
    return Padding(
      padding: const EdgeInsets.only(top: 22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          V2SectionHeader(title: title, subtitle: subtitle),
          child,
        ],
      ),
    );
  }

  Widget _hList({required double height, required List<Widget> children}) {
    return SizedBox(
      height: height,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: children.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (_, i) => children[i],
      ),
    );
  }

  Widget _buildSection(SectionV2 s) {
    switch (s.type) {
      case 'banner_carousel':
      case 'hero_banner':
      case 'promo':
      case 'promo_card':
      case 'promotion_banner':
      case 'promo_banner':
      case 'mid_banner':
        {
          // In-feed promo strip. The hero already consumed the real banner
          // section (see _isHeroBannerSection), so anything reaching here is a
          // promo / secondary carousel.
          final banners = s.items;
          if (banners.isEmpty) return const SizedBox.shrink();
          final rawTitle = (s.title ?? '').trim();
          final showTitle = rawTitle.isNotEmpty &&
              !rawTitle.toLowerCase().contains('promo widget') &&
              !rawTitle.toLowerCase().contains('banner');
          return Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (showTitle)
                  Padding(
                    padding: const EdgeInsets.only(left: 2, bottom: 10),
                    child: Text(rawTitle,
                        style: const TextStyle(
                            fontSize: 17, fontWeight: FontWeight.w800)),
                  ),
                BannerCarouselV2(banners: banners, onTap: _openBanner),
              ],
            ),
          );
        }
      case 'cuisine_grid':
      case 'categories':
        {
          final c = _resolveCuisines(s);
          if (c.isEmpty) return const SizedBox.shrink();
          return _shell(
            title: s.title ?? 'Explore Cuisines',
            subtitle: s.subtitle,
            child: _hList(
              height: 104,
              children: [
                for (final x in c)
                  CuisineChipV2(
                    name: (x['name'] ?? x['title'] ?? x['cuisine_name'] ?? '—')
                        .toString(),
                    imageUrl: v2ImageUrl(x, const [
                      'image_url',
                      'image',
                      'icon',
                      'icon_url',
                      'thumbnail',
                      'photo',
                    ]),
                    onTap: () => _openCuisine(x),
                  ),
              ],
            ),
          );
        }
      case 'popular_dishes':
        {
          final dishes = _resolveDishRaws(s);
          if (dishes.isNotEmpty) {
            return _shell(
              title: s.title ?? 'Popular Dishes',
              subtitle: s.subtitle,
              child: _hList(
                height: 204,
                children: [
                  for (final d in dishes)
                    DishCardV2(
                      raw: d,
                      onTap: () => showV2DishPopup(context, d),
                    ),
                ],
              ),
            );
          }
          final r = _resolveRestaurants(s);
          if (r.isEmpty) return const SizedBox.shrink();
          return _shell(
            title: s.title ?? 'Popular Dishes',
            subtitle: s.subtitle,
            child: _hList(
              height: 202,
              children: [
                for (final x in r.take(12))
                  RestaurantCardV2(
                    data: x,
                    onTap: () =>
                        v2OpenRestaurant(context, v2RestaurantId(x)),
                  ),
              ],
            ),
          );
        }
      case 'recommended_for_you':
        {
          // Small cards in a 2-row horizontally-scrolling grid — matches the
          // V1 production "Recommended For You" layout.
          final dishes = _resolveDishRaws(s);
          final rests = dishes.isEmpty
              ? _resolveRestaurants(s).take(12).toList()
              : const <Map<String, dynamic>>[];
          if (dishes.isEmpty && rests.isEmpty) return const SizedBox.shrink();
          return _shell(
            title: s.title ?? 'Recommended For You',
            subtitle: s.subtitle,
            child: LayoutBuilder(
              builder: (context, c) {
                final cardW =
                    ((c.maxWidth - 44) / 3).clamp(120.0, 150.0).toDouble();
                if (dishes.isNotEmpty) {
                  const rowH = 202.0;
                  return SizedBox(
                    height: 2 * rowH + 14,
                    child: GridView.builder(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
                      gridDelegate:
                          SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: 14,
                        crossAxisSpacing: 14,
                        mainAxisExtent: cardW,
                      ),
                      itemCount: dishes.length,
                      itemBuilder: (_, i) => DishCardV2(
                        raw: dishes[i],
                        width: cardW,
                        onTap: () => showV2DishPopup(context, dishes[i]),
                      ),
                    ),
                  );
                }
                final rowH = cardW / 1.3 + 76;
                return SizedBox(
                  height: 2 * rowH + 16,
                  child: GridView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
                    gridDelegate:
                        SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      mainAxisSpacing: 14,
                      crossAxisSpacing: 16,
                      mainAxisExtent: cardW,
                    ),
                    itemCount: rests.length,
                    itemBuilder: (_, i) => RecommendedGridCardV2(
                      data: rests[i],
                      onTap: () =>
                          v2OpenRestaurant(context, v2RestaurantId(rests[i])),
                    ),
                  ),
                );
              },
            ),
          );
        }
      case 'shop_by_brand':
        {
          final b = _serverRestaurants(s);
          if (b.isEmpty) return const SizedBox.shrink();
          return _shell(
            title: s.title ?? 'Shop By Brand',
            subtitle: s.subtitle,
            child: _hList(
              height: 118,
              children: [
                for (final x in b)
                  BrandBadgeV2(
                    name: (x['name'] ?? x['title'] ?? '').toString(),
                    imageUrl: v2ImageUrl(x, const [
                      'logo_image',
                      'image_url',
                      'image',
                      'banner_image',
                    ]),
                    onTap: () => v2OpenRestaurant(
                        context, v2Int(x['restaurant_id'] ?? x['id'])),
                  ),
              ],
            ),
          );
        }
      case 'deals_for_you':
      case 'promotion_type_section':
        {
          final offers = _resolveOffers(s);
          if (offers.isEmpty) return const SizedBox.shrink();
          // Render the exact same banner as V1 home (`_OfferTile`) via the
          // shared `OfferTile` widget — compact, reward + validity overlay only.
          return _shell(
            title: s.title ?? 'Deals for You',
            subtitle: s.subtitle,
            child: LayoutBuilder(
              builder: (context, constraints) {
                final cardWidth = math.max(
                  150.0,
                  (constraints.maxWidth - 50) / 2,
                );
                return SizedBox(
                  height: 154,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    itemCount: math.min(offers.length, 12),
                    separatorBuilder: (_, __) => const SizedBox(width: 10),
                    itemBuilder: (context, index) {
                      final o = offers[index];
                      return OfferTile(
                        offer: o,
                        width: cardWidth,
                        compact: true,
                        showCoupon: false,
                        showText: false,
                        showRewardAndValidityOnly: true,
                        onTap: () => _openOfferV2(o, s),
                      );
                    },
                  ),
                );
              },
            ),
          );
        }
      case 'menu_price_filter_config':
        {
          if (_priceFilter.isEmpty) return const SizedBox.shrink();
          final label = (_priceFilter['label'] ??
                  _priceFilter['title'] ??
                  'Budget picks')
              .toString();
          return Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 0),
            child: GlassPanel(
              radius: 18,
              onTap: () => v2OpenTaxonomy(
                context,
                title: label,
                filterType: 'price',
                priceMax: v2DoubleOrNull(_priceFilter['max_price']) ?? 250,
              ),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              child: Row(
                children: [
                  Icon(Icons.tune_rounded,
                      size: 18, color: V2Theme.of(context).accent),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      label,
                      style: TextStyle(
                        color: V2Theme.of(context).ink,
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  Icon(Icons.chevron_right_rounded,
                      color: V2Theme.of(context).inkFaint),
                ],
              ),
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
          final r = _resolveRestaurants(s);
          if (r.isEmpty) return const SizedBox.shrink();
          return _shell(
            title: s.title ?? 'Restaurants Near You',
            subtitle: s.subtitle,
            child: _vList(r),
          );
        }
      case 'featured_restaurants':
      case 'trending_near_you':
        {
          final r = _resolveRestaurants(s);
          if (r.isEmpty) return const SizedBox.shrink();
          return _shell(
            title: s.title ?? 'Featured Restaurants',
            subtitle: s.subtitle,
            child: _hList(
              height: 202,
              children: [
                for (final x in r.take(12))
                  RestaurantCardV2(
                    data: x,
                    onTap: () =>
                        v2OpenRestaurant(context, v2RestaurantId(x)),
                  ),
              ],
            ),
          );
        }
      case 'popular_restaurants':
      case 'new_arrivals':
      case 'restaurant_grid':
      default:
        {
          final r = _resolveRestaurants(s);
          if (r.isEmpty) return const SizedBox.shrink();
          return _shell(
            title: s.title ?? 'Restaurants',
            subtitle: s.subtitle,
            child: _vList(r),
          );
        }
    }
  }

  Widget _vList(List<Map<String, dynamic>> list, {int limit = 20}) {
    final shown = list.take(limit).toList(growable: false);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        children: [
          for (var i = 0; i < shown.length; i++)
            Padding(
              padding: EdgeInsets.only(bottom: i == shown.length - 1 ? 0 : 16),
              child: RestaurantBigCardV2(
                data: shown[i],
                onTap: () => v2OpenRestaurant(context, v2RestaurantId(shown[i])),
              ),
            ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------

/// Pinned hero: full-bleed banner carousel behind the status bar with the
/// location + wallet/notification controls overlaid, and a search + Veg-filter
/// row that freezes to the top when the banner scrolls away.
/// Full-bleed hero banner: the artwork covers the top (behind the status bar),
/// bottom corners curved, height driven by the real image aspect. The
/// location + wallet + notification controls are overlaid on it.
class _HeroBannerBlock extends StatefulWidget {
  const _HeroBannerBlock({
    required this.banners,
    required this.city,
    required this.address,
    required this.notificationCount,
    required this.onLocationTap,
    required this.onWalletTap,
    required this.onNotificationTap,
    required this.onBannerTap,
  });

  final List<Map<String, dynamic>> banners;
  final String city;
  final String address;
  final int notificationCount;
  final VoidCallback onLocationTap;
  final VoidCallback onWalletTap;
  final VoidCallback onNotificationTap;
  final ValueChanged<Map<String, dynamic>> onBannerTap;

  @override
  State<_HeroBannerBlock> createState() => _HeroBannerBlockState();
}

class _HeroBannerBlockState extends State<_HeroBannerBlock> {
  final PageController _controller = PageController();
  int _index = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _syncAutoAdvance();
  }

  @override
  void didUpdateWidget(covariant _HeroBannerBlock old) {
    super.didUpdateWidget(old);
    // Banners usually arrive after the first build (deferred load), so the
    // auto-advance timer has to be (re)started here, not just in initState.
    if (old.banners.length != widget.banners.length) {
      if (_index >= widget.banners.length) _index = 0;
      _syncAutoAdvance();
    }
  }

  void _syncAutoAdvance() {
    _timer?.cancel();
    if (widget.banners.length > 1) {
      _timer = Timer.periodic(const Duration(seconds: 4), (_) {
        if (!mounted || !_controller.hasClients) return;
        _controller.animateToPage(
          (_index + 1) % widget.banners.length,
          duration: const Duration(milliseconds: 480),
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

  String _url(Map<String, dynamic> b) => v2ImageUrl(b, const [
        'image_url',
        'image',
        'banner_image',
        'photo',
      ]);

  /// One banner slide. Honours the admin `layout_mode`: `text_image` overlays
  /// the title / description / CTA on the artwork; `full_image` (and
  /// `promo_card`) show the artwork alone.
  Widget _bannerSlide(BuildContext context, Map<String, dynamic> b,
      double w, double h, V2Palette p) {
    final img = AppCachedImage(
      imageUrl: _url(b),
      width: w,
      height: h,
      fit: BoxFit.cover,
      errorWidget: Container(color: p.accent.withOpacity(0.85)),
    );
    final mode = (b['layout_mode'] ?? 'full_image').toString();
    if (mode != 'text_image') return img;

    final title = (b['title'] ?? '').toString().trim();
    final desc = (b['description'] ?? '').toString().trim();
    final cta = (b['cta_label'] ?? '').toString().trim();
    if (title.isEmpty && desc.isEmpty && cta.isEmpty) return img;

    return Stack(
      fit: StackFit.expand,
      children: [
        img,
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
              colors: [Color(0xCC000000), Color(0x22000000)],
            ),
          ),
        ),
        Positioned(
          left: 18,
          right: 18,
          bottom: 34,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (title.isNotEmpty)
                Text(title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 19,
                        fontWeight: FontWeight.w900,
                        height: 1.15)),
              if (desc.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(desc,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        color: Colors.white.withOpacity(0.9),
                        fontSize: 12.5,
                        fontWeight: FontWeight.w500)),
              ],
              if (cta.isNotEmpty) ...[
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 7),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(cta,
                      style: TextStyle(
                          color: p.accent,
                          fontSize: 12,
                          fontWeight: FontWeight.w900)),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final mq = MediaQuery.of(context);
    final topInset = mq.padding.top;
    final width = mq.size.width;
    final banners = widget.banners;
    final single = banners.length == 1;

    // Fallback fixed height (used before an image resolves, and for carousels).
    var ratioPct = 42.0;
    if (banners.isNotEmpty) {
      final r = v2Double(banners.first['image_ratio'] ??
          banners.first['imageRatio']);
      if (r >= 20 && r <= 120) ratioPct = r;
    }
    // A hero banner should read as a band, not take over the screen: cap it at
    // ~62% of the width regardless of the admin ratio.
    final fallbackArt =
        (width * ratioPct / 100).clamp(150.0, width * 0.62).toDouble();

    Widget art;
    if (banners.isEmpty) {
      art = SizedBox(
        height: topInset + fallbackArt,
        child: DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [p.accent, Color.lerp(p.accent, Colors.black, 0.25)!],
            ),
          ),
        ),
      );
    } else if (single) {
      art = SizedBox(
        height: topInset + fallbackArt,
        width: width,
        child: _bannerSlide(context, banners.first, width,
            topInset + fallbackArt, p),
      );
    } else {
      art = SizedBox(
        height: topInset + fallbackArt,
        child: PageView.builder(
          controller: _controller,
          physics: const BouncingScrollPhysics(),
          onPageChanged: (i) => setState(() => _index = i),
          itemCount: banners.length,
          itemBuilder: (_, i) => GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: () => widget.onBannerTap(banners[i]),
            child: _bannerSlide(context, banners[i], width,
                topInset + fallbackArt, p),
          ),
        ),
      );
    }

    return ClipRRect(
      borderRadius: const BorderRadius.vertical(bottom: Radius.circular(26)),
      child: Stack(
        children: [
          if (single)
            GestureDetector(
              onTap: () => widget.onBannerTap(banners.first),
              child: art,
            )
          else
            art,
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            height: topInset + 84,
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.black.withOpacity(0.42),
                    Colors.black.withOpacity(0.0),
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            top: topInset + 6,
            left: 16,
            right: 16,
            child: Row(
              children: [
                Expanded(
                  child: V2Tappable(
                    onTap: widget.onLocationTap,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.location_on_rounded,
                                size: 16, color: Colors.white),
                            const SizedBox(width: 4),
                            Flexible(
                              child: Text(
                                widget.city,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 15,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            const Icon(Icons.keyboard_arrow_down_rounded,
                                size: 18, color: Colors.white),
                          ],
                        ),
                        const SizedBox(height: 2),
                        Text(
                          widget.address,
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
                const SizedBox(width: 10),
                _OverlayIconButton(
                  icon: Icons.account_balance_wallet_rounded,
                  onTap: widget.onWalletTap,
                ),
                const SizedBox(width: 8),
                _OverlayIconButton(
                  icon: Icons.notifications_rounded,
                  badge: widget.notificationCount,
                  onTap: widget.onNotificationTap,
                ),
              ],
            ),
          ),
          if (banners.length > 1)
            Positioned(
              left: 0,
              right: 0,
              bottom: 12,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(banners.length, (i) {
                  final active = i == _index;
                  return AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    width: active ? 18 : 6,
                    height: 6,
                    decoration: BoxDecoration(
                      color: active
                          ? Colors.white
                          : Colors.white.withOpacity(0.5),
                      borderRadius: BorderRadius.circular(3),
                    ),
                  );
                }),
              ),
            ),
        ],
      ),
    );
  }
}


/// Pinned search + veg-filter row. It carries the status-bar inset so it reads
/// correctly once the banner scrolls away and it sits at the very top.
class _SearchBarDelegate extends SliverPersistentHeaderDelegate {
  _SearchBarDelegate({
    required this.topInset,
    required this.reserveInset,
    required this.vegOnly,
    required this.onSearchTap,
    required this.onVegChanged,
  });

  final double topInset;

  /// Once the feed is scrolled the header reserves an extra [topInset] so the
  /// pinned search row clears the status bar; at rest it reserves only the row
  /// height so there's no dead gap under the hero.
  final bool reserveInset;
  final bool vegOnly;
  final VoidCallback onSearchTap;
  final ValueChanged<bool> onVegChanged;

  static const double _row = 58;

  @override
  double get minExtent => _row + (reserveInset ? topInset : 0);
  @override
  double get maxExtent => _row + (reserveInset ? topInset : 0);

  @override
  Widget build(
      BuildContext context, double shrinkOffset, bool overlapsContent) {
    final p = V2Theme.of(context);
    final pinned = overlapsContent || shrinkOffset > 2 || reserveInset;
    final pad = reserveInset ? topInset : 0.0;
    return SizedBox(
      height: _row + pad,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
        decoration: BoxDecoration(
          color: pinned ? p.bgTop : Colors.transparent,
          boxShadow: pinned
              ? [
                  BoxShadow(
                    color: p.shadow,
                    blurRadius: 12,
                    offset: const Offset(0, 6),
                  ),
                ]
              : null,
        ),
        child: AnimatedPadding(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          padding: EdgeInsets.fromLTRB(16, 6 + pad, 16, 8),
          child: Row(
              children: [
                Expanded(
                  child: V2Tappable(
                    onTap: onSearchTap,
                    child: GlassPanel(
                      radius: 16,
                      strong: true,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 11),
                      child: Row(
                        children: [
                          Icon(Icons.search_rounded,
                              size: 19, color: p.inkSoft),
                          const SizedBox(width: 8),
                          Text(
                            'Search restaurants & dishes',
                            style: TextStyle(
                              color: p.inkSoft,
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                _VegToggle(active: vegOnly, onChanged: onVegChanged),
              ],
            ),
          ),
        ),
      );
  }

  @override
  bool shouldRebuild(covariant _SearchBarDelegate old) =>
      old.vegOnly != vegOnly ||
      old.topInset != topInset ||
      old.reserveInset != reserveInset;
}

/// The cuisine chip row, pinned below the search bar. As it pins it shrinks
/// from a large pill to a compact one — a single lightweight widget per chip
/// (no cross-fade, no double list) so the transition stays smooth.
class _CuisineChipsDelegate extends SliverPersistentHeaderDelegate {
  _CuisineChipsDelegate({required this.cuisines, required this.onTap});

  final List<Map<String, dynamic>> cuisines;
  final ValueChanged<Map<String, dynamic>> onTap;

  static const double _expanded = 80;
  static const double _collapsed = 50;

  @override
  double get maxExtent => _expanded;
  @override
  double get minExtent => _collapsed;

  static const _imgKeys = [
    'image_url',
    'image',
    'icon',
    'icon_url',
    'thumbnail',
    'photo',
  ];

  @override
  Widget build(
      BuildContext context, double shrinkOffset, bool overlapsContent) {
    final p = V2Theme.of(context);
    final range = _expanded - _collapsed;
    final t = (range <= 0 ? 0.0 : shrinkOffset / range).clamp(0.0, 1.0);
    final pinned = overlapsContent || shrinkOffset > 2;

    // ClipRect guarantees nothing paints outside the (shrinking) header box
    // during a fast fling, even if a child is mid-relayout.
    return ClipRect(
      child: SizedBox(
        height: (_expanded - shrinkOffset).clamp(_collapsed, _expanded),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: pinned ? p.bgTop : Colors.transparent,
            boxShadow: pinned
                ? [
                    BoxShadow(
                      color: p.shadow,
                      blurRadius: 10,
                      offset: const Offset(0, 5),
                    ),
                  ]
                : null,
          ),
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            physics: const BouncingScrollPhysics(),
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: cuisines.length,
            separatorBuilder: (_, __) => const SizedBox(width: 8),
            itemBuilder: (context, i) {
              final c = cuisines[i];
              return _MorphCuisineChip(
                t: t,
                name: (c['name'] ?? c['title'] ?? c['cuisine_name'] ?? '—')
                    .toString(),
                imageUrl: v2ImageUrl(c, _imgKeys),
                onTap: () => onTap(c),
              );
            },
          ),
        ),
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _CuisineChipsDelegate old) =>
      old.cuisines != cuisines;
}

/// A single cuisine pill that lerps between a large and compact size by [t].
class _MorphCuisineChip extends StatelessWidget {
  const _MorphCuisineChip({
    required this.t,
    required this.name,
    required this.imageUrl,
    required this.onTap,
  });

  final double t;
  final String name;
  final String imageUrl;
  final VoidCallback onTap;

  double _l(double a, double b) => a + (b - a) * t;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    // Image size is FIXED across the morph so AppCachedImage never re-decodes
    // per scroll frame (that churn was the visible jank + transient overflow).
    // The pill's overall height stays ~46 at every t, comfortably inside the
    // 50–80 header box, so it can't overflow.
    const circle = 34.0;
    return Center(
      child: V2Tappable(
        onTap: onTap,
        child: Container(
          padding: EdgeInsets.fromLTRB(
              imageUrl.isEmpty ? _l(16, 14) : 5,
              5,
              _l(16, 13),
              5),
          decoration: BoxDecoration(
            color: p.glassTop,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: p.glassBorder),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (imageUrl.isNotEmpty) ...[
                ClipOval(
                  child: AppCachedImage(
                    imageUrl: imageUrl,
                    width: circle,
                    height: circle,
                    fit: BoxFit.cover,
                    errorWidget: v2ImageFallback(context, circle, circle),
                  ),
                ),
                SizedBox(width: _l(9, 7)),
              ],
              Text(name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                      color: p.inkSoft,
                      fontSize: _l(13.5, 12.5),
                      fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      ),
    );
  }
}



class _OverlayIconButton extends StatelessWidget {
  const _OverlayIconButton({
    required this.icon,
    required this.onTap,
    this.badge = 0,
  });

  final IconData icon;
  final VoidCallback onTap;
  final int badge;

  @override
  Widget build(BuildContext context) {
    return V2Tappable(
      onTap: onTap,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            padding: const EdgeInsets.all(9),
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.32),
              borderRadius: BorderRadius.circular(13),
              border: Border.all(color: Colors.white.withOpacity(0.28)),
            ),
            child: Icon(icon, size: 20, color: Colors.white),
          ),
          if (badge > 0)
            Positioned(
              right: -6,
              top: -6,
              child: Container(
                padding: const EdgeInsets.all(3),
                constraints:
                    const BoxConstraints(minWidth: 16, minHeight: 16),
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
                    height: 1,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _VegToggle extends StatelessWidget {
  const _VegToggle({required this.active, required this.onChanged});

  final bool active;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: () => onChanged(!active),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(
          color: active ? p.positive.withOpacity(0.22) : p.glassTop,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: active ? p.positive.withOpacity(0.7) : p.glassBorder,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            VegDot(isVeg: true, size: 12),
            const SizedBox(width: 6),
            Text(
              'Veg',
              style: TextStyle(
                color: active ? p.ink : p.inkSoft,
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

class _FeedSkeleton extends StatelessWidget {
  const _FeedSkeleton();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const V2Skeleton(width: 150, height: 18),
          const SizedBox(height: 14),
          Row(
            children: [
              for (var i = 0; i < 4; i++) ...[
                const V2Skeleton(width: 60, height: 60, radius: 18),
                const SizedBox(width: 14),
              ],
            ],
          ),
          const SizedBox(height: 28),
          const V2Skeleton(width: 180, height: 18),
          const SizedBox(height: 14),
          Row(
            children: const [
              Expanded(child: V2Skeleton(height: 150, radius: 20)),
              SizedBox(width: 12),
              Expanded(child: V2Skeleton(height: 150, radius: 20)),
            ],
          ),
        ],
      ),
    );
  }
}
