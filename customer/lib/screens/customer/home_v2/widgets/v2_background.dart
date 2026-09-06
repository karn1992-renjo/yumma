// Static aurora backdrop — painted radial gradients, no blur, no animation
// controller running by default. Cheap: one CustomPaint, repainted only on
// theme change.

import 'package:flutter/material.dart';

import '../theme/v2_theme.dart';

class V2Background extends StatelessWidget {
  const V2Background({super.key, required this.palette});

  final V2Palette palette;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [palette.bgTop, palette.bgMid, palette.bgBottom],
          stops: const [0.0, 0.55, 1.0],
        ),
      ),
      child: CustomPaint(
        painter: _AuroraPainter(palette),
        child: const SizedBox.expand(),
      ),
    );
  }
}

class _AuroraPainter extends CustomPainter {
  _AuroraPainter(this.palette);

  final V2Palette palette;

  void _blob(Canvas canvas, Offset center, double radius, Color color) {
    final rect = Rect.fromCircle(center: center, radius: radius);
    final paint = Paint()
      ..shader = RadialGradient(
        colors: [color, color.withOpacity(0)],
      ).createShader(rect);
    canvas.drawRect(rect, paint);
  }

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final strength = palette.isDark ? 0.55 : 0.9;
    _blob(canvas, Offset(w * 0.05, h * 0.02), w * 0.85,
        palette.aurora1.withOpacity(0.5 * strength));
    _blob(canvas, Offset(w * 1.02, h * 0.12), w * 0.7,
        palette.aurora2.withOpacity(0.42 * strength));
    _blob(canvas, Offset(w * -0.1, h * 0.5), w * 0.8,
        palette.aurora3.withOpacity(0.32 * strength));
    _blob(canvas, Offset(w * 1.05, h * 0.82), w * 0.85,
        palette.aurora4.withOpacity(0.26 * strength));
  }

  @override
  bool shouldRepaint(_AuroraPainter old) => old.palette.mode != palette.mode;
}
