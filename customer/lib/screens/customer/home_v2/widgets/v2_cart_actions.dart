import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../models/menu_item.dart';
import '../../../../models/restaurant.dart';
import '../../../../providers/cart_provider.dart';
import '../theme/v2_theme.dart';
import 'v2_anim.dart';

/// Adds [item] to the cart, enforcing a single-restaurant cart: if the cart
/// already holds items from a different restaurant, a confirm dialog offers to
/// clear it first.
Future<void> v2AddToCart(
  BuildContext context,
  MenuItem item,
  Restaurant restaurant,
) async {
  final cart = context.read<CartProvider>();
  final carts = cart.carts;
  final otherRestaurant = carts
      .where((c) => c.restaurant.id != restaurant.id && c.items.isNotEmpty)
      .toList();

  if (cart.totalCartItemCount > 0 && otherRestaurant.isNotEmpty) {
    final currentName = otherRestaurant.first.restaurant.name;
    final ok = await showGeneralDialog<bool>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'replace cart',
      barrierColor: Colors.black.withOpacity(0.45),
      transitionDuration: const Duration(milliseconds: 190),
      pageBuilder: (_, __, ___) => _ReplaceCartDialog(currentName: currentName),
      transitionBuilder: (_, a, __, child) => FadeTransition(
        opacity: a,
        child: ScaleTransition(
          scale: Tween(begin: 0.92, end: 1.0)
              .animate(CurvedAnimation(parent: a, curve: Curves.easeOutBack)),
          child: child,
        ),
      ),
    );
    if (ok != true) return;
    cart.clearAllCarts();
  }

  cart.addItem(item, restaurant);
}

class _ReplaceCartDialog extends StatelessWidget {
  const _ReplaceCartDialog({required this.currentName});

  final String currentName;

  @override
  Widget build(BuildContext context) {
    final p = V2Palette.of(v2ModeNotifier.value);
    return V2Theme(
      palette: p,
      child: Center(
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 36),
          padding: const EdgeInsets.fromLTRB(22, 22, 22, 16),
          decoration: BoxDecoration(
            color: p.isDark ? const Color(0xFF161C2C) : Colors.white,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: p.glassBorder),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.28),
                blurRadius: 32,
                offset: const Offset(0, 16),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Start a new cart?',
                style: TextStyle(
                  color: p.ink,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Your cart has items from $currentName. Adding this item will '
                'clear that cart.',
                style: TextStyle(color: p.inkSoft, fontSize: 13, height: 1.4),
              ),
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  V2Tappable(
                    onTap: () => Navigator.of(context).pop(false),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 10),
                      child: Text(
                        'Keep cart',
                        style: TextStyle(
                          color: p.inkSoft,
                          fontWeight: FontWeight.w800,
                          fontSize: 13.5,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  V2Tappable(
                    onTap: () => Navigator.of(context).pop(true),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 18, vertical: 11),
                      decoration: BoxDecoration(
                        color: p.accent,
                        borderRadius: BorderRadius.circular(13),
                      ),
                      child: const Text(
                        'Clear & add',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 13.5,
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
