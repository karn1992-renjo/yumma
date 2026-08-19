import 'dart:async';

class RestaurantOrderRealtimeService {
  RestaurantOrderRealtimeService._();

  static final RestaurantOrderRealtimeService instance =
      RestaurantOrderRealtimeService._();

  final StreamController<Map<String, dynamic>> _controller =
      StreamController<Map<String, dynamic>>.broadcast(sync: true);

  Stream<Map<String, dynamic>> get updates => _controller.stream;

  void publish(dynamic payload) {
    if (payload is! Map) return;
    final normalized = Map<String, dynamic>.from(payload);
    final nestedOrder = normalized['order'];
    final order = nestedOrder is Map
        ? Map<String, dynamic>.from(nestedOrder)
        : normalized;
    if (order['id'] == null && order['order_id'] == null) return;
    _controller.add(order);
  }
}
