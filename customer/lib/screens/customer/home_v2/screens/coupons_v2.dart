import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import '../../../../utils/currency_utils.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

/// "All coupons" — the reward coupons the customer earned / was assigned, plus
/// the admin-configured promo codes for this restaurant. Tap a card to apply.
class CouponsV2 extends StatefulWidget {
  const CouponsV2({
    super.key,
    required this.restaurantId,
    this.appliedCode = '',
  });

  final int restaurantId;
  final String appliedCode;

  @override
  State<CouponsV2> createState() => _CouponsV2State();
}

class _CouponsV2State extends State<CouponsV2> {
  final ApiService _api = ApiService();

  bool _loading = true;
  List<Map<String, dynamic>> _rewards = const [];
  List<Map<String, dynamic>> _promos = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await Future.wait<dynamic>([
      _api.get(ApiConstants.rewardCoupons).catchError((_) => null),
      _api
          .get(ApiConstants.promotions, queryParams: {
            'restaurant_id': widget.restaurantId,
            'platform': 'app',
          })
          .catchError((_) => null),
    ]);

    // Reward coupons the customer holds and hasn't spent.
    final rewardRows = _extractList(res[0])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .where((c) =>
            (c['status'] ?? 'unused').toString().toLowerCase() == 'unused')
        .toList();

    // Admin / restaurant promotions that carry a code the user can type in.
    final promoRows = _extractList(res[1])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .where((p) {
          final code = (p['coupon_code'] ?? p['code'] ?? '').toString().trim();
          final mode =
              (p['application_mode'] ?? '').toString().toLowerCase();
          // Automatic promos apply themselves — only surface code-based ones.
          return code.isNotEmpty && mode != 'automatic';
        })
        .toList();

    if (!mounted) return;
    setState(() {
      _rewards = rewardRows;
      _promos = promoRows;
      _loading = false;
    });
  }

  List<dynamic> _extractList(dynamic res) {
    if (res is List) return res;
    if (res is Map) {
      final d = res['data'];
      if (d is List) return d;
      if (d is Map && d['data'] is List) return d['data'] as List;
      if (d is Map && d['promotions'] is List) return d['promotions'] as List;
      if (res['coupons'] is List) return res['coupons'] as List;
    }
    return const [];
  }

  void _apply(String code) {
    if (code.trim().isEmpty) return;
    Navigator.of(context).pop(code.trim());
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final hasAny = _rewards.isNotEmpty || _promos.isNotEmpty;
    return V2Scaffold(
      title: 'Coupons & offers',
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : !hasAny
              ? const V2EmptyState(
                  icon: Icons.local_activity_outlined,
                  title: 'No coupons right now',
                  message:
                      'Earned scratch-card coupons and running promo codes '
                      'will show up here.',
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  color: p.accent,
                  backgroundColor: p.bgMid,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
                    children: [
                      if (_rewards.isNotEmpty) ...[
                        _sectionLabel(context, 'YOUR REWARD COUPONS'),
                        for (final c in _rewards)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: V2Entrance(
                              child: PromoCouponCard(
                                data: _rewardShape(c),
                                applied: _isApplied(c),
                                onApply: () => _apply(_codeOf(c)),
                              ),
                            ),
                          ),
                        const SizedBox(height: 10),
                      ],
                      if (_promos.isNotEmpty) ...[
                        _sectionLabel(context, 'OFFERS FOR THIS RESTAURANT'),
                        for (final c in _promos)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: V2Entrance(
                              child: PromoCouponCard(
                                data: c,
                                applied: _isApplied(c),
                                onApply: () => _apply(_codeOf(c)),
                              ),
                            ),
                          ),
                      ],
                    ],
                  ),
                ),
    );
  }

  bool _isApplied(Map<String, dynamic> c) =>
      widget.appliedCode.isNotEmpty &&
      _codeOf(c).toUpperCase() == widget.appliedCode.toUpperCase();

  String _codeOf(Map<String, dynamic> c) =>
      (c['coupon_code'] ?? c['code'] ?? '').toString();

  /// Flatten a reward-coupon row (which nests its promotion) to the same shape
  /// [PromoCouponCard] reads from a promotion payload.
  Map<String, dynamic> _rewardShape(Map<String, dynamic> c) {
    final promo = c['promotion'] is Map
        ? Map<String, dynamic>.from(c['promotion'] as Map)
        : const <String, dynamic>{};
    return {
      ...promo,
      'code': c['code'] ?? promo['code'] ?? promo['coupon_code'],
      'coupon_code': c['code'] ?? promo['coupon_code'],
      'title': promo['title'] ?? c['title'] ?? 'Reward coupon',
      'description': promo['description'] ?? c['description'],
      'is_reward': true,
      'expires_at': c['expires_at'] ?? promo['end_date'] ?? promo['valid_to'],
    };
  }

  Widget _sectionLabel(BuildContext context, String t) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 10),
      child: Text(t,
          style: TextStyle(
              color: p.inkFaint,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.7)),
    );
  }
}

/// A ticket-style promo coupon — a light body with a bold accent stub on the
/// right, a perforated tear edge, and notch cut-outs (matches the uploaded
/// coupon-template look, in the V2 palette).
class PromoCouponCard extends StatelessWidget {
  const PromoCouponCard({
    super.key,
    required this.data,
    required this.onApply,
    this.applied = false,
  });

  final Map<String, dynamic> data;
  final VoidCallback onApply;
  final bool applied;

  static const double _stub = 96;

  String get _code =>
      (data['coupon_code'] ?? data['code'] ?? '').toString().trim();

