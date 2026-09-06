import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../config/api_constants.dart';
import '../../../../models/app_branding.dart';
import '../../../../models/order.dart';
import '../../../../services/api_service.dart';
import '../../../../services/app_branding_service.dart';
import '../../../../services/websocket_service.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

/// Help &amp; Support — two tabs, mirroring the AH Food layout:
///  * **Help** — call / email / start live chat + common-issue FAQs.
///  * **Chat** — a local assistant that answers common questions right away,
///    asks if it helped, and escalates to a human via `/support/conversations`
///    only when asked. The local transcript is kept for 30 days then cleared.
class SupportChatV2 extends StatefulWidget {
  const SupportChatV2({super.key, this.order, this.openChat = false});

  final Order? order;
  final bool openChat;

  @override
  State<SupportChatV2> createState() => _SupportChatV2State();
}

enum _Role { user, bot, system }

class _Msg {
  _Msg(this.text, this.role);
  final String text;
  final _Role role;

  Map<String, dynamic> toJson() => {'t': text, 'r': role.index};
  static _Msg fromJson(Map<String, dynamic> j) => _Msg(
      (j['t'] ?? '').toString(),
      _Role.values[(j['r'] is int ? j['r'] as int : 1).clamp(0, 2)]);
}

class _SupportChatV2State extends State<SupportChatV2> {
  final ApiService _api = ApiService();
  final WebSocketService _ws = WebSocketService();
  final TextEditingController _input = TextEditingController();
  final ScrollController _scroll = ScrollController();

  AppBranding _branding = AppBranding.fallback();

  final List<_Msg> _messages = [];
  bool _awaitingSatisfaction = false;
  bool _showEscalation = false;
  bool _escalated = false;
  bool _sending = false;
  bool _loaded = false;
  int? _conversationId;
  Timer? _poll;

  static const _maxAgeDays = 30;

  String get _prefsKey => widget.order == null
      ? 'v2_support_chat_general'
      : 'v2_support_chat_order_${widget.order!.id}';

  String? get _orderContext =>
      widget.order == null ? null : 'Order #${widget.order!.orderNumber}';

  @override
  void initState() {
    super.initState();
    _loadBranding();
    _restore();
  }

