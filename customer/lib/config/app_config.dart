// lib/config/app_config.dart
import 'package:flutter/material.dart';

class AppConfig {
  static const String appRole = String.fromEnvironment(
    'APP_ROLE',
    defaultValue: 'customer',
  );
  static const String appPackageName = String.fromEnvironment(
    'APP_PACKAGE_NAME',
    defaultValue: 'com.adgraph.yumma',
  );

  static const String appName = appRole == 'driver'
      ? 'Yumma! Go'
      : appRole == 'restaurant'
          ? 'Yumma! Resto'
          : 'Yumma!';
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://yumma.in/api',
  );
  static const String googleMapsApiKey = String.fromEnvironment(
    'GOOGLE_MAPS_API_KEY',
    defaultValue: '',
  );
  static const Color primaryColor = Color(0xFF0A9443);
  static const Color secondaryColor = Color(0xFF282C3F);
  static const Color backgroundColor = Color(0xFFF8F8F8);

  static const String walletMoneyLabel = 'Yumma! Money';

  static String get apiOrigin {
    final uri = Uri.parse(apiBaseUrl);
    if (!uri.hasScheme || uri.host.isEmpty) return apiBaseUrl;
    return uri.hasPort ? '${uri.scheme}://${uri.host}:${uri.port}' : uri.origin;
  }

  static String resolveMediaUrl(dynamic rawValue) {
    final value = rawValue?.toString().trim() ?? '';
    if (value.isEmpty || value == 'null') return '';
    if (value.startsWith('http://') ||
        value.startsWith('https://') ||
        value.startsWith('data:') ||
        value.startsWith('blob:')) {
      return value;
    }
    if (value.startsWith('//')) {
      final scheme = Uri.tryParse(apiBaseUrl)?.scheme;
      return '${scheme?.isNotEmpty == true ? scheme : 'https'}:$value';
    }

    final normalized = value.replaceFirst(RegExp(r'^/+'), '');
    if (normalized.startsWith('storage/')) return '$apiOrigin/$normalized';
    if (normalized.startsWith('public/')) {
      return '$apiOrigin/storage/${normalized.substring('public/'.length)}';
    }
    return '$apiOrigin/storage/$normalized';
  }

// Firebase Config (if using)
  static const String firebaseProjectId = String.fromEnvironment(
    'FIREBASE_PROJECT_ID',
    defaultValue: 'yumma-458b0',
  );
  static const String firebaseWebVapidKey = String.fromEnvironment(
    'FIREBASE_WEB_VAPID_KEY',
    defaultValue: '',
  );

  static const String appsFlyerDevKey = String.fromEnvironment(
    'APPSFLYER_DEV_KEY',
    defaultValue: '4wDuAPLmyp6yg5GGCaqq9i',
  );
  static const String appsFlyerAppId = String.fromEnvironment(
    'APPSFLYER_APP_ID',
    defaultValue: 'com.adgraph.yumma',
  );
  static const String appsFlyerOneLinkId = String.fromEnvironment(
    'APPSFLYER_ONELINK_ID',
    defaultValue: 'HZOC',
  );
  static const String appsFlyerOneLinkDomain = String.fromEnvironment(
    'APPSFLYER_ONELINK_DOMAIN',
    defaultValue: 'yumma.onelink.me',
  );
  static const String appsFlyerOneLinkPath = String.fromEnvironment(
    'APPSFLYER_ONELINK_PATH',
    defaultValue: 'HZOC/2f2m39o2',
  );
  static const bool appsFlyerDebug = bool.fromEnvironment(
    'APPSFLYER_DEBUG',
    defaultValue: false,
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
