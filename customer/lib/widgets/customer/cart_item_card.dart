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

  const CartItemCard({
    super.key,
    required this.item,
    required this.onIncrement,
    required this.onDecrement,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: SizedBox(
              width: 88,
              height: 88,
              child: item.menuItem.imageUrl.isNotEmpty
                  ? AppCachedImage(
                      imageUrl: item.menuItem.imageUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _placeholder(),
                    )
                  : _placeholder(),
            ),
          ),
          const SizedBox(width: 14),
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
                const SizedBox(height: 8),
                Text(
                  item.menuItem.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 16,
                    height: 1.2,
                    fontWeight: FontWeight.w700,
                    color: FoodFlowTheme.ink,
                  ),
                ),
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
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      formatCurrency(context, item.totalPrice),
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Container(
                      decoration: BoxDecoration(
                        color: FoodFlowTheme.orange.withOpacity(0.08),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: FoodFlowTheme.orange.withOpacity(0.25)),
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
                              '${item.quantity}',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: FoodFlowTheme.orange,
                                fontSize: 16,
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

  Widget _placeholder() {
    return Container(
      color: const Color(0xFFF8EFE7),
      child: const Icon(
        Icons.fastfood_rounded,
        color: FoodFlowTheme.orange,
        size: 34,
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
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        width: 40,
        height: 40,
        child: Icon(icon, color: FoodFlowTheme.orange, size: 18),
      ),
    );
  }
}
