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
  final bool isPromotionReward;
  final int? promotionId;
  final String? promotionTitle;
  final String? promotionCouponCode;
  final String? promotionGroupKey;
  final int? promotionGroupSize;
  final double? promotionDealPrice;
  final double? promotionOriginalPrice;

  CartItem({
    required this.menuItem,
    this.quantity = 1,
    this.selectedVariant,
    this.selectedAddOns = const [],
    this.isPromotionReward = false,
    this.promotionId,
    this.promotionTitle,
    this.promotionCouponCode,
    this.promotionGroupKey,
    this.promotionGroupSize,
    this.promotionDealPrice,
    this.promotionOriginalPrice,
  });

  double get unitPrice => isPromotionReward
      ? 0
      : menuItem.finalPrice +
          (selectedVariant?.price ?? 0) +
          selectedAddOns.fold(0.0, (sum, option) => sum + option.price);

  double get totalPrice => unitPrice * quantity;

  double get displayUnitPrice {
    final dealPrice = promotionDealPrice ?? 0;
    final originalPrice = promotionOriginalPrice ?? 0;
    if (isPromotionReward || dealPrice <= 0 || originalPrice <= 0) {
      return unitPrice;
    }
    return unitPrice * (dealPrice / originalPrice);
  }

  double get displayTotalPrice => displayUnitPrice * quantity;

  String get signature => [
        isPromotionReward ? 'promotion_reward' : 'paid',
        if (promotionId != null) promotionId.toString(),
        if ((promotionGroupKey ?? '').trim().isNotEmpty) promotionGroupKey!,
        menuItem.id.toString(),
        selectedVariant?.name ?? '',
        ...selectedAddOns.map((option) => option.name),
      ].join('|');

  Map<String, dynamic> toJson() => {
        ...menuItem.toJson(),
        'menu_item_id': menuItem.id,
        'cart_unit_price': unitPrice,
        'quantity': quantity,
        'selected_variant': selectedVariant?.toJson(),
        'selected_add_ons':
            selectedAddOns.map((option) => option.toJson()).toList(),
        'line_type': isPromotionReward ? 'promotion_reward' : 'paid',
        'is_promotion_reward': isPromotionReward,
        'promotion_id': promotionId,
        'promotion_title': promotionTitle,
        'promotion_coupon_code': promotionCouponCode,
        'promotion_group_key': promotionGroupKey,
        'promotion_group_size': promotionGroupSize,
        'promotion_deal_price': promotionDealPrice,
        'promotion_original_price': promotionOriginalPrice,
      };

  factory CartItem.fromJson(Map<String, dynamic> json, MenuItem item) {
    return CartItem(
      menuItem: item,
      quantity: _parseQuantity(json['quantity']),
      selectedVariant: _parseOption(json['selected_variant']),
      selectedAddOns: _parseOptionList(
        json['selected_add_ons'] ?? json['selected_addons'],
      ),
      isPromotionReward: _parseBool(
        json['is_promotion_reward'] ??
            json['is_reward_item'] ??
            ((json['line_type']?.toString() ?? '') == 'promotion_reward'),
      ),
      promotionId: _parseNullableInt(json['promotion_id']),
      promotionTitle: json['promotion_title']?.toString(),
      promotionCouponCode: json['promotion_coupon_code']?.toString(),
      promotionGroupKey: json['promotion_group_key']?.toString(),
      promotionGroupSize: _parseNullableInt(json['promotion_group_size']),
      promotionDealPrice: _parseNullableDouble(json['promotion_deal_price']),
      promotionOriginalPrice:
          _parseNullableDouble(json['promotion_original_price']),
    );
  }

  static int _parseQuantity(dynamic value) {
    if (value is int) return value > 0 ? value : 1;
    if (value is num) return value.toInt() > 0 ? value.toInt() : 1;
    final parsed = int.tryParse(value?.toString() ?? '');
    return parsed != null && parsed > 0 ? parsed : 1;
  }

  static MenuOption? _parseOption(dynamic value) {
    if (value == null) return null;
    final option = MenuOption.fromJson(value);
    return option.name.trim().isEmpty ? null : option;
  }

  static List<MenuOption> _parseOptionList(dynamic value) {
    if (value is! List) return const [];
    return value
        .map(_parseOption)
        .whereType<MenuOption>()
        .toList(growable: false);
  }

  static bool _parseBool(dynamic value) {
    if (value is bool) return value;
    if (value is num) return value != 0;
    final normalized = value?.toString().toLowerCase().trim();
    return normalized == '1' || normalized == 'true' || normalized == 'yes';
  }

  static int? _parseNullableInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  static double? _parseNullableDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }
}

