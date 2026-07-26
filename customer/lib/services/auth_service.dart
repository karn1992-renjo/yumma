import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:sendotp_flutter_sdk/sendotp_flutter_sdk.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:sms_autofill/sms_autofill.dart';
import 'api_service.dart';
import 'foreground_service_manager.dart';
import 'notification_service.dart';
import '../config/api_constants.dart';
import 'package:http/http.dart' as http;
import '../models/user.dart';
import '../utils/phone_number_utils.dart';

class AuthService {
  final ApiService _api = ApiService();
  _Msg91WidgetSession? _msg91WidgetSession;

  Future<Map<String, dynamic>> getPhoneStatus({
    required String phone,
    String? role,
  }) async {
    final normalizedPhone = _normalizePhone(phone);
    final response = await _api.get(
      ApiConstants.phoneStatus,
      queryParams: {
        'phone': normalizedPhone,
        if (role != null && role.isNotEmpty) 'role': role,
      },
      includeAuth: false,
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
    String? password,
    String? passwordConfirmation,
    String? verifiedPhoneToken,
    String? role,
    String? referralCode,
  }) async {
    final normalizedPhone = _normalizePhone(phone);
    final data = {
      'name': name,
      'phone': normalizedPhone,
      if (email.trim().isNotEmpty) 'email': email.trim(),
      if (password != null && password.isNotEmpty) 'password': password,
      if (passwordConfirmation != null && passwordConfirmation.isNotEmpty)
        'password_confirmation': passwordConfirmation,
      if (verifiedPhoneToken != null && verifiedPhoneToken.isNotEmpty)
        'verified_phone_token': verifiedPhoneToken,
      if (role != null && role.isNotEmpty) 'role': role,
      if (referralCode != null && referralCode.isNotEmpty)
        'referral_code': referralCode,
    };

    final response = await _api.post(
      ApiConstants.register,
      data: data,
      includeAuth: false,
    );

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance
          .registerDeviceToken(user: user);
      if (user.isDriver || user.isRestaurantOwner) {
        await ForegroundServiceManager.startForegroundService();
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
      includeAuth: false,
    );

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance
          .registerDeviceToken(user: user);
      if (user.isDriver || user.isRestaurantOwner) {
        await ForegroundServiceManager.startForegroundService();
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
    final normalizedPhone = _normalizePhone(phone);
    final response = await _api.post(
      ApiConstants.loginWithPhone,
      data: {
        'phone': normalizedPhone,
        'firebase_id_token': firebaseIdToken,
        if (role != null && role.isNotEmpty) 'role': role,
      },
      includeAuth: false,
    );

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance
          .registerDeviceToken(user: user);
      if (user.isDriver || user.isRestaurantOwner) {
        await ForegroundServiceManager.startForegroundService();
      }
      return {'user': user, 'token': token};
    }
    throw Exception(response['message'] ?? 'Login failed');
  }

  Future<Map<String, dynamic>> verifyFirebasePhone({
    required String phone,
    required String firebaseIdToken,
    required String flow,
    String? role,
  }) async {
    final normalizedPhone = _normalizePhone(phone);
    final response = await _api.post(
      ApiConstants.verifyFirebasePhone,
      data: {
        'phone': normalizedPhone,
        'firebase_id_token': firebaseIdToken,
        'flow': flow,
        if (role != null && role.isNotEmpty) 'role': role,
      },
      includeAuth: false,
    );

    if (response['success'] == true) {
      return Map<String, dynamic>.from(response['data'] ?? const {});
    }

    throw Exception(response['message'] ?? 'Phone verification failed');
  }

  Future<Map<String, dynamic>> loginWithSocial({
    required String provider,
    required String firebaseIdToken,
    String? role,
    String? displayName,
  }) async {
    final response = await _api.post(
      ApiConstants.socialLogin,
      data: {
        'provider': provider,
        'firebase_id_token': firebaseIdToken,
        if (role != null && role.isNotEmpty) 'role': role,
        if (displayName != null && displayName.isNotEmpty)
          'display_name': displayName,
      },
      includeAuth: false,
    );

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance
          .registerDeviceToken(user: user);
      if (user.isDriver || user.isRestaurantOwner) {
        await ForegroundServiceManager.startForegroundService();
      }
      return {'user': user, 'token': token};
    }

