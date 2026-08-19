// lib/screens/customer/order_tracking_screen.dart
import 'dart:async';
import 'dart:math';
import 'dart:typed_data';
import 'dart:ui' as ui;
import 'package:flutter/foundation.dart' show ValueListenable;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show rootBundle, SystemUiOverlayStyle;
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:lottie/lottie.dart' hide Marker;
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../providers/order_provider.dart';
import '../../providers/auth_provider.dart';
import '../../services/flexible_order_payment_service.dart';
import '../../services/notification_service.dart';
import '../../services/websocket_service.dart';
import '../../services/directions_service.dart';
import '../../models/order.dart';
import '../../models/user.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/common/app_skeleton.dart';
import '../../widgets/customer/order_feedback_dialog.dart';
import '../../widgets/customer/tip_driver_sheet.dart';
import 'order_chat_screen.dart';

class OrderTrackingScreen extends StatefulWidget {
  final int orderId;

  const OrderTrackingScreen({Key? key, required this.orderId})
      : super(key: key);

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen>
    with TickerProviderStateMixin {
  GoogleMapController? _mapController;
  Timer? _timer;
  int? _realtimeUserId;
  String? _realtimeHandlerId;
  BitmapDescriptor? _driverMarkerIcon;
  final List<BitmapDescriptor> _driverMarkerIcons = [];
  BitmapDescriptor? _restaurantMarkerIcon;
  BitmapDescriptor? _deliveryMarkerIcon;

  Order? _order;
  OrderProvider? _orderProvider;
  Set<Marker> _markers = {};
  final ValueNotifier<Set<Marker>> _markersNotifier =
      ValueNotifier<Set<Marker>>({});
  Set<Polyline> _polylines = {};
  List<LatLng> _currentRoute = [];
  LatLng? _restaurantLocation;
  LatLng? _deliveryLocation;
  LatLng? _driverLocation;
  LatLng? _animatedDriverLocation;
  LatLng? _driverAnimationStart;
  LatLng? _driverAnimationEnd;
  LatLng? _lastAcceptedDriverLocation;
  DateTime? _lastDriverLocationAt;
  DateTime? _lastFollowCameraAt;
  double _driverBearing = 0;
  double _driverStartBearing = 0;
  double _driverTargetBearing = 0;
  int _driverPulseFrame = 0;
  bool _isDriverMoving = false;
  bool _followDriver = false;
  static const double _minDriverMoveMeters = 3;
  static const double _bikeMarkerBearingOffset = -90;
  static const double _followCameraAheadMeters = 85;
  bool _isLoading = true;
  bool _isMapReady = false;
  bool _isFullScreenMap = false;
  bool _isStartingPayment = false;
  String _estimatedTime = 'Calculating...';
  String _distanceRemaining = '';
  int _currentStep = 0;
  String? _errorMessage;
  late AnimationController _animationController;
  late AnimationController _driverMoveController;
  late Animation<double> _pulseAnimation;
  late final FlexibleOrderPaymentService _paymentService;

  bool _isOrderPickedUp = false;
  int _pollTick = 0;
  bool _feedbackPromptShown = false;
  bool _tipPromptShown = false;
  final GlobalKey _orderSummaryKey = GlobalKey();
  final DraggableScrollableController _sheetController =
      DraggableScrollableController();
  bool get _isGroceryOrder => _order?.serviceType == 'grocery';
  Color get _primary => _isGroceryOrder
      ? const Color(0xFF138A36)
      : Theme.of(context).colorScheme.primary;
  Color get _secondary => _isGroceryOrder
      ? const Color(0xFF52B42C)
      : Theme.of(context).colorScheme.secondary;

  final List<Map<String, dynamic>> _orderSteps = [
    {'label': 'Order Placed', 'icon': Icons.receipt, 'status': 'pending'},
    {'label': 'Preparing', 'icon': Icons.restaurant, 'status': 'preparing'},
    {
      'label': 'Ready',
      'icon': Icons.pending_actions,
      'status': 'ready_for_pickup'
    },
    {'label': 'Picked Up', 'icon': Icons.check_circle, 'status': 'picked_up'},
    {
      'label': 'On The Way',
      'icon': Icons.delivery_dining,
      'status': 'on_the_way'
    },
    {'label': 'Delivered', 'icon': Icons.home, 'status': 'delivered'},
  ];

  final List<Map<String, dynamic>> _takeawaySteps = [
    {'label': 'Order Placed', 'icon': Icons.receipt, 'status': 'pending'},
    {'label': 'Confirmed', 'icon': Icons.store, 'status': 'confirmed'},
    {'label': 'Preparing', 'icon': Icons.restaurant, 'status': 'preparing'},
    {
      'label': 'Ready to Collect',
      'icon': Icons.shopping_bag,
      'status': 'ready_for_pickup'
    },
    {'label': 'Picked Up', 'icon': Icons.check_circle, 'status': 'delivered'},
  ];

  List<Map<String, dynamic>> get _visibleOrderSteps =>
      (_order?.isTakeaway ?? false) ? _takeawaySteps : _orderSteps;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..repeat(reverse: true);
    _pulseAnimation = Tween<double>(begin: 1.0, end: 1.3)
        .animate(_animationController)
      ..addListener(_handleDriverPulseTick);
    _driverMoveController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )
      ..addListener(_handleDriverMoveTick)
      ..addStatusListener((status) {
        if (status == AnimationStatus.completed ||
            status == AnimationStatus.dismissed) {
          _isDriverMoving = false;
          _animateFollowDriverCamera(force: true);
        }
      });
    _paymentService = FlexibleOrderPaymentService();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _orderProvider = context.read<OrderProvider>();
      _orderProvider!.addListener(_handleProviderOrderUpdate);
      _prepareCustomMarkers();
      _loadOrderDetails();
      _startPolling();
      _initializeRealtime();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    if (_realtimeUserId != null) {
      WebSocketService().removeCustomerHandler(
        _realtimeUserId!,
        _realtimeHandlerId,
      );
    }
    _orderProvider?.removeListener(_handleProviderOrderUpdate);
    _mapController?.dispose();
    _markersNotifier.dispose();
    _driverMoveController.dispose();
    _animationController.dispose();
    _paymentService.dispose();
    _sheetController.dispose();
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
            final orderId = int.tryParse(
              '${data['order_id'] ?? data['id'] ?? ''}',
            );
            if (orderId != widget.orderId) return;
            final updated =
                context.read<OrderProvider>().applyOrderStatusUpdate(data);
            if (updated == null) {
              unawaited(_loadOrderDetails(showLoading: false));
            } else {
              _applyRealtimeOrder(updated);
            }
          },
        );
        return;
      }
      await Future<void>.delayed(const Duration(milliseconds: 300));
    }
  }

  void _startPolling() {
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (mounted) {
        _pollTick++;
        _predictDriverMotionIfNeeded();
        if (_pollTick % 8 == 0) {
          _loadOrderDetails(showLoading: false);
        } else if ((_order?.isPending ?? false) ||
            (_order?.canCancel ?? false)) {
          setState(() {});
        }
      } else {
        timer.cancel();
      }
    });
  }

  Future<void> _loadOrderDetails({bool showLoading = true}) async {
    if (showLoading && mounted) {
      setState(() => _isLoading = true);
    }

    try {
      final orderProvider = Provider.of<OrderProvider>(context, listen: false);
      final order = await orderProvider.fetchOrderDetails(
        widget.orderId,
        notifyLoading: false,
        preferCache: showLoading,
      );

      if (order != null && mounted) {
        _applyServerDistance(order);
        final restaurantLocation = _getRestaurantLocation(order);
        final deliveryLocation = _getDeliveryLocation(order);
        final liveDriverLocation = _getLiveDriverLocation(order);

        final bool pickedUp =
            !order.isTakeaway && (order.isPickedUp || order.isOnTheWay);

        setState(() {
          _order = order;
          _currentStep = _getStepIndexFor(order);
          _estimatedTime = _estimatedTimeFor(order);
          _restaurantLocation = restaurantLocation;
          _deliveryLocation = deliveryLocation;
          _isLoading = false;
          _errorMessage = null;
        });
        _syncLiveOrderNotification(order);

        if (order.isTakeaway) {
          _driverLocation = null;
          _currentRoute = [];
          _isOrderPickedUp = false;
          _distanceRemaining = 'Pickup at store';
        } else if (pickedUp &&
            deliveryLocation != null &&
            (liveDriverLocation != null || restaurantLocation != null)) {
          final fallbackDriverLocation = restaurantLocation != null
              ? _calculateDriverPosition(deliveryLocation, restaurantLocation,
                  isPickedUp: true)
              : null;
          _driverLocation = liveDriverLocation ?? fallbackDriverLocation;
          _isOrderPickedUp = true;
          final routePoints = await _loadRoutePoints(
            startLocation: _driverLocation ?? restaurantLocation,
            endLocation: deliveryLocation,
          );
          _currentRoute = routePoints;
          if (routePoints.isNotEmpty) {
            _calculateRouteInfo(routePoints);
          }
        } else if (restaurantLocation != null && deliveryLocation != null) {
          _driverLocation = liveDriverLocation ?? restaurantLocation;
          _isOrderPickedUp = false;
          final routePoints = await _loadRoutePoints(
            startLocation: restaurantLocation,
            endLocation: deliveryLocation,
          );
          _currentRoute = routePoints;
          if (routePoints.isNotEmpty) {
            _calculateRouteInfo(routePoints);
          }
        }

        _syncDriverLocation(order, _driverLocation);
        final markers = _buildMapMarkers(
          order: order,
          restaurantLocation: restaurantLocation,
          deliveryLocation: deliveryLocation,
          driverLocation: _driverLocation,
          isPickedUp: pickedUp,
        );

        final polylines = _buildMapPolylines(
          order: order,
          routePoints: _currentRoute,
          isPickedUp: pickedUp,
        );

        if (!mounted) return;
        setState(() {
          _order = order;
          _currentStep = _getStepIndexFor(order);
          _estimatedTime = _estimatedTimeFor(order);
          _restaurantLocation = restaurantLocation;
          _deliveryLocation = deliveryLocation;
          _markers = markers;
          _markersNotifier.value = markers;
          _polylines = polylines;
          _isLoading = false;
          _errorMessage = null;
        });

        if (order.isDelivered) {
          _showCompletionFeedback(order);
        }

        if (_mapController != null && _isMapReady && !_isFullScreenMap) {
          Future.delayed(const Duration(milliseconds: 300), () {
            _fitMapToRoute();
          });
        }
      } else if (mounted) {
        setState(() {
          _errorMessage = orderProvider.error ?? 'Unable to load order details';
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint('Error loading order: $e');
      if (mounted) {
        setState(() {
          _errorMessage = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  void _calculateRouteInfo(List<LatLng> routePoints) {
    double totalDistance = 0;
    for (int i = 0; i < routePoints.length - 1; i++) {
      totalDistance += _calculateDistance(routePoints[i], routePoints[i + 1]);
    }
    totalDistance = totalDistance / 1000;

    setState(() {
      if (_order?.deliveryDistanceLabel == null ||
          _order!.deliveryDistanceLabel!.isEmpty) {
        _distanceRemaining = '${totalDistance.toStringAsFixed(1)} km';
      }
      if (_order?.etaRange == null && _order?.etaMinutes == null) {
        int estimatedMinutes = (totalDistance / 30 * 60).round();
        if (estimatedMinutes < 5) estimatedMinutes = 5;
        if (estimatedMinutes > 45) estimatedMinutes = 45;
        _estimatedTime = '$estimatedMinutes-${estimatedMinutes + 5} mins';
      }
    });
  }

  void _handleProviderOrderUpdate() {
    if (!mounted) return;
    final current = context.read<OrderProvider>().currentOrder;
    if (current?.id != widget.orderId) return;
    _applyRealtimeOrder(current!);
  }

  void _applyRealtimeOrder(Order order) {
    if (!mounted || order.id != widget.orderId) return;
    _applyServerDistance(order);
    setState(() {
      _order = order;
      _currentStep = _getStepIndexFor(order);
      _estimatedTime = _estimatedTimeFor(order);
      _isOrderPickedUp =
          order.isPickedUp || order.isOnTheWay || order.isDelivered;
    });
    _syncDriverLocation(order, _driverLocation);
    _syncLiveOrderNotification(order);
    if (order.isDelivered) {
      _showCompletionFeedback(order);
    }
  }

  void _syncLiveOrderNotification(Order order) {
    unawaited(
      FirebaseNotificationService.showLiveOrderNotificationFromOrder(
        order,
        etaText: _estimatedTime,
        distanceText: _distanceRemaining,
      ),
    );
  }

  void _showCompletionFeedback(Order order) {
    if (!mounted || _feedbackPromptShown || !order.needsFeedback) return;
    _feedbackPromptShown = true;
    _timer?.cancel();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      await showOrderFeedbackDialog(context, order);
      if (!mounted) return;
      await _maybeShowTipPrompt(_order ?? order);
    });
  }

  Future<void> _maybeShowTipPrompt(Order order) async {
    if (!mounted ||
        _tipPromptShown ||
        order.isTakeaway ||
        order.driver == null ||
        (order.tip ?? 0) > 0) {
      return;
    }
    _tipPromptShown = true;

    final updated = await showTipDriverSheet(context, order: order);
    if (updated != null && mounted) {
      setState(() => _order = updated);
    }
  }

  void _applyServerDistance(Order order) {
    final label = order.deliveryDistanceLabel;
    if (label != null && label.isNotEmpty) {
      _distanceRemaining = label;
    }

    final etaRange = order.etaRange;
    if (etaRange != null && etaRange.isNotEmpty) {
      _estimatedTime = etaRange;
      return;
    }

    final minutes = order.etaMinutes;
    if (minutes != null && minutes > 0) {
      _estimatedTime = '$minutes mins';
    }
  }

  LatLng? _calculateDriverPosition(LatLng destination, LatLng start,
      {bool isPickedUp = false}) {
    if (isPickedUp) {
      final lat =
          start.latitude + (destination.latitude - start.latitude) * 0.6;
      final lng =
          start.longitude + (destination.longitude - start.longitude) * 0.6;
      return LatLng(lat, lng);
    } else {
      return start;
    }
  }

  LatLng? _getRestaurantLocation(Order order) {
    if (order.restaurant == null ||
        order.restaurant!.latitude == 0.0 ||
        order.restaurant!.longitude == 0.0) {
      return null;
    }
    return LatLng(order.restaurant!.latitude, order.restaurant!.longitude);
  }

  LatLng? _getDeliveryLocation(Order order) {
    if (order.isTakeaway) return null;

    return (order.deliveryLat != null &&
            order.deliveryLat != 0.0 &&
            order.deliveryLng != null &&
            order.deliveryLng != 0.0)
        ? LatLng(order.deliveryLat!, order.deliveryLng!)
        : null;
  }

  LatLng? _getLiveDriverLocation(Order order) {
    if (!order.hasLiveDriverLocation) return null;
    return LatLng(order.driverLat!, order.driverLng!);
  }

  Set<Marker> _buildMapMarkers({
    required Order order,
    required LatLng? restaurantLocation,
    required LatLng? deliveryLocation,
    required LatLng? driverLocation,
    required bool isPickedUp,
  }) {
    final markers = <Marker>{};

    if (restaurantLocation != null && (!isPickedUp || order.isTakeaway)) {
      markers.add(
        Marker(
          markerId: const MarkerId('restaurant'),
          position: restaurantLocation,
          icon: _restaurantMarkerIcon ??
              BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueRed),
          infoWindow: InfoWindow(
            title: order.isTakeaway ? 'Pickup Counter' : 'Store',
            snippet: order.isTakeaway
                ? (order.restaurant?.name ?? 'Collect your order here')
                : 'Pickup location',
          ),
        ),
      );
    }

    if (deliveryLocation != null && !order.isTakeaway) {
      markers.add(
        Marker(
          markerId: const MarkerId('delivery'),
          position: deliveryLocation,
          icon: _deliveryMarkerIcon ??
              BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueGreen),
          infoWindow: const InfoWindow(
              title: 'Your Location', snippet: 'Delivery address'),
        ),
      );
    }

    final hasRealDriverLocation = order.hasLiveDriverLocation;
    final visibleDriverLocation = _animatedDriverLocation ?? driverLocation;

    if (!order.isTakeaway &&
        visibleDriverLocation != null &&
        (hasRealDriverLocation || order.isOnTheWay || order.isPickedUp)) {
      markers.add(
        Marker(
          markerId: const MarkerId('driver'),
          position: visibleDriverLocation,
          icon: _activeDriverMarkerIcon,
          infoWindow: InfoWindow(
            title: 'Delivery Partner',
            snippet: isPickedUp ? 'On the way to you!' : 'At store',
          ),
          anchor: const Offset(0.5, 0.9),
          flat: true,
          rotation: _normalizeBearing(
            _driverBearing + _bikeMarkerBearingOffset,
          ),
          zIndex: 1000,
        ),
      );
    }

    return markers;
  }

  Set<Polyline> _buildMapPolylines({
    required Order order,
    required List<LatLng> routePoints,
    required bool isPickedUp,
  }) {
    final polylines = <Polyline>{};

    if (order.isTakeaway) return polylines;

    if (routePoints.isNotEmpty) {
      polylines.add(
        Polyline(
          polylineId: const PolylineId('route_main'),
          points: routePoints,
          color: isPickedUp ? _secondary : _primary,
          width: 6,
          startCap: Cap.roundCap,
          endCap: Cap.roundCap,
          geodesic: true,
          zIndex: 1,
        ),
      );

      polylines.add(
        Polyline(
          polylineId: const PolylineId('route_dashed'),
          points: routePoints,
          color: isPickedUp
              ? const Color(0xFF90EE90).withOpacity(0.5)
              : const Color(0xFFFFD2AA),
          width: 10,
          patterns: [PatternItem.dash(25), PatternItem.gap(20)],
          startCap: Cap.roundCap,
          endCap: Cap.roundCap,
          geodesic: true,
          zIndex: 0,
        ),
      );

      if ((order.isOnTheWay || order.isPickedUp) &&
          _driverLocation != null &&
          routePoints.isNotEmpty) {
        final completedPath =
            _getCompletedRoutePoints(routePoints, _driverLocation!);
        if (completedPath.isNotEmpty && completedPath.length > 1) {
          polylines.add(
            Polyline(
              polylineId: const PolylineId('route_completed'),
              points: completedPath,
              color: _secondary,
              width: 6,
              startCap: Cap.roundCap,
              endCap: Cap.roundCap,
              geodesic: true,
              zIndex: 3,
            ),
          );
        }
      }

      polylines.add(
        Polyline(
          polylineId: const PolylineId('route_glow'),
          points: routePoints,
          color: (isPickedUp ? _secondary : _primary).withOpacity(0.3),
          width: 14,
          startCap: Cap.roundCap,
          endCap: Cap.roundCap,
          geodesic: true,
          zIndex: -1,
        ),
      );
    }

    return polylines;
  }

  List<LatLng> _getCompletedRoutePoints(
      List<LatLng> routePoints, LatLng driverPos) {
    int closestIndex = 0;
    double minDistance = double.infinity;

    for (int i = 0; i < routePoints.length; i++) {
      final point = routePoints[i];
      final distance = _calculateDistance(point, driverPos);
      if (distance < minDistance) {
        minDistance = distance;
        closestIndex = i;
      }
    }

    if (closestIndex > 0 && closestIndex < routePoints.length) {
      return routePoints.sublist(0, closestIndex + 1);
    }
    return [];
  }

  double _calculateDistance(LatLng p1, LatLng p2) {
    const double R = 6371e3;
    final double lat1 = p1.latitude * pi / 180;
    final double lat2 = p2.latitude * pi / 180;
    final double deltaLat = (p2.latitude - p1.latitude) * pi / 180;
    final double deltaLng = (p2.longitude - p1.longitude) * pi / 180;

    final double a = sin(deltaLat / 2) * sin(deltaLat / 2) +
        cos(lat1) * cos(lat2) * sin(deltaLng / 2) * sin(deltaLng / 2);
    final double c = 2 * atan2(sqrt(a), sqrt(1 - a));

    return R * c;
  }

  BitmapDescriptor get _activeDriverMarkerIcon {
    if (_driverMarkerIcons.isNotEmpty) {
      final index = _driverPulseFrame.clamp(0, _driverMarkerIcons.length - 1);
      return _driverMarkerIcons[index];
    }
    return _driverMarkerIcon ??
        BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueAzure);
  }

  void _handleDriverPulseTick() {
    if (_isDriverMoving || _animatedDriverLocation == null) return;
    final frame = ((_pulseAnimation.value - 1.0) / 0.3 * 5).round().clamp(0, 5);
    if (frame == _driverPulseFrame) return;
    _driverPulseFrame = frame;
    _updateDriverMarkerOnly();
  }

  void _handleDriverMoveTick() {
    final start = _driverAnimationStart;
    final end = _driverAnimationEnd;
    if (start == null || end == null) return;

    final t = Curves.easeInOut.transform(_driverMoveController.value);
    _animatedDriverLocation = _lerpLatLng(start, end, t);
    _driverBearing = _lerpBearing(_driverStartBearing, _driverTargetBearing, t);
    _updateDriverMarkerOnly();
    _animateFollowDriverCamera();
  }

  void _syncDriverLocation(Order order, LatLng? rawLocation) {
    if (order.isTakeaway || rawLocation == null) {
      _animatedDriverLocation = null;
      _lastAcceptedDriverLocation = null;
      return;
    }

    final snap = _snapDriverToRoute(rawLocation);
    final nextLocation = snap.point;
    final previous = _lastAcceptedDriverLocation ?? _animatedDriverLocation;
    final now = DateTime.now();

    if (previous == null) {
      _animatedDriverLocation = nextLocation;
      _lastAcceptedDriverLocation = nextLocation;
      _lastDriverLocationAt = now;
      _driverBearing = snap.bearing ?? _driverBearing;
      _updateDriverMarkerOnly();
      _animateFollowDriverCamera(force: true);
      return;
    }

    final distance = _calculateDistance(previous, nextLocation);
    if (distance < _minDriverMoveMeters) {
      _lastDriverLocationAt = now;
      return;
    }

    final target = distance < 12
        ? _lerpLatLng(previous, nextLocation, 0.65)
        : nextLocation;
    final targetBearing = snap.bearing ?? _calculateBearing(previous, target);

    _driverAnimationStart = _animatedDriverLocation ?? previous;
    _driverAnimationEnd = target;
    _driverStartBearing = _driverBearing;
    _driverTargetBearing = _driverStartBearing +
        _shortestBearingDelta(_driverStartBearing, targetBearing);
    _lastAcceptedDriverLocation = target;
    _lastDriverLocationAt = now;
    _driverPulseFrame = 0;
    _isDriverMoving = true;
    _driverMoveController.duration = Duration(
      milliseconds: distance > 80
          ? 1800
          : distance < 15
              ? 1000
              : 1400,
    );
    _driverMoveController.forward(from: 0);
  }

  void _predictDriverMotionIfNeeded() {
    if (_isDriverMoving || !_isOrderPickedUp || _currentRoute.isEmpty) return;
    final lastLocation = _animatedDriverLocation;
    final lastUpdate = _lastDriverLocationAt;
    if (lastLocation == null || lastUpdate == null) return;

    final staleSeconds = DateTime.now().difference(lastUpdate).inSeconds;
    if (staleSeconds < 5 || staleSeconds > 14) return;

    final predictedDistance = min(18.0, staleSeconds * 1.4);
    final projected =
        _offsetLatLng(lastLocation, _driverBearing, predictedDistance);
    final snap = _snapDriverToRoute(projected);
    if (_calculateDistance(lastLocation, snap.point) < _minDriverMoveMeters)
      return;

    _driverAnimationStart = lastLocation;
    _driverAnimationEnd = snap.point;
    _driverStartBearing = _driverBearing;
    _driverTargetBearing = _driverStartBearing +
        _shortestBearingDelta(
            _driverStartBearing, snap.bearing ?? _driverBearing);
    _isDriverMoving = true;
    _driverMoveController.duration = const Duration(milliseconds: 1200);
    _driverMoveController.forward(from: 0);
  }

  void _updateDriverMarkerOnly() {
    final order = _order;
    final position = _animatedDriverLocation ?? _driverLocation;
    if (order == null || position == null) return;
    _markers = _buildMapMarkers(
      order: order,
      restaurantLocation: _restaurantLocation,
      deliveryLocation: _deliveryLocation,
      driverLocation: position,
      isPickedUp: !order.isTakeaway && (order.isPickedUp || order.isOnTheWay),
    );
    _markersNotifier.value = _markers;
  }

  void _animateFollowDriverCamera({bool force = false}) {
    if (!_followDriver || _mapController == null || !_isMapReady) return;
    final driver = _animatedDriverLocation ?? _driverLocation;
    if (driver == null) return;

    final now = DateTime.now();
    if (!force &&
        _lastFollowCameraAt != null &&
        now.difference(_lastFollowCameraAt!) <
            const Duration(milliseconds: 650)) {
      return;
    }
    _lastFollowCameraAt = now;

    final target =
        _offsetLatLng(driver, _driverBearing, _followCameraAheadMeters);
    _mapController!.animateCamera(
      CameraUpdate.newCameraPosition(
        CameraPosition(target: target, zoom: 16.5),
      ),
    );
  }

  LatLng _lerpLatLng(LatLng start, LatLng end, double t) {
    return LatLng(
      start.latitude + (end.latitude - start.latitude) * t,
      start.longitude + (end.longitude - start.longitude) * t,
    );
  }

  double _lerpBearing(double start, double end, double t) {
    return _normalizeBearing(start + (end - start) * t);
  }

  double _calculateBearing(LatLng from, LatLng to) {
    final lat1 = from.latitude * pi / 180;
    final lat2 = to.latitude * pi / 180;
    final deltaLng = (to.longitude - from.longitude) * pi / 180;
    final y = sin(deltaLng) * cos(lat2);
    final x = cos(lat1) * sin(lat2) - sin(lat1) * cos(lat2) * cos(deltaLng);
    return _normalizeBearing(atan2(y, x) * 180 / pi);
  }

  double _normalizeBearing(double bearing) {
    final normalized = bearing % 360;
    return normalized < 0 ? normalized + 360 : normalized;
  }

  double _shortestBearingDelta(double from, double to) {
    return ((to - from + 540) % 360) - 180;
  }

  LatLng _offsetLatLng(LatLng origin, double bearing, double meters) {
    const earthRadius = 6371000.0;
    final distanceRatio = meters / earthRadius;
    final bearingRad = bearing * pi / 180;
    final lat1 = origin.latitude * pi / 180;
    final lng1 = origin.longitude * pi / 180;

    final lat2 = asin(sin(lat1) * cos(distanceRatio) +
        cos(lat1) * sin(distanceRatio) * cos(bearingRad));
    final lng2 = lng1 +
        atan2(
          sin(bearingRad) * sin(distanceRatio) * cos(lat1),
          cos(distanceRatio) - sin(lat1) * sin(lat2),
        );

    return LatLng(lat2 * 180 / pi, lng2 * 180 / pi);
  }

  _DriverRouteSnap _snapDriverToRoute(LatLng location) {
    if (_currentRoute.length < 2) {
      return _DriverRouteSnap(location, null, 0);
    }

    LatLng closest = location;
    double? roadBearing;
    double closestDistance = double.infinity;

    for (var i = 0; i < _currentRoute.length - 1; i++) {
      final start = _currentRoute[i];
      final end = _currentRoute[i + 1];
      final candidate = _nearestPointOnSegment(location, start, end);
      final distance = _calculateDistance(location, candidate);
      if (distance < closestDistance) {
        closest = candidate;
        closestDistance = distance;
        roadBearing = _calculateBearing(start, end);
      }
    }

    return _DriverRouteSnap(closest, roadBearing, closestDistance);
  }

  LatLng _nearestPointOnSegment(LatLng point, LatLng start, LatLng end) {
    final refLat = point.latitude * pi / 180;
    final metersPerLat = 111320.0;
    final metersPerLng = 111320.0 * cos(refLat).abs().clamp(0.0001, 1.0);

    final px = point.longitude * metersPerLng;
    final py = point.latitude * metersPerLat;
    final ax = start.longitude * metersPerLng;
    final ay = start.latitude * metersPerLat;
    final bx = end.longitude * metersPerLng;
    final by = end.latitude * metersPerLat;
    final dx = bx - ax;
    final dy = by - ay;
    final segmentLengthSq = dx * dx + dy * dy;

    if (segmentLengthSq == 0) return start;
    final t =
        (((px - ax) * dx + (py - ay) * dy) / segmentLengthSq).clamp(0.0, 1.0);
    return LatLng(
      (ay + dy * t) / metersPerLat,
      (ax + dx * t) / metersPerLng,
    );
  }

  Future<List<LatLng>> _loadRoutePoints({
    required LatLng? startLocation,
    required LatLng? endLocation,
  }) async {
    if (startLocation == null || endLocation == null) {
      return [];
    }
    try {
      return await DirectionsService.fetchRoutePoints(
          startLocation, endLocation);
    } catch (e) {
      debugPrint('Route loading error: $e');
      return [];
    }
  }

  Future<void> _prepareCustomMarkers() async {
    await _prepareDriverMarkerIcon();
    await _prepareRestaurantMarkerIcon();
    await _prepareDeliveryMarkerIcon();
  }

  Future<void> _prepareDriverMarkerIcon() async {
    try {
      final asset = await rootBundle.load('assets/images/delivery-bike.png');
      final codec = await ui.instantiateImageCodec(
        asset.buffer.asUint8List(),
        targetWidth: 62,
        targetHeight: 62,
      );
      final frame = await codec.getNextFrame();
      final markerFrames = <BitmapDescriptor>[];

      for (var i = 0; i < 6; i++) {
        final bytes = await _renderDriverMarkerBytes(
          bikeImage: frame.image,
          pulseProgress: i / 5,
        );
        markerFrames.add(BitmapDescriptor.fromBytes(bytes));
      }

      frame.image.dispose();
      if (!mounted) return;
      _driverMarkerIcons
        ..clear()
        ..addAll(markerFrames);
      _driverMarkerIcon = markerFrames.first;
      _updateDriverMarkerOnly();
      setState(() {});
    } catch (error) {
      debugPrint('Driver marker asset failed: $error');
      await _prepareFallbackDriverMarkerIcon();
    }
  }

  Future<Uint8List> _renderDriverMarkerBytes({
    required ui.Image bikeImage,
    required double pulseProgress,
  }) async {
    const int size = 132;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    const bikeCenter = Offset(size / 2, 58);
    final baseColor = _primary;

    if (pulseProgress > 0) {
      final haloPaint = Paint()
        ..color = baseColor.withOpacity(0.16 * (1 - pulseProgress));
      canvas.drawCircle(bikeCenter, 38 + 12 * pulseProgress, haloPaint);
    }

    final glowPaint = Paint()
      ..color = baseColor.withOpacity(0.18)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 10);
    canvas.drawCircle(bikeCenter, 39, glowPaint);

    final shadowPaint = Paint()
      ..color = Colors.black.withOpacity(0.22)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 8);
    canvas.drawOval(
      const Rect.fromLTRB(38, 88, 94, 103),
      shadowPaint,
    );

    canvas.drawCircle(bikeCenter, 37, Paint()..color = Colors.white);
    canvas.drawCircle(bikeCenter, 32, Paint()..color = baseColor);

    final imagePaint = Paint()..filterQuality = FilterQuality.high;
    canvas.drawImageRect(
      bikeImage,
      Rect.fromLTWH(
          0, 0, bikeImage.width.toDouble(), bikeImage.height.toDouble()),
      const Rect.fromLTRB(39, 31, 93, 85),
      imagePaint,
    );

    final picture = recorder.endRecording();
    final image = await picture.toImage(size, size);
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    return byteData!.buffer.asUint8List();
  }

  Future<void> _prepareFallbackDriverMarkerIcon() async {
    const int size = 120;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    final center = Offset(size / 2, size / 2);

    final glowPaint = Paint()..color = const Color(0xFF1976D2).withOpacity(0.3);
    canvas.drawCircle(center, size * 0.5, glowPaint);

    final paint = Paint()..color = const Color(0xFF1976D2);
    canvas.drawCircle(center, size * 0.42, paint);
    canvas.drawCircle(center, size * 0.35, Paint()..color = Colors.white);

    final iconPainter = TextPainter(
      text: TextSpan(
        text: String.fromCharCode(Icons.delivery_dining.codePoint),
        style: TextStyle(
          fontFamily: Icons.delivery_dining.fontFamily,
          package: Icons.delivery_dining.fontPackage,
          fontSize: 48,
          color: const Color(0xFF1976D2),
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    iconPainter.paint(
      canvas,
      Offset(center.dx - iconPainter.width / 2,
          center.dy - iconPainter.height / 2),
    );

    final picture = recorder.endRecording();
    final image = await picture.toImage(size, size);
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    if (byteData != null && mounted) {
      _driverMarkerIcon =
          BitmapDescriptor.fromBytes(byteData.buffer.asUint8List());
      _updateDriverMarkerOnly();
      setState(() {});
    }
  }

  Future<void> _prepareRestaurantMarkerIcon() async {
    const int size = 80;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    final center = Offset(size / 2, size / 2);
    canvas.drawCircle(
        center, size * 0.42, Paint()..color = const Color(0xFFE23744));
    canvas.drawCircle(center, size * 0.35, Paint()..color = Colors.white);

    final iconPainter = TextPainter(
      text: const TextSpan(text: '🍔', style: TextStyle(fontSize: 32)),
      textDirection: TextDirection.ltr,
    )..layout();
    iconPainter.paint(
      canvas,
      Offset(center.dx - iconPainter.width / 2,
          center.dy - iconPainter.height / 2),
    );

    final picture = recorder.endRecording();
    final image = await picture.toImage(size, size);
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    if (byteData != null && mounted) {
      setState(() => _restaurantMarkerIcon =
          BitmapDescriptor.fromBytes(byteData.buffer.asUint8List()));
    }
  }

  Future<void> _prepareDeliveryMarkerIcon() async {
    const int size = 80;
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    final center = Offset(size / 2, size / 2);
    canvas.drawCircle(center, size * 0.42, Paint()..color = _secondary);
    canvas.drawCircle(center, size * 0.35, Paint()..color = Colors.white);

    final iconPainter = TextPainter(
      text: const TextSpan(text: '🏠', style: TextStyle(fontSize: 32)),
      textDirection: TextDirection.ltr,
    )..layout();
    iconPainter.paint(
      canvas,
      Offset(center.dx - iconPainter.width / 2,
          center.dy - iconPainter.height / 2),
    );

    final picture = recorder.endRecording();
    final image = await picture.toImage(size, size);
    final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
    if (byteData != null && mounted) {
      setState(() => _deliveryMarkerIcon =
          BitmapDescriptor.fromBytes(byteData.buffer.asUint8List()));
    }
  }

  int _getStepIndexFor(Order order) {
    if (order.isTakeaway) {
      final takeawayStatusMap = {
        'pending': 0,
        'confirmed': 1,
        'preparing': 2,
        'ready_for_pickup': 3,
        'picked_up': 4,
        'delivered': 4,
      };
      return takeawayStatusMap[order.status] ?? 0;
    }

    final statusMap = {
      'pending': 0,
      'confirmed': 0,
      'preparing': 1,
      'ready_for_pickup': 2,
      'picked_up': 3,
      'on_the_way': 4,
      'delivered': 5,
    };
    return statusMap[order.status] ?? 0;
  }

  void _onMapCreated(GoogleMapController controller) {
    _mapController = controller;
    setState(() => _isMapReady = true);
    Future.delayed(const Duration(milliseconds: 500), () {
      if (mounted && !_isFullScreenMap) _fitMapToRoute();
    });
  }

  void _animateToLocation(LatLng location) {
    if (_mapController != null && _isMapReady) {
      _mapController!.animateCamera(
        CameraUpdate.newCameraPosition(
            CameraPosition(target: location, zoom: 16)),
      );
    }
  }

  void _centerOnRestaurant() {
    if (_restaurantLocation != null && _mapController != null && _isMapReady) {
      _animateToLocation(_restaurantLocation!);
    }
  }

  void _centerOnDelivery() {
    if (_deliveryLocation != null && _mapController != null && _isMapReady) {
      _animateToLocation(_deliveryLocation!);
    }
  }

  void _centerOnDriver() {
    if (_driverLocation != null && _mapController != null && _isMapReady) {
      _animateToLocation(_driverLocation!);
    }
  }

  void _toggleFollowDriver() {
    setState(() => _followDriver = !_followDriver);
    if (_followDriver) {
      _animateFollowDriverCamera(force: true);
    }
  }

  Future<void> _launchPhone(String? phone, String fallbackMessage) async {
    final cleanedPhone = phone?.trim();
    if (cleanedPhone == null || cleanedPhone.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(fallbackMessage)));
      return;
    }
    final launched = await launchUrl(Uri(scheme: 'tel', path: cleanedPhone),
        mode: LaunchMode.externalApplication);
    if (!launched && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open the phone dialer.')));
    }
  }

  void _openSupport({bool openChat = false}) {
    Navigator.pushNamed(context, '/support',
        arguments: {'order': _order, 'openChat': openChat});
  }

  LatLng? get _mapTarget =>
      _driverLocation ?? _restaurantLocation ?? _deliveryLocation;
  bool get _hasVisibleMap => _mapTarget != null;

  void _fitMapToRoute() {
    if (_mapController == null || !_isMapReady) return;

    if (_currentRoute.isNotEmpty) {
      final latitudes = _currentRoute.map((point) => point.latitude);
      final longitudes = _currentRoute.map((point) => point.longitude);
      final minLat = latitudes.reduce((a, b) => a < b ? a : b);
      final maxLat = latitudes.reduce((a, b) => a > b ? a : b);
      final minLng = longitudes.reduce((a, b) => a < b ? a : b);
      final maxLng = longitudes.reduce((a, b) => a > b ? a : b);

      final latPadding = (maxLat - minLat) * 0.1;
      final lngPadding = (maxLng - minLng) * 0.1;

      _mapController!.animateCamera(
        CameraUpdate.newLatLngBounds(
          LatLngBounds(
            southwest: LatLng(minLat - latPadding, minLng - lngPadding),
            northeast: LatLng(maxLat + latPadding, maxLng + lngPadding),
          ),
          40,
        ),
      );
      return;
    }

    if (_restaurantLocation != null && _deliveryLocation != null) {
      final minLat = _restaurantLocation!.latitude < _deliveryLocation!.latitude
          ? _restaurantLocation!.latitude
          : _deliveryLocation!.latitude;
      final maxLat = _restaurantLocation!.latitude > _deliveryLocation!.latitude
          ? _restaurantLocation!.latitude
          : _deliveryLocation!.latitude;
      final minLng =
          _restaurantLocation!.longitude < _deliveryLocation!.longitude
              ? _restaurantLocation!.longitude
              : _deliveryLocation!.longitude;
      final maxLng =
          _restaurantLocation!.longitude > _deliveryLocation!.longitude
              ? _restaurantLocation!.longitude
              : _deliveryLocation!.longitude;

      final latPadding = (maxLat - minLat) * 0.1;
      final lngPadding = (maxLng - minLng) * 0.1;

      _mapController!.animateCamera(
        CameraUpdate.newLatLngBounds(
          LatLngBounds(
            southwest: LatLng(minLat - latPadding, minLng - lngPadding),
            northeast: LatLng(maxLat + latPadding, maxLng + lngPadding),
          ),
          40,
        ),
      );
      return;
    }

    if (_mapTarget != null) _animateToLocation(_mapTarget!);
  }

  void _openFullScreenMap() {
    setState(() => _isFullScreenMap = true);
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => FullScreenMapScreen(
          routePoints: _currentRoute,
          markers: _markers,
          markersListenable: _markersNotifier,
          polylines: _polylines,
          restaurantLocation: _restaurantLocation,
          deliveryLocation: _deliveryLocation,
          driverLocation: _driverLocation,
          order: _order,
          isPickedUp: _isOrderPickedUp,
          estimatedTime: _estimatedTime,
          distanceRemaining: _distanceRemaining,
          onClose: () => setState(() => _isFullScreenMap = false),
        ),
      ),
    ).then((_) {
      setState(() => _isFullScreenMap = false);
      if (_mapController != null && _isMapReady) {
        Future.delayed(
            const Duration(milliseconds: 300), () => _fitMapToRoute());
      }
    });
  }

  String _estimatedTimeFor(Order order) {
    if (order.isTakeaway) {
      if (order.isDelivered || order.isPickedUp) return 'Picked up';
      if (order.isReadyForPickup) return 'Ready now';
      if (order.isPreparing) return '15-20 mins';
      return '20-25 mins';
    }

    if (order.isDelivered) return 'Delivered';
    if (order.etaRange != null && order.etaRange!.isNotEmpty) {
      return order.etaRange!;
    }
    final minutes = order.etaMinutes;
    if (minutes != null && minutes > 0) return '$minutes mins';
    if (order.isOnTheWay || order.isPickedUp) return _estimatedTime;
    if (order.isReadyForPickup) return '15-20 mins';
    if (order.hasActivePreparationTimer || order.isPreparationDelayed) {
      final remaining = order.remainingPreparationMinutes;
      if (remaining != null && remaining > 0) {
        return '$remaining-${remaining + 10} mins';
      }
      return order.isPreparationDelayed ? 'Delayed' : 'Almost ready';
    }
    if (order.isPreparing) return '20-25 mins';
    return '30-35 mins';
  }

  void _showHelpDialog() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) => Container(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Center(
                child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 20),
            const Text('Need Help?',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            if (_order?.isDelivered != true)
              _buildHelpOption(
                icon: Icons.phone,
                title: 'Call Store',
                subtitle: _order?.restaurant?.phone?.trim().isNotEmpty == true
                    ? _order!.restaurant!.phone
                    : 'Store phone unavailable',
                onTap: () {
                  Navigator.pop(context);
                  _launchPhone(_order?.restaurant?.phone,
                      'Store phone number is not available.');
                },
              ),
            _buildHelpOption(
              icon: Icons.support_agent,
              title: 'Customer Support',
              subtitle: '24/7 support available',
              onTap: () {
                Navigator.pop(context);
                _openSupport(openChat: true);
              },
            ),
            _buildHelpOption(
              icon: Icons.message,
              title: 'Live Chat',
              subtitle: 'Chat with support team',
              onTap: () {
                Navigator.pop(context);
                _openSupport(openChat: true);
              },
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildHelpOption(
      {required IconData icon,
      required String title,
      required String subtitle,
      required VoidCallback onTap}) {
    return ListTile(
      leading: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
              color: _primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: _primary)),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text(subtitle,
          style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }

  String _formatDuration(Duration duration) {
    if (duration.inSeconds <= 0) {
      return '00:00';
    }

    final minutes = duration.inMinutes.remainder(60).toString().padLeft(2, '0');
    final seconds = duration.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }

  String _refundStatusText(Order order) {
    switch (order.refundStatus) {
      case 'completed':
        return 'Refund completed';
      case 'processing':
        return 'Refund is being processed';
      case 'pending':
        return 'Refund request submitted';
      case 'rejected':
        return 'Refund request rejected';
      default:
        return 'Refund will follow the active admin policy';
    }
  }

  String _refundModeText(Order order) {
    final label = order.refundModeLabel?.trim();
    if (label != null && label.isNotEmpty) return label;

    switch (order.refundMode?.toLowerCase()) {
      case 'wallet':
        return 'Customer wallet';
      case 'razorpay':
        return 'Razorpay';
      case 'stripe':
        return 'Stripe';
      case 'cashfree':
        return 'Cashfree';
      case 'paystack':
        return 'Paystack';
      case 'mollie':
        return 'Mollie';
      case 'mercadopago':
        return 'Mercado Pago';
      case 'cod':
      case 'manual':
        return 'Manual adjustment';
      default:
        return order.refundStatus == null
            ? 'As per admin refund policy'
            : 'Original payment mode or wallet';
    }
  }

  String _paymentStatusLabel(Order order) {
    switch (order.paymentStatus.toLowerCase()) {
      case 'success':
      case 'paid':
        return 'Paid';
      case 'failed':
        return 'Failed';
      case 'refunded':
        return 'Refunded';
      default:
        return 'Pending';
    }
  }

  String _paymentMethodLabel(String value) {
    final normalized = value.toLowerCase();
    switch (normalized) {
      case 'cod':
        return 'Cash on Delivery';
      case 'cash':
        return 'Cash';
      case 'razorpay':
        return 'Razorpay';
      case 'stripe':
        return 'Stripe';
      case 'cashfree':
        return 'Cashfree';
      default:
        return value.replaceAll('_', ' ').toUpperCase();
    }
  }

  Future<void> _showPayNowSheet() async {
    final order = _order;
    if (order == null || !order.canPayOnlineNow || _isStartingPayment) return;

    final user = context.read<AuthProvider>().currentUser;
    final gateway = _adminConfiguredPaymentGateway(user);
    if (gateway == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Online payment is not available right now')),
      );
      return;
    }

    setState(() => _isStartingPayment = true);
    try {
      final launched = await _paymentService.pay(
        orderId: order.id,
        gateway: gateway,
        cancelOrderOnFailure: false,
        customerName: order.customerName,
        customerEmail: user?.email,
        customerPhone: order.customerPhone.trim().isNotEmpty
            ? order.customerPhone
            : user?.phone,
      );
      if (launched && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${_adminConfiguredPaymentGatewayLabel(user, gateway)} payment submitted. Waiting for confirmation.',
            ),
          ),
        );
      }
      await _pollPaymentAfterLaunch(order.id);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Bad state: ', ''))),
        );
      }
    } finally {
      if (mounted) setState(() => _isStartingPayment = false);
    }
  }

  String? _adminConfiguredPaymentGateway(User? user) {
    if (user == null || !user.isPaymentGatewayEnabled) return null;

    final provider = user.paymentGatewayProvider.toLowerCase();
    if (_isTrackOrderPaymentGateway(provider)) return provider;

    for (final gateway in user.paymentGateways) {
      if (gateway.selected && _isTrackOrderPaymentGateway(gateway.key)) {
        return gateway.key;
      }
    }

    for (final gateway in user.enabledPaymentGatewayKeys) {
      if (_isTrackOrderPaymentGateway(gateway)) return gateway;
    }

    return null;
  }

  bool _isTrackOrderPaymentGateway(String provider) {
    return {'razorpay', 'stripe', 'cashfree'}.contains(provider);
  }

  String _adminConfiguredPaymentGatewayLabel(User? user, String gateway) {
    for (final option in user?.paymentGateways ?? const []) {
      if (option.key == gateway && option.label.trim().isNotEmpty) {
        return option.label;
      }
    }

    return _paymentMethodLabel(gateway);
  }

  Future<void> _pollPaymentAfterLaunch(int orderId) async {
    for (var attempt = 0; attempt < 8 && mounted; attempt++) {
      await Future<void>.delayed(const Duration(seconds: 2));
      final status = await _paymentService.paymentStatus(orderId);
      if (status?['payment_status'] == 'success') {
        await _loadOrderDetails(showLoading: false);
        return;
      }
    }
    await _loadOrderDetails(showLoading: false);
  }

  Widget _buildPaymentStatusCard() {
    final order = _order!;
    final paid = order.isPaymentPaid;
    final color = paid
        ? const Color(0xFF16A34A)
        : order.paymentStatus == 'failed'
            ? const Color(0xFFE11D48)
            : const Color(0xFFFF9800);

    return _premiumCard(
      padding: const EdgeInsets.all(16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              paid ? Icons.verified_rounded : Icons.payments_outlined,
              color: color,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _paymentStatusLabel(order),
                  style: const TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF171717),
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  paid
                      ? 'Paid via ${_paymentMethodLabel(order.paymentMethod)}'
                      : 'Amount due: ${formatCurrency(context, order.total)}',
                  style: const TextStyle(
                      fontSize: 12, color: Color(0xFF666666)),
                ),
                if (order.paidAt != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    DateFormat('d MMM, h:mm a').format(order.paidAt!),
                    style: const TextStyle(
                        fontSize: 11.5, color: Color(0xFF999999)),
                  ),
                ],
              ],
            ),
          ),
          if (order.canPayOnlineNow) ...[
            const SizedBox(width: 8),
            ElevatedButton(
              onPressed: _isStartingPayment ? null : _showPayNowSheet,
              style: ElevatedButton.styleFrom(
                backgroundColor: _primary,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: _isStartingPayment
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Pay Now'),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _submitCancellation({
    required Order order,
    required String reason,
    required bool isForceCancel,
  }) async {
    final orderProvider = Provider.of<OrderProvider>(context, listen: false);
    final success = await orderProvider.cancelOrder(order.id, reason);

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isForceCancel
                ? 'Order cancelled. Refund will follow the active policy.'
                : 'Order cancelled successfully.',
          ),
          backgroundColor: Colors.green,
        ),
      );
      await _loadOrderDetails(showLoading: false);
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          orderProvider.error ??
              'Unable to cancel this order right now. Please try again.',
        ),
        backgroundColor: Colors.red,
      ),
    );
  }

  void _showCancelOrderSheet({required bool isForceCancel}) {
    final order = _order;
    if (order == null) return;

    final reasonController = TextEditingController();
    final Color accent =
        isForceCancel ? const Color(0xFFE11D48) : _cancelAccent;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        final viewInsets = MediaQuery.of(sheetContext).viewInsets;
        return Padding(
          padding: EdgeInsets.only(bottom: viewInsets.bottom),
          child: Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
            child: SafeArea(
              top: false,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 42,
                        height: 5,
                        decoration: BoxDecoration(
                          color: const Color(0xFFDCE1EA),
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 46,
                          height: 46,
                          decoration: BoxDecoration(
                            color: accent.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(16),
                          ),
                          child: Icon(
                            Icons.cancel_outlined,
                            color: accent,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Cancel order',
                                style: TextStyle(
                                  color: FoodFlowTheme.ink,
                                  fontSize: 18,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                isForceCancel
                                    ? 'The store has already accepted this order. Refund will follow the active admin refund policy.'
                                    : 'Free cancellation for ${_formatDuration(order.remainingCancellationTime)} more.',
                                style: const TextStyle(
                                  color: FoodFlowTheme.muted,
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
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
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF7F8FC),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFE4E8F0)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  '#${order.orderNumber}',
                                  style: const TextStyle(
                                    fontSize: 13.5,
                                    fontWeight: FontWeight.w800,
                                    color: FoodFlowTheme.ink,
                                  ),
                                ),
                              ),
                              Text(
                                formatCurrency(context, order.total),
                                style: const TextStyle(
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w900,
                                  color: FoodFlowTheme.ink,
                                ),
                              ),
                            ],
                          ),
                          if (order.restaurant?.name != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              order.restaurant!.name,
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: FoodFlowTheme.muted,
                              ),
                            ),
                          ],
                          const SizedBox(height: 10),
                          const Divider(
                              height: 1, color: Color(0xFFE4E8F0)),
                          const SizedBox(height: 10),
                          Text(
                            order.items
                                .map((item) => '${item.quantity}x ${item.name}')
                                .join(', '),
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF444444),
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: reasonController,
                      maxLines: 3,
                      decoration: InputDecoration(
                        labelText: 'Reason',
                        hintText: isForceCancel
                            ? 'Need urgent cancellation, ordered by mistake, change of plan...'
                            : 'Ordered by mistake, wrong address, changed my mind...',
                        alignLabelWithHint: true,
                      ),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => Navigator.pop(sheetContext),
                            child: const Text('Keep Order'),
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
                                    content: Text('Please enter a reason.'),
                                    backgroundColor: Colors.red,
                                  ),
                                );
                                return;
                              }

                              Navigator.pop(sheetContext);
                              await _submitCancellation(
                                order: order,
                                reason: reason,
                                isForceCancel: isForceCancel,
                              );
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: accent,
                              elevation: 0,
                            ),
                            child: const Text('Cancel Order'),
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
      },
    );
  }

  Widget _buildDeliveryOtpCard() {
    final order = _order!;
    final isTakeaway = order.isTakeaway;
    final hasOtp =
        order.deliveryOtp?.trim().isNotEmpty == true && !order.isDelivered;

    if (!hasOtp && isTakeaway) return const SizedBox.shrink();

    final title = isTakeaway ? 'Pickup OTP' : 'Delivery OTP';
    final message = hasOtp
        ? (isTakeaway
            ? 'Share this with the store staff when collecting your order.'
            : 'Share this with the delivery partner only at delivery.')
        : (order.isDelivered
            ? 'Order completed'
            : 'Delivery OTP will appear when the driver is nearby');

    return _premiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: hasOtp
                      ? _primary.withOpacity(0.1)
                      : const Color(0xFFF5F5F5),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  hasOtp ? Icons.password_rounded : Icons.lock_clock_rounded,
                  color: hasOtp ? _primary : const Color(0xFF777777),
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        color: Color(0xFF171717),
                        fontSize: 14.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      message,
                      softWrap: true,
                      style: const TextStyle(
                        color: Color(0xFF666666),
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (hasOtp) ...[
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (final digit in order.deliveryOtp!.split(''))
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 5),
                    child: Container(
                      width: 42,
                      height: 50,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF7F7F7),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: _primary.withOpacity(0.3)),
                      ),
                      child: Text(
                        digit,
                        style: const TextStyle(
                          color: Color(0xFF171717),
                          fontSize: 24,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  static const Color _cancelAccent = Color(0xFFF97316);

  Widget _buildCancellationWindowCard() {
    final order = _order;
    if (order == null || order.isCancelled) return const SizedBox.shrink();

    final bool inFreeWindow = order.isPending && order.canCancel;
    final bool eligibleForForceCancel = !inFreeWindow && order.canForceCancel;

    if (!inFreeWindow && !eligibleForForceCancel) {
      return const SizedBox.shrink();
    }

    final String title = inFreeWindow
        ? 'Cancellation available for ${_formatDuration(order.remainingCancellationTime)}'
        : 'Need to cancel this order?';
    final String subtitle = inFreeWindow
        ? 'You can cancel free of cost within 2 minutes of placing it.'
        : 'The store has already accepted this order. Cancelling now may attract a refund review.';

    return _premiumCard(
      padding: EdgeInsets.zero,
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () =>
            _showCancelOrderSheet(isForceCancel: !inFreeWindow),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: _cancelAccent.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(Icons.timer_outlined, color: _cancelAccent),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF171717),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(Icons.chevron_right_rounded, color: Colors.grey.shade400),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCancelledStateCard() {
    final order = _order!;
    return _premiumCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  color: const Color(0xFFE11D48).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: const Icon(Icons.cancel_rounded,
                    color: Color(0xFFE11D48), size: 28),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Order cancelled',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                        color: Color(0xFF171717),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      order.cancellationReason?.trim().isNotEmpty == true
                          ? order.cancellationReason!
                          : 'This order has been cancelled.',
                      style: TextStyle(
                        fontSize: 12.5,
                        color: Colors.grey.shade700,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (order.refundStatus != null || order.refundAmount != null) ...[
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFF7F7F7),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Refund status',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF666666),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _refundStatusText(order),
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (order.refundAmount != null) ...[
                    const SizedBox(height: 6),
                    Text(
                      'Refund amount: ${formatCurrency(context, order.refundAmount!)}',
                      style: TextStyle(
                        fontSize: 12.5,
                        color: Colors.grey.shade700,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  const SizedBox(height: 6),
                  Text(
                    'Refund mode: ${_refundModeText(order)}',
                    style: TextStyle(
                      fontSize: 12.5,
                      color: Colors.grey.shade700,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (order.refundTransactionId?.trim().isNotEmpty == true) ...[
                    const SizedBox(height: 6),
                    Text(
                      'Reference: ${order.refundTransactionId}',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey.shade600,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildCancelledTimelineCard() {
    final order = _order!;
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Timeline',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 20),
          _buildStaticTimelineRow(
            icon: Icons.receipt,
            title: 'Order placed',
            subtitle: 'Your order was received successfully.',
            color: _primary,
            isFirst: true,
          ),
          _buildStaticTimelineRow(
            icon: Icons.cancel_rounded,
            title: 'Order cancelled',
            subtitle: order.cancellationReason?.trim().isNotEmpty == true
                ? order.cancellationReason!
                : 'Cancellation was processed successfully.',
            color: const Color(0xFFE11D48),
            isFirst: false,
          ),
          if (order.refundStatus != null)
            _buildStaticTimelineRow(
              icon: Icons.account_balance_wallet_outlined,
              title: _refundStatusText(order),
              subtitle: order.refundAmount != null
                  ? 'Amount: ${formatCurrency(context, order.refundAmount!)} - Mode: ${_refundModeText(order)}'
                  : 'Refund will be handled according to the active policy.',
              color: const Color(0xFFFF6E00),
              isFirst: false,
              showConnector: false,
            ),
        ],
      ),
    );
  }

  Widget _buildStaticTimelineRow({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
    required bool isFirst,
    bool showConnector = true,
  }) {
    return Padding(
      padding: EdgeInsets.only(bottom: showConnector ? 16 : 0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: color,
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 18, color: Colors.white),
              ),
              if (showConnector)
                Container(
                  width: 2,
                  height: 28,
                  color: const Color(0xFFE5E7EB),
                ),
            ],
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTrackingMapCard() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.grey.shade200),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withOpacity(0.08),
              blurRadius: 12,
              offset: const Offset(0, 4))
        ],
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                      color: _primary.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12)),
                  child: Icon(
                      _order?.isTakeaway == true
                          ? Icons.shopping_bag
                          : _isOrderPickedUp
                              ? Icons.delivery_dining
                              : Icons.route,
                      color: _primary,
                      size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _order?.isTakeaway == true
                            ? 'Pickup Location'
                            : _isOrderPickedUp
                                ? 'Live Delivery Tracking'
                                : 'Delivery Route',
                        style: const TextStyle(
                            fontSize: 16, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        _order?.isTakeaway == true
                            ? 'Collect your order from the store'
                            : _isOrderPickedUp
                                ? 'Driver is on the way to you'
                                : 'Estimated route to your location',
                        style: TextStyle(
                            fontSize: 12, color: Colors.grey.shade600),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                      color: _primary.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(20)),
                  child: Column(
                    children: [
                      Text(_estimatedTime,
                          style: TextStyle(
                              color: _primary,
                              fontSize: 13,
                              fontWeight: FontWeight.w700)),
                      if (_isOrderPickedUp)
                        Text(_distanceRemaining,
                            style: TextStyle(
                                fontSize: 10, color: Colors.grey.shade600)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          GestureDetector(
            onTap: _openFullScreenMap,
            child: SizedBox(
              height: 300,
              child: ClipRRect(
                borderRadius:
                    const BorderRadius.vertical(bottom: Radius.circular(20)),
                child: Stack(
                  children: [
                    if (_hasVisibleMap)
                      ValueListenableBuilder<Set<Marker>>(
                        valueListenable: _markersNotifier,
                        builder: (context, markers, _) => GoogleMap(
                          onMapCreated: _onMapCreated,
                          initialCameraPosition:
                              CameraPosition(target: _mapTarget!, zoom: 14),
                          markers: markers,
                          polylines: _polylines,
                          myLocationEnabled: true,
                          myLocationButtonEnabled: true,
                          zoomControlsEnabled: true,
                          zoomGesturesEnabled: true,
                          compassEnabled: true,
                          mapToolbarEnabled: false,
                          padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
                        ),
                      )
                    else
                      _buildRouteFallback(),
                    if (_isOrderPickedUp)
                      Positioned(
                        top: 16,
                        left: 16,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.95),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(
                                  color: const Color(0xFF4CAF50)
                                      .withOpacity(0.25))),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              AnimatedBuilder(
                                animation: _pulseAnimation,
                                builder: (context, child) => Container(
                                    width: 8,
                                    height: 8,
                                    decoration: const BoxDecoration(
                                        color: Color(0xFF4CAF50),
                                        shape: BoxShape.circle)),
                              ),
                              const SizedBox(width: 8),
                              const Text('Live tracking',
                                  style: TextStyle(
                                      color: Color(0xFF4CAF50),
                                      fontWeight: FontWeight.w700,
                                      fontSize: 12)),
                            ],
                          ),
                        ),
                      ),
                    Positioned(
                      top: 16,
                      right: 16,
                      child: Material(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        elevation: 2,
                        child: InkWell(
                          onTap: _openFullScreenMap,
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                              padding: const EdgeInsets.all(10),
                              child: Icon(Icons.fullscreen,
                                  color: _primary, size: 20)),
                        ),
                      ),
                    ),
                    Positioned(
                        left: 12,
                        right: 12,
                        bottom: 16,
                        child: _buildRouteSummary()),
                    if (_hasVisibleMap)
                      Positioned(
                        bottom: 80,
                        right: 12,
                        child: Column(
                          children: [
                            _buildMapButton(
                                icon: Icons.my_location,
                                onPressed: _fitMapToRoute),
                            const SizedBox(height: 8),
                            if (_restaurantLocation != null &&
                                (!_isOrderPickedUp ||
                                    _order?.isTakeaway == true))
                              _buildMapButton(
                                  icon: Icons.restaurant,
                                  onPressed: _centerOnRestaurant),
                            const SizedBox(height: 8),
                            if (_driverLocation != null) ...[
                              const SizedBox(height: 8),
                              _buildMapButton(
                                  icon: Icons.navigation,
                                  isSelected: _followDriver,
                                  onPressed: _toggleFollowDriver),
                            ],
                            if (_order?.isTakeaway != true)
                              _buildMapButton(
                                  icon: Icons.location_on,
                                  onPressed: _centerOnDelivery),
                            if (_driverLocation != null &&
                                _isOrderPickedUp) ...[
                              const SizedBox(height: 8),
                              _buildMapButton(
                                  icon: Icons.delivery_dining,
                                  onPressed: _centerOnDriver),
                            ],
                          ],
                        ),
                      ),
                    if (_hasVisibleMap && !_isMapReady)
                      Positioned.fill(
                          child: Container(
                              color: Colors.white.withOpacity(0.65),
                              child: const Center(
                                  child: CircularProgressIndicator()))),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMapButton({
    required IconData icon,
    required VoidCallback onPressed,
    bool isSelected = false,
  }) {
    return Material(
      color: isSelected ? _primary : Colors.white,
      borderRadius: BorderRadius.circular(28),
      elevation: 3,
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(28),
        child: Container(
          width: 46,
          height: 46,
          decoration: const BoxDecoration(shape: BoxShape.circle),
          child: Icon(
            icon,
            color: isSelected ? Colors.white : _primary,
            size: 22,
          ),
        ),
      ),
    );
  }

  Widget _buildRouteFallback() {
    final isTakeaway = _order?.isTakeaway == true;

    return Container(
      color: const Color(0xFFF7F7F7),
      padding: const EdgeInsets.fromLTRB(18, 24, 18, 90),
      child: Row(
        children: [
          Column(
            children: [
              _buildRouteDot(Icons.restaurant, _primary),
              if (!isTakeaway) ...[
                Expanded(
                    child: Container(
                        width: 3,
                        margin: const EdgeInsets.symmetric(vertical: 8),
                        color: Colors.orange.shade200)),
                _buildRouteDot(Icons.home, Colors.green),
              ],
            ],
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                _buildRoutePointText(
                    title: _order!.restaurant?.name ?? 'Store',
                    subtitle: _order!.restaurant?.address ?? 'Pickup location'),
                if (!isTakeaway)
                  _buildRoutePointText(
                      title: 'Delivery address',
                      subtitle: _order!.deliveryAddress)
                else
                  _buildRoutePointText(
                      title: 'Pickup instruction',
                      subtitle: 'Show this order at the store counter.'),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRouteDot(IconData icon, Color color) {
    return Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        child: Icon(icon, color: Colors.white, size: 20));
  }

  Widget _buildRoutePointText(
      {required String title, required String subtitle}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        Text(subtitle.isEmpty ? 'Location details unavailable' : subtitle,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(fontSize: 12, color: Colors.grey.shade700)),
      ],
    );
  }

  Widget _buildRouteSummary() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
                color: Colors.black.withOpacity(0.10),
                blurRadius: 12,
                offset: const Offset(0, 3))
          ]),
      child: Row(
        children: [
          Icon(
            _order?.isTakeaway == true
                ? Icons.shopping_bag
                : _isOrderPickedUp
                    ? Icons.delivery_dining
                    : Icons.restaurant,
            color: _primary,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _order!.isTakeaway
                      ? _order!.statusText
                      : _isOrderPickedUp
                          ? 'Delivery partner is heading to you'
                          : _order!.statusText,
                  style: const TextStyle(
                      fontSize: 13, fontWeight: FontWeight.w700),
                ),
                if (!_order!.isTakeaway && _distanceRemaining.isNotEmpty)
                  Text('Delivery distance: $_distanceRemaining',
                      style:
                          TextStyle(fontSize: 11, color: Colors.grey.shade600)),
              ],
            ),
          ),
          if (!_order!.isTakeaway && _distanceRemaining.isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                  color: const Color(0xFF4CAF50).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12)),
              child: Text(_distanceRemaining,
                  style: const TextStyle(
                      color: Color(0xFF4CAF50),
                      fontSize: 12,
                      fontWeight: FontWeight.w600)),
            ),
        ],
      ),
    );
  }

  Future<bool> _onWillPop() async {
    if (Navigator.canPop(context)) {
      return true;
    }
    Navigator.pushReplacementNamed(context, '/customer/home');
    return false;
  }

  void _handleBack() {
    if (Navigator.canPop(context)) {
      Navigator.pop(context);
      return;
    }
    Navigator.pushReplacementNamed(context, '/customer/home');
  }

  Widget _premiumCard({required Widget child, EdgeInsetsGeometry? padding}) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      padding: padding ?? const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFEDEDED)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: child,
    );
  }

  Color get _bannerColor {
    final order = _order!;
    if (order.isCancelled) return const Color(0xFFE11D48);
    return const Color(0xFF1DB955);
  }

  String get _bannerHeadline {
    final order = _order!;
    if (order.isCancelled) return 'Order Cancelled';
    if (order.isDelivered)
      return order.isTakeaway ? 'Order Collected' : 'Order Delivered';
    if (order.isTakeaway) {
      return order.isReadyForPickup
          ? 'Ready for pickup'
          : 'Preparing your order';
    }
    return _isOrderPickedUp ? 'Order is on the way' : 'Preparing your order';
  }

  String get _bannerEmoji {
    final order = _order!;
    if (order.isCancelled) return '';
    if (order.isDelivered) return ' 🎉';
    if (_isOrderPickedUp) return ' 🛵';
    return ' 👨‍🍳';
  }

  Future<void> _shareOrderStatus() async {
    final order = _order!;
    await Share.share(
      'Track my order #${order.orderNumber} from ${order.restaurant?.name ?? 'the restaurant'} — ${order.statusText}.',
    );
  }

  Future<void> _scrollToOrderSummary() async {
    if (_sheetController.isAttached) {
      await _sheetController.animateTo(
        0.9,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
      );
    }
    final context = _orderSummaryKey.currentContext;
    if (context == null) return;
    Scrollable.ensureVisible(
      context,
      duration: const Duration(milliseconds: 400),
      curve: Curves.easeOut,
    );
  }

  Widget _bannerIconButton({
    required IconData icon,
    required VoidCallback onTap,
    double size = 38,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: size,
        height: size,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.18),
          shape: BoxShape.circle,
        ),
        child: Icon(icon, color: Colors.white, size: 20),
      ),
    );
  }

  /// Compact card version of the status header, floated over the full-screen
  /// map (Zomato-style) instead of the full-width banner that pushes content
  /// down. Reuses the same `_banner*` state/getters as `_buildStatusBanner`.
  Widget _buildFloatingStatusBar() {
    final order = _order!;
    return Container(
      padding: const EdgeInsets.fromLTRB(10, 8, 10, 12),
      decoration: BoxDecoration(
        color: _bannerColor,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.20),
            blurRadius: 18,
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
              _bannerIconButton(
                icon: Icons.arrow_back_rounded,
                onTap: _handleBack,
                size: 32,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  order.restaurant?.name ?? 'Order Tracking',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              _bannerIconButton(
                icon: Icons.share_outlined,
                onTap: _shareOrderStatus,
                size: 32,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.only(left: 6),
            child: Row(
              children: [
                Expanded(
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 250),
                    child: Text(
                      '$_bannerHeadline$_bannerEmoji',
                      key: ValueKey('float_headline_$_bannerHeadline'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
                if (!order.isCancelled) ...[
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.18),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: AnimatedSwitcher(
                      duration: const Duration(milliseconds: 250),
                      child: Text(
                        order.isDelivered
                            ? 'Delivered'
                            : 'Arriving in $_estimatedTime',
                        key: ValueKey('float_eta_$_estimatedTime'),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                  if (!order.isDelivered) ...[
                    const SizedBox(width: 6),
                    _bannerIconButton(
                      icon: Icons.refresh_rounded,
                      onTap: () => _loadOrderDetails(showLoading: false),
                      size: 28,
                    ),
                  ],
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderSummaryLink() {
    final order = _order!;
    if (order.isCancelled) return const SizedBox.shrink();
    return InkWell(
      onTap: _scrollToOrderSummary,
      child: Container(
        width: double.infinity,
        color: Colors.white,
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        child: Row(
          children: [
            Expanded(
              child: Text(
                'Order #${order.orderNumber} · View order summary',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF16A34A),
                ),
              ),
            ),
            const Icon(Icons.chevron_right_rounded,
                size: 20, color: Color(0xFF16A34A)),
          ],
        ),
      ),
    );
  }

  Widget _buildHeroStatusCard() {
    final order = _order!;
    if (order.isCancelled) {
      return _premiumCard(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 20),
        child: Text(
          _refundStatusText(order),
          softWrap: true,
          style: const TextStyle(
            fontSize: 13,
            color: Color(0xFF666666),
            fontWeight: FontWeight.w500,
          ),
        ),
      );
    }

    return _premiumCard(
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'LIVE STATUS',
            style: TextStyle(
              fontSize: 11.5,
              letterSpacing: 0.8,
              color: Color(0xFF666666),
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 18),
          _buildStatusTimeline(),
        ],
      ),
    );
  }

  Widget _buildStatusTimeline() {
    final order = _order!;
    final steps = order.isTakeaway
        ? [
            _UiTrackStep('Order Placed', Icons.storefront_outlined,
                order.createdAt, true),
            _UiTrackStep(
                'Preparing',
                Icons.shopping_bag_outlined,
                order.preparingAt ?? order.confirmedAt,
                order.isPreparing ||
                    order.isReadyForPickup ||
                    order.isDelivered),
            _UiTrackStep('Ready', Icons.local_mall_outlined, order.readyAt,
                order.isReadyForPickup || order.isDelivered),
            _UiTrackStep('Collected', Icons.home_rounded, order.deliveredAt,
                order.isDelivered),
          ]
        : [
            _UiTrackStep('Order Placed', Icons.storefront_outlined,
                order.createdAt, true),
            _UiTrackStep(
                'Preparing',
                Icons.room_service_outlined,
                order.preparingAt ?? order.confirmedAt,
                order.isPreparing ||
                    order.isReadyForPickup ||
                    order.isPickedUp ||
                    order.isOnTheWay ||
                    order.isDelivered),
            _UiTrackStep(
                'On The Way',
                Icons.delivery_dining,
                order.driverAcceptedAt ?? order.reachedAt ?? order.readyAt,
                order.isPickedUp || order.isOnTheWay || order.isDelivered),
            _UiTrackStep('Delivered', Icons.home_rounded, order.deliveredAt,
                order.isDelivered),
          ];

    return Column(
      children: List.generate(steps.length, (index) {
        final step = steps[index];
        final isLast = index == steps.length - 1;
        final nextDone = !isLast && steps[index + 1].isDone;
        final isCurrent = !step.isDone &&
            (index == 0 || steps[index - 1].isDone) &&
            !order.isCancelled;
        return IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Column(
                children: [
                  Container(
                    width: isCurrent ? 20 : 16,
                    height: isCurrent ? 20 : 16,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: step.isDone
                          ? _primary
                          : isCurrent
                              ? Colors.white
                              : const Color(0xFFF2F2F2),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: step.isDone || isCurrent
                            ? _primary
                            : const Color(0xFFD8D8D8),
                        width: isCurrent ? 2.5 : 2,
                      ),
                    ),
                    child: step.isDone
                        ? const Icon(Icons.check_rounded,
                            color: Colors.white, size: 11)
                        : isCurrent
                            ? Container(
                                width: 8,
                                height: 8,
                                decoration: BoxDecoration(
                                  color: _primary,
                                  shape: BoxShape.circle,
                                ),
                              )
                            : null,
                  ),
                  if (!isLast)
                    Expanded(
                      child: Container(
                        width: 2,
                        color: nextDone ? _primary : const Color(0xFFE4E4E4),
                      ),
                    ),
                ],
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Padding(
                  padding: EdgeInsets.only(bottom: isLast ? 0 : 20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        step.label,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: step.isDone
                              ? const Color(0xFF171717)
                              : isCurrent
                                  ? _primary
                                  : const Color(0xFF9A9A9A),
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        isCurrent
                            ? 'In progress'
                            : step.time == null
                                ? 'Pending'
                                : DateFormat('hh:mm a').format(step.time!),
                        style: TextStyle(
                          fontSize: 12,
                          color: isCurrent ? _primary : const Color(0xFF888888),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildPremiumTrackingMap({bool fullScreen = false}) {
    // In fullScreen mode the map sits behind the floating status bar and the
    // draggable bottom sheet, so the camera-fit padding must reserve enough
    // room for both — otherwise CameraUpdate.newLatLngBounds() centers the
    // route (and its polyline) partly underneath the opaque sheet.
    final mapPadding = fullScreen
        ? EdgeInsets.fromLTRB(
            16,
            MediaQuery.paddingOf(context).top + 140,
            16,
            MediaQuery.sizeOf(context).height * 0.44,
          )
        : const EdgeInsets.fromLTRB(16, 54, 16, 92);

    final content = Stack(
        children: [
          Positioned.fill(
            child: _hasVisibleMap
                ? ValueListenableBuilder<Set<Marker>>(
                    valueListenable: _markersNotifier,
                    builder: (context, markers, _) => GoogleMap(
                      onMapCreated: _onMapCreated,
                      initialCameraPosition:
                          CameraPosition(target: _mapTarget!, zoom: 14),
                      markers: markers,
                      polylines: _polylines,
                      myLocationEnabled: true,
                      myLocationButtonEnabled: false,
                      zoomControlsEnabled: false,
                      zoomGesturesEnabled: true,
                      compassEnabled: false,
                      mapToolbarEnabled: false,
                      padding: mapPadding,
                    ),
                  )
                : _buildRouteFallback(),
          ),
          Positioned(
            left: 14,
            top: 14,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.96),
                borderRadius: BorderRadius.circular(14),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.10),
                    blurRadius: 14,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 9,
                    height: 9,
                    decoration: BoxDecoration(
                      color:
                          _isOrderPickedUp ? const Color(0xFF22C55E) : _primary,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    _isOrderPickedUp ? 'Live tracking' : _order!.statusText,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF222222),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Positioned(
            right: 14,
            bottom: 14,
            child: Column(
              children: [
                _buildMapButton(
                    icon: Icons.fullscreen, onPressed: _openFullScreenMap),
                const SizedBox(height: 10),
                _buildMapButton(
                    icon: Icons.my_location, onPressed: _fitMapToRoute),
                if (_driverLocation != null) ...[
                  const SizedBox(height: 10),
                  _buildMapButton(
                    icon: Icons.navigation,
                    isSelected: _followDriver,
                    onPressed: _toggleFollowDriver,
                  ),
                ],
              ],
            ),
          ),
        ],
      );

    if (fullScreen) {
      return Positioned.fill(child: content);
    }

    final mapHeight = MediaQuery.sizeOf(context).height * 0.42;
    return Container(
      height: mapHeight.clamp(320.0, 460.0),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(24)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.10),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: content,
    );
  }

  Widget _buildMapCallout({
    required IconData icon,
    required Color iconColor,
    required String title,
    required String subtitle,
    bool alignRight = false,
  }) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 214),
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.96),
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.12),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        textDirection: alignRight ? TextDirection.rtl : TextDirection.ltr,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(color: iconColor, shape: BoxShape.circle),
            child: Icon(icon, color: Colors.white, size: 19),
          ),
          const SizedBox(width: 10),
          Flexible(
            child: Column(
              crossAxisAlignment: alignRight
                  ? CrossAxisAlignment.end
                  : CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontSize: 13, fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                Text(subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontSize: 11.5, color: Color(0xFF666666))),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDeliveryPartnerCard() {
    final order = _order!;
    if (order.isTakeaway || order.isPending || order.isCancelled) {
      return const SizedBox.shrink();
    }

    final driver = order.driver;
    final driverName = driver?.name.trim() ?? '';
    final driverPhone = driver?.phone.trim() ?? '';
    final driverImage = driver?.profileImage?.trim() ?? '';
    final hasDriver = driver != null && driverName.isNotEmpty;
    final assignedAt = order.driverAssignedAt ?? order.driverAcceptedAt;
    final vehicleLabel = [
      driver?.vehicleType?.trim() ?? '',
      driver?.vehicleNumber?.trim() ?? '',
    ].where((part) => part.isNotEmpty).join(' · ');

    return _premiumCard(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: const Color(0xFFFFEFE2),
              border: Border.all(color: Colors.white, width: 3),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.08),
                  blurRadius: 12,
                  offset: const Offset(0, 5),
                ),
              ],
            ),
            clipBehavior: Clip.antiAlias,
            child: hasDriver && driverImage.isNotEmpty
                ? AppCachedImage(
                    imageUrl: driverImage,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) =>
                        Icon(Icons.person, color: _primary, size: 32),
                  )
                : Icon(
                    hasDriver ? Icons.person : Icons.delivery_dining_rounded,
                    color: _primary,
                    size: 32,
                  ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  hasDriver ? driverName : 'Delivery partner',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  hasDriver
                      ? (vehicleLabel.isNotEmpty
                          ? vehicleLabel
                          : 'Assigned to this order')
                      : order.statusText,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: Color(0xFF666666),
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _buildPartnerStatusChip(order.statusText),
                    if (assignedAt != null)
                      _buildPartnerStatusChip(
                        'Assigned ${DateFormat('h:mm a').format(assignedAt)}',
                      ),
                    if (order.driverRating != null && order.driverRating! > 0)
                      _buildPartnerRatingChip(order.driverRating!),
                    if ((driver?.deliveredOrdersCount ?? 0) > 0)
                      _buildPartnerStatusChip(
                        '${driver!.deliveredOrdersCount}+ orders delivered',
                      ),
                  ],
                ),
              ],
            ),
          ),
          if (hasDriver) ...[
            const SizedBox(width: 12),
            _buildPartnerAction(
              icon: Icons.chat_bubble_outline_rounded,
              label: 'Chat',
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => OrderChatScreen(order: order),
                ),
              ),
            ),
          ],
          if (driverPhone.isNotEmpty) ...[
            const SizedBox(width: 12),
            _buildPartnerAction(
              icon: Icons.call,
              label: 'Call',
              onTap: () => _launchPhone(
                driverPhone,
                'Driver phone not available',
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildPartnerStatusChip(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF8EE),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          fontSize: 11.5,
          color: Color(0xFF179C43),
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  Widget _buildPartnerRatingChip(int rating) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFFEAF8EE),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.star_rounded, size: 14, color: Color(0xFF179C43)),
          const SizedBox(width: 2),
          Text(
            '$rating/5',
            style: const TextStyle(
              fontSize: 11.5,
              color: Color(0xFF179C43),
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPartnerAction(
      {required IconData icon,
      required String label,
      required VoidCallback onTap}) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: const Color(0xFFE8E8E8)),
              color: Colors.white,
            ),
            child: Icon(icon, color: Colors.black, size: 22),
          ),
          const SizedBox(height: 6),
          Text(label,
              style:
                  const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _buildPremiumOrderSummaryCard() {
    final order = _order!;
    return _premiumCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('ITEMS IN THIS ORDER (${order.items.length})',
              style: const TextStyle(
                  fontSize: 11.5,
                  letterSpacing: 0.8,
                  color: Color(0xFF666666),
                  fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          for (var i = 0; i < order.items.length; i++) ...[
            if (i > 0) const SizedBox(height: 14),
            _buildOrderItemRow(order.items[i]),
          ],
          const SizedBox(height: 18),
          const _DashedDivider(),
          const SizedBox(height: 16),
          const Text('BILL DETAILS',
              style: TextStyle(
                  fontSize: 11.5,
                  letterSpacing: 0.8,
                  color: Color(0xFF666666),
                  fontWeight: FontWeight.w700)),
          const SizedBox(height: 14),
          _buildOrderSummaryRow(
              'Item Total', formatCurrency(context, order.subtotal)),
          if (!order.isTakeaway || order.deliveryFee > 0)
            _buildOrderSummaryRow(
                'Delivery Fee', formatCurrency(context, order.deliveryFee)),
          if (order.platformFee > 0)
            _buildOrderSummaryRow(
                'Platform Fee', formatCurrency(context, order.platformFee)),
          if (order.tax > 0)
            _buildOrderSummaryRow(
                'Tax & Charges', formatCurrency(context, order.tax)),
          if (order.discount > 0)
            _buildOrderSummaryRow(
                'Discount', '-${formatCurrency(context, order.discount)}'),
          if ((order.tip ?? 0) > 0)
            _buildOrderSummaryRow(
                'Delivery Tip', formatCurrency(context, order.tip!)),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 8),
            child: Divider(color: Color(0xFFE8E8E8), height: 1),
          ),
          _buildOrderSummaryRow(
              'Total Paid', formatCurrency(context, order.total),
              isTotal: true),
        ],
      ),
    );
  }

  Widget _buildOrderItemRow(OrderItem item) {
    return Row(
      children: [
        Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            color: const Color(0xFFFFEFE2),
            borderRadius: BorderRadius.circular(8),
          ),
          clipBehavior: Clip.antiAlias,
          child: item.imageUrl.trim().isNotEmpty
              ? AppCachedImage(
                  imageUrl: item.imageUrl,
                  width: 46,
                  height: 46,
                  fit: BoxFit.cover,
                  errorWidget: Icon(Icons.fastfood, color: _primary, size: 22),
                )
              : Icon(Icons.fastfood, color: _primary, size: 22),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(item.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 13.5, fontWeight: FontWeight.w800)),
              const SizedBox(height: 3),
              Text('Qty ${item.quantity}',
                  style: const TextStyle(
                      fontSize: 11.5, color: Color(0xFF666666))),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Text(formatCurrency(context, item.totalPrice),
            style:
                const TextStyle(fontSize: 13, fontWeight: FontWeight.w800)),
      ],
    );
  }

  Widget _buildDeliveryAddressCard() {
    final order = _order!;
    final isTakeaway = order.isTakeaway;
    final address = (isTakeaway
            ? (order.restaurant?.address ?? 'Pickup location unavailable')
            : order.deliveryAddress)
        .trim();
    final phone =
        (isTakeaway ? order.restaurant?.phone : order.customerPhone)?.trim() ??
            '';

    const addressAccent = Color(0xFF16A34A);

    return _premiumCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: addressAccent.withOpacity(0.1),
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(Icons.location_on_rounded,
                color: addressAccent, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: addressAccent.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    isTakeaway ? 'PICKUP ADDRESS' : 'DELIVERY ADDRESS',
                    style: const TextStyle(
                      fontSize: 10.5,
                      letterSpacing: 0.6,
                      color: addressAccent,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  isTakeaway
                      ? (order.restaurant?.name ?? 'Store')
                      : order.customerName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  address.isEmpty ? 'Address details unavailable' : address,
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: Color(0xFF666666),
                    height: 1.35,
                  ),
                ),
                if (phone.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      const Icon(
                        Icons.phone_outlined,
                        color: Color(0xFF666666),
                        size: 16,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          phone,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12.5,
                            color: Color(0xFF333333),
                            fontWeight: FontWeight.w700,
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
    );
  }

  Widget _buildSafetyStrip() {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFEDEDED)),
      ),
      child: InkWell(
        onTap: () => _openSupport(),
        child: Row(
          children: [
            Icon(Icons.shield_outlined, color: _primary, size: 24),
            const SizedBox(width: 12),
            const Expanded(
                child: Text('Your safety is our priority. Tap to know more.',
                    style: TextStyle(
                        fontSize: 12.5,
                        color: Color(0xFF555555),
                        fontWeight: FontWeight.w600))),
            const Icon(Icons.chevron_right, color: Color(0xFF777777)),
          ],
        ),
      ),
    );
  }

  String _deliveryShortLabel() {
    final value = _order?.deliveryAddress.trim() ?? '';
    if (value.isEmpty) return 'Delivery address';
    final parts = value
        .split(',')
        .map((part) => part.trim())
        .where((part) => part.isNotEmpty)
        .toList();
    return parts.length > 1 ? parts[1] : parts.first;
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return WillPopScope(
        onWillPop: _onWillPop,
        child: const Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(child: AppDetailSkeleton(cardCount: 4)),
        ),
      );
    }

    if (_errorMessage != null || _order == null) {
      return WillPopScope(
        onWillPop: _onWillPop,
        child: Scaffold(
          appBar: AppBar(
            leading: IconButton(
              icon: const Icon(Icons.arrow_back_rounded),
              onPressed: _handleBack,
            ),
            title: const Text('Order Tracking'),
            backgroundColor: Colors.white,
            elevation: 0,
          ),
          body: Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.error_outline,
                      size: 72, color: Colors.red.shade300),
                  const SizedBox(height: 16),
                  Text(_errorMessage ?? 'Order not found',
                      style: const TextStyle(fontSize: 16),
                      textAlign: TextAlign.center),
                  const SizedBox(height: 24),
                  ElevatedButton.icon(
                    onPressed: () {
                      setState(() {
                        _isLoading = true;
                        _errorMessage = null;
                      });
                      _loadOrderDetails();
                    },
                    icon: const Icon(Icons.refresh),
                    label: const Text('Try Again'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _primary,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }

    final order = _order!;
    return WillPopScope(
      onWillPop: _onWillPop,
      child: AnnotatedRegion<SystemUiOverlayStyle>(
        value: SystemUiOverlayStyle.light,
        child: Scaffold(
          backgroundColor: const Color(0xFFF7F7F7),
          body: Stack(
            children: [
              // Full-screen interactive map, or a solid backdrop for
              // cancelled orders where the map is not shown.
              if (!order.isCancelled)
                _buildPremiumTrackingMap(fullScreen: true)
              else
                Positioned.fill(child: Container(color: _bannerColor)),
              Positioned(
                left: 12,
                right: 12,
                top: MediaQuery.paddingOf(context).top + 8,
                child: _buildFloatingStatusBar(),
              ),
              DraggableScrollableSheet(
                controller: _sheetController,
                initialChildSize: 0.42,
                minChildSize: 0.16,
                maxChildSize: 0.9,
                snap: true,
                snapSizes: const [0.16, 0.42, 0.9],
                builder: (context, scrollController) {
                  return Container(
                    decoration: const BoxDecoration(
                      color: Color(0xFFF7F7F7),
                      borderRadius:
                          BorderRadius.vertical(top: Radius.circular(24)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black26,
                          blurRadius: 20,
                          offset: Offset(0, -6),
                        ),
                      ],
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: SafeArea(
                      top: false,
                      child: ListView(
                        controller: scrollController,
                        physics: const BouncingScrollPhysics(),
                        padding: EdgeInsets.zero,
                        children: [
                          Center(
                            child: Container(
                              width: 42,
                              height: 5,
                              margin: const EdgeInsets.symmetric(
                                vertical: 10,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFFDCE1EA),
                                borderRadius: BorderRadius.circular(999),
                              ),
                            ),
                          ),
                          _buildOrderSummaryLink(),
                          _buildHeroStatusCard(),
                          if (order.isCancelled)
                            _buildCancelledStateCard()
                          else ...[
                            _buildCancellationWindowCard(),
                            _buildPaymentStatusCard(),
                            _buildDeliveryPartnerCard(),
                          ],
                          if (order.isCancelled) ...[
                            _buildCancelledTimelineCard(),
                            const SizedBox(height: 16),
                            KeyedSubtree(
                              key: _orderSummaryKey,
                              child: _buildPremiumOrderSummaryCard(),
                            ),
                            _buildDeliveryAddressCard(),
                            _buildSafetyStrip(),
                            const SizedBox(height: 28),
                          ] else ...[
                            KeyedSubtree(
                              key: _orderSummaryKey,
                              child: _buildPremiumOrderSummaryCard(),
                            ),
                            _buildDeliveryAddressCard(),
                            _buildDeliveryOtpCard(),
                            _buildSafetyStrip(),
                            const SizedBox(height: 28),
                          ],
                        ],
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildOrderSummaryRow(String label, String value,
      {bool isTotal = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label,
              style: TextStyle(
                  fontSize: isTotal ? 14 : 12.5,
                  fontWeight: isTotal ? FontWeight.bold : FontWeight.normal,
                  color: isTotal ? Colors.black : Colors.grey.shade700)),
          Text(value,
              style: TextStyle(
                  fontSize: isTotal ? 14.5 : 12.5,
                  fontWeight: isTotal ? FontWeight.bold : FontWeight.w500,
                  color: isTotal ? _primary : Colors.black87)),
        ],
      ),
    );
  }
}

class _UiTrackStep {
  final String label;
  final IconData icon;
  final DateTime? time;
  final bool isDone;

  const _UiTrackStep(this.label, this.icon, this.time, this.isDone);
}

/// A dashed horizontal rule — the divider Zomato uses between the item
/// list and the bill breakdown on its order-summary screen.
class _DashedDivider extends StatelessWidget {
  const _DashedDivider();

  static const Color _color = Color(0xFFE0E0E0);

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 1,
      child: LayoutBuilder(
        builder: (context, constraints) {
          const dashWidth = 6.0;
          const dashGap = 4.0;
          final count =
              (constraints.maxWidth / (dashWidth + dashGap)).floor();
          return Row(
            children: List.generate(
              count,
              (_) => Padding(
                padding: const EdgeInsets.only(right: dashGap),
                child: Container(width: dashWidth, height: 1, color: _color),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _DriverRouteSnap {
  final LatLng point;
  final double? bearing;
  final double distanceMeters;

  const _DriverRouteSnap(this.point, this.bearing, this.distanceMeters);
}

// Full Screen Map Widget
class FullScreenMapScreen extends StatefulWidget {
  final List<LatLng> routePoints;
  final Set<Marker> markers;
  final ValueListenable<Set<Marker>> markersListenable;
  final Set<Polyline> polylines;
  final LatLng? restaurantLocation;
  final LatLng? deliveryLocation;
  final LatLng? driverLocation;
  final Order? order;
  final bool isPickedUp;
  final String estimatedTime;
  final String distanceRemaining;
  final VoidCallback onClose;

  const FullScreenMapScreen({
    Key? key,
    required this.routePoints,
    required this.markers,
    required this.markersListenable,
    required this.polylines,
    this.restaurantLocation,
    this.deliveryLocation,
    this.driverLocation,
    this.order,
    required this.isPickedUp,
    required this.estimatedTime,
    required this.distanceRemaining,
    required this.onClose,
  }) : super(key: key);

  @override
  State<FullScreenMapScreen> createState() => _FullScreenMapScreenState();
}

class _FullScreenMapScreenState extends State<FullScreenMapScreen> {
  GoogleMapController? _mapController;
  bool _isMapReady = false;

  @override
  void dispose() {
    _mapController?.dispose();
    super.dispose();
  }

  void _onMapCreated(GoogleMapController controller) {
    _mapController = controller;
    setState(() => _isMapReady = true);
    _fitMapToRoute();
  }

  void _fitMapToRoute() {
    if (_mapController == null || !_isMapReady) return;

    if (widget.routePoints.isNotEmpty) {
      final latitudes = widget.routePoints.map((point) => point.latitude);
      final longitudes = widget.routePoints.map((point) => point.longitude);
      final minLat = latitudes.reduce((a, b) => a < b ? a : b);
      final maxLat = latitudes.reduce((a, b) => a > b ? a : b);
      final minLng = longitudes.reduce((a, b) => a < b ? a : b);
      final maxLng = longitudes.reduce((a, b) => a > b ? a : b);

      final latPadding = (maxLat - minLat) * 0.1;
      final lngPadding = (maxLng - minLng) * 0.1;

      _mapController!.animateCamera(
        CameraUpdate.newLatLngBounds(
          LatLngBounds(
            southwest: LatLng(minLat - latPadding, minLng - lngPadding),
            northeast: LatLng(maxLat + latPadding, maxLng + lngPadding),
          ),
          40,
        ),
      );
    } else if (widget.restaurantLocation != null &&
        widget.deliveryLocation != null) {
      final minLat = widget.restaurantLocation!.latitude <
              widget.deliveryLocation!.latitude
          ? widget.restaurantLocation!.latitude
          : widget.deliveryLocation!.latitude;
      final maxLat = widget.restaurantLocation!.latitude >
              widget.deliveryLocation!.latitude
          ? widget.restaurantLocation!.latitude
          : widget.deliveryLocation!.latitude;
      final minLng = widget.restaurantLocation!.longitude <
              widget.deliveryLocation!.longitude
          ? widget.restaurantLocation!.longitude
          : widget.deliveryLocation!.longitude;
      final maxLng = widget.restaurantLocation!.longitude >
              widget.deliveryLocation!.longitude
          ? widget.restaurantLocation!.longitude
          : widget.deliveryLocation!.longitude;

      final latPadding = (maxLat - minLat) * 0.1;
      final lngPadding = (maxLng - minLng) * 0.1;

      _mapController!.animateCamera(
        CameraUpdate.newLatLngBounds(
          LatLngBounds(
            southwest: LatLng(minLat - latPadding, minLng - lngPadding),
            northeast: LatLng(maxLat + latPadding, maxLng + lngPadding),
          ),
          40,
        ),
      );
    } else if (widget.restaurantLocation != null) {
      _mapController!.animateCamera(CameraUpdate.newCameraPosition(
          CameraPosition(target: widget.restaurantLocation!, zoom: 16)));
    }
  }

  void _centerOnRestaurant() {
    if (widget.restaurantLocation != null &&
        _mapController != null &&
        _isMapReady) {
      _mapController!.animateCamera(CameraUpdate.newCameraPosition(
          CameraPosition(target: widget.restaurantLocation!, zoom: 16)));
    }
  }

  void _centerOnDelivery() {
    if (widget.deliveryLocation != null &&
        _mapController != null &&
        _isMapReady) {
      _mapController!.animateCamera(CameraUpdate.newCameraPosition(
          CameraPosition(target: widget.deliveryLocation!, zoom: 16)));
    }
  }

  void _centerOnDriver() {
    if (widget.driverLocation != null &&
        _mapController != null &&
        _isMapReady) {
      _mapController!.animateCamera(CameraUpdate.newCameraPosition(
          CameraPosition(target: widget.driverLocation!, zoom: 16)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final isTakeaway = widget.order?.isTakeaway == true;

    return Scaffold(
      body: Stack(
        children: [
          ValueListenableBuilder<Set<Marker>>(
            valueListenable: widget.markersListenable,
            builder: (context, markers, _) => GoogleMap(
              onMapCreated: _onMapCreated,
              initialCameraPosition: CameraPosition(
                  target: widget.driverLocation ??
                      widget.restaurantLocation ??
                      const LatLng(28.6139, 77.2090),
                  zoom: 14),
              markers: markers,
              polylines: widget.polylines,
              myLocationEnabled: true,
              myLocationButtonEnabled: true,
              zoomControlsEnabled: true,
              zoomGesturesEnabled: true,
              compassEnabled: true,
              mapToolbarEnabled: true,
              padding: const EdgeInsets.fromLTRB(12, 96, 12, 150),
            ),
          ),
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.95),
                    boxShadow: [
                      BoxShadow(
                          color: Colors.black.withOpacity(0.1), blurRadius: 8)
                    ]),
                child: Row(
                  children: [
                    GestureDetector(
                      onTap: () {
                        widget.onClose();
                        Navigator.pop(context);
                      },
                      child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                              color: Colors.grey.shade100,
                              borderRadius: BorderRadius.circular(12)),
                          child: const Icon(Icons.arrow_back, size: 20)),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                        child: Text(
                            isTakeaway ? 'Pickup Tracking' : 'Live Tracking',
                            style: TextStyle(
                                fontSize: 15, fontWeight: FontWeight.w600))),
                    if (!isTakeaway)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                            color: const Color(0xFF4CAF50).withOpacity(0.1),
                            borderRadius: BorderRadius.circular(20)),
                        child: Row(children: [
                          Container(
                              width: 8,
                              height: 8,
                              decoration: const BoxDecoration(
                                  color: Color(0xFF4CAF50),
                                  shape: BoxShape.circle)),
                          const SizedBox(width: 6),
                          const Text('Live',
                              style: TextStyle(
                                  color: Color(0xFF4CAF50),
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600))
                        ]),
                      ),
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            bottom: 20,
            right: 16,
            child: Column(
              children: [
                _buildFullScreenMapButton(
                    icon: Icons.my_location, onPressed: _fitMapToRoute),
                const SizedBox(height: 12),
                if (widget.restaurantLocation != null &&
                    (!widget.isPickedUp || isTakeaway))
                  _buildFullScreenMapButton(
                      icon: Icons.restaurant, onPressed: _centerOnRestaurant),
                if (!isTakeaway) ...[
                  const SizedBox(height: 12),
                  _buildFullScreenMapButton(
                      icon: Icons.location_on, onPressed: _centerOnDelivery),
                  const SizedBox(height: 12),
                ],
                if (widget.driverLocation != null && widget.isPickedUp)
                  _buildFullScreenMapButton(
                      icon: Icons.delivery_dining, onPressed: _centerOnDriver),
              ],
            ),
          ),
          if (widget.order != null)
            Positioned(
              bottom: 20,
              left: 16,
              right: 80,
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                          color: Colors.black.withOpacity(0.15),
                          blurRadius: 12,
                          offset: const Offset(0, 4))
                    ]),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                        isTakeaway
                            ? 'Pickup Status'
                            : widget.isPickedUp
                                ? 'Delivery Partner is Coming'
                                : 'Order Status',
                        style:
                            const TextStyle(fontSize: 12, color: Colors.grey)),
                    const SizedBox(height: 4),
                    Text(
                        isTakeaway
                            ? widget.order!.statusText
                            : widget.isPickedUp
                                ? 'Arriving in ${widget.estimatedTime}'
                                : widget.order!.statusText,
                        style: const TextStyle(
                            fontSize: 12.5, fontWeight: FontWeight.w600)),
                    if (widget.isPickedUp)
                      Text(widget.distanceRemaining,
                          style: TextStyle(
                              fontSize: 12, color: Colors.grey.shade600)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildFullScreenMapButton(
      {required IconData icon, required VoidCallback onPressed}) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(28),
      elevation: 4,
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(28),
        child: Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(shape: BoxShape.circle),
            child: Icon(
              icon,
              color: Theme.of(context).colorScheme.primary,
              size: 24,
            )),
      ),
    );
  }
}
