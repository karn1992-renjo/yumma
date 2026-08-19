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
    apiKey: 'AIzaSyAmLGAn7rBEB02d-k3TFz71i7UQzmIPhwU',
    authDomain: 'renjo-technology-d0684.firebaseapp.com',
    databaseURL: 'https://renjo-technology-d0684-default-rtdb.firebaseio.com',
    projectId: 'renjo-technology-d0684',
    storageBucket: 'renjo-technology-d0684.firebasestorage.app',
    messagingSenderId: '435753341002',
    appId: '1:435753341002:web:6aa19e2487d7c6910816b3',
    measurementId: 'G-W9CNN2GE92',
  );

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyAmLGAn7rBEB02d-k3TFz71i7UQzmIPhwU',
    authDomain: 'renjo-technology-d0684.firebaseapp.com',
    databaseURL: 'https://renjo-technology-d0684-default-rtdb.firebaseio.com',
    projectId: 'renjo-technology-d0684',
    storageBucket: 'renjo-technology-d0684.firebasestorage.app',
    messagingSenderId: '435753341002',
    appId: '1:435753341002:android:fb3ea58b7ac3e73b0816b3',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyAmLGAn7rBEB02d-k3TFz71i7UQzmIPhwU',
    appId: '1:435753341002:ios:1f04a9fb55e902ec0816b3',
    messagingSenderId: '435753341002',
    projectId: 'renjo-technology-d0684',
    storageBucket: 'renjo-technology-d0684.firebasestorage.app',
    iosBundleId: 'com.renjo.restro.android',
    databaseURL: 'https://renjo-technology-d0684-default-rtdb.firebaseio.com',
  );
}
 