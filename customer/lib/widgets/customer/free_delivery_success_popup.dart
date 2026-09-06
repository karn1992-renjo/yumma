import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../theme/foodflow_theme.dart';

class FreeDeliveryMilestoneTracker {
  FreeDeliveryMilestoneTracker._();

  static bool _celebrated = false;

  static bool shouldCelebrate({
    required bool eligible,
    required bool achieved,
  }) {
    if (!eligible || !achieved) {
      _celebrated = false;
      return false;
    }
    if (_celebrated) return false;
    _celebrated = true;
    return true;
  }
}

Future<void> showFreeDeliverySuccessPopup(
  BuildContext context, {
  String? savedText,
}) {
  return showGeneralDialog<void>(
    context: context,
    barrierDismissible: true,
    barrierLabel: 'Free delivery unlocked',
    barrierColor: Colors.black.withOpacity(0.34),
    transitionDuration: const Duration(milliseconds: 260),
    pageBuilder: (_, __, ___) => const SizedBox.shrink(),
    transitionBuilder: (dialogContext, anim, __, ___) {
      final curved = CurvedAnimation(parent: anim, curve: Curves.easeOutCubic);
      return Opacity(
        opacity: anim.value,
        child: Transform.translate(
          offset: Offset(0, (1 - curved.value) * 40),
          child: _FreeDeliveryDialog(savedText: savedText),
        ),
      );
    },
  );
}

class _FreeDeliveryDialog extends StatefulWidget {
  const _FreeDeliveryDialog({this.savedText});

  final String? savedText;

  @override
  State<_FreeDeliveryDialog> createState() => _FreeDeliveryDialogState();
}

