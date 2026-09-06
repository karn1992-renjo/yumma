// lib/screens/driver/driver_gigs_screen.dart
import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../widgets/aurora/swipe_to_confirm.dart';
import '../../utils/currency_utils.dart';

class DriverGigsScreen extends StatefulWidget {
  const DriverGigsScreen({Key? key}) : super(key: key);

  @override
  State<DriverGigsScreen> createState() => _DriverGigsScreenState();
}

class _DriverGigsScreenState extends State<DriverGigsScreen> {
  final ApiService _api = ApiService();

  List<dynamic> _availableGigs = [];
  List<dynamic> _myGigs = [];
  bool _isLoading = true;
  DateTime? _selectedDate;

  @override
  void initState() {
    super.initState();
    _loadGigs();
  }

  Future<void> _loadGigs() async {
    setState(() => _isLoading = true);

    try {
      final availableResponse = await _api.get(ApiConstants.driverGigs,
          queryParams: _gigQueryParams('available'));
      final bookedResponse = await _api.get(ApiConstants.driverGigs,
          queryParams: _gigQueryParams('booked'));
      final completedResponse = await _api.get(ApiConstants.driverGigs,
          queryParams: _gigQueryParams('completed'));

      if (availableResponse['success'] == true) {
        if (!mounted) return;
        setState(() {
          _availableGigs = availableResponse['data'] ?? [];
        });
      }

      if (bookedResponse['success'] == true ||
          completedResponse['success'] == true) {
        if (!mounted) return;
        setState(() {
          _myGigs = [
            ..._extractGigs(bookedResponse),
            ..._extractGigs(completedResponse),
          ]..sort(_compareGigsByDateTime);
        });
      }
    } catch (e) {
      debugPrint('Load gigs error: $e');
    }

    if (!mounted) return;
    setState(() => _isLoading = false);
  }

  Map<String, dynamic> _gigQueryParams(String status) {
    return {
      'status': status,
      if (_selectedDate != null)
        'date': DateFormat('yyyy-MM-dd').format(_selectedDate!),
    };
  }

  List<dynamic> _extractGigs(dynamic response) {
    if (response is Map && response['data'] is List) {
      return List<dynamic>.from(response['data'] as List);
    }
    return [];
  }

  DateTime? _parseDateTime(dynamic value) {
    if (value == null) return null;
    return DateTime.tryParse(value.toString());
  }

  String _formatGigDate(Map<String, dynamic> gig) {
    final dateShort = gig['date_short']?.toString().trim();
    if (dateShort != null && dateShort.isNotEmpty) {
      return dateShort;
    }

    final date = _parseDateTime(gig['date']);
    if (date == null) return '';
    return DateFormat('d MMM').format(date);
  }

  String _formatGigTimeRange(Map<String, dynamic> gig) {
    final apiTimeRange = gig['time_range']?.toString().trim();
    if (apiTimeRange != null && apiTimeRange.isNotEmpty) {
      return apiTimeRange;
    }

    final startTime = _parseDateTime(gig['slot_start_local']) ??
        _parseDateTime(gig['start_time']);
    final endTime = _parseDateTime(gig['slot_end_local']) ??
        _parseDateTime(gig['end_time']);
    if (startTime == null || endTime == null) return '';
    return '${DateFormat('hh:mm a').format(startTime)} - ${DateFormat('hh:mm a').format(endTime)}';
  }

  String get _dateFilterLabel {
    if (_selectedDate == null) return 'All upcoming dates';
    return DateFormat('EEE, d MMM').format(_selectedDate!);
  }

  Future<void> _selectDate() async {
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: _selectedDate ?? now,
      firstDate: now.subtract(const Duration(days: 90)),
      lastDate: now.add(const Duration(days: 365)),
    );

