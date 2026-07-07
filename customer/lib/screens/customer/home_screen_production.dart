import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'dart:ui' as ui;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:lottie/lottie.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../utils/route_observer.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../models/address.dart' as app_address;
import '../../models/menu_item.dart';
import '../../models/order.dart';
import '../../models/restaurant.dart';
import '../../providers/cart_provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/order_provider.dart';
import '../../services/api_service.dart';
import '../../services/app_image_cache.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../services/app_branding_service.dart';
import '../../services/location_service.dart';
import '../../services/websocket_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/lucide_icon.dart';
import '../../widgets/customer/menu_item_card.dart';
import '../customer/cart_screen.dart';
import '../customer/orders_screen.dart';
import '../customer/profile_screen.dart';
import '../customer/restaurant_detail_screen.dart';
import '../customer/search_screen.dart';

const _homeAccent = Color(0xFFFF6B00);
const _homeAccentDeep = Color(0xFFFF7A00);
const _homePeach = Color(0xFFFFE9DD);
const _homeBg = Color(0xFFFAFAFA);
const _homeText = Color(0xFF111827);
const _homeMuted = Color(0xFF6B7280);
const _homeBorder = Color(0xFFE5E7EB);
const _homeGreen = Color(0xFF16A34A);
const _homeLightGreen = Color(0xFFEAF8EA);
const _homeRed = Color(0xFFE53935);
const _vegModePrefsKey = 'customer_home_veg_only_mode';
final Set<int> _sessionShownPopupCampaignIds = <int>{};

enum _HomeBlockingState { offline, deliveryUnavailable }

String _resolveHomeAssetUrl(String rawValue) {
  return AppImageCache.resolveUrl(rawValue);
}

bool _isLottieUrl(String url) {
  final normalized = url.trim().toLowerCase();
  if (normalized.isEmpty) return false;
  final uri = Uri.tryParse(normalized);
  final path = uri?.path.toLowerCase() ?? normalized.split('?').first;
  final format = uri?.queryParameters['format']?.toLowerCase();
  final type = uri?.queryParameters['type']?.toLowerCase();
  final filename = path.split('/').last;

  return filename.endsWith('.json') ||
      path.contains('.json/') ||
      format == 'json' ||
      type == 'lottie' ||
      normalized.contains('lottie') ||
      normalized.contains('animation');
}

bool _isLottieMediaItem(dynamic item) {
  if (item is! Map) return false;

  final mediaType = item['media_type']?.toString().toLowerCase();
  if (mediaType == 'lottie' ||
      mediaType == 'animation' ||
      mediaType == 'json') {
    return true;
  }

  final type = item['type']?.toString().toLowerCase();
  if (type == 'lottie' || type == 'animation') return true;

  final format = item['format']?.toString().toLowerCase();
  if (format == 'json') return true;

  for (final key in const <String>[
    'animation_url',
    'lottie_url',
    'json_url',
    'media_url',
    'asset_url',
    'webp_url',
    'hero_image',
    'image',
    'banner_image',
    'image_url',
    'photo'
  ]) {
    final value = item[key];
    if (value is String && value.trim().isNotEmpty) {
      final normalizedValue = value.trim().toLowerCase();
      if (normalizedValue.contains('lottie') ||
          normalizedValue.contains('animation') ||
          normalizedValue.endsWith('.json')) {
        return true;
      }
    }
  }

  return false;
}

double _homePriceValue(dynamic value) {
  if (value == null) return 0;
  if (value is num) return value.toDouble();
  if (value is String) {
    final cleaned = value.replaceAll(RegExp(r'[^0-9.\-]'), '');
    if (cleaned.isEmpty || cleaned == '-' || cleaned == '.') return 0;
    return double.tryParse(cleaned) ?? 0;
  }
  if (value is Map) {
    for (final key in const ['amount', 'value', 'price', 'final_price']) {
      final parsed = _homePriceValue(value[key]);
      if (parsed > 0) return parsed;
    }
  }
  return 0;
}

double _homeMenuItemPrice(Map<String, dynamic> item) {
  for (final value in <dynamic>[
    item['final_price'],
    item['finalPrice'],
    item['discounted_price'],
    item['discountedPrice'],
    item['sale_price'],
    item['offer_price'],
    item['price'],
    item['base_price'],
    item['regular_price'],
    item['item_price'],
    item['menu_price'],
    item['unit_price'],
    item['mrp'],
    item['selling_price'],
    item['amount'],
  ]) {
    final parsed = _homePriceValue(value);
    if (parsed > 0) return parsed;
  }

  for (final key in const ['variant', 'default_variant', 'selected_variant']) {
    final value = item[key];
    if (value is Map) {
      final parsed = _homePriceValue(value['price'] ?? value['final_price']);
      if (parsed > 0) return parsed;
    }
  }

  final variants = item['variants'];
  if (variants is List) {
    final prices = variants
        .whereType<Map>()
        .map((variant) => _homePriceValue(
              variant['price'] ?? variant['final_price'] ?? variant['amount'],
            ))
        .where((value) => value > 0)
        .toList();
    if (prices.isNotEmpty) {
      prices.sort();
      return prices.first;
    }
  }

  return 0;
}

double _homeMinimumNestedMenuPrice(dynamic value) {
  final prices = <double>[];

  void collectPrice(dynamic candidate, {bool nestedOnly = false}) {
    if (candidate == null) return;
    if (candidate is Map) {
      final map = Map<String, dynamic>.from(candidate);
      if (!nestedOnly) {
        final directPrice = _homeMenuItemPrice(map);
        if (directPrice > 0) prices.add(directPrice);
      }
      for (final key in const [
        'items',
        'menu_items',
        'matched_menu_items',
        'matchedMenuItems',
        'popular_items',
        'popular_dishes',
        'recommended_items',
        'products',
      ]) {
        collectPrice(map[key]);
      }
      return;
    }
    if (candidate is List) {
      for (final item in candidate) {
        collectPrice(item);
      }
    }
  }

  collectPrice(value, nestedOnly: true);
  if (prices.isEmpty) return 0;
  prices.sort();
  return prices.first;
}

Future<bool> _isRemoteImageUrl(Uri uri) async {
  try {
    final headResponse =
        await http.head(uri).timeout(const Duration(seconds: 4));
    final contentType =
        headResponse.headers['content-type']?.toLowerCase() ?? '';
    if (headResponse.statusCode == 200 && contentType.startsWith('image/')) {
      return true;
    }
    if (headResponse.statusCode == 405 || headResponse.statusCode == 501) {
      final getResponse = await http.get(uri, headers: {
        'Range': 'bytes=0-8191'
      }).timeout(const Duration(seconds: 6));
      final getType = getResponse.headers['content-type']?.toLowerCase() ?? '';
      return getResponse.statusCode == 200 && getType.startsWith('image/');
    }
    return false;
  } catch (_) {
    return false;
  }
}

Future<void> _safePrecacheImage(String url, BuildContext context) async {
  final uri = Uri.tryParse(url);
  if (uri == null || !uri.isAbsolute) return;

  try {
    await precacheImage(
      CachedNetworkImageProvider(url, cacheManager: AppImageCache.instance),
      context,
    );
  } catch (_) {
    // ignore invalid image data or network decode failures
  }
}

