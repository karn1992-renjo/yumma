import 'dart:async';
import 'dart:io';

import 'package:flutter/widgets.dart';
import 'package:permission_handler/permission_handler.dart';

/// Presents the App Tracking Transparency (ATT) prompt on iOS.
///
/// Apple requires the ATT prompt to appear *before* any tracking data is
/// collected (i.e. before AppsFlyer / analytics start). iOS only presents the
/// prompt while the app is in the foreground `active` state and while no other
/// system permission alert is on screen, so this service:
///   * runs once, as early as possible after the first frame;
///   * waits for the app to actually be `resumed`;
///   * lets the first frame settle before asking;
///   * is safe to call multiple times (later calls are no-ops).
class TrackingAuthorizationService {
  TrackingAuthorizationService._();

  static final TrackingAuthorizationService instance =
      TrackingAuthorizationService._();

  Future<void>? _pending;
  bool _completed = false;

  /// Requests ATT authorization if it has not been determined yet.
  /// Resolves once the user has responded (or immediately on non-iOS / when the
  /// status is already decided). Never throws.
  Future<void> ensureRequested({bool force = false}) {
    if (_completed && !force) return Future<void>.value();
    return _pending ??= _run();
  }

  Future<void> _run() async {
    try {
      if (!Platform.isIOS) {
        _completed = true;
        return;
      }

      // Wait until the Flutter app lifecycle is truly resumed.
      await _waitUntilResumed();

      // iOS requires the window and ViewController to be active and frontmost.
      // Give the active state at least 1000ms to settle.
      await Future<void>.delayed(const Duration(milliseconds: 1000));

      final status = await Permission.appTrackingTransparency.status;
      if (_isDecided(status)) {
        // Already decided by the user (or restricted by system policy) - nothing to prompt.
        _completed = true;
        return;
      }

      final result = await Permission.appTrackingTransparency.request();
      debugPrint('[ATT] authorization result: $result');

      // In permission_handler_apple:
      // Authorized -> granted
      // Restricted -> restricted
      // Denied -> permanentlyDenied
      // NotDetermined -> denied
      // If result is still 'denied' (NotDetermined), the OS dropped the prompt
      // (e.g. window not active yet). Keep _completed false so the next screen can retry.
      if (_isDecided(result)) {
        _completed = true;
      } else {
        final recheck = await Permission.appTrackingTransparency.status;
        if (_isDecided(recheck)) {
          _completed = true;
        }
      }
    } catch (error) {
      debugPrint('[ATT] request skipped: $error');
    } finally {
      _pending = null;
    }
  }

  bool _isDecided(PermissionStatus status) {
    return status.isGranted ||
        status.isLimited ||
        status.isRestricted ||
        status.isPermanentlyDenied;
  }

  Future<void> _waitUntilResumed() async {
    for (var attempt = 0; attempt < 25; attempt++) {
      final state = WidgetsBinding.instance.lifecycleState;
      if (state == AppLifecycleState.resumed) return;
      await Future<void>.delayed(const Duration(milliseconds: 200));
    }
  }
}

