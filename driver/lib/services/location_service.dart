// lib/services/location_service.dart
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class LocationService {
  static const String _savedCityKey = 'saved_city';
  static const String _savedLatKey = 'saved_latitude';
  static const String _savedLngKey = 'saved_longitude';
  
  Future<Position?> getCurrentLocation({bool requestPermission = true}) async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      return null;
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied && requestPermission) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        return null;
      }
    }

    if (permission == LocationPermission.deniedForever) {
      return null;
    }

    return await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );
  }

  Future<bool> isLocationServiceEnabled() async {
    return await Geolocator.isLocationServiceEnabled();
  }

  Future<LocationPermission> checkLocationPermission() async {
    return await Geolocator.checkPermission();
  }

  Future<bool> requestLocationPermission() async {
    final permission = await Geolocator.requestPermission();
    return permission == LocationPermission.always || permission == LocationPermission.whileInUse;
  }

  Future<bool> openAppSettings() async {
    return await Geolocator.openAppSettings();
  }

  Future<bool> openLocationSettings() async {
    return await Geolocator.openLocationSettings();
  }

  Future<double> calculateDistance(double lat1, double lon1, double lat2, double lon2) async {
    return Geolocator.distanceBetween(lat1, lon1, lat2, lon2) / 1000; // Return in KM
  }

  Future<String?> getCityFromLatLng(double lat, double lng) async {
    try {
      final response = await http.get(
        Uri.parse('https://nominatim.openstreetmap.org/reverse?lat=$lat&lon=$lng&format=json&accept-language=en'),
        headers: {
          'User-Agent': 'YummaDriver/1.0 (https://food.unisell.online)',
          'Accept-Language': 'en',
        },
      );
      
      if (response.statusCode == 200) {
        final data = _safeDecodeJson(response.body);
        if (data is Map<String, dynamic>) {
          String city = data['address']?['city'] ??
              data['address']?['town'] ??
              data['address']?['village'] ??
              data['address']?['state'] ??
              '';
          return city;
        }
      } else {
        debugPrint('Geocoding reverse lookup failed: ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('Geocoding error: $e');
    }
    return null;
  }

  Future<Map<String, String>?> getAddressFromLatLng(double lat, double lng) async {
    try {
      final response = await http.get(
        Uri.parse('https://nominatim.openstreetmap.org/reverse?lat=$lat&lon=$lng&format=json&addressdetails=1&accept-language=en'),
        headers: {
          'User-Agent': 'YummaDriver/1.0 (https://food.unisell.online)',
          'Accept-Language': 'en',
        },
      );

      if (response.statusCode == 200) {
        final data = _safeDecodeJson(response.body);
        if (data is Map<String, dynamic>) {
          final addressData = data['address'] as Map<String, dynamic>?;
          final displayName = data['display_name']?.toString() ?? '';
          final road = addressData?['road']?.toString();
          final neighbourhood = addressData?['neighbourhood']?.toString();
          final suburb = addressData?['suburb']?.toString();
          final village = addressData?['village']?.toString();
          final town = addressData?['town']?.toString();
          final city = addressData?['city']?.toString() ?? town ?? village ?? addressData?['county']?.toString() ?? '';
          final state = addressData?['state']?.toString() ?? '';
          final postcode = addressData?['postcode']?.toString() ?? '';

          final addressParts = [road, neighbourhood, suburb, village, town]
              .where((value) => value != null && value.trim().isNotEmpty)
              .cast<String>()
              .toList();

          final address = addressParts.isNotEmpty
              ? addressParts.join(', ')
              : displayName;

          return {
            'address': address,
            'city': city,
            'state': state,
            'pincode': postcode,
          };
        }
      } else {
        debugPrint('Geocoding reverse lookup failed: ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('Geocoding error: $e');
    }
    return null;
  }

  Future<Map<String, dynamic>?> getLocationFromAddress(String address) async {
    try {
      final response = await http.get(
        Uri.parse('https://nominatim.openstreetmap.org/search?q=${Uri.encodeComponent(address)}&format=json&limit=1'),
        headers: {
          'User-Agent': 'YummaDriver/1.0 (https://food.unisell.online)',
          'Accept-Language': 'en',
        },
      );
      
      if (response.statusCode == 200) {
        final data = _safeDecodeJson(response.body);
        if (data is List && data.isNotEmpty && data[0] is Map<String, dynamic>) {
          final location = data[0] as Map<String, dynamic>;
          final lat = double.tryParse(location['lat']?.toString() ?? '');
          final lon = double.tryParse(location['lon']?.toString() ?? '');
          final city = location['address']?['city'] ??
              location['address']?['town'] ??
              location['address']?['village'] ??
              location['address']?['state'] ??
              address;

          if (lat != null && lon != null) {
            return {'city': city, 'lat': lat, 'lng': lon};
          }
        }
      } else {
        debugPrint('Geocoding search failed: ${response.statusCode}');
      }
    } catch (e) {
      debugPrint('Geocoding error: $e');
    }
    return null;
  }

  dynamic _safeDecodeJson(String body) {
    try {
      return jsonDecode(body);
    } catch (e) {
      debugPrint('JSON decode failed: $e');
      return null;
    }
  }

  Future<void> saveLocation(String city, double lat, double lng) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_savedCityKey, city);
    await prefs.setDouble(_savedLatKey, lat);
    await prefs.setDouble(_savedLngKey, lng);
  }

  Future<Map<String, dynamic>?> getSavedLocation() async {
    final prefs = await SharedPreferences.getInstance();
    final city = prefs.getString(_savedCityKey);
    final lat = prefs.getDouble(_savedLatKey);
    final lng = prefs.getDouble(_savedLngKey);
    
    if (city != null && lat != null && lng != null) {
      return {'city': city, 'lat': lat, 'lng': lng};
    }
    return null;
  }
}
