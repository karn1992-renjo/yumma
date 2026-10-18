import 'dart:convert';
import 'dart:async';

import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_constants.dart';
import '../models/app_branding.dart';
import 'api_service.dart';

class AppBrandingService {
  AppBrandingService._();

  static final AppBrandingService instance = AppBrandingService._();
  static const String _cacheKey = 'app_branding_cache';

  final ApiService _api = ApiService();
  AppBranding? _memoryCache;

  // Synchronous access to whatever's already in memory, for call sites that
  // can't await (e.g. building an ad widget's adUnitId). loadBranding() is
  // already called once at app startup, so by the time a user reaches a
  // screen deep enough to show an ad, this is populated.
  AppBranding get cachedOrFallback => _memoryCache ?? AppBranding.fallback();

  Future<AppBranding> loadBranding({bool forceRefresh = false}) async {
    if (!forceRefresh && _memoryCache != null) {
      return _memoryCache!;
    }

    final prefs = await SharedPreferences.getInstance();
    final cachedJson = prefs.getString(_cacheKey);

    if (!forceRefresh && cachedJson != null && cachedJson.isNotEmpty) {
      try {
        final decoded = jsonDecode(cachedJson);
        if (decoded is Map<String, dynamic>) {
          _memoryCache = AppBranding.fromJson(decoded);
          unawaited(loadBranding(forceRefresh: true));
          return _memoryCache!;
        }
      } catch (_) {
        // Ignore cache decode issues and refetch.
      }
    }

    try {
      final response = await _api.get(
        ApiConstants.appBranding,
        includeAuth: false,
        cachePolicy: ApiCachePolicy.staticContent,
        cacheFirst: !forceRefresh,
      );
      if (response is Map<String, dynamic> &&
          response['success'] == true &&
          response['data'] is Map) {
        final branding = AppBranding.fromJson(
          Map<String, dynamic>.from(response['data'] as Map),
        );
        _memoryCache = branding;
        await prefs.setString(_cacheKey, jsonEncode(branding.toJson()));
        return branding;
      }
    } catch (_) {
      // Fall back to cached or default branding below.
    }

    return _memoryCache ?? AppBranding.fallback();
  }
}
