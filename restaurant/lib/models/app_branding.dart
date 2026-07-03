import '../config/app_config.dart';

class AppBranding {
  final String appName;
  final String appLogoUrl;
  final String appIconUrl;
  final String appFaviconUrl;
  final String primaryColorHex;
  final String secondaryColorHex;
  final String restaurantPrimaryColorHex;
  final String restaurantSecondaryColorHex;
  final String supportEmail;
  final String supportPhone;
  final String defaultMobileCountryCode;
  final String pusherAppKey;
  final String pusherAppCluster;
  final String otpServiceProvider;

  const AppBranding({
    required this.appName,
    this.appLogoUrl = '',
    this.appIconUrl = '',
    this.appFaviconUrl = '',
    this.primaryColorHex = '#0A9443',
    this.secondaryColorHex = '#0C7038',
    this.restaurantPrimaryColorHex = '#0A9443',
    this.restaurantSecondaryColorHex = '#0C7038',
    this.supportEmail = '',
    this.supportPhone = '',
    this.defaultMobileCountryCode = '+91',
    this.pusherAppKey = '',
    this.pusherAppCluster = 'mt1',
    this.otpServiceProvider = 'log',
  });

  factory AppBranding.fromJson(Map<String, dynamic> json) {
    return AppBranding(
      appName: (json['app_name'] ?? AppConfig.appName).toString().trim(),
      appLogoUrl: (json['app_logo'] ?? '').toString().trim(),
      appIconUrl: (json['app_icon'] ?? '').toString().trim(),
      appFaviconUrl: (json['app_favicon'] ?? '').toString().trim(),
      primaryColorHex: (json['primary_color'] ?? '#0A9443').toString().trim(),
      secondaryColorHex:
          (json['secondary_color'] ?? '#0C7038').toString().trim(),
      restaurantPrimaryColorHex:
          (json['restaurant_primary_color'] ?? json['primary_color'] ?? '#0A9443')
              .toString()
              .trim(),
      restaurantSecondaryColorHex: (json['restaurant_secondary_color'] ??
              json['secondary_color'] ??
              '#0C7038')
          .toString()
          .trim(),
      supportEmail: (json['support_email'] ?? '').toString().trim(),
      supportPhone: (json['support_phone'] ?? '').toString().trim(),
      defaultMobileCountryCode: _normalizeCountryCode(
        (json['default_mobile_country_code'] ?? '+91').toString(),
      ),
      pusherAppKey: (json['pusher_app_key'] ?? '').toString().trim(),
      pusherAppCluster: (json['pusher_app_cluster'] ?? 'mt1').toString().trim(),
      otpServiceProvider:
          (json['otp_service_provider'] ?? 'log').toString().trim(),
    );
  }

  factory AppBranding.fallback() {
    return AppBranding(
      appName: AppConfig.appName,
      supportEmail: AppConfig.supportEmail,
      supportPhone: AppConfig.supportPhone,
      defaultMobileCountryCode: '+91',
      pusherAppCluster: 'mt1',
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
      'restaurant_primary_color': restaurantPrimaryColorHex,
      'restaurant_secondary_color': restaurantSecondaryColorHex,
      'support_email': supportEmail,
      'support_phone': supportPhone,
      'default_mobile_country_code': defaultMobileCountryCode,
      'pusher_app_key': pusherAppKey,
      'pusher_app_cluster': pusherAppCluster,
      'otp_service_provider': otpServiceProvider,
    };
  }

  String get displayName => appName.isEmpty ? AppConfig.appName : appName;
  String get preferredLogoUrl {
    if (appLogoUrl.isNotEmpty) return appLogoUrl;
    if (appIconUrl.isNotEmpty) return appIconUrl;
    return appFaviconUrl;
  }

  String get resolvedPusherAppKey => pusherAppKey.trim();
  String get resolvedPusherAppCluster {
    final cluster = pusherAppCluster.trim();
    return cluster.isEmpty ? 'mt1' : cluster;
  }

  String get resolvedOtpServiceProvider {
    final provider = otpServiceProvider.trim().toLowerCase();
    return provider.isEmpty ? 'log' : provider;
  }

  bool get usesFirebasePhoneAuth => resolvedOtpServiceProvider == 'firebase';

  static String _normalizeCountryCode(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return '+91';
    return trimmed.startsWith('+') ? trimmed : '+$trimmed';
  }
}
