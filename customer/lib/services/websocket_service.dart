import 'dart:convert';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import 'api_service.dart';
import 'app_branding_service.dart';

class WebSocketService {
  static final WebSocketService _instance = WebSocketService._internal();
  factory WebSocketService() => _instance;
  WebSocketService._internal();
  
  PusherChannelsFlutter? _pusher;
  final Set<String> _subscribedChannels = {};
  final Map<String, _RestaurantSocketHandlers> _restaurantHandlers = {};
  final Map<String, _DriverSocketHandlers> _driverHandlers = {};
  final Map<String, Map<String, Function(Map<String, dynamic>)>>
      _customerHandlers = {};
  int _nextCustomerHandlerId = 0;
  final Map<int, Function(Map<String, dynamic>)> _orderChatHandlers = {};
  
  Future<void> init(
    int restaurantId, {
    required Function(Map<String, dynamic>) onNewOrder,
    required Function(Map<String, dynamic>) onOrderUpdate,
  }) async {
    await initRestaurant(
      restaurantId,
      onNewOrder: onNewOrder,
      onOrderUpdate: onOrderUpdate,
    );
  }

  Future<void> initRestaurant(
    int restaurantId, {
    required Function(Map<String, dynamic>) onNewOrder,
    required Function(Map<String, dynamic>) onOrderUpdate,
  }) async {
    try {
      await _ensureInitialized();
      final channelName = 'private-restaurant.$restaurantId';
      _restaurantHandlers[channelName] = _RestaurantSocketHandlers(
        onNewOrder: onNewOrder,
        onOrderUpdate: onOrderUpdate,
      );
      if (_subscribedChannels.contains(channelName)) return;
      debugPrint('Pusher subscribing to $channelName');

      await _pusher!.subscribe(
        channelName: channelName,
        onEvent: _handlePusherEvent,
      );
      _subscribedChannels.add(channelName);
    } catch (e) {
      print('WebSocket init error: $e');
    }
  }

  Future<void> initDriver(
    int driverId, {
    required Function(Map<String, dynamic>) onOrderAssigned,
  }) async {
    try {
      await _ensureInitialized();
      final channelName = 'private-driver.$driverId';
      _driverHandlers[channelName] = _DriverSocketHandlers(
        onOrderAssigned: onOrderAssigned,
      );
      if (_subscribedChannels.contains(channelName)) return;
      debugPrint('Pusher subscribing to $channelName');

      await _pusher!.subscribe(
        channelName: channelName,
        onEvent: _handlePusherEvent,
      );
      _subscribedChannels.add(channelName);
    } catch (e) {
      print('Driver WebSocket init error: $e');
    }
  }

  Future<String?> initCustomer(
    int userId, {
    required Function(Map<String, dynamic>) onOrderUpdate,
  }) async {
    final handlerId = 'customer_${++_nextCustomerHandlerId}';
    final channelName = 'private-user.$userId';
    _customerHandlers
        .putIfAbsent(channelName, () => {})[handlerId] = onOrderUpdate;
    try {
      await _ensureInitialized();
      if (_subscribedChannels.contains(channelName)) return handlerId;

      await _pusher!.subscribe(
        channelName: channelName,
        onEvent: _handlePusherEvent,
      );
      _subscribedChannels.add(channelName);
      return handlerId;
    } catch (e) {
      _customerHandlers[channelName]?.remove(handlerId);
      debugPrint('Customer WebSocket init error: $e');
      return null;
    }
  }

  void removeCustomerHandler(int userId, [String? handlerId]) {
    final channelName = 'private-user.$userId';
    if (handlerId == null) {
      _customerHandlers.remove(channelName);
      return;
    }
    final handlers = _customerHandlers[channelName];
    handlers?.remove(handlerId);
    if (handlers?.isEmpty == true) _customerHandlers.remove(channelName);
  }

  Future<void> initOrderChat(
    int orderId, {
    required Function(Map<String, dynamic>) onMessage,
  }) async {
    _orderChatHandlers[orderId] = onMessage;
    try {
      await _ensureInitialized();
      final channelName = 'private-order.$orderId';
      if (_subscribedChannels.contains(channelName)) return;
      await _pusher!.subscribe(
        channelName: channelName,
        onEvent: _handlePusherEvent,
      );
      _subscribedChannels.add(channelName);
    } catch (e) {
      debugPrint('Order chat WebSocket init error: $e');
    }
  }

