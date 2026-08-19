import 'package:flutter/material.dart';
import 'package:intl/intl.dart' show DateFormat;

import '../../config/api_constants.dart';
import '../../models/scratch_card.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/app_cached_image.dart';
import '../../widgets/common/app_skeleton.dart';
import '../../widgets/customer/scratch_card_art.dart';
import '../../widgets/customer/scratch_card_reveal_dialog.dart';

const Color _scratchOrange = Color(0xFFFF5A00);
const Color _scratchAmber = Color(0xFFFF9900);
const Color _scratchPeach = Color(0xFFFFF2E8);
const Color _scratchLine = Color(0xFFFFE3CF);

class ScratchCardsScreen extends StatefulWidget {
  const ScratchCardsScreen({super.key, this.orderId});

  final int? orderId;

  @override
  State<ScratchCardsScreen> createState() => _ScratchCardsScreenState();
}

class _ScratchCardsScreenState extends State<ScratchCardsScreen> {
  final ApiService _api = ApiService();
  List<ScratchCard> _cards = const [];
  int _pointsBalance = 0;
  List<Map<String, dynamic>> _pointTransactions = const [];
  Map<String, dynamic> _pointsRedemption = const {};
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadCards();
  }

  Future<void> _loadCards({bool forceRefresh = false}) async {
    setState(() => _loading = _cards.isEmpty && _pointTransactions.isEmpty);
    try {
      final responses = await Future.wait([
        _api.get(
          ApiConstants.scratchCards,
          cachePolicy: ApiCachePolicy.screen,
          cacheFirst: !forceRefresh,
          refreshCached: !forceRefresh,
          onCacheRefreshed: (_) {
            if (mounted) _loadCards(forceRefresh: true);
          },
        ),
        _api.get(
          ApiConstants.rewardPoints,
          cachePolicy: ApiCachePolicy.screen,
          cacheFirst: !forceRefresh,
          refreshCached: !forceRefresh,
        ),
      ]);
      final response = responses.first;
      final pointsResponse = responses.last;
      final rows = response is Map &&
              response['success'] == true &&
              response['data'] is List
          ? (response['data'] as List)
          : const [];
      final cards = rows
          .whereType<Map>()
          .map((row) => ScratchCard.fromJson(Map<String, dynamic>.from(row)))
          .where(
            (card) => widget.orderId == null || card.orderId == widget.orderId,
          )
          .toList(growable: false);
      final pointsData = pointsResponse is Map && pointsResponse['data'] is Map
          ? Map<String, dynamic>.from(pointsResponse['data'] as Map)
          : const <String, dynamic>{};
      final transactions = pointsData['transactions'] is List
          ? (pointsData['transactions'] as List)
              .whereType<Map>()
              .map((row) => Map<String, dynamic>.from(row))
              .toList(growable: false)
          : const <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() {
        _cards = cards;
        _pointsBalance = _toInt(pointsData['balance']);
        _pointTransactions = transactions;
        _pointsRedemption = pointsData['redemption'] is Map
            ? Map<String, dynamic>.from(pointsData['redemption'] as Map)
            : const <String, dynamic>{};
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openScratchCard(ScratchCard card) async {
    if (card.isExpired) return;
    if (card.isRevealLocked) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(card.revealLockedReason ??
              'Scratch card reward can be claimed after delivery.'),
        ),
      );
      return;
    }
    final updated = await showScratchCardRevealDialog(context, card: card);
    if (!mounted || updated == null) return;
    setState(() {
      _cards = _cards
          .map((item) => item.id == updated.id ? updated : item)
          .toList(growable: false);
    });
    await _loadRewardPoints();
  }

  Future<void> _loadRewardPoints() async {
    try {
      final response = await _api.get(
        ApiConstants.rewardPoints,
        cachePolicy: ApiCachePolicy.screen,
        cacheFirst: false,
      );
      final data = response is Map && response['data'] is Map
          ? Map<String, dynamic>.from(response['data'] as Map)
          : const <String, dynamic>{};
      final transactions = data['transactions'] is List
          ? (data['transactions'] as List)
              .whereType<Map>()
              .map((row) => Map<String, dynamic>.from(row))
              .toList(growable: false)
          : const <Map<String, dynamic>>[];
      if (!mounted) return;
      setState(() {
        _pointsBalance = _toInt(data['balance']);
        _pointTransactions = transactions;
        _pointsRedemption = data['redemption'] is Map
            ? Map<String, dynamic>.from(data['redemption'] as Map)
            : const <String, dynamic>{};
      });
    } catch (_) {}
  }

  Future<void> _openRedeemPointsSheet() async {
    final redeemed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _RewardPointsRedeemSheet(
        balance: _pointsBalance,
        settings: _pointsRedemption,
        onRedeem: _redeemRewardPoints,
      ),
    );
    if (redeemed == true) {
      await _loadRewardPoints();
    }
  }

  Future<bool> _redeemRewardPoints(int points) async {
    try {
      final response = await _api.post(
        ApiConstants.rewardPointsRedeem,
        data: {'points': points},
      );
      if (!mounted) return false;
      if (response is Map && response['success'] == true) {
        final data = response['data'] is Map
            ? Map<String, dynamic>.from(response['data'] as Map)
            : const <String, dynamic>{};
        final amount = _toDouble(data['amount']);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              amount > 0
                  ? 'Points redeemed. Wallet credited with ${formatCurrency(context, amount)}.'
                  : 'Points redeemed successfully.',
            ),
            backgroundColor: const Color(0xFF0E8F45),
          ),
        );
        return true;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(response['message']?.toString() ??
              'Unable to redeem reward points.'),
          backgroundColor: FoodFlowTheme.danger,
        ),
      );
      return false;
    } catch (error) {
      if (!mounted) return false;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_cleanError(error.toString())),
          backgroundColor: FoodFlowTheme.danger,
        ),
      );
      return false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final available =
        _cards.where((card) => !card.isRevealed && !card.isExpired).toList();
    final scratched =
        _cards.where((card) => card.isRevealed && !card.isExpired).toList();
    final expired = _cards.where((card) => card.isExpired).toList();
    final primaryCard = available.isNotEmpty
        ? available.first
        : (scratched.isNotEmpty
            ? scratched.first
            : (expired.isNotEmpty ? expired.first : null));

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        centerTitle: true,
        title: const Text(
          'Scratch Card',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
        ),
        backgroundColor: Colors.white,
        foregroundColor: FoodFlowTheme.ink,
        surfaceTintColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            onPressed: _showInfo,
            icon: const Icon(Icons.help_outline_rounded),
          ),
        ],
      ),
      body: _loading
          ? const AppSkeletonListView(itemCount: 4, itemHeight: 126)
          : RefreshIndicator(
              onRefresh: () => _loadCards(forceRefresh: true),
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                children: [
                  const _ScratchHero(),
                  const SizedBox(height: 20),
                  _ScratchCountStrip(
                    count: available.length,
                    onHowToPlay: _showInfo,
                  ),
                  const SizedBox(height: 18),
                  if (primaryCard != null)
                    _PrimaryScratchCard(
                      card: primaryCard,
                      onTap: () => _openScratchCard(primaryCard),
                    )
                  else
                    const _EmptyScratchState(),
                  const SizedBox(height: 18),
                  const _TermsCard(),
                  const SizedBox(height: 22),
                  _RewardsSection(cards: _cards, onTap: _openScratchCard),
                  const SizedBox(height: 14),
                  _RewardPointsPanel(
                    balance: _pointsBalance,
                    transactions: _pointTransactions,
                    settings: _pointsRedemption,
                    onRedeem: _openRedeemPointsSheet,
                  ),
                  const SizedBox(height: 14),
                  const _MoreRewardsBanner(),
                ],
              ),
            ),
    );
  }

  void _showInfo() {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => const _HowItWorksSheet(),
    );
  }

  int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.round();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _cleanError(String message) {
    final trimmed = message.trim();
    return trimmed.startsWith('Exception: ')
        ? trimmed.substring('Exception: '.length)
        : trimmed;
  }
}

