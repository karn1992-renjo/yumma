// lib/screens/restaurant/restaurant_promos_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';

class RestaurantPromosScreen extends StatefulWidget {
  const RestaurantPromosScreen({Key? key}) : super(key: key);

  @override
  State<RestaurantPromosScreen> createState() => _RestaurantPromosScreenState();
}

class _RestaurantPromosScreenState extends State<RestaurantPromosScreen> {
  final ApiService _api = ApiService();

  List<dynamic> _promos = [];
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
      if (response['success'] == true) {
        setState(() {
          _promos = response['data'] ?? [];
        });
      }
    } catch (e) {
      debugPrint('Load promos error: $e');
    }

    setState(() => _isLoading = false);
  }

  Future<void> _togglePromoStatus(int promoId, bool currentStatus) async {
    try {
      final response =
          await _api.post('${ApiConstants.restaurantPromos}/$promoId/toggle');
      if (response['success'] == true) {
        setState(() {
          final index = _promos.indexWhere((p) => p['id'] == promoId);
          if (index != -1) {
            _promos[index]['is_active'] = !currentStatus;
          }
        });
      }
    } catch (e) {
      debugPrint('Toggle promo error: $e');
    }
  }

  Future<void> _deletePromo(int promoId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Promo'),
        content: const Text('Are you sure you want to delete this promo code?'),
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
            const SnackBar(content: Text('Promo deleted successfully')),
          );
        }
      }
    } catch (e) {
      debugPrint('Delete promo error: $e');
    }
  }

  void _showAddPromoDialog() {
    final formKey = GlobalKey<FormState>();
    final codeController = TextEditingController();
    final descriptionController = TextEditingController();
    final discountValueController = TextEditingController();
    String discountType = 'percentage';
    double? minOrderAmount;
    double? maxDiscountAmount;
    int? usageLimit;
    DateTime? startDate;
    DateTime? endDate;

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
                      'Create Promo Code',
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
                          controller: codeController,
                          decoration: const InputDecoration(
                            labelText: 'Promo Code',
                            border: OutlineInputBorder(),
                            hintText: 'e.g., SAVE20',
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
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: DropdownButtonFormField<String>(
                                value: discountType,
                                decoration: const InputDecoration(
                                  labelText: 'Discount Type',
                                  border: OutlineInputBorder(),
                                ),
                                items: [
                                  DropdownMenuItem(
                                    value: 'percentage',
                                    child: const Text('Percentage (%)'),
                                  ),
                                  DropdownMenuItem(
                                    value: 'fixed',
                                    child: Text('Fixed Amount (${getCurrencySymbol(context)})'),
                                  ),
                                ],
                                onChanged: (value) =>
                                    setState(() => discountType = value!),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: discountValueController,
                                decoration: InputDecoration(
                                  labelText: 'Discount Value',
                                  prefixText: discountType == 'percentage'
                                      ? '%'
                                      : currencyInputPrefix(context),
                                  border: const OutlineInputBorder(),
                                ),
                                keyboardType: TextInputType.number,
                                validator: (value) =>
                                    value?.isEmpty == true ? 'Required' : null,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                decoration: InputDecoration(
                                  labelText: 'Min Order Amount',
                                  prefixText: currencyInputPrefix(context),
                                  border: OutlineInputBorder(),
                                ),
                                keyboardType: TextInputType.number,
                                onChanged: (value) =>
                                    minOrderAmount = double.tryParse(value),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                decoration: InputDecoration(
                                  labelText: 'Max Discount',
                                  prefixText: currencyInputPrefix(context),
                                  border: OutlineInputBorder(),
                                ),
                                keyboardType: TextInputType.number,
                                onChanged: (value) =>
                                    maxDiscountAmount = double.tryParse(value),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          decoration: const InputDecoration(
                            labelText: 'Usage Limit',
                            border: OutlineInputBorder(),
                          ),
                          keyboardType: TextInputType.number,
                          onChanged: (value) =>
                              usageLimit = int.tryParse(value),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: InkWell(
                                onTap: () async {
                                  final date = await showDatePicker(
                                    context: context,
                                    initialDate: DateTime.now(),
                                    firstDate: DateTime.now(),
                                    lastDate: DateTime.now()
                                        .add(const Duration(days: 365)),
                                  );
                                  if (date != null) {
                                    setState(() => startDate = date);
                                  }
                                },
                                child: InputDecorator(
                                  decoration: const InputDecoration(
                                    labelText: 'Start Date',
                                    border: OutlineInputBorder(),
                                  ),
                                  child: Text(
                                    startDate != null
                                        ? DateFormat('dd MMM yyyy')
                                            .format(startDate!)
                                        : 'Select date',
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: InkWell(
                                onTap: () async {
                                  final date = await showDatePicker(
                                    context: context,
                                    initialDate: DateTime.now()
                                        .add(const Duration(days: 7)),
                                    firstDate: DateTime.now(),
                                    lastDate: DateTime.now()
                                        .add(const Duration(days: 365)),
                                  );
                                  if (date != null) {
                                    setState(() => endDate = date);
                                  }
                                },
                                child: InputDecorator(
                                  decoration: const InputDecoration(
                                    labelText: 'End Date',
                                    border: OutlineInputBorder(),
                                  ),
                                  child: Text(
                                    endDate != null
                                        ? DateFormat('dd MMM yyyy')
                                            .format(endDate!)
                                        : 'Select date',
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: () async {
                              if (formKey.currentState!.validate()) {
                                if (startDate == null || endDate == null) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(
                                      content: Text(
                                          'Please select start and end dates'),
                                    ),
                                  );
                                  return;
                                }

                                Navigator.pop(context);
                                await _createPromo(
                                  code: codeController.text,
                                  description: descriptionController.text,
                                  discountType: discountType,
                                  discountValue: double.parse(
                                      discountValueController.text),
                                  minOrderAmount: minOrderAmount,
                                  maxDiscountAmount: maxDiscountAmount,
                                  usageLimit: usageLimit,
                                  startDate: startDate!,
                                  endDate: endDate!,
                                );
                              }
                            },
                            child: const Text('Create Promo'),
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

  Future<void> _createPromo({
    required String code,
    String? description,
    required String discountType,
    required double discountValue,
    double? minOrderAmount,
    double? maxDiscountAmount,
    int? usageLimit,
    required DateTime startDate,
    required DateTime endDate,
  }) async {
    try {
      final response = await _api.post(ApiConstants.restaurantPromos, data: {
        'code': code.toUpperCase(),
        'description': description,
        'discount_type': discountType,
        'discount_value': discountValue,
        'min_order_amount': minOrderAmount,
        'max_discount_amount': maxDiscountAmount,
        'usage_limit': usageLimit,
        'start_date': startDate.toIso8601String(),
        'end_date': endDate.toIso8601String(),
      });

      if (response['success'] == true) {
        await _loadPromos();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Promo code created successfully')),
          );
        }
      }
    } catch (e) {
      debugPrint('Create promo error: $e');
    }
  }

  DateTime? _tryParseDate(dynamic value) {
    if (value is DateTime) return value;
    if (value is String && value.trim().isNotEmpty) {
      return DateTime.tryParse(value.trim());
    }
    return null;
  }

  String _formatPromoDateRange(Map promo) {
    final startDate = _tryParseDate(promo['start_date']);
    final endDate = _tryParseDate(promo['end_date']);

    if (startDate != null && endDate != null) {
      return '${DateFormat('dd MMM').format(startDate)} - ${DateFormat('dd MMM yyyy').format(endDate)}';
    }
    if (startDate != null) {
      return 'Starts ${DateFormat('dd MMM yyyy').format(startDate)}';
    }
    if (endDate != null) {
      return 'Until ${DateFormat('dd MMM yyyy').format(endDate)}';
    }
    return 'No validity dates set';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Promotions'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: _showAddPromoDialog,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _promos.isEmpty
              ? Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    FoodFlowTheme.emptyState(
                      icon: Icons.local_offer_outlined,
                      title: 'No promo codes yet',
                      subtitle: 'Create offers to attract more orders.',
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 32),
                      child: ElevatedButton(
                        onPressed: _showAddPromoDialog,
                        child: const Text('Create First Promo'),
                      ),
                    ),
                  ],
                )
              : ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  itemCount: _promos.length + 1,
                  itemBuilder: (context, index) {
                    if (index == 0) {
                      return PremiumRestaurantHeader(
                        title: 'Growth Offers',
                        subtitle:
                            '${_promos.length} promos configured for acquisition and repeat orders.',
                        icon: Icons.local_offer,
                        trailing: IconButton(
                          onPressed: _showAddPromoDialog,
                          icon: const Icon(Icons.add),
                          color: Colors.white,
                          style: IconButton.styleFrom(
                            backgroundColor: Colors.white.withOpacity(0.14),
                          ),
                        ),
                      );
                    }
                    final promo = _promos[index - 1];
                    final isActive = promo['is_active'] ?? false;
                    final endDate = _tryParseDate(promo['end_date']);
                    final isExpired = endDate != null &&
                        endDate.isBefore(
                          DateTime.now().subtract(const Duration(days: 1)),
                        );

                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      decoration: RestaurantPremium.panel(radius: 16),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 6,
                                  ),
                                  decoration: BoxDecoration(
                                    color: FoodFlowTheme.orange
                                        .withOpacity(0.1),
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: Text(
                                    promo['code'],
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                      color: FoodFlowTheme.orange,
                                    ),
                                  ),
                                ),
                                Row(
                                  children: [
                                    Switch(
                                      value: isActive && !isExpired,
                                      onChanged: (_) => _togglePromoStatus(
                                        promo['id'],
                                        isActive,
                                      ),
                                      activeColor: FoodFlowTheme.orange,
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.delete_outline,
                                          color: Colors.red),
                                      onPressed: () =>
                                          _deletePromo(promo['id']),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            if (promo['description'] != null)
                              Text(
                                promo['description'],
                                style: TextStyle(color: Colors.grey.shade600),
                              ),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                Icon(Icons.local_offer,
                                    size: 16, color: Colors.grey.shade600),
                                const SizedBox(width: 4),
                                Text(
                                  promo['discount_type'] == 'percentage'
                                      ? '${promo['discount_value']}% OFF'
                                      : '${formatCurrencyValue(context, promo['discount_value'])} OFF',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w500),
                                ),
                                if (promo['min_order_amount'] != null)
                                  Padding(
                                    padding: const EdgeInsets.only(left: 12),
                                    child: Text(
                                      'Min ${formatCurrencyValue(context, promo['min_order_amount'])}',
                                      style: TextStyle(
                                          fontSize: 12,
                                          color: Colors.grey.shade600),
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                Icon(Icons.date_range,
                                    size: 16, color: Colors.grey.shade600),
                                const SizedBox(width: 4),
                                Text(
                                  _formatPromoDateRange(promo),
                                  style: TextStyle(
                                      fontSize: 12,
                                      color: Colors.grey.shade600),
                                ),
                              ],
                            ),
                            if (promo['used_count'] != null)
                              Padding(
                                padding: const EdgeInsets.only(top: 8),
                                child: Text(
                                  'Used: ${promo['used_count']}${promo['usage_limit'] != null ? ' / ${promo['usage_limit']}' : ''}',
                                  style: TextStyle(
                                      fontSize: 12,
                                      color: Colors.grey.shade600),
                                ),
                              ),
                            if (isExpired && isActive)
                              Container(
                                margin: const EdgeInsets.only(top: 8),
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 8, vertical: 4),
                                decoration: BoxDecoration(
                                  color: Colors.orange.shade100,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: const Text(
                                  'Expired',
                                  style: TextStyle(
                                      fontSize: 12, color: Colors.orange),
                                ),
                              ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}
