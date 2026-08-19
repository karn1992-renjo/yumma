// lib/screens/restaurant/restaurant_menu_screen.dart
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../models/menu_item.dart';
import '../../providers/auth_provider.dart';
import '../../providers/restaurant_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_image_loader.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';

const String _menuMetadataSeparator = ' • ';

InputDecoration _menuInputDecoration(
  BuildContext context,
  String hintText, {
  String? prefixText,
  Widget? suffixIcon,
}) {
  return InputDecoration(
    hintText: hintText,
    prefixText: prefixText,
    suffixIcon: suffixIcon,
    hintStyle: const TextStyle(
      color: FoodFlowTheme.faint,
      fontSize: 14,
      fontWeight: FontWeight.w700,
    ),
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
    filled: true,
    fillColor: Colors.white,
    border: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: FoodFlowTheme.line),
    ),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: FoodFlowTheme.line),
    ),
  );
}

class RestaurantMenuScreen extends StatefulWidget {
  const RestaurantMenuScreen({Key? key}) : super(key: key);

  @override
  State<RestaurantMenuScreen> createState() => _RestaurantMenuScreenState();
}

class _RestaurantMenuScreenState extends State<RestaurantMenuScreen>
    with TickerProviderStateMixin {
  TabController? _tabController;
  final ApiService _api = ApiService();
  final TextEditingController _searchController = TextEditingController();

  List<dynamic> _categories = [];
  List<dynamic> _cuisines = [];
  List<dynamic> _globalMenuItems = [];
  List<dynamic> _globalCategories = [];
  List<MenuItem> _menuItems = [];
  final Set<int> _selectedItemIds = {};
  bool _isLoading = true;
  bool _selectionMode = false;
  bool _showOutletPicker = true;
  bool _showMenuModePicker = false;
  bool _showGlobalCatalog = false;
  int _menuTabIndex = 0;
  final Set<int> _expandedCategoryIds = <int>{};
  String _searchQuery = '';
  String _availabilityFilter = 'all';
  String _sortMode = 'name';
  int _selectedCategoryId = 0;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  @override
  void dispose() {
    _tabController?.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Map<String, dynamic> _selectedRestaurantQueryParams() {
    final selectedId = Provider.of<RestaurantProvider>(context, listen: false)
        .selectedRestaurantId;
    return {
      if (selectedId != null) 'restaurant_id': selectedId.toString(),
    };
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);

    try {
      final restaurantProvider = Provider.of<RestaurantProvider>(
        context,
        listen: false,
      );
      await restaurantProvider.loadRestaurants();
      if (restaurantProvider.selectedRestaurantId == null) {
        if (mounted) setState(() => _isLoading = false);
        return;
      }

      final restaurantParams = _selectedRestaurantQueryParams();
      final categoriesResponse = await _api.get(
        ApiConstants.restaurantCategories,
        queryParams: restaurantParams,
      );
      final cuisinesResponse = await _api.get(ApiConstants.popularCuisines);
      final menuResponse = await _api.get(
        ApiConstants.restaurantMenuItems,
        queryParams: restaurantParams,
      );
      final globalMenuResponse =
          await _api.get(ApiConstants.restaurantGlobalMenu);
      final globalCategoriesResponse =
          await _api.get(ApiConstants.restaurantGlobalCategories);

      if (categoriesResponse['success'] == true) {
        setState(() {
          _categories = categoriesResponse['data'] ?? [];
        });

        _tabController?.dispose();
        _tabController = TabController(
          length: _categories.length + 1,
          vsync: this,
        );
        _tabController!.addListener(() {
          if (_tabController!.indexIsChanging) {
            setState(() {
              _selectedCategoryId = _tabController!.index > 0
                  ? _categories[_tabController!.index - 1]['id']
                  : 0;
            });
          }
        });
      }

      if (cuisinesResponse['success'] == true) {
        setState(() {
          _cuisines = cuisinesResponse['data'] ?? [];
        });
      }

      if (menuResponse['success'] == true) {
        final List<dynamic> data = menuResponse['data'] ?? [];
        setState(() {
          _menuItems = data.map((json) => MenuItem.fromJson(json)).toList();
        });
      }

      if (globalMenuResponse['success'] == true) {
        setState(() {
          _globalMenuItems = globalMenuResponse['data'] ?? [];
        });
      }

      if (globalCategoriesResponse['success'] == true) {
        setState(() {
          _globalCategories = globalCategoriesResponse['data'] ?? [];
        });
      }
    } catch (e) {
      debugPrint('Load menu error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to load menu: $e')),
        );
      }
    }

    setState(() => _isLoading = false);
  }

  Future<void> _deleteMenuItem(int itemId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Item'),
        content: const Text('Are you sure you want to delete this item?'),
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
      final response = await _api.post(
        '${ApiConstants.restaurantMenuItems}/$itemId/delete',
        queryParams: _selectedRestaurantQueryParams(),
      );
      if (response['success'] == true) {
        await _loadData();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Item deleted successfully')),
          );
        }
      }
    } catch (e) {
      debugPrint('Delete item error: $e');
    }
  }

  Future<void> _toggleAvailability(int itemId, bool currentStatus) async {
    try {
      final response = await _api.post(
        '${ApiConstants.restaurantMenuItems}/$itemId/toggle',
        queryParams: _selectedRestaurantQueryParams(),
      );
      if (response['success'] == true) {
        setState(() {
          final index = _menuItems.indexWhere((item) => item.id == itemId);
          if (index != -1) {
            _menuItems[index] = MenuItem.fromJson({
              ..._menuItems[index].toJson(),
              'is_available': !currentStatus,
            });
          }
        });
      }
    } catch (e) {
      debugPrint('Toggle availability error: $e');
    }
  }

  Future<void> _showAdjustPricesSheet() async {
    final rootContext = context;
    var direction = 'increase';
    var type = 'percentage';
    final valueController = TextEditingController();
    final applied = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(
              20, 8, 20, MediaQuery.viewInsetsOf(context).bottom + 24),
          child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Adjust all menu prices',
                    style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 6),
                Text('Base and discounted prices will be updated together.',
                    style: Theme.of(context).textTheme.bodyMedium),
                const SizedBox(height: 20),
                SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(value: 'increase', label: Text('Increase')),
                      ButtonSegment(value: 'decrease', label: Text('Decrease'))
                    ],
                    selected: {
                      direction
                    },
                    onSelectionChanged: (value) =>
                        setSheetState(() => direction = value.first)),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                    value: type,
                    decoration:
                        const InputDecoration(labelText: 'Adjustment method'),
                    items: const [
                      DropdownMenuItem(
                          value: 'percentage', child: Text('Percentage')),
                      DropdownMenuItem(
                          value: 'fixed', child: Text('Fixed amount'))
                    ],
                    onChanged: (value) => setSheetState(() => type = value!)),
                const SizedBox(height: 14),
                TextField(
                    controller: valueController,
                    keyboardType:
                        const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(
                        labelText:
                            type == 'percentage' ? 'Percentage' : 'Amount',
                        suffixText: type == 'percentage' ? '%' : null,
                        prefixText: type == 'fixed'
                            ? currencyInputPrefix(context)
                            : null)),
                const SizedBox(height: 20),
                SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                        onPressed: () async {
                          final value = double.tryParse(valueController.text);
                          if (value == null || value <= 0) return;
                          try {
                            await _api.post(
                                '${ApiConstants.restaurantMenuItems}/adjust-prices',
                                queryParams: _selectedRestaurantQueryParams(),
                                data: {
                                  'direction': direction,
                                  'adjustment_type': type,
                                  'value': value
                                });
                            if (sheetContext.mounted)
                              Navigator.pop(sheetContext, true);
                          } catch (error) {
                            if (mounted) {
                              ScaffoldMessenger.of(rootContext).showSnackBar(
                                SnackBar(content: Text('$error')),
                              );
                            }
                          }
                        },
                        child: const Text('Apply to all items'))),
              ]),
        ),
      ),
    );
    valueController.dispose();
    if (applied == true) {
      await _loadData();
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Menu prices updated')));
    }
  }

  List<MenuItem> _visibleMenuItems() {
    var items = _selectedCategoryId == 0
        ? List<MenuItem>.from(_menuItems)
        : _menuItems
            .where((item) => item.categoryId == _selectedCategoryId)
            .toList();

    final query = _searchQuery.trim().toLowerCase();
    if (query.isNotEmpty) {
      items = items.where((item) {
        final haystack = [
          item.name,
          item.description ?? '',
          item.categoryName ?? '',
          item.cuisineName ?? '',
          item.dietLabel,
        ].join(' ').toLowerCase();
        return haystack.contains(query);
      }).toList();
    }

    if (_availabilityFilter == 'live') {
      items = items.where((item) => item.isAvailable).toList();
    } else if (_availabilityFilter == 'hidden') {
      items = items.where((item) => !item.isAvailable).toList();
    }

    items.sort((a, b) {
      switch (_sortMode) {
        case 'price_high':
          return b.finalPrice.compareTo(a.finalPrice);
        case 'price_low':
          return a.finalPrice.compareTo(b.finalPrice);
        case 'orders':
          return b.totalOrders.compareTo(a.totalOrders);
        case 'newest':
          return b.createdAt.compareTo(a.createdAt);
        default:
          return a.name.toLowerCase().compareTo(b.name.toLowerCase());
      }
    });

    return items;
  }

  void _setSelectionMode(bool enabled) {
    setState(() {
      _selectionMode = enabled;
      if (!enabled) _selectedItemIds.clear();
    });
  }

  void _toggleItemSelection(int itemId, bool selected) {
    setState(() {
      if (selected) {
        _selectedItemIds.add(itemId);
        _selectionMode = true;
      } else {
        _selectedItemIds.remove(itemId);
        if (_selectedItemIds.isEmpty) _selectionMode = false;
      }
    });
  }

  Future<void> _duplicateMenuItem(MenuItem item) async {
    await _createMenuItem(
      name: '${item.name} Copy',
      description: item.description,
      price: item.price,
      discountedPrice: item.discountedPrice,
      categoryId: item.categoryId,
      cuisineId: item.cuisineId,
      foodType: item.foodType,
      isPriceInclusiveGst: item.isPriceInclusiveGst,
      variants: item.variants.map((option) => option.toJson()).toList(),
      addOns: item.addOns.map((option) => option.toJson()).toList(),
    );
  }

  Future<void> _bulkSetAvailability(bool available) async {
    final ids = _selectedItemIds.toList();
    for (final id in ids) {
      final item = _menuItems.where((menuItem) => menuItem.id == id);
      if (item.isNotEmpty && item.first.isAvailable != available) {
        await _api.post('${ApiConstants.restaurantMenuItems}/$id/toggle');
      }
    }
    _setSelectionMode(false);
    await _loadData();
  }

  Future<void> _bulkDeleteSelected() async {
    final count = _selectedItemIds.length;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete selected items?'),
        content: Text('This will permanently delete $count menu item(s).'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    final ids = _selectedItemIds.toList();
    for (final id in ids) {
      await _api.post('${ApiConstants.restaurantMenuItems}/$id/delete');
    }
    _setSelectionMode(false);
    await _loadData();
  }

  int? _asInt(dynamic value) {
    if (value is int) return value;
    return int.tryParse(value?.toString() ?? '');
  }

  List<dynamic> _globalSubcategories(int? categoryId) {
    if (categoryId == null) return [];
    final category = _globalCategories.firstWhere(
      (item) => _asInt(item['id']) == categoryId,
      orElse: () => <String, dynamic>{},
    );
    final subcategories = category['subcategories'];
    return subcategories is List ? subcategories : [];
  }

  String _globalCategoryName(int? categoryId) {
    if (categoryId == null) return '';
    final category = _globalCategories.firstWhere(
      (item) => _asInt(item['id']) == categoryId,
      orElse: () => <String, dynamic>{},
    );
    return category['name']?.toString() ?? '';
  }

  String _globalSubcategoryName(int? categoryId, int? subcategoryId) {
    if (subcategoryId == null) return '';
    final subcategory = _globalSubcategories(categoryId).firstWhere(
      (item) => _asInt(item['id']) == subcategoryId,
      orElse: () => <String, dynamic>{},
    );
    return subcategory['name']?.toString() ?? '';
  }

  List<dynamic> _filteredGlobalMenuItems(
    int? categoryId,
    int? subcategoryId,
  ) {
    final categoryName = _globalCategoryName(categoryId);
    final subcategoryName = _globalSubcategoryName(categoryId, subcategoryId);

    return _globalMenuItems.where((item) {
      final itemCategory = item['category_name']?.toString() ?? '';
      final itemSubcategory = item['subcategory_name']?.toString() ?? '';
      return (categoryName.isEmpty || itemCategory == categoryName) &&
          (subcategoryName.isEmpty || itemSubcategory == subcategoryName);
    }).toList();
  }

  void _showAddMethodSheet() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Select Menu Creation Method',
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 14),
              ListTile(
                leading: const Icon(Icons.library_books_outlined),
                title: const Text('Select From Global Menu'),
                subtitle: const Text('Use an admin-created catalog item.'),
                onTap: () {
                  Navigator.pop(context);
                  _showGlobalMenuDialog();
                },
              ),
              ListTile(
                leading: const Icon(Icons.add_circle_outline),
                title: const Text('Create Own Menu Item'),
                subtitle: const Text('Create a custom product.'),
                onTap: () {
                  Navigator.pop(context);
                  _showAddItemDialog();
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showGlobalMenuDialog() {
    if (_globalMenuItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No global menu items available')),
      );
      return;
    }

    // ignore: dead_code
    // TODO: Remove this superseded bottom-sheet editor after migration settles.
    // ignore: dead_code
    final formKey = GlobalKey<FormState>();
    final priceController = TextEditingController();
    final discountedPriceController = TextEditingController();
    final prepController = TextEditingController(text: '20');
    final variantsController = TextEditingController();
    final addOnsController = TextEditingController();
    int? selectedGlobalCategoryId;
    int? selectedGlobalSubcategoryId;
    int? selectedMasterId;
    int optionEditorRevision = 0;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding:
              EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Form(
              key: formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Add From Global Menu',
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<int>(
                    decoration: const InputDecoration(
                      labelText: 'Global Category',
                      border: OutlineInputBorder(),
                    ),
                    items: _globalCategories.map((category) {
                      return DropdownMenuItem<int>(
                        value: _asInt(category['id']),
                        child: Text(category['name']?.toString() ?? ''),
                      );
                    }).toList(),
                    onChanged: (value) {
                      selectedGlobalCategoryId = value;
                      selectedGlobalSubcategoryId = null;
                      selectedMasterId = null;
                      variantsController.clear();
                      addOnsController.clear();
                      setSheetState(() => optionEditorRevision += 1);
                    },
                    validator: (value) => value == null ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    value: selectedGlobalSubcategoryId,
                    decoration: const InputDecoration(
                      labelText: 'Global Sub Category',
                      border: OutlineInputBorder(),
                    ),
                    items: [
                      const DropdownMenuItem<int>(
                        value: null,
                        child: Text('All sub categories'),
                      ),
                      ..._globalSubcategories(selectedGlobalCategoryId).map(
                        (subcategory) => DropdownMenuItem<int>(
                          value: _asInt(subcategory['id']),
                          child: Text(subcategory['name']?.toString() ?? ''),
                        ),
                      ),
                    ],
                    onChanged: (value) {
                      selectedGlobalSubcategoryId = value;
                      selectedMasterId = null;
                      variantsController.clear();
                      addOnsController.clear();
                      setSheetState(() => optionEditorRevision += 1);
                    },
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    value: selectedMasterId,
                    decoration: const InputDecoration(
                      labelText: 'Global Menu Item',
                      border: OutlineInputBorder(),
                    ),
                    items: _filteredGlobalMenuItems(
                      selectedGlobalCategoryId,
                      selectedGlobalSubcategoryId,
                    ).map((item) {
                      final name = item['name']?.toString() ?? '';
                      final category = item['category_name']?.toString() ?? '';
                      final subcategory =
                          item['subcategory_name']?.toString() ?? '';
                      return DropdownMenuItem<int>(
                        value: _asInt(item['id']),
                        child: Text(
                          subcategory.isNotEmpty
                              ? '$name - $subcategory'
                              : (category.isEmpty ? name : '$name - $category'),
                        ),
                      );
                    }).toList(),
                    onChanged: (value) {
                      selectedMasterId = value;
                      final selected = _globalMenuItems.firstWhere(
                        (item) => item['id'].toString() == value.toString(),
                        orElse: () => <String, dynamic>{},
                      );
                      prepController.text =
                          (selected?['preparation_time'] ?? 20).toString();
                      variantsController.text =
                          _formatRawMenuOptions(selected?['variants']);
                      addOnsController.text = _formatRawMenuOptions(
                          selected?['add_ons'] ?? selected?['addons']);
                      setSheetState(() => optionEditorRevision += 1);
                    },
                    validator: (value) => value == null ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: priceController,
                          decoration: InputDecoration(
                            labelText: 'Selling Price',
                            prefixText: currencyInputPrefix(context),
                            border: const OutlineInputBorder(),
                          ),
                          keyboardType: const TextInputType.numberWithOptions(
                              decimal: true),
                          validator: (value) =>
                              value?.trim().isEmpty == true ? 'Required' : null,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: discountedPriceController,
                          decoration: InputDecoration(
                            labelText: 'Offer Price',
                            prefixText: currencyInputPrefix(context),
                            border: const OutlineInputBorder(),
                          ),
                          keyboardType: const TextInputType.numberWithOptions(
                              decimal: true),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: prepController,
                    decoration: const InputDecoration(
                      labelText: 'Preparation Time',
                      border: OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 12),
                  _MenuOptionEditor(
                    key: ValueKey('global_variants_$optionEditorRevision'),
                    controller: variantsController,
                    label: 'Variants (Size / Quantity)',
                    addButtonLabel: 'Add Variant',
                    placeholder: 'Medium / 500g',
                    helpText:
                        'Customers must choose one available variant when variants are configured.',
                    emptyText:
                        'No variants added. Add sizes, weights, portions, or quantity choices.',
                  ),
                  const SizedBox(height: 12),
                  _MenuOptionEditor(
                    key: ValueKey('global_add_ons_$optionEditorRevision'),
                    controller: addOnsController,
                    label: 'Add-ons / Extras',
                    addButtonLabel: 'Add Extra',
                    placeholder: 'Extra cheese',
                    helpText:
                        'Customers can select multiple available extras during add-to-cart.',
                    emptyText:
                        'No extras added. Add toppings, sides, sauces, or paid extras.',
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: () async {
                        if (!formKey.currentState!.validate() ||
                            selectedMasterId == null) {
                          return;
                        }
                        Navigator.pop(context);
                        await _importGlobalMenuItem(
                          masterMenuItemId: selectedMasterId!,
                          price: double.parse(priceController.text),
                          discountedPrice:
                              discountedPriceController.text.isNotEmpty
                                  ? double.parse(discountedPriceController.text)
                                  : null,
                          preparationTime: int.tryParse(prepController.text),
                          globalCategoryId: selectedGlobalCategoryId,
                          globalSubcategoryId: selectedGlobalSubcategoryId,
                          variants: _parseMenuOptions(variantsController.text),
                          addOns: _parseMenuOptions(addOnsController.text),
                        );
                      },
                      child: const Text('Add To My Menu'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _showAddItemDialog() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _MenuItemFormScreen(
          categories: _categories,
          globalCategories: _globalCategories,
          globalMenuItems: _globalMenuItems,
          cuisines: _cuisines,
          availableAddOns: _realAddOns(),
          onSubmit: ({
            required String name,
            description,
            required double price,
            discountedPrice,
            categoryId,
            globalCategoryId,
            globalSubcategoryId,
            masterMenuItemId,
            cuisineId,
            required String foodType,
            required bool isAvailable,
            required bool isPriceInclusiveGst,
            preparationTime,
            calories,
            required List<String> tags,
            required List<String> imagePaths,
            required List<String> existingImages,
            required List<Map<String, dynamic>> variants,
            required List<Map<String, dynamic>> addOns,
          }) async {
            await _createMenuItem(
              name: name,
              description: description,
              price: price,
              discountedPrice: discountedPrice,
              categoryId: categoryId,
              globalCategoryId: globalCategoryId,
              globalSubcategoryId: globalSubcategoryId,
              masterMenuItemId: masterMenuItemId,
              cuisineId: cuisineId,
              foodType: foodType,
              isAvailable: isAvailable,
              isPriceInclusiveGst: isPriceInclusiveGst,
              preparationTime: preparationTime,
              calories: calories,
              tags: tags,
              imagePaths: imagePaths,
              existingImages: existingImages,
              variants: variants,
              addOns: addOns,
            );
          },
        ),
      ),
    );
    return;

    // ignore: dead_code
    final formKey = GlobalKey<FormState>();
    final nameController = TextEditingController();
    final descriptionController = TextEditingController();
    final priceController = TextEditingController();
    final discountedPriceController = TextEditingController();
    final variantsController = TextEditingController();
    final addOnsController = TextEditingController();
    int? selectedCategoryId;
    int? selectedGlobalCategoryId;
    int? selectedGlobalSubcategoryId;
    int? selectedCuisineId;
    XFile? selectedImage;
    String foodType = 'veg';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
          ),
          child: SingleChildScrollView(
            child: Form(
              key: formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.all(16),
                    child: Text(
                      'Add Menu Item',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  const Divider(),
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        TextFormField(
                          controller: nameController,
                          decoration: const InputDecoration(
                            labelText: 'Item Name',
                            border: OutlineInputBorder(),
                          ),
                          validator: (value) =>
                              value?.isEmpty == true ? 'Required' : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: descriptionController,
                          decoration: const InputDecoration(
                            labelText: 'Description',
                            border: OutlineInputBorder(),
                          ),
                          maxLines: 2,
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          decoration: const InputDecoration(
                            labelText: 'Restaurant Category',
                            border: OutlineInputBorder(),
                          ),
                          items: [
                            const DropdownMenuItem(
                                value: null, child: Text('Select Category')),
                            ..._categories.map((cat) => DropdownMenuItem(
                                  value: cat['id'],
                                  child: Text(cat['name']),
                                )),
                          ],
                          onChanged: (value) {
                            selectedCategoryId = value;
                            if (value != null) selectedGlobalCategoryId = null;
                          },
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          decoration: const InputDecoration(
                            labelText: 'Or Select Global Category',
                            border: OutlineInputBorder(),
                          ),
                          items: [
                            const DropdownMenuItem(
                                value: null,
                                child: Text('Select Global Category')),
                            ..._globalCategories
                                .map((cat) => DropdownMenuItem<int>(
                                      value: cat['id'] is int
                                          ? cat['id'] as int
                                          : int.tryParse(cat['id'].toString()),
                                      child:
                                          Text(cat['name']?.toString() ?? ''),
                                    )),
                          ],
                          onChanged: (value) {
                            selectedGlobalCategoryId = value;
                            selectedGlobalSubcategoryId = null;
                            if (value != null) selectedCategoryId = null;
                            setState(() {});
                          },
                          validator: (_) => selectedCategoryId == null &&
                                  selectedGlobalCategoryId == null
                              ? 'Select a category'
                              : null,
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          value: selectedGlobalSubcategoryId,
                          decoration: const InputDecoration(
                            labelText: 'Global Sub Category',
                            border: OutlineInputBorder(),
                          ),
                          items: [
                            const DropdownMenuItem<int>(
                              value: null,
                              child: Text('Select Sub Category'),
                            ),
                            ..._globalSubcategories(selectedGlobalCategoryId)
                                .map((subcategory) => DropdownMenuItem<int>(
                                      value: _asInt(subcategory['id']),
                                      child: Text(
                                        subcategory['name']?.toString() ?? '',
                                      ),
                                    )),
                          ],
                          onChanged: selectedGlobalCategoryId == null
                              ? null
                              : (value) => setState(
                                  () => selectedGlobalSubcategoryId = value),
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          decoration: const InputDecoration(
                            labelText: 'Cuisine',
                            border: OutlineInputBorder(),
                          ),
                          items: [
                            const DropdownMenuItem(
                                value: null, child: Text('Select Cuisine')),
                            ..._cuisines.map((cuisine) => DropdownMenuItem<int>(
                                  value: cuisine['id'],
                                  child:
                                      Text(cuisine['name']?.toString() ?? ''),
                                )),
                          ],
                          onChanged: (value) => selectedCuisineId = value,
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                controller: priceController,
                                decoration: InputDecoration(
                                  labelText: 'Price',
                                  prefixText: currencyInputPrefix(context),
                                  border: const OutlineInputBorder(),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true),
                                validator: (value) =>
                                    value?.isEmpty == true ? 'Required' : null,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: discountedPriceController,
                                decoration: InputDecoration(
                                  labelText: 'Discounted Price',
                                  prefixText: currencyInputPrefix(context),
                                  border: const OutlineInputBorder(),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<String>(
                          value: foodType,
                          decoration: const InputDecoration(
                            labelText: 'Food Type',
                            border: OutlineInputBorder(),
                          ),
                          items: const [
                            DropdownMenuItem(value: 'veg', child: Text('Veg')),
                            DropdownMenuItem(value: 'egg', child: Text('Egg')),
                            DropdownMenuItem(
                                value: 'non_veg', child: Text('Non-Veg')),
                          ],
                          onChanged: (value) =>
                              setState(() => foodType = value ?? 'veg'),
                        ),
                        const SizedBox(height: 12),
                        _MenuOptionEditor(
                          controller: variantsController,
                          label: 'Variants (Size / Quantity)',
                          addButtonLabel: 'Add Variant',
                          placeholder: 'Medium / 500g',
                          helpText:
                              'Customers must choose one available variant when variants are configured.',
                          emptyText:
                              'No variants added. Add sizes, weights, portions, or quantity choices.',
                        ),
                        const SizedBox(height: 12),
                        _MenuOptionEditor(
                          controller: addOnsController,
                          label: 'Add-ons / Extras',
                          addButtonLabel: 'Add Extra',
                          placeholder: 'Extra cheese',
                          helpText:
                              'Customers can select multiple available extras during add-to-cart.',
                          emptyText:
                              'No extras added. Add toppings, sides, sauces, or paid extras.',
                        ),
                        const SizedBox(height: 12),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(12),
                          decoration: FoodFlowTheme.softSurface(radius: 12),
                          child: Row(
                            children: [
                              Container(
                                width: 54,
                                height: 54,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFF3E8),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Icon(
                                  Icons.image_outlined,
                                  color: FoodFlowTheme.orange,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  selectedImage == null
                                      ? 'No item image selected'
                                      : selectedImage!.name,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: FoodFlowTheme.ink,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              TextButton.icon(
                                onPressed: () async {
                                  final image = await ImagePicker().pickImage(
                                    source: ImageSource.gallery,
                                    imageQuality: 85,
                                  );
                                  if (image != null) {
                                    setState(() => selectedImage = image);
                                  }
                                },
                                icon: const Icon(Icons.upload_file),
                                label: const Text('Upload'),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: () async {
                              if (formKey.currentState!.validate()) {
                                Navigator.pop(context);
                                await _createMenuItem(
                                  name: nameController.text,
                                  description: descriptionController.text,
                                  price: double.parse(priceController.text),
                                  discountedPrice:
                                      discountedPriceController.text.isNotEmpty
                                          ? double.parse(
                                              discountedPriceController.text)
                                          : null,
                                  categoryId: selectedCategoryId,
                                  globalCategoryId: selectedGlobalCategoryId,
                                  globalSubcategoryId:
                                      selectedGlobalSubcategoryId,
                                  cuisineId: selectedCuisineId,
                                  foodType: foodType,
                                  imagePath: selectedImage?.path,
                                  variants: _parseMenuOptions(
                                      variantsController.text),
                                  addOns:
                                      _parseMenuOptions(addOnsController.text),
                                );
                              }
                            },
                            child: const Text('Add Item'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _showEditItemDialog(MenuItem item) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _MenuItemFormScreen(
          item: item,
          categories: _categories,
          globalCategories: _globalCategories,
          globalMenuItems: _globalMenuItems,
          cuisines: _cuisines,
          availableAddOns: _realAddOns(),
          onSubmit: ({
            required String name,
            description,
            required double price,
            discountedPrice,
            categoryId,
            globalCategoryId,
            globalSubcategoryId,
            masterMenuItemId,
            cuisineId,
            required String foodType,
            required bool isAvailable,
            required bool isPriceInclusiveGst,
            preparationTime,
            calories,
            required List<String> tags,
            required List<String> imagePaths,
            required List<String> existingImages,
            required List<Map<String, dynamic>> variants,
            required List<Map<String, dynamic>> addOns,
          }) async {
            await _updateMenuItem(
              itemId: item.id,
              name: name,
              description: description,
              price: price,
              discountedPrice: discountedPrice,
              categoryId: categoryId,
              globalCategoryId: globalCategoryId,
              globalSubcategoryId: globalSubcategoryId,
              masterMenuItemId: masterMenuItemId,
              cuisineId: cuisineId,
              foodType: foodType,
              isAvailable: isAvailable,
              isPriceInclusiveGst: isPriceInclusiveGst,
              preparationTime: preparationTime,
              calories: calories,
              tags: tags,
              imagePaths: imagePaths,
              existingImages: existingImages,
              variants: variants,
              addOns: addOns,
            );
          },
        ),
      ),
    );
    return;

    // ignore: dead_code
    final formKey = GlobalKey<FormState>();
    final nameController = TextEditingController(text: item.name);
    final descriptionController =
        TextEditingController(text: item.description ?? '');
    final priceController = TextEditingController(
      text: item.price.toStringAsFixed(getCurrencyDecimals(context)),
    );
    final discountedPriceController = TextEditingController(
      text:
          item.discountedPrice?.toStringAsFixed(getCurrencyDecimals(context)) ??
              '',
    );
    final variantsController = TextEditingController(
      text: _formatMenuOptions(item.variants),
    );
    final addOnsController = TextEditingController(
      text: _formatMenuOptions(item.addOns),
    );
    int? selectedCategoryId = item.categoryId;
    int? selectedGlobalCategoryId;
    int? selectedCuisineId = item.cuisineId;
    String foodType = item.foodType;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
          ),
          child: SingleChildScrollView(
            child: Form(
              key: formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
                    child: Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Edit Menu Item',
                            style: TextStyle(
                              color: FoodFlowTheme.ink,
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(context),
                          icon: const Icon(Icons.close),
                        ),
                      ],
                    ),
                  ),
                  const Divider(),
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        TextFormField(
                          controller: nameController,
                          decoration: const InputDecoration(
                            labelText: 'Item Name',
                            prefixIcon: Icon(Icons.restaurant_menu),
                          ),
                          validator: (value) =>
                              value?.trim().isEmpty == true ? 'Required' : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: descriptionController,
                          decoration: const InputDecoration(
                            labelText: 'Description',
                            prefixIcon: Icon(Icons.notes),
                          ),
                          maxLines: 2,
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          value: selectedCategoryId,
                          decoration: const InputDecoration(
                            labelText: 'Restaurant Category',
                            prefixIcon: Icon(Icons.category_outlined),
                          ),
                          items: [
                            const DropdownMenuItem(
                              value: null,
                              child: Text('Select Category'),
                            ),
                            ..._categories.map(
                              (cat) => DropdownMenuItem<int>(
                                value: cat['id'],
                                child: Text(cat['name']),
                              ),
                            ),
                          ],
                          onChanged: (value) {
                            selectedCategoryId = value;
                            if (value != null) selectedGlobalCategoryId = null;
                          },
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          value: selectedGlobalCategoryId,
                          decoration: const InputDecoration(
                            labelText: 'Or Select Global Category',
                            prefixIcon: Icon(Icons.public),
                          ),
                          items: [
                            const DropdownMenuItem(
                              value: null,
                              child: Text('Select Global Category'),
                            ),
                            ..._globalCategories.map(
                              (cat) => DropdownMenuItem<int>(
                                value: cat['id'] is int
                                    ? cat['id'] as int
                                    : int.tryParse(cat['id'].toString()),
                                child: Text(cat['name']?.toString() ?? ''),
                              ),
                            ),
                          ],
                          onChanged: (value) {
                            selectedGlobalCategoryId = value;
                            if (value != null) selectedCategoryId = null;
                          },
                          validator: (_) => selectedCategoryId == null &&
                                  selectedGlobalCategoryId == null
                              ? 'Select a category'
                              : null,
                        ),
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          value: selectedCuisineId,
                          decoration: const InputDecoration(
                            labelText: 'Cuisine',
                            prefixIcon: Icon(Icons.ramen_dining_outlined),
                          ),
                          items: [
                            const DropdownMenuItem(
                              value: null,
                              child: Text('Select Cuisine'),
                            ),
                            ..._cuisines.map(
                              (cuisine) => DropdownMenuItem<int>(
                                value: cuisine['id'],
                                child: Text(cuisine['name']?.toString() ?? ''),
                              ),
                            ),
                          ],
                          onChanged: (value) => selectedCuisineId = value,
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                controller: priceController,
                                decoration: InputDecoration(
                                  labelText: 'Price',
                                  prefixText: currencyInputPrefix(context),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true),
                                validator: (value) {
                                  final parsed = double.tryParse(value ?? '');
                                  if (parsed == null || parsed <= 0) {
                                    return 'Invalid';
                                  }
                                  return null;
                                },
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: discountedPriceController,
                                decoration: InputDecoration(
                                  labelText: 'Discounted',
                                  prefixText: currencyInputPrefix(context),
                                ),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true),
                                validator: (value) {
                                  if (value == null || value.trim().isEmpty) {
                                    return null;
                                  }
                                  final parsed = double.tryParse(value);
                                  if (parsed == null || parsed < 0) {
                                    return 'Invalid';
                                  }
                                  return null;
                                },
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Container(
                          decoration: FoodFlowTheme.softSurface(radius: 12),
                          child: DropdownButtonFormField<String>(
                            value: foodType,
                            decoration: const InputDecoration(
                              labelText: 'Food Type',
                              prefixIcon: Icon(Icons.restaurant),
                            ),
                            items: const [
                              DropdownMenuItem(
                                  value: 'veg', child: Text('Veg')),
                              DropdownMenuItem(
                                  value: 'egg', child: Text('Egg')),
                              DropdownMenuItem(
                                  value: 'non_veg', child: Text('Non-Veg')),
                            ],
                            onChanged: (value) =>
                                setSheetState(() => foodType = value ?? 'veg'),
                          ),
                        ),
                        const SizedBox(height: 12),
                        _MenuOptionEditor(
                          controller: variantsController,
                          label: 'Variants (Size / Quantity)',
                          addButtonLabel: 'Add Variant',
                          placeholder: 'Medium / 500g',
                          helpText:
                              'Customers must choose one available variant when variants are configured.',
                          emptyText:
                              'No variants added. Add sizes, weights, portions, or quantity choices.',
                        ),
                        const SizedBox(height: 12),
                        _MenuOptionEditor(
                          controller: addOnsController,
                          label: 'Add-ons / Extras',
                          addButtonLabel: 'Add Extra',
                          placeholder: 'Extra cheese',
                          helpText:
                              'Customers can select multiple available extras during add-to-cart.',
                          emptyText:
                              'No extras added. Add toppings, sides, sauces, or paid extras.',
                        ),
                        const SizedBox(height: 18),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: () async {
                              if (!formKey.currentState!.validate()) return;
                              Navigator.pop(context);
                              await _updateMenuItem(
                                itemId: item.id,
                                name: nameController.text.trim(),
                                description: descriptionController.text.trim(),
                                price: double.parse(priceController.text),
                                discountedPrice: discountedPriceController.text
                                        .trim()
                                        .isNotEmpty
                                    ? double.parse(
                                        discountedPriceController.text.trim())
                                    : null,
                                categoryId: selectedCategoryId,
                                globalCategoryId: selectedGlobalCategoryId,
                                cuisineId: selectedCuisineId,
                                foodType: foodType,
                                variants:
                                    _parseMenuOptions(variantsController.text),
                                addOns:
                                    _parseMenuOptions(addOnsController.text),
                              );
                            },
                            icon: const Icon(Icons.save_outlined),
                            label: const Text('Save Changes'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _createMenuItem({
    required String name,
    String? description,
    required double price,
    double? discountedPrice,
    int? categoryId,
    int? globalCategoryId,
    int? globalSubcategoryId,
    int? masterMenuItemId,
    int? cuisineId,
    required String foodType,
    bool isAvailable = true,
    bool isPriceInclusiveGst = false,
    int? preparationTime,
    String? calories,
    List<String> tags = const [],
    String? imagePath,
    List<String> imagePaths = const [],
    List<String> existingImages = const [],
    List<Map<String, dynamic>> variants = const [],
    List<Map<String, dynamic>> addOns = const [],
  }) async {
    try {
      final data = {
        'name': name,
        'description': description,
        'price': price,
        'discounted_price': discountedPrice,
        if (categoryId != null) 'category_id': categoryId,
        if (globalCategoryId != null) 'global_category_id': globalCategoryId,
        if (globalSubcategoryId != null)
          'global_subcategory_id': globalSubcategoryId,
        'master_menu_item_id': masterMenuItemId,
        'cuisine_id': cuisineId,
        'food_type': foodType,
        'is_veg': foodType == 'veg',
        'is_available': isAvailable,
        'is_price_inclusive_gst': isPriceInclusiveGst,
        if (preparationTime != null) 'preparation_time': preparationTime,
        if (calories != null && calories.trim().isNotEmpty)
          'calories': calories.trim(),
        if (tags.isNotEmpty) 'tags': tags,
        'variants': variants,
        'add_ons': addOns,
      };
      final uploads = [
        if (imagePath != null && imagePath.trim().isNotEmpty) imagePath,
        ...imagePaths.where((path) => path.trim().isNotEmpty),
      ];

      final response = uploads.isEmpty
          ? await _api.post(ApiConstants.restaurantMenuItems,
              data: data, queryParams: _selectedRestaurantQueryParams())
          : await _api.postMultipart(
              ApiConstants.restaurantMenuItems,
              queryParams: _selectedRestaurantQueryParams(),
              fields: data.map(
                (key, value) => MapEntry(
                  key,
                  value is List ? jsonEncode(value) : value?.toString() ?? '',
                ),
              ),
              files: {'image': uploads.first},
              fileLists: uploads.length > 1 ? {'images[]': uploads} : null,
            );

      if (response['success'] == true) {
        await _loadData();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Item added successfully')),
          );
        }
      }
    } catch (e) {
      debugPrint('Create item error: $e');
    }
  }

  Future<void> _importGlobalMenuItem({
    required int masterMenuItemId,
    required double price,
    double? discountedPrice,
    int? preparationTime,
    int? globalCategoryId,
    int? globalSubcategoryId,
    List<Map<String, dynamic>> variants = const [],
    List<Map<String, dynamic>> addOns = const [],
  }) async {
    try {
      final response = await _api.post(
        '${ApiConstants.restaurantMenuItems}/from-global',
        queryParams: _selectedRestaurantQueryParams(),
        data: {
          'items': [
            {
              'master_menu_item_id': masterMenuItemId,
              'price': price,
              'discounted_price': discountedPrice,
              'preparation_time': preparationTime,
              'is_available': true,
              if (globalCategoryId != null)
                'global_category_id': globalCategoryId,
              if (globalSubcategoryId != null)
                'global_subcategory_id': globalSubcategoryId,
              'variants': variants,
              'add_ons': addOns,
            }
          ],
        },
      );

      if (response['success'] == true) {
        final addedItems = response['data'];
        final addedCount = addedItems is List ? addedItems.length : 0;
        await _loadData();
        if (mounted) {
          if (addedCount > 0) {
            setState(() => _selectedCategoryId = 0);
            _tabController?.animateTo(0);
          }
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                response['message']?.toString() ??
                    (addedCount > 0
                        ? 'Global menu item added'
                        : 'No global menu item was added'),
              ),
            ),
          );
        }
      }
    } catch (e) {
      debugPrint('Import global item error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString().replaceFirst('Exception: ', ''))),
        );
      }
    }
  }

  Future<void> _updateMenuItem({
    required int itemId,
    required String name,
    String? description,
    required double price,
    double? discountedPrice,
    int? categoryId,
    int? globalCategoryId,
    int? globalSubcategoryId,
    int? masterMenuItemId,
    int? cuisineId,
    required String foodType,
    bool isAvailable = true,
    bool isPriceInclusiveGst = false,
    int? preparationTime,
    String? calories,
    List<String> tags = const [],
    List<String> imagePaths = const [],
    List<String> existingImages = const [],
    List<Map<String, dynamic>> variants = const [],
    List<Map<String, dynamic>> addOns = const [],
  }) async {
    try {
      final payload = {
        'name': name,
        'description': description,
        'price': price,
        'discounted_price': discountedPrice,
        if (categoryId != null) 'category_id': categoryId,
        if (globalCategoryId != null) 'global_category_id': globalCategoryId,
        if (globalSubcategoryId != null)
          'global_subcategory_id': globalSubcategoryId,
        'master_menu_item_id': masterMenuItemId,
        'cuisine_id': cuisineId,
        'food_type': foodType,
        'is_veg': foodType == 'veg',
        'is_available': isAvailable,
        'is_price_inclusive_gst': isPriceInclusiveGst,
        if (preparationTime != null) 'preparation_time': preparationTime,
        if (calories != null && calories.trim().isNotEmpty)
          'calories': calories.trim(),
        if (tags.isNotEmpty) 'tags': tags,
        'existing_images': existingImages,
        'variants': variants,
        'add_ons': addOns,
      };

      final response = imagePaths.isEmpty
          ? await _api.post(
              '${ApiConstants.restaurantMenuItems}/$itemId',
              data: payload,
              queryParams: _selectedRestaurantQueryParams(),
            )
          : await _api.postMultipart(
              '${ApiConstants.restaurantMenuItems}/$itemId',
              queryParams: _selectedRestaurantQueryParams(),
              fields: payload.map(
                (key, value) => MapEntry(
                  key,
                  value is List ? jsonEncode(value) : value?.toString() ?? '',
                ),
              ),
              files: {'image': imagePaths.first},
              fileLists:
                  imagePaths.length > 1 ? {'images[]': imagePaths} : null,
            );

      if (response['success'] == true) {
        await _loadData();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Item updated successfully')),
          );
        }
      }
    } catch (e) {
      debugPrint('Update item error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update item: $e')),
        );
      }
    }
  }

  Future<void> _showAddCategoryDialog() async {
    final formKey = GlobalKey<FormState>();
    final nameController = TextEditingController();

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Add Category',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 12),
              Form(
                key: formKey,
                child: TextFormField(
                  controller: nameController,
                  decoration: const InputDecoration(
                    labelText: 'Category Name',
                    border: OutlineInputBorder(),
                  ),
                  validator: (value) =>
                      value?.isEmpty == true ? 'Required' : null,
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    if (formKey.currentState!.validate()) {
                      Navigator.pop(context);
                      await _createCategory(nameController.text.trim());
                    }
                  },
                  child: const Text('Create Category'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _createCategory(String name) async {
    try {
      final response =
          await _api.post(ApiConstants.restaurantCategories, data: {
        'name': name,
      });
      if (response['success'] == true) {
        await _loadData();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Category created successfully')),
          );
        }
      }
    } catch (e) {
      debugPrint('Create category error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to create category: $e')),
        );
      }
    }
  }

  List<Map<String, dynamic>> _parseMenuOptions(String text) {
    return text
        .split(RegExp(r'\\n|\r\n|\r|\n'))
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .map((line) {
          final parts = line.split('|').map((part) => part.trim()).toList();
          final name = parts.isNotEmpty ? parts[0] : '';
          final price =
              parts.length > 1 ? double.tryParse(parts[1]) ?? 0.0 : 0.0;
          final availability =
              parts.length > 2 ? parts[2].toLowerCase() : 'yes';
          final customFields = <String, String>{};

          if (parts.length > 3) {
            for (final field in parts[3].split(';')) {
              final fieldParts = field.split('=');
              if (fieldParts.length < 2) continue;
              final key = fieldParts.first.trim();
              final value = fieldParts.sublist(1).join('=').trim();
              if (key.isNotEmpty && value.isNotEmpty) {
                customFields[key] = value;
              }
            }
          }

          return {
            'name': name,
            'price': price < 0 ? 0 : price,
            'is_available': !['no', 'false', '0', 'off'].contains(availability),
            'custom_fields': customFields,
          };
        })
        .where((option) => (option['name'] as String).isNotEmpty)
        .toList();
  }

  String _formatMenuOptions(List<MenuOption> options) {
    return options.map((option) {
      final customFields = option.customFields.entries
          .map((entry) => '${entry.key}=${entry.value}')
          .join('; ');
      return [
        option.name,
        option.price.toStringAsFixed(getCurrencyDecimals(context)),
        option.isAvailable ? 'yes' : 'no',
        if (customFields.isNotEmpty) customFields,
      ].join(' | ');
    }).join('\\n');
  }

  String _formatRawMenuOptions(dynamic rawOptions) {
    dynamic value = rawOptions;
    if (value is String || value == null) return '';
    if (value is! List) return '';

    return value
        .map((option) {
          if (option is String) return option;
          if (option is! Map) return '';

          final customFields = option['custom_fields'] is Map
              ? (option['custom_fields'] as Map)
                  .entries
                  .map((entry) => '${entry.key}=${entry.value}')
                  .join('; ')
              : '';

          return [
            option['name'] ?? option['label'] ?? option['title'] ?? '',
            option['price'] ??
                option['additional_price'] ??
                option['amount'] ??
                0,
            option['is_available'] == false ? 'no' : 'yes',
            if (customFields.isNotEmpty) customFields,
          ].join(' | ');
        })
        .where((line) => line.toString().trim().isNotEmpty)
        .join('\\n');
  }

  Map<String, dynamic>? _selectedRestaurant() {
    final provider = Provider.of<RestaurantProvider>(context, listen: false);
    final selectedId = provider.selectedRestaurantId;
    if (selectedId == null) return null;
    for (final restaurant in provider.restaurants) {
      if (_asInt(restaurant['id']) == selectedId) return restaurant;
    }
    return provider.restaurant;
  }

  String _restaurantTitle(Map<String, dynamic>? restaurant) {
    return restaurant?['name']?.toString() ?? 'Your Menu';
  }

  String _restaurantSubtitle(Map<String, dynamic>? restaurant) {
    final parts = [
      restaurant?['area'],
      restaurant?['city'],
      restaurant?['address'],
    ]
        .map((part) => part?.toString().trim() ?? '')
        .where((part) => part.isNotEmpty)
        .toList();
    return parts.isEmpty ? '' : parts.first;
  }

  List<_MenuAddonView> _realAddOns() {
    final map = <String, _MenuAddonView>{};
    for (final item in _menuItems) {
      for (final addOn in item.addOns) {
        final key = addOn.name.trim().toLowerCase();
        if (key.isEmpty) continue;
        map[key] = _MenuAddonView(
          name: addOn.name,
          price: addOn.price,
          isAvailable: addOn.isAvailable,
          sourceItem: item,
        );
      }
    }
    final addOns = map.values.toList()
      ..sort((a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()));
    return addOns;
  }

  Map<String, List<MenuItem>> _groupedVisibleItems() {
    final grouped = <String, List<MenuItem>>{};
    for (final item in _visibleMenuItems()) {
      if (item.isDisabled || item.isOutOfStock) continue;
      final category = item.categoryName?.trim().isNotEmpty == true
          ? item.categoryName!.trim()
          : 'Uncategorized';
      grouped.putIfAbsent(category, () => <MenuItem>[]).add(item);
    }
    return grouped;
  }

  int _categoryIdForName(String name) {
    final category = _categories.firstWhere(
      (item) => item['name']?.toString() == name,
      orElse: () => <String, dynamic>{},
    );
    return _asInt(category['id']) ?? name.hashCode;
  }

  void _showPreviewItemDialog(MenuItem item) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => SafeArea(
        top: false,
        child: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.sizeOf(context).height * 0.72,
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(18, 14, 18, 18),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          item.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.close),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: item.imageUrl.isEmpty
                            ? Container(
                                width: 72,
                                height: 72,
                                color: const Color(0xFFEDEDEE),
                                child: const Icon(Icons.fastfood_outlined,
                                    color: FoodFlowTheme.muted),
                              )
                            : NetworkImageLoader(
                                imageUrl: item.imageUrl,
                                width: 72,
                                height: 72,
                                fit: BoxFit.cover,
                                borderRadius: BorderRadius.circular(12),
                              ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                if (item.hasDiscount) ...[
                                  Text(
                                    formatCurrency(context, item.price),
                                    style: const TextStyle(
                                      color: FoodFlowTheme.faint,
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                      decoration: TextDecoration.lineThrough,
                                    ),
                                  ),
                                  const SizedBox(width: 7),
                                ],
                                Text(
                                  formatCurrency(context, item.finalPrice),
                                  style: const TextStyle(
                                    color: FoodFlowTheme.success,
                                    fontSize: 18,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              [
                                item.dietLabel,
                                if (item.categoryName?.trim().isNotEmpty ==
                                    true)
                                  item.categoryName!,
                                if (item.preparationTime != null)
                                  '${item.preparationTime} min',
                              ].join(_menuMetadataSeparator),
                              style: const TextStyle(
                                color: FoodFlowTheme.muted,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            if (item.isPriceInclusiveGst) ...[
                              const SizedBox(height: 5),
                              const Text(
                                'Inclusive of GST',
                                style: TextStyle(
                                  color: FoodFlowTheme.muted,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                  if (item.description?.trim().isNotEmpty == true) ...[
                    const SizedBox(height: 14),
                    Text(
                      item.description!,
                      style: const TextStyle(
                        color: FoodFlowTheme.inkSoft,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                  if (item.variants.isNotEmpty || item.addOns.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    if (item.variants.isNotEmpty)
                      _PreviewOptionLine(
                        title: 'Variants',
                        options: item.variants,
                      ),
                    if (item.addOns.isNotEmpty)
                      _PreviewOptionLine(
                        title: 'Add-ons',
                        options: item.addOns,
                      ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _showAddOnDialog() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _MenuItemFormScreen(
          categories: _categories,
          globalCategories: _globalCategories,
          globalMenuItems: _globalMenuItems,
          cuisines: _cuisines,
          addOnOnly: true,
          onSubmit: (
              {required String name,
              description,
              required double price,
              discountedPrice,
              categoryId,
              globalCategoryId,
              globalSubcategoryId,
              masterMenuItemId,
              cuisineId,
              required String foodType,
              required bool isAvailable,
              required bool isPriceInclusiveGst,
              preparationTime,
              calories,
              required List<String> tags,
              required List<String> imagePaths,
              required List<String> existingImages,
              required List<Map<String, dynamic>> variants,
              required List<Map<String, dynamic>> addOns}) async {
            await _createMenuItem(
              name: name,
              description: description,
              price: price,
              discountedPrice: discountedPrice,
              foodType: foodType,
              isAvailable: isAvailable,
              addOns: [
                {
                  'name': name,
                  'price': price,
                  'is_available': isAvailable,
                  'custom_fields': <String, String>{}
                }
              ],
            );
          },
        ),
      ),
    );
  }

  Future<void> _showGlobalImportSheet(dynamic rawItem) async {
    if (rawItem is! Map) return;
    final item = Map<String, dynamic>.from(rawItem);
    final masterId = _asInt(item['id'] ?? item['master_menu_item_id']);
    if (masterId == null) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to import this global item.')),
      );
      return;
    }

    final result = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _GlobalItemImportSheet(item: item),
    );
    if (!mounted || result == null) return;

    await _importGlobalMenuItem(
      masterMenuItemId: masterId,
      price: result['price'] as double,
      discountedPrice: result['discounted_price'] as double?,
      preparationTime: result['preparation_time'] as int?,
      globalCategoryId:
          _asInt(item['global_category_id'] ?? item['category_id']),
      globalSubcategoryId:
          _asInt(item['global_subcategory_id'] ?? item['subcategory_id']),
      variants: _parseMenuOptions(_formatRawMenuOptions(item['variants'])),
      addOns: _parseMenuOptions(
        _formatRawMenuOptions(item['add_ons'] ?? item['addons']),
      ),
    );

    if (!mounted) return;
    setState(() {
      _showMenuModePicker = false;
      _showGlobalCatalog = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RestaurantProvider>();
    final selected = _selectedRestaurant();
    if (_showOutletPicker || selected == null) {
      return _OutletPickerScreen(
        restaurants: provider.restaurants,
        selectedRestaurantId: provider.selectedRestaurantId,
        isLoading: _isLoading,
        onSelect: (id) async {
          await provider.selectRestaurant(id);
          if (!mounted) return;
          setState(() {
            _showOutletPicker = false;
            _showMenuModePicker = true;
            _showGlobalCatalog = false;
            _expandedCategoryIds.clear();
            _searchController.clear();
            _searchQuery = '';
          });
          await _loadData();
        },
      );
    }

    if (_showMenuModePicker) {
      return _MenuModeScreen(
        restaurantName: _restaurantTitle(selected),
        onBack: () => setState(() {
          _showOutletPicker = true;
          _showMenuModePicker = false;
          _showGlobalCatalog = false;
        }),
        onCustomMenu: () => setState(() {
          _showMenuModePicker = false;
          _showGlobalCatalog = false;
        }),
        onGlobalMenu: () => setState(() {
          _showMenuModePicker = false;
          _showGlobalCatalog = true;
        }),
      );
    }

    if (_showGlobalCatalog) {
      return _GlobalMenuCatalogScreen(
        items: _globalMenuItems,
        onBack: () => setState(() {
          _showMenuModePicker = true;
          _showGlobalCatalog = false;
        }),
        onSelect: _showGlobalImportSheet,
      );
    }
    final grouped = _groupedVisibleItems();
    final addons = _realAddOns();
    final visibleItems = _visibleMenuItems();
    final outOfStockItems =
        visibleItems.where((item) => item.isOutOfStock).toList(growable: false);
    final disabledItems =
        visibleItems.where((item) => item.isDisabled).toList(growable: false);

    return Scaffold(
      backgroundColor: const Color(0xFFF1F1F5),
      appBar: AppBar(
        backgroundColor: const Color(0xFFF1F1F5),
        titleSpacing: 0,
        leading: IconButton(
            icon: const Icon(Icons.arrow_back),
            onPressed: () => setState(() {
                  _showMenuModePicker = true;
                  _showGlobalCatalog = false;
                })),
        title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_restaurantTitle(selected),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      fontSize: 17, fontWeight: FontWeight.w900)),
              Text(_restaurantSubtitle(selected),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w700)),
            ]),
        actions: [
          PopupMenuButton<String>(
              onSelected: (_) => setState(() {
                    _showOutletPicker = true;
                    _showMenuModePicker = false;
                    _showGlobalCatalog = false;
                  }),
              itemBuilder: (_) => const [
                    PopupMenuItem(value: 'outlet', child: Text('Change outlet'))
                  ])
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: FoodFlowTheme.ink,
        foregroundColor: Colors.white,
        onPressed: _menuTabIndex == 0 ? _showAddItemDialog : _showAddOnDialog,
        icon: const Icon(Icons.add),
        label: Text(_menuTabIndex == 0 ? 'MENU' : 'ADD-ON'),
      ),
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.fromLTRB(0, 18, 0, 104),
                children: [
                    Container(
                      padding: const EdgeInsets.fromLTRB(18, 18, 18, 22),
                      decoration: const BoxDecoration(
                          color: Colors.white,
                          borderRadius:
                              BorderRadius.vertical(top: Radius.circular(24))),
                      child: Column(children: [
                        Row(children: [
                          _MenuPill(
                              label: 'Menu Items',
                              selected: _menuTabIndex == 0,
                              onTap: () => setState(() => _menuTabIndex = 0)),
                          const SizedBox(width: 10),
                          _MenuPill(
                              label: 'Add-ons',
                              selected: _menuTabIndex == 1,
                              onTap: () => setState(() => _menuTabIndex = 1)),
                          const Spacer(),
                          if (_menuTabIndex == 0)
                            TextButton.icon(
                                onPressed: _showAddCategoryDialog,
                                icon: const Icon(Icons.add, size: 17),
                                label: const Text('Create Category')),
                        ]),
                        const SizedBox(height: 16),
                        TextField(
                            controller: _searchController,
                            onChanged: (value) =>
                                setState(() => _searchQuery = value),
                            decoration: InputDecoration(
                                hintText: _menuTabIndex == 0
                                    ? 'Search for items'
                                    : 'Search for add-on',
                                suffixIcon: const Icon(Icons.search, size: 28),
                                filled: true,
                                fillColor: const Color(0xFFF0F0F4),
                                border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(14),
                                    borderSide: BorderSide.none))),
                      ]),
                    ),
                    if (_menuTabIndex == 0) ...[
                      if (outOfStockItems.isNotEmpty)
                        _MenuStatusBlock(
                          color: const Color(0xFFFFEEF1),
                          icon: Icons.error,
                          label:
                              '${outOfStockItems.length} ITEM${outOfStockItems.length == 1 ? '' : 'S'} OUT OF STOCK',
                          items: outOfStockItems,
                          onPreview: _showPreviewItemDialog,
                          onEdit: _showEditItemDialog,
                          onDelete: (item) => _deleteMenuItem(item.id),
                          onToggle: (item) =>
                              _toggleAvailability(item.id, item.isAvailable),
                        ),
                      if (disabledItems.isNotEmpty)
                        _MenuStatusBlock(
                          color: const Color(0xFFEDEDEE),
                          icon: Icons.cancel,
                          label:
                              '${disabledItems.length} ITEM${disabledItems.length == 1 ? '' : 'S'} DISABLED',
                          items: disabledItems,
                          onPreview: _showPreviewItemDialog,
                          onEdit: _showEditItemDialog,
                          onDelete: (item) => _deleteMenuItem(item.id),
                          onToggle: (item) =>
                              _toggleAvailability(item.id, item.isAvailable),
                        ),
                      if (grouped.isEmpty)
                        Padding(
                            padding: const EdgeInsets.only(top: 72),
                            child: FoodFlowTheme.emptyState(
                                icon: Icons.menu_book_outlined,
                                title: 'No menu items',
                                subtitle:
                                    'Items from this outlet will appear here once added.'))
                      else
                        ...grouped.entries.map((entry) {
                          final id = _categoryIdForName(entry.key);
                          final open = _expandedCategoryIds.contains(id);
                          return _MenuCategoryBlock(
                              title: entry.key,
                              expanded: open,
                              items: entry.value,
                              onToggleExpanded: () => setState(() => open
                                  ? _expandedCategoryIds.remove(id)
                                  : _expandedCategoryIds.add(id)),
                              onAddItem: _showAddItemDialog,
                              onPreview: _showPreviewItemDialog,
                              onEdit: _showEditItemDialog,
                              onDelete: (item) => _deleteMenuItem(item.id),
                              onToggle: (item) => _toggleAvailability(
                                  item.id, item.isAvailable));
                        }),
                    ] else if (addons.isEmpty)
                      Container(
                          color: Colors.white,
                          padding: const EdgeInsets.only(top: 72),
                          child: FoodFlowTheme.emptyState(
                              icon: Icons.playlist_add_outlined,
                              title: 'No add-ons found',
                              subtitle:
                                  'Add-ons saved on real menu items will appear here.'))
                    else
                      Container(
                          color: Colors.white,
                          child: Column(children: [
                            _CreateAddOnRow(onTap: _showAddOnDialog),
                            ...addons.map((addon) => _AddonRow(
                                addon: addon,
                                onEdit: () =>
                                    _showEditItemDialog(addon.sourceItem),
                                onToggle: () => _toggleAvailability(
                                    addon.sourceItem.id,
                                    addon.sourceItem.isAvailable)))
                          ])),
                  ]),
      ),
    );
  }
}

