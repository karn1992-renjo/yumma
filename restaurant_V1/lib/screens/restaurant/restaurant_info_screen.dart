import 'package:flutter/material.dart';
import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';

class RestaurantInfoScreen extends StatefulWidget {
  const RestaurantInfoScreen({super.key});

  @override
  State<RestaurantInfoScreen> createState() => _RestaurantInfoScreenState();
}

class _RestaurantInfoScreenState extends State<RestaurantInfoScreen> {
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();

  final _name = TextEditingController();
  final _description = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _address = TextEditingController();
  final _city = TextEditingController();
  final _state = TextEditingController();
  final _pincode = TextEditingController();
  final _minOrder = TextEditingController();

  bool _isLoading = true;
  bool _isSaving = false;
  bool _isPureVeg = false;
  bool _autoAccept = false;
  Map<String, dynamic> _info = {};
  List<dynamic> _availableCuisines = [];
  String? _selectedCuisine;

  List<String> get _cuisineOptions {
    final seen = <String>{};
    final options = <String>[];

    for (final cuisine in _availableCuisines) {
      final rawName = cuisine is Map ? cuisine['name'] : null;
      final name = rawName?.toString().trim() ?? '';
      if (name.isEmpty || !seen.add(name)) {
        continue;
      }
      options.add(name);
    }

    return options;
  }

  @override
  void initState() {
    super.initState();
    _loadInfo();
  }

