import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../config/app_config.dart';
import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../services/local_cache_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_image_loader.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';

class RestaurantPayoutDetailScreen extends StatefulWidget {
  const RestaurantPayoutDetailScreen({
    super.key,
    required this.payoutId,
    this.initialTransaction,
  });

  final int payoutId;
  final Map<String, dynamic>? initialTransaction;

  @override
  State<RestaurantPayoutDetailScreen> createState() =>
      _RestaurantPayoutDetailScreenState();
}

class _RestaurantPayoutDetailScreenState
    extends State<RestaurantPayoutDetailScreen> {
  final ApiService _api = ApiService();
  bool _isLoading = true;
  bool _hasData = false;
  Map<String, dynamic> _payout = {};
  List<Map<String, dynamic>> _orders = [];

  String get _cacheKey =>
      '${AppConfig.apiBaseUrl}${ApiConstants.walletPayoutDetails(widget.payoutId)}';

  @override
  void initState() {
    super.initState();
    // Seed instantly: the transaction row we were opened with, then any cached
    // full response, then a silent network refresh.
    if (widget.initialTransaction != null) {
      _payout = Map<String, dynamic>.from(widget.initialTransaction!);
      _isLoading = false;
    }
    final cached = LocalCacheService.get(_cacheKey);
    if (cached is Map && _applyPayout(cached)) {
      _isLoading = false;
      _hasData = true;
    }
    _loadPayout(silent: _isLoading == false);
  }

  bool _applyPayout(Map<dynamic, dynamic> response) {
    if (response['success'] != true) return false;
    final data = _asMap(response['data']);
    final orders = data['orders'] is List ? data['orders'] as List : const [];
    _payout = _asMap(data['payout']);
    _orders = orders
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    return true;
  }

  Future<void> _loadPayout({bool silent = false}) async {
    if (!silent) setState(() => _isLoading = true);
    try {
      final response = await _api.get(
        ApiConstants.walletPayoutDetails(widget.payoutId),
      );
      if (!mounted) return;
      if (response is Map && _applyPayout(response)) {
        setState(() => _hasData = true);
      }
    } catch (e) {
      if (mounted && !_hasData && widget.initialTransaction == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Payout details unavailable: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        leading: const BackButton(),
        title: Text('Payout statement',
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            )),
        actions: [
          IconButton(
            onPressed: _isLoading ? null : _loadPayout,
            icon: Icon(Icons.refresh_rounded, color: foodflow.ink),
          ),
        ],
      ),
      body: Stack(children: [
        Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(color: foodflow.canvas),
            child: Stack(children: AuroraTheme.auroraBlobs()),
          ),
        ),
        Positioned.fill(
          child: RefreshIndicator(
            color: foodflow.orange,
            onRefresh: _loadPayout,
            child: _isLoading
                ? Center(
                    child: CircularProgressIndicator(color: foodflow.orange))
                : ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.fromLTRB(16,
                        MediaQuery.of(context).padding.top + 64, 16, 30),
                    children: [
                  _PayoutHero(
                    payout: _payout,
                    transaction: widget.initialTransaction,
                    orderCount: _orders.length,
                  ),
                  const SizedBox(height: 12),
                  _PayoutBreakdown(payout: _payout),
                  const SizedBox(height: 18),
                  _SectionHeading(
                    title: 'Covered Orders',
                    subtitle: _orders.isEmpty
                        ? 'No linked orders found'
                        : '${_orders.length} orders included',
                  ),
                  const SizedBox(height: 10),
                  if (_orders.isEmpty)
                    const _NoOrdersState()
                  else
                    ..._orders.map((order) => _PayoutOrderCard(
                          order: order,
                          onOpenOrder: _openOrderDetails,
                        )),
                    ],
                  ),
          ),
        ),
      ]),
    );
  }

  void _openOrderDetails(Map<String, dynamic> order) {
    final orderId = _toInt(order['id'] ?? order['order_id']);
    if (orderId == null) return;
    final restaurantId = _restaurantIdFrom(order);
    Navigator.pushNamed(
      context,
      '/restaurant/order',
      arguments: {
        'orderId': orderId,
        if (restaurantId != null) 'restaurantId': restaurantId,
      },
    );
  }
}

class _PayoutHero extends StatelessWidget {
  const _PayoutHero({
    required this.payout,
    required this.transaction,
    required this.orderCount,
  });

  final Map<String, dynamic> payout;
  final Map<String, dynamic>? transaction;
  final int orderCount;

