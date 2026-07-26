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

  static Future<void> showRestaurantOrder(
    Map<String, dynamic> order, {
    Future<bool> Function(int orderId, int preparationMinutes)? onAccept,
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
        'Could not show restaurant order overlay: context=$context orderId=$orderId isShowing=$_isShowing',
      );
      return;
    }

    await HapticFeedback.heavyImpact();
    SoundService.startIncomingOrderAlarm();
    _isShowing = true;
    try {
      await showModalBottomSheet<void>(
        context: context,
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
          onViewDetails: () {
            onViewDetails?.call();
            appNavigatorKey.currentState?.pushNamed(
              '/restaurant/order',
              arguments: orderId,
            );
          },
        ),
      );
    } finally {
      await SoundService.stopIncomingOrderAlarm();
      _isShowing = false;
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
    }
  }

  static Future<void> showOrderCancelled(
    Map<String, dynamic> order, {
    required String role,
    int durationSeconds = 45,
  }) async {
    order = _normalizeOrder(order);
    final context = appNavigatorKey.currentContext ??
        appNavigatorKey.currentState?.overlay?.context;
    final orderId = _parseId(order['id'] ?? order['order_id']);
    if (context == null || orderId == null || _isShowing) {
      debugPrint(
        'Could not show cancellation overlay: context=$context orderId=$orderId isShowing=$_isShowing',
      );
      return;
    }

    await HapticFeedback.heavyImpact();
    SoundService.startIncomingOrderAlarm();
    _isShowing = true;
    try {
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        isDismissible: true,
        enableDrag: true,
        backgroundColor: Colors.transparent,
        builder: (_) => _OrderCancelledSheet(
          order: order,
          role: role,
          durationSeconds: durationSeconds,
          onViewDetails: () {
            appNavigatorKey.currentState?.pushNamed(
              role == 'driver' ? '/driver/order' : '/restaurant/order',
              arguments: orderId,
            );
          },
        ),
      );
    } finally {
      await SoundService.stopIncomingOrderAlarm();
      _isShowing = false;
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
    required this.role,
    required this.durationSeconds,
    required this.onViewDetails,
  });

  final Map<String, dynamic> order;
  final String role;
  final int durationSeconds;
  final VoidCallback onViewDetails;

  @override
  State<_OrderCancelledSheet> createState() => _OrderCancelledSheetState();
}

