import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../providers/cart_provider.dart';
import '../../../../utils/currency_utils.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class CartV2 extends StatelessWidget {
  const CartV2({super.key});

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      title: 'Your Cart',
      showBack: true,
      body: Consumer<CartProvider>(
        builder: (context, cart, _) {
          final carts = cart.carts;
          if (cart.totalCartItemCount <= 0 || carts.isEmpty) {
            return const V2EmptyState(
              icon: Icons.shopping_bag_outlined,
              title: 'Your cart is empty',
              message: 'Add dishes from a restaurant to get started.',
            );
          }
          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
                  children: [
                    for (final rc in carts)
                      V2Entrance(
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: 14),
                          child: GlassPanel(
                            radius: 22,
                            strong: true,
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  rc.restaurant.name,
                                  style: TextStyle(
                                    color: p.ink,
                                    fontSize: 15,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 10),
                                for (final item in rc.items)
                                  Padding(
                                    padding: const EdgeInsets.only(bottom: 10),
                                    child: Row(
                                      children: [
                                        VegDot(
                                          isVeg: item.menuItem.isVeg,
                                          size: 12,
                                        ),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: Text(
                                            item.menuItem.name,
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: TextStyle(
                                              color: p.ink,
                                              fontSize: 13,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ),
                                        _Stepper(
                                          quantity: item.quantity,
                                          onAdd: () => cart.incrementBySignature(
                                              item.signature),
                                          onRemove: () =>
                                              cart.decrementBySignature(
                                                  item.signature),
                                        ),
                                        const SizedBox(width: 10),
                                        Text(
                                          formatCurrency(
                                              context, item.displayTotalPrice),
                                          style: TextStyle(
                                            color: p.inkSoft,
                                            fontSize: 12.5,
                                            fontWeight: FontWeight.w800,
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
                  ],
                ),
              ),
              _CheckoutBar(
                total: cart.displayTotal,
                onCheckout: () => v2OpenCheckout(context),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _Stepper extends StatelessWidget {
  const _Stepper({
    required this.quantity,
    required this.onAdd,
    required this.onRemove,
  });

  final int quantity;
  final VoidCallback onAdd;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      decoration: BoxDecoration(
        color: p.accent.withOpacity(p.isDark ? 0.18 : 0.10),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: p.accent.withOpacity(0.45)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _btn(context, Icons.remove_rounded, onRemove),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Text(
              '$quantity',
              style: TextStyle(
                color: p.ink,
                fontWeight: FontWeight.w800,
                fontSize: 13,
              ),
            ),
          ),
          _btn(context, Icons.add_rounded, onAdd),
        ],
      ),
    );
  }

  Widget _btn(BuildContext context, IconData icon, VoidCallback onTap) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(6),
        child: Icon(icon, size: 16, color: p.accent),
      ),
    );
  }
}

class _CheckoutBar extends StatelessWidget {
  const _CheckoutBar({required this.total, required this.onCheckout});

  final double total;
  final VoidCallback onCheckout;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      padding: EdgeInsets.fromLTRB(
          16, 12, 16, MediaQuery.of(context).padding.bottom + 14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [p.glassStrongTop, p.glassStrongBottom],
        ),
        border: Border(top: BorderSide(color: p.glassBorder)),
      ),
      child: Row(
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Total',
                  style: TextStyle(color: p.inkFaint, fontSize: 11)),
              Text(
                formatCurrency(context, total),
                style: TextStyle(
                  color: p.ink,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const Spacer(),
          V2Tappable(
            onTap: onCheckout,
            child: Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 26, vertical: 14),
              decoration: BoxDecoration(
                color: p.accent,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: p.accent.withOpacity(0.4),
                    blurRadius: 16,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: const Row(
                children: [
                  Text('Checkout',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        fontSize: 14,
                      )),
                  SizedBox(width: 6),
                  Icon(Icons.arrow_forward_rounded,
                      color: Colors.white, size: 18),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
