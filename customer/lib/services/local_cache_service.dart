import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';

class LocalCacheService {
  static const _boxName = 'public_api_cache_v2';
  static const _legacyBoxName = 'api_cache';
  static const _maxEntries = 240;
  static final Map<String, dynamic> _memory = <String, dynamic>{};
  static Future<void>? _initializing;

  static Future<void> initialize() {
    return _initializing ??= _initialize();
  }

  static Future<void> _initialize() async {
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
    final envelope = <String, dynamic>{
      '_cache_schema': _schemaVersion,
      'cached_at': DateTime.now().toUtc().toIso8601String(),
      'max_age_seconds': maxAge?.inSeconds,
      'data': value,
    };
    _memory[key] = envelope;
    await box.put(key, jsonEncode(envelope));
    while (box.length > _maxEntries) {
      final oldestKey = box.keyAt(0)?.toString();
      await box.deleteAt(0);
      if (oldestKey != null) _memory.remove(oldestKey);
    }
  }

  static dynamic get(
    String key, {
    Duration? maxAge,
    bool allowExpired = false,
  }) {
    if (!Hive.isBoxOpen(_boxName)) return null;
    final box = Hive.box<String>(_boxName);
    dynamic decoded = _memory[key];
    if (decoded == null) {
      final value = box.get(key);
      if (value == null) return null;
      try {
        decoded = jsonDecode(value);
        _memory[key] = decoded;
      } catch (_) {
        box.delete(key);
        return null;
      }
    }
    if (decoded is! Map || decoded['_cache_schema'] != _schemaVersion) {
      if (_isFailedApiResponse(decoded)) {
        box.delete(key);
        return null;
      }
      return allowExpired || maxAge == null ? decoded : null;
    }
    final data = decoded['data'];
    if (_isFailedApiResponse(data)) {
      box.delete(key);
      return null;
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
    return data;
  }

  static bool _isFailedApiResponse(dynamic value) {
    return value is Map && value['success'] == false;
  }

  static bool contains(String key) {
    return Hive.isBoxOpen(_boxName) &&
        Hive.box<String>(_boxName).containsKey(key);
  }

  static Future<void> clear() async {
    _memory.clear();
    if (Hive.isBoxOpen(_boxName)) await Hive.box<String>(_boxName).clear();
  }
}
