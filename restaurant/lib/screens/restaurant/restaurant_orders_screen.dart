import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/app_config.dart';
import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../providers/auth_provider.dart';
import '../../providers/restaurant_provider.dart';
import '../../services/api_service.dart';
import '../../services/app_order_overlay_service.dart';
import '../../services/restaurant_order_realtime_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
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
  bool _hasData = false;
  String? _loadError;
  String _selectedFilter = 'all';
  Map<String, dynamic>? _incomingOrderPayload;
  bool _showIncomingOrderBanner = false;
  bool _isOpeningIncomingOrder = false;
  StreamSubscription<Map<String, dynamic>>? _orderUpdateSubscription;

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
    _orderUpdateSubscription =
        RestaurantOrderRealtimeService.instance.updates.listen(
      _onRealtimeOrderUpdate,
    );
    _loadOrders();
  }

  void _onRealtimeOrderUpdate(Map<String, dynamic> payload) {
    if (!mounted || !_matchesRestaurantScope(payload)) return;
    setState(() {
      _upsertRealtimeOrder(payload);
      _orders.sort((a, b) => b.createdAt.compareTo(a.createdAt));
    });
  }

  bool _matchesRestaurantScope(Map<String, dynamic> payload) {
    if (!_isProviderListenerAttached ||
        _restaurantProvider.selectedRestaurantId == null) {
      return true;
    }
    final restaurant = payload['restaurant'];
    final restaurantId = _parseNullableInt(
      payload['restaurant_id'] ?? (restaurant is Map ? restaurant['id'] : null),
    );
    if (restaurantId != null) {
      return restaurantId == _restaurantProvider.selectedRestaurantId;
    }
    final orderId = _parseNullableInt(payload['id'] ?? payload['order_id']);
    return orderId != null && _orders.any((order) => order.id == orderId);
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
    _mergeRealtimeOrdersFromProvider();
  }

  void _mergeRealtimeOrdersFromProvider() {
    final payloads = <Map<String, dynamic>>[];
    for (final source in [
      ..._restaurantProvider.pendingOrders,
      ..._restaurantProvider.activeOrders,
    ]) {
      if (source is Map<String, dynamic>) {
        payloads.add(source);
      } else if (source is Map) {
        payloads.add(Map<String, dynamic>.from(source));
      }
    }
    if (payloads.isEmpty) return;

    setState(() {
      for (final payload in payloads) {
        _upsertRealtimeOrder(payload);
      }
      _orders.sort((a, b) => b.createdAt.compareTo(a.createdAt));
    });
  }

  void _upsertRealtimeOrder(Map<String, dynamic> payload) {
    final incoming = Order.fromJson(payload);
    if (incoming.canRestaurantAccept) {
      _incomingOrderPayload = payload;
      _showIncomingOrderBanner = true;
    } else if (_parseNullableInt(
          _incomingOrderPayload?['id'] ?? _incomingOrderPayload?['order_id'],
        ) ==
        incoming.id) {
      _incomingOrderPayload = null;
      _showIncomingOrderBanner = false;
    }

    if (!_matchesSelectedFilter(incoming)) {
      _orders.removeWhere((order) => order.id == incoming.id);
      return;
    }

    final index = _orders.indexWhere((order) => order.id == incoming.id);
    if (index == -1) {
      _orders.insert(0, incoming);
      return;
    }

    final hasFullDetails = payload.containsKey('items') ||
        payload.containsKey('order_items') ||
        payload.containsKey('customer_name') ||
        payload.containsKey('total');
    if (hasFullDetails) {
      _orders[index] = incoming;
      return;
    }

    _orders[index] = _orders[index].copyWithRealtime(
      status: payload['status']?.toString(),
      driverId: _parseNullableInt(payload['driver_id']),
    );
  }

  bool _matchesSelectedFilter(Order order) {
    return _selectedFilter == 'all' || order.status == _selectedFilter;
  }

  int? _parseNullableInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  @override
  void dispose() {
    _orderUpdateSubscription?.cancel();
    if (_isProviderListenerAttached) {
      _restaurantProvider.removeListener(_onRestaurantProviderUpdated);
    }
    super.dispose();
  }

  void _applyOrders(dynamic response) {
    if (response is! Map || response['success'] != true) return;
    _orders = _extractOrders(response['data'])
        .whereType<Map>()
        .map((json) => Order.fromJson(Map<String, dynamic>.from(json)))
        .toList();
    _loadError = null;
    _hasData = true;
  }

  Future<void> _loadOrders() async {
    if (!_hasData) setState(() => _isLoading = true);
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
      final response = await _api.getWithCache(
        ApiConstants.restaurantOrders,
        queryParams: params,
        onCache: (cached) {
          if (!mounted) return;
          setState(() {
            _applyOrders(cached);
            _isLoading = false;
          });
        },
      );
      if (response['success'] == true && mounted) {
        setState(() => _applyOrders(response));
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
    String? rejectReason;
    if (status == 'cancelled') {
      rejectReason = await _askRejectReason();
      if (!mounted || rejectReason == null) return;
    }

    try {
      final response = await _sendOrderAction(
        orderId,
        status,
        rejectReason: rejectReason,
      );
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

  Future<dynamic> _sendOrderAction(
    int orderId,
    String status, {
    String? rejectReason,
  }) async {
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
      final reason = rejectReason;
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

  Future<Map<String, dynamic>> _incomingOrderDetailsPayload(
    Map<String, dynamic> payload,
  ) async {
    final orderId = _parseNullableInt(payload['id'] ?? payload['order_id']);
    if (orderId == null) return payload;

    try {
      final response = await _api.get(
        ApiConstants.restaurantOrderDetails(orderId),
      );
      final body = response['data'];
      if (body is Map<String, dynamic>) {
        return <String, dynamic>{...payload, ...body};
      }
      if (body is Map) {
        return <String, dynamic>{
          ...payload,
          ...Map<String, dynamic>.from(body),
        };
      }
    } catch (error) {
      debugPrint('Incoming order details error: $error');
    }
    return payload;
  }

  Future<void> _openIncomingOrderAcceptScreen() async {
    final payload = _incomingOrderPayload;
    if (payload == null || _isOpeningIncomingOrder) return;

    setState(() => _isOpeningIncomingOrder = true);
    final fullPayload = await _incomingOrderDetailsPayload(payload);
    if (!mounted) return;

    setState(() => _isOpeningIncomingOrder = false);
    final orderId = _parseNullableInt(
      fullPayload['id'] ?? fullPayload['order_id'],
    );
    if (orderId == null) return;

    var handled = false;
    await AppOrderOverlayService.showRestaurantOrder(
      fullPayload,
      onAccept: (id, preparationMinutes) async {
        final success = await _restaurantProvider.acceptOrder(
          id,
          preparationTimeMinutes: preparationMinutes,
        );
        handled = success;
        return success;
      },
      onReject: (id, reason) async {
        final selectedReason =
            reason.trim().isEmpty ? 'Rejected by restaurant' : reason.trim();
        final success = await _restaurantProvider.rejectOrder(
          id,
          selectedReason,
        );
        handled = success;
        return success;
      },
    );

    if (!mounted || !handled) return;
    setState(() {
      _incomingOrderPayload = null;
      _showIncomingOrderBanner = false;
    });
    await _loadOrders();
  }

  @override
  Widget build(BuildContext context) {
    Widget list;
    if (_isLoading) {
      list = Center(child: CircularProgressIndicator(color: foodflow.orange));
    } else if (_loadError != null && _orders.isEmpty) {
      list = NetworkErrorView(message: _loadError, onRetry: _loadOrders);
    } else if (_orders.isEmpty) {
      list = FoodFlowTheme.emptyState(
        icon: Icons.receipt_long_outlined,
        title: 'No orders found',
        subtitle: 'New restaurant orders will appear here.',
      );
    } else {
      final groups = _groupByDay(_orders);
      final rows = <Widget>[];
      var idx = 0;
      groups.forEach((header, orders) {
        rows.add(_DateGroupHeader(label: header, count: orders.length));
        for (final order in orders) {
          rows.add(AuroraEntrance(
            delay: Duration(milliseconds: (idx++ * 35).clamp(0, 280)),
            child: _OrderTicketRow(
              order: order,
              onTap: () => Navigator.pushNamed(
                context,
                '/restaurant/order',
                arguments: order.id,
              ).then((_) => _loadOrders()),
            ),
          ));
        }
      });
      list = RefreshIndicator(
        onRefresh: _loadOrders,
        color: foodflow.orange,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(14, 6, 14, 120),
          children: rows,
        ),
      );
    }

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: widget.showAppBar,
      appBar: widget.showAppBar
          ? GlassAppBar(
              leading: const BackButton(),
              title: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Orders',
                    style: TextStyle(
                      color: foodflow.ink,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  Text(
                    '${_orders.length} ${_orders.length == 1 ? 'ticket' : 'tickets'} - ${_OrdersSummaryHeader._label(_selectedFilter)}',
                    style: TextStyle(
                      color: foodflow.muted,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
              actions: [
                IconButton(
                  onPressed: _loadOrders,
                  icon: Icon(Icons.refresh_rounded, color: foodflow.ink),
                  tooltip: 'Refresh',
                ),
              ],
            )
          : null,
      body: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(color: foodflow.canvas),
              child: Stack(children: AuroraTheme.auroraBlobs()),
            ),
          ),
          Positioned.fill(
            child: SafeArea(
              bottom: false,
              child: Column(
                children: [
                  if (!widget.showAppBar) const SizedBox(height: 4),
                  SizedBox(
                    height: 48,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      itemCount: _filters.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 8),
                      itemBuilder: (context, i) {
                        final filter = _filters[i];
                        return _OrdersFilterPill(
                          label: _filterLabel(filter),
                          selected: _selectedFilter == filter,
                          onTap: () {
                            if (_selectedFilter == filter) return;
                            setState(() => _selectedFilter = filter);
                            _loadOrders();
                          },
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 6),
                  if (!_isLoading && _orders.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(14, 0, 14, 4),
                      child: _OrdersSummaryBar(orders: _orders),
                    ),
                  Expanded(child: list),
                ],
              ),
            ),
          ),
          if (_incomingOrderPayload != null)
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: _IncomingOrderSlideBanner(
                visible: _showIncomingOrderBanner,
                loading: _isOpeningIncomingOrder,
                payload: _incomingOrderPayload!,
                onTap: _openIncomingOrderAcceptScreen,
                onDismiss: () =>
                    setState(() => _showIncomingOrderBanner = false),
              ),
            ),
        ],
      ),
    );
  }
}

