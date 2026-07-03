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

class AuthService {
  final ApiService _api = ApiService();

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
      'email': email,
      'phone': normalizedPhone,
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
    final data = {
      'phone': normalizedPhone,
      'flow': flow,
      if (role != null && role.isNotEmpty) 'role': role,
    };

    final response = await _sendLoginOtpRequest(data);

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Failed to send OTP');
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
    final normalizedPhone = _normalizePhone(phone);
    final response = await _api.post(
      ApiConstants.forgotPassword,
      data: {
        'phone': normalizedPhone,
        if (role != null && role.isNotEmpty) 'role': role,
      },
      includeAuth: false,
    );

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Failed to send reset OTP');
    }
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
