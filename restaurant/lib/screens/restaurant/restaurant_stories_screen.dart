// lib/screens/restaurant/restaurant_stories_screen.dart
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:provider/provider.dart';
import 'package:video_player/video_player.dart';
import 'package:video_thumbnail/video_thumbnail.dart' as vt;

import '../../config/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/subscription_checkout_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../utils/currency_utils.dart';

class RestaurantStoriesScreen extends StatefulWidget {
  const RestaurantStoriesScreen({super.key});

  @override
  State<RestaurantStoriesScreen> createState() => _RestaurantStoriesScreenState();
}

class _RestaurantStoriesScreenState extends State<RestaurantStoriesScreen> {
  final ApiService _api = ApiService();
  final ImagePicker _picker = ImagePicker();

  List<Map<String, dynamic>> _stories = [];
  List<Map<String, dynamic>> _addonPlans = [];
  List<String> _availableGateways = [];
  bool _isLoading = true;
  bool _canPost = false;
  bool _isUploading = false;
  bool _isSubscribingAddon = false;

  @override
  void initState() {
    super.initState();
    _loadAll();
  }

  Future<void> _loadAll() async {
    setState(() => _isLoading = true);
    await _loadStories();
    if (!_canPost) await _loadAddonPlans();
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _loadStories() async {
    try {
      final response = await _api.get(ApiConstants.restaurantStories);
      if (response['success'] == true && mounted) {
        setState(() {
          _stories = (response['data'] as List? ?? [])
              .whereType<Map>()
              .map((row) => Map<String, dynamic>.from(row))
              .toList();
          _canPost = response['can_post'] == true;
        });
      }
    } catch (e) {
      debugPrint('Load stories error: $e');
    }
  }

  Future<void> _loadAddonPlans() async {
    try {
      final response = await _api.get(
        ApiConstants.restaurantSubscriptionPlans,
        queryParams: {'scope': 'stories_addon'},
      );
      if (response['success'] == true && mounted) {
        setState(() {
          _addonPlans = (response['data'] as List? ?? [])
              .whereType<Map>()
              .map((row) => Map<String, dynamic>.from(row))
              .toList();
          _availableGateways = (response['available_gateways'] as List? ?? [])
              .map((g) => g.toString())
              .toList();
        });
      }
    } catch (e) {
      debugPrint('Load stories addon plans error: $e');
    }
  }

  Future<void> _subscribeAddon(Map<String, dynamic> plan) async {
    if (_isSubscribingAddon) return;

    final paymentMethod = await _resolvePaymentMethod();
    if (paymentMethod == null) return;

    setState(() => _isSubscribingAddon = true);

    final user = context.read<AuthProvider>().currentUser;
    final checkout = SubscriptionCheckoutService(
      onAuthorized: () async {
        if (!mounted) return;
        setState(() => _isSubscribingAddon = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Payment authorized. Activating Stories — this can take a few seconds.'),
          ),
        );
        await Future.delayed(const Duration(seconds: 2));
        await _loadAll();
      },
      onFailure: (message) {
        if (!mounted) return;
        setState(() => _isSubscribingAddon = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
      },
    );

    try {
      await checkout.subscribe(
        planId: plan['id'] as int,
        paymentMethod: paymentMethod,
        user: user,
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubscribingAddon = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_cleanError(e))));
    } finally {
      checkout.dispose();
    }
  }

  /// Only ever offers gateways the admin has both enabled for customer
  /// payments AND configured with real API credentials
  /// (`available_gateways`, from SubscriptionGatewayResolver::
  /// availableGateways() on the backend). Skips the picker when there's
  /// just one option.
  Future<String?> _resolvePaymentMethod() async {
    if (_availableGateways.isEmpty) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Subscriptions aren\'t available right now. Please contact support.'),
          ),
        );
      }
      return null;
    }

    if (_availableGateways.length == 1) {
      return _availableGateways.first;
    }

    const labels = {
      'razorpay': ('Razorpay', 'UPI AutoPay / Card'),
      'cashfree': ('Cashfree', 'UPI / Card / Netbanking'),
    };

    return showModalBottomSheet<String>(
      context: context,
      backgroundColor: foodflow.surfaceColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Pay with', style: TextStyle(color: foodflow.ink, fontSize: 15, fontWeight: FontWeight.w900)),
              const SizedBox(height: 12),
              for (final gateway in _availableGateways)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(Icons.account_balance_wallet_outlined, color: foodflow.orange),
                  title: Text(labels[gateway]?.$1 ?? gateway, style: const TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text(labels[gateway]?.$2 ?? ''),
                  onTap: () => Navigator.pop(sheetContext, gateway),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showUploadSheet() async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: foodflow.surfaceColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('New story', style: TextStyle(color: foodflow.ink, fontSize: 15, fontWeight: FontWeight.w900)),
              const SizedBox(height: 12),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(Icons.image_outlined, color: foodflow.orange),
                title: const Text('Photo', style: TextStyle(fontWeight: FontWeight.w800)),
                onTap: () => Navigator.pop(sheetContext, 'image'),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(Icons.videocam_outlined, color: foodflow.orange),
                title: const Text('Video', style: TextStyle(fontWeight: FontWeight.w800)),
                subtitle: const Text('Up to 60 seconds'),
                onTap: () => Navigator.pop(sheetContext, 'video'),
              ),
            ],
          ),
        ),
      ),
    );

    if (choice == 'image') {
      await _pickAndUploadImage();
    } else if (choice == 'video') {
      await _pickAndUploadVideo();
    }
  }

  Future<void> _pickAndUploadImage() async {
    final picked = await _picker.pickImage(source: ImageSource.gallery, imageQuality: 90);
    if (picked == null) return;

    final caption = await _promptCaption();
    await _uploadStory(mediaType: 'image', mediaPath: picked.path, caption: caption);
  }

  Future<void> _pickAndUploadVideo() async {
    final picked = await _picker.pickVideo(source: ImageSource.gallery);
    if (picked == null) return;

    setState(() => _isUploading = true);

    try {
      final controller = VideoPlayerController.file(File(picked.path));
      await controller.initialize();
      final duration = controller.value.duration.inSeconds;
      await controller.dispose();

      if (duration > 60) {
        if (!mounted) return;
        setState(() => _isUploading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Video stories can\'t be longer than 60 seconds.')),
        );
        return;
      }

      final tempDir = await getTemporaryDirectory();
      final thumbnailPath = await vt.VideoThumbnail.thumbnailFile(
        video: picked.path,
        thumbnailPath: tempDir.path,
        imageFormat: vt.ImageFormat.JPEG,
        maxHeight: 640,
        quality: 75,
      );

      if (thumbnailPath == null) {
        if (!mounted) return;
        setState(() => _isUploading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not generate a thumbnail for this video.')),
        );
        return;
      }

      setState(() => _isUploading = false);
      final caption = await _promptCaption();
      await _uploadStory(
        mediaType: 'video',
        mediaPath: picked.path,
        thumbnailPath: thumbnailPath,
        durationSeconds: duration,
        caption: caption,
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isUploading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not process this video: $e')),
      );
    }
  }

  Future<String?> _promptCaption() {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Add a caption?'),
        content: TextField(
          controller: controller,
          maxLength: 150,
          decoration: const InputDecoration(hintText: 'Optional'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, null),
            child: const Text('Skip'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(dialogContext, controller.text.trim()),
            child: const Text('Post'),
          ),
        ],
      ),
    );
  }

  Future<void> _uploadStory({
    required String mediaType,
    required String mediaPath,
    String? thumbnailPath,
    String? caption,
    int? durationSeconds,
  }) async {
    setState(() => _isUploading = true);

    try {
      final response = await _api.postMultipart(
        ApiConstants.restaurantStories,
        fields: {
          'media_type': mediaType,
          if (caption != null && caption.isNotEmpty) 'caption': caption,
          if (durationSeconds != null) 'duration_seconds': durationSeconds.toString(),
        },
        files: {
          'media': mediaPath,
          if (thumbnailPath != null) 'thumbnail': thumbnailPath,
        },
      );

      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Unable to post story');
      }

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Story posted!')),
      );
      await _loadStories();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_cleanError(e))));
    } finally {
      if (mounted) setState(() => _isUploading = false);
    }
  }

  Future<void> _deleteStory(int id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete this story?'),
        content: const Text('This can\'t be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      final response = await _api.delete(ApiConstants.restaurantStory(id));
      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Unable to delete story');
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Story deleted.')));
      await _loadStories();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_cleanError(e))));
    }
  }

  String _cleanError(Object error) {
    final message = error.toString();
    return message.startsWith('Exception: ') ? message.substring('Exception: '.length) : message;
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top + 60;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Text('Stories', style: TextStyle(color: foodflow.ink, fontSize: 17, fontWeight: FontWeight.w900)),
      ),
      floatingActionButton: _canPost
          ? FloatingActionButton.extended(
              onPressed: _isUploading ? null : _showUploadSheet,
              backgroundColor: foodflow.orange,
              icon: _isUploading
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.add, color: Colors.white),
              label: Text(_isUploading ? 'Posting…' : 'New Story', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
            )
          : null,
      body: Stack(
        children: [
          ...AuroraTheme.auroraBlobs(),
          _isLoading
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _loadAll,
                  child: !_canPost
                      ? _StoriesUpsell(
                          plans: _addonPlans,
                          isBusy: _isSubscribingAddon,
                          onSubscribe: _subscribeAddon,
                          topPad: topPad,
                        )
                      : GridView.builder(
                          padding: EdgeInsets.fromLTRB(16, topPad, 16, 96),
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 3,
                            crossAxisSpacing: 10,
                            mainAxisSpacing: 10,
                            childAspectRatio: 0.62,
                          ),
                          itemCount: _stories.length,
                          itemBuilder: (context, index) => _StoryTile(
                            story: _stories[index],
                            onDelete: () => _deleteStory(_stories[index]['id'] as int),
                          ),
                        ),
                ),
        ],
      ),
    );
  }
}

