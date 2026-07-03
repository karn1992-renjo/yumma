// lib/widgets/customer/menu_item_card.dart
import 'package:flutter/material.dart';
import '../../utils/currency_utils.dart';
import '../../models/menu_item.dart';
import '../../theme/foodflow_theme.dart';
import '../common/network_image_loader.dart';

class MenuItemCard extends StatefulWidget {
  final MenuItem item;
  final VoidCallback onAddToCart;

  const MenuItemCard({
    Key? key,
    required this.item,
    required this.onAddToCart,
  }) : super(key: key);

  @override
  State<MenuItemCard> createState() => _MenuItemCardState();
}

class _MenuItemCardState extends State<MenuItemCard> {
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onLongPress: widget.item.isAvailable ? widget.onAddToCart : null,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 14),
        decoration: FoodFlowTheme.softSurface(radius: 14),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    FoodFlowTheme.vegDot(widget.item.isVeg, size: 15),
                    const SizedBox(height: 8),
                    Text(
                      widget.item.name,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                        color: FoodFlowTheme.ink,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Padding(
                      padding: const EdgeInsets.only(top: 6, bottom: 6),
                      child: Row(
                        children: [
                          Text(
                            formatCurrency(context, widget.item.finalPrice),
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w900,
                              color: FoodFlowTheme.ink,
                            ),
                          ),
                          if (widget.item.hasDiscount) ...[
                            const SizedBox(width: 6),
                            Text(
                              formatCurrency(context, widget.item.price),
                              style: TextStyle(
                                fontSize: 12,
                                color: FoodFlowTheme.faint,
                                decoration: TextDecoration.lineThrough,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: Colors.red.shade50,
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                '${widget.item.discountPercent.toStringAsFixed(0)}% off',
                                style: TextStyle(
                                  fontSize: 11,
                                  color: Colors.red.shade700,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),

                    // Description (optional)
                    if (widget.item.description != null &&
                        widget.item.description!.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Text(
                          widget.item.description!,
                          style: const TextStyle(
                            fontSize: 12,
                            color: FoodFlowTheme.muted,
                            height: 1.35,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    Row(
                      children: [
                        if (widget.item.rating != null) ...[
                          FoodFlowTheme.ratingBadge(widget.item.rating!,
                              compact: true),
                          const SizedBox(width: 6),
                          Text(
                            '(${widget.item.totalOrders})',
                            style: const TextStyle(
                                fontSize: 11, color: FoodFlowTheme.muted),
                          ),
                        ],
                        if (!widget.item.isAvailable)
                          Container(
                            margin: const EdgeInsets.only(left: 8),
                            padding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: Colors.red.shade50,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              'Not available',
                              style: TextStyle(
                                fontSize: 10,
                                color: Colors.red.shade700,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 14),
              Stack(
                clipBehavior: Clip.none,
                alignment: Alignment.bottomCenter,
                children: [
                  NetworkImageLoader(
                    imageUrl: widget.item.imageUrl,
                    width: 112,
                    height: 104,
                    fit: BoxFit.cover,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  if (widget.item.isAvailable)
                    Positioned(
                      bottom: -14,
                      child: Material(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(8),
                        elevation: 3,
                        shadowColor: Colors.black.withOpacity(0.15),
                        child: InkWell(
                          onTap: widget.onAddToCart,
                          borderRadius: BorderRadius.circular(8),
                          child: Container(
                            width: 86,
                            height: 34,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              border: Border.all(color: FoodFlowTheme.orange),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              'ADD',
                              style: TextStyle(
                                color: FoodFlowTheme.orange,
                                fontSize: 13,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
