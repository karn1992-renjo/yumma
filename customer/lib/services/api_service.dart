import 'dart:convert';
import 'dart:async';
import 'dart:io';
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

enum ApiCachePolicy {
  none(Duration.zero),
  discovery(Duration(minutes: 5)),
  staticContent(Duration(hours: 24));

  const ApiCachePolicy(this.maxAge);
  final Duration maxAge;
}

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  String? _authToken;
  static const Duration _requestTimeout = Duration(seconds: 25);

  Future<String?> getToken() async {
    if (_authToken != null) return _authToken;
    final prefs = await SharedPreferences.getInstance();
    _authToken = prefs.getString('auth_token');
    return _authToken;
  }

  Future<void> setToken(String token) async {
    _authToken = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }

  Future<void> clearToken() async {
    _authToken = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }

  Future<Map<String, String>> _getHeaders({bool includeAuth = true}) async {
    final token = includeAuth ? await getToken() : null;
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (token?.isNotEmpty == true) {
      headers['Authorization'] = 'Bearer $token';
    }

    return headers;
  }

  Future<dynamic> get(
    String endpoint, {
    Map<String, dynamic>? queryParams,
    bool includeAuth = true,
    bool cacheResponse = false,
    bool cacheFirst = false,
    bool refreshCached = true,
    void Function(dynamic freshData)? onCacheRefreshed,
    ApiCachePolicy cachePolicy = ApiCachePolicy.none,
  }) async {
    final uri = _buildUri(endpoint, queryParams);
    final shouldCache = cacheResponse || cachePolicy != ApiCachePolicy.none;
    final maxAge = cachePolicy == ApiCachePolicy.none
        ? null
        : cachePolicy.maxAge;
    if (shouldCache && cacheFirst) {
      final cached = LocalCacheService.get(
        uri.toString(),
        maxAge: maxAge,
        allowExpired: true,
      );
      if (cached != null) {
        if (refreshCached) {
          unawaited(_refreshCachedGet(
            uri,
            includeAuth: includeAuth,
            onRefreshed: onCacheRefreshed,
            maxAge: maxAge,
          ));
        }
        return cached;
      }
    }

    try {

      if (kDebugMode) print('📍 GET: $uri');

      final response = await http.get(
        uri,
        headers: await _getHeaders(includeAuth: includeAuth),
      ).timeout(_requestTimeout);

      final result = await _handleResponse(response, includeAuth: includeAuth);
      if (shouldCache) {
        await LocalCacheService.put(uri.toString(), result, maxAge: maxAge);
      }
      return result;
    } on TimeoutException {
      if (shouldCache) {
        final cached = LocalCacheService.get(
          uri.toString(),
          maxAge: maxAge,
          allowExpired: true,
        );
        if (cached != null) return cached;
      }
      throw ApiException('Connection timed out. Please try again.');
    } on SocketException {
      if (shouldCache) {
        final cached = LocalCacheService.get(
          uri.toString(),
          maxAge: maxAge,
          allowExpired: true,
        );
        if (cached != null) return cached;
      }
      throw ApiException('No internet connection. Please try again.');
    } catch (e) {
      if (e is ApiException) rethrow;
      if (shouldCache) {
        final cached = LocalCacheService.get(
          uri.toString(),
          maxAge: maxAge,
          allowExpired: true,
        );
        if (cached != null) return cached;
      }
      throw Exception('Network error: $e');
    }
  }

  Uri _buildUri(String endpoint, Map<String, dynamic>? queryParams) {
    final normalizedParams = queryParams == null
        ? null
        : Map<String, dynamic>.fromEntries(
            queryParams.entries.toList()
              ..sort((a, b) => a.key.compareTo(b.key)),
          );
    return Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
      queryParameters: normalizedParams?.map(
        (key, value) => MapEntry(key, value.toString()),
      ),
    );
  }

  Future<void> _refreshCachedGet(
    Uri uri, {
    required bool includeAuth,
    void Function(dynamic freshData)? onRefreshed,
    Duration? maxAge,
  }) async {
    try {
      final response = await http
          .get(uri, headers: await _getHeaders(includeAuth: includeAuth))
          .timeout(_requestTimeout);
      final result = await _handleResponse(response, includeAuth: includeAuth);
      final previous = LocalCacheService.get(uri.toString());
      await LocalCacheService.put(uri.toString(), result, maxAge: maxAge);
      if (jsonEncode(previous) != jsonEncode(result)) {
        onRefreshed?.call(result);
      }
    } catch (error) {
      if (kDebugMode) {
        debugPrint('Background refresh skipped for $uri: $error');
      }
    }
  }

  Future<dynamic> post(String endpoint,
      {dynamic data,
      Map<String, dynamic>? queryParams,
      bool includeAuth = true}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
          queryParameters:
              queryParams?.map((k, v) => MapEntry(k, v.toString())));

      if (kDebugMode) {
        print('📍 POST: $uri');
        print('📤 Body: $data');
      }

      final response = await http.post(
        uri,
        headers: await _getHeaders(includeAuth: includeAuth),
        body: data != null ? jsonEncode(data) : null,
      ).timeout(_requestTimeout);

      return await _handleResponse(response, includeAuth: includeAuth);
    } on TimeoutException {
      throw ApiException('Connection timed out. Please try again.');
    } on SocketException {
      throw ApiException('No internet connection. Please try again.');
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
      ).timeout(_requestTimeout);

      return await _handleResponse(response);
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
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
        queryParameters: queryParams?.map(
          (key, value) => MapEntry(key, value.toString()),
        ),
      );

      final response = await http.delete(
        uri,
        headers: await _getHeaders(),
      ).timeout(_requestTimeout);

      return await _handleResponse(response);
    } catch (e) {
      if (e is ApiException) rethrow;
      throw Exception('Network error: $e');
    }
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

  Future<dynamic> _handleResponse(
    http.Response response, {
    bool includeAuth = true,
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
      if (includeAuth && response.statusCode == 401) {
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

    if (response.statusCode == 429) {
      final retryAfter = response.headers['retry-after'];
      final seconds = int.tryParse(retryAfter ?? '');
      final waitText = seconds == null || seconds <= 0
          ? 'a short while'
          : seconds >= 60
              ? '${(seconds / 60).ceil()} minute${seconds > 60 ? 's' : ''}'
              : '$seconds second${seconds == 1 ? '' : 's'}';
      throw ApiException(
        'Too many attempts. Please wait $waitText before trying again.',
      );
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
    if (includeAuth &&
        (response.statusCode == 401 ||
            normalizedMessage.contains('unauthenticated') ||
            normalizedMessage.contains('session expired'))) {
      await clearToken();
      throw ApiException('Session expired. Please login again.');
    }

    throw ApiException(message);
  }
}
