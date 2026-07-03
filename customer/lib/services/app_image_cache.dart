import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';

import '../config/app_config.dart';

class AppImageCache {
  AppImageCache._();

  static final CacheManager instance = CacheManager(
    Config(
      'yumma_image_cache_v1',
      stalePeriod: const Duration(days: 7),
      maxNrOfCacheObjects: 600,
      repo: JsonCacheInfoRepository(databaseName: 'yumma_image_cache_v1'),
      fileService: HttpFileService(),
    ),
  );

  static String resolveUrl(String rawValue) {
    final value = rawValue.trim();
    if (value.isEmpty ||
        value == 'null' ||
        value.startsWith('{') ||
        value.startsWith('[')) {
      return '';
    }
    final absolute = Uri.tryParse(value);
    if (absolute?.scheme == 'http' || absolute?.scheme == 'https') return value;

    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final port = apiUri.hasPort ? ':${apiUri.port}' : '';
    final origin = '${apiUri.scheme}://${apiUri.host}$port';
    final normalized = value.startsWith('/') ? value.substring(1) : value;
    if (normalized.startsWith('storage/')) return '$origin/$normalized';
    if (value.startsWith('/')) return '$origin/$normalized';
    return '$origin/storage/$normalized';
  }

  static Future<void> precacheVisible(
    BuildContext context,
    Iterable<String> urls, {
    int limit = 6,
    int concurrency = 3,
  }) async {
    final unique = urls.map(resolveUrl).where((url) => url.isNotEmpty).toSet();
    final selected = unique.take(limit).toList(growable: false);
    for (var start = 0; start < selected.length; start += concurrency) {
      final end = (start + concurrency).clamp(0, selected.length);
      await Future.wait(
        selected.sublist(start, end).map((url) async {
          try {
            await precacheImage(
              CachedNetworkImageProvider(url, cacheManager: instance),
              context,
            );
          } catch (_) {}
        }),
      );
    }
  }
}
