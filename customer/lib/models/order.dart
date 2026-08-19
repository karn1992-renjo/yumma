// lib/models/order.dart
import 'dart:convert';
import 'package:flutter/material.dart';
import '../utils/json_utils.dart';
import 'restaurant.dart';
import 'user.dart';

class Order {
  final int id;
  final String orderNumber;
  final String serviceType;
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
  final double? tip;
  final DateTime? tipPaidAt;
  String status;
  final String paymentMethod;
  final String paymentStatus;
  final String? paymentSource;
  final String? paymentGateway;
  final String? paymentLinkId;
  final DateTime? paidAt;
  final PaymentAttempt? activePaymentAttempt;
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
  final DateTime? preparingAt;
  final int? preparationTimeMinutes;
  final DateTime? readyByAt;
  final DateTime? readyAt;
  final DateTime? reachedAt;
  final int? readyCountdownSeconds;
  final bool isPreparationDelayed;
  final int preparationDelayMinutes;
  final int? remainingPreparationMinutes;
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
  final double? driverLat;
  final double? driverLng;
  final DateTime? driverLocationUpdatedAt;
  final Map<String, dynamic> eta;

  // Relations (loaded separately)
  final Restaurant? restaurant;
  final User? driver;
  final String? deliveryOtp;

  Order({
    required this.id,
    required this.orderNumber,
    this.serviceType = 'food',
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
    this.tip,
    this.tipPaidAt,
    required this.status,
    required this.paymentMethod,
    required this.paymentStatus,
    this.paymentSource,
    this.paymentGateway,
    this.paymentLinkId,
    this.paidAt,
    this.activePaymentAttempt,
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
    this.preparingAt,
    this.preparationTimeMinutes,
    this.readyByAt,
    this.readyAt,
    this.reachedAt,
    this.readyCountdownSeconds,
    this.isPreparationDelayed = false,
    this.preparationDelayMinutes = 0,
    this.remainingPreparationMinutes,
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
    this.driverLat,
    this.driverLng,
    this.driverLocationUpdatedAt,
    this.eta = const {},
    this.restaurant,
    this.driver,
    this.deliveryOtp,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    final preparation = json['preparation'] is Map
        ? Map<String, dynamic>.from(json['preparation'] as Map)
        : const <String, dynamic>{};
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

    final driverLocation = json['driver_location'] is Map
        ? Map<String, dynamic>.from(json['driver_location'] as Map)
        : const <String, dynamic>{};

    return Order(
      id: parseIntValue(json['id']),
      orderNumber: json['order_number'] ?? 'ORD${json['id']}',
      serviceType: json['service_type']?.toString() ??
          (json['restaurant'] is Map
              ? ((json['restaurant'] as Map)['service_type']?.toString() ??
                  'food')
              : 'food'),
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
      tip: parseNullableDouble(json['tip_amount']),
      tipPaidAt: json['tip_paid_at'] != null
          ? DateTime.tryParse(json['tip_paid_at'].toString())
          : null,
      status: (json['status'] ?? 'pending')
          .toString()
          .trim()
          .toLowerCase()
          .replaceAll('-', '_')
          .replaceAll(' ', '_'),
      paymentMethod: json['payment_method'] ?? 'cod',
      paymentStatus: json['payment_status'] ?? 'pending',
      paymentSource: json['payment_source']?.toString(),
      paymentGateway: json['payment_gateway']?.toString(),
      paymentLinkId: json['payment_link_id']?.toString(),
      paidAt: json['paid_at'] != null
          ? DateTime.tryParse(json['paid_at'].toString())
          : null,
      activePaymentAttempt: PaymentAttempt.fromJsonOrNull(
        json['active_payment_attempt'] ??
            (json['payment_summary'] is Map
                ? (json['payment_summary'] as Map)['active_attempt']
                : null),
      ),
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
      preparingAt: json['preparing_at'] != null
          ? DateTime.tryParse(json['preparing_at'].toString())
          : null,
      preparationTimeMinutes: parseNullableInt(
          json['preparation_time_minutes'] ??
              preparation['preparation_time_minutes']),
      readyByAt: (json['ready_by_at'] ?? preparation['ready_by_at']) != null
          ? DateTime.tryParse(
              (json['ready_by_at'] ?? preparation['ready_by_at']).toString())
          : null,
      readyAt: json['ready_at'] != null
          ? DateTime.tryParse(json['ready_at'].toString())
          : null,
      reachedAt: json['reached_at'] != null
          ? DateTime.tryParse(json['reached_at'].toString())
          : null,
      readyCountdownSeconds: parseNullableInt(
        json['ready_countdown_seconds'] ??
            preparation['ready_countdown_seconds'],
      ),
      isPreparationDelayed: json['is_preparation_delayed'] == true ||
          preparation['is_preparation_delayed'] == true ||
          json['is_preparation_delayed']?.toString() == '1' ||
          preparation['is_preparation_delayed']?.toString() == '1',
      preparationDelayMinutes: parseIntValue(
          json['preparation_delay_minutes'] ??
              preparation['preparation_delay_minutes'] ??
              0),
      remainingPreparationMinutes: parseNullableInt(
          json['remaining_preparation_minutes'] ??
              preparation['remaining_preparation_minutes']),
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
      driverLat: parseNullableDouble(
        driverLocation['lat'] ??
            driverLocation['latitude'] ??
            json['driver_lat'] ??
            json['driver_latitude'],
      ),
      driverLng: parseNullableDouble(
        driverLocation['lng'] ??
            driverLocation['longitude'] ??
            json['driver_lng'] ??
            json['driver_longitude'],
      ),
      driverLocationUpdatedAt: driverLocation['updated_at'] != null
          ? DateTime.tryParse(driverLocation['updated_at'].toString())
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
  bool get hasLiveDriverLocation =>
      driverLat != null &&
      driverLat != 0.0 &&
      driverLng != null &&
      driverLng != 0.0;
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

  bool get canCancel => isPending && remainingCancellationTime.inSeconds > 0;

  bool get canForceCancel =>
      !isCancelled &&
      !isDelivered &&
      ['confirmed', 'preparing', 'ready_for_pickup'].contains(status);

  bool get canRequestRefund => isDelivered && refundStatus == null;
  bool get isPaymentPaid =>
      paymentStatus == 'success' || paymentStatus == 'paid';
  bool get isCodPayment => paymentMethod.toLowerCase() == 'cod';
  bool get canPayOnlineNow =>
      !isPaymentPaid && !isDelivered && !isCancelled && isCodPayment;
  bool get hasActivePreparationTimer =>
      (isConfirmed || isPreparing) && readyByAt != null;
  Duration get readyTimeRemaining {
    final target = readyByAt;
    if (target == null || !(isConfirmed || isPreparing)) {
      return Duration.zero;
    }
    final remaining = target.difference(DateTime.now());
    return remaining.isNegative ? Duration.zero : remaining;
  }

  String get preparationStatusLabel {
    if (isReadyForPickup) return 'Ready now';
    if (isPreparationDelayed) {
      final minutes =
          preparationDelayMinutes <= 0 ? 1 : preparationDelayMinutes;
      return 'Restaurant needs more time - delayed $minutes min';
    }
    if (!hasActivePreparationTimer) return statusText;
    final remaining = readyTimeRemaining;
    if (remaining.inSeconds <= 0) return 'Expected any moment';
    final minutes = remaining.inMinutes;
    if (minutes <= 0) return 'Ready in under 1 min';
    return 'Ready in about $minutes min';
  }

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
          return 'Store Confirmed';
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
        return const Color(0xFFFF6E00);
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

class PaymentAttempt {
  final int id;
  final String gateway;
  final String source;
  final String status;
  final double amount;
  final String currency;
  final String? paymentLink;
  final String? qrReference;
  final DateTime? expiresAt;

  const PaymentAttempt({
    required this.id,
    required this.gateway,
    required this.source,
    required this.status,
    required this.amount,
    required this.currency,
    this.paymentLink,
    this.qrReference,
    this.expiresAt,
  });

  factory PaymentAttempt.fromJson(Map<String, dynamic> json) {
    return PaymentAttempt(
      id: parseIntValue(json['id']),
      gateway: json['gateway']?.toString() ?? '',
      source: json['source']?.toString() ?? '',
      status: json['status']?.toString() ?? 'pending',
      amount: parseDoubleValue(json['amount']),
      currency: json['currency']?.toString() ?? 'INR',
      paymentLink: json['payment_link']?.toString(),
      qrReference: json['qr_reference']?.toString(),
      expiresAt: json['expires_at'] != null
          ? DateTime.tryParse(json['expires_at'].toString())
          : null,
    );
  }

  static PaymentAttempt? fromJsonOrNull(dynamic value) {
    if (value is Map<String, dynamic>) return PaymentAttempt.fromJson(value);
    if (value is Map)
      return PaymentAttempt.fromJson(Map<String, dynamic>.from(value));
    return null;
  }
}

class OrderItem {
  final int? menuItemId;
  final String name;
  final int quantity;
  final double unitPrice;
  final double totalPrice;
  final double price; // Non-nullable, defaults to unitPrice
  final String imageUrl;
  final int? rating;

  OrderItem({
    this.menuItemId,
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    double? price,
    this.imageUrl = '',
    this.rating,
  }) : price = price ?? unitPrice;

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    final unitPrice = parseDoubleValue(json['price'] ?? json['unit_price']);
    final menuItem = json['menu_item'] is Map
        ? Map<String, dynamic>.from(json['menu_item'] as Map)
        : const <String, dynamic>{};
    final imageUrl = _firstStringValue(json, const [
      'image_url',
      'image',
      'thumbnail',
      'thumbnail_url',
      'item_image',
      'item_image_url',
      'menu_item_image',
      'menu_item_image_url',
      'menu_image_url',
      'product_image_url',
      'dish_image_url',
    ]);
    final nestedImageUrl = _firstStringValue(menuItem, const [
      'image_url',
      'image',
      'thumbnail',
      'thumbnail_url',
      'item_image',
      'item_image_url',
      'menu_item_image',
      'menu_item_image_url',
      'menu_image_url',
      'product_image_url',
      'dish_image_url',
    ]);
    return OrderItem(
      menuItemId: parseNullableInt(json['menu_item_id'] ?? json['id']),
      name: json['name'] ?? json['item_name'] ?? '',
      quantity: parseIntValue(json['quantity'] ?? 1),
      unitPrice: unitPrice,
      totalPrice: parseDoubleValue(json['total'] ?? json['total_price']),
      price: unitPrice,
      imageUrl: imageUrl.isNotEmpty ? imageUrl : nestedImageUrl,
      rating: parseNullableInt(json['rating']),
    );
  }

  static String _firstStringValue(
      Map<String, dynamic> json, List<String> keys) {
    for (final key in keys) {
      final value = json[key];
      if (value == null) continue;
      final text = value.toString().trim();
      if (text.isNotEmpty && text != 'null') return text;
    }
    return '';
  }

  Map<String, dynamic> toJson() {
    return {
      'menu_item_id': menuItemId,
      'name': name,
      'quantity': quantity,
      'unit_price': unitPrice,
      'total_price': totalPrice,
      'image_url': imageUrl,
      'rating': rating,
    };
  }
}
