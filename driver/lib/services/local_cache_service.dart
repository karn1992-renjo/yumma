import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';

class LocalCacheService {
  static const _boxName = 'api_cache';

  static Future<void> initialize() async {
    await Hive.initFlutter();
    if (!Hive.isBoxOpen(_boxName)) await Hive.openBox<String>(_boxName);
  }

  static Future<void> put(String key, dynamic value) async {
    if (Hive.isBoxOpen(_boxName)) {
      await Hive.box<String>(_boxName).put(key, jsonEncode(value));
    }
  }

  static dynamic get(String key) {
    if (!Hive.isBoxOpen(_boxName)) return null;
    final value = Hive.box<String>(_boxName).get(key);
    return value == null ? null : jsonDecode(value);
  }

  static Future<void> clear() async {
    if (Hive.isBoxOpen(_boxName)) await Hive.box<String>(_boxName).clear();
  }
}
