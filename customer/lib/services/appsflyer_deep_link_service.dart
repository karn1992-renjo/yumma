import 'dart:async';
import 'dart:io';

import 'package:appsflyer_sdk/appsflyer_sdk.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/app_config.dart';
import '../screens/customer/search_screen.dart';
import '../screens/customer/home_screen.dart';
import '../screens/customer/restaurant_detail_screen.dart';
import '../screens/customer/campaign_landing_screen.dart';
import 'navigation_service.dart';

class AppsFlyerDeepLinkService {
  AppsFlyerDeepLinkService._();

  static final AppsFlyerDeepLinkService instance = AppsFlyerDeepLinkService._();
  static const String pendingReferralCodeKey = 'pending_referral_code';

  AppsflyerSdk? _sdk;
  bool _initialized = false;
  String? _lastNavigationKey;
  DateTime? _lastNavigationAt;

  Future<void> initialize() async {
    if (_initialized || !AppConfig.isCustomerApp) return;
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
    final destination = AppsFlyerDeepLinkDestination.fromPayload(payload);
    if (destination == null) {
      _log('No AppsFlyer route found for $source payload.');
      return;
    }

    final eventKey = '$source:${destination.signature}';
    if (_isDuplicate(eventKey)) {
      _log('Duplicate AppsFlyer navigation ignored: $eventKey');
      return;
    }

    _lastNavigationKey = eventKey;
    _lastNavigationAt = DateTime.now();
    _log('Handling AppsFlyer destination: $destination');

    if (destination.type == AppsFlyerDeepLinkType.referral) {
      await _storeReferralCode(destination.primaryRaw);
      _navigateHome('Stored referral code.');
      return;
    }

    final isValid = destination.isValid;
    if (!isValid) {
      _log('Invalid AppsFlyer destination, falling back home: $destination');
      _navigateHome('Invalid destination.');
      return;
    }

    _navigate(destination);
  }

  bool _isDuplicate(String eventKey) {
    final lastAt = _lastNavigationAt;
    if (_lastNavigationKey != eventKey || lastAt == null) return false;
    return DateTime.now().difference(lastAt) < const Duration(seconds: 3);
  }

  Future<void> _storeReferralCode(String? referralCode) async {
    final code = referralCode?.trim();
    if (code == null || code.isEmpty) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(pendingReferralCodeKey, code);
    _log('Stored pending referral code: $code');
  }

  void _navigate(AppsFlyerDeepLinkDestination destination) {
    switch (destination.type) {
      case AppsFlyerDeepLinkType.restaurant:
        _push(
          RestaurantDetailScreen(restaurantId: destination.primaryId!),
        );
        return;
      case AppsFlyerDeepLinkType.product:
        final restaurantId = destination.secondaryId;
        if (restaurantId == null || restaurantId <= 0) {
          _log(
            'Product deep link requires deep_link_sub2 restaurant_id in this app.',
          );
          _navigateHome('Missing product restaurant context.');
          return;
        }
        _push(
          RestaurantDetailScreen(
            restaurantId: restaurantId,
            initialMenuItemId: destination.primaryId,
          ),
        );
        return;
      case AppsFlyerDeepLinkType.category:
        _pushWithArguments(
          const SearchScreen(),
          <String, dynamic>{
            'query': 'Category',
            'title': 'Category',
            'browseMode': 'category',
            'cuisine_id': destination.primaryId,
          },
        );
        return;
      case AppsFlyerDeepLinkType.branch:
        _push(
          RestaurantDetailScreen(restaurantId: destination.primaryId!),
        );
        return;
      case AppsFlyerDeepLinkType.campaign:
        _push(
          CampaignLandingScreen(campaignId: destination.primaryId!),
        );
        return;
      case AppsFlyerDeepLinkType.banner:
        _navigateHome('Banner destination opens home.');
        return;
      case AppsFlyerDeepLinkType.referral:
        _navigateHome('Referral handled.');
        return;
    }
  }

  void _push(Widget screen) {
    _withNavigator((navigator) {
      navigator.pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => screen),
        (_) => false,
      );
    });
  }

  void _pushWithArguments(Widget screen, Object arguments) {
    _withNavigator((navigator) {
      navigator.pushAndRemoveUntil(
        MaterialPageRoute(
          settings: RouteSettings(arguments: arguments),
          builder: (_) => screen,
        ),
        (_) => false,
      );
    });
  }

  void _navigateHome(String reason) {
    _log('Fallback home: $reason');
    _withNavigator((navigator) {
      navigator.pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const HomeScreen()),
        (_) => false,
      );
    });
  }

  void _withNavigator(
    void Function(NavigatorState navigator) action, {
    int attempt = 0,
  }) {
    final navigator = appNavigatorKey.currentState;
    if (navigator != null) {
      action(navigator);
      return;
    }
    if (attempt >= 8) {
      _log('Navigator not ready; dropping AppsFlyer navigation.');
      return;
    }
    Future<void>.delayed(const Duration(milliseconds: 250), () {
      _withNavigator(action, attempt: attempt + 1);
    });
  }

  void _log(String message) {
    debugPrint('[AppsFlyerDeepLink] $message');
  }
}

enum AppsFlyerDeepLinkType {
  restaurant,
  product,
  category,
  branch,
  campaign,
  banner,
  referral,
}

class AppsFlyerDeepLinkDestination {
  const AppsFlyerDeepLinkDestination({
    required this.type,
    required this.primaryRaw,
    this.secondaryRaw,
    this.tertiaryRaw,
  });