typedef _MenuSubmit = Future<void> Function({
  required String name,
  String? description,
  required double price,
  double? discountedPrice,
  int? categoryId,
  int? globalCategoryId,
  int? globalSubcategoryId,
  int? masterMenuItemId,
  int? cuisineId,
  required String foodType,
  required bool isAvailable,
  required bool isPriceInclusiveGst,
  int? preparationTime,
  String? calories,
  required List<String> tags,
  required List<String> imagePaths,
  required List<String> existingImages,
  required List<Map<String, dynamic>> variants,
  required List<Map<String, dynamic>> addOns,
});

class _MenuItemFormScreen extends StatefulWidget {
  final MenuItem? item;
  final List<dynamic> categories;
  final List<dynamic> globalCategories;
  final List<dynamic> globalMenuItems;
  final List<dynamic> cuisines;
  final List<_MenuAddonView> availableAddOns;
  final bool addOnOnly;
  final _MenuSubmit onSubmit;

  const _MenuItemFormScreen({
    this.item,
    required this.categories,
    required this.globalCategories,
    required this.globalMenuItems,
    required this.cuisines,
    this.availableAddOns = const [],
    this.addOnOnly = false,
    required this.onSubmit,
  });

  @override
  State<_MenuItemFormScreen> createState() => _MenuItemFormScreenState();
}

