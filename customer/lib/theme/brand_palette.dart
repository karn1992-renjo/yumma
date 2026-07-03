import 'package:flutter/material.dart';

import '../models/app_branding.dart';

class BrandPalette {
  const BrandPalette({
    required this.primary,
    required this.secondary,
  });

  final Color primary;
  final Color secondary;

  factory BrandPalette.fallback() {
    return const BrandPalette(
      primary: Color(0xFF8B5CF6),
      secondary: Color(0xFF111827),
    );
  }

  factory BrandPalette.fromBranding(AppBranding? branding) {
    if (branding == null) return BrandPalette.fallback();
    return BrandPalette(
      primary: colorFromHex(branding.primaryColorHex) ?? const Color(0xFF8B5CF6),
      secondary:
          colorFromHex(branding.secondaryColorHex) ?? const Color(0xFF111827),
    );
  }

  LinearGradient get primaryGradient => LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: <Color>[
          primary,
          Color.lerp(primary, secondary, 0.25) ?? primary,
        ],
      );

  Color get primarySoft => Color.lerp(primary, Colors.white, 0.88) ?? primary;
  Color get secondarySoft =>
      Color.lerp(secondary, Colors.white, 0.94) ?? secondary;
  Color get border => const Color(0xFFE5E7EB);
  Color get text => const Color(0xFF111827);
  Color get muted => const Color(0xFF6B7280);
  Color get canvas => const Color(0xFFFAFAFA);
  Color get success => const Color(0xFF16A34A);
}

Color? colorFromHex(String? value) {
  if (value == null || value.trim().isEmpty) return null;
  final hex = value.trim().replaceFirst('#', '');
  if (hex.length != 6 && hex.length != 8) return null;
  final normalized = hex.length == 6 ? 'FF$hex' : hex;
  final parsed = int.tryParse(normalized, radix: 16);
  if (parsed == null) return null;
  return Color(parsed);
}
