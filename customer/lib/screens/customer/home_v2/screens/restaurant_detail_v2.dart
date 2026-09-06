import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../models/menu_item.dart';
import '../../../../models/restaurant.dart';
import '../../../../providers/cart_provider.dart';
import '../../../../utils/currency_utils.dart';
import '../../../../widgets/common/app_cached_image.dart';
import '../data/v2_repository.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_cards.dart';
import '../widgets/v2_cart_actions.dart';
import '../widgets/v2_cart_bar.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';
import 'restaurant_reviews_v2.dart';

const String _kAllCategories = 'All items';

class RestaurantDetailV2 extends StatefulWidget {
  const RestaurantDetailV2({
    super.key,
    required this.restaurantId,
    this.initialMenuItemId,
  });

  final int restaurantId;
  final int? initialMenuItemId;

  @override
  State<RestaurantDetailV2> createState() => _RestaurantDetailV2State();
}

class _RestaurantDetailV2State extends State<RestaurantDetailV2> {
  final V2Repository _repo = V2Repository.instance;

  bool _loading = true;
  bool _failed = false;
  bool _vegOnly = false;
  String _category = _kAllCategories;
  String _query = '';
  bool _menuSearchInset = false;

  static bool _mIsVeg(Map<String, dynamic> m) =>
      v2Bool(m['is_veg'], fallback: true);

  /// Ordered category chips for the pinned bar: "All items" first, then the
  /// menu's own categories in menu order, each with its item count over the
  /// current veg filter.
  List<(String, int)> get _categoryChips {
    final counts = _categoryCounts;
    final ordered = <String>[];
    final seen = <String>{};
    final src = _vegOnly ? _menu.where(_mIsVeg) : _menu;
    for (final m in src) {
      final l = _labelOf(m);
      if (seen.add(l)) ordered.add(l);
    }
    return [
      (_kAllCategories, counts.values.fold(0, (a, b) => a + b)),
      for (final l in ordered) (l, counts[l] ?? 0),
    ];
  }

  void _setVegOnly(bool v) {
    setState(() {
      _vegOnly = v;
      // A category may vanish from the veg-only set — fall back to "All".
      if (v && _category != _kAllCategories &&
          !_categoryChips.any((c) => c.$1 == _category)) {
        _category = _kAllCategories;
      }
    });
  }

  final TextEditingController _searchCtl = TextEditingController();
  Map<String, dynamic> _restaurant = const {};
  List<Map<String, dynamic>> _menu = const [];
  List<Map<String, dynamic>> _promos = const [];

