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
  Future<void> ensureRequested() {
    if (_completed) return Future<void>.value();
    return _pending ??= _run();
  }

  Future<void> _run() async {
    try {
      if (!Platform.isIOS) return;

      await _waitUntilResumed();
      // iOS silently drops the prompt if asked before the app has fully
      // finished presenting its first frame.
      await Future<void>.delayed(const Duration(milliseconds: 600));

      final status = await Permission.appTrackingTransparency.status;
      if (status.isGranted ||
          status.isLimited ||
          status.isRestricted ||
          status.isPermanentlyDenied) {
        // Already decided by the user (or unavailable) - nothing to prompt.
        return;
      }

      final result = await Permission.appTrackingTransparency.request();
      debugPrint('[ATT] authorization result: $result');
    } catch (error) {
      debugPrint('[ATT] request skipped: $error');
    } finally {
      _completed = true;
      _pending = null;
    }
  }

  Future<void> _waitUntilResumed() async {
    for (var attempt = 0; attempt < 25; attempt++) {
      final state = WidgetsBinding.instance.lifecycleState;
      if (state == null || state == AppLifecycleState.resumed) return;
      await Future<void>.delayed(const Duration(milliseconds: 200));
    }
  }
}
