import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/app_config.dart';
import '../../providers/auth_provider.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../utils/phone_number_utils.dart';

class FirebasePhoneLoginScreen extends StatefulWidget {
  const FirebasePhoneLoginScreen({
    super.key,
    this.initialPhone = '',
    this.countryCode = '+91',
    this.appName = '',
    this.role = 'customer',
  });

  final String initialPhone;
  final String countryCode;
  final String appName;
  final String role;

  @override
  State<FirebasePhoneLoginScreen> createState() => _FirebasePhoneLoginScreenState();
}

class _FirebasePhoneLoginScreenState extends State<FirebasePhoneLoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _phoneController = TextEditingController();
  final _otpController = TextEditingController();
  final FirebasePhoneAuthService _phoneAuthService = FirebasePhoneAuthService();

  String? _verificationId;
  bool _otpSent = false;
  bool _isSendingOtp = false;
  bool _isVerifyingOtp = false;
  int _resendCountdown = 0;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _phoneController.text = _initialLocalNumber(widget.initialPhone);
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  String _normalizedPhone() {
    return PhoneNumberUtils.normalizeMobile(
      _phoneController.text,
      countryCode: widget.countryCode,
      log: true,
    ).normalizedNumber;
  }

  String _initialLocalNumber(String phone) {
    try {
      return PhoneNumberUtils.localMobile(phone, countryCode: widget.countryCode);
    } on FormatException {
      return PhoneNumberUtils.sanitizedDigits(phone);
    }
  }

  Future<void> _sendOtp() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isSendingOtp = true;
      _errorMessage = null;
    });

    try {
      final verificationId = await _phoneAuthService.sendOtp(
        phone: _normalizedPhone(),
        countryCode: widget.countryCode,
      );

      if (!mounted) return;
      setState(() {
        _verificationId = verificationId;
        _otpSent = true;
        _resendCountdown = 60;
      });
      _startResendCountdown();
      _showMessage(
        'OTP sent to ${_phoneAuthService.formatPhoneForDisplay(_normalizedPhone(), countryCode: widget.countryCode)}',
      );
    } catch (e) {
      _setError(e);
    } finally {
      if (mounted) {
        setState(() {
          _isSendingOtp = false;
        });
      }
    }
  }

  Future<void> _verifyOtp() async {
    if (_verificationId == null || _otpController.text.trim().length != 6) {
      _showMessage('Please enter the 6-digit OTP.', isError: true);
      return;
    }

    final authProvider = context.read<AuthProvider>();

    setState(() {
      _isVerifyingOtp = true;
      _errorMessage = null;
    });

    try {
      final firebaseIdToken = await _phoneAuthService.verifySmsCode(
        verificationId: _verificationId!,
        smsCode: _otpController.text.trim(),
      );

      final success = await authProvider.loginWithPhone(
        phone: _normalizedPhone(),
        firebaseIdToken: firebaseIdToken,
        role: widget.role,
      );

      if (!mounted) return;
      if (!success) {
        _showMessage(authProvider.error ?? 'Login failed.', isError: true);
        return;
      }

      if (!authProvider.canUseCurrentApp) {
        await authProvider.logout();
        if (!mounted) return;
        _showMessage('Please login with a ${AppConfig.appRole} account.', isError: true);
        return;
      }

      Navigator.pushReplacementNamed(context, _homeRoute(authProvider));
    } catch (e) {
      _setError(e);
    } finally {
      if (mounted) {
        setState(() {
          _isVerifyingOtp = false;
        });
      }
    }
  }

  void _startResendCountdown() {
    Future.doWhile(() async {
      if (!mounted || _resendCountdown <= 0) return false;
      await Future.delayed(const Duration(seconds: 1));
      if (!mounted) return false;
      setState(() {
        _resendCountdown -= 1;
      });
      return _resendCountdown > 0;
    });
  }

  String _homeRoute(AuthProvider authProvider) {
    if (AppConfig.isRestaurantApp || authProvider.isRestaurantOwner) {
      return '/restaurant/dashboard';
    }
    if (AppConfig.isDriverApp || authProvider.isDriver) {
      return '/driver/dashboard';
    }
    return AppConfig.isRoleLocked ? '/home' : '/customer/home';
  }

  void _setError(Object error) {
    final message = error.toString().replaceAll('Exception: ', '');
    if (!mounted) return;
    setState(() {
      _errorMessage = message;
    });
    _showMessage(message, isError: true);
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : null,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isLoading = _isSendingOtp || _isVerifyingOtp;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.appName.isEmpty ? 'Phone Login' : widget.appName),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  _otpSent ? 'Verify your phone' : 'Continue with phone',
                  style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                Text(
                  _otpSent
                      ? 'Enter the 6-digit code sent to your mobile number.'
                      : 'We will verify your number with Firebase and then sign you in.',
                  style: const TextStyle(color: Color(0xFF6B7280)),
                ),
                const SizedBox(height: 24),
                TextFormField(
                  controller: _phoneController,
                  enabled: !isLoading && !_otpSent,
                  keyboardType: TextInputType.phone,
                  decoration: InputDecoration(
                    labelText: 'Mobile number',
                    prefixText: '${widget.countryCode} ',
                    border: const OutlineInputBorder(),
                  ),
                  validator: (value) {
                    return PhoneNumberUtils.validateIndianMobile(
                      value,
                      countryCode: widget.countryCode,
                    );
                  },
                ),
                if (_otpSent) ...[
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _otpController,
                    enabled: !isLoading,
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    decoration: const InputDecoration(
                      labelText: 'OTP',
                      border: OutlineInputBorder(),
                      counterText: '',
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed: isLoading || _resendCountdown > 0 ? null : _sendOtp,
                    child: Text(
                      _resendCountdown > 0
                          ? 'Resend OTP in ${_resendCountdown}s'
                          : 'Resend OTP',
                    ),
                  ),
                ],
                if (_errorMessage != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _errorMessage!,
                    style: const TextStyle(color: Colors.red),
                  ),
                ],
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: isLoading ? null : (_otpSent ? _verifyOtp : _sendOtp),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    child: Text(_otpSent ? 'Verify OTP' : 'Send OTP'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
