// lib/screens/restaurant/profile/restaurant_profile_screen.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../providers/auth_provider.dart';
import '../../../theme/foodflow_theme.dart';
import '../../../widgets/restaurant/premium_restaurant_widgets.dart';

class RestaurantProfileScreen extends StatelessWidget {
  const RestaurantProfileScreen({Key? key}) : super(key: key);
  Future<void> _confirmDeleteAccount(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete account?'),
        content: const Text(
          'This will permanently delete or anonymize your account data where permitted. Active orders, payouts, tax records, disputes, and legal records may be retained as required.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true || !context.mounted) return;

    final authProvider = context.read<AuthProvider>();
    final deleted = await authProvider.deleteAccount();
    if (!context.mounted) return;

    if (deleted) {
      Navigator.of(context, rootNavigator: true).pushNamedAndRemoveUntil(
        '/login',
        (route) => false,
      );
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(authProvider.error ?? 'Unable to delete account.'),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().currentUser;
    final title = user?.name.trim().isNotEmpty == true
        ? user!.name.trim()
        : 'Restaurant Profile';
    final subtitle = user?.restaurantAccessLabel ?? 'Manage your restaurant';

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: RestaurantPremium.glowPanel(radius: 18),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 30,
                    backgroundColor: Colors.white.withOpacity(0.16),
                    child: Text(
                      title.isNotEmpty ? title[0].toUpperCase() : 'R',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        fontSize: 24,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          subtitle,
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.82),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (user?.email.isNotEmpty == true) ...[
                          const SizedBox(height: 6),
                          Text(
                            user!.email,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Colors.white.withOpacity(0.74),
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            _ProfileMenuItem(
              icon: Icons.storefront_outlined,
              title: 'Restaurant Details',
              subtitle: 'Name, contact info, description and minimum order',
              onTap: () =>
                  Navigator.pushNamed(context, '/restaurant/profile/edit'),
            ),
            _ProfileMenuItem(
              icon: Icons.account_balance_outlined,
              title: 'Bank Details',
              subtitle: 'Payout account and settlement preferences',
              onTap: () =>
                  Navigator.pushNamed(context, '/restaurant/profile/bank'),
            ),
            _ProfileMenuItem(
              icon: Icons.location_on_outlined,
              title: 'Location',
              subtitle: 'Address, map pin and FSSAI-backed location requests',
              onTap: () =>
                  Navigator.pushNamed(context, '/restaurant/profile/location'),
            ),
            _ProfileMenuItem(
              icon: Icons.help_outline,
              title: 'Help & Support',
              subtitle: 'Reach support and common questions',
              onTap: () =>
                  Navigator.pushNamed(context, '/restaurant/profile/help'),
            ),
            _ProfileMenuItem(
              icon: Icons.gavel_outlined,
              title: 'Legal',
              subtitle: 'Policies, terms and partnership documents',
              onTap: () =>
                  Navigator.pushNamed(context, '/restaurant/profile/legal'),
            ),
            const SizedBox(height: 8),
            _ProfileMenuItem(
              icon: Icons.person_remove_outlined,
              title: 'Delete Account',
              subtitle: 'Permanently remove this restaurant login',
              onTap: () => _confirmDeleteAccount(context),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileMenuItem extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _ProfileMenuItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF3E8),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(icon, color: FoodFlowTheme.orange),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right, color: FoodFlowTheme.faint),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
