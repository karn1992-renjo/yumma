// lib/screens/restaurant/restaurant_dashboard.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/app_config.dart';
import '../../config/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../providers/restaurant_provider.dart';
import '../../services/api_service.dart';
import '../../services/incoming_order_alert_service.dart';
import '../../services/order_alert_permission_manager.dart';
import '../../services/restaurant_order_realtime_service.dart';
import '../../services/sound_service.dart';
import '../../services/websocket_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/common/network_image_loader.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';
import '../../widgets/restaurant/reject_order_dialog.dart';
import '../../utils/route_observer.dart';
import 'restaurant_analytics_screen.dart';
import 'restaurant_wallet_screen.dart';
import 'restaurant_settings_screen.dart';
import 'restaurant_orders_screen.dart';
import 'restaurant_menu_screen.dart';
import 'restaurant_promos_screen.dart';
import 'restaurant_printers_screen.dart';
import 'restaurant_info_screen.dart';
import 'staff_management_screen.dart';
import 'restaurant_dining_screen.dart';
import 'restaurant_driver_tracking_screen.dart';
import 'restaurant_notifications_screen.dart';
import 'profile/restaurant_profile_screen.dart';

class RestaurantDashboard extends StatefulWidget {
  const RestaurantDashboard({super.key});

  @override
  State<RestaurantDashboard> createState() => _RestaurantDashboardState();
}

class _RestaurantDashboardState extends State<RestaurantDashboard> {
  final ApiService _api = ApiService();
  int _currentIndex = 0;
  bool _businessMode = false;
  bool _isWebSocketInitialized = false;
  bool _isInitializingWebSocket = false;
  bool _isPollingOrders = false;
  Timer? _orderPollingTimer;
  Timer? _webSocketRetryTimer;
  StreamSubscription<Map<String, dynamic>>? _providerOrderSubscription;
  final Set<int> _knownPendingOrderIds = {};
  int _unreadNotificationCount = 0;

  @override
  void initState() {
    super.initState();
    _providerOrderSubscription =
        RestaurantOrderRealtimeService.instance.updates.listen((order) {
      if (!mounted) return;
      final provider = context.read<RestaurantProvider>();
      if (_shouldReflectOrderInSelectedScope(order, provider)) {
        provider.updateOrder(order);
      }
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initializeRealtimeOrders();
      _loadUnreadNotificationCount();
    });
  }

  Future<void> _initializeRealtimeOrders() async {
    if (_isWebSocketInitialized || _isInitializingWebSocket) return;
    _isInitializingWebSocket = true;

    final restaurantProvider = Provider.of<RestaurantProvider>(
      context,
      listen: false,
    );
    _startOrderPollingFallback();
    unawaited(_pollForNewOrders());

    await restaurantProvider.loadRestaurants();

    final restaurantIds = restaurantProvider.restaurants
        .map((restaurant) => _parseId(restaurant['id']))
        .whereType<int>()
        .toSet()
        .toList();
    final fallbackRestaurantId = _parseId(restaurantProvider.restaurant?['id']);
    if (restaurantIds.isEmpty && fallbackRestaurantId != null) {
      restaurantIds.add(fallbackRestaurantId);
    }

    if (restaurantIds.isEmpty) {
      debugPrint('Restaurant websocket skipped: restaurant id missing.');
      _isInitializingWebSocket = false;
      _scheduleWebSocketRetry();
      return;
    }

    var allSubscriptionsStarted = true;
    for (final restaurantId in restaurantIds) {
      final subscriptionStarted = await WebSocketService().initRestaurant(
        restaurantId,
        onNewOrder: (order) {
          final orderId = _parseId(order['id'] ?? order['order_id']);
          if (orderId != null) _knownPendingOrderIds.add(orderId);
          if (_shouldReflectOrderInSelectedScope(order, restaurantProvider)) {
            restaurantProvider.addNewOrder(order);
          }
          _showNewOrderNotification(order);
        },
        onOrderUpdate: (order) {
          if (_shouldReflectOrderInSelectedScope(order, restaurantProvider)) {
            restaurantProvider.updateOrder(order);
          }
          SoundService.playOrderAcceptedSound();
        },
      );
      allSubscriptionsStarted = allSubscriptionsStarted && subscriptionStarted;
    }

    _isWebSocketInitialized = allSubscriptionsStarted;
    _isInitializingWebSocket = false;
    if (_isWebSocketInitialized) {
      _webSocketRetryTimer?.cancel();
    } else {
      _scheduleWebSocketRetry();
    }

    await restaurantProvider.loadDashboardData();
    _rememberPendingOrders(restaurantProvider.pendingOrders);
    unawaited(_pollForNewOrders());
  }

  void _scheduleWebSocketRetry() {
    _webSocketRetryTimer?.cancel();
    if (!mounted || _isWebSocketInitialized) return;
    _webSocketRetryTimer = Timer(const Duration(seconds: 5), () {
      if (mounted) unawaited(_initializeRealtimeOrders());
    });
  }

  void _startOrderPollingFallback() {
    _orderPollingTimer?.cancel();
    _orderPollingTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      _pollForNewOrders();
    });
  }

  Future<void> _pollForNewOrders() async {
    if (_isPollingOrders || !mounted) return;
    _isPollingOrders = true;

    try {
      final response = await _api.get(
        ApiConstants.restaurantDashboard,
        queryParams: const {'restaurant_id': 'all'},
      );
      if (!mounted) return;

      final pendingOrders = response['success'] == true
          ? (response['data']?['pending_orders'] as List? ?? const [])
          : const [];

      for (final rawOrder in pendingOrders) {
        if (rawOrder is! Map) continue;
        final order = Map<String, dynamic>.from(rawOrder);
        final orderId = _parseId(order['id'] ?? order['order_id']);
        if (orderId == null || _knownPendingOrderIds.contains(orderId)) {
          continue;
        }

        _knownPendingOrderIds.add(orderId);
        final restaurantProvider = Provider.of<RestaurantProvider>(
          context,
          listen: false,
        );
        if (_shouldReflectOrderInSelectedScope(order, restaurantProvider)) {
          restaurantProvider.addNewOrder(order);
        }
        _showNewOrderNotification(order);
        break;
      }
      _rememberPendingOrders(pendingOrders);
    } catch (e) {
      debugPrint('Restaurant order polling error: $e');
    } finally {
      _isPollingOrders = false;
    }
  }

  void _rememberPendingOrders(List<dynamic> orders) {
    for (final order in orders) {
      if (order is! Map) continue;
      final orderId = _parseId(order['id'] ?? order['order_id']);
      if (orderId != null) _knownPendingOrderIds.add(orderId);
    }
  }

  int? _parseId(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value);
    return null;
  }

  bool _shouldReflectOrderInSelectedScope(
    Map<String, dynamic> order,
    RestaurantProvider provider,
  ) {
    if (provider.isAllRestaurantsSelected) return true;
    final restaurantValue = order['restaurant'];
    final orderRestaurantId = _parseId(
      order['restaurant_id'] ??
          (restaurantValue is Map ? restaurantValue['id'] : null),
    );
    if (orderRestaurantId == null) {
      final orderId = _parseId(order['id'] ?? order['order_id']);
      if (orderId == null) return false;
      for (final existing in [
        ...provider.pendingOrders,
        ...provider.activeOrders,
      ]) {
        if (existing is! Map) continue;
        if (_parseId(existing['id'] ?? existing['order_id']) == orderId) {
          return true;
        }
      }
      return false;
    }
    return orderRestaurantId == provider.selectedRestaurantId;
  }

  void _showNewOrderNotification(Map<String, dynamic> order) {
    if (!mounted) return;

    setState(() => _unreadNotificationCount++);
    IncomingOrderAlertService.instance.handleIncomingOrderData({
      ...order,
      'role': 'restaurant',
      'type': 'new_order',
    }, source: IncomingOrderSource.websocket);
  }

  Future<void> _loadUnreadNotificationCount() async {
    try {
      final response = await _api.get(
        ApiConstants.notifications,
        queryParams: const {'limit': '1', 'target_app': 'restaurant'},
      );
      if (!mounted || response['success'] != true) return;
      final data = response['data'] as Map<String, dynamic>? ?? {};
      setState(() {
        _unreadNotificationCount =
            _parseId(data['unread_count']) ?? _unreadNotificationCount;
      });
    } catch (e) {
      debugPrint('Notification count load error: $e');
    }
  }

  @override
  void dispose() {
    _orderPollingTimer?.cancel();
    _webSocketRetryTimer?.cancel();
    _providerOrderSubscription?.cancel();
    WebSocketService().dispose();
    SoundService.stopIncomingOrderAlarm();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final user = authProvider.currentUser;
    final navItems = _buildNavItems(user);
    final effectiveIndex = _currentIndex >= navItems.length ? 0 : _currentIndex;

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      drawer: _buildDrawer(),
      body: SafeArea(
        bottom: false,
        child: IndexedStack(
          index: effectiveIndex,
          children: navItems.map((item) => item.screen).toList(),
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: EdgeInsets.zero,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            border: const Border(top: BorderSide(color: FoodFlowTheme.line)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.05),
                blurRadius: 16,
                offset: const Offset(0, -6),
              ),
            ],
          ),
          clipBehavior: Clip.antiAlias,
          child: BottomNavigationBarTheme(
            data: BottomNavigationBarThemeData(
              backgroundColor: Colors.white,
              selectedItemColor: _DashboardPalette.brand,
              unselectedItemColor: _DashboardPalette.muted,
              selectedLabelStyle: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
              unselectedLabelStyle: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
            child: BottomNavigationBar(
              currentIndex: effectiveIndex,
              onTap: (index) {
                final item = navItems[index];
                setState(() {
                  if (!_businessMode && item.key == 'business') {
                    _businessMode = true;
                    _currentIndex = 0;
                    return;
                  }

                  if (_businessMode && item.key == 'orders') {
                    _businessMode = false;
                    _currentIndex = 0;
                    return;
                  }

                  _currentIndex = index;
                });
              },
              elevation: 0,
              type: BottomNavigationBarType.fixed,
              backgroundColor: Colors.white,
              items: navItems.map((item) => item.navItem).toList(),
            ),
          ),
        ),
      ),
    );
  }

  List<_DashboardNavItem> _buildNavItems(user) {
    if (_businessMode) {
      final items = <_DashboardNavItem>[
        const _DashboardNavItem(
          key: 'dashboard',
          title: 'Dashboard',
          screen: _RestaurantBusinessScreen(),
          navItem: BottomNavigationBarItem(
            icon: Icon(Icons.dashboard_outlined),
            activeIcon: Icon(Icons.dashboard_rounded),
            label: 'Dashboard',
          ),
        ),
        if (user?.canViewReports ?? true)
          const _DashboardNavItem(
            key: 'reports',
            title: 'Reports',
            screen: RestaurantAnalyticsScreen(),
            navItem: BottomNavigationBarItem(
              icon: Icon(Icons.insert_chart_outlined_rounded),
              activeIcon: Icon(Icons.insert_chart_rounded),
              label: 'Reports',
            ),
          ),
        if (user?.isRestaurantOwner ?? true)
          const _DashboardNavItem(
            key: 'payouts',
            title: 'Payouts',
            screen: RestaurantWalletScreen(),
            navItem: BottomNavigationBarItem(
              icon: Icon(Icons.account_balance_wallet_outlined),
              activeIcon: Icon(Icons.account_balance_wallet_rounded),
              label: 'Payouts',
            ),
          ),
        if (user?.isRestaurantOwner ?? true)
          const _DashboardNavItem(
            key: 'growth',
            title: 'Growth',
            screen: RestaurantPromosScreen(),
            navItem: BottomNavigationBarItem(
              icon: Icon(Icons.rocket_launch_outlined),
              activeIcon: Icon(Icons.rocket_launch_rounded),
              label: 'Growth',
            ),
          ),
        const _DashboardNavItem(
          key: 'orders',
          title: 'Orders',
          screen: RestaurantHomeContent(),
          navItem: BottomNavigationBarItem(
            icon: Icon(Icons.room_service_outlined),
            activeIcon: Icon(Icons.room_service_rounded),
            label: 'Orders',
          ),
        ),
      ];

      return items;
    }

    final items = <_DashboardNavItem>[
      const _DashboardNavItem(
        key: 'orders',
        title: 'Orders',
        screen: RestaurantHomeContent(),
        navItem: BottomNavigationBarItem(
          icon: Icon(Icons.room_service_outlined),
          activeIcon: Icon(Icons.room_service_rounded),
          label: 'Orders',
        ),
      ),
    ];

    if (user?.canViewMenu ?? true) {
      items.add(
        const _DashboardNavItem(
          key: 'menu',
          title: 'Menu',
          screen: RestaurantMenuScreen(),
          navItem: BottomNavigationBarItem(
            icon: Icon(Icons.menu_book_outlined),
            activeIcon: Icon(Icons.menu_book_rounded),
            label: 'Menu',
          ),
        ),
      );
    }

    if (user?.canViewOrders ?? true) {
      items.add(
        const _DashboardNavItem(
          key: 'complaints',
          title: 'Complaints',
          screen: _RestaurantComplaintsScreen(),
          navItem: BottomNavigationBarItem(
            icon: Icon(Icons.warning_amber_rounded),
            activeIcon: Icon(Icons.warning_rounded),
            label: 'Complaints',
          ),
        ),
      );
    }

    if (user?.canViewReports ?? true) {
      items.add(
        const _DashboardNavItem(
          key: 'reviews',
          title: 'Reviews',
          screen: _RestaurantReviewsScreen(),
          navItem: BottomNavigationBarItem(
            icon: Icon(Icons.star_border_rounded),
            activeIcon: Icon(Icons.star_rounded),
            label: 'Reviews',
          ),
        ),
      );
    }

    items.add(
      const _DashboardNavItem(
        key: 'business',
        title: 'Business',
        screen: _RestaurantBusinessScreen(),
        navItem: BottomNavigationBarItem(
          icon: Icon(Icons.business_center_outlined),
          activeIcon: Icon(Icons.business_center_rounded),
          label: 'Business',
        ),
      ),
    );

    return items;
  }

  Widget _buildDrawer() {
    final user = Provider.of<AuthProvider>(context).currentUser;
    final restaurant = Provider.of<RestaurantProvider>(context).restaurant;
    final showDiningManagement = restaurant?['restaurant_type'] == 'both';

    return Drawer(
      backgroundColor: const Color(0xFFFFF8F3),
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(18, 54, 18, 18),
            decoration: BoxDecoration(
              gradient: FoodFlowTheme.brandGradient,
              borderRadius: const BorderRadius.only(
                bottomLeft: Radius.circular(26),
                bottomRight: Radius.circular(26),
              ),
              boxShadow: [
                BoxShadow(
                  color: FoodFlowTheme.orange.withOpacity(0.24),
                  blurRadius: 22,
                  offset: const Offset(0, 12),
                ),
              ],
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: Colors.white,
                  child: Text(
                    (user?.name.isNotEmpty == true
                        ? user!.name[0].toUpperCase()
                        : 'R'),
                    style: TextStyle(
                      fontSize: 20,
                      color: FoodFlowTheme.orange,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user?.name ?? 'Restaurant Owner',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        user?.restaurantAccessLabel ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.7),
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        user?.email ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.55),
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(14, 16, 14, 14),
              children: [
                const _DrawerSectionLabel('Account'),
                _DrawerMenuTile(
                  icon: Icons.person_outline,
                  title: 'Profile',
                  subtitle: 'Restaurant details, timings and account',
                  onTap: () {
                    Navigator.pop(context);
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const RestaurantProfileScreen(),
                      ),
                    );
                  },
                ),
                if (user?.isRestaurantOwner ?? true)
                  _DrawerMenuTile(
                    icon: Icons.storefront_outlined,
                    title: 'Restaurant Info',
                    onTap: () {
                      Navigator.pop(context);
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const RestaurantInfoScreen(),
                        ),
                      );
                    },
                  ),
                const _DrawerSectionLabel('Manage'),
                if (user?.canManageStaff ?? false)
                  _DrawerMenuTile(
                    icon: Icons.people_outline,
                    title: 'Staff Management',
                    onTap: () {
                      Navigator.pop(context);
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const StaffManagementScreen(),
                        ),
                      );
                    },
                  ),
                if (user?.isRestaurantOwner ?? true)
                  _DrawerMenuTile(
                    icon: Icons.local_offer_outlined,
                    title: 'Promotions',
                    subtitle: 'Create offers and track performance',
                    onTap: () {
                      Navigator.pop(context);
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const RestaurantPromosScreen(),
                        ),
                      );
                    },
                  ),
                if (showDiningManagement && (user?.canViewOrders ?? true))
                  _DrawerMenuTile(
                    icon: Icons.event_seat_outlined,
                    title: 'Dining Management',
                    subtitle: 'Bookings and table settings',
                    onTap: () {
                      Navigator.pop(context);
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const RestaurantDiningScreen(),
                        ),
                      );
                    },
                  ),
                if (user?.isRestaurantOwner ?? true)
                  _DrawerMenuTile(
                    icon: Icons.print_outlined,
                    title: 'Printers',
                    onTap: () {
                      Navigator.pop(context);
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const RestaurantPrintersScreen(),
                        ),
                      );
                    },
                  ),
                const _DrawerSectionLabel('Support'),
                _DrawerMenuTile(
                  icon: Icons.help_outline,
                  title: 'Help & Support',
                  onTap: () {
                    Navigator.pop(context);
                    Navigator.pushNamed(context, '/restaurant/profile/help');
                  },
                ),
                const SizedBox(height: 8),
                _DrawerMenuTile(
                  icon: Icons.logout,
                  title: 'Logout',
                  danger: true,
                  onTap: () async {
                    Navigator.pop(context);
                    await Provider.of<AuthProvider>(
                      context,
                      listen: false,
                    ).logout();
                    if (mounted) {
                      Navigator.of(
                        context,
                        rootNavigator: true,
                      ).pushNamedAndRemoveUntil('/login', (route) => false);
                    }
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DrawerSectionLabel extends StatelessWidget {
  const _DrawerSectionLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(6, 14, 6, 8),
      child: Text(
        label.toUpperCase(),
        style: const TextStyle(
          color: FoodFlowTheme.muted,
          fontSize: 11,
          fontWeight: FontWeight.w900,
          letterSpacing: 0,
        ),
      ),
    );
  }
}

