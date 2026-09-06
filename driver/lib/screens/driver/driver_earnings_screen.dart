// lib/screens/driver/driver_earnings_screen.dart
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../services/websocket_service.dart';
import '../../config/api_constants.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/aurora/aurora.dart';

class DriverEarningsScreen extends StatefulWidget {
  const DriverEarningsScreen({Key? key}) : super(key: key);

  @override
  State<DriverEarningsScreen> createState() => _DriverEarningsScreenState();
}

class _DriverEarningsScreenState extends State<DriverEarningsScreen>
    with SingleTickerProviderStateMixin {
  final ApiService _api = ApiService();

  late TabController _tabController;
  Map<String, dynamic> _earnings = {};
  List<Map<String, dynamic>> _transactions = [];
  bool _isLoading = true;
  bool _isRefreshing = false;
  StreamSubscription<Map<String, dynamic>>? _driverEventsSubscription;
  Timer? _realtimeRefreshDebounce;
  String _selectedPeriod = 'week';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadEarnings();
    _subscribeToRealtimeEarnings();
  }

  @override
  void dispose() {
    _realtimeRefreshDebounce?.cancel();
    _driverEventsSubscription?.cancel();
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadEarnings({bool showLoader = true}) async {
    if (showLoader && mounted) {
      setState(() => _isLoading = true);
    } else if (mounted) {
      setState(() => _isRefreshing = true);
    }

    try {
      final response = await _api.get(
        ApiConstants.driverEarnings,
        queryParams: {'period': _selectedPeriod},
      );

      if (response['success'] == true) {
        final data = response['data'];
        final dataMap = data is Map ? Map<String, dynamic>.from(data) : {};
        final summary = dataMap['summary'];
        final transactions = dataMap['transactions'];

        if (!mounted) return;
        setState(() {
          _earnings = summary is Map ? Map<String, dynamic>.from(summary) : {};
          _transactions = _normalizeTransactions(transactions);
        });
      }
    } catch (e) {
      debugPrint('Load earnings error: $e');
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _isRefreshing = false;
        });
      }
    }
  }

  void _subscribeToRealtimeEarnings() {
    _driverEventsSubscription = WebSocketService().driverEvents.listen((event) {
      final eventName = event['_event']?.toString().toLowerCase() ?? '';
      final status = event['status']?.toString().toLowerCase() ?? '';
      final isEarningRelated = status == 'delivered' ||
          eventName.contains('payment') ||
          eventName.contains('collection') ||
          event.containsKey('payment_status') ||
          event.containsKey('paid_at');

      if (isEarningRelated) {
        _scheduleRealtimeRefresh();
      }
    });
  }

  void _scheduleRealtimeRefresh() {
    _realtimeRefreshDebounce?.cancel();
    _realtimeRefreshDebounce = Timer(const Duration(milliseconds: 500), () {
      if (mounted) _loadEarnings(showLoader: false);
    });
  }

  List<Map<String, dynamic>> _normalizeTransactions(dynamic value) {
    if (value is! Iterable) return const [];
    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
  }

  @override
  Widget build(BuildContext context) {
    return AuroraScaffold(
      appBar: GlassAppBar(
        title: const Text('Earnings'),
        bottom: TabBar(
          controller: _tabController,
          labelColor: foodflow.orange,
          unselectedLabelColor: foodflow.muted,
          indicatorColor: foodflow.orange,
          tabs: const [
            Tab(text: 'Overview'),
            Tab(text: 'Transactions'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildOverviewTab(),
          _buildTransactionsTab(),
        ],
      ),
    );
  }

  Widget _buildOverviewTab() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: () => _loadEarnings(showLoader: false),
      child: SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AuroraEntrance(
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                gradient: foodflow.brandGradient,
                borderRadius: BorderRadius.circular(22),
                boxShadow: [
                  BoxShadow(
                    color: foodflow.orange.withOpacity(0.28),
                    blurRadius: 24,
                    offset: const Offset(0, 14),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _selectedPeriod == 'week'
                        ? 'This week'
                        : 'This month',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.85),
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  TweenAnimationBuilder<double>(
                    tween: Tween(
                      begin: 0,
                      end: (_earnings['total_earnings'] as num?)?.toDouble() ??
                          0,
                    ),
                    duration: const Duration(milliseconds: 700),
                    curve: Curves.easeOutCubic,
                    builder: (context, value, _) => Text(
                      formatCurrencyValue(context, value),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 34,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '${_earnings['total_deliveries'] ?? 0} deliveries • '
                    '${formatCurrencyValue(context, _earnings['tip_earnings'])} tips',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.9),
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),

          AuroraEntrance(delay: const Duration(milliseconds: 60),
              child: _buildPayoutModeBanner()),

          // Period Selector
          Row(
            children: [
              _buildPeriodButton('week', 'This Week'),
              const SizedBox(width: 12),
              _buildPeriodButton('month', 'This Month'),
            ],
          ),
          const SizedBox(height: 24),

          // Stats Cards
          for (final (i, pair) in <List<Widget>>[
            [
              _buildStatCard('Total Deliveries',
                  '${_earnings['total_deliveries'] ?? 0}',
                  Icons.delivery_dining, Colors.blue),
              _buildStatCard('Avg per Delivery',
                  formatCurrencyValue(context, _earnings['avg_per_delivery']),
                  Icons.trending_up, Colors.green),
            ],
            [
              _buildStatCard('Pending Amount',
                  formatCurrencyValue(context, _earnings['pending_amount']),
                  Icons.pending, Colors.orange),
              _buildStatCard('Withdrawn',
                  formatCurrencyValue(context, _earnings['withdrawn_amount']),
                  Icons.account_balance_wallet, const Color(0xFFFF6E00)),
            ],
            [
              _buildStatCard('Tips Received',
                  formatCurrencyValue(context, _earnings['tip_earnings']),
                  Icons.volunteer_activism, const Color(0xFF16A34A)),
              _buildStatCard('Cash Collected',
                  formatCurrencyValue(
                      context, _earnings['cash_collected_total']),
                  Icons.payments_rounded, const Color(0xFF0EA5E9)),
            ],
          ].indexed)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: AuroraEntrance(
                delay: Duration(milliseconds: 90 + i * 60),
                child: Row(
                  children: [
                    Expanded(child: pair[0]),
                    const SizedBox(width: 12),
                    Expanded(child: pair[1]),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 12),

          // Multiple Order Bonus Info
          if ((_earnings['multiple_order_bonus'] ?? 0) > 0)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [
                    Color(0xFFFFECB3),
                    Color(0xFFFFF9C4),
                  ],
                ),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFFB300)),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(
                      Icons.star,
                      color: Color(0xFFFFB300),
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Multiple Order Bonus',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w400,
                            color: Colors.grey,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          formatCurrencyValue(
                              context, _earnings['multiple_order_bonus']),
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFFFFB300),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    '${_earnings['multiple_order_deliveries'] ?? 0} routes',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w400,
                      color: Colors.grey,
                    ),
                  ),
                ],
              ),
            ),
          if ((_earnings['multiple_order_bonus'] ?? 0) > 0)
            const SizedBox(height: 24),

          // Earnings Chart — hidden when there is no data.
          if (((_earnings['daily_earnings'] as List?) ?? const []).isNotEmpty)
            AuroraEntrance(
              delay: const Duration(milliseconds: 260),
              child: GlassCard(
                solid: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Earnings Overview',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: foodflow.ink,
                      ),
                    ),
                    const SizedBox(height: 16),
                    SizedBox(height: 190, child: _buildEarningsChart()),
                  ],
                ),
              ),
            ),
          if (((_earnings['daily_earnings'] as List?) ?? const []).isNotEmpty)
            const SizedBox(height: 16),

          GlassCard(
            child: Row(
              children: [
                Icon(Icons.info_outline, color: foodflow.orange),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Payouts are processed automatically by admin payment gateway. Manual withdrawal is disabled.',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: foodflow.inkSoft,
                      fontSize: 12.5,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
        ],
      ),
      ),
    );
  }

  Widget _buildPayoutModeBanner() {
    final isSalary = '${_earnings['earning_mode'] ?? 'commission'}' == 'salary';
    final title = isSalary ? 'Fixed salary' : 'Per-delivery commission';
    final subtitle = isSalary
        ? 'You earn a fixed monthly salary. Per-trip amounts below are for reference and are already included in your salary.'
        : 'You earn per delivery. Totals below are what you have earned this period.';
    final accent = isSalary ? const Color(0xFF7C3AED) : foodflow.orange;

    return Padding(
      padding: const EdgeInsets.only(bottom: 24),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: accent.withOpacity(0.08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: accent.withOpacity(0.30)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              isSalary ? Icons.badge_rounded : Icons.route_rounded,
              color: accent,
              size: 20,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        title,
                        style: TextStyle(
                          color: accent,
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                        ),
                      ),
                      if (isSalary &&
                          (_earnings['salary_accrued'] ?? 0) > 0) ...[
                        const Spacer(),
                        Text(
                          '${formatCurrencyValue(context, _earnings['salary_accrued'])} accrued',
                          style: TextStyle(
                            color: accent,
                            fontWeight: FontWeight.w800,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style:  TextStyle(
                      color: foodflow.muted,
                      fontSize: 11.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPeriodButton(String period, String label) {
    final isSelected = _selectedPeriod == period;
    return Expanded(
      child: OutlinedButton(
        onPressed: _isRefreshing || isSelected
            ? null
            : () {
                setState(() => _selectedPeriod = period);
                _loadEarnings();
              },
        style: OutlinedButton.styleFrom(
          backgroundColor:
              isSelected ? foodflow.orange : foodflow.surfaceColor,
          disabledBackgroundColor: foodflow.orange,
          side: BorderSide(
            color: isSelected ? foodflow.orange : foodflow.line,
          ),
          foregroundColor: isSelected ? Colors.white : foodflow.ink,
          disabledForegroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
        child: Text(label,
            style: const TextStyle(fontWeight: FontWeight.w700)),
      ),
    );
  }

  Widget _buildStatCard(
      String title, String value, IconData icon, Color color) {
    return GlassCard(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: color.withOpacity(0.14),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 12,
                    color: foodflow.muted,
                    fontWeight: FontWeight.w400,
                  ),
                ),
                Text(
                  value,
                  style: TextStyle(
                    fontSize: 18,
                    color: foodflow.ink,
                    fontWeight: FontWeight.w800,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEarningsChart() {
    final dailyEarnings = _earnings['daily_earnings'] as List? ?? [];

    if (dailyEarnings.isEmpty) {
      return const Center(child: Text('No data available'));
    }

    return LineChart(
      LineChartData(
        gridData: const FlGridData(show: true),
        titlesData: FlTitlesData(
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 40,
              getTitlesWidget: (value, meta) {
                return Text(formatCompactCurrency(context, value));
              },
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 30,
              getTitlesWidget: (value, meta) {
                if (value.toInt() < dailyEarnings.length) {
                  return Text(
                    DateFormat('dd').format(
                      DateTime.parse(dailyEarnings[value.toInt()]['date']),
                    ),
                    style: const TextStyle(fontSize: 10),
                  );
                }
                return const Text('');
              },
            ),
          ),
          rightTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          topTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData: FlBorderData(show: false),
        lineBarsData: [
          LineChartBarData(
            spots: dailyEarnings.asMap().entries.map((entry) {
              return FlSpot(entry.key.toDouble(),
                  (entry.value['amount'] ?? 0).toDouble());
            }).toList(),
            isCurved: true,
            color: foodflow.orange,
            barWidth: 3,
            dotData: const FlDotData(show: true),
            belowBarData: BarAreaData(
              show: true,
              color: foodflow.orange.withOpacity(0.1),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTransactionsTab() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_transactions.isEmpty) {
      return foodflow.emptyState(
        icon: Icons.history,
        title: 'No transactions yet',
        subtitle: 'Earnings credits and withdrawals will appear here.',
      );
    }

    return RefreshIndicator(
      onRefresh: () => _loadEarnings(showLoader: false),
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
        itemCount: _transactions.length,
        itemBuilder: (context, index) {
          final transaction = _transactions[index];
          final isCredit = transaction['type'] == 'credit';
          final tint = isCredit ? foodflow.success : foodflow.danger;
          final parsedDate =
              DateTime.tryParse('${transaction['created_at'] ?? ''}');

          return AuroraEntrance(
            delay: Duration(milliseconds: (index * 45).clamp(0, 300)),
            child: GlassCard(
              solid: true,
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: tint.withOpacity(0.14),
                      borderRadius: BorderRadius.circular(11),
                    ),
                    child: Icon(
                      isCredit
                          ? Icons.south_west_rounded
                          : Icons.north_east_rounded,
                      color: tint,
                      size: 19,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          transaction['description'] ??
                              'Order #${transaction['order_number'] ?? ''}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: foodflow.ink,
                            fontSize: 13.5,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          parsedDate == null
                              ? ''
                              : DateFormat('dd MMM, h:mm a').format(parsedDate),
                          style:
                              TextStyle(fontSize: 11.5, color: foodflow.muted),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    '${isCredit ? '+' : '-'}${formatCurrencyValue(context, transaction['amount'])}',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: tint,
                      fontSize: 14,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
