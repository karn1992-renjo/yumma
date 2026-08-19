import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../providers/restaurant_provider.dart';
import '../../services/app_order_overlay_service.dart';
import '../../services/sound_service.dart';
import '../../services/websocket_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/restaurant/reject_order_dialog.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';
import 'restaurant_orders_screen.dart';
import 'restaurant_menu_screen.dart';
import 'restaurant_analytics_screen.dart';
import 'restaurant_settings_screen.dart';

class RestaurantHomeScreen extends StatefulWidget {
  const RestaurantHomeScreen({super.key});

  @override
  State<RestaurantHomeScreen> createState() => _RestaurantHomeScreenState();
}

class _RestaurantHomeScreenState extends State<RestaurantHomeScreen>
    with WidgetsBindingObserver {
  int _currentIndex = 0;
  bool _isWebSocketInitialized = false;

  final List<Widget> _screens = [
    const DashboardContent(),
    const RestaurantOrdersScreen(),
    const RestaurantMenuScreen(),
    const RestaurantAnalyticsScreen(),
    const RestaurantSettingsScreen(),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initialize();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _loadData();
    }
  }

  Future<void> _initialize() async {
    await _loadData();
    await _initWebSocket();
  }

  Future<void> _loadData() async {
    final restaurantProvider =
        Provider.of<RestaurantProvider>(context, listen: false);
    await restaurantProvider.loadDashboardData();
  }

  Future<void> _initWebSocket() async {
    if (_isWebSocketInitialized) return;

    final restaurantProvider =
        Provider.of<RestaurantProvider>(context, listen: false);

    final restaurantId = _parseId(restaurantProvider.restaurant?['id']);

    if (restaurantId != null) {
      try {
        await WebSocketService().initRestaurant(
          restaurantId,
          onNewOrder: (order) {
            restaurantProvider.addNewOrder(order);
            _showNewOrderNotification(order);
          },
          onOrderUpdate: (order) {
            restaurantProvider.updateOrder(order);
            SoundService.playOrderAcceptedSound();
          },
        );
        _isWebSocketInitialized = true;
      } catch (e) {
        debugPrint('WebSocket init error: $e');
      }
    }
  }

  int? _parseId(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value);
    return null;
  }

  void _showNewOrderNotification(Map<String, dynamic> order) {
    HapticFeedback.heavyImpact();
    final restaurantProvider = Provider.of<RestaurantProvider>(
      context,
      listen: false,
    );

    AppOrderOverlayService.showRestaurantOrder(
      order,
      onAccept: (orderId, minutes) => restaurantProvider.acceptOrder(
        orderId,
        preparationTimeMinutes: minutes,
      ),
      onReject: (orderId, reason) => restaurantProvider.rejectOrder(
        orderId,
        reason,
      ),
      onViewDetails: () => setState(() => _currentIndex = 1),
    );
  }

  Future<void> _acceptOrder(int orderId) async {
    final restaurantProvider =
        Provider.of<RestaurantProvider>(context, listen: false);
    final success = await restaurantProvider.acceptOrder(orderId);

    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Order accepted!')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) => setState(() => _currentIndex = index),
        type: BottomNavigationBarType.fixed,
        items: const [
          BottomNavigationBarItem(
              icon: Icon(Icons.dashboard_outlined), label: 'Dashboard'),
          BottomNavigationBarItem(
              icon: Icon(Icons.receipt_outlined), label: 'Orders'),
          BottomNavigationBarItem(
              icon: Icon(Icons.menu_book_outlined), label: 'Menu'),
          BottomNavigationBarItem(
              icon: Icon(Icons.analytics_outlined), label: 'Analytics'),
          BottomNavigationBarItem(
              icon: Icon(Icons.settings_outlined), label: 'Settings'),
        ],
      ),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    WebSocketService().dispose();
    SoundService.stopIncomingOrderAlarm();
    super.dispose();
  }
}

class DashboardContent extends StatefulWidget {
  const DashboardContent({super.key});

  @override
  State<DashboardContent> createState() => _DashboardContentState();
}

class _DashboardContentState extends State<DashboardContent> {
  @override
  Widget build(BuildContext context) {
    final restaurantProvider = Provider.of<RestaurantProvider>(context);

    if (restaurantProvider.isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: () => restaurantProvider.loadDashboardData(),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Hello, ${restaurantProvider.restaurant?['name'] ?? 'Restaurant'}!',
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 20,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Here\'s what\'s happening today',
              style: TextStyle(
                color: FoodFlowTheme.muted,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 24),

            // Stats Grid
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 2,
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
              childAspectRatio: 1.5,
              children: [
                _buildStatCard(
                    'Today\'s Orders',
                    '${restaurantProvider.todayOrders}',
                    Icons.receipt,
                    Colors.blue),
                _buildStatCard(
                    'Today\'s Revenue',
                    formatCurrencyValue(context, restaurantProvider.todayRevenue),
                    Icons.currency_rupee,
                    FoodFlowTheme.success),
                _buildStatCard(
                    'Total Orders',
                    '${restaurantProvider.totalOrders}',
                    Icons.receipt_long,
                    FoodFlowTheme.crimson),
                _buildStatCard(
                    'Customers',
                    '${restaurantProvider.totalCustomers}',
                    Icons.people,
                    Colors.purple),
              ],
            ),
            const SizedBox(height: 24),

            // Status Toggle
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: FoodFlowTheme.line),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Restaurant Status',
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Switch(
                    value: restaurantProvider.isOpen ?? false,
                    onChanged: (_) async {
                      try {
                        await restaurantProvider.toggleRestaurantStatus();
                      } catch (e) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Error: $e')),
                        );
                      }
                    },
                    activeColor: FoodFlowTheme.orange,
                  ),
                ],
              ),
            ),

            if (restaurantProvider.pendingOrders.isNotEmpty) ...[
              const SizedBox(height: 24),
              const Text(
                'Pending Orders',
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 12),
              ...restaurantProvider.pendingOrders
                  .take(3)
                  .map((order) => _buildPendingOrderCard(order)),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildStatCard(
      String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: RestaurantPremium.panel(radius: 16),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(height: 8),
          Text(
            value,
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 12,
              color: FoodFlowTheme.muted,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPendingOrderCard(dynamic order) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: RestaurantPremium.panel(radius: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '#${order['order_number']}',
                style: const TextStyle(
                  color: FoodFlowTheme.ink,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                    color: FoodFlowTheme.crimson.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(12)),
                child: const Text('Pending',
                    style: TextStyle(color: FoodFlowTheme.crimson, fontSize: 12)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text('${formatCurrencyValue(context, order['total'])} • ${order['items_count']} items'),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => _showRejectDialog(order['id']),
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                  child: const Text('Reject'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: ElevatedButton(
                  onPressed: () => _acceptOrder(order['id']),
                  child: const Text('Accept'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _acceptOrder(int orderId) async {
    final restaurantProvider =
        Provider.of<RestaurantProvider>(context, listen: false);
    await restaurantProvider.acceptOrder(orderId);
    ScaffoldMessenger.of(context)
        .showSnackBar(const SnackBar(content: Text('Order accepted')));
  }

  Future<void> _showRejectDialog(int orderId) async {
    final reason = await showRestaurantRejectOrderDialog(context);
    if (reason == null || !mounted) return;

    final restaurantProvider =
        Provider.of<RestaurantProvider>(context, listen: false);
    await restaurantProvider.rejectOrder(orderId, reason);
    if (!mounted) return;

    ScaffoldMessenger.of(context)
        .showSnackBar(const SnackBar(content: Text('Order rejected')));
  }
}
