import 'dart:async';

import 'package:flutter/material.dart';

import '../widgets/foodflow_restaurant_card.dart';
import '../services/api_service.dart';
import '../config/api_constants.dart';

class FoodFlowHomeScreen extends StatefulWidget {
  const FoodFlowHomeScreen({super.key});

  @override
  State<FoodFlowHomeScreen> createState() => _FoodFlowHomeScreenState();
}

class _FoodFlowHomeScreenState extends State<FoodFlowHomeScreen> {
  final ApiService _api = ApiService();
  final TextEditingController _searchController = TextEditingController();
  Timer? _debounce;
  List<Map<String, dynamic>> _restaurants = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadNearby();
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  int _restaurantIdOf(Map<String, dynamic> restaurant) {
    final value = restaurant['id'] ?? restaurant['restaurant_id'];
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  void _onSearchChanged() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      _performSearch(_searchController.text.trim());
    });
  }

  Future<void> _loadNearby() async {
    setState(() => _isLoading = true);
    try {
      final resp = await _api.get(ApiConstants.nearbyRestaurants);
      if (resp is Map && resp['success'] == true && resp['data'] is List) {
        setState(() => _restaurants = List<Map<String, dynamic>>.from(resp['data']));
      }
    } catch (e) {
      // ignore network errors for UI fallback
    }
    setState(() => _isLoading = false);
  }

  Future<void> _performSearch(String q) async {
    if (q.isEmpty) return _loadNearby();
    setState(() => _isLoading = true);
    try {
      final resp = await _api.get(ApiConstants.searchRestaurants, queryParams: {'q': q});
      if (resp is Map && resp['success'] == true && resp['data'] is List) {
        setState(() => _restaurants = List<Map<String, dynamic>>.from(resp['data']));
      }
    } catch (e) {
      // ignore
    }
    setState(() => _isLoading = false);
  }

  void _onVoiceTap() {
    Navigator.pushNamed(
      context,
      '/search',
      arguments: const {'startVoiceSearch': true},
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            // Top Location Picker and Profile
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Row(
                  children: [
                    const Icon(Icons.location_on, color: Color(0xFF0E9F6E), size: 28),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: const [
                              Text(
                                'Home',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                              ),
                              Icon(Icons.keyboard_arrow_down),
                            ],
                          ),
                          const Text(
                            '123, Tech Park, Bangalore, Karnataka',
                            style: TextStyle(color: Colors.grey, fontSize: 12),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    const CircleAvatar(
                      backgroundColor: Colors.grey,
                      child: Icon(Icons.person, color: Colors.white),
                    ),
                  ],
                ),
              ),
            ),

            // Sticky Search Bar (rounded, voice, minimal shadow)
            SliverAppBar(
              pinned: true,
              floating: true,
              backgroundColor: Colors.white,
              elevation: 0,
              automaticallyImplyLeading: false,
              title: Container(
                height: 48,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(28),
                  boxShadow: [
                    BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6, offset: const Offset(0,2)),
                  ],
                ),
                child: Row(
                  children: [
                    const SizedBox(width: 12),
                    const Icon(Icons.search, color: Colors.grey),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: _searchController,
                        decoration: const InputDecoration.collapsed(
                          hintText: 'Search restaurants, dishes, cuisines',
                        ),
                        textInputAction: TextInputAction.search,
                        onSubmitted: (v) => _performSearch(v.trim()),
                      ),
                    ),
                    InkWell(
                      onTap: _onVoiceTap,
                      borderRadius: BorderRadius.circular(24),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        child: Icon(Icons.mic, color: Theme.of(context).primaryColor),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Promotional Banners
            SliverToBoxAdapter(
              child: SizedBox(
                height: 200,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
                  itemCount: 3,
                  itemBuilder: (context, index) => Container(
                    width: 300,
                    margin: const EdgeInsets.only(right: 16),
                    decoration: BoxDecoration(
                      color: Colors.orange[100 * (index + 1)],
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Center(child: Text('Special Offer ${index + 1}')),
                  ),
                ),
              ),
            ),

            // Categories Grid (What's on your mind?)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 16.0),
                child: Text(
                  "What's on your mind?",
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
              ),
            ),

            SliverToBoxAdapter(
              child: SizedBox(
                height: 120,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.all(16),
                  itemCount: 6,
                  itemBuilder: (context, index) => Padding(
                    padding: const EdgeInsets.only(right: 20),
                    child: Column(
                      children: [
                        CircleAvatar(
                          radius: 30,
                          backgroundColor: Colors.grey[200],
                          child: const Icon(Icons.fastfood, color: Colors.orange),
                        ),
                        const SizedBox(height: 8),
                        const Text('Item'),
                      ],
                    ),
                  ),
                ),
              ),
            ),

            const SliverPadding(
              padding: EdgeInsets.all(16),
              sliver: SliverToBoxAdapter(
                child: Divider(),
              ),
            ),

            // Restaurant List header
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 12),
                child: Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Restaurants',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                    ),
                    if (_isLoading) const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2)),
                  ],
                ),
              ),
            ),

            SliverList(
              delegate: SliverChildBuilderDelegate(
                (context, index) {
                  final item = index < _restaurants.length ? _restaurants[index] : null;
                  if (item == null) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                    child: FoodFlowRestaurantCard(
                      restaurant: item,
                      onTap: () {
                        final restaurantId = _restaurantIdOf(item);
                        if (restaurantId <= 0) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Unable to open this restaurant right now.'),
                            ),
                          );
                          return;
                        }
                        Navigator.pushNamed(
                          context,
                          '/restaurant/detail',
                          arguments: restaurantId,
                        );
                      },
                    ),
                  );
                },
                childCount: _restaurants.length,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
