import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:sendotp_flutter_sdk/sendotp_flutter_sdk.dart';
import 'package:sms_autofill/sms_autofill.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_constants.dart';
import '../models/user.dart';
import 'api_service.dart';
import 'foreground_service_manager.dart';
import 'incoming_order_alert_service.dart';
import 'notification_service.dart';
import 'order_alert_startup_permission_service.dart';

class AuthService {
  final ApiService _api = ApiService();
  _Msg91WidgetSession? _msg91WidgetSession;

  Future<Map<String, dynamic>> getPhoneStatus({
    required String phone,
    String? role,
  }) async {
    final response = await _api.post(
      ApiConstants.phoneStatus,
      data: {'phone': phone, if (role != null && role.isNotEmpty) 'role': role},
    );

    if (response['success'] == true) {
      return Map<String, dynamic>.from(response['data'] ?? const {});
    }

    throw Exception(response['message'] ?? 'Unable to check mobile number');
  }

  Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String phone,
    required String password,
    required String passwordConfirmation,
    String? role,
  }) async {
    final data = {
      'name': name,
      'email': email,
      'phone': phone,
      'password': password,
      'password_confirmation': passwordConfirmation,
      if (role != null && role.isNotEmpty) 'role': role,
    };

    final response = await _api.post(
      ApiConstants.register,
      data: data,
    );

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance.registerDeviceToken(
        user: user,
      );
      await IncomingOrderAlertService.instance.initialize();
      if (user.isDriver || user.isRestaurantMember) {
        await ForegroundServiceManager.startForegroundService();
        await OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: user.isRestaurantMember,
        );
      }
      return {'user': user, 'token': token};
    }
    throw Exception(response['message'] ?? 'Registration failed');
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
    String? role,
  }) async {
    final response = await _api.post(
      ApiConstants.login,
      data: {
        'email': email,
        'password': password,
        if (role != null && role.isNotEmpty) 'role': role,
      },
    );

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance.registerDeviceToken(
        user: user,
      );
      await IncomingOrderAlertService.instance.initialize();
      if (user.isDriver || user.isRestaurantMember) {
        await ForegroundServiceManager.startForegroundService();
        await OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: user.isRestaurantMember,
        );
      }
      return {'user': user, 'token': token};
    }
    throw Exception(response['message'] ?? 'Login failed');
  }

  Future<Map<String, dynamic>> loginWithPhone({
    required String phone,
    required String firebaseIdToken,
    String? role,
  }) async {
    final response = await _api.post(
      ApiConstants.loginWithPhone,
      data: {
        'phone': phone,
        'firebase_id_token': firebaseIdToken,
        if (role != null && role.isNotEmpty) 'role': role,
      },
    );

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance.registerDeviceToken(
        user: user,
      );
      await IncomingOrderAlertService.instance.initialize();
      if (user.isDriver || user.isRestaurantMember) {
        await ForegroundServiceManager.startForegroundService();
        await OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: user.isRestaurantMember,
        );
      }
      return {'user': user, 'token': token};
    }

    throw Exception(response['message'] ?? 'Login failed');
  }

  Future<void> sendLoginOtp({
    required String phone,
    String flow = 'login',
    String? role,
  }) async {
    debugPrint(
      "[OTP] send start phone=${_maskPhone(phone)} flow=$flow role=${role ?? 'restaurant'}",
    );

    final appSignature = await _smsRetrieverSignature();
    late final dynamic response;
    try {
      response = await _api.post(
        ApiConstants.sendLoginOtp,
        data: {
          'phone': phone,
          'flow': flow,
          if (role != null && role.isNotEmpty) 'role': role,
          if (appSignature.isNotEmpty) 'app_signature': appSignature,
        },
      );
    } catch (error) {
      debugPrint(
        "[OTP] send error phone=${_maskPhone(phone)} flow=$flow role=${role ?? 'restaurant'} error=$error",
      );
      rethrow;
    }

    debugPrint(
      "[OTP] send response success=${response['success']} provider=${response['data']?['provider']} message=${response['message']}",
    );

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Failed to send OTP');
    }

    final responseData = _asMap(response['data']);
    if (responseData['provider'] == 'msg91' &&
        responseData['otp_flow'] == 'widget') {
      await _sendMsg91WidgetOtp(
        phone: phone,
        flow: flow,
        role: role ?? 'restaurant',
        config: _asMap(responseData['msg91_widget']),
      );
    }
  }

  Future<Map<String, dynamic>> verifyLoginOtp({
    required String phone,
    required String otp,
    String flow = 'login',
    String? role,
  }) async {
    debugPrint(
      "[OTP] verify start phone=${_maskPhone(phone)} flow=$flow role=${role ?? 'restaurant'} digits=${otp.length}",
    );

    final msg91AccessToken = await _verifyMsg91WidgetOtpIfActive(
      phone: phone,
      otp: otp,
      flow: flow,
      role: role ?? 'restaurant',
    );

    late final dynamic response;
    try {
      response = await _api.post(
        ApiConstants.verifyLoginOtp,
        data: {
          'phone': phone,
          'otp': otp,
          'flow': flow,
          if (role != null && role.isNotEmpty) 'role': role,
          if (msg91AccessToken != null) ...{
            'msg91_access_token': msg91AccessToken,
            'msg91_req_id': _msg91WidgetSession?.reqId ?? '',
          },
        },
      );
    } catch (error) {
      debugPrint(
        "[OTP] verify error phone=${_maskPhone(phone)} flow=$flow role=${role ?? 'restaurant'} digits=${otp.length} error=$error",
      );
      rethrow;
    }

    debugPrint(
      "[OTP] verify response success=${response['success']} message=${response['message']}",
    );

    if (response['success'] == true) {
      if (flow != 'login') {
        return Map<String, dynamic>.from(response['data'] ?? const {});
      }

      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance.registerDeviceToken(
        user: user,
      );
      await IncomingOrderAlertService.instance.initialize();
      if (user.isDriver || user.isRestaurantMember) {
        await ForegroundServiceManager.startForegroundService();
        await OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: user.isRestaurantMember,
        );
      }
      return {'user': user, 'token': token};
    }

    throw Exception(response['message'] ?? 'OTP verification failed');
  }

  Future<Map<String, dynamic>> verifyFirebasePhone({
    required String phone,
    required String firebaseIdToken,
    required String flow,
    String? role,
  }) async {
    final response = await _api.post(
      ApiConstants.verifyFirebasePhone,
      data: {
        'phone': phone,
        'firebase_id_token': firebaseIdToken,
        'flow': flow,
        if (role != null && role.isNotEmpty) 'role': role,
      },
    );

    if (response['success'] == true) {
      return Map<String, dynamic>.from(response['data'] ?? const {});
    }

    throw Exception(response['message'] ?? 'Phone verification failed');
  }

  Future<void> logout() async {
    try {
      await _api.post(
        ApiConstants.logout,
        data: const {'target_app': 'restaurant'},
      );
    } catch (e) {
      // Ignore logout errors
    } finally {
      await clearStoredUser();
      await _api.clearToken();
      await ForegroundServiceManager.stopForegroundService();
    }
  }

  Future<void> deleteAccount() async {
    final response = await _api.delete(ApiConstants.deleteAccount);
    if (response is Map && response['success'] == false) {
      throw Exception(response['message'] ?? 'Account deletion failed');
    }

    await clearStoredUser();
    await _api.clearToken();
    await ForegroundServiceManager.stopForegroundService();
  }

  Future<void> _sendMsg91WidgetOtp({
    required String phone,
    required String flow,
    required String role,
    required Map<String, dynamic> config,
  }) async {
    final widgetId = (config['widget_id'] ?? '').toString().trim();
    final tokenAuth = (config['token_auth'] ?? '').toString().trim();
    if (widgetId.isEmpty || tokenAuth.isEmpty) {
      throw Exception('MSG91 widget credentials are missing.');
    }

    OTPWidget.initializeWidget(widgetId, tokenAuth);
    final identifier = _msg91Identifier(phone);
    debugPrint('[OTP] msg91 widget send start phone=${_maskPhone(phone)}');

    late final Map<String, dynamic> widgetData;
    try {
      final widgetResponse =
          await OTPWidget.sendOTP({'identifier': identifier});
      widgetData = _asMap(widgetResponse);
    } catch (error) {
      widgetData = _msg91WidgetDataFromError(error);
      if (!_msg91WidgetSuccess(widgetData)) {
        debugPrint(
          '[OTP] msg91 widget send failed ${_msg91WidgetFailureMessage(widgetData)}',
        );
        throw Exception(_msg91WidgetFailureMessage(widgetData));
      }
    }

    debugPrint(
        '[OTP] msg91 widget send response ${_safeMsg91WidgetData(widgetData)}');
    if (!_msg91WidgetSuccess(widgetData)) {
      throw Exception(_msg91WidgetFailureMessage(widgetData));
    }

    final accessToken = _msg91WidgetAccessToken(widgetData);
    final reqId = _msg91WidgetRequestId(widgetData);
    if (reqId == null && accessToken == null) {
      throw Exception(
        'MSG91 widget did not return a request id. Response: ${_safeMsg91WidgetData(widgetData)}',
      );
    }

    _msg91WidgetSession = _Msg91WidgetSession(
      phone: phone,
      flow: flow,
      role: role,
      reqId: reqId ?? '',
      accessToken: accessToken,
    );
  }

  bool hasCompletedMsg91WidgetVerification({
    required String phone,
    required String flow,
    required String role,
  }) {
    final session = _msg91WidgetSession;
    return session != null &&
        session.phone == phone &&
        session.flow == flow &&
        session.role == role &&
        (session.accessToken ?? '').isNotEmpty;
  }

  Future<String?> _verifyMsg91WidgetOtpIfActive({
    required String phone,
    required String otp,
    required String flow,
    required String role,
  }) async {
    final session = _msg91WidgetSession;
    if (session == null ||
        session.phone != phone ||
        session.flow != flow ||
        session.role != role) {
      return null;
    }

    if ((session.accessToken ?? '').isNotEmpty) {
      return session.accessToken;
    }

    debugPrint('[OTP] msg91 widget verify start reqId=${session.maskedReqId}');

    late final Map<String, dynamic> widgetData;
    try {
      final widgetResponse = await OTPWidget.verifyOTP({
        'reqId': session.reqId,
        'otp': otp,
      });
      widgetData = _asMap(widgetResponse);
    } catch (error) {
      widgetData = _msg91WidgetDataFromError(error);
      if (!_msg91WidgetSuccess(widgetData)) {
        debugPrint(
          '[OTP] msg91 widget verify failed ${_msg91WidgetFailureMessage(widgetData)}',
        );
        throw Exception(_msg91WidgetFailureMessage(widgetData));
      }
    }

    debugPrint(
        '[OTP] msg91 widget verify response ${_safeMsg91WidgetData(widgetData)}');
    if (!_msg91WidgetSuccess(widgetData)) {
      throw Exception(_msg91WidgetFailureMessage(widgetData));
    }

    return _msg91WidgetAccessToken(widgetData) ??
        _firstString(widgetData, [
          'access-token',
          'accessToken',
          'access_token',
          'token',
          'data.access-token',
          'data.accessToken',
          'data.access_token',
          'data.token',
          if (_msg91WidgetSuccess(widgetData)) 'message',
          if (_msg91WidgetSuccess(widgetData)) 'data.message',
        ]);
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return Map<String, dynamic>.from(value);
    if (value is String) {
      try {
        final decoded = jsonDecode(value);
        if (decoded is Map<String, dynamic>) return decoded;
        if (decoded is Map) return Map<String, dynamic>.from(decoded);
      } catch (_) {
        return const {};
      }
    }
    return const {};
  }

  Map<String, dynamic> _msg91WidgetDataFromError(Object error) {
    final text = error.toString();
    final match = RegExp(r'uri=(https?:\/\/\S+)').firstMatch(text);
    if (match == null) return const {};

    try {
      final uri = Uri.parse(match.group(1)!);
      return Map<String, dynamic>.from(uri.queryParameters);
    } catch (_) {
      return const {};
    }
  }

  bool _msg91WidgetSuccess(Map<String, dynamic> data) {
    final status = _firstString(data, ['status', 'type'])?.toLowerCase();
    return status == 'success' || status == 'verified';
  }

  String _msg91WidgetFailureMessage(Map<String, dynamic> data) {
    final message =
        _firstString(data, ['message', 'detailMessage', 'error']) ?? 'unknown';
    if (message.toLowerCase().contains('ipblocked')) {
      return 'MSG91 blocked this IP/token. Check OTP Token security or unblock/whitelist it in MSG91.';
    }
    return message;
  }

  String? _msg91WidgetRequestId(Map<String, dynamic> data) {
    return _firstString(data, const [
          'reqId',
          'req_id',
          'requestId',
          'request_id',
          'data.reqId',
          'data.req_id',
          'data.requestId',
          'data.request_id',
          'response.reqId',
          'response.req_id',
          'response.requestId',
          'response.request_id',
        ]) ??
        _firstStringIf(
            data, const ['message', 'data.message'], _looksLikeMsg91RequestId);
  }

  String? _msg91WidgetAccessToken(Map<String, dynamic> data) {
    return _firstString(data, const [
      'access-token',
      'accessToken',
      'access_token',
      'token',
      'data.access-token',
      'data.accessToken',
      'data.access_token',
      'data.token',
      'response.access-token',
      'response.accessToken',
      'response.access_token',
      'response.token',
    ]);
  }

  bool _looksLikeMsg91RequestId(String value) {
    return value.length >= 12 && !value.contains(RegExp(r'\s'));
  }

  Map<String, dynamic> _safeMsg91WidgetData(Map<String, dynamic> data) {
    final safe = _redactMsg91WidgetData(data);
    final reqId = _msg91WidgetRequestId(safe);
    if (reqId != null && reqId.length > 6) {
      safe['reqId'] =
          '${reqId.substring(0, 3)}***${reqId.substring(reqId.length - 3)}';
      safe.remove('requestId');
      safe.remove('request_id');
    }
    return safe;
  }

  Map<String, dynamic> _redactMsg91WidgetData(Map<String, dynamic> data) {
    final safe = <String, dynamic>{};
    for (final entry in data.entries) {
      if (_isSensitiveMsg91WidgetKey(entry.key)) continue;
      final value = entry.value;
      safe[entry.key] = value is Map
          ? _redactMsg91WidgetData(Map<String, dynamic>.from(value))
          : value;
    }
    return safe;
  }

  bool _isSensitiveMsg91WidgetKey(String key) {
    final normalized = key.toLowerCase().replaceAll(RegExp(r'[-_]'), '');
    return normalized == 'tokenauth' ||
        normalized == 'authkey' ||
        normalized == 'accesstoken' ||
        normalized == 'token';
  }

  String? _firstString(Map<String, dynamic> data, List<String> keys) {
    for (final key in keys) {
      final value = _deepValue(data, key)?.toString().trim();
      if (value != null && value.isNotEmpty) return value;
    }
    return null;
  }

  String? _firstStringIf(
    Map<String, dynamic> data,
    List<String> keys,
    bool Function(String value) test,
  ) {
    for (final key in keys) {
      final value = _deepValue(data, key)?.toString().trim();
      if (value != null && value.isNotEmpty && test(value)) return value;
    }
    return null;
  }

  dynamic _deepValue(Map<String, dynamic> data, String key) {
    dynamic current = data;
    for (final part in key.split('.')) {
      if (current is Map && current.containsKey(part)) {
        current = current[part];
      } else {
        return null;
      }
    }
    return current;
  }

  String _msg91Identifier(String phone) {
    return phone.replaceAll(RegExp(r'\D'), '');
  }

  Future<String> _smsRetrieverSignature() async {
    try {
      return (await SmsAutoFill().getAppSignature).trim();
    } catch (_) {
      return '';
    }
  }

  String _maskPhone(String phone) {
    final digits = phone.replaceAll(RegExp(r'\D'), '');
    if (digits.length <= 4) return '****';
    return '***${digits.substring(digits.length - 4)}';
  }

  Future<User> getCurrentUser() async {
    final response = await _api.get(ApiConstants.user);
    if (response['success'] == true) {
      return User.fromJson(response['data']);
    }
    throw Exception('Failed to get user');
  }

  Future<User> updateProfile({
    required String name,
    required String phone,
    String? profileImagePath,
  }) async {
    if (profileImagePath != null) {
      final request = http.MultipartRequest(
        'POST',
        Uri.parse('${ApiConstants.baseUrl}${ApiConstants.updateProfile}'),
      );

      final token = await _api.getToken();
      request.headers.addAll({'Authorization': 'Bearer $token'});

      request.fields['name'] = name;
      request.fields['phone'] = phone;

      request.files.add(
        await http.MultipartFile.fromPath('profile_image', profileImagePath),
      );

      final response = await request.send();
      final responseBody = await response.stream.bytesToString();
      final decodedResponse = jsonDecode(responseBody);

      if (decodedResponse['success'] == true) {
        final user = User.fromJson(decodedResponse['data']);
        await persistUser(user);
        return user;
      }
    } else {
      final response = await _api.put(
        ApiConstants.updateProfile,
        data: {'name': name, 'phone': phone},
      );
      if (response['success'] == true) {
        final user = User.fromJson(response['data']);
        await persistUser(user);
        return user;
      }
    }
    throw Exception('Failed to update profile');
  }

  Future<void> updatePassword({
    required String currentPassword,
    required String newPassword,
    required String newPasswordConfirmation,
  }) async {
    final response = await _api.post(
      ApiConstants.updatePassword,
      data: {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': newPasswordConfirmation,
      },
    );

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Failed to update password');
    }
  }

  Future<bool> isLoggedIn() async {
    final token = await _api.getToken();
    return token != null;
  }

  Future<void> persistUser(User user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('user_data', jsonEncode(user.toJson()));
  }

  Future<User?> getStoredUser() async {
    final prefs = await SharedPreferences.getInstance();
    final userData = prefs.getString('user_data');
    if (userData == null) return null;

    try {
      final userJson = jsonDecode(userData);
      if (userJson is Map<String, dynamic>) {
        return User.fromJson(userJson);
      }
    } catch (_) {
      return null;
    }
    return null;
  }

  Future<void> clearStoredUser() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('user_data');
  }
}

class _Msg91WidgetSession {
  const _Msg91WidgetSession({
    required this.phone,
    required this.flow,
    required this.role,
    required this.reqId,
    this.accessToken,
  });

  final String phone;
  final String flow;
  final String role;
  final String reqId;
  final String? accessToken;

  String get maskedReqId {
    if (reqId.length <= 6) return '***';
    return '${reqId.substring(0, 3)}***${reqId.substring(reqId.length - 3)}';
  }
}
