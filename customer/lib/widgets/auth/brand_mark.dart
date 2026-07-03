import 'package:flutter/material.dart';
import '../common/app_cached_image.dart';

import '../../models/app_branding.dart';
import '../../theme/brand_palette.dart';

class BrandMark extends StatelessWidget {
  final AppBranding branding;
  final double size;
  final Color fallbackBackground;
  final Color fallbackForeground;

  const BrandMark({
    super.key,
    required this.branding,
    this.size = 40,
    this.fallbackBackground = const Color(0xFFFF5A1F),
    this.fallbackForeground = Colors.white,
  });

  @override
  Widget build(BuildContext context) {
    final logoUrl = branding.preferredLogoUrl;
    if (logoUrl.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(size * 0.28),
        child: AppCachedImage(
          imageUrl: logoUrl,
          width: size,
          height: size,
          fit: BoxFit.cover,
          errorBuilder: (_, __, ___) => _fallback(),
        ),
      );
    }

    return _fallback();
  }

  Widget _fallback() {
    final trimmedName = branding.displayName.trim();
    final label =
        trimmedName.isNotEmpty ? trimmedName.substring(0, 1).toUpperCase() : 'F';
    final resolvedBackground = fallbackBackground == const Color(0xFFFF5A1F)
        ? (colorFromHex(branding.primaryColorHex) ?? fallbackBackground)
        : fallbackBackground;

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: resolvedBackground,
        borderRadius: BorderRadius.circular(size * 0.28),
      ),
      alignment: Alignment.center,
      child: Text(
        label,
        style: TextStyle(
          color: fallbackForeground,
          fontSize: size * 0.42,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
