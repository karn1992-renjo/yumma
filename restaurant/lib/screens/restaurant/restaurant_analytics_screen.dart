import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../providers/restaurant_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
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
  Map<String, dynamic> _compare = {};
  bool _loadingPerformance = true;
  bool _loadingCompare = false;
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
    });
  }

  Future<void> _loadPerformance() async {
    setState(() {
      _loadingPerformance = true;
      _error = null;
    });
    try {
      final response = await _api.get(ApiConstants.restaurantAnalytics,
          queryParams: _queryParams());
      if (!mounted) return;
      if (response['success'] == true && response['data'] is Map) {
        setState(() {
          _performance = Map<String, dynamic>.from(response['data']);
          final sections = _performanceSections;
          if (!sections.any((s) => s.title == _section))
            _section = sections.isEmpty ? 'Sales' : sections.first.title;
        });
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
      backgroundColor: const Color(0xFFF2F2F6),
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          color: _orange,
          onRefresh: _tab == 'compare' ? _loadCompare : _loadPerformance,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.zero,
            children: [
              _ReportsHeader(
                  selectedTab: _tab,
                  onTab: (value) async {
                    setState(() => _tab = value);
                    if (value == 'compare' && _compare.isEmpty)
                      await _loadCompare();
                  }),
              _ReportsFilterBar(
                  outletLabel: provider.selectedRestaurantLabel,
                  periodLabel: _periodLabel,
                  onFilter: _showFilters),
              if (_error != null)
                _ReportsMessage(
                    icon: Icons.error_outline_rounded,
                    text: _error!,
                    actionLabel: 'Retry',
                    onAction:
                        _tab == 'compare' ? _loadCompare : _loadPerformance)
              else if (_tab == 'compare')
                _buildCompare()
              else
                _buildPerformance(),
            ],
          ),
        ),
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
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      _ReportsRail(
          sections: sections.map((s) => s.title).toList(),
          selected: selected.title,
          onSelected: (v) => setState(() => _section = v)),
      Expanded(
          child: Padding(
              padding: const EdgeInsets.fromLTRB(8, 8, 10, 24),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _ReportsNotice(
                        text: 'Showing real data for $_periodLabel.',
                        onRefresh: _loadPerformance),
                    const SizedBox(height: 8),
                    _ReportsCard(
                        title: selected.title, metrics: selected.metrics),
                    if (selected.rows.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      _ReportsRowsCard(
                          title: selected.rowTitle, rows: selected.rows)
                    ],
                  ]))),
    ]);
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
                        style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    Text(
                        '$peerCount peer restaurants compared. Your own restaurants are excluded.',
                        style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w600)),
                  ])),
          if (needs.isNotEmpty) ...[
            const SizedBox(height: 10),
            _CompareSection(
                title: 'Needs Improvement', good: false, metrics: needs)
          ],
          if (good.isNotEmpty) ...[
            const SizedBox(height: 10),
            _CompareSection(title: 'Doing Great', good: true, metrics: good)
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
    var activeTab = 'Date';
    showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (context) =>
            StatefulBuilder(builder: (context, setSheetState) {
              final filteredRestaurants = sheetCity == null
                  ? restaurants
                  : restaurants
                      .where((r) => r['city']?.toString() == sheetCity)
                      .toList();
              return FractionallySizedBox(
                  heightFactor: 0.72,
                  child: Container(
                      decoration: const BoxDecoration(
                          color: Colors.white,
                          borderRadius:
                              BorderRadius.vertical(top: Radius.circular(20))),
                      child: Column(children: [
                        Padding(
                            padding: const EdgeInsets.fromLTRB(18, 14, 10, 12),
                            child: Row(children: [
                              const Expanded(
                                  child: Text('Filters',
                                      style: TextStyle(
                                          fontSize: 24,
                                          fontWeight: FontWeight.w900))),
                              IconButton(
                                  onPressed: () => Navigator.pop(context),
                                  icon: const Icon(Icons.close_rounded)),
                            ])),
                        const Divider(height: 1, color: FoodFlowTheme.line),
                        Expanded(
                            child: Row(children: [
                          SizedBox(
                              width: 94,
                              child: Column(
                                  children: ['Date', 'City', 'Outlet']
                                      .map((tab) => _FilterTab(
                                          label: tab,
                                          selected: activeTab == tab,
                                          onTap: () => setSheetState(
                                              () => activeTab = tab)))
                                      .toList())),
                          const VerticalDivider(
                              width: 1, color: FoodFlowTheme.line),
                          Expanded(
                              child: ListView(
                                  padding:
                                      const EdgeInsets.fromLTRB(18, 16, 18, 20),
                                  children: [
                                if (activeTab == 'Date')
                                  ..._periodOptions.entries.map((e) =>
                                      RadioListTile<String>(
                                          dense: true,
                                          value: e.key,
                                          groupValue: sheetPeriod,
                                          activeColor: _orange,
                                          title: Text(e.value),
                                          onChanged: (v) => setSheetState(() =>
                                              sheetPeriod = v ?? 'week'))),
                                if (activeTab == 'City') ...[
                                  CheckboxListTile(
                                      dense: true,
                                      value: sheetCity == null,
                                      activeColor: _orange,
                                      title: const Text('All Cities'),
                                      onChanged: (_) => setSheetState(
                                          () => sheetCity = null)),
                                  ...cities.map((city) => CheckboxListTile(
                                      dense: true,
                                      value: sheetCity == city,
                                      activeColor: _orange,
                                      title: Text(city),
                                      onChanged: (_) => setSheetState(() {
                                            sheetCity =
                                                sheetCity == city ? null : city;
                                            if (sheetCity != null &&
                                                !filteredRestaurants.any((r) =>
                                                    _id(r['id']) ==
                                                    sheetRestaurantId))
                                              sheetRestaurantId = null;
                                          }))),
                                ],
                                if (activeTab == 'Outlet') ...[
                                  RadioListTile<int?>(
                                      dense: true,
                                      value: null,
                                      groupValue: sheetRestaurantId,
                                      activeColor: _orange,
                                      title:
                                          const Text('All accessible outlets'),
                                      onChanged: (v) => setSheetState(
                                          () => sheetRestaurantId = v)),
                                  ...filteredRestaurants.map((restaurant) {
                                    final id = _id(restaurant['id']);
                                    final subtitle = [
                                      restaurant['city']?.toString(),
                                      restaurant['area']?.toString()
                                    ]
                                        .whereType<String>()
                                        .where((v) => v.isNotEmpty)
                                        .join(', ');
                                    return RadioListTile<int?>(
                                        dense: true,
                                        value: id,
                                        groupValue: sheetRestaurantId,
                                        activeColor: _orange,
                                        title: Text(
                                            restaurant['name']?.toString() ??
                                                'Outlet'),
                                        subtitle: subtitle.isEmpty
                                            ? null
                                            : Text(subtitle),
                                        onChanged: (v) => setSheetState(
                                            () => sheetRestaurantId = v));
                                  }),
                                ],
                              ])),
                        ])),
                        SafeArea(
                            top: false,
                            minimum: const EdgeInsets.fromLTRB(16, 12, 16, 18),
                            child: Row(children: [
                              Expanded(
                                  child: TextButton(
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
                                      child: const Text('Clear Filter'))),
                              const SizedBox(width: 12),
                              Expanded(
                                  flex: 2,
                                  child: FilledButton(
                                      style: FilledButton.styleFrom(
                                          backgroundColor: _orange,
                                          minimumSize:
                                              const Size.fromHeight(52),
                                          shape: RoundedRectangleBorder(
                                              borderRadius:
                                                  BorderRadius.circular(12))),
                                      onPressed: () async {
                                        setState(() {
                                          _period = sheetPeriod;
                                          _selectedCity = sheetCity;
                                        });
                                        await provider.selectRestaurant(
                                            sheetRestaurantId);
                                        if (!mounted) return;
                                        Navigator.pop(context);
                                        await _reloadCurrentTab();
                                      },
                                      child: const Text('Apply',
                                          style: TextStyle(
                                              fontWeight: FontWeight.w900)))),
                            ])),
                      ])));
            }));
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

