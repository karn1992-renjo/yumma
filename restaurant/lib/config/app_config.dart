// lib/config/app_config.dart
import 'package:flutter/material.dart';

class AppConfig {
  static const String appRole = String.fromEnvironment(
    'APP_ROLE',
    defaultValue: 'restaurant',
  );
  static const String appPackageName = String.fromEnvironment(
    'APP_PACKAGE_NAME',
    defaultValue: 'com.adgraph.vendor',
  );

  static const String appName = appRole == 'driver'
      ? 'Yumma! Go'
      : appRole == 'restaurant'
          ? 'Yumma! Resto'
          : 'Yumma!';
  static const String brandName = 'Yumma';
  static const String companyName = 'Adgraph Media Private Limited';
  static const String privacyPolicyUrl = 'https://yumma.in/privacy-policy';
  static const String accountDeletionPolicyUrl =
      'https://yumma.in/delete-account-policy.html';
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://yumma.in/api',
  );
  static const String googleMapsApiKey = String.fromEnvironment(
    'GOOGLE_MAPS_API_KEY',
    defaultValue: '',
  );
  static const Color primaryColor = Color(0xFF0E9F6E);
  static const Color secondaryColor = Color(0xFF282C3F);
  static const Color backgroundColor = Color(0xFFF7F7F7);

  // Firebase Config (if using)
  static const String firebaseProjectId = String.fromEnvironment(
    'FIREBASE_PROJECT_ID',
    defaultValue: 'yumma-458b0',
  );

  static const String supportPhone = String.fromEnvironment(
    'SUPPORT_PHONE',
    defaultValue: '+917038666066',
  );
  static const String supportEmail = String.fromEnvironment(
    'SUPPORT_EMAIL',
    defaultValue: 'info@yumma.in',
  );

  static bool get isCustomerApp => appRole == 'customer';
  static bool get isRestaurantApp => appRole == 'restaurant';
  static bool get isDriverApp => appRole == 'driver';
  static bool get isRoleLocked => appRole != 'all';
}