  /// "50%" / "₹50" for the stub headline.
  String _amount(BuildContext context) {
    final type = (data['discount_type'] ?? data['type'] ?? '')
        .toString()
        .toLowerCase();
    final value = v2Double(data['discount_value'] ?? data['value']);
    if (value <= 0) return 'DEAL';
    if (type.contains('percent') || type == 'percentage' || type == 'percent') {
      return '${value.round()}%';
    }
    return formatCurrency(context, value);
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final title = (data['title'] ?? data['name'] ?? 'Offer').toString();
    final amount = _amount(context);
    final minOrder =
        v2Double(data['min_order_amount'] ?? data['min_order_value']);
    final maxDisc =
        v2Double(data['max_discount'] ?? data['max_discount_amount']);
    final validUntil = _prettyDate((data['expires_at'] ??
            data['end_date'] ??
            data['valid_to'] ??
            '')
        .toString());

    final sub = <String>[
      if (minOrder > 0) 'Min ${formatCurrency(context, minOrder)}',
      if (maxDisc > 0) 'Up to ${formatCurrency(context, maxDisc)}',
    ].join('  ·  ');

    final body = ClipRRect(
      borderRadius: BorderRadius.circular(18),
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border.all(
              color: applied ? p.accent : p.glassBorder,
              width: applied ? 1.6 : 1),
          borderRadius: BorderRadius.circular(18),
        ),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // -- body -------------------------------------------------
              Expanded(
                child: Container(
                  color: p.isDark
                      ? const Color(0xFF161D2E)
                      : Colors.white,
                  padding: const EdgeInsets.fromLTRB(16, 14, 14, 14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.confirmation_num_rounded,
                              size: 14, color: p.accent),
                          const SizedBox(width: 6),
                          Text('COUPON',
                              style: TextStyle(
                                  color: p.inkFaint,
                                  fontSize: 10,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: 2)),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                              color: p.ink,
                              fontSize: 14.5,
                              height: 1.15,
                              fontWeight: FontWeight.w900)),
                      if (_code.isNotEmpty) ...[
                        const SizedBox(height: 9),
                        Row(
                          children: [
                            _CodeChip(code: _code),
                            const SizedBox(width: 8),
                            V2Tappable(
                              onTap: () {
                                Clipboard.setData(
                                    ClipboardData(text: _code));
                                ScaffoldMessenger.of(context)
                                  ..hideCurrentSnackBar()
                                  ..showSnackBar(const SnackBar(
                                      content: Text('Code copied')));
                              },
                              child: Icon(Icons.copy_rounded,
                                  size: 14, color: p.inkFaint),
                            ),
                          ],
                        ),
                      ],
                      if (sub.isNotEmpty) ...[
                        const SizedBox(height: 7),
                        Text(sub,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                                color: p.inkFaint,
                                fontSize: 10.5,
                                fontWeight: FontWeight.w600)),
                      ],
                      const SizedBox(height: 3),
                      Text(
                        validUntil == null
                            ? 'NO EXPIRY'
                            : 'VALID UNTIL $validUntil',
                        style: TextStyle(
                            color: p.inkFaint,
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.8),
                      ),
                      const SizedBox(height: 10),
                      V2Tappable(
                        onTap: applied ? null : onApply,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 8),
                          decoration: BoxDecoration(
                            color: applied
                                ? p.positive.withOpacity(0.16)
                                : p.accent,
                            borderRadius: BorderRadius.circular(9),
                          ),
                          child: Text(
                            applied ? 'APPLIED' : 'APPLY',
                            style: TextStyle(
                                color:
                                    applied ? p.positive : Colors.white,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0.6),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              // -- perforation ---------------------------------------
              _Perforation(color: p.glassBorder),
              // -- accent stub -------------------------------------
              Container(
                width: _stub,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      p.accent,
                      Color.lerp(p.accent, Colors.black, 0.20)!,
                    ],
                  ),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(amount,
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 27,
                            height: 1,
                            fontWeight: FontWeight.w900)),
                    const SizedBox(height: 2),
                    Text('OFF',
                        style: TextStyle(
                            color: Colors.white.withOpacity(0.85),
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 4)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );

    return Stack(
      clipBehavior: Clip.none,
      children: [
        body,
        Positioned(
          right: _stub - 7,
          top: -7,
          child: _Notch(color: p.bgTop),
        ),
        Positioned(
          right: _stub - 7,
          bottom: -7,
          child: _Notch(color: p.bgTop),
        ),
      ],
    );
  }

  static String? _prettyDate(String raw) {
    final s = raw.trim();
    if (s.isEmpty) return null;
    final dt = DateTime.tryParse(s);
    if (dt == null) return null;
    const m = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    final l = dt.toLocal();
    return '${l.day} ${m[l.month - 1]} ${l.year}';
  }
}

class _CodeChip extends StatelessWidget {
  const _CodeChip({required this.code});
  final String code;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: p.accent.withOpacity(0.10),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: p.accent.withOpacity(0.5)),
      ),
      child: Text(code,
          style: TextStyle(
              color: p.accent,
              fontSize: 12,
              fontWeight: FontWeight.w900,
              letterSpacing: 1)),
    );
  }
}

class _Notch extends StatelessWidget {
  const _Notch({required this.color});
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
        width: 14,
        height: 14,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      );
}

/// Vertical dashed tear line between the coupon body and its accent stub.
class _Perforation extends StatelessWidget {
  const _Perforation({required this.color});
  final Color color;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 1,
      child: LayoutBuilder(
        builder: (context, c) {
          const dash = 4.0;
          final count = (c.maxHeight / (dash * 2)).floor().clamp(0, 200);
          return Column(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: List.generate(
              count,
              (_) => Container(width: 1, height: dash, color: color),
            ),
          );
        },
      ),
    );
  }
}
