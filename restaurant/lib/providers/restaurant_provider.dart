import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';
import '../config/api_constants.dart';

class RestaurantProvider extends ChangeNotifier {
  final ApiService _api = ApiService();

  Map<String, dynamic>? _restaurant;
  List<Map<String, dynamic>> _restaurants = [];
  int? _selectedRestaurantId;
  List<dynamic> _pendingOrders = [];
  List<dynamic> _activeOrders = [];
  Map<String, dynamic> _stats = {};
  bool _isLoading = false;
  bool? _isOpen;
  String? _error;

  Map<String, dynamic>? get restaurant => _restaurant;
  List<Map<String, dynamic>> get restaurants => _restaurants;
  int? get selectedRestaurantId => _selectedRestaurantId;
  bool get isAllRestaurantsSelected => _selectedRestaurantId == null;
  String get selectedRestaurantLabel {
    if (_selectedRestaurantId == null) return 'All Restaurants';
    final selected = _restaurants.where(
      (item) => _parseId(item['id']) == _selectedRestaurantId,
    );
    return selected.isNotEmpty
        ? selected.first['name']?.toString() ?? 'Restaurant'
        : 'Restaurant';
  }

  List<dynamic> get pendingOrders => _pendingOrders;
  List<dynamic> get activeOrders => _activeOrders;
  Map<String, dynamic> get stats => Map<String, dynamic>.from(_stats);
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

  Map<String, dynamic> get _restaurantQueryParams => {
        'restaurant_id': _selectedRestaurantId?.toString() ?? 'all',
      };

  Future<void> loadRestaurants() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      _selectedRestaurantId ??=
          _parseId(prefs.getString('selected_restaurant_id'));
      final response = await _api.get(ApiConstants.restaurantStores);
      if (response['success'] == true && response['data'] is List) {
        _restaurants = (response['data'] as List)
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
        if (_selectedRestaurantId != null &&
            !_restaurants
                .any((item) => _parseId(item['id']) == _selectedRestaurantId)) {
          _selectedRestaurantId = null;
        }
        notifyListeners();
      }
    } catch (e) {
      debugPrint('Load restaurants error: $e');
    }
  }

  Future<void> selectRestaurant(int? restaurantId) async {
    if (_selectedRestaurantId == restaurantId) return;
    _selectedRestaurantId = restaurantId;
    final prefs = await SharedPreferences.getInstance();
    if (restaurantId == null) {
      await prefs.remove('selected_restaurant_id');
    } else {
      await prefs.setString('selected_restaurant_id', restaurantId.toString());
    }
    await loadDashboardData();
  }

  Future<void> loadDashboardData() async {
    _isLoading = true;
    notifyListeners();

    try {
      if (_restaurants.isEmpty) {
        final storesResponse = await _api.get(ApiConstants.restaurantStores);
        if (storesResponse['success'] == true &&
            storesResponse['data'] is List) {
          _restaurants = (storesResponse['data'] as List)
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList();
        }
      }

      final response = await _api.get(
        ApiConstants.restaurantDashboard,
        queryParams: _restaurantQueryParams,
      );

      if (response['success'] == true) {
        final data = response['data'];
        _restaurant = data['restaurant'];
        if (data['restaurants'] is List) {
          _restaurants = (data['restaurants'] as List)
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList();
        }
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
        queryParams: _restaurantQueryParams,
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
      params.addAll(_restaurantQueryParams);
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
    final orderId = _parseId(order['id'] ?? order['order_id']);
    if (orderId != null) {
      _pendingOrders.removeWhere((item) {
        if (item is! Map) return false;
        return _parseId(item['id'] ?? item['order_id']) == orderId;
      });
    }
    _pendingOrders.insert(0, order);
    _stats['today_orders'] = _parseCount(_stats['today_orders']) + 1;
    _stats['total_orders'] = _parseCount(_stats['total_orders']) + 1;
    _stats['pending_orders_count'] = _pendingOrders.length;
    notifyListeners();
  }

  int? _parseId(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  int _parseCount(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  void updateOrder(Map<String, dynamic> updatedOrder) {
    final updatedOrderId =
        _parseId(updatedOrder['id'] ?? updatedOrder['order_id']);
    final pendingIndex = _pendingOrders.indexWhere((o) {
      if (o is! Map) return false;
      return _parseId(o['id'] ?? o['order_id']) == updatedOrderId;
    });
    if (pendingIndex != -1) {
      if (updatedOrder['status'] != 'pending') {
        _pendingOrders.removeAt(pendingIndex);
        _activeOrders.insert(0, updatedOrder);
      } else {
        _pendingOrders[pendingIndex] = updatedOrder;
      }
    }

    final activeIndex = _activeOrders.indexWhere((o) {
      if (o is! Map) return false;
      return _parseId(o['id'] ?? o['order_id']) == updatedOrderId;
    });
    if (activeIndex != -1) {
      _activeOrders[activeIndex] = updatedOrder;
    }

    _stats['pending_orders_count'] = _pendingOrders.length;
    notifyListeners();
  }
}
