import 'package:country_flags/country_flags.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class A1PasoAuthColors {
  static const green = Color(0xFF089B3A);
  static const darkGreen = Color(0xFF047B31);
  static const text = Color(0xFF111111);
  static const subtext = Color(0xFF6B7280);
  static const border = Color(0xFFD8DCE2);
  static const wash = Color(0xFFF5FFFA);
}

class A1PasoLogo extends StatelessWidget {
  const A1PasoLogo({super.key, this.width = 230});

  final double width;

  @override
  Widget build(BuildContext context) {
    final scale = width / 230;
    return SizedBox(
      width: width,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  'A1',
                  style: TextStyle(
                    color: A1PasoAuthColors.green,
                    fontSize: 64 * scale,
                    height: 0.84,
                    fontWeight: FontWeight.w900,
                    fontStyle: FontStyle.italic,
                    letterSpacing: 0,
                  ),
                ),
                Text(
                  'pas',
                  style: TextStyle(
                    color: Colors.black,
                    fontSize: 56 * scale,
                    height: 0.86,
                    fontWeight: FontWeight.w900,
                    fontStyle: FontStyle.italic,
                    letterSpacing: 0,
                  ),
                ),
                Container(
                  width: 39 * scale,
                  height: 39 * scale,
                  margin: EdgeInsets.only(left: 1 * scale, bottom: 3 * scale),
                  decoration: const BoxDecoration(
                    color: A1PasoAuthColors.green,
                    shape: BoxShape.circle,
                  ),
                  child: Center(
                    child: Transform.rotate(
                      angle: -0.72,
                      child: Container(
                        width: 28 * scale,
                        height: 11 * scale,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          SizedBox(height: 11 * scale),
          Row(
            children: [
              Expanded(
                child: Container(height: 2, color: A1PasoAuthColors.green),
              ),
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 11 * scale),
                child: Text(
                  'D E L I V E R Y',
                  style: TextStyle(
                    color: A1PasoAuthColors.green,
                    fontSize: 13 * scale,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 8 * scale,
                    height: 1,
                  ),
                ),
              ),
              Expanded(
                child: Container(height: 2, color: A1PasoAuthColors.green),
              ),
            ],
          ),
          SizedBox(height: 11 * scale),
          Text.rich(
            TextSpan(
              text: 'Cerca de ti, ',
              children: const [
                TextSpan(
                  text: 'a un paso.',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ],
            ),
            textAlign: TextAlign.center,
            style: TextStyle(
              color: A1PasoAuthColors.green,
              fontSize: 15 * scale,
              fontWeight: FontWeight.w600,
              fontStyle: FontStyle.italic,
              height: 1,
            ),
          ),
        ],
      ),
    );
  }
}

class A1PasoDeliveryScene extends StatelessWidget {
  const A1PasoDeliveryScene({super.key});

  @override
  Widget build(BuildContext context) {
    return SvgPicture.asset(
      'assets/images/a1paso_delivery_scene.svg',
      fit: BoxFit.contain,
    );
  }
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
        ..quadraticBezierTo(size.width * 0.6, size.height * 0.91,
            size.width * 0.5, size.height * 0.95)
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
    try {
      return CountryFlag.fromPhonePrefix(
        countryCode,
        theme: const ImageTheme(
          width: 22,
          height: 16,
          shape: RoundedRectangle(2),
        ),
      );
    } catch (_) {
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
}
