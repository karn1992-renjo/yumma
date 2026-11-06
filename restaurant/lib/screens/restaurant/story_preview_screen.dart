// lib/screens/restaurant/story_preview_screen.dart
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:video_player/video_player.dart';

import '../../widgets/stories/story_layout.dart';

/// Shows a restaurant its own story exactly as customers see it (layout, dish card and button), with its views
/// and how long it stays live.
class StoryPreviewScreen extends StatefulWidget {
  const StoryPreviewScreen({super.key, required this.story, this.onDelete});

  final Map<String, dynamic> story;

  /// Called after the owner confirms deleting; the screen closes with `true`.
  final Future<bool> Function()? onDelete;

  @override
  State<StoryPreviewScreen> createState() => _StoryPreviewScreenState();
}

class _StoryPreviewScreenState extends State<StoryPreviewScreen> {
  VideoPlayerController? _video;

  @override
  void initState() {
    super.initState();
    if (widget.story['media_type'] == 'video') _initVideo();
  }

  Future<void> _initVideo() async {
    final url = widget.story['media_url']?.toString();
    if (url == null || url.isEmpty) return;
    final controller = VideoPlayerController.networkUrl(Uri.parse(url));
    _video = controller;
    try {
      await controller.initialize();
      await controller.setLooping(true);
      await controller.play();
      if (mounted) setState(() {});
    } catch (_) {}
  }

  @override
  void dispose() {
    _video?.dispose();
    super.dispose();
  }

  Widget _media() {
    if (widget.story['media_type'] == 'video') {
      final v = _video;
      if (v != null && v.value.isInitialized) {
        return ClipRect(
          child: FittedBox(
            fit: BoxFit.cover,
            child: SizedBox(width: v.value.size.width, height: v.value.size.height, child: VideoPlayer(v)),
          ),
        );
      }
      return const Center(child: CircularProgressIndicator(color: Colors.white));
    }
    final url = widget.story['media_url']?.toString() ?? '';
    return CachedNetworkImage(imageUrl: url, fit: BoxFit.cover, width: double.infinity, height: double.infinity);
  }

  @override
  Widget build(BuildContext context) {
    final story = widget.story;
    final menuItem = story['menu_item'] is Map ? Map<String, dynamic>.from(story['menu_item'] as Map) : null;
    final views = (story['view_count'] as num?)?.toInt() ?? 0;
    final expires = DateTime.tryParse(story['expires_at']?.toString() ?? '')?.toLocal();
    final left = expires?.difference(DateTime.now());
    final leftText = left == null
        ? ''
        : left.isNegative
            ? 'Expired'
            : left.inHours >= 1
                ? '${left.inHours}h ${left.inMinutes % 60}m left'
                : '${left.inMinutes}m left';

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          StoryLayoutView(
            media: _media(),
            layout: story['layout']?.toString() ?? 'classic',
            caption: story['caption']?.toString(),
            menuItem: menuItem,
            ctaLabel: story['cta_label']?.toString(),
            topInset: 72,
            onCta: menuItem == null
                ? null
                : () => ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Customers tap this button to open the dish and order it.')),
                    ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(8, 4, 8, 0),
              child: Row(
                children: [
                  _roundButton(Icons.arrow_back_rounded, () => Navigator.of(context).maybePop()),
                  const SizedBox(width: 10),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                    decoration: BoxDecoration(color: Colors.black.withOpacity(0.5), borderRadius: BorderRadius.circular(999)),
                    child: Row(mainAxisSize: MainAxisSize.min, children: [
                      const Icon(Icons.remove_red_eye_outlined, color: Colors.white, size: 15),
                      const SizedBox(width: 5),
                      Text('$views', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12.5)),
                      if (leftText.isNotEmpty) ...[
                        const SizedBox(width: 10),
                        const Icon(Icons.schedule_rounded, color: Colors.white70, size: 14),
                        const SizedBox(width: 4),
                        Text(leftText, style: const TextStyle(color: Colors.white70, fontWeight: FontWeight.w700, fontSize: 12)),
                      ],
                    ]),
                  ),
                  const Spacer(),
                  if (widget.onDelete != null)
                    _roundButton(Icons.delete_outline_rounded, () async {
                      final deleted = await widget.onDelete!();
                      if (deleted && mounted) Navigator.of(context).pop(true);
                    }),
                ],
              ),
            ),
          ),
          if (expires != null)
            Positioned(
              left: 0,
              right: 0,
              top: MediaQuery.of(context).padding.top + 52,
              child: Center(
                child: Text(
                  'Posted ${DateFormat('d MMM, h:mm a').format(DateTime.tryParse(story['posted_at']?.toString() ?? '')?.toLocal() ?? DateTime.now())}',
                  style: const TextStyle(color: Colors.white54, fontSize: 11, fontWeight: FontWeight.w600),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _roundButton(IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(color: Colors.black.withOpacity(0.5), shape: BoxShape.circle),
        child: Icon(icon, color: Colors.white, size: 20),
      ),
    );
  }
}
