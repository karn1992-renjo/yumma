import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../providers/auth_provider.dart';
import '../screens/customer/theatre/theatre_food_court_screen.dart';
import '../screens/customer/theatre/theatre_seat_link.dart';
import 'navigation_service.dart';

/// swaad-only: opens the in-seat ordering flow when the phone hands us a
/// scanned armrest sticker's URL.
///
/// The sticker encodes an ordinary `https://<host>/t/...` link so that a
/// customer with no app installed still gets the web ordering page — that
/// is the whole point of the QR. But a customer who DOES have the app
/// should land in the app, on the right seat, without the web page in
/// between. That is what this does: the OS verifies the domain (Android App
/// Links / iOS Universal Links, see the manifest, Runner.entitlements and
/// the /.well-known files the backend serves) and delivers the URL here
/// instead of to the browser.
///
/// Deliberately separate from [AppsFlyerDeepLinkService], which handles
/// marketing attribution links through OneLink. This one handles a physical
/// sticker on a chair: no attribution, no deferred install, no campaign —
/// just a seat. Routing them through the attribution SDK would make every
/// snack order look like a marketing conversion.
class SeatDeepLinkService {
  SeatDeepLinkService._();

  static final SeatDeepLinkService instance = SeatDeepLinkService._();

  final AppLinks _appLinks = AppLinks();
  StreamSubscription<Uri>? _subscription;
  bool _initialized = false;

  /// Guards against the same sticker being handled twice — the cold-start
  /// link is also replayed on the stream by some platform versions.
  Uri? _lastHandled;
  DateTime? _lastHandledAt;

  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;

    // A link that cold-started the app. Anything else (a marketing link, a
    // share link) is left alone for the other handlers.
    try {
      final initial = await _appLinks.getInitialLink();
      if (initial != null) {
        await _handle(initial);
      }
    } catch (error) {
      debugPrint('SeatDeepLinkService: initial link failed: $error');
    }

    _subscription = _appLinks.uriLinkStream.listen(
      _handle,
      onError: (Object error) {
        debugPrint('SeatDeepLinkService: link stream error: $error');
      },
    );
  }

  Future<void> dispose() async {
    await _subscription?.cancel();
    _subscription = null;
    _initialized = false;
  }

  Future<void> _handle(Uri uri) async {
    final seat = TheatreSeatLink.parse(uri.toString());
    if (seat == null) return;

    if (_isDuplicate(uri)) return;
    _lastHandled = uri;
    _lastHandledAt = DateTime.now();

    final navigator = appNavigatorKey.currentState;
    if (navigator == null) {
      // The app has not built its navigator yet (a cold start racing the
      // first frame). Retry once the tree is up rather than dropping the
      // customer on the home screen with no explanation.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        unawaited(_navigate(seat, uri));
      });
      return;
    }

    await _navigate(seat, uri);
  }

  Future<void> _navigate(TheatreSeatLink seat, Uri uri) async {
    final navigator = appNavigatorKey.currentState;
    if (navigator == null) return;

    // The in-app theatre flow needs a signed-in customer, but the web page
    // this same link points at does not -- it takes a phone number only at
    // payment. So a signed-out customer is better served by the page than
    // by a login wall ten minutes into a film: hand the link back to the
    // browser, which is exactly where it would have gone without the app.
    if (!_isSignedIn(navigator)) {
      await _openInBrowser(uri);
      return;
    }

    try {
      final cart = await seat.resolveCart();
      if (!navigator.mounted) return;

      navigator.push(
        MaterialPageRoute(builder: (_) => TheatreFoodCourtScreen(cart: cart)),
      );
    } catch (error) {
      final context = navigator.overlay?.context;
      if (context == null || !navigator.mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.toString().replaceFirst('Exception: ', '')),
        ),
      );
    }
  }

  bool _isSignedIn(NavigatorState navigator) {
    try {
      final auth = Provider.of<AuthProvider>(navigator.context, listen: false);
      return auth.isAuthenticated && auth.canUseCurrentApp;
    } catch (_) {
      // No provider in scope yet; treat as signed out and let the web page
      // take it rather than risk a dead end.
      return false;
    }
  }

  /// Opened in an in-app browser view (Custom Tabs / SFSafariViewController)
  /// rather than handed to the system browser.
  ///
  /// Handing it out would bounce straight back: this app now claims that
  /// domain's /t links, so the OS would resolve the URL to us again and
  /// loop. A browser view renders the page directly without app-link
  /// resolution, which breaks the cycle — and keeps the customer inside the
  /// app shell, which is the nicer outcome anyway.
  Future<void> _openInBrowser(Uri uri) async {
    try {
      await launchUrl(uri, mode: LaunchMode.inAppBrowserView);
    } catch (error) {
      debugPrint('SeatDeepLinkService: could not open $uri: $error');
    }
  }

  bool _isDuplicate(Uri uri) {
    final at = _lastHandledAt;
    if (_lastHandled != uri || at == null) return false;

    return DateTime.now().difference(at) < const Duration(seconds: 3);
  }
}
