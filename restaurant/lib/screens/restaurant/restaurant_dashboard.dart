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
import '../../services/sound_service.dart';
import '../../services/websocket_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
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
        preferredSize: const Size.fromHeight(64),
        child: Container(
          padding: EdgeInsets.only(
            top: MediaQuery.of(context).padding.top,
            left: 12,
            right: 16,
            bottom: 8,
          ),
          decoration: const BoxDecoration(
            color: FoodFlowTheme.canvas,
            border: Border(bottom: BorderSide(color: FoodFlowTheme.line)),
          ),
          child: Row(
            children: [
              Builder(
                builder: (context) => IconButton(
                  onPressed: () => Scaffold.of(context).openDrawer(),
                  icon: const Icon(Icons.menu),
                ),
              ),
              _RestaurantScopeSelector(
                selectedRestaurantId: restaurantProvider.selectedRestaurantId,
                restaurants: restaurantProvider.restaurants,
                onChanged: (restaurantId) {
                  restaurantProvider.selectRestaurant(restaurantId);
                },
              ),
              const SizedBox(width: 10),
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
                        color: FoodFlowTheme.ink,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const Text(
                      'Role-aware restaurant workspace',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: FoodFlowTheme.line),
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
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 10,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: effectiveIndex,
          onTap: (index) {
            setState(() => _currentIndex = index);
          },
          type: BottomNavigationBarType.fixed,
          items: navItems.map((item) => item.navItem).toList(),
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
      backgroundColor: FoodFlowTheme.canvas,
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(18, 54, 18, 18),
            decoration: BoxDecoration(gradient: RestaurantPremium.darkGradient),
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
          const SizedBox(height: 8),
          ListTile(
            leading: const Icon(Icons.person_outline),
            title: const Text('Profile'),
            subtitle: const Text('Restaurant details, timings and account'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(
                  context,
                  MaterialPageRoute(
                      builder: (_) => const RestaurantProfileScreen()));
            },
          ),
          if (user?.isRestaurantOwner ?? true)
            ListTile(
              leading: const Icon(Icons.store),
              title: const Text('Restaurant Info'),
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
          if (user?.canManageStaff ?? false)
            ListTile(
              leading: const Icon(Icons.people_outline),
              title: const Text('Staff Management'),
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
            ListTile(
              leading: const Icon(Icons.local_offer_outlined),
              title: const Text('Promotions'),
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
            ListTile(
              leading: const Icon(Icons.event_seat_outlined),
              title: const Text('Dining Management'),
              subtitle: const Text('Bookings and table settings'),
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
            ListTile(
              leading: const Icon(Icons.print_outlined),
              title: const Text('Printers'),
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
          const Divider(),
          ListTile(
            leading: const Icon(Icons.help_outline),
            title: const Text('Help & Support'),
            onTap: () {
              Navigator.pop(context);
              Navigator.pushNamed(context, '/restaurant/profile/help');
            },
          ),
          ListTile(
            leading: const Icon(Icons.logout, color: Colors.red),
            title: const Text('Logout', style: TextStyle(color: Colors.red)),
            onTap: () async {
              Navigator.pop(context);
              await Provider.of<AuthProvider>(context, listen: false).logout();
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

    return ClipRRect(
      borderRadius: BorderRadius.circular(size * 0.32),
      child: url.isEmpty
          ? Container(
              width: size,
              height: size,
              color: const Color(0xFFFFF3E8),
              child: Icon(fallbackIcon, color: iconColor, size: size * 0.62),
            )
          : Image.network(
              url,
              width: size,
              height: size,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) => Container(
                width: size,
                height: size,
                color: const Color(0xFFFFF3E8),
                child: Icon(fallbackIcon, color: iconColor, size: size * 0.62),
              ),
            ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final selectedMatches = restaurants.where(
      (item) => _asInt(item['id']) == selectedRestaurantId,
    );
    final selectedName = selectedRestaurantId == null
        ? 'All'
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
        constraints: const BoxConstraints(maxWidth: 150),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
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
                    Icons.dashboard_customize_outlined,
                    color: FoodFlowTheme.orange,
                    size: 18,
                  )
                : _restaurantLogo(selectedRestaurant, size: 24),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                selectedName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: FoodFlowTheme.orange,
                  fontSize: 14,
                  height: 1.05,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            const SizedBox(width: 2),
            Icon(
              Icons.keyboard_arrow_down,
              color: FoodFlowTheme.orange,
              size: 18,
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
      _isOpen = _restaurantProvider.isOpen ?? _stats['is_open'] ?? _isOpen;
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
        final data = response['data'] as Map<String, dynamic>? ?? {};
        setState(() {
          _stats = data['stats'] ?? {};
          _recentOrders = data['pending_orders'] ?? [];
          _runningOrders = data['active_orders'] ?? [];
          _isOpen = _stats['is_open'] ?? false;
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

    final user = Provider.of<AuthProvider>(context).currentUser;
    final branchLabel = user?.hasBranch == true ? user!.branchLabel : null;

    return RefreshIndicator(
      onRefresh: _loadData,
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(0, 0, 0, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            PremiumRestaurantHeader(
              title: 'Live Restaurant Control',
              subtitle: [
                if (branchLabel != null) branchLabel,
                _isOpen
                    ? 'Accepting orders now. Keep prep times sharp.'
                    : 'Your store is marked closed. Switch on when ready.',
              ].join(' | '),
              icon: _isOpen ? Icons.storefront : Icons.storefront_outlined,
              trailing: Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Switch(
                    value: _isOpen,
                    onChanged: (_) => _toggleRestaurantStatus(),
                    activeColor: Colors.white,
                    activeTrackColor: FoodFlowTheme.success.withOpacity(0.7),
                    inactiveThumbColor: Colors.white,
                    inactiveTrackColor: Colors.white24,
                  ),
                  Text(
                    _isOpen ? 'OPEN' : 'CLOSED',
                    style: TextStyle(
                      color: _isOpen
                          ? FoodFlowTheme.success
                          : Colors.white.withOpacity(0.62),
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  Expanded(
                    child: PremiumMetricCard(
                      title: 'Revenue',
                      value: formatCurrencyValue(
                        context,
                        _stats['today_revenue'],
                      ),
                      icon: Icons.currency_rupee,
                      color: FoodFlowTheme.success,
                      caption: 'TODAY',
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: PremiumMetricCard(
                      title: 'Orders',
                      value: '${_stats['today_orders'] ?? 0}',
                      icon: Icons.receipt_long,
                      color: Colors.blue,
                      caption: 'TODAY',
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  Expanded(
                    child: PremiumMetricCard(
                      title: 'Total Orders',
                      value: '${_stats['total_orders'] ?? 0}',
                      icon: Icons.shopping_bag_outlined,
                      color: RestaurantPremium.gold,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: PremiumMetricCard(
                      title: 'Customers',
                      value: '${_stats['total_customers'] ?? 0}',
                      icon: Icons.people_alt_outlined,
                      color: Colors.purple,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 22),
            PremiumSectionTitle(
              title: 'Running Orders',
              subtitle: 'Accepted orders moving through prep and delivery',
              trailing: TextButton(
                onPressed: () {},
                child: const Text('View All'),
              ),
            ),
            _buildOrderList(_runningOrders, 'No running orders right now'),
            const SizedBox(height: 22),
            PremiumSectionTitle(
              title: 'Recent Orders',
              subtitle: 'Newest tickets waiting for action',
              trailing: TextButton(
                onPressed: () {},
                child: const Text('View All'),
              ),
            ),
            if (_recentOrders.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  decoration: RestaurantPremium.panel(radius: 18),
                  child: FoodFlowTheme.emptyState(
                    icon: Icons.receipt_long_outlined,
                    title: 'No orders yet',
                    subtitle: 'Fresh orders will land here instantly.',
                  ),
                ),
              )
            else
              ListView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: _recentOrders.length > 5 ? 5 : _recentOrders.length,
                itemBuilder: (context, index) {
                  final order = _recentOrders[index];
                  final status = order['status'] ?? 'pending';
                  final statusColor = _getStatusColor(status);
                  return Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: RestaurantPremium.panel(radius: 16),
                    child: ListTile(
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 8,
                      ),
                      minVerticalPadding: 10,
                      isThreeLine: true,
                      leading: Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [
                              statusColor.withOpacity(0.95),
                              statusColor.withOpacity(0.7),
                            ],
                          ),
                          borderRadius: BorderRadius.circular(15),
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          order['order_number'] != null &&
                                  order['order_number'].length > 2
                              ? order['order_number'][2]
                              : 'O',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                            fontSize: 18,
                          ),
                        ),
                      ),
                      title: Text(
                        'Order #${order['order_number'] ?? 'N/A'}',
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      subtitle: Text(
                        '${formatCurrencyValue(context, order['total'])} - ${order['customer_name'] ?? 'Guest'}',
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontWeight: FontWeight.w700,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      trailing: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          _getStatusText(status),
                          style: TextStyle(
                            color: statusColor,
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
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
              ),
          ],
        ),
      ),
    );
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
        return Colors.blue;
      case 'preparing':
        return Colors.purple;
      case 'ready_for_pickup':
        return Colors.teal;
      case 'picked_up':
        return Colors.indigo;
      case 'on_the_way':
        return Colors.cyan;
      case 'delivered':
        return Colors.green;
      case 'cancelled':
        return Colors.red;
      default:
        return Colors.grey;
    }
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
