import 'dart:math' as math;

import '../config/api_constants.dart';
import 'api_service.dart';

/// Checks a lat/lng against the admin's active delivery areas
/// (`GET /delivery-areas/active`). Circle areas use a haversine radius test;
/// polygon areas use ray casting. Fails open -- if the check can't run or the
/// admin has configured no areas, the location is treated as serviceable.
class DeliveryZoneService {
  Future<bool> isServiceable(double lat, double lng) async {
    try {
      final res = await ApiService().get(
        ApiConstants.deliveryAreasActive,
        includeAuth: false,
      );
      final areas = _extractList(res);
      if (areas.isEmpty) return true;

      for (final raw in areas) {
        if (raw is! Map) continue;
        final a = Map<String, dynamic>.from(raw);
        final polygon = _parsePolygon(a['polygon_coordinates']);
        if (polygon.length >= 3) {
          if (_pointInPolygon(lat, lng, polygon)) return true;
          continue;
        }
        final cLat = _doubleOrNull(a['latitude']);
        final cLng = _doubleOrNull(a['longitude']);
        final radiusKm = _doubleOrNull(a['radius_km']);
        if (cLat == null || cLng == null || radiusKm == null || radiusKm <= 0) {
          continue;
        }
        if (_haversineKm(lat, lng, cLat, cLng) <= radiusKm) return true;
      }
      return false;
    } catch (_) {
      return true;
    }
  }

  List<dynamic> _extractList(dynamic response) {
    if (response is List) return response;
    if (response is Map) {
      final data = response['data'];
      if (data is List) return data;
      if (data is Map) {
        for (final k in const ['data', 'items', 'sections', 'restaurants', 'results']) {
          if (data[k] is List) return data[k] as List;
        }
      }
      for (final k in const [
        'items',
        'sections',
        'restaurants',
        'results',
        'categories',
        'banners',
        'offers',
        'menu',
      ]) {
        if (response[k] is List) return response[k] as List;
      }
    }
    return const <dynamic>[];
  }

  double? _doubleOrNull(dynamic v) {
    if (v is double) return v;
    if (v is int) return v.toDouble();
    if (v is String) return double.tryParse(v);
    return null;
  }

  List<List<double>> _parsePolygon(dynamic value) {
    final out = <List<double>>[];
    if (value is List) {
      for (final p in value) {
        if (p is Map) {
          final la = _doubleOrNull(p['lat'] ?? p['latitude']);
          final ln = _doubleOrNull(p['lng'] ?? p['longitude']);
          if (la != null && ln != null) out.add([la, ln]);
        } else if (p is List && p.length >= 2) {
          final la = _doubleOrNull(p[0]);
          final ln = _doubleOrNull(p[1]);
          if (la != null && ln != null) out.add([la, ln]);
        }
      }
    }
    return out;
  }

  bool _pointInPolygon(double lat, double lng, List<List<double>> poly) {
    var inside = false;
    for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      final yi = poly[i][0], xi = poly[i][1];
      final yj = poly[j][0], xj = poly[j][1];
      final intersect = ((yi > lat) != (yj > lat)) &&
          (lng < (xj - xi) * (lat - yi) / ((yj - yi) == 0 ? 1e-12 : (yj - yi)) + xi);
      if (intersect) inside = !inside;
    }
    return inside;
  }

  double _haversineKm(double lat1, double lng1, double lat2, double lng2) {
    const r = 6371.0;
    final dLat = _rad(lat2 - lat1);
    final dLng = _rad(lng2 - lng1);
    final a = math.sin(dLat / 2) * math.sin(dLat / 2) +
        math.cos(_rad(lat1)) *
            math.cos(_rad(lat2)) *
            math.sin(dLng / 2) *
            math.sin(dLng / 2);
    return r * 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a));
  }

  double _rad(double deg) => deg * math.pi / 180.0;
}
