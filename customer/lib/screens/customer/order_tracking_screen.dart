// lib/screens/customer/order_tracking_screen.dart
import 'dart:async';
import 'dart:math';
import 'dart:typed_data';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:lottie/lottie.dart' hide Marker;
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../providers/order_provider.dart';
import '../../providers/auth_provider.dart';
import '../../services/websocket_service.dart';
import '../../services/directions_service.dart';
import '../../models/order.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/order_feedback_dialog.dart';

class OrderTrackingScreen extends StatefulWidget {
  final int orderId;

  const OrderTrackingScreen({Key? key, required this.orderId})
      : super(key: key);

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen>
    with SingleTickerProviderStateMixin {
  GoogleMapController? _mapController;
  Timer? _timer;
  int? _realtimeUserId;
  String? _realtimeHandlerId;
  BitmapDescriptor? _driverMarkerIcon;
  BitmapDescriptor? _restaurantMarkerIcon;
  BitmapDescriptor? _deliveryMarkerIcon;

  Order? _order;
  OrderProvider? _orderProvider;
  Set<Marker> _markers = {};
  Set<Polyline> _polylines = {};
  List<LatLng> _currentRoute = [];
  LatLng? _restaurantLocation;
  LatLng? _deliveryLocation;
  LatLng? _driverLocation;
  bool _isLoading = true;
  bool _isMapReady = false;
  bool _isFullScreenMap = false;
  String _estimatedTime = 'Calculating...';
  String _distanceRemaining = '';
  int _currentStep = 0;
  String? _errorMessage;
  late AnimationController _animationController;
  late Animation<double> _pulseAnimation;

  bool _isOrderPickedUp = false;
  int _pollTick = 0;
  bool _feedbackPromptShown = false;
  Color get _primary => Theme.of(context).colorScheme.primary;
  Color get _secondary => Theme.of(context).colorScheme.secondary;

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
    _pulseAnimation =
        Tween<double>(begin: 1.0, end: 1.3).animate(_animationController);

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
    _animationController.dispose();
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
      );

