// lib/screens/restaurant/restaurant_subscription_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/subscription_checkout_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../widgets/aurora/aurora_dialogs.dart';
import '../../utils/currency_utils.dart';

const _statusLabels = {
  'pending_authorization': 'Setting up',
  'trialing': 'Free trial',
  'active': 'Active',
  'past_due': 'Payment due',
  'billing_choice_required': 'Action needed',
  'expired': 'Expired',
  'cancelled': 'Cancelled',
};

const _statusColors = {
  'pending_authorization': Colors.blueGrey,
  'trialing': Color(0xFF2563EB),
  'active': Color(0xFF16A34A),
  'past_due': Color(0xFFF97316),
  'billing_choice_required': Color(0xFFDC2626),
  'expired': Color(0xFF6B7280),
  'cancelled': Color(0xFF6B7280),
};

class RestaurantSubscriptionScreen extends StatefulWidget {
  const RestaurantSubscriptionScreen({super.key});

  @override
  State<RestaurantSubscriptionScreen> createState() =>
      _RestaurantSubscriptionScreenState();
}

class _RestaurantSubscriptionScreenState
    extends State<RestaurantSubscriptionScreen> {
  final ApiService _api = ApiService();
  SubscriptionCheckoutService? _checkout;

  Map<String, dynamic>? _current;
  List<Map<String, dynamic>> _plans = [];
  List<String> _availableGateways = [];
  bool _isLoading = true;
  bool _hasData = false;
  bool _isSubscribing = false;

  @override
  void initState() {
    super.initState();
    _loadAll();
  }

  @override
  void dispose() {
    _checkout?.dispose();
    super.dispose();
  }

  Future<void> _loadAll() async {
    if (mounted && !_hasData) setState(() => _isLoading = true);
    await Future.wait([_loadCurrent(), _loadPlans()]);
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _loadCurrent() async {
    try {
      final response = await _api.get(ApiConstants.restaurantSubscriptionCurrent);
      if (response['success'] == true && mounted) {
        setState(() {
          _current = Map<String, dynamic>.from(response['data'] ?? {});
          _hasData = true;
        });
      }
    } catch (e) {
      debugPrint('Load subscription error: $e');
    }
  }

  Future<void> _loadPlans() async {
    try {
      final response = await _api.get(ApiConstants.restaurantSubscriptionPlans);
      if (response['success'] == true && mounted) {
        setState(() {
          _plans = (response['data'] as List? ?? [])
              .whereType<Map>()
              .map((row) => Map<String, dynamic>.from(row))
              .toList();
          _availableGateways = (response['available_gateways'] as List? ?? [])
              .map((g) => g.toString())
              .toList();
        });
      }
    } catch (e) {
      debugPrint('Load subscription plans error: $e');
    }
  }

  Map<String, dynamic>? get _subscription =>
      _current?['subscription'] is Map
          ? Map<String, dynamic>.from(_current!['subscription'])
          : null;

  Future<void> _subscribe(Map<String, dynamic> plan) async {
    if (_isSubscribing) return;

    final paymentMethod = await _resolvePaymentMethod();
    if (paymentMethod == null) return;

    setState(() => _isSubscribing = true);

    final user = context.read<AuthProvider>().currentUser;
    _checkout?.dispose();
    _checkout = SubscriptionCheckoutService(
      onAuthorized: () async {
        if (!mounted) return;
        setState(() => _isSubscribing = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Payment authorized. Setting up your subscription — this can take a few seconds.',
            ),
          ),
        );
        await Future.delayed(const Duration(seconds: 2));
        await _loadAll();
      },
      onFailure: (message) {
        if (!mounted) return;
        setState(() => _isSubscribing = false);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(message)));
      },
    );

    try {
      await _checkout!.subscribe(
        planId: plan['id'] as int,
        paymentMethod: paymentMethod,
        user: user,
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubscribing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(_cleanError(e))),
      );
    }
  }

  /// Only ever offers gateways the admin has both enabled for customer
  /// payments AND configured with real API credentials
  /// (`available_gateways`, from SubscriptionGatewayResolver::
  /// availableGateways() on the backend) -- never a hardcoded Razorpay/
  /// Cashfree pair. Skips the picker entirely when there's just one option.
  Future<String?> _resolvePaymentMethod() async {
    if (_availableGateways.isEmpty) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Subscriptions aren\'t available right now. Please contact support.'),
          ),
        );
      }
      return null;
    }

    if (_availableGateways.length == 1) {
      return _availableGateways.first;
    }

    return _pickPaymentMethod(_availableGateways);
  }

  Future<String?> _pickPaymentMethod(List<String> gateways) {
    const labels = {
      'razorpay': ('Razorpay', 'UPI AutoPay / Card'),
      'cashfree': ('Cashfree', 'UPI / Card / Netbanking'),
    };

    return showModalBottomSheet<String>(
      context: context,
      backgroundColor: foodflow.surfaceColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Pay with',
                style: TextStyle(
                  color: foodflow.ink,
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              for (final gateway in gateways)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(Icons.account_balance_wallet_outlined, color: foodflow.orange),
                  title: Text(labels[gateway]?.$1 ?? gateway,
                      style: const TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text(labels[gateway]?.$2 ?? ''),
                  onTap: () => Navigator.pop(sheetContext, gateway),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _switchToCommission() async {
    Map<String, dynamic> preview;
    try {
      final response =
          await _api.get(ApiConstants.restaurantSubscriptionCommissionPreview);
      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Unable to load commission terms');
      }
      preview = Map<String, dynamic>.from(response['data'] ?? {});
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(_cleanError(e))));
      return;
    }

    if (!mounted) return;
    final type = preview['type']?.toString() ?? 'percentage';
    final value = (preview['value'] as num?)?.toDouble() ?? 0;
    final rateText = type == 'fixed'
        ? '${formatCurrencyValue(context, value)} per order'
        : '${value.toStringAsFixed(value == value.roundToDouble() ? 0 : 2)}% of each order';

    final confirmed = await showAuroraConfirm(
      context,
      icon: Icons.percent_rounded,
      title: 'Switch to commission billing?',
      message:
          'Your current subscription will be cancelled. Going forward, the platform will charge $rateText instead of a flat subscription fee. This takes effect immediately.',
      confirmLabel: 'Switch & accept terms',
    );
    if (!confirmed) return;

    try {
      final response = await _api.post(
        ApiConstants.restaurantSubscriptionSwitchToCommission,
        data: {'consent': true},
      );
      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Unable to switch billing mode');
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Switched to commission billing.')),
      );
      await _loadAll();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(_cleanError(e))));
    }
  }

  String _cleanError(Object error) {
    final message = error.toString();
    return message.startsWith('Exception: ')
        ? message.substring('Exception: '.length)
        : message;
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top + 60;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Text(
          'Subscription',
          style: TextStyle(
            color: foodflow.ink,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
      body: Stack(
        children: [
          ...AuroraTheme.auroraBlobs(),
          _isLoading
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _loadAll,
                  child: ListView(
                    padding: EdgeInsets.fromLTRB(16, topPad, 16, 96),
                    children: [
                      if (_subscription != null) ...[
                        _CurrentPlanCard(
                          subscription: _subscription!,
                          onSwitchToCommission: _switchToCommission,
                        ),
                        const SizedBox(height: 18),
                        Text(
                          'Other plans',
                          style: TextStyle(
                            color: foodflow.ink,
                            fontSize: 15,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 10),
                      ] else ...[
                        Text(
                          'Choose a plan',
                          style: TextStyle(
                            color: foodflow.ink,
                            fontSize: 17,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Switch from commission-based billing to a flat recurring plan.',
                          style: TextStyle(color: foodflow.muted, fontSize: 13),
                        ),
                        const SizedBox(height: 14),
                      ],
                      ..._plans
                          .where((plan) =>
                              _subscription == null ||
                              plan['id'] != _subscription!['plan']?['id'])
                          .map(
                            (plan) => Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: _PlanCard(
                                plan: plan,
                                isBusy: _isSubscribing,
                                onSubscribe: () => _subscribe(plan),
                              ),
                            ),
                          ),
                      if (_plans.isEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 40),
                          child: Center(
                            child: Text(
                              'No subscription plans available right now.',
                              style: TextStyle(color: foodflow.muted),
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

class _CurrentPlanCard extends StatelessWidget {
  const _CurrentPlanCard({
    required this.subscription,
    required this.onSwitchToCommission,
  });

  final Map<String, dynamic> subscription;
  final VoidCallback onSwitchToCommission;

  @override
  Widget build(BuildContext context) {
    final status = subscription['status']?.toString() ?? 'active';
    final plan = subscription['plan'] is Map
        ? Map<String, dynamic>.from(subscription['plan'])
        : <String, dynamic>{};
    final orderLimit = subscription['order_limit'] as int?;
    final menuItemLimit = subscription['menu_item_limit'] as int?;
    final ordersUsed = (subscription['orders_used_in_period'] as num?)?.toInt() ?? 0;
    final trialEndsAt = _parseDate(subscription['trial_ends_at']);
    final periodEnd = _parseDate(subscription['current_period_end']);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: foodflow.line),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  plan['name']?.toString() ?? 'Subscription',
                  style: TextStyle(
                    color: foodflow.ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: (_statusColors[status] ?? Colors.grey).withOpacity(0.12),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  _statusLabels[status] ?? status,
                  style: TextStyle(
                    color: _statusColors[status] ?? Colors.grey,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          if (status == 'trialing' && trialEndsAt != null) ...[
            const SizedBox(height: 6),
            Text(
              'Trial ends ${DateFormat('dd MMM yyyy').format(trialEndsAt)}',
              style: TextStyle(color: foodflow.muted, fontSize: 12.5, fontWeight: FontWeight.w700),
            ),
          ],
          if (status == 'billing_choice_required') ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF2F2),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFFECACA)),
              ),
              child: Text(
                'Your subscription has lapsed. Choose a new plan below or switch to commission billing to keep receiving orders.',
                style: const TextStyle(color: Color(0xFF991B1B), fontSize: 12.5, fontWeight: FontWeight.w700),
              ),
            ),
          ] else if (periodEnd != null) ...[
            const SizedBox(height: 6),
            Text(
              'Renews ${DateFormat('dd MMM yyyy').format(periodEnd)}',
              style: TextStyle(color: foodflow.muted, fontSize: 12.5, fontWeight: FontWeight.w700),
            ),
          ],
          const SizedBox(height: 16),
          _UsageBar(label: 'Orders this period', used: ordersUsed, limit: orderLimit),
          const SizedBox(height: 10),
          _UsageBar(label: 'Menu items', used: null, limit: menuItemLimit),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton(
              onPressed: onSwitchToCommission,
              style: OutlinedButton.styleFrom(
                foregroundColor: foodflow.danger,
                side: BorderSide(color: foodflow.danger.withOpacity(0.4)),
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: const Text('Switch to commission billing',
                  style: TextStyle(fontWeight: FontWeight.w800)),
            ),
          ),
        ],
      ),
    );
  }

  DateTime? _parseDate(dynamic value) {
    if (value is! String || value.isEmpty) return null;
    return DateTime.tryParse(value);
  }
}

class _UsageBar extends StatelessWidget {
  const _UsageBar({required this.label, required this.used, required this.limit});

  final String label;
  final int? used;
  final int? limit;

  @override
  Widget build(BuildContext context) {
    final isUnlimited = limit == null;
    final fraction = (!isUnlimited && used != null && limit! > 0)
        ? (used! / limit!).clamp(0.0, 1.0)
        : 0.0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label,
                style: TextStyle(color: foodflow.muted, fontSize: 12, fontWeight: FontWeight.w700)),
            Text(
              isUnlimited ? 'Unlimited' : '${used ?? 0} / $limit',
              style: TextStyle(color: foodflow.ink, fontSize: 12, fontWeight: FontWeight.w900),
            ),
          ],
        ),
        if (!isUnlimited) ...[
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: fraction,
              minHeight: 8,
              backgroundColor: foodflow.line,
              valueColor: AlwaysStoppedAnimation(
                fraction >= 1 ? foodflow.danger : foodflow.orange,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.plan,
    required this.isBusy,
    required this.onSubscribe,
  });

  final Map<String, dynamic> plan;
  final bool isBusy;
  final VoidCallback onSubscribe;

  @override
  Widget build(BuildContext context) {
    final price = (plan['price'] as num?)?.toDouble() ?? 0;
    final period = plan['billing_period']?.toString() ?? 'monthly';
    final orderLimit = plan['order_limit'] as int?;
    final menuItemLimit = plan['menu_item_limit'] as int?;
    final trialDays = plan['trial_days'] as int?;
    final includesStories = plan['includes_stories'] == true;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: foodflow.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  plan['name']?.toString() ?? 'Plan',
                  style: TextStyle(color: foodflow.ink, fontSize: 15, fontWeight: FontWeight.w900),
                ),
              ),
              Text(
                '${formatCurrencyValue(context, price)}/$period',
                style: TextStyle(color: foodflow.orange, fontSize: 14, fontWeight: FontWeight.w900),
              ),
            ],
          ),
          if ((plan['description']?.toString() ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(plan['description'].toString(),
                style: TextStyle(color: foodflow.muted, fontSize: 12.5)),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              _Pill(text: orderLimit == null ? 'Unlimited orders' : '$orderLimit orders/period'),
              _Pill(text: menuItemLimit == null ? 'Unlimited menu items' : '$menuItemLimit menu items'),
              if (includesStories) const _Pill(text: 'Stories included', highlight: true),
              if ((trialDays ?? 0) > 0) _Pill(text: '$trialDays-day free trial', highlight: true),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: isBusy ? null : onSubscribe,
              style: FoodFlowTheme.zomatoPrimaryButton(radius: 10),
              child: Text(
                (trialDays ?? 0) > 0 ? 'Start free trial' : 'Subscribe',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.text, this.highlight = false});

  final String text;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: highlight ? foodflow.orange.withOpacity(0.1) : foodflow.canvas,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: highlight ? foodflow.orange.withOpacity(0.3) : foodflow.line),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: highlight ? foodflow.orange : foodflow.muted,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
