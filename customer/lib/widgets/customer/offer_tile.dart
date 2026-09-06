import 'package:flutter/material.dart';

import '../../utils/currency_utils.dart';
import '../common/app_cached_image.dart';

/// Shared promo/offer banner card.
///
/// This is a lift-and-shift of the `_OfferTile` used on the V1 production home
/// screen (`home_screen_production.dart`) so V2 (`home_feed_v2.dart`) can render
/// the *exact* same banner. Keep the two visually in sync — if the V1 tile
/// changes, mirror it here (and vice versa). The helper functions below are the
/// transitive dependencies of that widget, copied verbatim.
class OfferTile extends StatelessWidget {
  const OfferTile({
    super.key,
    required this.offer,
    this.onTap,
    this.width = 310,
    this.compact = false,
    this.showCoupon = true,
    this.showText = true,
    this.showRewardAndValidityOnly = false,
  });

  final Map<String, dynamic> offer;
  final VoidCallback? onTap;
  final double width;
  final bool compact;
  final bool showCoupon;
  final bool showText;
  final bool showRewardAndValidityOnly;

  @override
  Widget build(BuildContext context) {
    final style = _offerVisualStyle(offer);
    final title =
        (offer['title'] ?? offer['code'] ?? 'Special offer').toString().trim();
    final code =
        (offer['code'] ?? offer['coupon_code'] ?? '').toString().trim();
    final imageUrl = _homeOfferImage(offer);
    final menuLabel = _homeOfferMenuLabel(offer);
    final rewardLabel = _homeOfferRewardText(context, offer).trim();

    return GestureDetector(
      onTap: onTap ?? () => Navigator.pushNamed(context, '/offers'),
      child: Container(
        width: width,
        decoration: BoxDecoration(
          color: style.colors.last,
          borderRadius: BorderRadius.circular(compact ? 16 : 18),
          boxShadow: [
            BoxShadow(
              color: style.colors.last.withOpacity(0.24),
              blurRadius: compact ? 12 : 18,
              offset: Offset(0, compact ? 7 : 10),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: <Widget>[
            Positioned.fill(
              child: imageUrl.isNotEmpty
                  ? AppCachedImage(
                      imageUrl: imageUrl,
                      // cover so the artwork fills the whole card (no
                      // letterbox gap); the text overlay sits on the gradient.
                      fit: BoxFit.cover,
                      width: width.isFinite ? width : null,
                      height: compact ? 154 : 220,
                      loadingBuilder: (context, _, __) =>
                          _OfferBackdropFallback(style: style),
                      errorBuilder: (_, __, ___) =>
                          _OfferBackdropFallback(style: style),
                    )
                  : _OfferBackdropFallback(style: style),
            ),
            if (showText)
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.centerLeft,
                      end: Alignment.centerRight,
                      colors: [
                        Colors.black.withOpacity(0.72),
                        Colors.black.withOpacity(0.25),
                        Colors.black.withOpacity(0.08),
                      ],
                    ),
                  ),
                ),
              ),
            if (showText)
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 13, 14, 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    _OfferBannerBadge(style: style),
                    const SizedBox(height: 12),
                    if (rewardLabel.isNotEmpty) ...[
                      Text(
                        rewardLabel.toUpperCase(),
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 28,
                          height: 0.92,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 5),
                    ],
                    Text(
                      menuLabel.isEmpty ? title : menuLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.92),
                        fontSize: 12,
                        height: 1.15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (showCoupon) ...[
                      const SizedBox(height: 9),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 7),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.94),
                          borderRadius: BorderRadius.circular(9),
                        ),
                        child: Text(
                          code.isEmpty ? '+ FREE ITEM' : 'CODE $code',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: style.colors.last,
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                    const Spacer(),
                    Row(
                      children: [
                        const Icon(Icons.timer_outlined,
                            color: Colors.white, size: 16),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            _homeOfferValidity(offer).toUpperCase(),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        Text(
                          _homeOfferEndDate(offer),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.92),
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(width: 4),
                        const Icon(Icons.chevron_right_rounded,
                            color: Colors.white, size: 17),
                      ],
                    ),
                  ],
                ),
              ),
            if (showRewardAndValidityOnly) ...[
              if (rewardLabel.isNotEmpty)
                Positioned(
                  left: compact ? 8 : 12,
                  top: compact ? 8 : 12,
                  right: compact ? 8 : 12,
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Container(
                      padding: EdgeInsets.symmetric(
                        horizontal: compact ? 7 : 10,
                        vertical: compact ? 5 : 7,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.56),
                        borderRadius: BorderRadius.circular(compact ? 9 : 12),
                      ),
                      child: Text(
                        rewardLabel.toUpperCase(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: compact ? 9.2 : 12,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ),
                ),
              Positioned(
                left: compact ? 8 : 12,
                right: compact ? 8 : 12,
                bottom: compact ? 8 : 12,
                child: Container(
                  padding: EdgeInsets.symmetric(
                    horizontal: compact ? 7 : 10,
                    vertical: compact ? 6 : 8,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.50),
                    borderRadius: BorderRadius.circular(compact ? 10 : 12),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.timer_outlined,
                        color: Colors.white,
                        size: compact ? 12 : 15,
                      ),
                      SizedBox(width: compact ? 4 : 6),
                      Expanded(
                        child: Text(
                          _homeOfferValidity(offer).toUpperCase(),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: compact ? 8.6 : 10.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if (!compact) ...[
                        Text(
                          _homeOfferEndDate(offer),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.92),
                            fontSize: 10.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(width: 2),
                      ],
                      Icon(
                        Icons.chevron_right_rounded,
                        color: Colors.white,
                        size: compact ? 13 : 16,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _OfferBannerBadge extends StatelessWidget {
  const _OfferBannerBadge({required this.style});

  final _HomeOfferStyle style;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: style.colors),
        borderRadius: BorderRadius.circular(9),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(style.icon, color: Colors.white, size: 13),
          const SizedBox(width: 5),
          Text(
            style.label.toUpperCase(),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 10,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _OfferBackdropFallback extends StatelessWidget {
  const _OfferBackdropFallback({required this.style});

  final _HomeOfferStyle style;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: style.colors,
        ),
      ),
      child: Align(
        alignment: Alignment.centerRight,
        child: Padding(
          padding: const EdgeInsets.only(right: 26),
          child: Icon(
            style.icon,
            color: Colors.white.withOpacity(0.26),
            size: 104,
          ),
        ),
      ),
    );
  }
}

class _HomeOfferStyle {
  const _HomeOfferStyle({
    required this.label,
    required this.icon,
    required this.colors,
  });

  final String label;
  final IconData icon;
  final List<Color> colors;
}

_HomeOfferStyle _offerVisualStyle(Map<String, dynamic> offer) {
  final type = _homeOfferType(offer);
  if (type.contains('delivery')) {
    return const _HomeOfferStyle(
      label: 'Delivery',
      icon: Icons.delivery_dining_rounded,
      colors: [Color(0xFF0891B2), Color(0xFF0E7490)],
    );
  }
  if (type.contains('packaging')) {
    return const _HomeOfferStyle(
      label: 'Packaging',
      icon: Icons.inventory_2_rounded,
      colors: [Color(0xFF7C3AED), Color(0xFF5B21B6)],
    );
  }
  if (type.contains('wallet') || type.contains('cashback')) {
    return const _HomeOfferStyle(
      label: 'Cashback',
      icon: Icons.account_balance_wallet_rounded,
      colors: [Color(0xFF059669), Color(0xFF047857)],
    );
  }
  if (type.contains('reward_points')) {
    return const _HomeOfferStyle(
      label: 'Points',
      icon: Icons.stars_rounded,
      colors: [Color(0xFFF59E0B), Color(0xFFD97706)],
    );
  }
  if (type.contains('scratch')) {
    return const _HomeOfferStyle(
      label: '',
      icon: Icons.confirmation_number_rounded,
      colors: [Color(0xFFDB2777), Color(0xFFBE185D)],
    );
  }
  if (type.contains('voucher') || type.contains('gift')) {
    return const _HomeOfferStyle(
      label: 'Gift',
      icon: Icons.card_giftcard_rounded,
      colors: [Color(0xFFE11D48), Color(0xFFBE123C)],
    );
  }
  if (type.contains('referral')) {
    return const _HomeOfferStyle(
      label: 'Referral',
      icon: Icons.group_add_rounded,
      colors: [Color(0xFF2563EB), Color(0xFF1D4ED8)],
    );
  }
  if (type.contains('festival')) {
    return const _HomeOfferStyle(
      label: 'Festival',
      icon: Icons.celebration_rounded,
      colors: [Color(0xFFEA580C), Color(0xFFDC2626)],
    );
  }
  if (type.contains('flash')) {
    return const _HomeOfferStyle(
      label: 'Flash Sale',
      icon: Icons.flash_on_rounded,
      colors: [Color(0xFF111827), Color(0xFFEF4444)],
    );
  }
  if (type.contains('bogo') ||
      type.contains('buy_') ||
      type.contains('free_item') ||
      type.contains('free_drink') ||
      type.contains('free_dessert')) {
    return const _HomeOfferStyle(
      label: 'Free Item',
      icon: Icons.redeem_rounded,
      colors: [Color(0xFF16A34A), Color(0xFF15803D)],
    );
  }
  if (type.contains('combo') || type.contains('meal')) {
    return const _HomeOfferStyle(
      label: 'Combo Deal',
      icon: Icons.fastfood_rounded,
      colors: [Color(0xFFFF6B00), Color(0xFFEA580C)],
    );
  }
  if (type.contains('custom')) {
    return const _HomeOfferStyle(
      label: 'Special Rule',
      icon: Icons.auto_awesome_rounded,
      colors: [Color(0xFF4F46E5), Color(0xFF7C3AED)],
    );
  }
  return const _HomeOfferStyle(
    label: 'Offer',
    icon: Icons.local_offer_rounded,
    colors: [Color(0xFFFF7A00), Color(0xFFE53935)],
  );
}

String _homeOfferType(Map<String, dynamic> offer) {
  final reward = offer['rewards'] is Map
      ? Map<String, dynamic>.from(offer['rewards'] as Map)
      : offer['reward_config'] is Map
          ? Map<String, dynamic>.from(offer['reward_config'] as Map)
          : const <String, dynamic>{};
  return (offer['reward_type'] ??
          reward['type'] ??
          offer['promotion_type'] ??
          offer['discount_type'] ??
          '')
      .toString()
      .toLowerCase();
}

String _homeOfferImage(Map<String, dynamic> offer) {
  for (final key in const [
    'promo_image',
    'promo_image_url',
    'promotion_image',
    'promotion_image_url',
    'image_url',
    'image',
    'banner_image',
    'banner_image_url',
    'thumbnail',
    'thumbnail_url',
  ]) {
    final value = offer[key]?.toString().trim() ?? '';
    if (value.isNotEmpty && value != 'null') return value;
  }

  for (final nestedKey in const ['visibility', 'media', 'assets']) {
    final nested = offer[nestedKey];
    if (nested is! Map) continue;
    final nestedMap = Map<String, dynamic>.from(nested);
    for (final key in const [
      'promo_image',
      'promotion_image',
      'image_url',
      'image',
      'banner_image',
      'thumbnail',
    ]) {
      final value = nestedMap[key]?.toString().trim() ?? '';
      if (value.isNotEmpty && value != 'null') return value;
    }
  }

  final menuItems = offer['menu_items'];
  if (menuItems is List) {
    for (final item in menuItems.whereType<Map>()) {
      final images = item['images'];
      if (images is List) {
        for (final image in images) {
          final value = image?.toString().trim() ?? '';
          if (value.isNotEmpty && value != 'null') return value;
        }
      }

      for (final key in const [
        'image_url',
        'image',
        'thumbnail_url',
        'photo_url'
      ]) {
        final value = item[key]?.toString().trim() ?? '';
        if (value.isNotEmpty && value != 'null') return value;
      }
    }
  }

  return '';
}

String _homeOfferMenuLabel(Map<String, dynamic> offer) {
  final menuItems = offer['menu_items'];
  if (menuItems is! List) return '';

  final names = menuItems
      .whereType<Map>()
      .map((item) => (item['name'] ?? item['title'] ?? '').toString().trim())
      .where((name) => name.isNotEmpty && name != 'null')
      .take(2)
      .toList(growable: false);

  if (names.isEmpty) return '';
  if (names.length == 1) return 'On ${names.first}';
  return names.join(' + ');
}

String _homeOfferValidity(Map<String, dynamic> offer) {
  final raw = (offer['valid_to'] ?? offer['end_date'] ?? offer['ends_at'] ?? '')
      .toString();
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) return 'Limited time';

  final remaining = parsed.difference(DateTime.now());
  if (remaining.inMinutes <= 0) return 'Ending soon';
  if (remaining.inHours < 1) return '${remaining.inMinutes} mins left';
  if (remaining.inHours < 24) return '${remaining.inHours} hrs left';
  if (remaining.inDays == 1) return '1 day left';
  return '${remaining.inDays} days left';
}

String _homeOfferEndDate(Map<String, dynamic> offer) {
  final raw = (offer['valid_to'] ?? offer['end_date'] ?? offer['ends_at'] ?? '')
      .toString();
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) return 'Limited time';

  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];

  return 'Valid till ${parsed.day} ${months[parsed.month - 1]}';
}