class _FreeDeliveryDialogState extends State<_FreeDeliveryDialog>
    with TickerProviderStateMixin {
  late final AnimationController _in;
  late final AnimationController _confetti;

  @override
  void initState() {
    super.initState();
    _in = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    )..forward();
    _confetti = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..forward();

    // Auto-dismiss so it doesn't block the checkout flow.
    Future.delayed(const Duration(milliseconds: 2600), () {
      if (mounted) Navigator.of(context).maybePop();
    });
  }

  @override
  void dispose() {
    _in.dispose();
    _confetti.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    // Header + accents tuned to the purple delivery-scooter mascot.
    const accent = Color(0xFF7A2BE0);

    final scooterSlide = CurvedAnimation(
      parent: _in,
      curve: const Interval(0.05, 0.7, curve: Curves.easeOutCubic),
    );
    final stampPop = CurvedAnimation(
      parent: _in,
      curve: const Interval(0.55, 1.0, curve: Curves.elasticOut),
    );

    return GestureDetector(
      onTap: () => Navigator.of(context).maybePop(),
      child: Material(
        color: Colors.transparent,
        child: Center(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 34),
            child: Container(
              constraints: const BoxConstraints(maxWidth: 360),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(28),
                boxShadow: [
                  BoxShadow(
                    color: accent.withOpacity(0.22),
                    blurRadius: 40,
                    offset: const Offset(0, 18),
                  ),
                ],
              ),
              clipBehavior: Clip.antiAlias,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // ---- animated header band ----
                  SizedBox(
                    height: 172,
                    child: ClipRect(
                      child: Stack(
                        clipBehavior: Clip.none,
                        children: [
                          Positioned.fill(
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                  colors: [
                                    accent,
                                    Color.lerp(accent, Colors.black, 0.18)!,
                                  ],
                                ),
                              ),
                            ),
                          ),
                          Positioned.fill(
                            child: AnimatedBuilder(
                              animation: _confetti,
                              builder: (_, __) => CustomPaint(
                                painter: _ConfettiPainter(_confetti.value),
                              ),
                            ),
                          ),
                          // road line
                          Positioned(
                            left: 0,
                            right: 0,
                            bottom: 20,
                            child: Container(
                              height: 3,
                              color: Colors.white.withOpacity(0.30),
                            ),
                          ),
                          // scooter drives in from the right (it faces left)
                          AnimatedBuilder(
                            animation: scooterSlide,
                            builder: (context, child) {
                              final v = scooterSlide.value;
                              // motion whoosh lines trailing the scooter
                              return Positioned(
                                right: -180 + v * 200,
                                bottom: 8,
                                child: Opacity(
                                  opacity: v < 0.15 ? v / 0.15 : 1,
                                  child: Row(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.center,
                                    children: [
                                      Column(
                                        mainAxisAlignment:
                                            MainAxisAlignment.center,
                                        crossAxisAlignment:
                                            CrossAxisAlignment.end,
                                        children: [
                                          for (var i = 0; i < 3; i++)
                                            Padding(
                                              padding: const EdgeInsets.symmetric(
                                                  vertical: 4),
                                              child: Container(
                                                width: (26 - i * 7) *
                                                    (v > 0.75 ? 0.0 : 1.0),
                                                height: 3,
                                                decoration: BoxDecoration(
                                                  color: Colors.white
                                                      .withOpacity(0.55),
                                                  borderRadius:
                                                      BorderRadius.circular(3),
                                                ),
                                              ),
                                            ),
                                        ],
                                      ),
                                      const SizedBox(width: 6),
                                      child!,
                                    ],
                                  ),
                                ),
                              );
                            },
                            child: Image.asset(
                              'assets/images/scooter.png',
                              width: 132,
                              height: 132,
                              fit: BoxFit.contain,
                              filterQuality: FilterQuality.medium,
                            ),
                          ),
                          // FREE stamp (top-left, out of the scooter's path)
                          Positioned(
                            left: 18,
                            top: 20,
                            child: ScaleTransition(
                              scale: stampPop,
                              child: Transform.rotate(
                                angle: -0.12,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 12, vertical: 7),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(10),
                                    boxShadow: [
                                      BoxShadow(
                                        color: Colors.black.withOpacity(0.15),
                                        blurRadius: 8,
                                        offset: const Offset(0, 3),
                                      ),
                                    ],
                                  ),
                                  child: Text(
                                    'FREE',
                                    style: TextStyle(
                                      color: accent,
                                      fontSize: 20,
                                      letterSpacing: 1,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  // ---- body ----
                  Padding(
                    padding: const EdgeInsets.fromLTRB(22, 18, 22, 20),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text(
                          'Free delivery unlocked!',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          widget.savedText != null &&
                                  widget.savedText!.trim().isNotEmpty
                              ? 'Your delivery charge of ${widget.savedText} is on us.'
                              : 'Your delivery charge is now on us.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 13,
                            height: 1.35,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 18),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton(
                            onPressed: () =>
                                Navigator.of(context).maybePop(),
                            style: FilledButton.styleFrom(
                              backgroundColor: primary,
                              foregroundColor: Colors.white,
                              padding:
                                  const EdgeInsets.symmetric(vertical: 13),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(16),
                              ),
                            ),
                            child: const Text(
                              'Continue',
                              style: TextStyle(fontWeight: FontWeight.w900),
                            ),
                          ),
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
}

class _ConfettiPainter extends CustomPainter {
  _ConfettiPainter(this.t);

  final double t;

  static const _colors = [
    Color(0xFFFFD54F),
    Color(0xFFFF8A65),
    Color(0xFF4FC3F7),
    Color(0xFFFFFFFF),
    Color(0xFFCE93D8),
  ];

  @override
  void paint(Canvas canvas, Size size) {
    final rnd = math.Random(7);
    for (var i = 0; i < 22; i++) {
      final startX = rnd.nextDouble() * size.width;
      final drift = (rnd.nextDouble() - 0.5) * 40;
      final fall = t * (size.height + 30) * (0.6 + rnd.nextDouble() * 0.6);
      final x = startX + drift * t;
      final y = -20 + fall;
      if (y > size.height) continue;
      final paint = Paint()
        ..color = _colors[i % _colors.length].withOpacity(1 - t * 0.4);
      canvas.save();
      canvas.translate(x, y);
      canvas.rotate((i + t * 6) * 0.7);
      if (i.isEven) {
        canvas.drawRect(
            const Rect.fromLTWH(-3, -3, 6, 6), paint);
      } else {
        canvas.drawCircle(Offset.zero, 3, paint);
      }
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(covariant _ConfettiPainter oldDelegate) =>
      oldDelegate.t != t;
}
