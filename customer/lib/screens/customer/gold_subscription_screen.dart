// lib/screens/customer/gold_subscription_screen.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../services/app_branding_service.dart';
import '../../services/gold_checkout_service.dart';
import '../../theme/brand_palette.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/app_skeleton.dart';
import '../../widgets/customer/account_chrome.dart';

const _statusLabels = {
  'pending_authorization': 'Setting up',
  'trialing': 'Free trial',
  'active': 'Active',
  'past_due': 'Payment due',
  'expired': 'Expired',
  'cancelled': 'Cancelled',
};

// Dark + gold "celebration" treatment for an actually-active membership --
// an original composition (not a copy of any specific app's design),
// matching the premium feel used elsewhere this session for Gold surfaces.
const _goldDark = Color(0xFF14110B);
const _goldDarkDeep = Color(0xFF2A2210);
const _gold = Color(0xFFF2C94C);
const _goldDeep = Color(0xFFC89B2E);
const _goldCream = Color(0xFFFFF6E0);

class GoldSubscriptionScreen extends StatefulWidget {
  const GoldSubscriptionScreen({super.key});

  @override
  State<GoldSubscriptionScreen> createState() => _GoldSubscriptionScreenState();
}

class _GoldSubscriptionScreenState extends State<GoldSubscriptionScreen> {
  final ApiService _api = ApiService();
  GoldCheckoutService? _checkout;

  Map<String, dynamic>? _current;
  List<Map<String, dynamic>> _plans = [];
  List<String> _availableGateways = [];
  AppBranding _branding = AppBranding.fallback();
  bool _isLoading = true;
  bool _hasData = false;
  bool _isSubscribing = false;

  BrandPalette get _palette => BrandPalette.fromBranding(_branding);

  @override
  void initState() {
    super.initState();
    _branding = AppBrandingService.instance.cachedOrFallback;
    _loadAll();
    _loadBranding();
  }

  @override
  void dispose() {
    _checkout?.dispose();
    super.dispose();
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (mounted) setState(() => _branding = branding);
  }

