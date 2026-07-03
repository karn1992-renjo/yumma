import 'dart:async';
import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter/foundation.dart'; 
import 'package:http/http.dart' as http;

import '../config/api_constants.dart';
import '../config/app_config.dart';
import '../firebase_options.dart';
import '../models/user.dart';
import 'api_service.dart';
import 'customer_order_status_overlay_service.dart';
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
    if (FirebaseNotificationService.isCustomerOrderStatusPayload(data)) {
      await FirebaseNotificationService.showCustomerOrderStatusNotificationFromMessage(
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

  static const AndroidNotificationSound _customPushSound =
      RawResourceAndroidNotificationSound('custom_push');
  static const String _defaultChannelId = 'default_notification_channel_custom';
  static const String _orderStatusChannelId = 'order_status_channel_custom';

  static final FirebaseNotificationService instance =
      FirebaseNotificationService._internal();

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

    await _createNotificationChannels();

    await _messaging.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );

    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    debugPrint('FCM permission status: ${settings.authorizationStatus}');

    _deviceToken = await _resolveDeviceToken();
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

  void _handleForegroundMessage(RemoteMessage message) {
    try {
      _showInAppOrderOverlay(message);
    } catch (e, stackTrace) {
      debugPrint('FCM in-app overlay skipped: $e');
      debugPrintStack(stackTrace: stackTrace);
    }

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

      _deviceToken ??= await _resolveDeviceToken();
      if (_deviceToken == null || _deviceToken!.isEmpty) {
        debugPrint('FCM token registration skipped: token unavailable');
        return;
      }

      final role = user?.role;
      await ApiService().post(ApiConstants.registerFcmToken, data: {
        'fcm_token': _deviceToken,
        'target_app': 'customer',
        if (user != null) 'user_id': user.id,
        if (role != null) 'role': role,
      });
      debugPrint('FCM token registered with backend');
    } catch (e) {
      debugPrint('Failed to register FCM token: $e');
    }
  }

  Future<void> _showForegroundNotification(RemoteMessage message) async {
    if (_isCustomerOrderStatusMessage(message)) {
      await showCustomerOrderStatusNotificationFromMessage(message);
      return;
    }

    if (_isForeignOrderMessage(message)) {
      return;
    }

    if (_shouldPlayForegroundAlertSound(message)) {
      unawaited(SoundService.playMessageSound());
    }

    final notification = message.notification;
    final android = message.notification?.android;
    final data = _safeDataMap(message.data);
    final title = notification?.title?.toString() ??
        data['notification_title']?.toString() ??
        data['title']?.toString();
    final body = notification?.body?.toString() ??
        data['notification_body']?.toString() ??
        data['body']?.toString() ??
        data['message']?.toString();

    if ((title == null || title.trim().isEmpty) &&
        (body == null || body.trim().isEmpty)) {
      return;
    }

    const androidChannel = AndroidNotificationChannel(
      _defaultChannelId,
      'Default Notifications',
      description: 'General notifications from FoodFlow',
      importance: Importance.high,
      playSound: true,
      sound: _customPushSound,
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
        playSound: true,
        sound: _customPushSound,
        styleInformation: imageStyle,
      ),
      iOS: const DarwinNotificationDetails(sound: 'custom-push.mp3'),
    );

    await _localNotifications.show(
      id: notification.hashCode,
      title: title,
      body: body,
      notificationDetails: platformDetails,
      payload: jsonEncode(_safeDataMap(message.data)),
    );
  }

  Future<void> _createNotificationChannels() async {
    final android = _localNotifications.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    if (android == null) return;

    await android.createNotificationChannel(const AndroidNotificationChannel(
      _defaultChannelId,
      'Default Notifications',
      description: 'General notifications from FoodFlow',
      importance: Importance.high,
      playSound: true,
      sound: _customPushSound,
    ));
    await android.createNotificationChannel(const AndroidNotificationChannel(
      _orderStatusChannelId,
      'Order Status Updates',
      description: 'Customer order status alerts',
      importance: Importance.high,
      playSound: true,
      sound: _customPushSound,
    ));
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

  Future<String?> _resolveDeviceToken() {
    if (kIsWeb && AppConfig.firebaseWebVapidKey.isNotEmpty) {
      return _messaging.getToken(vapidKey: AppConfig.firebaseWebVapidKey);
    }
    return _messaging.getToken();
  }

  static Future<void> showCustomerOrderStatusNotificationFromMessage(
    RemoteMessage message,
  ) async {
    final data = _normalizeOrderData(_safeDataMap(message.data));
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
      _orderStatusChannelId,
      'Order Status Updates',
      description: 'Customer order status alerts',
      importance: Importance.high,
      playSound: true,
      sound: _customPushSound,
    );

    await plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    final orderId = data['order_id'] ?? data['id'] ?? '';
    final title = message.notification?.title?.toString() ??
        data['notification_title']?.toString() ??
        'Order update';
    final body = message.notification?.body?.toString() ??
        data['message']?.toString() ??
        'Order #$orderId has a new update.';

    await plugin.show(
      id: DateTime.now().millisecondsSinceEpoch ~/ 1000,
      title: title,
      body: body,
      notificationDetails: const NotificationDetails(
        android: AndroidNotificationDetails(
          _orderStatusChannelId,
          'Order Status Updates',
          channelDescription: 'Customer order status alerts',
          importance: Importance.high,
          priority: Priority.high,
          playSound: true,
          sound: _customPushSound,
          visibility: NotificationVisibility.public,
        ),
        iOS: DarwinNotificationDetails(sound: 'custom-push.mp3'),
      ),
      payload: jsonEncode(data),
    );
  }

  void _showInAppOrderOverlay(RemoteMessage message) {
    final data = _normalizeOrderData(_safeDataMap(message.data));
    if (_isForeignOrderData(data)) {
      return;
    }

    if (_isCustomerOrderStatusData(data)) {
      unawaited(
        CustomerOrderStatusOverlayService.instance.show(
          data: data,
          onTap: () {
            final orderId = _parseOrderId(data['order_id'] ?? data['id']);
            if (orderId != null) {
              appNavigatorKey.currentState?.pushNamed(
                '/order/track',
                arguments: orderId,
              );
            }
          },
        ),
      );
    }
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
    if (_isForeignOrderData(data)) {
      return;
    }

    if (_handleGenericDeepLink(data)) {
      return;
    }

    if (_isCustomerOrderStatusData(data)) {
      final orderId = _parseOrderId(data['order_id'] ?? data['id']);
      if (orderId != null) {
        appNavigatorKey.currentState?.pushNamed(
          '/order/track',
          arguments: orderId,
        );
      }
      return;
    }
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
            '/order/chat',
            arguments: orderId,
          );
        }
        return true;
      }
      if (deepLink == '/support') {
        appNavigatorKey.currentState?.pushNamed(
          deepLink,
          arguments: {'openChat': true},
        );
      } else if (deepLink == '/order/track') {
        final orderId = _parseOrderId(data['order_id'] ?? data['id']);
        if (orderId != null) {
          appNavigatorKey.currentState?.pushNamed(
            deepLink,
            arguments: orderId,
          );
        }
      } else {
        appNavigatorKey.currentState?.pushNamed(deepLink);
      }
      return true;
    }

    if (type.contains('support')) {
      appNavigatorKey.currentState?.pushNamed(
        '/support',
        arguments: {'openChat': true},
      );
      return true;
    }

    return false;
  }

  bool _isCustomerOrderStatusMessage(RemoteMessage message) {
    return _isCustomerOrderStatusData(_safeDataMap(message.data));
  }

  bool _isForeignOrderMessage(RemoteMessage message) {
    return _isForeignOrderData(_safeDataMap(message.data));
  }

  bool _shouldPlayForegroundAlertSound(RemoteMessage message) {
    final data = _normalizeOrderData(_safeDataMap(message.data));
    final type = data['type']?.toString().toLowerCase() ?? '';
    final event = data['event']?.toString().toLowerCase() ?? '';
    final category = data['category']?.toString().toLowerCase() ?? '';
    final deepLink = data['deep_link']?.toString().toLowerCase() ?? '';
    final text = '$type $event $category $deepLink';

    return text.contains('order') ||
        text.contains('refund') ||
        text.contains('chat') ||
        text.contains('support') ||
        text.contains('service_request') ||
        text.contains('service-request');
  }

  static bool _isCustomerOrderStatusData(Map<String, dynamic> data) {
    final normalized = _normalizeOrderData(data);
    final type = normalized['type']?.toString().toLowerCase() ?? '';
    final role = normalized['role']?.toString().toLowerCase() ?? '';
    final hasOrderId = normalized.containsKey('order_id') ||
        normalized.containsKey('id');

    return hasOrderId && (role == 'customer' || type == 'customer_order_status');
  }

  static bool _isForeignOrderData(Map<String, dynamic> data) {
    final normalized = _normalizeOrderData(data);
    final role = normalized['role']?.toString().toLowerCase() ?? '';
    final type = normalized['type']?.toString().toLowerCase() ?? '';
    final hasOrderMarker = normalized.containsKey('order_id') ||
        normalized.containsKey('order_number') ||
        type.contains('order');

    return hasOrderMarker && role.isNotEmpty && role != 'customer';
  }

  static Map<String, dynamic> normalizeNotificationData(
    Map<dynamic, dynamic> data,
  ) {
    return _normalizeOrderData(_safeDataMap(data));
  }

  static bool isCustomerOrderStatusPayload(Map<String, dynamic> data) {
    return _isCustomerOrderStatusData(data);
  }

  static int? _parseOrderId(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
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
}
