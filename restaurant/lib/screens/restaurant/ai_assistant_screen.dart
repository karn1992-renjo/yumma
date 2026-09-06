import 'package:flutter/material.dart';

import '../../services/ai_assistant_service.dart';
import '../../theme/aurora_theme.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/aurora/aurora.dart';

class AiAssistantScreen extends StatefulWidget {
  const AiAssistantScreen({super.key});

  @override
  State<AiAssistantScreen> createState() => _AiAssistantScreenState();
}

class _AiAssistantScreenState extends State<AiAssistantScreen> {
  final AiAssistantService _svc = AiAssistantService.instance;
  final TextEditingController _input = TextEditingController();
  final ScrollController _scroll = ScrollController();

  final List<_Msg> _msgs = [];
  bool _sending = false;
  bool? _enabled; // null = checking
  String? _conversationId;

  static const _starters = [
    'Make me a plan to increase sales this month',
    'Design a coupon to win back customers',
    'Explain my last payout',
    'Why are my orders down and how do I fix it?',
    'Which menu items should I promote?',
  ];

  @override
  void initState() {
    super.initState();
    _boot();
  }

  Future<void> _boot() async {
    final enabled = await _svc.isEnabled();
    final history = enabled ? await _svc.history() : const <AiAssistantMessage>[];
    if (!mounted) return;
    setState(() {
      _enabled = enabled;
      for (final m in history) {
        _msgs.add(_Msg(fromUser: m.fromUser, text: m.text, plans: m.plans));
      }
    });
    _jump();
  }

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _jump() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(
          _scroll.position.maxScrollExtent + 200,
          duration: const Duration(milliseconds: 260),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _send(String text) async {
    final trimmed = text.trim();
    if (trimmed.isEmpty || _sending) return;
    setState(() {
      _msgs.add(_Msg(fromUser: true, text: trimmed));
      _sending = true;
      _input.clear();
    });
    _jump();
    try {
      final reply = await _svc.send(trimmed, conversationId: _conversationId);
      _conversationId = reply.conversationId ?? _conversationId;
      if (!mounted) return;
      setState(() {
        _msgs.add(_Msg(
          fromUser: false,
          text: reply.reply.isEmpty
              ? 'Here is what I found.'
              : reply.reply,
          plans: reply.plans,
          suggestions: reply.suggestions,
        ));
        _sending = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _msgs.add(_Msg(
          fromUser: false,
          text: _enabled == false
              ? "The AI assistant isn't switched on for your account yet. "
                  "Ask your platform admin to enable it."
              : "I couldn't reach the assistant just now. Please try again.",
        ));
        _sending = false;
      });
    }
    _jump();
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top + 60;
    final empty = _msgs.isEmpty && !_sending;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Row(
          children: [
            _sparkAvatar(),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('Growth assistant',
                    style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 16,
                        fontWeight: FontWeight.w900)),
                Text(
                  _enabled == null
                      ? 'Connecting…'
                      : (_enabled! ? 'Ask about sales, payouts, offers' : 'Currently offline'),
                  style: TextStyle(
                      color: foodflow.muted,
                      fontSize: 11,
                      fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ],
        ),
      ),
      body: Stack(children: [
        ...AuroraTheme.auroraBlobs(),
        Column(
          children: [
            Expanded(
              child: empty
                  ? _intro(topPad)
                  : ListView.builder(
                      controller: _scroll,
                      padding: EdgeInsets.fromLTRB(16, topPad, 16, 16),
                      itemCount: _msgs.length + (_sending ? 1 : 0),
                      itemBuilder: (context, i) {
                        if (i == _msgs.length) return const _TypingBubble();
                        return _Bubble(
                          msg: _msgs[i],
                          onSuggestion: _send,
                        );
                      },
                    ),
            ),
            _composer(),
          ],
        ),
      ]),
    );
  }