  Future<void> _ensureInitialized() async {
    if (_pusher != null) return;

    final branding = await AppBrandingService.instance.loadBranding();
    final pusherKey = branding.resolvedPusherAppKey;
    final pusherCluster = branding.resolvedPusherAppCluster;
    if (pusherKey.isEmpty) {
      throw Exception('Pusher key is not configured in admin panel branding settings.');
    }

    _pusher = PusherChannelsFlutter.getInstance();

    await _pusher!.init(
      apiKey: pusherKey,
      cluster: pusherCluster,
      useTLS: true,
      onConnectionStateChange: (currentState, previousState) {
        debugPrint('Pusher state: $previousState -> $currentState');
        return true;
      },
      onSubscriptionSucceeded: (channelName, data) {
        debugPrint('Pusher subscription succeeded: $channelName');
      },
      onError: (message, code, error) {
        debugPrint('Pusher error: $message ($code) - $error');
      },
      onSubscriptionError: (message, error) {
        debugPrint('Pusher subscription error: $message - $error');
      },
      onEvent: _handlePusherEvent,
      onAuthorizer: (channelName, socketId, options) async {
        try {
          return await _authorizeChannel(channelName, socketId);
        } catch (e, stackTrace) {
          debugPrint('Pusher authorizer failed for $channelName: $e');
          debugPrintStack(stackTrace: stackTrace);
          // pusher_channels_flutter 2.4.0 force-casts every non-null iOS
          // authorizer result to [String: String]. If this exception crosses the
          // method channel, Flutter returns a FlutterError object and the plugin
          // aborts while casting it. A null result is explicitly handled by the
          // native plugin as an authorization failure.
          return null;
        }
      },
    );

    await _pusher!.connect();
  }

  void _handlePusherEvent(dynamic rawEvent) {
    if (rawEvent is! PusherEvent) {
      debugPrint('Ignoring unsupported Pusher event: ${rawEvent.runtimeType}');
      return;
    }
    final event = rawEvent;
    final eventName = _normalizeEventName(event.eventName);
    final data = _normalizeOrderPayload(_decodeEventData(event.data));
    debugPrint(
      'Pusher event: ${event.channelName} -> ${event.eventName} '
      'data: ${jsonEncode(data)}',
    );

    final restaurantHandlers = _restaurantHandlers[event.channelName];
    if (restaurantHandlers != null) {
      if (_isNewOrderEvent(eventName, data)) {
        restaurantHandlers.onNewOrder(data);
      } else if (_isOrderStatusEvent(eventName, data)) {
        restaurantHandlers.onOrderUpdate(data);
      }
      return;
    }

    final driverHandlers = _driverHandlers[event.channelName];
    if (driverHandlers != null && _isDriverOrderAssignedEvent(eventName, data)) {
      driverHandlers.onOrderAssigned(data);
      return;
    }

    final customerHandlers = _customerHandlers[event.channelName];
    if (customerHandlers != null && _isOrderStatusEvent(eventName, data)) {
      for (final handler in List.of(customerHandlers.values)) {
        handler(data);
      }
      return;
    }

    final orderId = _extractOrderId(data);
    final chatHandler = orderId != null ? _orderChatHandlers[orderId] : null;
    if (chatHandler != null && _isOrderChatEvent(eventName, data)) {
      chatHandler({
        ...data,
        '_event': eventName,
      });
    }
  }

  String _normalizeEventName(String eventName) {
    return eventName
        .replaceFirst(RegExp(r'^\.'), '')
        .replaceAll('\\', '')
        .toLowerCase();
  }

  bool _isNewOrderEvent(String eventName, Map<String, dynamic> data) {
    final type = data['type']?.toString().toLowerCase() ?? '';
    return eventName == 'new-order' ||
        eventName.endsWith(r'neworderevent') ||
        type == 'new_order' ||
        type == 'new-order' ||
        (data.containsKey('order_number') && data['status'] == 'pending');
  }

  bool _isOrderStatusEvent(String eventName, Map<String, dynamic> data) {
    return eventName == 'order-status-updated' ||
        eventName.endsWith(r'orderstatusupdatedevent') ||
        data.containsKey('status_label') ||
        data.containsKey('driver_id');
  }

  bool _isDriverOrderAssignedEvent(String eventName, Map<String, dynamic> data) {
    final type = data['type']?.toString().toLowerCase() ?? '';
    return eventName == 'driver-order-assigned' ||
        eventName.endsWith(r'driverorderassignedevent') ||
        type == 'driver_order_assigned' ||
        type == 'driver-order-assigned' ||
        (data.containsKey('restaurant_name') &&
            data.containsKey('delivery_address'));
  }

