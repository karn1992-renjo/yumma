import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../config/api_constants.dart';
import '../../../../models/order.dart';
import '../../../../providers/auth_provider.dart';
import '../../../../providers/order_provider.dart';
import '../../../../services/api_service.dart';
import '../../../../services/websocket_service.dart';
import '../../../../utils/currency_utils.dart';
import '../../order_chat_screen.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';
import 'rating_v2.dart';

class OrderTrackingV2 extends StatefulWidget {
  const OrderTrackingV2({super.key, required this.orderId});

  final int orderId;

  @override
  State<OrderTrackingV2> createState() => _OrderTrackingV2State();
}

class _OrderTrackingV2State extends State<OrderTrackingV2> {
  static const _flow = <(String, String, IconData)>[
    ('pending', 'Order placed', Icons.receipt_long_rounded),
    ('confirmed', 'Confirmed by restaurant', Icons.verified_rounded),
    ('preparing', 'Preparing your food', Icons.soup_kitchen_rounded),
    ('ready_for_pickup', 'Ready for pickup', Icons.shopping_bag_rounded),
    ('picked_up', 'Picked up', Icons.delivery_dining_rounded),
    ('on_the_way', 'On the way', Icons.navigation_rounded),
    ('delivered', 'Delivered', Icons.check_circle_rounded),
  ];

  final ApiService _api = ApiService();
  bool _loading = true;
  bool _tipping = false;
  Order? _order;
  Map<String, dynamic> _track = const {};
  Timer? _fastTimer;
  Timer? _slowTimer;
  bool _feedbackPrompted = false;

  // realtime
  int? _wsUserId;
  String? _wsHandlerId;

  // map
  final Completer<GoogleMapController> _mapController = Completer();
  LatLng? _driver;
  LatLng? _dest;
  LatLng? _restaurant;
  DateTime? _driverUpdatedAt;
  bool _mapEverFitted = false;

  @override
  void initState() {
    super.initState();
    _load(initial: true);
    _fastTimer =
        Timer.periodic(const Duration(seconds: 8), (_) => _refreshTrack());
    _slowTimer =
        Timer.periodic(const Duration(seconds: 24), (_) => _load());
    _initRealtime();
  }

  @override
  void dispose() {
    _fastTimer?.cancel();
    _slowTimer?.cancel();
    if (_wsUserId != null) {
      WebSocketService().removeCustomerHandler(_wsUserId!, _wsHandlerId);
    }
    super.dispose();
  }

  Future<void> _initRealtime() async {
    for (var i = 0; i < 10 && mounted; i++) {
      final user = context.read<AuthProvider>().currentUser;
      if (user != null) {
        _wsUserId = user.id;
        _wsHandlerId = await WebSocketService().initCustomer(
          user.id,
          onOrderUpdate: (data) {
            if (!mounted) return;
            final id = int.tryParse(
                '${data['order_id'] ?? data['id'] ?? ''}');
            if (id != widget.orderId) return;
            _load();
            _refreshTrack();
          },
        );
        return;
      }
      await Future<void>.delayed(const Duration(milliseconds: 300));
    }
  }

  bool get _terminal =>
      _order?.isDelivered == true || _order?.isCancelled == true;

