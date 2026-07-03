import 'dart:async';

import 'package:flutter/material.dart';

import 'navigation_service.dart';

class CustomerOrderStatusOverlayService {
  CustomerOrderStatusOverlayService._();

  static final CustomerOrderStatusOverlayService instance =
      CustomerOrderStatusOverlayService._();

  OverlayEntry? _entry;
  Timer? _dismissTimer;
  bool _isShowing = false;

  Future<void> show({
    required Map<String, dynamic> data,
    required VoidCallback onTap,
  }) async {
    final context = appNavigatorKey.currentState?.overlay?.context;
    if (context == null) return;

    _dismissCurrent();

    _isShowing = true;
    _entry = OverlayEntry(
      builder: (_) => _CustomerStatusOverlay(
        data: data,
        onTap: () {
          _dismissCurrent();
          onTap();
        },
        onClose: _dismissCurrent,
      ),
    );

    Overlay.of(context, rootOverlay: true).insert(_entry!);
    _dismissTimer = Timer(const Duration(seconds: 5), _dismissCurrent);
  }

  void _dismissCurrent() {
    _dismissTimer?.cancel();
    _dismissTimer = null;
    _entry?.remove();
    _entry = null;
    _isShowing = false;
  }

  bool get isShowing => _isShowing;
}

class _CustomerStatusOverlay extends StatelessWidget {
  final Map<String, dynamic> data;
  final VoidCallback onTap;
  final VoidCallback onClose;

  const _CustomerStatusOverlay({
    required this.data,
    required this.onTap,
    required this.onClose,
  });

  @override
  Widget build(BuildContext context) {
    final title = (data['notification_title'] ??
            data['title'] ??
            'Order update')
        .toString();
    final body = (data['notification_body'] ??
            data['message'] ??
            'Your order has a new update.')
        .toString();
    final orderNumber =
        (data['order_number'] ?? data['order_id'] ?? '').toString();

    return Positioned(
      top: MediaQuery.of(context).padding.top + 12,
      left: 16,
      right: 16,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(24),
          child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.14),
                  blurRadius: 24,
                  offset: const Offset(0, 12),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFF5A1F),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  alignment: Alignment.center,
                  child: const Icon(
                    Icons.notifications_active_rounded,
                    color: Colors.white,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF26242B),
                        ),
                      ),
                      if (orderNumber.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          'Order #$orderNumber',
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFFFF5A1F),
                          ),
                        ),
                      ],
                      const SizedBox(height: 4),
                      Text(
                        body,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 12,
                          height: 1.35,
                          color: Color(0xFF6B6772),
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: onClose,
                  icon: const Icon(
                    Icons.close_rounded,
                    color: Color(0xFF8B8791),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
