import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:lottie/lottie.dart';
import 'package:shimmer/shimmer.dart';

import '../../models/scratch_card.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

const String scratchGiftLottie = 'assets/animations/gift_box_white.json';
const Color _scratchOrange = Color(0xFFFF5A00);
const Color _scratchAmber = Color(0xFFFFB300);
const Color _scratchGold = Color(0xFFFFD54A);
const Color _scratchPeach = Color(0xFFFFF2E8);

class ScratchRewardText {
  static String title(BuildContext context, ScratchCard card) {
    final reward = card.reward ?? const <String, dynamic>{};
    final type = card.rewardType ?? reward['type']?.toString() ?? '';
    if (type == 'wallet_cashback' ||
        type == 'wallet_credit' ||
        type == 'cashback') {
      final amount = double.tryParse('${reward['amount'] ?? 0}') ?? 0;
      return '${formatCurrency(context, amount)} Cashback';
    }
    if (type == 'reward_points') {
      return '${reward['points'] ?? reward['amount'] ?? 0} Points';
    }
    if (type == 'no_reward') {
      return 'Better luck next time';
    }
    return card.rewardTitle ?? reward['title']?.toString() ?? 'Reward';
  }

  static String subtitle(ScratchCard card) {
    final reward = card.reward ?? const <String, dynamic>{};
    final type = card.rewardType ?? reward['type']?.toString() ?? '';
    if (type == 'wallet_cashback' ||
        type == 'wallet_credit' ||
        type == 'cashback') {
      return 'Amount has been added to your wallet';
    }
    if (type == 'reward_points') {
      return 'Points have been added to your account';
    }
    if (type == 'gift_voucher' || type == 'gift_card') {
      return 'Gift voucher saved to your rewards';
    }
    if (reward['coupon_code'] != null) {
      return 'Auto-applied at checkout on eligible orders';
    }
    if (type == 'no_reward') {
      return 'Keep ordering to win exciting rewards';
    }
    return 'Reward saved to your account';
  }

  static String code(ScratchCard card) {
    final reward = card.reward ?? const <String, dynamic>{};
    return (reward['coupon_code'] ??
            reward['gift_card_code'] ??
            reward['code'] ??
            '')
        .toString();
  }

  static String expiresLabel(ScratchCard card) {
    final expiresAt = card.expiresAt;
    if (expiresAt == null) return 'No expiry';
    if (card.isExpired) {
      return 'Expired on ${DateFormat('d MMM yyyy').format(expiresAt)}';
    }
    final days = expiresAt.difference(DateTime.now()).inDays + 1;
    if (days <= 1) return 'Expires today';
    return 'Expires in $days days';
  }
}

class ScratchGiftBox extends StatelessWidget {
  const ScratchGiftBox({super.key, this.size = 132});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Lottie.asset(
        scratchGiftLottie,
        fit: BoxFit.contain,
        repeat: true,
      ),
    );
  }
}

/// The static/idle card face used in lists and previews — a foil "ticket"
/// with a torn/wavy edge, shimmering while unrevealed (Paytm-style).
class ScratchCardFace extends StatefulWidget {
  const ScratchCardFace({
    super.key,
    required this.card,
    this.height = 178,
    this.compact = false,
    this.showOrderTag = true,
  });

  final ScratchCard card;
  final double height;
  final bool compact;
  final bool showOrderTag;

  @override
  State<ScratchCardFace> createState() => _ScratchCardFaceState();
}

class _ScratchCardFaceState extends State<ScratchCardFace>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final card = widget.card;
    return SizedBox(
      height: widget.height,
      width: double.infinity,
      child: ClipPath(
        clipper: _TornEdgeClipper(radius: widget.compact ? 18 : 20),
        child: Stack(
          fit: StackFit.expand,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: card.isExpired
                      ? const [Color(0xFFE5E7EB), Color(0xFFBDBDBD)]
                      : const [_scratchAmber, _scratchOrange],
                ),
              ),
            ),
            CustomPaint(
              painter: _ScratchConfettiPainter(
                muted: card.isExpired,
                dense: !widget.compact,
              ),
            ),
            if (card.isRevealed)
              _RevealedRewardPanel(card: card, compact: widget.compact)
            else if (card.isExpired)
              _ExpiredPanel(card: card, compact: widget.compact)
            else
              _UnrevealedPanel(
                card: card,
                compact: widget.compact,
                pulse: _pulse,
              ),
            if (widget.showOrderTag && card.orderNumber?.isNotEmpty == true)
              Positioned(
                left: 12,
                top: 10,
                child: _CardPill(label: '#${card.orderNumber}', dark: true),
              ),
          ],
        ),
      ),
    );
  }
}

/// Clips a rounded-rect with a torn/scalloped bottom edge — the classic
/// coupon/scratch-ticket silhouette.
class _TornEdgeClipper extends CustomClipper<Path> {
  const _TornEdgeClipper({required this.radius});

