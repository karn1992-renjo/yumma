import 'package:flutter/material.dart';
import 'package:intl/intl.dart' show DateFormat;

import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
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
                          formatCurrency(context, order.total),
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
                    '${order.items.length} item${order.items.length == 1 ? '' : 's'} - ${order.paymentMethod}',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.9),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _section(
              title: 'Customer',
              children: [
                _detailRow(Icons.person_outline, 'Name', order.customerName),
                _detailRow(Icons.call_outlined, 'Phone', order.customerPhone),
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
              title: 'Bill Summary',
              children: [
                _billRow(context, 'Subtotal', order.subtotal),
                _billRow(context, 'Delivery Fee', order.deliveryFee),
                _billRow(context, 'Tax', order.tax),
                if (order.discount > 0)
                  _billRow(context, 'Discount', order.discount),
                const Divider(height: 22),
                _billRow(context, 'Total', order.total, isTotal: true),
              ],
            ),
          ],
        ),
      ),
    );
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
        onPressed:
            _isUpdating ? null : () => _updateStatus('ready_for_pickup'),
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
          Container(
            width: 28,
            height: 28,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: const Color(0xFFFFF3E8),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              '${item.quantity}x',
              style: TextStyle(
                color: FoodFlowTheme.orange,
                fontSize: 11,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
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
            formatCurrency(context, item.totalPrice),
            style: const TextStyle(
              color: FoodFlowTheme.ink,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  Widget _billRow(BuildContext context, String label, num value,
      {bool isTotal = false}) {
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
            formatCurrency(context, value),
            style: TextStyle(
              color: isTotal ? FoodFlowTheme.orange : FoodFlowTheme.ink,
              fontSize: isTotal ? 18 : 14,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
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
