import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class ForegroundServiceManager {
  ForegroundServiceManager._();

  static const MethodChannel _channel = MethodChannel(
    'com.adgraph.yamma_delivery/order_alerts',
  );

  /// Best-effort mirror of whether the native foreground service is up. While
  /// this is true the OS keeps the process alive regardless of the battery
  /// optimization whitelist, so callers can treat a battery-opt exemption as a
  /// "nice to have" rather than a hard requirement.
  static bool isRunning = false;

  static Future<void> startForegroundService({
    String status = 'Logged in and ready for delivery orders',
    bool fullScreen = false,
    bool trackLocation = false,
  }) async {
    try {
      await _channel.invokeMethod<void>('startForegroundService', {
        'status': status,
        'fullScreen': fullScreen,
        'trackLocation': trackLocation,
      });
      isRunning = true;
    } catch (error, stackTrace) {
      debugPrint('Foreground service start failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  static Future<void> updateServiceNotification(
    String status, {
    bool fullScreen = false,
    bool trackLocation = false,
  }) async {
    try {
      await _channel.invokeMethod<void>('updateServiceNotification', {
        'status': status,
        'fullScreen': fullScreen,
        'trackLocation': trackLocation,
      });
    } catch (error, stackTrace) {
      debugPrint('Foreground service update failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  static Future<void> stopForegroundService() async {
    try {
      await _channel.invokeMethod<void>('stopForegroundService');
      isRunning = false;
    } catch (error, stackTrace) {
      debugPrint('Foreground service stop failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  static Future<void> bringAppToFront() async {
    try {
      await _channel.invokeMethod<void>('bringAppToFront');
    } catch (error, stackTrace) {
      debugPrint('Bring app to front failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  static Future<void> handleServiceRestart() async {
    await startForegroundService(
      status: 'Searching for new order.....',
    );
    isRunning = true;
  }
}
