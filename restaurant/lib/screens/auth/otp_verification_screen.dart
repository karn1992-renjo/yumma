import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../services/firebase_phone_auth_service.dart';

class OtpVerificationScreen extends StatefulWidget {
  const OtpVerificationScreen({
    super.key,
    required this.phoneNumber,
    required this.countryCode,
    required this.appName,
    required this.role,
    this.flow = 'login',
    this.useFirebasePhoneAuth = false,
    this.initialFirebaseVerificationId,
  });

  final String phoneNumber;
  final String countryCode;
  final String appName;
  final String role;
  final String flow;
  final bool useFirebasePhoneAuth;
  final String? initialFirebaseVerificationId;

  @override
  State<OtpVerificationScreen> createState() => _OtpVerificationScreenState();
}

class _OtpVerificationScreenState extends State<OtpVerificationScreen> {
  static const _orange = Color(0xFFFF7A00);
  static const _green = Color(0xFF22C55E);
  static const _text = Color(0xFF111827);
  static const _subtext = Color(0xFF6B7280);
  static const _line = Color(0xFFE5E7EB);

  Timer? _timer;
  final FirebasePhoneAuthService _firebasePhoneAuthService =
      FirebasePhoneAuthService();
  int _secondsRemaining = 28;
  String _otp = '';
  String? _firebaseVerificationId;

