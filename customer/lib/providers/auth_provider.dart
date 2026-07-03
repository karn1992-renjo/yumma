import 'package:flutter/material.dart';

import '../config/app_config.dart';
import '../models/user.dart';
import '../services/auth_service.dart';
import '../utils/currency_utils.dart';

class AuthProvider extends ChangeNotifier {
  final AuthService _authService = AuthService();

  User? _currentUser;
  bool _isLoading = false;
  String? _error;

  User? get currentUser => _currentUser;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isAuthenticated => _currentUser != null;

  String? get userRole => _currentUser?.role;
  bool get isCustomer => _currentUser?.isCustomer ?? false;
  bool get isRestaurantOwner => _currentUser?.isRestaurantOwner ?? false;
  bool get isDriver => _currentUser?.isDriver ?? false;
  bool get isAdmin => _currentUser?.isAdmin ?? false;
  bool get canUseCurrentApp {
    final user = _currentUser;
    if (user == null) return false;
    if (!AppConfig.isRoleLocked) return true;
    if (AppConfig.isDriverApp) return user.isDriver;
    if (AppConfig.isRestaurantApp) return user.isRestaurantOwner;
    return user.isCustomer;
  }

  Future<bool> register({
    required String name,
    required String email,
    required String phone,
    String? password,
    String? passwordConfirmation,
    String? verifiedPhoneToken,
    String? role,
    String? referralCode,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      final result = await _authService.register(
        name: name,
        email: email,
        phone: phone,
        password: password,
        passwordConfirmation: passwordConfirmation,
        verifiedPhoneToken: verifiedPhoneToken,
        role: role,
        referralCode: referralCode,
      );
      _setCurrentUser(result['user']);
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<bool> login({
    required String email,
    required String password,
    String? role,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      final result = await _authService.login(
        email: email,
        password: password,
        role: role,
      );
      _setCurrentUser(result['user']);
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<bool> loginWithPhone({
    required String phone,
    required String firebaseIdToken,
    String? role,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      final result = await _authService.loginWithPhone(
        phone: phone,
        firebaseIdToken: firebaseIdToken,
        role: role,
      );
      _setCurrentUser(result['user']);
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<Map<String, dynamic>?> verifyFirebasePhone({
    required String phone,
    required String firebaseIdToken,
    required String flow,
    String? role,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      final result = await _authService.verifyFirebasePhone(
        phone: phone,
        firebaseIdToken: firebaseIdToken,
        flow: flow,
        role: role,
      );
      _setLoading(false);
      return Map<String, dynamic>.from(result);
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return null;
    }
  }

  Future<bool> loginWithSocial({
    required String provider,
    required String firebaseIdToken,
    String? role,
    String? displayName,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      final result = await _authService.loginWithSocial(
        provider: provider,
        firebaseIdToken: firebaseIdToken,
        role: role,
        displayName: displayName,
      );
      _setCurrentUser(result['user']);
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<bool> sendLoginOtp({
    required String phone,
    String flow = 'login',
    String? role,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      await _authService.sendLoginOtp(phone: phone, flow: flow, role: role);
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<bool> verifyLoginOtp({
    required String phone,
    required String otp,
    String flow = 'login',
    String? role,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      final result = await _authService.verifyLoginOtp(
        phone: phone,
        otp: otp,
        flow: flow,
        role: role,
      );
      if (flow == 'login') {
        _setCurrentUser(result['user']);
      }
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<Map<String, dynamic>?> verifyOtp({
    required String phone,
    required String otp,
    String flow = 'login',
    String? role,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      final result = await _authService.verifyLoginOtp(
        phone: phone,
        otp: otp,
        flow: flow,
        role: role,
      );
      if (flow == 'login') {
        _setCurrentUser(result['user']);
      }
      _setLoading(false);
      return Map<String, dynamic>.from(result);
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return null;
    }
  }

  Future<bool> requestPasswordReset({
    required String email,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      await _authService.requestPasswordReset(email: email);
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<Map<String, dynamic>?> getPhoneStatus({
    required String phone,
    String? role,
  }) async {
    _clearError();
    try {
      return await _authService.getPhoneStatus(phone: phone, role: role);
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return null;
    }
  }

  Future<bool> sendForgotPasswordOtp({
    required String phone,
    String? role,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      await _authService.sendForgotPasswordOtp(phone: phone, role: role);
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<bool> resetPasswordByPhone({
    required String phone,
    required String verifiedPhoneToken,
    required String password,
    required String passwordConfirmation,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      await _authService.resetPasswordByPhone(
        phone: phone,
        verifiedPhoneToken: verifiedPhoneToken,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  void setUser(User? user) {
    _setCurrentUser(user);
    notifyListeners();
  }

  Future<void> logout() async {
    _setLoading(true);
    try {
      await _authService.logout();
    } finally {
      _setCurrentUser(null);
      _setLoading(false);
    }
  }

  Future<void> loadUser({bool forceRefresh = false}) async {
    if (!await _authService.isLoggedIn()) return;

    if (_currentUser == null) {
      final storedUser = await _authService.getStoredUser();
      if (storedUser != null) {
        _setCurrentUser(storedUser);
        notifyListeners();
      }
    }

    if (!forceRefresh && _currentUser != null) {
      return;
    }

    try {
      final user = await _authService.getCurrentUser();
      _setCurrentUser(user);
      await _authService.persistUser(user);
      notifyListeners();
    } catch (e) {
      debugPrint('Load user error: $e');
    }
  }

  Future<bool> updateProfile({
    required String name,
    required String phone,
    String? profileImagePath,
  }) async {
    _setLoading(true);

    try {
      final user = await _authService.updateProfile(
        name: name,
        phone: phone,
        profileImagePath: profileImagePath,
      );
      _setCurrentUser(user);
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<bool> updatePassword({
    required String currentPassword,
    required String newPassword,
    required String newPasswordConfirmation,
  }) async {
    _setLoading(true);

    try {
      await _authService.updatePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
        newPasswordConfirmation: newPasswordConfirmation,
      );
      _setLoading(false);
      return true;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  void _setLoading(bool loading) {
    _isLoading = loading;
    notifyListeners();
  }

  void _setCurrentUser(User? user) {
    _currentUser = user;
    if (user != null) {
      setGlobalCurrencySymbol(user.currencySymbol);
      setGlobalCurrencyDecimals(user.currencyDecimals);
    }
  }

  void _clearError() {
    _error = null;
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
