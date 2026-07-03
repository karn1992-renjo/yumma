import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:firebase_auth/firebase_auth.dart';
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

  Future<SocialAuthResult> signInWithGoogle({
    String? webClientId,
  }) async {
    final googleSignIn = GoogleSignIn(
      scopes: const ['email', 'profile'],
      serverClientId: _emptyToNull(webClientId),
    );

    final googleUser = await googleSignIn.signIn();
    if (googleUser == null) {
      throw Exception('Google sign in was cancelled.');
    }

    final googleAuth = await googleUser.authentication;
    final credential = GoogleAuthProvider.credential(
      accessToken: googleAuth.accessToken,
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
}
