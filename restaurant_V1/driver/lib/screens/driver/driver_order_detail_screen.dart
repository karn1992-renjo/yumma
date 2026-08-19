// lib/screens/driver/driver_order_detail_screen.dart
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../services/directions_service.dart';
import '../../services/location_service.dart';
import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';

class DriverOrderDetailScreen extends StatefulWidget {
  final int orderId;

  const DriverOrderDetailScreen({super.key, required this.orderId});

  @override
  State<DriverOrderDetailScreen> createState() =>
      _DriverOrderDetailScreenState();
}

class _DriverOrderDetailScreenState extends State<DriverOrderDetailScreen> {
  final ApiService _api = ApiService();

  Order? _order;
  String? _loadError;
  bool _isLoading = true;
  bool _isUpdating = false;
  late GoogleMapController _mapController;
  LatLng? _restaurantLocation;
  LatLng? _deliveryLocation;
  List<LatLng> _routePoints = [];
  Set<Polyline> _polylines = {};
  final TextEditingController _otpController = TextEditingController();
  double _swipeProgress = 0;
  String _selectedPaymentMode = 'cash';
  bool _cashCollected = false;

  @override
  void initState() {
    super.initState();
    _loadOrder();
  }

  @override
  void dispose() {
    _otpController.dispose();
    _mapController.dispose();
    super.dispose();
  }

  Future<void> _loadOrder() async {
    setState(() => _isLoading = true);

    try {
      final response =
          await _api.get('${ApiConstants.driverOrders}/${widget.orderId}');
      if (response['success'] == true) {
        final order = Order.fromJson(response['data']);
        LatLng? restaurantLocation;
        LatLng? deliveryLocation;

        if (order.restaurant != null) {
          restaurantLocation = LatLng(
            order.restaurant!.latitude,
            order.restaurant!.longitude,
          );
        }

        if (order.deliveryLat != null && order.deliveryLng != null) {
          deliveryLocation = LatLng(
            order.deliveryLat!,
            order.deliveryLng!,
          );
        }

        final routePoints = await _loadRoutePoints(
          restaurantLocation,
          deliveryLocation,
        );

        setState(() {
          _order = order;
          _loadError = null;
          _selectedPaymentMode = order.paymentStatus == 'success'
              ? 'online'
              : _paymentModeFor(order);
          _cashCollected = false;
          _restaurantLocation = restaurantLocation;
          _deliveryLocation = deliveryLocation;
          _routePoints = routePoints;
          _polylines = _buildDriverRoutePolylines();
        });
      }
    } catch (e) {
      debugPrint('Load order error: $e');
      if (mounted) {
        setState(() => _loadError = _cleanApiError(e));
      }
    }

    setState(() => _isLoading = false);
  }

