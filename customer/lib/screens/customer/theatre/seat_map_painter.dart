import 'dart:math' show sqrt;
import 'dart:ui' as ui;
import 'package:flutter/material.dart';

import 'theatre_models.dart';
import 'theatre_theme.dart';

/// swaad-only: renders a saved seat_map_config matching the admin's
/// seat-map builder contract, upgraded with BookMyShow cinema aesthetics
/// and interactive pinch-to-zoom & pan.
class SeatMapView extends StatefulWidget {
  const SeatMapView({
    super.key,
    required this.rows,
    required this.sections,
    this.selectedSeat,
    this.onSeatSelected,
  });

  final List<SeatMapRow> rows;
  final Map<String, SeatMapSection> sections;
  final String? selectedSeat;
  final ValueChanged<String>? onSeatSelected;

  @override
  State<SeatMapView> createState() => _SeatMapViewState();
}

class _SeatMapViewState extends State<SeatMapView> {
  static const double _canvasWidth = 800;

  double get _computedCanvasHeight {
    double maxY = 220;
    for (final row in widget.rows) {
      final cp = SeatMapMath.controlPoint(row);
      final yValues = [row.start.y, row.end.y, cp.dy];
      for (final y in yValues) {
        if (y > maxY) maxY = y;
      }
    }
    return (maxY + 60).clamp(240.0, 560.0);
  }

  late List<_SeatHit> _hits;
  final TransformationController _transformController =
      TransformationController();

