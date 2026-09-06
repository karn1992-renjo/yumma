import 'package:flutter/material.dart';

import '../../theme/foodflow_theme.dart';

class A1PasoAuthColors {
  static const green = Color(0xFF089B3A);
  static const darkGreen = Color(0xFF047B31);

  // Brightness-aware so the auth screens follow the app theme.
  static Color get text => foodflow.ink;
  static Color get subtext => foodflow.muted;
  static Color get border => foodflow.line;
  static Color get wash => foodflow.canvas;
  static Color get surface => foodflow.surfaceColor;
}

class A1PasoShieldBadge extends StatelessWidget {
  const A1PasoShieldBadge({super.key, this.size = 126});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              color: const Color(0xFFE5F5EC),
              shape: BoxShape.circle,
              border: Border.all(color: const Color(0xFFF3FBF7), width: 8),
            ),
          ),
          for (final dot in const [
            _ShieldDot(0.11, 0.29, 11),
            _ShieldDot(0.03, 0.54, 9),
            _ShieldDot(0.18, 0.75, 6),
            _ShieldDot(0.78, 0.23, 6),
            _ShieldDot(0.91, 0.47, 12),
            _ShieldDot(0.82, 0.74, 7),
          ])
            Positioned(
              left: size * dot.left,
              top: size * dot.top,
              child: Container(
                width: dot.size,
                height: dot.size,
                decoration: const BoxDecoration(
                  color: Color(0xFFBFE7D0),
                  shape: BoxShape.circle,
                ),
              ),
            ),
          CustomPaint(
            size: Size(size * 0.64, size * 0.72),
            painter: _ShieldPainter(),
          ),
          Icon(
            Icons.lock_outline_rounded,
            color: Colors.white,
            size: size * 0.29,
          ),
        ],
      ),
    );
  }
}

class _ShieldDot {
  const _ShieldDot(this.left, this.top, this.size);

  final double left;
  final double top;
  final double size;
}

class _ShieldPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final path = Path()
      ..moveTo(size.width * 0.5, 0)
      ..lineTo(size.width, size.height * 0.2)
      ..lineTo(size.width * 0.9, size.height * 0.72)
      ..quadraticBezierTo(
        size.width * 0.78,
        size.height * 0.92,
        size.width * 0.5,
        size.height,
      )
      ..quadraticBezierTo(
        size.width * 0.22,
        size.height * 0.92,
        size.width * 0.1,
        size.height * 0.72,
      )
      ..lineTo(0, size.height * 0.2)
      ..close();

    final fill = Paint()
      ..shader = const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF11BC5C), Color(0xFF058538)],
      ).createShader(Offset.zero & size);
    canvas.drawPath(path, fill);

    final highlight = Paint()
      ..color = Colors.white.withOpacity(0.16)
      ..style = PaintingStyle.fill;
    canvas.drawPath(
      Path()
        ..moveTo(size.width * 0.5, size.height * 0.08)
        ..lineTo(size.width * 0.82, size.height * 0.22)
        ..lineTo(size.width * 0.7, size.height * 0.82)
        ..quadraticBezierTo(
          size.width * 0.6,
          size.height * 0.91,
          size.width * 0.5,
          size.height * 0.95,
        )
        ..close(),
      highlight,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class CountryFlagBadge extends StatelessWidget {
  const CountryFlagBadge({
    super.key,
    required this.countryCode,
  });

  final String countryCode;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 22,
      height: 16,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: const Color(0xFFF2F7F4),
        borderRadius: BorderRadius.circular(2),
        border: Border.all(color: const Color(0xFFE0E7E3)),
      ),
      child: const Icon(
        Icons.phone_iphone_rounded,
        color: A1PasoAuthColors.green,
        size: 12,
      ),
    );
  }
}
