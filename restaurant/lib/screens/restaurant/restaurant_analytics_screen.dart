// lib/screens/restaurant/restaurant_analytics_screen.dart
import 'dart:math' as math;

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../utils/json_utils.dart';

class RestaurantAnalyticsScreen extends StatefulWidget {
  const RestaurantAnalyticsScreen({Key? key}) : super(key: key);

  @override
  State<RestaurantAnalyticsScreen> createState() =>
      _RestaurantAnalyticsScreenState();
}

class _RestaurantAnalyticsScreenState extends State<RestaurantAnalyticsScreen> {
  final ApiService _api = ApiService();
  Map<String, dynamic> _data = {};
  bool _isLoading = true;
  String _selectedPeriod = 'week';

  @override
  void initState() {
    super.initState();
    _loadAnalytics();
  }

  Future<void> _loadAnalytics() async {
    setState(() => _isLoading = true);

    try {
      final response = await _api.get(
        ApiConstants.restaurantAnalytics,
        queryParams: {'period': _selectedPeriod},
      );

      if (!mounted) return;
      if (response['success'] == true) {
        setState(() => _data = Map<String, dynamic>.from(response['data']));
      }
    } catch (e) {
      debugPrint('Load analytics error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Unable to load analytics: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final canViewReports =
        Provider.of<AuthProvider>(context).currentUser?.canViewReports ?? true;

    if (!canViewReports) {
      return Scaffold(
        backgroundColor: const Color(0xFFF7F7F8),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: const [
                Icon(Icons.lock_outline, size: 42, color: FoodFlowTheme.muted),
                SizedBox(height: 12),
                Text(
                  'Reports access is not enabled for this staff account.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: FoodFlowTheme.muted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF7F7F8),
      body: RefreshIndicator(
        color: FoodFlowTheme.orange,
        onRefresh: _loadAnalytics,
        child: _isLoading
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  Padding(
                    padding: const EdgeInsets.only(top: 80),
                    child: const Center(child: CircularProgressIndicator()),
                  ),
                ],
              )
            : ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
                children: [
                  _PeriodSelector(
                    selected: _selectedPeriod,
                    onChanged: (value) {
                      setState(() => _selectedPeriod = value);
                      _loadAnalytics();
                    },
                  ),
                  const SizedBox(height: 16),
                  _buildMetricGrid(),
                  const SizedBox(height: 18),
                  _SectionPanel(
                    title: 'Revenue trend',
                    subtitle: 'Sales across $_periodLabel',
                    trailing: _money(context, _num('total_revenue')),
                    child: SizedBox(
                      height: 248,
                      child: _buildRevenueChart(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  _SectionPanel(
                    title: 'Order volume',
                    subtitle: 'Daily order movement',
                    trailing: '${_int('total_orders')} orders',
                    child: SizedBox(
                      height: 228,
                      child: _buildOrdersChart(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  _SectionPanel(
                    title: 'Best sellers',
                    subtitle: 'Items customers picked most',
                    child: _buildTopItemsList(),
                  ),
                  const SizedBox(height: 16),
                  _SectionPanel(
                    title: 'Peak hours',
                    subtitle: 'When your kitchen is busiest',
                    child: _buildHourlyDistribution(),
                  ),
                ],
              ),
      ),
    );
  }

  Widget _buildMetricGrid() {
    final delivered = _int('delivered_orders');
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.55,
      children: [
        _MetricTile(
          title: 'Revenue',
          value: _money(context, _num('total_revenue')),
          icon: Icons.payments_outlined,
          color: const Color(0xFF0F9D58),
        ),
        _MetricTile(
          title: 'Orders',
          value: '${_int('total_orders')}',
          icon: Icons.receipt_long,
          color: FoodFlowTheme.crimson,
        ),
        _MetricTile(
          title: 'Avg order',
          value: _money(context, _num('avg_order_value')),
          icon: Icons.trending_up,
          color: const Color(0xFF2563EB),
        ),
        _MetricTile(
          title: delivered > 0 ? 'Delivered' : 'Cancelled',
          value: delivered > 0
              ? '$delivered'
              : '${_num('cancellation_rate').toStringAsFixed(1)}%',
          icon: delivered > 0 ? Icons.done_all : Icons.cancel_outlined,
          color: const Color(0xFFFF8A00),
        ),
      ],
    );
  }

  Widget _buildRevenueChart() {
    final dailyData = _list('daily_revenue');
    final spots = <FlSpot>[];

    for (var i = 0; i < dailyData.length; i++) {
      final item = _map(dailyData[i]);
      spots.add(FlSpot(i.toDouble(), parseNullableDouble(item['revenue']) ?? 0));
    }

    if (spots.isEmpty || spots.every((spot) => spot.y == 0)) {
      return const _EmptyAnalyticsState(
        icon: Icons.show_chart,
        text: 'Revenue will appear after orders come in',
      );
    }

    final maxY = spots.map((spot) => spot.y).reduce(math.max);
    return LineChart(
      LineChartData(
        minY: 0,
        maxY: maxY * 1.2,
        gridData: FlGridData(
          drawVerticalLine: false,
          getDrawingHorizontalLine: (_) => FlLine(
            color: Colors.grey.shade200,
            strokeWidth: 1,
          ),
        ),
        titlesData: FlTitlesData(
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 44,
              interval: _chartInterval(maxY),
              getTitlesWidget: (value, meta) => Text(
                _compactMoney(context, value),
                style: const TextStyle(fontSize: 10, color: FoodFlowTheme.muted),
              ),
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 32,
              interval: _bottomInterval(dailyData.length),
              getTitlesWidget: (value, meta) =>
                  _dateTitle(value, dailyData, compact: true),
            ),
          ),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData: FlBorderData(show: false),
        lineTouchData: LineTouchData(
          touchTooltipData: LineTouchTooltipData(
            getTooltipItems: (spots) => spots.map((spot) {
              return LineTooltipItem(
                _money(context, spot.y),
                const TextStyle(color: Colors.white, fontWeight: FontWeight.w800),
              );
            }).toList(),
          ),
        ),
        lineBarsData: [
          LineChartBarData(
            spots: spots,
            isCurved: true,
            color: FoodFlowTheme.orange,
            barWidth: 4,
            isStrokeCapRound: true,
            dotData: FlDotData(
              show: dailyData.length <= 12,
              getDotPainter: (spot, percent, bar, index) =>
                  FlDotCirclePainter(
                radius: 4,
                color: Colors.white,
                strokeWidth: 3,
                strokeColor: FoodFlowTheme.orange,
              ),
            ),
            belowBarData: BarAreaData(
              show: true,
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  FoodFlowTheme.orange.withOpacity(0.24),
                  FoodFlowTheme.orange.withOpacity(0.02),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrdersChart() {
    final dailyData = _list('daily_orders');
    final groups = <BarChartGroupData>[];

    for (var i = 0; i < dailyData.length; i++) {
      final item = _map(dailyData[i]);
      final orders = parseNullableDouble(item['orders']) ?? 0;
      groups.add(
        BarChartGroupData(
          x: i,
          barRods: [
            BarChartRodData(
              toY: orders,
              width: dailyData.length > 18 ? 8 : 16,
              color: FoodFlowTheme.orange,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(5)),
              backDrawRodData: BackgroundBarChartRodData(
                show: true,
                toY: math.max(orders, 1),
                color: const Color(0xFFFFE7D1),
              ),
            ),
          ],
        ),
      );
    }

    if (groups.isEmpty || groups.every((group) => group.barRods.first.toY == 0)) {
      return const _EmptyAnalyticsState(
        icon: Icons.bar_chart,
        text: 'Order volume will appear here',
      );
    }

    final maxY = groups.map((g) => g.barRods.first.toY).reduce(math.max);
    return BarChart(
      BarChartData(
        alignment: BarChartAlignment.spaceAround,
        maxY: maxY + math.max(2, maxY * 0.2),
        gridData: FlGridData(
          drawVerticalLine: false,
          getDrawingHorizontalLine: (_) => FlLine(
            color: Colors.grey.shade200,
            strokeWidth: 1,
          ),
        ),
        borderData: FlBorderData(show: false),
        barGroups: groups,
        titlesData: FlTitlesData(
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 32,
              getTitlesWidget: (value, meta) => Text(
                value.toInt().toString(),
                style: const TextStyle(fontSize: 10, color: FoodFlowTheme.muted),
              ),
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 30,
              interval: _bottomInterval(dailyData.length),
              getTitlesWidget: (value, meta) =>
                  _dateTitle(value, dailyData, compact: true),
            ),
          ),
          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
      ),
    );
  }

  Widget _buildTopItemsList() {
    final topItems = _list('top_items');

    if (topItems.isEmpty) {
      return const _EmptyAnalyticsState(
        icon: Icons.restaurant_menu,
        text: 'Top items will appear after sales',
      );
    }

    final maxOrders = topItems
        .map((item) => parseNullableDouble(_map(item)['total_orders']) ?? 0)
        .fold<double>(0, math.max);

    return Column(
      children: topItems.take(6).toList().asMap().entries.map((entry) {
        final index = entry.key;
        final item = _map(entry.value);
        final orders = parseNullableDouble(item['total_orders']) ?? 0;
        final revenue = parseNullableDouble(item['revenue']) ?? 0;
        final progress = maxOrders <= 0 ? 0.0 : (orders / maxOrders).clamp(0.0, 1.0);

        return Padding(
          padding: EdgeInsets.only(bottom: index == topItems.length - 1 ? 0 : 14),
          child: Row(
            children: [
              Container(
                width: 34,
                height: 34,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: index == 0 ? const Color(0xFFFFF1D6) : Colors.grey.shade100,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '${index + 1}',
                  style: TextStyle(
                    color: index == 0 ? const Color(0xFFFF8A00) : FoodFlowTheme.ink,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item['name']?.toString() ?? 'Menu item',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    const SizedBox(height: 7),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(20),
                      child: LinearProgressIndicator(
                        value: progress,
                        minHeight: 7,
                        backgroundColor: Colors.grey.shade200,
                        color: FoodFlowTheme.orange,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${orders.toInt()} orders',
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Text(
                _money(context, revenue),
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                  color: FoodFlowTheme.ink,
                ),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }

  Widget _buildHourlyDistribution() {
    final hourlyData = _list('hourly_data');
    if (hourlyData.isEmpty) {
      return const _EmptyAnalyticsState(
        icon: Icons.schedule,
        text: 'Peak-hour data will appear here',
      );
    }

    final activeHours = hourlyData
        .map((item) => _map(item))
        .where((item) => (parseNullableDouble(item['orders']) ?? 0) > 0)
        .toList();

    if (activeHours.isEmpty) {
      return const _EmptyAnalyticsState(
        icon: Icons.schedule,
        text: 'No busy hours in this period',
      );
    }

    activeHours.sort((a, b) {
      final aOrders = parseNullableDouble(a['orders']) ?? 0;
      final bOrders = parseNullableDouble(b['orders']) ?? 0;
      return bOrders.compareTo(aOrders);
    });

    final topHours = activeHours.take(5).toList();
    final maxOrders =
        topHours.map((e) => parseNullableDouble(e['orders']) ?? 0).reduce(math.max);

    return Column(
      children: topHours.map((item) {
        final hour = parseIntValue(item['hour']);
        final orders = parseNullableDouble(item['orders']) ?? 0;
        final label = '${hour.toString().padLeft(2, '0')}:00';
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Row(
            children: [
              SizedBox(
                width: 54,
                child: Text(
                  label,
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    color: FoodFlowTheme.ink,
                  ),
                ),
              ),
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: LinearProgressIndicator(
                    value: maxOrders <= 0 ? 0 : (orders / maxOrders),
                    minHeight: 12,
                    color: FoodFlowTheme.crimson,
                    backgroundColor: const Color(0xFFFFE2E5),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              SizedBox(
                width: 68,
                child: Text(
                  '${orders.toInt()} orders',
                  textAlign: TextAlign.right,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: FoodFlowTheme.muted,
                  ),
                ),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }

  Widget _dateTitle(double value, List<dynamic> data, {bool compact = false}) {
    final index = value.toInt();
    if (index < 0 || index >= data.length) return const SizedBox.shrink();
    final rawDate = _map(data[index])['date']?.toString();
    if (rawDate == null) return const SizedBox.shrink();
    final date = DateTime.tryParse(rawDate);
    if (date == null) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Text(
        _selectedPeriod == 'year'
            ? DateFormat('MMM').format(date)
            : DateFormat(compact ? 'd MMM' : 'dd MMM').format(date),
        style: const TextStyle(
          fontSize: 10,
          color: FoodFlowTheme.muted,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  double _chartInterval(double maxY) {
    if (maxY <= 0) return 1;
    return math.max(1, (maxY / 4).ceilToDouble());
  }

  double _bottomInterval(int length) {
    if (length <= 8) return 1;
    if (length <= 31) return 5;
    return 45;
  }

  String _compactMoney(BuildContext context, double value) {
    final symbol = getCurrencySymbol(context);
    if (value >= 100000) return '$symbol${(value / 100000).toStringAsFixed(1)}L';
    if (value >= 1000) return '$symbol${(value / 1000).toStringAsFixed(0)}k';
    return '$symbol${value.toInt()}';
  }

  String _money(BuildContext context, num value) =>
      formatCurrency(context, value);

  num _num(String key) => parseNullableDouble(_data[key]) ?? 0;

  int _int(String key) => parseIntValue(_data[key] ?? 0);

  List<dynamic> _list(String key) => _data[key] is List ? _data[key] as List : [];

  Map<String, dynamic> _map(dynamic value) =>
      value is Map<String, dynamic> ? value : Map<String, dynamic>.from(value as Map);

  String get _periodLabel {
    switch (_selectedPeriod) {
      case 'month':
        return 'last 30 days';
      case 'year':
        return 'last 12 months';
      default:
        return 'last 7 days';
    }
  }
}

class _PeriodSelector extends StatelessWidget {
  const _PeriodSelector({required this.selected, required this.onChanged});

  final String selected;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final periods = const [
      ('week', '7 days'),
      ('month', '30 days'),
      ('year', '12 months'),
    ];

    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE9E9EB)),
      ),
      child: Row(
        children: periods.map((period) {
          final isSelected = selected == period.$1;
          return Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(10),
              onTap: () => onChanged(period.$1),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 160),
                padding: const EdgeInsets.symmetric(vertical: 11),
                decoration: BoxDecoration(
                  color: isSelected ? FoodFlowTheme.orange : Colors.transparent,
                  borderRadius: BorderRadius.circular(10),
                ),
                alignment: Alignment.center,
                child: Text(
                  period.$2,
                  style: TextStyle(
                    color: isSelected ? Colors.white : FoodFlowTheme.muted,
                    fontWeight: FontWeight.w900,
                    fontSize: 13,
                  ),
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String title;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE9E9EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: color.withOpacity(0.11),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 5),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    value,
                    style: const TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 19,
                      fontWeight: FontWeight.w900,
                    ),
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

class _SectionPanel extends StatelessWidget {
  const _SectionPanel({
    required this.title,
    required this.subtitle,
    required this.child,
    this.trailing,
  });

  final String title;
  final String subtitle;
  final String? trailing;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE9E9EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              if (trailing != null)
                Text(
                  trailing!,
                  style: TextStyle(
                    color: FoodFlowTheme.orange,
                    fontWeight: FontWeight.w900,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 18),
          child,
        ],
      ),
    );
  }
}

class _EmptyAnalyticsState extends StatelessWidget {
  const _EmptyAnalyticsState({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Colors.grey.shade400, size: 42),
          const SizedBox(height: 10),
          Text(
            text,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
