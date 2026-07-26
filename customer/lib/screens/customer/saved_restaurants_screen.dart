import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/profile_screen_chrome.dart';
import 'restaurant_detail_screen.dart';

class SavedRestaurantsScreen extends StatefulWidget {
  const SavedRestaurantsScreen({super.key});

  @override
  State<SavedRestaurantsScreen> createState() => _SavedRestaurantsScreenState();
}

class _SavedRestaurantsScreenState extends State<SavedRestaurantsScreen> {
  final ApiService _api = ApiService();

  List<int> _ids = <int>[];
  List<Map<String, dynamic>> _restaurants = <Map<String, dynamic>>[];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadSaved();
  }

  Future<void> _loadSaved() async {
    setState(() => _loading = true);
    final prefs = await SharedPreferences.getInstance();
    final savedIds = (prefs.getStringList('saved_restaurant_ids') ?? <String>[])
        .map(int.tryParse)
        .whereType<int>()
        .toList(growable: false);

    final restaurants = <Map<String, dynamic>>[];

    try {
      final response = await _api.get(ApiConstants.savedRestaurants);
      if (response['success'] == true && response['data'] is List) {
        restaurants.addAll(
          (response['data'] as List)
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item)),
        );
      }
    } catch (_) {}

    if (restaurants.isEmpty) {
      for (final id in savedIds) {
        try {
          final response =
              await _api.get('${ApiConstants.restaurantDetails}/$id');
          final data = response['data'];
          if (response['success'] == true && data is Map) {
            restaurants.add(Map<String, dynamic>.from(data));
          }
        } catch (_) {}
      }
    }

    if (!mounted) return;
    setState(() {
      _ids = savedIds;
      _restaurants = restaurants;
      _loading = false;
    });
  }

  Future<void> _removeSaved(int id) async {
    final prefs = await SharedPreferences.getInstance();
    final nextIds = _ids.where((item) => item != id).toList(growable: false);
    await prefs.setStringList(
      'saved_restaurant_ids',
      nextIds.map((item) => item.toString()).toList(growable: false),
    );

    try {
      await _api.post(ApiConstants.removeFavoriteRestaurant(id));
    } catch (_) {}

    if (!mounted) return;
    setState(() {
      _ids = nextIds;
      _restaurants.removeWhere((restaurant) => _restaurantId(restaurant) == id);
    });
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Removed from saved restaurants')),
    );
  }

  int _restaurantId(Map<String, dynamic> restaurant) {
    final value = restaurant['id'] ?? restaurant['restaurant_id'];
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  double _parseDouble(dynamic value, {double fallback = 0}) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? fallback;
  }

  int _parseInt(dynamic value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  bool _parseBool(dynamic value, {bool fallback = false}) {
    if (value is bool) return value;
    if (value is int) return value != 0;
    final normalized = value?.toString().trim().toLowerCase();
    if (normalized == null) return fallback;
    return normalized == 'true' ||
        normalized == '1' ||
        normalized == 'yes' ||
        normalized == 'y';
  }

  String _imageUrl(Map<String, dynamic> restaurant) {
    for (final key in const [
      'logo_image',
      'logo',
      'banner_url',
      'banner_image',
      'image_url',
      'image',
      'photo',
    ]) {
      final value = restaurant[key]?.toString().trim() ?? '';
      if (value.isEmpty || value == 'null') continue;
      if (value.startsWith('http')) return value;
      if (value.startsWith('/')) return '${AppConfig.apiBaseUrl}$value';
      return '${AppConfig.apiBaseUrl}/storage/$value';
    }
    return '';
  }

  String _cuisineText(Map<String, dynamic> restaurant) {
    List<String> namesFrom(dynamic value) {
      if (value is String && value.trim().isNotEmpty) {
        return value
            .split(',')
            .map((item) => item.trim())
            .where((item) => item.isNotEmpty && int.tryParse(item) == null)
            .take(3)
            .toList(growable: false);
      }
      if (value is List) {
        return value
            .map((item) {
              if (item is Map) {
                return (item['name'] ??
                        item['title'] ??
                        item['cuisine_name'] ??
                        '')
                    .toString()
                    .trim();
              }
              final text = item.toString().trim();
              return int.tryParse(text) == null ? text : '';
            })
            .where((item) => item.isNotEmpty)
            .take(3)
            .toList(growable: false);
      }
      return const <String>[];
    }

    for (final key in const [
      'cuisine_text',
      'cuisine_names',
      'cuisines',
      'cuisine',
    ]) {
      final values = namesFrom(restaurant[key]);
      if (values.isNotEmpty) return values.join(', ');
    }
    return '';
  }

  void _openRestaurant(int id) {
    if (id <= 0) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RestaurantDetailScreen(restaurantId: id),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final fallbackIds = _restaurants.isEmpty ? _ids : <int>[];

    return Scaffold(
      backgroundColor: profileCanvasColor(context),
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: _loadSaved,
          color: profileAccentColor(context),
          child: CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
                  child: Row(
                    children: [
                      _CircleIconButton(
                        icon: Icons.arrow_back_rounded,
                        onTap: () => Navigator.of(context).maybePop(),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Saved Restaurants',
                              style: TextStyle(
                                color: profileTextColor(context),
                                fontSize: 20,
                                height: 1.05,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            SizedBox(height: 4),
                            Text(
                              'Your favorite places, ready for the next order',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: profileMutedColor(context),
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      _CircleIconButton(
                        icon: Icons.refresh_rounded,
                        onTap: _loadSaved,
                      ),
                    ],
                  ),
                ),
              ),
              if (_loading)
                const SliverFillRemaining(
                  child: Center(child: CircularProgressIndicator()),
                )
              else if (_restaurants.isEmpty && fallbackIds.isEmpty)
                SliverFillRemaining(
                  child: _SavedEmptyState(),
                )
              else if (_restaurants.isNotEmpty)
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(12, 4, 12, 120),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final restaurant = _restaurants[index];
                        return Padding(
                          padding: EdgeInsets.only(
                            bottom: index == _restaurants.length - 1 ? 0 : 12,
                          ),
                          child: _SavedRestaurantCard(
                            restaurant: restaurant,
                            imageUrl: _imageUrl(restaurant),
                            cuisineText: _cuisineText(restaurant),
                            rating: _parseDouble(
                              restaurant['rating'] ??
                                  restaurant['avg_rating'] ??
                                  restaurant['review_rating'],
                            ),
                            deliveryTime: _parseInt(
                              restaurant['delivery_time'] ??
                                  restaurant['deliveryTime'],
                              fallback: 30,
                            ),
                            minOrder: _parseDouble(
                              restaurant['min_order_amount'] ??
                                  restaurant['min_order'],
                            ),
                            isPureVeg: _parseBool(
                              restaurant['is_pure_veg'] ??
                                  restaurant['pure_veg'] ??
                                  restaurant['is_veg'],
                            ),
                            isOpen: _parseBool(
                              restaurant['is_open_now'] ??
                                  restaurant['is_open'],
                              fallback: true,
                            ),
                            onOpen: () =>
                                _openRestaurant(_restaurantId(restaurant)),
                            onRemove: () =>
                                _removeSaved(_restaurantId(restaurant)),
                          ),
                        );
                      },
                      childCount: _restaurants.length,
                    ),
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(12, 4, 12, 120),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) => Padding(
                        padding: EdgeInsets.only(
                          bottom: index == fallbackIds.length - 1 ? 0 : 12,
                        ),
                        child: _SavedIdCard(
                          id: fallbackIds[index],
                          onOpen: () => _openRestaurant(fallbackIds[index]),
                          onRemove: () => _removeSaved(fallbackIds[index]),
                        ),
                      ),
                      childCount: fallbackIds.length,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SavedRestaurantCard extends StatelessWidget {
  final Map<String, dynamic> restaurant;
  final String imageUrl;
  final String cuisineText;
  final double rating;
  final int deliveryTime;
  final double minOrder;
  final bool isPureVeg;
  final bool isOpen;
  final VoidCallback onOpen;
  final VoidCallback onRemove;

  const _SavedRestaurantCard({
    required this.restaurant,
    required this.imageUrl,
    required this.cuisineText,
    required this.rating,
    required this.deliveryTime,
    required this.minOrder,
    required this.isPureVeg,
    required this.isOpen,
    required this.onOpen,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    final name = restaurant['name']?.toString() ?? 'Saved restaurant';

    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: profileLineColor(context)),
          boxShadow: [
            BoxShadow(
              color: Theme.of(context).colorScheme.shadow.withOpacity(0.04),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Opacity(
          opacity: isOpen ? 1 : 0.7,
          child: Row(
            children: [
              ClipRRect(
                borderRadius: const BorderRadius.horizontal(
                  left: Radius.circular(20),
                ),
                child: Stack(
                  children: [
                    SizedBox(
                      width: 108,
                      height: 128,
                      child: imageUrl.isNotEmpty
                          ? AppCachedImage(
                              imageUrl: imageUrl,
                              fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) =>
                                  _imageFallback(context),
                            )
                          : _imageFallback(context),
                    ),
                    Positioned(
                      left: 8,
                      bottom: 8,
                      child: _statusBadge(context, isOpen),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 12, 10, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: profileTextColor(context),
                                fontSize: 16,
                                height: 1.12,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          InkWell(
                            onTap: onRemove,
                            borderRadius: BorderRadius.circular(999),
                            child: Padding(
                              padding: const EdgeInsets.all(4),
                              child: Icon(
                                Icons.bookmark_rounded,
                                color: profileButtonColor(context),
                                size: 22,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        cuisineText.isEmpty
                            ? (isOpen
                                ? 'Open to view menu'
                                : 'Closed right now')
                            : cuisineText,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: profileMutedColor(context),
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 10),
                      if (!isOpen) ...[
                        Text(
                          'Closed - ordering unavailable',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 8),
                      ],
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          _MiniChip(
                            icon: Icons.timer_rounded,
                            label: '$deliveryTime-${deliveryTime + 5} mins',
                          ),
                          if (rating > 0)
                            _MiniChip(
                              icon: Icons.star_rounded,
                              label: rating.toStringAsFixed(1),
                              active: true,
                            ),
                          if (rating <= 0)
                            const _MiniChip(
                              icon: Icons.star_rounded,
                              label: 'New',
                              active: true,
                            ),
                          if (isPureVeg)
                            const _MiniChip(
                              icon: Icons.eco_rounded,
                              label: 'Pure Veg',
                              active: true,
                            ),
                        ],
                      ),
                      if (minOrder > 0) ...[
                        const SizedBox(height: 10),
                        Text(
                          'Min order ${formatCurrency(context, minOrder)}',
                          style: TextStyle(
                            color: profileMutedColor(context),
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _imageFallback(BuildContext context) {
    return Container(
      color: profileSoftColor(context),
      child: Icon(
        Icons.restaurant_rounded,
        color: profileAccentColor(context),
        size: 34,
      ),
    );
  }

  Widget _statusBadge(BuildContext context, bool isOpen) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: isOpen
            ? profileAccentColor(context)
            : Theme.of(context).colorScheme.error,
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: Theme.of(context).colorScheme.shadow.withOpacity(0.18),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isOpen ? Icons.check_circle_rounded : Icons.lock_clock_rounded,
            color: Theme.of(context).colorScheme.surface,
            size: 13,
          ),
          const SizedBox(width: 4),
          Text(
            isOpen ? 'Open' : 'Closed',
            style: TextStyle(
              color: Theme.of(context).colorScheme.surface,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _SavedIdCard extends StatelessWidget {
  final int id;
  final VoidCallback onOpen;
  final VoidCallback onRemove;

  const _SavedIdCard({
    required this.id,
    required this.onOpen,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: profileLineColor(context)),
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: profileSoftColor(context),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Icon(
                Icons.restaurant_rounded,
                color: profileAccentColor(context),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'Saved restaurant #$id',
                style: TextStyle(
                  color: profileTextColor(context),
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            IconButton(
              onPressed: onRemove,
              icon: Icon(Icons.bookmark_remove_rounded,
                  color: profileButtonColor(context)),
            ),
          ],
        ),
      ),
    );
  }
}

class _MiniChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool active;

  const _MiniChip({
    required this.icon,
    required this.label,
    this.active = false,
  });

  @override
  Widget build(BuildContext context) {
    final isNewBadge = label == 'New';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: isNewBadge
            ? profileAccentColor(context)
            : active
                ? profileSoftColor(context)
                : profileLineColor(context).withOpacity(0.3),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            size: 13,
            color: isNewBadge
                ? Theme.of(context).colorScheme.surface
                : active
                    ? profileAccentColor(context)
                    : profileMutedColor(context),
          ),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              color: isNewBadge
                  ? Theme.of(context).colorScheme.surface
                  : active
                      ? profileAccentColor(context)
                      : profileMutedColor(context),
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _SavedEmptyState extends StatelessWidget {
  const _SavedEmptyState();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: ProfileSurfaceCard(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ProfileAccentIcon(
                icon: Icons.bookmark_border_rounded,
                size: 66,
                iconSize: 30,
                radius: 20,
              ),
              const SizedBox(height: 16),
              Text(
                'No saved restaurants yet',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: profileTextColor(context),
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Tap the heart or bookmark on a restaurant to save it here.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: profileMutedColor(context),
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CircleIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _CircleIconButton({
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          shape: BoxShape.circle,
        ),
        child: Icon(icon, color: profileButtonColor(context)),
      ),
    );
  }
}
