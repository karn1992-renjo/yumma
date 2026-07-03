// lib/providers/order_provider.dart
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../config/api_constants.dart';
import '../models/order.dart';

class OrderProvider extends ChangeNotifier {
  final ApiService _api = ApiService();

  List<Order> _orders = [];
  Order? _currentOrder;
  bool _isLoading = false;
  String? _error;

  List<Order> get orders => _orders;
  Order? get currentOrder => _currentOrder;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Order? applyOrderStatusUpdate(Map<String, dynamic> data) {
    final rawId = data['order_id'] ?? data['id'];
    final orderId = rawId is num
        ? rawId.toInt()
        : int.tryParse(rawId?.toString() ?? '');
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
        final order = Order.fromJson(response['data']['order']);
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

  Future<List<Order>> fetchMyOrders({bool notifyLoading = true}) async {
    if (notifyLoading) {
      _setLoading(true);
    }

    try {
      final response = await _api.get(ApiConstants.myOrders);
      if (response['success'] == true) {
        final List<dynamic> ordersData =
            response['data']['data'] ?? response['data'];
        _orders = ordersData.map((json) => Order.fromJson(json)).toList();
        if (notifyLoading) {
          _setLoading(false);
        } else {
          notifyListeners();
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
  }) async {
    if (notifyLoading) {
      _setLoading(true);
    }

    try {
      final response = await _api.get('${ApiConstants.orderDetails}/$orderId');
      if (response['success'] == true) {
        final order = Order.fromJson(response['data']);
        _currentOrder = order;
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