class _DrawerMenuTile extends StatelessWidget {
  const _DrawerMenuTile({
    required this.icon,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.danger = false,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? Colors.red : FoodFlowTheme.ink;
    final iconColor = danger ? Colors.red : FoodFlowTheme.orange;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: FoodFlowTheme.line),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.035),
                  blurRadius: 12,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: iconColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: iconColor, size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: color,
                          fontSize: 14,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      if (subtitle != null) ...[
                        const SizedBox(height: 3),
                        Text(
                          subtitle!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: FoodFlowTheme.faint, size: 20),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class RestaurantHomeContent extends StatefulWidget {
  const RestaurantHomeContent({super.key});

  @override
  State<RestaurantHomeContent> createState() => _RestaurantHomeContentState();
}

class _RestaurantScopeSelector extends StatelessWidget {
  final int? selectedRestaurantId;
  final List<Map<String, dynamic>> restaurants;
  final ValueChanged<int?> onChanged;

  const _RestaurantScopeSelector({
    required this.selectedRestaurantId,
    required this.restaurants,
    required this.onChanged,
  });

  int? _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  String _logoUrl(Map<String, dynamic>? restaurant) {
    if (restaurant == null) return '';

    final raw = restaurant['logo_url'] ??
        restaurant['logo_image_url'] ??
        restaurant['logo_image'] ??
        restaurant['logo'] ??
        restaurant['image_url'];
    final value = raw?.toString().trim() ?? '';
    if (value.isEmpty || value == 'null') return '';
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }

    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final origin = '${apiUri.scheme}://${apiUri.host}';
    final normalized = value.startsWith('/') ? value.substring(1) : value;

    if (normalized.startsWith('storage/')) {
      return '$origin/$normalized';
    }

    return '$origin/storage/$normalized';
  }

  Widget _restaurantLogo(
    Map<String, dynamic>? restaurant, {
    double size = 28,
    Color? iconColor,
  }) {
    iconColor ??= FoodFlowTheme.orange;
    final url = _logoUrl(restaurant);
    final isOpen = restaurant?['is_open'] == true;
    final fallbackIcon = isOpen ? Icons.storefront : Icons.storefront_outlined;

    final radius = BorderRadius.circular(size * 0.32);
    final placeholder = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: _DashboardPalette.brandSoft,
        borderRadius: radius,
      ),
      child: Icon(fallbackIcon, color: iconColor, size: size * 0.62),
    );

    if (url.isEmpty) return placeholder;

