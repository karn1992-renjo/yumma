// lib/screens/restaurant/restaurant_promos_screen.dart
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../config/app_config.dart';
import '../../config/api_constants.dart';
import '../../providers/restaurant_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_image_loader.dart';

// Keep this screen's existing compact theme references while using the app theme.
// ignore: camel_case_types
typedef foodflow = FoodFlowTheme;

const Map<String, _PromoShape> _promoShapes = {
  'percentage_discount': _PromoShape(
    label: 'Percentage Discount',
    rewardType: 'percentage',
    icon: Icons.percent,
    hint: 'Percentage off eligible order value.',
  ),
  'flat_discount': _PromoShape(
    label: 'Flat Discount',
    rewardType: 'flat',
    icon: Icons.local_offer_outlined,
    hint: 'Fixed amount off eligible orders.',
  ),
  'fixed_price': _PromoShape(
    label: 'Fixed Price',
    rewardType: 'fixed_price',
    icon: Icons.receipt_long_outlined,
    hint: 'Set eligible cart to a fixed payable price.',
  ),
  'item_discount': _PromoShape(
    label: 'Item Discount',
    rewardType: 'item_discount',
    icon: Icons.fastfood_outlined,
    hint: 'Discount selected menu items.',
    preferredTarget: 'items',
  ),
  'category_discount': _PromoShape(
    label: 'Category Discount',
    rewardType: 'category_discount',
    icon: Icons.category_outlined,
    hint: 'Discount selected menu categories.',
    preferredTarget: 'categories',
  ),
  'combo_deal': _PromoShape(
    label: 'Combo Deal',
    rewardType: 'combo_deal',
    icon: Icons.set_meal_outlined,
    hint: 'Bundle selected items into a deal.',
    preferredTarget: 'items',
  ),
  'meal_deal': _PromoShape(
    label: 'Meal Deal',
    rewardType: 'meal_deal',
    icon: Icons.restaurant_menu,
    hint: 'Meal deal pricing for selected content.',
    preferredTarget: 'items',
  ),
  'bogo': _PromoShape(
    label: 'BOGO',
    rewardType: 'bogo',
    icon: Icons.card_giftcard,
    hint: 'Buy one, get one reward.',
    preferredTarget: 'items',
    noValueRequired: true,
    needsBuyFree: true,
  ),
  'buy_x_get_y': _PromoShape(
    label: 'Buy X Get Y',
    rewardType: 'buy_x_get_y',
    icon: Icons.add_circle_outline,
    hint: 'Configure buy and free quantities.',
    preferredTarget: 'items',
    noValueRequired: true,
    needsBuyFree: true,
  ),
  'buy_2_get_1': _PromoShape(
    label: 'Buy 2 Get 1',
    rewardType: 'buy_2_get_1',
    icon: Icons.filter_2,
    hint: 'Buy two eligible items, get one.',
    preferredTarget: 'items',
    noValueRequired: true,
    needsBuyFree: true,
    defaultBuy: 2,
    defaultFree: 1,
  ),
  'buy_3_get_1': _PromoShape(
    label: 'Buy 3 Get 1',
    rewardType: 'buy_3_get_1',
    icon: Icons.filter_3,
    hint: 'Buy three eligible items, get one.',
    preferredTarget: 'items',
    noValueRequired: true,
    needsBuyFree: true,
    defaultBuy: 3,
    defaultFree: 1,
  ),
  'buy_3_get_2': _PromoShape(
    label: 'Buy 3 Get 2',
    rewardType: 'buy_3_get_2',
    icon: Icons.filter_3,
    hint: 'Buy three eligible items, get two.',
    preferredTarget: 'items',
    noValueRequired: true,
    needsBuyFree: true,
    defaultBuy: 3,
    defaultFree: 2,
  ),
  'free_item': _PromoShape(
    label: 'Free Item',
    rewardType: 'free_item',
    icon: Icons.cookie_outlined,
    hint: 'Attach a free item reward.',
    preferredTarget: 'items',
    noValueRequired: true,
  ),
};

const List<String> _restaurantPromotionTypeKeys = [
  'percentage_discount',
  'flat_discount',
  'fixed_price',
  'item_discount',
  'category_discount',
  'combo_deal',
  'meal_deal',
  'bogo',
  'buy_x_get_y',
  'buy_2_get_1',
  'buy_3_get_1',
  'buy_3_get_2',
  'free_item',
];

String _canonicalRestaurantPromotionType(String? key) {
  switch ((key ?? '').trim()) {
    case 'restaurant_discount':
    case 'happy_hours':
      return 'percentage_discount';
    case 'deal_of_day':
      return 'flat_discount';
    case 'buy_1_get_1':
      return 'bogo';
    case 'free_product':
    case 'free_drink':
    case 'free_dessert':
      return 'free_item';
    default:
      return key?.trim() ?? '';
  }
}

bool _isRestaurantPromotionType(String key) => _restaurantPromotionTypeKeys
    .contains(_canonicalRestaurantPromotionType(key));

_PromoShape _restaurantPromoShape(String? key) {
  final canonicalKey = _canonicalRestaurantPromotionType(key);
  if (_isRestaurantPromotionType(canonicalKey) &&
      _promoShapes.containsKey(canonicalKey)) {
    return _promoShapes[canonicalKey]!;
  }

  return _promoShapes['percentage_discount']!;
}

class _PromoShape {
  const _PromoShape({
    required this.label,
    required this.rewardType,
    required this.icon,
    required this.hint,
    this.preferredTarget,
    this.noValueRequired = false,
    this.needsBuyFree = false,
    this.defaultBuy = 1,
    this.defaultFree = 1,
  });

  final String label;
  final String rewardType;
  final IconData icon;
  final String hint;
  final String? preferredTarget;
  final bool noValueRequired;
  final bool needsBuyFree;
  final int defaultBuy;
  final int defaultFree;
}

BoxDecoration _partnerPanelDecoration({double radius = 28}) {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: foodflow.line),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withOpacity(0.055),
        blurRadius: 22,
        offset: const Offset(0, 12),
      ),
    ],
  );
}

BoxDecoration _brandLiftDecoration({double radius = 14}) {
  return BoxDecoration(
    gradient: foodflow.brandGradient,
    borderRadius: BorderRadius.circular(radius),
    boxShadow: [
      BoxShadow(
        color: foodflow.orange.withOpacity(0.24),
        blurRadius: 18,
        offset: const Offset(0, 10),
      ),
    ],
  );
}

class RestaurantPromosScreen extends StatefulWidget {
  const RestaurantPromosScreen({Key? key}) : super(key: key);

  @override
  State<RestaurantPromosScreen> createState() => _RestaurantPromosScreenState();
}

