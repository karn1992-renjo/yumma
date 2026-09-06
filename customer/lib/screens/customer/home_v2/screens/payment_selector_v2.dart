import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../config/api_constants.dart';
import '../../../../config/app_config.dart';
import '../../../../providers/auth_provider.dart';
import '../../../../services/api_service.dart';
import '../../../../utils/currency_utils.dart';
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class V2PaymentChoice {
  const V2PaymentChoice({
    required this.method,
    this.gateway,
    this.mode,
    required this.label,
  });

  /// 'cod' | 'wallet' | 'online'
  final String method;

  /// gateway key when [method] == 'online' ('razorpay' | 'stripe' | 'cashfree')
  final String? gateway;

  /// online sub-mode passed to the gateway ('upi' | 'card' | 'netbanking' | ...)
  final String? mode;

  final String label;

  bool sameAs(V2PaymentChoice? o) =>
      o != null &&
      o.method == method &&
      o.gateway == gateway &&
      o.mode == mode;
}

/// Full-screen payment method picker — mirrors the production sheet's options
/// (COD, app wallet, and per-gateway online modes: UPI / card / netbanking / …).
class PaymentSelectorV2 extends StatefulWidget {
  const PaymentSelectorV2({super.key, this.selected, this.total = 0});

  final V2PaymentChoice? selected;
  final double total;

  @override
  State<PaymentSelectorV2> createState() => _PaymentSelectorV2State();
}

class _PaymentSelectorV2State extends State<PaymentSelectorV2> {
  double _walletBalance = 0;
  bool _walletLoaded = false;

  static const _gatewayLabel = <String, String>{
    'razorpay': 'Razorpay',
    'stripe': 'Stripe',
    'cashfree': 'Cashfree',
  };

  // Online sub-modes per gateway, matching the V1 checkout sheet.
  static const _modes = <String, List<(String, String, IconData)>>{
    'stripe': [
      ('card', 'Credit / Debit Card', Icons.credit_card_rounded),
      ('google_pay', 'Google Pay', Icons.account_balance_wallet_rounded),
    ],
    'cashfree': [
      ('upi', 'UPI', Icons.qr_code_2_rounded),
      ('card', 'Credit / Debit Card', Icons.credit_card_rounded),
      ('wallet', 'Online wallets', Icons.account_balance_wallet_rounded),
      ('netbanking', 'Net banking', Icons.account_balance_rounded),
    ],
    'razorpay': [
      ('upi', 'UPI', Icons.qr_code_2_rounded),
      ('card', 'Credit / Debit Card', Icons.credit_card_rounded),
      ('wallet', 'Online wallets', Icons.account_balance_wallet_rounded),
      ('netbanking', 'Net banking', Icons.account_balance_rounded),
    ],
  };

  @override
  void initState() {
    super.initState();
    _loadWallet();
  }

  Future<void> _loadWallet() async {
    try {
      final res = await ApiService().get(ApiConstants.wallet);
      final data = res is Map ? res['data'] : null;
      final wallet = data is Map ? data['wallet'] : null;
      final bal = wallet is Map
          ? wallet['balance']
          : (data is Map ? data['balance'] : null);
      _walletBalance = v2Double(bal);
    } catch (_) {}
    if (mounted) setState(() => _walletLoaded = true);
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final user = context.read<AuthProvider>().currentUser;
    final gateways = user?.enabledPaymentGatewayKeys ?? const ['razorpay'];
    final codEnabled = user?.isCodEnabled ?? true;
    final walletCovers =
        _walletLoaded && widget.total > 0 && _walletBalance >= widget.total;

    return V2Scaffold(
      title: 'Payment method',
      showBack: true,
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 34),
        children: [
          if (codEnabled) ...[
            _label(context, 'PAY ON DELIVERY'),
            _row(
              context,
              icon: Icons.payments_rounded,
              title: 'Cash on delivery',
              subtitle: 'Pay when your order arrives',
              active: widget.selected?.method == 'cod',
              onTap: () => Navigator.of(context).pop(const V2PaymentChoice(
                  method: 'cod', label: 'Cash on delivery')),
            ),
            const SizedBox(height: 16),
          ],
          _label(context, AppConfig.walletMoneyLabel.toUpperCase()),
          _row(
            context,
            icon: Icons.account_balance_wallet_rounded,
            title: AppConfig.walletMoneyLabel,
            subtitle: !_walletLoaded
                ? 'Checking balance…'
                : walletCovers
                    ? 'Balance ${formatCurrency(context, _walletBalance)} — no gateway needed'
                    : 'Balance ${formatCurrency(context, _walletBalance)} is below the bill',
            active: widget.selected?.method == 'wallet',
            enabled: walletCovers,
            onTap: () => Navigator.of(context).pop(V2PaymentChoice(
                method: 'wallet', label: AppConfig.walletMoneyLabel)),
          ),
          const SizedBox(height: 16),
          _label(context, 'PAY ONLINE'),
          for (final g in gateways) ...[
            Padding(
              padding: const EdgeInsets.only(left: 4, top: 4, bottom: 6),
              child: Text(_gatewayLabel[g] ?? g,
                  style: TextStyle(
                      color: p.inkFaint,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700)),
            ),
            for (final m in (_modes[g] ?? _modes['razorpay']!))
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _row(
                  context,
                  icon: m.$3,
                  title: m.$2,
                  subtitle: 'via ${_gatewayLabel[g] ?? g}',
                  active: widget.selected?.method == 'online' &&
                      widget.selected?.gateway == g &&
                      widget.selected?.mode == m.$1,
                  onTap: () => Navigator.of(context).pop(V2PaymentChoice(
                        method: 'online',
                        gateway: g,
                        mode: m.$1,
                        label: '${m.$2} · ${_gatewayLabel[g] ?? g}',
                      )),
                ),
              ),
          ],
          if (gateways.isEmpty && !codEnabled && !walletCovers)
            const V2EmptyState(
              icon: Icons.credit_card_off_rounded,
              title: 'No payment methods available',
            ),
        ],
      ),
    );
  }

  Widget _label(BuildContext context, String t) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 8),
      child: Text(t,
          style: TextStyle(
              color: p.inkFaint,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.8)),
    );
  }

  Widget _row(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required bool active,
    required VoidCallback onTap,
    bool enabled = true,
  }) {
    final p = V2Theme.of(context);
    return Opacity(
      opacity: enabled ? 1 : 0.5,
      child: V2Tappable(
        onTap: enabled ? onTap : null,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [p.glassStrongTop, p.glassStrongBottom],
            ),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: active ? p.accent : p.glassBorder,
              width: active ? 1.6 : 1,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: p.accent.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, size: 20, color: p.accent),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title,
                        style: TextStyle(
                            color: p.ink,
                            fontSize: 14,
                            fontWeight: FontWeight.w800)),
                    const SizedBox(height: 2),
                    Text(subtitle,
                        style:
                            TextStyle(color: p.inkFaint, fontSize: 11.5)),
                  ],
                ),
              ),
              Icon(
                active
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_off_rounded,
                color: active ? p.accent : p.inkFaint,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
