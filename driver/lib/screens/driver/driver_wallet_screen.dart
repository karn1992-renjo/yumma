import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/api_constants.dart';
import '../../models/user.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/wallet_recharge_payment_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

class DriverWalletScreen extends StatefulWidget {
  const DriverWalletScreen({super.key});

  @override
  State<DriverWalletScreen> createState() => _DriverWalletScreenState();
}

class _DriverWalletScreenState extends State<DriverWalletScreen> {
  final ApiService _api = ApiService();
  late final WalletRechargePaymentService _paymentService;
  bool _isLoading = true;
  bool _isRecharging = false;
  bool _isRequestingWithdrawal = false;
  double _balance = 0;
  double _minimumDriverBalance = 0;
  List<dynamic> _transactions = [];

  @override
  void initState() {
    super.initState();
    _paymentService = WalletRechargePaymentService(
      onSuccess: () async {
        if (!mounted) return;
        setState(() => _isRecharging = false);
        if (!mounted) return;
        ScaffoldMessenger.of(context).clearSnackBars();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Wallet recharged successfully')),
        );
        await _loadWallet();
      },
      onFailure: (message) {
        if (!mounted) return;
        setState(() => _isRecharging = false);
        if (!mounted) return;
        ScaffoldMessenger.of(context).clearSnackBars();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(message),
            backgroundColor: FoodFlowTheme.danger,
          ),
        );
      },
    );
    _loadWallet();
  }

  @override
  void dispose() {
    _paymentService.dispose();
    super.dispose();
  }

  Future<void> _loadWallet() async {
    if (!mounted) return;
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(ApiConstants.wallet);
      if (!mounted) return;
      if (response['success'] == true) {
        final data = response['data'] ?? {};
        final wallet = data['wallet'] ?? {};
        if (!mounted) return;
        setState(() {
          _balance = double.tryParse('${wallet['balance'] ?? 0}') ?? 0;
          _minimumDriverBalance =
              double.tryParse('${data['minimum_driver_balance'] ?? 0}') ?? 0;
          _transactions = data['transactions'] ?? [];
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).clearSnackBars();
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('Wallet unavailable: $e')));
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _showRechargeSheet() async {
    final user = context.read<AuthProvider>().currentUser;
    if (!mounted) return;

    final amount = await Navigator.of(context).push<double>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) =>
            _WalletRechargePage(minimumDriverBalance: _minimumDriverBalance),
      ),
    );

    if (amount != null && mounted) {
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        if (mounted) {
          await _startRecharge(amount, user);
        }
      });
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
    controller.dispose();

    if (amount == null || amount <= 0) return;
    if (amount > _balance) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Amount exceeds available balance')),
      );
      return;
    }

    if (!mounted) return;
    setState(() => _isRequestingWithdrawal = true);
    try {
      final response = await _api.post(
        ApiConstants.walletWithdraw,
        data: {
          'amount': amount,
          'description': 'Driver manual withdrawal requested',
        },
      );

      if (response['success'] == true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).clearSnackBars();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Withdrawal request submitted')),
        );
        await _loadWallet();
      } else {
        throw Exception(response['message'] ?? 'Withdrawal request failed');
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Withdrawal unavailable: $e'),
          backgroundColor: FoodFlowTheme.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _isRequestingWithdrawal = false);
    }
  }

  Future<void> _startRecharge(double amount, User? user) async {
    if (!mounted) return;

    try {
      if (!mounted) return;
      setState(() => _isRecharging = true);

      if (!mounted) return;
      await _paymentService.start(amount: amount, user: user);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isRecharging = false);
      if (!mounted) return;
      ScaffoldMessenger.of(context).clearSnackBars();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_normalizeStartupErrorMessage(e.toString())),
          backgroundColor: FoodFlowTheme.danger,
        ),
      );
    }
  }

  String _normalizeStartupErrorMessage(String? message) {
    final trimmed = message?.trim();
    if (trimmed == null || trimmed.isEmpty) {
      return 'Payment cancelled or failed';
    }
    final lower = trimmed.toLowerCase();
    if (lower == 'null' || lower == 'undefined') {
      return 'Payment cancelled or failed';
    }
    return trimmed;
  }

  @override
  Widget build(BuildContext context) {
    return _isLoading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _loadWallet,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: FoodFlowTheme.crimson,
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Available balance',
                        style: TextStyle(color: Colors.white70),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        formatCurrencyWithDecimals(context, _balance),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 32,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      if (_minimumDriverBalance > 0) ...[
                        const SizedBox(height: 8),
                        Text(
                          'Minimum for COD orders: ${formatCurrencyWithDecimals(context, _minimumDriverBalance)}',
                          style: const TextStyle(
                            color: Colors.white70,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          Expanded(
                            child: ElevatedButton.icon(
                              onPressed:
                                  _isRecharging ? null : _showRechargeSheet,
                              icon: _isRecharging
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white,
                                      ),
                                    )
                                  : const Icon(Icons.add_card_rounded),
                              label: Text(
                                _isRecharging
                                    ? 'Opening...'
                                    : 'Recharge',
                              ),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: Colors.white,
                                foregroundColor: FoodFlowTheme.crimson,
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: ElevatedButton.icon(
                              onPressed: _balance <= 0 ||
                                      _isRequestingWithdrawal
                                  ? null
                                  : _requestWithdrawal,
                              icon: _isRequestingWithdrawal
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(Icons.account_balance_rounded),
                              label: Text(
                                _isRequestingWithdrawal
                                    ? 'Submitting...'
                                    : 'Withdraw',
                              ),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: Colors.white,
                                foregroundColor: FoodFlowTheme.crimson,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                const Text(
                  'Wallet activity',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 10),
                if (_transactions.isEmpty)
                  FoodFlowTheme.emptyState(
                    icon: Icons.account_balance_wallet_outlined,
                    title: 'No wallet activity yet',
                    subtitle: 'Payout adjustments and credits will show here.',
                  )
                else
                  ..._transactions.map((transaction) {
                    final amount =
                        double.tryParse('${transaction['amount'] ?? 0}') ?? 0;
                    final type = '${transaction['type'] ?? ''}';
                    final isCredit =
                        type.contains('credit') || type.contains('topup');
                    return Card(
                      child: ListTile(
                        leading: Icon(
                          isCredit ? Icons.arrow_downward : Icons.arrow_upward,
                        ),
                        title: Text(
                          transaction['description']?.toString() ??
                              type.replaceAll('_', ' '),
                        ),
                        subtitle: Text(type.replaceAll('_', ' ')),
                        trailing: Text(
                          '${isCredit ? '+' : '-'} ${formatCurrency(context, amount)}',
                        ),
                      ),
                    );
                  }),
              ],
            ),
          );
  }
}

