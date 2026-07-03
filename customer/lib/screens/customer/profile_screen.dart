import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../models/user.dart';
import '../../providers/auth_provider.dart';
import '../../providers/order_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/customer/account_chrome.dart';
import '../../widgets/common/app_cached_image.dart';
import 'edit_profile_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _isLoading = false;
  bool _isLoggingOut = false;
  double _walletBalance = 0;
  int _savedAddressCount = 0;
  int _offersCount = 0;
  final ApiService _api = ApiService();

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    if (!mounted) return;
    setState(() => _isLoading = true);

    try {
      await Future.wait([
        context.read<AuthProvider>().loadUser(),
        context.read<OrderProvider>().fetchMyOrders(),
        _loadProfileStats(),
      ]);
    } catch (e) {
      debugPrint('Error loading profile data: $e');
    }

    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _loadProfileStats() async {
    Future<dynamic> safelyLoad(String endpoint) async {
      try {
        return await _api.get(endpoint);
      } catch (error) {
        debugPrint('Could not load profile stat $endpoint: $error');
        return null;
      }
    }

    final results = await Future.wait<dynamic>([
      safelyLoad(ApiConstants.wallet),
      safelyLoad(ApiConstants.addresses),
      safelyLoad(ApiConstants.activeOffers),
    ]);

    final walletResponse = results[0];
    final walletData =
        walletResponse is Map ? walletResponse['data'] : null;
    final wallet = walletData is Map ? walletData['wallet'] : null;
    _walletBalance = double.tryParse('${wallet is Map ? wallet['balance'] : 0}') ?? 0;

    final addressResponse = results[1];
    final addressData =
        addressResponse is Map ? addressResponse['data'] : addressResponse;
    final addressItems = addressData is List
        ? addressData
        : addressData is Map && addressData['data'] is List
            ? addressData['data'] as List
            : const <dynamic>[];
    _savedAddressCount = addressItems.length;

    final offerResponse = results[2];
    final offerData = offerResponse is Map ? offerResponse['data'] : offerResponse;
    final offerItems = offerData is List
        ? offerData
        : offerData is Map && offerData['data'] is List
            ? offerData['data'] as List
            : const <dynamic>[];
    _offersCount = offerItems.length;
  }

  Future<void> _refreshData() async {
    await _loadData();
  }

  Future<void> _openEditProfileScreen() async {
    final updated = await Navigator.of(
      context,
    ).push<bool>(MaterialPageRoute(builder: (_) => const EditProfileScreen()));

    if (updated == true && mounted) {
      await _loadData();
    }
  }

  Future<bool> _handleBackNavigation() async {
    final authProvider = context.read<AuthProvider>();
    if (authProvider.isAuthenticated && authProvider.canUseCurrentApp) {
      Navigator.pushNamedAndRemoveUntil(context, '/home', (route) => false);
      return false;
    }
    return true;
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    final orderProvider = context.watch<OrderProvider>();
    final scheme = Theme.of(context).colorScheme;
    final user = authProvider.currentUser;

    return WillPopScope(
      onWillPop: _handleBackNavigation,
      child: Scaffold(
        backgroundColor: accountCanvas,
        body: SafeArea(
          child: RefreshIndicator(
            onRefresh: _refreshData,
            color: scheme.primary,
            child: Stack(
              children: [
                ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(18, 14, 18, 116),
                    children: [
                      _ProfileTopBar(
                        canPop: Navigator.canPop(context),
                        onBack: _handleBackNavigation,
                      ),
                      const SizedBox(height: 16),
                      _ProfileHeroWithStats(
                        user: user,
                        orderCount: orderProvider.orders.length,
                        walletBalance: _walletBalance,
                        savedAddressCount: _savedAddressCount,
                        offersCount: _offersCount,
                        onEdit: _openEditProfileScreen,
                      ),
                      const SizedBox(height: 72),
                      _ProfileSectionCard(
                        title: 'Account',
                        children: [
                          _ProfileMenuTile(
                            icon: LucideIcons.user_round_pen,
                            title: 'Edit Profile',
                            subtitle: 'Update your personal information',
                            onTap: _openEditProfileScreen,
                          ),
                          _ProfileMenuTile(
                            icon: LucideIcons.map_pin,
                            title: 'Addresses',
                            subtitle: 'Manage your saved addresses',
                            onTap: () =>
                                Navigator.pushNamed(context, '/addresses'),
                          ),
                          _ProfileMenuTile(
                            icon: LucideIcons.wallet_cards,
                            title: 'Payments & Wallet',
                            subtitle: 'Payment methods, wallet, refund history',
                            onTap: () =>
                                Navigator.pushNamed(context, '/wallet'),
                          ),
                          _ProfileMenuTile(
                            icon: LucideIcons.bell,
                            title: 'Notifications',
                            subtitle: 'Manage your notification preferences',
                            onTap: () =>
                                Navigator.pushNamed(context, '/notifications'),
                          ),
                          _ProfileMenuTile(
                            icon: LucideIcons.shield_check,
                            title: 'Privacy & Security',
                            subtitle: 'Privacy settings and account security',
                            onTap: () =>
                                Navigator.pushNamed(context, '/privacy-legal'),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      _ProfileSectionCard(
                        title: 'Orders & More',
                        children: [
                          _ProfileMenuTile(
                            icon: LucideIcons.shopping_bag,
                            title: 'My Orders',
                            subtitle: 'Track current orders and view history',
                            onTap: () =>
                                Navigator.pushNamed(context, '/orders'),
                          ),
                          _ProfileMenuTile(
                            icon: LucideIcons.calendar_days,
                            title: 'Dining Reservations',
                            subtitle: 'View and manage your reservations',
                            iconColor: const Color(0xFFF43F5E),
                            iconBackground: const Color(0xFFFFEEF1),
                            onTap: () => Navigator.pushNamed(
                              context,
                              '/dining/bookings',
                            ),
                          ),
                          _ProfileMenuTile(
                            icon: LucideIcons.heart,
                            title: 'Favorites',
                            subtitle: 'Your favorite restaurants and items',
                            iconColor: const Color(0xFFF59E0B),
                            iconBackground: const Color(0xFFFFF4DE),
                            onTap: () => Navigator.pushNamed(
                              context,
                              '/saved-restaurants',
                            ),
                          ),
                          _ProfileMenuTile(
                            icon: LucideIcons.ticket_percent,
                            title: 'Coupons & Offers',
                            subtitle: 'View all available offers and discounts',
                            iconColor: const Color(0xFF14B8A6),
                            iconBackground: const Color(0xFFE0F8F5),
                            onTap: () =>
                                Navigator.pushNamed(context, '/offers'),
                          ),
                          _ProfileMenuTile(
                            icon: LucideIcons.headset,
                            title: 'Help Center',
                            subtitle: 'FAQs, chat support and more',
                            iconColor: const Color(0xFF3B82F6),
                            iconBackground: const Color(0xFFEAF3FF),
                            onTap: () =>
                                Navigator.pushNamed(context, '/support'),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      OutlinedButton.icon(
                        onPressed: _isLoggingOut ? null : _showLogoutDialog,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: const Color(0xFFFF2D2D),
                          side: BorderSide(
                            color: const Color(0xFFFF2D2D).withOpacity(0.62),
                          ),
                          minimumSize: const Size.fromHeight(52),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(18),
                          ),
                        ),
                        icon: _isLoggingOut
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Color(0xFFFF2D2D),
                                ),
                              )
                            : const Icon(LucideIcons.log_out, size: 18),
                        label: const Text(
                          'Logout',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                  ),
                if (_isLoading)
                  const Positioned(
                    left: 0,
                    right: 0,
                    top: 0,
                    child: LinearProgressIndicator(minHeight: 2),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _showLogoutDialog() {
    final scheme = Theme.of(context).colorScheme;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return Dialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: scheme.primary.withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    LucideIcons.log_out,
                    size: 34,
                    color: scheme.primary,
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Logout',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Are you sure you want to logout from your account?',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 14,
                    color: FoodFlowTheme.muted,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 24),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.pop(dialogContext),
                        child: const Text('Cancel'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () async {
                          Navigator.pop(dialogContext);
                          if (!mounted) return;
                          setState(() => _isLoggingOut = true);
                          try {
                            await context.read<AuthProvider>().logout();
                            if (!mounted) return;
                            Navigator.pushNamedAndRemoveUntil(
                              context,
                              '/login',
                              (route) => false,
                            );
                          } finally {
                            if (mounted) {
                              setState(() => _isLoggingOut = false);
                            }
                          }
                        },
                        child: const Text('Logout'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ProfileTopBar extends StatelessWidget {
  const _ProfileTopBar({
    required this.canPop,
    required this.onBack,
  });

  final bool canPop;
  final Future<bool> Function() onBack;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      children: [
        if (canPop)
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: InkWell(
              onTap: () {
                onBack();
              },
              borderRadius: BorderRadius.circular(16),
              child: Container(
                width: 44,
                height: 44,
                decoration: FoodFlowTheme.softSurface(radius: 16),
                child: const Icon(
                  LucideIcons.arrow_left,
                  size: 20,
                  color: FoodFlowTheme.ink,
                ),
              ),
            ),
          ),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Profile',
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: const Color(0xFF081033),
                      fontSize: 29,
                      fontWeight: FontWeight.w900,
                    ),
              ),
              const SizedBox(height: 2),
              const Text(
                'Manage your account and preferences',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 15,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
        _RoundHeaderButton(
          icon: LucideIcons.bell,
          color: scheme.primary,
          showDot: true,
          onTap: () => Navigator.pushNamed(context, '/notifications'),
        ),
        const SizedBox(width: 10),
        _RoundHeaderButton(
          icon: LucideIcons.settings,
          color: const Color(0xFF111827),
          onTap: () => Navigator.pushNamed(context, '/privacy-legal'),
        ),
      ],
    );
  }
}

class _RoundHeaderButton extends StatelessWidget {
  const _RoundHeaderButton({
    required this.icon,
    required this.color,
    required this.onTap,
    this.showDot = false,
  });

  final IconData icon;
  final Color color;
  final VoidCallback onTap;
  final bool showDot;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Container(
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.9),
          shape: BoxShape.circle,
          border: Border.all(color: accountBorder),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.035),
              blurRadius: 14,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Stack(
          alignment: Alignment.center,
          children: [
            Icon(icon, size: 22, color: color),
            if (showDot)
              Positioned(
                top: 11,
                right: 11,
                child: Container(
                  width: 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: color,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 1.3),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ProfileHeroWithStats extends StatelessWidget {
  const _ProfileHeroWithStats({
    required this.user,
    required this.orderCount,
    required this.walletBalance,
    required this.savedAddressCount,
    required this.offersCount,
    required this.onEdit,
  });

  final User? user;
  final int orderCount;
  final double walletBalance;
  final int savedAddressCount;
  final int offersCount;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final currencySymbol = user?.currencySymbol ?? '₹';

    return Stack(
      clipBehavior: Clip.none,
      children: [
        Container(
          height: 260,
          padding: const EdgeInsets.fromLTRB(22, 24, 22, 84),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                scheme.primary,
                const Color(0xFF4C1D95),
                Color.lerp(scheme.primary, scheme.secondary, 0.45) ??
                    scheme.primary,
              ],
            ),
            borderRadius: BorderRadius.circular(26),
            boxShadow: [
              BoxShadow(
                color: scheme.primary.withOpacity(0.22),
                blurRadius: 24,
                offset: const Offset(0, 14),
              ),
            ],
          ),
          child: Stack(
            children: [
              const Positioned(
                right: -40,
                top: -56,
                child: _HeroGlowCircle(size: 166),
              ),
              Positioned(
                right: 42,
                top: 8,
                child: _HeroDots(color: Colors.white.withOpacity(0.16)),
              ),
              const Positioned(
                left: 82,
                bottom: -116,
                child: _HeroGlowCircle(size: 238, opacity: 0.08),
              ),
              Positioned(
                right: 0,
                top: 0,
                child: OutlinedButton.icon(
                  onPressed: onEdit,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.white,
                    side: BorderSide(color: Colors.white.withOpacity(0.62)),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 11,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                  icon: const Icon(LucideIcons.pencil, size: 15),
                  label: const Text(
                    'Edit Profile',
                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _ProfilePhoto(user: user, onEdit: onEdit),
                  const SizedBox(width: 20),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 50),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user?.name.isNotEmpty == true
                                ? user!.name
                                : 'Guest User',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 25,
                              fontWeight: FontWeight.w900,
                              height: 1,
                            ),
                          ),
                          const SizedBox(height: 14),
                          if ((user?.phone ?? '').isNotEmpty)
                            _HeroContactLine(
                              icon: LucideIcons.phone,
                              text: user!.phone,
                            ),
                          if ((user?.email ?? '').isNotEmpty) ...[
                            const SizedBox(height: 9),
                            _HeroContactLine(
                              icon: LucideIcons.mail,
                              text: user!.email,
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        Positioned(
          left: 18,
          right: 18,
          bottom: -46,
          child: _StatsPanel(
            stats: [
              _ProfileStatData(
                icon: LucideIcons.shopping_bag,
                value: '$orderCount',
                label: 'Total Orders',
                color: scheme.primary,
                background: scheme.primary.withOpacity(0.11),
              ),
              _ProfileStatData(
                icon: LucideIcons.wallet_cards,
                value: '$currencySymbol${walletBalance.toStringAsFixed(2)}',
                label: 'Wallet Balance',
                color: const Color(0xFF10B981),
                background: const Color(0xFFE2F8F1),
              ),
              _ProfileStatData(
                icon: LucideIcons.map_pin,
                value: '$savedAddressCount',
                label: 'Saved Addresses',
                color: const Color(0xFFF97316),
                background: const Color(0xFFFFEDE4),
              ),
              _ProfileStatData(
                icon: LucideIcons.gift,
                value: '$offersCount',
                label: 'Offers & Rewards',
                color: const Color(0xFFEC4899),
                background: const Color(0xFFFFE7F1),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ProfilePhoto extends StatelessWidget {
  const _ProfilePhoto({
    required this.user,
    required this.onEdit,
  });

  final User? user;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Container(
          width: 104,
          height: 104,
          decoration: BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 5),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.12),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: ClipOval(
            child: user?.profileImage?.isNotEmpty == true
                ? AppCachedImage(
                    imageUrl: user!.profileImage!,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => _ProfileAvatarFallback(
                      name: user?.name ?? 'Guest User',
                    ),
                  )
                : _ProfileAvatarFallback(
                    name: user?.name ?? 'Guest User',
                  ),
          ),
        ),
        Positioned(
          right: -2,
          bottom: 0,
          child: InkWell(
            onTap: onEdit,
            borderRadius: BorderRadius.circular(999),
            child: Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 2),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.1),
                    blurRadius: 12,
                    offset: const Offset(0, 5),
                  ),
                ],
              ),
              child: Icon(
                LucideIcons.camera,
                color: scheme.primary,
                size: 20,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _ProfileAvatarFallback extends StatelessWidget {
  const _ProfileAvatarFallback({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty ? 'G' : name.trim()[0].toUpperCase();
    return Container(
      color: const Color(0xFFF5F5F5),
      alignment: Alignment.center,
      child: Text(
        initial,
        style: TextStyle(
          color: Theme.of(context).colorScheme.primary,
          fontSize: 34,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _HeroContactLine extends StatelessWidget {
  const _HeroContactLine({
    required this.icon,
    required this.text,
  });

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: Colors.white, size: 16),
        const SizedBox(width: 10),
        Expanded(
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              text,
              maxLines: 1,
              style: TextStyle(
                color: Colors.white.withOpacity(0.94),
                fontSize: 13.2,
                fontWeight: FontWeight.w600,
                height: 1.18,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _HeroGlowCircle extends StatelessWidget {
  const _HeroGlowCircle({
    required this.size,
    this.opacity = 0.1,
  });

  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withOpacity(opacity),
      ),
    );
  }
}

class _HeroDots extends StatelessWidget {
  const _HeroDots({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 78,
      height: 72,
      child: GridView.builder(
        physics: const NeverScrollableScrollPhysics(),
        padding: EdgeInsets.zero,
        itemCount: 48,
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 8,
          mainAxisSpacing: 7,
          crossAxisSpacing: 7,
        ),
        itemBuilder: (_, __) => DecoratedBox(
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
          ),
        ),
      ),
    );
  }
}

class _ProfileStatData {
  const _ProfileStatData({
    required this.icon,
    required this.value,
    required this.label,
    required this.color,
    required this.background,
  });

  final IconData icon;
  final String value;
  final String label;
  final Color color;
  final Color background;
}

class _StatsPanel extends StatelessWidget {
  const _StatsPanel({required this.stats});

  final List<_ProfileStatData> stats;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.08),
            blurRadius: 26,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Row(
        children: List.generate(stats.length * 2 - 1, (index) {
          if (index.isOdd) {
            return Container(
              width: 1,
              height: 58,
              margin: const EdgeInsets.symmetric(horizontal: 6),
              color: accountBorder,
            );
          }
          final stat = stats[index ~/ 2];
          return Expanded(child: _ProfileStatItem(stat: stat));
        }),
      ),
    );
  }
}

class _ProfileStatItem extends StatelessWidget {
  const _ProfileStatItem({required this.stat});

  final _ProfileStatData stat;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: stat.background,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Icon(stat.icon, color: stat.color, size: 21),
        ),
        const SizedBox(height: 9),
        Text(
          stat.value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Color(0xFF081033),
            fontSize: 14,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          stat.label,
          maxLines: 2,
          overflow: TextOverflow.visible,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: FoodFlowTheme.muted,
            fontSize: 10.2,
            fontWeight: FontWeight.w600,
            height: 1.08,
          ),
        ),
      ],
    );
  }
}

class _ProfileSectionCard extends StatelessWidget {
  const _ProfileSectionCard({
    required this.title,
    required this.children,
  });

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 2, bottom: 12),
          child: Text(
            title,
            style: const TextStyle(
              color: Color(0xFF081033),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.035),
                blurRadius: 22,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
            child: Column(
              children: List.generate(children.length * 2 - 1, (index) {
                if (index.isOdd) {
                  return const Padding(
                    padding: EdgeInsets.only(left: 62),
                    child: Divider(height: 1, color: accountBorder),
                  );
                }
                return children[index ~/ 2];
              }),
            ),
          ),
        ),
      ],
    );
  }
}

class _ProfileMenuTile extends StatelessWidget {
  const _ProfileMenuTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.iconColor,
    this.iconBackground,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final Color? iconColor;
  final Color? iconBackground;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final resolvedColor = iconColor ?? scheme.primary;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: iconBackground ?? scheme.primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: resolvedColor, size: 21),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: Color(0xFF081033),
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      height: 1.25,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(
              LucideIcons.chevron_right,
              color: FoodFlowTheme.muted,
              size: 20,
            ),
          ],
        ),
      ),
    );
  }
}