  @override
  void dispose() {
    _poll?.cancel();
    if (_conversationId != null) {
      _ws.removeSupportChatHandler(_conversationId!);
    }
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _loadBranding() async {
    final b = await AppBrandingService.instance.loadBranding();
    if (mounted) setState(() => _branding = b);
  }

  // ---- persistence (30-day TTL) --------------------------------------

  Future<void> _restore() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_prefsKey);
      if (raw != null && raw.isNotEmpty) {
        final decoded = jsonDecode(raw);
        if (decoded is Map) {
          final savedAt = DateTime.tryParse('${decoded['saved_at']}');
          final stale = savedAt == null ||
              DateTime.now().difference(savedAt).inDays >= _maxAgeDays;
          if (stale) {
            await prefs.remove(_prefsKey);
          } else {
            final stored = (decoded['messages'] as List? ?? const [])
                .whereType<Map>()
                .map((e) => _Msg.fromJson(Map<String, dynamic>.from(e)))
                .where((m) => m.text.isNotEmpty)
                .toList();
            _messages.addAll(stored);
            _awaitingSatisfaction = decoded['awaiting'] == true;
            _showEscalation = decoded['escalation'] == true;
            _escalated = decoded['escalated'] == true;
            _conversationId = decoded['conversation_id'] is int
                ? decoded['conversation_id'] as int
                : null;
          }
        }
      }
    } catch (_) {}

    if (_messages.isEmpty) {
      _messages.add(_Msg(
        _orderContext == null
            ? 'Hi! Ask me anything and I’ll try to help right away.'
            : 'Hi! Ask me anything about $_orderContext and I’ll help right away.',
        _Role.bot,
      ));
    }

    if (_escalated && _conversationId != null) {
      _ws.initSupportChat(_conversationId!, onMessage: _onWsMessage);
      _poll =
          Timer.periodic(const Duration(seconds: 5), (_) => _refreshServer());
      _refreshServer();
    }

    if (mounted) setState(() => _loaded = true);
    _toBottom();
  }

  Future<void> _save() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _prefsKey,
        jsonEncode({
          'saved_at': DateTime.now().toIso8601String(),
          'messages': _messages.map((m) => m.toJson()).toList(),
          'awaiting': _awaitingSatisfaction,
          'escalation': _showEscalation,
          'escalated': _escalated,
          'conversation_id': _conversationId,
        }),
      );
    } catch (_) {}
  }

  // ---- local assistant ----------------------------------------------

  String _localReply(String question) {
    final t = question.toLowerCase();
    final order = widget.order;

    if (t.contains('status') || t.contains('where') || t.contains('track')) {
      return order == null
          ? 'Open Orders and tap the active order to see live status and the '
              'delivery partner on the map.'
          : '$_orderContext is currently "${order.statusText}". You can follow '
              'live progress on the tracking screen.';
    }
    if (t.contains('delay') || t.contains('late') || t.contains('slow')) {
      return 'Sorry about the wait. Kitchens get busy at peak times. If it '
          'hasn’t moved in 10–15 minutes, tap "Talk to an agent" below and '
          'we’ll check with the restaurant.';
    }
    if (t.contains('cancel')) {
      return order?.canCancel == true
          ? 'You can cancel this order from the tracking screen while the '
              'cancellation timer is still running.'
          : 'This order can’t be cancelled automatically now. Choose "No" '
              'below and we’ll connect you with an agent.';
    }
    if (t.contains('refund') || t.contains('money back')) {
      return 'Refunds for cancelled or failed orders go back to your original '
          'payment method or Yumma! wallet, usually within 3–5 business days. '
          'You can check the status in Order Details.';
    }
    if (t.contains('payment') || t.contains('paid') || t.contains('charge')) {
      return order == null
          ? 'Payment status for any order is shown in Order Details. Failed '
              'online payments are auto-refunded.'
          : 'The payment status for this order is "${order.paymentStatus}". '
              'If money was deducted but the order didn’t place, it is '
              'auto-refunded within a few days.';
    }
    if (t.contains('otp')) {
      return order?.deliveryOtp?.isNotEmpty == true
          ? 'Your delivery OTP is ${order!.deliveryOtp}. Share it with the '
              'partner only at handover.'
          : 'The delivery OTP appears on the tracking screen once the order '
              'is close to delivery.';
    }
    if (t.contains('driver') || t.contains('rider') || t.contains('partner')) {
      return order?.driver == null
          ? 'A delivery partner is assigned once the restaurant is almost done '
              'preparing your order.'
          : 'Your delivery partner is ${order!.driver!.name}. You can call or '
              'chat with them from the tracking screen.';
    }
    if (t.contains('coupon') || t.contains('promo') || t.contains('offer')) {
      return 'Check the minimum order value and that the coupon is valid for '
          'this restaurant. Some coupons are single-use. You can browse all '
          'your coupons from the checkout screen ("View all").';
    }
    if (t.contains('address') || t.contains('location')) {
      return 'Manage your saved addresses under Profile → Saved Addresses. '
          'You can also change the delivery address on the checkout screen '
          'before placing the order.';
    }
    if (t.contains('tip')) {
      return 'You can add a tip for your delivery partner on the checkout '
          'screen, or after the order is delivered from the tracking screen. '
          'The full tip goes to the partner.';
    }
    if (t.contains('missing') ||
        t.contains('wrong') ||
        t.contains('quality') ||
        t.contains('cold') ||
        t.contains('spilled')) {
      return 'Sorry about that. For a wrong, missing or bad-quality item we '
          'need an agent to review it. Choose "No" below to connect.';
    }
    if (t.contains('hi') || t.contains('hello') || t.contains('hey')) {
      return 'Hi! Tell me what you need help with — order status, refunds, '
          'payments, delivery OTP, coupons, or something else.';
    }
    return 'I can help with order status, delays, cancellation, refunds, '
        'payments, delivery OTP, coupons and delivery partner details. If I '
        'don’t cover it, choose "No" below and I’ll connect you to an agent.';
  }

  // ---- send / escalate --------------------------------------------

  Future<void> _send([String? preset]) async {
    final text = (preset ?? _input.text).trim();
    if (text.isEmpty || _sending) return;

    if (_escalated) {
      setState(() {
        _messages.add(_Msg(text, _Role.user));
        _input.clear();
        _sending = true;
      });
      _toBottom();
      await _sendToServer(text);
      if (mounted) setState(() => _sending = false);
      _save();
      return;
    }

    // Local assistant turn.
    setState(() {
      _messages.add(_Msg(text, _Role.user));
      _messages.add(_Msg(_localReply(text), _Role.bot));
      _messages.add(_Msg('Was this helpful?', _Role.bot));
      _input.clear();
      _awaitingSatisfaction = true;
      _showEscalation = false;
    });
    _toBottom();
    _save();
  }

  void _answerSatisfaction(bool helpful) {
    setState(() {
      _awaitingSatisfaction = false;
      _showEscalation = !helpful;
      _messages.add(_Msg(
        helpful
            ? 'Glad I could help! Ask me anything else whenever you need.'
            : 'No problem — I’ll connect you with a support agent.',
        _Role.bot,
      ));
    });
    _toBottom();
    _save();
    if (!helpful) _startEscalation();
  }

  Future<void> _startEscalation() async {
    if (_escalated) return;
    setState(() {
      _showEscalation = false;
      _messages.add(_Msg('Connecting you with a support agent…', _Role.system));
    });
    _toBottom();
    try {
      // Carry the last question the user asked as context for the agent.
      final lastQuestion = _messages.lastWhere(
        (m) => m.role == _Role.user,
        orElse: () => _Msg('', _Role.user),
      );
      final res = await _api.post(ApiConstants.supportConversations, data: {
        'order_id': widget.order?.id,
        if (lastQuestion.text.isNotEmpty) 'message': lastQuestion.text,
      });
      final data = res is Map ? res['data'] : null;
      final id = data is Map ? data['id'] : null;
      _conversationId = id is int ? id : int.tryParse('$id');
      _escalated = true;
      if (_conversationId != null) {
        _ws.initSupportChat(_conversationId!, onMessage: _onWsMessage);
        _poll = Timer.periodic(
            const Duration(seconds: 5), (_) => _refreshServer());
        await _refreshServer();
      }
    } catch (_) {
      if (mounted) {
        setState(() => _messages.add(_Msg(
            'Could not reach support just now. Please try again in a bit.',
            _Role.system)));
      }
    }
    if (mounted) setState(() {});
    _save();
  }

  Future<void> _sendToServer(String text) async {
    if (_conversationId == null) return;
    try {
      await _api.post(
          ApiConstants.supportConversationMessages(_conversationId!),
          data: {'message': text});
      await _refreshServer();
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Could not send message.')));
      }
    }
  }

  Future<void> _refreshServer() async {
    if (_conversationId == null) return;
    try {
      final res =
          await _api.get(ApiConstants.supportConversation(_conversationId!));
      final data = res is Map ? res['data'] : null;
      final list = (res is Map ? res['messages'] : null) ??
          (data is Map ? data['messages'] : null);
      if (list is! List) return;
      final server = <_Msg>[];
      for (final e in list.whereType<Map>()) {
        final sender = (e['sender_type'] ?? '').toString();
        final body = (e['message'] ?? e['body'] ?? '').toString();
        if (body.isEmpty) continue;
        server.add(_Msg(
          body,
          sender == 'customer'
              ? _Role.user
              : sender == 'system'
                  ? _Role.system
                  : _Role.bot,
        ));
      }
      if (server.isEmpty || !mounted) return;
      setState(() {
        // Keep the local pre-escalation transcript, append the server thread.
        final keep = _messages
            .takeWhile((m) => m.role != _Role.system ||
                !m.text.contains('Connecting you'))
            .toList();
        _messages
          ..clear()
          ..addAll(keep)
          ..add(_Msg('— connected to support —', _Role.system))
          ..addAll(server);
      });
      _toBottom();
      _save();
    } catch (_) {}
  }

  void _onWsMessage(Map<String, dynamic> payload) {
    if ('${payload['conversation_id'] ?? ''}' != '${_conversationId ?? ''}') {
      return;
    }
    _refreshServer();
  }

  Future<void> _clearChat() async {
    _poll?.cancel();
    if (_conversationId != null) {
      _ws.removeSupportChatHandler(_conversationId!);
    }
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_prefsKey);
    } catch (_) {}
    if (!mounted) return;
    setState(() {
      _messages
        ..clear()
        ..add(_Msg(
          'Hi! Ask me anything and I’ll try to help right away.',
          _Role.bot,
        ));
      _awaitingSatisfaction = false;
      _showEscalation = false;
      _escalated = false;
      _conversationId = null;
    });
  }

  void _toBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(_scroll.position.maxScrollExtent,
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOut);
      }
    });
  }

  // ---- contact actions ------------------------------------------------

  Future<void> _dial() async {
    final phone = _branding.supportPhone.trim();
    if (phone.isEmpty) {
      _snack('Support phone number is not set yet.');
      return;
    }
    await launchUrl(Uri(scheme: 'tel', path: phone),
        mode: LaunchMode.externalApplication);
  }

  Future<void> _mail() async {
    final email = _branding.supportEmail.trim();
    if (email.isEmpty) {
      _snack('Support email is not set yet.');
      return;
    }
    await launchUrl(
      Uri(scheme: 'mailto', path: email, queryParameters: {
        'subject': _orderContext == null
            ? 'Support request'
            : 'Support request for $_orderContext',
      }),
      mode: LaunchMode.externalApplication,
    );
  }

  void _snack(String m) {
    if (mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(m)));
    }
  }

  // ---- build --------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      initialIndex: widget.openChat ? 1 : 0,
      child: V2Scaffold(
        title: 'Help & Support',
        showBack: true,
        body: Builder(
          builder: (context) {
            final p = V2Theme.of(context);
            return Column(
              children: [
                Container(
                  margin: const EdgeInsets.fromLTRB(16, 4, 16, 4),
                  decoration: BoxDecoration(
                    color: p.glassTop,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: p.glassBorder),
                  ),
                  child: TabBar(
                    indicator: BoxDecoration(
                      color: p.accent,
                      borderRadius: BorderRadius.circular(11),
                    ),
                    indicatorSize: TabBarIndicatorSize.tab,
                    indicatorPadding: const EdgeInsets.all(4),
                    dividerColor: Colors.transparent,
                    labelColor: Colors.white,
                    unselectedLabelColor: p.inkSoft,
                    labelStyle: const TextStyle(
                        fontSize: 12.5, fontWeight: FontWeight.w900),
                    unselectedLabelStyle: const TextStyle(
                        fontSize: 12.5, fontWeight: FontWeight.w700),
                    tabs: const [
                      Tab(text: 'Help'),
                      Tab(text: 'Chat'),
                    ],
                  ),
                ),
                Expanded(
                  child: TabBarView(
                    children: [
                      _HelpTab(
                        branding: _branding,
                        orderContext: _orderContext,
                        orderStatus: widget.order?.statusText,
                        onCall: _dial,
                        onMail: _mail,
                        onLiveChat: () =>
                            DefaultTabController.of(context).animateTo(1),
                      ),
                      _loaded
                          ? _chatTab(p)
                          : Center(
                              child: CircularProgressIndicator(
                                  color: p.accent)),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _chatTab(V2Palette p) {
    return Column(
      children: [
        if (_orderContext != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
            child: _OrderBanner(
                label: _orderContext!, status: widget.order?.statusText),
          ),
        Expanded(
          child: ListView.builder(
            controller: _scroll,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            itemCount: _messages.length,
            itemBuilder: (_, i) => _Bubble(msg: _messages[i]),
          ),
        ),
        if (_awaitingSatisfaction)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: _InlineCard(
              child: Row(
                children: [
                  Expanded(
                    child: Text('Was this helpful?',
                        style: TextStyle(
                            color: p.ink,
                            fontSize: 13,
                            fontWeight: FontWeight.w800)),
                  ),
                  _pill(p, Icons.thumb_up_alt_outlined, 'Yes',
                      () => _answerSatisfaction(true)),
                  const SizedBox(width: 8),
                  _pill(p, Icons.thumb_down_alt_outlined, 'No',
                      () => _answerSatisfaction(false)),
                ],
              ),
            ),
          ),
        if (_showEscalation)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: _InlineCard(
              child: Row(
                children: [
                  Icon(Icons.support_agent_rounded,
                      size: 18, color: p.accent),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text('Talk to a support agent instead?',
                        style: TextStyle(
                            color: p.ink,
                            fontSize: 13,
                            fontWeight: FontWeight.w700)),
                  ),
                  _pill(p, Icons.headset_mic_rounded, 'Connect',
                      _startEscalation, filled: true),
                ],
              ),
            ),
          ),
        _Composer(
          controller: _input,
          sending: _sending,
          onSend: () => _send(),
          onClear: _clearChat,
        ),
      ],
    );
  }

  Widget _pill(V2Palette p, IconData icon, String label, VoidCallback onTap,
      {bool filled = false}) {
    return V2Tappable(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: filled ? p.accent : p.accent.withOpacity(0.10),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
              color: filled ? p.accent : p.accent.withOpacity(0.4)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: filled ? Colors.white : p.accent),
            const SizedBox(width: 5),
            Text(label,
                style: TextStyle(
                    color: filled ? Colors.white : p.accent,
                    fontSize: 12,
                    fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

// =====================================================================
// Help tab
// =====================================================================

class _HelpTab extends StatelessWidget {
  const _HelpTab({
    required this.branding,
    required this.orderContext,
    required this.orderStatus,
    required this.onCall,
    required this.onMail,
    required this.onLiveChat,
  });

  final AppBranding branding;
  final String? orderContext;
  final String? orderStatus;
  final VoidCallback onCall;
  final VoidCallback onMail;
  final VoidCallback onLiveChat;

  static const _faqs = <(String, String)>[
    (
      'Where is my order?',
      'Open Orders and tap the active order for live status and the delivery '
          'partner. If it hasn’t moved in a while, start a chat and share '
          'your order number.'
    ),
    (
      'Delivery OTP is missing',
      'The OTP is generated automatically. Keep the tracking screen open for '
          'a few seconds or pull to refresh the order.'
    ),
    (
      'Need to cancel or get a refund',
      'Orders can be cancelled only before the restaurant starts preparing. '
          'Refunds go back to your original payment method or wallet, usually '
          'in 3–5 business days.'
    ),
    (
      'A coupon is not applying',
      'Check the minimum order value and that the coupon is valid for this '
          'restaurant. Some coupons are single-use.'
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final phone = branding.supportPhone.trim();
    final email = branding.supportEmail.trim();
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      children: [
        V2Entrance(
          child: GlassPanel(
            radius: 20,
            strong: true,
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: p.accent.withOpacity(0.16),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(Icons.support_agent_rounded,
                      color: p.accent, size: 24),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Support that feels personal',
                          style: TextStyle(
                              color: p.ink,
                              fontSize: 14.5,
                              fontWeight: FontWeight.w900)),
                      const SizedBox(height: 5),
                      Text(
                          'Call, email, or chat with our team without leaving '
                          'the app.',
                          style: TextStyle(
                              color: p.inkSoft,
                              fontSize: 12.5,
                              height: 1.35)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        if (orderContext != null) ...[
          _OrderBanner(label: orderContext!, status: orderStatus),
          const SizedBox(height: 12),
        ],
        _ActionTile(
          icon: Icons.call_outlined,
          title: 'Call customer support',
          subtitle: phone.isEmpty ? 'Not configured' : phone,
          onTap: onCall,
        ),
        _ActionTile(
          icon: Icons.mail_outline_rounded,
          title: 'Email customer support',
          subtitle: email.isEmpty ? 'Not configured' : email,
          onTap: onMail,
        ),
        _ActionTile(
          icon: Icons.chat_bubble_outline_rounded,
          title: 'Start live chat',
          subtitle: 'Message support inside the app',
          onTap: onLiveChat,
        ),
        const SizedBox(height: 20),
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 10),
          child: Text('COMMON ISSUES',
              style: TextStyle(
                  color: p.inkFaint,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.8)),
        ),
        for (final f in _faqs)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: _Faq(q: f.$1, a: f.$2),
          ),
      ],
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: GlassPanel(
        radius: 16,
        onTap: onTap,
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: p.accent.withOpacity(0.12),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Icon(icon, color: p.accent, size: 21),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          color: p.ink,
                          fontSize: 14,
                          fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text(subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: p.inkFaint, fontSize: 12)),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: p.inkFaint, size: 22),
          ],
        ),
      ),
    );
  }
}

class _OrderBanner extends StatelessWidget {
  const _OrderBanner({required this.label, this.status});
  final String label;
  final String? status;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: p.accent.withOpacity(0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: p.accent.withOpacity(0.3)),
      ),
      child: Row(
        children: [
          Icon(Icons.receipt_long_rounded, color: p.accent, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label,
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w900)),
                if ((status ?? '').isNotEmpty)
                  Text(status!,
                      style: TextStyle(color: p.inkSoft, fontSize: 11.5)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Faq extends StatefulWidget {
  const _Faq({required this.q, required this.a});
  final String q;
  final String a;

  @override
  State<_Faq> createState() => _FaqState();
}

class _FaqState extends State<_Faq> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return GlassPanel(
      radius: 16,
      onTap: () => setState(() => _open = !_open),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(widget.q,
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 13.5,
                        fontWeight: FontWeight.w800)),
              ),
              Icon(
                _open
                    ? Icons.keyboard_arrow_up_rounded
                    : Icons.keyboard_arrow_down_rounded,
                color: p.inkFaint,
              ),
            ],
          ),
          AnimatedCrossFade(
            firstChild: const SizedBox(width: double.infinity),
            secondChild: Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Text(widget.a,
                  style: TextStyle(
                      color: p.inkSoft, fontSize: 12.5, height: 1.45)),
            ),
            crossFadeState:
                _open ? CrossFadeState.showSecond : CrossFadeState.showFirst,
            duration: const Duration(milliseconds: 200),
          ),
        ],
      ),
    );
  }
}

