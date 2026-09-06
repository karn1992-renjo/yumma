import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/wallet_recharge_payment_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/aurora/aurora.dart';

/// Lets a per-order-incentive rider deposit the COD cash they're holding so
/// their account is unblocked for new orders. Reuses the wallet-recharge
/// gateway flow with `purpose: cod_settlement`.
class DriverCodDepositScreen extends StatefulWidget {
  const DriverCodDepositScreen({
    super.key,
    required this.amountDue,
    required this.limit,
  });

  final double amountDue;
  final double limit;

  @override
  State<DriverCodDepositScreen> createState() => _DriverCodDepositScreenState();
}

class _DriverCodDepositScreenState extends State<DriverCodDepositScreen> {
  final _api = ApiService();
  final _amountController = TextEditingController();
  late final WalletRechargePaymentService _payment;
  bool _busy = false;
  double _amountDue = 0;

  @override
  void initState() {
    super.initState();
    _amountDue = widget.amountDue;
    _amountController.text = widget.amountDue > 0
        ? widget.amountDue.toStringAsFixed(0)
        : '';
    _payment = WalletRechargePaymentService(
      onSuccess: () async {
        if (!mounted) return;
        setState(() => _busy = false);
        ScaffoldMessenger.of(context)
          ..clearSnackBars()
          ..showSnackBar(const SnackBar(
              content: Text('Cash deposit received. You can receive orders again.')));
        // Pull the fresh profile so the dashboard clears the block.
        await context.read<AuthProvider>().refreshUserFromServer();
        if (mounted) Navigator.pop(context, true);
      },
      onFailure: (message) {
        if (!mounted) return;
        setState(() => _busy = false);
        ScaffoldMessenger.of(context)
          ..clearSnackBars()
          ..showSnackBar(SnackBar(
            content: Text(message),
            backgroundColor: foodflow.danger,
          ));
      },
    );
  }

  @override
  void dispose() {
    _payment.dispose();
    _amountController.dispose();
    super.dispose();
  }

  Future<void> _deposit() async {
    final amount = double.tryParse(_amountController.text.trim()) ?? 0;
    if (amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter an amount to deposit')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await _payment.start(
        amount: amount,
        user: context.read<AuthProvider>().currentUser,
        purpose: 'cod_settlement',
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$e'), backgroundColor: foodflow.danger),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return AuroraScaffold(
      appBar: GlassAppBar(
        leading: const BackButton(),
        title: Text(
          'Deposit COD cash',
          style: TextStyle(
            color: foodflow.ink,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              GlassCard(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: foodflow.orange.withOpacity(0.14),
                            borderRadius: BorderRadius.circular(13),
                          ),
                          child: Icon(Icons.account_balance_wallet_rounded,
                              color: foodflow.orange),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            widget.limit > 0
                                ? 'New orders are paused'
                                : 'Deposit your COD cash',
                            style: TextStyle(
                              color: foodflow.ink,
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      widget.limit > 0
                          ? "You're holding ${formatCurrency(context, _amountDue)} in "
                              'undeposited cash. The limit is '
                              '${formatCurrency(context, widget.limit)}. Deposit it '
                              'online to keep receiving deliveries.'
                          : "You're holding ${formatCurrency(context, _amountDue)} in "
                              'undeposited COD cash. Deposit it online to clear '
                              'your balance.',
                      style: TextStyle(color: foodflow.muted, fontSize: 13),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Amount',
                style: TextStyle(
                  color: foodflow.ink,
                  fontWeight: FontWeight.w800,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _amountController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                ],
                decoration: InputDecoration(
                  prefixText: '${getCurrencySymbol(context)} ',
                  hintText: _amountDue.toStringAsFixed(0),
                ),
              ),
              const SizedBox(height: 16),
              GlassButton(
                label: _busy ? 'Opening payment…' : 'Deposit now',
                loading: _busy,
                onPressed: _busy ? null : _deposit,
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: _busy
                    ? null
                    : () => Navigator.pushNamed(context, '/driver/support'),
                icon: const Icon(Icons.support_agent_rounded, size: 18),
                label: const Text('Raise a ticket instead'),
              ),
              const SizedBox(height: 8),
              Text(
                'Deposited cash is applied to your pending COD orders and clears '
                'the block immediately.',
                style: TextStyle(color: foodflow.faint, fontSize: 11.5),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
