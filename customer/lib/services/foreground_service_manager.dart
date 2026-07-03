import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class ForegroundServiceManager {
  ForegroundServiceManager._();

  static const MethodChannel _channel =
      MethodChannel('com.adgraph.yumma/order_alerts');

  static Future<void> startForegroundService({
    String status = 'Online and listening for orders',
    bool fullScreen = false,
  }) async {
    try {
      await _channel.invokeMethod<void>(
        'startForegroundService',
        {
          'status': status,
          'fullScreen': fullScreen,
        },
      );
    } catch (error, stackTrace) {
      debugPrint('Foreground service start failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  static Future<void> updateServiceNotification(
    String status, {
    bool fullScreen = false,
  }) async {
    try {
      await _channel.invokeMethod<void>(
        'updateServiceNotification',
        {
          'status': status,
          'fullScreen': fullScreen,
        },
      );
    } catch (error, stackTrace) {
      debugPrint('Foreground service update failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  static Future<void> stopForegroundService() async {
    try {
      await _channel.invokeMethod<void>('stopForegroundService');
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
        status: 'Reconnected and listening for orders');
  }
}
