import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
  int _minutes = 20;
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
    final items = _itemsFrom(order['items']);
    final total = _money(order['total']);
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
                      color: const Color(0xFFFFF3E8),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      Icons.receipt_long,
                      color: FoodFlowTheme.orange,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'New order',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        Text(
                          '#$orderNumber - $total',
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
              const SizedBox(height: 16),
              Text(
                order['customer_name']?.toString().isNotEmpty == true
                    ? order['customer_name'].toString()
                    : 'Customer',
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              if (items.isNotEmpty)
                ...items.take(4).map(
                      (item) => Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Row(
                          children: [
                            Text(
                              '${item['quantity'] ?? 1}x',
                              style: TextStyle(
                                color: FoodFlowTheme.orange,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                item['name']?.toString() ??
                                    item['item_name']?.toString() ??
                                    'Item',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                    fontWeight: FontWeight.w700),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
              const SizedBox(height: 12),
              const Text(
                'Preparation time',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                children: [10, 15, 20, 25, 30, 40]
                    .map(
                      (minutes) => ChoiceChip(
                        label: Text('$minutes min'),
                        selected: _minutes == minutes,
                        onSelected: _isAccepting
                            ? null
                            : (_) => setState(() => _minutes = minutes),
                      ),
                    )
                    .toList(),
              ),
              const SizedBox(height: 14),
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
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not accept order')),
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
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not reject order')),
      );
    }
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
                          Icons.payments_outlined,
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