      if (order != null && mounted) {
        _applyServerDistance(order);
        final restaurantLocation = _getRestaurantLocation(order);
        final deliveryLocation = _getDeliveryLocation(order);

        final bool pickedUp =
            !order.isTakeaway && (order.isPickedUp || order.isOnTheWay);

        if (order.isTakeaway) {
          _driverLocation = null;
          _currentRoute = [];
          _isOrderPickedUp = false;
          _distanceRemaining = 'Pickup at restaurant';
        } else if (pickedUp &&
            restaurantLocation != null &&
            deliveryLocation != null) {
          _driverLocation = _calculateDriverPosition(
              deliveryLocation, restaurantLocation,
              isPickedUp: true);
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
          _driverLocation = restaurantLocation;
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

        setState(() {
          _order = order;
          _currentStep = _getStepIndexFor(order);
          _estimatedTime = _estimatedTimeFor(order);
          _restaurantLocation = restaurantLocation;
          _deliveryLocation = deliveryLocation;
          _markers = markers;
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
    if (current!.isDelivered) {
      setState(() {
        _order = current;
        _currentStep = _getStepIndexFor(current);
      });
      _showCompletionFeedback(current);
    }
  }

  void _showCompletionFeedback(Order order) {
    if (!mounted || _feedbackPromptShown || !order.needsFeedback) return;
    _feedbackPromptShown = true;
    _timer?.cancel();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      await showOrderFeedbackDialog(context, order);
    });
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
            title: order.isTakeaway ? 'Pickup Counter' : 'Restaurant',
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

    if (!order.isTakeaway &&
        driverLocation != null &&
        (order.isOnTheWay || order.isPickedUp)) {
      markers.add(
        Marker(
          markerId: const MarkerId('driver'),
          position: driverLocation,
          icon: _driverMarkerIcon ??
              BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueAzure),
          infoWindow: InfoWindow(
            title: 'Delivery Partner',
            snippet: isPickedUp ? 'On the way to you!' : 'At restaurant',
          ),
          zIndex: 10,
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
    if (byteData != null && mounted) {
      setState(() => _driverMarkerIcon =
          BitmapDescriptor.fromBytes(byteData.buffer.asUint8List()));
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
        arguments: {'order': _order, 'openChat': true});
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
                title: 'Call Restaurant',
                subtitle: _order?.restaurant?.phone?.trim().isNotEmpty == true
                    ? _order!.restaurant!.phone
                    : 'Restaurant phone unavailable',
                onTap: () {
                  Navigator.pop(context);
                  _launchPhone(_order?.restaurant?.phone,
                      'Restaurant phone number is not available.');
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
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 42,
                      height: 4,
                      decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    isForceCancel ? 'Force cancel order' : 'Cancel order',
                    style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    isForceCancel
                        ? 'Restaurant has already accepted this order. Refund will be initiated as per the active admin refund policy.'
                        : 'You can cancel this pending order within 2 minutes of placing it.',
                    style: TextStyle(
                      fontSize: 13,
                      color: Colors.grey.shade700,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  if (!isForceCancel) ...[
                    const SizedBox(height: 14),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF7ED),
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: const Color(0xFFFED7AA)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.timer_outlined,
                              color: Color(0xFFF97316)),
                          const SizedBox(width: 10),
                          Text(
                            'Time left: ${_formatDuration(order.remainingCancellationTime)}',
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF9A3412),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
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
                            backgroundColor: isForceCancel
                                ? const Color(0xFFE11D48)
                                : _primary,
                          ),
                          child: Text(
                            isForceCancel ? 'Force Cancel' : 'Cancel Order',
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildDeliveryOtpCard() {
    final isTakeaway = _order?.isTakeaway == true;
    final hasOtp = _order?.deliveryOtp?.trim().isNotEmpty == true &&
        _order?.isDelivered == false;

    if (!hasOtp) {
      if (isTakeaway) return const SizedBox.shrink();

      return Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Colors.grey.shade200)),
        child: Row(
          children: [
            Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                    color: Colors.grey.shade100,
                    borderRadius: BorderRadius.circular(12)),
                child: Icon(Icons.lock_clock, color: Colors.grey.shade600)),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(isTakeaway ? 'Pickup OTP' : 'Delivery OTP',
                      style: const TextStyle(
                          fontSize: 14, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 3),
                  Text(
                      _order!.isDelivered
                          ? 'Order completed'
                          : 'Delivery OTP will appear when driver is nearby',
                      style:
                          TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                ],
              ),
            ),
          ],
        ),
      );
    }

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
          color: const Color(0xFFFFF3E7),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFFFD2AA))),
      child: Row(
        children: [
          Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                  color: Colors.white, borderRadius: BorderRadius.circular(14)),
              child: Icon(Icons.password, color: _primary, size: 28)),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(isTakeaway ? 'Pickup OTP' : 'Delivery OTP',
                    style: const TextStyle(
                        color: Color(0xFF9B4A00),
                        fontSize: 12,
                        fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Text(_order!.deliveryOtp!,
                        maxLines: 1,
                        style: const TextStyle(
                            color: Colors.black,
                            fontSize: 34,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 6))),
                const SizedBox(height: 4),
                Text(
                    isTakeaway
                        ? 'Share this with the restaurant staff when collecting your order.'
                        : 'Share this with the delivery partner only at delivery.',
                    style:
                        TextStyle(color: Colors.grey.shade800, fontSize: 12)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCancellationWindowCard() {
    final order = _order;
    if (order == null || !order.isPending) {
      return const SizedBox.shrink();
    }

    final isActive = order.canCancel;
    final accent = isActive ? const Color(0xFFF97316) : const Color(0xFF9CA3AF);

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isActive ? const Color(0xFFFFF7ED) : const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isActive ? const Color(0xFFFED7AA) : const Color(0xFFE5E7EB),
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(Icons.timer_outlined, color: accent),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isActive
                      ? 'Cancellation available for ${_formatDuration(order.remainingCancellationTime)}'
                      : 'Cancellation window has closed',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: isActive
                        ? const Color(0xFF9A3412)
                        : const Color(0xFF4B5563),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  isActive
                      ? 'You can cancel only within 2 minutes unless the restaurant accepts the order first.'
                      : 'Once accepted by the restaurant, you can only use force cancel with refund policy.',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCancelledStateCard() {
    final order = _order!;
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F2),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFFBCFE8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  color: Colors.white,
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
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: Color(0xFF9F1239),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      order.cancellationReason?.trim().isNotEmpty == true
                          ? order.cancellationReason!
                          : 'This order has been cancelled.',
                      style: TextStyle(
                        fontSize: 13,
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
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFF3D6D9)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Refund status',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF9F1239),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _refundStatusText(order),
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (order.refundAmount != null) ...[
                    const SizedBox(height: 6),
                    Text(
                      'Refund amount: ${formatCurrency(context, order.refundAmount!)}',
                      style: TextStyle(
                        fontSize: 13,
                        color: Colors.grey.shade700,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  const SizedBox(height: 6),
                  Text(
                    'Refund mode: ${_refundModeText(order)}',
                    style: TextStyle(
                      fontSize: 13,
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
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
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
              color: const Color(0xFF7C3AED),
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
                            ? 'Collect your order from the restaurant'
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
                      GoogleMap(
                        onMapCreated: _onMapCreated,
                        initialCameraPosition:
                            CameraPosition(target: _mapTarget!, zoom: 14),
                        markers: _markers,
                        polylines: _polylines,
                        myLocationEnabled: true,
                        myLocationButtonEnabled: true,
                        zoomControlsEnabled: true,
                        zoomGesturesEnabled: true,
                        compassEnabled: true,
                        mapToolbarEnabled: false,
                        padding: const EdgeInsets.fromLTRB(12, 12, 12, 80),
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

  Widget _buildMapButton(
      {required IconData icon, required VoidCallback onPressed}) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(28),
      elevation: 3,
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(28),
        child: Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(shape: BoxShape.circle),
            child: Icon(icon, color: _primary, size: 22)),
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
                    title: _order!.restaurant?.name ?? 'Restaurant',
                    subtitle: _order!.restaurant?.address ?? 'Pickup location'),
                if (!isTakeaway)
                  _buildRoutePointText(
                      title: 'Delivery address',
                      subtitle: _order!.deliveryAddress)
                else
                  _buildRoutePointText(
                      title: 'Pickup instruction',
                      subtitle: 'Show this order at the restaurant counter.'),
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
            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800)),
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

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return WillPopScope(
        onWillPop: _onWillPop,
        child: Scaffold(
          body: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const CircularProgressIndicator(),
                const SizedBox(height: 16),
                Text('Loading order details...',
                    style: TextStyle(color: Colors.grey.shade600))
              ],
            ),
          ),
        ),
      );
    }

    if (_errorMessage != null || _order == null) {
      return WillPopScope(
        onWillPop: _onWillPop,
        child: Scaffold(
          appBar: AppBar(
              title: const Text('Track Order'),
              backgroundColor: Colors.transparent,
              elevation: 0),
          body: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.error_outline, size: 80, color: Colors.red.shade300),
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
                          borderRadius: BorderRadius.circular(12))),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return WillPopScope(
      onWillPop: _onWillPop,
      child: Scaffold(
        body: Column(
          children: [
            Container(
              padding: EdgeInsets.fromLTRB(
                  16, MediaQuery.of(context).padding.top + 12, 16, 16),
              decoration: const BoxDecoration(color: Colors.white, boxShadow: [
                BoxShadow(
                    color: Colors.black12, blurRadius: 8, offset: Offset(0, 2))
              ]),
              child: Row(
                children: [
                  GestureDetector(
                    onTap: _handleBack,
                    child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                            color: Colors.grey.shade100,
                            borderRadius: BorderRadius.circular(12)),
                        child: const Icon(Icons.arrow_back, size: 20)),
                  ),
                  const Spacer(),
                  Text(_order!.isCancelled ? 'Order Details' : 'Track Order',
                      style: const TextStyle(
                          fontSize: 18, fontWeight: FontWeight.w600)),
                  const Spacer(),
                  GestureDetector(
                    onTap: _showHelpDialog,
                    child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                            color: Colors.grey.shade100,
                            borderRadius: BorderRadius.circular(12)),
                        child: const Icon(Icons.headset_mic, size: 20)),
                  ),
                ],
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      margin: const EdgeInsets.all(16),
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: _order!.isCancelled
                                ? const [Color(0xFFE11D48), Color(0xFFF97316)]
                                : [_primary, _secondary]),
                        borderRadius: BorderRadius.circular(24),
                        boxShadow: [
                          BoxShadow(
                              color: (_order!.isCancelled
                                      ? const Color(0xFFE11D48)
                                      : _primary)
                                  .withOpacity(0.3),
                              blurRadius: 15,
                              offset: const Offset(0, 5))
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('Order #${_order!.orderNumber}',
                                  style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 14,
                                      fontWeight: FontWeight.w500)),
                              Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 12, vertical: 6),
                                  decoration: BoxDecoration(
                                      color: Colors.white.withOpacity(0.2),
                                      borderRadius: BorderRadius.circular(20)),
                                  child: Text(_order!.statusText,
                                      style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 12,
                                          fontWeight: FontWeight.w600))),
                            ],
                          ),
                          const SizedBox(height: 16),
                          Text(
                              _order!.isCancelled
                                  ? 'Cancellation Update'
                                  : 'Your Order Status',
                              style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 24,
                                  fontWeight: FontWeight.bold)),
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              Icon(
                                  _order!.isCancelled
                                      ? Icons.info_outline
                                      : Icons.timer,
                                  color: Colors.white,
                                  size: 18),
                              const SizedBox(width: 6),
                              Text(
                                  _order!.isCancelled
                                      ? _refundStatusText(_order!)
                                      : _order!.isTakeaway
                                          ? 'Estimated pickup: $_estimatedTime'
                                          : 'Estimated delivery: $_estimatedTime',
                                  style: TextStyle(
                                      color: Colors.white.withOpacity(0.9),
                                      fontSize: 14)),
                            ],
                          ),
                        ],
                      ),
                    ),
                    if (_order!.isCancelled)
                      _buildCancelledStateCard()
                    else ...[
                      _buildCancellationWindowCard(),
                      Container(
                        margin: const EdgeInsets.all(16),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            boxShadow: [
                              BoxShadow(
                                  color: Colors.black.withOpacity(0.05),
                                  blurRadius: 10,
                                  offset: const Offset(0, 2))
                            ]),
                        child: Row(
                          children: [
                            Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                    color: _primary.withOpacity(0.1),
                                    borderRadius: BorderRadius.circular(14)),
                                child: Icon(
                                    _order!.isTakeaway
                                        ? Icons.shopping_bag
                                        : Icons.delivery_dining,
                                    color: _primary,
                                    size: 28)),
                            const SizedBox(width: 16),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                      _order!.isTakeaway
                                          ? 'Takeaway Pickup'
                                          : _order!.isPending
                                              ? 'Restaurant Confirmation'
                                              : 'Delivery Partner',
                                      style: const TextStyle(
                                          fontSize: 12, color: Colors.grey)),
                                  const SizedBox(height: 4),
                                  Text(
                                      _order!.isTakeaway
                                          ? (_order!.isReadyForPickup
                                              ? 'Ready to collect from restaurant'
                                              : 'Collect from the restaurant counter')
                                          : _order!.isPending
                                              ? 'Waiting for restaurant acceptance'
                                              : (_order!.driver?.name ??
                                                  'Assigning driver...'),
                                      style: const TextStyle(
                                          fontSize: 16,
                                          fontWeight: FontWeight.w600)),
                                  if (_isOrderPickedUp &&
                                      !_order!.isDelivered) ...[
                                    const SizedBox(height: 4),
                                    InkWell(
                                      onTap: () => _launchPhone(
                                          _order!.driver?.phone,
                                          'Driver phone not available'),
                                      child: Row(children: [
                                        const Icon(Icons.phone,
                                            size: 14, color: Colors.green),
                                        const SizedBox(width: 4),
                                        Text('Contact Driver',
                                            style: TextStyle(
                                                fontSize: 12,
                                                color: Colors.green.shade600))
                                      ]),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            if (_isOrderPickedUp)
                              AnimatedBuilder(
                                animation: _pulseAnimation,
                                builder: (context, child) => Transform.scale(
                                  scale: _pulseAnimation.value,
                                  child: Container(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 12, vertical: 6),
                                      decoration: BoxDecoration(
                                          color: Colors.green.withOpacity(0.1),
                                          borderRadius:
                                              BorderRadius.circular(20)),
                                      child: Row(children: [
                                        Container(
                                            width: 8,
                                            height: 8,
                                            decoration: const BoxDecoration(
                                                shape: BoxShape.circle,
                                                color: Colors.green)),
                                        const SizedBox(width: 6),
                                        const Text('Live',
                                            style: TextStyle(
                                                color: Colors.green,
                                                fontSize: 12,
                                                fontWeight: FontWeight.w600))
                                      ])),
                                ),
                              ),
                          ],
                        ),
                      ),
                      _buildDeliveryOtpCard(),
                      _buildTrackingMapCard(),
                    ],
                    if (_order!.isCancelled)
                      _buildCancelledTimelineCard()
                    else
                      Container(
                        margin: const EdgeInsets.symmetric(horizontal: 16),
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            boxShadow: [
                              BoxShadow(
                                  color: Colors.black.withOpacity(0.05),
                                  blurRadius: 10,
                                  offset: const Offset(0, 2))
                            ]),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Order Timeline',
                                style: TextStyle(
                                    fontSize: 18, fontWeight: FontWeight.bold)),
                            const SizedBox(height: 20),
                            ...List.generate(_visibleOrderSteps.length,
                                (index) {
                              final step = _visibleOrderSteps[index];
                              final isCompleted = index <= _currentStep;
                              final isCurrent = index == _currentStep;

                              return Padding(
                                padding: const EdgeInsets.only(bottom: 16),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Column(
                                      children: [
                                        Container(
                                          width: 32,
                                          height: 32,
                                          decoration: BoxDecoration(
                                            shape: BoxShape.circle,
                                            color: isCompleted
                                                ? _primary
                                                : Colors.grey.shade200,
                                            border: isCurrent
                                                ? Border.all(
                                                    color: _primary, width: 2)
                                                : null,
                                          ),
                                          child: isCurrent &&
                                                  (step['status'] ==
                                                          'preparing' ||
                                                      step['status'] ==
                                                          'on_the_way')
                                              ? Lottie.asset(
                                                  step['status'] == 'preparing'
                                                      ? 'assets/animations/packaging.json'
                                                      : 'assets/animations/delivery_boy.json',
                                                  width: 40,
                                                  height: 40,
                                                  fit: BoxFit.contain,
                                                )
                                              : Icon(
                                                  step['icon'] as IconData,
                                                  color: isCompleted
                                                      ? Colors.white
                                                      : Colors.grey.shade500,
                                                  size: 16,
                                                ),
                                        ),
                                        if (index <
                                            _visibleOrderSteps.length - 1)
                                          Container(
                                              width: 2,
                                              height: 40,
                                              color: isCompleted
                                                  ? _primary
                                                  : Colors.grey.shade200),
                                      ],
                                    ),
                                    const SizedBox(width: 16),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(step['label'] as String,
                                              style: TextStyle(
                                                  fontSize: 14,
                                                  fontWeight: isCurrent
                                                      ? FontWeight.bold
                                                      : FontWeight.w500,
                                                  color: isCompleted
                                                      ? _primary
                                                      : Colors.grey.shade600)),
                                          if (isCurrent &&
                                              (_isOrderPickedUp ||
                                                  _order!.isTakeaway))
                                            Padding(
                                                padding:
                                                    EdgeInsets.only(top: 4),
                                                child: Text(
                                                    _order!.isTakeaway
                                                        ? 'Please collect your order from the restaurant counter.'
                                                        : 'Your delivery partner is on the way!',
                                                    style: const TextStyle(
                                                        fontSize: 12,
                                                        color: Colors.grey))),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            }),
                          ],
                        ),
                      ),
                    Container(
                      margin: const EdgeInsets.all(16),
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                                color: Colors.black.withOpacity(0.05),
                                blurRadius: 10,
                                offset: const Offset(0, 2))
                          ]),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(children: [
                            Icon(Icons.restaurant, size: 20, color: _primary),
                            const SizedBox(width: 12),
                            const Text('Restaurant Details',
                                style: TextStyle(
                                    fontSize: 15, fontWeight: FontWeight.bold))
                          ]),
                          const SizedBox(height: 16),
                          Text(_order!.restaurant?.name ?? 'Restaurant',
                              style: const TextStyle(
                                  fontSize: 15, fontWeight: FontWeight.w600)),
                          const SizedBox(height: 4),
                          Text(_order!.restaurant?.address ?? '',
                              style: TextStyle(
                                  fontSize: 12, color: Colors.grey.shade600)),
                          if (_order!.scheduledTime != null) ...[
                            const SizedBox(height: 12),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 10,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFFEFF7FF),
                                borderRadius: BorderRadius.circular(14),
                              ),
                              child: Row(
                                children: [
                                  const Icon(
                                    Icons.schedule_outlined,
                                    size: 16,
                                    color: Color(0xFF2F68D8),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'Scheduled for ${DateFormat('EEE, d MMM - hh:mm a').format(_order!.scheduledTime!)}',
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: FoodFlowTheme.ink,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                          const Divider(height: 24),
                          Row(children: [
                            Icon(Icons.location_on, size: 20, color: _primary),
                            const SizedBox(width: 12),
                            Text(
                                _order!.isTakeaway
                                    ? 'Pickup Address'
                                    : 'Delivery Address',
                                style: const TextStyle(
                                    fontSize: 15, fontWeight: FontWeight.bold))
                          ]),
                          const SizedBox(height: 16),
                          Text(
                              _order!.isTakeaway
                                  ? (_order!.restaurant?.address ??
                                      'Pickup location unavailable')
                                  : _order!.deliveryAddress,
                              style: const TextStyle(fontSize: 13)),
                          const Divider(height: 24),
                          Row(children: [
                            Icon(Icons.receipt, size: 20, color: _primary),
                            const SizedBox(width: 12),
                            const Text('Order Summary',
                                style: TextStyle(
                                    fontSize: 15, fontWeight: FontWeight.bold))
                          ]),
                          const SizedBox(height: 16),
                          ..._order!.items.map((item) => Padding(
                                padding: const EdgeInsets.only(bottom: 12),
                                child: Row(
                                    mainAxisAlignment:
                                        MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text('${item.quantity}x ${item.name}',
                                          style: const TextStyle(fontSize: 13)),
                                      Text(
                                          formatCurrency(
                                              context, item.totalPrice),
                                          style: const TextStyle(
                                              fontSize: 13,
                                              fontWeight: FontWeight.w500))
                                    ]),
                              )),
                          const Divider(height: 24),
                          _buildOrderSummaryRow('Item Total',
                              formatCurrency(context, _order!.subtotal)),
                          if (!_order!.isTakeaway || _order!.deliveryFee > 0)
                            _buildOrderSummaryRow('Delivery Fee',
                                formatCurrency(context, _order!.deliveryFee)),
                          if (_order!.platformFee > 0)
                            _buildOrderSummaryRow('Platform Fee',
                                formatCurrency(context, _order!.platformFee)),
                          _buildOrderSummaryRow('Tax & Charges',
                              formatCurrency(context, _order!.tax)),
                          if (_order!.discount > 0)
                            _buildOrderSummaryRow(
                              'Discount',
                              '-${formatCurrency(context, _order!.discount)}',
                            ),
                          const SizedBox(height: 8),
                          _buildOrderSummaryRow('Total Amount',
                              formatCurrency(context, _order!.total),
                              isTotal: true),
                        ],
                      ),
                    ),
                    const SizedBox(height: 80),
                  ],
                ),
              ),
            ),
            if (!_order!.isDelivered)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(color: Colors.white, boxShadow: [
                  BoxShadow(
                      color: Colors.black.withOpacity(0.1),
                      blurRadius: 10,
                      offset: const Offset(0, -5))
                ]),
                child: SafeArea(
                  child: Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _showHelpDialog,
                          icon: const Icon(Icons.support_agent),
                          label: const Text('Need Help?'),
                          style: OutlinedButton.styleFrom(
                              side: BorderSide(color: _primary),
                              shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12)),
                              padding:
                                  const EdgeInsets.symmetric(vertical: 14)),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: _order!.isCancelled
                              ? () =>
                                  Navigator.pushNamed(context, '/customer/home')
                              : _order!.canCancel
                                  ? () => _showCancelOrderSheet(
                                      isForceCancel: false)
                                  : _order!.canForceCancel
                                      ? () => _showCancelOrderSheet(
                                          isForceCancel: true)
                                      : () => Navigator.pushNamed(
                                          context, '/customer/home'),
                          icon: Icon(
                            _order!.isCancelled
                                ? Icons.restaurant
                                : (_order!.canCancel || _order!.canForceCancel)
                                    ? Icons.cancel_outlined
                                    : Icons.restaurant,
                          ),
                          label: Text(
                            _order!.isCancelled
                                ? 'Order More'
                                : _order!.canCancel
                                    ? 'Cancel Now'
                                    : _order!.canForceCancel
                                        ? 'Force Cancel'
                                        : 'Order More',
                          ),
                          style: ElevatedButton.styleFrom(
                              backgroundColor: _order!.canForceCancel
                                  ? const Color(0xFFE11D48)
                                  : _primary,
                              shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12)),
                              padding:
                                  const EdgeInsets.symmetric(vertical: 14)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
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
                  fontSize: isTotal ? 15 : 14,
                  fontWeight: isTotal ? FontWeight.bold : FontWeight.normal,
                  color: isTotal ? Colors.black : Colors.grey.shade700)),
          Text(value,
              style: TextStyle(
                  fontSize: isTotal ? 16 : 14,
                  fontWeight: isTotal ? FontWeight.bold : FontWeight.w500,
                  color: isTotal ? _primary : Colors.black87)),
        ],
      ),
    );
  }
}

// Full Screen Map Widget
class FullScreenMapScreen extends StatefulWidget {
  final List<LatLng> routePoints;
  final Set<Marker> markers;
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
          GoogleMap(
            onMapCreated: _onMapCreated,
            initialCameraPosition: CameraPosition(
                target: widget.driverLocation ??
                    widget.restaurantLocation ??
                    const LatLng(28.6139, 77.2090),
                zoom: 14),
            markers: widget.markers,
            polylines: widget.polylines,
            myLocationEnabled: true,
            myLocationButtonEnabled: true,
            zoomControlsEnabled: true,
            zoomGesturesEnabled: true,
            compassEnabled: true,
            mapToolbarEnabled: true,
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
                                fontSize: 18, fontWeight: FontWeight.w600))),
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
                            fontSize: 14, fontWeight: FontWeight.w600)),
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
