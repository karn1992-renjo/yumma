import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Persisted flag that decides which customer home experience is mounted.
///
/// `false` (default) keeps the battle-tested production home
/// ([CustomerHomeScreenProduction]); `true` opts the user into the redesigned
/// glassmorphism home ([CustomerHomeScreenV2]). Both are driven by the same
/// `/home/sections` layout managed from admin.
const String kHomeV2PrefsKey = 'customer_home_v2_enabled';

/// Broadcasts the current choice so [HomeScreen] can swap experiences live
/// (e.g. right after the user flips the toggle in Profile) without a restart.
final ValueNotifier<bool> homeV2Enabled = ValueNotifier<bool>(false);

/// Loads the saved preference into [homeV2Enabled]. Safe to call multiple times;
/// failures fall back to the production home.
Future<void> loadHomeExperiencePref() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    homeV2Enabled.value = prefs.getBool(kHomeV2PrefsKey) ?? false;
  } catch (_) {
    homeV2Enabled.value = false;
  }
}

/// Persists [enabled] and updates [homeV2Enabled] immediately.
Future<void> setHomeV2Enabled(bool enabled) async {
  homeV2Enabled.value = enabled;
  try {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(kHomeV2PrefsKey, enabled);
  } catch (_) {
    // Preference is best-effort; the in-memory notifier still reflects intent.
  }
}
