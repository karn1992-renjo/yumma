import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../models/menu_item.dart';
import '../../models/restaurant.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../utils/promotion_summary_utils.dart';
import '../../widgets/common/app_cached_image.dart';

class CartScreen extends StatefulWidget {
  const CartScreen({
    super.key,
    this.onBrowseRestaurants,
    this.onAddMore,
  });

  final VoidCallback? onBrowseRestaurants;
  final VoidCallback? onAddMore;

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  final ApiService _api = ApiService();
  final List<Map<String, dynamic>> _rewardLines = <Map<String, dynamic>>[];
  String? _lastCartSignature;
  String? _rewardCartSignature;
  String? _inFlightRewardSignature;
  int _rewardRequestId = 0;
  Timer? _rewardDebounce;
  double? _summaryDiscount;
  double? _summaryEmbeddedItemDiscount;
  double? _summaryTotal;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refreshCartRewards());
  }

  @override
  void dispose() {
    _rewardDebounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final primary = FoodFlowTheme.brandPrimary(context);
    _scheduleRewardRefreshIfNeeded(cart);
    final cartSignature = _cartSignature(cart);
    final hasFreshSummary = _rewardCartSignature == cartSignature;
    final localDiscount = cart.promotionDisplayDiscount;
    final localTotal = cart.displayTotal;
    final double checkoutTotal =
        hasFreshSummary ? (_summaryTotal ?? localTotal) : localTotal;
    final double checkoutDiscount = hasFreshSummary
        ? ((_summaryDiscount ?? 0) + (_summaryEmbeddedItemDiscount ?? 0))
        : localDiscount;

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      body: SafeArea(
        bottom: false,
        child: cart.totalCartItemCount == 0
            ? _EmptyCartView(
                onBrowse: widget.onBrowseRestaurants ?? _goHome,
              )
            : CustomScrollView(
                physics: const BouncingScrollPhysics(
                  parent: AlwaysScrollableScrollPhysics(),
                ),
                slivers: [
                  SliverToBoxAdapter(
                    child: _CartHeader(
                      cart: cart,
                      onBrowse: widget.onBrowseRestaurants ?? _goHome,
                    ),
                  ),
                  if (cart.carts.length > 1)
                    SliverToBoxAdapter(
                      child: _CartSwitcher(carts: cart.carts),
                    ),
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
                    sliver: SliverList.separated(
                      itemCount: cart.paidItems.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, index) {
                        final item = cart.paidItems[index];
                        return _CartReviewItem(
                          item: item,
                          restaurant: cart.restaurant,
                          rewardLine: _rewardLineForCartItem(item),
                        );
                      },
                    ),
                  ),
                  if (_detachedRewardLines.isNotEmpty)
                    SliverPadding(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
                      sliver: SliverToBoxAdapter(
                        child: _CartRewardSection(
                          rewardLines: _detachedRewardLines,
                        ),
                      ),
                    ),
                  const SliverToBoxAdapter(child: SizedBox(height: 150)),
                ],
              ),
      ),
      bottomNavigationBar: cart.totalCartItemCount == 0
          ? null
          : _CartBottomBar(
              total: checkoutTotal,
              discount: checkoutDiscount,
              itemCount: cart.paidItemCount,
              primary: primary,
              onAddMore: widget.onAddMore ?? _addMore,
              onCheckout: () => Navigator.pushNamed(context, '/checkout'),
            ),
    );
  }

  void _goHome() {
    Navigator.pushNamedAndRemoveUntil(context, '/home', (route) => false);
  }

  void _addMore() {
    final restaurant = context.read<CartProvider>().restaurant;
    if (restaurant == null) {
      _goHome();
      return;
    }
    Navigator.pushNamed(context, '/restaurant/detail',
        arguments: restaurant.id);
  }

  List<Map<String, dynamic>> get _detachedRewardLines {
    return _rewardLines
        .where((line) => !_rewardLineMatchesAnyCartItem(line))
        .toList(growable: false);
  }

  String _cartSignature(CartProvider cart) {
    final restaurantId = cart.restaurant?.id ?? 0;
    final itemSignature = cart.paidItems
        .map((item) => '${item.signature}:${item.quantity}')
        .join('|');
    return '$restaurantId::$itemSignature';
  }

  void _scheduleRewardRefreshIfNeeded(CartProvider cart) {
    if (cart.isEmpty) {
      _lastCartSignature = null;
      if (_rewardLines.isNotEmpty || _rewardCartSignature != null) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (!mounted) return;
          setState(() {
            _rewardLines.clear();
            _rewardCartSignature = null;
            _summaryDiscount = null;
            _summaryEmbeddedItemDiscount = null;
            _summaryTotal = null;
          });
        });
      }
      return;
    }

    // Skip while this screen is hidden behind another route (e.g. the
    // customer proceeded to checkout, which is pushed on top and keeps its
    // own summary in sync). Without this guard, both screens independently
    // re-fetch on every cart edit — checkout's build() keeps running here
    // too since Navigator keeps this route's State alive underneath — and
    // together they burn through /orders/summary's shared rate limit,
    // surfacing as 429s while adjusting quantity.
    if (ModalRoute.of(context)?.isCurrent != true) return;

    final signature = _cartSignature(cart);
    if (signature == _lastCartSignature) return;
    _lastCartSignature = signature;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _queueCartRewardRefresh();
    });
  }

  void _queueCartRewardRefresh([
    Duration delay = const Duration(milliseconds: 80),
  ]) {
    _rewardDebounce?.cancel();
    _rewardDebounce = Timer(delay, () {
      if (mounted) _refreshCartRewards();
    });
  }

  Future<void> _refreshCartRewards() async {
    final cart = context.read<CartProvider>();
    final restaurant = cart.restaurant;
    if (restaurant == null || cart.paidItems.isEmpty) {
      if (!mounted) return;
      setState(() {
        _rewardLines.clear();
        _rewardCartSignature = null;
        _summaryDiscount = null;
        _summaryEmbeddedItemDiscount = null;
        _summaryTotal = null;
      });
      return;
    }

    final requestSignature = _cartSignature(cart);
    if (_inFlightRewardSignature == requestSignature) {
      return;
    }
    _inFlightRewardSignature = requestSignature;
    final requestId = ++_rewardRequestId;
    final summaryItems = cart.paidItems
        .map(
          (item) => {
            'id': item.menuItem.id,
            'quantity': item.quantity,
            'selected_variant': item.selectedVariant?.toJson(),
            'selected_add_ons':
                item.selectedAddOns.map((option) => option.toJson()).toList(),
            if (item.promotionId != null) 'promotion_id': item.promotionId,
            if ((item.promotionTitle ?? '').trim().isNotEmpty)
              'promotion_title': item.promotionTitle,
            if ((item.promotionGroupKey ?? '').trim().isNotEmpty)
              'promotion_group_key': item.promotionGroupKey,
            if (item.promotionGroupSize != null)
              'promotion_group_size': item.promotionGroupSize,
            if (item.promotionDealPrice != null)
              'promotion_deal_price': item.promotionDealPrice,
            if (item.promotionOriginalPrice != null)
              'promotion_original_price': item.promotionOriginalPrice,
          },
        )
        .toList();
    debugPrint(
      '[SwaadPromoCart] cart summary request: restaurant=${restaurant.id}, '
      'signature=$requestSignature, items=$summaryItems',
    );
    try {
      final response = await _api.post(
        ApiConstants.orderSummary,
        data: {
          'restaurant_id': restaurant.id,
          'items': summaryItems,
          'order_type': 'delivery',
          if (cart.promotionCouponCode.isNotEmpty)
            'coupon_code': cart.promotionCouponCode,
        },
        coalesce: true,
        reuseFor: const Duration(seconds: 10),
      );

      if (!mounted ||
          requestId != _rewardRequestId ||
          requestSignature != _cartSignature(context.read<CartProvider>())) {
        return;
      }

      final data = response['data'] is Map
          ? Map<String, dynamic>.from(response['data'] as Map)
          : const <String, dynamic>{};
      final lines = response['success'] == true
          ? promotionRewardLinesFromSummary(data)
          : const <Map<String, dynamic>>[];
      debugPrint(
        '[SwaadPromoCart] cart summary response: success=${response['success']}, '
        'rewardLines=${lines.length}, rewards=${_rewardLineDebug(lines)}, '
        '${_summaryDebug(data)}',
      );
      setState(() {
        _rewardLines
          ..clear()
          ..addAll(lines);
        _rewardCartSignature = requestSignature;
        _summaryDiscount = promotionSummaryNumber(data, 'discount');
        _summaryEmbeddedItemDiscount =
            promotionSummaryNumber(data, 'embedded_item_discount');
        _summaryTotal = _doubleValue(data['total']);
      });
    } catch (e) {
      debugPrint('Cart reward summary error: $e');
      debugPrint('[SwaadPromoCart] cart summary failed: $e');
    } finally {
      if (_inFlightRewardSignature == requestSignature) {
        _inFlightRewardSignature = null;
      }
    }
  }

  List<Map<String, dynamic>> _mapList(dynamic value) {
    if (value is! List) return const <Map<String, dynamic>>[];
    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
  }

  Map<String, dynamic>? _rewardLineForCartItem(CartItem item) {
    if (_rewardCartSignature != _cartSignature(context.read<CartProvider>())) {
      return null;
    }
    final cart = context.read<CartProvider>();
    if (!item.isPromotionReward &&
        cart.promotionRewardItems.any(
          (reward) => reward.menuItem.id == item.menuItem.id,
        )) {
      return null;
    }
    for (final line in _rewardLines) {
      if (_rewardLineMatchesCartItem(line, item)) {
        return line;
      }
    }
    return null;
  }

  double? _doubleValue(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString());
  }

  String _rewardLineDebug(List<Map<String, dynamic>> lines) {
    return lines
        .map(
          (line) =>
              '${line['promotion_id']}:${line['menu_item_id'] ?? line['item_id']}x${line['quantity'] ?? line['qty']}',
        )
        .join(',');
  }

  String _summaryDebug(Map<String, dynamic> data) {
    return [
      'applied=${_promotionDebug(_mapList(data['applied_promotions']))}',
      'eligible=${_promotionDebug(_mapList(data['eligible_promotions']))}',
      'actions=${_actionDebug(_mapList(data['reward_actions']))}',
      'progress=${_progressDebug(_mapList(data['promotion_progress']))}',
      'invalid=${_invalidDebug(_mapList(data['invalid_reasons']))}',
      'discounts=${_discountDebug(_mapList(data['discount_lines']))}',
    ].join(', ');
  }

  String _promotionDebug(List<Map<String, dynamic>> items) {
    if (items.isEmpty) return '-';
    return items
        .take(5)
        .map((item) =>
            '${item['id']}:${item['promotion_type'] ?? item['title']}')
        .join('|');
  }

  String _actionDebug(List<Map<String, dynamic>> items) {
    if (items.isEmpty) return '-';
    return items
        .take(5)
        .map(
          (item) =>
              '${item['promotion_id']}:${item['action']}:units=${item['reward_units']}',
        )
        .join('|');
  }

  String _progressDebug(List<Map<String, dynamic>> items) {
    if (items.isEmpty) return '-';
    return items
        .take(5)
        .map(
          (item) =>
              '${item['promotion_id']}:${item['current']}/${item['required']}:${item['message']}',
        )
        .join('|');
  }

  String _invalidDebug(List<Map<String, dynamic>> items) {
    if (items.isEmpty) return '-';
    return items
        .take(5)
        .map(
          (item) =>
              '${item['promotion_id']}:${item['reason_code'] ?? ''}:${item['reason']}',
        )
        .join('|');
  }

  String _discountDebug(List<Map<String, dynamic>> items) {
    if (items.isEmpty) return '-';
    return items
        .take(5)
        .map(
          (item) =>
              '${item['promotion_id']}:${item['type']}:discount=${item['discount_amount']}:free=${item['free_item_id']}',
        )
        .join('|');
  }

  bool _rewardLineMatchesAnyCartItem(Map<String, dynamic> line) {
    final cart = context.read<CartProvider>();
    return cart.items.any((item) => _rewardLineMatchesCartItem(line, item));
  }

  static bool _rewardLineMatchesCartItem(
    Map<String, dynamic> line,
    CartItem item,
  ) {
    final rewardItemId = line['menu_item_id'] ?? line['item_id'];
    return rewardItemId?.toString() == item.menuItem.id.toString();
  }

  static bool _rewardLineIncludedInCart(Map<String, dynamic>? line) {
    if (line == null) return false;
    final value = line['included_in_cart'];
    if (value is bool) return value;
    final normalized = value?.toString().toLowerCase().trim();
    return normalized == '1' || normalized == 'true' || normalized == 'yes';
  }
}

