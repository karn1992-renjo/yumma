import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/app_config.dart';
import 'navigation_service.dart';
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
  static bool _isRedirectingToLogin = false;

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

  Future<void> _handleSessionExpired() async {
    final hadToken = (await getToken()) != null;
    await clearToken();
    if (!hadToken || _isRedirectingToLogin) return;
    _isRedirectingToLogin = true;
    Future<void>.delayed(Duration.zero, () async {
      appNavigatorKey.currentState?.pushNamedAndRemoveUntil(
        '/login',
        (route) => false,
      );
      await Future<void>.delayed(const Duration(milliseconds: 600));
      _isRedirectingToLogin = false;
    });
  }

  Future<Map<String, String>> _getHeaders() async {
    final token = await getToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Future<Map<String, dynamic>?> _withRestaurantScope(
    String endpoint,
    Map<String, dynamic>? queryParams,
  ) async {
    if (!endpoint.startsWith('/restaurant/')) {
      return queryParams;
    }

    final prefs = await SharedPreferences.getInstance();
    final selectedRestaurantId = prefs.getString('selected_restaurant_id');
    if (selectedRestaurantId == null || selectedRestaurantId.isEmpty) {
      return queryParams;
    }

    return {
      ...?queryParams,
      'restaurant_id': queryParams?['restaurant_id'] ?? selectedRestaurantId,
    };
  }

  bool _shouldRetryNetworkError(Object error) {
    final message = error.toString().toLowerCase();
    return message.contains(
          'connection closed before full header was received',
        ) ||
        message.contains('connection reset by peer') ||
        message.contains('connection terminated during handshake') ||
        error is http.ClientException;
  }

  Future<http.Response> _sendWithRetry(
    Future<http.Response> Function() request,
  ) async {
    try {
      return await request();
    } catch (error) {
      if (!_shouldRetryNetworkError(error)) {
        rethrow;
      }

      await Future.delayed(const Duration(milliseconds: 350));
      return request();
    }
  }

  /// Best-effort read of the last cached GET response for [endpoint] straight
  /// from local storage (no network). Returns null on a miss. Mirrors the exact
  /// URI (incl. restaurant scope) that [get] caches under.
  Future<dynamic> peekCache(
    String endpoint, {
    Map<String, dynamic>? queryParams,
  }) async {
    try {
      final scopedParams = await _withRestaurantScope(endpoint, queryParams);
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: scopedParams?.map((k, v) => MapEntry(k, v.toString())),
      );
      return LocalCacheService.get(uri.toString());
    } catch (_) {
      return null;
    }
  }

  /// Cache-first GET: fires [onCache] immediately with any locally cached payload
  /// (so a screen can paint instantly), then performs the live request and
  /// returns the fresh result. [onCache] is not called on a cache miss.
  Future<dynamic> getWithCache(
    String endpoint, {
    Map<String, dynamic>? queryParams,
    required void Function(dynamic cached) onCache,
  }) async {
    final cached = await peekCache(endpoint, queryParams: queryParams);
    if (cached != null) onCache(cached);
    return get(endpoint, queryParams: queryParams);
  }

  Future<dynamic> get(
    String endpoint, {
    Map<String, dynamic>? queryParams,
  }) async {
    try {
      final scopedParams = await _withRestaurantScope(endpoint, queryParams);
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: scopedParams?.map((k, v) => MapEntry(k, v.toString())),
      );
      final headers = await _getHeaders();

      if (kDebugMode) print('GET: $uri');

      final response = await _sendWithRetry(
        () => http.get(uri, headers: headers),
      );

      final result = await _handleResponse(response, endpoint);
      await LocalCacheService.put(uri.toString(), result);
      return result;
    } catch (e) {
      if (e is ApiException) rethrow;
      final scopedParams = await _withRestaurantScope(endpoint, queryParams);
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: scopedParams?.map((k, v) => MapEntry(k, v.toString())),
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
      final scopedParams = await _withRestaurantScope(endpoint, queryParams);
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: scopedParams?.map((k, v) => MapEntry(k, v.toString())),
      );
      final headers = await _getHeaders();

      if (kDebugMode) {
        print('POST: $uri');
        print('Body: $data');
      }

      final response = await _sendWithRetry(
        () => http.post(
          uri,
          headers: headers,
          body: data != null ? jsonEncode(data) : null,
        ),
      );

      return _handleResponse(response, endpoint);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw Exception('Network error: $e');
    }
  }

  Future<dynamic> put(
    String endpoint, {
    dynamic data,
    Map<String, dynamic>? queryParams,
  }) async {
    try {
      final scopedParams = await _withRestaurantScope(endpoint, queryParams);
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: scopedParams?.map((k, v) => MapEntry(k, v.toString())),
      );
      final headers = await _getHeaders();

      final response = await _sendWithRetry(
        () => http.put(uri, headers: headers, body: jsonEncode(data)),
      );

      return _handleResponse(response, endpoint);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw Exception('Network error: $e');
    }
  }

  Future<dynamic> delete(
    String endpoint, {
    Map<String, dynamic>? queryParams,
  }) async {
    try {
      final scopedParams = await _withRestaurantScope(endpoint, queryParams);
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: scopedParams?.map((k, v) => MapEntry(k, v.toString())),
      );
      final headers = await _getHeaders();

      final response = await _sendWithRetry(
        () => http.delete(uri, headers: headers),
      );

      return _handleResponse(response, endpoint);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw Exception('Network error: $e');
    }
  }

  Future<dynamic> _handleResponse(http.Response response,
      [String endpoint = '']) async {
    final isIdentityCall =
        endpoint == '/user' || endpoint.endsWith('/user');
    if (kDebugMode) print('Status: ${response.statusCode}');

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
        // Only bounce to login when the identity call itself fails. An HTML
        // login page from any other route is a server-side route/auth quirk
        // (e.g. an endpoint missing from the sanctum group) and must not kill
        // an otherwise-valid session.
        if (isIdentityCall) {
          await _handleSessionExpired();
          throw ApiException('Session expired. Please login again.');
        }
        throw ApiException('This section is temporarily unavailable.');
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
      // Only a 401 on the identity endpoint means the session itself is dead.
      // A 401 on any other resource is a per-request authorization problem and
      // must NOT log the user out (was auto-logging out on payout-detail taps).
      if (isIdentityCall) {
        await _handleSessionExpired();
        throw ApiException('Session expired. Please login again.');
      }
      throw ApiException(
        message.trim().isEmpty ? 'You are not allowed to view this.' : message,
      );
    }

    throw ApiException(message);
  }

  Future<dynamic> postMultipart(
    String endpoint, {
    Map<String, String>? fields,
    Map<String, String>? files,
    Map<String, List<String>>? fileLists,
    Map<String, dynamic>? queryParams,
  }) async {
    try {
      final scopedParams = await _withRestaurantScope(endpoint, queryParams);
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: scopedParams?.map((k, v) => MapEntry(k, v.toString())),
      );
      final token = await getToken();
      final request = http.MultipartRequest('POST', uri)
        ..headers.addAll({
          'Accept': 'application/json',
          if (token != null) 'Authorization': 'Bearer $token',
        });

      if (fields != null) {
        request.fields.addAll(fields);
      }

      if (files != null) {
        for (final entry in files.entries) {
          request.files.add(
            await http.MultipartFile.fromPath(entry.key, entry.value),
          );
        }
      }

      if (fileLists != null) {
        for (final entry in fileLists.entries) {
          for (final path in entry.value) {
            request.files.add(
              await http.MultipartFile.fromPath(entry.key, path),
            );
          }
        }
      }

      final streamed = await request.send();
      final response = await http.Response.fromStream(streamed);
      return _handleResponse(response, endpoint);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw Exception('Network error: $e');
    }
  }
}
