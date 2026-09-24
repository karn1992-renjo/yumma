import 'package:flutter/material.dart';

/// BookMyShow cinema design tokens and reusable UI components
/// for the movie theatre in-seat ordering experience.
class BmsTheme {
  // Brand colors
  static const Color primary = Color(0xFFF84464); // Iconic BookMyShow Red/Pink
  static const Color primaryDark = Color(0xFFDC2626);
  static const Color primaryLight = Color(0xFFFF6B88);

  // Cinema Dark Palette
  static const Color bgDark = Color(0xFF151821); // Midnight Cinema Background
  static const Color bgSurface = Color(0xFF1F2533); // Card / Sheet Surface
  static const Color bgElevated = Color(0xFF272F42); // Elevated Tile / Container
  static const Color bgInput = Color(0xFF1A1F2C);
  static const Color borderSubtle = Color(0xFF2E384D);
  static const Color borderLight = Color(0xFF3F4C68);

  // Text colors
  static const Color textPrimary = Color(0xFFFFFFFF);
  static const Color textSecondary = Color(0xFF9CA3AF);
  static const Color textMuted = Color(0xFF6B7280);
  static const Color textFaint = Color(0xFF4B5563);

  // BookMyShow Light Discovery Palette
  static const Color bgLight = Color(0xFFF5F5FA); // BookMyShow light canvas
  static const Color surfaceLight = Colors.white; // Pure white cards
  static const Color borderLightGray = Color(0xFFE5E7EB);
  static const Color textDark = Color(0xFF111827); // Dark ink header
  static const Color textDarkSecondary = Color(0xFF4B5563);
  static const Color textDarkMuted = Color(0xFF9CA3AF);

  // Status & Badges
  static const Color gold = Color(0xFFF59E0B); // Recliner / Star ratings
  static const Color green = Color(0xFF22C55E); // Veg tag / In-geofence active
  static const Color greenSoft = Color(0xFF14532D);
  static const Color red = Color(0xFFEF4444); // Non-veg tag
  static const Color cyan = Color(0xFF38BDF8); // Screen projection beam

  // Gradients
  static const LinearGradient primaryGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFF84464), Color(0xFFE51937)],
  );

  static const LinearGradient cardGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF252C3D), Color(0xFF1C2230)],
  );

  static const LinearGradient screenBeamGradient = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: [Color(0x3338BDF8), Color(0x00151821)],
  );

  // Shadows
  static List<BoxShadow> get redGlow => [
        BoxShadow(
          color: primary.withValues(alpha: 0.45),
          blurRadius: 16,
          spreadRadius: 1,
          offset: const Offset(0, 4),
        ),
      ];

  static List<BoxShadow> get cardShadow => [
        BoxShadow(
          color: Colors.black.withValues(alpha: 0.08),
          blurRadius: 10,
          offset: const Offset(0, 3),
        ),
      ];
}

/// A BookMyShow style perforated ticket container with side semicircular cutouts
/// and a dashed separator line between the upper and lower sections.
class BmsTicketCard extends StatelessWidget {
  const BmsTicketCard({
    super.key,
    required this.header,
    required this.body,
    this.notchRadius = 10.0,
    this.backgroundColor = BmsTheme.surfaceLight,
    this.borderColor = BmsTheme.borderLightGray,
    this.notchColor = BmsTheme.bgLight,
  });

  final Widget header;
  final Widget body;
  final double notchRadius;
  final Color backgroundColor;
  final Color borderColor;
  final Color notchColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor),
        boxShadow: BmsTheme.cardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          header,
          _TicketDivider(
            notchRadius: notchRadius,
            cardColor: backgroundColor,
            lineColor: borderColor,
            notchColor: notchColor,
          ),
          body,
        ],
      ),
    );
  }
}

class _TicketDivider extends StatelessWidget {
  const _TicketDivider({
    required this.notchRadius,
    required this.cardColor,
    required this.lineColor,
    required this.notchColor,
  });

  final double notchRadius;
  final Color cardColor;
  final Color lineColor;
  final Color notchColor;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: notchRadius * 2,
      child: Row(
        children: [
          // Left Notch
          CustomPaint(
            size: Size(notchRadius, notchRadius * 2),
            painter: _NotchPainter(isLeft: true, color: notchColor),
          ),
          // Dashed Divider
          Expanded(
            child: LayoutBuilder(
              builder: (context, constraints) {
                const dashWidth = 5.0;
                const dashSpace = 4.0;
                final count =
                    (constraints.maxWidth / (dashWidth + dashSpace)).floor();
                return Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: List.generate(
                    count,
                    (_) => Container(
                      width: dashWidth,
                      height: 1.5,
                      color: lineColor,
                    ),
                  ),
                );
              },
            ),
          ),
          // Right Notch
          CustomPaint(
            size: Size(notchRadius, notchRadius * 2),
            painter: _NotchPainter(isLeft: false, color: notchColor),
          ),
        ],
      ),
    );
  }
}

