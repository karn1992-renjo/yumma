import 'package:flutter/material.dart';

import '../../../../config/api_constants.dart';
import '../../../../models/scratch_card.dart';
import '../../../../services/api_service.dart';
import '../../../../utils/currency_utils.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class ScratchCardsV2 extends StatefulWidget {
  const ScratchCardsV2({super.key});

  @override
  State<ScratchCardsV2> createState() => _ScratchCardsV2State();
}

class _ScratchCardsV2State extends State<ScratchCardsV2> {
  final ApiService _api = ApiService();
  bool _loading = true;
  bool _redeeming = false;
  int _points = 0;
  Map<String, dynamic> _redemption = const {};
  List<ScratchCard> _cards = const [];

  int get _minPoints => v2Int(_redemption['minimum_points']);
  double get _pointsValue => v2Double(_redemption['points_per_currency']);
  bool get _redeemEnabled =>
      _redemption['enabled'] == true && _points >= _minPoints && _minPoints > 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await Future.wait([
        _api.get(ApiConstants.scratchCards),
        _api.get(ApiConstants.rewardPoints),
      ]);
      final rows = res[0] is Map && res[0]['data'] is List
          ? res[0]['data'] as List
          : const [];
      _cards = rows
          .whereType<Map>()
          .map((r) => ScratchCard.fromJson(Map<String, dynamic>.from(r)))
          .toList();
      final pd = res[1] is Map && res[1]['data'] is Map
          ? Map<String, dynamic>.from(res[1]['data'] as Map)
          : const {};
      _points = v2Int(pd['balance'] ?? pd['points']);
      _redemption = pd['redemption'] is Map
          ? Map<String, dynamic>.from(pd['redemption'] as Map)
          : const {};
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _reveal(ScratchCard c) async {
    if (!c.canReveal) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
            content: Text(c.revealLockedReason ??
                'This card can be revealed after delivery.')));
      return;
    }
    ScratchCard revealed = c;
    try {
      final res = await _api.post(ApiConstants.revealScratchCard(c.id),
          data: const {});
      if (res is Map && res['data'] is Map) {
        revealed = ScratchCard.fromJson(
            Map<String, dynamic>.from(res['data'] as Map));
      }
    } catch (_) {}
    if (mounted) await _showRevealPopup(revealed);
    _load();
  }

  Future<void> _showRevealPopup(ScratchCard c) {
    final p = V2Palette.of(v2ModeNotifier.value);
    return showDialog<void>(
      context: context,
      barrierColor: Colors.black.withOpacity(0.4),
      builder: (dialogContext) => V2Theme(
        palette: p,
        child: Dialog(
          backgroundColor: Colors.transparent,
          elevation: 0,
          insetPadding: const EdgeInsets.symmetric(horizontal: 34),
          child: _RevealCard(card: c),
        ),
      ),
    );
  }

  Future<void> _openRedeem() async {
    final result = await showModalBottomSheet<int>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (sheetContext) => _RedeemSheet(
        balance: _points,
        minPoints: _minPoints,
        pointsValue: _pointsValue,
      ),
    );
    if (result == null || result <= 0) return;
    setState(() => _redeeming = true);
    try {
      final res = await _api.post(ApiConstants.rewardPointsRedeem,
          data: {'points': result});
      final ok = res is Map && res['success'] == true;
      final amount = (res is Map && res['data'] is Map)
          ? v2Double((res['data'] as Map)['amount'])
          : 0.0;
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(
              content: Text(ok
                  ? (amount > 0
                      ? 'Redeemed — wallet credited ${formatCurrency(context, amount)}.'
                      : 'Points redeemed.')
                  : (res is Map
                      ? '${res['message'] ?? 'Could not redeem points.'}'
                      : 'Could not redeem points.'))));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(
              const SnackBar(content: Text('Could not redeem points.')));
      }
    }
    if (mounted) setState(() => _redeeming = false);
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      title: 'Scratch Cards',
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : RefreshIndicator(
              onRefresh: _load,
              color: p.accent,
              backgroundColor: p.bgMid,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 34),
                children: [
                  GlassPanel(
                    radius: 18,
                    strong: true,
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            Icon(Icons.stars_rounded,
                                color: p.warning, size: 24),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text('Reward points',
                                  style: TextStyle(
                                      color: p.ink,
                                      fontSize: 14,
                                      fontWeight: FontWeight.w800)),
                            ),
                            Text('$_points',
                                style: TextStyle(
                                    color: p.ink,
                                    fontSize: 20,
                                    fontWeight: FontWeight.w900)),
                          ],
                        ),
                        if (_redemption['enabled'] == true) ...[
                          const SizedBox(height: 12),
                          V2Tappable(
                            onTap: (_redeemEnabled && !_redeeming)
                                ? _openRedeem
                                : null,
                            child: Container(
                              height: 44,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: _redeemEnabled
                                    ? p.accent
                                    : p.accent.withOpacity(0.35),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Text(
                                _redeeming
                                    ? 'Redeeming…'
                                    : _redeemEnabled
                                        ? 'Redeem points to wallet'
                                        : 'Min $_minPoints points to redeem',
                                style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w800,
                                    fontSize: 13),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (_cards.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 40),
                      child: V2EmptyState(
                        icon: Icons.card_giftcard_rounded,
                        title: 'No scratch cards yet',
                        message: 'Place an order to earn one.',
                      ),
                    )
                  else
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: 12,
                        crossAxisSpacing: 12,
                        childAspectRatio: 0.82,
                      ),
                      itemCount: _cards.length,
                      itemBuilder: (_, i) => _Card(
                          card: _cards[i],
                          onReveal: () => _reveal(_cards[i])),
                    ),
                ],
              ),
            ),
    );
  }
}

