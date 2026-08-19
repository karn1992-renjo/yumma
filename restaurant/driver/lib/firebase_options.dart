import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      case TargetPlatform.macOS:
      case TargetPlatform.windows:
      case TargetPlatform.linux:
      case TargetPlatform.fuchsia:
        return web;
    }
  }

  static const FirebaseOptions web = FirebaseOptions(
    apiKey: 'PLACE_API_KEY_HERE',
    authDomain: 'PLACE_AUTH_DOMAIN_HERE',
    databaseURL: 'PLACE_DATABASE_URL_HERE',
    projectId: 'Yumma-458b0',
    storageBucket: 'PLACE_STORAGE_BUCKET_HERE',
    messagingSenderId: '596992936599',
    appId: '1:596992936599:web:8d6fa739975dfcf6f6b7d2',
    measurementId: 'G-P05GYQKEG3',
  );

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'PLACE_API_KEY_HERE',
    authDomain: 'PLACE_AUTH_DOMAIN_HERE',
    databaseURL: 'PLACE_DATABASE_URL_HERE',
    projectId: 'Yumma-458b0',
    storageBucket: 'PLACE_STORAGE_BUCKET_HERE',
    messagingSenderId: '596992936599',
    appId: '1:596992936599:android:9729edc404ec811ff6b7d2',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'PLACE_API_KEY_HERE',
    appId: '1:596992936599:ios:8f0a1b2c3d4e5f6g726703',
    messagingSenderId: '596992936599',
    projectId: 'Yumma-458b0',
    storageBucket: 'PLACE_STORAGE_BUCKET_HERE',
    iosBundleId: 'com.example.app',
    databaseURL: 'PLACE_DATABASE_URL_HERE',
  );
}
