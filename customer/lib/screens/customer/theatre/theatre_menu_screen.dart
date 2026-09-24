import 'package:flutter/material.dart';

import 'theatre_api_service.dart';
import 'theatre_cart_provider.dart';
import 'theatre_cart_screen.dart';
import 'theatre_models.dart';
import 'theatre_theme.dart';

class TheatreMenuScreen extends StatefulWidget {
  const TheatreMenuScreen({
    super.key,
    required this.cart,
    required this.restaurant,
  });

  final TheatreCartProvider cart;
  final TheatreRestaurant restaurant;

  @override
  State<TheatreMenuScreen> createState() => _TheatreMenuScreenState();
}

class _TheatreMenuScreenState extends State<TheatreMenuScreen> {
  late Future<List<Map<String, dynamic>>> _future;
  String _searchQuery = '';
  bool _vegOnly = false;

  @override
  void initState() {
    super.initState();
    _future = TheatreApiService.instance.menuForRestaurant(
      widget.restaurant.id,
    );
    widget.cart.addListener(_onCartChanged);
  }

  @override
  void dispose() {
    widget.cart.removeListener(_onCartChanged);
    super.dispose();
  }

  void _onCartChanged() {
    if (mounted) setState(() {});
  }

  double _priceOf(Map<String, dynamic> item) {
    final discounted = item['discounted_price'];
    if (discounted != null) {
      final value = double.tryParse(discounted.toString());
      if (value != null && value > 0) return value;
    }
    return double.tryParse((item['price'] ?? 0).toString()) ?? 0;
  }

  double? _originalPriceOf(Map<String, dynamic> item) {
    final discounted = item['discounted_price'];
    if (discounted != null) {
      final discVal = double.tryParse(discounted.toString());
      final origVal = double.tryParse((item['price'] ?? 0).toString());
      if (discVal != null && origVal != null && origVal > discVal) {
        return origVal;
      }
    }
    return null;
  }

  bool _isVeg(Map<String, dynamic> item) {
    final veg = item['is_veg'] ?? item['veg'];
    if (veg == true || veg == 1 || veg == '1') return true;
    final foodType = (item['food_type'] ?? '').toString().toLowerCase();
    if (foodType.contains('veg') && !foodType.contains('non')) return true;
    return false;
  }

