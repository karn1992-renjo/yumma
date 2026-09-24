import '../../../services/api_service.dart';
import 'theatre_models.dart';

/// swaad-only: thin wrapper around ApiService for the 5 theatre-ordering
/// endpoints. Deliberately separate from the main app's order/restaurant
/// API calls -- see Api\TheatreController on the backend (additive, does
/// not touch the existing checkout).
class TheatreApiService {
  TheatreApiService._();
  static final TheatreApiService instance = TheatreApiService._();

  final ApiService _api = ApiService();

  Future<List<TheatreSummary>> nearby({
    required double lat,
    required double lng,
  }) async {
    final response = await _api.get(
      '/theatre/nearby',
      queryParams: {'lat': lat, 'lng': lng},
    );
    return _listFrom(response, TheatreSummary.fromJson);
  }

  /// One theatre by id, for the QR-scan flow: a scanned sticker already
  /// names its theatre, and location is often denied inside a cinema --
  /// which is precisely the case the sticker exists to solve.
  Future<TheatreSummary?> theatre(int theatreId) async {
    final response = await _api.get('/theatre/$theatreId');
    if (response is Map<String, dynamic> &&
        response['success'] == true &&
        response['data'] is Map) {
      return TheatreSummary.fromJson(
        Map<String, dynamic>.from(response['data'] as Map),
      );
    }
    return null;
  }

  Future<List<TheatreScreenSeatMap>> screens(int theatreId) async {
    final response = await _api.get('/theatre/$theatreId/screens');
    return _listFrom(response, TheatreScreenSeatMap.fromJson);
  }

  /// What is playing on this screen right now, for the timing choice at
  /// checkout. An empty list simply means no schedule has been loaded and
  /// only deliver-now is offered.
  Future<List<TheatreShowOption>> shows(int screenId) async {
    final response = await _api.get('/theatre/screens/$screenId/shows');
    return _listFrom(response, TheatreShowOption.fromJson);
  }

  Future<List<TheatreRestaurant>> restaurants(int theatreId) async {
    final response = await _api.get('/theatre/$theatreId/restaurants');
    return _listFrom(response, TheatreRestaurant.fromJson);
  }