class _CartHeader extends StatelessWidget {
  const _CartHeader({
    required this.cart,
    required this.onBrowse,
  });

  final CartProvider cart;
  final VoidCallback onBrowse;

  @override
  Widget build(BuildContext context) {
    final restaurant = cart.restaurant;
    final restaurantName = _cartRestaurantDisplayName(cart);
    final primary = FoodFlowTheme.brandPrimary(context);

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            FoodFlowTheme.brandSoft(context, 0.86),
            Colors.white,
          ],
        ),
        borderRadius: const BorderRadius.vertical(
          bottom: Radius.circular(28),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _RoundIconButton(
                icon: Icons.arrow_back_ios_new_rounded,
                onTap: () {
                  if (Navigator.canPop(context)) {
                    Navigator.pop(context);
                  } else {
                    onBrowse();
                  }
                },
              ),
              const Spacer(),
              Text(
                'Cart',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: FoodFlowTheme.ink,
                ),
              ),
              const Spacer(),
              _RoundIconButton(
                icon: Icons.delete_outline_rounded,
                color: FoodFlowTheme.danger,
                onTap: () => _confirmClearCart(context),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              _RestaurantAvatar(restaurant: restaurant, size: 58),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      restaurantName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${cart.itemCount} item${cart.itemCount == 1 ? '' : 's'} ready for checkout',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                        color: FoodFlowTheme.muted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.88),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: FoodFlowTheme.line),
            ),
            child: Row(
              children: [
                Icon(Icons.shopping_bag_outlined, color: primary, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Review items here. Payment and order placement happen on checkout.',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 12,
                      height: 1.35,
                      fontWeight: FontWeight.w600,
                      color: FoodFlowTheme.inkSoft,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _confirmClearCart(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return Container(
          margin: const EdgeInsets.all(12),
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.16),
                blurRadius: 30,
                offset: const Offset(0, 18),
              ),
            ],
          ),
          child: SafeArea(
            top: false,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Clear this cart?',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Items from the current restaurant cart will be removed.',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    color: FoodFlowTheme.muted,
                  ),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.pop(sheetContext),
                        child: const Text('Keep items'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: FoodFlowTheme.danger,
                          foregroundColor: Colors.white,
                        ),
                        onPressed: () {
                          context.read<CartProvider>().clearCart();
                          Navigator.pop(sheetContext);
                        },
                        child: const Text('Clear'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

String _cartRestaurantDisplayName(CartProvider cart) {
  final activeId = cart.restaurant?.id ?? 0;
  final names = <String>[
    cart.restaurant?.name ?? '',
    if (activeId > 0)
      ...cart.carts
          .where((entry) => entry.restaurant.id == activeId)
          .map((entry) => entry.restaurant.name),
    ...cart.carts.map((entry) => entry.restaurant.name),
  ];
  for (final name in names) {
    final trimmed = name.trim();
    if (trimmed.isNotEmpty && trimmed.toLowerCase() != 'restaurant') {
      return trimmed;
    }
  }
  return 'Current cart';
}

class _CartSwitcher extends StatelessWidget {
  const _CartSwitcher({required this.carts});

  final List<RestaurantCart> carts;

  @override
  Widget build(BuildContext context) {
    final activeId = context.select<CartProvider, int?>(
      (cart) => cart.restaurant?.id,
    );

    return SizedBox(
      height: 96,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
        scrollDirection: Axis.horizontal,
        itemCount: carts.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final restaurantCart = carts[index];
          final restaurant = restaurantCart.restaurant;
          final isActive = restaurant.id == activeId;
          final primary = FoodFlowTheme.brandPrimary(context);

          return InkWell(
            borderRadius: BorderRadius.circular(18),
            onTap: () => context.read<CartProvider>().setActiveCart(
                  restaurant.id,
                ),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              width: 190,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: isActive ? primary : FoodFlowTheme.line,
                  width: isActive ? 1.4 : 1,
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(isActive ? 0.12 : 0.05),
                    blurRadius: isActive ? 18 : 10,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Row(
                children: [
                  _RestaurantAvatar(restaurant: restaurant, size: 44),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          restaurant.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: FoodFlowTheme.ink,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${restaurantCart.itemCount} item${restaurantCart.itemCount == 1 ? '' : 's'}',
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: isActive ? primary : FoodFlowTheme.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _CartReviewItem extends StatelessWidget {
  const _CartReviewItem({
    required this.item,
    required this.restaurant,
    required this.rewardLine,
  });

  final CartItem item;
  final Restaurant? restaurant;
  final Map<String, dynamic>? rewardLine;

  @override
  Widget build(BuildContext context) {
    final menuItem = item.menuItem;
    final isReward = item.isPromotionReward;
    final hasOptions = !isReward &&
        (item.selectedVariant != null || item.selectedAddOns.isNotEmpty);
    final freeQuantity =
        isReward ? item.quantity : _rewardLineQuantity(rewardLine);
    final promotionTitle =
        item.promotionTitle ?? _rewardLinePromotionTitle(rewardLine);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: FoodFlowTheme.line.withOpacity(0.8)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _ItemImage(menuItem: menuItem),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _DietDot(item: menuItem),
                      const SizedBox(width: 7),
                      Expanded(
                        child: Text(
                          menuItem.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 14.5,
                            height: 1.24,
                            fontWeight: FontWeight.w800,
                            color: FoodFlowTheme.ink,
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (!isReward &&
                      menuItem.description?.trim().isNotEmpty == true) ...[
                    const SizedBox(height: 5),
                    Text(
                      menuItem.description!.trim(),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 11.5,
                        height: 1.35,
                        fontWeight: FontWeight.w500,
                        color: FoodFlowTheme.muted,
                      ),
                    ),
                  ],
                  if (hasOptions) ...[
                    const SizedBox(height: 8),
                    _OptionText(item: item),
                  ],
                  if (freeQuantity > 0) ...[
                    const SizedBox(height: 8),
                    _InlineRewardBadge(
                      quantity: freeQuantity,
                      title: promotionTitle,
                    ),
                  ],
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: isReward
                            ? Text(
                                'FREE',
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w900,
                                  color: FoodFlowTheme.successDark,
                                ),
                              )
                            : _PriceLine(item: item),
                      ),
                      if (!isReward)
                        _QuantityStepper(
                          quantity: item.quantity,
                          onDecrease: () => context
                              .read<CartProvider>()
                              .decrementBySignature(item.signature),
                          onIncrease: () => context
                              .read<CartProvider>()
                              .incrementBySignature(item.signature),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  static int _rewardLineQuantity(Map<String, dynamic>? line) {
    if (line == null) return 0;
    final value = line['quantity'] ?? line['qty'];
    if (value is num) return value.toInt().clamp(1, 999);
    return (int.tryParse(value?.toString() ?? '') ?? 1).clamp(1, 999);
  }

  static String _rewardLinePromotionTitle(Map<String, dynamic>? line) {
    final text =
        (line?['promotion_title'] ?? line?['title'])?.toString().trim();
    return text == null || text.isEmpty ? 'Promotion' : text;
  }
}

class _InlineRewardBadge extends StatelessWidget {
  const _InlineRewardBadge({
    required this.quantity,
    required this.title,
  });

  final int quantity;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: FoodFlowTheme.tagGreenSoft,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: FoodFlowTheme.tagGreenBorder),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.check_circle_rounded,
            color: FoodFlowTheme.tagGreenDark,
            size: 15,
          ),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              '$quantity free item • $title',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 11,
                fontWeight: FontWeight.w800,
                color: FoodFlowTheme.tagGreenDark,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CartRewardSection extends StatelessWidget {
  const _CartRewardSection({
    required this.rewardLines,
  });

  final List<Map<String, dynamic>> rewardLines;

  @override
  Widget build(BuildContext context) {
    if (rewardLines.isEmpty) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: FoodFlowTheme.tagGreenBorder),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.card_giftcard_rounded,
                color: FoodFlowTheme.tagGreenDark,
                size: 19,
              ),
              const SizedBox(width: 8),
              Text(
                'Free with this order',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                  color: FoodFlowTheme.ink,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ...rewardLines.map((line) => _DetachedRewardLine(line: line)),
        ],
      ),
    );
  }
}

class _DetachedRewardLine extends StatelessWidget {
  const _DetachedRewardLine({required this.line});

  final Map<String, dynamic> line;

  @override
  Widget build(BuildContext context) {
    final quantity = _rewardLineQuantity(line);
    final name =
        (line['name'] ?? line['title'] ?? 'Free item').toString().trim();
    final imageUrl = (line['image_url'] ?? line['image'] ?? '').toString();

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: SizedBox(
              width: 46,
              height: 46,
              child: imageUrl.isEmpty
                  ? _rewardPlaceholder(context)
                  : AppCachedImage(
                      imageUrl: imageUrl,
                      width: 46,
                      height: 46,
                      fit: BoxFit.cover,
                      errorBuilder: (context, _, __) =>
                          _rewardPlaceholder(context),
                    ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              name.isEmpty ? 'Free item' : name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 13,
                height: 1.25,
                fontWeight: FontWeight.w800,
                color: FoodFlowTheme.ink,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '$quantity x FREE',
            style: GoogleFonts.plusJakartaSans(
              fontSize: 12,
              fontWeight: FontWeight.w900,
              color: FoodFlowTheme.tagGreenDark,
            ),
          ),
        ],
      ),
    );
  }

  static int _rewardLineQuantity(Map<String, dynamic> line) {
    final value = line['quantity'] ?? line['qty'];
    if (value is num) return value.toInt().clamp(1, 999);
    return (int.tryParse(value?.toString() ?? '') ?? 1).clamp(1, 999);
  }

  static Widget _rewardPlaceholder(BuildContext context) {
    return Container(
      color: FoodFlowTheme.tagGreenSoft,
      child: const Icon(
        Icons.card_giftcard_rounded,
        color: FoodFlowTheme.tagGreenDark,
        size: 20,
      ),
    );
  }
}

class _ItemImage extends StatelessWidget {
  const _ItemImage({required this.menuItem});

  final MenuItem menuItem;

  @override
  Widget build(BuildContext context) {
    final imageUrl = menuItem.imageUrl;
    return ClipRRect(
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        width: 90,
        height: 96,
        child: imageUrl.isEmpty
            ? Container(
                color: FoodFlowTheme.surfaceCool,
                child: Icon(
                  Icons.restaurant_menu_rounded,
                  color: FoodFlowTheme.brandPrimary(context),
                  size: 30,
                ),
              )
            : AppCachedImage(
                imageUrl: imageUrl,
                fit: BoxFit.cover,
                width: 90,
                height: 96,
                errorBuilder: (context, _, __) => Container(
                  color: FoodFlowTheme.surfaceCool,
                  child: Icon(
                    Icons.restaurant_menu_rounded,
                    color: FoodFlowTheme.brandPrimary(context),
                    size: 30,
                  ),
                ),
              ),
      ),
    );
  }
}

class _PriceLine extends StatelessWidget {
  const _PriceLine({required this.item});

  final CartItem item;

  @override
  Widget build(BuildContext context) {
    final menuItem = item.menuItem;
    final displayTotal = item.totalPrice;
    return Wrap(
      spacing: 7,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        Text(
          formatCurrency(context, displayTotal),
          style: GoogleFonts.plusJakartaSans(
            fontSize: 17,
            fontWeight: FontWeight.w900,
            color: FoodFlowTheme.ink,
          ),
        ),
        if (menuItem.hasDiscount)
          Text(
            formatCurrency(
              context,
              menuItem.price * item.quantity,
            ),
            style: GoogleFonts.plusJakartaSans(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: FoodFlowTheme.faint,
              decoration: TextDecoration.lineThrough,
            ),
          ),
      ],
    );
  }
}

