import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:lottie/lottie.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../models/menu_item.dart';
import '../../models/order.dart';
import '../../models/user.dart';
import '../../models/address.dart' as app_address;
import '../../providers/auth_provider.dart';
import '../../providers/cart_provider.dart';
import '../../providers/order_provider.dart';
import '../../services/api_service.dart';
import '../../services/flexible_order_payment_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../utils/promotion_display_utils.dart';
import '../../utils/promotion_summary_utils.dart';
import '../../widgets/common/lucide_icon.dart';
import '../../widgets/customer/cart_item_card.dart';
import '../../widgets/customer/account_chrome.dart';
import '../../widgets/customer/free_delivery_success_popup.dart';
import '../../widgets/customer/menu_item_card.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({
    super.key,
    this.onBrowseRestaurants,
    this.onAddMore,
  });

  final VoidCallback? onBrowseRestaurants;
  final VoidCallback? onAddMore;

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final ApiService _api = ApiService();
  final FlexibleOrderPaymentService _paymentService =
      FlexibleOrderPaymentService();

  TextTheme? _cachedBaseTextTheme;
  TextTheme? _cachedJakartaTextTheme;
  TextTheme? _cachedBasePrimaryTextTheme;
  TextTheme? _cachedJakartaPrimaryTextTheme;

  TextTheme _jakartaTextTheme(TextTheme base) {
    if (identical(_cachedBaseTextTheme, base) &&
        _cachedJakartaTextTheme != null) {
      return _cachedJakartaTextTheme!;
    }
    _cachedBaseTextTheme = base;
    _cachedJakartaTextTheme = GoogleFonts.plusJakartaSansTextTheme(base);
    return _cachedJakartaTextTheme!;
  }

  TextTheme _jakartaPrimaryTextTheme(TextTheme base) {
    if (identical(_cachedBasePrimaryTextTheme, base) &&
        _cachedJakartaPrimaryTextTheme != null) {
      return _cachedJakartaPrimaryTextTheme!;
    }
    _cachedBasePrimaryTextTheme = base;
    _cachedJakartaPrimaryTextTheme = GoogleFonts.plusJakartaSansTextTheme(base);
    return _cachedJakartaPrimaryTextTheme!;
  }

  bool _isSummaryLoading = false;
  double? _summarySubtotal;
  double? _summaryOriginalSubtotal;
  double? _summaryDeliveryFee;
  double? _summaryDeliveryDiscount;
  double? _summaryPlatformFee;
  double? _summaryTax;
  double? _summaryDiscount;
  double? _summaryEmbeddedItemDiscount;
  double? _summaryTotal;
  double? _freeDeliveryThreshold;
  double? _freeDeliveryRemaining;
  double? _deliveryDistanceKm;
  String _summaryTaxLabel = 'Taxes';
  List<_ChargeBreakdownItem> _summaryTaxBreakdown = [];
  String? _lastCartSignature;
  String? _summaryCartSignature;
  String? _inFlightSummarySignature;
  int _summaryRequestId = 0;
  Timer? _summaryDebounce;
  List<app_address.Address> _addresses = [];
  app_address.Address? _selectedAddress;
  String? _confirmedDeliveryLocationKey;
  bool _isAddressLoading = false;
  int? _loadedAddressRestaurantId;
  List<Map<String, dynamic>> _cartPromos = <Map<String, dynamic>>[];
  int? _loadedPromoRestaurantId;
  int? _loadingPromoRestaurantId;
  List<Map<String, dynamic>> _promotionProgress = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _rewardLines = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _rewardActions = <Map<String, dynamic>>[];
  double _cashbackEarned = 0;
  int _rewardPointsEarned = 0;
  List<MenuItem> _suggestedItems = <MenuItem>[];
  String? _loadedSuggestedSignature;
  String? _loadingSuggestedSignature;
  String _cartNote = '';
  String _selectedOrderType = 'delivery';
  String _selectedPaymentMethod = 'online';
  String? _selectedGatewayKey;
  String _selectedOnlinePaymentMode = 'upi';
  String? _selectedCashfreeUpiAppId;
  String? _selectedCashfreeUpiAppName;
  List<CashfreeUpiApp> _cashfreeUpiApps = const [];
  bool _isLoadingCashfreeUpiApps = false;
  bool _hasLoadedCashfreeUpiApps = false;
  String _selectedCouponCode = '';
  DateTime? _scheduledTime;
  double _walletBalance = 0;
  bool _isPlacingOrder = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refreshCartSummary());
    _loadWalletBalance();
  }

  @override
  void dispose() {
    _summaryDebounce?.cancel();
    _paymentService.dispose();
    super.dispose();
  }

  Future<void> _refreshCartSummary() async {
    final cart = context.read<CartProvider>();
    final restaurant = cart.restaurant;
    if (restaurant == null || cart.paidItems.isEmpty) {
      if (!mounted) return;
      setState(() {
        _summarySubtotal = null;
        _summaryOriginalSubtotal = null;
        _summaryDeliveryFee = null;
        _summaryDeliveryDiscount = null;
        _summaryPlatformFee = null;
        _summaryTax = null;
        _summaryDiscount = null;
        _summaryEmbeddedItemDiscount = null;
        _summaryTotal = null;
        _freeDeliveryThreshold = null;
        _freeDeliveryRemaining = null;
        _deliveryDistanceKm = null;
        _summaryTaxBreakdown = [];
        _summaryCartSignature = null;
        _addresses = [];
        _selectedAddress = null;
        _confirmedDeliveryLocationKey = null;
        _loadedAddressRestaurantId = null;
        _cartPromos = <Map<String, dynamic>>[];
        _loadedPromoRestaurantId = null;
        _loadingPromoRestaurantId = null;
        _promotionProgress = <Map<String, dynamic>>[];
        _rewardLines = <Map<String, dynamic>>[];
        _rewardActions = <Map<String, dynamic>>[];
        _cashbackEarned = 0;
        _rewardPointsEarned = 0;
        _suggestedItems = <MenuItem>[];
        _loadedSuggestedSignature = null;
        _loadingSuggestedSignature = null;
      });
      return;
    }

    String? requestSignature;
    int? requestId;
    try {
      final orderType = _effectiveOrderType(restaurant);
      final address = orderType == 'delivery'
          ? await _loadAddressesForCart(restaurant.id)
          : _selectedAddress;
      if (!mounted ||
          cart.restaurant?.id != restaurant.id ||
          cart.paidItems.isEmpty) {
        return;
      }

      requestSignature = _cartSignature(cart);
      if (_inFlightSummarySignature == requestSignature) return;
      _inFlightSummarySignature = requestSignature;
      requestId = ++_summaryRequestId;
      setState(() => _isSummaryLoading = true);

      final cartCouponCode = cart.promotionCouponCode;
      final couponCode =
          _selectedCouponCode.isNotEmpty ? _selectedCouponCode : cartCouponCode;
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
        '[SwaadPromoCart] checkout summary request: restaurant=${restaurant.id}, '
        'signature=$requestSignature, type=$orderType, address=${address?.id}, '
        'items=$summaryItems',
      );
      final response = await _api.post(
        ApiConstants.orderSummary,
        data: {
          'restaurant_id': restaurant.id,
          'items': summaryItems,
          if (orderType == 'delivery') ...{
            'delivery_address_id': address?.id,
            'delivery_lat': address?.latitude,
            'delivery_lng': address?.longitude,
          },
          'order_type': orderType,
          if (couponCode.isNotEmpty) 'coupon_code': couponCode,
        },
        coalesce: true,
        reuseFor: const Duration(seconds: 10),
      );

      if (!mounted ||
          requestId != _summaryRequestId ||
          requestSignature != _cartSignature(context.read<CartProvider>())) {
        return;
      }
      if (response['success'] == true) {
        final data = Map<String, dynamic>.from(response['data'] ?? {});
        final threshold = _nullableDouble(data['free_delivery_threshold']);
        final remaining = _nullableDouble(data['free_delivery_remaining']);
        final celebrate = FreeDeliveryMilestoneTracker.shouldCelebrate(
          eligible: threshold != null,
          achieved: threshold != null && (remaining ?? 0) <= 0,
        );
        setState(() {
          _summarySubtotal = _toDouble(data['subtotal']);
          _summaryOriginalSubtotal = _toDouble(data['original_subtotal']);
          _summaryDeliveryFee = _toDouble(data['delivery_fee']);
          _summaryDeliveryDiscount = _toDouble(data['delivery_discount']);
          _summaryPlatformFee = _toDouble(data['platform_fee']);
          _summaryTax = _toDouble(data['tax']);
          _summaryDiscount = _toDouble(data['discount']);
          _summaryEmbeddedItemDiscount =
              _toDouble(data['embedded_item_discount']);
          _summaryTotal = _toDouble(data['total']);
          _promotionProgress = _mapList(data['promotion_progress']);
          _rewardLines = promotionRewardLinesFromSummary(data);
          _rewardActions = _mapList(data['reward_actions']);
          _cashbackEarned = _toDouble(data['cashback_earned']);
          _rewardPointsEarned = _toDouble(data['reward_points_earned']).round();
          _freeDeliveryThreshold = threshold;
          _freeDeliveryRemaining = remaining;
          _deliveryDistanceKm = _nullableDouble(data['delivery_distance_km'] ??
              (data['eta'] is Map
                  ? (data['eta'] as Map)['travel_distance_km']
                  : null));
          _summaryTaxLabel =
              data['tax_label']?.toString().trim().isNotEmpty == true
                  ? data['tax_label'].toString()
                  : 'Taxes';
          _summaryTaxBreakdown = _parseTaxBreakdown(data['tax_breakdown']);
          _summaryCartSignature = requestSignature;
        });
        debugPrint(
          '[SwaadPromoCart] checkout summary response: '
          'rewardLines=${_rewardLines.length}, '
          'rewards=${_rewardLineDebug(_rewardLines)}, '
          'actions=${_rewardActions.length}, progress=${_promotionProgress.length}, '
          '${_summaryDebug(data)}',
        );
        unawaited(_loadCartPromos(restaurant.id));
        unawaited(_loadSuggestedItems());
        if (celebrate) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) showFreeDeliverySuccessPopup(context);
          });
        }
      }
    } catch (e) {
      debugPrint('Cart summary error: $e');
      debugPrint('[SwaadPromoCart] checkout summary failed: $e');
    } finally {
      if (requestSignature != null &&
          _inFlightSummarySignature == requestSignature) {
        _inFlightSummarySignature = null;
      }
      if (mounted && requestId != null && requestId == _summaryRequestId) {
        setState(() => _isSummaryLoading = false);
      }
    }
  }

  void _queueCartSummaryRefresh(
      {Duration delay = const Duration(milliseconds: 80)}) {
    _summaryDebounce?.cancel();
    _summaryDebounce = Timer(delay, () {
      if (!mounted) return;
      _refreshCartSummary();
    });
  }

  Future<app_address.Address?> _loadAddressesForCart(
    int restaurantId, {
    bool force = false,
  }) async {
    if (!force &&
        _loadedAddressRestaurantId == restaurantId &&
        _addresses.isNotEmpty) {
      return _selectedAddress;
    }

    if (mounted) setState(() => _isAddressLoading = true);
    try {
      final response = await _api.get(
        ApiConstants.addresses,
        queryParams: {'restaurant_id': restaurantId},
        cacheResponse: true,
        cacheFirst: !force,
        refreshCached: true,
        cachePolicy: ApiCachePolicy.screen,
      );
      if (response['success'] != true || response['data'] is! List) {
        return _selectedAddress;
      }

      final addresses = (response['data'] as List)
          .whereType<Map>()
          .map((json) =>
              app_address.Address.fromJson(Map<String, dynamic>.from(json)))
          .toList(growable: false);

      addresses.sort((a, b) {
        if (a.isDeliverable != b.isDeliverable) {
          return a.isDeliverable ? -1 : 1;
        }
        if (a.isDefault != b.isDefault) {
          return a.isDefault ? -1 : 1;
        }
        return a.name.compareTo(b.name);
      });

      final previousSelectedId = _selectedAddress?.id;
      app_address.Address? selected;
      if (previousSelectedId != null) {
        for (final address in addresses) {
          if (address.id == previousSelectedId) {
            selected = address;
            break;
          }
        }
      }
      if (selected == null && addresses.isNotEmpty) {
        final defaultAddress = addresses.firstWhere(
          (address) => address.isDefault,
          orElse: () => addresses.first,
        );
        selected = addresses.firstWhere(
          (address) => address.isDeliverable,
          orElse: () => defaultAddress,
        );
      }

      if (!mounted) return selected;
      setState(() {
        final previousConfirmedKey = _confirmedDeliveryLocationKey;
        final nextKey =
            selected == null ? null : _deliveryLocationKey(selected);
        _addresses = addresses;
        _setSelectedAddress(
          selected,
          confirmed:
              previousConfirmedKey != null && previousConfirmedKey == nextKey,
        );
        _loadedAddressRestaurantId = restaurantId;
      });
      return selected;
    } finally {
      if (mounted) setState(() => _isAddressLoading = false);
    }
  }

  String _deliveryLocationKey(app_address.Address address) {
    final lat = address.latitude?.toStringAsFixed(6) ?? '';
    final lng = address.longitude?.toStringAsFixed(6) ?? '';
    return '${address.id}:$lat:$lng';
  }

  bool get _isSelectedDeliveryLocationConfirmed {
    final address = _selectedAddress;
    return address != null &&
        _confirmedDeliveryLocationKey == _deliveryLocationKey(address);
  }

  void _setSelectedAddress(
    app_address.Address? address, {
    bool confirmed = false,
  }) {
    _selectedAddress = address;
    _confirmedDeliveryLocationKey =
        address != null && confirmed ? _deliveryLocationKey(address) : null;
  }

  app_address.Address _addressWithPinnedLocation(
    app_address.Address current,
    app_address.Address pinned,
  ) {
    return app_address.Address(
      id: current.id,
      userId: current.userId,
      name: current.name,
      address: current.address,
      city: current.city,
      state: current.state,
      pincode: current.pincode,
      phone: current.phone,
      latitude: pinned.latitude ?? current.latitude,
      longitude: pinned.longitude ?? current.longitude,
      isDefault: current.isDefault,
      distanceKm: current.distanceKm,
      isDeliverable: current.isDeliverable,
      deliveryStatusLabel: current.deliveryStatusLabel,
      createdAt: current.createdAt,
    );
  }

  Future<void> _openDeliveryPinPicker({app_address.Address? address}) async {
    final current = address ?? _selectedAddress;
    if (current == null) {
      await _addAddressAndRefresh(context.read<CartProvider>().restaurant?.id);
      return;
    }

    final result = await Navigator.pushNamed(
      context,
      '/map-picker',
      arguments: current,
    );
    if (!mounted || result is! app_address.Address) return;

    setState(() {
      _setSelectedAddress(
        _addressWithPinnedLocation(current, result),
        confirmed: true,
      );
    });
    await _refreshCartSummary();
  }

  Future<String?> _showConfirmLocationSheet(app_address.Address address) {
    final scheme = Theme.of(context).colorScheme;
    final primary = scheme.primary;
    final secondary = scheme.secondary;

    return showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
            child: Container(
              padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
              decoration: BoxDecoration(
                color: scheme.surface,
                borderRadius: BorderRadius.circular(26),
                boxShadow: [
                  BoxShadow(
                    color: scheme.shadow.withOpacity(0.08),
                    blurRadius: 26,
                    offset: const Offset(0, 14),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Confirm delivery location',
                          style: GoogleFonts.inter(
                            color: FoodFlowTheme.ink,
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(sheetContext),
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: primary.withOpacity(0.08),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: primary.withOpacity(0.16)),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.location_on_outlined,
                            color: primary, size: 24),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                address.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: FoodFlowTheme.ink,
                                  fontSize: 14,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                address.fullAddress,
                                maxLines: 3,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: FoodFlowTheme.inkSoft,
                                  fontSize: 12,
                                  height: 1.35,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => Navigator.pop(sheetContext, 'pin'),
                          style: FoodFlowTheme.zomatoOutlineButton(
                            color: secondary,
                            radius: 999,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                          ),
                          icon: const Icon(Icons.map_outlined, size: 18),
                          label: const Text('Pin on map'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () =>
                              Navigator.pop(sheetContext, 'confirm'),
                          style: FoodFlowTheme.zomatoPrimaryButton(
                            color: secondary,
                            radius: 999,
                            padding: const EdgeInsets.symmetric(vertical: 15),
                          ),
                          child: const Text('Confirm'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Future<bool> _confirmSelectedDeliveryLocation() async {
    final address = _selectedAddress;
    if (address == null) return false;
    if (_isSelectedDeliveryLocationConfirmed) return true;

    final action = await _showConfirmLocationSheet(address);
    if (!mounted) return false;

    if (action == 'confirm') {
      setState(() {
        _confirmedDeliveryLocationKey = _deliveryLocationKey(address);
      });
      return true;
    }

    if (action == 'pin') {
      await _openDeliveryPinPicker(address: address);
      return _isSelectedDeliveryLocationConfirmed;
    }

    return false;
  }

  Future<void> _loadCartPromos(int restaurantId) async {
    if (_loadedPromoRestaurantId == restaurantId ||
        _loadingPromoRestaurantId == restaurantId) {
      return;
    }

    _loadingPromoRestaurantId = restaurantId;
    try {
      final responses = await Future.wait([
        _api
            .get(
              ApiConstants.customerRestaurantPromos(restaurantId),
              cacheResponse: true,
              cacheFirst: true,
              cachePolicy: ApiCachePolicy.screen,
            )
            .timeout(const Duration(seconds: 10)),
        _api
            .get(
              ApiConstants.rewardCoupons,
              cacheResponse: true,
              cacheFirst: true,
              cachePolicy: ApiCachePolicy.screen,
            )
            .timeout(const Duration(seconds: 10)),
      ]);
      final response = responses.first;
      final rewardResponse = responses.last;
      final restaurantPromos = response['success'] == true
          ? PromotionDisplayUtils.activePromos(response['data'])
          : const <Map<String, dynamic>>[];
      final rewardPromos = _scratchRewardCouponPromos(
        rewardResponse,
        restaurantId,
      );
      final promos = _mergeCouponPromos(restaurantPromos, rewardPromos);
      if (!mounted) return;
      setState(() {
        _cartPromos = promos;
        _loadedPromoRestaurantId = restaurantId;
      });
    } catch (e) {
      debugPrint('Cart promo load error: $e');
      if (!mounted) return;
      setState(() {
        _cartPromos = <Map<String, dynamic>>[];
        _loadedPromoRestaurantId = restaurantId;
      });
    } finally {
      if (_loadingPromoRestaurantId == restaurantId) {
        _loadingPromoRestaurantId = null;
      }
    }
  }

  List<Map<String, dynamic>> _scratchRewardCouponPromos(
    dynamic response,
    int restaurantId,
  ) {
    if (response is! Map || response['success'] != true) {
      return const <Map<String, dynamic>>[];
    }

    final rows = response['data'] is List ? response['data'] as List : const [];
    return rows
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .where((coupon) => (coupon['status'] ?? '').toString() == 'unused')
        .map((coupon) {
          final promotion = coupon['promotion'] is Map
              ? Map<String, dynamic>.from(coupon['promotion'] as Map)
              : <String, dynamic>{};
          final promoRestaurantId =
              int.tryParse('${promotion['restaurant_id'] ?? ''}');
          final targets = promotion['targets'] is Map
              ? Map<String, dynamic>.from(promotion['targets'] as Map)
              : const <String, dynamic>{};
          final restaurantIds = (targets['restaurant_ids'] is List
                  ? targets['restaurant_ids'] as List
                  : const [])
              .map((value) => int.tryParse('$value') ?? 0)
              .where((value) => value > 0)
              .toSet();
          final appliesToRestaurant = promoRestaurantId == null ||
              promoRestaurantId == restaurantId ||
              restaurantIds.isEmpty ||
              restaurantIds.contains(restaurantId);
          if (!appliesToRestaurant) return null;

          final rewards = promotion['rewards'] is Map
              ? Map<String, dynamic>.from(promotion['rewards'] as Map)
              : const <String, dynamic>{};
          final conditions = promotion['conditions'] is Map
              ? Map<String, dynamic>.from(promotion['conditions'] as Map)
              : const <String, dynamic>{};
          return <String, dynamic>{
            ...promotion,
            'id': promotion['id'] ?? coupon['id'],
            'coupon_id': coupon['id'],
            'code': coupon['code'],
            'coupon_code': coupon['code'],
            'title': promotion['title'] ?? 'Scratch card reward',
            'description': promotion['description'] ??
                'Scratch card coupon. Apply at checkout.',
            'promotion_type': promotion['promotion_type'] ??
                rewards['type'] ??
                'free_delivery',
            'reward_type': rewards['type'] ??
                promotion['reward_type'] ??
                promotion['promotion_type'],
            'discount_type': promotion['promotion_type'],
            'discount_value': rewards['value'] ?? 0,
            'conditions': conditions,
            'rewards': rewards,
            'min_order_amount': conditions['min_order_amount'],
            'min_order_value':
                conditions['min_order_value'] ?? conditions['min_order_amount'],
            'expires_at': coupon['expires_at'] ?? promotion['ends_at'],
            'end_date': coupon['expires_at'] ?? promotion['ends_at'],
            'source_type': 'scratch_card_reward',
            'is_active': true,
            'status': 'active',
          };
        })
        .whereType<Map<String, dynamic>>()
        .toList(growable: false);
  }

  List<Map<String, dynamic>> _mergeCouponPromos(
    List<Map<String, dynamic>> restaurantPromos,
    List<Map<String, dynamic>> rewardPromos,
  ) {
    final merged = <Map<String, dynamic>>[];
    final seen = <String>{};

    for (final promo in [...rewardPromos, ...restaurantPromos]) {
      final code = _promoCode(promo).toUpperCase();
      final key = code.isNotEmpty
          ? 'code:$code'
          : 'promo:${promo['source_type'] ?? ''}:${promo['id'] ?? merged.length}';
      if (seen.add(key)) {
        merged.add(promo);
      }
    }

    return merged;
  }

  Future<void> _loadSuggestedItems() async {
    final cart = context.read<CartProvider>();
    final restaurant = cart.restaurant;
    if (restaurant == null) return;

    final requestSignature = _cartSignature(cart);
    if (_loadedSuggestedSignature == requestSignature ||
        _loadingSuggestedSignature == requestSignature) {
      return;
    }

    _loadingSuggestedSignature = requestSignature;
    try {
      final response = await _api
          .get('${ApiConstants.restaurantDetails}/${restaurant.id}/menu');
      final data = response['data'] is Map ? response['data'] as Map : response;
      final rawItems = (data['menu_items'] ??
          data['items'] ??
          data['menu'] ??
          []) as List<dynamic>;
      final cartIds = cart.items.map((item) => item.menuItem.id).toSet();
      final matchingDietItems = rawItems
          .whereType<Map<String, dynamic>>()
          .map(MenuItem.fromJson)
          .where(
            (item) =>
                !cartIds.contains(item.id) &&
                _matchesSuggestionDiet(item, cart),
          )
          .toList(growable: false);
      final highlightedItems = matchingDietItems
          .where(
            (item) =>
                item.isBestseller ||
                item.isRecommended ||
                item.isCombo ||
                item.isNew,
          )
          .toList(growable: false);
      final items =
          (highlightedItems.isNotEmpty ? highlightedItems : matchingDietItems)
              .take(8)
              .toList(growable: false);
      if (mounted) {
        setState(() {
          _suggestedItems = items;
          _loadedSuggestedSignature = requestSignature;
        });
      }
    } catch (e) {
      debugPrint('Load suggested cart items error: $e');
    } finally {
      if (_loadingSuggestedSignature == requestSignature) {
        _loadingSuggestedSignature = null;
      }
    }
  }

  bool _matchesSuggestionDiet(MenuItem suggestion, CartProvider cart) {
    if (cart.items.isEmpty) return true;
    final allVeg = cart.items.every((item) => item.menuItem.isVeg);
    if (allVeg) return suggestion.isVeg;
    final allNonVeg = cart.items.every((item) => !item.menuItem.isVeg);
    if (allNonVeg) return !suggestion.isVeg;
    return true;
  }

  void _addSuggestedItem(CartProvider cart, MenuItem item) {
    final restaurant = cart.restaurant;
    if (restaurant == null) return;

    void add({
      MenuOption? variant,
      List<MenuOption> addOns = const [],
    }) {
      cart.addItem(
        item,
        restaurant,
        selectedVariant: variant,
        selectedAddOns: addOns,
      );
      setState(() {
        _suggestedItems.removeWhere((suggested) => suggested.id == item.id);
      });
      _queueCartSummaryRefresh();
    }

    if (item.hasCustomizations) {
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (_) => MenuCustomizationSheet(
          item: item,
          onAdd: (result) => add(
            variant: result.variant,
            addOns: result.addOns,
          ),
        ),
      );
      return;
    }

    add();
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  double? _nullableDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  List<Map<String, dynamic>> _mapList(dynamic value) {
    if (value is! List) return const <Map<String, dynamic>>[];
    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
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
      'charges=subtotal:${data['subtotal']}/delivery:${data['delivery_fee']}/deliveryDiscount:${data['delivery_discount']}/platform:${data['platform_fee']}/tax:${data['tax']}/total:${data['total']}',
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

  Map<String, dynamic>? _rewardLineForCartItem(CartItem item) {
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

  bool _rewardLineMatchesAnyCartItem(
    CartProvider cart,
    Map<String, dynamic> line,
  ) {
    return cart.items.any((item) => _rewardLineMatchesCartItem(line, item));
  }

  static bool _rewardLineMatchesCartItem(
    Map<String, dynamic> line,
    CartItem item,
  ) {
    final rewardItemId = line['menu_item_id'] ?? line['item_id'];
    return rewardItemId?.toString() == item.menuItem.id.toString();
  }

  bool _rewardLineIncludedInCart(Map<String, dynamic>? line) {
    if (line == null) return false;
    final value = line['included_in_cart'];
    if (value is bool) return value;
    return value?.toString() == '1' ||
        value?.toString().toLowerCase() == 'true';
  }

  int _rewardLineQuantity(Map<String, dynamic>? line) {
    final value = line?['quantity'] ?? line?['qty'];
    if (value is num) return value.toInt().clamp(1, 999);
    return (int.tryParse(value?.toString() ?? '') ?? 1).clamp(1, 999);
  }

  String _rewardLinePromotionTitle(Map<String, dynamic>? line) {
    final text =
        (line?['promotion_title'] ?? line?['title'])?.toString().trim();
    return text == null || text.isEmpty ? 'Promotion' : text;
  }

  int _paidQuantityForCartItem(CartItem item) {
    return item.quantity;
  }

  Set<int> _activeCartPromotionIds(CartProvider cart) {
    return cart.paidItems
        .map((item) => item.promotionId)
        .whereType<int>()
        .where((id) => id > 0)
        .toSet();
  }

  Set<String> _activeCartPromotionTitles(CartProvider cart) {
    return cart.paidItems
        .map((item) => item.promotionTitle?.trim().toLowerCase() ?? '')
        .where((title) => title.isNotEmpty)
        .toSet();
  }

  List<Map<String, dynamic>> _visiblePromotionProgress(CartProvider cart) {
    return _filterPromotionRowsForCart(cart, _promotionProgress);
  }

  List<Map<String, dynamic>> _visibleRewardActions(CartProvider cart) {
    return _filterPromotionRowsForCart(cart, _rewardActions);
  }

  List<Map<String, dynamic>> _filterPromotionRowsForCart(
    CartProvider cart,
    List<Map<String, dynamic>> rows,
  ) {
    final activeIds = _activeCartPromotionIds(cart);
    if (activeIds.isEmpty) return rows;

    final activeTitles = _activeCartPromotionTitles(cart);
    return rows.where((row) {
      final rowId = _promotionRowId(row);
      if (rowId != null && activeIds.contains(rowId)) return true;

      final title = (row['promotion_title'] ??
              row['title'] ??
              row['name'] ??
              row['message'] ??
              '')
          .toString()
          .trim()
          .toLowerCase();
      return title.isNotEmpty &&
          activeTitles.any((activeTitle) =>
              title.contains(activeTitle) || activeTitle.contains(title));
    }).toList(growable: false);
  }

  int? _promotionRowId(Map<String, dynamic> row) {
    final value = row['promotion_id'] ?? row['id'];
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  String _activePromotionTitle({bool hasFreshSummary = true}) {
    final cart = context.read<CartProvider>();
    final cartTitle = cart.paidItems
        .map((item) => item.promotionTitle?.trim() ?? '')
        .firstWhere((title) => title.isNotEmpty, orElse: () => '');
    if (cartTitle.isNotEmpty) return cartTitle;

    if (hasFreshSummary && _rewardLines.isNotEmpty) {
      return _rewardLinePromotionTitle(_rewardLines.first);
    }

    if (hasFreshSummary) {
      for (final progress in _visiblePromotionProgress(cart)) {
        if (progress['unlocked'] == true) {
          final title =
              (progress['title'] ?? progress['name'])?.toString().trim();
          if (title != null && title.isNotEmpty) return title;
        }
      }
    }

    if (_cartPromos.isNotEmpty) {
      final title = (_cartPromos.first['title'] ?? _cartPromos.first['name'])
          ?.toString()
          .trim();
      if (title != null && title.isNotEmpty) return title;
    }

    return 'promotion';
  }

  String _cartSavingsText(double discount, {required bool hasFreshSummary}) {
    if (discount > 0) {
      return 'You saved ${formatCurrency(context, discount)} with ${_activePromotionTitle(hasFreshSummary: hasFreshSummary)}';
    }
    if (hasFreshSummary && _rewardLines.isNotEmpty) {
      return '${_activePromotionTitle(hasFreshSummary: true)} applied';
    }
    return 'Promotion available';
  }

  List<_ChargeBreakdownItem> _parseTaxBreakdown(dynamic value) {
    if (value is! List) return const <_ChargeBreakdownItem>[];
    return value
        .whereType<Map>()
        .map((item) => _ChargeBreakdownItem(
              label: item['name']?.toString().trim() ?? 'Tax',
              amount: _toDouble(item['amount']),
              rate: _nullableDouble(item['rate']),
              description: item['description']?.toString().trim(),
            ))
        .where((item) => item.amount > 0)
        .toList(growable: false);
  }

  List<_ChargeBreakdownItem> _taxAndChargesBreakdown() {
    return <_ChargeBreakdownItem>[
      ..._summaryTaxBreakdown,
    ];
  }

  void _showTaxBreakdownPopup(double tax) {
    final charges = _taxAndChargesBreakdown();
    showDialog<void>(
      context: context,
      barrierColor: Colors.black.withOpacity(0.18),
      builder: (dialogContext) => _TaxBreakdownDialog(charges: charges),
    );
  }

  String _cartSignature(CartProvider cart) {
    final restaurant = cart.restaurant;
    final restaurantId = restaurant?.id ?? 0;
    final itemSignature = cart.paidItems
        .map((item) => '${item.signature}:${item.quantity}')
        .join('|');
    final orderType = _effectiveOrderType(restaurant);
    final address = _selectedAddress;
    final addressKey = orderType == 'delivery' && address != null
        ? _deliveryLocationKey(address)
        : '0';
    return '$restaurantId::$orderType::$addressKey::${_selectedCouponCode.trim()}::$itemSignature';
  }

  String _promotionTagForMenuItem(MenuItem item) {
    return PromotionDisplayUtils.tagForMenuItem(
      item,
      _cartPromos,
      currencySymbol: getCurrencySymbol(context),
    );
  }

  List<String> _cartPromotionLabels(CartProvider cart) {
    final seen = <String>{};
    return cart.items
        .map((item) => _promotionTagForMenuItem(item.menuItem))
        .where((label) => label.isNotEmpty && seen.add(label))
        .take(3)
        .toList(growable: false);
  }

  void _scheduleSummaryRefreshIfNeeded(CartProvider cart) {
    if (cart.isEmpty) {
      _lastCartSignature = null;
      return;
    }
    final signature = _cartSignature(cart);
    if (signature == _lastCartSignature) return;
    _lastCartSignature = signature;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _queueCartSummaryRefresh();
    });
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final cart = Provider.of<CartProvider>(context);
    final currentCartSignature = _cartSignature(cart);
    final hasFreshSummary = _summaryCartSignature == currentCartSignature;
    _scheduleSummaryRefreshIfNeeded(cart);

    if (cart.isEmpty) {
      return _EmptyCartView(onBrowseRestaurants: widget.onBrowseRestaurants);
    }

    final restaurant = cart.restaurant;
    final orderType = _effectiveOrderType(restaurant);
    final isTakeaway = orderType == 'takeaway';
    final localPromotionDiscount = cart.promotionDisplayDiscount;
    // While a fresh re-sync is in flight (item added, quantity changed,
    // address/coupon changed), keep showing the last fetched delivery
    // fee/platform fee/tax for this same restaurant+order-type instead of
    // snapping to the crude local cart defaults (which are hardcoded to 0
    // for platform fee/tax) — that snap-then-correct was the visible
    // "sync" flicker on every quantity change.
    final canReuseChargeSummary = !hasFreshSummary &&
        _summaryCartSignature != null &&
        _summaryCartSignature!
            .startsWith('${restaurant?.id ?? 0}::$orderType::');
    final subtotal =
        hasFreshSummary ? (_summarySubtotal ?? cart.subtotal) : cart.subtotal;
    final originalSubtotal = hasFreshSummary
        ? (_summaryOriginalSubtotal ?? subtotal)
        : cart.subtotal;
    final deliveryFee = isTakeaway
        ? (hasFreshSummary ? (_summaryDeliveryFee ?? 0) : 0.0)
        : (hasFreshSummary || canReuseChargeSummary
            ? (_summaryDeliveryFee ?? cart.deliveryFee)
            : cart.deliveryFee);
    final deliveryDiscount = isTakeaway
        ? 0.0
        : (hasFreshSummary || canReuseChargeSummary
            ? (_summaryDeliveryDiscount ?? 0)
            : 0.0);
    final platformFee = hasFreshSummary || canReuseChargeSummary
        ? (_summaryPlatformFee ?? cart.platformFee)
        : cart.platformFee;
    final tax = hasFreshSummary || canReuseChargeSummary
        ? (_summaryTax ?? cart.tax)
        : cart.tax;
    final discount =
        hasFreshSummary ? (_summaryDiscount ?? 0) : localPromotionDiscount;
    final savedOnOrder =
        (discount + (hasFreshSummary ? (_summaryEmbeddedItemDiscount ?? 0) : 0))
            .clamp(0.0, double.infinity)
            .toDouble();
    final calculatedTotal =
        subtotal + deliveryFee + platformFee + tax - discount;
    final total =
        hasFreshSummary ? (_summaryTotal ?? calculatedTotal) : calculatedTotal;
    final deliveryLabel = isTakeaway
        ? 'Pickup fee'
        : _deliveryDistanceKm != null
            ? 'Delivery fee (${_deliveryDistanceKm!.toStringAsFixed(_deliveryDistanceKm! >= 10 ? 0 : 1)} km)'
            : 'Delivery fee';
    final promotionLabels = _cartPromotionLabels(cart);
    final visiblePromotionProgress = _visiblePromotionProgress(cart);
    final visibleRewardActions = _visibleRewardActions(cart);
    final hasPromotionWorkflow = promotionLabels.isNotEmpty ||
        _cartPromos.isNotEmpty ||
        (hasFreshSummary &&
            (visiblePromotionProgress.isNotEmpty ||
                _rewardLines.isNotEmpty ||
                visibleRewardActions.isNotEmpty ||
                _cashbackEarned > 0 ||
                _rewardPointsEarned > 0));

    final baseTheme = Theme.of(context);
    final cartTheme = baseTheme.copyWith(
      textTheme: _jakartaTextTheme(baseTheme.textTheme),
      primaryTextTheme: _jakartaPrimaryTextTheme(baseTheme.primaryTextTheme),
    );

    return Theme(
      data: cartTheme,
      child: Scaffold(
        backgroundColor: const Color(0xFFF5F6FB),
        body: SafeArea(
          bottom: false,
          child: CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: _buildCartHeader(primary, cart, restaurant, orderType),
              ),
              if (savedOnOrder > 0)
                SliverToBoxAdapter(
                  child: _buildTopSavingsStrip(savedOnOrder),
                ),
              if (!isTakeaway && _freeDeliveryThreshold != null)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(
                      10,
                      discount > 0 ? 10 : 12,
                      10,
                      0,
                    ),
                    child: _buildFreeDeliveryMilestone(primary, subtotal),
                  ),
                ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(10, 10, 10, 0),
                sliver: SliverList(
                  delegate: SliverChildListDelegate(
                    [
                      if (hasPromotionWorkflow) ...[
                        _buildSpecialOfferPanel(
                          discount,
                          savedOnOrder: savedOnOrder,
                          hasFreshSummary: hasFreshSummary,
                        ),
                        const SizedBox(height: 12),
                      ],
                      _buildItemsPanel(
                        cart,
                        hasFreshSummary: hasFreshSummary,
                      ),
                      if (_suggestedItems.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        _buildSuggestedItemsPanel(cart),
                      ],
                      if (hasPromotionWorkflow) ...[
                        const SizedBox(height: 12),
                        _buildCartPromotionSummary(
                          primary,
                          promotionLabels,
                          discount: discount,
                          savedOnOrder: savedOnOrder,
                          hasFreshSummary: hasFreshSummary,
                          promotionProgress: visiblePromotionProgress,
                          rewardActions: visibleRewardActions,
                        ),
                      ],
                      const SizedBox(height: 12),
                      _buildDeliveryBillPanel(
                        primary: primary,
                        restaurant: restaurant,
                        orderType: orderType,
                        restaurantId: restaurant?.id,
                        subtotal: subtotal,
                        originalSubtotal: originalSubtotal,
                        deliveryFee: deliveryFee,
                        deliveryDiscount: deliveryDiscount,
                        deliveryLabel: deliveryLabel,
                        platformFee: platformFee,
                        tax: tax,
                        discount: discount,
                        total: total,
                      ),
                      const SizedBox(height: 16),
                      _buildCancellationPolicy(),
                      const SizedBox(height: 120),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        bottomNavigationBar: _buildBottomCheckoutBar(primary, cart, total),
      ),
    );
  }

  Widget _buildCartHeader(
    Color primary,
    CartProvider cart,
    dynamic restaurant,
    String orderType,
  ) {
    final restaurantName = _checkoutRestaurantDisplayName(cart, restaurant);
    final address = _selectedAddress;
    final isTakeaway = orderType == 'takeaway';
    final eta = _scheduledTime == null
        ? (isTakeaway
            ? _pickupEtaText(restaurant)
            : (restaurant?.deliveryEtaLabel?.toString() ?? ''))
        : _formatScheduledTime(_scheduledTime!);
    final destination = isTakeaway
        ? 'pickup'
        : address?.name.trim().isNotEmpty == true
            ? address!.name
            : address?.city.trim().isNotEmpty == true
                ? address!.city
                : '';

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(4, 6, 8, 10),
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.of(context).maybePop(),
            icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
            color: FoodFlowTheme.ink,
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  restaurantName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                RichText(
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  text: TextSpan(
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: FoodFlowTheme.ink,
                    ),
                    children: [
                      if (eta.isNotEmpty)
                        TextSpan(
                          text: '$eta ',
                          style: const TextStyle(color: Color(0xFF059669)),
                        ),
                      if (destination.isNotEmpty)
                        TextSpan(
                            text: isTakeaway ? 'for pickup' : 'to $destination')
                      else
                        TextSpan(
                          text:
                              '${cart.itemCount} item${cart.itemCount == 1 ? '' : 's'}',
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: () => _showClearCartDialog(context, cart),
            icon: const Icon(Icons.delete_outline_rounded, size: 21),
            color: FoodFlowTheme.inkSoft,
          ),
        ],
      ),
    );
  }

  String _checkoutRestaurantDisplayName(CartProvider cart, dynamic restaurant) {
    final activeId = cart.restaurant?.id ?? restaurant?.id ?? 0;
    final names = <String>[
      restaurant?.name?.toString() ?? '',
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
    return 'Cart';
  }

  Widget _buildTopSavingsStrip(double discount) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
      color: const Color(0xFFEFFCF3),
      child: Row(
        children: [
          const Icon(Icons.local_offer_rounded,
              color: Color(0xFF16A34A), size: 16),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'You saved ${formatCurrency(context, discount)} on this order',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Color(0xFF16A34A),
                fontSize: 12.5,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildItemsPanel(
    CartProvider cart, {
    required bool hasFreshSummary,
  }) {
    return _CartPanel(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
      child: Column(
        children: [
          ...cart.paidItems.map((item) {
            final rewardLine =
                hasFreshSummary ? _rewardLineForCartItem(item) : null;
            final freeQuantity = item.isPromotionReward
                ? item.quantity
                : (rewardLine == null ? 0 : _rewardLineQuantity(rewardLine));
            final paidQuantity = item.isPromotionReward
                ? 0
                : (hasFreshSummary
                    ? _paidQuantityForCartItem(item)
                    : item.quantity);

            return _buildCompactCartItemRow(
              cart: cart,
              item: item,
              paidQuantity: paidQuantity,
              freeQuantity: freeQuantity,
              freePromotionTitle:
                  freeQuantity > 0 ? _rewardLinePromotionTitle(rewardLine) : '',
            );
          }),
          if (hasFreshSummary)
            ..._rewardLines
                .where((line) => !_rewardLineMatchesAnyCartItem(cart, line))
                .map(_buildDetachedRewardRow),
          const SizedBox(height: 8),
          Row(
            children: [
              _CartActionPill(
                icon: Icons.add_rounded,
                label: 'Add more items',
                onTap: () => _addMoreItems(context),
                strong: true,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _CartActionPill(
                  icon: Icons.receipt_long_outlined,
                  label: _cartNote.isEmpty
                      ? 'Add a note for the restaurant'
                      : 'Note added',
                  onTap: _showCartNoteSheet,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// True once a promotion/coupon is actually applied to this cart — as
  /// opposed to merely being available to apply (e.g. an unused scratch
  /// card coupon sitting in [_cartPromos]).
  bool _hasAppliedPromotion(bool hasFreshSummary, double discount) {
    final cart = context.read<CartProvider>();
    final hasCartItemPromotion = cart.paidItems
        .any((item) => (item.promotionTitle ?? '').trim().isNotEmpty);
    if (hasCartItemPromotion) return true;
    if (discount > 0) return true;
    if (hasFreshSummary && _rewardLines.isNotEmpty) return true;
    if (_selectedCouponCode.isNotEmpty) return true;
    return false;
  }

  Widget _buildSpecialOfferPanel(
    double discount, {
    required double savedOnOrder,
    required bool hasFreshSummary,
  }) {
    final applied = _hasAppliedPromotion(hasFreshSummary, discount);

    if (!applied) {
      // A coupon is available (e.g. a scratch-card reward the customer
      // won) but hasn't been applied yet — prompt them to apply it instead
      // of showing a misleading "ADDED" badge for nothing.
      final availablePromo = _cartPromos.isNotEmpty ? _cartPromos.first : null;
      final isScratchWin =
          availablePromo != null && _isScratchRewardPromo(availablePromo);
      final promoTitle = availablePromo != null
          ? _promoTitle(availablePromo)
          : 'Coupon available for this order';

      return _CartPanel(
        padding: const EdgeInsets.fromLTRB(14, 14, 12, 12),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: _showPromoSelectorSheet,
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: const Color(0xFFEFFCF3),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(999),
                      border: const Border.fromBorderSide(
                        BorderSide(color: Color(0xFFBBF0CE)),
                      ),
                    ),
                    child: const Icon(
                      Icons.card_giftcard_rounded,
                      color: Color(0xFF16A34A),
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          isScratchWin
                              ? 'You won a coupon!'
                              : 'Coupon available for you',
                          style: const TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 13,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          promoTitle,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Color(0xFF16A34A),
                            fontSize: 12,
                            height: 1.2,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 7),
                    decoration: BoxDecoration(
                      color: const Color(0xFF16A34A),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Text(
                      'APPLY',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }

    final title = _activePromotionTitle(hasFreshSummary: hasFreshSummary);
    final rewardLine =
        hasFreshSummary && _rewardLines.isNotEmpty ? _rewardLines.first : null;
    final rewardName = rewardLine == null
        ? ''
        : (rewardLine['name'] ?? rewardLine['title'] ?? '').toString().trim();
    final support = rewardName.isNotEmpty
        ? '${_rewardLineQuantity(rewardLine)} free $rewardName'
        : savedOnOrder > 0
            ? 'Offer savings ${formatCurrency(context, savedOnOrder)}'
            : 'Eligible on your current cart';

    return _CartPanel(
      padding: const EdgeInsets.fromLTRB(14, 14, 12, 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: _showPromoSelectorSheet,
        child: DecoratedBox(
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFFFFF3E9), Color(0xFFFFFBF6)],
            ),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: const Color(0xFFFFCDD2)),
                  ),
                  child: const Icon(
                    Icons.card_giftcard_rounded,
                    color: Color(0xFFE83E58),
                    size: 24,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Special offer for you',
                        style: TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 13,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 9),
                      Text(
                        title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 13,
                          height: 1.2,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        support,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF2E7BE6),
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 7),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: FoodFlowTheme.brandPrimary(context),
                        ),
                      ),
                      child: Text(
                        'ADDED',
                        style: TextStyle(
                          color: FoodFlowTheme.brandPrimary(context),
                          fontSize: 12,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    if (discount > 0) ...[
                      const SizedBox(height: 8),
                      Text(
                        formatCurrency(context, discount),
                        style: TextStyle(
                          color: FoodFlowTheme.brandPrimary(context),
                          fontSize: 12,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCompactCartItemRow({
    required CartProvider cart,
    required CartItem item,
    required int paidQuantity,
    required int freeQuantity,
    required String freePromotionTitle,
  }) {
    final isReward = item.isPromotionReward;
    final hasOptions = !isReward &&
        (item.selectedVariant != null || item.selectedAddOns.isNotEmpty);
    final promoTag = isReward
        ? (item.promotionTitle ?? 'Promotion reward')
        : _promotionTagForMenuItem(item.menuItem);
    final displayTotal = isReward ? 0.0 : item.totalPrice;
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: FoodFlowTheme.vegDot(item.menuItem.isVeg, size: 14),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.menuItem.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 15,
                    height: 1.18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (hasOptions) ...[
                  const SizedBox(height: 4),
                  Text(
                    [
                      if (item.selectedVariant != null)
                        item.selectedVariant!.name,
                      ...item.selectedAddOns.map((option) => option.name),
                    ].join(' + '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
                if (promoTag.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    promoTag,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: FoodFlowTheme.brandPrimary(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
                if (freeQuantity > 0) ...[
                  const SizedBox(height: 5),
                  Row(
                    children: [
                      const Icon(Icons.check_circle_rounded,
                          color: Color(0xFF2E7BE6), size: 15),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          'Free item${freePromotionTitle.isEmpty ? '' : ' + $freePromotionTitle'}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Color(0xFF2E7BE6),
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
                if (!isReward) ...[
                  const SizedBox(height: 4),
                  Text(
                    'Edit',
                    style: TextStyle(
                      color: FoodFlowTheme.brandPrimary(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (!isReward)
                _MiniQuantityStepper(
                  quantity: paidQuantity,
                  onMinus: () {
                    cart.decrementBySignature(item.signature);
                    _queueCartSummaryRefresh();
                  },
                  onPlus: () {
                    cart.incrementBySignature(item.signature);
                    _queueCartSummaryRefresh();
                  },
                ),
              const SizedBox(height: 6),
              Text(
                isReward
                    ? 'FREE'
                    : formatCurrency(
                        context,
                        displayTotal,
                      ),
                style: TextStyle(
                  color:
                      isReward ? FoodFlowTheme.successDark : FoodFlowTheme.ink,
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (!isReward &&
                  displayTotal < item.unitPrice * paidQuantity &&
                  freeQuantity <= 0) ...[
                const SizedBox(height: 3),
                Text(
                  formatCurrency(context, item.unitPrice * paidQuantity),
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    decoration: TextDecoration.lineThrough,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
              if (freeQuantity > 0 && !isReward) ...[
                const SizedBox(height: 3),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      formatCurrency(context, item.unitPrice * freeQuantity),
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        decoration: TextDecoration.lineThrough,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      'FREE',
                      style: TextStyle(
                        color: FoodFlowTheme.brandPrimary(context),
                        fontSize: 12,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildDetachedRewardRow(Map<String, dynamic> line) {
    final quantity = _rewardLineQuantity(line);
    final name =
        line['name']?.toString() ?? line['title']?.toString() ?? 'Free item';
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        children: [
          const Icon(Icons.check_circle_rounded,
              color: Color(0xFF2E7BE6), size: 16),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 15,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Text(
            '$quantity x FREE',
            style: TextStyle(
              color: FoodFlowTheme.brandPrimary(context),
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSuggestedItemsPanel(CartProvider cart) {
    return _CartPanel(
      padding: const EdgeInsets.fromLTRB(12, 12, 0, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              CircleAvatar(
                radius: 16,
                backgroundColor: Color(0xFFF1F3F7),
                child: Icon(Icons.dashboard_customize_rounded,
                    size: 17, color: FoodFlowTheme.ink),
              ),
              SizedBox(width: 10),
              Text(
                'Complete your meal with',
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 158,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _suggestedItems.length,
              separatorBuilder: (_, __) => const SizedBox(width: 10),
              itemBuilder: (context, index) =>
                  _buildSuggestedTile(cart, _suggestedItems[index]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSuggestedTile(CartProvider cart, MenuItem item) {
    return SizedBox(
      width: 102,
      child: InkWell(
        onTap: () => _addSuggestedItem(cart, item),
        borderRadius: BorderRadius.circular(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: SizedBox(
                    width: 102,
                    height: 78,
                    child: item.imageUrl.isNotEmpty
                        ? AppCachedImage(
                            imageUrl: item.imageUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => _foodThumbFallback(),
                          )
                        : _foodThumbFallback(),
                  ),
                ),
                Positioned(
                  left: 5,
                  bottom: 5,
                  child: FoodFlowTheme.vegDot(item.isVeg, size: 13),
                ),
                Positioned(
                  right: 5,
                  bottom: 5,
                  child: Container(
                    width: 28,
                    height: 28,
                    decoration: BoxDecoration(
                      color: FoodFlowTheme.success.withOpacity(0.10),
                      borderRadius: BorderRadius.circular(9),
                      border: Border.all(
                        color: FoodFlowTheme.brandPrimary(context),
                      ),
                    ),
                    child: Icon(
                      Icons.add_rounded,
                      color: FoodFlowTheme.brandPrimary(context),
                      size: 20,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 7),
            Text(
              item.name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 12,
                height: 1.15,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 3),
            Row(
              children: [
                Text(
                  formatCurrency(context, item.finalPrice),
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (item.hasDiscount) ...[
                  const SizedBox(width: 4),
                  Flexible(
                    child: Text(
                      formatCurrency(context, item.price),
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 11,
                        decoration: TextDecoration.lineThrough,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDeliveryBillPanel({
    required Color primary,
    required dynamic restaurant,
    required String orderType,
    required int? restaurantId,
    required double subtotal,
    required double originalSubtotal,
    required double deliveryFee,
    required double deliveryDiscount,
    required String deliveryLabel,
    required double platformFee,
    required double tax,
    required double discount,
    required double total,
  }) {
    final address = _selectedAddress;
    final isTakeaway = orderType == 'takeaway';
    final itemPromotionSavings =
        (originalSubtotal - subtotal).clamp(0.0, double.infinity).toDouble();
    final displaySavings = (discount + itemPromotionSavings)
        .clamp(0.0, double.infinity)
        .toDouble();
    // Only worth showing as a choice when the restaurant genuinely offers
    // both — a lone "Delivery" (or lone "Pickup") chip with nothing to
    // switch to is just clutter, not a real selector.
    final showOrderTypeSelector =
        _supportsDelivery(restaurant) && _supportsTakeaway(restaurant);
    return _CartPanel(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      child: Column(
        children: [
          if (showOrderTypeSelector) ...[
            _buildOrderTypeSelector(primary, restaurant),
            const _PanelDivider(),
          ],
          _InfoLine(
            icon: isTakeaway ? Icons.storefront_outlined : Icons.bolt_rounded,
            title: _scheduledTime == null
                ? (isTakeaway
                    ? 'Pickup in ${_pickupEtaText(restaurant)}'
                    : 'Delivery in ${_deliveryEtaText()}')
                : 'Scheduled for ${_formatScheduledTime(_scheduledTime!)}',
            titleColor: const Color(0xFF059669),
            subtitle: _scheduledTime == null
                ? 'Want this later? Schedule it'
                : isTakeaway
                    ? 'Tap to change or pickup now'
                    : 'Tap to change or deliver now',
            trailing: true,
            onTap: _showScheduleSelectorSheet,
          ),
          const _PanelDivider(),
          if (isTakeaway)
            _InfoLine(
              icon: Icons.store_mall_directory_outlined,
              title: 'Pickup from ${restaurant?.name ?? 'restaurant'}',
              subtitle: restaurant?.address?.toString(),
            )
          else ...[
            _InfoLine(
              icon: Icons.location_on_outlined,
              title: address == null
                  ? 'Select delivery address'
                  : 'Delivery at ${address.name}',
              subtitle: address?.fullAddress,
              trailing: true,
              onTap: _isAddressLoading
                  ? null
                  : () => _addresses.isNotEmpty
                      ? _showAddressSelector()
                      : _addAddressAndRefresh(restaurantId),
            ),
            if (address?.phone.trim().isNotEmpty == true) ...[
              const _PanelDivider(),
              _InfoLine(
                icon: Icons.call_outlined,
                title: address!.phone,
                trailing: true,
                onTap: () => _showAddressSelector(),
              ),
            ],
          ],
          const _PanelDivider(),
          _BillSummaryLine(
            total: total,
            gross: originalSubtotal + deliveryFee + platformFee + tax,
            saved: displaySavings,
            onTap: () => _showBillDetailsSheet(
              subtotal: subtotal,
              originalSubtotal: originalSubtotal,
              deliveryFee: deliveryFee,
              deliveryDiscount: deliveryDiscount,
              deliveryLabel: deliveryLabel,
              platformFee: platformFee,
              tax: tax,
              discount: discount,
              total: total,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderTypeSelector(Color primary, dynamic restaurant) {
    final supportsDelivery = _supportsDelivery(restaurant);
    final supportsTakeaway = _supportsTakeaway(restaurant);
    if (!supportsDelivery && !supportsTakeaway) return const SizedBox.shrink();

    return Row(
      children: [
        if (supportsDelivery)
          Expanded(
            child: _orderTypeChip(
              icon: Icons.delivery_dining_rounded,
              label: 'Delivery',
              selected: _effectiveOrderType(restaurant) == 'delivery',
              primary: primary,
              onTap: () => _selectOrderType('delivery'),
            ),
          ),
        if (supportsDelivery && supportsTakeaway) const SizedBox(width: 10),
        if (supportsTakeaway)
          Expanded(
            child: _orderTypeChip(
              icon: Icons.shopping_bag_outlined,
              label: 'Pickup',
              selected: _effectiveOrderType(restaurant) == 'takeaway',
              primary: primary,
              onTap: () => _selectOrderType('takeaway'),
            ),
          ),
      ],
    );
  }

  Widget _orderTypeChip({
    required IconData icon,
    required String label,
    required bool selected,
    required Color primary,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        height: 44,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: selected ? primary.withOpacity(0.1) : const Color(0xFFF7F8FB),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? primary : const Color(0xFFE4E8F0),
            width: selected ? 1.3 : 1,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 18,
              color: selected ? primary : FoodFlowTheme.inkSoft,
            ),
            const SizedBox(width: 7),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: selected ? primary : FoodFlowTheme.ink,
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _selectOrderType(String orderType) {
    if (_selectedOrderType == orderType) return;
    setState(() {
      _selectedOrderType = orderType;
      _summaryCartSignature = null;
      _lastCartSignature = null;
    });
    _queueCartSummaryRefresh(delay: Duration.zero);
  }

  String _effectiveOrderType(dynamic restaurant) {
    final supportsDelivery = _supportsDelivery(restaurant);
    final supportsTakeaway = _supportsTakeaway(restaurant);
    if (_selectedOrderType == 'takeaway') {
      return supportsTakeaway ? 'takeaway' : 'delivery';
    }
    if (!supportsDelivery && supportsTakeaway) return 'takeaway';
    return 'delivery';
  }

  bool _supportsDelivery(dynamic restaurant) {
    if (restaurant == null) return true;
    try {
      return restaurant.isDelivery == true;
    } catch (_) {
      return true;
    }
  }

  bool _supportsTakeaway(dynamic restaurant) {
    if (restaurant == null) return false;
    try {
      return restaurant.isTakeaway == true;
    } catch (_) {
      return false;
    }
  }

  String _pickupEtaText(dynamic restaurant) {
    final minutes = restaurant?.preparationMinutes ?? restaurant?.deliveryTime;
    if (minutes is int && minutes > 0) return '$minutes mins';
    return 'soon';
  }

  String _deliveryEtaText() {
    final restaurant = context.read<CartProvider>().restaurant;
    return restaurant?.deliveryEtaLabel ?? 'soon';
  }

  void _showScheduleSelectorSheet() {
    final now = DateTime.now();
    final options = <_ScheduleOption>[
      _ScheduleOption(
        label: _effectiveOrderType(context.read<CartProvider>().restaurant) ==
                'takeaway'
            ? 'Pickup now'
            : 'Deliver now',
        minutesFromNow: 0,
      ),
      const _ScheduleOption(label: 'In 30 minutes', minutesFromNow: 30),
      const _ScheduleOption(label: 'In 1 hour', minutesFromNow: 60),
      const _ScheduleOption(label: 'In 2 hours', minutesFromNow: 120),
    ];

    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (sheetContext) {
        final primary = FoodFlowTheme.brandPrimary(context);
        return SafeArea(
          child: SingleChildScrollView(
            padding: EdgeInsets.only(
              bottom: MediaQuery.of(sheetContext).viewInsets.bottom,
            ),
            child: Container(
              margin: const EdgeInsets.all(10),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.14),
                    blurRadius: 26,
                    offset: const Offset(0, 16),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          _effectiveOrderType(context
                                      .read<CartProvider>()
                                      .restaurant) ==
                                  'takeaway'
                              ? 'Schedule pickup'
                              : 'Schedule delivery',
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 19,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(sheetContext),
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ...options.map((option) {
                    final value = option.minutesFromNow == 0
                        ? null
                        : now.add(Duration(minutes: option.minutesFromNow));
                    final selected = option.minutesFromNow == 0
                        ? _scheduledTime == null
                        : _scheduledTime != null &&
                            _scheduledTime!.difference(value!).inMinutes.abs() <
                                2;
                    return _scheduleSheetTile(
                      title: option.label,
                      subtitle: value == null
                          ? 'Restaurant will start preparing now'
                          : _formatScheduledTime(value),
                      selected: selected,
                      primary: primary,
                      onTap: () {
                        setState(() => _scheduledTime = value);
                        Navigator.pop(sheetContext);
                      },
                    );
                  }),
                  _scheduleSheetTile(
                    title: 'Choose custom time',
                    subtitle: _scheduledTime == null
                        ? 'Pick date and time'
                        : _formatScheduledTime(_scheduledTime!),
                    selected: false,
                    primary: primary,
                    onTap: () => _pickCustomSchedule(sheetContext),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _scheduleSheetTile({
    required String title,
    required String subtitle,
    required bool selected,
    required Color primary,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: selected ? primary.withOpacity(0.08) : const Color(0xFFF7F8FC),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? primary : const Color(0xFFE4E8F0),
          ),
        ),
        child: Row(
          children: [
            Icon(
              selected ? Icons.check_circle_rounded : Icons.schedule_rounded,
              color: selected ? primary : FoodFlowTheme.inkSoft,
              size: 21,
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickCustomSchedule(BuildContext sheetContext) async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: _scheduledTime ?? now.add(const Duration(hours: 1)),
      firstDate: now,
      lastDate: now.add(const Duration(days: 7)),
    );
    if (date == null || !mounted) return;

    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(
        _scheduledTime ?? now.add(const Duration(hours: 1)),
      ),
    );
    if (time == null || !mounted) return;

    final selected = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );
    if (!selected.isAfter(now)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please choose a future time')),
      );
      return;
    }

    setState(() => _scheduledTime = selected);
    Navigator.pop(sheetContext);
  }

  String _formatScheduledTime(DateTime value) {
    final now = DateTime.now();
    final tomorrow = DateTime(now.year, now.month, now.day + 1);
    final valueDay = DateTime(value.year, value.month, value.day);
    final today = DateTime(now.year, now.month, now.day);
    final dayLabel = valueDay == today
        ? 'Today'
        : valueDay == tomorrow
            ? 'Tomorrow'
            : '${value.day}/${value.month}/${value.year}';
    final hour12 = value.hour % 12 == 0 ? 12 : value.hour % 12;
    final minute = value.minute.toString().padLeft(2, '0');
    final suffix = value.hour >= 12 ? 'PM' : 'AM';
    return '$dayLabel, $hour12:$minute $suffix';
  }

  Widget _buildCancellationPolicy() {
    return const Padding(
      padding: EdgeInsets.fromLTRB(2, 0, 2, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'CANCELLATION POLICY',
            style: TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              letterSpacing: 1.8,
              fontWeight: FontWeight.w800,
            ),
          ),
          SizedBox(height: 6),
          Text(
            'A cancellation fee may apply after the restaurant starts preparing your order.',
            style: TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              height: 1.3,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBottomCheckoutBar(
    Color primary,
    CartProvider cart,
    double total,
  ) {
    final user = context.watch<AuthProvider>().currentUser;
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
            child: InkWell(
              onTap: () => _showPaymentSelectorSheet(total),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 4,
                  vertical: 4,
                ),
                child: Row(
                  children: [
                    Container(
                      width: 34,
                      height: 34,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF3F7F4),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: _paymentIconWidget(user, size: 18),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'PAY USING',
                            style: TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 10,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _paymentTitle(user),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: FoodFlowTheme.ink,
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(
                      Icons.keyboard_arrow_up_rounded,
                      color: FoodFlowTheme.muted,
                      size: 18,
                    ),
                  ],
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
                  onTap: _isPlacingOrder
                      ? null
                      : () => _proceedToCheckout(context, cart.restaurant?.id),
                  borderRadius: BorderRadius.circular(18),
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
                                  color: FoodFlowTheme.brandOnPrimary(context),
                                  fontSize: 15,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              Text(
                                'TOTAL',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: GoogleFonts.plusJakartaSans(
                                  color: FoodFlowTheme.brandOnPrimary(context)
                                      .withOpacity(0.85),
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        if (_isPlacingOrder)
                          const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        else ...[
                          Text(
                            'Place Order',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: GoogleFonts.plusJakartaSans(
                              color: FoodFlowTheme.brandOnPrimary(context),
                              fontSize: 14,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Icon(
                            Icons.arrow_forward_ios_rounded,
                            color: FoodFlowTheme.brandOnPrimary(context),
                            size: 16,
                          ),
                        ],
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

  String _paymentTitle(User? user) {
    return switch (_selectedPaymentMethod) {
      'wallet' => AppConfig.walletMoneyLabel,
      'cod' => 'Cash on Delivery',
      _ => _selectedCashfreeUpiAppName?.trim().isNotEmpty == true
          ? _selectedCashfreeUpiAppName!
          : _onlinePaymentModeTitle(_selectedOnlineModeFor(user)),
    };
  }

  IconData _paymentIcon(User? user) {
    return switch (_selectedPaymentMethod) {
      'wallet' => Icons.account_balance_wallet_outlined,
      'cod' => Icons.payments_outlined,
      _ => _onlinePaymentModeIcon(_selectedOnlineModeFor(user)),
    };
  }

  Widget _paymentIconWidget(User? user, {required double size}) {
    final assetIcon = _paymentAssetForSelection(user);
    if (assetIcon.isNotEmpty) {
      return SvgPicture.asset(
        assetIcon,
        width: size,
        height: size,
        fit: BoxFit.contain,
      );
    }

    return Icon(
      _paymentIcon(user),
      size: size,
      color: FoodFlowTheme.brandPrimary(context),
    );
  }

  Future<void> _showPaymentSelectorSheet(double total) async {
    final authProvider = context.read<AuthProvider>();
    await authProvider.loadUser(forceRefresh: true);
    if (!mounted) return;
    final initialUser = authProvider.currentUser;
    debugPrint(
        '[PaymentTrace] checkout.settings.refreshed source=sheet default=${initialUser?.paymentGatewayProvider ?? '-'} enabled=${initialUser?.enabledPaymentGatewayKeys.join(',') ?? '-'} gateways=${initialUser?.paymentGateways.map((gateway) => '${gateway.key}:${gateway.enabled}:${gateway.selected}').join('|') ?? '-'}');
    final initialGateway = _effectiveGatewayProvider(initialUser);
    debugPrint(
        '[PaymentTrace] checkout.sheet.open selectedMethod=$_selectedPaymentMethod selectedGateway=$_selectedGatewayKey effectiveGateway=$initialGateway enabledGateways=${initialUser?.enabledPaymentGatewayKeys.join(',') ?? '-'} defaultProvider=${initialUser?.paymentGatewayProvider ?? '-'}');
    if (initialGateway == 'cashfree') {
      await _loadCashfreeUpiApps();
      if (!mounted) return;
    }

    showModalBottomSheet<void>(
      context: context,
      backgroundColor: const Color(0xFFF5F6FB),
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        final user =
            Provider.of<AuthProvider>(context, listen: false).currentUser;
        final activeGateway = _effectiveGatewayProvider(user);
        debugPrint(
            '[PaymentTrace] checkout.sheet.build activeGateway=$activeGateway selectedGateway=$_selectedGatewayKey selectedMode=$_selectedOnlinePaymentMode cashfreeApps=${_cashfreeUpiApps.length}');
        final availableGateways = _availablePaymentGateways(user);
        final onlineOptions = activeGateway == null
            ? const <_OnlinePaymentModeOption>[]
            : _onlinePaymentModeOptions(activeGateway);
        final visibleOnlineOptions =
            activeGateway == 'cashfree' && _cashfreeUpiApps.isNotEmpty
                ? onlineOptions.where((option) => option.key != 'upi').toList()
                : onlineOptions;
        final savings = ((_summaryDiscount ?? 0) +
                (_summaryEmbeddedItemDiscount ?? 0) +
                (_summaryDeliveryDiscount ?? 0))
            .clamp(0.0, double.infinity)
            .toDouble();
        final walletCoversTotal = _walletBalance >= total && total > 0;
        return SafeArea(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.sizeOf(context).height * 0.88,
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 42,
                      height: 4,
                      decoration: BoxDecoration(
                        color: const Color(0xFFD5DAE4),
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.arrow_back),
                        color: FoodFlowTheme.ink,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Choose payment',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                                color: FoodFlowTheme.ink,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Secure payment powered by ${AppConfig.appName}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                                color: FoodFlowTheme.muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _paymentBillCard(total: total, savings: savings),
                  const SizedBox(height: 18),
                  if (availableGateways.length > 1) ...[
                    _paymentSectionTitle('Payment gateway'),
                    const SizedBox(height: 10),
                    for (final gateway in availableGateways) ...[
                      _paymentSheetTile(
                        title: gateway.label,
                        subtitle: gateway.key == 'cashfree'
                            ? 'Use Cashfree UPI, cards, wallets and banking.'
                            : gateway.key == 'stripe'
                                ? 'Use Stripe card and Google Pay checkout.'
                                : 'Use Razorpay UPI, cards, wallets and banking.',
                        icon: Icons.account_balance_wallet_outlined,
                        assetIcon: _gatewayAssetForKey(gateway.key),
                        selected: _selectedPaymentMethod == 'online' &&
                            activeGateway == gateway.key,
                        trailingText:
                            gateway.key == activeGateway ? 'Selected' : 'Use',
                        onTap: () => _selectPaymentMethod(
                          'online',
                          gatewayKey: gateway.key,
                          onlineMode: _onlinePaymentModeOptions(gateway.key)
                                  .isNotEmpty
                              ? _onlinePaymentModeOptions(gateway.key).first.key
                              : 'upi',
                        ),
                      ),
                      const SizedBox(height: 10),
                    ],
                    const SizedBox(height: 8),
                  ],
                  if (activeGateway != null && onlineOptions.isNotEmpty) ...[
                    _paymentSectionTitle(
                      activeGateway == 'cashfree' ? 'UPI apps' : 'Recommended',
                    ),
                    const SizedBox(height: 10),
                    if (activeGateway == 'cashfree' &&
                        _cashfreeUpiApps.isNotEmpty)
                      for (final app in _cashfreeUpiApps) ...[
                        _paymentSheetTile(
                          title: app.displayName,
                          subtitle: 'Pay directly from ${app.displayName}',
                          icon: Icons.account_balance_wallet_outlined,
                          assetIcon: _cashfreeUpiAppAsset(app),
                          providedIcon: app.icon,
                          selected: _selectedPaymentMethod == 'online' &&
                              _selectedCashfreeUpiAppId == app.id,
                          badge: _selectedCashfreeUpiAppId == app.id
                              ? 'Selected'
                              : null,
                          trailingText: 'UPI',
                          onTap: () => _selectPaymentMethod(
                            'online',
                            gatewayKey: activeGateway,
                            onlineMode: 'upi',
                            cashfreeUpiAppId: app.id,
                            cashfreeUpiAppName: app.displayName,
                          ),
                        ),
                        const SizedBox(height: 10),
                      ],
                    for (final option in visibleOnlineOptions) ...[
                      _paymentSheetTile(
                        title: option.title,
                        subtitle: option.subtitle,
                        icon: option.icon,
                        assetIcon: option.assetIcon,
                        selected: _selectedPaymentMethod == 'online' &&
                            _selectedOnlineModeFor(user) == option.key &&
                            _selectedCashfreeUpiAppId == null,
                        badge: option.key == 'upi' ? 'Recommended' : null,
                        trailingText: option.trailingText,
                        onTap: () => _selectPaymentMethod(
                          'online',
                          gatewayKey: activeGateway,
                          onlineMode: option.key,
                        ),
                      ),
                      const SizedBox(height: 10),
                    ],
                  ],
                  const SizedBox(height: 8),
                  _paymentSectionTitle('More ways to pay'),
                  const SizedBox(height: 10),
                  _paymentSheetTile(
                    title: AppConfig.walletMoneyLabel,
                    subtitle: walletCoversTotal
                        ? 'Available balance: ${formatCurrency(context, _walletBalance)}'
                        : 'Balance ${formatCurrency(context, _walletBalance)} is below the bill total',
                    icon: Icons.account_balance_wallet_outlined,
                    assetIcon: 'assets/icons/payment/wallet.svg',
                    selected: _selectedPaymentMethod == 'wallet',
                    badge: walletCoversTotal ? 'Instant' : null,
                    trailingText:
                        walletCoversTotal ? 'No gateway needed' : null,
                    enabled: walletCoversTotal,
                    onTap: () => _selectPaymentMethod('wallet'),
                  ),
                  const SizedBox(height: 10),
                  _paymentSheetTile(
                    title: 'Cash on Delivery',
                    subtitle: 'Pay when your order arrives',
                    icon: Icons.payments_outlined,
                    assetIcon: 'assets/icons/payment/cod.svg',
                    selected: _selectedPaymentMethod == 'cod',
                    trailingText: 'Cash',
                    onTap: () => _selectPaymentMethod('cod'),
                  ),
                  const SizedBox(height: 14),
                  _paymentSafetyStrip(),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  void _selectPaymentMethod(
    String method, {
    String? gatewayKey,
    String? onlineMode,
    String? cashfreeUpiAppId,
    String? cashfreeUpiAppName,
  }) {
    debugPrint(
        '[PaymentTrace] checkout.select method=$method gateway=${gatewayKey ?? '-'} mode=${onlineMode ?? '-'} cashfreeUpiApp=${cashfreeUpiAppId ?? '-'} cashfreeUpiName=${cashfreeUpiAppName ?? '-'}');
    setState(() {
      _selectedPaymentMethod = method;
      if (method == 'online') {
        _selectedGatewayKey = gatewayKey;
        _selectedOnlinePaymentMode = onlineMode ?? _selectedOnlinePaymentMode;
        _selectedCashfreeUpiAppId = cashfreeUpiAppId;
        _selectedCashfreeUpiAppName = cashfreeUpiAppName;
      } else {
        _selectedCashfreeUpiAppId = null;
        _selectedCashfreeUpiAppName = null;
      }
    });
    Navigator.pop(context);
  }

  Widget _paymentBillCard({
    required double total,
    required double savings,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE8ECF3)),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF8F3),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.lock_outline_rounded,
                  color: Color(0xFF168A35),
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Payable amount',
                      style: TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      formatCurrency(context, total),
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              if (savings > 0)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF8F3),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    'Saved ${formatCurrency(context, savings)}',
                    style: const TextStyle(
                      color: Color(0xFF168A35),
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
            ],
          ),
          if (_cashbackEarned > 0 || _rewardPointsEarned > 0) ...[
            const SizedBox(height: 12),
            const Divider(height: 1),
            const SizedBox(height: 12),
            Row(
              children: [
                const Icon(
                  Icons.local_offer_outlined,
                  size: 17,
                  color: FoodFlowTheme.muted,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _paymentRewardSummary(),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.inkSoft,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _paymentSectionTitle(String title) {
    return Text(
      title.toUpperCase(),
      style: const TextStyle(
        color: FoodFlowTheme.muted,
        fontSize: 11,
        fontWeight: FontWeight.w900,
      ),
    );
  }

  Widget _paymentSheetTile({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool selected,
    required VoidCallback onTap,
    String logoUrl = '',
    String assetIcon = '',
    String providedIcon = '',
    String? badge,
    String? trailingText,
    bool enabled = true,
  }) {
    final foreground = enabled ? FoodFlowTheme.ink : FoodFlowTheme.muted;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        onTap: enabled ? onTap : null,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: selected
                  ? FoodFlowTheme.brandPrimary(context).withOpacity(0.42)
                  : const Color(0xFFE8ECF3),
              width: selected ? 1.5 : 1,
            ),
          ),
          child: Row(
            children: [
              providedIcon.isNotEmpty
                  ? _paymentProvidedIcon(
                      providedIcon,
                      selected,
                      fallback: assetIcon.isNotEmpty
                          ? _paymentAssetIcon(assetIcon, selected)
                          : _paymentFallbackIcon(icon, selected),
                    )
                  : assetIcon.isNotEmpty
                      ? _paymentAssetIcon(assetIcon, selected)
                      : logoUrl.isNotEmpty
                          ? ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: AppCachedImage(
                                imageUrl: logoUrl,
                                width: 44,
                                height: 44,
                                fit: BoxFit.contain,
                                errorBuilder: (_, __, ___) =>
                                    _paymentFallbackIcon(
                                  icon,
                                  selected,
                                ),
                              ),
                            )
                          : _paymentFallbackIcon(icon, selected),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: foreground,
                              fontSize: 15.5,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        if (badge != null) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 7,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEFF8F3),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              badge,
                              style: const TextStyle(
                                color: Color(0xFF168A35),
                                fontSize: 10,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color:
                            enabled ? FoodFlowTheme.muted : FoodFlowTheme.faint,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Icon(
                    selected ? Icons.check_circle : Icons.chevron_right,
                    color: selected
                        ? FoodFlowTheme.brandPrimary(context)
                        : FoodFlowTheme.faint,
                  ),
                  if (trailingText != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      trailingText,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _paymentSafetyStrip() {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF3FA),
        borderRadius: BorderRadius.circular(16),
      ),
      child: const Row(
        children: [
          Icon(Icons.verified_user_outlined,
              size: 18, color: Color(0xFF4D5C73)),
          SizedBox(width: 8),
          Expanded(
            child: Text(
              'Payments are verified before the order is confirmed. If a gateway fails, choose another method and try again.',
              style: TextStyle(
                color: Color(0xFF4D5C73),
                fontSize: 12,
                height: 1.3,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _paymentIconContainer(
    Widget child,
    bool selected, {
    double padding = 7,
  }) {
    return Container(
      width: 44,
      height: 44,
      padding: EdgeInsets.all(padding),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: selected
              ? FoodFlowTheme.brandPrimary(context).withOpacity(0.35)
              : FoodFlowTheme.line,
        ),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(6),
        child: child,
      ),
    );
  }

  Widget _paymentAssetIcon(String assetIcon, bool selected) {
    return _paymentIconContainer(
      SvgPicture.asset(assetIcon, fit: BoxFit.contain),
      selected,
    );
  }

  Widget _paymentProvidedIcon(
    String providedIcon,
    bool selected, {
    required Widget fallback,
  }) {
    final image = _paymentProvidedIconImage(providedIcon);
    if (image == null) return fallback;
    return _paymentIconContainer(image, selected, padding: 5);
  }

  Widget? _paymentProvidedIconImage(String providedIcon) {
    final raw = providedIcon.trim();
    if (raw.isEmpty) return null;

    if (raw.startsWith('<svg')) {
      return SvgPicture.string(raw, fit: BoxFit.contain);
    }

    if (raw.startsWith('http://') || raw.startsWith('https://')) {
      return AppCachedImage(imageUrl: raw, fit: BoxFit.contain);
    }

    try {
      final lower = raw.toLowerCase();
      if (lower.startsWith('data:')) {
        final commaIndex = raw.indexOf(',');
        if (commaIndex == -1) return null;
        final meta = raw.substring(0, commaIndex).toLowerCase();
        final payload = raw.substring(commaIndex + 1);
        if (meta.contains('svg')) {
          final svg = meta.contains('base64')
              ? utf8.decode(base64Decode(payload))
              : Uri.decodeComponent(payload);
          return SvgPicture.string(svg, fit: BoxFit.contain);
        }
        return Image.memory(
          base64Decode(payload),
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => const SizedBox.shrink(),
        );
      }

      final normalized = raw.replaceAll(RegExp(r'\s+'), '');
      return Image.memory(
        base64Decode(normalized),
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => const SizedBox.shrink(),
      );
    } catch (_) {
      return null;
    }
  }

  Widget _paymentFallbackIcon(IconData icon, bool selected) {
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Icon(
        icon,
        color: selected
            ? FoodFlowTheme.brandPrimary(context)
            : FoodFlowTheme.inkSoft,
      ),
    );
  }

  Future<void> _loadCashfreeUpiApps() async {
    if (_isLoadingCashfreeUpiApps || _hasLoadedCashfreeUpiApps) {
      debugPrint(
          '[PaymentTrace] checkout.cashfreeUpiApps.skip loading=$_isLoadingCashfreeUpiApps loaded=$_hasLoadedCashfreeUpiApps count=${_cashfreeUpiApps.length}');
      return;
    }
    setState(() => _isLoadingCashfreeUpiApps = true);
    try {
      final apps = await _paymentService.cashfreeUpiApps();
      if (!mounted) return;
      final appSummary =
          apps.map((app) => '${app.id}:${app.displayName}').join('|');
      debugPrint(
          '[PaymentTrace] checkout.cashfreeUpiApps.loaded count=${apps.length} apps=$appSummary');
      setState(() {
        _cashfreeUpiApps = apps;
        _hasLoadedCashfreeUpiApps = true;
        if (apps.isNotEmpty && _selectedCashfreeUpiAppId == null) {
          _selectedCashfreeUpiAppId = apps.first.id;
          _selectedCashfreeUpiAppName = apps.first.displayName;
        } else if (apps.isEmpty) {
          _selectedCashfreeUpiAppId = null;
          _selectedCashfreeUpiAppName = null;
          _selectedOnlinePaymentMode = 'upi';
        }
      });
    } catch (error) {
      debugPrint('[PaymentTrace] checkout.cashfreeUpiApps.error $error');
      debugPrint('Load Cashfree UPI apps error: $error');
      if (!mounted) return;
      setState(() {
        _cashfreeUpiApps = const [];
        _hasLoadedCashfreeUpiApps = true;
        _selectedCashfreeUpiAppId = null;
        _selectedCashfreeUpiAppName = null;
        _selectedOnlinePaymentMode = 'upi';
      });
    } finally {
      if (mounted) setState(() => _isLoadingCashfreeUpiApps = false);
    }
  }

  Future<void> _loadWalletBalance() async {
    try {
      final response = await _api.get(ApiConstants.wallet);
      final data = response is Map ? response['data'] : null;
      final wallet = data is Map ? data['wallet'] : null;
      final balance = wallet is Map ? wallet['balance'] : null;
      if (!mounted) return;
      setState(() {
        _walletBalance = double.tryParse('$balance') ?? 0;
      });
    } catch (error) {
      debugPrint('Load wallet balance error: $error');
    }
  }

  String _gatewayDisplayName(User? user) {
    final provider = _effectiveGatewayProvider(user);
    return _gatewayOptionFor(user, provider)?.label ??
        _gatewayLabelForKey(provider ?? 'razorpay');
  }

  String _gatewayLogoUrl(User? user) {
    final provider = _effectiveGatewayProvider(user);
    return _gatewayOptionFor(user, provider)?.logo ??
        user?.paymentGatewayLogo ??
        '';
  }

  List<_OnlinePaymentModeOption> _onlinePaymentModeOptions(String provider) {
    if (provider == 'stripe') {
      return const [
        _OnlinePaymentModeOption(
          key: 'card',
          title: 'Credit / Debit Card',
          subtitle: 'Pay securely with your saved or new card.',
          icon: Icons.credit_card_rounded,
          trailingText: 'Card',
          assetIcon: 'assets/icons/payment/card.svg',
        ),
        _OnlinePaymentModeOption(
          key: 'google_pay',
          title: 'Google Pay',
          subtitle: 'Use Google Pay if it is enabled on your Stripe account.',
          icon: Icons.account_balance_wallet_outlined,
          trailingText: 'GPay',
          assetIcon: 'assets/icons/payment/google_pay.svg',
        ),
      ];
    }

    if (provider == 'cashfree') {
      return const [
        _OnlinePaymentModeOption(
          key: 'upi',
          title: 'UPI',
          subtitle: 'Choose an installed UPI app and complete payment here.',
          icon: Icons.qr_code_2_rounded,
          trailingText: 'Instant',
          assetIcon: 'assets/icons/payment/upi.svg',
        ),
        _OnlinePaymentModeOption(
          key: 'card',
          title: 'Credit / Debit Card',
          subtitle: 'Pay by card through the secure Cashfree SDK screen.',
          icon: Icons.credit_card_rounded,
          trailingText: 'Cards',
          assetIcon: 'assets/icons/payment/card.svg',
        ),
        _OnlinePaymentModeOption(
          key: 'wallet',
          title: 'Online Wallets',
          subtitle: 'Pay using wallets supported by your Cashfree account.',
          icon: Icons.account_balance_wallet_outlined,
          trailingText: 'Wallets',
          assetIcon: 'assets/icons/payment/wallet.svg',
        ),
        _OnlinePaymentModeOption(
          key: 'netbanking',
          title: 'Net Banking',
          subtitle: 'Choose a bank on the secure Cashfree SDK screen.',
          icon: Icons.account_balance_rounded,
          trailingText: 'Bank',
          assetIcon: 'assets/icons/payment/netbanking.svg',
        ),
        _OnlinePaymentModeOption(
          key: 'paylater',
          title: 'Pay Later',
          subtitle: 'Use pay later providers enabled on Cashfree.',
          icon: Icons.schedule_rounded,
          trailingText: 'Later',
          assetIcon: 'assets/icons/payment/pay_later.svg',
        ),
        _OnlinePaymentModeOption(
          key: 'emi',
          title: 'EMI',
          subtitle: 'Pay in installments if available for this order.',
          icon: Icons.payments_rounded,
          trailingText: 'EMI',
          assetIcon: 'assets/icons/payment/emi.svg',
        ),
      ];
    }

    return const [
      _OnlinePaymentModeOption(
        key: 'upi',
        title: 'UPI',
        subtitle: 'Pay from any UPI app on the secure payment page.',
        icon: Icons.qr_code_2_rounded,
        trailingText: 'Instant',
        assetIcon: 'assets/icons/payment/upi.svg',
      ),
      _OnlinePaymentModeOption(
        key: 'card',
        title: 'Credit / Debit Card',
        subtitle: 'Use Visa, Mastercard, RuPay or other supported cards.',
        icon: Icons.credit_card_rounded,
        trailingText: 'Cards',
        assetIcon: 'assets/icons/payment/card.svg',
      ),
      _OnlinePaymentModeOption(
        key: 'wallet',
        title: 'Online Wallets',
        subtitle: 'Pay using supported wallet apps on the payment page.',
        icon: Icons.account_balance_wallet_outlined,
        trailingText: 'Wallets',
        assetIcon: 'assets/icons/payment/wallet.svg',
      ),
      _OnlinePaymentModeOption(
        key: 'netbanking',
        title: 'Net Banking',
        subtitle: 'Continue to your bank from the secure payment page.',
        icon: Icons.account_balance_rounded,
        trailingText: 'Bank',
        assetIcon: 'assets/icons/payment/netbanking.svg',
      ),
    ];
  }

  String _selectedOnlineModeFor(User? user) {
    final provider = _effectiveGatewayProvider(user);
    if (provider == null) return _selectedOnlinePaymentMode;
    final options = _onlinePaymentModeOptions(provider);
    if (options.any((option) => option.key == _selectedOnlinePaymentMode)) {
      return _selectedOnlinePaymentMode;
    }
    return options.isEmpty ? _selectedOnlinePaymentMode : options.first.key;
  }

  String _onlinePaymentModeTitle(String mode) {
    return switch (mode) {
      'card' => 'Credit / Debit Card',
      'wallet' => 'Online Wallets',
      'netbanking' => 'Net Banking',
      'paylater' => 'Pay Later',
      'emi' => 'EMI',
      'google_pay' => 'Google Pay',
      _ => 'UPI',
    };
  }

  IconData _onlinePaymentModeIcon(String mode) {
    return switch (mode) {
      'card' => Icons.credit_card_rounded,
      'wallet' => Icons.account_balance_wallet_outlined,
      'netbanking' => Icons.account_balance_rounded,
      'paylater' => Icons.schedule_rounded,
      'emi' => Icons.payments_rounded,
      'google_pay' => Icons.account_balance_wallet_outlined,
      _ => Icons.qr_code_2_rounded,
    };
  }

  String _paymentAssetForSelection(User? user) {
    return switch (_selectedPaymentMethod) {
      'wallet' => 'assets/icons/payment/wallet.svg',
      'cod' => 'assets/icons/payment/cod.svg',
      _ => _selectedCashfreeUpiAppName?.trim().isNotEmpty == true
          ? _cashfreeUpiAppNameAsset(_selectedCashfreeUpiAppName!)
          : _onlinePaymentModeAsset(_selectedOnlineModeFor(user)),
    };
  }

  String _onlinePaymentModeAsset(String mode) {
    return switch (mode) {
      'card' => 'assets/icons/payment/card.svg',
      'wallet' => 'assets/icons/payment/wallet.svg',
      'netbanking' => 'assets/icons/payment/netbanking.svg',
      'paylater' => 'assets/icons/payment/pay_later.svg',
      'emi' => 'assets/icons/payment/emi.svg',
      'google_pay' => 'assets/icons/payment/google_pay.svg',
      _ => 'assets/icons/payment/upi.svg',
    };
  }

  String _cashfreeUpiAppAsset(CashfreeUpiApp app) {
    return _cashfreeUpiAppNameAsset(app.displayName);
  }

  String _cashfreeUpiAppNameAsset(String displayName) {
    final name = displayName.toLowerCase();
    if (name.contains('google') || name.contains('gpay')) {
      return 'assets/icons/payment/google_pay.svg';
    }
    if (name.contains('phonepe') || name.contains('phone pe')) {
      return 'assets/icons/payment/phonepe.svg';
    }
    if (name.contains('paytm')) return 'assets/icons/payment/paytm.svg';
    if (name.contains('bhim')) return 'assets/icons/payment/bhim.svg';
    if (name.contains('amazon')) return 'assets/icons/payment/amazon_pay.svg';
    return 'assets/icons/payment/upi.svg';
  }

  String _paymentRewardSummary() {
    final parts = <String>[];
    if (_cashbackEarned > 0) {
      parts.add('Cashback ${formatCurrency(context, _cashbackEarned)}');
    }
    if (_rewardPointsEarned > 0) {
      parts.add('$_rewardPointsEarned points after delivery');
    }
    return parts.join(' + ');
  }

  List<PaymentGatewayOption> _availablePaymentGateways(User? user) {
    if (user == null || !user.isPaymentGatewayEnabled) return const [];

    return user.paymentGateways
        .where((gateway) => gateway.enabled && _isNativeGateway(gateway.key))
        .toList();
  }

  PaymentGatewayOption? _gatewayOptionFor(User? user, String? provider) {
    if (provider == null) return null;
    for (final gateway in user?.paymentGateways ?? const []) {
      if (gateway.key == provider) return gateway;
    }
    return null;
  }

  String? _effectiveGatewayProvider(User? user) {
    if (user == null || !user.isPaymentGatewayEnabled) return null;

    final selected = _selectedGatewayKey?.toLowerCase();
    if (selected != null &&
        _isNativeGateway(selected) &&
        user.enabledPaymentGatewayKeys.contains(selected)) {
      return selected;
    }

    for (final gateway in user.paymentGateways) {
      if (gateway.enabled &&
          gateway.selected &&
          _isNativeGateway(gateway.key)) {
        return gateway.key;
      }
    }

    final provider = user.paymentGatewayProvider.toLowerCase();
    if (_isNativeGateway(provider) &&
        user.enabledPaymentGatewayKeys.contains(provider)) {
      return provider;
    }

    final available = _availablePaymentGateways(user);
    return available.isNotEmpty ? available.first.key : null;
  }

  bool _isNativeGateway(String provider) {
    return {'razorpay', 'stripe', 'cashfree'}.contains(provider);
  }

  String _gatewayAssetForKey(String provider) {
    return switch (provider) {
      'cashfree' => 'assets/icons/payment/cashfree.svg',
      'stripe' => 'assets/icons/payment/stripe.svg',
      _ => 'assets/icons/payment/razorpay.svg',
    };
  }

  String _gatewayLabelForKey(String provider) {
    return switch (provider) {
      'stripe' => 'Stripe',
      'cashfree' => 'UPI / Cards / Wallets',
      _ => 'Razorpay',
    };
  }

  Widget _foodThumbFallback() {
    return Container(
      color: const Color(0xFFFFF3E8),
      child: Icon(
        Icons.fastfood_rounded,
        color: Theme.of(context).colorScheme.primary,
      ),
    );
  }

  void _showBillDetailsSheet({
    required double subtotal,
    required double originalSubtotal,
    required double deliveryFee,
    required double deliveryDiscount,
    required String deliveryLabel,
    required double platformFee,
    required double tax,
    required double discount,
    required double total,
  }) {
    final itemPromotionSavings =
        (originalSubtotal - subtotal).clamp(0.0, double.infinity).toDouble();
    final orderPromotionSavings =
        (discount - deliveryDiscount).clamp(0.0, double.infinity).toDouble();
    final offerDiscount = itemPromotionSavings + orderPromotionSavings;
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => SafeArea(
        child: Container(
          margin: const EdgeInsets.all(10),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Bill details',
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 16),
              _billRow(context, 'Item total', originalSubtotal),
              _billDeliveryRow(
                context,
                deliveryLabel,
                deliveryFee,
                deliveryDiscount,
              ),
              if (platformFee > 0)
                _billRow(context, 'Platform fee', platformFee),
              _billRow(
                context,
                _summaryTaxLabel,
                tax,
                onTap: () => _showTaxBreakdownPopup(tax),
                tappable: true,
              ),
              if (offerDiscount > 0)
                _billRow(
                  context,
                  'Offer discount',
                  offerDiscount,
                  isSavings: true,
                ),
              const Divider(height: 24),
              _billRow(context, 'Total bill', total, isTotal: true),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showCartNoteSheet() async {
    final note = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => _CartNoteSheet(initialNote: _cartNote),
    );
    if (!mounted || note == null) return;
    setState(() => _cartNote = note);
  }

  void _showPromoSelectorSheet() {
    final primary = FoodFlowTheme.brandPrimary(context);
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        final subtotal = context.read<CartProvider>().subtotal;
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.only(top: 42),
            child: DraggableScrollableSheet(
              expand: false,
              initialChildSize: 0.72,
              minChildSize: 0.42,
              maxChildSize: 0.92,
              builder: (context, scrollController) {
                final scratchBlocked = _hasActiveItemOfferBlockingScratch(
                    context.read<CartProvider>());
                return Container(
                  decoration: const BoxDecoration(
                    color: Color(0xFFF5F6FB),
                    borderRadius:
                        BorderRadius.vertical(top: Radius.circular(26)),
                  ),
                  child: ListView(
                    controller: scrollController,
                    padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
                    children: [
                      Row(
                        children: [
                          const Expanded(
                            child: Text(
                              'Coupons and promos',
                              style: TextStyle(
                                color: FoodFlowTheme.ink,
                                fontSize: 21,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          IconButton.filled(
                            onPressed: () => Navigator.pop(sheetContext),
                            style: IconButton.styleFrom(
                              backgroundColor: FoodFlowTheme.ink,
                              foregroundColor: Colors.white,
                            ),
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      if (_selectedCouponCode.isNotEmpty)
                        _promoSheetCard(
                          title: 'Remove coupon',
                          subtitle: 'Continue with automatic promotions only',
                          code: '',
                          selected: false,
                          enabled: true,
                          primary: primary,
                          onTap: () {
                            setState(() => _selectedCouponCode = '');
                            Navigator.pop(sheetContext);
                            _queueCartSummaryRefresh(delay: Duration.zero);
                          },
                        ),
                      if (_cartPromos.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 26),
                          child: Text(
                            'No coupons available for this cart.',
                            style: TextStyle(
                              color: FoodFlowTheme.muted,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        )
                      else
                        ..._cartPromos.map((promo) {
                          final code = _promoCode(promo);
                          final isScratchReward = _isScratchRewardPromo(promo);
                          final minOrder = _toDouble(
                            promo['min_order_amount'] ??
                                promo['min_order_value'] ??
                                (promo['conditions'] is Map
                                    ? (promo['conditions']
                                        as Map)['min_order_amount']
                                    : null),
                          );
                          final minOrderEnabled = code.isEmpty ||
                              minOrder <= 0 ||
                              subtotal >= minOrder;
                          final enabled = minOrderEnabled &&
                              !(isScratchReward && scratchBlocked);
                          return _promoSheetCard(
                            title: _promoTitle(promo),
                            subtitle: isScratchReward && scratchBlocked
                                ? 'Scratch rewards cannot be combined with active item offers.'
                                : enabled
                                    ? _promoSubtitle(promo)
                                    : 'Add ${formatCurrency(context, minOrder - subtotal)} more to apply',
                            code: code,
                            selected:
                                code.isNotEmpty && code == _selectedCouponCode,
                            enabled: enabled && code.isNotEmpty,
                            primary: primary,
                            onTap: code.isEmpty
                                ? null
                                : () {
                                    setState(() => _selectedCouponCode = code);
                                    Navigator.pop(sheetContext);
                                    _queueCartSummaryRefresh(
                                      delay: Duration.zero,
                                    );
                                  },
                          );
                        }),
                    ],
                  ),
                );
              },
            ),
          ),
        );
      },
    );
  }

  Widget _promoSheetCard({
    required String title,
    required String subtitle,
    required String code,
    required bool selected,
    required bool enabled,
    required Color primary,
    required VoidCallback? onTap,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: selected ? primary : const Color(0xFFE4E8F0),
          width: selected ? 1.5 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: InkWell(
        onTap: enabled ? onTap : null,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: enabled
                      ? FoodFlowTheme.tagOrangeSoft
                      : const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  Icons.local_offer_rounded,
                  color: enabled
                      ? FoodFlowTheme.tagOrangeDark
                      : FoodFlowTheme.faint,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color:
                            enabled ? FoodFlowTheme.ink : FoodFlowTheme.muted,
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        height: 1.3,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    if (code.isNotEmpty) ...[
                      const SizedBox(height: 9),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 9,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF7F8FC),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          code,
                          style: const TextStyle(
                            color: FoodFlowTheme.inkSoft,
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(
                selected
                    ? Icons.check_circle_rounded
                    : Icons.radio_button_unchecked_rounded,
                color: selected
                    ? primary
                    : enabled
                        ? FoodFlowTheme.muted
                        : FoodFlowTheme.faint,
                size: 22,
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _promoCode(Map<String, dynamic> promo) {
    return (promo['coupon_code'] ?? promo['code'] ?? '').toString().trim();
  }

  bool _isScratchRewardPromo(Map<String, dynamic> promo) {
    final source = (promo['source_type'] ??
            promo['migrated_from_type'] ??
            (promo['visibility'] is Map
                ? (promo['visibility'] as Map)['source']
                : null))
        ?.toString()
        .toLowerCase();
    final title = (promo['title'] ?? '').toString().toLowerCase();
    return source == 'scratch_card_reward' ||
        source == 'scratch_card' ||
        title.startsWith('scratch reward');
  }

  bool _hasActiveItemOfferBlockingScratch(CartProvider cart) {
    if (cart.paidItems.any((item) =>
        item.promotionId != null ||
        (item.promotionDealPrice ?? 0) > 0 ||
        _isBlockingOfferText(item.promotionTitle))) {
      return true;
    }

    if (_rewardLines.isNotEmpty) {
      return true;
    }

    return _rewardActions.any((action) =>
        action['action']?.toString() == 'auto_add_reward' ||
        _isBlockingOfferText(action['title']));
  }

  bool _isBlockingOfferText(Object? value) {
    final text = value?.toString().toLowerCase().replaceAll('-', '_') ?? '';
    return text.contains('bogo') ||
        text.contains('buy_1') ||
        text.contains('buy 1') ||
        text.contains('buy_2') ||
        text.contains('buy 2') ||
        text.contains('combo') ||
        text.contains('free item') ||
        text.contains('free_item');
  }

  String _promoTitle(Map<String, dynamic> promo) {
    final label = PromotionDisplayUtils.labelForPromo(
      promo,
      currencySymbol: getCurrencySymbol(context),
      preferTitle: true,
    );
    return label.trim().isEmpty ? 'Promotion' : label;
  }

  String _promoSubtitle(Map<String, dynamic> promo) {
    final description = promo['description']?.toString().trim();
    if (description != null && description.isNotEmpty) return description;

    final minOrder = _toDouble(
      promo['min_order_amount'] ?? promo['min_order_value'],
    );
    if (minOrder > 0) {
      return 'Valid above ${formatCurrency(context, minOrder)}';
    }

    return 'Tap to apply this coupon';
  }

  Widget _buildCartPromotionSummary(
    Color primary,
    List<String> labels, {
    required double discount,
    required double savedOnOrder,
    required bool hasFreshSummary,
    required List<Map<String, dynamic>> promotionProgress,
    required List<Map<String, dynamic>> rewardActions,
  }) {
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 10),
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE9EDF3)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(
              color: FoodFlowTheme.success.withOpacity(0.10),
              borderRadius: BorderRadius.circular(999),
            ),
            child: const Icon(
              Icons.check_rounded,
              color: FoodFlowTheme.success,
              size: 18,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _cartSavingsText(
                    savedOnOrder > 0 ? savedOnOrder : discount,
                    hasFreshSummary: hasFreshSummary,
                  ),
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (_selectedCouponCode.isNotEmpty) ...[
                  const SizedBox(height: 7),
                  Text(
                    'Coupon $_selectedCouponCode selected',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF168A35),
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
                if (_cartPromos.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  InkWell(
                    onTap: _showPromoSelectorSheet,
                    borderRadius: BorderRadius.circular(10),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 2),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            _selectedCouponCode.isEmpty
                                ? 'View all coupons'
                                : 'Change coupon',
                            style: TextStyle(
                              color: primary,
                              fontSize: 12,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(width: 4),
                          Icon(
                            Icons.chevron_right_rounded,
                            size: 16,
                            color: primary,
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
                if (labels.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: labels
                        .map(
                          (label) => Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 9,
                              vertical: 5,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEFF8F1),
                              borderRadius: BorderRadius.circular(999),
                              border: Border.all(
                                color: const Color(0xFFBFE7C8),
                              ),
                            ),
                            child: Text(
                              label,
                              style: const TextStyle(
                                color: Color(0xFF168A35),
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        )
                        .toList(growable: false),
                  ),
                ],
                if (promotionProgress.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  ...promotionProgress.take(2).map(
                        (progress) => _PromotionProgressRow(
                          message: progress['message']?.toString() ??
                              'Add eligible items to unlock this promotion',
                          progress:
                              _toDouble(progress['progress']).clamp(0.0, 1.0),
                          unlocked: progress['unlocked'] == true,
                          primary: primary,
                        ),
                      ),
                ],
                if (_rewardLines.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  ..._rewardLines.take(2).map(
                        (line) => _RewardLinePreview(
                          name: line['name']?.toString() ??
                              line['title']?.toString() ??
                              'Free reward',
                          quantity: _toDouble(line['quantity']).round(),
                        ),
                      ),
                ],
                if (rewardActions.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  ...rewardActions.take(1).map(
                        (action) => Text(
                          action['action'] == 'select_reward'
                              ? 'Choose your reward in checkout'
                              : 'Free reward will be added automatically',
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                ],
                if (_cashbackEarned > 0 || _rewardPointsEarned > 0) ...[
                  const SizedBox(height: 12),
                  Text(
                    [
                      if (_cashbackEarned > 0)
                        'Cashback ${formatCurrency(context, _cashbackEarned)}',
                      if (_rewardPointsEarned > 0)
                        '$_rewardPointsEarned points after delivery',
                    ].join(' â€¢ '),
                    style: const TextStyle(
                      color: Color(0xFF168A35),
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFreeDeliveryMilestone(Color primary, double subtotal) {
    final threshold = _freeDeliveryThreshold ?? 0;
    final remaining = _freeDeliveryRemaining ??
        (threshold > subtotal ? threshold - subtotal : 0);
    final achieved = remaining <= 0;
    final progress =
        threshold > 0 ? (subtotal / threshold).clamp(0.0, 1.0) : 1.0;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: primary.withOpacity(0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: primary.withOpacity(0.18)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                achieved
                    ? Icons.check_circle_rounded
                    : Icons.local_shipping_rounded,
                color: primary,
                size: 20,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  achieved
                      ? 'You unlocked free delivery!'
                      : 'Add ${formatCurrency(context, remaining)} more for free delivery',
                  style: GoogleFonts.plusJakartaSans(
                    color: FoodFlowTheme.ink,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 7,
              color: primary,
              backgroundColor: primary.withOpacity(0.14),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDeliveryAddressCard(Color primary, int? restaurantId) {
    final address = _selectedAddress;
    final hasAddresses = _addresses.isNotEmpty;
    final canDeliver = address?.isDeliverable ?? true;
    final statusText = address?.deliveryStatusLabel ??
        (canDeliver ? 'Delivery address' : 'Outside delivery zone');
    final confirmed = address != null && _isSelectedDeliveryLocationConfirmed;
    final secondary = Theme.of(context).colorScheme.secondary;

    return AccountSurfaceCard(
      radius: 24,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: canDeliver
                      ? primary.withOpacity(0.1)
                      : FoodFlowTheme.danger.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(
                  canDeliver
                      ? Icons.location_on_outlined
                      : Icons.location_off_outlined,
                  color: canDeliver ? primary : FoodFlowTheme.danger,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      statusText,
                      style: TextStyle(
                        color: canDeliver ? primary : FoodFlowTheme.danger,
                        fontSize: 11,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      address?.name ?? 'Select delivery address',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      address?.fullAddress ??
                          'Choose from your saved addresses before checkout.',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.inkSoft,
                        fontSize: 12,
                        height: 1.3,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (address?.phone.trim().isNotEmpty == true) ...[
                      const SizedBox(height: 5),
                      Text(
                        'Phone: ${address!.phone}',
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              TextButton(
                onPressed: _isAddressLoading
                    ? null
                    : () => hasAddresses
                        ? _showAddressSelector()
                        : _addAddressAndRefresh(restaurantId),
                style: TextButton.styleFrom(foregroundColor: primary),
                child: Text(
                  hasAddresses ? 'Change' : 'Add',
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
          if (_isAddressLoading) ...[
            const SizedBox(height: 12),
            LinearProgressIndicator(
              color: primary,
              minHeight: 3,
              backgroundColor: primary.withOpacity(0.1),
            ),
          ],
          if (address != null && canDeliver) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: primary.withOpacity(0.08),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: primary.withOpacity(0.14)),
              ),
              child: Column(
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        confirmed
                            ? Icons.check_circle_rounded
                            : Icons.my_location_rounded,
                        color: primary,
                        size: 21,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              confirmed
                                  ? 'Location confirmed'
                                  : 'Confirm exact delivery pin',
                              style: const TextStyle(
                                color: FoodFlowTheme.ink,
                                fontSize: 13,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              confirmed
                                  ? 'This saved address is ready for delivery.'
                                  : 'Confirm this saved address or move the pin on the map.',
                              style: const TextStyle(
                                color: FoodFlowTheme.inkSoft,
                                fontSize: 11.5,
                                height: 1.3,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  if (!confirmed) ...[
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () => _openDeliveryPinPicker(
                              address: address,
                            ),
                            style: FoodFlowTheme.zomatoOutlineButton(
                              color: secondary,
                              radius: 999,
                              padding: const EdgeInsets.symmetric(vertical: 12),
                            ),
                            icon: const Icon(Icons.map_outlined, size: 17),
                            label: const Text('Pin'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: () {
                              setState(() {
                                _confirmedDeliveryLocationKey =
                                    _deliveryLocationKey(address);
                              });
                            },
                            style: FoodFlowTheme.zomatoPrimaryButton(
                              color: secondary,
                              radius: 999,
                              padding: const EdgeInsets.symmetric(vertical: 13),
                            ),
                            child: const Text('Confirm'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ],
          if (address != null && !canDeliver) ...[
            const SizedBox(height: 10),
            Text(
              'Please change to a deliverable saved address for this restaurant.',
              style: TextStyle(
                color: FoodFlowTheme.danger,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _addAddressAndRefresh(int? restaurantId) async {
    final result = await Navigator.pushNamed(context, '/addresses/add');
    if (!mounted) return;
    if (result == true && restaurantId != null) {
      await _loadAddressesForCart(restaurantId, force: true);
      await _refreshCartSummary();
    }
  }

  void _showAddressSelector() {
    final primary = Theme.of(context).colorScheme.primary;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.only(top: 42),
            child: DraggableScrollableSheet(
              expand: false,
              initialChildSize: 0.78,
              minChildSize: 0.45,
              maxChildSize: 0.92,
              builder: (context, scrollController) {
                return Container(
                  decoration: const BoxDecoration(
                    color: Color(0xFFF5F6FB),
                    borderRadius:
                        BorderRadius.vertical(top: Radius.circular(26)),
                  ),
                  child: ListView(
                    controller: scrollController,
                    padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
                    children: [
                      Row(
                        children: [
                          const Expanded(
                            child: Text(
                              'Select delivery address',
                              style: TextStyle(
                                color: FoodFlowTheme.ink,
                                fontSize: 21,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          IconButton.filled(
                            onPressed: () => Navigator.pop(sheetContext),
                            style: IconButton.styleFrom(
                              backgroundColor: FoodFlowTheme.ink,
                              foregroundColor: Colors.white,
                            ),
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _addressSheetAction(
                        icon: Icons.add_location_alt_outlined,
                        title: 'Add new address',
                        subtitle: 'Save another delivery location',
                        color: primary,
                        onTap: () async {
                          Navigator.pop(sheetContext);
                          await _addAddressAndRefresh(
                            context.read<CartProvider>().restaurant?.id,
                          );
                        },
                      ),
                      if (_selectedAddress != null) ...[
                        const SizedBox(height: 10),
                        _addressSheetAction(
                          icon: Icons.map_outlined,
                          title: 'Pin selected location',
                          subtitle: 'Open full screen map and adjust the pin',
                          color: primary,
                          onTap: () async {
                            final selectedAddress = _selectedAddress;
                            Navigator.pop(sheetContext);
                            if (selectedAddress != null) {
                              await _openDeliveryPinPicker(
                                address: selectedAddress,
                              );
                            }
                          },
                        ),
                      ],
                      const SizedBox(height: 16),
                      const Text(
                        'Saved addresses',
                        style: TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      if (_addresses.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 16),
                          child: Text(
                            'No saved addresses yet.',
                            style: TextStyle(
                              color: FoodFlowTheme.muted,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        )
                      else
                        ..._addresses.map(_addressSheetCard),
                    ],
                  ),
                );
              },
            ),
          ),
        );
      },
    );
  }

  Widget _addressSheetAction({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: accountBorder),
        ),
        child: Row(
          children: [
            Icon(icon, color: color, size: 25),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded,
                color: FoodFlowTheme.inkSoft),
          ],
        ),
      ),
    );
  }

  Widget _addressSheetCard(app_address.Address address) {
    final primary = Theme.of(context).colorScheme.primary;
    final selected = address.id == _selectedAddress?.id;
    final canDeliver = address.isDeliverable;
    final distanceText = address.distanceKm == null
        ? null
        : '${address.distanceKm!.toStringAsFixed(address.distanceKm! >= 10 ? 0 : 1)} km';

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: canDeliver
            ? () async {
                Navigator.pop(context);
                setState(() => _setSelectedAddress(address));
                try {
                  await _api.post(
                    '${ApiConstants.setDefaultAddress}/${address.id}',
                  );
                } catch (e) {
                  debugPrint('Set default address error: $e');
                }
                await _refreshCartSummary();
                await Future<void>.delayed(const Duration(milliseconds: 120));
                if (mounted) {
                  await _confirmSelectedDeliveryLocation();
                }
              }
            : null,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: selected ? primary : accountBorder,
              width: selected ? 1.3 : 1,
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                address.name.toLowerCase().contains('work')
                    ? Icons.business_center_outlined
                    : Icons.home_outlined,
                color: canDeliver ? primary : FoodFlowTheme.muted,
                size: 28,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      address.deliveryStatusLabel ??
                          (canDeliver
                              ? 'DELIVERS TO'
                              : 'DOES NOT DELIVER HERE'),
                      style: TextStyle(
                        color: canDeliver ? primary : FoodFlowTheme.danger,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      address.name,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      address.fullAddress,
                      style: const TextStyle(
                        color: FoodFlowTheme.inkSoft,
                        fontSize: 12,
                        height: 1.3,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (distanceText != null) ...[
                      const SizedBox(height: 6),
                      Text(
                        distanceText,
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (selected)
                Icon(Icons.check_circle_rounded, color: primary, size: 22),
            ],
          ),
        ),
      ),
    );
  }

  static Widget _restaurantIcon(BuildContext context) {
    return Container(
      color: const Color(0xFFF8EFE7),
      child: Icon(
        Icons.restaurant_rounded,
        color: Theme.of(context).colorScheme.primary,
        size: 34,
      ),
    );
  }

  Future<void> _proceedToCheckout(
      BuildContext context, int? restaurantId) async {
    final restaurant = context.read<CartProvider>().restaurant;
    final orderType = _effectiveOrderType(restaurant);
    if (orderType == 'takeaway') {
      await _placeOrderFromCart();
      return;
    }

    if (_selectedAddress == null) {
      if (_addresses.isNotEmpty) {
        _showAddressSelector();
      } else {
        await _addAddressAndRefresh(restaurantId);
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a delivery address')),
      );
      return;
    }

    if (_selectedAddress?.isDeliverable == false) {
      _showAddressSelector();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please choose an address in this delivery zone'),
        ),
      );
      return;
    }

    if (!_isSelectedDeliveryLocationConfirmed) {
      final confirmed = await _confirmSelectedDeliveryLocation();
      if (!confirmed) return;
    }

    await _placeOrderFromCart();
  }

  Future<void> _placeOrderFromCart() async {
    if (_isPlacingOrder) return;

    final cart = context.read<CartProvider>();
    final auth = context.read<AuthProvider>();
    final orderProvider = context.read<OrderProvider>();
    final restaurant = cart.restaurant;
    final address = _selectedAddress;
    final orderType = _effectiveOrderType(restaurant);
    final isTakeaway = orderType == 'takeaway';
    final user = auth.currentUser;

    if (restaurant == null || cart.paidItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Your cart is empty')),
      );
      return;
    }

    if (!isTakeaway && (address == null || !address.isDeliverable)) {
      await _proceedToCheckout(context, restaurant.id);
      return;
    }

    if (!isTakeaway && !_isSelectedDeliveryLocationConfirmed) {
      await _proceedToCheckout(context, restaurant.id);
      return;
    }

    final authProvider = context.read<AuthProvider>();
    await authProvider.loadUser(forceRefresh: true);
    if (!mounted) return;
    final refreshedUser = authProvider.currentUser;
    debugPrint(
        '[PaymentTrace] checkout.settings.refreshed source=place default=${refreshedUser?.paymentGatewayProvider ?? '-'} enabled=${refreshedUser?.enabledPaymentGatewayKeys.join(',') ?? '-'} gateways=${refreshedUser?.paymentGateways.map((gateway) => '${gateway.key}:${gateway.enabled}:${gateway.selected}').join('|') ?? '-'}');
    final paymentMethod = _orderPaymentMethod(refreshedUser);
    if (paymentMethod == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Online payment is not available right now')),
      );
      return;
    }

    setState(() => _isPlacingOrder = true);
    final cartCouponCode = cart.promotionCouponCode;
    final couponCode =
        _selectedCouponCode.isNotEmpty ? _selectedCouponCode : cartCouponCode;
    final addressPhone = !isTakeaway ? (address?.phone.trim() ?? '') : '';
    final userPhone = user?.phone.trim() ?? '';
    final checkoutPhone = addressPhone.isNotEmpty ? addressPhone : userPhone;
    final orderData = <String, dynamic>{
      'restaurant_id': restaurant.id,
      'items': cart.paidItems
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
          .toList(),
      if (!isTakeaway && address != null) ...{
        'delivery_address_id': address.id,
        'delivery_address': address.fullAddress,
        'delivery_lat': address.latitude,
        'delivery_lng': address.longitude,
      },
      'order_type': orderType,
      'customer_name': refreshedUser?.name,
      'customer_email': refreshedUser?.email,
      'email': refreshedUser?.email,
      'customer_phone': checkoutPhone,
      'phone': checkoutPhone,
      'contact': checkoutPhone,
      'payment_method': paymentMethod,
      'special_instructions': _cartNote.trim(),
      if (couponCode.isNotEmpty) 'coupon_code': couponCode,
      if (_scheduledTime != null)
        'scheduled_time': _scheduledTime!.toIso8601String(),
    };
    debugPrint(
        '[PaymentTrace] checkout.place resolvedPayment=$paymentMethod selectedMethod=$_selectedPaymentMethod selectedGateway=$_selectedGatewayKey onlineMode=${_selectedOnlineModeFor(refreshedUser)} cashfreeUpiApp=${_selectedCashfreeUpiAppId ?? '-'} userDefault=${refreshedUser?.paymentGatewayProvider ?? '-'} enabledGateways=${refreshedUser?.enabledPaymentGatewayKeys.join(',') ?? '-'}');
    debugPrint(
      '[SwaadPromoCart] create order request: restaurant=${restaurant.id}, '
      'paidItems=${cart.paidItems.map((item) => '${item.menuItem.id}x${item.quantity}').join(',')}, '
      'cartRewardItems=${cart.promotionRewardItems.map((item) => '${item.promotionId}:${item.menuItem.id}x${item.quantity}').join(',')}, '
      'summaryRewards=${_rewardLineDebug(_rewardLines)}, type=$orderType, payment=$paymentMethod',
    );

    if (_selectedPaymentMethod == 'online') {
      final order = await _startOnlineCheckoutPayment(
        orderData: orderData,
        gateway: paymentMethod,
        paymentMode: _selectedOnlineModeFor(refreshedUser),
        cashfreeUpiAppId: _selectedCashfreeUpiAppId,
      );
      if (!mounted) return;

      if (order == null) {
        setState(() => _isPlacingOrder = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Payment was not confirmed. Order was not placed.'),
          ),
        );
        return;
      }

      setState(() => _isPlacingOrder = false);
      _navigateToCartOrderConfirmation(order, cart);
      return;
    }

    final order = await orderProvider.createOrder(orderData);

    if (!mounted) return;

    if (order == null) {
      setState(() => _isPlacingOrder = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(orderProvider.error ?? 'Failed to place order'),
          backgroundColor: FoodFlowTheme.danger,
        ),
      );
      return;
    }

    setState(() => _isPlacingOrder = false);
    _navigateToCartOrderConfirmation(order, cart);
  }

  Future<Order?> _startOnlineCheckoutPayment({
    required Map<String, dynamic> orderData,
    required String gateway,
    required String paymentMode,
    String? cashfreeUpiAppId,
  }) async {
    try {
      debugPrint(
          '[PaymentTrace] checkout.payForCheckout.call gateway=$gateway mode=$paymentMode cashfreeUpiApp=${cashfreeUpiAppId ?? '-'} orderPaymentMethod=${orderData['payment_method']} itemCount=${orderData['items'] is List ? (orderData['items'] as List).length : '-'}');
      return await _paymentService.payForCheckout(
        orderData: orderData,
        gateway: gateway,
        paymentMode: paymentMode,
        cashfreeUpiAppId: cashfreeUpiAppId,
      );
    } catch (error) {
      debugPrint('[PaymentTrace] checkout.payForCheckout.error $error');
      if (!mounted) return null;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error.toString().replaceFirst('Bad state: ', ''),
          ),
          backgroundColor: FoodFlowTheme.danger,
        ),
      );
      return null;
    }
  }

  String? _orderPaymentMethod(User? user) {
    if (_selectedPaymentMethod == 'cod' || _selectedPaymentMethod == 'wallet') {
      return _selectedPaymentMethod;
    }
    return _effectiveGatewayProvider(user);
  }

  void _navigateToCartOrderConfirmation(Order order, CartProvider cart) {
    final restaurantName =
        cart.restaurant?.name ?? order.restaurant?.name ?? '';
    final restaurantLogoUrl =
        cart.restaurant?.logoUrl ?? order.restaurant?.logoUrl ?? '';
    final user = context.read<AuthProvider>().currentUser;
    final scratchCards = context.read<OrderProvider>().lastCreatedScratchCards;
    cart.clearCart();

    Navigator.pushReplacementNamed(
      context,
      '/order/confirmation',
      arguments: {
        'orderId': order.id,
        'orderNumber': order.orderNumber,
        'restaurantName': restaurantName,
        'restaurantLogoUrl': restaurantLogoUrl,
        'paymentGatewayName':
            _selectedPaymentMethod == 'online' ? _gatewayDisplayName(user) : '',
        'paymentGatewayLogoUrl':
            _selectedPaymentMethod == 'online' ? _gatewayLogoUrl(user) : '',
        'subtotal': order.subtotal,
        'discount': order.discount,
        'savedAmount': ((_summaryDiscount ?? order.discount) +
                (_summaryEmbeddedItemDiscount ?? 0))
            .clamp(0.0, double.infinity)
            .toDouble(),
        'deliveryFee': order.deliveryFee,
        'platformFee': order.platformFee,
        'tax': order.tax,
        'taxLabel': _summaryTaxLabel,
        'total': order.total,
        'scratchCards': scratchCards,
      },
    );
  }

  static Widget _billRow(
    BuildContext context,
    String label,
    double value, {
    bool isTotal = false,
    bool isSavings = false,
    VoidCallback? onTap,
    bool tappable = false,
  }) {
    final row = Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Expanded(
            child: Row(
              children: [
                Flexible(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: isTotal ? 16 : 14,
                      fontWeight: isTotal ? FontWeight.w700 : FontWeight.w500,
                      color:
                          isTotal ? FoodFlowTheme.ink : FoodFlowTheme.inkSoft,
                      decoration: tappable ? TextDecoration.underline : null,
                      decorationStyle: TextDecorationStyle.dotted,
                    ),
                  ),
                ),
                if (tappable) ...[
                  const SizedBox(width: 5),
                  const Icon(
                    Icons.info_outline_rounded,
                    size: 15,
                    color: FoodFlowTheme.muted,
                  ),
                ],
              ],
            ),
          ),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '${isSavings ? '-' : ''}${formatCurrency(context, value)}',
                style: TextStyle(
                  fontSize: isTotal ? 16 : 14,
                  fontWeight: isTotal ? FontWeight.w800 : FontWeight.w600,
                  color:
                      isSavings ? FoodFlowTheme.successDark : FoodFlowTheme.ink,
                ),
              ),
            ],
          ),
        ],
      ),
    );
    if (onTap == null) return row;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: row,
    );
  }

  static Widget _billDeliveryRow(
    BuildContext context,
    String label,
    double deliveryFee,
    double deliveryDiscount,
  ) {
    final isFree = deliveryFee > 0 && deliveryDiscount >= deliveryFee - 0.01;
    final payable =
        (deliveryFee - deliveryDiscount).clamp(0, double.infinity).toDouble();

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: FoodFlowTheme.inkSoft,
              ),
            ),
          ),
          if (isFree) ...[
            Text(
              formatCurrency(context, deliveryFee),
              style: const TextStyle(
                color: FoodFlowTheme.muted,
                fontSize: 13,
                decoration: TextDecoration.lineThrough,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(width: 7),
            const Text(
              'FREE',
              style: TextStyle(
                color: FoodFlowTheme.successDark,
                fontSize: 14,
                fontWeight: FontWeight.w900,
              ),
            ),
          ] else
            Text(
              formatCurrency(context, payable),
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
        ],
      ),
    );
  }

  static Widget _billTextRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: FoodFlowTheme.inkSoft,
              ),
            ),
          ),
          Text(
            value,
            style: const TextStyle(
              color: Color(0xFF168A35),
              fontSize: 14,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  static Future<void> _showClearCartDialog(
    BuildContext context,
    CartProvider cart,
  ) {
    return showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog(
        backgroundColor: const Color(0xFFF5F6FB),
        insetPadding: const EdgeInsets.symmetric(horizontal: 22),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFE8ECF3)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 52,
                      height: 52,
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF3E8),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Icon(
                        Icons.delete_outline_rounded,
                        size: 27,
                        color: Theme.of(dialogContext).colorScheme.primary,
                      ),
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'Clear cart?',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'This will remove all selected items from your current restaurant cart.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13,
                        height: 1.35,
                        color: FoodFlowTheme.muted,
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => Navigator.pop(dialogContext),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: FoodFlowTheme.ink,
                              side: const BorderSide(color: FoodFlowTheme.line),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                              padding: const EdgeInsets.symmetric(vertical: 13),
                            ),
                            child: const Text('Keep items'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: FilledButton(
                            onPressed: () {
                              cart.clearCart();
                              Navigator.pop(dialogContext);
                            },
                            style: FilledButton.styleFrom(
                              backgroundColor:
                                  Theme.of(dialogContext).colorScheme.primary,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                              padding: const EdgeInsets.symmetric(vertical: 13),
                            ),
                            child: const Text('Clear cart'),
                          ),
                        ),
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

  void _addMoreItems(BuildContext context) {
    if (widget.onAddMore != null) {
      widget.onAddMore!();
      return;
    }

    if (Navigator.of(context).canPop()) {
      Navigator.of(context).pop();
      return;
    }

    Navigator.of(context).pushReplacementNamed('/home');
  }
}

class _CartNoteSheet extends StatefulWidget {
  const _CartNoteSheet({required this.initialNote});

  final String initialNote;

  @override
  State<_CartNoteSheet> createState() => _CartNoteSheetState();
}

class _CartNoteSheetState extends State<_CartNoteSheet> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialNote);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: AnimatedPadding(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOut,
        padding: EdgeInsets.only(
          left: 10,
          right: 10,
          bottom: MediaQuery.viewInsetsOf(context).bottom + 10,
        ),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Note for the restaurant',
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _controller,
                minLines: 3,
                maxLines: 5,
                textInputAction: TextInputAction.newline,
                decoration: InputDecoration(
                  hintText: 'Add cooking or packing instructions',
                  filled: true,
                  fillColor: const Color(0xFFF7F8FC),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: const BorderSide(color: Color(0xFFE4E8F0)),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: const BorderSide(color: Color(0xFFE4E8F0)),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () {
                    FocusScope.of(context).unfocus();
                    Navigator.pop(context, _controller.text.trim());
                  },
                  style: FilledButton.styleFrom(
                    backgroundColor: FoodFlowTheme.brandPrimary(context),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Text('Save note'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CartPanel extends StatelessWidget {
  const _CartPanel({
    required this.child,
    this.padding = const EdgeInsets.all(12),
  });

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE9EDF3)),
      ),
      child: child,
    );
  }
}

class _MiniQuantityStepper extends StatelessWidget {
  const _MiniQuantityStepper({
    required this.quantity,
    required this.onMinus,
    required this.onPlus,
  });

  final int quantity;
  final VoidCallback onMinus;
  final VoidCallback onPlus;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 32,
      decoration: BoxDecoration(
        color: FoodFlowTheme.success.withOpacity(0.10),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: FoodFlowTheme.brandPrimary(context)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _MiniStepButton(icon: Icons.remove_rounded, onTap: onMinus),
          SizedBox(
            width: 32,
            child: Text(
              '$quantity',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 13,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          _MiniStepButton(icon: Icons.add_rounded, onTap: onPlus),
        ],
      ),
    );
  }
}

class _MiniStepButton extends StatelessWidget {
  const _MiniStepButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: SizedBox(
        width: 28,
        height: 32,
        child: Icon(
          icon,
          size: 17,
          color: FoodFlowTheme.brandPrimary(context),
        ),
      ),
    );
  }
}

class _CartActionPill extends StatelessWidget {
  const _CartActionPill({
    required this.icon,
    required this.label,
    required this.onTap,
    this.strong = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool strong;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        height: 40,
        padding: const EdgeInsets.symmetric(horizontal: 11),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE4E8F0)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon,
                size: 18,
                color: strong
                    ? FoodFlowTheme.brandPrimary(context)
                    : FoodFlowTheme.muted),
            const SizedBox(width: 7),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: strong
                      ? FoodFlowTheme.brandPrimary(context)
                      : FoodFlowTheme.inkSoft,
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({
    required this.icon,
    required this.title,
    this.subtitle,
    this.titleColor = FoodFlowTheme.ink,
    this.trailing = false,
    this.onTap,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final Color titleColor;
  final bool trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final row = Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: FoodFlowTheme.inkSoft),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: titleColor,
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (subtitle?.trim().isNotEmpty == true) ...[
                const SizedBox(height: 4),
                Text(
                  subtitle!,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    height: 1.25,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ],
          ),
        ),
        if (trailing)
          const Icon(Icons.chevron_right_rounded,
              size: 20, color: FoodFlowTheme.muted),
      ],
    );

    if (onTap == null) return row;
    return InkWell(onTap: onTap, child: row);
  }
}

class _PanelDivider extends StatelessWidget {
  const _PanelDivider();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 12),
      child: Divider(height: 1, color: Color(0xFFE9EDF3)),
    );
  }
}

class _BillSummaryLine extends StatelessWidget {
  const _BillSummaryLine({
    required this.total,
    required this.gross,
    required this.saved,
    required this.onTap,
  });

  final double total;
  final double gross;
  final double saved;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Row(
        children: [
          const Icon(Icons.receipt_long_outlined,
              size: 18, color: FoodFlowTheme.inkSoft),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Text(
                      'Total Bill',
                      style: TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(width: 6),
                    if (saved > 0)
                      Text(
                        formatCurrency(context, gross),
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 12,
                          decoration: TextDecoration.lineThrough,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    const SizedBox(width: 4),
                    Text(
                      formatCurrency(context, total),
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  saved > 0
                      ? 'You saved ${formatCurrency(context, saved)}'
                      : 'Incl. taxes and charges',
                  style: TextStyle(
                    color: saved > 0
                        ? const Color(0xFF2E7BE6)
                        : FoodFlowTheme.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded,
              size: 20, color: FoodFlowTheme.muted),
        ],
      ),
    );
  }
}

class _PromotionProgressRow extends StatelessWidget {
  final String message;
  final double progress;
  final bool unlocked;
  final Color primary;

  const _PromotionProgressRow({
    required this.message,
    required this.progress,
    required this.unlocked,
    required this.primary,
  });

  @override
  Widget build(BuildContext context) {
    final color = unlocked ? FoodFlowTheme.successDark : primary;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                unlocked ? Icons.check_circle_rounded : Icons.local_offer,
                color: color,
                size: 16,
              ),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  message,
                  style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 6,
              color: color,
              backgroundColor: color.withOpacity(0.14),
            ),
          ),
        ],
      ),
    );
  }
}

class _PromotionRewardCartCard extends StatelessWidget {
  final Map<String, dynamic> line;

  const _PromotionRewardCartCard({required this.line});

  @override
  Widget build(BuildContext context) {
    final imageUrl = (line['image_url'] ?? line['image'])?.toString() ?? '';
    final name = line['name']?.toString() ??
        line['title']?.toString() ??
        'Promotion reward';
    final quantity = _intValue(line['quantity'], fallback: 1);
    final unitPrice = _doubleValue(line['unit_price']);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFBFE7C8)),
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
              child: imageUrl.isNotEmpty
                  ? AppCachedImage(
                      imageUrl: imageUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _rewardPlaceholder(),
                    )
                  : _rewardPlaceholder(),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF8F1),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: const Color(0xFFBFE7C8)),
                  ),
                  child: const Text(
                    'PROMOTION REWARD',
                    style: TextStyle(
                      color: Color(0xFF168A35),
                      fontSize: 10,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 16,
                    height: 1.2,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Text(
                      'Qty $quantity',
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const Spacer(),
                    if (unitPrice > 0) ...[
                      Text(
                        formatCurrency(context, unitPrice),
                        style: const TextStyle(
                          color: FoodFlowTheme.faint,
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                      const SizedBox(width: 8),
                    ],
                    const Text(
                      'FREE',
                      style: TextStyle(
                        color: Color(0xFF168A35),
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
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

  static int _intValue(dynamic value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static double _doubleValue(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  static Widget _rewardPlaceholder() {
    return Container(
      color: const Color(0xFFEFF8F1),
      child: const Icon(
        Icons.card_giftcard_rounded,
        color: Color(0xFF168A35),
      ),
    );
  }
}

class _OnlinePaymentModeOption {
  const _OnlinePaymentModeOption({
    required this.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.trailingText,
    this.assetIcon = '',
  });

  final String key;
  final String title;
  final String subtitle;
  final IconData icon;
  final String trailingText;
  final String assetIcon;
}

class _RewardLinePreview extends StatelessWidget {
  final String name;
  final int quantity;

  const _RewardLinePreview({
    required this.name,
    required this.quantity,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          const Icon(
            Icons.card_giftcard_rounded,
            color: Color(0xFF168A35),
            size: 16,
          ),
          const SizedBox(width: 7),
          Expanded(
            child: Text(
              '${quantity <= 1 ? 1 : quantity} x $name added as reward',
              style: const TextStyle(
                color: FoodFlowTheme.inkSoft,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const Text(
            'FREE',
            style: TextStyle(
              color: Color(0xFF168A35),
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _ChargeBreakdownItem {
  const _ChargeBreakdownItem({
    required this.label,
    required this.amount,
    this.rate,
    this.description,
  });

  final String label;
  final double amount;
  final double? rate;
  final String? description;
}

class _ScheduleOption {
  const _ScheduleOption({
    required this.label,
    required this.minutesFromNow,
  });

  final String label;
  final int minutesFromNow;
}

class _TaxBreakdownDialog extends StatelessWidget {
  const _TaxBreakdownDialog({required this.charges});

  final List<_ChargeBreakdownItem> charges;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      alignment: Alignment.center,
      insetPadding: const EdgeInsets.symmetric(horizontal: 44),
      backgroundColor: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 14),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Tax & Other Charges',
              style: TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 16,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 14),
            if (charges.isEmpty)
              const Text(
                'No tax or extra charges on this order.',
                style: TextStyle(
                  color: FoodFlowTheme.inkSoft,
                  fontSize: 13,
                  height: 1.35,
                  fontWeight: FontWeight.w500,
                ),
              )
            else
              ...charges.map(
                (charge) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              charge.rate == null
                                  ? charge.label
                                  : '${charge.label} (${charge.rate!.toStringAsFixed(charge.rate! == charge.rate!.roundToDouble() ? 0 : 2)}%)',
                              style: const TextStyle(
                                color: FoodFlowTheme.ink,
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          Text(
                            formatCurrency(context, charge.amount),
                            style: const TextStyle(
                              color: FoodFlowTheme.ink,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                      if (charge.description?.isNotEmpty == true) ...[
                        const SizedBox(height: 4),
                        Text(
                          charge.description!,
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 11,
                            height: 1.3,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _EmptyCartView extends StatelessWidget {
  final VoidCallback? onBrowseRestaurants;

  const _EmptyCartView({this.onBrowseRestaurants});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        title: const Text('View cart'),
        backgroundColor: Colors.transparent,
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Lottie.asset(
                'assets/animations/empty.json',
                width: 225,
                height: 195,
                fit: BoxFit.contain,
              ),
              const SizedBox(height: 24),
              const Text(
                'Your cart is empty',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  color: FoodFlowTheme.ink,
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                'Add dishes from your favorite restaurant and they will appear here.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 14,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 26),
              FilledButton(
                onPressed: () {
                  if (onBrowseRestaurants != null) {
                    onBrowseRestaurants!();
                    return;
                  }
                  Navigator.of(context)
                      .pushNamedAndRemoveUntil('/home', (route) => false);
                },
                child: const Text('Browse restaurants'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
