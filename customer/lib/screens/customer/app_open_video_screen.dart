// lib/screens/customer/app_open_video_screen.dart
import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:video_player/video_player.dart';

enum AppOpenVideoResult { finished, skipped, failed }

/// Full-screen video played when the app opens. Skippable (after [skipAfterSeconds], 0 = straight away); closes by
/// itself at the end. Returns how it ended.
class AppOpenVideoScreen extends StatefulWidget {
  const AppOpenVideoScreen({super.key, required this.file, this.skipAfterSeconds = 0});

  final File file;
  final int skipAfterSeconds;

  @override
  State<AppOpenVideoScreen> createState() => _AppOpenVideoScreenState();
}

class _AppOpenVideoScreenState extends State<AppOpenVideoScreen> {
  VideoPlayerController? _controller;
  Timer? _skipTimer;
  late int _secondsToSkip;
  bool _finished = false;
  bool _muted = false;
  bool _failed = false;

  bool get _canSkip => _secondsToSkip <= 0;

  @override
  void initState() {
    super.initState();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    _secondsToSkip = widget.skipAfterSeconds;
    if (_secondsToSkip > 0) {
      _skipTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (!mounted) return;
        setState(() => _secondsToSkip--);
        if (_secondsToSkip <= 0) timer.cancel();
      });
    }
    _start();
  }

  Future<void> _start() async {
    final controller = VideoPlayerController.file(widget.file);
    _controller = controller;
    try {
      await controller.initialize();
      if (!mounted) return;
      controller.addListener(_onTick);
      await controller.play();
      setState(() {});
    } catch (_) {
      _failed = true;
      _close(AppOpenVideoResult.failed);
    }
  }

  void _onTick() {
    final value = _controller?.value;
    if (value == null || !value.isInitialized) return;
    if (value.hasError) {
      _close(AppOpenVideoResult.failed);
      return;
    }
    if (value.duration > Duration.zero && value.position >= value.duration - const Duration(milliseconds: 150)) {
      _close(AppOpenVideoResult.finished);
      return;
    }
    if (mounted) setState(() {});
  }

  void _close(AppOpenVideoResult result) {
    if (_finished) return;
    _finished = true;
    if (mounted) Navigator.of(context).pop(result);
  }

  @override
  void dispose() {
    _skipTimer?.cancel();
    _controller?.removeListener(_onTick);
    _controller?.dispose();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = _controller;
    final ready = controller != null && controller.value.isInitialized;
    final progress = ready && controller.value.duration.inMilliseconds > 0
        ? controller.value.position.inMilliseconds / controller.value.duration.inMilliseconds
        : 0.0;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && _canSkip) _close(AppOpenVideoResult.skipped);
      },
      child: Scaffold(
        backgroundColor: Colors.black,
        body: Stack(
          fit: StackFit.expand,
          children: [
            if (ready)
              Center(
                child: controller.value.aspectRatio < 1
                    ? SizedBox.expand(
                        child: FittedBox(
                          fit: BoxFit.cover,
                          child: SizedBox(width: controller.value.size.width, height: controller.value.size.height, child: VideoPlayer(controller)),
                        ),
                      )
                    : AspectRatio(aspectRatio: controller.value.aspectRatio, child: VideoPlayer(controller)),
              )
            else if (!_failed)
              const Center(child: CircularProgressIndicator(color: Colors.white)),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
                child: Column(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(2),
                      child: LinearProgressIndicator(
                        value: progress.clamp(0.0, 1.0),
                        minHeight: 3,
                        backgroundColor: Colors.white24,
                        valueColor: const AlwaysStoppedAnimation(Colors.white),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        _pill(
                          onTap: ready
                              ? () {
                                  setState(() => _muted = !_muted);
                                  controller.setVolume(_muted ? 0 : 1);
                                }
                              : null,
                          child: Icon(_muted ? Icons.volume_off_rounded : Icons.volume_up_rounded, color: Colors.white, size: 18),
                        ),
                        const Spacer(),
                        _pill(
                          onTap: _canSkip ? () => _close(AppOpenVideoResult.skipped) : null,
                          child: Text(
                            _canSkip ? 'Skip' : 'Skip in $_secondsToSkip',
                            style: TextStyle(color: _canSkip ? Colors.white : Colors.white60, fontWeight: FontWeight.w800, fontSize: 13),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pill({required Widget child, VoidCallback? onTap}) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(color: Colors.black.withOpacity(0.5), borderRadius: BorderRadius.circular(999), border: Border.all(color: Colors.white24)),
        child: child,
      ),
    );
  }
}