  Future<void> _loadInfo() async {
    setState(() => _isLoading = true);
    try {
      final infoResponse = await _api.get(ApiConstants.restaurantInfo);
      final cuisinesResponse = await _api.get(ApiConstants.popularCuisines);

      if (mounted && cuisinesResponse['success'] == true) {
        _availableCuisines = List<dynamic>.from(cuisinesResponse['data'] ?? []);
      }

      if (infoResponse['success'] == true && mounted) {
        final data = Map<String, dynamic>.from(infoResponse['data'] as Map);
        _applyInfo(data);
      }
    } catch (e) {
      debugPrint('Load restaurant info error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to load restaurant info: $e')),
        );
      }
    }
    if (mounted) setState(() => _isLoading = false);
  }

  void _applyInfo(Map<String, dynamic> data) {
    _info = data;
    _name.text = data['name']?.toString() ?? '';
    _description.text = data['description']?.toString() ?? '';
    _phone.text = data['phone']?.toString() ?? '';
    _email.text = data['email']?.toString() ?? '';
    _address.text = data['address']?.toString() ?? '';
    _city.text = data['city']?.toString() ?? '';
    _state.text = data['state']?.toString() ?? '';
    _pincode.text = data['pincode']?.toString() ?? '';
    final cuisine = data['cuisine'];
    final selectedCuisine = cuisine is List
        ? (cuisine.isNotEmpty ? cuisine.first.toString() : null)
        : cuisine?.toString();
    final availableCuisineNames = _cuisineOptions.toSet();
    _selectedCuisine = availableCuisineNames.contains(selectedCuisine)
        ? selectedCuisine
        : null;
    _minOrder.text = data['min_order_amount']?.toString() ?? '';
    _isPureVeg = data['is_pure_veg'] == true;
    _autoAccept = data['auto_accept_orders'] == true;
  }

  int _totalRatings() {
    final raw = _info['total_ratings'] ?? _info['review_count'] ?? 0;
    if (raw is num) return raw.toInt();
    return int.tryParse(raw.toString()) ?? 0;
  }

  bool _hasVisibleRating() => _totalRatings() >= 3;

  String _ratingSubtitle() {
    final totalRatings = _totalRatings();
    final rating = double.tryParse('${_info['rating'] ?? ''}');

    if (_hasVisibleRating() && rating != null && rating > 0) {
      return '${rating.toStringAsFixed(1)} rating • $totalRatings reviews';
    }

    return 'New restaurant • $totalRatings ratings so far';
  }

  Future<void> _saveInfo() async {
    if (!_formKey.currentState!.validate() || _isSaving) return;

    setState(() => _isSaving = true);
    try {
      final response = await _api.post(
        ApiConstants.restaurantInfo,
        data: {
          'name': _name.text.trim(),
          'description': _description.text.trim(),
          'phone': _phone.text.trim(),
          'email': _email.text.trim(),
          'address': _address.text.trim(),
          'city': _city.text.trim(),
          'state': _state.text.trim(),
          'pincode': _pincode.text.trim(),
          'cuisine': _selectedCuisine,
          'is_pure_veg': _isPureVeg,
          'auto_accept_orders': _autoAccept,
        },
      );

      if (response['success'] == true && mounted) {
        _applyInfo(Map<String, dynamic>.from(response['data'] as Map));
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Restaurant information updated')),
        );
      }
    } catch (e) {
      debugPrint('Save restaurant info error: $e');
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Failed to save: $e')));
      }
    }
    if (mounted) setState(() => _isSaving = false);
  }

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    _phone.dispose();
    _email.dispose();
    _address.dispose();
    _city.dispose();
    _state.dispose();
    _pincode.dispose();
    _minOrder.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Restaurant Info'),
        actions: [
          IconButton(
            onPressed: _isLoading ? null : _loadInfo,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: FoodFlowTheme.line)),
          ),
          child: ElevatedButton.icon(
            onPressed: _isSaving || _isLoading ? null : _saveInfo,
            icon: _isSaving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.save_outlined),
            label: const Text('Save Changes'),
          ),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadInfo,
              child: Form(
                key: _formKey,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                  children: [
                    PremiumRestaurantHeader(
                      title: _name.text.isEmpty ? 'Store Profile' : _name.text,
                      subtitle: _ratingSubtitle(),
                      icon: Icons.storefront,
                    ),
                    _branchTile(),
                    _section('Identity', [
                      _field(
                        _name,
                        'Restaurant name',
                        Icons.storefront,
                        required: true,
                      ),
                      _field(
                        _description,
                        'Description',
                        Icons.notes,
                        maxLines: 3,
                      ),
                      Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        decoration: FoodFlowTheme.softSurface(radius: 14),
                        child: DropdownButtonFormField<String>(
                          value: _selectedCuisine,
                          decoration: const InputDecoration(
                            labelText: 'Cuisine',
                            prefixIcon: Icon(Icons.restaurant_menu),
                          ),
                          items: [
                            const DropdownMenuItem<String>(
                              value: null,
                              child: Text('Select cuisine'),
                            ),
                            ..._cuisineOptions.map(
                              (cuisineName) => DropdownMenuItem<String>(
                                value: cuisineName,
                                child: Text(cuisineName),
                              ),
                            ),
                          ],
                          onChanged: (value) =>
                              setState(() => _selectedCuisine = value),
                        ),
                      ),
                    ]),
                    _section('Contact', [
                      _field(
                        _phone,
                        'Phone',
                        Icons.call_outlined,
                        keyboardType: TextInputType.phone,
                      ),
                      _field(
                        _email,
                        'Email',
                        Icons.mail_outline,
                        keyboardType: TextInputType.emailAddress,
                      ),
                    ]),
                    _section('Location', [
                      _field(
                        _address,
                        'Address',
                        Icons.location_on_outlined,
                        maxLines: 2,
                      ),
                      Row(
                        children: [
                          Expanded(
                            child: _field(_city, 'City', Icons.apartment),
                          ),
                          const SizedBox(width: 10),
                          Expanded(child: _field(_state, 'State', Icons.map)),
                        ],
                      ),
                      _field(
                        _pincode,
                        'Pincode',
                        Icons.pin_drop_outlined,
                        keyboardType: TextInputType.number,
                      ),
                    ]),
                    _section('Ordering', [
                      _field(
                        _minOrder,
                        'Min order',
                        Icons.payments,
                        keyboardType: TextInputType.number,
                        enabled: false,
                      ),
                      const Padding(
                        padding: EdgeInsets.only(bottom: 12),
                        child: Text(
                          'Minimum order, delivery fee, delivery time and radius are controlled by admin.',
                          style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                          ),
                        ),
                      ),
                      SwitchListTile(
                        value: _isPureVeg,
                        onChanged: (value) =>
                            setState(() => _isPureVeg = value),
                        title: const Text('Pure veg restaurant'),
                        contentPadding: EdgeInsets.zero,
                        activeColor: FoodFlowTheme.success,
                      ),
                      SwitchListTile(
                        value: _autoAccept,
                        onChanged: (value) =>
                            setState(() => _autoAccept = value),
                        title: const Text('Auto accept orders'),
                        contentPadding: EdgeInsets.zero,
                        activeColor: FoodFlowTheme.orange,
                      ),
                    ]),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _section(String title, List<Widget> children) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: RestaurantPremium.panel(radius: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }

  Widget _branchTile() {
    final branch = _info['branch'];
    if (branch is! Map) {
      return const SizedBox.shrink();
    }

    final code = branch['code']?.toString().trim();
    final name = branch['name']?.toString().trim();
    final city = branch['city']?.toString().trim();
    final state = branch['state']?.toString().trim();
    final location = [
      if (city != null && city.isNotEmpty) city,
      if (state != null && state.isNotEmpty) state,
    ].join(', ');

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: RestaurantPremium.panel(radius: 16),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: FoodFlowTheme.softSurface(radius: 14),
            child: const Icon(Icons.account_tree_outlined),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  [
                    if (code != null && code.isNotEmpty) code,
                    if (name != null && name.isNotEmpty) name,
                  ].join(' - '),
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (location.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 3),
                    child: Text(
                      location,
                      style: const TextStyle(color: FoodFlowTheme.muted),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _field(
    TextEditingController controller,
    String label,
    IconData icon, {
    String? hint,
    bool required = false,
    bool enabled = true,
    int maxLines = 1,
    TextInputType? keyboardType,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        enabled: enabled,
        maxLines: maxLines,
        keyboardType: keyboardType,
        decoration: InputDecoration(
          labelText: label,
          hintText: hint,
          prefixIcon: Icon(icon),
          border: const OutlineInputBorder(),
        ),
        validator: required
            ? (value) =>
                  value == null || value.trim().isEmpty ? 'Required' : null
            : null,
      ),
    );
  }
}
