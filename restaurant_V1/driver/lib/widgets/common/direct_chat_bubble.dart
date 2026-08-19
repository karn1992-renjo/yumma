import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/api_service.dart';
import '../../services/sound_service.dart';

class DirectChatBubble extends StatefulWidget {
  const DirectChatBubble({super.key});

  @override
  State<DirectChatBubble> createState() => _DirectChatBubbleState();
}

class _DirectChatBubbleState extends State<DirectChatBubble> {
  final ApiService _api = ApiService();
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _messageController = TextEditingController();

  Timer? _timer;
  bool _authenticated = false;
  bool _open = false;
  bool _showSearch = false;
  bool _loading = false;
  int? _me;
  Map<String, dynamic>? _activeConversation;
  List<Map<String, dynamic>> _conversations = [];
  List<Map<String, dynamic>> _messages = [];
  List<Map<String, dynamic>> _users = [];

  int get _unreadCount => _conversations.fold<int>(
        0,
        (total, item) => total + _asInt(item['unread_count']),
      );

  @override
  void initState() {
    super.initState();
    _bootstrap();
    _timer = Timer.periodic(const Duration(seconds: 5), (_) => _refresh());
  }

  @override
  void dispose() {
    _timer?.cancel();
    _searchController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final token = await _api.getToken();
    if (!mounted || token == null || token.isEmpty) return;
    setState(() => _authenticated = true);
    try {
      final userResponse = await _api.get('/user');
      final user = Map<String, dynamic>.from(userResponse['data'] as Map);
      _me = _asInt(user['id']);
    } catch (_) {}
    await _loadConversations();
  }

  Future<void> _refresh() async {
    if (!_authenticated) return;
    if (_activeConversation != null) {
      await _openConversation(_asInt(_activeConversation!['id']), silent: true);
    } else {
      await _loadConversations();
    }
  }

  Future<void> _loadConversations() async {
    try {
      final previousUnreadCount = _unreadCount;
      final response = await _api.get('/direct-chat/conversations');
      final data = response['data'] as List? ?? const [];
      if (!mounted) return;
      setState(() {
        _conversations =
            data.map((item) => Map<String, dynamic>.from(item as Map)).toList();
      });
      if (_unreadCount > previousUnreadCount) {
        unawaited(SoundService.playMessageSound());
      }
    } catch (_) {}
  }

  Future<void> _searchUsers([String query = '']) async {
    try {
      final response = await _api.get(
        '/direct-chat/users',
        queryParams: {'q': query},
      );
      final data = response['data'] as List? ?? const [];
      if (!mounted) return;
      setState(() {
        _users =
            data.map((item) => Map<String, dynamic>.from(item as Map)).toList();
      });
    } catch (_) {}
  }

  Future<void> _startChat(int userId) async {
    final response = await _api.post(
      '/direct-chat/conversations',
      data: {'user_id': userId},
    );
    final conversation = Map<String, dynamic>.from(response['data'] as Map);
    await _openConversation(_asInt(conversation['id']));
  }

