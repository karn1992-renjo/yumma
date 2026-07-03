// lib/models/dining_booking.dart
import 'package:flutter/material.dart';

class DiningBooking {
  final int id;
  final int restaurantId;
  final int userId;
  final String bookingNumber;
  final DateTime bookingDate;
  final TimeOfDay bookingTime;
  final int numberOfGuests;
  final String? celebrationType;
  final String? specialRequests;
  final String status; // pending, confirmed, completed, cancelled
  final double bookingCharge;
  final String paymentStatus;
  final String? paymentMethod;
  final String? paymentId;
  final DateTime? confirmedAt;
  final DateTime? cancelledAt;
  final String? cancellationReason;
  final DateTime? onlinePaymentVerifiedAt;
  final DateTime createdAt;
  final DateTime updatedAt;

  // Additional fields for UI
  final String? restaurantName;
  final String? restaurantImage;
  final double? rating;

  DiningBooking({
    required this.id,
    required this.restaurantId,
    required this.userId,
    required this.bookingNumber,
    required this.bookingDate,
    required this.bookingTime,
    required this.numberOfGuests,
    this.celebrationType,
    this.specialRequests,
    required this.status,
    required this.bookingCharge,
    this.paymentStatus = 'pending',
    this.paymentMethod,
    this.paymentId,
    this.confirmedAt,
    this.cancelledAt,
    this.cancellationReason,
    this.onlinePaymentVerifiedAt,
    required this.createdAt,
    required this.updatedAt,
    this.restaurantName,
    this.restaurantImage,
    this.rating,
  });

  bool get isPending => status == 'pending';
  bool get isConfirmed => status == 'confirmed';
  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';
  bool get canBeCancelled => isPending || isConfirmed;
  bool get isPaymentSuccessful => paymentStatus == 'success';

