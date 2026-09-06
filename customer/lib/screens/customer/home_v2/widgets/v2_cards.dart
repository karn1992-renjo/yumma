import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../models/menu_item.dart';
import '../../../../models/restaurant.dart';
import '../../../../providers/cart_provider.dart';
import '../../../../utils/currency_utils.dart';
import '../../../../widgets/common/app_cached_image.dart';
import '../data/v2_saved.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import 'v2_anim.dart';
import 'v2_cart_actions.dart';
import 'v2_glass.dart';

Widget v2ImageFallback(BuildContext context, double w, double h) {
  final p = V2Theme.of(context);
  return Container(
    width: w,
    height: h,
    color: p.glassTop,
    alignment: Alignment.center,
    child: Icon(Icons.restaurant_rounded, color: p.inkFaint, size: 22),
  );
}

/// Circular restaurant logo badge, shown on the cover image of a listing card.
class _RestaurantLogo extends StatelessWidget {
  const _RestaurantLogo({required this.url, this.size = 38});

  final String url;
  final double size;

  @override
  Widget build(BuildContext context) {
    if (url.isEmpty) return const SizedBox.shrink();
    return Container(
      width: size,
      height: size,
      padding: const EdgeInsets.all(2),
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.25),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: ClipOval(
        child: AppCachedImage(
          imageUrl: url,
          width: size - 4,
          height: size - 4,
          fit: BoxFit.cover,
          errorWidget: Container(
            color: const Color(0xFFF1F2F5),
            alignment: Alignment.center,
            child: Icon(Icons.storefront_rounded,
                size: size * 0.5, color: const Color(0xFF9AA3B2)),
          ),
        ),
      ),
    );
  }
}

/// Up to five images for a restaurant card carousel: the banner first, then
/// popular dish photos (present on `/home/sections` and search payloads).
List<String> _v2RestaurantCardImages(Map<String, dynamic> data) {
  final out = <String>[];
  final banner = v2ImageUrl(data, kRestaurantImageKeys);
  if (banner.isNotEmpty) out.add(banner);
  for (final key in const ['menu_items', 'matched_menu_items', 'top_items']) {
    final list = data[key];
    if (list is List) {
      for (final it in list) {
        if (it is Map) {
          final u = v2ImageUrl(Map<String, dynamic>.from(it), const [
            'image_url',
            'image',
            'photo',
            'thumbnail',
          ]);
          if (u.isNotEmpty && !out.contains(u)) out.add(u);
        } else if (it is String && it.trim().isNotEmpty && !out.contains(it)) {
          out.add(it.trim());
        }
      }
    }
  }
  return out.take(5).toList();
}

/// Swipeable image strip for a restaurant card. Auto-advances when it has more
/// than one image; falls back to a single static image otherwise.
class _RestaurantImageStrip extends StatefulWidget {
  const _RestaurantImageStrip({required this.images, this.height = 180});

  final List<String> images;
  final double height;

  @override
  State<_RestaurantImageStrip> createState() => _RestaurantImageStripState();
}

