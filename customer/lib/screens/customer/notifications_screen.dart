import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _notifications = const [];
  bool _loading = true;
  bool _clearing = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) setState(() => _error = null);
    try {
      final response = await _api.get(
        ApiConstants.notifications,
        queryParams: const {'limit': 100},
      );
      final payload = response['data'];
      final items = payload is Map ? payload['notifications'] as List? : null;
      if (!mounted) return;
      setState(() {
        _notifications = (items ?? const [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList(growable: false);
        _loading = false;
      });
      if (_notifications.any((item) => item['read_at'] == null)) {
        await _api.post(ApiConstants.markNotificationsRead, data: const {});
        if (!mounted) return;
        setState(() {
          _notifications = _notifications
              .map((item) => {...item, 'read_at': DateTime.now().toIso8601String()})
              .toList(growable: false);
        });
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = error.toString();
      });
    }
  }

  Future<void> _open(Map<String, dynamic> item) async {
    final data = item['data'] is Map
        ? Map<String, dynamic>.from(item['data'] as Map)
        : <String, dynamic>{};
    final deepLink = data['deep_link']?.toString();
    final orderId = int.tryParse('${data['order_id'] ?? data['id'] ?? ''}');

    if (deepLink == '/support' || '${data['type']}'.contains('support')) {
      await Navigator.pushNamed(context, '/support');
    } else if (orderId != null) {
      await Navigator.pushNamed(context, '/order/track', arguments: orderId);
    } else if (deepLink != null && deepLink.startsWith('/')) {
      await Navigator.pushNamed(context, deepLink);
    }
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

    setState(() => _clearing = true);
    try {
      await _api.delete(ApiConstants.notifications);
      if (!mounted) return;
      setState(() => _notifications = const []);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Notifications cleared')),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not clear notifications. Try again.')),
      );
    } finally {
      if (mounted) setState(() => _clearing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFAFAFA),
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (_notifications.isNotEmpty)
            TextButton(
              onPressed: _clearing ? null : _clearAll,
              child: _clearing
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Clear all'),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _body(context),
      ),
    );
  }

  Widget _body(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 180),
          const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
          const SizedBox(height: 12),
          const Center(child: Text('Unable to load notifications')),
          Center(child: TextButton(onPressed: _load, child: const Text('Try again'))),
        ],
      );
    }
    if (_notifications.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 120, 20, 32),
        children: const [
          Icon(Icons.notifications_none_rounded, size: 52, color: Colors.grey),
          SizedBox(height: 14),
          Text(
            'No notifications yet',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
          ),
        ],
      );
    }

    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      itemCount: _notifications.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final item = _notifications[index];
        final data = item['data'] is Map
            ? Map<String, dynamic>.from(item['data'] as Map)
            : <String, dynamic>{};
        final title = '${data['title'] ?? 'Notification'}';
        final body = '${data['body'] ?? data['message'] ?? ''}';
        final unread = item['read_at'] == null;
        return Material(
          color: unread ? Theme.of(context).colorScheme.primary.withOpacity(.07) : Colors.white,
          borderRadius: BorderRadius.circular(16),
          child: ListTile(
            onTap: () => _open(item),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            leading: CircleAvatar(
              backgroundColor: Theme.of(context).colorScheme.primary.withOpacity(.12),
              child: Icon(Icons.notifications_rounded, color: Theme.of(context).colorScheme.primary),
            ),
            title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
            subtitle: body.isEmpty ? null : Text(body, maxLines: 3, overflow: TextOverflow.ellipsis),
            trailing: unread
                ? Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primary,
                      shape: BoxShape.circle,
                    ),
                  )
                : null,
          ),
        );
      },
    );
  }
}
