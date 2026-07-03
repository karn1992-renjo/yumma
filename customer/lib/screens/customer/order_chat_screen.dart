import 'dart:async';

import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../models/order.dart';
import '../../providers/order_provider.dart';
import '../../services/api_service.dart';
import '../../services/location_service.dart';
import '../../services/websocket_service.dart';

class OrderChatScreen extends StatefulWidget {
  const OrderChatScreen({
    super.key,
    this.order,
    this.orderId,
  }) : assert(order != null || orderId != null);

  final Order? order;
  final int? orderId;

  @override
  State<OrderChatScreen> createState() => _OrderChatScreenState();
}

class _OrderChatScreenState extends State<OrderChatScreen>
    with SingleTickerProviderStateMixin {
  static const _canvas = Color(0xFFF8F8F8);
  static const _ink = Color(0xFF111827);
  static const _muted = Color(0xFF6B7280);
  static const _line = Color(0xFFE5E7EB);

  final ApiService _api = ApiService();
  final WebSocketService _webSocketService = WebSocketService();
  final LocationService _locationService = LocationService();
  final ImagePicker _imagePicker = ImagePicker();
  final TextEditingController _messageController = TextEditingController();
  final TextEditingController _assistantController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  Order? _resolvedOrder;
  List<Map<String, dynamic>> _messages = const [];
  List<Map<String, String>> _assistantHistory = const [];
  Map<String, dynamic> _participants = const {};
  Map<String, dynamic> _summary = const {};
  String _recipientRole = 'restaurant';
  bool _isLoading = true;
  bool _isSending = false;
  bool _isAnswering = false;
  bool _otherPartyTyping = false;
  Timer? _typingDebounce;

  Color get _primary => AppConfig.primaryColor;
  Color get _secondary => AppConfig.secondaryColor;

  @override
  void initState() {
    super.initState();
    _resolvedOrder = widget.order;
    _recipientRole = _resolvedOrder?.driver != null ? 'driver' : 'restaurant';
    if (_resolvedOrder == null) {
      _loadOrder();
    }
    _loadChat();
    _webSocketService.initOrderChat(
      _orderId,
      onMessage: _handleIncomingEvent,
    );
    _messageController.addListener(_handleTypingInput);
  }

  int get _orderId => widget.order?.id ?? widget.orderId!;

  Future<void> _loadOrder() async {
    try {
      final order = await context.read<OrderProvider>().fetchOrderDetails(
            _orderId,
            notifyLoading: false,
          );
      if (!mounted || order == null) return;
      setState(() {
        _resolvedOrder = order;
        if (_recipientRole == 'restaurant' && order.driver != null) {
          _recipientRole = 'driver';
        }
      });
    } catch (_) {}
  }

  @override
  void dispose() {
    _typingDebounce?.cancel();
    _messageController.removeListener(_handleTypingInput);
    _messageController.dispose();
    _assistantController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadChat() async {
    try {
      final response = await _api.get(ApiConstants.orderChat(_orderId));
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
        _isLoading = false;
      });
      await _markRead();
      _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      setState(() => _isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to load order chat: $e')),
      );
    }
  }

  Future<void> _markRead() async {
    try {
      final response = await _api.post('${ApiConstants.orderChat(_orderId)}/read');
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
    } catch (_) {
      // Ignore read sync failures.
    }
  }

  void _handleIncomingEvent(Map<String, dynamic> payload) {
    if ((payload['order_id']?.toString() ?? '') != _orderId.toString()) {
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

    final nextMessage = payload.map((key, value) => MapEntry(key.toString(), value));
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
        '${ApiConstants.orderChat(_orderId)}/typing',
        data: {
          'recipient_role': _recipientRole,
          'is_typing': isTyping,
        },
      );
    } catch (_) {
      // Ignore typing sync errors.
    }
  }

  Future<void> _sendTextMessage() async {
    final message = _messageController.text.trim();
    if (message.isEmpty || _isSending) return;

    setState(() => _isSending = true);
    try {
      final response = await _api.post(
        ApiConstants.orderChat(_orderId),
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
      if (mounted) {
        setState(() => _isSending = false);
      }
      _sendTyping(false);
    }
  }

  Future<void> _sendImageMessage() async {
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
        ApiConstants.orderChat(_orderId),
        fields: {
          'recipient_role': _recipientRole,
          'message_type': 'image',
          'message': _messageController.text.trim(),
        },
        files: {
          'attachment': image.path,
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
        ApiConstants.orderChat(_orderId),
        data: {
          'recipient_role': _recipientRole,
          'message_type': 'location',
          'message': address?['address'] ?? 'Shared a live location',
          'location_lat': position.latitude,
          'location_lng': position.longitude,
          'location_label': address?['address'] ?? address?['city'] ?? 'Live location',
        },
      );

      final data = Map<String, dynamic>.from(response['data'] as Map);
      if (!mounted) return;
      setState(() {
        _messages = [..._messages, data];
      });
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

  Future<void> _askAssistant() async {
    final question = _assistantController.text.trim();
    if (question.isEmpty || _isAnswering) return;

    setState(() => _isAnswering = true);
    try {
      final response = await _api.post(
        '${ApiConstants.orderChat(_orderId)}/assistant',
        data: {'question': question},
      );
      final data = Map<String, dynamic>.from(response['data'] as Map);
      if (!mounted) return;
      setState(() {
        _assistantHistory = [
          ..._assistantHistory,
          {
            'question': data['question']?.toString() ?? question,
            'answer': data['answer']?.toString() ?? '',
          },
        ];
        _assistantController.clear();
      });
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Assistant unavailable: $e')),
      );
    } finally {
      if (mounted) setState(() => _isAnswering = false);
    }
  }

  Future<void> _openSharedLocation(Map<String, dynamic> message) async {
    final meta = Map<String, dynamic>.from(message['meta'] as Map? ?? const {});
    final lat = meta['location_lat'];
    final lng = meta['location_lng'];
    if (lat == null || lng == null) return;

    final uri = Uri.parse(
      'https://www.google.com/maps/search/?api=1&query=$lat,$lng',
    );
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  Future<void> _openAttachment(Map<String, dynamic> message) async {
    final url = message['attachment_url']?.toString();
    if (url == null || url.isEmpty) return;
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }

  void _applyQuickReply(String text) {
    _messageController.text = text;
    _messageController.selection = TextSelection.collapsed(offset: text.length);
  }

  List<String> _quickReplies() {
    switch (_recipientRole) {
      case 'driver':
        return const [
          'I am at the gate',
          'Please answer the call',
          'Share exact drop point',
        ];
      case 'restaurant':
      default:
        return const [
          'Please make it less spicy',
          'No onion please',
          'Replace unavailable item after calling',
        ];
    }
  }

  String _participantName(String role) {
    final participant = _participants[role];
    if (participant is Map && (participant['name']?.toString().isNotEmpty ?? false)) {
      return participant['name'].toString();
    }
    return role == 'driver' ? 'Delivery partner' : 'Restaurant';
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
    if (raw == null || raw.isEmpty) return '';
    final date = DateTime.tryParse(raw)?.toLocal();
    if (date == null) return '';
    final hour = date.hour > 12 ? date.hour - 12 : (date.hour == 0 ? 12 : date.hour);
    final minute = date.minute.toString().padLeft(2, '0');
    final suffix = date.hour >= 12 ? 'PM' : 'AM';
    return '$hour:$minute $suffix';
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: _canvas,
        appBar: AppBar(
          backgroundColor: Colors.white,
          elevation: 0,
          titleSpacing: 0,
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _participantName(_recipientRole),
                style: const TextStyle(
                  color: _ink,
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                _otherPartyTyping ? 'typing...' : 'Realtime order communication',
                style: const TextStyle(
                  color: _muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Live Chat'),
              Tab(text: 'AI Assistant'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            _buildLiveChatTab(),
            _buildAssistantTab(),
          ],
        ),
      ),
    );
  }

  Widget _buildLiveChatTab() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
          child: Column(
            children: [
              _buildOrderStatusCard(),
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: _line),
                ),
                child: SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'restaurant', label: Text('Restaurant')),
                    ButtonSegment(value: 'driver', label: Text('Driver')),
                  ],
                  selected: {_recipientRole},
                  onSelectionChanged: (selection) async {
                    setState(() {
                      _recipientRole = selection.first;
                      _otherPartyTyping = false;
                    });
                    await _markRead();
                  },
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 42,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemBuilder: (context, index) {
                    final text = _quickReplies()[index];
                    return ActionChip(
                      backgroundColor: Colors.white,
                      side: const BorderSide(color: _line),
                      label: Text(text),
                      onPressed: () => _applyQuickReply(text),
                    );
                  },
                  separatorBuilder: (_, __) => const SizedBox(width: 8),
                  itemCount: _quickReplies().length,
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: _isLoading
              ? const Center(child: CircularProgressIndicator())
              : ListView.builder(
                  controller: _scrollController,
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  itemCount: _messages.length + (_otherPartyTyping ? 1 : 0),
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
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: _line),
                          ),
                          child: const Text(
                            'Typing...',
                            style: TextStyle(
                              color: _muted,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      );
                    }

                    final message = _messages[index];
                    return _buildMessageBubble(message);
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
                color: Colors.white,
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
                    onPressed: _isSending ? null : _sendImageMessage,
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
                        hintText: 'Message ${_participantName(_recipientRole)}',
                        border: InputBorder.none,
                      ),
                    ),
                  ),
                  const SizedBox(width: 6),
                  Container(
                    decoration: BoxDecoration(
                      color: _primary,
                      shape: BoxShape.circle,
                      gradient: LinearGradient(
                        colors: [
                          _primary,
                          Color.lerp(_primary, _secondary, 0.2) ?? _primary,
                        ],
                      ),
                    ),
                    child: IconButton(
                      onPressed: _isSending ? null : _sendTextMessage,
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
    );
  }

  Widget _buildAssistantTab() {
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _assistantChip('What is my order status?'),
                  _assistantChip('Where is the driver now?'),
                  _assistantChip('Restaurant contact details'),
                  _assistantChip('Delivery OTP'),
                ],
              ),
              const SizedBox(height: 16),
              if (_assistantHistory.isEmpty)
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: _line),
                  ),
                  child: const Text(
                    'Ask about order progress, rider assignment, restaurant contact, tracking, or delivery OTP.',
                    style: TextStyle(
                      color: _muted,
                      fontWeight: FontWeight.w600,
                      height: 1.45,
                    ),
                  ),
                )
              else
                ..._assistantHistory.map(
                  (item) => Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(22),
                      border: Border.all(color: _line),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item['question'] ?? '',
                          style: const TextStyle(
                            color: _ink,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          item['answer'] ?? '',
                          style: const TextStyle(
                            color: _muted,
                            fontWeight: FontWeight.w600,
                            height: 1.45,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
            child: Container(
              padding: const EdgeInsets.fromLTRB(14, 8, 8, 8),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(28),
                border: Border.all(color: _line),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _assistantController,
                      minLines: 1,
                      maxLines: 3,
                      decoration: const InputDecoration(
                        hintText: 'Ask AI assistant about this order',
                        border: InputBorder.none,
                      ),
                    ),
                  ),
                  Container(
                    decoration: BoxDecoration(
                      color: _primary.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: IconButton(
                      onPressed: _isAnswering ? null : _askAssistant,
                      icon: _isAnswering
                          ? SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: _primary,
                              ),
                            )
                          : Icon(Icons.auto_awesome_rounded, color: _primary),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildOrderStatusCard() {
    final statusLabel =
        _summary['status_label']?.toString() ??
        _resolvedOrder?.statusText ??
        'Live order communication';
    final restaurantName =
        _summary['restaurant_name']?.toString() ??
        _resolvedOrder?.restaurant?.name ??
        'Restaurant';
    final driverName =
        _summary['driver_name']?.toString() ??
        _resolvedOrder?.driver?.name ??
        'Awaiting driver';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Color.lerp(_primary, Colors.white, 0.2) ?? _primary,
            _primary,
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Order #${_summary['order_number'] ?? _resolvedOrder?.orderNumber ?? _orderId}',
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            statusLabel,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _statusPill('Restaurant', restaurantName),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _statusPill('Driver', driverName),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _statusPill(String label, String value) {
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

  Widget _buildMessageBubble(Map<String, dynamic> message) {
    final senderRole = message['sender_role']?.toString() ?? '';
    final isMine = senderRole == 'customer';
    final isSystem =
        senderRole == 'system' || message['message_type']?.toString() == 'system';
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
            style: const TextStyle(
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
              message['sender_name']?.toString() ??
                  message['sender_role']?.toString() ??
                  'Message',
              style: TextStyle(
                color: isMine ? Colors.white70 : _muted,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            _buildMessageBody(message, isMine),
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

  Widget _buildMessageBody(Map<String, dynamic> message, bool isMine) {
    final type = message['message_type']?.toString() ?? 'text';
    final textColor = isMine ? Colors.white : _ink;
    final mutedColor = isMine ? Colors.white70 : _muted;

    if (type == 'image' && (message['attachment_url']?.toString().isNotEmpty ?? false)) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          GestureDetector(
            onTap: () => _openAttachment(message),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: AppCachedImage(
                imageUrl: message['attachment_url'].toString(),
                width: 180,
                height: 180,
                fit: BoxFit.cover,
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
      final meta = Map<String, dynamic>.from(message['meta'] as Map? ?? const {});
      return InkWell(
        onTap: () => _openSharedLocation(message),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: isMine ? Colors.white.withOpacity(0.14) : const Color(0xFFF3F4F6),
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

  Widget _assistantChip(String text) {
    return ActionChip(
      backgroundColor: Colors.white,
      side: const BorderSide(color: _line),
      label: Text(text),
      onPressed: () {
        _assistantController.text = text;
        _askAssistant();
      },
    );
  }
}