  @override
  void initState() {
    super.initState();
    _firebaseVerificationId = widget.initialFirebaseVerificationId;
    _startTimer();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    _secondsRemaining = 28;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      if (_secondsRemaining == 0) {
        timer.cancel();
        return;
      }
      setState(() => _secondsRemaining -= 1);
    });
  }

  void _appendDigit(String digit) {
    if (_otp.length >= 6) return;
    setState(() => _otp += digit);
  }

  void _removeDigit() {
    if (_otp.isEmpty) return;
    setState(() => _otp = _otp.substring(0, _otp.length - 1));
  }

  Future<void> _verifyOtp() async {
    if (_otp.length < 4) {
      _showMessage('Enter the OTP sent to your mobile number.', isError: true);
      return;
    }

    final authProvider = context.read<AuthProvider>();
    Map<String, dynamic>? result;

    if (widget.useFirebasePhoneAuth) {
      if ((_firebaseVerificationId ?? '').isEmpty) {
        _showMessage('Please resend the OTP and try again.', isError: true);
        return;
      }

      try {
        final firebaseIdToken = await _firebasePhoneAuthService.verifySmsCode(
          verificationId: _firebaseVerificationId!,
          smsCode: _otp,
        );
        if (widget.flow == 'signup') {
          result = await authProvider.verifyFirebasePhone(
            phone: widget.phoneNumber,
            firebaseIdToken: firebaseIdToken,
            flow: widget.flow,
            role: widget.role,
          );

          if (!mounted) return;

          if (result == null) {
            _showMessage(
              authProvider.error ?? 'OTP verification failed',
              isError: true,
            );
            return;
          }
        } else {
          final success = await authProvider.loginWithPhone(
            phone: widget.phoneNumber,
            firebaseIdToken: firebaseIdToken,
            role: widget.role,
          );

          if (!mounted) return;

          if (!success) {
            _showMessage(
              authProvider.error ?? 'OTP verification failed',
              isError: true,
            );
            return;
          }
        }
      } catch (error) {
        if (!mounted) return;
        _showMessage(
          error.toString().replaceFirst('Exception: ', ''),
          isError: true,
        );
        return;
      }
    } else {
      result = await authProvider.verifyOtp(
        phone: widget.phoneNumber,
        otp: _otp,
        flow: widget.flow,
        role: widget.role,
      );

      if (!mounted) return;

      if (result == null) {
        _showMessage(
          authProvider.error ?? 'OTP verification failed',
          isError: true,
        );
        return;
      }
    }

    if (widget.flow == 'signup') {
      Navigator.of(context).pop(result);
      return;
    }

    if (!authProvider.canUseCurrentApp || !authProvider.isRestaurantMember) {
      await authProvider.logout();
      if (!mounted) return;
      _showMessage(
        'This mobile number is not linked to a restaurant account.',
        isError: true,
      );
      return;
    }

    Navigator.pushNamedAndRemoveUntil(
      context,
      '/restaurant/dashboard',
      (_) => false,
    );
  }

  Future<void> _resendOtp() async {
    final authProvider = context.read<AuthProvider>();
    if (widget.useFirebasePhoneAuth) {
      try {
        final verificationId = await _firebasePhoneAuthService.sendOtp(
          phone: widget.phoneNumber,
          countryCode: widget.countryCode,
        );

        if (!mounted) return;

        setState(() {
          _firebaseVerificationId = verificationId;
          _otp = '';
        });
      } catch (error) {
        if (!mounted) return;
        _showMessage(
          error.toString().replaceFirst('Exception: ', ''),
          isError: true,
        );
        return;
      }
    } else {
      final success = await authProvider.sendLoginOtp(
        phone: widget.phoneNumber,
        flow: widget.flow,
        role: widget.role,
      );

      if (!mounted) return;

      if (!success) {
        _showMessage(authProvider.error ?? 'Failed to resend OTP', isError: true);
        return;
      }

      setState(() => _otp = '');
    }

    _startTimer();
    _showMessage('A new OTP has been sent.');
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              physics: const ClampingScrollPhysics(),
              padding: EdgeInsets.fromLTRB(24, 12, 24, bottomInset + 18),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.arrow_back_ios_new_rounded),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                widget.flow == 'signup'
                                    ? 'Verify Mobile'
                                    : 'Verify OTP',
                                style: const TextStyle(
                                  fontSize: 38,
                                  fontWeight: FontWeight.w700,
                                  color: _text,
                                ),
                              ),
                              const SizedBox(height: 10),
                              const Text(
                                'We have sent a 6-digit OTP to',
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w400,
                                  color: _subtext,
                                  height: 1.35,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                widget.phoneNumber,
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w700,
                                  color: _text,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Container(
                          width: 92,
                          height: 92,
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFF7ED),
                            borderRadius: BorderRadius.circular(24),
                          ),
                          child: Stack(
                            alignment: Alignment.center,
                            children: [
                              const Icon(
                                Icons.mark_email_read_rounded,
                                size: 54,
                                color: _orange,
                              ),
                              Positioned(
                                right: 10,
                                bottom: 10,
                                child: Container(
                                  width: 26,
                                  height: 26,
                                  decoration: const BoxDecoration(
                                    color: _green,
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(
                                    Icons.check,
                                    size: 16,
                                    color: Colors.white,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 30),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: List.generate(6, (index) {
                        final isActive = index == _otp.length;
                        final digit = index < _otp.length ? _otp[index] : '';
                        return Container(
                          width: 48,
                          height: 72,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: isActive ? _orange : _line,
                              width: isActive ? 1.4 : 1,
                            ),
                          ),
                          child: Text(
                            digit,
                            style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w700,
                              color: _text,
                            ),
                          ),
                        );
                      }),
                    ),
                    const SizedBox(height: 18),
                    Center(
                      child: Text.rich(
                        TextSpan(
                          text: 'Resend OTP in ',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w500,
                            color: _subtext,
                          ),
                          children: [
                            TextSpan(
                              text:
                                  '00:${_secondsRemaining.toString().padLeft(2, '0')}',
                              style: const TextStyle(
                                color: _text,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1FBF4),
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: const Color(0xFFD8F1DE)),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.shield_outlined, color: _green, size: 28),
                          SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Your verification code is secure',
                                  style: TextStyle(
                                    fontSize: 17,
                                    fontWeight: FontWeight.w700,
                                    color: _text,
                                  ),
                                ),
                                SizedBox(height: 4),
                                Text(
                                  'Never share OTP with anyone',
                                  style: TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w500,
                                    color: _subtext,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 28),
                    _buildKeypad(),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: Consumer<AuthProvider>(
                        builder: (context, auth, _) {
                          return DecoratedBox(
                            decoration: BoxDecoration(
                              gradient: const LinearGradient(
                                colors: [Color(0xFFEF4F5F), Color(0xFFFF7A00)],
                              ),
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: ElevatedButton(
                              onPressed: auth.isLoading ? null : _verifyOtp,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: Colors.transparent,
                                shadowColor: Colors.transparent,
                                minimumSize: const Size.fromHeight(56),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(18),
                                ),
                              ),
                              child: Text(
                                auth.isLoading
                                    ? 'Verifying...'
                                    : widget.flow == 'signup'
                                        ? 'Verify & Continue'
                                        : 'Verify OTP',
                                style: const TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.white,
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    const SizedBox(height: 8),
                    Center(
                      child: TextButton(
                        onPressed: _secondsRemaining == 0 ? _resendOtp : null,
                        child: const Text('Resend OTP'),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _buildKeypad() {
    final rows = const [
      ['1', '2', '3'],
      ['4', '5', '6'],
      ['7', '8', '9'],
    ];

    return Column(
      children: [
        for (final row in rows)
          Row(
            children: row
                .map(
                  (digit) => Expanded(
                    child: Padding(
                      padding: const EdgeInsets.all(6),
                      child: _keyButton(
                        label: digit,
                        onTap: () => _appendDigit(digit),
                      ),
                    ),
                  ),
                )
                .toList(),
          ),
        Row(
          children: [
            const Expanded(child: SizedBox()),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(6),
                child: _keyButton(label: '0', onTap: () => _appendDigit('0')),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(6),
                child: _keyButton(
                  icon: Icons.backspace_outlined,
                  onTap: _removeDigit,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _keyButton({
    String? label,
    IconData? icon,
    required VoidCallback onTap,
  }) {
    return InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Ink(
        height: 62,
        decoration: BoxDecoration(
          color: const Color(0xFFF8F9FB),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Center(
          child: icon != null
              ? Icon(icon, color: _text)
              : Text(
                  label ?? '',
                  style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w700,
                    color: _text,
                  ),
                ),
        ),
      ),
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.redAccent : _green,
      ),
    );
  }
}
