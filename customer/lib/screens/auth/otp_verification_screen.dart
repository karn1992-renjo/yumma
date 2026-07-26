import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:sms_autofill/sms_autofill.dart';

import '../../config/app_config.dart';
import '../../providers/auth_provider.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../theme/foodflow_theme.dart';
import 'a1paso_auth_widgets.dart';

import 'package:food_delivery_customer/utils/app_text.dart';

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
  static const _text = A1PasoAuthColors.text;
  static const _subtext = A1PasoAuthColors.subtext;
  static const _line = A1PasoAuthColors.border;

  final _otpController = TextEditingController();
  final _otpFocusNode = FocusNode();
  final FirebasePhoneAuthService _firebasePhoneAuthService =
      FirebasePhoneAuthService();

  Timer? _timer;
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
    listenForCode();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _otpFocusNode.requestFocus();
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _otpController.dispose();
    _otpFocusNode.dispose();
    cancel();
    super.dispose();
  }

  @override
  void codeUpdated() {
    final autoFilledOtp = code?.replaceAll(RegExp(r'\D'), '') ?? '';
    if (autoFilledOtp.isEmpty || !mounted) return;

    final normalizedOtp = autoFilledOtp.length > _otpLength
        ? autoFilledOtp.substring(0, _otpLength)
        : autoFilledOtp;
    _setOtp(normalizedOtp);

    if (_otp.length == _otpLength && !_autoSubmittedOtp) {
      _autoSubmittedOtp = true;
      _verifyOtp();
    }
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

  void _setOtp(String value) {
    final digits = value.replaceAll(RegExp(r'\D'), '');
    final normalized =
        digits.length > _otpLength ? digits.substring(0, _otpLength) : digits;
    setState(() {
      _autoSubmittedOtp = false;
      _otp = normalized;
      if (_otpController.text != normalized) {
        _otpController.value = TextEditingValue(
          text: normalized,
          selection: TextSelection.collapsed(offset: normalized.length),
        );
      }
    });
  }

  Future<void> _verifyOtp() async {
    if (_otp.length < _otpLength) {
      _showMessage(appText('Enter the OTP sent to your mobile number.'),
          isError: true);
      return;
    }

    final authProvider = context.read<AuthProvider>();
    Map<String, dynamic>? result;

    if (widget.useFirebasePhoneAuth) {
      if ((_firebaseVerificationId ?? '').isEmpty) {
        _showMessage(appText('Please resend the OTP and try again.'),
            isError: true);
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
        } else {
          final success = await authProvider.loginWithPhone(
            phone: widget.phoneNumber,
            firebaseIdToken: firebaseIdToken,
            role: widget.role,
          );

          if (!mounted) return;

          if (!success) {
            _showMessage(
              authProvider.error ?? appText('OTP verification failed'),
              isError: true,
            );
            return;
          }
        }

        if (!mounted) return;

        if (widget.flow == 'signup' && result == null) {
          _showMessage(
            authProvider.error ?? appText('OTP verification failed'),
            isError: true,
          );
          return;
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
          authProvider.error ?? appText('OTP verification failed'),
          isError: true,
        );
        return;
      }
    }

    if (widget.flow == 'signup') {
      Navigator.of(context).pop(result);
      return;
    }

    if (!authProvider.canUseCurrentApp || !authProvider.isCustomer) {
      await authProvider.logout();
      if (!mounted) return;
      _showMessage(
        appText('This mobile number is not linked to a customer account.'),
        isError: true,
      );
      return;
    }

    Navigator.pushNamedAndRemoveUntil(
      context,
      _homeRoute(authProvider),
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
          _otpController.clear();
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
        _showMessage(
          authProvider.error ?? appText('Failed to resend OTP'),
          isError: true,
        );
        return;
      }

      setState(() {
        _autoSubmittedOtp = false;
        _otp = '';
        _otpController.clear();
      });
    }

    _startTimer();
    listenForCode();
    _otpFocusNode.requestFocus();
    _showMessage(appText('A new OTP has been sent.'));
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

  String get _formattedPhone {
    final countryDigits = widget.countryCode.replaceAll(RegExp(r'\D'), '');
    final allDigits = widget.phoneNumber.replaceAll(RegExp(r'\D'), '');
    final localDigits = countryDigits.isNotEmpty &&
            allDigits.startsWith(countryDigits) &&
            allDigits.length > countryDigits.length
        ? allDigits.substring(countryDigits.length)
        : allDigits;

    if (localDigits.length == 10) {
      return '${widget.countryCode} ${localDigits.substring(0, 2)} ${localDigits.substring(2, 6)} ${localDigits.substring(6)}';
    }
    if (localDigits.length > 6) {
      return '${widget.countryCode} ${localDigits.substring(0, 3)} ${localDigits.substring(3, 6)} ${localDigits.substring(6)}';
    }
    return '${widget.countryCode} $localDigits'.trim();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      resizeToAvoidBottomInset: true,
      backgroundColor: Colors.white,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxHeight < 760;
            final primary = FoodFlowTheme.brandPrimary(context);
            return Stack(
              children: [
                SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: const EdgeInsets.fromLTRB(28, 0, 28, 98),
                  child: ConstrainedBox(
                    constraints:
                        BoxConstraints(minHeight: constraints.maxHeight - 106),
                    child: Column(
                      children: [
                        Align(
                          alignment: Alignment.centerLeft,
                          child: IconButton(
                            onPressed: () => Navigator.pop(context),
                            icon: const Icon(Icons.arrow_back_ios_new_rounded),
                            color: Colors.black,
                            iconSize: 24,
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(
                              minWidth: 42,
                              minHeight: 42,
                            ),
                          ),
                        ),
                        SizedBox(height: compact ? 6 : 16),
                        Image.asset(
                          'assets/images/logo.png',
                          width: compact ? 188 : 214,
                          height: compact ? 90 : 104,
                          fit: BoxFit.contain,
                        ),
                        SizedBox(height: compact ? 18 : 26),
                        A1PasoShieldBadge(size: compact ? 104 : 114),
                        SizedBox(height: compact ? 18 : 26),
                        const Text(
                          'Verify Your Number',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: _text,
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                            height: 1,
                          ),
                        ),
                        const SizedBox(height: 14),
                        Text(
                          appText('Enter the $_otpLength-digit code sent to'),
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: _subtext,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                            height: 1.1,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              _formattedPhone,
                              style: const TextStyle(
                                color: _text,
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(width: 10),
                            GestureDetector(
                              onTap: () => Navigator.pop(context),
                              child: Text(
                                'Edit',
                                style: TextStyle(
                                  color: primary,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ],
                        ),
                        SizedBox(height: compact ? 24 : 30),
                        _OtpEntry(
                          otp: _otp,
                          controller: _otpController,
                          focusNode: _otpFocusNode,
                          onChanged: _setOtp,
                          length: _otpLength,
                        ),
                        SizedBox(height: compact ? 22 : 28),
                        _resendRow(),
                      ],
                    ),
                  ),
                ),
                Positioned(
                  left: 28,
                  right: 28,
                  bottom: 14,
                  child: Consumer<AuthProvider>(
                    builder: (context, auth, _) {
                      return _OtpThreeDButton(
                        onPressed: auth.isLoading ? null : _verifyOtp,
                        child: Text(
                          auth.isLoading
                              ? appText('Verifying...')
                              : appText('Verify & Continue'),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _resendRow() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Text(
          'Didn\'t receive the code?',
          style: TextStyle(
            color: _subtext,
            fontSize: 13,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(width: 9),
        GestureDetector(
          onTap: _secondsRemaining == 0 ? _resendOtp : null,
          child: Text(
            'Resend',
            style: TextStyle(
              color: FoodFlowTheme.brandPrimary(context),
              fontSize: 13,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        if (_secondsRemaining > 0) ...[
          const SizedBox(width: 6),
          const Text(
            'in',
            style: TextStyle(
              color: _subtext,
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            '00:${_secondsRemaining.toString().padLeft(2, '0')}',
            style: const TextStyle(
              color: _subtext,
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ],
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(appText(message)),
        backgroundColor:
            isError ? Colors.red : FoodFlowTheme.brandPrimary(context),
      ),
    );
  }
}

class _OtpThreeDButton extends StatefulWidget {
  const _OtpThreeDButton({
    required this.child,
    required this.onPressed,
  });

  final Widget child;
  final VoidCallback? onPressed;

  @override
  State<_OtpThreeDButton> createState() => _OtpThreeDButtonState();
}

class _OtpThreeDButtonState extends State<_OtpThreeDButton> {
  bool _pressed = false;
  bool get _enabled => widget.onPressed != null;

  @override
  Widget build(BuildContext context) {
    final y = _pressed && _enabled ? 3.0 : 0.0;
    final primary = FoodFlowTheme.brandPrimary(context);

    return GestureDetector(
      onTapDown: _enabled ? (_) => setState(() => _pressed = true) : null,
      onTapCancel: _enabled ? () => setState(() => _pressed = false) : null,
      onTapUp: _enabled
          ? (_) {
              setState(() => _pressed = false);
              widget.onPressed?.call();
            }
          : null,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 110),
        curve: Curves.easeOut,
        width: double.infinity,
        height: 56,
        padding: EdgeInsets.only(top: y),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8),
          boxShadow: [
            BoxShadow(
              color: primary.withOpacity(_enabled ? 0.24 : 0.10),
              blurRadius: _pressed ? 7 : 17,
              offset: Offset(0, _pressed ? 4 : 10),
            ),
          ],
        ),
        child: DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                Color.lerp(primary, Colors.white, _enabled ? 0.12 : 0.36)!,
                primary,
                Color.lerp(primary, Colors.black, _enabled ? 0.10 : 0.02)!,
              ],
              stops: const [0, 0.58, 1],
            ),
          ),
          child: Stack(
            children: [
              Positioned(
                left: 1,
                right: 1,
                top: 1,
                height: 14,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    borderRadius:
                        const BorderRadius.vertical(top: Radius.circular(7)),
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Colors.white.withOpacity(0.42),
                        Colors.white.withOpacity(0)
                      ],
                    ),
                  ),
                ),
              ),
              Center(child: widget.child),
            ],
          ),
        ),
      ),
    );
  }
}

class _OtpEntry extends StatelessWidget {
  const _OtpEntry({
    required this.otp,
    required this.controller,
    required this.focusNode,
    required this.onChanged,
    this.length = 6,
  });

  final String otp;
  final TextEditingController controller;
  final FocusNode focusNode;
  final ValueChanged<String> onChanged;
  final int length;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        const gap = 9.0;
        final boxWidth = ((constraints.maxWidth - gap * (length - 1)) / length)
            .clamp(38.0, 50.0);
        return GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: focusNode.requestFocus,
          child: Stack(
            alignment: Alignment.center,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: List.generate(length, (index) {
                  final digit = index < otp.length ? otp[index] : '';
                  final active = index == otp.length && otp.length < length;
                  return _OtpBox(
                    width: boxWidth,
                    digit: digit,
                    active: active,
                  );
                }),
              ),
              Positioned.fill(
                child: Opacity(
                  opacity: 0.01,
                  child: TextField(
                    controller: controller,
                    focusNode: focusNode,
                    autofocus: true,
                    keyboardType: TextInputType.number,
                    textInputAction: TextInputAction.done,
                    maxLength: length,
                    enableSuggestions: false,
                    autocorrect: false,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    decoration: const InputDecoration(
                      counterText: '',
                      border: InputBorder.none,
                    ),
                    style: const TextStyle(color: Colors.transparent),
                    cursorColor: Colors.transparent,
                    onChanged: onChanged,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _OtpBox extends StatelessWidget {
  const _OtpBox({
    required this.width,
    required this.digit,
    required this.active,
  });

  final double width;
  final String digit;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: 58,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: active
              ? FoodFlowTheme.brandPrimary(context)
              : A1PasoAuthColors.border,
          width: active ? 1.5 : 1.2,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.035),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: digit.isNotEmpty
          ? Text(
              digit,
              style: const TextStyle(
                color: A1PasoAuthColors.text,
                fontSize: 20,
                fontWeight: FontWeight.w700,
              ),
            )
          : active
              ? Container(
                  width: 2,
                  height: 27,
                  decoration: BoxDecoration(
                    color: A1PasoAuthColors.text,
                    borderRadius: BorderRadius.circular(2),
                  ),
                )
              : null,
    );
  }
}