  Widget _sparkAvatar() => Container(
        width: 34,
        height: 34,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          gradient: foodflow.brandGradient,
          shape: BoxShape.circle,
        ),
        child: const Icon(Icons.auto_awesome_rounded,
            color: Colors.white, size: 18),
      );

  Widget _intro(double topPad) {
    return ListView(
      padding: EdgeInsets.fromLTRB(20, topPad + 20, 20, 20),
      children: [
        Center(
          child: Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              gradient: foodflow.brandGradient,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: foodflow.orange.withOpacity(0.3),
                  blurRadius: 22,
                  offset: const Offset(0, 12),
                ),
              ],
            ),
            child: const Icon(Icons.auto_awesome_rounded,
                color: Colors.white, size: 30),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          'Your restaurant growth assistant',
          textAlign: TextAlign.center,
          style: TextStyle(
              color: foodflow.ink, fontSize: 19, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 6),
        Text(
          'Ask anything about your sales, payouts, cancellations, offers or menu. '
          'I look at your live numbers and reply with a step-by-step plan.',
          textAlign: TextAlign.center,
          style: TextStyle(color: foodflow.muted, fontSize: 13, height: 1.4),
        ),
        const SizedBox(height: 22),
        Text('TRY ASKING',
            style: TextStyle(
                color: foodflow.muted,
                fontSize: 11,
                letterSpacing: 0.6,
                fontWeight: FontWeight.w900)),
        const SizedBox(height: 10),
        ..._starters.map(
          (s) => Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Material(
              color: foodflow.surfaceColor,
              borderRadius: BorderRadius.circular(14),
              child: InkWell(
                onTap: () => _send(s),
                borderRadius: BorderRadius.circular(14),
                child: Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: foodflow.line),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.trending_up_rounded,
                          size: 18, color: foodflow.orange),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(s,
                            style: TextStyle(
                                color: foodflow.ink,
                                fontSize: 13,
                                fontWeight: FontWeight.w700)),
                      ),
                      Icon(Icons.arrow_forward_rounded,
                          size: 16, color: foodflow.faint),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _composer() {
    return ClipRect(
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 10, 10, 12),
        decoration: BoxDecoration(
          color: foodflow.surfaceColor,
          border: Border(top: BorderSide(color: foodflow.line)),
        ),
        child: SafeArea(
          top: false,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Container(
                  constraints: const BoxConstraints(maxHeight: 120),
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: foodflow.canvas,
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: foodflow.line),
                  ),
                  child: TextField(
                    controller: _input,
                    minLines: 1,
                    maxLines: 5,
                    textCapitalization: TextCapitalization.sentences,
                    style: TextStyle(color: foodflow.ink, fontSize: 14),
                    decoration: InputDecoration(
                      hintText: 'Ask your growth assistant…',
                      hintStyle:
                          TextStyle(color: foodflow.muted, fontSize: 14),
                      border: InputBorder.none,
                      isCollapsed: true,
                      contentPadding:
                          const EdgeInsets.symmetric(vertical: 12),
                    ),
                    onSubmitted: _send,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: _sending ? null : () => _send(_input.text),
                child: Container(
                  width: 44,
                  height: 44,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    gradient: foodflow.brandGradient,
                    shape: BoxShape.circle,
                  ),
                  child: _sending
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
                        )
                      : const Icon(Icons.arrow_upward_rounded,
                          color: Colors.white),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Msg {
  _Msg({
    required this.fromUser,
    required this.text,
    this.plans = const [],
    this.suggestions = const [],
  });
  final bool fromUser;
  final String text;
  final List<AiPlan> plans;
  final List<String> suggestions;
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.msg, required this.onSuggestion});
  final _Msg msg;
  final ValueChanged<String> onSuggestion;

  @override
  Widget build(BuildContext context) {
    final me = msg.fromUser;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment:
            me ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          _AppearIn(
            child: Container(
              constraints: BoxConstraints(
                maxWidth: MediaQuery.of(context).size.width * 0.82,
              ),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                gradient: me ? foodflow.brandGradient : null,
                color: me ? null : foodflow.surfaceColor,
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(16),
                  topRight: const Radius.circular(16),
                  bottomLeft: Radius.circular(me ? 16 : 4),
                  bottomRight: Radius.circular(me ? 4 : 16),
                ),
                border: me ? null : Border.all(color: foodflow.line),
              ),
              child: Text(
                msg.text,
                style: TextStyle(
                  color: me ? Colors.white : foodflow.ink,
                  fontSize: 13.5,
                  height: 1.4,
                ),
              ),
            ),
          ),
          for (final plan in msg.plans) ...[
            const SizedBox(height: 8),
            _AppearIn(child: _PlanCard(plan: plan)),
          ],
          if (msg.suggestions.isNotEmpty) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: msg.suggestions
                  .map((s) => GestureDetector(
                        onTap: () => onSuggestion(s),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 7),
                          decoration: BoxDecoration(
                            color: foodflow.orange.withOpacity(0.10),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(
                                color: foodflow.orange.withOpacity(0.3)),
                          ),
                          child: Text(s,
                              style: TextStyle(
                                  color: foodflow.orange,
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w800)),
                        ),
                      ))
                  .toList(),
            ),
          ],
        ],
      ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.plan});
  final AiPlan plan;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: BoxConstraints(
        maxWidth: MediaQuery.of(context).size.width * 0.86,
      ),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: foodflow.orange.withOpacity(0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.checklist_rounded, size: 16, color: foodflow.orange),
              const SizedBox(width: 8),
              Expanded(
                child: Text(plan.title,
                    style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w900)),
              ),
            ],
          ),
          const SizedBox(height: 10),
          for (var i = 0; i < plan.steps.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 20,
                    height: 20,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: foodflow.orange.withOpacity(0.12),
                      shape: BoxShape.circle,
                    ),
                    child: Text('${i + 1}',
                        style: TextStyle(
                            color: foodflow.orange,
                            fontSize: 10,
                            fontWeight: FontWeight.w900)),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(plan.steps[i],
                        style: TextStyle(
                            color: foodflow.inkSoft,
                            fontSize: 12.5,
                            height: 1.35)),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Fade + slide-up entrance for chat items.