class _MenuItemFormScreenState extends State<_MenuItemFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _priceController = TextEditingController();
  final _discountedPriceController = TextEditingController();
  final _preparationController = TextEditingController(text: '15');
  final _variantsController = TextEditingController();
  final _addOnsController = TextEditingController();
  bool _isSubmitting = false;
  bool _isAvailable = true;
  bool _priceInclusiveGst = false;
  String _foodType = 'veg';
  int? _selectedCategoryId;
  int? _selectedGlobalCategoryId;
  int? _selectedGlobalSubcategoryId;
  int? _selectedCuisineId;
  int? _selectedMasterMenuItemId;
  XFile? _selectedImage;

  @override
  void initState() {
    super.initState();
    final item = widget.item;
    if (item == null) return;
    _nameController.text = item.name;
    _descriptionController.text = item.description ?? '';
    _priceController.text = item.price.toStringAsFixed(2);
    _discountedPriceController.text =
        item.discountedPrice?.toStringAsFixed(2) ?? '';
    _preparationController.text = item.preparationTime?.toString() ?? '15';
    _variantsController.text = _formatMenuOptions(item.variants);
    _addOnsController.text = _formatMenuOptions(item.addOns);
    _isAvailable = item.isAvailable;
    _priceInclusiveGst = item.isPriceInclusiveGst;
    _foodType = item.foodType;
    _selectedCategoryId = item.categoryId;
    _selectedCuisineId = item.cuisineId;
    _selectedMasterMenuItemId = item.masterMenuItemId;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _priceController.dispose();
    _discountedPriceController.dispose();
    _preparationController.dispose();
    _variantsController.dispose();
    _addOnsController.dispose();
    super.dispose();
  }

  int? _asInt(dynamic value) => value is int
      ? value
      : value is num
          ? value.toInt()
          : int.tryParse(value?.toString() ?? '');

  List<Map<String, dynamic>> _parseOptions(String text) => text
      .split(RegExp(r'\\n|\r\n|\r|\n'))
      .map((line) => line.trim())
      .where((line) => line.isNotEmpty)
      .map((line) {
        final parts = line.split('|').map((part) => part.trim()).toList();
        return {
          'name': parts.isNotEmpty ? parts[0] : '',
          'price': parts.length > 1 ? double.tryParse(parts[1]) ?? 0.0 : 0.0,
          'is_available': parts.length > 2
              ? !['no', 'false', '0', 'off'].contains(parts[2].toLowerCase())
              : true,
          'custom_fields': <String, String>{}
        };
      })
      .where((option) => option['name'].toString().isNotEmpty)
      .toList();

  String _formatMenuOptions(List<MenuOption> options) => options
      .map((option) =>
          '${option.name} | ${option.price.toStringAsFixed(getCurrencyDecimals(context))} | ${option.isAvailable ? 'yes' : 'no'}')
      .join('\\n');

  Future<void> _pickImage() async {
    final picked = await ImagePicker().pickImage(
        source: ImageSource.gallery, imageQuality: 82, maxWidth: 1600);
    if (picked != null) setState(() => _selectedImage = picked);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isSubmitting = true);
    try {
      final isAddOnOnly = widget.addOnOnly;
      final price = double.parse(_priceController.text.trim());
      await widget.onSubmit(
        name: _nameController.text.trim(),
        description: isAddOnOnly ? null : _descriptionController.text.trim(),
        price: price,
        discountedPrice: isAddOnOnly
            ? null
            : (double.tryParse(_discountedPriceController.text.trim())),
        categoryId: isAddOnOnly ? null : _selectedCategoryId,
        globalCategoryId: isAddOnOnly ? null : _selectedGlobalCategoryId,
        globalSubcategoryId: isAddOnOnly ? null : _selectedGlobalSubcategoryId,
        masterMenuItemId: null,
        cuisineId: isAddOnOnly ? null : _selectedCuisineId,
        foodType: _foodType,
        isAvailable: _isAvailable,
        isPriceInclusiveGst: _priceInclusiveGst,
        preparationTime: isAddOnOnly
            ? null
            : int.tryParse(_preparationController.text.trim()),
        calories: null,
        tags: const [],
        imagePaths: _selectedImage == null ? const [] : [_selectedImage!.path],
        existingImages: widget.item?.images ?? const [],
        variants:
            isAddOnOnly ? const [] : _parseOptions(_variantsController.text),
        addOns: isAddOnOnly ? const [] : _parseOptions(_addOnsController.text),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(error.toString().replaceFirst('Exception: ', ''))));
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _showDescriptionSheet() async {
    var draft = _descriptionController.text;
    final value = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => SafeArea(
        top: false,
        child: Container(
          padding: EdgeInsets.fromLTRB(
            16,
            18,
            16,
            MediaQuery.viewInsetsOf(context).bottom + 16,
          ),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Add description',
                      style: TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              TextFormField(
                initialValue: draft,
                maxLength: 500,
                minLines: 4,
                maxLines: 6,
                autofocus: true,
                style:
                    const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                onChanged: (value) => draft = value,
                decoration: _menuInputDecoration(context, 'Describe this item'),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: () => Navigator.pop(context, draft.trim()),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: FoodFlowTheme.orange,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: const Text('Done'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (value != null && mounted) {
      setState(() => _descriptionController.text = value);
    }
  }

  Future<void> _showVariantScreen() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _VariantEditorScreen(controller: _variantsController),
      ),
    );
    setState(() {});
  }

  Future<void> _showCopyAddOnsSheet() async {
    final currentOptions = _parseOptions(_addOnsController.text);
    final selected = currentOptions
        .map((option) => option['name']?.toString().trim().toLowerCase() ?? '')
        .where((name) => name.isNotEmpty)
        .toSet();
    final available = widget.availableAddOns;
    final result = await showModalBottomSheet<Set<String>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          top: false,
          child: Container(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 14),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Copy Add-ons from other items',
                        style: TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                if (available.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Text(
                      'No add-ons are available in this restaurant menu.',
                    ),
                  )
                else
                  ...available.map((addOn) {
                    final key = addOn.name.trim().toLowerCase();
                    return CheckboxListTile(
                      value: selected.contains(key),
                      onChanged: (value) => setSheetState(() {
                        if (value == true) {
                          selected.add(key);
                        } else {
                          selected.remove(key);
                        }
                      }),
                      title: Text(addOn.name),
                      subtitle: Text(formatCurrency(
                        context,
                        addOn.price,
                      )),
                    );
                  }),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: () => Navigator.pop(context, selected),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: FoodFlowTheme.orange,
                      foregroundColor: Colors.white,
                      elevation: 0,
                    ),
                    child: const Text('Confirm'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (result == null || !mounted) return;

    final availableKeys =
        available.map((addOn) => addOn.name.trim().toLowerCase()).toSet();
    final merged = <String, Map<String, dynamic>>{};
    for (final option in currentOptions) {
      final key = option['name']?.toString().trim().toLowerCase() ?? '';
      if (key.isEmpty || availableKeys.contains(key)) continue;
      merged[key] = option;
    }
    for (final addOn in available) {
      final key = addOn.name.trim().toLowerCase();
      if (!result.contains(key)) continue;
      merged[key] = {
        'name': addOn.name,
        'price': addOn.price,
        'is_available': addOn.isAvailable,
        'custom_fields': <String, String>{},
      };
    }

    setState(() {
      _addOnsController.text = merged.values
          .map(
            (option) => [
              option['name'],
              option['price'],
              option['is_available'] == false ? 'no' : 'yes',
            ].join(' | '),
          )
          .join('\n');
    });
  }

  @override
  Widget build(BuildContext context) {
    final isEditing = widget.item != null;
    final isAddOnOnly = widget.addOnOnly;
    final title = isAddOnOnly
        ? 'Create an add-on'
        : (isEditing ? 'Edit item' : 'Add an Item');
    final descriptionText = _descriptionController.text.trim();

    return Scaffold(
      backgroundColor: const Color(0xFFF0F0F4),
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.white,
        elevation: 1,
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              title,
              style: const TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 16,
                fontWeight: FontWeight.w900,
              ),
            ),
            if (!isAddOnOnly)
              const Text(
                'In Combo',
                style: TextStyle(
                  color: FoodFlowTheme.inkSoft,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(16, 8, 16, 16),
        child: Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: FoodFlowTheme.line),
          ),
          child: SizedBox(
            height: 52,
            child: ElevatedButton(
              onPressed: _isSubmitting ? null : _submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: FoodFlowTheme.orange,
                disabledBackgroundColor: const Color(0xFFE1E1E7),
                foregroundColor: Colors.white,
                disabledForegroundColor: FoodFlowTheme.faint,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
                textStyle: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                ),
              ),
              child: _isSubmitting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(
                      isEditing ? 'Save changes' : 'Save & Submit for review'),
            ),
          ),
        ),
      ),
      body: SafeArea(
        top: false,
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(0, 14, 0, 28),
            children: [
              _FormSection(
                title: 'Basic Details',
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _nameController,
                          style: const TextStyle(fontWeight: FontWeight.w900),
                          decoration: InputDecoration(
                            hintText: isAddOnOnly
                                ? 'Type add-on name*'
                                : 'Type Item name*',
                            hintStyle: const TextStyle(
                              color: FoodFlowTheme.faint,
                              fontWeight: FontWeight.w800,
                            ),
                            border: InputBorder.none,
                          ),
                          validator: (value) =>
                              value?.trim().isEmpty == true ? 'Required' : null,
                        ),
                      ),
                      if (!isAddOnOnly) ...[
                        const SizedBox(width: 12),
                        _DashedPhotoButton(
                          selectedImage: _selectedImage,
                          existingImageUrl: widget.item?.imageUrl ?? '',
                          onTap: _pickImage,
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 22),
                  const Text(
                    'Item type*',
                    style: TextStyle(
                      color: FoodFlowTheme.inkSoft,
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    children: [
                      _FoodTypeChip(
                        label: 'Veg',
                        value: 'veg',
                        selectedValue: _foodType,
                        color: FoodFlowTheme.success,
                        onTap: () => setState(() => _foodType = 'veg'),
                      ),
                      _FoodTypeChip(
                        label: 'Non-veg',
                        value: 'non_veg',
                        selectedValue: _foodType,
                        color: FoodFlowTheme.danger,
                        onTap: () => setState(() => _foodType = 'non_veg'),
                      ),
                      if (!isAddOnOnly)
                        _FoodTypeChip(
                          label: 'Egg',
                          value: 'egg',
                          selectedValue: _foodType,
                          color: const Color(0xFFF59E0B),
                          onTap: () => setState(() => _foodType = 'egg'),
                        ),
                    ],
                  ),
                  if (!isAddOnOnly) ...[
                    const SizedBox(height: 18),
                    const Divider(height: 1),
                    InkWell(
                      onTap: _showDescriptionSheet,
                      borderRadius: BorderRadius.circular(10),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(
                              descriptionText.isEmpty
                                  ? Icons.add
                                  : Icons.edit_outlined,
                              size: 18,
                              color: FoodFlowTheme.orange,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                descriptionText.isEmpty
                                    ? 'Add a description'
                                    : descriptionText,
                                maxLines: descriptionText.isEmpty ? 1 : 3,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: descriptionText.isEmpty
                                      ? FoodFlowTheme.orange
                                      : FoodFlowTheme.ink,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              _FormSection(
                title: 'Item Pricing',
                trailing: const Text(
                  'Your GST Info  ?',
                  style: TextStyle(
                    color: FoodFlowTheme.muted,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                children: [
                  TextFormField(
                    controller: _priceController,
                    keyboardType:
                        const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(
                      hintText: 'Item Price*',
                      prefixText: currencyInputPrefix(context),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    validator: (value) =>
                        double.tryParse(value?.trim() ?? '') == null
                            ? 'Required'
                            : null,
                  ),
                  if (!isAddOnOnly) ...[
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _discountedPriceController,
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(
                        hintText: 'Offer price',
                        prefixText: currencyInputPrefix(context),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  CheckboxListTile(
                    value: _priceInclusiveGst,
                    onChanged: (value) => setState(
                      () => _priceInclusiveGst = value ?? false,
                    ),
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    title: const Text(
                      'This price is inclusive of GST',
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: const Text(
                        'Mark if this is a packed item e.g Coldrink'),
                  ),
                  const Divider(height: 20, color: FoodFlowTheme.line),
                  const Text(
                    'Final item price',
                    style: TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const Text(
                    'Item price + GST',
                    style: TextStyle(color: FoodFlowTheme.faint),
                  ),
                ],
              ),
              if (!isAddOnOnly)
                _FormSection(
                  title: 'Customisations',
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Include variants',
                                style: TextStyle(fontWeight: FontWeight.w900),
                              ),
                              SizedBox(height: 3),
                              Text(
                                'Customers can choose exactly one of the defined variations',
                                style: TextStyle(color: FoodFlowTheme.muted),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _MenuOptionEditor(
                      controller: _variantsController,
                      label: 'Variants',
                      addButtonLabel: 'Add Variant',
                      placeholder: 'Option name',
                      helpText:
                          'Add only the options this item really supports.',
                      emptyText: 'No variants added.',
                    ),
                    const SizedBox(height: 10),
                    TextButton(
                      onPressed: _showVariantScreen,
                      child: const Text('+ Create my own variant'),
                    ),
                    const Divider(height: 26),
                    Row(
                      children: [
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Include add-ons',
                                style: TextStyle(fontWeight: FontWeight.w900),
                              ),
                              SizedBox(height: 3),
                              Text(
                                'Additional items that customers can buy with this dish',
                                style: TextStyle(color: FoodFlowTheme.muted),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    TextButton(
                      onPressed: _showCopyAddOnsSheet,
                      child: const Text('COPY ADD ONS FROM OTHER ITEMS'),
                    ),
                    _MenuOptionEditor(
                      controller: _addOnsController,
                      label: 'Add-ons / Extras',
                      addButtonLabel: 'Add Extra',
                      placeholder: 'Extra cheese',
                      helpText:
                          'Customers can select multiple available extras.',
                      emptyText: 'No extras added.',
                    ),
                  ],
                ),
              if (!isAddOnOnly)
                _FormSection(
                  title: 'Item Timings',
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.schedule,
                            size: 18, color: FoodFlowTheme.muted),
                        const SizedBox(width: 8),
                        Expanded(
                          child: TextFormField(
                            controller: _preparationController,
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              hintText:
                                  'Item is available at all times when restaurant is open',
                              border: InputBorder.none,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MenuAddonView {
  final String name;
  final double price;
  final bool isAvailable;
  final MenuItem sourceItem;
  const _MenuAddonView(
      {required this.name,
      required this.price,
      required this.isAvailable,
      required this.sourceItem});
}

class _PreviewOptionLine extends StatelessWidget {
  final String title;
  final List<MenuOption> options;

  const _PreviewOptionLine({required this.title, required this.options});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: FoodFlowTheme.ink,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            options
                .map((option) => option.price > 0
                    ? '${option.name} (${formatCurrency(context, option.price)})'
                    : option.name)
                .join(', '),
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _MenuModeScreen extends StatelessWidget {
  final String restaurantName;
  final VoidCallback onBack;
  final VoidCallback onCustomMenu;
  final VoidCallback onGlobalMenu;

  const _MenuModeScreen({
    required this.restaurantName,
    required this.onBack,
    required this.onCustomMenu,
    required this.onGlobalMenu,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.white,
        elevation: 1,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: onBack,
        ),
        title: const Text(
          'Your Menu',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(22, 32, 22, 32),
        children: [
          Text(
            restaurantName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Select menu source',
            style: TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 25,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 24),
          _MenuSourceCard(
            icon: Icons.restaurant_menu_outlined,
            title: 'Custom menu',
            subtitle: 'Create and manage this outlet menu manually.',
            onTap: onCustomMenu,
          ),
          const SizedBox(height: 14),
          _MenuSourceCard(
            icon: Icons.public_outlined,
            title: 'Global menu',
            subtitle: 'Select approved global items and add outlet pricing.',
            onTap: onGlobalMenu,
          ),
        ],
      ),
    );
  }
}

class _MenuSourceCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _MenuSourceCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: FoodFlowTheme.line),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.045),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: FoodFlowTheme.orange.withOpacity(0.1),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: FoodFlowTheme.orange),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: FoodFlowTheme.inkSoft),
          ],
        ),
      ),
    );
  }
}

class _GlobalMenuCatalogScreen extends StatefulWidget {
  final List<dynamic> items;
  final VoidCallback onBack;
  final ValueChanged<dynamic> onSelect;

  const _GlobalMenuCatalogScreen({
    required this.items,
    required this.onBack,
    required this.onSelect,
  });

  @override
  State<_GlobalMenuCatalogScreen> createState() =>
      _GlobalMenuCatalogScreenState();
}

class _GlobalMenuCatalogScreenState extends State<_GlobalMenuCatalogScreen> {
  String _query = '';

  String _text(Map<String, dynamic> item, List<String> keys) {
    for (final key in keys) {
      final value = item[key];
      if (value != null && value.toString().trim().isNotEmpty) {
        return value.toString().trim();
      }
    }
    return '';
  }

  String? _image(Map<String, dynamic> item) {
    final direct = _text(item, ['image_url', 'image', 'thumbnail', 'photo']);
    if (direct.isNotEmpty) return direct;
    final images = item['images'];
    if (images is List && images.isNotEmpty) return images.first?.toString();
    return null;
  }

  double? _price(Map<String, dynamic> item) {
    for (final key in ['price', 'base_price', 'selling_price', 'mrp']) {
      final parsed = double.tryParse(item[key]?.toString() ?? '');
      if (parsed != null) return parsed;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final rows = widget.items
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .where((item) {
      final q = _query.trim().toLowerCase();
      if (q.isEmpty) return true;
      return _text(item, ['name', 'title']).toLowerCase().contains(q) ||
          _text(item, ['category_name', 'subcategory_name', 'cuisine_name'])
              .toLowerCase()
              .contains(q);
    }).toList();

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.white,
        elevation: 1,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: widget.onBack,
        ),
        title: const Text(
          'Global Menu',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 32),
        children: [
          TextField(
            onChanged: (value) => setState(() => _query = value),
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
            decoration: _menuInputDecoration(context, 'Search global items',
                suffixIcon: const Icon(Icons.search)),
          ),
          const SizedBox(height: 18),
          if (rows.isEmpty)
            FoodFlowTheme.emptyState(
              icon: Icons.public_off_outlined,
              title: 'No global menu items',
              subtitle: 'Approved global menu items will appear here.',
            )
          else
            ...rows.map((item) {
              final name = _text(item, ['name', 'title']);
              final subtitle = [
                _text(item, ['category_name']),
                _text(item, ['subcategory_name']),
                _text(item, ['cuisine_name']),
              ].where((value) => value.isNotEmpty).join(_menuMetadataSeparator);
              final image = _image(item);
              final price = _price(item);
              return InkWell(
                onTap: () => widget.onSelect(item),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  decoration: const BoxDecoration(
                    border:
                        Border(bottom: BorderSide(color: FoodFlowTheme.line)),
                  ),
                  child: Row(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: image == null
                            ? Container(
                                width: 54,
                                height: 54,
                                color: const Color(0xFFF3F3F6),
                                child: const Icon(Icons.restaurant_menu,
                                    color: FoodFlowTheme.muted),
                              )
                            : NetworkImageLoader(
                                imageUrl: image,
                                width: 54,
                                height: 54,
                                borderRadius: BorderRadius.circular(12),
                              ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              name.isEmpty ? 'Menu item' : name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: FoodFlowTheme.ink,
                                fontSize: 16,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            if (subtitle.isNotEmpty) ...[
                              const SizedBox(height: 3),
                              Text(
                                subtitle,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: FoodFlowTheme.muted,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      if (price != null)
                        Text(
                          formatCurrency(context, price),
                          style: const TextStyle(
                            color: FoodFlowTheme.success,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      const SizedBox(width: 8),
                      const Icon(Icons.chevron_right,
                          color: FoodFlowTheme.inkSoft),
                    ],
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }
}

class _GlobalItemImportSheet extends StatefulWidget {
  final Map<String, dynamic> item;

  const _GlobalItemImportSheet({required this.item});

  @override
  State<_GlobalItemImportSheet> createState() => _GlobalItemImportSheetState();
}

class _GlobalItemImportSheetState extends State<_GlobalItemImportSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _priceController;
  late final TextEditingController _offerController;
  late final TextEditingController _prepController;

  @override
  void initState() {
    super.initState();
    _priceController = TextEditingController(text: _initialNumber('price'));
    _offerController =
        TextEditingController(text: _initialNumber('discounted_price'));
    _prepController =
        TextEditingController(text: _initialInt('preparation_time'));
  }

  @override
  void dispose() {
    _priceController.dispose();
    _offerController.dispose();
    _prepController.dispose();
    super.dispose();
  }

  String _name() =>
      (widget.item['name'] ?? widget.item['title'] ?? 'Global menu item')
          .toString();

  String _initialNumber(String key) {
    final value = double.tryParse(widget.item[key]?.toString() ?? '');
    if (value == null || value <= 0) return '';
    return value.toStringAsFixed(value.truncateToDouble() == value ? 0 : 2);
  }

  String _initialInt(String key) {
    final value = int.tryParse(widget.item[key]?.toString() ?? '');
    return value == null || value <= 0 ? '' : value.toString();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        padding: EdgeInsets.fromLTRB(
          18,
          18,
          18,
          MediaQuery.viewInsetsOf(context).bottom + 18,
        ),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      _name(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _priceController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                style:
                    const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                decoration: _menuInputDecoration(
                  context,
                  'Item price',
                  prefixText: currencyInputPrefix(context),
                ),
                validator: (value) {
                  final price = double.tryParse(value?.trim() ?? '');
                  if (price == null || price <= 0) return 'Enter item price';
                  return null;
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _offerController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                style:
                    const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                decoration: _menuInputDecoration(
                  context,
                  'Offer price',
                  prefixText: currencyInputPrefix(context),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _prepController,
                keyboardType: TextInputType.number,
                style:
                    const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                decoration: _menuInputDecoration(context, 'Preparation time'),
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: () {
                    if (!_formKey.currentState!.validate()) return;
                    Navigator.pop(context, {
                      'price': double.parse(_priceController.text.trim()),
                      'discounted_price':
                          double.tryParse(_offerController.text.trim()),
                      'preparation_time':
                          int.tryParse(_prepController.text.trim()),
                    });
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: FoodFlowTheme.orange,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Add To My Menu',
                    style: TextStyle(fontWeight: FontWeight.w900),
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

class _OutletPickerScreen extends StatelessWidget {
  final List<Map<String, dynamic>> restaurants;
  final int? selectedRestaurantId;
  final bool isLoading;
  final ValueChanged<int?> onSelect;
  const _OutletPickerScreen(
      {required this.restaurants,
      required this.selectedRestaurantId,
      required this.isLoading,
      required this.onSelect});
  int? _id(Map<String, dynamic> item) => item['id'] is int
      ? item['id'] as int
      : int.tryParse(item['id']?.toString() ?? '');
  bool _online(Map<String, dynamic> item) {
    final value = item['is_open'] ??
        item['is_online'] ??
        item['online'] ??
        item['status'];
    if (value is bool) return value;
    final text = value?.toString().toLowerCase() ?? '';
    return text == 'open' || text == 'online' || text == '1' || text == 'true';
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(
            backgroundColor: Colors.white,
            title: const Text('Your Menu',
                style: TextStyle(fontWeight: FontWeight.w900))),
        body: isLoading && restaurants.isEmpty
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.fromLTRB(24, 56, 24, 24),
                children: [
                    const Text('Select an outlet',
                        style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 26,
                            fontWeight: FontWeight.w900)),
                    const SizedBox(height: 34),
                    if (restaurants.isEmpty)
                      FoodFlowTheme.emptyState(
                          icon: Icons.storefront_outlined,
                          title: 'No outlets found',
                          subtitle:
                              'Outlets from your account will appear here.')
                    else
                      ...restaurants.map((restaurant) {
                        final id = _id(restaurant);
                        final online = _online(restaurant);
                        final subtitle = [
                          online ? 'Online' : 'Offline',
                          restaurant['area'],
                          restaurant['city']
                        ]
                            .map((part) => part?.toString().trim() ?? '')
                            .where((part) => part.isNotEmpty)
                            .join(' - ');
                        return InkWell(
                            onTap: () => onSelect(id),
                            child: Container(
                                padding:
                                    const EdgeInsets.symmetric(vertical: 18),
                                decoration: const BoxDecoration(
                                    border: Border(
                                        bottom: BorderSide(
                                            color: FoodFlowTheme.line))),
                                child: Row(children: [
                                  Icon(Icons.navigation,
                                      color: FoodFlowTheme.orange, size: 30),
                                  const SizedBox(width: 18),
                                  Expanded(
                                      child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                        Text(
                                            restaurant['name']?.toString() ??
                                                'Outlet',
                                            style: const TextStyle(
                                                fontSize: 18,
                                                fontWeight: FontWeight.w900)),
                                        const SizedBox(height: 4),
                                        Text(subtitle,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: TextStyle(
                                                color: online
                                                    ? FoodFlowTheme.success
                                                    : FoodFlowTheme.danger,
                                                fontWeight: FontWeight.w700))
                                      ])),
                                  if (id == selectedRestaurantId)
                                    const Icon(Icons.check_circle,
                                        color: FoodFlowTheme.success)
                                ])));
                      }),
                  ]),
      );
}

class _MenuPill extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _MenuPill(
      {required this.label, required this.selected, required this.onTap});
  @override
  Widget build(BuildContext context) => InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
          decoration: BoxDecoration(
              color: selected ? FoodFlowTheme.ink : Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: FoodFlowTheme.line)),
          child: Text(label,
              style: TextStyle(
                  color: selected ? Colors.white : FoodFlowTheme.inkSoft,
                  fontWeight: FontWeight.w900))));
}

class _MenuTip extends StatelessWidget {
  final VoidCallback onClose;
  const _MenuTip({required this.onClose});
  @override
  Widget build(BuildContext context) => Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
          color: const Color(0xFFF0EAFE),
          borderRadius: BorderRadius.circular(14)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Expanded(
            child: Text(
                'Use the add item button to create real menu entries for this outlet.',
                style: TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontWeight: FontWeight.w700))),
        IconButton(
            onPressed: onClose,
            icon: const Icon(Icons.close),
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints.tightFor(width: 28, height: 28))
      ]));
}

class _MenuStrip extends StatelessWidget {
  final Color color;
  final IconData icon;
  final String label;
  const _MenuStrip(
      {required this.color, required this.icon, required this.label});
  @override
  Widget build(BuildContext context) => Container(
      color: color,
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
      child: Row(children: [
        Icon(icon, size: 16, color: FoodFlowTheme.muted),
        const SizedBox(width: 10),
        Expanded(
            child: Text(label,
                style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    letterSpacing: 1.2,
                    fontWeight: FontWeight.w900))),
        const Icon(Icons.keyboard_arrow_down, color: FoodFlowTheme.muted)
      ]));
}

class _CreateAddOnRow extends StatelessWidget {
  final VoidCallback onTap;
  const _CreateAddOnRow({required this.onTap});
  @override
  Widget build(BuildContext context) => InkWell(
      onTap: onTap,
      child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
          child: Row(children: [
            Container(
                width: 54,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                    border: Border.all(color: FoodFlowTheme.line),
                    borderRadius: BorderRadius.circular(4)),
                child: Icon(Icons.add, color: FoodFlowTheme.success)),
            const SizedBox(width: 14),
            const Text('Create an add-on',
                style: TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 15,
                    fontWeight: FontWeight.w900))
          ])));
}

class _MenuStatusBlock extends StatefulWidget {
  final Color color;
  final IconData icon;
  final String label;
  final List<MenuItem> items;
  final ValueChanged<MenuItem> onPreview;
  final ValueChanged<MenuItem> onEdit;
  final ValueChanged<MenuItem> onDelete;
  final ValueChanged<MenuItem> onToggle;

  const _MenuStatusBlock({
    required this.color,
    required this.icon,
    required this.label,
    required this.items,
    required this.onPreview,
    required this.onEdit,
    required this.onDelete,
    required this.onToggle,
  });

  @override
  State<_MenuStatusBlock> createState() => _MenuStatusBlockState();
}

class _MenuStatusBlockState extends State<_MenuStatusBlock> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: widget.color,
      child: Column(
        children: [
          InkWell(
            onTap: () => setState(() => _expanded = !_expanded),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 12, 14, 12),
              child: Row(
                children: [
                  Icon(widget.icon, color: FoodFlowTheme.danger, size: 16),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      widget.label,
                      style: const TextStyle(
                        color: FoodFlowTheme.inkSoft,
                        fontSize: 12,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1.6,
                      ),
                    ),
                  ),
                  Icon(
                    _expanded
                        ? Icons.keyboard_arrow_up
                        : Icons.keyboard_arrow_down,
                    color: FoodFlowTheme.muted,
                  ),
                ],
              ),
            ),
          ),
          if (_expanded)
            ...widget.items.map(
              (item) => _CompactMenuItemRow(
                item: item,
                onPreview: () => widget.onPreview(item),
                onEdit: () => widget.onEdit(item),
                onDelete: () => widget.onDelete(item),
                onToggle: () => widget.onToggle(item),
              ),
            ),
        ],
      ),
    );
  }
}

