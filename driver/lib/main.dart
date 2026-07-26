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
import 'screens/driver/driver_dashboard.dart';
import 'screens/driver/driver_order_chat_screen.dart';
import 'screens/driver/driver_order_detail_screen.dart';
import 'screens/driver/privacy_legal_screen.dart';
import 'screens/driver/driver_support_screen.dart';
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
  });
}

Future<void> _initializeStartup(
  AuthProvider authProvider,
  CartProvider cartProvider,
) async {
  try {
    await LocalCacheService.initialize();
    await SharedPreferences.getInstance();
    await SoundService.init();
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
    }
    await FirebaseNotificationService.instance.initialize();
    await IncomingOrderAlertService.instance.initialize();
    await authProvider.loadUser();
    if (authProvider.isAuthenticated && !authProvider.canUseCurrentApp) {
      await authProvider.logout();
    }
  } catch (e) {
    debugPrint('Startup initialization failed: $e');
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
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final primary = _colorFromHex(
      _branding?.driverPrimaryColorHex,
      AppConfig.primaryColor,
    );
    final secondary = _colorFromHex(
      _branding?.driverSecondaryColorHex,
      AppConfig.secondaryColor,
    );
    foodflow.applyBrandColors(primary: primary, secondary: secondary);

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
          error: foodflow.danger,
        ),
        primaryColor: primary,
        scaffoldBackgroundColor: foodflow.canvas,
        visualDensity: const VisualDensity(horizontal: -2, vertical: -2),
        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
        fontFamily: GoogleFonts.nunitoSans().fontFamily,
        useMaterial3: true,
        textTheme: AppTypography.material3(
          base: GoogleFonts.nunitoSansTextTheme(),
          textColor: foodflow.ink,
          mutedColor: foodflow.inkSoft,
        ),
        appBarTheme: const AppBarTheme(
          elevation: 0,
          centerTitle: false,
          backgroundColor: Colors.white,
          foregroundColor: foodflow.ink,
          iconTheme: IconThemeData(color: foodflow.ink),
          titleTextStyle: TextStyle(
            color: foodflow.ink,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        tabBarTheme: TabBarThemeData(
          labelColor: primary,
          unselectedLabelColor: foodflow.muted,
          indicatorColor: primary,
          labelStyle: const TextStyle(fontWeight: FontWeight.w800),
          unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w400),
        ),
        dividerTheme: const DividerThemeData(
          color: foodflow.line,
          thickness: 1,
          space: 1,
        ),
        listTileTheme: ListTileThemeData(
          iconColor: primary,
          textColor: foodflow.ink,
          titleTextStyle: const TextStyle(
            color: foodflow.ink,
            fontSize: 15,
            fontWeight: FontWeight.w800,
          ),
          subtitleTextStyle: const TextStyle(
            color: foodflow.muted,
            fontSize: 12,
            fontWeight: FontWeight.w400,
          ),
        ),
        chipTheme: ChipThemeData(
          backgroundColor: Colors.white,
          selectedColor: const Color(0xFFFFF3E8),
          checkmarkColor: primary,
          labelStyle: const TextStyle(
            color: foodflow.ink,
            fontWeight: FontWeight.w800,
            fontSize: 12,
          ),
          side: const BorderSide(color: foodflow.line),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        bottomNavigationBarTheme: BottomNavigationBarThemeData(
          backgroundColor: Colors.white,
          selectedItemColor: primary,
          unselectedItemColor: foodflow.muted,
          selectedLabelStyle: const TextStyle(fontWeight: FontWeight.w800),
          unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w400),
          type: BottomNavigationBarType.fixed,
          showUnselectedLabels: true,
          elevation: 8,
        ),
        inputDecorationTheme: InputDecorationTheme(
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: foodflow.line),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: primary, width: 1.4),
          ),
          filled: true,
          fillColor: Colors.white,
          contentPadding: AppSpacing.input,
          prefixIconColor: primary,
          suffixIconColor: foodflow.muted,
          labelStyle: const TextStyle(
            color: foodflow.muted,
            fontWeight: FontWeight.w800,
            fontSize: 15,
          ),
          hintStyle: const TextStyle(
            color: foodflow.faint,
            fontWeight: FontWeight.w400,
            fontSize: 15,
          ),
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            backgroundColor: primary,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            elevation: 0,
            textStyle: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            side: BorderSide(color: primary),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
          ),
        ),
        cardTheme: CardThemeData(
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          margin: EdgeInsets.zero,
          color: Colors.white,
        ),
        snackBarTheme: SnackBarThemeData(
          backgroundColor: primary,
          contentTextStyle: const TextStyle(color: Colors.white),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        bottomSheetTheme: const BottomSheetThemeData(
          backgroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
        ),
        floatingActionButtonTheme: FloatingActionButtonThemeData(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          elevation: 5,
          extendedTextStyle: const TextStyle(fontWeight: FontWeight.w800),
        ),
        textButtonTheme: TextButtonThemeData(
          style: TextButton.styleFrom(
            foregroundColor: primary,
            textStyle: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
      ),
      home: AppSplashScreen(
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
      case '/support':
      case '/driver/support':
        final args = settings.arguments;
        return MaterialPageRoute(
          builder: (_) => DriverSupportScreen(
            openChat: args is Map && args['openChat'] == true,
          ),
        );

      default:
        return _errorRoute('Page not found: ${settings.name}');
    }
  }
}