class _RevealCard extends StatelessWidget {
  const _RevealCard({required this.card});
  final ScratchCard card;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return GlassPanel(
      radius: 26,
      strong: true,
      padding: const EdgeInsets.fromLTRB(24, 26, 24, 22),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          V2Entrance(
            offset: 0,
            child: Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: p.positive.withOpacity(0.14),
              ),
              child: Icon(Icons.redeem_rounded,
                  size: 42, color: p.positive),
            ),
          ),
          const SizedBox(height: 16),
          Text('You won',
              style: TextStyle(color: p.inkFaint, fontSize: 12.5)),
          const SizedBox(height: 4),
          Text(
            card.rewardTitle ?? card.title,
            textAlign: TextAlign.center,
            style: TextStyle(
                color: p.ink, fontSize: 20, fontWeight: FontWeight.w900),
          ),
          if ((card.restaurantName ?? '').isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(card.restaurantName!,
                style: TextStyle(color: p.inkFaint, fontSize: 12)),
          ],
          const SizedBox(height: 20),
          V2Tappable(
            onTap: () => Navigator.of(context).pop(),
            child: Container(
              height: 46,
              width: double.infinity,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: p.accent,
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Text('Awesome',
                  style: TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w900)),
            ),
          ),
        ],
      ),
    );
  }
}

class _RedeemSheet extends StatefulWidget {
  const _RedeemSheet({
    required this.balance,
    required this.minPoints,
    required this.pointsValue,
  });

  final int balance;
  final int minPoints;
  final double pointsValue;

  @override
  State<_RedeemSheet> createState() => _RedeemSheetState();
}

class _RedeemSheetState extends State<_RedeemSheet> {
  late int _points = widget.minPoints > 0 ? widget.minPoints : widget.balance;

  double get _cash =>
      widget.pointsValue > 0 ? _points / widget.pointsValue : 0;

  @override
  Widget build(BuildContext context) {
    final p = V2Palette.of(v2ModeNotifier.value);
    final steps = <int>{
      widget.minPoints,
      (widget.balance ~/ 2).clamp(widget.minPoints, widget.balance),
      widget.balance,
    }.where((v) => v >= widget.minPoints && v > 0).toList()
      ..sort();
    return V2Theme(
      palette: p,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: GlassPanel(
            radius: 24,
            strong: true,
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 22),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Redeem reward points',
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 17,
                        fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text('Balance: ${widget.balance} points',
                    style: TextStyle(color: p.inkFaint, fontSize: 12.5)),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final s in steps)
                      V2Tappable(
                        onTap: () => setState(() => _points = s),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 14, vertical: 9),
                          decoration: BoxDecoration(
                            color: _points == s
                                ? p.accent
                                : p.accent.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(
                                color: _points == s
                                    ? p.accent
                                    : p.accent.withOpacity(0.4)),
                          ),
                          child: Text('$s pts',
                              style: TextStyle(
                                  color: _points == s
                                      ? Colors.white
                                      : p.accent,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 12.5)),
                        ),
                      ),
                  ],
                ),
                if (_cash > 0) ...[
                  const SizedBox(height: 14),
                  Text(
                    '≈ ${formatCurrency(context, _cash)} to your wallet',
                    style: TextStyle(
                        color: p.positive,
                        fontSize: 13,
                        fontWeight: FontWeight.w700),
                  ),
                ],
                const SizedBox(height: 18),
                V2Tappable(
                  onTap: () => Navigator.of(context).pop(_points),
                  child: Container(
                    height: 48,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: p.accent,
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Text('Redeem $_points points',
                        style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w900)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.card, required this.onReveal});
  final ScratchCard card;
  final VoidCallback onReveal;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final revealed = card.status.toLowerCase().contains('reveal') ||
        card.revealedAt != null;
    return V2Tappable(
      onTap: revealed ? null : onReveal,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: revealed
                ? [p.positive.withOpacity(0.25), p.positive.withOpacity(0.10)]
                : [p.accent, Color.lerp(p.accent, Colors.black, 0.25)!],
          ),
          border: Border.all(color: p.glassBorder),
        ),
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              revealed ? Icons.redeem_rounded : Icons.card_giftcard_rounded,
              color: revealed ? p.positive : Colors.white,
              size: 26,
            ),
            const Spacer(),
            Text(
              revealed
                  ? (card.rewardTitle ?? card.title)
                  : 'Tap to scratch',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: revealed ? p.ink : Colors.white,
                fontSize: 13,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              card.restaurantName ?? card.orderNumber ?? '',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: revealed
                    ? p.inkFaint
                    : Colors.white.withOpacity(0.8),
                fontSize: 10.5,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
