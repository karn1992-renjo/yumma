import 'package:flutter/services.dart';

class PrinterDiscoveryService {
  PrinterDiscoveryService._();

  static const MethodChannel _channel =
      MethodChannel('com.adgraph.yumma_vendor/printer_discovery');

  static Future<bool> requestBluetoothPermissions() async {
    final result =
        await _channel.invokeMethod<bool>('requestBluetoothPermissions');
    return result ?? false;
  }

  static Future<Map<String, dynamic>> discoverAllPrinters() async {
    final result =
        await _channel.invokeMapMethod<String, dynamic>('discoverAllPrinters');
    return result ?? <String, dynamic>{};
  }

  static Future<List<Map<String, dynamic>>> discoverBluetoothPrinters() async {
    final result =
        await _channel.invokeListMethod<dynamic>('discoverBluetoothPrinters');
    return _normalizeList(result);
  }

  static Future<List<Map<String, dynamic>>> discoverNetworkPrinters() async {
    final result =
        await _channel.invokeListMethod<dynamic>('discoverNetworkPrinters');
    return _normalizeList(result);
  }

  static List<Map<String, dynamic>> _normalizeList(List<dynamic>? items) {
    if (items == null) return const [];
    return items
        .whereType<Map>()
        .map((item) => item.map(
              (key, value) => MapEntry(key.toString(), value),
            ))
        .toList();
  }
}
