// lib/models/order.dart
import 'dart:convert';
import 'package:flutter/material.dart';
import '../utils/json_utils.dart';
import 'restaurant.dart';
import 'user.dart';

class Order {
  final int id;
  final String orderNumber;
  final int restaurantId;
  final int? customerId;
  final int? driverId;
  final String orderType;
  final String customerName;
  final String customerPhone;
  final String deliveryAddress;
  final double? deliveryLat;
  final double? deliveryLng;
  final double? deliveryDistanceKm;
  final List<OrderItem> items;
  final double subtotal;
  final double deliveryFee;
  final double platformFee;
  final double tax;
  final double discount;
  final double total;
  String status;
  final String paymentMethod;
  final String paymentStatus;
  final String? deliveryPaymentMode;
  final double? cashCollectedAmount;
  final DateTime? cashCollectedAt;
  final DateTime? onlinePaymentVerifiedAt;
  final DateTime? scheduledTime;
  final String? specialInstructions;
  final String? cancellationReason;
  final String? refundStatus;
  final double? refundAmount;
  final String? refundMode;
  final String? refundModeLabel;
  final String? refundTransactionId;
  final DateTime createdAt;
  final DateTime? confirmedAt;
  final DateTime? deliveredAt;
  final DateTime? cancelledAt;
  final int? restaurantRating;
  final int? driverRating;
  final String? restaurantFeedback;
  final String? driverFeedback;
  final DateTime? feedbackSubmittedAt;
  final int driverAssignmentAttempts;
  final DateTime? driverAssignedAt;
  final DateTime? driverAcceptedAt;
  final Map<String, dynamic> eta;

  // Relations (loaded separately)
  final Restaurant? restaurant;
  final User? driver;
  final String? deliveryOtp;

