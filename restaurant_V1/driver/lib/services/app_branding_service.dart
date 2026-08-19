import 'dart:convert';

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
        }
      } catch (_) {
        // Ignore cache decode issues and refetch.
      }
    }

    try {
      final response = await _api.get(ApiConstants.appBranding);
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