class _MenuCategoryBlock extends StatelessWidget {
  final String title;
  final bool expanded;
  final List<MenuItem> items;
  final VoidCallback onToggleExpanded;
  final VoidCallback onAddItem;
  final ValueChanged<MenuItem> onPreview;
  final ValueChanged<MenuItem> onEdit;
  final ValueChanged<MenuItem> onDelete;
  final ValueChanged<MenuItem> onToggle;
  const _MenuCategoryBlock(
      {required this.title,
      required this.expanded,
      required this.items,
      required this.onToggleExpanded,
      required this.onAddItem,
      required this.onPreview,
      required this.onEdit,
      required this.onDelete,
      required this.onToggle});
  @override
  Widget build(BuildContext context) => Container(
      margin: const EdgeInsets.only(top: 8),
      color: Colors.white,
      child: Column(children: [
        ListTile(
            onTap: onToggleExpanded,
            title: Text(title,
                style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 18,
                    fontWeight: FontWeight.w900)),
            trailing: Row(mainAxisSize: MainAxisSize.min, children: [
              IconButton(
                  onPressed: onAddItem,
                  icon: const Icon(Icons.add_circle_outline)),
              IconButton(
                  onPressed: onToggleExpanded,
                  icon: Icon(expanded
                      ? Icons.keyboard_arrow_up
                      : Icons.keyboard_arrow_down))
            ])),
        if (expanded) ...[
          _CreateItemRow(onTap: onAddItem),
          ...items.map((item) => _CompactMenuItemRow(
              item: item,
              onPreview: () => onPreview(item),
              onEdit: () => onEdit(item),
              onDelete: () => onDelete(item),
              onToggle: () => onToggle(item)))
        ]
      ]));
}

