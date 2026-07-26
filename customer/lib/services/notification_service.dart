import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' show Color;

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/api_constants.dart';
import '../config/app_config.dart';
import '../firebase_options.dart';
import '../models/order.dart';
import '../models/user.dart';
import 'api_service.dart';
import 'customer_order_status_overlay_service.dart';
import 'navigation_service.dart';
import 'sound_service.dart';

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await _ensureFirebaseInitialized();
    final data = FirebaseNotificationService.normalizeNotificationData(
      message.data,
    );
    if (FirebaseNotificationService.isCustomerOrderStatusPayload(data)) {
      await FirebaseNotificationService
          .showCustomerOrderStatusNotificationFromMessage(
        message,
      );
      return;
    }

    if (FirebaseNotificationService.isCustomBroadcastPayload(data)) {
      await FirebaseNotificationService.showCustomBroadcastNotification(
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
  static const int _liveOrderNotificationBaseId = 810000;

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
      await _ensureFirebaseInitialized();
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

    _messaging.onTokenRefresh.listen((token) {
      _deviceToken = token;
      registerDeviceToken();
    });

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

    _isInitialized = true;
    await registerDeviceToken();
    if (_deviceToken == null && !kIsWeb && Platform.isIOS) {
      unawaited(Future<void>.delayed(
        const Duration(seconds: 3),
        registerDeviceToken,
      ));
    }
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
      await _ensureFirebaseInitialized();

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

    if (_isCustomBroadcastMessage(message)) {
      await showCustomBroadcastNotification(message);
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

    final imageStyle = await _bigPictureStyleForMessage(message);
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
        visibility: NotificationVisibility.public,
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

  static Future<void> showCustomBroadcastNotification(
    RemoteMessage message,
  ) async {
    final data = _safeDataMap(message.data);
    if (_isForeignOrderData(data)) {
      return;
    }

    final notification = message.notification;
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
      _defaultChannelId,
      'Default Notifications',
      description: 'General notifications from FoodFlow',
      importance: Importance.high,
      playSound: true,
      sound: _customPushSound,
    );

    await plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    final imageStyle = await _bigPictureStyleForMessage(
      message,
      showSummaryText: false,
    );
    final android = message.notification?.android;
    await plugin.show(
      id: DateTime.now().millisecondsSinceEpoch ~/ 1000,
      title: title,
      body: imageStyle == null ? body : null,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          _defaultChannelId,
          'Default Notifications',
          channelDescription: 'General notifications from FoodFlow',
          icon: android?.smallIcon,
          importance: Importance.high,
          priority: Priority.max,
          playSound: true,
          sound: _customPushSound,
          visibility: NotificationVisibility.public,
          styleInformation: imageStyle,
        ),
        iOS: const DarwinNotificationDetails(sound: 'custom-push.mp3'),
      ),
      payload: jsonEncode(data),
    );
  }

  static Future<BigPictureStyleInformation?> _bigPictureStyleForMessage(
    RemoteMessage message, {
    bool showSummaryText = true,
  }) async {
    final imageUrl = (message.data['image_url'] ??
            message.data['image'] ??
            message.notification?.android?.imageUrl)
        ?.toString()
        .trim();
    if (imageUrl == null || imageUrl.isEmpty) return null;

    try {
      final response = await http
          .get(Uri.parse(imageUrl))
          .timeout(const Duration(seconds: 4));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        return null;
      }

      final bitmap = ByteArrayAndroidBitmap(response.bodyBytes);
      return BigPictureStyleInformation(
        bitmap,
        largeIcon: bitmap,
        hideExpandedLargeIcon: true,
        contentTitle: message.notification?.title?.toString() ??
            message.data['notification_title']?.toString() ??
            message.data['title']?.toString(),
        summaryText: showSummaryText
            ? message.notification?.body?.toString() ??
                message.data['notification_body']?.toString() ??
                message.data['body']?.toString() ??
                message.data['message']?.toString()
            : null,
      );
    } catch (_) {
      return null;
    }
  }

  Future<String?> _resolveDeviceToken() async {
    if (kIsWeb && AppConfig.firebaseWebVapidKey.isNotEmpty) {
      return _messaging.getToken(vapidKey: AppConfig.firebaseWebVapidKey);
    }
    if (!kIsWeb && Platform.isIOS) {
      for (var attempt = 0; attempt < 10; attempt++) {
        final apnsToken = await _messaging.getAPNSToken();
        if (apnsToken?.isNotEmpty == true) return _messaging.getToken();
        await Future<void>.delayed(const Duration(milliseconds: 500));
      }
      debugPrint(
          'FCM token unavailable because APNs registration did not complete.');
      return null;
    }
    return _messaging.getToken();
  }

  static Future<void> showCustomerOrderStatusNotificationFromMessage(
    RemoteMessage message,
  ) async {
    final data = _normalizeOrderData(_safeDataMap(message.data));
    if (_isForeignOrderData(data)) {
      return;
    }

    await _showLiveOrderNotification(
      data: data,
      titleOverride: message.notification?.title?.toString() ??
          data['notification_title']?.toString(),
      bodyOverride:
          message.notification?.body?.toString() ?? data['message']?.toString(),
      alertOnce: true,
    );
  }

  static Future<void> showLiveOrderNotificationFromOrder(
    Order order, {
    String? etaText,
    String? distanceText,
  }) async {
    await _showLiveOrderNotification(
      data: {
        'type': 'customer_order_status',
        'role': 'customer',
        'order_id': order.id,
        'order_number': order.orderNumber,
        'status': order.status,
        'service_type': order.serviceType,
        'order_type': order.orderType,
        'eta_range': etaText ?? order.etaRange,
        'eta_minutes': order.etaMinutes,
        'distance_remaining': distanceText ?? order.deliveryDistanceLabel,
        'restaurant_name': order.restaurant?.name,
      },
      alertOnce: false,
      silent: true,
    );
  }

  static Future<void> _showLiveOrderNotification({
    required Map<String, dynamic> data,
    String? titleOverride,
    String? bodyOverride,
    bool alertOnce = true,
    bool silent = false,
  }) async {
    final normalized = _normalizeOrderData(data);
    final orderId = _parseOrderId(normalized['order_id'] ?? normalized['id']);
    if (orderId == null) return;

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

    final status = _normalizedStatus(normalized['status'] ??
        normalized['order_status'] ??
        normalized['current_status']);
    final terminal = status == 'delivered' || status == 'cancelled';
    final etaLabel = _etaLabelFromData(normalized);
    final progress = _progressForOrderNotification(normalized, status);
    final title = _liveOrderTitle(
      status: status,
      etaLabel: etaLabel,
      orderType: normalized['order_type']?.toString(),
      fallback: titleOverride,
    );
    final body = bodyOverride?.trim().isNotEmpty == true
        ? bodyOverride!.trim()
        : _liveOrderBody(
            status: status,
            orderType: normalized['order_type']?.toString(),
            restaurantName: normalized['restaurant_name']?.toString(),
          );
    final payload = {
      ...normalized,
      'type': 'customer_order_status',
      'role': 'customer',
      'order_id': orderId,
      'deep_link': '/order/track',
    };

    await plugin.show(
      id: _liveOrderNotificationId(orderId),
      title: title,
      body: body,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          _orderStatusChannelId,
          'Order Status Updates',
          channelDescription: 'Customer order status alerts',
          importance: Importance.high,
          priority: Priority.max,
          category: AndroidNotificationCategory.progress,
          playSound: !silent && alertOnce,
          sound: !silent && alertOnce ? _customPushSound : null,
          visibility: NotificationVisibility.public,
          ongoing: !terminal,
          autoCancel: terminal,
          onlyAlertOnce: true,
          showProgress: true,
          maxProgress: 100,
          progress: progress,
          color: const Color(0xFFE23744),
          colorized: false,
          subText: etaLabel,
          timeoutAfter: terminal ? 45000 : null,
          ticker: title,
        ),
        iOS: DarwinNotificationDetails(
          sound: !silent && alertOnce ? 'custom-push.mp3' : null,
        ),
      ),
      payload: jsonEncode(payload),
    );
  }

  static int _liveOrderNotificationId(int orderId) {
    return _liveOrderNotificationBaseId + orderId.remainder(100000);
  }

  static String _normalizedStatus(dynamic value) {
    return value
            ?.toString()
            .trim()
            .toLowerCase()
            .replaceAll('-', '_')
            .replaceAll(' ', '_') ??
        '';
  }

  static String? _etaLabelFromData(Map<String, dynamic> data) {
    final range =
        (data['eta_range'] ?? data['estimated_delivery_label'])?.toString();
    if (range != null && range.trim().isNotEmpty) return range.trim();

    final minutes = _parseOrderId(
      data['eta_minutes'] ??
          data['estimated_delivery_minutes'] ??
          data['delivery_eta_minutes'],
    );
    if (minutes != null && minutes > 0) return '$minutes mins';
    return null;
  }

  static String _liveOrderTitle({
    required String status,
    required String? etaLabel,
    required String? orderType,
    required String? fallback,
  }) {
    if (status == 'picked_up' || status == 'on_the_way') {
      return etaLabel == null ? 'Arriving soon' : 'Arriving in $etaLabel';
    }
    if (orderType == 'takeaway' && status == 'ready_for_pickup') {
      return 'Ready to collect';
    }
    if (status == 'delivered') return 'Delivered';
    if (status == 'cancelled') return 'Order cancelled';
    if (fallback != null && fallback.trim().isNotEmpty) return fallback.trim();
    if (status == 'preparing') return 'Preparing your order';
    if (status == 'ready_for_pickup') return 'Rider heading to pickup';
    if (status == 'confirmed') return 'Order confirmed';
    return 'Order update';
  }

  static String _liveOrderBody({
    required String status,
    required String? orderType,
    required String? restaurantName,
  }) {
    final store = restaurantName?.trim().isNotEmpty == true
        ? restaurantName!.trim()
        : 'The store';
    if (orderType == 'takeaway') {
      if (status == 'ready_for_pickup') {
        return 'Your order is waiting at the counter';
      }
      if (status == 'delivered' || status == 'picked_up') {
        return 'Thanks for ordering with FoodFlow';
      }
      return '$store is preparing your pickup';
    }

    switch (status) {
      case 'confirmed':
        return '$store accepted your order';
      case 'preparing':
        return 'Your food is being prepared';
      case 'ready_for_pickup':
        return 'Delivery partner will pick it up soon';
      case 'picked_up':
      case 'on_the_way':
        return 'Order is on the way';
      case 'delivered':
        return 'Enjoy your meal';
      case 'cancelled':
        return 'This order is no longer active';
      default:
        return 'We are tracking your order';
    }
  }

  static int _progressForOrderNotification(
    Map<String, dynamic> data,
    String status,
  ) {
    final explicitProgress = _parseOrderId(
      data['progress'] ??
          data['progress_percent'] ??
          data['completion_percent'],
    );
    if (explicitProgress != null) {
      return explicitProgress.clamp(0, 100);
    }

    final isTakeaway = data['order_type']?.toString() == 'takeaway';
    final progressMap = isTakeaway
        ? const {
            'pending': 8,
            'confirmed': 25,
            'preparing': 55,
            'ready_for_pickup': 90,
            'picked_up': 100,
            'delivered': 100,
            'cancelled': 100,
          }
        : const {
            'pending': 8,
            'confirmed': 18,
            'preparing': 36,
            'ready_for_pickup': 54,
            'reached_pickup': 62,
            'picked_up': 70,
            'on_the_way': 84,
            'delivered': 100,
            'cancelled': 100,
          };
    return progressMap[status] ?? 12;
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

  bool _isCustomBroadcastMessage(RemoteMessage message) {
    return _isCustomBroadcastData(_safeDataMap(message.data));
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
    final hasOrderId =
        normalized.containsKey('order_id') || normalized.containsKey('id');

    return hasOrderId &&
        (role == 'customer' || type == 'customer_order_status');
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

  static bool isCustomBroadcastPayload(Map<String, dynamic> data) {
    return _isCustomBroadcastData(data);
  }

  static bool _isCustomBroadcastData(Map<String, dynamic> data) {
    final normalized = _normalizeOrderData(data);
    final type = normalized['type']?.toString().toLowerCase() ?? '';
    final targetApp = normalized['target_app']?.toString().toLowerCase() ?? '';
    final dataOnly =
        normalized['data_only']?.toString().toLowerCase() == 'true' ||
            normalized['data_only']?.toString() == '1';
    return type == 'admin_broadcast' && (targetApp == 'customer' || dataOnly);
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

Future<void> _ensureFirebaseInitialized() async {
  if (Firebase.apps.isNotEmpty) return;

  if (!kIsWeb && defaultTargetPlatform == TargetPlatform.iOS) {
    await Firebase.initializeApp();
    return;
  }

  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
}
