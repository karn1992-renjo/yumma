import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import 'app_cached_image.dart';

class NetworkImageLoader extends StatelessWidget {
  const NetworkImageLoader(
      {super.key,
      required this.imageUrl,
      this.width = 100,
      this.height = 100,
      this.fit = BoxFit.cover,
      this.borderRadius});

  final String imageUrl;
  final double width;
  final double height;
  final BoxFit fit;
  final BorderRadius? borderRadius;

  @override
  Widget build(BuildContext context) {
    if (imageUrl.isEmpty) return _placeholder();
    final image = AppCachedImage(
      imageUrl: imageUrl,
      width: width,
      height: height,
      fit: fit,
      loadingBuilder: (_, __, ___) => Shimmer.fromColors(
          baseColor: Colors.grey.shade300,
          highlightColor: Colors.grey.shade100,
          child: Container(width: width, height: height, color: Colors.white)),
      errorBuilder: (_, __, ___) => _placeholder(),
    );
    return borderRadius == null
        ? image
        : ClipRRect(borderRadius: borderRadius!, child: image);
  }

  Widget _placeholder() => Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
            color: Colors.grey.shade200, borderRadius: borderRadius),
        child: const Icon(Icons.image_not_supported, color: Colors.grey),
      );
}
