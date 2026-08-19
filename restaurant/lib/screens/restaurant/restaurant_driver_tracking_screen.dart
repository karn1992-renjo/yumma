import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../services/directions_service.dart';
import '../../theme/foodflow_theme.dart';

class RestaurantDriverTrackingScreen extends StatefulWidget {
  const RestaurantDriverTrackingScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
  });

  final int orderId;
  final Map<String, dynamic>? initialOrder;

  @override
  State<RestaurantDriverTrackingScreen> createState() =>
      _RestaurantDriverTrackingScreenState();
}

class _RestaurantDriverTrackingScreenState
    extends State<RestaurantDriverTrackingScreen> {
  final ApiService _api = ApiService();
  final Completer<GoogleMapController> _mapController = Completer();
  Timer? _refreshTimer;
  Map<String, dynamic>? _order;
  List<LatLng> _route = const [];
  LatLng? _lastRoutedDriverLocation;
  bool _isLoading = true;
  bool _isRefreshing = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _order = widget.initialOrder;
    _refresh();
    _refreshTimer = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _refresh(),
    );
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _refresh() async {
    if (_isRefreshing) return;
    _isRefreshing = true;
    try {
      final response = await _api.get(
        ApiConstants.restaurantOrderDetails(widget.orderId),
      );
      final raw = response is Map ? response['data'] : null;
      if (raw is! Map) {
        throw const FormatException('Order tracking data is unavailable.');
      }

      final order = Map<String, dynamic>.from(raw);
      final driverLocation = _driverLocation(order);
      final restaurantLocation = _restaurantLocation(order);
      final arrived = _hasArrived(order);

      var route = _route;
      if (!arrived &&
          driverLocation != null &&
          restaurantLocation != null &&
          _locationChanged(driverLocation)) {
        route = await DirectionsService.fetchRoutePoints(
          driverLocation,
          restaurantLocation,
        );
        _lastRoutedDriverLocation = driverLocation;
      }

      if (!mounted) return;
      setState(() {
        _order = order;
        _route = route;
        _error = null;
        _isLoading = false;
      });
      await _fitMap(driverLocation, restaurantLocation);

      if (arrived) {
        _refreshTimer?.cancel();
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is ApiException
            ? error.message
            : 'Live partner location could not be loaded.';
        _isLoading = false;
      });
    } finally {
      _isRefreshing = false;
    }
  }

  bool _locationChanged(LatLng location) {
    final previous = _lastRoutedDriverLocation;
    if (previous == null) return true;
    return (previous.latitude - location.latitude).abs() > 0.00005 ||
        (previous.longitude - location.longitude).abs() > 0.00005;
  }

  Future<void> _fitMap(LatLng? driver, LatLng? restaurant) async {
    if (!_mapController.isCompleted) return;
    final controller = await _mapController.future;
    if (driver != null && restaurant != null) {
      final southwest = LatLng(
        driver.latitude < restaurant.latitude
            ? driver.latitude
            : restaurant.latitude,
        driver.longitude < restaurant.longitude
            ? driver.longitude
            : restaurant.longitude,
      );
      final northeast = LatLng(
        driver.latitude > restaurant.latitude
            ? driver.latitude
            : restaurant.latitude,
        driver.longitude > restaurant.longitude
            ? driver.longitude
            : restaurant.longitude,
      );
      if (southwest == northeast) {
        await controller.animateCamera(
          CameraUpdate.newLatLngZoom(driver, 16),
        );
      } else {
        await controller.animateCamera(
          CameraUpdate.newLatLngBounds(
            LatLngBounds(southwest: southwest, northeast: northeast),
            64,
          ),
        );
      }
      return;
    }

    final available = driver ?? restaurant;
    if (available != null) {
      await controller.animateCamera(
        CameraUpdate.newLatLngZoom(available, 16),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = _order ?? const <String, dynamic>{};
    final driver = _map(order['driver']);
    final driverLocation = _driverLocation(order);
    final restaurantLocation = _restaurantLocation(order);
    final arrived = _hasArrived(order);
    final partnerName = _firstText([order['driver_name'], driver['name']]);
    final partnerPhone = _firstText([order['driver_phone'], driver['phone']]);
    final initialLocation = driverLocation ?? restaurantLocation;

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.white,
        elevation: 0,
        titleSpacing: 0,
        title: const Text(
          'Track Partner',
          style: TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 19,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      body: _isLoading && initialLocation == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && initialLocation == null
              ? _TrackingError(message: _error!, onRetry: _refresh)
              : Column(
                  children: [
                    Expanded(
                      child: Stack(
                        children: [
                          if (initialLocation != null)
                            GoogleMap(
                              initialCameraPosition: CameraPosition(
                                target: initialLocation,
                                zoom: 15,
                              ),
                              onMapCreated: (controller) {
                                if (!_mapController.isCompleted) {
                                  _mapController.complete(controller);
                                }
                                _fitMap(driverLocation, restaurantLocation);
                              },
                              myLocationButtonEnabled: false,
                              zoomControlsEnabled: false,
                              mapToolbarEnabled: false,
                              markers: {
                                if (driverLocation != null)
                                  Marker(
                                    markerId: const MarkerId('driver'),
                                    position: driverLocation,
                                    icon: BitmapDescriptor.defaultMarkerWithHue(
                                      BitmapDescriptor.hueGreen,
                                    ),
                                    infoWindow: InfoWindow(
                                      title: partnerName ?? 'Delivery partner',
                                    ),
                                  ),
                                if (restaurantLocation != null)
                                  Marker(
                                    markerId: const MarkerId('restaurant'),
                                    position: restaurantLocation,
                                    infoWindow: InfoWindow(
                                      title: _firstText([
                                        order['restaurant_name'],
                                        'Restaurant',
                                      ]),
                                    ),
                                  ),
                              },
                              polylines: _route.isEmpty
                                  ? const {}
                                  : {
                                      Polyline(
                                        polylineId:
                                            const PolylineId('partner_route'),
                                        points: _route,
                                        color: FoodFlowTheme.orange,
                                        width: 6,
                                      ),
                                    },
                            )
                          else
                            const _LocationUnavailable(),
                          if (_isRefreshing && !_isLoading)
                            const Positioned(
                              top: 12,
                              right: 12,
                              child: SizedBox.square(
                                dimension: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                    SafeArea(
                      top: false,
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.fromLTRB(18, 15, 18, 14),
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          border: Border(
                            top: BorderSide(color: FoodFlowTheme.line),
                          ),
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 42,
                              height: 42,
                              decoration: BoxDecoration(
                                color: FoodFlowTheme.orange.withOpacity(0.1),
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                arrived
                                    ? Icons.storefront_rounded
                                    : Icons.delivery_dining_rounded,
                                color: FoodFlowTheme.orange,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    partnerName ?? 'Delivery partner',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: FoodFlowTheme.ink,
                                      fontSize: 15,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    arrived
                                        ? 'Partner has arrived at the restaurant'
                                        : driverLocation == null
                                            ? 'Waiting for live location'
                                            : 'Live location updates automatically',
                                    style: const TextStyle(
                                      color: FoodFlowTheme.muted,
                                      fontSize: 12,
                                      fontWeight: FontWeight.w500,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (partnerPhone != null)
                              IconButton.filled(
                                tooltip: 'Call Partner',
                                onPressed: () => _call(partnerPhone),
                                style: IconButton.styleFrom(
                                  backgroundColor: FoodFlowTheme.orange,
                                  foregroundColor: Colors.white,
                                ),
                                icon: const Icon(Icons.call_rounded),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }

  Future<void> _call(String phone) async {
    final uri = Uri(scheme: 'tel', path: phone);
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Calling is not available on this device.')),
      );
    }
  }

  static bool _hasArrived(Map<String, dynamic> order) {
    if (order['driver_arrived_at_restaurant'] == true) return true;
    return const {
      'reached_pickup',
      'picked_up',
      'on_the_way',
      'delivered',
      'completed',
    }.contains(order['status']?.toString().toLowerCase());
  }

  static LatLng? _driverLocation(Map<String, dynamic> order) {
    final location = _map(order['driver_location']);
    final driver = _map(order['driver']);
    return _latLng(
      location['lat'] ?? driver['latitude'],
      location['lng'] ?? driver['longitude'],
    );
  }

  static LatLng? _restaurantLocation(Map<String, dynamic> order) {
    final location = _map(order['restaurant_location']);
    return _latLng(location['lat'], location['lng']);
  }

  static LatLng? _latLng(dynamic latitude, dynamic longitude) {
    final lat = latitude is num
        ? latitude.toDouble()
        : double.tryParse(latitude?.toString() ?? '');
    final lng = longitude is num
        ? longitude.toDouble()
        : double.tryParse(longitude?.toString() ?? '');
    if (lat == null || lng == null) return null;
    if (lat.abs() > 90 || lng.abs() > 180) return null;
    return LatLng(lat, lng);
  }

  static Map<String, dynamic> _map(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return Map<String, dynamic>.from(value);
    return const {};
  }

  static String? _firstText(List<dynamic> values) {
    for (final value in values) {
      final text = value?.toString().trim() ?? '';
      if (text.isNotEmpty && text.toLowerCase() != 'null') return text;
    }
    return null;
  }
}

class _TrackingError extends StatelessWidget {
  const _TrackingError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.location_off_outlined,
              size: 50,
              color: FoodFlowTheme.muted,
            ),
            const SizedBox(height: 14),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: FoodFlowTheme.inkSoft,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

class _LocationUnavailable extends StatelessWidget {
  const _LocationUnavailable();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Padding(
        padding: EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.location_searching_rounded,
              size: 50,
              color: FoodFlowTheme.muted,
            ),
            SizedBox(height: 14),
            Text(
              'Waiting for the delivery partner location.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: FoodFlowTheme.inkSoft,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
