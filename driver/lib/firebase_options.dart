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
    apiKey: 'AIzaSyBi-pRKorYRAGhCip1CAe7LQ4FpHMFBXlY',
    authDomain: 'renjo-technology.firebaseapp.com',
    databaseURL: 'https://renjo-technology-default-rtdb.firebaseio.com',
    projectId: 'renjo-technology',
    storageBucket: 'renjo-technology.firebasestorage.app',
    messagingSenderId: '737787730111',
    appId: '1:737787730111:web:55d90999c0a8f468364e0a',
    measurementId: 'G-KE6C8VJK62',
  );

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyDs06Xh5QCDpQiy37L-RR0hrNqGPvx2paE',
    authDomain: 'yumma-458b0.firebaseapp.com',
    databaseURL: 'https://yumma-458b0-default-rtdb.firebaseio.com',
    projectId: 'yumma-458b0',
    storageBucket: 'yumma-458b0.firebasestorage.app',
    messagingSenderId: '596992936599',
    appId: '1:596992936599:android:9729edc404ec811ff6b7d2',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyDhZCVKvFQmun_wXebEFKgaP6zzjQn-c4I',
    appId: '1:596992936599:ios:c4ae54a8e54d38bbf6b7d2',
    messagingSenderId: '596992936599',
    projectId: 'yumma-458b0',
    storageBucket: 'yumma-458b0.firebasestorage.app',
    iosBundleId: 'com.adgraph.delivery',
    databaseURL: 'https://yumma-458b0-default-rtdb.firebaseio.com',
    authDomain: 'yumma-458b0.firebaseapp.com',
  );
}
