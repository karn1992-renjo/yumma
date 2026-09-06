import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../models/order.dart';
import '../../../../providers/order_provider.dart';
import '../../../../utils/currency_utils.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class OrdersV2 extends StatefulWidget {
  const OrdersV2({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<OrdersV2> createState() => _OrdersV2State();
}

class _OrdersV2State extends State<OrdersV2> {
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      await context.read<OrderProvider>().fetchMyOrders();
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  static const _running = {
    'pending',
    'confirmed',
    'preparing',
    'ready_for_pickup',
    'reached_pickup',
    'picked_up',
    'on_the_way',
  };

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final body = Consumer<OrderProvider>(
      builder: (context, provider, _) {
        final orders = provider.orders;
        if (_loading && orders.isEmpty) {
          return Center(child: CircularProgressIndicator(color: p.accent));
        }
        if (orders.isEmpty) {
          return const V2EmptyState(
            icon: Icons.receipt_long_rounded,
            title: 'No orders yet',
            message: 'Your past and active orders will show up here.',
          );
        }
        final active =
            orders.where((o) => _running.contains(o.status)).toList();
        final past = orders.where((o) => !_running.contains(o.status)).toList();
        return RefreshIndicator(
          onRefresh: _load,
          color: p.accent,
          backgroundColor: p.bgMid,
          child: ListView(
            padding: EdgeInsets.fromLTRB(
                16, 8, 16, MediaQuery.of(context).padding.bottom + 120),
            children: [
              if (active.isNotEmpty) ...[
                _label(context, 'Active'),
                for (var i = 0; i < active.length; i++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: V2Entrance(
                      delay: Duration(milliseconds: 30 * i),
                      child: _OrderCard(order: active[i], highlight: true),
                    ),
                  ),
                const SizedBox(height: 10),
              ],
              if (past.isNotEmpty) ...[
                _label(context, 'History'),
                for (var i = 0; i < past.length; i++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: V2Entrance(
                      delay: Duration(milliseconds: 24 * i.clamp(0, 8)),
                      child: _OrderCard(order: past[i]),
                    ),
                  ),
              ],
            ],
          ),
        );
      },
    );

    if (widget.embedded) {
      return Padding(
        padding: const EdgeInsets.only(top: 8),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 6, 18, 10),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Orders',
                  style: TextStyle(
                    color: p.ink,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.3,
                  ),
                ),
              ),
            ),
            Expanded(child: body),
          ],
        ),
      );
    }
    return V2Scaffold(title: 'Orders', showBack: true, body: body);
  }

  Widget _label(BuildContext context, String t) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 6, 0, 10),
      child: Text(
        t.toUpperCase(),
        style: TextStyle(
          color: p.inkFaint,
          fontSize: 11,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.8,
        ),
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order, this.highlight = false});

  final Order order;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final itemCount =
        order.items.fold<int>(0, (sum, it) => sum + it.quantity);
    return GlassPanel(
      radius: 20,
      strong: highlight,
      onTap: () => (order.isDelivered || order.isCancelled)
          ? v2OpenOrder(context, order.id)
          : v2OpenTracking(context, order.id),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Order #${order.orderNumber}',
                  style: TextStyle(
                    color: p.ink,
                    fontSize: 14.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: (highlight ? p.accent : p.positive)
                      .withOpacity(p.isDark ? 0.20 : 0.13),
                  borderRadius: BorderRadius.circular(7),
                  border: Border.all(
                    color: (highlight ? p.accent : p.positive).withOpacity(0.5),
                  ),
                ),
                child: Text(
                  order.statusText,
                  style: TextStyle(
                    color: p.ink,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            '$itemCount item${itemCount == 1 ? '' : 's'} · ${formatCurrency(context, order.total)}',
            style: TextStyle(
              color: p.inkSoft,
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            _date(order.createdAt),
            style: TextStyle(color: p.inkFaint, fontSize: 11),
          ),
        ],
      ),
    );
  }

  String _date(DateTime d) {
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    final h = d.hour % 12 == 0 ? 12 : d.hour % 12;
    final m = d.minute.toString().padLeft(2, '0');
    final ap = d.hour < 12 ? 'AM' : 'PM';
    return '${d.day} ${months[d.month - 1]}, $h:$m $ap';
  }
}
