import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sms_autofill/sms_autofill.dart';

import '../../config/app_config.dart';
import '../../models/app_branding.dart';
import '../../models/user.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/phone_number_utils.dart';
import '../../widgets/auth/auth_lottie_accent.dart';
import '../../widgets/auth/brand_mark.dart';

class ModernLoginScreen extends StatefulWidget {
  const ModernLoginScreen({super.key});

  @override
  State<ModernLoginScreen> createState() => _ModernLoginScreenState();
}

class _ModernLoginScreenState extends State<ModernLoginScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _otpController = TextEditingController();

  final FirebasePhoneAuthService _phoneAuthService = FirebasePhoneAuthService();
  String? _verificationId;
  bool _isSendingOtp = false;
  bool _isVerifyingOtp = false;
  bool _isResendAvailable = false;
  int _resendCountdown = 0;
  String? _authError;

  AppBranding _branding = AppBranding.fallback();
  bool _useOtp = false;
  bool _otpSent = false;
  bool _isPasswordVisible = false;
  late String _selectedRole = AppConfig.isDriverApp
      ? 'driver'
      : AppConfig.isRestaurantApp
          ? 'restaurant'
          : 'customer';

  @override
  void initState() {
    super.initState();
    _loadBranding();
  }

  @override
  void dispose() {
    SmsAutoFill().unregisterListener();
    _emailController.dispose();
    _passwordController.dispose();
    _phoneController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() {
      _branding = branding;
    });
  }

  String get _defaultCountryCode {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    return authProvider.currentUser?.defaultMobileCountryCode ??
        User.defaultMobileCountryCodeFallback;
  }

  Future<void> _startResendCountdown() async {
    if (_resendCountdown > 0) return;
    setState(() {
      _resendCountdown = 60;
      _isResendAvailable = false;
    });

    while (_resendCountdown > 0 && mounted) {
      await Future.delayed(const Duration(seconds: 1));
      if (!mounted) return;
      setState(() {
        _resendCountdown -= 1;
      });
    }

    if (!mounted) return;
    setState(() {
      _isResendAvailable = true;
    });
  }

  Future<void> _requestOtp() async {
    if (_isSendingOtp) return;
    late final String phone;
    try {
      phone = PhoneNumberUtils.normalizeMobile(
        _phoneController.text,
        countryCode: _defaultCountryCode,
        log: true,
      ).normalizedNumber;
    } on FormatException catch (error) {
      _showMessage(error.message, isError: true);
      return;
    }

    setState(() {
      _isSendingOtp = true;
      _authError = null;
    });

    try {
      final verificationId = await _phoneAuthService.sendOtp(
        phone: phone,
        countryCode: _defaultCountryCode,
      );

      if (!mounted) return;
      await SmsAutoFill().listenForCode();
      setState(() {
        _otpSent = true;
        _verificationId = verificationId;
        _isResendAvailable = false;
      });
      _startResendCountdown();
      _showMessage('OTP sent to ${_phoneAuthService.formatPhoneForDisplay(phone, countryCode: _defaultCountryCode)}');
    } catch (e) {
      setState(() {
        _authError = e.toString().replaceAll('Exception: ', '');
      });
      _showMessage(_authError ?? 'Failed to send OTP', isError: true);
    } finally {
      if (mounted) {
        setState(() {
          _isSendingOtp = false;
        });
      }
    }
  }

  Future<void> _verifyOtp() async {
    final authProvider = context.read<AuthProvider>();
    late final String phone;
    try {
      phone = PhoneNumberUtils.normalizeMobile(
        _phoneController.text,
        countryCode: _defaultCountryCode,
        log: true,
      ).normalizedNumber;
    } on FormatException catch (error) {
      _showMessage(error.message, isError: true);
      return;
    }
    final code = _otpController.text.trim();

    if (code.length < 6 || _verificationId == null) {
      _showMessage('Please enter the 6-digit OTP sent to your phone', isError: true);
      return;
    }

    setState(() {
      _isVerifyingOtp = true;
      _authError = null;
    });

    try {
      final firebaseIdToken = await _phoneAuthService.verifySmsCode(
        verificationId: _verificationId!,
        smsCode: code,
      );

      final success = await authProvider.loginWithPhone(
        phone: phone,
        firebaseIdToken: firebaseIdToken,
        role: _selectedRole,
      );

      if (!mounted) return;
      if (!success) {
        _showMessage(authProvider.error ?? 'Login failed', isError: true);
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
      setState(() {
        _authError = e.toString().replaceAll('Exception: ', '');
      });
      _showMessage(_authError ?? 'OTP verification failed', isError: true);
    } finally {
      if (mounted) {
        setState(() {
          _isVerifyingOtp = false;
        });
      }
    }
  }

  Future<void> _handleLogin() async {
    if (_isSendingOtp || _isVerifyingOtp) return;
    final authProvider = context.read<AuthProvider>();
    bool success;

    if (_useOtp) {
      if (!_otpSent) {
        await _requestOtp();
        return;
      }
      if (_otpController.text.trim().length < 6) {
        _showMessage('Please enter the 6-digit OTP', isError: true);
        return;
      }
      await _verifyOtp();
      return;
    } else {
      if (!_formKey.currentState!.validate()) return;
      success = await authProvider.login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
        role: _selectedRole,
      );
    }

    if (!mounted) return;

    if (!success) {
      final error = authProvider.error ?? 'Login failed';
      if (_isAccountMissingError(error)) {
        Navigator.pushNamed(
          context,
          '/register',
          arguments: {
            'phone': _phoneController.text.trim(),
            'email': _emailController.text.trim(),
          },
        );
        return;
      }
      _showMessage(error, isError: true);
      return;
    }

    if (!authProvider.canUseCurrentApp) {
      await authProvider.logout();
      if (!mounted) return;
      _showMessage(
        'Please login with a ${AppConfig.appRole} account.',
        isError: true,
      );
      return;
    }

    final roleMatches = switch (_selectedRole) {
      'driver' => authProvider.isDriver,
      'restaurant' => authProvider.isRestaurantOwner,
      _ => authProvider.isCustomer,
    };

    if (!roleMatches) {
      await authProvider.logout();
      if (!mounted) return;
      _showMessage(
        'This account is not a ${_roleLabel(_selectedRole).toLowerCase()} account.',
        isError: true,
      );
      return;
    }

    Navigator.pushReplacementNamed(context, _homeRoute(authProvider));
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

  bool _isAccountMissingError(String error) {
    final normalized = error.toLowerCase();
    return normalized.contains('no account found') ||
        normalized.contains('no matching account') ||
        normalized.contains('not registered');
  }

  String _roleLabel(String role) {
    switch (role) {
      case 'driver':
        return 'Driver';
      case 'restaurant':
        return 'Restaurant';
      default:
        return 'Customer';
    }
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : FoodFlowTheme.orange,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  InputDecoration _underlineField({
    required String label,
    String? hint,
    Widget? suffix,
  }) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      suffixIcon: suffix,
      floatingLabelBehavior: FloatingLabelBehavior.always,
      filled: false,
      contentPadding: const EdgeInsets.only(top: 4, bottom: 12),
      enabledBorder: const UnderlineInputBorder(
        borderSide: BorderSide(color: Color(0xFFB3ACA7)),
      ),
      focusedBorder: const UnderlineInputBorder(
        borderSide: BorderSide(color: Color(0xFFFF5A1F), width: 1.4),
      ),
      border: const UnderlineInputBorder(),
      labelStyle: const TextStyle(
        color: Color(0xFF2C2727),
        fontWeight: FontWeight.w600,
        fontSize: 12,
      ),
      hintStyle: const TextStyle(
        color: Color(0xFFAAA19B),
        fontSize: 13,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        return Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(28, 20, 28, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      BrandMark(branding: _branding, size: 30),
                      const Spacer(),
                      const AuthLottieAccent(height: 104),
                    ],
                  ),
                  const SizedBox(height: 26),
                  Expanded(
                    child: SingleChildScrollView(
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Welcome\nto ${_branding.displayName}',
                              style: const TextStyle(
                                fontSize: 44,
                                height: 0.98,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFF423E3E),
                              ),
                            ),
                            const SizedBox(height: 30),
                            if (!AppConfig.isRoleLocked) ...[
                              _RoleSelector(
                                selectedRole: _selectedRole,
                                onChanged: (role) {
                                  setState(() {
                                    _selectedRole = role;
                                    _otpSent = false;
                                  });
                                },
                              ),
                              const SizedBox(height: 18),
                            ],
                            _AuthModeToggle(
                              useOtp: _useOtp,
                              onChanged: (useOtp) {
                                setState(() {
                                  _useOtp = useOtp;
                                  _otpSent = false;
                                });
                              },
                            ),
                            const SizedBox(height: 18),
                            if (_useOtp) ...[
                              TextFormField(
                                controller: _phoneController,
                                keyboardType: TextInputType.phone,
                                decoration: _underlineField(
                                  label: 'Phone',
                                  hint: 'Enter your phone number',
                                ),
                                validator: (value) {
                                  return PhoneNumberUtils.validateIndianMobile(
                                    value,
                                    countryCode: _defaultCountryCode,
                                  );
                                },
                              ),
                              if (_otpSent) ...[
                                const SizedBox(height: 16),
                                PinFieldAutoFill(
                                  controller: _otpController,
                                  codeLength: 6,
                                  decoration: UnderlineDecoration(
                                    textStyle: const TextStyle(
                                      fontSize: 20,
                                      color: Color(0xFF222222),
                                      fontWeight: FontWeight.w700,
                                    ),
                                    colorBuilder: FixedColorBuilder(
                                      const Color(0xFFB3ACA7),
                                    ),
                                  ),
                                  currentCode: _otpController.text,
                                  onCodeChanged: (code) {
                                    if (code != null) {
                                      _otpController.text = code;
                                      if (code.length == 6) {
                                        _verifyOtp();
                                      }
                                    }
                                  },
                                ),
                                const SizedBox(height: 12),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      _resendCountdown > 0
                                          ? 'Resend in ${_resendCountdown}s'
                                          : 'Didn\'t receive the code?',
                                      style: const TextStyle(
                                        fontSize: 12,
                                        color: Color(0xFF7A7A7A),
                                      ),
                                    ),
                                    TextButton(
                                      onPressed: _isResendAvailable && !_isSendingOtp
                                          ? () async {
                                              await _requestOtp();
                                            }
                                          : null,
                                      child: const Text('Resend'),
                                    ),
                                  ],
                                ),
                                if (_authError != null) ...[
                                  const SizedBox(height: 12),
                                  Text(
                                    _authError!,
                                    style: const TextStyle(
                                      color: Colors.red,
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ],
                            ] else ...[
                              TextFormField(
                                controller: _emailController,
                                keyboardType: TextInputType.emailAddress,
                                decoration: _underlineField(
                                  label: 'Email',
                                  hint: 'Enter your email',
                                ),
                                validator: (value) {
                                  if (value == null || value.trim().isEmpty) {
                                    return 'Email is required';
                                  }
                                  if (!value.contains('@')) {
                                    return 'Enter a valid email';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 18),
                              TextFormField(
                                controller: _passwordController,
                                obscureText: !_isPasswordVisible,
                                decoration: _underlineField(
                                  label: 'Password',
                                  hint: 'Enter your password',
                                  suffix: IconButton(
                                    onPressed: () {
                                      setState(() {
                                        _isPasswordVisible = !_isPasswordVisible;
                                      });
                                    },
                                    icon: Icon(
                                      _isPasswordVisible
                                          ? Icons.visibility_off_outlined
                                          : Icons.visibility_outlined,
                                      color: const Color(0xFF8A817C),
                                    ),
                                  ),
                                ),
                                validator: (value) {
                                  if (value == null || value.isEmpty) {
                                    return 'Password is required';
                                  }
                                  if (value.length < 6) {
                                    return 'Password must be at least 6 characters';
                                  }
                                  return null;
                                },
                              ),
                              const SizedBox(height: 10),
                              Align(
                                alignment: Alignment.centerLeft,
                                child: TextButton(
                                  onPressed: () => Navigator.pushNamed(
                                    context,
                                    '/forgot-password',
                                  ),
                                  style: TextButton.styleFrom(
                                    padding: EdgeInsets.zero,
                                    minimumSize: Size.zero,
                                    tapTargetSize:
                                        MaterialTapTargetSize.shrinkWrap,
                                  ),
                                  child: const Text(
                                    'Forgot your password?',
                                    style: TextStyle(
                                      color: Color(0xFFFF5A1F),
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                            const SizedBox(height: 20),
                            Row(
                              children: const [
                                Expanded(
                                  child: Divider(color: Color(0xFFE6DDD8)),
                                ),
                                Padding(
                                  padding: EdgeInsets.symmetric(horizontal: 10),
                                  child: Text(
                                    'or continue with',
                                    style: TextStyle(
                                      color: Color(0xFF9B928C),
                                      fontSize: 11,
                                    ),
                                  ),
                                ),
                                Expanded(
                                  child: Divider(color: Color(0xFFE6DDD8)),
                                ),
                              ],
                            ),
                            const SizedBox(height: 18),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: const [
                                _SocialCircle(label: 'G'),
                                SizedBox(width: 14),
                                _SocialCircle(label: 'f'),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed:
                          auth.isLoading || _isSendingOtp || _isVerifyingOtp
                              ? null
                              : _handleLogin,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFFF5A1F),
                        foregroundColor: Colors.white,
                        minimumSize: const Size.fromHeight(54),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                      child: auth.isLoading || _isSendingOtp || _isVerifyingOtp
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(_useOtp
                              ? (_otpSent ? 'Verify OTP' : 'Send OTP')
                              : 'Sign in'),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Center(
                    child: TextButton(
                      onPressed: () => Navigator.pushNamed(context, '/register'),
                      child: const Text.rich(
                        TextSpan(
                          text: "Don't have an account? ",
                          style: TextStyle(color: Color(0xFF8A817C)),
                          children: [
                            TextSpan(
                              text: 'Sign up',
                              style: TextStyle(
                                color: Color(0xFFFF5A1F),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

class _SocialCircle extends StatelessWidget {
  final String label;

  const _SocialCircle({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: const Color(0xFFF3F0EE),
        shape: BoxShape.circle,
        border: Border.all(color: const Color(0xFFE2D9D4)),
      ),
      alignment: Alignment.center,
      child: Text(
        label,
        style: const TextStyle(
          color: Color(0xFF6D6661),
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _AuthModeToggle extends StatelessWidget {
  final bool useOtp;
  final ValueChanged<bool> onChanged;

  const _AuthModeToggle({
    required this.useOtp,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 236,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F3F1),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFD8CDC7)),
      ),
      child: Row(
        children: [
          Expanded(
            child: _AuthModeToggleChip(
              label: 'Password',
              selected: !useOtp,
              icon: Icons.check_rounded,
              onTap: () => onChanged(false),
            ),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: _AuthModeToggleChip(
              label: 'OTP',
              selected: useOtp,
              onTap: () => onChanged(true),
            ),
          ),
        ],
      ),
    );
  }
}

class _AuthModeToggleChip extends StatelessWidget {
  final String label;
  final bool selected;
  final IconData? icon;
  final VoidCallback onTap;

  const _AuthModeToggleChip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? const Color(0xFFDDF3DD) : Colors.white,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (selected && icon != null) ...[
                Icon(icon, size: 18, color: const Color(0xFF1C8A43)),
                const SizedBox(width: 6),
              ],
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.fade,
                  softWrap: false,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: const Color(0xFF2E2A2A),
                    fontSize: 13,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RoleSelector extends StatelessWidget {
  final String selectedRole;
  final ValueChanged<String> onChanged;

  const _RoleSelector({
    required this.selectedRole,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    const roles = [
      ('customer', 'Customer'),
      ('restaurant', 'Restaurant'),
      ('driver', 'Driver'),
    ];

    return Row(
      children: roles.map((role) {
        final selected = selectedRole == role.$1;
        return Expanded(
          child: Padding(
            padding: EdgeInsets.only(right: role == roles.last ? 0 : 8),
            child: InkWell(
              onTap: () => onChanged(role.$1),
              borderRadius: BorderRadius.circular(999),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: selected
                      ? const Color(0xFFFFEFE9)
                      : const Color(0xFFF7F3F1),
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(
                    color: selected
                        ? const Color(0xFFFF5A1F)
                        : const Color(0xFFE5DDD8),
                  ),
                ),
                child: Text(
                  role.$2,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: selected
                        ? const Color(0xFFFF5A1F)
                        : const Color(0xFF7D736E),
                  ),
                ),
              ),
            ),
          ),
        );
      }).toList(),
    );
  }
}