  final AppsFlyerDeepLinkType type;
  final String? primaryRaw;
  final String? secondaryRaw;
  final String? tertiaryRaw;

  int? get primaryId => _parsePositiveInt(primaryRaw);
  int? get secondaryId => _parsePositiveInt(secondaryRaw);
  int? get tertiaryId => _parsePositiveInt(tertiaryRaw);

  bool get isValid {
    if (type == AppsFlyerDeepLinkType.referral) {
      return primaryRaw?.trim().isNotEmpty == true;
    }
    return primaryId != null;
  }

  String get signature =>
      '${type.name}:${primaryRaw ?? ''}:${secondaryRaw ?? ''}:${tertiaryRaw ?? ''}';

  static AppsFlyerDeepLinkDestination? fromPayload(Map<String, dynamic> payload) {
    final value = _read(payload, const [
      'deep_link_value',
      'deepLinkValue',
      'screen',
      'type',
    ])?.toLowerCase();
    var type = _typeFrom(value);
    var primaryRaw = _read(payload, const ['deep_link_sub1', 'deepLinkSub1']);
    var secondaryRaw = _read(payload, const ['deep_link_sub2', 'deepLinkSub2']);
    var tertiaryRaw = _read(payload, const ['deep_link_sub3', 'deepLinkSub3']);

    if (type == null) {
      final deepLinkUri = _parseDeepLinkUri(payload);
      if (deepLinkUri != null) {
        final parsed = _parseDeepLinkUriSegments(deepLinkUri);
        type = parsed?.type;
        if (parsed != null) {
          primaryRaw ??= parsed.primaryRaw;
          secondaryRaw ??= parsed.secondaryRaw;
          tertiaryRaw ??= parsed.tertiaryRaw;
        }
      }
    }

    if (type == null) {
      if (primaryRaw != null && secondaryRaw != null) {
        type = AppsFlyerDeepLinkType.product;
      } else if (primaryRaw != null) {
        type = AppsFlyerDeepLinkType.restaurant;
      }
    }

    if (type == null) return null;

    return AppsFlyerDeepLinkDestination(
      type: type,
      primaryRaw: primaryRaw,
      secondaryRaw: secondaryRaw,
      tertiaryRaw: tertiaryRaw,
    );
  }

  static Uri? _parseDeepLinkUri(Map<String, dynamic> payload) {
    // Prefer the explicit web redirect payload over Appsflyer internal deep-link values.
    final uriValue = _read(payload, const [
      'link',
      'click_url',
      'af_web_dp',
      'deep_link',
    ]);
    if (uriValue == null) return null;
    try {
      return Uri.parse(uriValue);
    } catch (_) {
      return null;
    }
  }

  static _DeepLinkParseResult? _parseDeepLinkUriSegments(Uri uri) {
    final segments = uri.pathSegments.where((s) => s.isNotEmpty).toList();
    if (segments.isEmpty) return null;

    if (uri.scheme == 'foodflow' || uri.host == 'foodflow.in') {
      if (segments.length >= 2 &&
          (segments[0] == 'restaurants' || segments[0] == 'restaurant')) {
        final restaurantId = segments[1];
        final menuItemId = uri.queryParameters['menu_item_id'];
        if (menuItemId != null && menuItemId.isNotEmpty) {
          return _DeepLinkParseResult(
            type: AppsFlyerDeepLinkType.product,
            primaryRaw: menuItemId,
            secondaryRaw: restaurantId,
          );
        }
        return _DeepLinkParseResult(
          type: AppsFlyerDeepLinkType.restaurant,
          primaryRaw: restaurantId,
        );
      }
    }

    return null;
  }

  static int? _parsePositiveInt(String? value) {
    final parsed = int.tryParse(value?.trim() ?? '');
    return parsed != null && parsed > 0 ? parsed : null;
  }

  static String? _read(Map<String, dynamic> payload, List<String> keys) {
    for (final key in keys) {
      final value = payload[key];
      final text = value?.toString().trim();
      if (text != null && text.isNotEmpty && text != 'null') return text;
    }
    return null;
  }

  static AppsFlyerDeepLinkType? _typeFrom(String? value) {
    switch (value) {
      case 'restaurant':
        return AppsFlyerDeepLinkType.restaurant;
      case 'product':
      case 'menu_item':
      case 'item':
        return AppsFlyerDeepLinkType.product;
      case 'category':
      case 'cuisine':
        return AppsFlyerDeepLinkType.category;
      case 'branch':
        return AppsFlyerDeepLinkType.branch;
      case 'campaign':
        return AppsFlyerDeepLinkType.campaign;
      case 'banner':
        return AppsFlyerDeepLinkType.banner;
      case 'referral':
      case 'invite':
        return AppsFlyerDeepLinkType.referral;
    }
    return null;
  }

  @override
  String toString() {
    return 'AppsFlyerDeepLinkDestination(type: ${type.name}, '
        'sub1: $primaryRaw, sub2: $secondaryRaw, sub3: $tertiaryRaw)';
  }
}

class _DeepLinkParseResult {
  const _DeepLinkParseResult({
    required this.type,
    this.primaryRaw,
    this.secondaryRaw,
  });

  final AppsFlyerDeepLinkType type;
  final String? primaryRaw;
  final String? secondaryRaw;
  String? get tertiaryRaw => null;
}
