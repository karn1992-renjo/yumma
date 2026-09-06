import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../models/menu_item.dart';
import '../../../../models/restaurant.dart';
import '../../../../providers/cart_provider.dart';
import '../../../../utils/currency_utils.dart';
import '../../../../widgets/common/app_cached_image.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import 'v2_anim.dart';
import 'v2_cards.dart';
import 'v2_cart_actions.dart';
import 'v2_glass.dart';

/// Quick-look sheet for a dish — opened on dish-card tap instead of jumping
/// straight to the restaurant. Add to cart in place, or open the full menu.
Future<void> showV2DishPopup(BuildContext context, Map<String, dynamic> raw) {
  final p = V2Palette.of(v2ModeNotifier.value);
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: Colors.transparent,
    isScrollControlled: true,
    builder: (sheetContext) => V2Theme(
      palette: p,
      child: _DishPopupBody(raw: raw),
    ),
  );
}

class _DishPopupBody extends StatelessWidget {
  const _DishPopupBody({required this.raw});

  final Map<String, dynamic> raw;

  int get _menuItemId =>
      v2Int(raw['id'] ?? raw['menu_item_id'] ?? raw['master_menu_item_id']);
  int get _restaurantId => v2Int(raw['restaurant_id'] ??
      raw['restaurantId'] ??
      (raw['restaurant'] is Map ? (raw['restaurant'] as Map)['id'] : null));
  double get _price => v2Double(raw['discounted_price'] ??
      raw['price'] ??
      raw['min_price'] ??
      raw['base_price']);
  double get _original =>
      v2Double(raw['price'] ?? raw['mrp'] ?? raw['original_price']);

  Restaurant _restaurant() {
    final r = raw['restaurant'] is Map
        ? Map<String, dynamic>.from(raw['restaurant'] as Map)
        : <String, dynamic>{};
    return Restaurant.fromJson({
      'id': _restaurantId,
      'name': raw['restaurant_name'] ??
          raw['restaurantName'] ??
          r['name'] ??
          'Restaurant',
      'delivery_fee': r['delivery_fee'] ?? 0,
      ...r,
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final name = (raw['name'] ?? raw['title'] ?? 'Dish').toString();
    final desc =
        (raw['description'] ?? raw['desc'] ?? raw['details'] ?? '').toString();
    final restaurantName =
        (raw['restaurant_name'] ?? raw['restaurantName'] ?? '').toString();
    final isVeg = v2Bool(raw['is_veg'] ?? raw['veg'], fallback: true);
    final img = v2ImageUrl(raw, kDishImageKeys);
    final hasDiscount = _original > 0 && _original > _price + 0.01;
    final canAdd = _menuItemId > 0 && _restaurantId > 0;

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: GlassPanel(
          radius: 26,
          strong: true,
          padding: EdgeInsets.zero,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (img.isNotEmpty)
                ClipRRect(
                  borderRadius:
                      const BorderRadius.vertical(top: Radius.circular(26)),
                  child: AppCachedImage(
                    imageUrl: img,
                    width: double.infinity,
                    height: 190,
                    fit: BoxFit.cover,
                  ),
                ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      children: [
                        VegDot(isVeg: isVeg),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(name,
                              style: TextStyle(
                                  color: p.ink,
                                  fontSize: 18,
                                  fontWeight: FontWeight.w900)),
                        ),
                      ],
                    ),
                    if (restaurantName.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(restaurantName,
                          style:
                              TextStyle(color: p.inkFaint, fontSize: 12.5)),
                    ],
                    const SizedBox(height: 10),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.baseline,
                      textBaseline: TextBaseline.alphabetic,
                      children: [
                        Text(
                          _price > 0
                              ? formatCurrency(context, _price)
                              : 'See menu',
                          style: TextStyle(
                              color: hasDiscount ? p.positive : p.ink,
                              fontSize: 17,
                              fontWeight: FontWeight.w900),
                        ),
                        if (hasDiscount) ...[
                          const SizedBox(width: 8),
                          Text(
                            formatCurrency(context, _original),
                            style: TextStyle(
                                color: p.inkFaint,
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                decoration: TextDecoration.lineThrough),
                          ),
                        ],
                      ],
                    ),
                    if (desc.trim().isNotEmpty) ...[
                      const SizedBox(height: 12),
                      Text(desc,
                          style: TextStyle(
                              color: p.inkSoft,
                              fontSize: 13,
                              height: 1.4)),
                    ],
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        if (canAdd)
                          Expanded(
                            child: _AddControl(
                              menuItemId: _menuItemId,
                              raw: raw,
                              restaurant: _restaurant,
                            ),
                          )
                        else
                          Expanded(
                            child: _FullMenuButton(
                              restaurantId: _restaurantId,
                              filled: true,
                            ),
                          ),
                        if (canAdd) ...[
                          const SizedBox(width: 12),
                          _FullMenuButton(
                            restaurantId: _restaurantId,
                            filled: false,
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AddControl extends StatelessWidget {
  const _AddControl({
    required this.menuItemId,
    required this.raw,
    required this.restaurant,
  });

  final int menuItemId;
  final Map<String, dynamic> raw;
  final Restaurant Function() restaurant;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final qty = context.select<CartProvider, int>(
        (c) => c.quantityFor(menuItemId));
    if (qty == 0) {
      return V2Tappable(
        onTap: () {
          try {
            v2AddToCart(context, MenuItem.fromJson(raw), restaurant());
          } catch (_) {}
        },
        child: Container(
          height: 50,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: p.accent,
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Text('Add to cart',
              style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: 14)),
        ),
      );
    }
    return Container(
      height: 50,
      decoration: BoxDecoration(
        color: p.accent,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          _StepBtn(
            icon: Icons.remove_rounded,
            onTap: () =>
                context.read<CartProvider>().decrementQuantity(menuItemId),
          ),
          Text('$qty in cart',
              style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: 14)),
          _StepBtn(
            icon: Icons.add_rounded,
            onTap: () {
              try {
                v2AddToCart(
                    context, MenuItem.fromJson(raw), restaurant());
              } catch (_) {}
            },
          ),
        ],
      ),
    );
  }
}

class _StepBtn extends StatelessWidget {
  const _StepBtn({required this.icon, required this.onTap});
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return V2Tappable(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Icon(icon, color: Colors.white, size: 22),
      ),
    );
  }
}

class _FullMenuButton extends StatelessWidget {
  const _FullMenuButton({required this.restaurantId, required this.filled});
  final int restaurantId;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: () {
        Navigator.of(context).pop();
        v2OpenRestaurant(context, restaurantId);
      },
      child: Container(
        height: 50,
        padding: EdgeInsets.symmetric(horizontal: filled ? 0 : 18),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: filled ? p.accent : p.glassTop,
          borderRadius: BorderRadius.circular(14),
          border: filled ? null : Border.all(color: p.glassBorder),
        ),
        child: Text(
          filled ? 'View full menu' : 'Menu',
          style: TextStyle(
              color: filled ? Colors.white : p.ink,
              fontWeight: FontWeight.w800,
              fontSize: 14),
        ),
      ),
    );
  }
}
