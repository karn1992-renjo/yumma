import 'dart:async';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class SoundService {
  static const String _newOrderSoundAsset = 'sound/order-tone.mp3';
  static const MethodChannel _androidAudioChannel =
      MethodChannel('com.renjo.restro.android/order_audio');

  static final AudioPlayer _player = AudioPlayer();
  static final AudioPlayer _alarmPlayer = AudioPlayer();
  static Timer? _incomingOrderAlarmTimer;
  static Timer? _restoreAudioRouteTimer;
  static bool _assetUnavailable = false;
  static bool _urgentAudioPrepared = false;
  static bool _incomingOrderAlarmActive = false;

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
      await _player.setAudioContext(_urgentOrderAudioContext);
      await _alarmPlayer.setAudioContext(_urgentOrderAudioContext);
      await _player.setVolume(1);
      await _alarmPlayer.setVolume(1);
      await _player.setSourceAsset(_newOrderSoundAsset);
      await _alarmPlayer.setSourceAsset(_newOrderSoundAsset);
      _assetUnavailable = false;
    } catch (e) {
      _assetUnavailable = true;
      print('Sound init error: $e');
    }
  }

  static Future<void> playNewOrderSound() async {
    try {
      await _prepareUrgentOrderAudio();
      await _player.stop();
      await _player.setVolume(1);
      await _player.play(AssetSource(_newOrderSoundAsset));
      _scheduleAudioRouteRestore();
    } catch (e) {
      _assetUnavailable = true;
      await SystemSound.play(SystemSoundType.alert);
      print('Sound error: $e');
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

  static void startIncomingOrderAlarm() {
    if (_incomingOrderAlarmActive) return;

    _incomingOrderAlarmActive = true;
    _restoreAudioRouteTimer?.cancel();
    unawaited(_startIncomingOrderPlayback());
    _incomingOrderAlarmTimer = Timer.periodic(
      const Duration(seconds: 2),
      (_) => _pulseIncomingOrderAlert(),
    );
  }

  static Future<void> _startIncomingOrderPlayback() async {
    try {
      await _prepareUrgentOrderAudio();
      if (!_incomingOrderAlarmActive) return;
      await HapticFeedback.heavyImpact();

      await _alarmPlayer.stop();
      if (!_incomingOrderAlarmActive) return;
      await _alarmPlayer.setReleaseMode(ReleaseMode.loop);
      await _alarmPlayer.setVolume(1);
      await _alarmPlayer.play(AssetSource(_newOrderSoundAsset));
      _assetUnavailable = false;
    } catch (e) {
      _assetUnavailable = true;
      await SystemSound.play(SystemSoundType.alert);
      print('Incoming order alarm error: $e');
    }
  }

  static Future<void> _pulseIncomingOrderAlert() async {
    if (!_incomingOrderAlarmActive) return;
    await HapticFeedback.heavyImpact();
    if (_incomingOrderAlarmActive && _assetUnavailable) {
      await SystemSound.play(SystemSoundType.alert);
    }
  }

  static Future<void> stopIncomingOrderAlarm() async {
    if (!_incomingOrderAlarmActive && _incomingOrderAlarmTimer == null) return;

    _incomingOrderAlarmActive = false;
    _incomingOrderAlarmTimer?.cancel();
    _incomingOrderAlarmTimer = null;
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
