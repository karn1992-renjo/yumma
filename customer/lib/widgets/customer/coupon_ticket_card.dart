import 'package:flutter/material.dart';

/// Horizontal "DISCOUNT COUPON" ticket card — white body with a coloured
/// ribbon on the right that carries the headline value ("50%", "₹60 OFF",
/// "FREE DELIVERY"). Used on the Offers screen and in the checkout coupon
/// sheet so every coupon reads the same way.
class CouponTicketCard extends StatelessWidget {
  const CouponTicketCard({
    super.key,
    required this.valueText,
    required this.title,
    this.code,
    this.subtitle,
    this.validUntilText,
    this.accent = const Color(0xFF9A2FF2),
    this.enabled = true,
    this.selected = false,
    this.onTap,
    this.trailing,
    this.margin = EdgeInsets.zero,
    this.currencySymbol = '',
  });

  final String valueText;
  final String title;
  final String? code;
  final String? subtitle;
  final String? validUntilText;
  final Color accent;
  final bool enabled;
  final bool selected;
  final VoidCallback? onTap;
  final Widget? trailing;
  final EdgeInsetsGeometry margin;
  final String currencySymbol;

  static const Color _ink = Color(0xFF14181F);
  static const Color _muted = Color(0xFF8A94A6);

  @override
  Widget build(BuildContext context) {
    final effAccent = enabled ? accent : const Color(0xFFB6BCC6);
    final body = Opacity(
      opacity: enabled ? 1 : 0.7,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? effAccent : const Color(0xFFE7EBF2),
            width: selected ? 1.6 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 14, 12, 14),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 16,
                              height: 16,
                              decoration: BoxDecoration(
                                color: effAccent.withOpacity(0.15),
                                borderRadius: BorderRadius.circular(5),
                              ),
                              child: Icon(Icons.local_activity_rounded,
                                  size: 11, color: effAccent),
                            ),
                            const SizedBox(width: 6),
                            Text(
                              'DISCOUNT',
                              style: TextStyle(
                                color: _muted,
                                fontSize: 9.5,
                                letterSpacing: 1.6,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 3),
                        Text(
                          title.isEmpty ? 'COUPON' : title.toUpperCase(),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: effAccent,
                            fontSize: 17,
                            height: 1.05,
                            letterSpacing: 0.2,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                          const SizedBox(height: 5),
                          Text(
                            subtitle!,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: _muted,
                              fontSize: 11,
                              height: 1.25,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            if (code != null && code!.trim().isNotEmpty) ...[
                              _DashedChip(text: code!.toUpperCase()),
                              const SizedBox(width: 8),
                            ],
                            if (validUntilText != null &&
                                validUntilText!.trim().isNotEmpty)
                              Flexible(
                                child: Text(
                                  'VALID UNTIL: $validUntilText',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: _muted,
                                    fontSize: 9,
                                    letterSpacing: 0.6,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                _CouponBanner(
                  text: valueText,
                  color: effAccent,
                  selected: selected && enabled,
                ),
              ],
            ),
          ),
        ),
      ),
    );

    final card = Padding(padding: margin, child: body);
    if (onTap == null || !enabled) return card;
    return Padding(
      padding: margin,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: body,
        ),
      ),
    );
  }
}

class _CouponBanner extends StatelessWidget {
  const _CouponBanner({
    required this.text,
    required this.color,
    required this.selected,
  });

  final String text;
  final Color color;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final lines =
        text.split('\n').map((s) => s.trim()).where((s) => s.isNotEmpty).toList();
    final head = lines.isEmpty ? text : lines.first;
    final rest = lines.length > 1 ? lines.sublist(1) : const <String>[];

    return ClipPath(
      clipper: _CouponBannerClipper(),
      child: Container(
        width: 108,
        constraints: const BoxConstraints(minHeight: 96),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              color,
              Color.lerp(color, Colors.black, 0.14)!,
            ],
          ),
        ),
        // Extra bottom room so nothing sits on the V-notch.
        padding: const EdgeInsets.fromLTRB(6, 0, 6, 22),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  head,
                  maxLines: 1,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 27,
                    height: 1.0,
                    letterSpacing: -0.5,
                    fontWeight: FontWeight.w900,
                    shadows: selected
                        ? const [Shadow(color: Colors.black26, blurRadius: 4)]
                        : null,
                  ),
                ),
              ),
              for (final line in rest) ...[
                const SizedBox(height: 1),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    line,
                    maxLines: 1,
                    textAlign: TextAlign.center,
                    softWrap: false,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 13,
                      height: 1.0,
                      letterSpacing: 1.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Rectangle with a downward V notch cut out of the bottom edge — the
/// "bookmark ribbon" shape from the reference.
class _CouponBannerClipper extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    const notch = 15.0;
    return Path()
      ..lineTo(size.width, 0)
      ..lineTo(size.width, size.height)
      ..lineTo(size.width / 2, size.height - notch)
      ..lineTo(0, size.height)
      ..close();
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}

class _DashedChip extends StatelessWidget {
  const _DashedChip({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: const Color(0xFFF4F6FA),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: const Color(0xFFD8DEE8)),
      ),
      child: Text(
        text,
        style: const TextStyle(
          color: Color(0xFF3B4351),
          fontSize: 10.5,
          letterSpacing: 0.5,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

/// Best-effort "value" headline from a promotion payload.
String couponValueText(
  Map<String, dynamic> promo, {
  String currencySymbol = '',
}) {
  String norm(dynamic v) =>
      (v ?? '').toString().trim().toLowerCase().replaceAll('-', '_');
  final rewards = promo['rewards'] is Map
      ? Map<String, dynamic>.from(promo['rewards'] as Map)
      : const <String, dynamic>{};
  // `promotion_type` is often a category name (festival_offer, flash_sale) --
  // the reliable "is this a % or a flat ₹" signal is the reward/discount type.
  final valueType = norm(rewards['type'] ??
      promo['reward_type'] ??
      promo['discount_type'] ??
      promo['promotion_type'] ??
      promo['type']);
  final anyType =
      '$valueType ${norm(promo['promotion_type'] ?? promo['type'])}';
  final rawValue = promo['discount_value'] ??
      promo['reward_value'] ??
      rewards['value'] ??
      promo['value'];
  final value = rawValue is num
      ? rawValue.toDouble()
      : double.tryParse('${rawValue ?? ''}') ?? 0;

  if (anyType.contains('free_delivery')) return 'FREE\nDELIVERY';
  if (anyType.contains('free_item') || anyType.contains('free_product')) {
    return 'FREE\nITEM';
  }
  if (anyType.contains('bogo') || anyType.contains('buy_1_get_1')) {
    return 'BUY 1\nGET 1';
  }
  if (anyType.contains('cashback') || anyType.contains('wallet')) {
    return value > 0
        ? '$currencySymbol${_fmt(value)}\nCASHBACK'
        : 'CASH\nBACK';
  }
  if (anyType.contains('reward_point')) return 'BONUS\nPOINTS';
  if (value <= 0) return 'OFFER';
  if (valueType.contains('percent') || anyType.contains('percent')) {
    return '${_fmt(value)}%\nOFF';
  }
  return '$currencySymbol${_fmt(value)}\nOFF';
}

String _fmt(double v) =>
    v == v.roundToDouble() ? v.toStringAsFixed(0) : v.toStringAsFixed(1);
