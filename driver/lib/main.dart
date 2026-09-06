// lib/main.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:google_fonts/google_fonts.dart';
import 'firebase_options.dart';
import 'services/notification_service.dart';
import 'services/incoming_order_alert_service.dart';
import 'services/navigation_service.dart';
import 'services/order_alert_startup_permission_service.dart';
import 'services/sound_service.dart';
import 'services/local_cache_service.dart';
import 'services/app_branding_service.dart';
import 'services/app_update_service.dart';
import 'config/app_config.dart';
import 'models/app_branding.dart';
import 'theme/foodflow_theme.dart';
import 'theme/aurora_theme.dart';
import 'theme/responsive_theme.dart';
import 'providers/auth_provider.dart';
import 'providers/theme_provider.dart';
import 'providers/cart_provider.dart';
import 'providers/order_provider.dart';
import 'providers/restaurant_provider.dart';
import 'models/order.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/auth/partner_application_status_screen.dart';
import 'screens/app_splash_screen.dart';
import 'screens/driver/driver_dashboard.dart';
import 'screens/driver/driver_order_chat_screen.dart';
import 'screens/driver/driver_order_detail_screen.dart';
import 'screens/driver/driver_restaurant_onboarding_screen.dart';
import 'screens/driver/driver_gigs_screen.dart';
import 'screens/driver/driver_notifications_screen.dart';
import 'screens/driver/privacy_legal_screen.dart';
import 'screens/driver/driver_support_screen.dart';
import 'screens/driver/driver_cod_deposit_screen.dart';
import 'widgets/common/network_image_loader.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  AppImageCache.configureMemoryCache();
  configLoading();

  final authProvider = AuthProvider();
  final cartProvider = CartProvider();
  final startupFuture = _initializeStartup(authProvider, cartProvider);

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: authProvider),
        ChangeNotifierProvider.value(value: cartProvider),
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
        ChangeNotifierProvider(create: (_) => OrderProvider()),
        ChangeNotifierProvider(create: (_) => RestaurantProvider()),
      ],
      child: FoodDeliveryApp(startupFuture: startupFuture),
    ),
  );

  WidgetsBinding.instance.addPostFrameCallback((_) {
    unawaited(
      startupFuture.then(
        (_) => OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: authProvider.isDriver,
        ),
      ),
    );
    unawaited(
      startupFuture.then(
        (_) => AppUpdateService.checkForLatestRelease(appKey: 'driver'),
      ),
    );
    // Re-check for a still-pending incoming order once the UI actually exists.
    // On a cold start `didChangeAppLifecycleState(resumed)` never fires, so the
    // earlier restore attempt (during startup, no navigator) is the only one —
    // retry here so the alert reappears after a kill/relaunch until acted on.
    unawaited(
      startupFuture.then((_) async {
        for (final delay in const [
          Duration(milliseconds: 600),
          Duration(seconds: 2),
          Duration(seconds: 4),
        ]) {
          await Future<void>.delayed(delay);
          await IncomingOrderAlertService.instance.restorePendingOrderState();
        }
      }),
    );
  });
}

Future<void> _initializeStartup(
  AuthProvider authProvider,
  CartProvider cartProvider,
) async {
  // Each step is isolated: a failure in one (most often Firebase's
  // `[core/duplicate-app]` race) must never stop the session from being
  // restored, or the driver lands on the login screen despite a valid token.
  Future<void> step(String label, Future<void> Function() run) async {
    try {
      await run();
    } catch (e) {
      debugPrint('Startup step "$label" failed: $e');
    }
  }

  await step('local cache', () async {
    await LocalCacheService.initialize();
  });
  await step('shared prefs', () async {
    await SharedPreferences.getInstance();
  });
  await step('sound', () async {
    await SoundService.init();
  });
  await step('firebase', () async {
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
    }
  });
  await step('load user', () async {
    await authProvider.loadUser();
  });
  await step('firebase notifications', () async {
    await FirebaseNotificationService.instance.initialize();
  });
  await step('incoming order alerts', () async {
    await IncomingOrderAlertService.instance.initialize();
  });
  if (authProvider.isAuthenticated && !authProvider.canUseCurrentApp) {
    await step('logout wrong-app user', () async {
      await authProvider.logout();
    });
  }
  unawaited(cartProvider.loadCart());
}

void configLoading() {
  EasyLoading.instance
    ..displayDuration = const Duration(milliseconds: 2000)
    ..indicatorType = EasyLoadingIndicatorType.fadingCircle
    ..loadingStyle = EasyLoadingStyle.dark
    ..indicatorSize = 45.0
    ..radius = 10.0
    ..progressColor = Colors.yellow
    ..backgroundColor = Colors.green
    ..indicatorColor = Colors.yellow
    ..textColor = Colors.yellow
    ..maskColor = Colors.blue.withOpacity(0.5)
    ..userInteractions = false
    ..dismissOnTap = false;
}

int? _parseOrderId(dynamic args) {
  if (args == null) return null;
  if (args is int) return args;
  if (args is String) return int.tryParse(args);
  if (args is double) return args.toInt();
  return null;
}

Route<dynamic> _errorRoute(String message) {
  return MaterialPageRoute(
    builder: (_) => Scaffold(
      appBar: AppBar(title: const Text('Error')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.red),
          ),
        ),
      ),
    ),
  );
}

class FoodDeliveryApp extends StatefulWidget {
  const FoodDeliveryApp({
    super.key,
    required this.startupFuture,
  });

  final Future<void> startupFuture;

  @override
  State<FoodDeliveryApp> createState() => _FoodDeliveryAppState();
}

