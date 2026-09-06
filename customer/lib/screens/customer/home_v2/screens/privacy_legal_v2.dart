import 'package:flutter/material.dart';

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class PrivacyLegalV2 extends StatefulWidget {
  const PrivacyLegalV2({super.key});

  @override
  State<PrivacyLegalV2> createState() => _PrivacyLegalV2State();
}

class _PrivacyLegalV2State extends State<PrivacyLegalV2> {
  final ApiService _api = ApiService();
  bool _loading = true;
  bool _failed = false;
  Map<String, dynamic> _content = const {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failed = false;
    });
    try {
      final res = await _api.get(ApiConstants.legalContent);
      if (res is Map && res['data'] is Map) {
        _content = Map<String, dynamic>.from(res['data'] as Map);
      } else {
        _failed = true;
      }
    } catch (_) {
      _failed = true;
    }
    if (mounted) setState(() => _loading = false);
  }

  String _t(String k) {
    final v = _content[k]?.toString().trim() ?? '';
    return v.isEmpty ? 'Not available.' : v;
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final sections = <(String, IconData, String)>[
      ('Terms of Service', Icons.description_rounded, _t('terms')),
      ('Privacy Policy', Icons.shield_rounded, _t('privacy')),
      ('Refund Policy', Icons.receipt_long_rounded, _t('refund')),
      (
        'Data & Support',
        Icons.headset_mic_rounded,
        'Legal contact: ${_t('contact_email')}'
      ),
    ];
    return V2Scaffold(
      title: 'Privacy & Legal',
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : _failed
              ? V2EmptyState(
                  icon: Icons.cloud_off_rounded,
                  title: 'Could not load legal content',
                  onRetry: _load,
                )
              : ListView(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 30),
                  children: [
                    for (var i = 0; i < sections.length; i++)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: V2Entrance(
                          delay: Duration(milliseconds: 30 * i),
                          child: _Section(
                            title: sections[i].$1,
                            icon: sections[i].$2,
                            body: sections[i].$3,
                          ),
                        ),
                      ),
                  ],
                ),
    );
  }
}

class _Section extends StatefulWidget {
  const _Section({required this.title, required this.icon, required this.body});
  final String title;
  final IconData icon;
  final String body;

  @override
  State<_Section> createState() => _SectionState();
}

class _SectionState extends State<_Section> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return GlassPanel(
      radius: 18,
      onTap: () => setState(() => _open = !_open),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(widget.icon, size: 18, color: p.inkSoft),
              const SizedBox(width: 12),
              Expanded(
                child: Text(widget.title,
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 14,
                        fontWeight: FontWeight.w800)),
              ),
              Icon(
                _open
                    ? Icons.keyboard_arrow_up_rounded
                    : Icons.keyboard_arrow_down_rounded,
                color: p.inkFaint,
              ),
            ],
          ),
          AnimatedCrossFade(
            firstChild: const SizedBox(width: double.infinity),
            secondChild: Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(widget.body,
                  style:
                      TextStyle(color: p.inkSoft, fontSize: 12.5, height: 1.5)),
            ),
            crossFadeState:
                _open ? CrossFadeState.showSecond : CrossFadeState.showFirst,
            duration: const Duration(milliseconds: 220),
          ),
        ],
      ),
    );
  }
}
