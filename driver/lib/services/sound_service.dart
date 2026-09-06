import 'dart:async';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:vibration/vibration.dart';

class SoundService {
  static const String _newOrderSoundAsset = 'sound/order-tone.mp3';
  static final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();
  static bool _alarmChannelReady = false;
  static const MethodChannel _androidAudioChannel =
      MethodChannel('com.adgraph.yamma_delivery/order_audio');

  static final AudioPlayer _player = AudioPlayer();
  static final AudioPlayer _alarmPlayer = AudioPlayer();
  static Timer? _incomingOrderAlarmTimer;
  static Timer? _restoreAudioRouteTimer;
  static bool _assetUnavailable = false;
  static bool _urgentAudioPrepared = false;

  static final AudioContext _urgentOrderAudioContext = AudioContext(
    android: AudioContextAndroid(
      isSpeakerphoneOn: true,
      audioMode: AndroidAudioMode.inCommunication,
      stayAwake: true,
      contentType: AndroidContentType.sonification,
      usageType: AndroidUsageType.alarm,
      audioFocus: AndroidAudioFocus.gainTransientExclusive,
    ),
    iOS: AudioContextIOS(
      category: AVAudioSessionCategory.playback,
    ),
  );
  
  static Future<void> init() async {
    try {
      await AudioPlayer.global.setAudioContext(_urgentOrderAudioContext);
      await _player.setReleaseMode(ReleaseMode.stop);
      await _alarmPlayer.setReleaseMode(ReleaseMode.loop);
      await _player.setAudioContext(_urgentOrderAudioContext);
      await _alarmPlayer.setAudioContext(_urgentOrderAudioContext);
      await _player.setVolume(1);
      await _alarmPlayer.setVolume(1);
      await _player.setSourceAsset(_newOrderSoundAsset);
      await _alarmPlayer.setSourceAsset(_newOrderSoundAsset);
      _assetUnavailable = false;
      debugPrint('SoundService: init ok');
    } catch (e) {
      _assetUnavailable = true;
      debugPrint('SoundService: init error $e');
    }
    // Pre-warm the alarm notification channel so the first real alert isn't
    // delayed by plugin init + channel creation.
    unawaited(_ensureAlarmChannel());
  }

