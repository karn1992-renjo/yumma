// V2 shell: aurora background, 4 tabs in an IndexedStack, a glass bottom nav
// on the home tab, and a floating glass cart bar. Nothing here imports a V1
// screen.

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../config/api_constants.dart';
import '../../../services/api_service.dart';
import '../../../services/location_service.dart';
import '../home_experience.dart';
import 'data/v2_serviceability.dart';
import 'data/v2_util.dart';
import 'screens/home_feed_v2.dart';
import 'screens/no_delivery_zone_v2.dart';
import 'screens/orders_v2.dart';
import 'screens/profile_v2.dart';
import 'screens/search_v2.dart';
import 'theme/v2_theme.dart';
import 'v2_nav.dart';
import 'widgets/v2_background.dart';
import 'widgets/v2_bottom_nav.dart';
import 'widgets/v2_cart_bar.dart';
import 'widgets/v2_location_sheet.dart';
import 'widgets/v2_order_tracker_bar.dart';
import 'widgets/v2_scaffold.dart';

class CustomerHomeScreenV2 extends StatefulWidget {
  const CustomerHomeScreenV2({super.key});

  @override
  State<CustomerHomeScreenV2> createState() => _CustomerHomeScreenV2State();
}

class _CustomerHomeScreenV2State extends State<CustomerHomeScreenV2> {
  final LocationService _location = LocationService();
  final ApiService _api = ApiService();

  int _index = 0;
  int _locationRevision = 0;
  int _notificationCount = 0;
  DateTime? _lastBack;

  String _city = 'Home';
  String _address = 'Select your delivery address';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadLocation();
      _loadNotificationCount();
    });
  }

  Future<void> _loadLocation() async {
    final saved = await _location.getSavedLocation();
    if (!mounted || saved == null) return;
    setState(() {
      _city = (saved['city']?.toString().trim().isNotEmpty ?? false)
          ? saved['city'].toString().trim()
          : 'Home';
      final a = saved['address']?.toString().trim();
      if (a != null && a.isNotEmpty) _address = a;
      _locationRevision++;
    });
  }

  Future<void> _loadNotificationCount() async {
    try {
      final res = await _api.get(
        ApiConstants.notifications,
        queryParams: const {'limit': 1, 'target_app': 'customer'},
      );
      final data = res is Map ? res['data'] : null;
      if (!mounted || data is! Map) return;
      setState(() => _notificationCount = v2Int(data['unread_count']));
    } catch (_) {}
  }

  Future<void> _openLocation() async {
    final picked = await showV2LocationSheet(context);
    if (picked != null) {
      await _location.saveLocation(
        picked.city,
        picked.lat,
        picked.lng,
        address: picked.address,
      );
      if (mounted) _loadLocation();
      final serviceable = await v2IsServiceable(picked.lat, picked.lng);
      if (!serviceable && mounted) {
        Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => NoDeliveryZoneV2(
            locationLabel: picked.address.trim().isNotEmpty
                ? picked.address
                : picked.city,
            onChangeLocation: () {
              Navigator.of(context).pop();
              _openLocation();
            },
          ),
        ));
      }
      return;
    }
    if (mounted) _loadLocation();
  }

  Widget _tab(int i) {
    switch (i) {
      case 1:
        return const SearchV2(embedded: true);
      case 2:
        return const OrdersV2(embedded: true);
      case 3:
        return ProfileV2(onBackToHome: () => setState(() => _index = 0));
      case 0:
      default:
        return HomeFeedV2(
          city: _city,
          address: _address,
          locationRevision: _locationRevision,
          notificationCount: _notificationCount,
          onLocationTap: _openLocation,
          onWalletTap: () => v2OpenWallet(context),
          onNotificationTap: () {
            v2OpenNotifications(context);
            Future<void>.delayed(const Duration(milliseconds: 500),
                _loadNotificationCount);
          },
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<V2Mode>(
      valueListenable: v2ModeNotifier,
      builder: (context, mode, _) {
        final palette = V2Palette.of(mode);
        return V2Theme(
          palette: palette,
          child: PopScope(
            canPop: false,
            onPopInvokedWithResult: (didPop, _) {
              if (didPop) return;
              if (_index != 0) {
                setState(() => _index = 0);
                return;
              }
              final now = DateTime.now();
              if (_lastBack == null ||
                  now.difference(_lastBack!) > const Duration(seconds: 2)) {
                _lastBack = now;
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Press back again to exit'),
                    duration: Duration(seconds: 2),
                  ),
                );
                return;
              }
              SystemNavigator.pop();
            },
            child: Scaffold(
              backgroundColor: palette.bgTop,
              extendBody: true,
              // Scaffold gives its body LOOSE constraints; a Stack with only
              // Positioned children would collapse to 0x0, so force it to fill.
              body: SizedBox.expand(
                child: Stack(
                fit: StackFit.expand,
                children: [
                  Positioned.fill(child: V2Background(palette: palette)),
                  // The home feed draws its own hero banner behind the status
                  // bar, so it manages top insets itself; other tabs get a
                  // normal safe area.
                  Positioned.fill(
                    child: _index == 0
                        ? _tab(0)
                        : SafeArea(bottom: false, child: _tab(_index)),
                  ),
                  // Floating stack: order-tracker on top of the cart bar, both
                  // clearing the bottom nav (home) or the screen edge.
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: MediaQuery.of(context).padding.bottom +
                        (_index == 0 ? 86 : 20),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (_index == 0) const V2OrderTrackerBar(),
                        if (_index == 0) const SizedBox(height: 10),
                        V2CartBarBody(onTap: () => v2OpenCart(context)),
                      ],
                    ),
                  ),
                  if (_index == 0)
                    Positioned(
                      left: 0,
                      right: 0,
                      bottom: 0,
                      child: V2BottomNav(
                        currentIndex: _index,
                        onTap: (i) => setState(() => _index = i),
                      ),
                    ),
                ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
