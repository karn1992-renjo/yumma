// lib/screens/customer/dining_booking_screen_v2.dart

import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:intl/intl.dart';
import '../../models/dining_booking.dart';
import '../../providers/dining_provider.dart';
import '../../config/api_constants.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/theme_alt.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/account_chrome.dart';
import 'dining_confirmation_screen.dart';
import 'package:provider/provider.dart';

class DiningBookingScreenV2 extends StatefulWidget {
  final int restaurantId;
  final String restaurantName;
  final double diningCharge;
  final String? restaurantImage;
  final double? rating;

  const DiningBookingScreenV2({
    super.key,
    required this.restaurantId,
    required this.restaurantName,
    required this.diningCharge,
    this.restaurantImage,
    this.rating,
  });

  @override
  State<DiningBookingScreenV2> createState() => _DiningBookingScreenV2State();
}

class _DiningBookingScreenV2State extends State<DiningBookingScreenV2>
    with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _requestsController = TextEditingController();
  late AnimationController _animationController;

  DateTime _selectedDate = DateTime.now().add(const Duration(days: 1));
  TimeOfDay _selectedTime = const TimeOfDay(hour: 19, minute: 0);
  int _guestCount = 2;
  String? _selectedCelebrationType;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 500),
      vsync: this,
    );
    _animationController.forward();
  }

  @override
  void dispose() {
    _requestsController.dispose();
    _animationController.dispose();
    super.dispose();
  }

  Future<void> _selectDate(BuildContext context) async {
    final DateTime? pickedDate = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime.now().add(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: ThemeAlt.orange,
              onPrimary: Colors.white,
              onSurface: Colors.black,
            ),
          ),
          child: child!,
        );
      },
    );

    if (pickedDate != null && pickedDate != _selectedDate) {
      setState(() {
        _selectedDate = pickedDate;
      });
    }
  }

  Future<void> _selectTime(BuildContext context) async {
    final TimeOfDay? pickedTime = await showTimePicker(
      context: context,
      initialTime: _selectedTime,
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: ThemeAlt.orange,
              onPrimary: Colors.white,
              onSurface: Colors.black,
            ),
          ),
          child: child!,
        );
      },
    );

    if (pickedTime != null && pickedTime != _selectedTime) {
      setState(() {
        _selectedTime = pickedTime;
      });
    }
  }

  Future<void> _submitBooking(DiningProvider diningProvider) async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    try {
      final booking = await diningProvider.createBooking(
        restaurantId: widget.restaurantId,
        bookingDate: _selectedDate,
        bookingTime: _selectedTime,
        numberOfGuests: _guestCount,
        celebrationType: _selectedCelebrationType,
        specialRequests: _requestsController.text.trim(),
      );

      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });

        if (booking != null) {
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (_) => DiningConfirmationScreen(booking: booking),
            ),
          );
        } else {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(diningProvider.error ?? 'Failed to create booking'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: ${e.toString()}'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<DiningProvider>(
      builder: (context, diningProvider, child) {
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
              'Reserve a Table',
              style: TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            centerTitle: true,
          ),
          body: FadeTransition(
            opacity: _animationController,
            child: SingleChildScrollView(
              child: Column(
                children: [
                  // Restaurant Info Card
                  Container(
                    color: Colors.white,
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            if (widget.restaurantImage != null)
                              ClipRRect(
                                borderRadius: BorderRadius.circular(12),
                                child: AppCachedImage(
                                  imageUrl: widget.restaurantImage!,
                                  width: 60,
                                  height: 60,
                                  fit: BoxFit.cover,
                                  errorBuilder: (context, error, stackTrace) {
                                    return Container(
                                      width: 60,
                                      height: 60,
                                      decoration: BoxDecoration(
                                        color: Colors.grey.shade200,
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: const Icon(Icons.restaurant),
                                    );
                                  },
                                ),
                              ),
                            const SizedBox(width: 16),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    widget.restaurantName,
                                    style: const TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w700,
                                    ),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  const SizedBox(height: 4),
                                  Row(
                                    children: [
                                      Icon(
                                        Icons.star_rounded,
                                        color: Colors.orange.shade600,
                                        size: 16,
                                      ),
                                      const SizedBox(width: 4),
                                      Text(
                                        '${widget.rating?.toStringAsFixed(1) ?? 'N/A'} • Dining',
                                        style: TextStyle(
                                          fontSize: 12,
                                          color: Colors.grey.shade600,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 12,
                                      vertical: 4,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.orange.shade50,
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      'Cover charge: ${formatCurrency(context, widget.diningCharge)}',
                                      style: TextStyle(
                                        fontSize: 11,
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
                      ],
                    ),
                  ),
                  const SizedBox(height: 8),
                  // Booking Form
                  Container(
                    color: Colors.white,
                    margin: const EdgeInsets.only(top: 8),
                    padding: const EdgeInsets.all(20),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Date Selection
                          const Text(
                            'Date & Time',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Row(
                            children: [
                              Expanded(
                                child: _buildDateTimeButton(
                                  context,
                                  icon: Icons.calendar_today_rounded,
                                  label: 'Date',
                                  value:
                                      DateFormat('MMM dd, yyyy').format(_selectedDate),
                                  onTap: () => _selectDate(context),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _buildDateTimeButton(
                                  context,
                                  icon: Icons.access_time_rounded,
                                  label: 'Time',
                                  value: _selectedTime.format(context),
                                  onTap: () => _selectTime(context),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 28),
                          // Guest Count
                          const Text(
                            'Number of Guests',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 12),
                          _buildGuestSelector(),
                          const SizedBox(height: 28),
                          // Celebration Type
                          const Text(
                            'Occasion (Optional)',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 12),
                          diningProvider.isLoading
                              ? const SizedBox(
                                  height: 100,
                                  child: Center(
                                    child: CircularProgressIndicator(),
                                  ),
                                )
                              : _buildCelebrationTypeGrid(diningProvider),
                          const SizedBox(height: 28),
                          // Special Requests
                          const Text(
                            'Special Requests',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _requestsController,
                            maxLines: 3,
                            decoration: InputDecoration(
                              hintText: 'E.g., Prefer window seating, need high chair...',
                              hintStyle: TextStyle(
                                color: Colors.grey.shade500,
                                fontSize: 14,
                              ),
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: BorderSide(
                                  color: Colors.grey.shade300,
                                ),
                              ),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: BorderSide(
                                  color: Colors.grey.shade300,
                                ),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(
                                  color: ThemeAlt.orange,
                                  width: 2,
                                ),
                              ),
                              filled: true,
                              fillColor: Colors.grey.shade50,
                              contentPadding: const EdgeInsets.all(16),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 100),
                ],
              ),
            ),
          ),
          bottomSheet: Container(
            color: Colors.white,
            padding: const EdgeInsets.all(16),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                height: 56,
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : () => _submitBooking(diningProvider),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: ThemeAlt.orange,
                    disabledBackgroundColor: Colors.grey.shade300,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: _isSubmitting
                      ? const SizedBox(
                          height: 24,
                          width: 24,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2,
                          ),
                        )
                      : const Text(
                          'Confirm Booking',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildDateTimeButton(
    BuildContext context, {
    required IconData icon,
    required String label,
    required String value,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey.shade300),
          borderRadius: BorderRadius.circular(12),
          color: Colors.white,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 18, color: ThemeAlt.orange),
                const SizedBox(width: 8),
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 11,
                    color: Colors.grey.shade600,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
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
    );
  }

  Widget _buildGuestSelector() {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: List.generate(6, (index) {
          final count = index + 1;
          final isSelected = _guestCount == count;
          return Expanded(
            child: GestureDetector(
              onTap: () {
                setState(() {
                  _guestCount = count;
                });
              },
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 4),
                padding: const EdgeInsets.symmetric(vertical: 12),
                decoration: BoxDecoration(
                  color: isSelected ? ThemeAlt.orange : Colors.grey.shade100,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: isSelected ? ThemeAlt.orange : Colors.transparent,
                    width: 2,
                  ),
                ),
                child: Center(
                  child: Text(
                    count > 4 ? '$count+' : '$count',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: isSelected ? Colors.white : Colors.black,
                    ),
                  ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget _buildCelebrationTypeGrid(DiningProvider diningProvider) {
    if (diningProvider.celebrationTypes.isEmpty) {
      return Container(
        padding: const EdgeInsets.all(20),
        child: const Center(
          child: Text(
            'No celebration types available',
            style: TextStyle(color: Colors.grey),
          ),
        ),
      );
    }

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
        childAspectRatio: 1.2,
      ),
      itemCount: diningProvider.celebrationTypes.length,
      itemBuilder: (context, index) {
        final celebration = diningProvider.celebrationTypes[index];
        final isSelected = _selectedCelebrationType == celebration.name;

        return GestureDetector(
          onTap: () {
            setState(() {
              _selectedCelebrationType = isSelected ? null : celebration.name;
            });
          },
          child: Container(
            decoration: BoxDecoration(
              color: isSelected ? ThemeAlt.orange.withOpacity(0.1) : Colors.white,
              border: Border.all(
                color: isSelected ? ThemeAlt.orange : Colors.grey.shade200,
                width: isSelected ? 2 : 1,
              ),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                if (celebration.icon != null)
                  Text(
                    celebration.icon!,
                    style: const TextStyle(fontSize: 24),
                  ),
                const SizedBox(height: 8),
                Text(
                  celebration.name,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: isSelected ? ThemeAlt.orange : Colors.black,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
