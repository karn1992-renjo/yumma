// lib/models/user.dart
import 'dart:convert';

import '../utils/currency_utils.dart';
import '../utils/json_utils.dart';

class User {
  final int id;
  final String name;
  final String email;
  final String phone;
  final String? profileImage;
  final String? role;
  final bool isActive;
  final String? vehicleType;
  final String? vehicleNumber;
  final String? licenseNumber;
  final DateTime createdAt;
  final Map<String, dynamic> settings;

  User({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    this.profileImage,
    this.role,
    this.isActive = true,
    this.vehicleType,
    this.vehicleNumber,
    this.licenseNumber,
    required this.createdAt,
    this.settings = const {},
  });

  factory User.fromJson(Map<String, dynamic> json) {
    final dynamic rolesJson = json['roles'];
    String? parsedRole;

    if (json['role'] != null) {
      parsedRole = json['role'].toString().toLowerCase();
    } else if (rolesJson is List && rolesJson.isNotEmpty) {
      final firstRole = rolesJson.first;
      if (firstRole is Map<String, dynamic>) {
        parsedRole = firstRole['name']?.toString().toLowerCase();
      } else if (firstRole is String) {
        parsedRole = firstRole.toLowerCase();
      }
    }

    return User(
      id: json['id'],
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'] ?? '',
      profileImage: json['profile_image'] ?? json['profile_photo_url'],
      role: parsedRole ?? 'customer',
      isActive: parseBoolValue(json['is_active'], true),
      vehicleType: json['vehicle_type'],
      vehicleNumber: json['vehicle_number'],
      licenseNumber: json['license_number'],
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
      settings: _mergeSettings(json),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'profile_image': profileImage,
      'role': role,
      'is_active': isActive,
      'vehicle_type': vehicleType,
      'vehicle_number': vehicleNumber,
      'license_number': licenseNumber,
      'created_at': createdAt.toIso8601String(),
      'settings': settings,
    };
  }

  bool get isCustomer {
    if (role == null) return true;
    final normalized = role!.toLowerCase();
    return normalized.contains('customer') || normalized.contains('user');
  }

  bool get isRestaurantOwner {
    if (role == null) return false;
    final normalized = role!.toLowerCase();
    return normalized.contains('restaurant') || normalized.contains('owner');
  }

  bool get isDriver {
    if (role == null) return false;
    final normalized = role!.toLowerCase();
    return normalized.contains('delivery') || normalized.contains('driver');
  }

  bool get isAdmin {
    if (role == null) return false;
    final normalized = role!.toLowerCase();
    return normalized.contains('admin') || normalized.contains('super');
  }

  String get currencyCode =>
      settings['currency_code']?.toString().toUpperCase() ?? 'INR';

  String get currencySymbol =>
      normalizeCurrencySymbol(settings['currency_symbol']?.toString());

  int get currencyDecimals {
    final value = int.tryParse(settings['currency_decimals']?.toString() ?? '');
    if (value == null) return 2;
    return value.clamp(2, 5).toInt();
  }

  String get paymentGatewayProvider =>
      settings['payment_gateway_provider']?.toString().toLowerCase() ??
      'razorpay';

  String get paymentGatewayLogo =>
      settings['payment_gateway_logo']?.toString().trim() ?? '';

  List<PaymentGatewayOption> get paymentGateways {
    final rawGateways = settings['payment_gateways'];
    if (rawGateways is List) {
      final gateways = rawGateways
          .whereType<Map>()
          .map((gateway) => PaymentGatewayOption.fromJson(
                Map<String, dynamic>.from(gateway),
              ))
          .where((gateway) => gateway.key.isNotEmpty)
          .toList();
      if (gateways.isNotEmpty) return gateways;
    }

    return const [
      PaymentGatewayOption(key: 'razorpay', label: 'Razorpay'),
      PaymentGatewayOption(key: 'stripe', label: 'Stripe'),
      PaymentGatewayOption(key: 'cashfree', label: 'Cashfree'),
    ];
  }

  List<String> get enabledPaymentGatewayKeys {
    final raw = settings['enabled_payment_gateways'];
    if (raw is List) {
      return raw.map((item) => item.toString().toLowerCase()).toList();
    }
    if (raw is String && raw.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(raw);
        if (decoded is List) {
          return decoded.map((item) => item.toString().toLowerCase()).toList();
        }
      } catch (_) {
        // Fall through to the enabled gateway metadata below.
      }
    }

    return paymentGateways
        .where((gateway) => gateway.enabled)
        .map((gateway) => gateway.key)
        .toList();
  }

  String get customerDeeplinkBaseUrl =>
      settings['customer_deeplink_base_url']?.toString().trim() ?? '';

  bool get isPaymentGatewayEnabled {
    final value = settings['payment_gateway_enabled'];
    if (value is bool) return value;
    if (value is num) return value == 1;
    return value?.toString() == '1' ||
        value?.toString().toLowerCase() == 'true';
  }

  bool get isCodEnabled {
    final value = settings['cod_enabled'];
    if (value == null) return true;
    if (value is bool) return value;
    if (value is num) return value == 1;
    return value.toString() == '1' ||
        value.toString().toLowerCase() == 'true';
  }

  static const String defaultMobileCountryCodeFallback = '+91';

  static String normalizeMobileCountryCode(String code) {
    final normalized = code.trim();
    if (normalized.isEmpty) return defaultMobileCountryCodeFallback;
    return normalized.startsWith('+') ? normalized : '+$normalized';
  }

  String get defaultMobileCountryCode {
    final code = settings['default_mobile_country_code']?.toString().trim() ?? '';
    return normalizeMobileCountryCode(code);
  }

  static Map<String, dynamic> _mergeSettings(Map<String, dynamic> json) {
    final merged = json['settings'] is Map
        ? Map<String, dynamic>.from(json['settings'] as Map)
        : <String, dynamic>{};

    for (final key in const [
      'currency_code',
      'currency_symbol',
      'currency_decimals',
      'payment_gateway_provider',
      'payment_gateway_logo',
      'enabled_payment_gateways',
      'payment_gateways',
      'customer_deeplink_base_url',
      'country_code',
      'default_mobile_country_code',
      'payment_gateway_enabled',
      'cod_enabled',
    ]) {
      final value = json[key];
      if (value != null && value.toString().isNotEmpty) {
        merged[key] = value;
      }
    }

    return merged;
  }
}

class PaymentGatewayOption {
  final String key;
  final String label;
  final String logo;
  final bool enabled;
  final bool selected;

  const PaymentGatewayOption({
    required this.key,
    required this.label,
    this.logo = '',
    this.enabled = true,
    this.selected = false,
  });

  factory PaymentGatewayOption.fromJson(Map<String, dynamic> json) {
    return PaymentGatewayOption(
      key: json['key']?.toString().toLowerCase() ?? '',
      label: json['label']?.toString() ?? '',
      logo: json['logo']?.toString() ?? '',
      enabled: parseBoolValue(json['enabled'], true),
      selected: parseBoolValue(json['selected'], false),
    );
  }
}
