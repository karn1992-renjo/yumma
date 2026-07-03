// lib/screens/customer/checkout_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_cashfree_pg_sdk/api/cferrorresponse/cferrorresponse.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfwebcheckoutpayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfsession/cfsession.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfenums.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfexceptions.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:lottie/lottie.dart' hide Marker;
import 'package:provider/provider.dart';
import 'package:flutter_stripe/flutter_stripe.dart' hide Address;
import 'package:geolocator/geolocator.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import '../../providers/auth_provider.dart';
import '../../providers/cart_provider.dart';
import '../../providers/order_provider.dart';
import '../../services/api_service.dart';
import '../../services/app_branding_service.dart';
import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../models/address.dart' as app_address;
import '../../models/order.dart';
import '../../models/menu_item.dart';
import '../../models/user.dart';
import '../../widgets/customer/free_delivery_success_popup.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../utils/phone_number_utils.dart';
import '../../widgets/common/lucide_icon.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/customer/account_chrome.dart';
import '../../widgets/customer/menu_item_card.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen>
    with TickerProviderStateMixin {
  final ApiService _api = ApiService();
  late final Razorpay _razorpay;
  final CFPaymentGatewayService _cashfree = CFPaymentGatewayService();
  final TextEditingController _couponController = TextEditingController();
  final TextEditingController _instructionsController = TextEditingController();
  final TextEditingController _contactPhoneController = TextEditingController();

  List<app_address.Address> _addresses = [];
  app_address.Address? _selectedAddress;
  String _selectedPaymentMethod = 'online';
  String? _selectedGatewayProvider;
  String? _couponCode;
  String? _appliedCouponCode;
  int? _pendingPaymentOrderId;
  String? _pendingCashfreeOrderId;
  String _selectedOnlinePaymentView = 'upi';
  double _discount = 0;
  double _summarySubtotal = 0;
  double _summaryDeliveryFee = 0;
  double _summaryPlatformFee = 0;
  double _summaryTax = 0;
  double _summaryTotal = 0;
  double _walletBalance = 0;
  double? _freeDeliveryThreshold;
  double? _freeDeliveryRemaining;
  double? _summaryDeliveryDistanceKm;
  String _summaryTaxLabel = 'Taxes';
  List<_ChargeBreakdownItem> _summaryTaxBreakdown = [];
  String _orderType = 'delivery';
  bool _dontSendCutlery = false;
  DateTime? _scheduledTime;
  bool _isLoading = true;
  bool _isPlacingOrder = false;
  bool _isValidatingCoupon = false;
  List<dynamic> _eligiblePromos = [];
  List<MenuItem> _suggestedItems = [];
  bool _isLoadingPromos = false;
  double _swipeDrag = 0;
  bool _didNormalizeOrderType = false;
  bool _didEditContactPhone = false;

  bool get _isTakeaway => _orderType == 'takeaway';
  Color get _primary => Theme.of(context).colorScheme.primary;
  Color get _secondary => Theme.of(context).colorScheme.secondary;

  @override
  void initState() {
    super.initState();
    _razorpay = Razorpay();
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handleRazorpaySuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handleRazorpayError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, _handleExternalWallet);
    _cashfree.setCallback(_handleCashfreeSuccess, _handleCashfreeError);
    _contactPhoneController.addListener(_markContactPhoneEdited);
    _syncContactPhone(force: true);
    _loadAddresses();
    _loadWalletBalance();
    _loadEligiblePromos();
    _loadSuggestedItems();
  }

  @override
  void dispose() {
    _razorpay.clear();
    _couponController.dispose();
    _instructionsController.dispose();
    _contactPhoneController.dispose();
    super.dispose();
  }

  Future<void> _loadAddresses() async {
    setState(() => _isLoading = true);
    try {
      final restaurant =
          Provider.of<CartProvider>(context, listen: false).restaurant;
      final response = await _api.get(
        ApiConstants.addresses,
        queryParams: {
          if (restaurant != null) 'restaurant_id': restaurant.id,
        },
      );
      if (response['success'] == true) {
        final List<dynamic> addressesData = response['data'];
        _addresses = addressesData
            .map((json) => app_address.Address.fromJson(json))
            .toList();
        if (_addresses.isNotEmpty) {
          final defaultAddress = _addresses.firstWhere(
            (addr) => addr.isDefault,
            orElse: () => _addresses.first,
          );
          _selectedAddress = _isTakeaway
              ? defaultAddress
              : _addresses.firstWhere(
                  (addr) => addr.isDeliverable,
                  orElse: () => defaultAddress,
                );
        }
        _syncContactPhone();
        await _refreshCheckoutSummary();
      }
    } catch (e) {
      debugPrint('Load addresses error: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _loadEligiblePromos() async {
    final cartProvider = Provider.of<CartProvider>(context, listen: false);
    if (cartProvider.restaurant == null) return;

    setState(() => _isLoadingPromos = true);
    try {
      final response = await _api
          .get(
            ApiConstants.customerRestaurantPromos(cartProvider.restaurant!.id),
          )
          .timeout(const Duration(seconds: 10));

      if (response['success'] == true && response['data'] is List) {
        final List<dynamic> promos = response['data'];
        // Filter to show only active promos
        setState(() {
          _eligiblePromos = promos.where((promo) {
            if (promo is Map<String, dynamic>) {
              final isActive =
                  promo['is_active'] == true || promo['status'] == 'active';
              return isActive;
            }
            return false;
          }).toList();
        });
      }
    } catch (e) {
      debugPrint('Load promos error: $e');
      // Don't show error to user, just continue without promos
    } finally {
      if (mounted) setState(() => _isLoadingPromos = false);
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didNormalizeOrderType) return;
    final restaurant =
        Provider.of<CartProvider>(context, listen: false).restaurant;
    if (restaurant != null && !restaurant.isDelivery && restaurant.isTakeaway) {
      _orderType = 'takeaway';
    }
    final user = Provider.of<AuthProvider>(context, listen: false).currentUser;
    final selectedGateway = _defaultGatewayProvider(user);
    if (selectedGateway != null) {
      _selectedGatewayProvider = selectedGateway;
      _selectedPaymentMethod = 'online';
    } else if (!_isCodAvailable(user)) {
      _selectedPaymentMethod = 'wallet';
    }
    _didNormalizeOrderType = true;
  }

  Future<void> _refreshCheckoutSummary() async {
    final cartProvider = Provider.of<CartProvider>(context, listen: false);
    final restaurant = cartProvider.restaurant;
    if (restaurant == null || cartProvider.items.isEmpty) {
      return;
    }

    try {
      final response = await _api.post(
        ApiConstants.orderSummary,
        data: {
          'restaurant_id': restaurant.id,
          'items': cartProvider.items
              .map(
                (item) => {
                  'id': item.menuItem.id,
                  'quantity': item.quantity,
                  'selected_variant': item.selectedVariant?.toJson(),
                  'selected_add_ons': item.selectedAddOns
                      .map((option) => option.toJson())
                      .toList(),
                },
              )
              .toList(),
          'delivery_address_id': _selectedAddress?.id,
          'delivery_lat': _selectedAddress?.latitude,
          'delivery_lng': _selectedAddress?.longitude,
          'order_type': _orderType,
          'coupon_code': _appliedCouponCode,
        },
      );

      if (response['success'] == true && mounted) {
        final data = Map<String, dynamic>.from(response['data'] ?? {});
        final threshold = _toNullableDouble(data['free_delivery_threshold']);
        final remaining = _toNullableDouble(data['free_delivery_remaining']);
        final celebrate = FreeDeliveryMilestoneTracker.shouldCelebrate(
          eligible: threshold != null,
          achieved: threshold != null && (remaining ?? 0) <= 0,
        );
        setState(() {
          _summarySubtotal = _toDouble(data['subtotal']);
          _summaryDeliveryFee = _toDouble(data['delivery_fee']);
          _summaryPlatformFee = _toDouble(data['platform_fee']);
          _summaryTax = _toDouble(data['tax']);
          _summaryTotal = _toDouble(data['total']);
          _freeDeliveryThreshold = threshold;
          _freeDeliveryRemaining = remaining;
          _summaryDeliveryDistanceKm = _toNullableDouble(
            data['delivery_distance_km'] ??
                (data['eta'] is Map
                    ? (data['eta'] as Map)['travel_distance_km']
                    : null),
          );
          _discount = _toDouble(data['discount']);
          _summaryTaxLabel = data['tax_label']?.toString() ?? 'Taxes';
          _summaryTaxBreakdown = _parseTaxBreakdown(data['tax_breakdown']);
        });
        if (celebrate) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) showFreeDeliverySuccessPopup(context);
          });
        }
      }
    } catch (e) {
      debugPrint('Checkout summary error: $e');
    }
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  List<_ChargeBreakdownItem> _parseTaxBreakdown(dynamic value) {
    if (value is! List) return const <_ChargeBreakdownItem>[];
    return value
        .whereType<Map>()
        .map((item) => _ChargeBreakdownItem(
              label: item['name']?.toString().trim() ?? 'Tax',
              amount: _toDouble(item['amount']),
              rate: _toNullableDouble(item['rate']),
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
    showDialog<void>(
      context: context,
      barrierColor: Colors.black.withOpacity(0.18),
      builder: (_) => _TaxBreakdownDialog(
        charges: _taxAndChargesBreakdown(),
      ),
    );
  }

  double? _toNullableDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  String _deliveryFeeLabel() {
    if (_isTakeaway) return 'Delivery Fee';
    final distance = _summaryDeliveryDistanceKm;
    if (distance == null) return 'Delivery Fee';
    final decimals = distance >= 10 ? 0 : 1;
    return 'Delivery Fee (${distance.toStringAsFixed(decimals)} km)';
  }

  double _displaySubtotal(CartProvider cartProvider) {
    return _summarySubtotal > 0 ? _summarySubtotal : cartProvider.subtotal;
  }

  String _preferredContactPhone() {
    final userPhone =
        Provider.of<AuthProvider>(context, listen: false).currentUser?.phone;
    if (userPhone != null && userPhone.trim().isNotEmpty) {
      return userPhone.trim();
    }

    return _selectedAddress?.phone.trim() ?? '';
  }

  void _setContactPhoneText(String phone) {
    _contactPhoneController.removeListener(_markContactPhoneEdited);
    _contactPhoneController.text = phone;
    _contactPhoneController.addListener(_markContactPhoneEdited);
  }

  void _markContactPhoneEdited() {
    _didEditContactPhone = true;
  }

  void _syncContactPhone({bool force = false}) {
    final phone = _preferredContactPhone();
    if (phone.isEmpty) return;
    if (!force &&
        _didEditContactPhone &&
        _contactPhoneController.text.trim().isNotEmpty) {
      return;
    }

    _setContactPhoneText(phone);
    _didEditContactPhone = false;
  }

  String? _buildOrderInstructions() {
    final parts = <String>[];
    final note = _instructionsController.text.trim();
    if (note.isNotEmpty) {
      parts.add(note);
    }
    if (_dontSendCutlery) {
      parts.add('Cutlery preference: do not send cutlery.');
    }

    if (parts.isEmpty) {
      return null;
    }

    return parts.join('\n');
  }

  Future<void> _placeOrder() async {
    if (!_isTakeaway && _selectedAddress == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Please select a delivery address'),
            backgroundColor: Colors.orange),
      );
      return;
    }

    if (!_isTakeaway && _selectedAddress?.isDeliverable == false) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content:
              Text('Selected address is outside this restaurant delivery area'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    final cartProvider = Provider.of<CartProvider>(context, listen: false);
    final orderProvider = Provider.of<OrderProvider>(context, listen: false);
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final orderPaymentMethod =
        _effectiveOrderPaymentMethod(authProvider.currentUser);
    final rawCustomerPhone = _contactPhoneController.text.trim();

    late final String customerPhone;
    try {
      customerPhone = PhoneNumberUtils.normalizeMobile(
        rawCustomerPhone,
        log: true,
      ).normalizedNumber;
    } on FormatException catch (error) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.message),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (orderPaymentMethod == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Online payment is not available right now'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (cartProvider.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Your cart is empty'),
            backgroundColor: Colors.orange),
      );
      return;
    }

    setState(() => _isPlacingOrder = true);

    final orderData = {
      'restaurant_id': cartProvider.restaurant?.id,
      'items': cartProvider.items
          .map((item) => {
                'id': item.menuItem.id,
                'quantity': item.quantity,
                'selected_variant': item.selectedVariant?.toJson(),
                'selected_add_ons': item.selectedAddOns
                    .map((option) => option.toJson())
                    .toList(),
              })
          .toList(),
      'delivery_address_id': _isTakeaway ? null : _selectedAddress!.id,
      'delivery_address': _isTakeaway ? null : _selectedAddress!.fullAddress,
      'delivery_lat': _isTakeaway ? null : _selectedAddress!.latitude,
      'delivery_lng': _isTakeaway ? null : _selectedAddress!.longitude,
      'order_type': _orderType,
      'customer_name': authProvider.currentUser?.name,
      'customer_phone': customerPhone,
      'payment_method': orderPaymentMethod,
      'coupon_code': _appliedCouponCode,
      'scheduled_time': _scheduledTime?.toIso8601String(),
      'special_instructions': _buildOrderInstructions(),
    };

    final order = await orderProvider.createOrder(orderData);
    if (!mounted) return;

    if (order == null) {
      setState(() => _isPlacingOrder = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text(orderProvider.error ?? 'Failed to place order'),
            backgroundColor: Colors.red),
      );
      return;
    }

    if (_selectedPaymentMethod != 'cod') {
      if (_selectedPaymentMethod == 'wallet') {
        // Process wallet payment
        _navigateToConfirmation(order, cartProvider);
      } else {
        await _startOnlinePayment(order.id, order.total);
      }
      return;
    }

    _navigateToConfirmation(order, cartProvider);
  }

  Future<void> _loadSuggestedItems() async {
    final cartProvider = Provider.of<CartProvider>(context, listen: false);
    final restaurant = cartProvider.restaurant;
    if (restaurant == null) return;

    try {
      final response = await _api
          .get('${ApiConstants.restaurantDetails}/${restaurant.id}/menu');
      final data = response['data'] is Map ? response['data'] as Map : response;
      final rawItems = (data['menu_items'] ??
          data['items'] ??
          data['menu'] ??
          []) as List<dynamic>;
      final cartIds =
          cartProvider.items.map((item) => item.menuItem.id).toSet();
      final matchingDietItems = rawItems
          .whereType<Map<String, dynamic>>()
          .map(MenuItem.fromJson)
          .where((item) =>
              !cartIds.contains(item.id) &&
              _matchesSuggestionDiet(item, cartProvider))
          .toList(growable: false);
      final highlightedItems = matchingDietItems
          .where((item) =>
              item.isBestseller ||
              item.isRecommended ||
              item.isCombo ||
              item.isNew)
          .toList(growable: false);
      final List<MenuItem> items =
          (highlightedItems.isNotEmpty ? highlightedItems : matchingDietItems)
              .take(6)
              .toList(growable: false);
      if (mounted) setState(() => _suggestedItems = items);
    } catch (e) {
      debugPrint('Load suggested checkout items error: $e');
    }
  }

  bool _matchesSuggestionDiet(MenuItem suggestion, CartProvider cartProvider) {
    if (cartProvider.items.isEmpty) return true;

    final allVeg = cartProvider.items.every((item) => item.menuItem.isVeg);
    if (allVeg) return suggestion.isVeg;

    final allNonVeg = cartProvider.items.every((item) => !item.menuItem.isVeg);
    if (allNonVeg) return !suggestion.isVeg;

    return true;
  }

  Future<void> _confirmLocationBeforeOrder() async {
    if (_isTakeaway || _selectedAddress == null) {
      await _placeOrder();
      return;
    }

    final hasPin = _selectedAddress!.latitude != null &&
        _selectedAddress!.longitude != null;
    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Confirm delivery location',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              Text(_selectedAddress!.fullAddress),
              const SizedBox(height: 12),
              Container(
                height: 220,
                clipBehavior: Clip.antiAlias,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: Colors.grey.shade300),
                ),
                child: hasPin
                    ? GoogleMap(
                        initialCameraPosition: CameraPosition(
                          target: LatLng(
                            _selectedAddress!.latitude!,
                            _selectedAddress!.longitude!,
                          ),
                          zoom: 17,
                        ),
                        markers: {
                          Marker(
                            markerId: const MarkerId('checkout_delivery_pin'),
                            position: LatLng(
                              _selectedAddress!.latitude!,
                              _selectedAddress!.longitude!,
                            ),
                          ),
                        },
                        myLocationButtonEnabled: false,
                        zoomControlsEnabled: false,
                      )
                    : Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text(
                            'This address has no exact geo pin. Edit the address and pin the location before checkout.',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: Colors.grey.shade700),
                          ),
                        ),
                      ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () async {
                        Navigator.pop(context, false);
                        final result = await Navigator.pushNamed(
                          context,
                          '/addresses/edit',
                          arguments: _selectedAddress,
                        );
                        if (result == true) _loadAddresses();
                      },
                      child: const Text('Edit / pin'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed:
                          hasPin ? () => Navigator.pop(context, true) : null,
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

    if (confirmed == true) {
      await _placeOrder();
    }
  }

  void _navigateToConfirmation(Order order, CartProvider cartProvider) {
    if (!mounted) return;
    final restaurantName =
        cartProvider.restaurant?.name ?? order.restaurant?.name ?? '';
    final restaurantLogoUrl =
        cartProvider.restaurant?.logoUrl ?? order.restaurant?.logoUrl ?? '';
    cartProvider.clearCart();

    Navigator.pushReplacementNamed(
      context,
      '/order/confirmation',
      arguments: _buildConfirmationArgs(
        order,
        restaurantName: restaurantName,
        restaurantLogoUrl: restaurantLogoUrl,
      ),
    );
  }

  Map<String, dynamic> _buildConfirmationArgs(
    Order order, {
    String? restaurantName,
    String? restaurantLogoUrl,
  }) {
    final user = Provider.of<AuthProvider>(context, listen: false).currentUser;
    final cartProvider = Provider.of<CartProvider>(context, listen: false);

    return {
      'orderId': order.id,
      'orderNumber': order.orderNumber,
      'restaurantName': restaurantName ?? order.restaurant?.name ?? '',
      'restaurantLogoUrl': restaurantLogoUrl ??
          cartProvider.restaurant?.logoUrl ??
          order.restaurant?.logoUrl ??
          '',
      'paymentGatewayName':
          _selectedPaymentMethod == 'online' ? _gatewayDisplayName(user) : '',
      'paymentGatewayLogoUrl':
          _selectedPaymentMethod == 'online' ? _gatewayLogoUrl(user) : '',
      'subtotal': order.subtotal,
      'discount': order.discount,
      'deliveryFee': order.deliveryFee,
      'platformFee': order.platformFee,
      'tax': order.tax,
      'taxLabel': _summaryTaxLabel,
      'total': order.total,
      'couponCode': _appliedCouponCode,
      'scheduledTime':
          (_scheduledTime ?? order.scheduledTime)?.toIso8601String(),
    };
  }

  Future<void> _startOnlinePayment(int orderId, double amount) async {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final user = authProvider.currentUser;
    final provider = _effectiveGatewayProvider(user);

    try {
      if (provider == null) {
        throw Exception('Online payment is not available right now');
      }

      final response = await _api.post(ApiConstants.createPayment, data: {
        'order_id': orderId,
        'payment_method': provider,
      });

      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Unable to start payment');
      }

      final data = Map<String, dynamic>.from(response['data'] ?? {});
      final branding = await AppBrandingService.instance.loadBranding();
      final paymentBrandName = branding.displayName;
      final paymentBrandColor =
          '#${_primary.value.toRadixString(16).padLeft(8, '0').substring(2).toUpperCase()}';

      if (provider == 'razorpay') {
        _pendingPaymentOrderId = orderId;
        _razorpay.open({
          'key': data['key'],
          'amount': data['amount'],
          'currency': data['currency'] ?? 'INR',
          'name': paymentBrandName,
          'description': 'Order payment',
          'order_id': data['order_id'],
          'prefill': {
            'contact': user?.phone ?? _selectedAddress?.phone ?? '',
            'email': user?.email ?? '',
          },
          'theme': {'color': paymentBrandColor},
        });
        return;
      }

      if (provider == 'stripe') {
        await _processStripePayment(orderId, data);
        return;
      }

      if (provider == 'cashfree') {
        await _processCashfreePayment(orderId, data);
        return;
      }

      setState(() => _isPlacingOrder = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Unsupported payment method: $provider'),
          backgroundColor: Colors.red,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isPlacingOrder = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
      );
    }
  }

  Future<void> _processStripePayment(
      int orderId, Map<String, dynamic> paymentData) async {
    try {
      final clientSecret = paymentData['client_secret'] as String?;
      final publishableKey = paymentData['publishable_key']?.toString();
      if (clientSecret == null) {
        throw Exception('No client secret from server');
      }
      if (publishableKey != null && publishableKey.isNotEmpty) {
        Stripe.publishableKey = publishableKey;
        await Stripe.instance.applySettings();
      }

      await Stripe.instance.initPaymentSheet(
        paymentSheetParameters: SetupPaymentSheetParameters(
          paymentIntentClientSecret: clientSecret,
          style: ThemeMode.system,
          merchantDisplayName:
              (await AppBrandingService.instance.loadBranding()).displayName,
          googlePay: const PaymentSheetGooglePay(merchantCountryCode: 'IN'),
          applePay: const PaymentSheetApplePay(merchantCountryCode: 'IN'),
        ),
      );

      await Stripe.instance.presentPaymentSheet();

      await _verifyStripePayment(orderId, clientSecret);
    } on StripeException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
              'Stripe Error: ${e.error.localizedMessage ?? e.error.message}'),
          backgroundColor: Colors.red,
        ),
      );
      setState(() => _isPlacingOrder = false);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text('Payment failed: $e'), backgroundColor: Colors.red),
      );
      setState(() => _isPlacingOrder = false);
    }
  }

  Future<void> _processCashfreePayment(
    int orderId,
    Map<String, dynamic> paymentData,
  ) async {
    final cashfreeOrderId = paymentData['order_id']?.toString();
    final paymentSessionId = paymentData['payment_session_id']?.toString();
    if (cashfreeOrderId == null ||
        cashfreeOrderId.isEmpty ||
        paymentSessionId == null ||
        paymentSessionId.isEmpty) {
      throw Exception('Cashfree payment session missing from server');
    }

    _pendingPaymentOrderId = orderId;
    _pendingCashfreeOrderId = cashfreeOrderId;

    try {
      final environment =
          paymentData['environment']?.toString().toLowerCase() == 'sandbox'
              ? CFEnvironment.SANDBOX
              : CFEnvironment.PRODUCTION;
      final session = CFSessionBuilder()
          .setEnvironment(environment)
          .setOrderId(cashfreeOrderId)
          .setPaymentSessionId(paymentSessionId)
          .build();
      final payment = CFWebCheckoutPaymentBuilder().setSession(session).build();
      _cashfree.doPayment(payment);
    } on CFException catch (e) {
      throw Exception(e.message);
    }
  }

  Future<void> _verifyStripePayment(int orderId, String clientSecret) async {
    try {
      // Extract Payment Intent ID from client secret
      final paymentIntentId = clientSecret.split('_secret_')[0];

      final response = await _api.post(ApiConstants.verifyPayment, data: {
        'order_id': orderId,
        'payment_method': 'stripe',
        'payment_id': paymentIntentId,
        'stripe_payment_intent_id': paymentIntentId,
      });

      if (response['success'] == true) {
        if (!mounted) return;
        Provider.of<CartProvider>(context, listen: false).clearCart();
        Navigator.pushReplacementNamed(context, '/order/track',
            arguments: orderId);
      } else {
        throw Exception(response['message'] ?? 'Payment verification failed');
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
      );
      setState(() => _isPlacingOrder = false);
    }
  }

  Future<void> _handleRazorpaySuccess(PaymentSuccessResponse response) async {
    final orderId = _pendingPaymentOrderId;
    if (orderId == null) return;

    try {
      final verifyResponse = await _api.post(ApiConstants.verifyPayment, data: {
        'order_id': orderId,
        'payment_id': response.paymentId,
        'payment_method': 'razorpay',
        'razorpay_order_id': response.orderId,
        'razorpay_signature': response.signature,
      });

      if (!mounted) return;
      if (verifyResponse['success'] == true) {
        final authProvider = Provider.of<AuthProvider>(context, listen: false);
        final cartProvider = Provider.of<CartProvider>(context, listen: false);
        final orderProvider =
            Provider.of<OrderProvider>(context, listen: false);
        final order = await orderProvider.fetchOrderDetails(orderId,
                notifyLoading: false) ??
            orderProvider.currentOrder;
        final restaurantName =
            cartProvider.restaurant?.name ?? order?.restaurant?.name ?? '';
        final restaurantLogoUrl = cartProvider.restaurant?.logoUrl ??
            order?.restaurant?.logoUrl ??
            '';
        cartProvider.clearCart();
        Navigator.pushReplacementNamed(
          context,
          '/order/confirmation',
          arguments: order != null
              ? _buildConfirmationArgs(
                  order,
                  restaurantName: restaurantName,
                  restaurantLogoUrl: restaurantLogoUrl,
                )
              : {
                  'orderId': orderId,
                  'orderNumber': 'ORD$orderId',
                  'restaurantName': restaurantName,
                  'restaurantLogoUrl': restaurantLogoUrl,
                  'paymentGatewayName': _gatewayDisplayName(
                    authProvider.currentUser,
                  ),
                  'paymentGatewayLogoUrl': _gatewayLogoUrl(
                    authProvider.currentUser,
                  ),
                  'subtotal': _displaySubtotal(cartProvider),
                  'discount': _discount,
                  'deliveryFee': _summaryDeliveryFee,
                  'platformFee': _summaryPlatformFee,
                  'tax': _summaryTax,
                  'taxLabel': _summaryTaxLabel,
                  'total': _summaryTotal > 0
                      ? _summaryTotal
                      : cartProvider.total - _discount,
                  'couponCode': _appliedCouponCode,
                },
        );
      } else {
        throw Exception(
            verifyResponse['message'] ?? 'Payment verification failed');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _isPlacingOrder = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
      );
    }
  }

  void _handleRazorpayError(PaymentFailureResponse response) {
    if (!mounted) return;
    setState(() => _isPlacingOrder = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(response.message ?? 'Payment cancelled or failed'),
        backgroundColor: Colors.red,
      ),
    );
  }

  void _handleExternalWallet(ExternalWalletResponse response) {}

  Future<void> _handleCashfreeSuccess(String orderId) async {
    final pendingOrderId = _pendingPaymentOrderId;
    if (pendingOrderId == null) return;

    await _verifyCashfreePayment(
      orderId: pendingOrderId,
      cashfreeOrderId: _pendingCashfreeOrderId ?? orderId,
    );
  }

  void _handleCashfreeError(CFErrorResponse error, String orderId) {
    if (!mounted) return;
    setState(() => _isPlacingOrder = false);
    final message = error.getMessage();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          message == null || message.trim().isEmpty
              ? 'Payment cancelled'
              : message,
        ),
        backgroundColor: Colors.red,
      ),
    );
  }

  Future<void> _verifyCashfreePayment({
    required int orderId,
    required String cashfreeOrderId,
  }) async {
    try {
      final response = await _api.post(ApiConstants.verifyPayment, data: {
        'order_id': orderId,
        'payment_method': 'cashfree',
        'payment_id': cashfreeOrderId,
      });

      if (response['success'] == true) {
        if (!mounted) return;
        Provider.of<CartProvider>(context, listen: false).clearCart();
        Navigator.pushReplacementNamed(context, '/order/track',
            arguments: orderId);
      } else {
        throw Exception(response['message'] ?? 'Payment verification failed');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _isPlacingOrder = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
      );
    }
  }

  Future<void> _applyCoupon() async {
    final code = _couponController.text.trim();
    if (code.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Enter a coupon code to apply'),
            backgroundColor: Colors.orange),
      );
      return;
    }

    final cartProvider = Provider.of<CartProvider>(context, listen: false);
    if (cartProvider.restaurant == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Select a restaurant and add items to cart first'),
            backgroundColor: Colors.orange),
      );
      return;
    }

    setState(() => _isValidatingCoupon = true);
    try {
      final response = await _api.post(ApiConstants.validateCoupon, data: {
        'code': code,
        'restaurant_id': cartProvider.restaurant!.id,
        'subtotal': cartProvider.subtotal,
      });

      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Invalid coupon');
      }

      final data = response['data'] as Map<String, dynamic>?;
      if (data == null || data['discount_amount'] == null) {
        throw Exception('Coupon is not valid for this order');
      }

      final discountAmount = data['discount_amount'];
      setState(() {
        _discount = discountAmount is num
            ? discountAmount.toDouble()
            : double.tryParse(discountAmount.toString()) ?? 0;
        _appliedCouponCode = data['coupon_code']?.toString() ?? code;
        _couponCode = _appliedCouponCode;
        _couponController.text = _appliedCouponCode!;
      });

      await _refreshCheckoutSummary();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text('Coupon applied: ${_appliedCouponCode!}'),
            backgroundColor: Colors.green),
      );
    } catch (e) {
      setState(() {
        _discount = 0;
        _appliedCouponCode = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _isValidatingCoupon = false);
    }
  }

  void _removeCoupon() {
    setState(() {
      _discount = 0;
      _appliedCouponCode = null;
      _couponController.clear();
    });
    _refreshCheckoutSummary();
  }

  @override
  Widget build(BuildContext context) {
    final cartProvider = Provider.of<CartProvider>(context);
    final platformFee = _summaryPlatformFee;
    final gst = _summaryTax;
    final subtotal = _displaySubtotal(cartProvider);
    final total = _summaryTotal > 0
        ? _summaryTotal
        : subtotal + _summaryDeliveryFee + platformFee + gst - _discount;

    return Scaffold(
      backgroundColor: accountCanvas,
      body: SafeArea(
        bottom: false,
        child: Stack(
          children: [
            Positioned.fill(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _addresses.isEmpty &&
                          !_isTakeaway &&
                          !(cartProvider.restaurant?.isTakeaway ?? false)
                      ? _buildEmptyAddressState()
                      : SingleChildScrollView(
                          padding: const EdgeInsets.only(bottom: 116),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildCheckoutHeader(cartProvider),
                              _buildSavingsBanner(),
                              _buildOrderTypeSection(cartProvider),
                              _buildCartReviewSection(cartProvider),
                              if (_suggestedItems.isNotEmpty) ...[
                                const SizedBox(height: 4),
                                _buildSuggestedItemsSection(cartProvider),
                              ],
                              const SizedBox(height: 4),
                              _buildCouponSection(cartProvider),
                              const SizedBox(height: 4),
                              _buildDeliveryAndBillingSection(total),
                              const SizedBox(height: 4),
                              _buildPaymentPreviewCard(total),
                              const SizedBox(height: 4),
                              _buildPremiumBillDetails(
                                cartProvider,
                                platformFee,
                                gst,
                                total,
                              ),
                              const SizedBox(height: 4),
                              _buildCancellationPolicyCard(),
                            ],
                          ),
                        ),
            ),
            if (_isPlacingOrder)
              Positioned.fill(
                child: _buildOrderProcessingOverlay(),
              ),
          ],
        ),
      ),
      bottomNavigationBar: _isPlacingOrder
          ? Container(
              height: 122,
              color: Colors.white,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Lottie.asset(
                    'assets/animations/payment.json',
                    width: 98,
                    height: 98,
                    fit: BoxFit.contain,
                  ),
                  const SizedBox(width: 8),
                  const Text(
                    'Processing your order…',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                ],
              ),
            )
          : _buildPremiumPlaceOrderBar(total),
    );
  }

  Future<void> _loadWalletBalance() async {
    try {
      final response = await _api.get(ApiConstants.wallet);
      final data = response is Map ? response['data'] : null;
      final wallet = data is Map ? data['wallet'] : null;
      final balance = wallet is Map ? wallet['balance'] : null;
      if (mounted) {
        setState(() {
          _walletBalance = double.tryParse('$balance') ?? 0;
        });
      }
    } catch (error) {
      debugPrint('Load wallet balance error: $error');
    }
  }

  Widget _buildOrderProcessingOverlay() {
    final gatewayLabel = _selectedPaymentMethod == 'online'
        ? _gatewayDisplayName(
            Provider.of<AuthProvider>(context, listen: false).currentUser,
          )
        : null;
    final subtitle = _selectedPaymentMethod == 'cod'
        ? 'Sending your order to the restaurant.'
        : _selectedPaymentMethod == 'wallet'
            ? 'Reserving your wallet payment and confirming your order.'
            : 'Preparing secure ${gatewayLabel ?? 'online'} payment.';

    return Material(
      color: accountCanvas.withOpacity(0.96),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 24),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Colors.white.withOpacity(0.96),
              FoodFlowTheme.primaryColor.withOpacity(0.08),
              const Color(0xFFFFF7F1),
            ],
          ),
        ),
        child: Center(
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(24, 28, 24, 26),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(32),
              boxShadow: [
                BoxShadow(
                  color: FoodFlowTheme.primaryColor.withOpacity(0.18),
                  blurRadius: 36,
                  offset: const Offset(0, 18),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(
                  width: 210,
                  height: 210,
                  child: Lottie.asset(
                    'assets/animations/payment.json',
                    fit: BoxFit.contain,
                    repeat: true,
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'Processing your order',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  subtitle,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 14,
                    height: 1.4,
                    fontWeight: FontWeight.w500,
                    color: FoodFlowTheme.inkSoft,
                  ),
                ),
                const SizedBox(height: 20),
                ClipRRect(
                  borderRadius: BorderRadius.circular(999),
                  child: const LinearProgressIndicator(
                    minHeight: 7,
                    backgroundColor: Color(0xFFF1E6DE),
                    color: FoodFlowTheme.primaryColor,
                  ),
                ),
                const SizedBox(height: 14),
                const Text(
                  'Please don’t close or go back.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: FoodFlowTheme.muted,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildPlaceOrderBar(double total) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
              color: Colors.black.withOpacity(0.08),
              blurRadius: 15,
              offset: const Offset(0, -5))
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: LayoutBuilder(
            builder: (context, constraints) {
              final maxDrag = constraints.maxWidth - 64;
              final progress = (_swipeDrag / maxDrag).clamp(0.0, 1.0);
              return GestureDetector(
                onHorizontalDragUpdate: (details) {
                  setState(() {
                    _swipeDrag =
                        (_swipeDrag + details.delta.dx).clamp(0, maxDrag);
                  });
                },
                onHorizontalDragEnd: (_) {
                  if (progress > 0.72) {
                    setState(() => _swipeDrag = 0);
                    _confirmLocationBeforeOrder();
                  } else {
                    setState(() => _swipeDrag = 0);
                  }
                },
                child: Container(
                  height: 58,
                  decoration: BoxDecoration(
                    color: FoodFlowTheme.primaryColor,
                    borderRadius: BorderRadius.circular(18),
                    boxShadow: [
                      BoxShadow(
                        color: FoodFlowTheme.primaryColor.withOpacity(0.26),
                        blurRadius: 18,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      Positioned.fill(
                        child: FractionallySizedBox(
                          alignment: Alignment.centerLeft,
                          widthFactor: progress,
                          child: Container(
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.16),
                              borderRadius: BorderRadius.circular(18),
                            ),
                          ),
                        ),
                      ),
                      Text(
                        'Swipe to place order • ${formatCurrency(context, total)}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      AnimatedPositioned(
                        duration: const Duration(milliseconds: 120),
                        left: 5 + _swipeDrag,
                        child: Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(15),
                          ),
                          child: const Icon(
                            Icons.arrow_forward,
                            color: FoodFlowTheme.primaryColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyAddressState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.location_off_outlined,
              size: 80, color: Colors.grey.shade400),
          const SizedBox(height: 16),
          const Text('No Address Found',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Text('Please add a delivery address to continue',
              style: TextStyle(fontSize: 14, color: Colors.grey.shade600)),
          const SizedBox(height: 24),
          ElevatedButton.icon(
            onPressed: () async {
              final result =
                  await Navigator.pushNamed(context, '/addresses/add');
              if (result == true) _loadAddresses();
            },
            icon: const Icon(Icons.add),
            label: const Text('Add New Address'),
            style: ElevatedButton.styleFrom(
              backgroundColor: _primary,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAddressSection() {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 10,
              offset: const Offset(0, 2))
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.location_on, size: 20, color: _primary),
              SizedBox(width: 8),
              Text('Delivery Address',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ],
          ),
          const SizedBox(height: 12),
          if (_selectedAddress != null) ...[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(_selectedAddress!.name,
                          style: const TextStyle(fontWeight: FontWeight.w500)),
                      const SizedBox(height: 4),
                      Text(_selectedAddress!.fullAddress,
                          style: TextStyle(
                              fontSize: 14, color: Colors.grey.shade600)),
                      const SizedBox(height: 4),
                      Text(_selectedAddress!.phone,
                          style: TextStyle(
                              fontSize: 14, color: Colors.grey.shade600)),
                    ],
                  ),
                ),
                if (_addresses.length > 1)
                  TextButton(
                    onPressed: _showAddressSelector,
                    style: TextButton.styleFrom(foregroundColor: _primary),
                    child: const Text('Change'),
                  ),
              ],
            ),
            const SizedBox(height: 12),
          ],
          OutlinedButton.icon(
            onPressed: () async {
              final result =
                  await Navigator.pushNamed(context, '/addresses/add');
              if (result == true) _loadAddresses();
            },
            icon: const Icon(Icons.add, size: 18),
            label: const Text('Add New Address'),
            style: OutlinedButton.styleFrom(
              side: BorderSide(color: _primary),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderItemsSection(CartProvider cartProvider) {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 10,
              offset: const Offset(0, 2))
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Order Items',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          ...cartProvider.items.map((item) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(item.menuItem.name,
                              style:
                                  const TextStyle(fontWeight: FontWeight.w500)),
                          if (item.selectedVariant != null ||
                              item.selectedAddOns.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              [
                                if (item.selectedVariant != null)
                                  item.selectedVariant!.name,
                                ...item.selectedAddOns
                                    .map((option) => option.name),
                              ].join(' • '),
                              style: TextStyle(
                                  fontSize: 12, color: Colors.grey.shade600),
                            ),
                          ],
                          const SizedBox(height: 4),
                          Text('Qty ${item.quantity}',
                              style: TextStyle(
                                  fontSize: 13, color: Colors.grey.shade600)),
                        ],
                      ),
                    ),
                    Text(formatCurrency(context, item.totalPrice),
                        style: const TextStyle(fontWeight: FontWeight.w500)),
                  ],
                ),
              )),
        ],
      ),
    );
  }

  Widget _buildSuggestedItemsSection(CartProvider cartProvider) {
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 14,
                backgroundColor: Color(0xFFF1F3F7),
                child: AppIcon(AppIcons.offer, size: 15),
              ),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Complete your meal with',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 158,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _suggestedItems.length,
              separatorBuilder: (_, __) => const SizedBox(width: 10),
              itemBuilder: (context, index) {
                final item = _suggestedItems[index];
                return SizedBox(
                  width: 120,
                  child: InkWell(
                    onTap: () => _addSuggestedItem(
                      cartProvider: cartProvider,
                      item: item,
                    ),
                    borderRadius: BorderRadius.circular(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Stack(
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(12),
                              child: SizedBox(
                                width: 120,
                                height: 82,
                                child: item.imageUrl.isNotEmpty
                                    ? AppCachedImage(
                                        imageUrl: item.imageUrl,
                                        fit: BoxFit.cover,
                                        errorBuilder: (_, __, ___) =>
                                            _foodThumbFallback(),
                                      )
                                    : _foodThumbFallback(),
                              ),
                            ),
                            Positioned(
                              left: 6,
                              bottom: 6,
                              child: _buildDietBadge(item),
                            ),
                            Positioned(
                              right: 6,
                              bottom: 6,
                              child: InkWell(
                                onTap: () => _addSuggestedItem(
                                  cartProvider: cartProvider,
                                  item: item,
                                ),
                                child: Container(
                                  width: 28,
                                  height: 28,
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(8),
                                    border: Border.all(
                                      color: const Color(0xFF0F8F45),
                                    ),
                                  ),
                                  child: const Icon(
                                    Icons.add,
                                    color: Color(0xFF0F8F45),
                                    size: 18,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          item.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: FoodFlowTheme.ink,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          formatCurrency(context, item.finalPrice),
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: FoodFlowTheme.ink,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  String _promoCode(Map promo) {
    return promo['code']?.toString() ?? promo['coupon_code']?.toString() ?? '';
  }

  String _promoHeadline(Map promo) {
    final discountType = promo['discount_type']?.toString() ?? 'percentage';
    final discountValue = _toDouble(promo['discount_value']);
    if (discountType == 'fixed') {
      return 'Get ${formatCurrency(context, discountValue)} OFF';
    }
    return 'Get ${discountValue.toStringAsFixed(discountValue == discountValue.roundToDouble() ? 0 : 1)}% OFF';
  }

  double _estimatedPromoSavings(Map promo, double subtotal) {
    final discountType = promo['discount_type']?.toString() ?? 'percentage';
    final discountValue = _toDouble(promo['discount_value']);
    final maxDiscount = _toDouble(promo['max_discount_amount']);

    double savings;
    if (discountType == 'fixed') {
      savings = discountValue;
    } else {
      savings = subtotal * (discountValue / 100);
      if (maxDiscount > 0) {
        savings = savings.clamp(0, maxDiscount);
      }
    }

    return savings.clamp(0, subtotal);
  }

  String _promoSupportText(Map promo, CartProvider cartProvider) {
    final minOrder = _toDouble(promo['min_order_amount']);
    if (minOrder > 0 && cartProvider.subtotal < minOrder) {
      final more = minOrder - cartProvider.subtotal;
      return 'Add eligible items worth ${formatCurrency(context, more)} more to unlock';
    }

    final savings = _estimatedPromoSavings(promo, cartProvider.subtotal);
    if (savings > 0) {
      return 'Save ${formatCurrency(context, savings)} with this code';
    }

    return 'Eligible on this restaurant order';
  }

  Future<void> _openPromoSelectionScreen(CartProvider cartProvider) async {
    final selectedCode = await Navigator.push<String>(
      context,
      MaterialPageRoute(
        builder: (_) => _PromoSelectionScreen(
          promos: _eligiblePromos
              .whereType<Map>()
              .map((promo) => Map<String, dynamic>.from(promo))
              .toList(),
          selectedCode: _appliedCouponCode ?? _couponController.text.trim(),
          subtotal: cartProvider.subtotal,
        ),
      ),
    );

    if (selectedCode == null || selectedCode.isEmpty) return;
    _couponController.text = selectedCode;
    _couponCode = selectedCode;
    await _applyCoupon();
  }

  Widget _buildCouponSection(CartProvider cartProvider) {
    final featuredPromo = _eligiblePromos.isNotEmpty &&
            _eligiblePromos.first is Map<String, dynamic>
        ? _eligiblePromos.first as Map<String, dynamic>
        : null;

    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 10,
              offset: const Offset(0, 2))
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Coupons',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
            decoration: BoxDecoration(
              color: const Color(0xFFF7F8FC),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE8EBF3)),
            ),
            child: Row(
              children: [
                const AppIcon(AppIcons.offer, size: 18),
                const SizedBox(width: 10),
                Expanded(
                  child: TextField(
                    controller: _couponController,
                    onChanged: (value) => _couponCode = value,
                    decoration: const InputDecoration(
                      hintText: 'Have a coupon code? Type here',
                      border: InputBorder.none,
                      isDense: true,
                    ),
                  ),
                ),
                TextButton(
                  onPressed: _isValidatingCoupon ? null : _applyCoupon,
                  style: TextButton.styleFrom(
                    foregroundColor: _primary,
                  ),
                  child: _isValidatingCoupon
                      ? const SizedBox(
                          height: 16,
                          width: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text(
                          'APPLY',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                ),
              ],
            ),
          ),
          if (featuredPromo != null) ...[
            const SizedBox(height: 14),
            InkWell(
              onTap: () => _openPromoSelectionScreen(cartProvider),
              borderRadius: BorderRadius.circular(18),
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFFF0F4FF), Color(0xFFFFF4E9)],
                  ),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 36,
                      height: 36,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Center(
                        child: AppIcon(AppIcons.offer, size: 18),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _appliedCouponCode != null
                                ? 'Save ${formatCurrency(context, _discount)} with ${_appliedCouponCode!}'
                                : _promoHeadline(featuredPromo),
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              color: FoodFlowTheme.ink,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _promoSupportText(featuredPromo, cartProvider),
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: FoodFlowTheme.muted,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'View all coupons ›',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: _primary,
                            ),
                          ),
                        ],
                      ),
                    ),
                    OutlinedButton(
                      onPressed: _appliedCouponCode == _promoCode(featuredPromo)
                          ? _removeCoupon
                          : () {
                              _couponController.text =
                                  _promoCode(featuredPromo);
                              _applyCoupon();
                            },
                      style: OutlinedButton.styleFrom(
                        foregroundColor: _primary,
                        side: BorderSide(color: _primary),
                      ),
                      child: Text(
                        _appliedCouponCode == _promoCode(featuredPromo)
                            ? 'REMOVE'
                            : 'APPLY',
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
          if (_appliedCouponCode != null) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.green.shade50,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      'Applied promo: ${_appliedCouponCode!}',
                      style: const TextStyle(fontWeight: FontWeight.w500),
                    ),
                  ),
                  TextButton(
                    onPressed: _removeCoupon,
                    child: Text(
                      'Remove',
                      style: TextStyle(color: _primary),
                    ),
                  ),
                ],
              ),
            ),
          ] else if (_eligiblePromos.isNotEmpty) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF7F8FC),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Text(
                '${_eligiblePromos.length} restaurant coupon${_eligiblePromos.length == 1 ? '' : 's'} available',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: FoodFlowTheme.ink,
                ),
              ),
            ),
          ],
          const SizedBox(height: 12),
          Text(
            'Eligible coupons will be validated against your current restaurant and order total.',
            style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
          ),
        ],
      ),
    );
  }

  Widget _buildInstructionSection() {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.edit_note_rounded, color: _primary),
              SizedBox(width: 8),
              Text(
                'Special Instructions',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _instructionsController,
            minLines: 2,
            maxLines: 3,
            decoration: const InputDecoration(
              hintText: 'Add cooking notes, cutlery requests or delivery tips',
              prefixIcon: Icon(Icons.mode_edit_outline_rounded),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCheckoutHeader(CartProvider cartProvider) {
    final restaurantName = cartProvider.restaurant?.name ?? 'Checkout';
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 4, 8, 4),
      child: Row(
        children: [
          IconButton(
            onPressed: () => Navigator.of(context).maybePop(),
            icon: const AppIcon(AppIcons.arrowBack, size: 20),
            color: Colors.transparent,
            iconSize: 20,
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(minWidth: 34, minHeight: 34),
          ),
          Expanded(
            child: Text(
              restaurantName,
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: FoodFlowTheme.ink,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          IconButton(
            onPressed: () {},
            icon: const AppIcon(AppIcons.share, size: 18),
            color: Colors.transparent,
            iconSize: 18,
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(minWidth: 34, minHeight: 34),
          ),
        ],
      ),
    );
  }

  Widget _buildActionChip({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    bool highlighted = false,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        decoration: BoxDecoration(
          color: highlighted ? const Color(0xFFF1FBF4) : Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color:
                highlighted ? const Color(0xFFB8E3C7) : const Color(0xFFE7EAF0),
          ),
        ),
        child: Row(
          children: [
            Icon(icon, size: 16, color: FoodFlowTheme.inkSoft),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: FoodFlowTheme.ink,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDeliveryAndBillingSection(double total) {
    final savedAmount = _discount > 0 ? _discount : 0;

    return Container(
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          ListTile(
            leading: const AppIcon(AppIcons.schedule, size: 18),
            title: Text(
              _scheduledTime == null
                  ? 'Delivery in 30-35 mins'
                  : 'Scheduled for ${_formatScheduledTime(_scheduledTime!)}',
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: FoodFlowTheme.ink,
              ),
            ),
            trailing: _scheduledTime == null
                ? const AppIcon(AppIcons.chevronRight, size: 16)
                : IconButton(
                    tooltip: 'Clear scheduled time',
                    onPressed: () {
                      setState(() => _scheduledTime = null);
                      _refreshCheckoutSummary();
                    },
                    icon: const AppIcon(AppIcons.close, size: 16),
                  ),
            subtitle: InkWell(
              onTap: _pickScheduledTime,
              child: Text(
                _scheduledTime == null
                    ? 'Want this later? Schedule it'
                    : 'Change scheduled time',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: FoodFlowTheme.ink,
                  decoration: TextDecoration.underline,
                ),
              ),
            ),
          ),
          const Divider(height: 1, indent: 16, endIndent: 16),
          ListTile(
            leading: const Icon(
              Icons.phone_outlined,
              size: 20,
              color: FoodFlowTheme.inkSoft,
            ),
            title: const Text(
              'Contact number',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: FoodFlowTheme.ink,
              ),
            ),
            subtitle: Padding(
              padding: const EdgeInsets.only(top: 8),
              child: TextField(
                controller: _contactPhoneController,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  hintText: 'Enter mobile number',
                  isDense: true,
                  border: OutlineInputBorder(),
                  contentPadding:
                      EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                ),
              ),
            ),
          ),
          const Divider(height: 1, indent: 16, endIndent: 16),
          ListTile(
            onTap: () {},
            leading: const AppIcon(AppIcons.receipt, size: 18),
            title: Row(
              children: [
                Expanded(
                  child: Text(
                    'Total Bill ${formatCurrency(context, total)}',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: FoodFlowTheme.ink,
                    ),
                  ),
                ),
                if (savedAmount > 0)
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE8F0FF),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      'You saved ${formatCurrency(context, savedAmount)}',
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF2F68D8),
                      ),
                    ),
                  ),
              ],
            ),
            subtitle: const Text(
              'Incl. taxes and charges',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: FoodFlowTheme.muted,
              ),
            ),
            trailing: const Icon(Icons.chevron_right),
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentPreviewCard(double total) {
    final user = context.watch<AuthProvider>().currentUser;
    final method = _selectedPaymentMethod == 'online'
        ? _gatewayDisplayName(user)
        : _selectedPaymentMethod == 'wallet'
            ? AppConfig.walletMoneyLabel
            : 'Cash on Delivery';
    final subtitle = _selectedPaymentMethod == 'online'
        ? _gatewaySubtitle(user)
        : _selectedPaymentMethod == 'wallet'
            ? 'Pay directly from your wallet balance'
            : 'Pay when your order arrives';

    return Container(
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: InkWell(
        onTap: () => _showPaymentSelectorSheet(total),
        child: Row(
          children: [
            _buildPaymentMethodIcon(user),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    method,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: FoodFlowTheme.ink,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: FoodFlowTheme.muted,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right),
          ],
        ),
      ),
    );
  }

  Widget _buildCancellationPolicyCard() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Text(
        'CANCELLATION POLICY\nHelp us reduce food waste by avoiding cancellations after placing your order. A cancellation fee may apply after preparation starts.',
        style: TextStyle(
          height: 1.35,
          color: Colors.grey.shade700,
          fontSize: 12,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.2,
        ),
      ),
    );
  }

  Future<void> _showInstructionsSheet() async {
    final controller =
        TextEditingController(text: _instructionsController.text);
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) => Padding(
        padding: EdgeInsets.fromLTRB(
          16,
          20,
          16,
          16 + MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Add a note for the restaurant',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              maxLines: 4,
              decoration: const InputDecoration(
                hintText: 'Less spicy, no onion, ring the bell on arrival...',
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.pop(context, true),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF0F8F45),
                ),
                child: const Text('Save note'),
              ),
            ),
          ],
        ),
      ),
    );

    if (saved == true) {
      setState(() {
        _instructionsController.text = controller.text.trim();
      });
    }
  }

  Future<void> _pickScheduledTime() async {
    final minimumTime = _minimumScheduleTime();
    DateTime selectedDate = DateUtils.dateOnly(_scheduledTime ?? minimumTime);
    DateTime? selectedSlot = _coerceScheduledSlot(
      _scheduledTime,
      selectedDate,
      minimumTime,
    );

    final pickedSlot = await showModalBottomSheet<DateTime>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) => StatefulBuilder(
        builder: (context, setModalState) {
          final availableSlots =
              _availableScheduleSlotsForDate(selectedDate, minimumTime);
          selectedSlot ??=
              availableSlots.isNotEmpty ? availableSlots.first : null;

          return SafeArea(
            child: SizedBox(
              height: MediaQuery.of(context).size.height * 0.72,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Schedule your order',
                            style: TextStyle(
                              fontSize: 19,
                              fontWeight: FontWeight.w900,
                              color: FoodFlowTheme.ink,
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(context),
                          icon: const Icon(Icons.close_rounded),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Choose a delivery or pickup time at least 45 minutes from now.',
                      style: TextStyle(
                        fontSize: 13,
                        color: Colors.grey.shade700,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 16),
                    CalendarDatePicker(
                      initialDate: selectedDate.isBefore(minimumTime)
                          ? minimumTime
                          : selectedDate,
                      firstDate: DateUtils.dateOnly(minimumTime),
                      lastDate: DateUtils.dateOnly(
                        DateTime.now().add(const Duration(days: 7)),
                      ),
                      onDateChanged: (value) {
                        setModalState(() {
                          selectedDate = value;
                          selectedSlot = _coerceScheduledSlot(
                            selectedSlot,
                            selectedDate,
                            minimumTime,
                          );
                        });
                      },
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Available slots',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Colors.grey.shade900,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Expanded(
                      child: availableSlots.isEmpty
                          ? Center(
                              child: Text(
                                'No schedule slots are available for this day yet.',
                                style: TextStyle(
                                  fontSize: 13,
                                  color: Colors.grey.shade600,
                                ),
                                textAlign: TextAlign.center,
                              ),
                            )
                          : SingleChildScrollView(
                              child: Wrap(
                                spacing: 10,
                                runSpacing: 10,
                                children: availableSlots.map((slot) {
                                  final isSelected = selectedSlot == slot;
                                  return ChoiceChip(
                                    label: Text(
                                      DateFormat('hh:mm a').format(slot),
                                    ),
                                    selected: isSelected,
                                    onSelected: (_) {
                                      setModalState(() => selectedSlot = slot);
                                    },
                                  );
                                }).toList(),
                              ),
                            ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        if (_scheduledTime != null)
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () =>
                                  Navigator.pop(context, DateTime(0)),
                              child: const Text('Clear'),
                            ),
                          ),
                        if (_scheduledTime != null) const SizedBox(width: 12),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: selectedSlot == null
                                ? null
                                : () => Navigator.pop(context, selectedSlot),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: const Color(0xFF0F8F45),
                              foregroundColor: Colors.white,
                            ),
                            child: const Text('Save schedule'),
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
      ),
    );

    if (!mounted || pickedSlot == null) return;

    setState(() {
      _scheduledTime = pickedSlot.year == 0 ? null : pickedSlot;
    });
    _refreshCheckoutSummary();
  }

  DateTime _minimumScheduleTime() {
    final now = DateTime.now().add(const Duration(minutes: 45));
    final roundedMinute = now.minute <= 30 ? 30 : 60;
    if (roundedMinute == 60) {
      return DateTime(now.year, now.month, now.day, now.hour + 1);
    }
    return DateTime(now.year, now.month, now.day, now.hour, 30);
  }

  DateTime? _coerceScheduledSlot(
    DateTime? scheduled,
    DateTime selectedDate,
    DateTime minimumTime,
  ) {
    final slots = _availableScheduleSlotsForDate(selectedDate, minimumTime);
    if (slots.isEmpty) return null;
    if (scheduled == null) return slots.first;
    return slots.contains(scheduled) ? scheduled : slots.first;
  }

  List<DateTime> _availableScheduleSlotsForDate(
    DateTime selectedDate,
    DateTime minimumTime,
  ) {
    final selectedDay = DateUtils.dateOnly(selectedDate);
    final isToday = DateUtils.isSameDay(selectedDay, minimumTime);
    final firstSlot = isToday
        ? minimumTime
        : DateTime(selectedDay.year, selectedDay.month, selectedDay.day, 8);
    final lastSlot =
        DateTime(selectedDay.year, selectedDay.month, selectedDay.day, 23, 30);

    if (firstSlot.isAfter(lastSlot)) return const [];

    final slots = <DateTime>[];
    var slot = firstSlot;
    while (!slot.isAfter(lastSlot)) {
      slots.add(slot);
      slot = slot.add(const Duration(minutes: 30));
    }
    return slots;
  }

  String _formatScheduledTime(DateTime scheduledTime) {
    final now = DateTime.now();
    if (DateUtils.isSameDay(now, scheduledTime)) {
      return 'today at ${DateFormat('hh:mm a').format(scheduledTime)}';
    }
    if (DateUtils.isSameDay(
      now.add(const Duration(days: 1)),
      scheduledTime,
    )) {
      return 'tomorrow at ${DateFormat('hh:mm a').format(scheduledTime)}';
    }
    return DateFormat('EEE, d MMM - hh:mm a').format(scheduledTime);
  }

  void _addSuggestedItem({
    required CartProvider cartProvider,
    required MenuItem item,
  }) {
    final restaurant = cartProvider.restaurant;
    if (restaurant == null) return;

    void addSuggested({
      MenuOption? variant,
      List<MenuOption> addOns = const [],
    }) {
      cartProvider.addItem(
        item,
        restaurant,
        selectedVariant: variant,
        selectedAddOns: addOns,
      );
      _refreshCheckoutSummary();
      setState(() {
        _suggestedItems.removeWhere((suggested) => suggested.id == item.id);
      });
    }

    if (item.hasCustomizations) {
      showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        builder: (_) => MenuCustomizationSheet(
          item: item,
          onAdd: (result) => addSuggested(
            variant: result.variant,
            addOns: result.addOns,
          ),
        ),
      );
      return;
    }

    addSuggested();
  }

  Widget _buildSavingsBanner() {
    final hasSavings = _discount > 0;
    final threshold = _freeDeliveryThreshold;
    final remaining = _freeDeliveryRemaining ?? 0;
    final hasFreeDeliveryMilestone = threshold != null;
    final freeDeliveryAchieved = hasFreeDeliveryMilestone && remaining <= 0;
    final progress = hasFreeDeliveryMilestone && threshold > 0
        ? (_summarySubtotal / threshold).clamp(0.0, 1.0)
        : 1.0;
    final message = hasFreeDeliveryMilestone
        ? freeDeliveryAchieved
            ? 'You unlocked free delivery!'
            : 'Add ${formatCurrency(context, remaining)} more for free delivery'
        : hasSavings
            ? 'You saved ${formatCurrency(context, _discount)} with offers'
            : 'Your order is ready for checkout';

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.fromLTRB(16, 2, 16, 16),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFDDE8FF), Color(0xFFF6ECDD)],
        ),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                freeDeliveryAchieved
                    ? Icons.check_circle_rounded
                    : Icons.local_shipping_rounded,
                color: const Color(0xFF3A66C7),
                size: 20,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  message,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF3A66C7),
                  ),
                ),
              ),
            ],
          ),
          if (hasFreeDeliveryMilestone) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 7,
                color: const Color(0xFF3A66C7),
                backgroundColor: const Color(0x263A66C7),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildCartReviewSection(CartProvider cartProvider) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ...cartProvider.items.asMap().entries.map((entry) {
            final item = entry.value;
            return Padding(
              padding: EdgeInsets.only(
                bottom: entry.key == cartProvider.items.length - 1 ? 0 : 16,
              ),
              child: Column(
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: _buildDietBadge(item.menuItem),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.menuItem.name,
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                                color: FoodFlowTheme.ink,
                              ),
                            ),
                            if (item.selectedVariant != null ||
                                item.selectedAddOns.isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Text(
                                [
                                  if (item.selectedVariant != null)
                                    item.selectedVariant!.name,
                                  ...item.selectedAddOns
                                      .map((option) => option.name),
                                ].join(' • '),
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: FoodFlowTheme.muted,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                            const SizedBox(height: 4),
                            InkWell(
                              onTap: () {},
                              child: const Text(
                                'Edit',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF0F8F45),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          _buildQuantityControl(
                            quantity: item.quantity,
                            onMinus: () {
                              cartProvider.decrementBySignature(item.signature);
                              _refreshCheckoutSummary();
                            },
                            onPlus: () {
                              cartProvider.incrementBySignature(item.signature);
                              _refreshCheckoutSummary();
                            },
                          ),
                          const SizedBox(height: 8),
                          Text(
                            formatCurrency(context, item.totalPrice),
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: FoodFlowTheme.ink,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  if (entry.key != cartProvider.items.length - 1)
                    const Padding(
                      padding: EdgeInsets.only(top: 16),
                      child: Divider(height: 1),
                    ),
                ],
              ),
            );
          }),
          const SizedBox(height: 10),
          InkWell(
            onTap: () => Navigator.of(context).maybePop(),
            child: Padding(
              padding: EdgeInsets.symmetric(vertical: 6),
              child: Text(
                '+ Add more items',
                style: TextStyle(
                  color: _primary,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _buildActionChip(
                  icon: Icons.note_alt_outlined,
                  label: _instructionsController.text.trim().isEmpty
                      ? 'Add a note for the restaurant'
                      : 'Note added',
                  onTap: _showInstructionsSheet,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildActionChip(
                  icon: Icons.restaurant_outlined,
                  label: _dontSendCutlery
                      ? "Don't send cutlery"
                      : 'Cutlery preference',
                  highlighted: _dontSendCutlery,
                  onTap: () {
                    setState(() => _dontSendCutlery = !_dontSendCutlery);
                  },
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildCheckoutRestaurantCard(CartProvider cartProvider) {
    final restaurant = cartProvider.restaurant;

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 2, 12, 8),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: FoodFlowTheme.surface(radius: 30),
        child: Row(
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: const Color(0xFFF7EFE7),
                borderRadius: BorderRadius.circular(24),
              ),
              clipBehavior: Clip.antiAlias,
              child: restaurant?.logoUrl.isNotEmpty == true
                  ? AppCachedImage(
                      imageUrl: restaurant!.logoUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _buildRestaurantIcon(),
                    )
                  : _buildRestaurantIcon(),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    restaurant?.name ?? 'Restaurant',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: FoodFlowTheme.ink,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${cartProvider.itemCount} item${cartProvider.itemCount == 1 ? '' : 's'} in this order',
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF3E8),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      _isTakeaway ? 'Takeaway order' : 'Delivery order',
                      style: TextStyle(
                        color: _primary,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
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

  Widget _buildPaymentSection() {
    final user = context.watch<AuthProvider>().currentUser;
    final onlinePaymentEnabled = _isOnlinePaymentAvailable(user);
    final codEnabled = _isCodAvailable(user);
    _normalizePaymentSelection(user);
    final gatewayLabel = _gatewayDisplayName(user);
    final gatewaySubtitle = _gatewaySubtitle(user);
    final gatewayLogoUrl = _gatewayLogoUrl(user);

    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 10,
              offset: const Offset(0, 2))
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Payment Method',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          if (onlinePaymentEnabled)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                children: [
                  _buildGatewayLogoChip(gatewayLogoUrl),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Online checkout will use the admin-selected gateway: $gatewayLabel',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey.shade700,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          if (onlinePaymentEnabled)
            RadioListTile<String>(
              title: Text('Online payment via $gatewayLabel'),
              subtitle: Text(
                gatewaySubtitle,
                style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
              ),
              value: 'online',
              groupValue: _selectedPaymentMethod,
              onChanged: (value) =>
                  setState(() => _selectedPaymentMethod = value!),
              activeColor: _primary,
              contentPadding: EdgeInsets.zero,
            ),
          if (codEnabled)
            RadioListTile<String>(
              title: const Text('Cash on Delivery'),
              subtitle: Text('Pay when you receive',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
              value: 'cod',
              groupValue: _selectedPaymentMethod,
              onChanged: (value) =>
                  setState(() => _selectedPaymentMethod = value!),
              activeColor: _primary,
              contentPadding: EdgeInsets.zero,
            ),
          RadioListTile<String>(
            title: const Text('App Wallet'),
            subtitle: Text('Use your ${AppConfig.walletMoneyLabel} balance',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            value: 'wallet',
            groupValue: _selectedPaymentMethod,
            onChanged: (value) =>
                setState(() => _selectedPaymentMethod = value!),
            activeColor: _primary,
            contentPadding: EdgeInsets.zero,
          ),
        ],
      ),
    );
  }

  Widget _buildOrderTypeSection(CartProvider cartProvider) {
    final restaurant = cartProvider.restaurant;
    if (restaurant == null ||
        (!restaurant.isDelivery && !restaurant.isTakeaway)) {
      return const SizedBox.shrink();
    }

    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          if (restaurant.isDelivery)
            Expanded(
              child: _buildOrderTypeChip(
                value: 'delivery',
                icon: Icons.delivery_dining,
                title: 'Delivery',
                subtitle: 'Bring it to me',
              ),
            ),
          if (restaurant.isDelivery && restaurant.isTakeaway)
            const SizedBox(width: 10),
          if (restaurant.isTakeaway)
            Expanded(
              child: _buildOrderTypeChip(
                value: 'takeaway',
                icon: Icons.storefront,
                title: 'Takeaway',
                subtitle: 'I will pick it up',
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildOrderTypeChip({
    required String value,
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    final selected = _orderType == value;
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () async {
        setState(() => _orderType = value);
        await _loadAddresses();
        await _refreshCheckoutSummary();
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: selected
              ? const Color(0xFFE23744).withOpacity(0.1)
              : Colors.grey.shade50,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? const Color(0xFFE23744) : Colors.grey.shade200,
            width: selected ? 1.4 : 1,
          ),
        ),
        child: Row(
          children: [
            Icon(icon,
                color:
                    selected ? const Color(0xFFE23744) : Colors.grey.shade600),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      style: const TextStyle(fontWeight: FontWeight.w900)),
                  Text(subtitle,
                      style:
                          TextStyle(fontSize: 11, color: Colors.grey.shade600)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String? _effectiveOrderPaymentMethod(User? user) {
    _normalizePaymentSelection(user);
    if (_selectedPaymentMethod == 'online') {
      return _effectiveGatewayProvider(user);
    }
    return _selectedPaymentMethod;
  }

  String? _effectiveGatewayProvider(User? user) {
    final selected = _selectedGatewayProvider?.toLowerCase();
    final availableGateways = _availablePaymentGateways(user);
    if (selected != null &&
        availableGateways.any((gateway) => gateway.key == selected)) {
      return selected;
    }

    return _defaultGatewayProvider(user);
  }

  bool _isOnlinePaymentAvailable(User? user) {
    final provider = _effectiveGatewayProvider(user);
    final enabled = user?.isPaymentGatewayEnabled ?? true;
    return enabled && provider != null;
  }

  bool _isCodAvailable(User? user) => user?.isCodEnabled ?? true;

  void _normalizePaymentSelection(User? user) {
    final fallbackGateway = _defaultGatewayProvider(user);
    final onlineAvailable =
        (user?.isPaymentGatewayEnabled ?? true) && fallbackGateway != null;

    if (_selectedPaymentMethod == 'online' && onlineAvailable) {
      _selectedGatewayProvider ??= fallbackGateway;
      _selectedOnlinePaymentView = _selectedGatewayProvider ?? fallbackGateway;
      return;
    }

    if (_selectedPaymentMethod == 'cod' && _isCodAvailable(user)) {
      return;
    }

    if (_selectedPaymentMethod == 'wallet') {
      return;
    }

    if (onlineAvailable) {
      _selectedGatewayProvider = fallbackGateway;
      _selectedPaymentMethod = 'online';
      _selectedOnlinePaymentView = fallbackGateway;
      return;
    }

    _selectedPaymentMethod = _isCodAvailable(user) ? 'cod' : 'wallet';
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

  String _gatewaySubtitle(User? user) {
    return switch (_effectiveGatewayProvider(user)) {
      'stripe' => 'Secure card checkout based on the admin payment gateway.',
      'cashfree' => 'UPI, cards and wallets via the admin payment gateway.',
      _ => 'UPI, cards and wallets via the admin payment gateway.',
    };
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

  String? _defaultGatewayProvider(User? user) {
    if (user == null || !user.isPaymentGatewayEnabled) return null;

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

  String _gatewayLabelForKey(String provider) {
    return switch (provider) {
      'stripe' => 'Stripe',
      'cashfree' => 'Cashfree',
      _ => 'Razorpay',
    };
  }

  Widget _buildBillDetails(
      CartProvider cartProvider, double platformFee, double gst, double total) {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 10,
              offset: const Offset(0, 2))
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Bill Details',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          _buildBillRow(
            'Item Total',
            formatCurrency(context, _displaySubtotal(cartProvider)),
          ),
          _buildBillRow(
              _deliveryFeeLabel(),
              _summaryDeliveryFee > 0
                  ? formatCurrency(context, _summaryDeliveryFee)
                  : 'Free'),
          if (platformFee > 0)
            _buildBillRow(
              'Platform Fee',
              formatCurrency(context, platformFee),
            ),
          _buildBillRow(
            _summaryTaxLabel,
            formatCurrency(context, gst),
            onTap: () => _showTaxBreakdownPopup(gst),
            tappable: true,
          ),
          if (_discount > 0)
            _buildBillRow(
                'Coupon Discount', '-${formatCurrency(context, _discount)}',
                isSavings: true),
          const SizedBox(height: 12),
          const Divider(height: 1),
          const SizedBox(height: 12),
          _buildBillRow('Total Amount', formatCurrency(context, total),
              isTotal: true),
        ],
      ),
    );
  }

  Widget _buildBillRow(
    String label,
    String value, {
    bool isTotal = false,
    bool isSavings = false,
    VoidCallback? onTap,
    bool tappable = false,
  }) {
    final row = Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Row(
              children: [
                Flexible(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: isTotal ? 15 : 14,
                      fontWeight: isTotal ? FontWeight.bold : FontWeight.normal,
                      color: isTotal ? Colors.black : Colors.grey.shade700,
                      decoration: tappable ? TextDecoration.underline : null,
                      decorationStyle: TextDecorationStyle.dotted,
                    ),
                  ),
                ),
                if (tappable) ...[
                  const SizedBox(width: 5),
                  Icon(
                    Icons.info_outline_rounded,
                    size: 15,
                    color: Colors.grey.shade600,
                  ),
                ],
              ],
            ),
          ),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                value,
                style: TextStyle(
                  fontSize: isTotal ? 16 : 14,
                  fontWeight: isTotal ? FontWeight.bold : FontWeight.w500,
                  color: isSavings
                      ? Colors.green
                      : (isTotal ? _primary : Colors.black87),
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

  Widget _buildPremiumPlaceOrderBar(double total) {
    final user = context.watch<AuthProvider>().currentUser;
    final paymentTitle = _selectedPaymentMethod == 'online'
        ? _gatewayDisplayName(user)
        : _selectedPaymentMethod == 'wallet'
            ? AppConfig.walletMoneyLabel
            : 'Cash on Delivery';
    final paymentSubtitle = _selectedPaymentMethod == 'online'
        ? _gatewaySubtitle(user)
        : _selectedPaymentMethod == 'wallet'
            ? 'Use your ${AppConfig.walletMoneyLabel} balance'
            : 'Pay when your order arrives';

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.08),
            blurRadius: 15,
            offset: const Offset(0, -5),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 14),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              InkWell(
                borderRadius: BorderRadius.circular(18),
                onTap: () => _showPaymentSelectorSheet(total),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(4, 0, 4, 8),
                  child: Row(
                    children: [
                      _buildPaymentMethodIcon(user),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              paymentTitle,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                                color: FoodFlowTheme.ink,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              paymentSubtitle,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w500,
                                color: FoodFlowTheme.muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.chevron_right,
                          color: FoodFlowTheme.faint),
                    ],
                  ),
                ),
              ),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: () {
                    if (!_isTakeaway &&
                        (_selectedAddress == null ||
                            _selectedAddress?.isDeliverable == false)) {
                      _showAddressSelector();
                      return;
                    }
                    _confirmLocationBeforeOrder();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).colorScheme.primary,
                    foregroundColor: Colors.white,
                    elevation: 3,
                    shadowColor:
                        Theme.of(context).colorScheme.primary.withOpacity(0.22),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        !_isTakeaway &&
                                (_selectedAddress == null ||
                                    _selectedAddress?.isDeliverable == false)
                            ? 'Select address at next step'
                            : 'Continue with ${formatCurrency(context, total)}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.1,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(width: 4),
                      const Icon(
                        Icons.chevron_right,
                        color: Colors.white,
                        size: 18,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPaymentMethodIcon(User? user) {
    if (_selectedPaymentMethod == 'online') {
      return _buildGatewayLogoChip(_gatewayLogoUrl(user));
    }

    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: const Color(0xFFF7F7F7),
        borderRadius: BorderRadius.circular(10),
      ),
      child: AppIcon(
        _selectedPaymentMethod == 'wallet'
            ? AppIcons.wallet
            : AppIcons.payments,
        size: 18,
      ),
    );
  }

  void _showPaymentSelectorSheet(double total) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFFF5F6FB),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        final user =
            Provider.of<AuthProvider>(context, listen: false).currentUser;
        final availableGateways = _availablePaymentGateways(user);
        final codEnabled = _isCodAvailable(user);
        _normalizePaymentSelection(user);

        return SafeArea(
          child: DraggableScrollableSheet(
            expand: false,
            initialChildSize: 0.86,
            minChildSize: 0.55,
            maxChildSize: 0.95,
            builder: (context, scrollController) {
              return ListView(
                controller: scrollController,
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                children: [
                  Row(
                    children: [
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.arrow_back),
                        color: FoodFlowTheme.ink,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'Bill total: ${formatCurrency(context, total)}',
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                          color: FoodFlowTheme.ink,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  if (availableGateways.isNotEmpty) ...[
                    _paymentGroupLabel('Recommended'),
                    _paymentSheetGroup(
                      children: availableGateways
                          .map(
                            (gateway) => _buildPaymentSheetTile(
                              title: gateway.label,
                              logoUrl: gateway.logo,
                              selected: _selectedPaymentMethod == 'online' &&
                                  _effectiveGatewayProvider(user) ==
                                      gateway.key,
                              onTap: () {
                                _selectGatewayProvider(gateway.key);
                                Navigator.pop(context);
                              },
                            ),
                          )
                          .toList(),
                    ),
                    const SizedBox(height: 20),
                  ],
                  _paymentGroupLabel('Wallets'),
                  _paymentSheetGroup(
                    children: [
                      _buildPaymentSheetTile(
                        title: AppConfig.walletMoneyLabel,
                        subtitle:
                            'Available balance: ${formatCurrency(context, _walletBalance)}',
                        icon: Icons.account_balance_wallet_outlined,
                        selected: _selectedPaymentMethod == 'wallet',
                        trailingIcon: Icons.add,
                        onTap: () {
                          _selectPaymentCategory('wallet');
                          Navigator.pop(context);
                        },
                      ),
                    ],
                  ),
                  if (codEnabled) ...[
                    const SizedBox(height: 20),
                    _paymentGroupLabel('Cash on Delivery'),
                    _paymentSheetGroup(
                      children: [
                        _buildPaymentSheetTile(
                          title: 'Cash on Delivery',
                          icon: Icons.payments_outlined,
                          selected: _selectedPaymentMethod == 'cod',
                          onTap: () {
                            _selectPaymentCategory('cod');
                            Navigator.pop(context);
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      'Use cash on delivery mindfully. Cancellations may affect future COD availability.',
                      style: TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        height: 1.35,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ],
              );
            },
          ),
        );
      },
    );
  }

  Widget _paymentSheetGroup({required List<Widget> children}) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          for (var index = 0; index < children.length; index++) ...[
            children[index],
            if (index != children.length - 1)
              const Divider(height: 1, indent: 16, endIndent: 16),
          ],
        ],
      ),
    );
  }

  Widget _buildPaymentSheetTile({
    required String title,
    String? subtitle,
    String logoUrl = '',
    IconData? icon,
    required bool selected,
    IconData trailingIcon = Icons.chevron_right,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            logoUrl.isNotEmpty
                ? _buildGatewayLogoChip(logoUrl)
                : Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: FoodFlowTheme.line),
                    ),
                    child: Icon(
                      icon ?? Icons.credit_card_outlined,
                      color: selected
                          ? const Color(0xFF0F8F45)
                          : FoodFlowTheme.inkSoft,
                    ),
                  ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (subtitle?.isNotEmpty == true) ...[
                    const SizedBox(height: 3),
                    Text(
                      subtitle!,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            Icon(
              selected ? Icons.check_circle : trailingIcon,
              color: selected ? const Color(0xFF0F8F45) : FoodFlowTheme.inkSoft,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPremiumAddressSection() {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Delivery Address',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          if (_selectedAddress != null) ...[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF4EC),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    Icons.home_outlined,
                    color: _primary,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _selectedAddress!.name,
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _selectedAddress!.fullAddress,
                        style: const TextStyle(
                          fontSize: 14,
                          height: 1.45,
                          color: FoodFlowTheme.muted,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Phone: ${_selectedAddress!.phone}',
                        style: const TextStyle(
                          fontSize: 14,
                          color: FoodFlowTheme.ink,
                        ),
                      ),
                    ],
                  ),
                ),
                TextButton(
                  onPressed: _showAddressSelector,
                  style: TextButton.styleFrom(
                    foregroundColor: _primary,
                    textStyle: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  child: const Text('CHANGE'),
                ),
              ],
            ),
            const SizedBox(height: 14),
          ],
          OutlinedButton.icon(
            onPressed: () async {
              final result =
                  await Navigator.pushNamed(context, '/addresses/add');
              if (result == true) _loadAddresses();
            },
            icon: const Icon(Icons.add, size: 18),
            label: const Text('Add New Address'),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: FoodFlowTheme.line),
              foregroundColor: FoodFlowTheme.ink,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPremiumOrderItemsSection(CartProvider cartProvider) {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Summary',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          ...cartProvider.items.asMap().entries.map((entry) {
            final index = entry.key;
            final item = entry.value;
            return Padding(
              padding: EdgeInsets.only(
                bottom: index == cartProvider.items.length - 1 ? 0 : 14,
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: SizedBox(
                      width: 58,
                      height: 58,
                      child: item.menuItem.imageUrl.isNotEmpty
                          ? AppCachedImage(
                              imageUrl: item.menuItem.imageUrl,
                              fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) =>
                                  _foodThumbFallback(),
                            )
                          : _foodThumbFallback(),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              _buildDietBadge(item.menuItem),
                              const SizedBox(width: 6),
                              Expanded(
                                child: Text(
                                  item.menuItem.name,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w700,
                                    color: FoodFlowTheme.ink,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          if (item.selectedVariant != null ||
                              item.selectedAddOns.isNotEmpty) ...[
                            const SizedBox(height: 5),
                            Text(
                              [
                                if (item.selectedVariant != null)
                                  item.selectedVariant!.name,
                                ...item.selectedAddOns
                                    .map((option) => option.name),
                              ].join(' • '),
                              style: const TextStyle(
                                fontSize: 12,
                                color: FoodFlowTheme.muted,
                              ),
                            ),
                          ],
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              _buildQuantityControl(
                                quantity: item.quantity,
                                onMinus: () {
                                  cartProvider
                                      .decrementQuantity(item.menuItem.id);
                                  _refreshCheckoutSummary();
                                },
                                onPlus: () {
                                  cartProvider
                                      .incrementQuantity(item.menuItem.id);
                                  _refreshCheckoutSummary();
                                },
                              ),
                              const Spacer(),
                              Text(
                                formatCurrency(context, item.totalPrice),
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w700,
                                  color: FoodFlowTheme.ink,
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
          }),
        ],
      ),
    );
  }

  Widget _buildPremiumInstructionSection() {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Add cooking instructions (optional)',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),
              ),
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF4EC),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  Icons.edit_outlined,
                  color: _primary,
                  size: 18,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _instructionsController,
            minLines: 1,
            maxLines: 3,
            decoration: const InputDecoration(
              hintText:
                  'Less spicy, no onion, send cutlery, ring bell on arrival...',
              border: InputBorder.none,
              isDense: true,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPremiumPaymentSection() {
    final user = context.watch<AuthProvider>().currentUser;
    final onlinePaymentEnabled = _isOnlinePaymentAvailable(user);
    final availableGateways = _availablePaymentGateways(user);
    final codEnabled = _isCodAvailable(user);
    _normalizePaymentSelection(user);

    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Payment Options',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 14),
          if (onlinePaymentEnabled && availableGateways.isNotEmpty) ...[
            _paymentGroupLabel('Recommended'),
            ...availableGateways.map(
              (gateway) => _buildGatewayPaymentTile(
                gateway: gateway,
                selected: _selectedPaymentMethod == 'online' &&
                    _effectiveGatewayProvider(user) == gateway.key,
                onTap: () => _selectGatewayProvider(gateway.key),
              ),
            ),
            const SizedBox(height: 8),
            _paymentGroupLabel('More ways to pay'),
          ] else
            Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF1F1),
                borderRadius: BorderRadius.circular(18),
              ),
              child: const Text(
                'Online payment is currently unavailable.',
                style: TextStyle(
                  color: FoodFlowTheme.danger,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          _buildPaymentOptionTile(
            title: 'Wallets',
            subtitle: 'Use your ${AppConfig.walletMoneyLabel} balance',
            icon: Icons.account_balance_wallet_outlined,
            selected: _selectedPaymentCategory == 'wallet',
            onTap: () => _selectPaymentCategory('wallet'),
          ),
          if (codEnabled)
            _buildPaymentOptionTile(
              title: 'Cash on Delivery',
              subtitle: 'Pay when your order arrives',
              icon: Icons.money_outlined,
              selected: _selectedPaymentCategory == 'cod',
              onTap: () => _selectPaymentCategory('cod'),
            ),
        ],
      ),
    );
  }

  Widget _buildPremiumBillDetails(
    CartProvider cartProvider,
    double platformFee,
    double gst,
    double total,
  ) {
    return Container(
      margin: const EdgeInsets.all(12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Bill Details',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          _buildBillRow(
            'Item Total',
            formatCurrency(context, _displaySubtotal(cartProvider)),
          ),
          _buildBillRow(
            _deliveryFeeLabel(),
            _summaryDeliveryFee > 0
                ? formatCurrency(context, _summaryDeliveryFee)
                : 'Free',
          ),
          if (platformFee > 0)
            _buildBillRow(
              'Platform Fee',
              formatCurrency(context, platformFee),
            ),
          _buildBillRow(
            _summaryTaxLabel,
            formatCurrency(context, gst),
            onTap: () => _showTaxBreakdownPopup(gst),
            tappable: true,
          ),
          if (_discount > 0)
            _buildBillRow(
              'Coupon Discount',
              '-${formatCurrency(context, _discount)}',
              isSavings: true,
            ),
          if (_appliedCouponCode != null)
            _buildBillRow(
              'Coupon Applied',
              _appliedCouponCode!,
              isSavings: true,
            ),
          const SizedBox(height: 12),
          const Divider(height: 1),
          const SizedBox(height: 12),
          _buildBillRow(
            'Total Amount',
            formatCurrency(context, total),
            isTotal: true,
          ),
        ],
      ),
    );
  }

  String get _selectedPaymentCategory {
    return switch (_selectedPaymentMethod) {
      'wallet' => 'wallet',
      'cod' => 'cod',
      _ => _selectedOnlinePaymentView,
    };
  }

  void _selectPaymentCategory(String category) {
    setState(() {
      if (category == 'wallet' || category == 'cod') {
        _selectedPaymentMethod = category;
      } else {
        _selectedPaymentMethod = 'online';
        _selectedOnlinePaymentView = category;
      }
    });
  }

  void _selectGatewayProvider(String provider) {
    setState(() {
      _selectedPaymentMethod = 'online';
      _selectedGatewayProvider = provider;
      _selectedOnlinePaymentView = provider;
    });
  }

  Widget _paymentGroupLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10, top: 2),
      child: Text(
        label.toUpperCase(),
        style: const TextStyle(
          color: FoodFlowTheme.faint,
          fontSize: 12,
          fontWeight: FontWeight.w900,
          letterSpacing: 0,
        ),
      ),
    );
  }

  Widget _buildGatewayPaymentTile({
    required PaymentGatewayOption gateway,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(
            color: selected ? const Color(0xFFFFF4EC) : Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: selected ? const Color(0xFFFFD2B0) : FoodFlowTheme.line,
            ),
          ),
          child: Row(
            children: [
              _buildGatewayLogoChip(gateway.logo),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  gateway.label,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: FoodFlowTheme.ink,
                  ),
                ),
              ),
              Icon(
                selected ? Icons.radio_button_checked : Icons.chevron_right,
                color: selected ? _primary : FoodFlowTheme.faint,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPaymentOptionTile({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          decoration: BoxDecoration(
            color: selected ? const Color(0xFFFFF4EC) : Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: selected ? const Color(0xFFFFD2B0) : FoodFlowTheme.line,
            ),
          ),
          child: Row(
            children: [
              Icon(
                icon,
                color: selected ? _primary : FoodFlowTheme.ink,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 12,
                        color: FoodFlowTheme.muted,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_off,
                color: selected ? _primary : Colors.grey.shade400,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildGatewayLogoChip(String logoUrl) {
    if (logoUrl.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: AppCachedImage(
          imageUrl: logoUrl,
          width: 44,
          height: 44,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => _buildGatewayFallbackIcon(),
        ),
      );
    }

    return _buildGatewayFallbackIcon();
  }

  Widget _buildGatewayFallbackIcon() {
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: const Color(0xFFFFEBDC),
        borderRadius: BorderRadius.circular(12),
      ),
      alignment: Alignment.center,
      child: Icon(
        Icons.account_balance_wallet_outlined,
        color: _primary,
      ),
    );
  }

  Widget _buildRestaurantIcon() {
    return Container(
      color: const Color(0xFFFFF3E8),
      alignment: Alignment.center,
      child: Icon(
        Icons.storefront_rounded,
        color: _primary,
      ),
    );
  }

  Widget _buildQuantityControl({
    required int quantity,
    required VoidCallback onMinus,
    required VoidCallback onPlus,
  }) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFFD2B0)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _buildQuantityButton(icon: Icons.remove, onTap: onMinus),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Text(
              '$quantity',
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                color: FoodFlowTheme.ink,
              ),
            ),
          ),
          _buildQuantityButton(icon: Icons.add, onTap: onPlus),
        ],
      ),
    );
  }

  Widget _buildQuantityButton({
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        child: Icon(icon, size: 18, color: _primary),
      ),
    );
  }

  Widget _buildDietBadge(MenuItem item) {
    final color = item.isNonVeg
        ? const Color(0xFFEF4444)
        : item.isEgg
            ? const Color(0xFFF59E0B)
            : const Color(0xFF22C55E);
    return Container(
      width: 14,
      height: 14,
      padding: const EdgeInsets.all(2),
      decoration: BoxDecoration(
        border: Border.all(color: color, width: 1.2),
        borderRadius: BorderRadius.circular(4),
      ),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(999),
        ),
      ),
    );
  }

  Widget _foodThumbFallback() {
    return Container(
      color: const Color(0xFFFFF3E8),
      child: Icon(Icons.fastfood_rounded, color: _primary),
    );
  }

  void _showAddressSelector() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => SafeArea(
        child: Stack(
          alignment: Alignment.topCenter,
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 44),
              child: DraggableScrollableSheet(
                expand: false,
                initialChildSize: 0.84,
                minChildSize: 0.5,
                maxChildSize: 0.94,
                builder: (context, scrollController) {
                  return Container(
                    decoration: const BoxDecoration(
                      color: Color(0xFFF5F6FB),
                      borderRadius:
                          BorderRadius.vertical(top: Radius.circular(24)),
                    ),
                    child: ListView(
                      controller: scrollController,
                      padding: const EdgeInsets.fromLTRB(16, 22, 16, 28),
                      children: [
                        const Text(
                          'Select an address',
                          style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                            color: FoodFlowTheme.ink,
                          ),
                        ),
                        const SizedBox(height: 24),
                        _addressActionGroup(),
                        const SizedBox(height: 24),
                        _paymentGroupLabel('Saved Addresses'),
                        const SizedBox(height: 4),
                        ..._addresses.map(_buildAddressSheetCard),
                      ],
                    ),
                  );
                },
              ),
            ),
            Material(
              color: const Color(0xFF202124),
              shape: const CircleBorder(),
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: () => Navigator.pop(context),
                child: const SizedBox(
                  width: 58,
                  height: 58,
                  child: Icon(Icons.close, color: Colors.white, size: 30),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _addressActionGroup() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          _addressActionTile(
            icon: Icons.add,
            title: 'Add Address',
            onTap: () async {
              Navigator.pop(context);
              final result =
                  await Navigator.pushNamed(context, '/addresses/add');
              if (result == true) _loadAddresses();
            },
          ),
          const Divider(height: 1, indent: 16, endIndent: 16),
          _addressActionTile(
            icon: Icons.my_location,
            title: 'Use current location',
            onTap: () {
              Navigator.pop(context);
              _detectCurrentLocation();
            },
          ),
          const Divider(height: 1, indent: 16, endIndent: 16),
          _addressActionTile(
            icon: Icons.map_outlined,
            title: 'Pick from map',
            onTap: () {
              Navigator.pop(context);
              _openMapPicker();
            },
          ),
        ],
      ),
    );
  }

  Widget _addressActionTile({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        child: Row(
          children: [
            Icon(icon, color: const Color(0xFF0F8F45), size: 26),
            const SizedBox(width: 16),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  color: Color(0xFF0F8F45),
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            const Icon(Icons.chevron_right, color: FoodFlowTheme.inkSoft),
          ],
        ),
      ),
    );
  }

  Widget _addressIconButton({
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: Border.all(color: const Color(0xFFE7EAF0)),
        ),
        child: Icon(icon, size: 18, color: const Color(0xFF0F8F45)),
      ),
    );
  }

  Widget _buildAddressSheetCard(app_address.Address address) {
    final selected = address == _selectedAddress;
    final canDeliver = _isTakeaway || address.isDeliverable;
    final distanceText = address.distanceKm == null
        ? '--'
        : '${address.distanceKm!.toStringAsFixed(address.distanceKm! >= 10 ? 0 : 1)} km';

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: selected
            ? Border.all(color: const Color(0xFF0F8F45), width: 1.2)
            : Border.all(color: const Color(0xFFE8ECF3)),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: canDeliver
            ? () async {
                Navigator.pop(context);
                setState(() => _selectedAddress = address);
                _syncContactPhone();
                await _refreshCheckoutSummary();
              }
            : null,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 44,
              child: Column(
                children: [
                  Icon(
                    address.name.toLowerCase().contains('work')
                        ? Icons.business_center_outlined
                        : Icons.home_outlined,
                    color: FoodFlowTheme.inkSoft,
                    size: 30,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    distanceText,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    address.deliveryStatusLabel ??
                        (canDeliver ? 'DELIVERS TO' : 'DOES NOT DELIVER TO'),
                    style: TextStyle(
                      color: canDeliver
                          ? const Color(0xFF3468B7)
                          : FoodFlowTheme.danger,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    address.name,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    address.fullAddress,
                    style: const TextStyle(
                      color: FoodFlowTheme.inkSoft,
                      fontSize: 13,
                      height: 1.3,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Phone number: ${address.phone}',
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      _addressIconButton(
                        icon: Icons.delete_outline,
                        onTap: () async {
                          final confirmed = await showDialog<bool>(
                            context: context,
                            builder: (dialogContext) => AlertDialog(
                              title: const Text('Delete address?'),
                              content: const Text(
                                'This address will be removed from your saved addresses.',
                              ),
                              actions: [
                                TextButton(
                                  onPressed: () =>
                                      Navigator.pop(dialogContext, false),
                                  child: const Text('Cancel'),
                                ),
                                TextButton(
                                  onPressed: () =>
                                      Navigator.pop(dialogContext, true),
                                  child: const Text('Delete'),
                                ),
                              ],
                            ),
                          );
                          if (confirmed != true) return;
                          await _api.post(
                            ApiConstants.deleteAddress(address.id),
                          );
                          if (!mounted) return;
                          Navigator.pop(context);
                          await _loadAddresses();
                        },
                      ),
                      const SizedBox(width: 10),
                      _addressIconButton(
                        icon: Icons.edit_outlined,
                        onTap: () async {
                          Navigator.pop(context);
                          final result = await Navigator.pushNamed(
                            context,
                            '/addresses/edit',
                            arguments: address,
                          );
                          if (result == true) {
                            _loadAddresses();
                          }
                        },
                      ),
                      const SizedBox(width: 10),
                      _addressIconButton(
                        icon: Icons.check_circle_outline,
                        onTap: () async {
                          await _api.post(
                            '${ApiConstants.setDefaultAddress}/${address.id}',
                          );
                          await _loadAddresses();
                        },
                      ),
                    ],
                  ),
                ],
              ),
            ),
            if (selected)
              const Icon(Icons.check_circle, color: Color(0xFF0F8F45)),
          ],
        ),
      ),
    );
  }

  Future<void> _detectCurrentLocation() async {
    try {
      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        final result = await Geolocator.requestPermission();
        if (result == LocationPermission.denied) {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Location permission denied'),
              backgroundColor: Colors.orange,
            ),
          );
          return;
        }
      }

      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      if (!mounted) return;
      Navigator.pop(context);

      // Show confirmation dialog with detected location
      _showLocationConfirmation(
        position.latitude,
        position.longitude,
        'Detected Location',
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error detecting location: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _openMapPicker() async {
    // Navigate to map picker screen
    final result = await Navigator.pushNamed(
      context,
      '/map-picker',
      arguments: _selectedAddress,
    );

    if (result != null && result is app_address.Address) {
      if (!mounted) return;
      Navigator.pop(context);
      setState(() => _selectedAddress = result);
      _syncContactPhone();
      await _refreshCheckoutSummary();
    }
  }

  void _showLocationConfirmation(
    double latitude,
    double longitude,
    String locationName,
  ) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm Location'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Location: $locationName'),
            const SizedBox(height: 8),
            Text('Latitude: $latitude'),
            const SizedBox(height: 8),
            Text('Longitude: $longitude'),
            const SizedBox(height: 16),
            const Text(
              'Is this your delivery address?',
              style: TextStyle(fontWeight: FontWeight.w500),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Change'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              // Update selected address with detected location
              setState(() {
                _selectedAddress = _addresses.firstWhere(
                  (addr) =>
                      addr.latitude == latitude && addr.longitude == longitude,
                  orElse: () => _addresses.first,
                );
              });
              _syncContactPhone();
              _refreshCheckoutSummary();
            },
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
  }
}

