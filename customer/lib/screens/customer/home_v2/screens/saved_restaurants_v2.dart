import 'package:flutter/material.dart';

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_cards.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class SavedRestaurantsV2 extends StatefulWidget {
  const SavedRestaurantsV2({super.key});

  @override
  State<SavedRestaurantsV2> createState() => _SavedRestaurantsV2State();
}

class _SavedRestaurantsV2State extends State<SavedRestaurantsV2> {
  final ApiService _api = ApiService();
  bool _loading = true;
  List<Map<String, dynamic>> _items = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await _api.get(ApiConstants.savedRestaurants);
      _items = v2MapList(v2ExtractList(res));
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      title: 'Saved Restaurants',
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : _items.isEmpty
              ? const V2EmptyState(
                  icon: Icons.favorite_border_rounded,
                  title: 'No saved restaurants',
                  message: 'Tap the heart on a restaurant to save it here.',
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  color: p.accent,
                  backgroundColor: p.bgMid,
                  child: ListView.separated(
                    padding: EdgeInsets.fromLTRB(
                        16, 10, 16, MediaQuery.of(context).padding.bottom + 30),
                    itemCount: _items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 14),
                    itemBuilder: (_, i) => V2Entrance(
                      delay: Duration(milliseconds: 22 * i.clamp(0, 8)),
                      child: RestaurantBigCardV2(
                        data: _items[i],
                        onTap: () => v2OpenRestaurant(
                            context, v2RestaurantId(_items[i])),
                      ),
                    ),
                  ),
                ),
    );
  }
}
