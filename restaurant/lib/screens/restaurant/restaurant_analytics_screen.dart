import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../providers/restaurant_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../utils/currency_utils.dart';
import '../../utils/json_utils.dart';

class RestaurantAnalyticsScreen extends StatefulWidget {
  const RestaurantAnalyticsScreen({super.key});

  @override
  State<RestaurantAnalyticsScreen> createState() =>
      _RestaurantAnalyticsScreenState();
}

class _RestaurantAnalyticsScreenState extends State<RestaurantAnalyticsScreen> {
  final ApiService _api = ApiService();
  Map<String, dynamic> _performance = {};
  Map<String, dynamic> _adPerformance = {};
  Map<String, dynamic> _compare = {};
  bool _loadingPerformance = true;
  bool _loadingCompare = false;
  bool _hasPerformance = false;
  String? _error;
  String _tab = 'performance';
  String _period = 'week';
  String? _selectedCity;
  String _section = 'Sales';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final provider = context.read<RestaurantProvider>();
      if (provider.restaurants.isEmpty) await provider.loadRestaurants();
      await _loadPerformance();
      unawaited(_loadAdPerformance());
    });
  }

  void _applyPerformance(dynamic response) {
    if (response is! Map ||
        response['success'] != true ||
        response['data'] is! Map) {
      return;
    }
    _performance = Map<String, dynamic>.from(response['data']);
    final sections = _performanceSections;
    if (!sections.any((s) => s.title == _section)) {
      _section = sections.isEmpty ? 'Sales' : sections.first.title;
    }
    _hasPerformance = true;
  }

  Future<void> _loadPerformance() async {
    setState(() {
      if (!_hasPerformance) _loadingPerformance = true;
      _error = null;
    });
    try {
      final params = _queryParams();
      final cached = await _api.peekCache(ApiConstants.restaurantAnalytics,
          queryParams: params);
      if (mounted && cached != null) {
        setState(() {
          _applyPerformance(cached);
          _loadingPerformance = false;
        });
      }
      final response = await _api.get(ApiConstants.restaurantAnalytics,
          queryParams: params);
      if (!mounted) return;
      if (response['success'] == true && response['data'] is Map) {
        setState(() => _applyPerformance(response));
      } else {
        setState(() => _error = response['message']?.toString() ??
            'Unable to load restaurant reports.');
      }
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loadingPerformance = false);
    }
  }

  Future<void> _loadAdPerformance() async {
    try {
      final response = await _api.get(ApiConstants.restaurantAdPerformance);
      if (!mounted) return;
      if (response['success'] == true && response['data'] is Map) {
        setState(() {
          _adPerformance = Map<String, dynamic>.from(response['data']);
        });
      }
    } catch (e) {
      debugPrint('Load ad performance error: $e');
    }
  }

  Future<void> _loadCompare() async {
    setState(() {
      _loadingCompare = true;
      _error = null;
    });
    try {
      final response = await _api.get(ApiConstants.restaurantAnalyticsCompare,
          queryParams: _queryParams());
      if (!mounted) return;
      if (response['success'] == true && response['data'] is Map) {
        setState(() => _compare = Map<String, dynamic>.from(response['data']));
      } else {
        setState(() => _error = response['message']?.toString() ??
            'Unable to load comparison analysis.');
      }
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loadingCompare = false);
    }
  }

  Map<String, dynamic> _queryParams() {
    final provider = context.read<RestaurantProvider>();
    return {
      'period': _period,
      if (provider.selectedRestaurantId != null)
        'restaurant_id': provider.selectedRestaurantId,
      if (_selectedCity != null && _selectedCity!.isNotEmpty)
        'city': _selectedCity,
    };
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().currentUser;
    if (!(user?.canViewReports ?? true)) return const _ReportsAccessDenied();
    final provider = context.watch<RestaurantProvider>();
    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Text(
          'Business reports',
          style: TextStyle(
            color: foodflow.ink,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
      body: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(color: foodflow.canvas),
              child: Stack(children: AuroraTheme.auroraBlobs()),
            ),
          ),
          Positioned.fill(
            child: RefreshIndicator(
              color: _orange,
              onRefresh:
                  _tab == 'compare' ? _loadCompare : _loadPerformance,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: EdgeInsets.fromLTRB(
                  0,
                  MediaQuery.of(context).padding.top + 64,
                  0,
                  24,
                ),
                children: [
                  _ReportsHeader(
                      selectedTab: _tab,
                      onTab: (value) async {
                        setState(() => _tab = value);
                        if (value == 'compare' && _compare.isEmpty)
                          await _loadCompare();
                      }),
                  _PeriodStrip(
                    period: _period,
                    outlet: provider.selectedRestaurantLabel,
                    onPick: (v) {
                      setState(() => _period = v);
                      _reloadCurrentTab();
                    },
                    onOutlet: _showFilters,
                  ),
                  const SizedBox(height: 6),
                  if (_error != null)
                    _ReportsMessage(
                        icon: Icons.error_outline_rounded,
                        text: _error!,
                        actionLabel: 'Retry',
                        onAction: _tab == 'compare'
                            ? _loadCompare
                            : _loadPerformance)
                  else if (_tab == 'compare')
                    _buildCompare()
                  else
                    _buildPerformance(),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPerformance() {
    if (_loadingPerformance) return const _ReportsLoader();
    final sections = _performanceSections;
    if (sections.isEmpty)
      return const _ReportsMessage(
          icon: Icons.insert_chart_outlined_rounded,
          text: 'Reports will appear after real orders are available.');
    final selected = sections.firstWhere((s) => s.title == _section,
        orElse: () => sections.first);
    final rest = selected.metrics.length > 1
        ? selected.metrics.sublist(1)
        : const <_ReportMetric>[];

    final hourly = _list(_performance['hourly_data'])
        .map(_map)
        .map((m) => (
              hour: (parseNullableDouble(m['hour']) ?? 0).toInt(),
              orders: (parseNullableDouble(m['orders']) ?? 0),
            ))
        .toList();
    final delivered = _num('delivered_orders');
    final cancelled = _num('cancelled_orders');
    final totalOrders = _num('total_orders');
    final showCharts = selected.title == 'Sales';

    return Padding(
        padding: const EdgeInsets.fromLTRB(14, 6, 14, 24),
        child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _SectionChips(
                  sections: sections.map((s) => s.title).toList(),
                  selected: selected.title,
                  onSelected: (v) => setState(() => _section = v)),
              const SizedBox(height: 10),
              if (selected.metrics.isNotEmpty)
                _ReportHero(
                    label: selected.metrics.first.title,
                    value: selected.metrics.first.value,
                    caption: 'for $_periodLabel'),
              if (showCharts && totalOrders > 0) ...[
                const SizedBox(height: 10),
                _OrderOutcomeCard(
                  delivered: delivered,
                  cancelled: cancelled,
                  total: totalOrders,
                ),
              ],
              if (showCharts && hourly.any((h) => h.orders > 0)) ...[
                const SizedBox(height: 10),
                _HourlyOrdersCard(data: hourly),
              ],
              if (rest.isNotEmpty) ...[
                const SizedBox(height: 10),
                _MetricBoard(metrics: rest),
              ],
              if (selected.rows.isNotEmpty) ...[
                const SizedBox(height: 10),
                _LeaderboardCard(
                    title: selected.rowTitle, rows: selected.rows)
              ],
            ]));
  }

  Widget _buildCompare() {
    if (_loadingCompare) return const _ReportsLoader();
    final metrics = _list(_compare['metrics'])
        .map((m) => _CompareMetric.fromMap(_map(m), _formatValue))
        .toList();
    if (metrics.isEmpty)
      return const _ReportsMessage(
          icon: Icons.compare_arrows_rounded,
          text:
              'Comparison will appear when delivery-zone restaurants have data.');
    final restaurant = _map(_compare['restaurant']);
    final zone = _map(_compare['delivery_zone']);
    final peerCount = parseIntValue(_compare['peer_restaurant_count']);
    final needs = metrics.where((m) => !m.isBetter).toList();
    final good = metrics.where((m) => m.isBetter).toList();
    return Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 24),
        child:
            Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Container(
              padding: const EdgeInsets.all(14),
              decoration: _cardDecoration(),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Compare your performance',
                        style: TextStyle(
                            fontSize: 16, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 6),
                    Text(
                        '${restaurant['name'] ?? 'Selected outlet'} vs ${zone['name'] ?? restaurant['city'] ?? 'delivery zone'} average',
                        style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    Text(
                        '$peerCount peer restaurants compared. Your own restaurants are excluded.',
                        style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w600)),
                  ])),
          if (needs.isNotEmpty) ...[
            const SizedBox(height: 12),
            _CompareBoard(
                title: 'Needs improvement', good: false, metrics: needs),
          ],
          if (good.isNotEmpty) ...[
            const SizedBox(height: 12),
            _CompareBoard(title: 'Doing great', good: true, metrics: good),
          ],
        ]));
  }

  List<_PerformanceSection> get _performanceSections {
    final sections = <_PerformanceSection>[
      _PerformanceSection('Sales', [
        _metric('Net Sales', _num('total_revenue'), 'currency'),
        _metric('Total Orders', _num('total_orders'), 'number'),
        _metric('Delivered Orders', _num('delivered_orders'), 'number'),
        _metric('Average Order Value', _num('avg_order_value'), 'currency'),
        _metric('Cancelled Orders', _num('cancelled_orders'), 'number'),
        _metric('Cancellation Rate', _num('cancellation_rate'), 'percent'),
      ]),
    ];
    final promo = _map(_performance['promotion_performance']);
    if (promo.isNotEmpty) {
      sections.add(_PerformanceSection(
          'Growth',
          [
            _metricFrom(
                promo, 'Active Promotions', 'active_promotions', 'number'),
            _metricFrom(
                promo, 'Total Promotions', 'total_promotions', 'number'),
            _metricFrom(promo, 'Promo Orders', 'coupon_orders', 'number'),
            _metricFrom(promo, 'Discount Given', 'discount_given', 'currency'),
            _metricFrom(promo, 'Average Discount', 'avg_discount', 'currency'),
          ],
          rows: _promoRows(promo),
          rowTitle: 'Top promotions'));
    }
    if (_adPerformance.isNotEmpty) {
      sections.add(_PerformanceSection(
          'Ads',
          [
            _metricFrom(
                _adPerformance, 'Active Campaigns', 'active_campaigns', 'number'),
            _metricFrom(_adPerformance, 'Impressions', 'impressions', 'number'),
            _metricFrom(_adPerformance, 'Clicks', 'clicks', 'number'),
            _metricFrom(_adPerformance, 'CTR', 'ctr', 'percent'),
            _metricFrom(_adPerformance, 'Ad Spend', 'spend', 'currency'),
            _metricFrom(_adPerformance, 'Avg. CPC', 'avg_cpc', 'currency'),
          ],
          rows: _adCampaignRows(_adPerformance),
          rowTitle: 'Top campaigns'));
    }
    final topItems = _list(_performance['top_items']);
    if (topItems.isNotEmpty) {
      sections.add(_PerformanceSection(
          'Menu',
          [
            _metric(
                'Top Item Revenue', _sumRows(topItems, 'revenue'), 'currency'),
            _metric('Top Item Orders', _sumRows(topItems, 'total_orders'),
                'number'),
          ],
          rows: topItems.take(8).map((item) {
            final row = _map(item);
            return _ReportRow(
                row['name']?.toString() ?? 'Menu item',
                '${_formatValue(parseNullableDouble(row['total_orders']) ?? 0, 'number')} orders',
                _formatValue(
                    parseNullableDouble(row['revenue']) ?? 0, 'currency'));
          }).toList(),
          rowTitle: 'Top selling items'));
    }
    final hourly = _list(_performance['hourly_data']);
    if (hourly.isNotEmpty) {
      final busiest =
          hourly.map(_map).fold<Map<String, dynamic>?>(null, (prev, cur) {
        if (prev == null) return cur;
        return (parseNullableDouble(cur['orders']) ?? 0) >
                (parseNullableDouble(prev['orders']) ?? 0)
            ? cur
            : prev;
      });
      sections.add(_PerformanceSection('Operations', [
        _metric(
            'Busiest Hour', parseNullableDouble(busiest?['hour']) ?? 0, 'hour'),
        _metric('Busiest Hour Orders',
            parseNullableDouble(busiest?['orders']) ?? 0, 'number'),
      ]));
    }
    return sections;
  }

  _ReportMetric _metric(String title, num value, String unit) =>
      _ReportMetric(title, _formatValue(value, unit));
  _ReportMetric _metricFrom(
          Map<String, dynamic> data, String title, String key, String unit) =>
      _metric(title, parseNullableDouble(data[key]) ?? 0, unit);
  double _num(String key) => parseNullableDouble(_performance[key]) ?? 0;
  double _sumRows(List<dynamic> rows, String key) => rows.fold<double>(
      0, (sum, item) => sum + (parseNullableDouble(_map(item)[key]) ?? 0));

  List<_ReportRow> _promoRows(Map<String, dynamic> promo) =>
      _list(promo['top_promos']).take(6).map((item) {
        final row = _map(item);
        return _ReportRow(
            row['title']?.toString() ?? 'Promotion',
            '${parseIntValue(row['usage_count'])} orders',
            _formatValue(
                parseNullableDouble(row['discount_given']) ?? 0, 'currency'));
      }).toList();

  List<_ReportRow> _adCampaignRows(Map<String, dynamic> adPerformance) =>
      _list(adPerformance['top_campaigns']).take(6).map((item) {
        final row = _map(item);
        return _ReportRow(
            row['name']?.toString() ?? 'Campaign',
            '${parseIntValue(row['clicks'])} clicks',
            _formatValue(parseNullableDouble(row['spend']) ?? 0, 'currency'));
      }).toList();

  String _formatValue(num value, String unit) {
    switch (unit) {
      case 'currency':
        return formatCurrency(context, value);
      case 'percent':
        return '${_trim(value)}%';
      case 'minutes':
        return '${_trim(value)} min';
      case 'rating':
        return '${_trim(value)}/5';
      case 'hour':
        return '${value.toInt().toString().padLeft(2, '0')}:00';
      default:
        return _trim(value);
    }
  }

  String _trim(num value) {
    final fixed =
        value.toStringAsFixed(value.truncateToDouble() == value ? 0 : 2);
    return fixed.endsWith('.00') ? fixed.substring(0, fixed.length - 3) : fixed;
  }

  String get _periodLabel => switch (_period) {
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_week' => 'Last week',
        'month' => 'This month',
        'year' => 'Last 365 days',
        _ => 'Last 7 days',
      };
  void _showFilters() {
    final provider = context.read<RestaurantProvider>();
    var sheetPeriod = _period;
    var sheetCity = _selectedCity;
    var sheetRestaurantId = provider.selectedRestaurantId;
    final restaurants = provider.restaurants;
    final cities = restaurants
        .map((r) => r['city']?.toString().trim())
        .whereType<String>()
        .where((c) => c.isNotEmpty)
        .toSet()
        .toList()
      ..sort();
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) {
          final filteredRestaurants = sheetCity == null
              ? restaurants
              : restaurants
                  .where((r) => r['city']?.toString() == sheetCity)
                  .toList();

          Widget chip(String label, bool sel, VoidCallback onTap) =>
              GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: onTap,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                  decoration: BoxDecoration(
                    color: sel
                        ? foodflow.orange
                        : (foodflow.isDark
                            ? foodflow.elevatedSurface
                            : Colors.white),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                        color: sel ? foodflow.orange : foodflow.line),
                  ),
                  child: Text(label,
                      style: TextStyle(
                        color: sel ? Colors.white : foodflow.ink,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                      )),
                ),
              );

          Widget label(String t) => Padding(
                padding: const EdgeInsets.fromLTRB(2, 18, 2, 8),
                child: Text(t.toUpperCase(),
                    style: TextStyle(
                      color: foodflow.muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1.2,
                    )),
              );

          return Padding(
            padding: EdgeInsets.only(
                bottom: MediaQuery.of(context).viewInsets.bottom),
            child: Container(
              constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(context).size.height * 0.82),
              decoration: BoxDecoration(
                color: foodflow.surfaceColor,
                borderRadius:
                    const BorderRadius.vertical(top: Radius.circular(24)),
                border: Border.all(color: foodflow.glassBorder),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const SizedBox(height: 10),
                  Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: foodflow.faint,
                      borderRadius: BorderRadius.circular(99),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(18, 14, 8, 6),
                    child: Row(children: [
                      Expanded(
                        child: Text('Filters',
                            style: TextStyle(
                              color: foodflow.ink,
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            )),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: Icon(Icons.close_rounded, color: foodflow.muted),
                      ),
                    ]),
                  ),
                  Flexible(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          label('Period'),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              for (final e in _periodOptions.entries)
                                chip(e.value, sheetPeriod == e.key,
                                    () => setSheetState(
                                        () => sheetPeriod = e.key)),
                            ],
                          ),
                          if (cities.isNotEmpty) ...[
                            label('City'),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                chip('All cities', sheetCity == null,
                                    () => setSheetState(() => sheetCity = null)),
                                for (final c in cities)
                                  chip(c, sheetCity == c, () {
                                    setSheetState(() {
                                      sheetCity = sheetCity == c ? null : c;
                                      if (sheetCity != null &&
                                          !filteredRestaurants.any((r) =>
                                              _id(r['id']) ==
                                              sheetRestaurantId)) {
                                        sheetRestaurantId = null;
                                      }
                                    });
                                  }),
                              ],
                            ),
                          ],
                          label('Outlet'),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              chip('All outlets', sheetRestaurantId == null,
                                  () => setSheetState(
                                      () => sheetRestaurantId = null)),
                              for (final r in filteredRestaurants)
                                chip(
                                  r['name']?.toString() ?? 'Outlet',
                                  sheetRestaurantId == _id(r['id']),
                                  () => setSheetState(() =>
                                      sheetRestaurantId = _id(r['id'])),
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                  SafeArea(
                    top: false,
                    minimum: const EdgeInsets.fromLTRB(16, 10, 16, 16),
                    child: Row(children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () async {
                            setState(() {
                              _period = 'week';
                              _selectedCity = null;
                            });
                            await provider.selectRestaurant(null);
                            if (!mounted) return;
                            Navigator.pop(context);
                            await _reloadCurrentTab();
                          },
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size.fromHeight(50),
                            side: BorderSide(color: foodflow.line),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          child: const Text('Reset'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        flex: 2,
                        child: FilledButton(
                          style: FilledButton.styleFrom(
                            backgroundColor: foodflow.orange,
                            minimumSize: const Size.fromHeight(50),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          onPressed: () async {
                            setState(() {
                              _period = sheetPeriod;
                              _selectedCity = sheetCity;
                            });
                            await provider.selectRestaurant(sheetRestaurantId);
                            if (!mounted) return;
                            Navigator.pop(context);
                            await _reloadCurrentTab();
                          },
                          child: const Text('Apply',
                              style: TextStyle(fontWeight: FontWeight.w900)),
                        ),
                      ),
                    ]),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _reloadCurrentTab() =>
      _tab == 'compare' ? _loadCompare() : _loadPerformance();
  static int? _id(dynamic value) =>
      value is int ? value : int.tryParse(value?.toString() ?? '');
  static Map<String, dynamic> _map(dynamic value) => value
          is Map<String, dynamic>
      ? value
      : (value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{});
  static List<dynamic> _list(dynamic value) => value is List ? value : const [];
  static const _periodOptions = {
    'today': 'Today',
    'yesterday': 'Yesterday',
    'week': 'This week',
    'last_week': 'Last week',
    'month': 'This month'
  };
}

class _PerformanceSection {
  const _PerformanceSection(this.title, this.metrics,
      {this.rows = const [], this.rowTitle = 'Details'});
  final String title;
  final List<_ReportMetric> metrics;
  final List<_ReportRow> rows;
  final String rowTitle;
}

class _ReportMetric {
  const _ReportMetric(this.title, this.value);
  final String title;
  final String value;
}

class _ReportRow {
  const _ReportRow(this.title, this.subtitle, this.trailing);
  final String title;
  final String subtitle;
  final String trailing;
}

class _CompareMetric {
  const _CompareMetric(
      {required this.label,
      required this.group,
      required this.you,
      required this.average,
      required this.isBetter});
  factory _CompareMetric.fromMap(
      Map<String, dynamic> data, String Function(num, String) formatter) {
    final unit = data['unit']?.toString() ?? 'number';
    return _CompareMetric(
        label: data['label']?.toString() ?? 'Metric',
        group: data['group']?.toString() ?? 'Performance',
        you: formatter(parseNullableDouble(data['you']) ?? 0, unit),
        average: formatter(
            parseNullableDouble(data['delivery_zone_average']) ?? 0, unit),
        isBetter: data['is_better'] == true);
  }
  final String label;
  final String group;
  final String you;
  final String average;
  final bool isBetter;
}

Color get _orange => foodflow.orange;
BoxDecoration _cardDecoration() => BoxDecoration(
    color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: foodflow.line));

