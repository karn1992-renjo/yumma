import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import 'coupon_ticket_card.dart';

/// Bottom sheet shown for a promotion that has **no shoppable items** — i.e.
/// a banner-only offer (free delivery, cashback, flat cart discount, a coupon
/// with no product mapping, …). Tapping such an offer used to push an empty
/// "no menu items" product screen; now it opens this instead.
Future<void> showPromotionDetailSheet(
  BuildContext context,
  Map<String, dynamic> offer, {
  VoidCallback? onPrimaryAction,
  String? primaryActionLabel,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (sheetContext) => _PromotionDetailSheet(
      offer: offer,
      onPrimaryAction: onPrimaryAction,
      primaryActionLabel: primaryActionLabel,
    ),
  );
}

class _PromotionDetailSheet extends StatelessWidget {
  const _PromotionDetailSheet({
    required this.offer,
    this.onPrimaryAction,
    this.primaryActionLabel,
  });

  final Map<String, dynamic> offer;
  final VoidCallback? onPrimaryAction;
  final String? primaryActionLabel;

  String get _title {
    final v = (offer['title'] ?? offer['name'] ?? '').toString().trim();
    return v.isEmpty || v == 'null' ? 'Special offer' : v;
  }

  String get _description {
    final v = (offer['description'] ??
            offer['subtitle'] ??
            offer['terms'] ??
            offer['how_it_works'] ??
            '')
        .toString()
        .trim();
    return v == 'null' ? '' : v;
  }

  String get _code {
    final v = (offer['coupon_code'] ?? offer['code'] ?? '').toString().trim();
    return v.isEmpty || v == 'null' ? '' : v.toUpperCase();
  }

  String get _validUntil {
    final raw = offer['valid_to'] ??
        offer['valid_till'] ??
        offer['end_date'] ??
        offer['expires_at'] ??
        offer['ends_at'];
    final parsed = DateTime.tryParse(raw?.toString() ?? '');
    if (parsed == null) return '';
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    return '${parsed.day} ${months[parsed.month - 1]} ${parsed.year}';
  }

  @override
  Widget build(BuildContext context) {
    final accent = FoodFlowTheme.brandPrimary(context);
    final valueText = couponValueText(offer, currencySymbol: getCurrencySymbol(context))
        .replaceAll('\n', ' ');
    final bottomInset = MediaQuery.viewInsetsOf(context).bottom;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: Container(
        margin: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(26),
        ),
        clipBehavior: Clip.antiAlias,
        child: SafeArea(
          top: false,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // header band
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [accent, Color.lerp(accent, Colors.black, 0.22)!],
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.local_offer_rounded,
                              color: Colors.white, size: 18),
                          const SizedBox(width: 7),
                          Expanded(
                            child: Text(
                              valueText,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 12.5,
                                letterSpacing: 1,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          InkWell(
                            onTap: () => Navigator.pop(context),
                            child: const Icon(Icons.close_rounded,
                                color: Colors.white70, size: 22),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Text(
                        _title,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 22,
                          height: 1.12,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (_code.isNotEmpty) ...[
                        _CodeRow(code: _code, accent: accent),
                        const SizedBox(height: 14),
                      ],
                      if (_description.isNotEmpty) ...[
                        Text(
                          _description,
                          style: const TextStyle(
                            color: FoodFlowTheme.inkSoft,
                            fontSize: 13.5,
                            height: 1.45,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 14),
                      ],
                      _InfoLine(
                        icon: Icons.check_circle_rounded,
                        text: _code.isEmpty
                            ? 'Applied automatically at checkout when your cart qualifies.'
                            : 'Enter this code at checkout to apply the offer.',
                      ),
                      if (_validUntil.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        _InfoLine(
                          icon: Icons.schedule_rounded,
                          text: 'Valid until $_validUntil',
                        ),
                      ],
                      const SizedBox(height: 8),
                      const _InfoLine(
                        icon: Icons.info_rounded,
                        text: 'One offer applies per order. See full terms at checkout.',
                      ),
                      const SizedBox(height: 18),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton(
                          onPressed: () {
                            Navigator.pop(context);
                            onPrimaryAction?.call();
                          },
                          style: FilledButton.styleFrom(
                            backgroundColor: accent,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(15),
                            ),
                          ),
                          child: Text(
                            primaryActionLabel ??
                                (onPrimaryAction != null
                                    ? 'Browse menu'
                                    : 'Got it'),
                            style: const TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 15,
                            ),
                          ),
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
  }
}

class _CodeRow extends StatelessWidget {
  const _CodeRow({required this.code, required this.accent});

  final String code;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 10, 8, 10),
      decoration: BoxDecoration(
        color: accent.withOpacity(0.06),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(
          color: accent.withOpacity(0.35),
          style: BorderStyle.solid,
        ),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              code,
              style: TextStyle(
                color: accent,
                fontSize: 17,
                letterSpacing: 2,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          TextButton.icon(
            onPressed: () {
              Clipboard.setData(ClipboardData(text: code));
              ScaffoldMessenger.of(context).hideCurrentSnackBar();
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Code copied'),
                  duration: Duration(seconds: 1),
                ),
              );
            },
            icon: const Icon(Icons.copy_rounded, size: 16),
            label: const Text('Copy'),
            style: TextButton.styleFrom(foregroundColor: accent),
          ),
        ],
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 15, color: FoodFlowTheme.muted),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              height: 1.35,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}
