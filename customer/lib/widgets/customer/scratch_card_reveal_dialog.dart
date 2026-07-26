import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../config/api_constants.dart';
import '../../models/scratch_card.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../common/app_cached_image.dart';
import 'scratch_card_art.dart';

Future<ScratchCard?> showScratchCardRevealDialog(
  BuildContext context, {
  required ScratchCard card,
}) {
  return showDialog<ScratchCard>(
    context: context,
    barrierDismissible: card.isRevealed || card.isRevealLocked,
    builder: (_) => _ScratchCardRevealDialog(card: card),
  );
}

class _ScratchCardRevealDialog extends StatefulWidget {
  const _ScratchCardRevealDialog({required this.card});

  final ScratchCard card;

  @override
  State<_ScratchCardRevealDialog> createState() =>
      _ScratchCardRevealDialogState();
}

class _ScratchCardRevealDialogState extends State<_ScratchCardRevealDialog> {
  final ApiService _api = ApiService();
  final List<Offset> _scratchPoints = [];
  final Set<String> _scratchCells = {};
  late ScratchCard _card = widget.card;
  bool _revealing = false;
  bool _started = false;
  bool _introVisible = false;
  String? _error;
  double _progress = 0;

  bool get _surfaceHidden =>
      _card.isRevealed && (!_started || _progress >= 0.58);

  @override
  void initState() {
    super.initState();
    _introVisible = !_card.isRevealed;
    _markViewed();
  }

  Future<void> _markViewed() async {
    if (_card.isRevealed) return;
    try {
      final response = await _api.post(ApiConstants.viewScratchCard(_card.id));
      if (!mounted) return;
      if (response['success'] == true && response['data'] is Map) {
        setState(() {
          _card = ScratchCard.fromJson(
            Map<String, dynamic>.from(response['data'] as Map),
          );
        });
      }
    } catch (_) {
      // Viewing analytics must never block the customer from scratching.
    }
  }