class _ReportsHeader extends StatelessWidget {
  const _ReportsHeader({required this.selectedTab, required this.onTab});
  final String selectedTab;
  final ValueChanged<String> onTab;
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.fromLTRB(14, 6, 14, 10),
      child: Container(
        padding: const EdgeInsets.all(4),
        decoration: BoxDecoration(
          color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: foodflow.line),
        ),
        child: Row(children: [
          _TopTab(
              label: 'Your Performance',
              selected: selectedTab == 'performance',
              onTap: () => onTab('performance')),
          _TopTab(
              label: 'Compare',
              selected: selectedTab == 'compare',
              onTap: () => onTab('compare')),
        ]),
      ));
}

class _TopTab extends StatelessWidget {
  const _TopTab(
      {required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Expanded(
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(vertical: 11),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? foodflow.orange : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(label,
              style: TextStyle(
                  color: selected ? Colors.white : foodflow.muted,
                  fontSize: 13,
                  fontWeight: FontWeight.w900)),
        ),
      ));
}

class _ReportsFilterBar extends StatelessWidget {
  const _ReportsFilterBar(
      {required this.outletLabel,
      required this.periodLabel,
      required this.onFilter});
  final String outletLabel;
  final String periodLabel;
  final VoidCallback onFilter;
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.fromLTRB(14, 0, 14, 0),
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
        decoration: BoxDecoration(
          color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: foodflow.line),
        ),
        child: Row(children: [
          Icon(Icons.tune_rounded, size: 18, color: foodflow.muted),
          const SizedBox(width: 8),
          Expanded(
              child: Text('$outletLabel - $periodLabel',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                      color: foodflow.ink, fontWeight: FontWeight.w900))),
          TextButton(onPressed: onFilter, child: const Text('Filter')),
        ]),
      ));
}

