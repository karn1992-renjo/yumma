import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../providers/auth_provider.dart';
import '../../providers/restaurant_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';
import '../../widgets/restaurant/reject_order_dialog.dart';

class RestaurantOrdersScreen extends StatefulWidget {
  const RestaurantOrdersScreen({Key? key}) : super(key: key);

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
      _restaurantProvider = Provider.of<RestaurantProvider>(context, listen: false);
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
      final params = <String, dynamic>{
        'restaurant_id':
            _restaurantProvider.selectedRestaurantId?.toString() ?? 'all',
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
      body: Column(
        children: [
          const PremiumRestaurantHeader(
            title: 'Order Flow',
            subtitle:
                'Accept, prepare, and hand off every ticket with confidence.',
            icon: Icons.receipt_long,
          ),
          Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: _filters
                    .map(
                      (filter) => Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: FilterChip(
                          label:
                              Text(filter.replaceAll('_', ' ').toUpperCase()),
                          selected: _selectedFilter == filter,
                          onSelected: (_) {
                            setState(() => _selectedFilter = filter);
                            _loadOrders();
                          },
                          selectedColor: FoodFlowTheme.orange,
                          backgroundColor: Colors.white,
                          side: const BorderSide(color: FoodFlowTheme.line),
                          labelStyle: TextStyle(
                            color: _selectedFilter == filter
                                ? Colors.white
                                : FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                            fontSize: 12,
                          ),
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
                          padding: const EdgeInsets.all(16),
                          itemCount: _orders.length,
                          itemBuilder: (context, index) {
                            final order = _orders[index];
                            return _RestaurantOrderCard(
                              order: order,
                              canManageOrders: canManageOrders,
                              onUpdateStatus: _updateStatus,
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

class _RestaurantOrderCard extends StatelessWidget {
  final Order order;
  final bool canManageOrders;
  final Future<void> Function(int orderId, String status) onUpdateStatus;

  const _RestaurantOrderCard({
    required this.order,
    required this.canManageOrders,
    required this.onUpdateStatus,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: RestaurantPremium.panel(radius: 16),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
          childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
          leading: CircleAvatar(
            backgroundColor: order.statusColor,
            child: Text(
              _orderInitial(order.orderNumber),
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          title: Text(
            '#${order.orderNumber}',
            style: const TextStyle(
              color: FoodFlowTheme.ink,
              fontWeight: FontWeight.w900,
            ),
          ),
          subtitle: Text(
            [
              formatCurrency(context, order.total),
              order.customerName,
              if (order.restaurantName?.isNotEmpty == true)
                order.restaurantName!,
            ].join(' - '),
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontWeight: FontWeight.w600,
            ),
          ),
          trailing: _StatusPill(text: order.statusText, color: order.statusColor),
          children: [
            _detailRow(Icons.person_outline, 'Customer', order.customerName),
            _detailRow(Icons.call_outlined, 'Phone', order.customerPhone),
            _detailRow(
              Icons.location_on_outlined,
              'Address',
              order.deliveryAddress,
            ),
            const Divider(height: 24),
            const Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Items',
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            const SizedBox(height: 8),
            if (order.items.isEmpty)
              const Text(
                'No item data available',
                style: TextStyle(
                  color: FoodFlowTheme.muted,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ...order.items.map(
              (item) => Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${item.quantity}x ${item.name}',
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    Text(
                      formatCurrency(context, item.totalPrice),
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const Divider(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Total',
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  formatCurrency(context, order.total),
                  style: TextStyle(
                    color: FoodFlowTheme.orange,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _actions(),
          ],
        ),
      ),
    );
  }

  Widget _detailRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: FoodFlowTheme.orange),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              '$label: ${value.isEmpty ? '-' : value}',
              style: const TextStyle(
                color: FoodFlowTheme.muted,
                fontWeight: FontWeight.w600,
              ),
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
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF3E8),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(
          'View-only order access',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: FoodFlowTheme.orange,
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
              style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
              child: const Text('Reject'),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: ElevatedButton(
              onPressed: () => onUpdateStatus(order.id, 'confirmed'),
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
          child: const Text('Start Preparing'),
        ),
      );
    }

    if (order.canRestaurantMarkReady) {
      return SizedBox(
        width: double.infinity,
        child: ElevatedButton(
          onPressed: () => onUpdateStatus(order.id, 'ready_for_pickup'),
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
          label: const Text('Verify Pickup OTP'),
        ),
      );
    }

    return const SizedBox.shrink();
  }

  String _orderInitial(String orderNumber) {
    if (orderNumber.length > 2) return orderNumber[2].toUpperCase();
    if (orderNumber.isNotEmpty) return orderNumber[0].toUpperCase();
    return 'O';
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
