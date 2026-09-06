// lib/screens/driver/driver_order_detail_screen.dart
import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../services/websocket_service.dart';
import '../../services/directions_service.dart';
import '../../services/location_service.dart';
import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../widgets/aurora/swipe_to_confirm.dart';
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
  GoogleMapController? _mapController;
  LatLng? _restaurantLocation;
  LatLng? _deliveryLocation;
  List<LatLng> _routePoints = [];
  Set<Polyline> _polylines = {};
  final TextEditingController _otpController = TextEditingController();
  String _selectedPaymentMode = 'cash';
  bool _cashCollected = false;
  bool _isGeneratingQr = false;
  Timer? _paymentPollTimer;

  @override
  void initState() {
    super.initState();
    _loadOrder();
  }

  @override
  void dispose() {
    _otpController.dispose();
    _paymentPollTimer?.cancel();
    _mapController?.dispose();
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
          _selectedPaymentMode =
              order.isPaymentPaid ? 'online' : _paymentModeFor(order);
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

  /// Reload this order AND tell the rest of the app (dashboard stats, orders
  /// list) to refresh — the server doesn't echo the driver's own changes back.
  Future<void> _reloadAndBroadcast() async {
    await _loadOrder();
    WebSocketService().notifyLocalOrderChange(
      orderId: widget.orderId,
      status: _order?.status,
    );
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
        await _reloadAndBroadcast();
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

  Future<void> _markArrivedAtCustomer() async {
    setState(() => _isUpdating = true);

    try {
      final response =
          await _api.post(ApiConstants.driverArrived(widget.orderId));

      if (response['success'] == true) {
        await _reloadAndBroadcast();
      }
    } catch (e) {
      debugPrint('Mark arrived error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to record arrival: $e')),
        );
      }
    }

    setState(() => _isUpdating = false);
  }

  Future<void> _showReportDeliveryFailedDialog() async {
    final reasonController = TextEditingController();

    final reason = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Report Delivery Issue'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              "Tell us what happened. This will mark the delivery as failed and you'll still be paid for this trip.",
            ),
            const SizedBox(height: 12),
            TextField(
              controller: reasonController,
              maxLines: 3,
              autofocus: true,
              decoration: const InputDecoration(
                labelText: 'Reason',
                hintText: 'e.g. Customer not responding, refused delivery...',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              final text = reasonController.text.trim();
              if (text.isEmpty) {
                ScaffoldMessenger.of(dialogContext).showSnackBar(
                  const SnackBar(content: Text('Please enter a reason')),
                );
                return;
              }
              Navigator.pop(dialogContext, text);
            },
            child: const Text('Submit'),
          ),
        ],
      ),
    );

    if (reason != null && reason.isNotEmpty) {
      await _reportDeliveryFailed(reason);
    }
  }

  Future<void> _reportDeliveryFailed(String reason) async {
    setState(() => _isUpdating = true);

    try {
      final response = await _api.post(
        ApiConstants.driverReportDeliveryFailed(widget.orderId),
        data: {'reason': reason},
      );

      if (response['success'] == true) {
        await _reloadAndBroadcast();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                'Delivery marked as failed. You have been paid for this delivery.',
              ),
            ),
          );
        }
      }
    } catch (e) {
      debugPrint('Report delivery failed error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to report delivery issue: $e')),
        );
      }
    }

    setState(() => _isUpdating = false);
  }

  Future<void> _confirmFoodReturned() async {
    setState(() => _isUpdating = true);

    try {
      final response = await _api.post(
        ApiConstants.driverConfirmFoodReturned(widget.orderId),
      );

      if (response['success'] == true) {
        await _reloadAndBroadcast();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Food return confirmed.')),
          );
        }
      }
    } catch (e) {
      debugPrint('Confirm food returned error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to confirm return: $e')),
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
        await _reloadAndBroadcast();
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

    if (!_isPaymentAlreadyPaid &&
        _selectedPaymentMode == 'cash' &&
        !_cashCollected) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Confirm cash collection first')),
      );
      return;
    }

    if (!_isPaymentAlreadyPaid && _selectedPaymentMode == 'online') {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Online payment is still pending')),
      );
      return;
    }

    setState(() => _isUpdating = true);

    try {
      // Report the mode the driver actually used to settle, not the transient
      // selector value (which `_loadOrder` flips to 'online' once paid).
      final settledMode = _cashCollected
          ? 'cash'
          : (_order?.cashCollectedAmount != null ? 'cash' : _selectedPaymentMode);
      final response = await _api.post(
        '${ApiConstants.verifyDeliveryOtp}/${widget.orderId}',
        data: {
          'otp': _otpController.text,
          'payment_mode': settledMode,
          'cash_collected': _cashCollected || _order?.cashCollectedAmount != null,
        },
      );

      if (response['success'] == true) {
        await _reloadAndBroadcast();
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

  _DriverActionTarget get _customerContactTarget => _DriverActionTarget(
        title: 'Customer',
        role: 'customer',
        name: _order?.customerName ?? 'Customer',
        address: _order?.deliveryAddress ?? '',
        location: _deliveryLocation,
        icon: Icons.person_rounded,
        color: foodflow.success,
        callable: _order != null,
      );

  _DriverActionTarget get _storeContactTarget => _DriverActionTarget(
        title: 'Store',
        role: 'restaurant',
        name: _order?.restaurant?.name ?? 'Store',
        address: _order?.restaurant?.address ?? '',
        location: _restaurantLocation,
        icon: Icons.restaurant_rounded,
        color: foodflow.primaryColor,
        callable: _order?.restaurant != null,
      );

  _DriverActionTarget get _activeContactTarget =>
      _isCustomerLegActive ? _customerContactTarget : _storeContactTarget;

  _DriverActionTarget get _alternateContactTarget =>
      _isCustomerLegActive ? _storeContactTarget : _customerContactTarget;

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
          value: primary.callable ? 'Tap to call' : 'Not available',
          icon: Icons.call_rounded,
          enabled: primary.callable,
          onTap: () {
            Navigator.pop(context);
            _callParticipant(primary.role);
          },
        ),
        secondaryAction: _DriverSheetAction(
          target: secondary,
          label: 'Call ${secondary.title}',
          value: secondary.callable ? 'Tap to call' : 'Not available',
          icon: Icons.call_outlined,
          enabled: secondary.callable,
          onTap: () {
            Navigator.pop(context);
            _callParticipant(secondary.role);
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
            : 'Use store pickup location',
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

  Future<void> _callParticipant(String target) async {
    try {
      final response = await _api.post(
        ApiConstants.driverCallParticipant(widget.orderId),
        data: {'target': target},
      );
      final success = response is Map && response['success'] == true;
      final message = response is Map ? response['message']?.toString() : null;
      _showSnack(
        success
            ? 'Connecting your call…'
            : (message ?? 'Could not place the call.'),
      );
    } catch (e) {
      _showSnack('Could not place the call.');
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
        _mapController?.animateCamera(
          CameraUpdate.newCameraPosition(
            CameraPosition(target: _restaurantLocation!, zoom: 13),
          ),
        );
      }
      return;
    }

    if (points.length == 1) {
      _mapController?.animateCamera(
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

    _mapController?.animateCamera(
      CameraUpdate.newLatLngBounds(
        LatLngBounds(southwest: southwest, northeast: northeast),
        70,
      ),
    );
  }

  double _min(double a, double b) => a < b ? a : b;
  double _max(double a, double b) => a > b ? a : b;

  String _formatTime(DateTime value) {
    final local = value.toLocal();
    final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
    final minute = local.minute.toString().padLeft(2, '0');
    final period = local.hour >= 12 ? 'PM' : 'AM';
    return '$hour:$minute $period';
  }

  String _paymentModeFor(Order order) {
    final method = order.paymentMethod.toLowerCase();
    if (method == 'cod' || method == 'cash') return 'cash';
    return 'online';
  }

  bool get _isPaymentAlreadyPaid => _order?.isPaymentPaid == true;

  String _driverEarningText(Order order) {
    final earning = formatCurrency(context, order.driverEarningAmount);
    if (order.driverIncentiveAmount > 0) {
      return '$earning + ${formatCurrency(context, order.driverIncentiveAmount)} incentive';
    }
    return earning;
  }

  String get _paymentMethodLabel {
    final method = _order?.paymentMethod.toLowerCase() ?? 'cod';
    switch (method) {
      case 'cod':
        return 'Cash on delivery';
      case 'cash':
        return 'Cash';
      case 'razorpay':
        return 'Razorpay';
      case 'cashfree':
        return 'Cashfree';
      case 'stripe':
        return 'Stripe';
      case 'upi':
        return 'UPI';
      case 'card':
        return 'Card';
      default:
        return method.toUpperCase();
    }
  }

  Future<void> _markCashReceived() async {
    final order = _order;
    if (order == null || _isUpdating) return;

    setState(() => _isUpdating = true);
    try {
      final response = await _api.post(
        ApiConstants.driverCash(order.id),
        data: {
          'amount': order.total,
          'collection_notes': 'Collected by driver at delivery',
        },
      );
      if (response['success'] == true) {
        await _reloadAndBroadcast();
        if (mounted) {
          setState(() => _cashCollected = true);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Cash payment recorded')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_cleanApiError(e))),
        );
      }
    } finally {
      if (mounted) setState(() => _isUpdating = false);
    }
  }

  Future<void> _generateQr() async {
    if (_order == null || _isGeneratingQr) return;

    setState(() => _isGeneratingQr = true);
    try {
      final endpoint = ApiConstants.driverPaymentLink(_order!.id);
      debugPrint('Driver QR Flutter endpoint: $endpoint');
      final response = await _api.post(endpoint);
      final data = Map<String, dynamic>.from(response['data'] ?? const {});
      debugPrint('Driver QR API response: ${_qrResponseDebugSummary(data)}');
      debugPrint(
          'Driver QR image_url received by Flutter: ${data['image_url'] ?? data['qr_image_url'] ?? ''}');
      await _showQrPaymentSheet(data);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(_cleanApiError(e))),
        );
      }
    } finally {
      if (mounted) setState(() => _isGeneratingQr = false);
    }
  }

  Future<void> _showQrPaymentSheet(Map<String, dynamic> data) async {
    final qrData =
        (data['image_content'] ?? data['qr_payload'] ?? data['qr_code'])
                ?.toString()
                .trim() ??
            '';
    final qrImageBytes = _decodeQrImageBytes(data['image_bytes']);
    final expiresAt = DateTime.tryParse(data['expires_at']?.toString() ?? '') ??
        DateTime.now().add(const Duration(minutes: 10));
    if (qrData.isEmpty && qrImageBytes == null) {
      debugPrint(
        'Driver QR render data missing. image_url=${data['image_url'] ?? data['qr_image_url'] ?? ''}, '
        'image_content_length=${(data['image_content'] ?? data['qr_payload'] ?? data['qr_code'] ?? '').toString().length}, '
        'image_bytes_length=${(data['image_bytes'] ?? '').toString().length}',
      );
      throw StateError('Payment QR image unavailable. Please try again.');
    }

    Timer? ticker;
    Timer? poller;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: foodflow.surfaceColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
      ),
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            ticker ??= Timer.periodic(const Duration(seconds: 1), (_) {
              if (context.mounted) setSheetState(() {});
            });
            poller ??= Timer.periodic(const Duration(seconds: 3), (_) async {
              final order = _order;
              if (order == null || !context.mounted) return;
              final response =
                  await _api.get(ApiConstants.orderPaymentStatus(order.id));
              final status = response['data'] is Map
                  ? (response['data']['payment_status']?.toString() ?? '')
                  : '';
              if (status == 'success' || status == 'paid') {
                ticker?.cancel();
                poller?.cancel();
                if (context.mounted) Navigator.pop(context);
                await _reloadAndBroadcast();
                if (mounted) {
                  ScaffoldMessenger.of(this.context).showSnackBar(
                    const SnackBar(content: Text('Payment received')),
                  );
                }
              }
            });

            final remaining = expiresAt.difference(DateTime.now());
            final expired = remaining.inSeconds <= 0;
            final minutes =
                remaining.inMinutes.remainder(60).toString().padLeft(2, '0');
            final seconds =
                remaining.inSeconds.remainder(60).toString().padLeft(2, '0');
            final qrSize = (MediaQuery.of(context).size.width * 0.86)
                .clamp(300.0, 430.0)
                .toDouble();

            return SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(22, 18, 22, 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'UPI QR',
                            style: TextStyle(
                                fontSize: 20, fontWeight: FontWeight.w800),
                          ),
                        ),
                        IconButton(
                          onPressed: () {
                            ticker?.cancel();
                            poller?.cancel();
                            Navigator.pop(context);
                          },
                          icon: Icon(Icons.close),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _buildQrDisplay(qrData, qrImageBytes, qrSize),
                    const SizedBox(height: 12),
                    Text(
                      expired ? 'QR expired' : 'Expires in $minutes:$seconds',
                      style: TextStyle(
                        color: expired ? foodflow.orange : foodflow.success,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      formatCurrency(
                          context,
                          (data['amount'] as num?)?.toDouble() ??
                              (_order?.total ?? 0)),
                      style:
                          TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () {
                              ticker?.cancel();
                              poller?.cancel();
                              Navigator.pop(context);
                            },
                            child: const Text('Cancel'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: expired
                                ? () {
                                    ticker?.cancel();
                                    poller?.cancel();
                                    Navigator.pop(context);
                                    _generateQr();
                                  }
                                : null,
                            child: const Text('Refresh QR'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    ).whenComplete(() {
      ticker?.cancel();
      poller?.cancel();
    });
  }

  Uint8List? _decodeQrImageBytes(dynamic value) {
    final encoded = value?.toString().trim() ?? '';
    if (encoded.isEmpty) return null;

    try {
      return base64Decode(encoded);
    } catch (e) {
      debugPrint('Payment QR image decode error: $e');
      return null;
    }
  }

  Widget _buildQrDisplay(String qrData, Uint8List? qrImageBytes, double size) {
    if (qrData.isEmpty && qrImageBytes != null) {
      return ClipRect(
        child: Image.memory(
          qrImageBytes,
          width: size,
          height: size,
          fit: BoxFit.cover,
          alignment: Alignment.center,
          filterQuality: FilterQuality.high,
          errorBuilder: (context, error, stackTrace) {
            debugPrint('Payment QR image loading exception: $error');
            debugPrint('Payment QR image loading stack: $stackTrace');
            return _qrLoadError(size);
          },
        ),
      );
    }

    return QrImageView(
      data: qrData,
      size: size,
      backgroundColor: foodflow.surfaceColor,
    );
  }

  Widget _qrLoadError(double size) {
    return SizedBox(
      width: size,
      height: size,
      child: Center(
        child: Text(
          'Unable to load payment QR',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: foodflow.orange,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }

  String _qrResponseDebugSummary(Map<String, dynamic> data) {
    final imageContent =
        (data['image_content'] ?? data['qr_payload'] ?? data['qr_code'] ?? '')
            .toString();
    final imageBytes = (data['image_bytes'] ?? '').toString();
    return {
      'payment_attempt_id': data['payment_attempt_id'],
      'qr_id': data['qr_id'],
      'status': data['status'],
      'render_mode': data['render_mode'],
      'image_url': data['image_url'] ?? data['qr_image_url'],
      'image_content_length': imageContent.length,
      'image_bytes_length': imageBytes.length,
      'image_mime_type': data['image_mime_type'],
      'image_encoding': data['image_encoding'],
      'expires_at': data['expires_at'],
    }.toString();
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
        : _order!.restaurant?.name ?? 'Store';
    final address = _order!.isPickedUp || _order!.isOnTheWay
        ? _order!.deliveryAddress
        : _order!.restaurant?.address ?? 'Pickup location';
    final icon = _order!.isPickedUp || _order!.isOnTheWay
        ? Icons.person_pin_circle_outlined
        : Icons.storefront_outlined;
    final color = _order!.isPickedUp || _order!.isOnTheWay
        ? foodflow.success
        : foodflow.orange;

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
                style: TextStyle(
                  color: foodflow.ink,
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                address,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: foodflow.muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w400,
                ),
              ),
            ],
          ),
        ),
        IconButton(
          onPressed: _showNavigateDialog,
          icon: Icon(Icons.navigation, color: foodflow.success),
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
    if (_order!.isReadyForPickup) return 'You are heading to store';
    if (_order!.isReachedPickup) return 'You reached store';
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
    if (_order!.isReadyForPickup) return 'Confirm when you reach store';
    if (_order!.isReachedPickup) return 'Confirm food is collected';
    if (_order!.isPickedUp) return 'Start customer delivery';
    if (_order!.isOnTheWay) return 'Enter OTP, then swipe';
    return '';
  }

  Future<void> _runBottomAction() async {
    if (_order == null || _isUpdating) return;

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

  /// Horizontal stage tracker for the delivery flow:
  /// accept → reach store → pick up → on the way → delivered.
  Widget _orderFlowTracker() {
    final o = _order!;
    int done;
    if (o.isDelivered) {
      done = 5;
    } else if (o.isOnTheWay) {
      done = 4;
    } else if (o.isPickedUp) {
      done = 3;
    } else if (o.isReachedPickup) {
      done = 2;
    } else if (o.isReadyForPickup) {
      done = 1;
    } else {
      done = 0;
    }

    const labels = ['Accept', 'At store', 'Picked', 'On way', 'Done'];
    const icons = [
      Icons.assignment_turned_in_rounded,
      Icons.storefront_rounded,
      Icons.shopping_bag_rounded,
      Icons.two_wheeler_rounded,
      Icons.flag_rounded,
    ];

    Widget node(int i) {
      final complete = i < done;
      final active = i == done && !o.isDelivered;
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 260),
            width: 30,
            height: 30,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: complete
                  ? foodflow.success
                  : active
                      ? foodflow.success.withOpacity(0.14)
                      : foodflow.canvas,
              shape: BoxShape.circle,
              border: Border.all(
                color: active
                    ? foodflow.success
                    : complete
                        ? foodflow.success
                        : foodflow.line,
                width: active ? 2 : 1,
              ),
            ),
            child: Icon(
              complete ? Icons.check_rounded : icons[i],
              size: 15,
              color: complete
                  ? Colors.white
                  : active
                      ? foodflow.success
                      : foodflow.muted,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            labels[i],
            style: TextStyle(
              fontSize: 9.5,
              fontWeight: active ? FontWeight.w800 : FontWeight.w600,
              color: complete || active ? foodflow.ink : foodflow.muted,
            ),
          ),
        ],
      );
    }

    Widget connector(int i) {
      return Expanded(
        child: Container(
          margin: const EdgeInsets.only(top: 14),
          height: 2,
          color: i < done ? foodflow.success : foodflow.line,
        ),
      );
    }

    final row = <Widget>[];
    for (var i = 0; i < 5; i++) {
      row.add(node(i));
      if (i < 4) row.add(connector(i));
    }

    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: row);
  }

  Widget _buildBottomStatusPanel() {
    if (_order == null ||
        (!_hasPrimaryBottomAction && !_order!.isDeliveryFailed) ||
        _order!.isDelivered) {
      return const SizedBox.shrink();
    }

    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        decoration: BoxDecoration(
          color: foodflow.surfaceColor,
          border: Border(top: BorderSide(color: foodflow.line)),
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
                Icon(_getStatusIcon(), size: 16, color: foodflow.orange),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _bottomActionHint.isEmpty
                        ? _bottomStatusTitle
                        : _bottomActionHint,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: foodflow.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (_order!.isDriverAssignmentPending)
              Row(
                children: [
                  Expanded(child: _buildRejectButton()),
                  const SizedBox(width: 10),
                  Expanded(flex: 2, child: _buildSwipeAction()),
                ],
              )
            else if (_order!.isDeliveryFailed)
              _buildResaleStatusPanel()
            else if (_hasPrimaryBottomAction)
              _buildSwipeAction(),
          ],
        ),
      ),
    );
  }

  Widget _buildResaleStatusPanel() {
    final order = _order!;

    if (order.isAwaitingFoodReturn) {
      return GestureDetector(
        onTap: _isUpdating ? null : _confirmFoodReturned,
        child: Container(
          width: double.infinity,
          height: 52,
          decoration: BoxDecoration(
            color: foodflow.surfaceColor,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: foodflow.orange.withOpacity(0.4)),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.store_rounded, color: foodflow.orange, size: 20),
              SizedBox(width: 6),
              Text(
                'Confirm Food Returned to Restaurant',
                style: TextStyle(
                  color: foodflow.orange,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (order.isResaleClaimed) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: foodflow.success.withOpacity(0.08),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: foodflow.success.withOpacity(0.28)),
        ),
        child:  Text(
          'Resold! Check your orders for the new delivery.',
          textAlign: TextAlign.center,
          style:
              TextStyle(color: foodflow.success, fontWeight: FontWeight.w700),
        ),
      );
    }

    if (order.isFoodReturned) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: foodflow.canvas,
          borderRadius: BorderRadius.circular(14),
        ),
        child:  Text(
          'Food returned to restaurant.',
          textAlign: TextAlign.center,
          style: TextStyle(color: foodflow.muted, fontWeight: FontWeight.w700),
        ),
      );
    }

    // isResaleOffered (or resale status not yet known) -- show a countdown.
    final expiresAt = order.resaleOfferExpiresAt;
    if (expiresAt == null) {
      return const SizedBox.shrink();
    }

    return StreamBuilder<int>(
      stream: Stream.periodic(const Duration(seconds: 1), (value) => value),
      builder: (context, _) {
        final remaining = expiresAt.difference(DateTime.now());
        final minutes = remaining.isNegative ? 0 : remaining.inMinutes;
        final seconds = remaining.isNegative ? 0 : remaining.inSeconds % 60;

        return Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            color: foodflow.orange.withOpacity(0.08),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: foodflow.orange.withOpacity(0.28)),
          ),
          child: Text(
            'Trying to resell nearby... ${minutes}m ${seconds}s left',
            textAlign: TextAlign.center,
            style:
                TextStyle(color: foodflow.orange, fontWeight: FontWeight.w700),
          ),
        );
      },
    );
  }

  Widget _buildArrivalPanel() {
    final order = _order!;

    if (order.arrivedAtCustomer == null) {
      return GestureDetector(
        onTap: _isUpdating ? null : _markArrivedAtCustomer,
        child: Container(
          width: double.infinity,
          height: 52,
          decoration: BoxDecoration(
            color: foodflow.surfaceColor,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: foodflow.success.withOpacity(0.4)),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.location_on_rounded,
                  color: foodflow.success, size: 20),
              SizedBox(width: 6),
              Text(
                "I've Arrived",
                style: TextStyle(
                  color: foodflow.success,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      );
    }

    final unlockAt = order.arrivedAtCustomer!
        .add(Duration(minutes: order.deliveryFailureWaitMinutes));

    return StreamBuilder<int>(
      stream: Stream.periodic(const Duration(seconds: 1), (value) => value),
      builder: (context, _) {
        final remaining = unlockAt.difference(DateTime.now());

        if (remaining > Duration.zero) {
          final minutes = remaining.inMinutes;
          final seconds = remaining.inSeconds % 60;
          return Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 14),
            decoration: BoxDecoration(
              color: foodflow.orange.withOpacity(0.08),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: foodflow.orange.withOpacity(0.28)),
            ),
            child: Text(
              'You can report an issue in ${minutes}m ${seconds}s',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: foodflow.orange,
                fontWeight: FontWeight.w700,
              ),
            ),
          );
        }

        return GestureDetector(
          onTap: _isUpdating ? null : _showReportDeliveryFailedDialog,
          child: Container(
            width: double.infinity,
            height: 52,
            decoration: BoxDecoration(
              color: foodflow.surfaceColor,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: foodflow.orange.withOpacity(0.28)),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.report_problem_outlined,
                    color: foodflow.orange, size: 20),
                SizedBox(width: 6),
                Text(
                  'Report Delivery Issue',
                  style: TextStyle(
                    color: foodflow.orange,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildRejectButton() {
    return GestureDetector(
      onTap: _isUpdating ? null : _rejectAssignment,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        height: 58,
        decoration: BoxDecoration(
          color: foodflow.surfaceColor,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: foodflow.orange.withOpacity(0.28)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.close_rounded, color: foodflow.orange, size: 20),
            SizedBox(width: 6),
            Text(
              'Reject',
              style: TextStyle(
                color: foodflow.orange,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPickupTimingPanel(Order order) {
    return StreamBuilder<int>(
      stream: Stream.periodic(const Duration(seconds: 1), (value) => value),
      builder: (context, _) {
        final delayed = order.isPreparationDelayed ||
            (order.readyByAt?.isBefore(DateTime.now()) == true &&
                (order.isConfirmed || order.isPreparing));
        final color = delayed ? foodflow.orange : foodflow.orange;

        return Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: color.withOpacity(0.08),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: color.withOpacity(0.24)),
          ),
          child: Row(
            children: [
              Icon(
                delayed ? Icons.warning_amber_rounded : Icons.timer_outlined,
                color: color,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      delayed ? 'Pickup delayed' : 'Pickup timing',
                      style: TextStyle(
                        color: color,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      order.pickupTimingLabel,
                      style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildPaymentCollectionCard() {
    final order = _order!;
    final amountText = formatCurrency(context, order.total);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: foodflow.line),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: foodflow.success.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  Icons.payments_rounded,
                  color: foodflow.success,
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
                      style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _isPaymentAlreadyPaid
                          ? 'Payment completed'
                          : order.isCodPayment
                              ? 'Amount to collect: $amountText'
                              : 'No cash collection required',
                      style: TextStyle(
                        color: foodflow.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w400,
                      ),
                    ),
                  ],
                ),
              ),
              if (order.isCodPayment && !_isPaymentAlreadyPaid)
                Text(
                  amountText,
                  style: TextStyle(
                    color: foodflow.ink,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
            ],
          ),
          if (order.cashCollectedAmount != null) ...[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: foodflow.success.withOpacity(0.10),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: foodflow.success.withOpacity(0.30)),
              ),
              child: Row(
                children: [
                  Icon(Icons.check_circle_rounded,
                      color: foodflow.success, size: 18),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Collected ${formatCurrency(context, order.cashCollectedAmount!)}'
                      '${order.cashCollectedAt != null ? ' • ${_formatTime(order.cashCollectedAt!)}' : ''}',
                      style: TextStyle(
                        color: foodflow.success,
                        fontWeight: FontWeight.w700,
                        fontSize: 12.5,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (!_isPaymentAlreadyPaid && order.isCodPayment) ...[
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
                onTap: _isUpdating ? null : _markCashReceived,
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                  decoration: BoxDecoration(
                    color: _cashCollected
                        ? foodflow.success.withOpacity(0.10)
                        : foodflow.canvas,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: _cashCollected
                          ? foodflow.success.withOpacity(0.36)
                          : foodflow.line,
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        _cashCollected
                            ? Icons.check_circle_rounded
                            : Icons.radio_button_unchecked_rounded,
                        color:
                            _cashCollected ? foodflow.success : foodflow.faint,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Cash collected from customer',
                          style: TextStyle(
                            color: _cashCollected
                                ? foodflow.success
                                : foodflow.ink,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ] else ...[
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _isGeneratingQr ? null : _generateQr,
                  icon: _isGeneratingQr
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Icon(Icons.qr_code_2_rounded),
                  label:
                      Text(_isGeneratingQr ? 'Generating...' : 'Generate QR'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: foodflow.ink,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
               Text(
                'Ask the customer to scan and complete the payment.',
                style: TextStyle(
                  color: foodflow.muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w400,
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
          color: selected ? foodflow.ink : foodflow.canvas,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: selected ? foodflow.ink : foodflow.line,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              color: selected ? Colors.white : foodflow.muted,
              size: 20,
            ),
            const SizedBox(width: 7),
            Text(
              title,
              style: TextStyle(
                color: selected ? Colors.white : foodflow.ink,
                fontWeight: FontWeight.w800,
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
        color: foodflow.orange.withOpacity(0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: foodflow.orange.withOpacity(0.22)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: foodflow.orange.withOpacity(0.12),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(
              Icons.password_rounded,
              color: foodflow.orange,
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
              style: TextStyle(
                color: foodflow.ink,
                fontSize: 18,
                fontWeight: FontWeight.w800,
                letterSpacing: 4,
              ),
              decoration:  InputDecoration(
                counterText: '',
                hintText: 'OTP',
                hintStyle: TextStyle(
                  color: foodflow.faint,
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
                  color: foodflow.surfaceColor,
                  borderRadius: BorderRadius.circular(11),
                  border: Border.all(color: foodflow.line),
                ),
                child: Icon(
                  Icons.refresh_rounded,
                  color: foodflow.ink,
                  size: 20,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _odContact({
    required IconData icon,
    required Color tint,
    required String title,
    required String subtitle,
    Widget? trailing,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 30,
          height: 30,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: tint.withOpacity(0.14),
            borderRadius: BorderRadius.circular(9),
          ),
          child: Icon(icon, size: 16, color: tint),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: foodflow.ink,
                  fontSize: 13.5,
                ),
              ),
              if (subtitle.trim().isNotEmpty)
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 12, color: foodflow.muted),
                ),
            ],
          ),
        ),
        if (trailing != null) trailing,
      ],
    );
  }

  Widget _buildSwipeAction() {
    final pending = _order?.isDriverAssignmentPending ?? false;
    return SwipeToConfirm(
      label: _bottomActionText.isEmpty ? 'Swipe to continue' : _bottomActionText,
      confirmedLabel: 'Done',
      loading: _isUpdating,
      accent: pending ? foodflow.orange : foodflow.success,
      onConfirmed: _runBottomAction,
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
        backgroundColor: foodflow.canvas,
        appBar: AppBar(title: const Text('Order Details')),
        body: NetworkErrorView(
          title: 'Unable to load order',
          message: _loadError ?? 'Order not found',
          onRetry: _loadOrder,
        ),
      );
    }

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Text(
          'Order #${_order!.orderNumber}',
          style: TextStyle(
            color: foodflow.ink,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        actions: [
          Container(
            margin: const EdgeInsets.only(right: 14),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
              color: _order!.paymentStatus == 'paid'
                  ? foodflow.success.withOpacity(0.14)
                  : foodflow.orange.withOpacity(0.14),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              _order!.paymentStatus.toUpperCase(),
              style: TextStyle(
                color: _order!.paymentStatus == 'paid'
                    ? foodflow.success
                    : foodflow.orange,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: _buildBottomStatusPanel(),
      body: Stack(
        children: [
          Positioned.fill(child: Stack(children: AuroraTheme.auroraBlobs())),
          ListView(
            padding: EdgeInsets.only(
              top: MediaQuery.paddingOf(context).top + 68,
              bottom: _hasPrimaryBottomAction ? 20 : 10,
            ),
            children: [
              if (!_order!.isDelivered && !_order!.isDeliveryFailed)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(22),
                    child: SizedBox(
                      height: 220,
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
                      myLocationEnabled: true,
                      myLocationButtonEnabled: false,
                      zoomControlsEnabled: false,
                      compassEnabled: false,
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
                            right: 12,
                            bottom: 12,
                            child: _mapNavPill(),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                child: _trackingStatusCard(),
              ),
              if (_order!.isOnTheWay) ...[
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                  child: _finishDeliveryCard(),
                ),
              ],
              if (_order!.isPartOfRouteBatch)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                  child: _batchInfoCard(),
                ),
              if (_order!.hasActivePreparationTimer ||
                  _order!.isPreparationDelayed)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                  child: GlassCard(child: _buildPickupTimingPanel(_order!)),
                ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                child: (_order!.isDelivered || _order!.isDeliveryFailed)
                    ? _deliverySummaryCard()
                    : _pickupDropCard(),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                child: _itemsCard(),
              ),
              const SizedBox(height: 28),
            ],
          ),
        ],
      ),
    );
  }

  Widget _mapNavPill() {
    return Material(
      color: foodflow.surfaceColor,
      elevation: 4,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: InkWell(
        onTap: _showNavigateDialog,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.navigation_rounded, size: 16, color: foodflow.orange),
              const SizedBox(width: 6),
              Text(
                'Navigate',
                style: TextStyle(
                  color: foodflow.ink,
                  fontWeight: FontWeight.w800,
                  fontSize: 12.5,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Prominent status card: current stage + hint + earning, with the
  /// order-flow stepper underneath. Doubles as the "delivered" success card.
  Widget _trackingStatusCard() {
    final o = _order!;
    final delivered = o.isDelivered;
    final accent = delivered ? foodflow.success : foodflow.orange;
    return GlassCard(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: accent.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  delivered ? Icons.check_circle_rounded : _getStatusIcon(),
                  color: accent,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _bottomStatusTitle,
                      style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      delivered
                          ? 'Earnings added to today'
                          : (_bottomActionHint.isEmpty
                              ? o.statusText
                              : _bottomActionHint),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: foodflow.muted, fontSize: 12),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    _driverEarningText(o),
                    style: TextStyle(
                      color: foodflow.success,
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    'earning',
                    style: TextStyle(color: foodflow.faint, fontSize: 10),
                  ),
                ],
              ),
            ],
          ),
          if (o.tipAmount > 0) ...[
            const SizedBox(height: 12),
            Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: foodflow.success.withOpacity(0.10),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                '🎉 + ${formatCurrency(context, o.tipAmount)} customer tip',
                style: TextStyle(
                  color: foodflow.success,
                  fontWeight: FontWeight.w700,
                  fontSize: 12.5,
                ),
              ),
            ),
          ],
          const SizedBox(height: 18),
          _orderFlowTracker(),
        ],
      ),
    );
  }

  /// Full "finish this delivery" flow — arrival, payment collection and OTP,
  /// laid out as clear numbered steps (was crammed into the bottom bar).
  Widget _finishDeliveryCard() {
    Widget step(int n, String title, Widget child) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 22,
                height: 22,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: foodflow.orange.withOpacity(0.14),
                  shape: BoxShape.circle,
                ),
                child: Text(
                  '$n',
                  style: TextStyle(
                    color: foodflow.orange,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 9),
              Text(
                title,
                style: TextStyle(
                  color: foodflow.ink,
                  fontSize: 13.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          child,
        ],
      );
    }

    return GlassCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Finish this delivery',
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 14),
          step(1, 'Reach the customer', _buildArrivalPanel()),
          const SizedBox(height: 16),
          step(2, 'Collect payment', _buildPaymentCollectionCard()),
          const SizedBox(height: 16),
          step(3, 'Verify delivery OTP', _buildOtpEntry()),
        ],
      ),
    );
  }

  Widget _batchInfoCard() {
    final b = _order!.routeBatch!;
    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.alt_route_rounded, color: foodflow.orange, size: 18),
              const SizedBox(width: 8),
              Text(
                'Grouped delivery route',
                style: TextStyle(
                  color: foodflow.ink,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Batch ${b.id} · ${b.ordersCount} matched orders',
            style: TextStyle(
              color: foodflow.inkSoft,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Orders: ${b.orderNumbers.join(', ')}',
            style: TextStyle(color: foodflow.muted, fontSize: 12),
          ),
          if (b.restaurants.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'Pickups: ${b.restaurants.join(', ')}',
                style: TextStyle(color: foodflow.muted, fontSize: 12),
              ),
            ),
        ],
      ),
    );
  }

  Widget _pickupDropCard() {
    final o = _order!;
    // Contact + navigation only make sense for the leg you're currently on.
    final storeActive = o.isReadyForPickup || o.isReachedPickup;
    final customerActive = o.isPickedUp || o.isOnTheWay;

    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Pickup & drop',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: foodflow.ink,
            ),
          ),
          const SizedBox(height: 14),
          _odContact(
            icon: Icons.storefront_rounded,
            tint: foodflow.orange,
            title: o.restaurant?.name ?? 'Restaurant',
            subtitle: o.restaurant?.address ?? '',
            trailing: storeActive
                ? Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      IconButton(
                        icon: Icon(Icons.call_rounded,
                            size: 20, color: foodflow.orange),
                        onPressed: _showCallDialog,
                        tooltip: 'Call store',
                      ),
                      IconButton(
                        icon: Icon(Icons.navigation_rounded,
                            size: 20, color: foodflow.orange),
                        onPressed: _showNavigateDialog,
                        tooltip: 'Navigate to store',
                      ),
                    ],
                  )
                : null,
          ),
          Padding(
            padding: const EdgeInsets.only(left: 15),
            child: SizedBox(
              height: 14,
              child: VerticalDivider(
                  color: foodflow.line, thickness: 2, width: 2),
            ),
          ),
          _odContact(
            icon: Icons.person_pin_circle_rounded,
            tint: foodflow.success,
            title: o.customerName,
            subtitle: o.deliveryAddress,
            trailing: customerActive
                ? Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      IconButton(
                        icon: Icon(Icons.call_rounded,
                            size: 20, color: foodflow.success),
                        onPressed: _showCallDialog,
                        tooltip: 'Call customer',
                      ),
                      IconButton(
                        icon: Icon(Icons.navigation_rounded,
                            size: 20, color: foodflow.success),
                        onPressed: _showNavigateDialog,
                        tooltip: 'Navigate to customer',
                      ),
                    ],
                  )
                : null,
          ),
        ],
      ),
    );
  }

  String _shortDateTime(DateTime v) {
    final l = v.toLocal();
    const m = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    return '${m[l.month - 1]} ${l.day}, ${_formatTime(v)}';
  }

  Widget _sumRow(String label, String value, {bool strong = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              color: foodflow.muted,
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(
                color: strong ? foodflow.success : foodflow.ink,
                fontSize: strong ? 15 : 12.5,
                fontWeight: strong ? FontWeight.w800 : FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// Read-only recap shown once the order is delivered / failed — no call or
  /// navigation actions, just what happened and what was earned.
  Widget _deliverySummaryCard() {
    final o = _order!;
    final failed = o.isDeliveryFailed;
    final grandTotal = o.totalDriverEarning + o.tipAmount;

    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            failed ? 'Delivery not completed' : 'Delivery summary',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: foodflow.ink,
            ),
          ),
          const SizedBox(height: 10),
          _sumRow(
            failed ? 'Reported' : 'Delivered',
            o.deliveredAt != null ? _shortDateTime(o.deliveredAt!) : '—',
          ),
          _sumRow('From', o.restaurant?.name ?? '—'),
          _sumRow('To', o.customerName),
          if (o.deliveryAddress.trim().isNotEmpty)
            _sumRow('Drop address', o.deliveryAddress),
          Divider(color: foodflow.line, height: 22),
          _sumRow('Payment', _paymentMethodLabel),
          if (o.isCodPayment)
            _sumRow(
              'Cash collected',
              o.cashCollectedAmount != null
                  ? '${formatCurrency(context, o.cashCollectedAmount!)}'
                      '${o.cashCollectedAt != null ? ' · ${_formatTime(o.cashCollectedAt!)}' : ''}'
                  : (o.isPaymentPaid ? 'Paid' : 'Not recorded'),
            ),
          if (!failed) ...[
            Divider(color: foodflow.line, height: 22),
            _sumRow('Delivery fee',
                formatCurrency(context, o.driverEarningAmount)),
            if (o.driverIncentiveAmount > 0)
              _sumRow('Incentive',
                  '+ ${formatCurrency(context, o.driverIncentiveAmount)}'),
            if (o.tipAmount > 0)
              _sumRow('Customer tip',
                  '+ ${formatCurrency(context, o.tipAmount)}'),
            const SizedBox(height: 4),
            _sumRow('You earned', formatCurrency(context, grandTotal),
                strong: true),
          ],
        ],
      ),
    );
  }

  Widget _itemsCard() {
    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Items',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: foodflow.ink,
                ),
              ),
              Text(
                '${_order!.items.length} item${_order!.items.length == 1 ? '' : 's'}',
                style: TextStyle(color: foodflow.muted, fontSize: 12),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ..._order!.items.map((item) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 7, vertical: 2),
                      decoration: BoxDecoration(
                        color: foodflow.orange.withOpacity(0.12),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        '${item.quantity}x',
                        style: TextStyle(
                          color: foodflow.orange,
                          fontWeight: FontWeight.w800,
                          fontSize: 12,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.name,
                            style: TextStyle(
                              fontSize: 13.5,
                              color: foodflow.ink,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          if (item.hasCustomizations)
                            Padding(
                              padding: const EdgeInsets.only(top: 2),
                              child: Text(
                                item.customizationSummary,
                                style: TextStyle(
                                  fontSize: 11.5,
                                  color: foodflow.muted,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              )),
          // Once delivered/failed the recap card already reports the cash line.
          if (_order!.isCodPayment &&
              !_order!.isDelivered &&
              !_order!.isDeliveryFailed) ...[
            Divider(color: foodflow.line, height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Amount to collect',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    color: foodflow.ink,
                  ),
                ),
                Text(
                  _order!.isPaymentPaid
                      ? 'Paid'
                      : formatCurrency(context, _order!.total),
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                    color: foodflow.success,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _DriverActionTarget {
  final String title;
  final String role;
  final String name;
  final String address;
  final LatLng? location;
  final IconData icon;
  final Color color;
  final bool callable;

  const _DriverActionTarget({
    required this.title,
    required this.role,
    required this.name,
    required this.address,
    required this.location,
    required this.icon,
    required this.color,
    required this.callable,
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
          color: foodflow.surfaceColor,
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
                        style: TextStyle(
                          color: foodflow.ink,
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        style: TextStyle(
                          color: foodflow.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: Icon(Icons.close_rounded),
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
    final color = action.enabled ? action.target.color : foodflow.faint;

    return Material(
      color: prominent
          ? action.target.color.withOpacity(0.08)
          : foodflow.canvas,
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
                  : foodflow.line,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: foodflow.surfaceColor,
                  borderRadius: BorderRadius.circular(13),
                  border: Border.all(color: foodflow.line),
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
                        color: action.enabled ? foodflow.ink : foodflow.faint,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      action.target.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: foodflow.inkSoft,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      action.value,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: action.enabled ? foodflow.muted : foodflow.faint,
                        fontSize: 12,
                        fontWeight: FontWeight.w400,
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