class RestaurantCart {
  final Restaurant restaurant;
  final List<CartItem> items;

  RestaurantCart({
    required this.restaurant,
    required List<CartItem> items,
  }) : items = List<CartItem>.from(items);

  int get itemCount => items.fold(0, (sum, item) => sum + item.quantity);
  double get subtotal => items.fold(0, (sum, item) => sum + item.totalPrice);
  double get displaySubtotal => _promotionAwareDisplaySubtotal(items);
  double get total => subtotal + restaurant.deliveryFee;
  double get displayTotal => displaySubtotal + restaurant.deliveryFee;

  Map<String, dynamic> toJson() => {
        'restaurant': restaurant.toJson(),
        'items': items
            .where((item) => !item.isPromotionReward)
            .map((item) => item.toJson())
            .toList(),
      };

  factory RestaurantCart.fromJson(Map<String, dynamic> json) {
    final restaurant = Restaurant.fromJson(
      Map<String, dynamic>.from(json['restaurant'] as Map),
    );
    final rawItems = json['items'];
    final items = rawItems is List
        ? rawItems.whereType<Map>().map((itemJson) {
            final itemData = Map<String, dynamic>.from(itemJson);
            return CartItem.fromJson(itemData, MenuItem.fromJson(itemData));
          }).where((item) {
            return !item.isPromotionReward &&
                item.menuItem.id > 0 &&
                item.quantity > 0;
          }).toList(growable: false)
        : <CartItem>[];

    return RestaurantCart(restaurant: restaurant, items: items);
  }
}

double _promotionAwareDisplaySubtotal(List<CartItem> items) {
  var total = 0.0;
  final groups = <String, _PromotionSubtotalGroup>{};

  for (final item in items) {
    final dealPrice = item.promotionDealPrice ?? 0;
    final promotionId = item.promotionId;
    if (!item.isPromotionReward && promotionId != null && dealPrice > 0) {
      final key = [
        promotionId,
        item.promotionGroupKey?.trim() ?? '',
        dealPrice.toStringAsFixed(2),
      ].join('|');
      groups
          .putIfAbsent(
            key,
            () => _PromotionSubtotalGroup(
              dealPrice,
              expectedSize: item.promotionGroupSize,
            ),
          )
          .lines
          .add(item);
      continue;
    }

    total += item.displayTotalPrice;
  }

  for (final group in groups.values) {
    total += group.total;
  }

  return total;
}

double _promotionAwareDisplayLineTotal(List<CartItem> items, CartItem target) {
  final dealPrice = target.promotionDealPrice ?? 0;
  final promotionId = target.promotionId;
  if (target.isPromotionReward || promotionId == null || dealPrice <= 0) {
    return target.displayTotalPrice;
  }

  final groupKey = target.promotionGroupKey?.trim() ?? '';
  final lines = items
      .where(
        (item) =>
            !item.isPromotionReward &&
            item.promotionId == promotionId &&
            (item.promotionGroupKey?.trim() ?? '') == groupKey &&
            (item.promotionDealPrice ?? 0).toStringAsFixed(2) ==
                dealPrice.toStringAsFixed(2),
      )
      .toList(growable: false);
  final requiredLines =
      target.promotionGroupSize ?? (lines.length > 1 ? lines.length : 2);
  if (lines.length < requiredLines) {
    return target.totalPrice;
  }

  final setCount = lines
      .map((item) => item.quantity)
      .reduce((a, b) => a < b ? a : b)
      .clamp(1, 999)
      .toInt();
  final originalPrice = target.promotionOriginalPrice ?? 0;
  final ratio = originalPrice > 0 ? dealPrice / originalPrice : 1.0;
  final discountedUnits =
      target.quantity < setCount ? target.quantity : setCount;
  final extraUnits = target.quantity - discountedUnits;
  return (target.unitPrice * discountedUnits * ratio) +
      (extraUnits > 0 ? target.unitPrice * extraUnits : 0);
}

