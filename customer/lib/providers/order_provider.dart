// lib/providers/order_provider.dart
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../config/api_constants.dart';
import '../models/order.dart';
import '../models/scratch_card.dart';

class OrderProvider extends ChangeNotifier {
  final ApiService _api = ApiService();

  List<Order> _orders = [];
  Order? _currentOrder;
  List<ScratchCard> _lastCreatedScratchCards = const [];
  bool _isLoading = false;
  String? _error;

  List<Order> get orders => _orders;
  Order? get currentOrder => _currentOrder;
  List<ScratchCard> get lastCreatedScratchCards => _lastCreatedScratchCards;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Order? applyOrderStatusUpdate(Map<String, dynamic> data) {
    final rawId = data['order_id'] ?? data['id'];
    final orderId =
        rawId is num ? rawId.toInt() : int.tryParse(rawId?.toString() ?? '');
    final status = data['status']?.toString();
    if (orderId == null || status == null || status.trim().isEmpty) return null;

    final index = _orders.indexWhere((order) => order.id == orderId);
    if (index < 0) return null;

    final order = _orders[index];
    order.applyRealtimeStatus(status);
    if (_currentOrder?.id == orderId) {
      _currentOrder?.applyRealtimeStatus(status);
    }
    notifyListeners();
    return order;
  }

  Future<Order?> createOrder(Map<String, dynamic> orderData) async {
    _setLoading(true);
    _clearError();

    try {
      final response =
          await _api.post(ApiConstants.createOrder, data: orderData);
      if (response['success'] == true) {
        final data = response['data'] is Map
            ? Map<String, dynamic>.from(response['data'] as Map)
            : <String, dynamic>{};
        final order = Order.fromJson(
          Map<String, dynamic>.from(data['order'] as Map),
        );
        _lastCreatedScratchCards = _parseScratchCards(data['scratch_cards']);
        _currentOrder = order;
        _setLoading(false);
        return order;
      }
      throw Exception(response['message'] ?? 'Failed to create order');
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return null;
    }
  }

  Future<List<Order>> fetchMyOrders({
    bool notifyLoading = true,
    bool forceRefresh = false,
  }) async {
    if (notifyLoading && _orders.isEmpty) {
      _setLoading(true);
    }

    try {
      final response = await _api.get(
        ApiConstants.myOrders,
        cachePolicy: ApiCachePolicy.screen,
        cacheFirst: notifyLoading && !forceRefresh,
        refreshCached: notifyLoading && !forceRefresh,
        onCacheRefreshed: _applyOrdersResponse,
      );
      if (_applyOrdersResponse(response)) {
        if (notifyLoading) {
          _setLoading(false);
        }
        return _orders;
      }
      throw Exception(response['message'] ?? 'Failed to fetch orders');
    } catch (e) {
      _error = e.toString();
      if (notifyLoading) {
        _setLoading(false);
      } else {
        notifyListeners();
      }
      return [];
    }
  }

  Future<Order?> fetchOrderDetails(
    int orderId, {
    bool notifyLoading = true,
    bool preferCache = false,
  }) async {
    if (notifyLoading && _currentOrder?.id != orderId) {
      _setLoading(true);
    }

    try {
      final response = await _api.get(
        '${ApiConstants.orderDetails}/$orderId',
        cachePolicy: ApiCachePolicy.screen,
        cacheFirst: preferCache,
        refreshCached: preferCache,
        onCacheRefreshed: (fresh) => _applyOrderResponse(orderId, fresh),
      );
      final order = _applyOrderResponse(orderId, response);
      if (order != null) {
        if (notifyLoading) {
          _setLoading(false);
        }
        return order;
      }
      throw Exception(response['message'] ?? 'Failed to fetch order');
    } catch (e) {
      _error = e.toString();
      if (notifyLoading) {
        _setLoading(false);
      }
      return null;
    }
  }

  bool _applyOrdersResponse(dynamic response) {
    if (response is! Map || response['success'] != true) return false;
    final payload = response['data'];
    final rows = payload is Map ? payload['data'] : payload;
    if (rows is! List) return false;
    _orders = rows
        .whereType<Map>()
        .map((json) => Order.fromJson(Map<String, dynamic>.from(json)))
        .toList();
    _error = null;
    notifyListeners();
    return true;
  }