  static Future<void> _ensureAlarmChannel() async {
    if (_alarmChannelReady) return;
    try {
      await _localNotifications.initialize(
        settings: const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher'),
          iOS: DarwinInitializationSettings(),
        ),
      );
      await _localNotifications
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(
        const AndroidNotificationChannel(
          'incoming_order_channel',
          'Incoming Orders',
          description: 'Urgent store and driver order alerts',
          importance: Importance.max,
          playSound: true,
          enableVibration: true,
          audioAttributesUsage: AudioAttributesUsage.alarm,
        ),
      );
      _alarmChannelReady = true;
    } catch (e) {
      debugPrint('SoundService: alarm channel prep failed $e');
    }
  }
  
  static Future<void> playNewOrderSound() async {
    debugPrint('SoundService: playNewOrderSound');
    // Instant, no awaits.
    HapticFeedback.heavyImpact();
    SystemSound.play(SystemSoundType.alert);
    try {
      Vibration.vibrate(pattern: [0, 350, 180, 350, 180, 500], repeat: 0);
    } catch (_) {}
    unawaited(_fireAlarmNotification());

    _prepareUrgentOrderAudio();
    try {
      await _player.setVolume(1);
      await _player.seek(Duration.zero);
      await _player.resume();
      _scheduleAudioRouteRestore();
    } catch (_) {
      try {
        await _player.play(AssetSource(_newOrderSoundAsset));
      } catch (e) {
        debugPrint('SoundService: playNewOrderSound error $e');
      }
    }
  }

  static Future<void> playMessageSound() async {
    try {
      await _player.stop();
      await _player.setVolume(.35);
      await _player.play(AssetSource(_newOrderSoundAsset));
    } catch (e) {
      await SystemSound.play(SystemSoundType.alert);
      print('Message sound error: $e');
    }
  }

  /// OS-level alarm-channel notification. Guaranteed to make sound + vibrate
  /// even when the in-process AudioPlayer loses the audio-focus fight with
  /// another app.
  static Future<void> _fireAlarmNotification() async {
    try {
      await _ensureAlarmChannel();
      await _localNotifications.show(
        id: 90901,
        title: 'New delivery request',
        body: 'Tap to review the order before the timer runs out.',
        notificationDetails: const NotificationDetails(
          android: AndroidNotificationDetails(
            'incoming_order_channel',
            'Incoming Orders',
            channelDescription: 'Urgent store and driver order alerts',
            importance: Importance.max,
            priority: Priority.max,
            category: AndroidNotificationCategory.call,
            fullScreenIntent: true,
            audioAttributesUsage: AudioAttributesUsage.alarm,
            enableVibration: true,
          ),
          iOS: DarwinNotificationDetails(
            interruptionLevel: InterruptionLevel.critical,
          ),
        ),
      );
    } catch (e) {
      debugPrint('SoundService: alarm notification failed $e');
    }
  }

  static Future<void> _cancelAlarmNotification() async {
    try {
      await _localNotifications.cancel(id: 90901);
    } catch (_) {}
  }

  static void startIncomingOrderAlarm() {
    debugPrint('SoundService: startIncomingOrderAlarm');
    _incomingOrderAlarmTimer?.cancel();
    _restoreAudioRouteTimer?.cancel();

    // --- instant feedback, no awaits ---
    HapticFeedback.heavyImpact();
    SystemSound.play(SystemSoundType.alert);
    try {
      Vibration.vibrate(pattern: [0, 400, 200, 400, 200, 600], repeat: 0);
    } catch (_) {}
    unawaited(_fireAlarmNotification()); // channel is pre-warmed in init()

    // --- the looped tone (best-effort, may lose the audio-focus fight) ---
    _prepareUrgentOrderAudio();
    () async {
      try {
        // Source is already loaded from init(); seek+resume is far quicker
        // than stop()+play(AssetSource) which re-decodes the asset.
        await _alarmPlayer.setReleaseMode(ReleaseMode.loop);
        await _alarmPlayer.setVolume(1);
        await _alarmPlayer.seek(Duration.zero);
        await _alarmPlayer.resume();
        debugPrint('SoundService: alarm loop resumed');
      } catch (e) {
        try {
          await _alarmPlayer.play(AssetSource(_newOrderSoundAsset));
        } catch (e2) {
          debugPrint('SoundService: alarm loop failed $e2');
        }
      }
    }();

    _incomingOrderAlarmTimer = Timer.periodic(
      const Duration(seconds: 2),
      (_) => _playIncomingOrderAlarmTick(),
    );
  }

  static Future<void> _playIncomingOrderAlarmTick() async {
    try {
      SystemSound.play(SystemSoundType.alert);
      HapticFeedback.heavyImpact();
      // If the looped player somehow stopped, nudge it back.
      if (!_assetUnavailable && _alarmPlayer.state != PlayerState.playing) {
        await _alarmPlayer.setVolume(1);
        try {
          await _alarmPlayer.resume();
        } catch (_) {
          await _alarmPlayer.play(AssetSource(_newOrderSoundAsset));
        }
      }
    } catch (e) {
      debugPrint('SoundService: alarm tick error $e');
    }
  }

  static Future<void> stopIncomingOrderAlarm() async {
    _incomingOrderAlarmTimer?.cancel();
    _incomingOrderAlarmTimer = null;
    try {
      Vibration.cancel();
    } catch (_) {}
    unawaited(_cancelAlarmNotification());
    try {
      await _player.stop();
      await _alarmPlayer.stop();
    } catch (_) {}
    await _restoreNormalAudioRoute();
  }
  
  static Future<void> playOrderAcceptedSound() async {
    try {
      await playNewOrderSound();
    } catch (e) {
      print('Sound error: $e');
    }
  }
  
  static Future<void> dispose() async {
    await stopIncomingOrderAlarm();
    await _player.dispose();
    await _alarmPlayer.dispose();
  }

  static Future<void> _prepareUrgentOrderAudio() async {
    if (defaultTargetPlatform != TargetPlatform.android) {
      return;
    }

    try {
      await _androidAudioChannel.invokeMethod('prepareUrgentOrderAudio');
      _urgentAudioPrepared = true;
    } catch (e) {
      debugPrint('Urgent audio route prepare skipped: $e');
    }
  }

  static void _scheduleAudioRouteRestore() {
    if (_incomingOrderAlarmTimer != null) return;
    _restoreAudioRouteTimer?.cancel();
    _restoreAudioRouteTimer = Timer(
      const Duration(seconds: 5),
      _restoreNormalAudioRoute,
    );
  }

  static Future<void> _restoreNormalAudioRoute() async {
    _restoreAudioRouteTimer?.cancel();
    _restoreAudioRouteTimer = null;

    if (!_urgentAudioPrepared) return;

    try {
      await _androidAudioChannel.invokeMethod('restoreNormalAudio');
    } catch (e) {
      debugPrint('Urgent audio route restore skipped: $e');
    } finally {
      _urgentAudioPrepared = false;
    }
  }
}
