import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../services/app_image_cache.dart';

class AppCachedImage extends StatelessWidget {
  const AppCachedImage({
    super.key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit,
    this.alignment = Alignment.center,
    this.errorBuilder,
    this.loadingBuilder,
    this.color,
    this.colorBlendMode,
    this.filterQuality = FilterQuality.low,
  });

  final String imageUrl;
  final double? width;
  final double? height;
  final BoxFit? fit;
  final Alignment alignment;
  final ImageErrorWidgetBuilder? errorBuilder;
  final ImageLoadingBuilder? loadingBuilder;
  final Color? color;
  final BlendMode? colorBlendMode;
  final FilterQuality filterQuality;

  @override
  Widget build(BuildContext context) {
    final resolvedUrl = AppImageCache.resolveUrl(imageUrl);
    if (resolvedUrl.isEmpty) {
      return errorBuilder?.call(
            context,
            ArgumentError.value(imageUrl, 'imageUrl'),
            StackTrace.empty,
          ) ??
          SizedBox(width: width, height: height);
    }
    final targetWidth = width != null && width!.isFinite
        ? (width! * MediaQuery.devicePixelRatioOf(context)).round()
        : null;
    final targetHeight = height != null && height!.isFinite
        ? (height! * MediaQuery.devicePixelRatioOf(context)).round()
        : null;

    return CachedNetworkImage(
      cacheManager: AppImageCache.instance,
      imageUrl: resolvedUrl,
      width: width,
      height: height,
      fit: fit,
      alignment: alignment,
      memCacheWidth: targetWidth,
      memCacheHeight: targetHeight,
      fadeInDuration: const Duration(milliseconds: 90),
      fadeOutDuration: const Duration(milliseconds: 60),
      imageBuilder: (context, provider) => Image(
        image: provider,
        width: width,
        height: height,
        fit: fit,
        alignment: alignment,
        color: color,
        colorBlendMode: colorBlendMode,
        filterQuality: filterQuality,
        gaplessPlayback: true,
      ),
      placeholder: (context, _) => loadingBuilder?.call(
            context,
            SizedBox(width: width, height: height),
            null,
          ) ??
          SizedBox(width: width, height: height),
      errorWidget: (context, _, error) => errorBuilder?.call(
            context,
            error,
            StackTrace.empty,
          ) ??
          SizedBox(width: width, height: height),
    );
  }
}
