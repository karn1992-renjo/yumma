import 'dart:math' as math;

import 'package:flutter/material.dart';

class AnimatedLoadingSpinner extends StatefulWidget {
  final double size;
  final double strokeWidth;
  final Color? color;

  const AnimatedLoadingSpinner({
    super.key,
    this.size = 44,
    this.strokeWidth = 4,
    this.color,
  });

  @override
  State<AnimatedLoadingSpinner> createState() => _AnimatedLoadingSpinnerState();
}

class _AnimatedLoadingSpinnerState extends State<AnimatedLoadingSpinner>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 950),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final color = widget.color ?? Theme.of(context).colorScheme.primary;

    return SizedBox.square(
      dimension: widget.size,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, child) {
          return Transform.rotate(
            angle: _controller.value * math.pi * 2,
            child: CustomPaint(
              painter: _SpinnerPainter(
                color: color,
                progress: _controller.value,
                strokeWidth: widget.strokeWidth,
              ),
            ),
          );
        },
      ),
    );
  }
}

class AppLoadingView extends StatelessWidget {
  final String? message;
  final double spinnerSize;

  const AppLoadingView({
    super.key,
    this.message,
    this.spinnerSize = 48,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          AnimatedLoadingSpinner(size: spinnerSize),
          if (message != null) ...[
            const SizedBox(height: 14),
            Text(
              message!,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ],
      ),
    );
  }
}

class _SpinnerPainter extends CustomPainter {
  final Color color;
  final double progress;
  final double strokeWidth;

  const _SpinnerPainter({
    required this.color,
    required this.progress,
    required this.strokeWidth,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final paint = Paint()
      ..shader = SweepGradient(
        colors: [
          color.withOpacity(0.12),
          color.withOpacity(0.55),
          color,
        ],
        stops: const [0.0, 0.58, 1.0],
        transform: GradientRotation(progress * math.pi * 2),
      ).createShader(rect)
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.round;

    final inset = strokeWidth / 2;
    canvas.drawArc(
      Rect.fromLTWH(inset, inset, size.width - strokeWidth, size.height - strokeWidth),
      -math.pi / 2,
      math.pi * 1.55,
      false,
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant _SpinnerPainter oldDelegate) {
    return oldDelegate.color != color ||
        oldDelegate.progress != progress ||
        oldDelegate.strokeWidth != strokeWidth;
  }
}
