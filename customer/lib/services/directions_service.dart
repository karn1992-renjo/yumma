import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'native_config_service.dart';

class DirectionsService {
  static final http.Client _client = http.Client();
  static final Map<String, _RouteCacheEntry> _cache =
      <String, _RouteCacheEntry>{};
  static final Map<String, Future<List<LatLng>>> _inFlight =
      <String, Future<List<LatLng>>>{};
  static const Duration _routeMaxAge = Duration(minutes: 2);

  static Future<List<LatLng>> fetchRoutePoints(
    LatLng origin,
    LatLng destination,
  ) async {
    final cacheKey = _routeKey(origin, destination);
    final cached = _cache[cacheKey];
    if (cached != null &&
        DateTime.now().difference(cached.savedAt) < _routeMaxAge) {
      return cached.points;
    }
    final pending = _inFlight[cacheKey];
    if (pending != null) return pending;

    final request = _fetchRoutePoints(origin, destination);
    _inFlight[cacheKey] = request;
    try {
      final points = await request;
      if (points.isNotEmpty) {
        _cache[cacheKey] = _RouteCacheEntry(points, DateTime.now());
        if (_cache.length > 24) _cache.remove(_cache.keys.first);
      }
      return points;
    } finally {
      if (identical(_inFlight[cacheKey], request)) {
        _inFlight.remove(cacheKey);
      }
    }
  }

  static Future<List<LatLng>> _fetchRoutePoints(
    LatLng origin,
    LatLng destination,
  ) async {
    final googleMapsApiKey = await NativeConfigService.getGoogleMapsApiKey();
    if (googleMapsApiKey.isEmpty) {
      return [];
    }

    final url = Uri.parse(
      'https://maps.googleapis.com/maps/api/directions/json'
      '?origin=${origin.latitude},${origin.longitude}'
      '&destination=${destination.latitude},${destination.longitude}'
      '&mode=driving'
      '&key=$googleMapsApiKey',
    );

    final response = await _client.get(url);
    if (response.statusCode != 200) {
      return [];
    }

    final data = jsonDecode(response.body);
    if (data == null || data['status'] != 'OK') {
      return [];
    }

    final route = data['routes'] is List && data['routes'].isNotEmpty
        ? data['routes'][0]
        : null;
    final polyline = route?['overview_polyline']?['points'];
    if (polyline == null || polyline is! String) {
      return [];
    }

    return decodePolyline(polyline);
  }

  static String _routeKey(LatLng origin, LatLng destination) {
    String point(LatLng value) =>
        '${value.latitude.toStringAsFixed(4)},${value.longitude.toStringAsFixed(4)}';
    return '${point(origin)}>${point(destination)}';
  }

  static List<LatLng> decodePolyline(String encoded) {
    final points = <LatLng>[];
    int index = 0;
    int len = encoded.length;
    int lat = 0;
    int lng = 0;

    while (index < len) {
      int shift = 0;
      int result = 0;
      while (true) {
        final int byte = encoded.codeUnitAt(index++) - 63;
        result |= (byte & 0x1F) << shift;
        shift += 5;
        if (byte < 0x20) break;
      }
      final int deltaLat = ((result & 1) != 0) ? ~(result >> 1) : (result >> 1);
      lat += deltaLat;

      shift = 0;
      result = 0;
      while (true) {
        final int byte = encoded.codeUnitAt(index++) - 63;
        result |= (byte & 0x1F) << shift;
        shift += 5;
        if (byte < 0x20) break;
      }
      final int deltaLng = ((result & 1) != 0) ? ~(result >> 1) : (result >> 1);
      lng += deltaLng;

      points.add(LatLng(lat / 1E5, lng / 1E5));
    }

    return points;
  }
}

class _RouteCacheEntry {
  const _RouteCacheEntry(this.points, this.savedAt);

  final List<LatLng> points;
  final DateTime savedAt;
}