    return NetworkImageLoader(
      imageUrl: url,
      width: size,
      height: size,
      borderRadius: radius,
    );
  }

  @override
  Widget build(BuildContext context) {
    final selectedMatches = restaurants.where(
      (item) => _asInt(item['id']) == selectedRestaurantId,
    );
    final selectedName = selectedRestaurantId == null
        ? 'All Stores'
        : selectedMatches.isNotEmpty
            ? selectedMatches.first['name']?.toString() ?? 'Store'
            : 'Store';
    final selectedRestaurant =
        selectedRestaurantId == null || selectedMatches.isEmpty
            ? null
            : selectedMatches.first;

    return PopupMenuButton<int>(
      tooltip: 'Select restaurant',
      onSelected: (value) => onChanged(value == -1 ? null : value),
      itemBuilder: (context) => [
        const PopupMenuItem<int>(
          value: -1,
          child: Row(
            children: [
              Icon(Icons.dashboard_customize_outlined),
              SizedBox(width: 10),
              Text('All Restaurants'),
            ],
          ),
        ),
        const PopupMenuDivider(),
        ...restaurants.map(
          (restaurant) => PopupMenuItem<int>(
            value: _asInt(restaurant['id']) ?? -1,
            child: Row(
              children: [
                _restaurantLogo(
                  restaurant,
                  size: 32,
                  iconColor: restaurant['is_open'] == true
                      ? FoodFlowTheme.success
                      : FoodFlowTheme.muted,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    restaurant['name']?.toString() ?? 'Restaurant',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
      child: Container(
        constraints: const BoxConstraints(maxWidth: 134, minHeight: 42),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: FoodFlowTheme.line),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            selectedRestaurantId == null
                ? Icon(
                    Icons.storefront_outlined,
                    color: _DashboardPalette.brand,
                    size: 19,
                  )
                : _restaurantLogo(selectedRestaurant, size: 22),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                selectedName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: _DashboardPalette.brand,
                  fontSize: 13,
                  height: 1.05,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            const SizedBox(width: 1),
            Icon(
              Icons.keyboard_arrow_down,
              color: _DashboardPalette.brand,
              size: 16,
            ),
          ],
        ),
      ),
    );
  }
}

class _RestaurantHomeContentState extends State<RestaurantHomeContent>
    with RouteAware {
  final ApiService _api = ApiService();
  late RestaurantProvider _restaurantProvider;
  bool _isProviderListenerAttached = false;
  bool _isRouteObserverSubscribed = false;
  int? _lastSyncedRestaurantId;
  StreamSubscription<Map<String, dynamic>>? _orderUpdateSubscription;

  Map<String, dynamic> _stats = {};
  List<dynamic> _recentOrders = [];
  List<dynamic> _runningOrders = [];
  bool _isLoading = true;
  String? _loadError;
  bool _isOpen = false;
  String _selectedOrderStage = 'preparing';
  double _orderStageDragDistance = 0;
  final Set<String> _expandedBillOrderIds = <String>{};
  static const List<String> _orderStages = [
    'preparing',
    'ready',
    'picked_up',
  ];

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return Map<String, dynamic>.from(value);
    return <String, dynamic>{};
  }

  List<dynamic> _asList(dynamic value) {
    if (value is List) return List<dynamic>.from(value);
    return <dynamic>[];
  }

  int? _valueAsInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  @override
  void initState() {
    super.initState();
    _orderUpdateSubscription =
        RestaurantOrderRealtimeService.instance.updates.listen(
      _onRealtimeOrderUpdate,
    );
    _loadData();
  }

  void _onRealtimeOrderUpdate(Map<String, dynamic> payload) {
    if (!mounted || !_matchesSelectedRestaurant(payload)) return;
    final orderId = _orderId(payload);
    if (orderId == null) return;

    Map<String, dynamic> existing = <String, dynamic>{};
    for (final raw in [..._recentOrders, ..._runningOrders]) {
      final candidate = _asOrderMap(raw);
      if (_orderId(candidate) == orderId) {
        existing = candidate;
        break;
      }
    }
    final merged = <String, dynamic>{...existing, ...payload};
    final status = merged['status']?.toString().toLowerCase() ?? '';

    bool matches(dynamic raw) => _orderId(_asOrderMap(raw)) == orderId;

    setState(() {
      _recentOrders.removeWhere(matches);
      _runningOrders.removeWhere(matches);
      if (status == 'pending') {
        _recentOrders.insert(0, merged);
      } else if (!const {
        'cancelled',
        'delivered',
        'completed',
        'rejected',
      }.contains(status)) {
        _runningOrders.insert(0, merged);
      }
      _stats['pending_orders_count'] = _recentOrders.length;
    });
  }

  bool _matchesSelectedRestaurant(Map<String, dynamic> payload) {
    if (!_isProviderListenerAttached ||
        _restaurantProvider.selectedRestaurantId == null) {
      return true;
    }
    final restaurant = payload['restaurant'];
    final restaurantId = _valueAsInt(
      payload['restaurant_id'] ?? (restaurant is Map ? restaurant['id'] : null),
    );
    if (restaurantId != null) {
      return restaurantId == _restaurantProvider.selectedRestaurantId;
    }
    final id = _orderId(payload);
    return id != null &&
        [..._recentOrders, ..._runningOrders]
            .map(_asOrderMap)
            .any((order) => _orderId(order) == id);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_isProviderListenerAttached) {
      _restaurantProvider = Provider.of<RestaurantProvider>(
        context,
        listen: false,
      );
      _restaurantProvider.addListener(_onRestaurantScopeChanged);
      _isProviderListenerAttached = true;
    }

    if (!_isRouteObserverSubscribed) {
      final route = ModalRoute.of(context);
      if (route is PageRoute) {
        routeObserver.subscribe(this, route);
        _isRouteObserverSubscribed = true;
      }
    }
  }

  void _onRestaurantScopeChanged() {
    if (!mounted) return;
    if (_lastSyncedRestaurantId != _restaurantProvider.selectedRestaurantId) {
      _lastSyncedRestaurantId = _restaurantProvider.selectedRestaurantId;
      _loadData();
      return;
    }
    _syncFromProvider();
  }

  void _syncFromProvider() {
    if (!mounted) return;
    setState(() {
      _stats = _restaurantProvider.stats;
      _recentOrders = List<dynamic>.from(_restaurantProvider.pendingOrders);
      _runningOrders = List<dynamic>.from(_restaurantProvider.activeOrders);
      _isOpen = _restaurantProvider.isOpen ?? _stats['is_open'] == true;
      _isLoading = false;
    });
  }

  @override
  void dispose() {
    _orderUpdateSubscription?.cancel();
    if (_isProviderListenerAttached) {
      _restaurantProvider.removeListener(_onRestaurantScopeChanged);
    }
    if (_isRouteObserverSubscribed) {
      routeObserver.unsubscribe(this);
    }
    super.dispose();
  }

  @override
  void didPopNext() {
    super.didPopNext();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);

    try {
      final provider = Provider.of<RestaurantProvider>(context, listen: false);
      _lastSyncedRestaurantId = provider.selectedRestaurantId;
      final response = await _api.get(
        ApiConstants.restaurantDashboard,
        queryParams: {
          'restaurant_id': provider.selectedRestaurantId?.toString() ?? 'all',
        },
      );
      if (response['success'] == true) {
        final data = _asMap(response['data']);
        setState(() {
          _stats = _asMap(data['stats']);
          _recentOrders = _asList(data['pending_orders']);
          _runningOrders = _asList(data['active_orders']);
          _isOpen = _stats['is_open'] == true;
          _loadError = null;
          _isLoading = false;
        });
        await provider.loadDashboardData();
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      debugPrint('Load dashboard error: $e');
      if (mounted) {
        setState(() {
          _loadError = _cleanApiError(e);
          _isLoading = false;
        });
      }
    }
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

  Future<bool> _ensureBatteryOptimizationDisabled() async {
    final disabled =
        await OrderAlertPermissionManager.isBatteryOptimizationDisabled();
    if (disabled) return true;

    if (!mounted) return false;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Allow unrestricted battery usage before opening for orders.',
        ),
      ),
    );
    await OrderAlertPermissionManager.requestBatteryOptimizationExemption();
    return false;
  }

  Future<void> _toggleRestaurantStatus() async {
    try {
      final provider = Provider.of<RestaurantProvider>(context, listen: false);
      if (provider.isAllRestaurantsSelected) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Select one restaurant to change open/closed status'),
          ),
        );
        return;
      }

      if (!_isOpen && !await _ensureBatteryOptimizationDisabled()) {
        return;
      }

      final response = await _api.post(
        '/restaurant/toggle-status',
        queryParams: {
          'restaurant_id': provider.selectedRestaurantId.toString(),
        },
      );
      if (response['success'] == true) {
        setState(() {
          _isOpen = !_isOpen;
          _stats['is_open'] = _isOpen;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              _isOpen ? 'Restaurant is now open' : 'Restaurant is now closed',
            ),
            backgroundColor:
                _isOpen ? FoodFlowTheme.success : FoodFlowTheme.danger,
          ),
        );
      }
    } catch (e) {
      debugPrint('Toggle status error: $e');
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Failed to toggle status: $e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_loadError != null && _stats.isEmpty) {
      return NetworkErrorView(message: _loadError, onRetry: _loadData);
    }

    final provider = Provider.of<RestaurantProvider>(context);
    final stageOrders = _ordersForStage(_selectedOrderStage);
    final totalActionable = _actionableOrders().length;

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onHorizontalDragStart: (_) => _orderStageDragDistance = 0,
      onHorizontalDragUpdate: (details) {
        _orderStageDragDistance += details.delta.dx;
      },
      onHorizontalDragEnd: _handleOrderStageSwipe,
      onHorizontalDragCancel: () => _orderStageDragDistance = 0,
      child: RefreshIndicator(
        onRefresh: _loadData,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: _OrdersHomeHeader(
                restaurants: provider.restaurants,
                selectedRestaurantId: provider.selectedRestaurantId,
                onMenu: () => Scaffold.of(context).openDrawer(),
              ),
            ),
            SliverPersistentHeader(
              pinned: true,
              delegate: _OrderStageTabsHeader(
                selected: _selectedOrderStage,
                counts: {
                  'preparing': _ordersForStage('preparing').length,
                  'ready': _ordersForStage('ready').length,
                  'picked_up': _ordersForStage('picked_up').length,
                },
                onSelected: _selectOrderStage,
              ),
            ),
            if (stageOrders.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: _OrdersHomeEmptyState(
                  hasAnyActionableOrder: totalActionable > 0,
                  selectedStage: _selectedOrderStage,
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(14, 16, 14, 22),
                sliver: SliverList.separated(
                  itemCount: stageOrders.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 16),
                  itemBuilder: (context, index) {
                    final order = stageOrders[index];
                    return _OrdersHomeCard(
                      order: order,
                      expandedBill: _expandedBillOrderIds.contains(
                        _orderIdentity(order),
                      ),
                      onToggleBill: () {
                        final id = _orderIdentity(order);
                        setState(() {
                          if (_expandedBillOrderIds.contains(id)) {
                            _expandedBillOrderIds.remove(id);
                          } else {
                            _expandedBillOrderIds.add(id);
                          }
                        });
                      },
                      onAccept: () => _acceptOrder(order),
                      onReject: () => _rejectOrder(order),
                      onMarkReady: () => _markOrderReady(order),
                      onDetails: () => Navigator.pushNamed(
                        context,
                        '/restaurant/order',
                        arguments: order['id'],
                      ),
                      onHelp: () => Navigator.pushNamed(
                        context,
                        '/restaurant/profile/help',
                      ),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }

  void _selectOrderStage(String stage) {
    if (stage == _selectedOrderStage || !_orderStages.contains(stage)) return;
    setState(() => _selectedOrderStage = stage);
  }

  void _handleOrderStageSwipe(DragEndDetails details) {
    final velocity = details.primaryVelocity ?? 0;
    final drag = _orderStageDragDistance;
    _orderStageDragDistance = 0;
    if (drag.abs() < 56 && velocity.abs() < 420) return;

    final currentIndex = _orderStages.indexOf(_selectedOrderStage);
    final direction =
        drag.abs() >= 56 ? (drag < 0 ? 1 : -1) : (velocity < 0 ? 1 : -1);
    final nextIndex =
        (currentIndex + direction).clamp(0, _orderStages.length - 1);
    if (nextIndex == currentIndex) return;
    _selectOrderStage(_orderStages[nextIndex]);
  }

  int? _orderId(Map<String, dynamic> order) {
    final value = order['id'] ?? order['order_id'];
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  Future<void> _acceptOrder(Map<String, dynamic> order) async {
    final id = _orderId(order);
    if (id == null) return;
    final provider = context.read<RestaurantProvider>();
    final success = await provider.acceptOrder(id);
    if (!mounted) return;
    if (success) _syncFromProvider();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
          content: Text(success ? 'Order accepted' : 'Could not accept order')),
    );
  }

  Future<void> _rejectOrder(Map<String, dynamic> order) async {
    final id = _orderId(order);
    if (id == null) return;
    final reason = await showRestaurantRejectOrderDialog(context);
    if (reason == null || !mounted) return;
    final provider = context.read<RestaurantProvider>();
    final success = await provider.rejectOrder(id, reason);
    if (!mounted) return;
    if (success) _syncFromProvider();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
          content: Text(success ? 'Order rejected' : 'Could not reject order')),
    );
  }

  Future<void> _markOrderReady(Map<String, dynamic> order) async {
    final id = _orderId(order);
    if (id == null) return;
    final provider = context.read<RestaurantProvider>();
    final success = await provider.markOrderReady(id);
    if (!mounted) return;
    if (success) _syncFromProvider();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
          content:
              Text(success ? 'Order marked ready' : 'Could not update order')),
    );
  }

  List<Map<String, dynamic>> _ordersForStage(String stage) {
    return _actionableOrders().where((order) {
      final status = order['status']?.toString() ?? '';
      switch (stage) {
        case 'ready':
          return status == 'ready_for_pickup' || status == 'reached_pickup';
        case 'picked_up':
          return status == 'picked_up' || status == 'on_the_way';
        case 'preparing':
        default:
          return status == 'pending' ||
              status == 'accepted' ||
              status == 'confirmed' ||
              status == 'preparing';
      }
    }).toList();
  }

  List<Map<String, dynamic>> _actionableOrders() {
    final seen = <String>{};
    final orders = <Map<String, dynamic>>[];
    for (final raw in [..._recentOrders, ..._runningOrders]) {
      final order = _asOrderMap(raw);
      if (order.isEmpty) continue;
      final id = _orderIdentity(order);
      if (!seen.add(id)) continue;
      final status = order['status']?.toString() ?? '';
      if (status == 'delivered' || status == 'cancelled') continue;
      orders.add(order);
    }
    orders.sort((a, b) {
      final aDate = DateTime.tryParse(a['created_at']?.toString() ?? '') ??
          DateTime.fromMillisecondsSinceEpoch(0);
      final bDate = DateTime.tryParse(b['created_at']?.toString() ?? '') ??
          DateTime.fromMillisecondsSinceEpoch(0);
      return bDate.compareTo(aDate);
    });
    return orders;
  }

  String _orderIdentity(Map<String, dynamic> order) {
    return (order['id'] ?? order['order_id'] ?? order['order_number'] ?? '')
        .toString();
  }

  Widget _buildMetricGrid(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: _DashboardMetricCard(
                  title: 'Revenue',
                  value: formatCurrencyValue(context, _stats['today_revenue']),
                  icon: Icons.currency_rupee_rounded,
                  color: _DashboardPalette.brand,
                  trend: _trendPercent([
                    'today_revenue_change',
                    'today_revenue_growth',
                    'revenue_change',
                  ]),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _DashboardMetricCard(
                  title: 'Orders',
                  value: '${_stats['today_orders'] ?? 0}',
                  icon: Icons.receipt_long_rounded,
                  color: _DashboardPalette.brand,
                  trend: _trendPercent([
                    'today_orders_change',
                    'today_orders_growth',
                    'orders_change',
                  ]),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _DashboardMetricCard(
                  title: 'Total Orders',
                  value: '${_stats['total_orders'] ?? 0}',
                  icon: Icons.shopping_bag_outlined,
                  color: _DashboardPalette.brand,
                  trend: _trendPercent([
                    'total_orders_change',
                    'total_orders_growth',
                    'orders_growth',
                  ]),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _DashboardMetricCard(
                  title: 'Customers',
                  value: '${_stats['total_customers'] ?? 0}',
                  icon: Icons.groups_2_outlined,
                  color: _DashboardPalette.brand,
                  trend: _trendPercent([
                    'customers_change',
                    'customers_growth',
                    'total_customers_growth',
                  ]),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildRunningOrdersPanel() {
    final counts = _runningStatusCounts();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 18),
        decoration: _dashboardPanel(radius: 18),
        child: Column(
          children: [
            Row(
              children: [
                _RunningStep(
                  icon: Icons.receipt_long_rounded,
                  label: 'Accepted',
                  count: counts['accepted'] ?? 0,
                ),
                const _DashedConnector(),
                _RunningStep(
                  icon: Icons.soup_kitchen_outlined,
                  label: 'Preparing',
                  count: counts['preparing'] ?? 0,
                ),
                const _DashedConnector(),
                _RunningStep(
                  icon: Icons.delivery_dining_rounded,
                  label: 'On the Way',
                  count: counts['on_the_way'] ?? 0,
                ),
                const _DashedConnector(),
                _RunningStep(
                  icon: Icons.check_circle_outline_rounded,
                  label: 'Delivered',
                  count: counts['delivered'] ?? 0,
                ),
              ],
            ),
            const SizedBox(height: 18),
            if (_runningOrders.isEmpty)
              const _RunningEmptyState()
            else
              Column(
                children: _runningOrders.take(3).map((rawOrder) {
                  final order = _asOrderMap(rawOrder);
                  final status = order['status']?.toString() ?? 'pending';
                  final statusColor = _getStatusColor(status);
                  return InkWell(
                    onTap: () {
                      Navigator.pushNamed(
                        context,
                        '/restaurant/order',
                        arguments: order['id'],
                      );
                    },
                    child: _buildCompactOrderRow(order, status, statusColor),
                  );
                }).toList(),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildRecentOrdersPanel(BuildContext context) {
    if (_recentOrders.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Container(
          decoration: _dashboardPanel(radius: 18),
          child: FoodFlowTheme.emptyState(
            icon: Icons.receipt_long_outlined,
            title: 'No orders yet',
            subtitle: 'Fresh orders will land here instantly.',
          ),
        ),
      );
    }

    final visibleOrders = _recentOrders.take(5).map(_asOrderMap).toList();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        decoration: _dashboardPanel(radius: 18),
        clipBehavior: Clip.antiAlias,
        child: Column(
          children: [
            for (var index = 0; index < visibleOrders.length; index++) ...[
              _buildRecentOrderRow(context, visibleOrders[index], index),
              if (index != visibleOrders.length - 1)
                const Divider(height: 1, color: FoodFlowTheme.line),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildRecentOrderRow(
    BuildContext context,
    Map<String, dynamic> order,
    int index,
  ) {
    final status = order['status']?.toString() ?? 'pending';
    final statusColor = _getStatusColor(status);
    final imageUrl = _orderImageUrl(order);

    return InkWell(
      onTap: () {
        Navigator.pushNamed(
          context,
          '/restaurant/order',
          arguments: order['id'],
        );
      },
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 9, 8, 9),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            _OrderThumbnail(imageUrl: imageUrl),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          _orderNumber(order),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _DashboardPalette.ink,
                            fontSize: 13,
                            height: 1.15,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      _PaymentPill(label: _paymentLabel(order)),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          '${_itemsCount(order)} ${_itemsCount(order) == 1 ? 'Item' : 'Items'} - ${formatCurrencyValue(context, order['total'])}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _DashboardPalette.muted,
                            fontSize: 11,
                            height: 1.2,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        _formatOrderTime(order['created_at']),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _DashboardPalette.muted,
                          fontSize: 10,
                          height: 1.2,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 5),
                  Row(
                    children: [
                      Container(
                        width: 6,
                        height: 6,
                        decoration: BoxDecoration(
                          color: statusColor,
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          _getStatusText(status),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: statusColor,
                            fontSize: 11,
                            height: 1.1,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 4),
            const Icon(
              Icons.chevron_right_rounded,
              color: _DashboardPalette.muted,
              size: 24,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCompactOrderRow(
    Map<String, dynamic> order,
    String status,
    Color statusColor,
  ) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: statusColor.withOpacity(0.10),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(Icons.delivery_dining, color: statusColor, size: 18),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              _orderNumber(order),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _DashboardPalette.ink,
                fontSize: 12,
                height: 1.1,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            _getStatusText(status),
            style: TextStyle(
              color: statusColor,
              fontSize: 11,
              height: 1.1,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  BoxDecoration _dashboardPanel({double radius = 16}) {
    return BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: FoodFlowTheme.line),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withOpacity(0.035),
          blurRadius: 16,
          offset: const Offset(0, 8),
        ),
      ],
    );
  }

  String? _trendPercent(List<String> keys) {
    for (final key in keys) {
      final value = _stats[key];
      if (value == null) continue;
      final parsed = value is num ? value : num.tryParse(value.toString());
      if (parsed == null) continue;
      final decimals = parsed % 1 == 0 ? 0 : 1;
      return '${parsed.toStringAsFixed(decimals)}%';
    }
    return null;
  }

  Map<String, int> _runningStatusCounts() {
    final counts = {
      'accepted': 0,
      'preparing': 0,
      'on_the_way': 0,
      'delivered': 0,
    };

    for (final rawOrder in _runningOrders) {
      final status = _asOrderMap(rawOrder)['status']?.toString() ?? '';
      final bucket = _runningStatusBucket(status);
      if (bucket != null) counts[bucket] = (counts[bucket] ?? 0) + 1;
    }

    return counts;
  }

  String? _runningStatusBucket(String status) {
    switch (status) {
      case 'accepted':
      case 'confirmed':
        return 'accepted';
      case 'preparing':
      case 'ready_for_pickup':
      case 'reached_pickup':
        return 'preparing';
      case 'picked_up':
      case 'on_the_way':
        return 'on_the_way';
      case 'delivered':
        return 'delivered';
      default:
        return null;
    }
  }

  Map<String, dynamic> _asOrderMap(dynamic order) {
    if (order is Map<String, dynamic>) return order;
    if (order is Map) return Map<String, dynamic>.from(order);
    return <String, dynamic>{};
  }

  String _orderNumber(Map<String, dynamic> order) {
    final raw = order['order_number'] ?? order['id'] ?? 'N/A';
    final value = raw.toString();
    return value.startsWith('#') ? value : '#$value';
  }

  int _itemsCount(Map<String, dynamic> order) {
    final rawCount = order['items_count'] ?? order['item_count'];
    if (rawCount is int) return rawCount;
    if (rawCount is num) return rawCount.toInt();
    final parsed = int.tryParse(rawCount?.toString() ?? '');
    if (parsed != null) return parsed;
    final items = order['items'];
    if (items is List) return items.length;
    return 0;
  }

  String _paymentLabel(Map<String, dynamic> order) {
    final raw = (order['payment_method'] ??
            order['payment_type'] ??
            order['delivery_payment_mode'] ??
            order['payment_status'])
        ?.toString()
        .toLowerCase();
    if (raw == null || raw.isEmpty) return 'Online';
    if (raw.contains('cod') || raw.contains('cash')) return 'COD';
    return 'Online';
  }

  String _formatOrderTime(dynamic value) {
    final parsed = DateTime.tryParse(value?.toString() ?? '');
    if (parsed == null) return '';
    final local = parsed.toLocal();
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
    final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
    final minute = local.minute.toString().padLeft(2, '0');
    final period = local.hour >= 12 ? 'PM' : 'AM';
    return '${local.day} ${months[local.month - 1]}, $hour:$minute $period';
  }

  String _orderImageUrl(Map<String, dynamic> order) {
    dynamic raw = order['image_url'] ??
        order['image'] ??
        order['thumbnail_url'] ??
        order['restaurant_image'];

    final items = order['items'];
    if ((raw == null || raw.toString().isEmpty) &&
        items is List &&
        items.isNotEmpty) {
      final firstItem = items.first;
      if (firstItem is Map) {
        raw = firstItem['image_url'] ??
            firstItem['image'] ??
            firstItem['thumbnail_url'];
      }
    }

    return _resolveDashboardImageUrl(raw);
  }

  String _resolveDashboardImageUrl(dynamic raw) {
    final value = raw?.toString().trim() ?? '';
    if (value.isEmpty || value == 'null') return '';
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }

    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final origin = '${apiUri.scheme}://${apiUri.host}';
    final normalized = value.startsWith('/') ? value.substring(1) : value;
    if (normalized.startsWith('storage/')) return '$origin/$normalized';
    return '$origin/storage/$normalized';
  }

  Widget _buildOrderList(List<dynamic> orders, String emptyText) {
    if (orders.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Container(
          decoration: RestaurantPremium.panel(radius: 18),
          child: FoodFlowTheme.emptyState(
            icon: Icons.receipt_long_outlined,
            title: emptyText,
            subtitle: 'Orders will appear here automatically.',
          ),
        ),
      );
    }

    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: orders.length > 3 ? 3 : orders.length,
      itemBuilder: (context, index) {
        final order = orders[index];
        final status = order['status'] ?? 'pending';
        final statusColor = _getStatusColor(status);
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          decoration: RestaurantPremium.panel(radius: 16),
          child: ListTile(
            minVerticalPadding: 10,
            isThreeLine: true,
            leading: Icon(Icons.delivery_dining, color: statusColor),
            title: Text(
              'Order #${order['order_number'] ?? 'N/A'}',
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            subtitle: Text(
              '${formatCurrencyValue(context, order['total'])} - ${order['customer_name'] ?? 'Guest'}',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            trailing: Text(
              _getStatusText(status),
              style: TextStyle(color: statusColor, fontWeight: FontWeight.w900),
            ),
            onTap: () {
              Navigator.pushNamed(
                context,
                '/restaurant/order',
                arguments: order['id'],
              );
            },
          ),
        );
      },
    );
  }

  Widget _buildStatCard(
    String title,
    String value,
    IconData icon,
    Color color,
  ) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: FoodFlowTheme.surface(radius: 14),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: color, size: 24),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(fontSize: 12, color: FoodFlowTheme.muted),
                ),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                    color: FoodFlowTheme.ink,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _getStatusText(String status) {
    switch (status) {
      case 'pending':
        return 'Pending';
      case 'confirmed':
        return 'Confirmed';
      case 'preparing':
        return 'Preparing';
      case 'ready_for_pickup':
        return 'Ready';
      case 'reached_pickup':
        return 'Reached';
      case 'picked_up':
        return 'Picked Up';
      case 'on_the_way':
        return 'On The Way';
      case 'delivered':
        return 'Delivered';
      case 'cancelled':
        return 'Cancelled';
      default:
        return status;
    }
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'pending':
        return Colors.orange;
      case 'confirmed':
        return _DashboardPalette.success;
      case 'preparing':
        return _DashboardPalette.success;
      case 'ready_for_pickup':
        return _DashboardPalette.success;
      case 'reached_pickup':
        return _DashboardPalette.success;
      case 'picked_up':
        return _DashboardPalette.success;
      case 'on_the_way':
        return _DashboardPalette.success;
      case 'delivered':
        return _DashboardPalette.success;
      case 'cancelled':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }
}

class _OrdersHomeHeader extends StatelessWidget {
  const _OrdersHomeHeader({
    required this.restaurants,
    required this.selectedRestaurantId,
    required this.onMenu,
  });

  final List<Map<String, dynamic>> restaurants;
  final int? selectedRestaurantId;
  final VoidCallback onMenu;

  int? _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  bool _isOpen(Map<String, dynamic> restaurant) {
    final value =
        restaurant['is_open'] ?? restaurant['online'] ?? restaurant['status'];
    if (value is bool) return value;
    final text = value?.toString().toLowerCase() ?? '';
    return text == '1' || text == 'open' || text == 'online' || text == 'true';
  }

  @override
  Widget build(BuildContext context) {
    final selectedRestaurants = selectedRestaurantId == null
        ? restaurants
        : restaurants
            .where((restaurant) =>
                _asInt(restaurant['id']) == selectedRestaurantId)
            .toList();
    final selectedCount = selectedRestaurants.length;
    final onlineCount = selectedRestaurants.where(_isOpen).length;
    final offlineCount = selectedCount - onlineCount;

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(18, 10, 14, 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Icon(Icons.navigation_rounded,
              color: _DashboardPalette.brand, size: 24),
          const SizedBox(width: 8),
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const _RestaurantOutletSelectorScreen(),
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(children: [
                      Flexible(
                        child: Text(
                          '$selectedCount ${selectedCount == 1 ? 'Outlet' : 'Outlets'} Selected',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _DashboardPalette.ink,
                            fontSize: 20,
                            height: 1.05,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      const SizedBox(width: 2),
                      const Icon(Icons.chevron_right_rounded,
                          color: FoodFlowTheme.muted, size: 22),
                    ]),
                    const SizedBox(height: 1),
                    Text.rich(
                      TextSpan(children: [
                        TextSpan(
                            text: '$onlineCount Online',
                            style: const TextStyle(
                                color: _DashboardPalette.success)),
                        const TextSpan(
                            text: ' | ',
                            style: TextStyle(color: FoodFlowTheme.muted)),
                        TextSpan(
                            text: '$offlineCount Offline',
                            style:
                                const TextStyle(color: FoodFlowTheme.danger)),
                      ]),
                      style: const TextStyle(
                          fontSize: 13, fontWeight: FontWeight.w900),
                    ),
                  ],
                ),
              ),
            ),
          ),
          IconButton(
            onPressed: onMenu,
            icon: const Icon(Icons.menu_rounded, size: 30),
          ),
        ],
      ),
    );
  }
}

class _RestaurantOutletSelectorScreen extends StatefulWidget {
  const _RestaurantOutletSelectorScreen();

  @override
  State<_RestaurantOutletSelectorScreen> createState() =>
      _RestaurantOutletSelectorScreenState();
}

class _RestaurantOutletSelectorScreenState
    extends State<_RestaurantOutletSelectorScreen> {
  final ApiService _api = ApiService();
  final Set<int> _updatingIds = <int>{};

  int? _asInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  bool _isOpen(Map<String, dynamic> restaurant) {
    final value =
        restaurant['is_open'] ?? restaurant['online'] ?? restaurant['status'];
    if (value is bool) return value;
    final text = value?.toString().toLowerCase() ?? '';
    return text == '1' || text == 'open' || text == 'online' || text == 'true';
  }

  String _logoUrl(Map<String, dynamic> restaurant) {
    final raw = restaurant['logo_url'] ??
        restaurant['logo_image_url'] ??
        restaurant['logo_image'] ??
        restaurant['logo'] ??
        restaurant['image_url'];
    final value = raw?.toString().trim() ?? '';
    if (value.isEmpty || value == 'null') return '';
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }
    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final origin = '${apiUri.scheme}://${apiUri.host}';
    final normalized = value.startsWith('/') ? value.substring(1) : value;
    if (normalized.startsWith('storage/')) return '$origin/$normalized';
    return '$origin/storage/$normalized';
  }

  Future<void> _toggleOutlet(Map<String, dynamic> restaurant) async {
    final id = _asInt(restaurant['id']);
    if (id == null || _updatingIds.contains(id)) return;

    setState(() => _updatingIds.add(id));
    try {
      await _api.post(
        ApiConstants.restaurantToggleStatus,
        queryParams: {'restaurant_id': id.toString()},
        data: {'is_open': !_isOpen(restaurant)},
      );
      if (!mounted) return;
      await context.read<RestaurantProvider>().loadDashboardData();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not update outlet: $error')),
      );
    } finally {
      if (mounted) setState(() => _updatingIds.remove(id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RestaurantProvider>();
    final restaurants = provider.restaurants;
    final selectedId = provider.selectedRestaurantId;

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0.5,
        foregroundColor: _DashboardPalette.ink,
        title: const Text('Select outlet'),
      ),
      body: RefreshIndicator(
        onRefresh: provider.loadDashboardData,
        color: _DashboardPalette.brand,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
          children: [
            _OutletSelectTile(
              title: 'All Outlets',
              subtitle: '${restaurants.length} outlets selected',
              selected: selectedId == null,
              leading: Icon(Icons.dashboard_customize_outlined,
                  color: _DashboardPalette.brand),
              onTap: () async {
                await provider.selectRestaurant(null);
                if (context.mounted) Navigator.pop(context);
              },
            ),
            const SizedBox(height: 8),
            ...restaurants.map((restaurant) {
              final id = _asInt(restaurant['id']);
              final isOpen = _isOpen(restaurant);
              final updating = id != null && _updatingIds.contains(id);
              final logoUrl = _logoUrl(restaurant);
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _OutletSelectTile(
                  title: restaurant['name']?.toString() ?? 'Outlet',
                  subtitle:
                      '${isOpen ? 'Online' : 'Offline'}${restaurant['city'] == null ? '' : ' - ${restaurant['city']}'}',
                  selected: id == selectedId,
                  leading: logoUrl.isEmpty
                      ? Icon(Icons.storefront_rounded,
                          color: isOpen
                              ? _DashboardPalette.success
                              : FoodFlowTheme.muted)
                      : NetworkImageLoader(
                          imageUrl: logoUrl,
                          width: 38,
                          height: 38,
                          borderRadius: BorderRadius.circular(10),
                        ),
                  trailing: updating
                      ? const SizedBox(
                          width: 28,
                          height: 28,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Switch(
                          value: isOpen,
                          activeColor: _DashboardPalette.success,
                          onChanged: (_) => _toggleOutlet(restaurant),
                        ),
                  onTap: () async {
                    await provider.selectRestaurant(id);
                    if (context.mounted) Navigator.pop(context);
                  },
                ),
              );
            }),
          ],
        ),
      ),
    );
  }
}

