// lib/screens/customer/dining_confirmation_screen.dart

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../models/dining_booking.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/account_chrome.dart';

class DiningConfirmationScreen extends StatefulWidget {
  final DiningBooking booking;

  const DiningConfirmationScreen({
    super.key,
    required this.booking,
  });

  @override
  State<DiningConfirmationScreen> createState() => _DiningConfirmationScreenState();
}

class _DiningConfirmationScreenState extends State<DiningConfirmationScreen>
    with SingleTickerProviderStateMixin {
  late AnimationController _animationController;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 1500),
      vsync: this,
    );
    _animationController.forward();
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final timeFormat = DateFormat('hh:mm a');
    final dateFormat = DateFormat('EEEE, MMM dd, yyyy');
    final mediaQuery = MediaQuery.of(context);

    return WillPopScope(
      onWillPop: () async => false,
      child: MediaQuery(
        data: mediaQuery.copyWith(textScaler: const TextScaler.linear(1.08)),
        child: Scaffold(
          backgroundColor: accountCanvas,
          appBar: AppBar(
          backgroundColor: accountCanvas,
          elevation: 0,
          leading: null,
          automaticallyImplyLeading: false,
        ),
        body: SingleChildScrollView(
          child: Center(
            child: Column(
              children: [
                // Success Animation
                ScaleTransition(
                  scale: Tween<double>(begin: 0.0, end: 1.0).animate(
                    CurvedAnimation(
                      parent: _animationController,
                      curve: Curves.elasticOut,
                    ),
                  ),
                  child: Container(
                    margin: const EdgeInsets.symmetric(vertical: 24),
                    width: 120,
                    height: 120,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.1),
                          blurRadius: 20,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: const Center(
                      child: Icon(
                        Icons.check_circle,
                        color: FoodFlowTheme.orange,
                        size: 80,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                // Booking Confirmed Text
                FadeTransition(
                  opacity: Tween<double>(begin: 0.0, end: 1.0).animate(
                    CurvedAnimation(
                      parent: _animationController,
                      curve: const Interval(0.3, 1.0),
                    ),
                  ),
                  child: const Column(
                    children: [
                      Text(
                        'Booking Confirmed!',
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w800,
                          color: FoodFlowTheme.ink,
                        ),
                      ),
                      SizedBox(height: 8),
                      Text(
                        'Your table is reserved',
                        style: TextStyle(
                          fontSize: 16,
                          color: FoodFlowTheme.inkSoft,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 32),
                // Details Card
                FadeTransition(
                  opacity: Tween<double>(begin: 0.0, end: 1.0).animate(
                    CurvedAnimation(
                      parent: _animationController,
                      curve: const Interval(0.4, 1.0),
                    ),
                  ),
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 20),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.1),
                          blurRadius: 20,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: Column(
                      children: [
                        // Booking Number - Prominent
                        Container(
                          padding: const EdgeInsets.all(20),
                          decoration: const BoxDecoration(
                            color: Color(0xFFFFF3E0),
                            borderRadius: BorderRadius.only(
                              topLeft: Radius.circular(16),
                              topRight: Radius.circular(16),
                            ),
                          ),
                          child: Column(
                            children: [
                              Text(
                                'Booking Reference',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: Colors.grey.shade600,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                widget.booking.bookingNumber,
                                style: const TextStyle(
                                  fontSize: 24,
                                  fontWeight: FontWeight.w700,
                                  color: FoodFlowTheme.orange,
                                  letterSpacing: 2,
                                ),
                              ),
                            ],
                          ),
                        ),
                        // Details
                        Padding(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            children: [
                              _buildDetailRow(
                                icon: Icons.calendar_today,
                                label: 'Date',
                                value: dateFormat.format(widget.booking.bookingDate),
                              ),
                              const SizedBox(height: 16),
                              _buildDetailRow(
                                icon: Icons.access_time,
                                label: 'Time',
                                value: timeFormat.format(
                                  DateTime.now()
                                      .copyWith(
                                        hour: widget.booking.bookingTime.hour,
                                        minute: widget.booking.bookingTime.minute,
                                      )
                                ),
                              ),
                              const SizedBox(height: 16),
                              _buildDetailRow(
                                icon: Icons.group,
                                label: 'Guests',
                                value: '${widget.booking.numberOfGuests} ${widget.booking.numberOfGuests > 1 ? 'people' : 'person'}',
                              ),
                              if (widget.booking.celebrationType != null) ...[
                                const SizedBox(height: 16),
                                _buildDetailRow(
                                  icon: Icons.celebration,
                                  label: 'Occasion',
                                  value: widget.booking.celebrationType ?? '',
                                ),
                              ],
                              const SizedBox(height: 20),
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Colors.orange.shade50,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Row(
                                  children: [
                                    Icon(
                                      Icons.info_outline,
                                      size: 18,
                                      color: Colors.orange.shade700,
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Text(
                                        'Cover charge: ${formatCurrency(context, widget.booking.bookingCharge)}',
                                        style: TextStyle(
                                          fontSize: 12,
                                          color: Colors.orange.shade700,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  ],
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
                // Important Notes
                FadeTransition(
                  opacity: Tween<double>(begin: 0.0, end: 1.0).animate(
                    CurvedAnimation(
                      parent: _animationController,
                      curve: const Interval(0.5, 1.0),
                    ),
                  ),
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 20),
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: accountBorder,
                        width: 1,
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(
                              Icons.lightbulb_outline,
                              color: FoodFlowTheme.orange,
                              size: 18,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                'Things to Remember',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                  color: FoodFlowTheme.ink,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        _buildNote('📱 Show your booking reference at the restaurant'),
                        const SizedBox(height: 8),
                        _buildNote('⏰ Please arrive 10 minutes early'),
                        const SizedBox(height: 8),
                        _buildNote('❌ Cancellations accepted up to 3 hours before booking'),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 40),
              ],
            ),
          ),
        ),
          bottomSheet: Container(
            color: accountCanvas,
            padding: const EdgeInsets.all(16),
            child: SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(
                  width: double.infinity,
                  height: 56,
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.popUntil(context, (route) {
                        return route.isFirst;
                      });
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: FoodFlowTheme.orange,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Back to Home',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildDetailRow({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: Colors.grey.shade100,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(
            icon,
            size: 18,
            color: FoodFlowTheme.orange,
          ),
        ),
        const SizedBox(width: 16),
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
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: Colors.black,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildNote(String text) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 12,
        color: FoodFlowTheme.inkSoft,
        height: 1.5,
      ),
    );
  }
}
