import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:http/http.dart' as http;

/// The kind of adverse condition, used to pick an icon/colour in the UI.
enum WeatherAdvisoryKind { rain, storm, snow, fog, wind, heat, cold }

/// A short, human advisory shown on the order-tracking screen when the
/// weather at the delivery location could genuinely slow the partner down.
/// Absence of this object means the weather is fine and nothing is shown.
class WeatherAdvisory {
  const WeatherAdvisory(this.kind, this.message);

  final WeatherAdvisoryKind kind;
  final String message;
}

/// Live weather lookup backed by Open-Meteo (free, no API key, no signup).
/// https://open-meteo.com/en/docs
class WeatherService {
  WeatherService._();

  static final http.Client _client = http.Client();
  static final Map<String, _CacheEntry> _cache = <String, _CacheEntry>{};
  static const Duration _maxAge = Duration(minutes: 15);

  /// Current advisory for [where], or null when conditions are unremarkable.
  /// Results are cached per ~1km cell for [_maxAge] so repeated tracking-screen
  /// polls don't hit the network.
  static Future<WeatherAdvisory?> advisoryFor(LatLng where) async {
    final key =
        '${where.latitude.toStringAsFixed(2)},${where.longitude.toStringAsFixed(2)}';
    final cached = _cache[key];
    if (cached != null && DateTime.now().difference(cached.savedAt) < _maxAge) {
      return cached.value;
    }

    try {
      final uri = Uri.parse(
        'https://api.open-meteo.com/v1/forecast'
        '?latitude=${where.latitude}&longitude=${where.longitude}'
        '&current=weather_code,temperature_2m,wind_speed_10m'
        '&wind_speed_unit=kmh&timezone=auto',
      );
      final res =
          await _client.get(uri).timeout(const Duration(seconds: 6));
      if (res.statusCode != 200) return _store(key, null);

      final body = jsonDecode(res.body);
      final current = body is Map ? body['current'] : null;
      if (current is! Map) return _store(key, null);

      final code = (current['weather_code'] as num?)?.toInt() ?? 0;
      final temp = (current['temperature_2m'] as num?)?.toDouble();
      final wind = (current['wind_speed_10m'] as num?)?.toDouble() ?? 0;
      return _store(key, _classify(code, temp, wind));
    } catch (e) {
      debugPrint('weather advisory failed: $e');
      return _store(key, null);
    }
  }

  static WeatherAdvisory? _classify(int code, double? temp, double windKmh) {
    // WMO weather interpretation codes.
    if (code >= 95) {
      return const WeatherAdvisory(
        WeatherAdvisoryKind.storm,
        'Thunderstorms near the route. Your delivery partner will take the '
        'safest path, so it may take a little longer.',
      );
    }
    if ((code >= 71 && code <= 77) || code == 85 || code == 86) {
      return const WeatherAdvisory(
        WeatherAdvisoryKind.snow,
        'Snow near the route. Your delivery partner is riding carefully — it '
        'may take a little longer.',
      );
    }
    if ((code >= 51 && code <= 67) || (code >= 80 && code <= 82)) {
      return const WeatherAdvisory(
        WeatherAdvisoryKind.rain,
        "It's raining near the restaurant. Your delivery partner may ride "
        'slower to stay safe.',
      );
    }
    if (code == 45 || code == 48) {
      return const WeatherAdvisory(
        WeatherAdvisoryKind.fog,
        'Low visibility due to fog. Your delivery partner will ride carefully '
        '— it may take a little longer.',
      );
    }
    if (windKmh >= 40) {
      return const WeatherAdvisory(
        WeatherAdvisoryKind.wind,
        'Strong winds near the route. Your delivery partner will take the '
        'safest path, so it may take a little longer.',
      );
    }
    if (temp != null && temp >= 43) {
      return const WeatherAdvisory(
        WeatherAdvisoryKind.heat,
        "It's extremely hot outside. Do offer your delivery partner some "
        'water if you can.',
      );
    }
    if (temp != null && temp <= 2) {
      return const WeatherAdvisory(
        WeatherAdvisoryKind.cold,
        "It's freezing outside. Your delivery partner is braving the cold to "
        'reach you.',
      );
    }
    return null;
  }

  static WeatherAdvisory? _store(String key, WeatherAdvisory? value) {
    _cache[key] = _CacheEntry(DateTime.now(), value);
    return value;
  }
}

class _CacheEntry {
  _CacheEntry(this.savedAt, this.value);

  final DateTime savedAt;
  final WeatherAdvisory? value;
}
