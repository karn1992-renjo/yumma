import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../config/api_constants.dart';
import '../../../models/app_branding.dart';
import '../../../services/api_service.dart';
import '../../../services/app_branding_service.dart';
import '../../../theme/foodflow_theme.dart';

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
  final List<_SupportMessage> _messages = [];
  final ApiService _api = ApiService();
  int? _ticketId;
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

  Future<void> _loadLatestTicket({bool silent = false}) async {
    try {
      final response = await _api.get(
        ApiConstants.supportTickets,
        queryParams: {'requester_role': 'restaurant'},
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
        debugPrint('Load restaurant support chat error: $e');
      }
    }
  }

  Future<void> _sendMessage() async {
    final message = _messageController.text.trim();
    if (message.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter your query')),
      );
      return;
    }

    setState(() {
      _messages.add(_SupportMessage(text: message, fromUser: true));
      _messageController.clear();
    });

    try {
      final response = _ticketId == null
          ? await _api.post(ApiConstants.supportTickets, data: {
              'subject': 'Restaurant live chat support',
              'message': message,
              'category': 'general_inquiry',
              'priority': 'medium',
              'requester_role': 'restaurant',
              'target_app': 'restaurant',
            })
          : await _api.post(ApiConstants.supportTicketReply(_ticketId!), data: {
              'message': message,
              'requester_role': 'restaurant',
              'target_app': 'restaurant',
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
        SnackBar(content: Text('Could not send support message: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      initialIndex: widget.openChat ? 1 : 0,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Help & Support'),
          elevation: 0,
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
        const SizedBox(height: 8),
        const Text(
          'Contact Us',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 16),
        _buildContactCard(
          icon: Icons.phone,
          title: 'Call Support',
          subtitle: _supportPhone.isEmpty ? 'Not configured' : _supportPhone,
          onTap: () {
            if (_supportPhone.isEmpty) {
              _showSnack('Support phone number is not configured yet.');
              return;
            }
            _launchUrl('tel:$_supportPhone');
          },
        ),
        const SizedBox(height: 12),
        _buildContactCard(
          icon: Icons.email,
          title: 'Email Support',
          subtitle: _supportEmail.isEmpty ? 'Not configured' : _supportEmail,
          onTap: () {
            if (_supportEmail.isEmpty) {
              _showSnack('Support email is not configured yet.');
              return;
            }
            _launchUrl('mailto:$_supportEmail');
          },
        ),
        const SizedBox(height: 12),
        _buildContactCard(
          icon: Icons.chat,
          title: 'Live Chat',
          subtitle: 'Connected to admin support in real time',
          onTap: () => DefaultTabController.of(tabContext).animateTo(1),
        ),
        const SizedBox(height: 24),
        const Text(
          'Frequently Asked Questions',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 16),
        _buildFAQItem(
          question: 'How do I update my restaurant details?',
          answer:
              'Go to My Profile and tap the edit button to update your restaurant information.',
        ),
        const SizedBox(height: 12),
        _buildFAQItem(
          question: 'How to add bank details for payouts?',
          answer: 'Navigate to Bank Details section and fill in your account information.',
        ),
        const SizedBox(height: 12),
        _buildFAQItem(
          question: 'How to change restaurant location?',
          answer:
              'Use the Location section to update your address and pinpoint on the map.',
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
                      'Send a message to start restaurant support chat.',
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
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: message.fromUser
                              ? FoodFlowTheme.orange
                              : Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: message.fromUser
                              ? null
                              : Border.all(color: Colors.grey.shade200),
                        ),
                        child: Text(
                          message.text,
                          style: TextStyle(
                            color: message.fromUser ? Colors.white : Colors.black87,
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
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _messageController,
                    minLines: 1,
                    maxLines: 4,
                    decoration: const InputDecoration(
                      hintText: 'Describe your issue or question...',
                      contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                IconButton.filled(
                  onPressed: _sendMessage,
                  icon: const Icon(Icons.send),
                  style: IconButton.styleFrom(
                    backgroundColor: FoodFlowTheme.orange,
                    foregroundColor: Colors.white,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
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
          color: Colors.white,
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
            const Icon(Icons.arrow_forward_ios,
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
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            question,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            answer,
            style: TextStyle(
              fontSize: 12,
              color: Colors.grey.shade700,
            ),
          ),
        ],
      ),
    );
  }
}

class _SupportMessage {
  final String text;
  final bool fromUser;

  const _SupportMessage({
    required this.text,
    required this.fromUser,
  });
}
