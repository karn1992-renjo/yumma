import 'package:flutter/material.dart';

class AppSpacing {
  static const double xxs = 4;
  static const double xs = 8;
  static const double sm = 12;
  static const double md = 16;
  static const double lg = 24;
  static const double xl = 32;

  static const EdgeInsets screen = EdgeInsets.all(md);
  static const EdgeInsets card = EdgeInsets.all(sm);
  static const EdgeInsets input =
      EdgeInsets.symmetric(horizontal: md, vertical: sm);
}

class AppTypography {
  static const double display = 28;
  static const double headline = 24;
  static const double titleLarge = 18;
  static const double titleMedium = 16;
  static const double bodyLarge = 15;
  static const double bodyMedium = 14;
  static const double bodySmall = 12;
  static const double caption = 11;

  static TextTheme material3({
    required TextTheme base,
    required Color textColor,
    required Color mutedColor,
  }) {
    return base.copyWith(
      displayLarge: TextStyle(
        color: textColor,
        fontSize: display,
        fontWeight: FontWeight.w800,
        height: 1.05,
      ),
      displayMedium: TextStyle(
        color: textColor,
        fontSize: 26,
        fontWeight: FontWeight.w800,
        height: 1.06,
      ),
      headlineLarge: TextStyle(
        color: textColor,
        fontSize: headline,
        fontWeight: FontWeight.w800,
        height: 1.08,
      ),
      headlineMedium: TextStyle(
        color: textColor,
        fontSize: 22,
        fontWeight: FontWeight.w800,
        height: 1.12,
      ),
      titleLarge: TextStyle(
        color: textColor,
        fontSize: titleLarge,
        fontWeight: FontWeight.w800,
        height: 1.18,
      ),
      titleMedium: TextStyle(
        color: textColor,
        fontSize: titleMedium,
        fontWeight: FontWeight.w700,
        height: 1.2,
      ),
      titleSmall: TextStyle(
        color: textColor,
        fontSize: bodyMedium,
        fontWeight: FontWeight.w700,
        height: 1.2,
      ),
      bodyLarge: TextStyle(
        color: mutedColor,
        fontSize: bodyLarge,
        fontWeight: FontWeight.w600,
        height: 1.32,
      ),
      bodyMedium: TextStyle(
        color: mutedColor,
        fontSize: bodyMedium,
        fontWeight: FontWeight.w500,
        height: 1.3,
      ),
      bodySmall: TextStyle(
        color: mutedColor,
        fontSize: bodySmall,
        fontWeight: FontWeight.w500,
        height: 1.24,
      ),
      labelLarge: TextStyle(
        color: textColor,
        fontSize: bodyMedium,
        fontWeight: FontWeight.w700,
        height: 1.14,
      ),
      labelMedium: TextStyle(
        color: mutedColor,
        fontSize: bodySmall,
        fontWeight: FontWeight.w700,
        height: 1.14,
      ),
      labelSmall: TextStyle(
        color: mutedColor,
        fontSize: caption,
        fontWeight: FontWeight.w500,
        height: 1.12,
      ),
    );
  }
}

class ResponsiveBreakpoints {
  static bool isSmallPhone(BuildContext context) =>
      MediaQuery.sizeOf(context).width < 375;

  static bool isTablet(BuildContext context) =>
      MediaQuery.sizeOf(context).shortestSide >= 600;

  static double screenPadding(BuildContext context) =>
      isTablet(context) ? AppSpacing.lg : AppSpacing.md;

  static int gridColumns(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    if (width >= 900) return 4;
    if (width >= 600) return 3;
    return 2;
  }
}

class ResponsiveMedia {
  static Widget withClampedTextScale({
    required BuildContext context,
    required Widget? child,
  }) {
    final media = MediaQuery.of(context);
    final scale = media.textScaler.scale(1).clamp(0.85, 1.3).toDouble();
    return MediaQuery(
      data: media.copyWith(textScaler: TextScaler.linear(scale)),
      child: child ?? const SizedBox.shrink(),
    );
  }
}
