import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../providers/cart_provider.dart';
import '../../../../utils/currency_utils.dart';
import '../theme/v2_theme.dart';
import 'v2_anim.dart';

/// Glass floating cart bar. Hides itself when the cart is empty.
/// [V2CartBar] positions itself; [V2CartBarBody] is the same pill without the
/// Positioned wrapper, for stacking inside a Column with other floating bars.
class V2CartBar extends StatelessWidget {
  const V2CartBar({super.key, required this.onTap, this.bottomOffset = 96});

  final VoidCallback onTap;
  final double bottomOffset;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).padding.bottom;
    return Positioned(
      left: 0,
      right: 0,
      bottom: bottomInset + bottomOffset,
      child: V2CartBarBody(onTap: onTap),
    );
  }
}

class V2CartBarBody extends StatelessWidget {
  const V2CartBarBody({super.key, required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final count = cart.totalCartItemCount;
    if (count <= 0) return const SizedBox.shrink();

    final p = V2Theme.of(context);
    final width = math.min(MediaQuery.sizeOf(context).width - 32, 420.0);
    final name = cart.restaurant?.name.trim() ?? '';
    final label = name.isNotEmpty && name != 'Restaurant' ? name : 'Your cart';

    return Center(
        child: V2Tappable(
          onTap: onTap,
          child: Container(
            width: width,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(20),
              gradient: LinearGradient(
                colors: [p.accent, Color.lerp(p.accent, Colors.black, 0.18)!],
              ),
              boxShadow: [
                BoxShadow(
                  color: p.accent.withOpacity(0.4),
                  blurRadius: 20,
                  offset: const Offset(0, 10),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.2),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    '$count',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 13.5,
                        ),
                      ),
                      Text(
                        formatCurrency(context, cart.displaySubtotal),
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.9),
                          fontWeight: FontWeight.w600,
                          fontSize: 11.5,
                        ),
                      ),
                    ],
                  ),
                ),
                const Text(
                  'View cart',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(width: 2),
                const Icon(Icons.arrow_forward_rounded,
                    color: Colors.white, size: 18),
              ],
            ),
          ),
        ),
    );
  }
}