  @override
  void dispose() {
    _searchCtl.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failed = false;
    });
    try {
      final results = await Future.wait([
        _repo.restaurant(widget.restaurantId),
        _repo.restaurantMenu(widget.restaurantId),
      ]);
      if (!mounted) return;
      setState(() {
        _restaurant = results[0] as Map<String, dynamic>;
        _menu = results[1] as List<Map<String, dynamic>>;
        _loading = false;
      });
      _repo.restaurantPromos(widget.restaurantId).then((v) {
        if (mounted && v.isNotEmpty) setState(() => _promos = v);
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _failed = true;
        });
      }
    }
  }

  String _labelOf(Map<String, dynamic> item) {
    final sub = (item['subcategory_name'] ?? item['sub_category_name'] ?? '')
        .toString()
        .trim();
    if (sub.isNotEmpty) return sub;
    final cat = (item['category_name'] ??
            (item['category'] is Map ? item['category']['name'] : item['category']) ??
            '')
        .toString()
        .trim();
    if (cat.isNotEmpty) {
      final parts = cat
          .split('/')
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty)
          .toList();
      return parts.length > 1 ? parts.last : cat;
    }
    return 'Recommended for you';
  }

  List<Map<String, dynamic>> get _visibleItems {
    var src = _menu;
    if (_vegOnly) {
      src = src.where(_mIsVeg).toList(growable: false);
    }
    if (_category != _kAllCategories) {
      src = src.where((m) => _labelOf(m) == _category).toList(growable: false);
    }
    if (_query.isNotEmpty) {
      final q = _query.toLowerCase();
      src = src
          .where((m) =>
              (m['name'] ?? '').toString().toLowerCase().contains(q) ||
              (m['description'] ?? '').toString().toLowerCase().contains(q))
          .toList(growable: false);
    }
    return src;
  }

  /// category -> count, over the veg-filtered set.
  Map<String, int> get _categoryCounts {
    final out = <String, int>{};
    final src = _vegOnly
        ? _menu.where((m) => v2Bool(m['is_veg'], fallback: true))
        : _menu;
    for (final m in src) {
      out.update(_labelOf(m), (v) => v + 1, ifAbsent: () => 1);
    }
    return out;
  }

  Map<String, List<Map<String, dynamic>>> get _grouped {
    final out = <String, List<Map<String, dynamic>>>{};
    for (final item in _visibleItems) {
      out.putIfAbsent(_labelOf(item), () => []).add(item);
    }
    return out;
  }

  void _add(Map<String, dynamic> raw) {
    try {
      final item = MenuItem.fromJson(raw);
      final restaurant = Restaurant.fromJson({
        ..._restaurant,
        'id': widget.restaurantId,
      });
      v2AddToCart(context, item, restaurant);
    } catch (_) {}
  }

  void _remove(Map<String, dynamic> raw) {
    final id = v2Int(raw['id'] ?? raw['menu_item_id']);
    if (id > 0) context.read<CartProvider>().decrementQuantity(id);
  }

  void _openReviews() {
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => RestaurantReviewsV2(restaurant: _restaurant),
    ));
  }

  /// Full-width strip of the restaurant's own offers / promo codes — a
  /// carousel with dot indicators when there's more than one.
  Widget _offerStrip() {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(left: 20, bottom: 8),
            child: Text('Offers for you',
                style: TextStyle(
                    color: p.ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w900)),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: _RestaurantOfferCarousel(offers: _promos),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      safeTop: false,
      floating: Stack(
        children: [
          V2CartBar(
            onTap: () => v2OpenCart(context),
            bottomOffset: 20,
          ),
          // Floating glass back + save over the full-bleed hero. Once the menu
          // bar pins under the status bar these are hidden and the bar carries
          // its own back / save so nothing overlaps.
          if (!_menuSearchInset) ...[
            Positioned(
              top: MediaQuery.of(context).padding.top + 6,
              left: 14,
              child: V2Tappable(
                onTap: () => Navigator.of(context).maybePop(),
                child: Container(
                  padding: const EdgeInsets.all(9),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.34),
                    borderRadius: BorderRadius.circular(13),
                    border: Border.all(color: Colors.white.withOpacity(0.28)),
                  ),
                  child: const Icon(Icons.arrow_back_rounded,
                      size: 20, color: Colors.white),
                ),
              ),
            ),
            if (!_loading && !_failed)
              Positioned(
                top: MediaQuery.of(context).padding.top + 10,
                right: 18,
                child: SaveRestaurantButton(id: widget.restaurantId),
              ),
          ],
        ],
      ),
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : _failed
              ? V2EmptyState(
                  icon: Icons.cloud_off_rounded,
                  title: 'Could not load restaurant',
                  onRetry: _load,
                )
              : NotificationListener<ScrollNotification>(
                  onNotification: (n) {
                    if (n.metrics.axis != Axis.vertical) return false;
                    final px = n.metrics.pixels;
                    final next = _menuSearchInset ? px > 60 : px > 300;
                    if (next != _menuSearchInset) {
                      WidgetsBinding.instance.addPostFrameCallback((_) {
                        if (mounted) setState(() => _menuSearchInset = next);
                      });
                    }
                    return false;
                  },
                  child: RefreshIndicator(
                  onRefresh: _load,
                  color: p.accent,
                  backgroundColor: p.bgMid,
                  child: CustomScrollView(
                    physics: const BouncingScrollPhysics(
                      parent: AlwaysScrollableScrollPhysics(),
                    ),
                    slivers: [
                      SliverToBoxAdapter(child: _hero()),
                      if (_promos.isNotEmpty)
                        SliverToBoxAdapter(child: _offerStrip()),
                      SliverToBoxAdapter(child: _menuTitle()),
                      SliverPersistentHeader(
                        pinned: true,
                        delegate: _MenuHeaderDelegate(
                          topInset: _menuSearchInset
                              ? MediaQuery.of(context).padding.top
                              : 0,
                          query: _query,
                          controller: _searchCtl,
                          categories: _categoryChips,
                          selectedCategory: _category,
                          onCategory: (c) => setState(() => _category = c),
                          vegOnly: _vegOnly,
                          onVegChanged: _setVegOnly,
                          restaurantId: widget.restaurantId,
                          onBack: () => Navigator.of(context).maybePop(),
                          onChanged: (v) => setState(() => _query = v.trim()),
                          onClear: () {
                            _searchCtl.clear();
                            setState(() => _query = '');
                          },
                        ),
                      ),
                      if (_menu.isEmpty)
                        const SliverFillRemaining(
                          hasScrollBody: false,
                          child: V2EmptyState(
                            icon: Icons.restaurant_menu_rounded,
                            title: 'Menu coming soon',
                            message: 'This restaurant has not published its menu yet.',
                          ),
                        )
                      else if (_visibleItems.isEmpty)
                        SliverToBoxAdapter(
                          child: Padding(
                            padding: const EdgeInsets.only(top: 30),
                            child: V2EmptyState(
                              icon: Icons.filter_alt_off_rounded,
                              title: 'No items in "$_category"',
                              onRetry: () =>
                                  setState(() => _category = _kAllCategories),
                            ),
                          ),
                        )
                      else
                        SliverList(
                          delegate: SliverChildListDelegate([
                            for (final entry in _grouped.entries) ...[
                              Padding(
                                padding: const EdgeInsets.fromLTRB(20, 18, 20, 10),
                                child: Row(
                                  children: [
                                    Text(
                                      entry.key,
                                      style: TextStyle(
                                        color: p.ink,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w900,
                                        letterSpacing: -0.2,
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    Text(
                                      '${entry.value.length}',
                                      style: TextStyle(
                                        color: p.inkFaint,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              for (final item in entry.value)
                                Padding(
                                  padding:
                                      const EdgeInsets.fromLTRB(16, 0, 16, 12),
                                  child: _MenuItemCard(
                                    data: item,
                                    restaurantOpen: v2IsOpen(_restaurant),
                                    onAdd: () => _add(item),
                                    onRemove: () => _remove(item),
                                  ),
                                ),
                            ],
                            _footer(),
                            SizedBox(
                              height: MediaQuery.of(context).padding.bottom + 130,
                            ),
                          ]),
                        ),
                    ],
                  ),
                ),
                ),
    );
  }

  /// Just the "Menu" heading — scrolls away; the search field + category
  /// chips below it are a pinned header so they stay reachable down a long menu.
  Widget _menuTitle() {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
      child: Text(
        'Menu',
        style: TextStyle(
          color: p.ink,
          fontSize: 19,
          fontWeight: FontWeight.w900,
          letterSpacing: -0.3,
        ),
      ),
    );
  }

  Widget _hero() {
    final p = V2Theme.of(context);
    final rating = v2RatingOf(_restaurant);
    final ratingCount = v2Int(_restaurant['total_ratings'] ??
        _restaurant['ratings_count'] ??
        _restaurant['review_count']);
    final cuisine = v2CuisineText(_restaurant);
    final eta = v2EtaText(_restaurant);
    final address = (_restaurant['address'] ??
            _restaurant['full_address'] ??
            _restaurant['area'] ??
            '')
        .toString()
        .trim();
    final topInset = MediaQuery.of(context).padding.top;
    final width = MediaQuery.sizeOf(context).width;
    final imgHeight = (width * 0.62).clamp(200.0, 320.0).toDouble();
    final open = v2IsOpen(_restaurant);
    final logoUrl = v2ImageUrl(_restaurant, const [
      'logo_image',
      'logo',
      'logo_url',
    ]);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Full-bleed cover image: flush to the top edge (under the status
        // bar), both bottom corners curved. Restaurant logo overlaid.
        ClipRRect(
          borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
          child: Stack(
            children: [
              AppCachedImage(
                imageUrl: v2ImageUrl(_restaurant, kRestaurantImageKeys),
                height: topInset + imgHeight,
                width: double.infinity,
                fit: BoxFit.cover,
                errorWidget: v2ImageFallback(context, width, topInset + imgHeight),
              ),
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                height: 100,
                child: const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Color(0x40000000)],
                    ),
                  ),
                ),
              ),
              if (logoUrl.isNotEmpty)
                Positioned(
                  left: 18,
                  bottom: 14,
                  child: Container(
                    width: 54,
                    height: 54,
                    padding: const EdgeInsets.all(3),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.28),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: ClipOval(
                      child: AppCachedImage(
                        imageUrl: logoUrl,
                        width: 48,
                        height: 48,
                        fit: BoxFit.cover,
                        errorWidget: v2ImageFallback(context, 48, 48),
                      ),
                    ),
                  ),
                ),
              Positioned(
                right: 16,
                bottom: 16,
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: (open ? p.positive : p.danger),
                    borderRadius: BorderRadius.circular(9),
                  ),
                  child: Text(
                    open ? 'Open now' : 'Closed',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                (_restaurant['name'] ?? 'Restaurant').toString(),
                style: TextStyle(
                  color: p.ink,
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.4,
                ),
              ),
              if (cuisine.isNotEmpty) ...[
                const SizedBox(height: 3),
                Text(
                  cuisine,
                  style: TextStyle(color: p.inkFaint, fontSize: 12.5),
                ),
              ],
              const SizedBox(height: 12),
              Row(
                children: [
                  if (rating > 0) ...[
                    V2Tappable(
                      onTap: _openReviews,
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          RatingPill(rating: rating),
                          const SizedBox(width: 5),
                          Text(
                            ratingCount > 0
                                ? '$ratingCount reviews'
                                : 'See reviews',
                            style: TextStyle(
                                color: p.accent,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w800),
                          ),
                          Icon(Icons.chevron_right_rounded,
                              size: 15, color: p.accent),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                  ] else ...[
                    const NewTag(),
                    const SizedBox(width: 12),
                  ],
                  if (eta.isNotEmpty) ...[
                    Icon(Icons.schedule_rounded, size: 14, color: p.inkSoft),
                    const SizedBox(width: 3),
                    Text(eta,
                        style: TextStyle(
                            color: p.inkSoft,
                            fontSize: 12,
                            fontWeight: FontWeight.w600)),
                  ],
                ],
              ),
              // Open / closed status stays on the banner badge only.
              if (address.isNotEmpty) ...[
                const SizedBox(height: 10),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.location_on_rounded,
                        size: 14, color: p.inkFaint),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        address,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            color: p.inkFaint,
                            fontSize: 11.5,
                            height: 1.35),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  /// Restaurant identity block at the foot of the menu: name, hours, licence,
  /// and the standard disclaimer — a quiet card, visually separate from items.
  /// (The address is shown in the hero, not here.)
  Widget _footer() {
    final p = V2Theme.of(context);
    final fssai =
        (_restaurant['fssai_license_number'] ?? '').toString().trim();
    final name = (_restaurant['name'] ?? 'This restaurant').toString();
    Widget line(IconData icon, String text, {bool strong = false}) => Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, size: 13, color: p.inkFaint),
              const SizedBox(width: 8),
              Expanded(
                child: Text(text,
                    style: TextStyle(
                        color: p.inkSoft,
                        fontSize: 11.5,
                        height: 1.4,
                        fontWeight:
                            strong ? FontWeight.w700 : FontWeight.w500)),
              ),
            ],
          ),
        );
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 28, 16, 8),
      child: GlassPanel(
        radius: 18,
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Builder(builder: (context) {
              final logo = v2ImageUrl(_restaurant, const [
                'logo_image',
                'logo',
                'logo_url',
              ]);
              return Row(
                children: [
                  if (logo.isNotEmpty) ...[
                    ClipOval(
                      child: AppCachedImage(
                        imageUrl: logo,
                        width: 32,
                        height: 32,
                        fit: BoxFit.cover,
                        errorWidget:
                            v2ImageFallback(context, 32, 32),
                      ),
                    ),
                    const SizedBox(width: 10),
                  ],
                  Expanded(
                    child: Text(name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            color: p.ink,
                            fontSize: 14,
                            fontWeight: FontWeight.w900)),
                  ),
                ],
              );
            }),
            Builder(builder: (context) {
              final hrs = _hoursText();
              return hrs.isEmpty
                  ? const SizedBox.shrink()
                  : line(Icons.access_time_rounded, hrs);
            }),
            if (fssai.isNotEmpty)
              line(Icons.verified_user_rounded, 'FSSAI Lic. No. $fssai',
                  strong: true),
            Divider(color: p.glassBorder, height: 22),
            Text(
              'Prices and taxes are set by the restaurant. An average active '
              'adult needs about 2,000 kcal a day; individual needs vary. '
              'Please tell the restaurant about any allergies before ordering.',
              style: TextStyle(
                  color: p.inkFaint, fontSize: 10.5, height: 1.5),
            ),
          ],
        ),
      ),
    );
  }

  /// Best-effort "open/close" line from whatever timing shape the API returns.
  String _hoursText() {
    final next = (_restaurant['next_opening_label'] ??
            _restaurant['next_open_label'] ??
            '')
        .toString()
        .trim();
    final openT = (_restaurant['opening_time'] ??
            _restaurant['open_time'] ??
            _restaurant['opens_at'] ??
            '')
        .toString()
        .trim();
    final closeT = (_restaurant['closing_time'] ??
            _restaurant['close_time'] ??
            _restaurant['closes_at'] ??
            '')
        .toString()
        .trim();
    if (v2IsOpen(_restaurant)) {
      if (closeT.isNotEmpty) return 'Open now · closes $closeT';
      if (openT.isNotEmpty && closeT.isNotEmpty) return '$openT – $closeT';
      return 'Open now';
    }
    if (next.isNotEmpty) return next;
    if (openT.isNotEmpty) return 'Closed · opens $openT';
    return 'Currently closed';
  }
}

