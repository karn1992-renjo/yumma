// lib/screens/driver/driver_notifications_screen.dart
import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/aurora/aurora.dart';

class DriverNotificationsScreen extends StatefulWidget {
  const DriverNotificationsScreen({Key? key}) : super(key: key);

  @override
  State<DriverNotificationsScreen> createState() =>
      _DriverNotificationsScreenState();
}

class _DriverNotificationsScreenState extends State<DriverNotificationsScreen> {
  final ApiService _api = ApiService();
  bool _isLoading = true;
  bool _isClearing = false;
  int _unreadCount = 0;
  List<dynamic> _notifications = [];

  @override
  void initState() {
    super.initState();
    _loadNotifications();
  }

  Future<void> _loadNotifications() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(
        ApiConstants.notifications,
        queryParams: const {'target_app': 'driver'},
      );
      if (response['success'] == true) {
        final data = response['data'] as Map<String, dynamic>? ?? {};
        setState(() {
          _notifications = data['notifications'] ?? [];
          _unreadCount = int.tryParse('${data['unread_count'] ?? 0}') ?? 0;
        });
      }
    } catch (e) {
      debugPrint('Load driver notifications error: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _markAllRead() async {
    try {
      await _api.post(
        ApiConstants.notificationsRead,
        data: const {'target_app': 'driver'},
      );
      await _loadNotifications();
    } catch (e) {
      debugPrint('Mark all read error: $e');
    }
  }

  Future<void> _clearAll() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Clear notifications?'),
        content:
            const Text('This will permanently remove all your notifications.'),
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
      await _api.delete('${ApiConstants.notifications}?target_app=driver');
      if (!mounted) return;
      setState(() {
        _notifications = [];
        _unreadCount = 0;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Notifications cleared')),
      );
    } catch (e) {
      debugPrint('Clear notifications error: $e');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Could not clear notifications. Try again.')),
      );
    } finally {
      if (mounted) setState(() => _isClearing = false);
    }
  }

  Future<void> _openNotification(Map<String, dynamic> notification) async {
    final id = notification['id']?.toString();
    if (id != null && id.isNotEmpty) {
      try {
        await _api.post(
          '${ApiConstants.notifications}/$id/read',
          data: const {'target_app': 'driver'},
        );
      } catch (e) {
        debugPrint('Mark notification read error: $e');
      }
    }

    final data = notification['data'];
    final map =
        data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
    final deepLink = map['deep_link']?.toString();
    final orderId = int.tryParse('${map['order_id'] ?? map['id'] ?? ''}');

    if (!mounted) return;

    if (deepLink == '/driver/gigs') {
      Navigator.pushNamed(context, '/driver/gigs')
          .then((_) => _loadNotifications());
    } else if (orderId != null) {
      Navigator.pushNamed(context, '/driver/order', arguments: orderId)
          .then((_) => _loadNotifications());
    } else {
      _loadNotifications();
    }
  }

  @override
  Widget build(BuildContext context) {
    return AuroraScaffold(
      appBar: GlassAppBar(
        title: const Text('Notifications'),
        actions: [
          TextButton(
            onPressed: _unreadCount == 0 ? null : _markAllRead,
            child: const Text('Mark read'),
          ),
          if (_notifications.isNotEmpty)
            TextButton(
              onPressed: _isClearing ? null : _clearAll,
              child: _isClearing
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Clear all'),
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadNotifications,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                children: [
                  Text(
                    _unreadCount == 0
                        ? 'Everything is caught up.'
                        : '$_unreadCount unread update${_unreadCount == 1 ? '' : 's'}.',
                    style:  TextStyle(
                      color: foodflow.muted,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (_notifications.isEmpty)
                    SizedBox(
                      height: 300,
                      child: foodflow.emptyState(
                        icon: Icons.notifications_none_rounded,
                        title: 'No notifications yet',
                        subtitle: 'Order and gig updates will appear here.',
                      ),
                    )
                  else
                    ..._notifications.indexed.map((entry) {
                      final raw = entry.$2;
                      final notification =
                          Map<String, dynamic>.from(raw as Map);
                      final data = notification['data'] is Map
                          ? Map<String, dynamic>.from(notification['data'])
                          : <String, dynamic>{};
                      final unread = notification['read_at'] == null;
                      final title = data['title']?.toString() ??
                          notification['type']?.toString() ??
                          'Notification';
                      final body = data['body']?.toString() ??
                          data['message']?.toString() ??
                          'Tap to view details';

                      return AuroraEntrance(
                        delay: Duration(
                            milliseconds: (entry.$1 * 45).clamp(0, 350)),
                        child: GlassCard(
                        solid: true,
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(14),
                        radius: 14,
                        onTap: () => _openNotification(notification),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 38,
                              height: 38,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: (unread ? foodflow.orange : foodflow.muted)
                                    .withOpacity(0.14),
                                borderRadius: BorderRadius.circular(11),
                              ),
                              child: Icon(
                                unread
                                    ? Icons.notifications_active_rounded
                                    : Icons.notifications_none_rounded,
                                size: 18,
                                color:
                                    unread ? foodflow.orange : foodflow.muted,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    title,
                                    style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                      color: foodflow.ink,
                                      fontSize: 13.5,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    body,
                                    style: TextStyle(
                                      color: foodflow.muted,
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (unread) ...[
                              const SizedBox(width: 8),
                              Container(
                                margin: const EdgeInsets.only(top: 4),
                                width: 8,
                                height: 8,
                                decoration: BoxDecoration(
                                  color: foodflow.orange,
                                  shape: BoxShape.circle,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      );
                    }),
                ],
              ),
            ),
    );
  }
}
