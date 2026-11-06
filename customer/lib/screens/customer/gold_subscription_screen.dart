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
        // Pay-as-you-go: by the time this fires, /gold/verify has already
        // confirmed the payment and activated the membership synchronously
        // (or it was a free trial, activated with no payment at all) --
        // same guarantee a real checkout payment gives, no async wait needed.
        if (!mounted) return;
        setState(() => _isSubscribing = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Membership activated!')),
        );
        await _loadAll();
      },
      onFailure: (message) async {
        if (!mounted) return;
        setState(() => _isSubscribing = false);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(message)));
        // The gateway checkout had already created a pending_authorization
        // row by this point (startCheckout() creates it before opening the
        // gateway's payment UI) -- a client-side cancel/decline inside that
        // UI never reaches our backend on its own, so without this the row
        // sits stuck until the 30-minute staleness sweep or a manual
        // "Cancel and try again" tap. Clean it up immediately instead.
        await _cleanupStuckPendingRow();
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
      // Same reasoning as onFailure above -- the row may already exist even
      // though subscribe() itself threw (e.g. opening the checkout UI
      // failed after the backend had already created it).
      await _cleanupStuckPendingRow();
    }
  }

  /// Best-effort: clears a pending_authorization row left behind by a
  /// failed/cancelled payment attempt so the user lands back on a clean
  /// plan-picker instead of a stuck "Cancel and try again" card. Silent on
  /// failure -- there may be nothing to clean up, and the 30-minute
  /// staleness sweep on the next real subscribe attempt is the fallback.
  Future<void> _cleanupStuckPendingRow() async {
    try {
      final response = await _api.post(ApiConstants.goldCancel);
      if (response['success'] != true) return;
    } catch (_) {
      return;
    }
    if (mounted) await _loadAll();
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
                          branding: _branding,
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
    final deep = Color.lerp(palette.primary, Colors.black, 0.32) ?? palette.primary;

    return Container(
      padding: const EdgeInsets.fromLTRB(24, 28, 16, 28),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [palette.primary, deep],
        ),
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(color: palette.primary.withOpacity(0.35), blurRadius: 24, offset: const Offset(0, 14)),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  'Every order.',
                  style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900, height: 1.18),
                ),
                Text(
                  'Free delivery.',
                  style: TextStyle(color: _gold, fontSize: 26, fontWeight: FontWeight.w900, height: 1.18),
                ),
                const SizedBox(height: 10),
                Text(
                  'No minimum order.',
                  style: TextStyle(color: Colors.white.withOpacity(0.82), fontSize: 13, fontWeight: FontWeight.w600, height: 1.3),
                ),
                Text(
                  'More delicious moments.',
                  style: TextStyle(color: Colors.white.withOpacity(0.82), fontSize: 13, fontWeight: FontWeight.w600, height: 1.3),
                ),
              ],
            ),
          ),
          const SizedBox(width: 6),
          const _MembershipHeroGraphic(),
        ],
      ),
    );
  }
}

/// Podium + glow + sparkle composition framing the real crown artwork
/// (assets/images/gold_crown.webp) -- same asset used on the plan card
/// below, kept consistent across the screen.
class _MembershipHeroGraphic extends StatelessWidget {
  const _MembershipHeroGraphic();

