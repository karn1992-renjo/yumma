import 'package:flutter/material.dart';

import '../../../../config/api_constants.dart';
import '../../../../services/api_service.dart';
import '../../../../utils/notification_route_resolver.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class NotificationsV2 extends StatefulWidget {
  const NotificationsV2({super.key});

  @override
  State<NotificationsV2> createState() => _NotificationsV2State();
}

class _NotificationsV2State extends State<NotificationsV2> {
  final ApiService _api = ApiService();
  bool _loading = true;
  bool _clearing = false;
  List<Map<String, dynamic>> _items = const [];

  String _idOf(Map<String, dynamic> item) =>
      '${item['id'] ?? item['notification_id'] ?? ''}';

  Future<void> _deleteOne(Map<String, dynamic> item) async {
    final id = _idOf(item);
    setState(() => _items = _items.where((e) => _idOf(e) != id).toList());
    if (id.isEmpty) return;
    try {
      await _api.post(ApiConstants.deleteNotification(id),
          data: const {'target_app': 'customer'});
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not remove that notification.')),
        );
        _load();
      }
    }
  }

  Future<void> _clearAll() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Clear all notifications?'),
        content: const Text('This removes every notification from this list.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Clear all')),
        ],
      ),
    );
    if (ok != true) return;
    setState(() {
      _clearing = true;
      _items = const [];
    });
    try {
      await _api.post(ApiConstants.clearNotifications,
          data: const {'target_app': 'customer'});
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not clear notifications.')),
        );
        _load();
      }
    }
    if (mounted) setState(() => _clearing = false);
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await _api.get(ApiConstants.notifications,
          queryParams: const {'target_app': 'customer'});
      final payload = res is Map ? res['data'] : null;
      final list = payload is Map ? payload['notifications'] : null;
      if (list is List) {
        _items = list
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
      }
      try {
        await _api.post(ApiConstants.markNotificationsRead, data: const {});
      } catch (_) {}
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  void _open(Map<String, dynamic> item) {
    final data = item['data'] is Map
        ? Map<String, dynamic>.from(item['data'] as Map)
        : <String, dynamic>{};
    final route = resolveNotificationDeepLink(data);
    if (route != null) {
      Navigator.of(context).pushNamed(route.name, arguments: route.arguments);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Scaffold(
      title: 'Notifications',
      showBack: true,
      actions: _items.isEmpty
          ? null
          : [
              IconButton(
                tooltip: 'Clear all',
                onPressed: _clearing ? null : _clearAll,
                icon: Icon(
                  Icons.delete_sweep_rounded,
                  color: p.ink,
                ),
              ),
            ],
      body: _loading
          ? Center(child: CircularProgressIndicator(color: p.accent))
          : _items.isEmpty
              ? const V2EmptyState(
                  icon: Icons.notifications_none_rounded,
                  title: 'No notifications',
                  message: 'Updates about your orders and offers land here.',
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  color: p.accent,
                  backgroundColor: p.bgMid,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 30),
                    itemCount: _items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (_, i) {
                      final item = _items[i];
                      final data = item['data'] is Map
                          ? Map<String, dynamic>.from(item['data'] as Map)
                          : const {};
                      final title =
                          '${data['title'] ?? item['title'] ?? 'Notification'}';
                      final body =
                          '${data['body'] ?? data['message'] ?? item['body'] ?? ''}';
                      final unread = item['read_at'] == null &&
                          item['is_read'] != true;
                      return V2Entrance(
                        delay: Duration(milliseconds: 20 * i.clamp(0, 10)),
                        child: Dismissible(
                          key: ValueKey('notif_${_idOf(item)}_$i'),
                          direction: DismissDirection.endToStart,
                          onDismissed: (_) => _deleteOne(item),
                          background: Container(
                            alignment: Alignment.centerRight,
                            padding: const EdgeInsets.only(right: 22),
                            margin: const EdgeInsets.symmetric(vertical: 1),
                            decoration: BoxDecoration(
                              color: p.danger.withOpacity(0.14),
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: Icon(Icons.delete_outline_rounded,
                                color: p.danger),
                          ),
                          child: GlassPanel(
                            radius: 18,
                            strong: unread,
                            onTap: () => _open(item),
                            padding: const EdgeInsets.all(14),
                            child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                width: 8,
                                height: 8,
                                margin: const EdgeInsets.only(top: 5, right: 10),
                                decoration: BoxDecoration(
                                  color: unread ? p.accent : Colors.transparent,
                                  shape: BoxShape.circle,
                                ),
                              ),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.start,
                                  children: [
                                    Text(title,
                                        style: TextStyle(
                                            color: p.ink,
                                            fontSize: 14,
                                            fontWeight: FontWeight.w800)),
                                    if (body.isNotEmpty) ...[
                                      const SizedBox(height: 3),
                                      Text(body,
                                          style: TextStyle(
                                              color: p.inkFaint,
                                              fontSize: 12,
                                              height: 1.35)),
                                    ],
                                  ],
                                ),
                              ),
                            ],
                          ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