class _WalletRechargePage extends StatelessWidget {
  const _WalletRechargePage({required this.minimumDriverBalance});

  final double minimumDriverBalance;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(title: const Text('Recharge wallet')),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 560),
            child: _WalletRechargeSheet(
              minimumDriverBalance: minimumDriverBalance,
            ),
          ),
        ),
      ),
    );
  }
}

class _WalletRechargeSheet extends StatefulWidget {
  const _WalletRechargeSheet({Key? key, required this.minimumDriverBalance})
    : super(key: key);

  final double minimumDriverBalance;

  @override
  State<_WalletRechargeSheet> createState() => _WalletRechargeSheetState();
}

class _WalletRechargeSheetState extends State<_WalletRechargeSheet> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _presets = [200, 500, 1000, 2000];

  @override
  void initState() {
    super.initState();
    _amountController.text = widget.minimumDriverBalance > 0
        ? widget.minimumDriverBalance.toStringAsFixed(0)
        : '500';
  }

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: SingleChildScrollView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Recharge wallet',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _presets.map((amount) {
                  return ChoiceChip(
                    label: Text(formatCurrencyValue(context, amount)),
                    selected: _amountController.text == '$amount',
                    onSelected: (_) {
                      _amountController.text = '$amount';
                      setState(() {});
                    },
                  );
                }).toList(),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                textInputAction: TextInputAction.done,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  labelText: 'Amount',
                  prefixText: currencyInputPrefix(context),
                ),
                validator: (value) {
                  final amount = double.tryParse(value?.trim() ?? '');
                  if (amount == null || amount < 1) {
                    return 'Enter an amount of at least ${formatCurrency(context, 1)}';
                  }
                  if (amount > 100000) {
                    return 'Maximum recharge is ${formatCurrency(context, 100000)}';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: const Text('Cancel'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton.icon(
                      icon: const Icon(Icons.add_card_rounded),
                      label: const Text('Proceed'),
                      onPressed: () {
                        FocusScope.of(context).unfocus();
                        if (!_formKey.currentState!.validate()) {
                          return;
                        }
                        final parsedAmount = double.tryParse(
                          _amountController.text.trim(),
                        );
                        if (parsedAmount != null && parsedAmount > 0) {
                          Navigator.of(context).pop(parsedAmount);
                        }
                      },
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
