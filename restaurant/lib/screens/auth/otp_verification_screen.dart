import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sms_autofill/sms_autofill.dart';

import '../../providers/auth_provider.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../theme/foodflow_theme.dart';

class OtpVerificationScreen extends StatefulWidget {
  const OtpVerificationScreen({
    super.key,
    required this.phoneNumber,
    required this.countryCode,
    required this.appName,
    required this.role,
    this.flow = 'login',
    this.useFirebasePhoneAuth = false,
    this.otpServiceProvider = '',
    this.initialFirebaseVerificationId,
  });

  final String phoneNumber;
  final String countryCode;
  final String appName;
  final String role;
  final String flow;
  final bool useFirebasePhoneAuth;
  final String otpServiceProvider;
  final String? initialFirebaseVerificationId;

  @override
  State<OtpVerificationScreen> createState() => _OtpVerificationScreenState();
}

class _OtpVerificationScreenState extends State<OtpVerificationScreen>
    with CodeAutoFill {
  Timer? _timer;
  final FirebasePhoneAuthService _firebasePhoneAuthService =
      FirebasePhoneAuthService();
  int _secondsRemaining = 28;
  String _otp = '';
  String? _firebaseVerificationId;
  bool _autoSubmittedOtp = false;

  int get _otpLength {
    if (widget.useFirebasePhoneAuth) return 6;
    return widget.otpServiceProvider.trim().toLowerCase() == 'msg91' ? 4 : 6;
  }

  @override
  void initState() {
    super.initState();
    _firebaseVerificationId = widget.initialFirebaseVerificationId;
    _startTimer();
    _listenForOtpCode();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _completeMsg91AutoVerificationIfReady();
    });
  }

  Future<bool> _completeMsg91AutoVerificationIfReady() async {
    if (!mounted || widget.useFirebasePhoneAuth) return false;

    final authProvider = context.read<AuthProvider>();
    final canComplete = authProvider.hasCompletedMsg91WidgetVerification(
      phone: widget.phoneNumber,
      flow: widget.flow,
      role: widget.role,
    );
    if (!canComplete) return false;

    setState(() {
      _autoSubmittedOtp = true;
      _otp = ''.padLeft(_otpLength, '0');
    });
    await _verifyOtp(allowMsg91AutoVerification: true);
    return true;
  }

  @override
  void dispose() {
    _timer?.cancel();
    cancel();
    unregisterListener();
    super.dispose();
  }

  @override
  void codeUpdated() {
    final autoFilledOtp = code?.replaceAll(RegExp(r'\D'), '') ?? '';
    if (autoFilledOtp.isEmpty || !mounted) return;

    setState(() {
      _otp = autoFilledOtp.length > _otpLength
          ? autoFilledOtp.substring(0, _otpLength)
          : autoFilledOtp;
    });

    if (_otp.length == _otpLength && !_autoSubmittedOtp) {
      _autoSubmittedOtp = true;
      _verifyOtp();
    }
  }

  void _startTimer() {
    _timer?.cancel();
    if (mounted) {
      setState(() => _secondsRemaining = 28);
    } else {
      _secondsRemaining = 28;
    }
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
    if (_otp.length >= _otpLength) return;
    setState(() {
      _autoSubmittedOtp = false;
      _otp += digit;
    });
  }

  void _removeDigit() {
    if (_otp.isEmpty) return;
    setState(() {
      _autoSubmittedOtp = false;
      _otp = _otp.substring(0, _otp.length - 1);
    });
  }

  Future<void> _verifyOtp({bool allowMsg91AutoVerification = false}) async {
    if (_otp.length != _otpLength && !allowMsg91AutoVerification) {
      _showMessage(
          'Enter the $_otpLength-digit OTP sent to your mobile number.',
          isError: true);
      return;
    }

    final submittedOtp = allowMsg91AutoVerification
        ? ''.padLeft(_otpLength, '0')
        : _otp;
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
          smsCode: submittedOtp,
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
        otp: submittedOtp,
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
          _autoSubmittedOtp = false;
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
        _showMessage(authProvider.error ?? 'Failed to resend OTP',
            isError: true);
        return;
      }

      if (await _completeMsg91AutoVerificationIfReady()) return;

      setState(() {
        _autoSubmittedOtp = false;
        _otp = '';
      });
    }

    _startTimer();
    _listenForOtpCode();
    _showMessage('A new OTP has been sent.');
  }

  void _listenForOtpCode() {
    listenForCode(smsCodeRegexPattern: '\\b\\d{$_otpLength}\\b');
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              physics: const ClampingScrollPhysics(),
              padding: EdgeInsets.fromLTRB(16, 14, 16, bottomInset + 18),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _OtpHeader(
                      phoneNumber: widget.phoneNumber,
                      isSignup: widget.flow == 'signup',
                      onBack: () => Navigator.pop(context),
                    ),
                    const SizedBox(height: 14),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: _panelDecoration(),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Enter verification code',
                            style: TextStyle(
                              color: FoodFlowTheme.ink,
                              fontSize: 16,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Sent to ${widget.phoneNumber}',
                            style: const TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 16),
                          _OtpBoxes(otp: _otp, length: _otpLength),
                          const SizedBox(height: 14),
                          _TimerRow(
                            secondsRemaining: _secondsRemaining,
                            onResend:
                                _secondsRemaining == 0 ? _resendOtp : null,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    _SecurityNotice(),
                    const SizedBox(height: 14),
                    _buildKeypad(),
                    const SizedBox(height: 12),
                    Consumer<AuthProvider>(
                      builder: (context, auth, _) {
                        return SizedBox(
                          width: double.infinity,
                          height: 48,
                          child: FilledButton.icon(
                            onPressed: auth.isLoading ? null : () => _verifyOtp(),
                            icon: const Icon(Icons.verified_rounded, size: 18),
                            label: Text(
                              auth.isLoading
                                  ? 'Verifying...'
                                  : widget.flow == 'signup'
                                      ? 'Verify & Continue'
                                      : 'Verify OTP',
                            ),
                            style: FilledButton.styleFrom(
                              backgroundColor: FoodFlowTheme.orange,
                              foregroundColor: Colors.white,
                              textStyle: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w900,
                              ),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                            ),
                          ),
                        );
                      },
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
                      padding: const EdgeInsets.all(5),
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
                padding: const EdgeInsets.all(5),
                child: _keyButton(label: '0', onTap: () => _appendDigit('0')),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(5),
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
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Ink(
        height: 52,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: FoodFlowTheme.line),
        ),
        child: Center(
          child: icon != null
              ? const Icon(Icons.backspace_outlined, color: FoodFlowTheme.ink)
              : Text(
                  label ?? '',
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: FoodFlowTheme.ink,
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
        backgroundColor: isError ? FoodFlowTheme.danger : FoodFlowTheme.success,
      ),
    );
  }
}