  Future<void> _updateOrderStatus(String status, {String? reason}) async {
    setState(() => _isUpdating = true);

    try {
      final response = await _api.post(
        ApiConstants.updateOrderStatus(widget.orderId),
        data: {
          'status': status,
          if (reason != null) 'reason': reason,
        },
      );

      if (response['success'] == true) {
        await _loadOrder();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
                content: Text(
                    'Order status updated to ${status.replaceAll('_', ' ')}')),
          );
        }
      }
    } catch (e) {
      debugPrint('Update status error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update status: $e')),
        );
      }
    }

    setState(() => _isUpdating = false);
  }

  Future<void> _acceptAssignment() async {
    setState(() => _isUpdating = true);
    try {
      final response =
          await _api.post(ApiConstants.driverAcceptOrder(widget.orderId));
      if (response['success'] == true) {
        await _loadOrder();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Delivery accepted')),
          );
        }
      }
    } catch (e) {
      debugPrint('Accept delivery error: $e');
      if (mounted) {
        final message = _cleanApiError(e);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(message)),
        );
        if (message.toLowerCase().contains('no longer available')) {
          Navigator.maybePop(context);
        }
      }
    }
    if (mounted) setState(() => _isUpdating = false);
  }

  Future<void> _rejectAssignment() async {
    setState(() => _isUpdating = true);
    try {
      final response = await _api.post(
        ApiConstants.driverRejectOrder(widget.orderId),
        data: {'reason': 'Rejected by driver'},
      );
      if (response['success'] == true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Delivery rejected')),
          );
          Navigator.pop(context);
        }
      }
    } catch (e) {
      debugPrint('Reject delivery error: $e');
      if (mounted) {
        final message = _cleanApiError(e);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(message)),
        );
        if (message.toLowerCase().contains('no longer available')) {
          Navigator.maybePop(context);
        }
      }
    }
    if (mounted) setState(() => _isUpdating = false);
  }

  String _cleanApiError(Object error) {
    final message = error.toString().trim();
    if (message.startsWith('Exception: ')) {
      return message.substring('Exception: '.length);
    }
    return message.isEmpty ? 'Unable to update delivery' : message;
  }

  Future<void> _verifyAndComplete() async {
    if (_otpController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter OTP')),
      );
      return;
    }

    if (_selectedPaymentMode == 'cash' && !_cashCollected) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Confirm cash collection first')),
      );
      return;
    }

    setState(() => _isUpdating = true);

    try {
      final response = await _api.post(
        '${ApiConstants.verifyDeliveryOtp}/${widget.orderId}',
        data: {
          'otp': _otpController.text,
          'payment_mode': _selectedPaymentMode,
          'cash_collected': _cashCollected,
        },
      );

      if (response['success'] == true) {
        await _loadOrder();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Delivery completed successfully!')),
          );
          Navigator.pop(context);
        }
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(response['message'] ?? 'Invalid OTP')),
        );
      }
    } catch (e) {
      debugPrint('Verify OTP error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to verify OTP: $e')),
        );
      }
    }

    setState(() => _isUpdating = false);
  }

  Future<void> _resendOtp() async {
    try {
      final response = await _api
          .post('${ApiConstants.resendDeliveryOtp}/${widget.orderId}');
      if (response['success'] == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('OTP resent successfully')),
        );
      }
    } catch (e) {
      debugPrint('Resend OTP error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to resend OTP: $e')),
        );
      }
    }
  }

  bool get _isCustomerLegActive {
    final order = _order;
    return order != null &&
        (order.isPickedUp || order.isOnTheWay || order.isDelivered);
  }

  _DriverActionTarget get _activeContactTarget {
    if (_isCustomerLegActive) {
      return _DriverActionTarget(
        title: 'Customer',
        name: _order?.customerName ?? 'Customer',
        phone: _order?.customerPhone ?? '',
        address: _order?.deliveryAddress ?? '',
        location: _deliveryLocation,
        icon: Icons.person_rounded,
        color: FoodFlowTheme.success,
      );
    }

    return _DriverActionTarget(
      title: 'Restaurant',
      name: _order?.restaurant?.name ?? 'Restaurant',
      phone: _order?.restaurant?.phone ?? '',
      address: _order?.restaurant?.address ?? '',
      location: _restaurantLocation,
      icon: Icons.restaurant_rounded,
      color: FoodFlowTheme.primaryColor,
    );
  }

  _DriverActionTarget get _alternateContactTarget {
    if (_isCustomerLegActive) {
      return _DriverActionTarget(
        title: 'Restaurant',
        name: _order?.restaurant?.name ?? 'Restaurant',
        phone: _order?.restaurant?.phone ?? '',
        address: _order?.restaurant?.address ?? '',
        location: _restaurantLocation,
        icon: Icons.restaurant_rounded,
        color: FoodFlowTheme.primaryColor,
      );
    }

    return _DriverActionTarget(
      title: 'Customer',
      name: _order?.customerName ?? 'Customer',
      phone: _order?.customerPhone ?? '',
      address: _order?.deliveryAddress ?? '',
      location: _deliveryLocation,
      icon: Icons.person_rounded,
      color: FoodFlowTheme.success,
    );
  }

  void _showCallDialog() {
    final primary = _activeContactTarget;
    final secondary = _alternateContactTarget;

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => _DriverActionSheet(
        title: 'Call ${primary.title.toLowerCase()}',
        subtitle: _isCustomerLegActive
            ? 'Drop-off leg is active'
            : 'Pickup leg is active',
        primaryAction: _DriverSheetAction(
          target: primary,
          label: 'Call ${primary.title}',
          value: primary.phone.isNotEmpty ? primary.phone : 'Phone unavailable',
          icon: Icons.call_rounded,
          enabled: primary.phone.trim().isNotEmpty,
          onTap: () {
            Navigator.pop(context);
            _callPhone(primary.phone);
          },
        ),
        secondaryAction: _DriverSheetAction(
          target: secondary,
          label: 'Call ${secondary.title}',
          value:
              secondary.phone.isNotEmpty ? secondary.phone : 'Phone unavailable',
          icon: Icons.call_outlined,
          enabled: secondary.phone.trim().isNotEmpty,
          onTap: () {
            Navigator.pop(context);
            _callPhone(secondary.phone);
          },
        ),
      ),
    );
  }

  void _showNavigateDialog() {
    final primary = _activeContactTarget;
    final secondary = _alternateContactTarget;

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => _DriverActionSheet(
        title: 'Navigate to ${primary.title.toLowerCase()}',
        subtitle: _isCustomerLegActive
            ? 'Use customer drop-off location'
            : 'Use restaurant pickup location',
        primaryAction: _DriverSheetAction(
          target: primary,
          label: 'Open ${primary.title} route',
          value: primary.address.isNotEmpty
              ? primary.address
              : 'Location coordinates will be used',
          icon: Icons.navigation_rounded,
          enabled: primary.canNavigate,
          onTap: () {
            Navigator.pop(context);
            _openNavigation(primary);
          },
        ),
        secondaryAction: _DriverSheetAction(
          target: secondary,
          label: 'Open ${secondary.title} route',
          value: secondary.address.isNotEmpty
              ? secondary.address
              : 'Location coordinates will be used',
          icon: Icons.map_outlined,
          enabled: secondary.canNavigate,
          onTap: () {
            Navigator.pop(context);
            _openNavigation(secondary);
          },
        ),
      ),
    );
  }

  Future<void> _callPhone(String phone) async {
    final digits = phone.replaceAll(RegExp(r'[^\d+]'), '');
    if (digits.isEmpty) {
      _showSnack('Phone number not available');
      return;
    }

    final launched = await launchUrl(
      Uri(scheme: 'tel', path: digits),
      mode: LaunchMode.externalApplication,
    );

    if (!launched) {
      _showSnack('Could not open phone dialer');
    }
  }

  Future<void> _openNavigation(_DriverActionTarget target) async {
    if (!target.canNavigate) {
      _showSnack('${target.title} location not available');
      return;
    }

    final destination = target.location != null
        ? '${target.location!.latitude},${target.location!.longitude}'
        : Uri.encodeComponent(target.address);
    final uri = Uri.parse(
      'https://www.google.com/maps/dir/?api=1&destination=$destination&travelmode=driving',
    );

    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched) {
      _showSnack('Could not open navigation');
    }
  }

  void _showSnack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  void _onMapCreated(GoogleMapController controller) {
    _mapController = controller;
    _fitMapToRoute();
  }

  Future<List<LatLng>> _loadRoutePoints(
    LatLng? restaurantLocation,
    LatLng? deliveryLocation,
  ) async {
    if (restaurantLocation == null || deliveryLocation == null) {
      return [];
    }

    try {
      return await DirectionsService.fetchRoutePoints(
        restaurantLocation,
        deliveryLocation,
      );
    } catch (_) {
      return [];
    }
  }

  void _fitMapToRoute() {
    final points = _routePoints.isNotEmpty
        ? _routePoints
        : (_restaurantLocation != null && _deliveryLocation != null)
            ? [_restaurantLocation!, _deliveryLocation!]
            : [];

    if (points.isEmpty) {
      if (_restaurantLocation != null) {
        _mapController.animateCamera(
          CameraUpdate.newCameraPosition(
            CameraPosition(target: _restaurantLocation!, zoom: 13),
          ),
        );
      }
      return;
    }

    if (points.length == 1) {
      _mapController.animateCamera(
        CameraUpdate.newCameraPosition(
          CameraPosition(target: points[0], zoom: 13),
        ),
      );
      return;
    }

    final latitudes = points.map((point) => point.latitude).toList();
    final longitudes = points.map((point) => point.longitude).toList();
    final southwest = LatLng(
      latitudes.reduce((a, b) => a < b ? a : b),
      longitudes.reduce((a, b) => a < b ? a : b),
    );
    final northeast = LatLng(
      latitudes.reduce((a, b) => a > b ? a : b),
      longitudes.reduce((a, b) => a > b ? a : b),
    );

    _mapController.animateCamera(
      CameraUpdate.newLatLngBounds(
        LatLngBounds(southwest: southwest, northeast: northeast),
        70,
      ),
    );
  }

  double _min(double a, double b) => a < b ? a : b;
  double _max(double a, double b) => a > b ? a : b;

  String _paymentModeFor(Order order) {
    final method = order.paymentMethod.toLowerCase();
    if (method == 'cod' || method == 'cash') return 'cash';
    return 'online';
  }

  bool get _isPaymentAlreadyPaid => _order?.paymentStatus == 'success';

  String get _paymentMethodLabel {
    final method = _order?.paymentMethod.toLowerCase() ?? 'cod';
    switch (method) {
      case 'cod':
        return 'Cash on delivery';
      case 'razorpay':
        return 'Razorpay';
      case 'upi':
        return 'UPI';
      case 'card':
        return 'Card';
      default:
        return method.toUpperCase();
    }
  }

  Set<Polyline> _buildDriverRoutePolylines() {
    final points = _routePoints.isNotEmpty
        ? _routePoints
        : (_restaurantLocation != null && _deliveryLocation != null)
            ? [_restaurantLocation!, _deliveryLocation!]
            : <LatLng>[];

    if (points.isEmpty) {
      return {};
    }

    return {
      Polyline(
        polylineId: const PolylineId('driver_route_preview'),
        points: points,
        color: const Color(0xFF1E88E5),
        width: 5,
        patterns: [PatternItem.dash(18), PatternItem.gap(12)],
        startCap: Cap.roundCap,
        endCap: Cap.roundCap,
        zIndex: 1,
      ),
      Polyline(
        polylineId: const PolylineId('driver_route_highlight'),
        points: points,
        color: const Color(0xFF90CAF9),
        width: 3,
        patterns: [PatternItem.dot],
        startCap: Cap.roundCap,
        endCap: Cap.roundCap,
        zIndex: 2,
      ),
    };
  }

  IconData _getStatusIcon() {
    if (_order == null) return Icons.error;
    if (_order!.isPending) return Icons.receipt;
    if (_order!.isConfirmed) return Icons.check_circle;
    if (_order!.isPreparing) return Icons.restaurant;
    if (_order!.isReadyForPickup) return Icons.location_on;
    if (_order!.isReachedPickup) return Icons.location_on;
    if (_order!.isPickedUp) return Icons.local_shipping;
    if (_order!.isOnTheWay) return Icons.directions_car;
    if (_order!.isDelivered) return Icons.check_circle;
    return Icons.error;
  }

  Widget _buildRouteCard() {
    final heading = _order!.isPickedUp || _order!.isOnTheWay
        ? _order!.customerName
        : _order!.restaurant?.name ?? 'Restaurant';
    final address = _order!.isPickedUp || _order!.isOnTheWay
        ? _order!.deliveryAddress
        : _order!.restaurant?.address ?? 'Pickup location';
    final icon = _order!.isPickedUp || _order!.isOnTheWay
        ? Icons.person_pin_circle_outlined
        : Icons.storefront_outlined;
    final color = _order!.isPickedUp || _order!.isOnTheWay
        ? FoodFlowTheme.success
        : FoodFlowTheme.crimson;

    return Row(
      children: [
        Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            color: color.withOpacity(0.10),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: color),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                heading,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: FoodFlowTheme.ink,
                  fontWeight: FontWeight.w900,
                  fontSize: 15,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                address,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        IconButton(
          onPressed: _showNavigateDialog,
          icon: const Icon(Icons.navigation, color: FoodFlowTheme.success),
        ),
      ],
    );
  }

  bool get _hasPrimaryBottomAction {
    return _order != null &&
        (_order!.isDriverAssignmentPending ||
            _order!.isReadyForPickup ||
            _order!.isReachedPickup ||
            _order!.isPickedUp ||
            _order!.isOnTheWay);
  }

  String get _bottomStatusTitle {
    if (_order == null) return '';
    if (_order!.isDriverAssignmentPending) return 'New order incoming';
    if (_order!.isReadyForPickup) return 'You are heading to restaurant';
    if (_order!.isReachedPickup) return 'You reached restaurant';
    if (_order!.isPickedUp) return 'Order picked up';
    if (_order!.isOnTheWay) return 'On the way to customer';
    if (_order!.isDelivered) return 'Order delivered';
    return _order!.statusText;
  }

  String get _bottomActionText {
    if (_order == null) return '';
    if (_order!.isDriverAssignmentPending) return 'Swipe to accept';
    if (_order!.isReadyForPickup) return 'Swipe to confirm arrival';
    if (_order!.isReachedPickup) return 'Swipe after pickup';
    if (_order!.isPickedUp) return 'Swipe to start delivery';
    if (_order!.isOnTheWay) return 'Swipe to complete delivery';
    return '';
  }

  String get _bottomActionHint {
    if (_order == null) return '';
    if (_order!.isDriverAssignmentPending) return 'Review payout and accept';
    if (_order!.isReadyForPickup) return 'Confirm when you reach restaurant';
    if (_order!.isReachedPickup) return 'Confirm food is collected';
    if (_order!.isPickedUp) return 'Start customer delivery';
    if (_order!.isOnTheWay) return 'Enter OTP, then swipe';
    return '';
  }

  Future<void> _runBottomAction() async {
    if (_order == null || _isUpdating) return;
    setState(() => _swipeProgress = 0);

    if (_order!.isDriverAssignmentPending) {
      await _acceptAssignment();
    } else if (_order!.isReadyForPickup) {
      await _updateOrderStatus('reached_pickup');
    } else if (_order!.isReachedPickup) {
      await _updateOrderStatus('picked_up');
    } else if (_order!.isPickedUp) {
      await _updateOrderStatus('on_the_way');
    } else if (_order!.isOnTheWay) {
      await _verifyAndComplete();
    }
  }

  Widget _buildBottomStatusPanel() {
    if (_order == null || (!_hasPrimaryBottomAction && !_order!.isDelivered)) {
      return const SizedBox.shrink();
    }

    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(top: BorderSide(color: FoodFlowTheme.line)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.10),
              blurRadius: 22,
              offset: const Offset(0, -8),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: _order!.isDelivered
                        ? FoodFlowTheme.success.withOpacity(0.12)
                        : FoodFlowTheme.crimson.withOpacity(0.10),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(
                    _order!.isDelivered ? Icons.check_circle : _getStatusIcon(),
                    color: _order!.isDelivered
                        ? FoodFlowTheme.success
                        : FoodFlowTheme.crimson,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _bottomStatusTitle,
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 15,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          if (!_order!.isDelivered) ...[
                            Container(
                              width: 7,
                              height: 7,
                              decoration: BoxDecoration(
                                color: FoodFlowTheme.success,
                                borderRadius: BorderRadius.circular(4),
                              ),
                            ),
                            const SizedBox(width: 6),
                          ],
                          Expanded(
                            child: Text(
                              _order!.isDelivered
                                  ? 'Earnings added to today'
                                  : _bottomActionHint,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: FoodFlowTheme.muted,
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                Text(
                  formatCurrency(context, _order!.deliveryFee),
                  style: const TextStyle(
                    color: FoodFlowTheme.success,
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
            if (_order!.isDriverAssignmentPending) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _buildRejectButton(),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: _buildSwipeAction(),
                  ),
                ],
              ),
            ] else if (_order!.isOnTheWay) ...[
              const SizedBox(height: 12),
              _buildPaymentCollectionCard(),
              const SizedBox(height: 12),
              _buildOtpEntry(),
              const SizedBox(height: 12),
              _buildSwipeAction(),
            ] else if (_hasPrimaryBottomAction) ...[
              const SizedBox(height: 12),
              _buildSwipeAction(),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildRejectButton() {
    return GestureDetector(
      onTap: _isUpdating ? null : _rejectAssignment,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        height: 58,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: FoodFlowTheme.crimson.withOpacity(0.28)),
        ),
        child: const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.close_rounded, color: FoodFlowTheme.crimson, size: 20),
            SizedBox(width: 6),
            Text(
              'Reject',
              style: TextStyle(
                color: FoodFlowTheme.crimson,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentCollectionCard() {
    final order = _order!;
    final amountText = formatCurrency(context, order.total);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: FoodFlowTheme.success.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.payments_rounded,
                  color: FoodFlowTheme.success,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _isPaymentAlreadyPaid
                          ? 'Payment completed'
                          : 'Collect payment',
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _isPaymentAlreadyPaid
                          ? 'Verified by backend via $_paymentMethodLabel'
                          : 'Customer payable: $amountText',
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                amountText,
                style: const TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          if (!_isPaymentAlreadyPaid) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _buildPaymentModeOption(
                    mode: 'cash',
                    title: 'Cash',
                    icon: Icons.payments_rounded,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _buildPaymentModeOption(
                    mode: 'online',
                    title: 'Online',
                    icon: Icons.account_balance_wallet_rounded,
                  ),
                ),
              ],
            ),
            if (_selectedPaymentMode == 'cash') ...[
              const SizedBox(height: 10),
              InkWell(
                onTap: _isUpdating
                    ? null
                    : () => setState(() => _cashCollected = !_cashCollected),
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                  decoration: BoxDecoration(
                    color: _cashCollected
                        ? FoodFlowTheme.success.withOpacity(0.10)
                        : FoodFlowTheme.canvas,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: _cashCollected
                          ? FoodFlowTheme.success.withOpacity(0.36)
                          : FoodFlowTheme.line,
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        _cashCollected
                            ? Icons.check_circle_rounded
                            : Icons.radio_button_unchecked_rounded,
                        color: _cashCollected
                            ? FoodFlowTheme.success
                            : FoodFlowTheme.faint,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Cash collected from customer',
                          style: TextStyle(
                            color: _cashCollected
                                ? FoodFlowTheme.success
                                : FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ] else ...[
              const SizedBox(height: 10),
              const Text(
                'Backend will verify captured Razorpay payment before delivery is completed.',
                style: TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _buildPaymentModeOption({
    required String mode,
    required String title,
    required IconData icon,
  }) {
    final selected = _selectedPaymentMode == mode;

    return InkWell(
      onTap: _isUpdating
          ? null
          : () => setState(() {
                _selectedPaymentMode = mode;
                if (mode == 'online') _cashCollected = false;
              }),
      borderRadius: BorderRadius.circular(12),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        height: 48,
        decoration: BoxDecoration(
          color: selected ? FoodFlowTheme.ink : FoodFlowTheme.canvas,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: selected ? FoodFlowTheme.ink : FoodFlowTheme.line,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              color: selected ? Colors.white : FoodFlowTheme.muted,
              size: 20,
            ),
            const SizedBox(width: 7),
            Text(
              title,
              style: TextStyle(
                color: selected ? Colors.white : FoodFlowTheme.ink,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOtpEntry() {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 10, 10),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFAF5),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: FoodFlowTheme.orange.withOpacity(0.22)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: FoodFlowTheme.orange.withOpacity(0.12),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(
              Icons.password_rounded,
              color: FoodFlowTheme.orange,
              size: 20,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: TextFormField(
              controller: _otpController,
              enabled: !_isUpdating,
              keyboardType: TextInputType.number,
              maxLength: 4,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 18,
                fontWeight: FontWeight.w900,
                letterSpacing: 4,
              ),
              decoration: const InputDecoration(
                counterText: '',
                hintText: 'OTP',
                hintStyle: TextStyle(
                  color: FoodFlowTheme.faint,
                  letterSpacing: 0,
                  fontWeight: FontWeight.w800,
                ),
                border: InputBorder.none,
                isDense: true,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Tooltip(
            message: 'Resend OTP',
            child: InkWell(
              onTap: _isUpdating ? null : _resendOtp,
              borderRadius: BorderRadius.circular(11),
              child: Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(11),
                  border: Border.all(color: FoodFlowTheme.line),
                ),
                child: const Icon(
                  Icons.refresh_rounded,
                  color: FoodFlowTheme.ink,
                  size: 20,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSwipeAction() {
    return LayoutBuilder(
      builder: (context, constraints) {
        const knobWidth = 64.0;
        final trackWidth = constraints.maxWidth;
        final maxOffset = (trackWidth - knobWidth).clamp(0.0, double.infinity);
        final offset = maxOffset * _swipeProgress;
        final progressWidth = (knobWidth + offset).clamp(knobWidth, trackWidth);
        final readyToConfirm = _swipeProgress > 0.85;

        return GestureDetector(
          onHorizontalDragUpdate: _isUpdating
              ? null
              : (details) {
                  if (maxOffset <= 0) return;
                  final delta = details.primaryDelta ?? 0;
                  setState(() {
                    _swipeProgress = (_swipeProgress + delta / maxOffset)
                        .clamp(0.0, 1.0)
                        .toDouble();
                  });
                },
          onHorizontalDragEnd: _isUpdating
              ? null
              : (_) {
                  if (readyToConfirm) {
                    _runBottomAction();
                  } else {
                    setState(() => _swipeProgress = 0);
                  }
                },
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            height: 58,
            decoration: BoxDecoration(
              color: _isUpdating ? FoodFlowTheme.line : const Color(0xFFECFFF4),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: _isUpdating
                    ? FoodFlowTheme.line
                    : FoodFlowTheme.success.withOpacity(0.30),
              ),
              boxShadow: [
                BoxShadow(
                  color: FoodFlowTheme.success.withOpacity(0.18),
                  blurRadius: 18,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Stack(
              alignment: Alignment.centerLeft,
              children: [
                Positioned.fill(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 120),
                        width: progressWidth,
                        decoration: const BoxDecoration(
                          gradient: LinearGradient(
                            colors: [FoodFlowTheme.success, Color(0xFF24A866)],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                Positioned.fill(
                  child: Center(
                    child: AnimatedDefaultTextStyle(
                      duration: const Duration(milliseconds: 120),
                      style: TextStyle(
                        color:
                            readyToConfirm ? Colors.white : FoodFlowTheme.ink,
                        fontWeight: FontWeight.w900,
                        fontSize: 14,
                      ),
                      child: Text(
                        _isUpdating
                            ? 'Updating...'
                            : readyToConfirm
                                ? 'Release to confirm'
                                : _bottomActionText,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ),
                ),
                Positioned(
                  left: offset,
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 90),
                    width: knobWidth,
                    height: 58,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(
                        color: readyToConfirm
                            ? Colors.white
                            : FoodFlowTheme.success.withOpacity(0.20),
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.18),
                          blurRadius: 16,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Icon(
                      _isUpdating ? Icons.hourglass_top : Icons.arrow_forward,
                      color: _isUpdating
                          ? FoodFlowTheme.muted
                          : readyToConfirm
                              ? FoodFlowTheme.success
                              : FoodFlowTheme.success,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_order == null) {
      return Scaffold(
        backgroundColor: FoodFlowTheme.canvas,
        appBar: AppBar(title: const Text('Order Details')),
        body: NetworkErrorView(
          title: 'Unable to load order',
          message: _loadError ?? 'Order not found',
          onRetry: _loadOrder,
        ),
      );
    }

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: Text('Order ID: ${_order!.orderNumber}'),
        backgroundColor: Colors.white,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 12),
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: _order!.paymentStatus == 'paid'
                  ? FoodFlowTheme.success.withOpacity(0.12)
                  : FoodFlowTheme.crimson.withOpacity(0.12),
              borderRadius: BorderRadius.circular(5),
            ),
            child: Text(
              _order!.paymentStatus.toUpperCase(),
              style: TextStyle(
                color: _order!.paymentStatus == 'paid'
                    ? FoodFlowTheme.success
                    : FoodFlowTheme.crimson,
                fontSize: 11,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: _buildBottomStatusPanel(),
      body: SingleChildScrollView(
        padding: EdgeInsets.only(bottom: _hasPrimaryBottomAction ? 18 : 0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: 430,
              child: Stack(
                children: [
                  Positioned.fill(
                    child: GoogleMap(
                      onMapCreated: _onMapCreated,
                      initialCameraPosition: CameraPosition(
                        target: _restaurantLocation ??
                            const LatLng(28.6139, 77.2090),
                        zoom: 13,
                      ),
                      markers: {
                        if (_restaurantLocation != null)
                          Marker(
                            markerId: const MarkerId('pickup'),
                            position: _restaurantLocation!,
                            infoWindow:
                                const InfoWindow(title: 'Pickup Location'),
                            icon: BitmapDescriptor.defaultMarkerWithHue(
                                BitmapDescriptor.hueRed),
                          ),
                        if (_deliveryLocation != null)
                          Marker(
                            markerId: const MarkerId('delivery'),
                            position: _deliveryLocation!,
                            infoWindow:
                                const InfoWindow(title: 'Delivery Location'),
                            icon: BitmapDescriptor.defaultMarkerWithHue(
                                BitmapDescriptor.hueGreen),
                          ),
                      },
                      polylines: _polylines,
                    ),
                  ),
                  Positioned(
                    top: 14,
                    left: 16,
                    right: 16,
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: _order!.isOnTheWay
                            ? const Color(0xFF0F7A45)
                            : FoodFlowTheme.crimson,
                        borderRadius: BorderRadius.circular(8),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.12),
                            blurRadius: 16,
                            offset: const Offset(0, 8),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          Icon(
                            _order!.isOnTheWay
                                ? Icons.turn_left
                                : _getStatusIcon(),
                            color: Colors.white,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _order!.isOnTheWay
                                      ? 'Head straight'
                                      : _order!.isReadyForPickup ||
                                              _order!.isReachedPickup
                                          ? 'Go to restaurant'
                                          : _order!.statusText,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                    fontSize: 15,
                                  ),
                                ),
                                Text(
                                  _order!.isPickedUp || _order!.isOnTheWay
                                      ? '200 m - 23rd Cross Road'
                                      : '4.6 km - pickup route',
                                  style: TextStyle(
                                    color: Colors.white.withOpacity(0.85),
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const Icon(Icons.keyboard_arrow_down,
                              color: Colors.white),
                        ],
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
                            color: Colors.black.withOpacity(0.12),
                            blurRadius: 20,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: _buildRouteCard(),
                    ),
                  ),
                ],
              ),
            ),
            if (_order!.isDelivered)
              Container(
                margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                padding: const EdgeInsets.all(22),
                decoration: FoodFlowTheme.surface(radius: 12),
                child: Column(
                  children: [
                    Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(
                        color: FoodFlowTheme.success.withOpacity(0.12),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.check_circle,
                        color: FoodFlowTheme.success,
                        size: 44,
                      ),
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      'Order Delivered!',
                      style: TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      formatCurrency(context, _order!.deliveryFee),
                      style: const TextStyle(
                        color: FoodFlowTheme.success,
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const Text(
                      'Total Earnings',
                      style: TextStyle(color: FoodFlowTheme.muted),
                    ),
                  ],
                ),
              ),
            Card(
              margin: const EdgeInsets.all(16),
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: const BorderSide(color: FoodFlowTheme.line),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (_order!.isPartOfRouteBatch) ...[
                      Container(
                        width: double.infinity,
                        margin: const EdgeInsets.only(bottom: 16),
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEEF6FF),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFBFDBFE)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Row(
                              children: [
                                Icon(
                                  Icons.route,
                                  color: Color(0xFF2563EB),
                                  size: 18,
                                ),
                                SizedBox(width: 8),
                                Text(
                                  'Grouped Delivery Route',
                                  style: TextStyle(
                                    color: Color(0xFF1D4ED8),
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'Batch ${_order!.routeBatch!.id} contains ${_order!.routeBatch!.ordersCount} matched orders.',
                              style: const TextStyle(
                                color: Color(0xFF334155),
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Orders: ${_order!.routeBatch!.orderNumbers.join(', ')}',
                              style: const TextStyle(
                                color: Color(0xFF475569),
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            if (_order!.routeBatch!.restaurants.isNotEmpty) ...[
                              const SizedBox(height: 6),
                              Text(
                                'Pickups: ${_order!.routeBatch!.restaurants.join(', ')}',
                                style: const TextStyle(
                                  color: Color(0xFF475569),
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                    const Text(
                      'Order Details',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Restaurant Info
                    Row(
                      children: [
                        const Icon(Icons.restaurant,
                            size: 16, color: Colors.grey),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _order!.restaurant?.name ?? '',
                            style: const TextStyle(fontSize: 14),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),

                    // Customer Info with Call Button
                    Row(
                      children: [
                        const Icon(Icons.person, size: 16, color: Colors.grey),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            '${_order!.customerName} • ${_order!.customerPhone}',
                            style: const TextStyle(fontSize: 14),
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.call,
                              size: 18, color: Colors.green),
                          onPressed: _showCallDialog,
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),

                    // Delivery Address with Navigate Button
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.location_on,
                            size: 16, color: Colors.grey),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _order!.deliveryAddress,
                            style: const TextStyle(fontSize: 14),
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.navigation,
                              size: 18, color: Colors.blue),
                          onPressed: _showNavigateDialog,
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    const Divider(),

                    // Items List
                    const Text(
                      'Items',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 8),
                    ..._order!.items.map((item) => Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '${item.quantity}x ${item.name}',
                                      style: const TextStyle(fontSize: 14),
                                    ),
                                    if (item.hasCustomizations) ...[
                                      const SizedBox(height: 2),
                                      Text(
                                        item.customizationSummary,
                                        style: const TextStyle(
                                          fontSize: 12,
                                          color: Colors.grey,
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                              const SizedBox(width: 12),
                              Text(
                                formatCurrency(context, item.totalPrice),
                                style: const TextStyle(fontSize: 14),
                              ),
                            ],
                          ),
                        )),
                    const Divider(),

                    // Totals
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Subtotal'),
                          Text(formatCurrency(context, _order!.subtotal)),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Delivery Fee'),
                          Text(formatCurrency(context, _order!.deliveryFee)),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Tax'),
                          Text(formatCurrency(context, _order!.tax)),
                        ],
                      ),
                    ),
                    const Divider(),
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Total',
                            style: TextStyle(fontWeight: FontWeight.bold),
                          ),
                          Text(
                            formatCurrency(context, _order!.total),
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                              color: Color(0xFF0E9F6E),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }
}

class _DriverActionTarget {
  final String title;
  final String name;
  final String phone;
  final String address;
  final LatLng? location;
  final IconData icon;
  final Color color;

  const _DriverActionTarget({
    required this.title,
    required this.name,
    required this.phone,
    required this.address,
    required this.location,
    required this.icon,
    required this.color,
  });

  bool get canNavigate => location != null || address.trim().isNotEmpty;
}

class _DriverSheetAction {
  final _DriverActionTarget target;
  final String label;
  final String value;
  final IconData icon;
  final bool enabled;
  final VoidCallback onTap;

  const _DriverSheetAction({
    required this.target,
    required this.label,
    required this.value,
    required this.icon,
    required this.enabled,
    required this.onTap,
  });
}

class _DriverActionSheet extends StatelessWidget {
  final String title;
  final String subtitle;
  final _DriverSheetAction primaryAction;
  final _DriverSheetAction secondaryAction;

  const _DriverActionSheet({
    required this.title,
    required this.subtitle,
    required this.primaryAction,
    required this.secondaryAction,
  });

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        margin: const EdgeInsets.all(12),
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.16),
              blurRadius: 28,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 46,
                height: 5,
                decoration: BoxDecoration(
                  color: const Color(0xFFE1E4EA),
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: primaryAction.target.color.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(
                    primaryAction.target.icon,
                    color: primaryAction.target.color,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _DriverActionTile(action: primaryAction, prominent: true),
            const SizedBox(height: 10),
            _DriverActionTile(action: secondaryAction),
          ],
        ),
      ),
    );
  }
}

class _DriverActionTile extends StatelessWidget {
  final _DriverSheetAction action;
  final bool prominent;

  const _DriverActionTile({
    required this.action,
    this.prominent = false,
  });

  @override
  Widget build(BuildContext context) {
    final color = action.enabled ? action.target.color : FoodFlowTheme.faint;

    return Material(
      color: prominent
          ? action.target.color.withOpacity(0.08)
          : const Color(0xFFF8F9FB),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: action.enabled ? action.onTap : null,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: prominent
                  ? action.target.color.withOpacity(0.24)
                  : FoodFlowTheme.line,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(13),
                  border: Border.all(color: FoodFlowTheme.line),
                ),
                child: Icon(action.icon, color: color, size: 21),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      action.label,
                      style: TextStyle(
                        color: action.enabled
                            ? FoodFlowTheme.ink
                            : FoodFlowTheme.faint,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      action.target.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.inkSoft,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      action.value,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: action.enabled
                            ? FoodFlowTheme.muted
                            : FoodFlowTheme.faint,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Icon(
                Icons.arrow_forward_ios_rounded,
                size: 16,
                color: color,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
