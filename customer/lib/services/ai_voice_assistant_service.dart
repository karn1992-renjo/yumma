import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:audioplayers/audioplayers.dart';
import 'package:record/record.dart';

import '../config/api_constants.dart';
import '../config/app_config.dart';
import 'api_service.dart';

class AiVoiceRuntimeConfig {
  const AiVoiceRuntimeConfig({
    required this.enabled,
    required this.serviceEnabled,
    required this.allowanceEnabled,
    required this.remainingSeconds,
    required this.sessionLimitSeconds,
    required this.idleTimeoutSeconds,
    required this.bargeInEnabled,
    required this.language,
    required this.gatewayUrl,
    required this.gatewayWsUrl,
  });

  final bool enabled;
  final bool serviceEnabled;
  final bool allowanceEnabled;
  final int remainingSeconds;
  final int sessionLimitSeconds;
  final int idleTimeoutSeconds;
  final bool bargeInEnabled;
  final String language;
  final String gatewayUrl;
  final String gatewayWsUrl;

  factory AiVoiceRuntimeConfig.fromJson(Map<String, dynamic> json) {
    return AiVoiceRuntimeConfig(
      enabled: json['enabled'] == true,
      serviceEnabled: json['service_enabled'] != false,
      allowanceEnabled: json['allowance_enabled'] != false,
      remainingSeconds: int.tryParse('${json['remaining_seconds'] ?? 0}') ?? 0,
      sessionLimitSeconds:
          int.tryParse('${json['session_limit_seconds'] ?? 600}') ?? 600,
      idleTimeoutSeconds:
          int.tryParse('${json['idle_timeout_seconds'] ?? 45}') ?? 45,
      bargeInEnabled: json['barge_in_enabled'] != false,
      language: json['language']?.toString() ?? 'auto',
      gatewayUrl: json['gateway_url']?.toString() ?? AppConfig.aiVoiceBaseUrl,
      gatewayWsUrl: json['gateway_ws_url']?.toString() ?? '',
    );
  }
}

class AiVoiceGatewayEvent {
  const AiVoiceGatewayEvent({required this.type, this.text, this.message});

  final String type;
  final String? text;
  final String? message;
}

typedef AiVoiceEventHandler = void Function(AiVoiceGatewayEvent event);

class AiVoiceAssistantService {
  AiVoiceAssistantService({ApiService? api}) : _api = api ?? ApiService();

  final ApiService _api;
  final AudioPlayer _player = AudioPlayer();
  final AudioRecorder _recorder = AudioRecorder();
  final BytesBuilder _assistantAudio = BytesBuilder(copy: false);

  WebSocket? _socket;
  StreamSubscription<Uint8List>? _recordingSubscription;
  StreamSubscription<dynamic>? _socketSubscription;
  AiVoiceEventHandler? _onEvent;
  AiVoiceRuntimeConfig? _runtimeConfig;
  String? _voiceToken;
  DateTime? _voiceTokenExpiresAt;
  int _assistantSampleRate = 16000;

  bool get isConnected => _socket != null;

  Future<AiVoiceRuntimeConfig> connect({AiVoiceEventHandler? onEvent}) async {
    _onEvent = onEvent;
    final token = await _validVoiceToken();
    final config = _runtimeConfig;
    if (config == null || !config.enabled) {
      throw ApiException('AI voice assistant is not available.');
    }

    await disconnect(sendEnd: false);
    final socket =
        await WebSocket.connect(_voiceWebSocketUri(config).toString())
            .timeout(const Duration(seconds: 20));
    _socket = socket;
    _socketSubscription = socket.listen(
      _handleSocketMessage,
      onError: (_) => _emit(const AiVoiceGatewayEvent(
        type: 'error',
        message: 'AI voice connection failed.',
      )),
      onDone: () => _emit(const AiVoiceGatewayEvent(type: 'disconnected')),
      cancelOnError: true,
    );
    _sendJson(<String, dynamic>{
      'type': 'session.start',
      'token': token,
      if (config.language != 'auto') 'language': config.language,
    });
    return config;
  }

  Future<void> startListening() async {
    if (_socket == null) {
      await connect(onEvent: _onEvent);
    }
    await stopSpeaking();
    if (!await _recorder.hasPermission()) {
      throw ApiException('Microphone permission is required for AI voice.');
    }
    if (await _recorder.isRecording()) return;

    final stream = await _recorder.startStream(
      const RecordConfig(
        encoder: AudioEncoder.pcm16bits,
        sampleRate: 16000,
        numChannels: 1,
      ),
    );
    _recordingSubscription = stream.listen(
      (chunk) {
        if (chunk.isEmpty) return;
        _sendJson(<String, dynamic>{
          'type': 'audio.chunk',
          'audio': base64Encode(chunk),
        });
      },
      onError: (_) => _emit(const AiVoiceGatewayEvent(
        type: 'error',
        message: 'Microphone recording failed. Please try again.',
      )),
      cancelOnError: true,
    );
  }

  Future<void> stopListening() async {
    final isRecording = await _recorder.isRecording();
    await _recordingSubscription?.cancel();
    _recordingSubscription = null;
    if (isRecording) {
      await _recorder.stop();
    }
    _sendJson(<String, dynamic>{'type': 'audio.end'});
  }

  Future<void> stopSpeaking() async {
    await _player.stop();
    _assistantAudio.clear();
  }

