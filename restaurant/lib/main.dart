// lib/main.dart
import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:firebase_core/firebase_core.dart';
import 'firebase_options.dart';
import 'services/notification_service.dart';
import 'services/incoming_order_alert_service.dart';
import 'services/navigation_service.dart';
import 'services/sound_service.dart';
import 'services/local_cache_service.dart';
import 'services/app_branding_service.dart';
import 'config/app_config.dart';
import 'models/app_branding.dart';
import 'theme/foodflow_theme.dart';
import 'providers/auth_provider.dart';
import 'providers/cart_provider.dart';
import 'providers/order_provider.dart';
import 'providers/restaurant_provider.dart';
import 'models/order.dart';
import 'screens/splash_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/auth/partner_application_status_screen.dart';
import 'screens/onboarding/onboarding_screen.dart';

import 'screens/restaurant/restaurant_dashboard.dart';
import 'screens/restaurant/restaurant_notifications_screen.dart';
import 'screens/restaurant/restaurant_order_chat_screen.dart';
import 'screens/restaurant/restaurant_order_detail_screen.dart';
import 'screens/restaurant/restaurant_dining_screen.dart';
import 'screens/restaurant/profile/index.dart';
import 'screens/location_required_screen.dart';
import 'utils/route_observer.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  configLoading();
  _logStartupStep('runApp');

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()..loadUser()),
        ChangeNotifierProvider(create: (_) => CartProvider()..loadCart()),
        ChangeNotifierProvider(create: (_) => OrderProvider()),
        ChangeNotifierProvider(create: (_) => RestaurantProvider()),
      ],
      child: const FoodDeliveryApp(),
    ),
  );

  WidgetsBinding.instance.addPostFrameCallback((_) {
    unawaited(_initializeAfterFirstFrame());
  });
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
  const FoodDeliveryApp({super.key});

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
        visualDensity: VisualDensity.adaptivePlatformDensity,
        materialTapTargetSize: MaterialTapTargetSize.padded,
        fontFamily: 'Poppins',
        useMaterial3: true,
        textTheme: const TextTheme(
          displayLarge: TextStyle(
            color: homeText,
            fontSize: 32,
            fontWeight: FontWeight.w800,
            height: 1.02,
          ),
          displayMedium: TextStyle(
            color: homeText,
            fontSize: 28,
            fontWeight: FontWeight.w800,
            height: 1.06,
          ),
          headlineLarge: TextStyle(
            color: homeText,
            fontSize: 25,
            fontWeight: FontWeight.w800,
            height: 1.08,
          ),
          headlineMedium: TextStyle(
            color: homeText,
            fontSize: 23,
            fontWeight: FontWeight.w800,
            height: 1.1,
          ),
          titleLarge: TextStyle(
            color: homeText,
            fontSize: 20,
            fontWeight: FontWeight.w800,
            height: 1.16,
          ),
          titleMedium: TextStyle(
            color: homeText,
            fontSize: 18,
            fontWeight: FontWeight.w700,
            height: 1.18,
          ),
          titleSmall: TextStyle(
            color: homeText,
            fontSize: 16,
            fontWeight: FontWeight.w700,
            height: 1.18,
          ),
          bodyLarge: TextStyle(
            color: homeMuted,
            fontSize: 17,
            fontWeight: FontWeight.w500,
            height: 1.3,
          ),
          bodyMedium: TextStyle(
            color: homeMuted,
            fontSize: 16,
            fontWeight: FontWeight.w500,
            height: 1.28,
          ),
          bodySmall: TextStyle(
            color: homeMuted,
            fontSize: 14,
            fontWeight: FontWeight.w600,
            height: 1.22,
          ),
          labelLarge: TextStyle(
            color: homeText,
            fontSize: 16,
            fontWeight: FontWeight.w800,
            height: 1.1,
          ),
          labelMedium: TextStyle(
            color: homeMuted,
            fontSize: 14,
            fontWeight: FontWeight.w700,
            height: 1.1,
          ),
          labelSmall: TextStyle(
            color: homeMuted,
            fontSize: 12,
            fontWeight: FontWeight.w700,
            height: 1.1,
          ),
        ),
        appBarTheme: const AppBarTheme(
          elevation: 0,
          centerTitle: false,
          backgroundColor: homeCanvas,
          foregroundColor: homeText,
          iconTheme: IconThemeData(color: homeText),
          titleTextStyle: TextStyle(
            color: homeText,
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
          toolbarHeight: 58,
          scrolledUnderElevation: 0,
          surfaceTintColor: Colors.transparent,
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
            borderRadius: BorderRadius.circular(18),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(18),
            borderSide: const BorderSide(color: homeBorder),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(18),
            borderSide: BorderSide(color: primary, width: 1.2),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(18),
            borderSide: const BorderSide(color: FoodFlowTheme.danger),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(18),
            borderSide: const BorderSide(
              color: FoodFlowTheme.danger,
              width: 1.2,
            ),
          ),
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 18,
            vertical: 18,
          ),
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
          isDense: false,
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            backgroundColor: primary,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
            minimumSize: const Size(0, 54),
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
              borderRadius: BorderRadius.circular(18),
            ),
            foregroundColor: homeText,
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),
            minimumSize: const Size(0, 52),
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
              borderRadius: BorderRadius.circular(18),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
            minimumSize: const Size(0, 54),
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
        bottomSheetTheme: const BottomSheetThemeData(
          backgroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
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
      home: const SplashScreen(),
      navigatorObservers: [routeObserver],
      onGenerateRoute: _generateRoute,
      builder: (context, child) {
        return EasyLoading.init()(context, child);
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
