import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../services/api_service.dart';
import '../../services/sound_service.dart';
import '../../theme/aurora_theme.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/aurora/aurora.dart';

/// Test order notification + delivery troubleshooting.
///
/// Contract (new): POST /api/restaurant/notifications/test
///   -> pushes a real FCM "test order" to every device token registered for this
///      restaurant owner; returns { success, data:{ sent_to: <n> } }
class NotificationTestScreen extends StatefulWidget {
  const NotificationTestScreen({super.key});

  @override
  State<NotificationTestScreen> createState() => _NotificationTestScreenState();
}

class _NotificationTestScreenState extends State<NotificationTestScreen> {
  final ApiService _api = ApiService();
  final _local = FlutterLocalNotificationsPlugin();

  AuthorizationStatus? _permission;
  String? _token;
  bool _checking = true;
  bool _sending = false;
  String? _lastResult;

  @override
  void initState() {
    super.initState();
    _diagnose();
  }

  Future<void> _diagnose() async {
    setState(() => _checking = true);
    try {
      final settings = await FirebaseMessaging.instance.getNotificationSettings();
      _permission = settings.authorizationStatus;
      _token = await FirebaseMessaging.instance.getToken();
    } catch (_) {}
    if (mounted) setState(() => _checking = false);
  }

  Future<void> _askPermission() async {
    await FirebaseMessaging.instance
        .requestPermission(alert: true, badge: true, sound: true);
    await _diagnose();
  }

  Future<void> _sendTest() async {
    setState(() {
      _sending = true;
      _lastResult = null;
    });

    // 1. Ask the server to push a real FCM to all registered devices.
    String serverResult;
    try {
      final res = await _api.post('/restaurant/notifications/test');
      if (res is Map && res['success'] == true) {
        final n = (res['data'] is Map ? res['data']['sent_to'] : null) ?? '';
        serverResult = 'Server sent a push to $n device(s).';
      } else {
        serverResult =
            (res is Map ? res['message']?.toString() : null) ??
                'Server push unavailable.';
      }
    } catch (_) {
      serverResult =
          'Server test endpoint not available yet — showing a local preview instead.';
    }

    // 2. Always fire a local preview so the alert style + sound is verified now.
    try {
      await SoundService.playNewOrderSound();
      await _local.show(
        id: 424242,
        title: 'Test order · #TEST1234',
        body: '1 item · ₹199 · Prepaid — this is a test alert',
        notificationDetails: const NotificationDetails(
          android: AndroidNotificationDetails(
            'orders',
            'Orders',
            channelDescription: 'New order alerts',
            importance: Importance.max,
            priority: Priority.high,
            playSound: true,
          ),
          iOS: DarwinNotificationDetails(presentSound: true),
        ),
      );
    } catch (_) {}

    if (mounted) {
      setState(() {
        _sending = false;
        _lastResult = serverResult;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top + 60;
    final granted = _permission == AuthorizationStatus.authorized ||
        _permission == AuthorizationStatus.provisional;
    final hasToken = (_token ?? '').isNotEmpty;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Text('Order alerts',
            style: TextStyle(
                color: foodflow.ink,
                fontSize: 17,
                fontWeight: FontWeight.w900)),
      ),
      body: Stack(children: [
        ...AuroraTheme.auroraBlobs(),
        ListView(
          padding: EdgeInsets.fromLTRB(16, topPad, 16, 28),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: foodflow.brandGradient,
                borderRadius: BorderRadius.circular(22),
              ),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.notifications_active_rounded,
                        color: Colors.white, size: 22),
                    const SizedBox(height: 10),
                    const Text('Send a test order alert',
                        style: TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(
                      'Fires a sample "new order" notification with sound so you can '
                      'confirm alerts reach this device.',
                      style: TextStyle(
                          color: Colors.white.withOpacity(0.85),
                          fontSize: 12.5,
                          height: 1.35),
                    ),
                    const SizedBox(height: 14),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _sending ? null : _sendTest,
                        icon: _sending
                            ? const SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2, color: Colors.white))
                            : const Icon(Icons.send_rounded),
                        label: Text(_sending ? 'Sending…' : 'Send test alert'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: foodflow.orange,
                          minimumSize: const Size.fromHeight(48),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                          textStyle:
                              const TextStyle(fontWeight: FontWeight.w900),
                        ),
                      ),
                    ),
                    if (_lastResult != null) ...[
                      const SizedBox(height: 10),
                      Text(_lastResult!,
                          style: TextStyle(
                              color: Colors.white.withOpacity(0.9),
                              fontSize: 11.5,
                              fontWeight: FontWeight.w700)),
                    ],
                  ]),
            ),
            const SizedBox(height: 16),
            Text('DELIVERY CHECKLIST',
                style: TextStyle(
                    color: foodflow.muted,
                    fontSize: 11,
                    letterSpacing: 0.6,
                    fontWeight: FontWeight.w900)),
            const SizedBox(height: 10),
            _check(
              ok: granted,
              title: 'Notification permission',
              detail: granted
                  ? 'Allowed for this app.'
                  : 'Blocked. Alerts cannot be shown until you allow them.',
              action: granted ? null : ('Allow', _askPermission),
            ),
            _check(
              ok: hasToken,
              title: 'Device registered for push',
              detail: hasToken
                  ? 'This device has a delivery token.'
                  : 'No push token yet. Reopen the app or check your internet.',
              action: hasToken ? null : ('Retry', _diagnose),
            ),
            _check(
              ok: true,
              title: 'Alert sound',
              detail:
                  'Plays the new-order chime. If silent, check the phone ringer / Do Not Disturb.',
              action: ('Play', () => SoundService.playNewOrderSound()),
            ),
            _check(
              ok: !granted ? false : true,
              title: 'Background delivery (Android)',
              detail:
                  'For alerts while the app is closed, disable battery optimisation for this app in phone Settings → Apps.',
            ),
            if (_checking)
              const Padding(
                padding: EdgeInsets.only(top: 12),
                child: Center(child: CircularProgressIndicator()),
              ),
          ],
        ),
      ]),
    );
  }

  Widget _check({
    required bool ok,
    required String title,
    required String detail,
    (String, VoidCallback)? action,
  }) {
    final color = ok ? const Color(0xFF16A34A) : const Color(0xFFE2546A);
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: foodflow.line),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(ok ? Icons.check_circle_rounded : Icons.error_rounded,
              color: color, size: 18),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w900)),
                const SizedBox(height: 2),
                Text(detail,
                    style: TextStyle(
                        color: foodflow.muted, fontSize: 11.5, height: 1.35)),
              ],
            ),
          ),
          if (action != null)
            TextButton(
              onPressed: action.$2,
              style: TextButton.styleFrom(
                foregroundColor: foodflow.orange,
                visualDensity: VisualDensity.compact,
              ),
              child: Text(action.$1),
            ),
        ],
      ),
    );
  }
}