    if (selected == null || !mounted) return;
    setState(() => _selectedDate = selected);
    await _loadGigs();
  }

  Future<void> _clearDateFilter() async {
    if (_selectedDate == null) return;
    setState(() => _selectedDate = null);
    await _loadGigs();
  }

  int _compareGigsByDateTime(dynamic first, dynamic second) {
    if (first is! Map || second is! Map) return 0;
    final firstDate = _parseDateTime(first['slot_start_local']) ??
        _parseDateTime(first['start_time']) ??
        _parseDateTime(first['date']) ??
        DateTime.fromMillisecondsSinceEpoch(0);
    final secondDate = _parseDateTime(second['slot_start_local']) ??
        _parseDateTime(second['start_time']) ??
        _parseDateTime(second['date']) ??
        DateTime.fromMillisecondsSinceEpoch(0);
    return firstDate.compareTo(secondDate);
  }

  Future<bool> _bookGig(int gigId) async {
    try {
      final response =
          await _api.post('${ApiConstants.driverGigs}/$gigId/book');
      return response['success'] == true;
    } catch (e) {
      debugPrint('Book gig error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to book gig: $e')),
        );
      }
      return false;
    }
  }

  /// Opens the redesigned gig detail / booking sheet.
  Future<void> _openGig(
    Map<String, dynamic> gig, {
    required bool booked,
    required bool completed,
  }) async {
    final result = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.black.withOpacity(0.45),
      builder: (_) => _GigDetailSheet(
        gig: gig,
        booked: booked,
        completed: completed,
        money: _money,
        dateLabel: _formatGigDate(gig),
        timeLabel: _formatGigTimeRange(gig),
        surge: _surgeMultiplier(gig),
        projectedOrders: _projectedOrdersPerDriver(gig),
        forecastOrders: _forecastedOrders(gig),
        onBook: () => _bookGig((gig['id'] as num).toInt()),
      ),
    );

    if (!mounted) return;

    if (result == 'booked') {
      await _loadGigs();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Gig booked. See you in your slot!')),
        );
        Navigator.pop(context, true);
      }
    } else if (result == 'dispute') {
      final bookingId = gig['driver_booking_id'];
      if (bookingId is int) _disputeGig(bookingId);
    }
  }

  Future<void> _disputeGig(int bookingId) async {
    final reasonController = TextEditingController();
    final messageController = TextEditingController();

    final submitted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Report an issue'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: reasonController,
              maxLength: 120,
              decoration: const InputDecoration(
                labelText: 'Reason',
                hintText: 'e.g. Earnings not credited',
              ),
            ),
            TextField(
              controller: messageController,
              maxLength: 2000,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Details (optional)',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Submit'),
          ),
        ],
      ),
    );

    if (submitted != true || !mounted) return;

    final reason = reasonController.text.trim();
    if (reason.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a reason.')),
      );
      return;
    }

    try {
      final response = await _api.post(
        '${ApiConstants.driverGigs}/$bookingId/dispute',
        data: {
          'reason': reason,
          if (messageController.text.trim().isNotEmpty)
            'message': messageController.text.trim(),
        },
      );
      if (!mounted) return;
      if (response['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Dispute submitted. Our team will review it.')),
        );
      }
    } catch (e) {
      debugPrint('Dispute gig error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to submit dispute: $e')),
        );
      }
    }
  }

  double? _surgeMultiplier(Map<String, dynamic> gig) {
    final value = gig['surge_multiplier'];
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  int? _forecastedOrders(Map<String, dynamic> gig) {
    final value = gig['forecasted_orders'];
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  /// Orders the demand forecast projects for a single driver on this slot --
  /// what the "Potential earning" figure is actually based on.
  int? _projectedOrdersPerDriver(Map<String, dynamic> gig) {
    final value = gig['projected_orders_per_driver'];
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  /// Only shown when a slot is actually predicted to be busier than usual --
  /// most slots have no meaningful surge, so we skip the badge for those
  /// rather than showing "Normal demand" on every single card.
  Widget? _demandBadge(Map<String, dynamic> gig) {
    final surge = _surgeMultiplier(gig);
    if (surge == null || surge < 1.15) return null;

    final isHigh = surge >= 1.3;
    final color = isHigh ? Colors.red.shade700 : Colors.amber.shade800;
    final background = isHigh ? Colors.red.shade50 : Colors.amber.shade50;
    final label = isHigh ? 'High demand' : 'Busy';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.trending_up_rounded, size: 12, color: color),
          const SizedBox(width: 3),
          Text(
            label,
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
        ],
      ),
    );
  }

  String _money(dynamic value) {
    if (value is num) {
      return formatCurrencyValue(context, value.toDouble());
    }
    return formatCurrencyValue(
        context, double.tryParse(value?.toString() ?? '') ?? 0);
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: AuroraScaffold(
        appBar: const GlassAppBar(
          title: Text('Delivery Gigs'),
          bottom: TabBar(
            tabs: [
              Tab(text: 'Available Gigs'),
              Tab(text: 'My Gigs'),
            ],
          ),
        ),
        body: Column(
          children: [
            _buildDateFilter(),
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : TabBarView(
                      children: [
                        _buildAvailableGigsTab(),
                        _buildMyGigsTab(),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDateFilter() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      child: Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _selectDate,
              icon: const Icon(Icons.calendar_today_rounded, size: 18),
              label: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  _dateFilterLabel,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: foodflow.ink,
                backgroundColor: foodflow.surfaceColor,
                side: BorderSide(color: foodflow.line),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              ),
            ),
          ),
          if (_selectedDate != null) ...[
            const SizedBox(width: 8),
            IconButton.filledTonal(
              onPressed: _clearDateFilter,
              icon: const Icon(Icons.close_rounded),
              tooltip: 'Clear date',
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildAvailableGigsTab() {
    if (_availableGigs.isEmpty) {
      return foodflow.emptyState(
        icon: Icons.event_busy,
        title: 'No available gigs',
        subtitle: _selectedDate == null
            ? 'Open delivery slots will show up here.'
            : 'No open delivery slots for $_dateFilterLabel.',
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _availableGigs.length,
      itemBuilder: (context, index) {
        final gig = _availableGigs[index];
        final gigMap = Map<String, dynamic>.from(gig as Map);
        final dateLabel = _formatGigDate(gigMap);
        final timeLabel = _formatGigTimeRange(gigMap);
        final title = gig['title']?.toString().trim().isNotEmpty == true
            ? gig['title'].toString()
            : 'Open delivery slot';
        final description = gig['description']?.toString() ?? '';

        return AuroraEntrance(
          delay: Duration(milliseconds: 30 * index.clamp(0, 8)),
          child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () => _openGig(gigMap, booked: false, completed: false),
          child: Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(16),
          decoration: foodflow.surface(radius: 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: foodflow.orange.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      Icons.delivery_dining,
                      color: foodflow.orange,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Flexible(
                              child: Text(
                                title,
                                overflow: TextOverflow.ellipsis,
                                style:  TextStyle(
                                  color: foodflow.ink,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 16,
                                ),
                              ),
                            ),
                            if (_demandBadge(gigMap) != null) ...[
                              const SizedBox(width: 6),
                              _demandBadge(gigMap)!,
                            ],
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          [dateLabel, timeLabel]
                              .where((label) => label.isNotEmpty)
                              .join(' - '),
                          style:  TextStyle(
                            fontSize: 14,
                            color: foodflow.muted,
                            fontWeight: FontWeight.w400,
                          ),
                        ),
                        if (description.isNotEmpty) ...[
                          const SizedBox(height: 4),
                          Text(
                            description,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style:  TextStyle(
                              fontSize: 12,
                              color: foodflow.muted,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  Icon(Icons.chevron_right_rounded, color: foodflow.muted),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Icon(Icons.location_on, size: 16, color: foodflow.muted),
                  const SizedBox(width: 4),
                  Text(
                    (gig['area']?['name'] ?? 'Delivery Area').toString(),
                    style:  TextStyle(
                      fontSize: 12,
                      color: foodflow.muted,
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _InfoChip(
                    icon: Icons.timer_outlined,
                    label: '${gig['min_login_minutes'] ?? 0} min login',
                  ),
                  _InfoChip(
                    icon: Icons.shopping_bag_outlined,
                    label: '${gig['min_orders_required'] ?? 0} orders',
                  ),
                  _InfoChip(
                    icon: Icons.cancel_outlined,
                    label:
                        'Max ${gig['max_cancellations_allowed'] ?? 0} cancels',
                  ),
                  _InfoChip(
                    icon: Icons.groups_2_outlined,
                    label:
                        '${gig['available_seats'] ?? 1}/${gig['capacity'] ?? 1} seats left',
                  ),
                  if ((_projectedOrdersPerDriver(gigMap) ?? 0) > 0)
                    _InfoChip(
                      icon: Icons.insights_outlined,
                      label:
                          '~${_projectedOrdersPerDriver(gigMap)} orders for you',
                    )
                  else if ((_forecastedOrders(gigMap) ?? 0) > 0)
                    _InfoChip(
                      icon: Icons.insights_outlined,
                      label: '~${_forecastedOrders(gigMap)} orders expected',
                    ),
                ],
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.monetization_on,
                            size: 16, color: Colors.green),
                        const SizedBox(width: 4),
                        Text(
                          'Potential earning: ${_money(gig['estimated_earning'])}',
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w400,
                            color: Colors.green,
                          ),
                        ),
                      ],
                    ),
                    if ((_projectedOrdersPerDriver(gigMap) ?? 0) > 0) ...[
                      const SizedBox(height: 2),
                      Text(
                        'Based on ~${_projectedOrdersPerDriver(gigMap)} orders forecast for this slot'
                        '${(_surgeMultiplier(gigMap) ?? 1) >= 1.15 ? ' + ${_surgeMultiplier(gigMap)!.toStringAsFixed(1)}x surge' : ''}',
                        style: TextStyle(
                          fontSize: 11,
                          color: Colors.green.shade700,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if ((gig['terms_conditions']?.toString() ?? '').isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  gig['terms_conditions'].toString(),
                  style:  TextStyle(
                    fontSize: 12,
                    color: foodflow.muted,
                  ),
                ),
              ],
            ],
          ),
        ),
        ),
        );
      },
    );
  }

  Widget _buildMyGigsTab() {
    if (_myGigs.isEmpty) {
      return foodflow.emptyState(
        icon: Icons.calendar_today,
        title: 'No booked gigs',
        subtitle: _selectedDate == null
            ? 'Booked and completed slots will appear here.'
            : 'No booked or completed slots for $_dateFilterLabel.',
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _myGigs.length,
      itemBuilder: (context, index) {
        final gig = _myGigs[index];
        final gigMap = Map<String, dynamic>.from(gig as Map);
        final dateLabel = _formatGigDate(gigMap);
        final timeLabel = _formatGigTimeRange(gigMap);
        final isCompleted = gig['status'] == 'completed';
        final title = gig['title']?.toString().trim().isNotEmpty == true
            ? gig['title'].toString()
            : 'Gig slot';

        return AuroraEntrance(
          delay: Duration(milliseconds: 30 * index.clamp(0, 8)),
          child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () =>
              _openGig(gigMap, booked: true, completed: isCompleted),
          child: Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(16),
          decoration: foodflow.surface(radius: 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: (isCompleted ? Colors.green : Colors.orange)
                          .withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      isCompleted ? Icons.check_circle : Icons.schedule,
                      color: isCompleted ? Colors.green : Colors.orange,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style:  TextStyle(
                            color: foodflow.ink,
                            fontWeight: FontWeight.w800,
                            fontSize: 16,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          [dateLabel, timeLabel]
                              .where((label) => label.isNotEmpty)
                              .join(' - '),
                          style:  TextStyle(
                            fontSize: 14,
                            color: foodflow.muted,
                            fontWeight: FontWeight.w400,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: isCompleted
                          ? Colors.green.shade100
                          : Colors.orange.shade100,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      isCompleted ? 'Completed' : 'Booked',
                      style: TextStyle(
                        fontSize: 12,
                        color: isCompleted ? Colors.green : Colors.orange,
                        fontWeight: FontWeight.w400,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Icon(Icons.location_on, size: 16, color: foodflow.muted),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      (gig['area']?['name'] ?? 'Delivery Area').toString(),
                      style: TextStyle(fontSize: 12, color: foodflow.muted),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.monetization_on,
                        size: 16, color: Colors.green),
                    const SizedBox(width: 4),
                    Text(
                      'Earned: ${_money(gig['actual_earning'] ?? gig['estimated_earning'])}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w400,
                        color: Colors.green,
                      ),
                    ),
                  ],
                ),
              ),
              if (isCompleted && gig['driver_booking_id'] != null) ...[
                const SizedBox(height: 8),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed: () =>
                        _disputeGig(gig['driver_booking_id'] as int),
                    icon: const Icon(Icons.flag_outlined, size: 16),
                    label: const Text('Report an issue'),
                  ),
                ),
              ],
            ],
          ),
        ),
        ),
        );
      },
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;

  const _InfoChip({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.orange.shade50,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: Colors.orange.shade700),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              color: Colors.orange.shade700,
              fontWeight: FontWeight.w400,
            ),
          ),
        ],
      ),
    );
  }
}