const _orange = Color(0xFFFF5200);
BoxDecoration _cardDecoration() => BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(10),
    border: Border.all(color: FoodFlowTheme.line));

class _ReportsHeader extends StatelessWidget {
  const _ReportsHeader({required this.selectedTab, required this.onTab});
  final String selectedTab;
  final ValueChanged<String> onTab;
  @override
  Widget build(BuildContext context) => Container(
      color: Colors.white,
      child: Column(children: [
        Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 4),
            child: Row(children: const [
              Expanded(
                  child: Text('Business Reports',
                      style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                          color: FoodFlowTheme.ink)))
            ])),
        Row(children: [
          _TopTab(
              label: 'Your Performance',
              selected: selectedTab == 'performance',
              onTap: () => onTab('performance')),
          _TopTab(
              label: 'Compare',
              selected: selectedTab == 'compare',
              onTap: () => onTab('compare'))
        ]),
      ]));
}

class _TopTab extends StatelessWidget {
  const _TopTab(
      {required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Expanded(
      child: InkWell(
          onTap: onTap,
          child: Column(children: [
            Padding(
                padding: const EdgeInsets.symmetric(vertical: 13),
                child: Text(label,
                    style: TextStyle(
                        color:
                            selected ? FoodFlowTheme.ink : FoodFlowTheme.muted,
                        fontWeight: FontWeight.w900))),
            AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                height: 3,
                margin: const EdgeInsets.symmetric(horizontal: 20),
                decoration: BoxDecoration(
                    color: selected ? _orange : Colors.transparent,
                    borderRadius: BorderRadius.circular(4))),
          ])));
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
  Widget build(BuildContext context) => Container(
      padding: const EdgeInsets.fromLTRB(12, 11, 12, 11),
      decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: FoodFlowTheme.line))),
      child: Row(children: [
        const Icon(Icons.tune_rounded, size: 20),
        const SizedBox(width: 8),
        Expanded(
            child: Text('$outletLabel - $periodLabel',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w900))),
        TextButton(onPressed: onFilter, child: const Text('Filter')),
      ]));
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
      width: 86,
      color: Colors.white,
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
                                  ? _orange
                                  : FoodFlowTheme.ink,
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
                style: const TextStyle(
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
        const Divider(height: 1, color: FoodFlowTheme.line),
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
              return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(metric.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w700,
                            fontSize: 13)),
                    const SizedBox(height: 5),
                    Text(metric.value,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            fontSize: 19, fontWeight: FontWeight.w900))
                  ]);
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
                        style: const TextStyle(
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
                        style: const TextStyle(
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
            style: const TextStyle(
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
            style: const TextStyle(
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
  Widget build(BuildContext context) => const Scaffold(
      backgroundColor: Color(0xFFF2F2F6),
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