  static const List<double> _sparkleAngles = [-55, -20, 20, 55];

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 104,
      height: 110,
      child: Stack(
        alignment: Alignment.bottomCenter,
        clipBehavior: Clip.none,
        children: [
          Positioned(
            bottom: 2,
            child: Container(
              width: 86,
              height: 24,
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.16),
                borderRadius: BorderRadius.circular(999),
              ),
            ),
          ),
          for (final angle in _sparkleAngles)
            Positioned(
              bottom: 44,
              child: Transform.rotate(
                angle: angle * 3.14159265 / 180,
                child: Container(
                  width: 3,
                  height: 16,
                  margin: const EdgeInsets.only(bottom: 56),
                  decoration: BoxDecoration(
                    color: _gold.withOpacity(0.85),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
            ),
          Positioned(
            bottom: 18,
            child: Container(
              width: 66,
              height: 66,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(color: _gold.withOpacity(0.6), blurRadius: 26, spreadRadius: 2),
                ],
              ),
              child: ClipOval(
                child: Image.asset(
                  'assets/images/gold_crown.webp',
                  width: 66,
                  height: 66,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => Container(
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: LinearGradient(colors: [_gold, _goldDeep]),
                    ),
                    child: const Icon(Icons.star_rounded, color: _goldDark, size: 38),
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
    required this.branding,
    required this.onSubscribe,
  });

  final Map<String, dynamic> plan;
  final bool isBusy;
  final BrandPalette palette;
  final AppBranding branding;
  final VoidCallback onSubscribe;

  @override
  Widget build(BuildContext context) {
    final price = (plan['price'] as num?)?.toDouble() ?? 0;
    final compareAtPrice = (plan['compare_at_price'] as num?)?.toDouble();
    final period = plan['billing_period']?.toString() ?? 'monthly';
    final trialDays = plan['trial_days'] as int?;
    // The title is composed from the real app branding, not the admin's
    // free-text plan name -- a white-labelled build should always read
    // "<App Name> Gold", even if the plan row itself was typed/copied under
    // a different brand's name.
    final nameLead = branding.displayName;
    const nameHighlight = 'Gold';

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: palette.primary.withOpacity(0.18)),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 18, offset: const Offset(0, 8)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipOval(
                child: Image.asset(
                  'assets/images/gold_crown.webp',
                  width: 46,
                  height: 46,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => Container(
                    width: 46,
                    height: 46,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: LinearGradient(colors: [_gold, _goldDeep]),
                    ),
                    child: const Icon(Icons.workspace_premium_rounded, color: _goldDark, size: 24),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: RichText(
                    overflow: TextOverflow.ellipsis,
                    text: TextSpan(
                      style: const TextStyle(fontSize: 23, fontWeight: FontWeight.w900, color: FoodFlowTheme.ink),
                      children: [
                        if (nameLead.isNotEmpty) TextSpan(text: '$nameLead '),
                        TextSpan(text: nameHighlight, style: TextStyle(color: palette.primary)),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  if (compareAtPrice != null && compareAtPrice > price)
                    Text(
                      formatCurrency(context, compareAtPrice),
                      style: TextStyle(
                        color: FoodFlowTheme.faint,
                        fontSize: 12.5,
                        decoration: TextDecoration.lineThrough,
                      ),
                    ),
                  Text(
                    formatCurrency(context, price),
                    style: TextStyle(color: palette.primary, fontSize: 19, fontWeight: FontWeight.w900, height: 1.15),
                  ),
                  Text('per $period', style: TextStyle(color: FoodFlowTheme.muted, fontSize: 11)),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),
          _MembershipPill(
            text: 'FREE DELIVERY ON EVERY ORDER',
            icon: Icons.local_shipping_rounded,
            palette: palette,
          ),
          if ((trialDays ?? 0) > 0) ...[
            const SizedBox(height: 8),
            _MembershipPill(text: '$trialDays-day free trial', palette: palette),
          ],
          const SizedBox(height: 18),
          _MembershipSubscribeButton(
            label: (trialDays ?? 0) > 0 ? 'Start Free Trial' : 'Subscribe Now',
            isBusy: isBusy,
            palette: palette,
            onTap: isBusy ? null : onSubscribe,
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              const Expanded(
                child: _MembershipFootNote(
                  icon: Icons.shopping_cart_outlined,
                  // Matches the free-delivery benefit already stated above --
                  // this plan's waiver has no minimum order requirement.
                  label: 'No minimum order',
                ),
              ),
              Container(width: 1, height: 28, color: const Color(0xFFE5E7EB)),
              const Expanded(
                child: _MembershipFootNote(
                  icon: Icons.history_rounded,
                  // Matches the real cancel flow below -- membership can be
                  // cancelled at any time, not just at period end.
                  label: 'Cancel anytime',
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Glossy, gradient-filled "3D" button -- a lighter tint at the top fading
/// to the brand color, a thin inner highlight along the top edge, and a
/// tinted drop shadow for lift, instead of a flat single-color fill.
class _MembershipSubscribeButton extends StatelessWidget {
  const _MembershipSubscribeButton({
    required this.label,
    required this.isBusy,
    required this.palette,
    required this.onTap,
  });

  final String label;
  final bool isBusy;
  final BrandPalette palette;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final light = Color.lerp(palette.primary, Colors.white, 0.18) ?? palette.primary;
    final deep = Color.lerp(palette.primary, Colors.black, 0.28) ?? palette.primary;

    const radius = 999.0;

    // The gradient/ink/border layer is hard-clipped by ClipRRect, and the
    // shadow lives on a separate, unclipped outer Container -- keeping
    // these apart avoids the square corner artifacts that can leak through
    // when a single rounded BoxDecoration tries to paint both a shadow and
    // a clipped InkWell ripple at once.
    return SizedBox(
      width: double.infinity,
      height: 56,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(radius),
          boxShadow: [
            BoxShadow(color: deep.withOpacity(0.45), blurRadius: 16, offset: const Offset(0, 8)),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(radius),
          child: Material(
            color: Colors.transparent,
            child: Ink(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [light, palette.primary, deep],
                  stops: const [0, 0.5, 1],
                ),
              ),
              child: InkWell(
                onTap: onTap,
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(radius),
                    border: Border.all(color: Colors.white.withOpacity(0.28), width: 1),
                  ),
                  alignment: Alignment.center,
                  child: isBusy
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                        )
                      : Row(
                          mainAxisSize: MainAxisSize.min,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              label,
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                                shadows: [Shadow(color: Colors.black26, blurRadius: 3, offset: Offset(0, 1))],
                              ),
                            ),
                            const SizedBox(width: 8),
                            const Icon(Icons.arrow_forward_rounded, size: 19, color: Colors.white),
                          ],
                        ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _MembershipFootNote extends StatelessWidget {
  const _MembershipFootNote({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
          width: 26,
          height: 26,
          decoration: BoxDecoration(color: _gold.withOpacity(0.16), shape: BoxShape.circle),
          child: Icon(icon, size: 14, color: _goldDeep),
        ),
        const SizedBox(width: 6),
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(color: FoodFlowTheme.muted, fontSize: 11.5, fontWeight: FontWeight.w700),
          ),
        ),
      ],
    );
  }
}

class _MembershipPill extends StatelessWidget {
  const _MembershipPill({required this.text, required this.palette, this.icon});

  final String text;
  final BrandPalette palette;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: palette.primarySoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: palette.primary.withOpacity(0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 13, color: palette.primary),
            const SizedBox(width: 5),
          ],
          Text(
            text,
            style: TextStyle(color: palette.primary, fontSize: 11, fontWeight: FontWeight.w800, letterSpacing: 0.2),
          ),
        ],
      ),
    );
  }
}
