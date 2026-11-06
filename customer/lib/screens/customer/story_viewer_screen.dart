// lib/screens/customer/story_viewer_screen.dart
import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';
import 'package:video_player/video_player.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../services/appsflyer_deep_link_service.dart';
import '../../services/story_prefetcher.dart';
import '../../widgets/stories/story_layout.dart';
import 'restaurant_detail_screen.dart';

/// Full-screen Instagram-style story viewer for one restaurant's current
/// reel: tap-to-advance, per-segment auto-progress (real video duration for
/// video segments, a fixed ~5s for images), swipe-down-to-dismiss. Calls
/// markViewed() as each segment opens -- that's what flips the home rail's
/// ring from gradient (unseen) to gray (seen) on the next load.
class StoryViewerScreen extends StatefulWidget {
  const StoryViewerScreen({
    super.key,
    required this.restaurantId,
    required this.restaurantName,
    this.restaurantAvatar,
    this.initialStoryId,
  });

  final int restaurantId;
  final String restaurantName;
  final String? restaurantAvatar;

  /// Start at this story (opened from a shared link) instead of the first.
  final int? initialStoryId;

  @override
  State<StoryViewerScreen> createState() => _StoryViewerScreenState();
}

class _StoryViewerScreenState extends State<StoryViewerScreen>
    with SingleTickerProviderStateMixin {
  static const _imageDuration = Duration(seconds: 5);

  final ApiService _api = ApiService();
  late final AnimationController _progressController;

  List<Map<String, dynamic>> _stories = [];
  bool _isLoading = true;
  String? _loadError;
  int _index = 0;
  VideoPlayerController? _videoController;
  bool _paused = false;

  @override
  void initState() {
    super.initState();
    _progressController = AnimationController(vsync: this)
      ..addStatusListener((status) {
        if (status == AnimationStatus.completed) _advance(1);
      });
    _load();
  }

  @override
  void dispose() {
    _progressController.dispose();
    _videoController?.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      // Usually already fetched by the home rail, so this returns instantly.
      final stories = await StoryPrefetcher.instance.reel(widget.restaurantId);
      if (!mounted) return;
      if (stories.isEmpty) {
        setState(() {
          _isLoading = false;
          _loadError = 'No stories right now.';
        });
        return;
      }
      setState(() {
        _stories = stories;
        _isLoading = false;
      });
      final start = stories.indexWhere((s) => (s['id'] as num?)?.toInt() == widget.initialStoryId);
      await _openSegment(start < 0 ? 0 : start);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _loadError = 'Unable to load stories.';
      });
    }
  }

  Map<String, dynamic>? get _current => _index < _stories.length ? _stories[_index] : null;

  Future<void> _openSegment(int index) async {
    _progressController.stop();
    await _videoController?.dispose();
    _videoController = null;

    if (index < 0 || index >= _stories.length) {
      if (mounted) Navigator.of(context).maybePop();
      return;
    }

    setState(() => _index = index);

    final story = _stories[index];
    unawaited(_markViewed(story));

    if (story['media_type'] == 'video') {
      final url = story['media_url']?.toString();
      if (url == null || url.isEmpty) {
        _advance(1);
        return;
      }
      // Play the downloaded copy when we have it (instant); otherwise stream
      // it while a background download fills the cache for next time.
      final file = await StoryPrefetcher.instance.cachedVideo(url);
      if (!mounted || _index != index) return;
      final controller = file != null
          ? VideoPlayerController.file(file)
          : VideoPlayerController.networkUrl(Uri.parse(url));
      if (file == null) unawaited(StoryPrefetcher.instance.warmStory(story));
      _videoController = controller;
      try {
        await controller.initialize();
        if (!mounted || _index != index || _videoController != controller) return;
        _warmNext(index);
        setState(() {});
        controller.play();
        _progressController.duration = controller.value.duration;
        _progressController.forward(from: 0);
      } catch (_) {
        _advance(1);
      }
    } else {
      _warmNext(index);
      _progressController.duration = _imageDuration;
      _progressController.forward(from: 0);
    }
  }

  /// Starts downloading the segment after [index] so the tap-through is instant.
  void _warmNext(int index) {
    final next = index + 1;
    if (next < _stories.length) {
      unawaited(StoryPrefetcher.instance.warmStory(_stories[next]));
    }
  }

  Future<void> _markViewed(Map<String, dynamic> story) async {
    final storyId = story['id'];
    if (storyId == null) return;
    try {
      await _api.post(ApiConstants.storyMarkViewed(storyId as int));
    } catch (_) {
      // Non-critical -- a failed view-ack shouldn't interrupt viewing.
    }
  }

  /// Heart toggle: instant (optimistic), rolled back if the server says no.
  Future<void> _toggleLike() async {
    final story = _current;
    final storyId = (story?['id'] as num?)?.toInt();
    if (story == null || storyId == null) return;

    final wasLiked = story['liked'] == true;
    final count = (story['like_count'] as num?)?.toInt() ?? 0;
    HapticFeedback.lightImpact();
    setState(() {
      story['liked'] = !wasLiked;
      story['like_count'] = wasLiked ? (count > 0 ? count - 1 : 0) : count + 1;
    });

    try {
      final response = wasLiked
          ? await _api.delete(ApiConstants.storyLike(storyId))
          : await _api.post(ApiConstants.storyLike(storyId));
      if (response is Map && response['success'] != true) throw StateError('rejected');
      if (response is Map && response['like_count'] is num && mounted) {
        setState(() => story['like_count'] = (response['like_count'] as num).toInt());
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        story['liked'] = wasLiked;
        story['like_count'] = count;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Log in to like stories.'), duration: Duration(seconds: 2)),
      );
    }
  }

  /// Shares a short AppsFlyer link that opens this restaurant in the app.
  Future<void> _share() async {
    final story = _current;
    if (story == null) return;

    _progressController.stop();
    _videoController?.pause();
    try {
      final link = await AppsFlyerDeepLinkService.instance.createRestaurantShortLink(
        restaurantId: widget.restaurantId,
        restaurantName: widget.restaurantName,
        extra: {
          'deep_link_sub2': widget.restaurantName,
          'deep_link_sub3': '${story['id'] ?? ''}',
        },
      );
      if (link == null || link.isEmpty) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Unable to create share link.'), duration: Duration(seconds: 2)),
          );
        }
        return;
      }

      final caption = story['caption']?.toString().trim() ?? '';
      final message = [
        "Check out ${widget.restaurantName}'s story",
        if (caption.isNotEmpty) caption,
        link,
      ].join('\n');
      await Share.share(message, subject: widget.restaurantName);

      final storyId = (story['id'] as num?)?.toInt();
      if (storyId != null) {
        story['share_count'] = ((story['share_count'] as num?)?.toInt() ?? 0) + 1;
        unawaited(_api.post(ApiConstants.storyShared(storyId)).catchError((_) {}));
      }
    } finally {
      if (mounted && !_paused) {
        _progressController.forward();
        _videoController?.play();
      }
    }
  }

  void _advance(int direction) {
    _openSegment(_index + direction);
  }

  void _togglePause() {
    setState(() => _paused = !_paused);
    if (_paused) {
      _progressController.stop();
      _videoController?.pause();
    } else {
      _progressController.forward();
      _videoController?.play();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onVerticalDragEnd: (details) {
          if ((details.primaryVelocity ?? 0) > 250) {
            Navigator.of(context).maybePop();
          }
        },
        onLongPressStart: (_) => _togglePause(),
        onLongPressEnd: (_) => _togglePause(),
        onTapUp: (details) {
          final width = MediaQuery.of(context).size.width;
          if (details.localPosition.dx < width / 3) {
            _advance(-1);
          } else {
            _advance(1);
          }
        },
        // Edge to edge: the picture fills the whole screen (under the notch and home indicator); only the
        // controls are kept inside the safe area, so a story is never letterboxed on an iPhone.
        child: _isLoading
            ? const SafeArea(child: Center(child: CircularProgressIndicator(color: Colors.white)))
            : _loadError != null
                ? SafeArea(child: _ErrorState(message: _loadError!))
                : Stack(
                    fit: StackFit.expand,
                    children: [
                      _buildStory(),
                      _buildProgressBars(),
                      _buildHeader(),
                      _buildActions(),
                    ],
                  ),
      ),
    );
  }

  /// The story in the layout the restaurant chose, with its dish card and button when a menu item is linked.
  Widget _buildStory() {
    final story = _current;
    if (story == null) return const SizedBox.shrink();

    final menuItem = story['menu_item'] is Map ? Map<String, dynamic>.from(story['menu_item'] as Map) : null;
    return StoryLayoutView(
      media: _buildMedia(),
      layout: story['layout']?.toString() ?? 'classic',
      caption: story['caption']?.toString(),
      menuItem: menuItem,
      ctaLabel: story['cta_label']?.toString(),
      topInset: MediaQuery.paddingOf(context).top + 64,
      bottomInset: MediaQuery.paddingOf(context).bottom,
      onCta: menuItem == null ? null : () => _openLinkedDish(menuItem),
    );
  }

  /// Opens the linked dish in the restaurant page, pausing the story meanwhile and resuming afterwards.
  Future<void> _openLinkedDish(Map<String, dynamic> menuItem) async {
    final itemId = (menuItem['id'] as num?)?.toInt();
    _progressController.stop();
    _videoController?.pause();
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => RestaurantDetailScreen(
          restaurantId: widget.restaurantId,
          initialMenuItemId: itemId,
        ),
      ),
    );
    if (!mounted || _paused) return;
    _progressController.forward();
    _videoController?.play();
  }

  Widget _buildMedia() {
    final story = _current;
    if (story == null) return const SizedBox.shrink();

    if (story['media_type'] == 'video') {
      final controller = _videoController;
      if (controller != null && controller.value.isInitialized) {
        return ClipRect(
          child: FittedBox(
            fit: BoxFit.cover,
            child: SizedBox(
              width: controller.value.size.width,
              height: controller.value.size.height,
              child: VideoPlayer(controller),
            ),
          ),
        );
      }
      // Show the poster straight away while the video buffers.
      final poster = story['thumbnail_url']?.toString();
      return Stack(
        fit: StackFit.expand,
        children: [
          if (poster != null && poster.isNotEmpty)
            CachedNetworkImage(imageUrl: poster, fit: BoxFit.cover),
          const Center(child: CircularProgressIndicator(color: Colors.white)),
        ],
      );
    }

    final url = story['media_url']?.toString();
    if (url == null || url.isEmpty) return const SizedBox.shrink();
    return CachedNetworkImage(
      imageUrl: url,
      fit: BoxFit.cover,
      width: double.infinity,
      height: double.infinity,
      placeholder: (_, __) => const Center(child: CircularProgressIndicator(color: Colors.white)),
    );
  }

  /// Like and share, stacked on the right edge like Instagram reels.
  Widget _buildActions() {
    final story = _current;
    if (story == null) return const SizedBox.shrink();
    final liked = story['liked'] == true;
    final likes = (story['like_count'] as num?)?.toInt() ?? 0;

    return Positioned(
      right: 10,
      bottom: MediaQuery.paddingOf(context).bottom + 120,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _ActionButton(
            icon: liked ? Icons.favorite : Icons.favorite_border,
            color: liked ? const Color(0xFFFF3B5C) : Colors.white,
            label: likes > 0 ? _compact(likes) : null,
            tooltip: liked ? 'Unlike' : 'Like',
            onTap: _toggleLike,
          ),
          const SizedBox(height: 14),
          _ActionButton(
            icon: Icons.send_rounded,
            color: Colors.white,
            tooltip: 'Share',
            onTap: _share,
          ),
        ],
      ),
    );
  }

  String _compact(int n) => n >= 1000000
      ? '${(n / 1000000).toStringAsFixed(1)}M'
      : n >= 1000
          ? '${(n / 1000).toStringAsFixed(1)}K'
          : '$n';

  Widget _buildProgressBars() {
    return Positioned(
      top: MediaQuery.paddingOf(context).top + 8,
      left: 8,
      right: 8,
      child: Row(
        children: List.generate(_stories.length, (segmentIndex) {
          return Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(2),
                child: SizedBox(
                  height: 2.5,
                  child: segmentIndex < _index
                      ? const ColoredBox(color: Colors.white)
                      : segmentIndex > _index
                          ? ColoredBox(color: Colors.white.withOpacity(0.3))
                          : AnimatedBuilder(
                              animation: _progressController,
                              builder: (context, _) => LinearProgressIndicator(
                                value: _progressController.value,
                                backgroundColor: Colors.white.withOpacity(0.3),
                                valueColor: const AlwaysStoppedAnimation(Colors.white),
                              ),
                            ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget _buildHeader() {
    return Positioned(
      top: MediaQuery.paddingOf(context).top + 20,
      left: 12,
      right: 12,
      child: Row(
        children: [
          CircleAvatar(
            radius: 16,
            backgroundColor: Colors.white24,
            backgroundImage: widget.restaurantAvatar != null && widget.restaurantAvatar!.isNotEmpty
                ? CachedNetworkImageProvider(widget.restaurantAvatar!)
                : null,
            child: widget.restaurantAvatar == null || widget.restaurantAvatar!.isEmpty
                ? Text(
                    widget.restaurantName.isNotEmpty ? widget.restaurantName[0].toUpperCase() : '?',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12),
                  )
                : null,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              widget.restaurantName,
              style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w800),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          IconButton(
            onPressed: () => Navigator.of(context).maybePop(),
            icon: const Icon(Icons.close, color: Colors.white),
          ),
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.icon,
    required this.color,
    required this.onTap,
    required this.tooltip,
    this.label,
  });

  final IconData icon;
  final Color color;
  final String? label;
  final String tooltip;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: tooltip,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(color: Colors.black38, shape: BoxShape.circle),
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 180),
                transitionBuilder: (child, animation) => ScaleTransition(scale: animation, child: child),
                child: Icon(icon, key: ValueKey(icon), color: color, size: 26),
              ),
            ),
            if (label != null) ...[
              const SizedBox(height: 3),
              Text(
                label!,
                style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.camera_alt_outlined, color: Colors.white54, size: 40),
          const SizedBox(height: 12),
          Text(message, style: const TextStyle(color: Colors.white70)),
          const SizedBox(height: 16),
          TextButton(
            onPressed: () => Navigator.of(context).maybePop(),
            child: const Text('Close', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }
}
