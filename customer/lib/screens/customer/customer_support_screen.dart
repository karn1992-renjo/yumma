// lib/screens/customer/customer_support_screen.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/api_constants.dart';
import '../../models/app_branding.dart';
import '../../models/order.dart';
import '../../providers/order_provider.dart';
import '../../services/api_service.dart';
import '../../services/app_branding_service.dart';
import '../../services/websocket_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import 'order_tracking_screen.dart';

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

  // --- Client-side assistant (Chat tab, before a live agent is pulled in) ---
  final TextEditingController _asstInput = TextEditingController();
  final List<_AsstMsg> _asst = [];
  bool _asstTyping = false;
  bool _liveChat = false;
  Map<String, dynamic>? _refundPolicyCache;
  bool _awaitSubject = false;

  @override
  void initState() {
    super.initState();
    _loadBranding();
    _seedAssistant();
  }

  @override
  void dispose() {
    if (_conversationId != null) {
      _webSocketService.removeSupportChatHandler(_conversationId!);
    }
    _messageController.dispose();
    _csatCommentController.dispose();
    _asstInput.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _seedAssistant() {
    _asst.add(_AsstMsg.bot(
      'Hi! I\'m the $_assistantName assistant. What do you need help with?',
      chips: [
        for (final t in _kTopics) _AsstChip(t.label, () => _runTopic(t.key)),
        _AsstChip('Something else', _offerHuman),
      ],
    ));
  }

  String get _assistantName {
    final n = _branding.displayName.trim();
    return n.isEmpty ? 'Yumma!' : n;
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
        ? 'Yumma! support request'
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
    if (!_liveChat) return _buildAssistantTab();

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

  // ======================================================================
  // Client-side assistant
  // ======================================================================

  Widget _buildAssistantTab() {
    return Column(
      children: [
        if (_orderContextText != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _buildOrderBanner(),
          ),
        Container(
          margin: const EdgeInsets.fromLTRB(16, 10, 16, 0),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
          decoration: BoxDecoration(
            color: const Color(0xFFF3F0FF),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Row(
            children: const [
              Icon(Icons.smart_toy_outlined,
                  size: 16, color: Color(0xFF6D5BD0)),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Chatting with the assistant',
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF6D5BD0),
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: ListView.builder(
            controller: _scrollController,
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 20),
            itemCount: _asst.length + (_asstTyping ? 1 : 0),
            itemBuilder: (context, index) {
              if (index == _asst.length) return const _AsstTypingDots();
              return _buildAsstBubble(_asst[index]);
            },
          ),
        ),
        _buildAsstInputBar(),
      ],
    );
  }

  void _asstScroll() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent + 200,
        duration: const Duration(milliseconds: 240),
        curve: Curves.easeOut,
      );
    });
  }

  void _asstUser(String text) {
    setState(() => _asst.add(_AsstMsg.user(text)));
    _asstScroll();
  }

  void _asstSay(String text, {List<_AsstChip>? chips, Widget? custom}) {
    setState(() => _asstTyping = true);
    _asstScroll();
    Timer(const Duration(milliseconds: 650), () {
      if (!mounted) return;
      setState(() {
        _asstTyping = false;
        _asst.add(_AsstMsg.bot(text, chips: chips, custom: custom));
      });
      _asstScroll();
    });
  }

  // ---- data ----

  Future<Map<String, dynamic>> _refundPolicy() async {
    if (_refundPolicyCache != null) return _refundPolicyCache!;
    try {
      final res = await _api.get(ApiConstants.refundPolicy);
      final data = res is Map ? res['data'] : null;
      _refundPolicyCache =
          data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
    } catch (_) {
      _refundPolicyCache = <String, dynamic>{};
    }
    return _refundPolicyCache!;
  }

  /// (cancellationCharge, refundAmount, note)
  Future<(double, double, String)> _refundPreview(Order o) async {
    if (!o.isPaymentPaid) {
      return (0.0, 0.0, 'No payment was collected, so there\'s nothing to refund.');
    }
    final policy = await _refundPolicy();
    final rules = policy['cancellation_refund_rules'];
    double? pct;
    if (rules is Map && rules[o.status] != null) {
      pct = double.tryParse('${rules[o.status]}');
    }
    pct ??=
        const {'pending': 95.0, 'confirmed': 85.0, 'preparing': 70.0}[o.status];
    pct ??= 0.0;
    final refund = double.parse((o.total * pct / 100).toStringAsFixed(2));
    final charge = double.parse((o.total - refund).toStringAsFixed(2));
    return (charge, refund, '');
  }

  Future<void> _ensureOrders() async {
    final provider = context.read<OrderProvider>();
    if (provider.orders.isNotEmpty) return;
    setState(() => _asstTyping = true);
    _asstScroll();
    await provider.fetchMyOrders(notifyLoading: false);
    if (!mounted) return;
    setState(() => _asstTyping = false);
  }

  Future<void> _needOrder(void Function(Order) then) async {
    // An order passed into the screen wins outright.
    if (widget.order != null) {
      then(widget.order!);
      return;
    }
    await _ensureOrders();
    if (!mounted) return;
    final orders = context.read<OrderProvider>().orders;
    if (orders.isEmpty) {
      _asstSay('I can\'t see any orders on your account yet.', chips: [
        _AsstChip('Talk to a human', () => _talkToHuman()),
      ]);
      return;
    }
    final recent = orders.take(5).toList();
    if (recent.length == 1) {
      then(recent.first);
      return;
    }
    _asstSay('Which order? Here are your latest ones.',
        custom: _AsstOrderPicker(
          orders: recent,
          onPick: (o) {
            _asstUser('#${o.orderNumber}');
            then(o);
          },
        ));
  }

  // ---- topics ----

  void _runTopic(String key) {
    final t = _kTopics.firstWhere((t) => t.key == key,
        orElse: () => _kTopics.first);
    _asstUser(t.label);
    _dispatch(key);
  }

  void _dispatch(String key) {
    switch (key) {
      case 'order':
        _needOrder(_answerTrack);
        break;
      case 'payment':
        _needOrder(_answerPayment);
        break;
      case 'refund':
        _needOrder(_answerRefund);
        break;
      case 'delivery':
        _needOrder(_answerDelivery);
        break;
      case 'cancel':
        _needOrder(_cancelForOrder);
        break;
      case 'account':
        _asstSay(
            'Update your name, phone or photo in Profile → Edit Profile. Manage saved addresses under Saved Addresses. To close your account, contact support.');
        break;
    }
  }

  String _friendlyStatus(Order o) {
    switch (o.status) {
      case 'delivered':
        return 'Delivered';
      case 'cancelled':
        return 'Cancelled';
      case 'out_for_delivery':
      case 'picked_up':
      case 'on_the_way':
        return 'On the way';
      case 'ready':
      case 'ready_for_pickup':
        return 'Ready';
      case 'preparing':
        return 'Being prepared';
      case 'confirmed':
        return 'Confirmed by the restaurant';
      default:
        return 'Waiting for the restaurant to accept';
    }
  }

  String _methodLabel(String raw) {
    switch (raw.toLowerCase()) {
      case 'cod':
        return 'Cash on delivery';
      case 'wallet':
        return 'Wallet';
      case 'razorpay':
        return 'Razorpay';
      case 'stripe':
        return 'Stripe';
      case 'cashfree':
        return 'Cashfree';
      case '':
        return 'your payment method';
      default:
        return raw[0].toUpperCase() + raw.substring(1);
    }
  }

  String _money(num v) => formatCurrency(context, v);

  void _openTracking(Order o) {
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => OrderTrackingScreen(orderId: o.id),
    ));
  }

  void _answerTrack(Order o) {
    final buf = StringBuffer()
      ..writeln('Order #${o.orderNumber} — ${_friendlyStatus(o)}');
    final eta = o.etaRange ??
        (o.etaMinutes != null ? 'about ${o.etaMinutes} min' : null);
    if (!o.isDelivered && !o.isCancelled && eta != null) {
      buf.writeln('Estimated arrival: $eta');
    }
    if (o.deliveryAddress.trim().isNotEmpty && !o.isTakeaway) {
      buf.writeln('Delivering to: ${o.deliveryAddress.trim()}');
    }
    _asstSay(buf.toString().trimRight(), chips: [
      _AsstChip('Open live tracking', () => _openTracking(o)),
      _AsstChip('That helped', _thanks),
      _AsstChip('Talk to a human',
          () => _talkToHuman(prefill: 'Question about order #${o.orderNumber}.')),
    ]);
  }

  Future<void> _answerPayment(Order o) async {
    setState(() => _asstTyping = true);
    _asstScroll();
    String status = o.paymentStatus;
    String method = o.paymentMethod;
    String? txn = o.refundTransactionId;
    DateTime? paidAt = o.paidAt;
    try {
      final res = await _api.get(ApiConstants.orderPaymentStatus(o.id));
      final d = res is Map ? res['data'] : null;
      if (d is Map) {
        status = '${d['payment_status'] ?? status}';
        method = '${d['payment_method'] ?? method}';
        final t = '${d['transaction_id'] ?? ''}';
        if (t.isNotEmpty && t != 'null') txn = t;
        final p = d['paid_at']?.toString();
        if (p != null && p.isNotEmpty) paidAt = DateTime.tryParse(p) ?? paidAt;
      }
    } catch (_) {/* fall back to model */}
    if (!mounted) return;
    setState(() => _asstTyping = false);

    final m = _methodLabel(method);
    String reply;
    final chips = <_AsstChip>[];
    switch (status.toLowerCase()) {
      case 'success':
      case 'paid':
      case 'completed':
        reply =
            'Paid ${_money(o.total)} via $m${paidAt != null ? ' on ${_fmtDate(paidAt)}' : ''}. Nothing is pending on this order.';
        break;
      case 'refunded':
        reply =
            'This payment was refunded. Ask me about "Refund status" for the details.';
        chips.add(_AsstChip('Refund status', () {
          _asstUser('Refund status');
          _answerRefund(o);
        }));
        break;
      case 'failed':
      case 'cancelled':
        reply =
            'The payment did not go through, so no money was captured. If your bank shows a temporary hold it is released in 3–5 working days.';
        if (o.canPayOnlineNow) {
          chips.add(_AsstChip('Pay again', () => _openTracking(o)));
        }
        break;
      default:
        reply =
            'The payment for this order is not confirmed yet. Please don\'t retry for about 15 minutes — if money was debited it is reversed automatically to the original method within 3–5 working days.';
        if (o.canPayOnlineNow) {
          chips.add(_AsstChip('Pay again', () => _openTracking(o)));
        }
    }
    if (txn != null && txn.isNotEmpty && txn != 'null') {
      reply = '$reply\nReference: $txn';
    }
    chips.add(_AsstChip('That helped', _thanks));
    chips.add(_AsstChip('Talk to a human',
        () => _talkToHuman(prefill: 'Payment issue with order #${o.orderNumber}.')));
    _asstSay(reply, chips: chips);
  }

  Future<void> _answerRefund(Order o) async {
    final policy = await _refundPolicy();
    if (!mounted) return;
    final windowH = policy['refund_window_hours'];
    final rs = (o.refundStatus ?? '').toLowerCase();
    final dest = o.refundModeLabel ?? _methodLabel(o.paymentMethod);
    String reply;
    final chips = <_AsstChip>[];
    if (rs.isEmpty) {
      if (o.isDelivered && o.isPaymentPaid) {
        reply =
            'There is no refund in progress for this order. If something was wrong with it you can request one.';
        chips.add(_AsstChip('Request a refund', () => _requestRefund(o)));
      } else {
        reply = 'There is no refund in progress for this order.';
      }
      if (windowH != null) {
        reply =
            '$reply\nRefunds can be requested within $windowH hours of ordering.';
      }
    } else if (rs == 'pending' || rs == 'requested') {
      reply =
          'Your refund request has been received and is awaiting review by our team.';
    } else if (rs == 'processing') {
      reply =
          'Your refund of ${_money(o.refundAmount ?? 0)} is approved and on its way to $dest. It usually settles in 3–5 working days.';
    } else if (rs == 'completed' || rs == 'refunded' || rs == 'success') {
      reply = '${_money(o.refundAmount ?? 0)} was refunded to $dest.';
      if ((o.refundTransactionId ?? '').isNotEmpty) {
        reply = '$reply\nReference: ${o.refundTransactionId}';
      }
    } else if (rs == 'rejected' || rs == 'declined' || rs == 'failed') {
      reply =
          'Your refund request was not approved. Talk to a human if you\'d like our team to take another look.';
      chips.add(_AsstChip('Talk to a human',
          () => _talkToHuman(prefill: 'Refund query for order #${o.orderNumber}.')));
    } else {
      reply = 'Refund status: $rs.';
    }
    chips.add(_AsstChip('That helped', _thanks));
    chips.add(_AsstChip('Talk to a human',
        () => _talkToHuman(prefill: 'Refund query for order #${o.orderNumber}.')));
    _asstSay(reply, chips: chips);
  }

  void _answerDelivery(Order o) {
    _asstSay(
      'Sorry your order had a problem. Our team can review order #${o.orderNumber} and make it right.',
      chips: [
        _AsstChip('Talk to a human',
            () => _talkToHuman(
                prefill:
                    'I had a delivery problem with order #${o.orderNumber}.')),
      ],
    );
  }

  Future<void> _requestRefund(Order o) async {
    final ok = await context
        .read<OrderProvider>()
        .requestRefund(o.id, 'Requested from support chat');
    if (!mounted) return;
    _asstSay(ok
        ? 'Refund request submitted. Our team will review it and you\'ll be updated in Notifications.'
        : 'Could not submit the refund request. Please try again later.');
  }

  // ---- cancel ----

  Future<void> _cancelForOrder(Order o) async {
    if (o.isCancelled) {
      _asstSay('This order is already cancelled.');
      return;
    }
    const tooLate = ['out_for_delivery', 'picked_up', 'on_the_way', 'delivered'];
    if (o.isDelivered || tooLate.contains(o.status)) {
      _asstSay(
        'This order is too far along to cancel — our team can still help.',
        chips: [
          _AsstChip('Talk to a human',
              () => _talkToHuman(
                  prefill: 'Please cancel order #${o.orderNumber}.')),
        ],
      );
      return;
    }

    setState(() => _asstTyping = true);
    _asstScroll();
    final (charge, refund, note) = await _refundPreview(o);
    if (!mounted) return;
    setState(() => _asstTyping = false);

    final method = o.refundModeLabel ?? _methodLabel(o.paymentMethod);

    if (o.canCancel) {
      _asstSay(
        'You can still cancel order #${o.orderNumber} right now.',
        custom: _AsstCancelCard(
          orderNumber: o.orderNumber,
          charge: charge,
          refund: refund,
          method: method,
          note: note,
          confirmLabel: 'Confirm cancel',
          onConfirm: () => _doInstantCancel(o),
          onDismiss: () => _asstSay('No problem — your order is unchanged.'),
        ),
      );
      return;
    }

    if (o.canForceCancel) {
      _asstSay(
        'The restaurant has already started preparing order #${o.orderNumber}, so I can\'t cancel it instantly. I can pass a cancellation request to our team.',
        custom: _AsstCancelCard(
          orderNumber: o.orderNumber,
          charge: charge,
          refund: refund,
          method: method,
          note: note.isEmpty
              ? 'Refund shown is an estimate — the final amount is confirmed by our team.'
              : note,
          confirmLabel: 'Request cancellation',
          onConfirm: () => _talkToHuman(
              prefill:
                  'Please cancel order #${o.orderNumber}. Estimated refund ${_money(refund)}.'),
          onDismiss: () => _asstSay('No problem — your order is unchanged.'),
        ),
      );
      return;
    }

    _asstSay('This order can\'t be cancelled from here.', chips: [
      _AsstChip('Talk to a human',
          () => _talkToHuman(prefill: 'Please cancel order #${o.orderNumber}.')),
    ]);
  }

  Future<void> _doInstantCancel(Order o) async {
    setState(() => _asstTyping = true);
    _asstScroll();
    final provider = context.read<OrderProvider>();
    bool ok = false;
    String? apiMsg;
    try {
      ok = await provider.cancelOrder(o.id, 'Cancelled from support chat');
      apiMsg = provider.error;
    } catch (e) {
      apiMsg = e.toString();
    }
    if (!mounted) return;
    setState(() => _asstTyping = false);
    if (ok) {
      await provider.fetchMyOrders(notifyLoading: false);
      final fresh =
          provider.orders.firstWhere((x) => x.id == o.id, orElse: () => o);
      final amount = fresh.refundAmount ?? 0;
      _asstSay(amount > 0
          ? 'Order #${o.orderNumber} is cancelled. ${_money(amount)} will be refunded to ${fresh.refundModeLabel ?? _methodLabel(o.paymentMethod)}.'
          : 'Order #${o.orderNumber} is cancelled.');
    } else {
      final reason = (apiMsg == null || apiMsg.isEmpty)
          ? 'please try again'
          : apiMsg.replaceFirst('Exception: ', '');
      _asstSay(
        'I couldn\'t cancel it: $reason. Our team can still handle it.',
        chips: [
          _AsstChip('Talk to a human',
              () => _talkToHuman(
                  prefill: 'Please cancel order #${o.orderNumber}.')),
        ],
      );
    }
  }

  // ---- human handoff ----

  void _thanks() {
    _asstUser('That helped');
    _asstSay('Glad I could help! Ask me anything else, anytime.', chips: [
      for (final t in _kTopics.take(4)) _AsstChip(t.label, () => _runTopic(t.key)),
    ]);
  }

  void _offerHuman() {
    _asstUser('Something else');
    _awaitSubject = true;
    _asstSay('Sure — tell me the subject of your issue in a few words.');
  }

  Future<void> _talkToHuman({String? prefill}) async {
    _asstSay('Connecting you with a support agent…');
    await _startOrLoadConversation();
    if (!mounted) return;
    if (_conversationId != null && _stage == 'bot') {
      await _escalateToAgent();
    }
    if (!mounted) return;
    if (prefill != null && prefill.trim().isNotEmpty && _conversationId != null) {
      await _sendMessage(prefill.trim());
    }
    if (!mounted) return;
    setState(() => _liveChat = true);
  }

  _AsstTopic? _matchTopic(String text) {
    final q = text.toLowerCase();
    for (final t in _kTopics) {
      if (t.keywords.any(q.contains)) return t;
    }
    return null;
  }

  void _asstSend() {
    final text = _asstInput.text.trim();
    if (text.isEmpty) return;
    _asstInput.clear();
    _asstUser(text);

    if (_awaitSubject) {
      _awaitSubject = false;
      _asstSay('Got it. I\'ll connect you with an agent about "$text".',
          chips: [
            _AsstChip('Talk to a human', () => _talkToHuman(prefill: text)),
            _AsstChip('Never mind', () {}),
          ]);
      return;
    }

    final t = _matchTopic(text);
    if (t != null) {
      _dispatch(t.key);
    } else {
      _asstSay(
        'I\'m not sure I can answer that, but a support agent can help.',
        chips: [
          _AsstChip('Talk to a human', () => _talkToHuman(prefill: text)),
        ],
      );
    }
  }

  String _fmtDate(DateTime d) {
    final l = d.toLocal();
    final h = l.hour > 12 ? l.hour - 12 : (l.hour == 0 ? 12 : l.hour);
    final mm = l.minute.toString().padLeft(2, '0');
    final ap = l.hour >= 12 ? 'PM' : 'AM';
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    return '${l.day} ${months[l.month - 1]}, $h:$mm $ap';
  }

  // ---- assistant UI ----

  Widget _buildAsstBubble(_AsstMsg m) {
    return Column(
      crossAxisAlignment:
          m.bot ? CrossAxisAlignment.start : CrossAxisAlignment.end,
      children: [
        if (m.text.trim().isNotEmpty)
          Row(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            mainAxisAlignment:
                m.bot ? MainAxisAlignment.start : MainAxisAlignment.end,
            children: [
              if (m.bot) _avatar(isBot: true),
              Flexible(
                child: Container(
                  margin: const EdgeInsets.only(bottom: 6),
                  constraints: const BoxConstraints(maxWidth: 260),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: m.bot ? Colors.white : FoodFlowTheme.crimson,
                    borderRadius: BorderRadius.only(
                      topLeft: const Radius.circular(16),
                      topRight: const Radius.circular(16),
                      bottomLeft: Radius.circular(m.bot ? 4 : 16),
                      bottomRight: Radius.circular(m.bot ? 16 : 4),
                    ),
                    border: m.bot
                        ? Border.all(color: Colors.grey.shade200)
                        : null,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.04),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Text(
                    m.text,
                    style: TextStyle(
                      color: m.bot ? Colors.black87 : Colors.white,
                      fontSize: 13,
                      height: 1.35,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ],
          ),
        if (m.custom != null)
          Padding(
            padding: EdgeInsets.only(bottom: 8, left: m.bot ? 34 : 0),
            child: m.custom!,
          ),
        if (m.chips != null && m.chips!.isNotEmpty)
          Padding(
            padding: EdgeInsets.only(bottom: 12, top: 2, left: m.bot ? 34 : 0),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final c in m.chips!)
                  OutlinedButton(
                    onPressed: c.onTap,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: FoodFlowTheme.crimson,
                      side: const BorderSide(color: Color(0xFFFFD2AA)),
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 8),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(20)),
                    ),
                    child: Text(
                      c.label,
                      style: const TextStyle(
                          fontSize: 12, fontWeight: FontWeight.w700),
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _buildAsstInputBar() {
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
                  controller: _asstInput,
                  minLines: 1,
                  maxLines: 3,
                  textInputAction: TextInputAction.send,
                  onSubmitted: (_) => _asstSend(),
                  decoration: const InputDecoration(
                    hintText: 'Type your message',
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
                  onPressed: _asstSend,
                  color: Colors.white,
                  icon: const Icon(Icons.send_rounded, size: 20),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// A persistent strip under the header, mirroring the "Chatting with
  /// FoodFlow Assistant / Talk to a human" bar Zomato & Swiggy keep pinned
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
                  ? 'Chatting with the Yumma! assistant'
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
      'bot' => 'Yumma! Assistant',
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

// ==========================================================================
// Client-side assistant — models & widgets
// ==========================================================================

class _AsstTopic {
  const _AsstTopic(this.key, this.label, this.keywords);
  final String key;
  final String label;
  final List<String> keywords;
}

const List<_AsstTopic> _kTopics = [
  _AsstTopic('order', 'Track my order', [
    'track', 'where', 'order', 'status', 'late', 'delay', 'eta', 'arrive'
  ]),
  _AsstTopic('payment', 'Payment issue', [
    'pay', 'payment', 'deducted', 'debited', 'failed', 'upi', 'card', 'money', 'charged'
  ]),
  _AsstTopic('refund', 'Refund status',
      ['refund', 'return money', 'reversal', 'cashback', 'money back']),
  _AsstTopic('cancel', 'Cancel an order',
      ['cancel', 'stop order', 'don\'t want', 'do not want']),
  _AsstTopic('delivery', 'Delivery problem', [
    'missing', 'wrong item', 'spilled', 'spilt', 'damaged', 'not delivered', 'quality', 'cold'
  ]),
  _AsstTopic('account', 'Account & profile', [
    'account', 'profile', 'phone number', 'address', 'delete account', 'password', 'name'
  ]),
];

class _AsstMsg {
  _AsstMsg.bot(this.text, {this.chips, this.custom}) : bot = true;
  _AsstMsg.user(this.text)
      : bot = false,
        chips = null,
        custom = null;

  final bool bot;
  final String text;
  final List<_AsstChip>? chips;
  final Widget? custom;
}

class _AsstChip {
  const _AsstChip(this.label, this.onTap);
  final String label;
  final VoidCallback onTap;
}

class _AsstTypingDots extends StatefulWidget {
  const _AsstTypingDots();

  @override
  State<_AsstTypingDots> createState() => _AsstTypingDotsState();
}

class _AsstTypingDotsState extends State<_AsstTypingDots>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10, left: 34),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(16),
              topRight: Radius.circular(16),
              bottomLeft: Radius.circular(4),
              bottomRight: Radius.circular(16),
            ),
            border: Border.all(color: Colors.grey.shade200),
          ),
          child: AnimatedBuilder(
            animation: _c,
            builder: (_, __) => Row(
              mainAxisSize: MainAxisSize.min,
              children: List.generate(3, (d) {
                final phase = (_c.value * 3 - d).clamp(0.0, 1.0);
                final wave = (0.5 - (phase - 0.5).abs()) * 2;
                return Padding(
                  padding: EdgeInsets.only(right: d < 2 ? 5 : 0),
                  child: Transform.translate(
                    offset: Offset(0, -3 * wave),
                    child: Opacity(
                      opacity: 0.35 + 0.65 * wave,
                      child: Container(
                        width: 6,
                        height: 6,
                        decoration: const BoxDecoration(
                          color: FoodFlowTheme.crimson,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ),
                  ),
                );
              }),
            ),
          ),
        ),
      ),
    );
  }
}

class _AsstOrderPicker extends StatefulWidget {
  const _AsstOrderPicker({required this.orders, required this.onPick});
  final List<Order> orders;
  final void Function(Order) onPick;

  @override
  State<_AsstOrderPicker> createState() => _AsstOrderPickerState();
}

class _AsstOrderPickerState extends State<_AsstOrderPicker> {
  bool _done = false;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 280),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final o in widget.orders)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: InkWell(
                borderRadius: BorderRadius.circular(14),
                onTap: _done
                    ? null
                    : () {
                        setState(() => _done = true);
                        widget.onPick(o);
                      },
                child: Opacity(
                  opacity: _done ? 0.5 : 1,
                  child: Container(
                    padding: const EdgeInsets.all(11),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFE8E8EE)),
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('#${o.orderNumber}',
                                  style: const TextStyle(
                                      fontSize: 12.5,
                                      fontWeight: FontWeight.w900)),
                              const SizedBox(height: 2),
                              Text(
                                '${o.items.length} item(s) · ${o.status.replaceAll('_', ' ')}',
                                style: TextStyle(
                                    color: Colors.grey.shade600,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600),
                              ),
                            ],
                          ),
                        ),
                        const Icon(Icons.chevron_right_rounded,
                            size: 18, color: Color(0xFF9AA0A6)),
                      ],
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _AsstCancelCard extends StatefulWidget {
  const _AsstCancelCard({
    required this.orderNumber,
    required this.charge,
    required this.refund,
    required this.method,
    required this.note,
    required this.confirmLabel,
    required this.onConfirm,
    required this.onDismiss,
  });

  final String orderNumber;
  final double charge;
  final double refund;
  final String method;
  final String note;
  final String confirmLabel;
  final Future<void> Function() onConfirm;
  final VoidCallback onDismiss;

  @override
  State<_AsstCancelCard> createState() => _AsstCancelCardState();
}

