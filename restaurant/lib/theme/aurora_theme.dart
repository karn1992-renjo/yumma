import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'foodflow_theme.dart';
import 'responsive_theme.dart';

/// Builds the light + dark [ThemeData] for the driver app.
///
/// The neutral palette itself lives on [foodflow] (so the many legacy screens
/// that read `foodflow.ink` etc. stay theme-aware); call
/// [foodflow.applyBrightness] for the target brightness *before* calling
/// [AuroraTheme.build] so the tokens below resolve correctly.
class AuroraTheme {
  static ThemeData build({
    required Brightness brightness,
    required Color primary,
    required Color secondary,
  }) {
    final isDark = brightness == Brightness.dark;

    final colorScheme = ColorScheme.fromSeed(
      seedColor: primary,
      brightness: brightness,
      primary: primary,
      secondary: secondary,
      surface: foodflow.surfaceColor,
      error: foodflow.danger,
    );

    final textTheme = AppTypography.material3(
      base: GoogleFonts.plusJakartaSansTextTheme(
        isDark ? ThemeData.dark().textTheme : ThemeData.light().textTheme,
      ),
      textColor: foodflow.ink,
      mutedColor: foodflow.inkSoft,
    );

    final inputFill = isDark
        ? foodflow.elevatedSurface
        : const Color(0xFFF7F8FC);

    return ThemeData(
      brightness: brightness,
      colorScheme: colorScheme,
      primaryColor: primary,
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: _AuroraPageTransitionsBuilder(),
          TargetPlatform.iOS: _AuroraPageTransitionsBuilder(),
        },
      ),
      scaffoldBackgroundColor: foodflow.canvas,
      canvasColor: foodflow.canvas,
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
      fontFamily: GoogleFonts.plusJakartaSans().fontFamily,
      useMaterial3: true,
      textTheme: textTheme,
      appBarTheme: AppBarTheme(
        elevation: 0,
        centerTitle: false,
        backgroundColor: foodflow.canvas,
        surfaceTintColor: Colors.transparent,
        foregroundColor: foodflow.ink,
        iconTheme: IconThemeData(color: foodflow.ink),
        titleTextStyle: TextStyle(
          color: foodflow.ink,
          fontSize: 18,
          fontWeight: FontWeight.w800,
        ),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: primary,
        unselectedLabelColor: foodflow.muted,
        indicatorColor: primary,
        labelStyle: const TextStyle(fontWeight: FontWeight.w800),
        unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w400),
      ),
      dividerTheme: DividerThemeData(
        color: foodflow.line,
        thickness: 1,
        space: 1,
      ),
      listTileTheme: ListTileThemeData(
        iconColor: primary,
        textColor: foodflow.ink,
        titleTextStyle: TextStyle(
          color: foodflow.ink,
          fontSize: 15,
          fontWeight: FontWeight.w800,
        ),
        subtitleTextStyle: TextStyle(
          color: foodflow.muted,
          fontSize: 12,
          fontWeight: FontWeight.w400,
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: foodflow.surfaceColor,
        selectedColor: primary.withOpacity(0.12),
        checkmarkColor: primary,
        labelStyle: TextStyle(
          color: foodflow.ink,
          fontWeight: FontWeight.w800,
          fontSize: 12,
        ),
        side: BorderSide(color: foodflow.line),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        backgroundColor: foodflow.surfaceColor,
        selectedItemColor: primary,
        unselectedItemColor: foodflow.muted,
        selectedLabelStyle: const TextStyle(fontWeight: FontWeight.w800),
        unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w400),
        type: BottomNavigationBarType.fixed,
        showUnselectedLabels: true,
        elevation: 0,
      ),
      inputDecorationTheme: InputDecorationTheme(
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: foodflow.line),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: primary, width: 1.4),
        ),
        filled: true,
        fillColor: inputFill,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        prefixIconColor: primary,
        suffixIconColor: foodflow.muted,
        labelStyle: TextStyle(
          color: foodflow.muted,
          fontWeight: FontWeight.w800,
          fontSize: 15,
        ),
        hintStyle: TextStyle(
          color: foodflow.faint,
          fontWeight: FontWeight.w400,
          fontSize: 15,
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          disabledBackgroundColor:
              isDark ? const Color(0xFF2A3240) : const Color(0xFFD8DEE8),
          minimumSize: const Size.fromHeight(56),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
          elevation: 0,
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: foodflow.ink,
          side: BorderSide(color: primary),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        margin: EdgeInsets.zero,
        color: foodflow.surfaceColor,
        surfaceTintColor: Colors.transparent,
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: isDark ? foodflow.elevatedSurface : primary,
        contentTextStyle: TextStyle(
          color: isDark ? foodflow.ink : Colors.white,
        ),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: foodflow.surfaceColor,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: foodflow.surfaceColor,
        surfaceTintColor: Colors.transparent,
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: primary,
        foregroundColor: Colors.white,
        elevation: 5,
        extendedTextStyle: const TextStyle(fontWeight: FontWeight.w800),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: primary,
          textStyle: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
      popupMenuTheme: PopupMenuThemeData(
        color: foodflow.elevatedSurface,
        surfaceTintColor: Colors.transparent,
        textStyle: TextStyle(color: foodflow.ink, fontWeight: FontWeight.w600),
      ),
    );
  }

  static const Duration pageTransition = Duration(milliseconds: 320);

  /// The aurora wash painted behind the whole screen: a soft diagonal ground
  /// tint plus three wide, low-opacity colour clouds spread across the viewport.
  /// Deliberately gentle so text and glass cards stay readable on top.
  static List<Widget> auroraBlobs() {
    final dark = foodflow.isDark;
    final o = dark ? 0.28 : 0.55;
    return [
      Positioned.fill(
        child: IgnorePointer(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  foodflow.auroraA.withOpacity(dark ? 0.16 : 0.22),
                  foodflow.canvas.withOpacity(0),
                  foodflow.auroraC.withOpacity(dark ? 0.16 : 0.20),
                ],
                stops: const [0.0, 0.55, 1.0],
              ),
            ),
          ),
        ),
      ),
      _blob(size: 500, color: foodflow.auroraA, opacity: o, top: -200, left: -170),
      _blob(
          size: 440,
          color: foodflow.auroraB,
          opacity: o * 0.9,
          top: 90,
          right: -210),
      _blob(
          size: 560,
          color: foodflow.auroraC,
          opacity: o * 0.85,
          bottom: -260,
          left: -140),
    ];
  }

  static Widget _blob({
    required double size,
    required Color color,
    required double opacity,
    double? top,
    double? left,
    double? right,
    double? bottom,
  }) {
    return Positioned(
      top: top,
      left: left,
      right: right,
      bottom: bottom,
      child: IgnorePointer(
        child: Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: RadialGradient(
              colors: [color.withOpacity(opacity), color.withOpacity(0)],
            ),
          ),
        ),
      ),
    );
  }
}

/// Fade + gentle upward slide page transition used app-wide.
class _AuroraPageTransitionsBuilder extends PageTransitionsBuilder {
  const _AuroraPageTransitionsBuilder();

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    final curved = CurvedAnimation(
      parent: animation,
      curve: Curves.easeOutCubic,
      reverseCurve: Curves.easeInCubic,
    );
    return FadeTransition(
      opacity: curved,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0, 0.035),
          end: Offset.zero,
        ).animate(curved),
        child: child,
      ),
    );
  }
}
