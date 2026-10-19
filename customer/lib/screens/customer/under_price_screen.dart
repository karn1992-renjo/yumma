// lib/screens/customer/under_price_screen.dart
import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../services/location_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/common/app_skeleton.dart';
import 'restaurant_detail_screen.dart';

/// Restaurants with at least one real, in-stock menu item priced under the
/// threshold the backend returns (default ₹99) -- see
/// RestaurantController::underPrice() in the admin-panel. The list is
/// entirely auto-computed from live menu data on every load; nothing here
/// is admin-curated or hardcoded.
class UnderPriceScreen extends StatefulWidget {
  const UnderPriceScreen({super.key, this.initialLat, this.initialLng});

  final double? initialLat;
  final double? initialLng;

  @override
  State<UnderPriceScreen> createState() => _UnderPriceScreenState();
}

class _UnderPriceScreenState extends State<UnderPriceScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _restaurants = [];
  double _threshold = 99;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      var lat = widget.initialLat;
      var lng = widget.initialLng;
      if (lat == null || lng == null) {
        final position = await LocationService().getCurrentLocation(requestPermission: false);
        lat = position?.latitude;
        lng = position?.longitude;
      }

      if (lat == null || lng == null) {
        setState(() {
          _isLoading = false;
          _error = 'Enable location to see budget-friendly restaurants near you.';
        });
        return;
      }

      final response = await _api.get(
        ApiConstants.underPriceRestaurants,
        queryParams: {'lat': lat, 'lng': lng},
      );

      if (response['success'] == true && mounted) {
        setState(() {
          _restaurants = (response['data'] as List? ?? [])
              .whereType<Map>()
              .map((row) => Map<String, dynamic>.from(row))
              .toList();
          _threshold = (response['threshold'] as num?)?.toDouble() ?? 99;
          _isLoading = false;
        });
      } else if (mounted) {
        setState(() {
          _isLoading = false;
          _error = response['message']?.toString() ?? 'Could not load restaurants right now.';
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _error = 'Could not load restaurants right now.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F7FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        foregroundColor: FoodFlowTheme.ink,
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Under ${getCurrencySymbol(context)}${_threshold.toInt()}',
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w900, color: FoodFlowTheme.ink),
            ),
            Text(
              'Real menu items, real prices -- no minimums.',
              style: TextStyle(fontSize: 11.5, color: FoodFlowTheme.muted, fontWeight: FontWeight.w500),
            ),
          ],
        ),
      ),
      body: _isLoading
          ? const AppSkeletonListView(itemCount: 5, itemHeight: 130)
          : _error != null
              ? _UnderPriceErrorState(message: _error!, onRetry: _load)
              : _restaurants.isEmpty
                  ? _UnderPriceEmptyState(threshold: _threshold)
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(14, 14, 14, 24),
                        itemCount: _restaurants.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, index) => _UnderPriceRestaurantCard(
                          restaurant: _restaurants[index],
                          threshold: _threshold,
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => RestaurantDetailScreen(
                                restaurantId: _restaurants[index]['id'] as int,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
    );
  }
}

class _UnderPriceEmptyState extends StatelessWidget {
  const _UnderPriceEmptyState({required this.threshold});

  final double threshold;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.sell_outlined, size: 48, color: Color(0xFFB8BDC9)),
            const SizedBox(height: 14),
            Text(
              'No items under ${getCurrencySymbol(context)}${threshold.toInt()} nearby right now.',
              textAlign: TextAlign.center,
              style: TextStyle(color: FoodFlowTheme.muted, fontSize: 13.5, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}

class _UnderPriceErrorState extends StatelessWidget {
  const _UnderPriceErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline_rounded, size: 44, color: Color(0xFFB8BDC9)),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: TextStyle(color: FoodFlowTheme.muted, fontSize: 13.5)),
            const SizedBox(height: 14),
            OutlinedButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