  Future<void> _load({bool initial = false}) async {
    if (initial && mounted) setState(() => _loading = true);
    try {
      final o =
          await context.read<OrderProvider>().fetchOrderDetails(widget.orderId);
      if (!mounted) return;
      setState(() {
        if (o != null) _order = o;
        _loading = false;
      });
      _syncGeometryFromOrder();
      _maybePromptFeedback();
      if (_terminal) {
        _fastTimer?.cancel();
        _slowTimer?.cancel();
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
    await _refreshTrack();
  }

  Future<void> _refreshTrack() async {
    if (_terminal) return;
    try {
      final t = await context.read<OrderProvider>().trackOrder(widget.orderId);
      if (t == null || !mounted) return;
      setState(() => _track = t);
      _syncGeometryFromTrack();
    } catch (_) {}
  }

  /// Post-delivery tip — the full amount goes to the delivery partner
  /// (wallet-funded, see POST /orders/{id}/tip).
  Future<void> _addTip(double amount) async {
    if (_tipping || amount <= 0) return;
    setState(() => _tipping = true);
    try {
      final res = await _api.post(
          ApiConstants.orderTip(widget.orderId), data: {'amount': amount});
      final ok = res is Map && res['success'] == true;
      if (!mounted) return;
      if (ok) {
        await _load();
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(const SnackBar(
              content: Text('Tip sent to your delivery partner. Thank you!')));
      } else {
        final msg = (res is Map ? res['message'] : null)?.toString() ??
            'Could not add the tip.';
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(msg)));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(
              content: Text(
                  e.toString().replaceFirst('Exception: ', ''))));
      }
    } finally {
      if (mounted) setState(() => _tipping = false);
    }
  }

  void _syncGeometryFromOrder() {
    final o = _order;
    if (o == null) return;
    if (o.deliveryLat != null && o.deliveryLng != null) {
      _dest = LatLng(o.deliveryLat!, o.deliveryLng!);
    }
    final r = o.restaurant;
    if (r != null && r.latitude != 0 && r.longitude != 0) {
      _restaurant = LatLng(r.latitude, r.longitude);
    }
    if (o.driverLat != null && o.driverLng != null) {
      _driver = LatLng(o.driverLat!, o.driverLng!);
    }
    _fitMap();
  }

  void _syncGeometryFromTrack() {
    final dl = _track['driver_location'];
    if (dl is Map) {
      final lat = _numOf(dl['lat'] ?? dl['latitude']);
      final lng = _numOf(dl['lng'] ?? dl['longitude']);
      if (lat != null && lng != null) {
        _driver = LatLng(lat, lng);
        final ts = dl['updated_at']?.toString();
        _driverUpdatedAt = ts != null ? DateTime.tryParse(ts) : DateTime.now();
        _animateToDriver();
      }
    }
    final pu = _track['pickup_location'];
    if (pu is Map) {
      final lat = _numOf(pu['latitude'] ?? pu['lat']);
      final lng = _numOf(pu['longitude'] ?? pu['lng']);
      if (lat != null && lng != null) _restaurant = LatLng(lat, lng);
    }
    _fitMap();
  }

  double? _numOf(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse('${v ?? ''}');

  Future<void> _fitMap() async {
    if (_mapEverFitted || !_mapController.isCompleted) return;
    final pts = [_driver, _dest, _restaurant].whereType<LatLng>().toList();
    if (pts.length < 2) return;
    _mapEverFitted = true;
    final c = await _mapController.future;
    final sw = LatLng(
      pts.map((p) => p.latitude).reduce((a, b) => a < b ? a : b),
      pts.map((p) => p.longitude).reduce((a, b) => a < b ? a : b),
    );
    final ne = LatLng(
      pts.map((p) => p.latitude).reduce((a, b) => a > b ? a : b),
      pts.map((p) => p.longitude).reduce((a, b) => a > b ? a : b),
    );
    try {
      await c.animateCamera(CameraUpdate.newLatLngBounds(
          LatLngBounds(southwest: sw, northeast: ne), 60));
    } catch (_) {}
  }

  Future<void> _animateToDriver() async {
    if (!_mapController.isCompleted || _driver == null) return;
    final c = await _mapController.future;
    try {
      await c.animateCamera(CameraUpdate.newLatLng(_driver!));
    } catch (_) {}
  }

  void _maybePromptFeedback() {
    final o = _order;
    if (o == null || _feedbackPrompted) return;
    if (o.isDelivered && o.needsFeedback) {
      _feedbackPrompted = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) showV2OrderFeedbackDialog(context, o);
      });
    }
  }

  int get _activeStep {
    final s = _order?.status ?? 'pending';
    if (s == 'cancelled') return -1;
    final i = _flow.indexWhere((e) => e.$1 == s);
    if (i >= 0) return i;
    const alias = {'reached_pickup': 3, 'accepted': 1};
    return alias[s] ?? 0;
  }

  String? get _etaText {
    final mins = _track['estimated_delivery_minutes'] ??
        (_track['eta'] is Map ? (_track['eta'] as Map)['eta_minutes'] : null);
    final label = _track['estimated_delivery_label'] ??
        (_track['eta'] is Map ? (_track['eta'] as Map)['eta_range'] : null);
    if (label != null && '$label'.trim().isNotEmpty) {
      return 'Arriving in $label';
    }
    if (mins != null) return 'Arriving in ~$mins min';
    return null;
  }

  Future<void> _call(String phone) async {
    final uri =
        Uri(scheme: 'tel', path: phone.replaceAll(RegExp(r'[^0-9+]'), ''));
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  bool _showMap(Order o) =>
      !o.isDelivered &&
      !o.isCancelled &&
      _dest != null &&
      (_driver != null || _restaurant != null);

  @override
  Widget build(BuildContext context) {
    final o = _order;
    return V2Scaffold(
      showBack: true,
      title: o == null ? 'Track order' : 'Order #${o.orderNumber}',
      body: Builder(
        builder: (context) {
          final p = V2Theme.of(context);
          if (_loading && o == null) {
            return Center(child: CircularProgressIndicator(color: p.accent));
          }
          if (o == null) {
            return V2EmptyState(
              icon: Icons.cloud_off_rounded,
              title: 'Could not load this order',
              onRetry: () => _load(initial: true),
            );
          }
          return RefreshIndicator(
            onRefresh: () => _load(),
            color: p.accent,
            backgroundColor: p.bgMid,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 6, 16, 40),
              children: [
                if (_showMap(o)) ...[
                  _LiveMap(
                    driver: _driver,
                    dest: _dest!,
                    restaurant: _restaurant,
                    updatedAt: _driverUpdatedAt,
                    onCreated: (c) {
                      if (!_mapController.isCompleted) {
                        _mapController.complete(c);
                      }
                      _fitMap();
                    },
                  ),
                  const SizedBox(height: 14),
                ],
                V2Entrance(
                  child: GlassPanel(
                    radius: 22,
                    strong: true,
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(o.statusText,
                            style: TextStyle(
                                color: p.ink,
                                fontSize: 18,
                                fontWeight: FontWeight.w900)),
                        if (_etaText != null &&
                            !o.isDelivered &&
                            !o.isCancelled) ...[
                          const SizedBox(height: 4),
                          Text(_etaText!,
                              style: TextStyle(
                                  color: p.accent,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700)),
                        ],
                        const SizedBox(height: 16),
                        if (o.isCancelled)
                          Text('This order was cancelled.',
                              style: TextStyle(
                                  color: p.danger,
                                  fontWeight: FontWeight.w600))
                        else
                          _Timeline(active: _activeStep, flow: _flow),
                      ],
                    ),
                  ),
                ),
                if (o.deliveryOtp != null &&
                    o.deliveryOtp!.isNotEmpty &&
                    !o.isDelivered &&
                    !o.isCancelled) ...[
                  const SizedBox(height: 14),
                  GlassPanel(
                    radius: 18,
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        Icon(Icons.lock_rounded, size: 18, color: p.accent),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                              'Share this OTP with your delivery partner',
                              style: TextStyle(
                                  color: p.inkSoft, fontSize: 12.5)),
                        ),
                        const SizedBox(width: 10),
                        for (final d in o.deliveryOtp!.split(''))
                          Container(
                            margin: const EdgeInsets.only(left: 5),
                            width: 30,
                            height: 38,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: p.accent.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(9),
                              border: Border.all(
                                  color: p.accent.withOpacity(0.4)),
                            ),
                            child: Text(d,
                                style: TextStyle(
                                    color: p.ink,
                                    fontSize: 18,
                                    fontWeight: FontWeight.w900)),
                          ),
                      ],
                    ),
                  ),
                ],
                if (o.driver != null && !o.isTakeaway) ...[
                  const SizedBox(height: 14),
                  GlassPanel(
                    radius: 18,
                    padding: const EdgeInsets.all(14),
                    child: Row(
                      children: [
                        CircleAvatar(
                          radius: 22,
                          backgroundColor: p.accent.withOpacity(0.14),
                          backgroundImage:
                              (o.driver!.profileImage ?? '').isNotEmpty
                                  ? NetworkImage(o.driver!.profileImage!)
                                  : null,
                          child: (o.driver!.profileImage ?? '').isEmpty
                              ? Icon(Icons.person_rounded, color: p.accent)
                              : null,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(o.driver!.name,
                                  style: TextStyle(
                                      color: p.ink,
                                      fontSize: 14,
                                      fontWeight: FontWeight.w800)),
                              Text('Your delivery partner',
                                  style: TextStyle(
                                      color: p.inkFaint, fontSize: 11.5)),
                            ],
                          ),
                        ),
                        _RoundBtn(
                          icon: Icons.chat_bubble_rounded,
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                                builder: (_) => OrderChatScreen(order: o)),
                          ),
                        ),
                        const SizedBox(width: 8),
                        if (o.driver!.phone.isNotEmpty)
                          _RoundBtn(
                            icon: Icons.call_rounded,
                            filled: true,
                            onTap: () => _call(o.driver!.phone),
                          ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                GlassPanel(
                  radius: 20,
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Order summary',
                          style: TextStyle(
                              color: p.inkSoft,
                              fontSize: 12.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.4)),
                      const SizedBox(height: 10),
                      for (final it in o.items)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 7),
                          child: Row(
                            children: [
                              Text('${it.quantity}×',
                                  style: TextStyle(
                                      color: p.accent,
                                      fontWeight: FontWeight.w800)),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(it.name,
                                    style: TextStyle(color: p.ink)),
                              ),
                              Text(
                                  formatCurrency(
                                      context, it.price * it.quantity),
                                  style: TextStyle(
                                      color: p.inkSoft,
                                      fontWeight: FontWeight.w700)),
                            ],
                          ),
                        ),
                      Divider(color: p.glassBorder, height: 22),
                      _row(context, 'Subtotal', o.subtotal),
                      if (o.deliveryFee > 0)
                        _row(context, 'Delivery fee', o.deliveryFee),
                      if (o.platformFee > 0)
                        _row(context, 'Platform fee', o.platformFee),
                      if (o.tax > 0) _row(context, 'Taxes', o.tax),
                      if (o.discount > 0)
                        _row(context, 'Discount', -o.discount),
                      if ((o.tip ?? 0) > 0) _row(context, 'Tip', o.tip!),
                      const SizedBox(height: 4),
                      _row(context, 'Total', o.total, bold: true),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                GlassPanel(
                  radius: 18,
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Delivering to',
                          style: TextStyle(
                              color: p.inkSoft,
                              fontSize: 12.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.4)),
                      const SizedBox(height: 8),
                      Text(o.deliveryAddress,
                          style: TextStyle(color: p.ink, fontSize: 13)),
                    ],
                  ),
                ),
                if (o.isDelivered &&
                    o.driver != null &&
                    (o.tip ?? 0) <= 0) ...[
                  const SizedBox(height: 14),
                  GlassPanel(
                    radius: 18,
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(Icons.volunteer_activism_rounded,
                                size: 17, color: p.accent),
                            const SizedBox(width: 8),
                            Text('Tip ${o.driver!.name.split(' ').first}',
                                style: TextStyle(
                                    color: p.ink,
                                    fontSize: 14,
                                    fontWeight: FontWeight.w900)),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                            'Say thanks — the full tip goes to your delivery '
                            'partner from your Yumma! wallet.',
                            style: TextStyle(
                                color: p.inkFaint, fontSize: 11.5)),
                        const SizedBox(height: 12),
                        _tipping
                            ? Center(
                                child: SizedBox(
                                  width: 22,
                                  height: 22,
                                  child: CircularProgressIndicator(
                                      strokeWidth: 2, color: p.accent),
                                ),
                              )
                            : Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: [
                                  for (final t in const [
                                    10.0,
                                    20.0,
                                    30.0,
                                    50.0
                                  ])
                                    V2Tappable(
                                      onTap: () => _addTip(t),
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(
                                            horizontal: 18, vertical: 9),
                                        decoration: BoxDecoration(
                                          color:
                                              p.accent.withOpacity(0.08),
                                          borderRadius:
                                              BorderRadius.circular(999),
                                          border: Border.all(
                                              color: p.accent
                                                  .withOpacity(0.4)),
                                        ),
                                        child: Text(
                                            formatCurrency(context, t),
                                            style: TextStyle(
                                                color: p.accent,
                                                fontSize: 13,
                                                fontWeight:
                                                    FontWeight.w900)),
                                      ),
                                    ),
                                ],
                              ),
                      ],
                    ),
                  ),
                ],
                if (o.isDelivered) ...[
                  const SizedBox(height: 16),
                  V2Tappable(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                          builder: (_) => RatingV2(order: o)),
                    ),
                    child: Container(
                      height: 52,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: p.accent,
                        borderRadius: BorderRadius.circular(15),
                      ),
                      child: Text(
                          o.needsFeedback
                              ? 'Rate your order'
                              : 'Update your rating',
                          style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w900)),
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _row(BuildContext context, String label, double value,
      {bool bold = false}) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        children: [
          Expanded(
            child: Text(label,
                style: TextStyle(
                  color: bold ? p.ink : p.inkSoft,
                  fontWeight: bold ? FontWeight.w900 : FontWeight.w600,
                  fontSize: bold ? 14.5 : 12.5,
                )),
          ),
          Text(formatCurrency(context, value),
              style: TextStyle(
                color: value < 0 ? p.positive : (bold ? p.ink : p.inkSoft),
                fontWeight: bold ? FontWeight.w900 : FontWeight.w700,
                fontSize: bold ? 14.5 : 12.5,
              )),
        ],
      ),
    );
  }
}

