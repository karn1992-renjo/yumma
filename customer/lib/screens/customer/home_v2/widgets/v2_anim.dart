// Lightweight animation helpers for V2. All are implicit / one-shot so they
// cost nothing after settling.

import 'package:flutter/material.dart';

/// Fade + upward slide on first build. Use a small [delay] to stagger a list.
class V2Entrance extends StatefulWidget {
  const V2Entrance({
    super.key,
    required this.child,
    this.delay = Duration.zero,
    this.offset = 18,
    this.duration = const Duration(milliseconds: 420),
  });

  final Widget child;
  final Duration delay;
  final double offset;
  final Duration duration;

  @override
  State<V2Entrance> createState() => _V2EntranceState();
}

class _V2EntranceState extends State<V2Entrance>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c =
      AnimationController(vsync: this, duration: widget.duration);
  late final Animation<double> _t =
      CurvedAnimation(parent: _c, curve: Curves.easeOutCubic);

  @override
  void initState() {
    super.initState();
    if (widget.delay == Duration.zero) {
      _c.forward();
    } else {
      Future<void>.delayed(widget.delay, () {
        if (mounted) _c.forward();
      });
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _t,
      builder: (context, child) => Opacity(
        opacity: _t.value,
        child: Transform.translate(
          offset: Offset(0, widget.offset * (1 - _t.value)),
          child: child,
        ),
      ),
      child: widget.child,
    );
  }
}

/// Scale + fade press feedback without a Material ink splash (keeps glass clean).
class V2Tappable extends StatefulWidget {
  const V2Tappable({
    super.key,
    required this.child,
    required this.onTap,
    this.scale = 0.96,
    this.borderRadius,
  });

  final Widget child;
  final VoidCallback? onTap;
  final double scale;
  final BorderRadius? borderRadius;

  @override
  State<V2Tappable> createState() => _V2TappableState();
}

class _V2TappableState extends State<V2Tappable> {
  bool _down = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: widget.onTap == null ? null : (_) => setState(() => _down = true),
      onTapUp: widget.onTap == null ? null : (_) => setState(() => _down = false),
      onTapCancel:
          widget.onTap == null ? null : () => setState(() => _down = false),
      onTap: widget.onTap,
      child: AnimatedScale(
        scale: _down ? widget.scale : 1,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: widget.child,
      ),
    );
  }
}

/// Fade-through page transition for navigating between V2 screens.
class V2PageRoute<T> extends PageRouteBuilder<T> {
  V2PageRoute({required WidgetBuilder builder})
      : super(
          transitionDuration: const Duration(milliseconds: 320),
          reverseTransitionDuration: const Duration(milliseconds: 240),
          pageBuilder: (context, a, b) => builder(context),
          transitionsBuilder: (context, a, b, child) {
            final curved = CurvedAnimation(parent: a, curve: Curves.easeOutCubic);
            return FadeTransition(
              opacity: curved,
              child: SlideTransition(
                position: Tween<Offset>(
                  begin: const Offset(0, 0.03),
                  end: Offset.zero,
                ).animate(curved),
                child: child,
              ),
            );
          },
        );
}