class _OrderCancelledSheetState extends State<_OrderCancelledSheet> {
  late int _remaining = widget.durationSeconds.clamp(10, 120);
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      if (_remaining <= 1) {
        timer.cancel();
        Navigator.of(context).maybePop();
        return;
      }
      setState(() => _remaining--);
    });
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

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
        child: Container(
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(28),
            border: Border.all(color: FoodFlowTheme.crimson.withOpacity(0.18)),
            boxShadow: [
              BoxShadow(
                color: FoodFlowTheme.crimson.withOpacity(0.22),
                blurRadius: 34,
                offset: const Offset(0, 18),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 58,
                    height: 58,
                    decoration: BoxDecoration(
                      color: FoodFlowTheme.crimson.withOpacity(0.12),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.cancel_rounded,
                      color: FoodFlowTheme.crimson,
                      size: 34,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Order cancelled',
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Order #$orderNumber needs attention',
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                    decoration: BoxDecoration(
                      color: FoodFlowTheme.crimson.withOpacity(0.10),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '${_remaining}s',
                      style: const TextStyle(
                        color: FoodFlowTheme.crimson,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (restaurantName != null && restaurantName.isNotEmpty)
                _CancelInfoRow(
                  icon: Icons.storefront_rounded,
                  label: 'Restaurant',
                  value: restaurantName,
                ),
              if (reason != null && reason.isNotEmpty)
                _CancelInfoRow(
                  icon: Icons.info_rounded,
                  label: 'Reason',
                  value: reason,
                ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(context).maybePop(),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: FoodFlowTheme.crimson,
                        side: BorderSide(
                          color: FoodFlowTheme.crimson.withOpacity(0.28),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: const Text(
                        'Dismiss',
                        style: TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton(
                      onPressed: () {
                        Navigator.of(context).maybePop();
                        widget.onViewDetails();
                      },
                      style: FilledButton.styleFrom(
                        backgroundColor: FoodFlowTheme.crimson,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: const Text(
                        'View order',
                        style: TextStyle(fontWeight: FontWeight.w900),
                      ),
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
}

class _CancelInfoRow extends StatelessWidget {
  const _CancelInfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Icon(icon, color: FoodFlowTheme.crimson, size: 18),
          const SizedBox(width: 9),
          Text(
            '$label: ',
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
          Expanded(
            child: Text(
              value,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 13,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
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
    required this.onTimeout,
    required this.onViewDetails,
  });

  final Map<String, dynamic> order;
  final int durationSeconds;
  final Future<bool> Function(int preparationMinutes) onAccept;
  final Future<bool> Function() onReject;
  final Future<bool> Function() onTimeout;
  final VoidCallback onViewDetails;

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
    final items = _itemsFrom(order['items']);
    final total = _money(order['total']);
    final orderNumber = order['order_number'] ?? order['id'] ?? '';
    final customerName =
        order['customer_name']?.toString().trim().isNotEmpty == true
            ? order['customer_name'].toString().trim()
            : 'Customer';
    final deliveryAddress =
        _textValue(order['delivery_address'], fallback: 'Delivery address');
    final pickupLocation = _pickupLocationFromOrder(order);
    final deliveryLocation = _deliveryLocationFromOrder(order);

    return PopScope(
      canPop: false,
      child: SafeArea(
        child: Container(
          margin: const EdgeInsets.all(12),
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.92,
          ),
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              const Positioned.fill(child: _IncomingMapBackdrop()),
              Container(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.96),
                  borderRadius: BorderRadius.circular(28),
                  border:
                      Border.all(color: FoodFlowTheme.orange.withOpacity(0.22)),
                  boxShadow: [
                    BoxShadow(
                      color: FoodFlowTheme.orange.withOpacity(0.18),
                      blurRadius: 34,
                      offset: const Offset(0, 18),
                    ),
                    const BoxShadow(
                      color: Color(0x1F000000),
                      blurRadius: 18,
                      offset: Offset(0, 8),
                    ),
                  ],
                ),
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(height: 12),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                _NotificationLabel(
                                  icon: Icons.shopping_bag_rounded,
                                  text: 'New Order',
                                  color: FoodFlowTheme.orange,
                                ),
                                const SizedBox(height: 16),
                                const Text(
                                  'Incoming Order!',
                                  style: TextStyle(
                                    color: FoodFlowTheme.ink,
                                    fontSize: 32,
                                    height: 1,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(height: 10),
                                const Text(
                                  'You have a new order to prepare',
                                  style: TextStyle(
                                    color: FoodFlowTheme.muted,
                                    fontSize: 15,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 4),
                          const _HeroAsset(
                            assetPath: 'assets/images/restauarant.png',
                            size: 132,
                            fallbackIcon: Icons.storefront_rounded,
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _OrderSummary3d(
                        leadingIcon: Icons.restaurant_menu_rounded,
                        orderNumber: '#$orderNumber',
                        title: customerName,
                        subtitle:
                            '${items.length} item${items.length == 1 ? '' : 's'}',
                        amount: total,
                        amountLabel: _textValue(
                          order['payment_method'] ?? order['payment_type'],
                          fallback: 'Payment',
                        ),
                      ),
                      const SizedBox(height: 12),
                      _AddressPreview3d(
                        icon: Icons.location_on_rounded,
                        label: 'Deliver to',
                        value: deliveryAddress,
                        pickupLocation: pickupLocation,
                        deliveryLocation: deliveryLocation,
                      ),
                      if (items.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        _ItemsPreview3d(items: items),
                      ],
                      const SizedBox(height: 12),
                      _PrepSelector3d(
                        minutes: _minutes,
                        canDecrease: !_isBusy && _minutes > _minPrepMinutes,
                        canIncrease: !_isBusy && _minutes < _maxPrepMinutes,
                        onDecrease: () => _changeMinutes(-_prepStep),
                        onIncrease: () => _changeMinutes(_prepStep),
                      ),
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: _isBusy
                                  ? null
                                  : () => _reject('Rejected by restaurant'),
                              icon: _isRejecting
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(Icons.close_rounded),
                              label: const Text('Reject'),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: FoodFlowTheme.orange,
                                side: BorderSide(
                                  color: FoodFlowTheme.orange.withOpacity(0.18),
                                ),
                                backgroundColor:
                                    FoodFlowTheme.orange.withOpacity(0.06),
                                padding:
                                    const EdgeInsets.symmetric(vertical: 16),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(18),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            flex: 2,
                            child: FilledButton.icon(
                              onPressed: _isBusy ? null : _accept,
                              icon: _isAccepting
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white,
                                      ),
                                    )
                                  : const Icon(Icons.check_circle_rounded),
                              label: Text('Accept $_minutes min'),
                              style: FilledButton.styleFrom(
                                backgroundColor: FoodFlowTheme.orange,
                                foregroundColor: Colors.white,
                                padding:
                                    const EdgeInsets.symmetric(vertical: 16),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(18),
                                ),
                                textStyle: const TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 15,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              Positioned(
                top: -28,
                left: 0,
                right: 0,
                child: Center(
                  child: _AlertDisc(
                    seconds: _remainingSeconds,
                    color: FoodFlowTheme.orange,
                  ),
                ),
              ),
            ],
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
      _timer?.cancel();
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
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
    setState(() => _isAccepting = false);
    if (ok) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Order accepted: $_minutes min')),
      );
    } else {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order is no longer available or was cancelled'),
        ),
      );
    }
  }

  Future<void> _reject(String reason) async {
    _timer?.cancel();
    setState(() => _isRejecting = true);
    final ok = await widget.onReject();
    if (!mounted) return;
    setState(() => _isRejecting = false);
    if (ok) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Order rejected')),
      );
    } else {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order is no longer available or was cancelled'),
        ),
      );
    }
  }
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
      width: 64,
      height: 64,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [discColor.withOpacity(0.86), discColor],
        ),
        border: Border.all(color: Colors.white, width: 5),
        boxShadow: [
          BoxShadow(
            color: discColor.withOpacity(0.30),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Center(
        child: Text(
          '${seconds}s',
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w900,
            fontSize: 16,
          ),
        ),
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
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          color: enabled ? Colors.white : FoodFlowTheme.line.withOpacity(0.5),
          borderRadius: BorderRadius.circular(14),
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
      _timer?.cancel();
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
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
    setState(() => _isAccepting = false);
    if (ok) {
      Navigator.pop(context);
      widget.onViewDetails();
      ScaffoldMessenger.of(context).showSnackBar(
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
    setState(() => _isRejecting = false);
    if (ok) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
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