class _RoundBtn extends StatelessWidget {
  const _RoundBtn(
      {required this.icon, required this.onTap, this.filled = false});
  final IconData icon;
  final VoidCallback onTap;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: onTap,
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: filled ? p.accent : p.accent.withOpacity(0.12),
          shape: BoxShape.circle,
        ),
        child: Icon(icon,
            size: 18, color: filled ? Colors.white : p.accent),
      ),
    );
  }
}

class _LiveMap extends StatelessWidget {
  const _LiveMap({
    required this.driver,
    required this.dest,
    required this.restaurant,
    required this.updatedAt,
    required this.onCreated,
  });

  final LatLng? driver;
  final LatLng dest;
  final LatLng? restaurant;
  final DateTime? updatedAt;
  final void Function(GoogleMapController) onCreated;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final markers = <Marker>{
      Marker(
        markerId: const MarkerId('dest'),
        position: dest,
        icon:
            BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueGreen),
        infoWindow: const InfoWindow(title: 'Delivery address'),
      ),
    };
    if (driver != null) {
      markers.add(Marker(
        markerId: const MarkerId('driver'),
        position: driver!,
        icon: BitmapDescriptor.defaultMarkerWithHue(
            BitmapDescriptor.hueOrange),
        infoWindow: const InfoWindow(title: 'Delivery partner'),
        anchor: const Offset(0.5, 0.5),
      ));
    }
    if (restaurant != null) {
      markers.add(Marker(
        markerId: const MarkerId('restaurant'),
        position: restaurant!,
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueRed),
        infoWindow: const InfoWindow(title: 'Restaurant'),
      ));
    }
    final polylines = <Polyline>{};
    final routeStart = driver ?? restaurant;
    if (routeStart != null) {
      polylines.add(Polyline(
        polylineId: const PolylineId('route'),
        points: [routeStart, dest],
        color: p.accent,
        width: 4,
        patterns: [PatternItem.dash(18), PatternItem.gap(10)],
      ));
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: SizedBox(
        height: 240,
        child: Stack(
          children: [
            GoogleMap(
              initialCameraPosition: CameraPosition(
                target: driver ?? restaurant ?? dest,
                zoom: 14,
              ),
              markers: markers,
              polylines: polylines,
              myLocationButtonEnabled: false,
              zoomControlsEnabled: false,
              compassEnabled: false,
              onMapCreated: onCreated,
            ),
            Positioned(
              left: 12,
              top: 12,
              child: GlassPanel(
                radius: 12,
                strong: true,
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _Pulse(color: driver != null ? p.positive : p.inkFaint),
                    const SizedBox(width: 7),
                    Text(
                      driver != null
                          ? 'Live · partner on the move'
                          : 'Waiting for delivery partner',
                      style: TextStyle(
                          color: p.ink,
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700),
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
}

class _Pulse extends StatefulWidget {
  const _Pulse({required this.color});
  final Color color;

  @override
  State<_Pulse> createState() => _PulseState();
}

class _PulseState extends State<_Pulse>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween<double>(begin: 0.35, end: 1).animate(_c),
      child: Container(
        width: 9,
        height: 9,
        decoration:
            BoxDecoration(color: widget.color, shape: BoxShape.circle),
      ),
    );
  }
}

class _Timeline extends StatelessWidget {
  const _Timeline({required this.active, required this.flow});

  final int active;
  final List<(String, String, IconData)> flow;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Column(
      children: [
        for (var i = 0; i < flow.length; i++)
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Column(
                  children: [
                    Container(
                      width: 26,
                      height: 26,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: i <= active ? p.accent : Colors.transparent,
                        border: Border.all(
                          color: i <= active ? p.accent : p.glassBorder,
                          width: 2,
                        ),
                      ),
                      child: Icon(
                        flow[i].$3,
                        size: 13,
                        color: i <= active ? Colors.white : p.inkFaint,
                      ),
                    ),
                    if (i != flow.length - 1)
                      Expanded(
                        child: Container(
                          width: 2,
                          color: i < active ? p.accent : p.glassBorder,
                        ),
                      ),
                  ],
                ),
                const SizedBox(width: 12),
                Padding(
                  padding: EdgeInsets.only(
                      top: 4, bottom: i == flow.length - 1 ? 0 : 18),
                  child: Text(
                    flow[i].$2,
                    style: TextStyle(
                      color: i <= active ? p.ink : p.inkFaint,
                      fontWeight:
                          i == active ? FontWeight.w900 : FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
