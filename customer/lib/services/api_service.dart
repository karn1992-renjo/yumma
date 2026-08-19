import 'dart:convert';
import 'dart:async';
import 'dart:io';
import 'package:crypto/crypto.dart';
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
  screen(Duration(minutes: 2)),
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
  final http.Client _client = http.Client();
  final Map<String, Future<dynamic>> _inFlightGets =
      <String, Future<dynamic>>{};
  final Map<String, Future<dynamic>> _inFlightPosts =
      <String, Future<dynamic>>{};
  final Map<String, _RecentPostResponse> _recentPostResponses =
      <String, _RecentPostResponse>{};
  static const Duration _requestTimeout = Duration(seconds: 25);

  Future<String?> getToken() async {
    if (_authToken != null) return _authToken;
    final prefs = await SharedPreferences.getInstance();
    _authToken = prefs.getString('auth_token');
    return _authToken;
  }

  Future<void> setToken(String token) async {
    if (_authToken != null && _authToken != token) {
      _inFlightGets.clear();
      _inFlightPosts.clear();
      _recentPostResponses.clear();
    }
    _authToken = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }

  Future<void> clearToken() async {
    _authToken = null;
    _inFlightGets.clear();
    _inFlightPosts.clear();
    _recentPostResponses.clear();
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await LocalCacheService.clear();
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
    final maxAge =
        cachePolicy == ApiCachePolicy.none ? null : cachePolicy.maxAge;
    final cacheKey = await _cacheKey(uri, includeAuth: includeAuth);
    if (shouldCache) await LocalCacheService.initialize();
    if (shouldCache && cacheFirst) {
      final cached = LocalCacheService.get(
        cacheKey,
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
            cacheKey: cacheKey,
          ));
        }
        return cached;
      }
    }

    try {
      if (kDebugMode) print('📍 GET: $uri');

      final result = await _coalescedGet(uri, includeAuth: includeAuth);
      if (shouldCache && _isCacheableResponse(result)) {
        await LocalCacheService.put(cacheKey, result, maxAge: maxAge);
      }
      return result;
    } on TimeoutException {
      if (shouldCache) {
        final cached = LocalCacheService.get(
          cacheKey,
          maxAge: maxAge,
          allowExpired: true,
        );
        if (cached != null) return cached;
      }
      throw ApiException('Connection timed out. Please try again.');
    } on SocketException {
      if (shouldCache) {
        final cached = LocalCacheService.get(
          cacheKey,
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
          cacheKey,
          maxAge: maxAge,
          allowExpired: true,
        );
        if (cached != null) return cached;
      }
      throw Exception('Network error: $e');
    }
  }

  Future<String> _cacheKey(Uri uri, {required bool includeAuth}) async {
    if (!includeAuth) return 'public:${uri.toString()}';
    final token = await getToken();
    if (token == null || token.isEmpty) return 'guest:${uri.toString()}';
    final fingerprint = sha256.convert(utf8.encode(token)).toString();
    return 'user:$fingerprint:${uri.toString()}';
  }

  Future<dynamic> _coalescedGet(
    Uri uri, {
    required bool includeAuth,
  }) async {
    final key = await _cacheKey(uri, includeAuth: includeAuth);
    final existing = _inFlightGets[key];
    if (existing != null) return existing;

    final request = _client
        .get(uri, headers: await _getHeaders(includeAuth: includeAuth))
        .timeout(_requestTimeout)
        .then(
          (response) => _handleResponse(response, includeAuth: includeAuth),
        );
    _inFlightGets[key] = request;
    try {
      return await request;
    } finally {
      if (identical(_inFlightGets[key], request)) {
        _inFlightGets.remove(key);
      }
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
    required String cacheKey,
  }) async {
    try {
      final result = await _coalescedGet(uri, includeAuth: includeAuth);
      final previous = LocalCacheService.get(cacheKey);
      if (!_isCacheableResponse(result)) return;
      await LocalCacheService.put(cacheKey, result, maxAge: maxAge);
      if (jsonEncode(previous) != jsonEncode(result)) {
        onRefreshed?.call(result);
      }
    } catch (error) {
      if (kDebugMode) {
        debugPrint('Background refresh skipped for $uri: $error');
      }
    }
  }

  bool _isCacheableResponse(dynamic result) {
    return result is! Map || result['success'] != false;
  }

  Future<dynamic> post(String endpoint,
      {dynamic data,
      Map<String, dynamic>? queryParams,
      bool includeAuth = true,
      bool coalesce = false,
      Duration reuseFor = Duration.zero}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint').replace(
          queryParameters:
              queryParams?.map((k, v) => MapEntry(k, v.toString())));

      if (kDebugMode) {
        print('📍 POST: $uri');
        print('📤 Body: $data');
      }

      if (coalesce || reuseFor > Duration.zero) {
        return await _coalescedPost(
          uri,
          data: data,
          includeAuth: includeAuth,
          reuseFor: reuseFor,
        );
      }

      final response = await _client
          .post(
            uri,
            headers: await _getHeaders(includeAuth: includeAuth),
            body: data != null ? jsonEncode(data) : null,
          )
          .timeout(_requestTimeout);

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

  Future<dynamic> _coalescedPost(
    Uri uri, {
    dynamic data,
    required bool includeAuth,
    required Duration reuseFor,
  }) async {
    final encodedBody = data == null ? '' : jsonEncode(data);
    final bodyFingerprint = sha256.convert(utf8.encode(encodedBody)).toString();
    final key =
        '${await _cacheKey(uri, includeAuth: includeAuth)}:$bodyFingerprint';
    final recent = _recentPostResponses[key];
    if (recent != null) {
      if (DateTime.now().isBefore(recent.expiresAt)) return recent.value;
      _recentPostResponses.remove(key);
    }

    final existing = _inFlightPosts[key];
    if (existing != null) return existing;

    final request = _client
        .post(
          uri,
          headers: await _getHeaders(includeAuth: includeAuth),
          body: encodedBody.isEmpty ? null : encodedBody,
        )
        .timeout(_requestTimeout)
        .then(
          (response) => _handleResponse(response, includeAuth: includeAuth),
        );
    _inFlightPosts[key] = request;
    try {
      final result = await request;
      if (reuseFor > Duration.zero && _isCacheableResponse(result)) {
        _recentPostResponses.removeWhere(
          (_, entry) => !DateTime.now().isBefore(entry.expiresAt),
        );
        _recentPostResponses[key] = _RecentPostResponse(result, reuseFor);
      }
      return result;
    } finally {
      if (identical(_inFlightPosts[key], request)) {
        _inFlightPosts.remove(key);
      }
    }
  }

  Future<dynamic> put(String endpoint, {dynamic data}) async {
    try {
      final uri = Uri.parse('${AppConfig.apiBaseUrl}$endpoint');

      final response = await http
          .put(
            uri,
            headers: await _getHeaders(),
            body: jsonEncode(data),
          )
          .timeout(_requestTimeout);

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

      final response = await http
          .delete(
            uri,
            headers: await _getHeaders(),
          )
          .timeout(_requestTimeout);

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

class _RecentPostResponse {
  _RecentPostResponse(this.value, Duration lifetime)
      : expiresAt = DateTime.now().add(lifetime);

  final dynamic value;
  final DateTime expiresAt;
}