class _OutletSelectTile extends StatelessWidget {
  const _OutletSelectTile({
    required this.title,
    required this.subtitle,
    required this.selected,
    required this.leading,
    required this.onTap,
    this.trailing,
  });

  final String title;
  final String subtitle;
  final bool selected;
  final Widget leading;
  final VoidCallback onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            children: [
              SizedBox(width: 40, height: 40, child: Center(child: leading)),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: _DashboardPalette.ink,
                            fontSize: 14,
                            fontWeight: FontWeight.w900)),
                    const SizedBox(height: 3),
                    Text(subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
              if (trailing != null) ...[
                const SizedBox(width: 8),
                trailing!,
              ] else if (selected)
                Icon(Icons.check_circle_rounded,
                    color: _DashboardPalette.brand),
            ],
          ),
        ),
      ),
    );
  }
}

class _OrderStageTabsHeader extends SliverPersistentHeaderDelegate {
  _OrderStageTabsHeader(
      {required this.selected, required this.counts, required this.onSelected});

  final String selected;
  final Map<String, int> counts;
  final ValueChanged<String> onSelected;

  @override
  double get minExtent => 50;

  @override
  double get maxExtent => 50;

  @override
  Widget build(
      BuildContext context, double shrinkOffset, bool overlapsContent) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
              color: Colors.black.withOpacity(0.12),
              blurRadius: 8,
              offset: const Offset(0, 3))
        ],
      ),
      child: Row(children: [
        _OrderStageTab(
            label: 'Preparing',
            value: 'preparing',
            selected: selected == 'preparing',
            count: counts['preparing'] ?? 0,
            onTap: onSelected),
        _OrderStageTab(
            label: 'Ready',
            value: 'ready',
            selected: selected == 'ready',
            count: counts['ready'] ?? 0,
            onTap: onSelected),
        _OrderStageTab(
            label: 'Picked up',
            value: 'picked_up',
            selected: selected == 'picked_up',
            count: counts['picked_up'] ?? 0,
            onTap: onSelected),
      ]),
    );
  }

  @override
  bool shouldRebuild(covariant _OrderStageTabsHeader oldDelegate) {
    return selected != oldDelegate.selected || counts != oldDelegate.counts;
  }
}

