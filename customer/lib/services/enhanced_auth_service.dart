import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';
import 'foreground_service_manager.dart';
import 'notification_service.dart';
import '../config/api_constants.dart';
import 'package:http/http.dart' as http;
import '../models/user.dart';
import '../utils/phone_number_utils.dart';

class EnhancedAuthService {
  final ApiService _api = ApiService();

  Future<void> sendPhoneOtp({
    required String phoneNumber,
    String flow = 'login',
    String? role,
    String? countryCode,
  }) async {
    try {
      final normalizedPhone = _normalizePhoneNumber(
        phoneNumber,
        defaultMobileCountryCode: countryCode,
      );
      final response = await _sendOtpRequest({
        'phone': normalizedPhone,
        'flow': flow,
        if (role != null && role.isNotEmpty) 'role': role,
      });

      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Failed to send OTP');
      }
    } catch (e) {
      throw Exception('Error sending OTP: $e');
    }
  }

  /// Verify phone OTP
  Future<Map<String, dynamic>> verifyPhoneOtp({
    required String phoneNumber,
    required String otp,
    String flow = 'login',
    String? role,
    String? countryCode,
  }) async {
    try {
      final normalizedPhone = _normalizePhoneNumber(
        phoneNumber,
        defaultMobileCountryCode: countryCode,
      );
      final response = await _api.post(
        ApiConstants.verifyLoginOtp,
        data: {
          'phone': normalizedPhone,
          'otp': otp,
          'flow': flow,
          if (role != null && role.isNotEmpty) 'role': role,
        },
        includeAuth: false,
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
    } catch (e) {
      throw Exception('Error verifying OTP: $e');
    }
  }

  /// Resend OTP
  Future<void> resendOtp({
    required String phoneNumber,
    String flow = 'login',
    String? role,
    String? countryCode,
  }) async {
    try {
      final normalizedPhone = _normalizePhoneNumber(
        phoneNumber,
        defaultMobileCountryCode: countryCode,
      );
      final response = await _sendOtpRequest({
        'phone': normalizedPhone,
        'flow': flow,
        if (role != null && role.isNotEmpty) 'role': role,
      });
      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Failed to resend OTP');
      }
    } catch (e) {
      throw Exception('Error resending OTP: $e');
    }
  }

  Future<String> _getRecaptchaToken() async {
    return '';
  }

  Future<dynamic> _sendOtpRequest(Map<String, String> data) async {
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

  /// Validate reCAPTCHA score on backend
  Future<bool> validateRecaptchaScore({
    required String token,
    required double minScore,
  }) async {
    try {
      final response = await _api.post(
        '/recaptcha/validate',
        data: {
          'token': token,
          'score_threshold': minScore,
        },
        includeAuth: false,
      );

      return response['success'] == true && response['data']['valid'] == true;
    } catch (e) {
      print('Error validating reCAPTCHA: $e');
      return false;
    }
  }

  /// Normalize phone number to international format using the provided country code.
  String _normalizePhoneNumber(String phone,
      {String? defaultMobileCountryCode}) {
    return PhoneNumberUtils.normalizeMobile(
      phone,
      countryCode: defaultMobileCountryCode,
      log: true,
    ).normalizedNumber;
  }

  /// Format phone number for display using the provided country code.
  String formatPhoneForDisplay(String phone, {String? countryCode}) {
    final normalized = _normalizePhoneNumber(
      phone,
      defaultMobileCountryCode: countryCode,
    );
    return normalized;
  }

  /// Legacy methods (kept for backward compatibility)

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
      'phone': _normalizePhoneNumber(phone),
      'password': password,
      'password_confirmation': passwordConfirmation,
      if (role != null && role.isNotEmpty) 'role': role,
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

  Future<void> logout() async {
    try {
      await _api.post(ApiConstants.logout, data: const {'target_app': 'customer'});
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

  Future<void> persistUser(User user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('user', jsonEncode(user.toJson()));
  }

  Future<void> clearStoredUser() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('user');
  }

  Future<User?> getStoredUser() async {
    final prefs = await SharedPreferences.getInstance();
    final userJson = prefs.getString('user');
    if (userJson != null) {
      return User.fromJson(jsonDecode(userJson));
    }
    return null;
  }
}
