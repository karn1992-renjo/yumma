// lib/screens/customer/story_viewer_screen.dart
import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';

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
  });

  final int restaurantId;
  final String restaurantName;
  final String? restaurantAvatar;

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
      final response = await _api.get(
        ApiConstants.restaurantStories(widget.restaurantId),
      );
      if (response['success'] == true) {
        final stories = (response['data'] as List? ?? [])
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList();
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
        await _openSegment(0);
      } else {
        if (!mounted) return;
        setState(() {
          _isLoading = false;
          _loadError = response['message']?.toString() ?? 'Unable to load stories.';
        });
      }
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
      final controller = VideoPlayerController.networkUrl(Uri.parse(url));
      _videoController = controller;
      try {
        await controller.initialize();
        if (!mounted) return;
        setState(() {});
        controller.play();
        _progressController.duration = controller.value.duration;
        _progressController.forward(from: 0);
      } catch (_) {
        _advance(1);
      }
    } else {
      _progressController.duration = _imageDuration;
      _progressController.forward(from: 0);
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
        child: SafeArea(
          child: _isLoading
              ? const Center(child: CircularProgressIndicator(color: Colors.white))
              : _loadError != null
                  ? _ErrorState(message: _loadError!)
                  : Stack(
                      fit: StackFit.expand,
                      children: [
                        _buildMedia(),
                        _buildProgressBars(),
                        _buildHeader(),
                        if (_current?['caption'] != null &&
                            (_current!['caption'] as String).isNotEmpty)
                          _buildCaption(_current!['caption'] as String),
                      ],
                    ),
        ),
      ),
    );
  }

  Widget _buildMedia() {
    final story = _current;
    if (story == null) return const SizedBox.shrink();

    if (story['media_type'] == 'video') {
      final controller = _videoController;
      if (controller != null && controller.value.isInitialized) {
        return Center(
          child: AspectRatio(
            aspectRatio: controller.value.aspectRatio,
            child: VideoPlayer(controller),
          ),
        );
      }
      return const Center(child: CircularProgressIndicator(color: Colors.white));
    }

    final url = story['media_url']?.toString();
    if (url == null || url.isEmpty) return const SizedBox.shrink();
    return CachedNetworkImage(
      imageUrl: url,
      fit: BoxFit.contain,
      width: double.infinity,
      height: double.infinity,
      placeholder: (_, __) => const Center(child: CircularProgressIndicator(color: Colors.white)),
    );
  }

  Widget _buildProgressBars() {
    return Positioned(
      top: 8,
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
      top: 20,
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

  Widget _buildCaption(String caption) {
    return Positioned(
      left: 16,
      right: 16,
      bottom: 24,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: Colors.black.withOpacity(0.45),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Text(
          caption,
          style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
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
