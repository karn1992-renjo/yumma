import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../firebase_options.dart';
import '../models/user.dart';
import 'incoming_order_alert_service.dart';
import 'navigation_service.dart';
import 'sound_service.dart';

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
    }
    final data = FirebaseNotificationService.normalizeNotificationData(
      message.data,
    );
    if (FirebaseNotificationService.isIncomingOrderPayload(data)) {
      await FirebaseNotificationService.persistPendingOrderData(message.data);
      await FirebaseNotificationService.showOrderNotificationFromMessage(
        message,
      );
    }
  } catch (e, stackTrace) {
    debugPrint('Background FCM notification skipped: $e');
    debugPrintStack(stackTrace: stackTrace);
  }
}

class FirebaseNotificationService {
  FirebaseNotificationService._internal();

  static final FirebaseNotificationService instance =
      FirebaseNotificationService._internal();
  static Map<String, dynamic>? _pendingOverlayData;
  static int _pendingOverlayAttempts = 0;

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  String? _deviceToken;
  bool _isInitialized = false;

  Future<void> initialize() async {
    if (_isInitialized) return;

    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(
          options: DefaultFirebaseOptions.currentPlatform,
        );
      }
    } catch (e) {
      debugPrint('Firebase initialization failed: $e');
    }

    const androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings();
    await _localNotifications.initialize(
      settings: const InitializationSettings(
        android: androidSettings,
        iOS: iosSettings,
      ),
      onDidReceiveNotificationResponse: (response) {
        _handleNotificationPayload(response.payload);
      },
      onDidReceiveBackgroundNotificationResponse: _handleBackgroundTap,
    );

    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    debugPrint('FCM permission status: ${settings.authorizationStatus}');

    _deviceToken = await _messaging.getToken();
    debugPrint('FCM device token: $_deviceToken');

    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    FirebaseMessaging.onMessage.listen((message) {
      debugPrint('FCM onMessage: ${message.messageId}');
      _handleForegroundMessage(message);
    });

    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      debugPrint('FCM onMessageOpenedApp: ${message.messageId}');
      try {
        _presentOrderFromData(_safeDataMap(message.data));
      } catch (e, stackTrace) {
        debugPrint('FCM open message skipped: $e');
        debugPrintStack(stackTrace: stackTrace);
      }
    });

    final launchDetails =
        await _localNotifications.getNotificationAppLaunchDetails();
    final launchPayload = launchDetails?.notificationResponse?.payload;
    if (launchDetails?.didNotificationLaunchApp == true &&
        launchPayload != null &&
        launchPayload.isNotEmpty) {
      _handleNotificationPayload(launchPayload);
    }

    final initialMessage = await _messaging.getInitialMessage();
    if (initialMessage != null) {
      _presentOrderFromData(_safeDataMap(initialMessage.data));
    }

    _messaging.onTokenRefresh.listen((token) {
      _deviceToken = token;
      registerDeviceToken();
    });

    _isInitialized = true;
    await registerDeviceToken();
  }

  @pragma('vm:entry-point')
  static void _handleBackgroundTap(NotificationResponse response) {
    // The foreground isolate handles the same payload during app launch via
    // getNotificationAppLaunchDetails. This callback keeps Android delivery
    // reliable when the plugin dispatches the tap before initialization.
  }

  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    var handledByOverlay = false;
    try {
      handledByOverlay = await _showInAppOrderOverlay(message);
    } catch (e, stackTrace) {
      debugPrint('FCM in-app overlay skipped: $e');
      debugPrintStack(stackTrace: stackTrace);
    }

    if (handledByOverlay) return;

    _showForegroundNotification(message)
        .catchError((Object e, StackTrace stackTrace) {
      debugPrint('FCM foreground notification skipped: $e');
      debugPrintStack(stackTrace: stackTrace);
    });
  }

  Future<void> registerDeviceToken({User? user}) async {
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp(
          options: DefaultFirebaseOptions.currentPlatform,
        );
      }

      _deviceToken ??= await _messaging.getToken();
      if (_deviceToken == null || _deviceToken!.isEmpty) {
        debugPrint('FCM token registration skipped: token unavailable');
        return;
      }

      await IncomingOrderAlertService.instance.registerDeviceToken(
        token: _deviceToken,
        user: user,
      );
      debugPrint('FCM token registered with backend');
    } catch (e) {
      debugPrint('Failed to register FCM token: $e');
    }
  }

  Future<void> _showForegroundNotification(RemoteMessage message) async {
    if (_isOrderMessage(message)) {
      await showOrderNotificationFromMessage(message);
      return;
    }

    final notification = message.notification;
    final android = message.notification?.android;

    if (notification == null) return;

    const androidChannel = AndroidNotificationChannel(
      'default_notification_channel',
      'Default Notifications',
      description: 'General notifications from FoodFlow',
      importance: Importance.high,
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(androidChannel);

    final imageStyle = await _bigPictureStyleFor(message);
    final platformDetails = NotificationDetails(
      android: AndroidNotificationDetails(
        androidChannel.id,
        androidChannel.name,
        channelDescription: androidChannel.description,
        icon: android?.smallIcon,
        importance: Importance.high,
        priority: Priority.high,
        styleInformation: imageStyle,
      ),
      iOS: const DarwinNotificationDetails(),
    );

    await _localNotifications.show(
      id: notification.hashCode,
      title: notification.title?.toString(),
      body: notification.body?.toString(),
      notificationDetails: platformDetails,
      payload: jsonEncode(_safeDataMap(message.data)),
    );
  }

  Future<BigPictureStyleInformation?> _bigPictureStyleFor(
    RemoteMessage message,
  ) async {
    final imageUrl = (message.data['image_url'] ??
            message.data['image'] ??
            message.notification?.android?.imageUrl)
        ?.toString()
        .trim();
    if (imageUrl == null || imageUrl.isEmpty) return null;

    try {
      final response =
          await http.get(Uri.parse(imageUrl)).timeout(const Duration(seconds: 4));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        return null;
      }

      final bitmap = ByteArrayAndroidBitmap(response.bodyBytes);
      return BigPictureStyleInformation(
        bitmap,
        largeIcon: bitmap,
        contentTitle: message.notification?.title,
        summaryText: message.notification?.body,
      );
    } catch (_) {
      return null;
    }
  }

  static Future<void> showOrderNotificationFromMessage(
    RemoteMessage message,
  ) async {
    final data = _normalizeOrderData(_safeDataMap(message.data));
    final orderId = data['order_id'] ?? data['id'];
    if (orderId == null && !_isStaticOrderData(data)) return;

    await SoundService.playNewOrderSound();

    final plugin = FlutterLocalNotificationsPlugin();
    const androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings();
    await plugin.initialize(
      settings: const InitializationSettings(
        android: androidSettings,
        iOS: iosSettings,
      ),
    );

    const channel = AndroidNotificationChannel(
      'incoming_order_channel',
      'Incoming Orders',
      description: 'Urgent restaurant and driver order alerts',
      importance: Importance.max,
      playSound: true,
      audioAttributesUsage: AudioAttributesUsage.alarm,
    );

    await plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    final title = message.notification?.title?.toString() ??
        data['notification_title']?.toString() ??
        (data['role'] == 'driver' ? 'New delivery' : 'New order');
    final body = message.notification?.body?.toString() ??
        data['notification_body']?.toString() ??
        'Order #${data['order_number'] ?? orderId ?? ''} is waiting.';

    final notificationId = DateTime.now().millisecondsSinceEpoch ~/ 1000;
    final payload = jsonEncode(data);

    try {
      await plugin.show(
        id: notificationId,
        title: title,
        body: body,
        notificationDetails: const NotificationDetails(
          android: AndroidNotificationDetails(
            'incoming_order_channel',
            'Incoming Orders',
            channelDescription: 'Urgent restaurant and driver order alerts',
            importance: Importance.max,
            priority: Priority.max,
            category: AndroidNotificationCategory.call,
            visibility: NotificationVisibility.public,
            fullScreenIntent: true,
            ongoing: true,
            autoCancel: false,
            audioAttributesUsage: AudioAttributesUsage.alarm,
          ),
          iOS: DarwinNotificationDetails(
            interruptionLevel: InterruptionLevel.critical,
          ),
        ),
        payload: payload,
      );
    } catch (e, stackTrace) {
      debugPrint('Urgent order notification fallback: $e');
      debugPrintStack(stackTrace: stackTrace);
      await plugin.show(
        id: notificationId,
        title: title,
        body: body,
        notificationDetails: const NotificationDetails(
          android: AndroidNotificationDetails(
            'incoming_order_channel',
            'Incoming Orders',
            channelDescription: 'Urgent restaurant and driver order alerts',
            importance: Importance.max,
            priority: Priority.max,
            visibility: NotificationVisibility.public,
            autoCancel: true,
          ),
          iOS: DarwinNotificationDetails(),
        ),
        payload: payload,
      );
    }
  }

  Future<bool> _showInAppOrderOverlay(RemoteMessage message) {
    final data = _normalizeOrderData(_safeDataMap(message.data));
    if (!_isStaticOrderData(data)) return Future.value(false);

    return IncomingOrderAlertService.instance.onMessageReceived(data);
  }

  void _handleNotificationPayload(String? payload) {
    if (payload == null || payload.isEmpty) return;
    try {
      final decoded = jsonDecode(payload);
      if (decoded is Map) {
        _presentOrderFromData(Map<String, dynamic>.from(decoded));
      }
    } catch (_) {
      return;
    }
  }

  void _presentOrderFromData(Map<String, dynamic> data) {
    data = _normalizeOrderData(data);
    if (_handleGenericDeepLink(data)) {
      return;
    }

    final orderId = data['order_id'] ?? data['id'];
    if (orderId == null) {
      return;
    }

    if (_isStaticOrderData(data)) {
      _queueOrderOverlay(data);
      return;
    }

    final route =
        data['role'] == 'driver' || data['type'] == 'driver_order_assigned'
            ? '/driver/order'
            : '/restaurant/order';

    appNavigatorKey.currentState?.pushNamed(route, arguments: orderId);
  }

  bool _handleGenericDeepLink(Map<String, dynamic> data) {
    final deepLink = data['deep_link']?.toString().trim();
    final type = data['type']?.toString().trim().toLowerCase() ?? '';

    if (deepLink != null && deepLink.isNotEmpty) {
      final chatMatch = RegExp(r'^/orders/(\d+)/chat$').firstMatch(deepLink);
      if (chatMatch != null) {
        final orderId = int.tryParse(chatMatch.group(1) ?? '');
        if (orderId != null) {
          appNavigatorKey.currentState?.pushNamed(
            '/restaurant/order/chat',
            arguments: orderId,
          );
        }
        return true;
      }
      if (deepLink == '/restaurant/profile/help') {
        appNavigatorKey.currentState?.pushNamed(
          deepLink,
          arguments: {'openChat': true},
        );
      } else {
        appNavigatorKey.currentState?.pushNamed(deepLink);
      }
      return true;
    }

    if (type.contains('support')) {
      appNavigatorKey.currentState?.pushNamed(
        '/restaurant/profile/help',
        arguments: {'openChat': true},
      );
      return true;
    }

    return false;
  }

  static void _queueOrderOverlay(Map<String, dynamic> data) {
    _pendingOverlayData = _normalizeOrderData(data);
    _pendingOverlayAttempts = 0;
    _tryShowPendingOverlay();
  }

  static void _tryShowPendingOverlay() {
    final data = _pendingOverlayData;
    if (data == null) return;

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final contextReady = appNavigatorKey.currentContext != null &&
          appNavigatorKey.currentState != null;

      if (!contextReady) {
        _retryPendingOverlay();
        return;
      }

      _pendingOverlayData = null;
      _pendingOverlayAttempts = 0;

      await IncomingOrderAlertService.instance.onNotificationTapRedirect(data);
    });
  }

  static void _retryPendingOverlay() {
    _pendingOverlayAttempts++;
    if (_pendingOverlayAttempts > 20) {
      final data = _pendingOverlayData;
      _pendingOverlayData = null;
      _pendingOverlayAttempts = 0;

      final orderId = data?['order_id'] ?? data?['id'];
      if (orderId != null) {
        final route =
            _isDriverOrderData(data!) ? '/driver/order' : '/restaurant/order';
        appNavigatorKey.currentState?.pushNamed(route, arguments: orderId);
      }
      return;
    }

    Future<void>.delayed(
      const Duration(milliseconds: 350),
      _tryShowPendingOverlay,
    );
  }

  static bool _isDriverOrderData(Map<String, dynamic> data) {
    final type = data['type']?.toString() ?? '';
    return data['role'] == 'driver' || type == 'driver_order_assigned';
  }

  static bool _isIncomingOrderData(Map<String, dynamic> data) {
    final normalized = _normalizeOrderData(data);
    final type = normalized['type']?.toString().toLowerCase() ?? '';
    final role = normalized['role']?.toString().toLowerCase() ?? '';

    return type == 'new_order' ||
        type == 'driver_order_assigned' ||
        role == 'driver' ||
        role == 'restaurant';
  }

  static Map<String, dynamic> normalizeNotificationData(
    Map<dynamic, dynamic> data,
  ) {
    return _normalizeOrderData(_safeDataMap(data));
  }

  static bool isIncomingOrderPayload(Map<String, dynamic> data) {
    return _isIncomingOrderData(data);
  }

  static bool _isStaticOrderData(Map<String, dynamic> data) {
    data = _normalizeOrderData(data);
    final type = data['type']?.toString() ?? '';
    return type.contains('order') ||
        data.containsKey('order_id') ||
        data.containsKey('order_number');
  }

  bool _isOrderMessage(RemoteMessage message) {
    return _isIncomingOrderData(_safeDataMap(message.data));
  }

  static Map<String, dynamic> _normalizeOrderData(Map<String, dynamic> data) {
    for (final key in const ['order', 'order_data', 'data', 'payload']) {
      final nested = data[key];
      if (nested is Map<String, dynamic>) {
        return {...data, ...nested};
      }
      if (nested is Map) {
        return {...data, ...Map<String, dynamic>.from(nested)};
      }
      if (nested is String && nested.trim().isNotEmpty) {
        try {
          final decoded = jsonDecode(nested);
          if (decoded is Map<String, dynamic>) {
            return {...data, ...decoded};
          }
          if (decoded is Map) {
            return {...data, ...Map<String, dynamic>.from(decoded)};
          }
        } catch (_) {
          continue;
        }
      }
    }
    return data;
  }

  static Map<String, dynamic> _safeDataMap(Map<dynamic, dynamic> data) {
    return data.map((key, value) => MapEntry(key.toString(), value));
  }

  static Future<void> persistPendingOrderData(
      Map<dynamic, dynamic> data) async {
    final normalized = _normalizeOrderData(_safeDataMap(data));
    if (!_isStaticOrderData(normalized)) return;

    final prefs = await SharedPreferences.getInstance();
    final duration = IncomingOrderAlertService.timerDuration(normalized);
    await prefs.setString(
      'active_incoming_order_payload',
      jsonEncode(normalized),
    );
    await prefs.setInt(
      'active_incoming_order_expiry_ms',
      DateTime.now().add(Duration(seconds: duration)).millisecondsSinceEpoch,
    );
    await prefs.setString(
      'active_incoming_order_role',
      IncomingOrderAlertService.roleFor(normalized),
    );
  }
}
