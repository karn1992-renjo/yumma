import 'package:flutter/material.dart';

import '../data/v2_repository.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_cards.dart';
import '../widgets/v2_dish_popup.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class TaxonomyResultsV2 extends StatefulWidget {
  const TaxonomyResultsV2({
    super.key,
    required this.title,
    required this.filterType,
    this.filterId,
    this.label,
    this.priceMax,
  });

  final String title;
  final String filterType; // cuisine | category | subcategory | price
  final int? filterId;
  final String? label;
  final double? priceMax;

  @override
  State<TaxonomyResultsV2> createState() => _TaxonomyResultsV2State();
}

class _TaxonomyResultsV2State extends State<TaxonomyResultsV2> {
  final V2Repository _repo = V2Repository.instance;

  bool _loading = true;
  bool _failed = false;
  List<Map<String, dynamic>> _dishes = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failed = false;
    });
    try {
      List<Map<String, dynamic>> data;
      if (widget.filterType == 'price') {
        final all = await _repo.nearbyRestaurants();
        final menus = await Future.wait(all.take(16).map((r) async {
          final id = v2RestaurantId(r);
          if (id <= 0) return const <Map<String, dynamic>>[];
          try {
            final items = await _repo.restaurantMenu(id);
            final slim = {
              'id': id,
              'name': r['name'],
              'logo_image': r['logo_image'] ?? r['image'],
            };
            return items
                .map((it) => {...it, 'restaurant': slim, 'restaurant_id': id})
                .toList();
          } catch (_) {
            return const <Map<String, dynamic>>[];
          }
        }));
        final max = widget.priceMax ?? 250;
        data = [
          for (final list in menus)
            for (final it in list)
              if (v2Double(it['discounted_price'] ?? it['price']) > 0 &&
                  v2Double(it['discounted_price'] ?? it['price']) <= max)
                it,
        ]..sort((a, b) => v2Double(a['discounted_price'] ?? a['price'])
            .compareTo(v2Double(b['discounted_price'] ?? b['price'])));
      } else {
        data = await _repo.taxonomyDishes(
          filterType: widget.filterType,
          filterId: widget.filterId,
          label: widget.label ?? widget.title,
        );
      }
      if (!mounted) return;
      setState(() {
        _dishes = data;
        _loading = false;
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _failed = true;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      title: widget.title,
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : _failed
              ? V2EmptyState(
                  icon: Icons.cloud_off_rounded,
                  title: 'Could not load results',
                  onRetry: _load,
                )
              : _dishes.isEmpty
                  ? const V2EmptyState(
                      icon: Icons.ramen_dining_outlined,
                      title: 'Nothing here yet',
                      message: 'No matching dishes near you.',
                    )
                  : Column(
                      children: [
                        _TaxonomyBanner(
                          title: widget.title,
                          count: _dishes.length,
                          imageUrl: _bannerImage(),
                        ),
                        Expanded(
                          child: RefreshIndicator(
                            onRefresh: _load,
                            color: p.accent,
                            backgroundColor: p.bgMid,
                            child: Builder(builder: (context) {
                              final cellW =
                                  (MediaQuery.sizeOf(context).width - 32 - 12) /
                                      2;
                              return GridView.builder(
                                padding: EdgeInsets.fromLTRB(16, 14, 16,
                                    MediaQuery.of(context).padding.bottom + 40),
                                gridDelegate:
                                    const SliverGridDelegateWithFixedCrossAxisCount(
                                  crossAxisCount: 2,
                                  mainAxisSpacing: 12,
                                  crossAxisSpacing: 12,
                                  mainAxisExtent: 236,
                                ),
                                itemCount: _dishes.length,
                                itemBuilder: (_, i) => V2Entrance(
                                  delay: Duration(
                                      milliseconds: 20 * i.clamp(0, 10)),
                                  child: DishCardV2(
                                    raw: _dishes[i],
                                    width: cellW,
                                    onTap: () =>
                                        showV2DishPopup(context, _dishes[i]),
                                  ),
                                ),
                              );
                            }),
                          ),
                        ),
                      ],
                    ),
    );
  }

  /// A representative dish image for the banner — the most-ordered / first item
  /// that actually has a photo.
  String _bannerImage() {
    for (final d in _dishes) {
      final url = v2ImageUrl(d, kDishImageKeys);
      if (url.isNotEmpty) return url;
    }
    return '';
  }
}

class _TaxonomyBanner extends StatelessWidget {
  const _TaxonomyBanner({
    required this.title,
    required this.count,
    required this.imageUrl,
  });

  final String title;
  final int count;
  final String imageUrl;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 0),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: SizedBox(
          height: 118,
          child: Stack(
            fit: StackFit.expand,
            children: [
              if (imageUrl.isNotEmpty)
                Image.network(imageUrl, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) =>
                        Container(color: p.accent.withOpacity(0.18)))
              else
                Container(color: p.accent.withOpacity(0.18)),
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                    colors: [Color(0xE6000000), Color(0x33000000)],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(title,
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 20,
                            fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(
                      count > 0
                          ? '$count dishes near you'
                          : 'Explore this selection',
                      style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 12.5,
                          fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
