List<Map<String, dynamic>> promotionRewardLinesFromSummary(
  Map<String, dynamic> summary,
) {
  final promotionResult = summary['promotion_result'] is Map
      ? Map<String, dynamic>.from(summary['promotion_result'] as Map)
      : const <String, dynamic>{};
  final sources = <dynamic>[
    summary['reward_lines'],
    promotionResult['reward_lines'],
    summary['free_items'],
    promotionResult['free_items'],
  ];
  final lines = <Map<String, dynamic>>[];
  final seen = <String>{};

  for (final source in sources) {
    if (source is! List) continue;
    for (final raw in source.whereType<Map>()) {
      final line = Map<String, dynamic>.from(raw);
      final itemId =
          line['menu_item_id'] ?? line['item_id'] ?? line['free_item_id'];
      final normalizedId = int.tryParse(itemId?.toString() ?? '') ?? 0;
      if (normalizedId <= 0) continue;

      final promotionId = line['promotion_id']?.toString() ?? '';
      final key = '$promotionId:$normalizedId';
      if (!seen.add(key)) continue;

      final quantity = int.tryParse(
            (line['quantity'] ?? line['qty'] ?? line['free_quantity'] ?? 1)
                .toString(),
          ) ??
          1;
      lines.add({
        ...line,
        'menu_item_id': normalizedId,
        'item_id': normalizedId,
        'quantity': quantity.clamp(1, 999),
        'line_type': 'promotion_reward',
        'name': line['name'] ?? line['item_name'] ?? line['title'],
        'promotion_title': line['promotion_title'] ?? line['title'],
        'payable_amount': 0,
      });
    }
  }

  return lines;
}

double promotionSummaryNumber(
  Map<String, dynamic> summary,
  String key,
) {
  final direct = _asDouble(summary[key]);
  if (direct != null) return direct;
  final promotionResult = summary['promotion_result'];
  if (promotionResult is Map) {
    return _asDouble(promotionResult[key]) ?? 0;
  }
  return 0;
}

double? _asDouble(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse(value?.toString() ?? '');
}
