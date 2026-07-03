// lib/widgets/customer/cart_item_card.dart
import 'package:flutter/material.dart';
import '../../providers/cart_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

class CartItemCard extends StatelessWidget {
  final CartItem item;
  final VoidCallback onIncrement;
  final VoidCallback onDecrement;
  final VoidCallback onRemove;

  const CartItemCard({
    Key? key,
    required this.item,
    required this.onIncrement,
    required this.onDecrement,
    required this.onRemove,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: FoodFlowTheme.softSurface(radius: 14),
      child: Row(
        children: [
          Container(
            width: 104,
            height: 112,
            decoration: BoxDecoration(
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(14),
                bottomLeft: Radius.circular(14),
              ),
              color: Colors.grey.shade100,
            ),
            child: ClipRRect(
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(14),
                bottomLeft: Radius.circular(14),
              ),
              child: item.menuItem.imageUrl.isNotEmpty
                  ? Image.network(
                      item.menuItem.imageUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _buildPlaceholder(),
                    )
                  : _buildPlaceholder(),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  FoodFlowTheme.vegDot(item.menuItem.isVeg, size: 14),
                  const SizedBox(height: 8),
                  Text(
                    item.menuItem.name,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                      color: FoodFlowTheme.ink,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Text(
                        formatCurrency(context, item.menuItem.finalPrice),
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w900,
                          color: FoodFlowTheme.orange,
                        ),
                      ),
                      if (item.menuItem.hasDiscount) ...[
                        const SizedBox(width: 6),
                        Text(
                          formatCurrency(context, item.menuItem.price),
                          style: TextStyle(
                            fontSize: 12,
                            decoration: TextDecoration.lineThrough,
                            color: Colors.grey.shade500,
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Container(
                        decoration: BoxDecoration(
                          border: Border.all(color: Colors.grey.shade300),
                          borderRadius: BorderRadius.circular(25),
                        ),
                        child: Row(
                          children: [
                            GestureDetector(
                              onTap: onDecrement,
                              child: Container(
                                padding: const EdgeInsets.all(8),
                                decoration: const BoxDecoration(
                                  borderRadius: BorderRadius.only(
                                    topLeft: Radius.circular(25),
                                    bottomLeft: Radius.circular(25),
                                  ),
                                ),
                                child: const Icon(
                                  Icons.remove,
                                  size: 16,
                                  color: Color(0xFF0E9F6E),
                                ),
                              ),
                            ),
                            Container(
                              width: 34,
                              child: Text(
                                item.quantity.toString(),
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                            GestureDetector(
                              onTap: onIncrement,
                              child: Container(
                                padding: const EdgeInsets.all(8),
                                decoration: const BoxDecoration(
                                  borderRadius: BorderRadius.only(
                                    topRight: Radius.circular(25),
                                    bottomRight: Radius.circular(25),
                                  ),
                                ),
                                child: const Icon(
                                  Icons.add,
                                  size: 16,
                                  color: Color(0xFF0E9F6E),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      GestureDetector(
                        onTap: onRemove,
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: Colors.red.shade50,
                            borderRadius: BorderRadius.circular(25),
                          ),
                          child: Icon(
                            Icons.delete_outline,
                            size: 18,
                            color: Colors.red.shade400,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPlaceholder() {
    return Container(
      color: Colors.grey.shade100,
      child: Icon(
        Icons.fastfood,
        size: 32,
        color: Colors.grey.shade400,
      ),
    );
  }
}
