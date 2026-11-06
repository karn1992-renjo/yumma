// lib/services/app_open_video_service.dart
import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';

import '../config/api_constants.dart';
import '../screens/customer/app_open_video_screen.dart';
import 'api_service.dart';

/// Plays the admin's customer "app open" video every time the app is opened, skippable. Works for guests too.
///
/// The file is kept in a cache (flutter_cache_manager), so it downloads once, plays instantly and works offline.
/// There is no "seen" memory: it plays on every cold start (once per launch, not on every return to the home screen).
class AppOpenVideoService {
  AppOpenVideoService._();

  /// Dedicated cache: small, long-lived. The default image cache is not meant for large files.
  static final CacheManager cache = CacheManager(
    Config(
      'customer_app_open_video',
      stalePeriod: const Duration(days: 365),
      maxNrOfCacheObjects: 3,
      fileService: HttpFileService(),
    ),
  );

  static const Duration _fetchTimeout = Duration(seconds: 5);
  static const Duration _downloadWait = Duration(seconds: 8);

  static bool _checkedThisRun = false;

  /// Call once the home screen is on screen. Never throws and never blocks the app: any problem simply
  /// skips the video this time.
  static Future<void> maybePlay(BuildContext context) async {
    if (_checkedThisRun) return;
    _checkedThisRun = true;

    try {
      final config = await _fetch();
      if (config == null) return;

      final key = config['key']?.toString() ?? '';
      final url = config['url']?.toString() ?? '';
      final id = int.tryParse('${config['id']}');
      if (key.isEmpty || url.isEmpty || id == null) return;

      final file = await _cachedFile(url, key);
      if (file == null || !context.mounted) return;

      final skipAfter = ((config['skip_after_seconds'] as num?)?.toInt() ?? 0).clamp(0, 30);
      await Navigator.of(context, rootNavigator: true).push<AppOpenVideoResult>(
        PageRouteBuilder(
          opaque: true,
          transitionDuration: const Duration(milliseconds: 220),
          pageBuilder: (_, __, ___) => AppOpenVideoScreen(file: file, skipAfterSeconds: skipAfter),
          transitionsBuilder: (_, animation, __, child) => FadeTransition(opacity: animation, child: child),
        ),
      );
    } catch (e) {
      debugPrint('App open video skipped: $e');
    }
  }

  static Future<Map<String, dynamic>?> _fetch() async {
    try {
      final response = await ApiService().get(ApiConstants.customerAppVideo).timeout(_fetchTimeout);
      final data = response is Map ? response['data'] : null;
      return data is Map ? Map<String, dynamic>.from(data) : null;
    } catch (_) {
      return null;
    }
  }

  /// The cached file; downloads it when missing. If the download is slow it carries on in the background and the
  /// video plays on the next open instead of holding the app up.
  static Future<File?> _cachedFile(String url, String key) async {
    final cached = await cache.getFileFromCache(key);
    if (cached != null && cached.validTill.isAfter(DateTime.now())) return cached.file;

    final download = cache.getSingleFile(url, key: key);
    try {
      return await download.timeout(_downloadWait);
    } on TimeoutException {
      unawaited(download.then<void>((_) {}, onError: (_) {}));
      return null;
    } catch (_) {
      return null;
    }
  }

}
