// lib/main.dart
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
import 'screens/driver/driver_dashboard.dart';
import 'screens/driver/driver_order_chat_screen.dart';
import 'screens/driver/driver_order_detail_screen.dart';
import 'screens/driver/privacy_legal_screen.dart';
import 'screens/driver/driver_support_screen.dart';
import 'widgets/common/direct_chat_bubble.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await SharedPreferences.getInstance();
    await SoundService.init();
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
    }
    await FirebaseNotificationService.instance.initialize();
    await IncomingOrderAlertService.instance.initialize();
  } catch (e) {
    debugPrint('Startup initialization failed: $e');
  }

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

  configLoading();
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
    FoodFlowTheme.applyBrandColors(
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
    final primary = _colorFromHex(
      _branding?.driverPrimaryColorHex,
      AppConfig.primaryColor,
    );
    final secondary = _colorFromHex(
      _branding?.driverSecondaryColorHex,
      AppConfig.secondaryColor,
    );
    FoodFlowTheme.applyBrandColors(primary: primary, secondary: secondary);

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
        scaffoldBackgroundColor: FoodFlowTheme.canvas,
        visualDensity: VisualDensity.adaptivePlatformDensity,
        fontFamily: 'Poppins',
        useMaterial3: true,
        textTheme: const TextTheme(
          displayLarge: TextStyle(fontSize: 30, fontWeight: FontWeight.w800, color: FoodFlowTheme.ink),
          displayMedium: TextStyle(fontSize: 27, fontWeight: FontWeight.w800, color: FoodFlowTheme.ink),
          headlineLarge: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: FoodFlowTheme.ink),
          headlineMedium: TextStyle(fontSize: 21, fontWeight: FontWeight.w800, color: FoodFlowTheme.ink),
          titleLarge: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: FoodFlowTheme.ink),
          titleMedium: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: FoodFlowTheme.ink),
          bodyLarge: TextStyle(fontSize: 16, fontWeight: FontWeight.w500, color: FoodFlowTheme.inkSoft, height: 1.4),
          bodyMedium: TextStyle(fontSize: 15, fontWeight: FontWeight.w500, color: FoodFlowTheme.inkSoft, height: 1.35),
          bodySmall: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: FoodFlowTheme.muted, height: 1.3),
          labelLarge: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: FoodFlowTheme.ink),
          labelMedium: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: FoodFlowTheme.muted),
        ),
        appBarTheme: const AppBarTheme(
          elevation: 0,
          centerTitle: false,
          backgroundColor: Colors.white,
          foregroundColor: FoodFlowTheme.ink,
          iconTheme: IconThemeData(color: FoodFlowTheme.ink),
          titleTextStyle: TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        tabBarTheme: TabBarThemeData(
          labelColor: primary,
          unselectedLabelColor: FoodFlowTheme.muted,
          indicatorColor: primary,
          labelStyle: const TextStyle(fontWeight: FontWeight.w900),
          unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
        dividerTheme: const DividerThemeData(
          color: FoodFlowTheme.line,
          thickness: 1,
          space: 1,
        ),
        listTileTheme: ListTileThemeData(
          iconColor: primary,
          textColor: FoodFlowTheme.ink,
          titleTextStyle: const TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 15,
            fontWeight: FontWeight.w800,
          ),
          subtitleTextStyle: const TextStyle(
            color: FoodFlowTheme.muted,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
        chipTheme: ChipThemeData(
          backgroundColor: Colors.white,
          selectedColor: const Color(0xFFFFF3E8),
          checkmarkColor: primary,
          labelStyle: const TextStyle(
            color: FoodFlowTheme.ink,
            fontWeight: FontWeight.w800,
            fontSize: 12,
          ),
          side: const BorderSide(color: FoodFlowTheme.line),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        bottomNavigationBarTheme: BottomNavigationBarThemeData(
          backgroundColor: Colors.white,
          selectedItemColor: primary,
          unselectedItemColor: FoodFlowTheme.muted,
          selectedLabelStyle: const TextStyle(fontWeight: FontWeight.w900),
          unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w600),
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
            borderSide: const BorderSide(color: FoodFlowTheme.line),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: BorderSide(color: primary, width: 1.4),
          ),
          filled: true,
          fillColor: Colors.white,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 18, vertical: 17),
          prefixIconColor: primary,
          suffixIconColor: FoodFlowTheme.muted,
          labelStyle: const TextStyle(
              color: FoodFlowTheme.muted, fontWeight: FontWeight.w700, fontSize: 15),
          hintStyle: const TextStyle(
              color: FoodFlowTheme.faint, fontWeight: FontWeight.w600, fontSize: 15),
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            backgroundColor: primary,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            padding: const EdgeInsets.symmetric(vertical: 16),
            elevation: 0,
            textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            side: BorderSide(color: primary),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
            padding: const EdgeInsets.symmetric(vertical: 14),
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
            borderRadius: BorderRadius.circular(10),
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
          extendedTextStyle: const TextStyle(fontWeight: FontWeight.w900),
        ),
        textButtonTheme: TextButtonThemeData(
          style: TextButton.styleFrom(
            foregroundColor: primary,
            textStyle: const TextStyle(fontWeight: FontWeight.w900),
          ),
        ),
      ),
      home: const SplashScreen(),
      onGenerateRoute: _generateRoute,
      builder: (context, child) {
        child = EasyLoading.init()(context, child);
        return Stack(
          children: [
            child,
            const DirectChatBubble(),
          ],
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
