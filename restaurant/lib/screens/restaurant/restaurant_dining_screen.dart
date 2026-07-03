import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
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

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    try {
      final results = await Future.wait([
        _api.get(ApiConstants.restaurantInfo),
        _api.get(ApiConstants.restaurantDiningStats),
        _api.get(
          ApiConstants.restaurantDiningBookings,
          queryParams: _status == 'all' ? null : {'status': _status},
        ),
      ]);

      final info = results[0]['data'] as Map<String, dynamic>? ?? {};
      final bookingData = results[2]['data'];
      setState(() {
        _restaurant = info;
        _chargeController.text = '${info['dining_charge'] ?? 0}';
        _stats = results[1]['data'] as Map<String, dynamic>? ?? {};
        _bookings = bookingData is Map<String, dynamic>
            ? (bookingData['data'] as List<dynamic>? ?? [])
            : (bookingData as List<dynamic>? ?? []);
        _isLoading = false;
      });
    } catch (e) {
      debugPrint('Dining management load failed: $e');
      setState(() => _isLoading = false);
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
    if (_isLoading) {
      return const Scaffold(
        backgroundColor: FoodFlowTheme.canvas,
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (!_isBothMapped) {
      return Scaffold(
        backgroundColor: FoodFlowTheme.canvas,
        appBar: AppBar(title: const Text('Dining Management')),
        body: Padding(
          padding: const EdgeInsets.all(18),
          child: Container(
            decoration: RestaurantPremium.panel(radius: 18),
            padding: const EdgeInsets.all(18),
            child: FoodFlowTheme.emptyState(
              icon: Icons.event_seat_outlined,
              title: 'Dining is not mapped',
              subtitle: 'Dining management is available only for restaurants mapped as both delivery and dining.',
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(title: const Text('Dining Management')),
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: ListView(
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            PremiumRestaurantHeader(
              title: 'Table Bookings',
              subtitle: 'Confirm reservations, manage covers, and keep the dining room moving.',
              icon: Icons.event_seat,
              trailing: Icon(Icons.restaurant, color: Colors.white.withOpacity(0.92), size: 34),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  Expanded(child: _metric('Pending', _stats['pending_bookings'], Colors.orange)),
                  const SizedBox(width: 10),
                  Expanded(child: _metric('Confirmed', _stats['confirmed_bookings'], Colors.blue)),
                  const SizedBox(width: 10),
                  Expanded(child: _metric('Guests', _stats['total_guests'], Colors.green)),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _settingsCard(),
            const SizedBox(height: 16),
            _filters(),
            const SizedBox(height: 10),
            ..._bookings.map((booking) => _bookingCard(Map<String, dynamic>.from(booking as Map))),
            if (_bookings.isEmpty)
              Padding(
                padding: const EdgeInsets.all(16),
                child: Container(
                  decoration: RestaurantPremium.panel(radius: 18),
                  child: FoodFlowTheme.emptyState(
                    icon: Icons.chair_outlined,
                    title: 'No bookings yet',
                    subtitle: 'New table requests will appear here instantly after refresh.',
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _metric(String title, dynamic value, Color color) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: RestaurantPremium.panel(radius: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.circle, size: 10, color: color),
          const SizedBox(height: 10),
          Text('${value ?? 0}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
          Text(title, style: const TextStyle(color: FoodFlowTheme.muted, fontWeight: FontWeight.w700, fontSize: 12)),
        ],
      ),
    );
  }

  Widget _settingsCard() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        decoration: RestaurantPremium.panel(radius: 18),
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: _chargeController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: 'Dining cover charge',
                  prefixText: currencyInputPrefix(context),
                ),
              ),
            ),
            const SizedBox(width: 12),
            ElevatedButton(
              onPressed: _isSaving ? null : _saveSettings,
              child: _isSaving
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Save'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _filters() {
    return SizedBox(
      height: 42,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        children: ['all', 'pending', 'confirmed', 'completed', 'cancelled'].map((status) {
          final selected = _status == status;
          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ChoiceChip(
              label: Text(status == 'all' ? 'All' : status[0].toUpperCase() + status.substring(1)),
              selected: selected,
              onSelected: (_) {
                setState(() => _status = status);
                _loadData();
              },
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _bookingCard(Map<String, dynamic> booking) {
    final user = booking['user'] is Map ? booking['user'] as Map : {};
    final status = booking['status']?.toString() ?? 'pending';
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: Container(
        decoration: RestaurantPremium.panel(radius: 18),
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: FoodFlowTheme.orange.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(Icons.event_seat, color: FoodFlowTheme.orange),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(user['name']?.toString() ?? 'Guest', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
                      Text('${booking['booking_date']} at ${booking['booking_time']} - ${booking['number_of_guests']} guests',
                          style: const TextStyle(color: FoodFlowTheme.muted, fontWeight: FontWeight.w700, fontSize: 12)),
                    ],
                  ),
                ),
                _statusBadge(status),
              ],
            ),
            if ((booking['special_requests']?.toString() ?? '').isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(booking['special_requests'].toString(), style: const TextStyle(color: FoodFlowTheme.ink)),
            ],
            const SizedBox(height: 12),
            Row(
              children: [
                if (status == 'pending') ...[
                  Expanded(child: OutlinedButton(onPressed: () => _updateBooking(booking['id'] as int, 'reject', reason: 'Unavailable'), child: const Text('Reject'))),
                  const SizedBox(width: 10),
                  Expanded(child: ElevatedButton(onPressed: () => _updateBooking(booking['id'] as int, 'confirm'), child: const Text('Confirm'))),
                ] else if (status == 'confirmed')
                  Expanded(child: ElevatedButton(onPressed: () => _updateBooking(booking['id'] as int, 'complete'), child: const Text('Mark Completed'))),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _statusBadge(String status) {
    final color = switch (status) {
      'confirmed' => Colors.blue,
      'completed' => Colors.green,
      'cancelled' => Colors.red,
      _ => Colors.orange,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
      child: Text(status.toUpperCase(), style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w900)),
    );
  }
}
