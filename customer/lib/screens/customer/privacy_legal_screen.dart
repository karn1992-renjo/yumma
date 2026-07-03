import 'package:flutter/material.dart';
import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/customer/account_chrome.dart';

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
        _legalText('terms')
      ),
      (
        'Privacy Policy',
        _legalText('privacy')
      ),
      (
        'Refund Policy',
        _legalText('refund')
      ),
      (
        'Data & Support',
        'Legal contact: ${_legalText('contact_email')}'
      ),
    ];

    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        title: const Text('Privacy & Legal'),
        backgroundColor: accountCanvas,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text('Could not load legal content.'),
                        const SizedBox(height: 12),
                        ElevatedButton(
                          onPressed: () {
                            setState(() => _isLoading = true);
                            _loadContent();
                          },
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : ListView.separated(
        padding: EdgeInsets.zero,
        itemCount: sections.length + 2,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          if (index == 0) {
            return const AccountHeroCard(
              title: 'Privacy & legal',
              subtitle:
                  'Review your policies, refund terms, privacy details, and how support works around your account.',
              icon: Icons.gavel_rounded,
              badge: 'PROFILE SPACE',
            );
          }
          if (index == 1) {
            return const Padding(
              padding: EdgeInsets.fromLTRB(16, 0, 16, 2),
              child: AccountSectionTitle(title: 'POLICIES'),
            );
          }
          final section = sections[index - 2];
          return AccountSurfaceCard(
            margin: const EdgeInsets.fromLTRB(16, 0, 16, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  section.$1,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  section.$2,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    height: 1.45,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