class _ReportsRail extends StatelessWidget {
  const _ReportsRail(
      {required this.sections,
      required this.selected,
      required this.onSelected});
  final List<String> sections;
  final String selected;
  final ValueChanged<String> onSelected;
  @override
  Widget build(BuildContext context) => Container(
      width: 92,
      child: Column(
          children: sections
              .map((section) => InkWell(
                  onTap: () => onSelected(section),
                  child: Container(
                      height: 54,
                      alignment: Alignment.centerLeft,
                      padding: const EdgeInsets.only(left: 9),
                      decoration: BoxDecoration(
                          border: Border(
                              left: BorderSide(
                                  color: section == selected
                                      ? _orange
                                      : Colors.transparent,
                                  width: 4))),
                      child: Text(section,
                          style: TextStyle(
                              color: section == selected
                                  ? foodflow.orange
                                  : foodflow.ink,
                              fontSize: 12,
                              fontWeight: FontWeight.w800)))))
              .toList()));
}

class _ReportsNotice extends StatelessWidget {
  const _ReportsNotice({required this.text, required this.onRefresh});
  final String text;
  final VoidCallback onRefresh;
  @override
  Widget build(BuildContext context) => Container(
      padding: const EdgeInsets.all(12),
      decoration: _cardDecoration(),
      child: Row(children: [
        Expanded(
            child: Text(text,
                style: TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700))),
        TextButton.icon(
            onPressed: onRefresh,
            icon: const Icon(Icons.refresh_rounded, size: 16),
            label: const Text('Refresh'))
      ]));
}

