import 'dart:math' as math;

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import 'v2_util.dart';

/// Checks a lat/lng against the admin's active delivery areas
/// (`GET /delivery-areas/active`). Circle areas use a haversine radius test;
/// polygon areas use ray casting. Fails open — if the check can't run or the
/// admin has configured no areas, the location is treated as serviceable.
Future<bool> v2IsServiceable(double lat, double lng) async {
  try {
    final res = await ApiService().get(
      ApiConstants.deliveryAreasActive,
      includeAuth: false,
    );
    final areas = v2ExtractList(res);
    if (areas.isEmpty) return true;

    for (final raw in areas) {
      if (raw is! Map) continue;
      final a = Map<String, dynamic>.from(raw);
      final polygon = _parsePolygon(a['polygon_coordinates']);
      if (polygon.length >= 3) {
        if (_pointInPolygon(lat, lng, polygon)) return true;
        continue;
      }
      final cLat = v2DoubleOrNull(a['latitude']);
      final cLng = v2DoubleOrNull(a['longitude']);
      final radiusKm = v2DoubleOrNull(a['radius_km']);
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

List<List<double>> _parsePolygon(dynamic value) {
  final out = <List<double>>[];
  if (value is List) {
    for (final p in value) {
      if (p is Map) {
        final la = v2DoubleOrNull(p['lat'] ?? p['latitude']);
        final ln = v2DoubleOrNull(p['lng'] ?? p['lng'] ?? p['longitude']);
        if (la != null && ln != null) out.add([la, ln]);
      } else if (p is List && p.length >= 2) {
        final la = v2DoubleOrNull(p[0]);
        final ln = v2DoubleOrNull(p[1]);
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
