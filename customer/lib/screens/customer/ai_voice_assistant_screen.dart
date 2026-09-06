import 'dart:async';

import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../services/ai_voice_assistant_service.dart';
import '../../theme/foodflow_theme.dart';

class AiVoiceAssistantScreen extends StatefulWidget {
  const AiVoiceAssistantScreen({super.key});

  @override
  State<AiVoiceAssistantScreen> createState() => _AiVoiceAssistantScreenState();
}

class _AiVoiceAssistantScreenState extends State<AiVoiceAssistantScreen> {
  final AiVoiceAssistantService _assistant = AiVoiceAssistantService();
  final ScrollController _scrollController = ScrollController();
  final List<_AiVoiceMessage> _messages = <_AiVoiceMessage>[
    const _AiVoiceMessage(
      role: _AiVoiceRole.assistant,
      text: 'Hi, main Yumma! AI assistant hoon. Boliye, kya order karna hai?',
    ),
  ];

  bool _isConnecting = true;
  bool _isListening = false;
  bool _isThinking = false;
  bool _isSpeaking = false;
  String _statusText = 'Connecting...';
  String _assistantDraft = '';
  AiVoiceRuntimeConfig? _config;
  Timer? _responseTimeout;

  @override
  void initState() {
    super.initState();
    unawaited(_connect());
  }

