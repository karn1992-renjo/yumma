import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../models/order.dart';
import '../../../../providers/order_provider.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import 'v2_anim.dart';
import 'v2_glass.dart';

const _activeStatuses = {
  'pending',
  'confirmed',
  'preparing',
  'ready_for_pickup',
  'reached_pickup',
  'picked_up',
  'on_the_way',
};

/// Floating "track your order" strip. Shows one active order, or a swipeable
/// carousel with dots when there are several.
class V2OrderTrackerBar extends StatefulWidget {
  const V2OrderTrackerBar({super.key});

  @override
  State<V2OrderTrackerBar> createState() => _V2OrderTrackerBarState();
}

class _V2OrderTrackerBarState extends State<V2OrderTrackerBar> {
  final PageController _page = PageController();
  int _index = 0;
  Timer? _poll;
  final Set<int> _dismissed = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
    _poll = Timer.periodic(const Duration(seconds: 20), (_) => _refresh());
  }

  void _refresh() {
    if (!mounted) return;
    // Defer so we never notifyListeners() during a build.
    Future.microtask(() {
      if (!mounted) return;
      context
          .read<OrderProvider>()
          .fetchMyOrders()
          .catchError((_) => <Order>[]);
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    _page.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<OrderProvider>(
      builder: (context, provider, _) {
        final active = provider.orders
            .where((o) =>
                !o.isDelivered &&
                !o.isCancelled &&
                !_dismissed.contains(o.id) &&
                _activeStatuses.contains(o.status))
            .toList();
        if (active.isEmpty) return const SizedBox.shrink();
        if (_index >= active.length) _index = 0;
        final p = V2Theme.of(context);
        final multi = active.length > 1;
        return V2Entrance(
          offset: 12,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                height: 64,
                child: multi
                    ? PageView.builder(
                        controller: _page,
                        onPageChanged: (i) => setState(() => _index = i),
                        itemCount: active.length,
                        itemBuilder: (_, i) => _Card(
                          order: active[i],
                          index: i + 1,
                          total: active.length,
                          onDismiss: () =>
                              setState(() => _dismissed.add(active[i].id)),
                        ),
                      )
                    : _Card(
                        order: active.first,
                        index: 1,
                        total: 1,
                        onDismiss: () =>
                            setState(() => _dismissed.add(active.first.id)),
                      ),
              ),
              if (multi) ...[
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    for (var i = 0; i < active.length; i++)
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        margin: const EdgeInsets.symmetric(horizontal: 3),
                        width: i == _index ? 16 : 6,
                        height: 6,
                        decoration: BoxDecoration(
                          color: i == _index
                              ? p.accent
                              : p.inkFaint.withOpacity(0.4),
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                  ],
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({
    required this.order,
    required this.index,
    required this.total,
    required this.onDismiss,
  });

  final Order order;
  final int index;
  final int total;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: GlassPanel(
        radius: 18,
        strong: true,
        onTap: () => v2OpenTracking(context, order.id),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: p.accent.withOpacity(0.14),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(Icons.delivery_dining_rounded,
                  size: 20, color: p.accent),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Text('Order #${order.orderNumber}',
                          style: TextStyle(
                              color: p.ink,
                              fontSize: 12.5,
                              fontWeight: FontWeight.w800)),
                      if (total > 1) ...[
                        const SizedBox(width: 6),
                        Text('$index/$total',
                            style: TextStyle(
                                color: p.inkFaint, fontSize: 11)),
                      ],
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(order.statusText,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: p.accent, fontSize: 12)),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, size: 20),
            V2Tappable(
              onTap: onDismiss,
              child: Padding(
                padding: const EdgeInsets.only(left: 4),
                child: Icon(Icons.close_rounded,
                    size: 16, color: p.inkFaint),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
