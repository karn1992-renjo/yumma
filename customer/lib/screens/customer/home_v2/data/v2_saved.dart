import 'package:flutter/foundation.dart';

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import 'v2_util.dart';

/// App-wide set of the customer's saved restaurant ids, so the bookmark icon
/// on every card reflects the same state and updates instantly on tap.
class V2Saved {
  V2Saved._();
  static final V2Saved instance = V2Saved._();

  final ApiService _api = ApiService();
  final ValueNotifier<Set<int>> ids = ValueNotifier<Set<int>>({});
  bool _loaded = false;

  bool contains(int id) => ids.value.contains(id);

  Future<void> ensureLoaded() async {
    if (_loaded) return;
    _loaded = true;
    try {
      final res = await _api.get(ApiConstants.savedRestaurants);
      final list = v2ExtractList(res);
      final next = <int>{};
      for (final r in list) {
        if (r is Map) {
          final id = v2RestaurantId(Map<String, dynamic>.from(r));
          if (id > 0) next.add(id);
        } else {
          final id = v2Int(r);
          if (id > 0) next.add(id);
        }
      }
      ids.value = next;
    } catch (_) {
      _loaded = false; // allow a retry
    }
  }

  /// Optimistic toggle — flips locally, then calls the API and reverts on error.
  Future<void> toggle(int id) async {
    if (id <= 0) return;
    final was = ids.value.contains(id);
    final next = Set<int>.from(ids.value);
    was ? next.remove(id) : next.add(id);
    ids.value = next;
    try {
      await _api.post(
        was
            ? ApiConstants.removeFavoriteRestaurant(id)
            : ApiConstants.favoriteRestaurant(id),
        data: const {},
      );
    } catch (_) {
      final revert = Set<int>.from(ids.value);
      was ? revert.add(id) : revert.remove(id);
      ids.value = revert;
    }
  }
}
