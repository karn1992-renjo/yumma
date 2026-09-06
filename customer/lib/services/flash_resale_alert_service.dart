import 'dart:async';

import 'package:flutter/material.dart';

import '../config/api_constants.dart';
import '../screens/customer/home_experience.dart';
import '../screens/customer/home_v2/widgets/v2_flash_resale.dart';
import 'api_service.dart';
import 'navigation_service.dart';

/// Shows a full-screen "food rescue" flash-resale offer for undelivered food
/// nearby, styled after Zomato's cancelled-order rescue popup: a dimmed
/// backdrop, a floating rescue-bag graphic, and a bottom sheet with the
/// discounted price, trust badges, an ETA, a claim button, and a countdown
/// bar. Dismissible (X or backdrop tap) -- a customer shouldn't be forced to
/// respond to a food ad.
class FlashResaleAlertService {
  FlashResaleAlertService._();

  static final FlashResaleAlertService instance = FlashResaleAlertService._();

  OverlayEntry? _entry;
  Timer? _dismissTimer;
  int? _currentOrderId;

  void show(Map<String, dynamic> data) {
    final context = appNavigatorKey.currentState?.overlay?.context;
    if (context == null) return;

    final orderId = int.tryParse((data['order_id'] ?? '').toString());
    if (orderId == null) return;

    final expiresAt = DateTime.tryParse((data['expires_at'] ?? '').toString());

    _dismissCurrent();
    _currentOrderId = orderId;

    _entry = OverlayEntry(
      builder: (_) => homeV2Enabled.value
          ? V2FlashResaleOverlay(
              data: data,
              orderId: orderId,
              expiresAt: expiresAt,
              onClose: _dismissCurrent,
              onClaimed: _dismissCurrent,
            )
          : _FlashResaleOverlay(
              data: data,
              orderId: orderId,
              expiresAt: expiresAt,
              onClose: _dismissCurrent,
              onClaimed: _dismissCurrent,
            ),
    );

    Overlay.of(context, rootOverlay: true).insert(_entry!);

    final remaining = expiresAt != null
        ? expiresAt.difference(DateTime.now())
        : const Duration(minutes: 2);
    _dismissTimer = Timer(
      remaining.isNegative ? Duration.zero : remaining,
      _dismissCurrent,
    );
  }

  /// Auto-hides the overlay if it's currently showing this exact order --
  /// used when a push tells us someone else claimed it (or it expired)
  /// while this customer still has it open. A no-op if they've already
  /// dismissed it or it's showing a different order.
  void hideIfOrder(int orderId, {String? message}) {
    if (_currentOrderId != orderId) return;
    _dismissCurrent();
    if (message != null) {
      _showToast(message);
    }
  }

  void _showToast(String message) {
    final overlay = appNavigatorKey.currentState?.overlay;
    if (overlay == null) return;

    late final OverlayEntry toastEntry;
    toastEntry = OverlayEntry(
      builder: (context) => Positioned(
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).padding.bottom + 24,
        child: Material(
          color: Colors.transparent,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: const Color(0xFF1C1C1C),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontSize: 13.5),
            ),
          ),
        ),
      ),
    );

    overlay.insert(toastEntry);
    Timer(const Duration(seconds: 3), () {
      toastEntry.remove();
    });
  }

  void _dismissCurrent() {
    _dismissTimer?.cancel();
    _dismissTimer = null;
    _entry?.remove();
    _entry = null;
    _currentOrderId = null;
  }
}

class _FlashResaleOverlay extends StatefulWidget {
  final Map<String, dynamic> data;
  final int orderId;
  final DateTime? expiresAt;
  final VoidCallback onClose;
  final VoidCallback onClaimed;

  const _FlashResaleOverlay({
    required this.data,
    required this.orderId,
    required this.expiresAt,
    required this.onClose,
    required this.onClaimed,
  });

  @override
  State<_FlashResaleOverlay> createState() => _FlashResaleOverlayState();
}

