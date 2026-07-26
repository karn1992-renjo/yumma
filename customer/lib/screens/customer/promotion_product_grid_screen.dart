import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/menu_item.dart';
import '../../models/restaurant.dart';
import '../../providers/cart_provider.dart';
import '../../services/app_image_cache.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/customer/floating_cart_bar.dart';

const _promoGridText = FoodFlowTheme.ink;
const _promoGridSubtext = FoodFlowTheme.muted;
const _promoGridLine = FoodFlowTheme.line;
const _promoGridSuccess = FoodFlowTheme.success;
const _promoGridBg = FoodFlowTheme.warmCanvas;

Color _promoGridPrimary(BuildContext context) =>
    FoodFlowTheme.brandPrimary(context);

Color _promoGridSecondary(BuildContext context) =>
    FoodFlowTheme.brandSecondary(context);

class PromotionProductGridScreen extends StatelessWidget {
  const PromotionProductGridScreen({
    super.key,
    required this.title,
    this.subtitle,
    required this.offers,
    this.vegOnly = false,
  });

  final String title;
  final String? subtitle;
  final List<Map<String, dynamic>> offers;
  final bool vegOnly;

  factory PromotionProductGridScreen.fromArguments(dynamic arguments) {
    final args = arguments is Map
        ? Map<String, dynamic>.from(arguments)
        : const <String, dynamic>{};
    final rawOffers = args['offers'];
    final offers = rawOffers is List
        ? rawOffers
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList(growable: false)
        : const <Map<String, dynamic>>[];

    return PromotionProductGridScreen(
      title: args['title']?.toString().trim().isNotEmpty == true
          ? args['title'].toString().trim()
          : 'Promotion Items',
      subtitle: args['subtitle']?.toString(),
      offers: offers,
      vegOnly: args['veg_only'] == true ||
          args['vegOnly'] == true ||
          args['veg_only']?.toString() == 'true',
    );
  }

