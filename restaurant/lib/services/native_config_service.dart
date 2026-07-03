import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import '../config/app_config.dart';

class NativeConfigService {
  static const MethodChannel _channel =
      MethodChannel('com.adgraph.yumma_vendor/app_config');
  static String? _googleMapsApiKey;

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
}
