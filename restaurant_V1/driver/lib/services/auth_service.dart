import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_service.dart';
import 'foreground_service_manager.dart';
import 'incoming_order_alert_service.dart';
import 'notification_service.dart';
import 'order_alert_startup_permission_service.dart';
import '../config/api_constants.dart';
import 'package:http/http.dart' as http;
import '../models/user.dart';

class AuthService {
  final ApiService _api = ApiService();

  Future<Map<String, dynamic>> getPhoneStatus({
    required String phone,
    String? role,
  }) async {
    final response = await _api.post(ApiConstants.phoneStatus, data: {
      'phone': phone,
      if (role != null && role.isNotEmpty) 'role': role,
    });

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
  }) async {
    final data = {
      'name': name,
      'email': email,
      'phone': phone,
      if (password != null && password.isNotEmpty) 'password': password,
      if (passwordConfirmation != null && passwordConfirmation.isNotEmpty)
        'password_confirmation': passwordConfirmation,
      if (verifiedPhoneToken != null && verifiedPhoneToken.isNotEmpty)
        'verified_phone_token': verifiedPhoneToken,
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
        await OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: user.isDriver,
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
        await OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: user.isDriver,
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
    final response = await _api.post(ApiConstants.loginWithPhone, data: {
      'phone': phone,
      'firebase_id_token': firebaseIdToken,
      if (role != null && role.isNotEmpty) 'role': role,
    });

    if (response['success'] == true) {
      final token = response['data']['token'];
      await _api.setToken(token);
      final user = User.fromJson(response['data']['user']);
      await persistUser(user);
      await FirebaseNotificationService.instance.registerDeviceToken(user: user);
      await IncomingOrderAlertService.instance.initialize();
      if (user.isDriver || user.isRestaurantOwner) {
        await ForegroundServiceManager.startForegroundService();
        await OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: user.isDriver,
        );
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
    final response = await _api.post(ApiConstants.verifyFirebasePhone, data: {
      'phone': phone,
      'firebase_id_token': firebaseIdToken,
      'flow': flow,
      if (role != null && role.isNotEmpty) 'role': role,
    });

    if (response['success'] == true) {
      return Map<String, dynamic>.from(response['data'] ?? const {});
    }

    throw Exception(response['message'] ?? 'Phone verification failed');
  }

  Future<void> sendLoginOtp({
    required String phone,
    String flow = 'login',
    String? role,
  }) async {
    final response = await _api.post(ApiConstants.sendLoginOtp, data: {
      'phone': phone,
      'flow': flow,
      if (role != null && role.isNotEmpty) 'role': role,
    });

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Failed to send OTP');
    }
  }

  Future<Map<String, dynamic>> verifyLoginOtp({
    required String phone,
    required String otp,
    String flow = 'login',
    String? role,
  }) async {
    final response = await _api.post(ApiConstants.verifyLoginOtp, data: {
      'phone': phone,
      'otp': otp,
      'flow': flow,
      if (role != null && role.isNotEmpty) 'role': role,
    });

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
      await IncomingOrderAlertService.instance.initialize();
      if (user.isDriver || user.isRestaurantOwner) {
        await ForegroundServiceManager.startForegroundService();
        await OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: user.isDriver,
        );
      }
      return {'user': user, 'token': token};
    }

    throw Exception(response['message'] ?? 'OTP verification failed');
  }

  Future<void> logout() async {
    try {
      await _api.post(ApiConstants.logout);
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
      request.fields['phone'] = phone;

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
    } else {
      final response = await _api.put(ApiConstants.updateProfile, data: {
        'name': name,
        'phone': phone,
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
