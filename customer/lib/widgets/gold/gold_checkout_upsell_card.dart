// lib/widgets/gold/gold_checkout_upsell_card.dart
import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../screens/customer/gold_subscription_screen.dart';
import '../../services/api_service.dart';
import '../../services/app_branding_service.dart';
import '../../theme/brand_palette.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

/// Shown on checkout, below the bill, only when: membership is "enabled"
/// (at least one active customer-audience plan) AND this customer isn't
/// already a member AND this order actually has a delivery fee to save.
/// Fetches its own current-membership/plans state rather than threading it
/// through the whole checkout screen's state, since this is the only place
/// that needs it.
class GoldCheckoutUpsellCard extends StatefulWidget {
  const GoldCheckoutUpsellCard({super.key, required this.deliveryFeeThisOrder});

  final double deliveryFeeThisOrder;

  @override
  State<GoldCheckoutUpsellCard> createState() => _GoldCheckoutUpsellCardState();
}

class _GoldCheckoutUpsellCardState extends State<GoldCheckoutUpsellCard> {
  final ApiService _api = ApiService();
  Map<String, dynamic>? _plan;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final currentResponse = await _api.get(ApiConstants.goldCurrent);
      if (currentResponse['success'] != true) return;
      final isMember = Map<String, dynamic>.from(currentResponse['data'] ?? {})['is_gold_member'] == true;
      if (isMember) {
        if (mounted) setState(() => _loaded = true);
        return;
      }

      final plansResponse = await _api.get(ApiConstants.goldPlans);
      if (plansResponse['success'] != true) return;
      final plans = (plansResponse['data'] as List? ?? [])
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList();

      if (!mounted) return;
      setState(() {
        _plan = plans.isNotEmpty ? plans.first : null;
        _loaded = true;
      });
    } catch (_) {
      if (mounted) setState(() => _loaded = true);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded || _plan == null) return const SizedBox.shrink();

    final palette = BrandPalette.fromBranding(AppBrandingService.instance.cachedOrFallback);
    final planName = _plan!['name']?.toString().trim();
    final price = (_plan!['price'] as num?)?.toDouble() ?? 0;
    final compareAtPrice = (_plan!['compare_at_price'] as num?)?.toDouble();
    final hasPromo = compareAtPrice != null && compareAtPrice > price;

    return GestureDetector(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const GoldSubscriptionScreen()),
      ),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: palette.primarySoft,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: palette.primary.withOpacity(0.3)),
        ),
        child: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(color: palette.primary, shape: BoxShape.circle),
              child: const Icon(Icons.star_rounded, color: Colors.white, size: 18),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Save ${formatCurrency(context, widget.deliveryFeeThisOrder)} with ${planName?.isNotEmpty == true ? planName : 'a membership'}',
                    style: TextStyle(color: palette.secondary, fontSize: 13.5, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 3),
                  Row(
                    children: [
                      Text(
                        'Free delivery on every order • ',
                        style: TextStyle(color: FoodFlowTheme.muted, fontSize: 11.5),
                      ),
                      if (hasPromo) ...[
                        Text(
                          formatCurrency(context, compareAtPrice),
                          style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 11.5,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                        const SizedBox(width: 4),
                      ],
                      Text(
                        '${formatCurrency(context, price)}/${_plan!['billing_period'] ?? 'month'}',
                        style: TextStyle(color: palette.primary, fontSize: 11.5, fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: palette.primary.withOpacity(0.7)),
          ],
        ),
      ),
    );
  }
}
