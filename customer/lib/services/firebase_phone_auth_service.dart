import 'dart:async';

import 'package:firebase_auth/firebase_auth.dart';
import '../utils/phone_number_utils.dart';

class FirebasePhoneAuthService {
  final FirebaseAuth _firebaseAuth = FirebaseAuth.instance;
  int? _resendToken;

  String _normalizePhoneNumber(String phone, {String? defaultMobileCountryCode}) {
    return PhoneNumberUtils.normalizeMobile(
      phone,
      countryCode: defaultMobileCountryCode,
      log: true,
    ).normalizedNumber;
  }

  String formatPhoneForDisplay(String phone, {String? countryCode}) {
    final normalized = _normalizePhoneNumber(phone, defaultMobileCountryCode: countryCode);
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
  }) async {
    final normalizedPhone = _normalizePhoneNumber(
      phone,
      defaultMobileCountryCode: countryCode,
    );

    final completer = Completer<String>();

    await _firebaseAuth.verifyPhoneNumber(
      phoneNumber: normalizedPhone,
      timeout: timeout,
      forceResendingToken: _resendToken,
      verificationCompleted: (credential) async {
        try {
          await _firebaseAuth.signInWithCredential(credential);
          if (!completer.isCompleted) {
            completer.complete(credential.verificationId ?? '');
          }
        } catch (_) {
          if (!completer.isCompleted) {
            completer.completeError('Automatic verification failed.');
          }
        }
      },
      verificationFailed: (exception) {
        if (!completer.isCompleted) {
          completer.completeError(exception.message ?? 'Phone verification failed');
        }
      },
      codeSent: (verificationId, resendToken) {
        _resendToken = resendToken;
        if (!completer.isCompleted) {
          completer.complete(verificationId);
        }
      },
      codeAutoRetrievalTimeout: (verificationId) {
        if (!completer.isCompleted) {
          completer.complete(verificationId);
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

    final result = await _firebaseAuth.signInWithCredential(credential);
    final idToken = await result.user?.getIdToken();
    if (idToken == null || idToken.isEmpty) {
      throw Exception('Unable to obtain Firebase ID token.');
    }
    return idToken;
  }

  Future<void> signOut() async {
    await _firebaseAuth.signOut();
  }
}
