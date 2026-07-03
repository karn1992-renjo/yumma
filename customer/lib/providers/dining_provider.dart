// lib/providers/dining_provider.dart

import 'package:flutter/material.dart';
import '../models/dining_booking.dart';
import '../services/api_service.dart';
import '../config/api_constants.dart';

class DiningProvider extends ChangeNotifier {
  final ApiService _api = ApiService();

  // State variables
  List<DiningBooking> _bookings = [];
  List<CelebrationType> _celebrationTypes = [];
  DiningBooking? _currentBooking;
  bool _isLoading = false;
  bool _isSubmitting = false;
  String? _error;

  // Getters
  List<DiningBooking> get bookings => _bookings;
  List<CelebrationType> get celebrationTypes => _celebrationTypes;
  DiningBooking? get currentBooking => _currentBooking;
  bool get isLoading => _isLoading;
  bool get isSubmitting => _isSubmitting;
  String? get error => _error;

  DiningProvider() {
    _initializeData();
  }

  void _initializeData() {
    loadCelebrationTypes();
  }

  void _setLoading(bool value) {
    _isLoading = value;
    notifyListeners();
  }

  void _setSubmitting(bool value) {
    _isSubmitting = value;
    notifyListeners();
  }

  void _setError(String? error) {
    _error = error;
    notifyListeners();
  }

  void _clearError() {
    _error = null;
  }

  // Load celebration types
  Future<void> loadCelebrationTypes() async {
    _setLoading(true);
    _clearError();

    try {
      final response = await _api.get(ApiConstants.diningCelebrationTypes);
      
      if (response['success'] == true) {
        final data = _extractList(response['data']);
        _celebrationTypes = data
            .map((item) => CelebrationType.fromJson(item as Map<String, dynamic>))
            .toList();
      } else {
        _setError(response['message'] ?? 'Failed to load celebration types');
      }
    } catch (e) {
      _setError('Error loading celebration types: $e');
      print('Error: $e');
    } finally {
      _setLoading(false);
    }
  }

  // Fetch user's bookings
  Future<void> fetchMyBookings() async {
    _setLoading(true);
    _clearError();

    try {
      final response = await _api.get(ApiConstants.diningMyBookings);
      
      if (response['success'] == true) {
        final data = _extractList(response['data']);
        _bookings = data
            .map((item) => DiningBooking.fromJson(item as Map<String, dynamic>))
            .toList();
      } else {
        _setError(response['message'] ?? 'Failed to load bookings');
      }
    } catch (e) {
      _setError('Error loading bookings: $e');
      print('Error: $e');
    } finally {
      _setLoading(false);
    }
  }

  // Get single booking details
  Future<DiningBooking?> getBookingDetails(int bookingId) async {
    _setLoading(true);
    _clearError();

    try {
      final response = await _api.get(
        ApiConstants.diningBookingDetail(bookingId),
      );
      
      if (response['success'] == true) {
        final booking = DiningBooking.fromJson(response['data'] as Map<String, dynamic>);
        _currentBooking = booking;
        return booking;
      } else {
        _setError(response['message'] ?? 'Failed to load booking details');
        return null;
      }
    } catch (e) {
      _setError('Error loading booking details: $e');
      print('Error: $e');
      return null;
    } finally {
      _setLoading(false);
    }
  }

  // Create dining booking
  Future<DiningBooking?> createBooking({
    required int restaurantId,
    required DateTime bookingDate,
    required TimeOfDay bookingTime,
    required int numberOfGuests,
    String? celebrationType,
    String? specialRequests,
  }) async {
    _setSubmitting(true);
    _clearError();

    try {
      final bookingData = {
        'restaurant_id': restaurantId,
        'booking_date': bookingDate.toIso8601String().split('T')[0],
        'booking_time': '${bookingTime.hour.toString().padLeft(2, '0')}:${bookingTime.minute.toString().padLeft(2, '0')}',
        'number_of_guests': numberOfGuests,
        'celebration_type': celebrationType,
        'special_requests': specialRequests,
      };

      final response = await _api.post(
        ApiConstants.diningBook,
        data: bookingData,
      );

      if (response['success'] == true) {
        final booking = DiningBooking.fromJson(response['data'] as Map<String, dynamic>);
        _currentBooking = booking;
        _bookings.insert(0, booking);
        return booking;
      } else {
        _setError(response['message'] ?? 'Failed to create booking');
        return null;
      }
    } catch (e) {
      _setError('Error creating booking: $e');
      print('Error: $e');
      return null;
    } finally {
      _setSubmitting(false);
    }
  }

  // Cancel booking
  Future<bool> cancelBooking(int bookingId, String reason) async {
    _setSubmitting(true);
    _clearError();

    try {
      final response = await _api.post(
        '${ApiConstants.diningCancel}/$bookingId',
        data: {'cancellation_reason': reason},
      );

      if (response['success'] == true) {
        // Update booking in list
        final index = _bookings.indexWhere((b) => b.id == bookingId);
        if (index >= 0) {
          _bookings[index] = _bookings[index].copyWith(
            status: 'cancelled',
            cancellationReason: reason,
          );
        }
        
        // Update current booking
        if (_currentBooking?.id == bookingId) {
          _currentBooking = _currentBooking?.copyWith(
            status: 'cancelled',
            cancellationReason: reason,
          );
        }

        return true;
      } else {
        _setError(response['message'] ?? 'Failed to cancel booking');
        return false;
      }
    } catch (e) {
      _setError('Error cancelling booking: $e');
      print('Error: $e');
      return false;
    } finally {
      _setSubmitting(false);
    }
  }

  // Submit review/rating for completed booking
  Future<bool> submitReview({
    required int bookingId,
    required double rating,
    required String feedback,
  }) async {
    _setSubmitting(true);
    _clearError();

    try {
      final response = await _api.post(
        ApiConstants.diningBookingReview(bookingId),
        data: {
          'rating': rating,
          'feedback': feedback,
        },
      );

      if (response['success'] == true) {
        // Update booking in list
        final index = _bookings.indexWhere((b) => b.id == bookingId);
        if (index >= 0) {
          _bookings[index] = _bookings[index].copyWith(rating: rating);
        }

        if (_currentBooking?.id == bookingId) {
          _currentBooking = _currentBooking?.copyWith(rating: rating);
        }

        return true;
      } else {
        _setError(response['message'] ?? 'Failed to submit review');
        return false;
      }
    } catch (e) {
      _setError('Error submitting review: $e');
      print('Error: $e');
      return false;
    } finally {
      _setSubmitting(false);
    }
  }

  // Clear current booking
  void clearCurrentBooking() {
    _currentBooking = null;
    _clearError();
    notifyListeners();
  }

  // Filter bookings by status
  List<DiningBooking> getBookingsByStatus(String status) {
    return _bookings.where((b) => b.status == status).toList();
  }

  // Get upcoming bookings
  List<DiningBooking> getUpcomingBookings() {
    return _bookings
        .where((b) => b.isPending || b.isConfirmed)
        .toList();
  }

  // Get past bookings
  List<DiningBooking> getPastBookings() {
    return _bookings
        .where((b) => b.isCompleted || b.isCancelled)
        .toList();
  }

  List<dynamic> _extractList(dynamic data) {
    if (data is List) return data;
    if (data is Map<String, dynamic>) {
      final nested = data['data'];
      if (nested is List) return nested;
    }
    return [];
  }
}