class _OtpHeader extends StatelessWidget {
  const _OtpHeader({
    required this.phoneNumber,
    required this.isSignup,
    required this.onBack,
  });

  final String phoneNumber;
  final bool isSignup;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: _panelDecoration(),
      child: Row(
        children: [
          SizedBox(
            width: 38,
            height: 38,
            child: IconButton(
              onPressed: onBack,
              icon: const Icon(Icons.arrow_back_rounded, size: 20),
              color: FoodFlowTheme.ink,
              padding: EdgeInsets.zero,
            ),
          ),
          const SizedBox(width: 8),
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: FoodFlowTheme.orange.withOpacity(0.12),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              Icons.mark_email_read_rounded,
              color: FoodFlowTheme.orange,
              size: 23,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isSignup ? 'Verify Mobile' : 'Verify OTP',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  phoneNumber,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _OtpBoxes extends StatelessWidget {
  const _OtpBoxes({required this.otp, required this.length});

  final String otp;
  final int length;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: List.generate(length, (index) {
        final isActive = index == otp.length;
        final digit = index < otp.length ? otp[index] : '';
        return Expanded(
          child: Padding(
            padding: EdgeInsets.only(right: index == length - 1 ? 0 : 6),
            child: Container(
              height: 54,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: isActive
                    ? FoodFlowTheme.orange.withOpacity(0.06)
                    : FoodFlowTheme.canvas,
                borderRadius: BorderRadius.circular(13),
                border: Border.all(
                  color: isActive ? FoodFlowTheme.orange : FoodFlowTheme.line,
                  width: isActive ? 1.4 : 1,
                ),
              ),
              child: Text(
                digit,
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: FoodFlowTheme.ink,
                ),
              ),
            ),
          ),
        );
      }),
    );
  }
}

class _TimerRow extends StatelessWidget {
  const _TimerRow({required this.secondsRemaining, required this.onResend});

  final int secondsRemaining;
  final VoidCallback? onResend;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            secondsRemaining == 0
                ? 'Code expired?'
                : 'Resend in 00:${secondsRemaining.toString().padLeft(2, '0')}',
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        TextButton(
          onPressed: onResend,
          style: TextButton.styleFrom(
            foregroundColor: FoodFlowTheme.orange,
            visualDensity: VisualDensity.compact,
            textStyle: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
          child: const Text('Resend OTP'),
        ),
      ],
    );
  }
}

class _SecurityNotice extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: FoodFlowTheme.success.withOpacity(0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: FoodFlowTheme.success.withOpacity(0.18)),
      ),
      child: const Row(
        children: [
          Icon(Icons.shield_outlined, color: FoodFlowTheme.success, size: 22),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'Never share OTP with anyone.',
              style: TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

BoxDecoration _panelDecoration() {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(14),
    border: Border.all(color: FoodFlowTheme.line),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withOpacity(0.035),
        blurRadius: 14,
        offset: const Offset(0, 6),
      ),
    ],
  );
}