class _CreateItemRow extends StatelessWidget {
  final VoidCallback onTap;
  const _CreateItemRow({required this.onTap});
  @override
  Widget build(BuildContext context) => InkWell(
      onTap: onTap,
      child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
          child: Row(children: [
            Container(
                width: 54,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                    border: Border.all(color: FoodFlowTheme.line),
                    borderRadius: BorderRadius.circular(4)),
                child: Icon(Icons.add, color: FoodFlowTheme.success)),
            const SizedBox(width: 14),
            const Text('Add an item',
                style: TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 15,
                    fontWeight: FontWeight.w900))
          ])));
}

class _CompactMenuItemRow extends StatelessWidget {
  final MenuItem item;
  final VoidCallback onPreview;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final VoidCallback onToggle;
  const _CompactMenuItemRow(
      {required this.item,
      required this.onPreview,
      required this.onEdit,
      required this.onDelete,
      required this.onToggle});
  @override
  Widget build(BuildContext context) => Container(
      padding: const EdgeInsets.fromLTRB(18, 14, 18, 14),
      decoration: const BoxDecoration(
          border: Border(top: BorderSide(color: Color(0xFFF0F0F0)))),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: item.imageUrl.isEmpty
                ? Container(
                    width: 56,
                    height: 56,
                    color: const Color(0xFFEDEDEE),
                    child: const Icon(Icons.fastfood_outlined,
                        color: FoodFlowTheme.muted))
                : NetworkImageLoader(
                    imageUrl: item.imageUrl,
                    width: 56,
                    height: 56,
                    fit: BoxFit.cover)),
        const SizedBox(width: 12),
        Expanded(
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          FoodFlowTheme.vegDot(item.foodType != 'non_veg', size: 14),
          const SizedBox(height: 5),
          Text('${item.name}, ${formatCurrency(context, item.finalPrice)}',
              style: const TextStyle(
                  color: FoodFlowTheme.inkSoft, fontWeight: FontWeight.w900)),
          if (item.unavailableUntil != null) ...[
            const SizedBox(height: 4),
            Text(
              _availabilityLabel(context),
              style: const TextStyle(
                color: FoodFlowTheme.danger,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          const SizedBox(height: 8),
          Wrap(spacing: 18, children: [
            InkWell(onTap: onPreview, child: const Text('Preview')),
            InkWell(onTap: onEdit, child: const Text('Edit'))
          ])
        ])),
        Switch(
            value: item.isAvailable,
            onChanged: (_) => onToggle(),
            activeColor: FoodFlowTheme.success),
        PopupMenuButton<String>(
            onSelected: (value) {
              if (value == 'edit') onEdit();
              if (value == 'delete') onDelete();
            },
            itemBuilder: (_) => const [
                  PopupMenuItem(value: 'edit', child: Text('Edit item')),
                  PopupMenuItem(value: 'delete', child: Text('Delete item'))
                ])
      ]));

  String _availabilityLabel(BuildContext context) {
    final local = item.unavailableUntil!.toLocal();
    final localizations = MaterialLocalizations.of(context);
    return 'Available again ${localizations.formatMediumDate(local)}, '
        '${localizations.formatTimeOfDay(TimeOfDay.fromDateTime(local))}';
  }
}