  @override
  Widget build(BuildContext context) {
    final amount = _toDouble(payout['amount'] ?? transaction?['amount']);
    final status = '${payout['status'] ?? 'pending'}';
    final gateway = '${payout['gateway'] ?? ''}'.trim();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: _panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: FoodFlowTheme.orange.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  Icons.payments_rounded,
                  color: FoodFlowTheme.orange,
                  size: 22,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Payout #${payout['id'] ?? ''}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _dateRange(payout),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              _StatusPill(text: status),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            formatCurrencyWithDecimals(context, amount),
            style: TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 28,
              height: 1,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _InfoChip(
                icon: Icons.receipt_long_rounded,
                text: '$orderCount orders',
              ),
              if (gateway.isNotEmpty)
                _InfoChip(
                  icon: Icons.account_balance_rounded,
                  text: _titleCase(gateway.replaceAll('_', ' ')),
                ),
              _InfoChip(
                icon: Icons.schedule_rounded,
                text: _formatDate(payout['created_at']),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PayoutBreakdown extends StatelessWidget {
  const _PayoutBreakdown({required this.payout});

  final Map<String, dynamic> payout;

  @override
  Widget build(BuildContext context) {
    final gross = _toDouble(payout['gross_amount']);
    final commission = _toDouble(payout['platform_commission']);
    final gst = _toDouble(payout['gst_on_commission']);
    final gatewayFee = _toDouble(payout['payment_gateway_fee']);
    final deduction = _toDouble(payout['deduction_amount']);
    final net = _toDouble(payout['net_amount'] ?? payout['amount']);

    final deductTotal = commission + gst + gatewayFee + deduction;
    final base = gross <= 0 ? (net + deductTotal) : gross;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('How this payout was calculated',
              style: TextStyle(
                color: foodflow.ink,
                fontSize: 15,
                fontWeight: FontWeight.w900,
              )),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: SizedBox(
              height: 14,
              child: Row(
                children: [
                  Expanded(
                    flex: base <= 0 ? 1 : (net.clamp(0, base) * 1000).round(),
                    child: ColoredBox(color: foodflow.success),
                  ),
                  if (deductTotal > 0)
                    Expanded(
                      flex: base <= 0
                          ? 0
                          : (deductTotal.clamp(0, base) * 1000).round(),
                      child: ColoredBox(color: foodflow.danger),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          _moneyRow(context, 'Covered order value', gross),
          _moneyRow(context, 'Platform commission', commission, negative: true),
          _moneyRow(context, 'GST on commission', gst, negative: true),
          _moneyRow(context, 'Payment gateway fee', gatewayFee, negative: true),
          if (deduction > 0)
            _moneyRow(context, 'Other deduction', deduction, negative: true),
          Divider(height: 20, color: foodflow.line),
          _moneyRow(context, 'Net payout to you', net,
              strong: true, color: foodflow.orange),
        ],
      ),
    );
  }
}

class _PayoutOrderCard extends StatelessWidget {
  const _PayoutOrderCard({required this.order, required this.onOpenOrder});

  final Map<String, dynamic> order;
  final ValueChanged<Map<String, dynamic>> onOpenOrder;

  @override
  Widget build(BuildContext context) {
    final items = order['items'] is List ? order['items'] as List : const [];
    final firstItem = items.whereType<Map>().isNotEmpty
        ? Map<String, dynamic>.from(items.whereType<Map>().first)
        : <String, dynamic>{};
    final payout = _toDouble(order['restaurant_earning']);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => onOpenOrder(order),
          child: Ink(
            padding: const EdgeInsets.all(12),
            decoration: _panelDecoration(),
            child: Column(
              children: [
                Row(
                  children: [
                    _MenuThumb(item: firstItem, size: 50),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '#${order['order_number'] ?? order['id'] ?? ''}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: FoodFlowTheme.ink,
                              fontSize: 14,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${items.length} item${items.length == 1 ? '' : 's'} - ${_formatDate(order['delivered_at'] ?? order['created_at'])}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            '${order['customer_name'] ?? 'Customer'}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: FoodFlowTheme.muted,
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
                        Text(
                          formatCurrency(context, payout),
                          style: TextStyle(
                            color: FoodFlowTheme.orange,
                            fontSize: 13,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            _StatusPill(text: '${order['status'] ?? ''}'),
                            const SizedBox(width: 4),
                            Icon(
                              Icons.chevron_right_rounded,
                              color: FoodFlowTheme.muted,
                              size: 18,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ],
                ),
                if (items.isNotEmpty) ...[
                  const SizedBox(height: 11),
                  _ItemStrip(items: items),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ItemStrip extends StatelessWidget {
  const _ItemStrip({required this.items});

  final List<dynamic> items;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = _asMap(items[index]);
          return Container(
            constraints: const BoxConstraints(maxWidth: 176),
            padding: const EdgeInsets.only(right: 8),
            decoration: BoxDecoration(
              color: FoodFlowTheme.canvas,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: FoodFlowTheme.line),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                _MenuThumb(item: item, size: 40),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    '${item['quantity'] ?? 1} x ${item['name'] ?? 'Item'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _MenuThumb extends StatelessWidget {
  const _MenuThumb({required this.item, required this.size});

  final Map<String, dynamic> item;
  final double size;

  @override
  Widget build(BuildContext context) {
    final imageUrl =
        _resolveImageUrl('${item['image_url'] ?? item['image'] ?? ''}');
    final radius = BorderRadius.circular(size * 0.24);
    if (imageUrl.isEmpty) {
      return Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: FoodFlowTheme.orange.withOpacity(0.10),
          borderRadius: radius,
        ),
        child: Icon(
          Icons.restaurant_menu_rounded,
          color: FoodFlowTheme.orange,
          size: size * 0.48,
        ),
      );
    }

    return NetworkImageLoader(
      imageUrl: imageUrl,
      width: size,
      height: size,
      borderRadius: radius,
    );
  }

  String _resolveImageUrl(String raw) {
    final value = raw.trim();
    if (value.isEmpty || value.toLowerCase() == 'null') return '';
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }

    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final origin = '${apiUri.scheme}://${apiUri.host}';
    final normalized = value.startsWith('/') ? value.substring(1) : value;
    if (normalized.startsWith('storage/')) return '$origin/$normalized';
    return '$origin/storage/$normalized';
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          subtitle,
          style: TextStyle(
            color: FoodFlowTheme.muted,
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    final normalized = text.toLowerCase();
    final color = normalized.contains('paid') ||
            normalized.contains('processed') ||
            normalized.contains('delivered')
        ? FoodFlowTheme.success
        : FoodFlowTheme.orange;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.11),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(
        _titleCase(text.replaceAll('_', ' ')),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
      decoration: BoxDecoration(
        color: FoodFlowTheme.canvas,
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: FoodFlowTheme.muted, size: 15),
          const SizedBox(width: 5),
          Text(
            text,
            style: TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _NoOrdersState extends StatelessWidget {
  const _NoOrdersState();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 30),
      decoration: _panelDecoration(),
      child: Column(
        children: [
          Icon(Icons.receipt_long_outlined,
              color: FoodFlowTheme.muted, size: 34),
          SizedBox(height: 10),
          Text(
            'No covered orders',
            style: TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 15,
              fontWeight: FontWeight.w900,
            ),
          ),
          SizedBox(height: 4),
          Text(
            'Manual withdrawals may not be linked to individual orders.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

Widget _moneyRow(
  BuildContext context,
  String label,
  double value, {
  bool negative = false,
  bool strong = false,
  Color? color,
}) {
  final display = negative && value > 0
      ? '-${formatCurrency(context, value)}'
      : formatCurrency(context, value);

  return Padding(
    padding: const EdgeInsets.only(bottom: 9),
    child: Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: strong ? FoodFlowTheme.ink : FoodFlowTheme.muted,
              fontSize: strong ? 13 : 12,
              fontWeight: strong ? FontWeight.w900 : FontWeight.w700,
            ),
          ),
        ),
        Text(
          display,
          style: TextStyle(
            color: color ?? (strong ? FoodFlowTheme.ink : FoodFlowTheme.muted),
            fontSize: strong ? 14 : 12,
            fontWeight: strong ? FontWeight.w900 : FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

BoxDecoration _panelDecoration() {
  return BoxDecoration(
    color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: foodflow.line),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withOpacity(foodflow.isDark ? 0.3 : 0.035),
        blurRadius: 14,
        offset: const Offset(0, 6),
      ),
    ],
  );
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return Map<String, dynamic>.from(value);
  return {};
}

double _toDouble(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse('${value ?? 0}') ?? 0;
}

int? _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? ''}');
}

int? _restaurantIdFrom(Map<String, dynamic> value) {
  final restaurant = _asMap(value['restaurant']);
  return _toInt(
    value['restaurant_id'] ??
        value['restaurantId'] ??
        restaurant['id'] ??
        restaurant['restaurant_id'],
  );
}

String _dateRange(Map<String, dynamic> payout) {
  final start = _tryDate(payout['period_start']);
  final end = _tryDate(payout['period_end']);
  if (start == null || end == null) return 'Settlement period';
  return '${DateFormat('dd MMM').format(start)} - ${DateFormat('dd MMM').format(end)}';
}

String _formatDate(dynamic value) {
  final parsed = _tryDate(value);
  if (parsed == null) return 'Recent';
  return DateFormat('dd MMM, h:mm a').format(parsed);
}

DateTime? _tryDate(dynamic value) {
  final parsed = DateTime.tryParse('${value ?? ''}');
  return parsed?.toLocal();
}

String _titleCase(String value) {
  return value
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => part[0].toUpperCase() + part.substring(1))
      .join(' ');
}