class _OrderStageTab extends StatelessWidget {
  const _OrderStageTab(
      {required this.label,
      required this.value,
      required this.selected,
      required this.count,
      required this.onTap});
  final String label;
  final String value;
  final bool selected;
  final int count;
  final ValueChanged<String> onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        onTap: () => onTap(value),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.end,
          children: [
            Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              Text(label,
                  style: TextStyle(
                      color: selected
                          ? _DashboardPalette.brand
                          : _DashboardPalette.ink,
                      fontSize: 14,
                      fontWeight:
                          selected ? FontWeight.w900 : FontWeight.w700)),
              if (count > 0) ...[
                const SizedBox(width: 8),
                Container(
                  width: 24,
                  height: 24,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                      color: selected
                          ? _DashboardPalette.brand
                          : const Color(0xFFF0F0F4),
                      shape: BoxShape.circle),
                  child: Text('$count',
                      style: TextStyle(
                          color:
                              selected ? Colors.white : _DashboardPalette.ink,
                          fontWeight: FontWeight.w900)),
                ),
              ],
            ]),
            const SizedBox(height: 14),
            LayoutBuilder(
              builder: (context, constraints) {
                return AnimatedContainer(
                    duration: const Duration(milliseconds: 160),
                    height: 4,
                    width: selected ? constraints.maxWidth : 0,
                    decoration: BoxDecoration(
                        color: _DashboardPalette.brand,
                        borderRadius: BorderRadius.circular(999)));
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _OrdersHomeEmptyState extends StatelessWidget {
  const _OrdersHomeEmptyState(
      {required this.hasAnyActionableOrder, required this.selectedStage});
  final bool hasAnyActionableOrder;
  final String selectedStage;

  @override
  Widget build(BuildContext context) {
    final title = hasAnyActionableOrder ? 'No Orders!' : 'All Good!';
    final subtitle = hasAnyActionableOrder
        ? '${_stageLabel(selectedStage)} orders will be shown here'
        : 'No orders need your attention';
    return Container(
      color: const Color(0xFFE5E5E5),
      alignment: Alignment.center,
      padding: const EdgeInsets.symmetric(horizontal: 28),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(
            hasAnyActionableOrder
                ? Icons.soup_kitchen_outlined
                : Icons.done_all_rounded,
            color: Colors.white,
            size: 72),
        const SizedBox(height: 22),
        Text(title,
            textAlign: TextAlign.center,
            style: const TextStyle(
                color: _DashboardPalette.ink,
                fontSize: 20,
                fontWeight: FontWeight.w900)),
        const SizedBox(height: 10),
        Text(subtitle,
            textAlign: TextAlign.center,
            style: const TextStyle(
                color: FoodFlowTheme.muted,
                fontSize: 14,
                fontWeight: FontWeight.w800)),
      ]),
    );
  }

  String _stageLabel(String stage) {
    if (stage == 'ready') return 'Ready';
    if (stage == 'picked_up') return 'Picked up';
    return 'Preparing';
  }
}

class _OrdersHomeCard extends StatelessWidget {
  const _OrdersHomeCard({
    required this.order,
    required this.expandedBill,
    required this.onToggleBill,
    required this.onAccept,
    required this.onReject,
    required this.onMarkReady,
    required this.onDetails,
    required this.onHelp,
  });
  final Map<String, dynamic> order;
  final bool expandedBill;
  final VoidCallback onToggleBill;
  final VoidCallback onAccept;
  final VoidCallback onReject;
  final VoidCallback onMarkReady;
  final VoidCallback onDetails;
  final VoidCallback onHelp;

