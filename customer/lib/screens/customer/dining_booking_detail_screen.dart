// lib/screens/customer/dining_booking_detail_screen.dart

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../models/dining_booking.dart';
import '../../providers/dining_provider.dart';
import '../../theme/foodflow_theme.dart';
import 'package:provider/provider.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/account_chrome.dart';
import '../../widgets/common/app_cached_image.dart';

class DiningBookingDetailScreen extends StatefulWidget {
  final DiningBooking booking;

  const DiningBookingDetailScreen({
    super.key,
    required this.booking,
  });

  @override
  State<DiningBookingDetailScreen> createState() =>
      _DiningBookingDetailScreenState();
}

class _DiningBookingDetailScreenState extends State<DiningBookingDetailScreen> {
  bool _showReviewForm = false;
  double _rating = 0;
  final TextEditingController _feedbackController = TextEditingController();

  @override
  void dispose() {
    _feedbackController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('EEEE, MMM dd, yyyy');
    final timeFormat = DateFormat('hh:mm a');
    final bookingDateTime = DateTime.now().copyWith(
      hour: widget.booking.bookingTime.hour,
      minute: widget.booking.bookingTime.minute,
    );

    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        backgroundColor: accountCanvas,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: FoodFlowTheme.ink),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Booking Details',
          style: TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        child: Column(
          children: [
            const SizedBox(height: 12),
            // Status Card
            _buildStatusCard(),
            const SizedBox(height: 16),
            // Booking Information
            _buildInfoCard(
              title: 'Booking Information',
              children: [
                _buildInfoRow(
                  icon: Icons.confirmation_number,
                  label: 'Booking Reference',
                  value: widget.booking.bookingNumber,
                  highlight: true,
                ),
                const Divider(height: 24),
                _buildInfoRow(
                  icon: Icons.calendar_today,
                  label: 'Date',
                  value: dateFormat.format(widget.booking.bookingDate),
                ),
                const SizedBox(height: 16),
                _buildInfoRow(
                  icon: Icons.access_time,
                  label: 'Time',
                  value: timeFormat.format(bookingDateTime),
                ),
                const SizedBox(height: 16),
                _buildInfoRow(
                  icon: Icons.group,
                  label: 'Number of Guests',
                  value:
                      '${widget.booking.numberOfGuests} ${widget.booking.numberOfGuests > 1 ? 'people' : 'person'}',
                ),
                if (widget.booking.celebrationType != null) ...[
                  const SizedBox(height: 16),
                  _buildInfoRow(
                    icon: Icons.celebration,
                    label: 'Occasion',
                    value: widget.booking.celebrationType ?? '',
                  ),
                ],
              ],
            ),
            const SizedBox(height: 12),
            // Restaurant Information
            _buildRestaurantCard(),
            const SizedBox(height: 12),
            // Charges
            _buildChargesCard(),
            const SizedBox(height: 12),
            // Special Requests
            if (widget.booking.specialRequests != null &&
                widget.booking.specialRequests!.isNotEmpty)
              _buildSpecialRequestsCard(),
            const SizedBox(height: 12),
            // Status Timeline
            if (widget.booking.isCompleted || widget.booking.isCancelled)
              _buildStatusTimeline(),
            const SizedBox(height: 12),
            // Review Section (for completed bookings)
            if (widget.booking.isCompleted && widget.booking.rating == null)
              _buildReviewPrompt(),
            if (_showReviewForm) _buildReviewForm(),
            const SizedBox(height: 100),
          ],
        ),
      ),
      bottomSheet: widget.booking.canBeCancelled
          ? Container(
              color: Colors.white,
              padding: const EdgeInsets.all(16),
              child: SafeArea(
                child: SizedBox(
                  width: double.infinity,
                  height: 56,
                  child: ElevatedButton(
                    onPressed: () {
                      _showCancellationDialog();
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.red,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Cancel Booking',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ),
            )
          : null,
    );
  }

  Widget _buildStatusCard() {
    Color bgColor;
    Color textColor;
    String statusText;
    IconData icon;

    switch (widget.booking.status.toLowerCase()) {
      case 'pending':
        bgColor = Colors.amber.shade50;
        textColor = Colors.amber.shade700;
        statusText = 'Booking Pending';
        icon = Icons.hourglass_empty;
        break;
      case 'confirmed':
        bgColor = Colors.blue.shade50;
        textColor = Colors.blue.shade700;
        statusText = 'Booking Confirmed';
        icon = Icons.check_circle;
        break;
      case 'completed':
        bgColor = Colors.green.shade50;
        textColor = Colors.green.shade700;
        statusText = 'Booking Completed';
        icon = Icons.verified;
        break;
      case 'cancelled':
        bgColor = Colors.red.shade50;
        textColor = Colors.red.shade700;
        statusText = 'Booking Cancelled';
        icon = Icons.cancel;
        break;
      default:
        bgColor = Colors.grey.shade100;
        textColor = Colors.grey.shade700;
        statusText = widget.booking.status;
        icon = Icons.info;
    }

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: textColor.withOpacity(0.3)),
      ),
      child: Row(
        children: [
          Icon(icon, color: textColor, size: 24),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  statusText,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: textColor,
                  ),
                ),
                const SizedBox(height: 4),
                if (widget.booking.confirmedAt != null)
                  Text(
                    'Confirmed on ${DateFormat('MMM dd, yyyy hh:mm a').format(widget.booking.confirmedAt!)}',
                    style: TextStyle(
                      fontSize: 12,
                      color: textColor.withOpacity(0.7),
                    ),
                  ),
                if (widget.booking.cancelledAt != null)
                  Text(
                    'Cancelled on ${DateFormat('MMM dd, yyyy hh:mm a').format(widget.booking.cancelledAt!)}',
                    style: TextStyle(
                      fontSize: 12,
                      color: textColor.withOpacity(0.7),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoCard({
    required String title,
    required List<Widget> children,
  }) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Text(
              title,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: Colors.black,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: children,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow({
    required IconData icon,
    required String label,
    required String value,
    bool highlight = false,
  }) {
    return Row(
      children: [
        Icon(
          icon,
          size: 20,
          color: highlight ? FoodFlowTheme.orange : Colors.grey.shade600,
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey.shade600,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                value,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: highlight ? FontWeight.w700 : FontWeight.w600,
                  color: highlight ? FoodFlowTheme.orange : Colors.black,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildRestaurantCard() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Restaurant',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Colors.black,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              if (widget.booking.restaurantImage != null)
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: AppCachedImage(
                    imageUrl: widget.booking.restaurantImage!,
                    width: 80,
                    height: 80,
                    fit: BoxFit.cover,
                    errorBuilder: (context, error, stackTrace) {
                      return Container(
                        width: 80,
                        height: 80,
                        decoration: BoxDecoration(
                          color: Colors.grey.shade200,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(Icons.restaurant),
                      );
                    },
                  ),
                ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.booking.restaurantName ?? 'Restaurant',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: Colors.black,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Icon(
                          Icons.star_rounded,
                          color: Colors.orange.shade600,
                          size: 16,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          widget.booking.rating?.toStringAsFixed(1) ?? 'N/A',
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: Colors.black,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildChargesCard() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Charges',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Colors.black,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Cover Charge (${widget.booking.numberOfGuests} guests)',
                style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey.shade700,
                ),
              ),
              Text(
                formatCurrency(context, widget.booking.bookingCharge),
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: Colors.black,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: BoxDecoration(
              border: Border(
                top: BorderSide(color: Colors.grey.shade200),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Total Amount',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: Colors.black,
                  ),
                ),
                Text(
                  formatCurrency(context, widget.booking.bookingCharge),
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: FoodFlowTheme.orange,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSpecialRequestsCard() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Special Requests',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Colors.black,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            widget.booking.specialRequests ?? '',
            style: const TextStyle(
              fontSize: 14,
              color: Colors.grey,
              height: 1.6,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusTimeline() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Timeline',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Colors.black,
            ),
          ),
          const SizedBox(height: 16),
          _buildTimelineItem(
            title: 'Booking Created',
            time: DateFormat('MMM dd, yyyy hh:mm a').format(
              widget.booking.createdAt,
            ),
            isActive: true,
          ),
          if (widget.booking.confirmedAt != null)
            _buildTimelineItem(
              title: 'Booking Confirmed',
              time: DateFormat('MMM dd, yyyy hh:mm a').format(
                widget.booking.confirmedAt!,
              ),
              isActive: true,
            ),
          if (widget.booking.isCompleted)
            _buildTimelineItem(
              title: 'Booking Completed',
              time: 'On ${DateFormat('MMM dd, yyyy').format(widget.booking.bookingDate)}',
              isActive: true,
            ),
          if (widget.booking.isCancelled)
            _buildTimelineItem(
              title: 'Booking Cancelled',
              time: DateFormat('MMM dd, yyyy hh:mm a').format(
                widget.booking.cancelledAt!,
              ),
              isActive: false,
            ),
        ],
      ),
    );
  }

  Widget _buildTimelineItem({
    required String title,
    required String time,
    required bool isActive,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        children: [
          Container(
            width: 12,
            height: 12,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: isActive ? FoodFlowTheme.orange : Colors.grey.shade300,
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: Colors.black,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  time,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey.shade600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReviewPrompt() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: Colors.blue.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.blue.shade200),
      ),
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Icon(
            Icons.rate_review,
            color: Colors.blue.shade700,
            size: 24,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Rate your experience',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: Colors.blue.shade700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Share your feedback to help others',
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.blue.shade600,
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: () {
              setState(() {
                _showReviewForm = true;
              });
            },
            child: Text(
              'Rate',
              style: TextStyle(
                color: Colors.blue.shade700,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReviewForm() {
    return Consumer<DiningProvider>(
      builder: (context, diningProvider, child) {
        return Container(
          margin: const EdgeInsets.symmetric(horizontal: 16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
          ),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Rate Your Dining Experience',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: Colors.black,
                ),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(5, (index) {
                  return GestureDetector(
                    onTap: () {
                      setState(() {
                        _rating = (index + 1).toDouble();
                      });
                    },
                    child: Icon(
                      Icons.star_rounded,
                      size: 40,
                      color: index < _rating
                          ? Colors.amber.shade500
                          : Colors.grey.shade300,
                    ),
                  );
                }),
              ),
              const SizedBox(height: 20),
              TextField(
                controller: _feedbackController,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: 'Share your feedback...',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  contentPadding: const EdgeInsets.all(12),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {
                        setState(() {
                          _showReviewForm = false;
                          _rating = 0;
                          _feedbackController.clear();
                        });
                      },
                      child: const Text('Cancel'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: _rating == 0
                          ? null
                          : () async {
                              final success =
                                  await diningProvider.submitReview(
                                bookingId: widget.booking.id,
                                rating: _rating,
                                feedback: _feedbackController.text,
                              );

                              if (mounted) {
                                setState(() {
                                  if (success) {
                                    _showReviewForm = false;
                                    _rating = 0;
                                    _feedbackController.clear();
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(
                                        content: Text(
                                            'Thank you for your feedback!'),
                                        backgroundColor: Colors.green,
                                      ),
                                    );
                                  } else {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: Text(
                                            diningProvider.error ?? 'Error'),
                                        backgroundColor: Colors.red,
                                      ),
                                    );
                                  }
                                });
                              }
                            },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: FoodFlowTheme.orange,
                      ),
                      child: const Text(
                        'Submit',
                        style: TextStyle(color: Colors.white),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  void _showCancellationDialog() {
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
              _cancelBooking(reasonController.text);
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

  Future<void> _cancelBooking(String reason) async {
    final provider = context.read<DiningProvider>();
    final success = await provider.cancelBooking(
      widget.booking.id,
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
        Navigator.pop(context);
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
