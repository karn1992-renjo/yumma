import 'package:flutter/foundation.dart';

import 'order_alert_permission_manager.dart';

class OrderAlertStartupPermissionService {
  OrderAlertStartupPermissionService._();

  static bool _requestedThisRun = false;

  static Future<void> ensureForOrderAlerts({required bool enabled}) async {
    if (!enabled || _requestedThisRun) return;

    try {
      final canDrawOverlays =
          await OrderAlertPermissionManager.checkOverlayPermission();
      if (canDrawOverlays) return;

      _requestedThisRun = true;
      await OrderAlertPermissionManager.requestOverlayPermission();
    } catch (e) {
      debugPrint('Order alert overlay permission request skipped: $e');
    }
  }
}
