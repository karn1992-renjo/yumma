import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../models/dining_booking.dart';
import '../../config/app_config.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/account_chrome.dart';
import 'dining_booking_checkout_screen.dart';
import 'dining_confirmation_screen.dart';

class DiningBookingScreen extends StatefulWidget {
  final int restaurantId;
  final String restaurantName;
  final double diningCharge;

  const DiningBookingScreen({
    super.key,
    required this.restaurantId,
    required this.restaurantName,
    required this.diningCharge,
  });

  @override
  State<DiningBookingScreen> createState() => _DiningBookingScreenState();
}

class _DiningBookingScreenState extends State<DiningBookingScreen> {
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _requestsController = TextEditingController();

  DateTime _selectedDate = DateTime.now().add(const Duration(days: 1));
  TimeOfDay _selectedTime = const TimeOfDay(hour: 19, minute: 0);
  int _guestCount = 2;
  String? _selectedCelebrationType;
  List<dynamic> _celebrationTypes = [];
  bool _isLoading = false;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _loadCelebrationTypes();
  }

  @override
  void dispose() {
    _requestsController.dispose();
    super.dispose();
  }

  Future<void> _loadCelebrationTypes() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(ApiConstants.diningCelebrationTypes);
      final data = response['data'];
      if (!mounted) return;
      if (response['success'] == true && data is List) {
        setState(() {
          _celebrationTypes = data;
          if (_celebrationTypes.isNotEmpty) {
            _selectedCelebrationType =
                _celebrationTypes.first['value']?.toString();
          }
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      debugPrint('Error loading celebration types: $e');
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _chooseDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: now,
      lastDate: now.add(const Duration(days: 90)),
      builder: (context, child) => _wrapPickerTheme(child),
    );
    if (picked != null) {
      setState(() {
        _selectedDate = picked;
      });
    }
  }

  Future<void> _chooseTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _selectedTime,
      builder: (context, child) => _wrapPickerTheme(child),
    );
    if (picked != null) {
      setState(() {
        _selectedTime = picked;
      });
    }
  }

  Future<void> _submitBooking() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    if (_celebrationTypes.isNotEmpty && _selectedCelebrationType == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a celebration type')),
      );
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    final bookingDate =
        '${_selectedDate.year.toString().padLeft(4, '0')}-${_selectedDate.month.toString().padLeft(2, '0')}-${_selectedDate.day.toString().padLeft(2, '0')}';
    final bookingTime =
        '${_selectedTime.hour.toString().padLeft(2, '0')}:${_selectedTime.minute.toString().padLeft(2, '0')}';

    try {
      final response = await _api.post(ApiConstants.diningBook, data: {
        'restaurant_id': widget.restaurantId,
        'booking_date': bookingDate,
        'booking_time': bookingTime,
        'number_of_guests': _guestCount,
        'celebration_type': _selectedCelebrationType,
        'special_requests': _requestsController.text.trim(),
      });

      if (response['success'] == true) {
        if (!mounted) return;
        final booking = DiningBooking.fromJson(
          Map<String, dynamic>.from(response['data'] ?? {}),
        );
        if (booking.bookingCharge > 0) {
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (_) => DiningBookingCheckoutScreen(
                booking: booking,
                restaurantName: widget.restaurantName,
              ),
            ),
          );
        } else {
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(
              builder: (_) => DiningConfirmationScreen(booking: booking),
            ),
          );
        }
      } else {
        if (!mounted) return;
        _showStatusSnackBar(_extractBookingError(response));
      }
    } catch (e) {
      debugPrint('Dining booking error: $e');
      if (mounted) {
        _showStatusSnackBar('Unable to confirm booking. Please try again.');
      }
    }

    if (mounted) {
      setState(() {
        _isSubmitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        title: const Text('Reserve a table'),
        backgroundColor: accountCanvas,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
      ),
      body: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildHeroCard(context),
                const SizedBox(height: 16),
                _buildReservationCard(context),
                const SizedBox(height: 16),
                _buildCelebrationCard(),
                const SizedBox(height: 16),
                _buildRequestsCard(),
                const SizedBox(height: 18),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _isSubmitting ? null : _submitBooking,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppConfig.primaryColor,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      padding: const EdgeInsets.symmetric(vertical: 17),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(18),
                      ),
                    ),
                    child: _isSubmitting
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              color: Colors.white,
                              strokeWidth: 2,
                            ),
                          )
                        : Text(
                            widget.diningCharge > 0
                                ? 'Continue to payment'
                                : 'Confirm booking',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _pickGuestCount() async {
    final result = await showModalBottomSheet<int>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (context) {
        int selectedCount = _guestCount;
        return StatefulBuilder(
          builder: (context, setModalState) {
            return SafeArea(
              top: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 42,
                      height: 4,
                      decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'How many guests?',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Pick the table size for your reservation.',
                      style: TextStyle(color: Colors.grey.shade600),
                    ),
                    const SizedBox(height: 20),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [2, 4, 6, 8].map((count) {
                        final isSelected = selectedCount == count;
                        return ChoiceChip(
                          label: Text('$count'),
                          selected: isSelected,
                          selectedColor:
                              AppConfig.primaryColor.withOpacity(0.14),
                          labelStyle: TextStyle(
                            color: isSelected
                                ? AppConfig.primaryColor
                                : FoodFlowTheme.ink,
                            fontWeight: FontWeight.w700,
                          ),
                          onSelected: (_) =>
                              setModalState(() => selectedCount = count),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 18),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 18,
                        vertical: 14,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF7F2),
                        borderRadius: BorderRadius.circular(24),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          _CounterButton(
                            icon: Icons.remove,
                            onTap: selectedCount > 1
                                ? () => setModalState(() => selectedCount -= 1)
                                : null,
                          ),
                          Column(
                            children: [
                              Text(
                                '$selectedCount',
                                style: const TextStyle(
                                  fontSize: 34,
                                  fontWeight: FontWeight.w900,
                                  color: FoodFlowTheme.ink,
                                ),
                              ),
                              Text(
                                selectedCount == 1 ? 'guest' : 'guests',
                                style: TextStyle(
                                  color: Colors.grey.shade600,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                          _CounterButton(
                            icon: Icons.add,
                            onTap: () => setModalState(() => selectedCount += 1),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: () => Navigator.pop(context, selectedCount),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppConfig.primaryColor,
                          padding: const EdgeInsets.symmetric(vertical: 15),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(18),
                          ),
                        ),
                        child: const Text(
                          'Done',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );

    if (result != null) {
      setState(() => _guestCount = result);
    }
  }

  Widget _buildDetailTile({
    required String label,
    required String value,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Icon(
                Icons.event_available,
                color: AppConfig.primaryColor,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label,
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 6),
                  Text(value, style: const TextStyle(color: Colors.grey)),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, size: 20, color: Colors.grey),
          ],
        ),
      ),
    );
  }

  Widget _buildHeroCard(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFE23744), Color(0xFFFF8A5C)],
        ),
        borderRadius: BorderRadius.circular(28),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.16),
              borderRadius: BorderRadius.circular(999),
            ),
            child: const Text(
              'DINING EXPERIENCE',
              style: TextStyle(
                color: Colors.white,
                fontSize: 11,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.8,
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            widget.restaurantName,
            style: const TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w900,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            widget.diningCharge > 0
                ? 'Reserve your table from ${formatCurrency(context, widget.diningCharge)} cover per guest.'
                : 'Reserve your table and arrive to a table-ready experience.',
            style: TextStyle(
              fontSize: 14,
              height: 1.45,
              color: Colors.white.withOpacity(0.92),
            ),
          ),
          const SizedBox(height: 18),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _buildHeroPill(
                Icons.calendar_today_outlined,
                _formatDateLabel(_selectedDate),
              ),
              _buildHeroPill(Icons.schedule, _selectedTime.format(context)),
              _buildHeroPill(
                Icons.groups_2_outlined,
                '$_guestCount ${_guestCount == 1 ? 'guest' : 'guests'}',
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildReservationCard(BuildContext context) {
    return _buildCard(
      title: 'Reservation details',
      subtitle: 'Choose the timing and table size that fits your plan.',
      child: Column(
        children: [
          _buildDetailTile(
            label: 'Date',
            value: _formatDateLabel(_selectedDate),
            onTap: _chooseDate,
          ),
          const SizedBox(height: 12),
          _buildDetailTile(
            label: 'Time',
            value: _selectedTime.format(context),
            onTap: _chooseTime,
          ),
          const SizedBox(height: 12),
          _buildDetailTile(
            label: 'Guests',
            value: '$_guestCount ${_guestCount == 1 ? 'person' : 'people'}',
            onTap: _pickGuestCount,
          ),
        ],
      ),
    );
  }

  Widget _buildCelebrationCard() {
    return _buildCard(
      title: 'Occasion vibe',
      subtitle: 'Optional, but it helps the restaurant prepare better.',
      child: _isLoading
          ? const Center(
              child: Padding(
                padding: EdgeInsets.symmetric(vertical: 16),
                child: CircularProgressIndicator(),
              ),
            )
          : _celebrationTypes.isNotEmpty
              ? Wrap(
                  spacing: 8,
                  runSpacing: 10,
                  children: _celebrationTypes.map((type) {
                    final title = type['title']?.toString() ??
                        type['name']?.toString() ??
                        'Event';
                    final value =
                        type['value']?.toString() ?? type['id']?.toString();
                    final isSelected = value == _selectedCelebrationType;
                    return ChoiceChip(
                      label: Text(title),
                      selected: isSelected,
                      showCheckmark: false,
                      backgroundColor: const Color(0xFFFFF5EF),
                      selectedColor:
                          AppConfig.primaryColor.withOpacity(0.14),
                      side: BorderSide(
                        color: isSelected
                            ? AppConfig.primaryColor.withOpacity(0.18)
                            : Colors.transparent,
                      ),
                      labelStyle: TextStyle(
                        color: isSelected
                            ? AppConfig.primaryColor
                            : FoodFlowTheme.ink,
                        fontWeight: FontWeight.w700,
                      ),
                      onSelected: (selected) {
                        if (selected && value != null) {
                          setState(() => _selectedCelebrationType = value);
                        }
                      },
                    );
                  }).toList(),
                )
              : Container(
                  width: double.infinity,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF7F2),
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: Text(
                    'You can continue without selecting a celebration type.',
                    style: TextStyle(
                      color: Colors.grey.shade700,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
    );
  }

  Widget _buildRequestsCard() {
    return _buildCard(
      title: 'Special requests',
      subtitle: 'Share anything that can make the visit smoother.',
      child: TextFormField(
        controller: _requestsController,
        minLines: 4,
        maxLines: 5,
        decoration: InputDecoration(
          hintText: 'Birthday cake, quiet corner, stroller space, accessibility, etc.',
          filled: true,
          fillColor: const Color(0xFFFFFBF8),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(18),
            borderSide: BorderSide(color: Colors.grey.shade200),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(18),
            borderSide: BorderSide(color: Colors.grey.shade200),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(18),
            borderSide: const BorderSide(
              color: AppConfig.primaryColor,
              width: 1.3,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCard({
    required String title,
    required String subtitle,
    required Widget child,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              color: FoodFlowTheme.ink,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: TextStyle(
              color: Colors.grey.shade600,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }

  Widget _buildHeroPill(IconData icon, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.16),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: Colors.white),
          const SizedBox(width: 6),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _wrapPickerTheme(Widget? child) {
    return Theme(
      data: Theme.of(context).copyWith(
        colorScheme: Theme.of(context).colorScheme.copyWith(
              primary: AppConfig.primaryColor,
              surface: Colors.white,
            ),
        dialogTheme: const DialogThemeData(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(28)),
          ),
        ),
      ),
      child: child ?? const SizedBox.shrink(),
    );
  }

  String _formatDateLabel(DateTime date) {
    return DateFormat('EEE, d MMM').format(date);
  }

  String _extractBookingError(Map<String, dynamic> response) {
    final message = response['message']?.toString();
    if (message != null && message.trim().isNotEmpty) {
      return message;
    }

    final errors = response['errors'];
    if (errors is Map) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }
        if (value != null) {
          return value.toString();
        }
      }
    }

    return 'Booking failed. Please try again.';
  }

  void _showStatusSnackBar(String message, {bool isSuccess = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor:
            isSuccess ? const Color(0xFF138A5A) : const Color(0xFFE23744),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }
}

class _CounterButton extends StatelessWidget {
  const _CounterButton({
    required this.icon,
    required this.onTap,
  });

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Ink(
        width: 52,
        height: 52,
        decoration: BoxDecoration(
          color: onTap == null ? Colors.grey.shade200 : Colors.white,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Icon(
          icon,
          color: onTap == null ? Colors.grey.shade400 : AppConfig.primaryColor,
        ),
      ),
    );
  }
}
