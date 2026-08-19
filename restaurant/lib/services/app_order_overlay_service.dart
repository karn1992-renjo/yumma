import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'dart:async';
import '../config/api_constants.dart';
import '../theme/foodflow_theme.dart';
import '../utils/currency_utils.dart';
import 'api_service.dart';
import 'navigation_service.dart';
import 'sound_service.dart';

class AppOrderOverlayService {
  AppOrderOverlayService._();

  static bool _isShowing = false;
  static int? _activeOrderId;

  static Future<void> showRestaurantOrder(
    Map<String, dynamic> order, {
    Future<bool> Function(int orderId, int preparationMinutes)? onAccept,
    Future<bool> Function(int orderId, String reason)? onReject,
    Future<bool> Function(
      int orderId,
      List<int> menuItemIds,
      String availabilityOption,
    )? onMarkOutOfStock,
    Future<bool> Function(int orderId)? onTimeout,
    VoidCallback? onViewDetails,
    int durationSeconds = 30,
  }) async {
    order = _normalizeOrder(order);
    final context = appNavigatorKey.currentContext ??
        appNavigatorKey.currentState?.overlay?.context;
    final orderId = _parseId(order['id'] ?? order['order_id']);
    if (context == null || orderId == null || _isShowing) {
      debugPrint(
        'Could not show restaurant order overlay: context=$context orderId=$orderId isShowing=$_isShowing',
      );
      return;
    }

    await HapticFeedback.heavyImpact();
    SoundService.startIncomingOrderAlarm();
    _isShowing = true;
    _activeOrderId = orderId;
    try {
      await showModalBottomSheet<void>(
        context: context,
        useSafeArea: true,
        isScrollControlled: true,
        isDismissible: false,
        enableDrag: false,
        backgroundColor: Colors.transparent,
        builder: (_) => _RestaurantIncomingOrderSheet(
          order: order,
          durationSeconds: durationSeconds,
          onAccept: (minutes) async {
            final accept = onAccept ??
                (id, prep) async {
                  final response = await ApiService().post(
                    ApiConstants.restaurantAcceptOrder(id),
                    data: {'preparation_time_minutes': prep},
                  );
                  return response['success'] == true;
                };
            return accept(orderId, minutes);
          },
          onReject: () async {
            final reject = onReject ??
                (id, reason) async {
                  final response = await ApiService().post(
                    ApiConstants.restaurantRejectOrder(id),
                    data: {'reason': reason},
                  );
                  return response['success'] == true;
                };
            return reject(orderId, 'Rejected by restaurant');
          },
          onMarkOutOfStock: (menuItemIds, availabilityOption) async {
            if (onMarkOutOfStock == null) return false;
            return onMarkOutOfStock(
              orderId,
              menuItemIds,
              availabilityOption,
            );
          },
          onTimeout: () async {
            final timeout = onTimeout ??
                (id) async {
                  final response = await ApiService().post(
                    ApiConstants.restaurantRejectOrder(id),
                    data: {
                      'reason': 'Auto rejected: incoming order timer expired',
                    },
                  );
                  return response['success'] == true;
                };
            return timeout(orderId);
          },
          onMinimize: () {
            Navigator.of(context).pop();
            onViewDetails?.call();
          },
          onHelp: () {
            Navigator.of(context).pop();
            appNavigatorKey.currentState?.pushNamed(
              '/restaurant/profile/help',
              arguments: orderId,
            );
          },
        ),
      );
    } finally {
      await SoundService.stopIncomingOrderAlarm();
      _isShowing = false;
      _activeOrderId = null;
    }
  }

  static Future<void> showDriverOrder(
    Map<String, dynamic> order, {
    Future<bool> Function(int orderId)? onAccept,
    Future<bool> Function(int orderId, String reason)? onReject,
    Future<bool> Function(int orderId)? onTimeout,
    VoidCallback? onViewDetails,
    int durationSeconds = 30,
  }) async {
    order = _normalizeOrder(order);
    final context = appNavigatorKey.currentContext ??
        appNavigatorKey.currentState?.overlay?.context;
    final orderId = _parseId(order['id'] ?? order['order_id']);
    if (context == null || orderId == null || _isShowing) {
      debugPrint(
        'Could not show driver order overlay: context=$context orderId=$orderId isShowing=$_isShowing',
      );
      return;
    }

    await HapticFeedback.heavyImpact();
    SoundService.startIncomingOrderAlarm();
    _isShowing = true;
    _activeOrderId = orderId;
    try {
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        isDismissible: false,
        enableDrag: false,
        backgroundColor: Colors.transparent,
        builder: (_) => _DriverIncomingOrderSheet(
          order: order,
          durationSeconds: durationSeconds,
          onAccept: () async {
            final accept = onAccept ??
                (id) async {
                  final response = await ApiService().post(
                    ApiConstants.driverAcceptOrder(id),
                  );
                  return response['success'] == true;
                };
            return accept(orderId);
          },
          onReject: () async {
            final reject = onReject ??
                (id, reason) async {
                  final response = await ApiService().post(
                    ApiConstants.driverRejectOrder(id),
                    data: {'reason': reason},
                  );
                  return response['success'] == true;
                };
            return reject(orderId, 'Rejected by driver');
          },
          onTimeout: () async {
            final timeout = onTimeout ??
                (id) async {
                  final response = await ApiService().post(
                    ApiConstants.driverRejectOrder(id),
                    data: {
                      'reason': 'Auto rejected: incoming order timer expired',
                    },
                  );
                  return response['success'] == true;
                };
            return timeout(orderId);
          },
          onViewDetails: () {
            onViewDetails?.call();
            appNavigatorKey.currentState?.pushNamed(
              '/driver/order',
              arguments: orderId,
            );
          },
        ),
      );
    } finally {
      await SoundService.stopIncomingOrderAlarm();
      _isShowing = false;
      _activeOrderId = null;
    }
  }

  static Future<void> showOrderCancelled(
    Map<String, dynamic> order, {
    required String role,
    int durationSeconds = 45,
  }) async {
    order = _normalizeOrder(order);
    final orderId = _parseId(order['id'] ?? order['order_id']);
    if (orderId == null) {
      debugPrint('Could not show cancellation overlay: missing order ID');
      return;
    }

    if (_isShowing && _activeOrderId == orderId) {
      appNavigatorKey.currentState?.pop();
      for (var attempt = 0; attempt < 12 && _isShowing; attempt++) {
        await Future<void>.delayed(const Duration(milliseconds: 40));
      }
    }

    final context = appNavigatorKey.currentContext ??
        appNavigatorKey.currentState?.overlay?.context;
    if (context == null || _isShowing) {
      debugPrint(
        'Could not show cancellation overlay: context=$context orderId=$orderId isShowing=$_isShowing',
      );
      return;
    }

    await HapticFeedback.heavyImpact();
    SoundService.startIncomingOrderAlarm();
    _isShowing = true;
    _activeOrderId = orderId;
    try {
      final viewOrder = await showModalBottomSheet<bool>(
        context: context,
        isScrollControlled: true,
        isDismissible: false,
        enableDrag: false,
        backgroundColor: Colors.transparent,
        builder: (_) => _OrderCancelledSheet(
          order: order,
          durationSeconds: durationSeconds,
        ),
      );
      if (viewOrder == true) {
        await Future<void>.delayed(Duration.zero);
        appNavigatorKey.currentState?.pushNamed(
          role == 'driver' ? '/driver/order' : '/restaurant/order',
          arguments: role == 'driver'
              ? orderId
              : <String, dynamic>{
                  'orderId': orderId,
                  if (_parseId(order['restaurant_id']) != null)
                    'restaurantId': _parseId(order['restaurant_id']),
                },
        );
      }
    } finally {
      await SoundService.stopIncomingOrderAlarm();
      _isShowing = false;
      _activeOrderId = null;
    }
  }

  static int? _parseId(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value);
    return null;
  }

  static Map<String, dynamic> _normalizeOrder(Map<String, dynamic> data) {
    for (final key in const ['order', 'order_data', 'data', 'payload']) {
      final nested = data[key];
      if (nested is Map<String, dynamic>) {
        return {...data, ...nested};
      }
      if (nested is Map) {
        return {...data, ...Map<String, dynamic>.from(nested)};
      }
    }
    return data;
  }
}

