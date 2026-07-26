import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/customer/profile_screen_chrome.dart';

class PrivacyLegalScreen extends StatefulWidget {
  const PrivacyLegalScreen({super.key});

  @override
  State<PrivacyLegalScreen> createState() => _PrivacyLegalScreenState();
}

class _PrivacyLegalScreenState extends State<PrivacyLegalScreen> {
  final ApiService _api = ApiService();
  Map<String, dynamic> _content = {};
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadContent();
  }

  Future<void> _loadContent({bool forceRefresh = false}) async {
    try {
      final response = await _api.get(
        ApiConstants.legalContent,
        includeAuth: false,
        cachePolicy: ApiCachePolicy.staticContent,
        cacheFirst: !forceRefresh,
        refreshCached: !forceRefresh,
        onCacheRefreshed: (_) {
          if (mounted) _loadContent(forceRefresh: true);
        },
      );
      if (response['success'] == true && response['data'] is Map) {
        if (!mounted) return;
        setState(() {
          _content = Map<String, dynamic>.from(response['data']);
          _error = null;
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String _legalText(String key) {
    final value = _content[key]?.toString() ?? '';
    return value
        .replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n')
        .replaceAll(RegExp(r'</p\s*>', caseSensitive: false), '\n\n')
        .replaceAll(RegExp(r'<[^>]*>'), '')
        .replaceAll('&nbsp;', ' ')
        .replaceAll('&amp;', '&')
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .replaceAll('&quot;', '"')
        .replaceAll('&#039;', "'")
        .trim();
  }

  @override
  Widget build(BuildContext context) {
    final sections = [
      (
        'Terms of Service',
        _legalText('terms'),
        LucideIcons.file_text,
      ),
      (
        'Privacy Policy',
        _legalText('privacy'),
        LucideIcons.shield_check,
      ),
      (
        'Refund Policy',
        _legalText('refund'),
        LucideIcons.receipt_text,
      ),
      (
        'Data & Support',
        'Legal contact: ${_legalText('contact_email')}',
        LucideIcons.headset,
      ),
    ];

    return Scaffold(
      backgroundColor: profileCanvasColor(context),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => _loadContent(forceRefresh: true),
          color: profileAccentColor(context),
          child: Stack(
            children: [
              ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 32),
                children: [
                  const ProfilePageTopBar(
                    title: 'Privacy & Legal',
                    subtitle: 'Policies, terms and support details',
                  ),
                  const SizedBox(height: 22),
                  ProfileSurfaceCard(
                    padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
                    child: Row(
                      children: [
                        ProfileAccentIcon(
                          icon: LucideIcons.shield_check,
                          size: 58,
                          iconSize: 26,
                          radius: 20,
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Privacy & legal',
                                style: TextStyle(
                                  color: profileTextColor(context),
                                  fontSize: 16,
                                  fontWeight: FontWeight.w800,
                                  height: 1.1,
                                ),
                              ),
                              SizedBox(height: 6),
                              Text(
                                'Review policy details for your account, orders and data.',
                                style: TextStyle(
                                  color: profileMutedColor(context),
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  height: 1.35,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  const ProfileSectionLabel(title: 'Policies'),
                  const SizedBox(height: 10),
                  if (_error != null)
                    _LegalErrorCard(onRetry: () {
                      setState(() => _isLoading = true);
                      _loadContent(forceRefresh: true);
                    })
                  else
                    ...sections.map(
                      (section) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _LegalSectionCard(
                          title: section.$1,
                          body: section.$2,
                          icon: section.$3,
                        ),
                      ),
                    ),
                ],
              ),
              if (_isLoading)
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
}

class _LegalSectionCard extends StatelessWidget {
  const _LegalSectionCard({
    required this.title,
    required this.body,
    required this.icon,
  });

  final String title;
  final String body;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return ProfileSurfaceCard(
      padding: const EdgeInsets.all(16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ProfileAccentIcon(
            icon: icon,
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: profileTextColor(context),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  body.isEmpty ? 'Details are not available yet.' : body,
                  style: TextStyle(
                    color: profileMutedColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LegalErrorCard extends StatelessWidget {
  const _LegalErrorCard({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return ProfileSurfaceCard(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          ProfileAccentIcon(
            icon: LucideIcons.cloud_off,
            size: 66,
            iconSize: 30,
            radius: 20,
          ),
          const SizedBox(height: 16),
          Text(
            'Could not load legal content',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: profileTextColor(context),
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Please check your connection and try again.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: profileMutedColor(context),
              fontSize: 12,
              fontWeight: FontWeight.w500,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 18),
          ElevatedButton(
            onPressed: onRetry,
            style: FoodFlowTheme.zomatoPrimaryButton(
              color: profileButtonColor(context),
              foregroundColor: profileOnButtonColor(context),
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
              radius: 14,
            ),
            child: Text('Retry'),
          ),
        ],
      ),
    );
  }
}
