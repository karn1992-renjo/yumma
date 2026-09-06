import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../config/api_constants.dart';
import '../../../../models/menu_item.dart';
import '../../../../models/order.dart';
import '../../../../models/restaurant.dart';
import '../../../../providers/auth_provider.dart';
import '../../../../providers/cart_provider.dart';
import '../../../../providers/order_provider.dart';
import '../../../../services/api_service.dart';
import '../../../../services/flexible_order_payment_service.dart';
import '../../../../services/location_service.dart';
import '../../../../utils/currency_utils.dart';
import '../../../../widgets/common/app_cached_image.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_cart_actions.dart';
import '../widgets/v2_free_delivery_popup.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_location_sheet.dart';
import '../widgets/v2_scaffold.dart';
import 'coupons_v2.dart';
import 'order_confirmation_v2.dart';
import 'payment_selector_v2.dart';
import 'pre_order_confirm_v2.dart';

class CheckoutV2 extends StatefulWidget {
  const CheckoutV2({super.key});

  @override
  State<CheckoutV2> createState() => _CheckoutV2State();
}

class _CheckoutV2State extends State<CheckoutV2> {
  final ApiService _api = ApiService();
  final LocationService _location = LocationService();
  final TextEditingController _couponCtl = TextEditingController();
  final TextEditingController _noteCtl = TextEditingController();

