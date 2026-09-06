import 'package:flutter/material.dart';

import '../../screens/restaurant/ai_assistant_screen.dart';
import '../../theme/foodflow_theme.dart';

/// Floating "growth assistant" bubble. Drop it inside a Stack (e.g. the dashboard
/// body). It pulses gently and opens the full AI chat screen on tap.
class AiAssistantBubble extends StatefulWidget {
  const AiAssistantBubble({super.key, this.bottom = 96, this.right = 16});

  final double bottom;
  final double right;

  @override
  State<AiAssistantBubble> createState() => _AiAssistantBubbleState();
}

class _AiAssistantBubbleState extends State<AiAssistantBubble>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2600),
  )..repeat();

  bool _expanded = true;

  @override
  void initState() {
    super.initState();
    // Collapse the label after a few seconds so it becomes just an orb.
    Future.delayed(const Duration(seconds: 4), () {
      if (mounted) setState(() => _expanded = false);
    });
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  void _open() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const AiAssistantScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Positioned(
      right: widget.right,
      bottom: widget.bottom,
      child: GestureDetector(
        onTap: _open,
        child: AnimatedBuilder(
          animation: _pulse,
          builder: (context, _) {
            final glow = 0.18 + 0.14 * (1 - (_pulse.value - 0.5).abs() * 2);
            return AnimatedContainer(
              duration: const Duration(milliseconds: 240),
              curve: Curves.easeOut,
              height: 52,
              padding: EdgeInsets.symmetric(horizontal: _expanded ? 16 : 15),
              decoration: BoxDecoration(
                gradient: foodflow.brandGradient,
                borderRadius: BorderRadius.circular(999),
                boxShadow: [
                  BoxShadow(
                    color: foodflow.orange.withOpacity(glow),
                    blurRadius: 24,
                    spreadRadius: 1,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.auto_awesome_rounded,
                      color: Colors.white, size: 22),
                  if (_expanded) ...[
                    const SizedBox(width: 8),
                    const Text(
                      'Ask AI',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        fontSize: 14,
                      ),
                    ),
                  ],
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
