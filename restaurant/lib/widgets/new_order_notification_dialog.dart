// lib/widgets/new_order_notification_dialog.dart
import 'package:flutter/material.dart';
import '../models/order.dart';
import '../theme/foodflow_theme.dart';
import '../utils/currency_utils.dart';

class NewOrderNotificationDialog extends StatefulWidget {
  final Order order;
  final ValueChanged<int> onAccept;
  final VoidCallback onReject;

  const NewOrderNotificationDialog({
    Key? key,
    required this.order,
    required this.onAccept,
    required this.onReject,
  }) : super(key: key);

  @override
  State<NewOrderNotificationDialog> createState() =>
      _NewOrderNotificationDialogState();
}

class _NewOrderNotificationDialogState extends State<NewOrderNotificationDialog>
    with SingleTickerProviderStateMixin {
  static const int _minPrepMinutes = 5;
  static const int _maxPrepMinutes = 60;
  static const _presets = [10, 15, 20, 30, 45];

  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);
  int _preparationMinutes = 20;

  @override
  void initState() {
    super.initState();
    _preparationMinutes = (widget.order.preparationTimeMinutes ?? 20)
        .clamp(_minPrepMinutes, _maxPrepMinutes)
        .toInt();
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  double get _orderTotal {
    final o = widget.order;
    if (o.total > 0) return o.total;
    return o.items.fold<double>(0, (s, i) => s + i.price * i.quantity);
  }

  @override
  Widget build(BuildContext context) {
    final o = widget.order;
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 24),
      child: Container(
        decoration: BoxDecoration(
          color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
          borderRadius: BorderRadius.circular(26),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.28),
              blurRadius: 40,
              offset: const Offset(0, 16),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // ---- alert banner ----
              Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(22, 22, 22, 20),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [foodflow.orange, foodflow.orangeDark],
                  ),
                ),
                child: Column(
                  children: [
                    AnimatedBuilder(
                      animation: _pulse,
                      builder: (context, child) => Container(
                        width: 66,
                        height: 66,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(
                              0.14 + 0.12 * _pulse.value),
                          shape: BoxShape.circle,
                        ),
                        child: child,
                      ),
                      child: const Icon(Icons.notifications_active_rounded,
                          color: Colors.white, size: 32),
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      'NEW ORDER',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '#${o.orderNumber}  -  ${formatCurrency(context, _orderTotal)}',
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.9),
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),

              Padding(
                padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // customer + address
                    Container(
                      padding: const EdgeInsets.all(13),
                      decoration: BoxDecoration(
                        color: foodflow.isDark
                            ? foodflow.surfaceColor
                            : foodflow.canvas,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: foodflow.line),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.person_outline,
                                  size: 16, color: foodflow.orange),
                              const SizedBox(width: 7),
                              Expanded(
                                child: Text(
                                  o.customerName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    color: foodflow.ink,
                                    fontSize: 14,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                              Text(
                                '${o.items.length} ${o.items.length == 1 ? 'item' : 'items'}',
                                style: TextStyle(
                                  color: foodflow.muted,
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Icon(Icons.location_on_outlined,
                                  size: 16, color: foodflow.muted),
                              const SizedBox(width: 7),
                              Expanded(
                                child: Text(
                                  o.deliveryAddress,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    color: foodflow.muted,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 10),
                    // items
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxHeight: 150),
                      child: ListView.builder(
                        shrinkWrap: true,
                        itemCount: o.items.length,
                        itemBuilder: (context, i) {
                          final it = o.items[i];
                          return Padding(
                            padding: const EdgeInsets.symmetric(vertical: 5),
                            child: Row(
                              children: [
                                Text(
                                  '${it.quantity}x',
                                  style: TextStyle(
                                    color: foodflow.orange,
                                    fontSize: 13,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    it.name,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      color: foodflow.ink,
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                                Text(
                                  formatCurrency(context, it.price * it.quantity),
                                  style: TextStyle(
                                    color: foodflow.ink,
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ),
                    const SizedBox(height: 14),
                    _prepSelector(),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () {
                              Navigator.pop(context);
                              widget.onReject();
                            },
                            style: OutlinedButton.styleFrom(
                              foregroundColor: foodflow.danger,
                              side: BorderSide(
                                  color: foodflow.danger.withOpacity(0.5)),
                              padding: const EdgeInsets.symmetric(vertical: 15),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                              textStyle: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            child: const Text('Reject'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          flex: 2,
                          child: ElevatedButton(
                            onPressed: () {
                              Navigator.pop(context);
                              widget.onAccept(_preparationMinutes);
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: foodflow.orange,
                              foregroundColor: Colors.white,
                              elevation: 0,
                              padding: const EdgeInsets.symmetric(vertical: 15),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                              textStyle: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            child: Text('Accept - $_preparationMinutes min'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _prepSelector() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(Icons.timer_outlined, size: 15, color: foodflow.muted),
            const SizedBox(width: 6),
            Text(
              'Ready in',
              style: TextStyle(
                color: foodflow.muted,
                fontSize: 12,
                fontWeight: FontWeight.w900,
                letterSpacing: 0.4,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final m in _presets)
              GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => setState(() => _preparationMinutes = m),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: _preparationMinutes == m
                        ? foodflow.orange
                        : (foodflow.isDark
                            ? foodflow.surfaceColor
                            : Colors.white),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: _preparationMinutes == m
                          ? foodflow.orange
                          : foodflow.line,
                    ),
                  ),
                  child: Text(
                    '$m min',
                    style: TextStyle(
                      color: _preparationMinutes == m
                          ? Colors.white
                          : foodflow.ink,
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}
