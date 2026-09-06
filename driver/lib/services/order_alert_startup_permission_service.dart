import 'package:flutter/foundation.dart';

import 'order_alert_permission_manager.dart';

class OrderAlertStartupPermissionService {
  OrderAlertStartupPermissionService._();

  static bool _requestedOverlayThisRun = false;

  /// Runs once on driver app startup.
  ///
  /// It deliberately no longer fires the battery-optimization ("always run in
  /// the background") system dialog. That request ran on every cold start and
  /// kept reappearing even after the driver had allowed it -- on many OEM ROMs
  /// `isIgnoringBatteryOptimizations` never flips to true regardless of what the
  /// user toggles, so the check stayed false forever. The exemption nudge now
  /// lives only behind the explicit "go online" action in the dashboard, shown
  /// at most once unless the driver asks for it.
  static Future<void> ensureForOrderAlerts({required bool enabled}) async {
    if (!enabled) return;
    if (_requestedOverlayThisRun) return;

    try {
      final canDrawOverlays =
          await OrderAlertPermissionManager.checkOverlayPermission();
      if (canDrawOverlays) return;

      _requestedOverlayThisRun = true;
      await OrderAlertPermissionManager.requestOverlayPermission();
    } catch (e) {
      debugPrint('Order alert overlay permission request skipped: $e');
    }
  }
}
