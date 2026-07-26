import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import '../config/app_config.dart';

class NativeConfigService {
  static const MethodChannel _channel =
      MethodChannel('com.adgraph.yamma_delivery/app_config');
  static String? _googleMapsApiKey;
  static String? _appName;

  static Future<String> getGoogleMapsApiKey() async {
    if (AppConfig.googleMapsApiKey.isNotEmpty) {
      return AppConfig.googleMapsApiKey;
    }

    if (_googleMapsApiKey != null) {
      return _googleMapsApiKey!;
    }

    if (defaultTargetPlatform != TargetPlatform.android) {
      _googleMapsApiKey = '';
      return _googleMapsApiKey!;
    }

    final key = await _channel.invokeMethod<String>('getGoogleMapsApiKey');
    _googleMapsApiKey = key ?? '';
    return _googleMapsApiKey!;
  }

  static Future<String> getAppName() async {
    if (_appName != null) {
      return _appName!;
    }

    if (defaultTargetPlatform != TargetPlatform.android) {
      _appName = AppConfig.appName;
      return _appName!;
    }

    final appName = await _channel.invokeMethod<String>('getAppName');
    final resolved = appName?.trim().isNotEmpty == true
        ? appName!.trim()
        : AppConfig.appName;
    _appName = resolved;
    return _appName!;
  }
}
