import 'package:flutter/material.dart';
import '../../utils/currency_utils.dart';
import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';

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
  List<dynamic> _transactions = [];

  @override
  void initState() {
    super.initState();
    _loadWallet();
  }

  Future<void> _requestWithdrawal() async {
    final controller = TextEditingController(
      text: _balance > 0 ? _balance.toStringAsFixed(2) : '',
    );
    final amount = await showDialog<double>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Request Withdrawal'),
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
              Navigator.pop(
                context,
                double.tryParse(controller.text.trim()),
              );
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

  Future<void> _loadWallet() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(ApiConstants.wallet);
      if (response['success'] == true) {
        final data = response['data'] ?? {};
        final wallet = data['wallet'] ?? {};
        setState(() {
          _balance = double.tryParse('${wallet['balance'] ?? 0}') ?? 0;
          _transactions = data['transactions'] ?? [];
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Wallet unavailable: $e')));
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Wallet'),
        actions: [
          IconButton(
            onPressed: _isLoading ? null : _loadWallet,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadWallet,
              child: ListView(
                padding: const EdgeInsets.only(bottom: 24),
                children: [
                  PremiumRestaurantHeader(
                    title: 'Restaurant Wallet',
                    subtitle:
                        'Track credits, deductions, and payout-ready balance in one place.',
                    icon: Icons.account_balance_wallet_rounded,
                    trailing: Text(
                      formatCurrencyWithDecimals(context, _balance),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: SizedBox(
                      height: 48,
                      child: FilledButton.icon(
                        onPressed: _balance <= 0 || _isRequestingWithdrawal
                            ? null
                            : _requestWithdrawal,
                        icon: const Icon(Icons.account_balance_rounded),
                        label: Text(_isRequestingWithdrawal
                            ? 'Submitting...'
                            : 'Request Withdrawal'),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Row(
                      children: [
                        Expanded(
                          child: PremiumMetricCard(
                            title: 'Available',
                            value: formatCurrency(context, _balance),
                            icon: Icons.savings_rounded,
                            color: FoodFlowTheme.success,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: PremiumMetricCard(
                            title: 'Transactions',
                            value: _transactions.length.toString(),
                            icon: Icons.receipt_long_rounded,
                            color: FoodFlowTheme.orange,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  const PremiumSectionTitle(
                    title: 'Wallet Activity',
                    subtitle: 'Latest settlement and adjustment entries.',
                  ),
                  if (_transactions.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Container(
                        decoration: RestaurantPremium.panel(radius: 18),
                        child: FoodFlowTheme.emptyState(
                          icon: Icons.account_balance_wallet_outlined,
                          title: 'No wallet activity yet',
                          subtitle:
                              'Payouts, deductions and credits will show here.',
                        ),
                      ),
                    )
                  else
                    ..._transactions.map((transaction) {
                      final amount =
                          double.tryParse('${transaction['amount'] ?? 0}') ?? 0;
                      final type = '${transaction['type'] ?? ''}';
                      final isCredit =
                          type.contains('credit') || type.contains('topup');

                      return Padding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                        child: Container(
                          decoration: RestaurantPremium.panel(radius: 18),
                          padding: const EdgeInsets.all(14),
                          child: Row(
                            children: [
                              Container(
                                width: 44,
                                height: 44,
                                decoration: BoxDecoration(
                                  color: (isCredit
                                          ? FoodFlowTheme.success
                                          : FoodFlowTheme.orange)
                                      .withOpacity(0.12),
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                child: Icon(
                                  isCredit
                                      ? Icons.arrow_downward_rounded
                                      : Icons.arrow_upward_rounded,
                                  color: isCredit
                                      ? FoodFlowTheme.success
                                      : FoodFlowTheme.orange,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      transaction['description']?.toString() ??
                                          type.replaceAll('_', ' '),
                                      style: const TextStyle(
                                        color: FoodFlowTheme.ink,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    const SizedBox(height: 3),
                                    Text(
                                      type.replaceAll('_', ' '),
                                      style: const TextStyle(
                                        color: FoodFlowTheme.muted,
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              Text(
                                '${isCredit ? '+' : '-'} ${formatCurrency(context, amount)}',
                                style: TextStyle(
                                  color: isCredit
                                      ? FoodFlowTheme.success
                                      : FoodFlowTheme.orange,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }
}
