import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';

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
        queryParams: const {'target_app': 'restaurant'},
      );
      if (response['success'] == true) {
        final data = response['data'] as Map<String, dynamic>? ?? {};
        setState(() {
          _notifications = data['notifications'] ?? [];
          _unreadCount = int.tryParse('${data['unread_count'] ?? 0}') ?? 0;
        });
      }
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
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
                padding: const EdgeInsets.only(bottom: 24),
                children: [
                  PremiumRestaurantHeader(
                    title: 'Notifications',
                    subtitle: _unreadCount == 0
                        ? 'Everything is caught up.'
                        : '$_unreadCount unread updates need attention.',
                    icon: Icons.notifications_active_outlined,
                  ),
                  if (_notifications.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Container(
                        decoration: RestaurantPremium.panel(radius: 18),
                        child: FoodFlowTheme.emptyState(
                          icon: Icons.notifications_none_outlined,
                          title: 'No notifications yet',
                          subtitle: 'Order and support updates will appear here.',
                        ),
                      ),
                    )
                  else
                    ..._notifications.map((raw) {
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
                      return Padding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                        child: Container(
                          decoration: RestaurantPremium.panel(radius: 18),
                          child: ListTile(
                            leading: Icon(
                              unread
                                  ? Icons.notifications_active
                                  : Icons.notifications_none,
                              color: unread
                                  ? FoodFlowTheme.orange
                                  : FoodFlowTheme.muted,
                            ),
                            title: Text(
                              title,
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            subtitle: Text(body),
                            trailing: unread
                                ? const Icon(Icons.circle, size: 10)
                                : null,
                            onTap: () => _openNotification(notification),
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
