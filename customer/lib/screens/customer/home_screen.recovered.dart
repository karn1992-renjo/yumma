// lib/screens/customer/home_screen.dart
import 'dart:math';
import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:geolocator/geolocator.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../theme/foodflow_theme.dart';
import '../../services/location_service.dart';
import '../../providers/cart_provider.dart';
import '../../providers/order_provider.dart';
import '../../models/restaurant.dart';
import '../../models/order.dart';
import '../../models/menu_item.dart';
import '../../widgets/customer/restaurant_card.dart';
import '../../widgets/customer/category_card.dart';
import '../../widgets/customer/banner_carousel.dart';
import '../../widgets/common/lucide_icon.dart';
import 'restaurant_detail_screen.dart';
import 'cart_screen.dart';
import 'search_screen.dart';
import 'orders_screen.dart';
import 'offers_screen.dart';
import 'profile_screen.dart';
import '../../utils/currency_utils.dart';

List<Map<String, Object>> homeFilterOptions(BuildContext context) => [
      {
        'key': 'fast_delivery',
        'label': 'Fast Delivery',
        'icon': Icons.flash_on
      },
      {'key': 'pure_veg', 'label': 'Pure Veg', 'icon': Icons.eco},
      {
        'key': 'under_99',
        'label': 'Under ${getCurrencySymbol(context)} 99',
        'icon': Icons.currency_rupee
      },
      {
        'key': 'under_199',
        'label': 'Under ${getCurrencySymbol(context)} 199',
        'icon': Icons.sell
      },
      {
        'key': 'bestsellers',
        'label': 'Bestsellers',
        'icon': Icons.local_fire_department
      },
      {'key': 'ratings_4_0', 'label': 'Ratings 4.0+', 'icon': Icons.star},
      {'key': 'offers', 'label': 'Offers', 'icon': Icons.local_offer},
    ];

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen>
    with SingleTickerProviderStateMixin {
  int _currentIndex = 0;
  bool _showDiningMode = false;
  String _currentCity = 'Loading...';
  String _userName = 'Guest';
  final LocationService _locationService = LocationService();
  final ApiService _api = ApiService();
  late AnimationController _cartAnimationController;
  late Animation<double> _cartScaleAnimation;
  late Animation<double> _cartSlideAnimation;
  bool _feedbackPromptChecked = false;
  bool _feedbackPromptOpen = false;
  bool _isUpdatingLocation = false;

  @override
  void initState() {
    super.initState();
    _loadUserInfo();
    _loadLocation();

    _cartAnimationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 300),
    );
    _cartScaleAnimation = Tween<double>(begin: 1.0, end: 1.2).animate(
      CurvedAnimation(
          parent: _cartAnimationController, curve: Curves.easeInOut),
    );
    _cartSlideAnimation = Tween<double>(begin: 0, end: -20).animate(
      CurvedAnimation(
          parent: _cartAnimationController, curve: Curves.easeOutCubic),
    );

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _maybeShowRecentOrderFeedback();
      _loadActiveOrders();
    });
  }

  Future<void> _loadActiveOrders() async {
    final orderProvider = Provider.of<OrderProvider>(context, listen: false);
    if (orderProvider.orders.isEmpty) {
      await orderProvider.fetchMyOrders();
    }
  }

  Order? _getActiveOrder(OrderProvider orderProvider) {
    final activeOrders = orderProvider.orders
        .where((order) => !order.isDelivered && !order.isCancelled)
        .toList();
    if (activeOrders.isEmpty) return null;
    activeOrders.sort((a, b) => b.createdAt.compareTo(a.createdAt));
    return activeOrders.first;
  }

  List<Order> _getActiveOrders(OrderProvider orderProvider) {
    final activeOrders = orderProvider.orders
        .where((order) => !order.isDelivered && !order.isCancelled)
        .toList();
    activeOrders.sort((a, b) => b.createdAt.compareTo(a.createdAt));
    return activeOrders;
  }

  @override
  void dispose() {
    _cartAnimationController.dispose();
    super.dispose();
  }

  void _animateCart() {
    _cartAnimationController.forward().then((_) {
      _cartAnimationController.reverse();
    });
  }

  Future<void> _maybeShowRecentOrderFeedback() async {
    if (_feedbackPromptChecked || _feedbackPromptOpen || !mounted) return;
    _feedbackPromptChecked = true;

    final orderProvider = Provider.of<OrderProvider>(context, listen: false);
    final orders = await orderProvider.fetchMyOrders();
    if (!mounted) return;

    final feedbackOrders = orders.where((order) => order.needsFeedback).toList()
      ..sort((a, b) {
        final aDate = a.deliveredAt ?? a.createdAt;
        final bDate = b.deliveredAt ?? b.createdAt;
        return bDate.compareTo(aDate);
      });
    if (feedbackOrders.isEmpty) return;

    final prefs = await SharedPreferences.getInstance();
    final order = feedbackOrders.first;
    final dismissedOrderId = prefs.getInt('dismissed_feedback_order_id');
    if (dismissedOrderId == order.id) return;

    _feedbackPromptOpen = true;
    await _showFeedbackDialog(order);
    _feedbackPromptOpen = false;
  }

  Future<void> _showFeedbackDialog(Order order) async {
    int restaurantRating = 5;
    int driverRating = 5;
    bool isSubmitting = false;
    final restaurantFeedback = TextEditingController();
    final driverFeedback = TextEditingController();
    final hasDriver = order.driverId != null || order.driver != null;

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            Future<void> submit() async {
              setDialogState(() => isSubmitting = true);
              final provider = Provider.of<OrderProvider>(
                this.context,
                listen: false,
              );
              final success = await provider.submitFeedback(
                orderId: order.id,
                restaurantRating: restaurantRating,
                driverRating: hasDriver ? driverRating : null,
                restaurantFeedback: restaurantFeedback.text,
                driverFeedback: driverFeedback.text,
              );

              if (!mounted) return;
              setDialogState(() => isSubmitting = false);

              if (success) {
                Navigator.pop(dialogContext);
                ScaffoldMessenger.of(this.context).showSnackBar(
                  const SnackBar(content: Text('Thanks for your feedback!')),
                );
              } else {
                ScaffoldMessenger.of(this.context).showSnackBar(
                  SnackBar(
                      content:
                          Text(provider.error ?? 'Feedback submit failed')),
                );
              }
            }

            Future<void> remindLater() async {
              final prefs = await SharedPreferences.getInstance();
              await prefs.setInt('dismissed_feedback_order_id', order.id);
              if (dialogContext.mounted) Navigator.pop(dialogContext);
            }

            return AlertDialog(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(18),
              ),
              titlePadding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
              contentPadding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
              actionsPadding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              title: const Text(
                'How was your order?',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Order #${order.orderNumber}',
                      style: TextStyle(
                        color: Colors.grey.shade700,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 16),
                    _RatingSection(
                      title: order.restaurant?.name ?? 'Restaurant',
                      subtitle: 'Rate food, packing and service',
                      icon: Icons.restaurant,
                      rating: restaurantRating,
                      onChanged: (value) {
                        setDialogState(() => restaurantRating = value);
                      },
                      feedbackController: restaurantFeedback,
                      feedbackHint: 'Tell us about the restaurant',
                    ),
                    if (hasDriver) ...[
                      const SizedBox(height: 18),
                      _RatingSection(
                        title: order.driver?.name ?? 'Delivery partner',
                        subtitle: 'Rate delivery experience',
                        icon: Icons.delivery_dining,
                        rating: driverRating,
                        onChanged: (value) {
                          setDialogState(() => driverRating = value);
                        },
                        feedbackController: driverFeedback,
                        feedbackHint: 'Tell us about the delivery',
                      ),
                    ],
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isSubmitting ? null : remindLater,
                  child: const Text('Later'),
                ),
                ElevatedButton(
                  onPressed: isSubmitting ? null : submit,
                  child: isSubmitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Submit'),
                ),
              ],
            );
          },
        );
      },
    );

    restaurantFeedback.dispose();
    driverFeedback.dispose();
  }

  Future<void> _loadUserInfo() async {
    try {
      final response = await _api.get(ApiConstants.user);
      if (response['success'] == true && mounted) {
        setState(() {
          _userName = response['data']['name']?.split(' ').first ?? 'User';
        });
      }
    } catch (e) {
      debugPrint('Error loading user: $e');
    }
  }

  Future<void> _loadLocation() async {
    final savedLocation = await _locationService.getSavedLocation();
    if (savedLocation != null && mounted) {
      setState(() {
        _currentCity = savedLocation['city']?.toString() ?? 'Select location';
      });
      return;
    }
    await _updateLocationFromDevice();
  }

  Future<void> _updateLocationFromDevice() async {
    if (_isUpdatingLocation) return;
    setState(() {
      _isUpdatingLocation = true;
      if (_currentCity == 'Loading...') {
        _currentCity = 'Fetching location...';
      }
    });

    final serviceEnabled = await _locationService.isLocationServiceEnabled();
    if (!serviceEnabled) {
      if (mounted) {
        setState(() {
          _currentCity = 'Select location';
          _isUpdatingLocation = false;
        });
        _showLocationMessage(
          'Location service is off. Turn it on or search manually.',
          actionLabel: 'Settings',
          onAction: _locationService.openLocationSettings,
        );
      }
      return;
    }

    final permission = await _locationService.checkLocationPermission();
    if (permission == LocationPermission.deniedForever) {
      if (mounted) {
        setState(() {
          _currentCity = 'Select location';
          _isUpdatingLocation = false;
        });
        _showLocationMessage(
          'Location permission is blocked. Enable it from app settings or search manually.',
          actionLabel: 'Settings',
          onAction: _locationService.openAppSettings,
        );
      }
      return;
    }

    final position = await _locationService.getCurrentLocation();
    if (position != null && mounted) {
      final city = await _locationService.getCityFromLatLng(
        position.latitude,
        position.longitude,
      );
      final resolvedCity =
          city?.trim().isNotEmpty == true ? city!.trim() : 'Near you';
      setState(() {
        _currentCity = resolvedCity;
        _isUpdatingLocation = false;
      });
      await _locationService.saveLocation(
        resolvedCity,
        position.latitude,
        position.longitude,
      );
      return;
    }

    if (mounted) {
      setState(() {
        _currentCity = 'Select location';
        _isUpdatingLocation = false;
      });
      _showLocationMessage(
          'Unable to fetch your location. Try geo location again or search manually.');
    }
  }

  Future<void> _showLocationPicker() async {
    final result = await showModalBottomSheet<String>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) => SafeArea(
        child: Container(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Select Location',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 20),
              _buildLocationOption(
                icon: Icons.my_location,
                title: 'Use Geo Location',
                subtitle: 'Fetch your current location with GPS',
                iconColor: AppConfig.primaryColor,
                onTap: () {
                  Navigator.pop(context, 'current');
                },
              ),
              const SizedBox(height: 12),
              _buildLocationOption(
                icon: Icons.search,
                title: 'Search Manually',
                subtitle: 'Search your city, area, or landmark',
                iconColor: Colors.blue,
                onTap: () {
                  Navigator.pop(context, 'manual');
                },
              ),
            ],
          ),
        ),
      ),
    );

    if (!mounted) return;

    if (result == 'current') {
      await _updateLocationFromDevice();
    } else if (result == 'manual') {
      await _showManualLocationDialog();
    }
  }

  Future<void> _showManualLocationDialog() async {
    final controller = TextEditingController();
    List<Map<String, dynamic>> suggestions = [];

    final result = await showModalBottomSheet<Map<String, dynamic>?>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => Padding(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 16,
            bottom: MediaQuery.of(context).viewInsets.bottom + 20,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  alignment: Alignment.center,
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: AppConfig.primaryColor.withOpacity(0.15),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(
                        Icons.location_on_outlined,
                        size: 18,
                        color: AppConfig.primaryColor,
                      ),
                    ),
                    const SizedBox(width: 12),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Search Location',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                              color: Colors.black,
                            ),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'Find by city, area or landmark',
                            style: TextStyle(
                              fontSize: 11,
                              color: Colors.grey,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: controller,
                  autofocus: true,
                  textInputAction: TextInputAction.search,
                  decoration: InputDecoration(
                    hintText: 'City, area, pincode...',
                    prefixIcon:
                        const Icon(Icons.search, color: AppConfig.primaryColor),
                    suffixIcon: controller.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, size: 18),
                            onPressed: () => setState(() {
                              controller.clear();
                              suggestions = [];
                            }),
                          )
                        : null,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Colors.grey),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide(color: Colors.grey.shade300),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(
                        color: AppConfig.primaryColor,
                        width: 2,
                      ),
                    ),
                    contentPadding: const EdgeInsets.symmetric(
                        horizontal: 14, vertical: 14),
                  ),
                  onChanged: (value) async {
                    if (value.trim().isEmpty) {
                      setState(() => suggestions = []);
                      return;
                    }

                    final newSuggestions = await _locationService
                        .getLocationSuggestions(value.trim());
                    setState(() => suggestions = newSuggestions);
                  },
                  onSubmitted: (value) async {
                    if (value.trim().isNotEmpty && suggestions.isNotEmpty) {
                      Navigator.pop(context, suggestions.first);
                    }
                  },
                ),
                const SizedBox(height: 12),

                // Suggestions List
                if (suggestions.isNotEmpty)
                  Material(
                    child: Container(
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey.shade200),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      constraints: BoxConstraints(
                        maxHeight: MediaQuery.of(context).size.height * 0.4,
                      ),
                      child: ListView.separated(
                        shrinkWrap: true,
                        physics: const ClampingScrollPhysics(),
                        separatorBuilder: (_, __) =>
                            Divider(height: 1, color: Colors.grey.shade200),
                        itemCount: suggestions.length,
                        itemBuilder: (context, index) {
                          final suggestion = suggestions[index];
                          final displayName = suggestion['display_name'] ??
                              suggestion['city'] ??
                              '';
                          final city = suggestion['city'] ?? '';

                          return Material(
                            color: Colors.transparent,
                            child: InkWell(
                              onTap: () {
                                Navigator.pop(context, suggestion);
                              },
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 14, vertical: 12),
                                child: Row(
                                  children: [
                                    Icon(Icons.location_on,
                                        size: 18, color: Colors.grey.shade600),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            city,
                                            style: const TextStyle(
                                              fontSize: 14,
                                              fontWeight: FontWeight.w600,
                                              color: Colors.black87,
                                            ),
                                          ),
                                          if (displayName.length > city.length)
                                            Text(
                                              displayName.replaceFirst(
                                                  '$city, ', ''),
                                              style: TextStyle(
                                                fontSize: 11,
                                                color: Colors.grey.shade600,
                                              ),
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  )
                else if (controller.text.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Center(
                      child: Text(
                        'Searching locations...',
                        style: TextStyle(
                            color: Colors.grey.shade600, fontSize: 12),
                      ),
                    ),
                  ),

                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.pop(context),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          side: BorderSide(
                            color: Colors.grey,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        child: const Text(
                          'Cancel',
                          style: TextStyle(
                            fontWeight: FontWeight.w600,
                            color: Colors.black,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: (controller.text.trim().isEmpty ||
                                suggestions.isEmpty)
                            ? null
                            : () {
                                Navigator.pop(context, suggestions.first);
                              },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppConfig.primaryColor,
                          disabledBackgroundColor: Colors.grey.shade300,
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        child: const Text(
                          'Select',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );

    if (result != null && mounted) {
      final city = result['city'] as String?;
      final lat = result['lat'] as double?;
      final lng = result['lng'] as double?;

      if (city != null && lat != null && lng != null) {
        setState(() => _currentCity = city);
        await _locationService.saveLocation(city, lat, lng);
        _showLocationMessage('Location updated to $city', actionLabel: 'OK');
      }
    }
  }

  void _showLocationMessage(
    String message, {
    String? actionLabel,
    Future<bool> Function()? onAction,
  }) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        behavior: SnackBarBehavior.floating,
        action: actionLabel != null && onAction != null
            ? SnackBarAction(
                label: actionLabel,
                onPressed: () {
                  onAction();
                },
              )
            : null,
      ),
    );
  }

  Widget _buildLocationOption({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color iconColor,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            border: Border.all(color: Colors.grey.shade200),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: iconColor.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: iconColor),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    Text(
                      subtitle,
                      style:
                          TextStyle(fontSize: 14, color: Colors.grey.shade600),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: Colors.grey),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _getScreens() {
    return [
      CustomerHomeContent(
        currentCity: _currentCity,
        userName: _userName,
        showDiningMode: _showDiningMode,
        onLocationTap: _showLocationPicker,
        onWishlistTap: () => Navigator.pushNamed(context, '/saved-restaurants'),
        onProfileTap: () => setState(() => _currentIndex = 4),
        activeOrders: _currentIndex == 0
            ? _getActiveOrders(
                Provider.of<OrderProvider>(context, listen: false))
            : const [],
        currentIndex: _currentIndex,
        cartItemCount: Provider.of<CartProvider>(context).itemCount,
      ),
      const SearchScreen(),
      const OrdersScreen(),
      const OffersScreen(),
      const ProfileScreen(),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final screens = _getScreens();
    final cartProvider = Provider.of<CartProvider>(context);
    final cartItemCount = cartProvider.itemCount;

    return Scaffold(
      body: screens[_currentIndex],
      bottomNavigationBar: SafeArea(
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.08),
                blurRadius: 12,
                offset: const Offset(0, -4),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Sticky Cart Button (Premium Position)
              if (cartItemCount > 0 && _currentIndex == 0)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                  child: GestureDetector(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => CartScreen(
                            onBrowseRestaurants: () =>
                                setState(() => _currentIndex = 0),
                            onAddMore: () => setState(() => _currentIndex = 0),
                          ),
                        ),
                      );
                    },
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 20, vertical: 14),
                      decoration: BoxDecoration(
                        color: FoodFlowTheme.orange,
                        borderRadius: BorderRadius.circular(14),
                        boxShadow: [
                          BoxShadow(
                            color: FoodFlowTheme.orange.withOpacity(0.25),
                            blurRadius: 12,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.shopping_bag_rounded,
                            color: Colors.white,
                            size: 20,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Text(
                                  'View Cart',
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w800,
                                    fontSize: 13,
                                  ),
                                ),
                                Text(
                                  '$cartItemCount item${cartItemCount == 1 ? '' : 's'} added',
                                  style: TextStyle(
                                    color: Colors.white.withOpacity(0.9),
                                    fontWeight: FontWeight.w500,
                                    fontSize: 11,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const Icon(
                            Icons.arrow_forward_rounded,
                            color: Colors.white,
                            size: 18,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              // Delivery/Dining Toggle
              if (_currentIndex == 0)
                Padding(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  child: Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          Colors.white,
                          Colors.grey.shade50,
                        ],
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                      ),
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.06),
                          blurRadius: 12,
                          offset: const Offset(0, 2),
                        ),
                      ],
                      border: Border.all(
                        color: Colors.grey.shade100,
                        width: 1,
                      ),
                    ),
                    padding: const EdgeInsets.all(3),
                    child: Row(
                      children: [
                        Expanded(
                          child: _deliveryDiningChip(
                            label: 'Delivery',
                            icon: Icons.delivery_dining,
                            selected: !_showDiningMode,
                            onTap: () {
                              setState(() {
                                _showDiningMode = false;
                              });
                            },
                          ),
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: _deliveryDiningChip(
                            label: 'Dining',
                            icon: Icons.restaurant,
                            selected: _showDiningMode,
                            onTap: () {
                              setState(() {
                                _showDiningMode = true;
                              });
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              // Bottom Navigation Bar
              BottomNavigationBar(
                currentIndex: _currentIndex,
                onTap: (index) {
                  setState(() {
                    _currentIndex = index;
                  });
                  if (index == 0) {
                    _feedbackPromptChecked = false;
                    WidgetsBinding.instance.addPostFrameCallback((_) {
                      _maybeShowRecentOrderFeedback();
                    });
                  }
                },
                type: BottomNavigationBarType.fixed,
                selectedFontSize: 12,
                unselectedFontSize: 12,
                selectedItemColor: FoodFlowTheme.orange,
                unselectedItemColor: FoodFlowTheme.muted,
                elevation: 0,
                items: [
                  BottomNavigationBarItem(
                    icon: const AppIcon(AppIcons.home, size: 24),
                    label: 'Home',
                  ),
                  BottomNavigationBarItem(
                    icon: const AppIcon(AppIcons.search, size: 24),
                    label: 'Search',
                  ),
                  BottomNavigationBarItem(
                    icon: const AppIcon(AppIcons.receipt, size: 24),
                    label: 'Orders',
                  ),
                  BottomNavigationBarItem(
                    icon: const AppIcon(AppIcons.offer, size: 24),
                    label: 'Offers',
                  ),
                  BottomNavigationBarItem(
                    icon: const AppIcon(AppIcons.user, size: 24),
                    label: 'Profile',
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _deliveryDiningChip({
    required String label,
    required IconData icon,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeInOut,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: selected ? AppConfig.primaryColor : Colors.transparent,
        borderRadius: BorderRadius.circular(20),
        boxShadow: selected
            ? [
                BoxShadow(
                  color: AppConfig.primaryColor.withOpacity(0.35),
                  blurRadius: 16,
                  offset: const Offset(0, 4),
                ),
              ]
            : [],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(20),
          splashColor: selected
              ? Colors.white.withOpacity(0.2)
              : AppConfig.primaryColor.withOpacity(0.1),
          highlightColor: selected
              ? Colors.white.withOpacity(0.1)
              : AppConfig.primaryColor.withOpacity(0.05),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                icon,
                size: 16,
                color: selected ? Colors.white : Colors.grey.shade700,
              ),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(
                  color: selected ? Colors.white : Colors.grey.shade700,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                  letterSpacing: 0.3,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _homeModeChip({
    required String label,
    required IconData icon,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: selected ? FoodFlowTheme.orange : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? FoodFlowTheme.orange : Colors.grey.shade300,
            width: 1.2,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon,
                size: 16,
                color: selected ? Colors.white : Colors.grey.shade700),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                color: selected ? Colors.white : Colors.grey.shade800,
                fontWeight: FontWeight.bold,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActiveOrderBanner(Order order) {
    final statusText = order.statusText;
    final statusColor = order.statusColor;

    return GestureDetector(
      onTap: () =>
          Navigator.pushNamed(context, '/order/track', arguments: order.id),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFFFE8E2C), Color(0xFFFFB86C)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.orange.withOpacity(0.25),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.2),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.delivery_dining,
                  color: Colors.white, size: 28),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Active order',
                    style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 14),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '#${order.orderNumber} | ${order.restaurant?.name ?? 'Restaurant'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white70, fontSize: 13),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 5),
                        decoration: BoxDecoration(
                          color: statusColor.withOpacity(0.18),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          statusText,
                          style: TextStyle(
                              color: statusColor,
                              fontWeight: FontWeight.w700,
                              fontSize: 12),
                        ),
                      ),
                      const Spacer(),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.2),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Text(
                          'Track',
                          style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class CustomerHomeContent extends StatefulWidget {
  final String currentCity;
  final String userName;
  final bool showDiningMode;
  final VoidCallback onLocationTap;
  final VoidCallback onWishlistTap;
  final VoidCallback onProfileTap;
  final List<Order> activeOrders;
  final int currentIndex;
  final int cartItemCount;

  const CustomerHomeContent({
    super.key,
    required this.currentCity,
    required this.userName,
    required this.showDiningMode,
    required this.onLocationTap,
    required this.onWishlistTap,
    required this.onProfileTap,
    this.activeOrders = const [],
    this.currentIndex = 0,
    this.cartItemCount = 0,
  });

  @override
  State<CustomerHomeContent> createState() => _CustomerHomeContentState();
}

class _CustomerHomeContentState extends State<CustomerHomeContent> {
  final ApiService _api = ApiService();
  final LocationService _locationService = LocationService();
  static const int _highOrderThreshold = 20;

  List<dynamic> _categories = [];
  List<dynamic> _restaurants = [];
  List<dynamic> _allRestaurants = [];
  List<_DishPreview> _popularDishes = [];
  Set<String> _selectedFilters = {};
  final Set<int> _savedRestaurantIds = <int>{};
  List<dynamic> _banners = [];
  List<dynamic> _offers = [];
  final Map<int, _RestaurantMenuInsights> _restaurantInsights =
      <int, _RestaurantMenuInsights>{};
  final Set<int> _restaurantInsightRequests = <int>{};
  bool _isLoading = true;
  bool _isRefreshing = false;
  String _sortBy = 'recommended';

  @override
  void initState() {
    super.initState();
    _loadSavedRestaurantIds();
    _loadData();
  }

  Future<void> _loadSavedRestaurantIds() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getStringList('saved_restaurant_ids') ?? <String>[];
    if (!mounted) return;
    setState(() {
      _savedRestaurantIds
        ..clear()
        ..addAll(saved.map(int.tryParse).whereType<int>());
    });
  }

  Future<void> _toggleSavedRestaurant(Map<String, dynamic> restaurant) async {
    final restaurantId = _restaurantIdOf(restaurant);
    if (restaurantId <= 0) return;

    final prefs = await SharedPreferences.getInstance();
    final nextSaved = Set<int>.from(_savedRestaurantIds);
    final isSaving = !nextSaved.remove(restaurantId);
    if (isSaving) {
      nextSaved.add(restaurantId);
    }

    await prefs.setStringList(
      'saved_restaurant_ids',
      nextSaved.map((id) => id.toString()).toList(growable: false),
    );

    try {
      if (isSaving) {
        await _api.post(ApiConstants.favoriteRestaurant(restaurantId));
      } else {
        await _api.post(ApiConstants.removeFavoriteRestaurant(restaurantId));
      }
    } catch (_) {}

    if (!mounted) return;
    setState(() {
      _savedRestaurantIds
        ..clear()
        ..addAll(nextSaved);
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          isSaving ? 'Restaurant saved' : 'Restaurant removed from saved',
        ),
      ),
    );
  }

  @override
  void didUpdateWidget(CustomerHomeContent oldWidget) {
    super.didUpdateWidget(oldWidget);
    if ((oldWidget.currentCity != widget.currentCity &&
            widget.currentCity != 'Loading...') ||
        oldWidget.showDiningMode != widget.showDiningMode) {
      _loadData();
    }
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final savedLocation = await _locationService.getSavedLocation();
      final lat = _parseNullableDouble(savedLocation?['lat']);
      final lng = _parseNullableDouble(savedLocation?['lng']);

      final results = await Future.wait([
        _api
            .get('${ApiConstants.bannersByType}/home')
            .timeout(const Duration(seconds: 10)),
        _api
            .get(ApiConstants.popularCuisines)
            .timeout(const Duration(seconds: 10)),
        _api
            .get(ApiConstants.activeOffers)
            .timeout(const Duration(seconds: 10)),
        (lat != null && lng != null)
            ? _api.get(
                widget.showDiningMode
                    ? ApiConstants.diningRestaurants
                    : ApiConstants.nearbyRestaurants,
                queryParams: {
                  'lat': lat,
                  'lng': lng,
                  'radius': 15, // Max search radius
                },
              ).timeout(const Duration(seconds: 15))
            : Future.value({'success': true, 'data': []}),
      ]);

      final banners = _extractListFromResponse(results[0]);
      final categories = _extractListFromResponse(results[1]);
      final allOffers = _extractListFromResponse(results[2]);
      final offers = allOffers.where((offer) {
        if (offer is! Map<String, dynamic>) return true;
        final isActive = offer['is_active'] != false &&
            offer['status']?.toString().toLowerCase() != 'inactive';
        final code = offer['code'] ?? offer['coupon_code'] ?? offer['title'];
        return isActive && code != null && code.toString().trim().isNotEmpty;
      }).toList();

      final rawRestaurants = _extractListFromResponse(results[3]);
      final allRestaurants = _filterRestaurantsByDeliveryRadius(
        rawRestaurants,
        lat,
        lng,
      );
      final restaurants = _applyFilters(allRestaurants);

      if (!mounted) return;
      setState(() {
        _banners = banners;
        _categories = categories;
        _offers = offers;
        _allRestaurants = allRestaurants;
        _restaurants = restaurants;
        _popularDishes = [];
        _isLoading = false;
      });
      _refreshPopularDishes(restaurants);
      _hydrateRestaurantInsights(allRestaurants);
    } catch (e) {
      debugPrint('Home load error: $e');
      setState(() {
        _isLoading = false;
      });
    }
  }

  // Filter restaurants based on their delivery radius and customer location
  List<dynamic> _filterRestaurantsByDeliveryRadius(
    List<dynamic> restaurants,
    double? customerLat,
    double? customerLng,
  ) {
    if (customerLat == null || customerLng == null) {
      return restaurants;
    }

    return restaurants.where((restaurant) {
      if (restaurant is! Map<String, dynamic>) return true;

      final restaurantLat = _parseDouble(
        restaurant['latitude'] ?? restaurant['lat'],
        fallback: double.nan,
      );
      final restaurantLng = _parseDouble(
        restaurant['longitude'] ?? restaurant['lng'],
        fallback: double.nan,
      );
      final deliveryRadius = _parseDouble(
        restaurant['delivery_radius'],
        fallback: 5,
      );

      if (restaurantLat.isNaN || restaurantLng.isNaN || deliveryRadius <= 0) {
        return true;
      }

      // Calculate distance using Haversine formula
      final distance = _calculateDistance(
        customerLat,
        customerLng,
        restaurantLat,
        restaurantLng,
      );
      restaurant['distance'] = distance;

      // Only show restaurant if it delivers to this area
      return distance <= deliveryRadius;
    }).toList();
  }

  // Haversine formula to calculate distance between two coordinates
  double _calculateDistance(
    double lat1,
    double lon1,
    double lat2,
    double lon2,
  ) {
    const earthRadiusKm = 6371.0;
    final dLat = _degreesToRadians(lat2 - lat1);
    final dLon = _degreesToRadians(lon2 - lon1);
    final a = (sin(dLat / 2) * sin(dLat / 2)) +
        (cos(_degreesToRadians(lat1)) *
            cos(_degreesToRadians(lat2)) *
            sin(dLon / 2) *
            sin(dLon / 2));
    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    return earthRadiusKm * c;
  }

  double _degreesToRadians(double degrees) {
    return degrees * (3.14159265359 / 180);
  }

  Future<void> _onRefresh() async {
    setState(() => _isRefreshing = true);
    await _loadData();
    setState(() => _isRefreshing = false);
  }

  List<dynamic> _extractListFromResponse(dynamic response) {
    if (response == null) return [];
    if (response is List) return response;
    if (response is Map<String, dynamic>) {
      if (response['success'] == true && response['data'] is List) {
        return response['data'] as List<dynamic>;
      }
      return response['data'] as List<dynamic>? ??
          response['restaurants'] as List<dynamic>? ??
          response['categories'] as List<dynamic>? ??
          response['offers'] as List<dynamic>? ??
          response['banners'] as List<dynamic>? ??
          [];
    }
    return [];
  }

  Future<void> _refreshPopularDishes(List<dynamic> restaurants) async {
    final dishes = await _loadPopularDishes(restaurants);
    if (!mounted) return;
    setState(() {
      _popularDishes = dishes;
    });
  }

  Future<List<_DishPreview>> _loadPopularDishes(
    List<dynamic> restaurants,
  ) async {
    final previews = <_DishPreview>[];
    final usedKeys = <String>{};

    final candidates = restaurants
        .whereType<Map<String, dynamic>>()
        .where((restaurant) {
          final id = restaurant['id'] ?? restaurant['restaurant_id'];
          return _parseInt(id) > 0;
        })
        .take(12)
        .toList();

    for (final restaurant in candidates) {
      if (previews.length >= 8) break;

      final restaurantId =
          _parseInt(restaurant['id'] ?? restaurant['restaurant_id']);
      if (restaurantId <= 0) continue;

      try {
        final response = await _api
            .get(
              '${ApiConstants.restaurantDetails}/$restaurantId/menu',
            )
            .timeout(const Duration(seconds: 3));
        final data = response['data'] is Map<String, dynamic>
            ? response['data'] as Map<String, dynamic>
            : <String, dynamic>{};
        final rawItems = (data['menu_items'] ??
            data['items'] ??
            data['menu'] ??
            []) as List<dynamic>;

        final items = rawItems
            .whereType<Map<String, dynamic>>()
            .map((json) => MenuItem.fromJson(json))
            .where((item) => item.isAvailable)
            .toList()
          ..sort((a, b) {
            final scoreA = (a.isBestseller ? 100000 : 0) +
                (a.isRecommended ? 50000 : 0) +
                a.totalOrders;
            final scoreB = (b.isBestseller ? 100000 : 0) +
                (b.isRecommended ? 50000 : 0) +
                b.totalOrders;
            if (scoreA != scoreB) return scoreB.compareTo(scoreA);
            final ratingA = a.rating ?? 0;
            final ratingB = b.rating ?? 0;
            if (ratingA != ratingB) return ratingB.compareTo(ratingA);
            return b.createdAt.compareTo(a.createdAt);
          });

        _cacheRestaurantInsights(restaurantId, items, restaurant: restaurant);

        for (final item in items) {
          final key = '${restaurantId}_${item.name.toLowerCase().trim()}';
          if (usedKeys.contains(key)) continue;
          usedKeys.add(key);

          previews.add(
            _DishPreview(
              name: item.name,
              imageUrl: item.imageUrl,
              price: item.finalPrice,
              isVeg: item.isVeg,
              restaurantId: restaurantId,
              restaurantName: restaurant['name']?.toString() ?? 'Restaurant',
            ),
          );

          if (previews.length >= 8) break;
        }
      } catch (e) {
        debugPrint(
          'Popular dish load failed for restaurant $restaurantId: $e',
        );
      }
    }

    return previews;
  }

  double _parseDouble(dynamic value, {double fallback = 0.0}) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) {
      return double.tryParse(value) ?? fallback;
    }
    return fallback;
  }

  double? _parseNullableDouble(dynamic value) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }

  String _categoryQuery(dynamic category) {
    if (category is! Map) return '';
    return (category['name'] ??
            category['title'] ??
            category['cuisine'] ??
            category['slug'] ??
            '')
        .toString()
        .trim();
  }

  void _openCategory(dynamic category) {
    final query = _categoryQuery(category);
    if (query.isEmpty) return;
    Navigator.pushNamed(
      context,
      '/search',
      arguments: {
        'query': query,
        'source': 'category',
        'title': query,
      },
    );
  }

  int _parseInt(dynamic value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is double) return value.toInt();
    if (value is String) {
      return int.tryParse(value) ?? double.tryParse(value)?.toInt() ?? fallback;
    }
    return fallback;
  }

  bool _parseBool(dynamic value, {bool fallback = false}) {
    if (value is bool) return value;
    if (value is int) return value != 0;
    if (value is String) {
      final normalized = value.toLowerCase().trim();
      return normalized == 'true' ||
          normalized == '1' ||
          normalized == 'yes' ||
          normalized == 'y';
    }
    return fallback;
  }

  bool _isFastDelivery(Map<String, dynamic> restaurant) {
    return _parseInt(restaurant['delivery_time'] ?? restaurant['deliveryTime'],
            fallback: 999) <=
        30;
  }

  double _ratingValue(Map<String, dynamic> restaurant) {
    if (!_hasVisibleRating(restaurant)) {
      return -1.0;
    }

    return _parseDouble(
      restaurant['rating'] ??
          restaurant['avg_rating'] ??
          restaurant['review_rating'] ??
          restaurant['rating_value'] ??
          restaurant['restaurant_rating'],
      fallback: 0.0,
    );
  }

  int _ratingCount(Map<String, dynamic> restaurant) {
    return _parseInt(
      restaurant['total_ratings'] ??
          restaurant['review_count'] ??
          restaurant['total_reviews'] ??
          restaurant['rating_count'],
      fallback: 0,
    );
  }

  bool _hasVisibleRating(Map<String, dynamic> restaurant) {
    return _ratingCount(restaurant) >= 3;
  }

  bool _hasOffer(Map<String, dynamic> restaurant) {
    if (restaurant['discount'] != null &&
        restaurant['discount'].toString().trim().isNotEmpty) {
      return true;
    }
    if (restaurant['offer'] != null &&
        restaurant['offer'].toString().trim().isNotEmpty) {
      return true;
    }
    if (restaurant['offers'] is List &&
        (restaurant['offers'] as List).isNotEmpty) {
      return true;
    }
    if (restaurant['active_promos'] is List &&
        (restaurant['active_promos'] as List).isNotEmpty) {
      return true;
    }
    if (restaurant['has_active_promo'] == true ||
        restaurant['has_offer'] == true ||
        restaurant['promo_available'] == true) {
      return true;
    }
    return false;
  }

  bool _isPureVeg(Map<String, dynamic> restaurant) {
    if (restaurant['is_pure_veg'] != null ||
        restaurant['is_veg'] != null ||
        restaurant['pure_veg'] != null ||
        restaurant['veg'] != null) {
      return _parseBool(
          restaurant['is_pure_veg'] ??
              restaurant['is_veg'] ??
              restaurant['pure_veg'] ??
              restaurant['veg'],
          fallback: false);
    }

    final cuisineText = restaurant['cuisine'] ?? restaurant['cuisine_text'];
    if (cuisineText is String) {
      return cuisineText.toLowerCase().contains('veg');
    }
    if (cuisineText is List) {
      return cuisineText.join(' ').toLowerCase().contains('veg');
    }
    return false;
  }

  int _restaurantIdOf(Map<String, dynamic> restaurant) {
    return _parseInt(restaurant['id'] ?? restaurant['restaurant_id']);
  }

  _RestaurantMenuInsights? _restaurantInsightsFor(
      Map<String, dynamic> restaurant) {
    final restaurantId = _restaurantIdOf(restaurant);
    final cached = _restaurantInsights[restaurantId];
    if (cached != null) return cached;
    return _RestaurantMenuInsights.fromDynamic(restaurant['_menu_analysis']);
  }

  bool _matchesPureVegFilter(Map<String, dynamic> restaurant) {
    final insights = _restaurantInsightsFor(restaurant);
    if (insights != null && insights.availableItemCount > 0) {
      return insights.isPureVegMenu;
    }
    return _isPureVeg(restaurant);
  }

  bool _matchesPricePointFilter(Map<String, dynamic> restaurant, double cap) {
    final insights = _restaurantInsightsFor(restaurant);
    if (insights != null && insights.availableItemCount > 0) {
      return insights.minPrice <= cap;
    }

    final startingPrice = _parseNullableDouble(
      restaurant['starting_price'] ??
          restaurant['min_price'] ??
          restaurant['lowest_price'],
    );
    if (startingPrice != null) {
      return startingPrice <= cap;
    }
    return false;
  }

  bool _matchesBestsellerFilter(Map<String, dynamic> restaurant) {
    final insights = _restaurantInsightsFor(restaurant);
    if (insights != null && insights.availableItemCount > 0) {
      return insights.hasBestSeller || insights.hasHighlyOrdered;
    }
    return restaurant['has_bestsellers'] == true ||
        restaurant['bestseller'] == true;
  }

  void _cacheRestaurantInsights(
    int restaurantId,
    List<MenuItem> items, {
    Map<String, dynamic>? restaurant,
  }) {
    if (restaurantId <= 0 || items.isEmpty) return;
    final insights = _RestaurantMenuInsights.fromItems(
      restaurantId,
      items,
      highOrderThreshold: _highOrderThreshold,
    );
    _restaurantInsights[restaurantId] = insights;
    restaurant?['_menu_analysis'] = insights.toJson();
  }

  Future<_RestaurantMenuInsights?> _fetchRestaurantInsights(
      int restaurantId) async {
    if (restaurantId <= 0) return null;
    final cached = _restaurantInsights[restaurantId];
    if (cached != null) return cached;
    if (_restaurantInsightRequests.contains(restaurantId)) return null;

    _restaurantInsightRequests.add(restaurantId);
    try {
      final response = await _api
          .get('${ApiConstants.restaurantDetails}/$restaurantId/menu')
          .timeout(const Duration(seconds: 4));
      final data = response['data'] is Map<String, dynamic>
          ? response['data'] as Map<String, dynamic>
          : <String, dynamic>{};
      final rawItems = (data['menu_items'] ??
          data['items'] ??
          data['menu'] ??
          []) as List<dynamic>;
      final items = rawItems
          .whereType<Map<String, dynamic>>()
          .map(MenuItem.fromJson)
          .where((item) => item.isAvailable)
          .toList(growable: false);
      if (items.isEmpty) return null;
      final insights = _RestaurantMenuInsights.fromItems(
        restaurantId,
        items,
        highOrderThreshold: _highOrderThreshold,
      );
      _restaurantInsights[restaurantId] = insights;
      return insights;
    } catch (e) {
      debugPrint('Restaurant insights load failed for $restaurantId: $e');
      return null;
    } finally {
      _restaurantInsightRequests.remove(restaurantId);
    }
  }

  Future<void> _hydrateRestaurantInsights(List<dynamic> restaurants) async {
    final candidates = restaurants
        .whereType<Map<String, dynamic>>()
        .where((restaurant) => _restaurantIdOf(restaurant) > 0)
        .toList(growable: false);

    if (candidates.isEmpty) return;

    final fetched = await Future.wait(
      candidates.map((restaurant) async {
        final insights =
            await _fetchRestaurantInsights(_restaurantIdOf(restaurant));
        if (insights == null) return false;
        restaurant['_menu_analysis'] = insights.toJson();
        return true;
      }),
    );

    if (!mounted || !fetched.contains(true)) return;
    setState(() {
      _restaurants = _applyFilters(_allRestaurants);
    });
  }

  List<dynamic> _applyFilters(List<dynamic> restaurants) {
    final filtered = _selectedFilters.isEmpty
        ? List<dynamic>.from(restaurants)
        : restaurants.where((item) {
            final restaurant = item as Map<String, dynamic>;
            if (_selectedFilters.contains('fast_delivery') &&
                !_isFastDelivery(restaurant)) {
              return false;
            }
            if (_selectedFilters.contains('pure_veg') &&
                !_matchesPureVegFilter(restaurant)) {
              return false;
            }
            if (_selectedFilters.contains('under_99') &&
                !_matchesPricePointFilter(restaurant, 99)) {
              return false;
            }
            if (_selectedFilters.contains('under_199') &&
                !_matchesPricePointFilter(restaurant, 199)) {
              return false;
            }
            if (_selectedFilters.contains('bestsellers') &&
                !_matchesBestsellerFilter(restaurant)) {
              return false;
            }
            if (_selectedFilters.contains('ratings_4_0') &&
                _ratingValue(restaurant) < 4.0) {
              return false;
            }
            if (_selectedFilters.contains('offers') && !_hasOffer(restaurant)) {
              return false;
            }
            return true;
          }).toList();

    filtered.sort((a, b) {
      final left = a as Map<String, dynamic>;
      final right = b as Map<String, dynamic>;
      switch (_sortBy) {
        case 'rating':
          return _ratingValue(right).compareTo(_ratingValue(left));
        case 'delivery_time':
          return _parseInt(
            left['delivery_time'] ?? left['deliveryTime'],
            fallback: 999,
          ).compareTo(
            _parseInt(
              right['delivery_time'] ?? right['deliveryTime'],
              fallback: 999,
            ),
          );
        case 'distance':
          return _parseDouble(left['distance'], fallback: 9999)
              .compareTo(_parseDouble(right['distance'], fallback: 9999));
        default:
          return 0;
      }
    });

    return filtered;
  }

  void _onFilterChipTapped(String key) {
    setState(() {
      if (_selectedFilters.contains(key)) {
        _selectedFilters.remove(key);
      } else {
        _selectedFilters.add(key);
      }
      _restaurants = _applyFilters(_allRestaurants);
    });
  }

  void _showSortSheet() {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
      ),
      builder: (context) {
        Widget option(String value, String title, IconData icon) {
          final selected = _sortBy == value;
          return ListTile(
            leading: Icon(icon,
                color: selected ? FoodFlowTheme.crimson : FoodFlowTheme.muted),
            title: Text(
              title,
              style: TextStyle(
                fontWeight: selected ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
            trailing: selected
                ? const Icon(Icons.check_circle, color: FoodFlowTheme.crimson)
                : null,
            onTap: () {
              setState(() {
                _sortBy = value;
                _restaurants = _applyFilters(_allRestaurants);
              });
              Navigator.pop(context);
            },
          );
        }

        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              option('recommended', 'Recommended', Icons.auto_awesome),
              option('rating', 'Rating: high to low', Icons.star),
              option('delivery_time', 'Fastest delivery', Icons.timer),
              option('distance', 'Nearest first', Icons.near_me),
              const SizedBox(height: 8),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Container(
        color: Colors.white,
        child: Center(
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 22),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(28),
              boxShadow: [
                BoxShadow(
                  color: FoodFlowTheme.orange.withOpacity(0.12),
                  blurRadius: 28,
                  offset: const Offset(0, 16),
                ),
              ],
            ),
            child: const Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircularProgressIndicator(color: FoodFlowTheme.orange),
                SizedBox(height: 16),
                Text(
                  'Finding great food near you',
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final topRestaurants = _restaurants.take(3).toList();
    final nearbyRestaurants = List<dynamic>.from(_restaurants);

    return RefreshIndicator(
      onRefresh: _onRefresh,
      color: FoodFlowTheme.orange,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: Container(
              color: Colors.white,
              padding: EdgeInsets.fromLTRB(
                14,
                MediaQuery.of(context).padding.top + 12,
                14,
                12,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: InkWell(
                          onTap: widget.onLocationTap,
                          borderRadius: BorderRadius.circular(22),
                          child: Row(
                            children: [
                              Container(
                                width: 48,
                                height: 48,
                                decoration: BoxDecoration(
                                  gradient: const LinearGradient(
                                    colors: [
                                      FoodFlowTheme.orange,
                                      FoodFlowTheme.orangeDark,
                                    ],
                                  ),
                                  borderRadius: BorderRadius.circular(18),
                                  boxShadow: [
                                    BoxShadow(
                                      color: FoodFlowTheme.orange
                                          .withOpacity(0.22),
                                      blurRadius: 18,
                                      offset: const Offset(0, 8),
                                    ),
                                  ],
                                ),
                                child: const Icon(
                                  Icons.location_on_rounded,
                                  color: Colors.white,
                                  size: 24,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text(
                                      'Deliver to',
                                      style: TextStyle(
                                        fontSize: 12,
                                        color: FoodFlowTheme.muted,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                            widget.currentCity,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(
                                              fontSize: 16,
                                              color: FoodFlowTheme.ink,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                        ),
                                        const Icon(
                                          Icons.keyboard_arrow_down_rounded,
                                          color: FoodFlowTheme.ink,
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Stack(
                        clipBehavior: Clip.none,
                        children: [
                          InkWell(
                            onTap: _showSortSheet,
                            borderRadius: BorderRadius.circular(18),
                            child: Container(
                              width: 48,
                              height: 48,
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(18),
                                border: Border.all(color: FoodFlowTheme.line),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withOpacity(0.05),
                                    blurRadius: 18,
                                    offset: const Offset(0, 8),
                                  ),
                                ],
                              ),
                              child: const Icon(
                                Icons.notifications_none_rounded,
                                color: FoodFlowTheme.ink,
                              ),
                            ),
                          ),
                          Positioned(
                            right: 2,
                            top: 2,
                            child: Container(
                              width: 10,
                              height: 10,
                              decoration: BoxDecoration(
                                color: FoodFlowTheme.orange,
                                shape: BoxShape.circle,
                                border:
                                    Border.all(color: Colors.white, width: 2),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: FoodFlowTheme.line),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.04),
                          blurRadius: 22,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: ListTile(
                      onTap: () => Navigator.pushNamed(context, '/search'),
                      leading: const Icon(Icons.search_rounded,
                          color: FoodFlowTheme.ink),
                      title: const Text(
                        'Search restaurants, cuisines or dishes',
                        style: TextStyle(
                          fontSize: 14,
                          color: FoodFlowTheme.muted,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      trailing: Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF4EC),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(Icons.tune_rounded,
                            color: FoodFlowTheme.orange, size: 20),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _buildHeroBanner(),
                ],
              ),
            ),
          ),
          if (_categories.isNotEmpty)
            SliverPersistentHeader(
              pinned: true,
              delegate: _CategoriesSliverDelegate(
                categories: _categories,
                onShowAll: _showAllCategories,
                onCategoryTap: (category) => _openCategory(category),
              ),
            ),
          if (_offers.isNotEmpty)
            SliverToBoxAdapter(
              child: Container(
                color: Colors.white,
                padding: const EdgeInsets.only(bottom: 8),
                child: Column(
                  children: [
                    _buildSectionHeader(
                      'Offers for you',
                      actionLabel: 'See all',
                      onAction: () => Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const OffersScreen()),
                      ),
                    ),
                    SizedBox(
                      height: 112,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        padding: const EdgeInsets.symmetric(horizontal: 10),
                        separatorBuilder: (_, __) => const SizedBox(width: 10),
                        itemCount: min(_offers.length, 6),
                        itemBuilder: (context, index) => _buildOfferCard(
                            Map<String, dynamic>.from(_offers[index])),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          if (widget.activeOrders.isNotEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 10, 10, 2),
                child: SizedBox(
                  height: 92,
                  child: PageView.builder(
                    controller: PageController(viewportFraction: 0.96),
                    itemCount: widget.activeOrders.length,
                    itemBuilder: (context, index) => Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child:
                          _ActiveOrderCard(order: widget.activeOrders[index]),
                    ),
                  ),
                ),
              ),
            ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(10, 10, 10, 4),
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: homeFilterOptions(context).map((filter) {
                    final key = filter['key'] as String;
                    return Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: _FilterChip(
                        label: filter['label'] as String,
                        icon: filter['icon'] as IconData,
                        selected: _selectedFilters.contains(key),
                        onTap: () => _onFilterChipTapped(key),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),
          ),
          if (topRestaurants.isNotEmpty)
            SliverToBoxAdapter(
              child: Column(
                children: [
                  _buildSectionHeader(
                    'Top Restaurants Near You',
                    actionLabel: 'See all',
                    onAction: () => _showAllRestaurants(
                      title: 'Top Restaurants Near You',
                      restaurants: _restaurants,
                    ),
                  ),
                  SizedBox(
                    height: 300,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      separatorBuilder: (_, __) => const SizedBox(width: 10),
                      itemCount: topRestaurants.length,
                      itemBuilder: (context, index) => _buildTopRestaurantCard(
                        topRestaurants[index] as Map<String, dynamic>,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          if (_popularDishes.isNotEmpty)
            SliverToBoxAdapter(
              child: Column(
                children: [
                  _buildSectionHeader(
                    'Popular Dishes',
                    actionLabel: 'See all',
                    onAction: _showAllPopularDishes,
                  ),
                  SizedBox(
                    height: 176,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      separatorBuilder: (_, __) => const SizedBox(width: 10),
                      itemCount: _popularDishes.length,
                      itemBuilder: (context, index) =>
                          _buildPopularDishCard(_popularDishes[index]),
                    ),
                  ),
                ],
              ),
            ),
          if (nearbyRestaurants.isNotEmpty)
            SliverToBoxAdapter(
              child: _buildSectionHeader(
                'More Nearby',
                actionLabel: 'Sort',
                onAction: _showSortSheet,
              ),
            ),
          if (nearbyRestaurants.isNotEmpty)
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              sliver: SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, index) {
                    final restaurant = nearbyRestaurants[index];
                    return RestaurantCard(
                      restaurant: restaurant,
                      isSaved: _savedRestaurantIds
                          .contains(_restaurantIdOf(restaurant)),
                      onSaveToggle: () => _toggleSavedRestaurant(restaurant),
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => RestaurantDetailScreen(
                              restaurantId: restaurant['id'] ??
                                  restaurant['restaurant_id'],
                            ),
                          ),
                        );
                      },
                    );
                  },
                  childCount: nearbyRestaurants.length,
                ),
              ),
            )
          else if (_restaurants.isEmpty)
            SliverFillRemaining(
              child: FoodFlowTheme.emptyState(
                icon: widget.showDiningMode
                    ? Icons.event_seat_outlined
                    : Icons.restaurant_outlined,
                title: widget.showDiningMode
                    ? 'No dining restaurants found nearby'
                    : 'No restaurants found nearby',
                subtitle: 'Try changing your location or clearing filters.',
              ),
            ),
          // Premium bottom padding for navigation + floating elements safe area
          SliverPadding(
              padding: EdgeInsets.only(
                  bottom: widget.currentIndex == 0
                      ? (widget.cartItemCount > 0 ? 320 : 240)
                      : 96)),
        ],
      ),
    );
  }

  void _showAllCategories() {
    _showHomeSheet(
      title: 'Categories',
      child: GridView.builder(
        padding: const EdgeInsets.fromLTRB(12, 4, 12, 24),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 3,
          mainAxisSpacing: 12,
          crossAxisSpacing: 10,
          childAspectRatio: 0.92,
        ),
        itemCount: _categories.length,
        itemBuilder: (context, index) => CategoryCard(
          category: _categories[index],
          onTap: () {
            Navigator.pop(context);
            _openCategory(_categories[index]);
          },
        ),
      ),
    );
  }

  void _showAllRestaurants({
    required String title,
    required List<dynamic> restaurants,
  }) {
    _showHomeSheet(
      title: title,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 4, 12, 24),
        itemCount: restaurants.length,
        itemBuilder: (context, index) {
          final restaurant = restaurants[index] as Map<String, dynamic>;
          return RestaurantCard(
            restaurant: restaurant,
            isSaved: _savedRestaurantIds.contains(_restaurantIdOf(restaurant)),
            onSaveToggle: () => _toggleSavedRestaurant(restaurant),
            onTap: () {
              Navigator.pop(context);
              _openRestaurant(restaurant);
            },
          );
        },
      ),
    );
  }

  void _showAllPopularDishes() {
    _showHomeSheet(
      title: 'Popular Dishes',
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(12, 4, 12, 24),
        itemCount: _popularDishes.length,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          final dish = _popularDishes[index];
          return InkWell(
            onTap: () {
              Navigator.pop(context);
              _openDishPreview(dish);
            },
            borderRadius: BorderRadius.circular(18),
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFE8ECF3)),
              ),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: SizedBox(
                      width: 74,
                      height: 68,
                      child: dish.imageUrl.isNotEmpty
                          ? AppCachedImage(
                              imageUrl: dish.imageUrl,
                              fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) =>
                                  _dishImageFallback(dish),
                            )
                          : _dishImageFallback(dish),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          dish.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          dish.restaurantName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.inkSoft,
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          formatCurrency(context, dish.price),
                          style: const TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  OutlinedButton(
                    onPressed: () => _addDishPreviewToCart(dish),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFF0A9443),
                      side: const BorderSide(color: Color(0xFF83D4A5)),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: const Text('ADD'),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  void _showHomeSheet({
    required String title,
    required Widget child,
  }) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFFF5F6FB),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
      ),
      builder: (context) {
        return SafeArea(
          top: false,
          child: SizedBox(
            height: MediaQuery.of(context).size.height * 0.82,
            child: Column(
              children: [
                Container(
                  width: 48,
                  height: 5,
                  margin: const EdgeInsets.only(top: 10, bottom: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFD7DCE4),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 0, 10, 12),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          title,
                          style: const TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ],
                  ),
                ),
                Expanded(child: child),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildSectionHeader(
    String title, {
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 14),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 18,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.3,
              ),
            ),
          ),
          if (actionLabel != null)
            GestureDetector(
              onTap: onAction,
              child: Text(
                actionLabel,
                style: const TextStyle(
                  color: FoodFlowTheme.orange,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.3,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildHeroBanner() {
    if (_banners.isNotEmpty) {
      return BannerCarousel(banners: _banners);
    }

    final title = widget.showDiningMode
        ? 'Book premium tables,\ninstantly'
        : 'Delicious food,\ndelivered fast';
    final subtitle = widget.showDiningMode
        ? 'Reserve top restaurant experiences around you.'
        : 'Order from your favorite restaurants near you.';

    return Container(
      height: 250,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFFFF2448),
            Color(0xFFD90429),
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0x55D90429),
            blurRadius: 16,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            top: -36,
            right: -18,
            child: Container(
              width: 180,
              height: 180,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.07),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            bottom: -54,
            left: -28,
            child: Container(
              width: 220,
              height: 220,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.06),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 18, 18),
            child: Row(
              children: [
                Expanded(
                  flex: 11,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title.toUpperCase(),
                        style: const TextStyle(
                          fontSize: 30,
                          height: 0.98,
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          shadows: [
                            Shadow(
                              color: Colors.black45,
                              offset: Offset(2, 3),
                              blurRadius: 0,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        subtitle,
                        style: TextStyle(
                          fontSize: 13,
                          height: 1.45,
                          color: Colors.white.withOpacity(0.92),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const Spacer(),
                      ElevatedButton(
                        onPressed: () =>
                            Navigator.pushNamed(context, '/search'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.black,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 18,
                            vertical: 12,
                          ),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'Order now',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 14,
                              ),
                            ),
                            SizedBox(width: 8),
                            Icon(Icons.arrow_forward_rounded, size: 15),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: List.generate(
                          4,
                          (index) => Container(
                            width: index == 0 ? 24 : 8,
                            height: 8,
                            margin: const EdgeInsets.only(right: 6),
                            decoration: BoxDecoration(
                              color: index == 0 ? Colors.white : Colors.white54,
                              borderRadius: BorderRadius.circular(999),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(flex: 9, child: _heroImageFallback()),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _heroImageFallback() {
    return Container(
      decoration: const BoxDecoration(color: Colors.transparent),
      child: const Center(
        child: Icon(
          Icons.local_offer_rounded,
          color: Colors.white,
          size: 96,
        ),
      ),
    );
  }

  String _resolveImageUrl(Map<String, dynamic> item, List<String> keys) {
    for (final key in keys) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) {
        if (value.startsWith('http')) return value;
        if (value.startsWith('/')) return '${AppConfig.apiBaseUrl}$value';
        return value;
      }
    }
    return '';
  }

  String _restaurantCuisineText(Map<String, dynamic> restaurant) {
    final cuisine = restaurant['cuisine'];
    if (cuisine is String && cuisine.trim().isNotEmpty) return cuisine;
    if (cuisine is List) {
      return cuisine
          .map((item) {
            if (item is Map<String, dynamic>) {
              return (item['name'] ?? item['title'] ?? '').toString().trim();
            }
            return item.toString().trim();
          })
          .where((item) => item.isNotEmpty)
          .take(3)
          .join(', ');
    }
    final text = restaurant['cuisine_text']?.toString() ?? '';
    return text;
  }

  String _restaurantDiscountText(Map<String, dynamic> restaurant) {
    final discount = restaurant['discount']?.toString().trim() ?? '';
    if (discount.isNotEmpty) return discount;
    final offer = restaurant['offer']?.toString().trim() ?? '';
    if (offer.isNotEmpty) return offer;
    final promos = restaurant['active_promos'];
    if (promos is List && promos.isNotEmpty) {
      final first = promos.first;
      if (first is Map<String, dynamic>) {
        final value = first['discount_value']?.toString() ?? '';
        final type = first['discount_type']?.toString() ?? 'percentage';
        if (value.isNotEmpty) {
          return type == 'percentage' ? '$value% OFF' : '$value OFF';
        }
      }
    }
    return '';
  }

  List<String> _restaurantSignalTags(Map<String, dynamic> restaurant) {
    final tags = <String>[];
    final insights = _restaurantInsightsFor(restaurant);

    if (_matchesPureVegFilter(restaurant)) {
      tags.add('Pure Veg');
    }
    if (insights != null && insights.availableItemCount > 0) {
      if (insights.hasBestSeller) {
        tags.add('Best seller');
      } else if (insights.hasHighlyOrdered) {
        tags.add('Highly ordered');
      }
      if (insights.minPrice <= 99) {
        tags.add('Under ${getCurrencySymbol(context)}99');
      } else if (insights.minPrice <= 199) {
        tags.add('Under ${getCurrencySymbol(context)}199');
      }
    }

    return tags.take(3).toList(growable: false);
  }

  Widget _buildRestaurantSignalChip(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: const Color(0xFFF4F7FB),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 11,
          color: FoodFlowTheme.ink,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  void _openRestaurant(Map<String, dynamic> restaurant) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RestaurantDetailScreen(
          restaurantId: restaurant['id'] ?? restaurant['restaurant_id'],
        ),
      ),
    );
  }

  Widget _buildTopRestaurantCard(Map<String, dynamic> restaurant) {
    final imageUrl = _resolveImageUrl(restaurant, const [
      'banner_url',
      'banner_image',
      'image_url',
      'image',
      'photo',
    ]);
    final cuisines = _restaurantCuisineText(restaurant);
    final rating = _ratingValue(restaurant);
    final hasRating = _hasVisibleRating(restaurant);
    final discount = _restaurantDiscountText(restaurant);
    final deliveryMinutes = _parseInt(
      restaurant['delivery_time'] ?? restaurant['deliveryTime'],
      fallback: 30,
    );
    final amountForOne = _parseDouble(
      restaurant['amount_for_one'] ??
          restaurant['amountForOne'] ??
          restaurant['price_for_one'] ??
          restaurant['cost_for_one'] ??
          restaurant['lowest_price'] ??
          restaurant['min_price'],
      fallback: 0,
    );
    final distance = _parseDouble(restaurant['distance'], fallback: -1);
    final distanceText = distance < 0
        ? ''
        : distance < 1
            ? '${(distance * 1000).round()} m away'
            : '${distance.toStringAsFixed(distance >= 10 ? 0 : 1)} km away';
    final signalTags = _restaurantSignalTags(restaurant);

    return InkWell(
      onTap: () => _openRestaurant(restaurant),
      borderRadius: BorderRadius.circular(18),
      child: Container(
        width: 248,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: FoodFlowTheme.line),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(18),
                  ),
                  child: SizedBox(
                    height: 140,
                    width: double.infinity,
                    child: imageUrl.isNotEmpty
                        ? AppCachedImage(
                            imageUrl: imageUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) =>
                                _restaurantImageFallback(),
                          )
                        : _restaurantImageFallback(),
                  ),
                ),
                if (discount.isNotEmpty)
                  Positioned(
                    left: 12,
                    top: 12,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.78),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        discount,
                        style: const TextStyle(
                          fontSize: 11,
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                Positioned(
                  right: 12,
                  top: 12,
                  child: InkWell(
                    onTap: () => _toggleSavedRestaurant(restaurant),
                    borderRadius: BorderRadius.circular(14),
                    child: Container(
                      width: 34,
                      height: 34,
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.18),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Icon(
                        _savedRestaurantIds
                                .contains(_restaurantIdOf(restaurant))
                            ? Icons.bookmark_rounded
                            : Icons.bookmark_border_rounded,
                        color: Colors.white,
                        size: 20,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    restaurant['name']?.toString() ?? 'Restaurant',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 15,
                      color: FoodFlowTheme.ink,
                      fontWeight: FontWeight.w800,
                      height: 1.1,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 9,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFF0A9443),
                          borderRadius: BorderRadius.circular(18),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.star_rounded,
                              color: Colors.white,
                              size: 14,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              hasRating ? rating.toStringAsFixed(1) : 'New',
                              style: const TextStyle(
                                fontSize: 12,
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          cuisines.isEmpty ? 'Multi-cuisine' : cuisines,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 11,
                            color: FoodFlowTheme.inkSoft,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Text(
                        '$deliveryMinutes-${deliveryMinutes + 5} mins',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF09A65A),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (distanceText.isNotEmpty) ...[
                        const Text(
                          '  •  ',
                          style: TextStyle(color: FoodFlowTheme.faint),
                        ),
                        Expanded(
                          child: Text(
                            distanceText,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFF09A65A),
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  if (discount.isNotEmpty || amountForOne > 0) ...[
                    const SizedBox(height: 6),
                    Text(
                      discount.isNotEmpty
                          ? discount
                          : 'From ${formatCurrency(context, amountForOne)}',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF626C81),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                  if (signalTags.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: signalTags
                          .map(_buildRestaurantSignalChip)
                          .toList(growable: false),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _restaurantImageFallback() {
    return Container(
      color: const Color(0xFFFFF3E8),
      child: const Center(
        child: Icon(
          Icons.restaurant_rounded,
          size: 52,
          color: FoodFlowTheme.orange,
        ),
      ),
    );
  }

  Widget _miniLogoFallback() {
    return Container(
      color: const Color(0xFFFFF3E8),
      child: const Icon(
        Icons.storefront_rounded,
        color: FoodFlowTheme.orange,
        size: 28,
      ),
    );
  }

  void _openDishPreview(_DishPreview dish) {
    if (dish.restaurantId <= 0) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RestaurantDetailScreen(restaurantId: dish.restaurantId),
      ),
    );
  }

  Future<void> _addDishPreviewToCart(_DishPreview dish) async {
    if (dish.restaurantId <= 0) return;

    final restaurantData = _restaurants
        .whereType<Map<String, dynamic>>()
        .cast<Map<String, dynamic>?>()
        .firstWhere(
          (item) =>
              item != null &&
              (item['id'] == dish.restaurantId ||
                  item['restaurant_id'] == dish.restaurantId),
          orElse: () => null,
        );

    if (restaurantData == null) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Unable to find this restaurant right now')),
      );
      return;
    }

    try {
      final response = await _api.get(
        '${ApiConstants.restaurantDetails}/${dish.restaurantId}/menu',
      );
      final data = response['data'] is Map ? response['data'] as Map : response;
      final rawItems = (data['menu_items'] ??
          data['items'] ??
          data['menu'] ??
          []) as List<dynamic>;
      final query = dish.name.toLowerCase().trim();

      final menuItems = rawItems
          .whereType<Map<String, dynamic>>()
          .map((json) => MenuItem.fromJson(json))
          .toList();

      MenuItem? matched;
      for (final item in menuItems) {
        final name = item.name.toLowerCase().trim();
        if (name == query) {
          matched = item;
          break;
        }
      }
      matched ??= menuItems.cast<MenuItem?>().firstWhere(
            (item) => item != null && item.name.toLowerCase().contains(query),
            orElse: () => null,
          );
      matched ??= menuItems.cast<MenuItem?>().firstWhere(
            (item) => item != null && query.contains(item.name.toLowerCase()),
            orElse: () => null,
          );

      if (matched == null) {
        _openDishPreview(dish);
        return;
      }

      if (matched.hasCustomizations) {
        _openDishPreview(dish);
        return;
      }

      final cartProvider = Provider.of<CartProvider>(context, listen: false);
      final restaurant = restaurantData;
      cartProvider.addItem(
        matched,
        Restaurant.fromJson(restaurant),
      );

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${matched.name} added to cart'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Open the restaurant to add this dish'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Widget _buildPopularDishCard(_DishPreview dish) {
    return InkWell(
      onTap: () => _openDishPreview(dish),
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        width: 126,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: SizedBox(
                height: 86,
                width: 126,
                child: dish.imageUrl.isNotEmpty
                    ? AppCachedImage(
                        imageUrl: dish.imageUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _dishImageFallback(dish),
                      )
                    : _dishImageFallback(dish),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              dish.name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 12,
                color: FoodFlowTheme.ink,
                fontWeight: FontWeight.w500,
                height: 1.25,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              formatCurrency(context, dish.price),
              style: const TextStyle(
                fontSize: 12,
                color: FoodFlowTheme.ink,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 6),
            SizedBox(
              width: 58,
              height: 30,
              child: OutlinedButton(
                onPressed: () => _addDishPreviewToCart(dish),
                style: OutlinedButton.styleFrom(
                  foregroundColor: FoodFlowTheme.orange,
                  side: const BorderSide(color: Color(0xFFFFB98D)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                  padding: EdgeInsets.zero,
                ),
                child: const Text(
                  'Add',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _dishImageFallback(_DishPreview dish) {
    final label = dish.name.toLowerCase();
    IconData icon = Icons.fastfood_rounded;
    Color tint = const Color(0xFFFFC48A);
    if (label.contains('biryani')) {
      icon = Icons.rice_bowl_rounded;
      tint = const Color(0xFFFFC087);
    } else if (label.contains('noodle') || label.contains('hakka')) {
      icon = Icons.ramen_dining_rounded;
      tint = const Color(0xFFFFB36B);
    } else if (label.contains('butter') || label.contains('paneer')) {
      icon = Icons.dinner_dining_rounded;
      tint = const Color(0xFFFFAD7A);
    } else if (label.contains('jamun') || label.contains('dessert')) {
      icon = Icons.icecream_rounded;
      tint = const Color(0xFFFFC8A4);
    } else if (label.contains('bhature') || label.contains('dosa')) {
      icon = Icons.lunch_dining_rounded;
      tint = const Color(0xFFFFBF7D);
    }

    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [tint.withOpacity(0.9), const Color(0xFFFF8A3D)],
        ),
      ),
      child: Center(
        child: Icon(icon, size: 42, color: Colors.white.withOpacity(0.95)),
      ),
    );
  }

  Widget _buildQuickAction({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
  }) {
    return Container(
      width: 118,
      margin: const EdgeInsets.only(right: 10, top: 8, bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFF2E2E2)),
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.08),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: color.withOpacity(0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 18),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOfferCard(Map<String, dynamic> offer) {
    final discountType = offer['discount_type'] ?? 'percentage';
    var discountValue = offer['discount_value'] ?? 0;

    double discountDouble = 0;
    if (discountValue is String) {
      discountDouble = double.tryParse(discountValue) ?? 0;
    } else if (discountValue is num) {
      discountDouble = discountValue.toDouble();
    }

    String discountDisplay = discountType == 'percentage'
        ? '${discountDouble.toStringAsFixed(0)}% OFF'
        : '${formatCurrency(context, discountDouble)} OFF';
    final code =
        (offer['code'] ?? offer['coupon_code'] ?? offer['title'] ?? 'AUTO')
            .toString();
    final subtitle = (offer['description'] ??
            offer['subtitle'] ??
            offer['offer_text'] ??
            'Available on eligible orders')
        .toString();
    final imageUrl = _resolveImageUrl(offer, const [
      'promo_image',
      'image',
      'image_url',
      'banner_image',
    ]);

    return Container(
      width: 232,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE8ECF3)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: const Color(0xFFF0F5FF),
              borderRadius: BorderRadius.circular(15),
            ),
            clipBehavior: Clip.antiAlias,
            child: imageUrl.isNotEmpty
                ? AppCachedImage(
                    imageUrl: imageUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const Icon(
                      Icons.local_offer_rounded,
                      color: Color(0xFF4B76E5),
                      size: 23,
                    ),
                  )
                : const Icon(
                    Icons.local_offer_rounded,
                    color: Color(0xFF4B76E5),
                    size: 23,
                  ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  discountDisplay,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 12,
                    color: FoodFlowTheme.inkSoft,
                    height: 1.25,
                    fontWeight: FontWeight.w500,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 8),
                Text(
                  'Code: $code',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11,
                    color: FoodFlowTheme.muted,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoriesSliverDelegate extends SliverPersistentHeaderDelegate {
  final List<dynamic> categories;
  final VoidCallback onShowAll;
  final Function(dynamic) onCategoryTap;

  _CategoriesSliverDelegate({
    required this.categories,
    required this.onShowAll,
    required this.onCategoryTap,
  });

  @override
  Widget build(
      BuildContext context, double shrinkOffset, bool overlapsContent) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(10, 12, 10, 8),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Padding(
                padding: EdgeInsets.only(left: 6),
                child: Text(
                  'Categories',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.3,
                    color: FoodFlowTheme.ink,
                  ),
                ),
              ),
              TextButton(
                onPressed: onShowAll,
                style: TextButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
                child: const Text(
                  'See all',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: FoodFlowTheme.orange,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 88,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemCount: categories.length,
              itemBuilder: (context, index) => GestureDetector(
                onTap: () => onCategoryTap(categories[index]),
                child: CategoryCard(
                  category: categories[index],
                  onTap: () => onCategoryTap(categories[index]),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  double get maxExtent => 156;

  @override
  double get minExtent => 156;

  @override
  bool shouldRebuild(covariant _CategoriesSliverDelegate oldDelegate) {
    return categories != oldDelegate.categories;
  }
}

class _SearchHeaderDelegate extends SliverPersistentHeaderDelegate {
  final VoidCallback onTap;

  _SearchHeaderDelegate({required this.onTap});

  @override
  Widget build(
      BuildContext context, double shrinkOffset, bool overlapsContent) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          height: 52,
          decoration: BoxDecoration(
            color: const Color(0xFFFFFFFF),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFF0DADB)),
            boxShadow: [
              BoxShadow(
                color: FoodFlowTheme.crimson.withOpacity(0.08),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: const Row(
            children: [
              SizedBox(width: 16),
              Icon(Icons.search, color: FoodFlowTheme.crimson, size: 22),
              SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Search "biryani", pizza, burger...',
                  style: TextStyle(
                    color: Color(0xFF686B78),
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              Icon(Icons.mic_none, color: FoodFlowTheme.crimson, size: 22),
              SizedBox(width: 14),
            ],
          ),
        ),
      ),
    );
  }

  @override
  double get maxExtent => 72;

  @override
  double get minExtent => 72;

  @override
  bool shouldRebuild(covariant _SearchHeaderDelegate oldDelegate) {
    return onTap != oldDelegate.onTap;
  }
}

class _ActiveOrderCard extends StatelessWidget {
  final Order order;

  const _ActiveOrderCard({required this.order});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () =>
          Navigator.pushNamed(context, '/order/track', arguments: order.id),
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFFE23744), Color(0xFFFF6B7A)],
          ),
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(
              color: FoodFlowTheme.crimson.withOpacity(0.18),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.18),
                borderRadius: BorderRadius.circular(15),
              ),
              child: const Icon(Icons.delivery_dining,
                  color: Colors.white, size: 23),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Running order #${order.orderNumber}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${order.restaurant?.name ?? 'Restaurant'} | ${order.statusText}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.78),
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Text(
                'Track',
                style: TextStyle(
                  color: FoodFlowTheme.crimson,
                  fontWeight: FontWeight.w900,
                  fontSize: 12,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RatingSection extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  final int rating;
  final ValueChanged<int> onChanged;
  final TextEditingController feedbackController;
  final String feedbackHint;

  const _RatingSection({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.rating,
    required this.onChanged,
    required this.feedbackController,
    required this.feedbackHint,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(9),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF3E7),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: AppConfig.primaryColor, size: 20),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      subtitle,
                      style: TextStyle(
                        color: Colors.grey.shade600,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: List.generate(5, (index) {
              final value = index + 1;
              return IconButton(
                visualDensity: VisualDensity.compact,
                padding: EdgeInsets.zero,
                constraints:
                    const BoxConstraints.tightFor(width: 38, height: 38),
                onPressed: () => onChanged(value),
                icon: Icon(
                  value <= rating ? Icons.star : Icons.star_border,
                  color: Colors.amber.shade700,
                  size: 30,
                ),
              );
            }),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: feedbackController,
            minLines: 2,
            maxLines: 3,
            textInputAction: TextInputAction.newline,
            decoration: InputDecoration(
              hintText: feedbackHint,
              filled: true,
              fillColor: Colors.white,
              contentPadding: const EdgeInsets.all(12),
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  const _FilterChip({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
        decoration: BoxDecoration(
          color: selected ? FoodFlowTheme.crimson : Colors.white,
          border: Border.all(
              color: selected ? FoodFlowTheme.crimson : const Color(0xFFF0DADB),
              width: 1),
          borderRadius: BorderRadius.circular(22),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: FoodFlowTheme.crimson.withOpacity(0.16),
                    blurRadius: 14,
                    offset: const Offset(0, 6),
                  ),
                ]
              : [],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon,
                size: 14,
                color: selected ? Colors.white : FoodFlowTheme.crimson),
            const SizedBox(width: 5),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: selected ? Colors.white : FoodFlowTheme.ink,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DishPreview {
  final String name;
  final String imageUrl;
  final double price;
  final bool isVeg;
  final int restaurantId;
  final String restaurantName;

  const _DishPreview({
    required this.name,
    required this.imageUrl,
    required this.price,
    required this.isVeg,
    required this.restaurantId,
    required this.restaurantName,
  });
}

class _RestaurantMenuInsights {
  final int restaurantId;
  final int availableItemCount;
  final double minPrice;
  final bool isPureVegMenu;
  final int bestsellerCount;
  final int highlyOrderedCount;

  const _RestaurantMenuInsights({
    required this.restaurantId,
    required this.availableItemCount,
    required this.minPrice,
    required this.isPureVegMenu,
    required this.bestsellerCount,
    required this.highlyOrderedCount,
  });

  bool get hasBestSeller => bestsellerCount > 0;
  bool get hasHighlyOrdered => highlyOrderedCount > 0;

  factory _RestaurantMenuInsights.fromItems(
    int restaurantId,
    List<MenuItem> items, {
    required int highOrderThreshold,
  }) {
    final availableItems = items.where((item) => item.isAvailable).toList();
    final prices = availableItems.map((item) => item.finalPrice).toList();

    return _RestaurantMenuInsights(
      restaurantId: restaurantId,
      availableItemCount: availableItems.length,
      minPrice: prices.isEmpty
          ? double.infinity
          : prices.reduce((left, right) => left < right ? left : right),
      isPureVegMenu: availableItems.isNotEmpty &&
          availableItems.every((item) => item.isVeg),
      bestsellerCount: availableItems.where((item) => item.isBestseller).length,
      highlyOrderedCount: availableItems
          .where((item) => item.totalOrders >= highOrderThreshold)
          .length,
    );
  }

  static _RestaurantMenuInsights? fromDynamic(dynamic raw) {
    if (raw is! Map) {
      return null;
    }

    double parseDouble(dynamic value, {double fallback = 0}) {
      if (value is double) return value;
      if (value is int) return value.toDouble();
      return double.tryParse(value?.toString() ?? '') ?? fallback;
    }

    int parseInt(dynamic value, {int fallback = 0}) {
      if (value is int) return value;
      if (value is double) return value.toInt();
      return int.tryParse(value?.toString() ?? '') ?? fallback;
    }

    bool parseBool(dynamic value, {bool fallback = false}) {
      if (value is bool) return value;
      if (value is int) return value != 0;
      final normalized = value?.toString().trim().toLowerCase();
      if (normalized == null) return fallback;
      return normalized == 'true' ||
          normalized == '1' ||
          normalized == 'yes' ||
          normalized == 'y';
    }

    return _RestaurantMenuInsights(
      restaurantId: parseInt(raw['restaurant_id']),
      availableItemCount: parseInt(raw['available_item_count']),
      minPrice: parseDouble(raw['min_price'], fallback: double.infinity),
      isPureVegMenu: parseBool(raw['is_pure_veg_menu']),
      bestsellerCount: parseInt(raw['bestseller_count']),
      highlyOrderedCount: parseInt(raw['highly_ordered_count']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'restaurant_id': restaurantId,
      'available_item_count': availableItemCount,
      'min_price': minPrice,
      'is_pure_veg_menu': isPureVegMenu,
      'bestseller_count': bestsellerCount,
      'highly_ordered_count': highlyOrderedCount,
      'has_best_seller': hasBestSeller,
      'has_highly_ordered': hasHighlyOrdered,
    };
  }
}
