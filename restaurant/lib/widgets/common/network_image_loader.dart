import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';

import '../../config/app_config.dart';

class AppImageCache {
  AppImageCache._();

  static final CacheManager instance = CacheManager(
    Config(
      'Yumma_image_cache_v3',
      stalePeriod: const Duration(days: 30),
      maxNrOfCacheObjects: 1000,
      fileService: HttpFileService(),
    ),
  );

  static void configureMemoryCache() {
    final cache = PaintingBinding.instance.imageCache;
    cache.maximumSize = 300;
    cache.maximumSizeBytes = 96 << 20;
  }

  static String resolveUrl(String rawValue) {
    final value = rawValue.trim();
    if (value.isEmpty ||
        value == 'null' ||
        value.startsWith('{') ||
        value.startsWith('[')) {
      return '';
    }

    final absolute = Uri.tryParse(value);
    if (absolute?.scheme == 'http' || absolute?.scheme == 'https') {
      return value;
    }

    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final port = apiUri.hasPort ? ':${apiUri.port}' : '';
    final origin = '${apiUri.scheme}://${apiUri.host}$port';
    var normalized = value.startsWith('/') ? value.substring(1) : value;
    if (normalized.startsWith('public/')) {
      normalized = normalized.substring('public/'.length);
    }
    if (normalized.startsWith('storage/')) return '$origin/$normalized';
    if (value.startsWith('/')) return '$origin/$normalized';

    return '$origin/storage/$normalized';
  }

  static Future<void> precacheVisible(
    BuildContext context,
    Iterable<String> urls, {
    int limit = 10,
    int concurrency = 3,
  }) async {
    final selected = urls
        .map(resolveUrl)
        .where((url) => url.isNotEmpty)
        .toSet()
        .take(limit)
        .toList(growable: false);

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

class AppNetworkImage extends StatelessWidget {
  const AppNetworkImage({
    super.key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.alignment = Alignment.center,
    this.borderRadius,
    this.heroTag,
    this.cacheKey,
    this.placeholder,
    this.errorWidget,
    this.color,
    this.colorBlendMode,
    this.filterQuality = FilterQuality.low,
    this.fadeIn = true,
    this.lazy = true,
  });

  final String imageUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final Alignment alignment;
  final BorderRadius? borderRadius;
  final Object? heroTag;
  final String? cacheKey;
  final Widget? placeholder;
  final Widget? errorWidget;
  final Color? color;
  final BlendMode? colorBlendMode;
  final FilterQuality filterQuality;
  final bool fadeIn;
  final bool lazy;

  @override
  Widget build(BuildContext context) {
    final resolvedUrl = AppImageCache.resolveUrl(imageUrl);
    final uri = Uri.tryParse(resolvedUrl);
    if (resolvedUrl.isEmpty || uri == null || !uri.isAbsolute) {
      return _wrap(errorWidget ?? _placeholder());
    }

    final pixelRatio = MediaQuery.devicePixelRatioOf(context);
    final screenWidth = MediaQuery.sizeOf(context).width;
    final targetWidth = width != null && width!.isFinite
        ? (width! * pixelRatio).round()
        : (screenWidth * pixelRatio).round();
    final targetHeight = height != null && height!.isFinite
        ? (height! * pixelRatio).round()
        : null;

    final image = CachedNetworkImage(
      cacheManager: AppImageCache.instance,
      cacheKey: cacheKey,
      imageUrl: resolvedUrl,
      width: width,
      height: height,
      fit: fit,
      alignment: alignment,
      color: color,
      colorBlendMode: colorBlendMode,
      filterQuality: filterQuality,
      memCacheWidth: targetWidth,
      memCacheHeight: targetHeight,
      useOldImageOnUrlChange: true,
      fadeInDuration: fadeIn ? const Duration(milliseconds: 80) : Duration.zero,
      fadeOutDuration: Duration.zero,
      placeholderFadeInDuration: Duration.zero,
      placeholder: (context, _) => placeholder ?? _placeholder(),
      errorWidget: (context, _, __) => errorWidget ?? _placeholder(),
    );

    return _wrap(image);
  }

  Widget _wrap(Widget child) {
    Widget current = borderRadius == null
        ? child
        : ClipRRect(borderRadius: borderRadius!, child: child);
    if (heroTag != null) {
      current = Hero(tag: heroTag!, child: current);
    }
    return current;
  }

  Widget _placeholder() => Container(
        width: width,
        height: height,
        color: Colors.grey.shade200,
        alignment: Alignment.center,
        child: Icon(Icons.image_not_supported, color: Colors.grey.shade500),
      );
}

class NetworkImageLoader extends AppNetworkImage {
  const NetworkImageLoader({
    super.key,
    required super.imageUrl,
    super.width = 100,
    super.height = 100,
    super.fit = BoxFit.cover,
    super.borderRadius,
    super.heroTag,
    super.cacheKey,
    super.placeholder,
    super.errorWidget,
    super.fadeIn,
    super.lazy,
  });
}