    throw Exception(response['message'] ?? 'Social login failed');
  }

  Future<void> sendLoginOtp({
    required String phone,
    String flow = 'login',
    String? role,
  }) async {
    final normalizedPhone = _normalizePhone(phone);
    final appSignature = await _smsRetrieverSignature();
    final data = {
      'phone': normalizedPhone,
      'flow': flow,
      if (role != null && role.isNotEmpty) 'role': role,
      if (appSignature.isNotEmpty) 'app_signature': appSignature,
    };

    debugPrint(
      "[OTP] send start phone=${_maskPhone(normalizedPhone)} flow=$flow role=${role ?? 'customer'}",
    );

    late final dynamic response;
    try {
      response = await _sendLoginOtpRequest(data);
    } catch (error) {
      debugPrint(
        "[OTP] send error phone=${_maskPhone(normalizedPhone)} flow=$flow role=${role ?? 'customer'} error=$error",
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
        phone: normalizedPhone,
        flow: flow,
        role: role ?? 'customer',
        config: _asMap(responseData['msg91_widget']),
      );
    }
  }

  Future<dynamic> _sendLoginOtpRequest(Map<String, String> data) async {
    try {
      return await _api.post(
        ApiConstants.sendLoginOtp,
        data: data,
        includeAuth: false,
      );
    } catch (error) {
      final message = error.toString().toLowerCase();
      final methodNotAllowed = message.contains('post method') &&
          message.contains('not supported') &&
          message.contains('auth/otp/send');

      if (!methodNotAllowed) rethrow;

      return _api.get(
        ApiConstants.sendLoginOtp,
        queryParams: data,
        includeAuth: false,
      );
    }
  }

  Future<Map<String, dynamic>> verifyLoginOtp({
    required String phone,
    required String otp,
    String flow = 'login',
    String? role,
  }) async {
    final normalizedPhone = _normalizePhone(phone);
    debugPrint(
      "[OTP] verify start phone=${_maskPhone(normalizedPhone)} flow=$flow role=${role ?? 'customer'} digits=${otp.length}",
    );

    final msg91AccessToken = await _verifyMsg91WidgetOtpIfActive(
      phone: normalizedPhone,
      otp: otp,
      flow: flow,
      role: role ?? 'customer',
    );

    late final dynamic response;
    try {
      response = await _api.post(
        ApiConstants.verifyLoginOtp,
        data: {
          'phone': normalizedPhone,
          'otp': otp,
          'flow': flow,
          if (role != null && role.isNotEmpty) 'role': role,
          if (msg91AccessToken != null) ...{
            'msg91_access_token': msg91AccessToken,
            'msg91_req_id': _msg91WidgetSession?.reqId ?? '',
          },
        },
        includeAuth: false,
      );
    } catch (error) {
      debugPrint(
        "[OTP] verify error phone=${_maskPhone(normalizedPhone)} flow=$flow role=${role ?? 'customer'} digits=${otp.length} error=$error",
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
      await FirebaseNotificationService.instance
          .registerDeviceToken(user: user);
      if (user.isDriver || user.isRestaurantOwner) {
        await ForegroundServiceManager.startForegroundService();
      }
      return {'user': user, 'token': token};
    }

    throw Exception(response['message'] ?? 'OTP verification failed');
  }

  Future<void> requestPasswordReset({
    required String email,
  }) async {
    final response = await _api.post(
      ApiConstants.forgotPassword,
      data: {
        'email': email.trim(),
      },
      includeAuth: false,
    );

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Failed to send reset link');
    }
  }

  Future<void> sendForgotPasswordOtp({
    required String phone,
    String? role,
  }) async {
    await sendLoginOtp(phone: phone, flow: 'forgot_password', role: role);
  }

  Future<void> resetPasswordByPhone({
    required String phone,
    required String verifiedPhoneToken,
    required String password,
    required String passwordConfirmation,
  }) async {
    final normalizedPhone = _normalizePhone(phone);
    final response = await _api.post(
      ApiConstants.resetPasswordByPhone,
      data: {
        'phone': normalizedPhone,
        'verified_phone_token': verifiedPhoneToken,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
      includeAuth: false,
    );

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Failed to reset password');
    }
  }

  Future<void> logout() async {
    try {
      await _api
          .post(ApiConstants.logout, data: const {'target_app': 'customer'});
    } catch (e) {
      // Ignore logout errors
    } finally {
      await clearStoredUser();
      await _api.clearToken();
      await ForegroundServiceManager.stopForegroundService();
    }
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
    final normalizedPhone = _normalizePhone(phone);
    if (profileImagePath != null) {
      // Use regular post with multipart form data
      final request = http.MultipartRequest(
        'POST',
        Uri.parse('${ApiConstants.baseUrl}${ApiConstants.updateProfile}'),
      );

      final token = await _api.getToken();
      request.headers.addAll({
        'Authorization': 'Bearer $token',
      });

      request.fields['name'] = name;
      request.fields['phone'] = normalizedPhone;

      request.files.add(await http.MultipartFile.fromPath(
        'profile_image',
        profileImagePath,
      ));

      final response = await request.send();
      final responseBody = await response.stream.bytesToString();
      final decodedResponse = jsonDecode(responseBody);

      if (decodedResponse['success'] == true) {
        final user = User.fromJson(decodedResponse['data']);
        await persistUser(user);
        return user;
      }

      final errorMessage = decodedResponse['message']?.toString();
      final errors = decodedResponse['errors'];
      if (errorMessage != null && errorMessage.isNotEmpty) {
        throw Exception(errorMessage);
      }
      if (errors is Map) {
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty) {
            throw Exception(value.first.toString());
          }
          if (value != null) {
            throw Exception(value.toString());
          }
        }
      }
    } else {
      final response = await _api.put(ApiConstants.updateProfile, data: {
        'name': name,
        'phone': normalizedPhone,
      });
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
    final response = await _api.post(ApiConstants.updatePassword, data: {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': newPasswordConfirmation,
    });

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Failed to update password');
    }
  }

  Future<bool> isLoggedIn() async {
    final token = await _api.getToken();
    return token != null;
  }

  String _normalizePhone(String phone) {
    return PhoneNumberUtils.normalizeMobile(phone, log: true).normalizedNumber;
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

    final reqId = _msg91WidgetRequestId(widgetData);
    if (reqId == null) {
      throw Exception(
        'MSG91 widget did not return a request id. Check the MSG91 widget token/auth key and response payload.',
      );
    }

    _msg91WidgetSession = _Msg91WidgetSession(
      phone: phone,
      flow: flow,
      role: role,
      reqId: reqId,
    );
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

    return _firstString(widgetData, [
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
    if (value is String) return _msg91WidgetDataFromText(value);
    return const {};
  }

  Map<String, dynamic> _msg91WidgetDataFromError(Object error) {
    if (error is PlatformException) {
      final details = _asMap(error.details);
      if (details.isNotEmpty) {
        return {
          ...details,
          if ((error.code).trim().isNotEmpty) 'code': error.code,
          if ((error.message ?? '').trim().isNotEmpty) 'message': error.message,
        };
      }
    }

    return _msg91WidgetDataFromText(error.toString());
  }

  Map<String, dynamic> _msg91WidgetDataFromText(String text) {
    final trimmed = text.trim();
    if (trimmed.isEmpty) return const {};

    try {
      final decoded = jsonDecode(trimmed);
      final decodedMap = _asMap(decoded);
      if (decodedMap.isNotEmpty) return decodedMap;
    } catch (_) {}

    final jsonMatch = RegExp(r'\{.*\}').firstMatch(trimmed);
    if (jsonMatch != null) {
      try {
        final decoded = jsonDecode(jsonMatch.group(0)!);
        final decodedMap = _asMap(decoded);
        if (decodedMap.isNotEmpty) return decodedMap;
      } catch (_) {}
    }

    final uriMatch = RegExp(r'(?:uri=)?(https?:\/\/\S+)').firstMatch(trimmed);
    if (uriMatch != null) {
      try {
        final uri = Uri.parse(uriMatch.group(1)!);
        final query = Map<String, dynamic>.from(uri.queryParameters);
        if (query.isNotEmpty) return query;
      } catch (_) {}
    }

    if (_looksLikeMsg91RequestId(trimmed)) {
      return {'request_id': trimmed};
    }

    return {'message': trimmed};
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

  Map<String, dynamic> _safeMsg91WidgetData(Map<String, dynamic> data) {
    final safe = Map<String, dynamic>.from(data)
      ..remove('tokenAuth')
      ..remove('authkey')
      ..remove('access-token')
      ..remove('accessToken')
      ..remove('access_token')
      ..remove('token');
    final reqId = _msg91WidgetRequestId(safe);
    if (reqId != null && reqId.length > 6) {
      safe['reqId'] =
          '${reqId.substring(0, 3)}***${reqId.substring(reqId.length - 3)}';
      safe.remove('requestId');
    }
    return safe;
  }

  String? _msg91WidgetRequestId(Map<String, dynamic> data) {
    final value = _firstString(data, const [
      'reqId',
      'req_id',
      'requestId',
      'requestID',
      'request_id',
      'message',
      'data',
      'data.reqId',
      'data.req_id',
      'data.requestId',
      'data.requestID',
      'data.request_id',
      'data.message',
      'data.data.reqId',
      'data.data.req_id',
      'data.data.requestId',
      'data.data.request_id',
      'data.data.message',
      'response.reqId',
      'response.req_id',
      'response.requestId',
      'response.request_id',
      'response.message',
    ]);

    return value != null && _looksLikeMsg91RequestId(value) ? value : null;
  }

  String? _firstString(Map<String, dynamic> data, List<String> keys) {
    for (final key in keys) {
      final value = _deepValue(data, key)?.toString().trim();
      if (value != null && value.isNotEmpty) return value;
      if (!key.contains('.')) {
        final deepValue = _findDeepString(data, key)?.trim();
        if (deepValue != null && deepValue.isNotEmpty) return deepValue;
      }
    }
    return null;
  }

  String? _findDeepString(dynamic value, String key) {
    if (value is Map) {
      if (value.containsKey(key)) return value[key]?.toString();
      for (final child in value.values) {
        final found = _findDeepString(child, key);
        if (found != null && found.trim().isNotEmpty) return found;
      }
    } else if (value is List) {
      for (final child in value) {
        final found = _findDeepString(child, key);
        if (found != null && found.trim().isNotEmpty) return found;
      }
    }
    return null;
  }

  bool _looksLikeMsg91RequestId(String value) {
    final trimmed = value.trim();
    if (trimmed.length < 8 || trimmed.contains(RegExp(r'\s'))) return false;
    final lower = trimmed.toLowerCase();
    if (lower == 'success' || lower == 'verified') return false;
    return RegExp(r'^[a-zA-Z0-9._:-]+$').hasMatch(trimmed);
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

  String _maskPhone(String phone) {
    final digits = phone.replaceAll(RegExp(r'\D'), '');
    if (digits.length <= 4) return '****';
    return '***${digits.substring(digits.length - 4)}';
  }

  Future<String> _smsRetrieverSignature() async {
    try {
      return (await SmsAutoFill().getAppSignature).trim();
    } catch (_) {
      return '';
    }
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

// Import http for multipart request
class _Msg91WidgetSession {
  const _Msg91WidgetSession({
    required this.phone,
    required this.flow,
    required this.role,
    required this.reqId,
  });

  final String phone;
  final String flow;
  final String role;
  final String reqId;

  String get maskedReqId {
    if (reqId.length <= 6) return '***';
    return '${reqId.substring(0, 3)}***${reqId.substring(reqId.length - 3)}';
  }
}
