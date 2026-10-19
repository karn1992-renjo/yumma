// lib/services/splash_campaign_service.dart
import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_constants.dart';
import '../models/splash_campaign.dart';
import 'api_service.dart';

/// Fetches the remotely-configured promotional splash campaign. Mirrors
/// AppBrandingService's cache-first-then-refresh shape: on a cold start we
/// never want to block the splash indefinitely on a slow network, so the
/// network call has its own short timeout and any valid cached campaign is
/// used as a fallback.
class SplashCampaignService {
  SplashCampaignService._();

  static final SplashCampaignService instance = SplashCampaignService._();
  static const String _cacheKey = 'splash_campaign_cache_v1';
  static const Duration _networkTimeout = Duration(seconds: 4);

  final ApiService _api = ApiService();

  Future<SplashCampaign?> loadActiveCampaign() async {
    SplashCampaign? cached;
    try {
      final prefs = await SharedPreferences.getInstance();
      final cachedJson = prefs.getString(_cacheKey);
      if (cachedJson != null && cachedJson.isNotEmpty) {
        final decoded = jsonDecode(cachedJson);
        if (decoded is Map<String, dynamic>) {
          cached = SplashCampaign.fromJson(decoded);
        }
      }
    } catch (_) {
      // Ignore cache decode issues -- fall through to a network fetch.
    }

    try {
      final platform = defaultTargetPlatform == TargetPlatform.iOS
          ? 'ios'
          : defaultTargetPlatform == TargetPlatform.android
              ? 'android'
              : 'both';

      final response = await _api
          .get(
            ApiConstants.activeSplashCampaign,
            queryParams: {'platform': platform},
            includeAuth: false,
          )
          .timeout(_networkTimeout);

      if (response is Map<String, dynamic> && response['success'] == true) {
        final data = response['data'];
        final campaign = data is Map<String, dynamic>
            ? SplashCampaign.fromJson(data)
            : null;

        unawaited(_persist(data is Map<String, dynamic> ? data : null));

        return (campaign != null && campaign.isEligibleNow) ? campaign : null;
      }
    } catch (_) {
      // Network unavailable, timed out, or server error -- fall back below.
    }

    if (cached != null && cached.isEligibleNow) {
      return cached;
    }
    return null;
  }

  Future<void> _persist(Map<String, dynamic>? data) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (data == null) {
        await prefs.remove(_cacheKey);
      } else {
        await prefs.setString(_cacheKey, jsonEncode(data));
      }
    } catch (_) {
      // Non-fatal -- caching is a best-effort convenience.
    }
  }
}
