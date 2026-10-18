// lib/config/ad_config.dart
//
// AdMob (Google Mobile Ads) placement IDs, centralized so every screen that
// shows an ad reads from here instead of embedding literal ad unit strings.
// Real values are admin-configurable (Admin > Settings > Branding > Google
// Ads) and arrive via AppBrandingService -- changing them there takes
// effect on the app's next branding refresh, no rebuild needed.
//
// Ad format is Native Advanced (see NativeAdWidget + the native-side
// factories: android/.../ListTileNativeAdFactory.kt,
// ios/.../ListTileNativeAdFactory.swift), not Banner. A Native Advanced
// placement needs an ad unit created in the AdMob console specifically as
// "Native advanced" format -- a Banner-format ad unit ID will not serve
// here, even though the string looks the same shape.
//
// The App IDs are the one exception: Google's SDK reads them from
// AndroidManifest.xml / Info.plist at native init time, before any network
// call could complete, so they stay build-time constants regardless of what
// the admin sets (that admin field is for record-keeping only).
//
// Whenever the admin hasn't set a real ad unit yet, these fall back to
// Google's publicly documented TEST ids for Native Advanced
// (https://developers.google.com/admob/android/test-ads,
// https://developers.google.com/admob/ios/test-ads) -- they always fill
// with a sample "Test Ad" creative and are safe to ship while the app is
// still in development.
import 'dart:io';

import 'package:flutter/foundation.dart';

import '../models/app_branding.dart';
import '../services/app_branding_service.dart';

class AdConfig {
  const AdConfig._();

  // TODO: replace with the real AdMob Android App ID in
  // android/app/src/main/AndroidManifest.xml before release.
  static const String androidAppId =
      'ca-app-pub-3940256099942544~3347511713';

  // TODO: replace with the real AdMob iOS App ID in ios/Runner/Info.plist
  // before release.
  static const String iosAppId = 'ca-app-pub-3940256099942544~1458002511';

  static const String _testNativeAndroid =
      'ca-app-pub-3940256099942544/2247696110';
  static const String _testNativeIos =
      'ca-app-pub-3940256099942544/3986624511';

  static bool get _isIos => !kIsWeb && Platform.isIOS;

  static AppBranding get _branding => AppBrandingService.instance.cachedOrFallback;

  static bool get adsEnabled => _branding.admobAdsEnabled;

  static String get nativeAdUnitIdOrderTracking {
    final configured = _isIos
        ? _branding.admobIosOrderTrackingAdUnitId.trim()
        : _branding.admobAndroidOrderTrackingAdUnitId.trim();
    if (configured.isNotEmpty) return configured;
    return _isIos ? _testNativeIos : _testNativeAndroid;
  }

  static String get nativeAdUnitIdCheckout {
    final configured = _isIos
        ? _branding.admobIosCheckoutAdUnitId.trim()
        : _branding.admobAndroidCheckoutAdUnitId.trim();
    if (configured.isNotEmpty) return configured;
    return _isIos ? _testNativeIos : _testNativeAndroid;
  }
}
