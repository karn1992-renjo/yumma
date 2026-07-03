// lib/providers/auth_provider.dart
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
  bool get isRestaurantStaff => _currentUser?.isRestaurantStaff ?? false;
  bool get isRestaurantMember => _currentUser?.isRestaurantMember ?? false;
  bool get isDriver => _currentUser?.isDriver ?? false;
  bool get isAdmin => _currentUser?.isAdmin ?? false;
  bool get canUseCurrentApp {
    final user = _currentUser;
    if (user == null) return false;
    if (!AppConfig.isRoleLocked) return true;
    if (AppConfig.isDriverApp) return user.isDriver;
    if (AppConfig.isRestaurantApp) return user.isRestaurantMember;
    return user.isCustomer;
  }

  Future<bool> register({
    required String name,
    required String email,
    required String phone,
    required String password,
    required String passwordConfirmation,
    String? role,
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
        role: role,
      );
      _currentUser = result['user'];
      _syncCurrencySettings();
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
      _currentUser = result['user'];
      _syncCurrencySettings();
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
      _currentUser = result['user'];
      _syncCurrencySettings();
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
      return result;
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return null;
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
        _currentUser = result['user'];
        _syncCurrencySettings();
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
        _currentUser = result['user'];
        _syncCurrencySettings();
      }
      _setLoading(false);
      return Map<String, dynamic>.from(result);
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return null;
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

  void setUser(User? user) {
    _currentUser = user;
    _syncCurrencySettings();
    notifyListeners();
  }

  Future<void> logout() async {
    _setLoading(true);
    try {
      await _authService.logout();
    } finally {
      _currentUser = null;
      _setLoading(false);
    }
  }

  Future<void> loadUser() async {
    if (!await _authService.isLoggedIn()) return;

    final storedUser = await _authService.getStoredUser();
    if (storedUser != null) {
      _currentUser = storedUser;
      _syncCurrencySettings();
      notifyListeners();
    }

    try {
      final user = await _authService.getCurrentUser();
      _currentUser = user;
      _syncCurrencySettings();
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
      _currentUser = user;
      _syncCurrencySettings();
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

  void _syncCurrencySettings() {
    final user = _currentUser;
    if (user == null) return;
    setGlobalCurrencySymbol(user.currencySymbol);
    setGlobalCurrencyDecimals(user.currencyDecimals);
  }

  void _clearError() {
    _error = null;
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
