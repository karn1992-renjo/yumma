import 'package:flutter/material.dart';

import 'theatre_models.dart';
import 'theatre_theme.dart';

/// swaad-only: renders a seat_map_config saved with layout_type='grid' --
/// a straight rows x columns layout (unlike SeatMapView's free-form bezier
/// canvas), matching the "BookMyShow-flat" reference layout the admin/
/// theatre panel's Grid builder produces. Seat selection here needs no
/// catchment-radius math at all: every seat is its own real widget with its
/// own exact hit area, so there is no equivalent of the curved layout's
/// compressed-spacing mis-tap class of bug.
class GridSeatMapView extends StatelessWidget {
  const GridSeatMapView({
    super.key,
    required this.rows,
    required this.sections,
    required this.rowGroupGapAfter,
    this.selectedSeat,
    this.onSeatSelected,
  });

  final List<SeatMapRow> rows;
  final Map<String, SeatMapSection> sections;
  final List<String> rowGroupGapAfter;
  final String? selectedSeat;
  final ValueChanged<String>? onSeatSelected;

  static const double _seatSize = 42;
  static const double _seatGap = 8;
  static const double _blockGap = 26;
  static const double _rowGroupGap = 28;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) {
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

    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          color: const Color(0xFFFAFAFA),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: InteractiveViewer(
          minScale: 0.5,
          maxScale: 3.0,
          boundaryMargin: const EdgeInsets.all(48),
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final row in rows) ...[
                  _buildRow(row),
                  SizedBox(
                    height: rowGroupGapAfter.contains(row.label)
                        ? _rowGroupGap
                        : 10,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildRow(SeatMapRow row) {
    final section = sections[row.section] ??
        const SeatMapSection(label: 'Standard', colorValue: 0xFF64748B);
    final sectionColor = Color(section.colorValue);
    final blocks = row.columnBlocks.isNotEmpty
        ? row.columnBlocks
        : [row.seatCount];

    var seatIndex = 0;
    final children = <Widget>[
      SizedBox(
        width: 22,
        child: Text(
          row.label,
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w800,
            color: Color(0xFF64748B),
          ),
        ),
      ),
    ];

    for (var blockIdx = 0; blockIdx < blocks.length; blockIdx++) {
      final blockSize = blocks[blockIdx];
      for (var b = 0; b < blockSize; b++) {
        final idx = seatIndex;
        final isGap = row.gaps.contains(idx);
        final label = row.labelForIndex(idx);
        final isSelected = !isGap && label != null && label == selectedSeat;

        children.add(_buildSeat(
          label: label,
          isGap: isGap,
          isSelected: isSelected,
          sectionColor: sectionColor,
          onTap: isGap || label == null
              ? null
              : () => onSeatSelected?.call(label),
        ));
        final isLastInBlock = b == blockSize - 1;
        children.add(SizedBox(
          width: isLastInBlock && blockIdx < blocks.length - 1
              ? _blockGap
              : _seatGap,
        ));
        seatIndex++;
      }
    }

    return Row(mainAxisSize: MainAxisSize.min, children: children);
  }

  Widget _buildSeat({
    required String? label,
    required bool isGap,
    required bool isSelected,
    required Color sectionColor,
    required VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(6),
      child: Container(
        width: _seatSize,
        height: _seatSize,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: isGap
              ? Colors.transparent
              : (isSelected ? BmsTheme.primary : Colors.white),
          borderRadius: BorderRadius.circular(6),
          border: Border.all(
            color: isGap
                ? const Color(0xFFCBD5E1)
                : (isSelected ? BmsTheme.primary : sectionColor),
            width: 2,
          ),
        ),
        child: isGap
            ? null
            : Text(
                label ?? '',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: isSelected ? Colors.white : const Color(0xFF111827),
                ),
              ),
      ),
    );
  }
}
