import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';

class RestaurantDiningScreen extends StatefulWidget {
  const RestaurantDiningScreen({super.key});

  @override
  State<RestaurantDiningScreen> createState() => _RestaurantDiningScreenState();
}

class _RestaurantDiningScreenState extends State<RestaurantDiningScreen> {
  final ApiService _api = ApiService();
  List<dynamic> _bookings = [];
  Map<String, dynamic> _stats = {};
  Map<String, dynamic>? _restaurant;
  bool _isLoading = true;
  bool _hasData = false;
  bool _isSaving = false;
  String _status = 'all';
  final TextEditingController _chargeController = TextEditingController();

  bool get _isBothMapped => _restaurant?['restaurant_type'] == 'both';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  @override
  void dispose() {
    _chargeController.dispose();
    super.dispose();
  }

  void _applyDining(dynamic info, dynamic stats, dynamic bookings) {
    final infoMap = (info is Map ? info['data'] : null) as Map<String, dynamic>? ?? {};
    final bookingData = bookings is Map ? bookings['data'] : null;
    _restaurant = infoMap;
    _chargeController.text = '${infoMap['dining_charge'] ?? 0}';
    _stats = (stats is Map ? stats['data'] : null) as Map<String, dynamic>? ?? {};
    _bookings = bookingData is Map<String, dynamic>
        ? (bookingData['data'] as List<dynamic>? ?? [])
        : (bookingData as List<dynamic>? ?? []);
    _hasData = true;
  }

