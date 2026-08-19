// lib/screens/customer/search_screen.dart
import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/common/app_skeleton.dart';
import 'package:lottie/lottie.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:speech_to_text/speech_to_text.dart';
import 'package:speech_to_text/speech_recognition_error.dart';
import 'package:speech_to_text/speech_recognition_result.dart';

import '../../services/api_service.dart';
import '../../services/app_image_cache.dart';
import '../../services/location_service.dart';
import '../../config/api_constants.dart';
import '../../models/menu_item.dart';
import '../../models/restaurant.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

const _searchText = FoodFlowTheme.ink;
const _searchSubtext = FoodFlowTheme.muted;
const _searchLine = FoodFlowTheme.line;
const _searchBg = Color(0xFFFAFAFA);

Color _searchPrimary(BuildContext context) =>
    FoodFlowTheme.brandPrimary(context);
Color _searchSecondary(BuildContext context) =>
    FoodFlowTheme.brandSecondary(context);

class SearchScreen extends StatefulWidget {
  const SearchScreen({
    super.key,
    this.embedded = false,
  });

  final bool embedded;

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _MenuSearchHit {
  const _MenuSearchHit({
    required this.restaurant,
    required this.item,
  });

  final Restaurant restaurant;
  final MenuItem item;
}

class _SearchMenuItemCard extends StatelessWidget {
  const _SearchMenuItemCard({
    required this.hit,
    required this.onTap,
  });