class _UnderPriceRestaurantCard extends StatelessWidget {
  const _UnderPriceRestaurantCard({
    required this.restaurant,
    required this.threshold,
    required this.onTap,
  });

  final Map<String, dynamic> restaurant;
  final double threshold;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final name = restaurant['name']?.toString() ?? 'Restaurant';
    final imageUrl = restaurant['banner_image']?.toString().isNotEmpty == true
        ? restaurant['banner_image'].toString()
        : (restaurant['logo_image']?.toString() ?? '');
    final rating = (restaurant['rating'] as num?)?.toDouble() ?? 0;
    final cuisine = restaurant['cuisine_text']?.toString().trim() ?? '';
    final deliveryTime = (restaurant['delivery_time'] as num?)?.toInt() ?? 30;
    final distance = (restaurant['distance'] as num?)?.toDouble();
    final address = restaurant['address']?.toString().trim().isNotEmpty == true
        ? restaurant['address'].toString()
        : (restaurant['city']?.toString() ?? 'Nearby');
    final cheapestPrice = (restaurant['cheapest_item_price'] as num?)?.toDouble();
    final isOpen = restaurant['is_open_now'] == true || restaurant['is_open'] == true;

    return Opacity(
      opacity: isOpen ? 1 : 0.7,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFE5E7EB).withOpacity(0.8)),
            boxShadow: const [BoxShadow(color: Color(0x08000000), blurRadius: 10, offset: Offset(0, 4))],
          ),
          clipBehavior: Clip.antiAlias,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: 104,
                height: 104,
                child: imageUrl.isNotEmpty
                    ? AppCachedImage(
                        imageUrl: imageUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _fallbackTile(),
                      )
                    : _fallbackTile(),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800, color: FoodFlowTheme.ink),
                            ),
                          ),
                          if (rating > 0) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                              decoration: BoxDecoration(color: const Color(0xFF1BA672), borderRadius: BorderRadius.circular(6)),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(Icons.star_rounded, color: Colors.white, size: 11),
                                  const SizedBox(width: 2),
                                  Text(rating.toStringAsFixed(1), style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w800)),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 3),
                      if (cuisine.isNotEmpty)
                        Text(
                          cuisine,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(fontSize: 11.5, color: FoodFlowTheme.muted, fontWeight: FontWeight.w500),
                        ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          const Icon(Icons.access_time_filled_rounded, color: Color(0xFFFC8019), size: 12),
                          const SizedBox(width: 3),
                          Text(
                            distance != null
                                ? '$deliveryTime mins • ${distance.toStringAsFixed(distance >= 10 ? 0 : 1)} km'
                                : '$deliveryTime mins',
                            style: TextStyle(fontSize: 11, color: FoodFlowTheme.muted, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        address,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 10.5, color: FoodFlowTheme.faint),
                      ),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          if (cheapestPrice != null)
                            Text(
                              'From ${formatCurrency(context, cheapestPrice)}',
                              style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800, color: FoodFlowTheme.ink),
                            ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                            decoration: BoxDecoration(
                              color: const Color(0xFFEDFAF2),
                              borderRadius: BorderRadius.circular(999),
                              border: Border.all(color: const Color(0xFF83D4A5)),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.sell_rounded, color: Color(0xFF0A9443), size: 10),
                                const SizedBox(width: 3),
                                Text(
                                  'Under ${getCurrencySymbol(context)}${threshold.toInt()}',
                                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Color(0xFF0A9443)),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      if (!isOpen) ...[
                        const SizedBox(height: 4),
                        Text(
                          restaurant['next_opening_label']?.toString() ?? 'Currently closed',
                          style: const TextStyle(fontSize: 10.5, color: Color(0xFFD14343), fontWeight: FontWeight.w600),
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

  Widget _fallbackTile() {
    return Container(
      color: const Color(0xFFFFE9DD),
      alignment: Alignment.center,
      child: const Icon(Icons.storefront_rounded, color: Color(0xFFFF6B00), size: 28),
    );
  }
}
