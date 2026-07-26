import '../models/menu_item.dart';

class PromotionDisplayUtils {
  const PromotionDisplayUtils._();

  static List<Map<String, dynamic>> activePromos(dynamic value) {
    final promos = value is List ? value : value is Map ? value['active_promos'] : null;
    if (promos is! List) return const <Map<String, dynamic>>[];
    return promos
        .whereType<Map>()
        .map((promo) => Map<String, dynamic>.from(promo))
        .where((promo) {
          final status = promo['status']?.toString().trim().toLowerCase();
          return promo['is_active'] == true || status == 'active' || status == null;
        })
        .toList(growable: false);
  }

  static String labelForPromo(
    Map promo, {
    String currencySymbol = '',
    bool preferTitle = false,
  }) {
    final title = promo['title']?.toString().trim() ?? '';
    if (preferTitle && title.isNotEmpty) return title;

    final type = _normalize(
      promo['promotion_type'] ??
          promo['promo_type'] ??
          promo['reward_type'] ??
          promo['type'],
    );
    final rewardType = _normalize(
      promo['reward_type'] ??
          (promo['rewards'] is Map ? (promo['rewards'] as Map)['type'] : null) ??
          (promo['reward_config'] is Map
              ? (promo['reward_config'] as Map)['type']
              : null),
    );
    final combined = '$type $rewardType';

    if (combined.contains('buy_2_get_1') ||
        combined.contains('buy2get1') ||
        combined.contains('buy_2')) {
      return 'Buy 2 Get 1';
    }
    if (combined.contains('buy_3_get_1') || combined.contains('buy3get1')) {
      return 'Buy 3 Get 1';
    }
    if (combined.contains('bogo') ||
        combined.contains('buy_1_get_1') ||
        combined.contains('buy1get1')) {
      return 'Buy 1 Get 1';
    }
    if (combined.contains('combo') || combined.contains('meal')) {
      return 'Combo Deal';
    }
    if (combined.contains('free_item') ||
        combined.contains('free_drink') ||
        combined.contains('free_dessert')) {
      return 'Free Item';
    }
    if (combined.contains('custom_rule')) return 'Special Offer';

    final discountType = _normalize(promo['discount_type'] ?? rewardType);
    final discountValue =
        _toDouble(promo['discount_value'] ?? promo['value'] ?? promo['amount']);
    if (discountValue > 0) {
      if (discountType.contains('percent') || type.contains('percent')) {
        return '${_formatNumber(discountValue)}% OFF';
      }
      return '$currencySymbol${_formatNumber(discountValue)} OFF';
    }

    return title.isNotEmpty ? title : 'Promotion';
  }

  static String tagForMenuItem(
    MenuItem item,
    List<Map<String, dynamic>> promos, {
    String currencySymbol = '',
  }) {
    for (final promo in promos) {
      if (appliesToMenuItem(promo, item)) {
        return labelForPromo(promo, currencySymbol: currencySymbol);
      }
    }
    return '';
  }

