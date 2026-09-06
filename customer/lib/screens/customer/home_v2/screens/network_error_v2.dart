import 'package:flutter/material.dart';

import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

/// Full-screen "you're offline / we can't reach the server" state.
/// Use inline (as a body) or push as its own screen.
class NetworkErrorV2 extends StatelessWidget {
  const NetworkErrorV2({
    super.key,
    required this.onRetry,
    this.embedded = false,
    this.message,
  });

  final Future<void> Function() onRetry;
  final bool embedded;
  final String? message;

  @override
  Widget build(BuildContext context) {
    final content = _Body(onRetry: onRetry, message: message);
    if (embedded) return content;
    return V2Scaffold(showBack: true, title: 'Connection', body: content);
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.onRetry, this.message});

  final Future<void> Function() onRetry;
  final String? message;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: V2Entrance(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 108,
                height: 108,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: p.accent.withOpacity(0.12),
                ),
                child: Icon(Icons.wifi_off_rounded,
                    size: 52, color: p.accent),
              ),
              const SizedBox(height: 22),
              Text("You're offline",
                  style: TextStyle(
                      color: p.ink,
                      fontSize: 20,
                      fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              Text(
                message ??
                    "We can't reach the server right now. Check your internet "
                        'connection and try again.',
                textAlign: TextAlign.center,
                style: TextStyle(color: p.inkSoft, fontSize: 13, height: 1.5),
              ),
              const SizedBox(height: 24),
              _RetryButton(onRetry: onRetry),
            ],
          ),
        ),
      ),
    );
  }
}

class _RetryButton extends StatefulWidget {
  const _RetryButton({required this.onRetry});
  final Future<void> Function() onRetry;

  @override
  State<_RetryButton> createState() => _RetryButtonState();
}

class _RetryButtonState extends State<_RetryButton> {
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: _busy
          ? null
          : () async {
              setState(() => _busy = true);
              try {
                await widget.onRetry();
              } finally {
                if (mounted) setState(() => _busy = false);
              }
            },
      child: Container(
        height: 50,
        padding: const EdgeInsets.symmetric(horizontal: 34),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: p.accent,
          borderRadius: BorderRadius.circular(15),
        ),
        child: _busy
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                    strokeWidth: 2, color: Colors.white),
              )
            : const Text('Try again',
                style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 15)),
      ),
    );
  }
}
