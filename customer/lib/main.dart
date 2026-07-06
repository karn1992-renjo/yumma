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
import 'services/navigation_service.dart';
import 'services/sound_service.dart';
import 'services/local_cache_service.dart';
import 'services/app_branding_service.dart';
import 'config/app_config.dart';
import 'models/app_branding.dart';
import 'theme/foodflow_theme.dart';
import 'theme/brand_palette.dart';
import 'providers/auth_provider.dart';
import 'providers/cart_provider.dart';
import 'providers/order_provider.dart';
import 'providers/restaurant_provider.dart';
import 'providers/dining_provider.dart';
import 'models/order.dart';
import 'models/address.dart' as app_address;
import 'screens/splash_screen.dart';
import 'screens/onboarding/onboarding_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/auth/forgot_password_screen.dart';
import 'screens/customer/home_screen.dart';
import 'screens/customer/restaurant_detail_screen.dart';
import 'screens/customer/cart_screen.dart';
import 'screens/customer/checkout_screen.dart';

import 'screens/customer/map_picker_screen.dart';
import 'screens/customer/saved_restaurants_screen.dart';
import 'screens/customer/offers_screen.dart';
import 'screens/customer/privacy_legal_screen.dart';
import 'screens/customer/order_confirmation_screen.dart';
import 'screens/customer/order_chat_screen.dart';
import 'screens/customer/order_tracking_screen.dart';
import 'screens/customer/orders_screen.dart';
import 'screens/customer/notifications_screen.dart';
import 'screens/customer/profile_screen.dart';
import 'screens/customer/address_screen.dart';
import 'screens/customer/add_address_screen.dart';
import 'screens/customer/search_screen.dart';
import 'screens/customer/customer_support_screen.dart';
import 'screens/customer/dining_bookings_list_screen.dart';
import 'screens/customer/wallet_screen.dart';
import 'widgets/common/animated_loading_spinner.dart';
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
        ChangeNotifierProvider(create: (_) => DiningProvider()),
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
}

