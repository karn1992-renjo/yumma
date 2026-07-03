import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../config/app_config.dart';
import 'local_cache_service.dart';

class ApiException implements Exception {
  ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  String? _authToken;

  Future<String?> getToken() async {
    if (_authToken != null) return _authToken;
    final prefs = await SharedPreferences.getInstance();
    _authToken = prefs.getString('auth_token');
    return _authToken;
  }

  Future<void> setToken(String token) async {
    if (_authToken != token) await LocalCacheService.clear();
    _authToken = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }

  Future<void> clearToken() async {
    _authToken = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await LocalCacheService.clear();
  }

  Future<Map<String, String>> _getHeaders() async {
    final token = await getToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Future<dynamic> get(
    String endpoint, {
    Map<String, dynamic>? queryParams,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: queryParams?.map((k, v) => MapEntry(k, v.toString())),
      );

      if (kDebugMode) print('📍 GET: $uri');

      final response = await http.get(uri, headers: await _getHeaders());

      final result = await _handleResponse(response);
      await LocalCacheService.put(uri.toString(), result);
      return result;
    } catch (e) {
      if (e is ApiException) rethrow;
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: queryParams?.map((k, v) => MapEntry(k, v.toString())),
      );
      final cached = LocalCacheService.get(uri.toString());
      if (cached != null) return cached;
      throw Exception('Network error: $e');
    }
  }

  Future<dynamic> post(
    String endpoint, {
    dynamic data,
    Map<String, dynamic>? queryParams,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: queryParams?.map((k, v) => MapEntry(k, v.toString())),
      );

      if (kDebugMode) {
        print('📍 POST: $uri');
        print('📤 Body: $data');
      }

      final response = await http.post(
        uri,
        headers: await _getHeaders(),
        body: data != null ? jsonEncode(data) : null,
      );

      return await _handleResponse(response);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw Exception('Network error: $e');
    }
  }

  Future<dynamic> put(String endpoint, {dynamic data}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint');

      final response = await http.put(
        uri,
        headers: await _getHeaders(),
        body: jsonEncode(data),
      );

      return await _handleResponse(response);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw Exception('Network error: $e');
    }
  }

  Future<dynamic> delete(String endpoint) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint');

      final response = await http.delete(uri, headers: await _getHeaders());

      return await _handleResponse(response);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw Exception('Network error: $e');
    }
  }

  Future<dynamic> _handleResponse(http.Response response) async {
    if (kDebugMode) print('📥 Status: ${response.statusCode}');

    if (response.body.trim().isEmpty) {
      if (response.statusCode >= 200 && response.statusCode < 300) {
        return {'success': true};
      }
      throw ApiException('Empty response from server');
    }

    final contentType = response.headers['content-type'] ?? '';
    final trimmedBody = response.body.trimLeft();
    if (contentType.contains('text/html') ||
        trimmedBody.startsWith('<!DOCTYPE html>') ||
        trimmedBody.startsWith('<html')) {
      if (trimmedBody.toLowerCase().contains('<title>login')) {
        await clearToken();
        throw ApiException('Session expired. Please login again.');
      }
      if (kDebugMode) print('HTML response body: ${response.body}');
      throw ApiException('Server returned HTML instead of JSON.');
    }

    dynamic data;
    try {
      data = jsonDecode(response.body);
    } catch (e) {
      if (kDebugMode) print('Response body: ${response.body}');
      throw ApiException('Invalid JSON response from server');
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return data;
    }

    String message = 'Something went wrong';
    if (data is Map<String, dynamic>) {
      if (data['message'] != null) {
        message = data['message'].toString();
      } else if (data['error'] != null) {
        message = data['error'].toString();
      } else if (data['errors'] is Map) {
        final errors = data['errors'] as Map;
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty) {
            message = value.first.toString();
            break;
          }
          if (value != null && value.toString().trim().isNotEmpty) {
            message = value.toString();
            break;
          }
        }
      }
    }

    final normalizedMessage = message.toLowerCase();
    if (response.statusCode == 401 ||
        normalizedMessage.contains('unauthenticated')) {
      await clearToken();
      throw ApiException('Session expired. Please login again.');
    }

    throw ApiException(message);
  }

  Future<dynamic> postMultipart(
    String endpoint, {
    Map<String, String>? fields,
    Map<String, String>? files,
  }) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint');
      final token = await getToken();
      final request = http.MultipartRequest('POST', uri)
        ..headers.addAll({
          'Accept': 'application/json',
          if (token != null) 'Authorization': 'Bearer $token',
        });

      if (fields != null) request.fields.addAll(fields);
      if (files != null) {
        for (final entry in files.entries) {
          request.files.add(
            await http.MultipartFile.fromPath(entry.key, entry.value),
          );
        }
      }

      final streamed = await request.send();
      final response = await http.Response.fromStream(streamed);
      return await _handleResponse(response);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw Exception('Network error: $e');
    }
  }
}
