// lib/screens/driver/driver_gigs_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../theme/foodflow_theme.dart';
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
      final availableResponse =
          await _api.get(ApiConstants.driverGigs, queryParams: _gigQueryParams('available'));
      final bookedResponse =
          await _api.get(ApiConstants.driverGigs, queryParams: _gigQueryParams('booked'));
      final completedResponse =
          await _api.get(ApiConstants.driverGigs, queryParams: _gigQueryParams('completed'));

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
    final endTime =
        _parseDateTime(gig['slot_end_local']) ?? _parseDateTime(gig['end_time']);
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

  Future<void> _bookGig(int gigId) async {
    try {
      final response =
          await _api.post('${ApiConstants.driverGigs}/$gigId/book');
      if (response['success'] == true) {
        await _loadGigs();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Gig booked successfully!')),
          );
          Navigator.pop(context, true);
        }
      }
    } catch (e) {
      debugPrint('Book gig error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to book gig: $e')),
        );
      }
    }
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
      child: Scaffold(
        backgroundColor: foodflow.canvas,
        appBar: AppBar(
          title: const Text('Delivery Gigs'),
          bottom: const TabBar(
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
      color: foodflow.canvas,
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
                backgroundColor: Colors.white,
                side: BorderSide(color: Colors.grey.shade300),
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

        return Container(
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
                        Text(
                          title,
                          style: const TextStyle(
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
                          style: const TextStyle(
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
                            style: const TextStyle(
                              fontSize: 12,
                              color: foodflow.muted,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  ElevatedButton(
                    onPressed: () => _bookGig(gig['id']),
                    style: ElevatedButton.styleFrom(
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                    child: const Text('Book'),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  const Icon(Icons.location_on, size: 16, color: Colors.grey),
                  const SizedBox(width: 4),
                  Text(
                    (gig['area']?['name'] ?? 'Delivery Area').toString(),
                    style: const TextStyle(
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
                      'Potential earning: ${_money(gig['estimated_earning'])}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w400,
                        color: Colors.green,
                      ),
                    ),
                  ],
                ),
              ),
              if ((gig['terms_conditions']?.toString() ?? '').isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  gig['terms_conditions'].toString(),
                  style: const TextStyle(
                    fontSize: 12,
                    color: foodflow.muted,
                  ),
                ),
              ],
            ],
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

        return Container(
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
                          style: const TextStyle(
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
                          style: const TextStyle(
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
                  const Icon(Icons.location_on, size: 16, color: Colors.grey),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      (gig['area']?['name'] ?? 'Delivery Area').toString(),
                      style: const TextStyle(fontSize: 12, color: Colors.grey),
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
            ],
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
