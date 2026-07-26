import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../config/app_config.dart';
import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_image_loader.dart';

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
  Map<String, dynamic> _payout = {};
  List<Map<String, dynamic>> _orders = [];

  @override
  void initState() {
    super.initState();
    _loadPayout();
  }

  Future<void> _loadPayout() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(
        ApiConstants.walletPayoutDetails(widget.payoutId),
      );
      if (response['success'] == true) {
        final data = _asMap(response['data']);
        final orders =
            data['orders'] is List ? data['orders'] as List : const [];
        if (!mounted) return;
        setState(() {
          _payout = _asMap(data['payout']);
          _orders = orders
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList();
        });
      }
    } catch (e) {
      if (mounted) {
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
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Payout Details'),
        actions: [
          IconButton(
            onPressed: _isLoading ? null : _loadPayout,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        color: FoodFlowTheme.orange,
        onRefresh: _loadPayout,
        child: _isLoading
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 220),
                  Center(child: CircularProgressIndicator()),
                ],
              )
            : ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 26),
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
                    ..._orders.map((order) => _PayoutOrderCard(order: order)),
                ],
              ),
      ),
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
                      style: const TextStyle(
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
                      style: const TextStyle(
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
            style: const TextStyle(
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

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: _panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Payout Bifurcation',
            style: TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 15,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 11),
          _moneyRow(context, 'Covered order value', gross),
          _moneyRow(context, 'Platform commission', commission, negative: true),
          _moneyRow(context, 'GST on commission', gst, negative: true),
          _moneyRow(context, 'Payment gateway fee', gatewayFee, negative: true),
          if (deduction > 0)
            _moneyRow(context, 'Other deduction', deduction, negative: true),
          const Divider(height: 20),
          _moneyRow(
            context,
            'Total payout',
            net,
            strong: true,
            color: FoodFlowTheme.orange,
          ),
        ],
      ),
    );
  }
}

class _PayoutOrderCard extends StatelessWidget {
  const _PayoutOrderCard({required this.order});

  final Map<String, dynamic> order;

  @override
  Widget build(BuildContext context) {
    final items = order['items'] is List ? order['items'] as List : const [];
    final firstItem = items.whereType<Map>().isNotEmpty
        ? Map<String, dynamic>.from(items.whereType<Map>().first)
        : <String, dynamic>{};
    final payout = _toDouble(order['restaurant_earning']);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
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
                      style: const TextStyle(
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
                      style: const TextStyle(
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
                      style: const TextStyle(
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
                  _StatusPill(text: '${order['status'] ?? ''}'),
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
                    style: const TextStyle(
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
          style: const TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          subtitle,
          style: const TextStyle(
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
            style: const TextStyle(
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
      child: const Column(
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
    color: Colors.white,
    borderRadius: BorderRadius.circular(14),
    border: Border.all(color: FoodFlowTheme.line),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withOpacity(0.035),
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
