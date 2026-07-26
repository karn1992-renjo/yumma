import 'package:flutter/material.dart';
import '../common/app_cached_image.dart';

import '../../providers/cart_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

class CartItemCard extends StatelessWidget {
  final CartItem item;
  final VoidCallback onIncrement;
  final VoidCallback onDecrement;
  final VoidCallback onRemove;
  final String? promotionTag;
  final int? displayQuantity;
  final double? displayTotal;
  final int freeQuantity;
  final String? freePromotionTitle;

  const CartItemCard({
    super.key,
    required this.item,
    required this.onIncrement,
    required this.onDecrement,
    required this.onRemove,
    this.promotionTag,
    this.displayQuantity,
    this.displayTotal,
    this.freeQuantity = 0,
    this.freePromotionTitle,
  });

  @override
  Widget build(BuildContext context) {
    final promoTag = promotionTag?.trim() ?? '';
    final paidQuantity = displayQuantity ?? item.quantity;
    final total = displayTotal ?? item.totalPrice;
    final freeTitle = freePromotionTitle?.trim() ?? '';
    final primary = FoodFlowTheme.brandPrimary(context);
    final compact = MediaQuery.sizeOf(context).width < 380;
    final imageSize = compact ? 72.0 : 88.0;
    final cardPadding = compact
        ? const EdgeInsets.fromLTRB(12, 12, 12, 12)
        : const EdgeInsets.fromLTRB(14, 13, 14, 13);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: cardPadding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE9EDF3)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: SizedBox(
              width: imageSize,
              height: imageSize,
              child: item.menuItem.imageUrl.isNotEmpty
                  ? AppCachedImage(
                      imageUrl: item.menuItem.imageUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _placeholder(context),
                    )
                  : _placeholder(context),
            ),
          ),
          SizedBox(width: compact ? 10 : 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    FoodFlowTheme.vegDot(item.menuItem.isVeg, size: 14),
                    const SizedBox(width: 8),
                    Text(
                      item.menuItem.dietLabel,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  item.menuItem.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13.8,
                    height: 1.2,
                    fontWeight: FontWeight.w700,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                if (promoTag.isNotEmpty) ...[
                  const SizedBox(height: 7),
                  _CartPromotionTag(label: promoTag),
                ],
                if (freeQuantity > 0) ...[
                  const SizedBox(height: 7),
                  Row(
                    children: [
                      const Icon(
                        Icons.check_circle_rounded,
                        color: Color(0xFF2E7BE6),
                        size: 16,
                      ),
                      const SizedBox(width: 5),
                      Flexible(
                        child: Text(
                          '$freeQuantity free${freeTitle.isEmpty ? '' : ' with $freeTitle'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Color(0xFF2E7BE6),
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
                if (item.selectedVariant != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    item.selectedVariant!.name,
                    style: const TextStyle(
                      color: FoodFlowTheme.inkSoft,
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
                if (item.selectedAddOns.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    item.selectedAddOns.map((option) => option.name).join(', '),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      height: 1.35,
                    ),
                  ),
                ],
                const SizedBox(height: 10),
                Row(
                  children: [
                    Text(
                      formatCurrency(context, item.unitPrice),
                      style: const TextStyle(
                        fontSize: 13.8,
                        fontWeight: FontWeight.w700,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      formatCurrency(context, total),
                      style: const TextStyle(
                        fontSize: 14.1,
                        fontWeight: FontWeight.w800,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Container(
                      decoration: BoxDecoration(
                        color: primary.withOpacity(0.08),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: primary.withOpacity(0.25)),
                      ),
                      child: Row(
                        children: [
                          _CounterButton(
                            icon: Icons.remove,
                            onTap: onDecrement,
                          ),
                          SizedBox(
                            width: 34,
                            child: Text(
                              '$paidQuantity',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: primary,
                                fontSize: 14.1,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          _CounterButton(
                            icon: Icons.add,
                            onTap: onIncrement,
                          ),
                        ],
                      ),
                    ),
                    const Spacer(),
                    IconButton(
                      onPressed: onRemove,
                      style: IconButton.styleFrom(
                        backgroundColor: const Color(0xFFF4F5F8),
                        foregroundColor: const Color(0xFF636B78),
                      ),
                      icon: const Icon(Icons.close_rounded),
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

  Widget _placeholder(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    return Container(
      color: primary.withOpacity(0.08),
      child: Icon(
        Icons.fastfood_rounded,
        color: primary,
        size: 34,
      ),
    );
  }
}

class _CartPromotionTag extends StatelessWidget {
  final String label;

  const _CartPromotionTag({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF8F1),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFBFE7C8)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.local_offer_rounded,
            size: 13,
            color: Color(0xFF168A35),
          ),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Color(0xFF168A35),
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

class _CounterButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _CounterButton({
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        width: 40,
        height: 40,
        child: Icon(icon, color: primary, size: 18),
      ),
    );
  }
}
