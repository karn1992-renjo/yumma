// lib/screens/driver/driver_earnings_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

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
  List<dynamic> _transactions = [];
  bool _isLoading = true;
  String _selectedPeriod = 'week';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadEarnings();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadEarnings() async {
    setState(() => _isLoading = true);

    try {
      final response =
          await _api.get(ApiConstants.driverEarnings, queryParams: {
        'period': _selectedPeriod,
      });

      if (response['success'] == true) {
        final summary = response['data']['summary'] ?? {};
        final transactions = response['data']['transactions'] ?? [];
        
        setState(() {
          _earnings = Map<String, dynamic>.from(summary);
          _transactions = transactions;
        });
      }
    } catch (e) {
      debugPrint('Load earnings error: $e');
    }

    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: foodflow.canvas,
      appBar: AppBar(
        title: const Text('Earnings'),
        backgroundColor: Colors.white,
        foregroundColor: foodflow.ink,
        elevation: 0,
        bottom: TabBar(
          controller: _tabController,
          labelColor: foodflow.crimson,
          unselectedLabelColor: foodflow.muted,
          indicatorColor: foodflow.crimson,
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

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: foodflow.line),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.04),
                  blurRadius: 18,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Today\'s Earnings',
                  style: TextStyle(
                    color: foodflow.ink,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  formatCurrencyValue(context, _earnings['total_earnings']),
                  style: const TextStyle(
                    color: foodflow.success,
                    fontSize: 32,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Icon(
                      Icons.calendar_today,
                      size: 14,
                      color: foodflow.muted,
                    ),
                    const SizedBox(width: 4),
                    Text(
                      _selectedPeriod == 'week'
                          ? 'Last 7 days'
                          : 'Last 30 days',
                      style: const TextStyle(
                        color: foodflow.muted,
                        fontSize: 12,
                        fontWeight: FontWeight.w400,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

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
          Row(
            children: [
              Expanded(
                child: _buildStatCard(
                  'Total Deliveries',
                  '${_earnings['total_deliveries'] ?? 0}',
                  Icons.delivery_dining,
                  Colors.blue,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildStatCard(
                  'Avg per Delivery',
                  formatCurrencyValue(context, _earnings['avg_per_delivery']),
                  Icons.trending_up,
                  Colors.green,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _buildStatCard(
                  'Pending Amount',
                  formatCurrencyValue(context, _earnings['pending_amount']),
                  Icons.pending,
                  Colors.orange,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildStatCard(
                  'Withdrawn',
                  formatCurrencyValue(context, _earnings['withdrawn_amount']),
                  Icons.account_balance_wallet,
                  const Color(0xFFFF6E00),
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),

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
                          formatCurrencyValue(context, _earnings['multiple_order_bonus']),
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

          // Earnings Chart
          const Text(
            'Earnings Overview',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            height: 200,
            child: _buildEarningsChart(),
          ),
          const SizedBox(height: 24),

          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.blue.withOpacity(0.08),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.blue.withOpacity(0.18)),
            ),
            child: const Row(
              children: [
                Icon(Icons.info_outline, color: Colors.blue),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Payouts are processed automatically by admin payment gateway. Manual withdrawal is disabled.',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _buildPeriodButton(String period, String label) {
    final isSelected = _selectedPeriod == period;
    return Expanded(
      child: OutlinedButton(
        onPressed: () {
          setState(() => _selectedPeriod = period);
          _loadEarnings();
        },
        style: OutlinedButton.styleFrom(
          backgroundColor: isSelected ? foodflow.crimson : Colors.white,
          side: BorderSide(
            color: isSelected ? foodflow.crimson : foodflow.line,
          ),
          foregroundColor: isSelected ? Colors.white : foodflow.ink,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
        child: Text(label),
      ),
    );
  }

  Widget _buildStatCard(
      String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: foodflow.surface(radius: 14),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: color, size: 24),
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
                  style: const TextStyle(
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

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _transactions.length,
      itemBuilder: (context, index) {
        final transaction = _transactions[index];
        final isCredit = transaction['type'] == 'credit';

        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          decoration: foodflow.surface(radius: 14),
          child: ListTile(
            leading: CircleAvatar(
              backgroundColor:
                  isCredit ? Colors.green.shade100 : Colors.red.shade100,
              child: Icon(
                isCredit ? Icons.arrow_downward : Icons.arrow_upward,
                color: isCredit ? Colors.green : Colors.red,
              ),
            ),
            title: Text(transaction['description'] ??
                'Order #${transaction['order_number']}'),
            subtitle: Text(
              DateFormat('dd MMM yyyy, HH:mm').format(
                DateTime.parse(transaction['created_at']),
              ),
            ),
            trailing: Text(
              '${isCredit ? '+' : '-'} ${formatCurrencyValue(context, transaction['amount'])}',
              style: TextStyle(
                fontWeight: FontWeight.w800,
                color: isCredit ? Colors.green : Colors.red,
              ),
            ),
          ),
        );
      },
    );
  }

}
