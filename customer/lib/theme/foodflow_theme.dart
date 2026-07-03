import 'package:flutter/material.dart';

class FoodFlowTheme {
  static const Color orange = Color(0xFF0A9443);
  static const Color primaryColor = orange;
  static const Color orangeDark = Color(0xFF0C7038);
  static const Color crimson = Color(0xFFFF6B00);
  static const Color ink = Color(0xFF111827);
  static const Color inkSoft = Color(0xFF374151);
  static const Color muted = Color(0xFF6B7280);
  static const Color faint = Color(0xFF9CA3AF);
  static const Color line = Color(0xFFE5E7EB);
  static const Color canvas = Color(0xFFF8F8F8);
  static const Color warmCanvas = Color(0xFFFFF3E8);
  static const Color success = Color(0xFF22C55E);
  static const Color danger = Color(0xFFE53935);

  static const LinearGradient brandGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [orange, orangeDark],
  );

  static BoxDecoration surface(
      {double radius = 12, Color color = Colors.white}) {
    return BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: line),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withOpacity(0.035),
          blurRadius: 10,
          offset: const Offset(0, 4),
        ),
      ],
    );
  }

  static BoxDecoration softSurface({double radius = 10}) {
    return BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: line),
    );
  }

  static BoxDecoration orangeBand({double radius = 14}) {
    return BoxDecoration(
      gradient: brandGradient,
      borderRadius: BorderRadius.circular(radius),
      boxShadow: [
        BoxShadow(
          color: orange.withOpacity(0.24),
          blurRadius: 10,
          offset: const Offset(0, 4),
        ),
      ],
    );
  }

  static BoxDecoration elevatedCard({
    double radius = 24,
    Color color = Colors.white,
    Color borderColor = line,
  }) {
    return BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: borderColor),
      boxShadow: [
        BoxShadow(
          color: crimson.withOpacity(0.08),
          blurRadius: 24,
          offset: const Offset(0, 12),
        ),
      ],
    );
  }

  static ButtonStyle zomatoPrimaryButton({
    Color color = crimson,
    EdgeInsetsGeometry padding =
        const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
    double radius = 18,
  }) {
    return ElevatedButton.styleFrom(
      backgroundColor: color,
      foregroundColor: Colors.white,
      elevation: 0,
      padding: padding,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(radius),
      ),
      textStyle: const TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w800,
      ),
    ).copyWith(
      shadowColor: MaterialStatePropertyAll(color.withOpacity(0.25)),
      overlayColor: const MaterialStatePropertyAll(Color(0x14FFFFFF)),
      elevation: const MaterialStatePropertyAll(0),
    );
  }

  static ButtonStyle zomatoOutlineButton({
    Color color = crimson,
    EdgeInsetsGeometry padding =
        const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
    double radius = 18,
  }) {
    return OutlinedButton.styleFrom(
      foregroundColor: color,
      side: BorderSide(color: color.withOpacity(0.25)),
      backgroundColor: color.withOpacity(0.04),
      padding: padding,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(radius),
      ),
      textStyle: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w800,
      ),
    );
  }

  static ButtonStyle softIconButton({
    Color backgroundColor = Colors.white,
    Color foregroundColor = ink,
  }) {
    return IconButton.styleFrom(
      backgroundColor: backgroundColor,
      foregroundColor: foregroundColor,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
      ),
      side: const BorderSide(color: line),
    );
  }

  static Widget vegDot(bool isVeg, {double size = 16}) {
    final color = isVeg ? success : danger;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: color, width: 1.4),
        borderRadius: BorderRadius.circular(3),
      ),
      alignment: Alignment.center,
      child: Container(
        width: size * 0.48,
        height: size * 0.48,
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(2),
        ),
      ),
    );
  }

  static Widget ratingBadge(double rating, {bool compact = false}) {
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 6 : 8,
        vertical: compact ? 3 : 4,
      ),
      decoration: BoxDecoration(
        color: success,
        borderRadius: BorderRadius.circular(5),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            rating.toStringAsFixed(1),
            style: TextStyle(
              color: Colors.white,
              fontSize: compact ? 11 : 12,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(width: 3),
          Icon(Icons.star, size: compact ? 10 : 12, color: Colors.white),
        ],
      ),
    );
  }

  static Widget emptyState({
    required IconData icon,
    required String title,
    String? subtitle,
  }) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxHeight < 180;
        return SingleChildScrollView(
          physics: const ClampingScrollPhysics(),
          padding: const EdgeInsets.all(20),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (!compact) ...[
                  Container(
                    width: 64,
                    height: 64,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF3E8),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Icon(icon, size: 30, color: orange),
                  ),
                  const SizedBox(height: 14),
                ],
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (subtitle != null && !compact) ...[
                  const SizedBox(height: 8),
                  Text(
                    subtitle,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  static Widget sectionTitle(String title, {String? trailing}) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 6),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: const TextStyle(
                color: ink,
                fontSize: 15,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          if (trailing != null)
            Text(
              trailing,
              style: const TextStyle(
                color: muted,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
        ],
      ),
    );
  }
}