class _NotchPainter extends CustomPainter {
  final bool isLeft;
  final Color color;

  _NotchPainter({required this.isLeft, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.fill;

    final path = Path();
    if (isLeft) {
      path.arcTo(
        Rect.fromCircle(
          center: Offset(0, size.height / 2),
          radius: size.width,
        ),
        -1.5708, // -pi/2
        3.14159, // pi
        false,
      );
      path.lineTo(0, size.height);
      path.lineTo(0, 0);
    } else {
      path.arcTo(
        Rect.fromCircle(
          center: Offset(size.width, size.height / 2),
          radius: size.width,
        ),
        1.5708, // pi/2
        3.14159, // pi
        false,
      );
      path.lineTo(size.width, 0);
      path.lineTo(size.width, size.height);
    }
    path.close();
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _NotchPainter oldDelegate) =>
      oldDelegate.color != color || oldDelegate.isLeft != isLeft;
}

/// BookMyShow curved screen indicator at the top of the seat layout.
class BmsScreenArc extends StatelessWidget {
  const BmsScreenArc({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        CustomPaint(
          size: const Size(double.infinity, 32),
          painter: _ScreenArcPainter(),
        ),
        const SizedBox(height: 4),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 16,
              height: 1,
              color: BmsTheme.textMuted.withValues(alpha: 0.4),
            ),
            const SizedBox(width: 8),
            const Text(
              'SCREEN THIS WAY',
              style: TextStyle(
                color: BmsTheme.textMuted,
                fontSize: 10,
                letterSpacing: 2.2,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(width: 8),
            Container(
              width: 16,
              height: 1,
              color: BmsTheme.textMuted.withValues(alpha: 0.4),
            ),
          ],
        ),
      ],
    );
  }
}

class _ScreenArcPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;

    // Glowing beam gradient under arc
    final beamPath = Path()
      ..moveTo(w * 0.15, 6)
      ..quadraticBezierTo(w * 0.5, h * 0.85, w * 0.85, 6)
      ..lineTo(w * 0.95, h)
      ..lineTo(w * 0.05, h)
      ..close();

    final beamPaint = Paint()
      ..shader = const LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [Color(0x3038BDF8), Color(0x00FFFFFF)],
      ).createShader(Rect.fromLTWH(0, 0, w, h));
    canvas.drawPath(beamPath, beamPaint);

    // Curved screen arc stroke
    final arcPath = Path()
      ..moveTo(w * 0.1, 6)
      ..quadraticBezierTo(w * 0.5, h * 0.85, w * 0.9, 6);

    // Glow stroke
    final glowPaint = Paint()
      ..color = const Color(0x4038BDF8)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 6.0
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 4.0);
    canvas.drawPath(arcPath, glowPaint);

    // Crisp arc stroke
    final strokePaint = Paint()
      ..shader = const LinearGradient(
        colors: [
          Color(0x4038BDF8),
          Color(0xFF38BDF8),
          Color(0xFF0284C7),
          Color(0xFF38BDF8),
          Color(0x4038BDF8),
        ],
      ).createShader(Rect.fromLTWH(0, 0, w, h))
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 3.5;
    canvas.drawPath(arcPath, strokePaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// Small cinema badge (e.g. "DOLBY ATMOS", "M-TICKET", "4K LASER")
class BmsBadge extends StatelessWidget {
  const BmsBadge({
    super.key,
    required this.text,
    this.color = BmsTheme.borderLight,
    this.textColor = BmsTheme.textSecondary,
    this.icon,
  });

  final String text;
  final Color color;
  final Color textColor;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(5),
        border: Border.all(color: color.withValues(alpha: 0.35), width: 0.8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 11, color: textColor),
            const SizedBox(width: 4),
          ],
          Text(
            text.toUpperCase(),
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.5,
              color: textColor,
            ),
          ),
        ],
      ),
    );
  }
}

/// Veg / Non-Veg indicator icon
class BmsDietIndicator extends StatelessWidget {
  const BmsDietIndicator({super.key, required this.isVeg});

  final bool isVeg;

  @override
  Widget build(BuildContext context) {
    final color = isVeg ? BmsTheme.green : BmsTheme.red;
    return Container(
      width: 14,
      height: 14,
      decoration: BoxDecoration(
        color: Colors.transparent,
        border: Border.all(color: color, width: 1.5),
        borderRadius: BorderRadius.circular(3),
      ),
      alignment: Alignment.center,
      child: isVeg
          ? Container(
              width: 6,
              height: 6,
              decoration: BoxDecoration(
                color: color,
                shape: BoxShape.circle,
              ),
            )
          : CustomPaint(
              size: const Size(6, 6),
              painter: _TrianglePainter(color: color),
            ),
    );
  }
}

class _TrianglePainter extends CustomPainter {
  final Color color;
  _TrianglePainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.fill;
    final path = Path()
      ..moveTo(size.width / 2, 0)
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _TrianglePainter oldDelegate) =>
      oldDelegate.color != color;
}