class _PromotionSubtotalGroup {
  _PromotionSubtotalGroup(this.dealPrice, {int? expectedSize})
      : expectedSize =
            expectedSize == null || expectedSize < 1 ? null : expectedSize;

  final double dealPrice;
  final int? expectedSize;
  final List<CartItem> lines = [];

  double get total {
    if (lines.isEmpty) return 0;
    final requiredLines = expectedSize ?? (lines.length > 1 ? lines.length : 2);
    if (lines.length < requiredLines) {
      return lines.fold(0.0, (sum, item) => sum + item.totalPrice);
    }

    final sets = lines
        .map((item) => item.quantity)
        .reduce((a, b) => a < b ? a : b)
        .clamp(1, 999)
        .toInt();
    final extraTotal = lines.fold(0.0, (sum, item) {
      final extraQuantity = item.quantity - sets;
      if (extraQuantity <= 0) return sum;
      return sum + (item.unitPrice * extraQuantity);
    });
    return (dealPrice * sets) + extraTotal;
  }
}

class CartProvider extends ChangeNotifier {
  List<CartItem> _items = [];
  Restaurant? _restaurant;
  final Map<int, RestaurantCart> _restaurantCarts = {};
  int? _activeRestaurantId;

  List<CartItem> get items => _items;
  List<CartItem> get paidItems =>
      _items.where((item) => !item.isPromotionReward).toList(growable: false);
  List<CartItem> get promotionRewardItems =>
      _items.where((item) => item.isPromotionReward).toList(growable: false);
  Restaurant? get restaurant => _restaurant;
  List<RestaurantCart> get carts => _restaurantCarts.values
      .where((cart) => cart.items.any((item) => !item.isPromotionReward))
      .toList(growable: false);
  int get cartCount => carts.length;
  int get totalCartItemCount =>
      carts.fold(0, (sum, cart) => sum + cart.itemCount);

  int get itemCount => _items.fold(0, (sum, item) => sum + item.quantity);
  int get paidItemCount =>
      paidItems.fold(0, (sum, item) => sum + item.quantity);

  String get promotionCouponCode {
    for (final item in paidItems) {
      final code = item.promotionCouponCode?.trim() ?? '';
      if (code.isNotEmpty && code != 'null') return code;
    }
    return '';
  }

  double get subtotal => _items.fold(0, (sum, item) => sum + item.totalPrice);

  double get displaySubtotal => _promotionAwareDisplaySubtotal(_items);

  double displayTotalForCartItem(CartItem item) =>
      _promotionAwareDisplayLineTotal(_items, item);

  double get promotionDisplayDiscount =>
      (subtotal - displaySubtotal).clamp(0.0, double.infinity).toDouble();

  double get deliveryFee => _restaurant?.deliveryFee ?? 0;

  double get platformFee => 0;

  double get tax => 0;

  double get total => subtotal + deliveryFee + platformFee + tax;

  double get displayTotal => displaySubtotal + deliveryFee + platformFee + tax;

  bool get isEmpty => _items.isEmpty;

  bool get isNotEmpty => _items.isNotEmpty;

  int quantityFor(int menuItemId) {
    return _items
        .where(
            (item) => !item.isPromotionReward && item.menuItem.id == menuItemId)
        .fold(0, (sum, item) => sum + item.quantity);
  }

