import 'package:flutter/services.dart';

class OrderAlertPermissionManager {
  OrderAlertPermissionManager._();

  static const MethodChannel _channel =
      MethodChannel('com.example.foodflow_driver/order_alerts');

  static Future<bool> checkOverlayPermission() async {
    try {
      return await _channel.invokeMethod<bool>('canDrawOverlays') ?? false;
    } catch (_) {
      return false;
    }
  }

  static Future<void> requestOverlayPermission() async {
    try {
      await _channel.invokeMethod<void>('requestOverlayPermission');
    } catch (_) {}
  }

  static Future<void> requestBatteryOptimizationExemption() async {
    try {
      await _channel.invokeMethod<void>('requestBatteryOptimizationExemption');
    } catch (_) {}
  }

  static Future<void> requestForegroundServicePermission() async {
    try {
      await _channel.invokeMethod<void>('openAppNotificationSettings');
    } catch (_) {}
  }
}
