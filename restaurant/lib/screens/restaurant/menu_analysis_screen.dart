import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/aurora_theme.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/aurora/aurora.dart';

/// Menu performance analysis for the last 30 days.
///
/// Primary source: GET /api/restaurant/menu/analytics?days=30 (contract):
///   data.items[] = { id, name, qty, revenue, trend:[int], group:"best"|"low"|"mid",
///                    ai_suggestion:"..." }
/// Fallback: GET /api/restaurant/analytics (data.top_items[] = {name,total_orders,revenue})
class MenuAnalysisScreen extends StatefulWidget {
  const MenuAnalysisScreen({super.key});

  @override
  State<MenuAnalysisScreen> createState() => _MenuAnalysisScreenState();
}

class _MenuAnalysisScreenState extends State<MenuAnalysisScreen> {
  final ApiService _api = ApiService();
  bool _loading = true;
  bool _aiAvailable = false;
  List<_Item> _items = [];
  int _totalQty = 0;
  double _totalRevenue = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await _api.get('/restaurant/menu/analytics',
          queryParams: {'days': 30});
      final data = res is Map ? res['data'] : null;
      final raw = data is Map ? data['items'] : null;
      if (raw is List && raw.isNotEmpty) {
        _items = raw
            .whereType<Map>()
            .map((m) => _Item.fromRich(Map<String, dynamic>.from(m)))
            .toList();
        _aiAvailable = _items.any((i) => i.aiSuggestion.isNotEmpty);
      } else {
        await _loadFallback();
      }
    } catch (_) {
      await _loadFallback();
    }
    _items.sort((a, b) => b.qty.compareTo(a.qty));
    _totalQty = _items.fold(0, (s, i) => s + i.qty);
    _totalRevenue = _items.fold(0.0, (s, i) => s + i.revenue);
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _loadFallback() async {
    try {
      final res = await _api.get(ApiConstants.restaurantAnalytics,
          queryParams: {'period': 'month'});
      final data = res is Map ? res['data'] : null;
      final top = data is Map ? data['top_items'] : null;
      if (top is List) {
        _items = top
            .whereType<Map>()
            .map((m) => _Item.fromTop(Map<String, dynamic>.from(m)))
            .toList();
      }
    } catch (_) {}
    // Group: top third = best, bottom third = low.
    final n = _items.length;
    for (var i = 0; i < n; i++) {
      _items[i] = _items[i].copyWithGroup(
        i < (n / 3).ceil()
            ? 'best'
            : (i >= n - (n / 3).ceil() ? 'low' : 'mid'),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top + 60;
    final best = _items.where((i) => i.group == 'best').toList();
    final low = _items.where((i) => i.group == 'low').toList();
    final maxQty = _items.isEmpty
        ? 1
        : _items.map((i) => i.qty).reduce((a, b) => a > b ? a : b);

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Menu analysis',
                style: TextStyle(
                    color: foodflow.ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w900)),
            Text('Last 30 days',
                style: TextStyle(
                    color: foodflow.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700)),
          ],
        ),
      ),
      body: Stack(children: [
        ...AuroraTheme.auroraBlobs(),
        _loading
            ? const Center(child: CircularProgressIndicator())
            : RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  padding: EdgeInsets.fromLTRB(16, topPad, 16, 28),
                  children: [
                    _kpiHero(),
                    const SizedBox(height: 14),
                    if (_items.isEmpty)
                      _empty()
                    else ...[
                      _chartCard(maxQty),
                      const SizedBox(height: 14),
                      if (best.isNotEmpty)
                        _group('BEST SELLING', best, const Color(0xFF16A34A),
                            Icons.trending_up_rounded, maxQty),
                      if (low.isNotEmpty)
                        _group('NEEDS ATTENTION', low,
                            const Color(0xFFE2546A),
                            Icons.trending_down_rounded, maxQty),
                      if (!_aiAvailable)
                        Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text(
                            'Per-item AI suggestions appear here once the platform AI service is enabled.',
                            style: TextStyle(
                                color: foodflow.muted, fontSize: 11.5),
                          ),
                        ),
                    ],
                  ],
                ),
              ),
      ]),
    );
  }

  Widget _kpiHero() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: foodflow.brandGradient,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: foodflow.orange.withOpacity(0.26),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Row(children: [
        _heroCell('$_totalQty', 'items sold'),
        Container(width: 1, height: 32, color: Colors.white.withOpacity(0.22)),
        _heroCell(_items.length.toString(), 'dishes ordered'),
        Container(width: 1, height: 32, color: Colors.white.withOpacity(0.22)),
        _heroCell(formatCurrency(context, _totalRevenue), 'revenue'),
      ]),
    );
  }

  Widget _heroCell(String value, String label) => Expanded(
        child: Column(children: [
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(value,
                style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w900)),
          ),
          const SizedBox(height: 2),
          Text(label,
              style: TextStyle(
                  color: Colors.white.withOpacity(0.8),
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700)),
        ]),
      );

  Widget _chartCard(int maxQty) {
    final top = _items.take(6).toList();
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: foodflow.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Units sold · top ${top.length}',
              style: TextStyle(
                  color: foodflow.ink,
                  fontSize: 13,
                  fontWeight: FontWeight.w900)),
          const SizedBox(height: 14),
          ...top.map((it) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Row(children: [
                  SizedBox(
                    width: 96,
                    child: Text(it.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            color: foodflow.inkSoft,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w700)),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(5),
                      child: Container(
                        height: 10,
                        color: foodflow.line,
                        child: FractionallySizedBox(
                          alignment: Alignment.centerLeft,
                          widthFactor:
                              maxQty == 0 ? 0 : it.qty / maxQty,
                          child: DecoratedBox(
                            decoration: BoxDecoration(
                                gradient: foodflow.brandGradient),
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: 28,
                    child: Text('${it.qty}',
                        textAlign: TextAlign.right,
                        style: TextStyle(
                            color: foodflow.ink,
                            fontSize: 12,
                            fontWeight: FontWeight.w900)),
                  ),
                ]),
              )),
        ],
      ),
    );
  }

  Widget _group(String label, List<_Item> items, Color color, IconData icon,
      int maxQty) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: foodflow.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(width: 8),
            Text(label,
                style: TextStyle(
                    color: color,
                    fontSize: 12,
                    letterSpacing: 0.5,
                    fontWeight: FontWeight.w900)),
          ]),
          const SizedBox(height: 12),
          ...items.map((it) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Expanded(
                        child: Text(it.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                                color: foodflow.ink,
                                fontSize: 13,
                                fontWeight: FontWeight.w800)),
                      ),
                      Text('${it.qty} sold',
                          style: TextStyle(
                              color: foodflow.muted,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w800)),
                      const SizedBox(width: 8),
                      Text(formatCurrency(context, it.revenue),
                          style: TextStyle(
                              color: foodflow.ink,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w900)),
                    ]),
                    if (it.aiSuggestion.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: foodflow.orange.withOpacity(0.08),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(Icons.auto_awesome_rounded,
                                size: 13, color: foodflow.orange),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(it.aiSuggestion,
                                  style: TextStyle(
                                      color: foodflow.inkSoft,
                                      fontSize: 11.5,
                                      height: 1.35)),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              )),
        ],
      ),
    );
  }

  Widget _empty() => Container(
        padding: const EdgeInsets.symmetric(vertical: 34, horizontal: 20),
        decoration: BoxDecoration(
          color: foodflow.surfaceColor,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: foodflow.line),
        ),
        child: Column(children: [
          Icon(Icons.insights_outlined, color: foodflow.orange, size: 30),
          const SizedBox(height: 12),
          Text('No sales in the last 30 days',
              style: TextStyle(
                  color: foodflow.ink,
                  fontSize: 15,
                  fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          Text('Item-level performance shows up here once you start getting orders.',
              textAlign: TextAlign.center,
              style: TextStyle(color: foodflow.muted, fontSize: 12.5)),
        ]),
      );
}

class _Item {
  _Item({
    required this.name,
    required this.qty,
    required this.revenue,
    this.group = 'mid',
    this.aiSuggestion = '',
  });

  final String name;
  final int qty;
  final double revenue;
  final String group;
  final String aiSuggestion;

  _Item copyWithGroup(String g) => _Item(
        name: name,
        qty: qty,
        revenue: revenue,
        group: g,
        aiSuggestion: aiSuggestion,
      );

  factory _Item.fromRich(Map<String, dynamic> j) => _Item(
        name: j['name']?.toString() ?? 'Item',
        qty: int.tryParse('${j['qty'] ?? j['quantity'] ?? 0}') ?? 0,
        revenue: double.tryParse('${j['revenue'] ?? 0}') ?? 0,
        group: j['group']?.toString() ?? 'mid',
        aiSuggestion: j['ai_suggestion']?.toString() ?? '',
      );

  factory _Item.fromTop(Map<String, dynamic> j) => _Item(
        name: j['name']?.toString() ?? 'Item',
        qty: int.tryParse('${j['total_orders'] ?? j['qty'] ?? 0}') ?? 0,
        revenue: double.tryParse('${j['revenue'] ?? 0}') ?? 0,
      );
}
