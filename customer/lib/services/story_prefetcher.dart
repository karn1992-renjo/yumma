// lib/services/story_prefetcher.dart
import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';

import '../config/api_constants.dart';
import 'api_service.dart';

/// Makes stories open instantly, the way Instagram does: while the home rail
/// is on screen it fetches each restaurant's reel and downloads the first
/// segments' pictures / videos to the device, so a tap finds everything local.
class StoryPrefetcher {
  StoryPrefetcher._();
  static final StoryPrefetcher instance = StoryPrefetcher._();

  /// Dedicated cache so story videos never evict other app images.
  static final CacheManager videoCache = CacheManager(
    Config(
      'storyVideoCache',
      stalePeriod: const Duration(days: 2),
      maxNrOfCacheObjects: 30,
    ),
  );

  static const _reelTtl = Duration(seconds: 90);
  static const _bubblesToWarm = 6;
  static const _segmentsToWarm = 2;

  final ApiService _api = ApiService();
  final Map<int, _CachedReel> _reels = {};
  final Set<String> _warming = {};

  /// A restaurant's current reel. Reuses an in-flight or recent request.
  Future<List<Map<String, dynamic>>> reel(int restaurantId, {bool force = false}) {
    final cached = _reels[restaurantId];
    if (!force && cached != null && DateTime.now().difference(cached.at) < _reelTtl) {
      return cached.future;
    }
    final future = _fetch(restaurantId);
    final entry = _CachedReel(future);
    _reels[restaurantId] = entry;
    // A failed fetch must not be served again from the cache.
    future.catchError((_) {
      if (identical(_reels[restaurantId], entry)) _reels.remove(restaurantId);
      return <Map<String, dynamic>>[];
    });
    return future;
  }

  Future<List<Map<String, dynamic>>> _fetch(int restaurantId) async {
    final response = await _api.get(ApiConstants.restaurantStories(restaurantId));
    if (response['success'] != true) {
      throw StateError(response['message']?.toString() ?? 'Unable to load stories.');
    }
    return (response['data'] as List? ?? [])
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();
  }

  /// Warms the first bubbles of the rail (unseen ones first). Safe to call often.
  void warmRail(List<Map<String, dynamic>> items) {
    final ordered = [...items]..sort((a, b) {
        final au = a['has_unseen'] == true ? 0 : 1;
        final bu = b['has_unseen'] == true ? 0 : 1;
        return au.compareTo(bu);
      });
    for (final item in ordered.take(_bubblesToWarm)) {
      final id = (item['restaurant_id'] as num?)?.toInt();
      if (id == null) continue;
      unawaited(_warmRestaurant(id));
    }
  }

  Future<void> _warmRestaurant(int restaurantId) async {
    try {
      final stories = await reel(restaurantId);
      for (final story in stories.take(_segmentsToWarm)) {
        unawaited(warmStory(story));
      }
    } catch (e) {
      debugPrint('Story warm-up skipped for $restaurantId: $e');
    }
  }

  /// Downloads a segment's media (and poster) into the local caches.
  Future<void> warmStory(Map<String, dynamic> story) async {
    final isVideo = story['media_type'] == 'video';
    final media = story['media_url']?.toString();
    final thumb = story['thumbnail_url']?.toString();

    if (thumb != null && thumb.isNotEmpty) await _download(DefaultCacheManager(), thumb);
    if (media == null || media.isEmpty) return;
    await _download(isVideo ? videoCache : DefaultCacheManager(), media);
  }

  Future<void> _download(CacheManager manager, String url) async {
    if (!_warming.add(url)) return;
    try {
      await manager.downloadFile(url);
    } catch (e) {
      debugPrint('Story media warm-up failed: $e');
    } finally {
      _warming.remove(url);
    }
  }

  /// The downloaded video file, or null if it hasn't finished downloading yet.
  Future<File?> cachedVideo(String url) async {
    try {
      final info = await videoCache.getFileFromCache(url);
      return info?.file;
    } catch (_) {
      return null;
    }
  }
}

class _CachedReel {
  _CachedReel(this.future) : at = DateTime.now();

  final Future<List<Map<String, dynamic>>> future;
  final DateTime at;
}