  /// Reuses the SAME menu endpoint the rest of the app already uses
  /// (`/restaurants/{id}/menu`) — plain menu browsing needs nothing new.
  Future<List<Map<String, dynamic>>> menuForRestaurant(int restaurantId) async {
    final response = await _api.get('/restaurants/$restaurantId/menu');
    final data = response is Map && response['data'] is Map
        ? Map<String, dynamic>.from(response['data'] as Map)
        : (response is Map ? Map<String, dynamic>.from(response) : const <String, dynamic>{});
    final items = data['menu_items'] ?? data['items'] ?? data['menu'];
    if (items is List) {
      return items
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    return const [];
  }

  /// Creates a real gateway payment session (Razorpay/Stripe/Cashfree) for
  /// the server-computed total of this cart, BEFORE any order exists --
  /// the app runs the gateway SDK against this session, then calls
  /// [checkout] with the resulting proof. Throws [TheatreCheckoutException]
  /// with the server's own message (bad geofence/seat/item, or a real
  /// gateway failure) on any rejection.
  /// Presence is proven by EITHER [seatToken] (the signature off the
  /// scanned armrest sticker) or [lat]/[lng] — the server requires one of
  /// the two, so the manual pick-a-seat flow keeps sending coordinates and
  /// the scan flow sends the signature instead.
  Future<Map<String, dynamic>> paymentSession({
    required int theatreId,
    required int screenId,
    required String seatLabel,
    double? lat,
    double? lng,
    String? seatToken,
    int? showId,
    String? deliveryTiming,
    required List<Map<String, dynamic>> stalls,
    required String gateway,
  }) async {
    try {
      final response = await _api.post('/theatre/checkout/payment-session', data: {
        'theatre_id': theatreId,
        'screen_id': screenId,
        'seat_label': seatLabel,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        if (seatToken != null) 'seat_token': seatToken,
        if (showId != null) 'show_id': showId,
        if (deliveryTiming != null) 'delivery_timing': deliveryTiming,
        'stalls': stalls,
        'payment_method': gateway,
      });

      if (response is Map<String, dynamic> &&
          response['success'] == true &&
          response['data'] is Map) {
        return Map<String, dynamic>.from(response['data'] as Map);
      }

      throw TheatreCheckoutException(
        (response is Map ? response['message']?.toString() : null) ??
            'Could not start payment.',
      );
    } on TheatreCheckoutException {
      rethrow;
    } catch (e) {
      throw TheatreCheckoutException(_messageFromException(e));
    }
  }

  /// Throws [TheatreCheckoutException] with the server's own message on any
  /// rejection (bad geofence, invalid seat, unavailable item, failed
  /// payment, ...) so the UI can show exactly why, not a generic error.
  Future<TheatreOrderGroupResult> checkout({
    required int theatreId,
    required int screenId,
    required String seatLabel,
    double? lat,
    double? lng,
    String? seatToken,
    int? showId,
    String? deliveryTiming,
    required List<Map<String, dynamic>> stalls,
    required String paymentMethod,
    String? paymentId,
    String? razorpayOrderId,
    String? razorpaySignature,
    String? stripePaymentIntentId,
  }) async {
    try {
      final response = await _api.post('/theatre/checkout', data: {
        'theatre_id': theatreId,
        'screen_id': screenId,
        'seat_label': seatLabel,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        if (seatToken != null) 'seat_token': seatToken,
        if (showId != null) 'show_id': showId,
        if (deliveryTiming != null) 'delivery_timing': deliveryTiming,
        'stalls': stalls,
        'payment_method': paymentMethod,
        if (paymentId != null) 'payment_id': paymentId,
        if (razorpayOrderId != null) 'razorpay_order_id': razorpayOrderId,
        if (razorpaySignature != null)
          'razorpay_signature': razorpaySignature,
        if (stripePaymentIntentId != null)
          'stripe_payment_intent_id': stripePaymentIntentId,
      });

      if (response is Map<String, dynamic> &&
          response['success'] == true &&
          response['data'] is Map) {
        return TheatreOrderGroupResult.fromJson(
          Map<String, dynamic>.from(response['data'] as Map),
        );
      }

      throw TheatreCheckoutException(
        (response is Map ? response['message']?.toString() : null) ??
            'Could not place this order.',
      );
    } on TheatreCheckoutException {
      rethrow;
    } catch (e) {
      throw TheatreCheckoutException(_messageFromException(e));
    }
  }

  Future<Map<String, dynamic>> orderGroup(int orderGroupId) async {
    final response = await _api.get('/theatre/order-groups/$orderGroupId');
    if (response is Map<String, dynamic> &&
        response['success'] == true &&
        response['data'] is Map) {
      return Map<String, dynamic>.from(response['data'] as Map);
    }
    throw TheatreCheckoutException('Could not load this order.');
  }

  List<T> _listFrom<T>(
    dynamic response,
    T Function(Map<String, dynamic>) mapper,
  ) {
    if (response is Map<String, dynamic> &&
        response['success'] == true &&
        response['data'] is List) {
      return (response['data'] as List)
          .map((e) => mapper(Map<String, dynamic>.from(e as Map)))
          .toList();
    }
    return const [];
  }

  String _messageFromException(Object e) {
    // ApiException's toString() is already the server's own message (or a
    // clear "Request timed out..." from the ApiService timeout guard); a
    // generic Exception wraps it as "Network error: ...".
    final text = e.toString();
    final prefixed = text.startsWith('Exception: ') ? text.substring(11) : text;
    return prefixed.isEmpty ? 'Could not place this order.' : prefixed;
  }
}

class TheatreCheckoutException implements Exception {
  final String message;
  const TheatreCheckoutException(this.message);

  @override
  String toString() => message;
}
