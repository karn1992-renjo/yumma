import '../../../../services/delivery_zone_service.dart';

/// Checks a lat/lng against the admin's active delivery areas. Delegates to
/// [DeliveryZoneService], the shared implementation also used by the app-wide
/// location gate (`lib/screens/location_gate_screen.dart`).
Future<bool> v2IsServiceable(double lat, double lng) =>
    DeliveryZoneService().isServiceable(lat, lng);