class _AddonRow extends StatelessWidget {
  final _MenuAddonView addon;
  final VoidCallback onEdit;
  final VoidCallback onToggle;
  const _AddonRow(
      {required this.addon, required this.onEdit, required this.onToggle});
  @override
  Widget build(BuildContext context) => Container(
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
      decoration: const BoxDecoration(
          border: Border(top: BorderSide(color: Color(0xFFF0F0F0)))),
      child: Row(children: [
        FoodFlowTheme.vegDot(addon.sourceItem.foodType != 'non_veg', size: 14),
        const SizedBox(width: 12),
        Expanded(
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${addon.name}, ${formatCurrency(context, addon.price)}',
              style: const TextStyle(
                  color: FoodFlowTheme.inkSoft, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          InkWell(onTap: onEdit, child: const Text('Edit'))
        ])),
        Switch(
            value: addon.isAvailable,
            onChanged: (_) => onToggle(),
            activeColor: FoodFlowTheme.success)
      ]));
}

class _FormSection extends StatelessWidget {
  final String title;
  final Widget? trailing;
  final List<Widget> children;

  const _FormSection({
    required this.title,
    this.trailing,
    required this.children,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(0, 0, 0, 16),
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 19,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              if (trailing != null) trailing!,
            ],
          ),
          const Divider(height: 24),
          ...children,
        ],
      ),
    );
  }
}