  Future<void> _reveal() async {
    if (_card.isRevealed || _revealing) return;
    if (_card.isRevealLocked) {
      setState(() {
        _error = _card.revealLockedReason ??
            'Scratch card reward can be claimed after delivery.';
      });
      return;
    }
    setState(() {
      _revealing = true;
      _error = null;
    });
    try {
      final response =
          await _api.post(ApiConstants.revealScratchCard(_card.id));
      if (response['success'] == true && response['data'] is Map) {
        final revealed = ScratchCard.fromJson(
          Map<String, dynamic>.from(response['data'] as Map),
        );
        if (!mounted) return;
        HapticFeedback.mediumImpact();
        setState(() => _card = revealed);
      } else {
        setState(() => _error = response['message']?.toString());
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Unable to reveal this scratch card.';
          _started = false;
        });
      }
    } finally {
      if (mounted) setState(() => _revealing = false);
    }
  }

  void _scratch(Offset localPosition, Size size) {
    if (_card.isRevealLocked) {
      setState(() {
        _error = _card.revealLockedReason ??
            'Scratch card reward can be claimed after delivery.';
      });
      return;
    }
    if (size.width <= 0 || size.height <= 0) return;
    if (localPosition.dx < 0 ||
        localPosition.dy < 0 ||
        localPosition.dx > size.width ||
        localPosition.dy > size.height) {
      return;
    }

    if (!_started) {
      _started = true;
      HapticFeedback.selectionClick();
      _reveal();
    }

    final cellSize = 14.0;
    final cellX = (localPosition.dx / cellSize).floor();
    final cellY = (localPosition.dy / cellSize).floor();
    final maxCells =
        ((size.width / cellSize).ceil() * (size.height / cellSize).ceil())
            .clamp(1, 10000);

    setState(() {
      _scratchPoints.add(localPosition);
      _scratchCells.add('$cellX:$cellY');
      _progress = (_scratchCells.length / maxCells).clamp(0, 1);
    });
  }

  @override
  Widget build(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    if (_introVisible && !_card.isRevealed) {
      return _ScratchIntroDialog(
        card: _card,
        onClose: () => Navigator.pop(context, _card),
        onScratch: _card.isRevealLocked
            ? null
            : () => setState(() => _introVisible = false),
      );
    }
    if (_card.isRevealed && _surfaceHidden) {
      return _ScratchResultDialog(
        card: _card,
        onClose: () => Navigator.pop(context, _card),
      );
    }

    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
      backgroundColor: Colors.transparent,
      child: LayoutBuilder(
        builder: (context, viewport) => SingleChildScrollView(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: 430,
              maxHeight: viewport.maxHeight,
            ),
            child: Container(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(26),
                boxShadow: [
                  BoxShadow(
                    color: FoodFlowTheme.tagOrange.withOpacity(0.24),
                    blurRadius: 36,
                    offset: const Offset(0, 18),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      _RestaurantAvatar(card: _card),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _card.isRevealed
                                  ? 'Reward revealed'
                                  : _card.title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: FoodFlowTheme.ink,
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              _card.restaurantName?.isNotEmpty == true
                                  ? _card.restaurantName!
                                  : 'Order reward',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: FoodFlowTheme.muted,
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(context, _card),
                        icon: const Icon(Icons.close_rounded),
                        color: FoodFlowTheme.ink,
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  AspectRatio(
                    aspectRatio: 1.18,
                    child: LayoutBuilder(
                      builder: (context, constraints) {
                        final size = constraints.biggest;
                        return GestureDetector(
                          onPanDown: (details) =>
                              _scratch(details.localPosition, size),
                          onPanUpdate: (details) =>
                              _scratch(details.localPosition, size),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(24),
                            child: Stack(
                              fit: StackFit.expand,
                              children: [
                                _RewardFace(
                                  card: _card,
                                  revealing: _revealing,
                                  error: _error,
                                ),
                                IgnorePointer(
                                  child: AnimatedOpacity(
                                    opacity: _surfaceHidden ? 0 : 1,
                                    duration: const Duration(milliseconds: 260),
                                    child: CustomPaint(
                                      painter: _ScratchOverlayPainter(
                                        points: _scratchPoints,
                                        primary: primary,
                                        progress: _progress,
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 12),
                  LinearProgressIndicator(
                    value: _surfaceHidden ? 1 : _progress,
                    minHeight: 6,
                    backgroundColor: FoodFlowTheme.line,
                    color: FoodFlowTheme.tagOrange,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          _card.isRevealed
                              ? ScratchRewardText.subtitle(_card)
                              : 'Rub the card to unlock your reward.',
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      ElevatedButton(
                        onPressed: _card.isRevealed
                            ? () => Navigator.pop(context, _card)
                            : null,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: FoodFlowTheme.tagOrange,
                          foregroundColor: Colors.white,
                          disabledBackgroundColor: FoodFlowTheme.disabledFill,
                          elevation: 8,
                          shadowColor:
                              FoodFlowTheme.tagOrange.withOpacity(0.28),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        child: Text(_card.isRevealed ? 'Done' : 'Scratch'),
                      ),
                    ],
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

class _ScratchResultDialog extends StatelessWidget {
  const _ScratchResultDialog({required this.card, required this.onClose});

  final ScratchCard card;
  final VoidCallback onClose;

  bool get _isNoReward {
    final reward = card.reward ?? const <String, dynamic>{};
    final type = card.rewardType ?? reward['type']?.toString() ?? '';
    return type == 'no_reward';
  }

  bool get _isWalletReward {
    final reward = card.reward ?? const <String, dynamic>{};
    final type = card.rewardType ?? reward['type']?.toString() ?? '';
    return type == 'wallet_cashback' ||
        type == 'wallet_credit' ||
        type == 'cashback' ||
        type == 'reward_points';
  }

  @override
  Widget build(BuildContext context) {
    final code = ScratchRewardText.code(card);
    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 22, vertical: 24),
      backgroundColor: Colors.transparent,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 430),
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.18),
                blurRadius: 34,
                offset: const Offset(0, 18),
              ),
            ],
          ),
          child: Stack(
            children: [
              Positioned.fill(
                  child: CustomPaint(painter: _DialogConfettiPainter())),
              Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: IconButton(
                      onPressed: onClose,
                      icon: const Icon(Icons.close_rounded),
                      color: FoodFlowTheme.ink,
                    ),
                  ),
                  if (_isNoReward)
                    const _BetterLuckContent()
                  else if (code.isNotEmpty)
                    _CouponWinContent(card: card, code: code)
                  else
                    _WalletWinContent(card: card),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: onClose,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: FoodFlowTheme.tagOrange,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: Text(
                        _isNoReward
                            ? 'Try Another Card'
                            : (_isWalletReward
                                ? 'Check Wallet'
                                : 'Yay! Use Now'),
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CouponWinContent extends StatelessWidget {
  const _CouponWinContent({required this.card, required this.code});

  final ScratchCard card;
  final String code;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const Text(
          'Congratulations!',
          style: TextStyle(
            color: FoodFlowTheme.tagOrange,
            fontSize: 25,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        const Text(
          'You Won',
          style: TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 18),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFFFF4A22), Color(0xFFFFA000)],
            ),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            children: [
              const RotatedBox(
                quarterTurns: 3,
                child: Text(
                  'FLAT',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  ScratchRewardText.title(context, card),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 34,
                    fontWeight: FontWeight.w900,
                    height: 0.95,
                  ),
                ),
              ),
              Container(
                  width: 1, height: 56, color: Colors.white.withOpacity(0.5)),
              const SizedBox(width: 16),
              Expanded(
                child: Text(
                  ScratchRewardText.subtitle(card),
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    height: 1.25,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        const Text(
          'Coupon Code',
          style: TextStyle(
            color: FoodFlowTheme.muted,
            fontSize: 13,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFFFC9A8)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                code,
                style: const TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(width: 12),
              InkWell(
                onTap: () => Clipboard.setData(ClipboardData(text: code)),
                child: const Icon(Icons.copy_rounded,
                    size: 20, color: FoodFlowTheme.ink),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        Text(
          ScratchRewardText.expiresLabel(card),
          style: const TextStyle(
            color: FoodFlowTheme.tagOrange,
            fontSize: 12,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}

class _WalletWinContent extends StatelessWidget {
  const _WalletWinContent({required this.card});

  final ScratchCard card;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const Text(
          'Wow!\nYou Won',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: FoodFlowTheme.tagOrange,
            fontSize: 20,
            fontWeight: FontWeight.w900,
            height: 1.2,
          ),
        ),
        const SizedBox(height: 12),
        const ScratchGiftBox(size: 116),
        const SizedBox(height: 8),
        Text(
          ScratchRewardText.title(context, card),
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Color(0xFF0E8F45),
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          ScratchRewardText.subtitle(card),
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: FoodFlowTheme.inkSoft,
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _BetterLuckContent extends StatelessWidget {
  const _BetterLuckContent();

  @override
  Widget build(BuildContext context) {
    return const Column(
      children: [
        Text(
          'Better Luck\nNext Time!',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: FoodFlowTheme.tagOrange,
            fontSize: 21,
            fontWeight: FontWeight.w900,
            height: 1.2,
          ),
        ),
        SizedBox(height: 20),
        Icon(Icons.sentiment_dissatisfied_rounded,
            size: 74, color: Color(0xFFFFC84D)),
        SizedBox(height: 18),
        Text(
          'Do not worry, keep trying.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: FoodFlowTheme.inkSoft,
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _DialogConfettiPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    const colors = [
      Color(0xFFFF5A00),
      Color(0xFFFFB300),
      Color(0xFF54A7C5),
      Color(0xFFD673B1),
    ];
    for (var i = 0; i < 18; i++) {
      final x = ((i * 47) % 100) / 100 * size.width;
      final y = ((i * 31) % 100) / 100 * size.height;
      final paint = Paint()
        ..color = colors[i % colors.length].withOpacity(0.65)
        ..strokeWidth = i.isEven ? 4 : 3
        ..strokeCap = StrokeCap.round;
      canvas.save();
      canvas.translate(x, y);
      canvas.rotate(i * 0.38);
      canvas.drawLine(const Offset(-4, 0), const Offset(4, 0), paint);
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(covariant _DialogConfettiPainter oldDelegate) => false;
}

class _ScratchIntroDialog extends StatelessWidget {
  const _ScratchIntroDialog({
    required this.card,
    required this.onClose,
    required this.onScratch,
  });

  final ScratchCard card;
  final VoidCallback onClose;
  final VoidCallback? onScratch;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 24),
      backgroundColor: Colors.transparent,
      child: LayoutBuilder(
        builder: (context, viewport) => SingleChildScrollView(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: 410,
              maxHeight: viewport.maxHeight,
            ),
            child: Container(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.28),
                    blurRadius: 36,
                    offset: const Offset(0, 18),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: IconButton(
                      onPressed: onClose,
                      icon: const Icon(Icons.close_rounded),
                      color: FoodFlowTheme.ink,
                    ),
                  ),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(18, 18, 18, 20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [Color(0xFFFF7A1A), Color(0xFFFF3D00)],
                      ),
                      borderRadius: BorderRadius.circular(22),
                      boxShadow: [
                        BoxShadow(
                          color: FoodFlowTheme.tagOrange.withOpacity(0.24),
                          blurRadius: 22,
                          offset: const Offset(0, 12),
                        ),
                      ],
                    ),
                    child: Column(
                      children: [
                        const Text(
                          'Congratulations!',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        const Text(
                          'You have unlocked',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 3),
                        const Text(
                          '1 Scratch Card',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 21,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 6),
                        const ScratchGiftBox(size: 138),
                        if (card.restaurantName?.isNotEmpty == true) ...[
                          const SizedBox(height: 4),
                          Text(
                            card.restaurantName!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  if (card.isRevealLocked) ...[
                    Text(
                      card.revealLockedReason ??
                          'Scratch card reward can be claimed after delivery.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: onScratch,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: FoodFlowTheme.tagOrange,
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: FoodFlowTheme.disabledFill,
                        disabledForegroundColor: FoodFlowTheme.muted,
                        elevation: 8,
                        shadowColor: FoodFlowTheme.tagOrange.withOpacity(0.28),
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: Text(
                        card.isRevealLocked ? 'After Delivery' : 'Scratch Now',
                        style: TextStyle(
                            fontSize: 15, fontWeight: FontWeight.w900),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed: onClose,
                    child: Text(
                      card.isRevealLocked ? 'Close' : 'Maybe Later',
                      style: TextStyle(
                        color: FoodFlowTheme.muted,
                        fontWeight: FontWeight.w800,
                      ),
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

class _RewardFace extends StatelessWidget {
  const _RewardFace({
    required this.card,
    required this.revealing,
    required this.error,
  });

  final ScratchCard card;
  final bool revealing;
  final String? error;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: card.isRevealed
              ? const [Color(0xFFFFF7ED), Colors.white]
              : const [Color(0xFFFF7A1A), Color(0xFFFF3D00)],
        ),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SizedBox(
            height: 116,
            child: card.isRevealed
                ? const ScratchGiftBox(size: 116)
                : revealing
                    ? const Center(child: CircularProgressIndicator())
                    : const ScratchGiftBox(size: 116),
          ),
          const SizedBox(height: 8),
          Text(
            card.isRevealed
                ? ScratchRewardText.title(context, card)
                : (revealing ? 'Unlocking reward...' : 'Scratch & Win'),
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: card.isRevealed ? FoodFlowTheme.ink : Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w900,
              height: 1.05,
            ),
          ),
          const SizedBox(height: 7),
          Text(
            error?.isNotEmpty == true
                ? error!
                : card.isRevealed
                    ? ScratchRewardText.subtitle(card)
                    : 'Your surprise reward is hidden under the premium foil.',
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: card.isRevealed
                  ? FoodFlowTheme.muted
                  : Colors.white.withOpacity(0.84),
              fontSize: 13,
              fontWeight: FontWeight.w800,
              height: 1.25,
            ),
          ),
        ],
      ),
    );
  }
}

class _ScratchOverlayPainter extends CustomPainter {
  const _ScratchOverlayPainter({
    required this.points,
    required this.primary,
    required this.progress,
  });

  final List<Offset> points;
  final Color primary;
  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    final bounds = Offset.zero & size;
    canvas.saveLayer(bounds, Paint());

    final rect = RRect.fromRectAndRadius(bounds, const Radius.circular(24));
    final foil = Paint()
      ..shader = LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [
          const Color(0xFFE5E7EB),
          Colors.white,
          const Color(0xFF9CA3AF),
          primary.withOpacity(0.35),
        ],
        stops: const [0, 0.34, 0.68, 1],
      ).createShader(bounds);
    canvas.drawRRect(rect, foil);

    final shimmer = Paint()
      ..color = Colors.white.withOpacity(0.36)
      ..strokeWidth = 2;
    for (var i = -size.height.toInt(); i < size.width; i += 24) {
      canvas.drawLine(
        Offset(i.toDouble(), size.height),
        Offset(i + size.height, 0),
        shimmer,
      );
    }

    final badgePaint = Paint()..color = Colors.black.withOpacity(0.16);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromCenter(
          center: Offset(size.width / 2, size.height / 2),
          width: math.min(size.width * 0.72, 260),
          height: 74,
        ),
        const Radius.circular(20),
      ),
      badgePaint,
    );

    final textPainter = TextPainter(
      text: TextSpan(
        text: progress > 0.04 ? 'KEEP SCRATCHING' : 'SCRATCH HERE',
        style: const TextStyle(
          color: Colors.white,
          fontSize: 18,
          fontWeight: FontWeight.w900,
          letterSpacing: 1.4,
        ),
      ),
      textAlign: TextAlign.center,
      textDirection: TextDirection.ltr,
    )..layout(maxWidth: size.width);
    textPainter.paint(
      canvas,
      Offset(
        (size.width - textPainter.width) / 2,
        (size.height - textPainter.height) / 2,
      ),
    );

    final clearPaint = Paint()
      ..blendMode = BlendMode.clear
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round
      ..style = PaintingStyle.stroke
      ..strokeWidth = 34;

    for (final point in points) {
      canvas.drawCircle(point, 21, clearPaint);
    }

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _ScratchOverlayPainter oldDelegate) {
    return oldDelegate.points.length != points.length ||
        oldDelegate.primary != primary ||
        oldDelegate.progress != progress;
  }
}

class _RestaurantAvatar extends StatelessWidget {
  const _RestaurantAvatar({required this.card});

  final ScratchCard card;

  @override
  Widget build(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    return Container(
      width: 52,
      height: 52,
      decoration: BoxDecoration(
        color: FoodFlowTheme.brandSoft(context),
        borderRadius: BorderRadius.circular(18),
      ),
      clipBehavior: Clip.antiAlias,
      child: card.restaurantLogoUrl?.isNotEmpty == true
          ? AppCachedImage(
              imageUrl: card.restaurantLogoUrl!,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) =>
                  Icon(Icons.auto_awesome_rounded, color: primary),
            )
          : Icon(Icons.auto_awesome_rounded, color: primary),
    );
  }
}
