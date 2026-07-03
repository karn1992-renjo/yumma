import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'native_config_service.dart';

class DirectionsService {
  static Future<List<LatLng>> fetchRoutePoints(
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

    final response = await http.get(url);
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
