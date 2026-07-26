import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/cart_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../common/app_cached_image.dart';

class CustomerFloatingCartBar extends StatelessWidget {
  const CustomerFloatingCartBar({
    super.key,
    this.bottomPadding = 8,
    this.onTap,
  });

  final double bottomPadding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    if (cart.totalCartItemCount <= 0) {
      return const SizedBox.shrink();
    }

    final carts = cart.carts;
    final hasMultipleCarts = cart.cartCount > 1;
    final restaurant = cart.restaurant ??
        (carts.length == 1 ? carts.first.restaurant : null) ??
        (carts.isNotEmpty ? carts.first.restaurant : null);
    final restaurantName = restaurant?.name.trim() ?? '';
    final label = restaurantName.isNotEmpty && restaurantName != 'Restaurant'
        ? restaurantName
        : 'Current order';
    final itemCount = cart.itemCount > 0
        ? cart.itemCount
        : (carts.isNotEmpty ? carts.first.itemCount : cart.totalCartItemCount);
    final width = math.min(MediaQuery.sizeOf(context).width - 36, 360.0);
    final bottomInset = MediaQuery.paddingOf(context).bottom;
    final secondary = FoodFlowTheme.brandSecondary(context);
    void openCart() {
      if (hasMultipleCarts && onTap == null) {
        _showAllCartsSheet(context);
        return;
      }
      if (onTap != null) {
        onTap!();
        return;
      }
      Navigator.pushNamed(context, '/cart');
    }

    void openMenu() {
      if (hasMultipleCarts) {
        openCart();
        return;
      }
      final restaurantId = restaurant?.id ?? 0;
      if (restaurantId <= 0) {
        openCart();
        return;
      }
      Navigator.pushNamed(context, '/restaurant/detail',
          arguments: restaurantId);
    }

    return SizedBox(
      height: 78 + bottomPadding + bottomInset,
      child: Padding(
        padding: EdgeInsets.fromLTRB(18, 5, 18, bottomPadding + bottomInset),
        child: Center(
          child: Stack(
            clipBehavior: Clip.none,
            alignment: Alignment.topCenter,
            children: [
              Material(
                color: Colors.transparent,
                child: Container(
                  width: width,
                  padding: const EdgeInsets.fromLTRB(8, 8, 8, 8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: Colors.white.withOpacity(0.9)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.13),
                        blurRadius: 22,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 42,
                        height: 42,
                        decoration: const BoxDecoration(shape: BoxShape.circle),
                        clipBehavior: Clip.antiAlias,
                        child: restaurant?.logoUrl.isNotEmpty == true
                            ? AppCachedImage(
                                imageUrl: restaurant!.logoUrl,
                                fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) =>
                                    const _CartFallbackIcon(),
                              )
                            : const _CartFallbackIcon(),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: InkWell(
                          onTap: openMenu,
                          borderRadius: BorderRadius.circular(18),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                label,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: FoodFlowTheme.ink,
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 1),
                              Text(
                                'View Menu',
                                style: TextStyle(
                                  color: secondary,
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      InkWell(
                        onTap: openCart,
                        borderRadius: BorderRadius.circular(999),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 9,
                          ),
                          decoration: BoxDecoration(
                            color: secondary,
                            borderRadius: BorderRadius.circular(999),
                            boxShadow: [
                              BoxShadow(
                                color: secondary.withOpacity(0.22),
                                blurRadius: 12,
                                offset: const Offset(0, 5),
                              ),
                            ],
                          ),
                          child: Text(
                            'View Cart\n$itemCount item${itemCount == 1 ? '' : 's'}',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              height: 1.05,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      InkWell(
                        onTap: () => context.read<CartProvider>().clearCart(),
                        borderRadius: BorderRadius.circular(999),
                        child: Container(
                          width: 30,
                          height: 30,
                          decoration: const BoxDecoration(
                            color: Color(0xFFF1F2F5),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.close_rounded,
                            color: FoodFlowTheme.muted,
                            size: 18,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              if (hasMultipleCarts)
                Positioned(
                  top: -18,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(999),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.08),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: InkWell(
                      onTap: openCart,
                      borderRadius: BorderRadius.circular(999),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            'All carts',
                            style: TextStyle(
                              color: secondary,
                              fontSize: 12,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(width: 4),
                          Icon(
                            Icons.keyboard_arrow_up_rounded,
                            color: secondary,
                            size: 16,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  void _showAllCartsSheet(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: const Color(0xFFF5F6FB),
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) {
        return Consumer<CartProvider>(
          builder: (context, cart, _) {
            final carts = cart.carts;
            final secondary = FoodFlowTheme.brandSecondary(context);

            return SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: const BoxDecoration(
                        color: Color(0xFF1F2937),
                        shape: BoxShape.circle,
                      ),
                      child: IconButton(
                        onPressed: () => Navigator.pop(sheetContext),
                        icon: const Icon(Icons.close, color: Colors.white),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Your Carts (${carts.length})',
                            style: const TextStyle(
                              color: FoodFlowTheme.ink,
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        TextButton(
                          onPressed: () {
                            cart.clearAllCarts();
                            Navigator.pop(sheetContext);
                          },
                          child: const Text('Clear all'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    for (final entry in carts) ...[
                      _FloatingCartSheetRow(
                        cart: entry,
                        secondary: secondary,
                        onViewMenu: () {
                          Navigator.pop(sheetContext);
                          Navigator.pushNamed(
                            context,
                            '/restaurant/detail',
                            arguments: entry.restaurant.id,
                          );
                        },
                        onViewCart: () {
                          cart.setActiveCart(entry.restaurant.id);
                          Navigator.pop(sheetContext);
                          Navigator.pushNamed(context, '/cart');
                        },
                        onRemove: () => cart.removeCart(entry.restaurant.id),
                      ),
                      const SizedBox(height: 12),
                    ],
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _CartFallbackIcon extends StatelessWidget {
  const _CartFallbackIcon();

  @override
  Widget build(BuildContext context) {
    return const Icon(
      Icons.restaurant_rounded,
      color: FoodFlowTheme.muted,
      size: 20,
    );
  }
}

class _FloatingCartSheetRow extends StatelessWidget {
  const _FloatingCartSheetRow({
    required this.cart,
    required this.secondary,
    required this.onViewMenu,
    required this.onViewCart,
    required this.onRemove,
  });

  final RestaurantCart cart;
  final Color secondary;
  final VoidCallback onViewMenu;
  final VoidCallback onViewCart;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 14,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: const BoxDecoration(shape: BoxShape.circle),
            clipBehavior: Clip.antiAlias,
            child: cart.restaurant.logoUrl.isNotEmpty
                ? AppCachedImage(
                    imageUrl: cart.restaurant.logoUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const _CartFallbackIcon(),
                  )
                : const _CartFallbackIcon(),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: InkWell(
              onTap: onViewMenu,
              borderRadius: BorderRadius.circular(16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    cart.restaurant.name.isEmpty
                        ? 'Restaurant'
                        : cart.restaurant.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'View Menu',
                    style: TextStyle(
                      color: secondary,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ),
          FilledButton(
            onPressed: onViewCart,
            style: FilledButton.styleFrom(
              backgroundColor: secondary,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(999),
              ),
            ),
            child: Text(
              'View Cart\n${cart.itemCount} item${cart.itemCount == 1 ? '' : 's'}',
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
            ),
          ),
          IconButton(
            onPressed: onRemove,
            icon: const Icon(Icons.close),
            color: FoodFlowTheme.muted,
          ),
        ],
      ),
    );
  }
}
