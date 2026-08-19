import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../config/api_constants.dart';

class RestaurantProvider extends ChangeNotifier {
  final ApiService _api = ApiService();

  Map<String, dynamic>? _restaurant;
  List<dynamic> _pendingOrders = [];
  List<dynamic> _activeOrders = [];
  Map<String, dynamic> _stats = {};
  bool _isLoading = false;
  bool? _isOpen;
  String? _error;

  Map<String, dynamic>? get restaurant => _restaurant;
  List<dynamic> get pendingOrders => _pendingOrders;
  List<dynamic> get activeOrders => _activeOrders;
  bool get isLoading => _isLoading;
  bool? get isOpen => _isOpen;
  String? get error => _error;

  int get todayOrders => _stats['today_orders'] ?? 0;
  double get todayRevenue => (_stats['today_revenue'] ?? 0).toDouble();
  int get pendingOrdersCount => _stats['pending_orders_count'] ?? 0;
  int get totalOrders => _stats['total_orders'] ?? 0;
  double get totalRevenue => (_stats['total_revenue'] ?? 0).toDouble();
  int get totalCustomers => _stats['total_customers'] ?? 0;
  int get totalMenuItems => _stats['total_menu_items'] ?? 0;

  Future<void> loadDashboardData() async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _api.get(ApiConstants.restaurantDashboard);

      if (response['success'] == true) {
        final data = response['data'];
        _restaurant = data['restaurant'];
        _stats = data['stats'];
        _pendingOrders = data['pending_orders'] ?? [];
        _activeOrders = data['active_orders'] ?? [];
        _isOpen = _restaurant?['is_open'] ?? false;
      } else {
        _error = response['message'] ?? 'Failed to load dashboard';
      }
    } catch (e) {
      _error = e.toString();
      debugPrint('Load dashboard error: $e');
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> toggleRestaurantStatus() async {
    try {
      final newStatus = !(_isOpen ?? false);
      final response = await _api.post(
        ApiConstants.restaurantToggleStatus,
        data: {'is_open': newStatus},
      );

      if (response['success'] == true) {
        _isOpen = response['data']['is_open'];
        _stats['is_open'] = _isOpen;
        if (_restaurant != null) {
          _restaurant!['is_open'] = _isOpen;
        }
        notifyListeners();
      } else {
        throw Exception(response['message'] ?? 'Failed to toggle status');
      }
    } catch (e) {
      debugPrint('Toggle status error: $e');
      rethrow;
    }
  }

  Future<bool> acceptOrder(
    int orderId, {
    int? preparationTimeMinutes,
  }) async {
    try {
      final response = await _api.post(
        ApiConstants.restaurantAcceptOrder(orderId),
        data: {
          if (preparationTimeMinutes != null)
            'preparation_time_minutes': preparationTimeMinutes,
        },
      );
      if (response['success'] == true) {
        await loadDashboardData();
        return true;
      }
      return false;
    } catch (e) {
      debugPrint('Accept order error: $e');
      return false;
    }
  }

  Future<bool> rejectOrder(int orderId, String reason) async {
    try {
      final response = await _api.post(
        ApiConstants.restaurantRejectOrder(orderId),
        data: {'reason': reason},
      );
      if (response['success'] == true) {
        await loadDashboardData();
        return true;
      }
      return false;
    } catch (e) {
      debugPrint('Reject order error: $e');
      return false;
    }
  }

  Future<bool> updateOrderStatus(int orderId, String status) async {
    try {
      if (status == 'confirmed') {
        return acceptOrder(orderId);
      }

      if (status == 'ready_for_pickup') {
        return markOrderReady(orderId);
      }

      final response = await _api.post(
        ApiConstants.restaurantOrderStatus(orderId),
        data: {'status': status},
      );
      if (response['success'] == true) {
        await loadDashboardData();
        return true;
      }
      return false;
    } catch (e) {
      debugPrint('Update order status error: $e');
      return false;
    }
  }

  Future<bool> markOrderReady(int orderId) async {
    try {
      final response =
          await _api.post(ApiConstants.restaurantOrderReady(orderId));
      if (response['success'] == true) {
        await loadDashboardData();
        return true;
      }
      return false;
    } catch (e) {
      debugPrint('Mark order ready error: $e');
      return false;
    }
  }

  Future<List<dynamic>> fetchOrders({String? status, String? search}) async {
    try {
      final Map<String, dynamic> params = {};
      if (status != null && status != 'all') params['status'] = status;
      if (search != null && search.isNotEmpty) params['search'] = search;

      final response =
          await _api.get(ApiConstants.restaurantOrders, queryParams: params);

      if (response['success'] == true) {
        return response['data'] ?? [];
      }
      return [];
    } catch (e) {
      debugPrint('Fetch orders error: $e');
      return [];
    }
  }

  void addNewOrder(Map<String, dynamic> order) {
    _pendingOrders.insert(0, order);
    _stats['pending_orders_count'] = _pendingOrders.length;
    notifyListeners();
  }

  void updateOrder(Map<String, dynamic> updatedOrder) {
    final pendingIndex =
        _pendingOrders.indexWhere((o) => o['id'] == updatedOrder['id']);
    if (pendingIndex != -1) {
      if (updatedOrder['status'] != 'pending') {
        _pendingOrders.removeAt(pendingIndex);
        _activeOrders.insert(0, updatedOrder);
      } else {
        _pendingOrders[pendingIndex] = updatedOrder;
      }
    }

    final activeIndex =
        _activeOrders.indexWhere((o) => o['id'] == updatedOrder['id']);
    if (activeIndex != -1) {
      _activeOrders[activeIndex] = updatedOrder;
    }

    _stats['pending_orders_count'] = _pendingOrders.length;
    notifyListeners();
  }
}
