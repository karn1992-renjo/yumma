import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../config/api_constants.dart';
import '../../models/app_branding.dart';
import '../../services/api_service.dart';
import '../../services/app_branding_service.dart';
import '../../theme/theme_alt.dart';

class DriverSupportScreen extends StatefulWidget {
  const DriverSupportScreen({
    super.key,
    this.openChat = false,
  });

  final bool openChat;

  @override
  State<DriverSupportScreen> createState() => _DriverSupportScreenState();
}

class _DriverSupportScreenState extends State<DriverSupportScreen> {
  final TextEditingController _messageController = TextEditingController();
  final List<_SupportMessage> _messages = [];
  final ApiService _api = ApiService();
  final ImagePicker _picker = ImagePicker();
  int? _ticketId;
  String? _attachmentPath;
  Timer? _refreshTimer;
  AppBranding _branding = AppBranding.fallback();

  @override
  void initState() {
    super.initState();
    _loadBranding();
    _loadLatestTicket();
    _refreshTimer = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _loadLatestTicket(silent: true),
    );
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _launch(Uri uri, String fallbackMessage) async {
    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(fallbackMessage)),
      );
    }
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() => _branding = branding);
  }

  String get _supportPhone => _branding.supportPhone.trim();
  String get _supportEmail => _branding.supportEmail.trim();

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
    return _launch(
      Uri(
        scheme: 'mailto',
        path: _supportEmail,
        queryParameters: {'subject': 'Driver support request'},
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

  Future<void> _loadLatestTicket({bool silent = false}) async {
    try {
      final response = await _api.get(
        ApiConstants.supportTickets,
        queryParams: {'requester_role': 'driver'},
      );
      final tickets = response['data'];
      if (response['success'] == true && tickets is List && tickets.isNotEmpty) {
        final ticket = tickets.first as Map;
        final replies = ticket['replies'] is List ? ticket['replies'] as List : [];
        final nextMessages = replies.map((reply) {
          final map = reply as Map;
          return _SupportMessage(
            text: map['message']?.toString() ?? '',
            fromUser: map['is_admin_reply'] != true,
          );
        }).toList();

        if (!mounted) return;
        setState(() {
          _ticketId = ticket['id'] is int ? ticket['id'] as int : int.tryParse('${ticket['id']}');
          _messages
            ..clear()
            ..addAll(nextMessages);
        });
      }
    } catch (e) {
      if (!silent) {
        debugPrint('Load driver support chat error: $e');
      }
    }
  }

  Future<void> _sendMessage() async {
    final message = _messageController.text.trim();
    if (message.isEmpty && _attachmentPath == null) return;

    setState(() {
      _messages.add(_SupportMessage(
        text: _composeMessageWithAttachment(message, _attachmentPath),
        fromUser: true,
      ));
      _messageController.clear();
    });

    try {
      final hasAttachment = _attachmentPath != null;
      final response = _ticketId == null
          ? hasAttachment
              ? await _api.postMultipart(
                  ApiConstants.supportTickets,
                  fields: {
                    'subject': 'Driver live chat support',
                    'message': message,
                    'category': 'general_inquiry',
                    'priority': 'medium',
                    'requester_role': 'driver',
                    'target_app': 'driver',
                  },
                  files: {'attachment': _attachmentPath!},
                )
              : await _api.post(ApiConstants.supportTickets, data: {
                  'subject': 'Driver live chat support',
                  'message': message,
                  'category': 'general_inquiry',
                  'priority': 'medium',
                  'requester_role': 'driver',
                  'target_app': 'driver',
                })
          : hasAttachment
              ? await _api.postMultipart(
                  ApiConstants.supportTicketReply(_ticketId!),
                  fields: {
                    'message': message,
                    'requester_role': 'driver',
                    'target_app': 'driver',
                  },
                  files: {'attachment': _attachmentPath!},
                )
              : await _api.post(ApiConstants.supportTicketReply(_ticketId!), data: {
                  'message': message,
                  'requester_role': 'driver',
                  'target_app': 'driver',
                });

      if (response['success'] == true) {
        final ticket = response['data'] as Map?;
        final id = ticket?['id'];
        _ticketId = id is int ? id : int.tryParse('$id');
        await _loadLatestTicket(silent: true);
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not send chat message: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _attachmentPath = null);
      }
    }
  }

  Future<void> _pickAttachment() async {
    final file = await _picker.pickImage(source: ImageSource.gallery);
    if (file == null || !mounted) return;
    setState(() => _attachmentPath = file.path);
  }

  String _composeMessageWithAttachment(String text, String? attachmentPath) {
    final safeText = text.trim();
    if (attachmentPath == null || attachmentPath.trim().isEmpty) {
      return safeText;
    }
    final fileName = attachmentPath.split(RegExp(r'[\\/]')).last;
    if (safeText.isEmpty) return 'Attachment: $fileName';
    return '$safeText\nAttachment: $fileName';
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      initialIndex: widget.openChat ? 1 : 0,
      child: Scaffold(
        backgroundColor: Colors.grey.shade50,
        appBar: AppBar(
          title: const Text('Driver Support'),
          backgroundColor: Colors.white,
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Help'),
              Tab(text: 'Chat'),
            ],
          ),
        ),
        body: Builder(
          builder: (tabContext) => TabBarView(
            children: [
              _buildHelpTab(tabContext),
              _buildChatTab(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHelpTab(BuildContext tabContext) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _buildActionTile(
          icon: Icons.call_outlined,
          title: 'Call Driver Support',
          subtitle: _supportPhone.isEmpty ? 'Not configured' : _supportPhone,
          onTap: _callSupport,
        ),
        _buildActionTile(
          icon: Icons.mail_outline,
          title: 'Email Driver Support',
          subtitle: _supportEmail.isEmpty ? 'Not configured' : _supportEmail,
          onTap: _emailSupport,
        ),
        _buildActionTile(
          icon: Icons.chat_bubble_outline,
          title: 'Start Live Chat',
          subtitle: 'Message dispatch and support inside the app',
          onTap: () => DefaultTabController.of(tabContext).animateTo(1),
        ),
        const SizedBox(height: 18),
        const Text(
          'Common Issues',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 10),
        _buildFaq(
          'I am not receiving orders',
          'Check that you are online, your gig is active, and location permission is enabled. If the problem continues, start a live chat.',
        ),
        _buildFaq(
          'Payout or wallet issue',
          'Share the affected payout date or wallet transaction details in chat so support can verify it quickly.',
        ),
        _buildFaq(
          'Problem at pickup or delivery',
          'Start live chat and include the order number plus what happened so dispatch can assist immediately.',
        ),
      ],
    );
  }

  Widget _buildChatTab() {
    return Column(
      children: [
        Expanded(
          child: _messages.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(
                      'Send a message to start a driver support chat.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.grey.shade600),
                    ),
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _messages.length,
                  itemBuilder: (context, index) {
                    final message = _messages[index];
                    return Align(
                      alignment: message.fromUser
                          ? Alignment.centerRight
                          : Alignment.centerLeft,
                      child: Container(
                        constraints: const BoxConstraints(maxWidth: 280),
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          color: message.fromUser
                              ? const Color(0xFFFC8019)
                              : Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: message.fromUser
                              ? null
                              : Border.all(color: Colors.grey.shade200),
                        ),
                        child: Text(
                          message.text,
                          style: TextStyle(
                            color: message.fromUser
                                ? Colors.white
                                : Colors.black87,
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    );
                  },
                ),
        ),
        SafeArea(
          top: false,
          child: Container(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
            color: Colors.white,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (_attachmentPath != null)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Text(
                        'Attached: ${_attachmentPath!.split(RegExp(r'[\\/]')).last}',
                        style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                      ),
                    ),
                  ),
                Row(
                  children: [
                    IconButton(
                      onPressed: _pickAttachment,
                      icon: const Icon(Icons.attach_file),
                    ),
                    Expanded(
                      child: TextField(
                        controller: _messageController,
                        minLines: 1,
                        maxLines: 3,
                        decoration: const InputDecoration(
                          hintText: 'Type your message',
                          contentPadding:
                              EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    IconButton.filled(
                      onPressed: _sendMessage,
                      icon: const Icon(Icons.send),
                      style: IconButton.styleFrom(
                        backgroundColor: const Color(0xFFFC8019),
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildActionTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: const Color(0xFFFFF3E7),
          child: Icon(icon, color: ThemeAlt.orange),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }

  Widget _buildFaq(String title, String body) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          Text(body, style: TextStyle(color: Colors.grey.shade700)),
        ],
      ),
    );
  }
}

class _SupportMessage {
  final String text;
  final bool fromUser;

  _SupportMessage({
    required this.text,
    required this.fromUser,
  });
}
