import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';

class LocalCacheService {
  static const _boxName = 'public_api_cache_v2';
  static const _legacyBoxName = 'api_cache';
  static const _maxEntries = 80;

  static Future<void> initialize() async {
    await Hive.initFlutter();
    if (await Hive.boxExists(_legacyBoxName)) {
      if (Hive.isBoxOpen(_legacyBoxName)) {
        await Hive.box<String>(_legacyBoxName).clear();
        await Hive.box<String>(_legacyBoxName).close();
      }
      await Hive.deleteBoxFromDisk(_legacyBoxName);
    }
    if (!Hive.isBoxOpen(_boxName)) {
      await Hive.openBox<String>(_boxName);
    }
  }

  static const _schemaVersion = 1;

  static Future<void> put(
    String key,
    dynamic value, {
    Duration? maxAge,
  }) async {
    if (!Hive.isBoxOpen(_boxName)) return;
    final box = Hive.box<String>(_boxName);
    await box.put(key, jsonEncode({
      '_cache_schema': _schemaVersion,
      'cached_at': DateTime.now().toUtc().toIso8601String(),
      'max_age_seconds': maxAge?.inSeconds,
      'data': value,
    }));
    while (box.length > _maxEntries) {
      await box.deleteAt(0);
    }
  }

  static dynamic get(
    String key, {
    Duration? maxAge,
    bool allowExpired = false,
  }) {
    if (!Hive.isBoxOpen(_boxName)) return null;
    final value = Hive.box<String>(_boxName).get(key);
    if (value == null) return null;
    final decoded = jsonDecode(value);
    if (decoded is! Map || decoded['_cache_schema'] != _schemaVersion) {
      return allowExpired || maxAge == null ? decoded : null;
    }
    final cachedAt = DateTime.tryParse(decoded['cached_at']?.toString() ?? '');
    final storedSeconds = int.tryParse(
      decoded['max_age_seconds']?.toString() ?? '',
    );
    final effectiveMaxAge = maxAge ??
        (storedSeconds == null ? null : Duration(seconds: storedSeconds));
    final expired = cachedAt == null ||
        (effectiveMaxAge != null &&
            DateTime.now().toUtc().difference(cachedAt) > effectiveMaxAge);
    if (expired && !allowExpired) return null;
    return decoded['data'];
  }

  static bool contains(String key) {
    return Hive.isBoxOpen(_boxName) &&
        Hive.box<String>(_boxName).containsKey(key);
  }

  static Future<void> clear() async {
    if (Hive.isBoxOpen(_boxName)) await Hive.box<String>(_boxName).clear();
  }
}