  Future<void> _openConversation(int id, {bool silent = false}) async {
    if (!silent) setState(() => _loading = true);
    try {
      final response = await _api.get('/direct-chat/conversations/$id');
      final messages = response['messages'] as List? ?? const [];
      if (!mounted) return;
      setState(() {
        _activeConversation =
            Map<String, dynamic>.from(response['conversation'] as Map);
        _messages = messages
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
        _showSearch = false;
        _loading = false;
      });
      await _api.post('/direct-chat/conversations/$id/read', data: {});
      await _loadConversations();
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _sendMessage() async {
    final text = _messageController.text.trim();
    final conversationId = _asInt(_activeConversation?['id']);
    if (text.isEmpty || conversationId <= 0) return;
    _messageController.clear();
    await _api.post(
      '/direct-chat/conversations/$conversationId/messages',
      data: {'message': text},
    );
    await _openConversation(conversationId, silent: true);
  }

  int _asInt(dynamic value) {
    if (value is int) return value;
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _initials(String value) {
    final parts = value.trim().split(RegExp(r'\s+')).where((e) => e.isNotEmpty);
    final text = parts.take(2).map((e) => e[0]).join().toUpperCase();
    return text.isEmpty ? '?' : text;
  }

  String _orderLabel(Map<String, dynamic>? conversation) {
    final order = conversation?['order'];
    if (order is! Map) return '';
    final number = order['order_number']?.toString() ?? '';
    return number.isEmpty ? '' : 'Order #$number';
  }

  @override
  Widget build(BuildContext context) {
    if (!_authenticated) return const SizedBox.shrink();

    return Positioned(
      right: 18,
      bottom: 18 + MediaQuery.of(context).padding.bottom,
      child: Material(
        color: Colors.transparent,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            if (_open) _panel(context),
            _fab(),
          ],
        ),
      ),
    );
  }

  Widget _fab() {
    return GestureDetector(
      onTap: () {
        setState(() => _open = !_open);
        if (_open) _loadConversations();
      },
      child: Container(
        width: 58,
        height: 58,
        decoration: BoxDecoration(
          color: const Color(0xFF25D366),
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF25D366).withOpacity(.35),
              blurRadius: 24,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            const Center(
              child: Icon(Icons.chat_rounded, color: Colors.white, size: 28),
            ),
            if (_unreadCount > 0)
              Positioned(
                right: -4,
                top: -4,
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                  decoration: BoxDecoration(
                    color: Colors.red,
                    borderRadius: BorderRadius.circular(99),
                    border: Border.all(color: Colors.white, width: 2),
                  ),
                  child: Text(
                    _unreadCount > 99 ? '99+' : '$_unreadCount',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _panel(BuildContext context) {
    final size = MediaQuery.of(context).size;
    return Container(
      width: size.width > 430 ? 380 : size.width - 36,
      height: size.height > 720 ? 560 : size.height * .72,
      margin: const EdgeInsets.only(bottom: 74),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(.22),
            blurRadius: 36,
            offset: const Offset(0, 18),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: _activeConversation == null ? _home() : _thread(),
    );
  }

  Widget _home() {
    return Column(
      children: [
        _header('Chats', subtitle: 'Direct realtime messages'),
        Row(
          children: [
            Expanded(child: _tab('Chats', !_showSearch, () => setState(() => _showSearch = false))),
            Expanded(child: _tab('New Chat', _showSearch, () {
              setState(() => _showSearch = true);
              _searchUsers(_searchController.text);
            })),
          ],
        ),
        Expanded(child: _showSearch ? _searchPane() : _conversationList()),
      ],
    );
  }

  Widget _header(String title, {String? subtitle}) {
    return Container(
      color: const Color(0xFF075E54),
      padding: const EdgeInsets.fromLTRB(16, 14, 8, 14),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w900)),
                if (subtitle != null)
                  Text(subtitle,
                      style:
                          TextStyle(color: Colors.white.withOpacity(.75))),
              ],
            ),
          ),
          IconButton(
            onPressed: () => setState(() => _open = false),
            icon: const Icon(Icons.close, color: Colors.white),
          ),
        ],
      ),
    );
  }

  Widget _tab(String label, bool active, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 11),
        decoration: BoxDecoration(
          border: Border(
            bottom: BorderSide(
              color: active ? const Color(0xFF25D366) : Colors.grey.shade200,
              width: active ? 3 : 1,
            ),
          ),
        ),
        child: Text(label,
            textAlign: TextAlign.center,
            style: TextStyle(
                color: active ? const Color(0xFF075E54) : Colors.grey,
                fontWeight: FontWeight.w900)),
      ),
    );
  }

  Widget _conversationList() {
    if (_conversations.isEmpty) {
      return const Center(child: Text('No chats yet. Start a new chat.'));
    }
    return ListView.builder(
      itemCount: _conversations.length,
      itemBuilder: (_, index) {
        final item = _conversations[index];
        final title = item['title']?.toString() ?? 'Chat';
        final orderLabel = _orderLabel(item);
        final preview = item['last_message']?['message']?.toString() ?? '';
        return _chatRow(
          title: title,
          subtitle: orderLabel.isEmpty || title == orderLabel ? preview : '$orderLabel · $preview',
          unread: _asInt(item['unread_count']),
          onTap: () => _openConversation(_asInt(item['id'])),
        );
      },
    );
  }

  Widget _searchPane() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: TextField(
            controller: _searchController,
            onChanged: _searchUsers,
            decoration: InputDecoration(
              hintText: 'Search users',
              prefixIcon: const Icon(Icons.search),
              border:
                  OutlineInputBorder(borderRadius: BorderRadius.circular(24)),
              isDense: true,
            ),
          ),
        ),
        Expanded(
          child: ListView.builder(
            itemCount: _users.length,
            itemBuilder: (_, index) {
              final user = _users[index];
              return _chatRow(
                title: user['name']?.toString() ?? 'User',
                subtitle: user['email']?.toString() ??
                    user['phone']?.toString() ??
                    '',
                onTap: () => _startChat(_asInt(user['id'])),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _chatRow({
    required String title,
    String subtitle = '',
    int unread = 0,
    required VoidCallback onTap,
  }) {
    return ListTile(
      onTap: onTap,
      leading: CircleAvatar(
        backgroundColor: const Color(0xFFE1F7E8),
        child: Text(_initials(title),
            style: const TextStyle(
                color: Color(0xFF075E54), fontWeight: FontWeight.w900)),
      ),
      title: Text(title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontWeight: FontWeight.w800)),
      subtitle:
          Text(subtitle, maxLines: 1, overflow: TextOverflow.ellipsis),
      trailing: unread > 0
          ? CircleAvatar(
              radius: 12,
              backgroundColor: const Color(0xFF25D366),
              child: Text(unread > 99 ? '99+' : '$unread',
                  style: const TextStyle(color: Colors.white, fontSize: 10)),
            )
          : null,
    );
  }

  Widget _thread() {
    final title = _activeConversation?['title']?.toString() ?? 'Chat';
    final orderLabel = _orderLabel(_activeConversation);
    final headerTitle = orderLabel.isEmpty || title == orderLabel ? title : '$title · $orderLabel';
    return Column(
      children: [
        Container(
          color: const Color(0xFF075E54),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
          child: Row(
            children: [
              IconButton(
                onPressed: () => setState(() => _activeConversation = null),
                icon: const Icon(Icons.arrow_back, color: Colors.white),
              ),
              CircleAvatar(child: Text(_initials(title))),
              const SizedBox(width: 10),
              Expanded(
                child: Text(headerTitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        color: Colors.white, fontWeight: FontWeight.w900)),
              ),
            ],
          ),
        ),
        Expanded(
          child: Container(
            color: const Color(0xFFEFE7DD),
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: _messages.length,
                    itemBuilder: (_, index) {
                      final message = _messages[index];
                      final mine = _asInt(message['sender_id']) == _me;
                      return Align(
                        alignment: mine
                            ? Alignment.centerRight
                            : Alignment.centerLeft,
                        child: Container(
                          constraints: const BoxConstraints(maxWidth: 270),
                          margin: const EdgeInsets.symmetric(vertical: 4),
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: mine
                                ? const Color(0xFFDCF8C6)
                                : Colors.white,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(message['message']?.toString() ?? ''),
                        ),
                      );
                    },
                  ),
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(8),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _messageController,
                    decoration: InputDecoration(
                      hintText: 'Message',
                      border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(24)),
                      isDense: true,
                    ),
                    onSubmitted: (_) => _sendMessage(),
                  ),
                ),
                const SizedBox(width: 8),
                CircleAvatar(
                  backgroundColor: const Color(0xFF25D366),
                  child: IconButton(
                    onPressed: _sendMessage,
                    icon: const Icon(Icons.send, color: Colors.white),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