class _FoodDeliveryAppState extends State<FoodDeliveryApp>
    with WidgetsBindingObserver {
  AppBranding? _branding;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadBranding();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangePlatformBrightness() {
    // Rebuild so a "system" theme choice tracks the OS switch.
    if (mounted) setState(() {});
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    foodflow.applyBrandColors(
      primary: _colorFromHex(
        branding.driverPrimaryColorHex,
        AppConfig.primaryColor,
      ),
      secondary: _colorFromHex(
        branding.driverSecondaryColorHex,
        AppConfig.secondaryColor,
      ),
    );
    setState(() => _branding = branding);
  }

  Color _colorFromHex(String? value, Color fallback) {
    final normalized = value?.trim().replaceFirst('#', '') ?? '';
    if (normalized.length != 6) return fallback;
    final parsed = int.tryParse(normalized, radix: 16);
    return parsed == null ? fallback : Color(0xFF000000 | parsed);
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final primary = _colorFromHex(
      _branding?.driverPrimaryColorHex,
      AppConfig.primaryColor,
    );
    final secondary = _colorFromHex(
      _branding?.driverSecondaryColorHex,
      AppConfig.secondaryColor,
    );
    foodflow.applyBrandColors(primary: primary, secondary: secondary);

    final themeProvider = context.watch<ThemeProvider>();
    final platformBrightness =
        WidgetsBinding.instance.platformDispatcher.platformBrightness;
    final brightness = themeProvider.resolveBrightness(platformBrightness);
    // Legacy screens read `foodflow.*` statics directly, so retint them for the
    // effective brightness before the tree (re)builds.
    foodflow.applyBrightness(brightness);

    return MaterialApp(
      navigatorKey: appNavigatorKey,
      title: _branding?.displayName ?? AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: AuroraTheme.build(
        brightness: brightness,
        primary: primary,
        secondary: secondary,
      ),
      home: AppSplashScreen(
        branding: _branding,
        startupFuture: widget.startupFuture,
        builder: (_) =>
            authProvider.isAuthenticated && authProvider.canUseCurrentApp
                ? const DriverDashboard()
                : const LoginScreen(),
      ),
      onGenerateRoute: _generateRoute,
      builder: (context, child) {
        return ResponsiveMedia.withClampedTextScale(
          context: context,
          child: EasyLoading.init()(context, child),
        );
      },
    );
  }

  Route<dynamic>? _generateRoute(RouteSettings settings) {
    final routeName = settings.name ?? '';
    if (routeName.startsWith('/driver/restaurant-onboardings/')) {
      final id = int.tryParse(routeName.split('/').last);
      if (id == null) {
        return _errorRoute('Invalid restaurant onboarding ID.');
      }
      return MaterialPageRoute(
        builder: (_) => DriverRestaurantOnboardingDetailScreen(id: id),
      );
    }

    switch (settings.name) {
      // Auth Routes
      case '/login':
        return MaterialPageRoute(builder: (_) => const LoginScreen());
      case '/register':
        final args = settings.arguments;
        String? initialPhone;
        if (args is String) {
          initialPhone = args;
        } else if (args is Map) {
          initialPhone = args['phone']?.toString();
        }
        return MaterialPageRoute(
          builder: (_) => RegisterScreen(initialPhone: initialPhone),
        );
      case '/application-status':
        return MaterialPageRoute(
          builder: (_) => PartnerApplicationStatusScreen(
            applicationNumber: settings.arguments as String?,
          ),
        );
      case '/privacy-legal':
        return MaterialPageRoute(
          builder: (_) => const DriverPrivacyLegalScreen(),
        );

      // Driver Routes
      case '/driver/dashboard':
        return MaterialPageRoute(builder: (_) => const DriverDashboard());
      case '/driver/restaurant-onboardings':
        return MaterialPageRoute(
          builder: (_) => const DriverRestaurantOnboardingScreen(),
        );
      case '/driver/order':
        final orderId = _parseOrderId(settings.arguments);
        if (orderId == null) {
          return _errorRoute('Invalid driver order ID.');
        }
        return MaterialPageRoute(
          builder: (_) => DriverOrderDetailScreen(orderId: orderId),
        );
      case '/driver/order/chat':
        final orderId = _parseOrderId(
          settings.arguments is Map
              ? (settings.arguments as Map)['orderId'] ??
                  (settings.arguments as Map)['id']
              : settings.arguments,
        );
        if (orderId == null) {
          return _errorRoute('Invalid driver order chat ID.');
        }
        return MaterialPageRoute(
          builder: (_) => DriverOrderChatScreen(orderId: orderId),
        );
      case '/driver/gigs':
        return MaterialPageRoute(builder: (_) => const DriverGigsScreen());
      case '/driver/notifications':
        return MaterialPageRoute(
          builder: (_) => const DriverNotificationsScreen(),
        );
      case '/support':
      case '/driver/support':
        final args = settings.arguments;
        return MaterialPageRoute(
          builder: (_) => DriverSupportScreen(
            openChat: args is Map && args['openChat'] == true,
          ),
        );
      case '/driver/cod-deposit':
        final args = settings.arguments;
        final map = args is Map ? args : const {};
        return MaterialPageRoute(
          builder: (_) => DriverCodDepositScreen(
            amountDue: (map['amount_due'] as num?)?.toDouble() ?? 0,
            limit: (map['limit'] as num?)?.toDouble() ?? 0,
          ),
        );

      default:
        return _errorRoute('Page not found: ${settings.name}');
    }
  }
}
