import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_constants.dart';
import '../models/user.dart';
import 'api_service.dart';
import 'app_order_overlay_service.dart';
import 'foreground_service_manager.dart';
import 'navigation_service.dart';
import 'order_alert_permission_manager.dart';

enum IncomingOrderSource { fcmForeground, notificationTap, websocket, restored }

class IncomingOrderAlertService with WidgetsBindingObserver {
  IncomingOrderAlertService._();

  static final IncomingOrderAlertService instance =
      IncomingOrderAlertService._();

  static const String _pendingOrderKey = 'active_incoming_order_payload';
  static const String _pendingExpiryKey = 'active_incoming_order_expiry_ms';
  static const String _pendingRoleKey = 'active_incoming_order_role';

  final Set<String> _handledOrderKeys = <String>{};
  final Map<String, Future<void>> _activeLocks = <String, Future<void>>{};
  AppLifecycleState _state = AppLifecycleState.resumed;
  bool _initialized = false;

  Future<void> initialize() async {
    if (_initialized) return;
    WidgetsBinding.instance.addObserver(this);
    _initialized = true;
    await restorePendingOrderState();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _state = state;
    switch (state) {
      case AppLifecycleState.resumed:
        onAppForeground();
        break;
      case AppLifecycleState.inactive:
      case AppLifecycleState.hidden:
      case AppLifecycleState.paused:
        onAppBackground();
        break;
      case AppLifecycleState.detached:
        onAppTerminated();
        break;
    }
  }

  Future<void> onAppForeground() async {
    if (await _shouldKeepOrderListenerAlive()) {
      await ForegroundServiceManager.handleServiceRestart();
    }
    await retryFailedResponses();
    await restorePendingOrderState();
    await syncPendingOrdersFromServer();
  }

  Future<void> onAppBackground() async {
    if (await _shouldKeepOrderListenerAlive()) {
      await ForegroundServiceManager.startForegroundService();
    }
  }

  Future<void> onAppTerminated() async {
    if (await _shouldKeepOrderListenerAlive()) {
      await ForegroundServiceManager.startForegroundService();
    }
  }

  Future<void> registerDeviceToken({
    required String? token,
    User? user,
  }) async {
    if (token == null || token.isEmpty) return;
    await ApiService().post(ApiConstants.registerFcmToken, data: {
      'fcm_token': token,
      'target_app': 'driver',
      if (user != null) 'user_id': user.id,
      if (user?.role != null) 'role': user!.role,
    });
  }

  Future<bool> onMessageReceived(Map<String, dynamic> data) {
    return handleIncomingOrderData(data,
        source: IncomingOrderSource.fcmForeground);
  }

  Future<bool> onBackgroundMessage(Map<String, dynamic> data) async {
    await persistIncomingOrder(data);
    await ForegroundServiceManager.startForegroundService(
      status: 'Incoming order waiting',
      fullScreen: true,
    );
    return false;
  }

  Future<bool> onNotificationTapRedirect(Map<String, dynamic> data) {
    return handleIncomingOrderData(data,
        source: IncomingOrderSource.notificationTap);
  }

  Future<bool> handleIncomingOrderData(
    Map<String, dynamic> data, {
    required IncomingOrderSource source,
  }) async {
    final normalized = normalizeOrderData(data);
    if (!isIncomingOrder(normalized)) return false;

    final orderId = parseOrderId(normalized['order_id'] ?? normalized['id']);
    if (orderId == null) return false;

    final key = '${roleFor(normalized)}:$orderId';
    final isDuplicate = !_handledOrderKeys.add(key);
    if (isDuplicate && source != IncomingOrderSource.restored) {
      return false;
    }
    if (_activeLocks.containsKey(key)) {
      return false;
    }

    final duration = timerDuration(normalized);
    await persistIncomingOrder(normalized, durationSeconds: duration);
    await ForegroundServiceManager.startForegroundService(
      status: 'Incoming order #${normalized['order_number'] ?? orderId}',
    );
    final overlayPermissionGranted =
        await OrderAlertPermissionManager.checkOverlayPermission();

    final canUseFlutterUi = _hasFlutterUiContext() &&
        _state == AppLifecycleState.resumed;

    if (!canUseFlutterUi) {
      if (source == IncomingOrderSource.restored && overlayPermissionGranted) {
        return _retryShowRestoredOverlay(normalized, duration);
      }

      if (!overlayPermissionGranted) {
        await OrderAlertPermissionManager.requestOverlayPermission();
        await ForegroundServiceManager.updateServiceNotification(
          'Incoming order waiting. Tap the full-screen alert. Please enable overlay permission in app settings.',
          fullScreen: true,
        );
      } else {
        await ForegroundServiceManager.startForegroundService(
          status: 'Incoming order waiting',
          fullScreen: true,
        );
        await ForegroundServiceManager.bringAppToFront();
      }
      return false;
    }

    final future = _showIncomingOrderOverlay(
      normalized,
      durationSeconds: duration,
    ).whenComplete(() {
      _activeLocks.remove(key);
      unawaited(clearPendingOrder(orderId));
    });

    _activeLocks[key] = future;
    await future;
    return true;
  }

