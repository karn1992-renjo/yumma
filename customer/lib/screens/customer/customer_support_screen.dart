// lib/screens/customer/customer_support_screen.dart
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/api_constants.dart';
import '../../models/app_branding.dart';
import '../../models/order.dart';
import '../../services/api_service.dart';
import '../../services/app_branding_service.dart';
import '../../services/websocket_service.dart';
import '../../theme/foodflow_theme.dart';

class CustomerSupportScreen extends StatefulWidget {
  final Order? order;
  final bool openChat;

  const CustomerSupportScreen({
    super.key,
    this.order,
    this.openChat = false,
  });

  @override
  State<CustomerSupportScreen> createState() => _CustomerSupportScreenState();
}

class _CustomerSupportScreenState extends State<CustomerSupportScreen> {
  final TextEditingController _messageController = TextEditingController();
  final TextEditingController _csatCommentController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final ApiService _api = ApiService();
  final WebSocketService _webSocketService = WebSocketService();

  List<Map<String, dynamic>> _messages = [];
  int? _conversationId;
  String _stage = 'bot';
  bool _isLoading = false;
  bool _isSending = false;
  bool _csatSubmitted = false;
  int _csatRating = 0;
  AppBranding _branding = AppBranding.fallback();

  @override
  void initState() {
    super.initState();
    _loadBranding();
    if (widget.openChat) {
      _startOrLoadConversation();
    }
  }

  @override
  void dispose() {
    if (_conversationId != null) {
      _webSocketService.removeSupportChatHandler(_conversationId!);
    }
    _messageController.dispose();
    _csatCommentController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  String? get _orderContextText {
    final order = widget.order;
    if (order == null) return null;
    return 'Order #${order.orderNumber}';
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() => _branding = branding);
  }

  String get _supportPhone => _branding.supportPhone.trim();
  String get _supportEmail => _branding.supportEmail.trim();