  @override
  Widget build(BuildContext context) {
    final cart = widget.cart;

    return Scaffold(
      backgroundColor: BmsTheme.bgLight,
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: BmsTheme.textDark,
        elevation: 0.5,
        titleSpacing: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, size: 22),
          color: BmsTheme.textDark,
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.restaurant.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 15.5,
                fontWeight: FontWeight.w800,
                color: BmsTheme.textDark,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              '${cart.screen?.name ?? 'Audi'} · Seat ${cart.seatLabel ?? ''}',
              style: const TextStyle(
                fontSize: 11.5,
                color: Color(0xFF6B7280),
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(
                child: CircularProgressIndicator(color: BmsTheme.primary),
              );
            }
            final allItems = snapshot.data ?? const [];
            if (allItems.isEmpty) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Container(
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.04),
                          blurRadius: 10,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    child: const Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.restaurant_menu_rounded,
                          size: 44,
                          color: Color(0xFF9CA3AF),
                        ),
                        SizedBox(height: 12),
                        Text(
                          'No Items Available',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF111827),
                          ),
                        ),
                        SizedBox(height: 6),
                        Text(
                          'This concession stall has no snacks or beverages listed right now.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Color(0xFF6B7280),
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }

            final filteredItems = allItems.where((item) {
              final name = (item['name'] ?? '').toString().toLowerCase();
              final desc =
                  (item['description'] ?? '').toString().toLowerCase();
              final matchesQuery = _searchQuery.isEmpty ||
                  name.contains(_searchQuery.toLowerCase()) ||
                  desc.contains(_searchQuery.toLowerCase());
              final matchesVeg = !_vegOnly || _isVeg(item);
              return matchesQuery && matchesVeg;
            }).toList();

            return Column(
              children: [
                // Search & Filter Header
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  child: Row(
                    children: [
                      // Search input
                      Expanded(
                        child: Container(
                          height: 40,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFFE5E7EB)),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.02),
                                blurRadius: 4,
                                offset: const Offset(0, 1),
                              ),
                            ],
                          ),
                          padding: const EdgeInsets.symmetric(horizontal: 10),
                          child: TextField(
                            style: const TextStyle(
                              color: Color(0xFF111827),
                              fontSize: 13,
                            ),
                            cursorColor: BmsTheme.primary,
                            decoration: InputDecoration(
                              icon: const Icon(
                                Icons.search_rounded,
                                color: Color(0xFF9CA3AF),
                                size: 18,
                              ),
                              hintText: 'Search snacks & drinks...',
                              hintStyle: const TextStyle(
                                color: Color(0xFF9CA3AF),
                                fontSize: 12.5,
                              ),
                              border: InputBorder.none,
                              isDense: true,
                              contentPadding:
                                  const EdgeInsets.symmetric(vertical: 10),
                              suffixIcon: _searchQuery.isNotEmpty
                                  ? IconButton(
                                      padding: EdgeInsets.zero,
                                      icon: const Icon(
                                        Icons.close_rounded,
                                        color: Color(0xFF9CA3AF),
                                        size: 16,
                                      ),
                                      onPressed: () =>
                                          setState(() => _searchQuery = ''),
                                    )
                                  : null,
                            ),
                            onChanged: (q) => setState(() => _searchQuery = q),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      // Veg Toggle Chip
                      FilterChip(
                        selected: _vegOnly,
                        label: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            BmsDietIndicator(isVeg: true),
                            SizedBox(width: 6),
                            Text(
                              'VEG',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ],
                        ),
                        labelStyle: TextStyle(
                          color: _vegOnly ? Colors.white : const Color(0xFF374151),
                        ),
                        backgroundColor: Colors.white,
                        selectedColor: const Color(0xFF15803D),
                        checkmarkColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                          side: BorderSide(
                            color: _vegOnly
                                ? const Color(0xFF15803D)
                                : const Color(0xFFE5E7EB),
                          ),
                        ),
                        onSelected: (val) => setState(() => _vegOnly = val),
                      ),
                    ],
                  ),
                ),

                // Items list
                Expanded(
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 96),
                    itemCount: filteredItems.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final item = filteredItems[index];
                      final id = (item['id'] as num?)?.toInt();
                      if (id == null) return const SizedBox.shrink();
                      final name = (item['name'] ?? '').toString();
                      final desc = (item['description'] ?? '').toString();
                      final price = _priceOf(item);
                      final origPrice = _originalPriceOf(item);
                      final available = item['is_available'] != false;
                      final isVeg = _isVeg(item);
                      final qty =
                          cart.quantityFor(widget.restaurant.id, id);

                      return Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: qty > 0
                                ? BmsTheme.primary
                                : const Color(0xFFE5E7EB),
                            width: qty > 0 ? 1.5 : 1.0,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.04),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Veg / Non-Veg Indicator
                            Padding(
                              padding: const EdgeInsets.only(top: 3),
                              child: BmsDietIndicator(isVeg: isVeg),
                            ),
                            const SizedBox(width: 10),

                            // Item Info
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    name,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 14.5,
                                      color: Color(0xFF111827),
                                    ),
                                  ),
                                  if (desc.isNotEmpty) ...[
                                    const SizedBox(height: 3),
                                    Text(
                                      desc,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        fontSize: 11.5,
                                        color: Color(0xFF6B7280),
                                        height: 1.3,
                                      ),
                                    ),
                                  ],
                                  const SizedBox(height: 6),
                                  Row(
                                    children: [
                                      Text(
                                        '₹${price.toStringAsFixed(0)}',
                                        style: const TextStyle(
                                          color: Color(0xFF111827),
                                          fontWeight: FontWeight.w800,
                                          fontSize: 14.5,
                                        ),
                                      ),
                                      if (origPrice != null) ...[
                                        const SizedBox(width: 6),
                                        Text(
                                          '₹${origPrice.toStringAsFixed(0)}',
                                          style: const TextStyle(
                                            color: Color(0xFF9CA3AF),
                                            decoration:
                                                TextDecoration.lineThrough,
                                            fontSize: 12,
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                  if (!available)
                                    const Padding(
                                      padding: EdgeInsets.only(top: 4),
                                      child: Text(
                                        'Currently Unavailable',
                                        style: TextStyle(
                                          color: BmsTheme.red,
                                          fontSize: 11.5,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            ),

                            const SizedBox(width: 12),

                            // BMS Add Button / Stepper
                            if (available)
                              qty == 0
                                  ? OutlinedButton(
                                      onPressed: () => cart.addItem(
                                        restaurant: widget.restaurant,
                                        menuItemId: id,
                                        name: name,
                                        price: price,
                                      ),
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: BmsTheme.primary,
                                        side: const BorderSide(
                                          color: BmsTheme.primary,
                                          width: 1.5,
                                        ),
                                        backgroundColor: const Color(0xFFFFF1F3),
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 16,
                                          vertical: 8,
                                        ),
                                        shape: RoundedRectangleBorder(
                                          borderRadius:
                                              BorderRadius.circular(8),
                                        ),
                                      ),
                                      child: const Text(
                                        '+ ADD',
                                        style: TextStyle(
                                          fontWeight: FontWeight.w900,
                                          fontSize: 12,
                                          letterSpacing: 0.5,
                                        ),
                                      ),
                                    )
                                  : _BmsQuantityStepper(
                                      quantity: qty,
                                      onDecrement: () => cart.removeItem(
                                        restaurantId: widget.restaurant.id,
                                        menuItemId: id,
                                      ),
                                      onIncrement: () => cart.addItem(
                                        restaurant: widget.restaurant,
                                        menuItemId: id,
                                        name: name,
                                        price: price,
                                      ),
                                    ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
              ],
            );
          },
        ),
      ),
      bottomNavigationBar: cart.isEmpty
          ? null
          : Container(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
              decoration: BoxDecoration(
                color: Colors.white,
                border: const Border(
                  top: BorderSide(color: Color(0xFFE5E7EB)),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.06),
                    blurRadius: 10,
                    offset: const Offset(0, -3),
                  ),
                ],
              ),
              child: SafeArea(
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${cart.totalItemCount} ITEM${cart.totalItemCount > 1 ? 'S' : ''} IN CART',
                            style: const TextStyle(
                              color: Color(0xFF6B7280),
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 0.8,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '₹${cart.total.toStringAsFixed(0)}',
                            style: const TextStyle(
                              color: Color(0xFF111827),
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ],
                      ),
                    ),
                    ElevatedButton(
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => TheatreCartScreen(cart: cart),
                          ),
                        );
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: BmsTheme.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 22,
                          vertical: 14,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        elevation: 3,
                        shadowColor: BmsTheme.primary.withOpacity(0.35),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            'View Order',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.3,
                            ),
                          ),
                          SizedBox(width: 6),
                          Icon(Icons.arrow_forward_rounded, size: 16),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}

class _BmsQuantityStepper extends StatelessWidget {
  const _BmsQuantityStepper({
    required this.quantity,
    required this.onDecrement,
    required this.onIncrement,
  });

  final int quantity;
  final VoidCallback onDecrement;
  final VoidCallback onIncrement;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: BmsTheme.primary,
        borderRadius: BorderRadius.circular(8),
        boxShadow: [
          BoxShadow(
            color: BmsTheme.primary.withValues(alpha: 0.35),
            blurRadius: 6,
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            visualDensity: VisualDensity.compact,
            padding: const EdgeInsets.all(4),
            constraints: const BoxConstraints(minWidth: 30, minHeight: 30),
            icon: const Icon(Icons.remove_rounded, color: Colors.white, size: 16),
            onPressed: onDecrement,
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: Text(
              '$quantity',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w900,
                fontSize: 13,
              ),
            ),
          ),
          IconButton(
            visualDensity: VisualDensity.compact,
            padding: const EdgeInsets.all(4),
            constraints: const BoxConstraints(minWidth: 30, minHeight: 30),
            icon: const Icon(Icons.add_rounded, color: Colors.white, size: 16),
            onPressed: onIncrement,
          ),
        ],
      ),
    );
  }
}