  @override
  Widget build(BuildContext context) {
    final items = _items(order);
    final firstItem = items.isEmpty ? null : items.first;
    final specialInstructions = _firstText([
      order['special_instructions'],
      order['instructions'],
      order['note'],
      order['customer_note']
    ]);
    final partnerName = _partnerName(order);
    final partnerPhone = _firstText([
      order['driver_phone'],
      _map(order['driver'])['phone'],
      _map(order['delivery_partner'])['phone']
    ]);
    final partnerPhoto = _firstText([
      _map(order['driver'])['profile_photo_url'],
      _map(order['delivery_partner'])['profile_photo_url'],
    ]);
    final driverArrived = _driverHasArrived(order);
    final orderId = _intValue(order['id'] ?? order['order_id']);

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 13),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                    color: _DashboardPalette.brand,
                    borderRadius: BorderRadius.circular(10)),
                child: const Icon(Icons.receipt_long_rounded,
                    color: Colors.white)),
            const SizedBox(width: 12),
            Expanded(
                child: Text(_orderNumber(order),
                    style: const TextStyle(
                        color: _DashboardPalette.ink,
                        fontSize: 19,
                        fontWeight: FontWeight.w900))),
            PopupMenuButton<String>(
              tooltip: 'Order options',
              onSelected: (value) {
                if (value == 'details') onDetails();
                if (value == 'help') onHelp();
              },
              itemBuilder: (context) => const [
                PopupMenuItem(
                  value: 'details',
                  child: Text('Order details'),
                ),
                PopupMenuItem(
                  value: 'help',
                  child: Text('Help with this order'),
                ),
              ],
              icon: const Icon(Icons.more_vert_rounded),
            ),
          ]),
          const SizedBox(height: 8),
          Row(children: [
            Expanded(
                child: Text(_restaurantLine(order),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        color: _DashboardPalette.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w700))),
            const SizedBox(width: 10),
            Text(_time(order),
                style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 14,
                    fontWeight: FontWeight.w700)),
          ]),
          const SizedBox(height: 10),
          const Divider(height: 1, color: FoodFlowTheme.line),
          const SizedBox(height: 10),
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const _OrderCardSectionIcon(icon: Icons.fact_check_outlined),
            const SizedBox(width: 10),
            Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                  Row(children: [
                    const Expanded(child: _OrderCardTitle('ORDER DETAILS')),
                    Text(
                        '${_itemCount(order)} ${_itemCount(order) == 1 ? 'Item' : 'Items'}',
                        style: const TextStyle(
                            color: _DashboardPalette.ink,
                            fontSize: 14,
                            fontWeight: FontWeight.w900)),
                  ]),
                  if (_isKnownVegOrder(items)) ...[
                    const SizedBox(height: 8),
                    Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 5),
                        decoration: BoxDecoration(
                            color: FoodFlowTheme.success,
                            borderRadius: BorderRadius.circular(5)),
                        child: const Text('PURE VEG',
                            style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900))),
                  ],
                  if (firstItem != null) ...[
                    const SizedBox(height: 14),
                    Text('${_quantity(firstItem)} x ${_itemName(firstItem)}',
                        style: const TextStyle(
                            color: _DashboardPalette.ink,
                            fontSize: 14,
                            fontWeight: FontWeight.w900)),
                    if (_variant(firstItem).isNotEmpty) ...[
                      const SizedBox(height: 5),
                      Text('Variant: ${_variant(firstItem)}',
                          style: const TextStyle(
                              color: _DashboardPalette.ink,
                              fontSize: 14,
                              fontWeight: FontWeight.w600)),
                    ],
                  ],
                ])),
          ]),
          if (specialInstructions != null) ...[
            const SizedBox(height: 18),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                  color: const Color(0xFFC9F7E4),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: const Color(0xFF90E7C4))),
              child: Row(children: [
                const Icon(Icons.restaurant_outlined, color: Color(0xFF15A876)),
                const SizedBox(width: 10),
                Expanded(
                    child: Text(specialInstructions,
                        style: const TextStyle(
                            color: Color(0xFF15A876),
                            fontSize: 14,
                            fontWeight: FontWeight.w900))),
              ]),
            ),
          ],
          const SizedBox(height: 18),
          const Divider(height: 1, color: FoodFlowTheme.line),
          const SizedBox(height: 10),
          Row(children: [
            const _OrderCardSectionIcon(icon: Icons.payments_outlined),
            const SizedBox(width: 10),
            const Expanded(child: _OrderCardTitle('BILL DETAILS')),
            Text(formatCurrencyValue(context, _num(order['total'])),
                style: const TextStyle(
                    color: _DashboardPalette.ink,
                    fontSize: 14,
                    fontWeight: FontWeight.w900)),
            IconButton(
                onPressed: onToggleBill,
                icon: Icon(
                    expandedBill
                        ? Icons.keyboard_arrow_up_rounded
                        : Icons.keyboard_arrow_down_rounded,
                    color: _DashboardPalette.brand)),
          ]),
          if (expandedBill) ...[
            const SizedBox(height: 10),
            _BillBreakup(order: order),
          ],
          if (partnerName != null) ...[
            const SizedBox(height: 10),
            const Divider(height: 1, color: FoodFlowTheme.line),
            const SizedBox(height: 10),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipOval(
                  child: SizedBox.square(
                    dimension: 38,
                    child: partnerPhoto == null
                        ? Container(
                            color: FoodFlowTheme.canvas,
                            child: const Icon(
                              Icons.delivery_dining_rounded,
                              color: FoodFlowTheme.muted,
                              size: 22,
                            ),
                          )
                        : AppNetworkImage(
                            imageUrl: partnerPhoto,
                            width: 38,
                            height: 38,
                            fit: BoxFit.cover,
                            errorWidget: Container(
                              color: FoodFlowTheme.canvas,
                              child: const Icon(
                                Icons.delivery_dining_rounded,
                                color: FoodFlowTheme.muted,
                                size: 22,
                              ),
                            ),
                          ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        partnerName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _DashboardPalette.ink,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        _partnerStatusMessage(order),
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      if ((!driverArrived && orderId != null) ||
                          partnerPhone != null) ...[
                        const SizedBox(height: 5),
                        Wrap(
                          spacing: 4,
                          runSpacing: 2,
                          children: [
                            if (!driverArrived && orderId != null)
                              TextButton.icon(
                                onPressed: () => Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        RestaurantDriverTrackingScreen(
                                      orderId: orderId,
                                      initialOrder: order,
                                    ),
                                  ),
                                ),
                                style: TextButton.styleFrom(
                                  foregroundColor: _DashboardPalette.brand,
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 4,
                                    vertical: 2,
                                  ),
                                  minimumSize: const Size(0, 32),
                                  tapTargetSize:
                                      MaterialTapTargetSize.shrinkWrap,
                                ),
                                icon: const Icon(
                                  Icons.near_me_rounded,
                                  size: 17,
                                ),
                                label: const Text(
                                  'Track Partner',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                            if (partnerPhone != null)
                              TextButton.icon(
                                onPressed: () =>
                                    _callPartner(context, partnerPhone),
                                style: TextButton.styleFrom(
                                  foregroundColor: _DashboardPalette.brand,
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 4,
                                    vertical: 2,
                                  ),
                                  minimumSize: const Size(0, 32),
                                  tapTargetSize:
                                      MaterialTapTargetSize.shrinkWrap,
                                ),
                                icon: const Icon(
                                  Icons.phone_in_talk_rounded,
                                  size: 17,
                                ),
                                label: const Text(
                                  'Call Partner',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 10),
          const Divider(height: 1, color: FoodFlowTheme.line),
          const SizedBox(height: 10),
          _OrderInlineActions(
            status: order['status']?.toString() ?? '',
            onAccept: onAccept,
            onReject: onReject,
            onMarkReady: onMarkReady,
          ),
          const SizedBox(height: 10),
          Row(children: [
            Icon(Icons.thumb_up_alt_rounded,
                color: FoodFlowTheme.success, size: 22),
            const SizedBox(width: 12),
            Expanded(
                child: Text(_statusMessage(order),
                    style: const TextStyle(
                        color: FoodFlowTheme.success,
                        fontSize: 14,
                        fontWeight: FontWeight.w900))),
          ]),
        ]),
      ),
    );
  }

  static List<Map<String, dynamic>> _items(Map<String, dynamic> order) {
    final raw = order['items'] ?? order['order_items'] ?? order['cart_items'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  static Map<String, dynamic> _map(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
  static num _num(dynamic value) =>
      value is num ? value : num.tryParse(value?.toString() ?? '') ?? 0;
  static String _orderNumber(Map<String, dynamic> order) {
    final raw = order['order_number'] ?? order['order_no'] ?? order['id'] ?? '';
    final value = raw.toString();
    return value.startsWith('#') ? value : '#$value';
  }

  static String _restaurantLine(Map<String, dynamic> order) {
    final restaurant = _map(order['restaurant']);
    final parts = [
      _firstText([order['restaurant_name'], restaurant['name']]),
      _firstText([
        order['restaurant_rid'],
        order['rid'],
        restaurant['rid'],
        restaurant['id']
      ]),
      _firstText(
          [order['restaurant_area'], restaurant['area'], restaurant['address']])
    ].whereType<String>().where((value) => value.trim().isNotEmpty).toList();
    return parts.isEmpty ? 'Restaurant details unavailable' : parts.join(', ');
  }

  static String _time(Map<String, dynamic> order) {
    final parsed = DateTime.tryParse(order['created_at']?.toString() ?? '');
    if (parsed == null) return '';
    final local = parsed.toLocal();
    final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
    final minute = local.minute.toString().padLeft(2, '0');
    final period = local.hour >= 12 ? 'PM' : 'AM';
    return '$hour:$minute $period';
  }

  static int _itemCount(Map<String, dynamic> order) {
    final raw = order['items_count'] ?? order['item_count'];
    if (raw is int) return raw;
    if (raw is num) return raw.toInt();
    final parsed = int.tryParse(raw?.toString() ?? '');
    if (parsed != null) return parsed;
    return _items(order).fold<int>(0, (sum, item) => sum + _quantity(item));
  }

  static int _quantity(Map<String, dynamic> item) {
    final raw = item['quantity'] ?? item['qty'];
    if (raw is int) return raw;
    if (raw is num) return raw.toInt();
    return int.tryParse(raw?.toString() ?? '') ?? 1;
  }

  static String _itemName(Map<String, dynamic> item) =>
      _firstText([
        item['name'],
        item['item_name'],
        item['menu_name'],
        _map(item['menu_item'])['name']
      ]) ??
      'Item';
  static String _variant(Map<String, dynamic> item) {
    final variant = _map(item['selected_variant'] ?? item['variant']);
    return _firstText([variant['name'], item['variant_name']]) ?? '';
  }

  static bool _isKnownVegOrder(List<Map<String, dynamic>> items) {
    if (items.isEmpty) return false;
    var sawKnownValue = false;
    for (final item in items) {
      final value = item['is_veg'] ?? item['veg'] ?? item['food_type'];
      if (value == null) continue;
      sawKnownValue = true;
      final text = value.toString().toLowerCase();
      final isVeg = value == true ||
          text == '1' ||
          text == 'veg' ||
          text == 'vegetarian' ||
          text == 'true';
      if (!isVeg) return false;
    }
    return sawKnownValue;
  }

  static String? _partnerName(Map<String, dynamic> order) {
    final driver = _map(order['driver']);
    final partner = _map(order['delivery_partner']);
    return _firstText([order['driver_name'], driver['name'], partner['name']]);
  }

  static bool _driverHasArrived(Map<String, dynamic> order) {
    if (order['driver_arrived_at_restaurant'] == true) return true;
    return const {
      'reached_pickup',
      'picked_up',
      'on_the_way',
      'delivered',
      'completed',
    }.contains(order['status']?.toString().toLowerCase());
  }

  static String _partnerStatusMessage(Map<String, dynamic> order) {
    final status = order['status']?.toString().toLowerCase() ?? '';
    if (status == 'reached_pickup') {
      return 'Arrived at the restaurant';
    }
    if (status == 'picked_up' || status == 'on_the_way') {
      return 'Order picked up';
    }
    return 'Assigned to this order';
  }

  static int? _intValue(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  static Future<void> _callPartner(
    BuildContext context,
    String phone,
  ) async {
    final launched = await launchUrl(
      Uri(scheme: 'tel', path: phone),
      mode: LaunchMode.externalApplication,
    );
    if (!launched && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Calling is not available on this device.')),
      );
    }
  }

  static String _statusMessage(Map<String, dynamic> order) {
    final status = order['status']?.toString() ?? '';
    if (status == 'picked_up' || status == 'on_the_way')
      return 'Order handover on time';
    if (status == 'ready_for_pickup' || status == 'reached_pickup')
      return 'Order ready for pickup';
    if (status == 'pending') return 'Order needs your attention';
    return 'Order is being prepared';
  }

  static String? _firstText(List<dynamic> values) {
    for (final value in values) {
      final text = value?.toString().trim();
      if (text != null && text.isNotEmpty && text.toLowerCase() != 'null')
        return text;
    }
    return null;
  }
}

class _OrderInlineActions extends StatelessWidget {
  const _OrderInlineActions({
    required this.status,
    required this.onAccept,
    required this.onReject,
    required this.onMarkReady,
  });

  final String status;
  final VoidCallback onAccept;
  final VoidCallback onReject;
  final VoidCallback onMarkReady;

  @override
  Widget build(BuildContext context) {
    if (status == 'pending') {
      return Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: onReject,
              style: OutlinedButton.styleFrom(
                foregroundColor: FoodFlowTheme.danger,
                side: const BorderSide(color: FoodFlowTheme.danger),
                padding: const EdgeInsets.symmetric(vertical: 10),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              child: const Text('Reject'),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: ElevatedButton(
              onPressed: onAccept,
              style: ElevatedButton.styleFrom(
                backgroundColor: _DashboardPalette.brand,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 10),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              child: const Text('Accept'),
            ),
          ),
        ],
      );
    }

    if (status == 'accepted' ||
        status == 'confirmed' ||
        status == 'preparing') {
      return SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed: onMarkReady,
          icon: const Icon(Icons.check_circle_outline_rounded, size: 18),
          label: const Text('Mark ready'),
          style: ElevatedButton.styleFrom(
            backgroundColor: _DashboardPalette.brand,
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(vertical: 10),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
        ),
      );
    }

    return const SizedBox.shrink();
  }
}

class _OrderCardSectionIcon extends StatelessWidget {
  const _OrderCardSectionIcon({required this.icon});
  final IconData icon;
  @override
  Widget build(BuildContext context) => Container(
      width: 36,
      height: 36,
      decoration:
          const BoxDecoration(color: Color(0xFFF1F1F1), shape: BoxShape.circle),
      child: Icon(icon, color: FoodFlowTheme.muted, size: 19));
}

class _OrderCardTitle extends StatelessWidget {
  const _OrderCardTitle(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Text(text,
      style: const TextStyle(
          color: FoodFlowTheme.muted,
          fontSize: 14,
          letterSpacing: 0,
          fontWeight: FontWeight.w900));
}

class _BillBreakup extends StatelessWidget {
  const _BillBreakup({required this.order});
  final Map<String, dynamic> order;
  @override
  Widget build(BuildContext context) {
    final rows = [
      (
        'Item total',
        _num(order['subtotal'] ?? order['items_total'] ?? order['sub_total'])
      ),
      (
        'Packing charges',
        _num(order['packaging_charge'] ?? order['packing_charges'])
      ),
      ('Tax', _num(order['tax'] ?? order['tax_amount'])),
      ('Discount', -_num(order['discount'] ?? order['discount_amount'])),
      ('Bill Total', _num(order['total'] ?? order['grand_total'])),
    ].where((row) => row.$1 == 'Bill Total' || row.$2 != 0).toList();
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
          color: const Color(0xFFF0F0F0),
          borderRadius: BorderRadius.circular(10)),
      child: Column(
          children: rows.map((row) {
        final isTotal = row.$1 == 'Bill Total';
        return Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(children: [
              Expanded(
                  child: Text(row.$1,
                      textAlign: TextAlign.right,
                      style: TextStyle(
                          color: _DashboardPalette.ink,
                          fontSize: 14,
                          fontWeight:
                              isTotal ? FontWeight.w900 : FontWeight.w600))),
              const SizedBox(width: 18),
              SizedBox(
                  width: 88,
                  child: Text(formatCurrencyValue(context, row.$2),
                      textAlign: TextAlign.right,
                      style: TextStyle(
                          color: _DashboardPalette.ink,
                          fontSize: 14,
                          fontWeight:
                              isTotal ? FontWeight.w900 : FontWeight.w600)))
            ]));
      }).toList()),
    );
  }

  num _num(dynamic value) =>
      value is num ? value : num.tryParse(value?.toString() ?? '') ?? 0;
}

class _DashboardPalette {
  static const Color ink = Color(0xFF07132D);
  static const Color muted = Color(0xFF657085);
  static const Color faint = Color(0xFFEFF2F7);
  static const Color success = Color(0xFF20B85A);

  static Color get brand => FoodFlowTheme.orange;
  static Color get brandDark => FoodFlowTheme.orangeDark;
  static Color get brandSoft => FoodFlowTheme.orange.withOpacity(0.10);
  static LinearGradient get brandGradient => FoodFlowTheme.brandGradient;
}

class _LiveStoreControlCard extends StatelessWidget {
  final bool isOpen;
  final String subtitle;
  final VoidCallback onToggle;

  const _LiveStoreControlCard({
    required this.isOpen,
    required this.subtitle,
    required this.onToggle,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 20),
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 16),
      decoration: BoxDecoration(
        gradient: _DashboardPalette.brandGradient,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: _DashboardPalette.brand.withOpacity(0.20),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.20),
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Icon(
              Icons.storefront_outlined,
              color: Colors.white,
              size: 30,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Live Store Control',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    height: 1.08,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    height: 1.25,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Transform.scale(
                scale: 0.88,
                child: Switch(
                  value: isOpen,
                  onChanged: (_) => onToggle(),
                  activeColor: Colors.white,
                  activeTrackColor: _DashboardPalette.success,
                  inactiveThumbColor: Colors.white,
                  inactiveTrackColor: Colors.white.withOpacity(0.28),
                ),
              ),
              Text(
                isOpen ? 'OPEN' : 'CLOSED',
                style: TextStyle(
                  color: isOpen
                      ? _DashboardPalette.success
                      : Colors.white.withOpacity(0.72),
                  fontSize: 12,
                  height: 1,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DashboardMetricCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color color;
  final String? trend;

  const _DashboardMetricCard({
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
    this.trend,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 102),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: FoodFlowTheme.line),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.035),
            blurRadius: 14,
            offset: const Offset(0, 8),
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
                width: 38,
                height: 34,
                decoration: BoxDecoration(
                  color: color.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: color, size: 20),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 7,
                ),
                decoration: BoxDecoration(
                  color: _DashboardPalette.faint,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Text(
                  'TODAY',
                  style: TextStyle(
                    color: _DashboardPalette.muted,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      value,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: _DashboardPalette.ink,
                        fontSize: 14,
                        height: 1,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: _DashboardPalette.muted,
                        fontSize: 13,
                        height: 1,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              _MetricTrend(trend: trend),
            ],
          ),
        ],
      ),
    );
  }
}

