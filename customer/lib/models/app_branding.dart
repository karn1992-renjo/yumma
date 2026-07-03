import '../config/app_config.dart';

class AppOnboardingSlide {
  final String title;
  final String description;
  final String imageUrl;

  const AppOnboardingSlide({
    required this.title,
    required this.description,
    required this.imageUrl,
  });

  factory AppOnboardingSlide.fromJson(Map<String, dynamic> json) {
    return AppOnboardingSlide(
      title: (json['title'] ?? '').toString().trim(),
      description: (json['description'] ?? '').toString().trim(),
      imageUrl: (json['image'] ?? '').toString().trim(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'title': title,
      'description': description,
      'image': imageUrl,
    };
  }
}

class AppBranding {
  final String appName;
  final String appLogoUrl;
  final String appIconUrl;
  final String appFaviconUrl;
  final String primaryColorHex;
  final String secondaryColorHex;
  final String supportEmail;
  final String supportPhone;
  final String defaultMobileCountryCode;
  final String pusherAppKey;
  final String pusherAppCluster;
  final String otpServiceProvider;
  final bool socialLoginEnabled;
  final bool googleLoginEnabled;
  final bool appleLoginEnabled;
  final String googleWebClientId;
  final String appleServicesId;
  final String customerPlayStoreUrl;
  final String customerDeeplinkScheme;
  final String customerDeeplinkBaseUrl;
  final String customerOrderDeeplinkTemplate;
  final String customerRestaurantDeeplinkTemplate;
  final String customerWalletDeeplinkTemplate;
  final String headerBrandingType;
  final String onboardingIntroTitle;
  final String onboardingIntroSubtitle;
  final List<AppOnboardingSlide> onboardingSlides;

  const AppBranding({
    required this.appName,
    this.appLogoUrl = '',
    this.appIconUrl = '',
    this.appFaviconUrl = '',
    this.primaryColorHex = '#8B5CF6',
    this.secondaryColorHex = '#2B2A33',
    this.supportEmail = '',
    this.supportPhone = '',
    this.defaultMobileCountryCode = '+91',
    this.pusherAppKey = '',
    this.pusherAppCluster = 'mt1',
    this.otpServiceProvider = '',
    this.socialLoginEnabled = false,
    this.googleLoginEnabled = false,
    this.appleLoginEnabled = false,
    this.googleWebClientId = '',
    this.appleServicesId = '',
    this.customerPlayStoreUrl = '',
    this.customerDeeplinkScheme = 'foodflow',
    this.customerDeeplinkBaseUrl = '',
    this.customerOrderDeeplinkTemplate = 'foodflow://orders/{order_id}',
    this.customerRestaurantDeeplinkTemplate =
        'foodflow://restaurants/{restaurant_id}',
    this.customerWalletDeeplinkTemplate = 'foodflow://wallet',
    this.headerBrandingType = 'text',
    this.onboardingIntroTitle = '',
    this.onboardingIntroSubtitle = '',
    this.onboardingSlides = const [],
  });

