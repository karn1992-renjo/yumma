// lib/providers/cart_provider.dart
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/menu_item.dart';
import '../models/restaurant.dart';

class CartItem {
  final MenuItem menuItem;
  int quantity;
  final MenuOption? selectedVariant;
  final List<MenuOption> selectedAddOns;

  CartItem({
    required this.menuItem,
    this.quantity = 1,
    this.selectedVariant,
    this.selectedAddOns = const [],
  });

  double get unitPrice =>
      menuItem.finalPrice +
      (selectedVariant?.price ?? 0) +
      selectedAddOns.fold(0.0, (sum, option) => sum + option.price);

  double get totalPrice => unitPrice * quantity;

  String get signature => [
        menuItem.id.toString(),
        selectedVariant?.name ?? '',
        ...selectedAddOns.map((option) => option.name),
      ].join('|');

  Map<String, dynamic> toJson() => {
        'menu_item_id': menuItem.id,
        'name': menuItem.name,
        'price': unitPrice,
        'quantity': quantity,
        'image': menuItem.imageUrl,
        'selected_variant': selectedVariant?.toJson(),
        'selected_add_ons':
            selectedAddOns.map((option) => option.toJson()).toList(),
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

  double get platformFee => 0;

  double get tax => 0;

  double get total => subtotal + deliveryFee + platformFee + tax;

  bool get isEmpty => _items.isEmpty;

  bool get isNotEmpty => _items.isNotEmpty;

  int quantityFor(int menuItemId) {
    return _items
        .where((item) => item.menuItem.id == menuItemId)
        .fold(0, (sum, item) => sum + item.quantity);
  }

  int quantityForItem(MenuItem menuItem) {
    final exactQuantity = quantityFor(menuItem.id);
    if (exactQuantity > 0) return exactQuantity;

    final normalizedName = menuItem.name.trim().toLowerCase();
    return _items
        .where(
          (item) =>
              item.menuItem.restaurantId == menuItem.restaurantId &&
              item.menuItem.name.trim().toLowerCase() == normalizedName,
        )
        .fold(0, (sum, item) => sum + item.quantity);
  }

  int quantityForSelection(
    int menuItemId, {
    MenuOption? selectedVariant,
    List<MenuOption> selectedAddOns = const [],
  }) {
    final signature =
        _signatureFor(menuItemId, selectedVariant, selectedAddOns);
    final index = _items.indexWhere((item) => item.signature == signature);
    if (index == -1) return 0;
    return _items[index].quantity;
  }

  void addItem(
    MenuItem item,
    Restaurant restaurant, {
    MenuOption? selectedVariant,
    List<MenuOption> selectedAddOns = const [],
  }) {
    // If different restaurant, clear cart first
    if (_restaurant != null && _restaurant!.id != restaurant.id) {
      clearCart();
    }

    _restaurant = restaurant;

    final signature = _signatureFor(item.id, selectedVariant, selectedAddOns);
    final existingIndex = _items.indexWhere((i) => i.signature == signature);
    if (existingIndex != -1) {
      _items[existingIndex].quantity++;
    } else {
      _items.add(CartItem(
        menuItem: item,
        selectedVariant: selectedVariant,
        selectedAddOns: selectedAddOns,
      ));
    }

    _saveCart();
    notifyListeners();
  }

  void setItemQuantity(
    MenuItem item,
    Restaurant restaurant,
    int quantity, {
    MenuOption? selectedVariant,
    List<MenuOption> selectedAddOns = const [],
  }) {
    if (_restaurant != null && _restaurant!.id != restaurant.id) {
      clearCart();
    }

    final signature = _signatureFor(item.id, selectedVariant, selectedAddOns);

    if (quantity <= 0) {
      removeBySignature(signature);
      return;
    }

    _restaurant = restaurant;
    final existingIndex = _items.indexWhere((i) => i.signature == signature);
    if (existingIndex != -1) {
      _items[existingIndex].quantity = quantity;
    } else {
      _items.add(CartItem(
        menuItem: item,
        quantity: quantity,
        selectedVariant: selectedVariant,
        selectedAddOns: selectedAddOns,
      ));
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

  void removeBySignature(String signature) {
    _items.removeWhere((item) => item.signature == signature);
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

  void incrementBySignature(String signature) {
    final index = _items.indexWhere((item) => item.signature == signature);
    if (index == -1) return;
    _items[index].quantity++;
    _saveCart();
    notifyListeners();
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

  void decrementBySignature(String signature) {
    final index = _items.indexWhere((item) => item.signature == signature);
    if (index == -1) return;
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

  void clearCart() {
    _items.clear();
    _restaurant = null;
    _saveCart();
    notifyListeners();
  }

  Map<String, dynamic> getCheckoutData() {
    return {
      'restaurant_id': _restaurant?.id,
      'items': _items
          .map((item) => {
                'id': item.menuItem.id,
                'quantity': item.quantity,
                'selected_variant': item.selectedVariant?.toJson(),
                'selected_add_ons': item.selectedAddOns
                    .map((option) => option.toJson())
                    .toList(),
              })
          .toList(),
      'subtotal': subtotal,
      'delivery_fee': deliveryFee,
      'platform_fee': platformFee,
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

  String _signatureFor(
    int menuItemId,
    MenuOption? selectedVariant,
    List<MenuOption> selectedAddOns,
  ) {
    return [
      menuItemId.toString(),
      selectedVariant?.name ?? '',
      ...selectedAddOns.map((option) => option.name),
    ].join('|');
  }
}