  bool _isOrderChatEvent(String eventName, Map<String, dynamic> data) {
    final type = data['type']?.toString().toLowerCase() ?? '';
    return eventName.contains('chat') ||
        eventName.contains('message') ||
        type.contains('chat') ||
        type.contains('message') ||
        (data.containsKey('message') && _extractOrderId(data) != null);
  }

  int? _extractOrderId(Map<String, dynamic> data) {
    final value = data['order_id'] ?? data['orderId'] ?? data['id'];
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  Future<Map<String, dynamic>> _authorizeChannel(
    String channelName,
    String socketId,
  ) async {
    if (channelName.startsWith('private-encrypted-')) {
      throw Exception(
        'Unexpected encrypted Pusher channel "$channelName". '
        'The backend broadcasts normal private channels.',
      );
    }

    final token = await ApiService().getToken();
    final authUri = _broadcastAuthUri();
    debugPrint(
      'Pusher authorizing $channelName at $authUri '
      'with token: ${token == null || token.isEmpty ? 'missing' : 'present'}',
    );

    final response = await http.post(
      authUri,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
      },
      body: jsonEncode({
        'socket_id': socketId,
        'channel_name': channelName,
      }),
    );

    debugPrint(
      'Pusher auth response for $channelName: '
      '${response.statusCode} ${response.body}',
    );

    if (response.statusCode < 200 || response.statusCode >= 300) {
      final message =
          'Pusher auth failed for $channelName: ${response.statusCode} ${response.body}';
      debugPrint(message);
      throw Exception(message);
    }

    final decoded = jsonDecode(response.body);
    if (decoded is! Map) {
      throw Exception('Pusher auth returned invalid JSON: ${response.body}');
    }

    final authData = _normalizeAuthResponse(decoded, channelName);
    final auth = authData['auth'];
    if (auth == null || auth.isEmpty) {
      throw Exception('Pusher auth response missing "auth": ${response.body}');
    }

    return authData;
  }

  Map<String, String> _normalizeAuthResponse(
    Map<dynamic, dynamic> decoded,
    String channelName,
  ) {
    Map<dynamic, dynamic> authSource = decoded;
    for (final key in const ['data', 'result']) {
      final nested = decoded[key];
      if (nested is Map && nested['auth'] != null) {
        authSource = nested;
        break;
      }
    }

    final normalized = <String, String>{};
    for (final key in const ['auth', 'channel_data']) {
      final value = authSource[key];
      if (value is String && value.isNotEmpty) {
        normalized[key] = value;
      }
    }

    if (channelName.startsWith('private-encrypted-')) {
      final sharedSecret = authSource['shared_secret'];
      if (sharedSecret is String && sharedSecret.isNotEmpty) {
        normalized['shared_secret'] = sharedSecret;
      }
    }

    return normalized;
  }

  Uri _broadcastAuthUri() {
    return Uri.parse('${AppConfig.apiBaseUrl}/broadcasting/auth');
  }

  Map<String, dynamic> _decodeEventData(dynamic data) {
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    if (data is String && data.isNotEmpty) {
      final decoded = jsonDecode(data);
      if (decoded is Map<String, dynamic>) return decoded;
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
    }
    return <String, dynamic>{};
  }

  Map<String, dynamic> _normalizeOrderPayload(Map<String, dynamic> data) {
    for (final key in const ['order', 'order_data', 'data', 'payload']) {
      final nested = data[key];
      if (nested is Map<String, dynamic>) {
        return {...data, ...nested};
      }
      if (nested is Map) {
        return {...data, ...Map<String, dynamic>.from(nested)};
      }
    }
    return data;
  }
  
  void dispose() {
    _pusher?.disconnect();
    _pusher = null;
    _subscribedChannels.clear();
    _restaurantHandlers.clear();
    _driverHandlers.clear();
    _customerHandlers.clear();
    _orderChatHandlers.clear();
  }
}

class _RestaurantSocketHandlers {
  const _RestaurantSocketHandlers({
    required this.onNewOrder,
    required this.onOrderUpdate,
  });

  final Function(Map<String, dynamic>) onNewOrder;
  final Function(Map<String, dynamic>) onOrderUpdate;
}

class _DriverSocketHandlers {
  const _DriverSocketHandlers({
    required this.onOrderAssigned,
  });

  final Function(Map<String, dynamic>) onOrderAssigned;
}
