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
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import 'order_tracking_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  static const _text = FoodFlowTheme.ink;
  static const _muted = FoodFlowTheme.muted;
  static const _softLine = FoodFlowTheme.line;
  static const _success = FoodFlowTheme.success;
  static const _softCanvas = FoodFlowTheme.warmCanvas;
  final ApiService _api = ApiService();

  Color get _primary => FoodFlowTheme.brandPrimary(context);
  Color get _secondary => FoodFlowTheme.brandSecondary(context);

  late final TabController _tabController;
  final TextEditingController _searchController = TextEditingController();
  bool _isLoading = true;
  Timer? _refreshTimer;
  int? _realtimeUserId;
  String? _realtimeHandlerId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _tabController = TabController(length: 4, vsync: this);
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
    _searchController.dispose();
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

  void _handleBack() {
    if (Navigator.canPop(context)) {
      Navigator.pop(context);
      return;
    }
    Navigator.pushReplacementNamed(context, '/customer/home');
  }

  Future<bool> _handleSystemBack() async {
    if (Navigator.canPop(context)) {
      return true;
    }
    Navigator.pushReplacementNamed(context, '/customer/home');
    return false;
  }

  List<Order> _ordersForTab(List<Order> orders, int tabIndex) {
    switch (tabIndex) {
      case 0:
        return List<Order>.from(orders);
      case 1:
        return orders.where((o) => !o.isDelivered && !o.isCancelled).toList();
      case 2:
        return orders.where((o) => o.isDelivered).toList();
      case 3:
        return orders.where((o) => o.isCancelled).toList();
      default:
        return const <Order>[];
    }
  }

  List<Order> _visibleOrders(List<Order> orders) {
    final query = _searchController.text.trim().toLowerCase();
    final tabbed = _ordersForTab(orders, _tabController.index);
    return tabbed.where((order) {
      if (query.isEmpty) return true;
      final restaurant = order.restaurant?.name.toLowerCase() ?? '';
      final orderNumber = order.orderNumber.toLowerCase();
      final items =
          order.items.map((item) => item.name.toLowerCase()).join(' ');
      return restaurant.contains(query) ||
          orderNumber.contains(query) ||
          items.contains(query);
    }).toList(growable: false);
  }

  @override
  Widget build(BuildContext context) {
    return WillPopScope(
      onWillPop: _handleSystemBack,
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: Consumer<OrderProvider>(
            builder: (context, orderProvider, _) {
              final allCount = orderProvider.orders.length;
              final ongoingCount =
                  _ordersForTab(orderProvider.orders, 1).length;
              final completedCount =
                  _ordersForTab(orderProvider.orders, 2).length;
              final cancelledCount =
                  _ordersForTab(orderProvider.orders, 3).length;
              final filteredOrders = _visibleOrders(orderProvider.orders);

              return Column(
                children: [
                  _buildHeader(),
                  _buildTabs(
                    allCount: allCount,
                    ongoingCount: ongoingCount,
                    completedCount: completedCount,
                    cancelledCount: cancelledCount,
                  ),
                  _buildSearchRow(),
                  Expanded(
                    child: _isLoading || orderProvider.isLoading
                        ? _buildLoadingState(_primary)
                        : filteredOrders.isEmpty
                            ? _buildEmptyState(
                                tabIndex: _tabController.index,
                                primary: _primary,
                              )
                            : RefreshIndicator(
                                onRefresh: _refreshOrders,
                                color: _primary,
                                child: ListView.builder(
                                  physics: const BouncingScrollPhysics(
                                    parent: AlwaysScrollableScrollPhysics(),
                                  ),
                                  padding: const EdgeInsets.fromLTRB(
                                    16,
                                    16,
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
      ),
    );
  }

  Widget _buildHeader() {
    return SizedBox(
      height: 68,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 6),
        child: Stack(
          alignment: Alignment.center,
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: IconButton(
                onPressed: _handleBack,
                icon: const Icon(Icons.arrow_back_rounded),
                iconSize: 28,
                color: Colors.black,
                tooltip: 'Back',
              ),
            ),
            const Text(
              'My Orders',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: Colors.black,
                fontSize: 20,
                height: 1.05,
                fontWeight: FontWeight.w900,
              ),
            ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: () => Navigator.pushNamed(context, '/support'),
                icon: const Icon(Icons.support_agent_rounded, size: 23),
                label: const Text('Help'),
                style: TextButton.styleFrom(
                  foregroundColor: Colors.black,
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  textStyle: const TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _roundIconButton({
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.white,
      shape: const CircleBorder(),
      elevation: 10,
      shadowColor: Colors.black.withOpacity(0.16),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: 44,
          height: 44,
          child: Icon(icon, color: _text, size: 22),
        ),
      ),
    );
  }

  Widget _heroChip(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: _softLine),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: _text,
          fontSize: 11.5,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }

  Widget _gradientButton({
    required String label,
    required IconData icon,
    required VoidCallback onPressed,
    double? minWidth,
  }) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [_primary, _secondary],
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: _primary.withOpacity(0.24),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: SizedBox(
        height: 52,
        width: minWidth,
        child: FilledButton.icon(
          onPressed: onPressed,
          icon: Icon(icon, size: 18),
          label: Text(label),
          style: FilledButton.styleFrom(
            backgroundColor: Colors.transparent,
            foregroundColor: Colors.white,
            shadowColor: Colors.transparent,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
            ),
            textStyle: const TextStyle(
              fontWeight: FontWeight.w900,
              fontSize: 12.5,
            ),
          ),
        ),
      ),
    );
  }

  Widget _outlineActionButton({
    required String label,
    required IconData icon,
    required Color color,
    required VoidCallback onPressed,
  }) {
    return OutlinedButton.icon(
      onPressed: onPressed,
      icon: Icon(icon, color: color, size: 18),
      label: Text(
        label,
        style: TextStyle(color: color, fontWeight: FontWeight.w900),
      ),
      style: OutlinedButton.styleFrom(
        foregroundColor: color,
        side: BorderSide(color: color.withOpacity(0.22)),
        minimumSize: const Size.fromHeight(52),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
        ),
      ),
    );
  }

  Widget _buildTabs({
    required int allCount,
    required int ongoingCount,
    required int completedCount,
    required int cancelledCount,
  }) {
    return Container(
      height: 58,
      decoration: const BoxDecoration(
        border: Border(
          bottom: BorderSide(color: Color(0xFFEDEDED)),
        ),
      ),
      child: Row(
        children: [
          _tabText('All Orders', 0, allCount),
          _tabText('Ongoing', 1, ongoingCount),
          _tabText('Completed', 2, completedCount),
          _tabText('Cancelled', 3, cancelledCount),
        ],
      ),
    );
  }

  Widget _tabText(String label, int index, int count) {
    final selected = _tabController.index == index;
    return Expanded(
      child: InkWell(
        onTap: () => _tabController.animateTo(index),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.end,
          children: [
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: selected ? FontWeight.w900 : FontWeight.w700,
                color: selected ? _primary : const Color(0xFF6B6B73),
              ),
            ),
            const SizedBox(height: 15),
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              height: 2.5,
              width: selected ? 78 : 0,
              color: _primary,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchRow() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 12),
      child: Row(
        children: [
          Expanded(
            child: SizedBox(
              height: 52,
              child: TextField(
                controller: _searchController,
                onChanged: (_) => setState(() {}),
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  hintText: 'Search by restaurant or item',
                  hintStyle: const TextStyle(
                    color: Color(0xFF76767D),
                    fontSize: 12.5,
                    fontWeight: FontWeight.w500,
                  ),
                  prefixIcon: const Icon(
                    Icons.search_rounded,
                    color: Colors.black,
                    size: 27,
                  ),
                  suffixIcon: _searchController.text.isEmpty
                      ? null
                      : IconButton(
                          onPressed: () {
                            _searchController.clear();
                            setState(() {});
                          },
                          icon: const Icon(Icons.close_rounded),
                        ),
                  filled: true,
                  fillColor: Colors.white,
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE1E1E6)),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE1E1E6)),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: BorderSide(color: _primary, width: 1.2),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLoadingState(Color primary) {
    return Center(
      child: Container(
        margin: const EdgeInsets.all(22),
        padding: const EdgeInsets.fromLTRB(22, 26, 22, 24),
        decoration: _ordersPanelDecoration(context),
        child: Column(
          mainAxisSize: MainAxisSize.min,
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
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: _muted,
              ),
            ),
          ],
        ),
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
        child: Container(
          padding: const EdgeInsets.fromLTRB(22, 26, 22, 22),
          decoration: _ordersPanelDecoration(context),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              _OrdersFloatingIcon(icon: icon, color: primary, size: 72),
              const SizedBox(height: 18),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: _text,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                subtitle,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 12.5,
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                  color: _muted,
                ),
              ),
              if (tabIndex == 0) ...[
                const SizedBox(height: 22),
                _gradientButton(
                  label: 'Browse Restaurants',
                  icon: Icons.restaurant_menu_rounded,
                  onPressed: () =>
                      Navigator.pushNamed(context, '/customer/home'),
                  minWidth: 190,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildOrderCard(BuildContext context, Order order) {
    final isLive = !order.isDelivered && !order.isCancelled;
    final restaurantName = order.restaurant?.name ?? 'Restaurant';
    final itemCount =
        order.items.fold<int>(0, (total, item) => total + item.quantity);
    final actionLabel = order.isDelivered
        ? 'Reorder'
        : isLive
            ? 'Track Order'
            : 'View Details';

    return GestureDetector(
      onTap: () => _openOrder(order),
      child: Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE8E8EE)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.055),
              blurRadius: 18,
              offset: const Offset(0, 7),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _statusChip(order),
                const Spacer(),
                Text(
                  _formatOrderTime(order.createdAt),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF62636A),
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(width: 8),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: Color(0xFF56575E),
                  size: 25,
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _restaurantThumb(order),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        restaurantName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.black,
                          fontSize: 14.5,
                          height: 1.1,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Order ID: #${order.orderNumber}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF5E5F66),
                          fontSize: 12.5,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        '${itemCount == 1 ? '1 Item' : '$itemCount Items'}  -  ${formatCurrency(context, order.total)}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF3E3F46),
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: Color(0xFF56575E),
                  size: 25,
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Expanded(child: _orderTimelineLine(order)),
                const SizedBox(width: 12),
                _orderActionButton(
                  label: actionLabel,
                  filled: isLive,
                  onPressed: () {
                    if (order.isDelivered) {
                      _reorderItems(order);
                    } else {
                      _openOrder(order);
                    }
                  },
                ),
              ],
            ),
            if (order.canCancel || order.canRequestRefund) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  if (order.canCancel)
                    TextButton(
                      onPressed: () => _showCancelOrderDialog(order),
                      child: const Text('Cancel Order'),
                    ),
                  if (order.canRequestRefund)
                    TextButton(
                      onPressed: () => _showRefundRequestDialog(order),
                      child: const Text('Request Refund'),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  void _openOrder(Order order) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => OrderTrackingScreen(orderId: order.id),
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
      width: 74,
      height: 74,
      decoration: BoxDecoration(
        color: const Color(0xFFF5F5F5),
        borderRadius: BorderRadius.circular(13),
      ),
      clipBehavior: Clip.antiAlias,
      child: logoUrl.isNotEmpty
          ? AppCachedImage(
              imageUrl: logoUrl,
              fit: BoxFit.cover,
              width: 74,
              height: 74,
              errorBuilder: (_, __, ___) => _restaurantThumbFallback(order),
            )
          : _restaurantThumbFallback(order),
    );
  }

  Widget _restaurantThumbFallback(Order order) {
    final name = (order.restaurant?.name ?? 'Restaurant').trim();
    final words = name.split(RegExp(r'\s+')).where((word) => word.isNotEmpty);
    final label =
        words.take(2).map((word) => word.substring(0, 1).toUpperCase()).join();
    return Container(
      color: _primary.withOpacity(0.10),
      alignment: Alignment.center,
      child: Text(
        label.isEmpty ? 'FOOD' : label,
        textAlign: TextAlign.center,
        style: TextStyle(
          color: _primary,
          fontSize: label.length <= 2 ? 18 : 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }

  Widget _statusChip(Order order) {
    final style = _statusStyle(order);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: style.background,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        style.label,
        style: TextStyle(
          color: style.foreground,
          fontSize: 11.5,
          fontWeight: FontWeight.w900,
          letterSpacing: 0.2,
        ),
      ),
    );
  }

  Widget _orderTimelineLine(Order order) {
    final style = _statusStyle(order);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(style.icon, color: style.foreground, size: 18),
            const SizedBox(width: 7),
            Expanded(
              child: Text(
                _orderPrimaryLine(order),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: style.foreground,
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ),
        if (_orderSecondaryLine(order).isNotEmpty) ...[
          const SizedBox(height: 6),
          Padding(
            padding: const EdgeInsets.only(left: 25),
            child: Text(
              _orderSecondaryLine(order),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Color(0xFF67686F),
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ],
    );
  }

  Widget _orderActionButton({
    required String label,
    required bool filled,
    required VoidCallback onPressed,
  }) {
    return SizedBox(
      height: 42,
      child: filled
          ? FilledButton(
              onPressed: onPressed,
              style: FilledButton.styleFrom(
                backgroundColor: _primary.withOpacity(0.13),
                foregroundColor: _primary,
                elevation: 0,
                padding: const EdgeInsets.symmetric(horizontal: 18),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              child: Text(label,
                  style: const TextStyle(fontWeight: FontWeight.w900)),
            )
          : OutlinedButton(
              onPressed: onPressed,
              style: OutlinedButton.styleFrom(
                foregroundColor: _primary,
                side: BorderSide(color: _primary),
                padding: const EdgeInsets.symmetric(horizontal: 18),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              child: Text(label,
                  style: const TextStyle(fontWeight: FontWeight.w900)),
            ),
    );
  }

  _OrderStatusStyle _statusStyle(Order order) {
    if (order.isCancelled) {
      return const _OrderStatusStyle(
        label: 'CANCELLED',
        foreground: Color(0xFFE11D28),
        background: Color(0xFFFFECEC),
        icon: Icons.cancel_outlined,
      );
    }
    if (order.isDelivered) {
      return const _OrderStatusStyle(
        label: 'DELIVERED',
        foreground: Color(0xFF169B3A),
        background: Color(0xFFE8F8EA),
        icon: Icons.check_circle_outline_rounded,
      );
    }
    if (order.isOnTheWay || order.isPickedUp) {
      return const _OrderStatusStyle(
        label: 'LIVE',
        foreground: Color(0xFF11832D),
        background: Color(0xFFE6F7E7),
        icon: Icons.schedule_rounded,
      );
    }
    return const _OrderStatusStyle(
      label: 'CONFIRMED',
      foreground: Color(0xFF2563EB),
      background: Color(0xFFEAF2FF),
      icon: Icons.access_time_rounded,
    );
  }

  String _orderPrimaryLine(Order order) {
    if (order.isCancelled) return 'Cancelled';
    if (order.isDelivered) return 'Delivered on time';
    if (order.isOnTheWay || order.isPickedUp) {
      final eta = order.etaRange ??
          (order.etaMinutes == null
              ? null
              : '${order.etaMinutes}-${order.etaMinutes! + 5} mins');
      return eta == null ? 'Order on the way' : 'Arriving in $eta';
    }
    final eta = order.etaRange ??
        (order.etaMinutes == null
            ? null
            : '${order.etaMinutes}-${order.etaMinutes! + 5} mins');
    return eta == null ? 'Estimated delivery' : 'Estimated delivery: $eta';
  }

  String _orderSecondaryLine(Order order) {
    if (order.isCancelled) return order.cancellationReason ?? '';
    if (order.isDelivered) return '';
    if (order.isOnTheWay || order.isPickedUp) return 'Your order is on the way';
    return order.statusText;
  }

  Widget _metaPill({
    required IconData icon,
    required String label,
  }) {
    return const SizedBox.shrink();
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
                        color: FoodFlowTheme.danger.withOpacity(0.10),
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: const Icon(
                        Icons.close_rounded,
                        color: FoodFlowTheme.dangerDark,
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
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                              color: _text,
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'You can cancel only before the restaurant accepts it.',
                            style: TextStyle(
                              fontSize: 11.5,
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
                    color: FoodFlowTheme.surfaceWarm,
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: _softLine),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Order #${order.orderNumber}',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: _text,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        order.restaurant?.name ?? 'Restaurant',
                        style: const TextStyle(
                          fontSize: 12.5,
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
                        color: FoodFlowTheme.dangerDark,
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
                                backgroundColor: FoodFlowTheme.danger,
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
                                backgroundColor: FoodFlowTheme.success,
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
                                backgroundColor: FoodFlowTheme.danger,
                              ),
                            );
                          }
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: FoodFlowTheme.dangerDark,
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
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                              color: _text,
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'Share the issue and optionally suggest a refund amount.',
                            style: TextStyle(
                              fontSize: 11.5,
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
                                backgroundColor: FoodFlowTheme.danger,
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
                                backgroundColor: FoodFlowTheme.success,
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
                                backgroundColor: FoodFlowTheme.danger,
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
          backgroundColor: FoodFlowTheme.success,
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

  String _formatOrderTime(DateTime date) {
    final now = DateTime.now();
    final time = _formatClock(date);
    final today = DateTime(now.year, now.month, now.day);
    final orderDay = DateTime(date.year, date.month, date.day);
    final diff = today.difference(orderDay).inDays;
    if (diff == 0) return 'Today, $time';
    if (diff == 1) return 'Yesterday, $time';
    return '${date.day} ${_monthName(date.month)}, $time';
  }

  String _formatClock(DateTime date) {
    final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
    final minute = date.minute.toString().padLeft(2, '0');
    final suffix = date.hour >= 12 ? 'PM' : 'AM';
    return '$hour:$minute $suffix';
  }

  String _monthName(int month) {
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    return months[(month - 1).clamp(0, 11)];
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  }
}

class _OrderStatusStyle {
  const _OrderStatusStyle({
    required this.label,
    required this.foreground,
    required this.background,
    required this.icon,
  });

  final String label;
  final Color foreground;
  final Color background;
  final IconData icon;
}

class _OrdersFloatingIcon extends StatelessWidget {
  const _OrdersFloatingIcon({
    required this.icon,
    required this.color,
    this.size = 54,
  });

  final IconData icon;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.18),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Icon(icon, color: color, size: 28),
    );
  }
}

class _OrdersHeroPainter extends CustomPainter {
  const _OrdersHeroPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final wash = Paint()
      ..color = color.withOpacity(0.045)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(Offset(size.width * 0.88, size.height * 0.45), 120, wash);

    final dotPaint = Paint()
      ..color = color.withOpacity(0.06)
      ..style = PaintingStyle.fill;
    for (var row = 0; row < 7; row++) {
      for (var col = 0; col < 3; col++) {
        canvas.drawCircle(Offset(4 + col * 20.0, 34 + row * 20.0), 4, dotPaint);
      }
    }

    final linePaint = Paint()
      ..color = color.withOpacity(0.13)
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;
    final baseY = size.height * 0.82;
    canvas.drawLine(
      Offset(size.width * 0.46, baseY),
      Offset(size.width * 0.98, baseY),
      linePaint,
    );
    _drawCloud(canvas, linePaint, Offset(size.width * 0.68, 58), 22);
    _drawCloud(canvas, linePaint, Offset(size.width * 0.88, 38), 28);
    _drawCloud(canvas, linePaint, Offset(size.width * 0.54, 110), 18);
  }

  void _drawCloud(Canvas canvas, Paint paint, Offset center, double width) {
    final path = Path()
      ..moveTo(center.dx - width * 0.50, center.dy)
      ..quadraticBezierTo(center.dx - width * 0.28, center.dy - width * 0.22,
          center.dx - width * 0.08, center.dy - width * 0.06)
      ..quadraticBezierTo(center.dx + width * 0.08, center.dy - width * 0.36,
          center.dx + width * 0.30, center.dy - width * 0.08)
      ..quadraticBezierTo(center.dx + width * 0.48, center.dy - width * 0.06,
          center.dx + width * 0.55, center.dy);
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _OrdersHeroPainter oldDelegate) {
    return oldDelegate.color != color;
  }
}

BoxDecoration _ordersPanelDecoration(BuildContext context,
    {double radius = 28}) {
  return BoxDecoration(
    gradient: const LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [
        FoodFlowTheme.surfaceColor,
        FoodFlowTheme.surfaceWarm,
        FoodFlowTheme.surfaceCool,
      ],
      stops: [0, 0.56, 1],
    ),
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: _OrdersScreenState._softLine),
    boxShadow: [
      BoxShadow(
        color: Colors.white.withOpacity(0.95),
        blurRadius: 3,
        offset: const Offset(-2, -2),
      ),
      BoxShadow(
        color: FoodFlowTheme.brandPrimary(context).withOpacity(0.14),
        blurRadius: 22,
        spreadRadius: -3,
        offset: const Offset(0, 14),
      ),
      BoxShadow(
        color: Colors.black.withOpacity(0.07),
        blurRadius: 26,
        spreadRadius: -4,
        offset: const Offset(0, 16),
      ),
    ],
  );
}
