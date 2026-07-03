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

  @override
  void initState() {
    super.initState();
    _loadGigs();
  }

  Future<void> _loadGigs() async {
    setState(() => _isLoading = true);

    try {
      final availableResponse =
          await _api.get(ApiConstants.driverGigs, queryParams: {
        'status': 'available',
      });
      final bookedResponse =
          await _api.get(ApiConstants.driverGigs, queryParams: {
        'status': 'booked',
      });
      final completedResponse =
          await _api.get(ApiConstants.driverGigs, queryParams: {
        'status': 'completed',
      });

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
          ];
        });
      }
    } catch (e) {
      debugPrint('Load gigs error: $e');
    }

    if (!mounted) return;
    setState(() => _isLoading = false);
  }

  List<dynamic> _extractGigs(dynamic response) {
    if (response is Map && response['data'] is List) {
      return List<dynamic>.from(response['data'] as List);
    }
    return [];
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
        backgroundColor: FoodFlowTheme.canvas,
        appBar: AppBar(
          title: const Text('Delivery Gigs'),
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Available Gigs'),
              Tab(text: 'My Gigs'),
            ],
          ),
        ),
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : TabBarView(
                children: [
                  _buildAvailableGigsTab(),
                  _buildMyGigsTab(),
                ],
              ),
      ),
    );
  }

  Widget _buildAvailableGigsTab() {
    if (_availableGigs.isEmpty) {
      return FoodFlowTheme.emptyState(
        icon: Icons.event_busy,
        title: 'No available gigs',
        subtitle: 'Open delivery slots will show up here.',
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _availableGigs.length,
      itemBuilder: (context, index) {
        final gig = _availableGigs[index];
        final date = DateTime.parse(gig['date']);
        final startTime = DateTime.parse(gig['start_time']);
        final endTime = DateTime.parse(gig['end_time']);
        final title = gig['title']?.toString().trim().isNotEmpty == true
            ? gig['title'].toString()
            : 'Open delivery slot';
        final description = gig['description']?.toString() ?? '';

        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(16),
          decoration: FoodFlowTheme.surface(radius: 14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: FoodFlowTheme.orange.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      Icons.delivery_dining,
                      color: FoodFlowTheme.orange,
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
                            color: FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                            fontSize: 16,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${DateFormat('EEEE, d MMM yyyy').format(date)} • ${DateFormat('hh:mm a').format(startTime)} - ${DateFormat('hh:mm a').format(endTime)}',
                          style: const TextStyle(
                            fontSize: 14,
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w600,
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
                              color: FoodFlowTheme.muted,
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
                    gig['area']['name'] ?? 'Delivery Area',
                    style: const TextStyle(
                      fontSize: 12,
                      color: FoodFlowTheme.muted,
                      fontWeight: FontWeight.w600,
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
                        fontWeight: FontWeight.w500,
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
                    color: FoodFlowTheme.muted,
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
      return FoodFlowTheme.emptyState(
        icon: Icons.calendar_today,
        title: 'No booked gigs',
        subtitle: 'Booked and completed slots will appear here.',
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _myGigs.length,
      itemBuilder: (context, index) {
        final gig = _myGigs[index];
        final date = DateTime.parse(gig['date']);
        final startTime = DateTime.parse(gig['start_time']);
        final endTime = DateTime.parse(gig['end_time']);
        final isCompleted = gig['status'] == 'completed';
        final title = gig['title']?.toString().trim().isNotEmpty == true
            ? gig['title'].toString()
            : 'Gig slot';

        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(16),
          decoration: FoodFlowTheme.surface(radius: 14),
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
                            color: FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                            fontSize: 16,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${DateFormat('EEEE, d MMM yyyy').format(date)} • ${DateFormat('hh:mm a').format(startTime)} - ${DateFormat('hh:mm a').format(endTime)}',
                          style: const TextStyle(
                            fontSize: 14,
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w600,
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
                        fontWeight: FontWeight.w500,
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
                      gig['area']['name'] ?? 'Delivery Area',
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
                        fontWeight: FontWeight.w500,
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
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
