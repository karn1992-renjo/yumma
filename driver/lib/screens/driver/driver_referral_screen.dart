import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/aurora/aurora.dart';

/// "Refer & Earn" screen for the driver app. Invite other drivers to join
/// as delivery partners using a personal referral code / link; the bonus is
/// credited to both drivers after the invitee completes their first
/// delivery. Mirrors the customer app's referral screen but is built with
/// the driver app's own aurora/glass design system.
class DriverReferralScreen extends StatefulWidget {
  const DriverReferralScreen({super.key});

  @override
  State<DriverReferralScreen> createState() => _DriverReferralScreenState();
}

class _DriverReferralScreenState extends State<DriverReferralScreen> {
  final ApiService _api = ApiService();
  bool _loading = true;
  Map<String, dynamic> _data = const {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() => _loading = _data.isEmpty);
    try {
      final response = await _api.get(ApiConstants.referralSummary);
      if (!mounted) return;
      if (response is Map && response['data'] is Map) {
        setState(() {
          _data = Map<String, dynamic>.from(response['data'] as Map);
        });
      }
    } catch (e) {
      debugPrint('Load referral summary error: $e');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final code = _data['referral_code']?.toString() ?? '';
    final stats = _data['stats'] is Map
        ? Map<String, dynamic>.from(_data['stats'] as Map)
        : const <String, dynamic>{};
    final howItWorks = _data['how_it_works'] is List
        ? (_data['how_it_works'] as List)
            .map((e) => e.toString())
            .where((e) => e.isNotEmpty)
            .toList()
        : const <String>[];
    final referrals = _data['referrals'] is List
        ? (_data['referrals'] as List).whereType<Map>().toList()
        : const <Map>[];

    return AuroraScaffold(
      appBar: GlassAppBar(
        title: Text(
          'Refer & Earn',
          style: TextStyle(
            color: foodflow.ink,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      body: _loading && _data.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                children: [
                  AuroraEntrance(child: _heroCard(code)),
                  const SizedBox(height: 18),
                  _sectionHeader('Your progress'),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: _MetricCard(
                          icon: Icons.person_add_outlined,
                          label: 'Registered',
                          value: '${stats['registered'] ?? 0}',
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _MetricCard(
                          icon: Icons.verified_outlined,
                          label: 'Credited',
                          value: '${stats['credited'] ?? 0}',
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _MetricCard(
                          icon: Icons.auto_awesome_outlined,
                          label: 'Points',
                          value: '${stats['points_earned'] ?? 0}',
                        ),
                      ),
                    ],
                  ),
                  if (howItWorks.isNotEmpty) ...[
                    const SizedBox(height: 18),
                    _sectionHeader('How it works'),
                    const SizedBox(height: 10),
                    GlassCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          for (var i = 0; i < howItWorks.length; i++)
                            Padding(
                              padding: EdgeInsets.only(
                                bottom: i == howItWorks.length - 1 ? 0 : 12,
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    width: 22,
                                    height: 22,
                                    alignment: Alignment.center,
                                    decoration: BoxDecoration(
                                      color: foodflow.orange.withOpacity(0.14),
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      '${i + 1}',
                                      style: TextStyle(
                                        color: foodflow.orange,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      howItWorks[i],
                                      style: TextStyle(
                                        color: foodflow.inkSoft,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w500,
                                        height: 1.35,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  _sectionHeader('Referral history'),
                  const SizedBox(height: 10),
                  if (referrals.isEmpty)
                    _EmptyReferrals()
                  else
                    ...referrals.map(
                      (row) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _ReferralRow(
                          name: row['name']?.toString() ?? 'Driver',
                          status: _statusText(row),
                          pill: row['status']?.toString(),
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _sectionHeader(String title) {
    return Text(
      title,
      style: TextStyle(
        color: foodflow.ink,
        fontSize: 15,
        fontWeight: FontWeight.w800,
      ),
    );
  }

  Widget _heroCard(String code) {
    return GlassCard(
      padding: const EdgeInsets.fromLTRB(18, 20, 18, 18),
      child: Column(
          children: [
            Container(
              width: 62,
              height: 62,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: foodflow.orange.withOpacity(0.12),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Icon(
                Icons.card_giftcard,
                size: 28,
                color: foodflow.orange,
              ),
            ),
            const SizedBox(height: 14),
            Text(
              'Invite friends to drive, earn rewards',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: foodflow.ink,
                fontSize: 18,
                fontWeight: FontWeight.w800,
                height: 1.15,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Your friend signs up with this code. The referral bonus is '
              'credited to both of you after their first delivery.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: foodflow.muted,
                fontSize: 12,
                fontWeight: FontWeight.w500,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.fromLTRB(16, 12, 8, 12),
              decoration: BoxDecoration(
                color: foodflow.canvas,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: foodflow.orange.withOpacity(0.20)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      code.isEmpty ? 'No code yet' : code,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: foodflow.orange,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1.1,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: code.isEmpty
                        ? null
                        : () => Clipboard.setData(ClipboardData(text: code))
                            .then((_) => _toast('Code copied')),
                    icon: Icon(Icons.copy, size: 18, color: foodflow.orange),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            GlassButton(
              label: 'Share Invite',
              icon: Icons.share,
              onPressed: code.isEmpty ? null : () => _shareInvite(code),
            ),
          ],
        ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _shareInvite(String code) async {
    final backendLink = _data['share_link']?.toString().trim();
    final logoUrl = _data['app_logo_url']?.toString().trim();
    final link = _isAppsFlyerLink(backendLink)
        ? backendLink
        : _buildAppsFlyerReferralLink(code, logoUrl: logoUrl);
    if (link == null) {
      _toast('Unable to create invite link.');
      return;
    }

    final message = 'Join as a delivery partner and start earning with '
        'my referral code $code on ${AppConfig.appName}.\n\nSign up now: $link';

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
      final file = File('${tempDir.path}/Swado-driver-referral-logo.png');
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
        Uri.https('swado.online', '/referral', {'code': trimmedCode})
            .toString();

    final params = <String, String>{
      'pid': 'referral',
      'c': 'driver_referral_share',
      'deep_link_value': 'referral',
      'screen': 'referral',
      'type': 'referral',
      'deep_link_sub1': trimmedCode,
      'af_sub1': trimmedCode,
      'referral_code': trimmedCode,
      'link': webLink,
      'af_web_dp': webLink,
      'af_og_title': 'Join ${AppConfig.appName} as a delivery partner',
      'af_og_description':
          'Use referral code $trimmedCode to join as a delivery partner.',
    };
    final normalizedLogoUrl = logoUrl?.trim();
    if (normalizedLogoUrl != null && normalizedLogoUrl.startsWith('https://')) {
      params['af_og_image'] = normalizedLogoUrl;
    }

    return Uri.https(domain, pathSegments.join('/'), params).toString();
  }

  String _statusText(Map row) {
    final amount = double.tryParse('${row['amount'] ?? 0}') ?? 0;
    final points = int.tryParse('${row['points'] ?? 0}') ?? 0;
    if (amount > 0) return 'Referral bonus credited';
    if (points > 0) return '$points reward points credited';
    return 'Waiting for first delivery';
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      radius: 16,
      child: Column(
        children: [
          Icon(icon, size: 18, color: foodflow.orange),
          const SizedBox(height: 8),
          Text(
            value,
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: foodflow.muted,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _ReferralRow extends StatelessWidget {
  const _ReferralRow({
    required this.name,
    required this.status,
    required this.pill,
  });

  final String name;
  final String status;
  final String? pill;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(14),
      radius: 16,
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: foodflow.canvas,
              borderRadius: BorderRadius.circular(14),
            ),
            alignment: Alignment.center,
            child: Text(
              name.trim().isNotEmpty ? name.trim()[0].toUpperCase() : 'D',
              style: TextStyle(
                color: foodflow.orange,
                fontWeight: FontWeight.w900,
                fontSize: 16,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: foodflow.ink,
                    fontWeight: FontWeight.w800,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  status,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: foodflow.muted,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          _StatusPill(status: pill),
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
        ? foodflow.success
        : normalized == 'qualified'
            ? foodflow.orange
            : foodflow.muted;
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

class _EmptyReferrals extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          Container(
            width: 62,
            height: 62,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: foodflow.orange.withOpacity(0.12),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Icon(
              Icons.people_outline,
              size: 28,
              color: foodflow.orange,
            ),
          ),
          const SizedBox(height: 14),
          Text(
            'No referrals yet',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Share your invite to start earning rewards.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: foodflow.muted,
              fontSize: 12,
              fontWeight: FontWeight.w500,
              height: 1.35,
            ),
          ),
        ],
      ),
    );
  }
}
