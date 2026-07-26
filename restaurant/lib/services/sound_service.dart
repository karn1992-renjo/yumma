import 'dart:async';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class SoundService {
  static const String _newOrderSoundAsset = 'sound/order-tone.mp3';
  static const MethodChannel _androidAudioChannel =
      MethodChannel('com.adgraph.yumma_vendor/order_audio');

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
    _incomingOrderAlarmTimer?.cancel();
    _restoreAudioRouteTimer?.cancel();
    _prepareUrgentOrderAudio();
    _playIncomingOrderAlarmTick();
    _incomingOrderAlarmTimer = Timer.periodic(
      const Duration(seconds: 2),
      (_) => _playIncomingOrderAlarmTick(),
    );
  }

  static Future<void> _playIncomingOrderAlarmTick() async {
    try {
      await SystemSound.play(SystemSoundType.alert);
      await HapticFeedback.heavyImpact();
      if (!_assetUnavailable) {
        await _alarmPlayer.stop();
        await _alarmPlayer.setVolume(1);
        await _alarmPlayer.play(AssetSource(_newOrderSoundAsset));
      }
    } catch (e) {
      _assetUnavailable = true;
      print('Incoming order alarm error: $e');
    }
  }

  static Future<void> stopIncomingOrderAlarm() async {
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