  bool _loadingSummary = true;
  bool _placing = false;
  Map<String, dynamic> _summary = const {};
  Map<String, dynamic>? _savedLoc;
  String _coupon = '';
  double _tip = 0;
  V2PaymentChoice _payment = const V2PaymentChoice(
      method: 'cod', label: 'Cash on delivery');
  List<Map<String, dynamic>> _suggestions = const [];
  List<Map<String, dynamic>> _myCoupons = const [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadSummary();
      _loadSuggestions();
      _loadMyCoupons();
    });
  }

  Future<void> _loadMyCoupons() async {
    try {
      final res = await _api.get(ApiConstants.rewardCoupons);
      final rows = (res is Map && res['data'] is List)
          ? res['data'] as List
          : const [];
      final list = rows
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .where((c) => (c['status'] ?? '').toString() == 'unused')
          .toList();
      if (mounted) setState(() => _myCoupons = list);
    } catch (_) {}
  }

  Timer? _summaryDebounce;

  @override
  void dispose() {
    _summaryDebounce?.cancel();
    _couponCtl.dispose();
    _noteCtl.dispose();
    super.dispose();
  }

  /// Re-price after the cart is edited on this screen so "Item total", taxes,
  /// discount and the free-delivery bar can't go stale against the steppers.
  /// The bill also recomputes optimistically from the live cart in the
  /// meantime (see [_liveSubtotal] / [_computedTotal]).
  void _refreshSummarySoon() {
    setState(() {}); // reflect the new qty in the bill immediately
    _summaryDebounce?.cancel();
    _summaryDebounce = Timer(const Duration(milliseconds: 250), () {
      if (mounted) _loadSummary();
    });
  }

  int? get _restaurantId {
    final r = context.read<CartProvider>().restaurant;
    return r?.id;
  }

  Future<void> _loadSummary() async {
    final cart = context.read<CartProvider>();
    final restaurant = cart.restaurant;
    if (restaurant == null || cart.totalCartItemCount == 0) {
      setState(() => _loadingSummary = false);
      return;
    }
    setState(() => _loadingSummary = true);
    try {
      _savedLoc = await _location.getSavedLocation();
      final items = cart.paidItems
          .map((it) => {'id': it.menuItem.id, 'quantity': it.quantity})
          .toList();
      final res = await _api.post(ApiConstants.orderSummary, data: {
        'restaurant_id': restaurant.id,
        'items': items,
        'order_type': 'delivery',
        if (_savedLoc?['lat'] != null) 'delivery_lat': _savedLoc!['lat'],
        if (_savedLoc?['lng'] != null) 'delivery_lng': _savedLoc!['lng'],
        if (_coupon.isNotEmpty) 'coupon_code': _coupon,
        if (_tip > 0) 'tip': _tip,
      });
      if (res is Map && res['data'] is Map) {
        _summary = Map<String, dynamic>.from(res['data'] as Map);
      }
    } catch (_) {}
    if (mounted) setState(() => _loadingSummary = false);
    _maybeCelebrateFreeDelivery();
  }

  void _maybeCelebrateFreeDelivery() {
    final threshold = v2DoubleOrNull(_summary['free_delivery_threshold']);
    setV2FreeDeliveryThreshold(threshold);
    final remaining = v2DoubleOrNull(_summary['free_delivery_remaining']);
    final celebrate = V2FreeDeliveryTracker.shouldCelebrate(
      eligible: threshold != null,
      achieved: threshold != null && (remaining ?? 0) <= 0,
    );
    if (celebrate) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) showV2FreeDeliverySuccess(context);
      });
    }
  }

  Future<void> _loadSuggestions() async {
    final id = _restaurantId;
    if (id == null) return;
    try {
      final res = await _api.get('${ApiConstants.restaurantDetails}/$id/menu');
      final data = res is Map && res['data'] is Map
          ? Map<String, dynamic>.from(res['data'] as Map)
          : <String, dynamic>{};
      final list = (data['menu_items'] ?? data['items'] ?? data['menu']) as List?;
      final inCart = context
          .read<CartProvider>()
          .paidItems
          .map((e) => e.menuItem.id)
          .toSet();
      _suggestions = (list ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .where((e) =>
              !inCart.contains(v2Int(e['id'])) &&
              v2Double(e['discounted_price'] ?? e['price']) > 0)
          .take(8)
          .toList();
      if (mounted) setState(() {});
    } catch (_) {}
  }

  Future<void> _applyCoupon() async {
    setState(() => _coupon = _couponCtl.text.trim());
    await _loadSummary();
    if (mounted) {
      final applied = (_summary['discount'] ?? 0);
      final ok = (double.tryParse('$applied') ?? 0) > 0;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
            content: Text(ok
                ? 'Coupon applied'
                : _coupon.isEmpty
                    ? 'Coupon removed'
                    : 'Coupon not applicable')));
    }
  }

  double _lastTotal = 0;

  void _setTip(double v) {
    setState(() => _tip = v);
    _refreshSummarySoon();
  }

  Future<void> _pickCustomTip() async {
    final ctl = TextEditingController(
        text: _tip > 0 ? _tip.toStringAsFixed(0) : '');
    final v = await showDialog<double>(
      context: context,
      builder: (dctx) {
        final p = V2Theme.of(dctx);
        return AlertDialog(
          backgroundColor: p.bgTop,
          title: Text('Custom tip',
              style: TextStyle(color: p.ink, fontWeight: FontWeight.w900)),
          content: TextField(
            controller: ctl,
            autofocus: true,
            keyboardType: TextInputType.number,
            style: TextStyle(color: p.ink),
            decoration: InputDecoration(
              prefixText: '${currencyInputPrefix(dctx)} ',
              hintText: 'Amount',
            ),
          ),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(dctx),
                child: const Text('Cancel')),
            TextButton(
              onPressed: () => Navigator.pop(
                  dctx, double.tryParse(ctl.text.trim()) ?? 0),
              child: const Text('Add'),
            ),
          ],
        );
      },
    );
    if (v != null) _setTip(v.clamp(0, 1000).toDouble());
  }

  Future<void> _openAllCoupons() async {
    final id = _restaurantId;
    if (id == null) return;
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute(
        builder: (_) => CouponsV2(restaurantId: id, appliedCode: _coupon),
      ),
    );
    if (code != null && code.trim().isNotEmpty && mounted) {
      _couponCtl.text = code.trim();
      _applyCoupon();
    }
  }

  Future<void> _pickPayment() async {
    final choice = await Navigator.of(context).push<V2PaymentChoice>(
      MaterialPageRoute(
        builder: (_) =>
            PaymentSelectorV2(selected: _payment, total: _lastTotal),
      ),
    );
    if (choice != null && mounted) setState(() => _payment = choice);
  }

  Future<void> _pickAddress() async {
    final picked = await showV2LocationSheet(context);
    if (picked != null) {
      await _location.saveLocation(picked.city, picked.lat, picked.lng,
          address: picked.address);
      await _loadSummary();
    }
  }

  double _num(dynamic v, [double f = 0]) => v2DoubleOrNull(v) ?? f;

  /// True while the cart has been edited on this screen but the server
  /// re-price hasn't landed yet — the `_summary` figures are stale.
  bool get _summaryStale {
    final serverSub = v2DoubleOrNull(_summary['subtotal']);
    if (serverSub == null) return _summary.isEmpty;
    if ((_num(_summary['tip']) - _tip).abs() > 0.01) return true;
    return (serverSub - context.read<CartProvider>().displaySubtotal).abs() >
        0.5;
  }

  /// Item total to show — the live cart value whenever the server figure is
  /// stale, so the bill tracks the steppers instantly.
  double get _liveSubtotal {
    final cart = context.read<CartProvider>();
    return _summaryStale
        ? cart.displaySubtotal
        : _num(_summary['subtotal'], cart.displaySubtotal);
  }

  /// Actual (post free-delivery) delivery fee to charge.
  double get _payableDelivery => _num(
      _summary['payable_delivery_fee'] ??
          _summary['customer_delivery_fee'] ??
          _summary['delivery_fee'],
      context.read<CartProvider>().deliveryFee);

  /// Full delivery fee before any free-delivery / promo waiver.
  double get _originalDelivery => _num(
      _summary['original_delivery_fee'] ?? _summary['delivery_fee'],
      _payableDelivery);

  bool get _freeDeliveryApplied =>
      _originalDelivery - _payableDelivery > 0.01 &&
      _num(_summary['free_delivery_threshold']) > 0;

  /// The "To pay" figure — the same buildPricingSummary() the order-create
  /// endpoint runs, so the review screen and the created order agree. While a
  /// re-price is pending it's recomputed from the live cart + known fees.
  double get _computedTotal {
    final subtotal = _liveSubtotal;
    final platform = _num(_summary['platform_fee']);
    final tax = _num(_summary['tax']);
    final discount = _num(_summary['discount']);
    final surge = _num(_summary['surge_fee']) +
        _num(_summary['night_surcharge']);
    final fallback = subtotal +
        _payableDelivery +
        platform +
        tax +
        surge -
        discount +
        _tip;
    // The server total already includes the tip we send with the summary.
    return _summaryStale ? fallback : _num(_summary['total'], fallback);
  }

  /// Change the delivery address from the review screen; re-prices and hands
  /// the fresh address + total back so the review screen updates in place.
  Future<PreOrderReview?> _editLocationForReview() async {
    final picked = await showV2LocationSheet(context);
    if (picked == null || !mounted) return null;
    await _location.saveLocation(picked.city, picked.lat, picked.lng,
        address: picked.address);
    await _loadSummary();
    if (!mounted) return null;
    final addr = picked.address.trim();
    return PreOrderReview(
      address: addr.isNotEmpty ? addr : picked.city.toString(),
      lat: picked.lat,
      lng: picked.lng,
      total: _computedTotal,
    );
  }

  /// Step 1 — open the "review on a map" screen. The real order call runs from
  /// its Confirm button via [_placeOrder].
  Future<void> _reviewOrder(double total) async {
    if (_placing) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PreOrderConfirmV2(
          address: (_savedLoc?['address'] ?? _savedLoc?['city'] ?? '')
              .toString(),
          lat: v2DoubleOrNull(_savedLoc?['lat']),
          lng: v2DoubleOrNull(_savedLoc?['lng']),
          total: total,
          paymentLabel: _payment.label,
          onConfirm: _placeOrder,
          onEditLocation: _editLocationForReview,
        ),
      ),
    );
  }

  /// Step 2 — create the order (+ pay when online). Returns true on success;
  /// on success it also navigates checkout to the confirmation screen.
  Future<bool> _placeOrder() async {
    final cart = context.read<CartProvider>();
    final restaurant = cart.restaurant;
    if (restaurant == null) return false;
    final user = context.read<AuthProvider>().currentUser;
    setState(() => _placing = true);

    final orderData = <String, dynamic>{
      'restaurant_id': restaurant.id,
      'items': cart.paidItems
          .map((it) => {
                'id': it.menuItem.id,
                'quantity': it.quantity,
                'selected_variant': it.selectedVariant?.toJson(),
                'selected_add_ons':
                    it.selectedAddOns.map((o) => o.toJson()).toList(),
              })
          .toList(),
      'order_type': 'delivery',
      if (_savedLoc?['lat'] != null) 'delivery_lat': _savedLoc!['lat'],
      if (_savedLoc?['lng'] != null) 'delivery_lng': _savedLoc!['lng'],
      if (_savedLoc?['address'] != null)
        'delivery_address': _savedLoc!['address'],
      'customer_name': user?.name,
      'customer_email': user?.email,
      'email': user?.email,
      'customer_phone': user?.phone,
      'phone': user?.phone,
      'contact': user?.phone,
      'payment_method': _payment.method == 'cod'
          ? 'cod'
          : _payment.method == 'wallet'
              ? 'wallet'
              : (_payment.gateway ?? 'razorpay'),
      if (_payment.method == 'online' && _payment.mode != null)
        'payment_mode': _payment.mode,
      'special_instructions': _noteCtl.text.trim(),
      if (_coupon.isNotEmpty) 'coupon_code': _coupon,
      if (_tip > 0) 'tip': _tip,
    };

    final isOnline = _payment.method == 'online';
    final service = isOnline ? FlexibleOrderPaymentService() : null;
    try {
      final orderProvider = context.read<OrderProvider>();
      Order order;

      if (isOnline) {
        // Online orders go through /checkout/payment + verify: the server only
        // creates the order once it holds a verified gateway payment. Calling
        // createOrder first would fail with "payment proof is missing".
        final paid = await service!.payForCheckout(
          orderData: orderData,
          gateway: _payment.gateway ?? 'razorpay',
          paymentMode: _payment.mode ?? 'upi',
        );
        if (paid == null) {
          _fail('Payment was not confirmed. Order not placed.');
          return false;
        }
        order = paid;
      } else {
        final created = await orderProvider.createOrder(orderData);
        if (created == null) {
          _fail(orderProvider.error ?? 'Could not place order');
          return false;
        }
        order = created;
      }

      cart.clearAllCarts();
      if (!mounted) return true;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => OrderConfirmationV2(order: order),
        ),
      );
      return true;
    } catch (e) {
      _fail(e.toString().replaceFirst('Exception: ', ''));
      return false;
    } finally {
      service?.dispose();
    }
  }

  void _fail(String m) {
    if (!mounted) return;
    setState(() => _placing = false);
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(m)));
    // A rejected order can mean the coupon stopped qualifying (item removed,
    // window closed). Re-price so the bill on screen matches reality.
    if (_coupon.isNotEmpty) _loadSummary();
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final cart = context.watch<CartProvider>();
    final carts = cart.carts;
    if (cart.totalCartItemCount == 0 || carts.isEmpty) {
      return const V2Scaffold(
        title: 'Checkout',
        showBack: true,
        body: V2EmptyState(
          icon: Icons.shopping_bag_outlined,
          title: 'Your cart is empty',
        ),
      );
    }
    final rc = carts.first;
    final subtotal = _liveSubtotal;
    final delivery = _payableDelivery;
    final deliveryOriginal = _originalDelivery;
    final freeDelivery = _freeDeliveryApplied;
    final platform = _num(_summary['platform_fee']);
    final tax = _num(_summary['tax']);
    final discount = _num(_summary['discount']);
    final surge = _num(_summary['surge_fee']);
    final night = _num(_summary['night_surcharge']);
    final pointsMsg = (_summary['reward_points_message'] ?? '').toString();
    final fdThreshold = _num(_summary['free_delivery_threshold']);
    final fdRemaining = _num(_summary['free_delivery_remaining'], -1);
    // Single source of truth: the same buildPricingSummary() the order-create
    // endpoint runs. Free delivery is auto-applied by the server; the driver
    // tip is added on top. While a stepper edit is being re-priced the
    // "Item total" and "To pay" fall back to the live cart.
    final total = _computedTotal;
    _lastTotal = total;
    final address = (_savedLoc?['address'] ?? _savedLoc?['city'] ?? '')
        .toString();

    return V2Scaffold(
      title: 'Checkout',
      showBack: true,
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 20),
              children: [
                // -- address ------------------------------------------------
                V2Entrance(
                  child: GlassPanel(
                    radius: 18,
                    onTap: _pickAddress,
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        Icon(Icons.location_on_rounded,
                            size: 18, color: p.accent),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Delivering to',
                                  style: TextStyle(
                                      color: p.inkFaint, fontSize: 11)),
                              const SizedBox(height: 2),
                              Text(
                                address.isEmpty
                                    ? 'Tap to choose an address'
                                    : address,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                    color: p.ink,
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                        ),
                        Icon(Icons.chevron_right_rounded, color: p.inkFaint),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                // -- items ------------------------------------------------
                GlassPanel(
                  radius: 18,
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(rc.restaurant.name,
                          style: TextStyle(
                              color: p.ink,
                              fontSize: 15,
                              fontWeight: FontWeight.w900)),
                      const SizedBox(height: 10),
                      for (final it in rc.items)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Row(
                            children: [
                              _CheckoutThumb(
                                  url: it.menuItem.imageUrl,
                                  isVeg: it.menuItem.isVeg),
                              const SizedBox(width: 10),
                              _MiniStepper(
                                qty: it.quantity,
                                onAdd: () {
                                  cart.incrementBySignature(it.signature);
                                  _refreshSummarySoon();
                                },
                                onRemove: () {
                                  cart.decrementBySignature(it.signature);
                                  _refreshSummarySoon();
                                },
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(it.menuItem.name,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(color: p.ink)),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                formatCurrency(context, it.displayTotalPrice),
                                style: TextStyle(
                                    color: p.inkSoft,
                                    fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
                // -- suggestions ----------------------------------------
                if (_suggestions.isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Padding(
                    padding: const EdgeInsets.only(left: 4, bottom: 8),
                    child: Text('ADD MORE FROM ${rc.restaurant.name.toUpperCase()}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            color: p.inkFaint,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.6)),
                  ),
                  SizedBox(
                    height: 150,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: _suggestions.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 10),
                      itemBuilder: (_, i) => _SuggestionCard(
                        data: _suggestions[i],
                        restaurant: rc.restaurant,
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                // -- coupons: quick strip + "view all" -------------------
                Padding(
                  padding: const EdgeInsets.only(left: 4, bottom: 8),
                  child: Row(
                    children: [
                      Text('COUPONS & OFFERS',
                          style: TextStyle(
                              color: p.inkFaint,
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.6)),
                      const Spacer(),
                      V2Tappable(
                        onTap: _openAllCoupons,
                        child: Row(
                          children: [
                            Text('View all',
                                style: TextStyle(
                                    color: p.accent,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w900)),
                            Icon(Icons.chevron_right_rounded,
                                size: 16, color: p.accent),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                if (_myCoupons.isNotEmpty)
                  SizedBox(
                    height: 66,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: _myCoupons.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 10),
                      itemBuilder: (_, i) {
                        final c = _myCoupons[i];
                        final code = (c['code'] ?? '').toString();
                        final promo = c['promotion'] is Map
                            ? Map<String, dynamic>.from(c['promotion'] as Map)
                            : const {};
                        final title = (promo['title'] ??
                                c['title'] ??
                                'Reward coupon')
                            .toString();
                        final active =
                            _coupon.toUpperCase() == code.toUpperCase();
                        return V2Tappable(
                          onTap: () {
                            _couponCtl.text = code;
                            _applyCoupon();
                          },
                          child: Container(
                            width: 190,
                            padding: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 8),
                            decoration: BoxDecoration(
                              color: active
                                  ? p.accent.withOpacity(0.12)
                                  : p.glassTop,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                  color: active
                                      ? p.accent
                                      : p.glassBorder),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(code,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                        color: p.accent,
                                        fontWeight: FontWeight.w900,
                                        fontSize: 13,
                                        letterSpacing: 0.5)),
                                const SizedBox(height: 2),
                                Text(title,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                        color: p.inkSoft, fontSize: 11)),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                const SizedBox(height: 12),
                // -- coupon -------------------------------------------
                GlassPanel(
                  radius: 16,
                  padding: const EdgeInsets.symmetric(
                      horizontal: 12, vertical: 2),
                  child: Row(
                    children: [
                      Icon(Icons.confirmation_num_rounded,
                          size: 18, color: p.accent),
                      const SizedBox(width: 8),
                      Expanded(
                        child: TextField(
                          controller: _couponCtl,
                          textCapitalization: TextCapitalization.characters,
                          textAlignVertical: TextAlignVertical.center,
                          keyboardAppearance:
                              p.isDark ? Brightness.dark : Brightness.light,
                          style: TextStyle(
                              color: p.ink,
                              fontSize: 13.5,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 1),
                          cursorColor: p.accent,
                          decoration: InputDecoration(
                            isDense: true,
                            border: InputBorder.none,
                            contentPadding:
                                const EdgeInsets.symmetric(vertical: 12),
                            hintText: 'Enter a coupon code',
                            hintStyle:
                                TextStyle(color: p.inkFaint, fontSize: 13),
                          ),
                        ),
                      ),
                      V2Tappable(
                        onTap: _applyCoupon,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 10),
                          child: Text(
                            _coupon.isEmpty ? 'Apply' : 'Update',
                            style: TextStyle(
                                color: p.accent,
                                fontWeight: FontWeight.w900,
                                fontSize: 13),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                // -- note -------------------------------------------
                GlassPanel(
                  radius: 16,
                  padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.sticky_note_2_outlined,
                              size: 16, color: p.accent),
                          const SizedBox(width: 8),
                          Text('Delivery instructions',
                              style: TextStyle(
                                  color: p.inkSoft,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800)),
                        ],
                      ),
                      const SizedBox(height: 6),
                      ConstrainedBox(
                        constraints: const BoxConstraints(minHeight: 66),
                        child: TextField(
                          controller: _noteCtl,
                          maxLines: 4,
                          minLines: 3,
                          keyboardAppearance: p.isDark
                              ? Brightness.dark
                              : Brightness.light,
                          style: TextStyle(color: p.ink, fontSize: 13.5),
                          cursorColor: p.accent,
                          decoration: InputDecoration(
                            isDense: true,
                            border: InputBorder.none,
                            contentPadding: EdgeInsets.zero,
                            hintText:
                                'e.g. leave at the door, call on arrival, '
                                'gate code…',
                            hintStyle: TextStyle(
                                color: p.inkFaint, fontSize: 13, height: 1.35),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                // -- driver tip -------------------------------------
                GlassPanel(
                  radius: 16,
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.volunteer_activism_rounded,
                              size: 16, color: p.accent),
                          const SizedBox(width: 8),
                          Text('Tip your delivery partner',
                              style: TextStyle(
                                  color: p.ink,
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w800)),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text('100% of the tip goes to your delivery partner.',
                          style:
                              TextStyle(color: p.inkFaint, fontSize: 11)),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final t in const [10.0, 20.0, 30.0, 50.0])
                            _TipChip(
                              label: formatCurrency(context, t),
                              on: _tip == t,
                              onTap: () => _setTip(_tip == t ? 0 : t),
                            ),
                          _TipChip(
                            label: 'Other',
                            on: _tip > 0 &&
                                ![10.0, 20.0, 30.0, 50.0].contains(_tip),
                            onTap: _pickCustomTip,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                // -- payment method ----------------------------------
                GlassPanel(
                  radius: 16,
                  onTap: _pickPayment,
                  padding: const EdgeInsets.all(14),
                  child: Row(
                    children: [
                      Icon(Icons.account_balance_wallet_rounded,
                          size: 18, color: p.accent),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Payment',
                                style: TextStyle(
                                    color: p.inkFaint, fontSize: 11)),
                            const SizedBox(height: 2),
                            Text(
                              _payment.label,
                              style: TextStyle(
                                  color: p.ink,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800),
                            ),
                          ],
                        ),
                      ),
                      Text('Change',
                          style: TextStyle(
                              color: p.accent,
                              fontWeight: FontWeight.w800,
                              fontSize: 12.5)),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                // -- free-delivery progress -----------------------------
                if (fdThreshold > 0 && fdRemaining > 0.01) ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: p.accent.withOpacity(0.08),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: p.accent.withOpacity(0.3)),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.delivery_dining_rounded,
                            size: 18, color: p.accent),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Add ${formatCurrency(context, fdRemaining)} more '
                            'to get FREE delivery',
                            style: TextStyle(
                                color: p.accent,
                                fontSize: 12,
                                fontWeight: FontWeight.w800),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                ] else if (freeDelivery) ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: p.positive.withOpacity(0.10),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: p.positive.withOpacity(0.35)),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.check_circle_rounded,
                            size: 18, color: p.positive),
                        const SizedBox(width: 10),
                        Text('Free delivery applied 🎉',
                            style: TextStyle(
                                color: p.positive,
                                fontSize: 12.5,
                                fontWeight: FontWeight.w900)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                // -- bill ------------------------------------------
                GlassPanel(
                  radius: 18,
                  padding: const EdgeInsets.all(16),
                  child: _loadingSummary
                      ? Center(
                          child: SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: p.accent),
                          ),
                        )
                      : Column(
                          children: [
                            _bill(context, 'Item total', subtotal),
                            if (freeDelivery)
                              _freeDeliveryRow(context, deliveryOriginal)
                            else if (delivery != 0)
                              _bill(context, 'Delivery fee', delivery),
                            if (platform != 0)
                              _bill(context, 'Platform fee', platform),
                            if (surge != 0)
                              _bill(context, 'Surge fee', surge),
                            if (night != 0)
                              _bill(context, 'Late-night fee', night),
                            if (tax != 0) _bill(context, 'Taxes', tax),
                            if (discount != 0)
                              _bill(context, 'Discount', -discount,
                                  positive: true),
                            if (_tip != 0)
                              _bill(context, 'Delivery tip', _tip),
                            Divider(color: p.glassBorder, height: 22),
                            _bill(context, 'To pay', total, bold: true),
                            if (pointsMsg.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  Icon(Icons.stars_rounded,
                                      size: 13, color: p.accent),
                                  const SizedBox(width: 6),
                                  Expanded(
                                    child: Text(pointsMsg,
                                        style: TextStyle(
                                            color: p.accent,
                                            fontSize: 11,
                                            fontWeight: FontWeight.w700)),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                ),
                const SizedBox(height: 12),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.info_outline_rounded,
                        size: 13, color: p.inkFaint),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'Orders can be cancelled only before the restaurant '
                        'starts preparing. Refunds follow the active refund '
                        'policy.',
                        style: TextStyle(
                            color: p.inkFaint, fontSize: 10.5, height: 1.4),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          _PlaceBar(
            total: total,
            busy: _placing,
            label: _payment.method == 'online' ? 'Review & pay' : 'Review order',
            onTap: _placing ? null : () => _reviewOrder(total),
          ),
        ],
      ),
    );
  }

  Widget _freeDeliveryRow(BuildContext context, double original) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 5),
      child: Row(
        children: [
          Expanded(
            child: Text('Delivery fee',
                style: TextStyle(
                    color: p.inkSoft,
                    fontWeight: FontWeight.w600,
                    fontSize: 12.5)),
          ),
          if (original > 0) ...[
            Text(formatCurrency(context, original),
                style: TextStyle(
                    color: p.inkFaint,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                    decoration: TextDecoration.lineThrough)),
            const SizedBox(width: 6),
          ],
          Text('FREE',
              style: TextStyle(
                  color: p.positive,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }

  Widget _bill(BuildContext context, String label, double value,
      {bool bold = false, bool positive = false}) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 5),
      child: Row(
        children: [
          Expanded(
            child: Text(label,
                style: TextStyle(
                  color: bold ? p.ink : p.inkSoft,
                  fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
                  fontSize: bold ? 14.5 : 12.5,
                )),
          ),
          Text(
            formatCurrency(context, value),
            style: TextStyle(
              color: positive ? p.positive : (bold ? p.ink : p.inkSoft),
              fontWeight: bold ? FontWeight.w900 : FontWeight.w700,
              fontSize: bold ? 14.5 : 12.5,
            ),
          ),
        ],
      ),
    );
  }
}

/// Small rounded thumbnail for a line item in the checkout bill.
class _CheckoutThumb extends StatelessWidget {
  const _CheckoutThumb({required this.url, required this.isVeg});
  final String url;
  final bool isVeg;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return SizedBox(
      width: 44,
      height: 44,
      child: Stack(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(11),
            child: url.trim().isEmpty
                ? Container(
                    width: 44,
                    height: 44,
                    color: p.glassTop,
                    child: Icon(Icons.restaurant_rounded,
                        size: 18, color: p.inkFaint),
                  )
                : AppCachedImage(
                    imageUrl: url,
                    width: 44,
                    height: 44,
                    fit: BoxFit.cover,
                    errorWidget: Container(
                      width: 44,
                      height: 44,
                      color: p.glassTop,
                      child: Icon(Icons.restaurant_rounded,
                          size: 18, color: p.inkFaint),
                    ),
                  ),
          ),
          Positioned(
            left: 2,
            top: 2,
            child: Container(
              padding: const EdgeInsets.all(2),
              decoration: BoxDecoration(
                color: p.bgTop,
                borderRadius: BorderRadius.circular(4),
              ),
              child: VegDot(isVeg: isVeg, size: 9),
            ),
          ),
        ],
      ),
    );
  }
}

class _TipChip extends StatelessWidget {
  const _TipChip(
      {required this.label, required this.on, required this.onTap});
  final String label;
  final bool on;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 140),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
        decoration: BoxDecoration(
          color: on ? p.accent : p.accent.withOpacity(0.08),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
              color: on ? p.accent : p.accent.withOpacity(0.4)),
        ),
        child: Text(label,
            style: TextStyle(
                color: on ? Colors.white : p.accent,
                fontSize: 12.5,
                fontWeight: FontWeight.w900)),
      ),
    );
  }
}

class _MiniStepper extends StatelessWidget {
  const _MiniStepper(
      {required this.qty, required this.onAdd, required this.onRemove});
  final int qty;
  final VoidCallback onAdd;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      decoration: BoxDecoration(
        color: p.accent.withOpacity(0.1),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: p.accent.withOpacity(0.4)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          V2Tappable(
            onTap: onRemove,
            child: Padding(
              padding: const EdgeInsets.all(4),
              child: Icon(Icons.remove_rounded, size: 14, color: p.accent),
            ),
          ),
          Text('$qty',
              style: TextStyle(
                  color: p.accent,
                  fontWeight: FontWeight.w900,
                  fontSize: 12)),
          V2Tappable(
            onTap: onAdd,
            child: Padding(
              padding: const EdgeInsets.all(4),
              child: Icon(Icons.add_rounded, size: 14, color: p.accent),
            ),
          ),
        ],
      ),
    );
  }
}

