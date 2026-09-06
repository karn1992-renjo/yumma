// V2 design system — light + dark "glass" palettes.
//
// Static glass: surfaces are translucent gradient fills with a hairline border
// and a soft shadow. No live BackdropFilter here (that is reserved for the one
// pinned header). This keeps the whole UI cheap to composite and smooth on
// mid-range phones.

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

enum V2Mode { dark, light }

const String kV2ModePrefsKey = 'customer_home_v2_mode';

/// Global, persisted light/dark selection for the whole V2 experience.
final ValueNotifier<V2Mode> v2ModeNotifier = ValueNotifier<V2Mode>(V2Mode.dark);

Future<void> loadV2Mode() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(kV2ModePrefsKey);
    v2ModeNotifier.value = raw == 'light' ? V2Mode.light : V2Mode.dark;
  } catch (_) {
    v2ModeNotifier.value = V2Mode.dark;
  }
}

Future<void> setV2Mode(V2Mode mode) async {
  v2ModeNotifier.value = mode;
  try {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(kV2ModePrefsKey, mode == V2Mode.light ? 'light' : 'dark');
  } catch (_) {}
}

void toggleV2Mode() {
  setV2Mode(v2ModeNotifier.value == V2Mode.dark ? V2Mode.light : V2Mode.dark);
}

@immutable
class V2Palette {
  const V2Palette({
    required this.mode,
    required this.bgTop,
    required this.bgMid,
    required this.bgBottom,
    required this.aurora1,
    required this.aurora2,
    required this.aurora3,
    required this.aurora4,
    required this.glassTop,
    required this.glassBottom,
    required this.glassStrongTop,
    required this.glassStrongBottom,
    required this.glassBorder,
    required this.shadow,
    required this.ink,
    required this.inkSoft,
    required this.inkFaint,
    required this.accent,
    required this.accentSoft,
    required this.positive,
    required this.warning,
    required this.danger,
    required this.headerScrim,
  });

  final V2Mode mode;
  final Color bgTop;
  final Color bgMid;
  final Color bgBottom;
  final Color aurora1;
  final Color aurora2;
  final Color aurora3;
  final Color aurora4;
  final Color glassTop;
  final Color glassBottom;
  final Color glassStrongTop;
  final Color glassStrongBottom;
  final Color glassBorder;
  final Color shadow;
  final Color ink;
  final Color inkSoft;
  final Color inkFaint;
  final Color accent;
  final Color accentSoft;
  final Color positive;
  final Color warning;
  final Color danger;
  final Color headerScrim;

  bool get isDark => mode == V2Mode.dark;

  static const V2Palette dark = V2Palette(
    mode: V2Mode.dark,
    bgTop: Color(0xFF0B1020),
    bgMid: Color(0xFF121A30),
    bgBottom: Color(0xFF0A0E1C),
    aurora1: Color(0xFF2563EB),
    aurora2: Color(0xFF7C3AED),
    aurora3: Color(0xFF06B6D4),
    aurora4: Color(0xFFF97316),
    glassTop: Color(0x1FFFFFFF),
    glassBottom: Color(0x0FFFFFFF),
    glassStrongTop: Color(0x2EFFFFFF),
    glassStrongBottom: Color(0x14FFFFFF),
    glassBorder: Color(0x2BFFFFFF),
    shadow: Color(0x59000000),
    ink: Color(0xFFF7FAFF),
    inkSoft: Color(0xFFC4CDE6),
    inkFaint: Color(0xFF8B96B8),
    accent: Color(0xFF6C8BFF),
    accentSoft: Color(0x336C8BFF),
    positive: Color(0xFF34D399),
    warning: Color(0xFFFBBF24),
    danger: Color(0xFFF87171),
    headerScrim: Color(0x8C0B1020),
  );

  // Light: soft blue-lavender aurora canvas with solid white cards.
  static const V2Palette light = V2Palette(
    mode: V2Mode.light,
    bgTop: Color(0xFFEEF2FC),
    bgMid: Color(0xFFF6F8FE),
    bgBottom: Color(0xFFE9EEF9),
    aurora1: Color(0xFF93B4FF),
    aurora2: Color(0xFFC4B5FD),
    aurora3: Color(0xFF99E5F0),
    aurora4: Color(0xFFFFD8B0),
    glassTop: Color(0xF2FFFFFF),
    glassBottom: Color(0xE6FFFFFF),
    glassStrongTop: Color(0xFFFFFFFF),
    glassStrongBottom: Color(0xF7FFFFFF),
    glassBorder: Color(0x14101828),
    shadow: Color(0x1A1E293B),
    ink: Color(0xFF0F172A),
    inkSoft: Color(0xFF44506A),
    inkFaint: Color(0xFF7A879F),
    accent: Color(0xFF2F5DF0),
    accentSoft: Color(0x1A2F5DF0),
    positive: Color(0xFF0F9D58),
    warning: Color(0xFFB4690E),
    danger: Color(0xFFDC2626),
    headerScrim: Color(0xE6F2F5FC),
  );

  static V2Palette of(V2Mode mode) => mode == V2Mode.light ? light : dark;
}

/// Inherited access to the active palette. Placed by [V2Scaffold]; read with
/// `V2Theme.of(context)`.
class V2Theme extends InheritedWidget {
  const V2Theme({super.key, required this.palette, required super.child});

  final V2Palette palette;

  /// Returns the inherited palette when available, otherwise falls back to the
  /// current global mode. The fallback keeps `V2Theme.of(context)` safe to call
  /// from a screen's own `build` (whose context sits above the [V2Scaffold] it
  /// returns) while widgets nested under [V2Scaffold] still get the inherited,
  /// dependency-tracked instance.
  static V2Palette of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<V2Theme>();
    return scope?.palette ?? V2Palette.of(v2ModeNotifier.value);
  }

  @override
  bool updateShouldNotify(V2Theme oldWidget) => oldWidget.palette.mode != palette.mode;
}
