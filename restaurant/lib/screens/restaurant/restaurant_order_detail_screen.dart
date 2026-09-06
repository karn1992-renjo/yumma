import 'dart:async';
import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart' show DateFormat;

import '../../config/app_config.dart';
import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../widgets/aurora/swipe_to_confirm.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/common/network_image_loader.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';
import '../../widgets/restaurant/reject_order_dialog.dart';

class RestaurantOrderDetailScreen extends StatefulWidget {
  final int orderId;
  final int? restaurantId;

  const RestaurantOrderDetailScreen({
    super.key,
    required this.orderId,
    this.restaurantId,
  });

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
      final response = await _api.get(
        ApiConstants.restaurantOrderDetails(widget.orderId),
        queryParams: {
          if (widget.restaurantId != null) 'restaurant_id': widget.restaurantId,
        },
      );
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
    String? rejectReason;
    if (status == 'cancelled') {
      rejectReason = await _askRejectReason();
      if (!mounted || rejectReason == null) return;
    }

    setState(() => _isUpdating = true);
    try {
      final response = await _sendOrderAction(
        status,
        rejectReason: rejectReason,
      );
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

  Future<dynamic> _sendOrderAction(
    String status, {
    String? rejectReason,
  }) async {
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
      final reason = rejectReason;
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
      return Scaffold(
        backgroundColor: foodflow.canvas,
        body: Center(
          child: CircularProgressIndicator(color: foodflow.orange),
        ),
      );
    }

    final order = _order;
    if (order == null) {
      return Scaffold(
        backgroundColor: foodflow.canvas,
        appBar: GlassAppBar(
          leading: const BackButton(),
          title: Text('Order details',
              style: TextStyle(
                color: foodflow.ink,
                fontSize: 18,
                fontWeight: FontWeight.w900,
              )),
        ),
        body: NetworkErrorView(
          title: 'Unable to load order',
          message: _loadError ?? 'Order not found',
          onRetry: _loadOrder,
        ),
      );
    }

    final payoutAmount = _restaurantPayout(order);

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        leading: const BackButton(),
        title: Text('Order #${order.orderNumber}',
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            )),
        actions: [
          IconButton(
            onPressed: () => Navigator.pushNamed(
              context,
              '/restaurant/order/chat',
              arguments: order.id,
            ),
            icon: Icon(Icons.chat_bubble_outline_rounded, color: foodflow.ink),
            tooltip: 'Chat',
          ),
          IconButton(
            onPressed: _loadOrder,
            icon: Icon(Icons.refresh_rounded, color: foodflow.ink),
            tooltip: 'Refresh',
          ),
        ],
      ),
      bottomNavigationBar: _buildBottomAction(order),
      body: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(color: foodflow.canvas),
              child: Stack(children: AuroraTheme.auroraBlobs()),
            ),
          ),
          Positioned.fill(
            child: RefreshIndicator(
              onRefresh: _loadOrder,
              color: foodflow.orange,
              child: ListView(
                padding: EdgeInsets.fromLTRB(
                  14,
                  MediaQuery.of(context).padding.top + 64,
                  14,
                  28,
                ),
                children: [
                  _OrderFlowStepper(
                    order: order,
                    payout: formatCurrency(context, payoutAmount),
                  ),
                  const SizedBox(height: 14),
                  _KotTicket(order: order),
                  const SizedBox(height: 14),
                  _OrderMetaStrip(
                    order: order,
                    payoutRows: _payoutRows(context, order, payoutAmount),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _payoutRows(
    BuildContext context,
    Order order,
    double payoutAmount,
  ) {
    return [
      _billRow(context, 'Item subtotal', order.subtotal),
      if (order.discount > 0) _billRow(context, 'Discount', order.discount),
      _billRow(context, _commissionLabel(order), order.platformCommission,
          isDeduction: true),
      if (order.gstOnCommission > 0)
        _billRow(context, 'GST on commission', order.gstOnCommission,
            isDeduction: true),
      if (order.paymentGatewayFee > 0)
        _billRow(context, 'Payment gateway fee', order.paymentGatewayFee,
            isDeduction: true),
      Divider(height: 20, color: foodflow.line),
      _billRow(context, 'Total payout', payoutAmount, isTotal: true),
    ];
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

  ButtonStyle get _primaryStyle => ElevatedButton.styleFrom(
        backgroundColor: foodflow.orange,
        foregroundColor: Colors.white,
        elevation: 0,
        minimumSize: const Size.fromHeight(50),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
      );

  ({String label, String confirmedLabel, String status})? _nextAction(
      Order order) {
    if (order.canRestaurantAccept) {
      return (label: 'Swipe to accept order', confirmedLabel: 'Accepted', status: 'confirmed');
    }
    if (order.canRestaurantStartPreparing) {
      return (label: 'Swipe to start preparing', confirmedLabel: 'Preparing', status: 'preparing');
    }
    if (order.canRestaurantMarkReady) {
      return (label: 'Swipe when food is ready', confirmedLabel: 'Ready', status: 'ready_for_pickup');
    }
    if (order.canRestaurantVerifyTakeawayPickup) {
      return (label: 'Swipe to verify pickup OTP', confirmedLabel: 'Verified', status: 'verify_takeaway_otp');
    }
    return null;
  }

  Widget _buildBottomAction(Order order) {
    final action = _nextAction(order);
    final canReject = order.canRestaurantAccept;
    if (action == null && !canReject) return const SizedBox.shrink();

    return ClipRect(
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
        child: Container(
          decoration: BoxDecoration(
            color: foodflow.canvas.withOpacity(0.82),
            border: Border(top: BorderSide(color: foodflow.glassBorder)),
          ),
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
              child: Row(
                children: [
                  if (canReject) ...[
                    SizedBox(
                      width: 56,
                      height: 56,
                      child: OutlinedButton(
                        onPressed:
                            _isUpdating ? null : () => _updateStatus('cancelled'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: foodflow.danger,
                          side: BorderSide(
                              color: foodflow.danger.withOpacity(0.5)),
                          shape: const CircleBorder(),
                          padding: EdgeInsets.zero,
                        ),
                        child: const Icon(Icons.close_rounded),
                      ),
                    ),
                    const SizedBox(width: 12),
                  ],
                  Expanded(
                    child: action == null
                        ? Container(
                            height: 56,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: foodflow.isDark
                                  ? foodflow.elevatedSurface
                                  : Colors.white,
                              borderRadius: BorderRadius.circular(28),
                              border: Border.all(color: foodflow.line),
                            ),
                            child: Text(
                              'Waiting for delivery partner',
                              style: TextStyle(
                                color: foodflow.muted,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          )
                        : SwipeToConfirm(
                            key: ValueKey(action.status),
                            label: action.label,
                            confirmedLabel: action.confirmedLabel,
                            loading: _isUpdating,
                            accent: foodflow.orange,
                            onConfirmed: () => _updateStatus(action.status),
                          ),
                  ),
                ],
              ),
            ),
          ),
        ),
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
            style: TextStyle(
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
                  style: TextStyle(
                    color: FoodFlowTheme.faint,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  value.isEmpty ? '-' : value,
                  style: TextStyle(
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
                  style: TextStyle(
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
                          style: TextStyle(
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
                        child: Text(
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
                          style: TextStyle(
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
                    style: TextStyle(
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
                      style: TextStyle(
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


/// Horizontal order-flow stepper — the primary visual of the detail screen.
class _OrderFlowStepper extends StatelessWidget {
  const _OrderFlowStepper({required this.order, required this.payout});

  final Order order;
  final String payout;

  static const _deliverySteps = ['New', 'Accepted', 'Preparing', 'Ready', 'Picked up'];
  static const _takeawaySteps = ['New', 'Accepted', 'Preparing', 'Ready', 'Collected'];

  int get _current {
    switch (order.status) {
      case 'pending':
        return 0;
      case 'confirmed':
      case 'accepted':
        return 1;
      case 'preparing':
        return 2;
      case 'ready_for_pickup':
      case 'reached_pickup':
        return 3;
      default:
        return 4;
    }
  }

  ({String title, String hint}) get _headline {
    switch (_current) {
      case 0:
        return (title: 'New order', hint: 'Accept it to start the clock.');
      case 1:
        return (title: 'Order accepted', hint: 'Start preparing when the kitchen picks it up.');
      case 2:
        return (title: 'Preparing', hint: order.hasActivePreparationTimer
            ? 'Mark ready before the timer runs out.'
            : 'Mark ready once the food is packed.');
      case 3:
        return (title: 'Ready for pickup', hint: order.isTakeaway
            ? 'Verify the customer OTP at handover.'
            : 'Waiting for the delivery partner.');
      default:
        return (title: order.isTakeaway ? 'Collected' : 'Picked up', hint: 'This order is on its way.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final steps = order.isTakeaway ? _takeawaySteps : _deliverySteps;
    final current = _current;
    final head = _headline;

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
      decoration: BoxDecoration(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: foodflow.line),
        boxShadow: [
          BoxShadow(
            color: foodflow.isDark
                ? Colors.black.withOpacity(0.35)
                : Colors.black.withOpacity(0.05),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              for (var i = 0; i < steps.length; i++) ...[
                _FlowNode(
                  label: steps[i],
                  done: i < current,
                  active: i == current,
                ),
                if (i != steps.length - 1)
                  Expanded(
                    child: Container(
                      height: 3,
                      margin: const EdgeInsets.only(bottom: 18),
                      decoration: BoxDecoration(
                        color: i < current
                            ? foodflow.orange
                            : foodflow.line,
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
              ],
            ],
          ),
          const SizedBox(height: 6),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      head.title,
                      style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      head.hint,
                      style: TextStyle(
                        color: foodflow.muted,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'You earn $payout',
                      style: TextStyle(
                        color: foodflow.orange,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              if (order.hasActivePreparationTimer) ...[
                const SizedBox(width: 12),
                _PrepRing(order: order),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _FlowNode extends StatelessWidget {
  const _FlowNode({required this.label, required this.done, required this.active});

  final String label;
  final bool done;
  final bool active;

  @override
  Widget build(BuildContext context) {
    final filled = done || active;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 24,
          height: 24,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: filled ? foodflow.orange : Colors.transparent,
            shape: BoxShape.circle,
            border: Border.all(
              color: filled ? foodflow.orange : foodflow.line,
              width: 2,
            ),
          ),
          child: done
              ? const Icon(Icons.check_rounded, size: 14, color: Colors.white)
              : active
                  ? Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                    )
                  : null,
        ),
        const SizedBox(height: 5),
        SizedBox(
          width: 46,
          child: Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: filled ? foodflow.ink : foodflow.faint,
              fontSize: 9.5,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }
}

class _PrepRing extends StatelessWidget {
  const _PrepRing({required this.order});
  final Order order;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<int>(
      stream: Stream.periodic(const Duration(seconds: 1), (v) => v),
      builder: (context, _) {
        final delayed = order.isPreparationDelayed;
        final tint = delayed ? foodflow.danger : foodflow.orange;
        return Container(
          width: 66,
          height: 66,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: tint.withOpacity(0.10),
            shape: BoxShape.circle,
            border: Border.all(color: tint.withOpacity(0.35), width: 2),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                delayed ? Icons.warning_amber_rounded : Icons.timer_outlined,
                size: 15,
                color: tint,
              ),
              const SizedBox(height: 1),
              Text(
                order.readyTimeLabel,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: tint,
                  fontSize: 10,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Order contents rendered as a kitchen ticket, complete with a torn edge.
class _KotTicket extends StatelessWidget {
  const _KotTicket({required this.order});
  final Order order;

  String _time() {
    final d = order.createdAt.toLocal();
    final h = d.hour % 12 == 0 ? 12 : d.hour % 12;
    final m = d.minute.toString().padLeft(2, '0');
    return '$h:$m ${d.hour >= 12 ? 'PM' : 'AM'}';
  }

  @override
  Widget build(BuildContext context) {
    final note = (order.specialInstructions ?? '').trim();
    return PhysicalShape(
      clipper: _TicketClipper(),
      color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
      elevation: foodflow.isDark ? 0 : 3,
      shadowColor: Colors.black.withOpacity(0.12),
      child: Container(
        padding: const EdgeInsets.fromLTRB(18, 20, 18, 22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(Icons.receipt_long_rounded, size: 16, color: foodflow.muted),
                const SizedBox(width: 6),
                Text(
                  'KITCHEN TICKET',
                  style: TextStyle(
                    color: foodflow.muted,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1.5,
                  ),
                ),
                const Spacer(),
                Text(
                  _time(),
                  style: TextStyle(
                    color: foodflow.muted,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              '#${order.orderNumber}',
              style: TextStyle(
                color: foodflow.ink,
                fontSize: 18,
                fontWeight: FontWeight.w900,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
            const SizedBox(height: 12),
            const _DashRule(),
            const SizedBox(height: 12),
            if (order.items.isEmpty)
              Text('No item data available',
                  style: TextStyle(color: foodflow.muted))
            else
              ...order.items.map((it) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              margin: const EdgeInsets.only(top: 1),
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 7, vertical: 2),
                              decoration: BoxDecoration(
                                color: foodflow.orange,
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                '${it.quantity}x',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w900,
                                  fontFeatures: [FontFeature.tabularFigures()],
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                it.name,
                                style: TextStyle(
                                  color: foodflow.ink,
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(
                              formatCurrency(context, it.totalPrice),
                              style: TextStyle(
                                color: foodflow.ink,
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                fontFeatures:
                                    const [FontFeature.tabularFigures()],
                              ),
                            ),
                          ],
                        ),
                        if (it.hasPromotionFreeUnits)
                          Padding(
                            padding: const EdgeInsets.only(left: 34, top: 3),
                            child: Text(
                              'Includes promo free units',
                              style: TextStyle(
                                color: foodflow.success,
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                      ],
                    ),
                  )),
            if (note.isNotEmpty) ...[
              const SizedBox(height: 2),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(11),
                decoration: BoxDecoration(
                  color: foodflow.warmCanvas,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: foodflow.orange.withOpacity(0.3)),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.push_pin_outlined,
                        size: 15, color: foodflow.orange),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        note,
                        style: TextStyle(
                          color: foodflow.inkSoft,
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DashRule extends StatelessWidget {
  const _DashRule();
  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, c) {
      final count = (c.maxWidth / 8).floor();
      return Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: List.generate(
          count,
          (_) => Container(
            width: 4,
            height: 2,
            color: foodflow.line,
          ),
        ),
      );
    });
  }
}

class _TicketClipper extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    final teeth = (size.width / 16).round().clamp(4, 60);
    final w = size.width / teeth;
    final r = Radius.circular(w / 2);
    final path = Path()..moveTo(0, 0);
    for (var i = 0; i < teeth; i++) {
      path.arcToPoint(Offset(w * (i + 1), 0), radius: r, clockwise: false);
    }
    path.lineTo(size.width, size.height);
    for (var i = teeth; i > 0; i--) {
      path.arcToPoint(Offset(w * (i - 1), size.height),
          radius: r, clockwise: false);
    }
    path.close();
    return path;
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}

/// Collapsible strip carrying customer + payout detail (progressive disclosure).
class _OrderMetaStrip extends StatefulWidget {
  const _OrderMetaStrip({required this.order, required this.payoutRows});
  final Order order;
  final List<Widget> payoutRows;

  @override
  State<_OrderMetaStrip> createState() => _OrderMetaStripState();
}

class _OrderMetaStripState extends State<_OrderMetaStrip> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    return Container(
      decoration: BoxDecoration(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: foodflow.line),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          InkWell(
            onTap: () => setState(() => _open = !_open),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 14, 12, 14),
              child: Row(
                children: [
                  Icon(Icons.person_outline, size: 18, color: foodflow.orange),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      order.customerName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  Text(
                    order.paymentMethod.toLowerCase().contains('cod') ||
                            order.paymentMethod.toLowerCase().contains('cash')
                        ? 'COD'
                        : 'Prepaid',
                    style: TextStyle(
                      color: foodflow.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Icon(
                    _open
                        ? Icons.keyboard_arrow_up_rounded
                        : Icons.keyboard_arrow_down_rounded,
                    color: foodflow.muted,
                  ),
                ],
              ),
            ),
          ),
          if (_open)
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 0, 14, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _metaLine(
                    Icons.location_on_outlined,
                    order.isTakeaway ? 'Pickup' : 'Deliver to',
                    order.deliveryAddress,
                  ),
                  if (order.scheduledTime != null)
                    _metaLine(
                      Icons.schedule_outlined,
                      'Scheduled',
                      DateFormat('dd MMM, hh:mm a')
                          .format(order.scheduledTime!),
                    ),
                  const SizedBox(height: 6),
                  Divider(height: 18, color: foodflow.line),
                  ...widget.payoutRows,
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _metaLine(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: foodflow.muted),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label,
                    style: TextStyle(
                      color: foodflow.faint,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                    )),
                Text(value.isEmpty ? '-' : value,
                    style: TextStyle(
                      color: foodflow.ink,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    )),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
