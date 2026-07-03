import 'package:flutter/material.dart';

import '../../../config/app_config.dart';
import '../../../theme/foodflow_theme.dart';

class AccountDeletionPolicyScreen extends StatelessWidget {
  const AccountDeletionPolicyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Delete Account Policy'),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          _HeaderCard(),
          const SizedBox(height: 14),
          const _PolicySection(
            title: 'How to request deletion',
            body:
                'You can request deletion by emailing support@foodflow.in with the subject "foodflow Account Deletion Request" or by submitting a request from Help & Support in the app.',
            bullets: [
              'Include your registered phone number or email address.',
              'Mention your account type: customer, restaurant partner, staff user, or delivery partner.',
              'Restaurant partners should include the restaurant or outlet name.',
            ],
          ),
          const _PolicySection(
            title: 'Verification and processing',
            body:
                'Adgraph Media Private Limited may verify account ownership before processing deletion. Verified requests are generally completed within 30 days.',
            bullets: [
              'Open orders, pending refunds, unsettled payouts, chargebacks, disputes, or legal obligations may delay final deletion.',
              'After verification, account access may be disabled while deletion or anonymization is completed.',
            ],
          ),
          const _PolicySection(
            title: 'Data deleted or anonymized',
            bullets: [
              'Profile information such as name, email address, phone number, and preferences.',
              'Login credentials, authentication tokens, device tokens, and notification preferences.',
              'Saved addresses and location preferences where they are not tied to completed transactions or legal records.',
              'Restaurant profile, staff access, menus, and operational settings where deletion is permitted and no active business obligation remains.',
              'Support messages and optional content where retention is not required for an active issue or legal purpose.',
            ],
          ),
          const _PolicySection(
            title: 'Data we may retain',
            body:
                'Some records must be retained for legitimate business, legal, tax, accounting, fraud-prevention, security, and compliance purposes.',
            bullets: [
              'Order, invoice, payment, payout, refund, tax, and settlement records may be retained for up to 8 years or longer if required by law.',
              'Fraud-prevention, audit, and security logs may be retained where needed to protect users, partners, and the platform.',
              'Support and complaint records may be retained for up to 3 years or for the duration of an active dispute.',
              'Backup copies may remain for up to 90 days before being overwritten through normal backup cycles.',
              'Aggregated or anonymized analytics that cannot reasonably identify you may be retained.',
            ],
          ),
          const _PolicySection(
            title: 'Restaurant partner and staff accounts',
            body:
                'Restaurant account deletion may affect restaurant operations, payouts, order history, tax records, support cases, and compliance documents. Staff access can also be removed by the restaurant owner or authorized administrator.',
          ),
          const _ContactSection(),
        ],
      ),
    );
  }
}

class _HeaderCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: FoodFlowTheme.brandGradient,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text(
            AppConfig.companyName,
            style: TextStyle(
              color: Colors.white70,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
          SizedBox(height: 8),
          Text(
            'foodflow Account Deletion Policy',
            style: TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w900,
              height: 1.12,
            ),
          ),
          SizedBox(height: 8),
          Text(
            'Last updated: June 8, 2026',
            style: TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _PolicySection extends StatelessWidget {
  const _PolicySection({
    required this.title,
    this.body,
    this.bullets = const [],
  });

  final String title;
  final String? body;
  final List<String> bullets;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: FoodFlowTheme.softSurface(radius: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (body != null) ...[
            const SizedBox(height: 8),
            Text(
              body!,
              style: const TextStyle(
                color: FoodFlowTheme.muted,
                height: 1.45,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          if (bullets.isNotEmpty) ...[
            const SizedBox(height: 10),
            ...bullets.map((item) => _Bullet(text: item)),
          ],
        ],
      ),
    );
  }
}

class _Bullet extends StatelessWidget {
  const _Bullet({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 6,
            height: 6,
            margin: const EdgeInsets.only(top: 8, right: 10),
            decoration: BoxDecoration(
              color: FoodFlowTheme.orange,
              borderRadius: BorderRadius.circular(3),
            ),
          ),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(
                color: FoodFlowTheme.muted,
                height: 1.42,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ContactSection extends StatelessWidget {
  const _ContactSection();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: FoodFlowTheme.softSurface(radius: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Contact',
            style: TextStyle(
              color: FoodFlowTheme.ink,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 10),
          _InfoRow(
            icon: Icons.business_outlined,
            label: AppConfig.companyName,
          ),
          const SizedBox(height: 8),
          _InfoRow(
            icon: Icons.email_outlined,
            label: AppConfig.supportEmail,
          ),
          const SizedBox(height: 8),
          _InfoRow(
            icon: Icons.call_outlined,
            label: AppConfig.supportPhone,
          ),
          const SizedBox(height: 8),
          const _InfoRow(
            icon: Icons.public_outlined,
            label: AppConfig.accountDeletionPolicyUrl,
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
  });

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: FoodFlowTheme.orange, size: 20),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            label,
            style: const TextStyle(
              color: FoodFlowTheme.inkSoft,
              fontWeight: FontWeight.w700,
              height: 1.35,
            ),
          ),
        ),
      ],
    );
  }
}