  Order({
    required this.id,
    required this.orderNumber,
    required this.restaurantId,
    this.customerId,
    this.driverId,
    this.orderType = 'delivery',
    required this.customerName,
    required this.customerPhone,
    required this.deliveryAddress,
    this.deliveryLat,
    this.deliveryLng,
    this.deliveryDistanceKm,
    required this.items,
    required this.subtotal,
    required this.deliveryFee,
    required this.platformFee,
    required this.tax,
    required this.discount,
    required this.total,
    required this.status,
    required this.paymentMethod,
    required this.paymentStatus,
    this.deliveryPaymentMode,
    this.cashCollectedAmount,
    this.cashCollectedAt,
    this.onlinePaymentVerifiedAt,
    this.scheduledTime,
    this.specialInstructions,
    this.cancellationReason,
    this.refundStatus,
    this.refundAmount,
    this.refundMode,
    this.refundModeLabel,
    this.refundTransactionId,
    required this.createdAt,
    this.confirmedAt,
    this.deliveredAt,
    this.cancelledAt,
    this.restaurantRating,
    this.driverRating,
    this.restaurantFeedback,
    this.driverFeedback,
    this.feedbackSubmittedAt,
    this.driverAssignmentAttempts = 0,
    this.driverAssignedAt,
    this.driverAcceptedAt,
    this.eta = const {},
    this.restaurant,
    this.driver,
    this.deliveryOtp,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    List<OrderItem> itemsList = [];
    if (json['items'] != null) {
      if (json['items'] is String) {
        try {
          final decoded = jsonDecode(json['items']);
          if (decoded is List) {
            itemsList =
                decoded.map((item) => OrderItem.fromJson(item)).toList();
          }
        } catch (e) {
          itemsList = [];
        }
      } else if (json['items'] is List) {
        itemsList = (json['items'] as List)
            .map((item) => OrderItem.fromJson(item))
            .toList();
      }
    }

    return Order(
      id: parseIntValue(json['id']),
      orderNumber: json['order_number'] ?? 'ORD${json['id']}',
      restaurantId: parseIntValue(json['restaurant_id']),
      customerId: parseNullableInt(json['customer_id']),
      driverId: parseNullableInt(json['driver_id']),
      orderType: json['order_type']?.toString() ?? 'delivery',
      customerName: json['customer_name'] ?? '',
      customerPhone: json['customer_phone'] ?? '',
      deliveryAddress: json['delivery_address'] ?? '',
      deliveryLat: parseNullableDouble(json['delivery_lat']),
      deliveryLng: parseNullableDouble(json['delivery_lng']),
      deliveryDistanceKm: parseNullableDouble(
        json['delivery_distance_km'] ?? json['travel_distance_km'],
      ),
      items: itemsList,
      subtotal: parseDoubleValue(json['subtotal']),
      deliveryFee: parseDoubleValue(json['delivery_fee']),
      platformFee: parseDoubleValue(json['platform_fee']),
      tax: parseDoubleValue(json['tax']),
      discount: parseDoubleValue(json['discount']),
      total: parseDoubleValue(json['total']),
      status: (json['status'] ?? 'pending')
          .toString()
          .trim()
          .toLowerCase()
          .replaceAll('-', '_')
          .replaceAll(' ', '_'),
      paymentMethod: json['payment_method'] ?? 'cod',
      paymentStatus: json['payment_status'] ?? 'pending',
      deliveryPaymentMode: json['delivery_payment_mode']?.toString(),
      cashCollectedAmount: parseNullableDouble(json['cash_collected_amount']),
      cashCollectedAt: json['cash_collected_at'] != null
          ? DateTime.parse(json['cash_collected_at'])
          : null,
      onlinePaymentVerifiedAt: json['online_payment_verified_at'] != null
          ? DateTime.parse(json['online_payment_verified_at'])
          : null,
      scheduledTime: json['scheduled_time'] != null
          ? DateTime.tryParse(json['scheduled_time'].toString())
          : null,
      specialInstructions: json['special_instructions']?.toString(),
      cancellationReason: json['cancellation_reason'],
      refundStatus: json['refund_status'],
      refundAmount: parseNullableDouble(json['refund_amount']),
      refundMode: json['refund_mode']?.toString(),
      refundModeLabel: json['refund_mode_label']?.toString(),
      refundTransactionId: json['refund_transaction_id']?.toString(),
      createdAt: DateTime.parse(
          json['created_at'] ?? DateTime.now().toIso8601String()),
      confirmedAt: json['confirmed_at'] != null
          ? DateTime.parse(json['confirmed_at'])
          : null,
      deliveredAt: json['delivered_at'] != null
          ? DateTime.parse(json['delivered_at'])
          : null,
      cancelledAt: json['cancelled_at'] != null
          ? DateTime.parse(json['cancelled_at'])
          : null,
      restaurantRating: parseNullableInt(json['restaurant_rating']),
      driverRating: parseNullableInt(json['driver_rating']),
      restaurantFeedback: json['restaurant_feedback']?.toString(),
      driverFeedback: json['driver_feedback']?.toString(),
      feedbackSubmittedAt: json['feedback_submitted_at'] != null
          ? DateTime.parse(json['feedback_submitted_at'])
          : null,
      driverAssignmentAttempts:
          parseIntValue(json['driver_assignment_attempts'] ?? 0),
      driverAssignedAt: json['driver_assigned_at'] != null
          ? DateTime.parse(json['driver_assigned_at'])
          : null,
      driverAcceptedAt: json['driver_accepted_at'] != null
          ? DateTime.parse(json['driver_accepted_at'])
          : null,
      eta: json['eta'] is Map
          ? Map<String, dynamic>.from(json['eta'])
          : <String, dynamic>{
              if (json['estimated_delivery_minutes'] != null)
                'eta_minutes': json['estimated_delivery_minutes'],
              if (json['estimated_delivery_label'] != null)
                'eta_range': json['estimated_delivery_label'],
            },
      restaurant: json['restaurant'] != null
          ? Restaurant.fromJson(json['restaurant'])
          : null,
      driver: json['driver'] != null ? User.fromJson(json['driver']) : null,
      deliveryOtp: (json['delivery_otp'] ??
              json['otp'] ??
              json['delivery_code'] ??
              json['verification_code'])
          ?.toString(),
    );
  }

  bool get isPending => status == 'pending';
  bool get isConfirmed => status == 'confirmed';
  bool get isPreparing => status == 'preparing';
  bool get isReadyForPickup => status == 'ready_for_pickup';
  bool get isReachedPickup => status == 'reached_pickup';
  bool get isPickedUp => status == 'picked_up';
  bool get isOnTheWay => status == 'on_the_way';
  bool get isDelivered => status == 'delivered';
  bool get isCancelled => status == 'cancelled';
  bool get isTakeaway => orderType == 'takeaway';
  bool get isDriverAssignmentPending =>
      !isTakeaway &&
      driverId != null &&
      driverAcceptedAt == null &&
      (isConfirmed || isPreparing || isReadyForPickup);
  bool get hasFeedback =>
      feedbackSubmittedAt != null || restaurantRating != null;
  bool get needsFeedback => isDelivered && !hasFeedback;

  void applyRealtimeStatus(String nextStatus) {
    final normalized = nextStatus.trim().toLowerCase().replaceAll('-', '_');
    if (normalized.isNotEmpty) status = normalized;
  }

  DateTime get customerCancellationClosesAt =>
      createdAt.add(const Duration(minutes: 2));

  Duration get remainingCancellationTime =>
      customerCancellationClosesAt.difference(DateTime.now());

  bool get canCancel =>
      isPending && remainingCancellationTime.inSeconds > 0;

  bool get canForceCancel =>
      !isCancelled &&
      !isDelivered &&
      ['confirmed', 'preparing', 'ready_for_pickup'].contains(status);

  bool get canRequestRefund => isDelivered && refundStatus == null;
  int? get etaMinutes {
    final value = eta['eta_minutes'];
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  String? get etaRange {
    final value = eta['eta_range']?.toString().trim();
    if (value == null || value.isEmpty) return null;
    return value;
  }

  double? get etaDistanceKm {
    final value = deliveryDistanceKm ?? eta['travel_distance_km'];
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  String? get deliveryDistanceLabel {
    final distance = etaDistanceKm;
    if (distance == null || distance <= 0) return null;
    return '${distance.toStringAsFixed(distance >= 10 ? 0 : 1)} km';
  }

  String get statusText {
    if (isTakeaway) {
      switch (status) {
        case 'pending':
          return 'Order Placed';
        case 'confirmed':
          return 'Restaurant Confirmed';
        case 'preparing':
          return 'Preparing Food';
        case 'ready_for_pickup':
          return 'Ready to Collect';
        case 'picked_up':
        case 'delivered':
          return 'Picked Up';
        case 'cancelled':
          return 'Cancelled';
        default:
          return status;
      }
    }

    switch (status) {
      case 'pending':
        return 'Order Placed';
      case 'confirmed':
        return 'Order Confirmed';
      case 'preparing':
        return 'Preparing Food';
      case 'ready_for_pickup':
        return 'Ready for Pickup';
      case 'reached_pickup':
        return 'Reached Pickup';
      case 'picked_up':
        return 'Picked Up';
      case 'on_the_way':
        return 'On The Way';
      case 'delivered':
        return 'Delivered';
      case 'cancelled':
        return 'Cancelled';
      default:
        return status;
    }
  }

  Color get statusColor {
    switch (status) {
      case 'pending':
        return Colors.orange;
      case 'confirmed':
        return Colors.blue;
      case 'preparing':
        return Colors.purple;
      case 'ready_for_pickup':
        return Colors.teal;
      case 'reached_pickup':
        return Colors.amber;
      case 'picked_up':
        return Colors.indigo;
      case 'on_the_way':
        return Colors.cyan;
      case 'delivered':
        return Colors.green;
      case 'cancelled':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }
}

class OrderItem {
  final int? menuItemId;
  final String name;
  final int quantity;
  final double unitPrice;
  final double totalPrice;
  final double price; // Non-nullable, defaults to unitPrice

  OrderItem({
    this.menuItemId,
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    double? price,
  }) : price = price ?? unitPrice;

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    final unitPrice = parseDoubleValue(json['price'] ?? json['unit_price']);
    return OrderItem(
      menuItemId: parseNullableInt(json['menu_item_id'] ?? json['id']),
      name: json['name'] ?? json['item_name'] ?? '',
      quantity: parseIntValue(json['quantity'] ?? 1),
      unitPrice: unitPrice,
      totalPrice: parseDoubleValue(json['total'] ?? json['total_price']),
      price: unitPrice,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'menu_item_id': menuItemId,
      'name': name,
      'quantity': quantity,
      'unit_price': unitPrice,
      'total_price': totalPrice,
    };
  }
}
