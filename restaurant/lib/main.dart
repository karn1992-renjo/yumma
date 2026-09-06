// lib/main.dart
import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:firebase_core/firebase_core.dart';
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
import 'theme/responsive_theme.dart';
import 'providers/auth_provider.dart';
import 'providers/cart_provider.dart';
import 'providers/order_provider.dart';
import 'providers/restaurant_provider.dart';
import 'providers/theme_provider.dart';
import 'theme/aurora_theme.dart';
import 'models/order.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/auth/partner_application_status_screen.dart';
import 'screens/app_splash_screen.dart';
import 'screens/onboarding/onboarding_screen.dart';

import 'screens/restaurant/restaurant_dashboard.dart';
import 'screens/restaurant/restaurant_notifications_screen.dart';
import 'screens/restaurant/restaurant_order_chat_screen.dart';
import 'screens/restaurant/restaurant_order_detail_screen.dart';
import 'screens/restaurant/restaurant_dining_screen.dart';
import 'screens/restaurant/profile/index.dart';
import 'screens/location_required_screen.dart';
import 'utils/route_observer.dart';
import 'widgets/common/network_image_loader.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  AppImageCache.configureMemoryCache();

  configLoading();
  unawaited(_startApp());
}

Future<void> _startApp() async {
  final authProvider = AuthProvider();
  final cartProvider = CartProvider();
  var onboardingComplete = false;
  final startupFuture = _initializeStartup(
    authProvider,
    cartProvider,
    (value) => onboardingComplete = value,
  );

  _logStartupStep('runApp');

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: authProvider),
        ChangeNotifierProvider.value(value: cartProvider),
        ChangeNotifierProvider(create: (_) => OrderProvider()),
        ChangeNotifierProvider(create: (_) => RestaurantProvider()),
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
      ],
      child: FoodDeliveryApp(
        startupFuture: startupFuture,
        onboardingComplete: () => onboardingComplete,
      ),
    ),
  );

  WidgetsBinding.instance.addPostFrameCallback((_) {
    unawaited(
      startupFuture.then(
        (_) => OrderAlertStartupPermissionService.ensureForOrderAlerts(
          enabled: authProvider.isRestaurantMember,
        ),
      ),
    );
  });

  WidgetsBinding.instance.addPostFrameCallback((_) {
    unawaited(_initializeAfterFirstFrame());
    unawaited(
      startupFuture.then(
        (_) => AppUpdateService.checkForLatestRelease(appKey: 'restaurant'),
      ),
    );
  });
}

Future<void> _initializeStartup(
  AuthProvider authProvider,
  CartProvider cartProvider,
  ValueChanged<bool> setOnboardingComplete,
) async {
  var onboardingComplete = false;
  await _runStartupStep(
    'startup preferences',
    () async {
      final prefs = await SharedPreferences.getInstance();
      onboardingComplete = prefs.getBool('onboarding_complete') ?? false;
    },
  );

  if (onboardingComplete) {
    await _runStartupStep('firebase core', _ensureFirebaseInitialized);
    await _runStartupStep('auth session', authProvider.loadUser);
    if (authProvider.isAuthenticated && !authProvider.canUseCurrentApp) {
      await _runStartupStep('logout invalid app role', authProvider.logout);
    }
  }
  unawaited(cartProvider.loadCart());
  setOnboardingComplete(onboardingComplete);
}

Future<void> _initializeAfterFirstFrame() async {
  await _runStartupStep('local cache', LocalCacheService.initialize);
  await _runStartupStep(
    'shared preferences',
    () async {
      await SharedPreferences.getInstance();
    },
  );

  if (defaultTargetPlatform == TargetPlatform.iOS) {
    unawaited(
      Future<void>.delayed(const Duration(seconds: 2)).then(
        (_) => _runStartupStep('sound deferred', SoundService.init),
      ),
    );
  } else {
    await _runStartupStep('sound', SoundService.init);
  }

  await _runStartupStep('firebase core', _ensureFirebaseInitialized);
  await _runStartupStep(
    'notifications',
    FirebaseNotificationService.instance.initialize,
  );
  await _runStartupStep(
    'incoming order alerts',
    IncomingOrderAlertService.instance.initialize,
  );
}

Future<void> _ensureFirebaseInitialized() async {
  if (Firebase.apps.isNotEmpty) return;

  if (!kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.iOS ||
          defaultTargetPlatform == TargetPlatform.android)) {
    await Firebase.initializeApp();
    return;
  }

  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
}

Future<void> _runStartupStep(
  String name,
  Future<void> Function() action,
) async {
  _logStartupStep('$name start');
  try {
    await action();
    _logStartupStep('$name done');
  } catch (e, stackTrace) {
    debugPrint('Startup initiate: $name skipped: $e');
    debugPrintStack(stackTrace: stackTrace);
  }
}

void _logStartupStep(String message) {
  debugPrint('Startup initiate: $message');
}

void configLoading() {
  EasyLoading.instance
    ..displayDuration = const Duration(milliseconds: 2000)
    ..indicatorType = EasyLoadingIndicatorType.fadingCircle
    ..loadingStyle = EasyLoadingStyle.custom
    ..indicatorSize = 45.0
    ..radius = 14.0
    ..progressColor = Colors.white
    ..backgroundColor = Colors.black.withOpacity(0.82)
    ..indicatorColor = Colors.white
    ..textColor = Colors.white
    ..maskColor = Colors.black.withOpacity(0.16)
    ..userInteractions = false
    ..dismissOnTap = false;
}

