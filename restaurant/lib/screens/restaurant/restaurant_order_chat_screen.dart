import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../services/api_service.dart';
import '../../services/location_service.dart';
import '../../services/websocket_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../widgets/common/network_image_loader.dart';

class RestaurantOrderChatScreen extends StatefulWidget {
  const RestaurantOrderChatScreen({
    super.key,
    required this.orderId,
  });

  final int orderId;

  @override
  State<RestaurantOrderChatScreen> createState() =>
      _RestaurantOrderChatScreenState();
}

class _RestaurantOrderChatScreenState extends State<RestaurantOrderChatScreen> {
  Color get _ink => foodflow.ink;
  Color get _muted => foodflow.muted;
  Color get _line => foodflow.line;

  final ApiService _api = ApiService();
  final WebSocketService _webSocketService = WebSocketService();
  final LocationService _locationService = LocationService();
  final ImagePicker _imagePicker = ImagePicker();
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  List<Map<String, dynamic>> _messages = const [];
  Map<String, dynamic> _participants = const {};
  Map<String, dynamic> _summary = const {};
  String _recipientRole = 'customer';
  bool _isLoading = true;
  bool _isSending = false;
  bool _otherPartyTyping = false;
  Timer? _typingDebounce;

  Color get _primary => AppConfig.primaryColor;
  Color get _secondary => AppConfig.secondaryColor;

  @override
  void initState() {
    super.initState();
    _loadChat();
    _webSocketService.initOrderChat(
      widget.orderId,
      onMessage: _handleIncomingEvent,
    );
    _messageController.addListener(_handleTypingInput);
  }

  @override
  void dispose() {
    _typingDebounce?.cancel();
    _messageController.removeListener(_handleTypingInput);
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadChat() async {
    try {
      final response =
          await _api.get(ApiConstants.restaurantOrderChat(widget.orderId));
      final data = Map<String, dynamic>.from(response['data'] as Map);
      final messages = (data['messages'] as List? ?? const [])
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();

      if (!mounted) return;
      setState(() {
        _messages = messages;
        _participants = Map<String, dynamic>.from(
          data['participants'] as Map? ?? const {},
        );
        _summary = Map<String, dynamic>.from(
          data['summary'] as Map? ?? const {},
        );
        _recipientRole = (_participants['driver'] as Map?) == null
            ? 'customer'
            : _recipientRole;
        _isLoading = false;
      });
      await _markRead();
      _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to load chat: $e')),
      );
    }
  }

  Future<void> _markRead() async {
    try {
      final response = await _api.post(
        '${ApiConstants.restaurantOrderChat(widget.orderId)}/read',
      );
      final data = response['data'];
      if (data is! Map) return;
      final ids = (data['message_ids'] as List? ?? const [])
          .map((item) => item.toString())
          .toSet();
      final readAt = data['read_at']?.toString();
      if (ids.isEmpty || readAt == null || !mounted) return;

      setState(() {
        _messages = _messages.map((message) {
          if (ids.contains(message['id']?.toString())) {
            return {...message, 'read_at': readAt};
          }
          return message;
        }).toList();
      });
    } catch (_) {}
  }

  void _handleIncomingEvent(Map<String, dynamic> payload) {
    if ((payload['order_id']?.toString() ?? '') != widget.orderId.toString()) {
      return;
    }

    final type = payload['type']?.toString() ?? '';
    if (type == 'order_chat_typing') {
      if (payload['sender_role']?.toString() == _recipientRole && mounted) {
        setState(() => _otherPartyTyping = payload['is_typing'] == true);
      }
      return;
    }

    if (type == 'order_chat_read') {
      final ids = (payload['message_ids'] as List? ?? const [])
          .map((item) => item.toString())
          .toSet();
      final readAt = payload['read_at']?.toString();
      if (!mounted || ids.isEmpty || readAt == null) return;
      setState(() {
        _messages = _messages.map((message) {
          if (ids.contains(message['id']?.toString())) {
            return {...message, 'read_at': readAt};
          }
          return message;
        }).toList();
      });
      return;
    }

    final nextMessage =
        payload.map((key, value) => MapEntry(key.toString(), value));
    if (!mounted) return;
    setState(() {
      final exists = _messages.any(
        (message) => message['id']?.toString() == nextMessage['id']?.toString(),
      );
      if (!exists) {
        _messages = [..._messages, nextMessage];
      }
      _otherPartyTyping = false;
    });
    _scrollToBottom();
    _markRead();
  }