// ---------------------------------------------------------------------------

/// Full-width offer card, auto-advancing PageView + dot indicators when
/// there's more than one offer.
class _RestaurantOfferCarousel extends StatefulWidget {
  const _RestaurantOfferCarousel({required this.offers});

  final List<Map<String, dynamic>> offers;

  @override
  State<_RestaurantOfferCarousel> createState() =>
      _RestaurantOfferCarouselState();
}

class _RestaurantOfferCarouselState extends State<_RestaurantOfferCarousel> {
  final PageController _controller = PageController();
  int _index = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    if (widget.offers.length > 1) {
      _timer = Timer.periodic(const Duration(seconds: 5), (_) {
        if (!mounted || !_controller.hasClients) return;
        _controller.animateToPage(
          (_index + 1) % widget.offers.length,
          duration: const Duration(milliseconds: 420),
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
    final p = V2Theme.of(context);
    if (widget.offers.isEmpty) return const SizedBox.shrink();
    return Column(
      children: [
        SizedBox(
          height: 98,
          child: PageView.builder(
            controller: _controller,
            onPageChanged: (i) => setState(() => _index = i),
            itemCount: widget.offers.length,
            itemBuilder: (_, i) => _card(context, widget.offers[i]),
          ),
        ),
        if (widget.offers.length > 1) ...[
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(widget.offers.length, (i) {
              final active = i == _index;
              return AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: active ? 18 : 6,
                height: 6,
                decoration: BoxDecoration(
                  color: active ? p.accent : p.inkFaint.withOpacity(0.35),
                  borderRadius: BorderRadius.circular(3),
                ),
              );
            }),
          ),
        ],
      ],
    );
  }

  Widget _card(BuildContext context, Map<String, dynamic> o) {
    final p = V2Theme.of(context);
    final title = (o['title'] ??
            o['name'] ??
            o['offer_text'] ??
            o['description'] ??
            'Offer')
        .toString();
    final code = (o['code'] ?? o['coupon_code'] ?? '').toString();
    final sub = (o['subtitle'] ??
            o['description'] ??
            (v2Double(o['min_order_amount'] ?? o['min_order_value']) > 0
                ? 'On orders above '
                    '${formatCurrency(context, v2Double(o['min_order_amount'] ?? o['min_order_value']))}'
                : ''))
        .toString();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            p.accent.withOpacity(0.14),
            p.accent.withOpacity(0.05),
          ],
        ),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: p.accent.withOpacity(0.3)),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: p.accent.withOpacity(0.16),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(Icons.local_offer_rounded, size: 22, color: p.accent),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 13.5,
                        fontWeight: FontWeight.w900)),
                if (code.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text('Code $code',
                      style: TextStyle(
                          color: p.accent,
                          fontSize: 11.5,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.5)),
                ] else if (sub.trim().isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(sub,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: p.inkFaint, fontSize: 11)),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MenuItemCard extends StatelessWidget {
  const _MenuItemCard({
    required this.data,
    required this.onAdd,
    required this.onRemove,
    this.restaurantOpen = true,
  });

  final Map<String, dynamic> data;
  final VoidCallback onAdd;
  final VoidCallback onRemove;
  final bool restaurantOpen;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final original =
        v2Double(data['price'] ?? data['mrp'] ?? data['base_price']);
    final discounted = v2DoubleOrNull(data['discounted_price'] ??
        data['discount_price'] ??
        data['sale_price'] ??
        data['final_price']);
    final price = (discounted != null && discounted > 0) ? discounted : original;
    final hasDiscount =
        discounted != null && discounted > 0 && original > discounted + 0.01;
    final off = hasDiscount && original > 0
        ? (((original - price) / original) * 100).round()
        : 0;
    final rating = v2Double(data['rating'] ?? data['avg_rating']);
    final img = v2ImageUrl(data, kDishImageKeys);
    final desc = (data['description'] ?? '').toString().trim();
    final id = v2Int(data['id'] ?? data['menu_item_id']);
    final itemAvailable =
        v2Bool(data['is_available'] ?? data['available'], fallback: true);
    final orderable = restaurantOpen && itemAvailable;

    // Auto tags from order volume (falls back to the admin bestseller flag).
    final orders = v2Int(data['total_orders'] ?? data['order_count']);
    final isBestseller =
        v2Bool(data['is_bestseller'] ?? data['bestseller']) || orders >= 20;
    final isHighlyOrdered = !isBestseller && orders >= 8;

    return GlassPanel(
      radius: 18,
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // -- details -----------------------------------------------------
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (isBestseller || isHighlyOrdered) ...[
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: (isBestseller ? p.warning : p.accent)
                          .withOpacity(0.14),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                            isBestseller
                                ? Icons.local_fire_department_rounded
                                : Icons.trending_up_rounded,
                            size: 11,
                            color: isBestseller ? p.warning : p.accent),
                        const SizedBox(width: 3),
                        Text(
                          isBestseller ? 'BESTSELLER' : 'HIGHLY ORDERED',
                          style: TextStyle(
                              color: isBestseller ? p.warning : p.accent,
                              fontSize: 8.5,
                              fontWeight: FontWeight.w900,
                              letterSpacing: 0.4),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 6),
                ],
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: VegDot(
                        isVeg: v2Bool(data['is_veg'], fallback: true),
                      ),
                    ),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        (data['name'] ?? 'Item').toString(),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: p.ink,
                          fontSize: 14.5,
                          height: 1.2,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
                if (rating > 0) ...[
                  const SizedBox(height: 5),
                  Row(
                    children: [
                      Icon(Icons.star_rounded, size: 12, color: p.positive),
                      const SizedBox(width: 2),
                      Text(rating.toStringAsFixed(1),
                          style: TextStyle(
                              color: p.inkSoft,
                              fontSize: 11,
                              fontWeight: FontWeight.w800)),
                    ],
                  ),
                ],
                const SizedBox(height: 7),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Text(
                      price > 0 ? formatCurrency(context, price) : '',
                      style: TextStyle(
                        color: hasDiscount ? p.positive : p.inkSoft,
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (hasDiscount) ...[
                      const SizedBox(width: 6),
                      Text(
                        formatCurrency(context, original),
                        style: TextStyle(
                          color: p.inkFaint,
                          fontSize: 11.5,
                          fontWeight: FontWeight.w600,
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                      if (off >= 5) ...[
                        const SizedBox(width: 6),
                        Text('$off% OFF',
                            style: TextStyle(
                                color: p.positive,
                                fontSize: 10.5,
                                fontWeight: FontWeight.w900)),
                      ],
                    ],
                  ],
                ),
                if (desc.isNotEmpty) ...[
                  const SizedBox(height: 7),
                  Text(
                    desc,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: p.inkFaint,
                      fontSize: 11.5,
                      height: 1.35,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 14),
          // -- image + add ----------------------------------------------------
          SizedBox(
            width: 96,
            child: Column(
              children: [
                if (img.isNotEmpty)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: Stack(
                      children: [
                        AppCachedImage(
                          imageUrl: img,
                          width: 96,
                          height: 76,
                          fit: BoxFit.cover,
                          errorWidget: v2ImageFallback(context, 96, 76),
                        ),
                        if (!orderable)
                          Container(
                            width: 96,
                            height: 76,
                            color: Colors.black.withOpacity(0.45),
                            alignment: Alignment.center,
                            child: Text(
                              restaurantOpen ? 'Unavailable' : 'Closed',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 10.5,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                Transform.translate(
                  offset: Offset(0, img.isNotEmpty ? -16 : 4),
                  child: Center(
                    child: !orderable
                        ? Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 7),
                            decoration: BoxDecoration(
                              color: p.glassTop,
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: p.glassBorder),
                            ),
                            child: Text(
                              restaurantOpen ? 'Sold out' : 'Closed',
                              style: TextStyle(
                                color: p.inkFaint,
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          )
                        : Builder(
                            builder: (context) {
                              final qty = id > 0
                                  ? context.select<CartProvider, int>(
                                      (c) => c.quantityFor(id))
                                  : 0;
                              return V2QtyStepper(
                                quantity: qty,
                                dense: true,
                                onAdd: onAdd,
                                onRemove: onRemove,
                              );
                            },
                          ),
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


/// Pinned below the "Menu" heading: the search field on top, then a row of
/// the veg toggle + the menu's category chips. Once it pins under the status
/// bar it grows its own back / save so it doesn't fight the floating buttons.
class _MenuHeaderDelegate extends SliverPersistentHeaderDelegate {
  _MenuHeaderDelegate({
    required this.topInset,
    required this.query,
    required this.controller,
    required this.categories,
    required this.selectedCategory,
    required this.onCategory,
    required this.vegOnly,
    required this.onVegChanged,
    required this.restaurantId,
    required this.onBack,
    required this.onChanged,
    required this.onClear,
  });

  final double topInset;
  final String query;
  final TextEditingController controller;
  final List<(String, int)> categories;
  final String selectedCategory;
  final ValueChanged<String> onCategory;
  final bool vegOnly;
  final ValueChanged<bool> onVegChanged;
  final int restaurantId;
  final VoidCallback onBack;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;

  bool get _hasChips => categories.length > 1;
  bool get _chrome => topInset > 0; // pinned under the status bar

  static const double _fieldH = 44;
  static const double _chipsH = 38;
  static const double _gap = 10;

  double get _content =>
      10 + _fieldH + (_hasChips ? _gap + _chipsH : 0) + 12;

  @override
  double get minExtent => _content + topInset;
  @override
  double get maxExtent => _content + topInset;

  @override
  Widget build(
      BuildContext context, double shrinkOffset, bool overlapsContent) {
    final p = V2Theme.of(context);
    final pinned = overlapsContent || shrinkOffset > 2 || _chrome;

    final chips = _hasChips
        ? SizedBox(
            height: _chipsH,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: EdgeInsets.zero,
              itemCount: categories.length + 1,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (_, i) {
                if (i == 0) {
                  return Align(
                    alignment: Alignment.center,
                    child: _VegSwitch(
                        on: vegOnly,
                        onChanged: () => onVegChanged(!vegOnly)),
                  );
                }
                final c = categories[i - 1];
                return Align(
                  alignment: Alignment.center,
                  child: _CategoryChip(
                    label: c.$1,
                    count: c.$2,
                    selected: c.$1 == selectedCategory,
                    onTap: () => onCategory(c.$1),
                  ),
                );
              },
            ),
          )
        : null;

    final searchField = SizedBox(
      height: _fieldH,
      child: TextField(
        controller: controller,
        onChanged: onChanged,
        textAlignVertical: TextAlignVertical.center,
        keyboardAppearance:
            p.isDark ? Brightness.dark : Brightness.light,
        style: TextStyle(
            color: p.ink, fontSize: 13.5, fontWeight: FontWeight.w600),
        cursorColor: p.accent,
        decoration: InputDecoration(
          isDense: true,
          filled: true,
          fillColor: p.isDark ? const Color(0xFF1B2233) : Colors.white,
          hintText: 'Search dishes on this menu',
          hintStyle: TextStyle(color: p.inkFaint, fontSize: 13),
          prefixIcon: Icon(Icons.search_rounded, size: 18, color: p.inkSoft),
          prefixIconConstraints:
              const BoxConstraints(minWidth: 38, minHeight: 38),
          suffixIcon: query.isEmpty
              ? null
              : IconButton(
                  padding: EdgeInsets.zero,
                  constraints:
                      const BoxConstraints(minWidth: 38, minHeight: 38),
                  icon: Icon(Icons.close_rounded, size: 18, color: p.inkSoft),
                  onPressed: onClear,
                ),
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 10, vertical: 0),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: p.glassBorder),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: p.glassBorder),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: p.accent, width: 1.4),
          ),
        ),
      ),
    );

    final column = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        searchField,
        if (chips != null) ...[
          const SizedBox(height: _gap),
          chips,
        ],
      ],
    );

    return ClipRect(
      child: SizedBox(
        height: _content + topInset,
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: pinned ? p.bgTop : Colors.transparent,
            border: pinned
                ? Border(bottom: BorderSide(color: p.glassBorder))
                : null,
            boxShadow: pinned
                ? [
                    BoxShadow(
                      color: p.shadow,
                      blurRadius: 12,
                      offset: const Offset(0, 4),
                    ),
                  ]
                : null,
          ),
          child: Padding(
            padding: EdgeInsets.fromLTRB(
                _chrome ? 6 : 16, 10 + topInset, _chrome ? 6 : 16, 12),
            child: _chrome
                ? Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _HeaderIcon(
                          icon: Icons.arrow_back_rounded, onTap: onBack),
                      const SizedBox(width: 4),
                      Expanded(child: column),
                      const SizedBox(width: 4),
                      Padding(
                        padding: const EdgeInsets.only(top: 7),
                        child: SaveRestaurantButton(
                            id: restaurantId, dark: false),
                      ),
                    ],
                  )
                : column,
          ),
        ),
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _MenuHeaderDelegate old) =>
      old.query != query ||
      old.topInset != topInset ||
      old.vegOnly != vegOnly ||
      old.selectedCategory != selectedCategory ||
      old.categories.length != categories.length;
}

/// Plain 40&times;40 tappable icon for the pinned menu header.
class _HeaderIcon extends StatelessWidget {
  const _HeaderIcon({required this.icon, required this.onTap});
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: onTap,
      child: SizedBox(
        width: 38,
        height: 44,
        child: Icon(icon, size: 22, color: p.ink),
      ),
    );
  }
}

/// Compact veg-only switch used in the pinned menu bar.
class _VegSwitch extends StatelessWidget {
  const _VegSwitch({required this.on, required this.onChanged});
  final bool on;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: onChanged,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
        decoration: BoxDecoration(
          color: on ? p.positive.withOpacity(0.14) : p.glassTop,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
              color: on ? p.positive : p.glassBorder),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            VegDot(isVeg: true, size: 12),
            const SizedBox(width: 6),
            Text('Veg',
                style: TextStyle(
                    color: on ? p.positive : p.inkSoft,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

/// A single menu-category chip in the pinned bar.
class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.count,
    required this.selected,
    required this.onTap,
  });
  final String label;
  final int count;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? p.accent : p.glassTop,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: selected ? p.accent : p.glassBorder),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(label,
                style: TextStyle(
                    color: selected ? Colors.white : p.inkSoft,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700)),
            if (count > 0) ...[
              const SizedBox(width: 5),
              Text('$count',
                  style: TextStyle(
                      color: selected
                          ? Colors.white.withOpacity(0.75)
                          : p.inkFaint,
                      fontSize: 11,
                      fontWeight: FontWeight.w700)),
            ],
          ],
        ),
      ),
    );
  }
}