  Future<void> _loadAll() async {
    if (mounted && !_hasData) setState(() => _isLoading = true);
    await Future.wait([_loadCurrent(), _loadPlans()]);
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _loadCurrent() async {
    try {
      final response = await _api.get(ApiConstants.goldCurrent);
      if (response['success'] == true && mounted) {
        setState(() {
          _current = Map<String, dynamic>.from(response['data'] ?? {});
          _hasData = true;
        });
      }
    } catch (e) {
      debugPrint('Load Gold membership error: $e');
    }
  }

  Future<void> _loadPlans() async {
    try {
      final response = await _api.get(ApiConstants.goldPlans);
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
      debugPrint('Load Gold plans error: $e');
    }
  }

  bool get _isMember => _current?['is_gold_member'] == true;

  Map<String, dynamic>? get _subscription =>
      _current?['subscription'] is Map
          ? Map<String, dynamic>.from(_current!['subscription'])
          : null;

  double get _totalSaved =>
      double.tryParse('${_current?['total_saved'] ?? 0}') ?? 0;

  Future<void> _subscribe(Map<String, dynamic> plan) async {
    if (_isSubscribing) return;

    final paymentMethod = await _resolvePaymentMethod();
    if (paymentMethod == null) return;

    setState(() => _isSubscribing = true);

    final user = context.read<AuthProvider>().currentUser;
    _checkout?.dispose();
    _checkout = GoldCheckoutService(
      onAuthorized: () async {
        if (!mounted) return;
        setState(() => _isSubscribing = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Payment authorized. Activating your membership — this can take a few seconds.',
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
  /// availableGateways() on the backend). Skips the picker entirely when
  /// there's just one option.
  Future<String?> _resolvePaymentMethod() async {
    if (_availableGateways.isEmpty) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Membership isn\'t available right now. Please contact support.'),
          ),
        );
      }
      return null;
    }

    if (_availableGateways.length == 1) {
      return _availableGateways.first;
    }

    const labels = {
      'razorpay': ('Razorpay', 'UPI AutoPay / Card'),
      'cashfree': ('Cashfree', 'UPI / Card / Netbanking'),
    };

    return showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.white,
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
              const Text(
                'Pay with',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 12),
              for (final gateway in _availableGateways)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(Icons.account_balance_wallet_outlined, color: _palette.primary),
                  title: Text(labels[gateway]?.$1 ?? gateway, style: const TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text(labels[gateway]?.$2 ?? ''),
                  onTap: () => Navigator.pop(sheetContext, gateway),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _cancel() async {
    final isPending = _subscription?['status']?.toString() == 'pending_authorization';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(isPending ? 'Cancel and try again?' : 'Cancel membership?'),
        content: Text(
          isPending
              ? 'This will clear your stuck membership setup so you can subscribe again.'
              : 'You will stop getting free delivery once your current period ends.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Keep membership'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Cancel membership'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      final response = await _api.post(ApiConstants.goldCancel);
      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Unable to cancel membership');
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Membership cancelled.')),
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
    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        backgroundColor: accountCanvas,
        elevation: 0,
        foregroundColor: FoodFlowTheme.ink,
        title: const Text('Membership', style: TextStyle(fontWeight: FontWeight.w800)),
      ),
      body: _isLoading
          ? const AppSkeletonListView(itemCount: 3, itemHeight: 120)
          : RefreshIndicator(
              onRefresh: _loadAll,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                children: [
                  if (_subscription != null) ...[
                    _CurrentMembershipCard(
                      subscription: _subscription!,
                      isMember: _isMember,
                      palette: _palette,
                      onCancel: _cancel,
                      totalSaved: _totalSaved,
                    ),
                    const SizedBox(height: 18),
                  ] else ...[
                    _MembershipHeroHeader(palette: _palette),
                    const SizedBox(height: 18),
                  ],
                  if (!_isMember)
                    ..._plans.map(
                      (plan) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _MembershipPlanCard(
                          plan: plan,
                          isBusy: _isSubscribing,
                          palette: _palette,
                          onSubscribe: () => _subscribe(plan),
                        ),
                      ),
                    ),
                  if (!_isMember && _plans.isEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 40),
                      child: Center(
                        child: Text(
                          'Membership isn\'t available right now.',
                          style: TextStyle(color: FoodFlowTheme.muted),
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}

class _MembershipHeroHeader extends StatelessWidget {
  const _MembershipHeroHeader({required this.palette});

  final BrandPalette palette;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: palette.primaryGradient,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(color: Colors.white.withOpacity(0.2), shape: BoxShape.circle),
            child: const Icon(Icons.star_rounded, color: Colors.white, size: 24),
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Text(
              'Free delivery on every order, no minimum.',
              style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}

class _CurrentMembershipCard extends StatelessWidget {
  const _CurrentMembershipCard({
    required this.subscription,
    required this.isMember,
    required this.palette,
    required this.onCancel,
    this.totalSaved = 0,
  });

  final Map<String, dynamic> subscription;
  final bool isMember;
  final BrandPalette palette;
  final VoidCallback onCancel;
  final double totalSaved;

  @override
  Widget build(BuildContext context) {
    final status = subscription['status']?.toString() ?? 'active';

    // An actually-live membership (active/trialing) gets the full
    // celebration layout with real savings/validity data below. Every
    // other state (pending_authorization, past_due, expired, cancelled)
    // keeps the compact status card -- there's nothing to celebrate yet,
    // or the membership has lapsed.
    if (isMember) {
      return _GoldMemberCelebrationCard(
        subscription: subscription,
        totalSaved: totalSaved,
        onCancel: onCancel,
      );
    }

    final plan = subscription['plan'] is Map
        ? Map<String, dynamic>.from(subscription['plan'])
        : <String, dynamic>{};
    final trialEndsAt = _parseDate(subscription['trial_ends_at']);
    final periodEnd = _parseDate(subscription['current_period_end']);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: palette.primaryGradient,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.star_rounded, color: Colors.white, size: 24),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  plan['name']?.toString() ?? 'Membership',
                  style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w900),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.22),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  _statusLabels[status] ?? status,
                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
          if (status == 'trialing' && trialEndsAt != null) ...[
            const SizedBox(height: 8),
            Text(
              'Trial ends ${DateFormat('dd MMM yyyy').format(trialEndsAt)}',
              style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700),
            ),
          ] else if (periodEnd != null) ...[
            const SizedBox(height: 8),
            Text(
              'Renews ${DateFormat('dd MMM yyyy').format(periodEnd)}',
              style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700),
            ),
          ],
          if (status == 'pending_authorization') ...[
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: onCancel,
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.white,
                  side: const BorderSide(color: Colors.white70),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text(
                  'Cancel and try again',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  static DateTime? _parseDate(dynamic value) {
    if (value is! String || value.isEmpty) return null;
    return DateTime.tryParse(value);
  }
}

class _GoldMemberCelebrationCard extends StatelessWidget {
  const _GoldMemberCelebrationCard({
    required this.subscription,
    required this.totalSaved,
    required this.onCancel,
  });

  final Map<String, dynamic> subscription;
  final double totalSaved;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final plan = subscription['plan'] is Map
        ? Map<String, dynamic>.from(subscription['plan'])
        : <String, dynamic>{};
    final planName = plan['name']?.toString().trim();
    final periodEnd = _CurrentMembershipCard._parseDate(subscription['current_period_end']);
    final now = DateTime.now();
    final daysRemaining =
        periodEnd?.difference(DateTime(now.year, now.month, now.day)).inDays;

    // order_limit/orders_used_in_period are real usage data from the
    // backend (null order_limit = unlimited) -- not a fabricated figure.
    final orderLimit = (subscription['order_limit'] ?? plan['order_limit']) as int?;
    final ordersUsed = (subscription['orders_used_in_period'] as num?)?.toInt() ?? 0;

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: _goldDeep.withOpacity(0.35)),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.18), blurRadius: 18, offset: const Offset(0, 10)),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Celebration header.
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(18, 20, 18, 22),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [_goldDarkDeep, _goldDark],
              ),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: const LinearGradient(colors: [_gold, _goldDeep]),
                    boxShadow: [BoxShadow(color: _gold.withOpacity(0.35), blurRadius: 14, offset: const Offset(0, 6))],
                  ),
                  child: const Icon(Icons.workspace_premium_rounded, color: _goldDark, size: 28),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Congratulations!',
                        style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'You are a ${planName?.isNotEmpty == true ? planName : 'Gold'} Member',
                        style: const TextStyle(color: _gold, fontSize: 19, fontWeight: FontWeight.w900, height: 1.15),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Free delivery on every order, no minimum.',
                        style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 12.5, height: 1.35),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          // Real stats: savings + validity.
          Container(
            color: _goldCream,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            child: Row(
              children: [
                Expanded(
                  child: _GoldStatBlock(
                    icon: Icons.savings_rounded,
                    label: 'You have saved',
                    value: formatCurrency(context, totalSaved),
                    caption: 'with Gold Membership',
                  ),
                ),
                Container(width: 1, height: 42, color: _goldDeep.withOpacity(0.2)),
                Expanded(
                  child: _GoldStatBlock(
                    icon: Icons.event_available_rounded,
                    label: 'Member Validity',
                    value: periodEnd != null ? DateFormat('dd MMM yyyy').format(periodEnd) : '—',
                    caption: daysRemaining != null && daysRemaining >= 0
                        ? '($daysRemaining days remaining)'
                        : null,
                  ),
                ),
              ],
            ),
          ),
          // Only genuinely implemented benefits -- no decorative/unreal claims.
          Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: Column(
              children: [
                _GoldBenefitRow(
                  icon: Icons.local_shipping_rounded,
                  title: 'Free delivery on every order',
                  subtitle: 'No minimum order value required',
                ),
                if (orderLimit != null)
                  _GoldBenefitRow(
                    icon: Icons.shopping_bag_rounded,
                    title: 'Delivery waiver usage this period',
                    subtitle: '$ordersUsed of $orderLimit orders used',
                  )
                else
                  _GoldBenefitRow(
                    icon: Icons.all_inclusive_rounded,
                    title: 'Unlimited orders',
                    subtitle: 'No cap on how many orders get free delivery',
                  ),
              ],
            ),
          ),
          Container(
            width: double.infinity,
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: TextButton(
              onPressed: onCancel,
              style: TextButton.styleFrom(foregroundColor: const Color(0xFF9CA3AF)),
              child: const Text('Cancel membership', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
            ),
          ),
        ],
      ),
    );
  }
}

