// lib/screens/restaurant/profile/restaurant_profile_screen.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../providers/auth_provider.dart';
import '../../../providers/theme_provider.dart';
import '../../../theme/foodflow_theme.dart';
import '../../../theme/aurora_theme.dart';
import '../../../widgets/aurora/aurora.dart';

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
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        leading: const BackButton(),
        title: Text('Profile',
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 18,
              fontWeight: FontWeight.w900,
            )),
      ),
      body: Stack(children: [
        Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(color: foodflow.canvas),
            child: Stack(children: AuroraTheme.auroraBlobs()),
          ),
        ),
        Positioned.fill(
          child: ListView(
            padding: EdgeInsets.fromLTRB(
                16, MediaQuery.of(context).padding.top + 64, 16, 40),
            children: [
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [foodflow.orange, foodflow.orangeDark],
                  ),
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: foodflow.orange.withOpacity(0.3),
                      blurRadius: 24,
                      offset: const Offset(0, 12),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 30,
                      backgroundColor: Colors.white.withOpacity(0.18),
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
                          Text(title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                              )),
                          const SizedBox(height: 4),
                          Text(subtitle,
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.82),
                                fontWeight: FontWeight.w700,
                              )),
                          if (user?.email.isNotEmpty == true) ...[
                            const SizedBox(height: 6),
                            Text(user!.email,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: Colors.white.withOpacity(0.74),
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                )),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const _ProfileGroupLabel('Restaurant'),
              _ProfileMenuItem(
                icon: Icons.storefront_outlined,
                title: 'Restaurant details',
                subtitle: 'Name, contact, description, minimum order',
                onTap: () =>
                    Navigator.pushNamed(context, '/restaurant/profile/edit'),
              ),
              _ProfileMenuItem(
                icon: Icons.location_on_outlined,
                title: 'Location',
                subtitle: 'Address, map pin and location requests',
                onTap: () => Navigator.pushNamed(
                    context, '/restaurant/profile/location'),
              ),
              _ProfileMenuItem(
                icon: Icons.account_balance_outlined,
                title: 'Bank details',
                subtitle: 'Payout account and settlement',
                onTap: () =>
                    Navigator.pushNamed(context, '/restaurant/profile/bank'),
              ),
              const _ProfileGroupLabel('Appearance'),
              const _AppearancePicker(),
              const _ProfileGroupLabel('Support'),
              _ProfileMenuItem(
                icon: Icons.help_outline,
                title: 'Help & support',
                subtitle: 'Reach support and common questions',
                onTap: () =>
                    Navigator.pushNamed(context, '/restaurant/profile/help'),
              ),
              _ProfileMenuItem(
                icon: Icons.gavel_outlined,
                title: 'Legal',
                subtitle: 'Policies, terms and documents',
                onTap: () =>
                    Navigator.pushNamed(context, '/restaurant/profile/legal'),
              ),
              const SizedBox(height: 14),
              _ProfileMenuItem(
                icon: Icons.person_remove_outlined,
                title: 'Delete account',
                subtitle: 'Permanently remove this restaurant login',
                danger: true,
                onTap: () => _confirmDeleteAccount(context),
              ),
            ],
          ),
        ),
      ]),
    );
  }
}

class _ProfileGroupLabel extends StatelessWidget {
  const _ProfileGroupLabel(this.label);
  final String label;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 20, 4, 10),
        child: Text(
          label.toUpperCase(),
          style: TextStyle(
            color: foodflow.muted,
            fontSize: 11,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.2,
          ),
        ),
      );
}

class _AppearancePicker extends StatelessWidget {
  const _AppearancePicker();
  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ThemeProvider>();
    const modes = [
      (ThemeMode.system, 'System', Icons.brightness_auto_rounded),
      (ThemeMode.light, 'Light', Icons.light_mode_rounded),
      (ThemeMode.dark, 'Dark', Icons.dark_mode_rounded),
    ];
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: foodflow.line),
      ),
      child: Row(
        children: [
          for (final (mode, label, icon) in modes)
            Expanded(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => provider.setThemeMode(mode),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  decoration: BoxDecoration(
                    color: provider.themeMode == mode
                        ? foodflow.orange.withOpacity(0.16)
                        : Colors.transparent,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: [
                      Icon(icon,
                          size: 18,
                          color: provider.themeMode == mode
                              ? foodflow.orange
                              : foodflow.muted),
                      const SizedBox(height: 4),
                      Text(label,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: provider.themeMode == mode
                                ? foodflow.orange
                                : foodflow.muted,
                          )),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _ProfileMenuItem extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final bool danger;

  const _ProfileMenuItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.danger = false,
  });

  @override
  Widget build(BuildContext context) {
    final tint = danger ? foodflow.danger : foodflow.orange;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
        borderRadius: BorderRadius.circular(16),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Ink(
            padding: const EdgeInsets.all(13),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: foodflow.line),
            ),
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: tint.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(icon, color: tint, size: 21),
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title,
                          style: TextStyle(
                            color: danger ? foodflow.danger : foodflow.ink,
                            fontSize: 14.5,
                            fontWeight: FontWeight.w900,
                          )),
                      const SizedBox(height: 3),
                      Text(subtitle,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: foodflow.muted,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                          )),
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
