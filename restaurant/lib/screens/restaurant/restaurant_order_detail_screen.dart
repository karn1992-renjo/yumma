import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart' show DateFormat;

import '../../config/app_config.dart';
import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/common/network_image_loader.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';
import '../../widgets/restaurant/reject_order_dialog.dart';

class RestaurantOrderDetailScreen extends StatefulWidget {
  final int orderId;

  const RestaurantOrderDetailScreen({super.key, required this.orderId});

  @override
  State<RestaurantOrderDetailScreen> createState() =>
      _RestaurantOrderDetailScreenState();
}

class _RestaurantOrderDetailScreenState
    extends State<RestaurantOrderDetailScreen> {
  final ApiService _api = ApiService();

  Order? _order;
  String? _loadError;
  bool _isLoading = true;
  bool _isUpdating = false;

  @override
  void initState() {
    super.initState();
    _loadOrder();
  }

  Future<void> _loadOrder() async {
    setState(() => _isLoading = true);
    try {
      final response =
          await _api.get(ApiConstants.restaurantOrderDetails(widget.orderId));
      if (response['success'] == true && mounted) {
        final data = response['data'];
        setState(() {
          _order = Order.fromJson(
            data is Map<String, dynamic>
                ? data
                : Map<String, dynamic>.from(data as Map),
          );
          _loadError = null;
        });
      }
    } catch (e) {
      debugPrint('Load restaurant order error: $e');
      if (mounted) {
        setState(() => _loadError = _cleanApiError(e));
      }
    }
    if (mounted) setState(() => _isLoading = false);
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

  Future<void> _updateStatus(String status) async {
    if (_isUpdating) return;
    setState(() => _isUpdating = true);
    try {
      final response = await _sendOrderAction(status);
      if (response['success'] == true) {
        await _loadOrder();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
                content: Text(response['message']?.toString() ??
                    'Order ${status.replaceAll('_', ' ')}')),
          );
        }
      }
    } catch (e) {
      debugPrint('Restaurant order status error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update order: $e')),
        );
      }
    }
    if (mounted) setState(() => _isUpdating = false);
  }

  Future<void> _extendPrepTime(int minutes) async {
    if (_isUpdating) return;
    setState(() => _isUpdating = true);
    try {
      final response = await _api.post(
        ApiConstants.restaurantExtendPrepTime(widget.orderId),
        data: {'additional_minutes': minutes},
      );
      if (response['success'] == true) {
        await _loadOrder();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response['message']?.toString() ??
                  'Preparation time extended'),
            ),
          );
        }
      }
    } catch (e) {
      debugPrint('Extend prep time error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to extend time: $e')),
        );
      }
    }
    if (mounted) setState(() => _isUpdating = false);
  }

  Future<dynamic> _sendOrderAction(String status) async {
    if (status == 'verify_takeaway_otp') {
      final otp = await _askPickupOtp();
      if (otp == null) return {'success': false};
      return _api.post(
        ApiConstants.restaurantVerifyTakeawayOtp(widget.orderId),
        data: {'otp': otp},
      );
    }
    if (status == 'confirmed') {
      return _api.post(ApiConstants.restaurantAcceptOrder(widget.orderId));
    }
    if (status == 'ready_for_pickup') {
      return _api.post(ApiConstants.restaurantOrderReady(widget.orderId));
    }
    if (status == 'cancelled') {
      final reason = await _askRejectReason();
      if (reason == null) return {'success': false};
      return _api.post(
        ApiConstants.restaurantRejectOrder(widget.orderId),
        data: {'reason': reason},
      );
    }
    return _api.post(
      ApiConstants.restaurantOrderStatus(widget.orderId),
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
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final order = _order;
    if (order == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Order Details')),
        body: NetworkErrorView(
          title: 'Unable to load order',
          message: _loadError ?? 'Order not found',
          onRetry: _loadOrder,
        ),
      );
    }

    final payoutAmount = _restaurantPayout(order);

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: Text('Order #${order.orderNumber}'),
        actions: [
          IconButton(
            onPressed: () => Navigator.pushNamed(
              context,
              '/restaurant/order/chat',
              arguments: order.id,
            ),
            icon: const Icon(Icons.chat_bubble_outline_rounded),
            tooltip: 'Chat',
          ),
          IconButton(
            onPressed: _loadOrder,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
        ],
      ),
      bottomNavigationBar: _buildBottomAction(order),
      body: RefreshIndicator(
        onRefresh: _loadOrder,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: RestaurantPremium.glowPanel(radius: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          formatCurrency(context, payoutAmount),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 30,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      _StatusPill(
                        text: order.statusText,
                        color: RestaurantPremium.gold,
                        textColor: RestaurantPremium.navy,
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Total payout for this order',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.92),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${order.items.length} item${order.items.length == 1 ? '' : 's'} - ${order.paymentMethod}',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.76),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (order.hasActivePreparationTimer) ...[
              _section(
                title: 'Preparation',
                children: [
                  _PreparationTimingPanel(
                    order: order,
                    canExtend: !_isUpdating,
                    onExtend: _extendPrepTime,
                  ),
                ],
              ),
              const SizedBox(height: 14),
            ],
            _section(
              title: 'Customer',
              children: [
                _detailRow(Icons.person_outline, 'Name', order.customerName),
                if (order.scheduledTime != null)
                  _detailRow(
                    Icons.schedule_outlined,
                    'Scheduled',
                    DateFormat('dd MMM yyyy, hh:mm a')
                        .format(order.scheduledTime!),
                  ),
                _detailRow(
                  Icons.location_on_outlined,
                  'Address',
                  order.deliveryAddress,
                ),
                if ((order.specialInstructions ?? '').trim().isNotEmpty)
                  _detailRow(
                    Icons.note_alt_outlined,
                    'Customer Note',
                    order.specialInstructions!.trim(),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            _section(
              title: 'Items',
              children: order.items.isEmpty
                  ? [
                      const Text(
                        'No item data available',
                        style: TextStyle(color: FoodFlowTheme.muted),
                      ),
                    ]
                  : order.items.map(_itemRow).toList(),
            ),
            const SizedBox(height: 14),
            _section(
              title: 'Payout Summary',
              children: [
                _billRow(context, 'Item subtotal', order.subtotal),
                if (order.discount > 0)
                  _billRow(context, 'Discount', order.discount),
                _billRow(
                  context,
                  _commissionLabel(order),
                  order.platformCommission,
                  isDeduction: true,
                ),
                if (order.gstOnCommission > 0)
                  _billRow(
                    context,
                    'GST on commission',
                    order.gstOnCommission,
                    isDeduction: true,
                  ),
                if (order.paymentGatewayFee > 0)
                  _billRow(
                    context,
                    'Payment gateway fee',
                    order.paymentGatewayFee,
                    isDeduction: true,
                  ),
                const Divider(height: 22),
                _billRow(
                  context,
                  'Total payout',
                  payoutAmount,
                  isTotal: true,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  double _restaurantPayout(Order order) {
    if (order.restaurantEarning > 0) return order.restaurantEarning;
    final calculated = order.subtotal -
        order.platformCommission -
        order.gstOnCommission -
        order.paymentGatewayFee;
    return calculated > 0 ? calculated : order.subtotal;
  }

  String _commissionLabel(Order order) {
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

  Widget _buildBottomAction(Order order) {
    Widget? button;
    if (order.canRestaurantAccept) {
      button = Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: _isUpdating ? null : () => _updateStatus('cancelled'),
              style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
              child: const Text('Reject'),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: ElevatedButton(
              onPressed: _isUpdating ? null : () => _updateStatus('confirmed'),
              child: const Text('Accept'),
            ),
          ),
        ],
      );
    } else if (order.canRestaurantStartPreparing) {
      button = ElevatedButton(
        onPressed: _isUpdating ? null : () => _updateStatus('preparing'),
        child: const Text('Start Preparing'),
      );
    } else if (order.canRestaurantMarkReady) {
      button = ElevatedButton(
        onPressed: _isUpdating ? null : () => _updateStatus('ready_for_pickup'),
        child: const Text('Mark Ready'),
      );
    } else if (order.canRestaurantVerifyTakeawayPickup) {
      button = ElevatedButton.icon(
        onPressed:
            _isUpdating ? null : () => _updateStatus('verify_takeaway_otp'),
        icon: const Icon(Icons.password),
        label: const Text('Verify Pickup OTP'),
      );
    }

    if (button == null) return const SizedBox.shrink();

    return SafeArea(
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: FoodFlowTheme.line)),
        ),
        child: button,
      ),
    );
  }

  Widget _section({required String title, required List<Widget> children}) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: RestaurantPremium.panel(radius: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }

  Widget _detailRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: FoodFlowTheme.orange),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    color: FoodFlowTheme.faint,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  value.isEmpty ? '-' : value,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
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

  Widget _itemRow(OrderItem item) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _OrderItemImage(item: item, size: 42),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${item.quantity}x',
                  style: TextStyle(
                    color: FoodFlowTheme.orange,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (item.hasPromotionFreeUnits) ...[
                  const SizedBox(height: 5),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: FoodFlowTheme.success.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: FoodFlowTheme.success.withOpacity(0.28),
                          ),
                        ),
                        child: Text(
                          '${item.promotionFreeQuantity} FREE',
                          style: const TextStyle(
                            color: FoodFlowTheme.success,
                            fontSize: 10,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if ((item.promotionTitle ?? '').trim().isNotEmpty)
                        Text(
                          item.promotionTitle!.trim(),
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                    ],
                  ),
                ],
                if (item.isPromotionReward) ...[
                  const SizedBox(height: 5),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: FoodFlowTheme.success.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: FoodFlowTheme.success.withOpacity(0.28),
                          ),
                        ),
                        child: const Text(
                          'PROMOTION REWARD',
                          style: TextStyle(
                            color: FoodFlowTheme.success,
                            fontSize: 10,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if ((item.promotionTitle ?? '').trim().isNotEmpty)
                        Text(
                          item.promotionTitle!.trim(),
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                    ],
                  ),
                ],
                if (item.hasCustomizations) ...[
                  const SizedBox(height: 3),
                  Text(
                    item.customizationSummary,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Text(
            item.isPromotionReward
                ? 'FREE'
                : item.hasPromotionFreeUnits
                    ? formatCurrency(
                        context,
                        item.unitPrice *
                            (item.promotionPaidQuantity > 0
                                ? item.promotionPaidQuantity
                                : item.quantity - item.promotionFreeQuantity),
                      )
                    : formatCurrency(context, item.totalPrice),
            style: TextStyle(
              color: item.isPromotionReward
                  ? FoodFlowTheme.success
                  : FoodFlowTheme.ink,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  Widget _billRow(
    BuildContext context,
    String label,
    num value, {
    bool isTotal = false,
    bool isDeduction = false,
  }) {
    final amountText = isDeduction
        ? '-${formatCurrency(context, value)}'
        : formatCurrency(context, value);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                color: isTotal ? FoodFlowTheme.ink : FoodFlowTheme.muted,
                fontSize: isTotal ? 16 : 14,
                fontWeight: isTotal ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
          ),
          Text(
            amountText,
            style: TextStyle(
              color: isTotal
                  ? FoodFlowTheme.orange
                  : isDeduction
                      ? FoodFlowTheme.danger
                      : FoodFlowTheme.ink,
              fontSize: isTotal ? 18 : 14,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _OrderItemImage extends StatelessWidget {
  final OrderItem item;
  final double size;

  const _OrderItemImage({required this.item, required this.size});

  @override
  Widget build(BuildContext context) {
    final imageUrl = _resolveImageUrl(item.imageUrl);
    final radius = BorderRadius.circular(size * 0.24);
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
        final title = delayed ? 'Delayed' : 'Ready countdown';
        final subtitle = delayed
            ? 'Add time to update the customer and assigned driver.'
            : 'Ready in ${order.readyTimeLabel}';

        return Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: color.withOpacity(0.09),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: color.withOpacity(0.24)),
          ),
          child: Row(
            children: [
              Icon(
                delayed ? Icons.warning_amber_rounded : Icons.timer_outlined,
                color: color,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        color: color,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              if (delayed)
                PopupMenuButton<int>(
                  tooltip: 'Extend time',
                  enabled: canExtend,
                  onSelected: onExtend,
                  itemBuilder: (context) => const [
                    PopupMenuItem(value: 5, child: Text('+5 min')),
                    PopupMenuItem(value: 10, child: Text('+10 min')),
                    PopupMenuItem(value: 15, child: Text('+15 min')),
                  ],
                  child: Text(
                    'Extend',
                    style: TextStyle(
                      color: canExtend ? color : FoodFlowTheme.faint,
                      fontWeight: FontWeight.w900,
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

class _StatusPill extends StatelessWidget {
  final String text;
  final Color color;
  final Color textColor;

  const _StatusPill({
    required this.text,
    required this.color,
    required this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: textColor,
          fontSize: 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}