class _OptionText extends StatelessWidget {
  const _OptionText({required this.item});

  final CartItem item;

  @override
  Widget build(BuildContext context) {
    final values = <String>[
      if (item.selectedVariant != null) item.selectedVariant!.name,
      ...item.selectedAddOns.map((option) => option.name),
    ].where((value) => value.trim().isNotEmpty).join(' + ');

    if (values.isEmpty) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: FoodFlowTheme.tagOrangeSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: FoodFlowTheme.tagOrangeBorder),
      ),
      child: Text(
        values,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: GoogleFonts.plusJakartaSans(
          fontSize: 10.5,
          fontWeight: FontWeight.w700,
          color: FoodFlowTheme.tagOrangeDark,
        ),
      ),
    );
  }
}

class _QuantityStepper extends StatelessWidget {
  const _QuantityStepper({
    required this.quantity,
    required this.onDecrease,
    required this.onIncrease,
  });

  final int quantity;
  final VoidCallback onDecrease;
  final VoidCallback onIncrease;

  @override
  Widget build(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    return Container(
      height: 34,
      padding: const EdgeInsets.symmetric(horizontal: 4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: primary.withOpacity(0.45)),
        boxShadow: [
          BoxShadow(
            color: primary.withOpacity(0.18),
            blurRadius: 12,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _StepperButton(icon: Icons.remove_rounded, onTap: onDecrease),
          SizedBox(
            width: 28,
            child: Text(
              quantity.toString(),
              textAlign: TextAlign.center,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 13,
                fontWeight: FontWeight.w900,
                color: primary,
              ),
            ),
          ),
          _StepperButton(icon: Icons.add_rounded, onTap: onIncrease),
        ],
      ),
    );
  }
}