  Future<void> _launch(Uri uri, String fallbackMessage) async {
    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(fallbackMessage)),
      );
    }
  }

  Future<void> _callSupport() {
    if (_supportPhone.isEmpty) {
      _showSnack('Support phone number is not configured yet.');
      return Future.value();
    }
    return _launch(
      Uri(scheme: 'tel', path: _supportPhone),
      'Could not open the phone dialer.',
    );
  }

  Future<void> _emailSupport() {
    if (_supportEmail.isEmpty) {
      _showSnack('Support email is not configured yet.');
      return Future.value();
    }
    final subject = _orderContextText == null
        ? 'FoodFlow support request'
        : 'Support request for $_orderContextText';
    return _launch(
      Uri(
        scheme: 'mailto',
        path: _supportEmail,
        queryParameters: {'subject': subject},
      ),
      'Could not open your email app.',
    );
  }

  void _showSnack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  Future<void> _startOrLoadConversation() async {
    if (_conversationId != null || _isLoading) return;
    setState(() => _isLoading = true);
    try {
      final response = await _api.post(
        ApiConstants.supportConversations,
        data: {'order_id': widget.order?.id},
      );
      _applyConversationResponse(response);
      if (_conversationId != null) {
        _webSocketService.initSupportChat(
          _conversationId!,
          onMessage: _handleIncomingMessage,
        );
      }
    } catch (e) {
      if (!mounted) return;
      _showSnack('Could not start support chat: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _applyConversationResponse(Map<String, dynamic> response) {
    final data = response['data'];
    if (data is! Map) return;
    final messages = (response['messages'] as List? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();

    if (!mounted) return;
    setState(() {
      _conversationId = data['id'] is int ? data['id'] as int : null;
      _stage = data['stage']?.toString() ?? 'bot';
      _messages = messages;
      if (data['csat_rating'] is int && (data['csat_rating'] as int) > 0) {
        _csatSubmitted = true;
      }
    });
    _scrollToBottom();
  }

  void _handleIncomingMessage(Map<String, dynamic> payload) {
    if ((payload['conversation_id']?.toString() ?? '') !=
        _conversationId?.toString()) {
      return;
    }

    if (!mounted) return;
    setState(() {
      final exists = _messages.any(
        (m) => m['id']?.toString() == payload['id']?.toString(),
      );
      if (!exists) {
        _messages = [..._messages, payload];
      }
    });
    _scrollToBottom();
  }

  Future<void> _sendMessage([String? presetText, String? category]) async {
    final message = presetText ?? _messageController.text.trim();
    if (message.isEmpty || _isSending || _conversationId == null) return;

    setState(() => _isSending = true);
    try {
      final response = await _api.post(
        ApiConstants.supportConversationMessages(_conversationId!),
        data: {
          'message': message,
          if (category != null) 'category': category,
        },
      );
      final data = response['data'];
      if (data is Map) {
        final newMessage = Map<String, dynamic>.from(data);
        if (!mounted) return;
        setState(() {
          _messageController.clear();
          final exists = _messages.any(
            (m) => m['id']?.toString() == newMessage['id']?.toString(),
          );
          if (!exists) _messages = [..._messages, newMessage];
        });
        _scrollToBottom();
      }
      await _refreshConversationStage();
    } catch (e) {
      if (!mounted) return;
      _showSnack('Could not send message: $e');
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _escalateToAgent() async {
    if (_conversationId == null || _isSending || _stage != 'bot') return;
    setState(() => _isSending = true);
    try {
      await _api.post(ApiConstants.supportConversationEscalate(_conversationId!));
      await _refreshConversationStage();
    } catch (e) {
      if (!mounted) return;
      _showSnack('Could not connect to an agent: $e');
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  Future<void> _refreshConversationStage() async {
    if (_conversationId == null) return;
    try {
      final response = await _api.get(
        ApiConstants.supportConversation(_conversationId!),
      );
      _applyConversationResponse(response);
    } catch (_) {
      // Ignore; live updates arrive over the socket regardless.
    }
  }

  Future<void> _submitCsat() async {
    if (_conversationId == null || _csatRating == 0) return;
    try {
      await _api.post(
        ApiConstants.supportConversationCsat(_conversationId!),
        data: {
          'rating': _csatRating,
          if (_csatCommentController.text.trim().isNotEmpty)
            'comment': _csatCommentController.text.trim(),
        },
      );
      if (!mounted) return;
      setState(() => _csatSubmitted = true);
    } catch (e) {
      _showSnack('Could not submit rating: $e');
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent + 160,
        duration: const Duration(milliseconds: 240),
        curve: Curves.easeOut,
      );
    });
  }

  static IconData _categoryIcon(String? code) {
    switch (code) {
      case 'order_status':
        return Icons.local_shipping_outlined;
      case 'delivery_delay':
        return Icons.schedule_outlined;
      case 'otp_missing':
        return Icons.password_outlined;
      case 'driver_contact':
        return Icons.phone_in_talk_outlined;
      case 'cancel_order':
        return Icons.cancel_outlined;
      case 'refund_status':
        return Icons.currency_rupee_rounded;
      case 'payment_issue':
        return Icons.payment_outlined;
      case 'wrong_item':
        return Icons.report_gmailerrorred_outlined;
      case 'satisfied':
        return Icons.thumb_up_alt_rounded;
      case 'escalate':
        return Icons.support_agent_rounded;
      default:
        return Icons.help_outline_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      initialIndex: widget.openChat ? 1 : 0,
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: Column(
            children: [
              _buildHeader(),
              _buildTabs(),
              Expanded(
                child: Builder(
                  builder: (tabContext) => TabBarView(
                    children: [
                      _buildHelpTab(tabContext),
                      _buildChatTab(),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return SizedBox(
      height: 68,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 6),
        child: Stack(
          alignment: Alignment.center,
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: IconButton(
                onPressed: () => Navigator.maybePop(context),
                icon: const Icon(Icons.arrow_back_rounded),
                iconSize: 28,
                color: Colors.black,
                tooltip: 'Back',
              ),
            ),
            const Text(
              'Help & Support',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: Colors.black,
                fontSize: 20,
                height: 1.05,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTabs() {
    return Container(
      height: 58,
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: Color(0xFFEDEDED))),
      ),
      child: TabBar(
        indicatorColor: FoodFlowTheme.crimson,
        indicatorWeight: 2.5,
        labelColor: FoodFlowTheme.crimson,
        unselectedLabelColor: const Color(0xFF6B6B73),
        labelStyle: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w900),
        unselectedLabelStyle:
            const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
        onTap: (index) {
          if (index == 1) _startOrLoadConversation();
        },
        tabs: const [
          Tab(text: 'Help'),
          Tab(text: 'Chat'),
        ],
      ),
    );
  }

  BoxDecoration _supportCardDecoration({double radius = 14}) {
    return BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: const Color(0xFFE8E8EE)),
      boxShadow: [
        BoxShadow(
          color: Colors.black.withOpacity(0.055),
          blurRadius: 18,
          offset: const Offset(0, 7),
        ),
      ],
    );
  }

  Widget _buildSupportHeroCard() {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      decoration: _supportCardDecoration(),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: const Color(0xFFFFF1EE),
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.support_agent_rounded,
              color: FoodFlowTheme.crimson,
              size: 26,
            ),
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Support that feels personal',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.black,
                    fontSize: 14.5,
                    height: 1.1,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                SizedBox(height: 6),
                Text(
                  'Call, email, or chat with support without leaving your account area.',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Color(0xFF5E5F66),
                    fontSize: 12.5,
                    height: 1.35,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionLabel(String label) {
    return Text(
      label,
      style: const TextStyle(
        color: Color(0xFF666666),
        fontSize: 11.5,
        letterSpacing: 0.8,
        fontWeight: FontWeight.w800,
      ),
    );
  }

  Widget _buildHelpTab(BuildContext tabContext) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: [
        _buildSupportHeroCard(),
        if (_orderContextText != null) _buildOrderBanner(),
        _buildActionTile(
          icon: Icons.call_outlined,
          title: 'Call Customer Support',
          subtitle: _supportPhone.isEmpty ? 'Not configured' : _supportPhone,
          onTap: _callSupport,
        ),
        _buildActionTile(
          icon: Icons.mail_outline,
          title: 'Email Customer Support',
          subtitle: _supportEmail.isEmpty ? 'Not configured' : _supportEmail,
          onTap: _emailSupport,
        ),
        _buildActionTile(
          icon: Icons.chat_bubble_outline,
          title: 'Start Live Chat',
          subtitle: 'Message support inside the app',
          onTap: () => DefaultTabController.of(tabContext).animateTo(1),
        ),
        const SizedBox(height: 18),
        _sectionLabel('COMMON ISSUES'),
        const SizedBox(height: 10),
        _buildFaq(
          'Where is my order?',
          'Open order tracking to see the latest status. If it has not changed for a while, start a chat and include your order number.',
        ),
        _buildFaq(
          'Delivery OTP is missing',
          'OTP is generated automatically. Keep the tracking screen open for a few seconds or refresh the order.',
        ),
        _buildFaq(
          'Need to cancel or refund',
          'Orders can be cancelled only before preparation starts. Refunds depend on payment status and restaurant acceptance.',
        ),
      ],
    );
  }

  Widget _buildChatTab() {
    if (_isLoading && _messages.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    return Column(
      children: [
        if (_orderContextText != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _buildOrderBanner(),
          ),
        if (_conversationId != null) _buildChatStatusStrip(),
        Expanded(
          child: _messages.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(
                      'Send a message to start a support chat.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                          color: Colors.grey.shade600, fontSize: 12.5),
                    ),
                  ),
                )
              : ListView.builder(
                  controller: _scrollController,
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 20),
                  itemCount: _messages.length + (_stage == 'resolved' ? 1 : 0),
                  itemBuilder: (context, index) {
                    if (index == _messages.length) {
                      return _buildCsatCard();
                    }
                    return _buildMessageBubble(_messages[index]);
                  },
                ),
        ),
        _buildChatInputBar(),
      ],
    );
  }

  /// A persistent strip under the header, mirroring the "Chatting with
  /// Yumma Assistant / Talk to a human" bar Zomato & Swiggy keep pinned
  /// above the message list while a bot is triaging the issue.
  Widget _buildChatStatusStrip() {
    if (_stage == 'resolved') return const SizedBox.shrink();

    final isBot = _stage == 'bot';
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: isBot ? const Color(0xFFF3F0FF) : const Color(0xFFEFFAF1),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(
            isBot ? Icons.smart_toy_outlined : Icons.support_agent_rounded,
            size: 16,
            color: isBot ? const Color(0xFF6D5BD0) : const Color(0xFF0A9443),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              isBot
                  ? 'Chatting with Yumma Assistant'
                  : 'A support agent has joined this chat',
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color: isBot ? const Color(0xFF6D5BD0) : const Color(0xFF0A9443),
              ),
            ),
          ),
          if (isBot)
            TextButton(
              onPressed: _isSending ? null : _escalateToAgent,
              style: TextButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                foregroundColor: FoodFlowTheme.crimson,
              ),
              child: const Text(
                'Talk to an agent',
                style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildChatInputBar() {
    final disabled = _stage == 'resolved';
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 6, 6, 6),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(28),
            border: Border.all(color: const Color(0xFFE8E8EE)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.06),
                blurRadius: 18,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _messageController,
                  minLines: 1,
                  maxLines: 3,
                  enabled: !disabled,
                  decoration: InputDecoration(
                    hintText:
                        disabled ? 'This conversation is resolved' : 'Type your message',
                    border: InputBorder.none,
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 6),
              Container(
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    colors: [
                      FoodFlowTheme.crimson,
                      Color.lerp(FoodFlowTheme.crimson, Colors.black, 0.15) ??
                          FoodFlowTheme.crimson,
                    ],
                  ),
                ),
                child: IconButton(
                  onPressed: (_isSending || disabled) ? null : () => _sendMessage(),
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
                      : const Icon(Icons.send_rounded, size: 20),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCsatCard() {
    if (_csatSubmitted) {
      return Padding(
        padding: const EdgeInsets.only(top: 10),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: _supportCardDecoration(radius: 16),
          child: const Column(
            children: [
              Text('🎉', style: TextStyle(fontSize: 22)),
              SizedBox(height: 6),
              Text(
                'Thanks for rating this conversation!',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5),
              ),
            ],
          ),
        ),
      );
    }

    const emojis = ['😞', '😕', '😐', '🙂', '😍'];
    final headline = _csatRating == 0
        ? 'How was your support experience?'
        : _csatRating <= 2
            ? "Sorry it wasn't great — want to tell us more?"
            : 'Glad we could help!';

    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(18),
      decoration: _supportCardDecoration(radius: 18),
      child: Column(
        children: [
          Text(
            _csatRating == 0 ? '⭐' : emojis[_csatRating - 1],
            style: const TextStyle(fontSize: 34),
          ),
          const SizedBox(height: 8),
          Text(
            headline,
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5),
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (i) {
              final starIndex = i + 1;
              return IconButton(
                onPressed: () => setState(() => _csatRating = starIndex),
                icon: Icon(
                  starIndex <= _csatRating
                      ? Icons.star_rounded
                      : Icons.star_border_rounded,
                  color: Colors.amber,
                  size: 32,
                ),
              );
            }),
          ),
          if (_csatRating > 0) ...[
            TextField(
              controller: _csatCommentController,
              minLines: 1,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: 'Add a comment (optional)',
                filled: true,
                fillColor: const Color(0xFFF7F7F9),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _submitCsat,
                style: ElevatedButton.styleFrom(
                  backgroundColor: FoodFlowTheme.crimson,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Submit Rating',
                    style: TextStyle(fontWeight: FontWeight.w800)),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _avatar({required bool isBot}) {
    return Container(
      width: 28,
      height: 28,
      margin: const EdgeInsets.only(right: 6),
      decoration: BoxDecoration(
        color: isBot ? const Color(0xFFEFEBFF) : const Color(0xFFEFFAF1),
        shape: BoxShape.circle,
      ),
      child: Icon(
        isBot ? Icons.smart_toy_outlined : Icons.support_agent_rounded,
        size: 15,
        color: isBot ? const Color(0xFF6D5BD0) : const Color(0xFF0A9443),
      ),
    );
  }

  String _formatTime(String? raw) {
    final date = raw == null ? null : DateTime.tryParse(raw)?.toLocal();
    if (date == null) return '';
    final hour = date.hour > 12 ? date.hour - 12 : (date.hour == 0 ? 12 : date.hour);
    final minute = date.minute.toString().padLeft(2, '0');
    final suffix = date.hour >= 12 ? 'PM' : 'AM';
    return '$hour:$minute $suffix';
  }

  Widget _buildMessageBubble(Map<String, dynamic> message) {
    final senderType = message['sender_type']?.toString() ?? '';
    final isMine = senderType == 'customer';
    final isBot = senderType == 'bot';
    final isSystem = senderType == 'system' ||
        message['message_type']?.toString() == 'system';
    final quickReplies = (message['meta'] is Map)
        ? (message['meta']['quick_replies'] as List? ?? const [])
        : const [];
    final isIssuePicker = quickReplies.length > 2;

    if (isSystem) {
      return Center(
        child: Container(
          margin: const EdgeInsets.only(bottom: 14),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: const Color(0xFFF3F4F6),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.info_outline_rounded, size: 13, color: Colors.grey.shade500),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  message['message']?.toString() ?? '',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Color(0xFF6B7280),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    final bubbleColor = isMine ? FoodFlowTheme.crimson : Colors.white;
    final senderLabel = switch (senderType) {
      'bot' => 'Yumma Assistant',
      'admin' => 'Support Team',
      _ => message['sender_name']?.toString() ?? 'You',
    };

    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Column(
        crossAxisAlignment:
            isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            mainAxisAlignment:
                isMine ? MainAxisAlignment.end : MainAxisAlignment.start,
            children: [
              if (!isMine) _avatar(isBot: isBot),
              Flexible(
                child: Container(
                  constraints: const BoxConstraints(maxWidth: 260),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: bubbleColor,
                    borderRadius: BorderRadius.only(
                      topLeft: const Radius.circular(16),
                      topRight: const Radius.circular(16),
                      bottomLeft: Radius.circular(isMine ? 16 : 4),
                      bottomRight: Radius.circular(isMine ? 4 : 16),
                    ),
                    border: isMine ? null : Border.all(color: Colors.grey.shade200),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.04),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (!isMine)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 3),
                          child: Text(
                            senderLabel,
                            style: TextStyle(
                              color: Colors.grey.shade600,
                              fontSize: 10.5,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      Text(
                        message['message']?.toString() ?? '',
                        style: TextStyle(
                          color: isMine ? Colors.white : Colors.black87,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          height: 1.3,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          Padding(
            padding: EdgeInsets.only(
              top: 3,
              bottom: 8,
              left: isMine ? 0 : 34,
              right: isMine ? 4 : 0,
            ),
            child: Text(
              _formatTime(message['created_at']?.toString()),
              style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
            ),
          ),
          if (quickReplies.isNotEmpty && _stage != 'resolved')
            Padding(
              padding: EdgeInsets.only(bottom: 12, left: isMine ? 0 : 34),
              child: isIssuePicker
                  ? _buildIssuePickerList(quickReplies)
                  : _buildQuickReplyPills(quickReplies),
            ),
        ],
      ),
    );
  }

  /// Vertical list of tappable issue cards — the Zomato/Swiggy "select your
  /// issue" pattern — used for the initial bot category menu.
  Widget _buildIssuePickerList(List quickReplies) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 280),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: quickReplies.map<Widget>((option) {
          final map = Map<String, dynamic>.from(option as Map);
          final code = map['code']?.toString();
          return Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: _isSending
                  ? null
                  : () => _sendMessage(map['label']?.toString() ?? '', code),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFE8E8EE)),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF1EE),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(_categoryIcon(code),
                          size: 17, color: FoodFlowTheme.crimson),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        map['label']?.toString() ?? '',
                        style: const TextStyle(
                            fontSize: 12.5, fontWeight: FontWeight.w700),
                      ),
                    ),
                    const Icon(Icons.chevron_right_rounded,
                        size: 18, color: Color(0xFF9AA0A6)),
                  ],
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  /// Two-option row (thumbs up / talk to a human) shown after a bot answer.
  Widget _buildQuickReplyPills(List quickReplies) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: quickReplies.map<Widget>((option) {
        final map = Map<String, dynamic>.from(option as Map);
        final code = map['code']?.toString();
        return OutlinedButton.icon(
          onPressed: _isSending
              ? null
              : () => _sendMessage(map['label']?.toString() ?? '', code),
          icon: Icon(_categoryIcon(code), size: 15),
          label: Text(
            map['label']?.toString() ?? '',
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
          ),
          style: OutlinedButton.styleFrom(
            foregroundColor: FoodFlowTheme.crimson,
            side: const BorderSide(color: Color(0xFFFFD2AA)),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            shape:
                RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildOrderBanner() {
    final statusText = widget.order?.statusText ?? '';
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF3E7),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFFFD2AA)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.receipt_long, color: FoodFlowTheme.crimson, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              _orderContextText!,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w900),
            ),
          ),
          if (statusText.isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: FoodFlowTheme.crimson,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                statusText,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildActionTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: _supportCardDecoration(),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: const Color(0xFFFFF1EE),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: FoodFlowTheme.crimson, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.black,
                      fontSize: 14.5,
                      height: 1.1,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Color(0xFF5E5F66),
                      fontSize: 12.5,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(
              Icons.chevron_right_rounded,
              color: Color(0xFF56575E),
              size: 25,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFaq(String title, String body) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: _supportCardDecoration(),
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 16),
        title: Text(
          title,
          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
        children: [
          Text(
            body,
            style: TextStyle(
              color: Colors.grey.shade700,
              fontSize: 13,
              height: 1.35,
            ),
          ),
        ],
      ),
    );
  }
}
