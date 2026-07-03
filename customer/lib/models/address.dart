// lib/models/address.dart
import '../utils/json_utils.dart';

class Address {
  final int id;
  final int userId;
  final String name;
  final String address;
  final String city;
  final String state;
  final String pincode;
  final String phone;
  final double? latitude;
  final double? longitude;
  final bool isDefault;
  final double? distanceKm;
  final bool isDeliverable;
  final String? deliveryStatusLabel;
  final DateTime createdAt;

  Address({
    required this.id,
    required this.userId,
    required this.name,
    required this.address,
    required this.city,
    required this.state,
    required this.pincode,
    required this.phone,
    this.latitude,
    this.longitude,
    this.isDefault = false,
    this.distanceKm,
    this.isDeliverable = true,
    this.deliveryStatusLabel,
    required this.createdAt,
  });

  factory Address.fromJson(Map<String, dynamic> json) {
    return Address(
      id: parseIntValue(json['id']),
      userId: parseIntValue(json['user_id']),
      name: json['name'] ?? 'Home',
      address: json['address'] ?? '',
      city: json['city'] ?? '',
      state: json['state'] ?? '',
      pincode: json['pincode'] ?? '',
      phone: json['phone'] ?? '',
      latitude: parseNullableDouble(json['latitude']),
      longitude: parseNullableDouble(json['longitude']),
      isDefault: parseBoolValue(json['is_default'], false),
      distanceKm: parseNullableDouble(json['distance_km']),
      isDeliverable: parseBoolValue(json['is_deliverable'], true),
      deliveryStatusLabel: json['delivery_status_label']?.toString(),
      createdAt: DateTime.parse(json['created_at'] ?? DateTime.now().toIso8601String()),
    );
  }

  String get fullAddress {
    return '$address, $city, $state - $pincode';
  }
}
