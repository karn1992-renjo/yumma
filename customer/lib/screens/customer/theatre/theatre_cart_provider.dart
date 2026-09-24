import 'package:flutter/foundation.dart';

import 'theatre_models.dart';

/// swaad-only: the multi-stall cart for a theatre seat order. Deliberately
/// a NEW, separate provider from the main app's CartProvider -- that one is
/// built around the platform-wide "one order = one restaurant" assumption,
/// and this flow's whole point is letting one checkout span several stalls.
/// Reset once a checkout succeeds or the flow is abandoned.
class TheatreCartProvider extends ChangeNotifier {
  TheatreSummary? theatre;
  TheatreScreenSeatMap? screen;
  String? seatLabel;

  /// restaurantId -> (restaurant, its cart lines)
  final Map<int, TheatreRestaurant> _stallInfo = {};
  final Map<int, Map<int, TheatreCartLine>> _stallLines = {};

  void setVenue({
    required TheatreSummary theatre,
    required TheatreScreenSeatMap screen,
    required String seatLabel,
  }) {
    this.theatre = theatre;
    this.screen = screen;
    this.seatLabel = seatLabel;
    notifyListeners();
  }

  List<int> get stallRestaurantIds => _stallLines.keys.toList(growable: false);

  TheatreRestaurant? stallInfo(int restaurantId) => _stallInfo[restaurantId];

  List<TheatreCartLine> linesFor(int restaurantId) =>
      _stallLines[restaurantId]?.values.toList(growable: false) ?? const [];

  int quantityFor(int restaurantId, int menuItemId) =>
      _stallLines[restaurantId]?[menuItemId]?.quantity ?? 0;

  void addItem({
    required TheatreRestaurant restaurant,
    required int menuItemId,
    required String name,
    required double price,
  }) {
    _stallInfo[restaurant.id] = restaurant;
    final lines = _stallLines.putIfAbsent(restaurant.id, () => {});
    final existing = lines[menuItemId];
    if (existing != null) {
      existing.quantity += 1;
    } else {
      lines[menuItemId] = TheatreCartLine(
        menuItemId: menuItemId,
        name: name,
        price: price,
      );
    }
    notifyListeners();
  }

  void removeItem({required int restaurantId, required int menuItemId}) {
    final lines = _stallLines[restaurantId];
    if (lines == null) return;
    final existing = lines[menuItemId];
    if (existing == null) return;

    if (existing.quantity > 1) {
      existing.quantity -= 1;
    } else {
      lines.remove(menuItemId);
      if (lines.isEmpty) {
        _stallLines.remove(restaurantId);
        _stallInfo.remove(restaurantId);
      }
    }
    notifyListeners();
  }

  bool get isEmpty => _stallLines.isEmpty;

  int get totalItemCount => _stallLines.values
      .expand((lines) => lines.values)
      .fold(0, (sum, line) => sum + line.quantity);

  double get total => _stallLines.values
      .expand((lines) => lines.values)
      .fold(0.0, (sum, line) => sum + line.lineTotal);

  /// The exact `stalls` shape Api\TheatreController::checkout() expects.
  List<Map<String, dynamic>> toCheckoutStalls() {
    return _stallLines.entries.map((entry) {
      return {
        'restaurant_id': entry.key,
        'items': entry.value.values.map((line) => line.toJson()).toList(),
      };
    }).toList();
  }

  void reset() {
    theatre = null;
    screen = null;
    seatLabel = null;
    _stallInfo.clear();
    _stallLines.clear();
    notifyListeners();
  }
}