String _homeOfferRewardText(BuildContext context, Map<String, dynamic> offer) {
  final type = _homeOfferType(offer);
  final reward = offer['rewards'] is Map
      ? Map<String, dynamic>.from(offer['rewards'] as Map)
      : offer['reward_config'] is Map
          ? Map<String, dynamic>.from(offer['reward_config'] as Map)
          : const <String, dynamic>{};
  final value = _homePriceValue(
    offer['discount_value'] ?? offer['value'] ?? reward['value'] ?? 0,
  );

  if (type == 'free_delivery') return 'Free Delivery';
  if (type == 'delivery_discount') {
    return _percentOrMoney(context, value, 'Delivery');
  }
  if (type == 'packaging_discount') {
    return _percentOrMoney(context, value, 'Packaging');
  }
  if (type == 'wallet_credit' || type == 'wallet_cashback') {
    return value > 0
        ? '${formatCurrency(context, value)} Wallet'
        : 'Wallet Cashback';
  }
  if (type == 'cashback') {
    return value > 0 ? '${_trimNumber(value)}% Cashback' : 'Cashback';
  }
  if (type == 'reward_points') {
    return value > 0 ? '${_trimNumber(value)} Points' : 'Reward Points';
  }
  if (type == 'scratch_card') return '';
  if (type == 'gift_voucher') {
    return value > 0
        ? '${formatCurrency(context, value)} Voucher'
        : 'Gift Voucher';
  }
  if (type == 'referral_bonus') {
    return value > 0
        ? '${formatCurrency(context, value)} Referral'
        : 'Referral Bonus';
  }
  if (type == 'festival_offer') {
    return value > 0 ? '${_trimNumber(value)}% Festival' : 'Festival Offer';
  }
  if (type == 'flash_sale') {
    return value > 0 ? '${_trimNumber(value)}% Flash' : 'Flash Sale';
  }
  if (type == 'bogo') return 'Buy 1 Get 1';
  if (type == 'buy_3_get_1') return 'Buy 3 Get 1';
  if (type.startsWith('buy_')) {
    final buy = (reward['buy_quantity'] ?? '').toString();
    final free = (reward['free_quantity'] ?? '').toString();
    if (buy.isNotEmpty && free.isNotEmpty) return 'Buy $buy Get $free';
    return 'Buy More Get More';
  }
  if (type.startsWith('free_')) return 'Free Item';
  if (type.contains('combo') || type.contains('meal')) {
    return value > 0 ? '${formatCurrency(context, value)} Deal' : 'Combo Deal';
  }
  if (type.contains('custom')) return 'Special Rule';
  if (type.contains('percentage')) return '${_trimNumber(value)}% OFF';
  return value > 0 ? '${formatCurrency(context, value)} OFF' : 'Live Offer';
}

String _percentOrMoney(BuildContext context, double value, String suffix) {
  if (value <= 0) return '$suffix Off';
  if (value <= 100) return '${_trimNumber(value)}% $suffix';
  return '${formatCurrency(context, value)} $suffix';
}

String _trimNumber(double value) {
  return value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(1);
}

double _homePriceValue(dynamic value) {
  if (value == null) return 0;
  if (value is num) return value.toDouble();
  if (value is String) {
    final cleaned = value.replaceAll(RegExp(r'[^0-9.\-]'), '');
    if (cleaned.isEmpty || cleaned == '-' || cleaned == '.') return 0;
    return double.tryParse(cleaned) ?? 0;
  }
  if (value is Map) {
    for (final key in const ['amount', 'value', 'price', 'final_price']) {
      final parsed = _homePriceValue(value[key]);
      if (parsed > 0) return parsed;
    }
  }
  return 0;
}