  factory AppBranding.fromJson(Map<String, dynamic> json) {
    final slides = (json['onboarding_slides'] as List?)
            ?.whereType<Map>()
            .map((slide) => AppOnboardingSlide.fromJson(
                  Map<String, dynamic>.from(slide),
                ))
            .toList() ??
        const <AppOnboardingSlide>[];

    final socialLogin = json['social_login'] is Map
        ? Map<String, dynamic>.from(json['social_login'] as Map)
        : const <String, dynamic>{};
    final socialProviders = socialLogin['providers'] is Map
        ? Map<String, dynamic>.from(socialLogin['providers'] as Map)
        : const <String, dynamic>{};
    final googleProvider = socialProviders['google'] is Map
        ? Map<String, dynamic>.from(socialProviders['google'] as Map)
        : const <String, dynamic>{};
    final appleProvider = socialProviders['apple'] is Map
        ? Map<String, dynamic>.from(socialProviders['apple'] as Map)
        : const <String, dynamic>{};

    return AppBranding(
      appName: (json['app_name'] ?? AppConfig.appName).toString().trim(),
      appLogoUrl: (json['app_logo'] ?? '').toString().trim(),
      appIconUrl: (json['app_icon'] ?? '').toString().trim(),
      appFaviconUrl: (json['app_favicon'] ?? '').toString().trim(),
      primaryColorHex: (json['primary_color'] ?? '#8B5CF6').toString().trim(),
      secondaryColorHex:
          (json['secondary_color'] ?? '#2B2A33').toString().trim(),
      supportEmail: (json['support_email'] ?? '').toString().trim(),
      supportPhone: (json['support_phone'] ?? '').toString().trim(),
      defaultMobileCountryCode: _normalizeCountryCode(
        (json['default_mobile_country_code'] ?? '+91').toString(),
      ),
      pusherAppKey: (json['pusher_app_key'] ?? '').toString().trim(),
      pusherAppCluster: (json['pusher_app_cluster'] ?? 'mt1').toString().trim(),
      otpServiceProvider:
          (json['otp_service_provider'] ?? '').toString().trim(),
      socialLoginEnabled: _parseBool(socialLogin['enabled']),
      googleLoginEnabled: _parseBool(googleProvider['enabled']),
      appleLoginEnabled: _parseBool(appleProvider['enabled']),
      googleWebClientId:
          (googleProvider['web_client_id'] ?? '').toString().trim(),
      appleServicesId: (appleProvider['services_id'] ?? '').toString().trim(),
      customerPlayStoreUrl:
          (json['customer_play_store_url'] ?? '').toString().trim(),
      customerDeeplinkScheme:
          (json['customer_deeplink_scheme'] ?? 'foodflow').toString().trim(),
      customerDeeplinkBaseUrl:
          (json['customer_deeplink_base_url'] ?? '').toString().trim(),
      customerOrderDeeplinkTemplate:
          (json['customer_order_deeplink_template'] ??
                  'foodflow://orders/{order_id}')
              .toString()
              .trim(),
      customerRestaurantDeeplinkTemplate:
          (json['customer_restaurant_deeplink_template'] ??
                  'foodflow://restaurants/{restaurant_id}')
              .toString()
              .trim(),
      customerWalletDeeplinkTemplate:
          (json['customer_wallet_deeplink_template'] ?? 'foodflow://wallet')
              .toString()
              .trim(),
      headerBrandingType:
          (json['header_branding_type'] ?? 'text').toString().trim(),
      onboardingIntroTitle:
          (json['onboarding_intro_title'] ?? AppConfig.appName)
              .toString()
              .trim(),
      onboardingIntroSubtitle: (json['onboarding_intro_subtitle'] ??
              'Food, groceries and everyday cravings delivered fast.')
          .toString()
          .trim(),
      onboardingSlides: slides,
    );
  }

  factory AppBranding.fallback() {
    return AppBranding(
      appName: AppConfig.appName,
      supportEmail: AppConfig.supportEmail,
      supportPhone: AppConfig.supportPhone,
      defaultMobileCountryCode: '+91',
      pusherAppCluster: 'mt1',
      customerDeeplinkScheme: 'foodflow',
      customerOrderDeeplinkTemplate: 'foodflow://orders/{order_id}',
      customerRestaurantDeeplinkTemplate: 'foodflow://restaurants/{restaurant_id}',
      customerWalletDeeplinkTemplate: 'foodflow://wallet',
      onboardingIntroTitle: AppConfig.appName,
      onboardingIntroSubtitle:
          'Food, groceries and everyday cravings delivered fast.',
      onboardingSlides: const [
        AppOnboardingSlide(
          title:
              'Choose your favourite dishes from the nearest restaurant or cafe',
          description: 'Fresh food from the kitchens you already love.',
          imageUrl:
              'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=80',
        ),
        AppOnboardingSlide(
          title: 'Taste fresh delicious meals anytime anywhere',
          description: 'Fast ordering, clear tracking, and smooth checkout.',
          imageUrl:
              'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=900&q=80',
        ),
        AppOnboardingSlide(
          title:
              'We also deliver food, drinks, groceries from the nearest supermarket',
          description:
              'One app for meals, essentials, and late-night cravings.',
          imageUrl:
              'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=80',
        ),
      ],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'app_name': appName,
      'app_logo': appLogoUrl,
      'app_icon': appIconUrl,
      'app_favicon': appFaviconUrl,
      'primary_color': primaryColorHex,
      'secondary_color': secondaryColorHex,
      'support_email': supportEmail,
      'support_phone': supportPhone,
      'default_mobile_country_code': defaultMobileCountryCode,
      'pusher_app_key': pusherAppKey,
      'pusher_app_cluster': pusherAppCluster,
      'otp_service_provider': otpServiceProvider,
      'social_login': {
        'enabled': socialLoginEnabled,
        'providers': {
          'google': {
            'enabled': googleLoginEnabled,
            'web_client_id': googleWebClientId,
          },
          'apple': {
            'enabled': appleLoginEnabled,
            'services_id': appleServicesId,
          },
        },
      },
      'customer_play_store_url': customerPlayStoreUrl,
      'customer_deeplink_scheme': customerDeeplinkScheme,
      'customer_deeplink_base_url': customerDeeplinkBaseUrl,
      'customer_order_deeplink_template': customerOrderDeeplinkTemplate,
      'customer_restaurant_deeplink_template':
          customerRestaurantDeeplinkTemplate,
      'customer_wallet_deeplink_template': customerWalletDeeplinkTemplate,
      'header_branding_type': headerBrandingType,
      'onboarding_intro_title': onboardingIntroTitle,
      'onboarding_intro_subtitle': onboardingIntroSubtitle,
      'onboarding_slides':
          onboardingSlides.map((slide) => slide.toJson()).toList(),
    };
  }

