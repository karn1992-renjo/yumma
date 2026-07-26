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
    this.errorWidget,
    this.loadingBuilder,
    this.color,
    this.colorBlendMode,
    this.filterQuality = FilterQuality.low,
    this.borderRadius,
    this.heroTag,
    this.cacheKey,
    this.placeholder,
    this.fadeIn = true,
  });

  final String imageUrl;
  final double? width;
  final double? height;
  final BoxFit? fit;
  final Alignment alignment;
  final ImageErrorWidgetBuilder? errorBuilder;
  final Widget? errorWidget;
  final ImageLoadingBuilder? loadingBuilder;
  final Color? color;
  final BlendMode? colorBlendMode;
  final FilterQuality filterQuality;
  final BorderRadius? borderRadius;
  final Object? heroTag;
  final String? cacheKey;
  final Widget? placeholder;
  final bool fadeIn;

  @override
  Widget build(BuildContext context) {
    final resolvedUrl = AppImageCache.resolveUrl(imageUrl);
    if (resolvedUrl.isEmpty) {
      return errorBuilder?.call(
            context,
            ArgumentError.value(imageUrl, 'imageUrl'),
            StackTrace.empty,
          ) ??
          errorWidget ??
          _wrap(SizedBox(width: width, height: height));
    }
    final pixelRatio = MediaQuery.devicePixelRatioOf(context);
    final screenWidth = MediaQuery.sizeOf(context).width;
    final targetWidth = width != null && width!.isFinite
        ? (width! * pixelRatio).round()
        : (screenWidth * pixelRatio).round();
    final targetHeight =
        fit == BoxFit.fill && height != null && height!.isFinite
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
      placeholder: (context, _) =>
          loadingBuilder?.call(
            context,
            placeholder ?? SizedBox(width: width, height: height),
            null,
          ) ??
          placeholder ??
          SizedBox(width: width, height: height),
      errorWidget: (context, _, error) =>
          errorBuilder?.call(
            context,
            error,
            StackTrace.empty,
          ) ??
          errorWidget ??
          SizedBox(width: width, height: height),
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
}
