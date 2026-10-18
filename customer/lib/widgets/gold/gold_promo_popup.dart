// lib/widgets/gold/gold_promo_popup.dart
import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../screens/customer/gold_subscription_screen.dart';
import '../../services/api_service.dart';
import '../../utils/currency_utils.dart';

/// Shown once per app session (not every time the home tab rebuilds) when:
/// (a) at least one active customer-audience membership plan exists
/// ("enabled" in admin -- there is no separate master toggle, admins
/// control this purely via activating/deactivating plans in Settings ->
/// Subscription Plans), and (b) the signed-in customer isn't already a
/// member. Call from the home screen's initState postFrameCallback; this
/// guards everything else internally so it's safe to call unconditionally.
///
/// Light cream + gold "premium membership" card -- a widely used visual
/// language for paid tiers generally (warm neutral background, gold accent,
/// a single bold CTA), not any single brand's protected design. The
/// headline is always the real plan name from the backend, never a
/// hardcoded tier wordmark, and no third-party logo, icon glyph or copy is
/// reproduced here.
bool _hasShownThisSession = false;

const _gold = Color(0xFFBF8F2E);
const _goldDeep = Color(0xFF8C6418);
const _inkBrown = Color(0xFF3A2E12);
const _cta = Color(0xFF1E9E52);

Future<void> maybeShowGoldPromoPopup(BuildContext context) async {
  if (_hasShownThisSession) return;

  final api = ApiService();
  try {
    final currentResponse = await api.get(ApiConstants.goldCurrent);
    if (currentResponse['success'] != true) return;
    final isMember =
        Map<String, dynamic>.from(currentResponse['data'] ?? {})['is_gold_member'] == true;
    if (isMember) return;

    final plansResponse = await api.get(ApiConstants.goldPlans);
    if (plansResponse['success'] != true) return;
    final plans = (plansResponse['data'] as List? ?? [])
        .whereType<Map>()
        .map((row) => Map<String, dynamic>.from(row))
        .toList();
    if (plans.isEmpty) return;

    _hasShownThisSession = true;
    if (!context.mounted) return;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => _MembershipPromoSheet(plan: plans.first),
    );
  } catch (_) {
    // Silent -- a failed promo check must never block the home screen.
  }
}

class _MembershipPromoSheet extends StatelessWidget {
  const _MembershipPromoSheet({required this.plan});

  final Map<String, dynamic> plan;

  @override
  Widget build(BuildContext context) {
    final planName = plan['name']?.toString().trim();
    final price = (plan['price'] as num?)?.toDouble() ?? 0;
    final compareAtPrice = (plan['compare_at_price'] as num?)?.toDouble();
    final period = plan['billing_period']?.toString() ?? 'monthly';
    final validityDays = (plan['validity_days'] as num?)?.toInt();
    final hasPromo = compareAtPrice != null && compareAtPrice > price;
    final description = plan['description']?.toString().trim();
    final periodLabel = validityDays != null && validityDays > 0
        ? '$validityDays days'
        : period;
    final displayName = planName?.isNotEmpty == true ? planName! : 'Membership';

    return SafeArea(
      top: false,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(26),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.28),
              blurRadius: 28,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Stack(
          children: [
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Color(0xFFFFFBF0), Color(0xFFFFF3D6)],
                ),
              ),
              padding: const EdgeInsets.fromLTRB(26, 22, 26, 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const SizedBox(height: 12),
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: const LinearGradient(colors: [_gold, _goldDeep]),
                      boxShadow: [
                        BoxShadow(color: _gold.withOpacity(0.35), blurRadius: 14, offset: const Offset(0, 6)),
                      ],
                    ),
                    child: const Icon(Icons.verified_rounded, color: Colors.white, size: 28),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    displayName,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: _inkBrown,
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 0.3,
                    ),
                  ),
                  if (hasPromo) ...[
                    const SizedBox(height: 4),
                    const Text(
                      'Limited time offer',
                      style: TextStyle(color: _gold, fontSize: 13, fontStyle: FontStyle.italic, fontWeight: FontWeight.w700),
                    ),
                  ],
                  const SizedBox(height: 14),
                  Text(
                    description?.isNotEmpty == true ? description! : 'Free delivery on every order',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: _inkBrown.withOpacity(0.75), fontSize: 14, height: 1.35, fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 18),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        formatCurrency(context, price),
                        style: const TextStyle(color: _inkBrown, fontSize: 30, fontWeight: FontWeight.w900, height: 1),
                      ),
                      const SizedBox(width: 4),
                      Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Text('/$periodLabel', style: TextStyle(color: _inkBrown.withOpacity(0.55), fontSize: 13)),
                      ),
                      if (hasPromo) ...[
                        const SizedBox(width: 10),
                        Padding(
                          padding: const EdgeInsets.only(bottom: 5),
                          child: Text(
                            formatCurrency(context, compareAtPrice),
                            style: TextStyle(
                              color: _inkBrown.withOpacity(0.4),
                              fontSize: 15,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 22),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(14),
                        color: _cta,
                        boxShadow: [
                          BoxShadow(color: _cta.withOpacity(0.35), blurRadius: 16, offset: const Offset(0, 8)),
                        ],
                      ),
                      child: Material(
                        color: Colors.transparent,
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () {
                            Navigator.pop(context);
                            Navigator.of(context).push(
                              MaterialPageRoute(builder: (_) => const GoldSubscriptionScreen()),
                            );
                          },
                          child: Center(
                            child: Text(
                              'Get $displayName Now',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                fontSize: 15.5,
                                letterSpacing: 0.2,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text(
                      'Maybe later',
                      style: TextStyle(color: _gold, fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: GestureDetector(
                onTap: () => Navigator.pop(context),
                child: Container(
                  width: 30,
                  height: 30,
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.08),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(Icons.close_rounded, color: _inkBrown.withOpacity(0.7), size: 18),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