  int quantityForItem(MenuItem menuItem) {
    final exactQuantity = quantityFor(menuItem.id);
    if (exactQuantity > 0) return exactQuantity;

    final normalizedName = menuItem.name.trim().toLowerCase();
    return _items
        .where(
          (item) =>
              !item.isPromotionReward &&
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
    final index = _items.indexWhere(
      (item) => !item.isPromotionReward && item.signature == signature,
    );
    if (index == -1) return 0;
    return _items[index].quantity;
  }

  void addItem(
    MenuItem item,
    Restaurant restaurant, {
    MenuOption? selectedVariant,
    List<MenuOption> selectedAddOns = const [],
    int? promotionId,
    String? promotionTitle,
    String? promotionCouponCode,
    String? promotionGroupKey,
    int? promotionGroupSize,
    double? promotionDealPrice,
    double? promotionOriginalPrice,
  }) {
    _activateRestaurantCart(restaurant);

    final signature = _signatureFor(
      item.id,
      selectedVariant,
      selectedAddOns,
      promotionId: promotionId,
      promotionGroupKey: promotionGroupKey,
    );
    final existingIndex = _items.indexWhere(
      (i) => !i.isPromotionReward && i.signature == signature,
    );
    if (existingIndex != -1) {
      _items[existingIndex].quantity++;
    } else {
      _items.add(CartItem(
        menuItem: item,
        selectedVariant: selectedVariant,
        selectedAddOns: selectedAddOns,
        promotionId: promotionId,
        promotionTitle: promotionTitle,
        promotionCouponCode: promotionCouponCode,
        promotionGroupKey: promotionGroupKey,
        promotionGroupSize: promotionGroupSize,
        promotionDealPrice: promotionDealPrice,
        promotionOriginalPrice: promotionOriginalPrice,
      ));
    }

    _syncActiveCart();
    _saveCart();
    notifyListeners();
  }

  void setItemQuantity(
    MenuItem item,
    Restaurant restaurant,
    int quantity, {
    MenuOption? selectedVariant,
    List<MenuOption> selectedAddOns = const [],
    int? promotionId,
    String? promotionTitle,
    String? promotionCouponCode,
    String? promotionGroupKey,
    int? promotionGroupSize,
    double? promotionDealPrice,
    double? promotionOriginalPrice,
  }) {
    _activateRestaurantCart(restaurant);

    final signature = _signatureFor(
      item.id,
      selectedVariant,
      selectedAddOns,
      promotionId: promotionId,
      promotionGroupKey: promotionGroupKey,
    );

    if (quantity <= 0) {
      removeBySignature(signature);
      return;
    }

    final existingIndex = _items.indexWhere(
      (i) => !i.isPromotionReward && i.signature == signature,
    );
    if (existingIndex != -1) {
      _items[existingIndex].quantity = quantity;
    } else {
      _items.add(CartItem(
        menuItem: item,
        quantity: quantity,
        selectedVariant: selectedVariant,
        selectedAddOns: selectedAddOns,
        promotionId: promotionId,
        promotionTitle: promotionTitle,
        promotionCouponCode: promotionCouponCode,
        promotionGroupKey: promotionGroupKey,
        promotionGroupSize: promotionGroupSize,
        promotionDealPrice: promotionDealPrice,
        promotionOriginalPrice: promotionOriginalPrice,
      ));
    }

    _syncActiveCart();
    _saveCart();
    notifyListeners();
  }

  void removeItem(int menuItemId) {
    _items.removeWhere((item) => item.menuItem.id == menuItemId);
    if (_items.isEmpty) {
      _restaurant = null;
      if (_activeRestaurantId != null) {
        _restaurantCarts.remove(_activeRestaurantId);
        _activeRestaurantId = null;
      }
    } else {
      _syncActiveCart();
    }
    _saveCart();
    notifyListeners();
  }

  void removeBySignature(String signature) {
    _items.removeWhere((item) => item.signature == signature);
    if (_items.isEmpty) {
      _restaurant = null;
      if (_activeRestaurantId != null) {
        _restaurantCarts.remove(_activeRestaurantId);
        _activeRestaurantId = null;
      }
    } else {
      _syncActiveCart();
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
      if (_activeRestaurantId != null) {
        _restaurantCarts.remove(_activeRestaurantId);
        _activeRestaurantId = null;
      }
    } else {
      _syncActiveCart();
    }
    _saveCart();
    notifyListeners();
  }

  void incrementQuantity(int menuItemId) {
    final index = _items.indexWhere((item) => item.menuItem.id == menuItemId);
    if (index != -1) {
      _items[index].quantity++;
      _syncActiveCart();
      _saveCart();
      notifyListeners();
    }
  }

  void incrementBySignature(String signature) {
    final index = _items.indexWhere((item) => item.signature == signature);
    if (index == -1) return;
    _items[index].quantity++;
    _syncActiveCart();
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
          if (_activeRestaurantId != null) {
            _restaurantCarts.remove(_activeRestaurantId);
            _activeRestaurantId = null;
          }
        }
      } else {
        _items[index].quantity--;
      }
      if (_items.isNotEmpty) _syncActiveCart();
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
        if (_activeRestaurantId != null) {
          _restaurantCarts.remove(_activeRestaurantId);
          _activeRestaurantId = null;
        }
      }
    } else {
      _items[index].quantity--;
    }
    if (_items.isNotEmpty) _syncActiveCart();
    _saveCart();
    notifyListeners();
  }

  void clearCart() {
    if (_activeRestaurantId != null) {
      _restaurantCarts.remove(_activeRestaurantId);
    }
    _items.clear();
    _restaurant = null;
    _activeRestaurantId = null;
    _activateFirstAvailableCart();
    _saveCart();
    notifyListeners();
  }

  void clearAllCarts() {
    _restaurantCarts.clear();
    _items.clear();
    _restaurant = null;
    _activeRestaurantId = null;
    _saveCart();
    notifyListeners();
  }

  void removeCart(int restaurantId) {
    _restaurantCarts.remove(restaurantId);
    if (_activeRestaurantId == restaurantId) {
      _items.clear();
      _restaurant = null;
      _activeRestaurantId = null;
      _activateFirstAvailableCart();
    }
    _saveCart();
    notifyListeners();
  }

  void setActiveCart(int restaurantId) {
    final cart = _restaurantCarts[restaurantId];
    if (cart == null) return;
    _items = List<CartItem>.from(cart.items);
    _restaurant = cart.restaurant;
    _activeRestaurantId = restaurantId;
    _saveCart();
    notifyListeners();
  }

  Map<String, dynamic> getCheckoutData() {
    return {
      'restaurant_id': _restaurant?.id,
      'items': _items
          .where((item) => !item.isPromotionReward)
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
    _syncActiveCart();
    final prefs = await SharedPreferences.getInstance();
    final cartData = {
      'restaurant': _restaurant?.toJson(),
      'items': paidItems.map((item) => item.toJson()).toList(),
      'active_restaurant_id': _activeRestaurantId,
      'carts': carts.map((cart) => cart.toJson()).toList(),
    };
    await prefs.setString('cart', jsonEncode(cartData));
  }

  Future<void> loadCart() async {
    final prefs = await SharedPreferences.getInstance();
    final cartString = prefs.getString('cart');
    if (cartString != null) {
      try {
        final cartData = jsonDecode(cartString);
        final savedCarts = cartData['carts'];
        if (savedCarts is List) {
          _restaurantCarts
            ..clear()
            ..addEntries(
              savedCarts.whereType<Map>().map((cartJson) {
                final cart = RestaurantCart.fromJson(
                  Map<String, dynamic>.from(cartJson),
                );
                return MapEntry(cart.restaurant.id, cart);
              }).where((entry) => entry.value.items.isNotEmpty),
            );
          _activeRestaurantId = _parseNullableInt(
            cartData['active_restaurant_id'],
          );
          if (_activeRestaurantId != null &&
              _restaurantCarts.containsKey(_activeRestaurantId)) {
            setActiveCart(_activeRestaurantId!);
            return;
          }
          _activateFirstAvailableCart();
        } else if (cartData['restaurant'] != null) {
          _restaurant = Restaurant.fromJson(
            Map<String, dynamic>.from(cartData['restaurant'] as Map),
          );
          _activeRestaurantId = _restaurant?.id;
          final savedItems = cartData['items'];
          if (savedItems is List) {
            _items = savedItems
                .whereType<Map>()
                .map((itemJson) {
                  final itemData = Map<String, dynamic>.from(itemJson);
                  return CartItem.fromJson(
                    itemData,
                    MenuItem.fromJson(itemData),
                  );
                })
                .where((item) =>
                    !item.isPromotionReward &&
                    item.menuItem.id > 0 &&
                    item.quantity > 0)
                .toList(growable: false);
          }
        }

        if (_items.isEmpty) {
          _restaurant = null;
          _activeRestaurantId = null;
        } else {
          _syncActiveCart();
        }
      } catch (e) {
        debugPrint('Error loading cart: $e');
      }
    }
    notifyListeners();
  }

  String _signatureFor(
    int menuItemId,
    MenuOption? selectedVariant,
    List<MenuOption> selectedAddOns, {
    int? promotionId,
    String? promotionGroupKey,
  }) {
    return [
      'paid',
      if (promotionId != null) promotionId.toString(),
      if ((promotionGroupKey ?? '').trim().isNotEmpty) promotionGroupKey!,
      menuItemId.toString(),
      selectedVariant?.name ?? '',
      ...selectedAddOns.map((option) => option.name),
    ].join('|');
  }

  void _activateRestaurantCart(Restaurant restaurant) {
    if (_activeRestaurantId == restaurant.id &&
        _restaurant?.id == restaurant.id) {
      if (_isGenericRestaurantName(_restaurant?.name) &&
          !_isGenericRestaurantName(restaurant.name)) {
        _restaurant = restaurant;
        _syncActiveCart();
        _saveCart();
      }
      return;
    }

    _syncActiveCart();
    final existing = _restaurantCarts[restaurant.id];
    _restaurant = existing != null &&
            !_isGenericRestaurantName(existing.restaurant.name) &&
            _isGenericRestaurantName(restaurant.name)
        ? existing.restaurant
        : restaurant;
    _activeRestaurantId = restaurant.id;
    _items =
        existing == null ? <CartItem>[] : List<CartItem>.from(existing.items);
  }

  bool _isGenericRestaurantName(String? value) {
    final normalized = (value ?? '').trim().toLowerCase();
    return normalized.isEmpty || normalized == 'restaurant';
  }

  void _syncActiveCart() {
    final restaurant = _restaurant;
    final activeId = _activeRestaurantId;
    if (restaurant == null || activeId == null) return;

    if (paidItems.isEmpty) {
      _items.clear();
      _restaurantCarts.remove(activeId);
      _restaurant = null;
      _activeRestaurantId = null;
      _removeRewardOnlyCarts();
      _activateFirstAvailableCart();
      return;
    }

    _restaurantCarts[activeId] = RestaurantCart(
      restaurant: restaurant,
      items: _items,
    );
  }

  void _activateFirstAvailableCart() {
    _removeRewardOnlyCarts();
    if (_restaurantCarts.isEmpty) return;
    final first = _restaurantCarts.values.first;
    _restaurant = first.restaurant;
    _activeRestaurantId = first.restaurant.id;
    _items = List<CartItem>.from(first.items);
  }

  void _removeRewardOnlyCarts() {
    _restaurantCarts.removeWhere(
      (_, cart) => !cart.items.any((item) => !item.isPromotionReward),
    );
  }

  int? _parseNullableInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }
}