  Future<void> disconnect({bool sendEnd = true}) async {
    await _recordingSubscription?.cancel();
    _recordingSubscription = null;
    if (await _recorder.isRecording()) {
      await _recorder.stop();
    }
    if (sendEnd) {
      _sendJson(<String, dynamic>{'type': 'session.end'});
    }
    await _socketSubscription?.cancel();
    _socketSubscription = null;
    await _socket?.close();
    _socket = null;
  }

  void dispose() {
    unawaited(disconnect());
    _player.dispose();
    _recorder.dispose();
  }

  Future<String> _validVoiceToken() async {
    final token = _voiceToken;
    final expiresAt = _voiceTokenExpiresAt;
    if (token != null &&
        token.isNotEmpty &&
        expiresAt != null &&
        DateTime.now()
            .isBefore(expiresAt.subtract(const Duration(seconds: 45)))) {
      return token;
    }

    final response = await _api.post(ApiConstants.aiSessionToken);
    final data = response is Map<String, dynamic>
        ? response['data'] as Map<String, dynamic>?
        : null;
    final issuedToken = data?['voice_token']?.toString() ?? '';
    final expiresIn =
        int.tryParse(data?['expires_in']?.toString() ?? '') ?? 900;
    if (issuedToken.isEmpty || data == null) {
      throw ApiException('Unable to start AI voice assistant.');
    }

    _runtimeConfig = AiVoiceRuntimeConfig.fromJson(data);
    _voiceToken = issuedToken;
    _voiceTokenExpiresAt = DateTime.now().add(Duration(seconds: expiresIn));
    return issuedToken;
  }

  void _handleSocketMessage(dynamic raw) {
    if (raw is! String) return;
    final payload = jsonDecode(raw) as Map<String, dynamic>;
    final type = payload['type']?.toString() ?? '';

    if (type == 'session.started') {
      final audio = payload['audio'];
      if (audio is Map<String, dynamic>) {
        _assistantSampleRate = int.tryParse(
                '${audio['sample_rate'] ?? audio['output_sample_rate'] ?? 16000}') ??
            16000;
      }
    }

    if (type == 'assistant.audio.chunk') {
      final audio = payload['audio']?.toString();
      if (audio != null && audio.isNotEmpty) {
        _assistantAudio.add(base64Decode(audio));
      }
      return;
    }

    if (type == 'assistant.audio.end') {
      unawaited(_playAssistantAudio());
      _emit(AiVoiceGatewayEvent(type: type));
      return;
    }

    _emit(AiVoiceGatewayEvent(
      type: type,
      text: payload['text']?.toString(),
      message: payload['message']?.toString(),
    ));
  }

  Future<void> _playAssistantAudio() async {
    final pcm = _assistantAudio.takeBytes();
    if (pcm.isEmpty) return;
    final wav =
        _wavFromPcm16(pcm, sampleRate: _assistantSampleRate, channels: 1);
    await _player.stop();
    await _player.play(BytesSource(wav));
  }

  void _sendJson(Map<String, dynamic> payload) {
    final socket = _socket;
    if (socket == null) return;
    socket.add(jsonEncode(payload));
  }

  void _emit(AiVoiceGatewayEvent event) => _onEvent?.call(event);

  Uri _voiceWebSocketUri(AiVoiceRuntimeConfig config) {
    final raw = config.gatewayWsUrl.trim().isNotEmpty
        ? config.gatewayWsUrl.trim()
        : config.gatewayUrl.trim().isNotEmpty
            ? config.gatewayUrl.trim()
            : AppConfig.aiVoiceBaseUrl;
    final base = Uri.parse(raw);
    final scheme = base.scheme == 'https'
        ? 'wss'
        : base.scheme == 'http'
            ? 'ws'
            : base.scheme;
    final path = base.path.endsWith('/v1/voice')
        ? base.path
        : '${base.path.replaceFirst(RegExp(r'/+$'), '')}/v1/voice';
    return base.replace(scheme: scheme, path: path);
  }

  Uint8List _wavFromPcm16(
    Uint8List pcm, {
    required int sampleRate,
    required int channels,
  }) {
    const bitsPerSample = 16;
    final byteRate = sampleRate * channels * bitsPerSample ~/ 8;
    final blockAlign = channels * bitsPerSample ~/ 8;
    final dataLength = pcm.length;
    final fileLength = 36 + dataLength;
    final bytes = BytesBuilder(copy: false)
      ..add(ascii.encode('RIFF'))
      ..add(_uint32(fileLength))
      ..add(ascii.encode('WAVE'))
      ..add(ascii.encode('fmt '))
      ..add(_uint32(16))
      ..add(_uint16(1))
      ..add(_uint16(channels))
      ..add(_uint32(sampleRate))
      ..add(_uint32(byteRate))
      ..add(_uint16(blockAlign))
      ..add(_uint16(bitsPerSample))
      ..add(ascii.encode('data'))
      ..add(_uint32(dataLength))
      ..add(pcm);
    return bytes.toBytes();
  }

  List<int> _uint16(int value) => <int>[value & 0xff, (value >> 8) & 0xff];

  List<int> _uint32(int value) => <int>[
        value & 0xff,
        (value >> 8) & 0xff,
        (value >> 16) & 0xff,
        (value >> 24) & 0xff,
      ];
}
