import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

class SocialAuthResult {
  const SocialAuthResult({
    required this.provider,
    required this.firebaseIdToken,
    this.displayName,
  });

  final String provider;
  final String firebaseIdToken;
  final String? displayName;
}

class SocialAuthService {
  SocialAuthService({FirebaseAuth? firebaseAuth})
      : _firebaseAuth = firebaseAuth ?? FirebaseAuth.instance;

  final FirebaseAuth _firebaseAuth;
  bool _googleInitialized = false;
  String? _googleServerClientId;

  Future<SocialAuthResult> signInWithGoogle({
    String? webClientId,
  }) async {
    try {
      return await _signInWithGoogleClient(
        _emptyToNull(webClientId),
      );
    } on GoogleSignInException catch (error) {
      throw Exception(_googleSignInMessage(error));
    } on PlatformException catch (error) {
      throw Exception(_googlePlatformMessage(error));
    } on FirebaseAuthException catch (error) {
      throw Exception(_firebaseAuthMessage(error));
    }
  }

  Future<SocialAuthResult> _signInWithGoogleClient(
    String? serverClientId,
  ) async {
    final googleSignIn = GoogleSignIn.instance;
    await _initializeGoogleSignIn(
      googleSignIn,
      serverClientId: serverClientId,
    );

    await googleSignIn.signOut();
    if (!googleSignIn.supportsAuthenticate()) {
      throw Exception('Google sign in is not available on this platform.');
    }

    final googleUser = await googleSignIn.authenticate(
      scopeHint: const ['email', 'profile'],
    );
    final googleAuth = googleUser.authentication;
    final credential = GoogleAuthProvider.credential(
      idToken: googleAuth.idToken,
    );

    final userCredential = await _firebaseAuth.signInWithCredential(credential);
    final idToken = await userCredential.user?.getIdToken(true);
    if (idToken == null || idToken.isEmpty) {
      throw Exception('Google sign in did not return a Firebase token.');
    }

    return SocialAuthResult(
      provider: 'google',
      firebaseIdToken: idToken,
      displayName: userCredential.user?.displayName ?? googleUser.displayName,
    );
  }

  Future<void> _initializeGoogleSignIn(
    GoogleSignIn googleSignIn, {
    required String? serverClientId,
  }) async {
    if (_googleInitialized && _googleServerClientId == serverClientId) return;

    await googleSignIn.initialize(serverClientId: serverClientId);
    _googleInitialized = true;
    _googleServerClientId = serverClientId;
  }

  Future<SocialAuthResult> signInWithApple() async {
    final rawNonce = _generateNonce();
    final nonce = _sha256(rawNonce);

    final appleCredential = await SignInWithApple.getAppleIDCredential(
      scopes: const [
        AppleIDAuthorizationScopes.email,
        AppleIDAuthorizationScopes.fullName,
      ],
      nonce: nonce,
    );

    final identityToken = appleCredential.identityToken;
    if (identityToken == null || identityToken.isEmpty) {
      throw Exception('Apple sign in did not return an identity token.');
    }

    final oauthCredential = OAuthProvider('apple.com').credential(
      idToken: identityToken,
      rawNonce: rawNonce,
    );

    final userCredential =
        await _firebaseAuth.signInWithCredential(oauthCredential);
    final idToken = await userCredential.user?.getIdToken(true);
    if (idToken == null || idToken.isEmpty) {
      throw Exception('Apple sign in did not return a Firebase token.');
    }

    return SocialAuthResult(
      provider: 'apple',
      firebaseIdToken: idToken,
      displayName: _appleDisplayName(appleCredential) ??
          userCredential.user?.displayName,
    );
  }

  String? _appleDisplayName(AuthorizationCredentialAppleID credential) {
    final parts = [
      credential.givenName,
      credential.familyName,
    ].where((part) => part != null && part.trim().isNotEmpty);

    final name = parts.join(' ').trim();
    return name.isEmpty ? null : name;
  }

  String _generateNonce([int length = 32]) {
    const charset =
        '0123456789ABCDEFGHIJKLMNOPQRSTUVXYZabcdefghijklmnopqrstuvwxyz-._';
    final random = Random.secure();

    return List.generate(
      length,
      (_) => charset[random.nextInt(charset.length)],
    ).join();
  }

  String _sha256(String input) {
    final bytes = utf8.encode(input);
    final digest = sha256.convert(bytes);

    return digest.toString();
  }

  String? _emptyToNull(String? value) {
    final trimmed = value?.trim();
    return trimmed == null || trimmed.isEmpty ? null : trimmed;
  }

  String _googleSignInMessage(GoogleSignInException error) {
    final details = [
      error.code.name,
      error.description,
      error.details?.toString(),
    ].whereType<String>().join(' ').toLowerCase();

    if (error.code == GoogleSignInExceptionCode.canceled ||
        details.contains('cancel')) {
      if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
        return 'Google sign in could not continue after account selection. Check Firebase Android OAuth package name, SHA-1/SHA-256, and web client ID.';
      }

      return 'Google sign in was cancelled. Please choose a Google account to continue.';
    }

    if (error.code == GoogleSignInExceptionCode.clientConfigurationError ||
        details.contains('developer_error') ||
        details.contains('status code: 10') ||
        details.contains('api exception: 10')) {
      return 'Google sign in is not configured for this Android signing key. Add the release or Play App Signing SHA-1/SHA-256 in Firebase and download google-services.json again.';
    }

    if (error.code == GoogleSignInExceptionCode.providerConfigurationError) {
      return 'Google sign in is not enabled or configured correctly in Firebase.';
    }

    if (details.contains('network')) {
      return 'Google sign in needs an internet connection. Please try again.';
    }

    return error.description?.trim().isNotEmpty == true
        ? error.description!
        : 'Google sign in failed. Please try again.';
  }

  String _googlePlatformMessage(PlatformException error) {
    final details = [
      error.code,
      error.message,
      error.details?.toString(),
    ].whereType<String>().join(' ').toLowerCase();

    if (details.contains('1001') ||
        details.contains('cancel') ||
        details.contains('12501')) {
      return 'Google sign in was cancelled. Please choose a Google account to continue.';
    }

    if (details.contains('developer_error') ||
        details.contains('status code: 10') ||
        details.contains('api exception: 10')) {
      return 'Google sign in is not configured for this Android signing key. Add the release or Play App Signing SHA-1/SHA-256 in Firebase and download google-services.json again.';
    }

    if (details.contains('12500')) {
      return 'Google sign in failed. Enable Google as a sign-in provider in Firebase Authentication.';
    }

    if (details.contains('network') || details.contains('status code: 7')) {
      return 'Google sign in needs an internet connection. Please try again.';
    }

    return error.message?.trim().isNotEmpty == true
        ? error.message!
        : 'Google sign in failed. Please try again.';
  }

  String _firebaseAuthMessage(FirebaseAuthException error) {
    final details = [
      error.code,
      error.message,
    ].whereType<String>().join(' ').toLowerCase();

    if (details.contains('configuration-not-found') ||
        details.contains('configuration_not_found')) {
      return 'Google sign in is not enabled in Firebase Authentication.';
    }

    if (details.contains('network')) {
      return 'Google sign in needs an internet connection. Please try again.';
    }

    return error.message?.trim().isNotEmpty == true
        ? error.message!
        : 'Google sign in failed. Please try again.';
  }
}