  @override
  void initState() {
    super.initState();
    _hits = _computeHits();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initFit());
  }

  void _initFit() {
    if (!mounted) return;
    final box = context.findRenderObject() as RenderBox?;
    if (box != null && box.hasSize && box.size.width > 0) {
      final viewW = box.size.width;
      final targetScale = (viewW / _canvasWidth).clamp(0.4, 1.0);
      _transformController.value =
          Matrix4.identity()..scale(targetScale, targetScale);
    }
  }

  @override
  void dispose() {
    _transformController.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(covariant SeatMapView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.rows != widget.rows) {
      _hits = _computeHits();
    }
  }

  List<_SeatHit> _computeHits() {
    final hits = <_SeatHit>[];
    for (final row in widget.rows) {
      final positions = SeatMapMath.seatPositions(row);
      for (var i = 0; i < positions.length; i++) {
        final label = row.labelForIndex(i);
        hits.add(_SeatHit(position: positions[i], label: label, row: row));
      }
    }
    return hits;
  }

  void _handleTap(Offset localPosition, Size renderSize, double canvasHeight) {
    final scaleX = _canvasWidth / renderSize.width;
    final scaleY = canvasHeight / renderSize.height;
    final tapped = Offset(
      localPosition.dx * scaleX,
      localPosition.dy * scaleY,
    );

    _SeatHit? nearest;
    var nearestDistance = double.infinity;
    for (final hit in _hits) {
      if (hit.label == null) continue;
      final dx = hit.position.dx - tapped.dx;
      final dy = hit.position.dy - tapped.dy;
      final distance = dx * dx + dy * dy;
      if (distance < nearestDistance) {
        nearestDistance = distance;
        nearest = hit;
      }
    }

    // Comfortable tap radius: up to 65 canvas units (~28 screen pixels)
    // Ensures seats in curved and straight rows are effortlessly selected
    if (nearest != null && nearestDistance <= 65 * 65) {
      widget.onSeatSelected?.call(nearest.label!);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.rows.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.event_seat_outlined,
                  size: 44, color: BmsTheme.textMuted.withValues(alpha: 0.5)),
              const SizedBox(height: 12),
              const Text(
                'This screen has no seat map yet.\nAsk the theatre to configure it before ordering.',
                textAlign: TextAlign.center,
                style: TextStyle(color: BmsTheme.textSecondary, height: 1.4),
              ),
            ],
          ),
        ),
      );
    }

    final canvasHeight = _computedCanvasHeight;

    return LayoutBuilder(
      builder: (context, constraints) {
        // Uniform aspect ratio with 800 width inside pannable InteractiveViewer
        final renderWidth = 800.0;
        final renderHeight = canvasHeight;
        final renderSize = Size(renderWidth, renderHeight);

        return ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: Container(
            decoration: BoxDecoration(
              color: const Color(0xFFF4F5F9),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: InteractiveViewer(
              transformationController: _transformController,
              minScale: 0.35,
              maxScale: 3.5,
              boundaryMargin: const EdgeInsets.symmetric(
                horizontal: 48,
                vertical: 36,
              ),
              child: Center(
                child: GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTapDown: (details) => _handleTap(
                    details.localPosition,
                    renderSize,
                    canvasHeight,
                  ),
                  onTapUp: (details) => _handleTap(
                    details.localPosition,
                    renderSize,
                    canvasHeight,
                  ),
                  child: CustomPaint(
                    size: renderSize,
                    painter: _SeatMapPainter(
                      rows: widget.rows,
                      sections: widget.sections,
                      selectedSeat: widget.selectedSeat,
                      canvasWidth: _canvasWidth,
                      canvasHeight: canvasHeight,
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _SeatHit {
  final Offset position;
  final String? label;
  final SeatMapRow row;
  const _SeatHit({
    required this.position,
    required this.label,
    required this.row,
  });
}

/// The shared bezier math -- kept as static functions so both the painter
/// and the tap-hit-test above use exactly the same seat positions.
class SeatMapMath {
  static Offset controlPoint(SeatMapRow row) {
    final mx = (row.start.x + row.end.x) / 2;
    final my = (row.start.y + row.end.y) / 2;
    final dx = row.end.x - row.start.x;
    final dy = row.end.y - row.start.y;
    final lenSquared = dx * dx + dy * dy;
    final len = lenSquared == 0 ? 1 : sqrt(lenSquared);
    final nx = -dy / len;
    final ny = dx / len;
    return Offset(mx + nx * row.curveOffset, my + ny * row.curveOffset);
  }

  static Offset bezierPoint(SeatMapRow row, double t) {
    final p0 = Offset(row.start.x, row.start.y);
    final p2 = Offset(row.end.x, row.end.y);
    final p1 = controlPoint(row);
    final mt = 1 - t;
    return Offset(
      mt * mt * p0.dx + 2 * mt * t * p1.dx + t * t * p2.dx,
      mt * mt * p0.dy + 2 * mt * t * p1.dy + t * t * p2.dy,
    );
  }

  static List<Offset> seatPositions(SeatMapRow row) {
    final n = row.seatCount < 1 ? 1 : row.seatCount;
    return List.generate(n, (i) {
      final t = n == 1 ? 0.5 : (i + 0.5) / n;
      return bezierPoint(row, t);
    });
  }
}

class _SeatMapPainter extends CustomPainter {
  final List<SeatMapRow> rows;
  final Map<String, SeatMapSection> sections;
  final String? selectedSeat;
  final double canvasWidth;
  final double canvasHeight;

  _SeatMapPainter({
    required this.rows,
    required this.sections,
    required this.selectedSeat,
    required this.canvasWidth,
    required this.canvasHeight,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final scaleX = size.width / canvasWidth;
    final scaleY = size.height / canvasHeight;
    canvas.save();
    canvas.scale(scaleX, scaleY);

    // Clean light cinema floor background
    final bgPaint = Paint()..color = const Color(0xFFF4F5F9);
    canvas.drawRect(Rect.fromLTWH(0, 0, canvasWidth, canvasHeight), bgPaint);

    for (final row in rows) {
      final section = sections[row.section] ??
          const SeatMapSection(label: 'Standard', colorValue: 0xFF64748B);
      final sectionColor = Color(section.colorValue);
      final cp = SeatMapMath.controlPoint(row);

      // Subtle row guide curve on light floor
      final guidePaint = Paint()
        ..color = const Color(0xFFE2E8F0)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.2;
      final path = Path()
        ..moveTo(row.start.x, row.start.y)
        ..quadraticBezierTo(cp.dx, cp.dy, row.end.x, row.end.y);
      canvas.drawPath(path, guidePaint);

      // Row Alphabet Label pill on the left
      final labelPos = SeatMapMath.bezierPoint(row, 0.015);
      final labelOffset = Offset(labelPos.dx - 28, labelPos.dy - 10);

      final labelBgPaint = Paint()
        ..color = Colors.white
        ..style = PaintingStyle.fill;
      final labelBorderPaint = Paint()
        ..color = const Color(0xFFCBD5E1)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.0;
      final labelRRect = RRect.fromRectAndRadius(
        Rect.fromLTWH(labelOffset.dx, labelOffset.dy, 20, 18),
        const Radius.circular(5),
      );
      canvas.drawRRect(labelRRect, labelBgPaint);
      canvas.drawRRect(labelRRect, labelBorderPaint);

      _drawText(
        canvas,
        row.label,
        Offset(labelOffset.dx + 5.0, labelOffset.dy + 2.0),
        const Color(0xFF334155),
        10.0,
        FontWeight.w800,
      );

      final positions = SeatMapMath.seatPositions(row);
      for (var i = 0; i < positions.length; i++) {
        final isGap = row.gaps.contains(i);
        final label = row.labelForIndex(i);
        final isSelected = !isGap && label != null && label == selectedSeat;

        if (isGap) {
          // Minimal space marker for gap
          final gapPaint = Paint()
            ..color = const Color(0xFFE2E8F0)
            ..style = PaintingStyle.fill;
          canvas.drawCircle(positions[i], 3.0, gapPaint);
          continue;
        }

        final center = positions[i];
        _drawCinemaChair(canvas, center, sectionColor, isSelected);
      }
    }

    canvas.restore();
  }

  /// Draws an attractive, branded cinema armchair glyph (BookMyShow / PVR style):
  /// - Top curved backrest cushion
  /// - Left & right distinct flanking armrests
  /// - Center plush seat cushion
  /// - Crisp white checkmark (✓) and glowing aura when selected
  void _drawCinemaChair(
    Canvas canvas,
    Offset center,
    Color sectionColor,
    bool isSelected,
  ) {
    final scale = isSelected ? 1.2 : 1.0;
    final backWidth = 19.0 * scale;
    final backHeight = 6.4 * scale;
    final armWidth = 3.0 * scale;
    final armHeight = 12.0 * scale;
    final cushionWidth = 13.0 * scale;
    final cushionHeight = 11.2 * scale;

    final backTop = center.dy - 10.0 * scale;
    final armTop = center.dy - 2.8 * scale;
    final cushionTop = center.dy - 2.2 * scale;

    final backRRect = RRect.fromRectAndRadius(
      Rect.fromCenter(
        center: Offset(center.dx, backTop + backHeight / 2),
        width: backWidth,
        height: backHeight,
      ),
      Radius.circular(2.8 * scale),
    );

    // Flanking armrests with clean 1.2px air gap from cushion
    final leftArmRRect = RRect.fromRectAndRadius(
      Rect.fromCenter(
        center: Offset(center.dx - 9.2 * scale, armTop + armHeight / 2),
        width: armWidth,
        height: armHeight,
      ),
      Radius.circular(1.6 * scale),
    );

    final rightArmRRect = RRect.fromRectAndRadius(
      Rect.fromCenter(
        center: Offset(center.dx + 9.2 * scale, armTop + armHeight / 2),
        width: armWidth,
        height: armHeight,
      ),
      Radius.circular(1.6 * scale),
    );

    final cushionRRect = RRect.fromRectAndRadius(
      Rect.fromCenter(
        center: Offset(center.dx, cushionTop + cushionHeight / 2),
        width: cushionWidth,
        height: cushionHeight,
      ),
      Radius.circular(2.6 * scale),
    );

    if (isSelected) {
      // 1. Dual-layer radiant glow behind selected chair
      final glowPaint = Paint()
        ..color = BmsTheme.primary.withValues(alpha: 0.55)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 7.0);
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromCenter(
            center: center,
            width: 25 * scale,
            height: 22 * scale,
          ),
          const Radius.circular(6),
        ),
        glowPaint,
      );

      // 2. BookMyShow vibrant red fill
      final fillPaint = Paint()
        ..color = BmsTheme.primary
        ..style = PaintingStyle.fill;
      canvas.drawRRect(backRRect, fillPaint);
      canvas.drawRRect(leftArmRRect, fillPaint);
      canvas.drawRRect(rightArmRRect, fillPaint);
      canvas.drawRRect(cushionRRect, fillPaint);

      // 3. Crisp white border
      final borderPaint = Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.6;
      canvas.drawRRect(backRRect, borderPaint);
      canvas.drawRRect(leftArmRRect, borderPaint);
      canvas.drawRRect(rightArmRRect, borderPaint);
      canvas.drawRRect(cushionRRect, borderPaint);

      // 4. Iconic white checkmark (✓) inside seat cushion
      final checkPath = Path()
        ..moveTo(center.dx - 3.8 * scale, center.dy + 2.0 * scale)
        ..lineTo(center.dx - 0.8 * scale, center.dy + 5.2 * scale)
        ..lineTo(center.dx + 4.2 * scale, center.dy - 1.2 * scale);
      final checkPaint = Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..strokeWidth = 2.0 * scale;
      canvas.drawPath(checkPath, checkPaint);
    } else {
      // Available chair: Crisp white cushion with section-tinted armrests and bold section rim
      final cushionFillPaint = Paint()
        ..color = Colors.white
        ..style = PaintingStyle.fill;
      final armFillPaint = Paint()
        ..color = sectionColor.withValues(alpha: 0.14)
        ..style = PaintingStyle.fill;

      canvas.drawRRect(backRRect, armFillPaint);
      canvas.drawRRect(leftArmRRect, armFillPaint);
      canvas.drawRRect(rightArmRRect, armFillPaint);
      canvas.drawRRect(cushionRRect, cushionFillPaint);

      final strokePaint = Paint()
        ..color = sectionColor
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.35;
      canvas.drawRRect(backRRect, strokePaint);
      canvas.drawRRect(leftArmRRect, strokePaint);
      canvas.drawRRect(rightArmRRect, strokePaint);
      canvas.drawRRect(cushionRRect, strokePaint);

      // Subtle headrest stitch accent
      final accentPaint = Paint()
        ..color = sectionColor.withValues(alpha: 0.45)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 0.9;
      canvas.drawLine(
        Offset(center.dx - 5.5, backTop + backHeight * 0.72),
        Offset(center.dx + 5.5, backTop + backHeight * 0.72),
        accentPaint,
      );
    }
  }

  void _drawText(
    Canvas canvas,
    String text,
    Offset offset,
    Color color,
    double fontSize,
    FontWeight weight,
  ) {
    final painter = TextPainter(
      text: TextSpan(
        text: text,
        style: TextStyle(
          color: color,
          fontSize: fontSize,
          fontWeight: weight,
          letterSpacing: 0.2,
        ),
      ),
      textDirection: ui.TextDirection.ltr,
    )..layout();
    painter.paint(canvas, offset);
  }

  @override
  bool? hitTest(Offset position) => true;

  @override
  bool shouldRepaint(covariant _SeatMapPainter oldDelegate) {
    return oldDelegate.rows != rows ||
        oldDelegate.selectedSeat != selectedSeat ||
        oldDelegate.canvasHeight != canvasHeight;
  }
}
