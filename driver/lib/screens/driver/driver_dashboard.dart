// lib/screens/driver/driver_dashboard.dart

import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../providers/auth_provider.dart';
import '../../services/location_service.dart';
import '../../services/api_service.dart';
import '../../services/foreground_service_manager.dart';
import '../../services/incoming_order_alert_service.dart';
import '../../services/sound_service.dart';
import '../../services/websocket_service.dart';
import '../../config/api_constants.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import 'driver_orders_screen.dart';
import 'driver_gigs_screen.dart';
import 'driver_earnings_screen.dart';
import 'driver_profile_screen.dart';
import 'driver_wallet_screen.dart';

class DriverDashboard extends StatefulWidget {
  const DriverDashboard({super.key});

  @override
  State<DriverDashboard> createState() => _DriverDashboardState();
}

class _DriverDashboardState extends State<DriverDashboard>
    with WidgetsBindingObserver {
  int _currentIndex = 0;
  bool _isOnline = false;
  bool _isWebSocketInitialized = false;
  Timer? _locationTimer;
  Timer? _orderPollingTimer;
  Timer? _onlineDurationTimer;
  bool _isPollingOrders = false;
  final Set<int> _knownAssignedOrderIds = {};
  final LocationService _locationService = LocationService();
  final ApiService _api = ApiService();
  GoogleMapController? _dashboardMapController;
  LatLng? _driverLocation;
  bool _isLocatingDriver = true;

  Map<String, dynamic> _stats = {};
  String? _loadError;
  Map<String, dynamic>? _activeGig;
  DateTime? _onlineStartedAt;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadDriverStatus();
    _loadStats();
    _loadDriverLocation();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initWebSocket();
    });
  }

  Future<void> _initWebSocket() async {
    if (_isWebSocketInitialized) return;

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    if (authProvider.currentUser == null) {
      await authProvider.loadUser();
    }

    final user = authProvider.currentUser;
    if (user == null) return;

    await _rememberAssignedOrders();
    _startOrderPollingFallback();

    await WebSocketService().initDriver(
      user.id,
      onOrderAssigned: (order) {
        final orderId = _parseId(order['id'] ?? order['order_id']);
        if (orderId != null) _knownAssignedOrderIds.add(orderId);
        _loadStats();
        if (!mounted) return;
        IncomingOrderAlertService.instance.handleIncomingOrderData({
          ...order,
          'role': 'driver',
          'type': 'driver_order_assigned',
        }, source: IncomingOrderSource.websocket);
      },
    );

    _isWebSocketInitialized = true;
  }

  void _startOrderPollingFallback() {
    _orderPollingTimer?.cancel();
    _orderPollingTimer = Timer.periodic(const Duration(seconds: 12), (_) {
      _pollForAssignedOrders();
    });
  }

  Future<void> _rememberAssignedOrders() async {
    try {
      final response = await _api.get(ApiConstants.driverOrders);
      if (response['success'] == true) {
        for (final order in _extractOrders(response['data'])) {
          if (order is! Map) continue;
          final orderId = _parseId(order['id'] ?? order['order_id']);
          if (orderId != null) _knownAssignedOrderIds.add(orderId);
        }
      }
    } catch (e) {
      debugPrint('Remember driver orders error: $e');
    }
  }

  Future<void> _pollForAssignedOrders() async {
    if (_isPollingOrders || !mounted) return;
    _isPollingOrders = true;

    try {
      final response = await _api.get(ApiConstants.driverOrders);
      if (response['success'] != true || !mounted) return;

      for (final rawOrder in _extractOrders(response['data'])) {
        if (rawOrder is! Map) continue;
        final order = Map<String, dynamic>.from(rawOrder);
        final orderId = _parseId(order['id'] ?? order['order_id']);
        if (orderId == null || _knownAssignedOrderIds.contains(orderId)) {
          continue;
        }

        _knownAssignedOrderIds.add(orderId);
        await _loadStats();
        if (!mounted) return;
        await IncomingOrderAlertService.instance.handleIncomingOrderData({
          ...order,
          'role': 'driver',
          'type': 'driver_order_assigned',
        }, source: IncomingOrderSource.websocket);
        break;
      }
    } catch (e) {
      debugPrint('Driver order polling error: $e');
    } finally {
      _isPollingOrders = false;
    }
  }

  List<dynamic> _extractOrders(dynamic data) {
    if (data is List) return data;
    if (data is Map && data['data'] is List) return data['data'] as List;
    if (data is Map && data['orders'] is List) return data['orders'] as List;
    return const [];
  }

  int? _parseId(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value);
    return null;
  }

  DateTime? _parseOnlineStartedAt(dynamic value) {
    if (value is DateTime) return value.toLocal();
    if (value is String && value.isNotEmpty) {
      return DateTime.tryParse(value)?.toLocal();
    }
    return null;
  }

  String get _greeting {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
  }

  String get _onlineDurationText {
    if (!_isOnline || _onlineStartedAt == null) return 'Offline';
    final elapsed = DateTime.now().difference(_onlineStartedAt!);
    final hours = elapsed.inHours;
    final minutes = elapsed.inMinutes.remainder(60);
    if (hours <= 0) return '${minutes}m online';
    return '${hours}h ${minutes.toString().padLeft(2, '0')}m online';
  }

  String get _onlineStartedAtText {
    if (_onlineStartedAt == null) return '';
    return 'Since ${DateFormat('h:mm a').format(_onlineStartedAt!)}';
  }

  bool get _hasVisibleDriverRating {
    final count = int.tryParse('${_stats['total_ratings'] ?? 0}') ?? 0;
    final rating = double.tryParse('${_stats['rating'] ?? ''}');
    return count >= 3 && rating != null && rating > 0;
  }

  Future<void> _loadDriverStatus() async {
    try {
      final response = await _api.get(ApiConstants.driverStatus);
      if (response['success'] == true) {
        setState(() {
          _isOnline = response['data']?['is_online'] ?? false;
          _activeGig = response['data']?['active_gig'];
          _onlineStartedAt = _isOnline
              ? _parseOnlineStartedAt(response['data']?['online_started_at']) ??
                  DateTime.now()
              : null;
        });
        if (_isOnline) {
          await ForegroundServiceManager.startForegroundService(
            status: 'Online and sharing live location',
            trackLocation: true,
          );
          _startLocationTracking();
          _startOnlineDurationTimer();
        } else {
          _stopOnlineDurationTimer();
        }
      }
    } catch (e) {
      debugPrint('Load driver status error: $e');
    }
  }

  Future<void> _loadStats() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get('/driver/stats');
      if (response['success'] == true) {
        setState(() {
          _stats = response['data'] ?? {};
          _activeGig = _stats['active_gig'];
          _loadError = null;
        });
      }
    } catch (e) {
      debugPrint('Load stats error: $e');
      if (mounted && _stats.isEmpty) {
        setState(() => _loadError = _cleanApiError(e));
      }
    }
    setState(() => _isLoading = false);
  }

  Future<void> _toggleOnlineStatus() async {
    if (!_isOnline && _activeGig == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Book an active gig before going online.'),
          action: SnackBarAction(
            label: 'Gigs',
            textColor: Colors.white,
            onPressed: _openGigsSheet,
          ),
          backgroundColor: Colors.red,
        ),
      );
      _openGigsSheet();
      return;
    }

    if (!_isOnline && !await _ensureOnlineLocationPermission()) {
      return;
    }

    try {
      final response = await _api.post(
        ApiConstants.driverToggleStatus,
        data: {'is_online': !_isOnline},
      );
      if (response['success'] == true) {
        final isOnline = response['data']?['is_online'] ?? !_isOnline;
        setState(() {
          _isOnline = isOnline;
          _activeGig = response['data']?['active_gig'] ?? _activeGig;
          _onlineStartedAt = isOnline
              ? _parseOnlineStartedAt(response['data']?['online_started_at']) ??
                  DateTime.now()
              : null;
        });

        if (_isOnline) {
          await ForegroundServiceManager.startForegroundService(
            status: 'Online and sharing live location',
            trackLocation: true,
          );
          _startLocationTracking();
          _startOnlineDurationTimer();
          HapticFeedback.lightImpact();
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Row(
                children: const [
                  Icon(Icons.circle, color: Colors.green, size: 12),
                  SizedBox(width: 8),
                  Text('You are now online and ready to accept deliveries'),
                ],
              ),
              backgroundColor: Colors.green,
              duration: const Duration(seconds: 2),
            ),
          );
        } else {
          _stopLocationTracking();
          _stopOnlineDurationTimer();
          await ForegroundServiceManager.stopForegroundService();
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Row(
                children: const [
                  Icon(Icons.circle, color: Colors.red, size: 12),
                  SizedBox(width: 8),
                  Text('You are now offline'),
                ],
              ),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      debugPrint('Toggle status error: $e');
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Failed to toggle status: $e')));
    }
  }

  String _cleanApiError(Object error) {
    final message = error.toString();
    if (message.startsWith('Exception: ')) {
      return message.substring('Exception: '.length);
    }
    return message;
  }

  Future<bool> _ensureOnlineLocationPermission() async {
    if (!await _locationService.isLocationServiceEnabled()) {
      _showLocationPermissionSnackBar(
        'Turn on location services before going online.',
        _locationService.openLocationSettings,
      );
      return false;
    }

    var permission = await _locationService.checkLocationPermission();
    if (permission == LocationPermission.denied) {
      await _locationService.requestLocationPermission();
      permission = await _locationService.checkLocationPermission();
    }

    if (permission == LocationPermission.always) {
      return true;
    }

    final message = permission == LocationPermission.whileInUse
        ? 'Allow location all the time so orders and live tracking keep working in background.'
        : 'Allow location permission before going online.';

    _showLocationPermissionSnackBar(message, _locationService.openAppSettings);
    return false;
  }

  void _showLocationPermissionSnackBar(
    String message,
    Future<bool> Function() action,
  ) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        action: SnackBarAction(
          label: 'Settings',
          textColor: Colors.white,
          onPressed: () => action(),
        ),
        backgroundColor: FoodFlowTheme.crimson,
      ),
    );
  }

  void _startLocationTracking() {
    _locationTimer?.cancel();
    _updateLocation(); // Immediate update
    _locationTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
      if (_isOnline && mounted) {
        _updateLocation();
      } else if (!_isOnline) {
        _locationTimer?.cancel();
      }
    });
  }

  void _stopLocationTracking() {
    _locationTimer?.cancel();
    _locationTimer = null;
  }

  void _startOnlineDurationTimer() {
    _onlineDurationTimer?.cancel();
    _onlineDurationTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted && _isOnline) setState(() {});
    });
  }

  void _stopOnlineDurationTimer() {
    _onlineDurationTimer?.cancel();
    _onlineDurationTimer = null;
  }

  Future<void> _updateLocation() async {
    try {
      final token = await _api.getToken();
      if (token == null) {
        _stopLocationTracking();
        return;
      }

      final position = await _locationService.getCurrentLocation();
      if (position != null && mounted) {
        final location = LatLng(position.latitude, position.longitude);
        setState(() {
          _driverLocation = location;
          _isLocatingDriver = false;
        });
        _dashboardMapController?.animateCamera(
          CameraUpdate.newCameraPosition(
            CameraPosition(target: location, zoom: 16),
          ),
        );
        await _api.post(
          ApiConstants.driverLocation,
          data: {'lat': position.latitude, 'lng': position.longitude},
        );
      }
    } catch (e) {
      debugPrint('Update location error: $e');
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _stopLocationTracking();
    _stopOnlineDurationTimer();
    _orderPollingTimer?.cancel();
    WebSocketService().dispose();
    SoundService.stopIncomingOrderAlarm();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(72),
        child: _buildAppBar(),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _loadError != null && _stats.isEmpty
              ? NetworkErrorView(message: _loadError, onRetry: _loadStats)
              : _buildCurrentBody(),
      bottomNavigationBar: _buildBottomNavBar(),
    );
  }

  Widget _buildCurrentBody() {
    switch (_currentIndex) {
      case 1:
        return const DriverOrdersScreen();
      case 2:
        return const DriverEarningsScreen();
      case 3:
        return const DriverWalletScreen();
      default:
        return _buildDashboard();
    }
  }

  Future<void> _loadDriverLocation() async {
    try {
      final position = await _locationService.getCurrentLocation();
      if (!mounted) return;
      if (position == null) {
        setState(() => _isLocatingDriver = false);
        return;
      }

      final location = LatLng(position.latitude, position.longitude);
      setState(() {
        _driverLocation = location;
        _isLocatingDriver = false;
      });
      _dashboardMapController?.animateCamera(
        CameraUpdate.newCameraPosition(
          CameraPosition(target: location, zoom: 16),
        ),
      );
    } catch (e) {
      debugPrint('Load driver location error: $e');
      if (mounted) setState(() => _isLocatingDriver = false);
    }
  }

  Future<void> _openGigsSheet() async {
    final booked = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
      ),
      builder: (context) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.88,
        minChildSize: 0.55,
        maxChildSize: 0.95,
        builder: (context, scrollController) {
          return const DriverGigsScreen();
        },
      ),
    );

    if (booked == true && mounted) {
      await _loadDriverStatus();
      await _loadStats();
    }
  }

  Widget _buildAppBar() {
    final user = Provider.of<AuthProvider>(context).currentUser;
    final driverName = user != null && user.name.trim().isNotEmpty
        ? user.name.trim()
        : 'Driver';
    final branchLabel = user?.hasBranch == true ? user!.branchLabel : null;

    return Container(
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).padding.top,
        left: 16,
        right: 16,
      ),
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(color: Colors.black12, blurRadius: 4, offset: Offset(0, 2)),
        ],
      ),
      child: Column(
        children: [
          const SizedBox(height: 8),
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: FoodFlowTheme.crimson,
                  borderRadius: BorderRadius.circular(14),
                ),
                alignment: Alignment.center,
                child: Text(
                  user != null && user.name.isNotEmpty
                      ? user.name[0].toUpperCase()
                      : 'Z',
                  style: GoogleFonts.nunitoSans(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: Colors.white,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$_greeting, $driverName',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.nunitoSans(
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    Text(
                      _activeGig == null
                          ? 'Book a gig before accepting orders'
                          : _isOnline
                              ? 'You are receiving orders'
                              : 'You are offline',
                      style: GoogleFonts.nunitoSans(
                        fontSize: 12,
                        color: FoodFlowTheme.muted,
                      ),
                    ),
                    if (branchLabel != null)
                      Text(
                        branchLabel,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.nunitoSans(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: FoodFlowTheme.crimson,
                        ),
                      ),
                  ],
                ),
              ),
              if (_isOnline) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 7,
                  ),
                  decoration: BoxDecoration(
                    color: FoodFlowTheme.success.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: FoodFlowTheme.success.withOpacity(0.24),
                    ),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        _onlineDurationText,
                        style: GoogleFonts.nunitoSans(
                          fontSize: 11,
                          fontWeight: FontWeight.w900,
                          color: FoodFlowTheme.success,
                        ),
                      ),
                      if (_onlineStartedAtText.isNotEmpty)
                        Text(
                          _onlineStartedAtText,
                          style: GoogleFonts.nunitoSans(
                            fontSize: 9,
                            fontWeight: FontWeight.w700,
                            color: FoodFlowTheme.muted,
                          ),
                        ),
                    ],
                  ),
                ),
              ],
              const SizedBox(width: 8),
              PopupMenuButton<String>(
                tooltip: 'Menu',
                icon: const Icon(Icons.menu, color: FoodFlowTheme.ink),
                onSelected: (value) {
                  if (value == 'profile') {
                    Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (_) => const DriverProfileScreen()));
                  }
                },
                itemBuilder: (_) => const [
                  PopupMenuItem(
                      value: 'profile',
                      child: ListTile(
                          leading: Icon(Icons.person_outline),
                          title: Text('Profile'),
                          contentPadding: EdgeInsets.zero)),
                ],
              ),
              GestureDetector(
                onTap: _toggleOnlineStatus,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  width: 58,
                  height: 34,
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
                    color:
                        _isOnline ? FoodFlowTheme.success : FoodFlowTheme.line,
                    borderRadius: BorderRadius.circular(18),
                  ),
                  alignment:
                      _isOnline ? Alignment.centerRight : Alignment.centerLeft,
                  child: Container(
                    width: 26,
                    height: 26,
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Container(height: 1, color: FoodFlowTheme.line),
          const SizedBox(height: 4),
        ],
      ),
    );
  }

  Widget _buildQuickStat(
    String label,
    String value,
    IconData icon,
    Color color,
  ) {
    return Expanded(
      child: SizedBox(
        height: 66,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          decoration: BoxDecoration(
            color: color.withOpacity(0.1),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, color: color, size: 17),
              const SizedBox(height: 2),
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.nunitoSans(
                  fontWeight: FontWeight.bold,
                  fontSize: 13,
                  height: 1.0,
                  color: color,
                ),
              ),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.nunitoSans(
                  fontSize: 9,
                  height: 1.0,
                  color: Colors.grey.shade600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDashboard() {
    return RefreshIndicator(
      onRefresh: _loadStats,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              height: 430,
              decoration: FoodFlowTheme.surface(radius: 10),
              clipBehavior: Clip.antiAlias,
              child: Stack(
                children: [
                  Positioned.fill(child: _buildDriverMap()),
                  Positioned(
                    top: 18,
                    left: 18,
                    child: GestureDetector(
                      onTap: _loadDriverLocation,
                      child: Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.10),
                              blurRadius: 14,
                              offset: const Offset(0, 6),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.my_location,
                          color: FoodFlowTheme.crimson,
                          size: 20,
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    left: 16,
                    right: 16,
                    bottom: 16,
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.08),
                            blurRadius: 18,
                            offset: const Offset(0, 8),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  _isOnline
                                      ? 'You are online'
                                      : 'You are offline',
                                  style: GoogleFonts.nunitoSans(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w900,
                                    color: FoodFlowTheme.ink,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  _isOnline
                                      ? 'Stay ready for incoming orders'
                                      : _activeGig == null
                                          ? 'Book a gig to start receiving orders'
                                          : 'Go online to start receiving orders',
                                  style: GoogleFonts.nunitoSans(
                                    fontSize: 12,
                                    color: FoodFlowTheme.muted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          ElevatedButton(
                            onPressed: _activeGig == null
                                ? _openGigsSheet
                                : _toggleOnlineStatus,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: _activeGig == null
                                  ? FoodFlowTheme.crimson
                                  : FoodFlowTheme.crimson,
                              foregroundColor: Colors.white,
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                            child: Text(
                              _activeGig == null
                                  ? 'Book Gig'
                                  : _isOnline
                                      ? 'Online'
                                      : 'Go Online',
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            Row(
              children: [
                _buildQuickStat(
                  'Today',
                  formatCurrencyValue(context, _stats['today_earnings']),
                  Icons.payments_outlined,
                  FoodFlowTheme.success,
                ),
                const SizedBox(width: 10),
                _buildQuickStat(
                  'Orders',
                  '${_stats['today_deliveries'] ?? 0}',
                  Icons.shopping_bag_outlined,
                  FoodFlowTheme.crimson,
                ),
                const SizedBox(width: 10),
                _buildQuickStat(
                  'Rating',
                  _hasVisibleDriverRating ? '${_stats['rating']}' : 'New',
                  Icons.star,
                  Colors.amber.shade700,
                ),
              ],
            ),
            const SizedBox(height: 22),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Running Orders',
                  style: GoogleFonts.nunitoSans(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                TextButton(
                  onPressed: () => setState(() => _currentIndex = 2),
                  child: const Text('View All'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _buildRunningOrders(),
            const SizedBox(height: 24),

            Text(
              'Earnings Summary',
              style: GoogleFonts.nunitoSans(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: FoodFlowTheme.ink,
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: FoodFlowTheme.surface(radius: 14),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      children: [
                        Text(
                          'This Week',
                          style: TextStyle(
                            color: Colors.grey.shade600,
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          formatCurrencyValue(context, _stats['week_earnings']),
                          style: GoogleFonts.nunitoSans(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.green,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(width: 1, height: 40, color: Colors.grey.shade200),
                  Expanded(
                    child: Column(
                      children: [
                        Text(
                          'This Month',
                          style: TextStyle(
                            color: Colors.grey.shade600,
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          formatCurrencyValue(
                            context,
                            _stats['month_earnings'],
                          ),
                          style: GoogleFonts.nunitoSans(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.green,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Recent Deliveries
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Recent Deliveries',
                  style: GoogleFonts.nunitoSans(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                TextButton(
                  onPressed: () => setState(() => _currentIndex = 2),
                  child: const Text('View All'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _buildRecentDeliveries(),
          ],
        ),
      ),
    );
  }

  Widget _buildRecentDeliveries() {
    final recentDeliveries = _stats['recent_deliveries'] as List? ?? [];

    if (recentDeliveries.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(32),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          children: [
            Icon(
              Icons.delivery_dining_outlined,
              size: 64,
              color: Colors.grey.shade300,
            ),
            const SizedBox(height: 16),
            Text(
              'No deliveries yet',
              style: GoogleFonts.nunitoSans(color: Colors.grey.shade600),
            ),
            const SizedBox(height: 8),
            if (!_isOnline)
              Text(
                'Go online to start receiving orders',
                style: GoogleFonts.nunitoSans(
                  fontSize: 12,
                  color: Colors.grey.shade500,
                ),
              ),
          ],
        ),
      );
    }

    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: recentDeliveries.length > 3 ? 3 : recentDeliveries.length,
      itemBuilder: (context, index) {
        final delivery = recentDeliveries[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: Colors.green.shade100,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.delivery_dining, color: Colors.green),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Order #${delivery['order_number']}',
                      style:
                          GoogleFonts.nunitoSans(fontWeight: FontWeight.bold),
                    ),
                    Text(
                      '${delivery['customer_name']} • ${delivery['delivery_address']?.split(',')[0]}',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey.shade600,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    formatCurrencyValue(
                      context,
                      delivery['delivery_fee'] ?? 50,
                    ),
                    style: GoogleFonts.nunitoSans(
                      fontWeight: FontWeight.bold,
                      color: Colors.green,
                    ),
                  ),
                  Text(
                    DateFormat('dd MMM').format(
                      DateTime.parse(
                        delivery['delivered_at'] ?? delivery['created_at'],
                      ),
                    ),
                    style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildDriverMap() {
    final location = _driverLocation ?? const LatLng(28.6139, 77.2090);

    return Stack(
      children: [
        GoogleMap(
          onMapCreated: (controller) {
            _dashboardMapController = controller;
            if (_driverLocation != null) {
              controller.moveCamera(
                CameraUpdate.newCameraPosition(
                  CameraPosition(target: _driverLocation!, zoom: 16),
                ),
              );
            }
          },
          initialCameraPosition: CameraPosition(target: location, zoom: 15),
          myLocationEnabled: _driverLocation != null,
          myLocationButtonEnabled: false,
          zoomControlsEnabled: false,
          compassEnabled: false,
          markers: {
            if (_driverLocation != null)
              Marker(
                markerId: const MarkerId('driver_current_location'),
                position: _driverLocation!,
                infoWindow: const InfoWindow(title: 'Your location'),
                icon: BitmapDescriptor.defaultMarkerWithHue(
                  BitmapDescriptor.hueRed,
                ),
              ),
          },
        ),
        if (_isLocatingDriver)
          Container(
            color: Colors.white.withOpacity(0.72),
            alignment: Alignment.center,
            child: const CircularProgressIndicator(
              color: FoodFlowTheme.crimson,
            ),
          ),
        if (!_isLocatingDriver && _driverLocation == null)
          Container(
            color: Colors.white.withOpacity(0.84),
            alignment: Alignment.center,
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.location_off_outlined,
                  color: FoodFlowTheme.crimson,
                  size: 34,
                ),
                const SizedBox(height: 10),
                const Text(
                  'Enable location to show your live map',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 10),
                TextButton(
                  onPressed: _loadDriverLocation,
                  child: const Text('Try Again'),
                ),
              ],
            ),
          ),
      ],
    );
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _currentIndex == 0) {
      _loadDriverLocation();
      _loadStats();
    }
  }

  @override
  void didUpdateWidget(covariant DriverDashboard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_currentIndex == 0 && _driverLocation == null && !_isLocatingDriver) {
      _loadDriverLocation();
    }
  }

  @override
  void deactivate() {
    _dashboardMapController = null;
    super.deactivate();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_currentIndex == 0 && _driverLocation == null && !_isLocatingDriver) {
      _loadDriverLocation();
    }
  }

  @override
  void reassemble() {
    super.reassemble();
    if (_currentIndex == 0) {
      _loadDriverLocation();
    }
  }

  @override
  void activate() {
    super.activate();
    if (_currentIndex == 0 && _driverLocation == null) {
      _loadDriverLocation();
    }
  }

  @override
  void didChangeMetrics() {
    if (_driverLocation != null) {
      _dashboardMapController?.moveCamera(
        CameraUpdate.newCameraPosition(
          CameraPosition(target: _driverLocation!, zoom: 16),
        ),
      );
    }
  }

  Widget _buildRunningOrders() {
    final runningOrders = _stats['running_orders'] as List? ?? [];

    if (runningOrders.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(22),
        decoration: FoodFlowTheme.surface(radius: 14),
        child: Row(
          children: [
            Icon(
              Icons.delivery_dining_outlined,
              color: Colors.grey.shade400,
              size: 34,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'No running orders right now',
                style: GoogleFonts.nunitoSans(
                  color: FoodFlowTheme.muted,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      );
    }

    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: runningOrders.length > 3 ? 3 : runningOrders.length,
      itemBuilder: (context, index) {
        final order = runningOrders[index] as Map;
        final accepted = order['driver_accepted_at'] != null;
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(14),
          decoration: FoodFlowTheme.surface(radius: 14),
          child: ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(
              accepted ? Icons.route : Icons.notifications_active,
              color: accepted ? Colors.green : FoodFlowTheme.orange,
            ),
            title: Text(
              'Order #${order['order_number'] ?? ''}',
              style: GoogleFonts.nunitoSans(fontWeight: FontWeight.w900),
            ),
            subtitle: Text(
              accepted ? 'Running delivery' : 'Waiting for your response',
              style: const TextStyle(color: FoodFlowTheme.muted),
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              final id = order['id'];
              Navigator.pushNamed(context, '/driver/order', arguments: id);
            },
          ),
        );
      },
    );
  }

  Widget _buildBottomNavBar() {
    return BottomNavigationBar(
      currentIndex: _currentIndex,
      onTap: (index) => setState(() => _currentIndex = index),
      type: BottomNavigationBarType.fixed,
      selectedItemColor: FoodFlowTheme.crimson,
      unselectedItemColor: FoodFlowTheme.muted,
      selectedLabelStyle: GoogleFonts.nunitoSans(fontSize: 12),
      unselectedLabelStyle: GoogleFonts.nunitoSans(fontSize: 12),
      items: const [
        BottomNavigationBarItem(
          icon: Icon(Icons.shopping_bag_outlined),
          activeIcon: Icon(Icons.shopping_bag),
          label: 'New Order',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.receipt_long_outlined),
          activeIcon: Icon(Icons.receipt_long),
          label: 'Orders',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.account_balance_wallet_outlined),
          activeIcon: Icon(Icons.account_balance_wallet),
          label: 'Earnings',
        ),
        BottomNavigationBarItem(
          icon: Icon(Icons.wallet_outlined),
          activeIcon: Icon(Icons.wallet),
          label: 'Wallet',
        ),
      ],
    );
  }
}