class _ReportsCard extends StatelessWidget {
  const _ReportsCard({required this.title, required this.metrics});
  final String title;
  final List<_ReportMetric> metrics;
  @override
  Widget build(BuildContext context) => Container(
      padding: const EdgeInsets.all(14),
      decoration: _cardDecoration(),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title,
            style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900)),
        const SizedBox(height: 10),
        Divider(height: 1, color: FoodFlowTheme.line),
        const SizedBox(height: 12),
        GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: metrics.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 1.75,
                crossAxisSpacing: 10,
                mainAxisSpacing: 12),
            itemBuilder: (context, index) {
              final metric = metrics[index];
              return Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: foodflow.isDark
                      ? foodflow.surfaceColor
                      : foodflow.canvas,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: foodflow.line),
                ),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(metric.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                              color: foodflow.muted,
                              fontWeight: FontWeight.w700,
                              fontSize: 12)),
                      const SizedBox(height: 5),
                      Text(metric.value,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                              color: foodflow.ink,
                              fontSize: 19,
                              fontWeight: FontWeight.w900))
                    ]),
              );
            }),
      ]));
}

class _ReportsRowsCard extends StatelessWidget {
  const _ReportsRowsCard({required this.title, required this.rows});
  final String title;
  final List<_ReportRow> rows;
  @override
  Widget build(BuildContext context) => Container(
      padding: const EdgeInsets.all(14),
      decoration: _cardDecoration(),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
        const SizedBox(height: 8),
        ...rows.map((row) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Row(children: [
              Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                    Text(row.title,
                        style: const TextStyle(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 2),
                    Text(row.subtitle,
                        style: TextStyle(
                            color: FoodFlowTheme.muted, fontSize: 12))
                  ])),
              Text(row.trailing,
                  style: const TextStyle(fontWeight: FontWeight.w900))
            ]))),
      ]));
}

