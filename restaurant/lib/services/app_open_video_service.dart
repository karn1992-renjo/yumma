// lib/services/app_open_video_service.dart
import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_constants.dart';
import '../screens/restaurant/app_open_video_screen.dart';
import 'api_service.dart';

/// Plays the admin's "app open" video once per person, skippable.
///
/// The file is kept in a cache (flutter_cache_manager), so it downloads once, plays instantly and works offline.
/// "Seen" is remembered on the server (per account) and on the device, so a video that was watched or skipped
/// never plays again, and a replaced video (new key) plays once.
class AppOpenVideoService {
  AppOpenVideoService._();

  /// Dedicated cache: small, long-lived. The default image cache is not meant for large files.
  static final CacheManager cache = CacheManager(
    Config(
      'swado_app_open_video',
      stalePeriod: const Duration(days: 365),
      maxNrOfCacheObjects: 3,
      fileService: HttpFileService(),
    ),
  );

  static const String _seenKey = 'app_open_video_seen_keys';
  static const Duration _fetchTimeout = Duration(seconds: 5);
  static const Duration _downloadWait = Duration(seconds: 8);

  static bool _checkedThisRun = false;

  /// Call once the signed-in dashboard is on screen. Never throws and never blocks the app: any problem simply
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
      if (await _alreadySeen(key)) {
        unawaited(_report(id, skipped: false)); // the server missed it earlier; tell it now
        return;
      }

      final file = await _cachedFile(url, key);
      if (file == null || !context.mounted) return;

      final skipAfter = ((config['skip_after_seconds'] as num?)?.toInt() ?? 0).clamp(0, 30);
      final result = await Navigator.of(context, rootNavigator: true).push<AppOpenVideoResult>(
        PageRouteBuilder(
          opaque: true,
          transitionDuration: const Duration(milliseconds: 220),
          pageBuilder: (_, __, ___) => AppOpenVideoScreen(file: file, skipAfterSeconds: skipAfter),
          transitionsBuilder: (_, animation, __, child) => FadeTransition(opacity: animation, child: child),
        ),
      );

      // Watched or skipped = seen. A playback error is not, so it can try again next time.
      if (result == AppOpenVideoResult.finished || result == AppOpenVideoResult.skipped) {
        await _markSeen(key);
        unawaited(_report(id, skipped: result == AppOpenVideoResult.skipped));
      }
    } catch (e) {
      debugPrint('App open video skipped: $e');
    }
  }

  static Future<Map<String, dynamic>?> _fetch() async {
    try {
      final response = await ApiService().get(ApiConstants.restaurantAppVideo).timeout(_fetchTimeout);
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

  static Future<bool> _alreadySeen(String key) async {
    final prefs = await SharedPreferences.getInstance();
    return (prefs.getStringList(_seenKey) ?? const <String>[]).contains(key);
  }

  static Future<void> _markSeen(String key) async {
    final prefs = await SharedPreferences.getInstance();
    final seen = prefs.getStringList(_seenKey) ?? <String>[];
    if (!seen.contains(key)) {
      seen.add(key);
      await prefs.setStringList(_seenKey, seen.length > 50 ? seen.sublist(seen.length - 50) : seen);
    }
  }

  static Future<void> _report(int id, {required bool skipped}) async {
    try {
      await ApiService().post(ApiConstants.restaurantAppVideoViewed(id), data: {'skipped': skipped});
    } catch (_) {
      // Remembered on the device regardless; the server hears about it the next time.
    }
  }
}
