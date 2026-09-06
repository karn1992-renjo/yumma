import 'dart:async';

import 'package:flutter/material.dart';

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import '../../../../utils/currency_utils.dart';
import '../theme/v2_theme.dart';
import 'v2_anim.dart';
import 'v2_glass.dart';

/// V2 glass rendering of the cancelled-order "food rescue" flash resale offer.
/// Same payload + claim endpoint as the production [FlashResaleAlertService];
/// only the presentation differs. Inserted as an [OverlayEntry] by that service
/// when the V2 home experience is active.
class V2FlashResaleOverlay extends StatefulWidget {
  const V2FlashResaleOverlay({
    super.key,
    required this.data,
    required this.orderId,
    required this.expiresAt,
    required this.onClose,
    required this.onClaimed,
  });

  final Map<String, dynamic> data;
  final int orderId;
  final DateTime? expiresAt;
  final VoidCallback onClose;
  final VoidCallback onClaimed;

  @override
  State<V2FlashResaleOverlay> createState() => _V2FlashResaleOverlayState();
}

class _V2FlashResaleOverlayState extends State<V2FlashResaleOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _float = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 2),
  )..repeat(reverse: true);

  bool _claiming = false;
  String? _result;
  bool _ok = false;

  @override
  void dispose() {
    _float.dispose();
    super.dispose();
  }

  Future<void> _claim() async {
    if (_claiming) return;
    setState(() => _claiming = true);
    try {
      await ApiService().post(ApiConstants.claimFlashResale(widget.orderId));
      if (!mounted) return;
      setState(() {
        _ok = true;
        _result = 'Claimed! Your order is on its way.';
      });
      Future.delayed(const Duration(seconds: 2), widget.onClaimed);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _ok = false;
        _result = e is ApiException
            ? e.message
            : 'Someone else may have already claimed this.';
      });
    } finally {
      if (mounted) setState(() => _claiming = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Palette.of(v2ModeNotifier.value);
    final name =
        (widget.data['restaurant_name'] ?? 'A nearby restaurant').toString();
    final resale =
        double.tryParse((widget.data['resale_price'] ?? '').toString());
    final original =
        double.tryParse((widget.data['original_price'] ?? '').toString());
    final bottomInset = MediaQuery.of(context).padding.bottom;

    return Positioned.fill(
      child: V2Theme(
      palette: p,
      child: Material(
        color: Colors.transparent,
        child: Stack(
          children: [
            Positioned.fill(
              child: GestureDetector(
                onTap: widget.onClose,
                child: Container(color: Colors.black.withOpacity(0.72)),
              ),
            ),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: SafeArea(
                top: false,
                child: Padding(
                  padding: EdgeInsets.fromLTRB(14, 14, 14, 14 + bottomInset),
                  child: GlassPanel(
                    radius: 26,
                    strong: true,
                    padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            AnimatedBuilder(
                              animation: _float,
                              builder: (_, child) => Transform.translate(
                                offset: Offset(0, -6 + 12 * _float.value),
                                child: child,
                              ),
                              child: Container(
                                width: 46,
                                height: 46,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: p.warning.withOpacity(0.16),
                                ),
                                child: Icon(Icons.shopping_bag_rounded,
                                    color: p.warning),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('An order was just cancelled',
                                      style: TextStyle(
                                          color: p.ink,
                                          fontSize: 15,
                                          fontWeight: FontWeight.w900)),
                                  Text(name,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                          color: p.accent,
                                          fontSize: 12.5,
                                          fontWeight: FontWeight.w700)),
                                ],
                              ),
                            ),
                            GestureDetector(
                              onTap: widget.onClose,
                              child: Icon(Icons.close_rounded,
                                  color: p.inkFaint, size: 20),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.baseline,
                          textBaseline: TextBaseline.alphabetic,
                          children: [
                            Text('Claim at just ',
                                style: TextStyle(
                                    color: p.inkSoft, fontSize: 14)),
                            if (original != null)
                              Padding(
                                padding: const EdgeInsets.only(right: 6),
                                child: Text(
                                    formatCurrency(context, original),
                                    style: TextStyle(
                                        color: p.inkFaint,
                                        fontSize: 13,
                                        decoration:
                                            TextDecoration.lineThrough)),
                              ),
                            Text(formatCurrency(context, resale ?? 0),
                                style: TextStyle(
                                    color: p.ink,
                                    fontSize: 20,
                                    fontWeight: FontWeight.w900)),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            _Badge(text: 'Freshly prepared', p: p),
                            const SizedBox(width: 10),
                            _Badge(text: 'Safely sealed', p: p),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Icon(Icons.access_time_filled_rounded,
                                size: 15, color: p.inkFaint),
                            const SizedBox(width: 5),
                            _Countdown(expiresAt: widget.expiresAt, p: p),
                            const Spacer(),
                            if (_result != null)
                              Flexible(
                                child: Text(_result!,
                                    textAlign: TextAlign.right,
                                    style: TextStyle(
                                        color: _ok ? p.positive : p.danger,
                                        fontWeight: FontWeight.w700,
                                        fontSize: 12.5)),
                              )
                            else
                              V2Tappable(
                                onTap: _claiming ? null : _claim,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 22, vertical: 11),
                                  decoration: BoxDecoration(
                                    color: p.positive,
                                    borderRadius: BorderRadius.circular(24),
                                  ),
                                  child: _claiming
                                      ? const SizedBox(
                                          width: 16,
                                          height: 16,
                                          child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                              color: Colors.white),
                                        )
                                      : const Text('Claim now',
                                          style: TextStyle(
                                              color: Colors.white,
                                              fontWeight: FontWeight.w900,
                                              fontSize: 13.5)),
                                ),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.text, required this.p});
  final String text;
  final V2Palette p;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.check_circle_rounded, size: 15, color: p.positive),
        const SizedBox(width: 4),
        Text(text,
            style: TextStyle(
                color: p.inkSoft,
                fontSize: 12,
                fontWeight: FontWeight.w600)),
      ],
    );
  }
}

class _Countdown extends StatelessWidget {
  const _Countdown({required this.expiresAt, required this.p});
  final DateTime? expiresAt;
  final V2Palette p;

  @override
  Widget build(BuildContext context) {
    if (expiresAt == null) {
      return Text('Claim it now',
          style: TextStyle(
              color: p.inkFaint, fontSize: 12, fontWeight: FontWeight.w600));
    }
    return StreamBuilder<int>(
      stream: Stream.periodic(const Duration(seconds: 1), (v) => v),
      builder: (context, _) {
        final remaining = expiresAt!.difference(DateTime.now());
        final m = remaining.isNegative ? 0 : remaining.inMinutes;
        final s = remaining.isNegative ? 0 : remaining.inSeconds % 60;
        return Text('$m:${s.toString().padLeft(2, '0')} left',
            style: TextStyle(
                color: p.warning,
                fontSize: 12,
                fontWeight: FontWeight.w800));
      },
    );
  }
}
