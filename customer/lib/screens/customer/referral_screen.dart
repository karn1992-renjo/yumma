import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';

class ReferralScreen extends StatefulWidget {
  const ReferralScreen({super.key});

  @override
  State<ReferralScreen> createState() => _ReferralScreenState();
}

class _ReferralScreenState extends State<ReferralScreen> {
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
      final response = await _api.get(ApiConstants.referralSummary);
      if (!mounted) return;
      setState(() {
        _data = response is Map && response['data'] is Map
            ? Map<String, dynamic>.from(response['data'] as Map)
            : const {};
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final primary = FoodFlowTheme.brandPrimary(context);
    final code = _data['referral_code']?.toString() ?? '';
    final stats = _data['stats'] is Map
        ? Map<String, dynamic>.from(_data['stats'] as Map)
        : const <String, dynamic>{};
    final referrals = _data['referrals'] is List
        ? (_data['referrals'] as List).whereType<Map>().toList()
        : const <Map>[];

    return Scaffold(
      backgroundColor: FoodFlowTheme.warmCanvas,
      appBar: AppBar(
        title: const Text('Refer & Earn'),
        centerTitle: true,
        backgroundColor: Colors.transparent,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              color: primary,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 10, 18, 28),
                children: [
                  Container(
                    padding: const EdgeInsets.all(22),
                    decoration: _panel(context, radius: 30),
                    child: Column(
                      children: [
                        Icon(LucideIcons.gift, color: primary, size: 42),
                        const SizedBox(height: 12),
                        const Text(
                          'Invite friends, earn rewards',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Your friend signs up with this code. Referral bonus is credited after their eligible order.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 18),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 18,
                            vertical: 14,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(18),
                            border: Border.all(color: FoodFlowTheme.line),
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  code.isEmpty ? 'No code yet' : code,
                                  style: TextStyle(
                                    color: primary,
                                    fontSize: 24,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: 1.1,
                                  ),
                                ),
                              ),
                              IconButton(
                                onPressed: code.isEmpty
                                    ? null
                                    : () => Clipboard.setData(
                                          ClipboardData(text: code),
                                        ).then((_) => _toast('Code copied')),
                                icon: const Icon(LucideIcons.copy),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: code.isEmpty
                                ? null
                                : () => _shareInvite(code),
                            icon: const Icon(LucideIcons.share_2),
                            label: const Text('Share Invite'),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  Row(
                    children: [
                      Expanded(
                        child: _MetricCard(
                          label: 'Registered',
                          value: '${stats['registered'] ?? 0}',
                          color: primary,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _MetricCard(
                          label: 'Credited',
                          value: '${stats['credited'] ?? 0}',
                          color: FoodFlowTheme.success,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _MetricCard(
                          label: 'Points',
                          value: '${stats['points_earned'] ?? 0}',
                          color: FoodFlowTheme.tagOrange,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'Referral History',
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (referrals.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(18),
                      decoration: _panel(context),
                      child: const Text(
                        'No referrals yet. Share your invite to start earning.',
                        style: TextStyle(
                          color: FoodFlowTheme.muted,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    )
                  else
                    ...referrals.map((row) => Container(
                          margin: const EdgeInsets.only(bottom: 10),
                          padding: const EdgeInsets.all(14),
                          decoration: _panel(context, radius: 18),
                          child: Row(
                            children: [
                              CircleAvatar(
                                backgroundColor: primary.withOpacity(0.12),
                                child: Text(
                                  (row['name']?.toString().trim().isNotEmpty ==
                                          true)
                                      ? row['name'].toString()[0].toUpperCase()
                                      : 'C',
                                  style: TextStyle(
                                    color: primary,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      row['name']?.toString() ?? 'Customer',
                                      style: const TextStyle(
                                        color: FoodFlowTheme.ink,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                    Text(
                                      _statusText(row),
                                      style: const TextStyle(
                                        color: FoodFlowTheme.muted,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              _StatusPill(status: row['status']?.toString()),
                            ],
                          ),
                        )),
                ],
              ),
            ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _shareInvite(String code) async {
    final backendLink = _data['share_link']?.toString().trim();
    final logoUrl = _data['app_logo_url']?.toString().trim();
    final link = _isAppsFlyerLink(backendLink)
        ? backendLink
        : _buildAppsFlyerReferralLink(code, logoUrl: logoUrl);
    if (link == null) {
      _toast('Unable to create AppsFlyer invite link.');
      return;
    }

    final message =
        '\u{1F381} Join the ${AppConfig.appName} family with my referral code $code and enjoy a fast, convenient food ordering experience.\n\nSign up now: $link';

    final logoFile = await _shareableLogoFile();
    if (logoFile != null) {
      await Share.shareXFiles(
        [XFile(logoFile.path)],
        text: message,
        subject: AppConfig.appName,
      );
      return;
    }

    await Share.share(message, subject: AppConfig.appName);
  }

  bool _isAppsFlyerLink(String? link) {
    if (link == null || link.isEmpty) return false;
    final uri = Uri.tryParse(link);
    final host = uri?.host.toLowerCase() ?? '';
    return uri?.scheme == 'https' && host.endsWith('onelink.me');
  }

  Future<File?> _shareableLogoFile() async {
    const assetPath = 'android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png';
    try {
      final bytes = await rootBundle.load(assetPath);
      final tempDir = await getTemporaryDirectory();
      final file = File('${tempDir.path}/yumma-referral-logo.png');
      await file.writeAsBytes(
        bytes.buffer.asUint8List(bytes.offsetInBytes, bytes.lengthInBytes),
        flush: true,
      );
      return file;
    } catch (_) {
      return null;
    }
  }

  String? _buildAppsFlyerReferralLink(String code, {String? logoUrl}) {
    final trimmedCode = code.trim();
    final rawDomain = AppConfig.appsFlyerOneLinkDomain.trim();
    final domain = rawDomain.isNotEmpty
        ? rawDomain
            .replaceFirst(RegExp(r'^https?://'), '')
            .replaceFirst(RegExp(r'/$'), '')
        : AppConfig.appsFlyerOneLinkId.trim().isNotEmpty
            ? '${AppConfig.appsFlyerOneLinkId.trim()}.onelink.me'
            : '';

    if (domain.isEmpty || trimmedCode.isEmpty) {
      return null;
    }

    final pathSegments = AppConfig.appsFlyerOneLinkPath
        .split('/')
        .map((segment) => segment.trim())
        .where((segment) => segment.isNotEmpty)
        .toList(growable: true);
    if (pathSegments.isEmpty) {
      final oneLinkId = AppConfig.appsFlyerOneLinkId.trim();
      if (oneLinkId.isNotEmpty) pathSegments.add(oneLinkId);
      pathSegments.add('referral');
    }

    final webLink =
        Uri.https('yumma.in', '/referral', {'code': trimmedCode}).toString();

    final params = <String, String>{
      'pid': 'referral',
      'c': 'referral_share',
      'deep_link_value': 'referral',
      'screen': 'referral',
      'type': 'referral',
      'deep_link_sub1': trimmedCode,
      'af_sub1': trimmedCode,
      'referral_code': trimmedCode,
      'link': webLink,
      'af_web_dp': webLink,
      'af_og_title': 'Join ${AppConfig.appName}',
      'af_og_description':
          'Use referral code $trimmedCode for a fast, convenient food ordering experience.',
    };
    final normalizedLogoUrl = logoUrl?.trim();
    if (normalizedLogoUrl != null &&
        normalizedLogoUrl.startsWith('https://')) {
      params['af_og_image'] = normalizedLogoUrl;
    }

    return Uri.https(domain, pathSegments.join('/'), params).toString();
  }

  String _statusText(Map row) {
    final amount = double.tryParse('${row['amount'] ?? 0}') ?? 0;
    final points = int.tryParse('${row['points'] ?? 0}') ?? 0;
    if (amount > 0) return 'Wallet/voucher reward credited';
    if (points > 0) return '$points reward points credited';
    return 'Waiting for eligible order';
  }

  BoxDecoration _panel(BuildContext context, {double radius = 22}) {
    final primary = FoodFlowTheme.brandPrimary(context);
    return BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: FoodFlowTheme.line),
      boxShadow: [
        BoxShadow(
          color: primary.withOpacity(0.10),
          blurRadius: 22,
          offset: const Offset(0, 12),
        ),
      ],
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              color: color,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            label,
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status});

  final String? status;

  @override
  Widget build(BuildContext context) {
    final normalized = (status ?? 'registered').toLowerCase();
    final color = normalized == 'credited'
        ? FoodFlowTheme.success
        : normalized == 'qualified'
            ? FoodFlowTheme.tagOrange
            : FoodFlowTheme.muted;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        normalized.replaceAll('_', ' ').toUpperCase(),
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}