class _CompareSection extends StatelessWidget {
  const _CompareSection(
      {required this.title, required this.good, required this.metrics});
  final String title;
  final bool good;
  final List<_CompareMetric> metrics;
  @override
  Widget build(BuildContext context) {
    final accent = good ? const Color(0xFF0F9D58) : const Color(0xFFE94970);
    final bg = good ? const Color(0xFFEAF8F0) : const Color(0xFFFFEDF2);
    return Container(
        padding: const EdgeInsets.all(14),
        decoration: _cardDecoration(),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Icon(
                good ? Icons.check_circle_rounded : Icons.priority_high_rounded,
                color: accent,
                size: 20),
            const SizedBox(width: 8),
            Text(title,
                style: TextStyle(
                    color: accent, fontSize: 16, fontWeight: FontWeight.w900))
          ]),
          const SizedBox(height: 10),
          ...metrics.map((metric) => Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                  color: bg,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: accent.withOpacity(0.26))),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(metric.group.toUpperCase(),
                        style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 10,
                            fontWeight: FontWeight.w900)),
                    const SizedBox(height: 6),
                    Text(metric.label,
                        style: const TextStyle(fontWeight: FontWeight.w900)),
                    const SizedBox(height: 10),
                    Row(children: [
                      Expanded(
                          child: _CompareValue(
                              label: 'YOU', value: metric.you, color: accent)),
                      Expanded(
                          child: _CompareValue(
                              label: 'ZONE AVG',
                              value: metric.average,
                              color: FoodFlowTheme.ink))
                    ]),
                  ]))),
        ]));
  }
}

