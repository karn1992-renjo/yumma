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
    apiKey: 'AIzaSyBi-pRKorYRAGhCip1CAe7LQ4FpHMFBXlY',
    authDomain: 'renjo-technology.firebaseapp.com',
    databaseURL: 'https://renjo-technology-default-rtdb.firebaseio.com',
    projectId: 'renjo-technology',
    storageBucket: 'renjo-technology.firebasestorage.app',
    messagingSenderId: '737787730111',
    appId: '1:737787730111:web:55d90999c0a8f468364e0a',
    measurementId: 'G-KE6C8VJK62',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyBi-pRKorYRAGhCip1CAe7LQ4FpHMFBXlY',
    appId: '1:737787730111:web:55d90999c0a8f468364e0a',
    messagingSenderId: '737787730111',
    projectId: 'renjo-technology',
    storageBucket: 'renjo-technology.firebasestorage.app',
    iosBundleId: 'com.adgraph.yumma_delivery',
    databaseURL: 'https://renjo-technology-default-rtdb.firebaseio.com',
    authDomain: 'renjo-technology.firebaseapp.com',
    measurementId: 'G-KE6C8VJK62',
  );
}