class _AsstCancelCardState extends State<_AsstCancelCard> {
  bool _busy = false;
  bool _done = false;

  @override
  Widget build(BuildContext context) {
    String money(num v) => formatCurrency(context, v);
    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 280),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE8E8EE)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _kv('Order', '#${widget.orderNumber}'),
            const SizedBox(height: 6),
            _kv('Cancellation charge', money(widget.charge)),
            const SizedBox(height: 6),
            _kv('Refund to ${widget.method}', money(widget.refund), strong: true),
            if (widget.note.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(widget.note,
                  style: TextStyle(
                      color: Colors.grey.shade600,
                      fontSize: 11,
                      height: 1.35,
                      fontWeight: FontWeight.w500)),
            ],
            const SizedBox(height: 12),
            if (_done)
              const Text('Done.',
                  style: TextStyle(
                      color: FoodFlowTheme.crimson,
                      fontSize: 12,
                      fontWeight: FontWeight.w800))
            else
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton(
                      onPressed: _busy
                          ? null
                          : () async {
                              setState(() => _busy = true);
                              await widget.onConfirm();
                              if (mounted) setState(() => _done = true);
                            },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: FoodFlowTheme.crimson,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(vertical: 11),
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12)),
                      ),
                      child: _busy
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                  strokeWidth: 2, color: Colors.white))
                          : Text(widget.confirmLabel,
                              style: const TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w900)),
                    ),
                  ),
                  const SizedBox(width: 8),
                  OutlinedButton(
                    onPressed: _busy
                        ? null
                        : () {
                            setState(() => _done = true);
                            widget.onDismiss();
                          },
                    style: OutlinedButton.styleFrom(
                      foregroundColor: FoodFlowTheme.crimson,
                      side: const BorderSide(color: Color(0xFFFFD2AA)),
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 11),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Keep order',
                        style: TextStyle(
                            fontSize: 12.5, fontWeight: FontWeight.w800)),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _kv(String k, String v, {bool strong = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Flexible(
          child: Text(k,
              style: TextStyle(
                  color: Colors.grey.shade700,
                  fontSize: 12,
                  fontWeight: FontWeight.w600)),
        ),
        const SizedBox(width: 10),
        Text(v,
            style: TextStyle(
                color: strong ? FoodFlowTheme.crimson : Colors.black87,
                fontSize: strong ? 13.5 : 12.5,
                fontWeight: FontWeight.w900)),
      ],
    );
  }
}
