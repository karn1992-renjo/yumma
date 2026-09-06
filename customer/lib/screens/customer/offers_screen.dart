import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show Clipboard, ClipboardData;
import 'package:lottie/lottie.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../services/app_image_cache.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/account_chrome.dart';
import '../../widgets/customer/coupon_ticket_card.dart';
import '../../widgets/customer/promotion_detail_sheet.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/common/app_skeleton.dart';

class OffersScreen extends StatefulWidget {
  const OffersScreen({super.key});

  @override
  State<OffersScreen> createState() => _OffersScreenState();
}

class _OffersScreenState extends State<OffersScreen> {
  final ApiService _api = ApiService();

  List<Map<String, dynamic>> _offers = <Map<String, dynamic>>[];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadOffers();
  }

  Future<void> _loadOffers({bool forceRefresh = false}) async {
    setState(() => _loading = _offers.isEmpty);
    try {
      final response = await _api.get(
        ApiConstants.activeOffers,
        includeAuth: false,
        cachePolicy: ApiCachePolicy.discovery,
        cacheFirst: !forceRefresh,
        refreshCached: !forceRefresh,
        onCacheRefreshed: (_) {
          if (mounted) _loadOffers(forceRefresh: true);
        },
      );
      // Also pull every published promotion so admin coupon offers show here
      // even when the eligibility-filtered /offers/active list misses them.
      List<Map<String, dynamic>> allPromos = const [];
      try {
        final promoResponse = await _api.get(
          ApiConstants.promotions,
          includeAuth: false,
        );
        if (promoResponse['success'] == true && promoResponse['data'] is List) {
          allPromos = (promoResponse['data'] as List)
              .whereType<Map>()
              .map((offer) => Map<String, dynamic>.from(offer))
              .toList(growable: false);
        }
      } catch (_) {}

      final activeOffers = (response['success'] == true && response['data'] is List)
          ? (response['data'] as List)
              .whereType<Map>()
              .map((offer) => Map<String, dynamic>.from(offer))
              .toList(growable: false)
          : const <Map<String, dynamic>>[];

      // Coupons this customer personally holds (scratch-card wins, assigned
      // codes) -- these are user-scoped so they never come back on the public
      // lists above.
      final wonCoupons = await _loadWonCoupons();

      final seen = <String>{};
      _offers = [...wonCoupons, ...activeOffers, ...allPromos]
          .where((offer) =>
              (offer['source_type'] ?? 'promotion') == 'promotion' ||
              (offer['source_type'] ?? '') == 'scratch_card_reward')
          .where((offer) => _offerType(offer).isNotEmpty)
          .where((offer) => seen.add(
              '${offer['id'] ?? ''}:${offer['code'] ?? offer['coupon_code'] ?? ''}'))
          .toList(growable: false);
      _precacheOfferImages();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<List<Map<String, dynamic>>> _loadWonCoupons() async {
    try {
      final res = await _api.get(ApiConstants.rewardCoupons);
      if (res is! Map || res['success'] != true || res['data'] is! List) {
        return const [];
      }
      return (res['data'] as List)
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .where((c) => (c['status'] ?? '').toString().toLowerCase() == 'unused')
          .map((c) {
        final promo = c['promotion'] is Map
            ? Map<String, dynamic>.from(c['promotion'] as Map)
            : <String, dynamic>{};
        final rewards = promo['rewards'] is Map
            ? Map<String, dynamic>.from(promo['rewards'] as Map)
            : const <String, dynamic>{};
        return <String, dynamic>{
          ...promo,
          'id': promo['id'] ?? c['id'],
          'code': c['code'],
          'coupon_code': c['code'],
          'title': promo['title'] ?? 'Your coupon',
          'description': promo['description'] ?? 'Won from a scratch card',
          'promotion_type': promo['promotion_type'] ?? rewards['type'],
          'reward_type': rewards['type'] ?? promo['reward_type'],
          'discount_type': rewards['type'] ?? promo['promotion_type'],
          'discount_value': rewards['value'] ?? promo['discount_value'],
          'expires_at': c['expires_at'] ?? promo['ends_at'],
          'source_type': 'scratch_card_reward',
        };
      }).toList(growable: false);
    } catch (_) {
      return const [];
    }
  }

  void _precacheOfferImages() {
    if (!mounted || _offers.isEmpty) return;
    final urls = <String>{};
    void add(String url) {
      final trimmed = url.trim();
      if (trimmed.isNotEmpty) urls.add(trimmed);
    }

    for (final offer in _offers) {
      add(_offerImage(offer));
      for (final item in _menuItems(offer)) {
        add(_menuImage(item));
      }
      for (final category in _offerCategories(offer)) {
        add(_categoryImage(category));
      }
    }

    unawaited(AppImageCache.precacheVisible(context, urls, limit: 10));
  }

  List<_OfferGroup> _groups() {
    final grouped = <String, List<Map<String, dynamic>>>{};
    for (final offer in _offers) {
      final key = _offerType(offer);
      grouped.putIfAbsent(key, () => <Map<String, dynamic>>[]).add(offer);
    }

    final groups = grouped.entries
        .map((entry) => _OfferGroup(
              style: _styleForType(entry.key),
              offers: entry.value,
            ))
        .toList(growable: false);

    groups.sort((a, b) => a.style.order.compareTo(b.style.order));
    return groups;
  }

  @override
  Widget build(BuildContext context) {
    final groups = _groups();

    return Scaffold(
      backgroundColor: accountCanvas,
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: () => _loadOffers(forceRefresh: true),
          child: CustomScrollView(
            slivers: [
              SliverToBoxAdapter(child: _OffersHeader(onRefresh: _loadOffers)),
              if (_loading)
                const AppSkeletonSliverList(
                  itemCount: 4,
                  itemHeight: 126,
                )
              else if (groups.isEmpty)
                SliverFillRemaining(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Lottie.asset(
                        'assets/animations/Coupons.json',
                        width: 240,
                        height: 205,
                        fit: BoxFit.contain,
                      ),
                      const Text(
                        'No offers right now',
                        style: TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 6),
                      const Text(
                        'Fresh promotion-engine deals will appear here soon.',
                        style: TextStyle(color: FoodFlowTheme.muted),
                      ),
                    ],
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(0, 4, 0, 120),
                  sliver: SliverList.builder(
                    itemCount: groups.length,
                    itemBuilder: (context, index) {
                      final group = groups[index];
                      return Padding(
                        padding: EdgeInsets.only(
                          bottom: index == groups.length - 1 ? 0 : 24,
                        ),
                        child: _PromotionTypeSection(group: group),
                      );
                    },
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OffersHeader extends StatelessWidget {
  const _OffersHeader({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
      child: Row(
        children: [
          _CircleIconButton(
            icon: Icons.arrow_back_rounded,
            onTap: () => Navigator.of(context).maybePop(),
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Offers & Promos',
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 20,
                    height: 1.05,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Live promotion-engine deals for your next order',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          _CircleIconButton(
            icon: Icons.refresh_rounded,
            onTap: onRefresh,
          ),
        ],
      ),
    );
  }
}

class _PromotionTypeSection extends StatelessWidget {
  const _PromotionTypeSection({required this.group});

  final _OfferGroup group;

  @override
  Widget build(BuildContext context) {
    final style = group.style;
    final offers = group.offers;
    final displayOffers = _displayOffersForPromotionIntent(offers);
    final showCount = displayOffers.length > 10 ? 10 : displayOffers.length;
    final categories = _promotionCategories(offers);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  gradient: LinearGradient(colors: style.softColors),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(style.icon, color: style.colors.last, size: 27),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      style.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 24,
                        height: 1.05,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      style.subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.inkSoft,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              TextButton.icon(
                onPressed: () => _openPromotionProductGrid(
                  context,
                  style,
                  displayOffers,
                ),
                iconAlignment: IconAlignment.end,
                icon: const Icon(Icons.chevron_right_rounded),
                label: const Text('See all'),
                style: TextButton.styleFrom(
                  foregroundColor: style.colors.last,
                  textStyle: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Column(
            children: List.generate(showCount, (index) {
              final offer = displayOffers[index];
              return Padding(
                padding: EdgeInsets.only(
                  bottom: index == showCount - 1 ? 0 : 14,
                ),
                child: GestureDetector(
                  onTap: () => _isGlobalOffer(offer)
                      ? _handleGlobalOfferTap(context, offer)
                      : _openPromotionProductGrid(
                          context,
                          style,
                          [offer],
                        ),
                  child: _PromotionCard(
                    offer: offer,
                    style: style,
                  ),
                ),
              );
            }),
          ),
        ),
        if (categories.isNotEmpty) ...[
          const SizedBox(height: 18),
          _PromotionCategoryStrip(
            title: 'More ${style.title}',
            categories: categories,
            style: style,
            offers: offers,
          ),
        ],
        if (offers.length >= 3) ...[
          const SizedBox(height: 16),
          _BenefitStrip(style: style),
        ],
      ],
    );
  }
}

class _PromotionCard extends StatelessWidget {
  const _PromotionCard({
    required this.offer,
    required this.style,
  });

  final Map<String, dynamic> offer;
  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    // Coupon-code offers render as the "DISCOUNT COUPON" ticket; everything
    // else keeps the full-bleed promotional card.
    final code = _codeText(offer);
    if (code.isNotEmpty) {
      return CouponTicketCard(
        valueText: couponValueText(offer,
            currencySymbol: getCurrencySymbol(context)),
        title: _titleText(offer),
        code: code,
        subtitle: _subtitleText(offer),
        validUntilText: _offerValidUntilText(offer),
        accent: const Color(0xFF9A2FF2),
      );
    }
    return _FullImagePromotionCard(offer: offer, style: style);
  }
}

String? _offerValidUntilText(Map<String, dynamic> offer) {
  final raw = offer['expires_at'] ??
      offer['ends_at'] ??
      offer['end_date'] ??
      offer['valid_until'];
  if (raw == null) return null;
  final date = DateTime.tryParse(raw.toString());
  if (date == null) return null;
  const months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', //
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
  ];
  return '${date.day.toString().padLeft(2, '0')} ${months[date.month - 1]} ${date.year}';
}

class _FullImagePromotionCard extends StatelessWidget {
  const _FullImagePromotionCard({required this.offer, required this.style});

  final Map<String, dynamic> offer;
  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    final image = _offerImage(offer);
    final menuItems = _menuItems(offer);
    final menuTotal = _menuDealPrice(offer, menuItems);
    final menuOriginalTotal = _menuOriginalTotal(menuItems);
    final code = _codeText(offer);

    return Container(
      width: double.infinity,
      decoration: _cardDecoration(style.colors.last.withOpacity(0.16)),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 188,
            child: Stack(
              fit: StackFit.expand,
              children: [
                DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(colors: style.colors),
                  ),
                  child: menuItems.isNotEmpty
                      ? _MenuPairPreview(items: menuItems, style: style)
                      : image.isNotEmpty
                          ? _OfferImage(url: image, icon: style.icon)
                          : Icon(style.icon, color: Colors.white, size: 82),
                ),
                Positioned(
                  left: 12,
                  top: 12,
                  child: _Badge(
                    label: style.badge,
                    icon: style.icon,
                    colors: style.badgeColors,
                  ),
                ),
                Positioned(
                  right: 12,
                  top: 12,
                  child: _DiscountStamp(text: _rewardText(context, offer)),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _titleText(offer),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  _menuSummary(menuItems).isEmpty
                      ? _subtitleText(offer)
                      : _menuSummary(menuItems),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 12.5,
                    height: 1.25,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    if (menuTotal > 0) ...[
                      Flexible(
                        child: Text(
                          formatCurrency(context, menuTotal),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: style.colors.last,
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if (menuOriginalTotal > menuTotal) ...[
                        const SizedBox(width: 8),
                        Text(
                          formatCurrency(context, menuOriginalTotal),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                      ],
                    ] else
                      Expanded(
                        child: _MetaPill(
                          icon: Icons.schedule_rounded,
                          text: _validityText(offer),
                        ),
                      ),
                    const Spacer(),
                    _CodePill(code: code, color: style.colors.last),
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

class _ProductPromotionCard extends StatelessWidget {
  const _ProductPromotionCard({required this.offer, required this.style});

  final Map<String, dynamic> offer;
  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    final image = _offerImage(offer);
    final menuItems = _menuItems(offer);
    final menuTotal = _menuDealPrice(offer, menuItems);
    final menuOriginalTotal = _menuOriginalTotal(menuItems);
    final code = _codeText(offer);

    return Container(
      width: 242,
      decoration: _cardDecoration(style.colors.last.withOpacity(0.16)),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Stack(
              fit: StackFit.expand,
              children: [
                DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(colors: style.colors),
                  ),
                  child: menuItems.isNotEmpty
                      ? _MenuPairPreview(items: menuItems, style: style)
                      : image.isNotEmpty
                          ? _OfferImage(url: image, icon: style.icon)
                          : Icon(style.icon, color: Colors.white, size: 82),
                ),
                Positioned(
                  left: 12,
                  top: 12,
                  child: _Badge(
                    label: style.badge,
                    icon: style.icon,
                    colors: style.badgeColors,
                  ),
                ),
                Positioned(
                  right: 12,
                  top: 12,
                  child: _DiscountStamp(text: _rewardText(context, offer)),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _titleText(offer),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  _menuSummary(menuItems).isEmpty
                      ? _subtitleText(offer)
                      : _menuSummary(menuItems),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 12,
                    height: 1.25,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (menuTotal > 0) ...[
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Flexible(
                        child: Text(
                          formatCurrency(context, menuTotal),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: style.colors.last,
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if (menuOriginalTotal > menuTotal) ...[
                        const SizedBox(width: 8),
                        Text(
                          formatCurrency(context, menuOriginalTotal),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                        child: _MetaPill(
                            icon: Icons.schedule_rounded,
                            text: _validityText(offer))),
                    const SizedBox(width: 8),
                    _CodePill(code: code, color: style.colors.last),
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

class _BannerPromotionCard extends StatelessWidget {
  const _BannerPromotionCard({required this.offer, required this.style});

  final Map<String, dynamic> offer;
  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    final image = _offerImage(offer);
    final menuItems = _menuItems(offer);

    return Container(
      width: 318,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: style.colors,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: style.colors.last.withOpacity(0.22),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned(
            right: -26,
            bottom: -20,
            child: SizedBox(
              width: 150,
              height: 150,
              child: menuItems.isNotEmpty
                  ? _MenuImageStack(items: menuItems, style: style)
                  : image.isNotEmpty
                      ? _OfferImage(url: image, icon: style.icon)
                      : Icon(style.icon,
                          color: Colors.white.withOpacity(0.26), size: 110),
            ),
          ),
          Positioned(
            left: 16,
            top: 16,
            child: _Badge(
                label: style.badge,
                icon: style.icon,
                colors: style.badgeColors),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 62, 18, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _rewardText(context, offer).toUpperCase(),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 30,
                    height: 0.96,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  _menuSummary(menuItems).isEmpty
                      ? _titleText(offer)
                      : _menuSummary(menuItems),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.96),
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const Spacer(),
                Row(
                  children: [
                    Expanded(
                      child: _DarkMetaPill(
                        icon: Icons.schedule_rounded,
                        text: _validityText(offer),
                      ),
                    ),
                    const SizedBox(width: 10),
                    const Icon(Icons.arrow_forward_rounded,
                        color: Colors.white),
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

class _CompactPromotionCard extends StatelessWidget {
  const _CompactPromotionCard({required this.offer, required this.style});

  final Map<String, dynamic> offer;
  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    final image = _offerImage(offer);
    final menuItems = _menuItems(offer);

    return Container(
      width: 184,
      decoration: _cardDecoration(style.colors.last.withOpacity(0.14)),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 126,
            child: Stack(
              fit: StackFit.expand,
              children: [
                Container(
                  color: style.softColors.first,
                  child: menuItems.isNotEmpty
                      ? _CompactMenuHero(item: menuItems.first, style: style)
                      : image.isNotEmpty
                          ? _OfferImage(url: image, icon: style.icon)
                          : Icon(style.icon,
                              color: style.colors.last, size: 60),
                ),
                Positioned(
                  right: 10,
                  top: 10,
                  child: _MiniStamp(
                      text: _rewardText(context, offer),
                      color: style.colors.last),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _titleText(offer),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  _menuSummary(menuItems).isEmpty
                      ? _subtitleText(offer)
                      : _menuSummary(menuItems),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 12,
                    height: 1.2,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        _codeText(offer).isEmpty
                            ? 'Auto applies'
                            : _codeText(offer),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: style.colors.last,
                          fontSize: 12,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    CircleAvatar(
                      radius: 17,
                      backgroundColor: style.colors.last,
                      child: const Icon(Icons.arrow_forward_rounded,
                          color: Colors.white, size: 18),
                    ),
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

class _PromotionCategoryStrip extends StatelessWidget {
  const _PromotionCategoryStrip({
    required this.title,
    required this.categories,
    required this.style,
    required this.offers,
  });

  final String title;
  final List<Map<String, dynamic>> categories;
  final _PromotionStyle style;
  final List<Map<String, dynamic>> offers;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              TextButton.icon(
                onPressed: () =>
                    _openPromotionProductGrid(context, style, offers),
                iconAlignment: IconAlignment.end,
                icon: const Icon(Icons.chevron_right_rounded),
                label: const Text('See all'),
                style: TextButton.styleFrom(
                  foregroundColor: style.colors.last,
                  textStyle: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        SizedBox(
          height: 126,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            itemCount: categories.length,
            separatorBuilder: (_, __) => const SizedBox(width: 12),
            itemBuilder: (context, index) {
              final category = categories[index];
              return _PromotionCategoryCard(
                category: category,
                style: style,
                onTap: () => _openPromotionProductGrid(
                  context,
                  style,
                  _offersForCategory(offers, category),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _PromotionCategoryCard extends StatelessWidget {
  const _PromotionCategoryCard({
    required this.category,
    required this.style,
    required this.onTap,
  });

  final Map<String, dynamic> category;
  final _PromotionStyle style;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final image = _categoryImage(category);
    final name = _categoryName(category);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: 148,
        decoration: _cardDecoration(style.colors.last.withOpacity(0.08)),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  image.isNotEmpty
                      ? _OfferImage(url: image, icon: style.icon)
                      : Container(
                          color: style.softColors.first,
                          child: Icon(style.icon,
                              color: style.colors.last, size: 36),
                        ),
                  Positioned(
                    left: 8,
                    top: 8,
                    child: _CategoryRibbon(
                      text: style.badge,
                      color: style.colors.last,
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 9),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 13,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'On All $name',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.inkSoft,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  CircleAvatar(
                    radius: 16,
                    backgroundColor: const Color(0xFFF8FAFC),
                    child: Icon(Icons.arrow_forward_rounded,
                        color: FoodFlowTheme.ink, size: 17),
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

class _CategoryRibbon extends StatelessWidget {
  const _CategoryRibbon({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 54),
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 5),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(7),
      ),
      child: Text(
        text.replaceAll(' ', '\n'),
        maxLines: 3,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 9,
          height: 0.95,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _BenefitStrip extends StatelessWidget {
  const _BenefitStrip({required this.style});

  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: accountBorder),
      ),
      child: Row(
        children: [
          _BenefitItem(
              icon: Icons.verified_rounded,
              title: 'Live Deals',
              subtitle: style.title,
              color: style.colors.last),
          _Divider(),
          _BenefitItem(
              icon: Icons.bolt_rounded,
              title: 'Quick Apply',
              subtitle: 'Use at checkout',
              color: style.colors.first),
          _Divider(),
          _BenefitItem(
              icon: Icons.savings_rounded,
              title: 'Extra Savings',
              subtitle: 'More value inside',
              color: style.colors.last),
        ],
      ),
    );
  }
}

class _BenefitItem extends StatelessWidget {
  const _BenefitItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Row(
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: color.withOpacity(0.12),
            child: Icon(icon, color: color, size: 19),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
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

class _Divider extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      height: 38,
      margin: const EdgeInsets.symmetric(horizontal: 9),
      color: accountBorder,
    );
  }
}

class _OfferImage extends StatelessWidget {
  const _OfferImage({required this.url, required this.icon});

  final String url;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final resolvedUrl = AppImageCache.resolveUrl(url);
    if (resolvedUrl.isEmpty) {
      return Icon(icon, color: Colors.white, size: 64);
    }

    return AppCachedImage(
      imageUrl: resolvedUrl,
      fit: BoxFit.cover,
      errorWidget: Icon(icon, color: Colors.white, size: 64),
    );
  }
}

class _MenuPairPreview extends StatelessWidget {
  const _MenuPairPreview({required this.items, required this.style});

  final List<Map<String, dynamic>> items;
  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    final visible = items.take(2).toList(growable: false);

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 52, 12, 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(child: _PreviewMenuCard(item: visible.first, style: style)),
          if (visible.length > 1) ...[
            const SizedBox(width: 7),
            Container(
              width: 26,
              height: 26,
              margin: const EdgeInsets.only(bottom: 34),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.95),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.12),
                    blurRadius: 12,
                    offset: const Offset(0, 5),
                  ),
                ],
              ),
              child:
                  Icon(Icons.add_rounded, color: style.colors.last, size: 18),
            ),
            const SizedBox(width: 7),
            Expanded(child: _PreviewMenuCard(item: visible.last, style: style)),
          ],
        ],
      ),
    );
  }
}

class _PreviewMenuCard extends StatelessWidget {
  const _PreviewMenuCard({required this.item, required this.style});

  final Map<String, dynamic> item;
  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    final image = _menuImage(item);
    final price = _menuPrice(item);

    return Container(
      height: 104,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.14),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: image.isNotEmpty
                ? _OfferImage(url: image, icon: style.icon)
                : Container(
                    color: style.softColors.first,
                    child: Icon(style.icon, color: style.colors.last, size: 32),
                  ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 6, 8, 7),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _menuName(item),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (price > 0) ...[
                  const SizedBox(height: 2),
                  Text(
                    formatCurrency(context, price),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: style.colors.last,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MenuImageStack extends StatelessWidget {
  const _MenuImageStack({required this.items, required this.style});

  final List<Map<String, dynamic>> items;
  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    final visible = items.take(2).toList(growable: false);

    return Stack(
      clipBehavior: Clip.none,
      children: [
        for (var index = 0; index < visible.length; index++)
          Positioned(
            right: index == 0 ? 0 : 54,
            bottom: index == 0 ? 0 : 34,
            child: _StackedMenuImage(
              item: visible[index],
              style: style,
              size: index == 0 ? 112 : 86,
            ),
          ),
      ],
    );
  }
}

class _StackedMenuImage extends StatelessWidget {
  const _StackedMenuImage({
    required this.item,
    required this.style,
    required this.size,
  });

  final Map<String, dynamic> item;
  final _PromotionStyle style;
  final double size;

  @override
  Widget build(BuildContext context) {
    final image = _menuImage(item);

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.92),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.16),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: image.isNotEmpty
          ? _OfferImage(url: image, icon: style.icon)
          : Icon(style.icon, color: style.colors.last, size: size * 0.4),
    );
  }
}

class _CompactMenuHero extends StatelessWidget {
  const _CompactMenuHero({required this.item, required this.style});

  final Map<String, dynamic> item;
  final _PromotionStyle style;

  @override
  Widget build(BuildContext context) {
    final image = _menuImage(item);

    return Stack(
      fit: StackFit.expand,
      children: [
        image.isNotEmpty
            ? _OfferImage(url: image, icon: style.icon)
            : Icon(style.icon, color: style.colors.last, size: 58),
        Positioned(
          left: 10,
          right: 10,
          bottom: 10,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.92),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              _menuName(item),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 11,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({
    required this.label,
    required this.icon,
    required this.colors,
  });

  final String label;
  final IconData icon;
  final List<Color> colors;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: colors),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Colors.white, size: 15),
          const SizedBox(width: 5),
          Text(
            label,
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

class _DiscountStamp extends StatelessWidget {
  const _DiscountStamp({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 70,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 9),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.92),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        text,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: const TextStyle(
          color: FoodFlowTheme.ink,
          fontSize: 13,
          height: 1.05,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _MiniStamp extends StatelessWidget {
  const _MiniStamp({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 74),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.9),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(
        text,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: TextStyle(
          color: color,
          fontSize: 11,
          height: 1.05,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _MetaPill extends StatelessWidget {
  const _MetaPill({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F8FC),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, size: 15, color: FoodFlowTheme.inkSoft),
          const SizedBox(width: 5),
          Expanded(
            child: Text(
              text,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DarkMetaPill extends StatelessWidget {
  const _DarkMetaPill({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 17, color: Colors.white),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            text,
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
    );
  }
}

class _CodePill extends StatelessWidget {
  const _CodePill({required this.code, required this.color});

  final String code;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        code.isEmpty ? 'Auto' : code,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

BoxDecoration _cardDecoration(Color shadowColor) {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(22),
    border: Border.all(color: accountBorder),
    boxShadow: [
      BoxShadow(
        color: shadowColor,
        blurRadius: 18,
        offset: const Offset(0, 9),
      ),
    ],
  );
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
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: Border.all(color: accountBorder),
        ),
        child: Icon(icon, color: FoodFlowTheme.ink),
      ),
    );
  }
}

class _OfferGroup {
  const _OfferGroup({
    required this.style,
    required this.offers,
  });

  final _PromotionStyle style;
  final List<Map<String, dynamic>> offers;
}

class _PromotionStyle {
  const _PromotionStyle({
    required this.title,
    required this.subtitle,
    required this.badge,
    required this.icon,
    required this.colors,
    required this.softColors,
    required this.badgeColors,
    required this.layout,
    required this.order,
  });

  final String title;
  final String subtitle;
  final String badge;
  final IconData icon;
  final List<Color> colors;
  final List<Color> softColors;
  final List<Color> badgeColors;
  final _OfferLayout layout;
  final int order;
}

enum _OfferLayout { product, banner, compact }

/// A promotion that targets no specific restaurant, dish, category or
/// cuisine has nowhere for a tap to take the customer -- e.g. sitewide free
/// delivery, a blanket first-order %-off. Same rule as the web client's
/// isGlobalPromo(): true when targets.restaurant_ids/item_ids/category_ids/
/// cuisine_ids are all empty and there's no top-level restaurant_id.
bool _isGlobalOffer(Map<String, dynamic> offer) {
  final targets = offer['targets'] is Map
      ? Map<String, dynamic>.from(offer['targets'] as Map)
      : const <String, dynamic>{};

  bool hasEntries(dynamic value) => value is List && value.isNotEmpty;

  final hasScope = offer['restaurant_id'] != null ||
      hasEntries(targets['restaurant_ids']) ||
      hasEntries(targets['item_ids']) ||
      hasEntries(targets['category_ids']) ||
      hasEntries(targets['cuisine_ids']);

  return !hasScope;
}

String _offerType(Map<String, dynamic> offer) {
  if ((offer['source_type'] ?? '').toString() == 'scratch_card_reward') {
    return 'scratch_reward';
  }
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

_PromotionStyle _styleForType(String type) {
  if (type == 'scratch_reward') {
    return const _PromotionStyle(
      title: 'Your Coupons',
      subtitle: 'Rewards you won — ready to use',
      badge: 'YOURS',
      icon: Icons.confirmation_number_rounded,
      colors: [Color(0xFF7C2D91), Color(0xFF9A2FF2)],
      softColors: [Color(0xFFF6E9FF), Color(0xFFEFE0FF)],
      badgeColors: [Color(0xFF9A2FF2), Color(0xFF6D28D9)],
      layout: _OfferLayout.compact,
      order: -10,
    );
  }

  if (type.contains('combo') || type.contains('meal')) {
    return const _PromotionStyle(
      title: 'Combo Deals',
      subtitle: 'Best combos at best prices',
      badge: 'VALUE DEAL',
      icon: Icons.local_fire_department_rounded,
      colors: [Color(0xFF55A63E), Color(0xFF138A1E)],
      softColors: [Color(0xFFE8F8E5), Color(0xFFD8F2D4)],
      badgeColors: [Color(0xFF25A51E), Color(0xFF128117)],
      layout: _OfferLayout.product,
      order: 10,
    );
  }

  if (type == 'bogo' || type.startsWith('buy_')) {
    return const _PromotionStyle(
      title: 'Buy 1 Get 1 Free',
      subtitle: 'Double the joy, same price',
      badge: 'BESTSELLER',
      icon: Icons.card_giftcard_rounded,
      colors: [Color(0xFF221426), Color(0xFF6D28D9)],
      softColors: [Color(0xFFF3E8FF), Color(0xFFEDE9FE)],
      badgeColors: [Color(0xFF7C3AED), Color(0xFF4C1D95)],
      layout: _OfferLayout.banner,
      order: 20,
    );
  }

  if (type.startsWith('free_')) {
    return const _PromotionStyle(
      title: 'Free Item Offers',
      subtitle: 'Add something extra to your order',
      badge: 'FREE ITEM',
      icon: Icons.redeem_rounded,
      colors: [Color(0xFF047857), Color(0xFF16A34A)],
      softColors: [Color(0xFFDCFCE7), Color(0xFFECFDF5)],
      badgeColors: [Color(0xFF16A34A), Color(0xFF047857)],
      layout: _OfferLayout.banner,
      order: 30,
    );
  }

  if (type == 'free_delivery' || type == 'delivery_discount') {
    return const _PromotionStyle(
      title: 'Delivery Offers',
      subtitle: 'Save more before it reaches you',
      badge: 'FAST DEAL',
      icon: Icons.delivery_dining_rounded,
      colors: [Color(0xFFEF4444), Color(0xFFF97316)],
      softColors: [Color(0xFFFFEDD5), Color(0xFFFEE2E2)],
      badgeColors: [Color(0xFFF97316), Color(0xFFEA580C)],
      layout: _OfferLayout.compact,
      order: 40,
    );
  }

  if (type == 'wallet_cashback' ||
      type == 'wallet_credit' ||
      type == 'cashback') {
    return const _PromotionStyle(
      title: 'Cashback Rewards',
      subtitle: 'Money back into your wallet',
      badge: 'CASHBACK',
      icon: Icons.account_balance_wallet_rounded,
      colors: [Color(0xFF0F766E), Color(0xFF06B6D4)],
      softColors: [Color(0xFFCCFBF1), Color(0xFFE0F2FE)],
      badgeColors: [Color(0xFF0891B2), Color(0xFF0F766E)],
      layout: _OfferLayout.compact,
      order: 50,
    );
  }

  if (type == 'reward_points') {
    return const _PromotionStyle(
      title: 'Reward Points',
      subtitle: 'Earn points while you order',
      badge: 'POINTS',
      icon: Icons.stars_rounded,
      colors: [Color(0xFFCA8A04), Color(0xFFF59E0B)],
      softColors: [Color(0xFFFEF3C7), Color(0xFFFFFBEB)],
      badgeColors: [Color(0xFFF59E0B), Color(0xFFB45309)],
      layout: _OfferLayout.compact,
      order: 60,
    );
  }

  if (type == 'scratch_card') {
    return const _PromotionStyle(
      title: 'Scratch & Win',
      subtitle: 'Reveal surprise rewards',
      badge: 'SURPRISE',
      icon: Icons.auto_awesome_rounded,
      colors: [Color(0xFF7C2D12), Color(0xFFEC4899)],
      softColors: [Color(0xFFFCE7F3), Color(0xFFFFEDD5)],
      badgeColors: [Color(0xFFEC4899), Color(0xFFBE185D)],
      layout: _OfferLayout.banner,
      order: 70,
    );
  }

  if (type == 'gift_voucher' ||
      type.contains('voucher') ||
      type.contains('gift')) {
    return const _PromotionStyle(
      title: 'Gift Vouchers',
      subtitle: 'Unlock gift value on orders',
      badge: 'GIFT',
      icon: Icons.card_giftcard_rounded,
      colors: [Color(0xFFE11D48), Color(0xFFBE123C)],
      softColors: [Color(0xFFFFE4E6), Color(0xFFFCE7F3)],
      badgeColors: [Color(0xFFE11D48), Color(0xFFBE123C)],
      layout: _OfferLayout.compact,
      order: 80,
    );
  }

  if (type == 'referral_bonus' || type.contains('referral')) {
    return const _PromotionStyle(
      title: 'Referral Bonuses',
      subtitle: 'Invite and earn more',
      badge: 'REFER',
      icon: Icons.group_add_rounded,
      colors: [Color(0xFF2563EB), Color(0xFF1D4ED8)],
      softColors: [Color(0xFFDBEAFE), Color(0xFFEFF6FF)],
      badgeColors: [Color(0xFF2563EB), Color(0xFF1D4ED8)],
      layout: _OfferLayout.compact,
      order: 90,
    );
  }

  if (type == 'festival_offer') {
    return const _PromotionStyle(
      title: 'Festival Offers',
      subtitle: 'Celebrate with extra savings',
      badge: 'FESTIVE',
      icon: Icons.celebration_rounded,
      colors: [Color(0xFFEA580C), Color(0xFFDC2626)],
      softColors: [Color(0xFFFFEDD5), Color(0xFFFEE2E2)],
      badgeColors: [Color(0xFFEA580C), Color(0xFFDC2626)],
      layout: _OfferLayout.banner,
      order: 100,
    );
  }

  if (type == 'flash_sale') {
    return const _PromotionStyle(
      title: 'Flash Sale',
      subtitle: 'Limited-time deals moving fast',
      badge: 'FLASH',
      icon: Icons.flash_on_rounded,
      colors: [Color(0xFF111827), Color(0xFFEF4444)],
      softColors: [Color(0xFFFEE2E2), Color(0xFFF3F4F6)],
      badgeColors: [Color(0xFFEF4444), Color(0xFF991B1B)],
      layout: _OfferLayout.banner,
      order: 110,
    );
  }

  if (type == 'packaging_discount') {
    return const _PromotionStyle(
      title: 'Packaging Deals',
      subtitle: 'Lower fees on eligible orders',
      badge: 'SAVE FEES',
      icon: Icons.inventory_2_rounded,
      colors: [Color(0xFF9333EA), Color(0xFF6D28D9)],
      softColors: [Color(0xFFF3E8FF), Color(0xFFEDE9FE)],
      badgeColors: [Color(0xFF9333EA), Color(0xFF6D28D9)],
      layout: _OfferLayout.compact,
      order: 120,
    );
  }

  if (type.contains('percentage') || type == 'percent') {
    return const _PromotionStyle(
      title: 'Percentage Discounts',
      subtitle: 'A slice off your whole order',
      badge: '% OFF',
      icon: Icons.percent_rounded,
      colors: [Color(0xFFDB2777), Color(0xFF9333EA)],
      softColors: [Color(0xFFFCE7F3), Color(0xFFF3E8FF)],
      badgeColors: [Color(0xFFDB2777), Color(0xFF9333EA)],
      layout: _OfferLayout.product,
      order: 5,
    );
  }

  if (type.contains('fixed') || type.contains('flat') || type == 'amount') {
    return const _PromotionStyle(
      title: 'Flat Discounts',
      subtitle: 'Straight money off your bill',
      badge: 'FLAT OFF',
      icon: Icons.sell_rounded,
      colors: [Color(0xFF0EA5E9), Color(0xFF2563EB)],
      softColors: [Color(0xFFE0F2FE), Color(0xFFDBEAFE)],
      badgeColors: [Color(0xFF0EA5E9), Color(0xFF2563EB)],
      layout: _OfferLayout.product,
      order: 6,
    );
  }

  return const _PromotionStyle(
    title: 'Special Promotions',
    subtitle: 'Handpicked savings for you',
    badge: 'OFFER',
    icon: Icons.local_offer_rounded,
    colors: [Color(0xFFFF7A00), Color(0xFFE53935)],
    softColors: [Color(0xFFFFEDD5), Color(0xFFFEE2E2)],
    badgeColors: [Color(0xFFFF7A00), Color(0xFFE53935)],
    layout: _OfferLayout.product,
    order: 999,
  );
}

String _titleText(Map<String, dynamic> offer) {
  return (offer['title'] ?? offer['name'] ?? 'Special offer').toString().trim();
}

String _subtitleText(Map<String, dynamic> offer) {
  return (offer['description'] ??
          offer['subtitle'] ??
          offer['offer_text'] ??
          'Available on eligible restaurant orders')
      .toString()
      .trim();
}

String _codeText(Map<String, dynamic> offer) {
  var code = (offer['code'] ?? offer['coupon_code'] ?? '').toString().trim();
  if (code.isEmpty || code == 'null') {
    // Real API field is the plural `coupon_codes` array (see
    // PromotionController::show()'s `coupon_codes` + couponCodes relation);
    // `code`/`coupon_code` above are legacy/unused singular fallbacks that
    // never actually appear in a live promotion payload.
    final codes = offer['coupon_codes'];
    if (codes is List && codes.isNotEmpty) {
      final first = codes.first;
      code = (first is Map ? (first['code'] ?? '') : first).toString().trim();
    }
  }
  return code == 'null' ? '' : code;
}

void _handleGlobalOfferTap(BuildContext context, Map<String, dynamic> offer) {
  final code = _codeText(offer);
  final messenger = ScaffoldMessenger.of(context);
  if (code.isNotEmpty) {
    Clipboard.setData(ClipboardData(text: code));
    messenger.showSnackBar(SnackBar(content: Text('Code $code copied — apply it at checkout.')));
  } else {
    messenger.showSnackBar(const SnackBar(content: Text('Applied automatically at checkout.')));
  }
}

bool _offerHasShoppableItems(Map<String, dynamic> offer) {
  bool hasRealItems(dynamic v) {
    if (v is! List) return false;
    return v.whereType<Map>().any((m) {
      final id = m['menu_item_id'] ?? m['id'] ?? m['item_id'];
      final name = (m['name'] ?? m['title'] ?? '').toString().trim();
      return (int.tryParse('${id ?? ''}') ?? 0) > 0 || name.isNotEmpty;
    });
  }

  return _menuItems(offer).isNotEmpty || hasRealItems(offer['reward_menu_items']);
}

void _openPromotionProductGrid(
  BuildContext context,
  _PromotionStyle style,
  List<Map<String, dynamic>> offers,
) {
  final shoppable = offers.where(_offerHasShoppableItems).toList(growable: false);

  // Banner-only promotion(s) -> open the offer detail, never an empty grid.
  if (shoppable.isEmpty) {
    if (offers.length == 1) {
      final offer = offers.first;
      final restaurantId = int.tryParse(
              (offer['restaurant_id'] ?? offer['restaurantId'] ?? '').toString()) ??
          0;
      showPromotionDetailSheet(
        context,
        offer,
        onPrimaryAction: restaurantId > 0
            ? () => Navigator.pushNamed(context, '/restaurant/detail',
                arguments: restaurantId)
            : null,
      );
    } else if (offers.isNotEmpty) {
      showPromotionDetailSheet(context, offers.first);
    }
    return;
  }

  Navigator.pushNamed(
    context,
    '/promotion-products',
    arguments: {
      'title': style.title,
      'subtitle': style.subtitle,
      'promotion_type':
          shoppable.isEmpty ? null : _offerType(shoppable.first),
      'offers': shoppable,
    },
  );
}

bool _isBundlePromotionType(String type) {
  return type.contains('combo') || type.contains('meal');
}

bool _isItemRewardPromotionType(String type) {
  return type == 'bogo' || type.startsWith('buy_') || type.startsWith('free_');
}

List<Map<String, dynamic>> _displayOffersForPromotionIntent(
  List<Map<String, dynamic>> offers,
) {
  final displayOffers = <Map<String, dynamic>>[];
  final seen = <String>{};

  for (final offer in offers) {
    final type = _offerType(offer);
    final menuItems = _menuItems(offer);
    if (!_isItemRewardPromotionType(type) ||
        _isBundlePromotionType(type) ||
        menuItems.length <= 1) {
      displayOffers.add(offer);
      continue;
    }

    for (final item in menuItems) {
      final itemId =
          (item['menu_item_id'] ?? item['id'] ?? _menuName(item)).toString();
      final key = '${offer['display_id'] ?? offer['id'] ?? 'offer'}:$itemId';
      if (!seen.add(key)) continue;

      displayOffers.add({
        ...offer,
        'display_id': key,
        'display_item_id': itemId,
        'title': _menuName(item),
        'subtitle': offer['title'] ?? offer['subtitle'] ?? offer['description'],
        'menu_items': [item],
      });
    }
  }

  return displayOffers;
}

List<Map<String, dynamic>> _promotionCategories(
    List<Map<String, dynamic>> offers) {
  final seen = <String>{};
  final categories = <Map<String, dynamic>>[];

  for (final offer in offers) {
    for (final category in _offerCategories(offer)) {
      final id =
          (category['category_id'] ?? category['id'] ?? _categoryName(category))
              .toString();
      if (id.isEmpty || !seen.add(id)) continue;
      categories.add(category);
    }
  }

  return categories;
}

List<Map<String, dynamic>> _offerCategories(Map<String, dynamic> offer) {
  for (final key in const [
    'promotion_categories',
    'categories',
    'target_categories'
  ]) {
    final raw = offer[key];
    if (raw is List) {
      return raw
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .where((item) => _categoryName(item).isNotEmpty)
          .toList(growable: false);
    }
  }

  return const <Map<String, dynamic>>[];
}

List<Map<String, dynamic>> _offersForCategory(
  List<Map<String, dynamic>> offers,
  Map<String, dynamic> category,
) {
  final categoryId =
      (category['category_id'] ?? category['id'] ?? '').toString();
  final categoryName = _categoryName(category).toLowerCase();
  final matched = offers.where((offer) {
    return _offerCategories(offer).any((candidate) {
      final id = (candidate['category_id'] ?? candidate['id'] ?? '').toString();
      if (categoryId.isNotEmpty && id == categoryId) return true;
      return _categoryName(candidate).toLowerCase() == categoryName;
    });
  }).toList(growable: false);

  return matched.isEmpty ? offers : matched;
}

String _categoryName(Map<String, dynamic> category) {
  return (category['name'] ?? category['title'] ?? 'Category')
      .toString()
      .trim();
}

String _categoryImage(Map<String, dynamic> category) {
  for (final key in const [
    'image_url',
    'image',
    'thumbnail_url',
    'photo_url'
  ]) {
    final value = category[key]?.toString().trim() ?? '';
    if (value.isNotEmpty && value != 'null') return value;
  }
  return '';
}

List<Map<String, dynamic>> _menuItems(Map<String, dynamic> offer) {
  final raw = offer['menu_items'];
  if (raw is! List) return const <Map<String, dynamic>>[];

  final items = raw
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .where((item) => _menuName(item).isNotEmpty)
      .toList(growable: false);
  final eligibleItems = items
      .where((item) => item['is_reward_item'] != true)
      .toList(growable: false);

  return eligibleItems.isNotEmpty ? eligibleItems : items;
}

String _menuName(Map<String, dynamic> item) {
  return (item['name'] ?? item['title'] ?? 'Menu item').toString().trim();
}

String _menuImage(Map<String, dynamic> item) {
  for (final key in const [
    'image_url',
    'image',
    'thumbnail_url',
    'photo_url'
  ]) {
    final value = item[key]?.toString().trim() ?? '';
    if (value.isNotEmpty && value != 'null') return value;
  }
  return '';
}

double _menuPrice(Map<String, dynamic> item) {
  final discounted = _numeric(item['discounted_price']);
  if (discounted > 0) return discounted;
  return _numeric(item['price']);
}

double _menuOriginalPrice(Map<String, dynamic> item) {
  final original = _numeric(item['price']);
  return original > 0 ? original : _menuPrice(item);
}

double _menuTotal(List<Map<String, dynamic>> items) {
  return items.fold<double>(0, (total, item) => total + _menuPrice(item));
}

double _menuOriginalTotal(List<Map<String, dynamic>> items) {
  return items.fold<double>(
      0, (total, item) => total + _menuOriginalPrice(item));
}

double _menuDealPrice(
    Map<String, dynamic> offer, List<Map<String, dynamic>> items) {
  final type = _offerType(offer);
  final reward = offer['rewards'] is Map
      ? Map<String, dynamic>.from(offer['rewards'] as Map)
      : offer['reward_config'] is Map
          ? Map<String, dynamic>.from(offer['reward_config'] as Map)
          : const <String, dynamic>{};
  final value = _numeric(
      offer['discount_value'] ?? offer['value'] ?? reward['value'] ?? 0);

  if ((type.contains('combo') || type.contains('meal')) && value > 0) {
    return value;
  }

  return _menuTotal(items);
}

String _menuSummary(List<Map<String, dynamic>> items) {
  if (items.isEmpty) return '';
  return items.take(3).map(_menuName).join(' + ');
}

String _offerImage(Map<String, dynamic> offer) {
  for (final key in const [
    'promo_image',
    'promo_image_url',
    'image_url',
    'image',
    'banner_image',
  ]) {
    final value = offer[key]?.toString().trim() ?? '';
    if (value.isNotEmpty && value != 'null') return value;
  }
  return '';
}

String _validityText(Map<String, dynamic> offer) {
  final raw = (offer['valid_to'] ?? offer['end_date'] ?? offer['ends_at'] ?? '')
      .toString();
  if (raw.isEmpty || raw == 'null') return 'Limited time';
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) return 'Limited time';
  final now = DateTime.now();
  final days = parsed.difference(now).inDays;
  if (days < 0) return 'Ending soon';
  if (days == 0) return 'Ends today';
  if (days == 1) return '1 day left';
  if (days < 30) return '$days days left';
  return 'Valid till ${parsed.day}/${parsed.month}';
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

  if (type == 'free_delivery') return 'Free Delivery';
  if (type == 'delivery_discount')
    return _percentOrMoney(context, value, 'Delivery');
  if (type == 'packaging_discount')
    return _percentOrMoney(context, value, 'Packaging');
  if (type == 'wallet_cashback' || type == 'wallet_credit') {
    return value > 0
        ? '${formatCurrency(context, value)} Wallet'
        : 'Wallet Cashback';
  }
  if (type == 'cashback')
    return value > 0 ? '${_trim(value)}% Cashback' : 'Cashback';
  if (type == 'reward_points')
    return value > 0 ? '${_trim(value)} Points' : 'Reward Points';
  if (type == 'scratch_card') return 'Scratch & Win';
  if (type == 'gift_voucher')
    return value > 0
        ? '${formatCurrency(context, value)} Voucher'
        : 'Gift Voucher';
  if (type == 'referral_bonus')
    return value > 0
        ? '${formatCurrency(context, value)} Referral'
        : 'Referral Bonus';
  if (type == 'festival_offer')
    return value > 0 ? '${_trim(value)}% Festival' : 'Festival Offer';
  if (type == 'flash_sale')
    return value > 0 ? '${_trim(value)}% Flash' : 'Flash Sale';
  if (type == 'bogo') return 'Buy 1 Get 1';
  if (type == 'buy_3_get_1') return 'Buy 3 Get 1';
  if (type.startsWith('buy_')) {
    final buy = (reward['buy_quantity'] ?? '').toString();
    final free = (reward['free_quantity'] ?? '').toString();
    if (buy.isNotEmpty && free.isNotEmpty) return 'Buy $buy Get $free';
    return 'Buy More';
  }
  if (type.startsWith('free_')) return 'Free Item';
  if (type.contains('combo'))
    return value > 0 ? '${formatCurrency(context, value)} Deal' : 'Combo Deal';
  if (type.contains('percentage') || type == 'percentage')
    return '${_trim(value)}% OFF';
  return value > 0 ? '${formatCurrency(context, value)} OFF' : 'Live Offer';
}

String _percentOrMoney(BuildContext context, double value, String suffix) {
  if (value <= 0) return '$suffix Off';
  if (value <= 100) return '${_trim(value)}% $suffix';
  return '${formatCurrency(context, value)} $suffix';
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
