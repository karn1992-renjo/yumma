import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../models/order.dart';
import '../../../../providers/order_provider.dart';
import '../../../../utils/currency_utils.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class OrderDetailV2 extends StatefulWidget {
  const OrderDetailV2({
    super.key,
    required this.orderId,
    this.justPlaced = false,
  });

  final int orderId;

  /// Shows a "placed" success banner (used right after checkout, until the
  /// animated confirmation screen lands in Batch B).
  final bool justPlaced;

  @override
  State<OrderDetailV2> createState() => _OrderDetailV2State();
}

class _OrderDetailV2State extends State<OrderDetailV2> {
  bool _loading = true;
  Order? _order;

  static const _flow = [
    ('pending', 'Order placed'),
    ('confirmed', 'Confirmed'),
    ('preparing', 'Preparing'),
    ('ready_for_pickup', 'Ready'),
    ('picked_up', 'Picked up'),
    ('on_the_way', 'On the way'),
    ('delivered', 'Delivered'),
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final o = await context
          .read<OrderProvider>()
          .fetchOrderDetails(widget.orderId);
      if (mounted) setState(() => _order = o);
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  int get _activeStep {
    final s = _order?.status ?? '';
    if (s == 'cancelled') return -1;
    final idx = _flow.indexWhere((e) => e.$1 == s);
    return idx < 0 ? 0 : idx;
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final o = _order;
    return V2Scaffold(
      showBack: true,
      title: o == null ? 'Order' : 'Order #${o.orderNumber}',
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : o == null
              ? V2EmptyState(
                  icon: Icons.cloud_off_rounded,
                  title: 'Could not load order',
                  onRetry: _load,
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  color: p.accent,
                  backgroundColor: p.bgMid,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
                    children: [
                      if (widget.justPlaced) ...[
                        GlassPanel(
                          radius: 20,
                          padding: const EdgeInsets.all(16),
                          child: Row(
                            children: [
                              Icon(Icons.check_circle_rounded,
                                  color: p.positive, size: 26),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.start,
                                  children: [
                                    Text('Order placed',
                                        style: TextStyle(
                                            color: p.ink,
                                            fontSize: 15,
                                            fontWeight: FontWeight.w900)),
                                    const SizedBox(height: 2),
                                    Text(
                                        'The restaurant will confirm shortly.',
                                        style: TextStyle(
                                            color: p.inkSoft,
                                            fontSize: 12.5)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),
                      ],
                      GlassPanel(
                        radius: 22,
                        strong: true,
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              o.statusText,
                              style: TextStyle(
                                color: p.ink,
                                fontSize: 17,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 14),
                            if (o.status == 'cancelled')
                              Text('This order was cancelled.',
                                  style: TextStyle(
                                      color: p.danger,
                                      fontWeight: FontWeight.w600))
                            else
                              _Timeline(active: _activeStep, flow: _flow),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                      GlassPanel(
                        radius: 20,
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Items',
                                style: TextStyle(
                                    color: p.inkSoft,
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 0.4)),
                            const SizedBox(height: 10),
                            for (final it in o.items)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 8),
                                child: Row(
                                  children: [
                                    Text('${it.quantity}×',
                                        style: TextStyle(
                                            color: p.accent,
                                            fontWeight: FontWeight.w800)),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Text(it.name,
                                          style: TextStyle(color: p.ink)),
                                    ),
                                    Text(
                                        formatCurrency(
                                            context, it.price * it.quantity),
                                        style: TextStyle(
                                            color: p.inkSoft,
                                            fontWeight: FontWeight.w700)),
                                  ],
                                ),
                              ),
                            Divider(color: p.glassBorder, height: 22),
                            _row(context, 'Subtotal', o.subtotal),
                            if (o.deliveryFee > 0)
                              _row(context, 'Delivery', o.deliveryFee),
                            if (o.discount > 0)
                              _row(context, 'Discount', -o.discount),
                            const SizedBox(height: 6),
                            _row(context, 'Total', o.total, bold: true),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                      GlassPanel(
                        radius: 20,
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Delivering to',
                                style: TextStyle(
                                    color: p.inkSoft,
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 0.4)),
                            const SizedBox(height: 8),
                            Text(o.deliveryAddress,
                                style: TextStyle(color: p.ink, fontSize: 13)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }

  Widget _row(BuildContext context, String label, double value,
      {bool bold = false}) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        children: [
          Expanded(
            child: Text(label,
                style: TextStyle(
                  color: bold ? p.ink : p.inkSoft,
                  fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
                  fontSize: bold ? 14 : 12.5,
                )),
          ),
          Text(formatCurrency(context, value),
              style: TextStyle(
                color: bold ? p.ink : p.inkSoft,
                fontWeight: bold ? FontWeight.w800 : FontWeight.w700,
                fontSize: bold ? 14 : 12.5,
              )),
        ],
      ),
    );
  }
}

class _Timeline extends StatelessWidget {
  const _Timeline({required this.active, required this.flow});

  final int active;
  final List<(String, String)> flow;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Column(
      children: [
        for (var i = 0; i < flow.length; i++)
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Column(
                  children: [
                    Container(
                      width: 16,
                      height: 16,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: i <= active ? p.accent : Colors.transparent,
                        border: Border.all(
                          color: i <= active ? p.accent : p.glassBorder,
                          width: 2,
                        ),
                      ),
                      child: i <= active
                          ? const Icon(Icons.check,
                              size: 10, color: Colors.white)
                          : null,
                    ),
                    if (i != flow.length - 1)
                      Expanded(
                        child: Container(
                          width: 2,
                          color: i < active ? p.accent : p.glassBorder,
                        ),
                      ),
                  ],
                ),
                const SizedBox(width: 12),
                Padding(
                  padding: EdgeInsets.only(
                      bottom: i == flow.length - 1 ? 0 : 16),
                  child: Text(
                    flow[i].$2,
                    style: TextStyle(
                      color: i <= active ? p.ink : p.inkFaint,
                      fontWeight:
                          i == active ? FontWeight.w800 : FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