  String get displayName => appName.isEmpty ? AppConfig.appName : appName;
  String get resolvedPusherAppKey => pusherAppKey.trim();
  String get resolvedPusherAppCluster {
    final cluster = pusherAppCluster.trim();
    return cluster.isEmpty ? 'mt1' : cluster;
  }

  String get resolvedOtpServiceProvider {
    return otpServiceProvider.trim().toLowerCase();
  }

  bool get usesFirebasePhoneAuth => resolvedOtpServiceProvider == 'firebase';
  bool get usesGoogleLogin => socialLoginEnabled && googleLoginEnabled;
  bool get usesAppleLogin => socialLoginEnabled && appleLoginEnabled;

  String orderDeepLink(int orderId) {
    return customerOrderDeeplinkTemplate.isEmpty
        ? '$customerDeeplinkScheme://orders/$orderId'
        : customerOrderDeeplinkTemplate.replaceAll('{order_id}', '$orderId');
  }

  String restaurantDeepLink(int restaurantId) {
    return customerRestaurantDeeplinkTemplate.isEmpty
        ? '$customerDeeplinkScheme://restaurants/$restaurantId'
        : customerRestaurantDeeplinkTemplate
            .replaceAll('{restaurant_id}', '$restaurantId');
  }

  String get walletDeepLink {
    return customerWalletDeeplinkTemplate.isEmpty
        ? '$customerDeeplinkScheme://wallet'
        : customerWalletDeeplinkTemplate;
  }

  String get preferredLogoUrl {
    if (appLogoUrl.isNotEmpty) return _resolveAssetUrl(appLogoUrl);
    if (appIconUrl.isNotEmpty) return _resolveAssetUrl(appIconUrl);
    return _resolveAssetUrl(appFaviconUrl);
  }

  static String _resolveAssetUrl(String rawValue) {
    final value = rawValue.trim();
    if (value.isEmpty || value == 'null') return '';
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }

    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final origin = '${apiUri.scheme}://${apiUri.host}';
    final normalized = value.startsWith('/') ? value.substring(1) : value;

    if (normalized.startsWith('storage/')) {
      return '$origin/$normalized';
    }

    return '$origin/storage/$normalized';
  }

  List<AppOnboardingSlide> get resolvedOnboardingSlides {
    final fallbackSlides = AppBranding.fallback().onboardingSlides;
    if (onboardingSlides.length < 3) {
      return fallbackSlides;
    }

    return List<AppOnboardingSlide>.generate(onboardingSlides.length, (index) {
      final slide = onboardingSlides[index];
      final fallback =
          fallbackSlides[index.clamp(0, fallbackSlides.length - 1)];

      return AppOnboardingSlide(
        title: slide.title.isEmpty ? fallback.title : slide.title,
        description: slide.description.isEmpty
            ? fallback.description
            : slide.description,
        imageUrl: slide.imageUrl.isEmpty ? fallback.imageUrl : slide.imageUrl,
      );
    });
  }

  String get resolvedOnboardingIntroTitle => onboardingIntroTitle.isEmpty
      ? AppBranding.fallback().onboardingIntroTitle
      : onboardingIntroTitle;

  String get resolvedOnboardingIntroSubtitle => onboardingIntroSubtitle.isEmpty
      ? AppBranding.fallback().onboardingIntroSubtitle
      : onboardingIntroSubtitle;

  static String _normalizeCountryCode(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return '+91';
    return trimmed.startsWith('+') ? trimmed : '+$trimmed';
  }

  static bool _parseBool(dynamic value) {
    if (value is bool) return value;
    if (value is num) return value != 0;

    final normalized = value?.toString().trim().toLowerCase();
    return normalized == '1' ||
        normalized == 'true' ||
        normalized == 'yes' ||
        normalized == 'enabled';
  }
}