// =====================================================================
// Chat bits
// =====================================================================

class _InlineCard extends StatelessWidget {
  const _InlineCard({required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: p.glassStrongTop,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: p.glassBorder),
      ),
      child: child,
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.msg});
  final _Msg msg;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    if (msg.role == _Role.system) {
      return Center(
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: 8),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
          decoration: BoxDecoration(
            color: p.glassTop,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: p.glassBorder),
          ),
          child: Text(msg.text,
              textAlign: TextAlign.center,
              style: TextStyle(
                  color: p.inkFaint,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w700)),
        ),
      );
    }
    final isMine = msg.role == _Role.user;
    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        constraints: BoxConstraints(
            maxWidth: MediaQuery.sizeOf(context).width * 0.78),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: isMine ? p.accent : p.glassStrongTop,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(isMine ? 16 : 4),
            bottomRight: Radius.circular(isMine ? 4 : 16),
          ),
          border: isMine ? null : Border.all(color: p.glassBorder),
        ),
        child: Text(msg.text,
            style: TextStyle(
                color: isMine ? Colors.white : p.ink,
                fontSize: 13.5,
                height: 1.35)),
      ),
    );
  }
}

class _Composer extends StatelessWidget {
  const _Composer({
    required this.controller,
    required this.sending,
    required this.onSend,
    required this.onClear,
  });

  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      padding: EdgeInsets.fromLTRB(
          14, 8, 14, MediaQuery.of(context).padding.bottom + 12),
      decoration: BoxDecoration(
        color: p.isDark ? const Color(0xF2121720) : Colors.white,
        border: Border(top: BorderSide(color: p.glassBorder)),
      ),
      child: Row(
        children: [
          V2Tappable(
            onTap: onClear,
            child: Padding(
              padding: const EdgeInsets.only(right: 8),
              child: Icon(Icons.delete_sweep_outlined,
                  size: 22, color: p.inkFaint),
            ),
          ),
          Expanded(
            child: TextField(
              controller: controller,
              minLines: 1,
              maxLines: 4,
              keyboardAppearance:
                  p.isDark ? Brightness.dark : Brightness.light,
              style: TextStyle(color: p.ink, fontSize: 13.5),
              cursorColor: p.accent,
              onSubmitted: (_) => onSend(),
              decoration: InputDecoration(
                isDense: true,
                filled: true,
                fillColor:
                    p.isDark ? const Color(0xFF1B2233) : p.glassTop,
                hintText: 'Type your message…',
                hintStyle: TextStyle(color: p.inkFaint, fontSize: 13),
                contentPadding: const EdgeInsets.symmetric(
                    horizontal: 14, vertical: 12),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: p.glassBorder),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: p.glassBorder),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: p.accent, width: 1.5),
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          V2Tappable(
            onTap: sending ? null : onSend,
            child: Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: p.accent,
                shape: BoxShape.circle,
              ),
              child: sending
                  ? const Padding(
                      padding: EdgeInsets.all(12),
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.send_rounded,
                      color: Colors.white, size: 19),
            ),
          ),
        ],
      ),
    );
  }
}
