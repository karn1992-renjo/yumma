import 'theatre_api_service.dart';
import 'theatre_cart_provider.dart';

/// swaad-only: the seat identity encoded in an armrest QR sticker's URL,
/// `/t/{theatre}/{screen}/{seat}/{signature}`.
///
/// Mirrors `App\Support\TheatreSeatToken::path()` on the backend. Shared by
/// the in-app scanner and the OS deep-link handler, because the two are the
/// same journey arriving by different doors: a camera inside the app, or
/// the phone's own camera/browser opening the link. Both must resolve the
/// seat identically, so neither owns the parsing.
class TheatreSeatLink {
  const TheatreSeatLink({
    required this.theatreId,
    required this.screenId,
    required this.seatLabel,
    required this.token,
  });

  final int theatreId;
  final int screenId;
  final String seatLabel;

  /// The signature the server accepts in place of a GPS geofence check.
  final String token;

  /// Parsed by position from the END of the path, so the same sticker works
  /// whether the deployment serves from a domain root or a subdirectory.
  /// Returns null for anything that is not a seat link — including every
  /// other link on the same domain.
  static TheatreSeatLink? parse(String raw) {
    final uri = Uri.tryParse(raw.trim());
    if (uri == null) return null;

    final segments = uri.pathSegments.where((s) => s.isNotEmpty).toList();
    if (segments.length < 5) return null;

    final tail = segments.sublist(segments.length - 5);
    if (tail[0] != 't') return null;

    final theatreId = int.tryParse(tail[1]);
    final screenId = int.tryParse(tail[2]);
    final seatLabel = Uri.decodeComponent(tail[3]).toUpperCase();
    final token = tail[4];

    if (theatreId == null || screenId == null) return null;
    if (seatLabel.isEmpty || token.isEmpty) return null;

    return TheatreSeatLink(
      theatreId: theatreId,
      screenId: screenId,
      seatLabel: seatLabel,
      token: token,
    );
  }

  /// Builds a cart already pinned to this seat, ready to hand to the food
  /// court screen.
  ///
  /// No coordinates are attached: the signature IS the presence proof, and
  /// the server accepts it in place of the geofence — which is what lets
  /// this work with location denied, the common case in a cinema.
  ///
  /// Throws with a message safe to show the customer.
  Future<TheatreCartProvider> resolveCart() async {
    final theatre = await TheatreApiService.instance.theatre(theatreId);
    if (theatre == null) {
      throw Exception('This theatre is not taking orders right now.');
    }

    final screens = await TheatreApiService.instance.screens(theatreId);
    final matching = screens.where((s) => s.id == screenId).toList();
    if (matching.isEmpty) {
      throw Exception('This screen is not taking orders right now.');
    }

    final cart = TheatreCartProvider();
    cart.setVenue(
      theatre: theatre,
      screen: matching.first,
      seatLabel: seatLabel,
      seatToken: token,
    );

    return cart;
  }
}