class _FlashResaleOverlayState extends State<_FlashResaleOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _floatController;
  late final Animation<double> _floatOffset;
  late final Animation<double> _floatTilt;

  bool _claiming = false;
  String? _resultMessage;
  bool _resultSuccess = false;

  @override
  void initState() {
    super.initState();
    _floatController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 2),
    )..repeat(reverse: true);
    _floatOffset = Tween<double>(begin: -12, end: 12).animate(
      CurvedAnimation(parent: _floatController, curve: Curves.easeInOut),
    );
    _floatTilt = Tween<double>(begin: -0.035, end: 0.035).animate(
      CurvedAnimation(parent: _floatController, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _floatController.dispose();
    super.dispose();
  }

  Future<void> _claim() async {
    if (_claiming) return;
    setState(() => _claiming = true);

    try {
      await ApiService().post(ApiConstants.claimFlashResale(widget.orderId));
      if (!mounted) return;
      setState(() {
        _resultSuccess = true;
        _resultMessage = 'Claimed! Your order is on its way.';
      });
      Future.delayed(const Duration(seconds: 2), widget.onClaimed);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _resultSuccess = false;
        _resultMessage = e is ApiException
            ? e.message
            : 'Someone else may have already claimed this.';
      });
    } finally {
      if (mounted) setState(() => _claiming = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final restaurantName =
        (widget.data['restaurant_name'] ?? 'A nearby restaurant').toString();
    final resalePrice =
        double.tryParse((widget.data['resale_price'] ?? '').toString());
    final originalPrice =
        double.tryParse((widget.data['original_price'] ?? '').toString());
    final bottomInset = MediaQuery.of(context).padding.bottom;

    return Positioned.fill(
      child: Material(
        color: Colors.transparent,
        child: Stack(
          children: [
            Positioned.fill(
              child: GestureDetector(
                onTap: widget.onClose,
                child: Container(color: Colors.black.withOpacity(0.78)),
              ),
            ),
            Positioned.fill(
              child: Column(
                children: [
                  Expanded(
                    child: Align(
                      alignment: const Alignment(0, -0.2),
                      child: AnimatedBuilder(
                        animation: _floatController,
                        builder: (context, child) => Transform.translate(
                          offset: Offset(0, _floatOffset.value),
                          child: Transform.rotate(
                            angle: _floatTilt.value,
                            child: child,
                          ),
                        ),
                        child: Container(
                          width: 220,
                          height: 220,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            gradient: RadialGradient(
                              colors: [
                                const Color(0xFFFFB300).withOpacity(0.35),
                                const Color(0xFFFFB300).withOpacity(0.0),
                              ],
                            ),
                          ),
                          child: Center(
                            child: Image.asset(
                              'assets/images/food-rescue.png',
                              width: 168,
                              errorBuilder: (context, error, stack) =>
                                  const Icon(
                                Icons.shopping_bag_rounded,
                                size: 96,
                                color: Colors.white70,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  Container(
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.vertical(
                        top: Radius.circular(24),
                      ),
                    ),
                    padding: const EdgeInsets.fromLTRB(20, 22, 20, 18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text(
                          'An order was just cancelled.',
                          style: TextStyle(
                            fontSize: 14.5,
                            color: Color(0xFF6B6772),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          restaurantName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 12.5,
                            color: Color(0xFFFF5A1F),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.baseline,
                          textBaseline: TextBaseline.alphabetic,
                          children: [
                            const Text(
                              'Claim at just ',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF26242B),
                              ),
                            ),
                            if (originalPrice != null)
                              Padding(
                                padding: const EdgeInsets.only(right: 6),
                                child: Text(
                                  '₹${originalPrice.toStringAsFixed(2)}',
                                  style: const TextStyle(
                                    fontSize: 14,
                                    decoration: TextDecoration.lineThrough,
                                    color: Color(0xFF8B8791),
                                  ),
                                ),
                              ),
                            Text(
                              '₹${(resalePrice ?? 0).toStringAsFixed(2)}',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF26242B),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        const Row(
                          children: [
                            Icon(
                              Icons.check_circle,
                              size: 16,
                              color: Color(0xFF2E8B57),
                            ),
                            SizedBox(width: 4),
                            Text(
                              'Freshly prepared',
                              style: TextStyle(
                                fontSize: 12.5,
                                color: Color(0xFF4A4A4A),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            SizedBox(width: 16),
                            Icon(
                              Icons.check_circle,
                              size: 16,
                              color: Color(0xFF2E8B57),
                            ),
                            SizedBox(width: 4),
                            Text(
                              'Safely sealed',
                              style: TextStyle(
                                fontSize: 12.5,
                                color: Color(0xFF4A4A4A),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            const Icon(
                              Icons.access_time_filled_rounded,
                              size: 16,
                              color: Color(0xFF6B6772),
                            ),
                            const SizedBox(width: 4),
                            const Text(
                              '10-15 mins',
                              style: TextStyle(
                                fontSize: 12.5,
                                color: Color(0xFF6B6772),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const Spacer(),
                            if (_resultMessage != null)
                              Flexible(
                                child: Text(
                                  _resultMessage!,
                                  textAlign: TextAlign.right,
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: _resultSuccess
                                        ? const Color(0xFF2E8B57)
                                        : const Color(0xFFB4472F),
                                  ),
                                ),
                              )
                            else
                              ElevatedButton(
                                onPressed: _claiming ? null : _claim,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF2E8B57),
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 20,
                                    vertical: 11,
                                  ),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(24),
                                  ),
                                  elevation: 0,
                                ),
                                child: _claiming
                                    ? const SizedBox(
                                        width: 16,
                                        height: 16,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          color: Colors.white,
                                        ),
                                      )
                                    : const Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Text(
                                            'View cart',
                                            style: TextStyle(
                                              fontWeight: FontWeight.w800,
                                              fontSize: 13.5,
                                            ),
                                          ),
                                          Icon(
                                            Icons.chevron_right_rounded,
                                            size: 18,
                                          ),
                                        ],
                                      ),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  Container(
                    width: double.infinity,
                    color: const Color(0xFF1C1C1C),
                    padding: EdgeInsets.fromLTRB(
                      16,
                      10,
                      16,
                      10 + bottomInset,
                    ),
                    child: Center(
                      child: _CountdownLabel(expiresAt: widget.expiresAt),
                    ),
                  ),
                ],
              ),
            ),
            Positioned(
              top: MediaQuery.of(context).padding.top + 8,
              right: 12,
              child: GestureDetector(
                onTap: widget.onClose,
                child: Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.45),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.close_rounded,
                    color: Colors.white,
                    size: 18,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CountdownLabel extends StatelessWidget {
  final DateTime? expiresAt;

  const _CountdownLabel({required this.expiresAt});

  @override
  Widget build(BuildContext context) {
    if (expiresAt == null) {
      return const Text(
        'Reduce food wastage — claim it now',
        style: TextStyle(
          color: Colors.white,
          fontSize: 12.5,
          fontWeight: FontWeight.w600,
        ),
      );
    }

    return StreamBuilder<int>(
      stream: Stream.periodic(const Duration(seconds: 1), (value) => value),
      builder: (context, _) {
        final remaining = expiresAt!.difference(DateTime.now());
        final minutes = remaining.isNegative ? 0 : remaining.inMinutes;
        final seconds = remaining.isNegative ? 0 : remaining.inSeconds % 60;
        final timeText = '$minutes:${seconds.toString().padLeft(2, '0')}';

        return RichText(
          text: TextSpan(
            style: const TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: Colors.white,
            ),
            children: [
              const TextSpan(text: 'Claim in '),
              TextSpan(
                text: timeText,
                style: const TextStyle(
                  color: Color(0xFF6FCF97),
                  fontWeight: FontWeight.w800,
                ),
              ),
              const TextSpan(text: ' and reduce food wastage'),
            ],
          ),
        );
      },
    );
  }
}