Future<void> _ensureFirebaseInitialized() async {
  if (Firebase.apps.isNotEmpty) return;

  if (!kIsWeb && defaultTargetPlatform == TargetPlatform.iOS) {
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
    ..indicatorWidget = const AnimatedLoadingSpinner(
      size: 44,
      strokeWidth: 4,
      color: Colors.white,
    )
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

int? _parseRestaurantId(dynamic args) {
  if (args == null) return null;
  if (args is int) return args > 0 ? args : null;
  if (args is double) {
    final value = args.toInt();
    return value > 0 ? value : null;
  }
  if (args is String) {
    final value = int.tryParse(args.trim());
    return value != null && value > 0 ? value : null;
  }
  if (args is Map) {
    return _parseRestaurantId(args['restaurantId'] ?? args['id']);
  }
  return null;
}

int? _parseMenuItemId(dynamic args) {
  if (args == null) return null;
  if (args is int) return args > 0 ? args : null;
  if (args is double) {
    final value = args.toInt();
    return value > 0 ? value : null;
  }
  if (args is String) {
    final value = int.tryParse(args.trim());
    return value != null && value > 0 ? value : null;
  }
  if (args is Map) {
    return _parseMenuItemId(
      args['menuItemId'] ?? args['menu_item_id'] ?? args['item_id'],
    );
  }
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
    setState(() {
      _branding = branding;
    });
  }

  @override
  Widget build(BuildContext context) {
    final palette = BrandPalette.fromBranding(_branding);
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
          seedColor: palette.primary,
          primary: palette.primary,
          secondary: palette.secondary,
          surface: Colors.white,
          error: FoodFlowTheme.danger,
        ),
        primaryColor: palette.primary,
        scaffoldBackgroundColor: homeCanvas,
        visualDensity: const VisualDensity(horizontal: -3, vertical: -3),
        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
        fontFamily: GoogleFonts.nunitoSans().fontFamily,
        useMaterial3: true,
        textTheme: GoogleFonts.nunitoSansTextTheme().copyWith(
          displayLarge: const TextStyle(
            color: homeText,
            fontSize: 30,
            fontWeight: FontWeight.w800,
            height: 1.02,
          ),
          displayMedium: const TextStyle(
            color: homeText,
            fontSize: 26,
            fontWeight: FontWeight.w800,
            height: 1.06,
          ),
          headlineLarge: const TextStyle(
            color: homeText,
            fontSize: 23,
            fontWeight: FontWeight.w800,
            height: 1.08,
          ),
          headlineMedium: const TextStyle(
            color: homeText,
            fontSize: 21,
            fontWeight: FontWeight.w800,
            height: 1.1,
          ),
          titleLarge: const TextStyle(
            color: homeText,
            fontSize: 18,
            fontWeight: FontWeight.w800,
            height: 1.16,
          ),
          titleMedium: const TextStyle(
            color: homeText,
            fontSize: 16,
            fontWeight: FontWeight.w700,
            height: 1.18,
          ),
          titleSmall: const TextStyle(
            color: homeText,
            fontSize: 14,
            fontWeight: FontWeight.w700,
            height: 1.18,
          ),
          bodyLarge: const TextStyle(
            color: homeMuted,
            fontSize: 16,
            fontWeight: FontWeight.w600,
            height: 1.3,
          ),
          bodyMedium: const TextStyle(
            color: homeMuted,
            fontSize: 14,
            fontWeight: FontWeight.w500,
            height: 1.28,
          ),
          bodySmall: const TextStyle(
            color: homeMuted,
            fontSize: 12,
            fontWeight: FontWeight.w500,
            height: 1.22,
          ),
          labelLarge: const TextStyle(
            color: homeText,
            fontSize: 15,
            fontWeight: FontWeight.w700,
            height: 1.1,
          ),
          labelMedium: const TextStyle(
            color: homeMuted,
            fontSize: 13,
            fontWeight: FontWeight.w700,
            height: 1.1,
          ),
          labelSmall: const TextStyle(
            color: homeMuted,
            fontSize: 11,
            fontWeight: FontWeight.w500,
            height: 1.1,
          ),
        ),
        appBarTheme: AppBarTheme(
          elevation: 0,
          centerTitle: false,
          backgroundColor: homeCanvas,
          foregroundColor: homeText,
          iconTheme: const IconThemeData(color: homeText),
          titleTextStyle: const TextStyle(
            color: homeText,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
          toolbarHeight: 52,
          scrolledUnderElevation: 0,
          surfaceTintColor: Colors.transparent,
        ),
        tabBarTheme: TabBarThemeData(
          labelColor: palette.primary,
          unselectedLabelColor: FoodFlowTheme.muted,
          indicatorColor: palette.primary,
          labelStyle:
              const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          unselectedLabelStyle:
              const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          dividerColor: Colors.transparent,
        ),
        dividerTheme: const DividerThemeData(
          color: FoodFlowTheme.line,
          thickness: 1,
          space: 1,
        ),
        listTileTheme: ListTileThemeData(
          iconColor: palette.primary,
          textColor: homeText,
          dense: true,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
          minLeadingWidth: 18,
          titleTextStyle: const TextStyle(
            color: homeText,
            fontSize: 15,
            fontWeight: FontWeight.w700,
          ),
          subtitleTextStyle: const TextStyle(
            color: homeMuted,
            fontSize: 13,
            fontWeight: FontWeight.w500,
          ),
        ),
        chipTheme: ChipThemeData(
          backgroundColor: palette.primarySoft,
          selectedColor: palette.primarySoft,
          checkmarkColor: palette.primary,
          labelStyle: TextStyle(
            color: palette.text,
            fontWeight: FontWeight.w700,
            fontSize: 12,
          ),
          side: BorderSide(color: palette.primary.withOpacity(0.16)),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 1),
        ),
        bottomNavigationBarTheme: BottomNavigationBarThemeData(
          backgroundColor: Colors.white,
          selectedItemColor: palette.primary,
          unselectedItemColor: FoodFlowTheme.muted,
          unselectedLabelStyle:
              const TextStyle(fontWeight: FontWeight.w600, fontSize: 12),
          selectedLabelStyle:
              const TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
          selectedIconTheme: const IconThemeData(size: 20),
          unselectedIconTheme: const IconThemeData(size: 18),
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
            borderSide: BorderSide(color: palette.primary, width: 1.2),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(18),
            borderSide: const BorderSide(color: FoodFlowTheme.danger),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(18),
            borderSide:
                const BorderSide(color: FoodFlowTheme.danger, width: 1.2),
          ),
          filled: true,
          fillColor: Colors.white,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          prefixIconColor: palette.primary,
          suffixIconColor: homeMuted,
          labelStyle: const TextStyle(
              color: homeMuted, fontWeight: FontWeight.w700, fontSize: 15),
          hintStyle: const TextStyle(
              color: homeMuted, fontWeight: FontWeight.w600, fontSize: 15),
          floatingLabelStyle: TextStyle(
              color: palette.primary,
              fontWeight: FontWeight.w700,
              fontSize: 15),
          isDense: false,
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            backgroundColor: palette.primary,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
            minimumSize: const Size(0, 52),
            textStyle: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.2,
            ),
            elevation: 0,
            shadowColor: Colors.transparent,
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            side: BorderSide(color: homeBorder),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
            ),
            foregroundColor: homeText,
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),
            minimumSize: const Size(0, 50),
            textStyle: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.2,
            ),
          ),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            backgroundColor: palette.primary,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
            minimumSize: const Size(0, 52),
            textStyle: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
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
          backgroundColor: palette.primary,
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
          backgroundColor: palette.primary,
          foregroundColor: Colors.white,
          elevation: 3,
          extendedTextStyle:
              const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
        ),
        textButtonTheme: TextButtonThemeData(
          style: TextButton.styleFrom(
            foregroundColor: palette.primary,
            textStyle:
                const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            minimumSize: const Size(0, 32),
          ),
        ),
      ),
      home: const SplashScreen(),
      navigatorObservers: [routeObserver],
      onGenerateRoute: (settings) => _generateRoute(context, settings),
      builder: (context, child) {
        return EasyLoading.init()(context, child);
      },
    );
  }

  Route<dynamic>? _generateRoute(BuildContext context, RouteSettings settings) {
    final deepLinkRoute = _generateDeepLinkRoute(settings);
    if (deepLinkRoute != null) {
      return deepLinkRoute;
    }

    switch (settings.name) {
      // Onboarding Route
      case '/onboarding':
        return MaterialPageRoute(builder: (_) => const OnboardingScreen());
      // Auth Routes
      case '/login':
        return MaterialPageRoute(builder: (_) => const LoginScreen());
      case '/login/form':
        return MaterialPageRoute(builder: (_) => const LoginScreen());
      case '/register':
        final args = settings.arguments;
        if (args is Map) {
          return MaterialPageRoute(
            builder: (_) => RegisterScreen(
              initialPhone: args['phone']?.toString(),
              initialEmail: args['email']?.toString(),
            ),
          );
        }
        return MaterialPageRoute(builder: (_) => const RegisterScreen());
      case '/forgot-password':
        return MaterialPageRoute(
          builder: (_) => const ForgotPasswordScreen(),
        );
      case '/home':
        final authProvider = Provider.of<AuthProvider>(context, listen: false);
        if (!authProvider.isAuthenticated || !authProvider.canUseCurrentApp) {
          return MaterialPageRoute(builder: (_) => const LoginScreen());
        }
        return MaterialPageRoute(builder: (_) => const HomeScreen());

      // Customer Routes
      case '/customer/home':
        return MaterialPageRoute(builder: (_) => const HomeScreen());
      case '/restaurant/detail':
        final restaurantId = _parseRestaurantId(settings.arguments);
        if (restaurantId != null) {
          return MaterialPageRoute(
            builder: (_) => RestaurantDetailScreen(
              restaurantId: restaurantId,
              initialMenuItemId: _parseMenuItemId(settings.arguments),
            ),
          );
        }
        return _errorRoute('Invalid restaurant ID');
      case '/cart':
        return MaterialPageRoute(builder: (_) => const CartScreen());
      case '/checkout':
        return MaterialPageRoute(builder: (_) => const CheckoutScreen());
      case '/map-picker':
        final args = settings.arguments;
        return MaterialPageRoute(
          builder: (_) => MapPickerScreen(
            address: args is app_address.Address ? args : null,
          ),
        );
      case '/saved-restaurants':
        return MaterialPageRoute(
            builder: (_) => const SavedRestaurantsScreen());
      case '/offers':
        return MaterialPageRoute(builder: (_) => const OffersScreen());
      case '/privacy-legal':
        return MaterialPageRoute(builder: (_) => const PrivacyLegalScreen());
      case '/order/confirmation':
        final args = settings.arguments;
        if (args is Map && args['orderId'] is int) {
          return MaterialPageRoute(
            builder: (_) => OrderConfirmationScreen(
              orderId: args['orderId'] as int,
              orderNumber:
                  args['orderNumber']?.toString() ?? 'ORD${args['orderId']}',
              restaurantName: args['restaurantName']?.toString() ?? '',
              restaurantLogoUrl: args['restaurantLogoUrl']?.toString() ?? '',
              paymentGatewayName: args['paymentGatewayName']?.toString() ?? '',
              paymentGatewayLogoUrl:
                  args['paymentGatewayLogoUrl']?.toString() ?? '',
              subtotal: (args['subtotal'] is num)
                  ? (args['subtotal'] as num).toDouble()
                  : double.tryParse(args['subtotal']?.toString() ?? '0') ?? 0,
              discount: (args['discount'] is num)
                  ? (args['discount'] as num).toDouble()
                  : double.tryParse(args['discount']?.toString() ?? '0') ?? 0,
              deliveryFee: (args['deliveryFee'] is num)
                  ? (args['deliveryFee'] as num).toDouble()
                  : double.tryParse(args['deliveryFee']?.toString() ?? '0') ??
                      0,
              platformFee: (args['platformFee'] is num)
                  ? (args['platformFee'] as num).toDouble()
                  : double.tryParse(args['platformFee']?.toString() ?? '0') ??
                      0,
              tax: (args['tax'] is num)
                  ? (args['tax'] as num).toDouble()
                  : double.tryParse(args['tax']?.toString() ?? '0') ?? 0,
              taxLabel: args['taxLabel']?.toString() ?? 'Tax',
              total: (args['total'] is num)
                  ? (args['total'] as num).toDouble()
                  : double.tryParse(args['total']?.toString() ?? '0') ?? 0,
              couponCode: args['couponCode']?.toString(),
              scheduledTime: args['scheduledTime'] != null
                  ? DateTime.tryParse(args['scheduledTime'].toString())
                  : null,
            ),
          );
        }
        return _errorRoute('Invalid order confirmation data');
      case '/order/track':
        final orderId = _parseOrderId(settings.arguments);
        if (orderId == null) {
          return _errorRoute('Invalid order ID supplied for order tracking.');
        }
        return MaterialPageRoute(
          builder: (_) => OrderTrackingScreen(orderId: orderId),
        );
      case '/order/chat':
        final args = settings.arguments;
        if (args is Order) {
          return MaterialPageRoute(
            builder: (_) => OrderChatScreen(order: args),
          );
        }
        if (args is Map && args['order'] is Order) {
          return MaterialPageRoute(
            builder: (_) => OrderChatScreen(
              order: args['order'] as Order,
              orderId: _parseOrderId(args['orderId']),
            ),
          );
        }
        final orderId =
            _parseOrderId(args is Map ? args['orderId'] ?? args['id'] : args);
        if (orderId == null) {
          return _errorRoute('Invalid order ID supplied for order chat.');
        }
        return MaterialPageRoute(
          builder: (_) => OrderChatScreen(orderId: orderId),
        );
      case '/orders':
        return MaterialPageRoute(builder: (_) => const OrdersScreen());
      case '/profile':
        return MaterialPageRoute(builder: (_) => const ProfileScreen());
      case '/addresses':
        return MaterialPageRoute(builder: (_) => const AddressScreen());
      case '/addresses/add':
        final args = settings.arguments;
        return MaterialPageRoute(
          builder: (_) => AddAddressScreen(address: args),
        );
      case '/addresses/edit':
        final args = settings.arguments;
        return MaterialPageRoute(
          builder: (_) => AddAddressScreen(address: args),
        );
      case '/search':
        return MaterialPageRoute(builder: (_) => const SearchScreen());
      case '/notifications':
        return MaterialPageRoute(builder: (_) => const NotificationsScreen());
      case '/support':
        final args = settings.arguments;
        if (args is Map) {
          return MaterialPageRoute(
            builder: (_) => CustomerSupportScreen(
              order: args['order'] is Order ? args['order'] as Order : null,
              openChat: args['openChat'] == true,
            ),
          );
        }
        return MaterialPageRoute(builder: (_) => const CustomerSupportScreen());
      case '/wallet':
        return MaterialPageRoute(builder: (_) => const WalletScreen());
      case '/dining/bookings':
        return MaterialPageRoute(
          builder: (_) => const DiningBookingsListScreen(),
        );

      default:
        return _errorRoute('Page not found: ${settings.name}');
    }
  }

  Route<dynamic>? _generateDeepLinkRoute(RouteSettings settings) {
    final rawName = settings.name?.trim();
    if (rawName == null || rawName.isEmpty) return null;

    Uri? uri;
    try {
      uri = Uri.parse(rawName);
    } catch (_) {
      return null;
    }

    final appsFlyerRoute = _generateAppsFlyerFallbackRoute(uri);
    if (appsFlyerRoute != null) {
      return appsFlyerRoute;
    }

    final routeParts = <String>[
      if (uri.scheme != 'http' && uri.scheme != 'https' && uri.host.isNotEmpty)
        uri.host,
      ...uri.pathSegments,
    ].where((part) => part.trim().isNotEmpty).toList(growable: false);

    if (routeParts.isEmpty && uri.host.isNotEmpty) {
      if (uri.host == 'wallet') {
        return MaterialPageRoute(builder: (_) => const WalletScreen());
      }
      return null;
    }

    final queryRestaurantId = _parseRestaurantId(
      uri.queryParameters['restaurantId'] ??
          uri.queryParameters['restaurant_id'] ??
          uri.queryParameters['id'],
    );
    final queryMenuItemId = _parseMenuItemId(
      uri.queryParameters['menuItemId'] ??
          uri.queryParameters['menu_item_id'] ??
          uri.queryParameters['item_id'],
    );

    if (queryRestaurantId != null &&
        (uri.queryParameters['screen'] == 'restaurant_detail' ||
            routeParts.contains('restaurant') ||
            routeParts.contains('restaurants'))) {
      return MaterialPageRoute(
        builder: (_) => RestaurantDetailScreen(
          restaurantId: queryRestaurantId,
          initialMenuItemId: queryMenuItemId,
        ),
      );
    }

    if (routeParts.length >= 2 &&
        (routeParts.first == 'orders' || routeParts.first == 'order')) {
      final orderId = int.tryParse(routeParts[1]);
      if (orderId != null) {
        return MaterialPageRoute(
          builder: (_) => OrderTrackingScreen(orderId: orderId),
        );
      }
    }

    if (routeParts.length >= 2 &&
        (routeParts.first == 'restaurants' ||
            routeParts.first == 'restaurant')) {
      final restaurantId = int.tryParse(routeParts[1]);
      if (restaurantId != null) {
        return MaterialPageRoute(
          builder: (_) => RestaurantDetailScreen(
            restaurantId: restaurantId,
            initialMenuItemId: queryMenuItemId,
          ),
        );
      }
    }

    if (routeParts.isNotEmpty && routeParts.first == 'wallet') {
      return MaterialPageRoute(builder: (_) => const WalletScreen());
    }

    return null;
  }

  Route<dynamic>? _generateAppsFlyerFallbackRoute(Uri uri) {
    final params = uri.queryParameters;
    final hasAppsFlyerParams = params.containsKey('af_deeplink') ||
        params.containsKey('af_dp') ||
        params.containsKey('deep_link_value') ||
        params.containsKey('deep_link_sub1');
    if (!hasAppsFlyerParams) return null;

    final appDeepLink =
        params['af_dp'] ?? params['deep_link'] ?? params['link'];
    if (appDeepLink != null && appDeepLink.trim().isNotEmpty) {
      final nestedRoute = _generateDeepLinkRoute(
        RouteSettings(name: appDeepLink.trim()),
      );
      if (nestedRoute != null) return nestedRoute;
    }

    final screen =
        params['deep_link_value'] ?? params['screen'] ?? params['type'];
    final primaryId = int.tryParse(params['deep_link_sub1'] ?? '');
    final secondaryId = int.tryParse(params['deep_link_sub2'] ?? '');

    switch (screen) {
      case 'restaurant':
      case 'branch':
        if (primaryId != null && primaryId > 0) {
          return MaterialPageRoute(
            builder: (_) => RestaurantDetailScreen(restaurantId: primaryId),
          );
        }
        break;
      case 'product':
      case 'menu_item':
      case 'item':
        if (primaryId != null &&
            primaryId > 0 &&
            secondaryId != null &&
            secondaryId > 0) {
          return MaterialPageRoute(
            builder: (_) => RestaurantDetailScreen(
              restaurantId: secondaryId,
              initialMenuItemId: primaryId,
            ),
          );
        }
        break;
    }

    return MaterialPageRoute(builder: (_) => const HomeScreen());
  }
}