class _GoldStatBlock extends StatelessWidget {
  const _GoldStatBlock({
    required this.icon,
    required this.label,
    required this.value,
    this.caption,
  });

  final IconData icon;
  final String label;
  final String value;
  final String? caption;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(color: _gold.withOpacity(0.22), shape: BoxShape.circle),
          child: Icon(icon, color: _goldDeep, size: 17),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(color: Color(0xFF8A6D1F), fontSize: 11, fontWeight: FontWeight.w700)),
              const SizedBox(height: 2),
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: _goldDark, fontSize: 15, fontWeight: FontWeight.w900),
              ),
              if (caption != null) ...[
                const SizedBox(height: 1),
                Text(caption!, style: const TextStyle(color: Color(0xFF8A6D1F), fontSize: 10.5, fontWeight: FontWeight.w600)),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _GoldBenefitRow extends StatelessWidget {
  const _GoldBenefitRow({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(color: _gold.withOpacity(0.14), borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, color: _goldDeep, size: 18),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: Color(0xFF1C1C1C), fontSize: 13.5, fontWeight: FontWeight.w800)),
                const SizedBox(height: 1),
                Text(subtitle, style: TextStyle(color: FoodFlowTheme.muted, fontSize: 11.5)),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
            decoration: BoxDecoration(color: const Color(0xFFEAF8EA), borderRadius: BorderRadius.circular(999)),
            child: const Text('Active', style: TextStyle(color: Color(0xFF0C831F), fontSize: 10.5, fontWeight: FontWeight.w800)),
          ),
        ],
      ),
    );
  }
}