  Order? _applyOrderResponse(int orderId, dynamic response) {
    if (response is! Map ||
        response['success'] != true ||
        response['data'] is! Map) {
      return null;
    }
    final order = Order.fromJson(
      Map<String, dynamic>.from(response['data'] as Map),
    );
    if (order.id != orderId) return null;
    _currentOrder = order;
    final index = _orders.indexWhere((item) => item.id == order.id);
    if (index >= 0) _orders[index] = order;
    _error = null;
    notifyListeners();
    return order;
  }

  Future<bool> cancelOrder(int orderId, String reason) async {
    _setLoading(true);
    _clearError();

    try {
      final response =
          await _api.post(ApiConstants.cancelOrder(orderId), data: {
        'reason': reason,
      });
      if (response['success'] == true) {
        final index = _orders.indexWhere((order) => order.id == orderId);
        if (index >= 0) {
          _orders[index].applyRealtimeStatus('cancelled');
        }
        if (_currentOrder?.id == orderId) {
          _currentOrder?.applyRealtimeStatus('cancelled');
        }
        notifyListeners();
        await fetchMyOrders(notifyLoading: false);
        _setLoading(false);
        return true;
      }
      throw Exception(response['message'] ?? 'Failed to cancel order');
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  Future<Map<String, dynamic>?> trackOrder(int orderId) async {
    try {
      final response = await _api.get('${ApiConstants.trackOrder}/$orderId');
      if (response['success'] == true) {
        return response['data'];
      }
      return null;
    } catch (e) {
      debugPrint('Track order error: $e');
      return null;
    }
  }

  Future<bool> submitFeedback({
    required int orderId,
    required int restaurantRating,
    int? driverRating,
    int? itemRating,
    int? serviceRating,
    String? restaurantFeedback,
    String? driverFeedback,
    String? itemFeedback,
    String? serviceFeedback,
    List<Map<String, int>>? itemRatings,
  }) async {
    try {
      final response = await _api.post(
        ApiConstants.orderFeedback(orderId),
        data: {
          'restaurant_rating': restaurantRating,
          if (driverRating != null) 'driver_rating': driverRating,
          if (itemRating != null) 'item_rating': itemRating,
          if (serviceRating != null) 'service_rating': serviceRating,
          if (restaurantFeedback?.trim().isNotEmpty == true)
            'restaurant_feedback': restaurantFeedback!.trim(),
          if (driverFeedback?.trim().isNotEmpty == true)
            'driver_feedback': driverFeedback!.trim(),
          if (itemFeedback?.trim().isNotEmpty == true)
            'item_feedback': itemFeedback!.trim(),
          if (serviceFeedback?.trim().isNotEmpty == true)
            'service_feedback': serviceFeedback!.trim(),
          if (itemRatings != null && itemRatings.isNotEmpty)
            'items': itemRatings,
        },
      );

      if (response['success'] == true) {
        final updated =
            response['data'] != null ? Order.fromJson(response['data']) : null;
        if (updated != null) {
          _currentOrder = updated;
          final index = _orders.indexWhere((order) => order.id == orderId);
          if (index >= 0) {
            _orders[index] = updated;
          }
        }
        notifyListeners();
        return true;
      }
      throw Exception(response['message'] ?? 'Failed to submit feedback');
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> requestRefund(int orderId, String reason,
      {double? amount}) async {
    _setLoading(true);

    try {
      final Map<String, dynamic> data = {'reason': reason};
      if (amount != null) data['refund_amount'] = amount;

      final response =
          await _api.post(ApiConstants.requestRefund(orderId), data: data);
      if (response['success'] == true) {
        _setLoading(false);
        return true;
      }
      throw Exception(response['message'] ?? 'Failed to request refund');
    } catch (e) {
      _error = e.toString();
      _setLoading(false);
      return false;
    }
  }

  List<ScratchCard> _parseScratchCards(dynamic value) {
    if (value is! List) return const [];

    return value
        .whereType<Map>()
        .map((row) => ScratchCard.fromJson(Map<String, dynamic>.from(row)))
        .where((card) => card.id > 0)
        .toList(growable: false);
  }

  void _setLoading(bool loading) {
    if (_isLoading == loading) return;
    _isLoading = loading;
    notifyListeners();
  }

  void _clearError() {
    _error = null;
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void setCurrentOrder(Order? order) {
    _currentOrder = order;
    notifyListeners();
  }
}