  Future<void> restorePendingOrderState() async {
    final prefs = await SharedPreferences.getInstance();
    final payload = prefs.getString(_pendingOrderKey);
    final expiryMs = prefs.getInt(_pendingExpiryKey);
    if (payload == null || expiryMs == null) return;

    if (DateTime.now().millisecondsSinceEpoch >= expiryMs) {
      await clearPendingOrder(null);
      return;
    }

    try {
      final decoded = jsonDecode(payload);
      if (decoded is Map) {
        await resumeActiveOverlayIfExists(Map<String, dynamic>.from(decoded));
      }
    } catch (_) {
      await clearPendingOrder(null);
    }
  }

  Future<void> resumeActiveOverlayIfExists(Map<String, dynamic> data) async {
    await handleIncomingOrderData(data, source: IncomingOrderSource.restored);
  }

  Future<void> syncPendingOrdersFromServer() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final role = prefs.getString(_pendingRoleKey);
      if (role == null) return;

      final endpoint = role == 'driver'
          ? ApiConstants.driverOrders
          : ApiConstants.restaurantOrders;
      await ApiService().get(endpoint);
    } catch (_) {}
  }

  Future<void> retryFailedResponses() async {}

  Future<void> handleNetworkReconnect() async {
    await retryFailedResponses();
    await validateActiveOrderState();
  }

  Future<void> validateActiveOrderState() async {
    await restorePendingOrderState();
  }

  bool _hasFlutterUiContext() {
    return appNavigatorKey.currentContext != null ||
        appNavigatorKey.currentState?.overlay?.context != null;
  }

  Future<bool> _retryShowRestoredOverlay(
    Map<String, dynamic> normalized,
    int durationSeconds, {
    int attempt = 0,
  }) async {
    const maxAttempts = 6;
    if (attempt >= maxAttempts) {
      return false;
    }

    await Future<void>.delayed(Duration(milliseconds: 250 * (attempt + 1)));

    if (!_hasFlutterUiContext() || _state != AppLifecycleState.resumed) {
      return _retryShowRestoredOverlay(
        normalized,
        durationSeconds,
        attempt: attempt + 1,
      );
    }

    await ForegroundServiceManager.startForegroundService(
      status: 'Incoming order waiting',
      fullScreen: true,
    );

    final key =
        '${roleFor(normalized)}:${parseOrderId(normalized['order_id'] ?? normalized['id'])}';
    final future = _showIncomingOrderOverlay(
      normalized,
      durationSeconds: durationSeconds,
    ).whenComplete(() {
      _activeLocks.remove(key);
      unawaited(clearPendingOrder(parseOrderId(normalized['order_id'] ?? normalized['id'])));
    });

    _activeLocks[key] = future;
    await future;
    return true;
  }

  Future<void> _showIncomingOrderOverlay(
    Map<String, dynamic> data, {
    required int durationSeconds,
  }) async {
    final order = orderFromData(data);
    final role = roleFor(data);

    if (role == 'driver') {
      await AppOrderOverlayService.showDriverOrder(
        order,
        durationSeconds: durationSeconds,
        onAccept: (id) => acceptOrder(id, role: role),
        onReject: (id, reason) => rejectOrder(id, role: role, reason: reason),
        onTimeout: (id) => autoRejectOnTimeout(id, role: role),
      );
    } else {
      await AppOrderOverlayService.showRestaurantOrder(
        order,
        durationSeconds: durationSeconds,
        onAccept: (id, minutes) => acceptOrder(
          id,
          role: role,
          preparationMinutes: minutes,
        ),
        onReject: (id, reason) => rejectOrder(id, role: role, reason: reason),
        onTimeout: (id) => autoRejectOnTimeout(id, role: role),
      );
    }
  }

  Future<bool> acceptOrder(
    int orderId, {
    required String role,
    int? preparationMinutes,
  }) async {
    try {
      return await lockOrderInteraction(orderId, () async {
        final response = role == 'driver'
            ? await ApiService().post(ApiConstants.driverAcceptOrder(orderId))
            : await ApiService().post(
                ApiConstants.restaurantAcceptOrder(orderId),
                data: {
                  if (preparationMinutes != null)
                    'preparation_time_minutes': preparationMinutes,
                },
              );
        final ok = response['success'] == true;
        if (ok) await clearPendingOrder(orderId);
        return ok;
      });
    } catch (_) {
      return false;
    }
  }

  Future<bool> rejectOrder(
    int orderId, {
    required String role,
    required String reason,
  }) async {
    try {
      return await lockOrderInteraction(orderId, () async {
        final response = role == 'driver'
            ? await ApiService().post(
                ApiConstants.driverRejectOrder(orderId),
                data: {'reason': reason},
              )
            : await ApiService().post(
                ApiConstants.restaurantRejectOrder(orderId),
                data: {'reason': reason},
              );
        final ok = response['success'] == true;
        if (ok) await clearPendingOrder(orderId);
        return ok;
      });
    } catch (_) {
      return false;
    }
  }

  Future<bool> autoRejectOnTimeout(int orderId, {required String role}) {
    return rejectOrder(
      orderId,
      role: role,
      reason: 'Auto rejected: incoming order timer expired',
    );
  }

  Future<T> lockOrderInteraction<T>(
    int orderId,
    Future<T> Function() action,
  ) async {
    final key = 'response:$orderId';
    final existing = _activeLocks[key];
    if (existing != null) {
      await existing;
      throw StateError('Order response already in progress');
    }

    final completer = Completer<void>();
    _activeLocks[key] = completer.future;
    try {
      return await action();
    } finally {
      completer.complete();
      _activeLocks.remove(key);
    }
  }

  Future<void> persistIncomingOrder(
    Map<String, dynamic> data, {
    int? durationSeconds,
  }) async {
    final normalized = normalizeOrderData(data);
    final prefs = await SharedPreferences.getInstance();
    final seconds = durationSeconds ?? timerDuration(normalized);
    await prefs.setString(_pendingOrderKey, jsonEncode(normalized));
    await prefs.setString(_pendingRoleKey, roleFor(normalized));
    await prefs.setInt(
      _pendingExpiryKey,
      DateTime.now().add(Duration(seconds: seconds)).millisecondsSinceEpoch,
    );
  }

  Future<void> clearPendingOrder(int? orderId) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_pendingOrderKey);
    await prefs.remove(_pendingExpiryKey);
    await prefs.remove(_pendingRoleKey);
  }

  static Map<String, dynamic> normalizeOrderData(Map<dynamic, dynamic> data) {
    final normalized =
        data.map((key, value) => MapEntry(key.toString(), value));
    for (final key in const [
      'order',
      'order_data',
      'data',
      'payload',
      'metadata'
    ]) {
      final nested = normalized[key];
      if (nested is Map) {
        return {...normalized, ...normalizeOrderData(nested)};
      }
      if (nested is String && nested.trim().isNotEmpty) {
        try {
          final decoded = jsonDecode(nested);
          if (decoded is Map) {
            return {...normalized, ...normalizeOrderData(decoded)};
          }
        } catch (_) {}
      }
    }
    return normalized;
  }

  static bool isIncomingOrder(Map<String, dynamic> data) {
    final type = data['type']?.toString().toLowerCase().replaceAll('-', '_');
    return type == 'new_order' ||
        type == 'driver_order_assigned' ||
        data.containsKey('order_id') ||
        data.containsKey('order_number');
  }

  static String roleFor(Map<String, dynamic> data) {
    final role = data['role']?.toString().toLowerCase();
    final type = data['type']?.toString().toLowerCase() ?? '';
    if (role == 'driver' || type.contains('driver')) return 'driver';
    return 'restaurant';
  }

  static int timerDuration(Map<String, dynamic> data) {
    final raw = data['timer_duration'] ?? data['timer_seconds'] ?? data['ttl'];
    final parsed =
        raw is num ? raw.toInt() : int.tryParse(raw?.toString() ?? '');
    return (parsed ?? 30).clamp(10, 120);
  }

  static int? parseOrderId(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value);
    return null;
  }

  static Map<String, dynamic> orderFromData(Map<String, dynamic> data) {
    data = normalizeOrderData(data);
    return {
      'id': data['order_id'] ?? data['id'],
      'order_number': data['order_number'],
      'restaurant_name': data['restaurant_name'],
      'pickup_address': data['pickup_address'] ?? data['restaurant_address'],
      'delivery_address': data['delivery_address'],
      'customer_name': data['customer_name'],
      'distance': data['distance'] ?? data['distance_km'],
      'earnings': data['earnings'] ?? data['driver_earning'],
      'total': data['total'] ?? data['amount'],
      'items': itemsFromData(data['items']),
    };
  }

  static List<dynamic> itemsFromData(dynamic value) {
    if (value is List) return value;
    if (value is String && value.trim().isNotEmpty) {
      try {
        final decoded = jsonDecode(value);
        if (decoded is List) return decoded;
      } catch (_) {}
    }
    return const [];
  }

  Future<bool> _shouldKeepOrderListenerAlive() async {
    final prefs = await SharedPreferences.getInstance();
    final userData = prefs.getString('user_data');
    if (userData == null) return false;

    try {
      final decoded = jsonDecode(userData);
      if (decoded is! Map) return false;
      final role = decoded['role']?.toString().toLowerCase() ?? '';
      return role.contains('driver') ||
          role.contains('delivery') ||
          role.contains('restaurant') ||
          role.contains('owner');
    } catch (_) {
      return false;
    }
  }
}
