import 'package:flutter/material.dart';

/// Thin, stable facade kept for the many call sites that predate the aurora
/// redesign. New code should prefer [AuroraTokens] via `context`, but these
/// forwarders stay theme-aware because [foodflow] swaps its palette on
/// [foodflow.applyBrightness].
class FoodFlowTheme {
  static const Color fallbackOrange = foodflow.fallbackOrange;
  static const Color fallbackOrangeDark = foodflow.fallbackOrangeDark;
  static Color get orange => foodflow.orange;
  static Color get primaryColor => foodflow.primaryColor;
  static Color get orangeDark => foodflow.orangeDark;
  static Color get crimson => foodflow.crimson;
  static Color get ink => foodflow.ink;
  static Color get inkSoft => foodflow.inkSoft;
  static Color get muted => foodflow.muted;
  static Color get faint => foodflow.faint;
  static Color get line => foodflow.line;
  static Color get canvas => foodflow.canvas;
  static Color get warmCanvas => foodflow.warmCanvas;
  static Color get success => foodflow.success;
  static Color get danger => foodflow.danger;

  static void applyBrandColors({Color? primary, Color? secondary}) {
    foodflow.applyBrandColors(primary: primary, secondary: secondary);
  }

  static LinearGradient get brandGradient => foodflow.brandGradient;

  static BoxDecoration surface({double radius = 18, Color? color}) =>
      foodflow.surface(radius: radius, color: color);

  static BoxDecoration softSurface({double radius = 14}) =>
      foodflow.softSurface(radius: radius);

  static BoxDecoration orangeBand({double radius = 18}) =>
      foodflow.orangeBand(radius: radius);

  static Widget vegDot(bool isVeg, {double size = 16}) =>
      foodflow.vegDot(isVeg, size: size);

  static Widget ratingBadge(double rating, {bool compact = false}) =>
      foodflow.ratingBadge(rating, compact: compact);

  static Widget emptyState({
    required IconData icon,
    required String title,
    String? subtitle,
  }) =>
      foodflow.emptyState(icon: icon, title: title, subtitle: subtitle);

  static Widget sectionTitle(String title, {String? trailing}) =>
      foodflow.sectionTitle(title, trailing: trailing);
}

/// Global design palette. Colours are mutable so the app can retint on brand
/// load ([applyBrandColors]) and swap the whole neutral ramp when the effective
/// brightness changes ([applyBrightness]). Screens read these statics directly,
/// so flipping them + rebuilding the tree is enough to theme legacy screens.
// ignore: camel_case_types
class foodflow {
  static const Color fallbackOrange = Color(0xFF2563EB);
  static const Color fallbackOrangeDark = Color(0xFF1D4ED8);

  static Color orange = fallbackOrange;
  static Color primaryColor = fallbackOrange;
  static Color orangeDark = fallbackOrangeDark;

  static Brightness brightness = Brightness.light;

  // Neutral ramp + status colours — reassigned by [applyBrightness].
  static Color crimson = const Color(0xFFE8335A);
  static Color ink = const Color(0xFF1E293B);
  static Color inkSoft = const Color(0xFF334155);
  static Color muted = const Color(0xFF7A8798);
  static Color faint = const Color(0xFFA7B0BE);
  static Color line = const Color(0xFFE5EAF1);
  static Color canvas = const Color(0xFFF7F8FC);
  static Color warmCanvas = const Color(0xFFF7F8FC);
  static Color success = const Color(0xFF22C97B);
  static Color danger = const Color(0xFFE8335A);

  // Aurora surface tokens.
  static Color surfaceColor = Colors.white;
  static Color elevatedSurface = Colors.white;
  static Color glassSurface = Colors.white.withOpacity(0.72);
  static Color glassBorder = Colors.white.withOpacity(0.55);
  static Color scrim = const Color(0x14000000);

  // Aurora backdrop blobs (behind the frosted glass).
  static Color auroraA = const Color(0xFFB9D4FF);
  static Color auroraB = const Color(0xFFFFD9C2);
  static Color auroraC = const Color(0xFFD8C8FF);

  static bool get isDark => brightness == Brightness.dark;

  static void applyBrandColors({Color? primary, Color? secondary}) {
    orange = primary ?? fallbackOrange;
    primaryColor = orange;
    orangeDark = secondary ?? fallbackOrangeDark;
  }

