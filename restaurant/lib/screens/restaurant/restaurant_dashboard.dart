// lib/screens/restaurant/restaurant_dashboard.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/app_config.dart';
import '../../config/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../providers/restaurant_provider.dart';
import '../../services/api_service.dart';
import '../../services/incoming_order_alert_service.dart';
import '../../services/order_alert_permission_manager.dart';
import '../../services/sound_service.dart';
import '../../services/websocket_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/common/network_image_loader.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';
import '../../utils/route_observer.dart';
import 'restaurant_orders_screen.dart';
import 'restaurant_menu_screen.dart';
import 'restaurant_analytics_screen.dart';
import 'restaurant_promos_screen.dart';
import 'restaurant_printers_screen.dart';
import 'restaurant_info_screen.dart';
import 'staff_management_screen.dart';
import 'restaurant_dining_screen.dart';
import 'restaurant_wallet_screen.dart';
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
  bool _isWebSocketInitialized = false;
  bool _isPollingOrders = false;
  Timer? _orderPollingTimer;
  final Set<int> _knownPendingOrderIds = {};
  int _unreadNotificationCount = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initializeRealtimeOrders();
      _loadUnreadNotificationCount();
    });
  }

  Future<void> _initializeRealtimeOrders() async {
    if (_isWebSocketInitialized) return;

    final restaurantProvider = Provider.of<RestaurantProvider>(
      context,
      listen: false,
    );
    await restaurantProvider.loadDashboardData();
    _rememberPendingOrders(restaurantProvider.pendingOrders);
    _startOrderPollingFallback();

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
      return;
    }

    for (final restaurantId in restaurantIds) {
      await WebSocketService().initRestaurant(
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
    }

    _isWebSocketInitialized = true;
  }

  void _startOrderPollingFallback() {
    _orderPollingTimer?.cancel();
    _orderPollingTimer = Timer.periodic(const Duration(seconds: 12), (_) {
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
    WebSocketService().dispose();
    SoundService.stopIncomingOrderAlarm();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final user = authProvider.currentUser;
    final navItems = _buildNavItems(user);
    final restaurantProvider = Provider.of<RestaurantProvider>(context);
    final pendingNotificationCount = _unreadNotificationCount > 0
        ? _unreadNotificationCount
        : (restaurantProvider.pendingOrdersCount > 0
              ? restaurantProvider.pendingOrdersCount
              : restaurantProvider.pendingOrders.length);
    final effectiveIndex = _currentIndex >= navItems.length ? 0 : _currentIndex;

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(80),
        child: Container(
          padding: EdgeInsets.only(
            top: MediaQuery.of(context).padding.top,
            left: 12,
            right: 12,
            bottom: 10,
          ),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(bottom: BorderSide(color: FoodFlowTheme.line)),
          ),
          child: Row(
            children: [
              Builder(
                builder: (context) => SizedBox(
                  width: 40,
                  height: 40,
                  child: IconButton(
                    onPressed: () => Scaffold.of(context).openDrawer(),
                    icon: const Icon(Icons.menu_rounded, size: 26),
                    color: _DashboardPalette.ink,
                    padding: EdgeInsets.zero,
                  ),
                ),
              ),
              const SizedBox(width: 6),
              _RestaurantScopeSelector(
                selectedRestaurantId: restaurantProvider.selectedRestaurantId,
                restaurants: restaurantProvider.restaurants,
                onChanged: (restaurantId) {
                  restaurantProvider.selectRestaurant(restaurantId);
                },
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      navItems[effectiveIndex].title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: _DashboardPalette.ink,
                        fontSize: 17,
                        height: 1.05,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const Text(
                      'Role-aware store workspace',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: _DashboardPalette.muted,
                        fontSize: 11,
                        height: 1.2,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: FoodFlowTheme.line),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.035),
                      blurRadius: 12,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: IconButton(
                  icon: Badge(
                    isLabelVisible: pendingNotificationCount > 0,
                    label: Text('$pendingNotificationCount'),
                    child: const Icon(Icons.notifications_outlined),
                  ),
                  color: FoodFlowTheme.orange,
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const RestaurantNotificationsScreen(),
                      ),
                    ).then((_) => _loadUnreadNotificationCount());
                  },
                ),
              ),
            ],
          ),
        ),
      ),
      drawer: _buildDrawer(),
      body: IndexedStack(
        index: effectiveIndex,
        children: navItems.map((item) => item.screen).toList(),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: FoodFlowTheme.line),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.06),
                blurRadius: 22,
                offset: const Offset(0, 10),
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
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
              unselectedLabelStyle: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
            child: BottomNavigationBar(
              currentIndex: effectiveIndex,
              onTap: (index) {
                setState(() => _currentIndex = index);
              },
              elevation: 0,
              type: BottomNavigationBarType.fixed,
              items: navItems.map((item) => item.navItem).toList(),
            ),
          ),
        ),
      ),
    );
  }

  List<_DashboardNavItem> _buildNavItems(user) {
    final items = <_DashboardNavItem>[
      const _DashboardNavItem(
        key: 'dashboard',
        title: 'Dashboard',
        screen: RestaurantHomeContent(),
        navItem: BottomNavigationBarItem(
          icon: Icon(Icons.dashboard_outlined),
          activeIcon: Icon(Icons.dashboard),
          label: 'Dashboard',
        ),
      ),
    ];

    if (user?.canViewOrders ?? true) {
      items.add(
        const _DashboardNavItem(
          key: 'orders',
          title: 'Orders',
          screen: RestaurantOrdersScreen(),
          navItem: BottomNavigationBarItem(
            icon: Icon(Icons.receipt_outlined),
            activeIcon: Icon(Icons.receipt),
            label: 'Orders',
          ),
        ),
      );
    }

    if (user?.canViewMenu ?? true) {
      items.add(
        const _DashboardNavItem(
          key: 'menu',
          title: 'Menu',
          screen: RestaurantMenuScreen(),
          navItem: BottomNavigationBarItem(
            icon: Icon(Icons.menu_book_outlined),
            activeIcon: Icon(Icons.menu_book),
            label: 'Menu',
          ),
        ),
      );
    }

    if (user?.canViewReports ?? true) {
      items.add(
        const _DashboardNavItem(
          key: 'analytics',
          title: 'Analytics',
          screen: RestaurantAnalyticsScreen(),
          navItem: BottomNavigationBarItem(
            icon: Icon(Icons.analytics_outlined),
            activeIcon: Icon(Icons.analytics),
            label: 'Analytics',
          ),
        ),
      );
    }

    if (user?.isRestaurantOwner ?? true) {
      items.add(
        const _DashboardNavItem(
          key: 'wallet',
          title: 'Wallet',
          screen: RestaurantWalletScreen(),
          navItem: BottomNavigationBarItem(
            icon: Icon(Icons.account_balance_wallet_outlined),
            activeIcon: Icon(Icons.account_balance_wallet),
            label: 'Wallet',
          ),
        ),
      );
    }

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
                      fontSize: 24,
                      color: FoodFlowTheme.orange,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user?.name ?? 'Restaurant Owner',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 16,
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
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
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

    final raw =
        restaurant['logo_url'] ??
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

  Map<String, dynamic> _stats = {};
  List<dynamic> _recentOrders = [];
  List<dynamic> _runningOrders = [];
  bool _isLoading = true;
  String? _loadError;
  bool _isOpen = false;

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return Map<String, dynamic>.from(value);
    return <String, dynamic>{};
  }

  List<dynamic> _asList(dynamic value) {
    if (value is List) return List<dynamic>.from(value);
    return <dynamic>[];
  }

  @override
  void initState() {
    super.initState();
    _loadData();
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
            backgroundColor: _isOpen
                ? FoodFlowTheme.success
                : FoodFlowTheme.danger,
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

    final user = Provider.of<AuthProvider>(context).currentUser;
    final branchLabel = user?.hasBranch == true ? user!.branchLabel : null;
    final statusText = _isOpen
        ? 'Accepting orders now. Keep prep times sharp.'
        : 'Your store is marked closed. Switch on when ready.';

    return RefreshIndicator(
      onRefresh: _loadData,
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(0, 14, 0, 28),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _LiveStoreControlCard(
              isOpen: _isOpen,
              subtitle: branchLabel == null
                  ? statusText
                  : '$branchLabel  |  $statusText',
              onToggle: _toggleRestaurantStatus,
            ),
            _buildMetricGrid(context),
            const SizedBox(height: 24),
            _DashboardSectionTitle(
              title: 'Running Orders',
              subtitle: 'Accepted orders moving through prep and delivery',
              onViewAll: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) =>
                      const RestaurantOrdersScreen(showAppBar: true),
                ),
              ),
            ),
            _buildRunningOrdersPanel(),
            const SizedBox(height: 24),
            _DashboardSectionTitle(
              title: 'Recent Orders',
              subtitle: 'Newest tickets waiting for action',
              onViewAll: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) =>
                      const RestaurantOrdersScreen(showAppBar: true),
                ),
              ),
            ),
            _buildRecentOrdersPanel(context),
          ],
        ),
      ),
    );
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
    final raw =
        (order['payment_method'] ??
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
    dynamic raw =
        order['image_url'] ??
        order['image'] ??
        order['thumbnail_url'] ??
        order['restaurant_image'];

    final items = order['items'];
    if ((raw == null || raw.toString().isEmpty) &&
        items is List &&
        items.isNotEmpty) {
      final firstItem = items.first;
      if (firstItem is Map) {
        raw =
            firstItem['image_url'] ??
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
                    fontSize: 18,
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
                    fontSize: 18,
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
                        fontSize: 18,
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
              fontSize: 16,
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
                    fontSize: 18,
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
              fontSize: 17,
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
                  fontSize: 16,
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
