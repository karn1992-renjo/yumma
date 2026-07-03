import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/wallet_recharge_payment_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/customer/account_chrome.dart';

class WalletScreen extends StatefulWidget {
  const WalletScreen({super.key});

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  final ApiService _api = ApiService();
  late final WalletRechargePaymentService _paymentService;
  bool _isLoading = true;
  bool _isRecharging = false;
  double _balance = 0;
  List<dynamic> _transactions = [];
  String _selectedFilter = 'all';
  String? _loadError;

  @override
  void initState() {
    super.initState();
    _paymentService = WalletRechargePaymentService(
      onSuccess: () async {
        if (!mounted) return;
        setState(() => _isRecharging = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Wallet recharged successfully')),
        );
        await _loadWallet();
      },
      onFailure: (message) {
        if (!mounted) return;
        setState(() => _isRecharging = false);
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
    setState(() {
      _isLoading = true;
      _loadError = null;
    });
    try {
      final response = await _api.get(ApiConstants.wallet);
      if (response['success'] == true) {
        final data = response['data'] ?? {};
        final wallet = data['wallet'] ?? {};
        setState(() {
          _balance = double.tryParse('${wallet['balance'] ?? 0}') ?? 0;
          _transactions = data['transactions'] ?? [];
          _loadError = null;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _loadError = _cleanError(e));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String _cleanError(Object error) {
    final message = error.toString().trim();
    if (message.startsWith('Exception: ')) {
      return message.substring('Exception: '.length);
    }
    return message.isEmpty
        ? 'Please check your internet connection and try again.'
        : message;
  }

  Future<void> _showRechargeSheet() async {
    final amount = await Navigator.of(context).push<double>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => const _WalletRechargePage(),
      ),
    );

    if (amount != null && mounted) {
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        if (mounted) {
          await _startRecharge(amount);
        }
      });
    }
  }

  Future<void> _showGiftCardDialog() async {
    final redeemed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _GiftCardClaimSheet(),
    );

    if (redeemed == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Gift card redeemed successfully')),
      );
      await _loadWallet();
    }
  }

  Future<void> _startRecharge(double amount) async {
    final user = context.read<AuthProvider>().currentUser;
    setState(() => _isRecharging = true);
    try {
      await _paymentService.start(amount: amount, user: user);
    } catch (e) {
      if (!mounted) return;
      setState(() => _isRecharging = false);
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
      return 'Payment cancelled';
    }

    final normalized = trimmed.toLowerCase();
    if (normalized == 'null' ||
        normalized == 'undefined' ||
        normalized == 'exception: null' ||
        normalized == 'exception: undefined') {
      return 'Payment cancelled';
    }

    return trimmed.startsWith('Exception: ')
        ? trimmed.substring('Exception: '.length)
        : trimmed;
  }

  bool _matchesFilter(dynamic transaction) {
    if (_selectedFilter == 'all') return true;
    final type = '${transaction['type'] ?? ''}'.toLowerCase();
    final description = '${transaction['description'] ?? ''}'.toLowerCase();
    return switch (_selectedFilter) {
      'additions' => type.contains('credit') ||
          type.contains('topup') ||
          type.contains('addition'),
      'deductions' => type.contains('debit') ||
          type.contains('payment') ||
          type.contains('deduction'),
      'refunds' => type.contains('refund') || description.contains('refund'),
      'expired' => type.contains('expire') || description.contains('expire'),
      _ => true,
    };
  }

  List<dynamic> get _filteredTransactions =>
      _transactions.where(_matchesFilter).toList(growable: false);