  @override
  Widget build(BuildContext context) {
    final entries = _promotionEntries();
    final topInset = MediaQuery.paddingOf(context).top;

    return Scaffold(
      backgroundColor: _promoGridBg,
      bottomNavigationBar: const CustomerFloatingCartBar(),
      body: entries.isEmpty
          ? _PromotionEmptyState(
              title: title,
              subtitle: subtitle,
              topInset: topInset,
            )
          : LayoutBuilder(
              builder: (context, constraints) {
                final width = constraints.maxWidth;
                final columns = width >= 900 ? 3 : 2;
                final horizontalPadding = width >= 900 ? 24.0 : 14.0;
                final spacing = width >= 900 ? 18.0 : 12.0;
                final ratio = width >= 900 ? 0.80 : 0.66;

                return CustomScrollView(
                  physics: const BouncingScrollPhysics(
                    parent: AlwaysScrollableScrollPhysics(),
                  ),
                  slivers: [
                    SliverToBoxAdapter(
                      child: _PromotionGridHero(
                        title: title,
                        subtitle: subtitle,
                        count: entries.length,
                        topInset: topInset,
                      ),
                    ),
                    SliverPadding(
                      padding: EdgeInsets.fromLTRB(
                        horizontalPadding,
                        14,
                        horizontalPadding,
                        104 + MediaQuery.paddingOf(context).bottom,
                      ),
                      sliver: SliverGrid(
                        delegate: SliverChildBuilderDelegate(
                          (context, index) {
                            final entry = entries[index];
                            return _PromotionMenuCard(entry: entry);
                          },
                          childCount: entries.length,
                        ),
                        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: columns,
                          childAspectRatio: ratio,
                          crossAxisSpacing: spacing,
                          mainAxisSpacing: spacing,
                        ),
                      ),
                    ),
                  ],
                );
              },
            ),
    );
  }

  List<_PromotionEntry> _promotionEntries() {
    final seenSingleItems = <int>{};
    final entries = <_PromotionEntry>[];

    for (final offer in offers) {
      final rawItems = offer['menu_items'];
      if (rawItems is! List) continue;

      final rawMenuItems = rawItems.whereType<Map>().toList(growable: false);
      final rawRewardMenuItems = offer['reward_menu_items'] is List
          ? (offer['reward_menu_items'] as List).whereType<Map>().toList()
          : const <Map<dynamic, dynamic>>[];
      if (vegOnly &&
          rawRewardMenuItems.any((item) => !_rawPromotionItemIsVeg(item))) {
        continue;
      }
      final scopedRawItems = vegOnly
          ? rawMenuItems.where(_rawPromotionItemIsVeg).toList(growable: false)
          : rawMenuItems;
      if (vegOnly && scopedRawItems.length != rawMenuItems.length) {
        continue;
      }

      final eligibleRawItems = scopedRawItems
          .where((item) => item['is_reward_item'] != true)
          .toList(growable: false);
      final rewardRawItems = <Map<dynamic, dynamic>>[
        ...scopedRawItems.where((item) => item['is_reward_item'] == true),
        ...rawRewardMenuItems,
      ];
      final displayRawItems =
          eligibleRawItems.isNotEmpty ? eligibleRawItems : scopedRawItems;

      final items = displayRawItems
          .map((rawItem) =>
              MenuItem.fromJson(Map<String, dynamic>.from(rawItem)))
          .where((item) => item.id > 0)
          .toList(growable: false);
      if (items.isEmpty) continue;

      final type = _offerType(offer);
      if (_isBundlePromotionType(type)) {
        final reward = offer['rewards'] is Map
            ? Map<String, dynamic>.from(offer['rewards'] as Map)
            : offer['reward_config'] is Map
                ? Map<String, dynamic>.from(offer['reward_config'] as Map)
                : const <String, dynamic>{};
        final rawGroups = reward['combo_groups'];
        if (rawGroups is List && rawGroups.isNotEmpty) {
          for (var index = 0; index < rawGroups.length; index++) {
            final rawGroup = rawGroups[index];
            if (rawGroup is! Map) continue;
            final group = Map<String, dynamic>.from(rawGroup);
            final groupItemIds = (group['item_ids'] is List
                    ? group['item_ids'] as List
                    : const [])
                .map((id) => int.tryParse(id.toString()) ?? 0)
                .where((id) => id > 0)
                .toSet();
            if (groupItemIds.isEmpty) continue;

            final groupRawItems = displayRawItems.where((rawItem) {
              final itemId = int.tryParse(
                    (rawItem['menu_item_id'] ?? rawItem['id'] ?? '').toString(),
                  ) ??
                  0;
              return groupItemIds.contains(itemId);
            }).toList(growable: false);
            final groupItems = groupRawItems
                .map((rawItem) =>
                    MenuItem.fromJson(Map<String, dynamic>.from(rawItem)))
                .where((item) => item.id > 0)
                .toList(growable: false);
            if (groupItems.isEmpty) continue;

            final groupOffer = {
              ...offer,
              'display_id':
                  '${offer['display_id'] ?? 'promotion:${offer['id'] ?? index}'}:combo:$index',
              'group_key':
                  '${offer['id'] ?? offer['display_id'] ?? 'promotion'}:$index',
              if ((group['name'] ?? '').toString().trim().isNotEmpty)
                'title': group['name'],
              'menu_items': groupRawItems,
              'effective_price':
                  group['effective_price'] ?? group['price'] ?? group['value'],
              'combo_price':
                  group['effective_price'] ?? group['price'] ?? group['value'],
              'actual_price': group['actual_price'] ?? group['original_price'],
              'discount_percent': group['discount_percent'],
            };
            entries.add(
              _PromotionEntry(
                offer: groupOffer,
                items: groupItems,
                paidItems: groupItems,
              ),
            );
          }
          continue;
        }

        entries
            .add(_PromotionEntry(offer: offer, items: items, paidItems: items));
        continue;
      }

      final rewardItems = rewardRawItems
          .map((rawItem) =>
              MenuItem.fromJson(Map<String, dynamic>.from(rawItem)))
          .where((item) => item.id > 0)
          .toList(growable: false);
      for (final item in items) {
        final key = Object.hash(offer['display_id'] ?? offer['id'], item.id);
        if (!seenSingleItems.add(key)) continue;
        entries.add(
          _PromotionEntry(
            offer: offer,
            items: [item, ...rewardItems],
            paidItems: [item],
          ),
        );
      }
    }

    return entries;
  }
}

class _PromotionGridHero extends StatelessWidget {
  const _PromotionGridHero({
    required this.title,
    required this.subtitle,
    required this.count,
    required this.topInset,
  });

