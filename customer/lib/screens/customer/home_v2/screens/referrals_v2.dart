import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../../../../config/api_constants.dart';
import '../../../../config/app_config.dart';
import '../../../../services/api_service.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class ReferralsV2 extends StatefulWidget {
  const ReferralsV2({super.key});

  @override
  State<ReferralsV2> createState() => _ReferralsV2State();
}

class _ReferralsV2State extends State<ReferralsV2> {
  final ApiService _api = ApiService();
  bool _loading = true;
  Map<String, dynamic> _data = const {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await _api.get(ApiConstants.referralSummary);
      if (res is Map && res['data'] is Map) {
        _data = Map<String, dynamic>.from(res['data'] as Map);
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final code = (_data['referral_code'] ?? '').toString();
    final stats = _data['stats'] is Map
        ? Map<String, dynamic>.from(_data['stats'] as Map)
        : const {};
    final message = code.isEmpty
        ? 'Order great food on ${AppConfig.appName}!'
        : 'Use my code $code on ${AppConfig.appName} and we both earn rewards!';

    return V2Scaffold(
      title: 'Refer & Earn',
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 34),
              children: [
                V2Entrance(
                  child: GlassPanel(
                    radius: 24,
                    strong: true,
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.card_giftcard_rounded,
                            size: 34, color: p.accent),
                        const SizedBox(height: 12),
                        Text('Invite friends, earn rewards',
                            style: TextStyle(
                                color: p.ink,
                                fontSize: 17,
                                fontWeight: FontWeight.w900)),
                        const SizedBox(height: 6),
                        Text(
                          'Share your code. When a friend places their first '
                          'order, you both get reward points.',
                          style: TextStyle(
                              color: p.inkFaint, fontSize: 12.5, height: 1.4),
                        ),
                        if (code.isNotEmpty) ...[
                          const SizedBox(height: 18),
                          Row(
                            children: [
                              Expanded(
                                child: Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 14, vertical: 13),
                                  decoration: BoxDecoration(
                                    color: p.accent.withOpacity(0.10),
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(
                                        color: p.accent.withOpacity(0.4)),
                                  ),
                                  child: Text(
                                    code,
                                    style: TextStyle(
                                        color: p.accent,
                                        fontWeight: FontWeight.w900,
                                        fontSize: 16,
                                        letterSpacing: 2),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              V2Tappable(
                                onTap: () {
                                  Clipboard.setData(
                                      ClipboardData(text: code));
                                  ScaffoldMessenger.of(context)
                                    ..hideCurrentSnackBar()
                                    ..showSnackBar(const SnackBar(
                                        content: Text('Code copied')));
                                },
                                child: Container(
                                  padding: const EdgeInsets.all(13),
                                  decoration: BoxDecoration(
                                    color: p.glassTop,
                                    borderRadius: BorderRadius.circular(12),
                                    border:
                                        Border.all(color: p.glassBorder),
                                  ),
                                  child: Icon(Icons.copy_rounded,
                                      size: 18, color: p.inkSoft),
                                ),
                              ),
                            ],
                          ),
                        ],
                        const SizedBox(height: 14),
                        V2Tappable(
                          onTap: () => Share.share(message,
                              subject: AppConfig.appName),
                          child: Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: p.accent,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: const Text('Share invite',
                                style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                    fontSize: 14)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _stat(context, 'Invites', '${stats['total_referrals'] ?? stats['invites'] ?? 0}'),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _stat(context, 'Points earned',
                          '${stats['points_earned'] ?? 0}'),
                    ),
                  ],
                ),
              ],
            ),
    );
  }

  Widget _stat(BuildContext context, String label, String value) {
    final p = V2Theme.of(context);
    return GlassPanel(
      radius: 18,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value,
              style: TextStyle(
                  color: p.ink, fontSize: 22, fontWeight: FontWeight.w900)),
          const SizedBox(height: 2),
          Text(label, style: TextStyle(color: p.inkFaint, fontSize: 11.5)),
        ],
      ),
    );
  }
}
