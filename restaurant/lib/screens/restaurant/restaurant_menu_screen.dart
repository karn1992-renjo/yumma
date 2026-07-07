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

  Future<void> _loadData() async {
    setState(() => _isLoading = true);

    try {
      final categoriesResponse =
          await _api.get(ApiConstants.restaurantCategories);
      final cuisinesResponse = await _api.get(ApiConstants.popularCuisines);
      final menuResponse = await _api.get(ApiConstants.restaurantMenuItems);
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
          cuisines: _cuisines,
          onSubmit: ({
            required name,
            description,
            required price,
            discountedPrice,
            categoryId,
            globalCategoryId,
            globalSubcategoryId,
            cuisineId,
            required foodType,
            required isAvailable,
            preparationTime,
            calories,
            required tags,
            required imagePaths,
            required existingImages,
            required variants,
            required addOns,
          }) async {
            await _createMenuItem(
              name: name,
              description: description,
              price: price,
              discountedPrice: discountedPrice,
              categoryId: categoryId,
              globalCategoryId: globalCategoryId,
              globalSubcategoryId: globalSubcategoryId,
              cuisineId: cuisineId,
              foodType: foodType,
              isAvailable: isAvailable,
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
          cuisines: _cuisines,
          onSubmit: ({
            required name,
            description,
            required price,
            discountedPrice,
            categoryId,
            globalCategoryId,
            globalSubcategoryId,
            cuisineId,
            required foodType,
            required isAvailable,
            preparationTime,
            calories,
            required tags,
            required imagePaths,
            required existingImages,
            required variants,
            required addOns,
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
              cuisineId: cuisineId,
              foodType: foodType,
              isAvailable: isAvailable,
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
    int? cuisineId,
    required String foodType,
    bool isAvailable = true,
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
        'cuisine_id': cuisineId,
        'food_type': foodType,
        'is_veg': foodType == 'veg',
        'is_available': isAvailable,
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
          ? await _api.post(ApiConstants.restaurantMenuItems, data: data)
          : await _api.postMultipart(
              ApiConstants.restaurantMenuItems,
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
    int? cuisineId,
    required String foodType,
    bool isAvailable = true,
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
        'cuisine_id': cuisineId,
        'food_type': foodType,
        'is_veg': foodType == 'veg',
        'is_available': isAvailable,
        if (preparationTime != null) 'preparation_time': preparationTime,
        if (calories != null && calories.trim().isNotEmpty)
          'calories': calories.trim(),
        if (tags.isNotEmpty) 'tags': tags,
        'existing_images': existingImages,
        'variants': variants,
        'add_ons': addOns,
      };

      final response = imagePaths.isEmpty
          ? await _api.put(
              '${ApiConstants.restaurantMenuItems}/$itemId',
              data: payload,
            )
          : await _api.postMultipart(
              '${ApiConstants.restaurantMenuItems}/$itemId',
              fields: {
                '_method': 'PUT',
                ...payload.map(
                  (key, value) => MapEntry(
                    key,
                    value is List ? jsonEncode(value) : value?.toString() ?? '',
                  ),
                ),
              },
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
        .split('\n')
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
    }).join('\n');
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
        .join('\n');
  }

  @override
  Widget build(BuildContext context) {
    final user = Provider.of<AuthProvider>(context).currentUser;
    final canManageMenu = user?.canManageMenu ?? true;
    final filteredItems = _visibleMenuItems();

    return Scaffold(
      floatingActionButton: canManageMenu
          ? FloatingActionButton(
              onPressed: _showAddMethodSheet,
              backgroundColor: FoodFlowTheme.orange,
              child: const Icon(Icons.add, color: Colors.white),
            )
          : null,
      backgroundColor: FoodFlowTheme.canvas,
      body: Column(
        children: [
          if (canManageMenu)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: Align(
                  alignment: Alignment.centerRight,
                  child: OutlinedButton.icon(
                      onPressed: _showAdjustPricesSheet,
                      icon: const Icon(Icons.percent),
                      label: const Text('Adjust all prices'))),
            ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : filteredItems.isEmpty
                    ? Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          FoodFlowTheme.emptyState(
                            icon: Icons.restaurant_menu_outlined,
                            title: 'No items in this category',
                            subtitle:
                                'Add dishes and control availability here.',
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 32),
                            child: ElevatedButton.icon(
                              onPressed:
                                  canManageMenu ? _showAddMethodSheet : null,
                              icon: const Icon(Icons.add),
                              label: Text(
                                canManageMenu
                                    ? 'Add Menu Item'
                                    : 'View Only Access',
                              ),
                            ),
                          ),
                        ],
                      )
                    : RefreshIndicator(
                        onRefresh: _loadData,
                        child: ListView.builder(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.all(16),
                          itemCount: filteredItems.length,
                          itemBuilder: (context, index) {
                            final item = filteredItems[index];
                            return _MenuOperatorCard(
                              item: item,
                              canManageMenu: canManageMenu,
                              isSelected: _selectedItemIds.contains(item.id),
                              selectionMode: _selectionMode,
                              onEdit: () => _showEditItemDialog(item),
                              onDuplicate: () => _duplicateMenuItem(item),
                              onToggle: () => _toggleAvailability(
                                item.id,
                                item.isAvailable,
                              ),
                              onDelete: () => _deleteMenuItem(item.id),
                              onSelectionChanged: (selected) =>
                                  _toggleItemSelection(item.id, selected),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}

typedef _MenuItemFormSubmit = Future<void> Function({
  required String name,
  String? description,
  required double price,
  double? discountedPrice,
  int? categoryId,
  int? globalCategoryId,
  int? globalSubcategoryId,
  int? cuisineId,
  required String foodType,
  required bool isAvailable,
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
  final List<dynamic> cuisines;
  final _MenuItemFormSubmit onSubmit;

  const _MenuItemFormScreen({
    this.item,
    required this.categories,
    required this.globalCategories,
    required this.cuisines,
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
  final _prepController = TextEditingController();
  final _caloriesController = TextEditingController();
  final _tagController = TextEditingController();
  final _variantsController = TextEditingController();
  final _addOnsController = TextEditingController();
  final _picker = ImagePicker();

  late List<String> _existingImages;
  final List<XFile> _newImages = [];
  final List<String> _tags = [];
  int? _categoryId;
  int? _globalCategoryId;
  int? _globalSubcategoryId;
  int? _cuisineId;
  String _foodType = 'veg';
  bool _isAvailable = true;
  bool _isSaving = false;

  bool get _isEditing => widget.item != null;

  @override
  void initState() {
    super.initState();
    final item = widget.item;
    _existingImages = item?.images.toList() ?? [];
    if (item != null) {
      _nameController.text = item.name;
      _descriptionController.text = item.description ?? '';
      _priceController.text =
          item.price.toStringAsFixed(getCurrencyDecimals(context));
      _prepController.text = item.preparationTime?.toString() ?? '';
      _categoryId = _containsId(widget.categories, item.categoryId)
          ? item.categoryId
          : null;
      _cuisineId =
          _containsId(widget.cuisines, item.cuisineId) ? item.cuisineId : null;
      _tags.addAll(item.tags);
      _variantsController.text = _formatMenuOptions(item.variants);
      _addOnsController.text = _formatMenuOptions(item.addOns);
      _foodType = item.foodType;
      _isAvailable = item.isAvailable;
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _priceController.dispose();
    _prepController.dispose();
    _caloriesController.dispose();
    _tagController.dispose();
    _variantsController.dispose();
    _addOnsController.dispose();
    super.dispose();
  }

  int? _asInt(dynamic value) {
    if (value is int) return value;
    return int.tryParse(value?.toString() ?? '');
  }

  bool _containsId(List<dynamic> items, int? id) {
    if (id == null) return false;
    return items.any((item) => _asInt(item['id']) == id);
  }

  List<Map<String, dynamic>> _parseMenuOptions(String text) {
    return text
        .split('\n')
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
    }).join('\n');
  }

  List<dynamic> _globalSubcategories(int? categoryId) {
    if (categoryId == null) return const [];
    final category = widget.globalCategories.firstWhere(
      (item) => _asInt(item['id']) == categoryId,
      orElse: () => null,
    );
    if (category is! Map) return const [];
    final subcategories = category['subcategories'];
    return subcategories is List ? subcategories : const [];
  }

  String _asText(dynamic value, [String fallback = '']) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? fallback : text;
  }

  Future<void> _pickImages() async {
    final image = await _picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (image == null) return;
    setState(() {
      _newImages
        ..clear()
        ..add(image);
    });
  }

  void _addTag(String value) {
    final tag = value.trim();
    if (tag.isEmpty || _tags.contains(tag)) return;
    setState(() {
      _tags.add(tag);
      _tagController.clear();
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isSaving = true);
    try {
      await widget.onSubmit(
        name: _nameController.text.trim(),
        description: _descriptionController.text.trim(),
        price: double.parse(_priceController.text.trim()),
        discountedPrice: null,
        categoryId: _categoryId,
        globalCategoryId: _globalCategoryId,
        globalSubcategoryId: _globalSubcategoryId,
        cuisineId: _cuisineId,
        foodType: _foodType,
        isAvailable: _isAvailable,
        preparationTime: int.tryParse(_prepController.text.trim()),
        calories: _caloriesController.text.trim(),
        tags: _tags,
        imagePaths: _newImages.map((image) => image.path).toList(),
        existingImages: _existingImages,
        variants: _parseMenuOptions(_variantsController.text),
        addOns: _parseMenuOptions(_addOnsController.text),
      );
      if (mounted) Navigator.pop(context);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      body: SafeArea(
        child: Column(
          children: [
            _buildHeader(),
            Expanded(
              child: Form(
                key: _formKey,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 18, 16, 28),
                  children: [
                    _sectionTitle('Basic Information'),
                    _label('Menu Name', requiredField: true),
                    _input(
                      controller: _nameController,
                      hint: 'Enter menu name',
                      validator: (value) => value?.trim().isEmpty == true
                          ? 'Menu name is required'
                          : null,
                    ),
                    const SizedBox(height: 14),
                    if (_globalCategoryId == null) ...[
                      _label('Category', requiredField: true),
                      _select<int?>(
                        value: _categoryId,
                        hint: 'Select category',
                        items: widget.categories
                            .map(
                              (category) => DropdownMenuItem<int?>(
                                value: _asInt(category['id']),
                                child: Text(_asText(category['name'])),
                              ),
                            )
                            .toList(),
                        onChanged: (value) =>
                            setState(() => _categoryId = value),
                        validator: (_) =>
                            _categoryId == null && _globalCategoryId == null
                                ? 'Select a category or global category'
                                : null,
                      ),
                    ] else ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: FoodFlowTheme.softSurface(radius: 12),
                        child: Row(
                          children: [
                            Icon(Icons.auto_awesome,
                                color: FoodFlowTheme.orange),
                            SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                'Restaurant category will be created or mapped from the selected global category.',
                                style: TextStyle(
                                  color: FoodFlowTheme.muted,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                    if (widget.globalCategories.isNotEmpty) ...[
                      const SizedBox(height: 14),
                      _label('Global Category Mapping'),
                      _select<int?>(
                        value: _globalCategoryId,
                        hint: 'Select global category',
                        items: [
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('No global category'),
                          ),
                          ...widget.globalCategories.map(
                            (category) => DropdownMenuItem<int?>(
                              value: _asInt(category['id']),
                              child: Text(_asText(category['name'])),
                            ),
                          ),
                        ],
                        onChanged: (value) => setState(() {
                          _globalCategoryId = value;
                          if (value != null) _categoryId = null;
                          _globalSubcategoryId = null;
                        }),
                        validator: (_) =>
                            _categoryId == null && _globalCategoryId == null
                                ? 'Select a category or global category'
                                : null,
                      ),
                      const SizedBox(height: 14),
                      _label('Global Subcategory'),
                      _select<int?>(
                        value: _globalSubcategoryId,
                        hint: 'Select global subcategory',
                        items: [
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('No global subcategory'),
                          ),
                          ..._globalSubcategories(_globalCategoryId).map(
                            (subcategory) => DropdownMenuItem<int?>(
                              value: _asInt(subcategory['id']),
                              child: Text(_asText(subcategory['name'])),
                            ),
                          ),
                        ],
                        onChanged: _globalCategoryId == null
                            ? null
                            : (value) => setState(
                                  () => _globalSubcategoryId = value,
                                ),
                      ),
                    ],
                    const SizedBox(height: 14),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _label('Price', requiredField: true),
                              _input(
                                controller: _priceController,
                                hint: '0.00',
                                prefix: Text(getCurrencySymbol(context)),
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                  decimal: true,
                                ),
                                validator: (value) {
                                  final amount =
                                      double.tryParse(value?.trim() ?? '');
                                  return amount == null ? 'Required' : null;
                                },
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _label('Status', requiredField: true),
                              _select<bool>(
                                value: _isAvailable,
                                items: const [
                                  DropdownMenuItem(
                                    value: true,
                                    child: _StatusOption(active: true),
                                  ),
                                  DropdownMenuItem(
                                    value: false,
                                    child: _StatusOption(active: false),
                                  ),
                                ],
                                onChanged: (value) => setState(
                                    () => _isAvailable = value ?? true),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    _label('Description'),
                    _input(
                      controller: _descriptionController,
                      hint: 'Enter description',
                      minLines: 3,
                      maxLines: 4,
                    ),
                    const SizedBox(height: 20),
                    _imageSection(),
                    const SizedBox(height: 22),
                    _sectionTitle('Additional Information'),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _label('Preparation Time'),
                              _input(
                                controller: _prepController,
                                hint: 'e.g. 15-20 mins',
                                suffix: const Icon(Icons.schedule, size: 19),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _label('Calories'),
                              _input(
                                controller: _caloriesController,
                                hint: 'e.g. 250 kcal',
                                suffix: const Icon(
                                    Icons.local_fire_department_outlined,
                                    size: 19),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    _label('Tags'),
                    _tagsField(),
                    const SizedBox(height: 14),
                    _label('Food Type'),
                    _select<String>(
                      value: _foodType,
                      items: const [
                        DropdownMenuItem(value: 'veg', child: Text('Veg')),
                        DropdownMenuItem(value: 'egg', child: Text('Egg')),
                        DropdownMenuItem(
                            value: 'non_veg', child: Text('Non-Veg')),
                      ],
                      onChanged: (value) =>
                          setState(() => _foodType = value ?? 'veg'),
                    ),
                    if (widget.cuisines.isNotEmpty) ...[
                      const SizedBox(height: 14),
                      _label('Cuisine'),
                      _select<int?>(
                        value: _cuisineId,
                        hint: 'Select cuisine',
                        items: [
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('No cuisine'),
                          ),
                          ...widget.cuisines.map(
                            (cuisine) => DropdownMenuItem<int?>(
                              value: _asInt(cuisine['id']),
                              child: Text(_asText(cuisine['name'])),
                            ),
                          ),
                        ],
                        onChanged: (value) =>
                            setState(() => _cuisineId = value),
                      ),
                    ],
                    const SizedBox(height: 18),
                    _sectionTitle('Variants & Add-ons'),
                    const SizedBox(height: 10),
                    _MenuOptionEditor(
                      controller: _variantsController,
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
                      controller: _addOnsController,
                      label: 'Add-ons / Extras',
                      addButtonLabel: 'Add Extra',
                      placeholder: 'Extra cheese',
                      helpText:
                          'Customers can select multiple available extras during add-to-cart.',
                      emptyText:
                          'No extras added. Add toppings, sides, sauces, or paid extras.',
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

  Widget _buildHeader() {
    return Container(
      height: 72,
      padding: const EdgeInsets.symmetric(horizontal: 8),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: FoodFlowTheme.line)),
      ),
      child: Row(
        children: [
          IconButton(
            onPressed: _isSaving ? null : () => Navigator.pop(context),
            icon: const Icon(Icons.arrow_back, color: Color(0xFF5B21E8)),
          ),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _isEditing ? 'Edit Menu' : 'Add Menu',
                  style: const TextStyle(
                    color: Color(0xFF071332),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  _isEditing ? 'Update menu details' : 'Add a new menu item',
                  style: const TextStyle(
                    color: Color(0xFF65708A),
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: _isSaving ? null : _submit,
            child: _isSaving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(_isEditing ? 'Update' : 'Save'),
          ),
        ],
      ),
    );
  }

  Widget _imageSection() {
    final hasImages = _existingImages.isNotEmpty || _newImages.isNotEmpty;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: FoodFlowTheme.surface(radius: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionTitle('Menu Image', inner: true),
          const SizedBox(height: 12),
          if (!hasImages)
            InkWell(
              onTap: _pickImages,
              borderRadius: BorderRadius.circular(10),
              child: Container(
                width: double.infinity,
                padding:
                    const EdgeInsets.symmetric(vertical: 26, horizontal: 12),
                decoration: BoxDecoration(
                  color: const Color(0xFFFAF7FF),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: const Color(0xFF9C7BFF),
                    style: BorderStyle.solid,
                  ),
                ),
                child: const Column(
                  children: [
                    Icon(Icons.image_outlined,
                        color: Color(0xFF6D35E8), size: 32),
                    SizedBox(height: 10),
                    Text(
                      'Upload Menu Image',
                      style: TextStyle(
                        color: Color(0xFF071332),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Tap to upload an image',
                      style: TextStyle(color: Color(0xFF65708A), fontSize: 12),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'PNG, JPG up to 5MB',
                      style: TextStyle(color: Color(0xFF65708A), fontSize: 12),
                    ),
                  ],
                ),
              ),
            )
          else ...[
            ..._existingImages.map((image) => _existingImageTile(image)),
            ..._newImages.map((image) => _newImageTile(image)),
          ],
        ],
      ),
    );
  }

  Widget _existingImageTile(String image) {
    return _imageTile(
      image: NetworkImageLoader(
        imageUrl: MenuItem(
          id: 0,
          restaurantId: 0,
          name: '',
          price: 0,
          images: [image],
          createdAt: DateTime.now(),
        ).imageUrl,
        width: 100,
        height: 92,
        borderRadius: BorderRadius.circular(8),
      ),
      title: image.split('/').last,
      subtitle: 'Existing image',
      onRemove: () => setState(() => _existingImages.remove(image)),
    );
  }

  Widget _newImageTile(XFile image) {
    return _imageTile(
      image: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: Image.file(
          File(image.path),
          width: 100,
          height: 92,
          fit: BoxFit.cover,
        ),
      ),
      title: image.name,
      subtitle: 'Ready to upload',
      onRemove: () => setState(() => _newImages.remove(image)),
    );
  }

  Widget _imageTile({
    required Widget image,
    required String title,
    required String subtitle,
    required VoidCallback onRemove,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          image,
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFF071332),
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style:
                      const TextStyle(color: Color(0xFF65708A), fontSize: 12),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    OutlinedButton.icon(
                      onPressed: _pickImages,
                      icon: const Icon(Icons.image_outlined, size: 16),
                      label: const Text('Change'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF5B21E8),
                        side: const BorderSide(color: Color(0xFFB8A5FF)),
                        visualDensity: VisualDensity.compact,
                      ),
                    ),
                    OutlinedButton.icon(
                      onPressed: onRemove,
                      icon: const Icon(Icons.delete_outline, size: 16),
                      label: const Text('Remove'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: FoodFlowTheme.danger,
                        side: BorderSide(
                            color: FoodFlowTheme.danger.withOpacity(0.35)),
                        visualDensity: VisualDensity.compact,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _tagsField() {
    return Container(
      constraints: const BoxConstraints(minHeight: 46),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFD6DCE8)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Wrap(
              spacing: 6,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                ..._tags.map(
                  (tag) => Chip(
                    label: Text(tag),
                    deleteIcon: const Icon(Icons.close, size: 16),
                    onDeleted: () => setState(() => _tags.remove(tag)),
                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    visualDensity: VisualDensity.compact,
                    backgroundColor: const Color(0xFFEFF1F5),
                    side: BorderSide.none,
                  ),
                ),
                SizedBox(
                  width: _tags.isEmpty ? 220 : 120,
                  child: TextField(
                    controller: _tagController,
                    decoration: const InputDecoration(
                      hintText: 'Enter tags',
                      border: InputBorder.none,
                      isDense: true,
                    ),
                    onSubmitted: _addTag,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: () => _addTag(_tagController.text),
            icon: const Icon(Icons.keyboard_arrow_down),
          ),
        ],
      ),
    );
  }

  Widget _sectionTitle(String title, {bool inner = false}) {
    return Text(
      title,
      style: TextStyle(
        color: const Color(0xFF071332),
        fontSize: inner ? 16 : 15,
        fontWeight: FontWeight.w900,
      ),
    );
  }

  Widget _label(String label, {bool requiredField = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: RichText(
        text: TextSpan(
          text: label,
          style: const TextStyle(
            color: Color(0xFF071332),
            fontSize: 12,
            fontWeight: FontWeight.w800,
          ),
          children: [
            if (requiredField)
              const TextSpan(
                text: ' *',
                style: TextStyle(color: FoodFlowTheme.danger),
              ),
          ],
        ),
      ),
    );
  }

  Widget _input({
    required TextEditingController controller,
    required String hint,
    Widget? prefix,
    Widget? suffix,
    TextInputType? keyboardType,
    int minLines = 1,
    int maxLines = 1,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      minLines: minLines,
      maxLines: maxLines,
      validator: validator,
      decoration: _decoration(hint: hint, prefix: prefix, suffix: suffix),
    );
  }

  Widget _select<T>({
    required T value,
    String? hint,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?>? onChanged,
    String? Function(T?)? validator,
  }) {
    return DropdownButtonFormField<T>(
      value: value,
      items: items,
      onChanged: onChanged,
      validator: validator,
      decoration: _decoration(hint: hint ?? ''),
      icon: const Icon(Icons.keyboard_arrow_down, color: Color(0xFF65708A)),
    );
  }

  InputDecoration _decoration({
    required String hint,
    Widget? prefix,
    Widget? suffix,
  }) {
    return InputDecoration(
      hintText: hint,
      prefixIcon: prefix == null
          ? null
          : Padding(
              padding: const EdgeInsets.only(left: 14, right: 8),
              child: Center(widthFactor: 1, child: prefix),
            ),
      suffixIcon: suffix,
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
      hintStyle: const TextStyle(color: Color(0xFF7B86A0), fontSize: 14),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFFD6DCE8)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Color(0xFF5B21E8)),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: FoodFlowTheme.danger),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: FoodFlowTheme.danger),
      ),
    );
  }
}

class _StatusOption extends StatelessWidget {
  final bool active;

  const _StatusOption({required this.active});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 11,
          height: 11,
          decoration: BoxDecoration(
            color: active ? FoodFlowTheme.success : FoodFlowTheme.faint,
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 8),
        Text(active ? 'Active' : 'Inactive'),
      ],
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

class _MenuOptionEditorState extends State<_MenuOptionEditor> {
  late List<_EditableMenuOption> _options;

  @override
  void initState() {
    super.initState();
    _options = _EditableMenuOption.parse(widget.controller.text);
    _syncController();
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
        .join('\n');
  }

  @override
  Widget build(BuildContext context) {
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
          if (_options.isEmpty)
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
            )
          else
            ...List.generate(_options.length, (index) {
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
                              decimal: true,
                            ),
                            onChanged: (value) {
                              option.price = value;
                              _syncController();
                            },
                          ),
                        ),
                        const SizedBox(width: 4),
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
                        hintText: 'Portion: 2 slices\nUnit: 500g',
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
                      title: const Text(
                        'Available to customers',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
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
            }),
        ],
      ),
    );
  }
}

class _EditableMenuOption {
  String name;
  String price;
  bool isAvailable;
  String customFieldsText;

  _EditableMenuOption({
    this.name = '',
    this.price = '0',
    this.isAvailable = true,
    this.customFieldsText = '',
  });

  static List<_EditableMenuOption> parse(String text) {
    return text
        .split('\n')
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .map((line) {
          final parts = line.split('|').map((part) => part.trim()).toList();
          return _EditableMenuOption(
            name: parts.isNotEmpty ? parts[0] : '',
            price: parts.length > 1 ? parts[1] : '0',
            isAvailable: parts.length < 3 ||
                !['no', 'false', '0', 'off'].contains(parts[2].toLowerCase()),
            customFieldsText:
                parts.length > 3 ? parts.sublist(3).join(' | ') : '',
          );
        })
        .where((option) => option.name.trim().isNotEmpty)
        .toList();
  }

  String serialize() {
    final fields = customFieldsText
        .split('\n')
        .map((line) => line.trim())
        .where((line) => line.isNotEmpty)
        .join('; ');

    return [
      name.trim(),
      price.trim().isEmpty ? '0' : price.trim(),
      isAvailable ? 'yes' : 'no',
      if (fields.isNotEmpty) fields,
    ].join(' | ');
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

    return GestureDetector(
      onLongPress: canManageMenu ? () => onSelectionChanged(true) : null,
      child: Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.all(12),
        constraints: const BoxConstraints(minHeight: 160),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? FoodFlowTheme.orange : FoodFlowTheme.line,
            width: isSelected ? 1.6 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.055),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (selectionMode)
                  Padding(
                    padding: const EdgeInsets.only(right: 8, top: 30),
                    child: Checkbox(
                      value: isSelected,
                      activeColor: FoodFlowTheme.orange,
                      onChanged: (value) => onSelectionChanged(value ?? false),
                    ),
                  ),
                Stack(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: item.imageUrl.isNotEmpty
                          ? NetworkImageLoader(
                              imageUrl: item.imageUrl,
                              width: 90,
                              height: 90,
                              borderRadius: BorderRadius.circular(12),
                            )
                          : _fallbackImage(),
                    ),
                    Positioned(
                      left: 6,
                      bottom: 6,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          item.isAvailable ? 'Active' : 'Hidden',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 9,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.name,
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontWeight: FontWeight.w900,
                          fontSize: 16,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 3),
                      Text(
                        item.categoryName ?? 'Uncategorized',
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 7),
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
                              fontSize: 15,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          _TagPill(
                            label: item.dietLabel,
                            color: item.isVeg
                                ? FoodFlowTheme.success
                                : FoodFlowTheme.danger,
                          ),
                          if (item.totalOrders > 0)
                            _TagPill(
                              label: '${item.totalOrders} orders',
                              color: FoodFlowTheme.orangeDark,
                            ),
                          if (item.rating != null)
                            _TagPill(
                              label: item.rating!.toStringAsFixed(1),
                              color: FoodFlowTheme.orange,
                              icon: Icons.star,
                            ),
                          if (item.hasDiscount)
                            _TagPill(
                              label: 'Featured',
                              color: FoodFlowTheme.orange,
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                if (canManageMenu)
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Switch(
                        value: item.isAvailable,
                        onChanged: (_) => onToggle(),
                        activeColor: FoodFlowTheme.orange,
                      ),
                      PopupMenuButton<String>(
                        icon: const Icon(Icons.more_vert),
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
            if (canManageMenu) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  _QuickActionButton(
                    icon: Icons.straighten,
                    label: 'Variants',
                    onPressed: onEdit,
                  ),
                  _QuickActionButton(
                    icon: Icons.add_circle_outline,
                    label: 'Addons',
                    onPressed: onEdit,
                  ),
                  _QuickActionButton(
                    icon: Icons.schedule,
                    label: 'Schedule',
                    onPressed: onEdit,
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _fallbackImage() {
    return Container(
      width: 90,
      height: 90,
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

class _TagPill extends StatelessWidget {
  final String label;
  final Color color;
  final IconData? icon;

  const _TagPill({
    required this.label,
    required this.color,
    this.icon,
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
          if (icon != null) ...[
            Icon(icon, size: 11, color: color),
            const SizedBox(width: 3),
          ],
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
