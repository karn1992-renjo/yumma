import 'package:flutter/material.dart';

import '../../theme/foodflow_theme.dart';

class NetworkErrorScreen extends StatelessWidget {
  const NetworkErrorScreen({
    super.key,
    required this.onRetry,
    this.message,
    this.title = 'Connection lost',
  });

  final Future<void> Function() onRetry;
  final String? message;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      body: NetworkErrorView(
        onRetry: onRetry,
        message: message,
        title: title,
      ),
    );
  }
}

class NetworkErrorView extends StatefulWidget {
  const NetworkErrorView({
    super.key,
    required this.onRetry,
    this.message,
    this.title = 'Connection lost',
  });

  final Future<void> Function() onRetry;
  final String? message;
  final String title;

  @override
  State<NetworkErrorView> createState() => _NetworkErrorViewState();
}

class _NetworkErrorViewState extends State<NetworkErrorView>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _float;
  bool _isRetrying = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1300),
    )..repeat(reverse: true);
    _float = CurvedAnimation(parent: _controller, curve: Curves.easeInOut);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _retry() async {
    if (_isRetrying) return;
    setState(() => _isRetrying = true);
    try {
      await widget.onRetry();
    } finally {
      if (mounted) setState(() => _isRetrying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedBuilder(
                animation: _float,
                builder: (context, child) {
                  return Transform.translate(
                    offset: Offset(0, -8 * _float.value),
                    child: child,
                  );
                },
                child: Container(
                  width: 132,
                  height: 132,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(28),
                    border: Border.all(color: FoodFlowTheme.line),
                    boxShadow: [
                      BoxShadow(
                        color: FoodFlowTheme.crimson.withOpacity(0.10),
                        blurRadius: 28,
                        offset: const Offset(0, 14),
                      ),
                    ],
                  ),
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      Container(
                        width: 72,
                        height: 72,
                        decoration: BoxDecoration(
                          color: FoodFlowTheme.crimson.withOpacity(0.08),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const Icon(
                        Icons.wifi_off_rounded,
                        size: 48,
                        color: FoodFlowTheme.crimson,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 26),
              Text(
                widget.title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                widget.message?.trim().isNotEmpty == true
                    ? widget.message!
                    : 'Please check your internet connection and try again.',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 14,
                  height: 1.45,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: 180,
                height: 48,
                child: ElevatedButton.icon(
                  onPressed: _isRetrying ? null : _retry,
                  style: FoodFlowTheme.zomatoPrimaryButton(radius: 8),
                  icon: _isRetrying
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.refresh_rounded),
                  label: Text(_isRetrying ? 'Trying...' : 'Try again'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
