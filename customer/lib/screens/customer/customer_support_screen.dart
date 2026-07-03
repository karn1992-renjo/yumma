// lib/screens/customer/customer_support_screen.dart
import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/api_constants.dart';
import '../../models/app_branding.dart';
import '../../models/order.dart';
import '../../services/api_service.dart';
import '../../services/app_branding_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/customer/account_chrome.dart';

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
  final List<_SupportMessage> _messages = [];
  final ApiService _api = ApiService();
  int? _ticketId;
  String? _supportChoice;
  bool _awaitingSatisfaction = false;
  bool _showEscalationChoices = false;
  Timer? _refreshTimer;
  AppBranding _branding = AppBranding.fallback();

  @override
  void initState() {
    super.initState();
    if (widget.openChat) {
      _messages.add(
        _SupportMessage(
          text: _orderContextText == null
              ? 'Hi! Ask me your question and I’ll try to help right away.'
              : 'Hi! Ask me anything about $_orderContextText and I’ll try to help right away.',
          fromUser: false,
        ),
      );
    }
    _loadLocalConversation();
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

  String? get _orderContextText {
    final order = widget.order;
    if (order == null) return null;
    return 'Order #${order.orderNumber}';
  }

  String get _localConversationKey => widget.order == null
      ? 'support_local_chat_general'
      : 'support_local_chat_order_${widget.order!.id}';

  Future<void> _loadLocalConversation() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_localConversationKey);
    if (raw == null || raw.isEmpty || !mounted) return;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return;
      final storedMessages = (decoded['messages'] as List? ?? const [])
          .whereType<Map>()
          .map(
            (item) => _SupportMessage(
              text: item['text']?.toString() ?? '',
              fromUser: item['from_user'] == true,
            ),
          )
          .where((message) => message.text.isNotEmpty)
          .toList();
      setState(() {
        if (storedMessages.isNotEmpty) {
          _messages
            ..clear()
            ..addAll(storedMessages);
        }
        _awaitingSatisfaction = decoded['awaiting_satisfaction'] == true;
        _showEscalationChoices = decoded['show_escalation'] == true;
        final choice = decoded['support_choice']?.toString();
        if (choice == 'agent' || choice == 'ticket') {
          _supportChoice = choice;
        }
      });
    } catch (_) {
      // Ignore invalid local chat state.
    }
  }

  Future<void> _saveLocalConversation() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _localConversationKey,
      jsonEncode({
        'messages': _messages
            .map((message) => {
                  'text': message.text,
                  'from_user': message.fromUser,
                })
            .toList(),
        'awaiting_satisfaction': _awaitingSatisfaction,
        'show_escalation': _showEscalationChoices,
        'support_choice': _supportChoice,
      }),
    );
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

  Future<void> _loadLatestTicket({bool silent = false}) async {
    try {
      final response = await _api.get(
        ApiConstants.supportTickets,
        queryParams: {'requester_role': 'customer'},
      );
      final tickets = response['data'];
      if (response['success'] == true && tickets is List && tickets.isNotEmpty) {
        Map? ticket;
        if (widget.order == null) {
          ticket = tickets.first as Map;
        } else {
          for (final candidate in tickets.whereType<Map>()) {
            final subject = candidate['subject']?.toString() ?? '';
            if (subject.contains(widget.order!.orderNumber)) {
              ticket = candidate;
              break;
            }
          }
        }
        if (ticket == null) return;
        final Map selectedTicket = ticket;
        final replies = selectedTicket['replies'] is List
            ? selectedTicket['replies'] as List
            : [];
        final nextMessages = replies.map((reply) {
          final map = reply as Map;
          return _SupportMessage(
            text: map['message']?.toString() ?? '',
            fromUser: map['is_admin_reply'] != true,
          );
        }).toList();

        if (!mounted) return;
        setState(() {
          _ticketId = selectedTicket['id'] is int
              ? selectedTicket['id'] as int
              : int.tryParse('${selectedTicket['id']}');
          _supportChoice ??=
              selectedTicket['category']?.toString() == 'live_chat'
              ? 'agent'
              : 'ticket';
          _messages
            ..clear()
            ..addAll(nextMessages);
        });
      }
    } catch (e) {
      if (!silent) {
        debugPrint('Load support chat error: $e');
      }
    }
  }

  Future<void> _sendMessage() async {
    final message = _messageController.text.trim();
    if (message.isEmpty) return;

    if (_ticketId == null && _supportChoice == null) {
      setState(() {
        _messages.add(_SupportMessage(text: message, fromUser: true));
        _messages.add(
          _SupportMessage(text: _localAutoReply(message), fromUser: false),
        );
        _messages.add(
          const _SupportMessage(
            text: 'Are you satisfied with this answer?',
            fromUser: false,
          ),
        );
        _messageController.clear();
        _awaitingSatisfaction = true;
        _showEscalationChoices = false;
      });
      await _saveLocalConversation();
      return;
    }

    setState(() {
      _messages.add(_SupportMessage(text: message, fromUser: true));
      _messageController.clear();
    });
    await _saveLocalConversation();

    try {
      final subject = _orderContextText == null
          ? 'Live chat support'
          : 'Support for $_orderContextText';
      final response = _ticketId == null
          ? await _api.post(ApiConstants.supportTickets, data: {
              'subject': subject,
              'message': message,
              'category': _supportChoice == 'agent' ? 'live_chat' : 'order_issue',
              'priority': _supportChoice == 'agent' ? 'high' : 'medium',
              'requester_role': 'customer',
              'target_app': 'customer',
            })
          : await _api.post(ApiConstants.supportTicketReply(_ticketId!), data: {
              'message': message,
              'requester_role': 'customer',
              'target_app': 'customer',
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
    }
  }

  String _localAutoReply(String question) {
    final text = question.toLowerCase();
    final order = widget.order;
    if (text.contains('status') ||
        text.contains('where') ||
        text.contains('track')) {
      return order == null
          ? 'Open Orders and select Track Order to see the latest status.'
          : '${_orderContextText!} is currently ${order.statusText}. You can follow live progress on the tracking screen.';
    }
    if (text.contains('cancel')) {
      return order?.canCancel == true
          ? 'You can cancel this order from the tracking screen before the cancellation timer ends.'
          : 'This order cannot be cancelled automatically now. If you still need help, choose No below to contact support.';
    }
    if (text.contains('refund')) {
      return 'Refunds are processed according to the payment method and active refund policy. You can check the refund status in Order Details.';
    }
    if (text.contains('payment') || text.contains('paid')) {
      return order == null
          ? 'Payment status is available inside Order Details.'
          : 'The payment status for this order is ${order.paymentStatus}.';
    }
    if (text.contains('otp')) {
      return order?.deliveryOtp?.isNotEmpty == true
          ? 'Your delivery OTP is ${order!.deliveryOtp}.'
          : 'The delivery OTP appears in tracking when the order is close to delivery.';
    }
    if (text.contains('driver') || text.contains('rider')) {
      return order?.driver == null
          ? 'A delivery partner has not been assigned yet.'
          : 'Your delivery partner is ${order!.driver!.name}.';
    }
    if (text.contains('restaurant')) {
      return order?.restaurant == null
          ? 'Restaurant details are available in your order summary.'
          : 'This order is with ${order!.restaurant!.name}.';
    }
    return 'I can help with order status, cancellation, refunds, payment, delivery OTP, restaurant details, and driver assignment.';
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      initialIndex: widget.openChat ? 1 : 0,
      child: Scaffold(
        backgroundColor: accountCanvas,
        appBar: AppBar(
          title: const Text('Help & Support'),
          backgroundColor: accountCanvas,
          foregroundColor: FoodFlowTheme.ink,
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
        const AccountHeroCard(
          title: 'Support that feels personal',
          subtitle:
              'Call, email, or chat with support without leaving your account area.',
          icon: Icons.support_agent_rounded,
          badge: 'PROFILE SPACE',
          margin: EdgeInsets.fromLTRB(0, 0, 0, 16),
        ),
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
        const AccountSectionTitle(title: 'COMMON ISSUES'),
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
    return Column(
      children: [
        if (_orderContextText != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _buildOrderBanner(),
          ),
        if (_ticketId == null && _awaitingSatisfaction)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _buildSatisfactionChoice(),
          ),
        if (_ticketId == null && _showEscalationChoices)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _buildSupportChoice(),
          ),
        Expanded(
          child: _messages.isEmpty
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(
                      'Send a message to start a support chat.',
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
                              ? FoodFlowTheme.crimson
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
            child: Row(
              children: [
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
                    backgroundColor: FoodFlowTheme.crimson,
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

  Widget _buildSatisfactionChoice() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: FoodFlowTheme.elevatedCard(radius: 20),
      child: Row(
        children: [
          const Expanded(
            child: Text(
              'Was this helpful?',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
          TextButton.icon(
            onPressed: () => _answerSatisfaction(true),
            icon: const Icon(Icons.thumb_up_alt_outlined, size: 18),
            label: const Text('Yes'),
          ),
          TextButton.icon(
            onPressed: () => _answerSatisfaction(false),
            icon: const Icon(Icons.thumb_down_alt_outlined, size: 18),
            label: const Text('No'),
          ),
        ],
      ),
    );
  }

  Future<void> _answerSatisfaction(bool satisfied) async {
    setState(() {
      _awaitingSatisfaction = false;
      _showEscalationChoices = !satisfied;
      _messages.add(
        _SupportMessage(
          text: satisfied
              ? 'Glad I could help. You can ask another question anytime.'
              : 'No problem. Choose whether to talk with an agent or create a support ticket.',
          fromUser: false,
        ),
      );
    });
    await _saveLocalConversation();
  }

  Widget _buildSupportChoice() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: FoodFlowTheme.elevatedCard(radius: 22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Choose how you want help',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _supportChoiceButton(
                  icon: Icons.support_agent_rounded,
                  label: 'Talk with agent',
                  onTap: () => _chooseSupport('agent'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _supportChoiceButton(
                  icon: Icons.confirmation_number_outlined,
                  label: 'Create ticket',
                  onTap: () => _chooseSupport('ticket'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _supportChoiceButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return OutlinedButton.icon(
      onPressed: onTap,
      icon: Icon(icon, size: 20),
      label: Text(label, textAlign: TextAlign.center),
      style: OutlinedButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 14),
        foregroundColor: FoodFlowTheme.crimson,
        side: const BorderSide(color: Color(0xFFFFD2AA)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
    );
  }

  void _chooseSupport(String choice) {
    setState(() {
      _supportChoice = choice;
      _awaitingSatisfaction = false;
      _showEscalationChoices = false;
      _messages.add(
        _SupportMessage(
          text: choice == 'agent'
              ? 'Please describe the issue. A support agent will join this chat.'
              : 'Please describe the issue to create a support ticket for this order.',
          fromUser: false,
        ),
      );
    });
    _saveLocalConversation();
  }

  Widget _buildOrderBanner() {
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
          const Icon(Icons.receipt_long, color: FoodFlowTheme.crimson),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _orderContextText!,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                Text(
                  widget.order!.statusText,
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                ),
              ],
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
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: FoodFlowTheme.elevatedCard(radius: 22),
      child: ListTile(
        leading: Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: const Color(0xFFFFF1EE),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Icon(icon, color: FoodFlowTheme.crimson),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
        subtitle: Text(
          subtitle,
          style: const TextStyle(
            color: FoodFlowTheme.muted,
            fontWeight: FontWeight.w500,
          ),
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }

  Widget _buildFaq(String title, String body) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: FoodFlowTheme.elevatedCard(radius: 22),
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 16),
        title: Text(
          title,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
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

class _SupportMessage {
  final String text;
  final bool fromUser;

  const _SupportMessage({
    required this.text,
    required this.fromUser,
  });
}
