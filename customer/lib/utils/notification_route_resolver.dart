// Shared deep-link resolution used by both the FCM notification handler
// (notification_service.dart) and the in-app notifications list
// (notifications_screen.dart), so the two don't drift out of sync on how a
// notification's `data` payload maps to a route.
class NotificationRoute {
  const NotificationRoute(this.name, [this.arguments]);

  final String name;
  final Object? arguments;
}

/// Resolves the `deep_link`/`type` fields of a notification payload into a
/// route, or null if this payload isn't a generic deep-link case (callers
/// should apply their own further fallback, e.g. order-status handling).
NotificationRoute? resolveNotificationDeepLink(Map<String, dynamic> data) {
  final deepLink = data['deep_link']?.toString().trim();
  final type = data['type']?.toString().trim().toLowerCase() ?? '';

  if (deepLink != null && deepLink.isNotEmpty) {
    final chatMatch = RegExp(r'^/orders/(\d+)/chat$').firstMatch(deepLink);
    if (chatMatch != null) {
      final orderId = int.tryParse(chatMatch.group(1) ?? '');
      return orderId == null
          ? null
          : NotificationRoute('/order/chat', orderId);
    }

    if (deepLink == '/support') {
      return const NotificationRoute('/support', {'openChat': true});
    }

    if (deepLink == '/order/track') {
      final orderId = _parseOrderId(data['order_id'] ?? data['id']);
      return orderId == null
          ? null
          : NotificationRoute('/order/track', orderId);
    }

    return NotificationRoute(deepLink);
  }

  if (type.contains('support')) {
    return const NotificationRoute('/support', {'openChat': true});
  }

  return null;
}

int? _parseOrderId(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}
