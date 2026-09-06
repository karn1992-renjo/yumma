import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../providers/auth_provider.dart';
import '../../../../widgets/common/app_cached_image.dart';
import '../../home_experience.dart';
import '../theme/v2_theme.dart';
import '../v2_nav.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class ProfileV2 extends StatelessWidget {
  const ProfileV2({super.key, this.embedded = false, this.onBackToHome});

  final bool embedded;
  final VoidCallback? onBackToHome;

  @override
  Widget build(BuildContext context) {
    final body = _Body(onBackToHome: onBackToHome);
    if (embedded) {
      return Padding(padding: const EdgeInsets.only(top: 8), child: body);
    }
    return V2Scaffold(title: 'Profile', showBack: true, body: body);
  }
}

class _Body extends StatelessWidget {
  const _Body({this.onBackToHome});

  final VoidCallback? onBackToHome;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final user = context.watch<AuthProvider>().currentUser;
    final name = user?.name.trim() ?? 'Guest';
    final sub = user?.phone.trim().isNotEmpty == true
        ? user!.phone
        : (user?.email ?? 'Not signed in');
    final img = user?.profileImage?.trim() ?? '';

    return ListView(
      padding: EdgeInsets.fromLTRB(
          16, 8, 16, MediaQuery.of(context).padding.bottom + 120),
      children: [
        V2Entrance(
          child: GlassPanel(
            radius: 24,
            strong: true,
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: p.accent.withOpacity(0.2),
                    border: Border.all(color: p.accent.withOpacity(0.4)),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: img.isNotEmpty
                      ? AppCachedImage(imageUrl: img, fit: BoxFit.cover)
                      : Center(
                          child: Text(
                            name.isNotEmpty ? name[0].toUpperCase() : 'S',
                            style: TextStyle(
                              color: p.ink,
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        style: TextStyle(
                          color: p.ink,
                          fontSize: 17,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(sub,
                          style:
                              TextStyle(color: p.inkFaint, fontSize: 12.5)),
                    ],
                  ),
                ),
                V2Tappable(
                  onTap: () => v2OpenEditProfile(context),
                  child: Icon(Icons.edit_rounded, size: 18, color: p.inkSoft),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 20),
        _groupLabel(context, 'APPEARANCE'),
        const SizedBox(height: 10),
        GlassPanel(
          radius: 18,
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
          child: ValueListenableBuilder<V2Mode>(
            valueListenable: v2ModeNotifier,
            builder: (context, mode, _) => _ThemeRow(mode: mode),
          ),
        ),
        const SizedBox(height: 20),
        _groupLabel(context, 'ACCOUNT'),
        const SizedBox(height: 10),
        GlassPanel(
          radius: 18,
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Column(
            children: [
              _tile(context, Icons.person_rounded, 'Edit Profile',
                  () => v2OpenEditProfile(context)),
              _divider(context),
              _tile(context, Icons.receipt_long_rounded, 'Order History',
                  () => v2OpenOrders(context)),
              _divider(context),
              _tile(context, Icons.location_on_rounded, 'Saved Addresses',
                  () => v2OpenAddresses(context)),
              _divider(context),
              _tile(context, Icons.favorite_rounded, 'Saved Restaurants',
                  () => v2OpenSavedRestaurants(context)),
            ],
          ),
        ),
        const SizedBox(height: 20),
        _groupLabel(context, 'REWARDS & MONEY'),
        const SizedBox(height: 10),
        GlassPanel(
          radius: 18,
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Column(
            children: [
              _tile(context, Icons.account_balance_wallet_rounded, 'Wallet',
                  () => v2OpenWallet(context)),
              _divider(context),
              _tile(context, Icons.local_offer_rounded, 'Offers & Promos',
                  () => v2OpenOffers(context)),
              _divider(context),
              _tile(context, Icons.card_giftcard_rounded, 'Scratch Cards',
                  () => v2OpenScratchCards(context)),
              _divider(context),
              _tile(context, Icons.people_rounded, 'Refer & Earn',
                  () => v2OpenReferrals(context)),
            ],
          ),
        ),
        const SizedBox(height: 20),
        _groupLabel(context, 'MORE'),
        const SizedBox(height: 10),
        GlassPanel(
          radius: 18,
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Column(
            children: [
              _tile(context, Icons.notifications_rounded, 'Notifications',
                  () => v2OpenNotifications(context)),
              _divider(context),
              _tile(context, Icons.tune_rounded, 'Notification Preferences',
                  () => v2OpenNotificationPrefs(context)),
              _divider(context),
              _tile(context, Icons.headset_mic_rounded, 'Help & Support',
                  () => v2OpenSupport(context)),
              _divider(context),
              _tile(context, Icons.shield_rounded, 'Privacy & Legal',
                  () => v2OpenPrivacy(context)),
              _divider(context),
              _tile(
                context,
                Icons.dashboard_customize_rounded,
                'Switch to Classic Home',
                () async {
                  await setHomeV2Enabled(false);
                },
                accent: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        _groupLabel(context, 'ACCOUNT ACTIONS'),
        const SizedBox(height: 10),
        GlassPanel(
          radius: 18,
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Column(
            children: [
              _tile(context, Icons.logout_rounded, 'Log out',
                  () => _confirmLogout(context)),
              _divider(context),
              _tile(context, Icons.delete_forever_rounded, 'Delete account',
                  () => _confirmDelete(context), danger: true),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final ok = await _confirmSheet(
      context,
      title: 'Log out?',
      message: 'You can sign back in anytime.',
      confirmLabel: 'Log out',
      danger: false,
    );
    if (ok != true) return;
    await context.read<AuthProvider>().logout();
    if (context.mounted) {
      Navigator.of(context)
          .pushNamedAndRemoveUntil('/login', (route) => false);
    }
  }

  Future<void> _confirmDelete(BuildContext context) async {
    final ok = await _confirmSheet(
      context,
      title: 'Delete account?',
      message:
          'This permanently removes your account, orders and rewards. This '
          'cannot be undone.',
      confirmLabel: 'Delete',
      danger: true,
    );
    if (ok != true) return;
    final auth = context.read<AuthProvider>();
    final done = await auth.deleteAccount();
    if (!context.mounted) return;
    if (done) {
      Navigator.of(context)
          .pushNamedAndRemoveUntil('/login', (route) => false);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(auth.error?.replaceFirst('Exception: ', '') ??
            'Unable to delete account. Please try again.'),
      ));
    }
  }

  Future<bool?> _confirmSheet(
    BuildContext context, {
    required String title,
    required String message,
    required String confirmLabel,
    required bool danger,
  }) {
    final p = V2Palette.of(v2ModeNotifier.value);
    return showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => V2Theme(
        palette: p,
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: GlassPanel(
              radius: 22,
              strong: true,
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      style: TextStyle(
                          color: p.ink,
                          fontSize: 17,
                          fontWeight: FontWeight.w900)),
                  const SizedBox(height: 6),
                  Text(message,
                      style: TextStyle(color: p.inkSoft, fontSize: 13)),
                  const SizedBox(height: 18),
                  Row(
                    children: [
                      Expanded(
                        child: V2Tappable(
                          onTap: () =>
                              Navigator.of(sheetContext).pop(false),
                          child: Container(
                            height: 46,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: p.glassTop,
                              borderRadius: BorderRadius.circular(13),
                              border: Border.all(color: p.glassBorder),
                            ),
                            child: Text('Cancel',
                                style: TextStyle(
                                    color: p.inkSoft,
                                    fontWeight: FontWeight.w800)),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: V2Tappable(
                          onTap: () =>
                              Navigator.of(sheetContext).pop(true),
                          child: Container(
                            height: 46,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: danger ? p.danger : p.accent,
                              borderRadius: BorderRadius.circular(13),
                            ),
                            child: Text(confirmLabel,
                                style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900)),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _groupLabel(BuildContext context, String t) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(left: 4),
      child: Text(
        t,
        style: TextStyle(
          color: p.inkFaint,
          fontSize: 11.5,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.8,
        ),
      ),
    );
  }

  Widget _divider(BuildContext context) => Padding(
        padding: const EdgeInsets.only(left: 54),
        child: Divider(height: 1, color: V2Theme.of(context).glassBorder),
      );

  Widget _tile(BuildContext context, IconData icon, String title,
      VoidCallback onTap,
      {bool accent = false, bool danger = false}) {
    final p = V2Theme.of(context);
    final fg = danger ? p.danger : (accent ? p.accent : p.inkSoft);
    return V2Tappable(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        child: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: fg.withOpacity(0.14),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 18, color: fg),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  color: danger ? p.danger : (accent ? p.accent : p.ink),
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: p.inkFaint, size: 22),
          ],
        ),
      ),
    );
  }
}

class _ThemeRow extends StatelessWidget {
  const _ThemeRow({required this.mode});

  final V2Mode mode;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      child: Row(
        children: [
          Icon(
            mode == V2Mode.dark
                ? Icons.dark_mode_rounded
                : Icons.light_mode_rounded,
            size: 20,
            color: p.accent,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              'Theme',
              style: TextStyle(
                color: p.ink,
                fontSize: 14,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          _seg(context, 'Dark', mode == V2Mode.dark,
              () => setV2Mode(V2Mode.dark)),
          const SizedBox(width: 6),
          _seg(context, 'Light', mode == V2Mode.light,
              () => setV2Mode(V2Mode.light)),
        ],
      ),
    );
  }

  Widget _seg(
      BuildContext context, String label, bool active, VoidCallback onTap) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: active ? p.accent : p.glassTop,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: active ? p.accent : p.glassBorder),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: active ? Colors.white : p.inkSoft,
            fontSize: 12,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}
