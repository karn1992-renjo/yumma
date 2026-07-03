import 'dart:async';

import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:provider/provider.dart';

import '../../models/order.dart';
import '../../models/menu_item.dart';
import '../../models/restaurant.dart';
import '../../config/api_constants.dart';
import '../../providers/order_provider.dart';
import '../../providers/cart_provider.dart';
import '../../services/api_service.dart';
import '../../services/websocket_service.dart';
import '../../providers/auth_provider.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/account_chrome.dart';
import 'order_tracking_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  static const _text = Color(0xFF111827);
  static const _muted = Color(0xFF6B7280);
  static const _softLine = Color(0xFFE5E7EB);
  static const _softCanvas = Colors.white;
  final ApiService _api = ApiService();

  late final TabController _tabController;
  bool _isLoading = true;
  Timer? _refreshTimer;
  int? _realtimeUserId;
  String? _realtimeHandlerId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _tabController = TabController(length: 3, vsync: this);
    _tabController.addListener(_onTabChanged);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _loadOrders();
        _initializeRealtime();
        _refreshTimer = Timer.periodic(const Duration(seconds: 15), (_) {
          if (mounted) {
            context.read<OrderProvider>().fetchMyOrders(notifyLoading: false);
          }
        });
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _refreshTimer?.cancel();
    if (_realtimeUserId != null) {
      WebSocketService().removeCustomerHandler(
        _realtimeUserId!,
        _realtimeHandlerId,
      );
    }
    _tabController.removeListener(_onTabChanged);
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _initializeRealtime() async {
    for (var attempt = 0; attempt < 10 && mounted; attempt++) {
      final user = context.read<AuthProvider>().currentUser;
      if (user != null) {
        _realtimeUserId = user.id;
        _realtimeHandlerId = await WebSocketService().initCustomer(
          user.id,
          onOrderUpdate: (data) {
            if (!mounted) return;
            final updated =
                context.read<OrderProvider>().applyOrderStatusUpdate(data);
            if (updated == null) {
              unawaited(context
                  .read<OrderProvider>()
                  .fetchMyOrders(notifyLoading: false));
            }
          },
        );
        return;
      }
      await Future<void>.delayed(const Duration(milliseconds: 300));
    }
  }

  void _onTabChanged() {
    if (!mounted) return;
    setState(() {});
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _refreshOrders();
    }
  }

  Future<void> _loadOrders() async {
    final orderProvider = context.read<OrderProvider>();
    await orderProvider.fetchMyOrders();
    if (!mounted) return;
    setState(() => _isLoading = false);
  }

  Future<void> _refreshOrders() async {
    await context.read<OrderProvider>().fetchMyOrders();
  }

  List<Order> _ordersForTab(List<Order> orders, int tabIndex) {
    switch (tabIndex) {
      case 0:
        return orders.where((o) => !o.isDelivered && !o.isCancelled).toList();
      case 1:
        return orders.where((o) => o.isDelivered).toList();
      case 2:
        return orders.where((o) => o.isCancelled).toList();
      default:
        return const <Order>[];
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: _softCanvas,
      body: SafeArea(
        child: Consumer<OrderProvider>(
          builder: (context, orderProvider, _) {
            final activeOrders = _ordersForTab(orderProvider.orders, 0).length;
            final pastOrders = _ordersForTab(orderProvider.orders, 1).length;
            final cancelledOrders =
                _ordersForTab(orderProvider.orders, 2).length;
            final filteredOrders =
                _ordersForTab(orderProvider.orders, _tabController.index);

            return Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 46,
                            height: 46,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(color: accountBorder),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withOpacity(0.04),
                                  blurRadius: 18,
                                  offset: const Offset(0, 8),
                                ),
                              ],
                            ),
                            child: IconButton(
                              onPressed: () => Navigator.of(context).maybePop(),
                              icon: const Icon(
                                Icons.arrow_back_ios_new_rounded,
                                size: 18,
                                color: _text,
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: const [
                                Text(
                                  'My Orders',
                                  style: TextStyle(
                                    fontSize: 26,
                                    fontWeight: FontWeight.w900,
                                    color: _text,
                                    height: 1.05,
                                  ),
                                ),
                                SizedBox(height: 4),
                                Text(
                                  'Track deliveries, reorder favourites, and manage active meals.',
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: _muted,
                                    fontWeight: FontWeight.w400,
                                    height: 1.35,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      AccountHeroCard(
                        title: 'Orders made simple',
                        subtitle:
                            'Keep every update, payment status, and reorder action in one clean place.',
                        icon: Icons.receipt_long_rounded,
                        badge: '${orderProvider.orders.length} TOTAL ORDERS',
                        margin: const EdgeInsets.fromLTRB(0, 18, 0, 16),
                        trailing: Container(
                          width: 78,
                          height: 78,
                          decoration: BoxDecoration(
                            color: Colors.white.withOpacity(0.16),
                            borderRadius: BorderRadius.circular(24),
                            border: Border.all(
                              color: Colors.white.withOpacity(0.12),
                            ),
                          ),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                Icons.local_shipping_outlined,
                                color: Colors.white,
                                size: 30,
                              ),
                              const SizedBox(height: 6),
                              Text(
                                '$activeOrders active',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      _buildTabs(
                        scheme: scheme,
                        activeCount: activeOrders,
                        pastCount: pastOrders,
                        cancelledCount: cancelledOrders,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                Expanded(
                  child: _isLoading || orderProvider.isLoading
                      ? _buildLoadingState(scheme.primary)
                      : filteredOrders.isEmpty
                          ? _buildEmptyState(
                              tabIndex: _tabController.index,
                              primary: scheme.primary,
                            )
                          : RefreshIndicator(
                              onRefresh: _refreshOrders,
                              color: scheme.primary,
                              child: ListView.builder(
                                physics: const BouncingScrollPhysics(
                                  parent: AlwaysScrollableScrollPhysics(),
                                ),
                                padding: const EdgeInsets.fromLTRB(
                                  16,
                                  4,
                                  16,
                                  24,
                                ),
                                itemCount: filteredOrders.length,
                                itemBuilder: (context, index) {
                                  return _buildOrderCard(
                                    context,
                                    filteredOrders[index],
                                  );
                                },
                              ),
                            ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _buildTabs({
    required ColorScheme scheme,
    required int activeCount,
    required int pastCount,
    required int cancelledCount,
  }) {
    return Container(
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: accountBorder),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: _tabPill(
              label: 'Active',
              count: activeCount,
              selected: _tabController.index == 0,
              primary: scheme.primary,
              onTap: () => _tabController.animateTo(0),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: _tabPill(
              label: 'Past',
              count: pastCount,
              selected: _tabController.index == 1,
              primary: scheme.primary,
              onTap: () => _tabController.animateTo(1),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: _tabPill(
              label: 'Cancelled',
              count: cancelledCount,
              selected: _tabController.index == 2,
              primary: scheme.primary,
              onTap: () => _tabController.animateTo(2),
            ),
          ),
        ],
      ),
    );
  }

  Widget _tabPill({
    required String label,
    required int count,
    required bool selected,
    required Color primary,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
        decoration: BoxDecoration(
          color: selected ? primary : Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: selected ? primary : _softLine,
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              '$count',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w900,
                color: selected ? Colors.white : _text,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w400,
                color: selected ? Colors.white : _muted,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLoadingState(Color primary) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            width: 30,
            height: 30,
            child: CircularProgressIndicator(
              strokeWidth: 2.6,
              color: primary,
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'Loading your orders...',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: _muted,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState({
    required int tabIndex,
    required Color primary,
  }) {
    late final String title;
    late final String subtitle;
    late final IconData icon;

    switch (tabIndex) {
      case 0:
        title = 'No active orders yet';
        subtitle = 'Your live deliveries and pickup updates will show here.';
        icon = Icons.delivery_dining_rounded;
        break;
      case 1:
        title = 'No past orders yet';
        subtitle = 'Completed meals and reorder history will appear here.';
        icon = Icons.history_rounded;
        break;
      default:
        title = 'No cancelled orders';
        subtitle = 'If an order is cancelled, you will find it here.';
        icon = Icons.cancel_outlined;
    }

    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 108,
              height: 108,
              decoration: BoxDecoration(
                color: primary.withOpacity(0.08),
                borderRadius: BorderRadius.circular(34),
              ),
              child: Icon(icon, size: 48, color: primary),
            ),
            const SizedBox(height: 24),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w900,
                color: _text,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 14,
                height: 1.4,
                fontWeight: FontWeight.w500,
                color: _muted,
              ),
            ),
            if (tabIndex == 0) ...[
              const SizedBox(height: 22),
              ElevatedButton(
                onPressed: () => Navigator.pushNamed(context, '/customer/home'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: primary,
                  foregroundColor: Colors.white,
                  minimumSize: const Size(190, 54),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(18),
                  ),
                ),
                child: const Text(
                  'Browse Restaurants',
                  style: TextStyle(
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildOrderCard(BuildContext context, Order order) {
    final primary = Theme.of(context).colorScheme.primary;
    final itemPreview = order.items.take(2).toList();

    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => OrderTrackingScreen(orderId: order.id),
        ),
      ),
      child: AccountSurfaceCard(
        margin: const EdgeInsets.only(bottom: 14),
        padding: EdgeInsets.zero,
        radius: 28,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
              child: Row(
                children: [
                  _restaurantThumb(order),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          order.restaurant?.name ?? 'Restaurant',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            color: _text,
                            height: 1.1,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Order #${order.orderNumber}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w400,
                            color: _muted,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            _statusChip(order),
                            _metaPill(
                              icon: Icons.calendar_today_rounded,
                              label: _formatDate(order.createdAt),
                            ),
                            _metaPill(
                              icon: Icons.payment_rounded,
                              label: order.paymentMethod.toUpperCase(),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Container(
              width: double.infinity,
              margin: const EdgeInsets.symmetric(horizontal: 18),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFFAFAFA),
                borderRadius: BorderRadius.circular(22),
                border: Border.all(color: _softLine),
              ),
              child: Column(
                children: [
                  for (final item in itemPreview) ...[
                    Row(
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: primary,
                            borderRadius: BorderRadius.circular(99),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            '${item.quantity}x ${item.name}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: _text,
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          formatCurrency(context, item.totalPrice),
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: _text,
                          ),
                        ),
                      ],
                    ),
                    if (item != itemPreview.last) const SizedBox(height: 10),
                  ],
                  if (order.items.length > 2) ...[
                    const SizedBox(height: 10),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        '+${order.items.length - 2} more items',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: _muted,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 16, 18, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: const [
                        Text(
                          'Total Amount',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: _muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    formatCurrency(context, order.total),
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                      color: primary,
                    ),
                  ),
                ],
              ),
            ),
            if (!order.isDelivered && !order.isCancelled)
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
                child: Row(
                  children: [
                    if (order.canCancel) ...[
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => _showCancelOrderDialog(order),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: const Color(0xFFE11D48),
                            side: const BorderSide(color: Color(0xFFF3D6D9)),
                            minimumSize: const Size.fromHeight(52),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(18),
                            ),
                          ),
                          child: const Text(
                            'Cancel Order',
                            style: TextStyle(
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                    ],
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                OrderTrackingScreen(orderId: order.id),
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          minimumSize: const Size.fromHeight(52),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(18),
                          ),
                        ),
                        child: const Text(
                          'Track Order',
                          style: TextStyle(
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            if (order.isDelivered)
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
                child: SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () => _reorderItems(order),
                    icon: Icon(Icons.repeat_rounded, color: primary),
                    label: Text(
                      'Order Again',
                      style: TextStyle(
                        color: primary,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      side: BorderSide(color: primary.withOpacity(0.18)),
                      minimumSize: const Size.fromHeight(52),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(18),
                      ),
                    ),
                  ),
                ),
              ),
            if (order.canRequestRefund)
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
                child: SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () => _showRefundRequestDialog(order),
                    icon: const Icon(
                      Icons.monetization_on_outlined,
                      color: Color(0xFF138A5A),
                    ),
                    label: const Text(
                      'Request Refund',
                      style: TextStyle(
                        color: Color(0xFF138A5A),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: Color(0xFFCDEBDE)),
                      minimumSize: const Size.fromHeight(52),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(18),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _restaurantThumb(Order order) {
    final restaurant = order.restaurant;
    final logoUrl = restaurant == null
        ? ''
        : (restaurant.logoUrl.isNotEmpty
            ? restaurant.logoUrl
            : restaurant.bannerUrl);
    return Container(
      width: 78,
      height: 78,
      decoration: BoxDecoration(
        color: const Color(0xFFF6F7F9),
        borderRadius: BorderRadius.circular(22),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(22),
        child: logoUrl.isNotEmpty
            ? AppCachedImage(
                imageUrl: logoUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const Icon(
                  Icons.restaurant_rounded,
                  size: 34,
                  color: _muted,
                ),
              )
            : const Icon(
                Icons.restaurant_rounded,
                size: 34,
                color: _muted,
              ),
      ),
    );
  }

  Widget _statusChip(Order order) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: order.statusColor.withOpacity(0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        order.statusText,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w800,
          color: order.statusColor,
        ),
      ),
    );
  }

  Widget _metaPill({
    required IconData icon,
    required String label,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: _softLine),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: _muted),
          const SizedBox(width: 6),
          Text(
            label,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: _muted,
            ),
          ),
        ],
      ),
    );
  }

  void _showCancelOrderDialog(Order order) {
    final reasonController = TextEditingController();

    showDialog(
      context: context,
      builder: (dialogContext) => Dialog(
        insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        backgroundColor: Colors.transparent,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(30),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.12),
                blurRadius: 40,
                offset: const Offset(0, 18),
              ),
            ],
          ),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(22),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 54,
                      height: 54,
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF1F2),
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: const Icon(
                        Icons.close_rounded,
                        color: Color(0xFFE11D48),
                        size: 28,
                      ),
                    ),
                    const SizedBox(width: 14),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Cancel this order?',
                            style: TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                              color: _text,
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'You can cancel only before the restaurant accepts it.',
                            style: TextStyle(
                              fontSize: 13,
                              color: _muted,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFFBFA),
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: accountBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Order #${order.orderNumber}',
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: _text,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        order.restaurant?.name ?? 'Restaurant',
                        style: const TextStyle(
                          fontSize: 13,
                          color: _muted,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                TextField(
                  controller: reasonController,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: 'Reason for cancellation',
                    hintText:
                        'Changed my mind, wrong address, ordered by mistake...',
                    alignLabelWithHint: true,
                    filled: true,
                    fillColor: Colors.white,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: const BorderSide(color: _softLine),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: const BorderSide(color: _softLine),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: const BorderSide(
                        color: Color(0xFFE11D48),
                        width: 1.4,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.pop(dialogContext),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: _softLine),
                          minimumSize: const Size.fromHeight(52),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        child: const Text(
                          'Keep Order',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () async {
                          final reason = reasonController.text.trim();
                          if (reason.isEmpty) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text(
                                  'Please enter a cancellation reason.',
                                ),
                                backgroundColor: Colors.red,
                              ),
                            );
                            return;
                          }

                          Navigator.pop(dialogContext);
                          final orderProvider = context.read<OrderProvider>();
                          final success =
                              await orderProvider.cancelOrder(order.id, reason);

                          if (success && mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Order cancelled successfully'),
                                backgroundColor: Colors.green,
                              ),
                            );
                            _refreshOrders();
                          } else if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  orderProvider.error ??
                                      'Order can only be cancelled before the restaurant accepts it.',
                                ),
                                backgroundColor: Colors.red,
                              ),
                            );
                          }
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFE11D48),
                          foregroundColor: Colors.white,
                          elevation: 0,
                          minimumSize: const Size.fromHeight(52),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        child: const Text(
                          'Cancel Order',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _showRefundRequestDialog(Order order) {
    final reasonController = TextEditingController();
    final amountController = TextEditingController();
    final primary = Theme.of(context).colorScheme.primary;

    showDialog(
      context: context,
      builder: (dialogContext) => Dialog(
        insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        backgroundColor: Colors.transparent,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(30),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.12),
                blurRadius: 40,
                offset: const Offset(0, 18),
              ),
            ],
          ),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(22),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 54,
                      height: 54,
                      decoration: BoxDecoration(
                        color: primary.withOpacity(0.10),
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: Icon(
                        Icons.wallet_giftcard_rounded,
                        color: primary,
                        size: 28,
                      ),
                    ),
                    const SizedBox(width: 14),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Request refund',
                            style: TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                              color: _text,
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'Share the issue and optionally suggest a refund amount.',
                            style: TextStyle(
                              fontSize: 13,
                              color: _muted,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                TextField(
                  controller: reasonController,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: 'Reason',
                    hintText: 'Why are you requesting a refund?',
                    filled: true,
                    fillColor: Colors.white,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: const BorderSide(color: _softLine),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: BorderSide(color: primary, width: 1.4),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: amountController,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: 'Refund Amount (optional)',
                    hintText: 'Max ${formatCurrency(context, order.total)}',
                    filled: true,
                    fillColor: Colors.white,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: const BorderSide(color: _softLine),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: BorderSide(color: primary, width: 1.4),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.pop(dialogContext),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: _softLine),
                          minimumSize: const Size.fromHeight(52),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        child: const Text(
                          'Close',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () async {
                          final reason = reasonController.text.trim();
                          final amountText = amountController.text.trim();
                          final amount = amountText.isEmpty
                              ? null
                              : double.tryParse(amountText);

                          if (reason.isEmpty) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Please enter a refund reason.'),
                                backgroundColor: Colors.red,
                              ),
                            );
                            return;
                          }

                          Navigator.pop(dialogContext);
                          final orderProvider = context.read<OrderProvider>();
                          final success = await orderProvider.requestRefund(
                            order.id,
                            reason,
                            amount: amount,
                          );

                          if (success && mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text(
                                  'Refund request submitted successfully.',
                                ),
                                backgroundColor: Colors.green,
                              ),
                            );
                            _refreshOrders();
                          } else if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  orderProvider.error ??
                                      'Failed to submit refund request.',
                                ),
                                backgroundColor: Colors.red,
                              ),
                            );
                          }
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          minimumSize: const Size.fromHeight(52),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        child: const Text(
                          'Submit Request',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _reorderItems(Order order) async {
    if (order.restaurantId <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Restaurant details are unavailable.')),
      );
      return;
    }

    try {
      final restaurantResponse = await _api
          .get('${ApiConstants.restaurantDetails}/${order.restaurantId}');
      final restaurantData = restaurantResponse['data'] is Map<String, dynamic>
          ? restaurantResponse['data'] as Map<String, dynamic>
          : Map<String, dynamic>.from(restaurantResponse as Map);
      final restaurant = Restaurant.fromJson(restaurantData);

      final menuResponse = await _api
          .get('${ApiConstants.restaurantDetails}/${order.restaurantId}/menu');
      final menuData = menuResponse['data'] is Map<String, dynamic>
          ? menuResponse['data'] as Map<String, dynamic>
          : menuResponse;
      final rawItems = (menuData['menu_items'] ??
              menuData['items'] ??
              menuData['menu']) as List? ??
          const <dynamic>[];
      final menuById = rawItems
          .whereType<Map>()
          .map((item) => MenuItem.fromJson(Map<String, dynamic>.from(item)))
          .where((item) => item.isAvailable)
          .fold<Map<int, MenuItem>>(<int, MenuItem>{}, (map, item) {
        map[item.id] = item;
        return map;
      });

      var added = 0;
      final cart = context.read<CartProvider>();
      for (final orderItem in order.items) {
        final menuItemId = orderItem.menuItemId;
        if (menuItemId == null) continue;
        final menuItem = menuById[menuItemId];
        if (menuItem == null) continue;
        for (var i = 0; i < orderItem.quantity; i++) {
          cart.addItem(menuItem, restaurant);
          added++;
        }
      }

      if (!mounted) return;
      if (added == 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No previous items are currently available.'),
          ),
        );
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('$added item${added == 1 ? '' : 's'} added to cart'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.pushNamed(context, '/checkout');
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Unable to order again right now.'),
        ),
      );
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  }
}
