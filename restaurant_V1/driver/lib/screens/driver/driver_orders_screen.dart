// lib/screens/driver/driver_orders_screen.dart
import 'dart:async';
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/new_order_notification_dialog.dart';

class DriverOrdersScreen extends StatefulWidget {
  const DriverOrdersScreen({super.key});

  @override
  State<DriverOrdersScreen> createState() => _DriverOrdersScreenState();
}

class _DriverOrdersScreenState extends State<DriverOrdersScreen>
    with WidgetsBindingObserver {
  final ApiService _api = ApiService();

  List<Order> _orders = [];
  bool _isLoading = true;
  String? _loadError;
  String _selectedStatus = 'all';
  Set<int> _knownOrderIds = {};
  Set<int> _notifiedOrderIds = {};
  Timer? _pollingTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadOrders();
    _startPolling();
  }

  Future<void> _loadOrders({bool notifyNewOrder = false}) async {
    if (!_isLoading) {
      setState(() => _isLoading = true);
    }

    try {
      final response = await _api.get(ApiConstants.driverOrders);
      if (response['success'] == true) {
        final data = _extractOrders(response['data']);
        final orders = data
            .whereType<Map>()
            .map((json) => Order.fromJson(Map<String, dynamic>.from(json)))
            .toList();
        final currentIds =
            orders.map((order) => order.id).whereType<int>().toSet();
        final newIds = currentIds.difference(_knownOrderIds);

        if (notifyNewOrder &&
            _knownOrderIds.isNotEmpty &&
            newIds.isNotEmpty &&
            mounted) {
          // Show notification dialog for new orders
          for (int orderId in newIds) {
            if (!_notifiedOrderIds.contains(orderId)) {
              final newOrder =
                  orders.firstWhere((order) => order.id == orderId);
              _notifiedOrderIds.add(orderId);
              _showNewOrderNotification(newOrder);
            }
          }
        }

        if (!mounted) return;
        setState(() {
          _orders = orders;
          _knownOrderIds = currentIds;
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
    return [];
  }

  void _startPolling() {
    _pollingTimer?.cancel();
    _pollingTimer = Timer.periodic(const Duration(seconds: 20), (_) {
      if (mounted) {
        _loadOrders(notifyNewOrder: true);
      }
    });
  }

  Future<void> _updateOrderStatus(int orderId, String status) async {
    try {
      final response = await _api.post(
        ApiConstants.updateOrderStatus(orderId),
        data: {
          'status': status,
        },
      );

      if (response['success'] == true) {
        await _loadOrders();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
                content: Text(
                    'Order status updated to ${status.replaceAll('_', ' ')}')),
          );
        }
      }
    } catch (e) {
      debugPrint('Update status error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update status: $e')),
        );
      }
    }
  }

  Future<void> _acceptAssignment(int orderId) async {
    try {
      final response = await _api.post(ApiConstants.driverAcceptOrder(orderId));
      if (response['success'] == true) {
        await _loadOrders();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Delivery accepted')),
          );
        }
      }
    } catch (e) {
      debugPrint('Accept delivery error: $e');
      await _loadOrders();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_cleanApiError(e))),
        );
      }
    }
  }

  Future<void> _rejectAssignment(int orderId) async {
    try {
      final response = await _api.post(
        ApiConstants.driverRejectOrder(orderId),
        data: {'reason': 'Rejected by driver'},
      );
      if (response['success'] == true) {
        await _loadOrders();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Delivery rejected')),
          );
        }
      }
    } catch (e) {
      debugPrint('Reject delivery error: $e');
      await _loadOrders();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_cleanApiError(e))),
        );
      }
    }
  }

  String _cleanApiError(Object error) {
    final message = error.toString().trim();
    if (message.startsWith('Exception: ')) {
      return message.substring('Exception: '.length);
    }
    return message.isEmpty ? 'Unable to update delivery' : message;
  }

  void _showNewOrderNotification(Order order) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => NewOrderNotificationDialog(
        order: order,
        onAccept: () => _acceptAssignment(order.id),
        onReject: () => _rejectAssignment(order.id),
      ),
    );
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _loadOrders(notifyNewOrder: true);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _pollingTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filteredOrders = _selectedStatus == 'all'
        ? _orders
        : _selectedStatus == 'new'
            ? _orders
                .where((order) => order.isDriverAssignmentPending)
                .toList()
            : _selectedStatus == 'running'
                ? _orders
                    .where((order) =>
                        !order.isDriverAssignmentPending &&
                        !order.isDelivered &&
                        !order.isCancelled)
                    .toList()
                : _orders
                    .where((order) => order.status == _selectedStatus)
                    .toList();

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Orders'),
        backgroundColor: Colors.white,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(50),
          child: Container(
            height: 50,
            margin: const EdgeInsets.symmetric(horizontal: 16),
            child: ListView(
              scrollDirection: Axis.horizontal,
              children: [
                _buildFilterChip('All', 'all'),
                const SizedBox(width: 8),
                _buildFilterChip('New', 'new'),
                const SizedBox(width: 8),
                _buildFilterChip('Running', 'running'),
                const SizedBox(width: 8),
                _buildFilterChip('Picked Up', 'picked_up'),
                const SizedBox(width: 8),
                _buildFilterChip('On The Way', 'on_the_way'),
                const SizedBox(width: 8),
                _buildFilterChip('Delivered', 'delivered'),
              ],
            ),
          ),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _loadError != null && _orders.isEmpty
              ? NetworkErrorView(
                  message: _loadError,
                  onRetry: _loadOrders,
                )
          : filteredOrders.isEmpty
              ? FoodFlowTheme.emptyState(
                  icon: Icons.delivery_dining_outlined,
                  title: 'No deliveries found',
                  subtitle: 'Assigned delivery orders will appear here.',
                )
          : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: filteredOrders.length,
                  itemBuilder: (context, index) {
                    final order = filteredOrders[index];
                    final isNew = order.isDriverAssignmentPending;
                    return InkWell(
                      onTap: () {
                        Navigator.pushNamed(
                          context,
                          '/driver/order',
                          arguments: order.id,
                        );
                      },
                      borderRadius: BorderRadius.circular(12),
                      child: Container(
                      margin: const EdgeInsets.only(bottom: 14),
                      decoration: FoodFlowTheme.surface(radius: 12),
                      clipBehavior: Clip.antiAlias,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (isNew)
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.symmetric(
                                horizontal: 14,
                                vertical: 10,
                              ),
                              decoration: const BoxDecoration(
                                gradient: LinearGradient(
                                  colors: [
                                    Color(0xFF15191F),
                                    Color(0xFF26313A),
                                  ],
                                ),
                              ),
                              child: Row(
                                children: [
                                  const Icon(
                                    Icons.notifications_active,
                                    color: FoodFlowTheme.success,
                                    size: 18,
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'New Order Incoming',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                  ),
                                  Text(
                                    formatCurrency(context, order.deliveryFee),
                                    style: const TextStyle(
                                      color: FoodFlowTheme.success,
                                      fontWeight: FontWeight.w900,
                                      fontSize: 18,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'Order #${order.orderNumber}',
                                style: const TextStyle(
                                  color: FoodFlowTheme.ink,
                                  fontWeight: FontWeight.w900,
                                  fontSize: 16,
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: order.statusColor.withOpacity(0.10),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  order.statusText,
                                  style: TextStyle(
                                    color: order.statusColor,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          if (order.isPartOfRouteBatch) ...[
                            const SizedBox(height: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 7,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFFEEF6FF),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                  color: const Color(0xFFBFDBFE),
                                ),
                              ),
                              child: Row(
                                children: [
                                  const Icon(
                                    Icons.route,
                                    size: 16,
                                    color: Color(0xFF2563EB),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'Grouped route: ${order.routeBatch!.ordersCount} orders • Active ${order.routeBatch!.activeOrderIds.length}',
                                      style: const TextStyle(
                                        color: Color(0xFF1D4ED8),
                                        fontSize: 12,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                          const SizedBox(height: 8),
                          _buildLocationLine(
                            Icons.storefront_outlined,
                            order.restaurant?.name ?? 'Restaurant',
                            order.restaurant?.address ?? 'Pickup location',
                            FoodFlowTheme.crimson,
                          ),
                          const SizedBox(height: 12),
                          _buildLocationLine(
                            Icons.person_pin_circle_outlined,
                            order.customerName,
                            order.deliveryAddress,
                            FoodFlowTheme.success,
                          ),
                          const SizedBox(height: 14),
                          Row(
                            children: [
                              _buildMiniMeta(Icons.route, '4.6 km'),
                              const SizedBox(width: 10),
                              _buildMiniMeta(
                                Icons.shopping_bag_outlined,
                                '${order.items.length} items',
                              ),
                              if (order.isPartOfRouteBatch) ...[
                                const SizedBox(width: 10),
                                _buildMiniMeta(
                                  Icons.layers_outlined,
                                  'Batch ${order.routeBatch!.ordersCount}',
                                ),
                              ],
                              const Spacer(),
                              Text(
                                formatCurrency(context, order.deliveryFee),
                                style: const TextStyle(
                                  color: FoodFlowTheme.success,
                                  fontWeight: FontWeight.w900,
                                  fontSize: 16,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 14),
                          if (order.isDriverAssignmentPending)
                            Row(
                              children: [
                                Expanded(
                                  child: OutlinedButton(
                                    onPressed: () =>
                                        _rejectAssignment(order.id),
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: FoodFlowTheme.ink,
                                      side: const BorderSide(
                                        color: FoodFlowTheme.line,
                                      ),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                    ),
                                    child: const Text('Reject'),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: ElevatedButton(
                                    onPressed: () =>
                                        _acceptAssignment(order.id),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: FoodFlowTheme.success,
                                      foregroundColor: Colors.white,
                                      elevation: 0,
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                    ),
                                    child: const Text('Accept'),
                                  ),
                                ),
                              ],
                            )
                          else
                            Row(
                              children: [
                                Expanded(
                                  child: ElevatedButton.icon(
                                    onPressed: () {
                                      Navigator.pushNamed(
                                        context,
                                        '/driver/order',
                                        arguments: order.id,
                                      );
                                    },
                                    icon: const Icon(Icons.navigation),
                                    label: const Text('Open Delivery'),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: FoodFlowTheme.crimson,
                                      foregroundColor: Colors.white,
                                      elevation: 0,
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                    ),
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
                    );
                  },
                ),
    );
  }

  Widget _buildLocationLine(
    IconData icon,
    String title,
    String subtitle,
    Color color,
  ) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: color.withOpacity(0.10),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, size: 18, color: color),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontWeight: FontWeight.w900,
                  color: FoodFlowTheme.ink,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(
                  fontSize: 12,
                  color: FoodFlowTheme.muted,
                  fontWeight: FontWeight.w600,
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildMiniMeta(IconData icon, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: FoodFlowTheme.faint),
        const SizedBox(width: 4),
        Text(
          label,
          style: const TextStyle(
            color: FoodFlowTheme.muted,
            fontSize: 12,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }

  Widget _buildFilterChip(String label, String value) {
    final isSelected = _selectedStatus == value;
    return FilterChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (selected) {
        setState(() {
          _selectedStatus = value;
        });
        _loadOrders();
      },
      backgroundColor: Colors.white,
      selectedColor: FoodFlowTheme.crimson.withOpacity(0.1),
      checkmarkColor: FoodFlowTheme.crimson,
      side: BorderSide(
        color: isSelected ? FoodFlowTheme.crimson : FoodFlowTheme.line,
      ),
      labelStyle: TextStyle(
        color: isSelected ? FoodFlowTheme.crimson : FoodFlowTheme.ink,
        fontWeight: FontWeight.w900,
      ),
    );
  }
}