Widget _buildHomeNetworkImage(
  String imageUrl, {
  BoxFit fit = BoxFit.cover,
  double? width,
  double? height,
  Widget? placeholder,
  Widget? errorWidget,
}) {
  final resolvedUrl = _resolveHomeAssetUrl(imageUrl);
  final uri = Uri.tryParse(resolvedUrl);
  if (resolvedUrl.isEmpty || uri == null || !uri.isAbsolute) {
    return errorWidget ?? const SizedBox.shrink();
  }
  if (_isLottieUrl(resolvedUrl)) {
    return errorWidget ?? const SizedBox.shrink();
  }

  return AppCachedImage(
    imageUrl: resolvedUrl,
    fit: fit,
    width: width,
    height: height,
    loadingBuilder: (context, _, __) {
      return placeholder ??
          Container(
            color: const Color(0xFFF3F3F3),
            child: const Center(
              child: SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
          );
    },
    errorBuilder: (_, __, ___) => errorWidget ?? const SizedBox.shrink(),
  );
}

Widget _buildImageOrFallback(
  String imageUrl, {
  BoxFit fit = BoxFit.cover,
  double? width,
  double? height,
  Widget? placeholder,
  Widget? errorWidget,
}) {
  final resolvedUrl = _resolveHomeAssetUrl(imageUrl);
  if (resolvedUrl.isEmpty || _isLottieUrl(resolvedUrl)) {
    return errorWidget ?? placeholder ?? const SizedBox.shrink();
  }
  return _buildHomeNetworkImage(
    imageUrl,
    fit: fit,
    width: width,
    height: height,
    placeholder: placeholder,
    errorWidget: errorWidget,
  );
}

class CustomerHomeScreenProduction extends StatefulWidget {
  const CustomerHomeScreenProduction({super.key});

  @override
  State<CustomerHomeScreenProduction> createState() =>
      _CustomerHomeScreenProductionState();
}

class _CustomerHomeScreenProductionState
    extends State<CustomerHomeScreenProduction>
    with RouteAware, WidgetsBindingObserver {
  final LocationService _locationService = LocationService();
  final ApiService _api = ApiService();
  int _currentIndex = 0;
  int _locationRevision = 0;
  Timer? _activeOrderRefreshTimer;
  bool _isRefreshingActiveOrders = false;
  bool _feedbackPromptOpen = false;
  bool _feedbackPromptChecked = false;
  bool _isRouteAwareSubscribed = false;
  int? _realtimeCustomerId;
  String? _realtimeCustomerHandlerId;
  bool _showDiningMode = false;
  bool _hasPromptedForLocation = false;
  String _currentCity = 'Home';
  String _currentAddress = 'Select your delivery address';
  double? _currentLat;
  double? _currentLng;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _ensureLocationSelected();
      final orderProvider = context.read<OrderProvider>();
      if (orderProvider.orders.isEmpty) {
        orderProvider
            .fetchMyOrders()
            .then((_) => _maybeShowRecentOrderFeedback());
      } else {
        _maybeShowRecentOrderFeedback();
      }
      _startActiveOrderRefreshTimer();
      _initializeCustomerRealtime();
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_isRouteAwareSubscribed) {
      final route = ModalRoute.of(context);
      if (route is PageRoute) {
        routeObserver.subscribe(this, route);
        _isRouteAwareSubscribed = true;
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _activeOrderRefreshTimer?.cancel();
    final customerId = _realtimeCustomerId;
    if (customerId != null) {
      WebSocketService().removeCustomerHandler(
        customerId,
        _realtimeCustomerHandlerId,
      );
    }
    if (_isRouteAwareSubscribed) {
      routeObserver.unsubscribe(this);
    }
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(_loadLocation());
      _refresh();
      unawaited(_refreshActiveOrders());
    }
  }

  Future<void> _initializeCustomerRealtime() async {
    for (var attempt = 0; attempt < 10 && mounted; attempt++) {
      final user = context.read<AuthProvider>().currentUser;
      if (user != null) {
        _realtimeCustomerId = user.id;
        _realtimeCustomerHandlerId = await WebSocketService().initCustomer(
          user.id,
          onOrderUpdate: _handleRealtimeOrderUpdate,
        );
        return;
      }
      await Future<void>.delayed(const Duration(milliseconds: 300));
    }
  }

  void _handleRealtimeOrderUpdate(Map<String, dynamic> data) {
    if (!mounted) return;
    final order = context.read<OrderProvider>().applyOrderStatusUpdate(data);
    if (order == null) {
      unawaited(_refreshActiveOrders());
      return;
    }

    if (order.isDelivered) {
      _feedbackPromptChecked = false;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _maybeShowRecentOrderFeedback(force: true);
      });
    }

    unawaited(
      _refreshOrdersAfterRealtimeUpdate(showFeedback: order.isDelivered),
    );
  }

  Future<void> _refreshOrdersAfterRealtimeUpdate(
      {required bool showFeedback}) async {
    await context.read<OrderProvider>().fetchMyOrders(notifyLoading: false);
    if (mounted && showFeedback) {
      await _maybeShowRecentOrderFeedback(force: true);
    }
  }

  @override
  void didPopNext() {
    super.didPopNext();
    _loadLocation();
    _refreshActiveOrders();
  }

  void _startActiveOrderRefreshTimer() {
    _activeOrderRefreshTimer?.cancel();
    _activeOrderRefreshTimer = Timer.periodic(const Duration(seconds: 12), (_) {
      _refreshActiveOrders();
    });
  }

  Future<void> _refreshActiveOrders() async {
    if (!mounted || _isRefreshingActiveOrders) return;
    _isRefreshingActiveOrders = true;
    try {
      final provider = context.read<OrderProvider>();
      final hasRunningOrder = provider.orders.any(_isTrackableOrder);
      if (provider.orders.isEmpty || hasRunningOrder) {
        await provider.fetchMyOrders(notifyLoading: false);
        await _maybeShowRecentOrderFeedback();
      }
    } finally {
      _isRefreshingActiveOrders = false;
    }
  }

  bool _isTrackableOrder(Order order) {
    return !order.isDelivered &&
        !order.isCancelled &&
        const <String>{
          'pending',
          'confirmed',
          'preparing',
          'ready_for_pickup',
          'reached_pickup',
          'picked_up',
          'on_the_way',
        }.contains(order.status);
  }

  Future<void> _maybeShowRecentOrderFeedback({bool force = false}) async {
    if (!mounted || _feedbackPromptOpen) return;
    if (_feedbackPromptChecked && !force) return;
    _feedbackPromptChecked = true;

    final provider = context.read<OrderProvider>();
    final delivered = provider.orders.where((order) => order.needsFeedback);
    if (delivered.isEmpty) return;

    final order = delivered.first;
    final prefs = await SharedPreferences.getInstance();
    final dismissedOrderId = prefs.getInt('dismissed_feedback_order_id');
    if (!force && dismissedOrderId == order.id) return;

    _feedbackPromptOpen = true;
    await _showFeedbackDialog(order);
    _feedbackPromptOpen = false;
  }

  Future<void> _showFeedbackDialog(Order order) async {
    int itemRating = 5;
    int restaurantRating = 5;
    int driverRating = 5;
    int serviceRating = 5;
    final itemController = TextEditingController();
    final restaurantController = TextEditingController();
    final driverController = TextEditingController();
    final serviceController = TextEditingController();
    final canRateDriver = !order.isTakeaway && order.driver != null;
    var isSubmitting = false;
    var feedbackSubmitted = false;

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            Future<void> submit() async {
              if (isSubmitting) return;
              setDialogState(() => isSubmitting = true);
              final success =
                  await context.read<OrderProvider>().submitFeedback(
                        orderId: order.id,
                        itemRating: itemRating,
                        restaurantRating: restaurantRating,
                        driverRating: canRateDriver ? driverRating : null,
                        serviceRating: serviceRating,
                        itemFeedback: itemController.text,
                        restaurantFeedback: restaurantController.text,
                        driverFeedback:
                            canRateDriver ? driverController.text : null,
                        serviceFeedback: serviceController.text,
                      );

              if (!mounted || !dialogContext.mounted) return;

              if (success) {
                final prefs = await SharedPreferences.getInstance();
                await prefs.setInt('dismissed_feedback_order_id', order.id);
                feedbackSubmitted = true;
                Navigator.of(dialogContext).pop();
                return;
              }

              setDialogState(() => isSubmitting = false);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Could not submit feedback.')),
              );
            }

            return AlertDialog(
              backgroundColor: Colors.white,
              surfaceTintColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(28),
              ),
              icon: Container(
                width: 58,
                height: 58,
                decoration: const BoxDecoration(
                  color: _homeLightGreen,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_circle_rounded,
                  color: _homeGreen,
                  size: 36,
                ),
              ),
              title: const Text(
                'Order completed!',
                style: TextStyle(
                  color: _homeText,
                  fontWeight: FontWeight.w900,
                ),
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'How was your order from ${order.restaurant?.name ?? 'the restaurant'}?',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: _homeMuted,
                      ),
                    ),
                    const SizedBox(height: 18),
                    _FeedbackRatingSection(
                      title: 'Items',
                      rating: itemRating,
                      controller: itemController,
                      hint: 'How was the food?',
                      onChanged: (value) =>
                          setDialogState(() => itemRating = value),
                    ),
                    _FeedbackRatingSection(
                      title: 'Restaurant',
                      rating: restaurantRating,
                      controller: restaurantController,
                      hint: 'Packaging, freshness, restaurant experience',
                      onChanged: (value) =>
                          setDialogState(() => restaurantRating = value),
                    ),
                    if (canRateDriver)
                      _FeedbackRatingSection(
                        title: 'Delivery partner · ${order.driver!.name}',
                        rating: driverRating,
                        controller: driverController,
                        hint: 'Delivery experience and behaviour',
                        onChanged: (value) =>
                            setDialogState(() => driverRating = value),
                      ),
                    _FeedbackRatingSection(
                      title: 'Service',
                      rating: serviceRating,
                      controller: serviceController,
                      hint: 'Delivery and overall service',
                      onChanged: (value) =>
                          setDialogState(() => serviceRating = value),
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: isSubmitting
                      ? null
                      : () async {
                          final prefs = await SharedPreferences.getInstance();
                          await prefs.setInt(
                              'dismissed_feedback_order_id', order.id);
                          if (dialogContext.mounted) {
                            Navigator.of(dialogContext).pop();
                          }
                        },
                  child: const Text(
                    'Later',
                    style: TextStyle(color: _homeMuted),
                  ),
                ),
                ElevatedButton(
                  onPressed: isSubmitting ? null : submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _homeAccent,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: isSubmitting
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Submit'),
                ),
              ],
            );
          },
        );
      },
    );

    // The dialog future resolves before the reverse route animation has fully
    // removed its TextFields. Wait before disposing their controllers or
    // opening the next dialog to avoid overlapping Flutter build scopes.
    await Future<void>.delayed(const Duration(milliseconds: 350));
    itemController.dispose();
    restaurantController.dispose();
    driverController.dispose();
    serviceController.dispose();

    if (feedbackSubmitted && mounted) {
      await _loadPlayStoreCta();
    }
  }

  Future<void> _loadPlayStoreCta() async {
    final branding = await AppBrandingService.instance.loadBranding();
    final playStoreUrl = branding.customerPlayStoreUrl.trim();
    if (!mounted || playStoreUrl.isEmpty) return;

    final openStore = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Enjoying foodflow?'),
        content: const Text('Would you like to rate us on the Play Store?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Not now'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Rate us'),
          ),
        ],
      ),
    );

    if (openStore == true) {
      await launchUrl(Uri.parse(playStoreUrl),
          mode: LaunchMode.externalApplication);
    }
  }

  void _refresh() {
    setState(() {
      _locationRevision++;
    });
  }

  Future<void> _loadLocation() async {
    final savedLocation = await _locationService.getSavedLocation();
    if (!mounted || savedLocation == null) return;

    final nextCity =
        (savedLocation['city']?.toString().trim().isNotEmpty ?? false)
            ? savedLocation['city'].toString().trim()
            : 'Home';
    final savedAddress = savedLocation['address']?.toString().trim();
    final nextAddress =
        savedAddress?.isNotEmpty == true ? savedAddress! : _currentAddress;
    final nextLat = _parseDouble(savedLocation['lat']);
    final nextLng = _parseDouble(savedLocation['lng']);
    final changed = nextCity != _currentCity ||
        nextLat != _currentLat ||
        nextLng != _currentLng;

    if (!changed) return;

    setState(() {
      _currentCity = nextCity;
      _currentAddress = nextAddress;
      _currentLat = nextLat;
      _currentLng = nextLng;
      _locationRevision++;
    });
  }

  Future<void> _ensureLocationSelected() async {
    if (_hasPromptedForLocation) return;

    await _loadLocation();
    if (!mounted) return;

    final savedLocation = await _locationService.getSavedLocation();
    final hasLocation = savedLocation != null &&
        (savedLocation['address']?.toString().trim().isNotEmpty == true ||
            savedLocation['city']?.toString().trim().isNotEmpty == true);

    if (!hasLocation) {
      _hasPromptedForLocation = true;
      await _openLocationPicker();
    }
  }

  Future<void> _openLocationPicker() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: FoodFlowTheme.canvas,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
      ),
      builder: (context) {
        return _LocationPickerSheet(
          loadSavedAddresses: _loadSavedAddresses,
          onUseCurrentLocation: _useCurrentLocation,
          onManualSearch: _searchLocationManually,
          onSavedAddressSelected: _useSavedAddress,
          onManageAddresses: () async {
            Navigator.pop(context);
            await Navigator.pushNamed(context, '/addresses');
            if (!mounted) return;
            await _loadLocation();
          },
          onAddAddress: () async {
            Navigator.pop(context);
            await Navigator.pushNamed(context, '/addresses/add');
            if (!mounted) return;
            await _loadLocation();
          },
        );
      },
    );
  }

  Future<List<app_address.Address>> _loadSavedAddresses() async {
    final response = await _api.get(ApiConstants.addresses);
    final data = response is Map ? response['data'] : response;
    final items = data is List
        ? data
        : data is Map && data['data'] is List
            ? data['data'] as List
            : const <dynamic>[];
    final addresses = items
        .whereType<Map>()
        .map((item) => app_address.Address.fromJson(
              Map<String, dynamic>.from(item),
            ))
        .toList();
    addresses.sort((a, b) {
      if (a.isDefault == b.isDefault) return b.id.compareTo(a.id);
      return a.isDefault ? -1 : 1;
    });
    return addresses;
  }

  Future<void> _useCurrentLocation() async {
    final position = await _locationService.getCurrentLocation();
    if (!mounted) return;
    if (position == null) {
      _showLocationMessage('Unable to fetch current location.');
      return;
    }

    final address = await _locationService.getAddressFromLatLng(
      position.latitude,
      position.longitude,
    );
    if (!mounted) return;

    final city = address?['city']?.trim();
    final label = address?['address']?.trim();
    await _applySelectedLocation(
      city: city?.isNotEmpty == true ? city! : 'Current location',
      address: label?.isNotEmpty == true ? label! : 'Current location',
      latitude: position.latitude,
      longitude: position.longitude,
    );
  }

  Future<void> _searchLocationManually() async {
    final selected = await showModalBottomSheet<_ManualLocationResult>(
      context: context,
      isScrollControlled: true,
      backgroundColor: FoodFlowTheme.canvas,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(30)),
      ),
      builder: (context) {
        return _ManualLocationSearchSheet(
          loadSuggestions: _locationService.getLocationSuggestions,
        );
      },
    );

    if (!mounted || selected == null) return;
    await _applySelectedLocation(
      city: selected.city,
      address: selected.address,
      latitude: selected.latitude,
      longitude: selected.longitude,
      closePicker: false,
    );
  }

  Future<void> _useSavedAddress(app_address.Address address) async {
    final lat = address.latitude;
    final lng = address.longitude;
    if (lat == null || lng == null) {
      _showLocationMessage(
          'Edit this address and pin the exact location first.');
      return;
    }

    await _applySelectedLocation(
      city: address.city.trim().isNotEmpty ? address.city.trim() : address.name,
      address: address.fullAddress,
      latitude: lat,
      longitude: lng,
    );
  }

  Future<void> _applySelectedLocation({
    required String city,
    required String address,
    required double latitude,
    required double longitude,
    bool closePicker = true,
  }) async {
    await _locationService.saveLocation(
      city,
      latitude,
      longitude,
      address: address,
    );
    if (!mounted) return;
    if (closePicker && Navigator.canPop(context)) {
      Navigator.pop(context);
    }
    setState(() {
      _currentCity = city;
      _currentAddress = address;
      _currentLat = latitude;
      _currentLng = longitude;
      _locationRevision++;
    });
  }

  double? _parseDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  void _showLocationMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  void _openCartTab() {
    setState(() {
      _currentIndex = 2;
    });
  }

  Widget _buildPage() {
    switch (_currentIndex) {
      case 1:
        return const SearchScreen(embedded: true);
      case 2:
        return const CartScreen();
      case 3:
        return const OrdersScreen();
      case 4:
        return const ProfileScreen();
      case 0:
      default:
        return _CustomerHomeFeed(
          currentCity: _currentCity,
          currentAddress: _currentAddress,
          locationRevision: _locationRevision,
          showDiningMode: _showDiningMode,
          onDiningModeChanged: (value) {
            setState(() {
              _showDiningMode = value;
            });
          },
          onLocationTap: _openLocationPicker,
          onCartTap: _openCartTab,
          onNotificationTap: () async {
            await Navigator.pushNamed(context, '/notifications');
            if (mounted) setState(() {});
          },
          onWalletTap: () => Navigator.pushNamed(context, '/wallet'),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final cartCount = context.watch<CartProvider>().itemCount;

    return Scaffold(
      backgroundColor: _homeBg,
      body: _buildPage(),
      bottomNavigationBar: _currentIndex == 0 || _currentIndex > 0
          ? _HomeBottomNavBar(
              currentIndex: _currentIndex,
              cartCount: cartCount,
              cartActive: _currentIndex == 2,
              onTap: (index) {
                setState(() {
                  _currentIndex = index;
                });
              },
              onCartTap: _openCartTab,
            )
          : null,
    );
  }
}

class _CustomerHomeFeed extends StatefulWidget {
  const _CustomerHomeFeed({
    required this.currentCity,
    required this.currentAddress,
    required this.locationRevision,
    required this.showDiningMode,
    required this.onDiningModeChanged,
    required this.onLocationTap,
    required this.onCartTap,
    required this.onNotificationTap,
    required this.onWalletTap,
  });

  final String currentCity;
  final String currentAddress;
  final int locationRevision;
  final bool showDiningMode;
  final ValueChanged<bool> onDiningModeChanged;
  final VoidCallback onLocationTap;
  final VoidCallback onCartTap;
  final VoidCallback onNotificationTap;
  final VoidCallback onWalletTap;

  @override
  State<_CustomerHomeFeed> createState() => _CustomerHomeFeedState();
}

class _CustomerHomeFeedState extends State<_CustomerHomeFeed> {
  final ApiService _api = ApiService();
  final LocationService _locationService = LocationService();

  bool _isLoading = true;
  bool _isRefreshing = false;
  bool _isLoadingRestaurantFeed = false;
  bool _isLoadingPopularDishes = false;
  bool _vegOnlyMode = false;
  List<dynamic> _banners = [];
  List<Map<String, dynamic>> _popupCampaigns = [];
  List<dynamic> _categories = [];
  List<dynamic> _offers = [];
  List<dynamic> _restaurants = [];
  List<Map<String, dynamic>> _homeSections = [];
  List<_HomeDishCardData> _popularDishes = [];
  List<Map<String, dynamic>> _filteredCategoryRestaurants = [];
  List<_HomeDishCardData> _filteredCategoryDishes = [];
  final Set<int> _savedRestaurantIds = <int>{};
  String? _activeCategoryFilter;
  bool _isLoadingCategoryItems = false;
  bool _hasDeliveryLocation = false;
  _HomeBlockingState? _blockingState;
  int _notificationCount = 0;
  Timer? _cacheRefreshDebounce;

  @override
  void initState() {
    super.initState();
    _loadSavedRestaurantIds();
    _loadNotificationCount();
    _loadVegModePreference();
    _loadHomeData();
  }

  @override
  void dispose() {
    _cacheRefreshDebounce?.cancel();
    super.dispose();
  }

  void _scheduleCachedHomeRebuild() {
    _cacheRefreshDebounce?.cancel();
    _cacheRefreshDebounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) {
        unawaited(_loadHomeData(cacheFirst: true, refreshCached: false));
      }
    });
  }

  Future<void> _loadVegModePreference() async {
    final prefs = await SharedPreferences.getInstance();
    if (!mounted) return;
    setState(() {
      _vegOnlyMode = prefs.getBool(_vegModePrefsKey) ?? false;
    });
  }

  Future<void> _setVegOnlyMode(bool value) async {
    if (_vegOnlyMode == value) return;
    setState(() {
      _vegOnlyMode = value;
    });
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_vegModePrefsKey, value);
  }

  Future<void> _loadNotificationCount() async {
    try {
      final response = await _api.get(
        ApiConstants.notifications,
        queryParams: const {'limit': 1, 'target_app': 'customer'},
      );
      final data = response['data'];
      if (!mounted || data is! Map) return;
      setState(() {
        _notificationCount = int.tryParse('${data['unread_count'] ?? 0}') ?? 0;
      });
    } catch (_) {}
  }

  @override
  void didUpdateWidget(covariant _CustomerHomeFeed oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.showDiningMode != widget.showDiningMode ||
        oldWidget.currentCity != widget.currentCity ||
        oldWidget.locationRevision != widget.locationRevision) {
      _loadHomeData();
    }
  }

  Future<void> _loadSavedRestaurantIds() async {
    final prefs = await SharedPreferences.getInstance();
    final saved =
        prefs.getStringList('saved_restaurant_ids') ?? const <String>[];
    if (!mounted) return;
    setState(() {
      _savedRestaurantIds
        ..clear()
        ..addAll(saved.map(int.tryParse).whereType<int>());
    });
  }

  Future<void> _toggleSavedRestaurant(Map<String, dynamic> restaurant) async {
    final restaurantId = _restaurantId(restaurant);
    if (restaurantId <= 0) return;
    final nextSaved = Set<int>.from(_savedRestaurantIds);
    final shouldSave = !nextSaved.remove(restaurantId);
    if (shouldSave) {
      nextSaved.add(restaurantId);
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList(
      'saved_restaurant_ids',
      nextSaved.map((id) => id.toString()).toList(growable: false),
    );

    try {
      if (shouldSave) {
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
  }

  Future<void> _loadHomeData({
    bool cacheFirst = true,
    bool refreshCached = true,
  }) async {
    final hasExistingContent = _hasHomeContent;
    if (mounted) {
      setState(() {
        _isLoading = !hasExistingContent;
        _blockingState = null;
      });
    }

    try {
      final savedLocation = await _locationService.getSavedLocation();
      final lat = _parseDoubleOrNull(savedLocation?['lat']);
      final lng = _parseDoubleOrNull(savedLocation?['lng']);

      Future<dynamic> restaurantFeedRequest() {
        if (lat == null || lng == null) {
          return Future<dynamic>.value(
            <String, dynamic>{'success': true, 'data': <dynamic>[]},
          );
        }

        return _api.get(
          widget.showDiningMode
              ? ApiConstants.diningRestaurants
              : ApiConstants.nearbyRestaurants,
          queryParams: <String, dynamic>{
            'lat': lat,
            'lng': lng,
            if (!widget.showDiningMode) 'radius': 100,
          },
          includeAuth: false,
          cacheResponse: true,
          cacheFirst: cacheFirst,
          refreshCached: refreshCached,
          onCacheRefreshed: (_) => _scheduleCachedHomeRebuild(),
        );
      }

      final results = await Future.wait<dynamic>([
        _safeHomeGet(
          ApiConstants.popularCuisines,
          cacheFirst: cacheFirst,
          refreshCached: refreshCached,
        ),
        _safeHomeGet(
          ApiConstants.homeSections,
          queryParams: <String, dynamic>{
            if (lat != null && lng != null) ...<String, dynamic>{
              'lat': lat,
              'lng': lng,
              'radius': 100,
            },
          },
          cacheFirst: cacheFirst,
          refreshCached: refreshCached,
        ),
      ]);

      final categories = _extractList(results[0]);
      final homeSections = _extractList(results[1])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .where((item) => item['enabled'] != false)
          .toList(growable: false);
      final hasUsableHomeSections = homeSections.any(_sectionHasServerContent);
      final hasDeliveryZoneContext = lat != null && lng != null;
      final nextHomeSections = hasDeliveryZoneContext ||
              hasUsableHomeSections ||
              _homeSections.isEmpty
          ? homeSections
          : _homeSections;
      final homeSectionsChanged =
          !_jsonEquivalent(_homeSections, nextHomeSections);

      if (!mounted) return;
      setState(() {
        final nextCategories =
            _resolveCategoryItems(nextHomeSections, categories);
        final sectionRestaurants = _restaurantsFromHomeSections(
          nextHomeSections,
        );
        _categories = nextCategories.isNotEmpty || _categories.isEmpty
            ? nextCategories
            : _categories;
        if (homeSectionsChanged) _homeSections = nextHomeSections;
        final serverPopularDishes = nextHomeSections
            .where((section) => section['type'] == 'popular_dishes')
            .expand((section) => _mapList(section['items']))
            .map(_dishDataFromSectionItem)
            .whereType<_HomeDishCardData>()
            .toList(growable: false);
        if (serverPopularDishes.isNotEmpty) {
          _popularDishes = serverPopularDishes;
          _isLoadingPopularDishes = false;
        }
        if (sectionRestaurants.isNotEmpty && _restaurants.isEmpty) {
          _restaurants = sectionRestaurants;
        }
        if (homeSectionsChanged) {
          _activeCategoryFilter = null;
          _filteredCategoryRestaurants = [];
          _filteredCategoryDishes = [];
        }
        _isLoadingRestaurantFeed = true;
        _isLoading = false;
        _hasDeliveryLocation = lat != null && lng != null;
        _blockingState = null;
      });

      _precacheHomeImages(
        banners: const <dynamic>[],
        categories: _categories,
        sections: nextHomeSections,
        popupCampaigns: const <Map<String, dynamic>>[],
      );
      unawaited(_loadDeferredHomeContent(
        nextHomeSections,
        cacheFirst: cacheFirst,
        refreshCached: refreshCached,
      ));
      unawaited(_loadRestaurantFeedAfterFirstPaint(restaurantFeedRequest()));
    } catch (e) {
      if (!mounted) return;
      final offline = await _isOfflineNow();
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _isLoadingRestaurantFeed = false;
        _blockingState = offline ? _HomeBlockingState.offline : null;
      });
    }
  }

  Future<void> _loadDeferredHomeContent(
    List<Map<String, dynamic>> homeSections,
    {bool cacheFirst = true, bool refreshCached = true}
  ) async {
    try {
      final results = await Future.wait<dynamic>([
        _safeHomeGet(
          ApiConstants.campaigns,
          queryParams: <String, dynamic>{'type': 'banner,popup'},
          cacheFirst: cacheFirst,
          refreshCached: refreshCached,
        ),
        _safeHomeGet(
          '${ApiConstants.bannersByType}/home',
          cacheFirst: cacheFirst,
          refreshCached: refreshCached,
        ),
        _safeHomeGet(
          ApiConstants.activeOffers,
          cacheFirst: cacheFirst,
          refreshCached: refreshCached,
        ),
      ]);

      final campaigns = _extractList(results[0])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList(growable: false);
      final campaignBanners = campaigns
          .where((item) => item['type']?.toString() == 'banner')
          .toList(growable: false);
      final popupCampaigns = campaigns
          .where((item) => item['type']?.toString() == 'popup')
          .toList(growable: false);
      final banners = _extractList(results[1]);
      final offers = _extractList(results[2])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .where(_isActiveOffer)
          .toList(growable: false);
      final resolvedBanners = _resolveBannerItems(homeSections, banners);

      if (!mounted) return;
      setState(() {
        if (resolvedBanners.isNotEmpty || _banners.isEmpty) {
          _banners = resolvedBanners;
        }
        _popupCampaigns = popupCampaigns;
        _offers = offers;
      });

      _precacheHomeImages(
        banners: resolvedBanners,
        categories: _categories,
        sections: homeSections,
        popupCampaigns: popupCampaigns,
      );
      _trackCampaignImpressions(campaignBanners);
      _showNextPopupCampaign();
    } catch (_) {}
  }

  bool get _hasHomeContent {
    return _banners.isNotEmpty ||
        _categories.isNotEmpty ||
        _homeSections.isNotEmpty ||
        _restaurants.isNotEmpty ||
        _offers.isNotEmpty ||
        _popularDishes.isNotEmpty;
  }

  bool _jsonEquivalent(dynamic left, dynamic right) {
    try {
      return jsonEncode(left) == jsonEncode(right);
    } catch (_) {
      return false;
    }
  }

  bool _sectionHasServerContent(Map<String, dynamic> section) {
    final items = section['items'];
    return items is List && items.isNotEmpty;
  }

  Future<dynamic> _safeHomeGet(
    String endpoint, {
    Map<String, dynamic>? queryParams,
    bool cacheFirst = true,
    bool refreshCached = true,
  }) async {
    try {
      return await _api
          .get(
            endpoint,
            queryParams: queryParams,
            includeAuth: false,
            cacheResponse: true,
            cacheFirst: cacheFirst,
            refreshCached: refreshCached,
            onCacheRefreshed: (_) => _scheduleCachedHomeRebuild(),
          )
          .timeout(const Duration(seconds: 15));
    } catch (_) {
      return <String, dynamic>{'success': false, 'data': <dynamic>[]};
    }
  }

  Future<bool> _isOfflineNow() async {
    final result = await Connectivity().checkConnectivity();
    return result.contains(ConnectivityResult.none);
  }

  Future<void> _refresh() async {
    setState(() {
      _isRefreshing = true;
    });
    await Future.wait([
      _loadHomeData(cacheFirst: false, refreshCached: false),
      _loadNotificationCount(),
    ]);
    if (!mounted) return;
    setState(() {
      _isRefreshing = false;
    });
  }

  Future<void> _loadRestaurantFeedAfterFirstPaint(
    Future<dynamic> restaurantResponse,
  ) async {
    try {
      final response = await restaurantResponse.timeout(
        const Duration(seconds: 18),
      );
      final restaurants = _extractList(response)
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();
      restaurants.sort((a, b) {
        final openCompare =
            _restaurantOpenValue(b).compareTo(_restaurantOpenValue(a));
        if (openCompare != 0) return openCompare;
        return 0;
      });

      if (!mounted) return;
      setState(() {
        _restaurants = restaurants;
        _isLoadingRestaurantFeed = false;
        _blockingState = _shouldShowDeliveryUnavailable(restaurants)
            ? _HomeBlockingState.deliveryUnavailable
            : null;
      });

      _precacheHomeImages(restaurants: restaurants);
      final hasServerPopularDishes = _homeSections.any(
        (section) =>
            section['type'] == 'popular_dishes' &&
            _mapList(section['items']).isNotEmpty,
      );
      if (mounted && !hasServerPopularDishes) {
        setState(() {
          _isLoadingPopularDishes = restaurants.isNotEmpty;
        });
        unawaited(_loadPopularDishes(restaurants));
      }
    } catch (_) {
      if (!mounted) return;
      final offline = await _isOfflineNow();
      if (!mounted) return;
      final fallbackRestaurants = _restaurantsFromHomeSections(_homeSections);
      if (_isLoading) {
        setState(() {
          if (fallbackRestaurants.isNotEmpty) {
            _restaurants = fallbackRestaurants;
          }
          _isLoading = false;
          _isLoadingRestaurantFeed = false;
          _blockingState = offline ? _HomeBlockingState.offline : null;
        });
      } else {
        setState(() {
          if (fallbackRestaurants.isNotEmpty && _restaurants.isEmpty) {
            _restaurants = fallbackRestaurants;
          }
          _isLoadingRestaurantFeed = false;
          _blockingState = offline ? _HomeBlockingState.offline : null;
        });
      }
    }
  }

  int _restaurantOpenValue(Map<String, dynamic> restaurant) {
    final value = restaurant['is_open_now'] ?? restaurant['is_open'];
    if (value is bool) return value ? 1 : 0;
    final text = value?.toString().toLowerCase().trim();
    return text == 'true' || text == '1' ? 1 : 0;
  }

  bool _shouldShowDeliveryUnavailable(List<Map<String, dynamic>> restaurants) {
    return !widget.showDiningMode &&
        _hasDeliveryLocation &&
        restaurants.isEmpty &&
        !_homeSections.any(_sectionHasServerContent) &&
        !_isLoadingRestaurantFeed;
  }

  Future<void> _loadPopularDishes(
      List<Map<String, dynamic>> restaurants) async {
    final dishes = <_HomeDishCardData>[];
    final seen = <String>{};
    final restaurantBatch = restaurants.take(4).toList(growable: false);

    final menuResponses = await Future.wait<List<MenuItem>>(
      restaurantBatch.map((restaurant) async {
        final restaurantId = _restaurantId(restaurant);
        if (restaurantId <= 0) return <MenuItem>[];

        try {
          final response = await _api
              .get(
                '${ApiConstants.restaurantDetails}/$restaurantId/menu',
                includeAuth: false,
                cacheResponse: true,
                cacheFirst: true,
              )
              .timeout(const Duration(seconds: 3));
          final data = response['data'] is Map<String, dynamic>
              ? response['data'] as Map<String, dynamic>
              : <String, dynamic>{};
          return ((data['menu_items'] ?? data['items'] ?? data['menu'])
                      as List? ??
                  const <dynamic>[])
              .whereType<Map>()
              .map((json) => MenuItem.fromJson(Map<String, dynamic>.from(json)))
              .where((item) => item.isAvailable)
              .toList()
            ..sort((a, b) => b.totalOrders.compareTo(a.totalOrders));
        } catch (_) {
          return <MenuItem>[];
        }
      }),
    );

    for (var index = 0;
        index < restaurantBatch.length && dishes.length < 10;
        index++) {
      final restaurant = restaurantBatch[index];
      final restaurantId = _restaurantId(restaurant);
      final items = menuResponses[index];
      for (final item in items.take(3)) {
        final key = '$restaurantId:${item.name.toLowerCase()}';
        if (seen.contains(key)) continue;
        seen.add(key);
        dishes.add(
          _HomeDishCardData(
            name: item.name,
            imageUrl: item.imageUrl,
            price: item.finalPrice,
            restaurantId: restaurantId,
            restaurantName: restaurant['name']?.toString() ?? 'Restaurant',
            isVeg: item.isVeg,
            rating: _ratingOf(restaurant),
            etaMinutes: _parseInt(
              restaurant['delivery_time'] ?? restaurant['deliveryTime'],
              fallback: 0,
            ),
            item: item,
            restaurant: Restaurant.fromJson(restaurant),
          ),
        );
        if (dishes.length >= 10) break;
      }
    }

    if (!mounted) return;
    setState(() {
      _popularDishes = dishes;
      _isLoadingPopularDishes = false;
    });
    _precacheHomeImages(dishes: dishes);
  }

  void _precacheHomeImages({
    List<dynamic> banners = const <dynamic>[],
    List<dynamic> categories = const <dynamic>[],
    List<Map<String, dynamic>> sections = const <Map<String, dynamic>>[],
    List<Map<String, dynamic>> restaurants = const <Map<String, dynamic>>[],
    List<_HomeDishCardData> dishes = const <_HomeDishCardData>[],
    List<Map<String, dynamic>> popupCampaigns = const <Map<String, dynamic>>[],
  }) {
    if (!mounted) return;

    final urls = <String>{};
    void add(String url, {dynamic item}) {
      final trimmed = url.trim();
      if (trimmed.isEmpty) return;
      if (_isLottieUrl(trimmed)) return;
      if (_isLottieMediaItem(item)) return;

      final resolved = _resolveHomeAssetUrl(trimmed);
      final uri = Uri.tryParse(resolved);
      if (resolved.isNotEmpty && uri != null && uri.isAbsolute) {
        urls.add(resolved);
      }
    }

    for (final item in banners.take(4)) {
      add(
        _resolveImageUrl(
          item,
          const ['image_url', 'image', 'banner_image', 'hero_image', 'photo'],
        ),
        item: item,
      );
    }
    for (final item in popupCampaigns.take(3)) {
      add(_campaignHeroImageUrl(item), item: item);
      add(_campaignLogoUrl(item), item: item);
    }
    for (final item in categories.take(8)) {
      add(
        _resolveImageUrl(item, const ['image_url', 'image', 'icon']),
        item: item,
      );
    }
    for (final restaurant in restaurants.take(6)) {
      add(
        _resolveImageUrl(
          restaurant,
          const ['logo_image', 'logo', 'image', 'banner_image'],
        ),
        item: restaurant,
      );
    }
    for (final dish in dishes.take(6)) {
      add(dish.imageUrl, item: dish.item);
    }
    for (final section in sections.take(5)) {
      final items = section['items'];
      if (items is! List) continue;
      for (final item in items.take(4)) {
        add(
          _resolveImageUrl(
            item,
            const [
              'logo_image',
              'logo',
              'image_url',
              'image',
              'banner_image',
              'hero_image',
              'photo',
            ],
          ),
          item: item,
        );
      }
    }

    if (urls.isEmpty) return;
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;
      // Let visible cards claim network/decoder resources before warming a
      // small number of upcoming images in the background.
      await Future<void>.delayed(const Duration(milliseconds: 250));
      if (!mounted) return;
      for (final url in urls.take(6)) {
        _safePrecacheImage(url, context);
      }
    });
  }

  List<dynamic> _extractList(dynamic response) {
    if (response is List) return response;
    if (response is Map<String, dynamic>) {
      final data = response['data'];
      if (data is List) return data;
      if (data is Map<String, dynamic>) {
        if (data['data'] is List) return data['data'] as List<dynamic>;
        if (data['items'] is List) return data['items'] as List<dynamic>;
        if (data['sections'] is List) return data['sections'] as List<dynamic>;
      }
      if (response['items'] is List) return response['items'] as List<dynamic>;
      if (response['sections'] is List) {
        return response['sections'] as List<dynamic>;
      }
      if (response['restaurants'] is List) {
        return response['restaurants'] as List<dynamic>;
      }
      if (response['categories'] is List) {
        return response['categories'] as List<dynamic>;
      }
      if (response['banners'] is List)
        return response['banners'] as List<dynamic>;
      if (response['offers'] is List)
        return response['offers'] as List<dynamic>;
    }
    return const <dynamic>[];
  }

  bool _isActiveOffer(Map<String, dynamic> offer) {
    final code = offer['code'] ?? offer['coupon_code'] ?? offer['title'];
    if (code == null || code.toString().trim().isEmpty) return false;
    final active = offer['is_active'];
    if (active is bool && !active) return false;
    final status = offer['status']?.toString().toLowerCase();
    return status != 'inactive';
  }

  int _campaignId(Map<String, dynamic> campaign) {
    final id = campaign['id'];
    if (id is int) return id;
    if (id is num) return id.toInt();
    return int.tryParse(id?.toString() ?? '') ?? 0;
  }

  void _trackCampaignImpressions(List<Map<String, dynamic>> campaigns) {
    for (final campaign in campaigns) {
      final id = _campaignId(campaign);
      if (id <= 0) continue;
      unawaited(
        _api.post(ApiConstants.campaignTrackImpression(id)).catchError((_) {}),
      );
    }
  }

  void _trackCampaignClick(Map<String, dynamic> campaign) {
    final id = _campaignId(campaign);
    if (id <= 0) return;
    unawaited(
      _api.post(ApiConstants.campaignTrackClick(id)).catchError((_) {}),
    );
  }

  void _showNextPopupCampaign() {
    if (_popupCampaigns.isEmpty) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final campaign = _popupCampaigns.firstWhere(
        (item) {
          final id = _campaignId(item);
          return id > 0 && !_sessionShownPopupCampaignIds.contains(id);
        },
        orElse: () => const <String, dynamic>{},
      );
      if (campaign.isEmpty) return;
      final id = _campaignId(campaign);
      _sessionShownPopupCampaignIds.add(id);
      _trackCampaignImpressions(<Map<String, dynamic>>[campaign]);
      _showCampaignPopup(campaign);
    });
  }

  void _showCampaignPopup(Map<String, dynamic> campaign) {
    final primary = Theme.of(context).colorScheme.primary;
    final accent = Theme.of(context).colorScheme.secondary;
    final title = _campaignTitle(campaign);
    final restaurantName = _campaignRestaurantName(campaign);
    final imageUrl = _campaignHeroImageUrl(campaign);
    final logoUrl = _campaignLogoUrl(campaign);
    final badgeText = _campaignBadgeText(campaign);
    final details = campaign['discount_details'] is Map
        ? Map<String, dynamic>.from(campaign['discount_details'] as Map)
        : <String, dynamic>{};
    final offerLabel = _campaignOfferLabel(details);
    final description = campaign['description']?.toString().trim() ??
        _campaignSubtitle(details);
    final tags = _campaignTags(campaign);
    final metrics = _campaignMetrics(campaign);
    final deliveryTime = _campaignDeliveryTime(campaign);
    final primaryButton = _campaignButtonText(campaign, primary: true);
    final secondaryButton = _campaignButtonText(campaign, primary: false);
    final maxWidth = min(340.0, MediaQuery.of(context).size.width - 32);
    final maxHeight = min(650.0, MediaQuery.of(context).size.height * 0.92);

    showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Campaign Popup',
      barrierColor: Colors.black.withOpacity(0.45),
      transitionDuration: const Duration(milliseconds: 320),
      pageBuilder: (dialogContext, animation, secondaryAnimation) {
        return Center(
          child: SizedBox(
            width: maxWidth,
            child: ConstrainedBox(
              constraints: BoxConstraints(maxHeight: maxHeight),
              child: BackdropFilter(
                filter: ui.ImageFilter.blur(sigmaX: 12, sigmaY: 12),
                child: Material(
                  color: Colors.white,
                  elevation: 24,
                  borderRadius: BorderRadius.circular(28),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(28),
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(28),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x1A000000),
                            blurRadius: 40,
                            offset: Offset(0, 18),
                          ),
                        ],
                      ),
                      child: SingleChildScrollView(
                        physics: const BouncingScrollPhysics(),
                        child: Column(
                          children: <Widget>[
                            Padding(
                              padding: const EdgeInsets.fromLTRB(18, 18, 18, 0),
                              child: Stack(
                                alignment: Alignment.center,
                                children: <Widget>[
                                  Align(
                                    alignment: Alignment.topLeft,
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: <Widget>[
                                        Container(
                                          width: 56,
                                          height: 56,
                                          decoration: BoxDecoration(
                                            color: accent.withOpacity(0.14),
                                            borderRadius:
                                                BorderRadius.circular(20),
                                          ),
                                          child: ClipRRect(
                                            borderRadius:
                                                BorderRadius.circular(20),
                                            child: _buildHomeNetworkImage(
                                              logoUrl,
                                              fit: BoxFit.cover,
                                              width: 56,
                                              height: 56,
                                              placeholder: Container(
                                                color: accent.withOpacity(0.12),
                                                child: Icon(
                                                  Icons.restaurant_menu_rounded,
                                                  color: accent,
                                                  size: 28,
                                                ),
                                              ),
                                              errorWidget: Container(
                                                color: accent.withOpacity(0.12),
                                                child: Icon(
                                                  Icons.restaurant_menu_rounded,
                                                  color: accent,
                                                  size: 28,
                                                ),
                                              ),
                                            ),
                                          ),
                                        ),
                                        if (badgeText.isNotEmpty) ...<Widget>[
                                          const SizedBox(width: 10),
                                          Container(
                                            padding: const EdgeInsets.symmetric(
                                              horizontal: 12,
                                              vertical: 8,
                                            ),
                                            decoration: BoxDecoration(
                                              color: accent.withOpacity(0.14),
                                              borderRadius:
                                                  BorderRadius.circular(999),
                                            ),
                                            child: Text(
                                              badgeText,
                                              style: TextStyle(
                                                color: accent,
                                                fontSize: 12,
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                  Positioned(
                                    right: 0,
                                    child: Material(
                                      color: Colors.white,
                                      shape: const CircleBorder(),
                                      elevation: 3,
                                      child: IconButton(
                                        icon: const Icon(Icons.close_rounded),
                                        onPressed: () =>
                                            Navigator.of(dialogContext).pop(),
                                      ),
                                    ),
                                  ),
                                  Positioned(
                                    left: 24,
                                    top: 68,
                                    child: Container(
                                      width: 12,
                                      height: 12,
                                      decoration: BoxDecoration(
                                        color: accent.withOpacity(0.22),
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                  ),
                                  Positioned(
                                    right: 20,
                                    top: 90,
                                    child: Container(
                                      width: 16,
                                      height: 16,
                                      decoration: BoxDecoration(
                                        color: primary.withOpacity(0.22),
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 16),
                            Padding(
                              padding:
                                  const EdgeInsets.symmetric(horizontal: 18),
                              child: SizedBox(
                                height: (maxHeight * 0.32)
                                    .clamp(160.0, 220.0)
                                    .toDouble(),
                                child: Stack(
                                  children: <Widget>[
                                    Positioned.fill(
                                      child: ClipRRect(
                                        borderRadius: BorderRadius.circular(24),
                                        child: _buildHomeNetworkImage(
                                          imageUrl,
                                          fit: BoxFit.cover,
                                          placeholder: Container(
                                            decoration: BoxDecoration(
                                              color: accent.withOpacity(0.12),
                                              borderRadius:
                                                  BorderRadius.circular(24),
                                            ),
                                            child: Center(
                                              child: Icon(
                                                Icons.local_dining_rounded,
                                                color: accent,
                                                size: 68,
                                              ),
                                            ),
                                          ),
                                          errorWidget: Container(
                                            decoration: BoxDecoration(
                                              color: accent.withOpacity(0.12),
                                              borderRadius:
                                                  BorderRadius.circular(24),
                                            ),
                                            child: Center(
                                              child: Icon(
                                                Icons.local_dining_rounded,
                                                color: accent,
                                                size: 68,
                                              ),
                                            ),
                                          ),
                                        ),
                                      ),
                                    ),
                                    if (offerLabel.isNotEmpty)
                                      Positioned(
                                        right: 16,
                                        top: 16,
                                        child: Container(
                                          padding: const EdgeInsets.symmetric(
                                            horizontal: 14,
                                            vertical: 12,
                                          ),
                                          decoration: BoxDecoration(
                                            borderRadius:
                                                BorderRadius.circular(999),
                                            gradient: LinearGradient(
                                              colors: [
                                                accent,
                                                Color.lerp(accent, Colors.red,
                                                        0.36) ??
                                                    accent,
                                              ],
                                              begin: Alignment.topLeft,
                                              end: Alignment.bottomRight,
                                            ),
                                            boxShadow: [
                                              BoxShadow(
                                                color: accent.withOpacity(0.34),
                                                blurRadius: 24,
                                                offset: const Offset(0, 12),
                                              ),
                                            ],
                                          ),
                                          child: Text(
                                            offerLabel,
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontSize: 12,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                        ),
                                      ),
                                    Positioned(
                                      left: 16,
                                      bottom: 16,
                                      child: Container(
                                        width: 80,
                                        height: 80,
                                        decoration: BoxDecoration(
                                          color: Colors.white.withOpacity(0.18),
                                          borderRadius:
                                              BorderRadius.circular(24),
                                          border: Border.all(
                                            color:
                                                Colors.white.withOpacity(0.36),
                                          ),
                                          boxShadow: [
                                            BoxShadow(
                                              color: Colors.black
                                                  .withOpacity(0.08),
                                              blurRadius: 18,
                                              offset: const Offset(0, 8),
                                            ),
                                          ],
                                        ),
                                        child: const Center(
                                          child: Icon(
                                            Icons.emoji_food_beverage_rounded,
                                            color: Colors.white,
                                            size: 28,
                                          ),
                                        ),
                                      ),
                                    ),
                                    Positioned(
                                      right: 16,
                                      bottom: 12,
                                      child: Container(
                                        width: 52,
                                        height: 52,
                                        decoration: BoxDecoration(
                                          gradient: LinearGradient(
                                            colors: [
                                              Colors.white.withOpacity(0.72),
                                              Colors.white.withOpacity(0.24),
                                            ],
                                            begin: Alignment.topLeft,
                                            end: Alignment.bottomRight,
                                          ),
                                          borderRadius:
                                              BorderRadius.circular(18),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            const SizedBox(height: 20),
                            Padding(
                              padding:
                                  const EdgeInsets.symmetric(horizontal: 20),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  if (title.isNotEmpty)
                                    Text(
                                      title,
                                      style: const TextStyle(
                                        color: _homeText,
                                        fontSize: 24,
                                        fontWeight: FontWeight.w800,
                                        height: 1.08,
                                      ),
                                    ),
                                  if (restaurantName.isNotEmpty) ...<Widget>[
                                    const SizedBox(height: 8),
                                    Text(
                                      restaurantName,
                                      style: TextStyle(
                                        color: _homeMuted,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ],
                                  if (description.isNotEmpty) ...<Widget>[
                                    const SizedBox(height: 10),
                                    Text(
                                      description,
                                      style: const TextStyle(
                                        color: _homeMuted,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w500,
                                        height: 1.5,
                                      ),
                                    ),
                                  ],
                                  if (tags.isNotEmpty) ...<Widget>[
                                    const SizedBox(height: 14),
                                    Wrap(
                                      runSpacing: 8,
                                      spacing: 8,
                                      children: tags
                                          .map(
                                            (tag) => Container(
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                horizontal: 12,
                                                vertical: 8,
                                              ),
                                              decoration: BoxDecoration(
                                                color: _homePeach,
                                                borderRadius:
                                                    BorderRadius.circular(16),
                                              ),
                                              child: Text(
                                                tag,
                                                style: const TextStyle(
                                                  color: _homeText,
                                                  fontSize: 12,
                                                  fontWeight: FontWeight.w700,
                                                ),
                                              ),
                                            ),
                                          )
                                          .toList(),
                                    ),
                                  ],
                                  if (metrics.isNotEmpty) ...<Widget>[
                                    const SizedBox(height: 18),
                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: metrics
                                          .map(
                                            (item) => Expanded(
                                              child: Container(
                                                margin: const EdgeInsets.only(
                                                    right: 8),
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                  vertical: 14,
                                                  horizontal: 12,
                                                ),
                                                decoration: BoxDecoration(
                                                  color: _homeBg,
                                                  borderRadius:
                                                      BorderRadius.circular(18),
                                                ),
                                                child: Column(
                                                  children: <Widget>[
                                                    Icon(
                                                      item['icon'] as IconData,
                                                      size: 18,
                                                      color: primary,
                                                    ),
                                                    const SizedBox(height: 8),
                                                    Text(
                                                      item['label'] as String,
                                                      textAlign:
                                                          TextAlign.center,
                                                      style: const TextStyle(
                                                        color: _homeText,
                                                        fontSize: 13,
                                                        fontWeight:
                                                            FontWeight.w700,
                                                      ),
                                                    ),
                                                    const SizedBox(height: 4),
                                                    Text(
                                                      item['subtitle']
                                                          as String,
                                                      textAlign:
                                                          TextAlign.center,
                                                      style: const TextStyle(
                                                        color: _homeMuted,
                                                        fontSize: 11,
                                                        fontWeight:
                                                            FontWeight.w600,
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                              ),
                                            ),
                                          )
                                          .toList(),
                                    ),
                                  ],
                                  const SizedBox(height: 18),
                                  Row(
                                    children: <Widget>[
                                      _buildBenefitCard(
                                        icon: Icons.local_shipping_rounded,
                                        title: _campaignFreeDelivery(campaign)
                                            ? 'Free Delivery'
                                            : 'Delivery',
                                        subtitle:
                                            _campaignFreeDelivery(campaign)
                                                ? 'Zero delivery fee'
                                                : 'Reliable service',
                                        color: accent,
                                      ),
                                      const SizedBox(width: 10),
                                      _buildBenefitCard(
                                        icon: Icons.local_offer_rounded,
                                        title: offerLabel.isNotEmpty
                                            ? 'Exclusive Offer'
                                            : 'Special Promo',
                                        subtitle: offerLabel.isNotEmpty
                                            ? offerLabel
                                            : 'Tap to explore',
                                        color: primary,
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 10),
                                  Row(
                                    children: <Widget>[
                                      _buildBenefitCard(
                                        icon: Icons.flash_on_rounded,
                                        title: deliveryTime.isNotEmpty
                                            ? 'Fast Delivery'
                                            : 'Quick Service',
                                        subtitle: deliveryTime.isNotEmpty
                                            ? deliveryTime
                                            : 'Ready soon',
                                        color: Color.lerp(
                                                primary, Colors.orange, 0.5) ??
                                            primary,
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 22),
                                  Column(
                                    children: <Widget>[
                                      SizedBox(
                                        width: double.infinity,
                                        height: 56,
                                        child: ElevatedButton(
                                          onPressed: () {
                                            Navigator.of(dialogContext).pop();
                                            _trackCampaignClick(campaign);
                                            final link =
                                                campaign['link']?.toString() ??
                                                    campaign['link_url']
                                                        ?.toString() ??
                                                    '';
                                            if (link
                                                .contains('/restaurants/')) {
                                              final match =
                                                  RegExp(r'/restaurants/(\d+)')
                                                      .firstMatch(link);
                                              final restaurantId = int.tryParse(
                                                  match?.group(1) ?? '');
                                              if (restaurantId != null) {
                                                _openRestaurant(<String,
                                                    dynamic>{
                                                  'id': restaurantId
                                                });
                                                return;
                                              }
                                            }
                                            Navigator.pushNamed(
                                                context, '/search');
                                          },
                                          style: ElevatedButton.styleFrom(
                                            backgroundColor: primary,
                                            shape: RoundedRectangleBorder(
                                              borderRadius:
                                                  BorderRadius.circular(16),
                                            ),
                                          ),
                                          child: Text(
                                            primaryButton,
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontSize: 16,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                        ),
                                      ),
                                      const SizedBox(height: 12),
                                      SizedBox(
                                        width: double.infinity,
                                        height: 56,
                                        child: OutlinedButton(
                                          onPressed: () =>
                                              Navigator.of(dialogContext).pop(),
                                          style: OutlinedButton.styleFrom(
                                            foregroundColor: _homeText,
                                            side: BorderSide(
                                              color: _homeBorder,
                                            ),
                                            shape: RoundedRectangleBorder(
                                              borderRadius:
                                                  BorderRadius.circular(16),
                                            ),
                                          ),
                                          child: Text(
                                            secondaryButton,
                                            style: const TextStyle(
                                              fontSize: 16,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 18),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
      transitionBuilder: (dialogContext, animation, secondaryAnimation, child) {
        return FadeTransition(
          opacity: CurvedAnimation(
            parent: animation,
            curve: Curves.easeOut,
          ),
          child: ScaleTransition(
            scale: Tween<double>(begin: 0.92, end: 1.0).animate(
              CurvedAnimation(parent: animation, curve: Curves.easeOutBack),
            ),
            child: child,
          ),
        );
      },
    );
  }

  String _campaignTitle(Map<String, dynamic> campaign) {
    final title = campaign['title']?.toString().trim();
    if (title?.isNotEmpty == true) return title!;
    final name = campaign['name']?.toString().trim();
    if (name?.isNotEmpty == true) return name!;
    return campaign['headline']?.toString().trim() ?? '';
  }

  String _campaignRestaurantName(Map<String, dynamic> campaign) {
    return campaign['restaurant_name']?.toString().trim() ??
        campaign['brand_name']?.toString().trim() ??
        campaign['merchant_name']?.toString().trim() ??
        '';
  }

  String _campaignHeroImageUrl(Map<String, dynamic> campaign) {
    return _resolveImageUrl(
      campaign,
      const ['image_url', 'image', 'banner_image', 'hero_image', 'photo'],
    );
  }

  String _campaignLogoUrl(Map<String, dynamic> campaign) {
    return _resolveImageUrl(
      campaign,
      const ['logo', 'restaurant_logo', 'brand_logo', 'store_logo'],
    );
  }

  String _campaignBadgeText(Map<String, dynamic> campaign) {
    final badge = campaign['badge_label']?.toString().trim() ??
        campaign['badge']?.toString().trim() ??
        campaign['campaign_type']?.toString().trim() ??
        campaign['tag']?.toString().trim() ??
        '';
    return badge.toUpperCase();
  }

  List<String> _campaignTags(Map<String, dynamic> campaign) {
    final tags = <String>{};

    void addTag(dynamic raw) {
      if (raw == null) return;
      if (raw is String) {
        for (final part in raw.split(',')) {
          final trimmed = part.trim();
          if (trimmed.isNotEmpty) tags.add(trimmed);
        }
      } else if (raw is List) {
        for (final entry in raw) {
          final trimmed = entry?.toString().trim();
          if (trimmed?.isNotEmpty == true) tags.add(trimmed!);
        }
      }
    }

    addTag(campaign['tags']);
    addTag(campaign['cuisines']);
    addTag(campaign['categories']);
    addTag(campaign['category_tags']);

    if (tags.isEmpty) {
      addTag(campaign['style']);
    }

    return tags.take(3).toList();
  }

  List<Map<String, dynamic>> _campaignMetrics(Map<String, dynamic> campaign) {
    final metrics = <Map<String, dynamic>>[];
    final rating = campaign['rating']?.toString().trim();
    if (rating?.isNotEmpty == true) {
      metrics.add(<String, dynamic>{
        'icon': Icons.star_rounded,
        'label': rating!,
        'subtitle': 'Rating',
      });
    }

    final deliveryTime = _campaignDeliveryTime(campaign);
    if (deliveryTime.isNotEmpty) {
      metrics.add(<String, dynamic>{
        'icon': Icons.timer_rounded,
        'label': deliveryTime,
        'subtitle': 'Delivery',
      });
    }

    if (_campaignFreeDelivery(campaign)) {
      metrics.add(<String, dynamic>{
        'icon': Icons.local_shipping_rounded,
        'label': 'Free',
        'subtitle': 'Delivery',
      });
    }

    return metrics;
  }

  bool _campaignFreeDelivery(Map<String, dynamic> campaign) {
    final freeDelivery = campaign['free_delivery'];
    if (freeDelivery is bool) return freeDelivery;
    final fee = campaign['delivery_fee']?.toString().trim();
    if (fee?.isNotEmpty == true) {
      return fee == '0' || fee == '0.0';
    }
    return false;
  }

  String _campaignDeliveryTime(Map<String, dynamic> campaign) {
    return campaign['delivery_time']?.toString().trim() ??
        campaign['delivery_estimate']?.toString().trim() ??
        campaign['eta']?.toString().trim() ??
        '';
  }

  String _campaignButtonText(Map<String, dynamic> campaign,
      {required bool primary}) {
    if (primary) {
      return campaign['primary_cta']?.toString().trim() ??
          campaign['action_text']?.toString().trim() ??
          'Order Now';
    }
    return campaign['secondary_cta']?.toString().trim() ??
        campaign['dismiss_text']?.toString().trim() ??
        'Maybe Later';
  }

  Widget _buildBenefitCard({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
  }) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: _homeBg,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: color.withOpacity(0.2),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, size: 18, color: color),
            ),
            const SizedBox(height: 12),
            Text(
              title,
              style: TextStyle(
                color: _homeText,
                fontSize: 13,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              subtitle,
              style: const TextStyle(
                color: _homeMuted,
                fontSize: 11,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _campaignSubtitle(Map<String, dynamic> details) {
    final type = details['type']?.toString();
    final value = details['value']?.toString();
    final minOrder = details['min_order']?.toString();
    if (type == null || type.isEmpty || value == null || value.isEmpty) {
      return 'A new offer is live. Open it before it ends.';
    }
    final label = type == 'percentage' ? '$value% off' : 'Flat $value off';
    return minOrder != null && minOrder.isNotEmpty
        ? '$label on orders above $minOrder.'
        : '$label for a limited time.';
  }

  String _campaignOfferLabel(Map<String, dynamic> details) {
    final type = details['type']?.toString();
    final value = details['value']?.toString();
    if (type == null || type.isEmpty || value == null || value.isEmpty) {
      return '';
    }
    if (type == 'percentage') {
      return 'UP TO $value% OFF';
    }
    return 'UP TO $value OFF';
  }

  List<dynamic> _resolveBannerItems(
    List<Map<String, dynamic>> sections,
    List<dynamic> fallback,
  ) {
    for (final section in sections) {
      if (section['type']?.toString() == 'hero_banner' &&
          section['items'] is List &&
          (section['items'] as List).isNotEmpty) {
        return List<dynamic>.from(section['items'] as List);
      }
    }
    return fallback;
  }

  List<dynamic> _resolveCategoryItems(
    List<Map<String, dynamic>> sections,
    List<dynamic> fallback,
  ) {
    final merged = <dynamic>[];
    final seen = <String>{};
    void addItem(dynamic item) {
      final key = item is Map
          ? '${item['id'] ?? ''}|${item['slug'] ?? ''}|${item['name'] ?? item['title'] ?? ''}'
          : item.toString();
      if (key.trim().isEmpty || !seen.add(key)) return;
      merged.add(item);
    }

    for (final item in fallback) {
      addItem(item);
    }

    for (final section in sections) {
      if ((section['type']?.toString() == 'cuisine_grid' ||
              section['type']?.toString() == 'categories') &&
          section['items'] is List &&
          (section['items'] as List).isNotEmpty) {
        for (final item in section['items'] as List) {
          addItem(item);
        }
      }
    }
    return merged.isNotEmpty ? merged : fallback;
  }

  List<Map<String, dynamic>> _restaurantsFromHomeSections(
    List<Map<String, dynamic>> sections,
  ) {
    final restaurants = <Map<String, dynamic>>[];
    final seen = <int>{};

    for (final section in sections) {
      final type = section['type']?.toString() ?? '';
      if (!_sectionUsesRestaurantFeed(type) && type != 'recommended_for_you') {
        continue;
      }

      final items = section['items'];
      if (items is! List) continue;

      for (final item in items) {
        if (item is! Map) continue;
        final restaurant = Map<String, dynamic>.from(item);
        if (!_looksLikeRestaurantSectionItem(restaurant)) continue;
        final id = _restaurantId(restaurant);
        if (id > 0 && !seen.add(id)) continue;
        restaurants.add(restaurant);
      }
    }

    return _scopeRestaurants(restaurants);
  }

  List<Map<String, dynamic>> _renderableSections() {
    if (_homeSections.isEmpty) return _fallbackSections();
    final sections = <Map<String, dynamic>>[];
    final primaryHeroToken = _primaryHeroToken();
    final primaryBannerToken = _primaryBannerToken();
    final primaryCategoryToken = _primaryCategoryToken();
    for (final section in _homeSections) {
      final type = section['type']?.toString() ?? '';
      final token = section['token']?.toString();
      if ((type == 'hero_banner' && token == primaryHeroToken) ||
          (type == 'banner_carousel' && token == primaryBannerToken) ||
          ((type == 'cuisine_grid' || type == 'categories') &&
              token == primaryCategoryToken)) {
        continue;
      }
      final items = _resolveSectionItems(section);
      if (items.isEmpty) {
        if (_isLoadingRestaurantFeed && _sectionUsesRestaurantFeed(type)) {
          sections.add(<String, dynamic>{
            ...section,
            'resolved_items': items,
          });
        }
        continue;
      }
      sections.add(<String, dynamic>{
        ...section,
        'resolved_items': items,
      });
    }
    return sections.isEmpty ? _fallbackSections() : sections;
  }

  String? _primaryBannerToken() {
    for (final section in _homeSections) {
      if (section['type']?.toString() == 'banner_carousel' &&
          section['items'] is List &&
          (section['items'] as List).isNotEmpty) {
        return section['token']?.toString();
      }
    }
    return null;
  }

  String? _primaryHeroToken() {
    for (final section in _homeSections) {
      if (section['type']?.toString() == 'hero_banner' &&
          section['items'] is List &&
          (section['items'] as List).isNotEmpty) {
        return section['token']?.toString();
      }
    }
    return null;
  }

  List<dynamic> _heroBannerItems() {
    if (_banners.isNotEmpty) return _banners;
    for (final section in _homeSections) {
      if (section['type']?.toString() == 'hero_banner' &&
          section['items'] is List &&
          (section['items'] as List).isNotEmpty) {
        return List<dynamic>.from(section['items'] as List);
      }
    }
    return const <dynamic>[];
  }

  String? _primaryCategoryToken() {
    for (final section in _homeSections) {
      final type = section['type']?.toString();
      if ((type == 'cuisine_grid' || type == 'categories') &&
          section['items'] is List &&
          (section['items'] as List).isNotEmpty) {
        return section['token']?.toString();
      }
    }
    return null;
  }

  List<Map<String, dynamic>> _fallbackSections() {
    final restaurants = _scopeRestaurants(_restaurants);
    final dishes = _scopeDishes(_popularDishes);
    return <Map<String, dynamic>>[
      if (_isLoadingRestaurantFeed)
        <String, dynamic>{
          'type': 'nearby_restaurants',
          'title': 'Popular Near You',
          'subtitle': 'Finding restaurants around your location',
          'resolved_items': const <dynamic>[],
        },
      if (dishes.isNotEmpty)
        <String, dynamic>{
          'type': 'recommended_for_you',
          'title': 'Recommended For You',
          'subtitle': 'Menu items loved around you',
          'resolved_items': dishes,
        },
      if (_offers.isNotEmpty)
        <String, dynamic>{
          'type': 'admin_offers',
          'title': 'Offers For You',
          'subtitle': 'Only admin-managed live offers',
          'resolved_items': _offers.take(10).toList(),
        },
      if (restaurants.isNotEmpty)
        <String, dynamic>{
          'type': 'nearby_restaurants',
          'title': 'Popular Near You',
          'subtitle': 'Discover restaurants in your area',
          'resolved_items': restaurants,
        },
      if (dishes.isNotEmpty)
        <String, dynamic>{
          'type': 'popular_dishes',
          'title': 'Popular Dishes',
          'subtitle': 'Top ordered items from nearby restaurants',
          'resolved_items': dishes,
        },
    ];
  }

  List<dynamic> _resolveSectionItems(Map<String, dynamic> section) {
    final type = section['type']?.toString() ?? '';
    final usesClientFeed = section['client_feed'] == true;
    final hasServerItems = section['items'] is List;
    final hasNonEmptyServerItems =
        hasServerItems && (section['items'] as List).isNotEmpty;
    final strictServerItems = section['strict_items'] == true ||
        (hasNonEmptyServerItems &&
            !usesClientFeed &&
            type != 'banner_carousel' &&
            type != 'categories' &&
            type != 'cuisine_grid');
    switch (type) {
      case 'restaurant_discovery':
        return _scopeRestaurants(_restaurants);
      case 'nearby_restaurants':
        final serverItems = _mapList(section['items']);
        return serverItems.isNotEmpty
            ? _scopeSectionItemsForVegMode(type, serverItems)
            : _scopeRestaurants(_restaurants);
      case 'popular_restaurants':
        final serverItems =
            _restaurantItemsFromSection(_mapList(section['items']));
        if (strictServerItems)
          return _scopeSectionItemsForVegMode(type, serverItems);
        return serverItems.isNotEmpty
            ? _scopeSectionItemsForVegMode(type, serverItems)
            : _bestRatedRestaurants(_scopeRestaurants(_restaurants));
      case 'new_arrivals':
        final serverItems = _mapList(section['items']);
        if (strictServerItems)
          return _scopeSectionItemsForVegMode(type, serverItems);
        return serverItems.isNotEmpty
            ? _scopeSectionItemsForVegMode(type, serverItems)
            : _newArrivalRestaurants(_scopeRestaurants(_restaurants));
      case 'trending_near_you':
        final serverItems =
            _restaurantItemsFromSection(_mapList(section['items']));
        if (strictServerItems)
          return _scopeSectionItemsForVegMode(type, serverItems);
        return serverItems.isNotEmpty
            ? _scopeSectionItemsForVegMode(type, serverItems)
            : _trendingRestaurants(_scopeRestaurants(_restaurants));
      case 'featured_restaurants':
      case 'restaurant_grid':
        final serverItems =
            _restaurantItemsFromSection(_mapList(section['items']));
        if (strictServerItems)
          return _scopeSectionItemsForVegMode(type, serverItems);
        return serverItems.isNotEmpty
            ? _scopeSectionItemsForVegMode(type, serverItems)
            : _featuredRestaurants(_scopeRestaurants(_restaurants));
      case 'recommended_for_you':
        final serverItems = _mapList(section['items']);
        final restaurantItems = _dedupeRestaurants(serverItems
            .where(_looksLikeRestaurantSectionItem)
            .toList(growable: false));
        if (restaurantItems.isNotEmpty) {
          return _scopeSectionItemsForVegMode(
            type,
            restaurantItems,
          );
        }
        final dishItems = _recommendedItemsFromSection(serverItems);
        final fallbackDishes = _scopeDishes(_popularDishes)
            .where((dish) => dish.effectiveRestaurantId > 0)
            .toList(growable: false);
        if (strictServerItems)
          return _scopeSectionItemsForVegMode(type, dishItems);
        if (dishItems.isNotEmpty) {
          return _scopeSectionItemsForVegMode(type, dishItems);
        }
        final fallbackRestaurants = _scopeRestaurants(_restaurants);
        return fallbackRestaurants.isNotEmpty
            ? fallbackRestaurants
            : fallbackDishes;
      case 'popular_dishes':
        final serverItems = _mapList(section['items'])
            .map(_dishDataFromSectionItem)
            .whereType<_HomeDishCardData>()
            .toList(growable: false);
        if (strictServerItems)
          return _scopeSectionItemsForVegMode(type, serverItems);
        return serverItems.isNotEmpty
            ? _scopeSectionItemsForVegMode(type, serverItems)
            : _scopeDishes(_popularDishes);
      case 'admin_offers':
        final serverItems = _mapList(section['items']);
        if (strictServerItems) return serverItems;
        return serverItems.isNotEmpty ? serverItems : _offers;
      case 'shop_by_brand':
        final serverItems = _mapList(section['items']);
        if (strictServerItems)
          return _scopeSectionItemsForVegMode(type, serverItems);
        return serverItems.isNotEmpty
            ? _scopeSectionItemsForVegMode(type, serverItems)
            : _brandItemsFromRestaurants(
                _featuredRestaurants(_scopeRestaurants(_restaurants)),
              );
      default:
        return _scopeSectionItemsForVegMode(type, _mapList(section['items']));
    }
  }

  bool _looksLikeRestaurantSectionItem(Map<String, dynamic> item) {
    if (item.containsKey('restaurant_id') ||
        item.containsKey('master_menu_item_id') ||
        item.containsKey('price') ||
        item.containsKey('discounted_price')) {
      return false;
    }

    return item.containsKey('delivery_time') ||
        item.containsKey('delivery_fee') ||
        item.containsKey('min_order_amount') ||
        item.containsKey('is_open') ||
        item.containsKey('is_open_now') ||
        item.containsKey('restaurant_type') ||
        item.containsKey('cuisine_text') ||
        item.containsKey('logo_image') ||
        item.containsKey('banner_image');
  }

  List<Map<String, dynamic>> _restaurantItemsFromSection(
    List<Map<String, dynamic>> items,
  ) {
    return _dedupeRestaurants(
      items
          .where((item) =>
              _looksLikeRestaurantSectionItem(item) ||
              _sectionItemLooksLikeRestaurant(item))
          .toList(growable: false),
    );
  }

  List<Map<String, dynamic>> _dedupeRestaurants(
    List<Map<String, dynamic>> restaurants,
  ) {
    final seen = <int>{};
    final deduped = <Map<String, dynamic>>[];
    for (final restaurant in restaurants) {
      final id = _restaurantId(restaurant);
      if (id > 0 && !seen.add(id)) continue;
      deduped.add(restaurant);
    }
    return deduped;
  }

  List<Map<String, dynamic>> _mapList(dynamic items) {
    if (items is! List) return const <Map<String, dynamic>>[];
    return items
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
  }

  _HomeDishCardData? _dishDataFromSectionItem(Map<String, dynamic> item) {
    final name = (item['name'] ?? item['title'] ?? '').toString().trim();
    if (name.isEmpty) return null;
    final nestedRestaurant = item['restaurant'];
    final restaurantMap = nestedRestaurant is Map
        ? Map<String, dynamic>.from(nestedRestaurant)
        : const <String, dynamic>{};
    final restaurantId = _parseInt(
      item['restaurant_id'] ??
          item['restaurantId'] ??
          restaurantMap['id'] ??
          restaurantMap['restaurant_id'],
    );
    final price = _minimumMenuItemPrice(item);

    return _HomeDishCardData(
      name: name,
      imageUrl: _resolveImageUrl(
        item,
        const [
          'image_url',
          'image',
          'photo',
          'banner_image',
          'image_path',
          'thumbnail',
        ],
      ),
      price: price,
      restaurantId: restaurantId,
      restaurantName: (item['restaurant_name'] ??
              item['restaurantName'] ??
              restaurantMap['name'] ??
              item['category_name'] ??
              'Global Menu')
          .toString(),
      isVeg: _parseBool(item['is_veg'] ?? item['is_available'], fallback: true),
      rating: _parseDouble(
        item['rating'] ??
            item['avg_rating'] ??
            item['average_rating'] ??
            item['review_rating'] ??
            restaurantMap['rating'] ??
            restaurantMap['avg_rating'],
        fallback: 0,
      ),
      etaMinutes: _parseInt(
        item['preparation_time'] ??
            item['preparationTime'] ??
            item['prep_time'] ??
            item['eta'] ??
            item['delivery_time'] ??
            restaurantMap['delivery_time'] ??
            restaurantMap['deliveryTime'],
        fallback: 0,
      ),
      item: _menuItemFromDishSectionItem(item),
      restaurant: _restaurantFromDishSectionItem(item),
    );
  }

  List<_HomeDishCardData> _recommendedItemsFromSection(
    List<Map<String, dynamic>> items,
  ) {
    return items
        .map((item) => _sectionItemLooksLikeRestaurant(item)
            ? _restaurantDataFromSectionItem(item)
            : _dishDataFromSectionItem(item))
        .whereType<_HomeDishCardData>()
        .where((dish) => dish.effectiveRestaurantId > 0)
        .toList(growable: false);
  }

  bool _sectionItemLooksLikeRestaurant(Map<String, dynamic> item) {
    final hasMenuIdentity =
        item.containsKey('menu_item_id') || item.containsKey('menuItemId');
    if (hasMenuIdentity) return false;
    if (item.containsKey('restaurant_id') || item.containsKey('restaurantId')) {
      final hasDishSignals = item.containsKey('is_veg') ||
          item.containsKey('veg') ||
          item.containsKey('preparation_time') ||
          item.containsKey('preparationTime') ||
          item.containsKey('category_id') ||
          item.containsKey('categoryId') ||
          item.containsKey('final_price') ||
          item.containsKey('discounted_price') ||
          item.containsKey('base_price') ||
          item.containsKey('variants');
      final hasRestaurantSignals = item.containsKey('is_open') ||
          item.containsKey('is_open_now') ||
          item.containsKey('delivery_time') ||
          item.containsKey('deliveryTime') ||
          item.containsKey('cuisines') ||
          item.containsKey('cuisine') ||
          item.containsKey('address') ||
          item.containsKey('logo_image') ||
          item.containsKey('banner_image');
      if (hasDishSignals && !hasRestaurantSignals) return false;
      return true;
    }
    return _restaurantId(item) > 0;
  }

  _HomeDishCardData? _restaurantDataFromSectionItem(
    Map<String, dynamic> item,
  ) {
    final restaurantId = _sectionRestaurantId(item);
    if (restaurantId <= 0) return null;
    final name = (item['name'] ??
            item['restaurant_name'] ??
            item['restaurantName'] ??
            'Restaurant')
        .toString()
        .trim();
    if (name.isEmpty) return null;
    final price = _restaurantMinimumMenuItemPrice(item, restaurantId);

    try {
      return _HomeDishCardData(
        name: name,
        imageUrl: _restaurantBannerImageUrl(item, restaurantId),
        price: price,
        restaurantId: restaurantId,
        restaurantName: name,
        isVeg: _restaurantIsVegFriendly(item),
        rating: _ratingOf(item),
        etaMinutes: _parseInt(
          item['delivery_time'] ?? item['deliveryTime'] ?? item['eta'],
          fallback: 0,
        ),
        restaurant: Restaurant.fromJson(item),
      );
    } catch (_) {
      return _HomeDishCardData(
        name: name,
        imageUrl: _restaurantBannerImageUrl(item, restaurantId),
        price: price,
        restaurantId: restaurantId,
        restaurantName: name,
        isVeg: _restaurantIsVegFriendly(item),
        rating: _ratingOf(item),
        etaMinutes: _parseInt(
          item['delivery_time'] ?? item['deliveryTime'] ?? item['eta'],
          fallback: 0,
        ),
      );
    }
  }

  String _restaurantBannerImageUrl(
    Map<String, dynamic> item,
    int restaurantId,
  ) {
    const bannerKeys = <String>[
      'banner_image',
      'bannerImage',
      'cover_image',
      'coverImage',
      'cover_photo',
      'coverPhoto',
      'hero_image',
      'heroImage',
      'background_image',
      'backgroundImage',
    ];
    final sectionBanner = _resolveImageUrl(item, bannerKeys);
    if (sectionBanner.isNotEmpty) return sectionBanner;

    for (final restaurant in _restaurants.whereType<Map>()) {
      final restaurantMap = Map<String, dynamic>.from(restaurant);
      if (_restaurantId(restaurantMap) != restaurantId) continue;
      final feedBanner = _resolveImageUrl(restaurantMap, bannerKeys);
      if (feedBanner.isNotEmpty) return feedBanner;
      break;
    }

    return _resolveImageUrl(
      item,
      const ['image_url', 'image', 'photo', 'logo_image', 'logo'],
    );
  }

  int _sectionRestaurantId(Map<String, dynamic> item) {
    return _parseInt(
      item['restaurant_id'] ?? item['restaurantId'] ?? item['id'],
    );
  }

  double _restaurantMinimumMenuPrice(
    Map<String, dynamic> item,
    int restaurantId,
  ) {
    final directPrice = _restaurantAmountField(item);
    if (directPrice > 0) return directPrice;

    final nestedPrice = _homeMinimumNestedMenuPrice(item);
    if (nestedPrice > 0) return nestedPrice;

    for (final restaurant in _restaurants) {
      if (_restaurantId(restaurant) != restaurantId) continue;
      final feedPrice = _restaurantAmountField(restaurant);
      if (feedPrice > 0) return feedPrice;
      final feedNestedPrice = _homeMinimumNestedMenuPrice(restaurant);
      if (feedNestedPrice > 0) return feedNestedPrice;
      break;
    }

    final dishPrices = _popularDishes
        .where((dish) => dish.effectiveRestaurantId == restaurantId)
        .map((dish) => dish.price)
        .where((price) => price > 0)
        .toList(growable: false);
    if (dishPrices.isEmpty) return 0;
    dishPrices.sort();
    return dishPrices.first;
  }

  double _restaurantMinimumMenuItemPrice(
    Map<String, dynamic> item,
    int restaurantId,
  ) {
    final nestedPrice = _homeMinimumNestedMenuPrice(item);
    if (nestedPrice > 0) return nestedPrice;

    for (final restaurant in _restaurants) {
      if (_restaurantId(restaurant) != restaurantId) continue;
      final feedNestedPrice = _homeMinimumNestedMenuPrice(restaurant);
      if (feedNestedPrice > 0) return feedNestedPrice;
      break;
    }

    final dishPrices = _popularDishes
        .where((dish) => dish.effectiveRestaurantId == restaurantId)
        .map((dish) => dish.price)
        .where((price) => price > 0)
        .toList(growable: false);
    if (dishPrices.isEmpty) return 0;
    dishPrices.sort();
    return dishPrices.first;
  }

  double _restaurantAmountField(Map<String, dynamic> item) {
    for (final value in <dynamic>[
      item['minimum_menu_price'],
      item['minimumMenuPrice'],
      item['min_menu_price'],
      item['minMenuPrice'],
      item['lowest_menu_price'],
      item['lowestMenuPrice'],
      item['starting_price'],
      item['startingPrice'],
      item['price_from'],
      item['priceFrom'],
      item['amount_for_one'],
      item['amountForOne'],
      item['min_price'],
      item['minPrice'],
      item['minimum_price'],
      item['minimumPrice'],
      item['minimum_order'],
      item['minimumOrder'],
      item['minimum_order_amount'],
      item['minimumOrderAmount'],
      item['min_order_amount'],
      item['minOrderAmount'],
      item['price_for_one'],
      item['priceForOne'],
    ]) {
      final parsed = _parsePriceValue(value);
      if (parsed > 0) return parsed;
    }

    final priceForTwo = _parsePriceValue(
      item['price_for_two'] ?? item['priceForTwo'],
    );
    return priceForTwo > 0 ? priceForTwo / 2 : 0;
  }

  Map<String, dynamic> _restaurantWithLoadedMenuItems(
    Map<String, dynamic> restaurant,
  ) {
    final hasMenuItems = const <String>[
      'menu_items',
      'items',
      'popular_items',
      'featured_items',
      'recommended_items',
      'matched_menu_items',
      'matchedMenuItems',
    ].any(
      (key) => restaurant[key] is List && (restaurant[key] as List).isNotEmpty,
    );
    if (hasMenuItems) return restaurant;

    final restaurantId = _restaurantId(restaurant);
    if (restaurantId <= 0 || _popularDishes.isEmpty) return restaurant;

    final menuItems = _popularDishes
        .where(
          (dish) =>
              dish.effectiveRestaurantId == restaurantId &&
              dish.imageUrl.trim().isNotEmpty,
        )
        .take(6)
        .map(
          (dish) => <String, dynamic>{
            'name': dish.name,
            'image_url': dish.imageUrl,
            'price': dish.price,
            'is_veg': dish.isVeg,
          },
        )
        .toList(growable: false);
    if (menuItems.isEmpty) return restaurant;
    return <String, dynamic>{
      ...restaurant,
      'popular_items': menuItems,
    };
  }

  MenuItem? _menuItemFromDishSectionItem(Map<String, dynamic> item) {
    final itemId = _parseInt(item['menu_item_id'] ?? item['id']);
    final nestedRestaurant = item['restaurant'];
    final restaurantMap = nestedRestaurant is Map
        ? Map<String, dynamic>.from(nestedRestaurant)
        : const <String, dynamic>{};
    final restaurantId = _parseInt(
      item['restaurant_id'] ??
          item['restaurantId'] ??
          restaurantMap['id'] ??
          restaurantMap['restaurant_id'],
    );
    if (itemId <= 0 || restaurantId <= 0) return null;
    final price = _menuItemPrice(item);
    final discountedPrice = _parseDoubleOrNull(
      item['discounted_price'] ??
          item['discountedPrice'] ??
          item['sale_price'] ??
          item['offer_price'],
    );

    try {
      return MenuItem.fromJson(<String, dynamic>{
        ...item,
        'id': itemId,
        'restaurant_id': restaurantId,
        'name': (item['name'] ?? item['title'] ?? '').toString(),
        'price': price,
        if (discountedPrice != null && discountedPrice > 0)
          'discounted_price': discountedPrice,
        'is_available': item['is_available'] ?? true,
        'images': item['images'] ??
            [
              _resolveImageUrl(
                item,
                const [
                  'image_url',
                  'image',
                  'photo',
                  'banner_image',
                  'image_path',
                  'thumbnail',
                ],
              ),
            ],
      });
    } catch (_) {
      return null;
    }
  }

  Restaurant? _restaurantFromDishSectionItem(Map<String, dynamic> item) {
    final nested = item['restaurant'];
    final restaurantMap =
        nested is Map ? Map<String, dynamic>.from(nested) : null;
    final restaurantId = _parseInt(
      item['restaurant_id'] ??
          item['restaurantId'] ??
          restaurantMap?['id'] ??
          restaurantMap?['restaurant_id'],
    );
    if (restaurantId <= 0) return null;

    final data = restaurantMap != null
        ? restaurantMap
        : <String, dynamic>{
            'id': restaurantId,
            'name': item['restaurant_name'] ?? 'Restaurant',
          };

    try {
      return Restaurant.fromJson(<String, dynamic>{
        ...data,
        'id': restaurantId,
      });
    } catch (_) {
      return null;
    }
  }

  double _menuItemPrice(Map<String, dynamic> item) {
    for (final value in <dynamic>[
      item['final_price'],
      item['finalPrice'],
      item['discounted_price'],
      item['discountedPrice'],
      item['sale_price'],
      item['offer_price'],
      item['price'],
      item['base_price'],
      item['regular_price'],
      item['item_price'],
      item['menu_price'],
      item['unit_price'],
      item['mrp'],
      item['selling_price'],
      item['amount'],
    ]) {
      final parsed = _parsePriceValue(value);
      if (parsed > 0) return parsed;
    }

    for (final key in const [
      'variant',
      'default_variant',
      'selected_variant'
    ]) {
      final value = item[key];
      if (value is Map) {
        final parsed = _parsePriceValue(value['price'] ?? value['final_price']);
        if (parsed > 0) return parsed;
      }
    }

    final variants = item['variants'];
    if (variants is List) {
      final prices = variants
          .whereType<Map>()
          .map((variant) => _parsePriceValue(
                variant['price'] ?? variant['final_price'] ?? variant['amount'],
              ))
          .where((value) => value > 0)
          .toList();
      if (prices.isNotEmpty) {
        prices.sort();
        return prices.first;
      }
    }

    return 0;
  }

  double _minimumMenuItemPrice(dynamic value) {
    final prices = <double>[];

    void collectPrice(dynamic candidate) {
      if (candidate == null) return;
      if (candidate is Map) {
        final map = Map<String, dynamic>.from(candidate);
        final directPrice = _menuItemPrice(map);
        if (directPrice > 0) prices.add(directPrice);

        for (final key in const [
          'items',
          'menu_items',
          'matched_menu_items',
          'matchedMenuItems',
          'popular_items',
          'popular_dishes',
          'recommended_items',
          'products',
        ]) {
          collectPrice(map[key]);
        }
        return;
      }
      if (candidate is List) {
        for (final item in candidate) {
          collectPrice(item);
        }
      }
    }

    collectPrice(value);
    if (prices.isEmpty) return 0;
    prices.sort();
    return prices.first;
  }

  double _parsePriceValue(dynamic value) {
    if (value == null) return 0;
    if (value is num) return value.toDouble();
    if (value is String) {
      final cleaned = value.replaceAll(RegExp(r'[^0-9.\-]'), '');
      if (cleaned.isEmpty || cleaned == '-' || cleaned == '.') return 0;
      return double.tryParse(cleaned) ?? 0;
    }
    if (value is Map) {
      for (final key in const ['amount', 'value', 'price', 'final_price']) {
        final parsed = _parsePriceValue(value[key]);
        if (parsed > 0) return parsed;
      }
    }
    return 0;
  }

  List<Map<String, dynamic>> _featuredRestaurants(List<dynamic> restaurants) {
    return restaurants
        .whereType<Map<String, dynamic>>()
        .where((restaurant) =>
            _parseBool(restaurant['is_featured']) &&
            (!_vegOnlyMode || _restaurantIsVegFriendly(restaurant)))
        .toList(growable: false);
  }

  List<Map<String, dynamic>> _bestRatedRestaurants(List<dynamic> restaurants) {
    final sorted = restaurants.whereType<Map<String, dynamic>>().toList();
    sorted.removeWhere(
      (restaurant) => _vegOnlyMode && !_restaurantIsVegFriendly(restaurant),
    );
    sorted.sort((a, b) => _ratingOf(b).compareTo(_ratingOf(a)));
    return sorted;
  }

  List<Map<String, dynamic>> _newArrivalRestaurants(List<dynamic> restaurants) {
    final sorted = restaurants.whereType<Map<String, dynamic>>().toList();
    sorted.removeWhere(
      (restaurant) => _vegOnlyMode && !_restaurantIsVegFriendly(restaurant),
    );
    sorted.sort((a, b) {
      final left = DateTime.tryParse(a['created_at']?.toString() ?? '');
      final right = DateTime.tryParse(b['created_at']?.toString() ?? '');
      if (left == null && right == null) return 0;
      if (left == null) return 1;
      if (right == null) return -1;
      return right.compareTo(left);
    });
    return sorted;
  }

  List<Map<String, dynamic>> _trendingRestaurants(List<dynamic> restaurants) {
    final sorted = restaurants.whereType<Map<String, dynamic>>().toList();
    sorted.removeWhere(
      (restaurant) => _vegOnlyMode && !_restaurantIsVegFriendly(restaurant),
    );
    sorted.sort((a, b) {
      final orderCompare =
          _parseInt(b['orders_count']).compareTo(_parseInt(a['orders_count']));
      if (orderCompare != 0) return orderCompare;
      return _ratingOf(b).compareTo(_ratingOf(a));
    });
    return sorted;
  }

  List<Map<String, dynamic>> _brandItemsFromRestaurants(
      List<dynamic> restaurants) {
    return restaurants
        .whereType<Map<String, dynamic>>()
        .where((restaurant) => _resolveImageUrl(
              restaurant,
              const ['logo_image', 'logo', 'image', 'banner_image'],
            ).isNotEmpty)
        .where((restaurant) =>
            !_vegOnlyMode || _restaurantIsVegFriendly(restaurant))
        .map((restaurant) => <String, dynamic>{
              'id': _restaurantId(restaurant),
              'restaurant_id': _restaurantId(restaurant),
              'name': restaurant['name']?.toString() ?? 'Brand',
              'logo_image': _resolveImageUrl(
                restaurant,
                const ['logo_image', 'logo', 'image', 'banner_image'],
              ),
            })
        .toList(growable: false);
  }

  int _restaurantId(Map<String, dynamic> restaurant) {
    return _parseInt(restaurant['id'] ?? restaurant['restaurant_id']);
  }

  double _ratingOf(Map<String, dynamic> restaurant) {
    return _parseDouble(
      restaurant['rating'] ??
          restaurant['avg_rating'] ??
          restaurant['review_rating'] ??
          restaurant['rating_value'],
      fallback: 0,
    );
  }

  double _parseDouble(dynamic value, {double fallback = 0}) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? fallback;
    return fallback;
  }

  double? _parseDoubleOrNull(dynamic value) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
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
      final normalized = value.trim().toLowerCase();
      return normalized == 'true' ||
          normalized == '1' ||
          normalized == 'yes' ||
          normalized == 'y';
    }
    return fallback;
  }

  String _resolveImageUrl(dynamic item, List<String> keys) {
    if (item is! Map) return '';
    for (final key in keys) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) {
        return _resolveHomeAssetUrl(value);
      }
    }
    return '';
  }

  String _restaurantCuisineText(Map<String, dynamic> restaurant) {
    List<String> namesFrom(dynamic value) {
      if (value is String && value.trim().isNotEmpty) {
        return value
            .split(',')
            .map((item) => item.trim())
            .where((item) => item.isNotEmpty && int.tryParse(item) == null)
            .toList();
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
              final text = item?.toString().trim() ?? '';
              return int.tryParse(text) == null ? text : '';
            })
            .where((item) => item.isNotEmpty)
            .take(3)
            .toList();
      }
      return const <String>[];
    }

    for (final key in const [
      'cuisine_text',
      'cuisine_names',
      'cuisines',
      'cuisine',
    ]) {
      final names = namesFrom(restaurant[key]);
      if (names.isNotEmpty) return names.join(', ');
    }
    return 'Various cuisines';
  }

  String _restaurantOfferText(Map<String, dynamic> restaurant) {
    final discount = restaurant['discount']?.toString().trim() ?? '';
    if (discount.isNotEmpty) return discount;
    final offer = restaurant['offer']?.toString().trim() ?? '';
    if (offer.isNotEmpty) return offer;
    final promos = restaurant['active_promos'];
    if (promos is List && promos.isNotEmpty) {
      final first = promos.first;
      if (first is Map) {
        final value = first['discount_value']?.toString().trim() ?? '';
        if (value.isNotEmpty) {
          final type =
              first['discount_type']?.toString().trim() ?? 'percentage';
          return type == 'percentage' ? '$value% OFF' : '$value OFF';
        }
      }
    }
    return '';
  }

  void _openRestaurant(Map<String, dynamic> restaurant) {
    final id = _restaurantId(restaurant);
    if (id <= 0) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RestaurantDetailScreen(restaurantId: id),
      ),
    );
  }

  void _openCategory(dynamic category) {
    final title = (category is Map
            ? category['name'] ?? category['title'] ?? category['cuisine']
            : null)
        ?.toString()
        .trim();
    if (title == null || title.isEmpty) return;
    final cuisineId = category is Map
        ? _parseInt(category['id'] ?? category['cuisine_id'], fallback: 0)
        : 0;
    Navigator.push(
      context,
      MaterialPageRoute(
        settings: RouteSettings(arguments: <String, dynamic>{
          'query': title,
          'title': title,
          'category': title,
          'browseMode': 'category',
          if (cuisineId > 0) 'cuisine_id': cuisineId,
        }),
        builder: (_) => const SearchScreen(),
      ),
    );
  }

  void _clearCategoryFilter() {
    setState(() {
      _activeCategoryFilter = null;
      _filteredCategoryRestaurants = [];
      _filteredCategoryDishes = [];
      _isLoadingCategoryItems = false;
    });
  }

  bool _restaurantIsVegFriendly(Map<String, dynamic> restaurant) {
    final analysis = restaurant['_menu_analysis'];
    final menuAnalysis = analysis is Map
        ? Map<String, dynamic>.from(analysis)
        : const <String, dynamic>{};
    return _parseBool(
          restaurant['is_pure_veg'] ??
              restaurant['pure_veg'] ??
              restaurant['is_veg'] ??
              restaurant['vegetarian'] ??
              menuAnalysis['is_pure_veg_menu'],
        ) ||
        _parseBool(menuAnalysis['is_pure_veg_menu']);
  }

  List<Map<String, dynamic>> _scopeRestaurants(
    Iterable<dynamic> restaurants, {
    bool applyVegFilter = true,
  }) {
    final items = restaurants
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .where((restaurant) =>
            !applyVegFilter ||
            !_vegOnlyMode ||
            _restaurantIsVegFriendly(restaurant))
        .toList(growable: false);
    return items;
  }

  List<_HomeDishCardData> _scopeDishes(Iterable<_HomeDishCardData> dishes) {
    if (!_vegOnlyMode) return dishes.toList(growable: false);
    return dishes.where((dish) => dish.isVeg).toList(growable: false);
  }

  List<dynamic> _scopeSectionItemsForVegMode(
    String type,
    List<dynamic> items,
  ) {
    if (!_vegOnlyMode || items.isEmpty) return items;
    if (type == 'popular_dishes') {
      return items.where((item) {
        if (item is _HomeDishCardData) return item.isVeg;
        if (item is Map) {
          return _parseBool(item['is_veg'] ?? item['veg'], fallback: false);
        }
        return false;
      }).toList(growable: false);
    }

    const restaurantSectionTypes = <String>{
      'trending_near_you',
      'featured_restaurants',
      'recommended_for_you',
      'restaurant_grid',
      'restaurant_discovery',
      'nearby_restaurants',
      'popular_restaurants',
      'new_arrivals',
      'shop_by_brand',
    };
    if (!restaurantSectionTypes.contains(type)) return items;

    return items.where((item) {
      if (item is Map) {
        return _restaurantIsVegFriendly(Map<String, dynamic>.from(item));
      }
      return true;
    }).toList(growable: false);
  }

  String _normalizeFilterText(String value) {
    return value.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), ' ').trim();
  }

  bool _containsFilterText(String value, String filter) {
    final normalizedValue = _normalizeFilterText(value);
    final normalizedFilter = _normalizeFilterText(filter);
    if (normalizedFilter.isEmpty) return false;
    return normalizedValue.contains(normalizedFilter);
  }

  bool _restaurantMatchesCategory(
      Map<String, dynamic> restaurant, String filter) {
    List<String> cuisineValues(dynamic value) {
      if (value is String && value.trim().isNotEmpty) {
        return value
            .split(',')
            .map((item) => item.trim())
            .where((item) => item.isNotEmpty && int.tryParse(item) == null)
            .toList();
      }
      if (value is List) {
        return value
            .map((item) {
              if (item is Map) {
                return (item['name'] ??
                        item['title'] ??
                        item['cuisine_name'] ??
                        '')
                    .toString();
              }
              final text = item?.toString().trim() ?? '';
              return int.tryParse(text) == null ? text : '';
            })
            .where((item) => item.trim().isNotEmpty)
            .toList();
      }
      return const <String>[];
    }

    final values = <String>[
      restaurant['name']?.toString() ?? '',
      restaurant['category_name']?.toString() ?? '',
      restaurant['category']?.toString() ?? '',
    ];
    for (final key in const [
      'cuisine_text',
      'cuisine_names',
      'cuisines',
      'cuisine',
    ]) {
      values.addAll(cuisineValues(restaurant[key]));
    }
    return values.any((value) => _containsFilterText(value, filter));
  }

  bool _menuItemMatchesCategory(MenuItem item, String filter) {
    return _containsFilterText(item.name, filter) ||
        _containsFilterText(item.categoryName ?? '', filter) ||
        _containsFilterText(item.cuisineName ?? '', filter);
  }

  Future<void> _applyCategoryFilter(String title) async {
    final matchingRestaurants = _scopeRestaurants(_restaurants)
        .where((restaurant) => _restaurantMatchesCategory(restaurant, title))
        .toList(growable: false);

    setState(() {
      _activeCategoryFilter = title;
      _filteredCategoryRestaurants = matchingRestaurants;
      _filteredCategoryDishes = [];
      _isLoadingCategoryItems = true;
    });

    await _loadCategoryDishes(title, matchingRestaurants);
  }

  Future<void> _loadCategoryDishes(
    String title,
    List<Map<String, dynamic>> matchingRestaurants,
  ) async {
    final sourceRestaurants = matchingRestaurants.isNotEmpty
        ? matchingRestaurants
        : _scopeRestaurants(_restaurants);
    final dishes = <_HomeDishCardData>[];
    final seen = <String>{};

    for (final restaurant in sourceRestaurants.take(10)) {
      final restaurantId = _restaurantId(restaurant);
      if (restaurantId <= 0) continue;
      try {
        final response = await _api
            .get(
              '${ApiConstants.restaurantDetails}/$restaurantId/menu',
              includeAuth: false,
              cacheResponse: true,
              cacheFirst: true,
            )
            .timeout(const Duration(seconds: 5));
        final data = response['data'] is Map<String, dynamic>
            ? response['data'] as Map<String, dynamic>
            : <String, dynamic>{};
        final items = ((data['menu_items'] ?? data['items'] ?? data['menu'])
                    as List? ??
                const <dynamic>[])
            .whereType<Map>()
            .map((json) => MenuItem.fromJson(Map<String, dynamic>.from(json)))
            .where((item) =>
                item.isAvailable &&
                _menuItemMatchesCategory(item, title) &&
                (!_vegOnlyMode || item.isVeg))
            .toList()
          ..sort((a, b) => b.totalOrders.compareTo(a.totalOrders));

        for (final item in items.take(4)) {
          final key = '$restaurantId:${item.id}:${item.name.toLowerCase()}';
          if (!seen.add(key)) continue;
          dishes.add(
            _HomeDishCardData(
              name: item.name,
              imageUrl: item.imageUrl,
              price: item.finalPrice,
              restaurantId: restaurantId,
              restaurantName: restaurant['name']?.toString() ?? 'Restaurant',
              isVeg: item.isVeg,
              rating: _ratingOf(restaurant),
              etaMinutes: _parseInt(
                restaurant['delivery_time'] ?? restaurant['deliveryTime'],
                fallback: 0,
              ),
              item: item,
              restaurant: Restaurant.fromJson(restaurant),
            ),
          );
          if (dishes.length >= 16) break;
        }
      } catch (_) {}
      if (dishes.length >= 16) break;
    }

    if (!mounted || _activeCategoryFilter != title) return;
    setState(() {
      _filteredCategoryDishes = dishes;
      _isLoadingCategoryItems = false;
    });
  }

  void _showAllCategoriesSheet() {
    if (_categories.isEmpty) return;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: _homeBg,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) {
        return SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 54,
                  height: 6,
                  margin: const EdgeInsets.only(bottom: 18),
                  decoration: BoxDecoration(
                    color: const Color(0xFFD7DCE4),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'All Categories',
                        style: TextStyle(
                          color: _homeText,
                          fontSize: 20,
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
                const SizedBox(height: 10),
                Flexible(
                  child: GridView.builder(
                    shrinkWrap: true,
                    padding: const EdgeInsets.only(top: 4),
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 4,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 18,
                      childAspectRatio: 0.78,
                    ),
                    itemCount: _categories.length,
                    itemBuilder: (context, index) => _CategoryPill(
                      category: _categories[index],
                      onTap: () {
                        Navigator.pop(context);
                        _openCategory(_categories[index]);
                      },
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildLoadingView() {
    return Center(
      child: CircularProgressIndicator(
        color: Theme.of(context).colorScheme.primary,
      ),
    );
  }

  Widget _buildBlockingView(_HomeBlockingState state) {
    final offline = state == _HomeBlockingState.offline;
    return Scaffold(
      backgroundColor: _homeBg,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 28, 24, 28),
          child: _HomeAnimatedState(
            icon: offline ? Icons.wifi_off_rounded : Icons.location_off_rounded,
            accent: offline ? const Color(0xFF2563EB) : _homeAccent,
            title: offline
                ? 'Connect to the internet'
                : 'Unfortunately online ordering is no longer available at this location.',
            subtitle: offline
                ? 'Please connect to the internet and try again.'
                : 'Choose another delivery location to continue ordering.',
            primaryLabel: offline ? 'Try again' : 'Change location',
            secondaryLabel: offline ? null : 'Try again',
            onPrimary: offline ? _refresh : widget.onLocationTap,
            onSecondary: offline ? null : _refresh,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final orderProvider = context.watch<OrderProvider>();
    final activeOrders =
        orderProvider.orders.where(_isRunningOrder).toList(growable: false);
    final notificationCount = _notificationCount;
    final currentUser = context.watch<AuthProvider>().currentUser;
    final userName = currentUser?.name ?? '';
    final profileImage = currentUser?.profileImage?.trim() ?? '';
    final profileInitial =
        userName.trim().isNotEmpty ? userName.trim()[0].toUpperCase() : 'S';
    final hasCategoryFilter = _activeCategoryFilter != null;
    final sections = _renderableSections();
    final heroItems = _heroBannerItems();

    if (_isLoading && !_isRefreshing) {
      return Scaffold(
        backgroundColor: _homeBg,
        body: SafeArea(child: _buildLoadingView()),
      );
    }

    final blockingState = _blockingState;
    if (blockingState != null && !_isRefreshing) {
      return _buildBlockingView(blockingState);
    }

    return DefaultTextStyle.merge(
      style: GoogleFonts.nunitoSans(),
      child: Scaffold(
        backgroundColor: _homeBg,
        body: RefreshIndicator(
          onRefresh: _refresh,
          color: Theme.of(context).colorScheme.primary,
          child: CustomScrollView(
            physics: const BouncingScrollPhysics(
              parent: AlwaysScrollableScrollPhysics(),
            ),
            slivers: <Widget>[
              SliverPersistentHeader(
                pinned: true,
                delegate: _HomeHeroPinnedDelegate(
                  topInset: MediaQuery.of(context).padding.top,
                  banners: heroItems,
                  categories: _categories,
                  currentCity: widget.currentCity,
                  currentAddress: widget.currentAddress,
                  notificationCount: notificationCount,
                  profileInitial: profileInitial,
                  profileImage: profileImage,
                  vegOnlyMode: _vegOnlyMode,
                  onLocationTap: widget.onLocationTap,
                  onWalletTap: widget.onWalletTap,
                  onNotificationTap: () {
                    widget.onNotificationTap();
                    Future<void>.delayed(
                      const Duration(milliseconds: 400),
                      _loadNotificationCount,
                    );
                  },
                  onSearchTap: () => Navigator.pushNamed(context, '/search'),
                  onVoiceTap: () => Navigator.pushNamed(
                    context,
                    '/search',
                    arguments: <String, dynamic>{'startVoiceSearch': true},
                  ),
                  onVegModeChanged: _setVegOnlyMode,
                  onTapRestaurant: _openRestaurant,
                  onTapCategory: _openCategory,
                  onMore: _showAllCategoriesSheet,
                ),
              ),
              if (activeOrders.isNotEmpty)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
                    child: _RunningOrderCard(order: activeOrders.first),
                  ),
                ),
              if (hasCategoryFilter)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 20),
                    child: _HomeSectionContainer(
                      child: _buildCategoryFilterContent(),
                    ),
                  ),
                ),
              if (!hasCategoryFilter)
                for (final entry in sections.asMap().entries)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: EdgeInsets.only(
                        top: entry.key == 0
                            ? (activeOrders.isNotEmpty ? 18 : 0)
                            : 20,
                      ),
                      child: _HomeSectionContainer(
                        child: _buildSectionContent(entry.value),
                      ),
                    ),
                  ),
              SliverToBoxAdapter(
                child: SizedBox(
                  height: MediaQuery.of(context).padding.bottom + 110,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCategoryFilterContent() {
    final title = _activeCategoryFilter ?? '';
    final restaurantItems = _scopeRestaurants(_filteredCategoryRestaurants);
    final dishItems = _scopeDishes(_filteredCategoryDishes);
    final hasResults = restaurantItems.isNotEmpty || dishItems.isNotEmpty;

    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
          child: Row(
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: const TextStyle(
                        color: _homeText,
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      _isLoadingCategoryItems
                          ? 'Finding matching restaurants and dishes...'
                          : '${restaurantItems.length} restaurants • ${dishItems.length} dishes',
                      style: const TextStyle(
                        color: _homeMuted,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              TextButton.icon(
                onPressed: _clearCategoryFilter,
                icon: const Icon(Icons.close_rounded, size: 18),
                label: const Text('Clear'),
              ),
            ],
          ),
        ),
        if (_isLoadingCategoryItems && !hasResults)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 36),
            child: CircularProgressIndicator(
              color: Theme.of(context).colorScheme.primary,
            ),
          )
        else if (!hasResults)
          Padding(
            padding: const EdgeInsets.fromLTRB(28, 28, 28, 34),
            child: Column(
              children: <Widget>[
                Icon(
                  Icons.search_off_rounded,
                  size: 48,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: 12),
                const Text(
                  'No matching restaurants or dishes found',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: _homeText,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Try another category or clear the filter.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: _homeMuted,
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          )
        else ...[
          if (dishItems.isNotEmpty) ...[
            _SectionHeader(
              title: 'Matching Dishes',
              subtitle: 'Items available under $title',
            ),
            SizedBox(
              height: 156,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 20),
                itemCount: dishItems.length,
                separatorBuilder: (_, __) => const SizedBox(width: 14),
                itemBuilder: (context, index) => _DishPreviewCard(
                  dish: dishItems[index],
                  onTap: () => _openRestaurant(
                    <String, dynamic>{
                      'id': dishItems[index].effectiveRestaurantId,
                      'name': dishItems[index].restaurantName,
                    },
                  ),
                  onAdd: () => _addDishToCart(dishItems[index]),
                ),
              ),
            ),
            const SizedBox(height: 18),
          ],
          if (restaurantItems.isNotEmpty) ...[
            _SectionHeader(
              title: 'Matching Restaurants',
              subtitle: 'Restaurants serving $title',
              actionLabel: restaurantItems.length > 4 ? 'See All' : null,
              onAction: restaurantItems.length > 4
                  ? () => _showRestaurantListSheet(
                        '$title restaurants',
                        restaurantItems,
                      )
                  : null,
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                children: List<Widget>.generate(
                  min(restaurantItems.length, 6),
                  (index) => Padding(
                    padding: EdgeInsets.only(
                      bottom:
                          index == min(restaurantItems.length, 6) - 1 ? 0 : 12,
                    ),
                    child: _RestaurantListTileModern(
                      restaurant: restaurantItems[index],
                      isSaved: _savedRestaurantIds.contains(
                        _restaurantId(restaurantItems[index]),
                      ),
                      onTap: () => _openRestaurant(restaurantItems[index]),
                      onSaveToggle: () =>
                          _toggleSavedRestaurant(restaurantItems[index]),
                      cuisineTextBuilder: _restaurantCuisineText,
                      ratingBuilder: _ratingOf,
                      parseDouble: _parseDouble,
                      parseInt: _parseInt,
                      imageResolver: _resolveImageUrl,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ],
      ],
    );
  }

  Widget _buildSectionContent(Map<String, dynamic> section) {
    final type = section['type']?.toString() ?? '';
    final items =
        section['resolved_items'] as List<dynamic>? ?? const <dynamic>[];
    if (items.isEmpty) {
      if (_isLoadingRestaurantFeed && _sectionUsesRestaurantFeed(type)) {
        return _buildSectionLoading(section);
      }
      return const SizedBox.shrink();
    }

    switch (type) {
      case 'banner_carousel':
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Special Offers',
              subtitle: section['subtitle']?.toString(),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 0),
              child: _HomePromoBanner(
                banners: items,
                onTapRestaurant: _openRestaurant,
              ),
            ),
          ],
        );
      case 'cuisine_grid':
      case 'categories':
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Explore Categories',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'More',
              onAction: _showAllCategoriesSheet,
            ),
            SizedBox(
              height: 96,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 20),
                itemBuilder: (context, index) => _CategoryPill(
                  category: items[index],
                  onTap: () => _openCategory(items[index]),
                ),
                separatorBuilder: (_, __) => const SizedBox(width: 18),
                itemCount: items.length,
              ),
            ),
          ],
        );
      case 'recommended_for_you':
        final recommendedDishes = items.whereType<_HomeDishCardData>().toList();
        final recommendedRestaurants = items
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList(growable: false);
        if (recommendedDishes.isEmpty && recommendedRestaurants.isEmpty) {
          return const SizedBox.shrink();
        }
        if (recommendedRestaurants.isNotEmpty) {
          final recommendedCardWidth =
              ((MediaQuery.sizeOf(context).width - 44) / 3)
                  .clamp(115.0, 140.0)
                  .toDouble();
          return Column(
            children: <Widget>[
              _SectionHeader(
                title: section['title']?.toString() ?? 'Recommended For You',
                subtitle: section['subtitle']?.toString(),
                actionLabel:
                    recommendedRestaurants.length > 4 ? 'See All' : null,
                onAction: recommendedRestaurants.length > 4
                    ? () => _showRestaurantListSheet(
                          _plainText(
                            section['title']?.toString() ?? 'Recommended',
                          ),
                          recommendedRestaurants,
                        )
                    : null,
              ),
              SizedBox(
                height: 2 * (recommendedCardWidth / 1.25 + 69) + 16,
                child: GridView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 16,
                    mainAxisExtent: recommendedCardWidth,
                  ),
                  itemCount: recommendedRestaurants.length,
                  itemBuilder: (context, index) =>
                      _RecommendedRestaurantGridCard(
                    restaurant: recommendedRestaurants[index],
                    onTap: () => _openRestaurant(recommendedRestaurants[index]),
                    ratingBuilder: _ratingOf,
                    parseDouble: _parseDouble,
                    parseInt: _parseInt,
                    imageResolver: _resolveImageUrl,
                  ),
                ),
              ),
            ],
          );
        }
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Recommended For You',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'See All',
              onAction: () => _showDishSheet(
                _plainText(section['title']?.toString() ?? 'Recommended'),
                recommendedDishes,
              ),
            ),
            SizedBox(
              height: 390,
              child: GridView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 20),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 18,
                  crossAxisSpacing: 24,
                  childAspectRatio: 1,
                ),
                itemCount: recommendedDishes.length,
                itemBuilder: (context, index) {
                  final dish = recommendedDishes[index];
                  return _RecommendedDishCard(
                    dish: dish,
                    isMenuPricePending: dish.price <= 0 &&
                        (_isLoadingRestaurantFeed || _isLoadingPopularDishes),
                    onTap: () => _openRestaurant(
                      <String, dynamic>{
                        'id': dish.effectiveRestaurantId,
                        'name': dish.restaurantName,
                      },
                    ),
                  );
                },
              ),
            ),
          ],
        );
      case 'featured_restaurants':
      case 'trending_near_you':
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Recommended For You',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'See All',
              onAction: () => _showRestaurantListSheet(
                _plainText(section['title']?.toString() ?? 'Restaurants'),
                items
                    .whereType<Map>()
                    .map((e) => Map<String, dynamic>.from(e))
                    .toList(),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                children: List<Widget>.generate(
                  min(items.length, 2),
                  (index) => Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(
                        right: index == 0 && min(items.length, 2) > 1 ? 12 : 0,
                      ),
                      child: SizedBox(
                        height: 278,
                        child: _FeaturedStaticCard(
                          restaurant:
                              Map<String, dynamic>.from(items[index] as Map),
                          isSaved: _savedRestaurantIds.contains(
                            _restaurantId(
                                Map<String, dynamic>.from(items[index] as Map)),
                          ),
                          onTap: () => _openRestaurant(
                            Map<String, dynamic>.from(items[index] as Map),
                          ),
                          onSaveToggle: () => _toggleSavedRestaurant(
                            Map<String, dynamic>.from(items[index] as Map),
                          ),
                          cuisineTextBuilder: _restaurantCuisineText,
                          ratingBuilder: _ratingOf,
                          parseDouble: _parseDouble,
                          parseInt: _parseInt,
                          imageResolver: _resolveImageUrl,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      case 'restaurant_discovery':
      case 'nearby_restaurants':
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Restaurants Near You',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'See All',
              onAction: () => _showRestaurantListSheet(
                _plainText(
                    section['title']?.toString() ?? 'Restaurants Near You'),
                items
                    .whereType<Map>()
                    .map((e) => Map<String, dynamic>.from(e))
                    .toList(),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                children: List<Widget>.generate(
                  items.length,
                  (index) {
                    final restaurant = _restaurantWithLoadedMenuItems(
                      Map<String, dynamic>.from(items[index] as Map),
                    );
                    return Padding(
                      padding: EdgeInsets.only(
                        bottom: index == items.length - 1 ? 0 : 12,
                      ),
                      child: _RestaurantListTileModern(
                        restaurant: restaurant,
                        isSaved: _savedRestaurantIds.contains(
                          _restaurantId(restaurant),
                        ),
                        onTap: () => _openRestaurant(restaurant),
                        onSaveToggle: () => _toggleSavedRestaurant(restaurant),
                        cuisineTextBuilder: _restaurantCuisineText,
                        ratingBuilder: _ratingOf,
                        parseDouble: _parseDouble,
                        parseInt: _parseInt,
                        imageResolver: _resolveImageUrl,
                      ),
                    );
                  },
                ),
              ),
            ),
          ],
        );
      case 'popular_restaurants':
      case 'new_arrivals':
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Restaurants',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'See All',
              onAction: () => _showRestaurantListSheet(
                _plainText(section['title']?.toString() ?? 'Restaurants'),
                items
                    .whereType<Map>()
                    .map((e) => Map<String, dynamic>.from(e))
                    .toList(),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                children: List<Widget>.generate(
                  min(items.length, 4),
                  (index) => Padding(
                    padding: EdgeInsets.only(
                      bottom: index == min(items.length, 4) - 1 ? 0 : 12,
                    ),
                    child: _RestaurantListTileModern(
                      restaurant:
                          Map<String, dynamic>.from(items[index] as Map),
                      isSaved: _savedRestaurantIds.contains(
                        _restaurantId(
                            Map<String, dynamic>.from(items[index] as Map)),
                      ),
                      onTap: () => _openRestaurant(
                        Map<String, dynamic>.from(items[index] as Map),
                      ),
                      onSaveToggle: () => _toggleSavedRestaurant(
                        Map<String, dynamic>.from(items[index] as Map),
                      ),
                      cuisineTextBuilder: _restaurantCuisineText,
                      ratingBuilder: _ratingOf,
                      parseDouble: _parseDouble,
                      parseInt: _parseInt,
                      imageResolver: _resolveImageUrl,
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      case 'restaurant_grid':
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Restaurants',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'See All',
              onAction: () => _showRestaurantListSheet(
                _plainText(section['title']?.toString() ?? 'Restaurants'),
                items
                    .whereType<Map>()
                    .map((e) => Map<String, dynamic>.from(e))
                    .toList(),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                children: List<Widget>.generate(
                  min(items.length, 4),
                  (index) => Padding(
                    padding: EdgeInsets.only(
                      bottom: index == min(items.length, 4) - 1 ? 0 : 12,
                    ),
                    child: _RestaurantListTileModern(
                      restaurant:
                          Map<String, dynamic>.from(items[index] as Map),
                      isSaved: _savedRestaurantIds.contains(
                        _restaurantId(
                            Map<String, dynamic>.from(items[index] as Map)),
                      ),
                      onTap: () => _openRestaurant(
                        Map<String, dynamic>.from(items[index] as Map),
                      ),
                      onSaveToggle: () => _toggleSavedRestaurant(
                        Map<String, dynamic>.from(items[index] as Map),
                      ),
                      cuisineTextBuilder: _restaurantCuisineText,
                      ratingBuilder: _ratingOf,
                      parseDouble: _parseDouble,
                      parseInt: _parseInt,
                      imageResolver: _resolveImageUrl,
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      case 'popular_dishes':
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Popular Dishes',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'See All',
              onAction: () => _showDishSheet(
                _plainText(section['title']?.toString() ?? 'Popular Dishes'),
                items.whereType<_HomeDishCardData>().toList(),
              ),
            ),
            SizedBox(
              height: 156,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 20),
                itemCount: min(items.length, 10),
                separatorBuilder: (_, __) => const SizedBox(width: 14),
                itemBuilder: (context, index) {
                  final dish = items[index] as _HomeDishCardData;
                  return _DishPreviewCard(
                    dish: dish,
                    onTap: () => _openRestaurant(
                      <String, dynamic>{
                        'id': dish.effectiveRestaurantId,
                        'name': dish.restaurantName,
                      },
                    ),
                    onAdd: () => _addDishToCart(dish),
                  );
                },
              ),
            ),
          ],
        );
      case 'admin_offers':
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Offers For You',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'See All',
              onAction: () => Navigator.pushNamed(context, '/offers'),
            ),
            SizedBox(
              height: 124,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 20),
                itemCount: min(items.length, 10),
                separatorBuilder: (_, __) => const SizedBox(width: 12),
                itemBuilder: (context, index) => _OfferTile(
                  offer: Map<String, dynamic>.from(items[index] as Map),
                ),
              ),
            ),
          ],
        );
      case 'shop_by_brand':
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Shop By Brand',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'See All',
              onAction: () => _showBrandSheet(
                items
                    .whereType<Map>()
                    .map((e) => Map<String, dynamic>.from(e))
                    .toList(),
              ),
            ),
            SizedBox(
              height: 118,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 20),
                itemCount: min(items.length, 12),
                separatorBuilder: (_, __) => const SizedBox(width: 18),
                itemBuilder: (context, index) => _BrandBadge(
                  brand: Map<String, dynamic>.from(items[index] as Map),
                  imageResolver: _resolveImageUrl,
                  onTap: () => _openRestaurant(
                    <String, dynamic>{
                      'id': _parseInt(
                        (items[index] as Map)['restaurant_id'] ??
                            (items[index] as Map)['id'],
                      ),
                    },
                  ),
                ),
              ),
            ),
          ],
        );
      default:
        return Column(
          children: <Widget>[
            _SectionHeader(
              title: section['title']?.toString() ?? 'Section',
              subtitle: section['subtitle']?.toString(),
              actionLabel: 'See All',
              onAction: () => _showRestaurantListSheet(
                _plainText(section['title']?.toString() ?? 'Section'),
                items
                    .whereType<Map>()
                    .map((e) => Map<String, dynamic>.from(e))
                    .toList(),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                children: List<Widget>.generate(
                  min(items.length, 4),
                  (index) => Padding(
                    padding: EdgeInsets.only(
                      bottom: index == min(items.length, 4) - 1 ? 0 : 12,
                    ),
                    child: _RestaurantListTileModern(
                      restaurant:
                          Map<String, dynamic>.from(items[index] as Map),
                      isSaved: _savedRestaurantIds.contains(
                        _restaurantId(
                            Map<String, dynamic>.from(items[index] as Map)),
                      ),
                      onTap: () => _openRestaurant(
                        Map<String, dynamic>.from(items[index] as Map),
                      ),
                      onSaveToggle: () => _toggleSavedRestaurant(
                        Map<String, dynamic>.from(items[index] as Map),
                      ),
                      cuisineTextBuilder: _restaurantCuisineText,
                      ratingBuilder: _ratingOf,
                      parseDouble: _parseDouble,
                      parseInt: _parseInt,
                      imageResolver: _resolveImageUrl,
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
    }
  }

  bool _sectionUsesRestaurantFeed(String type) {
    return const <String>{
      'restaurant_discovery',
      'nearby_restaurants',
      'popular_restaurants',
      'new_arrivals',
      'trending_near_you',
      'featured_restaurants',
      'recommended_for_you',
      'restaurant_grid',
      'popular_dishes',
      'shop_by_brand',
    }.contains(type);
  }

  Widget _buildSectionLoading(Map<String, dynamic> section) {
    return Column(
      children: <Widget>[
        _SectionHeader(
          title: section['title']?.toString() ?? 'Restaurants Near You',
          subtitle: section['subtitle']?.toString(),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 26),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: _homeBorder),
            ),
            child: Center(
              child: CircularProgressIndicator(
                color: Theme.of(context).colorScheme.primary,
              ),
            ),
          ),
        ),
      ],
    );
  }

  bool _isRunningOrder(Order order) {
    return !order.isDelivered &&
        !order.isCancelled &&
        const <String>{
          'pending',
          'confirmed',
          'preparing',
          'ready_for_pickup',
          'reached_pickup',
          'picked_up',
          'on_the_way',
        }.contains(order.status);
  }

  void _showRestaurantListSheet(
    String title,
    List<Map<String, dynamic>> restaurants,
  ) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: _homeBg,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) {
        return SafeArea(
          top: false,
          child: SizedBox(
            height: MediaQuery.of(context).size.height * 0.82,
            child: Column(
              children: <Widget>[
                Container(
                  width: 54,
                  height: 6,
                  margin: const EdgeInsets.only(top: 10, bottom: 18),
                  decoration: BoxDecoration(
                    color: const Color(0xFFD7DCE4),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 10, 12),
                  child: Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          title,
                          style: const TextStyle(
                            color: _homeText,
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
                Expanded(
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
                    itemBuilder: (context, index) => _RestaurantListTileModern(
                      restaurant: restaurants[index],
                      isSaved: _savedRestaurantIds.contains(
                        _restaurantId(restaurants[index]),
                      ),
                      onTap: () {
                        Navigator.pop(context);
                        _openRestaurant(restaurants[index]);
                      },
                      onSaveToggle: () =>
                          _toggleSavedRestaurant(restaurants[index]),
                      cuisineTextBuilder: _restaurantCuisineText,
                      ratingBuilder: _ratingOf,
                      parseDouble: _parseDouble,
                      parseInt: _parseInt,
                      imageResolver: _resolveImageUrl,
                    ),
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemCount: restaurants.length,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _showDishSheet(String title, List<_HomeDishCardData> dishes) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: _homeBg,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) {
        return SafeArea(
          top: false,
          child: SizedBox(
            height: MediaQuery.of(context).size.height * 0.75,
            child: Column(
              children: <Widget>[
                Container(
                  width: 54,
                  height: 6,
                  margin: const EdgeInsets.only(top: 10, bottom: 18),
                  decoration: BoxDecoration(
                    color: const Color(0xFFD7DCE4),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 0, 10, 12),
                  child: Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          title,
                          style: const TextStyle(
                            color: _homeText,
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
                Expanded(
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
                    itemBuilder: (context, index) => _DishListTile(
                      dish: dishes[index],
                      onAdd: () => _addDishToCart(dishes[index]),
                      onTap: () {
                        Navigator.pop(context);
                        _openRestaurant(
                          <String, dynamic>{
                            'id': dishes[index].effectiveRestaurantId,
                          },
                        );
                      },
                    ),
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemCount: dishes.length,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _addDishToCart(_HomeDishCardData dish) {
    final item = dish.item;
    final restaurant = dish.restaurant;
    if (item == null || restaurant == null) {
      _openRestaurant(<String, dynamic>{'id': dish.effectiveRestaurantId});
      return;
    }

    if (item.hasCustomizations) {
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: FoodFlowTheme.canvas,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        builder: (_) => MenuCustomizationSheet(
          item: item,
          onAdd: (result) {
            final cart = context.read<CartProvider>();
            for (var i = 0; i < result.quantity; i++) {
              cart.addItem(
                item,
                restaurant,
                selectedVariant: result.variant,
                selectedAddOns: result.addOns,
              );
            }
            _showHomeMessage('${item.name} added to cart');
          },
        ),
      );
      return;
    }

    context.read<CartProvider>().addItem(item, restaurant);
    _showHomeMessage('${item.name} added to cart');
  }

  void _showHomeMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
          content: Text(message), duration: const Duration(milliseconds: 900)),
    );
  }

  void _showBrandSheet(List<Map<String, dynamic>> brands) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: _homeBg,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) {
        return SafeArea(
          top: false,
          child: SizedBox(
            height: MediaQuery.of(context).size.height * 0.62,
            child: Column(
              children: <Widget>[
                Container(
                  width: 54,
                  height: 6,
                  margin: const EdgeInsets.only(top: 10, bottom: 18),
                  decoration: BoxDecoration(
                    color: const Color(0xFFD7DCE4),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.fromLTRB(20, 0, 20, 12),
                  child: Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          'Shop By Brand',
                          style: TextStyle(
                            color: _homeText,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: GridView.builder(
                    padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 3,
                      mainAxisSpacing: 18,
                      crossAxisSpacing: 16,
                      childAspectRatio: 0.82,
                    ),
                    itemCount: brands.length,
                    itemBuilder: (context, index) => _BrandBadge(
                      brand: brands[index],
                      imageResolver: _resolveImageUrl,
                      onTap: () {
                        Navigator.pop(context);
                        _openRestaurant(
                          <String, dynamic>{
                            'id': _parseInt(
                              brands[index]['restaurant_id'] ??
                                  brands[index]['id'],
                            ),
                          },
                        );
                      },
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  String _plainText(String value) {
    return value
        .replaceAll(
          RegExp(
            r'<span\s+style=\\?"color:\s*#[0-9A-Fa-f]{6}\s*;?\\?">',
            caseSensitive: false,
          ),
          '',
        )
        .replaceAll('</span>', '')
        .trim();
  }
}

class _ManualLocationResult {
  const _ManualLocationResult({
    required this.city,
    required this.address,
    required this.latitude,
    required this.longitude,
  });

  final String city;
  final String address;
  final double latitude;
  final double longitude;
}

class _ManualLocationSearchSheet extends StatefulWidget {
  const _ManualLocationSearchSheet({
    required this.loadSuggestions,
  });

  final Future<List<Map<String, dynamic>>> Function(String query)
      loadSuggestions;

  @override
  State<_ManualLocationSearchSheet> createState() =>
      _ManualLocationSearchSheetState();
}

class _ManualLocationSearchSheetState
    extends State<_ManualLocationSearchSheet> {
  final TextEditingController _controller = TextEditingController();
  Timer? _debounce;
  bool _isLoading = false;
  String _query = '';
  String? _error;
  List<Map<String, dynamic>> _suggestions = const <Map<String, dynamic>>[];

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onQueryChanged(String value) {
    _debounce?.cancel();
    final query = value.trim();
    setState(() {
      _query = query;
      _error = null;
    });
    if (query.length < 3) {
      setState(() {
        _isLoading = false;
        _suggestions = const <Map<String, dynamic>>[];
      });
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 450), () {
      _load(query);
    });
  }

  Future<void> _load(String query) async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final results = await widget.loadSuggestions(query);
      if (!mounted || query != _query) return;
      setState(() {
        _suggestions = results;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _error = 'Unable to search locations right now.';
      });
    }
  }

  void _select(Map<String, dynamic> item) {
    final lat = _parseDouble(item['lat']);
    final lng = _parseDouble(item['lng']);
    if (lat == null || lng == null) return;
    final displayName = item['display_name']?.toString().trim();
    final city = item['city']?.toString().trim();
    Navigator.pop(
      context,
      _ManualLocationResult(
        city: city?.isNotEmpty == true
            ? city!
            : displayName?.split(',').first.trim() ?? _query,
        address: displayName?.isNotEmpty == true ? displayName! : _query,
        latitude: lat,
        longitude: lng,
      ),
    );
  }

  double? _parseDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.fromLTRB(16, 10, 16, 18 + bottomInset),
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.82,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Center(
                child: Container(
                  width: 48,
                  height: 5,
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFD9DDE5),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              Row(
                children: <Widget>[
                  IconButton(
                    tooltip: 'Back',
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.arrow_back_rounded),
                    style: FoodFlowTheme.softIconButton(
                      backgroundColor: Colors.white,
                      foregroundColor: FoodFlowTheme.ink,
                    ),
                  ),
                  const SizedBox(width: 10),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Search location',
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Area, street, landmark, or pincode',
                          style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Container(
                height: 56,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: FoodFlowTheme.softSurface(radius: 20),
                child: Row(
                  children: <Widget>[
                    const Icon(
                      Icons.search_rounded,
                      color: FoodFlowTheme.crimson,
                      size: 22,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextField(
                        controller: _controller,
                        autofocus: true,
                        textInputAction: TextInputAction.search,
                        onChanged: _onQueryChanged,
                        onSubmitted: (value) {
                          final query = value.trim();
                          if (query.length >= 3) _load(query);
                        },
                        decoration: const InputDecoration(
                          border: InputBorder.none,
                          hintText: 'Search delivery location',
                          hintStyle: TextStyle(
                            color: FoodFlowTheme.faint,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                    if (_controller.text.isNotEmpty)
                      IconButton(
                        tooltip: 'Clear',
                        onPressed: () {
                          _controller.clear();
                          _onQueryChanged('');
                        },
                        icon: const Icon(Icons.close_rounded, size: 18),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              Flexible(child: _buildResults()),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildResults() {
    if (_query.length < 3) {
      return const _ManualSearchState(
        icon: Icons.travel_explore_rounded,
        title: 'Start typing',
        message: 'Enter at least 3 characters to find your delivery area.',
      );
    }

    if (_isLoading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 30),
        child: Center(
          child: CircularProgressIndicator(color: FoodFlowTheme.crimson),
        ),
      );
    }

    if (_error != null) {
      return _ManualSearchState(
        icon: Icons.wifi_off_rounded,
        title: 'Search failed',
        message: _error!,
      );
    }

    if (_suggestions.isEmpty) {
      return const _ManualSearchState(
        icon: Icons.location_off_outlined,
        title: 'No locations found',
        message: 'Try a nearby landmark, area name, or pincode.',
      );
    }

    return ListView.separated(
      shrinkWrap: true,
      itemCount: _suggestions.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final item = _suggestions[index];
        final title = item['city']?.toString().trim().isNotEmpty == true
            ? item['city'].toString().trim()
            : item['display_name']?.toString().split(',').first.trim() ??
                'Location';
        final subtitle = item['display_name']?.toString() ?? '';
        return _ManualLocationSuggestionTile(
          title: title,
          subtitle: subtitle,
          onTap: () => _select(item),
        );
      },
    );
  }
}

class _ManualSearchState extends StatelessWidget {
  const _ManualSearchState({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 28),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: FoodFlowTheme.crimson.withOpacity(0.08),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Icon(icon, color: FoodFlowTheme.crimson, size: 30),
            ),
            const SizedBox(height: 14),
            Text(
              title,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 17,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 6),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 26),
              child: Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 13,
                  height: 1.35,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ManualLocationSuggestionTile extends StatelessWidget {
  const _ManualLocationSuggestionTile({
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: FoodFlowTheme.softSurface(radius: 20),
        child: Row(
          children: <Widget>[
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: FoodFlowTheme.crimson.withOpacity(0.08),
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Icon(
                Icons.location_on_outlined,
                color: FoodFlowTheme.crimson,
                size: 21,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 14.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  if (subtitle.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12.5,
                        height: 1.3,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 10),
            const Icon(
              Icons.chevron_right_rounded,
              color: FoodFlowTheme.faint,
              size: 22,
            ),
          ],
        ),
      ),
    );
  }
}

class _LocationPickerSheet extends StatefulWidget {
  const _LocationPickerSheet({
    required this.loadSavedAddresses,
    required this.onUseCurrentLocation,
    required this.onManualSearch,
    required this.onSavedAddressSelected,
    required this.onManageAddresses,
    required this.onAddAddress,
  });

  final Future<List<app_address.Address>> Function() loadSavedAddresses;
  final Future<void> Function() onUseCurrentLocation;
  final Future<void> Function() onManualSearch;
  final Future<void> Function(app_address.Address address)
      onSavedAddressSelected;
  final Future<void> Function() onManageAddresses;
  final Future<void> Function() onAddAddress;

  @override
  State<_LocationPickerSheet> createState() => _LocationPickerSheetState();
}

class _LocationPickerSheetState extends State<_LocationPickerSheet> {
  bool _isSyncingLocation = false;

  Future<void> _syncCurrentLocation() async {
    if (_isSyncingLocation) return;
    setState(() => _isSyncingLocation = true);
    try {
      await widget.onUseCurrentLocation();
    } finally {
      if (mounted) setState(() => _isSyncingLocation = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.fromLTRB(16, 10, 16, 18 + bottomInset),
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.82,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Center(
                child: Container(
                  width: 48,
                  height: 5,
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFD9DDE5),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              Row(
                children: <Widget>[
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: FoodFlowTheme.crimson.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: const Icon(
                      Icons.location_on_rounded,
                      color: FoodFlowTheme.crimson,
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Select location',
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Delivery address',
                          style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Close',
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded),
                    style: FoodFlowTheme.softIconButton(
                      backgroundColor: Colors.white,
                      foregroundColor: FoodFlowTheme.ink,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              _LocationActionTile(
                icon: Icons.search_rounded,
                label: 'Search location manually',
                subtitle: 'Area, street, landmark, or pincode',
                onTap: () async {
                  Navigator.pop(context);
                  await widget.onManualSearch();
                },
              ),
              const SizedBox(height: 10),
              _LocationActionTile(
                icon: Icons.my_location_rounded,
                label: _isSyncingLocation
                    ? 'Syncing current location...'
                    : 'Use current location',
                subtitle: _isSyncingLocation
                    ? 'Please wait while GPS and address sync'
                    : 'Detect from GPS and keep full address',
                isLoading: _isSyncingLocation,
                onTap: _syncCurrentLocation,
              ),
              const SizedBox(height: 10),
              _LocationActionTile(
                icon: Icons.add_location_alt_outlined,
                label: 'Add new saved address',
                subtitle: 'Home, work, or another place',
                onTap: widget.onAddAddress,
              ),
              const SizedBox(height: 22),
              Row(
                children: <Widget>[
                  const Expanded(
                    child: Text(
                      'SAVED LOCATIONS',
                      style: TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        letterSpacing: 0.8,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  TextButton.icon(
                    onPressed: widget.onManageAddresses,
                    icon: const Icon(Icons.tune_rounded, size: 16),
                    label: const Text('Manage'),
                    style: TextButton.styleFrom(
                      foregroundColor: FoodFlowTheme.crimson,
                      textStyle: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
              Flexible(
                child: FutureBuilder<List<app_address.Address>>(
                  future: widget.loadSavedAddresses(),
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Padding(
                        padding: EdgeInsets.symmetric(vertical: 24),
                        child: Center(
                          child: CircularProgressIndicator(
                            color: FoodFlowTheme.crimson,
                          ),
                        ),
                      );
                    }

                    if (snapshot.hasError) {
                      return Padding(
                        padding: const EdgeInsets.symmetric(vertical: 18),
                        child: Text(
                          'Saved addresses could not be loaded.',
                          style: const TextStyle(
                            color: FoodFlowTheme.danger,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      );
                    }

                    final addresses =
                        snapshot.data ?? const <app_address.Address>[];
                    if (addresses.isEmpty) {
                      return Padding(
                        padding: const EdgeInsets.symmetric(vertical: 18),
                        child: Text(
                          'No saved addresses yet.',
                          style: TextStyle(
                            color: FoodFlowTheme.muted.withOpacity(0.9),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      );
                    }

                    return ListView.separated(
                      shrinkWrap: true,
                      itemCount: addresses.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, index) {
                        final address = addresses[index];
                        return _SavedAddressTile(
                          address: address,
                          onTap: () => widget.onSavedAddressSelected(address),
                        );
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LocationActionTile extends StatelessWidget {
  const _LocationActionTile({
    required this.icon,
    required this.label,
    required this.subtitle,
    required this.onTap,
    this.isLoading = false,
  });

  final IconData icon;
  final String label;
  final String subtitle;
  final Future<void> Function() onTap;
  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        constraints: const BoxConstraints(minHeight: 64),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: FoodFlowTheme.softSurface(radius: 20),
        child: Row(
          children: <Widget>[
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: FoodFlowTheme.crimson.withOpacity(0.08),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: FoodFlowTheme.crimson, size: 21),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 14.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            if (isLoading)
              const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2.2,
                  color: FoodFlowTheme.crimson,
                ),
              )
            else
              const Icon(
                Icons.chevron_right_rounded,
                color: FoodFlowTheme.faint,
                size: 22,
              ),
          ],
        ),
      ),
    );
  }
}

class _SavedAddressTile extends StatelessWidget {
  const _SavedAddressTile({
    required this.address,
    required this.onTap,
  });

  final app_address.Address address;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(22),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: FoodFlowTheme.elevatedCard(
          radius: 22,
          borderColor: address.isDefault
              ? FoodFlowTheme.crimson.withOpacity(0.32)
              : FoodFlowTheme.line,
        ),
        child: Row(
          children: <Widget>[
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: FoodFlowTheme.crimson.withOpacity(0.1),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                address.isDefault
                    ? Icons.home_rounded
                    : Icons.location_on_outlined,
                color: FoodFlowTheme.crimson,
                size: 21,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Flexible(
                        child: Text(
                          address.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 14.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if (address.isDefault) ...[
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFF1EE),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: const Text(
                            'Default',
                            style: TextStyle(
                              color: FoodFlowTheme.crimson,
                              fontSize: 10.5,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    address.fullAddress,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12.5,
                      height: 1.3,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            const Icon(
              Icons.chevron_right_rounded,
              color: FoodFlowTheme.faint,
              size: 22,
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeAnimatedState extends StatefulWidget {
  const _HomeAnimatedState({
    required this.icon,
    required this.accent,
    required this.title,
    required this.subtitle,
    required this.primaryLabel,
    required this.onPrimary,
    this.secondaryLabel,
    this.onSecondary,
  });

  final IconData icon;
  final Color accent;
  final String title;
  final String subtitle;
  final String primaryLabel;
  final VoidCallback onPrimary;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;

  @override
  State<_HomeAnimatedState> createState() => _HomeAnimatedStateState();
}

class _HomeAnimatedStateState extends State<_HomeAnimatedState>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _float;
  late final Animation<double> _pulse;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1600),
    )..repeat(reverse: true);
    _float = Tween<double>(begin: -8, end: 8).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
    );
    _pulse = Tween<double>(begin: 0.88, end: 1.08).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOutCubic),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SingleChildScrollView(
        physics: const BouncingScrollPhysics(),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            SizedBox(
              width: 190,
              height: 190,
              child: AnimatedBuilder(
                animation: _controller,
                builder: (context, child) {
                  return Stack(
                    alignment: Alignment.center,
                    children: <Widget>[
                      Transform.scale(
                        scale: _pulse.value,
                        child: Container(
                          width: 150,
                          height: 150,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: widget.accent.withOpacity(0.11),
                          ),
                        ),
                      ),
                      Transform.scale(
                        scale: 1.12 - ((_pulse.value - 0.88) * 0.34),
                        child: Container(
                          width: 112,
                          height: 112,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: widget.accent.withOpacity(0.16),
                          ),
                        ),
                      ),
                      Transform.translate(
                        offset: Offset(0, _float.value),
                        child: Container(
                          width: 94,
                          height: 94,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: widget.accent,
                            boxShadow: <BoxShadow>[
                              BoxShadow(
                                color: widget.accent.withOpacity(0.26),
                                blurRadius: 24,
                                offset: const Offset(0, 14),
                              ),
                            ],
                          ),
                          child: Icon(
                            widget.icon,
                            color: Colors.white,
                            size: 44,
                          ),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: 18),
            Text(
              widget.title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: _homeText,
                fontSize: 21,
                height: 1.2,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              widget.subtitle,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: _homeMuted,
                fontSize: 14,
                height: 1.45,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: widget.onPrimary,
                style: ElevatedButton.styleFrom(
                  backgroundColor: widget.accent,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(vertical: 15),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                  textStyle: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                child: Text(widget.primaryLabel),
              ),
            ),
            if (widget.secondaryLabel != null &&
                widget.onSecondary != null) ...[
              const SizedBox(height: 10),
              TextButton(
                onPressed: widget.onSecondary,
                child: Text(widget.secondaryLabel!),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _HomeHeader extends StatelessWidget {
  const _HomeHeader({
    required this.currentCity,
    required this.currentAddress,
    required this.notificationCount,
    required this.profileInitial,
    required this.profileImage,
    required this.onLocationTap,
    required this.onWalletTap,
    required this.onNotificationTap,
  });

  final String currentCity;
  final String currentAddress;
  final int notificationCount;
  final String profileInitial;
  final String profileImage;
  final VoidCallback onLocationTap;
  final VoidCallback onWalletTap;
  final VoidCallback onNotificationTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 72,
      child: Row(
        children: <Widget>[
          Expanded(
            child: InkWell(
              onTap: onLocationTap,
              borderRadius: BorderRadius.circular(18),
              child: Row(
                children: <Widget>[
                  Icon(
                    Icons.location_on_rounded,
                    size: 24,
                    color: Colors.white,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Row(
                          children: <Widget>[
                            Flexible(
                              child: Text(
                                currentCity,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 18.5,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                            const SizedBox(width: 4),
                            const Icon(
                              Icons.keyboard_arrow_down_rounded,
                              color: Colors.white,
                              size: 20,
                            ),
                          ],
                        ),
                        Text(
                          currentAddress,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 12.5,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 12),
          InkWell(
            onTap: onWalletTap,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: Colors.black.withOpacity(0.07),
                    blurRadius: 18,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: const Icon(
                Icons.account_balance_wallet_outlined,
                color: _homeText,
                size: 21,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Stack(
            clipBehavior: Clip.none,
            children: <Widget>[
              InkWell(
                onTap: onNotificationTap,
                borderRadius: BorderRadius.circular(16),
                child: Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: <BoxShadow>[
                      BoxShadow(
                        color: Colors.black.withOpacity(0.07),
                        blurRadius: 18,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.notifications_none_rounded,
                    color: _homeText,
                    size: 22,
                  ),
                ),
              ),
              if (notificationCount > 0)
                Positioned(
                  right: -2,
                  top: -2,
                  child: Container(
                    width: 16,
                    height: 16,
                    decoration: BoxDecoration(
                      color: _homeRed,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      notificationCount > 9 ? '9+' : '$notificationCount',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 8,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(width: 10),
          InkWell(
            onTap: () => Navigator.pushNamed(context, '/profile'),
            borderRadius: BorderRadius.circular(22),
            child: Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: const Color(0xFFFFF7DA),
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 2),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: Colors.black.withOpacity(0.08),
                    blurRadius: 18,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              alignment: Alignment.center,
              clipBehavior: Clip.antiAlias,
              child: profileImage.isNotEmpty
                  ? AppCachedImage(
                      imageUrl: _resolveHomeAssetUrl(profileImage),
                      fit: BoxFit.cover,
                      width: 44,
                      height: 44,
                      errorBuilder: (_, __, ___) => _ProfileInitialAvatar(
                        initial: profileInitial,
                      ),
                    )
                  : _ProfileInitialAvatar(initial: profileInitial),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileInitialAvatar extends StatelessWidget {
  const _ProfileInitialAvatar({required this.initial});

  final String initial;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: const Color(0xFFFFF7DA),
      child: Center(
        child: Text(
          initial,
          style: const TextStyle(
            color: Color(0xFF8A5A00),
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

class _HomeHeroPinnedDelegate extends SliverPersistentHeaderDelegate {
  _HomeHeroPinnedDelegate({
    required this.topInset,
    required this.banners,
    required this.categories,
    required this.currentCity,
    required this.currentAddress,
    required this.notificationCount,
    required this.profileInitial,
    required this.profileImage,
    required this.vegOnlyMode,
    required this.onLocationTap,
    required this.onWalletTap,
    required this.onNotificationTap,
    required this.onSearchTap,
    required this.onVoiceTap,
    required this.onVegModeChanged,
    required this.onTapRestaurant,
    required this.onTapCategory,
    required this.onMore,
  });

  final double topInset;
  final List<dynamic> banners;
  final List<dynamic> categories;
  final String currentCity;
  final String currentAddress;
  final int notificationCount;
  final String profileInitial;
  final String profileImage;
  final bool vegOnlyMode;
  final VoidCallback onLocationTap;
  final VoidCallback onWalletTap;
  final VoidCallback onNotificationTap;
  final VoidCallback onSearchTap;
  final VoidCallback onVoiceTap;
  final ValueChanged<bool> onVegModeChanged;
  final ValueChanged<Map<String, dynamic>> onTapRestaurant;
  final void Function(dynamic category) onTapCategory;
  final VoidCallback onMore;

  double get _panelHeight => categories.isNotEmpty ? 164 : 74;

  double get _expandedCuisineBandHeight => categories.isNotEmpty ? 112 : 0;

  @override
  double get minExtent => topInset + _panelHeight;

  @override
  double get maxExtent => topInset + 448;

  @override
  Widget build(
      BuildContext context, double shrinkOffset, bool overlapsContent) {
    final range = maxExtent - minExtent;
    final progress =
        range <= 0 ? 1.0 : (shrinkOffset / range).clamp(0.0, 1.0).toDouble();
    final searchTop = ui.lerpDouble(topInset + 78, topInset, progress)!;
    final heroOpacity = 1.0 - progress;
    final showExpandedCuisine = categories.isNotEmpty && progress < 0.72;
    final showPinnedCuisine = categories.isNotEmpty && progress >= 0.92;

    return ClipRect(
      child: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          Positioned(
            left: 0,
            right: 0,
            top: 0,
            height: maxExtent - _expandedCuisineBandHeight,
            child: Opacity(
              opacity: heroOpacity,
              child: _HomePromoBanner(
                banners: banners,
                onTapRestaurant: onTapRestaurant,
                height: maxExtent - _expandedCuisineBandHeight,
                borderRadius: 0,
                showIndicators: false,
              ),
            ),
          ),
          if (progress < 1)
            Opacity(
              opacity: heroOpacity,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: <Color>[
                      Colors.black.withOpacity(0.38),
                      Colors.black.withOpacity(0.10),
                      Colors.transparent,
                    ],
                    stops: const <double>[0, 0.42, 1],
                  ),
                ),
              ),
            ),
          if (progress > 0.55)
            Positioned.fill(
              child: ColoredBox(
                color: Colors.white.withOpacity((progress - 0.55) / 0.45),
              ),
            ),
          if (progress < 0.88)
            Positioned(
              left: 20,
              right: 20,
              top: topInset + 8,
              child: Opacity(
                opacity: ((0.88 - progress) / 0.88).clamp(0.0, 1.0),
                child: _HomeHeader(
                  currentCity: currentCity,
                  currentAddress: currentAddress,
                  notificationCount: notificationCount,
                  profileInitial: profileInitial,
                  profileImage: profileImage,
                  onLocationTap: onLocationTap,
                  onWalletTap: onWalletTap,
                  onNotificationTap: onNotificationTap,
                ),
              ),
            ),
          Positioned(
            left: 0,
            right: 0,
            top: searchTop,
            child: _PinnedSearchCuisineStrip(
              categories: showPinnedCuisine ? categories : const <dynamic>[],
              vegOnlyMode: vegOnlyMode,
              onSearchTap: onSearchTap,
              onVoiceTap: onVoiceTap,
              onVegModeChanged: onVegModeChanged,
              onTapCategory: onTapCategory,
              onMore: onMore,
              transparent: progress < 0.2,
            ),
          ),
          if (showExpandedCuisine)
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              height: _expandedCuisineBandHeight,
              child: Opacity(
                opacity: ((0.72 - progress) / 0.72).clamp(0.0, 1.0),
                child: _ExpandedCuisineBand(
                  categories: categories,
                  onTapCategory: onTapCategory,
                  onMore: onMore,
                ),
              ),
            ),
        ],
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _HomeHeroPinnedDelegate oldDelegate) {
    return topInset != oldDelegate.topInset ||
        banners != oldDelegate.banners ||
        categories != oldDelegate.categories ||
        currentCity != oldDelegate.currentCity ||
        currentAddress != oldDelegate.currentAddress ||
        notificationCount != oldDelegate.notificationCount ||
        profileInitial != oldDelegate.profileInitial ||
        profileImage != oldDelegate.profileImage ||
        vegOnlyMode != oldDelegate.vegOnlyMode;
  }
}

class _PinnedSearchCuisineStrip extends StatelessWidget {
  const _PinnedSearchCuisineStrip({
    required this.categories,
    required this.vegOnlyMode,
    required this.onSearchTap,
    required this.onVoiceTap,
    required this.onVegModeChanged,
    required this.onTapCategory,
    required this.onMore,
    this.transparent = false,
  });

  final List<dynamic> categories;
  final bool vegOnlyMode;
  final VoidCallback onSearchTap;
  final VoidCallback onVoiceTap;
  final ValueChanged<bool> onVegModeChanged;
  final void Function(dynamic category) onTapCategory;
  final VoidCallback onMore;
  final bool transparent;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: transparent ? Colors.transparent : Colors.white,
        border: Border(
          bottom: BorderSide(
            color: transparent
                ? Colors.transparent
                : _homeBorder.withOpacity(0.65),
          ),
        ),
        boxShadow: transparent
            ? const <BoxShadow>[]
            : <BoxShadow>[
                BoxShadow(
                  color: Colors.black.withOpacity(0.06),
                  blurRadius: 18,
                  offset: const Offset(0, 8),
                ),
              ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 5, 14, 8),
        child: Column(
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: <Widget>[
                Expanded(
                  child: _HomeSearchBar(
                    onTap: onSearchTap,
                    onVoiceTap: onVoiceTap,
                    showVoiceInside: true,
                  ),
                ),
                const SizedBox(width: 8),
                _HomeVegToggle(
                  vegOnlyMode: vegOnlyMode,
                  onChanged: onVegModeChanged,
                  onHero: transparent,
                ),
              ],
            ),
            if (categories.isNotEmpty) ...<Widget>[
              const SizedBox(height: 8),
              SizedBox(
                height: 86,
                child: Row(
                  children: <Widget>[
                    Expanded(
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        padding: EdgeInsets.zero,
                        itemBuilder: (context, index) {
                          final category = categories[index];
                          return _CategoryPill(
                            category: category,
                            onTap: () => onTapCategory(category),
                          );
                        },
                        separatorBuilder: (_, __) => const SizedBox(width: 12),
                        itemCount: categories.length,
                      ),
                    ),
                    IconButton(
                      tooltip: 'More',
                      onPressed: onMore,
                      icon: const Icon(Icons.tune_rounded, size: 20),
                      color: _homeGreen,
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ExpandedCuisineBand extends StatelessWidget {
  const _ExpandedCuisineBand({
    required this.categories,
    required this.onTapCategory,
    required this.onMore,
  });

  final List<dynamic> categories;
  final void Function(dynamic category) onTapCategory;
  final VoidCallback onMore;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(color: Colors.white),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 8, 14, 8),
        child: Row(
          children: <Widget>[
            Expanded(
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: EdgeInsets.zero,
                itemBuilder: (context, index) {
                  final category = categories[index];
                  return _CategoryPill(
                    category: category,
                    onTap: () => onTapCategory(category),
                  );
                },
                separatorBuilder: (_, __) => const SizedBox(width: 12),
                itemCount: categories.length,
              ),
            ),
            IconButton(
              tooltip: 'More',
              onPressed: onMore,
              icon: const Icon(Icons.tune_rounded, size: 20),
              color: _homeGreen,
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeSearchBar extends StatelessWidget {
  const _HomeSearchBar({
    required this.onTap,
    required this.onVoiceTap,
    this.showVoiceInside = true,
  });

  final VoidCallback onTap;
  final VoidCallback onVoiceTap;
  final bool showVoiceInside;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 56,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          height: 56,
          padding: const EdgeInsets.symmetric(horizontal: 18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFF1F1F1)),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: Colors.black.withOpacity(0.06),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: <Widget>[
              const Icon(
                Icons.search_rounded,
                size: 22,
                color: Color(0xFF00A651),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'Search restaurants, dishes...',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Color(0xFF686B78),
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              if (showVoiceInside) ...<Widget>[
                Container(
                  width: 1,
                  height: 24,
                  color: const Color(0xFFEAEAEA),
                ),
                const SizedBox(width: 8),
                InkWell(
                  onTap: onVoiceTap,
                  borderRadius: BorderRadius.circular(16),
                  child: const SizedBox(
                    width: 34,
                    height: 34,
                    child: Icon(
                      Icons.mic_none_rounded,
                      size: 20,
                      color: Color(0xFF1C1C1C),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _HomeModeToggle extends StatelessWidget {
  const _HomeModeToggle({
    required this.showDiningMode,
    required this.onChanged,
  });

  final bool showDiningMode;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 48,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _homeBorder),
      ),
      child: Row(
        children: <Widget>[
          Expanded(
            child: _HomeModeChip(
              label: 'Delivery',
              icon: Icons.delivery_dining_rounded,
              selected: !showDiningMode,
              onTap: () => onChanged(false),
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: _HomeModeChip(
              label: 'Dining',
              icon: Icons.restaurant_rounded,
              selected: showDiningMode,
              onTap: () => onChanged(true),
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeVegToggle extends StatelessWidget {
  const _HomeVegToggle({
    required this.vegOnlyMode,
    required this.onChanged,
    required this.onHero,
  });

  final bool vegOnlyMode;
  final ValueChanged<bool> onChanged;
  final bool onHero;

  @override
  Widget build(BuildContext context) {
    final titleColor = onHero ? Colors.white : _homeText;
    final labelColor = onHero ? Colors.white : _homeMuted;
    final inactiveTrackColor =
        onHero ? Colors.white.withOpacity(0.45) : const Color(0xFFD1D5DB);
    return SizedBox(
      width: 58,
      height: 52,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Text(
            'VEG',
            style: TextStyle(
              color: titleColor,
              fontSize: 11,
              height: 1,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            'MODE',
            style: TextStyle(
              color: labelColor,
              fontSize: 9.5,
              height: 1,
              fontWeight: FontWeight.w900,
            ),
          ),
          SizedBox(
            height: 28,
            child: FittedBox(
              fit: BoxFit.contain,
              child: Switch.adaptive(
                value: vegOnlyMode,
                activeColor: _homeGreen,
                activeTrackColor: _homeGreen.withOpacity(0.45),
                inactiveThumbColor: Colors.white,
                inactiveTrackColor: inactiveTrackColor,
                onChanged: onChanged,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeModeChip extends StatelessWidget {
  const _HomeModeChip({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final secondary = Theme.of(context).colorScheme.secondary;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        decoration: BoxDecoration(
          gradient: selected
              ? LinearGradient(
                  colors: <Color>[
                    primary,
                    Color.lerp(primary, secondary, 0.24) ?? primary,
                  ],
                )
              : null,
          color: selected ? null : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(
              icon,
              size: 18,
              color: selected ? Colors.white : _homeMuted,
            ),
            const SizedBox(width: 8),
            Text(
              label,
              style: TextStyle(
                color: selected ? Colors.white : _homeMuted,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HomePromoBanner extends StatefulWidget {
  const _HomePromoBanner({
    required this.banners,
    required this.onTapRestaurant,
    this.height,
    this.borderRadius = 24,
    this.showIndicators = true,
  });

  final List<dynamic> banners;
  final ValueChanged<Map<String, dynamic>> onTapRestaurant;
  final double? height;
  final double borderRadius;
  final bool showIndicators;

  @override
  State<_HomePromoBanner> createState() => _HomePromoBannerState();
}

class _HomePromoBannerState extends State<_HomePromoBanner> {
  final PageController _controller = PageController(viewportFraction: 1);
  Timer? _timer;
  int _currentPage = 0;

  @override
  void initState() {
    super.initState();
    _startTimer();
  }

  @override
  void didUpdateWidget(covariant _HomePromoBanner oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.banners.length != widget.banners.length) {
      _timer?.cancel();
      _startTimer();
    }
  }

  void _startTimer() {
    if (widget.banners.length <= 1) return;
    _timer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!_controller.hasClients || !mounted) return;
      final next = (_currentPage + 1) % widget.banners.length;
      _controller.animateToPage(
        next,
        duration: const Duration(milliseconds: 420),
        curve: Curves.easeInOutCubic,
      );
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  String _resolveBannerLottieUrl(dynamic item) {
    if (item is! Map) return '';

    for (final key in const <String>[
      'lottie_url',
      'animation_url',
      'json_url',
      'media_url',
      'asset_url',
      'hero_image',
      'image',
      'banner_image',
      'image_url',
      'photo'
    ]) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) {
        final normalizedValue = value.trim().toLowerCase();
        if (normalizedValue.contains('lottie') ||
            normalizedValue.contains('animation') ||
            normalizedValue.contains('.json') ||
            normalizedValue.contains('.txt')) {
          return _resolveHomeAssetUrl(value);
        }
      }
    }
    return '';
  }

  String _resolveImageUrl(dynamic item) {
    if (item is! Map) return '';

    final lottieUrl = _resolveBannerLottieUrl(item);
    if (lottieUrl.isNotEmpty) {
      return lottieUrl;
    }

    for (final key in const <String>[
      'animation_url',
      'lottie_url',
      'json_url',
      'media_url',
      'asset_url',
      'webp_url',
      'hero_image',
      'image',
      'banner_image',
      'image_url',
      'photo'
    ]) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) {
        return _resolveHomeAssetUrl(value);
      }
    }
    return '';
  }

  bool _isLottieUrl(String url) {
    final normalized = url.trim().toLowerCase();
    if (normalized.isEmpty) return false;
    final uri = Uri.tryParse(normalized);
    final path = uri?.path.toLowerCase() ?? normalized.split('?').first;
    final format = uri?.queryParameters['format']?.toLowerCase();
    final type = uri?.queryParameters['type']?.toLowerCase();
    final filename = path.split('/').last;

    return filename.endsWith('.json') ||
        path.contains('.json/') ||
        normalized.contains('.json') ||
        normalized.contains('.txt') ||
        format == 'json' ||
        type == 'lottie' ||
        normalized.contains('lottie') ||
        normalized.contains('animation');
  }

  bool _isLottieBanner(dynamic banner) {
    if (banner is! Map<String, dynamic>) return false;

    final mediaType = banner['media_type']?.toString().toLowerCase();
    if (mediaType == 'lottie' ||
        mediaType == 'animation' ||
        mediaType == 'json') {
      return true;
    }

    final type = banner['type']?.toString().toLowerCase();
    if (type == 'lottie' || type == 'animation' || type == 'json') {
      return true;
    }

    final format = banner['format']?.toString().toLowerCase();
    if (format == 'json') return true;

    final lottieUrl = banner['lottie_url']?.toString().trim();
    if (lottieUrl != null && lottieUrl.isNotEmpty) {
      return true;
    }

    final image = banner['image']?.toString().trim().toLowerCase();
    if (image != null && image.endsWith('.json')) {
      return true;
    }

    for (final key in const <String>[
      'animation_url',
      'lottie_url',
      'json_url',
      'media_url',
      'asset_url',
      'webp_url',
      'hero_image',
      'image',
      'banner_image',
      'image_url',
      'photo'
    ]) {
      final value = banner[key];
      if (value is String && value.trim().isNotEmpty) {
        final normalizedValue = value.trim().toLowerCase();
        if (normalizedValue.contains('lottie') ||
            normalizedValue.contains('animation') ||
            normalizedValue.contains('.json')) {
          return true;
        }
      }
    }

    return false;
  }

  bool _isLottieMediaItem(dynamic item) {
    if (item is! Map<String, dynamic>) return false;

    final mediaType = item['media_type']?.toString().toLowerCase();
    if (mediaType == 'lottie' ||
        mediaType == 'json' ||
        mediaType == 'animation') {
      return true;
    }

    final type = item['type']?.toString().toLowerCase();
    if (type == 'lottie' || type == 'json' || type == 'animation') {
      return true;
    }

    if (item.containsKey('lottie_url') ||
        item.containsKey('animation_url') ||
        item.containsKey('json_url')) {
      return true;
    }

    for (final key in const <String>[
      'animation_url',
      'lottie_url',
      'json_url',
      'media_url',
      'asset_url',
      'webp_url',
      'hero_image',
      'image',
      'banner_image',
      'image_url',
      'photo'
    ]) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty && _isLottieUrl(value)) {
        return true;
      }
    }

    return false;
  }

  String _bannerText(Map<String, dynamic> banner, List<String> keys) {
    for (final key in keys) {
      final value = banner[key]?.toString().trim();
      if (value != null && value.isNotEmpty) return value;
    }
    return '';
  }

  bool _isImageOnlyBanner(Map<String, dynamic> banner) {
    final layoutMode = banner['layout_mode']?.toString().trim().toLowerCase();
    return layoutMode == 'full_image' || layoutMode == 'image_only';
  }

  Widget _buildBannerMedia(
    String mediaUrl,
    Map<String, dynamic> banner, {
    BoxFit fit = BoxFit.cover,
  }) {
    final fallback = Container(
      color: const Color(0x14000000),
      child: Icon(
        Icons.restaurant_rounded,
        size: 72,
        color: Colors.white.withOpacity(0.92),
      ),
    );
    if (mediaUrl.isEmpty) return fallback;

    final lottieUrl = _resolveBannerLottieUrl(banner);
    final isLottie = lottieUrl.isNotEmpty ||
        _isLottieBanner(banner) ||
        _isLottieUrl(mediaUrl);
    final effectiveUrl = lottieUrl.isNotEmpty ? lottieUrl : mediaUrl;
    if (isLottie) {
      return Lottie.network(
        effectiveUrl,
        fit: BoxFit.contain,
        repeat: true,
        frameRate: FrameRate.max,
        width: double.infinity,
        height: double.infinity,
        errorBuilder: (context, error, stackTrace) {
          return fallback;
        },
      );
    }
    return _buildHomeNetworkImage(
      mediaUrl,
      fit: fit,
      errorWidget: fallback,
    );
  }

  Future<void> _handleBannerTap(Map<String, dynamic> banner) async {
    final campaignId = banner['id'] is int
        ? banner['id'] as int
        : int.tryParse(banner['id']?.toString() ?? '') ?? 0;
    if (campaignId > 0 &&
        (banner['type']?.toString() == 'banner' ||
            banner.containsKey('discount_details'))) {
      unawaited(
        ApiService()
            .post(ApiConstants.campaignTrackClick(campaignId))
            .catchError((_) {}),
      );
    }

    final redirect = banner['redirect'] is Map
        ? Map<String, dynamic>.from(banner['redirect'] as Map)
        : <String, dynamic>{
            'type': banner['redirect_type'] ??
                banner['target_type'] ??
                banner['action_type'],
            'id': banner['redirect_menu_item_id'] ??
                banner['redirect_restaurant_id'] ??
                banner['redirect_category_id'] ??
                banner['target_id'] ??
                banner['collection_id'] ??
                banner['offer_id'],
            'url':
                banner['external_url'] ?? banner['link_url'] ?? banner['link'],
          };
    final redirectType = redirect['type']?.toString().toLowerCase();
    final redirectId = _parseBannerInt(redirect['id']);
    if (redirectType == 'restaurant' && redirectId != null) {
      widget.onTapRestaurant(<String, dynamic>{'id': redirectId});
      return;
    }
    if (redirectType == 'menu_item' && redirectId != null) {
      final restaurantId = _parseBannerInt(redirect['restaurant_id']);
      if (restaurantId != null) {
        Navigator.pushNamed(
          context,
          '/restaurant/detail',
          arguments: <String, dynamic>{
            'restaurantId': restaurantId,
            'menuItemId': redirectId,
          },
        );
        return;
      }
    }
    if (redirectType == 'category' && redirectId != null) {
      final name = (redirect['name'] ?? banner['title'] ?? '').toString();
      Navigator.pushNamed(
        context,
        '/search',
        arguments: <String, dynamic>{
          'source': 'category',
          'browseMode': 'category',
          'category': name.isNotEmpty ? name : 'Category',
          'category_id': redirectId,
        },
      );
      return;
    }
    if (redirectType == 'collection') {
      Navigator.pushNamed(
        context,
        '/search',
        arguments: <String, dynamic>{
          'source': 'collection',
          if (redirectId != null) 'collection_id': redirectId,
          'title': banner['title']?.toString() ?? 'Collection',
        },
      );
      return;
    }
    if (redirectType == 'offer' || redirectType == 'offers') {
      Navigator.pushNamed(context, '/offers');
      return;
    }

    final rawLink = (redirect['url'] ??
                banner['external_url'] ??
                banner['link_url'] ??
                banner['link'])
            ?.toString()
            .trim() ??
        '';
    final match = RegExp(r'/restaurants/(\d+)').firstMatch(rawLink);
    if (match != null) {
      widget.onTapRestaurant(
        <String, dynamic>{'id': int.tryParse(match.group(1) ?? '')},
      );
      return;
    }
    if (rawLink.startsWith('/offers')) {
      Navigator.pushNamed(context, '/offers');
      return;
    }
    if (rawLink.startsWith('/search')) {
      Navigator.pushNamed(context, '/search');
      return;
    }
    final uri = Uri.tryParse(rawLink);
    if (uri != null && uri.hasScheme) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
      return;
    }
    Navigator.pushNamed(context, '/search');
  }

  @override
  Widget build(BuildContext context) {
    final bannerHeight = widget.height ?? 238;
    if (widget.banners.isEmpty) {
      return Container(
        width: double.infinity,
        height: bannerHeight,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(widget.borderRadius),
          color: Colors.white,
        ),
        child: Center(
          child: Icon(Icons.lunch_dining_rounded, color: _homeGreen, size: 96),
        ),
      );
    }

    return Column(
      children: <Widget>[
        SizedBox(
          height: bannerHeight,
          child: PageView.builder(
            controller: _controller,
            itemCount: widget.banners.length,
            onPageChanged: (index) {
              if (!mounted) return;
              setState(() {
                _currentPage = index;
              });
            },
            itemBuilder: (context, index) {
              final banner = widget.banners[index] is Map<String, dynamic>
                  ? widget.banners[index] as Map<String, dynamic>
                  : Map<String, dynamic>.from(widget.banners[index] as Map);
              final mediaUrl = _resolveImageUrl(banner);
              final eyebrow = _bannerText(
                banner,
                const ['eyebrow', 'badge', 'tag', 'label'],
              ).toUpperCase();
              final headline = _bannerText(
                banner,
                const ['headline', 'title', 'name'],
              );
              final subtitle = _bannerText(
                banner,
                const ['subtitle', 'description', 'caption'],
              );
              final cta = _bannerText(
                banner,
                const ['cta', 'cta_text', 'button_text', 'action_text'],
              );
              final isJsonBanner = _resolveBannerLottieUrl(banner).isNotEmpty ||
                  _isLottieBanner(banner) ||
                  _isLottieUrl(mediaUrl);
              final hasText = !_isImageOnlyBanner(banner) &&
                  !isJsonBanner &&
                  (eyebrow.isNotEmpty ||
                      headline.isNotEmpty ||
                      subtitle.isNotEmpty ||
                      cta.isNotEmpty);
              return GestureDetector(
                onTap: () => _handleBannerTap(banner),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(widget.borderRadius),
                  child: Container(
                    width: double.infinity,
                    height: bannerHeight,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(widget.borderRadius),
                      color: Colors.white,
                      boxShadow: <BoxShadow>[
                        BoxShadow(
                          color: Colors.black.withOpacity(0.10),
                          blurRadius: 24,
                          offset: const Offset(0, 12),
                        ),
                      ],
                    ),
                    child: Stack(
                      fit: StackFit.expand,
                      children: <Widget>[
                        _buildBannerMedia(mediaUrl, banner, fit: BoxFit.cover),
                        if (hasText)
                          DecoratedBox(
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                begin: Alignment.topCenter,
                                end: Alignment.bottomCenter,
                                colors: const <Color>[
                                  Colors.transparent,
                                  Colors.transparent,
                                ],
                              ),
                            ),
                          ),
                        if (hasText)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: <Widget>[
                                if (eyebrow.isNotEmpty) ...<Widget>[
                                  Text(
                                    eyebrow,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 12,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                ],
                                if (headline.isNotEmpty)
                                  Text(
                                    headline,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 27,
                                      height: 1.0,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                if (subtitle.isNotEmpty) ...<Widget>[
                                  const SizedBox(height: 6),
                                  Text(
                                    subtitle,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 14,
                                      height: 1.25,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ],
                                if (cta.isNotEmpty) ...<Widget>[
                                  const SizedBox(height: 12),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 16,
                                      vertical: 9,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.black,
                                      borderRadius: BorderRadius.circular(999),
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: <Widget>[
                                        Text(
                                          cta,
                                          style: const TextStyle(
                                            color: Colors.white,
                                            fontSize: 13,
                                            fontWeight: FontWeight.w900,
                                          ),
                                        ),
                                        const SizedBox(width: 6),
                                        const Icon(
                                          Icons.arrow_forward_ios_rounded,
                                          color: Colors.white,
                                          size: 12,
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          )
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
        if (widget.banners.length > 1 && widget.showIndicators) ...<Widget>[
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List<Widget>.generate(
              widget.banners.length,
              (index) => Container(
                width: index == _currentPage ? 10 : 8,
                height: index == _currentPage ? 10 : 8,
                margin: const EdgeInsets.symmetric(horizontal: 4),
                decoration: BoxDecoration(
                  color: index == _currentPage
                      ? _homeGreen
                      : const Color(0xFFD7DCE4),
                  shape: BoxShape.circle,
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }

  int? _parseBannerInt(dynamic value) {
    if (value is int) return value;
    return int.tryParse(value?.toString() ?? '');
  }
}

class _HomeSectionContainer extends StatelessWidget {
  const _HomeSectionContainer({
    required this.child,
  });

  final Widget child;

  @override
  Widget build(BuildContext context) => child;
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.title,
    this.subtitle,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String? subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

  Color? _tryParseColor(String value) {
    final hex = value.trim().replaceFirst('#', '');
    if (hex.length != 6 && hex.length != 8) return null;
    final normalized = hex.length == 6 ? 'FF$hex' : hex;
    final parsed = int.tryParse(normalized, radix: 16);
    if (parsed == null) return null;
    return Color(parsed);
  }

  InlineSpan _styledTitle() {
    final pattern = RegExp(
      r'<span\s+style=\\?"color:\s*(#[0-9A-Fa-f]{6})\s*;?\\?">(.*?)</span>',
      caseSensitive: false,
    );
    final matches = pattern.allMatches(title).toList(growable: false);
    if (matches.isEmpty) {
      final plainText = _plainTitle(title);
      final words = plainText
          .split(RegExp(r'\s+'))
          .where((word) => word.isNotEmpty)
          .toList();
      if (words.isEmpty) {
        return const TextSpan();
      }
      final highlightedCount = words.length >= 3 ? 2 : 1;
      final splitIndex = words.length - highlightedCount;
      final leading = words.take(splitIndex).join(' ');
      final trailing = words.skip(splitIndex).join(' ');
      return TextSpan(
        children: [
          if (leading.isNotEmpty)
            TextSpan(
              text: '$leading ',
              style: const TextStyle(
                color: _homeText,
                fontSize: 21,
                fontWeight: FontWeight.w800,
              ),
            ),
          TextSpan(
            text: trailing,
            style: TextStyle(
              color: _accentColor(),
              fontSize: 21,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      );
    }

    final spans = <InlineSpan>[];
    var cursor = 0;
    for (final match in matches) {
      if (match.start > cursor) {
        spans.add(
          TextSpan(
            text: title.substring(cursor, match.start),
            style: const TextStyle(
              color: _homeText,
              fontSize: 21,
              fontWeight: FontWeight.w800,
            ),
          ),
        );
      }
      spans.add(
        TextSpan(
          text: match.group(2) ?? '',
          style: TextStyle(
            color: _tryParseColor(match.group(1) ?? '') ?? _homeAccent,
            fontSize: 21,
            fontWeight: FontWeight.w800,
          ),
        ),
      );
      cursor = match.end;
    }
    if (cursor < title.length) {
      spans.add(
        TextSpan(
          text: title.substring(cursor),
          style: const TextStyle(
            color: _homeText,
            fontSize: 21,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }
    return TextSpan(children: spans);
  }

  String _plainTitle(String value) {
    return value
        .replaceAll(
          RegExp(r'<[^>]+>', caseSensitive: false),
          '',
        )
        .trim();
  }

  Color _accentColor() {
    final colors = <Color>[
      const Color(0xFFFF6B00),
      const Color(0xFFFF914D),
      const Color(0xFFFF9F1C),
    ];
    return colors[title.length % colors.length];
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: SizedBox(
                  height: 30,
                  child: RichText(
                    text: TextSpan(children: <InlineSpan>[_styledTitle()]),
                  ),
                ),
              ),
              if (actionLabel != null && onAction != null)
                GestureDetector(
                  onTap: onAction,
                  child: Row(
                    children: <Widget>[
                      Text(
                        actionLabel!,
                        style: const TextStyle(
                          color: _homeMuted,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(width: 6),
                      const Icon(
                        Icons.chevron_right_rounded,
                        size: 18,
                        color: _homeMuted,
                      ),
                    ],
                  ),
                ),
            ],
          ),
          if (subtitle != null && subtitle!.trim().isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(
                subtitle!,
                style: const TextStyle(
                  color: _homeMuted,
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _CategoryPill extends StatelessWidget {
  const _CategoryPill({
    required this.category,
    required this.onTap,
  });

  final dynamic category;
  final VoidCallback onTap;

  String _resolveImageUrl(dynamic item) {
    if (item is! Map) return '';
    for (final key in const <String>[
      'image_url',
      'icon_url',
      'image',
      'icon',
      'thumb'
    ]) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) {
        return _resolveHomeAssetUrl(value);
      }
    }
    return '';
  }

  Color _accentColor(String seed) {
    const palette = <Color>[
      Color(0xFFFF7A00),
      Color(0xFFFFC857),
      Color(0xFFB8E986),
      Color(0xFFDAB6FC),
      Color(0xFFFFC6D9),
      Color(0xFFBDE7FF),
    ];
    if (seed.isEmpty) return palette.first;
    return palette[seed.codeUnits.fold<int>(0, (sum, code) => sum + code) %
        palette.length];
  }

  @override
  Widget build(BuildContext context) {
    final name = category is Map
        ? (category['name'] ?? category['title'] ?? '').toString()
        : '';
    final imageUrl = _resolveImageUrl(category);
    final accent = _accentColor(name);

    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: 72,
        child: Column(
          children: <Widget>[
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFF2F2F2)),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: Colors.black.withOpacity(0.06),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: ClipOval(
                child: _buildHomeNetworkImage(
                  imageUrl,
                  fit: BoxFit.cover,
                  width: 64,
                  height: 64,
                  placeholder: ColoredBox(
                    color: Colors.white,
                    child: Icon(
                      Icons.restaurant_menu_rounded,
                      color: accent,
                      size: 28,
                    ),
                  ),
                  errorWidget: ColoredBox(
                    color: Colors.white,
                    child: Icon(
                      Icons.restaurant_menu_rounded,
                      color: accent,
                      size: 28,
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: Color(0xFF1C1C1C),
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RestaurantCarouselCard extends StatelessWidget {
  const _RestaurantCarouselCard({
    required this.restaurant,
    required this.isSaved,
    required this.onTap,
    required this.onSaveToggle,
    required this.cuisineTextBuilder,
    required this.offerTextBuilder,
    required this.ratingBuilder,
    required this.parseDouble,
    required this.parseInt,
  });

  final Map<String, dynamic> restaurant;
  final bool isSaved;
  final VoidCallback onTap;
  final VoidCallback onSaveToggle;
  final String Function(Map<String, dynamic>) cuisineTextBuilder;
  final String Function(Map<String, dynamic>) offerTextBuilder;
  final double Function(Map<String, dynamic>) ratingBuilder;
  final double Function(dynamic, {double fallback}) parseDouble;
  final int Function(dynamic, {int fallback}) parseInt;

  String _resolveImageUrl(Map<String, dynamic> item) {
    for (final key in const <String>[
      'logo_image',
      'logo',
      'banner_image',
      'banner_url',
      'image_url',
      'image',
      'photo'
    ]) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) {
        return _resolveHomeAssetUrl(value);
      }
    }
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final imageUrl = _resolveImageUrl(restaurant);
    final rating = ratingBuilder(restaurant);
    final cuisine = cuisineTextBuilder(restaurant);
    final delivery = parseInt(
      restaurant['delivery_time'] ?? restaurant['deliveryTime'],
      fallback: 30,
    );
    final distance = parseDouble(restaurant['distance'], fallback: -1);
    final offerText = offerTextBuilder(restaurant);
    final amountForOne = parseDouble(
      restaurant['amount_for_one'] ??
          restaurant['amountForOne'] ??
          restaurant['price_for_one'] ??
          restaurant['cost_for_one'] ??
          restaurant['lowest_price'] ??
          restaurant['min_price'],
      fallback: 0,
    );
    final displayAmount = amountForOne > 0
        ? amountForOne
        : _homeMinimumNestedMenuPrice(restaurant);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(22),
      child: SizedBox(
        width: 190,
        height: 266,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: Colors.black.withOpacity(0.08),
                blurRadius: 20,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Stack(
                children: <Widget>[
                  ClipRRect(
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(22),
                    ),
                    child: SizedBox(
                      width: 190,
                      height: 130,
                      child: imageUrl.isNotEmpty
                          ? _buildHomeNetworkImage(
                              imageUrl,
                              fit: BoxFit.cover,
                              width: 190,
                              height: 130,
                              errorWidget: _cardFallback(),
                            )
                          : _cardFallback(),
                    ),
                  ),
                  if (offerText.isNotEmpty)
                    Positioned(
                      left: 12,
                      top: 12,
                      child: Container(
                        width: 64,
                        height: 28,
                        decoration: BoxDecoration(
                          color: _homeGreen,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          offerText.length > 10
                              ? offerText.substring(0, 10)
                              : offerText,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                  Positioned(
                    right: 12,
                    top: 12,
                    child: GestureDetector(
                      onTap: onSaveToggle,
                      child: Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(
                          color: Colors.black.withOpacity(0.4),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          isSaved
                              ? Icons.favorite
                              : Icons.favorite_border_rounded,
                          color: Colors.white,
                          size: 18,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              restaurant['name']?.toString() ?? 'Restaurant',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: _homeText,
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          if (rating > 0)
                            Text(
                              '★ ${rating.toStringAsFixed(1)}',
                              style: const TextStyle(
                                color: _homeGreen,
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          if (rating <= 0)
                            _restaurantRatingOrNewBadge(rating, fontSize: 12),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        distance >= 0
                            ? '$delivery-${delivery + 5} mins  •  ${distance.toStringAsFixed(distance >= 10 ? 0 : 1)} km'
                            : '$delivery-${delivery + 5} mins',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _homeMuted,
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        cuisine,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _homeMuted,
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        displayAmount > 0
                            ? 'From ${formatCurrency(context, displayAmount)}'
                            : 'Order now',
                        style: const TextStyle(
                          color: _homeText,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
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

  Widget _cardFallback() {
    return Container(
      color: _homePeach,
      child: const Center(
        child: Icon(Icons.storefront_rounded, size: 52, color: _homeAccent),
      ),
    );
  }
}

class _RecommendedRestaurantGridCard extends StatelessWidget {
  const _RecommendedRestaurantGridCard({
    required this.restaurant,
    required this.onTap,
    required this.ratingBuilder,
    required this.parseDouble,
    required this.parseInt,
    required this.imageResolver,
  });

  final Map<String, dynamic> restaurant;
  final VoidCallback onTap;
  final double Function(Map<String, dynamic>) ratingBuilder;
  final double Function(dynamic, {double fallback}) parseDouble;
  final int Function(dynamic, {int fallback}) parseInt;
  final String Function(dynamic, List<String>) imageResolver;

  @override
  Widget build(BuildContext context) {
    final imageUrl = imageResolver(restaurant, const <String>[
      'banner_image',
      'banner_url',
      'image_url',
      'image',
      'photo',
      'logo_image',
      'logo',
    ]);
    final rating = ratingBuilder(restaurant);
    final delivery = parseInt(
      restaurant['eta_minutes'] ??
          restaurant['delivery_time'] ??
          restaurant['deliveryTime'],
      fallback: 30,
    );
    final nearAndFastValue = restaurant['is_near_and_fast'];
    final isNearAndFast = nearAndFastValue == true ||
        nearAndFastValue == 1 ||
        nearAndFastValue?.toString().toLowerCase() == 'true';
    final etaRange = restaurant['eta_range']?.toString().trim() ?? '';
    final deliveryLabel = isNearAndFast
        ? 'Near & Fast'
        : etaRange.isNotEmpty
            ? etaRange
            : '$delivery-${delivery + 5} mins';
    final minimumPrice = parseDouble(
      restaurant['minimum_menu_price'] ??
          restaurant['min_menu_price'] ??
          restaurant['amount_for_one'],
      fallback: 0,
    );

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Stack(
            clipBehavior: Clip.none,
            children: <Widget>[
              AspectRatio(
                aspectRatio: 1.25,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: _RestaurantMediaCarousel(
                    restaurant: restaurant,
                    fallbackImageUrl: imageUrl,
                    parseDouble: parseDouble,
                    fallback: _fallback(),
                    rating: rating,
                    deliveryMinutes: delivery,
                    displayAmount: minimumPrice,
                    isSaved: false,
                    onSaveToggle: () {},
                    compact: true,
                  ),
                ),
              ),
              Positioned(
                left: 0,
                bottom: -16,
                child: Container(
                  padding: const EdgeInsets.fromLTRB(8, 5, 10, 5),
                  decoration: BoxDecoration(
                    color: _homeGreen,
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: Colors.white, width: 4),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      const Icon(Icons.star_rounded,
                          color: Colors.white, size: 16),
                      const SizedBox(width: 3),
                      Text(
                        rating > 0 ? rating.toStringAsFixed(1) : 'New',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                          height: 1,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          Text(
            restaurant['name']?.toString() ?? 'Restaurant',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _homeText,
              fontSize: 16,
              height: 1.08,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: <Widget>[
              Icon(
                isNearAndFast ? Icons.bolt_rounded : Icons.timer_outlined,
                color: isNearAndFast ? _homeGreen : _homeMuted,
                size: 21,
              ),
              const SizedBox(width: 5),
              Expanded(
                child: Text(
                  deliveryLabel,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: isNearAndFast ? _homeGreen : _homeMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _fallback() {
    return Container(
      color: _homePeach,
      alignment: Alignment.center,
      child: const Icon(
        Icons.storefront_rounded,
        size: 42,
        color: _homeAccent,
      ),
    );
  }
}

class _RecommendedRestaurantCard extends StatelessWidget {
  const _RecommendedRestaurantCard({
    required this.restaurant,
    required this.isSaved,
    required this.onTap,
    required this.onSaveToggle,
    required this.cuisineTextBuilder,
    required this.offerTextBuilder,
    required this.ratingBuilder,
    required this.parseDouble,
    required this.parseInt,
  });

  final Map<String, dynamic> restaurant;
  final bool isSaved;
  final VoidCallback onTap;
  final VoidCallback onSaveToggle;
  final String Function(Map<String, dynamic>) cuisineTextBuilder;
  final String Function(Map<String, dynamic>) offerTextBuilder;
  final double Function(Map<String, dynamic>) ratingBuilder;
  final double Function(dynamic, {double fallback}) parseDouble;
  final int Function(dynamic, {int fallback}) parseInt;

  String _resolveImageUrl(Map<String, dynamic> item) {
    for (final key in const <String>[
      'logo_image',
      'logo',
      'banner_image',
      'banner_url',
      'image_url',
      'image',
      'photo'
    ]) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) {
        return _resolveHomeAssetUrl(value);
      }
    }
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final imageUrl = _resolveImageUrl(restaurant);
    final rating = ratingBuilder(restaurant);
    final cuisine = cuisineTextBuilder(restaurant);
    final delivery = parseInt(
      restaurant['delivery_time'] ?? restaurant['deliveryTime'],
      fallback: 25,
    );
    final amountForOne = parseDouble(
      restaurant['amount_for_one'] ??
          restaurant['amountForOne'] ??
          restaurant['price_for_one'] ??
          restaurant['cost_for_one'] ??
          restaurant['lowest_price'] ??
          restaurant['min_price'],
      fallback: 0,
    );
    final displayAmount = amountForOne > 0
        ? amountForOne
        : _homeMinimumNestedMenuPrice(restaurant);
    final offerText = offerTextBuilder(restaurant);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: SizedBox(
        width: 158,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Stack(
              children: <Widget>[
                ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: SizedBox(
                    width: 158,
                    height: 108,
                    child: _buildHomeNetworkImage(
                      imageUrl,
                      fit: BoxFit.cover,
                      width: 154,
                      height: 104,
                      placeholder: _fallback(),
                      errorWidget: _fallback(),
                    ),
                  ),
                ),
                if (offerText.isNotEmpty)
                  Positioned(
                    left: 6,
                    top: 6,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 7,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: _homeGreen,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        offerText.length > 8
                            ? offerText.substring(0, 8)
                            : offerText,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                Positioned(
                  right: 6,
                  top: 6,
                  child: GestureDetector(
                    onTap: onSaveToggle,
                    child: Container(
                      width: 28,
                      height: 28,
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.32),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Icon(
                        isSaved
                            ? Icons.favorite
                            : Icons.favorite_border_rounded,
                        color: Colors.white,
                        size: 16,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Expanded(
                  child: Text(
                    restaurant['name']?.toString() ?? 'Restaurant',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _homeText,
                      fontSize: 14.5,
                      height: 1.05,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (rating > 0) ...<Widget>[
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEFFAF1),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Row(
                      children: <Widget>[
                        const Icon(Icons.star_rounded,
                            color: _homeGreen, size: 12),
                        const SizedBox(width: 2),
                        Text(
                          rating.toStringAsFixed(1),
                          style: const TextStyle(
                            color: _homeGreen,
                            fontSize: 10.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                if (rating <= 0)
                  _restaurantRatingOrNewBadge(rating, fontSize: 10.5),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              displayAmount > 0
                  ? '$delivery-${delivery + 5} mins  |  From ${formatCurrency(context, displayAmount)}'
                  : '$delivery-${delivery + 5} mins',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _homeMuted,
                fontSize: 10.8,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              cuisine,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _homeMuted,
                fontSize: 10.8,
                height: 1.16,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              isSaved ? 'Saved for later' : 'Tap to explore',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: primary.withOpacity(0.82),
                fontSize: 10.2,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _fallback() {
    return Container(
      color: _homePeach,
      child: const Center(
        child: Icon(Icons.storefront_rounded, size: 34, color: _homeAccent),
      ),
    );
  }
}

class _FeaturedStaticCard extends StatelessWidget {
  const _FeaturedStaticCard({
    required this.restaurant,
    required this.isSaved,
    required this.onTap,
    required this.onSaveToggle,
    required this.cuisineTextBuilder,
    required this.ratingBuilder,
    required this.parseDouble,
    required this.parseInt,
    required this.imageResolver,
  });

  final Map<String, dynamic> restaurant;
  final bool isSaved;
  final VoidCallback onTap;
  final VoidCallback onSaveToggle;
  final String Function(Map<String, dynamic>) cuisineTextBuilder;
  final double Function(Map<String, dynamic>) ratingBuilder;
  final double Function(dynamic, {double fallback}) parseDouble;
  final int Function(dynamic, {int fallback}) parseInt;
  final String Function(dynamic, List<String>) imageResolver;

  @override
  Widget build(BuildContext context) {
    final imageUrl = imageResolver(
      restaurant,
      const [
        'logo_image',
        'logo',
        'banner_image',
        'banner_url',
        'image_url',
        'image',
        'photo',
      ],
    );
    final rating = ratingBuilder(restaurant);
    final cuisine = cuisineTextBuilder(restaurant);
    final delivery = parseInt(
      restaurant['delivery_time'] ?? restaurant['deliveryTime'],
      fallback: 30,
    );
    final amountForOne = parseDouble(
      restaurant['amount_for_one'] ??
          restaurant['amountForOne'] ??
          restaurant['price_for_one'] ??
          restaurant['cost_for_one'] ??
          restaurant['lowest_price'] ??
          restaurant['min_price'],
      fallback: 0,
    );
    final displayAmount = amountForOne > 0
        ? amountForOne
        : _homeMinimumNestedMenuPrice(restaurant);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          AspectRatio(
            aspectRatio: 1,
            child: Stack(
              children: <Widget>[
                ClipRRect(
                  borderRadius: BorderRadius.circular(24),
                  child: SizedBox.expand(
                    child: _buildHomeNetworkImage(
                      imageUrl,
                      fit: BoxFit.cover,
                      placeholder: _fallback(),
                      errorWidget: _fallback(),
                    ),
                  ),
                ),
                Positioned(
                  right: 8,
                  top: 8,
                  child: GestureDetector(
                    onTap: onSaveToggle,
                    child: Container(
                      width: 30,
                      height: 30,
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.28),
                        borderRadius: BorderRadius.circular(15),
                      ),
                      child: Icon(
                        isSaved
                            ? Icons.favorite
                            : Icons.favorite_border_rounded,
                        color: Colors.white,
                        size: 17,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                child: Text(
                  restaurant['name']?.toString() ?? 'Restaurant',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _homeText,
                    fontSize: 14.8,
                    fontWeight: FontWeight.w800,
                    height: 1.05,
                  ),
                ),
              ),
              if (rating > 0) ...<Widget>[
                const SizedBox(width: 6),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFFAF1),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Row(
                    children: <Widget>[
                      const Icon(Icons.star_rounded,
                          color: _homeGreen, size: 12),
                      const SizedBox(width: 2),
                      Text(
                        rating.toStringAsFixed(1),
                        style: const TextStyle(
                          color: _homeGreen,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              if (rating <= 0) ...<Widget>[
                const SizedBox(width: 6),
                _restaurantRatingOrNewBadge(rating, fontSize: 11),
              ],
            ],
          ),
          const SizedBox(height: 5),
          Text(
            displayAmount > 0
                ? '$delivery-${delivery + 5} mins | From ${formatCurrency(context, displayAmount)}'
                : '$delivery-${delivery + 5} mins',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _homeMuted,
              fontSize: 11.8,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Expanded(
            child: Align(
              alignment: Alignment.topLeft,
              child: Text(
                cuisine,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: _homeMuted,
                  fontSize: 11.8,
                  fontWeight: FontWeight.w600,
                  height: 1.14,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _fallback() {
    return Container(
      color: _homePeach,
      child: const Center(
        child: Icon(Icons.storefront_rounded, size: 40, color: _homeAccent),
      ),
    );
  }
}

class _RestaurantCarouselCardModern extends StatelessWidget {
  const _RestaurantCarouselCardModern({
    required this.restaurant,
    required this.isSaved,
    required this.onTap,
    required this.onSaveToggle,
    required this.cuisineTextBuilder,
    required this.offerTextBuilder,
    required this.ratingBuilder,
    required this.parseDouble,
    required this.parseInt,
  });

  final Map<String, dynamic> restaurant;
  final bool isSaved;
  final VoidCallback onTap;
  final VoidCallback onSaveToggle;
  final String Function(Map<String, dynamic>) cuisineTextBuilder;
  final String Function(Map<String, dynamic>) offerTextBuilder;
  final double Function(Map<String, dynamic>) ratingBuilder;
  final double Function(dynamic, {double fallback}) parseDouble;
  final int Function(dynamic, {int fallback}) parseInt;

  String _resolveImageUrl(Map<String, dynamic> item) {
    for (final key in const <String>[
      'logo_image',
      'logo',
      'banner_image',
      'banner_url',
      'image_url',
      'image',
      'photo'
    ]) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) {
        return _resolveHomeAssetUrl(value);
      }
    }
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final imageUrl = _resolveImageUrl(restaurant);
    final rating = ratingBuilder(restaurant);
    final cuisine = cuisineTextBuilder(restaurant);
    final delivery = parseInt(
      restaurant['delivery_time'] ?? restaurant['deliveryTime'],
      fallback: 30,
    );
    final distance = parseDouble(restaurant['distance'], fallback: -1);
    final offerText = offerTextBuilder(restaurant);
    final amountForOne = parseDouble(
      restaurant['amount_for_one'] ??
          restaurant['amountForOne'] ??
          restaurant['price_for_one'] ??
          restaurant['cost_for_one'] ??
          restaurant['lowest_price'] ??
          restaurant['min_price'],
      fallback: 0,
    );
    final displayAmount = amountForOne > 0
        ? amountForOne
        : _homeMinimumNestedMenuPrice(restaurant);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: SizedBox(
        width: 194,
        height: 252,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: _homeBorder),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: Colors.black.withOpacity(0.06),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Stack(
                children: <Widget>[
                  ClipRRect(
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(22),
                    ),
                    child: SizedBox(
                      width: 194,
                      height: 132,
                      child: imageUrl.isNotEmpty
                          ? _buildHomeNetworkImage(
                              imageUrl,
                              fit: BoxFit.cover,
                              width: 194,
                              height: 132,
                              errorWidget: _fallback(),
                            )
                          : _fallback(),
                    ),
                  ),
                  if (offerText.isNotEmpty)
                    Positioned(
                      left: 12,
                      top: 12,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 9, vertical: 5),
                        decoration: BoxDecoration(
                          color: primary,
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          offerText.length > 12
                              ? offerText.substring(0, 12)
                              : offerText,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                  Positioned(
                    right: 12,
                    top: 12,
                    child: GestureDetector(
                      onTap: onSaveToggle,
                      child: Container(
                        width: 32,
                        height: 32,
                        decoration: BoxDecoration(
                          color: Colors.black.withOpacity(0.34),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          isSaved
                              ? Icons.favorite
                              : Icons.favorite_border_rounded,
                          color: Colors.white,
                          size: 18,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              restaurant['name']?.toString() ?? 'Restaurant',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: _homeText,
                                fontSize: 16.2,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          if (rating > 0)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 6, vertical: 3),
                              decoration: BoxDecoration(
                                color: const Color(0xFFEFFAF1),
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Row(
                                children: <Widget>[
                                  const Icon(
                                    Icons.star_rounded,
                                    color: _homeGreen,
                                    size: 12,
                                  ),
                                  const SizedBox(width: 2),
                                  Text(
                                    rating.toStringAsFixed(1),
                                    style: const TextStyle(
                                      color: _homeGreen,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          if (rating <= 0)
                            _restaurantRatingOrNewBadge(rating, fontSize: 11),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        distance >= 0
                            ? '$delivery-${delivery + 5} mins  |  ${distance.toStringAsFixed(distance >= 10 ? 0 : 1)} km'
                            : '$delivery-${delivery + 5} mins',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _homeMuted,
                          fontSize: 11.6,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        cuisine,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _homeMuted,
                          fontSize: 11.6,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        displayAmount > 0
                            ? 'From ${formatCurrency(context, displayAmount)}'
                            : 'Order now',
                        style: TextStyle(
                          color: primary,
                          fontSize: 11.6,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
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

  Widget _fallback() {
    return Container(
      color: _homePeach,
      child: const Center(
        child: Icon(Icons.storefront_rounded, size: 52, color: _homeAccent),
      ),
    );
  }
}

class _OfferTile extends StatelessWidget {
  const _OfferTile({required this.offer});

  final Map<String, dynamic> offer;

  @override
  Widget build(BuildContext context) {
    final title =
        (offer['title'] ?? offer['code'] ?? 'Special offer').toString();
    final code = (offer['code'] ?? offer['coupon_code'] ?? '').toString();
    final value =
        (offer['discount_value'] ?? offer['discount'] ?? '').toString();
    return Container(
      width: 196,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _homeBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: _homePeach,
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              code.isEmpty ? 'LIVE OFFER' : code,
              style: const TextStyle(
                color: _homeAccent,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const Spacer(),
          Text(
            title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _homeText,
              fontSize: 16,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value.isEmpty ? 'Available now' : '$value off on your next order',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _homeMuted,
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

class _BrandBadge extends StatelessWidget {
  const _BrandBadge({
    required this.brand,
    required this.imageResolver,
    required this.onTap,
  });

  final Map<String, dynamic> brand;
  final String Function(dynamic, List<String>) imageResolver;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final imageUrl = imageResolver(
        brand, const ['logo_image', 'logo', 'image', 'banner_image']);
    final name = brand['name']?.toString() ?? 'Brand';

    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: 72,
        child: Column(
          children: <Widget>[
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFF2DFD2)),
              ),
              child: ClipOval(
                child: _buildHomeNetworkImage(
                  imageUrl,
                  fit: BoxFit.cover,
                  width: 58,
                  height: 58,
                  placeholder: _fallback(name),
                  errorWidget: _fallback(name),
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: _homeText,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _fallback(String name) {
    final initial = name.trim().isNotEmpty ? name.trim()[0].toUpperCase() : 'B';
    return Container(
      color: _homePeach,
      child: Center(
        child: Text(
          initial,
          style: const TextStyle(
            color: _homeAccent,
            fontSize: 24,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}

class _RecommendedDishCard extends StatelessWidget {
  const _RecommendedDishCard({
    required this.dish,
    required this.onTap,
    this.isMenuPricePending = false,
  });

  final _HomeDishCardData dish;
  final VoidCallback onTap;
  final bool isMenuPricePending;

  @override
  Widget build(BuildContext context) {
    final etaText = dish.etaMinutes > 0
        ? '${dish.etaMinutes}-${dish.etaMinutes + 5} mins'
        : 'Near & Fast';
    final title = dish.restaurantName.trim().isNotEmpty &&
            dish.restaurantName.trim().toLowerCase() != 'global menu'
        ? dish.restaurantName.trim()
        : dish.name;
    final priceText = dish.price > 0
        ? 'From ${formatCurrency(context, dish.price)}'
        : isMenuPricePending
            ? 'Menu'
            : 'No menu';

    return GestureDetector(
      onTap: onTap,
      child: SizedBox.expand(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            SizedBox(
              height: 116,
              width: double.infinity,
              child: Stack(
                clipBehavior: Clip.none,
                children: <Widget>[
                  Positioned.fill(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: _buildImageOrFallback(
                        dish.imageUrl,
                        fit: BoxFit.cover,
                        placeholder: _fallback(),
                        errorWidget: _fallback(),
                      ),
                    ),
                  ),
                  Positioned(
                    left: 0,
                    top: 10,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.72),
                        borderRadius: const BorderRadius.only(
                          topRight: Radius.circular(6),
                          bottomRight: Radius.circular(6),
                        ),
                        boxShadow: <BoxShadow>[
                          BoxShadow(
                            color: Colors.black.withOpacity(0.18),
                            blurRadius: 6,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Text(
                        priceText,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    left: 10,
                    bottom: -14,
                    child: _restaurantRatingOrNewBadge(
                      dish.rating,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _homeText,
                fontSize: 16,
                height: 1.1,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: <Widget>[
                Icon(
                  dish.etaMinutes > 0
                      ? Icons.timer_outlined
                      : Icons.bolt_rounded,
                  size: 16,
                  color: dish.etaMinutes > 0 ? _homeMuted : _homeGreen,
                ),
                const SizedBox(width: 5),
                Expanded(
                  child: Text(
                    etaText,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: dish.etaMinutes > 0 ? _homeMuted : _homeGreen,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _fallback() {
    return Container(
      color: _homePeach,
      child: const Center(
        child: Icon(Icons.fastfood_rounded, size: 42, color: _homeAccent),
      ),
    );
  }
}

class _DishPreviewCard extends StatelessWidget {
  const _DishPreviewCard({
    required this.dish,
    required this.onTap,
    required this.onAdd,
  });

  final _HomeDishCardData dish;
  final VoidCallback onTap;
  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 110,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 14,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            ClipRRect(
              borderRadius:
                  const BorderRadius.vertical(top: Radius.circular(20)),
              child: SizedBox(
                height: 70,
                width: double.infinity,
                child: _buildImageOrFallback(
                  dish.imageUrl,
                  fit: BoxFit.cover,
                  height: 70,
                  placeholder: _fallback(),
                  errorWidget: _fallback(),
                ),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(9, 5, 9, 7),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    if (dish.isVeg)
                      const Icon(Icons.eco_rounded,
                          color: _homeGreen, size: 12),
                    const SizedBox(height: 2),
                    Text(
                      dish.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: _homeText,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      dish.restaurantName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: _homeMuted,
                        fontSize: 9.5,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const Spacer(),
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            formatCurrency(context, dish.price),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: _homeText,
                              fontSize: 10.5,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        _DishAddButton(
                          dish: dish,
                          onAdd: onAdd,
                          compact: true,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _fallback() {
    return Container(
      color: _homePeach,
      child: const Center(
        child: Icon(Icons.fastfood_rounded, size: 42, color: _homeAccent),
      ),
    );
  }
}

class _DishAddButton extends StatelessWidget {
  const _DishAddButton({
    required this.dish,
    required this.onAdd,
    this.compact = false,
  });

  final _HomeDishCardData dish;
  final VoidCallback onAdd;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Consumer<CartProvider>(
      builder: (context, cart, _) {
        final quantity =
            dish.item == null ? 0 : cart.quantityFor(dish.item!.id);
        final size = compact ? 30.0 : 36.0;
        if (quantity > 0 && dish.item != null) {
          return Material(
            color: _homeAccent,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(999),
              side: const BorderSide(color: _homeAccent),
            ),
            child: SizedBox(
              width: compact ? 82 : 96,
              height: size,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: <Widget>[
                  InkWell(
                    onTap: () => cart.decrementQuantity(dish.item!.id),
                    borderRadius: BorderRadius.circular(999),
                    child: SizedBox(
                      width: compact ? 26 : 30,
                      height: size,
                      child: Icon(
                        Icons.remove_rounded,
                        size: compact ? 16 : 18,
                        color: Colors.white,
                      ),
                    ),
                  ),
                  Text(
                    quantity > 99 ? '99+' : quantity.toString(),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  InkWell(
                    onTap: onAdd,
                    borderRadius: BorderRadius.circular(999),
                    child: SizedBox(
                      width: compact ? 26 : 30,
                      height: size,
                      child: Icon(
                        Icons.add_rounded,
                        size: compact ? 16 : 18,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        }
        return Material(
          color: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(999),
            side: BorderSide(
              color: _homeAccent.withOpacity(0.55),
            ),
          ),
          child: InkWell(
            onTap: onAdd,
            borderRadius: BorderRadius.circular(999),
            child: SizedBox(
              width: size,
              height: size,
              child: Center(
                child: Icon(
                  Icons.add_rounded,
                  size: compact ? 18 : 22,
                  color: _homeAccent,
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _RestaurantListTile extends StatelessWidget {
  const _RestaurantListTile({
    required this.restaurant,
    required this.isSaved,
    required this.onTap,
    required this.onSaveToggle,
    required this.cuisineTextBuilder,
    required this.ratingBuilder,
    required this.parseDouble,
    required this.parseInt,
    required this.imageResolver,
  });

  final Map<String, dynamic> restaurant;
  final bool isSaved;
  final VoidCallback onTap;
  final VoidCallback onSaveToggle;
  final String Function(Map<String, dynamic>) cuisineTextBuilder;
  final double Function(Map<String, dynamic>) ratingBuilder;
  final double Function(dynamic, {double fallback}) parseDouble;
  final int Function(dynamic, {int fallback}) parseInt;
  final String Function(dynamic, List<String>) imageResolver;

  @override
  Widget build(BuildContext context) {
    final imageUrl = imageResolver(
      restaurant,
      const [
        'logo_image',
        'logo',
        'banner_image',
        'banner_url',
        'image_url',
        'image',
        'photo',
      ],
    );
    final rating = ratingBuilder(restaurant);
    final delivery = parseInt(
      restaurant['delivery_time'] ?? restaurant['deliveryTime'],
      fallback: 30,
    );
    final cuisine = cuisineTextBuilder(restaurant);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: _homeBorder),
        ),
        child: Row(
          children: <Widget>[
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: SizedBox(
                width: 96,
                height: 84,
                child: _buildHomeNetworkImage(
                  imageUrl,
                  fit: BoxFit.cover,
                  width: 96,
                  height: 84,
                  placeholder: _fallback(),
                  errorWidget: _fallback(),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          restaurant['name']?.toString() ?? 'Restaurant',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _homeText,
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      if (rating > 0)
                        Text(
                          '★ ${rating.toStringAsFixed(1)}',
                          style: const TextStyle(
                            color: _homeGreen,
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      if (rating <= 0)
                        _restaurantRatingOrNewBadge(rating, fontSize: 12),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '$delivery-${delivery + 8} mins',
                    style: const TextStyle(
                      color: _homeMuted,
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    cuisine,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _homeMuted,
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: onSaveToggle,
              child: Icon(
                isSaved ? Icons.favorite : Icons.favorite_border_rounded,
                color: isSaved ? _homeAccent : _homeMuted,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _fallback() {
    return Container(
      color: _homePeach,
      child: const Center(
        child: Icon(Icons.storefront_rounded, size: 40, color: _homeAccent),
      ),
    );
  }
}

class _DishListTile extends StatelessWidget {
  const _DishListTile({
    required this.dish,
    required this.onTap,
    required this.onAdd,
  });

  final _HomeDishCardData dish;
  final VoidCallback onTap;
  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: _homeBorder),
        ),
        child: Row(
          children: <Widget>[
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: SizedBox(
                width: 84,
                height: 72,
                child: _buildImageOrFallback(
                  dish.imageUrl,
                  fit: BoxFit.cover,
                  width: 84,
                  height: 72,
                  placeholder: _fallback(),
                  errorWidget: _fallback(),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    dish.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _homeText,
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    dish.restaurantName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: _homeMuted,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    formatCurrency(context, dish.price),
                    style: const TextStyle(
                      color: _homeText,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 10),
            _DishAddButton(
              dish: dish,
              onAdd: onAdd,
            ),
          ],
        ),
      ),
    );
  }

  Widget _fallback() {
    return Container(
      color: _homePeach,
      child: const Center(
        child: Icon(Icons.fastfood_rounded, size: 34, color: _homeAccent),
      ),
    );
  }
}

class _HomeFeatureStrip extends StatelessWidget {
  const _HomeFeatureStrip();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primary.withOpacity(0.08),
        borderRadius: BorderRadius.circular(20),
      ),
      child: const Row(
        children: <Widget>[
          Expanded(
            child: _FeatureItem(
              icon: Icons.delivery_dining_rounded,
              title: 'FREE DELIVERY',
              subtitle: 'On selected orders',
            ),
          ),
          _FeatureDivider(),
          Expanded(
            child: _FeatureItem(
              icon: Icons.shopping_bag_outlined,
              title: 'NO CONTACT',
              subtitle: 'Safe delivery',
            ),
          ),
          _FeatureDivider(),
          Expanded(
            child: _FeatureItem(
              icon: Icons.verified_user_outlined,
              title: 'SAFE & HYGIENIC',
              subtitle: 'Verified partners',
            ),
          ),
        ],
      ),
    );
  }
}

class _FeatureItem extends StatelessWidget {
  const _FeatureItem({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 20, color: primary),
        const SizedBox(width: 5),
        Expanded(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                title,
                maxLines: 2,
                style: TextStyle(
                  color: primary,
                  fontSize: 9.4,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                subtitle,
                maxLines: 2,
                style: const TextStyle(
                  color: _homeText,
                  fontSize: 9.3,
                  height: 1.15,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _FeatureDivider extends StatelessWidget {
  const _FeatureDivider();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      margin: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      color: Theme.of(context).colorScheme.primary.withOpacity(0.16),
    );
  }
}

Widget _restaurantRatingOrNewBadge(
  double rating, {
  EdgeInsetsGeometry? margin,
  double fontSize = 11,
}) {
  if (rating <= 0) {
    return Container(
      margin: margin,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFF0A9443),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const Icon(
            Icons.star_rounded,
            color: Colors.white,
            size: 12,
          ),
          const SizedBox(width: 4),
          Text(
            'New',
            style: TextStyle(
              color: Colors.white,
              fontSize: fontSize,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  return Container(
    margin: margin,
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
    decoration: BoxDecoration(
      color: const Color(0xFFEFFAF1),
      borderRadius: BorderRadius.circular(999),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        const Icon(
          Icons.star_rounded,
          color: _homeGreen,
          size: 13,
        ),
        const SizedBox(width: 2),
        Text(
          rating.toStringAsFixed(1),
          style: TextStyle(
            color: _homeGreen,
            fontSize: fontSize,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

bool _restaurantAcceptsOrders(Map<String, dynamic> restaurant) {
  final value = restaurant['is_open_now'] ?? restaurant['is_open'];
  if (value == null) return true;
  if (value is bool) return value;
  if (value is num) return value != 0;
  final normalized = value.toString().trim().toLowerCase();
  return normalized == 'true' ||
      normalized == '1' ||
      normalized == 'yes' ||
      normalized == 'y';
}

String _restaurantClosedMessage(Map<String, dynamic> restaurant) {
  for (final key in const [
    'next_opening_label',
    'next_opening_text',
    'nextOpenLabel',
  ]) {
    final value = restaurant[key]?.toString().trim();
    if (value != null && value.isNotEmpty) return value;
  }
  return 'Currently closed';
}

Widget _restaurantStatusBadge(bool isOpen) {
  return Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: isOpen ? const Color(0xFF0A9443) : const Color(0xFFD14343),
      borderRadius: BorderRadius.circular(999),
      boxShadow: <BoxShadow>[
        BoxShadow(
          color: Colors.black.withOpacity(0.18),
          blurRadius: 8,
          offset: const Offset(0, 2),
        ),
      ],
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(
          isOpen ? Icons.check_circle_rounded : Icons.lock_clock_rounded,
          color: Colors.white,
          size: 12,
        ),
        const SizedBox(width: 4),
        Text(
          isOpen ? 'Open' : 'Closed',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 10.5,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

class _ScaleOnPress extends StatefulWidget {
  const _ScaleOnPress({
    required this.child,
    required this.onTap,
  });

  final Widget child;
  final VoidCallback onTap;

  @override
  State<_ScaleOnPress> createState() => _ScaleOnPressState();
}

class _ScaleOnPressState extends State<_ScaleOnPress> {
  bool _pressed = false;

  void _setPressed(bool value) {
    if (_pressed == value) return;
    setState(() => _pressed = value);
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: widget.onTap,
      onTapDown: (_) => _setPressed(true),
      onTapCancel: () => _setPressed(false),
      onTapUp: (_) => _setPressed(false),
      child: AnimatedScale(
        scale: _pressed ? 0.985 : 1,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOutCubic,
        child: widget.child,
      ),
    );
  }
}

class _RestaurantListTileModern extends StatelessWidget {
  const _RestaurantListTileModern({
    required this.restaurant,
    required this.isSaved,
    required this.onTap,
    required this.onSaveToggle,
    required this.cuisineTextBuilder,
    required this.ratingBuilder,
    required this.parseDouble,
    required this.parseInt,
    required this.imageResolver,
  });

  final Map<String, dynamic> restaurant;
  final bool isSaved;
  final VoidCallback onTap;
  final VoidCallback onSaveToggle;
  final String Function(Map<String, dynamic>) cuisineTextBuilder;
  final double Function(Map<String, dynamic>) ratingBuilder;
  final double Function(dynamic, {double fallback}) parseDouble;
  final int Function(dynamic, {int fallback}) parseInt;
  final String Function(dynamic, List<String>) imageResolver;

  @override
  Widget build(BuildContext context) {
    final imageUrl = imageResolver(restaurant, const [
      'banner_image',
      'banner_url',
      'image_url',
      'image',
      'photo',
      'logo_image',
      'logo',
    ]);
    final logoUrl = imageResolver(restaurant, const [
      'logo_image',
      'logo',
      'icon',
      'icon_url',
      'restaurant_logo',
      'restaurantLogo',
    ]);
    final rating = ratingBuilder(restaurant);
    final delivery = parseInt(
      restaurant['delivery_time'] ?? restaurant['deliveryTime'],
      fallback: 30,
    );
    final isOpen = _restaurantAcceptsOrders(restaurant);
    final distance = parseDouble(restaurant['distance'], fallback: -1);
    final offerText = _offerText(context, restaurant);
    final cuisine = cuisineTextBuilder(restaurant).trim();
    final costForTwo = _costForTwoText(context, restaurant);
    final amountForOne = parseDouble(
      restaurant['amount_for_one'] ??
          restaurant['amountForOne'] ??
          restaurant['price_for_one'] ??
          restaurant['cost_for_one'] ??
          restaurant['lowest_price'] ??
          restaurant['min_price'],
      fallback: 0,
    );
    final displayAmount = amountForOne > 0
        ? amountForOne
        : _homeMinimumNestedMenuPrice(restaurant);

    return TweenAnimationBuilder<double>(
      duration: const Duration(milliseconds: 250),
      tween: Tween<double>(begin: 0, end: 1),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) {
        return Opacity(
          opacity: value,
          child: Transform.translate(
            offset: Offset(0, (1 - value) * 10),
            child: child,
          ),
        );
      },
      child: Opacity(
        opacity: isOpen ? 1 : 0.74,
        child: _ScaleOnPress(
          onTap: onTap,
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: const Color(0xFFF1F1F1)),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: Colors.black.withOpacity(0.08),
                  blurRadius: 12,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Stack(
                  clipBehavior: Clip.none,
                  alignment: Alignment.bottomCenter,
                  children: <Widget>[
                    ClipRRect(
                      borderRadius:
                          const BorderRadius.vertical(top: Radius.circular(24)),
                      child: SizedBox(
                        height: 210,
                        width: double.infinity,
                        child: _RestaurantMediaCarousel(
                          restaurant: restaurant,
                          fallbackImageUrl: imageUrl,
                          parseDouble: parseDouble,
                          fallback: _fallback(),
                          rating: rating,
                          deliveryMinutes: delivery,
                          displayAmount: displayAmount,
                          isSaved: isSaved,
                          onSaveToggle: onSaveToggle,
                        ),
                      ),
                    ),
                    Positioned(
                      bottom: -28,
                      child: _RestaurantLogoOverlay(
                        logoUrl: logoUrl,
                        fallbackName:
                            restaurant['name']?.toString() ?? 'Restaurant',
                      ),
                    ),
                  ],
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 38, 14, 14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              restaurant['name']?.toString() ?? 'Restaurant',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Color(0xFF1C1C1C),
                                fontSize: 17,
                                height: 1.15,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          _restaurantRatingOrNewBadge(
                            rating,
                            fontSize: 11,
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        cuisine.isEmpty
                            ? (distance >= 0
                                ? '${distance.toStringAsFixed(distance >= 10 ? 0 : 1)} km away'
                                : 'Popular dishes nearby')
                            : distance >= 0
                                ? '$cuisine • ${distance.toStringAsFixed(distance >= 10 ? 0 : 1)} km'
                                : cuisine,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF686B78),
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        costForTwo.isEmpty
                            ? '$delivery min'
                            : '$delivery min • $costForTwo',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF686B78),
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      if (!isOpen) ...<Widget>[
                        const SizedBox(height: 6),
                        Text(
                          _restaurantClosedMessage(restaurant),
                          style: const TextStyle(
                            color: Color(0xFFD14343),
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                      if (offerText.isNotEmpty) ...<Widget>[
                        const SizedBox(height: 10),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEAF8EA),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            offerText,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Color(0xFF00A651),
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _offerText(BuildContext context, Map<String, dynamic> restaurant) {
    final discount = restaurant['discount']?.toString().trim() ?? '';
    if (discount.isNotEmpty) return discount;
    final offer = restaurant['offer']?.toString().trim() ?? '';
    if (offer.isNotEmpty) return offer;
    final promos = restaurant['active_promos'];
    if (promos is List && promos.isNotEmpty && promos.first is Map) {
      final first = Map<String, dynamic>.from(promos.first as Map);
      final value = first['discount_value']?.toString().trim() ?? '';
      final min = first['min_order_amount']?.toString().trim() ?? '';
      if (value.isNotEmpty) {
        final type = first['discount_type']?.toString() ?? 'percentage';
        final label = type == 'percentage' ? '$value% OFF' : 'Flat $value OFF';
        return min.isNotEmpty
            ? '$label above ${formatCurrency(context, parseDouble(min))}'
            : label;
      }
    }
    return '';
  }

  String _costForTwoText(
      BuildContext context, Map<String, dynamic> restaurant) {
    final amount = parseDouble(
      restaurant['cost_for_two'] ??
          restaurant['avg_cost_for_two'] ??
          restaurant['costForTwo'] ??
          restaurant['average_order_value'] ??
          restaurant['minimum_order'],
      fallback: 0,
    );
    if (amount <= 0) return '';
    return '${formatCurrency(context, amount)} for two';
  }

  Widget _fallback() {
    return Container(
      color: _homePeach,
      child: const Center(
        child: Icon(Icons.storefront_rounded, size: 48, color: _homeAccent),
      ),
    );
  }
}

class _RestaurantLogoOverlay extends StatelessWidget {
  const _RestaurantLogoOverlay({
    required this.logoUrl,
    required this.fallbackName,
  });

  final String logoUrl;
  final String fallbackName;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 62,
      height: 62,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withOpacity(0.16),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(15),
        child: logoUrl.isNotEmpty
            ? _buildHomeNetworkImage(
                logoUrl,
                fit: BoxFit.cover,
                width: 56,
                height: 56,
                placeholder: _fallbackLogo(),
                errorWidget: _fallbackLogo(),
              )
            : _fallbackLogo(),
      ),
    );
  }

  Widget _fallbackLogo() {
    final initial = fallbackName.trim().isNotEmpty
        ? fallbackName.trim().characters.first.toUpperCase()
        : 'R';
    return Container(
      color: _homePeach,
      alignment: Alignment.center,
      child: Text(
        initial,
        style: const TextStyle(
          color: _homeAccent,
          fontSize: 24,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _RestaurantMediaCarousel extends StatefulWidget {
  const _RestaurantMediaCarousel({
    required this.restaurant,
    required this.fallbackImageUrl,
    required this.parseDouble,
    required this.fallback,
    required this.rating,
    required this.deliveryMinutes,
    required this.displayAmount,
    required this.isSaved,
    required this.onSaveToggle,
    this.compact = false,
  });

  final Map<String, dynamic> restaurant;
  final String fallbackImageUrl;
  final double Function(dynamic, {double fallback}) parseDouble;
  final Widget fallback;
  final double rating;
  final int deliveryMinutes;
  final double displayAmount;
  final bool isSaved;
  final VoidCallback onSaveToggle;
  final bool compact;

  @override
  State<_RestaurantMediaCarousel> createState() =>
      _RestaurantMediaCarouselState();
}

class _RestaurantMediaCarouselState extends State<_RestaurantMediaCarousel> {
  final PageController _controller = PageController();
  Timer? _autoSlideTimer;
  int _page = 0;

  @override
  void dispose() {
    _autoSlideTimer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _syncAutoSlideTimer());
  }

  @override
  void didUpdateWidget(covariant _RestaurantMediaCarousel oldWidget) {
    super.didUpdateWidget(oldWidget);
    WidgetsBinding.instance.addPostFrameCallback((_) => _syncAutoSlideTimer());
  }

  void _syncAutoSlideTimer() {
    if (!mounted) return;
    final media = _mediaItems();
    if (media.length <= 1) {
      _autoSlideTimer?.cancel();
      _autoSlideTimer = null;
      return;
    }
    if (_autoSlideTimer != null) return;
    _autoSlideTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!mounted || !_controller.hasClients) return;
      final media = _mediaItems();
      if (media.length <= 1) return;
      final nextPage = (_page + 1) % media.length;
      _controller.animateToPage(
        nextPage,
        duration: const Duration(milliseconds: 420),
        curve: Curves.easeOutCubic,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final media = _mediaItems();
    if (media.isEmpty) {
      return widget.fallback;
    }
    final activePage = _page.clamp(0, media.length - 1).toInt();
    return Stack(
      fit: StackFit.expand,
      children: <Widget>[
        PageView.builder(
          controller: _controller,
          itemCount: media.length,
          onPageChanged: (index) => setState(() => _page = index),
          itemBuilder: (context, index) {
            return _buildImageOrFallback(
              media[index].imageUrl,
              fit: BoxFit.cover,
              placeholder: widget.fallback,
              errorWidget: widget.fallback,
            );
          },
        ),
        Positioned(
          left: widget.compact ? 0 : 12,
          top: widget.compact ? 8 : 12,
          child: _MenuImageBadge(
            item: media[activePage],
            fallbackPrice: widget.displayAmount,
            compact: widget.compact,
          ),
        ),
        if (!widget.compact)
          Positioned(
            top: 12,
            right: 12,
            child: GestureDetector(
              onTap: widget.onSaveToggle,
              child: Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.92),
                  shape: BoxShape.circle,
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: Colors.black.withOpacity(0.08),
                      blurRadius: 10,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Icon(
                  widget.isSaved
                      ? Icons.bookmark_rounded
                      : Icons.bookmark_border_rounded,
                  color: const Color(0xFF1C1C1C),
                  size: 20,
                ),
              ),
            ),
          ),
        if (!widget.compact)
          Positioned(
            left: 12,
            bottom: 12,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.62),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                '${widget.deliveryMinutes} min',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        if (media.length > 1 && !widget.compact)
          Positioned(
            right: 16,
            bottom: 16,
            child: Row(
              children: List<Widget>.generate(
                media.length.clamp(0, 6),
                (index) => Container(
                  width: index == activePage ? 18 : 6,
                  height: 6,
                  margin: const EdgeInsets.only(left: 5),
                  decoration: BoxDecoration(
                    color: Colors.white
                        .withOpacity(index == activePage ? 0.95 : 0.55),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }

  List<_RestaurantMediaItem> _mediaItems() {
    final items = <_RestaurantMediaItem>[];
    void add({
      required String imageUrl,
      String? name,
      double? price,
      bool isVeg = false,
    }) {
      final trimmed = imageUrl.trim();
      if (trimmed.isEmpty || items.any((item) => item.imageUrl == trimmed)) {
        return;
      }
      if (_isLottieUrl(trimmed)) {
        return;
      }
      items.add(
        _RestaurantMediaItem(
          imageUrl: trimmed,
          name: name?.trim() ?? '',
          price: price,
          isVeg: isVeg,
        ),
      );
    }

    for (final key in const [
      'menu_items',
      'items',
      'popular_items',
      'featured_items',
      'recommended_items',
      'matched_menu_items',
      'matchedMenuItems',
    ]) {
      final list = widget.restaurant[key];
      if (list is! List) continue;
      for (final raw in list) {
        if (raw is! Map) continue;
        final item = Map<String, dynamic>.from(raw);
        final image = _imageFrom(item);
        add(
          imageUrl: image,
          name: (item['name'] ?? item['title'])?.toString(),
          price: _homeMenuItemPrice(item),
          isVeg: _boolValue(item['is_veg'] ?? item['veg']),
        );
      }
    }
    add(imageUrl: widget.fallbackImageUrl, price: widget.displayAmount);
    return items.take(8).toList(growable: false);
  }

  String _imageFrom(Map<String, dynamic> item) {
    void addFrom(dynamic value, List<String> candidates) {
      if (value is String && value.trim().isNotEmpty) {
        candidates.add(value);
        return;
      }
      if (value is List) {
        for (final entry in value) {
          addFrom(entry, candidates);
        }
        return;
      }
      if (value is Map) {
        for (final key in const ['url', 'path', 'image', 'image_url', 'file']) {
          addFrom(value[key], candidates);
        }
      }
    }

    final candidates = <String>[];
    for (final key in const [
      'image_url',
      'image',
      'photo',
      'banner_image',
      'image_path',
      'thumbnail',
      'images',
    ]) {
      addFrom(item[key], candidates);
    }
    for (final candidate in candidates) {
      final resolved = _resolveHomeAssetUrl(candidate);
      if (resolved.isNotEmpty) return resolved;
    }
    return '';
  }

  bool _boolValue(dynamic value) {
    if (value is bool) return value;
    if (value is int) return value != 0;
    final text = value?.toString().toLowerCase().trim();
    return text == 'true' || text == '1' || text == 'yes';
  }
}

class _RestaurantMediaItem {
  const _RestaurantMediaItem({
    required this.imageUrl,
    required this.name,
    required this.price,
    required this.isVeg,
  });

  final String imageUrl;
  final String name;
  final double? price;
  final bool isVeg;
}

class _MenuImageBadge extends StatelessWidget {
  const _MenuImageBadge({
    required this.item,
    required this.fallbackPrice,
    this.compact = false,
  });

  final _RestaurantMediaItem item;
  final double fallbackPrice;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final price =
        item.price != null && item.price! > 0 ? item.price! : fallbackPrice;
    if (item.name.isEmpty && price <= 0) {
      return const SizedBox.shrink();
    }
    final text = item.price != null && item.price! > 0
        ? '${item.name} · ${formatCurrency(context, item.price!)}'
        : item.name;
    final badgeText =
        price > 0 ? 'From ${formatCurrency(context, price)}' : text;
    return Container(
      constraints: BoxConstraints(maxWidth: compact ? 104 : 240),
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 7 : 10,
        vertical: compact ? 5 : 6,
      ),
      decoration: BoxDecoration(
        color: Colors.black.withOpacity(0.72),
        borderRadius: const BorderRadius.only(
          topRight: Radius.circular(8),
          bottomRight: Radius.circular(8),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (item.isVeg) ...<Widget>[
            const Icon(Icons.crop_square_rounded,
                color: Colors.white, size: 14),
            const SizedBox(width: 4),
          ],
          Flexible(
            child: Text(
              badgeText,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: Colors.white,
                fontSize: compact ? 11 : 13,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _RunningOrderCard extends StatefulWidget {
  const _RunningOrderCard({required this.order});

  final Order order;

  @override
  State<_RunningOrderCard> createState() => _RunningOrderCardState();
}

class _RunningOrderCardState extends State<_RunningOrderCard>
    with SingleTickerProviderStateMixin {
  bool _expanded = false;
  late final AnimationController _pulseController;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _pulseController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final steps = _runningOrderSteps(widget.order);
    final progress = _runningOrderProgress(widget.order);

    return AnimatedContainer(
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: _homeBorder),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: scheme.primary.withOpacity(0.12),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        children: <Widget>[
          InkWell(
            onTap: () => setState(() => _expanded = !_expanded),
            borderRadius: BorderRadius.circular(22),
            child: Row(
              children: <Widget>[
                AnimatedBuilder(
                  animation: _pulseController,
                  builder: (context, _) {
                    final scale = 1 + (_pulseController.value * 0.06);
                    return Transform.scale(
                      scale: scale,
                      child: Container(
                        width: 46,
                        height: 46,
                        decoration: BoxDecoration(
                          color: scheme.primary.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Icon(
                          Icons.delivery_dining_rounded,
                          color: scheme.primary,
                          size: 24,
                        ),
                      ),
                    );
                  },
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        _runningOrderTitle(widget.order),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _homeText,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        '${_runningOrderEta(widget.order)} | ${_runningOrderItemSummary(widget.order)}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _homeMuted,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                SizedBox(
                  width: 36,
                  height: 36,
                  child: Stack(
                    alignment: Alignment.center,
                    children: <Widget>[
                      CircularProgressIndicator(
                        value: progress,
                        strokeWidth: 4,
                        backgroundColor: const Color(0xFFE8EDF6),
                        valueColor:
                            AlwaysStoppedAnimation<Color>(scheme.primary),
                      ),
                      Icon(
                        _expanded
                            ? Icons.keyboard_arrow_down_rounded
                            : Icons.keyboard_arrow_up_rounded,
                        size: 18,
                        color: _homeText,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          if (_expanded) ...[
            const SizedBox(height: 14),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 8,
                backgroundColor: const Color(0xFFEFF2F6),
                valueColor: AlwaysStoppedAnimation<Color>(scheme.primary),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: List<Widget>.generate(
                steps.length,
                (index) => Expanded(
                  child: Row(
                    children: <Widget>[
                      Container(
                        width: 14,
                        height: 14,
                        decoration: BoxDecoration(
                          color:
                              steps[index].$2 ? scheme.primary : Colors.white,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color:
                                steps[index].$2 ? scheme.primary : _homeBorder,
                          ),
                        ),
                      ),
                      if (index != steps.length - 1)
                        Expanded(
                          child: Container(
                            height: 3,
                            margin: const EdgeInsets.symmetric(horizontal: 4),
                            decoration: BoxDecoration(
                              color: steps[index].$2
                                  ? scheme.primary.withOpacity(0.25)
                                  : const Color(0xFFE8EDF6),
                              borderRadius: BorderRadius.circular(999),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              steps.map((step) => step.$1).join('  |  '),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _homeMuted,
                fontSize: 11,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: <Widget>[
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => Navigator.pushNamed(
                      context,
                      '/order/track',
                      arguments: widget.order.id,
                    ),
                    child: const Text('Track Order'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pushNamed(
                      context,
                      '/support',
                      arguments: <String, dynamic>{
                        'order': widget.order,
                        'openChat': true,
                      },
                    ),
                    child: const Text('Support'),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

double _runningOrderProgress(Order order) {
  switch (order.status) {
    case 'pending':
      return 0.2;
    case 'confirmed':
      return 0.35;
    case 'preparing':
      return 0.58;
    case 'ready_for_pickup':
    case 'reached_pickup':
      return 0.75;
    case 'picked_up':
      return 0.88;
    case 'on_the_way':
      return 0.96;
    default:
      return 0.28;
  }
}

String _runningOrderTitle(Order order) {
  switch (order.status) {
    case 'pending':
      return 'Order placed';
    case 'confirmed':
      return 'Restaurant confirmed your order';
    case 'preparing':
      return 'Preparing your order';
    case 'ready_for_pickup':
      return 'Order is ready for pickup';
    case 'reached_pickup':
      return 'Driver reached the restaurant';
    case 'picked_up':
      return 'Order picked up';
    case 'on_the_way':
      return 'Rider is on the way';
    default:
      return order.statusText;
  }
}

String _runningOrderEta(Order order) {
  if (order.etaRange != null && order.etaRange!.isNotEmpty) {
    return order.deliveryDistanceLabel == null
        ? order.etaRange!
        : '${order.etaRange!} • ${order.deliveryDistanceLabel}';
  }
  final eta = order.etaMinutes;
  if (eta != null && eta > 0) {
    final label = eta <= 2 ? 'Arriving in 2 mins' : 'Arriving in $eta mins';
    return order.deliveryDistanceLabel == null
        ? label
        : '$label • ${order.deliveryDistanceLabel}';
  }
  switch (order.status) {
    case 'preparing':
      return 'Freshly being prepared';
    case 'ready_for_pickup':
    case 'reached_pickup':
      return 'Pickup in progress';
    case 'picked_up':
    case 'on_the_way':
      return 'Heading to your address';
    default:
      return 'Live status active';
  }
}

String _runningOrderItemSummary(Order order) {
  if (order.items.isEmpty) {
    return 'Your order is moving smoothly';
  }
  final first = order.items.first.name;
  if (order.items.length == 1) {
    return first;
  }
  return '$first +${order.items.length - 1} more';
}

List<(String, bool)> _runningOrderSteps(Order order) {
  final packed = <String>{
    'preparing',
    'ready_for_pickup',
    'reached_pickup',
    'picked_up',
    'on_the_way',
    'delivered',
  }.contains(order.status);
  final picked = <String>{
    'picked_up',
    'on_the_way',
    'delivered',
  }.contains(order.status);
  final onWay = <String>{
    'on_the_way',
    'delivered',
  }.contains(order.status);
  return <(String, bool)>[
    ('Confirmed', true),
    ('Packed', packed),
    ('Picked up', picked),
    ('On the way', onWay),
  ];
}

class _FeedbackRatingSection extends StatelessWidget {
  const _FeedbackRatingSection({
    required this.title,
    required this.rating,
    required this.controller,
    required this.hint,
    required this.onChanged,
  });

  final String title;
  final int rating;
  final TextEditingController controller;
  final String hint;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: _homeText,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            children: List.generate(5, (index) {
              final value = index + 1;
              return IconButton(
                visualDensity: VisualDensity.compact,
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints.tightFor(
                  width: 36,
                  height: 36,
                ),
                onPressed: () => onChanged(value),
                icon: Icon(
                  value <= rating
                      ? Icons.star_rounded
                      : Icons.star_border_rounded,
                  color: _homeGreen,
                  size: 30,
                ),
              );
            }),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: controller,
            maxLines: 2,
            decoration: InputDecoration(
              hintText: hint,
              filled: true,
              fillColor: const Color(0xFFF8FAFC),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: _homeBorder),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: _homeBorder),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeBottomNavBar extends StatelessWidget {
  const _HomeBottomNavBar({
    required this.currentIndex,
    required this.cartCount,
    required this.cartActive,
    required this.onTap,
    required this.onCartTap,
  });

  final int currentIndex;
  final int cartCount;
  final bool cartActive;
  final ValueChanged<int> onTap;
  final VoidCallback onCartTap;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).padding.bottom;
    return SizedBox(
      height: 78 + bottomInset,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 14,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        child: Padding(
          padding: EdgeInsets.fromLTRB(10, 10, 10, 8 + bottomInset),
          child: Row(
            children: <Widget>[
              _NavItem(
                icon: AppIcons.home,
                label: 'Home',
                active: currentIndex == 0,
                onTap: () => onTap(0),
              ),
              _NavItem(
                icon: AppIcons.search,
                label: 'Search',
                active: currentIndex == 1,
                onTap: () => onTap(1),
              ),
              _NavItem(
                icon: AppIcons.cart,
                label: 'Cart',
                active: cartActive,
                badgeCount: cartCount,
                onTap: onCartTap,
              ),
              _NavItem(
                icon: AppIcons.receipt,
                label: 'Orders',
                active: currentIndex == 3,
                onTap: () => onTap(3),
              ),
              _NavItem(
                icon: AppIcons.user,
                label: 'Profile',
                active: currentIndex == 4,
                onTap: () => onTap(4),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon,
    required this.label,
    required this.active,
    required this.onTap,
    this.badgeCount = 0,
  });

  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback onTap;
  final int badgeCount;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(18),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Stack(
              clipBehavior: Clip.none,
              children: <Widget>[
                AppIcon(
                  icon,
                  size: 23,
                  color: active ? primary : _homeMuted,
                ),
                if (badgeCount > 0)
                  Positioned(
                    right: -8,
                    top: -6,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 5,
                        vertical: 1,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFF00A651),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        badgeCount > 99 ? '99+' : '$badgeCount',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 7),
            Text(
              label,
              style: TextStyle(
                color: active ? primary : _homeMuted,
                fontSize: 12.2,
                fontWeight: active ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeDishCardData {
  const _HomeDishCardData({
    required this.name,
    required this.imageUrl,
    required this.price,
    required this.restaurantId,
    required this.restaurantName,
    required this.isVeg,
    this.rating = 0,
    this.etaMinutes = 0,
    this.item,
    this.restaurant,
  });

  final String name;
  final String imageUrl;
  final double price;
  final int restaurantId;
  final String restaurantName;
  final bool isVeg;
  final double rating;
  final int etaMinutes;
  final MenuItem? item;
  final Restaurant? restaurant;

  int get effectiveRestaurantId {
    if (restaurantId > 0) return restaurantId;
    final itemRestaurantId = item?.restaurantId ?? 0;
    if (itemRestaurantId > 0) return itemRestaurantId;
    final restaurantModelId = restaurant?.id ?? 0;
    if (restaurantModelId > 0) return restaurantModelId;
    return 0;
  }
}
