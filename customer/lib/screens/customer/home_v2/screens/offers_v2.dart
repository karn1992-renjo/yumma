import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class OffersV2 extends StatefulWidget {
  const OffersV2({super.key});

  @override
  State<OffersV2> createState() => _OffersV2State();
}

class _OffersV2State extends State<OffersV2> {
  final ApiService _api = ApiService();
  bool _loading = true;
  List<Map<String, dynamic>> _offers = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      // The eligibility-filtered active offers (auto promos + coupon offers
      // the viewer can see) ...
      final res = await _api.get(ApiConstants.activeOffers);
      final active = v2MapList(v2ExtractList(res));
      // ... plus every published promotion (so admin coupon offers always
      // show here even if the viewer can't apply them right now).
      List<Map<String, dynamic>> all = const [];
      try {
        final r2 = await _api.get(ApiConstants.promotions);
        all = v2MapList(v2ExtractList(r2));
      } catch (_) {}

      final seen = <String>{};
      final merged = <Map<String, dynamic>>[];
      for (final o in [...active, ...all]) {
        final key = '${o['id'] ?? ''}:${o['code'] ?? o['coupon_code'] ?? ''}';
        if (o.isEmpty || !seen.add(key)) continue;
        merged.add(o);
      }
      _offers = merged;
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      title: 'Offers & Promos',
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : _offers.isEmpty
              ? const V2EmptyState(
                  icon: Icons.local_offer_outlined,
                  title: 'No offers right now',
                  message: 'Check back soon for fresh deals.',
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  color: p.accent,
                  backgroundColor: p.bgMid,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 34),
                    itemCount: _offers.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (_, i) => V2Entrance(
                      delay: Duration(milliseconds: 24 * i.clamp(0, 8)),
                      child: _OfferRow(data: _offers[i]),
                    ),
                  ),
                ),
    );
  }
}

class _OfferRow extends StatelessWidget {
  const _OfferRow({required this.data});
  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final title = (data['title'] ??
            data['name'] ??
            data['reward_text'] ??
            'Offer')
        .toString();
    final desc = (data['description'] ??
            data['subtitle'] ??
            data['restaurant_name'] ??
            '')
        .toString();
    final code = (data['code'] ?? data['coupon_code'] ?? '').toString().trim();
    final validity =
        (data['validity_text'] ?? data['valid_till'] ?? data['ends_at'] ?? '')
            .toString();

    return GlassPanel(
      radius: 20,
      strong: true,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: p.warning.withOpacity(p.isDark ? 0.22 : 0.14),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: p.warning.withOpacity(0.5)),
                ),
                child: Text('OFFER',
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 9.5,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1)),
              ),
              const Spacer(),
              if (validity.isNotEmpty)
                Text(validity,
                    style: TextStyle(color: p.inkFaint, fontSize: 10.5)),
            ],
          ),
          const SizedBox(height: 10),
          Text(title,
              style: TextStyle(
                  color: p.ink, fontSize: 15, fontWeight: FontWeight.w800)),
          if (desc.trim().isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(desc,
                style: TextStyle(
                    color: p.inkFaint, fontSize: 12, height: 1.35)),
          ],
          if (code.isNotEmpty) ...[
            const SizedBox(height: 14),
            _CodeChip(code: code),
          ],
        ],
      ),
    );
  }
}

class _CodeChip extends StatelessWidget {
  const _CodeChip({required this.code});
  final String code;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: () {
        Clipboard.setData(ClipboardData(text: code));
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text('Copied "$code"')));
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: p.accent.withOpacity(0.5),
            style: BorderStyle.solid,
          ),
          color: p.accent.withOpacity(0.08),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(code,
                style: TextStyle(
                    color: p.accent,
                    fontWeight: FontWeight.w900,
                    fontSize: 13,
                    letterSpacing: 1)),
            const SizedBox(width: 8),
            Icon(Icons.copy_rounded, size: 14, color: p.accent),
          ],
        ),
      ),
    );
  }
}
