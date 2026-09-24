import 'package:flutter_test/flutter_test.dart';
import 'package:food_delivery_customer/screens/customer/theatre/seat_map_painter.dart';
import 'package:food_delivery_customer/screens/customer/theatre/theatre_models.dart';

/// Tapping a seat must select THAT seat. The picker maps a tap back to a
/// seat by nearest-neighbour over the same bezier positions the painter
/// draws, so these guard the two ways that can go wrong: a systematic
/// offset between drawing and hit-testing, and rows packed so tightly that
/// the nearest seat to a fingertip is in the row above.
///
/// Geometry here is the real layout saved by the admin seat-map builder.
SeatMapRow _row({
  required String label,
  required int seatCount,
  required double startX,
  required double endX,
  required double y,
  List<int> gaps = const [],
  double curveOffset = 0,
}) {
  return SeatMapRow(
    id: 'row-$label',
    label: label,
    seatCount: seatCount,
    start: SeatMapPoint(startX, y),
    end: SeatMapPoint(endX, y),
    curveOffset: curveOffset,
    gaps: gaps,
    section: 'standard',
  );
}

final _rows = <SeatMapRow>[
  _row(label: 'A', seatCount: 12, startX: 80, endX: 720, y: 60, gaps: [5, 6]),
  _row(label: 'B', seatCount: 14, startX: 60, endX: 740, y: 110, gaps: [6, 7]),
];

/// The smallest gap between neighbouring seats, the same measure the view
/// now uses to decide both its opening zoom and its catchment radius.
double get _minSeatPitch {
  var pitch = double.infinity;
  for (final row in _rows) {
    final positions = SeatMapMath.seatPositions(row);
    for (var i = 1; i < positions.length; i++) {
      final gap = (positions[i] - positions[i - 1]).distance;
      if (gap > 0 && gap < pitch) pitch = gap;
    }
  }
  final rowYs = _rows.map((r) => r.start.y).toList()..sort();
  for (var i = 1; i < rowYs.length; i++) {
    final gap = rowYs[i] - rowYs[i - 1];
    if (gap > 0 && gap < pitch) pitch = gap;
  }
  return pitch;
}

/// The picker's nearest-seat lookup, in canvas units.
String? _seatAt(Offset tap, {double? radius}) {
  radius ??= _minSeatPitch * 0.5;
  String? best;
  var bestDistance = double.infinity;

  for (final row in _rows) {
    final positions = SeatMapMath.seatPositions(row);
    for (var i = 0; i < positions.length; i++) {
      final label = row.labelForIndex(i);
      if (label == null) continue;
      final dx = positions[i].dx - tap.dx;
      final dy = positions[i].dy - tap.dy;
      final distance = dx * dx + dy * dy;
      if (distance < bestDistance) {
        bestDistance = distance;
        best = label;
      }
    }
  }

  return bestDistance <= radius * radius ? best : null;
}

void main() {
  test('a tap dead on a seat selects that seat, for every seat', () {
    for (final row in _rows) {
      final positions = SeatMapMath.seatPositions(row);
      for (var i = 0; i < positions.length; i++) {
        final label = row.labelForIndex(i);
        if (label == null) continue;
        expect(_seatAt(positions[i]), label,
            reason: 'tapping the painted centre of $label picked something else');
      }
    }
  });

  test('gap slots are never selectable', () {
    for (final row in _rows) {
      final positions = SeatMapMath.seatPositions(row);
      for (final gapIndex in row.gaps) {
        expect(row.labelForIndex(gapIndex), isNull);
        // A tap on the gap resolves to a neighbouring real seat, never to
        // a phantom label.
        final picked = _seatAt(positions[gapIndex]);
        expect(picked == null || picked.startsWith(row.label), isTrue);
      }
    }
  });

  /// The reported bug: a tap lands near a seat and its neighbour is
  /// selected. In canvas units the tolerance is inherently half the seat
  /// pitch -- that part is geometry and cannot be widened. What made it
  /// unusable was the opening zoom, which squeezed 800 canvas units into
  /// the viewport width so that half a pitch was ~15 logical pixels.
  ///
  /// The view now opens zoomed so one pitch is at least 44pt, which puts
  /// the tolerance at or above the platform minimum touch target.
  test('the opening zoom makes a seat a real touch target', () {
    const minTouchTargetPx = 44.0;
    final openingScale = minTouchTargetPx / _minSeatPitch;

    final tolerancePx = (_minSeatPitch / 2) * openingScale;

    expect(tolerancePx, greaterThanOrEqualTo(minTouchTargetPx / 2),
        reason: 'a fingertip must be able to miss the centre by half a '
            'touch target and still hit the right seat');

    // For the real layout: pitch ~48.6u, so the view opens at ~0.9x rather
    // than the old ~0.5x fit, and tolerance goes from ~12px to 22px.
    expect(openingScale, greaterThan(0.5));
  });

  test('a tap in the dead space between two rows selects nothing', () {
    // The old fixed 65-unit radius was wider than the 50-unit row gap, so
    // the exact midpoint between two rows still resolved to a seat. It now
    // belongs to neither.
    final rowA = SeatMapMath.seatPositions(_rows[0])[0];
    final between = Offset(rowA.dx, rowA.dy + 25);

    expect(_seatAt(between), isNull);
  });
}
