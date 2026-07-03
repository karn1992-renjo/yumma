// lib/models/user.dart
import '../utils/json_utils.dart';
import 'branch_info.dart';

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
  final int? branchId;
  final BranchInfo? branch;
  final DateTime createdAt;
  final Map<String, dynamic> settings;
  final List<String> permissions;

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
    this.branchId,
    this.branch,
    required this.createdAt,
    this.settings = const {},
    this.permissions = const [],
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
      branchId: parseNullableInt(
        json['branch_id'] ?? _mapOrNull(json['branch'])?['id'],
      ),
      branch: _mapOrNull(json['branch']) != null
          ? BranchInfo.fromJson(_mapOrNull(json['branch'])!)
          : null,
      createdAt: DateTime.parse(
        json['created_at'] ?? DateTime.now().toIso8601String(),
      ),
      settings: _mergeSettings(json),
      permissions: json['permissions'] is List
          ? (json['permissions'] as List)
                .map((item) => item.toString())
                .toList()
          : const [],
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
      'branch_id': branchId,
      'branch': branch?.toJson(),
      'created_at': createdAt.toIso8601String(),
      'settings': settings,
      'permissions': permissions,
    };
  }

  bool hasPermission(String permission) => permissions.contains(permission);

  bool get isCustomer {
    if (role == null) return true;
    final normalized = role!.toLowerCase();
    return normalized.contains('customer') || normalized.contains('user');
  }

  bool get isRestaurantOwner {
    if (role == null) return false;
    final normalized = _normalizedRole;
    return normalized == 'restaurant_owner' || normalized.contains('owner');
  }

  bool get isRestaurantStaff {
    if (role == null) return false;
    final normalized = _normalizedRole;
    return normalized == 'restaurant_staff' ||
        normalized == 'staff' ||
        normalized.contains('restaurant_staff') ||
        (normalized.contains('staff') && permissions.isNotEmpty);
  }

  bool get isRestaurantMember => isRestaurantOwner || isRestaurantStaff;

  bool get canManageOrders =>
      isRestaurantOwner ||
      hasPermission('manage_orders') ||
      hasPermission('update_order_status');

  bool get canViewOrders => canManageOrders || hasPermission('view_orders');

  bool get canManageMenu => isRestaurantOwner || hasPermission('manage_menu');

  bool get canViewMenu => canManageMenu || hasPermission('view_menu_items');

  bool get canViewReports => isRestaurantOwner || hasPermission('view_reports');

  bool get canManageStaff => isRestaurantOwner;

  bool get canManageSettings => isRestaurantOwner;

  String get restaurantAccessLabel {
    if (isRestaurantOwner) return 'Restaurant Owner';
    if (canManageOrders && canManageMenu && canViewReports) {
      return 'Operations Staff';
    }
    if (canManageOrders || canViewOrders) return 'Order Staff';
    if (canManageMenu || canViewMenu) return 'Menu Staff';
    if (canViewReports) return 'Reporting Staff';
    return 'Restaurant Staff';
  }

  String get branchLabel => branch?.label ?? 'Unassigned Branch';

  bool get hasBranch => branchId != null || branch != null;

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

  String get _normalizedRole =>
      (role ?? '').toLowerCase().replaceAll('-', '_').replaceAll(' ', '_');

  String get currencyCode =>
      settings['currency_code']?.toString().toUpperCase() ?? 'INR';

  String get currencySymbol =>
      _normalizeCurrencySymbol(settings['currency_symbol']?.toString());

  int get currencyDecimals {
    final value = int.tryParse(settings['currency_decimals']?.toString() ?? '');
    if (value == null) return 2;
    return value.clamp(2, 5).toInt();
  }

  String get paymentGatewayProvider =>
      settings['payment_gateway_provider']?.toString().toLowerCase() ??
      'razorpay';

  String get payoutGatewayProvider =>
      settings['payout_gateway_provider']?.toString().toLowerCase() ??
      paymentGatewayProvider;

  String get countryCode =>
      settings['country_code']?.toString().toUpperCase() ?? 'IN';

  static const String defaultMobileCountryCodeFallback = '+91';

  static String normalizeMobileCountryCode(String code) {
    final normalized = code.trim();
    if (normalized.isEmpty) return defaultMobileCountryCodeFallback;
    return normalized.startsWith('+') ? normalized : '+$normalized';
  }

  String get defaultMobileCountryCode {
    final code =
        settings['default_mobile_country_code']?.toString().trim() ?? '';
    return normalizeMobileCountryCode(code);
  }

  bool get isPaymentGatewayEnabled {
    final value = settings['payment_gateway_enabled'];
    if (value is bool) return value;
    if (value is num) return value == 1;
    return value?.toString() == '1' ||
        value?.toString().toLowerCase() == 'true';
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
      'payout_gateway_provider',
      'country_code',
      'default_mobile_country_code',
      'payment_gateway_enabled',
    ]) {
      final value = json[key];
      if (value != null && value.toString().isNotEmpty) {
        merged[key] = value;
      }
    }

    return merged;
  }

  static Map<String, dynamic>? _mapOrNull(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return Map<String, dynamic>.from(value);
    return null;
  }

  static String _normalizeCurrencySymbol(String? value) {
    final symbol = value?.trim();
    if (symbol == null || symbol.isEmpty || symbol == 'â‚¹') {
      return 'Rs';
    }
    return symbol;
  }
}