class _RestaurantImageStripState extends State<_RestaurantImageStrip> {
  // Manual swipe only — a per-card auto-advance timer on a long list is a
  // real scroll-jank source (N timers + N PageViews animating at once).
  final PageController _controller = PageController();
  int _index = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final imgs = widget.images;
    if (imgs.length <= 1) {
      return AppCachedImage(
        imageUrl: imgs.isEmpty ? '' : imgs.first,
        width: double.infinity,
        height: widget.height,
        fit: BoxFit.cover,
        errorWidget: v2ImageFallback(context, 400, widget.height),
      );
    }
    return SizedBox(
      height: widget.height,
      child: Stack(
        children: [
          PageView.builder(
            controller: _controller,
            onPageChanged: (i) => setState(() => _index = i),
            itemCount: imgs.length,
            itemBuilder: (_, i) => AppCachedImage(
              imageUrl: imgs[i],
              width: double.infinity,
              height: widget.height,
              fit: BoxFit.cover,
              errorWidget: v2ImageFallback(context, 400, widget.height),
            ),
          ),
          Positioned(
            right: 10,
            bottom: 10,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: List.generate(imgs.length, (i) {
                final on = i == _index;
                return AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  margin: const EdgeInsets.only(left: 4),
                  width: on ? 14 : 5,
                  height: 5,
                  decoration: BoxDecoration(
                    color: on ? Colors.white : Colors.white.withOpacity(0.5),
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

class RestaurantCardV2 extends StatelessWidget {
  const RestaurantCardV2({
    super.key,
    required this.data,
    required this.onTap,
    this.width = 220,
  });

  final Map<String, dynamic> data;
  final VoidCallback onTap;
  final double width;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final rating = v2RatingOf(data);
    final eta = v2EtaText(data);
    final cuisine = v2CuisineText(data);
    final open = v2IsOpen(data);
    return SizedBox(
      width: width,
      child: GlassPanel(
        radius: 22,
        onTap: onTap,
        padding: const EdgeInsets.all(10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(15),
              child: Stack(
                children: [
                  AppCachedImage(
                    imageUrl: v2ImageUrl(data, kRestaurantImageKeys),
                    width: width - 20,
                    height: 108,
                    fit: BoxFit.cover,
                    errorWidget: v2ImageFallback(context, width - 20, 108),
                  ),
                  Positioned(
                    left: 8,
                    bottom: 8,
                    child: _RestaurantLogo(
                      url: v2ImageUrl(data, const [
                        'logo_image',
                        'logo',
                        'logo_url',
                      ]),
                      size: 34,
                    ),
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
              style: TextStyle(
                color: p.ink,
                fontSize: 14.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (cuisine.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                cuisine,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: p.inkFaint,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
            const SizedBox(height: 8),
            Row(
              children: [
                if (rating > 0) ...[
                  RatingPill(rating: rating),
                  const SizedBox(width: 8),
                ],
                if (eta.isNotEmpty)
                  Text(
                    eta,
                    style: TextStyle(
                      color: p.inkSoft,
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

/// Compact glass restaurant card for the "Recommended For You" 2-row
/// horizontal grid — same visual language (GlassPanel, V2Tappable press
/// scale, RatingPill) as every other V2 card, just narrower.
class RecommendedGridCardV2 extends StatelessWidget {
  const RecommendedGridCardV2({
    super.key,
    required this.data,
    required this.onTap,
  });

  final Map<String, dynamic> data;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final rating = v2RatingOf(data);
    final open = v2IsOpen(data);
    final nf = data['is_near_and_fast'];
    final isNearAndFast =
        nf == true || nf == 1 || nf?.toString().toLowerCase() == 'true';
    final eta = v2EtaText(data);
    final deliveryLabel = isNearAndFast
        ? 'Near & Fast'
        : (eta.isNotEmpty ? eta : '');

    return GlassPanel(
      radius: 16,
      padding: const EdgeInsets.all(7),
      onTap: onTap,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Stack(
              children: [
                AspectRatio(
                  aspectRatio: 1.3,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      AppCachedImage(
                        imageUrl: v2ImageUrl(data, kRestaurantImageKeys),
                        fit: BoxFit.cover,
                        errorWidget: v2ImageFallback(context, 140, 108),
                      ),
                      if (!open)
                        Container(
                          color: Colors.black.withOpacity(0.5),
                          alignment: Alignment.center,
                          child: const Text(
                            'Closed',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: 12,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                Positioned(
                  left: 6,
                  bottom: 6,
                  child: _RestaurantLogo(
                    url: v2ImageUrl(data, const [
                      'logo_image',
                      'logo',
                      'logo_url',
                    ]),
                    size: 26,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 7),
          Text(
            (data['name'] ?? 'Restaurant').toString(),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: p.ink,
              fontSize: 13.5,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              if (rating > 0) ...[
                RatingPill(rating: rating),
                const SizedBox(width: 6),
              ] else if (isNearAndFast)
                Icon(Icons.bolt_rounded, color: p.positive, size: 15),
              if (deliveryLabel.isNotEmpty)
                Expanded(
                  child: Text(
                    deliveryLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: isNearAndFast ? p.positive : p.inkFaint,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class RestaurantRowV2 extends StatelessWidget {
  const RestaurantRowV2({super.key, required this.data, required this.onTap});

  final Map<String, dynamic> data;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final rating = v2RatingOf(data);
    final eta = v2EtaText(data);
    final cuisine = v2CuisineText(data);
    final open = v2IsOpen(data);
    return GlassPanel(
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
                  imageUrl: v2ImageUrl(data, kRestaurantImageKeys),
                  width: 82,
                  height: 82,
                  fit: BoxFit.cover,
                  errorWidget: v2ImageFallback(context, 82, 82),
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
                  style: TextStyle(
                    color: p.ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (cuisine.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    cuisine,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: p.inkFaint,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Row(
                  children: [
                    if (rating > 0) ...[
                      RatingPill(rating: rating),
                      const SizedBox(width: 10),
                    ],
                    if (eta.isNotEmpty)
                      Text(
                        eta,
                        style: TextStyle(
                          color: p.inkSoft,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: p.inkFaint),
        ],
      ),
    );
  }
}

/// Compact +/- quantity control used on dish cards and menu rows.
class V2QtyStepper extends StatelessWidget {
  const V2QtyStepper({
    super.key,
    required this.quantity,
    required this.onAdd,
    required this.onRemove,
    this.dense = false,
    this.light = false,
  });

  final int quantity;
  final VoidCallback onAdd;
  final VoidCallback onRemove;
  final bool dense;

  /// White background + accent foreground — for use on top of imagery.
  final bool light;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final pad = dense || light ? 5.0 : 7.0;
    final bg = light ? Colors.white : p.accent;
    final fg = light ? p.accent : Colors.white;
    if (quantity <= 0) {
      return V2Tappable(
        onTap: onAdd,
        child: Container(
          padding: EdgeInsets.symmetric(
              horizontal: dense || light ? 16 : 20, vertical: pad + 1),
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(10),
            boxShadow: light
                ? null
                : [
                    BoxShadow(
                      color: p.accent.withOpacity(0.35),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
          ),
          child: Text(
            'ADD',
            style: TextStyle(
              color: fg,
              fontWeight: FontWeight.w900,
              fontSize: 12,
              letterSpacing: 0.5,
            ),
          ),
        ),
      );
    }
    return Container(
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          V2Tappable(
            onTap: onRemove,
            child: Padding(
              padding: EdgeInsets.all(pad),
              child: Icon(Icons.remove_rounded, size: 15, color: fg),
            ),
          ),
          Text(
            '$quantity',
            style: TextStyle(
              color: fg,
              fontWeight: FontWeight.w900,
              fontSize: 13,
            ),
          ),
          V2Tappable(
            onTap: onAdd,
            child: Padding(
              padding: EdgeInsets.all(pad),
              child: Icon(Icons.add_rounded, size: 15, color: fg),
            ),
          ),
        ],
      ),
    );
  }
}

/// Popular / recommended dish card. Adds to cart in place when the raw item has
/// enough data to build a [MenuItem]; otherwise the ADD button opens the
/// restaurant.
class DishCardV2 extends StatelessWidget {
  const DishCardV2({
    super.key,
    required this.raw,
    required this.onTap,
    this.width = 168,
  });

  final Map<String, dynamic> raw;
  final VoidCallback onTap;
  final double width;

  double get _price => v2Double(raw['discounted_price'] ??
      raw['final_price'] ??
      raw['price'] ??
      raw['min_price'] ??
      raw['base_price']);
  double get _original =>
      v2Double(raw['price'] ?? raw['mrp'] ?? raw['original_price']);
  int get _menuItemId =>
      v2Int(raw['id'] ?? raw['menu_item_id'] ?? raw['master_menu_item_id']);
  int get _restaurantId => v2Int(raw['restaurant_id'] ??
      raw['restaurantId'] ??
      (raw['restaurant'] is Map ? (raw['restaurant'] as Map)['id'] : null));

  bool get _canAddInline => _menuItemId > 0 && _restaurantId > 0;

  void _add(BuildContext context) {
    if (!_canAddInline) {
      onTap();
      return;
    }
    try {
      final item = MenuItem.fromJson(raw);
      final r = raw['restaurant'] is Map
          ? Map<String, dynamic>.from(raw['restaurant'] as Map)
          : <String, dynamic>{};
      final restaurant = Restaurant.fromJson({
        'id': _restaurantId,
        'name': (raw['restaurant_name'] ?? raw['restaurantName'] ?? r['name'] ?? 'Restaurant'),
        'delivery_fee': r['delivery_fee'] ?? 0,
        ...r,
      });
      v2AddToCart(context, item, restaurant);
    } catch (_) {
      onTap();
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final name = (raw['name'] ?? raw['title'] ?? 'Dish').toString();
    final nestedR = raw['restaurant'];
    final restaurantName = (raw['restaurant_name'] ??
            raw['restaurantName'] ??
            (nestedR is Map ? nestedR['name'] : null) ??
            '')
        .toString()
        .trim();
    final dishRating = v2Double(raw['rating'] ??
        raw['avg_rating'] ??
        (nestedR is Map ? nestedR['rating'] : null));
    final isVeg = v2Bool(raw['is_veg'] ?? raw['veg'], fallback: true);
    final imageUrl = v2ImageUrl(raw, kDishImageKeys);
    final hasDiscount = _original > 0 && _original > _price + 0.01;
    final qty = _canAddInline
        ? context.select<CartProvider, int>((c) => c.quantityFor(_menuItemId))
        : 0;

    return SizedBox(
      width: width,
      child: GlassPanel(
        radius: 20,
        onTap: onTap,
        padding: const EdgeInsets.all(10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: Stack(
                children: [
                  AppCachedImage(
                    imageUrl: imageUrl,
                    width: width - 20,
                    height: 104,
                    fit: BoxFit.cover,
                    errorWidget: v2ImageFallback(context, width - 20, 104),
                  ),
                  Positioned(
                    right: 8,
                    bottom: 8,
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(11),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.22),
                            blurRadius: 8,
                            offset: const Offset(0, 3),
                          ),
                        ],
                      ),
                      child: V2QtyStepper(
                        quantity: qty,
                        light: true,
                        onAdd: () => _add(context),
                        onRemove: () => _canAddInline
                            ? context
                                .read<CartProvider>()
                                .decrementQuantity(_menuItemId)
                            : null,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                VegDot(isVeg: isVeg),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: p.ink,
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            if (restaurantName.isNotEmpty) ...[
              const SizedBox(height: 5),
              Row(
                children: [
                  Icon(Icons.storefront_rounded,
                      size: 12, color: p.inkFaint),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      restaurantName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: p.inkSoft,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  if (dishRating > 0) ...[
                    const SizedBox(width: 4),
                    Icon(Icons.star_rounded, size: 11, color: p.positive),
                    const SizedBox(width: 1),
                    Text(
                      dishRating.toStringAsFixed(1),
                      style: TextStyle(
                        color: p.inkSoft,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ],
              ),
            ],
            const SizedBox(height: 8),
            Row(
              children: [
                Text(
                  _price > 0 ? formatCurrency(context, _price) : 'View menu',
                  style: TextStyle(
                    color: hasDiscount ? p.positive : p.accent,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (hasDiscount) ...[
                  const SizedBox(width: 6),
                  Text(
                    formatCurrency(context, _original),
                    style: TextStyle(
                      color: p.inkFaint,
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      decoration: TextDecoration.lineThrough,
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// V1-scale restaurant card: full-width cover image + details, for the
/// "Restaurants Near You" style vertical lists.
class RestaurantBigCardV2 extends StatelessWidget {
  const RestaurantBigCardV2({
    super.key,
    required this.data,
    required this.onTap,
  });

  final Map<String, dynamic> data;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final rating = v2RatingOf(data);
    final eta = v2EtaText(data);
    final cuisine = v2CuisineText(data);
    final open = v2IsOpen(data);
    final offer = (data['discount'] ??
            data['offer'] ??
            data['offer_text'] ??
            '')
        .toString()
        .trim();

    return GlassPanel(
      radius: 24,
      onTap: () {
        v2TrackAdClick(data);
        onTap();
      },
      padding: const EdgeInsets.all(8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(18),
            child: Stack(
              children: [
                _RestaurantImageStrip(
                  images: _v2RestaurantCardImages(data),
                  height: 184,
                ),
                Positioned.fill(
                  child: IgnorePointer(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Colors.transparent,
                            Colors.black.withOpacity(0.05),
                            Colors.black.withOpacity(0.38),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                Positioned(
                  left: 10,
                  top: 10,
                  child: Row(
                    children: [
                      if (v2Bool(data['is_pure_veg'] ?? data['pure_veg']))
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 7, vertical: 3),
                          decoration: BoxDecoration(
                            color: const Color(0xFF0F9D58),
                            borderRadius: BorderRadius.circular(7),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.eco_rounded,
                                  size: 11, color: Colors.white),
                              SizedBox(width: 3),
                              Text('PURE VEG',
                                  style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 9,
                                      fontWeight: FontWeight.w900,
                                      letterSpacing: 0.4)),
                            ],
                          ),
                        ),
                      if (v2Bool(data['is_pure_veg'] ?? data['pure_veg']) &&
                          offer.isNotEmpty)
                        const SizedBox(width: 6),
                      if (offer.isNotEmpty)
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: p.accent,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(offer,
                              style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800)),
                        ),
                    ],
                  ),
                ),
                Positioned(
                  right: 8,
                  top: 8,
                  child: Row(
                    children: [
                      if (v2IsSponsored(data))
                        Container(
                          margin: const EdgeInsets.only(right: 6),
                          padding: const EdgeInsets.symmetric(
                              horizontal: 7, vertical: 3),
                          decoration: BoxDecoration(
                            color: Colors.black.withOpacity(0.62),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Text('Ad',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 9.5,
                                  fontWeight: FontWeight.w800)),
                        ),
                      SaveRestaurantButton(id: v2RestaurantId(data)),
                    ],
                  ),
                ),
                Positioned(
                  left: 10,
                  bottom: 10,
                  child: _RestaurantLogo(
                    url: v2ImageUrl(data, const [
                      'logo_image',
                      'logo',
                      'logo_url',
                    ]),
                    size: 40,
                  ),
                ),
                if (!open)
                  Positioned.fill(
                    child: IgnorePointer(
                      child: Container(
                        color: Colors.black.withOpacity(0.45),
                        alignment: Alignment.center,
                        child: const Text(
                          'Closed',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                            fontSize: 15,
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 0, 4, 4),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        (data['name'] ?? 'Restaurant').toString(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: p.ink,
                          fontSize: 16.5,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -0.3,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    if (rating > 0)
                      RatingPill(rating: rating)
                    else
                      const NewTag(),
                  ],
                ),
                if (cuisine.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    cuisine,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: p.inkFaint,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Row(
                  children: [
                    if (eta.isNotEmpty) ...[
                      Icon(Icons.schedule_rounded,
                          size: 13, color: p.inkSoft),
                      const SizedBox(width: 3),
                      Text(
                        eta,
                        style: TextStyle(
                          color: p.inkSoft,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                    if (v2FreeDeliveryText(context, data) != null) ...[
                      const SizedBox(width: 12),
                      Icon(Icons.pedal_bike_rounded,
                          size: 13, color: p.positive),
                      const SizedBox(width: 3),
                      Text(
                        v2FreeDeliveryText(context, data)!,
                        style: TextStyle(
                          color: p.positive,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// "New" pill for a restaurant with no ratings yet.
class NewTag extends StatelessWidget {
  const NewTag({super.key});

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: p.accent.withOpacity(p.isDark ? 0.22 : 0.12),
        borderRadius: BorderRadius.circular(7),
        border: Border.all(color: p.accent.withOpacity(0.5)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.auto_awesome_rounded, size: 11, color: p.accent),
          const SizedBox(width: 3),
          Text('New',
              style: TextStyle(
                  color: p.accent,
                  fontSize: 11,
                  fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }
}

/// Bookmark toggle wired to the app-wide [V2Saved] set.
class SaveRestaurantButton extends StatelessWidget {
  const SaveRestaurantButton({super.key, required this.id, this.dark = true});

  final int id;

  /// true = translucent-dark circle for use over a photo; false = glass chip.
  final bool dark;

  @override
  Widget build(BuildContext context) {
    if (id <= 0) return const SizedBox.shrink();
    V2Saved.instance.ensureLoaded();
    final p = V2Theme.of(context);
    return ValueListenableBuilder<Set<int>>(
      valueListenable: V2Saved.instance.ids,
      builder: (context, ids, _) {
        final saved = ids.contains(id);
        return V2Tappable(
          onTap: () => V2Saved.instance.toggle(id),
          child: Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: dark
                  ? Colors.black.withOpacity(0.42)
                  : p.glassStrongTop,
              shape: BoxShape.circle,
              border: dark ? null : Border.all(color: p.glassBorder),
            ),
            child: Icon(
              saved ? Icons.bookmark_rounded : Icons.bookmark_outline_rounded,
              size: 17,
              color: saved
                  ? (dark ? Colors.white : p.accent)
                  : (dark ? Colors.white : p.inkSoft),
            ),
          ),
        );
      },
    );
  }
}

class CuisineChipV2 extends StatelessWidget {
  const CuisineChipV2({
    super.key,
    required this.name,
    required this.imageUrl,
    required this.onTap,
  });

  final String name;
  final String imageUrl;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return SizedBox(
      width: 76,
      child: Column(
        children: [
          GlassPanel(
            radius: 22,
            onTap: onTap,
            padding: const EdgeInsets.all(4),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: AppCachedImage(
                imageUrl: imageUrl,
                width: 60,
                height: 60,
                fit: BoxFit.cover,
                errorWidget: v2ImageFallback(context, 60, 60),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: p.inkSoft,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class BrandBadgeV2 extends StatelessWidget {
  const BrandBadgeV2({
    super.key,
    required this.name,
    required this.imageUrl,
    required this.onTap,
  });

  final String name;
  final String imageUrl;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return SizedBox(
      width: 92,
      child: Column(
        children: [
          GlassPanel(
            radius: 20,
            onTap: onTap,
            padding: const EdgeInsets.all(6),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: AppCachedImage(
                imageUrl: imageUrl,
                width: 76,
                height: 76,
                fit: BoxFit.cover,
                errorWidget: v2ImageFallback(context, 76, 76),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: p.inkSoft,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class OfferCardV2 extends StatelessWidget {
  const OfferCardV2({super.key, required this.data, required this.onTap});

  final Map<String, dynamic> data;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
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
      child: GlassPanel(
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
                color: p.warning.withOpacity(p.isDark ? 0.22 : 0.16),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: p.warning.withOpacity(0.5)),
              ),
              child: Text(
                'OFFER',
                style: TextStyle(
                  color: p.ink,
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
              style: TextStyle(
                color: p.ink,
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
                style: TextStyle(
                  color: p.inkFaint,
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

class BannerCarouselV2 extends StatefulWidget {
  const BannerCarouselV2({
    super.key,
    required this.banners,
    required this.onTap,
  });

  final List<Map<String, dynamic>> banners;
  final ValueChanged<Map<String, dynamic>> onTap;

  @override
  State<BannerCarouselV2> createState() => _BannerCarouselV2State();
}

// Text/layout helpers copied verbatim from the V1 production `_HomePromoBanner`
// so V2 renders the same banner. Keep in sync with home_screen_production.dart.
const double _kBannerRadius = 24;
const Color _kBannerGreen = Color(0xFF16A34A);
const List<String> _kBannerImageKeys = <String>[
  'animation_url',
  'lottie_url',
  'json_url',
  'media_url',
  'asset_url',
  'webp_url',
  'hero_image',
  'image',
  'banner_image',
  'image_url',
  'photo',
];
const List<String> _kBannerBadgeKeys = <String>[
  'badge_image_url',
  'badge_image',
  'badge',
  'logo_image',
  'logo_url',
  'logo',
];

String _bannerText(Map<String, dynamic> b, List<String> keys) {
  for (final k in keys) {
    final v = b[k]?.toString().trim();
    if (v != null && v.isNotEmpty) return v;
  }
  return '';
}

bool _bannerIsImageOnly(Map<String, dynamic> b) {
  final m = b['layout_mode']?.toString().trim().toLowerCase();
  return m == 'full_image' || m == 'image_only';
}

bool _bannerIsPromoCard(Map<String, dynamic> b) {
  final m = (b['layout_mode'] ?? b['layoutMode'] ?? '')
      .toString()
      .trim()
      .toLowerCase();
  final t = (b['banner_type'] ?? b['bannerType'] ?? '')
      .toString()
      .trim()
      .toLowerCase();
  return m == 'promo_card' || t == 'promo';
}

class _BannerCarouselV2State extends State<BannerCarouselV2> {
  // Full-width pages, no peek — matches the production hero banner.
  final PageController _controller = PageController();
  int _index = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    if (widget.banners.length > 1) {
      _timer = Timer.periodic(const Duration(seconds: 5), (_) {
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

  @override
  Widget build(BuildContext context) {
    if (widget.banners.isEmpty) return const SizedBox.shrink();
    // Same sizing rule as the V1 production banner.
    final available =
        (MediaQuery.sizeOf(context).width - 40).clamp(0.0, double.infinity);
    final allPromo = widget.banners.every(_bannerIsPromoCard);
    final height = allPromo
        ? (available / 3.0).clamp(118.0, 150.0).toDouble()
        : (available / 3.0).clamp(128.0, 190.0).toDouble();
    return Column(
      children: [
        SizedBox(
          height: height,
          child: PageView.builder(
            controller: _controller,
            onPageChanged: (i) => setState(() => _index = i),
            itemCount: widget.banners.length,
            itemBuilder: (_, i) {
              final b = widget.banners[i];
              return V2Tappable(
                onTap: () => widget.onTap(b),
                child: _bannerIsPromoCard(b)
                    ? _promoCard(context, b, height)
                    : _fullBanner(context, b, height),
              );
            },
          ),
        ),
        if (widget.banners.length > 1) ...[
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(widget.banners.length, (i) {
              final active = i == _index;
              return Container(
                width: active ? 10 : 8,
                height: active ? 10 : 8,
                margin: const EdgeInsets.symmetric(horizontal: 4),
                decoration: BoxDecoration(
                  color: active ? _kBannerGreen : const Color(0xFFD7DCE4),
                  shape: BoxShape.circle,
                ),
              );
            }),
          ),
        ],
      ],
    );
  }

  Widget _fullBanner(BuildContext context, Map<String, dynamic> b, double h) {
    final eyebrow =
        _bannerText(b, const ['eyebrow', 'badge', 'tag', 'label']).toUpperCase();
    final headline = _bannerText(b, const ['headline', 'title', 'name']);
    final subtitle =
        _bannerText(b, const ['subtitle', 'description', 'caption']);
    final cta = _bannerText(
        b, const ['cta', 'cta_text', 'button_text', 'action_text']);
    final hasText = !_bannerIsImageOnly(b) &&
        (eyebrow.isNotEmpty ||
            headline.isNotEmpty ||
            subtitle.isNotEmpty ||
            cta.isNotEmpty);

    return ClipRRect(
      borderRadius: BorderRadius.circular(_kBannerRadius),
      child: Container(
        width: double.infinity,
        height: h,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(_kBannerRadius),
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.10),
              blurRadius: 24,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Stack(
          fit: StackFit.expand,
          children: [
            AppCachedImage(
              imageUrl: v2ImageUrl(b, _kBannerImageKeys),
              fit: BoxFit.cover,
              errorWidget: v2ImageFallback(context, 400, h),
            ),
            if (hasText)
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    if (eyebrow.isNotEmpty) ...[
                      Text(eyebrow,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w900)),
                      const SizedBox(height: 6),
                    ],
                    if (headline.isNotEmpty)
                      Text(headline,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                              color: Colors.white,
                              fontSize: 27,
                              height: 1.0,
                              fontWeight: FontWeight.w900)),
                    if (subtitle.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(subtitle,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                              color: Colors.white,
                              fontSize: 14,
                              height: 1.25,
                              fontWeight: FontWeight.w600)),
                    ],
                    if (cta.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 9),
                        decoration: BoxDecoration(
                          color: Colors.black,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(cta,
                                style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 13,
                                    fontWeight: FontWeight.w900)),
                            const SizedBox(width: 6),
                            const Icon(Icons.arrow_forward_ios_rounded,
                                color: Colors.white, size: 12),
                          ],
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

  Widget _promoCard(BuildContext context, Map<String, dynamic> b, double h) {
    final title = _bannerText(b, const ['headline', 'title', 'name']);
    final subtitle =
        _bannerText(b, const ['subtitle', 'description', 'caption']);
    final cta = _bannerText(b, const [
      'cta_label',
      'ctaLabel',
      'cta',
      'cta_text',
      'button_text',
      'action_text',
    ]);
    final badgeUrl = v2ImageUrl(b, _kBannerBadgeKeys);
    final compact = h < 150;

    return ClipRRect(
      borderRadius: BorderRadius.circular(_kBannerRadius),
      child: Container(
        width: double.infinity,
        height: h,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(_kBannerRadius),
          border: Border.all(color: const Color(0xFFE8E8EE)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.08),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: [
            Row(
              children: [
                Expanded(
                  flex: 52,
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(
                      compact ? 14 : 18,
                      compact ? 10 : 14,
                      compact ? 10 : 14,
                      compact ? 10 : 14,
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (title.isNotEmpty)
                          Text(title,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                  color: const Color(0xFF111827),
                                  fontSize: compact ? 20 : 24,
                                  height: 1.02,
                                  fontWeight: FontWeight.w900)),
                        if (subtitle.isNotEmpty) ...[
                          SizedBox(height: compact ? 5 : 7),
                          Text(subtitle,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                  color: const Color(0xFF6B7280),
                                  fontSize: compact ? 12 : 14,
                                  height: 1.22,
                                  fontWeight: FontWeight.w600)),
                        ],
                        if (cta.isNotEmpty) ...[
                          SizedBox(height: compact ? 8 : 10),
                          Container(
                            padding: EdgeInsets.symmetric(
                              horizontal: compact ? 14 : 18,
                              vertical: compact ? 7 : 9,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFF111827),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(cta.toUpperCase(),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w900)),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                Expanded(
                  flex: 48,
                  child: ClipPath(
                    clipper: _PromoTornEdgeClipperV2(),
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        const DecoratedBox(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                              colors: [Color(0xFFB91C1C), Color(0xFF7F1D1D)],
                            ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.only(left: 16),
                          child: AppCachedImage(
                            imageUrl: v2ImageUrl(b, _kBannerImageKeys),
                            fit: BoxFit.cover,
                            errorWidget: const SizedBox.shrink(),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            if (badgeUrl.isNotEmpty)
              Positioned(
                right: 12,
                top: 12,
                child: Container(
                  width: compact ? 34 : 40,
                  height: compact ? 34 : 40,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white,
                    border: Border.all(
                        color: const Color(0xFFB91C1C), width: 1.5),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.18),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: AppCachedImage(
                    imageUrl: badgeUrl,
                    fit: BoxFit.cover,
                    errorWidget: const SizedBox.shrink(),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _PromoTornEdgeClipperV2 extends CustomClipper<Path> {
  static const List<Offset> _points = [
    Offset(1.0, 1.0),
    Offset(0.3, 0.86),
    Offset(0.78, 0.72),
    Offset(0.18, 0.58),
    Offset(0.82, 0.44),
    Offset(0.28, 0.3),
    Offset(0.85, 0.16),
    Offset(0.0, 0.0),
  ];

  @override
  Path getClip(Size size) {
    final notch = size.width * 0.22;
    final path = Path()
      ..moveTo(size.width, 0)
      ..lineTo(size.width, size.height);
    for (final pt in _points) {
      path.lineTo(pt.dx * notch, pt.dy * size.height);
    }
    path.close();
    return path;
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}

class RunningOrderCardV2 extends StatelessWidget {
  const RunningOrderCardV2({
    super.key,
    required this.orderNumber,
    required this.statusText,
    required this.onTap,
  });

  final String orderNumber;
  final String statusText;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return GlassPanel(
      radius: 20,
      onTap: onTap,
      strong: true,
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: p.accent.withOpacity(0.22),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: p.accent.withOpacity(0.5)),
            ),
            child: Icon(Icons.delivery_dining_rounded, size: 22, color: p.ink),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Order #$orderNumber',
                  style: TextStyle(
                    color: p.ink,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  statusText,
                  style: TextStyle(
                    color: p.inkSoft,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right_rounded, color: p.inkFaint),
        ],
      ),
    );
  }
}
