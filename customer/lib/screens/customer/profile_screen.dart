import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:provider/provider.dart';

import '../../config/api_constants.dart';
import '../../models/user.dart';
import '../../providers/auth_provider.dart';
import '../../providers/order_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/app_cached_image.dart';
import 'edit_profile_screen.dart';

const _profileText = FoodFlowTheme.ink;
const _profileSubtext = FoodFlowTheme.muted;
const _profileLine = FoodFlowTheme.line;
const _profileSuccess = FoodFlowTheme.success;
const _profileBg = Colors.white;
const _profileAccent = Color(0xFFFF6A00);

Color _profilePrimary(BuildContext context) =>
    FoodFlowTheme.brandPrimary(context);

BoxDecoration _profilePanelDecoration(BuildContext context,
    {double radius = 28}) {
  return BoxDecoration(
    gradient: const LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: [
        FoodFlowTheme.surfaceColor,
        FoodFlowTheme.surfaceWarm,
        FoodFlowTheme.surfaceCool,
      ],
      stops: [0, 0.56, 1],
    ),
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: _profileLine),
    boxShadow: [
      BoxShadow(
        color: Colors.white.withOpacity(0.95),
        blurRadius: 3,
        offset: const Offset(-2, -2),
      ),
      BoxShadow(
        color: _profilePrimary(context).withOpacity(0.13),
        blurRadius: 22,
        spreadRadius: -3,
        offset: const Offset(0, 14),
      ),
      BoxShadow(
        color: Colors.black.withOpacity(0.06),
        blurRadius: 24,
        spreadRadius: -4,
        offset: const Offset(0, 14),
      ),
    ],
  );
}