  static bool appliesToMenuItem(Map promo, MenuItem item) {
    final targetType = _normalize(
      promo['target_type'] ?? promo['promotion_for'] ?? promo['target'],
    );

    final itemIds = <int>{
      ..._idsFrom(promo['menu_item_ids']),
      ..._idsFrom(promo['item_ids']),
      ..._idsFrom(promo['product_ids']),
      ..._idsFromMappedList(promo['menu_items'], const [
        'id',
        'menu_item_id',
        'item_id',
        'product_id',
      ]),
      ..._idsFromNested(promo, const [
        'conditions',
        'rewards',
        'reward_config',
      ], const [
        'menu_item_ids',
        'item_ids',
        'product_ids',
        'buy_item_ids',
        'get_item_ids',
        'free_item_ids',
        'combo_item_ids',
        'required_item_ids',
      ]),
      ..._idsFromNestedRule(promo, 'conditions', 'buy_rule', const [
        'item_ids',
        'menu_item_ids',
        'product_ids',
      ]),
      ..._idsFromNestedRule(promo, 'rewards', 'reward_rule', const [
        'item_ids',
        'menu_item_ids',
        'free_item_ids',
      ]),
      ..._idsFromNestedRule(promo, 'reward_config', 'reward_rule', const [
        'item_ids',
        'menu_item_ids',
        'free_item_ids',
      ]),
    };

    if (targetType.contains('item') ||
        targetType.contains('menu') ||
        targetType.contains('product')) {
      final targetIds = _idsFrom(promo['target_ids']);
      itemIds.addAll(targetIds);
      return itemIds.contains(item.id);
    }

    if (itemIds.contains(item.id)) return true;

    final categoryIds = <int>{
      ..._idsFrom(promo['category_ids']),
      ..._idsFromMappedList(promo['promotion_categories'], const [
        'id',
        'category_id',
      ]),
      ..._idsFromNested(promo, const [
        'conditions',
        'rewards',
        'reward_config',
      ], const [
        'category_ids',
        'buy_category_ids',
        'get_category_ids',
        'eligible_category_ids',
      ]),
      ..._idsFromNestedRule(promo, 'conditions', 'buy_rule', const [
        'category_ids',
        'menu_category_ids',
      ]),
      ..._idsFromNestedRule(promo, 'rewards', 'reward_rule', const [
        'category_ids',
        'menu_category_ids',
      ]),
      ..._idsFromNestedRule(promo, 'reward_config', 'reward_rule', const [
        'category_ids',
        'menu_category_ids',
      ]),
    };

    if (targetType.contains('categor')) {
      categoryIds.addAll(_idsFrom(promo['target_ids']));
      return item.categoryId != null && categoryIds.contains(item.categoryId);
    }

    if (item.categoryId != null && categoryIds.contains(item.categoryId)) {
      return true;
    }

    return targetType.isEmpty ||
        targetType == 'all' ||
        targetType == 'restaurant' ||
        targetType == 'entire_restaurant' ||
        targetType == 'order' ||
        targetType == 'cart';
  }

  static String _normalize(dynamic value) {
    return value?.toString().trim().toLowerCase().replaceAll('-', '_') ?? '';
  }

  static double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  static String _formatNumber(double value) {
    return value.toStringAsFixed(value == value.roundToDouble() ? 0 : 1);
  }

  static Iterable<int> _idsFrom(dynamic value) {
    if (value is List) {
      return value.map(_toInt).where((id) => id != null).cast<int>();
    }
    if (value is String) {
      return value
          .split(',')
          .map((part) => _toInt(part.trim()))
          .where((id) => id != null)
          .cast<int>();
    }
    final id = _toInt(value);
    return id == null ? const <int>[] : <int>[id];
  }

  static Iterable<int> _idsFromMappedList(dynamic value, List<String> keys) {
    if (value is! List) return const <int>[];
    return value.expand((entry) {
      if (entry is! Map) return _idsFrom(entry);
      return keys
          .map((key) => _toInt(entry[key]))
          .where((id) => id != null)
          .cast<int>();
    });
  }

  static Iterable<int> _idsFromNested(
    Map promo,
    List<String> containers,
    List<String> keys,
  ) {
    return containers.expand((containerKey) {
      final container = promo[containerKey];
      if (container is! Map) return const <int>[];
      return keys.expand((key) => _idsFrom(container[key]));
    });
  }

  static Iterable<int> _idsFromNestedRule(
    Map promo,
    String containerKey,
    String ruleKey,
    List<String> keys,
  ) {
    final container = promo[containerKey];
    if (container is! Map) return const <int>[];
    final rule = container[ruleKey];
    if (rule is! Map) return const <int>[];
    return keys.expand((key) => _idsFrom(rule[key]));
  }

  static int? _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) {
      return int.tryParse(value) ?? double.tryParse(value)?.toInt();
    }
    return null;
  }
}
