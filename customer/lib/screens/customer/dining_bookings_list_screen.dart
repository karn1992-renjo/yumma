// lib/screens/customer/dining_bookings_list_screen.dart

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../providers/dining_provider.dart';
import '../../theme/foodflow_theme.dart';
import 'dining_booking_detail_screen.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/account_chrome.dart';

class DiningBookingsListScreen extends StatefulWidget {
  const DiningBookingsListScreen({super.key});

  @override
  State<DiningBookingsListScreen> createState() => _DiningBookingsListScreenState();
}

class _DiningBookingsListScreenState extends State<DiningBookingsListScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  String _filterStatus = 'upcoming'; // upcoming, all, completed

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadBookings();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadBookings() async {
    final provider = context.read<DiningProvider>();
    await provider.fetchMyBookings();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        backgroundColor: accountCanvas,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'My Dining Reservations',
          style: TextStyle(
            color: Colors.black,
            fontSize: 18,
            fontWeight: FontWeight.w600,
          ),
        ),
        centerTitle: false,
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(
              child: Text(
                'Upcoming',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                ),
              ),
            ),
            Tab(
              child: Text(
                'Past',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                ),
              ),
            ),
          ],
          indicatorColor: Theme.of(context).colorScheme.primary,
          labelColor: Colors.black,
          unselectedLabelColor: Colors.grey.shade600,
          dividerColor: Colors.transparent,
        ),
      ),
      body: Consumer<DiningProvider>(
        builder: (context, diningProvider, child) {
          return RefreshIndicator(
            onRefresh: _loadBookings,
            child: TabBarView(
              controller: _tabController,
              children: [
                // Upcoming Bookings
                _buildBookingsList(
                  context,
                  diningProvider.getUpcomingBookings(),
                  diningProvider,
                  isUpcoming: true,
                ),
                // Past Bookings
                _buildBookingsList(
                  context,
                  diningProvider.getPastBookings(),
                  diningProvider,
                  isUpcoming: false,
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildBookingsList(
    BuildContext context,
    List<dynamic> bookings,
    DiningProvider provider, {
    required bool isUpcoming,
  }) {
    if (bookings.isEmpty) {
      return ListView(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        children: [
          AccountHeroCard(
            title: isUpcoming ? 'Upcoming tables' : 'Past dining visits',
            subtitle: isUpcoming
                ? 'Keep an eye on your next reservations and manage them quickly.'
                : 'Look back at your completed and older reservations.',
            icon: isUpcoming ? Icons.event_seat_rounded : Icons.history_rounded,
            badge: 'PROFILE SPACE',
            margin: const EdgeInsets.fromLTRB(0, 8, 0, 18),
          ),
          Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              isUpcoming ? Icons.event_busy : Icons.history,
              size: 64,
              color: Colors.grey.shade400,
            ),
            const SizedBox(height: 16),
            Text(
              isUpcoming
                  ? 'No upcoming reservations'
                  : 'No past reservations',
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: Colors.grey,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Book a table at your favorite restaurant',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey.shade600,
              ),
            ),
          ],
        ),
        ],
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
      itemCount: bookings.length + 2,
      itemBuilder: (context, index) {
        if (index == 0) {
          return AccountHeroCard(
            title: isUpcoming ? 'Upcoming tables' : 'Past dining visits',
            subtitle: isUpcoming
                ? 'Manage your next reservations with clear status and quick actions.'
                : 'Review previous reservations and open the full details anytime.',
            icon: isUpcoming ? Icons.event_seat_rounded : Icons.history_rounded,
            badge: 'PROFILE SPACE',
            margin: const EdgeInsets.fromLTRB(0, 8, 0, 16),
          );
        }
        if (index == 1) {
          return const Padding(
            padding: EdgeInsets.only(bottom: 14),
            child: AccountSectionTitle(title: 'RESERVATIONS'),
          );
        }
        final booking = bookings[index - 2];
        return _buildBookingCard(context, booking, provider);
      },
    );
  }

  Widget _buildBookingCard(
    BuildContext context,
    dynamic booking,
    DiningProvider provider,
  ) {
    final dateFormat = DateFormat('MMM dd, yyyy');
    final timeFormat = DateFormat('hh:mm a');
    final bookingDateTime = DateTime.now().copyWith(
      hour: booking.bookingTime.hour,
      minute: booking.bookingTime.minute,
    );

    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => DiningBookingDetailScreen(booking: booking),
          ),
        );
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.08),
              blurRadius: 12,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Status Badge and Restaurant Name
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                border: Border(
                  bottom: BorderSide(color: Colors.grey.shade100),
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                booking.restaurantName ?? 'Restaurant',
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.black,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            const SizedBox(width: 8),
                            _buildStatusBadge(booking.status),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Icon(
                              Icons.star_rounded,
                              size: 14,
                              color: Colors.orange.shade600,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '${booking.rating?.toStringAsFixed(1) ?? 'N/A'} • Dining',
                              style: TextStyle(
                                fontSize: 12,
                                color: Colors.grey.shade600,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            // Booking Details
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Booking Number
                  Row(
                    children: [
                      Icon(
                        Icons.confirmation_number,
                        size: 16,
                        color: Colors.grey.shade600,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        booking.bookingNumber,
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey.shade600,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  // Date, Time, Guests
                  Row(
                    children: [
                      Expanded(
                        child: _buildDetailChip(
                          icon: Icons.calendar_today,
                          label: dateFormat.format(booking.bookingDate),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _buildDetailChip(
                          icon: Icons.access_time,
                          label: timeFormat.format(bookingDateTime),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _buildDetailChip(
                          icon: Icons.group,
                          label: '${booking.numberOfGuests} guests',
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  // Cover Charge
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.orange.shade50,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.info_outline,
                          size: 14,
                          color: Colors.orange.shade700,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          'Cover charge: ${formatCurrency(context, booking.bookingCharge)}',
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.orange.shade700,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            // Action Button
            if (booking.canBeCancelled)
              Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () {
                          _showCancellationDialog(context, booking, provider);
                        },
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.red,
                          side: const BorderSide(color: Colors.red),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: const Text(
                          'Cancel Booking',
                          style: TextStyle(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) =>
                                  DiningBookingDetailScreen(booking: booking),
                            ),
                          );
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: FoodFlowTheme.orange,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: const Text(
                          'View Details',
                          style: TextStyle(
                            fontWeight: FontWeight.w600,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusBadge(String status) {
    Color bgColor;
    Color textColor;
    String text;

    switch (status.toLowerCase()) {
      case 'pending':
        bgColor = Colors.amber.shade100;
        textColor = Colors.amber.shade700;
        text = 'Pending';
        break;
      case 'confirmed':
        bgColor = Colors.blue.shade100;
        textColor = Colors.blue.shade700;
        text = 'Confirmed';
        break;
      case 'completed':
        bgColor = Colors.green.shade100;
        textColor = Colors.green.shade700;
        text = 'Completed';
        break;
      case 'cancelled':
        bgColor = Colors.red.shade100;
        textColor = Colors.red.shade700;
        text = 'Cancelled';
        break;
      default:
        bgColor = Colors.grey.shade100;
        textColor = Colors.grey.shade700;
        text = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: textColor,
        ),
      ),
    );
  }

  Widget _buildDetailChip({
    required IconData icon,
    required String label,
  }) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: Colors.grey.shade600),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: Colors.grey.shade700,
              fontWeight: FontWeight.w500,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  void _showCancellationDialog(
    BuildContext context,
    dynamic booking,
    DiningProvider provider,
  ) {
    final reasonController = TextEditingController();

    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Cancel Booking'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Are you sure you want to cancel this booking?',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey.shade700,
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: reasonController,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: 'Reason for cancellation (optional)',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                contentPadding: const EdgeInsets.all(12),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Close'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(dialogContext);
              _cancelBooking(booking, reasonController.text, provider);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
            ),
            child: const Text(
              'Cancel Booking',
              style: TextStyle(color: Colors.white),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _cancelBooking(
    dynamic booking,
    String reason,
    DiningProvider provider,
  ) async {
    final success = await provider.cancelBooking(
      booking.id,
      reason.isEmpty ? 'No reason provided' : reason,
    );

    if (mounted) {
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Booking cancelled successfully'),
            backgroundColor: Colors.green,
          ),
        );
        setState(() {
          _tabController.animateTo(0);
        });
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(provider.error ?? 'Failed to cancel booking'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }
}
