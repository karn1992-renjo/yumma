// lib/config/app_config.dart
import 'package:flutter/material.dart';

class AppConfig {
  static const String appRole = String.fromEnvironment(
    'APP_ROLE',
    defaultValue: 'driver',
  );
  static const String appPackageName = String.fromEnvironment(
    'APP_PACKAGE_NAME',
    defaultValue: 'com.adgraph.yamma_delivery',
  );

  static const String appName = 'Yumma! Go';
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://yumma.in/api',
  );
  static const String googleMapsApiKey = String.fromEnvironment(
    'GOOGLE_MAPS_API_KEY',
    defaultValue: '',
  );
  static const Color primaryColor = Color(0xFF2563EB);
  static const Color secondaryColor = Color(0xFF282C3F);
  static const Color backgroundColor = Color(0xFFF7F7F7);

  // Firebase Config (if using)
  static const String firebaseProjectId = String.fromEnvironment(
    'FIREBASE_PROJECT_ID',
    defaultValue: 'yumma-458b0',
  );

  static const String supportPhone = String.fromEnvironment(
    'SUPPORT_PHONE',
    defaultValue: '+917030666066',
  );
  static const String supportEmail = String.fromEnvironment(
    'SUPPORT_EMAIL',
    defaultValue: 'info@yumma.ine',
  );

  static bool get isCustomerApp => appRole == 'customer';
  static bool get isRestaurantApp => appRole == 'restaurant';
  static bool get isDriverApp => appRole == 'driver';
  static bool get isRoleLocked => appRole != 'all';
}
