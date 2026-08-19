// lib/models/order.dart
import 'dart:convert';
import 'dart:math';

import 'package:flutter/material.dart';

import '../utils/json_utils.dart';
import 'branch_info.dart';
import 'restaurant.dart';
import 'user.dart';

class Order {
  final int id;
  final String orderNumber;
  final int restaurantId;
  final String? restaurantName;
  final int? customerId;
  final int? driverId;
  final int? branchId;
  final BranchInfo? branch;
  final String orderType;
  final String customerName;
  final String customerPhone;
  final String deliveryAddress;
  final double? deliveryLat;
  final double? deliveryLng;
  final List<OrderItem> items;
  final double subtotal;
  final double deliveryFee;
  final double tax;
  final double discount;
  final double total;
  final double restaurantEarning;
  final double platformCommission;
  final double gstOnCommission;
  final double paymentGatewayFee;
  final String restaurantCommissionType;
  final double restaurantCommissionValue;
  final String status;
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
  final DateTime createdAt;
  final DateTime? confirmedAt;
  final int? preparationTimeMinutes;
  final DateTime? readyByAt;
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
  final Restaurant? restaurant;
  final User? driver;
  final String? deliveryOtp;

  Order({
    required this.id,
    required this.orderNumber,
    required this.restaurantId,
    this.restaurantName,
    this.customerId,
    this.driverId,
    this.branchId,
    this.branch,
    this.orderType = 'delivery',
    required this.customerName,
    required this.customerPhone,
    required this.deliveryAddress,
    this.deliveryLat,
    this.deliveryLng,
    required this.items,
    required this.subtotal,
    required this.deliveryFee,
    required this.tax,
    required this.discount,
    required this.total,
    this.restaurantEarning = 0,
    this.platformCommission = 0,
    this.gstOnCommission = 0,
    this.paymentGatewayFee = 0,
    this.restaurantCommissionType = 'percentage',
    this.restaurantCommissionValue = 0,
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
    required this.createdAt,
    this.confirmedAt,
    this.preparationTimeMinutes,
    this.readyByAt,
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
    this.restaurant,
    this.driver,
    this.deliveryOtp,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    final source = _normalizeSource(json);
    final customer = _mapOrNull(source['customer']);
    final driver = _mapOrNull(source['driver']);
    final restaurant = _mapOrNull(source['restaurant']);
    final branch = _mapOrNull(source['branch']);
    final address = _firstNonEmptyString([
      source['delivery_address'],
      source['address'],
      customer?['address'],
      _mapOrNull(source['delivery_location'])?['address'],
    ]);

    return Order(
      id: parseIntValue(source['id']),
      orderNumber: _firstNonEmptyString([
            source['order_number'],
            source['order_no'],
            source['number'],
          ]) ??
          'ORD${source['id']}',
      restaurantId: parseIntValue(source['restaurant_id'] ?? restaurant?['id']),
      restaurantName: _firstNonEmptyString([
        source['restaurant_name'],
        restaurant?['name'],
      ]),
      customerId: parseNullableInt(source['customer_id'] ?? customer?['id']),
      driverId: parseNullableInt(source['driver_id'] ?? driver?['id']),
      branchId: parseNullableInt(source['branch_id'] ?? branch?['id']),
      branch: branch != null ? BranchInfo.fromJson(branch) : null,
      orderType: source['order_type']?.toString() ?? 'delivery',
      customerName: _firstNonEmptyString([
            source['customer_name'],
            customer?['name'],
            customer?['full_name'],
          ]) ??
          'Guest',
      customerPhone: _firstNonEmptyString([
            source['customer_phone'],
            customer?['phone'],
            customer?['mobile'],
          ]) ??
          '',
      deliveryAddress: address ?? '',
      deliveryLat: parseNullableDouble(
        source['delivery_lat'] ??
            source['latitude'] ??
            _mapOrNull(source['delivery_location'])?['lat'],
      ),
      deliveryLng: parseNullableDouble(
        source['delivery_lng'] ??
            source['longitude'] ??
            _mapOrNull(source['delivery_location'])?['lng'],
      ),
      items: _parseItems(source),
      subtotal: parseDoubleValue(
        source['subtotal'] ?? source['sub_total'] ?? source['items_total'],
      ),
      deliveryFee: parseDoubleValue(
        source['delivery_fee'] ?? source['shipping_fee'],
      ),
      tax: parseDoubleValue(source['tax'] ?? source['tax_amount']),
      discount: parseDoubleValue(
        source['discount'] ?? source['discount_amount'],
      ),
      total: parseDoubleValue(
        source['total'] ?? source['grand_total'] ?? source['amount'],
      ),
      restaurantEarning: parseDoubleValue(
        source['restaurant_earning'] ??
            source['restaurant_payout'] ??
            source['payout_amount'] ??
            source['net_amount'],
      ),
      platformCommission: parseDoubleValue(
        source['platform_commission'] ?? source['admin_commission'],
      ),
      gstOnCommission: parseDoubleValue(source['gst_on_commission']),
      paymentGatewayFee: parseDoubleValue(source['payment_gateway_fee']),
      restaurantCommissionType:
          source['restaurant_commission_type']?.toString() ?? 'percentage',
      restaurantCommissionValue: parseDoubleValue(
        source['restaurant_commission_value'] ??
            source['commission_value'] ??
            source['commission_percentage'],
      ),
      status: _normalizeStatus(
        _firstNonEmptyString([source['status'], source['order_status']]) ??
            'pending',
      ),
      paymentMethod: _firstNonEmptyString([
            source['payment_method'],
            source['payment_type'],
          ]) ??
          'cod',
      paymentStatus: _normalizePaymentStatus(
        _firstNonEmptyString([
              source['payment_status'],
              source['payment_state'],
            ]) ??
            'pending',
      ),
      deliveryPaymentMode: source['delivery_payment_mode']?.toString(),
      cashCollectedAmount: parseNullableDouble(source['cash_collected_amount']),
      cashCollectedAt: _parseDate(source['cash_collected_at']),
      onlinePaymentVerifiedAt: _parseDate(source['online_payment_verified_at']),
      scheduledTime: _parseDate(source['scheduled_time']),
      specialInstructions: source['special_instructions']?.toString(),
      cancellationReason: source['cancellation_reason']?.toString(),
      refundStatus: source['refund_status']?.toString(),
      refundAmount: parseNullableDouble(source['refund_amount']),
      createdAt: _parseDate(source['created_at']) ?? DateTime.now(),
      confirmedAt: _parseDate(source['confirmed_at']),
      preparationTimeMinutes:
          parseNullableInt(source['preparation_time_minutes']),
      readyByAt: _parseDate(source['ready_by_at']),
      readyCountdownSeconds:
          parseNullableInt(source['ready_countdown_seconds']),
      isPreparationDelayed: source['is_preparation_delayed'] == true ||
          source['is_preparation_delayed']?.toString() == '1',
      preparationDelayMinutes:
          parseIntValue(source['preparation_delay_minutes'] ?? 0),
      remainingPreparationMinutes:
          parseNullableInt(source['remaining_preparation_minutes']),
      deliveredAt: _parseDate(source['delivered_at']),
      cancelledAt: _parseDate(source['cancelled_at']),
      restaurantRating: parseNullableInt(source['restaurant_rating']),
      driverRating: parseNullableInt(source['driver_rating']),
      restaurantFeedback: source['restaurant_feedback']?.toString(),
      driverFeedback: source['driver_feedback']?.toString(),
      feedbackSubmittedAt: _parseDate(source['feedback_submitted_at']),
      driverAssignmentAttempts: parseIntValue(
        source['driver_assignment_attempts'] ?? 0,
      ),
      driverAssignedAt: _parseDate(source['driver_assigned_at']),
      driverAcceptedAt: _parseDate(source['driver_accepted_at']),
      restaurant: restaurant != null ? Restaurant.fromJson(restaurant) : null,
      driver: driver != null ? User.fromJson(driver) : null,
      deliveryOtp: _firstNonEmptyString([
        source['delivery_otp'],
        source['otp'],
        source['delivery_code'],
        source['verification_code'],
      ]),
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

  bool get canRestaurantAccept => isPending;
  bool get canRestaurantReject => isPending;
  bool get canRestaurantStartPreparing => isConfirmed;
  bool get canRestaurantMarkReady => isPreparing;
  bool get canRestaurantVerifyTakeawayPickup => isTakeaway && isReadyForPickup;
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

  String get readyTimeLabel {
    if (isReadyForPickup) return 'Ready now';
    if (isPreparationDelayed) {
      return 'Delayed ${preparationDelayMinutes <= 0 ? 1 : preparationDelayMinutes} min';
    }
    final remaining = readyTimeRemaining;
    if (remaining.inSeconds <= 0) return 'Due now';
    final minutes = remaining.inMinutes;
    final seconds = remaining.inSeconds.remainder(60);
    if (minutes >= 60) {
      final hours = minutes ~/ 60;
      final mins = minutes.remainder(60);
      return '${hours}h ${mins}m';
    }
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  bool get isDriverAssignmentPending =>
      driverId != null &&
      driverAcceptedAt == null &&
      (isConfirmed || isPreparing || isReadyForPickup);

  bool get hasFeedback =>
      feedbackSubmittedAt != null || restaurantRating != null;
  bool get needsFeedback => isDelivered && !hasFeedback;

  DateTime get customerCancellationClosesAt =>
      createdAt.add(const Duration(minutes: 2));

  bool get canCancel =>
      isPending && DateTime.now().isBefore(customerCancellationClosesAt);
  bool get canRequestRefund => isDelivered && refundStatus == null;

  String get statusText {
    if (isTakeaway) {
      switch (status) {
        case 'pending':
          return 'Order Placed';
        case 'confirmed':
          return 'Order Confirmed';
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

  static Map<String, dynamic> _normalizeSource(Map<String, dynamic> json) {
    final nested = _mapOrNull(
      json['order'] ?? json['order_data'] ?? json['payload'],
    );
    return nested != null ? {...json, ...nested} : json;
  }

  static List<OrderItem> _parseItems(Map<String, dynamic> source) {
    final rawItems =
        source['items'] ?? source['order_items'] ?? source['cart_items'];
    if (rawItems is String) {
      try {
        final decoded = jsonDecode(rawItems);
        if (decoded is List) {
          return decoded
              .whereType<Map>()
              .map(
                (item) => OrderItem.fromJson(Map<String, dynamic>.from(item)),
              )
              .toList();
        }
      } catch (_) {}
      return const [];
    }

    if (rawItems is List) {
      return rawItems
          .whereType<Map>()
          .map((item) => OrderItem.fromJson(Map<String, dynamic>.from(item)))
          .toList();
    }

    return const [];
  }

  static Map<String, dynamic>? _mapOrNull(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return Map<String, dynamic>.from(value);
    return null;
  }

  static String? _firstNonEmptyString(List<dynamic> values) {
    for (final value in values) {
      final text = value?.toString().trim();
      if (text != null && text.isNotEmpty && text.toLowerCase() != 'null') {
        return text;
      }
    }
    return null;
  }

  static String _normalizeStatus(String value) {
    return value.trim().toLowerCase().replaceAll(' ', '_');
  }

  static String _normalizePaymentStatus(String value) {
    final normalized = value.trim().toLowerCase().replaceAll(' ', '_');
    if (normalized == 'paid' || normalized == 'success') return 'paid';
    return normalized;
  }

  static DateTime? _parseDate(dynamic value) {
    final text = value?.toString();
    if (text == null || text.isEmpty) return null;
    return DateTime.tryParse(text);
  }
}

class OrderItem {
  final int? menuItemId;
  final String name;
  final String imageUrl;
  final List<String> images;
  final int quantity;
  final double unitPrice;
  final double totalPrice;
  final double price;
  final SelectedOrderOption? selectedVariant;
  final List<SelectedOrderOption> selectedAddOns;
  final bool isPromotionReward;
  final String? promotionTitle;
  final int promotionFreeQuantity;
  final int promotionPaidQuantity;

  OrderItem({
    this.menuItemId,
    required this.name,
    this.imageUrl = '',
    this.images = const [],
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    this.selectedVariant,
    this.selectedAddOns = const [],
    this.isPromotionReward = false,
    this.promotionTitle,
    this.promotionFreeQuantity = 0,
    this.promotionPaidQuantity = 0,
    double? price,
  }) : price = price ?? unitPrice;

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    final unitPrice = parseDoubleValue(
      json['price'] ?? json['unit_price'] ?? json['amount'],
    );
    final totalPrice = parseDoubleValue(
      json['total'] ?? json['total_price'] ?? json['subtotal'] ?? unitPrice,
    );

    final images = _parseImages(json);
    final selectedVariant = SelectedOrderOption.fromJsonOrNull(
      json['selected_variant'] ?? json['variant'],
    );
    final customFields = selectedVariant?.customFields ?? const {};
    final specialInstructions = json['special_instructions']?.toString().trim();
    final tags = json['tags'];
    final hasPromotionTag = tags is List &&
        tags.any((tag) => tag?.toString().trim() == 'promotion_reward');
    final lineType = Order._firstNonEmptyString([
      json['line_type'],
      customFields['line_type'],
    ]);
    final isPromotionReward = lineType == 'promotion_reward' ||
        hasPromotionTag ||
        (specialInstructions?.toLowerCase().contains('promotion reward') ??
            false);
    final promotionTitle = Order._firstNonEmptyString([
      json['promotion_title'],
      customFields['promotion_title'],
      isPromotionReward ? specialInstructions : null,
    ]);
    final promotionFreeQuantity = parseIntValue(
      json['promotion_free_quantity'] ??
          customFields['promotion_free_quantity'] ??
          0,
    );
    final promotionPaidQuantity = parseIntValue(
      json['promotion_paid_quantity'] ??
          customFields['promotion_paid_quantity'] ??
          max(0, parseIntValue(json['quantity'] ?? 1) - promotionFreeQuantity),
    );

    return OrderItem(
      menuItemId: parseNullableInt(json['menu_item_id'] ?? json['id']),
      name: Order._firstNonEmptyString([
            json['name'],
            json['item_name'],
            json['menu_name'],
            _mapName(json['menu_item']),
          ]) ??
          'Item',
      imageUrl: images.isNotEmpty ? images.first : '',
      images: images,
      quantity: parseIntValue(json['quantity'] ?? 1),
      unitPrice: unitPrice,
      totalPrice: totalPrice,
      selectedVariant: selectedVariant,
      selectedAddOns: SelectedOrderOption.listFromJson(
        json['selected_add_ons'] ?? json['add_ons'] ?? json['addons'],
      ),
      isPromotionReward: isPromotionReward,
      promotionTitle: promotionTitle,
      promotionFreeQuantity: promotionFreeQuantity,
      promotionPaidQuantity: promotionPaidQuantity,
      price: unitPrice,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'menu_item_id': menuItemId,
      'name': name,
      'image_url': imageUrl,
      'images': images,
      'quantity': quantity,
      'unit_price': unitPrice,
      'total_price': totalPrice,
      'selected_variant': selectedVariant?.toJson(),
      'selected_add_ons':
          selectedAddOns.map((option) => option.toJson()).toList(),
      'line_type': isPromotionReward ? 'promotion_reward' : null,
      'promotion_title': promotionTitle,
      'promotion_free_quantity': promotionFreeQuantity,
      'promotion_paid_quantity': promotionPaidQuantity,
    };
  }

  bool get hasPromotionFreeUnits => promotionFreeQuantity > 0;

  bool get hasCustomizations =>
      !isPromotionReward &&
      (selectedAddOns.isNotEmpty ||
          (selectedVariant != null &&
              selectedVariant!.name.trim() != 'Promotion info'));

  String get customizationSummary {
    final parts = <String>[];
    if (selectedVariant != null &&
        selectedVariant!.name.trim() != 'Promotion info') {
      parts.add(selectedVariant!.name);
    }
    parts.addAll(selectedAddOns.map((option) => option.name));
    return parts.join(' • ');
  }

  static String? _mapName(dynamic value) {
    if (value is Map) {
      return value['name']?.toString();
    }
    return null;
  }

  static List<String> _parseImages(Map<String, dynamic> json) {
    final images = <String>[];
    void addImage(dynamic value) {
      final text = value?.toString().trim();
      if (text != null && text.isNotEmpty && text.toLowerCase() != 'null') {
        images.add(text);
      }
    }

    addImage(json['image_url']);
    addImage(json['image']);
    addImage(json['thumbnail_url']);

    final rawImages = json['images'];
    if (rawImages is List) {
      for (final image in rawImages) {
        addImage(image);
      }
    } else if (rawImages is String) {
      try {
        final decoded = jsonDecode(rawImages);
        if (decoded is List) {
          for (final image in decoded) {
            addImage(image);
          }
        } else {
          addImage(rawImages);
        }
      } catch (_) {
        addImage(rawImages);
      }
    }

    final menuItem = json['menu_item'];
    if (menuItem is Map) {
      addImage(menuItem['image_url']);
      addImage(menuItem['image']);
      final nestedImages = menuItem['images'];
      if (nestedImages is List) {
        for (final image in nestedImages) {
          addImage(image);
        }
      }
    }

    return images.toSet().toList();
  }
}

class SelectedOrderOption {
  final String name;
  final double price;
  final Map<String, String> customFields;

  const SelectedOrderOption({
    required this.name,
    this.price = 0,
    this.customFields = const {},
  });

  factory SelectedOrderOption.fromJson(Map<String, dynamic> json) {
    return SelectedOrderOption(
      name: (json['name'] ?? json['label'] ?? json['title'] ?? '').toString(),
      price: parseDoubleValue(
        json['price'] ?? json['additional_price'] ?? json['amount'],
      ),
      customFields: _parseCustomFields(json['custom_fields']),
    );
  }

  static SelectedOrderOption? fromJsonOrNull(dynamic value) {
    if (value is Map<String, dynamic>) {
      final option = SelectedOrderOption.fromJson(value);
      return option.name.trim().isEmpty ? null : option;
    }

    if (value is Map) {
      final option = SelectedOrderOption.fromJson(
        Map<String, dynamic>.from(value),
      );
      return option.name.trim().isEmpty ? null : option;
    }

    return null;
  }

  static List<SelectedOrderOption> listFromJson(dynamic value) {
    dynamic source = value;
    if (source is String) {
      if (source.trim().isEmpty) return const [];
      try {
        source = jsonDecode(source);
      } catch (_) {
        return const [];
      }
    }

    if (source is! List) return const [];

    return source.map(fromJsonOrNull).whereType<SelectedOrderOption>().toList();
  }

  Map<String, dynamic> toJson() => {
        'name': name,
        'price': price,
        'custom_fields': customFields,
      };

  static Map<String, String> _parseCustomFields(dynamic value) {
    if (value is! Map) return const {};

    return value.map((key, fieldValue) {
      return MapEntry(key.toString(), fieldValue?.toString() ?? '');
    });
  }
}
