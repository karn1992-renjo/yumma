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
import '../../widgets/common/app_skeleton.dart';
import '../../widgets/customer/profile_screen_chrome.dart';

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

  Future<void> _load({bool forceRefresh = false}) async {
    setState(() => _loading = _data.isEmpty);
    try {
      final response = await _api.get(
        ApiConstants.referralSummary,
        cachePolicy: ApiCachePolicy.screen,
        cacheFirst: !forceRefresh,
        refreshCached: !forceRefresh,
        onCacheRefreshed: _applyReferral,
      );
      _applyReferral(response);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _applyReferral(dynamic response) {
    if (!mounted || response is! Map || response['data'] is! Map) return;
    setState(() {
      _data = Map<String, dynamic>.from(response['data'] as Map);
    });
  }

  @override
  Widget build(BuildContext context) {
    final accent = profileAccentColor(context);
    final code = _data['referral_code']?.toString() ?? '';
    final stats = _data['stats'] is Map
        ? Map<String, dynamic>.from(_data['stats'] as Map)
        : const <String, dynamic>{};
    final referrals = _data['referrals'] is List
        ? (_data['referrals'] as List).whereType<Map>().toList()
        : const <Map>[];

    return Scaffold(
      backgroundColor: profileCanvasColor(context),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => _load(forceRefresh: true),
          color: accent,
          child: Stack(
            children: [
              ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 32),
                children: [
                  const ProfilePageTopBar(
                    title: 'Refer & Earn',
                    subtitle: 'Invite friends, earn rewards',
                  ),
                  const SizedBox(height: 22),

                  // Hero + code + share
                  ProfileSurfaceCard(
                    padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
                    child: Column(
                      children: [
                        ProfileAccentIcon(
                          icon: LucideIcons.gift,
                          size: 62,
                          iconSize: 28,
                          radius: 20,
                        ),
                        const SizedBox(height: 14),
                        Text(
                          'Invite friends, earn rewards',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: profileTextColor(context),
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            height: 1.15,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Your friend signs up with this code. The referral bonus is credited after their first eligible order.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: profileMutedColor(context),
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            height: 1.4,
                          ),
                        ),
                        const SizedBox(height: 16),
                        Container(
                          padding: const EdgeInsets.fromLTRB(16, 12, 8, 12),
                          decoration: BoxDecoration(
                            color: profileSoftColor(context),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: accent.withOpacity(0.20),
                            ),
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  code.isEmpty ? 'No code yet' : code,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    color: accent,
                                    fontSize: 22,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: 1.1,
                                  ),
                                ),
                              ),
                              IconButton(
                                style: FoodFlowTheme.softIconButton(
                                  backgroundColor:
                                      profileButtonSoftColor(context),
                                  foregroundColor: profileButtonColor(context),
                                ),
                                onPressed: code.isEmpty
                                    ? null
                                    : () => Clipboard.setData(
                                          ClipboardData(text: code),
                                        ).then((_) => _toast('Code copied')),
                                icon: const Icon(LucideIcons.copy, size: 18),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),
                        SizedBox(
                          width: double.infinity,
                          height: 50,
                          child: ElevatedButton.icon(
                            onPressed:
                                code.isEmpty ? null : () => _shareInvite(code),
                            style: FoodFlowTheme.zomatoPrimaryButton(
                              color: profileButtonColor(context),
                              foregroundColor: profileOnButtonColor(context),
                              radius: 16,
                            ),
                            icon: const Icon(LucideIcons.share_2, size: 18),
                            label: const Text('Share Invite'),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),

                  const ProfileSectionLabel(title: 'Your progress'),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: _MetricCard(
                          icon: LucideIcons.user_plus,
                          label: 'Registered',
                          value: '${stats['registered'] ?? 0}',
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _MetricCard(
                          icon: LucideIcons.badge_check,
                          label: 'Credited',
                          value: '${stats['credited'] ?? 0}',
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _MetricCard(
                          icon: LucideIcons.sparkles,
                          label: 'Points',
                          value: '${stats['points_earned'] ?? 0}',
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),

                  const ProfileSectionLabel(title: 'Referral history'),
                  const SizedBox(height: 10),
                  if (_loading && referrals.isEmpty)
                    const AppSkeletonColumn(itemCount: 4, itemHeight: 74)
                  else if (referrals.isEmpty)
                    _EmptyReferrals()
                  else
                    ...referrals.map(
                      (row) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _ReferralRow(
                          name: row['name']?.toString() ?? 'Customer',
                          status: _statusText(row),
                          pill: row['status']?.toString(),
                        ),
                      ),
                    ),
                ],
              ),
              if (_loading && referrals.isNotEmpty)
                const Positioned(
                  left: 0,
                  right: 0,
                  top: 0,
                  child: LinearProgressIndicator(minHeight: 2),
                ),
            ],
          ),
        ),
      ),
    );
  }

  void _toast(String message) {
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
      final file = File('${tempDir.path}/Swado-referral-logo.png');
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
        Uri.https('yumma.in', '/referral', {'code': trimmedCode})
            .toString();

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
    if (normalizedLogoUrl != null && normalizedLogoUrl.startsWith('https://')) {
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
    return ProfileSurfaceCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      radius: 18,
      child: Column(
        children: [
          Icon(icon, size: 18, color: profileAccentColor(context)),
          const SizedBox(height: 8),
          Text(
            value,
            style: TextStyle(
              color: profileTextColor(context),
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: profileMutedColor(context),
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
    final accent = profileAccentColor(context);
    return ProfileSurfaceCard(
      padding: const EdgeInsets.all(14),
      radius: 18,
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: profileSoftColor(context),
              borderRadius: BorderRadius.circular(14),
            ),
            alignment: Alignment.center,
            child: Text(
              name.trim().isNotEmpty ? name.trim()[0].toUpperCase() : 'C',
              style: TextStyle(
                color: accent,
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
                    color: profileTextColor(context),
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
                    color: profileMutedColor(context),
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
        ? FoodFlowTheme.success
        : normalized == 'qualified'
            ? FoodFlowTheme.tagOrange
            : profileMutedColor(context);
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
    return ProfileSurfaceCard(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          ProfileAccentIcon(
            icon: LucideIcons.users,
            size: 62,
            iconSize: 28,
            radius: 20,
          ),
          const SizedBox(height: 14),
          Text(
            'No referrals yet',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: profileTextColor(context),
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Share your invite to start earning rewards.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: profileMutedColor(context),
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