class _OrdersFilterPill extends StatelessWidget {
  const _OrdersFilterPill({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: selected
              ? foodflow.orange
              : (foodflow.isDark ? foodflow.elevatedSurface : Colors.white),
          borderRadius: BorderRadius.circular(13),
          border: Border.all(
            color: selected ? foodflow.orange : foodflow.line,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: selected ? Colors.white : foodflow.ink,
            fontSize: 12.5,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}

class _IncomingOrderSlideBanner extends StatelessWidget {
  const _IncomingOrderSlideBanner({
    required this.visible,
    required this.loading,
    required this.payload,
    required this.onTap,
    required this.onDismiss,
  });

  final bool visible;
  final bool loading;
  final Map<String, dynamic> payload;
  final VoidCallback onTap;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final orderNumber = _text(
        payload['order_number'] ?? payload['order_no'] ?? payload['id'],
        'Order');
    final restaurantName = _text(payload['restaurant_name'], 'New order');
    final total =
        payload['total'] ?? payload['grand_total'] ?? payload['amount'];
    final items = payload['items'] ?? payload['order_items'];
    final itemCount = items is List ? items.length : null;

    return SafeArea(
      top: false,
      minimum: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      child: AnimatedSlide(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOutCubic,
        offset: visible ? Offset.zero : const Offset(0, 1.1),
        child: AnimatedOpacity(
          duration: const Duration(milliseconds: 180),
          opacity: visible ? 1 : 0,
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: loading ? null : onTap,
              borderRadius: BorderRadius.circular(18),
              child: Container(
                padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
                decoration: BoxDecoration(
                  color: FoodFlowTheme.success,
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x22000000),
                      blurRadius: 18,
                      offset: Offset(0, 8),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(.18),
                        borderRadius: BorderRadius.circular(13),
                      ),
                      child: loading
                          ? const Padding(
                              padding: EdgeInsets.all(12),
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.receipt_long, color: Colors.white),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            'You have a new order',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            '#$orderNumber • $restaurantName${itemCount == null ? '' : ' • $itemCount item${itemCount == 1 ? '' : 's'}'}${total == null ? '' : ' • ${formatCurrencyValue(context, total)}'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Colors.white.withOpacity(.86),
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      onPressed: onDismiss,
                      icon: const Icon(Icons.close, color: Colors.white),
                    ),
                    const Icon(Icons.arrow_forward, color: Colors.white),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  static String _text(dynamic value, String fallback) {
    final text = value?.toString().trim();
    return text == null || text.isEmpty ? fallback : text;
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
                Text(
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
                  style: TextStyle(
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

double orderPayout(Order order) {
  if (order.restaurantEarning > 0) return order.restaurantEarning;
  final calc = order.subtotal -
      order.platformCommission -
      order.gstOnCommission -
      order.paymentGatewayFee;
  return calc > 0 ? calc : order.subtotal;
}

String _orderClock(DateTime value) {
  final d = value.toLocal();
  final h = d.hour % 12 == 0 ? 12 : d.hour % 12;
  final m = d.minute.toString().padLeft(2, '0');
  return '$h:$m ${d.hour >= 12 ? 'PM' : 'AM'}';
}

Map<String, List<Order>> _groupByDay(List<Order> orders) {
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final out = <String, List<Order>>{};
  for (final o in orders) {
    final d = o.createdAt.toLocal();
    final day = DateTime(d.year, d.month, d.day);
    final diff = today.difference(day).inDays;
    final key = diff == 0
        ? 'Today'
        : diff == 1
            ? 'Yesterday'
            : '${day.day.toString().padLeft(2, '0')} '
                '${const [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
              ][day.month - 1]}';
    (out[key] ??= []).add(o);
  }
  return out;
}

/// A one-line at-a-glance report for the current filter.
class _OrdersSummaryBar extends StatelessWidget {
  const _OrdersSummaryBar({required this.orders});
  final List<Order> orders;

  @override
  Widget build(BuildContext context) {
    final payout =
        orders.fold<double>(0, (sum, o) => sum + orderPayout(o));
    final delivered = orders.where((o) => o.isDelivered).length;
    final pct = orders.isEmpty ? 0 : (delivered * 100 / orders.length).round();

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [foodflow.orange, foodflow.orangeDark],
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: foodflow.orange.withOpacity(0.28),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          _sumCell('${orders.length}', 'orders'),
          _sumDivider(),
          _sumCell(formatCurrency(context, payout), 'payout'),
          _sumDivider(),
          _sumCell('$pct%', 'delivered'),
        ],
      ),
    );
  }

  Widget _sumCell(String v, String l) => Expanded(
        child: Column(
          children: [
            Text(v,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                )),
            Text(l,
                style: TextStyle(
                  color: Colors.white.withOpacity(0.8),
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                )),
          ],
        ),
      );

  Widget _sumDivider() =>
      Container(width: 1, height: 26, color: Colors.white.withOpacity(0.25));
}

class _DateGroupHeader extends StatelessWidget {
  const _DateGroupHeader({required this.label, required this.count});
  final String label;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
      child: Row(
        children: [
          Text(
            label.toUpperCase(),
            style: TextStyle(
              color: foodflow.muted,
              fontSize: 11,
              fontWeight: FontWeight.w900,
              letterSpacing: 1,
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '$count',
            style: TextStyle(
              color: foodflow.faint,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(child: Divider(color: foodflow.line, height: 1)),
        ],
      ),
    );
  }
}

/// Compact order ticket: status-coloured spine, key facts, payout, tap -> detail.
class _OrderTicketRow extends StatelessWidget {
  const _OrderTicketRow({required this.order, required this.onTap});
  final Order order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final n = order.items.length;
    final cod = order.paymentMethod.toLowerCase().contains('cod') ||
        order.paymentMethod.toLowerCase().contains('cash');
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(width: 4, color: order.statusColor),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(13, 12, 12, 12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                '#${order.orderNumber}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: foodflow.ink,
                                  fontSize: 14.5,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: order.statusColor.withOpacity(0.14),
                                borderRadius: BorderRadius.circular(99),
                              ),
                              child: Text(
                                order.statusText,
                                style: TextStyle(
                                  color: order.statusColor,
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                order.customerName,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: foodflow.inkSoft,
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            Text(
                              _orderClock(order.createdAt),
                              style: TextStyle(
                                color: foodflow.muted,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Icon(Icons.lunch_dining_outlined,
                                size: 14, color: foodflow.muted),
                            const SizedBox(width: 4),
                            Text(
                              '$n ${n == 1 ? 'item' : 'items'}',
                              style: TextStyle(
                                color: foodflow.muted,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(width: 10),
                            Icon(
                              cod
                                  ? Icons.payments_outlined
                                  : Icons.credit_card_rounded,
                              size: 14,
                              color: foodflow.muted,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              cod ? 'COD' : 'Prepaid',
                              style: TextStyle(
                                color: foodflow.muted,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const Spacer(),
                            Text(
                              formatCurrency(context, orderPayout(order)),
                              style: TextStyle(
                                color: foodflow.orange,
                                fontSize: 13.5,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.only(right: 6),
                  child: Icon(Icons.chevron_right_rounded,
                      color: foodflow.faint),
                ),
              ],
            ),
          ),
        ),
      ),
    );
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
                      style: TextStyle(
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
        style: TextStyle(
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
