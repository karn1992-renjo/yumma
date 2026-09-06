import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../config/api_constants.dart';
import '../../../models/app_branding.dart';
import '../../../services/api_service.dart';
import '../../../services/app_branding_service.dart';
import '../../../services/websocket_service.dart';
import '../../../theme/foodflow_theme.dart';
import '../../../theme/aurora_theme.dart';
import '../../../widgets/aurora/aurora.dart';

class RestaurantHelpSupportScreen extends StatefulWidget {
  const RestaurantHelpSupportScreen({
    Key? key,
    this.openChat = false,
  }) : super(key: key);

  final bool openChat;

  @override
  State<RestaurantHelpSupportScreen> createState() =>
      _RestaurantHelpSupportScreenState();
}

class _RestaurantHelpSupportScreenState
    extends State<RestaurantHelpSupportScreen> {
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

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() => _branding = branding);
  }

  String get _supportPhone => _branding.supportPhone.trim();
  String get _supportEmail => _branding.supportEmail.trim();

  Future<void> _launchUrl(String url) async {
    if (await canLaunchUrl(Uri.parse(url))) {
      await launchUrl(Uri.parse(url));
    }
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
      final response = await _api.post(ApiConstants.supportConversations, data: {});
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
      if (!exists) _messages = [..._messages, payload];
    });
    _scrollToBottom();
  }

  Future<void> _sendMessage([String? presetText, String? category]) async {
    final message = presetText ?? _messageController.text.trim();
    if (message.isEmpty || _isSending || _conversationId == null) {
      if (message.isEmpty && _conversationId != null) {
        _showSnack('Please enter your query');
      }
      return;
    }

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
      _showSnack('Could not send support message: $e');
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
      final response =
          await _api.get(ApiConstants.supportConversation(_conversationId!));
      _applyConversationResponse(response);
    } catch (_) {}
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
        backgroundColor: foodflow.canvas,
        extendBodyBehindAppBar: true,
        appBar: GlassAppBar(
          title: Text('Help & support',
              style: TextStyle(
                  color: foodflow.ink,
                  fontSize: 17,
                  fontWeight: FontWeight.w900)),
          bottom: TabBar(
            onTap: (index) {
              if (index == 1) _startOrLoadConversation();
            },
            indicatorColor: foodflow.orange,
            labelColor: foodflow.ink,
            unselectedLabelColor: foodflow.muted,
            labelStyle: const TextStyle(fontWeight: FontWeight.w800),
            tabs: const [
              Tab(text: 'Guides'),
              Tab(text: 'Live chat'),
            ],
          ),
        ),
        body: Stack(children: [
          ...AuroraTheme.auroraBlobs(),
          Builder(
            builder: (tabContext) => TabBarView(
              children: [
                _buildHelpTab(tabContext),
                _buildChatTab(),
              ],
            ),
          ),
        ]),
      ),
    );
  }

  Widget _buildHelpTab(BuildContext tabContext) {
    final topPad = MediaQuery.of(context).padding.top + 116;
    return ListView(
      padding: EdgeInsets.fromLTRB(16, topPad, 16, 28),
      children: [
        Container(
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            color: foodflow.surfaceColor,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: foodflow.line),
          ),
          child: Row(
            children: [
              _quickContact(
                icon: Icons.call_rounded,
                label: 'Call',
                onTap: () {
                  if (_supportPhone.isEmpty) {
                    _showSnack('Support phone number is not configured yet.');
                    return;
                  }
                  _launchUrl('tel:$_supportPhone');
                },
              ),
              _contactDivider(),
              _quickContact(
                icon: Icons.mail_rounded,
                label: 'Email',
                onTap: () {
                  if (_supportEmail.isEmpty) {
                    _showSnack('Support email is not configured yet.');
                    return;
                  }
                  _launchUrl('mailto:$_supportEmail');
                },
              ),
              _contactDivider(),
              _quickContact(
                icon: Icons.forum_rounded,
                label: 'Chat',
                onTap: () => DefaultTabController.of(tabContext).animateTo(1),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Text(
          _supportPhone.isEmpty && _supportEmail.isEmpty
              ? 'Live chat is bot-assisted and escalates to a human when needed.'
              : '${_supportPhone.isEmpty ? '' : _supportPhone}'
                  '${(_supportPhone.isNotEmpty && _supportEmail.isNotEmpty) ? '  ·  ' : ''}'
                  '${_supportEmail.isEmpty ? '' : _supportEmail}',
          style: TextStyle(color: foodflow.muted, fontSize: 12),
        ),
        const SizedBox(height: 24),
        Text(
          'COMMON QUESTIONS',
          style: TextStyle(
            fontSize: 12,
            letterSpacing: 0.6,
            color: foodflow.muted,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 10),
        _buildFAQItem(
          question: 'How do I update my restaurant details?',
          answer:
              'Go to Store Profile and edit any field, then tap Save changes at the bottom.',
        ),
        _buildFAQItem(
          question: 'How to add bank details for payouts?',
          answer:
              'Open Payout account from your profile and fill in your settlement account. It is encrypted and used only for payouts.',
        ),
        _buildFAQItem(
          question: 'How to change restaurant location?',
          answer:
              'Use the Location screen to update your address and drop the map pin precisely.',
        ),
        _buildFAQItem(
          question: 'Why is a payout still pending?',
          answer:
              'Payouts settle on your cycle after the order completes. Open Payouts to see each statement and its UTR once released.',
        ),
      ],
    );
  }

  Widget _quickContact({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 14),
          child: Column(
            children: [
              Icon(icon, color: foodflow.orange, size: 22),
              const SizedBox(height: 6),
              Text(label,
                  style: TextStyle(
                      color: foodflow.ink,
                      fontSize: 12,
                      fontWeight: FontWeight.w800)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _contactDivider() =>
      Container(width: 1, height: 34, color: foodflow.line);

  Widget _buildChatTab() {
    if (_isLoading && _messages.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    return Column(
      children: [
        if (_conversationId != null) _buildChatStatusStrip(),
        Expanded(
          child: _messages.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(
                      'Send a message to start restaurant support chat.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.grey.shade600),
                    ),
                  ),
                )
              : ListView.builder(
                  controller: _scrollController,
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 20),
                  itemCount: _messages.length + (_stage == 'resolved' ? 1 : 0),
                  itemBuilder: (context, index) {
                    if (index == _messages.length) return _buildCsatCard();
                    return _buildMessageBubble(_messages[index]);
                  },
                ),
        ),
        _buildChatInputBar(),
      ],
    );
  }

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
              isBot ? 'Chatting with the support bot' : 'A support agent has joined',
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
                foregroundColor: FoodFlowTheme.orange,
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
            color: foodflow.surfaceColor,
            borderRadius: BorderRadius.circular(28),
            border: Border.all(color: FoodFlowTheme.line),
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
                  maxLines: 4,
                  enabled: !disabled,
                  decoration: InputDecoration(
                    hintText: disabled
                        ? 'This conversation is resolved'
                        : 'Describe your issue or question...',
                    border: InputBorder.none,
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 6),
              Container(
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: FoodFlowTheme.orange,
                ),
                child: IconButton(
                  onPressed:
                      (_isSending || disabled) ? null : () => _sendMessage(),
                  color: Colors.white,
                  icon: _isSending
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
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
          decoration: BoxDecoration(
            color: foodflow.surfaceColor,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: FoodFlowTheme.line),
          ),
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
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: FoodFlowTheme.line),
      ),
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
                  backgroundColor: FoodFlowTheme.orange,
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
    final isMine = senderType == 'restaurant';
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
      );
    }

    final senderLabel = switch (senderType) {
      'bot' => 'Support Bot',
      'admin' => 'Support Team',
      _ => 'You',
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
                    color: isMine ? FoodFlowTheme.orange : Colors.white,
                    borderRadius: BorderRadius.only(
                      topLeft: const Radius.circular(16),
                      topRight: const Radius.circular(16),
                      bottomLeft: Radius.circular(isMine ? 16 : 4),
                      bottomRight: Radius.circular(isMine ? 4 : 16),
                    ),
                    border: isMine ? null : Border.all(color: Colors.grey.shade200),
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
                  color: foodflow.surfaceColor,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: FoodFlowTheme.line),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: FoodFlowTheme.orange.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(_categoryIcon(code),
                          size: 17, color: FoodFlowTheme.orange),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        map['label']?.toString() ?? '',
                        style: const TextStyle(
                            fontSize: 12.5, fontWeight: FontWeight.w700),
                      ),
                    ),
                    Icon(Icons.chevron_right_rounded,
                        size: 18, color: FoodFlowTheme.faint),
                  ],
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

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
            foregroundColor: FoodFlowTheme.orange,
            side: BorderSide(color: FoodFlowTheme.line),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            shape:
                RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          ),
        );
      }).toList(),
    );
  }

  Widget _buildContactCard({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: foodflow.surfaceColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: FoodFlowTheme.line),
        ),
        child: Row(
          children: [
            Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                color: FoodFlowTheme.orange.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                icon,
                color: FoodFlowTheme.orange,
                size: 24,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey.shade600,
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.arrow_forward_ios,
                size: 16, color: FoodFlowTheme.faint),
          ],
        ),
      ),
    );
  }

  Widget _buildFAQItem({
    required String question,
    required String answer,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: foodflow.line),
      ),
      child: Theme(
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 2),
          childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
          expandedCrossAxisAlignment: CrossAxisAlignment.start,
          iconColor: foodflow.orange,
          collapsedIconColor: foodflow.muted,
          title: Text(
            question,
            style: TextStyle(
              fontSize: 13.5,
              color: foodflow.ink,
              fontWeight: FontWeight.w800,
            ),
          ),
          children: [
            Text(
              answer,
              style: TextStyle(
                fontSize: 12.5,
                height: 1.35,
                color: FoodFlowTheme.muted,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