class _MembershipPlanCard extends StatelessWidget {
  const _MembershipPlanCard({
    required this.plan,
    required this.isBusy,
    required this.palette,
    required this.onSubscribe,
  });

  final Map<String, dynamic> plan;
  final bool isBusy;
  final BrandPalette palette;
  final VoidCallback onSubscribe;

  @override
  Widget build(BuildContext context) {
    final price = (plan['price'] as num?)?.toDouble() ?? 0;
    final compareAtPrice = (plan['compare_at_price'] as num?)?.toDouble();
    final period = plan['billing_period']?.toString() ?? 'monthly';
    final trialDays = plan['trial_days'] as int?;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: palette.primary.withOpacity(0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  plan['name']?.toString() ?? 'Plan',
                  style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900),
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Row(
                    children: [
                      if (compareAtPrice != null && compareAtPrice > price) ...[
                        Text(
                          formatCurrency(context, compareAtPrice),
                          style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 12.5,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                        const SizedBox(width: 6),
                      ],
                      Text(
                        formatCurrency(context, price),
                        style: TextStyle(
                          color: palette.primary,
                          fontSize: 15,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                  Text('per $period', style: TextStyle(color: FoodFlowTheme.muted, fontSize: 11)),
                ],
              ),
            ],
          ),
          if ((plan['description']?.toString() ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(plan['description'].toString(),
                style: TextStyle(color: FoodFlowTheme.muted, fontSize: 12.5)),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              _MembershipPill(text: 'Free delivery on every order', palette: palette),
              if ((trialDays ?? 0) > 0) _MembershipPill(text: '$trialDays-day free trial', palette: palette),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: isBusy ? null : onSubscribe,
              style: ElevatedButton.styleFrom(
                backgroundColor: palette.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                padding: const EdgeInsets.symmetric(vertical: 13),
              ),
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

class _MembershipPill extends StatelessWidget {
  const _MembershipPill({required this.text, required this.palette});

  final String text;
  final BrandPalette palette;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: palette.primarySoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: palette.primary.withOpacity(0.3)),
      ),
      child: Text(
        text,
        style: TextStyle(color: palette.primary, fontSize: 11, fontWeight: FontWeight.w800),
      ),
    );
  }
}
