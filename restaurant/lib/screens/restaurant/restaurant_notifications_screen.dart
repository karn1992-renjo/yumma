import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';

class RestaurantNotificationsScreen extends StatefulWidget {
  const RestaurantNotificationsScreen({super.key});

  @override
  State<RestaurantNotificationsScreen> createState() =>
      _RestaurantNotificationsScreenState();
}

class _RestaurantNotificationsScreenState
    extends State<RestaurantNotificationsScreen> {
  final ApiService _api = ApiService();
  bool _isLoading = true;
  bool _hasData = false;
  bool _isClearing = false;
  int _unreadCount = 0;
  List<dynamic> _notifications = [];

  @override
  void initState() {
    super.initState();
    _loadNotifications();
  }

  void _applyNotifications(dynamic response) {
    if (response is! Map || response['success'] != true) return;
    final data = response['data'] as Map<String, dynamic>? ?? {};
    _notifications = data['notifications'] ?? [];
    _unreadCount = int.tryParse('${data['unread_count'] ?? 0}') ?? 0;
    _hasData = true;
  }

  Future<void> _loadNotifications() async {
    if (!_hasData) setState(() => _isLoading = true);
    try {
      final response = await _api.getWithCache(
        ApiConstants.notifications,
        queryParams: const {'target_app': 'restaurant'},
        onCache: (cached) {
          if (!mounted) return;
          setState(() {
            _applyNotifications(cached);
            _isLoading = false;
          });
        },
      );
      if (mounted) setState(() => _applyNotifications(response));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _markAllRead() async {
    await _api.post(
      ApiConstants.notificationsRead,
      data: const {'target_app': 'restaurant'},
    );
    await _loadNotifications();
  }

  Future<void> _clearAll() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Clear notifications?'),
        content: const Text('This will permanently remove all your notifications.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Clear all'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _isClearing = true);
    try {
      await _api.delete(
        ApiConstants.notifications,
        queryParams: const {'target_app': 'restaurant'},
      );
      if (!mounted) return;
      setState(() {
        _notifications = [];
        _unreadCount = 0;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Notifications cleared')),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not clear notifications. Try again.')),
      );
    } finally {
      if (mounted) setState(() => _isClearing = false);
    }
  }

  Future<void> _openNotification(Map<String, dynamic> notification) async {
    final id = notification['id']?.toString();
    if (id != null && id.isNotEmpty) {
      await _api.post(
        '${ApiConstants.notifications}/$id/read',
        data: const {'target_app': 'restaurant'},
      );
    }
    final data = notification['data'];
    final orderId = data is Map
        ? int.tryParse('${data['order_id'] ?? data['id'] ?? ''}')
        : null;
    if (!mounted) return;
    if (orderId != null) {
      Navigator.pushNamed(context, '/restaurant/order', arguments: orderId)
          .then((_) => _loadNotifications());
    } else {
      _loadNotifications();
    }
  }

  IconData _iconFor(String type) {
    final t = type.toLowerCase();
    if (t.contains('order')) return Icons.receipt_long_rounded;
    if (t.contains('payout') || t.contains('wallet')) {
      return Icons.account_balance_wallet_rounded;
    }
    if (t.contains('review') || t.contains('rating')) return Icons.star_rounded;
    if (t.contains('complaint') || t.contains('alert') || t.contains('warn')) {
      return Icons.warning_amber_rounded;
    }
    if (t.contains('promo') || t.contains('offer')) {
      return Icons.local_offer_rounded;
    }
    return Icons.notifications_rounded;
  }

  Color _tintFor(String type) {
    final t = type.toLowerCase();
    if (t.contains('complaint') || t.contains('alert') || t.contains('warn')) {
      return foodflow.danger;
    }
    if (t.contains('payout') || t.contains('review')) return foodflow.success;
    return foodflow.orange;
  }

  String _relative(dynamic raw) {
    final dt = DateTime.tryParse(raw?.toString() ?? '');
    if (dt == null) return '';
    final d = DateTime.now().difference(dt.toLocal());
    if (d.inMinutes < 1) return 'just now';
    if (d.inMinutes < 60) return '${d.inMinutes}m ago';
    if (d.inHours < 24) return '${d.inHours}h ago';
    if (d.inDays < 7) return '${d.inDays}d ago';
    return '${dt.day.toString().padLeft(2, '0')}/${dt.month.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        leading: const BackButton(),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Notifications',
                style: TextStyle(
                  color: foodflow.ink,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                )),
            Text(
              _unreadCount == 0
                  ? 'All caught up'
                  : '$_unreadCount unread',
              style: TextStyle(
                color: foodflow.muted,
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: _unreadCount == 0 ? null : _markAllRead,
            child: const Text('Mark read'),
          ),
          if (_notifications.isNotEmpty)
            IconButton(
              onPressed: _isClearing ? null : _clearAll,
              icon: _isClearing
                  ? const SizedBox.square(
                      dimension: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Icon(Icons.delete_sweep_outlined, color: foodflow.ink),
              tooltip: 'Clear all',
            ),
        ],
      ),
      body: Stack(children: [
        Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(color: foodflow.canvas),
            child: Stack(children: AuroraTheme.auroraBlobs()),
          ),
        ),
        Positioned.fill(
          child: _isLoading
              ? Center(
                  child:
                      CircularProgressIndicator(color: foodflow.orange))
              : RefreshIndicator(
                  color: foodflow.orange,
                  onRefresh: _loadNotifications,
                  child: _notifications.isEmpty
                      ? ListView(children: [
                          SizedBox(
                              height:
                                  MediaQuery.of(context).padding.top + 120),
                          FoodFlowTheme.emptyState(
                            icon: Icons.notifications_none_rounded,
                            title: 'No notifications yet',
                            subtitle:
                                'Order and support updates will appear here.',
                          ),
                        ])
                      : ListView.builder(
                          padding: EdgeInsets.fromLTRB(14,
                              MediaQuery.of(context).padding.top + 64, 14, 30),
                          itemCount: _notifications.length,
                          itemBuilder: (context, i) {
                            final n = Map<String, dynamic>.from(
                                _notifications[i] as Map);
                            final data = n['data'] is Map
                                ? Map<String, dynamic>.from(n['data'])
                                : <String, dynamic>{};
                            final unread = n['read_at'] == null;
                            final type = n['type']?.toString() ?? '';
                            final title = data['title']?.toString() ??
                                (type.isEmpty ? 'Notification' : type);
                            final body = data['body']?.toString() ??
                                data['message']?.toString() ??
                                'Tap to view details';
                            final tint = _tintFor(type);
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: Material(
                                color: unread
                                    ? tint.withOpacity(0.06)
                                    : (foodflow.isDark
                                        ? foodflow.elevatedSurface
                                        : Colors.white),
                                borderRadius: BorderRadius.circular(16),
                                clipBehavior: Clip.antiAlias,
                                child: InkWell(
                                  onTap: () => _openNotification(n),
                                  child: Ink(
                                    padding: const EdgeInsets.all(13),
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(16),
                                      border: Border.all(
                                        color: unread
                                            ? tint.withOpacity(0.35)
                                            : foodflow.line,
                                      ),
                                    ),
                                    child: Row(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Container(
                                          width: 40,
                                          height: 40,
                                          alignment: Alignment.center,
                                          decoration: BoxDecoration(
                                            color: tint.withOpacity(0.14),
                                            borderRadius:
                                                BorderRadius.circular(12),
                                          ),
                                          child: Icon(_iconFor(type),
                                              color: tint, size: 20),
                                        ),
                                        const SizedBox(width: 12),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Row(
                                                children: [
                                                  Expanded(
                                                    child: Text(
                                                      title,
                                                      maxLines: 1,
                                                      overflow: TextOverflow
                                                          .ellipsis,
                                                      style: TextStyle(
                                                        color: foodflow.ink,
                                                        fontSize: 13.5,
                                                        fontWeight:
                                                            FontWeight.w900,
                                                      ),
                                                    ),
                                                  ),
                                                  Text(
                                                    _relative(
                                                        n['created_at']),
                                                    style: TextStyle(
                                                      color: foodflow.faint,
                                                      fontSize: 10.5,
                                                      fontWeight:
                                                          FontWeight.w700,
                                                    ),
                                                  ),
                                                  if (unread) ...[
                                                    const SizedBox(width: 6),
                                                    Container(
                                                      width: 8,
                                                      height: 8,
                                                      decoration: BoxDecoration(
                                                        color: tint,
                                                        shape: BoxShape.circle,
                                                      ),
                                                    ),
                                                  ],
                                                ],
                                              ),
                                              const SizedBox(height: 3),
                                              Text(
                                                body,
                                                maxLines: 2,
                                                overflow:
                                                    TextOverflow.ellipsis,
                                                style: TextStyle(
                                                  color: foodflow.muted,
                                                  fontSize: 12,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                ),
        ),
      ]),
    );
  }
}