class _SuggestionCard extends StatelessWidget {
  const _SuggestionCard({required this.data, required this.restaurant});
  final Map<String, dynamic> data;
  final Restaurant restaurant;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final price = v2Double(data['discounted_price'] ?? data['price']);
    final img = v2ImageUrl(data, kDishImageKeys);
    return SizedBox(
      width: 132,
      child: GlassPanel(
        radius: 16,
        padding: const EdgeInsets.all(8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(11),
              child: img.isEmpty
                  ? Container(width: 116, height: 66, color: p.glassTop)
                  : AppCachedImage(
                      imageUrl: img,
                      width: 116,
                      height: 66,
                      fit: BoxFit.cover),
            ),
            const SizedBox(height: 6),
            Text((data['name'] ?? 'Item').toString(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                    color: p.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w800)),
            const SizedBox(height: 4),
            Row(
              children: [
                Text(formatCurrency(context, price),
                    style: TextStyle(
                        color: p.accent,
                        fontSize: 11.5,
                        fontWeight: FontWeight.w800)),
                const Spacer(),
                V2Tappable(
                  onTap: () {
                    try {
                      v2AddToCart(
                          context, MenuItem.fromJson(data), restaurant);
                    } catch (_) {}
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: p.accent,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text('ADD',
                        style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w900)),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PlaceBar extends StatelessWidget {
  const _PlaceBar({
    required this.total,
    required this.busy,
    required this.label,
    required this.onTap,
  });
  final double total;
  final bool busy;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      padding: EdgeInsets.fromLTRB(
          16, 12, 16, MediaQuery.of(context).padding.bottom + 14),
      decoration: BoxDecoration(
        color: p.isDark ? const Color(0xF2121720) : Colors.white,
        border: Border(top: BorderSide(color: p.glassBorder)),
      ),
      child: Row(
        children: [
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('To pay',
                  style: TextStyle(color: p.inkFaint, fontSize: 11)),
              Text(formatCurrency(context, total),
                  style: TextStyle(
                      color: p.ink,
                      fontSize: 18,
                      fontWeight: FontWeight.w900)),
            ],
          ),
          const Spacer(),
          V2Tappable(
            onTap: onTap,
            child: Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
              decoration: BoxDecoration(
                color: onTap == null ? p.inkFaint : p.accent,
                borderRadius: BorderRadius.circular(15),
              ),
              child: busy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : Text(label,
                      style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 14)),
            ),
          ),
        ],
      ),
    );
  }
}
