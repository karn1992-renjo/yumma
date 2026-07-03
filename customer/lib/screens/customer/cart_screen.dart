import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:lottie/lottie.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../models/address.dart' as app_address;
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/lucide_icon.dart';
import '../../widgets/customer/cart_item_card.dart';
import '../../widgets/customer/account_chrome.dart';
import '../../widgets/customer/free_delivery_success_popup.dart';
import 'checkout_screen.dart';

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
  bool _isSummaryLoading = false;
  double? _summarySubtotal;
  double? _summaryDeliveryFee;
  double? _summaryPlatformFee;
  double? _summaryTax;
  double? _summaryTotal;
  double? _freeDeliveryThreshold;
  double? _freeDeliveryRemaining;
  double? _deliveryDistanceKm;
  String _summaryTaxLabel = 'Taxes';
  List<_ChargeBreakdownItem> _summaryTaxBreakdown = [];
  String? _lastCartSignature;
  int _summaryRequestId = 0;
  List<app_address.Address> _addresses = [];
  app_address.Address? _selectedAddress;
  bool _isAddressLoading = false;
  int? _loadedAddressRestaurantId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refreshCartSummary());
  }

  Future<void> _refreshCartSummary() async {
    final cart = context.read<CartProvider>();
    final restaurant = cart.restaurant;
    if (restaurant == null || cart.items.isEmpty) {
      if (!mounted) return;
      setState(() {
        _summarySubtotal = null;
        _summaryDeliveryFee = null;
        _summaryPlatformFee = null;
        _summaryTax = null;
        _summaryTotal = null;
        _freeDeliveryThreshold = null;
        _freeDeliveryRemaining = null;
        _deliveryDistanceKm = null;
        _summaryTaxBreakdown = [];
        _addresses = [];
        _selectedAddress = null;
        _loadedAddressRestaurantId = null;
      });
      return;
    }

    final requestId = ++_summaryRequestId;
    setState(() => _isSummaryLoading = true);
    try {
      final address = await _loadAddressesForCart(restaurant.id);
      final response = await _api.post(
        ApiConstants.orderSummary,
        data: {
          'restaurant_id': restaurant.id,
          'items': cart.items
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
          'delivery_address_id': address?.id,
          'delivery_lat': address?.latitude,
          'delivery_lng': address?.longitude,
          'order_type': 'delivery',
        },
      );

      if (!mounted || requestId != _summaryRequestId) return;
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
          _summaryDeliveryFee = _toDouble(data['delivery_fee']);
          _summaryPlatformFee = _toDouble(data['platform_fee']);
          _summaryTax = _toDouble(data['tax']);
          _summaryTotal = _toDouble(data['total']);
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
        });
        if (celebrate) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) showFreeDeliverySuccessPopup(context);
          });
        }
      }
    } catch (e) {
      debugPrint('Cart summary error: $e');
    } finally {
      if (mounted && requestId == _summaryRequestId) {
        setState(() => _isSummaryLoading = false);
      }
    }
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
        _addresses = addresses;
        _selectedAddress = selected;
        _loadedAddressRestaurantId = restaurantId;
      });
      return selected;
    } finally {
      if (mounted) setState(() => _isAddressLoading = false);
    }
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  double? _nullableDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
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
    final restaurantId = cart.restaurant?.id ?? 0;
    final itemSignature = cart.items
        .map((item) => '${item.signature}:${item.quantity}')
        .join('|');
    return '$restaurantId::$itemSignature';
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
      if (mounted) _refreshCartSummary();
    });
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final cart = Provider.of<CartProvider>(context);
    _scheduleSummaryRefreshIfNeeded(cart);

    if (cart.isEmpty) {
      return _EmptyCartView(onBrowseRestaurants: widget.onBrowseRestaurants);
    }

    final restaurant = cart.restaurant;
    final subtotal = _summarySubtotal ?? cart.subtotal;
    final deliveryFee = _summaryDeliveryFee ?? 0;
    final platformFee = _summaryPlatformFee ?? 0;
    final tax = _summaryTax ?? 0;
    final total = _summaryTotal ?? subtotal + deliveryFee + platformFee + tax;
    final deliveryLabel = _deliveryDistanceKm != null
        ? 'Delivery fee (${_deliveryDistanceKm!.toStringAsFixed(_deliveryDistanceKm! >= 10 ? 0 : 1)} km)'
        : 'Delivery fee';

    final baseTheme = Theme.of(context);
    final cartTheme = baseTheme.copyWith(
      textTheme: GoogleFonts.nunitoSansTextTheme(baseTheme.textTheme),
      primaryTextTheme:
          GoogleFonts.nunitoSansTextTheme(baseTheme.primaryTextTheme),
    );

    return Theme(
      data: cartTheme,
      child: Scaffold(
        backgroundColor: accountCanvas,
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(10, 6, 10, 5),
                child: Row(
                  children: [
                    IconButton.filledTonal(
                      onPressed: () => Navigator.of(context).maybePop(),
                      style: IconButton.styleFrom(
                        backgroundColor: Colors.white,
                        foregroundColor: FoodFlowTheme.ink,
                      ),
                      icon: const AppIcon(AppIcons.arrowBack, size: 18),
                    ),
                    const SizedBox(width: 12),
                    const Expanded(
                      child: Text(
                        'View cart',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: FoodFlowTheme.ink,
                        ),
                      ),
                    ),
                    IconButton.filledTonal(
                      onPressed: () => _showClearCartDialog(context, cart),
                      style: IconButton.styleFrom(
                        backgroundColor: Colors.white,
                        foregroundColor: FoodFlowTheme.inkSoft,
                      ),
                      icon: const AppIcon(AppIcons.delete, size: 18),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 2, 12, 8),
                child: AccountSurfaceCard(
                  radius: 28,
                  padding: const EdgeInsets.all(10),
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
                                errorBuilder: (_, __, ___) =>
                                    _restaurantIcon(context),
                              )
                            : _restaurantIcon(context),
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
                              '${cart.itemCount} item${cart.itemCount == 1 ? '' : 's'} in this cart',
                              style: const TextStyle(
                                color: FoodFlowTheme.muted,
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            if (restaurant?.isPureVeg == true) ...[
                              const SizedBox(height: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 5,
                                ),
                                decoration: BoxDecoration(
                                  color: primary.withOpacity(0.1),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  'Pure Veg',
                                  style: TextStyle(
                                    color: primary,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      TextButton(
                        onPressed: () => _addMoreItems(context),
                        style: TextButton.styleFrom(
                          foregroundColor: primary,
                        ),
                        child: const Text(
                          'Add more',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                child: _buildDeliveryAddressCard(primary, restaurant?.id),
              ),
              if (_freeDeliveryThreshold != null)
                _buildFreeDeliveryMilestone(primary, subtotal),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                  children: [
                    ...cart.items.map(
                      (item) => CartItemCard(
                        item: item,
                        onIncrement: () =>
                            cart.incrementBySignature(item.signature),
                        onDecrement: () =>
                            cart.decrementBySignature(item.signature),
                        onRemove: () => cart.removeBySignature(item.signature),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(22),
                        border: Border.all(color: accountBorder),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const AccountSectionTitle(title: 'BILL DETAILS'),
                          const SizedBox(height: 14),
                          if (_isSummaryLoading)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: LinearProgressIndicator(
                                color: primary,
                                minHeight: 3,
                                backgroundColor: primary.withOpacity(0.1),
                              ),
                            ),
                          _billRow(context, 'Item total', subtotal),
                          _billRow(context, deliveryLabel, deliveryFee),
                          if (platformFee > 0)
                            _billRow(context, 'Platform fee', platformFee),
                          _billRow(
                            context,
                            _summaryTaxLabel,
                            tax,
                            onTap: () => _showTaxBreakdownPopup(tax),
                            tappable: true,
                          ),
                          const Divider(height: 26),
                          _billRow(context, 'To pay', total, isTotal: true),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        bottomNavigationBar: SafeArea(
          top: false,
          child: Container(
            padding: const EdgeInsets.fromLTRB(10, 6, 10, 10),
            decoration: const BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Color(0x12000000),
                  blurRadius: 18,
                  offset: Offset(0, -4),
                ),
              ],
            ),
            child: SizedBox(
              height: 60,
              child: ElevatedButton(
                onPressed: () => _proceedToCheckout(context, restaurant?.id),
                style: ElevatedButton.styleFrom(
                  backgroundColor: primary,
                  foregroundColor: Colors.white,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(18),
                  ),
                ),
                child: Row(
                  children: [
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          formatCurrency(context, total),
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                          ),
                        ),
                        Text(
                          '${cart.itemCount} item${cart.itemCount == 1 ? '' : 's'}',
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.84),
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                    const Spacer(),
                    Text(
                      'Proceed to checkout',
                      style: GoogleFonts.nunitoSans(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
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
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 10),
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
                  style: GoogleFonts.nunitoSans(
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
                setState(() => _selectedAddress = address);
                try {
                  await _api.post(
                    '${ApiConstants.setDefaultAddress}/${address.id}',
                  );
                } catch (e) {
                  debugPrint('Set default address error: $e');
                }
                await _refreshCartSummary();
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

  void _proceedToCheckout(BuildContext context, int? restaurantId) {
    if (_selectedAddress == null) {
      if (_addresses.isNotEmpty) {
        _showAddressSelector();
      } else {
        _addAddressAndRefresh(restaurantId);
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

    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const CheckoutScreen()),
    );
  }

  static Widget _billRow(
    BuildContext context,
    String label,
    double value, {
    bool isTotal = false,
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
                formatCurrency(context, value),
                style: TextStyle(
                  fontSize: isTotal ? 16 : 14,
                  fontWeight: isTotal ? FontWeight.w800 : FontWeight.w600,
                  color: FoodFlowTheme.ink,
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