  void _handleTypingInput() {
    _sendTyping(true);
    _typingDebounce?.cancel();
    _typingDebounce = Timer(const Duration(milliseconds: 900), () {
      _sendTyping(false);
    });
  }

  Future<void> _sendTyping(bool isTyping) async {
    try {
      await _api.post(
        '${ApiConstants.restaurantOrderChat(widget.orderId)}/typing',
        data: {
          'recipient_role': _recipientRole,
          'is_typing': isTyping,
        },
      );
    } catch (_) {}
  }

  Future<void> _sendText() async {
    final message = _messageController.text.trim();
    if (message.isEmpty || _isSending) return;

    setState(() => _isSending = true);
    try {
      final response = await _api.post(
        ApiConstants.restaurantOrderChat(widget.orderId),
        data: {
          'message': message,
          'recipient_role': _recipientRole,
          'message_type': 'text',
        },
      );
      final data = Map<String, dynamic>.from(response['data'] as Map);
      if (!mounted) return;
      setState(() {
        _messageController.clear();
        _messages = [..._messages, data];
      });
      _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to send message: $e')),
      );
    } finally {
      if (mounted) setState(() => _isSending = false);
      _sendTyping(false);
    }
  }

  Future<void> _sendImage() async {
    if (_isSending) return;
    final image = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
      maxWidth: 2000,
    );
    if (image == null) return;

    setState(() => _isSending = true);
    try {
      final response = await _api.postMultipart(
        ApiConstants.restaurantOrderChat(widget.orderId),
        fields: {
          'recipient_role': _recipientRole,
          'message_type': 'image',
          'message': _messageController.text.trim(),
        },
        files: {'attachment': image.path},
      );
      final data = Map<String, dynamic>.from(response['data'] as Map);
      if (!mounted) return;
      setState(() {
        _messageController.clear();
        _messages = [..._messages, data];
      });
      _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to share image: $e')),
      );
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _shareLocation() async {
    if (_isSending) return;
    setState(() => _isSending = true);
    try {
      final position = await _locationService.getCurrentLocation();
      if (position == null) {
        throw Exception('Location unavailable right now.');
      }

      final address = await _locationService.getAddressFromLatLng(
        position.latitude,
        position.longitude,
      );

      final response = await _api.post(
        ApiConstants.restaurantOrderChat(widget.orderId),
        data: {
          'recipient_role': _recipientRole,
          'message_type': 'location',
          'message': address?['address'] ?? 'Restaurant location shared',
          'location_lat': position.latitude,
          'location_lng': position.longitude,
          'location_label': address?['address'] ?? 'Restaurant live location',
        },
      );
      final data = Map<String, dynamic>.from(response['data'] as Map);
      if (!mounted) return;
      setState(() => _messages = [..._messages, data]);
      _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to share location: $e')),
      );
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _openAttachment(Map<String, dynamic> message) async {
    final url = message['attachment_url']?.toString();
    if (url == null || url.isEmpty) return;
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }

  Future<void> _openSharedLocation(Map<String, dynamic> message) async {
    final meta = Map<String, dynamic>.from(message['meta'] as Map? ?? const {});
    final lat = meta['location_lat'];
    final lng = meta['location_lng'];
    if (lat == null || lng == null) return;
    final uri =
        Uri.parse('https://www.google.com/maps/search/?api=1&query=$lat,$lng');
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  List<String> _quickReplies() {
    return _recipientRole == 'customer'
        ? const [
            'Your order is being packed',
            'Please keep your phone reachable',
            'We are preparing it fresh',
          ]
        : const [
            'Order is ready for pickup',
            'Use the side entrance',
            'Handover in 2 mins',
          ];
  }

  void _applyQuickReply(String text) {
    _messageController.text = text;
    _messageController.selection = TextSelection.collapsed(offset: text.length);
  }

  String _participantName(String role) {
    final participant = _participants[role];
    if (participant is Map &&
        (participant['name']?.toString().isNotEmpty ?? false)) {
      return participant['name'].toString();
    }
    switch (role) {
      case 'driver':
        return 'Driver';
      case 'customer':
      default:
        return 'Customer';
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent + 120,
        duration: const Duration(milliseconds: 240),
        curve: Curves.easeOut,
      );
    });
  }

  String _formatTime(String? raw) {
    final date = raw == null ? null : DateTime.tryParse(raw)?.toLocal();
    if (date == null) return '';
    final hour =
        date.hour > 12 ? date.hour - 12 : (date.hour == 0 ? 12 : date.hour);
    final minute = date.minute.toString().padLeft(2, '0');
    final suffix = date.hour >= 12 ? 'PM' : 'AM';
    return '$hour:$minute $suffix';
  }

  @override
  Widget build(BuildContext context) {
    final initial = _participantName(_recipientRole).isNotEmpty
        ? _participantName(_recipientRole)[0].toUpperCase()
        : '?';

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        toolbarHeight: 64,
        title: Row(
          children: [
            Stack(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: _primary.withOpacity(0.14),
                    shape: BoxShape.circle,
                  ),
                  child: Text(initial,
                      style: TextStyle(
                          color: _primary,
                          fontWeight: FontWeight.w900,
                          fontSize: 15)),
                ),
                Positioned(
                  right: 0,
                  bottom: 0,
                  child: Container(
                    width: 10,
                    height: 10,
                    decoration: BoxDecoration(
                      color: _otherPartyTyping
                          ? const Color(0xFF16A34A)
                          : foodflow.muted,
                      shape: BoxShape.circle,
                      border: Border.all(color: foodflow.canvas, width: 2),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    _participantName(_recipientRole),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: _ink,
                      fontWeight: FontWeight.w900,
                      fontSize: 16,
                    ),
                  ),
                  Text(
                    _otherPartyTyping ? 'typing…' : 'Order thread',
                    style: TextStyle(
                      color: _muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Stack(
        children: [
          ...AuroraTheme.auroraBlobs(),
          Column(
            children: [
              SizedBox(height: MediaQuery.of(context).padding.top + 68),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                child: Row(
                  children: [
                    Expanded(
                      child: _ContextStrip(
                        summary: _summary,
                        muted: _muted,
                        ink: _ink,
                        line: _line,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.all(3),
                      decoration: BoxDecoration(
                        color: foodflow.surfaceColor,
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(color: _line),
                      ),
                      child: Row(
                        children: ['customer', 'driver'].map((role) {
                          final selected = _recipientRole == role;
                          return GestureDetector(
                            onTap: () => setState(() {
                              _recipientRole = role;
                              _otherPartyTyping = false;
                            }),
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 12, vertical: 7),
                              decoration: BoxDecoration(
                                color:
                                    selected ? _primary : Colors.transparent,
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: Text(
                                role == 'customer' ? 'Cust' : 'Driver',
                                style: TextStyle(
                                  color:
                                      selected ? Colors.white : _muted,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                    ),
                  ],
                ),
              ),
              SizedBox(
                height: 40,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemBuilder: (context, index) {
                    final text = _quickReplies()[index];
                    return ActionChip(
                      backgroundColor: foodflow.surfaceColor,
                      side: BorderSide(color: _line),
                      label: Text(text),
                      labelStyle: TextStyle(
                          color: _ink,
                          fontSize: 12,
                          fontWeight: FontWeight.w700),
                      onPressed: () => _applyQuickReply(text),
                    );
                  },
                  separatorBuilder: (_, __) => const SizedBox(width: 8),
                  itemCount: _quickReplies().length,
                ),
              ),
              const SizedBox(height: 6),
              Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _messages.isEmpty
                    ? Center(
                        child: Container(
                          margin: const EdgeInsets.symmetric(horizontal: 16),
                          padding: const EdgeInsets.all(24),
                          decoration: BoxDecoration(
                            color: foodflow.surfaceColor,
                            borderRadius: BorderRadius.circular(24),
                            border: Border.all(color: _line),
                            boxShadow: [
                              BoxShadow(
                                color: _secondary.withOpacity(0.06),
                                blurRadius: 16,
                                offset: const Offset(0, 8),
                              ),
                            ],
                          ),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.chat_bubble_outline_rounded,
                                size: 42,
                                color: _muted,
                              ),
                              const SizedBox(height: 12),
                              Text(
                                'No messages yet',
                                style: TextStyle(
                                  color: _ink,
                                  fontWeight: FontWeight.w800,
                                  fontSize: 18,
                                ),
                              ),
                              SizedBox(height: 8),
                              Text(
                                'Use this thread for pickup readiness, issue resolution, and delivery coordination.',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: _muted,
                                  fontWeight: FontWeight.w600,
                                  height: 1.45,
                                ),
                              ),
                            ],
                          ),
                        ),
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount:
                            _messages.length + (_otherPartyTyping ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (_otherPartyTyping && index == _messages.length) {
                            return Align(
                              alignment: Alignment.centerLeft,
                              child: Container(
                                margin: const EdgeInsets.only(bottom: 12),
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 16,
                                  vertical: 12,
                                ),
                                decoration: BoxDecoration(
                                  color: foodflow.surfaceColor,
                                  borderRadius: BorderRadius.circular(20),
                                  border: Border.all(color: _line),
                                ),
                                child: Text(
                                  'Typing...',
                                  style: TextStyle(
                                    color: _muted,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            );
                          }

                          return _messageBubble(_messages[index]);
                        },
                      ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
              child: Container(
                padding: const EdgeInsets.fromLTRB(10, 8, 8, 8),
                decoration: BoxDecoration(
                  color: foodflow.surfaceColor,
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(color: _line),
                  boxShadow: [
                    BoxShadow(
                      color: _secondary.withOpacity(0.06),
                      blurRadius: 18,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    IconButton(
                      onPressed: _isSending ? null : _sendImage,
                      icon: const Icon(Icons.attach_file_rounded),
                    ),
                    IconButton(
                      onPressed: _isSending ? null : _shareLocation,
                      icon: const Icon(Icons.location_on_outlined),
                    ),
                    Expanded(
                      child: TextField(
                        controller: _messageController,
                        minLines: 1,
                        maxLines: 4,
                        decoration: InputDecoration(
                          hintText:
                              'Message ${_participantName(_recipientRole)}',
                          border: InputBorder.none,
                        ),
                      ),
                    ),
                    Container(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            _primary,
                            Color.lerp(_primary, _secondary, 0.2) ?? _primary,
                          ],
                        ),
                        shape: BoxShape.circle,
                      ),
                      child: IconButton(
                        onPressed: _isSending ? null : _sendText,
                        color: Colors.white,
                        icon: _isSending
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Icon(Icons.send_rounded),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
        ],
      ),
    );
  }

  Widget _summaryCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color.lerp(_primary, Colors.white, 0.2) ?? _primary,
            _primary,
          ],
        ),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Order #${_summary['order_number'] ?? widget.orderId}',
            style: const TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w700,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _summary['status_label']?.toString() ??
                'Live delivery coordination',
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              fontSize: 22,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                  child:
                      _summaryPill('Customer', _participantName('customer'))),
              const SizedBox(width: 10),
              Expanded(
                  child: _summaryPill('Driver', _participantName('driver'))),
            ],
          ),
        ],
      ),
    );
  }

  Widget _summaryPill(String label, String value) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.14),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _messageBubble(Map<String, dynamic> message) {
    final senderRole = message['sender_role']?.toString() ?? '';
    final isMine = senderRole == 'restaurant';
    final isSystem = senderRole == 'system' ||
        message['message_type']?.toString() == 'system';
    final bubbleColor = isMine ? _primary : Colors.white;

    if (isSystem) {
      return Center(
        child: Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: const Color(0xFFF3F4F6),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            message['message']?.toString() ?? '',
            style: TextStyle(
              color: _muted,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      );
    }

    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(14),
        constraints: const BoxConstraints(maxWidth: 320),
        decoration: BoxDecoration(
          color: bubbleColor,
          borderRadius: BorderRadius.circular(22),
          border: isMine ? null : Border.all(color: _line),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 14,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              message['sender_name']?.toString() ?? senderRole,
              style: TextStyle(
                color: isMine ? Colors.white70 : _muted,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            _messageBody(message, isMine),
            const SizedBox(height: 8),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _formatTime(message['created_at']?.toString()),
                  style: TextStyle(
                    color: isMine ? Colors.white70 : _muted,
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (isMine) ...[
                  const SizedBox(width: 6),
                  Icon(
                    message['read_at'] != null
                        ? Icons.done_all_rounded
                        : Icons.done_rounded,
                    size: 14,
                    color: Colors.white70,
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _messageBody(Map<String, dynamic> message, bool isMine) {
    final type = message['message_type']?.toString() ?? 'text';
    final textColor = isMine ? Colors.white : _ink;
    final mutedColor = isMine ? Colors.white70 : _muted;

    if (type == 'image' &&
        (message['attachment_url']?.toString().isNotEmpty ?? false)) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          GestureDetector(
            onTap: () => _openAttachment(message),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: NetworkImageLoader(
                imageUrl: message['attachment_url'].toString(),
                width: 180,
                height: 180,
                fit: BoxFit.cover,
                errorWidget: Container(
                  width: 180,
                  height: 180,
                  color: _line,
                  child: const Icon(Icons.broken_image_outlined),
                ),
              ),
            ),
          ),
          if ((message['message']?.toString().trim().isNotEmpty ?? false)) ...[
            const SizedBox(height: 10),
            Text(
              message['message'].toString(),
              style: TextStyle(
                color: textColor,
                fontWeight: FontWeight.w600,
                height: 1.4,
              ),
            ),
          ],
        ],
      );
    }

    if (type == 'location') {
      final meta =
          Map<String, dynamic>.from(message['meta'] as Map? ?? const {});
      return InkWell(
        onTap: () => _openSharedLocation(message),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: isMine
                ? Colors.white.withOpacity(0.14)
                : const Color(0xFFF3F4F6),
            borderRadius: BorderRadius.circular(18),
          ),
          child: Row(
            children: [
              Icon(Icons.location_on_rounded, color: textColor),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      meta['location_label']?.toString() ??
                          message['message']?.toString() ??
                          'Shared location',
                      style: TextStyle(
                        color: textColor,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Tap to open in maps',
                      style: TextStyle(
                        color: mutedColor,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Text(
      message['message']?.toString() ?? '',
      style: TextStyle(
        color: textColor,
        fontWeight: FontWeight.w600,
        height: 1.45,
      ),
    );
  }
}

/// Slim single-line order context bar that replaces the tall gradient summary
/// card, so the message list gets the vertical space.
class _ContextStrip extends StatelessWidget {
  const _ContextStrip({
    required this.summary,
    required this.muted,
    required this.ink,
    required this.line,
  });

  final Map<String, dynamic> summary;
  final Color muted;
  final Color ink;
  final Color line;

  @override
  Widget build(BuildContext context) {
    final order = summary['order_number']?.toString();
    final status = summary['status_label']?.toString();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: line),
      ),
      child: Row(
        children: [
          Icon(Icons.receipt_long_rounded, size: 15, color: muted),
          const SizedBox(width: 6),
          Text(
            order != null ? 'Order #$order' : 'Order thread',
            style: TextStyle(
                color: ink, fontSize: 12, fontWeight: FontWeight.w900),
          ),
          if (status != null && status.trim().isNotEmpty) ...[
            const SizedBox(width: 8),
            Container(width: 3, height: 3, decoration: BoxDecoration(
              color: muted, shape: BoxShape.circle)),
            const SizedBox(width: 8),
            Flexible(
              child: Text(
                status,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                    color: muted,
                    fontSize: 11,
                    fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