class _AppearIn extends StatefulWidget {
  const _AppearIn({required this.child});
  final Widget child;
  @override
  State<_AppearIn> createState() => _AppearInState();
}

class _AppearInState extends State<_AppearIn>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 260),
  )..forward();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _c,
      child: SlideTransition(
        position: Tween(begin: const Offset(0, 0.06), end: Offset.zero)
            .animate(CurvedAnimation(parent: _c, curve: Curves.easeOut)),
        child: widget.child,
      ),
    );
  }
}

class _TypingBubble extends StatefulWidget {
  const _TypingBubble();
  @override
  State<_TypingBubble> createState() => _TypingBubbleState();
}

class _TypingBubbleState extends State<_TypingBubble>
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
      padding: const EdgeInsets.only(bottom: 12),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
          decoration: BoxDecoration(
            color: foodflow.surfaceColor,
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(16),
              topRight: Radius.circular(16),
              bottomRight: Radius.circular(16),
              bottomLeft: Radius.circular(4),
            ),
            border: Border.all(color: foodflow.line),
          ),
          child: AnimatedBuilder(
            animation: _c,
            builder: (context, _) {
              return Row(
                mainAxisSize: MainAxisSize.min,
                children: List.generate(3, (i) {
                  final t = (_c.value + i * 0.2) % 1.0;
                  final scale = 0.6 + 0.4 * (1 - (t - 0.5).abs() * 2).clamp(0.0, 1.0);
                  return Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2.5),
                    child: Transform.scale(
                      scale: scale,
                      child: Container(
                        width: 7,
                        height: 7,
                        decoration: BoxDecoration(
                          color: foodflow.orange,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ),
                  );
                }),
              );
            },
          ),
        ),
      ),
    );
  }
}
