// lib/providers/cart_provider.dart
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/menu_item.dart';
import '../models/restaurant.dart';

class CartItem {
  final MenuItem menuItem;
  int quantity;

  CartItem({
    required this.menuItem,
    this.quantity = 1,
  });

  double get totalPrice => menuItem.finalPrice * quantity;

  Map<String, dynamic> toJson() => {
    'menu_item_id': menuItem.id,
    'name': menuItem.name,
    'price': menuItem.finalPrice,
    'quantity': quantity,
    'image': menuItem.imageUrl,
  };

  factory CartItem.fromJson(Map<String, dynamic> json, MenuItem item) {
    return CartItem(
      menuItem: item,
      quantity: json['quantity'],
    );
  }
}

class CartProvider extends ChangeNotifier {
  List<CartItem> _items = [];
  Restaurant? _restaurant;
  
  List<CartItem> get items => _items;
  Restaurant? get restaurant => _restaurant;
  
  int get itemCount => _items.fold(0, (sum, item) => sum + item.quantity);
  
  double get subtotal => _items.fold(0, (sum, item) => sum + item.totalPrice);
  
  double get deliveryFee => _restaurant?.deliveryFee ?? 0;
  
  double get tax => subtotal * 0.05; // 5% GST
  
  double get total => subtotal + deliveryFee + tax;
  
  bool get isEmpty => _items.isEmpty;
  
  bool get isNotEmpty => _items.isNotEmpty;

  void addItem(MenuItem item, Restaurant restaurant) {
    // If different restaurant, clear cart first
    if (_restaurant != null && _restaurant!.id != restaurant.id) {
      clearCart();
    }
    
    _restaurant = restaurant;
    
    final existingIndex = _items.indexWhere((i) => i.menuItem.id == item.id);
    if (existingIndex != -1) {
      _items[existingIndex].quantity++;
    } else {
      _items.add(CartItem(menuItem: item));
    }
    
    _saveCart();
    notifyListeners();
  }

  void removeItem(int menuItemId) {
    _items.removeWhere((item) => item.menuItem.id == menuItemId);
    if (_items.isEmpty) {
      _restaurant = null;
    }
    _saveCart();
    notifyListeners();
  }

  void updateQuantity(int menuItemId, int quantity) {
    final index = _items.indexWhere((item) => item.menuItem.id == menuItemId);
    if (index != -1) {
      if (quantity <= 0) {
        _items.removeAt(index);
      } else {
        _items[index].quantity = quantity;
      }
    }
    if (_items.isEmpty) {
      _restaurant = null;
    }
    _saveCart();
    notifyListeners();
  }

  void incrementQuantity(int menuItemId) {
    final index = _items.indexWhere((item) => item.menuItem.id == menuItemId);
    if (index != -1) {
      _items[index].quantity++;
      _saveCart();
      notifyListeners();
    }
  }

  void decrementQuantity(int menuItemId) {
    final index = _items.indexWhere((item) => item.menuItem.id == menuItemId);
    if (index != -1) {
      if (_items[index].quantity <= 1) {
        _items.removeAt(index);
        if (_items.isEmpty) {
          _restaurant = null;
        }
      } else {
        _items[index].quantity--;
      }
      _saveCart();
      notifyListeners();
    }
  }

  void clearCart() {
    _items.clear();
    _restaurant = null;
    _saveCart();
    notifyListeners();
  }

  Map<String, dynamic> getCheckoutData() {
    return {
      'restaurant_id': _restaurant?.id,
      'items': _items.map((item) => {
        'id': item.menuItem.id,
        'quantity': item.quantity,
      }).toList(),
      'subtotal': subtotal,
      'delivery_fee': deliveryFee,
      'tax': tax,
      'total': total,
    };
  }

  Future<void> _saveCart() async {
    final prefs = await SharedPreferences.getInstance();
    final cartData = {
      'restaurant': _restaurant?.toJson(),
      'items': _items.map((item) => item.toJson()).toList(),
    };
    await prefs.setString('cart', jsonEncode(cartData));
  }

  Future<void> loadCart() async {
    final prefs = await SharedPreferences.getInstance();
    final cartString = prefs.getString('cart');
    if (cartString != null) {
      try {
        final cartData = jsonDecode(cartString);
        if (cartData['restaurant'] != null) {
          _restaurant = Restaurant.fromJson(cartData['restaurant']);
        }
        // Note: MenuItem loading would need full menu items
        // This is simplified - in production, you'd fetch menu items by IDs
      } catch (e) {
        debugPrint('Error loading cart: $e');
      }
    }
    notifyListeners();
  }
}