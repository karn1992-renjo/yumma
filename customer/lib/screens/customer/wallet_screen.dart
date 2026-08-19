import 'package:flutter/material.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/wallet_recharge_payment_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/network_error_screen.dart';
import '../../widgets/common/app_skeleton.dart';
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
        await _loadWallet(forceRefresh: true);
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

  Future<void> _loadWallet({bool forceRefresh = false}) async {
    setState(() {
      _isLoading = _transactions.isEmpty && _balance == 0;
      _loadError = null;
    });
    try {
      final response = await _api.get(
        ApiConstants.wallet,
        cachePolicy: ApiCachePolicy.screen,
        cacheFirst: !forceRefresh,
        refreshCached: !forceRefresh,
        onCacheRefreshed: _applyWallet,
      );
      _applyWallet(response);
    } catch (e) {
      if (!mounted) return;
      setState(() => _loadError = _cleanError(e));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _applyWallet(dynamic response) {
    if (!mounted || response is! Map || response['success'] != true) return;
    final data = response['data'];
    if (data is! Map) return;
    final wallet = data['wallet'];
    setState(() {
      _balance = double.tryParse(
            '${wallet is Map ? wallet['balance'] ?? 0 : 0}',
          ) ??
          0;
      _transactions =
          data['transactions'] is List ? data['transactions'] as List : [];
      _loadError = null;
    });
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
      await _loadWallet(forceRefresh: true);
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
          ? const AppSkeletonListView(itemCount: 4, itemHeight: 116)
          : _loadError != null
              ? NetworkErrorView(
                  message: _loadError,
                  onRetry: _loadWallet,
                )
              : RefreshIndicator(
                  onRefresh: () => _loadWallet(forceRefresh: true),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(0, 0, 0, 24),
                    children: [
                      _PaytmBalanceCard(
                        balance: _balance,
                        isRecharging: _isRecharging,
                        onAddMoney: _showRechargeSheet,
                        onGiftCard: _showGiftCardDialog,
                      ),
                      const SizedBox(height: 26),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 16),
                        child:
                            AccountSectionTitle(title: 'PASSBOOK'),
                      ),
                      const SizedBox(height: 14),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: SingleChildScrollView(
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
                      ),
                      const SizedBox(height: 22),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: transactions.isEmpty
                            ? const _WalletEmptyState()
                            : Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children:
                                    _buildPassbookEntries(transactions),
                              ),
                      ),
                    ],
                  ),
                ),
    );
  }

  /// Interleaves day-grouped headers ("Today" / "Yesterday" / date) with
  /// transaction rows, Paytm passbook-style. Falls back to a single
  /// "Recent" bucket for any entries without a parseable date, so a data
  /// shape mismatch never breaks the list.
  List<Widget> _buildPassbookEntries(List<dynamic> transactions) {
    final widgets = <Widget>[];
    String? lastGroupKey;

    for (final transaction in transactions) {
      final date = DateTime.tryParse(
        transaction['created_at']?.toString() ?? '',
      )?.toLocal();
      final groupKey = date == null
          ? 'recent'
          : '${date.year}-${date.month}-${date.day}';

      if (groupKey != lastGroupKey) {
        if (lastGroupKey != null) widgets.add(const SizedBox(height: 18));
        widgets.add(_PassbookDateHeader(date: date));
        widgets.add(const SizedBox(height: 10));
        lastGroupKey = groupKey;
      } else {
        widgets.add(const SizedBox(height: 12));
      }

      widgets.add(
        _WalletTransactionTile(transaction: transaction, time: date),
      );
    }

    return widgets;
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
              color:
                  selected ? const Color(0xFF68B98A) : const Color(0xFFE3E6EE),
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

/// Full-bleed colored balance card with the primary "Add Money" CTA
/// embedded directly on it — the signature Paytm wallet-home layout.
class _PaytmBalanceCard extends StatelessWidget {
  const _PaytmBalanceCard({
    required this.balance,
    required this.isRecharging,
    required this.onAddMoney,
    required this.onGiftCard,
  });

  final double balance;
  final bool isRecharging;
  final VoidCallback onAddMoney;
  final VoidCallback onGiftCard;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [scheme.primary, scheme.secondary],
        ),
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: scheme.primary.withOpacity(0.28),
            blurRadius: 26,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.18),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: const Icon(
                  Icons.account_balance_wallet_rounded,
                  color: Colors.white,
                  size: 18,
                ),
              ),
              const SizedBox(width: 10),
              Text(
                '${AppConfig.walletMoneyLabel} Balance',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.88),
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            formatCurrencyWithDecimals(context, balance, decimals: 2),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 36,
              fontWeight: FontWeight.w900,
              height: 1,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Usable across ${AppConfig.appName} for one-tap payments',
            style: TextStyle(
              color: Colors.white.withOpacity(0.78),
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 46,
                  child: ElevatedButton.icon(
                    onPressed: isRecharging ? null : onAddMoney,
                    icon: isRecharging
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.add_rounded, size: 18),
                    label: Text(
                      isRecharging ? 'Processing...' : 'Add Money',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: scheme.primary,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              SizedBox(
                width: 46,
                height: 46,
                child: OutlinedButton(
                  onPressed: isRecharging ? null : onGiftCard,
                  style: OutlinedButton.styleFrom(
                    padding: EdgeInsets.zero,
                    side: BorderSide(color: Colors.white.withOpacity(0.55)),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Icon(
                    Icons.card_giftcard_rounded,
                    color: Colors.white,
                    size: 20,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PassbookDateHeader extends StatelessWidget {
  const _PassbookDateHeader({required this.date});

  final DateTime? date;

  String get _label {
    final value = date;
    if (value == null) return 'Recent';
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final day = DateTime(value.year, value.month, value.day);
    final diff = today.difference(day).inDays;
    if (diff == 0) return 'Today';
    if (diff == 1) return 'Yesterday';
    return DateFormat('d MMMM yyyy').format(value);
  }

  @override
  Widget build(BuildContext context) {
    return Text(
      _label,
      style: const TextStyle(
        color: FoodFlowTheme.inkSoft,
        fontSize: 12,
        fontWeight: FontWeight.w800,
        letterSpacing: 0.3,
      ),
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
  final DateTime? time;

  const _WalletTransactionTile({required this.transaction, this.time});

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
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
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
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                '${isCredit ? '+' : '-'} ${formatCurrency(context, amount)}',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: isCredit ? const Color(0xFF0E8F45) : primary,
                ),
              ),
              if (time != null) ...[
                const SizedBox(height: 3),
                Text(
                  DateFormat('h:mm a').format(time!),
                  style: const TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w600,
                    color: FoodFlowTheme.muted,
                  ),
                ),
              ],
            ],
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
                        horizontal: 20,
                        vertical: 12,
                      ),
                      decoration: BoxDecoration(
                        color:
                            selected ? const Color(0xFFEFF9F2) : Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                          color: selected
                              ? const Color(0xFF68B98A)
                              : const Color(0xFFE4E8F0),
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.add_circle_rounded,
                            size: 15,
                            color: selected
                                ? const Color(0xFF0E8F45)
                                : FoodFlowTheme.faint,
                          ),
                          const SizedBox(width: 6),
                          Text(
                            formatCurrency(context, amount.toDouble()),
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              color: selected
                                  ? const Color(0xFF0E8F45)
                                  : FoodFlowTheme.inkSoft,
                            ),
                          ),
                        ],
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