class _CompareValue extends StatelessWidget {
  const _CompareValue(
      {required this.label, required this.value, required this.color});
  final String label;
  final String value;
  final Color color;
  @override
  Widget build(BuildContext context) =>
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label,
            style: TextStyle(
                color: FoodFlowTheme.muted,
                fontSize: 10,
                fontWeight: FontWeight.w900)),
        const SizedBox(height: 3),
        Text(value, style: TextStyle(color: color, fontWeight: FontWeight.w900))
      ]);
}

class _FilterTab extends StatelessWidget {
  const _FilterTab(
      {required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
      onTap: onTap,
      child: Container(
          height: 58,
          alignment: Alignment.centerLeft,
          padding: const EdgeInsets.only(left: 9),
          decoration: BoxDecoration(
              border: Border(
                  left: BorderSide(
                      color: selected ? _orange : Colors.transparent,
                      width: 4))),
          child: Text(label,
              style: TextStyle(
                  color: selected ? _orange : FoodFlowTheme.ink,
                  fontWeight: FontWeight.w800))));
}

class _ReportsLoader extends StatelessWidget {
  const _ReportsLoader();
  @override
  Widget build(BuildContext context) => const Padding(
      padding: EdgeInsets.only(top: 150),
      child: Center(child: CircularProgressIndicator()));
}

class _ReportsMessage extends StatelessWidget {
  const _ReportsMessage(
      {required this.icon,
      required this.text,
      this.actionLabel,
      this.onAction});
  final IconData icon;
  final String text;
  final String? actionLabel;
  final VoidCallback? onAction;
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.only(top: 160, left: 24, right: 24),
      child: Column(children: [
        Icon(icon, size: 54, color: FoodFlowTheme.muted),
        const SizedBox(height: 14),
        Text(text,
            textAlign: TextAlign.center,
            style: TextStyle(
                color: FoodFlowTheme.muted, fontWeight: FontWeight.w800)),
        if (actionLabel != null && onAction != null) ...[
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onAction, child: Text(actionLabel!))
        ],
      ]));
}

class _ReportsAccessDenied extends StatelessWidget {
  const _ReportsAccessDenied();
  @override
  Widget build(BuildContext context) => Scaffold(
      backgroundColor: foodflow.canvas,
      body: Center(
          child: Padding(
              padding: EdgeInsets.all(24),
              child: Text(
                  'Reports access is not enabled for this staff account.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                      color: FoodFlowTheme.muted,
                      fontWeight: FontWeight.w800)))));
}


double _firstNum(String s) {
  final m = RegExp(r'-?[\d.]+').firstMatch(s.replaceAll(',', ''));
  return m == null ? 0 : double.tryParse(m.group(0)!) ?? 0;
}

/// Period selector + outlet label as a horizontal strip.
class _PeriodStrip extends StatelessWidget {
  const _PeriodStrip({
    required this.period,
    required this.outlet,
    required this.onPick,
    required this.onOutlet,
  });
  final String period;
  final String outlet;
  final ValueChanged<String> onPick;
  final VoidCallback onOutlet;

  static const _opts = {
    'today': 'Today',
    'yesterday': 'Yesterday',
    'week': 'This week',
    'last_week': 'Last week',
    'month': 'This month',
  };

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 2, 14, 6),
          child: GestureDetector(
            onTap: onOutlet,
            child: Row(
              children: [
                Icon(Icons.storefront_outlined, size: 15, color: foodflow.muted),
                const SizedBox(width: 6),
                Flexible(
                  child: Text(outlet,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: foodflow.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      )),
                ),
                Icon(Icons.expand_more_rounded, size: 16, color: foodflow.muted),
              ],
            ),
          ),
        ),
        SizedBox(
          height: 38,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            itemCount: _opts.length,
            separatorBuilder: (_, __) => const SizedBox(width: 8),
            itemBuilder: (context, i) {
              final e = _opts.entries.elementAt(i);
              final sel = e.key == period;
              return GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => onPick(e.key),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 160),
                  alignment: Alignment.center,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: sel
                        ? foodflow.orange
                        : (foodflow.isDark
                            ? foodflow.elevatedSurface
                            : Colors.white),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                        color: sel ? foodflow.orange : foodflow.line),
                  ),
                  child: Text(e.value,
                      style: TextStyle(
                        color: sel ? Colors.white : foodflow.ink,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800,
                      )),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _SectionChips extends StatelessWidget {
  const _SectionChips({
    required this.sections,
    required this.selected,
    required this.onSelected,
  });
  final List<String> sections;
  final String selected;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 34,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: sections.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, i) {
          final s = sections[i];
          final sel = s == selected;
          return GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: () => onSelected(s),
            child: Container(
              alignment: Alignment.center,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              decoration: BoxDecoration(
                color: sel
                    ? foodflow.orange.withOpacity(0.14)
                    : Colors.transparent,
                borderRadius: BorderRadius.circular(11),
                border: Border.all(
                    color: sel ? foodflow.orange : foodflow.line),
              ),
              child: Text(s,
                  style: TextStyle(
                    color: sel ? foodflow.orange : foodflow.muted,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w900,
                  )),
            ),
          );
        },
      ),
    );
  }
}