  /// Swap the neutral ramp + surface tokens for the given brightness. Call this
  /// from the top of the widget tree before building [MaterialApp].
  static void applyBrightness(Brightness value) {
    brightness = value;
    if (value == Brightness.dark) {
      crimson = const Color(0xFFFF5C7A);
      ink = const Color(0xFFF1F5FB);
      inkSoft = const Color(0xFFCBD5E5);
      muted = const Color(0xFF93A1B5);
      faint = const Color(0xFF6B7688);
      line = const Color(0xFF283042);
      canvas = const Color(0xFF0C1017);
      warmCanvas = const Color(0xFF0C1017);
      success = const Color(0xFF34D98A);
      danger = const Color(0xFFFF5C7A);

      surfaceColor = const Color(0xFF141A24);
      elevatedSurface = const Color(0xFF1B2230);
      glassSurface = const Color(0xFF1A2230).withOpacity(0.86);
      glassBorder = Colors.white.withOpacity(0.10);
      scrim = const Color(0x33000000);

      auroraA = const Color(0xFF1E3A8A);
      auroraB = const Color(0xFF7C2D12);
      auroraC = const Color(0xFF4C1D95);
    } else {
      crimson = const Color(0xFFE8335A);
      ink = const Color(0xFF1E293B);
      inkSoft = const Color(0xFF334155);
      muted = const Color(0xFF7A8798);
      faint = const Color(0xFFA7B0BE);
      line = const Color(0xFFE3E8F0);
      canvas = const Color(0xFFEEF1F8);
      warmCanvas = const Color(0xFFEEF1F8);
      success = const Color(0xFF22C97B);
      danger = const Color(0xFFE8335A);

      surfaceColor = Colors.white;
      elevatedSurface = Colors.white;
      glassSurface = Colors.white.withOpacity(0.72);
      glassBorder = Colors.white.withOpacity(0.75);
      scrim = const Color(0x14000000);

      auroraA = const Color(0xFF7FA8FF);
      auroraB = const Color(0xFFFFB98A);
      auroraC = const Color(0xFFBFA0FF);
    }
  }

  static LinearGradient get brandGradient => LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [orange, orangeDark],
      );

  static BoxDecoration surface({double radius = 18, Color? color}) {
    return BoxDecoration(
      color: color ?? surfaceColor,
      borderRadius: BorderRadius.circular(radius),
      boxShadow: [
        BoxShadow(
          color: isDark ? Colors.black.withOpacity(0.35) : scrim,
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ],
    );
  }

  static BoxDecoration softSurface({double radius = 14}) {
    return BoxDecoration(
      color: surfaceColor,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: line.withOpacity(0.72)),
    );
  }

  static BoxDecoration orangeBand({double radius = 18}) {
    return BoxDecoration(
      gradient: brandGradient,
      borderRadius: BorderRadius.circular(radius),
      boxShadow: [
        BoxShadow(
          color: orange.withOpacity(0.24),
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ],
    );
  }

  static Widget vegDot(bool isVeg, {double size = 16}) {
    final color = isVeg ? success : danger;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: surfaceColor,
        border: Border.all(color: color, width: 1.4),
        borderRadius: BorderRadius.circular(3),
      ),
      alignment: Alignment.center,
      child: Container(
        width: size * 0.48,
        height: size * 0.48,
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(2),
        ),
      ),
    );
  }

  static Widget ratingBadge(double rating, {bool compact = false}) {
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 6 : 8,
        vertical: compact ? 3 : 4,
      ),
      decoration: BoxDecoration(
        color: success,
        borderRadius: BorderRadius.circular(5),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            rating.toStringAsFixed(1),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(width: 3),
          Icon(Icons.star, size: compact ? 10 : 12, color: Colors.white),
        ],
      ),
    );
  }

  static Widget emptyState({
    required IconData icon,
    required String title,
    String? subtitle,
  }) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 82,
              height: 82,
              decoration: BoxDecoration(
                color: orange.withOpacity(0.10),
                borderRadius: BorderRadius.circular(18),
              ),
              child: Icon(icon, size: 40, color: orange),
            ),
            const SizedBox(height: 18),
            Text(
              title,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: ink,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (subtitle != null) ...[
              const SizedBox(height: 8),
              Text(
                subtitle,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: muted,
                  fontSize: 13,
                  fontWeight: FontWeight.w400,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  static Widget sectionTitle(String title, {String? trailing}) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 22, 16, 10),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: ink,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          if (trailing != null)
            Text(
              trailing,
              style: TextStyle(
                color: muted,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
        ],
      ),
    );
  }
}