  @override
  Widget build(BuildContext context) {
    final transactions = _filteredTransactions;

    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        backgroundColor: accountCanvas,
        elevation: 0,
        foregroundColor: FoodFlowTheme.ink,
        title: Text(
          AppConfig.walletMoneyLabel,
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _loadError != null
              ? NetworkErrorView(
                  message: _loadError,
                  onRetry: _loadWallet,
                )
          : RefreshIndicator(
              onRefresh: _loadWallet,
              child: ListView(
                padding: EdgeInsets.zero,
                children: [
                  const AccountHeroCard(
                    title: 'Wallet & balance',
                    subtitle:
                        'Track your balance, add money, and review every movement from one clean account space.',
                    icon: Icons.account_balance_wallet_outlined,
                    badge: 'PROFILE SPACE',
                  ),
                  const Padding(
                    padding: EdgeInsets.fromLTRB(16, 0, 16, 14),
                    child: AccountSectionTitle(title: 'BALANCE'),
                  ),
                  AccountSurfaceCard(
                    margin: const EdgeInsets.fromLTRB(16, 0, 16, 0),
                    padding: const EdgeInsets.fromLTRB(16, 24, 16, 16),
                    child: Column(
                      children: [
                        _WalletHero(balance: _balance),
                        const SizedBox(height: 12),
                        const Text(
                          'Add money to enjoy one-tap, seamless payments',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        const SizedBox(height: 18),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: _isRecharging ? null : _showRechargeSheet,
                            style: FoodFlowTheme.zomatoPrimaryButton(),
                            child: _isRecharging
                                ? const SizedBox(
                                    height: 18,
                                    width: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text(
                                    'Add money',
                                    style: TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                          ),
                        ),
                        const SizedBox(height: 10),
                        SizedBox(
                          width: double.infinity,
                          child: OutlinedButton.icon(
                            onPressed:
                                _isRecharging ? null : _showGiftCardDialog,
                            icon: const Icon(Icons.card_giftcard_rounded),
                            label: const Text(
                              'Claim gift card',
                              style: TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 28),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16),
                    child: AccountSectionTitle(title: 'TRANSACTION HISTORY'),
                  ),
                  const SizedBox(height: 14),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        _buildFilterChip('all', 'All Transactions'),
                        _buildFilterChip('additions', 'Additions'),
                        _buildFilterChip('deductions', 'Deductions'),
                        _buildFilterChip('refunds', 'Refunds'),
                        _buildFilterChip('expired', 'Expired'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 26),
                  if (transactions.isEmpty)
                    const _WalletEmptyState()
                  else
                    ...transactions.map(
                      (transaction) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _WalletTransactionTile(transaction: transaction),
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _buildFilterChip(String key, String label) {
    final selected = _selectedFilter == key;
    return Padding(
      padding: const EdgeInsets.only(right: 10),
      child: InkWell(
        onTap: () => setState(() => _selectedFilter = key),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: selected ? const Color(0xFFEFF9F2) : Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected ? const Color(0xFF68B98A) : const Color(0xFFE3E6EE),
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? const Color(0xFF0E8F45) : FoodFlowTheme.ink,
              fontWeight: FontWeight.w700,
              fontSize: 13,
            ),
          ),
        ),
      ),
    );
  }
}

class _WalletHero extends StatelessWidget {
  final double balance;

  const _WalletHero({required this.balance});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 92,
          height: 92,
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFFFF6C86), Color(0xFFE83F5B)],
            ),
            borderRadius: BorderRadius.circular(28),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFFE83F5B).withOpacity(0.28),
                blurRadius: 20,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: const Center(
            child: Text(
              '₹',
              style: TextStyle(
                color: Colors.white,
                fontSize: 40,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          formatCurrencyWithDecimals(context, balance, decimals: 2),
          style: const TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 30,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}

class _WalletEmptyState extends StatelessWidget {
  const _WalletEmptyState();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const SizedBox(height: 20),
        ...List.generate(
          3,
          (_) => Container(
            width: 160,
            height: 40,
            margin: const EdgeInsets.only(bottom: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFFD9DEE9)),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Row(
                children: [
                  Container(
                    width: 16,
                    height: 16,
                    decoration: BoxDecoration(
                      color: const Color(0xFFDDE2EC),
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 70,
                          height: 6,
                          decoration: BoxDecoration(
                            color: const Color(0xFFE3E7F0),
                            borderRadius: BorderRadius.circular(999),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Container(
                          width: 46,
                          height: 6,
                          decoration: BoxDecoration(
                            color: const Color(0xFFE9ECF4),
                            borderRadius: BorderRadius.circular(999),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 18),
        const Text(
          'Your transactions will appear here',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w700,
            color: FoodFlowTheme.ink,
          ),
        ),
      ],
    );
  }
}

class _WalletTransactionTile extends StatelessWidget {
  final dynamic transaction;

  const _WalletTransactionTile({required this.transaction});

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final amount = double.tryParse('${transaction['amount'] ?? 0}') ?? 0;
    final type = '${transaction['type'] ?? ''}'.toLowerCase();
    final isCredit = type.contains('credit') ||
        type.contains('topup') ||
        type.contains('refund');

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE7EAF0)),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: (isCredit
                      ? const Color(0xFFEFF9F2)
                      : const Color(0xFFFFF0EC)),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Icon(
              isCredit ? Icons.south_west_rounded : Icons.north_east_rounded,
              color: isCredit ? const Color(0xFF0E8F45) : primary,
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
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  type.replaceAll('_', ' '),
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: FoodFlowTheme.muted,
                  ),
                ),
              ],
            ),
          ),
          Text(
            '${isCredit ? '+' : '-'} ${formatCurrency(context, amount)}',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: isCredit ? const Color(0xFF0E8F45) : primary,
            ),
          ),
        ],
      ),
    );
  }
}