class _RestaurantPromosScreenState extends State<RestaurantPromosScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _promos = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadPromos();
  }

  Future<void> _loadPromos() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(ApiConstants.restaurantPromos);
      if (response['success'] == true && mounted) {
        final rows = (response['data'] as List? ?? [])
            .map((row) => Map<String, dynamic>.from(row as Map))
            .toList();
        setState(() => _promos = rows);
      }
    } catch (e) {
      debugPrint('Load promos error: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _togglePromoStatus(Map<String, dynamic> promo) async {
    final promoId = _promoActionId(promo);
    if (promoId <= 0) return;
    try {
      final response =
          await _api.post('${ApiConstants.restaurantPromos}/$promoId/toggle');
      if (response['success'] == true) {
        await _loadPromos();
      }
    } catch (e) {
      debugPrint('Toggle promo error: $e');
    }
  }

  Future<void> _deletePromo(Map<String, dynamic> promo) async {
    final promoId = _promoActionId(promo);
    if (promoId <= 0) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Promotion'),
        content: Text('Delete "${_promoTitle(promo)}"?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      final response =
          await _api.delete('${ApiConstants.restaurantPromos}/$promoId');
      if (response['success'] == true) {
        await _loadPromos();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Promotion deleted successfully')),
          );
        }
      }
    } catch (e) {
      debugPrint('Delete promo error: $e');
    }
  }

  Future<void> _openCreate() async {
    final created = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => const PromotionEditorScreen()),
    );
    if (created == true) _loadPromos();
  }

  Future<void> _openEdit(Map<String, dynamic> promo) async {
    final updated = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => PromotionEditorScreen(promo: promo)),
    );
    if (updated == true) _loadPromos();
  }

  Future<void> _openDetails(Map<String, dynamic> promo) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => PromotionDetailsScreen(
          promo: promo,
          onEdit: () => _openEdit(promo),
          onToggle: () => _togglePromoStatus(promo),
          onDelete: () => _deletePromo(promo),
        ),
      ),
    );
    if (changed == true) _loadPromos();
  }

  int _promoActionId(Map<String, dynamic> promo) {
    final value =
        promo['legacy_id'] ?? promo['migrated_from_id'] ?? promo['id'];
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  bool _isPromoActive(Map<String, dynamic> promo) {
    if (promo['is_active'] is bool) return promo['is_active'] as bool;
    return promo['status']?.toString() == 'active';
  }

  DateTime? _tryParseDate(dynamic value) {
    if (value is DateTime) return value;
    if (value is String && value.trim().isNotEmpty) {
      return DateTime.tryParse(value.trim());
    }
    return null;
  }

  String _promoTitle(Map<String, dynamic> promo) {
    final description = promo['description']?.toString();
    if (description != null && description.trim().isNotEmpty) {
      return description.trim();
    }
    return promo['title']?.toString() ??
        promo['code']?.toString() ??
        'Promotion';
  }

  String _promoStatus(Map<String, dynamic> promo) {
    if (!_isPromoActive(promo)) return 'Draft';
    final start = _tryParseDate(promo['start_date'] ?? promo['valid_from']);
    if (start != null && start.isAfter(DateTime.now())) return 'Scheduled';
    return 'Active';
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'Active':
        return const Color(0xFF16A34A);
      case 'Scheduled':
        return const Color(0xFFF97316);
      default:
        return const Color(0xFF667085);
    }
  }

  @override
  Widget build(BuildContext context) {
    final active =
        _promos.where((promo) => _promoStatus(promo) == 'Active').length;
    final scheduled =
        _promos.where((promo) => _promoStatus(promo) == 'Scheduled').length;
    final draft =
        _promos.where((promo) => _promoStatus(promo) == 'Draft').length;
    final restaurantName =
        context.watch<RestaurantProvider>().selectedRestaurantLabel;

    return Scaffold(
      backgroundColor: foodflow.orange.withOpacity(0.04),
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : RefreshIndicator(
                onRefresh: _loadPromos,
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    _PromosHero(
                      restaurantName: restaurantName,
                      total: _promos.length,
                      active: active,
                      scheduled: scheduled,
                      draft: draft,
                      onCreate: _openCreate,
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 22, 20, 8),
                      child: Row(
                        children: [
                          const Expanded(
                            child: Text(
                              'Your Promotions',
                              style: TextStyle(
                                color: foodflow.ink,
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (_promos.isEmpty)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(20, 30, 20, 20),
                        child: _EmptyPromotionState(onCreate: _openCreate),
                      )
                    else
                      ..._promos.map(
                        (promo) => Padding(
                          padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
                          child: _PromotionListTile(
                            promo: promo,
                            status: _promoStatus(promo),
                            statusColor: _statusColor(_promoStatus(promo)),
                            onTap: () => _openDetails(promo),
                            onEdit: () => _openEdit(promo),
                            onDelete: () => _deletePromo(promo),
                          ),
                        ),
                      ),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
      ),
    );
  }
}

class PromotionEditorScreen extends StatefulWidget {
  const PromotionEditorScreen({super.key, this.promo});

  final Map<String, dynamic>? promo;

  @override
  State<PromotionEditorScreen> createState() => _PromotionEditorScreenState();
}

class _PromotionEditorScreenState extends State<PromotionEditorScreen> {
  final ApiService _api = ApiService();
  final ImagePicker _imagePicker = ImagePicker();
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _discountValue = TextEditingController(text: '20');
  final _maxDiscount = TextEditingController();
  final _minOrder = TextEditingController(text: '199');
  final _usageLimit = TextEditingController(text: '1000');
  final _perUserLimit = TextEditingController(text: '1');
  final _budgetLimit = TextEditingController();
  final _couponCode = TextEditingController();
  final _assignedUserId = TextEditingController();
  final _buyQuantity = TextEditingController(text: '1');
  final _freeQuantity = TextEditingController(text: '1');

  int _step = 0;
  bool _isSaving = false;
  bool _isActive = true;
  bool _specificHours = false;
  bool _repeatPromotion = true;
  bool _showUsage = true;
  bool _newUsersOnly = false;
  bool _firstOrderOnly = false;
  bool _specificUserOnly = false;
  String _promotionType = 'percentage_discount';
  String _discountType = 'percentage';
  List<String> _promotionShapeKeys = _restaurantPromotionTypeKeys.toList();
  String _applicationMode = 'coupon';
  String _promotionFor = 'restaurant';
  String _audienceType = 'all';
  String _couponType = 'public';
  String _validOn = 'All Days';
  String _validTime = 'All Day';
  String? _promoImagePath;
  String? _promoImageUrl;
  bool _isLoadingTargets = true;
  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _menuItems = [];
  final Set<int> _selectedTargetIds = {};
  int _freeItemId = 0;
  DateTime _startDate = DateTime.now();
  DateTime _endDate = DateTime.now().add(const Duration(days: 7));
  TimeOfDay _startTime = const TimeOfDay(hour: 0, minute: 0);
  TimeOfDay _endTime = const TimeOfDay(hour: 23, minute: 59);
  final Set<int> _repeatDays = {1, 2, 3, 4, 5};

  bool get _isEditing => widget.promo != null;
  int get _stepCount => _applicationMode == 'coupon' ? 7 : 6;
  int get _lastStep => _stepCount - 1;
  List<Map<String, dynamic>> get _targetRows =>
      _promotionFor == 'categories' ? _categories : _menuItems;
  _PromoShape get _shape => _restaurantPromoShape(_promotionType);
  Iterable<MapEntry<String, _PromoShape>> get _promotionShapeEntries =>
      _promotionShapeKeys
          .where(_isRestaurantPromotionType)
          .where(_promoShapes.containsKey)
          .map((key) => MapEntry(key, _promoShapes[key]!));
  bool get _rewardNeedsValue => !_shape.noValueRequired;
  bool get _rewardNeedsMax => {
        'percentage',
        'item_discount',
        'category_discount',
      }.contains(_shape.rewardType);
  bool get _rewardIsMoney => {
        'flat',
        'fixed_price',
        'combo_deal',
        'meal_deal',
      }.contains(_shape.rewardType);
  bool get _rewardIsPoints => false;

  @override
  void initState() {
    super.initState();
    _hydrate();
    _loadPromotionTargets();
    _loadPromotionOptions();
  }

  void _hydrate() {
    final promo = widget.promo;
    if (promo == null) return;
    final reward = promo['rewards'] is Map ? promo['rewards'] as Map : null;
    final conditions =
        promo['conditions'] is Map ? promo['conditions'] as Map : null;
    _title.text = _promoTitle(promo);
    _description.text = promo['description']?.toString() ?? '';
    _couponCode.text =
        promo['code']?.toString() ?? promo['coupon_code']?.toString() ?? '';
    _promotionType = _canonicalRestaurantPromotionType(
      (promo['promotion_type'] ?? _promotionTypeFromLegacy(promo)).toString(),
    );
    if (!_promoShapes.containsKey(_promotionType) ||
        !_isRestaurantPromotionType(_promotionType)) {
      _promotionType = 'percentage_discount';
    }
    _discountType = _shape.rewardType;
    if (!{'percentage', 'flat'}.contains(_discountType)) {
      _discountType =
          (promo['discount_type'] ?? reward?['type'] ?? 'percentage')
              .toString();
    }
    if (_discountType == 'fixed') _discountType = 'flat';
    if (_discountType != 'percentage' && _discountType != 'flat')
      _discountType = _shape.rewardType;
    _discountValue.text =
        _numText(promo['discount_value'] ?? reward?['value'] ?? 20);
    _maxDiscount.text =
        _numText(promo['max_discount_amount'] ?? reward?['max_discount'] ?? '');
    final config = _asMap(promo['reward_config'] ?? reward?['config']);
    _buyQuantity.text = _numText(config['buy_quantity'] ?? _shape.defaultBuy);
    _freeQuantity.text =
        _numText(config['free_quantity'] ?? _shape.defaultFree);
    _freeItemId = int.tryParse(
            (config['free_item_id'] ?? reward?['free_item_id'] ?? 0)
                .toString()) ??
        0;
    _minOrder.text = _numText(
        promo['min_order_amount'] ?? conditions?['min_order_amount'] ?? 199);
    _usageLimit.text = _numText(promo['usage_limit'] ?? 1000);
    _isActive = promo['is_active'] != false;
    _applicationMode = promo['application_mode']?.toString() == 'automatic'
        ? 'automatic'
        : 'coupon';
    _audienceType =
        (conditions?['audience_type'] ?? promo['audience_type'] ?? 'all')
            .toString();
    if (!{'all', 'new_customer', 'returning_customer'}
        .contains(_audienceType)) {
      _audienceType = 'all';
    }
    _newUsersOnly = _audienceType == 'new_customer';
    _firstOrderOnly = conditions?['first_order_only'] == true ||
        promo['first_order_only'] == true;
    _newUsersOnly = _newUsersOnly || config['new_users_only'] == true;
    _firstOrderOnly = _firstOrderOnly || config['first_order_only'] == true;
    if (_newUsersOnly || _firstOrderOnly) {
      _audienceType = 'new_customer';
    }
    _specificHours = config['specific_hours'] == true;
    _repeatPromotion = config['repeat_promotion'] != false;
    _showUsage = config['show_usage'] != false;
    _validOn = config['valid_on']?.toString() ?? _validOn;
    _validTime = config['valid_time']?.toString() ?? _validTime;
    final repeatDays = config['repeat_days'];
    if (repeatDays is List) {
      _repeatDays
        ..clear()
        ..addAll(repeatDays
            .map((day) => int.tryParse(day.toString()) ?? 0)
            .where((day) => day >= 1 && day <= 7));
    }
    _couponType =
        promo['coupon_type']?.toString() == 'prepaid' ? 'prepaid' : 'public';
    _specificUserOnly = _couponType == 'prepaid';
    _assignedUserId.text = promo['assigned_to']?.toString() ?? '';
    _promoImageUrl =
        promo['promo_image']?.toString() ?? promo['image']?.toString();
    if (_promoImageUrl != null && _promoImageUrl!.trim().isEmpty) {
      _promoImageUrl = null;
    }
    _promotionFor = promo['target_type']?.toString() ??
        promo['promotion_for']?.toString() ??
        'restaurant';
    if (!{'restaurant', 'categories', 'items'}.contains(_promotionFor)) {
      _promotionFor = 'restaurant';
    }
    _selectedTargetIds
      ..clear()
      ..addAll(_parseIdSet(promo['target_ids'] ?? promo['targets']));
    _startDate = _parseDate(promo['start_date'] ?? promo['valid_from']) ??
        DateTime.now();
    _endDate = _parseDate(promo['end_date'] ?? promo['valid_to']) ??
        DateTime.now().add(const Duration(days: 7));
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _discountValue.dispose();
    _maxDiscount.dispose();
    _minOrder.dispose();
    _usageLimit.dispose();
    _perUserLimit.dispose();
    _budgetLimit.dispose();
    _couponCode.dispose();
    _assignedUserId.dispose();
    _buyQuantity.dispose();
    _freeQuantity.dispose();
    super.dispose();
  }

  Future<void> _loadPromotionTargets() async {
    setState(() => _isLoadingTargets = true);
    try {
      final responses = await Future.wait([
        _api.get(ApiConstants.restaurantCategories),
        _api.get(ApiConstants.restaurantMenuItems),
      ]);
      if (!mounted) return;

      setState(() {
        _categories = _responseRows(responses[0]);
        _menuItems = _responseRows(responses[1]);
        _isLoadingTargets = false;
      });
    } catch (e) {
      debugPrint('Load promotion targets error: $e');
      if (mounted) setState(() => _isLoadingTargets = false);
    }
  }

  Future<void> _loadPromotionOptions() async {
    try {
      final response = await _api.get(ApiConstants.restaurantPromoOptions);
      final data = response['data'];
      final types = data is Map ? data['promotion_types'] : null;
      if (response['success'] == true && types is Map && mounted) {
        final keys = types.keys
            .map((key) => _canonicalRestaurantPromotionType(key.toString()))
            .where(_isRestaurantPromotionType)
            .where(_promoShapes.containsKey)
            .toSet()
            .toList();
        if (keys.isNotEmpty) {
          setState(() {
            _promotionShapeKeys = keys;
            if (!_promotionShapeKeys.contains(_promotionType)) {
              _promotionType = _promotionShapeKeys.first;
              _discountType = _shape.rewardType;
            }
          });
        }
      }
    } catch (e) {
      debugPrint('Load promotion options error: $e');
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_hasValidTargetSelection()) {
      _toast(_promotionFor == 'categories'
          ? 'Select at least one category for this promotion.'
          : 'Select at least one item for this promotion.');
      return;
    }
    final code =
        _applicationMode == 'coupon' && _couponCode.text.trim().isNotEmpty
            ? _couponCode.text.trim().toUpperCase()
            : _generateCode(_title.text);
    final startAt = DateTime(
      _startDate.year,
      _startDate.month,
      _startDate.day,
      _specificHours ? _startTime.hour : 0,
      _specificHours ? _startTime.minute : 0,
    );
    final endAt = DateTime(
      _endDate.year,
      _endDate.month,
      _endDate.day,
      _specificHours ? _endTime.hour : 23,
      _specificHours ? _endTime.minute : 59,
    );
    if (endAt.isBefore(startAt) || endAt.isAtSameMomentAs(startAt)) {
      _toast('End date must be after start date.');
      return;
    }

    final comboGroups = _comboGroupPayload();
    if (_isComboPromotion && comboGroups.isEmpty) {
      _toast('Select at least two menu items and set a combo price.');
      return;
    }

    setState(() => _isSaving = true);
    final payload = {
      'title': _title.text.trim(),
      'code': code,
      'description': _description.text.trim().isNotEmpty
          ? _description.text.trim()
          : _title.text.trim(),
      'promotion_type': _promotionType,
      'reward_type': _shape.rewardType,
      'reward_config': {
        'buy_quantity': int.tryParse(_buyQuantity.text) ?? _shape.defaultBuy,
        'free_quantity': int.tryParse(_freeQuantity.text) ?? _shape.defaultFree,
        'no_value_required': _shape.noValueRequired,
        'shape_label': _shape.label,
        'new_users_only': _newUsersOnly,
        'first_order_only': _firstOrderOnly,
        'specific_hours': _specificHours,
        'repeat_promotion': _repeatPromotion,
        'repeat_days': _repeatDays.toList()..sort(),
        'valid_on': _validOn,
        'valid_time': _validTime,
        'show_usage': _showUsage,
        if (_freeItemId > 0) 'free_item_id': _freeItemId,
        if (comboGroups.isNotEmpty) 'combo_groups': comboGroups,
      },
      if (comboGroups.isNotEmpty) 'combo_groups': comboGroups,
      'application_mode': _applicationMode,
      'discount_type': _legacyDiscountType(_promotionType),
      'discount_value':
          _rewardNeedsValue ? (double.tryParse(_discountValue.text) ?? 0) : 0,
      'min_order_amount': double.tryParse(_minOrder.text),
      'max_discount_amount':
          _rewardNeedsMax ? double.tryParse(_maxDiscount.text) : null,
      'usage_limit': int.tryParse(_usageLimit.text),
      'per_user_limit': int.tryParse(_perUserLimit.text),
      'audience_type':
          (_newUsersOnly || _firstOrderOnly) ? 'new_customer' : _audienceType,
      'target_type': _promotionFor,
      'target_ids':
          _promotionFor == 'restaurant' ? <int>[] : _selectedTargetIds.toList(),
      'coupon_type': _applicationMode == 'coupon' && _specificUserOnly
          ? 'prepaid'
          : 'public',
      'assigned_to':
          _specificUserOnly ? int.tryParse(_assignedUserId.text.trim()) : null,
      'start_date': startAt.toIso8601String(),
      'end_date': endAt.toIso8601String(),
      'is_active': _isActive,
    };

    try {
      final id = _isEditing ? _promoActionId(widget.promo!) : null;
      final response = _promoImagePath == null
          ? (_isEditing
              ? await _api.put('${ApiConstants.restaurantPromos}/$id',
                  data: payload)
              : await _api.post(ApiConstants.restaurantPromos, data: payload))
          : await _api.postMultipart(
              _isEditing
                  ? '${ApiConstants.restaurantPromos}/$id'
                  : ApiConstants.restaurantPromos,
              fields: _stringifyPromoPayload(
                payload,
                methodOverride: _isEditing ? 'PUT' : null,
              ),
              files: {'promo_image': _promoImagePath!},
            );
      if (response['success'] == true && mounted) {
        if (_isEditing) {
          Navigator.pop(context, true);
        } else {
          Navigator.pushReplacement<bool, bool>(
            context,
            MaterialPageRoute(
              builder: (_) => PromotionCreatedScreen(
                promo: Map<String, dynamic>.from(response['data'] as Map),
              ),
            ),
            result: true,
          );
        }
      }
    } catch (e) {
      debugPrint('Save promo error: $e');
      _toast('Could not save promotion. Please try again.');
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  bool _hasValidTargetSelection() {
    if (_isComboPromotion) {
      return _promotionFor == 'items' && _selectedTargetIds.length >= 2;
    }
    return _promotionFor == 'restaurant' || _selectedTargetIds.isNotEmpty;
  }

  bool get _isComboPromotion =>
      _shape.rewardType == 'combo_deal' || _shape.rewardType == 'meal_deal';

  List<Map<String, dynamic>> _comboGroupPayload() {
    if (!_isComboPromotion || _promotionFor != 'items') {
      return const <Map<String, dynamic>>[];
    }
    final itemIds = _selectedTargetIds.toList()..sort();
    if (itemIds.length < 2) return const <Map<String, dynamic>>[];

    final effectivePrice = double.tryParse(_discountValue.text) ?? 0;
    if (effectivePrice <= 0) return const <Map<String, dynamic>>[];

    final actualPrice = itemIds.fold<double>(
      0,
      (sum, id) => sum + _menuItemPrice(id),
    );
    final discountPercent = actualPrice > 0
        ? (((actualPrice - effectivePrice) / actualPrice) * 100)
            .clamp(0, double.infinity)
        : 0;

    return <Map<String, dynamic>>[
      {
        'name': _title.text.trim().isEmpty ? _shape.label : _title.text.trim(),
        'item_ids': itemIds,
        'actual_price': actualPrice,
        'price': effectivePrice,
        'discount_percent': discountPercent,
      }
    ];
  }

  double _menuItemPrice(int id) {
    for (final row in _menuItems) {
      if (_rowId(row) == id) {
        final discounted =
            _toDouble(row['discounted_price'] ?? row['final_price']);
        if (discounted > 0) return discounted;
        return _toDouble(row['price']);
      }
    }
    return 0;
  }

  Map<String, String> _stringifyPromoPayload(
    Map<String, dynamic> payload, {
    String? methodOverride,
  }) {
    final fields = <String, String>{};
    if (methodOverride != null) fields['_method'] = methodOverride;
    for (final entry in payload.entries) {
      final value = entry.value;
      if (value == null) continue;
      fields[entry.key] =
          value is List || value is Map ? jsonEncode(value) : value.toString();
    }
    return fields;
  }

  List<Map<String, dynamic>> _responseRows(dynamic response) {
    final data = response is Map ? response['data'] : null;
    final rows = data is List
        ? data
        : data is Map && data['data'] is List
            ? data['data'] as List
            : const [];

    return rows
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();
  }

  Set<int> _parseIdSet(dynamic value) {
    dynamic source = value;
    if (source is String && source.trim().isNotEmpty) {
      try {
        source = jsonDecode(source);
      } catch (_) {
        source = source.split(',');
      }
    }

    if (source is Map) {
      source = source['ids'] ??
          source['target_ids'] ??
          source['categories'] ??
          source['items'];
    }

    if (source is! Iterable) return {};
    return source
        .map((id) => id is num ? id.toInt() : int.tryParse(id.toString()))
        .whereType<int>()
        .where((id) => id > 0)
        .toSet();
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return Map<String, dynamic>.from(value);
    return <String, dynamic>{};
  }

  String _promotionTypeFromLegacy(Map<String, dynamic> promo) {
    final discountType = promo['discount_type']?.toString();
    return discountType == 'fixed' ? 'flat_discount' : 'percentage_discount';
  }

  void _next() {
    if (!_formKey.currentState!.validate()) return;
    if (_step < _lastStep) {
      setState(() => _step++);
    } else {
      _save();
    }
  }

  void _back() {
    if (_step > 0) {
      setState(() => _step--);
    } else {
      Navigator.pop(context);
    }
  }

  Future<void> _pickDate({required bool start}) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: start ? _startDate : _endDate,
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (picked == null) return;
    setState(() {
      if (start) {
        _startDate = picked;
        if (_endDate.isBefore(_startDate)) {
          _endDate = picked.add(const Duration(days: 7));
        }
      } else {
        _endDate = picked;
      }
    });
  }

  Future<void> _pickTime({required bool start}) async {
    final picked = await showTimePicker(
      context: context,
      initialTime: start ? _startTime : _endTime,
    );
    if (picked == null) return;
    setState(() {
      if (start) {
        _startTime = picked;
      } else {
        _endTime = picked;
      }
    });
  }

  Future<void> _pickPromoImage() async {
    final picked = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
      maxWidth: 1600,
    );
    if (picked == null) return;
    setState(() => _promoImagePath = picked.path);
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  void _toggleTargetId(int id) {
    setState(() {
      if (_selectedTargetIds.contains(id)) {
        _selectedTargetIds.remove(id);
      } else {
        _selectedTargetIds.add(id);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: foodflow.orange.withOpacity(0.04),
      appBar: AppBar(
        elevation: 0,
        backgroundColor: foodflow.orange.withOpacity(0.04),
        foregroundColor: foodflow.ink,
        title: Text(
          _isEditing ? 'Edit Promotion' : 'Create Promotion',
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
        ),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              _StepDots(current: _step, total: _stepCount),
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 180),
                    child: _buildStep(),
                  ),
                ),
              ),
              _EditorFooter(
                isLast: _step == _lastStep,
                isSaving: _isSaving,
                onBack: _back,
                onNext: _next,
                nextLabel: _step == _lastStep
                    ? (_isEditing ? 'Update Promotion' : 'Create Promotion')
                    : 'Next',
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStep() {
    final steps = <Widget Function()>[
      _generalStep,
      _rewardsStep,
      _conditionsStep,
      _scheduleStep,
      _limitsStep,
      if (_applicationMode == 'coupon') _couponsStep,
      _previewStep,
    ];
    final index = _step.clamp(0, steps.length - 1);
    return steps[index]();
  }

  Widget _generalStep() {
    return _StepSection(
      key: const ValueKey('general'),
      title: 'General Information',
      child: Column(
        children: [
          _PromoTextField(
            controller: _title,
            label: 'Promotion Title',
            required: true,
            hint: 'Enter promotion title',
          ),
          _PromoTextField(
            controller: _description,
            label: 'Short Description',
            hint: 'Describe the customer benefit',
            maxLength: 120,
          ),
          _ImagePlaceholder(
            imagePath: _promoImagePath,
            imageUrl: _promoImageUrl,
            onTap: _pickPromoImage,
          ),
        ],
      ),
    );
  }

  Widget _rewardsStep() {
    return _StepSection(
      key: const ValueKey('rewards'),
      title: 'Rewards',
      subtitle: 'What customer gets',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _FieldLabel('Promotion Shape'),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 2.1,
            children: _promotionShapeEntries.map((entry) {
              final shape = entry.value;
              return _RewardTypeCard(
                label: shape.label,
                icon: shape.icon,
                active: _promotionType == entry.key,
                onTap: () => setState(() {
                  _promotionType = entry.key;
                  _discountType = shape.rewardType;
                  _buyQuantity.text = shape.defaultBuy.toString();
                  _freeQuantity.text = shape.defaultFree.toString();
                  _freeItemId = 0;
                  if (shape.preferredTarget != null) {
                    _promotionFor = shape.preferredTarget!;
                    _selectedTargetIds.clear();
                  }
                }),
              );
            }).toList(),
          ),
          const SizedBox(height: 18),
          if (_rewardNeedsValue)
            _PromoTextField(
              controller: _discountValue,
              label: _rewardValueLabel(),
              required: true,
              keyboardType: TextInputType.number,
              suffixText: _rewardIsMoney ? null : _rewardValueSuffix(),
              prefixText: _rewardIsMoney ? currencyInputPrefix(context) : null,
            ),
          if (_rewardNeedsMax)
            _PromoTextField(
              controller: _maxDiscount,
              label: 'Maximum Discount',
              hint: '100',
              keyboardType: TextInputType.number,
              prefixText: currencyInputPrefix(context),
            ),
          if (_shape.needsBuyFree) ...[
            Row(
              children: [
                Expanded(
                  child: _PromoTextField(
                    controller: _buyQuantity,
                    label: 'Buy Quantity',
                    required: true,
                    keyboardType: TextInputType.number,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _PromoTextField(
                    controller: _freeQuantity,
                    label: 'Free Quantity',
                    required: true,
                    keyboardType: TextInputType.number,
                  ),
                ),
              ],
            ),
          ],
          if (_shape.needsBuyFree || _promotionType == 'free_item')
            _PromoDropdown<int>(
              label: 'Reward Item',
              value: _freeItemId,
              items: _rewardItemOptions(),
              onChanged: (value) => setState(() => _freeItemId = value),
            ),
          _InfoBox(
            text: _rewardInfoText(),
          ),
        ],
      ),
    );
  }

  Widget _conditionsStep() {
    return _StepSection(
      key: const ValueKey('conditions'),
      title: 'Conditions',
      subtitle: 'When it applies',
      child: Column(
        children: [
          _PromoTextField(
            controller: _minOrder,
            label: 'Minimum Order Value',
            required: true,
            keyboardType: TextInputType.number,
            prefixText: currencyInputPrefix(context),
          ),
          _PromoDropdown<String>(
            label: 'Audience',
            value: _audienceType,
            items: const {
              'all': 'All Customers',
              'new_customer': 'New Customers',
              'returning_customer': 'Returning Customers',
            },
            onChanged: (value) => setState(() {
              _audienceType = value;
              _newUsersOnly = value == 'new_customer';
              if (value != 'new_customer') {
                _firstOrderOnly = false;
              }
            }),
          ),
          if (_audienceType == 'new_customer')
            const _InfoBox(
              text:
                  'Only customers without previous orders can use this promotion.',
            ),
          if (_audienceType == 'returning_customer')
            const _InfoBox(
              text:
                  'Only customers with at least one previous order can use this promotion.',
            ),
          _SegmentedButtons(
            label: 'Apply Mode',
            value: _applicationMode,
            values: const {
              'automatic': 'Automatic',
              'coupon': 'Coupon',
            },
            onChanged: (value) => setState(() {
              _applicationMode = value;
              if (value == 'automatic') {
                _specificUserOnly = false;
                _couponType = 'public';
              }
              if (_step > _lastStep) {
                _step = _lastStep;
              }
            }),
          ),
          _PromoDropdown<String>(
            label: 'Valid On',
            value: _validOn,
            items: const {
              'All Days': 'All Days',
              'Weekdays': 'Weekdays',
              'Weekends': 'Weekends',
            },
            onChanged: (value) => setState(() => _validOn = value),
          ),
          _PromoDropdown<String>(
            label: 'Valid Time',
            value: _validTime,
            items: const {
              'All Day': 'All Day',
              'Lunch': 'Lunch',
              'Dinner': 'Dinner',
              'Late Night': 'Late Night',
            },
            onChanged: (value) => setState(() => _validTime = value),
          ),
          _SegmentedButtons(
            label: 'Applicable On',
            value: _promotionFor,
            values: const {
              'restaurant': 'All Items',
              'categories': 'Selected Categories',
              'items': 'Selected Items',
            },
            onChanged: (value) {
              setState(() {
                _promotionFor = value;
                _selectedTargetIds.clear();
              });
            },
          ),
          if (_promotionFor != 'restaurant') ...[
            _InfoBox(
              text:
                  'Select mapped ${_promotionFor == 'categories' ? 'categories' : 'items'} for this ${_shape.label.toLowerCase()}.',
            ),
            _TargetSelectionBox(
              type: _promotionFor,
              isLoading: _isLoadingTargets,
              rows: _targetRows,
              selectedIds: _selectedTargetIds,
              onRetry: _loadPromotionTargets,
              onToggle: _toggleTargetId,
            ),
          ],
          _SettingSwitch(
            title: 'New Users Only',
            value: _newUsersOnly,
            onChanged: (value) => setState(() {
              _newUsersOnly = value;
              _audienceType =
                  (_newUsersOnly || _firstOrderOnly) ? 'new_customer' : 'all';
            }),
          ),
          _SettingSwitch(
            title: 'First Order Only',
            value: _firstOrderOnly,
            onChanged: (value) => setState(() {
              _firstOrderOnly = value;
              _audienceType =
                  (_newUsersOnly || _firstOrderOnly) ? 'new_customer' : 'all';
            }),
          ),
          _SettingSwitch(
            title: 'Specific User',
            value: _specificUserOnly,
            onChanged: (value) => setState(() {
              _specificUserOnly = value;
              _applicationMode = 'coupon';
              _couponType = value ? 'prepaid' : 'public';
            }),
          ),
          if (_specificUserOnly)
            _PromoTextField(
              controller: _assignedUserId,
              label: 'Assigned Customer ID',
              required: true,
              keyboardType: TextInputType.number,
              hint: 'Customer user ID',
            ),
        ],
      ),
    );
  }

  Widget _scheduleStep() {
    return _StepSection(
      key: const ValueKey('schedule'),
      title: 'Schedule',
      child: Column(
        children: [
          _DateTimeRow(
            label: _specificHours ? 'Start Date & Time' : 'Start Date',
            date: _startDate,
            time: _startTime,
            showTime: _specificHours,
            onPickDate: () => _pickDate(start: true),
            onPickTime: () => _pickTime(start: true),
          ),
          _DateTimeRow(
            label: _specificHours ? 'End Date & Time' : 'End Date',
            date: _endDate,
            time: _endTime,
            showTime: _specificHours,
            onPickDate: () => _pickDate(start: false),
            onPickTime: () => _pickTime(start: false),
          ),
          _SettingSwitch(
            title: 'Set time for specific hours',
            value: _specificHours,
            onChanged: (value) => setState(() => _specificHours = value),
          ),
          _SettingSwitch(
            title: 'Repeat Promotion',
            value: _repeatPromotion,
            onChanged: (value) => setState(() => _repeatPromotion = value),
          ),
          if (_repeatPromotion) _RepeatDaysPicker(days: _repeatDays),
          if (_repeatPromotion)
            _InfoBox(
              text:
                  'This promotion will repeat every week on selected days till ${DateFormat('dd MMM yyyy').format(_endDate)}.',
            ),
          if (!_repeatPromotion)
            const _InfoBox(
              text: 'This promotion will run once during the selected dates.',
            ),
        ],
      ),
    );
  }

  Widget _limitsStep() {
    return _StepSection(
      key: const ValueKey('limits'),
      title: 'Limits',
      subtitle: 'Usage & Budget',
      child: Column(
        children: [
          _PromoTextField(
            controller: _usageLimit,
            label: 'Total Usage Limit',
            keyboardType: TextInputType.number,
          ),
          _PromoTextField(
            controller: _perUserLimit,
            label: 'Per User Limit',
            keyboardType: TextInputType.number,
          ),
          _PromoTextField(
            controller: _budgetLimit,
            label: 'Budget Limit',
            keyboardType: TextInputType.number,
            prefixText: currencyInputPrefix(context),
          ),
          _SettingSwitch(
            title: 'Show usage count to customers',
            value: _showUsage,
            onChanged: (value) => setState(() => _showUsage = value),
          ),
        ],
      ),
    );
  }

  Widget _couponsStep() {
    return _StepSection(
      key: const ValueKey('coupons'),
      title: 'Coupons',
      subtitle: 'If coupon based',
      child: Column(
        children: [
          if (_applicationMode == 'automatic') ...[
            _InfoBox(
              tone: _InfoTone.warning,
              text:
                  'You have selected Automatic mode. Coupons are not required.',
            ),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () => setState(() => _applicationMode = 'coupon'),
                child: const Text('Change to Coupon Mode'),
              ),
            ),
            const SizedBox(height: 54),
            Icon(
              Icons.discount_outlined,
              color: foodflow.line,
              size: 110,
            ),
            const SizedBox(height: 16),
            const Text(
              'This promotion will be applied automatically at checkout.',
              textAlign: TextAlign.center,
              style:
                  TextStyle(color: foodflow.muted, fontWeight: FontWeight.w700),
            ),
          ] else ...[
            _PromoTextField(
              controller: _couponCode,
              label: 'Coupon Code',
              required: true,
              hint: 'SAVE20',
              onChanged: (_) => setState(() {}),
            ),
            _PromoDropdown<String>(
              label: 'Coupon Availability',
              value: _couponType,
              items: const {
                'public': 'Anyone can use',
                'prepaid': 'Assigned customer only',
              },
              onChanged: (value) => setState(() {
                _couponType = value;
                _specificUserOnly = value == 'prepaid';
              }),
            ),
            if (_couponType == 'prepaid')
              _PromoTextField(
                controller: _assignedUserId,
                label: 'Assigned Customer ID',
                required: true,
                keyboardType: TextInputType.number,
                hint: 'Customer user ID',
              ),
            _InfoBox(
                text:
                    'Coupon code preview: ${_couponCode.text.isEmpty ? _generateCode(_title.text) : _couponCode.text.toUpperCase()}'),
          ],
        ],
      ),
    );
  }

  Widget _previewStep() {
    final minOrder = double.tryParse(_minOrder.text) ?? 0;
    final reward = _rewardPreview();
    final validTill = DateTime(
      _endDate.year,
      _endDate.month,
      _endDate.day,
      _specificHours ? _endTime.hour : 23,
      _specificHours ? _endTime.minute : 59,
    );

    return _StepSection(
      key: const ValueKey('preview'),
      title: 'Preview',
      child: Column(
        children: [
          _PreviewBanner(
            title: _title.text.isEmpty ? 'Promotion Preview' : _title.text,
            reward: reward,
            imagePath: _promoImagePath,
            imageUrl: _promoImageUrl,
          ),
          const SizedBox(height: 18),
          _PreviewRow(label: 'Promotion Shape', value: _shape.label),
          _PreviewRow(label: 'Reward', value: reward),
          _PreviewRow(
            label: 'Min. Order Value',
            value: minOrder > 0
                ? formatCurrencyValue(context, minOrder)
                : 'No minimum',
          ),
          _PreviewRow(label: 'Valid On', value: '$_validOn, $_validTime'),
          _PreviewRow(
            label: 'Valid Till',
            value: DateFormat('dd MMM yyyy, hh:mm a').format(validTill),
          ),
          _PreviewRow(label: 'Audience', value: _audienceLabel(_audienceType)),
          _PreviewRow(label: 'For', value: _targetSummary()),
          _PreviewRow(
              label: 'Application',
              value: _applicationMode == 'automatic' ? 'Automatic' : 'Coupon'),
          if (_applicationMode == 'coupon')
            _PreviewRow(
              label: 'Coupon',
              value: _couponCode.text.trim().isEmpty
                  ? _generateCode(_title.text)
                  : _couponCode.text.trim().toUpperCase(),
            ),
          if (_applicationMode == 'coupon')
            _PreviewRow(
              label: 'Availability',
              value: _couponAvailabilityLabel(_couponType),
            ),
        ],
      ),
    );
  }

  String _rewardInfoText() {
    final rewardItem = _freeItemId > 0
        ? _menuItems
            .where((item) => _rowId(item) == _freeItemId)
            .map(_rowTitle)
            .firstOrNull
        : null;
    if (_shape.needsBuyFree) {
      final rewardDescription = rewardItem ?? 'the same eligible item';
      return 'Customer must buy ${_buyQuantity.text.trim().isEmpty ? _shape.defaultBuy : _buyQuantity.text} and gets ${_freeQuantity.text.trim().isEmpty ? _shape.defaultFree : _freeQuantity.text} $rewardDescription free.';
    }
    if (!_rewardNeedsValue) return _shape.hint;
    final value = double.tryParse(_discountValue.text) ?? 0;
    final max = double.tryParse(_maxDiscount.text) ?? 0;
    if (_rewardIsPoints) {
      return 'Customer will get ${value.toStringAsFixed(0)} reward points on eligible orders.';
    }
    if (!_rewardIsMoney) {
      return 'Customer will get ${value.toStringAsFixed(0)}% OFF upto ${formatCurrencyValue(context, max)} on eligible orders.';
    }
    return 'Customer will get ${formatCurrencyValue(context, value)} benefit on eligible orders.';
  }

  Map<int, String> _rewardItemOptions() {
    final options = <int, String>{0: 'Same as eligible item'};
    for (final item in _menuItems) {
      final id = _rowId(item);
      if (id > 0) options[id] = _rowTitle(item);
    }
    if (_freeItemId > 0 && !options.containsKey(_freeItemId)) {
      options[_freeItemId] = 'Selected menu item';
    }
    return options;
  }

  String _rewardPreview() {
    final value = double.tryParse(_discountValue.text) ?? 0;
    final maxDiscount = double.tryParse(_maxDiscount.text) ?? 0;
    if (_shape.needsBuyFree) {
      return 'Buy ${_buyQuantity.text.trim().isEmpty ? _shape.defaultBuy : _buyQuantity.text} Get ${_freeQuantity.text.trim().isEmpty ? _shape.defaultFree : _freeQuantity.text}';
    }
    if (_shape.noValueRequired) return _shape.label;
    if (_rewardIsPoints) return '${value.toStringAsFixed(0)} Reward Points';
    if (_rewardIsMoney)
      return '${formatCurrencyValue(context, value)} ${_shape.label}';
    final cap = maxDiscount > 0
        ? ' upto ${formatCurrencyValue(context, maxDiscount)}'
        : '';
    return '${value.toStringAsFixed(0)}% ${_shape.label}$cap';
  }

  String _rewardValueLabel() {
    switch (_shape.rewardType) {
      case 'fixed_price':
        return 'Fixed Price';
      case 'combo_deal':
        return 'Combo Deal Amount';
      case 'meal_deal':
        return 'Meal Deal Amount';
      default:
        return _rewardIsMoney ? 'Reward Amount' : 'Reward Percentage';
    }
  }

  String? _rewardValueSuffix() {
    if (_shape.rewardType == 'reward_points') return 'points';
    return '%';
  }

  String _audienceLabel(String value) {
    switch (value) {
      case 'new_customer':
        return 'New Customers';
      case 'returning_customer':
        return 'Returning Customers';
      default:
        return 'All Customers';
    }
  }

  String _couponAvailabilityLabel(String value) {
    return value == 'prepaid' ? 'Assigned customer only' : 'Anyone can use';
  }

  String _targetSummary() {
    if (_promotionFor == 'restaurant') return 'All Items';
    if (_selectedTargetIds.isEmpty) {
      return _promotionFor == 'categories'
          ? 'No categories selected'
          : 'No items selected';
    }

    final rowsById = {
      for (final row in _targetRows) _rowId(row): _rowTitle(row),
    };
    final names = _selectedTargetIds
        .map((id) => rowsById[id])
        .whereType<String>()
        .where((name) => name.trim().isNotEmpty)
        .take(2)
        .toList();
    final fallbackLabel =
        '${_selectedTargetIds.length} ${_promotionFor == 'categories' ? 'categories' : 'items'}';
    if (names.isEmpty) return fallbackLabel;
    final extra = _selectedTargetIds.length - names.length;
    return extra > 0 ? '${names.join(', ')} +$extra more' : names.join(', ');
  }
}

class PromotionCreatedScreen extends StatelessWidget {
  const PromotionCreatedScreen({super.key, required this.promo});

  final Map<String, dynamic> promo;

  @override
  Widget build(BuildContext context) {
    final promotionType =
        promo['promotion_type']?.toString() ?? _promotionTypeFromLegacy(promo);
    final shape = _restaurantPromoShape(promotionType);
    final rewardConfig = promo['reward_config'] is Map
        ? Map<String, dynamic>.from(promo['reward_config'] as Map)
        : <String, dynamic>{};
    final reward = _promoRewardLabel(
      context,
      shape,
      _toDouble(promo['discount_value']),
      _toDouble(promo['max_discount_amount']),
      rewardConfig,
    );
    final minOrder = _toDouble(promo['min_order_amount']);
    final endDate = _parseDate(promo['end_date'] ?? promo['valid_to']);
    final code = (promo['code'] ?? promo['coupon_code'] ?? '').toString();
    final targetLabel = _promoTargetLabel(promo);
    final isActive = promo['is_active'] != false;

    return Scaffold(
      backgroundColor: foodflow.orange.withOpacity(0.04),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: Stack(
                children: [
                  Positioned.fill(
                    child: CustomPaint(
                      painter: _PromoSketchPainter(foodflow.orange),
                    ),
                  ),
                  ListView(
                    padding: const EdgeInsets.fromLTRB(20, 10, 20, 24),
                    children: [
                      Align(
                        alignment: Alignment.centerRight,
                        child: IconButton(
                          onPressed: () => Navigator.pop(context, true),
                          icon: const Icon(Icons.close_rounded),
                        ),
                      ),
                      const _CreatedCheckMark(),
                      const SizedBox(height: 18),
                      const Text(
                        'Promotion is live',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: foodflow.ink,
                          fontSize: 28,
                          fontWeight: FontWeight.w900,
                          height: 1.05,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        code.trim().isEmpty
                            ? 'Customers can now see this offer on eligible orders.'
                            : 'Coupon $code is ready for eligible orders.',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: foodflow.muted,
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 22),
                      _CreatedPromotionHero(
                        title: _promoTitle(promo),
                        reward: reward,
                        shape: shape.label,
                        code: code,
                        imageUrl: _promoImage(promo),
                        isActive: isActive,
                      ),
                      const SizedBox(height: 16),
                      _CreatedSummaryCard(
                        children: [
                          _PreviewRow(
                              label: 'Promotion Shape', value: shape.label),
                          _PreviewRow(label: 'Reward', value: reward),
                          _PreviewRow(
                            label: 'Minimum Order',
                            value: minOrder > 0
                                ? formatCurrencyValue(context, minOrder)
                                : 'No minimum',
                          ),
                          _PreviewRow(label: 'Applies To', value: targetLabel),
                          _PreviewRow(
                            label: 'Valid Till',
                            value: endDate == null
                                ? 'No end date'
                                : DateFormat('dd MMM yyyy, hh:mm a')
                                    .format(endDate),
                          ),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 18),
              decoration: const BoxDecoration(
                color: Colors.white,
                border: Border(top: BorderSide(color: foodflow.line)),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _BrandActionButton(
                    onPressed: () => Navigator.pop(context, true),
                    icon: Icons.local_offer_outlined,
                    label: 'View Promotions',
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed: () {
                      Navigator.pushReplacement(
                        context,
                        MaterialPageRoute(
                            builder: (_) => const PromotionEditorScreen()),
                      );
                    },
                    child: const Text('Create Another'),
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

class _CreatedCheckMark extends StatelessWidget {
  const _CreatedCheckMark();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 104,
        height: 104,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: const LinearGradient(
            colors: [Color(0xFF22C55E), Color(0xFF16A34A)],
          ),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF16A34A).withOpacity(0.28),
              blurRadius: 28,
              offset: const Offset(0, 14),
            ),
          ],
        ),
        child: const Icon(Icons.check_rounded, color: Colors.white, size: 58),
      ),
    );
  }
}

class _CreatedPromotionHero extends StatelessWidget {
  const _CreatedPromotionHero({
    required this.title,
    required this.reward,
    required this.shape,
    required this.code,
    required this.imageUrl,
    required this.isActive,
  });

  final String title;
  final String reward;
  final String shape;
  final String code;
  final String imageUrl;
  final bool isActive;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFFF7A00), Color(0xFFE53935)],
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: foodflow.orange.withOpacity(0.24),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.18),
                  borderRadius: BorderRadius.circular(13),
                  border: Border.all(color: Colors.white.withOpacity(0.2)),
                ),
                clipBehavior: Clip.antiAlias,
                child: imageUrl.isNotEmpty
                    ? NetworkImageLoader(
                        imageUrl: imageUrl,
                        fit: BoxFit.cover,
                        errorWidget: const Icon(
                          Icons.local_offer_outlined,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.local_offer_outlined,
                        color: Colors.white),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  shape.toUpperCase(),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.9),
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              _StatusPill(
                label: isActive ? 'Active' : 'Draft',
                color: Colors.white,
              ),
            ],
          ),
          const SizedBox(height: 22),
          Text(
            reward,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 30,
              height: 1,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: Colors.white.withOpacity(0.92),
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
          if (code.trim().isNotEmpty) ...[
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.18),
                borderRadius: BorderRadius.circular(999),
                border: Border.all(color: Colors.white.withOpacity(0.22)),
              ),
              child: Text(
                'Code $code',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _CreatedSummaryCard extends StatelessWidget {
  const _CreatedSummaryCard({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: _partnerPanelDecoration(radius: 22),
      child: Column(children: children),
    );
  }
}

class PromotionDetailsScreen extends StatelessWidget {
  const PromotionDetailsScreen({
    super.key,
    required this.promo,
    required this.onEdit,
    required this.onToggle,
    required this.onDelete,
  });

  final Map<String, dynamic> promo;
  final Future<void> Function() onEdit;
  final Future<void> Function() onToggle;
  final Future<void> Function() onDelete;

  @override
  Widget build(BuildContext context) {
    final isActive = promo['is_active'] != false;
    final value = _toDouble(promo['discount_value']);
    final max = _toDouble(promo['max_discount_amount']);
    final minOrder = _toDouble(promo['min_order_amount']);
    final usage = int.tryParse(promo['used_count']?.toString() ?? '') ?? 0;
    final limit = int.tryParse(promo['usage_limit']?.toString() ?? '') ?? 0;
    final endDate = _parseDate(promo['end_date'] ?? promo['valid_to']);
    final promotionType =
        promo['promotion_type']?.toString() ?? _promotionTypeFromLegacy(promo);
    final shape = _restaurantPromoShape(promotionType);
    final config = promo['reward_config'] is Map
        ? Map<String, dynamic>.from(promo['reward_config'] as Map)
        : <String, dynamic>{};
    final reward = _promoRewardLabel(
      context,
      shape,
      value,
      max,
      config,
    );
    final targetLabel = _promoTargetLabel(promo);
    final applicationLabel = _promoApplicationLabel(promo);
    final totalDiscount = _promoTotalDiscount(promo);

    return Scaffold(
      backgroundColor: foodflow.orange.withOpacity(0.04),
      body: SafeArea(
        child: Column(
          children: [
            Container(
              decoration: BoxDecoration(
                color: foodflow.orange.withOpacity(0.04),
              ),
              child: Stack(
                children: [
                  Positioned.fill(
                    child: CustomPaint(
                      painter: _PromoSketchPainter(foodflow.orange),
                    ),
                  ),
                  Column(
                    children: [
                      AppBar(
                        backgroundColor: Colors.transparent,
                        elevation: 0,
                        foregroundColor: foodflow.ink,
                        title: const Text(
                          'Promotion Details',
                          style: TextStyle(
                              fontSize: 15, fontWeight: FontWeight.w900),
                        ),
                        centerTitle: true,
                        actions: [
                          IconButton(
                            onPressed: () async {
                              await onEdit();
                              if (context.mounted) Navigator.pop(context, true);
                            },
                            icon: const Icon(Icons.edit),
                          ),
                          PopupMenuButton<String>(
                            color: Colors.white,
                            onSelected: (value) async {
                              if (value == 'delete') {
                                await onDelete();
                                if (context.mounted)
                                  Navigator.pop(context, true);
                              }
                            },
                            itemBuilder: (_) => const [
                              PopupMenuItem(
                                  value: 'delete', child: Text('Delete')),
                            ],
                          ),
                        ],
                      ),
                      _DetailsBanner(
                        title: _promoTitle(promo),
                        reward: reward,
                        shape: shape.label,
                        imageUrl: _promoImage(promo),
                        isActive: isActive,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  _PreviewRow(label: 'Promotion Shape', value: shape.label),
                  _PreviewRow(label: 'Reward', value: reward),
                  _PreviewRow(
                      label: 'Min. Order Value',
                      value: minOrder > 0
                          ? formatCurrencyValue(context, minOrder)
                          : 'No minimum'),
                  _PreviewRow(
                    label: 'Valid Till',
                    value: endDate == null
                        ? 'No end date'
                        : DateFormat('dd MMM yyyy, hh:mm a').format(endDate),
                  ),
                  _PreviewRow(label: 'For', value: targetLabel),
                  _PreviewRow(
                      label: 'Application Mode', value: applicationLabel),
                  const SizedBox(height: 22),
                  const Text(
                    'Usage Summary',
                    style: TextStyle(
                      color: foodflow.ink,
                      fontSize: 16,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _UsageBox(
                    usage: usage,
                    limit: limit,
                    totalDiscount: totalDiscount,
                  ),
                  const SizedBox(height: 22),
                  OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.red,
                      side: const BorderSide(color: Colors.red),
                    ),
                    onPressed: () async {
                      await onToggle();
                      if (context.mounted) Navigator.pop(context, true);
                    },
                    child: Text(isActive
                        ? 'Deactivate Promotion'
                        : 'Activate Promotion'),
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

class _PromosHero extends StatelessWidget {
  const _PromosHero({
    required this.restaurantName,
    required this.total,
    required this.active,
    required this.scheduled,
    required this.draft,
    required this.onCreate,
  });

  final String restaurantName;
  final int total;
  final int active;
  final int scheduled;
  final int draft;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: foodflow.orange.withOpacity(0.04),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(22)),
      ),
      child: Stack(
        children: [
          Positioned.fill(
            child: CustomPaint(
              painter: _PromoSketchPainter(foodflow.orange),
            ),
          ),
          Positioned(
            top: 42,
            right: -18,
            child: Opacity(
              opacity: 0.96,
              child: Image.asset(
                'assets/images/offer.png',
                width: 154,
                height: 126,
                fit: BoxFit.contain,
              ),
            ),
          ),
          Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 18),
                child: Row(
                  children: [
                    Icon(Icons.menu, color: foodflow.orange),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            restaurantName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: foodflow.ink,
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                            ),
                          ),
                          const Text(
                            'Promotions',
                            style:
                                TextStyle(color: foodflow.muted, fontSize: 11),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 4, 154, 12),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text.rich(
                    TextSpan(
                      text: 'Create offers,\n',
                      style: TextStyle(
                        color: foodflow.orange,
                        fontSize: 22,
                        height: 1.05,
                        fontWeight: FontWeight.w900,
                      ),
                      children: const [
                        TextSpan(
                          text: 'grow orders.',
                          style: TextStyle(
                            color: foodflow.ink,
                            fontSize: 30,
                            height: 1.02,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              Container(
                margin: const EdgeInsets.fromLTRB(20, 0, 20, 18),
                padding: const EdgeInsets.all(18),
                decoration: _partnerPanelDecoration(radius: 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Total Promotions',
                      style: TextStyle(
                        color: foodflow.ink,
                        fontWeight: FontWeight.w800,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '$total',
                      style: const TextStyle(
                        color: foodflow.ink,
                        fontWeight: FontWeight.w900,
                        fontSize: 28,
                      ),
                    ),
                    const SizedBox(height: 14),
                    Row(
                      children: [
                        Expanded(
                            child: _HeroMetric(
                                label: 'Active',
                                value: active,
                                color: const Color(0xFF16A34A))),
                        Expanded(
                            child: _HeroMetric(
                                label: 'Scheduled',
                                value: scheduled,
                                color: foodflow.orange)),
                        Expanded(
                            child: _HeroMetric(
                                label: 'Draft',
                                value: draft,
                                color: foodflow.ink)),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _BrandActionButton(
                      onPressed: onCreate,
                      icon: Icons.add,
                      label: 'Create Promotion',
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _BrandActionButton extends StatelessWidget {
  const _BrandActionButton({
    required this.onPressed,
    required this.icon,
    required this.label,
  });

  final VoidCallback? onPressed;
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: _brandLiftDecoration(),
      child: SizedBox(
        width: double.infinity,
        height: 52,
        child: ElevatedButton.icon(
          onPressed: onPressed,
          icon: Icon(icon, size: 20),
          label: Text(label),
          style: ElevatedButton.styleFrom(
            disabledBackgroundColor: Colors.transparent,
            backgroundColor: Colors.transparent,
            foregroundColor: Colors.white,
            shadowColor: Colors.transparent,
            textStyle: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w900,
            ),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
      ),
    );
  }
}

class _PromoSketchPainter extends CustomPainter {
  const _PromoSketchPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final wash = Paint()
      ..color = color.withOpacity(0.045)
      ..style = PaintingStyle.fill;
    canvas.drawCircle(Offset(size.width * 0.86, size.height * 0.36), 112, wash);
    canvas.drawCircle(Offset(size.width * 0.16, size.height * 0.82), 58, wash);

    final paint = Paint()
      ..color = color.withOpacity(0.18)
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    final ticket = RRect.fromRectAndRadius(
      Rect.fromLTWH(size.width * 0.58, size.height * 0.52, 106, 58),
      const Radius.circular(12),
    );
    canvas.drawRRect(ticket, paint);
    canvas.drawCircle(
      Offset(ticket.left, ticket.center.dy),
      8,
      Paint()
        ..color = color.withOpacity(0.04)
        ..style = PaintingStyle.fill,
    );
    canvas.drawCircle(
      Offset(ticket.right, ticket.center.dy),
      8,
      Paint()
        ..color = color.withOpacity(0.04)
        ..style = PaintingStyle.fill,
    );
    canvas.drawLine(
      Offset(ticket.left + 28, ticket.center.dy),
      Offset(ticket.right - 28, ticket.center.dy),
      paint,
    );
    canvas.drawLine(
      Offset(ticket.left + 26, ticket.top + 16),
      Offset(ticket.left + 44, ticket.bottom - 16),
      paint,
    );
    canvas.drawCircle(Offset(ticket.right - 34, ticket.top + 18), 5, paint);
    canvas.drawCircle(Offset(ticket.right - 34, ticket.bottom - 18), 5, paint);

    final dotPaint = Paint()
      ..color = color.withOpacity(0.09)
      ..style = PaintingStyle.fill;
    for (var row = 0; row < 4; row++) {
      for (var col = 0; col < 5; col++) {
        canvas.drawCircle(
          Offset(size.width * 0.08 + col * 18.0, 44 + row * 18.0),
          3.5,
          dotPaint,
        );
      }
    }
  }

  @override
  bool shouldRepaint(covariant _PromoSketchPainter oldDelegate) {
    return oldDelegate.color != color;
  }
}

class _PromotionListTile extends StatelessWidget {
  const _PromotionListTile({
    required this.promo,
    required this.status,
    required this.statusColor,
    required this.onTap,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> promo;
  final String status;
  final Color statusColor;
  final VoidCallback onTap;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final minOrder = _toDouble(promo['min_order_amount']);
    final endDate = _parseDate(promo['end_date'] ?? promo['valid_to']);
    final promotionType =
        promo['promotion_type']?.toString() ?? _promotionTypeFromLegacy(promo);
    final shape = _restaurantPromoShape(promotionType);
    final config = promo['reward_config'] is Map
        ? Map<String, dynamic>.from(promo['reward_config'] as Map)
        : <String, dynamic>{};
    final reward = _promoRewardLabel(
      context,
      shape,
      _toDouble(promo['discount_value']),
      _toDouble(promo['max_discount_amount']),
      config,
    );
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: _partnerPanelDecoration(radius: 18),
        child: Row(
          children: [
            _PromotionThumb(size: 58, imageUrl: _promoImage(promo)),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _promoTitle(promo),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: foodflow.ink,
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    reward,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: foodflow.muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    [
                      if (minOrder > 0)
                        'Min. ${formatCurrencyValue(context, minOrder)}',
                      if (endDate != null)
                        'Till ${DateFormat('dd MMM yyyy').format(endDate)}',
                    ].isEmpty
                        ? shape.label
                        : [
                            if (minOrder > 0)
                              'Min. ${formatCurrencyValue(context, minOrder)}',
                            if (endDate != null)
                              'Till ${DateFormat('dd MMM yyyy').format(endDate)}',
                          ].join(' · '),
                    style: const TextStyle(
                      color: foodflow.muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                _StatusPill(label: status, color: statusColor),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined, size: 18),
                ),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  onPressed: onDelete,
                  color: Colors.red,
                  icon: const Icon(Icons.delete_outline, size: 18),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _StepDots extends StatelessWidget {
  const _StepDots({required this.current, required this.total});

  final int current;
  final int total;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(22, 10, 22, 8),
      child: Row(
        children: List.generate(total, (index) {
          final active = index == current;
          return Expanded(
            child: Row(
              children: [
                Expanded(
                  child: Container(
                    height: 1,
                    color: index == 0 ? Colors.transparent : foodflow.line,
                  ),
                ),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  width: active ? 22 : 18,
                  height: active ? 22 : 18,
                  decoration: BoxDecoration(
                    color: active ? foodflow.orange : Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: active ? foodflow.orange : foodflow.line,
                    ),
                  ),
                  child: Center(
                    child: Text(
                      '${index + 1}',
                      style: TextStyle(
                        color: active ? Colors.white : foodflow.muted,
                        fontSize: 10,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
                Expanded(
                  child: Container(
                    height: 1,
                    color:
                        index == total - 1 ? Colors.transparent : foodflow.line,
                  ),
                ),
              ],
            ),
          );
        }),
      ),
    );
  }
}

class _StepSection extends StatelessWidget {
  const _StepSection({
    super.key,
    required this.title,
    required this.child,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      key: key,
      padding: const EdgeInsets.fromLTRB(18, 20, 18, 8),
      decoration: _partnerPanelDecoration(radius: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RichText(
            text: TextSpan(
              text: title,
              style: const TextStyle(
                color: foodflow.ink,
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
              children: [
                if (subtitle != null)
                  TextSpan(
                    text: ' ($subtitle)',
                    style: const TextStyle(
                      color: foodflow.muted,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          child,
        ],
      ),
    );
  }
}

class _TargetSelectionBox extends StatelessWidget {
  const _TargetSelectionBox({
    required this.type,
    required this.isLoading,
    required this.rows,
    required this.selectedIds,
    required this.onToggle,
    required this.onRetry,
  });

  final String type;
  final bool isLoading;
  final List<Map<String, dynamic>> rows;
  final Set<int> selectedIds;
  final ValueChanged<int> onToggle;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    if (type == 'restaurant') {
      return const _InfoBox(
        text:
            'This promotion applies to every eligible item in your restaurant.',
      );
    }

    final label = type == 'categories' ? 'Categories' : 'Items';
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: foodflow.orange.withOpacity(0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: foodflow.orange.withOpacity(0.12)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Select $label',
                  style: const TextStyle(
                    color: foodflow.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Text(
                '${selectedIds.length} selected',
                style: const TextStyle(
                  color: foodflow.muted,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (isLoading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 18),
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            )
          else if (rows.isEmpty)
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  type == 'categories'
                      ? 'No categories found. Create categories in Menu first.'
                      : 'No menu items found. Add items in Menu first.',
                  style: const TextStyle(
                    color: foodflow.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh, size: 16),
                  label: const Text('Retry'),
                ),
              ],
            )
          else
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 260),
              child: SingleChildScrollView(
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: rows.map((row) {
                    final id = _rowId(row);
                    final selected = selectedIds.contains(id);
                    return _TargetChoiceChip(
                      title: _rowTitle(row),
                      subtitle: _rowSubtitle(row, type),
                      imageUrl: _rowImage(row),
                      selected: selected,
                      onTap: id <= 0 ? null : () => onToggle(id),
                    );
                  }).toList(),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _TargetChoiceChip extends StatelessWidget {
  const _TargetChoiceChip({
    required this.title,
    required this.subtitle,
    required this.imageUrl,
    required this.selected,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final String imageUrl;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        width: 148,
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: selected ? Colors.white : Colors.white.withOpacity(0.68),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? foodflow.orange : foodflow.line,
            width: selected ? 1.4 : 1,
          ),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: foodflow.orange.withOpacity(0.12),
                    blurRadius: 12,
                    offset: const Offset(0, 5),
                  ),
                ]
              : null,
        ),
        child: Row(
          children: [
            _TargetThumb(imageUrl: imageUrl, selected: selected),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: foodflow.ink,
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  if (subtitle.isNotEmpty)
                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: foodflow.muted,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
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

class _TargetThumb extends StatelessWidget {
  const _TargetThumb({required this.imageUrl, required this.selected});

  final String imageUrl;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: foodflow.orange.withOpacity(0.08),
            borderRadius: BorderRadius.circular(11),
            border: Border.all(color: foodflow.line),
          ),
          clipBehavior: Clip.antiAlias,
          child: imageUrl.isEmpty
              ? Icon(Icons.image_outlined, color: foodflow.orange, size: 18)
              : NetworkImageLoader(
                  imageUrl: imageUrl,
                  fit: BoxFit.cover,
                  errorWidget: Icon(
                    Icons.image_not_supported_outlined,
                    color: foodflow.faint,
                    size: 17,
                  ),
                ),
        ),
        Positioned(
          right: -4,
          bottom: -4,
          child: Icon(
            selected
                ? Icons.check_circle_rounded
                : Icons.radio_button_unchecked_rounded,
            color: selected ? foodflow.orange : foodflow.faint,
            size: 16,
          ),
        ),
      ],
    );
  }
}

class _PromoTextField extends StatelessWidget {
  const _PromoTextField({
    required this.controller,
    required this.label,
    this.hint,
    this.required = false,
    this.keyboardType,
    this.prefixText,
    this.suffixText,
    this.maxLength,
    this.onChanged,
  });

  final TextEditingController controller;
  final String label;
  final String? hint;
  final bool required;
  final TextInputType? keyboardType;
  final String? prefixText;
  final String? suffixText;
  final int? maxLength;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _FieldLabel(label, required: required),
          TextFormField(
            controller: controller,
            keyboardType: keyboardType,
            maxLength: maxLength,
            maxLines: 1,
            onChanged: onChanged,
            validator: required
                ? (value) =>
                    value == null || value.trim().isEmpty ? 'Required' : null
                : null,
            decoration: InputDecoration(
              hintText: hint,
              prefixText: prefixText,
              suffixText: suffixText,
              counterText: maxLength == null ? null : '',
              filled: true,
              fillColor: Colors.white,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: foodflow.line),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: foodflow.line),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: BorderSide(color: foodflow.orange, width: 1.4),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PromoDropdown<T> extends StatelessWidget {
  const _PromoDropdown({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  final String label;
  final T value;
  final Map<T, String> items;
  final ValueChanged<T> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _FieldLabel(label),
          DropdownButtonFormField<T>(
            value: value,
            items: items.entries
                .map((entry) => DropdownMenuItem<T>(
                      value: entry.key,
                      child: Text(entry.value),
                    ))
                .toList(),
            onChanged: (value) {
              if (value != null) onChanged(value);
            },
            decoration: InputDecoration(
              filled: true,
              fillColor: Colors.white,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
              border:
                  OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: foodflow.line),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SegmentedButtons extends StatelessWidget {
  const _SegmentedButtons({
    required this.label,
    required this.value,
    required this.values,
    required this.onChanged,
  });

  final String label;
  final String value;
  final Map<String, String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _FieldLabel(label),
          Row(
            children: values.entries.map((entry) {
              final active = entry.key == value;
              return Expanded(
                child: Padding(
                  padding: EdgeInsets.only(
                    right: entry.key == values.keys.last ? 0 : 8,
                  ),
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      backgroundColor: active ? foodflow.orange : Colors.white,
                      foregroundColor: active ? Colors.white : foodflow.ink,
                      side: BorderSide(
                        color: active ? foodflow.orange : foodflow.line,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    onPressed: () => onChanged(entry.key),
                    child: Text(
                      entry.value,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          fontSize: 12, fontWeight: FontWeight.w900),
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.label, {this.required = false});

  final String label;
  final bool required;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: Text.rich(
        TextSpan(
          text: label,
          children: [
            if (required)
              TextSpan(
                text: ' *',
                style: TextStyle(color: foodflow.orange),
              ),
          ],
        ),
        style: const TextStyle(
          color: foodflow.ink,
          fontSize: 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _RewardTypeCard extends StatelessWidget {
  const _RewardTypeCard({
    required this.label,
    required this.icon,
    required this.active,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        height: 70,
        decoration: BoxDecoration(
          color: active ? foodflow.orange.withOpacity(0.08) : Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: active ? foodflow.orange : foodflow.line),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: active ? foodflow.orange : foodflow.ink),
            const SizedBox(height: 6),
            Text(
              label,
              textAlign: TextAlign.center,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: active ? foodflow.orange : foodflow.ink,
                fontSize: 11,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoBox extends StatelessWidget {
  const _InfoBox({required this.text, this.tone = _InfoTone.info});

  final String text;
  final _InfoTone tone;

  @override
  Widget build(BuildContext context) {
    final color =
        tone == _InfoTone.warning ? foodflow.orange : const Color(0xFF2563EB);
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 4, bottom: 14),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.09),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Icon(Icons.info_outline, color: color, size: 18),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(
                color: foodflow.ink,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

enum _InfoTone { info, warning }

class _SettingSwitch extends StatelessWidget {
  const _SettingSwitch({
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: const TextStyle(
                color: foodflow.ink,
                fontSize: 13,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Switch(
            value: value,
            onChanged: onChanged,
            activeColor: foodflow.orange,
          ),
        ],
      ),
    );
  }
}

class _DateTimeRow extends StatelessWidget {
  const _DateTimeRow({
    required this.label,
    required this.date,
    required this.time,
    required this.showTime,
    required this.onPickDate,
    required this.onPickTime,
  });

  final String label;
  final DateTime date;
  final TimeOfDay time;
  final bool showTime;
  final VoidCallback onPickDate;
  final VoidCallback onPickTime;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _FieldLabel(label, required: true),
          Row(
            children: [
              Expanded(
                flex: showTime ? 1 : 2,
                child: _PickerBox(
                  icon: Icons.calendar_today_outlined,
                  text: DateFormat('dd MMM yyyy').format(date),
                  onTap: onPickDate,
                ),
              ),
              if (showTime) ...[
                const SizedBox(width: 8),
                Expanded(
                  child: _PickerBox(
                    icon: Icons.access_time,
                    text: time.format(context),
                    onTap: onPickTime,
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _PickerBox extends StatelessWidget {
  const _PickerBox(
      {required this.icon, required this.text, required this.onTap});

  final IconData icon;
  final String text;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: foodflow.line),
        ),
        child: Row(
          children: [
            Icon(icon, color: foodflow.muted, size: 16),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                text,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: foodflow.ink,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RepeatDaysPicker extends StatefulWidget {
  const _RepeatDaysPicker({required this.days});

  final Set<int> days;

  @override
  State<_RepeatDaysPicker> createState() => _RepeatDaysPickerState();
}

class _RepeatDaysPickerState extends State<_RepeatDaysPicker> {
  static const labels = {
    1: 'Mon',
    2: 'Tue',
    3: 'Wed',
    4: 'Thu',
    5: 'Fri',
    6: 'Sat',
    7: 'Sun'
  };

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _FieldLabel('Repeat On'),
          Wrap(
            spacing: 8,
            children: labels.entries.map((entry) {
              final active = widget.days.contains(entry.key);
              return ChoiceChip(
                avatar: active
                    ? const Icon(
                        Icons.check_circle,
                        color: Color(0xFF16A34A),
                        size: 17,
                      )
                    : null,
                label: Text(entry.value),
                selected: active,
                selectedColor: const Color(0xFFE8F8EF),
                backgroundColor: Colors.white,
                side: BorderSide(
                  color: active ? const Color(0xFF16A34A) : foodflow.line,
                ),
                labelStyle: TextStyle(
                  color: active ? const Color(0xFF166534) : foodflow.ink,
                  fontWeight: FontWeight.w900,
                ),
                onSelected: (_) {
                  setState(() {
                    if (active) {
                      widget.days.remove(entry.key);
                    } else {
                      widget.days.add(entry.key);
                    }
                  });
                },
              );
            }).toList(),
          ),
        ],
      ),
    );
  }
}

class _EditorFooter extends StatelessWidget {
  const _EditorFooter({
    required this.isLast,
    required this.isSaving,
    required this.onBack,
    required this.onNext,
    required this.nextLabel,
  });

  final bool isLast;
  final bool isSaving;
  final VoidCallback onBack;
  final VoidCallback onNext;
  final String nextLabel;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: foodflow.line)),
      ),
      child: Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: isSaving ? null : onBack,
              child: const Text('Back'),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: ElevatedButton(
              onPressed: isSaving ? null : onNext,
              child: isSaving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(nextLabel),
            ),
          ),
        ],
      ),
    );
  }
}

class _PreviewBanner extends StatelessWidget {
  const _PreviewBanner({
    required this.title,
    required this.reward,
    this.imagePath,
    this.imageUrl,
  });

  final String title;
  final String reward;
  final String? imagePath;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 116,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        gradient: LinearGradient(
          colors: [Color(0xFFB91C1C), foodflow.orange],
        ),
      ),
      child: Row(
        children: [
          _PromotionThumb(size: 86, imagePath: imagePath, imageUrl: imageUrl),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  reward.toUpperCase(),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w800),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DetailsBanner extends StatelessWidget {
  const _DetailsBanner({
    required this.title,
    required this.reward,
    required this.shape,
    required this.imageUrl,
    required this.isActive,
  });

  final String title;
  final String reward;
  final String shape;
  final String imageUrl;
  final bool isActive;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 18),
      padding: const EdgeInsets.all(16),
      decoration: _partnerPanelDecoration(radius: 24),
      child: Row(
        children: [
          _PromotionThumb(size: 92, imageUrl: imageUrl),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  reward.toUpperCase(),
                  style: const TextStyle(
                    color: foodflow.ink,
                    fontSize: 27,
                    fontWeight: FontWeight.w900,
                    height: 1,
                  ),
                ),
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: foodflow.ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  shape,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: foodflow.muted, fontSize: 11),
                ),
              ],
            ),
          ),
          _StatusPill(
            label: isActive ? 'Active' : 'Draft',
            color: isActive ? const Color(0xFF16A34A) : foodflow.muted,
          ),
        ],
      ),
    );
  }
}

class _PreviewRow extends StatelessWidget {
  const _PreviewRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                color: foodflow.muted,
                fontSize: 13,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(
                color: foodflow.ink,
                fontSize: 13,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ImagePlaceholder extends StatelessWidget {
  const _ImagePlaceholder({
    required this.onTap,
    this.imagePath,
    this.imageUrl,
  });

  final String? imagePath;
  final String? imageUrl;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final hasLocalImage = imagePath != null && imagePath!.trim().isNotEmpty;
    final hasRemoteImage = imageUrl != null && imageUrl!.trim().isNotEmpty;
    final hasImage = hasLocalImage || hasRemoteImage;

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _FieldLabel('Promotion Banner'),
          Row(
            children: [
              _PromotionThumb(
                size: 88,
                imagePath: imagePath,
                imageUrl: imageUrl,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: InkWell(
                  onTap: onTap,
                  borderRadius: BorderRadius.circular(10),
                  child: Container(
                    height: 88,
                    clipBehavior: Clip.antiAlias,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: foodflow.line),
                    ),
                    child: hasImage
                        ? Stack(
                            fit: StackFit.expand,
                            children: [
                              if (hasLocalImage)
                                Image.file(File(imagePath!), fit: BoxFit.cover)
                              else
                                NetworkImageLoader(
                                  imageUrl: imageUrl!,
                                  fit: BoxFit.cover,
                                  errorWidget: const _UploadPrompt(),
                                ),
                              DecoratedBox(
                                decoration: BoxDecoration(
                                  gradient: LinearGradient(
                                    begin: Alignment.topCenter,
                                    end: Alignment.bottomCenter,
                                    colors: [
                                      Colors.transparent,
                                      Colors.black.withOpacity(0.45),
                                    ],
                                  ),
                                ),
                              ),
                              const Align(
                                alignment: Alignment.bottomCenter,
                                child: Padding(
                                  padding: EdgeInsets.only(bottom: 8),
                                  child: Text(
                                    'Change Image',
                                    style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 11,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          )
                        : const _UploadPrompt(),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _UploadPrompt extends StatelessWidget {
  const _UploadPrompt();

  @override
  Widget build(BuildContext context) {
    return const Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.image_outlined, color: foodflow.muted),
        SizedBox(height: 4),
        Text(
          'Upload Image',
          style: TextStyle(
            color: foodflow.ink,
            fontSize: 11,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          'Recommended 1200x600px',
          style: TextStyle(color: foodflow.muted, fontSize: 9),
        ),
      ],
    );
  }
}

class _PromotionThumb extends StatelessWidget {
  const _PromotionThumb({
    required this.size,
    this.imagePath,
    this.imageUrl,
  });

  final double size;
  final String? imagePath;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final localPath = imagePath?.trim() ?? '';
    final remoteUrl = imageUrl?.trim() ?? '';
    final hasLocalImage = localPath.isNotEmpty;
    final hasRemoteImage = remoteUrl.isNotEmpty && remoteUrl != 'null';

    return Container(
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFFFEDD5), Color(0xFFFED7AA)],
        ),
      ),
      child: hasLocalImage
          ? Image.file(
              File(localPath),
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => _promotionThumbFallback(),
            )
          : hasRemoteImage
              ? NetworkImageLoader(
                  imageUrl: remoteUrl,
                  fit: BoxFit.cover,
                  errorWidget: _promotionThumbFallback(),
                )
              : _promotionThumbFallback(),
    );
  }

  Widget _promotionThumbFallback() {
    return Icon(
      Icons.local_offer_outlined,
      color: foodflow.orange,
      size: size * 0.48,
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _HeroMetric extends StatelessWidget {
  const _HeroMetric({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          label,
          style: const TextStyle(
            color: foodflow.muted,
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          '$value',
          style: TextStyle(
            color: color,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}

class _EmptyPromotionState extends StatelessWidget {
  const _EmptyPromotionState({required this.onCreate});

  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: _partnerPanelDecoration(radius: 24),
      child: Column(
        children: [
          Icon(Icons.local_offer_outlined, color: foodflow.orange, size: 46),
          const SizedBox(height: 12),
          const Text(
            'No promotions yet',
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Create offers to bring customers back to your restaurant.',
            textAlign: TextAlign.center,
            style:
                TextStyle(color: foodflow.muted, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: _BrandActionButton(
              onPressed: onCreate,
              icon: Icons.add,
              label: 'Create Promotion',
            ),
          ),
        ],
      ),
    );
  }
}

class _SuccessPromoCard extends StatelessWidget {
  const _SuccessPromoCard({required this.promo});

  final Map<String, dynamic> promo;

  @override
  Widget build(BuildContext context) {
    final endDate = _parseDate(promo['end_date'] ?? promo['valid_to']);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: _partnerPanelDecoration(radius: 18),
      child: Row(
        children: [
          _PromotionThumb(size: 54, imageUrl: _promoImage(promo)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _promoTitle(promo),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: foodflow.ink,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  endDate == null
                      ? 'No validity date'
                      : 'Valid till ${DateFormat('dd MMM yyyy, hh:mm a').format(endDate)}',
                  style: const TextStyle(color: foodflow.muted, fontSize: 11),
                ),
              ],
            ),
          ),
          const _StatusPill(label: 'Active', color: Color(0xFF16A34A)),
        ],
      ),
    );
  }
}

class _UsageBox extends StatelessWidget {
  const _UsageBox({
    required this.usage,
    required this.limit,
    this.totalDiscount,
  });

  final int usage;
  final int limit;
  final double? totalDiscount;

  @override
  Widget build(BuildContext context) {
    final progress = limit <= 0 ? 0.0 : (usage / limit).clamp(0.0, 1.0);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: _partnerPanelDecoration(radius: 18),
      child: Column(
        children: [
          _PreviewRow(
            label: 'Total Usage',
            value: limit > 0 ? '$usage / $limit' : '$usage',
          ),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 8,
              color: const Color(0xFF16A34A),
              backgroundColor: const Color(0xFFE5E7EB),
            ),
          ),
          if (totalDiscount != null) ...[
            const SizedBox(height: 12),
            _PreviewRow(
              label: 'Total Discount Given',
              value: formatCurrencyValue(context, totalDiscount!),
            ),
          ],
        ],
      ),
    );
  }
}

String _legacyDiscountType(String promotionType) {
  final shape = _restaurantPromoShape(promotionType);
  if ({
    'flat',
    'fixed_price',
    'combo_deal',
    'meal_deal',
    'free_item',
  }.contains(shape.rewardType)) {
    return 'fixed';
  }
  return 'percentage';
}

String _promotionTypeFromLegacy(Map<String, dynamic> promo) {
  final discountType = promo['discount_type']?.toString();
  return discountType == 'fixed' ? 'flat_discount' : 'percentage_discount';
}

String _promoRewardLabel(
  BuildContext context,
  _PromoShape shape,
  double value,
  double max,
  Map<String, dynamic> config,
) {
  if (shape.needsBuyFree) {
    return 'Buy ${config['buy_quantity'] ?? shape.defaultBuy} Get ${config['free_quantity'] ?? shape.defaultFree}';
  }
  if (shape.noValueRequired) return shape.label;
  if (shape.rewardType == 'reward_points') {
    return '${value.toStringAsFixed(0)} Reward Points';
  }
  if (_legacyDiscountTypeForShape(shape) == 'fixed') {
    return '${formatCurrencyValue(context, value)} ${shape.label}';
  }
  final cap = max > 0 ? ' upto ${formatCurrencyValue(context, max)}' : '';
  return '${value.toStringAsFixed(0)}% ${shape.label}$cap';
}

String _legacyDiscountTypeForShape(_PromoShape shape) {
  return _legacyDiscountType(
    _promoShapes.entries
        .firstWhere(
          (entry) => identical(entry.value, shape),
          orElse: () => const MapEntry(
              'percentage_discount',
              _PromoShape(
                label: 'Percentage Discount',
                rewardType: 'percentage',
                icon: Icons.percent,
                hint: '',
              )),
        )
        .key,
  );
}

int _rowId(Map<String, dynamic> row) {
  final value = row['id'];
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

String _rowTitle(Map<String, dynamic> row) {
  return (row['name'] ??
          row['title'] ??
          row['category_name'] ??
          row['code'] ??
          'Untitled')
      .toString();
}

String _rowSubtitle(Map<String, dynamic> row, String type) {
  if (type == 'categories') {
    final count = row['item_count'] ?? row['items_count'] ?? row['menu_count'];
    return count == null ? 'Category' : '$count items';
  }

  final category = row['category_name'] ?? row['category']?['name'];
  final price = _toDouble(row['price']);
  final parts = [
    if (category != null && category.toString().trim().isNotEmpty)
      category.toString(),
    if (price > 0) 'Price $price',
  ];
  return parts.join(' · ');
}

String _rowImage(Map<String, dynamic> row) {
  final direct = row['image_url'] ??
      row['image'] ??
      row['thumbnail_url'] ??
      row['thumbnail'] ??
      row['photo_url'] ??
      row['photo'] ??
      row['logo_url'] ??
      row['logo_image_url'] ??
      row['logo_image'] ??
      row['logo'] ??
      row['promo_image_url'] ??
      row['promo_image'];
  final value = direct?.toString().trim() ?? '';
  if (value.isNotEmpty && value != 'null') return _absoluteImageUrl(value);

  final images = row['images'];
  if (images is List && images.isNotEmpty) {
    final first = images.first;
    if (first is Map) {
      final mapped = first['url'] ?? first['image_url'] ?? first['path'];
      final mappedValue = mapped?.toString().trim() ?? '';
      if (mappedValue.isNotEmpty && mappedValue != 'null') {
        return _absoluteImageUrl(mappedValue);
      }
    }

    final raw = first?.toString().trim() ?? '';
    if (raw.isNotEmpty && raw != 'null') return _absoluteImageUrl(raw);
  }

  return '';
}

String _absoluteImageUrl(String value) {
  if (value.startsWith('http://') || value.startsWith('https://')) {
    return value;
  }

  final apiUri = Uri.parse(AppConfig.apiBaseUrl);
  final origin = '${apiUri.scheme}://${apiUri.host}';
  final normalized = value.startsWith('/') ? value.substring(1) : value;

  if (normalized.startsWith('storage/')) {
    return '$origin/$normalized';
  }

  return '$origin/storage/$normalized';
}

String _promoImage(Map<String, dynamic> promo) {
  for (final key in const [
    'promo_image_url',
    'promo_image',
    'image_url',
    'image',
    'banner_image',
  ]) {
    final value = promo[key]?.toString().trim() ?? '';
    if (value.isNotEmpty && value != 'null') return _absoluteImageUrl(value);
  }

  return '';
}

String _promoTargetLabel(Map<String, dynamic> promo) {
  final targetType =
      (promo['target_type'] ?? promo['promotion_for'] ?? 'restaurant')
          .toString();
  final ids = promo['target_ids'] ?? promo['targets'];
  final count = ids is List ? ids.length : 0;

  if (targetType == 'categories') {
    return count > 0 ? '$count categories' : 'Selected Categories';
  }
  if (targetType == 'items') {
    return count > 0 ? '$count items' : 'Selected Items';
  }

  return 'All Items';
}

String _promoApplicationLabel(Map<String, dynamic> promo) {
  final mode = promo['application_mode']?.toString();
  if (mode == 'automatic') return 'Automatic';

  final code = (promo['code'] ?? promo['coupon_code'] ?? '').toString().trim();
  return code.isEmpty ? 'Automatic' : 'Coupon';
}

double? _promoTotalDiscount(Map<String, dynamic> promo) {
  for (final key in const [
    'total_discount_given',
    'discount_given',
    'total_discount',
    'promotion_discount',
    'discount_amount',
  ]) {
    if (!promo.containsKey(key)) continue;
    final value = _toDouble(promo[key]);
    if (value > 0) return value;
  }

  return null;
}

String _promoTitle(Map<String, dynamic> promo) {
  final description = promo['description']?.toString();
  if (description != null && description.trim().isNotEmpty) {
    return description.trim();
  }
  return promo['title']?.toString() ?? promo['code']?.toString() ?? 'Promotion';
}

double _toDouble(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '') ?? 0;
}

String _numText(dynamic value) {
  if (value == null) return '';
  if (value is num) {
    return value % 1 == 0 ? value.toInt().toString() : value.toString();
  }
  return value.toString();
}

DateTime? _parseDate(dynamic value) {
  if (value is DateTime) return value;
  if (value is String && value.trim().isNotEmpty) {
    return DateTime.tryParse(value.trim());
  }
  return null;
}

int _promoActionId(Map<String, dynamic> promo) {
  final value = promo['legacy_id'] ?? promo['migrated_from_id'] ?? promo['id'];
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? 0;
}

String _generateCode(String title) {
  final source = title.trim().isEmpty ? 'PROMO' : title.trim();
  final words = source
      .replaceAll(RegExp(r'[^A-Za-z0-9 ]'), ' ')
      .split(RegExp(r'\s+'))
      .where((word) => word.isNotEmpty)
      .take(3)
      .map((word) => word.substring(0, word.length < 3 ? word.length : 3))
      .join();
  final suffix = DateTime.now().millisecondsSinceEpoch.toString().substring(8);
  return '${words.isEmpty ? 'PROMO' : words.toUpperCase()}$suffix';
}