int? _parseOrderId(dynamic args) {
  if (args == null) return null;
  if (args is Map) {
    return _parseOrderId(
      args['orderId'] ?? args['order_id'] ?? args['id'],
    );
  }
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
  final Future<void> startupFuture;
  final bool Function() onboardingComplete;

  const FoodDeliveryApp({
    super.key,
    required this.startupFuture,
    required this.onboardingComplete,
  });

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
    super.didChangePlatformBrightness();
    if (mounted) setState(() {});
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    final primary = _colorFromHex(
      branding.restaurantPrimaryColorHex,
      AppConfig.primaryColor,
    );
    final secondary = _colorFromHex(
      branding.restaurantSecondaryColorHex,
      AppConfig.secondaryColor,
    );
    FoodFlowTheme.applyBrandColors(primary: primary, secondary: secondary);
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
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final primary = _colorFromHex(
      _branding?.restaurantPrimaryColorHex,
      AppConfig.primaryColor,
    );
    final secondary = _colorFromHex(
      _branding?.restaurantSecondaryColorHex,
      AppConfig.secondaryColor,
    );
    final themeProvider = context.watch<ThemeProvider>();
    final platformBrightness =
        View.of(context).platformDispatcher.platformBrightness;
    final brightness = themeProvider.resolveBrightness(platformBrightness);
    FoodFlowTheme.applyBrandColors(primary: primary, secondary: secondary);
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
        builder: (_) => !widget.onboardingComplete()
            ? const OnboardingScreen()
            : authProvider.isAuthenticated && authProvider.canUseCurrentApp
                ? const RestaurantDashboard()
                : const LoginScreen(),
      ),
      navigatorObservers: [routeObserver],
      onGenerateRoute: _generateRoute,
      builder: (context, child) {
        // Legacy screens read `foodflow.*` statics at build time, so a
        // brightness change must rebuild the whole navigator subtree.
        return ResponsiveMedia.withClampedTextScale(
          context: context,
          child: EasyLoading.init()(
            context,
            KeyedSubtree(
              key: ValueKey<Brightness>(brightness),
              child: child ?? const SizedBox.shrink(),
            ),
          ),
        );
      },
    );
  }

  Route<dynamic>? _generateRoute(RouteSettings settings) {
    switch (settings.name) {
      // Auth Routes
      case '/login':
        return MaterialPageRoute(builder: (_) => const LoginScreen());
      case '/register':
        return MaterialPageRoute(builder: (_) => const RegisterScreen());
      case '/application-status':
        return MaterialPageRoute(
          builder: (_) => PartnerApplicationStatusScreen(
            applicationNumber: settings.arguments as String?,
          ),
        );
      case '/onboarding':
        return MaterialPageRoute(builder: (_) => const OnboardingScreen());

      // Restaurant Routes
      case '/restaurant/dashboard':
        return MaterialPageRoute(builder: (_) => const RestaurantDashboard());
      case '/restaurant/notifications':
        return MaterialPageRoute(
          builder: (_) => const RestaurantNotificationsScreen(),
        );
      case '/restaurant/order':
        final orderId = _parseOrderId(settings.arguments);
        if (orderId == null) {
          return _errorRoute('Invalid restaurant order ID.');
        }
        final restaurantId = settings.arguments is Map
            ? _parseOrderId(
                (settings.arguments as Map)['restaurantId'] ??
                    (settings.arguments as Map)['restaurant_id'],
              )
            : null;
        return MaterialPageRoute(
          builder: (_) => RestaurantOrderDetailScreen(
            orderId: orderId,
            restaurantId: restaurantId,
          ),
        );
      case '/restaurant/order/chat':
        final orderId = _parseOrderId(
          settings.arguments is Map
              ? (settings.arguments as Map)['orderId'] ??
                  (settings.arguments as Map)['id']
              : settings.arguments,
        );
        if (orderId == null) {
          return _errorRoute('Invalid restaurant order chat ID.');
        }
        return MaterialPageRoute(
          builder: (_) => RestaurantOrderChatScreen(orderId: orderId),
        );
      case '/restaurant/dining':
        return MaterialPageRoute(
          builder: (_) => const RestaurantDiningScreen(),
        );
      case '/restaurant/profile':
        return MaterialPageRoute(
          builder: (_) => const RestaurantProfileScreen(),
        );
      case '/restaurant/profile/edit':
        return MaterialPageRoute(
          builder: (_) => const RestaurantProfileEditScreen(),
        );
      case '/restaurant/profile/bank':
        return MaterialPageRoute(
          builder: (_) => const RestaurantBankDetailsScreen(),
        );
      case '/location-setup':
        final args = settings.arguments as Map<String, dynamic>?;
        return MaterialPageRoute(
          builder: (_) => LocationRequiredScreen(
            nextRoute:
                args?['nextRoute']?.toString() ?? '/restaurant/dashboard',
          ),
        );
      case '/restaurant/profile/location':
        return MaterialPageRoute(
          builder: (_) => const RestaurantLocationScreen(),
        );
      case '/restaurant/profile/help':
        final args = settings.arguments;
        return MaterialPageRoute(
          builder: (_) => RestaurantHelpSupportScreen(
            openChat: args is Map && args['openChat'] == true,
          ),
        );
      case '/restaurant/profile/legal':
        return MaterialPageRoute(builder: (_) => const RestaurantLegalScreen());
      case '/restaurant/profile/account-deletion-policy':
        return MaterialPageRoute(
          builder: (_) => const AccountDeletionPolicyScreen(),
        );

      default:
        return _errorRoute('Page not found: ${settings.name}');
    }
  }
}