/// The one number that matters, oversized.
class _ReportHero extends StatelessWidget {
  const _ReportHero({
    required this.label,
    required this.value,
    required this.caption,
  });
  final String label;
  final String value;
  final String caption;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [foodflow.orange, foodflow.orangeDark],
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: foodflow.orange.withOpacity(0.3),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(),
              style: TextStyle(
                color: Colors.white.withOpacity(0.85),
                fontSize: 11.5,
                fontWeight: FontWeight.w900,
                letterSpacing: 1,
              )),
          const SizedBox(height: 8),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(value,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 40,
                  fontWeight: FontWeight.w900,
                  height: 1,
                )),
          ),
          const SizedBox(height: 6),
          Text(caption,
              style: TextStyle(
                color: Colors.white.withOpacity(0.8),
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
              )),
        ],
      ),
    );
  }
}

class _MetricBoard extends StatelessWidget {
  const _MetricBoard({required this.metrics});
  final List<_ReportMetric> metrics;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[];
    for (var i = 0; i < metrics.length; i += 2) {
      if (i > 0) rows.add(const SizedBox(height: 10));
      rows.add(IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(child: _tile(metrics[i])),
            const SizedBox(width: 10),
            Expanded(
              child: i + 1 < metrics.length
                  ? _tile(metrics[i + 1])
                  : const SizedBox.shrink(),
            ),
          ],
        ),
      ));
    }
    return Column(children: rows);
  }

  Widget _tile(_ReportMetric m) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
      decoration: BoxDecoration(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: foodflow.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(m.title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: foodflow.muted,
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
              )),
          const SizedBox(height: 4),
          Text(m.value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: foodflow.ink,
                fontSize: 18,
                fontWeight: FontWeight.w900,
              )),
        ],
      ),
    );
  }
}

/// Ranked leaderboard with proportional bars.
class _LeaderboardCard extends StatelessWidget {
  const _LeaderboardCard({required this.title, required this.rows});
  final String title;
  final List<_ReportRow> rows;