BoxDecoration _profileCircleDecoration(Color color) {
  return BoxDecoration(
    color: Colors.white,
    shape: BoxShape.circle,
    border: Border.all(color: _profileLine),
    boxShadow: [
      BoxShadow(
        color: color.withOpacity(0.14),
        blurRadius: 18,
        offset: const Offset(0, 10),
      ),
      BoxShadow(
        color: Colors.black.withOpacity(0.05),
        blurRadius: 16,
        offset: const Offset(0, 8),
      ),
    ],
  );
}

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, this.onBackToHome});

  final VoidCallback? onBackToHome;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _isLoading = false;
  bool _isLoggingOut = false;
  String _appVersionLabel = '';
  double _walletBalance = 0;
  int _savedAddressCount = 0;
  int _savedRestaurantCount = 0;
  int _offersCount = 0;
  final ApiService _api = ApiService();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _loadData();
        _loadAppVersion();
      }
    });
  }

  Future<void> _loadAppVersion() async {
    try {
      final packageInfo = await PackageInfo.fromPlatform();
      final version = packageInfo.version.trim();
      final buildNumber = packageInfo.buildNumber.trim();
      final label = buildNumber.isEmpty ? version : '$version+$buildNumber';

      if (mounted) {
        setState(() => _appVersionLabel = label);
      }
    } catch (error) {
      debugPrint('Could not load app version: $error');
    }
  }

  Future<void> _loadData({bool forceRefresh = false}) async {
    if (!mounted) return;
    setState(() => _isLoading = true);

    final authProvider = context.read<AuthProvider>();
    final orderProvider = context.read<OrderProvider>();

    try {
      await authProvider.loadUser(forceRefresh: forceRefresh);

      if (forceRefresh) {
        await Future.wait([
          orderProvider.fetchMyOrders(notifyLoading: false),
          _loadProfileStats(cacheFirst: false, refreshCached: false),
        ]);
      } else {
        await _loadProfileStats(cacheFirst: true, refreshCached: true);
        unawaited(_refreshProfileDataInBackground());
      }
    } catch (e) {
      debugPrint('Error loading profile data: $e');
    }

    if (mounted) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _refreshProfileDataInBackground() async {
    try {
      await Future.wait([
        context.read<AuthProvider>().loadUser(forceRefresh: true),
        context.read<OrderProvider>().fetchMyOrders(notifyLoading: false),
      ]);
    } catch (error) {
      debugPrint('Background profile refresh failed: $error');
    }
  }

  Future<void> _loadProfileStats({
    required bool cacheFirst,
    required bool refreshCached,
  }) async {
    Future<dynamic> safelyLoad(String endpoint) async {
      try {
        return await _api.get(
          endpoint,
          cachePolicy: ApiCachePolicy.discovery,
          cacheFirst: cacheFirst,
          refreshCached: refreshCached,
          onCacheRefreshed: (freshData) {
            if (!mounted) return;
            setState(() => _applyProfileStatResponse(endpoint, freshData));
          },
        );
      } catch (error) {
        debugPrint('Could not load profile stat $endpoint: $error');
        return null;
      }
    }

    final endpoints = [
      ApiConstants.wallet,
      ApiConstants.addresses,
      ApiConstants.savedRestaurants,
      ApiConstants.activeOffers,
    ];
    final results = await Future.wait<dynamic>(endpoints.map(safelyLoad));

    for (var index = 0; index < endpoints.length; index += 1) {
      _applyProfileStatResponse(endpoints[index], results[index]);
    }
  }

  void _applyProfileStatResponse(String endpoint, dynamic response) {
    if (response == null) return;

    if (endpoint == ApiConstants.wallet) {
      final walletResponse = response;
      final walletData = walletResponse is Map ? walletResponse['data'] : null;
      final wallet = walletData is Map ? walletData['wallet'] : null;
      _walletBalance =
          double.tryParse('${wallet is Map ? wallet['balance'] : 0}') ?? 0;
      return;
    }

    if (endpoint == ApiConstants.addresses) {
      _savedAddressCount = _extractProfileItems(response).length;
      return;
    }

    if (endpoint == ApiConstants.savedRestaurants) {
      _savedRestaurantCount = _extractProfileItems(response).length;
      return;
    }

    if (endpoint == ApiConstants.activeOffers) {
      _offersCount = _extractProfileItems(response).length;
    }
  }

  List<dynamic> _extractProfileItems(dynamic response) {
    final data = response is Map ? response['data'] : response;
    return data is List
        ? data
        : data is Map && data['data'] is List
            ? data['data'] as List
            : const <dynamic>[];
  }

  Future<void> _refreshData() async {
    await _loadData(forceRefresh: true);
  }

  String _countLabel(int count, String singular, String plural) {
    return count == 1 ? '1 $singular' : '$count $plural';
  }

  Future<void> _openEditProfileScreen() async {
    final updated = await Navigator.of(
      context,
    ).push<bool>(MaterialPageRoute(builder: (_) => const EditProfileScreen()));

    if (updated == true && mounted) {
      await _loadData(forceRefresh: true);
    }
  }

  Future<bool> _handleBackNavigation() async {
    if (widget.onBackToHome != null) {
      widget.onBackToHome!();
      return false;
    }

    if (Navigator.of(context).canPop()) {
      Navigator.of(context).pop();
      return false;
    }

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
    final user = authProvider.currentUser;

    return WillPopScope(
      onWillPop: _handleBackNavigation,
      child: Scaffold(
        backgroundColor: _profileBg,
        body: SafeArea(
          child: RefreshIndicator(
            onRefresh: _refreshData,
            color: _profilePrimary(context),
            child: Stack(
              children: [
                ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(18, 14, 18, 116),
                  children: [
                    _ProfileTopBar(
                      onBack: _handleBackNavigation,
                    ),
                    const SizedBox(height: 22),
                    _ProfileHeroWithStats(
                      user: user,
                      orderCount: orderProvider.orders.length,
                      walletBalance: _walletBalance,
                      savedRestaurantCount: _savedRestaurantCount,
                      offersCount: _offersCount,
                      onEdit: _openEditProfileScreen,
                    ),
                    const SizedBox(height: 18),
                    _ProfileSectionCard(
                      children: [
                        _ProfileMenuTile(
                          icon: LucideIcons.map_pin,
                          title: 'My Addresses',
                          subtitle: _countLabel(
                            _savedAddressCount,
                            'saved address',
                            'saved addresses',
                          ),
                          iconColor: const Color(0xFFD34B35),
                          iconBackground: const Color(0xFFFCEFED),
                          onTap: () =>
                              Navigator.pushNamed(context, '/addresses'),
                        ),
                        _ProfileMenuTile(
                          icon: LucideIcons.wallet_cards,
                          title: 'Payments',
                          subtitle:
                              'Balance ${formatCurrency(context, _walletBalance)}',
                          iconColor: const Color(0xFF159947),
                          iconBackground: const Color(0xFFEAF8EF),
                          onTap: () => Navigator.pushNamed(context, '/wallet'),
                        ),
                        _ProfileMenuTile(
                          icon: LucideIcons.heart,
                          title: 'Favourites',
                          subtitle: _countLabel(
                            _savedRestaurantCount,
                            'saved restaurant',
                            'saved restaurants',
                          ),
                          iconColor: const Color(0xFFE8387E),
                          iconBackground: const Color(0xFFFCEAF3),
                          onTap: () => Navigator.pushNamed(
                              context, '/saved-restaurants'),
                        ),
                        _ProfileMenuTile(
                          icon: LucideIcons.ticket_percent,
                          title: 'My Offers',
                          subtitle: _countLabel(
                            _offersCount,
                            'active offer',
                            'active offers',
                          ),
                          iconColor: const Color(0xFFB245D5),
                          iconBackground: const Color(0xFFF8ECFD),
                          onTap: () => Navigator.pushNamed(context, '/offers'),
                        ),
                        _ProfileMenuTile(
                          icon: LucideIcons.history,
                          title: 'Order History',
                          subtitle: _countLabel(
                            orderProvider.orders.length,
                            'past order',
                            'past orders',
                          ),
                          iconColor: const Color(0xFFE78317),
                          iconBackground: const Color(0xFFFFF3E6),
                          onTap: () => Navigator.pushNamed(context, '/orders'),
                        ),
                        _ProfileMenuTile(
                          icon: LucideIcons.headset,
                          title: 'Help & Support',
                          subtitle: 'FAQs, contact us & more',
                          iconColor: const Color(0xFF2A7FD6),
                          iconBackground: const Color(0xFFEAF4FF),
                          onTap: () => Navigator.pushNamed(context, '/support'),
                        ),
                        _ProfileMenuTile(
                          icon: LucideIcons.shield_check,
                          title: 'Privacy Policy',
                          subtitle: 'Learn how we protect your data',
                          iconColor: const Color(0xFF16A34A),
                          iconBackground: const Color(0xFFEAF8EF),
                          onTap: () =>
                              Navigator.pushNamed(context, '/privacy-legal'),
                        ),
                        _ProfileMenuTile(
                          icon: LucideIcons.file_text,
                          title: 'Terms & Conditions',
                          subtitle: 'Read our terms and conditions',
                          iconColor: const Color(0xFF222222),
                          iconBackground: const Color(0xFFF3F4F6),
                          onTap: () =>
                              Navigator.pushNamed(context, '/privacy-legal'),
                        ),
                        _ProfileMenuTile(
                          icon: _isLoggingOut
                              ? LucideIcons.loader
                              : LucideIcons.log_out,
                          title: 'Logout',
                          subtitle: 'Sign out from your account',
                          iconColor: FoodFlowTheme.danger,
                          iconBackground: const Color(0xFFFFEEEE),
                          titleColor: FoodFlowTheme.danger,
                          onTap: _isLoggingOut ? null : _showLogoutDialog,
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),
                    _ProfileVersionFooter(versionLabel: _appVersionLabel),
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
                    color: _profilePrimary(context).withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    LucideIcons.log_out,
                    size: 34,
                    color: _profilePrimary(context),
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
                const SizedBox(height: 4),
                const Text(
                  'Are you sure you want to logout from your account?',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 10.5,
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

class _ProfileVersionFooter extends StatelessWidget {
  const _ProfileVersionFooter({required this.versionLabel});

  final String versionLabel;

  @override
  Widget build(BuildContext context) {
    if (versionLabel.isEmpty) {
      return const SizedBox.shrink();
    }

    return Text(
      'Version $versionLabel',
      textAlign: TextAlign.center,
      style: const TextStyle(
        color: Color(0xFF9CA3AF),
        fontSize: 11,
        fontWeight: FontWeight.w600,
      ),
    );
  }
}

class _ProfileTopBar extends StatelessWidget {
  const _ProfileTopBar({
    required this.onBack,
  });

  final Future<bool> Function() onBack;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _RoundHeaderButton(
          icon: LucideIcons.arrow_left,
          color: Colors.black,
          onTap: () async {
            await onBack();
          },
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'My Profile',
                style: TextStyle(
                  color: Colors.black,
                  fontSize: 20,
                  height: 1.05,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Manage your account and preferences',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Color(0xFF676875),
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
        _RoundHeaderButton(
          icon: LucideIcons.bell,
          color: Colors.black,
          showDot: true,
          onTap: () => Navigator.pushNamed(context, '/notifications'),
        ),
        const SizedBox(width: 12),
        _RoundHeaderButton(
          icon: LucideIcons.settings,
          color: Colors.black,
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
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        width: 44,
        height: 44,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Icon(icon, size: 29, color: color),
            if (showDot)
              Positioned(
                top: 8,
                right: 9,
                child: Container(
                  width: 10,
                  height: 10,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFF3B1F),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 1.4),
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
    required this.savedRestaurantCount,
    required this.offersCount,
    required this.onEdit,
  });

  final User? user;
  final int orderCount;
  final double walletBalance;
  final int savedRestaurantCount;
  final int offersCount;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final name =
        user?.name.trim().isNotEmpty == true ? user!.name.trim() : 'Guest User';
    final phone = user?.phone.trim() ?? '';

    return InkWell(
      onTap: onEdit,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE9E2DD)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.025),
              blurRadius: 12,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                _ProfilePhoto(user: user, onEdit: onEdit),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Color(0xFF111827),
                          fontSize: 17,
                          height: 1.1,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      if (phone.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            const Icon(
                              LucideIcons.phone,
                              size: 14,
                              color: Color(0xFF6B7280),
                            ),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(
                                phone,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Color(0xFF6B7280),
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF2E8),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFFFDFC8)),
                  ),
                  child: const Icon(
                    LucideIcons.pencil,
                    color: _profileAccent,
                    size: 17,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _StatsPanel(
              stats: [
                _ProfileStatData(
                  icon: LucideIcons.shopping_bag,
                  value: '$orderCount',
                  label: 'Orders',
                  color: _profileAccent,
                  background: Colors.transparent,
                ),
                _ProfileStatData(
                  icon: LucideIcons.wallet_cards,
                  value: formatCurrency(context, walletBalance),
                  label: 'Wallet',
                  color: _profileAccent,
                  background: Colors.transparent,
                ),
                _ProfileStatData(
                  icon: LucideIcons.ticket_percent,
                  value: '$offersCount',
                  label: 'Offers',
                  color: _profileAccent,
                  background: Colors.transparent,
                ),
                _ProfileStatData(
                  icon: LucideIcons.heart,
                  value: '$savedRestaurantCount',
                  label: 'Saved',
                  color: _profileAccent,
                  background: Colors.transparent,
                ),
              ],
            ),
          ],
        ),
      ),
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
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Container(
          width: 66,
          height: 66,
          decoration: const BoxDecoration(
            shape: BoxShape.circle,
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0xFFFFE6D7), Color(0xFFFFC093)],
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: user?.profileImage?.isNotEmpty == true
              ? AppCachedImage(
                  imageUrl: user!.profileImage!,
                  fit: BoxFit.cover,
                  width: 66,
                  height: 66,
                  errorBuilder: (_, __, ___) => _ProfileAvatarFallback(
                    name: user?.name ?? 'Guest User',
                  ),
                )
              : _ProfileAvatarFallback(
                  name: user?.name ?? 'Guest User',
                ),
        ),
        Positioned(
          right: -2,
          bottom: -2,
          child: GestureDetector(
            onTap: onEdit,
            child: Container(
              width: 24,
              height: 24,
              decoration: BoxDecoration(
                color: _profileAccent,
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 2),
              ),
              child: const Icon(
                LucideIcons.pencil,
                color: Colors.white,
                size: 12,
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
      color: FoodFlowTheme.canvas,
      alignment: Alignment.center,
      child: Text(
        initial,
        style: TextStyle(
          color: Theme.of(context).colorScheme.primary,
          fontSize: 22,
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
        Icon(icon, color: _profilePrimary(context), size: 16),
        const SizedBox(width: 10),
        Expanded(
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              text,
              maxLines: 1,
              style: TextStyle(
                color: _profileSubtext,
                fontSize: 12,
                fontWeight: FontWeight.w800,
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
  const _HeroGlowCircle({required this.size});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withOpacity(0.1),
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
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 9),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFAF6),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFF0E3DA)),
        boxShadow: const [],
      ),
      child: Row(
        children: List.generate(stats.length * 2 - 1, (index) {
          if (index.isOdd) {
            return Container(
              width: 1,
              height: 34,
              margin: const EdgeInsets.symmetric(horizontal: 4),
              color: const Color(0xFFE0E0E4),
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
        Icon(stat.icon, color: stat.color, size: 16),
        const SizedBox(height: 4),
        Text(
          stat.value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Color(0xFF111827),
            fontSize: 13,
            height: 1,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          stat.label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Color(0xFF6B7280),
            fontSize: 10.5,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

class _ProfileSectionCard extends StatelessWidget {
  const _ProfileSectionCard({
    required this.children,
  });

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE4E5EA)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.025),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: List.generate(children.length * 2 - 1, (index) {
          if (index.isOdd) {
            return const Padding(
              padding: EdgeInsets.only(left: 80),
              child: Divider(height: 1, color: Color(0xFFE8E8ED)),
            );
          }
          return children[index ~/ 2];
        }),
      ),
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
    this.titleColor,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;
  final Color? iconColor;
  final Color? iconBackground;
  final Color? titleColor;

  @override
  Widget build(BuildContext context) {
    final resolvedColor = iconColor ?? _profileAccent;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: iconBackground ?? const Color(0xFFFFF2E8),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: resolvedColor, size: 22),
            ),
            const SizedBox(width: 18),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: titleColor ?? const Color(0xFF111827),
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF6B7280),
                      fontSize: 10.5,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            const Icon(
              LucideIcons.chevron_right,
              color: Color(0xFF5F626B),
              size: 25,
            ),
          ],
        ),
      ),
    );
  }
}