class _StepperButton extends StatelessWidget {
  const _StepperButton({
    required this.icon,
    required this.onTap,
  });

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(10),
      onTap: onTap,
      child: SizedBox(
        width: 28,
        height: 28,
        child: Icon(
          icon,
          size: 18,
          color: FoodFlowTheme.brandPrimary(context),
        ),
      ),
    );
  }
}

class _CartBottomBar extends StatelessWidget {
  const _CartBottomBar({
    required this.total,
    required this.discount,
    required this.itemCount,
    required this.primary,
    required this.onAddMore,
    required this.onCheckout,
  });

  final double total;
  final double discount;
  final int itemCount;
  final Color primary;
  final VoidCallback onAddMore;
  final VoidCallback onCheckout;

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.paddingOf(context).bottom;
    return Container(
      padding: EdgeInsets.fromLTRB(14, 12, 14, 12 + bottom),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.12),
            blurRadius: 26,
            offset: const Offset(0, -10),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: onAddMore,
              icon: Icon(Icons.add_rounded, color: primary, size: 20),
              label: Text(
                'Add more',
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: primary,
                ),
              ),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(0, 52),
                side: BorderSide(color: primary.withOpacity(0.25)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(17),
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            flex: 2,
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: FoodFlowTheme.brandGradientOf(context),
                borderRadius: BorderRadius.circular(18),
                boxShadow: [
                  BoxShadow(
                    color: primary.withOpacity(0.30),
                    blurRadius: 18,
                    offset: const Offset(0, 9),
                  ),
                  BoxShadow(
                    color: Colors.white.withOpacity(0.35),
                    blurRadius: 2,
                    offset: const Offset(-1, -1),
                  ),
                ],
              ),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(18),
                  onTap: onCheckout,
                  child: Container(
                    height: 54,
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                formatCurrency(context, total),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w900,
                                  color: FoodFlowTheme.brandOnPrimary(context),
                                ),
                              ),
                              Text(
                                discount > 0
                                    ? 'Saved ${formatCurrency(context, discount)}'
                                    : '$itemCount item${itemCount == 1 ? '' : 's'}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                  color: FoodFlowTheme.brandOnPrimary(context)
                                      .withOpacity(0.85),
                                ),
                              ),
                            ],
                          ),
                        ),
                        Text(
                          'Checkout',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 14,
                            fontWeight: FontWeight.w900,
                            color: FoodFlowTheme.brandOnPrimary(context),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Icon(
                          Icons.arrow_forward_ios_rounded,
                          size: 16,
                          color: FoodFlowTheme.brandOnPrimary(context),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyCartView extends StatelessWidget {
  const _EmptyCartView({required this.onBrowse});

  final VoidCallback onBrowse;

  @override
  Widget build(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          Row(
            children: [
              _RoundIconButton(
                icon: Icons.arrow_back_ios_new_rounded,
                onTap: () {
                  if (Navigator.canPop(context)) {
                    Navigator.pop(context);
                  } else {
                    onBrowse();
                  }
                },
              ),
              const Spacer(),
              Text(
                'Cart',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: FoodFlowTheme.ink,
                ),
              ),
              const Spacer(),
              const SizedBox(width: 44),
            ],
          ),
          const Spacer(),
          Container(
            width: 150,
            height: 150,
            decoration: BoxDecoration(
              color: FoodFlowTheme.brandSoft(context, 0.88),
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.shopping_cart_outlined,
              size: 64,
              color: primary,
            ),
          ),
          const SizedBox(height: 28),
          Text(
            'Your cart is empty',
            textAlign: TextAlign.center,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 24,
              fontWeight: FontWeight.w900,
              color: FoodFlowTheme.ink,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Add dishes from restaurants and review them here before checkout.',
            textAlign: TextAlign.center,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 13,
              height: 1.45,
              fontWeight: FontWeight.w500,
              color: FoodFlowTheme.muted,
            ),
          ),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: onBrowse,
              style: ElevatedButton.styleFrom(
                minimumSize: const Size.fromHeight(54),
                backgroundColor: primary,
                foregroundColor: FoodFlowTheme.brandOnPrimary(context),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(18),
                ),
              ),
              child: Text(
                'Browse restaurants',
                style: GoogleFonts.plusJakartaSans(
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
          const Spacer(),
        ],
      ),
    );
  }
}