  @override
  Widget build(BuildContext context) {
    final maxV = rows
        .map((r) => _firstNum(r.trailing))
        .fold<double>(0, (a, b) => b > a ? b : a);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: foodflow.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title,
              style: TextStyle(
                color: foodflow.ink,
                fontSize: 15,
                fontWeight: FontWeight.w900,
              )),
          const SizedBox(height: 12),
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0) const SizedBox(height: 12),
            Row(
              children: [
                SizedBox(
                  width: 20,
                  child: Text('${i + 1}',
                      style: TextStyle(
                        color: foodflow.faint,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      )),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(rows[i].title,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: foodflow.ink,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                )),
                          ),
                          Text(rows[i].trailing,
                              style: TextStyle(
                                color: foodflow.orange,
                                fontSize: 12.5,
                                fontWeight: FontWeight.w900,
                              )),
                        ],
                      ),
                      const SizedBox(height: 5),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(99),
                        child: LinearProgressIndicator(
                          value: maxV <= 0
                              ? 0
                              : (_firstNum(rows[i].trailing) / maxV)
                                  .clamp(0.02, 1.0),
                          minHeight: 6,
                          backgroundColor: foodflow.line,
                          valueColor:
                              AlwaysStoppedAnimation(foodflow.orange),
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(rows[i].subtitle,
                          style: TextStyle(
                            color: foodflow.muted,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          )),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// Diverging you-vs-zone bars.
class _CompareBoard extends StatelessWidget {
  const _CompareBoard({
    required this.title,
    required this.good,
    required this.metrics,
  });
  final String title;
  final bool good;
  final List<_CompareMetric> metrics;

  @override
  Widget build(BuildContext context) {
    final accent = good ? foodflow.success : foodflow.danger;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: foodflow.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                  good
                      ? Icons.trending_up_rounded
                      : Icons.trending_down_rounded,
                  color: accent,
                  size: 18),
              const SizedBox(width: 8),
              Text(title,
                  style: TextStyle(
                    color: accent,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  )),
            ],
          ),
          const SizedBox(height: 14),
          for (var i = 0; i < metrics.length; i++) ...[
            if (i > 0) const SizedBox(height: 16),
            _divergingRow(metrics[i], accent),
          ],
        ],
      ),
    );
  }

  Widget _divergingRow(_CompareMetric m, Color accent) {
    final you = _firstNum(m.you);
    final avg = _firstNum(m.average);
    final maxV = [you, avg, 1.0].reduce((a, b) => a > b ? a : b);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(m.label,
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 13,
              fontWeight: FontWeight.w900,
            )),
        const SizedBox(height: 8),
        _bar('You', m.you, you / maxV, accent),
        const SizedBox(height: 6),
        _bar('Zone avg', m.average, avg / maxV, foodflow.faint),
      ],
    );
  }

  Widget _bar(String label, String value, double frac, Color color) {
    return Row(
      children: [
        SizedBox(
          width: 62,
          child: Text(label,
              style: TextStyle(
                color: foodflow.muted,
                fontSize: 10.5,
                fontWeight: FontWeight.w800,
              )),
        ),
        Expanded(
          child: Stack(
            children: [
              Container(
                height: 16,
                decoration: BoxDecoration(
                  color: foodflow.line,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              FractionallySizedBox(
                widthFactor: frac.clamp(0.02, 1.0),
                child: Container(
                  height: 16,
                  decoration: BoxDecoration(
                    color: color,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        SizedBox(
          width: 62,
          child: Text(value,
              textAlign: TextAlign.right,
              style: TextStyle(
                color: foodflow.ink,
                fontSize: 11.5,
                fontWeight: FontWeight.w900,
              )),
        ),
      ],
    );
  }
}

/// Delivered vs cancelled donut.
class _OrderOutcomeCard extends StatelessWidget {
  const _OrderOutcomeCard({
    required this.delivered,
    required this.cancelled,
    required this.total,
  });
  final double delivered;
  final double cancelled;
  final double total;

  @override
  Widget build(BuildContext context) {
    final other = (total - delivered - cancelled).clamp(0, total).toDouble();
    final denom = delivered + cancelled + other;
    final segs = <(double, Color, String)>[
      (delivered, foodflow.success, 'Delivered'),
      (cancelled, foodflow.danger, 'Cancelled'),
      if (other > 0) (other, foodflow.faint, 'In progress'),
    ];
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: foodflow.line),
      ),
      child: Row(
        children: [
          SizedBox(
            width: 96,
            height: 96,
            child: CustomPaint(
              painter: _DonutPainter(
                segments: segs.map((s) => (s.$1, s.$2)).toList(),
                track: foodflow.line,
              ),
              child: Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      denom <= 0
                          ? '0%'
                          : '${(delivered * 100 / denom).round()}%',
                      style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text('delivered',
                        style: TextStyle(
                          color: foodflow.muted,
                          fontSize: 9.5,
                          fontWeight: FontWeight.w700,
                        )),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(width: 18),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Order outcomes',
                    style: TextStyle(
                      color: foodflow.ink,
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                    )),
                const SizedBox(height: 10),
                for (final seg in segs)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Row(
                      children: [
                        Container(
                          width: 10,
                          height: 10,
                          decoration: BoxDecoration(
                            color: seg.$2,
                            borderRadius: BorderRadius.circular(3),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(seg.$3,
                              style: TextStyle(
                                color: foodflow.muted,
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              )),
                        ),
                        Text('${seg.$1.toInt()}',
                            style: TextStyle(
                              color: foodflow.ink,
                              fontSize: 12.5,
                              fontWeight: FontWeight.w900,
                            )),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DonutPainter extends CustomPainter {
  _DonutPainter({required this.segments, required this.track});
  final List<(double, Color)> segments;
  final Color track;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final center = rect.center;
    final radius = size.shortestSide / 2 - 7;
    const stroke = 12.0;
    final total = segments.fold<double>(0, (a, b) => a + b.$1);
    final bg = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..color = track;
    canvas.drawCircle(center, radius, bg);
    if (total <= 0) return;
    var start = -1.5708;
    for (final seg in segments) {
      if (seg.$1 <= 0) continue;
      final sweep = seg.$1 / total * 6.2832;
      final paint = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = stroke
        ..strokeCap = StrokeCap.butt
        ..color = seg.$2;
      canvas.drawArc(
        Rect.fromCircle(center: center, radius: radius),
        start,
        sweep - 0.04,
        false,
        paint,
      );
      start += sweep;
    }
  }

  @override
  bool shouldRepaint(covariant _DonutPainter old) => old.segments != segments;
}

/// Orders-by-hour bar chart.
class _HourlyOrdersCard extends StatelessWidget {
  const _HourlyOrdersCard({required this.data});
  final List<({int hour, double orders})> data;

  @override
  Widget build(BuildContext context) {
    final peak = data.reduce((a, b) => b.orders > a.orders ? b : a);
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
      decoration: BoxDecoration(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: foodflow.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text('Orders by hour',
                  style: TextStyle(
                    color: foodflow.ink,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  )),
              const Spacer(),
              Text(
                'Peak ${peak.hour.toString().padLeft(2, '0')}:00',
                style: TextStyle(
                  color: foodflow.orange,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 92,
            child: CustomPaint(
              size: Size.infinite,
              painter: _BarsPainter(
                data: data,
                bar: foodflow.orange,
                dim: foodflow.line,
                label: foodflow.faint,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _BarsPainter extends CustomPainter {
  _BarsPainter({
    required this.data,
    required this.bar,
    required this.dim,
    required this.label,
  });
  final List<({int hour, double orders})> data;
  final Color bar;
  final Color dim;
  final Color label;

  @override
  void paint(Canvas canvas, Size size) {
    if (data.isEmpty) return;
    const axis = 16.0;
    final chartH = size.height - axis;
    final maxV = data.fold<double>(1, (a, b) => b.orders > a ? b.orders : a);
    const gap = 3.0;
    final bw = (size.width - gap * (data.length - 1)) / data.length;
    final tp = TextPainter(textDirection: TextDirection.ltr);

    for (var i = 0; i < data.length; i++) {
      final d = data[i];
      final x = i * (bw + gap);
      final h = (d.orders / maxV) * (chartH - 4);
      final r = RRect.fromRectAndCorners(
        Rect.fromLTWH(x, chartH - h, bw, h < 2 ? 2 : h),
        topLeft: const Radius.circular(3),
        topRight: const Radius.circular(3),
      );
      canvas.drawRRect(r, Paint()..color = d.orders > 0 ? bar : dim);
      if (data.length <= 12 || i % 3 == 0) {
        tp.text = TextSpan(
          text: d.hour.toString().padLeft(2, '0'),
          style: TextStyle(
            color: label,
            fontSize: 8,
            fontWeight: FontWeight.w700,
          ),
        );
        tp.layout();
        tp.paint(canvas, Offset(x + bw / 2 - tp.width / 2, chartH + 3));
      }
    }
  }

  @override
  bool shouldRepaint(covariant _BarsPainter old) => old.data != data;
}
