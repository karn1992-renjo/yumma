import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../services/api_service.dart';
import '../../services/local_cache_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
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
  bool _hasData = false;
  bool _isRequestingWithdrawal = false;
  double _balance = 0;
  double _lockedBalance = 0;
  List<Map<String, dynamic>> _transactions = [];

  String get _cacheKey => '${AppConfig.apiBaseUrl}${ApiConstants.wallet}';

  @override
  void initState() {
    super.initState();
    // Paint instantly from the last response, then refresh silently.
    final cached = LocalCacheService.get(_cacheKey);
    if (cached is Map && _applyWallet(cached)) {
      _isLoading = false;
      _hasData = true;
    }
    _loadWallet(silent: _hasData);
  }

  bool _applyWallet(Map<dynamic, dynamic> response) {
    if (response['success'] != true) return false;
    final data = _asMap(response['data']);
    final wallet = _asMap(data['wallet']);
    final transactions =
        data['transactions'] is List ? data['transactions'] as List : const [];
    _balance = _toDouble(wallet['balance']);
    _lockedBalance = _toDouble(wallet['locked_balance']);
    _transactions = transactions
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    return true;
  }

  Future<void> _loadWallet({bool silent = false}) async {
    if (!silent) setState(() => _isLoading = true);
    try {
      final response = await _api.get(ApiConstants.wallet);
      if (!mounted) return;
      if (response is Map && _applyWallet(response)) {
        setState(() => _hasData = true);
      }
    } catch (e) {
      if (mounted && !_hasData) {
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
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        leading: const BackButton(),
        title: Text('Payouts & wallet',
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            )),
      ),
      body: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(color: foodflow.canvas),
              child: Stack(children: AuroraTheme.auroraBlobs()),
            ),
          ),
          Positioned.fill(
            child: RefreshIndicator(
              color: foodflow.orange,
              onRefresh: _loadWallet,
              child: _isLoading
                  ? Center(
                      child:
                          CircularProgressIndicator(color: foodflow.orange))
                  : ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: EdgeInsets.fromLTRB(16,
                          MediaQuery.of(context).padding.top + 64, 16, 40),
                      children: [
                        _BalanceHero(
                          balance: _balance,
                          reserved: _lockedBalance,
                          isRequesting: _isRequestingWithdrawal,
                          onWithdraw:
                              _balance <= 0 || _isRequestingWithdrawal
                                  ? null
                                  : _requestWithdrawal,
                        ),
                        const SizedBox(height: 22),
                        Padding(
                          padding: const EdgeInsets.only(left: 4, bottom: 4),
                          child: Text(
                            'ACTIVITY',
                            style: TextStyle(
                              color: foodflow.muted,
                              fontSize: 11,
                              fontWeight: FontWeight.w900,
                              letterSpacing: 1.2,
                            ),
                          ),
                        ),
                        const SizedBox(height: 6),
                        if (_transactions.isEmpty)
                          const _WalletEmptyState()
                        else
                          for (var i = 0; i < _transactions.length; i++)
                            _TimelineEntry(
                              transaction: _transactions[i],
                              first: i == 0,
                              last: i == _transactions.length - 1,
                              onOpenPayout: _openPayoutDetails,
                              onOpenOrder: _openOrderDetails,
                            ),
                      ],
                    ),
            ),
          ),
        ],
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

  void _openOrderDetails(Map<String, dynamic> transaction) {
    final orderId = _transactionOrderId(transaction);
    if (orderId == null) return;
    final restaurantId = _restaurantIdFrom(transaction);
    Navigator.pushNamed(
      context,
      '/restaurant/order',
      arguments: {
        'orderId': orderId,
        if (restaurantId != null) 'restaurantId': restaurantId,
      },
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
              Expanded(
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
            style: TextStyle(
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
                  style: TextStyle(
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
                    style: TextStyle(
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
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: TextStyle(
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
    required this.onOpenOrder,
  });

  final Map<String, dynamic> transaction;
  final ValueChanged<Map<String, dynamic>> onOpenPayout;
  final ValueChanged<Map<String, dynamic>> onOpenOrder;

  @override
  Widget build(BuildContext context) {
    final type = '${transaction['type'] ?? ''}'.toLowerCase();
    final referenceType =
        '${transaction['reference_type'] ?? ''}'.toLowerCase();
    final isPayout = referenceType == 'payout' &&
        _toInt(transaction['reference_id']) != null;
    final isOrder = _isOrderReference(referenceType) &&
        _transactionOrderId(transaction) != null;
    final actionLabel = isPayout
        ? 'View orders'
        : isOrder
            ? 'View order'
            : null;
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
          onTap: isPayout
              ? () => onOpenPayout(transaction)
              : isOrder
                  ? () => onOpenOrder(transaction)
                  : null,
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
                        style: TextStyle(
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
                            style: TextStyle(
                              color: FoodFlowTheme.muted,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (actionLabel != null)
                            Text(
                              actionLabel,
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
                    if (actionLabel != null) ...[
                      const SizedBox(height: 5),
                      Icon(
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
      child: Column(
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

int? _transactionOrderId(Map<String, dynamic> transaction) {
  return _toInt(
    transaction['order_id'] ??
        transaction['orderId'] ??
        transaction['order'] ??
        transaction['reference_id'],
  );
}

bool _isOrderReference(String referenceType) {
  return referenceType == 'order' ||
      referenceType == 'orders' ||
      referenceType == 'restaurant_order' ||
      referenceType.contains('order');
}

int? _restaurantIdFrom(Map<String, dynamic> value) {
  final metadata = _asMap(value['metadata'] ?? value['meta']);
  final restaurant = _asMap(value['restaurant']);
  return _toInt(
    value['restaurant_id'] ??
        value['restaurantId'] ??
        metadata['restaurant_id'] ??
        metadata['restaurantId'] ??
        restaurant['id'] ??
        restaurant['restaurant_id'],
  );
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


class _BalanceHero extends StatelessWidget {
  const _BalanceHero({
    required this.balance,
    required this.reserved,
    required this.isRequesting,
    required this.onWithdraw,
  });

  final double balance;
  final double reserved;
  final bool isRequesting;
  final VoidCallback? onWithdraw;

  @override
  Widget build(BuildContext context) {
    final can = onWithdraw != null;
    return Container(
      padding: const EdgeInsets.fromLTRB(22, 22, 22, 20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [foodflow.orange, foodflow.orangeDark],
        ),
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: foodflow.orange.withOpacity(0.32),
            blurRadius: 26,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('AVAILABLE TO WITHDRAW',
              style: TextStyle(
                color: Colors.white.withOpacity(0.85),
                fontSize: 11,
                fontWeight: FontWeight.w900,
                letterSpacing: 1,
              )),
          const SizedBox(height: 10),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              formatCurrency(context, balance),
              style: const TextStyle(
                color: Colors.white,
                fontSize: 42,
                fontWeight: FontWeight.w900,
                height: 1,
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(
                    horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.16),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.lock_clock_rounded,
                        size: 14, color: Colors.white.withOpacity(0.9)),
                    const SizedBox(width: 6),
                    Text(
                      'Reserved ${formatCurrency(context, reserved)}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                child: InkWell(
                  onTap: onWithdraw,
                  borderRadius: BorderRadius.circular(14),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 11),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (isRequesting)
                          SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor:
                                  AlwaysStoppedAnimation(foodflow.orange),
                            ),
                          )
                        else
                          Icon(Icons.account_balance_rounded,
                              size: 16,
                              color: can ? foodflow.orange : foodflow.faint),
                        const SizedBox(width: 7),
                        Text(
                          isRequesting ? 'Submitting' : 'Withdraw',
                          style: TextStyle(
                            color: can ? foodflow.orange : foodflow.faint,
                            fontSize: 13.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
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

/// One node on the vertical activity timeline.
class _TimelineEntry extends StatelessWidget {
  const _TimelineEntry({
    required this.transaction,
    required this.first,
    required this.last,
    required this.onOpenPayout,
    required this.onOpenOrder,
  });

  final Map<String, dynamic> transaction;
  final bool first;
  final bool last;
  final ValueChanged<Map<String, dynamic>> onOpenPayout;
  final ValueChanged<Map<String, dynamic>> onOpenOrder;

  @override
  Widget build(BuildContext context) {
    final type = '${transaction['type'] ?? ''}'.toLowerCase();
    final referenceType =
        '${transaction['reference_type'] ?? ''}'.toLowerCase();
    final isPayout = referenceType == 'payout' &&
        _toInt(transaction['reference_id']) != null;
    final isOrder = _isOrderReference(referenceType) &&
        _transactionOrderId(transaction) != null;
    final isCredit = type.contains('credit') || type.contains('topup');
    final color = isCredit ? foodflow.success : foodflow.orange;
    final amount = _toDouble(transaction['amount']);
    final description = transaction['description']?.toString().trim();
    final action = isPayout
        ? 'View orders'
        : isOrder
            ? 'View order'
            : null;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 30,
            child: Column(
              children: [
                Container(
                  width: 2,
                  height: 14,
                  color: first ? Colors.transparent : foodflow.line,
                ),
                Container(
                  width: 14,
                  height: 14,
                  decoration: BoxDecoration(
                    color: color,
                    shape: BoxShape.circle,
                    border: Border.all(color: foodflow.canvas, width: 2),
                  ),
                ),
                Expanded(
                  child: Container(
                    width: 2,
                    color: last ? Colors.transparent : foodflow.line,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Material(
                color: foodflow.isDark
                    ? foodflow.elevatedSurface
                    : Colors.white,
                borderRadius: BorderRadius.circular(14),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: isPayout
                      ? () => onOpenPayout(transaction)
                      : isOrder
                          ? () => onOpenOrder(transaction)
                          : null,
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        Icon(
                          isCredit
                              ? Icons.south_west_rounded
                              : Icons.north_east_rounded,
                          size: 16,
                          color: color,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                description?.isNotEmpty == true
                                    ? description!
                                    : _titleCase(
                                        type.replaceAll('_', ' ')),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: foodflow.ink,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                _formatDate(transaction['created_at']) +
                                    (action == null ? '' : '  -  $action'),
                                style: TextStyle(
                                  color: foodflow.muted,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '${isCredit ? '+' : '-'} ${formatCurrency(context, amount)}',
                          style: TextStyle(
                            color: color,
                            fontSize: 13.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        if (action != null)
                          Icon(Icons.chevron_right_rounded,
                              size: 18, color: foodflow.faint),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
