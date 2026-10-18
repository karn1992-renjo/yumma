import 'package:flutter/material.dart';

import 'grid_seat_map_painter.dart';
import 'seat_map_painter.dart';
import 'theatre_cart_provider.dart';
import 'theatre_food_court_screen.dart';
import 'theatre_models.dart';
import 'theatre_theme.dart';

class TheatreSeatPickerScreen extends StatefulWidget {
  const TheatreSeatPickerScreen({
    super.key,
    required this.theatre,
    required this.screen,
    required this.lat,
    required this.lng,
  });

  final TheatreSummary theatre;
  final TheatreScreenSeatMap screen;
  final double lat;
  final double lng;

  @override
  State<TheatreSeatPickerScreen> createState() =>
      _TheatreSeatPickerScreenState();
}

class _TheatreSeatPickerScreenState extends State<TheatreSeatPickerScreen> {
  String? _selectedSeat;
  DateTime? _lastTapMissedFeedback;

  // A tap that lands outside every seat's own catchment previously did
  // nothing visible at all -- whatever was already selected just stayed
  // highlighted, which reads exactly like "I tapped a different seat and
  // it picked the wrong one." Throttled to avoid stacking snackbars on
  // rapid repeated mis-taps.
  void _showTapMissedFeedback() {
    final now = DateTime.now();
    if (_lastTapMissedFeedback != null &&
        now.difference(_lastTapMissedFeedback!) < const Duration(seconds: 1)) {
      return;
    }
    _lastTapMissedFeedback = now;
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          content: Text('Tap didn\'t land on a seat -- try tapping closer to its center.'),
          duration: Duration(seconds: 2),
        ),
      );
  }

  void _confirm() {
    if (_selectedSeat == null) return;

    final cart = TheatreCartProvider();
    cart.setVenue(
      theatre: widget.theatre,
      screen: widget.screen,
      seatLabel: _selectedSeat!,
      lat: widget.lat,
      lng: widget.lng,
    );

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TheatreFoodCourtScreen(cart: cart),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final sections = <String, SeatMapSection>{
      for (final row in widget.screen.rows)
        if (!widget.screen.sections.containsKey(row.section))
          row.section: const SeatMapSection(
            label: 'Standard',
            colorValue: 0xFF64748B,
          ),
      ...widget.screen.sections,
    };

    return Scaffold(
      backgroundColor: BmsTheme.bgLight,
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: BmsTheme.textDark,
        elevation: 0.5,
        centerTitle: false,
        titleSpacing: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, size: 22),
          color: BmsTheme.textDark,
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.theatre.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: BmsTheme.textDark,
              ),
            ),
            const SizedBox(height: 2),
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF1F3),
                    borderRadius: BorderRadius.circular(4),
                    border: Border.all(color: const Color(0xFFFFD4DC)),
                  ),
                  child: Text(
                    widget.screen.name.toUpperCase(),
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: BmsTheme.primary,
                      letterSpacing: 0.4,
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                const Text(
                  'Select Delivery Seat',
                  style: TextStyle(
                    fontSize: 11,
                    color: Color(0xFF6B7280),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            // Screen Arc & Orientation
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 24, vertical: 6),
              child: BmsScreenArc(),
            ),

            // Zoom hint
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.pinch_outlined,
                    size: 13,
                    color: const Color(0xFF9CA3AF),
                  ),
                  const SizedBox(width: 4),
                  const Text(
                    'Pinch to zoom · Tap seat to deliver food',
                    style: TextStyle(
                      fontSize: 11,
                      color: Color(0xFF6B7280),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),

            // Interactive Seat Map Canvas
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: widget.screen.isGridLayout
                    ? GridSeatMapView(
                        rows: widget.screen.rows,
                        sections: sections,
                        rowGroupGapAfter: widget.screen.rowGroupGapAfter,
                        selectedSeat: _selectedSeat,
                        onSeatSelected: (seat) =>
                            setState(() => _selectedSeat = seat),
                      )
                    : SeatMapView(
                        rows: widget.screen.rows,
                        sections: sections,
                        selectedSeat: _selectedSeat,
                        onSeatSelected: (seat) =>
                            setState(() => _selectedSeat = seat),
                        onTapMissed: _showTapMissedFeedback,
                      ),
              ),
            ),

            const SizedBox(height: 8),

            // BookMyShow Style Seat Legend
            _buildBmsLegend(sections),

            const SizedBox(height: 12),

            // Sticky Bottom CTA Bar
            _buildBottomBar(),
          ],
        ),
      ),
    );
  }

  Widget _buildBmsLegend(Map<String, SeatMapSection> sections) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Wrap(
        alignment: WrapAlignment.center,
        spacing: 16,
        runSpacing: 8,
        children: [
          // Available (section color chairs)
          ...sections.entries.map((entry) {
            return Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                _buildChairIcon(Color(entry.value.colorValue), false),
                const SizedBox(width: 6),
                Text(
                  entry.value.label,
                  style: const TextStyle(
                    fontSize: 11,
                    color: Color(0xFF4B5563),
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            );
          }),
          // Selected
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              _buildChairIcon(BmsTheme.primary, true),
              const SizedBox(width: 6),
              const Text(
                'Selected',
                style: TextStyle(
                  fontSize: 11,
                  color: BmsTheme.primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildChairIcon(Color color, bool isSelected) {
    return SizedBox(
      width: 14,
      height: 12,
      child: CustomPaint(
        painter: _ChairIconPainter(color: color, isSelected: isSelected),
      ),
    );
  }

  Widget _buildBottomBar() {
    final hasSeat = _selectedSeat != null;

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: const Border(
          top: BorderSide(color: Color(0xFFE5E7EB)),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.06),
            blurRadius: 10,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Row(
          children: [
            // Selected Seat Badge
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'DELIVERING TO',
                    style: TextStyle(
                      color: Color(0xFF9CA3AF),
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.8,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Row(
                    children: [
                      Icon(
                        Icons.event_seat_rounded,
                        size: 16,
                        color: hasSeat ? BmsTheme.primary : const Color(0xFF9CA3AF),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        hasSeat ? 'Seat $_selectedSeat' : 'No seat selected',
                        style: TextStyle(
                          color: hasSeat
                              ? const Color(0xFF111827)
                              : const Color(0xFF9CA3AF),
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            // BMS Red CTA Button
            ElevatedButton(
              onPressed: hasSeat ? _confirm : null,
              style: ElevatedButton.styleFrom(
                backgroundColor: BmsTheme.primary,
                foregroundColor: Colors.white,
                disabledBackgroundColor: const Color(0xFFF3F4F6),
                disabledForegroundColor: const Color(0xFF9CA3AF),
                padding:
                    const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: hasSeat ? 3 : 0,
                shadowColor: BmsTheme.primary.withOpacity(0.35),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    hasSeat ? 'Order Food' : 'Pick a Seat',
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.3,
                    ),
                  ),
                  if (hasSeat) ...[
                    const SizedBox(width: 6),
                    const Icon(Icons.arrow_forward_rounded, size: 16),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChairIconPainter extends CustomPainter {
  final Color color;
  final bool isSelected;
  _ChairIconPainter({required this.color, required this.isSelected});

  @override
  void paint(Canvas canvas, Size size) {
    final backRect = RRect.fromRectAndRadius(
      const Rect.fromLTWH(1, 0, 12, 3.2),
      const Radius.circular(1.5),
    );
    final leftArm = RRect.fromRectAndRadius(
      const Rect.fromLTWH(0, 3, 2, 7),
      const Radius.circular(1),
    );
    final rightArm = RRect.fromRectAndRadius(
      const Rect.fromLTWH(12, 3, 2, 7),
      const Radius.circular(1),
    );
    final cushion = RRect.fromRectAndRadius(
      const Rect.fromLTWH(2.5, 3.2, 9, 6.8),
      const Radius.circular(2),
    );

    if (isSelected) {
      final fill = Paint()..color = BmsTheme.primary;
      final border = Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.0;
      canvas.drawRRect(backRect, fill);
      canvas.drawRRect(leftArm, fill);
      canvas.drawRRect(rightArm, fill);
      canvas.drawRRect(cushion, fill);
      canvas.drawRRect(backRect, border);
      canvas.drawRRect(cushion, border);

      // Mini white checkmark
      final checkPath = Path()
        ..moveTo(4.5, 6.5)
        ..lineTo(6.5, 8.2)
        ..lineTo(9.5, 4.8);
      final checkPaint = Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..strokeWidth = 1.2;
      canvas.drawPath(checkPath, checkPaint);
    } else {
      final cushionFill = Paint()..color = Colors.white;
      final armFill = Paint()..color = color.withValues(alpha: 0.15);
      final stroke = Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.1;
      canvas.drawRRect(backRect, armFill);
      canvas.drawRRect(leftArm, armFill);
      canvas.drawRRect(rightArm, armFill);
      canvas.drawRRect(cushion, cushionFill);
      canvas.drawRRect(backRect, stroke);
      canvas.drawRRect(leftArm, stroke);
      canvas.drawRRect(rightArm, stroke);
      canvas.drawRRect(cushion, stroke);
    }
  }

  @override
  bool shouldRepaint(covariant _ChairIconPainter oldDelegate) =>
      oldDelegate.color != color || oldDelegate.isSelected != isSelected;
}
