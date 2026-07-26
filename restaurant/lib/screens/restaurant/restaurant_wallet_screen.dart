import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import 'restaurant_payout_detail_screen.dart';

class RestaurantWalletScreen extends StatefulWidget {
  const RestaurantWalletScreen({super.key});

  @override
  State<RestaurantWalletScreen> createState() => _RestaurantWalletScreenState();
}

class _RestaurantWalletScreenState extends State<RestaurantWalletScreen> {
  final ApiService _api = ApiService();
  bool _isLoading = true;
  bool _isRequestingWithdrawal = false;
  double _balance = 0;
  double _lockedBalance = 0;
  List<Map<String, dynamic>> _transactions = [];

  @override
  void initState() {
    super.initState();
    _loadWallet();
  }

  Future<void> _loadWallet() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(ApiConstants.wallet);
      if (response['success'] == true) {
        final data = _asMap(response['data']);
        final wallet = _asMap(data['wallet']);
        final transactions = data['transactions'] is List
            ? data['transactions'] as List
            : const [];
        if (!mounted) return;
        setState(() {
          _balance = _toDouble(wallet['balance']);
          _lockedBalance = _toDouble(wallet['locked_balance']);
          _transactions = transactions
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList();
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Wallet unavailable: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _requestWithdrawal() async {
    final controller = TextEditingController(
      text: _balance > 0 ? _balance.toStringAsFixed(2) : '',
    );
    final amount = await showDialog<double>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Request withdrawal'),
        content: TextField(
          controller: controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(
            labelText: 'Amount',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.pop(context, double.tryParse(controller.text.trim()));
            },
            child: const Text('Submit'),
          ),
        ],
      ),
    );

    if (amount == null || amount <= 0) return;
    if (amount > _balance) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Amount exceeds available balance')),
      );
      return;
    }

    setState(() => _isRequestingWithdrawal = true);
    try {
      final response = await _api.post(
        ApiConstants.walletWithdraw,
        data: {'amount': amount},
      );
      if (response['success'] == true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Withdrawal request submitted')),
          );
        }
        await _loadWallet();
      } else {
        throw Exception(response['message'] ?? 'Withdrawal request failed');
      }
    } finally {
      if (mounted) setState(() => _isRequestingWithdrawal = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      body: RefreshIndicator(
        color: FoodFlowTheme.orange,
        onRefresh: _loadWallet,
        child: _isLoading
            ? ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 180),
                  Center(child: CircularProgressIndicator()),
                ],
              )
            : ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 26),
                children: [
                  _WalletHeader(
                    balance: _balance,
                    isRequesting: _isRequestingWithdrawal,
                    onRefresh: _loadWallet,
                    onWithdraw: _balance <= 0 || _isRequestingWithdrawal
                        ? null
                        : _requestWithdrawal,
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: _WalletMetric(
                          title: 'Available',
                          value: formatCurrency(context, _balance),
                          icon: Icons.savings_rounded,
                          color: FoodFlowTheme.success,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _WalletMetric(
                          title: 'Reserved',
                          value: formatCurrency(context, _lockedBalance),
                          icon: Icons.lock_clock_rounded,
                          color: FoodFlowTheme.orange,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  _SectionHeading(
                    title: 'Wallet Activity',
                    subtitle: '${_transactions.length} latest entries',
                  ),
                  const SizedBox(height: 10),
                  if (_transactions.isEmpty)
                    const _WalletEmptyState()
                  else
                    ..._transactions.map(
                      (transaction) => _TransactionTile(
                        transaction: transaction,
                        onOpenPayout: _openPayoutDetails,
                      ),
                    ),
                ],
              ),
      ),
    );
  }

  void _openPayoutDetails(Map<String, dynamic> transaction) {
    final payoutId = _toInt(transaction['reference_id']);
    if (payoutId == null) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RestaurantPayoutDetailScreen(
          payoutId: payoutId,
          initialTransaction: transaction,
        ),
      ),
    );
  }
}

class _WalletHeader extends StatelessWidget {
  const _WalletHeader({
    required this.balance,
    required this.isRequesting,
    required this.onRefresh,
    required this.onWithdraw,
  });