class _ScratchHero extends StatefulWidget {
  const _ScratchHero();

  @override
  State<_ScratchHero> createState() => _ScratchHeroState();
}

class _ScratchHeroState extends State<_ScratchHero>
    with SingleTickerProviderStateMixin {
  late final AnimationController _float;

  @override
  void initState() {
    super.initState();
    _float = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2200),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _float.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 166,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(child: CustomPaint(painter: _HeroConfettiPainter())),
          const Positioned(
            left: 18,
            right: 110,
            top: 28,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Scratch & Win',
                  style: TextStyle(
                    color: _scratchOrange,
                    fontSize: 30,
                    fontWeight: FontWeight.w900,
                    height: 1,
                  ),
                ),
                SizedBox(height: 10),
                Text(
                  'Exciting Rewards!',
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 19,
                    fontWeight: FontWeight.w900,
                    height: 1.05,
                  ),
                ),
                SizedBox(height: 18),
                Text(
                  'Scratch the card and get a surprise reward',
                  style: TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Positioned(
            right: 8,
            top: 50,
            child: AnimatedBuilder(
              animation: _float,
              builder: (context, child) => Transform.translate(
                offset: Offset(0, -6 * _float.value),
                child: child,
              ),
              child: const ScratchGiftBox(size: 94),
            ),
          ),
        ],
      ),
    );
  }
}

