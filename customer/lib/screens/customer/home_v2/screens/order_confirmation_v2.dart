import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';

import '../../../../config/api_constants.dart';
import '../../../../models/order.dart';
import '../../../../models/scratch_card.dart';
import '../../../../services/api_service.dart';
import '../../../../utils/currency_utils.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';
import 'order_tracking_v2.dart';

/// Animated post-checkout confirmation. Reached via [Navigator.pushReplacement]
/// from [CheckoutV2] so back returns to the feed, not the cart.
class OrderConfirmationV2 extends StatefulWidget {
  const OrderConfirmationV2({super.key, required this.order});

  final Order order;

  @override
  State<OrderConfirmationV2> createState() => _OrderConfirmationV2State();
}

class _OrderConfirmationV2State extends State<OrderConfirmationV2>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..forward();

  List<ScratchCard> _cards = const [];

  @override
  void initState() {
    super.initState();
    _loadScratchCards();
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  Future<void> _loadScratchCards() async {
    try {
      final res = await ApiService().get(ApiConstants.scratchCards);
      final list = v2ExtractList(res);
      final cards = list
          .whereType<Map>()
          .map((e) => ScratchCard.fromJson(Map<String, dynamic>.from(e)))
          .where((c) => c.orderId == null || c.orderId == widget.order.id)
          .toList();
      if (mounted) setState(() => _cards = cards);
    } catch (_) {}
  }

  Animation<double> _step(double begin, double end) => CurvedAnimation(
        parent: _c,
        curve: Interval(begin, end, curve: Curves.easeOutCubic),
      );

  /// One-line payment status for the confirmation card.
  String _paymentLine(Order o) {
    if (o.isCodPayment) return 'Cash on delivery';
    final gw = (o.paymentGateway ?? '').trim();
    final via = gw.isEmpty
        ? ''
        : ' · ${gw[0].toUpperCase()}${gw.substring(1)}';
    if (o.isPaymentPaid) return 'Paid online$via';
    return 'Online payment pending$via';
  }

  @override
  Widget build(BuildContext context) {
    final o = widget.order;
    return V2Scaffold(
      body: Builder(
        builder: (context) {
          final p = V2Theme.of(context);
          return SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
              child: Column(
                children: [
                  const Spacer(),
                  ScaleTransition(
                    scale: CurvedAnimation(
                        parent: _c, curve: Curves.elasticOut),
                    child: SizedBox(
                      width: 160,
                      height: 160,
                      child: Lottie.asset(
                        'assets/animations/success.json',
                        controller: _c,
                        repeat: false,
                        onLoaded: (comp) {
                          _c.duration = comp.duration;
                          _c
                            ..reset()
                            ..forward();
                        },
                        errorBuilder: (_, __, ___) => Container(
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: p.positive.withOpacity(0.14),
                          ),
                          child: Icon(Icons.check_rounded,
                              size: 84, color: p.positive),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  FadeTransition(
                    opacity: _step(0.15, 0.5),
                    child: Text('Order placed!',
                        style: TextStyle(
                            color: p.ink,
                            fontSize: 24,
                            fontWeight: FontWeight.w900)),
                  ),
                  const SizedBox(height: 6),
                  FadeTransition(
                    opacity: _step(0.25, 0.6),
                    child: Text(
                      'Order #${o.orderNumber} · ${o.restaurant?.name ?? 'your restaurant'}',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: p.inkSoft, fontSize: 13.5),
                    ),
                  ),
                  const SizedBox(height: 20),
                  FadeTransition(
                    opacity: _step(0.35, 0.7),
                    child: GlassPanel(
                      radius: 20,
                      strong: true,
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              Icon(Icons.schedule_rounded,
                                  size: 18, color: p.accent),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text('Arriving in about 30–40 min',
                                    style: TextStyle(
                                        color: p.ink,
                                        fontSize: 13.5,
                                        fontWeight: FontWeight.w700)),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Icon(Icons.location_on_rounded,
                                  size: 18, color: p.accent),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(o.deliveryAddress,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                        color: p.inkSoft, fontSize: 12.5)),
                              ),
                            ],
                          ),
                          Divider(color: p.glassBorder, height: 26),
                          Row(
                            children: [
                              Icon(
                                  o.isCodPayment
                                      ? Icons.payments_rounded
                                      : (o.isPaymentPaid
                                          ? Icons.verified_rounded
                                          : Icons.schedule_rounded),
                                  size: 16,
                                  color: o.isPaymentPaid || o.isCodPayment
                                      ? p.positive
                                      : p.warning),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(_paymentLine(o),
                                    style: TextStyle(
                                        color: p.inkSoft, fontSize: 12)),
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              Text(o.isCodPayment ? 'To pay on delivery'
                                  : 'Total paid',
                                  style: TextStyle(
                                      color: p.inkSoft, fontSize: 13)),
                              const Spacer(),
                              Text(formatCurrency(context, o.total),
                                  style: TextStyle(
                                      color: p.ink,
                                      fontSize: 15,
                                      fontWeight: FontWeight.w900)),
                            ],
                          ),
                          if (o.paymentId != null &&
                              o.paymentId!.trim().isNotEmpty) ...[
                            const SizedBox(height: 6),
                            Row(
                              children: [
                                Text('Ref',
                                    style: TextStyle(
                                        color: p.inkFaint, fontSize: 11)),
                                const Spacer(),
                                Text(o.paymentId!,
                                    style: TextStyle(
                                        color: p.inkFaint,
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600)),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                  if (_cards.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    FadeTransition(
                      opacity: _step(0.45, 0.8),
                      child: GlassPanel(
                        radius: 18,
                        onTap: () => v2OpenScratchCards(context),
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          children: [
                            const Text('🎁', style: TextStyle(fontSize: 26)),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                      'You won ${_cards.length} scratch card${_cards.length > 1 ? 's' : ''}!',
                                      style: TextStyle(
                                          color: p.ink,
                                          fontSize: 14,
                                          fontWeight: FontWeight.w900)),
                                  const SizedBox(height: 2),
                                  Text('Tap to scratch and reveal your reward',
                                      style: TextStyle(
                                          color: p.inkSoft, fontSize: 12)),
                                ],
                              ),
                            ),
                            Icon(Icons.chevron_right_rounded,
                                color: p.inkFaint),
                          ],
                        ),
                      ),
                    ),
                  ],
                  const Spacer(),
                  FadeTransition(
                    opacity: _step(0.55, 0.9),
                    child: Row(
                      children: [
                        Expanded(
                          child: V2Tappable(
                            onTap: () => Navigator.of(context)
                                .popUntil((r) => r.isFirst),
                            child: Container(
                              height: 52,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: p.glassTop,
                                borderRadius: BorderRadius.circular(15),
                                border: Border.all(color: p.glassBorder),
                              ),
                              child: Text('Back to home',
                                  style: TextStyle(
                                      color: p.ink,
                                      fontWeight: FontWeight.w800)),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: V2Tappable(
                            onTap: () => Navigator.of(context).pushReplacement(
                              MaterialPageRoute(
                                builder: (_) =>
                                    OrderTrackingV2(orderId: o.id),
                              ),
                            ),
                            child: Container(
                              height: 52,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: p.accent,
                                borderRadius: BorderRadius.circular(15),
                              ),
                              child: const Text('Track order',
                                  style: TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w900)),
                            ),
                          ),
                        ),
                      ],
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