  final double radius;

  @override
  Path getClip(Size size) {
    const waveWidth = 16.0;
    const waveDepth = 5.0;
    final path = Path()
      ..moveTo(0, radius)
      ..quadraticBezierTo(0, 0, radius, 0)
      ..lineTo(size.width - radius, 0)
      ..quadraticBezierTo(size.width, 0, size.width, radius)
      ..lineTo(size.width, size.height - waveDepth);

    final waves = (size.width / waveWidth).ceil();
    for (var i = waves; i >= 1; i--) {
      final x2 = i * waveWidth;
      final x1 = x2 - waveWidth / 2;
      path.quadraticBezierTo(
        x1,
        size.height - waveDepth * (i.isEven ? 2 : 0),
        x2.clamp(0, size.width),
        size.height - waveDepth,
      );
    }

    path.lineTo(0, size.height - waveDepth);
    path.close();
    return path;
  }

  @override
  bool shouldReclip(covariant _TornEdgeClipper oldClipper) =>
      oldClipper.radius != radius;
}

class _UnrevealedPanel extends StatelessWidget {
  const _UnrevealedPanel({
    required this.card,
    required this.compact,
    required this.pulse,
  });

  final ScratchCard card;
  final bool compact;
  final Animation<double> pulse;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding:
          EdgeInsets.fromLTRB(14, compact ? 10 : 18, 14, compact ? 14 : 20),
      child: Column(
        children: [
          Text(
            'SCRATCH & WIN',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white,
              fontSize: compact ? 18 : 23,
              fontWeight: FontWeight.w900,
              letterSpacing: 0.5,
              height: 1,
            ),
          ),
          SizedBox(height: compact ? 6 : 10),
          Expanded(
            child: Stack(
              alignment: Alignment.center,
              children: [
                Positioned.fill(
                  child: Shimmer.fromColors(
                    baseColor: const Color(0xFFE7E9EC),
                    highlightColor: Colors.white,
                    period: const Duration(milliseconds: 1600),
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        CustomPaint(painter: _FoilPatchPainter()),
                        CustomPaint(
                          painter: _FoilCoinPatternPainter(muted: compact),
                        ),
                      ],
                    ),
                  ),
                ),
                IgnorePointer(
                  child: AnimatedBuilder(
                    animation: pulse,
                    builder: (context, child) => Transform.scale(
                      scale: 0.94 + pulse.value * 0.1,
                      child: child,
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: compact ? 40 : 52,
                          height: compact ? 40 : 52,
                          decoration: const BoxDecoration(
                            shape: BoxShape.circle,
                            gradient: LinearGradient(
                              colors: [_scratchGold, _scratchAmber],
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black26,
                                blurRadius: 6,
                                offset: Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Icon(
                            Icons.touch_app_rounded,
                            color: Colors.white,
                            size: compact ? 20 : 26,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'SCRATCH HERE',
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: compact ? 11 : 13,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 1,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (card.expiresAt != null) ...[
            SizedBox(height: compact ? 6 : 8),
            _CardPill(label: ScratchRewardText.expiresLabel(card), dark: true),
          ],
        ],
      ),
    );
  }
}

class _RevealedRewardPanel extends StatelessWidget {
  const _RevealedRewardPanel({required this.card, required this.compact});

  final ScratchCard card;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final code = ScratchRewardText.code(card);
    return Padding(
      padding: EdgeInsets.all(compact ? 12 : 16),
      child: Center(
        child: Container(
          width: double.infinity,
          padding: EdgeInsets.symmetric(
            horizontal: compact ? 14 : 18,
            vertical: compact ? 12 : 16,
          ),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFFFD5A6)),
          ),
          child: Stack(
            alignment: Alignment.topCenter,
            children: [
              Positioned.fill(
                child: IgnorePointer(
                  child: CustomPaint(painter: _SunburstPainter()),
                ),
              ),
              Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'Congratulations! \u{1F389}',
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  const Text(
                    'You won',
                    style: TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(
                    ScratchRewardText.title(context, card),
                    textAlign: TextAlign.center,
                    maxLines: compact ? 1 : 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: _scratchOrange,
                      fontSize: compact ? 22 : 28,
                      fontWeight: FontWeight.w900,
                      height: 1,
                    ),
                  ),
                  if (code.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    _CardPill(label: 'Code: $code', dark: false),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ExpiredPanel extends StatelessWidget {
  const _ExpiredPanel({required this.card, required this.compact});

  final ScratchCard card;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: compact ? 148 : 190,
        height: compact ? 106 : 132,
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.34),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.schedule_rounded,
              color: Colors.white.withOpacity(0.9),
              size: compact ? 34 : 44,
            ),
            const SizedBox(height: 8),
            Text(
              'Expired',
              style: TextStyle(
                color: Colors.white,
                fontSize: compact ? 16 : 20,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Text(
                ScratchRewardText.expiresLabel(card),
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white.withOpacity(0.82),
                  fontSize: compact ? 9 : 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CardPill extends StatelessWidget {
  const _CardPill({required this.label, required this.dark});

  final String label;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: dark ? Colors.white.withOpacity(0.2) : _scratchPeach,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: dark ? Colors.white : _scratchOrange,
          fontSize: 10,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _SunburstPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, 0);
    final paint = Paint()
      ..color = _scratchGold.withOpacity(0.16)
      ..strokeWidth = 3;
    for (var i = 0; i < 16; i++) {
      final angle = (i / 16) * math.pi;
      final dx = math.cos(angle) * size.width;
      final dy = math.sin(angle) * size.width;
      canvas.drawLine(center, Offset(center.dx + dx, dy), paint);
    }
  }

  @override
  bool shouldRepaint(covariant _SunburstPainter oldDelegate) => false;
}

class _ScratchConfettiPainter extends CustomPainter {
  const _ScratchConfettiPainter({required this.muted, required this.dense});

  final bool muted;
  final bool dense;

  @override
  void paint(Canvas canvas, Size size) {
    final colors = muted
        ? [Colors.white.withOpacity(0.38), Colors.white.withOpacity(0.18)]
        : const [
            Color(0xFFFFFFFF),
            Color(0xFFFFF3B0),
            Color(0xFFFFCB7A),
            Color(0xFF59A9C9),
            Color(0xFFD56FB8),
          ];
    final count = dense ? 38 : 22;
    for (var i = 0; i < count; i++) {
      final x = ((i * 47) % 100) / 100 * size.width;
      final y = ((i * 31) % 100) / 100 * size.height;
      final paint = Paint()
        ..color = colors[i % colors.length].withOpacity(muted ? 0.44 : 0.88)
        ..strokeWidth = i.isEven ? 3 : 2
        ..strokeCap = StrokeCap.round;
      canvas.save();
      canvas.translate(x, y);
      canvas.rotate((i * math.pi) / 7);
      canvas.drawLine(const Offset(-3, 0), const Offset(3, 0), paint);
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(covariant _ScratchConfettiPainter oldDelegate) {
    return oldDelegate.muted != muted || oldDelegate.dense != dense;
  }
}

class _FoilPatchPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final path = Path();
    final center = Offset(size.width / 2, size.height / 2);
    final rx = size.width * 0.43;
    final ry = size.height * 0.34;
    for (var i = 0; i <= 42; i++) {
      final angle = i / 42 * math.pi * 2;
      final jitter = 1 + math.sin(i * 1.7) * 0.06 + math.cos(i * 2.1) * 0.04;
      final point = Offset(
        center.dx + math.cos(angle) * rx * jitter,
        center.dy + math.sin(angle) * ry * jitter,
      );
      if (i == 0) {
        path.moveTo(point.dx, point.dy);
      } else {
        path.lineTo(point.dx, point.dy);
      }
    }
    path.close();

    final bounds = path.getBounds();
    final fill = Paint()
      ..shader = const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [
          _scratchGold,
          Color(0xFFFFF3C4),
          _scratchAmber,
          Color(0xFFE59400),
        ],
      ).createShader(bounds);
    canvas.drawPath(path, fill);

    final stroke = Paint()
      ..color = Colors.white.withOpacity(0.55)
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke;
    for (var i = 0; i < 11; i++) {
      final y = bounds.top + bounds.height * (i + 1) / 12;
      canvas.drawLine(
        Offset(bounds.left + 18, y),
        Offset(bounds.right - 18, y - 9),
        stroke,
      );
    }
  }

  @override
  bool shouldRepaint(covariant _FoilPatchPainter oldDelegate) => false;
}

class _FoilCoinPatternPainter extends CustomPainter {
  const _FoilCoinPatternPainter({required this.muted});

  final bool muted;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = muted ? 1.1 : 1.35
      ..color = const Color(0xFFB5761C).withOpacity(muted ? 0.16 : 0.22);
    final count = muted ? 7 : 11;
    for (var i = 0; i < count; i++) {
      final maxX = math.max(size.width.toInt() - 40, 1);
      final maxY = math.max(size.height.toInt() - 36, 1);
      final x = 20.0 + ((i * 67) % maxX);
      final y = 18.0 + ((i * 43) % maxY);
      final radius = muted ? 7.0 : 9.0;
      canvas.drawCircle(Offset(x, y), radius, paint);
      canvas.drawCircle(Offset(x, y), radius * 0.55, paint);
    }
  }

  @override
  bool shouldRepaint(covariant _FoilCoinPatternPainter oldDelegate) {
    return oldDelegate.muted != muted;
  }
}