class _MetricTrend extends StatelessWidget {
  final String? trend;

  const _MetricTrend({required this.trend});

  @override
  Widget build(BuildContext context) {
    if (trend == null) {
      return const Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            '-',
            style: TextStyle(
              color: _DashboardPalette.muted,
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
          SizedBox(height: 4),
          Text(
            'vs yesterday',
            style: TextStyle(
              color: _DashboardPalette.muted,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      );
    }

    final isNegative = trend!.startsWith('-');
    final color = isNegative ? FoodFlowTheme.danger : _DashboardPalette.success;
    final displayTrend = isNegative ? trend!.substring(1) : trend!;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              isNegative
                  ? Icons.arrow_downward_rounded
                  : Icons.arrow_upward_rounded,
              color: color,
              size: 18,
            ),
            const SizedBox(width: 4),
            Text(
              displayTrend,
              style: TextStyle(
                color: color,
                fontSize: 13,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        const Text(
          'vs yesterday',
          style: TextStyle(
            color: _DashboardPalette.muted,
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _DashboardSectionTitle extends StatelessWidget {
  final String title;
  final String subtitle;
  final VoidCallback onViewAll;

  const _DashboardSectionTitle({
    required this.title,
    required this.subtitle,
    required this.onViewAll,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: _DashboardPalette.ink,
                    fontSize: 14,
                    height: 1.15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _DashboardPalette.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          TextButton.icon(
            onPressed: onViewAll,
            style: TextButton.styleFrom(
              foregroundColor: _DashboardPalette.brand,
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
              textStyle: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w900,
              ),
            ),
            label: const Text('View All'),
            icon: const Icon(Icons.chevron_right_rounded, size: 23),
            iconAlignment: IconAlignment.end,
          ),
        ],
      ),
    );
  }
}

class _RunningStep extends StatelessWidget {
  final IconData icon;
  final String label;
  final int count;

  const _RunningStep({
    required this.icon,
    required this.label,
    required this.count,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: _DashboardPalette.success.withOpacity(0.12),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: _DashboardPalette.success, size: 23),
          ),
          const SizedBox(height: 8),
          Text(
            '$count',
            style: const TextStyle(
              color: _DashboardPalette.ink,
              fontSize: 14,
              height: 1,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _DashboardPalette.ink,
              fontSize: 10,
              height: 1,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
          Container(
            width: 36,
            height: 3,
            decoration: BoxDecoration(
              color: _DashboardPalette.success,
              borderRadius: BorderRadius.circular(999),
            ),
          ),
        ],
      ),
    );
  }
}

class _DashedConnector extends StatelessWidget {
  const _DashedConnector();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 32,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: List.generate(
          4,
          (_) => Container(
            width: 5,
            height: 2,
            decoration: BoxDecoration(
              color: _DashboardPalette.success.withOpacity(0.28),
              borderRadius: BorderRadius.circular(2),
            ),
          ),
        ),
      ),
    );
  }
}

class _RunningEmptyState extends StatelessWidget {
  const _RunningEmptyState();

