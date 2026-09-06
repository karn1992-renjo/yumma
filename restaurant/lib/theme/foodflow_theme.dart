import 'package:flutter/material.dart';

/// Thin, stable facade kept for the many call sites that predate the aurora
/// redesign. New code should prefer the [foodflow] tokens directly, but these
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

  static BoxDecoration surface({double radius = 12, Color? color}) =>
      foodflow.surface(radius: radius, color: color);

  static BoxDecoration softSurface({double radius = 10}) =>
      foodflow.softSurface(radius: radius);

  static BoxDecoration orangeBand({double radius = 14}) =>
      foodflow.orangeBand(radius: radius);

  static BoxDecoration elevatedCard({
    double radius = 24,
    Color? color,
    Color? borderColor,
  }) =>
      foodflow.elevatedCard(
        radius: radius,
        color: color,
        borderColor: borderColor,
      );

  static ButtonStyle zomatoPrimaryButton({
    Color? color,
    EdgeInsetsGeometry padding =
        const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
    double radius = 18,
  }) =>
      foodflow.zomatoPrimaryButton(
        color: color,
        padding: padding,
        radius: radius,
      );

  static ButtonStyle zomatoOutlineButton({
    Color? color,
    EdgeInsetsGeometry padding =
        const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
    double radius = 18,
  }) =>
      foodflow.zomatoOutlineButton(
        color: color,
        padding: padding,
        radius: radius,
      );

  static ButtonStyle softIconButton({
    Color? backgroundColor,
    Color? foregroundColor,
  }) =>
      foodflow.softIconButton(
        backgroundColor: backgroundColor,
        foregroundColor: foregroundColor,
      );

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
  static Color crimson = const Color(0xFFFF6B00);
  static Color ink = const Color(0xFF111827);
  static Color inkSoft = const Color(0xFF374151);
  static Color muted = const Color(0xFF6B7280);
  static Color faint = const Color(0xFF9CA3AF);
  static Color line = const Color(0xFFE5E7EB);
  static Color canvas = const Color(0xFFFAFAFA);
  static Color warmCanvas = const Color(0xFFFFF3E8);
  static Color success = const Color(0xFF22C55E);
  static Color danger = const Color(0xFFE53935);

  // Aurora surface tokens.
  static Color surfaceColor = Colors.white;
  static Color elevatedSurface = Colors.white;
  static Color glassSurface = Colors.white.withOpacity(0.72);
  static Color glassBorder = Colors.white.withOpacity(0.75);
  static Color scrim = const Color(0x14000000);

  // Aurora backdrop blobs (behind the frosted glass).
  static Color auroraA = const Color(0xFF7FA8FF);
  static Color auroraB = const Color(0xFFFFB98A);
  static Color auroraC = const Color(0xFFBFA0FF);

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
      crimson = const Color(0xFFFF8A3D);
      ink = const Color(0xFFF1F5FB);
      inkSoft = const Color(0xFFCBD5E5);
      muted = const Color(0xFF93A1B5);
      faint = const Color(0xFF6B7688);
      line = const Color(0xFF283042);
      canvas = const Color(0xFF0C1017);
      warmCanvas = const Color(0xFF16110B);
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
      crimson = const Color(0xFFFF6B00);
      ink = const Color(0xFF111827);
      inkSoft = const Color(0xFF374151);
      muted = const Color(0xFF6B7280);
      faint = const Color(0xFF9CA3AF);
      line = const Color(0xFFE5E7EB);
      canvas = const Color(0xFFF4F6FB);
      warmCanvas = const Color(0xFFFFF3E8);
      success = const Color(0xFF22C55E);
      danger = const Color(0xFFE53935);

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

  static BoxDecoration surface({double radius = 12, Color? color}) {
    return BoxDecoration(
      color: color ?? surfaceColor,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: line),
      boxShadow: [
        BoxShadow(
          color: isDark ? Colors.black.withOpacity(0.35) : scrim,
          blurRadius: 10,
          offset: const Offset(0, 4),
        ),
      ],
    );
  }

  static BoxDecoration softSurface({double radius = 10}) {
    return BoxDecoration(
      color: surfaceColor,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: line),
    );
  }

  static BoxDecoration orangeBand({double radius = 14}) {
    return BoxDecoration(
      gradient: brandGradient,
      borderRadius: BorderRadius.circular(radius),
      boxShadow: [
        BoxShadow(
          color: orange.withOpacity(0.24),
          blurRadius: 10,
          offset: const Offset(0, 4),
        ),
      ],
    );
  }

  static BoxDecoration elevatedCard({
    double radius = 24,
    Color? color,
    Color? borderColor,
  }) {
    return BoxDecoration(
      color: color ?? surfaceColor,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: borderColor ?? line),
      boxShadow: [
        BoxShadow(
          color: isDark
              ? Colors.black.withOpacity(0.4)
              : crimson.withOpacity(0.08),
          blurRadius: 24,
          offset: const Offset(0, 12),
        ),
      ],
    );
  }

  static ButtonStyle zomatoPrimaryButton({
    Color? color,
    EdgeInsetsGeometry padding =
        const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
    double radius = 18,
  }) {
    final base = color ?? crimson;
    return ElevatedButton.styleFrom(
      backgroundColor: base,
      foregroundColor: Colors.white,
      elevation: 0,
      padding: padding,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(radius),
      ),
      textStyle: const TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w800,
      ),
    ).copyWith(
      shadowColor: MaterialStatePropertyAll(base.withOpacity(0.25)),
      overlayColor: const MaterialStatePropertyAll(Color(0x14FFFFFF)),
      elevation: const MaterialStatePropertyAll(0),
    );
  }

  static ButtonStyle zomatoOutlineButton({
    Color? color,
    EdgeInsetsGeometry padding =
        const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
    double radius = 18,
  }) {
    final base = color ?? crimson;
    return OutlinedButton.styleFrom(
      foregroundColor: base,
      side: BorderSide(color: base.withOpacity(0.25)),
      backgroundColor: base.withOpacity(0.04),
      padding: padding,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(radius),
      ),
      textStyle: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w800,
      ),
    );
  }

  static ButtonStyle softIconButton({
    Color? backgroundColor,
    Color? foregroundColor,
  }) {
    return IconButton.styleFrom(
      backgroundColor: backgroundColor ?? surfaceColor,
      foregroundColor: foregroundColor ?? ink,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
      ),
      side: BorderSide(color: line),
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
            style: TextStyle(
              color: Colors.white,
              fontSize: compact ? 11 : 12,
              fontWeight: FontWeight.w900,
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
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxHeight < 180;
        return SingleChildScrollView(
          physics: const ClampingScrollPhysics(),
          padding: const EdgeInsets.all(20),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (!compact) ...[
                  Container(
                    width: 64,
                    height: 64,
                    decoration: BoxDecoration(
                      color: orange.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Icon(icon, size: 30, color: orange),
                  ),
                  const SizedBox(height: 14),
                ],
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (subtitle != null && !compact) ...[
                  const SizedBox(height: 8),
                  Text(
                    subtitle,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  static Widget sectionTitle(String title, {String? trailing}) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 6),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: ink,
                fontSize: 15,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          if (trailing != null)
            Text(
              trailing,
              style: TextStyle(
                color: muted,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
        ],
      ),
    );
  }
}
