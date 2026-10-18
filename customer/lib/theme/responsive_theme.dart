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
  // Poppins (the app's font, swapped in from Plus Jakarta Sans) renders
  // visibly larger/taller at the same declared fontSize -- without this,
  // every fixed-height card/row sized for the old font overflows by a few
  // pixels (dish cards, "Recommended For You" grid, the VEG MODE label,
  // etc.).
  //
  // The app's text size is intentionally fixed at this value regardless of
  // the phone's own system font-size setting -- every layout in this app is
  // sized for one specific scale, so letting a user's OS accessibility
  // setting multiply on top (as a normal Flutter app would allow) is exactly
  // what was reintroducing the overflow this constant fixes. This trades
  // away that one accessibility affordance for consistent, non-overflowing
  // layouts everywhere.
  static const double _fontBaselineScale = 0.94;

  static Widget withClampedTextScale({
    required BuildContext context,
    required Widget? child,
  }) {
    final media = MediaQuery.of(context);
    return MediaQuery(
      data: media.copyWith(
        textScaler: const TextScaler.linear(_fontBaselineScale),
      ),
      child: child ?? const SizedBox.shrink(),
    );
  }
}