class _RestaurantAvatar extends StatelessWidget {
  const _RestaurantAvatar({
    required this.restaurant,
    required this.size,
  });

  final Restaurant? restaurant;
  final double size;

  @override
  Widget build(BuildContext context) {
    final logoUrl = restaurant?.logoUrl ?? '';
    return ClipOval(
      child: SizedBox(
        width: size,
        height: size,
        child: logoUrl.isEmpty
            ? Container(
                color: FoodFlowTheme.brandSoft(context, 0.84),
                child: Icon(
                  Icons.storefront_rounded,
                  color: FoodFlowTheme.brandPrimary(context),
                  size: size * 0.44,
                ),
              )
            : AppCachedImage(
                imageUrl: logoUrl,
                fit: BoxFit.cover,
                width: size,
                height: size,
                errorBuilder: (context, _, __) => Container(
                  color: FoodFlowTheme.brandSoft(context, 0.84),
                  child: Icon(
                    Icons.storefront_rounded,
                    color: FoodFlowTheme.brandPrimary(context),
                    size: size * 0.44,
                  ),
                ),
              ),
      ),
    );
  }
}

class _DietDot extends StatelessWidget {
  const _DietDot({required this.item});

  final MenuItem item;

  @override
  Widget build(BuildContext context) {
    final color = item.isNonVeg
        ? FoodFlowTheme.danger
        : item.isEgg
            ? FoodFlowTheme.tagOrange
            : FoodFlowTheme.tagGreen;
    return Container(
      width: 14,
      height: 14,
      margin: const EdgeInsets.only(top: 2),
      decoration: BoxDecoration(
        border: Border.all(color: color, width: 1.4),
        borderRadius: BorderRadius.circular(3),
      ),
      child: Center(
        child: Container(
          width: 6,
          height: 6,
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
          ),
        ),
      ),
    );
  }
}

class _RoundIconButton extends StatelessWidget {
  const _RoundIconButton({
    required this.icon,
    required this.onTap,
    this.color,
  });

  final IconData icon;
  final VoidCallback onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: const CircleBorder(),
      elevation: 0,
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(
            icon,
            size: 20,
            color: color ?? FoodFlowTheme.ink,
          ),
        ),
      ),
    );
  }
}