/// Redesigned gig detail + booking modal. Opened from either gig tab; returns
/// 'booked' after a successful booking or 'dispute' to raise an issue.
class _GigDetailSheet extends StatefulWidget {
  const _GigDetailSheet({
    required this.gig,
    required this.booked,
    required this.completed,
    required this.money,
    required this.dateLabel,
    required this.timeLabel,
    required this.onBook,
    this.surge,
    this.projectedOrders,
    this.forecastOrders,
  });

  final Map<String, dynamic> gig;
  final bool booked;
  final bool completed;
  final String Function(dynamic) money;
  final String dateLabel;
  final String timeLabel;
  final Future<bool> Function() onBook;
  final double? surge;
  final int? projectedOrders;
  final int? forecastOrders;

  @override
  State<_GigDetailSheet> createState() => _GigDetailSheetState();
}

class _GigDetailSheetState extends State<_GigDetailSheet> {
  bool _booking = false;

  double _n(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0;
  int _i(dynamic v) =>
      v is num ? v.toInt() : int.tryParse(v?.toString() ?? '') ?? 0;

  Future<void> _confirm() async {
    setState(() => _booking = true);
    final ok = await widget.onBook();
    if (!mounted) return;
    setState(() => _booking = false);
    if (ok) {
      Navigator.pop(context, 'booked');
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not book this slot.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final gig = widget.gig;
    final title = (gig['title']?.toString().trim().isNotEmpty ?? false)
        ? gig['title'].toString()
        : 'Delivery slot';
    final area = (gig['area']?['name'] ?? '').toString();
    final terms = (gig['terms_conditions']?.toString() ?? '').trim();

    final basePay = _n(gig['base_pay']);
    final orderIncentive = _n(gig['order_incentive']);
    final loginIncentive = _n(gig['login_incentive']);
    final minOrders = _i(gig['min_orders_required']);
    final minLogin = _i(gig['min_login_minutes']);
    final maxCancels = _i(gig['max_cancellations_allowed']);
    final seatsLeft = _i(gig['available_seats']);
    final capacity = _i(gig['capacity']);
    final surge = widget.surge ?? 1;
    final ordersForEstimate =
        (widget.projectedOrders ?? 0) > 0 ? widget.projectedOrders! : minOrders;
    final total = _n(gig['estimated_earning']);
    final earned = gig['actual_earning'];

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: ClipRRect(
        borderRadius: const BorderRadius.vertical(top: Radius.circular(26)),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
          child: Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.88,
            ),
            decoration: BoxDecoration(
              color: foodflow.canvas.withOpacity(foodflow.isDark ? 0.92 : 0.96),
              border: Border(top: BorderSide(color: foodflow.glassBorder)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const SizedBox(height: 10),
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: foodflow.muted.withOpacity(0.4),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                Flexible(
                  child: ListView(
                    shrinkWrap: true,
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 44,
                            height: 44,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: foodflow.orange.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(13),
                            ),
                            child: Icon(Icons.delivery_dining_rounded,
                                color: foodflow.orange),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  title,
                                  style: TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.w900,
                                    color: foodflow.ink,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  [widget.dateLabel, widget.timeLabel]
                                      .where((s) => s.isNotEmpty)
                                      .join('  ·  '),
                                  style: TextStyle(
                                      fontSize: 13, color: foodflow.muted),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: () => Navigator.pop(context),
                            icon: Icon(Icons.close_rounded,
                                color: foodflow.muted),
                          ),
                        ],
                      ),
                      if (area.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            Icon(Icons.location_on_rounded,
                                size: 15, color: foodflow.muted),
                            const SizedBox(width: 4),
                            Text(area,
                                style: TextStyle(
                                    fontSize: 13, color: foodflow.muted)),
                            if (surge >= 1.15) ...[
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: foodflow.orange.withOpacity(0.14),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  '${surge.toStringAsFixed(1)}x demand',
                                  style: TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w800,
                                    color: foodflow.orange,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ],
                      const SizedBox(height: 16),
                      GlassCard(
                        solid: true,
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.completed
                                  ? 'You earned'
                                  : 'You could earn',
                              style: TextStyle(
                                  fontSize: 12.5, color: foodflow.muted),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              widget.money(widget.completed && earned != null
                                  ? earned
                                  : total),
                              style: TextStyle(
                                fontSize: 30,
                                fontWeight: FontWeight.w900,
                                color: foodflow.success,
                              ),
                            ),
                            if (!widget.completed &&
                                (widget.projectedOrders ?? 0) > 0) ...[
                              const SizedBox(height: 2),
                              Text(
                                'Forecast ~${widget.forecastOrders ?? 0} orders this slot · ~${widget.projectedOrders} for you',
                                style: TextStyle(
                                    fontSize: 11.5, color: foodflow.muted),
                              ),
                            ],
                            const SizedBox(height: 12),
                            _row('Base pay', widget.money(basePay)),
                            _row(
                              'Order incentive${ordersForEstimate > 0 ? '  (${widget.money(orderIncentive)} × ~$ordersForEstimate)' : ''}',
                              widget.money(orderIncentive * ordersForEstimate),
                            ),
                            _row('Login incentive',
                                widget.money(loginIncentive)),
                            if (surge > 1)
                              _row('Peak demand',
                                  '× ${surge.toStringAsFixed(2)}'),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      GlassCard(
                        solid: true,
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'To get paid for this slot',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: foodflow.ink,
                              ),
                            ),
                            const SizedBox(height: 10),
                            _req(Icons.timer_outlined,
                                'Stay online at least $minLogin min'),
                            _req(Icons.shopping_bag_outlined,
                                'Complete at least $minOrders order(s)'),
                            _req(Icons.cancel_outlined,
                                'No more than $maxCancels cancellation(s)'),
                            if (!widget.booked)
                              _req(Icons.groups_2_outlined,
                                  '$seatsLeft of $capacity seats left'),
                          ],
                        ),
                      ),
                      if (terms.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        Text(
                          terms,
                          style: TextStyle(
                              fontSize: 12,
                              color: foodflow.muted,
                              height: 1.4),
                        ),
                      ],
                      const SizedBox(height: 4),
                    ],
                  ),
                ),
                Container(
                  padding: EdgeInsets.fromLTRB(
                      20, 12, 20, 16 + MediaQuery.of(context).padding.bottom),
                  decoration: BoxDecoration(
                    border:
                        Border(top: BorderSide(color: foodflow.glassBorder)),
                  ),
                  child: _footer(),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _footer() {
    if (widget.completed) {
      return Row(
        children: [
          Expanded(
            child: Text('Slot completed',
                style: TextStyle(
                    fontWeight: FontWeight.w800, color: foodflow.ink)),
          ),
          if (widget.gig['driver_booking_id'] != null)
            TextButton.icon(
              onPressed: () => Navigator.pop(context, 'dispute'),
              icon: const Icon(Icons.flag_outlined, size: 16),
              label: const Text('Report an issue'),
            ),
        ],
      );
    }

    if (widget.booked) {
      return Column(
        children: [
          Row(
            children: [
              Icon(Icons.check_circle_rounded,
                  color: foodflow.success, size: 20),
              const SizedBox(width: 8),
              Text("You're booked for this slot",
                  style: TextStyle(
                      fontWeight: FontWeight.w800, color: foodflow.ink)),
            ],
          ),
          const SizedBox(height: 10),
          GlassButton(
            label: 'Report an issue',
            compact: true,
            onPressed: widget.gig['driver_booking_id'] != null
                ? () => Navigator.pop(context, 'dispute')
                : null,
          ),
        ],
      );
    }

    return SwipeToConfirm(
      label: 'Swipe to book this slot',
      confirmedLabel: 'Booked',
      loading: _booking,
      accent: foodflow.success,
      onConfirmed: _confirm,
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(
            child: Text(label,
                style: TextStyle(fontSize: 13, color: foodflow.muted)),
          ),
          Text(value,
              style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: foodflow.ink)),
        ],
      ),
    );
  }

  Widget _req(IconData icon, String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          Icon(icon, size: 16, color: foodflow.orange),
          const SizedBox(width: 10),
          Expanded(
            child: Text(text,
                style: TextStyle(fontSize: 13, color: foodflow.inkSoft)),
          ),
        ],
      ),
    );
  }
}