class _ScratchCountStrip extends StatelessWidget {
  const _ScratchCountStrip({required this.count, required this.onHowToPlay});

  final int count;
  final VoidCallback onHowToPlay;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 46,
          height: 46,
          decoration: const BoxDecoration(
            color: _scratchPeach,
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.card_giftcard_rounded, color: _scratchOrange),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text.rich(
            TextSpan(
              children: [
                const TextSpan(text: 'You have '),
                TextSpan(
                  text: '$count',
                  style: const TextStyle(color: _scratchOrange),
                ),
                const TextSpan(text: ' scratch cards'),
              ],
            ),
            style: const TextStyle(
              color: FoodFlowTheme.inkSoft,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        ElevatedButton(
          onPressed: onHowToPlay,
          style: ElevatedButton.styleFrom(
            backgroundColor: _scratchOrange,
            foregroundColor: Colors.white,
            elevation: 0,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            shape:
                RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          ),
          child: const Text(
            'How to play?',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
          ),
        ),
      ],
    );
  }
}

class _PrimaryScratchCard extends StatelessWidget {
  const _PrimaryScratchCard({required this.card, required this.onTap});

  final ScratchCard card;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: card.isExpired ? null : onTap,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: _scratchOrange.withOpacity(0.18),
              blurRadius: 24,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: ScratchCardFace(card: card, height: 314, showOrderTag: false),
      ),
    );
  }
}

class _EmptyScratchState extends StatelessWidget {
  const _EmptyScratchState();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(22, 28, 22, 26),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: _scratchLine),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        children: [
          const ScratchGiftBox(size: 132),
          const SizedBox(height: 14),
          const Text(
            'No Scratch Cards Yet',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Place an order and unlock exciting scratch cards',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 13,
              fontWeight: FontWeight.w700,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () => Navigator.pushNamedAndRemoveUntil(
                context,
                '/home',
                (route) => false,
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: _scratchOrange,
                foregroundColor: Colors.white,
                elevation: 0,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8)),
              ),
              child: const Text(
                'Order Now',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TermsCard extends StatelessWidget {
  const _TermsCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFF1F2F4)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.description_outlined, color: _scratchOrange, size: 22),
              SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Terms & Conditions',
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Icon(Icons.keyboard_arrow_down_rounded, color: FoodFlowTheme.ink),
            ],
          ),
          SizedBox(height: 12),
          _TermLine('Each scratch card can be used only once.'),
          SizedBox(height: 8),
          _TermLine('Rewards are valid for a limited time.'),
          SizedBox(height: 8),
          _TermLine('Coupons are applicable on select orders.'),
        ],
      ),
    );
  }
}

class _TermLine extends StatelessWidget {
  const _TermLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 4,
          height: 4,
          margin: const EdgeInsets.only(top: 7, right: 8),
          decoration: const BoxDecoration(
            color: FoodFlowTheme.inkSoft,
            shape: BoxShape.circle,
          ),
        ),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: FoodFlowTheme.inkSoft,
              fontSize: 12,
              fontWeight: FontWeight.w700,
              height: 1.35,
            ),
          ),
        ),
      ],
    );
  }
}

