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

class _FoodDeliveryAppState extends State<FoodDeliveryApp> {
  AppBranding? _branding;

  @override
  void initState() {
    super.initState();
    _loadBranding();
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
    FoodFlowTheme.applyBrandColors(primary: primary, secondary: secondary);
    const homeCanvas = Color(0xFFFAFAFA);
    const homeText = Color(0xFF111827);
    const homeMuted = Color(0xFF6B7280);
    const homeBorder = Color(0xFFE5E7EB);

    return MaterialApp(
      navigatorKey: appNavigatorKey,
      title: _branding?.displayName ?? AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: primary,
          primary: primary,
          secondary: secondary,
          surface: Colors.white,
          error: FoodFlowTheme.danger,
        ),
        primaryColor: primary,
        scaffoldBackgroundColor: homeCanvas,
        visualDensity: const VisualDensity(horizontal: -3, vertical: -3),
        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
        fontFamily: GoogleFonts.nunitoSans().fontFamily,
        useMaterial3: true,
        textTheme: AppTypography.material3(
          base: GoogleFonts.nunitoSansTextTheme(),
          textColor: homeText,
          mutedColor: homeMuted,
        ),
        appBarTheme: const AppBarTheme(
          elevation: 0,
          centerTitle: false,
          backgroundColor: homeCanvas,
          foregroundColor: homeText,
          iconTheme: IconThemeData(color: homeText),
          titleTextStyle: TextStyle(
            color: homeText,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
          toolbarHeight: 52,
          scrolledUnderElevation: 0,
          surfaceTintColor: Colors.transparent,
        ),
        dialogTheme: DialogThemeData(
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.transparent,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          titleTextStyle: const TextStyle(
              color: homeText, fontSize: 18, fontWeight: FontWeight.w800),
          contentTextStyle: const TextStyle(
              color: homeMuted, fontSize: 14, fontWeight: FontWeight.w500),
        ),
        bottomSheetTheme: const BottomSheetThemeData(
          backgroundColor: Colors.white,
          modalBackgroundColor: Colors.white,
          surfaceTintColor: Colors.transparent,
          showDragHandle: true,
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        ),
        tabBarTheme: TabBarThemeData(
          labelColor: primary,
          unselectedLabelColor: FoodFlowTheme.muted,
          indicatorColor: primary,
          labelStyle: TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
          unselectedLabelStyle: TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: 15,
          ),
          dividerColor: Colors.transparent,
        ),
        dividerTheme: const DividerThemeData(
          color: FoodFlowTheme.line,
          thickness: 1,
          space: 1,
        ),
        listTileTheme: ListTileThemeData(
          iconColor: primary,
          textColor: homeText,
          dense: true,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 16,
            vertical: 6,
          ),
          minLeadingWidth: 18,
          titleTextStyle: const TextStyle(
            color: homeText,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
          subtitleTextStyle: const TextStyle(
            color: homeMuted,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
        chipTheme: ChipThemeData(
          backgroundColor: const Color(0xFFEFFAF4),
          selectedColor: const Color(0xFFFFF3E8),
          checkmarkColor: primary,
          labelStyle: const TextStyle(
            color: FoodFlowTheme.ink,
            fontWeight: FontWeight.w700,
            fontSize: 13,
          ),
          side: const BorderSide(color: Color(0xFFE5E7EB)),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 1),
        ),
        bottomNavigationBarTheme: BottomNavigationBarThemeData(
          backgroundColor: Colors.white,
          selectedItemColor: primary,
          unselectedItemColor: FoodFlowTheme.muted,
          selectedLabelStyle: const TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 13,
          ),
          unselectedLabelStyle: const TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 13,
          ),
          selectedIconTheme: const IconThemeData(size: 23),
          unselectedIconTheme: const IconThemeData(size: 21),
          type: BottomNavigationBarType.fixed,
          showUnselectedLabels: true,
          elevation: 3,
        ),
        inputDecorationTheme: InputDecorationTheme(
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: homeBorder),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: primary, width: 1.2),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: FoodFlowTheme.danger),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(
              color: FoodFlowTheme.danger,
              width: 1.2,
            ),
          ),
          filled: true,
          fillColor: Colors.white,
          contentPadding: AppSpacing.input,
          prefixIconColor: primary,
          suffixIconColor: homeMuted,
          labelStyle: const TextStyle(
            color: homeMuted,
            fontWeight: FontWeight.w700,
            fontSize: 16,
          ),
          hintStyle: const TextStyle(
            color: homeMuted,
            fontWeight: FontWeight.w600,
            fontSize: 16,
          ),
          floatingLabelStyle: TextStyle(
            color: primary,
            fontWeight: FontWeight.w700,
            fontSize: 16,
          ),
          isDense: true,
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            backgroundColor: primary,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            minimumSize: const Size(0, 46),
            elevation: 0,
            shadowColor: Colors.transparent,
            textStyle: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.2,
            ),
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            side: BorderSide(color: primary.withOpacity(0.24)),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            foregroundColor: homeText,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
            minimumSize: const Size(0, 46),
            textStyle: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.2,
            ),
          ),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            backgroundColor: primary,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            minimumSize: const Size(0, 46),
            textStyle: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.2,
            ),
          ),
        ),
        cardTheme: CardThemeData(
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
            side: const BorderSide(color: homeBorder),
          ),
          margin: EdgeInsets.zero,
          color: Colors.white,
        ),
        snackBarTheme: SnackBarThemeData(
          backgroundColor: primary,
          contentTextStyle: const TextStyle(color: Colors.white),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
        floatingActionButtonTheme: FloatingActionButtonThemeData(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          elevation: 3,
          extendedTextStyle: const TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 15,
          ),
        ),
        textButtonTheme: TextButtonThemeData(
          style: TextButton.styleFrom(
            foregroundColor: primary,
            textStyle: const TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 15,
            ),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            minimumSize: const Size(0, 32),
          ),
        ),
      ),
      home: AppSplashScreen(
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
        return ResponsiveMedia.withClampedTextScale(
          context: context,
          child: EasyLoading.init()(context, child),
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
        return MaterialPageRoute(
          builder: (_) => RestaurantOrderDetailScreen(orderId: orderId),
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