class _FoodTypeChip extends StatelessWidget {
  final String label;
  final String value;
  final String selectedValue;
  final Color color;
  final VoidCallback onTap;

  const _FoodTypeChip({
    required this.label,
    required this.value,
    required this.selectedValue,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final selected = value == selectedValue;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 140),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? color.withOpacity(0.1) : Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: selected ? color : FoodFlowTheme.line),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            FoodFlowTheme.vegDot(value != 'non_veg', size: 12),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                color: selected ? color : FoodFlowTheme.inkSoft,
                fontSize: 13,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DashedPhotoButton extends StatelessWidget {
  final XFile? selectedImage;
  final String existingImageUrl;
  final VoidCallback onTap;

  const _DashedPhotoButton({
    required this.selectedImage,
    required this.existingImageUrl,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final hasLocalImage = selectedImage != null;
    final hasExistingImage = existingImageUrl.trim().isNotEmpty;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(4),
      child: Container(
        width: 76,
        height: 82,
        decoration: BoxDecoration(
          color: const Color(0xFFF5F5F8),
          borderRadius: BorderRadius.circular(4),
          border:
              Border.all(color: FoodFlowTheme.line, style: BorderStyle.solid),
        ),
        clipBehavior: Clip.antiAlias,
        child: hasLocalImage || hasExistingImage
            ? Stack(
                fit: StackFit.expand,
                children: [
                  if (hasLocalImage)
                    Image.file(
                      File(selectedImage!.path),
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) =>
                          const _MenuPhotoPlaceholder(),
                    )
                  else
                    NetworkImageLoader(
                      imageUrl: existingImageUrl,
                      fit: BoxFit.cover,
                    ),
                  Positioned(
                    right: 4,
                    bottom: 4,
                    child: Container(
                      width: 24,
                      height: 24,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(4),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x33000000),
                            blurRadius: 5,
                          ),
                        ],
                      ),
                      child: Icon(
                        Icons.edit_outlined,
                        size: 15,
                        color: FoodFlowTheme.orange,
                      ),
                    ),
                  ),
                ],
              )
            : const _MenuPhotoPlaceholder(),
      ),
    );
  }
}

