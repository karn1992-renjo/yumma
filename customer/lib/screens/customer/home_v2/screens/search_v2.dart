import 'dart:async';

import 'package:flutter/material.dart';

import '../../../../utils/currency_utils.dart';
import '../data/v2_repository.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_cards.dart';
import '../widgets/v2_dish_popup.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class SearchV2 extends StatefulWidget {
  const SearchV2({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<SearchV2> createState() => _SearchV2State();
}

class _SearchV2State extends State<SearchV2> {
  final V2Repository _repo = V2Repository.instance;
  final TextEditingController _controller = TextEditingController();
  Timer? _debounce;

  bool _loading = false;
  String _query = '';
  List<Map<String, dynamic>> _restaurants = const [];
  List<Map<String, dynamic>> _dishes = const [];

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () => _run(value));
  }

  Future<void> _run(String value) async {
    final q = value.trim();
    setState(() => _query = q);
    if (q.length < 2) {
      setState(() {
        _restaurants = const [];
        _dishes = const [];
        _loading = false;
      });
      return;
    }
    setState(() => _loading = true);
    try {
      final res = await _repo.search(q);
      if (!mounted || q != _query) return;
      setState(() {
        _restaurants = res.restaurants;
        _dishes = res.dishes;
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final body = _Body(
      controller: _controller,
      loading: _loading,
      query: _query,
      restaurants: _restaurants,
      dishes: _dishes,
      onChanged: _onChanged,
      onSubmit: _run,
      onClear: () {
        _controller.clear();
        _run('');
      },
    );
    if (widget.embedded) return body;
    return V2Scaffold(body: body);
  }
}

class _Body extends StatelessWidget {
  const _Body({
    required this.controller,
    required this.loading,
    required this.query,
    required this.restaurants,
    required this.dishes,
    required this.onChanged,
    required this.onSubmit,
    required this.onClear,
  });

  final TextEditingController controller;
  final bool loading;
  final String query;
  final List<Map<String, dynamic>> restaurants;
  final List<Map<String, dynamic>> dishes;
  final ValueChanged<String> onChanged;
  final ValueChanged<String> onSubmit;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final hasResults = restaurants.isNotEmpty || dishes.isNotEmpty;
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
          child: TextField(
            controller: controller,
            autofocus: !hasResults && query.isEmpty,
            onChanged: onChanged,
            onSubmitted: onSubmit,
            textInputAction: TextInputAction.search,
            keyboardAppearance:
                p.isDark ? Brightness.dark : Brightness.light,
            style: TextStyle(
                color: p.ink, fontSize: 14, fontWeight: FontWeight.w700),
            cursorColor: p.accent,
            decoration: InputDecoration(
              isDense: true,
              filled: true,
              fillColor: p.isDark ? const Color(0xFF1B2233) : Colors.white,
              hintText: 'Search restaurants & dishes',
              hintStyle: TextStyle(color: p.inkFaint, fontSize: 13.5),
              prefixIcon: Icon(Icons.search_rounded, size: 20, color: p.inkSoft),
              suffixIcon: controller.text.isEmpty
                  ? null
                  : IconButton(
                      icon: Icon(Icons.close_rounded,
                          size: 18, color: p.inkSoft),
                      onPressed: onClear,
                    ),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: BorderSide(color: p.glassBorder),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: BorderSide(color: p.glassBorder),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: BorderSide(color: p.accent, width: 1.6),
              ),
            ),
          ),
        ),
        Expanded(
          child: loading
              ? Center(child: CircularProgressIndicator(color: p.accent))
              : !hasResults
                  ? V2EmptyState(
                      icon: query.length < 2
                          ? Icons.search_rounded
                          : Icons.sentiment_dissatisfied_rounded,
                      title: query.length < 2
                          ? 'Find something to eat'
                          : 'No matches for "$query"',
                      message: query.length < 2
                          ? 'Search by restaurant, cuisine or dish.'
                          : 'Try a different term.',
                    )
                  : ListView(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 140),
                      children: [
                        if (restaurants.isNotEmpty) ...[
                          _label(context,
                              'Restaurants (${restaurants.length})'),
                          for (final r in restaurants.take(20))
                            Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: RestaurantRowV2(
                                data: r,
                                onTap: () {
                                  v2TrackAdClick(r, surface: 'search');
                                  v2OpenRestaurant(
                                      context, v2RestaurantId(r));
                                },
                              ),
                            ),
                          const SizedBox(height: 8),
                        ],
                        if (dishes.isNotEmpty) ...[
                          _label(context, 'Dishes (${dishes.length})'),
                          for (final d in dishes.take(20))
                            Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: _DishResultRow(data: d),
                            ),
                        ],
                      ],
                    ),
        ),
      ],
    );
  }

  Widget _label(BuildContext context, String text) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 6, 0, 10),
      child: Text(
        text.toUpperCase(),
        style: TextStyle(
          color: p.inkFaint,
          fontSize: 11,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.8,
        ),
      ),
    );
  }
}

class _DishResultRow extends StatelessWidget {
  const _DishResultRow({required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final nested = data['restaurant'];
    final price = v2Double(data['discounted_price'] ?? data['price']);
    return GlassPanel(
      radius: 18,
      onTap: () => showV2DishPopup(context, data),
      padding: const EdgeInsets.all(10),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: v2ImageUrl(data, kDishImageKeys).isEmpty
                ? v2ImageFallback(context, 60, 60)
                : Image.network(
                    v2ImageUrl(data, kDishImageKeys),
                    width: 60,
                    height: 60,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) =>
                        v2ImageFallback(context, 60, 60),
                  ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    VegDot(isVeg: v2Bool(data['is_veg'], fallback: true)),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        (data['name'] ?? data['title'] ?? 'Dish').toString(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            color: p.ink,
                            fontWeight: FontWeight.w800,
                            fontSize: 14),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  (data['restaurant_name'] ??
                          (nested is Map ? nested['name'] : '') ??
                          '')
                      .toString(),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: p.inkFaint, fontSize: 11.5),
                ),
                if (price > 0) ...[
                  const SizedBox(height: 4),
                  Text(
                    formatCurrency(context, price),
                    style: TextStyle(
                        color: p.accent,
                        fontWeight: FontWeight.w800,
                        fontSize: 12.5),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