class _RewardsSection extends StatelessWidget {
  const _RewardsSection({required this.cards, required this.onTap});

  final List<ScratchCard> cards;
  final ValueChanged<ScratchCard> onTap;

  @override
  Widget build(BuildContext context) {
    final rewards = cards.where((card) => card.isRevealed).toList();
    final pending = cards.where((card) => !card.isRevealed).toList();
    final visible = [...rewards, ...pending];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                'Your Rewards',
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            if (cards.length > 2)
              const Text(
                'View all',
                style: TextStyle(
                  color: _scratchOrange,
                  fontSize: 12,
                  fontWeight: FontWeight.w900,
                ),
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (visible.isEmpty)
          const Text(
            'Your scratched rewards will appear here',
            style: TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          )
        else
          ...visible.take(4).map(
                (card) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _RewardTile(card: card, onTap: () => onTap(card)),
                ),
              ),
      ],
    );
  }
}

class _RewardTile extends StatelessWidget {
  const _RewardTile({required this.card, required this.onTap});

  final ScratchCard card;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final disabled = card.isExpired;
    final statusText =
        disabled ? 'Expired' : (card.isRevealed ? 'Used' : 'Available');
    final statusColor = disabled
        ? FoodFlowTheme.faint
        : (card.isRevealed ? const Color(0xFF0E8F45) : _scratchOrange);
    return InkWell(
      onTap: disabled ? null : onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFF1F2F4)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.035),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Row(
          children: [
            _RewardThumb(card: card),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    card.isRevealed
                        ? ScratchRewardText.title(context, card)
                        : card.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    card.isRevealed
                        ? ScratchRewardText.subtitle(card)
                        : ScratchRewardText.expiresLabel(card),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (card.restaurantName?.isNotEmpty == true) ...[
                    const SizedBox(height: 4),
                    Text(
                      card.restaurantName!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: FoodFlowTheme.faint,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: statusColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    statusText,
                    style: TextStyle(
                      color: statusColor,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  _dateLabel(card),
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _dateLabel(ScratchCard card) {
    final date = card.scratchedAt ?? card.issuedAt ?? card.expiresAt;
    if (date == null) return '';
    return DateFormat('d MMM yyyy').format(date);
  }
}

class _RewardThumb extends StatelessWidget {
  const _RewardThumb({required this.card});

  final ScratchCard card;

  String get _rewardType {
    final reward = card.reward ?? const <String, dynamic>{};
    return card.rewardType ?? reward['type']?.toString() ?? '';
  }

  IconData get _icon {
    if (!card.isRevealed) return Icons.card_giftcard_rounded;
    switch (_rewardType) {
      case 'wallet_cashback':
      case 'wallet_credit':
      case 'cashback':
        return Icons.account_balance_wallet_rounded;
      case 'reward_points':
        return Icons.stars_rounded;
      case 'gift_voucher':
      case 'gift_card':
        return Icons.card_giftcard_rounded;
      case 'no_reward':
        return Icons.sentiment_neutral_rounded;
      default:
        return Icons.local_offer_rounded;
    }
  }

  Color get _color =>
      card.isRevealed ? const Color(0xFF0E8F45) : _scratchOrange;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 52,
      height: 52,
      decoration: BoxDecoration(
        color: card.isRevealed ? const Color(0xFFEAF8EE) : _scratchPeach,
        shape: BoxShape.circle,
      ),
      clipBehavior: Clip.antiAlias,
      child: card.restaurantLogoUrl?.isNotEmpty == true
          ? AppCachedImage(
              imageUrl: card.restaurantLogoUrl!,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => _fallbackIcon(),
            )
          : _fallbackIcon(),
    );
  }

  Widget _fallbackIcon() {
    return Icon(_icon, color: _color, size: 24);
  }
}

class _MoreRewardsBanner extends StatelessWidget {
  const _MoreRewardsBanner();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFFB26A), Color(0xFFFF8D36)],
        ),
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: _scratchOrange.withOpacity(0.16),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          const ScratchGiftBox(size: 66),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'More cards, More rewards!',
                  style: TextStyle(
                    color: _scratchOrange,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Keep ordering and win exciting rewards.',
                  style: TextStyle(
                    color: _scratchOrange,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          ElevatedButton(
            onPressed: () => Navigator.pushNamedAndRemoveUntil(
                context, '/home', (route) => false),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: _scratchOrange,
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8)),
            ),
            child: const Text(
              'Explore Offers',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
            ),
          ),
        ],
      ),
    );
  }
}

