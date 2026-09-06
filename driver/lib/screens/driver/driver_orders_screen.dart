// lib/screens/driver/driver_orders_screen.dart
import 'dart:async';
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/websocket_service.dart';
import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';

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
  Timer? _realtimeRefreshDebounce;
  StreamSubscription<Map<String, dynamic>>? _driverEventsSubscription;
  bool _pendingRealtimeNewOrderNotification = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadOrders();
    _subscribeToRealtimeOrders();
    _startPolling();
  }

  Future<void> _openOrder(int id) async {
    await Navigator.pushNamed(context, '/driver/order', arguments: id);
    if (mounted) _loadOrders(showLoader: false);
  }

  Future<void> _loadOrders({
    bool notifyNewOrder = false,
    bool showLoader = true,
  }) async {
    if (showLoader && !_isLoading) {
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

        // NOTE: the incoming-order alert UI is owned solely by
        // IncomingOrderAlertService (driven from the dashboard). This screen
        // used to also pop its own NewOrderNotificationDialog here, which
        // caused two stacked popups — it now just refreshes the list.
        if (notifyNewOrder && newIds.isNotEmpty) {
          _notifiedOrderIds.addAll(newIds);
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
        _loadOrders(notifyNewOrder: true, showLoader: false);
      }
    });
  }

  void _subscribeToRealtimeOrders() {
    _driverEventsSubscription = WebSocketService().driverEvents.listen((event) {
      final isNewAssignment = _isDriverAssignmentEvent(event);
      _pendingRealtimeNewOrderNotification =
          _pendingRealtimeNewOrderNotification || isNewAssignment;
      _scheduleRealtimeOrderRefresh();
    });
  }

  bool _isDriverAssignmentEvent(Map<String, dynamic> event) {
    final eventName = event['_event']?.toString().toLowerCase() ?? '';
    final type =
        event['type']?.toString().toLowerCase().replaceAll('-', '_') ?? '';
    final payloadEvent =
        event['event']?.toString().toLowerCase().replaceAll('-', '_') ?? '';
    return eventName == 'driver-order-assigned' ||
        eventName.endsWith('driverorderassignedevent') ||
        type == 'driver_order_assigned' ||
        payloadEvent == 'driver_order_assigned';
  }

  void _scheduleRealtimeOrderRefresh() {
    _realtimeRefreshDebounce?.cancel();
    _realtimeRefreshDebounce = Timer(const Duration(milliseconds: 350), () {
      if (!mounted) return;
      final notifyNewOrder = _pendingRealtimeNewOrderNotification;
      _pendingRealtimeNewOrderNotification = false;
      _loadOrders(
        notifyNewOrder: notifyNewOrder,
        showLoader: false,
      );
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
        WebSocketService().notifyLocalOrderChange(orderId: orderId);
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
        WebSocketService().notifyLocalOrderChange(orderId: orderId);
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

  String _driverEarningText(Order order) {
    final earning = formatCurrency(context, order.driverEarningAmount);
    if (order.driverIncentiveAmount > 0) {
      return '$earning + ${formatCurrency(context, order.driverIncentiveAmount)} incentive';
    }
    return earning;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _loadOrders(notifyNewOrder: true, showLoader: false);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _realtimeRefreshDebounce?.cancel();
    _driverEventsSubscription?.cancel();
    _pollingTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _selectedStatus == 'all'
        ? _orders
        : _selectedStatus == 'new'
            ? _orders.where((o) => o.isDriverAssignmentPending).toList()
            : _selectedStatus == 'running'
                ? _orders
                    .where((o) =>
                        !o.isDriverAssignmentPending &&
                        !o.isDelivered &&
                        !o.isCancelled)
                    .toList()
                : _orders.where((o) => o.status == _selectedStatus).toList();

    const filters = [
      ('all', 'All'),
      ('new', 'New'),
      ('running', 'Running'),
      ('picked_up', 'Picked up'),
      ('on_the_way', 'On the way'),
      ('delivered', 'Delivered'),
    ];

    return AuroraScaffold(
      appBar: GlassAppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Deliveries',
              style: TextStyle(
                color: foodflow.ink,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (!_isLoading)
              Text(
                '${_orders.length} total • ${_orders.where((o) => !o.isDelivered && !o.isCancelled).length} active',
                style: TextStyle(
                  color: foodflow.muted,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
          ],
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(52),
          child: SizedBox(
            height: 52,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
              itemCount: filters.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, i) {
                final (value, label) = filters[i];
                final selected = _selectedStatus == value;
                return GestureDetector(
                  onTap: () {
                    setState(() => _selectedStatus = value);
                  },
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: selected
                          ? foodflow.orange
                          : foodflow.surfaceColor,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: selected ? foodflow.orange : foodflow.line,
                      ),
                    ),
                    child: Text(
                      label,
                      style: TextStyle(
                        color: selected ? Colors.white : foodflow.inkSoft,
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _loadError != null && _orders.isEmpty
              ? SafeArea(
                  child: NetworkErrorView(
                      message: _loadError, onRetry: _loadOrders))
              : filtered.isEmpty
                  ? ListView(
                      children: [
                        const SizedBox(height: 60),
                        foodflow.emptyState(
                          icon: Icons.local_shipping_outlined,
                          title: 'No deliveries here',
                          subtitle:
                              'Assigned and past deliveries show up on this list.',
                        ),
                      ],
                    )
                  : RefreshIndicator(
                      onRefresh: _loadOrders,
                      child: ListView.builder(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                        itemCount: filtered.length,
                        itemBuilder: (context, index) => AuroraEntrance(
                          delay: Duration(
                              milliseconds: (index * 55).clamp(0, 350)),
                          child: _orderCard(filtered[index]),
                        ),
                      ),
                    ),
    );
  }

  Widget _orderCard(Order order) {
    final pending = order.isDriverAssignmentPending;
    final done = order.isDelivered || order.isCancelled;

    return GlassCard(
      solid: true,
      margin: const EdgeInsets.only(bottom: 14),
      padding: EdgeInsets.zero,
      radius: 18,
      onTap: () => _openOrder(order.id),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (pending)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
              decoration: BoxDecoration(
                gradient: foodflow.brandGradient,
                borderRadius:
                    const BorderRadius.vertical(top: Radius.circular(18)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.bolt_rounded, color: Colors.white, size: 18),
                  const SizedBox(width: 8),
                  const Expanded(
                    child: Text(
                      'New delivery request',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 13,
                      ),
                    ),
                  ),
                  Text(
                    _driverEarningText(order),
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
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
                  children: [
                    Expanded(
                      child: Text(
                        '#${order.orderNumber}',
                        style: TextStyle(
                          color: foodflow.ink,
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 9, vertical: 4),
                      decoration: BoxDecoration(
                        color: order.statusColor.withOpacity(0.12),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        order.statusText,
                        style: TextStyle(
                          color: order.statusColor,
                          fontSize: 11.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
                if (order.isPartOfRouteBatch) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: foodflow.orange.withOpacity(0.10),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.alt_route_rounded,
                            size: 15, color: foodflow.orange),
                        const SizedBox(width: 6),
                        Text(
                          'Grouped route • ${order.routeBatch!.ordersCount} stops',
                          style: TextStyle(
                            color: foodflow.orange,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                _routeRow(
                  Icons.storefront_rounded,
                  foodflow.orange,
                  order.restaurant?.name ?? 'Pickup',
                  order.restaurant?.address ?? '',
                ),
                Padding(
                  padding: const EdgeInsets.only(left: 15),
                  child: SizedBox(
                    height: 14,
                    child: VerticalDivider(
                      color: foodflow.line,
                      thickness: 2,
                      width: 2,
                    ),
                  ),
                ),
                _routeRow(
                  Icons.location_on_rounded,
                  foodflow.success,
                  order.customerName,
                  order.deliveryAddress,
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    _meta(Icons.shopping_bag_outlined,
                        '${order.items.length} item${order.items.length == 1 ? '' : 's'}'),
                    const SizedBox(width: 14),
                    _meta(
                      order.isCodPayment
                          ? Icons.payments_outlined
                          : Icons.credit_card_outlined,
                      order.isCodPayment
                          ? (order.isPaymentPaid ? 'COD · paid' : 'COD')
                          : 'Prepaid',
                    ),
                    const Spacer(),
                    if (!pending)
                      Text(
                        _driverEarningText(order),
                        style: TextStyle(
                          color: foodflow.success,
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
                  ],
                ),
                if (pending) ...[
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => _rejectAssignment(order.id),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: foodflow.inkSoft,
                            side: BorderSide(color: foodflow.line),
                            padding: const EdgeInsets.symmetric(vertical: 13),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                          ),
                          child: const Text('Reject'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        flex: 2,
                        child: FilledButton(
                          onPressed: () => _acceptAssignment(order.id),
                          style: FilledButton.styleFrom(
                            backgroundColor: foodflow.success,
                            minimumSize: const Size.fromHeight(46),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                          ),
                          child: const Text('Accept delivery'),
                        ),
                      ),
                    ],
                  ),
                ] else if (!done) ...[
                  const SizedBox(height: 14),
                  FilledButton.icon(
                    onPressed: () => _openOrder(order.id),
                    icon: const Icon(Icons.navigation_rounded, size: 18),
                    label: const Text('Continue delivery'),
                    style: FilledButton.styleFrom(
                      backgroundColor: foodflow.orange,
                      minimumSize: const Size.fromHeight(46),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
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

  Widget _routeRow(IconData icon, Color color, String title, String subtitle) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 30,
          height: 30,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: color.withOpacity(0.12),
            borderRadius: BorderRadius.circular(9),
          ),
          child: Icon(icon, size: 16, color: color),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: foodflow.ink,
                  fontSize: 13.5,
                ),
              ),
              if (subtitle.trim().isNotEmpty)
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 12,
                    color: foodflow.muted,
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _meta(IconData icon, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: foodflow.faint),
        const SizedBox(width: 5),
        Text(
          label,
          style: TextStyle(
            color: foodflow.muted,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }



}
