import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Holds the user's light / dark / system preference and persists it.
class ThemeProvider extends ChangeNotifier {
  ThemeProvider() {
    _load();
  }

  static const _prefsKey = 'restaurant_theme_mode';

  ThemeMode _themeMode = ThemeMode.system;
  ThemeMode get themeMode => _themeMode;

  bool get isLoaded => _loaded;
  bool _loaded = false;

  Future<void> _load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      _themeMode = _decode(prefs.getString(_prefsKey));
    } catch (_) {
      _themeMode = ThemeMode.system;
    }
    _loaded = true;
    notifyListeners();
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    if (_themeMode == mode) return;
    _themeMode = mode;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefsKey, _encode(mode));
    } catch (_) {
      // Best effort — the in-memory choice still applies for this session.
    }
  }

  /// Resolve to a concrete brightness given the current platform brightness.
  Brightness resolveBrightness(Brightness platformBrightness) {
    switch (_themeMode) {
      case ThemeMode.light:
        return Brightness.light;
      case ThemeMode.dark:
        return Brightness.dark;
      case ThemeMode.system:
        return platformBrightness;
    }
  }

  static ThemeMode _decode(String? raw) {
    switch (raw) {
      case 'light':
        return ThemeMode.light;
      case 'dark':
        return ThemeMode.dark;
      default:
        return ThemeMode.system;
    }
  }

  static String _encode(ThemeMode mode) {
    switch (mode) {
      case ThemeMode.light:
        return 'light';
      case ThemeMode.dark:
        return 'dark';
      case ThemeMode.system:
        return 'system';
    }
  }
}
