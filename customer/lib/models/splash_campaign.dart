// lib/models/splash_campaign.dart

class SplashCampaign {
  const SplashCampaign({
    required this.id,
    required this.version,
    required this.imageUrl,
    this.logoUrl,
    this.headline,
    this.subtitle,
    this.backgroundColorHex,
    required this.displayDurationSeconds,
    required this.tapAction,
    this.tapRestaurantId,
    this.startAt,
    this.endAt,
  });

  final int id;
  final String version;
  final String imageUrl;
  final String? logoUrl;
  final String? headline;
  final String? subtitle;
  final String? backgroundColorHex;
  final int displayDurationSeconds;
  final String tapAction;
  final int? tapRestaurantId;
  final DateTime? startAt;
  final DateTime? endAt;

  static SplashCampaign? fromJson(Map<String, dynamic>? json) {
    if (json == null) return null;
    final imageUrl = json['image_url']?.toString();
    if (imageUrl == null || imageUrl.isEmpty) return null;

    final params = json['tap_action_params'];
    final restaurantId = params is Map
        ? int.tryParse((params['restaurant_id'] ?? '').toString())
        : null;

    return SplashCampaign(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      version: json['version']?.toString() ?? '',
      imageUrl: imageUrl,
      logoUrl: json['logo_url']?.toString(),
      headline: json['headline']?.toString(),
      subtitle: json['subtitle']?.toString(),
      backgroundColorHex: json['background_color']?.toString(),
      displayDurationSeconds:
          int.tryParse(json['display_duration_seconds']?.toString() ?? '') ??
              4,
      tapAction: json['tap_action']?.toString() ?? 'none',
      tapRestaurantId: restaurantId,
      startAt: DateTime.tryParse(json['start_at']?.toString() ?? ''),
      endAt: DateTime.tryParse(json['end_at']?.toString() ?? ''),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'version': version,
        'image_url': imageUrl,
        'logo_url': logoUrl,
        'headline': headline,
        'subtitle': subtitle,
        'background_color': backgroundColorHex,
        'display_duration_seconds': displayDurationSeconds,
        'tap_action': tapAction,
        'tap_action_params':
            tapRestaurantId != null ? {'restaurant_id': tapRestaurantId} : {},
        'start_at': startAt?.toIso8601String(),
        'end_at': endAt?.toIso8601String(),
      };

  // Defense-in-depth re-check against device clock -- the server already
  // filters by schedule, but a campaign served from a stale local cache
  // (app was offline since before it expired) must not linger forever.
  bool get isEligibleNow {
    final now = DateTime.now();
    if (startAt != null && now.isBefore(startAt!)) return false;
    if (endAt != null && now.isAfter(endAt!)) return false;
    return true;
  }
}
