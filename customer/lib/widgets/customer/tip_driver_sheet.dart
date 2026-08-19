import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../models/order.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/wallet_recharge_payment_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

/// Shows a "Tip your delivery partner" bottom sheet for a delivered order.
/// Returns the updated [Order] (with `tip`/`tipPaidAt` set) on success, or
/// null if the customer dismissed it without tipping.
Future<Order?> showTipDriverSheet(
  BuildContext context, {
  required Order order,
}) {
  return showModalBottomSheet<Order>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => _TipDriverSheet(order: order),
  );
}

class _TipDriverSheet extends StatefulWidget {
  const _TipDriverSheet({required this.order});

  final Order order;

  @override
  State<_TipDriverSheet> createState() => _TipDriverSheetState();
}

class _TipDriverSheetState extends State<_TipDriverSheet> {
  static const _presets = [10, 20, 30, 50];

  final ApiService _api = ApiService();
  final TextEditingController _amountController =
      TextEditingController(text: '${_presets[1]}');
  late final WalletRechargePaymentService _paymentService;

  int? _selectedPreset = _presets[1];
  double _walletBalance = 0;
  bool _loadingBalance = true;
  bool _isSubmitting = false;
  bool _isToppingUp = false;
  String? _error;
  double? _pendingShortfall;

  @override
  void initState() {
    super.initState();
    _paymentService = WalletRechargePaymentService(
      onSuccess: () async {
        if (!mounted) return;
        setState(() => _isToppingUp = false);
        await _loadBalance();
        await _submitTip();
      },
      onFailure: (message) {
        if (!mounted) return;
        setState(() {
          _isToppingUp = false;
          _error = message;
        });
      },
    );
    _loadBalance();
  }

  @override
  void dispose() {
    _paymentService.dispose();
    _amountController.dispose();
    super.dispose();
  }

  double get _amount => double.tryParse(_amountController.text.trim()) ?? 0;

  Future<void> _loadBalance() async {
    try {
      final response = await _api.get(ApiConstants.wallet);
      if (!mounted) return;
      final data = response['data'];
      final wallet = data is Map ? data['wallet'] : null;
      setState(() {
        _walletBalance =
            double.tryParse('${wallet is Map ? wallet['balance'] ?? 0 : 0}') ??
                0;
        _loadingBalance = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loadingBalance = false);
    }
  }

  void _selectPreset(int amount) {
    setState(() {
      _selectedPreset = amount;
      _amountController.text = '$amount';
      _error = null;
      _pendingShortfall = null;
    });
  }

  Future<void> _submitTip() async {
    if (_amount < 1 || _isSubmitting || _isToppingUp) return;

    setState(() {
      _isSubmitting = true;
      _error = null;
      _pendingShortfall = null;
    });

    try {
      final response = await _api.post(
        ApiConstants.orderTip(widget.order.id),
        data: {'amount': _amount},
      );
      if (!mounted) return;

      if (response['success'] == true && response['data'] is Map) {
        final updated = Order.fromJson(
          Map<String, dynamic>.from(response['data'] as Map),
        );
        Navigator.of(context).pop(updated);
        return;
      }

      final data = response['data'];
      final shortfall =
          data is Map ? double.tryParse('${data['shortfall'] ?? ''}') : null;
      setState(() {
        _error = response['message']?.toString() ?? 'Unable to add tip';
        _pendingShortfall = shortfall;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = _cleanError(e.toString()));
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Future<void> _topUpAndRetry() async {
    final user = context.read<AuthProvider>().currentUser;
    final topUpAmount = (_pendingShortfall ?? _amount);
    setState(() {
      _isToppingUp = true;
      _error = null;
    });
    try {
      await _paymentService.start(
        amount: topUpAmount < 1 ? _amount : topUpAmount,
        user: user,
      );
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isToppingUp = false;
        _error = _cleanError(e.toString());
      });
    }
  }

  String _cleanError(String message) {
    final trimmed = message.trim();
    return trimmed.startsWith('Exception: ')
        ? trimmed.substring('Exception: '.length)
        : trimmed;
  }

  bool get _showTopUpAction => _pendingShortfall != null && _pendingShortfall! > 0;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    final driverName = widget.order.driver?.name ?? 'your delivery partner';
    final busy = _isSubmitting || _isToppingUp;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SafeArea(
        top: false,
        child: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 42,
                    height: 5,
                    decoration: BoxDecoration(
                      color: const Color(0xFFDCE1EA),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: const Color(0xFFEFF9F2),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: const Icon(
                        Icons.volunteer_activism_rounded,
                        color: Color(0xFF0E8F45),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Tip your delivery partner',
                            style: TextStyle(
                              color: FoodFlowTheme.ink,
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            '100% of your tip goes to $driverName',
                            style: const TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 12.5,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                    TextButton(
                      onPressed: busy ? null : () => Navigator.of(context).pop(),
                      child: const Text('Skip'),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: _presets.map((amount) {
                    final selected = _selectedPreset == amount;
                    return InkWell(
                      onTap: busy ? null : () => _selectPreset(amount),
                      borderRadius: BorderRadius.circular(14),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 20,
                          vertical: 14,
                        ),
                        decoration: BoxDecoration(
                          color: selected
                              ? const Color(0xFFEFF9F2)
                              : const Color(0xFFF7F8FC),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: selected
                                ? const Color(0xFF68B98A)
                                : const Color(0xFFE4E8F0),
                          ),
                        ),
                        child: Text(
                          formatCurrency(context, amount.toDouble()),
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: selected
                                ? const Color(0xFF0E8F45)
                                : FoodFlowTheme.inkSoft,
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ),
                const SizedBox(height: 14),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF7F8FC),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE4E8F0)),
                  ),
                  child: TextField(
                    controller: _amountController,
                    enabled: !busy,
                    keyboardType:
                        const TextInputType.numberWithOptions(decimal: true),
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: FoodFlowTheme.ink,
                    ),
                    decoration: InputDecoration(
                      prefixText: currencyInputPrefix(context),
                      border: InputBorder.none,
                      hintText: 'Enter custom amount',
                    ),
                    onChanged: (_) => setState(() {
                      _selectedPreset = null;
                      _error = null;
                      _pendingShortfall = null;
                    }),
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  _loadingBalance
                      ? 'Checking your wallet balance...'
                      : 'Wallet balance: ${formatCurrency(context, _walletBalance)}',
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: const TextStyle(
                      color: FoodFlowTheme.danger,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: busy || _amount < 1
                        ? null
                        : (_showTopUpAction ? _topUpAndRetry : _submitTip),
                    style: FoodFlowTheme.zomatoPrimaryButton(radius: 16),
                    child: busy
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : Text(
                            _showTopUpAction
                                ? 'Add money & pay tip'
                                : 'Pay ${formatCurrency(context, _amount)} tip',
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