  final String title;
  final String? subtitle;
  final int count;
  final double topInset;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 214 + topInset,
      child: Padding(
        padding: EdgeInsets.fromLTRB(18, topInset + 14, 18, 18),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Positioned.fill(
              child: CustomPaint(
                painter: _PromotionGridHeroPainter(_promoGridPrimary(context)),
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
              child: _FloatingPromotionIcon(
                icon: Icons.local_offer_rounded,
                color: _promoGridPrimary(context),
                assetPath: 'assets/images/deal.png',
                size: 112,
                iconSize: 54,
              ),
            ),
            Positioned(
              left: 0,
              right: 124,
              bottom: 4,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _promoGridText,
                      fontSize: 34,
                      height: 1.02,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 9),
                  Text(
                    subtitle?.trim().isNotEmpty == true
                        ? subtitle!.trim()
                        : '$count promotion item${count == 1 ? '' : 's'} available',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _promoGridSubtext,
                      fontSize: 14.5,
                      height: 1.28,
                      fontWeight: FontWeight.w800,
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

class _PromotionEmptyState extends StatelessWidget {
  const _PromotionEmptyState({
    required this.title,
    required this.subtitle,
    required this.topInset,
  });

  final String title;
  final String? subtitle;
  final double topInset;

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        SliverToBoxAdapter(
          child: _PromotionGridHero(
            title: title,
            subtitle: subtitle,
            count: 0,
            topInset: topInset,
          ),
        ),
        SliverFillRemaining(
          hasScrollBody: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
            child: Center(
              child: Container(
                padding: const EdgeInsets.fromLTRB(20, 24, 20, 22),
                decoration: _promotionPanelDecoration(context),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _FloatingPromotionIcon(
                      icon: Icons.restaurant_menu_rounded,
                      color: _promoGridPrimary(context),
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'No menu items found',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: _promoGridText,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'This promotion has no mapped menu items yet.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: _promoGridSubtext,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
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
          child: Icon(icon, color: _promoGridText, size: 22),
        ),
      ),
    );
  }
}

class _FloatingPromotionIcon extends StatelessWidget {
  const _FloatingPromotionIcon({
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

class _PromotionGridHeroPainter extends CustomPainter {
  const _PromotionGridHeroPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final wash = Paint()
      ..color = color.withOpacity(0.045)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(Offset(size.width * 0.88, size.height * 0.46), 120, wash);

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
  bool shouldRepaint(covariant _PromotionGridHeroPainter oldDelegate) {
    return oldDelegate.color != color;
  }
}

class _PromotionEntry {
  const _PromotionEntry({
    required this.offer,
    required this.items,
    this.paidItems = const <MenuItem>[],
  });

  final Map<String, dynamic> offer;
  final List<MenuItem> items;
  final List<MenuItem> paidItems;

  String get type => _offerType(offer);
  bool get isComboStyle => _isBundlePromotionType(type);
  List<MenuItem> get cartItems => paidItems.isNotEmpty ? paidItems : items;

  MenuItem get primaryItem =>
      cartItems.isNotEmpty ? cartItems.first : items.first;
}

class _PromotionMenuCard extends StatelessWidget {
  const _PromotionMenuCard({required this.entry});

  final _PromotionEntry entry;

  @override
  Widget build(BuildContext context) {
    final item = entry.primaryItem;
    final images = _entryImages(entry);
    final dealPrice = _dealPrice(entry);
    final originalPrice = _originalPrice(entry);
    final savings = (originalPrice - dealPrice).clamp(0, double.infinity);
    final quantity = _quantityForEntry(context.watch<CartProvider>(), entry);
    final tag = _promotionTypeTag(entry);
    final subtitle = entry.isComboStyle
        ? entry.items.take(3).map((item) => item.name).join(' + ')
        : item.description?.trim() ?? '';

    return InkWell(
      borderRadius: BorderRadius.circular(22),
      onTap: () => _openEntry(context, entry),
      child: Container(
        decoration: _cardDecoration(context),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              flex: 11,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  _AutoImageCarousel(
                    imageUrls: images,
                    fallback: _imageFallback(
                      icon: Icons.restaurant_menu_rounded,
                      color: _promoGridPrimary(context),
                    ),
                  ),
                  const _CardImageHighlight(),
                  Positioned(
                    left: 8,
                    top: 8,
                    child: _PromotionTypeTag(label: tag),
                  ),
                  if (item.hasDiscount || entry.isComboStyle)
                    Positioned(
                      right: 8,
                      bottom: 8,
                      child: _PromotionDiscountBadge(
                        text: _rewardText(context, entry.offer),
                      ),
                    ),
                ],
              ),
            ),
            Expanded(
              flex: 10,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        _VegMarker(isVeg: item.isVeg),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            entry.isComboStyle
                                ? _offerTitle(
                                    entry.offer,
                                    fallback: 'Combo Deal',
                                  )
                                : item.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: _promoGridText,
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
                      _restaurantNameForEntry(entry),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: _promoGridSubtext,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (subtitle.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _promoGridSubtext,
                          fontSize: 10,
                          height: 1.2,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                    const Spacer(),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: FittedBox(
                                      fit: BoxFit.scaleDown,
                                      alignment: Alignment.centerLeft,
                                      child: Text(
                                        formatCurrency(context, dealPrice),
                                        maxLines: 1,
                                        style: const TextStyle(
                                          color: _promoGridText,
                                          fontSize: 16,
                                          fontWeight: FontWeight.w900,
                                        ),
                                      ),
                                    ),
                                  ),
                                  if (originalPrice > dealPrice) ...[
                                    const SizedBox(width: 4),
                                    Flexible(
                                      child: Text(
                                        formatCurrency(context, originalPrice),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: TextStyle(
                                          color: _promoGridSubtext
                                              .withOpacity(0.72),
                                          fontSize: 10,
                                          fontWeight: FontWeight.w800,
                                          decoration:
                                              TextDecoration.lineThrough,
                                        ),
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
                                    color: _promoGridSuccess,
                                    fontSize: 9,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                        const SizedBox(width: 7),
                        _PromotionQuantityControl(
                          quantity: quantity,
                          enabled: item.isAvailable,
                          onChanged: (quantity) =>
                              _setEntryQuantity(context, entry, quantity),
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

class _AutoImageCarousel extends StatefulWidget {
  const _AutoImageCarousel({
    required this.imageUrls,
    required this.fallback,
  });

  final List<String> imageUrls;
  final Widget fallback;

  @override
  State<_AutoImageCarousel> createState() => _AutoImageCarouselState();
}

class _AutoImageCarouselState extends State<_AutoImageCarousel> {
  late final PageController _controller;
  Timer? _timer;
  int _page = 0;

  @override
  void initState() {
    super.initState();
    _controller = PageController();
    _startTimer();
  }

  @override
  void didUpdateWidget(covariant _AutoImageCarousel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.imageUrls.length != widget.imageUrls.length) {
      _timer?.cancel();
      _page = 0;
      _startTimer();
    }
  }

  void _startTimer() {
    if (widget.imageUrls.length <= 1) return;
    _timer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (!_controller.hasClients || !mounted) return;
      _page = (_page + 1) % widget.imageUrls.length;
      _controller.animateToPage(
        _page,
        duration: const Duration(milliseconds: 360),
        curve: Curves.easeOutCubic,
      );
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.imageUrls.isEmpty) return widget.fallback;

    return Stack(
      fit: StackFit.expand,
      children: [
        PageView.builder(
          controller: _controller,
          itemCount: widget.imageUrls.length,
          onPageChanged: (page) => setState(() => _page = page),
          itemBuilder: (context, index) {
            return AppCachedImage(
              imageUrl: widget.imageUrls[index],
              width: double.infinity,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => widget.fallback,
            );
          },
        ),
        if (widget.imageUrls.length > 1)
          Positioned(
            left: 0,
            right: 0,
            bottom: 9,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(
                widget.imageUrls.length,
                (index) => AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  width: index == _page ? 13 : 5,
                  height: 5,
                  margin: const EdgeInsets.symmetric(horizontal: 2),
                  decoration: BoxDecoration(
                    color:
                        Colors.white.withOpacity(index == _page ? 0.95 : 0.55),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _PromotionQuantityControl extends StatelessWidget {
  const _PromotionQuantityControl({
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
              ? [_promoGridPrimary(context), _promoGridSecondary(context)]
              : const [FoodFlowTheme.disabledFill, FoodFlowTheme.disabledText],
        ),
        borderRadius: BorderRadius.circular(15),
        boxShadow: [
          BoxShadow(
            color: _promoGridSuccess.withOpacity(enabled ? 0.22 : 0.08),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: SizedBox(
        width: 78,
        height: 31,
        child: isActive
            ? Row(
                children: [
                  _QuantityTapZone(
                    icon: Icons.remove_rounded,
                    enabled: enabled,
                    onTap: () => onChanged(quantity - 1),
                  ),
                  Expanded(
                    child: Text(
                      '$quantity',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
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
        height: double.infinity,
        child: Center(
          child: Container(
            width: 18,
            height: 18,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.18),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: Colors.white, size: 14),
          ),
        ),
      ),
    );
  }
}

class _PromotionTypeTag extends StatelessWidget {
  const _PromotionTypeTag({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 112),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [
            FoodFlowTheme.tagOrange,
            FoodFlowTheme.tagOrangeDark,
          ],
        ),
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.14),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Text(
        label.toUpperCase(),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 9,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _PromotionDiscountBadge extends StatelessWidget {
  const _PromotionDiscountBadge({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 72),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
      decoration: BoxDecoration(
        color: _promoGridSuccess,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        text,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          height: 1,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _DiscountStamp extends StatelessWidget {
  const _DiscountStamp({required this.text, required this.dense});

  final String text;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: dense ? 48 : 70,
      padding: EdgeInsets.symmetric(
        horizontal: dense ? 5 : 8,
        vertical: dense ? 6 : 9,
      ),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.94),
        borderRadius: BorderRadius.circular(9),
      ),
      child: Text(
        text,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: TextStyle(
          color: _promoGridText,
          fontSize: dense ? 9 : 14,
          height: 1.05,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _MiniDiscountStamp extends StatelessWidget {
  const _MiniDiscountStamp({required this.text, required this.dense});

  final String text;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: BoxConstraints(maxWidth: dense ? 48 : 74),
      padding: EdgeInsets.symmetric(
        horizontal: dense ? 5 : 8,
        vertical: dense ? 5 : 8,
      ),
      decoration: BoxDecoration(
        color: FoodFlowTheme.dangerDark,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        text,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: TextStyle(
          color: Colors.white,
          fontSize: dense ? 8.5 : 13,
          height: 1.05,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _AddButton extends StatelessWidget {
  const _AddButton({
    required this.color,
    required this.dense,
    required this.onTap,
  });

  final Color color;
  final bool dense;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [color, _promoGridSecondary(context)]),
        borderRadius: BorderRadius.circular(15),
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.24),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(15),
        onTap: onTap,
        child: SizedBox(
          width: double.infinity,
          height: dense ? 32 : 48,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                'Add',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: dense ? 11.5 : 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              SizedBox(width: dense ? 6 : 9),
              CircleAvatar(
                radius: dense ? 7.5 : 11,
                backgroundColor: Colors.white,
                child: Icon(
                  Icons.add_rounded,
                  color: _promoGridSuccess,
                  size: dense ? 12 : 17,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _VegMarker extends StatelessWidget {
  const _VegMarker({required this.isVeg});

  final bool isVeg;

  @override
  Widget build(BuildContext context) {
    final color = isVeg ? FoodFlowTheme.success : FoodFlowTheme.dangerDark;

    return Container(
      width: 13,
      height: 13,
      decoration: BoxDecoration(
        border: Border.all(color: color, width: 1.4),
      ),
      child: Center(
        child: Container(
          width: 6,
          height: 6,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
      ),
    );
  }
}

void _openEntry(BuildContext context, _PromotionEntry entry) {
  final item = entry.primaryItem;
  final restaurant = _restaurantFromEntry(entry);
  final restaurantId = restaurant?.id ?? item.restaurantId;
  if (restaurantId <= 0) return;

  Navigator.pushNamed(
    context,
    '/restaurant/detail',
    arguments: {
      'restaurant_id': restaurantId,
      'menu_item_id': item.id,
    },
  );
}

void _addEntryToCart(BuildContext context, _PromotionEntry entry) {
  final restaurant = _restaurantFromEntry(entry);
  if (restaurant == null) {
    _openEntry(context, entry);
    return;
  }

  final cartItems = entry.cartItems;
  if (cartItems.any((item) => item.hasCustomizations)) {
    _openEntry(context, entry);
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Choose item options before adding this promotion.'),
        duration: Duration(milliseconds: 1100),
      ),
    );
    return;
  }

  final cart = context.read<CartProvider>();
  final promotionId = _entryPromotionId(entry);
  final promotionTitle =
      _offerTitle(entry.offer, fallback: entry.primaryItem.name);
  final promotionCouponCode = _entryCouponCode(entry);
  final promotionGroupKey = _entryGroupKey(entry);
  final promotionGroupSize = cartItems.length;
  final useDealPrice = _usesEmbeddedDealPrice(entry.type);
  final dealPrice = useDealPrice ? _dealPrice(entry) : 0.0;
  final originalPrice = useDealPrice ? _originalPrice(entry) : 0.0;
  for (final item in cartItems) {
    cart.addItem(
      item,
      restaurant,
      promotionId: promotionId,
      promotionTitle: promotionTitle,
      promotionCouponCode: promotionCouponCode,
      promotionGroupKey: promotionGroupKey,
      promotionGroupSize: promotionGroupSize,
      promotionDealPrice: dealPrice > 0 ? dealPrice : null,
      promotionOriginalPrice: originalPrice > 0 ? originalPrice : null,
    );
  }

  ScaffoldMessenger.of(context).hideCurrentSnackBar();
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(
        '$promotionTitle added to cart',
      ),
      action: SnackBarAction(
        label: 'View Cart',
        onPressed: () => Navigator.pushNamed(context, '/cart'),
      ),
      duration: const Duration(seconds: 2),
    ),
  );
}

void _setEntryQuantity(
  BuildContext context,
  _PromotionEntry entry,
  int quantity,
) {
  final restaurant = _restaurantFromEntry(entry);
  if (restaurant == null) {
    _openEntry(context, entry);
    return;
  }

  final cartItems = entry.cartItems;
  if (cartItems.any((item) => item.hasCustomizations)) {
    _openEntry(context, entry);
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Choose item options before adding this promotion.'),
        duration: Duration(milliseconds: 1100),
      ),
    );
    return;
  }

  final cart = context.read<CartProvider>();
  final promotionId = _entryPromotionId(entry);
  final promotionTitle =
      _offerTitle(entry.offer, fallback: entry.primaryItem.name);
  final promotionCouponCode = _entryCouponCode(entry);
  final promotionGroupKey = _entryGroupKey(entry);
  final promotionGroupSize = cartItems.length;
  final useDealPrice = _usesEmbeddedDealPrice(entry.type);
  final dealPrice = useDealPrice ? _dealPrice(entry) : 0.0;
  final originalPrice = useDealPrice ? _originalPrice(entry) : 0.0;
  for (final item in cartItems) {
    cart.setItemQuantity(
      item,
      restaurant,
      quantity,
      promotionId: promotionId,
      promotionTitle: promotionTitle,
      promotionCouponCode: promotionCouponCode,
      promotionGroupKey: promotionGroupKey,
      promotionGroupSize: promotionGroupSize,
      promotionDealPrice: dealPrice > 0 ? dealPrice : null,
      promotionOriginalPrice: originalPrice > 0 ? originalPrice : null,
    );
  }
}

int _quantityForEntry(CartProvider cart, _PromotionEntry entry) {
  final restaurant = _restaurantFromEntry(entry);
  if (restaurant == null) return 0;
  final targetItem = entry.primaryItem;
  final targetPromotionId = _entryPromotionId(entry);
  final targetGroupKey = _entryGroupKey(entry);
  final normalizedName = targetItem.name.trim().toLowerCase();
  var quantity = 0;

  for (final restaurantCart in cart.carts) {
    if (restaurantCart.restaurant.id != restaurant.id) continue;
    for (final cartItem in restaurantCart.items) {
      if (targetGroupKey != null &&
          (cartItem.promotionId != targetPromotionId ||
              cartItem.promotionGroupKey != targetGroupKey)) {
        continue;
      }
      final menuItem = cartItem.menuItem;
      final sameId = menuItem.id == targetItem.id;
      final sameName = menuItem.restaurantId == targetItem.restaurantId &&
          menuItem.name.trim().toLowerCase() == normalizedName;
      if (sameId || sameName) quantity += cartItem.quantity;
    }
  }

  if (quantity > 0 || cart.restaurant?.id != restaurant.id) return quantity;

  for (final cartItem in cart.items) {
    if (targetGroupKey != null &&
        (cartItem.promotionId != targetPromotionId ||
            cartItem.promotionGroupKey != targetGroupKey)) {
      continue;
    }
    final menuItem = cartItem.menuItem;
    final sameId = menuItem.id == targetItem.id;
    final sameName = menuItem.restaurantId == targetItem.restaurantId &&
        menuItem.name.trim().toLowerCase() == normalizedName;
    if (sameId || sameName) quantity += cartItem.quantity;
  }

  return quantity;
}

String _restaurantNameForEntry(_PromotionEntry entry) {
  final offer = entry.offer;
  final restaurantMap = offer['restaurant'] is Map
      ? Map<String, dynamic>.from(offer['restaurant'] as Map)
      : const <String, dynamic>{};
  final name = (offer['restaurant_name'] ?? restaurantMap['name'] ?? '')
      .toString()
      .trim();
  return name.isEmpty || name == 'null' ? 'Restaurant' : name;
}

Restaurant? _restaurantFromEntry(_PromotionEntry entry) {
  final item = entry.primaryItem;
  final offer = entry.offer;
  final restaurantMap = offer['restaurant'] is Map
      ? Map<String, dynamic>.from(offer['restaurant'] as Map)
      : <String, dynamic>{};
  final restaurantId = _toInt(
    offer['restaurant_id'] ??
        offer['restaurantId'] ??
        offer['owner_id'] ??
        restaurantMap['id'] ??
        restaurantMap['restaurant_id'] ??
        item.restaurantId,
  );
  if (restaurantId <= 0) return null;

  return Restaurant.fromJson({
    ...restaurantMap,
    'id': restaurantId,
    'name': _restaurantNameForEntry(entry),
    'slug': restaurantMap['slug'] ?? '',
    'email': restaurantMap['email'] ?? '',
    'phone': restaurantMap['phone'] ?? '',
    'address': restaurantMap['address'] ?? '',
    'city': restaurantMap['city'] ?? '',
    'state': restaurantMap['state'] ?? '',
    'pincode': restaurantMap['pincode'] ?? '',
    'latitude': restaurantMap['latitude'] ?? 0,
    'longitude': restaurantMap['longitude'] ?? 0,
    'delivery_radius': restaurantMap['delivery_radius'] ?? 10,
    'min_order_amount': restaurantMap['min_order_amount'] ?? 0,
    'delivery_fee': restaurantMap['delivery_fee'] ?? 0,
    'delivery_time': restaurantMap['delivery_time'] ?? 30,
    'cuisine': restaurantMap['cuisine'] ?? const <String>[],
    'is_open': restaurantMap['is_open'] ?? true,
    'is_open_now': restaurantMap['is_open_now'] ?? true,
    'created_at':
        restaurantMap['created_at'] ?? DateTime.now().toIso8601String(),
  });
}

int _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

List<String> _entryImages(_PromotionEntry entry) {
  final urls = <String>[];
  for (final item in entry.items) {
    for (final image in item.images) {
      final resolved = AppImageCache.resolveUrl(image);
      if (resolved.isNotEmpty) urls.add(resolved);
    }
  }

  return urls.toSet().toList(growable: false);
}

String _offerType(Map<String, dynamic> offer) {
  final reward = offer['rewards'] is Map
      ? Map<String, dynamic>.from(offer['rewards'] as Map)
      : offer['reward_config'] is Map
          ? Map<String, dynamic>.from(offer['reward_config'] as Map)
          : const <String, dynamic>{};

  return (offer['promotion_type'] ??
          offer['reward_type'] ??
          offer['discount_type'] ??
          reward['type'] ??
          '')
      .toString()
      .trim()
      .toLowerCase();
}

bool _isBundlePromotionType(String type) {
  return type.contains('combo') || type.contains('meal');
}

bool _usesEmbeddedDealPrice(String type) {
  return type.contains('combo') || type.contains('meal');
}

bool _rawPromotionItemIsVeg(Map<dynamic, dynamic> item) {
  final value = item['is_veg'] ?? item['veg'] ?? item['isVegetarian'];
  if (value is bool) return value;
  if (value is num) return value != 0;
  final normalized = value?.toString().toLowerCase().trim();
  return normalized == '1' || normalized == 'true' || normalized == 'yes';
}

String _promotionTypeTag(_PromotionEntry entry) {
  final type = entry.type;
  if (type == 'bogo') return 'Buy 1 Get 1';
  if (type == 'buy_2_get_1') return 'Buy 2 Get 1';
  if (type == 'buy_3_get_1') return 'Buy 3 Get 1';
  if (type == 'buy_3_get_2') return 'Buy 3 Get 2';
  if (type == 'buy_x_get_y') return 'Buy X Get Y';
  if (type.contains('combo')) return 'Combo Deal';
  if (type.contains('meal')) return 'Meal Deal';
  if (type.contains('free_drink')) return 'Free Drink';
  if (type.contains('free_dessert')) return 'Free Dessert';
  if (type.contains('free_item')) return 'Free Item';
  if (type.contains('percentage')) return 'Percentage Off';
  if (type.contains('flat')) return 'Flat Off';
  if (type.contains('fixed')) return 'Fixed Price';

  final label = type
      .split('_')
      .where((part) => part.trim().isNotEmpty)
      .map((part) => part[0].toUpperCase() + part.substring(1))
      .join(' ');
  return label.isEmpty ? 'Promotion' : label;
}

String _offerTitle(Map<String, dynamic> offer, {required String fallback}) {
  final value = (offer['title'] ?? offer['name'] ?? '').toString().trim();
  return value.isEmpty || value == 'null' ? fallback : value;
}

String _rewardText(BuildContext context, Map<String, dynamic> offer) {
  final reward = offer['rewards'] is Map
      ? Map<String, dynamic>.from(offer['rewards'] as Map)
      : offer['reward_config'] is Map
          ? Map<String, dynamic>.from(offer['reward_config'] as Map)
          : const <String, dynamic>{};
  final type = _offerType(offer);
  final value = _numeric(
      offer['discount_value'] ?? offer['value'] ?? reward['value'] ?? 0);

  if (type == 'bogo') return 'Buy 1 Get 1';
  if (type == 'buy_3_get_1') return 'Buy 3 Get 1';
  if (type.startsWith('buy_')) {
    final buy = (reward['buy_quantity'] ?? '').toString();
    final free = (reward['free_quantity'] ?? '').toString();
    if (buy.isNotEmpty && free.isNotEmpty) return 'Buy $buy Get $free';
    return 'Buy More';
  }
  if (type.startsWith('free_')) return 'Free Item';
  if (type.contains('combo') || type.contains('meal')) {
    return value > 0 ? '${formatCurrency(context, value)} Deal' : 'Combo Deal';
  }
  if (type.contains('percentage') || type == 'percentage') {
    return '${_trim(value)}% OFF';
  }

  return value > 0 ? '${formatCurrency(context, value)} OFF' : 'Live Offer';
}

double _dealPrice(_PromotionEntry entry) {
  final reward = entry.offer['rewards'] is Map
      ? Map<String, dynamic>.from(entry.offer['rewards'] as Map)
      : entry.offer['reward_config'] is Map
          ? Map<String, dynamic>.from(entry.offer['reward_config'] as Map)
          : const <String, dynamic>{};
  final groupPrice = _numeric(entry.offer['effective_price'] ??
      entry.offer['combo_price'] ??
      entry.offer['price'] ??
      reward['effective_price'] ??
      reward['price']);
  if ((entry.type.contains('combo') || entry.type.contains('meal')) &&
      groupPrice > 0) {
    return groupPrice;
  }
  final value = _numeric(entry.offer['discount_value'] ??
      entry.offer['value'] ??
      reward['value'] ??
      0);

  if ((entry.type.contains('combo') || entry.type.contains('meal')) &&
      value > 0) {
    return value;
  }

  return entry.items.fold<double>(0, (total, item) => total + item.finalPrice);
}

double _originalPrice(_PromotionEntry entry) {
  final reward = entry.offer['rewards'] is Map
      ? Map<String, dynamic>.from(entry.offer['rewards'] as Map)
      : entry.offer['reward_config'] is Map
          ? Map<String, dynamic>.from(entry.offer['reward_config'] as Map)
          : const <String, dynamic>{};
  final groupActual = _numeric(entry.offer['actual_price'] ??
      entry.offer['original_price'] ??
      reward['actual_price'] ??
      reward['original_price']);
  if ((entry.type.contains('combo') || entry.type.contains('meal')) &&
      groupActual > 0) {
    return groupActual;
  }

  return entry.items.fold<double>(0, (total, item) => total + item.price);
}

int? _entryPromotionId(_PromotionEntry entry) {
  final value = entry.offer['id'] ?? entry.offer['promotion_id'];
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}

String? _entryCouponCode(_PromotionEntry entry) {
  final code = (entry.offer['coupon_code'] ?? entry.offer['code'] ?? '')
      .toString()
      .trim();
  return code.isEmpty || code == 'null' ? null : code;
}

String? _entryGroupKey(_PromotionEntry entry) {
  final key = (entry.offer['display_id'] ??
          entry.offer['group_key'] ??
          entry.offer['key'] ??
          entry.offer['id'] ??
          '')
      .toString()
      .trim();
  return key.isEmpty || key == 'null' ? null : key;
}

double _numeric(dynamic rawValue) {
  return rawValue is num
      ? rawValue.toDouble()
      : double.tryParse(rawValue.toString()) ?? 0;
}

String _trim(double value) {
  return value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(1);
}

Widget _imageFallback({required IconData icon, required Color color}) {
  return Container(
    color: color.withOpacity(0.1),
    child: Center(child: Icon(icon, color: color, size: 42)),
  );
}

class _CardImageHighlight extends StatelessWidget {
  const _CardImageHighlight();

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

BoxDecoration _cardDecoration(BuildContext context) {
  return _promotionPanelDecoration(
    context,
    radius: 22,
    shadowColor: _promoGridPrimary(context).withOpacity(0.13),
  );
}

BoxDecoration _promotionPanelDecoration(
  BuildContext context, {
  double radius = 28,
  Color? shadowColor,
}) {
  return BoxDecoration(
    gradient: const LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [
        FoodFlowTheme.surfaceColor,
        FoodFlowTheme.surfaceWarm,
        FoodFlowTheme.surfaceCool,
      ],
      stops: [0, 0.56, 1],
    ),
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: _promoGridLine),
    boxShadow: [
      BoxShadow(
        color: Colors.white.withOpacity(0.95),
        blurRadius: 3,
        offset: const Offset(-2, -2),
      ),
      BoxShadow(
        color: (shadowColor ?? _promoGridPrimary(context)).withOpacity(0.14),
        blurRadius: 22,
        spreadRadius: -3,
        offset: const Offset(0, 14),
      ),
      BoxShadow(
        color: Colors.black.withOpacity(0.07),
        blurRadius: 26,
        spreadRadius: -4,
        offset: const Offset(0, 16),
      ),
    ],
  );
}