class _HowItWorksSheet extends StatelessWidget {
  const _HowItWorksSheet();

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'How it works',
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                  color: FoodFlowTheme.ink,
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Row(
              children: [
                Expanded(
                  child: _HowStep(
                    icon: Icons.confirmation_number_outlined,
                    title: 'Step 1',
                    body: 'You will get scratch cards on offers & events',
                  ),
                ),
                _StepArrow(),
                Expanded(
                  child: _HowStep(
                    icon: Icons.touch_app_outlined,
                    title: 'Step 2',
                    body: 'Scratch the card to reveal your reward',
                  ),
                ),
                _StepArrow(),
                Expanded(
                  child: _HowStep(
                    icon: Icons.card_giftcard_rounded,
                    title: 'Step 3',
                    body: 'Claim your reward and use it on your next order',
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _HowStep extends StatelessWidget {
  const _HowStep({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 58,
          height: 58,
          decoration: BoxDecoration(
            color: _scratchPeach,
            shape: BoxShape.circle,
            border: Border.all(color: _scratchLine),
          ),
          child: Icon(icon, color: _scratchOrange, size: 26),
        ),
        const SizedBox(height: 10),
        Text(
          title,
          style: const TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 12,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          body,
          textAlign: TextAlign.center,
          maxLines: 4,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: FoodFlowTheme.inkSoft,
            fontSize: 10,
            fontWeight: FontWeight.w700,
            height: 1.2,
          ),
        ),
      ],
    );
  }
}

class _StepArrow extends StatelessWidget {
  const _StepArrow();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.only(bottom: 46),
      child: Icon(Icons.arrow_forward_rounded, color: FoodFlowTheme.muted),
    );
  }
}

class _HeroConfettiPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    const colors = [
      _scratchOrange,
      Color(0xFFFFB300),
      Color(0xFF54A7C5),
      Color(0xFFE38D62),
      Color(0xFFD673B1),
    ];
    for (var i = 0; i < 20; i++) {
      final x = ((i * 41) % 100) / 100 * size.width;
      final y = ((i * 29) % 100) / 100 * size.height;
      final paint = Paint()
        ..color = colors[i % colors.length].withOpacity(0.8)
        ..strokeWidth = i.isEven ? 4 : 3
        ..strokeCap = StrokeCap.round;
      canvas.save();
      canvas.translate(x, y);
      canvas.rotate(i * 0.35);
      if (i % 3 == 0) {
        canvas.drawLine(const Offset(-5, 0), const Offset(5, 0), paint);
      } else if (i % 3 == 1) {
        canvas.drawCircle(Offset.zero, 3, paint..style = PaintingStyle.fill);
      } else {
        final path = Path()
          ..moveTo(0, -6)
          ..lineTo(3, -1)
          ..lineTo(8, 0)
          ..lineTo(3, 1)
          ..lineTo(0, 6)
          ..lineTo(-3, 1)
          ..lineTo(-8, 0)
          ..lineTo(-3, -1)
          ..close();
        canvas.drawPath(path, paint..style = PaintingStyle.fill);
      }
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(covariant _HeroConfettiPainter oldDelegate) => false;
}

class _RewardPointsPanel extends StatelessWidget {
  const _RewardPointsPanel({
    required this.balance,
    required this.transactions,
    required this.settings,
    required this.onRedeem,
  });

  final int balance;
  final List<Map<String, dynamic>> transactions;
  final Map<String, dynamic> settings;
  final VoidCallback onRedeem;

