class ScratchCard {
  const ScratchCard({
    required this.id,
    required this.title,
    required this.status,
    this.orderId,
    this.orderNumber,
    this.restaurantName,
    this.restaurantLogoUrl,
    this.rewardTitle,
    this.rewardType,
    this.reward,
    this.issuedAt,
    this.viewedAt,
    this.scratchedAt,
    this.revealedAt,
    this.rewardGeneratedAt,
    this.rewardCreditedAt,
    this.redeemedAt,
    this.expiresAt,
  });

  final int id;
  final int? orderId;
  final String? orderNumber;
  final String? restaurantName;
  final String? restaurantLogoUrl;
  final String title;
  final String status;
  final String? rewardTitle;
  final String? rewardType;
  final Map<String, dynamic>? reward;
  final DateTime? issuedAt;
  final DateTime? viewedAt;
  final DateTime? scratchedAt;
  final DateTime? revealedAt;
  final DateTime? rewardGeneratedAt;
  final DateTime? rewardCreditedAt;
  final DateTime? redeemedAt;
  final DateTime? expiresAt;

  bool get isRevealed => status == 'revealed' || revealedAt != null;
  bool get isExpired =>
      status == 'expired' || (expiresAt?.isBefore(DateTime.now()) ?? false);

  factory ScratchCard.fromJson(Map<String, dynamic> json) {
    return ScratchCard(
      id: _toInt(json['id']),
      orderId: _toNullableInt(json['order_id']),
      orderNumber: json['order_number']?.toString(),
      restaurantName: json['restaurant_name']?.toString(),
      restaurantLogoUrl: json['restaurant_logo_url']?.toString(),
      title: json['title']?.toString() ?? 'Scratch & Win',
      status: json['status']?.toString() ?? 'issued',
      rewardTitle: json['reward_title']?.toString(),
      rewardType: json['reward_type']?.toString(),
      reward: json['reward'] is Map
          ? Map<String, dynamic>.from(json['reward'] as Map)
          : null,
      issuedAt: _toDate(json['issued_at']),
      viewedAt: _toDate(json['viewed_at']),
      scratchedAt: _toDate(json['scratched_at']),
      revealedAt: _toDate(json['revealed_at']),
      rewardGeneratedAt: _toDate(json['reward_generated_at']),
      rewardCreditedAt: _toDate(json['reward_credited_at']),
      redeemedAt: _toDate(json['redeemed_at']),
      expiresAt: _toDate(json['expires_at']),
    );
  }

  static int _toInt(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static int? _toNullableInt(dynamic value) {
    final parsed = _toInt(value);
    return parsed > 0 ? parsed : null;
  }

  static DateTime? _toDate(dynamic value) {
    if (value is DateTime) return value;
    return DateTime.tryParse(value?.toString() ?? '');
  }
}
