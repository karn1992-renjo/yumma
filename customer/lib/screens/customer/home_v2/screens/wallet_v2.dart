import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../config/api_constants.dart';
import '../../../../providers/auth_provider.dart';
import '../../../../services/api_service.dart';
import '../../../../services/wallet_recharge_payment_service.dart';
import '../../../../utils/currency_utils.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class WalletV2 extends StatefulWidget {
  const WalletV2({super.key});

  @override
  State<WalletV2> createState() => _WalletV2State();
}

class _WalletV2State extends State<WalletV2> {
  final ApiService _api = ApiService();
  WalletRechargePaymentService? _payment;

  bool _loading = true;
  bool _recharging = false;
  double _balance = 0;
  List<Map<String, dynamic>> _transactions = const [];

  @override
  void initState() {
    super.initState();
    _payment = WalletRechargePaymentService(
      onSuccess: () async {
        if (mounted) {
          setState(() => _recharging = false);
          _toast('Wallet topped up');
        }
        await _load();
      },
      onFailure: (m) {
        if (mounted) {
          setState(() => _recharging = false);
          _toast(m.isEmpty ? 'Payment failed' : m);
        }
      },
    );
    _load();
  }

  @override
  void dispose() {
    _payment?.dispose();
    super.dispose();
  }

  void _toast(String m) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(m)));
  }

  Future<void> _load() async {
    if (mounted && _transactions.isEmpty) setState(() => _loading = true);
    try {
      final res = await _api.get(ApiConstants.wallet);
      if (res is Map && res['data'] is Map) {
        final data = res['data'] as Map;
        final w = data['wallet'];
        _balance =
            double.tryParse('${w is Map ? w['balance'] ?? 0 : 0}') ?? 0;
        _transactions = data['transactions'] is List
            ? (data['transactions'] as List)
                .whereType<Map>()
                .map((e) => Map<String, dynamic>.from(e))
                .toList()
            : const [];
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _addMoney() async {
    final amount = await _askAmount();
    if (amount == null || amount <= 0) return;
    setState(() => _recharging = true);
    try {
      final user = context.read<AuthProvider>().currentUser;
      await _payment!.start(amount: amount, user: user);
    } catch (e) {
      if (mounted) {
        setState(() => _recharging = false);
        _toast(e.toString().replaceFirst('Exception: ', ''));
      }
    }
  }

  Future<double?> _askAmount() {
    final p = V2Theme.of(context);
    final controller = TextEditingController();
    return showModalBottomSheet<double>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => V2Theme(
        palette: p,
        child: Padding(
          padding: EdgeInsets.only(
              bottom: MediaQuery.of(context).viewInsets.bottom),
          child: Container(
            margin: const EdgeInsets.all(12),
            padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
            decoration: BoxDecoration(
              color: p.isDark ? const Color(0xFF141A29) : Colors.white,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: p.glassBorder),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Add money to wallet',
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 16,
                        fontWeight: FontWeight.w900)),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  children: [100, 200, 500, 1000]
                      .map((a) => V2Tappable(
                            onTap: () => Navigator.of(context).pop(a.toDouble()),
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 14, vertical: 9),
                              decoration: BoxDecoration(
                                color: p.accent.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(
                                    color: p.accent.withOpacity(0.4)),
                              ),
                              child: Text('+$a',
                                  style: TextStyle(
                                      color: p.accent,
                                      fontWeight: FontWeight.w800)),
                            ),
                          ))
                      .toList(),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: controller,
                  keyboardType: TextInputType.number,
                  autofocus: true,
                  keyboardAppearance:
                      p.isDark ? Brightness.dark : Brightness.light,
                  style: TextStyle(
                      color: p.ink, fontSize: 15, fontWeight: FontWeight.w700),
                  cursorColor: p.accent,
                  decoration: InputDecoration(
                    prefixText: '${currencyInputPrefix(context)} ',
                    prefixStyle: TextStyle(color: p.inkSoft),
                    hintText: 'Enter amount',
                    hintStyle: TextStyle(color: p.inkFaint),
                    filled: true,
                    fillColor:
                        p.isDark ? const Color(0xFF1B2233) : const Color(0xFFF3F4F6),
                    border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: BorderSide.none),
                  ),
                ),
                const SizedBox(height: 14),
                V2Tappable(
                  onTap: () {
                    final v = double.tryParse(controller.text.trim());
                    Navigator.of(context).pop(v);
                  },
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: p.accent,
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: const Text('Proceed to pay',
                        style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                            fontSize: 14)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      title: 'Wallet',
      showBack: true,
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : RefreshIndicator(
              onRefresh: _load,
              color: p.accent,
              backgroundColor: p.bgMid,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 34),
                children: [
                  V2Entrance(
                    child: GlassPanel(
                      radius: 24,
                      strong: true,
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Available balance',
                              style: TextStyle(
                                  color: p.inkFaint, fontSize: 12.5)),
                          const SizedBox(height: 6),
                          Text(
                            formatCurrency(context, _balance),
                            style: TextStyle(
                                color: p.ink,
                                fontSize: 32,
                                fontWeight: FontWeight.w900),
                          ),
                          const SizedBox(height: 16),
                          V2Tappable(
                            onTap: _recharging ? null : _addMoney,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 20, vertical: 12),
                              decoration: BoxDecoration(
                                color: p.accent,
                                borderRadius: BorderRadius.circular(13),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  if (_recharging)
                                    const SizedBox(
                                      width: 16,
                                      height: 16,
                                      child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          color: Colors.white),
                                    )
                                  else
                                    const Icon(Icons.add_rounded,
                                        color: Colors.white, size: 18),
                                  const SizedBox(width: 6),
                                  const Text('Add money',
                                      style: TextStyle(
                                          color: Colors.white,
                                          fontWeight: FontWeight.w900,
                                          fontSize: 13.5)),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Padding(
                    padding: const EdgeInsets.only(left: 4, bottom: 8),
                    child: Text('TRANSACTIONS',
                        style: TextStyle(
                            color: p.inkFaint,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.8)),
                  ),
                  if (_transactions.isEmpty)
                    GlassPanel(
                      radius: 16,
                      padding: const EdgeInsets.all(20),
                      child: Text('No transactions yet',
                          style: TextStyle(color: p.inkFaint, fontSize: 12.5)),
                    )
                  else
                    GlassPanel(
                      radius: 18,
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Column(
                        children: [
                          for (var i = 0; i < _transactions.length; i++) ...[
                            if (i > 0)
                              Divider(height: 1, color: p.glassBorder),
                            _txRow(context, _transactions[i]),
                          ],
                        ],
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _txRow(BuildContext context, Map<String, dynamic> t) {
    final p = V2Theme.of(context);
    final amount = double.tryParse('${t['amount'] ?? 0}') ?? 0;
    final credit = (t['type'] ?? t['direction'] ?? '')
            .toString()
            .toLowerCase()
            .contains('credit') ||
        amount > 0 &&
            !(t['type'] ?? '').toString().toLowerCase().contains('debit');
    final title =
        (t['description'] ?? t['title'] ?? t['reason'] ?? 'Transaction')
            .toString();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          Icon(
            credit
                ? Icons.south_west_rounded
                : Icons.north_east_rounded,
            size: 18,
            color: credit ? p.positive : p.danger,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: p.ink, fontSize: 13)),
          ),
          Text(
            '${credit ? '+' : '-'}${formatCurrency(context, amount.abs())}',
            style: TextStyle(
                color: credit ? p.positive : p.danger,
                fontWeight: FontWeight.w800,
                fontSize: 13),
          ),
        ],
      ),
    );
  }
}