  @override
  void dispose() {
    _responseTimeout?.cancel();
    _assistant.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _connect() async {
    setState(() {
      _isConnecting = true;
      _statusText = 'Connecting...';
    });
    try {
      final config = await _assistant.connect(onEvent: _handleGatewayEvent);
      if (!mounted) return;
      setState(() {
        _config = config;
        _isConnecting = false;
        _statusText =
            config.enabled ? 'Tap mic and speak' : 'Voice AI unavailable';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isConnecting = false;
        _statusText = 'AI voice is unavailable';
      });
      _addAssistantMessage(
          'AI assistant abhi connect nahi ho pa raha. Thodi der mein try karein.');
    }
  }

  Future<void> _toggleListening() async {
    if (_isConnecting) return;
    if (_isListening) {
      await _assistant.stopListening();
      _armResponseTimeout();
      if (!mounted) return;
      setState(() {
        _isListening = false;
        _isThinking = true;
        _statusText = 'Thinking...';
      });
      return;
    }

    if (!await _requestMicrophonePermission()) return;
    try {
      await _assistant.startListening();
      _cancelResponseTimeout();
      if (!mounted) return;
      setState(() {
        _isListening = true;
        _isThinking = false;
        _isSpeaking = false;
        _assistantDraft = '';
        _statusText = 'Listening...';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _statusText = 'Unable to start microphone');
    }
  }

  Future<bool> _requestMicrophonePermission() async {
    var status = await Permission.microphone.status;
    if (!status.isGranted) {
      status = await Permission.microphone.request();
    }
    if (status.isGranted) return true;
    if (!mounted) return false;
    if (status.isPermanentlyDenied || status.isRestricted) {
      _showPermissionSheet();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Microphone permission is required for AI voice.')),
      );
    }
    return false;
  }

  void _handleGatewayEvent(AiVoiceGatewayEvent event) {
    if (!mounted) return;
    switch (event.type) {
      case 'session.started':
      case 'provider.ready':
        setState(() => _statusText = 'Tap mic and speak');
        break;
      case 'speech.start':
        setState(() => _statusText = 'Listening...');
        break;
      case 'transcript.final':
        final text = event.text?.trim();
        if (text != null && text.isNotEmpty) {
          _armResponseTimeout();
          setState(() {
            _messages.add(_AiVoiceMessage(role: _AiVoiceRole.user, text: text));
            _isListening = false;
            _isThinking = true;
            _statusText = 'Thinking...';
          });
          _scrollToBottom();
        }
        break;
      case 'assistant.text.delta':
        final text = event.text?.trim();
        if (text == null || text.isEmpty) return;
        _cancelResponseTimeout();
        setState(() {
          _isThinking = false;
          _isSpeaking = true;
          _statusText = 'Speaking...';
          if (_assistantDraft.isEmpty) {
            _assistantDraft = text;
            _messages
                .add(_AiVoiceMessage(role: _AiVoiceRole.assistant, text: text));
          } else {
            _assistantDraft = '$_assistantDraft $text'.trim();
            final last = _messages.length - 1;
            if (last >= 0 && _messages[last].role == _AiVoiceRole.assistant) {
              _messages[last] = _AiVoiceMessage(
                  role: _AiVoiceRole.assistant, text: _assistantDraft);
            }
          }
        });
        _scrollToBottom();
        break;
      case 'assistant.audio.end':
        _cancelResponseTimeout();
        setState(() {
          _isSpeaking = false;
          _isThinking = false;
          _assistantDraft = '';
          _statusText = 'Tap mic and speak';
        });
        break;
      case 'assistant.interrupted':
        setState(() {
          _isSpeaking = false;
          _statusText = 'Listening...';
        });
        break;
      case 'error':
        _cancelResponseTimeout();
        setState(() {
          _isListening = false;
          _isThinking = false;
          _isSpeaking = false;
          _statusText = event.message ?? 'AI voice error';
        });
        _addAssistantMessage(
            event.message ?? 'AI assistant abhi connect nahi ho pa raha.');
        break;
      case 'disconnected':
        _cancelResponseTimeout();
        setState(() {
          _isListening = false;
          _isThinking = false;
          _isSpeaking = false;
          _statusText = 'Disconnected';
        });
        break;
    }
  }

  void _armResponseTimeout() {
    _responseTimeout?.cancel();
    _responseTimeout = Timer(const Duration(seconds: 45), () {
      if (!mounted || (!_isThinking && !_isListening)) return;
      setState(() {
        _isListening = false;
        _isThinking = false;
        _isSpeaking = false;
        _assistantDraft = '';
        _statusText = 'Tap mic and speak';
      });
      _addAssistantMessage(
          'AI voice did not receive a reply in time. Please try again.');
      unawaited(_assistant.disconnect(sendEnd: false).then((_) {
        if (mounted) return _connect();
      }));
    });
  }

  void _cancelResponseTimeout() {
    _responseTimeout?.cancel();
    _responseTimeout = null;
  }

  void _addAssistantMessage(String text) {
    setState(() => _messages
        .add(_AiVoiceMessage(role: _AiVoiceRole.assistant, text: text)));
    _scrollToBottom();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOutCubic,
      );
    });
  }

  void _showPermissionSheet() {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const Text('Allow microphone access',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              const Text(
                  'Turn on microphone permission in app settings to use Yumma! AI voice.'),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () {
                  Navigator.pop(context);
                  unawaited(openAppSettings());
                },
                child: const Text('Open Settings'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final remaining = _config?.remainingSeconds;
    final micDisabled =
        _isConnecting || _statusText == 'AI voice is unavailable';

    return Scaffold(
      backgroundColor: const Color(0xFFFAFAFA),
      appBar: AppBar(
        title: const Text('Yumma! AI Voice'),
        centerTitle: true,
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.white,
      ),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Expanded(
              child: ListView.separated(
                controller: _scrollController,
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                itemBuilder: (context, index) =>
                    _AiVoiceBubble(message: _messages[index]),
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemCount: _messages.length,
              ),
            ),
            if (remaining != null && remaining < 1000000)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  'Voice balance: ${remaining ~/ 60}m ${remaining % 60}s',
                  style: const TextStyle(
                      color: FoodFlowTheme.muted, fontWeight: FontWeight.w700),
                ),
              ),
            Text(_statusText,
                style: TextStyle(color: primary, fontWeight: FontWeight.w900)),
            const SizedBox(height: 12),
            GestureDetector(
              onTap: micDisabled ? null : _toggleListening,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                width: _isListening ? 92 : 82,
                height: _isListening ? 92 : 82,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    colors: micDisabled
                        ? <Color>[Colors.grey.shade400, Colors.grey.shade500]
                        : <Color>[primary, const Color(0xFFFF7A00)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: primary.withOpacity(_isListening ? 0.32 : 0.18),
                      blurRadius: _isListening ? 28 : 18,
                      offset: const Offset(0, 12),
                    ),
                  ],
                ),
                child: Icon(
                  _isConnecting
                      ? Icons.more_horiz_rounded
                      : _isListening
                          ? Icons.stop_rounded
                          : _isSpeaking
                              ? Icons.graphic_eq_rounded
                              : Icons.mic_rounded,
                  color: Colors.white,
                  size: 38,
                ),
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}

enum _AiVoiceRole { user, assistant }

class _AiVoiceMessage {
  const _AiVoiceMessage({required this.role, required this.text});

  final _AiVoiceRole role;
  final String text;
}

class _AiVoiceBubble extends StatelessWidget {
  const _AiVoiceBubble({required this.message});

  final _AiVoiceMessage message;

  @override
  Widget build(BuildContext context) {
    final isUser = message.role == _AiVoiceRole.user;
    final primary = Theme.of(context).colorScheme.primary;
    return Align(
      alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints:
            BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.78),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: isUser ? primary : Colors.white,
            borderRadius: BorderRadius.circular(18),
            boxShadow: isUser
                ? null
                : <BoxShadow>[
                    BoxShadow(
                      color: Colors.black.withOpacity(0.06),
                      blurRadius: 12,
                      offset: const Offset(0, 6),
                    ),
                  ],
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
            child: Text(
              message.text,
              style: TextStyle(
                color: isUser ? Colors.white : FoodFlowTheme.ink,
                fontSize: 14.5,
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