class _GiftCardClaimSheet extends StatefulWidget {
  const _GiftCardClaimSheet();

  @override
  State<_GiftCardClaimSheet> createState() => _GiftCardClaimSheetState();
}

class _GiftCardClaimSheetState extends State<_GiftCardClaimSheet> {
  final _formKey = GlobalKey<FormState>();
  final _codeController = TextEditingController();
  final _api = ApiService();
  bool _isSubmitting = false;

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _redeem() async {
    if (!_formKey.currentState!.validate()) return;

    FocusScope.of(context).unfocus();
    setState(() => _isSubmitting = true);

    try {
      final response = await _api.post(
        ApiConstants.walletGiftCardRedeem,
        data: {'code': _codeController.text.trim().toUpperCase()},
      );

      if (!mounted) return;
      if (response['success'] == true) {
        Navigator.of(context).pop(true);
        return;
      }

      _showError(response['message']?.toString() ?? 'Unable to redeem code');
    } catch (e) {
      if (!mounted) return;
      _showError(_cleanError(e.toString()));
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: FoodFlowTheme.danger,
      ),
    );
  }

  String _cleanError(String message) {
    final trimmed = message.trim();
    return trimmed.startsWith('Exception: ')
        ? trimmed.substring('Exception: '.length)
        : trimmed;
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SafeArea(
        top: false,
        child: Container(
          decoration: const BoxDecoration(
            color: accountCanvas,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 18),
            child: Form(
              key: _formKey,
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
                          color: const Color(0xFFFFF0EC),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Icon(
                          Icons.card_giftcard_rounded,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                      ),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Claim gift card',
                              style: TextStyle(
                                color: FoodFlowTheme.ink,
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            SizedBox(height: 3),
                            Text(
                              'Add the gift value to your wallet.',
                              style: TextStyle(
                                color: FoodFlowTheme.muted,
                                fontSize: 13,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 22),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: const Color(0xFFE4E8F0)),
                    ),
                    child: TextFormField(
                      controller: _codeController,
                      textCapitalization: TextCapitalization.characters,
                      keyboardType: TextInputType.visiblePassword,
                      textInputAction: TextInputAction.done,
                      enabled: !_isSubmitting,
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                      decoration: const InputDecoration(
                        border: InputBorder.none,
                        labelText: 'Gift card code',
                        labelStyle: TextStyle(color: FoodFlowTheme.muted),
                        hintText: 'GC-XXXXXXXX',
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Enter a gift card code';
                        }
                        if (value.trim().length < 4) {
                          return 'Enter a valid gift card code';
                        }
                        return null;
                      },
                      onFieldSubmitted: (_) => _isSubmitting ? null : _redeem(),
                    ),
                  ),
                  const SizedBox(height: 14),
                  const _WalletNote(
                    text:
                        'Once claimed, the amount is added instantly to your wallet balance.',
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _isSubmitting ? null : _redeem,
                      style: FoodFlowTheme.zomatoPrimaryButton(radius: 16),
                      child: _isSubmitting
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text(
                              'Claim gift card',
                              style: TextStyle(
                                fontSize: 16,
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
      ),
    );
  }
}

class _WalletRechargePage extends StatelessWidget {
  const _WalletRechargePage();

  @override
  Widget build(BuildContext context) {
    return const _WalletRechargeForm();
  }
}

class _WalletRechargeForm extends StatefulWidget {
  const _WalletRechargeForm();

  @override
  State<_WalletRechargeForm> createState() => _WalletRechargeFormState();
}

class _WalletRechargeFormState extends State<_WalletRechargeForm> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController(text: '2000');
  final _presets = const [2000, 5000, 10000];
  bool _autoAddEnabled = false;

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  double get _currentAmount =>
      double.tryParse(_amountController.text.trim()) ?? 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        backgroundColor: accountCanvas,
        elevation: 0,
        foregroundColor: FoodFlowTheme.ink,
        title: const Text(
          'Add money',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 140),
          children: [
            const Text(
              'Enter amount',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: FoodFlowTheme.muted,
              ),
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFE4E8F0)),
              ),
              child: TextFormField(
                controller: _amountController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                  color: FoodFlowTheme.ink,
                ),
                decoration: InputDecoration(
                  prefixText: currencyInputPrefix(context),
                  border: InputBorder.none,
                ),
                onChanged: (_) => setState(() {}),
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
            ),
            const SizedBox(height: 14),
            Row(
              children: _presets.map((amount) {
                final selected = _amountController.text == '$amount';
                return Padding(
                  padding: const EdgeInsets.only(right: 12),
                  child: InkWell(
                    onTap: () {
                      _amountController.text = '$amount';
                      setState(() {});
                    },
                    borderRadius: BorderRadius.circular(14),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 28,
                        vertical: 14,
                      ),
                      decoration: BoxDecoration(
                        color: selected ? const Color(0xFFEFF9F2) : Colors.white,
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
                  ),
                );
              }).toList(),
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFE4E8F0)),
              ),
              child: Row(
                children: [
                  Checkbox(
                    value: _autoAddEnabled,
                    activeColor: const Color(0xFF0E8F45),
                    onChanged: (value) =>
                        setState(() => _autoAddEnabled = value ?? false),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Auto-add ${formatCurrency(context, _currentAmount <= 0 ? 2000 : _currentAmount)}',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                            color: FoodFlowTheme.ink,
                          ),
                        ),
                        const SizedBox(height: 4),
                        const Text(
                          'when balance goes below ₹500',
                          style: TextStyle(
                            fontSize: 12,
                            color: FoodFlowTheme.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 28),
            const Text(
              'ADD WITH GIFT CARD',
              style: TextStyle(
                letterSpacing: 2.2,
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: FoodFlowTheme.muted,
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
              ),
              child: const Row(
                children: [
                  Icon(Icons.card_giftcard_rounded, color: FoodFlowTheme.ink),
                  SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Claim a gift card',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                  ),
                  Icon(Icons.chevron_right_rounded, color: FoodFlowTheme.muted),
                ],
              ),
            ),
            const SizedBox(height: 28),
            const Text(
              'NOTE',
              style: TextStyle(
                letterSpacing: 2.2,
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: FoodFlowTheme.muted,
              ),
            ),
            const SizedBox(height: 14),
            const _WalletNote(text: 'Money added has an expiry of 4 years'),
            const _WalletNote(
              text:
                  'Balance can not be transferred to a bank account as per RBI guidelines',
            ),
            const _WalletNote(
              text:
                  'Wallet balance can be used exclusively on ${AppConfig.appName}.',
            ),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
          ),
          child: Row(
            children: [
              Expanded(
                child: Container(
                  height: 70,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF7F8FC),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'PAY USING',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: FoodFlowTheme.muted,
                        ),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'UPI / Cards / Wallets',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: FoodFlowTheme.ink,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: SizedBox(
                  height: 70,
                  child: ElevatedButton(
                    onPressed: () {
                      FocusScope.of(context).unfocus();
                      if (!_formKey.currentState!.validate()) return;
                      if (_currentAmount > 0) {
                        Navigator.of(context).pop(_currentAmount);
                      }
                    },
                    style: FoodFlowTheme.zomatoPrimaryButton(radius: 16),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          formatCurrencyWithDecimals(
                            context,
                            _currentAmount <= 0 ? 0 : _currentAmount,
                            decimals: 2,
                          ),
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 2),
                        const Text(
                          'Add money',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _WalletNote extends StatelessWidget {
  final String text;

  const _WalletNote({required this.text});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            '•',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: FoodFlowTheme.muted,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(
                fontSize: 13,
                height: 1.4,
                color: FoodFlowTheme.inkSoft,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
