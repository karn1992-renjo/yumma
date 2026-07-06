// lib/screens/customer/search_screen.dart
import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
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
import '../../utils/currency_utils.dart';
import '../../widgets/customer/search_result_card.dart';

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
    final primary = Theme.of(context).colorScheme.primary;
    final imageUrl = hit.item.imageUrl.isNotEmpty
        ? hit.item.imageUrl
        : hit.restaurant.logoUrl;
    final tags = hit.item.displayTags.take(3).toList(growable: false);

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE5E7EB)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.045),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(18),
                bottomLeft: Radius.circular(18),
              ),
              child: imageUrl.isNotEmpty
                  ? AppCachedImage(
                      imageUrl: imageUrl,
                      width: 104,
                      height: 112,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => _fallback(primary),
                    )
                  : _fallback(primary),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      hit.item.name,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF111827),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      hit.restaurant.name,
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF6B7280),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (tags.isNotEmpty) ...[
                      const SizedBox(height: 7),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: tags
                            .map(
                              (tag) => Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFF3E8),
                                  borderRadius: BorderRadius.circular(999),
                                  border: Border.all(
                                    color: const Color(0xFFFFD7AF),
                                  ),
                                ),
                                child: Text(
                                  tag,
                                  style: const TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w800,
                                    color: Color(0xFFE86F00),
                                  ),
                                ),
                              ),
                            )
                            .toList(),
                      ),
                    ],
                    if (hit.item.description?.trim().isNotEmpty == true) ...[
                      const SizedBox(height: 6),
                      Text(
                        hit.item.description!.trim(),
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF6B7280),
                          height: 1.3,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: primary.withOpacity(0.08),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            hit.item.dietLabel,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: primary,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          hit.item.hasDiscount
                              ? formatCurrency(context, hit.item.finalPrice)
                              : formatCurrency(context, hit.item.price),
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF111827),
                          ),
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

  Widget _fallback(Color primary) {
    return Container(
      width: 104,
      height: 112,
      color: primary.withOpacity(0.08),
      child: Icon(
        Icons.fastfood_rounded,
        size: 34,
        color: primary,
      ),
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
  String? _categoryFilter;
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
        _isCategoryBrowse =
            initialQuery['browseMode']?.toString() == 'category' ||
                initialQuery['source']?.toString() == 'category';
        if (category != null && category.isNotEmpty) {
          _categoryFilter = category;
        }
        _shouldStartVoiceSearch = initialQuery['startVoiceSearch'] == true;
        if (title != null && title.isNotEmpty) {
          _initialTitle = title;
        }
        if (query.isNotEmpty) {
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
          backgroundColor: Colors.green,
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
          .map((item) => item['name']?.toString().trim() ?? '')
          .where((name) => name.isNotEmpty)
          .take(12)
          .map((name) => {
                'name': name,
                'icon': _popularSearchIcon(name),
              })
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

  List<Map<String, dynamic>> _extractGenericList(dynamic response) {
    if (response is List) {
      return response
          .whereType<Map>()
          .map((item) => _restaurantMapFromSearchItem(
                Map<String, dynamic>.from(item),
              ))
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
            .map((item) => Map<String, dynamic>.from(item))
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
          .map((item) => Map<String, dynamic>.from(item))
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
                .toList(growable: false);
          }
        }
      }
    }

    return const <Map<String, dynamic>>[];
  }

  Map<String, dynamic> _restaurantMapFromSearchItem(Map<String, dynamic> item) {
    if (item['name'] != null) return item;
    if (item['type'] != 'restaurant' && item['entity_type'] != 'restaurant') {
      return item;
    }

    return <String, dynamic>{
      ...item,
      'id': item['restaurant_id'] ?? item['entity_id'] ?? item['id'],
      'name': item['title'] ?? item['name'] ?? 'Restaurant',
      'description': item['description'],
      'latitude': item['latitude'],
      'longitude': item['longitude'],
      'cuisine': item['tags'] ?? const <dynamic>[],
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
        if (_cuisineId != null && _cuisineId! > 0)
          'cuisine_id': _cuisineId,
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

      debugPrint('🔍 Searching restaurants with query: "$query"');
      debugPrint('📍 Search params: $queryParams');

      // Try search endpoint
      dynamic response;
      try {
        response = await _api
            .get(
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
            )
            .timeout(const Duration(seconds: 15));
        debugPrint('✅ Search API response received');
      } catch (e) {
        debugPrint('❌ Search endpoint failed: $e');
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

          final parsedRestaurants = dataList
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
          final itemResults = <_MenuSearchHit>[
            ...directItemResults,
          ].where((hit) {
            return seenItems.add('${hit.restaurant.id}:${hit.item.id}');
          }).toList(growable: false);

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
              '📊 Total found: ${dataList.length}, Filtered: ${filteredRestaurants.length}');

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
        restaurant = Restaurant.fromJson(<String, dynamic>{
          'id': restaurantId,
          'name': rawItem['restaurant_name'] ?? 'Restaurant',
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
        color: Theme.of(context).scaffoldBackgroundColor,
        child: Column(
          children: [
            SafeArea(bottom: false, child: header),
            Expanded(child: _buildBody()),
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(78),
        child: SafeArea(bottom: false, child: header),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildHeader(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final borderColor = const Color(0xFFE5E7EB);
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 12),
      child: Row(
        children: [
          if (!widget.embedded) ...[
            InkWell(
              onTap: () => Navigator.pop(context),
              borderRadius: BorderRadius.circular(14),
              child: Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: borderColor),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.05),
                      blurRadius: 12,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.arrow_back_rounded,
                  color: Color(0xFF111827),
                ),
              ),
            ),
            const SizedBox(width: 12),
          ],
          Expanded(
            child: Container(
              height: 56,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: borderColor),
              ),
              child: Row(
                children: [
                  const Icon(Icons.search_rounded,
                      size: 20, color: Color(0xFF6B7280)),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      controller: _searchController,
                      focusNode: _focusNode,
                      autofocus: true,
                      decoration: const InputDecoration(
                        hintText: 'Search restaurant, item, cuisine...',
                        hintStyle: TextStyle(
                          color: Color(0xFF9CA3AF),
                          fontSize: 14.5,
                          fontWeight: FontWeight.w500,
                        ),
                        border: InputBorder.none,
                      ),
                      onChanged: _onSearchChanged,
                      onSubmitted: (value) {
                        if (value.trim().isNotEmpty) {
                          _searchRestaurants();
                        }
                      },
                    ),
                  ),
                  if (_searchController.text.isNotEmpty)
                    IconButton(
                      visualDensity: VisualDensity.compact,
                      icon: const Icon(Icons.clear_rounded,
                          color: Color(0xFF6B7280)),
                      onPressed: _clearSearch,
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 12),
          InkWell(
            onTap: _isSpeechBusy ? null : _startVoiceSearch,
            borderRadius: BorderRadius.circular(14),
            child: Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: borderColor),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.05),
                    blurRadius: 12,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Icon(
                _isListening ? Icons.mic : Icons.mic_none_rounded,
                size: 20,
                color: _isListening
                    ? Colors.redAccent
                    : (_speechPermissionDenied
                        ? Colors.orange
                        : colorScheme.primary),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBody() {
    final primary = Theme.of(context).colorScheme.primary;
    if (_isLoading) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text('Searching for restaurants...'),
          ],
        ),
      );
    }

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.wifi_off, size: 64, color: Colors.grey.shade400),
            const SizedBox(height: 16),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(
                _error!,
                style: TextStyle(color: Colors.grey.shade600),
                textAlign: TextAlign.center,
              ),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: () {
                if (_searchQuery.isNotEmpty) {
                  _searchRestaurants();
                }
              },
              icon: const Icon(Icons.refresh),
              label: const Text('Try Again'),
              style: ElevatedButton.styleFrom(
                backgroundColor: primary,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      );
    }

    if (_hasSearched) {
      if (_restaurants.isEmpty && _itemResults.isEmpty) {
        return Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Lottie.asset(
                'assets/animations/no-search.json',
                width: 240,
                height: 200,
                fit: BoxFit.contain,
              ),
              const SizedBox(height: 16),
              const Text(
                'No results found',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                'We couldn\'t find any restaurants or items matching "$_searchQuery"',
                style: TextStyle(fontSize: 14, color: Colors.grey.shade600),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              OutlinedButton.icon(
                onPressed: _clearSearch,
                icon: const Icon(Icons.clear),
                label: const Text('Clear Search'),
                style: OutlinedButton.styleFrom(
                  side: BorderSide(color: primary),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ],
          ),
        );
      }

      return Column(
        children: [
          if (_isListening)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: primary.withOpacity(0.08),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Icon(Icons.graphic_eq, color: primary, size: 18),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Listening... say a restaurant, dish, or cuisine',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: primary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          if (_initialTitle != null && _initialTitle!.isNotEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 2),
              child: Text(
                'Showing results for ${_initialTitle!}',
                style: TextStyle(
                  fontSize: 13,
                  color: Colors.grey.shade700,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          if (_liveSuggestions.isNotEmpty)
            Container(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              alignment: Alignment.centerLeft,
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _liveSuggestions
                    .map(
                      (suggestion) => ActionChip(
                        label: Text(suggestion),
                        onPressed: () => _searchWithQuery(suggestion),
                        backgroundColor: primary.withOpacity(0.08),
                        side: BorderSide(
                          color: primary.withOpacity(0.18),
                        ),
                      ),
                    )
                    .toList(),
              ),
            ),
          // Unified results summary
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            alignment: Alignment.centerLeft,
            child: Text(
              '${_itemResults.length} matching item${_itemResults.length == 1 ? '' : 's'} | '
              '${_restaurants.length} matching restaurant${_restaurants.length == 1 ? '' : 's'}',
              style: TextStyle(
                fontSize: 13,
                color: Colors.grey.shade600,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _searchRestaurants(forceRefresh: true),
              color: primary,
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: 8),
                children: [
                  if (_itemResults.isNotEmpty) ...[
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
                      child: Text(
                        'Matching items (${_itemResults.length})',
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF111827),
                        ),
                      ),
                    ),
                    ..._itemResults.map(
                      (hit) => _SearchMenuItemCard(
                        hit: hit,
                        onTap: () {
                          Navigator.pushNamed(
                            context,
                            '/restaurant/detail',
                            arguments: hit.restaurant.id,
                          );
                        },
                      ),
                    ),
                  ],
                  if (_restaurants.isNotEmpty) ...[
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
                      child: Text(
                        'Matching restaurants (${_restaurants.length})',
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF111827),
                        ),
                      ),
                    ),
                    ..._restaurants.map(
                    (restaurant) => SearchResultCard(
                      restaurant: restaurant,
                      onTap: () {
                        Navigator.pushNamed(
                          context,
                          '/restaurant/detail',
                          arguments: restaurant.id,
                        );
                      },
                    ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      );
    }

    // Initial state - show suggestions
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (_recentSearches.isNotEmpty) ...[
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Recent Searches',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
                TextButton(
                  onPressed: _clearRecentSearches,
                  style: TextButton.styleFrom(
                    foregroundColor: Colors.red,
                  ),
                  child:
                      const Text('Clear All', style: TextStyle(fontSize: 12)),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _recentSearches
                  .map((search) => Chip(
                        label:
                            Text(search, style: const TextStyle(fontSize: 13)),
                        onDeleted: () => _removeRecentSearch(search),
                        deleteIcon: const Icon(Icons.close, size: 16),
                        backgroundColor: Colors.grey.shade100,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(20),
                        ),
                      ))
                  .toList(),
            ),
            const SizedBox(height: 24),
          ],
          if (_popularSearches.isNotEmpty) ...[
            const Text(
              'Popular Searches',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: _popularSearches
                  .map((item) => GestureDetector(
                        onTap: () => _searchWithQuery(item['name'] as String),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 10),
                          decoration: BoxDecoration(
                            color: Colors.grey.shade100,
                            borderRadius: BorderRadius.circular(24),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                item['icon'] as IconData,
                                size: 18,
                                color: Theme.of(context).colorScheme.primary,
                              ),
                              const SizedBox(width: 8),
                              Text(
                                item['name'] as String,
                                style: const TextStyle(
                                    fontSize: 14, fontWeight: FontWeight.w500),
                              ),
                            ],
                          ),
                        ),
                      ))
                  .toList(),
            ),
            const SizedBox(height: 24),
          ],
          GestureDetector(
            onTap: () {
              Navigator.pushNamed(context, '/customer/home');
            },
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    Theme.of(context).colorScheme.primary.withOpacity(0.1),
                    Theme.of(context).colorScheme.primary.withOpacity(0.05),
                  ],
                ),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.restaurant,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'Browse all restaurants near you',
                      style:
                          TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                  ),
                  Icon(
                    Icons.arrow_forward,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
