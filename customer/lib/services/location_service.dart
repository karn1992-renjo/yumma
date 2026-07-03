// lib/services/location_service.dart
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class LocationService {
  static const String _savedCityKey = 'saved_city';
  static const String _savedAddressKey = 'saved_address';
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

    try {
      return await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
        timeLimit: const Duration(seconds: 12),
      );
    } catch (e) {
      debugPrint('Current GPS location failed, trying last known location: $e');
      return await Geolocator.getLastKnownPosition();
    }
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
          'User-Agent': 'FoodFlowApp/1.0 (https://example.com)',
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
          'User-Agent': 'FoodFlowApp/1.0 (https://example.com)',
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
        Uri.parse('https://nominatim.openstreetmap.org/search?q=${Uri.encodeComponent(address)}&format=json&addressdetails=1&limit=1&accept-language=en'),
        headers: {
          'User-Agent': 'FoodFlowApp/1.0 (https://example.com)',
          'Accept-Language': 'en',
        },
      ).timeout(const Duration(seconds: 10));
      
      if (response.statusCode == 200) {
        final data = _safeDecodeJson(response.body);
        if (data is List && data.isNotEmpty && data[0] is Map<String, dynamic>) {
          final location = data[0] as Map<String, dynamic>;
          final lat = double.tryParse(location['lat']?.toString() ?? '');
          final lon = double.tryParse(location['lon']?.toString() ?? '');
          
          // Extract city name from the response with multiple fallbacks
          String? city;
          if (location['address'] is Map<String, dynamic>) {
            final addressMap = location['address'] as Map<String, dynamic>;
            city = addressMap['city']?.toString() ??
                addressMap['town']?.toString() ??
                addressMap['village']?.toString() ??
                addressMap['municipality']?.toString() ??
                addressMap['district']?.toString() ??
                addressMap['county']?.toString() ??
                addressMap['state']?.toString();
          }
          
          // If city not found in address, try to extract from display_name
          if ((city == null || city.isEmpty) && location['display_name'] is String) {
            final displayName = location['display_name'] as String;
            final parts = displayName.split(',');
            if (parts.length > 1) {
              // Get the second-to-last part which usually contains the city
              city = parts[parts.length - 2].trim();
            } else if (parts.isNotEmpty) {
              city = parts.first.trim();
            }
          }
          
          if (lat != null && lon != null) {
            return {
              'city': city?.isNotEmpty == true ? city : address,
              'lat': lat,
              'lng': lon
            };
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

  Future<void> saveLocation(
    String city,
    double lat,
    double lng, {
    String? address,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_savedCityKey, city);
    if (address != null && address.trim().isNotEmpty) {
      await prefs.setString(_savedAddressKey, address.trim());
    }
    await prefs.setDouble(_savedLatKey, lat);
    await prefs.setDouble(_savedLngKey, lng);
  }

  Future<Map<String, dynamic>?> getSavedLocation() async {
    final prefs = await SharedPreferences.getInstance();
    final city = prefs.getString(_savedCityKey);
    final address = prefs.getString(_savedAddressKey);
    final lat = prefs.getDouble(_savedLatKey);
    final lng = prefs.getDouble(_savedLngKey);
    
    if (city != null && lat != null && lng != null) {
      return {'city': city, 'address': address ?? city, 'lat': lat, 'lng': lng};
    }
    return null;
  }

  // Get location suggestions as user types
  Future<List<Map<String, dynamic>>> getLocationSuggestions(String query) async {
    if (query.trim().isEmpty) {
      return [];
    }
    
    try {
      final response = await http.get(
        Uri.parse('https://nominatim.openstreetmap.org/search?q=${Uri.encodeComponent(query)}&format=json&addressdetails=1&limit=8&accept-language=en'),
        headers: {
          'User-Agent': 'FoodFlowApp/1.0 (https://example.com)',
          'Accept-Language': 'en',
        },
      ).timeout(const Duration(seconds: 8));
      
      if (response.statusCode == 200) {
        final data = _safeDecodeJson(response.body);
        if (data is List) {
          final suggestions = <Map<String, dynamic>>[];
          
          for (final item in data) {
            if (item is Map<String, dynamic>) {
              final lat = double.tryParse(item['lat']?.toString() ?? '');
              final lon = double.tryParse(item['lon']?.toString() ?? '');
              
              if (lat != null && lon != null) {
                String? city;
                if (item['address'] is Map<String, dynamic>) {
                  final addressMap = item['address'] as Map<String, dynamic>;
                  city = addressMap['city']?.toString() ??
                      addressMap['town']?.toString() ??
                      addressMap['village']?.toString() ??
                      addressMap['municipality']?.toString() ??
                      addressMap['district']?.toString() ??
                      addressMap['county']?.toString();
                }
                
                if ((city == null || city.isEmpty) && item['display_name'] is String) {
                  final displayName = item['display_name'] as String;
                  final parts = displayName.split(',');
                  if (parts.length > 1) {
                    city = parts[parts.length - 2].trim();
                  } else if (parts.isNotEmpty) {
                    city = parts.first.trim();
                  }
                }
                
                final displayName = item['display_name']?.toString() ?? city ?? query;
                suggestions.add({
                  'city': city?.isNotEmpty == true ? city : displayName.split(',').first.trim(),
                  'lat': lat,
                  'lng': lon,
                  'display_name': displayName,
                });
              }
            }
          }
          
          return suggestions;
        }
      }
    } catch (e) {
      debugPrint('Location suggestions error: $e');
    }
    
    return [];
  }
}
