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
    apiKey: 'AIzaSyB6Ixat1fY3Knl7mNiGl4zuNASMxHv23Qg',
    authDomain: 'yumma-458b0.firebaseapp.com',
    databaseURL: 'https://yumma-458b0-default-rtdb.firebaseio.com',
    projectId: 'yumma-458b0',
    storageBucket: 'yumma-458b0.firebasestorage.app',
    messagingSenderId: '596992936599',
    appId: '1:596992936599:web:abb75eb61f127ec4364e0a',
    measurementId: 'G-SPEPT9CS1B',
  );

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyDs06Xh5QCDpQiy37L-RR0hrNqGPvx2paE',
    authDomain: 'yumma-458b0.firebaseapp.com',
    databaseURL: 'https://yumma-458b0-default-rtdb.firebaseio.com',
    projectId: 'yumma-458b0',
    storageBucket: 'yumma-458b0.firebasestorage.app',
    messagingSenderId: '596992936599',
    appId: '1:596992936599:android:995c0be592e5f10ff6b7d2',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyDs06Xh5QCDpQiy37L-RR0hrNqGPvx2paE',
    appId: '1:596992936599:ios:8f0a1b2c3d4e5f6g726703',
    messagingSenderId: '596992936599',
    projectId: 'yumma-458b0',
    storageBucket: 'yumma-458b0.firebasestorage.app',
    iosBundleId: 'com.adgraph.yumma',
    databaseURL: 'https://yumma-458b0-default-rtdb.firebaseio.com',
  );
}