class _MenuPhotoPlaceholder extends StatelessWidget {
  const _MenuPhotoPlaceholder();

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Text(
          'ADD\\nPHOTO',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: FoodFlowTheme.inkSoft,
            fontSize: 11,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 8),
        Icon(Icons.add, color: FoodFlowTheme.orange),
      ],
    );
  }
}

class _VariantEditorScreen extends StatefulWidget {
  final TextEditingController controller;

  const _VariantEditorScreen({required this.controller});

  @override
  State<_VariantEditorScreen> createState() => _VariantEditorScreenState();
}

class _VariantEditorScreenState extends State<_VariantEditorScreen> {
  late final TextEditingController _workingController;

  @override
  void initState() {
    super.initState();
    _workingController = TextEditingController(text: widget.controller.text);
  }

  @override
  void dispose() {
    _workingController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Add a Variant',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: const [],
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(16, 8, 16, 16),
        child: SizedBox(
          height: 52,
          child: ElevatedButton(
            onPressed: () {
              widget.controller.text = _workingController.text;
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: FoodFlowTheme.orange,
              foregroundColor: Colors.white,
              elevation: 0,
            ),
            child: const Text('Confirm'),
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 24),
        children: [
          const Text(
            'Base',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          const Text(
            'Please ensure that the options are in low to high price order',
            style: TextStyle(color: FoodFlowTheme.muted),
          ),
          const SizedBox(height: 22),
          _MenuOptionEditor(
            controller: _workingController,
            label: 'Option name',
            addButtonLabel: 'Add another option',
            placeholder: 'Wheat',
            helpText: 'Add option name and additional price.',
            emptyText: 'No variants added.',
          ),
        ],
      ),
    );
  }
}

class _MenuOptionEditor extends StatefulWidget {
  final TextEditingController controller;
  final String label;
  final String addButtonLabel;
  final String placeholder;
  final String helpText;
  final String emptyText;

  const _MenuOptionEditor({
    super.key,
    required this.controller,
    required this.label,
    required this.addButtonLabel,
    required this.placeholder,
    required this.helpText,
    required this.emptyText,
  });

  @override
  State<_MenuOptionEditor> createState() => _MenuOptionEditorState();
}

class _EditableMenuOption {
  String name;
  String price;
  bool isAvailable;
  String customFieldsText;
  _EditableMenuOption(
      {this.name = '',
      this.price = '',
      this.isAvailable = true,
      this.customFieldsText = ''});
  String serialize() => [
        name,
        price.trim().isEmpty ? '0' : price.trim(),
        isAvailable ? 'yes' : 'no',
        if (customFieldsText.trim().isNotEmpty) customFieldsText.trim()
      ].join(' | ');
  static List<_EditableMenuOption> parse(String text) => text
          .split(RegExp(r'\\n|\r\n|\r|\n'))
          .map((line) => line.trim())
          .where((line) => line.isNotEmpty)
          .map((line) {
        final parts = line.split('|').map((part) => part.trim()).toList();
        return _EditableMenuOption(
            name: parts.isNotEmpty ? parts[0] : '',
            price: parts.length > 1 ? parts[1] : '',
            isAvailable: parts.length > 2
                ? !['no', 'false', '0', 'off'].contains(parts[2].toLowerCase())
                : true,
            customFieldsText:
                parts.length > 3 ? parts.sublist(3).join(' | ') : '');
      }).toList();
}

class _MenuOptionEditorState extends State<_MenuOptionEditor> {
  late List<_EditableMenuOption> _options;

  @override
  void initState() {
    super.initState();
    _options = _EditableMenuOption.parse(widget.controller.text);
  }

  void _addOption() {
    setState(() {
      _options.add(_EditableMenuOption());
      _syncController();
    });
  }

  void _removeOption(int index) {
    setState(() {
      _options.removeAt(index);
      _syncController();
    });
  }

  void _syncController() {
    widget.controller.text = _options
        .where((option) => option.name.trim().isNotEmpty)
        .map((option) => option.serialize())
        .join('\\n');
  }

  @override
  Widget build(BuildContext context) {
    final optionWidgets = _options.isEmpty
        ? <Widget>[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Text(
                widget.emptyText,
                style: const TextStyle(
                  color: FoodFlowTheme.muted,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ]
        : List<Widget>.generate(_options.length, (index) {
            final option = _options[index];
            return Container(
              margin: EdgeInsets.only(
                  bottom: index == _options.length - 1 ? 0 : 10),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Column(
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        flex: 5,
                        child: TextFormField(
                          initialValue: option.name,
                          decoration: InputDecoration(
                            labelText: 'Name',
                            hintText: widget.placeholder,
                            border: const OutlineInputBorder(),
                          ),
                          onChanged: (value) {
                            option.name = value;
                            _syncController();
                          },
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        flex: 4,
                        child: TextFormField(
                          initialValue: option.price,
                          decoration: InputDecoration(
                            labelText: 'Extra Price',
                            prefixText: currencyInputPrefix(context),
                            border: const OutlineInputBorder(),
                          ),
                          keyboardType: const TextInputType.numberWithOptions(
                              decimal: true),
                          onChanged: (value) {
                            option.price = value;
                            _syncController();
                          },
                        ),
                      ),
                      IconButton(
                        tooltip: 'Remove option',
                        onPressed: () => _removeOption(index),
                        icon: const Icon(Icons.close,
                            color: FoodFlowTheme.danger),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  TextFormField(
                    initialValue: option.customFieldsText,
                    decoration: const InputDecoration(
                      labelText: 'Custom Fields',
                      hintText: 'Portion: 2 slices\\nUnit: 500g',
                      border: OutlineInputBorder(),
                    ),
                    minLines: 1,
                    maxLines: 2,
                    onChanged: (value) {
                      option.customFieldsText = value;
                      _syncController();
                    },
                  ),
                  SwitchListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    value: option.isAvailable,
                    activeColor: FoodFlowTheme.orange,
                    title: const Text('Available to customers'),
                    onChanged: (value) {
                      setState(() {
                        option.isAvailable = value;
                        _syncController();
                      });
                    },
                  ),
                ],
              ),
            );
          });

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFAF5),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFFE1C5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.label,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      widget.helpText,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              TextButton.icon(
                onPressed: _addOption,
                icon: const Icon(Icons.add, size: 18),
                label: Text(widget.addButtonLabel),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ...optionWidgets,
        ],
      ),
    );
  }
}

class _MenuOperatorCard extends StatelessWidget {
  final MenuItem item;
  final bool canManageMenu;
  final bool isSelected;
  final bool selectionMode;
  final VoidCallback onEdit;
  final VoidCallback onDuplicate;
  final VoidCallback onToggle;
  final VoidCallback onDelete;
  final ValueChanged<bool> onSelectionChanged;

  const _MenuOperatorCard({
    required this.item,
    required this.canManageMenu,
    required this.isSelected,
    required this.selectionMode,
    required this.onEdit,
    required this.onDuplicate,
    required this.onToggle,
    required this.onDelete,
    required this.onSelectionChanged,
  });

  @override
  Widget build(BuildContext context) {
    final statusColor =
        item.isAvailable ? FoodFlowTheme.success : FoodFlowTheme.danger;
    final metadata = [
      item.categoryName ?? 'Uncategorized',
      if (item.cuisineName != null && item.cuisineName!.trim().isNotEmpty)
        item.cuisineName!,
      if (item.preparationTime != null) '${item.preparationTime} min',
    ].join(_menuMetadataSeparator);

    return InkWell(
      onTap: canManageMenu ? onEdit : null,
      onLongPress: canManageMenu ? () => onSelectionChanged(true) : null,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: isSelected ? FoodFlowTheme.orange : FoodFlowTheme.line,
            width: isSelected ? 1.6 : 1,
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            if (selectionMode)
              Padding(
                padding: const EdgeInsets.only(right: 6),
                child: Checkbox(
                  value: isSelected,
                  activeColor: FoodFlowTheme.orange,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  onChanged: (value) => onSelectionChanged(value ?? false),
                ),
              ),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: item.imageUrl.isNotEmpty
                  ? NetworkImageLoader(
                      imageUrl: item.imageUrl,
                      width: 58,
                      height: 58,
                      borderRadius: BorderRadius.circular(8),
                    )
                  : _fallbackImage(),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          item.name,
                          style: const TextStyle(
                            color: FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                            fontSize: 15,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 8),
                      _StatusDot(color: statusColor),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(
                    metadata,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      if (item.hasDiscount) ...[
                        Text(
                          formatCurrency(context, item.price),
                          style: const TextStyle(
                            decoration: TextDecoration.lineThrough,
                            fontSize: 12,
                            color: FoodFlowTheme.faint,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(width: 6),
                      ],
                      Text(
                        formatCurrency(context, item.finalPrice),
                        style: TextStyle(
                          color: FoodFlowTheme.orange,
                          fontWeight: FontWeight.w900,
                          fontSize: 14,
                        ),
                      ),
                      const SizedBox(width: 8),
                      _TagPill(
                        label: item.dietLabel,
                        color: item.isVeg
                            ? FoodFlowTheme.success
                            : FoodFlowTheme.danger,
                      ),
                      if (item.totalOrders > 0) ...[
                        const SizedBox(width: 6),
                        Flexible(
                          child: Text(
                            '${item.totalOrders} orders',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            if (canManageMenu)
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Transform.scale(
                    scale: 0.82,
                    child: Switch(
                      value: item.isAvailable,
                      onChanged: (_) => onToggle(),
                      activeColor: FoodFlowTheme.orange,
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                  ),
                  PopupMenuButton<String>(
                    icon: const Icon(Icons.more_vert),
                    tooltip: 'Menu item actions',
                    onSelected: (value) {
                      if (value == 'edit') onEdit();
                      if (value == 'duplicate') onDuplicate();
                      if (value == 'toggle') onToggle();
                      if (value == 'delete') onDelete();
                    },
                    itemBuilder: (context) => [
                      const PopupMenuItem(
                        value: 'edit',
                        child: Text('Edit'),
                      ),
                      const PopupMenuItem(
                        value: 'duplicate',
                        child: Text('Duplicate'),
                      ),
                      PopupMenuItem(
                        value: 'toggle',
                        child: Text(item.isAvailable ? 'Hide' : 'Show'),
                      ),
                      const PopupMenuItem(
                        value: 'delete',
                        child: Text('Delete'),
                      ),
                    ],
                  ),
                ],
              )
            else
              _AvailabilityPill(isAvailable: item.isAvailable),
          ],
        ),
      ),
    );
  }

  Widget _fallbackImage() {
    return Container(
      width: 58,
      height: 58,
      color: FoodFlowTheme.orange.withOpacity(0.08),
      child: Icon(
        item.isVeg ? Icons.eco : Icons.restaurant,
        color: item.isVeg ? FoodFlowTheme.success : FoodFlowTheme.danger,
      ),
    );
  }
}

class _QuickActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  const _QuickActionButton({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.only(right: 8),
        child: OutlinedButton.icon(
          onPressed: onPressed,
          icon: Icon(icon, size: 15),
          label: Text(label),
          style: OutlinedButton.styleFrom(
            foregroundColor: FoodFlowTheme.orange,
            side: BorderSide(color: FoodFlowTheme.orange.withOpacity(0.3)),
            padding: const EdgeInsets.symmetric(vertical: 10),
            textStyle: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ),
    );
  }
}

class _StatusDot extends StatelessWidget {
  final Color color;

  const _StatusDot({required this.color});

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: color == FoodFlowTheme.success ? 'Available' : 'Hidden',
      child: Container(
        width: 9,
        height: 9,
        decoration: BoxDecoration(
          color: color,
          shape: BoxShape.circle,
        ),
      ),
    );
  }
}

class _TagPill extends StatelessWidget {
  final String label;
  final Color color;
  const _TagPill({
    required this.label,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _AvailabilityPill extends StatelessWidget {
  final bool isAvailable;

  const _AvailabilityPill({required this.isAvailable});

  @override
  Widget build(BuildContext context) {
    final color = isAvailable ? FoodFlowTheme.success : FoodFlowTheme.danger;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        isAvailable ? 'LIVE' : 'OFF',
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _ToolbarIconButton extends StatelessWidget {
  final IconData icon;
  final bool isActive;
  final VoidCallback? onPressed;

  const _ToolbarIconButton({
    required this.icon,
    required this.isActive,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: onPressed,
      icon: Icon(icon),
      style: IconButton.styleFrom(
        backgroundColor: isActive
            ? FoodFlowTheme.orange
            : FoodFlowTheme.orange.withOpacity(0.08),
        foregroundColor: isActive ? Colors.white : FoodFlowTheme.orange,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => onTap(),
        selectedColor: FoodFlowTheme.orange,
        backgroundColor: Colors.white,
        labelStyle: TextStyle(
          color: selected ? Colors.white : FoodFlowTheme.ink,
          fontWeight: FontWeight.w900,
        ),
        side: BorderSide(
          color: selected ? FoodFlowTheme.orange : FoodFlowTheme.line,
        ),
      ),
    );
  }
}

class _BulkActionToolbar extends StatelessWidget {
  final int selectedCount;
  final int totalCount;
  final VoidCallback onSelectAll;
  final VoidCallback onClear;
  final VoidCallback onShow;
  final VoidCallback onHide;
  final VoidCallback onDelete;

  const _BulkActionToolbar({
    required this.selectedCount,
    required this.totalCount,
    required this.onSelectAll,
    required this.onClear,
    required this.onShow,
    required this.onHide,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(10),
      decoration: FoodFlowTheme.orangeBand(radius: 14),
      child: Row(
        children: [
          Expanded(
            child: Text(
              '$selectedCount selected',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          TextButton(
            onPressed: selectedCount == totalCount ? onClear : onSelectAll,
            child: Text(
              selectedCount == totalCount ? 'Clear' : 'Select All',
              style: const TextStyle(color: Colors.white),
            ),
          ),
          IconButton(
            tooltip: 'Show selected',
            onPressed: onShow,
            icon: const Icon(Icons.visibility, color: Colors.white),
          ),
          IconButton(
            tooltip: 'Hide selected',
            onPressed: onHide,
            icon: const Icon(Icons.visibility_off, color: Colors.white),
          ),
          IconButton(
            tooltip: 'Delete selected',
            onPressed: onDelete,
            icon: const Icon(Icons.delete_outline, color: Colors.white),
          ),
        ],
      ),
    );
  }
}
