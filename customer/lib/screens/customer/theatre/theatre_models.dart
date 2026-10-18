/// swaad-only: models for the movie-theatre in-seat food ordering module.
/// Mirrors the JSON contract served by Api\TheatreController on the backend
/// (see the movie-theatre-in-seat-ordering project memory / plan).
library;

class TheatreSummary {
  final int id;
  final String name;
  final String code;
  final String? address;
  final String? city;
  final String? logo;
  final double distanceMeters;
  final bool withinGeofence;
  final bool aisleHandoffOnly;
  final List<String> tags;

  const TheatreSummary({
    required this.id,
    required this.name,
    required this.code,
    this.address,
    this.city,
    this.logo,
    required this.distanceMeters,
    required this.withinGeofence,
    required this.aisleHandoffOnly,
    this.tags = const [],
  });

  factory TheatreSummary.fromJson(Map<String, dynamic> json) {
    final rawTags = json['tags'] as List? ?? const [];
    return TheatreSummary(
      id: (json['id'] as num).toInt(),
      name: (json['name'] ?? '').toString(),
      code: (json['code'] ?? '').toString(),
      address: json['address']?.toString(),
      city: json['city']?.toString(),
      logo: json['logo']?.toString(),
      distanceMeters: (json['distance_meters'] as num?)?.toDouble() ?? 0,
      withinGeofence: json['within_geofence'] == true,
      aisleHandoffOnly: json['aisle_handoff_only'] == true,
      tags: rawTags
          .map((t) => t.toString().trim())
          .where((t) => t.isNotEmpty)
          .toList(),
    );
  }
}

class SeatMapPoint {
  final double x;
  final double y;
  const SeatMapPoint(this.x, this.y);

  factory SeatMapPoint.fromJson(Map<String, dynamic> json) {
    return SeatMapPoint(
      (json['x'] as num?)?.toDouble() ?? 0,
      (json['y'] as num?)?.toDouble() ?? 0,
    );
  }
}

class SeatMapRow {
  final String id;
  final String label;
  final int seatCount;
  final SeatMapPoint start;
  final SeatMapPoint end;
  final double curveOffset;
  final List<int> gaps;
  final String section;
  // Grid layout only (see [[movie-theatre-in-seat-ordering]]): seat counts
  // per horizontal block, left to right, with an aisle gap between blocks.
  // Empty for curved rows, which have no block concept.
  final List<int> columnBlocks;

  const SeatMapRow({
    required this.id,
    required this.label,
    required this.seatCount,
    required this.start,
    required this.end,
    required this.curveOffset,
    required this.gaps,
    required this.section,
    this.columnBlocks = const [],
  });