class _PromoSelectionScreen extends StatefulWidget {
  final List<Map<String, dynamic>> promos;
  final String selectedCode;
  final double subtotal;

  const _PromoSelectionScreen({
    required this.promos,
    required this.selectedCode,
    required this.subtotal,
  });

  @override
  State<_PromoSelectionScreen> createState() => _PromoSelectionScreenState();
}

class _PromoSelectionScreenState extends State<_PromoSelectionScreen> {
  late String _selectedCode;
  Color get _primary => Theme.of(context).colorScheme.primary;

  @override
  void initState() {
    super.initState();
    _selectedCode = widget.selectedCode;
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _promoCode(Map<String, dynamic> promo) {
    return promo['code']?.toString() ?? promo['coupon_code']?.toString() ?? '';
  }

  String _title(Map<String, dynamic> promo) {
    final discountType = promo['discount_type']?.toString() ?? 'percentage';
    final discountValue = _toDouble(promo['discount_value']);
    if (discountType == 'fixed') {
      return 'Flat ${getCurrencySymbol(context)}${discountValue.toStringAsFixed(discountValue == discountValue.roundToDouble() ? 0 : 1)} OFF';
    }
    return '${discountValue.toStringAsFixed(discountValue == discountValue.roundToDouble() ? 0 : 1)}% OFF';
  }

  String _subtitle(Map<String, dynamic> promo) {
    final minOrder = _toDouble(promo['min_order_amount']);
    if (minOrder > 0 && widget.subtotal < minOrder) {
      return 'Add eligible items worth ${formatCurrency(context, minOrder - widget.subtotal)} more to unlock';
    }

    final code = _promoCode(promo);
    return code.isEmpty
        ? 'Tap to apply this coupon'
        : 'Save more with this code';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        backgroundColor: accountCanvas,
        elevation: 0,
        foregroundColor: FoodFlowTheme.ink,
        title: const Text(
          'Coupons',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Row(
                  children: [
                    const AppIcon(AppIcons.offer, size: 20),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _selectedCode.isEmpty
                            ? 'Select a coupon for this order'
                            : 'Coupon selected for you',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: FoodFlowTheme.ink,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                itemCount: widget.promos.length,
                itemBuilder: (context, index) {
                  final promo = widget.promos[index];
                  final code = _promoCode(promo);
                  final selected = code.isNotEmpty && code == _selectedCode;
                  return Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: selected ? _primary : const Color(0xFFE7EAF0),
                        width: selected ? 1.4 : 1,
                      ),
                    ),
                    child: InkWell(
                      onTap: code.isEmpty
                          ? null
                          : () => setState(() => _selectedCode = code),
                      borderRadius: BorderRadius.circular(18),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Padding(
                            padding: EdgeInsets.only(top: 2),
                            child: AppIcon(AppIcons.offer, size: 18),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _title(promo),
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w800,
                                    color: FoodFlowTheme.ink,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  _subtitle(promo),
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: _subtitle(promo)
                                            .startsWith('Add eligible')
                                        ? const Color(0xFFB45309)
                                        : const Color(0xFF1D4ED8),
                                  ),
                                ),
                                if (code.isNotEmpty) ...[
                                  const SizedBox(height: 8),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 10,
                                      vertical: 5,
                                    ),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFF7F8FC),
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      code,
                                      style: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: FoodFlowTheme.muted,
                                      ),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                          Radio<String>(
                            value: code,
                            groupValue: _selectedCode,
                            activeColor: _primary,
                            onChanged: code.isEmpty
                                ? null
                                : (value) =>
                                    setState(() => _selectedCode = value ?? ''),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _selectedCode.isEmpty
                      ? null
                      : () => Navigator.pop(context, _selectedCode),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                  ),
                  child: const Text(
                    'Tap to apply',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PaymentBadge extends StatelessWidget {
  final String label;

  const _PaymentBadge({required this.label});

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF4EC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFFFD8BE)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: primary,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
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