  factory DiningBooking.fromJson(Map<String, dynamic> json) {
    return DiningBooking(
      id: json['id'] as int? ?? 0,
      restaurantId: json['restaurant_id'] as int? ?? 0,
      userId: json['user_id'] as int? ?? 0,
      bookingNumber: json['booking_number'] as String? ?? '',
      bookingDate: json['booking_date'] != null
          ? DateTime.parse(json['booking_date'] as String)
          : DateTime.now(),
      bookingTime: _parseTimeOfDay(json['booking_time'] as String?),
      numberOfGuests: json['number_of_guests'] as int? ?? 1,
      celebrationType: json['celebration_type'] as String?,
      specialRequests: json['special_requests'] as String?,
      status: json['status'] as String? ?? 'pending',
      bookingCharge: _parseDouble(json['booking_charge']),
      paymentStatus: json['payment_status'] as String? ?? 'pending',
      paymentMethod: json['payment_method'] as String?,
      paymentId: json['payment_id'] as String?,
      confirmedAt: json['confirmed_at'] != null
          ? DateTime.parse(json['confirmed_at'] as String)
          : null,
      cancelledAt: json['cancelled_at'] != null
          ? DateTime.parse(json['cancelled_at'] as String)
          : null,
      cancellationReason: json['cancellation_reason'] as String?,
      onlinePaymentVerifiedAt: json['online_payment_verified_at'] != null
          ? DateTime.parse(json['online_payment_verified_at'] as String)
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'] as String)
          : DateTime.now(),
      updatedAt: json['updated_at'] != null
          ? DateTime.parse(json['updated_at'] as String)
          : DateTime.now(),
      restaurantName: json['restaurant_name'] as String? ??
          (json['restaurant'] is Map ? json['restaurant']['name']?.toString() : null),
      restaurantImage: json['restaurant_image'] as String? ??
          (json['restaurant'] is Map
              ? (json['restaurant']['banner_image'] ??
                      json['restaurant']['logo_image'] ??
                      json['restaurant']['image_url'])
                  ?.toString()
              : null),
      rating: _parseDouble(json['rating']),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'restaurant_id': restaurantId,
    'user_id': userId,
    'booking_number': bookingNumber,
    'booking_date': bookingDate.toIso8601String().split('T')[0],
    'booking_time': '${bookingTime.hour.toString().padLeft(2, '0')}:${bookingTime.minute.toString().padLeft(2, '0')}',
    'number_of_guests': numberOfGuests,
    'celebration_type': celebrationType,
    'special_requests': specialRequests,
    'status': status,
    'booking_charge': bookingCharge,
    'payment_status': paymentStatus,
    'payment_method': paymentMethod,
    'payment_id': paymentId,
  };

  static TimeOfDay _parseTimeOfDay(String? timeString) {
    if (timeString == null || timeString.isEmpty) {
      return const TimeOfDay(hour: 19, minute: 0);
    }
    try {
      if (timeString.contains('T')) {
        final dateTime = DateTime.parse(timeString);
        return TimeOfDay(hour: dateTime.hour, minute: dateTime.minute);
      }
      final parts = timeString.split(':');
      if (parts.length >= 2) {
        return TimeOfDay(
          hour: int.parse(parts[0]),
          minute: int.parse(parts[1]),
        );
      }
    } catch (e) {
      print('Error parsing time: $e');
    }
    return const TimeOfDay(hour: 19, minute: 0);
  }

  static double _parseDouble(dynamic value) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  DiningBooking copyWith({
    int? id,
    int? restaurantId,
    int? userId,
    String? bookingNumber,
    DateTime? bookingDate,
    TimeOfDay? bookingTime,
    int? numberOfGuests,
    String? celebrationType,
    String? specialRequests,
    String? status,
    double? bookingCharge,
    String? paymentStatus,
    String? paymentMethod,
    String? paymentId,
    DateTime? confirmedAt,
    DateTime? cancelledAt,
    String? cancellationReason,
    DateTime? onlinePaymentVerifiedAt,
    DateTime? createdAt,
    DateTime? updatedAt,
    String? restaurantName,
    String? restaurantImage,
    double? rating,
  }) =>
      DiningBooking(
        id: id ?? this.id,
        restaurantId: restaurantId ?? this.restaurantId,
        userId: userId ?? this.userId,
        bookingNumber: bookingNumber ?? this.bookingNumber,
        bookingDate: bookingDate ?? this.bookingDate,
        bookingTime: bookingTime ?? this.bookingTime,
        numberOfGuests: numberOfGuests ?? this.numberOfGuests,
        celebrationType: celebrationType ?? this.celebrationType,
        specialRequests: specialRequests ?? this.specialRequests,
        status: status ?? this.status,
        bookingCharge: bookingCharge ?? this.bookingCharge,
        paymentStatus: paymentStatus ?? this.paymentStatus,
        paymentMethod: paymentMethod ?? this.paymentMethod,
        paymentId: paymentId ?? this.paymentId,
        confirmedAt: confirmedAt ?? this.confirmedAt,
        cancelledAt: cancelledAt ?? this.cancelledAt,
        cancellationReason: cancellationReason ?? this.cancellationReason,
        onlinePaymentVerifiedAt:
            onlinePaymentVerifiedAt ?? this.onlinePaymentVerifiedAt,
        createdAt: createdAt ?? this.createdAt,
        updatedAt: updatedAt ?? this.updatedAt,
        restaurantName: restaurantName ?? this.restaurantName,
        restaurantImage: restaurantImage ?? this.restaurantImage,
        rating: rating ?? this.rating,
      );
}

class CelebrationType {
  final int id;
  final String name;
  final String? icon;
  final bool isActive;
  final int displayOrder;

  CelebrationType({
    required this.id,
    required this.name,
    this.icon,
    required this.isActive,
    required this.displayOrder,
  });

  factory CelebrationType.fromJson(Map<String, dynamic> json) {
    return CelebrationType(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      icon: json['icon'] as String?,
      isActive: json['is_active'] == true,
      displayOrder: json['display_order'] as int? ?? 0,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'icon': icon,
    'is_active': isActive,
    'display_order': displayOrder,
  };
}