  factory SeatMapRow.fromJson(Map<String, dynamic> json) {
    return SeatMapRow(
      id: (json['id'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
      seatCount: (json['seat_count'] as num?)?.toInt() ?? 0,
      start: SeatMapPoint.fromJson(
        Map<String, dynamic>.from(json['start'] as Map? ?? {}),
      ),
      end: SeatMapPoint.fromJson(
        Map<String, dynamic>.from(json['end'] as Map? ?? {}),
      ),
      curveOffset: (json['curve_offset'] as num?)?.toDouble() ?? 0,
      gaps: (json['gaps'] as List? ?? const [])
          .map((g) => (g as num).toInt())
          .toList(),
      section: (json['section'] ?? 'standard').toString(),
      columnBlocks: (json['column_blocks'] as List? ?? const [])
          .map((b) => (b as num).toInt())
          .toList(),
    );
  }

  /// Mirrors seatLabelFor() in the admin seat-map builder's JS and
  /// isValidSeatLabel() on the backend exactly: seats are numbered
  /// sequentially, skipping gap slots.
  String? labelForIndex(int index) {
    if (gaps.contains(index)) return null;
    var seatNumber = 0;
    for (var i = 0; i <= index; i++) {
      if (!gaps.contains(i)) seatNumber++;
    }
    return '$label$seatNumber';
  }
}

class SeatMapSection {
  final String label;
  final int colorValue;
  const SeatMapSection({required this.label, required this.colorValue});

  factory SeatMapSection.fromJson(Map<String, dynamic> json) {
    return SeatMapSection(
      label: (json['label'] ?? 'Standard').toString(),
      colorValue: _parseHexColor(json['color']?.toString()) ?? 0xFF64748B,
    );
  }

  static int? _parseHexColor(String? hex) {
    if (hex == null || hex.isEmpty) return null;
    var value = hex.replaceAll('#', '');
    if (value.length == 6) value = 'FF$value';
    return int.tryParse(value, radix: 16);
  }
}

class TheatreScreenSeatMap {
  final int id;
  final int theatreId;
  final String name;
  final List<SeatMapRow> rows;
  final Map<String, SeatMapSection> sections;
  // 'curve' (default, the original free-form bezier canvas) or 'grid' (a
  // straight rows x columns layout with aisle gaps) -- two interchangeable
  // layout styles a theatre owner picks per screen in the builder. Existing
  // screens saved before 'grid' existed have no layout_type key at all and
  // correctly default to 'curve', so their rendering is unaffected.
  final String layoutType;
  final List<String> rowGroupGapAfter;

  const TheatreScreenSeatMap({
    required this.id,
    required this.theatreId,
    required this.name,
    required this.rows,
    required this.sections,
    this.layoutType = 'curve',
    this.rowGroupGapAfter = const [],
  });

  bool get isGridLayout => layoutType == 'grid';

  factory TheatreScreenSeatMap.fromJson(Map<String, dynamic> json) {
    final config = json['seat_map_config'] as Map? ?? {};
    final rowsJson = (config['rows'] as List? ?? const []);
    final sectionsJson = Map<String, dynamic>.from(
      config['sections'] as Map? ?? {},
    );

    return TheatreScreenSeatMap(
      id: (json['id'] as num).toInt(),
      theatreId: (json['theatre_id'] as num).toInt(),
      name: (json['name'] ?? '').toString(),
      rows: rowsJson
          .map((r) => SeatMapRow.fromJson(Map<String, dynamic>.from(r as Map)))
          .toList(),
      sections: sectionsJson.map(
        (key, value) => MapEntry(
          key,
          SeatMapSection.fromJson(Map<String, dynamic>.from(value as Map)),
        ),
      ),
      layoutType: config['layout_type'] == 'grid' ? 'grid' : 'curve',
      rowGroupGapAfter: (config['row_group_gap_after'] as List? ?? const [])
          .map((v) => v.toString())
          .toList(),
    );
  }
}

class TheatreRestaurant {
  final int id;
  final String name;
  final String? logoImage;
  final double rating;
  final bool isOpen;

  const TheatreRestaurant({
    required this.id,
    required this.name,
    this.logoImage,
    required this.rating,
    required this.isOpen,
  });

  factory TheatreRestaurant.fromJson(Map<String, dynamic> json) {
    return TheatreRestaurant(
      id: (json['id'] as num).toInt(),
      name: (json['name'] ?? '').toString(),
      logoImage: json['logo_image']?.toString(),
      rating: (json['rating'] as num?)?.toDouble() ?? 0,
      isOpen: json['is_open'] == true,
    );
  }
}

/// One line item added to a stall's cart. Kept minimal on purpose --
/// variant/add-on selection can be layered on later without changing the
/// checkout contract, which already accepts them.
class TheatreCartLine {
  final int menuItemId;
  final String name;
  final double price;
  int quantity;

  TheatreCartLine({
    required this.menuItemId,
    required this.name,
    required this.price,
    this.quantity = 1,
  });

  double get lineTotal => price * quantity;

  Map<String, dynamic> toJson() => {
        'id': menuItemId,
        'quantity': quantity,
      };
}

class TheatreOrderGroupResult {
  final int orderGroupId;
  final String groupNumber;
  final double total;
  final List<int> orderIds;

  const TheatreOrderGroupResult({
    required this.orderGroupId,
    required this.groupNumber,
    required this.total,
    required this.orderIds,
  });

  factory TheatreOrderGroupResult.fromJson(Map<String, dynamic> json) {
    return TheatreOrderGroupResult(
      orderGroupId: (json['order_group_id'] as num).toInt(),
      groupNumber: (json['group_number'] ?? '').toString(),
      total: (json['total'] as num?)?.toDouble() ?? 0,
      orderIds: (json['order_ids'] as List? ?? const [])
          .map((id) => (id as num).toInt())
          .toList(),
    );
  }
}

/// swaad-only: a screening currently playing on a screen, offered at
/// checkout so the customer can choose "now" or "at the interval".
///
/// Scheduling only — swaad never sells tickets, so there is no seat
/// inventory attached to a show. It exists to answer when the interval is
/// and when ordering closes.
class TheatreShowOption {
  final int id;
  final String movieTitle;
  final String? language;
  final DateTime? intervalAt;

  /// False once the interval is too close for the kitchen to make the food
  /// in time — the server decides this, never the app.
  final bool intervalAvailable;

  const TheatreShowOption({
    required this.id,
    required this.movieTitle,
    this.language,
    this.intervalAt,
    required this.intervalAvailable,
  });

  factory TheatreShowOption.fromJson(Map<String, dynamic> json) {
    return TheatreShowOption(
      id: (json['id'] as num).toInt(),
      movieTitle: (json['movie_title'] ?? '').toString(),
      language: json['language']?.toString(),
      intervalAt: json['interval_at'] != null
          ? DateTime.tryParse(json['interval_at'].toString())?.toLocal()
          : null,
      intervalAvailable: json['interval_available'] == true,
    );
  }

  bool get canDeliverAtInterval => intervalAvailable && intervalAt != null;

  String get intervalLabel {
    final at = intervalAt;
    if (at == null) return '';
    final hour = at.hour % 12 == 0 ? 12 : at.hour % 12;
    final minute = at.minute.toString().padLeft(2, '0');
    return '$hour:$minute ${at.hour < 12 ? 'AM' : 'PM'}';
  }
}
