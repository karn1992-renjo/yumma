import 'package:flutter/foundation.dart';

import 'order_alert_permission_manager.dart';

class OrderAlertStartupPermissionService {
  OrderAlertStartupPermissionService._();

  static bool _requestedOverlayThisRun = false;
  static bool _requestedBatteryThisRun = false;

  static Future<void> ensureForOrderAlerts({required bool enabled}) async {
    if (!enabled) return;

    try {
      final batteryOptimizationDisabled =
          await OrderAlertPermissionManager.isBatteryOptimizationDisabled();
      if (!batteryOptimizationDisabled && !_requestedBatteryThisRun) {
        _requestedBatteryThisRun = true;
        await OrderAlertPermissionManager.requestBatteryOptimizationExemption();
        return;
      }
    } catch (e) {
      debugPrint('Battery optimization exemption request skipped: $e');
    }

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
