import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/app_config.dart';
import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../providers/auth_provider.dart';
import '../../providers/restaurant_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_image_loader.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';
import '../../widgets/restaurant/reject_order_dialog.dart';

class RestaurantOrdersScreen extends StatefulWidget {
  final bool showAppBar;

  const RestaurantOrdersScreen({Key? key, this.showAppBar = false})
      : super(key: key);

  @override
  State<RestaurantOrdersScreen> createState() => _RestaurantOrdersScreenState();
}

class _RestaurantOrdersScreenState extends State<RestaurantOrdersScreen> {
  final ApiService _api = ApiService();
  late RestaurantProvider _restaurantProvider;
  bool _isProviderListenerAttached = false;
  List<Order> _orders = [];
  bool _isLoading = true;
  String? _loadError;
  String _selectedFilter = 'all';

  static const _filters = [
    'all',
    'pending',
    'confirmed',
    'preparing',
    'ready_for_pickup',
    'delivered',
  ];

  @override
  void initState() {
    super.initState();
    _loadOrders();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_isProviderListenerAttached) {
      _restaurantProvider =
          Provider.of<RestaurantProvider>(context, listen: false);
      _restaurantProvider.addListener(_onRestaurantProviderUpdated);
      _isProviderListenerAttached = true;
    }
  }

  void _onRestaurantProviderUpdated() {
    if (!mounted) return;
    _loadOrders();
  }

  @override
  void dispose() {
    if (_isProviderListenerAttached) {
      _restaurantProvider.removeListener(_onRestaurantProviderUpdated);
    }
    super.dispose();
  }

  Future<void> _loadOrders() async {
    setState(() => _isLoading = true);
    try {
      final restaurantProvider = Provider.of<RestaurantProvider>(
        context,
        listen: false,
      );
      final params = <String, dynamic>{
        'restaurant_id':
            restaurantProvider.selectedRestaurantId?.toString() ?? 'all',
      };
      if (_selectedFilter != 'all') params['status'] = _selectedFilter;
      final response =
          await _api.get(ApiConstants.restaurantOrders, queryParams: params);
      if (response['success'] == true && mounted) {
        setState(() {
          _orders = _extractOrders(response['data'])
              .whereType<Map>()
              .map((json) => Order.fromJson(Map<String, dynamic>.from(json)))
              .toList();
          _loadError = null;
        });
      }
    } catch (e) {
      debugPrint('Load orders error: $e');
      if (mounted && _orders.isEmpty) {
        setState(() => _loadError = _cleanApiError(e));
      }
    }
    if (mounted) setState(() => _isLoading = false);
  }

  List<dynamic> _extractOrders(dynamic data) {
    if (data is List) return data;
    if (data is Map && data['data'] is List) return data['data'] as List;
    if (data is Map && data['orders'] is List) return data['orders'] as List;
    return const [];
  }

  String _cleanApiError(Object error) {
    final message = error.toString().trim();
    if (message.startsWith('Exception: ')) {
      return message.substring('Exception: '.length);
    }
    return message.isEmpty
        ? 'Please check your internet connection and try again.'
        : message;
  }

  String _filterLabel(String filter) {
    switch (filter) {
      case 'all':
        return 'All';
      case 'ready_for_pickup':
        return 'Ready';
      default:
        final words = filter.split('_');
        return words
            .map((word) => word.isEmpty
                ? word
                : '${word[0].toUpperCase()}${word.substring(1)}')
            .join(' ');
    }
  }

  Future<void> _updateStatus(int orderId, String status) async {
    try {
      final response = await _sendOrderAction(orderId, status);
      if (response['success'] == true) {
        await _loadOrders();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response['message']?.toString() ??
                  'Order ${status.replaceAll('_', ' ')}'),
              backgroundColor: Colors.green,
            ),
          );
        }
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
      );
    }
  }

  Future<void> _extendPrepTime(int orderId, int minutes) async {
    try {
      final response = await _api.post(
        ApiConstants.restaurantExtendPrepTime(orderId),
        data: {'additional_minutes': minutes},
      );
      if (response['success'] == true) {
        await _loadOrders();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response['message']?.toString() ??
                  'Preparation time extended'),
              backgroundColor: Colors.green,
            ),
          );
        }
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
      );
    }
  }

  Future<dynamic> _sendOrderAction(int orderId, String status) async {
    if (status == 'verify_takeaway_otp') {
      final otp = await _askPickupOtp();
      if (otp == null) return {'success': false};
      return _api.post(
        ApiConstants.restaurantVerifyTakeawayOtp(orderId),
        data: {'otp': otp},
      );
    }
    if (status == 'confirmed') {
      return _api.post(ApiConstants.restaurantAcceptOrder(orderId));
    }
    if (status == 'ready_for_pickup') {
      return _api.post(ApiConstants.restaurantOrderReady(orderId));
    }
    if (status == 'cancelled') {
      final reason = await _askRejectReason();
      if (reason == null) return {'success': false};
      return _api.post(
        ApiConstants.restaurantRejectOrder(orderId),
        data: {'reason': reason},
      );
    }

    return _api.post(
      ApiConstants.restaurantOrderStatus(orderId),
      data: {'status': status},
    );
  }

  Future<String?> _askPickupOtp() async {
    final controller = TextEditingController();
    final otp = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Verify Pickup OTP'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(
            hintText: 'Enter customer pickup OTP',
            counterText: '',
          ),
          keyboardType: TextInputType.number,
          maxLength: 8,
          autofocus: true,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              final value = controller.text.trim();
              if (value.length >= 4) Navigator.pop(dialogContext, value);
            },
            child: const Text('Verify'),
          ),
        ],
      ),
    );
    controller.dispose();
    return otp;
  }

  Future<String?> _askRejectReason() async {
    return showRestaurantRejectOrderDialog(context);
  }

  @override
  Widget build(BuildContext context) {
    final canManageOrders =
        Provider.of<AuthProvider>(context).currentUser?.canManageOrders ?? true;
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: widget.showAppBar
          ? AppBar(
              title: const Text('Orders'),
              actions: [
                IconButton(
                  onPressed: _loadOrders,
                  icon: const Icon(Icons.refresh),
                  tooltip: 'Refresh',
                ),
              ],
            )
          : null,
      body: Column(
        children: [
          _OrdersSummaryHeader(
            totalOrders: _orders.length,
            activeFilter: _selectedFilter,
          ),
          Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: _filters
                    .map(
                      (filter) => Padding(
                        padding: const EdgeInsets.only(right: 7),
                        child: ChoiceChip(
                          label: Text(_filterLabel(filter)),
                          selected: _selectedFilter == filter,
                          onSelected: (_) {
                            setState(() => _selectedFilter = filter);
                            _loadOrders();
                          },
                          selectedColor: FoodFlowTheme.orange,
                          backgroundColor: FoodFlowTheme.canvas,
                          side: const BorderSide(color: FoodFlowTheme.line),
                          labelStyle: TextStyle(
                            color: _selectedFilter == filter
                                ? Colors.white
                                : FoodFlowTheme.ink,
                            fontWeight: FontWeight.w800,
                            fontSize: 11,
                            height: 1,
                          ),
                          padding: const EdgeInsets.symmetric(horizontal: 8),
                          visualDensity: VisualDensity.compact,
                        ),
                      ),
                    )
                    .toList(),
              ),
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _loadError != null && _orders.isEmpty
                    ? NetworkErrorView(
                        message: _loadError,
                        onRetry: _loadOrders,
                      )
                    : _orders.isEmpty
                        ? FoodFlowTheme.emptyState(
                            icon: Icons.receipt_long_outlined,
                            title: 'No orders found',
                            subtitle: 'New restaurant orders will appear here.',
                          )
                        : RefreshIndicator(
                            onRefresh: _loadOrders,
                            child: ListView.builder(
                              padding:
                                  const EdgeInsets.fromLTRB(16, 12, 16, 20),
                              itemCount: _orders.length,
                              itemBuilder: (context, index) {
                                final order = _orders[index];
                                return _RestaurantOrderCard(
                                  order: order,
                                  canManageOrders: canManageOrders,
                                  onUpdateStatus: _updateStatus,
                                  onExtendPrepTime: _extendPrepTime,
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

class _OrdersSummaryHeader extends StatelessWidget {
  final int totalOrders;
  final String activeFilter;

  const _OrdersSummaryHeader({
    required this.totalOrders,
    required this.activeFilter,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 10),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: FoodFlowTheme.orange.withOpacity(0.10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              Icons.receipt_long_rounded,
              color: FoodFlowTheme.orange,
              size: 21,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Orders',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 17,
                    height: 1.1,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '$totalOrders tickets - ${_label(activeFilter)}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 11,
                    height: 1.15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static String _label(String filter) {
    if (filter == 'ready_for_pickup') return 'Ready';
    if (filter == 'all') return 'All';
    return filter
        .split('_')
        .map((word) => word.isEmpty
            ? word
            : '${word[0].toUpperCase()}${word.substring(1)}')
        .join(' ');
  }
}

class _RestaurantOrderCard extends StatelessWidget {
  final Order order;
  final bool canManageOrders;
  final Future<void> Function(int orderId, String status) onUpdateStatus;
  final Future<void> Function(int orderId, int minutes) onExtendPrepTime;

  const _RestaurantOrderCard({
    required this.order,
    required this.canManageOrders,
    required this.onUpdateStatus,
    required this.onExtendPrepTime,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: RestaurantPremium.panel(radius: 14),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.fromLTRB(12, 4, 8, 4),
          childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
          leading: _OrderItemImage(item: _firstItem(), size: 38),
          title: Row(
            children: [
              Expanded(
                child: Text(
                  '#${order.orderNumber}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 13,
                    height: 1.1,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              _StatusPill(text: order.statusText, color: order.statusColor),
            ],
          ),
          subtitle: Padding(
            padding: const EdgeInsets.only(top: 5),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${_itemsLabel()} - Payout ${formatCurrency(context, _restaurantPayout())}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 11,
                          height: 1.15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      _formatOrderTime(order.createdAt),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 10,
                        height: 1.15,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  order.customerName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 11,
                    height: 1.15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          children: [
            const Divider(height: 14),
            if (order.hasActivePreparationTimer) ...[
              _PreparationTimingPanel(
                order: order,
                canExtend: canManageOrders,
                onExtend: (minutes) => onExtendPrepTime(order.id, minutes),
              ),
              const SizedBox(height: 10),
            ],
            _detailTile(
              Icons.person_outline,
              'Customer',
              order.customerName,
            ),
            const SizedBox(height: 8),
            _detailTile(
              Icons.location_on_outlined,
              order.isTakeaway ? 'Pickup' : 'Address',
              order.deliveryAddress,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Items',
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 12,
                      height: 1.1,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                _PaymentPill(text: _paymentLabel()),
              ],
            ),
            const SizedBox(height: 8),
            if (order.items.isEmpty)
              const Text(
                'No item data available',
                style: TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ...order.items.map(
              (item) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  children: [
                    _OrderItemImage(item: item, size: 34),
                    const SizedBox(width: 9),
                    Expanded(
                      child: Text(
                        '${item.quantity}x ${item.name}',
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 11,
                          height: 1.15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    Text(
                      formatCurrency(context, item.totalPrice),
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const Divider(height: 18),
            _payoutBreakdown(context),
            const SizedBox(height: 10),
            _actions(),
          ],
        ),
      ),
    );
  }

  Widget _detailTile(IconData icon, String label, String value) {
    return Container(
      padding: const EdgeInsets.all(9),
      decoration: BoxDecoration(
        color: FoodFlowTheme.canvas,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Row(
        children: [
          Icon(icon, size: 15, color: FoodFlowTheme.orange),
          const SizedBox(width: 7),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 10,
                    height: 1.1,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  value.isEmpty ? '-' : value,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 11,
                    height: 1.15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _payoutBreakdown(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: FoodFlowTheme.canvas,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        children: [
          _payoutRow(context, 'Item subtotal', order.subtotal),
          if (order.discount > 0)
            _payoutRow(context, 'Discount', order.discount),
          _payoutRow(
            context,
            _commissionLabel(context),
            order.platformCommission,
            isDeduction: true,
          ),
          if (order.gstOnCommission > 0)
            _payoutRow(
              context,
              'GST on commission',
              order.gstOnCommission,
              isDeduction: true,
            ),
          if (order.paymentGatewayFee > 0)
            _payoutRow(
              context,
              'Payment gateway fee',
              order.paymentGatewayFee,
              isDeduction: true,
            ),
          const Divider(height: 16),
          _payoutRow(
            context,
            'Total payout',
            _restaurantPayout(),
            isTotal: true,
          ),
        ],
      ),
    );
  }

  Widget _payoutRow(
    BuildContext context,
    String label,
    num value, {
    bool isDeduction = false,
    bool isTotal = false,
  }) {
    final amount = isDeduction
        ? '-${formatCurrency(context, value)}'
        : formatCurrency(context, value);

    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: isTotal ? FoodFlowTheme.ink : FoodFlowTheme.muted,
                fontSize: isTotal ? 12 : 11,
                height: 1.15,
                fontWeight: isTotal ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Text(
            amount,
            style: TextStyle(
              color: isTotal
                  ? FoodFlowTheme.orange
                  : isDeduction
                      ? FoodFlowTheme.danger
                      : FoodFlowTheme.ink,
              fontSize: isTotal ? 13 : 11,
              height: 1.15,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  Widget _actions() {
    if (!canManageOrders) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: FoodFlowTheme.orange.withOpacity(0.10),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Text(
          'View-only order access',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: FoodFlowTheme.orange,
            fontSize: 11,
            fontWeight: FontWeight.w900,
          ),
        ),
      );
    }

    if (order.canRestaurantAccept) {
      return Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: () => onUpdateStatus(order.id, 'cancelled'),
              style: _outlineActionStyle(Colors.red),
              child: const Text('Reject'),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: ElevatedButton(
              onPressed: () => onUpdateStatus(order.id, 'confirmed'),
              style: _primaryActionStyle(),
              child: const Text('Accept'),
            ),
          ),
        ],
      );
    }

    if (order.canRestaurantStartPreparing) {
      return SizedBox(
        width: double.infinity,
        child: ElevatedButton(
          onPressed: () => onUpdateStatus(order.id, 'preparing'),
          style: _primaryActionStyle(),
          child: const Text('Start Preparing'),
        ),
      );
    }

    if (order.canRestaurantMarkReady) {
      return SizedBox(
        width: double.infinity,
        child: ElevatedButton(
          onPressed: () => onUpdateStatus(order.id, 'ready_for_pickup'),
          style: _primaryActionStyle(),
          child: const Text('Mark Ready'),
        ),
      );
    }

    if (order.canRestaurantVerifyTakeawayPickup) {
      return SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed: () => onUpdateStatus(order.id, 'verify_takeaway_otp'),
          icon: const Icon(Icons.password),
          style: _primaryActionStyle(),
          label: const Text('Verify Pickup OTP'),
        ),
      );
    }

    return const SizedBox.shrink();
  }

  ButtonStyle _primaryActionStyle() {
    return ElevatedButton.styleFrom(
      backgroundColor: FoodFlowTheme.orange,
      foregroundColor: Colors.white,
      elevation: 0,
      minimumSize: const Size.fromHeight(38),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
    );
  }

  ButtonStyle _outlineActionStyle(Color color) {
    return OutlinedButton.styleFrom(
      foregroundColor: color,
      minimumSize: const Size.fromHeight(38),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      side: BorderSide(color: color.withOpacity(0.35)),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
    );
  }

  String _itemsLabel() {
    final count = order.items.length;
    return '$count ${count == 1 ? 'Item' : 'Items'}';
  }

  double _restaurantPayout() {
    if (order.restaurantEarning > 0) return order.restaurantEarning;
    final calculated = order.subtotal -
        order.platformCommission -
        order.gstOnCommission -
        order.paymentGatewayFee;
    return calculated > 0 ? calculated : order.subtotal;
  }

  String _commissionLabel(BuildContext context) {
    final type = order.restaurantCommissionType.toLowerCase();
    final value = order.restaurantCommissionValue;
    if (type == 'fixed') {
      return 'Platform commission (${formatCurrency(context, value)} fixed)';
    }

    final percentage = value == value.roundToDouble()
        ? value.toStringAsFixed(0)
        : value.toStringAsFixed(2);
    return 'Platform commission ($percentage%)';
  }

  OrderItem? _firstItem() {
    return order.items.isEmpty ? null : order.items.first;
  }

  String _paymentLabel() {
    final raw = order.paymentMethod.toLowerCase();
    if (raw.contains('cod') || raw.contains('cash')) return 'COD';
    return 'Online';
  }

  String _formatOrderTime(DateTime value) {
    final local = value.toLocal();
    final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
    final minute = local.minute.toString().padLeft(2, '0');
    final period = local.hour >= 12 ? 'PM' : 'AM';
    return '$hour:$minute $period';
  }
}

class _PreparationTimingPanel extends StatelessWidget {
  final Order order;
  final bool canExtend;
  final ValueChanged<int> onExtend;

  const _PreparationTimingPanel({
    required this.order,
    required this.canExtend,
    required this.onExtend,
  });

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<int>(
      stream: Stream.periodic(const Duration(seconds: 1), (value) => value),
      builder: (context, _) {
        final delayed = order.isPreparationDelayed ||
            (order.readyByAt?.isBefore(DateTime.now()) == true &&
                (order.isConfirmed || order.isPreparing));
        final color = delayed ? FoodFlowTheme.danger : FoodFlowTheme.orange;
        final title = delayed ? 'Order delayed' : 'Ready countdown';
        final message = delayed
            ? 'Ask for more time so the customer and driver see the new estimate.'
            : 'Ready in ${order.readyTimeLabel}';

        return Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: color.withOpacity(0.09),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: color.withOpacity(0.24)),
          ),
          child: Row(
            children: [
              Icon(
                delayed ? Icons.warning_amber_rounded : Icons.timer_outlined,
                color: color,
                size: 20,
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        color: color,
                        fontSize: 12,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      message,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              if (canExtend && delayed)
                PopupMenuButton<int>(
                  tooltip: 'Extend time',
                  onSelected: onExtend,
                  itemBuilder: (context) => const [
                    PopupMenuItem(value: 5, child: Text('+5 min')),
                    PopupMenuItem(value: 10, child: Text('+10 min')),
                    PopupMenuItem(value: 15, child: Text('+15 min')),
                  ],
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: color.withOpacity(0.24)),
                    ),
                    child: Text(
                      'Extend',
                      style: TextStyle(
                        color: color,
                        fontSize: 11,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _OrderItemImage extends StatelessWidget {
  final OrderItem? item;
  final double size;

  const _OrderItemImage({required this.item, required this.size});

  @override
  Widget build(BuildContext context) {
    final imageUrl = _resolveImageUrl(item?.imageUrl ?? '');
    final radius = BorderRadius.circular(size * 0.28);
    final placeholder = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: FoodFlowTheme.orange.withOpacity(0.10),
        borderRadius: radius,
      ),
      child: Icon(
        Icons.restaurant_menu_rounded,
        color: FoodFlowTheme.orange,
        size: size * 0.52,
      ),
    );

    if (imageUrl.isEmpty) return placeholder;

    return NetworkImageLoader(
      imageUrl: imageUrl,
      width: size,
      height: size,
      borderRadius: radius,
    );
  }

  String _resolveImageUrl(String raw) {
    final value = raw.trim();
    if (value.isEmpty || value.toLowerCase() == 'null') return '';
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }

    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final origin = '${apiUri.scheme}://${apiUri.host}';
    final normalized = value.startsWith('/') ? value.substring(1) : value;
    if (normalized.startsWith('storage/')) return '$origin/$normalized';
    return '$origin/storage/$normalized';
  }
}

class _PaymentPill extends StatelessWidget {
  final String text;

  const _PaymentPill({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: FoodFlowTheme.success.withOpacity(0.10),
        borderRadius: BorderRadius.circular(9),
      ),
      child: Text(
        text,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: FoodFlowTheme.success,
          fontSize: 10,
          height: 1.1,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  final String text;
  final Color color;

  const _StatusPill({required this.text, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}
