import 'package:flutter/material.dart';

import '../../../config/api_constants.dart';
import '../../../config/app_config.dart';
import '../../../services/api_service.dart';
import '../../../theme/foodflow_theme.dart';
import '../../../theme/aurora_theme.dart';
import '../../../widgets/aurora/aurora.dart';
import 'legal_document_screen.dart';

/// Fallbacks shown only until an admin fills the matching field in the admin
/// panel (Settings → Privacy & legal). Live copy comes from `GET /content/legal`.
const String kTermsFallback =
    'Restaurant onboarding, menu operations, payouts, cancellations and account '
    'usage are governed by the platform partner terms. By using this app you '
    'agree to keep your menu, pricing, availability and store timings accurate, '
    'to prepare accepted orders on time, and to follow the platform policies on '
    'cancellations, refunds and settlements.';

const String kPrivacyFallback =
    'We process customer, restaurant, staff, delivery, order, payout, device, '
    'support and location data to operate the platform: to place and deliver '
    'orders, run partner operations, prevent fraud, provide support and meet '
    'legal obligations. Data is retained only as long as needed for those '
    'purposes or as required by law.';

const String kRefundFallback =
    'Customer refunds and their settlement effects are governed by the active '
    'platform refund policy. Refund eligibility depends on payment status, '
    'restaurant acceptance, delivery progress and support review. Approved '
    'customer refunds may be adjusted against your payouts.';

const String kAccountDeletionFallback = '''
How to request deletion
You can request deletion from Help & Support in the app, or by emailing the support address below with the subject "Account Deletion Request". Include your registered phone number or email, your account type (restaurant partner or staff), and the restaurant / outlet name.

Verification and processing
The platform may verify account ownership before processing deletion. Verified requests are generally completed within 30 days. Open orders, pending refunds, unsettled payouts, chargebacks, disputes or legal obligations may delay final deletion. After verification, account access may be disabled while deletion or anonymisation completes.

Data deleted or anonymised
Profile information (name, email, phone, preferences); login credentials, tokens and notification preferences; saved addresses not tied to completed transactions; restaurant profile, staff access, menus and operational settings where no active business obligation remains; support messages not needed for an active issue.

Data we may retain
Order, invoice, payment, payout, refund, tax and settlement records may be retained for up to 8 years or longer where required by law. Fraud-prevention, audit and security logs may be retained where needed to protect users and the platform. Support and complaint records may be retained for up to 3 years or for the duration of an active dispute. Backup copies may persist up to 90 days. Aggregated or anonymised analytics that cannot identify you may be retained.

Restaurant partner and staff accounts
Deleting a restaurant account may affect operations, payouts, order history, tax records, support cases and compliance documents. Staff access can also be removed by the restaurant owner from Staff Management.
''';

class RestaurantLegalScreen extends StatefulWidget {
  const RestaurantLegalScreen({super.key});

  @override
  State<RestaurantLegalScreen> createState() => _RestaurantLegalScreenState();
}

class _RestaurantLegalScreenState extends State<RestaurantLegalScreen> {
  final ApiService _api = ApiService();
  String _contactEmail = AppConfig.supportEmail;

  @override
  void initState() {
    super.initState();
    _loadContact();
  }

  void _apply(dynamic response) {
    if (response is Map && response['data'] is Map) {
      final email = response['data']['contact_email']?.toString().trim();
      if (email != null && email.isNotEmpty && mounted) {
        setState(() => _contactEmail = email);
      }
    }
  }

  Future<void> _loadContact() async {
    try {
      _apply(await _api.getWithCache(
        ApiConstants.legalContent,
        onCache: _apply,
      ));
    } catch (_) {}
  }

  void _open(String title, String key, String fallback, {String? intro}) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => LegalDocumentScreen(
          title: title,
          contentKey: key,
          fallbackBody: fallback,
          intro: intro,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top + 64;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Text('Privacy & legal',
            style: TextStyle(
                color: foodflow.ink,
                fontSize: 17,
                fontWeight: FontWeight.w900)),
      ),
      body: Stack(children: [
        ...AuroraTheme.auroraBlobs(),
        ListView(
          padding: EdgeInsets.fromLTRB(16, topPad, 16, 28),
          children: [
            _LegalRow(
              icon: Icons.person_remove_rounded,
              title: 'Account deletion policy',
              subtitle: 'How to request deletion and what data is kept',
              onTap: () => _open(
                'Account deletion policy',
                'account_deletion',
                kAccountDeletionFallback,
                intro:
                    'Applies to restaurant partner and staff accounts on this platform.',
              ),
            ),
            _LegalRow(
              icon: Icons.gavel_rounded,
              title: 'Terms of Service',
              subtitle: 'Partner terms for menu, orders and payouts',
              onTap: () => _open(
                  'Terms of Service', 'terms', kTermsFallback),
            ),
            _LegalRow(
              icon: Icons.shield_outlined,
              title: 'Privacy Policy',
              subtitle: 'What data we process and why',
              onTap: () => _open(
                  'Privacy Policy', 'privacy', kPrivacyFallback),
            ),
            _LegalRow(
              icon: Icons.currency_exchange_rounded,
              title: 'Refund Policy',
              subtitle: 'Customer refunds and settlement effects',
              onTap: () =>
                  _open('Refund Policy', 'refund', kRefundFallback),
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: foodflow.surfaceColor,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: foodflow.line),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.alternate_email_rounded,
                      size: 18, color: foodflow.muted),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Legal contact',
                            style: TextStyle(
                                color: foodflow.ink,
                                fontWeight: FontWeight.w900,
                                fontSize: 13)),
                        const SizedBox(height: 4),
                        Text('Email: $_contactEmail',
                            style: TextStyle(
                                color: FoodFlowTheme.muted,
                                height: 1.4,
                                fontSize: 12)),
                        Text('Phone: ${AppConfig.supportPhone}',
                            style: TextStyle(
                                color: FoodFlowTheme.muted,
                                height: 1.4,
                                fontSize: 12)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ]),
    );
  }
}

class _LegalRow extends StatelessWidget {
  const _LegalRow({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: foodflow.line),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(9),
                  decoration: BoxDecoration(
                    color: foodflow.orange.withOpacity(0.10),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, color: foodflow.orange, size: 18),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title,
                          style: TextStyle(
                              color: foodflow.ink,
                              fontWeight: FontWeight.w900,
                              fontSize: 14)),
                      const SizedBox(height: 2),
                      Text(subtitle,
                          style: TextStyle(
                              color: foodflow.muted,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w600)),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right_rounded, color: foodflow.faint),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