  @override
  Widget build(BuildContext context) {
    return Stack(
      alignment: Alignment.center,
      children: [
        Align(
          alignment: Alignment.centerRight,
          child: Opacity(
            opacity: 0.12,
            child: Icon(
              Icons.assignment_outlined,
              color: _DashboardPalette.brand,
              size: 86,
            ),
          ),
        ),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            children: [
              Text(
                'No running orders right now',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: _DashboardPalette.ink,
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                ),
              ),
              SizedBox(height: 10),
              Text(
                'Orders will appear here automatically.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: _DashboardPalette.muted,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _PaymentPill extends StatelessWidget {
  final String label;

  const _PaymentPill({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: _DashboardPalette.success.withOpacity(0.10),
        borderRadius: BorderRadius.circular(9),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: _DashboardPalette.success,
          fontSize: 10,
          height: 1.1,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _OrderThumbnail extends StatelessWidget {
  final String imageUrl;

  const _OrderThumbnail({required this.imageUrl});

  @override
  Widget build(BuildContext context) {
    final colors = [
      _DashboardPalette.brand.withOpacity(0.78),
      _DashboardPalette.brandDark,
    ];

    final radius = BorderRadius.circular(10);
    final placeholder = Container(
      width: 48,
      height: 48,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: colors,
        ),
        borderRadius: radius,
      ),
      child: const Icon(
        Icons.restaurant_menu_rounded,
        color: Colors.white,
        size: 24,
      ),
    );

    if (imageUrl.isEmpty) return placeholder;

    return NetworkImageLoader(
      imageUrl: imageUrl,
      width: 48,
      height: 48,
      borderRadius: radius,
    );
  }
}

class _RestaurantBusinessScreen extends StatefulWidget {
  const _RestaurantBusinessScreen();

  @override
  State<_RestaurantBusinessScreen> createState() =>
      _RestaurantBusinessScreenState();
}

class _RestaurantBusinessScreenState extends State<_RestaurantBusinessScreen> {
  final ApiService _api = ApiService();
  Map<String, dynamic> _analytics = {};
  bool _loadingAnalytics = true;
  String? _loadedRestaurantId;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final selectedRestaurantId =
        context.watch<RestaurantProvider>().selectedRestaurantId?.toString() ??
            'all';
    if (_loadedRestaurantId != selectedRestaurantId) {
      _loadedRestaurantId = selectedRestaurantId;
      _loadAnalytics();
    }
  }

  Future<void> _loadAnalytics() async {
    setState(() => _loadingAnalytics = true);
    try {
      final response = await _api.get(
        ApiConstants.restaurantAnalytics,
        queryParams: const {'period': 'today'},
      );
      if (!mounted) return;
      setState(() {
        _analytics = response['success'] == true && response['data'] is Map
            ? Map<String, dynamic>.from(response['data'])
            : <String, dynamic>{};
      });
    } catch (error) {
      debugPrint('Business dashboard analytics error: $error');
    } finally {
      if (mounted) setState(() => _loadingAnalytics = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RestaurantProvider>();
    final user = context.watch<AuthProvider>().currentUser;
    final stats = provider.stats;
    final restaurant = provider.restaurant;
    final selectedLabel = provider.selectedRestaurantLabel;
    final isOpen = restaurant?['is_open'] == true || stats['is_open'] == true;
    final showDiningManagement = restaurant?['restaurant_type'] == 'both';

    void push(Widget screen) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
    }

    final quickLinks = <_BusinessActionData>[
      _BusinessActionData(
        icon: Icons.storefront_outlined,
        title: 'Outlets',
        subtitle: 'Select and switch status',
        onTap: () => push(const _RestaurantOutletSelectorScreen()),
      ),
      if (user?.canViewReports ?? true)
        _BusinessActionData(
          icon: Icons.bar_chart_rounded,
          title: 'Reports',
          subtitle: 'Performance and compare',
          onTap: () => push(const RestaurantAnalyticsScreen()),
        ),
      if (user?.isRestaurantOwner ?? true)
        _BusinessActionData(
          icon: Icons.account_balance_wallet_outlined,
          title: 'Payouts',
          subtitle: 'Settlements and wallet',
          onTap: () => push(const RestaurantWalletScreen()),
        ),
      if (user?.isRestaurantOwner ?? true)
        _BusinessActionData(
          icon: Icons.local_offer_outlined,
          title: 'Growth',
          subtitle: 'Promos and campaigns',
          onTap: () => push(const RestaurantPromosScreen()),
        ),
      if (user?.canViewMenu ?? true)
        _BusinessActionData(
          icon: Icons.menu_book_outlined,
          title: 'Menu',
          subtitle: 'Items and availability',
          onTap: () => push(const RestaurantMenuScreen()),
        ),
      _BusinessActionData(
        icon: Icons.receipt_long_outlined,
        title: 'Orders',
        subtitle: 'History and details',
        onTap: () => push(const RestaurantOrdersScreen(showAppBar: true)),
      ),
      if (user?.canViewOrders ?? true)
        _BusinessActionData(
          icon: Icons.warning_amber_rounded,
          title: 'Complaints',
          subtitle: 'Customer issues',
          onTap: () => push(const _RestaurantComplaintsScreen()),
        ),
      if (user?.canViewReports ?? true)
        _BusinessActionData(
          icon: Icons.star_border_rounded,
          title: 'Reviews',
          subtitle: 'Ratings feedback',
          onTap: () => push(const _RestaurantReviewsScreen()),
        ),
      _BusinessActionData(
        icon: Icons.notifications_none_rounded,
        title: 'Notifications',
        subtitle: 'Business updates',
        onTap: () => push(const RestaurantNotificationsScreen()),
      ),
      if (user?.isRestaurantOwner ?? true)
        _BusinessActionData(
          icon: Icons.settings_outlined,
          title: 'Settings',
          subtitle: 'Restaurant setup',
          onTap: () => push(const RestaurantSettingsScreen()),
        ),
      if (user?.isRestaurantOwner ?? true)
        _BusinessActionData(
          icon: Icons.print_outlined,
          title: 'Printers',
          subtitle: 'Kitchen tickets',
          onTap: () => push(const RestaurantPrintersScreen()),
        ),
      if (showDiningManagement && (user?.canViewOrders ?? true))
        _BusinessActionData(
          icon: Icons.event_seat_outlined,
          title: 'Dining',
          subtitle: 'Bookings',
          onTap: () => push(const RestaurantDiningScreen()),
        ),
      if (user?.canManageStaff ?? false)
        _BusinessActionData(
          icon: Icons.people_outline,
          title: 'Staff',
          subtitle: 'Permissions',
          onTap: () => push(const StaffManagementScreen()),
        ),
    ];

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      body: RefreshIndicator(
        onRefresh: () async {
          await provider.loadDashboardData();
          await _loadAnalytics();
        },
        color: _DashboardPalette.brand,
        child: ListView(
          padding: EdgeInsets.fromLTRB(
            14,
            12,
            14,
            18 + MediaQuery.of(context).padding.bottom,
          ),
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Business Dashboard',
                    style: TextStyle(
                      color: _DashboardPalette.ink,
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                TextButton.icon(
                  onPressed: () =>
                      push(const _RestaurantOutletSelectorScreen()),
                  icon: const Icon(Icons.storefront_outlined, size: 18),
                  label: const Text('Outlets'),
                ),
              ],
            ),
            const SizedBox(height: 8),
            _BusinessOutletHeader(
              title: selectedLabel,
              online: isOpen,
              subtitle: isOpen ? 'Receiving orders' : 'Not receiving orders',
            ),
            const SizedBox(height: 12),
            _BusinessSummaryGrid(
              stats: stats,
              pendingOrders: provider.pendingOrders.length,
              activeOrders: provider.activeOrders.length,
            ),
            const SizedBox(height: 12),
            _BusinessOrdersChart(
              data: _asList(_analytics['hourly_data']),
              loading: _loadingAnalytics,
            ),
            const SizedBox(height: 14),
            const Text(
              'Quick Links',
              style: TextStyle(
                color: _DashboardPalette.ink,
                fontSize: 16,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            _BusinessQuickLinksGrid(items: quickLinks),
          ],
        ),
      ),
    );
  }

  static List<dynamic> _asList(dynamic value) =>
      value is List ? value : const [];
}

class _BusinessOutletHeader extends StatelessWidget {
  const _BusinessOutletHeader({
    required this.title,
    required this.subtitle,
    required this.online,
  });

  final String title;
  final String subtitle;
  final bool online;

  @override
  Widget build(BuildContext context) {
    final color = online ? _DashboardPalette.success : FoodFlowTheme.danger;
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: color.withOpacity(0.12),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(Icons.business_center_rounded, color: color, size: 21),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _DashboardPalette.ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(
                    color: color,
                    fontSize: 12,
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
}

class _BusinessSummaryGrid extends StatelessWidget {
  const _BusinessSummaryGrid({
    required this.stats,
    required this.pendingOrders,
    required this.activeOrders,
  });

  final Map<String, dynamic> stats;
  final int pendingOrders;
  final int activeOrders;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 8,
      crossAxisSpacing: 8,
      childAspectRatio: 2.15,
      children: [
        _BusinessMetricTile(
            label: 'Today orders', value: '${stats['today_orders'] ?? 0}'),
        _BusinessMetricTile(
            label: 'Today sales',
            value: formatCurrencyValue(
                context, _metricNum(stats['today_revenue']))),
        _BusinessMetricTile(label: 'Pending', value: '$pendingOrders'),
        _BusinessMetricTile(label: 'Active', value: '$activeOrders'),
      ],
    );
  }
}

class _BusinessOrdersChart extends StatelessWidget {
  const _BusinessOrdersChart({required this.data, required this.loading});

  final List<dynamic> data;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final rows = data.map(_map).toList();
    final visible = rows.where((row) => _num(row['orders']) > 0).toList();
    final chartRows = visible.isEmpty
        ? rows.where((row) => (_num(row['hour']) % 4) == 0).toList()
        : visible;
    final maxOrders = chartRows.fold<num>(
        0, (max, row) => _num(row['orders']) > max ? _num(row['orders']) : max);

    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Expanded(
                child: Text(
                  'Today Orders',
                  style: TextStyle(
                    color: _DashboardPalette.ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Text(
                'Time wise',
                style: TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (loading)
            const SizedBox(
              height: 104,
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            )
          else if (chartRows.isEmpty)
            const SizedBox(
              height: 104,
              child: Center(
                child: Text(
                  'No order activity today',
                  style: TextStyle(
                    color: FoodFlowTheme.muted,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            )
          else
            SizedBox(
              height: 118,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: chartRows.map((row) {
                  final orders = _num(row['orders']);
                  final hour = _num(row['hour']).toInt();
                  final factor = maxOrders <= 0
                      ? 0.05
                      : (orders / maxOrders).clamp(0.05, 1.0);
                  return Expanded(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 2),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          Flexible(
                            child: FractionallySizedBox(
                              heightFactor: factor.toDouble(),
                              alignment: Alignment.bottomCenter,
                              child: Container(
                                decoration: BoxDecoration(
                                  color: orders > 0
                                      ? _DashboardPalette.brand
                                      : _DashboardPalette.faint,
                                  borderRadius: BorderRadius.circular(7),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            '$hour',
                            style: const TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
        ],
      ),
    );
  }

  static Map<String, dynamic> _map(dynamic value) => value
          is Map<String, dynamic>
      ? value
      : (value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{});

  static num _num(dynamic value) =>
      value is num ? value : num.tryParse(value?.toString() ?? '') ?? 0;
}

class _BusinessQuickLinksGrid extends StatelessWidget {
  const _BusinessQuickLinksGrid({required this.items});

  final List<_BusinessActionData> items;

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: items.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: 8,
        crossAxisSpacing: 8,
        childAspectRatio: 0.98,
      ),
      itemBuilder: (context, index) =>
          _BusinessQuickLinkTile(data: items[index]),
    );
  }
}

class _BusinessQuickLinkTile extends StatelessWidget {
  const _BusinessQuickLinkTile({required this.data});

  final _BusinessActionData data;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: data.onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: FoodFlowTheme.line),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: _DashboardPalette.brand.withOpacity(0.10),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(data.icon, color: _DashboardPalette.brand, size: 19),
            ),
            const SizedBox(height: 8),
            Text(
              data.title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: _DashboardPalette.ink,
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

num _metricNum(dynamic value) {
  if (value is num) return value;
  return num.tryParse(value?.toString() ?? '') ?? 0;
}

class _BusinessActionData {
  const _BusinessActionData({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
}

class _BusinessSection extends StatelessWidget {
  const _BusinessSection({required this.title, required this.items});

  final String title;
  final List<_BusinessActionData> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(2, 0, 2, 8),
            child: Text(
              title,
              style: const TextStyle(
                color: FoodFlowTheme.muted,
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: FoodFlowTheme.line),
            ),
            child: Column(
              children: [
                for (var index = 0; index < items.length; index++) ...[
                  _BusinessActionTile(data: items[index]),
                  if (index != items.length - 1)
                    const Divider(height: 1, color: FoodFlowTheme.line),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BusinessMetricTile extends StatelessWidget {
  const _BusinessMetricTile({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style: const TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          Text(value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                  color: _DashboardPalette.ink,
                  fontSize: 20,
                  fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }
}

class _BusinessActionTile extends StatelessWidget {
  const _BusinessActionTile({required this.data});

  final _BusinessActionData data;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: data.onTap,
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: _DashboardPalette.brand.withOpacity(0.1),
                borderRadius: BorderRadius.circular(11),
              ),
              child: Icon(data.icon, color: _DashboardPalette.brand, size: 21),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(data.title,
                      style: const TextStyle(
                          color: _DashboardPalette.ink,
                          fontSize: 14,
                          fontWeight: FontWeight.w900)),
                  const SizedBox(height: 2),
                  Text(data.subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w700)),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: FoodFlowTheme.muted),
          ],
        ),
      ),
    );
  }
}

class _RestaurantComplaintsScreen extends StatefulWidget {
  const _RestaurantComplaintsScreen();

  @override
  State<_RestaurantComplaintsScreen> createState() =>
      _RestaurantComplaintsScreenState();
}

class _RestaurantComplaintsScreenState
    extends State<_RestaurantComplaintsScreen> {
  final ApiService _api = ApiService();
  bool _loading = true;
  String? _error;
  String? _loadedRestaurantId;
  Map<String, dynamic> _summary = {};
  List<Map<String, dynamic>> _complaints = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final selectedRestaurantId =
        context.watch<RestaurantProvider>().selectedRestaurantId?.toString();
    if (_loadedRestaurantId != selectedRestaurantId) {
      _loadedRestaurantId = selectedRestaurantId;
      _loadComplaints();
    }
  }

  Future<void> _loadComplaints() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await _api.get(ApiConstants.restaurantComplaints);
      final data = _asMap(response['data']);
      if (!mounted) return;
      setState(() {
        _summary = _asMap(data['summary']);
        _complaints = _asMapList(data['complaints']);
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.toString().replaceFirst('Exception: ', '');
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return _RestaurantListShell(
      title: 'Complaints',
      icon: Icons.warning_amber_rounded,
      loading: _loading,
      error: _error,
      emptyText: 'Complaints from real orders will appear here.',
      onRefresh: _loadComplaints,
      summary: [
        _SummaryTileData(
          label: 'Open',
          value: _summary['open_complaints']?.toString() ?? '0',
          color: const Color(0xFFE2546A),
        ),
        _SummaryTileData(
          label: 'Resolved',
          value: _summary['resolved_complaints']?.toString() ?? '0',
          color: const Color(0xFF12A66A),
        ),
        _SummaryTileData(
          label: 'Total',
          value: _summary['total_complaints']?.toString() ?? '0',
          color: _DashboardPalette.ink,
        ),
      ],
      children: _complaints
          .map((complaint) => _ComplaintCard(complaint: complaint))
          .toList(),
    );
  }
}

class _RestaurantReviewsScreen extends StatefulWidget {
  const _RestaurantReviewsScreen();

  @override
  State<_RestaurantReviewsScreen> createState() =>
      _RestaurantReviewsScreenState();
}

class _RestaurantReviewsScreenState extends State<_RestaurantReviewsScreen> {
  final ApiService _api = ApiService();
  bool _loading = true;
  String? _error;
  String? _loadedRestaurantId;
  Map<String, dynamic> _summary = {};
  List<Map<String, dynamic>> _reviews = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final selectedRestaurantId =
        context.watch<RestaurantProvider>().selectedRestaurantId?.toString();
    if (_loadedRestaurantId != selectedRestaurantId) {
      _loadedRestaurantId = selectedRestaurantId;
      _loadReviews();
    }
  }

  Future<void> _loadReviews() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await _api.get(ApiConstants.restaurantReviews);
      final data = _asMap(response['data']);
      if (!mounted) return;
      setState(() {
        _summary = _asMap(data['summary']);
        _reviews = _asMapList(data['reviews']);
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.toString().replaceFirst('Exception: ', '');
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final averageRating = _summary['average_rating'];
    return _RestaurantListShell(
      title: 'Reviews',
      icon: Icons.star_border_rounded,
      loading: _loading,
      error: _error,
      emptyText: 'Customer reviews will appear here once available.',
      onRefresh: _loadReviews,
      summary: [
        _SummaryTileData(
          label: 'Average',
          value: averageRating == null ? '-' : averageRating.toString(),
          color: _DashboardPalette.brand,
        ),
        _SummaryTileData(
          label: 'Reviews',
          value: _summary['total_reviews']?.toString() ?? '0',
          color: _DashboardPalette.ink,
        ),
      ],
      children: _reviews.map((review) => _ReviewCard(review: review)).toList(),
    );
  }
}

class _RestaurantListShell extends StatelessWidget {
  const _RestaurantListShell({
    required this.title,
    required this.icon,
    required this.loading,
    required this.error,
    required this.emptyText,
    required this.onRefresh,
    required this.summary,
    required this.children,
  });

  final String title;
  final IconData icon;
  final bool loading;
  final String? error;
  final String emptyText;
  final Future<void> Function() onRefresh;
  final List<_SummaryTileData> summary;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      body: RefreshIndicator(
        onRefresh: onRefresh,
        color: _DashboardPalette.brand,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(22, 22, 22, 14),
                child: Text(
                  title,
                  style: const TextStyle(
                    color: _DashboardPalette.ink,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
            if (!loading && error == null && summary.isNotEmpty)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                  child: Row(
                    children: summary
                        .map((item) => Expanded(
                              child: Padding(
                                padding:
                                    const EdgeInsets.symmetric(horizontal: 4),
                                child: _SummaryTile(data: item),
                              ),
                            ))
                        .toList(),
                  ),
                ),
              ),
            if (loading)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: Center(child: CircularProgressIndicator()),
              )
            else if (error != null)
              SliverFillRemaining(
                hasScrollBody: false,
                child: FoodFlowTheme.emptyState(
                  icon: Icons.cloud_off_rounded,
                  title: 'Could not load $title',
                  subtitle: error!,
                ),
              )
            else if (children.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: FoodFlowTheme.emptyState(
                  icon: icon,
                  title: 'No $title',
                  subtitle: emptyText,
                ),
              )
            else
              SliverList.separated(
                itemCount: children.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (context, index) => Padding(
                  padding: EdgeInsets.fromLTRB(
                    16,
                    index == 0 ? 0 : 0,
                    16,
                    index == children.length - 1 ? 26 : 0,
                  ),
                  child: children[index],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _SummaryTileData {
  const _SummaryTileData({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({required this.data});

  final _SummaryTileData data;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(data.label,
              style: const TextStyle(
                  color: FoodFlowTheme.muted,
                  fontWeight: FontWeight.w800,
                  fontSize: 12)),
          const SizedBox(height: 7),
          Text(data.value,
              style: TextStyle(
                  color: data.color,
                  fontWeight: FontWeight.w900,
                  fontSize: 22)),
        ],
      ),
    );
  }
}

class _ComplaintCard extends StatelessWidget {
  const _ComplaintCard({required this.complaint});

  final Map<String, dynamic> complaint;

  @override
  Widget build(BuildContext context) {
    final status = _cleanText(complaint['status'], 'open');
    return _DataCard(
      leading: Icons.warning_amber_rounded,
      title: _cleanText(complaint['subject'], 'Complaint'),
      trailing: _StatusPill(text: status),
      lines: [
        _cleanText(complaint['description']),
        [
          _cleanText(complaint['customer_name']),
          _cleanText(complaint['restaurant_name']),
        ].where((text) => text.isNotEmpty).join(' - '),
        _formatDate(complaint['created_at']),
      ].where((text) => text.isNotEmpty).toList(),
    );
  }
}

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({required this.review});

  final Map<String, dynamic> review;

  @override
  Widget build(BuildContext context) {
    final rating = int.tryParse('${review['rating']}') ?? 0;
    return _DataCard(
      leading: Icons.star_rounded,
      title: _cleanText(review['customer_name'], 'Customer'),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: List.generate(
          5,
          (index) => Icon(
            index < rating ? Icons.star_rounded : Icons.star_border_rounded,
            size: 17,
            color: _DashboardPalette.brand,
          ),
        ),
      ),
      lines: [
        _cleanText(review['comment']),
        [
          _cleanText(review['restaurant_name']),
          _cleanText(review['order_number']).isEmpty
              ? ''
              : '#${_cleanText(review['order_number'])}',
        ].where((text) => text.isNotEmpty).join(' - '),
        _formatDate(review['created_at']),
      ].where((text) => text.isNotEmpty).toList(),
    );
  }
}

class _DataCard extends StatelessWidget {
  const _DataCard({
    required this.leading,
    required this.title,
    required this.trailing,
    required this.lines,
  });

  final IconData leading;
  final String title;
  final Widget trailing;
  final List<String> lines;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: FoodFlowTheme.line),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: _DashboardPalette.brand.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(leading, color: _DashboardPalette.brand),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        title,
                        style: const TextStyle(
                          color: _DashboardPalette.ink,
                          fontWeight: FontWeight.w900,
                          fontSize: 14,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    trailing,
                  ],
                ),
                for (final line in lines) ...[
                  const SizedBox(height: 7),
                  Text(
                    line,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontWeight: FontWeight.w700,
                      height: 1.25,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    final normalized = text.toLowerCase();
    final isResolved = normalized == 'resolved' || normalized == 'closed';
    final color =
        isResolved ? const Color(0xFF12A66A) : const Color(0xFFE2546A);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text.replaceAll('_', ' '),
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w900,
          fontSize: 11,
        ),
      ),
    );
  }
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map) return Map<String, dynamic>.from(value);
  return <String, dynamic>{};
}

List<Map<String, dynamic>> _asMapList(dynamic value) {
  if (value is List) {
    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }
  return <Map<String, dynamic>>[];
}

String _cleanText(dynamic value, [String fallback = '']) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

String _formatDate(dynamic value) {
  final text = _cleanText(value);
  if (text.isEmpty) return '';
  final date = DateTime.tryParse(text)?.toLocal();
  if (date == null) return text;
  final minute = date.minute.toString().padLeft(2, '0');
  return '${date.day}/${date.month}/${date.year} ${date.hour}:$minute';
}

class _DashboardNavItem {
  final String key;
  final String title;
  final Widget screen;
  final BottomNavigationBarItem navItem;

  const _DashboardNavItem({
    required this.key,
    required this.title,
    required this.screen,
    required this.navItem,
  });
}
