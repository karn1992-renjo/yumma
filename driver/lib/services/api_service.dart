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

  static const List<String> _tokenKeys = [
    'auth_token',
    'driver_auth_token',
    'access_token',
    'token',
  ];

  String? _authToken;

  /// Whether the most recent request actually carried a bearer token.
  bool _lastRequestHadToken = false;

  /// Consecutive 401s seen while a token WAS attached. A single transient 401
  /// (proxy hiccup, race on cold start) must not destroy a valid session.
  int _consecutiveUnauthorized = 0;

  Future<String?> getToken() async {
    if (_authToken != null && _authToken!.isNotEmpty) return _authToken;

    final prefs = await SharedPreferences.getInstance();
    for (final key in _tokenKeys) {
      final token = prefs.getString(key);
      if (token != null && token.isNotEmpty) {
        _authToken = token;
        if (key != 'auth_token') {
          await prefs.setString('auth_token', token);
        }
        return _authToken;
      }
    }

    return null;
  }

  Future<void> setToken(String token) async {
    if (_authToken != token) await LocalCacheService.clear();
    _authToken = token;
    _consecutiveUnauthorized = 0;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
    await prefs.setString('driver_auth_token', token);
  }

  Future<void> clearToken() async {
    _authToken = null;
    _consecutiveUnauthorized = 0;
    final prefs = await SharedPreferences.getInstance();
    for (final key in _tokenKeys) {
      await prefs.remove(key);
    }
    await LocalCacheService.clear();
  }

  Future<Map<String, String>> _getHeaders() async {
    final token = await getToken();
    _lastRequestHadToken = token != null && token.isNotEmpty;
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
    bool clearTokenOnUnauthorized = true,
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

      return await _handleResponse(
        response,
        clearTokenOnUnauthorized: clearTokenOnUnauthorized,
      );
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

  Future<dynamic> _handleResponse(
    http.Response response, {
    bool clearTokenOnUnauthorized = true,
  }) async {
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
        _consecutiveUnauthorized++;
        final path = response.request?.url.path ?? '';
        final isIdentityCheck = path.endsWith('/user') || path.endsWith('/me');
        if (clearTokenOnUnauthorized &&
            _lastRequestHadToken &&
            isIdentityCheck &&
            _consecutiveUnauthorized >= 2) {
          await clearToken();
          throw ApiException('Session expired. Please login again.');
        }
        throw ApiException('Authorization check failed. Retrying...');
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
      _consecutiveUnauthorized = 0;
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
      // Ignore a 401 for a request that went out without a token (cold-start
      // race): the session was never actually rejected.
      if (!_lastRequestHadToken) {
        throw ApiException('Not authenticated yet. Please try again.');
      }
      _consecutiveUnauthorized++;
      // Only the identity endpoint (`/user`) is authoritative about a dead
      // token. A 401 from any other endpoint (permission quirk, transient proxy
      // failure, a route that briefly 401s under load) must NOT log the driver
      // out — that was the "session keeps dropping" bug.
      final path = response.request?.url.path ?? '';
      final isIdentityCheck = path.endsWith('/user') || path.endsWith('/me');
      if (clearTokenOnUnauthorized &&
          isIdentityCheck &&
          _consecutiveUnauthorized >= 2) {
        await clearToken();
        throw ApiException('Session expired. Please login again.');
      }
      throw ApiException('Authorization check failed. Retrying...');
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
