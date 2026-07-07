import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';
import 'foreground_service_manager.dart';
import 'incoming_order_alert_service.dart';
import 'notification_service.dart';
import '../config/api_constants.dart';
import 'package:http/http.dart' as http;
import '../models/user.dart';

class EnhancedAuthService {
  final ApiService _api = ApiService();

  /// Send OTP to phone number with reCAPTCHA validation
  Future<void> sendPhoneOtp({
    required String phoneNumber,
    String? role,
  }) async {
    try {
      final normalizedPhone = _normalizePhoneNumber(phoneNumber);
      final response = await _api.post(ApiConstants.sendLoginOtp, data: {
        'phone': normalizedPhone,
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
    String? role,
  }) async {
    try {
      final normalizedPhone = _normalizePhoneNumber(phoneNumber);
      final response = await _api.post(ApiConstants.verifyLoginOtp, data: {
        'phone': normalizedPhone,
        'otp': otp,
        if (role != null && role.isNotEmpty) 'role': role,
      });

      if (response['success'] == true) {
        final token = response['data']['token'];
        await _api.setToken(token);
        final user = User.fromJson(response['data']['user']);
        await persistUser(user);
        await FirebaseNotificationService.instance
            .registerDeviceToken(user: user);
        await IncomingOrderAlertService.instance.initialize();
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
    String? role,
  }) async {
    try {
      final normalizedPhone = _normalizePhoneNumber(phoneNumber);
      final response = await _api.post(ApiConstants.sendLoginOtp, data: {
        'phone': normalizedPhone,
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

  /// Validate reCAPTCHA score on backend
  Future<bool> validateRecaptchaScore({
    required String token,
    required double minScore,
  }) async {
    try {
      final response = await _api.post('/recaptcha/validate', data: {
        'token': token,
        'score_threshold': minScore,
      });
      
      return response['success'] == true && response['data']['valid'] == true;
    } catch (e) {
      print('Error validating reCAPTCHA: $e');
      return false;
    }
  }

  /// Normalize phone number to +91XXXXXXXXXX format (India)
  String _normalizePhoneNumber(String phone) {
    // Remove all non-digit characters
    String cleaned = phone.replaceAll(RegExp(r'\D'), '');
    
    // If already starts with 91, return with +91
    if (cleaned.startsWith('91')) {
      if (cleaned.length == 12) {
        return '+$cleaned';
      }
    }
    
    // If it's just 10 digits, add +91 prefix
    if (cleaned.length == 10) {
      return '+91$cleaned';
    }
    
    // If it already has +91 or +91 format, ensure it's correct
    if (phone.contains('+91')) {
      return phone.replaceAll(RegExp(r'\D'), '').length == 12 
        ? '+${phone.replaceAll(RegExp(r"\D"), "")}'
        : phone;
    }
    
    return '+91$cleaned';
  }

  /// Format phone number for display (e.g., +91 98765 43210)
  String formatPhoneForDisplay(String phone) {
    final normalized = _normalizePhoneNumber(phone);
    if (normalized.length == 13) {
      // +91XXXXXXXXXX format
      return '${normalized.substring(0, 3)} ${normalized.substring(3, 8)} ${normalized.substring(8)}';
    }
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

    final response = await _api.post(ApiConstants.register, data: data);

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance
          .registerDeviceToken(user: user);
      await IncomingOrderAlertService.instance.initialize();
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
    final response = await _api.post(ApiConstants.login, data: {
      'email': email,
      'password': password,
      if (role != null && role.isNotEmpty) 'role': role,
    });

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance
          .registerDeviceToken(user: user);
      await IncomingOrderAlertService.instance.initialize();
      if (user.isDriver || user.isRestaurantOwner) {
        await ForegroundServiceManager.startForegroundService();
      }
      return {'user': user, 'token': token};
    }
    throw Exception(response['message'] ?? 'Login failed');
  }

  Future<void> logout() async {
    try {
      await _api.post(ApiConstants.logout, data: const {'target_app': 'restaurant'});
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