class _StoryTile extends StatelessWidget {
  const _StoryTile({required this.story, required this.onDelete});

  final Map<String, dynamic> story;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final thumbnailUrl = (story['thumbnail_url'] ?? story['media_url'])?.toString();
    final viewCount = (story['view_count'] as num?)?.toInt() ?? 0;
    final postedAt = DateTime.tryParse(story['posted_at']?.toString() ?? '');

    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (thumbnailUrl != null && thumbnailUrl.isNotEmpty)
            Image.network(thumbnailUrl, fit: BoxFit.cover)
          else
            Container(color: foodflow.line),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: Container(
              padding: const EdgeInsets.fromLTRB(6, 16, 6, 6),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.transparent, Colors.black.withOpacity(0.72)],
                ),
              ),
              child: Row(
                children: [
                  const Icon(Icons.remove_red_eye_outlined, color: Colors.white, size: 12),
                  const SizedBox(width: 3),
                  Text('$viewCount', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                  const Spacer(),
                  if (postedAt != null)
                    Text(DateFormat('h:mm a').format(postedAt), style: const TextStyle(color: Colors.white70, fontSize: 9)),
                ],
              ),
            ),
          ),
          if (story['media_type'] == 'video')
            const Positioned(
              top: 6,
              right: 6,
              child: Icon(Icons.videocam, color: Colors.white, size: 16),
            ),
          Positioned(
            top: 4,
            left: 4,
            child: GestureDetector(
              onTap: onDelete,
              child: Container(
                width: 26,
                height: 26,
                decoration: BoxDecoration(color: Colors.black.withOpacity(0.5), shape: BoxShape.circle),
                child: const Icon(Icons.close, color: Colors.white, size: 15),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StoriesUpsell extends StatelessWidget {
  const _StoriesUpsell({
    required this.plans,
    required this.isBusy,
    required this.onSubscribe,
    required this.topPad,
  });

  final List<Map<String, dynamic>> plans;
  final bool isBusy;
  final void Function(Map<String, dynamic> plan) onSubscribe;
  final double topPad;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.fromLTRB(20, topPad, 20, 96),
      children: [
        Icon(Icons.auto_awesome_rounded, color: foodflow.orange, size: 42),
        const SizedBox(height: 14),
        Text(
          'Share Stories with customers',
          style: TextStyle(color: foodflow.ink, fontSize: 19, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        Text(
          'Stories is included in some subscription plans, or you can add it on its own even while on commission billing.',
          style: TextStyle(color: foodflow.muted, fontSize: 13.5, height: 1.4),
        ),
        const SizedBox(height: 22),
        if (plans.isEmpty)
          Text('No Stories add-on plan available right now.', style: TextStyle(color: foodflow.muted))
        else
          ...plans.map(
            (plan) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: foodflow.surfaceColor,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: foodflow.line),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            plan['name']?.toString() ?? 'Stories Add-on',
                            style: TextStyle(color: foodflow.ink, fontSize: 15, fontWeight: FontWeight.w900),
                          ),
                        ),
                        Text(
                          '${formatCurrencyValue(context, (plan['price'] as num?)?.toDouble() ?? 0)}/${plan['billing_period'] ?? 'month'}',
                          style: TextStyle(color: foodflow.orange, fontSize: 14, fontWeight: FontWeight.w900),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: isBusy ? null : () => onSubscribe(plan),
                        style: FoodFlowTheme.zomatoPrimaryButton(radius: 10),
                        child: const Text('Add Stories', style: TextStyle(fontWeight: FontWeight.w800)),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}