  Future<void> _loadData() async {
    if (!_hasData) setState(() => _isLoading = true);
    final bookingParams = _status == 'all' ? null : {'status': _status};
    try {
      // Cache-first paint from whatever is stored locally.
      final cached = await Future.wait([
        _api.peekCache(ApiConstants.restaurantInfo),
        _api.peekCache(ApiConstants.restaurantDiningStats),
        _api.peekCache(ApiConstants.restaurantDiningBookings,
            queryParams: bookingParams),
      ]);
      if (mounted && cached[0] != null) {
        setState(() {
          _applyDining(cached[0], cached[1], cached[2]);
          _isLoading = false;
        });
      }

      final results = await Future.wait([
        _api.get(ApiConstants.restaurantInfo),
        _api.get(ApiConstants.restaurantDiningStats),
        _api.get(ApiConstants.restaurantDiningBookings,
            queryParams: bookingParams),
      ]);
      if (mounted) {
        setState(() {
          _applyDining(results[0], results[1], results[2]);
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint('Dining management load failed: $e');
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _updateBooking(int id, String action, {String? reason}) async {
    final endpoint = switch (action) {
      'confirm' => ApiConstants.restaurantConfirmDiningBooking(id),
      'complete' => ApiConstants.restaurantCompleteDiningBooking(id),
      'reject' => ApiConstants.restaurantRejectDiningBooking(id),
      _ => null,
    };
    if (endpoint == null) return;

    try {
      await _api.post(endpoint, data: action == 'reject' ? {'reason': reason ?? 'Unavailable'} : null);
      await _loadData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Booking ${action}ed')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not update booking: $e')),
        );
      }
    }
  }

  Future<void> _saveSettings() async {
    setState(() => _isSaving = true);
    try {
      await _api.post(ApiConstants.restaurantDiningSettings, data: {
        'dining_charge': double.tryParse(_chargeController.text.trim()) ?? 0,
        'accepts_dining': true,
      });
      await _loadData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Dining settings saved')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not save settings: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top + 60;

    if (_isLoading && !_hasData) {
      return Scaffold(
        backgroundColor: foodflow.canvas,
        body: Stack(children: [
          ...AuroraTheme.auroraBlobs(),
          const Center(child: CircularProgressIndicator()),
        ]),
      );
    }

    if (!_isBothMapped) {
      return Scaffold(
        backgroundColor: foodflow.canvas,
        extendBodyBehindAppBar: true,
        appBar: GlassAppBar(
          title: Text('Dining',
              style: TextStyle(
                  color: foodflow.ink,
                  fontSize: 17,
                  fontWeight: FontWeight.w900)),
        ),
        body: Stack(children: [
          ...AuroraTheme.auroraBlobs(),
          Padding(
            padding: EdgeInsets.fromLTRB(18, topPad + 20, 18, 18),
            child: Container(
              decoration: BoxDecoration(
                color: foodflow.surfaceColor,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: foodflow.line),
              ),
              padding: const EdgeInsets.all(22),
              child: FoodFlowTheme.emptyState(
                icon: Icons.event_seat_outlined,
                title: 'Dining is not enabled',
                subtitle:
                    'Dining management is available only for restaurants mapped as both delivery and dining.',
              ),
            ),
          ),
        ]),
      );
    }

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Dining',
                style: TextStyle(
                    color: foodflow.ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w900)),
            Text('${_stats['pending_bookings'] ?? 0} awaiting confirmation',
                style: TextStyle(
                    color: foodflow.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700)),
          ],
        ),
      ),
      body: Stack(
        children: [
          ...AuroraTheme.auroraBlobs(),
          RefreshIndicator(
            onRefresh: _loadData,
            child: ListView(
              padding: EdgeInsets.fromLTRB(16, topPad, 16, 28),
              children: [
                _diningHero(),
                const SizedBox(height: 12),
                _coverChargeCard(),
                const SizedBox(height: 14),
                _statusFilter(),
                const SizedBox(height: 4),
                ..._bookings.map((booking) =>
                    _bookingCard(Map<String, dynamic>.from(booking as Map))),
                if (_bookings.isEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 30),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          vertical: 30, horizontal: 20),
                      decoration: BoxDecoration(
                        color: foodflow.surfaceColor,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: foodflow.line),
                      ),
                      child: FoodFlowTheme.emptyState(
                        icon: Icons.chair_outlined,
                        title: 'No ${_status == 'all' ? '' : '$_status '}bookings',
                        subtitle:
                            'New table requests appear here as guests reserve.',
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _diningHero() {
    final guests = _stats['total_guests'] ?? 0;
    final pending = _stats['pending_bookings'] ?? 0;
    final confirmed = _stats['confirmed_bookings'] ?? 0;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: foodflow.brandGradient,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: foodflow.orange.withOpacity(0.26),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('COVERS EXPECTED',
            style: TextStyle(
                color: Colors.white.withOpacity(0.75),
                fontSize: 11,
                letterSpacing: 0.6,
                fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        Text('$guests',
            style: const TextStyle(
                color: Colors.white,
                fontSize: 40,
                height: 1,
                fontWeight: FontWeight.w900)),
        const SizedBox(height: 14),
        Row(children: [
          _heroCell('Pending', '$pending'),
          Container(width: 1, height: 28, color: Colors.white.withOpacity(0.22)),
          _heroCell('Confirmed', '$confirmed'),
        ]),
      ]),
    );
  }

  Widget _heroCell(String label, String value) => Expanded(
        child: Column(children: [
          Text(value,
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 20,
                  fontWeight: FontWeight.w900)),
          const SizedBox(height: 2),
          Text(label,
              style: TextStyle(
                  color: Colors.white.withOpacity(0.8),
                  fontSize: 11,
                  fontWeight: FontWeight.w700)),
        ]),
      );

  Widget _coverChargeCard() {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 6, 10, 6),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: foodflow.line),
      ),
      child: Row(
        children: [
          Icon(Icons.confirmation_number_outlined,
              size: 18, color: foodflow.muted),
          const SizedBox(width: 10),
          Expanded(
            child: TextField(
              controller: _chargeController,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                isDense: true,
                border: InputBorder.none,
                labelText: 'Dining cover charge',
                prefixText: currencyInputPrefix(context),
              ),
            ),
          ),
          const SizedBox(width: 8),
          TextButton(
            onPressed: _isSaving ? null : _saveSettings,
            child: _isSaving
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Save'),
          ),
        ],
      ),
    );
  }

  Widget _statusFilter() {
    const statuses = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];
    return SizedBox(
      height: 38,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: statuses.map((status) {
          final selected = _status == status;
          final label =
              status == 'all' ? 'All' : status[0].toUpperCase() + status.substring(1);
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: GestureDetector(
              onTap: () {
                setState(() => _status = status);
                _loadData();
              },
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 160),
                padding: const EdgeInsets.symmetric(horizontal: 16),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: selected ? foodflow.orange : foodflow.surfaceColor,
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(
                      color: selected ? foodflow.orange : foodflow.line),
                ),
                child: Text(label,
                    style: TextStyle(
                      color: selected ? Colors.white : foodflow.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    )),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _bookingCard(Map<String, dynamic> booking) {
    final user = booking['user'] is Map ? booking['user'] as Map : {};
    final status = booking['status']?.toString() ?? 'pending';
    final spine = _statusColor(status);
    final time = booking['booking_time']?.toString() ?? '--:--';
    final date = booking['booking_date']?.toString() ?? '';
    final guests = booking['number_of_guests'];
    final requests = booking['special_requests']?.toString() ?? '';

    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          color: foodflow.surfaceColor,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: foodflow.line),
        ),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(width: 4, color: spine),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(time,
                                  style: TextStyle(
                                      color: foodflow.ink,
                                      fontWeight: FontWeight.w900,
                                      fontSize: 20)),
                              Text(date,
                                  style: TextStyle(
                                      color: foodflow.muted,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700)),
                            ],
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(user['name']?.toString() ?? 'Guest',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                        color: foodflow.ink,
                                        fontWeight: FontWeight.w900,
                                        fontSize: 15)),
                                const SizedBox(height: 3),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: foodflow.orange.withOpacity(0.10),
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                  child: Text('$guests guests',
                                      style: TextStyle(
                                          color: foodflow.orange,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w800)),
                                ),
                              ],
                            ),
                          ),
                          _statusBadge(status),
                        ],
                      ),
                      if (requests.isNotEmpty) ...[
                        const SizedBox(height: 10),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: foodflow.canvas,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: foodflow.line),
                          ),
                          child: Text(requests,
                              style: TextStyle(
                                  color: foodflow.inkSoft,
                                  fontSize: 12,
                                  height: 1.3)),
                        ),
                      ],
                      if (status == 'pending' || status == 'confirmed') ...[
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            if (status == 'pending') ...[
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: () => _updateBooking(
                                      booking['id'] as int, 'reject',
                                      reason: 'Unavailable'),
                                  child: const Text('Reject'),
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: ElevatedButton(
                                  onPressed: () => _updateBooking(
                                      booking['id'] as int, 'confirm'),
                                  child: const Text('Confirm'),
                                ),
                              ),
                            ] else
                              Expanded(
                                child: ElevatedButton(
                                  onPressed: () => _updateBooking(
                                      booking['id'] as int, 'complete'),
                                  child: const Text('Mark completed'),
                                ),
                              ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _statusColor(String status) => switch (status) {
        'confirmed' => Colors.blue,
        'completed' => const Color(0xFF16A34A),
        'cancelled' => const Color(0xFFEF4444),
        _ => Colors.orange,
      };

  Widget _statusBadge(String status) {
    final color = _statusColor(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
          color: color.withOpacity(0.12),
          borderRadius: BorderRadius.circular(999)),
      child: Text(status.toUpperCase(),
          style: TextStyle(
              color: color, fontSize: 9.5, fontWeight: FontWeight.w900)),
    );
  }
}