  @override
  Widget build(BuildContext context) {
    final last = transactions.isNotEmpty ? transactions.first : null;
    final lastPoints = last == null ? 0 : _toInt(last['points']);
    final lastType = last?['type']?.toString() ?? '';
    final configured = settings['configured'] == true;
    final enabled = settings['enabled'] == true;
    final minimumPoints = _toInt(settings['minimum_points']);
    final pointsValue = _toDouble(settings['points_per_currency']);
    final canRedeem = balance > 0 && configured && enabled;
    final helper = !configured
        ? 'Redemption setup pending'
        : (!enabled
            ? 'Redemption disabled'
            : [
                if (minimumPoints > 0) 'Min $minimumPoints points',
                if (pointsValue > 0)
                  '${_trimNumber(pointsValue)} points = 1 wallet unit',
              ].join(' | '));
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFF7A00), Color(0xFFFFA726)],
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: FoodFlowTheme.tagOrange.withOpacity(0.24),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.18),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(
              Icons.stars_rounded,
              color: Colors.white,
              size: 26,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Reward Points',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$balance points available',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (helper.isNotEmpty || last != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    helper.isNotEmpty
                        ? helper
                        : '${lastType == 'debit' ? 'Redeemed' : 'Last credited'} $lastPoints points',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.82),
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 10),
          ElevatedButton(
            onPressed: canRedeem ? onRedeem : null,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: FoodFlowTheme.tagOrange,
              disabledBackgroundColor: Colors.white.withOpacity(0.46),
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            child: const Text(
              'Redeem',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
            ),
          ),
        ],
      ),
    );
  }

  static int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.round();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  static String _trimNumber(double value) {
    if (value == value.roundToDouble()) return value.toStringAsFixed(0);
    return value
        .toStringAsFixed(4)
        .replaceFirst(RegExp(r'0+$'), '')
        .replaceFirst(RegExp(r'\.$'), '');
  }
}

class _RewardPointsRedeemSheet extends StatefulWidget {
  const _RewardPointsRedeemSheet({
    required this.balance,
    required this.settings,
    required this.onRedeem,
  });

  final int balance;
  final Map<String, dynamic> settings;
  final Future<bool> Function(int points) onRedeem;

  @override
  State<_RewardPointsRedeemSheet> createState() =>
      _RewardPointsRedeemSheetState();
}

class _RewardPointsRedeemSheetState extends State<_RewardPointsRedeemSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _controller;
  bool _submitting = false;

  int get _minimumPoints => _toInt(widget.settings['minimum_points']);

  double get _pointsValue => _toDouble(widget.settings['points_per_currency']);

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.balance.toString());
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() => _submitting = true);
    final points = int.parse(_controller.text.trim());
    final ok = await widget.onRedeem(points);
    if (!mounted) return;
    setState(() => _submitting = false);
    if (ok) Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(18, 10, 18, 18),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 42,
                    height: 5,
                    decoration: BoxDecoration(
                      color: const Color(0xFFDCE1EA),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                const Text(
                  'Redeem Reward Points',
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 19,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  [
                    '${widget.balance} points available.',
                    if (_minimumPoints > 0)
                      'Minimum redeem is $_minimumPoints points.',
                    if (_pointsValue > 0)
                      '${_trimNumber(_pointsValue)} points convert to 1 wallet unit.',
                  ].join(' '),
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _controller,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Points to redeem',
                    prefixIcon: const Icon(Icons.stars_rounded),
                    filled: true,
                    fillColor: FoodFlowTheme.canvas,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: BorderSide.none,
                    ),
                  ),
                  validator: (value) {
                    final points = int.tryParse(value?.trim() ?? '');
                    if (points == null || points <= 0) {
                      return 'Enter valid points';
                    }
                    if (points > widget.balance) {
                      return 'Insufficient reward points';
                    }
                    if (_minimumPoints > 0 && points < _minimumPoints) {
                      return 'Minimum redeem is $_minimumPoints points';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _submitting ? null : _submit,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: FoodFlowTheme.tagOrange,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 15),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    child: _submitting
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Text(
                            'Redeem to Wallet',
                            style: TextStyle(fontWeight: FontWeight.w900),
                          ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  static int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.round();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  static String _trimNumber(double value) {
    if (value == value.roundToDouble()) return value.toStringAsFixed(0);
    return value
        .toStringAsFixed(4)
        .replaceFirst(RegExp(r'0+$'), '')
        .replaceFirst(RegExp(r'\.$'), '');
  }
}
