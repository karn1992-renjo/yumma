import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';

class CampaignLandingScreen extends StatefulWidget {
  const CampaignLandingScreen({
    super.key,
    required this.campaignId,
  });

  final int campaignId;

  @override
  State<CampaignLandingScreen> createState() => _CampaignLandingScreenState();
}

class _CampaignLandingScreenState extends State<CampaignLandingScreen> {
  final ApiService _api = ApiService();

  bool _isLoading = true;
  bool _didTrackClick = false;
  Map<String, dynamic>? _campaign;

  @override
  void initState() {
    super.initState();
    _loadCampaign();
  }

  Future<void> _loadCampaign({bool forceRefresh = false}) async {
    try {
      final response = await _api
          .get(
            '${ApiConstants.campaigns}/${widget.campaignId}',
            includeAuth: false,
            cachePolicy: ApiCachePolicy.staticContent,
            cacheFirst: !forceRefresh,
            refreshCached: !forceRefresh,
            onCacheRefreshed: (_) {
              if (mounted) _loadCampaign(forceRefresh: true);
            },
          )
          .timeout(const Duration(seconds: 12));
      final data = response['data'];
      if (response['success'] == true && data is Map) {
        _campaign = Map<String, dynamic>.from(data);
        if (!_didTrackClick) {
          _didTrackClick = true;
          await _api
              .post(ApiConstants.campaignTrackClick(widget.campaignId))
              .catchError((_) {});
        }
      }
    } catch (_) {}

    if (!mounted) return;
    setState(() => _isLoading = false);
    if (_campaign == null) {
      Navigator.pushNamedAndRemoveUntil(context, '/customer/home', (_) => false);
    }
  }

  String _text(String key) => _campaign?[key]?.toString().trim() ?? '';

  String get _imageUrl {
    for (final key in const ['image_url', 'image', 'banner_image']) {
      final value = _text(key);
      if (value.isNotEmpty) return value;
    }
    return '';
  }

  Future<void> _openCampaignLink() async {
    final link = _text('link_url').isNotEmpty ? _text('link_url') : _text('link');
    if (link.isEmpty) {
      Navigator.pushNamedAndRemoveUntil(context, '/customer/home', (_) => false);
      return;
    }

    final uri = Uri.tryParse(link);
    if (uri == null) {
      Navigator.pushNamedAndRemoveUntil(context, '/customer/home', (_) => false);
      return;
    }

    if (uri.scheme == 'foodflow' || uri.host.contains('foodflow.in')) {
      Navigator.pushNamed(context, link);
      return;
    }

    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    final campaign = _campaign;
    return Scaffold(
      backgroundColor: const Color(0xFFFAFAFA),
      appBar: AppBar(
        title: const Text('Offer'),
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : campaign == null
              ? const SizedBox.shrink()
              : ListView(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                  children: [
                    if (_imageUrl.isNotEmpty)
                      ClipRRect(
                        borderRadius: BorderRadius.circular(18),
                        child: AspectRatio(
                          aspectRatio: 16 / 9,
                          child: AppCachedImage(
                            imageUrl: _imageUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => _imageFallback(),
                          ),
                        ),
                      )
                    else
                      _imageFallback(),
                    const SizedBox(height: 18),
                    Text(
                      _text('title').isNotEmpty ? _text('title') : _text('name'),
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (_text('description').isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(
                        _text('description'),
                        style: const TextStyle(
                          color: FoodFlowTheme.inkSoft,
                          fontSize: 14,
                          height: 1.45,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                    const SizedBox(height: 22),
                    SizedBox(
                      height: 50,
                      child: FilledButton(
                        onPressed: _openCampaignLink,
                        child: const Text('View Offer'),
                      ),
                    ),
                  ],
                ),
    );
  }

  Widget _imageFallback() {
    return Container(
      height: 180,
      decoration: BoxDecoration(
        color: const Color(0xFFEFF7F1),
        borderRadius: BorderRadius.circular(18),
      ),
      child: const Icon(
        Icons.local_offer_rounded,
        color: FoodFlowTheme.success,
        size: 42,
      ),
    );
  }
}
