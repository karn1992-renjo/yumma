import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import 'theatre_api_service.dart';
import 'theatre_cart_provider.dart';
import 'theatre_food_court_screen.dart';

/// swaad-only: scan the QR sticker on a cinema armrest and drop straight
/// into that seat's food court.
///
/// This is the short path through the whole flow. The manual route asks for
/// location, then a theatre, then a screen, then a seat on a map — four
/// screens of guessing, in the dark, while a film is playing. The sticker
/// already knows all four answers and carries a signature proving it, so a
/// scan skips every one of them, including the location permission (see
/// [TheatreCartProvider.seatToken]).
class TheatreScanScreen extends StatefulWidget {
  const TheatreScanScreen({super.key});

  @override
  State<TheatreScanScreen> createState() => _TheatreScanScreenState();
}

class _TheatreScanScreenState extends State<TheatreScanScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const [BarcodeFormat.qrCode],
  );

  bool _handling = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_handling) return;

    final raw = capture.barcodes
        .map((barcode) => barcode.rawValue)
        .firstWhere((value) => value != null && value.isNotEmpty,
            orElse: () => null);
    if (raw == null) return;

    final seat = _SeatQr.parse(raw);
    if (seat == null) {
      setState(() => _error = "That doesn't look like a swaad seat code.");
      return;
    }

    setState(() {
      _handling = true;
      _error = null;
    });

    try {
      final theatre = await TheatreApiService.instance.theatre(seat.theatreId);
      if (theatre == null) {
        throw Exception('This theatre is not taking orders right now.');
      }

      final screens = await TheatreApiService.instance.screens(seat.theatreId);
      final matching = screens.where((s) => s.id == seat.screenId).toList();
      if (matching.isEmpty) {
        throw Exception('This screen is not taking orders right now.');
      }
      final screen = matching.first;

      final cart = TheatreCartProvider();
      cart.setVenue(
        theatre: theatre,
        screen: screen,
        seatLabel: seat.seatLabel,
        // No coordinates: the signature IS the presence proof, and the
        // server accepts it in place of the geofence.
        seatToken: seat.token,
      );

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => TheatreFoodCourtScreen(cart: cart)),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _handling = false;
        _error = e.toString().replaceFirst('Exception: ', '');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Scan your seat',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
        ),
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),

          // A viewfinder, so people know to aim at the armrest sticker
          // rather than the screen or their ticket.
          Center(
            child: Container(
              width: 236,
              height: 236,
              decoration: BoxDecoration(
                border: Border.all(color: Colors.white70, width: 2),
                borderRadius: BorderRadius.circular(18),
              ),
            ),
          ),

          Positioned(
            left: 20,
            right: 20,
            bottom: 40,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (_handling)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 16),
                    child: CircularProgressIndicator(color: Colors.white),
                  ),
                if (_error != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 16),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 12,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(0.75),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      _error!,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                    ),
                  ),
                const Text(
                  'Point your camera at the QR sticker on your armrest',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white70,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The seat identity encoded in a sticker's URL: `/t/{theatre}/{screen}/{seat}/{signature}`.
///
/// Mirrors App\Support\TheatreSeatToken::path() on the backend. Parsed by
/// position from the END of the path so the same sticker works whether the
/// deployment serves from a domain root or a subdirectory.
class _SeatQr {
  const _SeatQr({
    required this.theatreId,
    required this.screenId,
    required this.seatLabel,
    required this.token,
  });

  final int theatreId;
  final int screenId;
  final String seatLabel;
  final String token;

  static _SeatQr? parse(String raw) {
    final uri = Uri.tryParse(raw.trim());
    if (uri == null) return null;

    final segments = uri.pathSegments;
    if (segments.length < 5) return null;

    final tail = segments.sublist(segments.length - 5);
    if (tail[0] != 't') return null;

    final theatreId = int.tryParse(tail[1]);
    final screenId = int.tryParse(tail[2]);
    final seatLabel = Uri.decodeComponent(tail[3]).toUpperCase();
    final token = tail[4];

    if (theatreId == null || screenId == null) return null;
    if (seatLabel.isEmpty || token.isEmpty) return null;

    return _SeatQr(
      theatreId: theatreId,
      screenId: screenId,
      seatLabel: seatLabel,
      token: token,
    );
  }
}
