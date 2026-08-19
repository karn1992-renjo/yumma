import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class ForegroundServiceManager {
  ForegroundServiceManager._();

  static const MethodChannel _channel =
      MethodChannel('com.renjo.restro.android/order_alerts');
  static bool _nativeChannelUnavailable = false;

  static Future<void> startForegroundService({
    String status = 'Online and listening for orders',
    bool fullScreen = false,
  }) async {
    if (_nativeChannelUnavailable) return;
    try {
      await _channel.invokeMethod<void>(
        'startForegroundService',
        {
          'status': status,
          'fullScreen': fullScreen,
        },
      );
    } on MissingPluginException catch (error) {
      _nativeChannelUnavailable = true;
      debugPrint('Foreground service native channel unavailable: $error');
    } catch (error, stackTrace) {
      debugPrint('Foreground service start failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  static Future<void> updateServiceNotification(
    String status, {
    bool fullScreen = false,
  }) async {
    if (_nativeChannelUnavailable) return;
    try {
      await _channel.invokeMethod<void>(
        'updateServiceNotification',
        {
          'status': status,
          'fullScreen': fullScreen,
        },
      );
    } on MissingPluginException catch (error) {
      _nativeChannelUnavailable = true;
      debugPrint('Foreground service native channel unavailable: $error');
    } catch (error, stackTrace) {
      debugPrint('Foreground service update failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  static Future<void> stopForegroundService() async {
    if (_nativeChannelUnavailable) return;
    try {
      await _channel.invokeMethod<void>('stopForegroundService');
    } on MissingPluginException catch (error) {
      _nativeChannelUnavailable = true;
      debugPrint('Foreground service native channel unavailable: $error');
    } catch (error, stackTrace) {
      debugPrint('Foreground service stop failed: $error');
      debugPrintStack(stackTrace: stackTrace);
    }
  }

  static Future<void> bringAppToFront() async {
    if (_nativeChannelUnavailable) return;
    try {
      await _channel.invokeMethod<void>('bringAppToFront');
    } on MissingPluginException catch (error) {
      _nativeChannelUnavailable = true;
      debugPrint('Foreground service native channel unavailable: $error');
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