class _OrderCancelledSheet extends StatefulWidget {
  const _OrderCancelledSheet({
    required this.order,
    required this.durationSeconds,
  });

  final Map<String, dynamic> order;
  final int durationSeconds;

  @override
  State<_OrderCancelledSheet> createState() => _OrderCancelledSheetState();
}

class _OrderCancelledSheetState extends State<_OrderCancelledSheet> {
  late int _remaining;
  Timer? _timer;
  bool _isClosing = false;

  @override
  void initState() {
    super.initState();
    _remaining = widget.durationSeconds.clamp(10, 120).toInt();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted || _isClosing) return;
      if (_remaining <= 1) {
        _close();
        return;
      }
      setState(() => _remaining--);
    });
  }

  void _close({bool viewOrder = false}) {
    if (_isClosing || !mounted) return;
    _isClosing = true;
    _timer?.cancel();
    Navigator.of(context).pop(viewOrder);
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final orderNumber =
        widget.order['order_number']?.toString().trim().isNotEmpty == true
            ? widget.order['order_number'].toString()
            : '${widget.order['id'] ?? widget.order['order_id'] ?? ''}';
    final reason = widget.order['cancellation_reason']?.toString().trim();
    final restaurantName = widget.order['restaurant_name']?.toString().trim();
    final customerMap = widget.order['customer'];
    final customerName = _textValue(
      widget.order['customer_name'] ??
          (customerMap is Map ? customerMap['name'] : null),
      fallback: '',
    );
    final items = _itemsFrom(
      widget.order['items'] ??
          widget.order['order_items'] ??
          widget.order['cart_items'],
    );
    final total = widget.order['total'] ??
        widget.order['grand_total'] ??
        widget.order['amount'];

    return PopScope(
      canPop: false,
      child: SafeArea(
        top: true,
        child: Material(
          color: const Color(0xFFF0F0F4),
          child: SizedBox(
            height: MediaQuery.sizeOf(context).height,
            child: Column(
              children: [
                Container(
                  constraints: const BoxConstraints(minHeight: 56),
                  padding: const EdgeInsets.fromLTRB(6, 6, 10, 6),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    border: Border(
                      bottom: BorderSide(color: FoodFlowTheme.line),
                    ),
                  ),
                  child: Row(
                    children: [
                      IconButton(
                        onPressed: _close,
                        icon: const Icon(Icons.close_rounded),
                        tooltip: 'Dismiss alert',
                        visualDensity: VisualDensity.compact,
                      ),
                      const Expanded(
                        child: Text(
                          'Order cancelled',
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 17,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      _AlertDisc(
                        seconds: _remaining,
                        color: FoodFlowTheme.crimson,
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(14, 14, 14, 18),
                    children: [
                      _IncomingOrderPanel(
                        child: Row(
                          children: [
                            Container(
                              width: 46,
                              height: 46,
                              decoration: BoxDecoration(
                                color: FoodFlowTheme.crimson,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(
                                Icons.cancel_rounded,
                                color: Colors.white,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '#$orderNumber',
                                    style: const TextStyle(
                                      color: FoodFlowTheme.ink,
                                      fontSize: 17,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                  if (restaurantName != null &&
                                      restaurantName.isNotEmpty) ...[
                                    const SizedBox(height: 3),
                                    Text(
                                      restaurantName,
                                      style: const TextStyle(
                                        color: FoodFlowTheme.muted,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 9,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                color: FoodFlowTheme.crimson.withOpacity(0.10),
                                borderRadius: BorderRadius.circular(7),
                              ),
                              child: const Text(
                                'CANCELLED',
                                style: TextStyle(
                                  color: FoodFlowTheme.crimson,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (reason != null && reason.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        _IncomingOrderPanel(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const _IncomingSectionTitle(
                                icon: Icons.info_outline_rounded,
                                title: 'CANCELLATION REASON',
                              ),
                              const SizedBox(height: 10),
                              Text(
                                reason,
                                style: const TextStyle(
                                  color: FoodFlowTheme.ink,
                                  fontSize: 15,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                      if (items.isNotEmpty || total != null) ...[
                        const SizedBox(height: 12),
                        _IncomingOrderPanel(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _IncomingSectionTitle(
                                icon: Icons.receipt_long_outlined,
                                title: 'ORDER DETAILS',
                                trailing: items.isEmpty
                                    ? null
                                    : '${items.length} ${items.length == 1 ? 'item' : 'items'}',
                              ),
                              if (items.isNotEmpty) ...[
                                const SizedBox(height: 12),
                                ...items.map((item) {
                                  final name = _textValue(
                                    item['name'] ?? item['item_name'],
                                    fallback: '',
                                  );
                                  final quantity = _intValue(
                                    item['quantity'],
                                    fallback: 1,
                                  );
                                  final lineTotal = item['total'] ??
                                      item['total_price'] ??
                                      item['subtotal'] ??
                                      item['price'];
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 10),
                                    child: Row(
                                      children: [
                                        const Icon(
                                          Icons.crop_square_rounded,
                                          color: FoodFlowTheme.success,
                                          size: 14,
                                        ),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: Text(
                                            name,
                                            style: const TextStyle(
                                              color: FoodFlowTheme.ink,
                                              fontSize: 14,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                        ),
                                        Text(
                                          'x$quantity',
                                          style: const TextStyle(
                                            color: FoodFlowTheme.ink,
                                            fontWeight: FontWeight.w900,
                                          ),
                                        ),
                                        if (lineTotal != null) ...[
                                          const SizedBox(width: 12),
                                          Text(
                                            _money(lineTotal),
                                            style: const TextStyle(
                                              color: FoodFlowTheme.inkSoft,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                        ],
                                      ],
                                    ),
                                  );
                                }),
                              ],
                              if (total != null) ...[
                                const Divider(height: 16),
                                _IncomingBillRow(
                                  'Bill Total',
                                  total,
                                  isTotal: true,
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],
                      if (customerName.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        _IncomingOrderPanel(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const _IncomingSectionTitle(
                                icon: Icons.person_outline_rounded,
                                title: 'CUSTOMER DETAILS',
                              ),
                              const SizedBox(height: 10),
                              Text(
                                customerName,
                                style: const TextStyle(
                                  color: FoodFlowTheme.ink,
                                  fontSize: 16,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                Container(
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    boxShadow: [
                      BoxShadow(
                        color: Color(0x14000000),
                        blurRadius: 12,
                        offset: Offset(0, -4),
                      ),
                    ],
                  ),
                  child: SafeArea(
                    top: false,
                    minimum: const EdgeInsets.fromLTRB(14, 8, 14, 10),
                    child: Row(
                      children: [
                        Expanded(
                          child: SizedBox(
                            height: 48,
                            child: OutlinedButton(
                              onPressed: _close,
                              style: OutlinedButton.styleFrom(
                                foregroundColor: FoodFlowTheme.ink,
                                side: const BorderSide(
                                  color: FoodFlowTheme.ink,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                textStyle: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              child: const Text('DISMISS'),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: SizedBox(
                            height: 48,
                            child: FilledButton.icon(
                              onPressed: () => _close(viewOrder: true),
                              style: FilledButton.styleFrom(
                                backgroundColor: FoodFlowTheme.crimson,
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                textStyle: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              icon: const Icon(
                                Icons.receipt_long_outlined,
                                size: 18,
                              ),
                              label: const Text('VIEW ORDER'),
                            ),
                          ),
                        ),
                      ],
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
}

class _RestaurantIncomingOrderSheet extends StatefulWidget {
  const _RestaurantIncomingOrderSheet({
    required this.order,
    required this.durationSeconds,
    required this.onAccept,
    required this.onReject,
    required this.onMarkOutOfStock,
    required this.onTimeout,
    required this.onMinimize,
    required this.onHelp,
  });

  final Map<String, dynamic> order;
  final int durationSeconds;
  final Future<bool> Function(int preparationMinutes) onAccept;
  final Future<bool> Function() onReject;
  final Future<bool> Function(
    List<int> menuItemIds,
    String availabilityOption,
  ) onMarkOutOfStock;
  final Future<bool> Function() onTimeout;
  final VoidCallback onMinimize;
  final VoidCallback onHelp;

  @override
  State<_RestaurantIncomingOrderSheet> createState() =>
      _RestaurantIncomingOrderSheetState();
}

class _RestaurantIncomingOrderSheetState
    extends State<_RestaurantIncomingOrderSheet> {
  static const int _minPrepMinutes = 5;
  static const int _maxPrepMinutes = 60;
  static const int _prepStep = 5;

  late int _minutes;
  late int _remainingSeconds;
  Timer? _timer;
  bool _isAccepting = false;
  bool _isRejecting = false;
  bool _timedOut = false;

  @override
  void initState() {
    super.initState();
    _minutes = _initialPreparationMinutes(widget.order);
    _remainingSeconds = widget.durationSeconds.clamp(10, 120).toInt();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    final items = _itemsFrom(
      order['items'] ?? order['order_items'] ?? order['cart_items'],
    );
    final orderNumber = _textValue(
      order['order_number'] ?? order['order_no'] ?? order['id'],
      fallback: 'Order',
    );
    final restaurantMap = order['restaurant'];
    final customerMap = order['customer'];
    final restaurantName = _textValue(
      order['restaurant_name'] ??
          (restaurantMap is Map ? restaurantMap['name'] : null),
      fallback: 'Restaurant',
    );
    final customerName = _textValue(
      order['customer_name'] ??
          (customerMap is Map ? customerMap['name'] : null),
      fallback: 'Customer',
    );
    final customerPhone = _textValue(
      order['customer_phone'] ??
          (customerMap is Map ? customerMap['phone'] : null),
      fallback: '',
    );
    final deliveryAddress = _textValue(
      order['delivery_address'] ?? order['address'],
      fallback: order['order_type']?.toString() == 'takeaway'
          ? 'Takeaway order'
          : 'Delivery address unavailable',
    );
    final deliveryOtp = _textValue(
      order['delivery_otp'] ?? order['otp'] ?? order['delivery_code'],
      fallback: '',
    );
    final specialInstructions = _textValue(
      order['special_instructions'] ?? order['notes'] ?? order['instruction'],
      fallback: '',
    );
    final itemCount = items.fold<int>(
      0,
      (sum, item) => sum + _intValue(item['quantity'], fallback: 1),
    );
    final subtotal =
        order['subtotal'] ?? order['sub_total'] ?? order['items_total'];
    final deliveryFee = order['delivery_fee'] ?? order['shipping_fee'];
    final tax = order['tax'] ?? order['tax_amount'] ?? order['gst_amount'];
    final discount = order['discount'] ?? order['discount_amount'];
    final total = order['total'] ?? order['grand_total'] ?? order['amount'];
    final paymentMethod = _textValue(
      order['payment_method'] ?? order['payment_type'],
      fallback: 'Payment',
    );

    return PopScope(
      canPop: false,
      child: SafeArea(
        top: true,
        child: Material(
          color: const Color(0xFFF0F0F4),
          child: SizedBox(
            height: MediaQuery.of(context).size.height,
            child: Column(
              children: [
                Container(
                  constraints: const BoxConstraints(minHeight: 56),
                  padding: const EdgeInsets.fromLTRB(6, 6, 10, 6),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    border: Border(
                      bottom: BorderSide(color: FoodFlowTheme.line),
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Color(0x0A000000),
                        blurRadius: 8,
                        offset: Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      IconButton(
                        onPressed: _isBusy ? null : widget.onMinimize,
                        icon: const Icon(Icons.arrow_back),
                        tooltip: 'Minimize order',
                        visualDensity: VisualDensity.compact,
                      ),
                      const Expanded(
                        child: Text(
                          'New order',
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 17,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      _AlertDisc(
                        seconds: _remainingSeconds,
                        color: FoodFlowTheme.orange,
                      ),
                      TextButton.icon(
                        onPressed: _isBusy ? null : widget.onHelp,
                        style: TextButton.styleFrom(
                          foregroundColor: FoodFlowTheme.success,
                          padding: const EdgeInsets.symmetric(horizontal: 8),
                          minimumSize: const Size(0, 40),
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                          textStyle: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        icon: const Icon(Icons.headset_mic_outlined, size: 17),
                        label: const Text('Help'),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(14, 14, 14, 18),
                    children: [
                      _IncomingOrderPanel(
                        child: Row(
                          children: [
                            Container(
                              width: 46,
                              height: 46,
                              decoration: BoxDecoration(
                                color: FoodFlowTheme.orange,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(
                                Icons.receipt_long_rounded,
                                color: Colors.white,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '#$orderNumber',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: FoodFlowTheme.ink,
                                      fontSize: 18,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    restaurantName,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: FoodFlowTheme.muted,
                                      fontSize: 12,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                color: FoodFlowTheme.success.withOpacity(.12),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: const Text(
                                'NEW',
                                style: TextStyle(
                                  color: FoodFlowTheme.success,
                                  fontWeight: FontWeight.w900,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      _IncomingOrderPanel(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _IncomingSectionTitle(
                              icon: Icons.fact_check_outlined,
                              title: 'ORDER DETAILS',
                              trailing:
                                  '$itemCount ${itemCount == 1 ? 'item' : 'items'}',
                            ),
                            const SizedBox(height: 12),
                            if (items.isEmpty)
                              const Text(
                                'Item details unavailable for this order.',
                                style: TextStyle(
                                  color: FoodFlowTheme.muted,
                                  fontWeight: FontWeight.w700,
                                ),
                              )
                            else
                              ...items.map((item) {
                                final quantity = _intValue(
                                  item['quantity'],
                                  fallback: 1,
                                );
                                final name = _textValue(
                                  item['name'] ??
                                      item['item_name'] ??
                                      item['menu_name'],
                                  fallback: 'Item',
                                );
                                final lineTotal = item['total'] ??
                                    item['total_price'] ??
                                    item['subtotal'] ??
                                    item['price'];
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      const Padding(
                                        padding: EdgeInsets.only(top: 3),
                                        child: Icon(
                                          Icons.crop_square_rounded,
                                          color: FoodFlowTheme.success,
                                          size: 14,
                                        ),
                                      ),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          name,
                                          style: const TextStyle(
                                            color: FoodFlowTheme.ink,
                                            fontSize: 15,
                                            fontWeight: FontWeight.w900,
                                          ),
                                        ),
                                      ),
                                      Text(
                                        'x$quantity',
                                        style: const TextStyle(
                                          color: FoodFlowTheme.ink,
                                          fontWeight: FontWeight.w900,
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Text(
                                        _money(lineTotal),
                                        style: const TextStyle(
                                          color: FoodFlowTheme.inkSoft,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }),
                            if (specialInstructions.isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF8F8FA),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: FoodFlowTheme.line),
                                ),
                                child: Text(
                                  specialInstructions,
                                  style: const TextStyle(
                                    color: FoodFlowTheme.inkSoft,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ],
                            const SizedBox(height: 12),
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF6F6F7),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Column(
                                children: [
                                  _IncomingBillRow('Item total', subtotal),
                                  _IncomingBillRow(
                                      'Delivery charges', deliveryFee),
                                  _IncomingBillRow('Taxes', tax),
                                  _IncomingBillRow('Discount', discount,
                                      isDeduction: true),
                                  const Divider(height: 16),
                                  _IncomingBillRow('Bill Total', total,
                                      isTotal: true),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      _IncomingOrderPanel(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const _IncomingSectionTitle(
                              icon: Icons.person_outline,
                              title: 'CUSTOMER DETAILS',
                            ),
                            const SizedBox(height: 10),
                            Text(
                              customerName,
                              style: const TextStyle(
                                color: FoodFlowTheme.ink,
                                fontSize: 17,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            if (customerPhone.isNotEmpty) ...[
                              const Divider(height: 22),
                              Row(
                                children: [
                                  Icon(Icons.call,
                                      color: FoodFlowTheme.orange, size: 18),
                                  const SizedBox(width: 8),
                                  Text(
                                    customerPhone,
                                    style: TextStyle(
                                      color: FoodFlowTheme.orange,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      _IncomingOrderPanel(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _IncomingSectionTitle(
                              icon: Icons.delivery_dining_outlined,
                              title: 'DELIVERY DETAILS',
                              trailing: deliveryOtp.isEmpty
                                  ? null
                                  : 'Passcode $deliveryOtp',
                            ),
                            const SizedBox(height: 12),
                            Text(
                              deliveryAddress,
                              style: const TextStyle(
                                color: FoodFlowTheme.inkSoft,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              paymentMethod,
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
                Container(
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    boxShadow: [
                      BoxShadow(
                        color: Color(0x14000000),
                        blurRadius: 12,
                        offset: Offset(0, -4),
                      ),
                    ],
                  ),
                  child: SafeArea(
                    top: false,
                    minimum: const EdgeInsets.fromLTRB(14, 8, 14, 10),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: const Color(0xFFDFF8EC),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Row(
                            children: [
                              _PrepTimeButton(
                                icon: Icons.remove_rounded,
                                enabled: !_isBusy && _minutes > _minPrepMinutes,
                                onTap: () => _changeMinutes(-_prepStep),
                              ),
                              Expanded(
                                child: Column(
                                  children: [
                                    Text(
                                      '$_minutes',
                                      style: const TextStyle(
                                        color: FoodFlowTheme.success,
                                        fontSize: 28,
                                        height: 1,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    const Text(
                                      'Suggested Prep Time',
                                      style: TextStyle(
                                        color: FoodFlowTheme.ink,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              _PrepTimeButton(
                                icon: Icons.add_rounded,
                                enabled: !_isBusy && _minutes < _maxPrepMinutes,
                                onTap: () => _changeMinutes(_prepStep),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Expanded(
                              child: SizedBox(
                                height: 48,
                                child: OutlinedButton.icon(
                                  onPressed: _isBusy ? null : _markOutOfStock,
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: FoodFlowTheme.ink,
                                    side: const BorderSide(
                                      color: FoodFlowTheme.ink,
                                    ),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    textStyle: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                  icon: _isRejecting
                                      ? const SizedBox(
                                          width: 17,
                                          height: 17,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                          ),
                                        )
                                      : const Icon(
                                          Icons.inventory_2_outlined,
                                          size: 17,
                                        ),
                                  label: const Text('OUT OF STOCK'),
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: SizedBox(
                                height: 48,
                                child: FilledButton.icon(
                                  onPressed: _isBusy ? null : _accept,
                                  style: FilledButton.styleFrom(
                                    backgroundColor: FoodFlowTheme.success,
                                    foregroundColor: Colors.white,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    textStyle: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                  icon: _isAccepting
                                      ? const SizedBox(
                                          width: 17,
                                          height: 17,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            color: Colors.white,
                                          ),
                                        )
                                      : const Icon(
                                          Icons.check_rounded,
                                          size: 19,
                                        ),
                                  label: const Text('CONFIRM NOW'),
                                ),
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
          ),
        ),
      ),
    );
  }

  bool get _isBusy => _isAccepting || _isRejecting;

  static int _initialPreparationMinutes(Map<String, dynamic> order) {
    final raw = order['preparation_time_minutes'] ??
        order['preparation_minutes'] ??
        order['order_lead_time'] ??
        order['estimated_preparation_minutes'];
    final parsed = raw is int
        ? raw
        : raw is num
            ? raw.toInt()
            : int.tryParse(raw?.toString() ?? '');
    final minutes = parsed ?? 20;
    return ((minutes / _prepStep).round() * _prepStep)
        .clamp(_minPrepMinutes, _maxPrepMinutes)
        .toInt();
  }

  void _changeMinutes(int delta) {
    if (_isBusy) return;
    setState(() {
      _minutes =
          (_minutes + delta).clamp(_minPrepMinutes, _maxPrepMinutes).toInt();
    });
  }

  Future<void> _tick() async {
    if (!mounted || _isBusy || _timedOut) return;
    if (_remainingSeconds <= 1) {
      setState(() {
        _remainingSeconds = 0;
        _timedOut = true;
        _isRejecting = true;
      });
      final ok = await widget.onTimeout();
      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      _timer?.cancel();
      Navigator.pop(context);
      messenger.showSnackBar(
        SnackBar(
          content: Text(ok ? 'Order auto rejected' : 'Order timer expired'),
        ),
      );
      return;
    }
    setState(() => _remainingSeconds--);
  }

  Future<void> _accept() async {
    _timer?.cancel();
    setState(() => _isAccepting = true);
    final ok = await widget.onAccept(_minutes);
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _isAccepting = false);
    if (ok) {
      Navigator.pop(context);
      messenger.showSnackBar(
        SnackBar(content: Text('Order accepted: $_minutes min')),
      );
    } else {
      Navigator.pop(context);
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Order is no longer available or was cancelled'),
        ),
      );
    }
  }

  void _startTimer() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  Future<void> _markOutOfStock() async {
    _timer?.cancel();
    final orderItems = _itemsFrom(
      widget.order['items'] ??
          widget.order['order_items'] ??
          widget.order['cart_items'],
    )
        .where((item) =>
            AppOrderOverlayService._parseId(
              item['menu_item_id'],
            ) !=
            null)
        .toList(growable: false);

    if (orderItems.isEmpty) {
      _startTimer();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Menu item details are unavailable for this order'),
        ),
      );
      return;
    }

    final selection = await showModalBottomSheet<_OutOfStockSelection>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _OutOfStockPicker(items: orderItems),
    );
    if (!mounted) return;
    if (selection == null) {
      _startTimer();
      return;
    }

    setState(() => _isRejecting = true);
    final ok = await widget.onMarkOutOfStock(
      selection.menuItemIds,
      selection.availabilityOption,
    );
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _isRejecting = false);
    if (ok) {
      Navigator.pop(context);
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Items marked out of stock and order rejected'),
        ),
      );
    } else {
      _startTimer();
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Could not update item availability. Please retry.'),
        ),
      );
    }
  }

  Future<void> _reject(String reason) async {
    _timer?.cancel();
    setState(() => _isRejecting = true);
    final ok = await widget.onReject();
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _isRejecting = false);
    if (ok) {
      Navigator.pop(context);
      messenger.showSnackBar(
        const SnackBar(content: Text('Order rejected')),
      );
    } else {
      Navigator.pop(context);
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Order is no longer available or was cancelled'),
        ),
      );
    }
  }
}

class _OutOfStockSelection {
  const _OutOfStockSelection({
    required this.menuItemIds,
    required this.availabilityOption,
  });

  final List<int> menuItemIds;
  final String availabilityOption;
}

class _OutOfStockPicker extends StatefulWidget {
  const _OutOfStockPicker({required this.items});

  final List<Map<String, dynamic>> items;

  @override
  State<_OutOfStockPicker> createState() => _OutOfStockPickerState();
}

class _OutOfStockPickerState extends State<_OutOfStockPicker> {
  final Set<int> _selectedIds = <int>{};
  String _availabilityOption = '30_minutes';

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * .78,
        ),
        padding: const EdgeInsets.fromLTRB(18, 14, 18, 16),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Mark item out of stock',
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                  tooltip: 'Close',
                ),
              ],
            ),
            const Text(
              'Select the unavailable item',
              style: TextStyle(
                color: FoodFlowTheme.muted,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Flexible(child: _buildItemList()),
            const Divider(height: 24),
            const Text(
              'When will it be available?',
              style: TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 14,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 10),
            _buildAvailabilityOptions(),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: FilledButton(
                onPressed: _selectedIds.isEmpty ? null : _confirm,
                style: FilledButton.styleFrom(
                  backgroundColor: FoodFlowTheme.success,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                  textStyle: const TextStyle(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                child: const Text('MARK OUT OF STOCK'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildItemList() {
    return SingleChildScrollView(
      child: Column(
        children: widget.items.map((item) {
          final id = AppOrderOverlayService._parseId(item['menu_item_id'])!;
          final name = _textValue(
            item['item_name'] ?? item['name'] ?? item['menu_name'],
            fallback: 'Menu item',
          );
          return CheckboxListTile(
            value: _selectedIds.contains(id),
            onChanged: (selected) => setState(() {
              if (selected == true) {
                _selectedIds.add(id);
              } else {
                _selectedIds.remove(id);
              }
            }),
            dense: true,
            contentPadding: EdgeInsets.zero,
            activeColor: FoodFlowTheme.success,
            controlAffinity: ListTileControlAffinity.leading,
            title: Text(
              name,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontWeight: FontWeight.w900,
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildAvailabilityOptions() {
    const options = <(String, String)>[
      ('30_minutes', 'In 30 min'),
      ('2_hours', 'In 2 hours'),
      ('tomorrow', 'Tomorrow'),
      ('manual', 'I will mark it myself'),
    ];
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: options.map((option) {
        final selected = _availabilityOption == option.$1;
        return ChoiceChip(
          label: Text(option.$2),
          selected: selected,
          onSelected: (_) => setState(() {
            _availabilityOption = option.$1;
          }),
          selectedColor: FoodFlowTheme.success.withOpacity(.14),
          labelStyle: TextStyle(
            color: selected ? FoodFlowTheme.success : FoodFlowTheme.inkSoft,
            fontWeight: FontWeight.w800,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        );
      }).toList(),
    );
  }

  void _confirm() {
    Navigator.pop(
      context,
      _OutOfStockSelection(
        menuItemIds: _selectedIds.toList(growable: false),
        availabilityOption: _availabilityOption,
      ),
    );
  }
}

class _IncomingOrderPanel extends StatelessWidget {
  const _IncomingOrderPanel({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: child,
    );
  }
}

class _IncomingSectionTitle extends StatelessWidget {
  const _IncomingSectionTitle({
    required this.icon,
    required this.title,
    this.trailing,
  });

  final IconData icon;
  final String title;
  final String? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: const Color(0xFFF1F1F3),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, size: 17, color: FoodFlowTheme.muted),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              fontWeight: FontWeight.w900,
              letterSpacing: 2,
            ),
          ),
        ),
        if (trailing != null)
          Text(
            trailing!,
            style: const TextStyle(
              color: FoodFlowTheme.ink,
              fontWeight: FontWeight.w900,
            ),
          ),
      ],
    );
  }
}

class _IncomingBillRow extends StatelessWidget {
  const _IncomingBillRow(
    this.label,
    this.value, {
    this.isTotal = false,
    this.isDeduction = false,
  });

  final String label;
  final dynamic value;
  final bool isTotal;
  final bool isDeduction;

  @override
  Widget build(BuildContext context) {
    final hasValue = value != null && value.toString().trim().isNotEmpty;
    if (!hasValue && !isTotal) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                color: isTotal ? FoodFlowTheme.ink : FoodFlowTheme.inkSoft,
                fontSize: isTotal ? 13 : 12,
                fontWeight: isTotal ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
          ),
          Text(
            isDeduction && hasValue ? '-${_money(value)}' : _money(value),
            style: TextStyle(
              color: isTotal ? FoodFlowTheme.ink : FoodFlowTheme.inkSoft,
              fontSize: isTotal ? 13 : 12,
              fontWeight: isTotal ? FontWeight.w900 : FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

int _intValue(dynamic value, {required int fallback}) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

class _IncomingMapBackdrop extends StatelessWidget {
  const _IncomingMapBackdrop();

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(30),
      child: Stack(
        children: [
          Positioned.fill(
            child: CustomPaint(
              painter: _SoftMapPainter(FoodFlowTheme.orange),
            ),
          ),
          Positioned.fill(
            child: Container(
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.74),
                borderRadius: BorderRadius.circular(30),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SoftMapPainter extends CustomPainter {
  const _SoftMapPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final roadPaint = Paint()
      ..color = const Color(0xFFE7EAF2)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 7
      ..strokeCap = StrokeCap.round;

    final accentPaint = Paint()
      ..color = color.withOpacity(0.20)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 5
      ..strokeCap = StrokeCap.round;

    for (var i = 0; i < 5; i++) {
      final y = size.height * (0.16 + i * 0.17);
      canvas.drawLine(
          Offset(-20, y), Offset(size.width + 20, y + 36), roadPaint);
    }
    for (var i = 0; i < 4; i++) {
      final x = size.width * (0.14 + i * 0.22);
      canvas.drawLine(
          Offset(x, -20), Offset(x + 40, size.height + 20), roadPaint);
    }

    final path = Path()
      ..moveTo(size.width * 0.18, size.height * 0.72)
      ..quadraticBezierTo(
        size.width * 0.42,
        size.height * 0.52,
        size.width * 0.58,
        size.height * 0.64,
      )
      ..quadraticBezierTo(
        size.width * 0.78,
        size.height * 0.80,
        size.width * 0.90,
        size.height * 0.42,
      );
    canvas.drawPath(path, accentPaint);

    final pinPaint = Paint()..color = const Color(0xFF0F9F9A).withOpacity(0.26);
    canvas.drawCircle(
        Offset(size.width * 0.86, size.height * 0.42), 30, pinPaint);
    canvas.drawCircle(
      Offset(size.width * 0.24, size.height * 0.72),
      24,
      Paint()..color = color.withOpacity(0.22),
    );
  }

  @override
  bool shouldRepaint(covariant _SoftMapPainter oldDelegate) {
    return oldDelegate.color != color;
  }
}

class _NotificationLabel extends StatelessWidget {
  const _NotificationLabel({
    required this.icon,
    required this.text,
    required this.color,
  });

  final IconData icon;
  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: color.withOpacity(0.10),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(width: 8),
          Text(
            text,
            style: TextStyle(
              color: color,
              fontSize: 15,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _AlertDisc extends StatelessWidget {
  const _AlertDisc({
    required this.seconds,
    required this.color,
  });

  final int seconds;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final danger = seconds <= 10;
    final discColor = danger ? FoodFlowTheme.danger : color;
    return Container(
      height: 34,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: discColor.withOpacity(0.11),
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: discColor.withOpacity(0.28)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.timer_outlined, color: discColor, size: 15),
          const SizedBox(width: 4),
          Text(
            '${seconds}s',
            style: TextStyle(
              color: discColor,
              fontWeight: FontWeight.w900,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroAsset extends StatelessWidget {
  const _HeroAsset({
    required this.assetPath,
    required this.size,
    required this.fallbackIcon,
  });

  final String assetPath;
  final double size;
  final IconData fallbackIcon;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Image.asset(
        assetPath,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => Container(
          decoration: BoxDecoration(
            color: FoodFlowTheme.orange.withOpacity(0.10),
            shape: BoxShape.circle,
          ),
          child: Icon(fallbackIcon, color: FoodFlowTheme.orange, size: 54),
        ),
      ),
    );
  }
}

class _OrderSummary3d extends StatelessWidget {
  const _OrderSummary3d({
    required this.leadingIcon,
    required this.orderNumber,
    required this.title,
    required this.subtitle,
    required this.amount,
    required this.amountLabel,
  });

  final IconData leadingIcon;
  final String orderNumber;
  final String title;
  final String subtitle;
  final String amount;
  final String amountLabel;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: _raisedDecoration(),
      child: Row(
        children: [
          Container(
            width: 60,
            height: 60,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [
                  FoodFlowTheme.orange.withOpacity(0.16),
                  const Color(0xFF0F9F9A).withOpacity(0.12),
                ],
              ),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Icon(leadingIcon, color: FoodFlowTheme.orange, size: 30),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Order $orderNumber',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 122),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  amount,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF0F9F9A),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                  decoration: BoxDecoration(
                    color: FoodFlowTheme.orange.withOpacity(0.09),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    amountLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: FoodFlowTheme.orange,
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
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
}

class _AddressPreview3d extends StatelessWidget {
  const _AddressPreview3d({
    required this.icon,
    required this.label,
    required this.value,
    required this.pickupLocation,
    required this.deliveryLocation,
  });

  final IconData icon;
  final String label;
  final String value;
  final LatLng? pickupLocation;
  final LatLng? deliveryLocation;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: _raisedDecoration(color: const Color(0xFFFCFBFF)),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: FoodFlowTheme.orange,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: FoodFlowTheme.orange.withOpacity(0.24),
                  blurRadius: 14,
                  offset: const Offset(0, 7),
                ),
              ],
            ),
            child: Icon(icon, color: Colors.white),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    color: FoodFlowTheme.orange,
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 14,
                    height: 1.25,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          SizedBox(
            width: 112,
            height: 64,
            child: _RouteMapPreview(
              pickupLocation: pickupLocation,
              deliveryLocation: deliveryLocation,
            ),
          ),
        ],
      ),
    );
  }
}

class _RouteMapPreview extends StatelessWidget {
  const _RouteMapPreview({
    required this.pickupLocation,
    required this.deliveryLocation,
  });

  final LatLng? pickupLocation;
  final LatLng? deliveryLocation;

  @override
  Widget build(BuildContext context) {
    final center = _mapCenter(pickupLocation, deliveryLocation);
    if (center == null) {
      return _MapUnavailablePreview(color: FoodFlowTheme.orange);
    }

    final markers = <Marker>{
      if (pickupLocation != null)
        Marker(
          markerId: const MarkerId('pickup'),
          position: pickupLocation!,
          icon: BitmapDescriptor.defaultMarkerWithHue(
            BitmapDescriptor.hueViolet,
          ),
        ),
      if (deliveryLocation != null)
        Marker(
          markerId: const MarkerId('delivery'),
          position: deliveryLocation!,
          icon: BitmapDescriptor.defaultMarkerWithHue(
            BitmapDescriptor.hueGreen,
          ),
        ),
    };

    final polylines = <Polyline>{
      if (pickupLocation != null && deliveryLocation != null)
        Polyline(
          polylineId: const PolylineId('route'),
          points: [pickupLocation!, deliveryLocation!],
          color: FoodFlowTheme.orange,
          width: 3,
        ),
    };

    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: GoogleMap(
        initialCameraPosition: CameraPosition(
          target: center,
          zoom: pickupLocation != null && deliveryLocation != null ? 12 : 15,
        ),
        markers: markers,
        polylines: polylines,
        liteModeEnabled: true,
        zoomControlsEnabled: false,
        myLocationButtonEnabled: false,
        mapToolbarEnabled: false,
        compassEnabled: false,
        rotateGesturesEnabled: false,
        scrollGesturesEnabled: false,
        tiltGesturesEnabled: false,
        zoomGesturesEnabled: false,
      ),
    );
  }
}

class _MapUnavailablePreview extends StatelessWidget {
  const _MapUnavailablePreview({required this.color});
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF2F4FA),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Icon(
        Icons.map_outlined,
        color: color,
        size: 26,
      ),
    );
  }
}

class _ItemsPreview3d extends StatelessWidget {
  const _ItemsPreview3d({required this.items});

  final List<Map<String, dynamic>> items;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 6),
      decoration: _raisedDecoration(),
      child: Column(
        children: items
            .take(4)
            .map(
              (item) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(
                  children: [
                    Text(
                      '${item['quantity'] ?? 1}x',
                      style: TextStyle(
                        color: FoodFlowTheme.orange,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _textValue(
                          item['name'] ?? item['item_name'],
                          fallback: 'Item',
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            )
            .toList(),
      ),
    );
  }
}

class _PrepSelector3d extends StatelessWidget {
  const _PrepSelector3d({
    required this.minutes,
    required this.canDecrease,
    required this.canIncrease,
    required this.onDecrease,
    required this.onIncrease,
  });

  final int minutes;
  final bool canDecrease;
  final bool canIncrease;
  final VoidCallback onDecrease;
  final VoidCallback onIncrease;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: _raisedDecoration(color: const Color(0xFFFFFAF4)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.timer_outlined, color: FoodFlowTheme.orange, size: 18),
              const SizedBox(width: 8),
              const Text(
                'Preparation time',
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _PrepTimeButton(
                icon: Icons.remove_rounded,
                enabled: canDecrease,
                onTap: onDecrease,
              ),
              Expanded(
                child: Column(
                  children: [
                    Text(
                      '$minutes',
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 38,
                        height: 1,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    const Text(
                      'minutes',
                      style: TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              _PrepTimeButton(
                icon: Icons.add_rounded,
                enabled: canIncrease,
                onTap: onIncrease,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

BoxDecoration _raisedDecoration({Color color = Colors.white}) {
  return BoxDecoration(
    color: color,
    borderRadius: BorderRadius.circular(22),
    border: Border.all(color: FoodFlowTheme.line.withOpacity(0.86)),
    boxShadow: const [
      BoxShadow(
        color: Color(0x14000000),
        blurRadius: 18,
        offset: Offset(0, 8),
      ),
    ],
  );
}

class _PrepTimeButton extends StatelessWidget {
  const _PrepTimeButton({
    required this.icon,
    required this.enabled,
    required this.onTap,
  });

  final IconData icon;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(14),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: enabled ? Colors.white : FoodFlowTheme.line.withOpacity(0.5),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: enabled
                ? FoodFlowTheme.orange.withOpacity(0.30)
                : FoodFlowTheme.line,
          ),
        ),
        child: Icon(
          icon,
          color: enabled ? FoodFlowTheme.orange : FoodFlowTheme.faint,
        ),
      ),
    );
  }
}

class _DriverIncomingOrderSheet extends StatefulWidget {
  const _DriverIncomingOrderSheet({
    required this.order,
    required this.durationSeconds,
    required this.onAccept,
    required this.onReject,
    required this.onTimeout,
    required this.onViewDetails,
  });

  final Map<String, dynamic> order;
  final int durationSeconds;
  final Future<bool> Function() onAccept;
  final Future<bool> Function() onReject;
  final Future<bool> Function() onTimeout;
  final VoidCallback onViewDetails;

  @override
  State<_DriverIncomingOrderSheet> createState() =>
      _DriverIncomingOrderSheetState();
}

class _DriverIncomingOrderSheetState extends State<_DriverIncomingOrderSheet> {
  late int _remainingSeconds;
  Timer? _timer;
  bool _isAccepting = false;
  bool _isRejecting = false;
  bool _timedOut = false;

  @override
  void initState() {
    super.initState();
    _remainingSeconds = widget.durationSeconds;
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    final orderNumber = order['order_number'] ?? order['id'] ?? '';
    return PopScope(
      canPop: false,
      child: SafeArea(
        child: Container(
          margin: const EdgeInsets.all(10),
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFFFFFFFF), Color(0xFFFFF7F0)],
            ),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: Color(0xFFFFD7B8)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x33000000),
                blurRadius: 24,
                offset: Offset(0, 12),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 48,
                  height: 5,
                  decoration: BoxDecoration(
                    color: FoodFlowTheme.line,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAF7EF),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.delivery_dining,
                      color: Colors.green,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'New delivery',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        Text(
                          'Order #$orderNumber',
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  _CountdownPill(seconds: _remainingSeconds),
                ],
              ),
              const SizedBox(height: 14),
              _infoLine(
                  Icons.storefront, order['restaurant_name'] ?? 'Restaurant'),
              const SizedBox(height: 8),
              _infoLine(
                Icons.location_on,
                order['delivery_address'] ?? 'Delivery address',
              ),
              if (order['distance'] != null || order['earnings'] != null) ...[
                const SizedBox(height: 10),
                Row(
                  children: [
                    if (order['distance'] != null)
                      Expanded(
                        child: _metricChip(
                          Icons.social_distance,
                          '${order['distance']} km',
                        ),
                      ),
                    if (order['distance'] != null && order['earnings'] != null)
                      const SizedBox(width: 8),
                    if (order['earnings'] != null)
                      Expanded(
                        child: _metricChip(
                          Icons.currency_rupee,
                          _money(order['earnings']),
                        ),
                      ),
                  ],
                ),
              ],
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _isBusy ? null : _reject,
                      icon: _isRejecting
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.close),
                      label: const Text('Reject'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: _isBusy ? null : _accept,
                      icon: _isAccepting
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.check_circle),
                      label: const Text('Accept'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  bool get _isBusy => _isAccepting || _isRejecting;

  Future<void> _tick() async {
    if (!mounted || _isBusy || _timedOut) return;
    if (_remainingSeconds <= 1) {
      setState(() {
        _remainingSeconds = 0;
        _timedOut = true;
        _isRejecting = true;
      });
      final ok = await widget.onTimeout();
      if (!mounted) return;
      final messenger = ScaffoldMessenger.of(context);
      _timer?.cancel();
      Navigator.pop(context);
      messenger.showSnackBar(
        SnackBar(
          content:
              Text(ok ? 'Delivery auto rejected' : 'Delivery timer expired'),
        ),
      );
      return;
    }
    setState(() => _remainingSeconds--);
  }

  Future<void> _accept() async {
    _timer?.cancel();
    setState(() => _isAccepting = true);
    final ok = await widget.onAccept();
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _isAccepting = false);
    if (ok) {
      Navigator.pop(context);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        widget.onViewDetails();
      });
      messenger.showSnackBar(
        const SnackBar(content: Text('Delivery accepted')),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not accept delivery')),
      );
    }
  }

  Future<void> _reject() async {
    _timer?.cancel();
    setState(() => _isRejecting = true);
    final ok = await widget.onReject();
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _isRejecting = false);
    if (ok) {
      Navigator.pop(context);
      messenger.showSnackBar(
        const SnackBar(content: Text('Delivery rejected')),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not reject delivery')),
      );
    }
  }

  Widget _infoLine(IconData icon, dynamic value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: FoodFlowTheme.orange),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            value.toString(),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    );
  }

  Widget _metricChip(IconData icon, String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F7F8),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: FoodFlowTheme.orange),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
        ],
      ),
    );
  }
}

class _CountdownPill extends StatelessWidget {
  const _CountdownPill({required this.seconds});

  final int seconds;

  @override
  Widget build(BuildContext context) {
    final danger = seconds <= 10;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 250),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: danger ? const Color(0xFFFFECEC) : const Color(0xFFFFF3E8),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: danger ? Colors.red.shade200 : const Color(0xFFFFD7B8),
        ),
      ),
      child: Text(
        '${seconds}s',
        style: TextStyle(
          color: danger ? Colors.red.shade700 : FoodFlowTheme.orange,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

List<Map<String, dynamic>> _itemsFrom(dynamic raw) {
  if (raw is List) {
    return raw
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }
  return const [];
}

String _money(dynamic value) {
  final amount = value is num ? value : num.tryParse(value?.toString() ?? '');
  if (amount == null) {
    final text = value?.toString().trim();
    return text == null || text.isEmpty ? 'Total unavailable' : text;
  }
  final context = appNavigatorKey.currentContext ??
      appNavigatorKey.currentState?.overlay?.context;
  if (context != null) {
    return formatCurrency(context, amount);
  }
  return formatGlobalCurrency(amount);
}

LatLng? _pickupLocationFromOrder(Map<String, dynamic> order) {
  return _latLngFromValues(
    order['pickup_lat'] ??
        order['restaurant_lat'] ??
        order['restaurant_latitude'] ??
        order['latitude'],
    order['pickup_lng'] ??
        order['restaurant_lng'] ??
        order['restaurant_longitude'] ??
        order['longitude'],
  );
}

LatLng? _deliveryLocationFromOrder(Map<String, dynamic> order) {
  return _latLngFromValues(
    order['delivery_lat'] ??
        order['customer_lat'] ??
        order['customer_latitude'],
    order['delivery_lng'] ??
        order['customer_lng'] ??
        order['customer_longitude'],
  );
}

LatLng? _latLngFromValues(dynamic latValue, dynamic lngValue) {
  final lat = _doubleOrNull(latValue);
  final lng = _doubleOrNull(lngValue);
  if (lat == null || lng == null) return null;
  if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
  return LatLng(lat, lng);
}

LatLng? _mapCenter(LatLng? pickupLocation, LatLng? deliveryLocation) {
  if (pickupLocation != null && deliveryLocation != null) {
    return LatLng(
      (pickupLocation.latitude + deliveryLocation.latitude) / 2,
      (pickupLocation.longitude + deliveryLocation.longitude) / 2,
    );
  }
  return pickupLocation ?? deliveryLocation;
}

double? _doubleOrNull(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '');
}

String _textValue(dynamic value, {required String fallback}) {
  final text = value?.toString().trim();
  return text == null || text.isEmpty ? fallback : text;
}
