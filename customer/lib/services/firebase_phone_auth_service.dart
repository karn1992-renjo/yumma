import 'dart:async';

import 'package:firebase_auth/firebase_auth.dart';
import '../utils/phone_number_utils.dart';

class FirebaseOtpSendResult {
  const FirebaseOtpSendResult({
    required this.verificationId,
    this.firebaseIdToken,
  });

  final String verificationId;
  final String? firebaseIdToken;

  bool get autoVerified => firebaseIdToken?.isNotEmpty == true;
}

class FirebasePhoneAuthService {
  final FirebaseAuth _firebaseAuth = FirebaseAuth.instance;
  int? _resendToken;

  String _normalizePhoneNumber(String phone,
      {String? defaultMobileCountryCode}) {
    return PhoneNumberUtils.normalizeMobile(
      phone,
      countryCode: defaultMobileCountryCode,
      log: true,
    ).normalizedNumber;
  }

  String formatPhoneForDisplay(String phone, {String? countryCode}) {
    final normalized =
        _normalizePhoneNumber(phone, defaultMobileCountryCode: countryCode);
    if (normalized.length > 3) {
      final prefix = normalized.substring(0, 3);
      final rest = normalized.substring(3);
      return '$prefix $rest';
    }
    return normalized;
  }

  Future<String> sendOtp({
    required String phone,
    String? countryCode,
    Duration timeout = const Duration(seconds: 60),
    Future<void> Function(String firebaseIdToken)? onAutoVerified,
  }) async {
    final result = await sendOtpWithAutoVerification(
      phone: phone,
      countryCode: countryCode,
      timeout: timeout,
      onAutoVerified: onAutoVerified,
    );

    return result.verificationId;
  }

  Future<FirebaseOtpSendResult> sendOtpWithAutoVerification({
    required String phone,
    String? countryCode,
    Duration timeout = const Duration(seconds: 60),
    Future<void> Function(String firebaseIdToken)? onAutoVerified,
  }) async {
    final normalizedPhone = _normalizePhoneNumber(
      phone,
      defaultMobileCountryCode: countryCode,
    );

    final completer = Completer<FirebaseOtpSendResult>();

    await _firebaseAuth.verifyPhoneNumber(
      phoneNumber: normalizedPhone,
      timeout: timeout,
      forceResendingToken: _resendToken,
      verificationCompleted: (credential) async {
        try {
          final result = await _firebaseAuth.signInWithCredential(credential);
          final idToken = await result.user?.getIdToken(true);
          if (idToken == null || idToken.isEmpty) {
            throw Exception('Unable to obtain Firebase ID token.');
          }

          await onAutoVerified?.call(idToken);
          if (!completer.isCompleted) {
            completer.complete(
              FirebaseOtpSendResult(
                verificationId: credential.verificationId ?? '',
                firebaseIdToken: idToken,
              ),
            );
          }
        } catch (error) {
          if (!completer.isCompleted) {
            completer.completeError(error);
          }
        }
      },
      verificationFailed: (exception) {
        if (!completer.isCompleted) {
          completer.completeError(_phoneAuthFailureMessage(exception));
        }
      },
      codeSent: (verificationId, resendToken) {
        _resendToken = resendToken;
        if (!completer.isCompleted) {
          completer.complete(
            FirebaseOtpSendResult(verificationId: verificationId),
          );
        }
      },
      codeAutoRetrievalTimeout: (verificationId) {
        if (!completer.isCompleted) {
          completer.complete(
            FirebaseOtpSendResult(verificationId: verificationId),
          );
        }
      },
    );

    return completer.future;
  }

  Future<String> verifySmsCode({
    required String verificationId,
    required String smsCode,
  }) async {
    final credential = PhoneAuthProvider.credential(
      verificationId: verificationId,
      smsCode: smsCode,
    );

    late final UserCredential result;
    try {
      result = await _firebaseAuth.signInWithCredential(credential);
    } on FirebaseAuthException catch (error) {
      throw Exception(_phoneAuthFailureMessage(error));
    }

    final idToken = await result.user?.getIdToken();
    if (idToken == null || idToken.isEmpty) {
      throw Exception('Unable to obtain Firebase ID token.');
    }
    return idToken;
  }

  String _phoneAuthFailureMessage(FirebaseAuthException error) {
    final code = error.code.toLowerCase();
    final message = (error.message ?? '').trim();
    final lowerMessage = message.toLowerCase();

    if (code == 'invalid-app-credential' ||
        code == 'missing-app-credential' ||
        lowerMessage.contains('invalid request')) {
      return 'Phone verification could not start. Please check that production APNs is enabled for this iOS app in Firebase and Apple Developer settings.';
    }

    if (code == 'quota-exceeded' || code == 'too-many-requests') {
      return 'Too many OTP requests. Please wait and try again later.';
    }

    if (code == 'invalid-phone-number') {
      return PhoneNumberUtils.invalidMobileMessage;
    }

    if (code == 'invalid-verification-code') {
      return 'Invalid OTP. Please check the code and try again.';
    }

    if (code == 'session-expired') {
      return 'This OTP session expired. Please request a new code.';
    }

    return message.isNotEmpty
        ? message
        : 'Phone verification failed. Please try again.';
  }

  Future<void> signOut() async {
    await _firebaseAuth.signOut();
  }
}