  final double balance;
  final bool isRequesting;
  final VoidCallback onRefresh;
  final VoidCallback? onWithdraw;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: _panelDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: FoodFlowTheme.orange.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  Icons.account_balance_wallet_rounded,
                  color: FoodFlowTheme.orange,
                  size: 22,
                ),
              ),
              const SizedBox(width: 11),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Restaurant Wallet',
                      style: TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Payout-ready balance and settlements',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: FoodFlowTheme.muted,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                onPressed: onRefresh,
                icon: const Icon(Icons.refresh_rounded),
                color: FoodFlowTheme.orange,
                visualDensity: VisualDensity.compact,
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            formatCurrencyWithDecimals(context, balance),
            style: const TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 28,
              height: 1,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 44,
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: onWithdraw,
              icon: const Icon(Icons.account_balance_rounded, size: 18),
              label:
                  Text(isRequesting ? 'Submitting...' : 'Request Withdrawal'),
              style: FilledButton.styleFrom(
                backgroundColor: FoodFlowTheme.orange,
                foregroundColor: Colors.white,
                textStyle: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WalletMetric extends StatelessWidget {
  const _WalletMetric({
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
      height: 92,
      padding: const EdgeInsets.all(12),
      decoration: _panelDecoration(),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: color.withOpacity(0.11),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 20),
          ),
          const SizedBox(width: 10),
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
                    fontSize: 11,
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
                      fontSize: 17,
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

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _TransactionTile extends StatelessWidget {
  const _TransactionTile({
    required this.transaction,
    required this.onOpenPayout,
  });

  final Map<String, dynamic> transaction;
  final ValueChanged<Map<String, dynamic>> onOpenPayout;

  @override
  Widget build(BuildContext context) {
    final type = '${transaction['type'] ?? ''}'.toLowerCase();
    final referenceType =
        '${transaction['reference_type'] ?? ''}'.toLowerCase();
    final isPayout = referenceType == 'payout' &&
        _toInt(transaction['reference_id']) != null;
    final isCredit = type.contains('credit') || type.contains('topup');
    final color = isCredit ? FoodFlowTheme.success : FoodFlowTheme.orange;
    final amount = _toDouble(transaction['amount']);
    final description = transaction['description']?.toString().trim();

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: isPayout ? () => onOpenPayout(transaction) : null,
          child: Ink(
            padding: const EdgeInsets.all(12),
            decoration: _panelDecoration(),
            child: Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.11),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    isCredit
                        ? Icons.arrow_downward_rounded
                        : Icons.arrow_upward_rounded,
                    color: color,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        description?.isNotEmpty == true
                            ? description!
                            : _titleCase(type.replaceAll('_', ' ')),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 13,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Wrap(
                        spacing: 7,
                        runSpacing: 4,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          Text(
                            _formatDate(transaction['created_at']),
                            style: const TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (isPayout)
                            Text(
                              'View orders',
                              style: TextStyle(
                                color: FoodFlowTheme.orange,
                                fontSize: 11,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      '${isCredit ? '+' : '-'} ${formatCurrency(context, amount)}',
                      style: TextStyle(
                        color: color,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (isPayout) ...[
                      const SizedBox(height: 5),
                      const Icon(
                        Icons.chevron_right_rounded,
                        color: FoodFlowTheme.muted,
                        size: 20,
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _WalletEmptyState extends StatelessWidget {
  const _WalletEmptyState();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 30),
      decoration: _panelDecoration(),
      child: const Column(
        children: [
          Icon(
            Icons.account_balance_wallet_outlined,
            color: FoodFlowTheme.muted,
            size: 34,
          ),
          SizedBox(height: 10),
          Text(
            'No wallet activity yet',
            style: TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 15,
              fontWeight: FontWeight.w900,
            ),
          ),
          SizedBox(height: 4),
          Text(
            'Payouts, deductions and credits will show here.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

BoxDecoration _panelDecoration() {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(14),
    border: Border.all(color: FoodFlowTheme.line),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withOpacity(0.035),
        blurRadius: 14,
        offset: const Offset(0, 6),
      ),
    ],
  );
}

Map<String, dynamic> _asMap(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return Map<String, dynamic>.from(value);
  return {};
}

double _toDouble(dynamic value) {
  if (value is num) return value.toDouble();
  return double.tryParse('${value ?? 0}') ?? 0;
}

int? _toInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? ''}');
}

String _formatDate(dynamic value) {
  final parsed = DateTime.tryParse('${value ?? ''}');
  if (parsed == null) return 'Recent';
  return DateFormat('dd MMM, h:mm a').format(parsed.toLocal());
}

String _titleCase(String value) {
  return value
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => part[0].toUpperCase() + part.substring(1))
      .join(' ');
}