  final _MenuSearchHit hit;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final primary = _searchPrimary(context);
    final secondary = _searchSecondary(context);
    final imageUrl = hit.item.imageUrl.isNotEmpty
        ? hit.item.imageUrl
        : hit.restaurant.bannerUrl.isNotEmpty
            ? hit.restaurant.bannerUrl
            : hit.restaurant.logoUrl;
    final category = hit.item.categoryName?.trim().isNotEmpty == true
        ? hit.item.categoryName!.trim()
        : hit.item.cuisineName?.trim() ?? hit.restaurant.cuisineText;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          padding: const EdgeInsets.all(10),
          decoration: _searchPanelDecoration(context, radius: 20),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: imageUrl.isNotEmpty
                        ? AppCachedImage(
                            imageUrl: imageUrl,
                            width: 104,
                            height: 132,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) =>
                                _foodFallback(primary),
                          )
                        : _foodFallback(primary),
                  ),
                  Positioned(
                    left: 7,
                    top: 7,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 7, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.94),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            hit.item.isVeg
                                ? Icons.eco_rounded
                                : Icons.restaurant_rounded,
                            color: hit.item.isVeg
                                ? FoodFlowTheme.success
                                : primary,
                            size: 12,
                          ),
                          const SizedBox(width: 3),
                          Text(
                            hit.item.dietLabel,
                            style: const TextStyle(
                              color: _searchText,
                              fontSize: 9.5,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(width: 12),
              Expanded(
                child: SizedBox(
                  height: 132,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              hit.item.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: _searchText,
                                fontSize: 16.5,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                formatCurrency(context, hit.item.finalPrice),
                                style: TextStyle(
                                  color: secondary,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              if (hit.item.hasDiscount)
                                Text(
                                  formatCurrency(context, hit.item.price),
                                  style: const TextStyle(
                                    color: FoodFlowTheme.faint,
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w700,
                                    decoration: TextDecoration.lineThrough,
                                  ),
                                ),
                            ],
                          ),
                        ],
                      ),
                      if (hit.item.hasDiscount) ...[
                        const SizedBox(height: 4),
                        _SearchDiscountBadge(
                          label:
                              '${hit.item.discountPercent.toStringAsFixed(0)}% off',
                          color: secondary,
                        ),
                      ],
                      const SizedBox(height: 4),
                      Text(
                        hit.restaurant.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _searchSubtext,
                          fontSize: 12.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (hit.item.description?.trim().isNotEmpty == true) ...[
                        const SizedBox(height: 5),
                        Text(
                          hit.item.description!.trim(),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.faint,
                            fontSize: 11.2,
                            height: 1.18,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                      if (category.trim().isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          category,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.faint,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                      const Spacer(),
                      Row(
                        children: [
                          _SearchMetricChip(
                              icon: Icons.schedule_rounded,
                              label: hit.restaurant.deliveryEtaLabel),
                        ],
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

  Widget _foodFallback(Color primary) {
    return Container(
      width: 104,
      height: 132,
      color: primary.withOpacity(0.08),
      child: Icon(Icons.fastfood_rounded, size: 34, color: primary),
    );
  }
}

class _SearchDiscountBadge extends StatelessWidget {
  const _SearchDiscountBadge({
    required this.label,
    required this.color,
  });

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: color.withOpacity(0.1),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: color.withOpacity(0.18)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.local_offer_rounded, size: 12, color: color),
            const SizedBox(width: 4),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 10.5,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SearchRestaurantCard extends StatelessWidget {
  const _SearchRestaurantCard({required this.restaurant, required this.onTap});

  final Restaurant restaurant;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final primary = _searchPrimary(context);
    final secondary = _searchSecondary(context);
    final imageUrl = restaurant.bannerUrl.isNotEmpty
        ? restaurant.bannerUrl
        : restaurant.logoUrl;
    final cuisine = restaurant.cuisineText;
    final amount = restaurant.amountForOne;
    final hasRating = restaurant.hasVisibleRating;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Container(
          margin: const EdgeInsets.fromLTRB(16, 0, 16, 14),
          decoration: _searchPanelDecoration(context, radius: 22),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Stack(
                children: [
                  SizedBox(
                    height: 142,
                    width: double.infinity,
                    child: imageUrl.isNotEmpty
                        ? AppCachedImage(
                            imageUrl: imageUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) =>
                                _restaurantFallback(primary),
                          )
                        : _restaurantFallback(primary),
                  ),
                  Positioned.fill(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Colors.transparent,
                            Colors.black.withOpacity(0.46)
                          ],
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    left: 12,
                    right: 12,
                    bottom: 12,
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(
                          child: Text(
                            restaurant.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 19,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 5),
                          decoration: BoxDecoration(
                            color: restaurant.isOpen
                                ? FoodFlowTheme.success
                                : FoodFlowTheme.danger,
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            restaurant.isOpen ? 'OPEN' : 'CLOSED',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        _SearchMetricChip(
                          icon: Icons.star_rounded,
                          label: hasRating
                              ? restaurant.rating.toStringAsFixed(1)
                              : 'New',
                          color: hasRating ? FoodFlowTheme.success : secondary,
                        ),
                        const SizedBox(width: 8),
                        _SearchMetricChip(
                            icon: Icons.schedule_rounded,
                            label: restaurant.deliveryEtaLabel),
                        if (amount != null && amount > 0) ...[
                          const SizedBox(width: 8),
                          _SearchMetricChip(
                            icon: Icons.currency_rupee_rounded,
                            label: '${amount.toStringAsFixed(0)} for one',
                          ),
                        ],
                      ],
                    ),
                    if (cuisine.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Text(
                        cuisine,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: _searchSubtext,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                    if (restaurant.matchedItemNames.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        'Matches ${restaurant.matchedItemNames.take(3).join(', ')}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: primary,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
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
    );
  }

  Widget _restaurantFallback(Color primary) {
    return Container(
      color: primary.withOpacity(0.08),
      child: Icon(Icons.restaurant_rounded, size: 48, color: primary),
    );
  }
}

class _SearchMetricChip extends StatelessWidget {
  const _SearchMetricChip(
      {required this.icon, required this.label, this.color});

  final IconData icon;
  final String label;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final resolvedColor = color ?? _searchText;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: resolvedColor.withOpacity(0.08),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: resolvedColor),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              color: resolvedColor,
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

BoxDecoration _searchPanelDecoration(BuildContext context,
    {double radius = 24}) {
  return BoxDecoration(
    gradient: const LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [
        FoodFlowTheme.surfaceColor,
        FoodFlowTheme.surfaceWarm,
        FoodFlowTheme.surfaceCool,
      ],
      stops: [0, 0.56, 1],
    ),
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: _searchLine),
    boxShadow: [
      BoxShadow(
        color: Colors.white.withOpacity(0.95),
        blurRadius: 3,
        offset: const Offset(-2, -2),
      ),
      BoxShadow(
        color: _searchPrimary(context).withOpacity(0.13),
        blurRadius: 22,
        spreadRadius: -3,
        offset: const Offset(0, 14),
      ),
      BoxShadow(
        color: Colors.black.withOpacity(0.06),
        blurRadius: 24,
        spreadRadius: -4,
        offset: const Offset(0, 14),
      ),
    ],
  );
}

class _SearchFloatingIcon extends StatelessWidget {
  const _SearchFloatingIcon({
    required this.icon,
    required this.color,
  });

  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 54,
      height: 54,
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.18),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Icon(icon, color: color, size: 28),
    );
  }
}

class _SearchScreenState extends State<SearchScreen> {
  final ApiService _api = ApiService();
  final LocationService _locationService = LocationService();
  final TextEditingController _searchController = TextEditingController();
  final FocusNode _focusNode = FocusNode();
  final SpeechToText _speechToText = SpeechToText();

  List<Restaurant> _restaurants = [];
  List<Restaurant> _allResults = [];
  List<_MenuSearchHit> _itemResults = [];
  List<String> _recentSearches = [];
  List<Map<String, dynamic>> _popularSearches = [];
  List<String> _liveSuggestions = [];
  bool _isLoading = false;
  bool _hasSearched = false;
  String _searchQuery = '';
  String? _initialTitle;
  String? _error;
  Timer? _debounceTimer;
  bool _speechEnabled = false;
  bool _isListening = false;
  bool _isSpeechBusy = false;
  bool _speechPermissionDenied = false;
  bool _shouldStartVoiceSearch = false;
  bool _isCategoryBrowse = false;
  bool _isPriceFilterBrowse = false;
  String? _categoryFilter;
  double? _minPriceFilter;
  double? _maxPriceFilter;
  int? _cuisineId;
  int _searchRequestId = 0;

  @override
  void initState() {
    super.initState();
    _loadRecentSearches();
    _loadPopularSearches();
    _initializeSpeechToText();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final initialQuery = ModalRoute.of(context)?.settings.arguments;
      if (initialQuery is Map) {
        final query = initialQuery['query']?.toString().trim() ?? '';
        final title = initialQuery['title']?.toString().trim();
        final category = initialQuery['category']?.toString().trim();
        _cuisineId = int.tryParse(
          initialQuery['cuisine_id']?.toString() ?? '',
        );
        final browseMode = initialQuery['browseMode']?.toString();
        final source = initialQuery['source']?.toString();
        _isCategoryBrowse = browseMode == 'category' || source == 'category';
        _isPriceFilterBrowse =
            browseMode == 'price_filter' || source == 'menu_price_filter';
        _minPriceFilter = _parseNullableDouble(initialQuery['min_price']);
        _maxPriceFilter = _parseNullableDouble(initialQuery['max_price']);
        if (category != null && category.isNotEmpty) {
          _categoryFilter = category;
        }
        _shouldStartVoiceSearch = initialQuery['startVoiceSearch'] == true;
        if (title != null && title.isNotEmpty) {
          _initialTitle = title;
        }
        if (_isPriceFilterBrowse) {
          _searchController.text = title ?? '';
          _loadPriceFilteredItems();
        } else if (query.isNotEmpty) {
          _searchWithQuery(query);
        } else {
          _focusNode.requestFocus();
        }
        if (_shouldStartVoiceSearch) {
          unawaited(_startVoiceSearch());
        }
        return;
      } else if (initialQuery is String && initialQuery.trim().isNotEmpty) {
        _initialTitle = initialQuery.trim();
        _searchWithQuery(initialQuery.trim());
      } else {
        _focusNode.requestFocus();
      }
    });
  }

  double? _parseNullableDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  @override
  void dispose() {
    _debounceTimer?.cancel();
    _speechToText.cancel();
    _searchController.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  Future<void> _initializeSpeechToText() async {
    try {
      final enabled = await _speechToText.initialize(
        onStatus: _handleSpeechStatus,
        onError: _handleSpeechError,
        debugLogging: false,
      );
      if (!mounted) return;
      setState(() {
        _speechEnabled = enabled;
      });
    } catch (e) {
      debugPrint('Speech initialization failed: $e');
      if (!mounted) return;
      setState(() {
        _speechEnabled = false;
      });
    }
  }

  void _handleSpeechStatus(String status) {
    if (!mounted) return;
    final listening = status == 'listening';
    if (_isListening != listening) {
      setState(() {
        _isListening = listening;
      });
    }
  }

  void _handleSpeechError(SpeechRecognitionError error) {
    debugPrint('Speech recognition error: ${error.errorMsg}');
    if (!mounted) return;
    setState(() {
      _isListening = false;
      _isSpeechBusy = false;
    });

    if (error.permanent) {
      _showPermissionSettingsSheet();
      return;
    }

    final message = error.errorMsg == 'error_no_match'
        ? 'No speech detected. Try again.'
        : 'Voice search stopped. ${error.errorMsg.replaceAll('_', ' ')}';
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  Future<bool> _requestSpeechPermissions() async {
    var microphoneStatus = await Permission.microphone.status;
    if (!microphoneStatus.isGranted) {
      microphoneStatus = await Permission.microphone.request();
    }

    PermissionStatus speechStatus = PermissionStatus.granted;
    if (Platform.isIOS) {
      speechStatus = await Permission.speech.status;
      if (!speechStatus.isGranted) {
        speechStatus = await Permission.speech.request();
      }
    }

    final granted = microphoneStatus.isGranted && speechStatus.isGranted;
    if (!mounted) return granted;

    setState(() {
      _speechPermissionDenied = !granted;
    });

    if (granted) return true;

    final permanentlyDenied = microphoneStatus.isPermanentlyDenied ||
        speechStatus.isPermanentlyDenied ||
        speechStatus.isRestricted;

    if (permanentlyDenied) {
      _showPermissionSettingsSheet();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Microphone permission is required for voice search.'),
        ),
      );
    }
    return false;
  }

  Future<void> _startVoiceSearch() async {
    if (_isSpeechBusy) return;
    if (_isListening) {
      await _stopVoiceSearch();
      return;
    }

    setState(() {
      _isSpeechBusy = true;
    });

    final permissionGranted = await _requestSpeechPermissions();
    if (!permissionGranted) {
      if (mounted) {
        setState(() {
          _isSpeechBusy = false;
        });
      }
      return;
    }

    if (!_speechEnabled) {
      await _initializeSpeechToText();
    }

    if (!_speechEnabled) {
      if (mounted) {
        setState(() {
          _isSpeechBusy = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Voice search is not available on this device.'),
          ),
        );
      }
      return;
    }

    try {
      await _speechToText.listen(
        onResult: _handleSpeechResult,
        listenFor: const Duration(seconds: 45),
        pauseFor: const Duration(seconds: 4),
        partialResults: true,
        cancelOnError: true,
        listenMode: ListenMode.search,
      );

      if (!mounted) return;
      setState(() {
        _isListening = true;
        _isSpeechBusy = false;
      });
    } catch (e) {
      debugPrint('Voice search start failed: $e');
      if (!mounted) return;
      setState(() {
        _isListening = false;
        _isSpeechBusy = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Unable to start voice search right now.'),
        ),
      );
    }
  }

  Future<void> _stopVoiceSearch() async {
    await _speechToText.stop();
    if (!mounted) return;
    setState(() {
      _isListening = false;
      _isSpeechBusy = false;
    });
  }

  void _handleSpeechResult(SpeechRecognitionResult result) {
    final recognizedWords = result.recognizedWords.trim();
    if (recognizedWords.isEmpty) return;
    _updateSearchField(
      recognizedWords,
      runSearchNow: result.finalResult,
    );
  }

  void _updateSearchField(String value, {bool runSearchNow = false}) {
    _searchController.value = TextEditingValue(
      text: value,
      selection: TextSelection.collapsed(offset: value.length),
    );
    _onSearchChanged(value);
    if (runSearchNow) {
      _searchRestaurants();
    }
  }

  Future<void> _showPermissionSettingsSheet() async {
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Allow microphone access',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 10),
              const Text(
                'Turn on microphone permission in app settings to use voice search.',
                style: TextStyle(fontSize: 14),
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    Navigator.pop(context);
                    await openAppSettings();
                  },
                  child: const Text('Open Settings'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _loadRecentSearches() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final searches = prefs.getStringList('recent_searches');
      if (searches != null && searches.isNotEmpty) {
        setState(() {
          _recentSearches = searches;
        });
      }
    } catch (e) {
      debugPrint('Error loading recent searches: $e');
    }
  }

  void _saveRecentSearch(String query) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      List<String> searches = prefs.getStringList('recent_searches') ?? [];
      searches.remove(query);
      searches.insert(0, query);
      if (searches.length > 10) {
        searches = searches.take(10).toList();
      }
      await prefs.setStringList('recent_searches', searches);
      setState(() {
        _recentSearches = searches;
      });
    } catch (e) {
      debugPrint('Error saving recent search: $e');
    }
  }

  void _clearRecentSearches() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('recent_searches');
      setState(() {
        _recentSearches = [];
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Recent searches cleared'),
          backgroundColor: FoodFlowTheme.success,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (e) {
      debugPrint('Error clearing recent searches: $e');
    }
  }

  void _removeRecentSearch(String search) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      List<String> searches = prefs.getStringList('recent_searches') ?? [];
      searches.remove(search);
      await prefs.setStringList('recent_searches', searches);
      setState(() {
        _recentSearches = searches;
      });
    } catch (e) {
      debugPrint('Error removing recent search: $e');
    }
  }

  Future<void> _loadPopularSearches() async {
    try {
      final response = await _api
          .get(ApiConstants.popularCuisines)
          .timeout(const Duration(seconds: 8));
      final rawItems = _extractGenericList(response);
      final searches = rawItems
          .map((item) {
            final name = _firstSearchText(
              item,
              const ['name', 'title', 'cuisine_name', 'category_name'],
            );
            if (name.isEmpty) return null;
            final imageUrl = _resolveSearchImageUrl(item, const [
              'image_url',
              'icon_url',
              'thumbnail_url',
              'thumb_url',
              'photo_url',
              'asset_url',
              'media_url',
              'image',
              'icon',
              'thumbnail',
              'thumb',
              'photo',
              'url',
              'images',
              'media',
            ]);
            return <String, dynamic>{
              'name': name,
              'icon': _popularSearchIcon(name),
              if (imageUrl.isNotEmpty) 'image_url': imageUrl,
            };
          })
          .whereType<Map<String, dynamic>>()
          .take(12)
          .toList(growable: false);
      if (!mounted) return;
      setState(() {
        _popularSearches = searches;
      });
    } catch (e) {
      debugPrint('Popular searches load failed: $e');
      if (!mounted) return;
      setState(() {
        _popularSearches = [];
      });
    }
  }

  IconData _popularSearchIcon(String name) {
    final normalized = name.toLowerCase();
    if (normalized.contains('pizza')) return Icons.local_pizza;
    if (normalized.contains('burger')) return Icons.fastfood;
    if (normalized.contains('biryani') || normalized.contains('rice')) {
      return Icons.rice_bowl;
    }
    if (normalized.contains('dessert') || normalized.contains('cake')) {
      return Icons.cake;
    }
    if (normalized.contains('coffee') || normalized.contains('tea')) {
      return Icons.local_cafe;
    }
    if (normalized.contains('drink') || normalized.contains('juice')) {
      return Icons.local_drink;
    }
    return Icons.restaurant_menu;
  }

  String _firstSearchText(Map<String, dynamic> item, List<String> keys) {
    for (final key in keys) {
      final value = item[key];
      if (value is String && value.trim().isNotEmpty) return value.trim();
      if (value is num) return value.toString();
    }

    for (final key in const ['restaurant', 'store', 'vendor', 'merchant']) {
      final nested = item[key];
      if (nested is Map) {
        final value = _firstSearchText(Map<String, dynamic>.from(nested), keys);
        if (value.isNotEmpty) return value;
      }
    }
    return '';
  }

  String _resolveSearchImageUrl(dynamic item, List<String> keys) {
    String resolve(dynamic value) {
      if (value is String && value.trim().isNotEmpty) return value.trim();
      if (value is Map) {
        final map = Map<String, dynamic>.from(value);
        for (final key in const [
          'url',
          'image_url',
          'icon_url',
          'path',
          'file',
          'src',
          'image',
          'icon',
        ]) {
          final resolved = resolve(map[key]);
          if (resolved.isNotEmpty) return resolved;
        }
      }
      if (value is List) {
        for (final child in value) {
          final resolved = resolve(child);
          if (resolved.isNotEmpty) return resolved;
        }
      }
      return '';
    }

    if (item is! Map) return resolve(item);
    final map = Map<String, dynamic>.from(item);
    for (final key in keys) {
      final resolved = resolve(map[key]);
      if (resolved.isNotEmpty) return resolved;
    }
    for (final key in const ['restaurant', 'store', 'vendor', 'merchant']) {
      final resolved = _resolveSearchImageUrl(map[key], keys);
      if (resolved.isNotEmpty) return resolved;
    }
    return '';
  }

  List<Map<String, dynamic>> _extractGenericList(dynamic response) {
    if (response is List) {
      return response
          .whereType<Map>()
          .map((item) => _restaurantMapFromSearchItem(
                Map<String, dynamic>.from(item),
              ))
          .whereType<Map<String, dynamic>>()
          .toList(growable: false);
    }
    if (response is! Map) return const <Map<String, dynamic>>[];
    for (final candidate in <dynamic>[
      response['data'],
      response['items'],
      response['results'],
    ]) {
      if (candidate is List) {
        return candidate
            .whereType<Map>()
            .map((item) => _restaurantMapFromSearchItem(
                  Map<String, dynamic>.from(item),
                ))
            .whereType<Map<String, dynamic>>()
            .toList(growable: false);
      }
      if (candidate is Map) {
        for (final key in const ['data', 'items', 'results']) {
          final nested = candidate[key];
          if (nested is List) {
            return nested
                .whereType<Map>()
                .map((item) => Map<String, dynamic>.from(item))
                .toList(growable: false);
          }
        }
      }
    }
    return const <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> _extractRestaurantMaps(dynamic response) {
    if (response is List) {
      return response
          .whereType<Map>()
          .map((item) => _restaurantMapFromSearchItem(
                Map<String, dynamic>.from(item),
              ))
          .whereType<Map<String, dynamic>>()
          .toList(growable: false);
    }

    if (response is! Map<String, dynamic>) {
      return const <Map<String, dynamic>>[];
    }

    final candidates = <dynamic>[
      response['data'],
      response['restaurants'],
      response['results'],
      response['items'],
    ];

    for (final candidate in candidates) {
      if (candidate is List) {
        return candidate
            .whereType<Map>()
            .map((item) => _restaurantMapFromSearchItem(
                  Map<String, dynamic>.from(item),
                ))
            .whereType<Map<String, dynamic>>()
            .toList(growable: false);
      }
      if (candidate is Map) {
        for (final key in const ['restaurants', 'data', 'results', 'items']) {
          final nested = candidate[key];
          if (nested is List) {
            return nested
                .whereType<Map>()
                .map((item) => _restaurantMapFromSearchItem(
                      Map<String, dynamic>.from(item),
                    ))
                .whereType<Map<String, dynamic>>()
                .toList(growable: false);
          }
        }
      }
    }

    return const <Map<String, dynamic>>[];
  }

  bool _looksLikeMenuResultItem(Map<String, dynamic> item) {
    final type = (item['type'] ?? item['entity_type'] ?? item['source'])
        ?.toString()
        .toLowerCase()
        .trim();
    if (type != null &&
        (type.contains('menu') ||
            type.contains('food') ||
            type.contains('dish') ||
            type.contains('item'))) {
      return !type.contains('restaurant');
    }

    final hasMenuIdentity = item['menu_item_id'] != null ||
        item['food_id'] != null ||
        item['dish_id'] != null ||
        item['item_id'] != null;
    final hasMenuPrice = item.containsKey('price') ||
        item.containsKey('discounted_price') ||
        item.containsKey('discount_price') ||
        item.containsKey('final_price') ||
        item.containsKey('actual_price') ||
        item.containsKey('original_price') ||
        item.containsKey('sale_price') ||
        item.containsKey('offer_price');
    final hasMenuMetadata = item.containsKey('restaurant_id') ||
        item.containsKey('category_id') ||
        item.containsKey('category_name') ||
        item.containsKey('cuisine_id') ||
        item.containsKey('cuisine_name') ||
        item.containsKey('is_veg') ||
        item.containsKey('food_type') ||
        item.containsKey('preparation_time');
    final hasRestaurantIdentity = item.containsKey('restaurant_name') ||
        item.containsKey('store_name') ||
        item.containsKey('business_name') ||
        item.containsKey('vendor_name') ||
        item.containsKey('merchant_name') ||
        item.containsKey('delivery_time') ||
        item.containsKey('delivery_fee') ||
        item.containsKey('address');

    return hasMenuIdentity ||
        (hasMenuPrice && hasMenuMetadata && !hasRestaurantIdentity);
  }

  Map<String, dynamic>? _restaurantMapFromSearchItem(
    Map<String, dynamic> item,
  ) {
    final nestedRestaurant = item['restaurant'];
    if (_looksLikeMenuResultItem(item)) {
      if (nestedRestaurant is Map) {
        return _restaurantMapFromSearchItem(
          Map<String, dynamic>.from(nestedRestaurant),
        );
      }
      return null;
    }

    final type = (item['type'] ?? item['entity_type'])?.toString();
    if (item['name'] != null && type != 'menu_item' && type != 'food') {
      return item;
    }
    if (type != 'restaurant' && item['entity_type'] != 'restaurant') {
      return item;
    }

    final name = _firstSearchText(item, const [
      'restaurant_name',
      'restaurantName',
      'store_name',
      'storeName',
      'business_name',
      'businessName',
      'vendor_name',
      'vendorName',
      'merchant_name',
      'merchantName',
      'title',
      'name',
    ]);
    final logoImage = _resolveSearchImageUrl(item, const [
      'restaurant_logo',
      'restaurant_logo_url',
      'logo_image',
      'logo',
      'image_url',
      'image',
      'photo',
      'thumbnail_url',
    ]);
    final bannerImage = _resolveSearchImageUrl(item, const [
      'restaurant_banner',
      'restaurant_banner_url',
      'banner_image',
      'banner_url',
      'cover_image',
      'image_url',
      'image',
      'photo',
    ]);

    return <String, dynamic>{
      ...item,
      'id': item['restaurant_id'] ?? item['entity_id'] ?? item['id'],
      'name': name.isNotEmpty ? name : 'Restaurant',
      'description': item['description'],
      'latitude': item['latitude'],
      'longitude': item['longitude'],
      'cuisine': item['tags'] ?? const <dynamic>[],
      if (logoImage.isNotEmpty) 'logo_image': logoImage,
      if (bannerImage.isNotEmpty) 'banner_image': bannerImage,
      if (logoImage.isNotEmpty && item['image'] == null) 'image': logoImage,
      'is_open': true,
      'is_verified': true,
      'delivery_fee': 0,
      'delivery_time': 30,
      'rating': 0,
      'total_ratings': 0,
      'created_at': DateTime.now().toIso8601String(),
    };
  }

  List<String> _extractSuggestions(dynamic response, String query) {
    if (response is Map<String, dynamic> && response['suggestions'] is List) {
      return List<String>.from(
        (response['suggestions'] as List)
            .map((item) => item.toString().trim())
            .where((item) => item.isNotEmpty),
      );
    }

    return query.trim().isEmpty ? <String>[] : <String>[query];
  }

  bool _isSuccessfulSearchResponse(
    dynamic response,
    List<Map<String, dynamic>> extractedRestaurants,
  ) {
    if (response is List) return true;
    if (response is! Map<String, dynamic>) return false;

    final success = response['success'];
    if (success == true) return true;
    if (success == false) return false;

    final status = response['status']?.toString().toLowerCase().trim();
    if (status == 'success' || status == 'ok') return true;
    if (status == 'error' || status == 'failed' || status == 'failure') {
      return false;
    }

    if (extractedRestaurants.isNotEmpty) return true;
    if (response['suggestions'] is List) return true;
    if (response.containsKey('data') ||
        response.containsKey('restaurants') ||
        response.containsKey('results') ||
        response.containsKey('items')) {
      return true;
    }

    return false;
  }

  Future<List<Restaurant>> _loadNearbyRestaurantsForSearch(
    Map<String, dynamic>? savedLocation, {
    bool forceRefresh = false,
  }) async {
    if (savedLocation == null) return const <Restaurant>[];
    final lat = savedLocation['lat'];
    final lng = savedLocation['lng'];
    if (lat is! num || lng is! num) return const <Restaurant>[];

    try {
      final response = await _api
          .get(
            ApiConstants.nearbyRestaurants,
            queryParams: {
              'lat': lat.toDouble(),
              'lng': lng.toDouble(),
              'radius': 100,
            },
            includeAuth: false,
            cachePolicy: ApiCachePolicy.discovery,
            cacheFirst: !forceRefresh,
            refreshCached: !forceRefresh,
          )
          .timeout(const Duration(seconds: 15));
      return _extractRestaurantMaps(response)
          .map((json) {
            try {
              return Restaurant.fromJson(json);
            } catch (e) {
              debugPrint('Error parsing nearby search restaurant: $e');
              return null;
            }
          })
          .whereType<Restaurant>()
          .toList(growable: false);
    } catch (e) {
      debugPrint('Nearby restaurant lookup for search failed: $e');
      return const <Restaurant>[];
    }
  }

  Future<List<Map<String, dynamic>>> _fallbackNearbySearch(
      String query, Map<String, dynamic>? savedLocation) async {
    if (savedLocation == null) return const <Map<String, dynamic>>[];
    final lat = savedLocation['lat'];
    final lng = savedLocation['lng'];
    if (lat is! num || lng is! num) {
      return const <Map<String, dynamic>>[];
    }

    final response = await _api.get(
      ApiConstants.nearbyRestaurants,
      queryParams: {
        'lat': lat.toDouble(),
        'lng': lng.toDouble(),
        'radius': 100,
      },
    ).timeout(const Duration(seconds: 15));

    final results = _extractRestaurantMaps(response);
    final queryLower = _normalizedCategoryQuery(query);
    return results.where((restaurant) {
      return _restaurantMatchesCategoryMap(restaurant, queryLower) ||
          (!_isCategoryBrowse &&
              _restaurantMatchesTextMap(restaurant, queryLower));
    }).toList(growable: false);
  }

  Future<void> _loadPriceFilteredItems({bool forceRefresh = false}) async {
    final requestId = ++_searchRequestId;
    final minPrice = _minPriceFilter ?? 0;
    final maxPrice = _maxPriceFilter;
    final title = _initialTitle?.trim().isNotEmpty == true
        ? _initialTitle!.trim()
        : maxPrice != null
            ? 'Under ${formatCurrency(context, maxPrice)}'
            : 'Filtered items';

    setState(() {
      _isLoading = true;
      _hasSearched = true;
      _searchQuery = title;
      _error = null;
      _restaurants = [];
      _allResults = [];
      _itemResults = [];
      _liveSuggestions = [];
    });

    try {
      final savedLocation = await _locationService.getSavedLocation();
      if (savedLocation == null) {
        throw Exception('Select a delivery location to view filtered items.');
      }
      final lat = savedLocation['lat'];
      final lng = savedLocation['lng'];
      if (lat is! num || lng is! num) {
        throw Exception('Select a delivery location to view filtered items.');
      }

      final response = await _api
          .get(
            ApiConstants.nearbyRestaurants,
            queryParams: {
              'lat': lat.toDouble(),
              'lng': lng.toDouble(),
              'radius': 100,
            },
            cachePolicy: ApiCachePolicy.discovery,
            cacheFirst: !forceRefresh,
            refreshCached: !forceRefresh,
          )
          .timeout(const Duration(seconds: 15));

      if (!mounted || requestId != _searchRequestId) return;

      final restaurants = _extractRestaurantMaps(response)
          .map((json) {
            try {
              return Restaurant.fromJson(json);
            } catch (_) {
              return null;
            }
          })
          .whereType<Restaurant>()
          .toList(growable: false);

      final hits = <_MenuSearchHit>[];
      final seenItems = <String>{};

      Future<List<_MenuSearchHit>> loadRestaurantItems(
          Restaurant restaurant) async {
        try {
          final menuResponse = await _api
              .get('${ApiConstants.restaurantDetails}/${restaurant.id}/menu')
              .timeout(const Duration(seconds: 10));
          final payload = menuResponse is Map
              ? Map<String, dynamic>.from(menuResponse)
              : <String, dynamic>{'data': menuResponse};
          final rawItems = _extractMenuItemMaps(payload);
          return _parseMenuItems(rawItems, restaurant.id)
              .where((item) {
                final price = item.finalPrice;
                return item.isAvailable &&
                    price > 0 &&
                    price >= minPrice &&
                    (maxPrice == null || price <= maxPrice);
              })
              .map((item) => _MenuSearchHit(restaurant: restaurant, item: item))
              .toList(growable: false);
        } catch (_) {
          return const <_MenuSearchHit>[];
        }
      }

      final grouped = await Future.wait(
        restaurants.take(24).map(loadRestaurantItems),
      );

      if (!mounted || requestId != _searchRequestId) return;

      for (final restaurantHits in grouped) {
        for (final hit in restaurantHits) {
          final key = '${hit.restaurant.id}:${hit.item.id}';
          if (seenItems.add(key)) hits.add(hit);
        }
      }
      hits.sort((a, b) {
        final priceCompare = a.item.finalPrice.compareTo(b.item.finalPrice);
        if (priceCompare != 0) return priceCompare;
        return b.item.totalOrders.compareTo(a.item.totalOrders);
      });

      setState(() {
        _itemResults = hits;
        _restaurants = const <Restaurant>[];
        _allResults = const <Restaurant>[];
        _error = null;
      });
      unawaited(AppImageCache.precacheVisible(
        context,
        hits.map((hit) => hit.item.imageUrl).toList(growable: false),
      ));
    } catch (e) {
      if (!mounted || requestId != _searchRequestId) return;
      setState(() {
        _error = e.toString().replaceFirst('Exception: ', '');
        _itemResults = [];
        _restaurants = [];
        _allResults = [];
      });
    }

    if (mounted && requestId == _searchRequestId) {
      setState(() => _isLoading = false);
    }
  }

  String _normalizedCategoryQuery(String query) {
    final value = (_categoryFilter ?? query).trim().toLowerCase();
    return value;
  }

  bool _containsLoose(String haystack, String needle) {
    final cleanHaystack =
        haystack.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), ' ');
    final cleanNeedle =
        needle.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), ' ');
    return cleanHaystack.contains(cleanNeedle.trim());
  }

  bool _restaurantMatchesCategoryMap(
      Map<String, dynamic> restaurant, String queryLower) {
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
    return values.any((value) => _containsLoose(value, queryLower));
  }

  bool _restaurantMatchesTextMap(
      Map<String, dynamic> restaurant, String queryLower) {
    final values = <String>[
      restaurant['name']?.toString() ?? '',
      restaurant['city']?.toString() ?? '',
    ];
    values.addAll([
      restaurant['cuisine_text'],
      restaurant['cuisine_names'],
      restaurant['cuisines'],
      restaurant['cuisine'],
    ].expand((value) {
      if (value is List) {
        return value.map((item) {
          if (item is Map) {
            return (item['name'] ?? item['title'] ?? item['cuisine_name'] ?? '')
                .toString();
          }
          final text = item?.toString().trim() ?? '';
          return int.tryParse(text) == null ? text : '';
        });
      }
      if (value is String) {
        return value
            .split(',')
            .map((item) => item.trim())
            .where((item) => item.isNotEmpty && int.tryParse(item) == null);
      }
      return const <String>[];
    }));
    return values.any((value) => _containsLoose(value, queryLower));
  }

  bool _restaurantMatchesCategory(Restaurant restaurant, String queryLower) {
    return _containsLoose(restaurant.cuisineText, queryLower) ||
        restaurant.matchedItemNames
            .any((item) => _containsLoose(item, queryLower));
  }

  Future<void> _searchRestaurants({bool forceRefresh = false}) async {
    final query = _searchQuery.trim();
    final requestId = ++_searchRequestId;

    if (query.isEmpty) {
      setState(() {
        _restaurants = [];
        _allResults = [];
        _itemResults = [];
        _hasSearched = false;
        _error = null;
        _liveSuggestions = [];
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _error = null;
      _hasSearched = true;
    });

    try {
      // Save the search query
      _saveRecentSearch(query);

      // Get saved location for better results
      final savedLocation = await _locationService.getSavedLocation();
      final Map<String, dynamic> queryParams = {
        'keyword': query,
        'q': query,
        'query': query,
        'delivery_zone_only': true,
        'type': _isCategoryBrowse ? 'category' : 'all',
        if (_categoryFilter != null && _categoryFilter!.isNotEmpty)
          'category': _categoryFilter,
        if (_categoryFilter != null && _categoryFilter!.isNotEmpty)
          'cuisine': _categoryFilter,
        if (_cuisineId != null && _cuisineId! > 0) 'cuisine_id': _cuisineId,
      };

      // Add location if available for better results
      if (savedLocation != null) {
        final lat = savedLocation['lat'];
        final lng = savedLocation['lng'];
        if (lat is num && lng is num) {
          queryParams['lat'] = lat.toDouble();
          queryParams['lng'] = lng.toDouble();
          queryParams['radius'] = 100;
        }
      }

      debugPrint(
          'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Searching restaurants with query: "$query"');
      debugPrint(
          'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Search params: $queryParams');

      // Try search endpoint
      dynamic response;
      try {
        response = await _api.get(
          ApiConstants.advancedSearch,
          queryParams: queryParams,
          includeAuth: false,
          cachePolicy: ApiCachePolicy.discovery,
          cacheFirst: !forceRefresh,
          refreshCached: !forceRefresh,
          onCacheRefreshed: (_) {
            if (mounted && query == _searchQuery.trim()) {
              _searchRestaurants(forceRefresh: true);
            }
          },
        ).timeout(const Duration(seconds: 15));
        debugPrint(
            'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã¢â‚¬Å“ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦ Search API response received');
      } catch (e) {
        debugPrint(
            'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ Search endpoint failed: $e');
        final fallback = await _fallbackNearbySearch(query, savedLocation);
        response = <String, dynamic>{
          'success': true,
          'data': fallback,
          'menu_items': const <dynamic>[],
          'suggestions': <String>[query],
        };
        debugPrint('Using delivery-zone nearby search fallback');
      }

      if (!mounted ||
          requestId != _searchRequestId ||
          query != _searchQuery.trim()) {
        return;
      }

      if (response is Map<String, dynamic> || response is List) {
        var dataList = _extractRestaurantMaps(response);
        final isSuccess = _isSuccessfulSearchResponse(response, dataList);

        if (isSuccess) {
          if (!mounted ||
              requestId != _searchRequestId ||
              query != _searchQuery.trim()) {
            return;
          }

          var parsedRestaurants = dataList
              .map((json) {
                try {
                  return Restaurant.fromJson(json);
                } catch (e) {
                  debugPrint('Error parsing restaurant: $e');
                  return null;
                }
              })
              .whereType<Restaurant>()
              .toList();
          if (parsedRestaurants.isEmpty) {
            parsedRestaurants = await _loadNearbyRestaurantsForSearch(
              savedLocation,
              forceRefresh: forceRefresh,
            );
            if (!mounted ||
                requestId != _searchRequestId ||
                query != _searchQuery.trim()) {
              return;
            }
          }
          final queryLower = _normalizedCategoryQuery(query);
          final filteredRestaurants = parsedRestaurants.where((restaurant) {
            if (_isCategoryBrowse) {
              if (_cuisineId != null && _cuisineId! > 0) return true;
              return _restaurantMatchesCategory(restaurant, queryLower);
            }
            return _containsLoose(restaurant.name, queryLower) ||
                _containsLoose(restaurant.cuisineText, queryLower) ||
                _containsLoose(restaurant.city, queryLower) ||
                _containsLoose(restaurant.address, queryLower) ||
                restaurant.matchedItemNames.any(
                  (item) => _containsLoose(item, queryLower),
                );
          }).toList();
          final directItemResults = <_MenuSearchHit>[
            ..._itemResultsFromSearchResponse(
              response,
              parsedRestaurants,
              query,
            ),
            ..._itemResultsFromSearchPayload(
              dataList,
              parsedRestaurants,
              query,
            ),
          ];
          final seenItems = <String>{};
          var itemResults = <_MenuSearchHit>[
            ...directItemResults,
          ].where((hit) {
            return seenItems.add('${hit.restaurant.id}:${hit.item.id}');
          }).toList(growable: false);

          if (itemResults.isEmpty && !_isCategoryBrowse) {
            var menuSearchRestaurants = parsedRestaurants;
            if (menuSearchRestaurants.isEmpty && savedLocation != null) {
              try {
                final lat = savedLocation['lat'];
                final lng = savedLocation['lng'];
                if (lat is num && lng is num) {
                  final nearbyResponse = await _api
                      .get(
                        ApiConstants.nearbyRestaurants,
                        queryParams: {
                          'lat': lat.toDouble(),
                          'lng': lng.toDouble(),
                          'radius': 100,
                        },
                        includeAuth: false,
                        cachePolicy: ApiCachePolicy.discovery,
                        cacheFirst: !forceRefresh,
                        refreshCached: !forceRefresh,
                      )
                      .timeout(const Duration(seconds: 15));
                  menuSearchRestaurants = _extractRestaurantMaps(nearbyResponse)
                      .map((json) {
                        try {
                          return Restaurant.fromJson(json);
                        } catch (e) {
                          debugPrint('Error parsing nearby restaurant: $e');
                          return null;
                        }
                      })
                      .whereType<Restaurant>()
                      .toList(growable: false);
                }
              } catch (e) {
                debugPrint('Nearby menu search fallback failed: $e');
              }
            }

            if (!mounted ||
                requestId != _searchRequestId ||
                query != _searchQuery.trim()) {
              return;
            }

            if (menuSearchRestaurants.isNotEmpty) {
              itemResults = await _findMatchingMenuItems(
                menuSearchRestaurants.take(24).toList(growable: false),
                query,
                requestId,
              );
            }
          }

          final seenRestaurantIds = <int>{};
          final results = <Restaurant>[
            ...filteredRestaurants,
            ...itemResults.map((hit) => hit.restaurant),
          ]
              .where((restaurant) => seenRestaurantIds.add(restaurant.id))
              .toList();
          results.sort((a, b) {
            final aItemMatch = _restaurantMatchesCategory(a, queryLower);
            final bItemMatch = _restaurantMatchesCategory(b, queryLower);
            if (aItemMatch != bItemMatch) {
              return bItemMatch ? 1 : -1;
            }
            return b.reviewCount.compareTo(a.reviewCount);
          });
          final suggestions = _extractSuggestions(response, query);

          if (!mounted ||
              requestId != _searchRequestId ||
              query != _searchQuery.trim()) {
            return;
          }

          debugPrint(
              'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â°ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â  Total found: ${dataList.length}, Filtered: ${filteredRestaurants.length}');

          setState(() {
            _restaurants = results;
            _allResults = List<Restaurant>.from(results);
            _itemResults = itemResults;
            _error = null;
            _liveSuggestions = suggestions;
          });
          unawaited(AppImageCache.precacheVisible(
            context,
            <String>[
              ...itemResults.map((hit) => hit.item.imageUrl),
              ...results.map((restaurant) => restaurant.logoUrl),
            ],
          ));
        } else {
          final message =
              response['message'] ?? response['error'] ?? 'Search failed';
          setState(() {
            _restaurants = [];
            _allResults = [];
            _itemResults = [];
            _error = message.toString();
            _liveSuggestions = [];
          });
        }
      } else {
        setState(() {
          _restaurants = [];
          _allResults = [];
          _itemResults = [];
          _error = 'Invalid response format from server';
          _liveSuggestions = [];
        });
      }
    } catch (e) {
      debugPrint('Search error: $e');
      if (!mounted ||
          requestId != _searchRequestId ||
          query != _searchQuery.trim()) {
        return;
      }
      setState(() {
        _error = 'Unable to search. Please check your internet connection.';
        _restaurants = [];
        _allResults = [];
        _itemResults = [];
        _liveSuggestions = [];
      });
    }

    if (mounted && requestId == _searchRequestId) {
      setState(() => _isLoading = false);
    }
  }

  void _onSearchChanged(String value) {
    setState(() {
      _searchQuery = value;
      if (value.isNotEmpty) {
        _initialTitle = null;
        if (_isPriceFilterBrowse) {
          _isPriceFilterBrowse = false;
          _minPriceFilter = null;
          _maxPriceFilter = null;
        }
      }
      if (_isCategoryBrowse &&
          value.trim().toLowerCase() !=
              (_categoryFilter ?? '').trim().toLowerCase()) {
        _isCategoryBrowse = false;
        _categoryFilter = null;
        _cuisineId = null;
      }
    });

    // Debounce search to avoid too many API calls
    if (_debounceTimer?.isActive ?? false) _debounceTimer?.cancel();

    if (value.isNotEmpty && value.length >= 2) {
      _debounceTimer = Timer(const Duration(milliseconds: 500), () {
        _searchRestaurants();
      });
    } else if (value.isEmpty) {
      setState(() {
        _restaurants = [];
        _itemResults = [];
        _hasSearched = false;
        _error = null;
        _liveSuggestions = [];
      });
    }
  }

  void _clearSearch() {
    if (_isListening) {
      unawaited(_stopVoiceSearch());
    }
    _searchController.clear();
    setState(() {
      _searchQuery = '';
      _restaurants = [];
      _allResults = [];
      _itemResults = [];
      _hasSearched = false;
      _error = null;
      _liveSuggestions = [];
    });
    _focusNode.requestFocus();
  }

  void _searchWithQuery(String query) {
    _updateSearchField(query, runSearchNow: true);
  }

  double _visibleRating(Restaurant restaurant) {
    return restaurant.reviewCount >= 3 ? restaurant.rating : -1;
  }

  List<_MenuSearchHit> _itemResultsFromSearchPayload(
    List<Map<String, dynamic>> restaurantPayloads,
    List<Restaurant> restaurants,
    String query,
  ) {
    final queryLower = _normalizedCategoryQuery(query);
    final restaurantsById = {
      for (final restaurant in restaurants) restaurant.id: restaurant,
    };
    final hits = <_MenuSearchHit>[];

    for (final payload in restaurantPayloads) {
      final restaurantId = payload['id'] is int
          ? payload['id'] as int
          : int.tryParse(payload['id']?.toString() ?? '') ?? 0;
      final restaurant = restaurantsById[restaurantId];
      if (restaurant == null) continue;

      final rawItems = _extractMenuItemMaps(payload);
      if (rawItems.isEmpty) continue;

      for (final item in _parseMenuItems(rawItems, restaurant.id)) {
        final categoryMatch =
            _containsLoose(item.categoryName ?? '', queryLower) ||
                _containsLoose(item.cuisineName ?? '', queryLower);
        final itemMatch = _containsLoose(item.name, queryLower) ||
            _containsLoose(item.description ?? '', queryLower) ||
            categoryMatch;
        if (itemMatch) {
          hits.add(_MenuSearchHit(restaurant: restaurant, item: item));
        }
      }
    }

    hits.sort((a, b) => b.item.totalOrders.compareTo(a.item.totalOrders));
    return hits;
  }

  List<_MenuSearchHit> _itemResultsFromSearchResponse(
    dynamic response,
    List<Restaurant> restaurants,
    String query,
  ) {
    if (response is! Map) return const <_MenuSearchHit>[];

    final restaurantsById = {
      for (final restaurant in restaurants) restaurant.id: restaurant,
    };
    final rawItems = <Map<String, dynamic>>[];
    final data = response['data'];
    if (data is Map) {
      for (final key in const [
        'foods',
        'menu_items',
        'menuItems',
        'matched_menu_items',
        'matchedMenuItems',
        'search_items',
        'searchItems',
      ]) {
        final value = data[key];
        if (value is List) {
          rawItems.addAll(
            value
                .whereType<Map>()
                .map((item) => Map<String, dynamic>.from(item)),
          );
        }
      }
    }
    for (final key in const [
      'foods',
      'menu_items',
      'menuItems',
      'matched_menu_items',
      'matchedMenuItems',
      'search_items',
      'searchItems',
    ]) {
      final value = response[key];
      if (value is List) {
        rawItems.addAll(
          value.whereType<Map>().map((item) => Map<String, dynamic>.from(item)),
        );
      }
    }

    final queryLower = _normalizedCategoryQuery(query);
    final hits = <_MenuSearchHit>[];
    for (final rawItem in rawItems) {
      final restaurantId = int.tryParse(
            (rawItem['restaurant_id'] ??
                        (rawItem['restaurant'] is Map
                            ? (rawItem['restaurant'] as Map)['id']
                            : null))
                    ?.toString() ??
                '',
          ) ??
          0;
      Restaurant? restaurant = restaurantsById[restaurantId];
      final rawRestaurant = rawItem['restaurant'];
      if (restaurant == null && rawRestaurant is Map) {
        try {
          restaurant = Restaurant.fromJson(
            Map<String, dynamic>.from(rawRestaurant),
          );
        } catch (_) {}
      }
      if (restaurant == null && restaurantId > 0) {
        final fallbackName = _firstSearchText(rawItem, const [
          'restaurant_name',
          'restaurantName',
          'store_name',
          'storeName',
          'business_name',
          'businessName',
          'vendor_name',
          'vendorName',
          'merchant_name',
          'merchantName',
          'brand_name',
          'title',
        ]);
        final fallbackLogo = _resolveSearchImageUrl(rawItem, const [
          'restaurant_logo',
          'restaurant_logo_url',
          'logo_image',
          'logo',
          'store_logo',
          'brand_logo',
          'image_url',
          'image',
          'photo',
          'thumbnail_url',
        ]);
        final fallbackBanner = _resolveSearchImageUrl(rawItem, const [
          'restaurant_banner',
          'restaurant_banner_url',
          'banner_image',
          'banner_url',
          'cover_image',
          'restaurant_image',
          'restaurant_image_url',
          'image_url',
          'image',
          'photo',
        ]);
        restaurant = Restaurant.fromJson(<String, dynamic>{
          'id': restaurantId,
          'name': fallbackName.isNotEmpty ? fallbackName : 'Restaurant',
          if (fallbackLogo.isNotEmpty) 'logo_image': fallbackLogo,
          if (fallbackBanner.isNotEmpty) 'banner_image': fallbackBanner,
          if (fallbackLogo.isNotEmpty) 'image': fallbackLogo,
          'is_open': true,
          'is_verified': true,
          'delivery_fee': 0,
          'delivery_time': 30,
          'created_at': DateTime.now().toIso8601String(),
        });
      }
      if (restaurant == null) continue;

      for (final item in _parseMenuItems([rawItem], restaurant.id)) {
        if (item.isAvailable && _menuItemMatchesQuery(item, queryLower)) {
          hits.add(_MenuSearchHit(restaurant: restaurant, item: item));
        }
      }
    }

    hits.sort((a, b) => b.item.totalOrders.compareTo(a.item.totalOrders));
    return hits;
  }

  Future<List<_MenuSearchHit>> _findMatchingMenuItems(
    List<Restaurant> restaurants,
    String query,
    int requestId,
  ) async {
    final queryLower = _normalizedCategoryQuery(query);
    final topRestaurants = restaurants.toList(growable: false);
    final seen = <String>{};
    final hits = <_MenuSearchHit>[];

    Future<List<_MenuSearchHit>> searchRestaurantMenu(
        Restaurant restaurant) async {
      try {
        final searchResponse = await _api.get(
          '${ApiConstants.restaurantDetails}/${restaurant.id}/menu/search',
          queryParams: {'query': query},
        ).timeout(const Duration(seconds: 6));
        final searchRawItems = _extractMenuItemMaps(
          Map<String, dynamic>.from(searchResponse as Map),
        );
        var items = _parseMenuItems(searchRawItems, restaurant.id)
            .where((item) => _menuItemMatchesQuery(item, queryLower))
            .toList(growable: false);

        if (items.isEmpty) {
          final response = await _api
              .get('${ApiConstants.restaurantDetails}/${restaurant.id}/menu')
              .timeout(const Duration(seconds: 10));
          final menuData = response['data'] is Map<String, dynamic>
              ? response['data'] as Map<String, dynamic>
              : response;
          final rawItems = _extractMenuItemMaps(
            Map<String, dynamic>.from(menuData as Map),
          );
          items = _parseMenuItems(rawItems, restaurant.id)
              .where((item) => _menuItemMatchesQuery(item, queryLower))
              .toList(growable: false);
        }

        if (items.isEmpty) {
          return const <_MenuSearchHit>[];
        }

        debugPrint(
          'Search menu ${restaurant.id}: ${items.length} parsed items for "$query"',
        );

        return items
            .where((item) => item.isAvailable)
            .map((item) => _MenuSearchHit(restaurant: restaurant, item: item))
            .toList(growable: false);
      } catch (_) {
        return const <_MenuSearchHit>[];
      }
    }

    final groupedResults = await Future.wait(
      topRestaurants.map(searchRestaurantMenu),
    );

    if (!mounted || requestId != _searchRequestId) {
      return const <_MenuSearchHit>[];
    }

    for (final restaurantHits in groupedResults) {
      for (final hit in restaurantHits) {
        final key = '${hit.restaurant.id}:${hit.item.id}';
        if (seen.add(key)) {
          hits.add(hit);
        }
      }
    }

    hits.sort((a, b) {
      final aCategoryMatch =
          _containsLoose(a.item.categoryName ?? '', queryLower) ||
              _containsLoose(a.item.cuisineName ?? '', queryLower);
      final bCategoryMatch =
          _containsLoose(b.item.categoryName ?? '', queryLower) ||
              _containsLoose(b.item.cuisineName ?? '', queryLower);
      if (aCategoryMatch != bCategoryMatch) {
        return bCategoryMatch ? 1 : -1;
      }
      final aStarts = a.item.name.toLowerCase().startsWith(queryLower);
      final bStarts = b.item.name.toLowerCase().startsWith(queryLower);
      if (aStarts != bStarts) {
        return bStarts ? 1 : -1;
      }
      return b.item.totalOrders.compareTo(a.item.totalOrders);
    });

    return hits;
  }

  List<Map<String, dynamic>> _extractMenuItemMaps(
      Map<String, dynamic> payload) {
    final lists = <dynamic>[
      payload['data'],
      payload['matched_menu_items'],
      payload['matchedMenuItems'],
      payload['menu_items'],
      payload['menuItems'],
      payload['items'],
      payload['menu'],
      payload['dishes'],
      payload['matched_items'],
      payload['food_items'],
      payload['foodItems'],
      payload['categories'],
    ];

    final data = payload['data'];
    if (data is Map) {
      lists.addAll([
        data['data'],
        data['matched_menu_items'],
        data['matchedMenuItems'],
        data['menu_items'],
        data['menuItems'],
        data['items'],
        data['menu'],
        data['dishes'],
        data['foods'],
        data['food_items'],
        data['foodItems'],
        data['categories'],
      ]);
    }

    final results = <Map<String, dynamic>>[];

    void collect(dynamic value) {
      if (value is List) {
        for (final item in value) {
          collect(item);
        }
        return;
      }
      if (value is! Map) return;

      final map = Map<String, dynamic>.from(value);
      final hasItemIdentity = map['id'] != null && map['name'] != null;
      final hasMenuFields = map.containsKey('price') ||
          map.containsKey('discounted_price') ||
          map.containsKey('final_price') ||
          map.containsKey('restaurant_id') ||
          map.containsKey('description') ||
          map.containsKey('images') ||
          map.containsKey('image') ||
          map.containsKey('image_url') ||
          map.containsKey('is_veg') ||
          map.containsKey('food_type');
      final looksLikeMenuItem = hasItemIdentity && hasMenuFields;
      if (looksLikeMenuItem && map['name'] != null) {
        results.add(map);
      }

      for (final key in const [
        'matched_menu_items',
        'matchedMenuItems',
        'menu_items',
        'menuItems',
        'items',
        'menu',
        'dishes',
        'foods',
        'food_items',
        'foodItems',
      ]) {
        collect(map[key]);
      }
    }

    for (final list in lists) {
      collect(list);
    }

    final seen = <String>{};
    return results
        .where((item) =>
            seen.add('${item['restaurant_id']}:${item['id']}:${item['name']}'))
        .toList(growable: false);
  }

  List<MenuItem> _parseMenuItems(
    List<Map<String, dynamic>> rawItems,
    int restaurantId,
  ) {
    final items = <MenuItem>[];
    for (final rawItem in rawItems) {
      try {
        final json = <String, dynamic>{
          ...rawItem,
          if (rawItem['id'] == null && rawItem['entity_id'] != null)
            'id': rawItem['entity_id'],
          if (rawItem['name'] == null && rawItem['title'] != null)
            'name': rawItem['title'],
          if (rawItem['price'] == null) 'price': 0,
          if (rawItem['images'] == null) 'images': const <dynamic>[],
          if (rawItem['is_available'] == null) 'is_available': true,
          if (rawItem['restaurant_id'] == null) 'restaurant_id': restaurantId,
          if (rawItem['created_at'] == null)
            'created_at': DateTime.now().toIso8601String(),
        };
        final item = MenuItem.fromJson(json);
        if (item.name.trim().isNotEmpty) {
          items.add(item);
        }
      } catch (e) {
        debugPrint('Error parsing search menu item: $e');
      }
    }
    return items;
  }

  bool _menuItemMatchesQuery(MenuItem item, String queryLower) {
    if (_cuisineId != null && item.cuisineId == _cuisineId) return true;
    final categoryMatch = _containsLoose(item.categoryName ?? '', queryLower) ||
        _containsLoose(item.cuisineName ?? '', queryLower);
    if (_isCategoryBrowse) {
      return categoryMatch || _containsLoose(item.name, queryLower);
    }
    return _containsLoose(item.name, queryLower) ||
        _containsLoose(item.description ?? '', queryLower) ||
        categoryMatch;
  }

  @override
  Widget build(BuildContext context) {
    final header = _buildHeader(context);
    if (widget.embedded) {
      return ColoredBox(
        color: _searchBg,
        child: Column(
          children: [
            SafeArea(bottom: false, child: header),
            Expanded(child: _buildBody()),
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: _searchBg,
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(82),
        child: SafeArea(bottom: false, child: header),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildHeader(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(
          bottom: BorderSide(color: _searchLine.withOpacity(0.65)),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
        child: Row(
          children: [
            Material(
              color: Colors.white,
              shape: const CircleBorder(),
              child: InkWell(
                onTap: _goBack,
                customBorder: const CircleBorder(),
                child: const SizedBox(
                  width: 44,
                  height: 44,
                  child: Icon(Icons.arrow_back_rounded, color: _searchText),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Container(
                height: 56,
                padding: const EdgeInsets.only(left: 14, right: 8),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFF1F1F1)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.06),
                      blurRadius: 12,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.search_rounded,
                      size: 22,
                      color: Color(0xFF00A651),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: SizedBox(
                        height: 22,
                        child: Stack(
                          alignment: Alignment.centerLeft,
                          children: [
                            if (_searchController.text.isEmpty)
                              const IgnorePointer(
                                child: Text(
                                  'Search restaurants, dishes...',
                                  maxLines: 1,
                                  overflow: TextOverflow.clip,
                                  softWrap: false,
                                  style: TextStyle(
                                    color: Color(0xFF686B78),
                                    fontSize: 15,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            EditableText(
                              controller: _searchController,
                              focusNode: _focusNode,
                              autofocus: !widget.embedded,
                              textInputAction: TextInputAction.search,
                              keyboardType: TextInputType.text,
                              maxLines: 1,
                              cursorColor: const Color(0xFF00A651),
                              backgroundCursorColor: const Color(0xFFEAEAEA),
                              style: const TextStyle(
                                color: Color(0xFF686B78),
                                fontSize: 15,
                                fontWeight: FontWeight.w600,
                              ),
                              onChanged: _onSearchChanged,
                              onSubmitted: (value) {
                                if (value.trim().isNotEmpty) {
                                  _searchRestaurants();
                                }
                              },
                            ),
                          ],
                        ),
                      ),
                    ),
                    Container(
                      width: 1,
                      height: 24,
                      color: const Color(0xFFEAEAEA),
                    ),
                    const SizedBox(width: 8),
                    InkWell(
                      onTap: _isSpeechBusy ? null : _startVoiceSearch,
                      borderRadius: BorderRadius.circular(16),
                      child: SizedBox(
                        width: 34,
                        height: 34,
                        child: Icon(
                          _isListening
                              ? Icons.mic_rounded
                              : Icons.mic_none_rounded,
                          size: 20,
                          color: _isListening
                              ? FoodFlowTheme.danger
                              : (_speechPermissionDenied
                                  ? FoodFlowTheme.dangerDark
                                  : const Color(0xFF1C1C1C)),
                        ),
                      ),
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

  Widget _buildBody() {
    final secondary = _searchSecondary(context);

    if (_isLoading) {
      return const AppSkeletonListView(
        itemCount: 5,
        itemHeight: 112,
        padding: EdgeInsets.fromLTRB(16, 12, 16, 28),
      );
    }

    if (_error != null) {
      return _SearchStatePanel(
        icon: Icons.wifi_off_rounded,
        title: 'Search failed',
        message: _error!,
        action: FilledButton.icon(
          onPressed: () {
            if (_isPriceFilterBrowse) {
              _loadPriceFilteredItems(forceRefresh: true);
            } else if (_searchQuery.isNotEmpty) {
              _searchRestaurants(forceRefresh: true);
            }
          },
          icon: const Icon(Icons.refresh_rounded),
          label: const Text('Try Again'),
          style: FilledButton.styleFrom(
            backgroundColor: secondary,
            foregroundColor: Colors.white,
            shape:
                RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
        ),
      );
    }

    if (_hasSearched) return _buildResultsState(secondary);
    return _buildInitialState(secondary);
  }

  Widget _buildResultsState(Color secondary) {
    if (_restaurants.isEmpty && _itemResults.isEmpty) {
      return _SearchStatePanel(
        icon: Icons.search_off_rounded,
        title: 'No matches found',
        message: _isPriceFilterBrowse
            ? 'No menu items are available in this price range right now.'
            : 'We could not find restaurants or dishes for "$_searchQuery".',
        action: OutlinedButton.icon(
          onPressed: _clearSearch,
          icon: const Icon(Icons.close_rounded),
          label: const Text('Clear Search'),
          style: OutlinedButton.styleFrom(
            foregroundColor: secondary,
            side: BorderSide(color: secondary),
            shape:
                RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          ),
        ),
      );
    }

    return RefreshIndicator(
      color: secondary,
      onRefresh: () async {
        if (_isPriceFilterBrowse) {
          await _loadPriceFilteredItems(forceRefresh: true);
        } else {
          await _searchRestaurants(forceRefresh: true);
        }
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(0, 18, 0, 28),
        children: [
          _resultSummary(),
          if (_liveSuggestions.isNotEmpty) _suggestionRail(secondary),
          if (_itemResults.isNotEmpty) ...[
            _sectionHeader(
              title: _isPriceFilterBrowse
                  ? 'Pocket-friendly dishes'
                  : 'Dishes matching your search',
              subtitle:
                  '${_itemResults.length} item${_itemResults.length == 1 ? '' : 's'} found',
            ),
            ..._itemResults.map(
              (hit) => _SearchMenuItemCard(
                hit: hit,
                onTap: () => _openRestaurant(
                  hit.restaurant.id,
                  menuItemId: hit.item.id,
                ),
              ),
            ),
          ],
          if (_restaurants.isNotEmpty) ...[
            _sectionHeader(
              title: 'Restaurants for you',
              subtitle:
                  '${_restaurants.length} place${_restaurants.length == 1 ? '' : 's'} nearby',
            ),
            ..._restaurants.map(
              (restaurant) => _SearchRestaurantCard(
                restaurant: restaurant,
                onTap: () => _openRestaurant(restaurant.id),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildInitialState(Color secondary) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(0, 18, 0, 28),
      children: [
        if (_isListening) _listeningPill(secondary),
        _recentSection(secondary),
        _popularSection(secondary),
      ],
    );
  }

  Widget _recentSection(Color secondary) {
    if (_recentSearches.isEmpty) return const SizedBox.shrink();
    return _SearchSectionShell(
      title: 'Recent Searches',
      trailing: TextButton(
        onPressed: _clearRecentSearches,
        style: TextButton.styleFrom(foregroundColor: const Color(0xFF6B7280)),
        child: const Text('Clear'),
      ),
      child: SizedBox(
        height: 46,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 20),
          itemCount: _recentSearches.take(10).length,
          separatorBuilder: (_, __) => const SizedBox(width: 10),
          itemBuilder: (context, index) {
            final search = _recentSearches[index];
            return _SearchRecentPill(
              label: search,
              color: secondary,
              onTap: () => _searchWithQuery(search),
              onRemove: () => _removeRecentSearch(search),
            );
          },
        ),
      ),
    );
  }

  Widget _popularSection(Color secondary) {
    final searches = _popularSearches.isNotEmpty
        ? _popularSearches.take(10).toList(growable: false)
        : <Map<String, dynamic>>[
            {'name': 'Biryani', 'icon': Icons.rice_bowl_rounded},
            {'name': 'Pizza', 'icon': Icons.local_pizza_rounded},
            {'name': 'North Indian', 'icon': Icons.restaurant_menu_rounded},
            {'name': 'Momos', 'icon': Icons.set_meal_rounded},
            {'name': 'Cake', 'icon': Icons.cake_rounded},
          ];

    return _SearchSectionShell(
      title: 'Popular Searches',
      subtitle: 'Most ordered around you',
      child: SizedBox(
        height: 116,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 20),
          itemCount: searches.length,
          separatorBuilder: (_, __) => const SizedBox(width: 14),
          itemBuilder: (context, index) {
            final item = searches[index];
            final name = item['name']?.toString() ?? '';
            final icon = item['icon'] is IconData
                ? item['icon'] as IconData
                : _popularSearchIcon(name);
            return _SearchPopularTile(
              label: name,
              icon: icon,
              color: secondary,
              imageUrl: item['image_url']?.toString().trim() ?? '',
              onTap: () => _searchWithQuery(name),
            );
          },
        ),
      ),
    );
  }

  Widget _listeningPill(Color secondary) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 18),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFF1F1F1)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Icon(Icons.graphic_eq_rounded, color: secondary),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Listening...',
              style: TextStyle(
                color: _searchText,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          TextButton(
            onPressed: _stopVoiceSearch,
            style: TextButton.styleFrom(foregroundColor: secondary),
            child: const Text('Stop'),
          ),
        ],
      ),
    );
  }

  Widget _suggestionRail(Color secondary) {
    return SizedBox(
      height: 46,
      child: ListView.separated(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        scrollDirection: Axis.horizontal,
        itemBuilder: (context, index) {
          final suggestion = _liveSuggestions[index];
          return _SearchRecentPill(
            label: suggestion,
            color: secondary,
            onTap: () => _searchWithQuery(suggestion),
          );
        },
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemCount: _liveSuggestions.length,
      ),
    );
  }

  Widget _resultSummary() {
    final title = _isPriceFilterBrowse
        ? (_initialTitle ?? 'Budget picks')
        : 'Showing results for "$_searchQuery"';
    final count = _restaurants.length + _itemResults.length;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _searchText,
                fontSize: 15,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Text(
            '$count found',
            style: const TextStyle(
              color: Color(0xFF6B7280),
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionHeader({required String title, required String subtitle}) {
    return _SearchHomeSectionHeader(title: title, subtitle: subtitle);
  }

  void _openRestaurant(int restaurantId, {int? menuItemId}) {
    Navigator.pushNamed(
      context,
      '/restaurant/detail',
      arguments: {
        'restaurantId': restaurantId,
        if (menuItemId != null && menuItemId > 0) 'menuItemId': menuItemId,
      },
    );
  }

  void _goBack() {
    if (widget.embedded) {
      Navigator.pushReplacementNamed(context, '/customer/home');
      return;
    }
    Navigator.maybePop(context);
  }
}

class _SearchSectionShell extends StatelessWidget {
  const _SearchSectionShell({
    required this.title,
    required this.child,
    this.subtitle,
    this.trailing,
  });

  final String title;
  final String? subtitle;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SearchHomeSectionHeader(
            title: title,
            subtitle: subtitle,
            trailing: trailing,
          ),
          child,
        ],
      ),
    );
  }
}

class _SearchHomeSectionHeader extends StatelessWidget {
  const _SearchHomeSectionHeader({
    required this.title,
    this.subtitle,
    this.trailing,
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final words = title
        .split(RegExp(r'\s+'))
        .where((word) => word.isNotEmpty)
        .toList(growable: false);
    final highlightCount = words.length >= 3 ? 2 : 1;
    final splitIndex = (words.length - highlightCount).clamp(0, words.length);
    final leading = words.take(splitIndex).join(' ');
    final trailingWords = words.skip(splitIndex).join(' ');

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 30,
                  child: RichText(
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    text: TextSpan(
                      children: [
                        if (leading.isNotEmpty)
                          TextSpan(
                            text: '$leading ',
                            style: const TextStyle(
                              color: _searchText,
                              fontSize: 21,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        TextSpan(
                          text: trailingWords,
                          style: const TextStyle(
                            color: Color(0xFFFF6B00),
                            fontSize: 21,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              if (trailing != null) trailing!,
            ],
          ),
          if (subtitle != null && subtitle!.trim().isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(
                subtitle!,
                style: const TextStyle(
                  color: Color(0xFF6B7280),
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

class _SearchPopularTile extends StatelessWidget {
  const _SearchPopularTile({
    required this.label,
    required this.icon,
    required this.color,
    required this.imageUrl,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final Color color;
  final String imageUrl;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        width: 76,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
                border: Border.all(color: const Color(0xFFF1F1F1)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.06),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              clipBehavior: Clip.antiAlias,
              child: imageUrl.isNotEmpty
                  ? AppCachedImage(
                      imageUrl: imageUrl,
                      width: 64,
                      height: 64,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _PopularIconFallback(
                        icon: icon,
                        color: color,
                      ),
                    )
                  : _PopularIconFallback(icon: icon, color: color),
            ),
            const SizedBox(height: 7),
            Text(
              label,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: _searchText,
                fontSize: 12,
                height: 1.08,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PopularIconFallback extends StatelessWidget {
  const _PopularIconFallback({
    required this.icon,
    required this.color,
  });

  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: Colors.white,
      child: Icon(icon, color: color, size: 28),
    );
  }
}

class _SearchRecentPill extends StatelessWidget {
  const _SearchRecentPill({
    required this.label,
    required this.color,
    required this.onTap,
    this.onRemove,
  });

  final String label;
  final Color color;
  final VoidCallback onTap;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: const Color(0xFFF1F1F1)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.04),
                blurRadius: 8,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.history_rounded, size: 17, color: color),
              const SizedBox(width: 7),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 150),
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _searchText,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (onRemove != null) ...[
                const SizedBox(width: 6),
                GestureDetector(
                  onTap: onRemove,
                  child: const Icon(
                    Icons.close_rounded,
                    size: 16,
                    color: Color(0xFF6B7280),
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

class _SearchStatePanel extends StatelessWidget {
  const _SearchStatePanel({
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final secondary = _searchSecondary(context);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: const Color(0xFFF1F1F1)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.06),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 70,
                height: 70,
                decoration: BoxDecoration(
                  color: secondary.withOpacity(0.10),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: secondary, size: 34),
              ),
              const SizedBox(height: 16),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: _searchText,
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Color(0xFF6B7280),
                  fontSize: 13.5,
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (action != null) ...[
                const SizedBox(height: 18),
                action!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}
