import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:lottie/lottie.dart';

import '../../models/scratch_card.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

const String scratchGiftLottie = 'assets/animations/gift_box_white.json';
const Color _scratchOrange = Color(0xFFFF5A00);
const Color _scratchAmber = Color(0xFFFF9800);
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

class ScratchCardFace extends StatelessWidget {
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
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      width: double.infinity,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(compact ? 18 : 20),
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
                dense: !compact,
              ),
            ),
            if (card.isRevealed)
              _RevealedRewardPanel(card: card, compact: compact)
            else if (card.isExpired)
              _ExpiredPanel(card: card, compact: compact)
            else
              _UnrevealedPanel(card: card, compact: compact),
            if (showOrderTag && card.orderNumber?.isNotEmpty == true)
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

class _UnrevealedPanel extends StatelessWidget {
  const _UnrevealedPanel({required this.card, required this.compact});

  final ScratchCard card;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding:
          EdgeInsets.fromLTRB(14, compact ? 12 : 22, 14, compact ? 12 : 16),
      child: Column(
        children: [
          Text(
            'SCRATCH & WIN',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white,
              fontSize: compact ? 20 : 25,
              fontWeight: FontWeight.w900,
              height: 1,
            ),
          ),
          SizedBox(height: compact ? 8 : 14),
          Expanded(
            child: Stack(
              alignment: Alignment.center,
              children: [
                CustomPaint(
                  painter: _FoilPatchPainter(),
                  child: Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.touch_app_outlined,
                          color: FoodFlowTheme.ink.withOpacity(0.68),
                          size: compact ? 30 : 40,
                        ),
                        const SizedBox(height: 5),
                        Text(
                          'Scratch Here',
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: compact ? 12 : 15,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                Positioned.fill(
                  child: IgnorePointer(
                    child: CustomPaint(
                      painter: _FoilGiftPatternPainter(muted: compact),
                    ),
                  ),
                ),
              ],
            ),
          ),
          if (card.expiresAt != null) ...[
            const SizedBox(height: 8),
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
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Congratulations!',
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
    final ry = size.height * 0.31;
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
          Color(0xFFF5F6F7),
          Color(0xFFD9DDE1),
          Color(0xFFFFFFFF),
          Color(0xFFC3C8CE),
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

class _FoilGiftPatternPainter extends CustomPainter {
  const _FoilGiftPatternPainter({required this.muted});

  final bool muted;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = muted ? 1.1 : 1.35
      ..color = const Color(0xFF9CA3AF).withOpacity(muted ? 0.18 : 0.24);
    final count = muted ? 8 : 13;
    for (var i = 0; i < count; i++) {
      final maxX = math.max(size.width.toInt() - 48, 1);
      final maxY = math.max(size.height.toInt() - 40, 1);
      final x = 24.0 + ((i * 67) % maxX);
      final y = 20.0 + ((i * 43) % maxY);
      canvas.save();
      canvas.translate(x, y);
      canvas.rotate((i % 5 - 2) * 0.14);
      final box = Rect.fromCenter(
        center: Offset.zero,
        width: muted ? 20 : 24,
        height: muted ? 18 : 22,
      );
      canvas.drawRRect(
        RRect.fromRectAndRadius(box, const Radius.circular(4)),
        paint,
      );
      canvas.drawLine(
        Offset(-box.width / 2, 0),
        Offset(box.width / 2, 0),
        paint,
      );
      canvas.drawLine(
        Offset(0, -box.height / 2),
        Offset(0, box.height / 2),
        paint,
      );
      canvas.drawArc(
        Rect.fromCenter(
          center: Offset(-box.width * 0.18, -box.height * 0.58),
          width: 12,
          height: 10,
        ),
        math.pi * 0.2,
        math.pi * 1.35,
        false,
        paint,
      );
      canvas.drawArc(
        Rect.fromCenter(
          center: Offset(box.width * 0.18, -box.height * 0.58),
          width: 12,
          height: 10,
        ),
        -math.pi * 0.55,
        math.pi * 1.35,
        false,
        paint,
      );
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(covariant _FoilGiftPatternPainter oldDelegate) {
    return oldDelegate.muted != muted;
  }
}
