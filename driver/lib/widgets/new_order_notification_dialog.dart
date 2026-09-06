// lib/widgets/new_order_notification_dialog.dart
import 'dart:async';

import 'package:flutter/material.dart';
import '../models/order.dart';
import '../theme/foodflow_theme.dart';
import '../utils/currency_utils.dart';

class NewOrderNotificationDialog extends StatefulWidget {
  final Order order;
  final FutureOr<void> Function() onAccept;
  final FutureOr<void> Function() onReject;

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
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 420),
  )..forward();
  late final Animation<double> _in =
      CurvedAnimation(parent: _c, curve: Curves.easeOutCubic);

  bool _isAccepting = false;
  bool _isRejecting = false;
  bool get _isBusy => _isAccepting || _isRejecting;

  String _earningText(BuildContext context) {
    final earning = formatCurrency(context, widget.order.driverEarningAmount);
    if (widget.order.driverIncentiveAmount > 0) {
      return '$earning + ${formatCurrency(context, widget.order.driverIncentiveAmount)} incentive';
    }
    return earning;
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    return Dialog(
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.all(16),
      child: FadeTransition(
        opacity: _in,
        child: ScaleTransition(
          scale: Tween<double>(begin: 0.92, end: 1).animate(_in),
          child: Container(
            decoration: BoxDecoration(
              color: foodflow.surfaceColor,
              borderRadius: BorderRadius.circular(24),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.25),
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
                  // Gradient header
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
                    decoration: BoxDecoration(gradient: foodflow.brandGradient),
                    child: Column(
                      children: [
                        _PulseIcon(animation: _c),
                        const SizedBox(height: 14),
                        const Text(
                          'New delivery request',
                          style: TextStyle(
                            fontSize: 19,
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'You earn ${_earningText(context)}',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: Colors.white.withOpacity(0.92),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                'Order #${order.orderNumber}',
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w800,
                                  color: foodflow.ink,
                                ),
                              ),
                            ),
                            _meta(
                              order.isCodPayment
                                  ? Icons.payments_outlined
                                  : Icons.credit_card_outlined,
                              order.isCodPayment ? 'COD' : 'Prepaid',
                            ),
                            const SizedBox(width: 12),
                            _meta(Icons.shopping_bag_outlined,
                                '${order.items.length} item${order.items.length == 1 ? '' : 's'}'),
                          ],
                        ),
                        const SizedBox(height: 14),
                        _row(
                          Icons.storefront_rounded,
                          foodflow.orange,
                          order.restaurant?.name ?? 'Pickup',
                          order.restaurant?.address ?? '',
                        ),
                        Padding(
                          padding: const EdgeInsets.only(left: 15),
                          child: SizedBox(
                            height: 16,
                            child: VerticalDivider(
                              color: foodflow.line,
                              thickness: 2,
                              width: 2,
                            ),
                          ),
                        ),
                        _row(
                          Icons.location_on_rounded,
                          foodflow.success,
                          order.customerName,
                          order.deliveryAddress,
                        ),
                        const SizedBox(height: 18),
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                onPressed: _isBusy ? null : _reject,
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: foodflow.inkSoft,
                                  side: BorderSide(color: foodflow.line),
                                  minimumSize: const Size.fromHeight(48),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(13),
                                  ),
                                ),
                                child: _isRejecting
                                    ? const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2),
                                      )
                                    : const Text('Reject'),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              flex: 2,
                              child: FilledButton(
                                onPressed: _isBusy ? null : _accept,
                                style: FilledButton.styleFrom(
                                  backgroundColor: foodflow.success,
                                  minimumSize: const Size.fromHeight(48),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(13),
                                  ),
                                ),
                                child: _isAccepting
                                    ? const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          color: Colors.white,
                                        ),
                                      )
                                    : const Text('Accept delivery'),
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
        ),
      ),
    );
  }

  Future<void> _accept() async {
    setState(() => _isAccepting = true);
    await widget.onAccept();
    if (!mounted) return;
    Navigator.pop(context);
  }

  Future<void> _reject() async {
    setState(() => _isRejecting = true);
    await widget.onReject();
    if (!mounted) return;
    Navigator.pop(context);
  }

  Widget _row(IconData icon, Color color, String title, String subtitle) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 30,
          height: 30,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: color.withOpacity(0.14),
            borderRadius: BorderRadius.circular(9),
          ),
          child: Icon(icon, size: 16, color: color),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w800,
                  color: foodflow.ink,
                ),
              ),
              if (subtitle.trim().isNotEmpty)
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 12, color: foodflow.muted),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _meta(IconData icon, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: foodflow.faint),
        const SizedBox(width: 4),
        Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w700,
            color: foodflow.muted,
          ),
        ),
      ],
    );
  }
}

class _PulseIcon extends StatelessWidget {
  const _PulseIcon({required this.animation});
  final Animation<double> animation;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: animation,
      builder: (context, _) {
        final t = animation.value;
        return Container(
          width: 60,
          height: 60,
          decoration: BoxDecoration(
            color: Colors.white.withOpacity(0.18),
            shape: BoxShape.circle,
            border: Border.all(
              color: Colors.white.withOpacity(0.35 + 0.25 * t),
              width: 2,
            ),
          ),
          child: const Icon(Icons.notifications_active_rounded,
              color: Colors.white, size: 28),
        );
      },
    );
  }
}
