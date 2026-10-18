import 'dart:async';
import 'dart:io';

import 'package:appsflyer_sdk/appsflyer_sdk.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/app_config.dart';

/// Simplified AppsFlyer integration for the driver app.
///
/// Unlike the customer app, the driver app has no restaurant/product/
/// category/branch/campaign/banner deep-link destinations to route to —
/// the only thing worth capturing here is a referral code, so that it can
/// be attached to a partner application when the driver registers. Any
/// other (unrecognized) payload is just logged and otherwise ignored.
class AppsFlyerDeepLinkService {
  AppsFlyerDeepLinkService._();

  static final AppsFlyerDeepLinkService instance = AppsFlyerDeepLinkService._();
  static const String pendingReferralCodeKey = 'pending_referral_code';

  AppsflyerSdk? _sdk;
  bool _initialized = false;

  Future<void> initialize() async {
    if (_initialized || !AppConfig.isDriverApp) return;
    _initialized = true;

    final devKey = AppConfig.appsFlyerDevKey.trim();
    if (devKey.isEmpty) {
      _log('AppsFlyer disabled: APPSFLYER_DEV_KEY is empty.');
      return;
    }

    final options = AppsFlyerOptions(
      afDevKey: devKey,
      appId: AppConfig.appsFlyerAppId.trim(),
      showDebug: AppConfig.appsFlyerDebug,
      appInviteOneLink: AppConfig.appsFlyerOneLinkId.trim(),
      timeToWaitForATTUserAuthorization: 15,
    );

    final sdk = AppsflyerSdk(options);
    _sdk = sdk;

    sdk.onDeepLinking(_handleUnifiedDeepLink);
    sdk.onInstallConversionData(_handleInstallConversionData);
    sdk.onAppOpenAttribution(_handleAppOpenAttribution);

    try {
      await sdk.initSdk(
        registerConversionDataCallback: true,
        registerOnAppOpenAttributionCallback: true,
        registerOnDeepLinkingCallback: true,
      );
      _log(
        'AppsFlyer initialized. platform=${Platform.operatingSystem}, '
        'appId=${AppConfig.appsFlyerAppId.isEmpty ? 'not-set' : 'set'}, '
        'oneLinkId=${AppConfig.appsFlyerOneLinkId.isEmpty ? 'not-set' : AppConfig.appsFlyerOneLinkId}',
      );
    } catch (error, stackTrace) {
      _log('AppsFlyer init failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  void _handleUnifiedDeepLink(DeepLinkResult result) {
    _log('UDL result status=${result.status}, deepLink=${result.deepLink}');
    if (result.status != Status.FOUND || result.deepLink == null) {
      return;
    }

    final event = <String, dynamic>{
      ..._mapFrom(result.deepLink?.clickEvent),
      'deep_link_value': result.deepLink?.deepLinkValue,
    };
    unawaited(_handleEvent(event, source: 'udl'));
  }

  void _handleInstallConversionData(dynamic result) {
    final payload = _extractPayload(result);
    _log('Install conversion data: $payload');
    unawaited(_handleEvent(payload, source: 'conversion'));
  }

  void _handleAppOpenAttribution(dynamic result) {
    final payload = _extractPayload(result);
    _log('App open attribution: $payload');
    unawaited(_handleEvent(payload, source: 'open_attribution'));
  }

  Map<String, dynamic> _extractPayload(dynamic result) {
    final map = _mapFrom(result);
    for (final key in const ['payload', 'data', 'deep_link']) {
      final nested = map[key];
      if (nested is Map) {
        return {
          ...map,
          ...Map<String, dynamic>.from(nested),
        };
      }
    }
    return map;
  }

  Map<String, dynamic> _mapFrom(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return Map<String, dynamic>.from(value);
    return <String, dynamic>{};
  }

  Future<void> _handleEvent(
    Map<String, dynamic> payload, {
    required String source,
  }) async {
    final referralCode = _readReferralCode(payload);
    if (referralCode == null || referralCode.isEmpty) {
      _log('No referral code found in $source payload; ignoring.');
      return;
    }
    await _storeReferralCode(referralCode);
  }

  String? _readReferralCode(Map<String, dynamic> payload) {
    return _read(payload, const [
      'deep_link_sub1',
      'deepLinkSub1',
      'referral_code',
      'referralCode',
      'code',
    ]);
  }

  Future<void> _storeReferralCode(String? referralCode) async {
    final code = referralCode?.trim();
    if (code == null || code.isEmpty) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(pendingReferralCodeKey, code);
    _log('Stored pending referral code: $code');
  }

  static String? _read(Map<String, dynamic> payload, List<String> keys) {
    for (final key in keys) {
      final value = payload[key];
      final text = value?.toString().trim();
      if (text != null && text.isNotEmpty && text != 'null') return text;
    }
    return null;
  }

  void _log(String message) {
    debugPrint('[AppsFlyerDeepLink] $message');
  }
}
