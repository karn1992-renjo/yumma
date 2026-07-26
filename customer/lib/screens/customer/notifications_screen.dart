import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/customer/profile_screen_chrome.dart';

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
    if (mounted) {
      setState(() {
        _error = null;
        _loading = true;
      });
    }
    try {
      final response = await _api.get(
        ApiConstants.notifications,
        queryParams: const {'limit': 100, 'target_app': 'customer'},
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
        await _api.post(
          ApiConstants.markNotificationsRead,
          data: const {'target_app': 'customer'},
        );
        if (!mounted) return;
        setState(() {
          _notifications = _notifications
              .map((item) => {
                    ...item,
                    'read_at': DateTime.now().toIso8601String(),
                  })
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
        title: Text('Clear notifications?'),
        content: Text(
          'This will permanently remove all your notifications.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            style: TextButton.styleFrom(
              foregroundColor: profileButtonColor(context),
              textStyle: const TextStyle(
                fontSize: 13,
                height: 1.05,
                fontWeight: FontWeight.w800,
              ),
            ),
            child: Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(
              backgroundColor: profileButtonColor(context),
              foregroundColor: profileOnButtonColor(context),
              textStyle: const TextStyle(
                fontSize: 14,
                height: 1.05,
                fontWeight: FontWeight.w900,
              ),
            ),
            child: Text('Clear all'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _clearing = true);
    try {
      await _api.delete(
        ApiConstants.notifications,
        queryParams: const {'target_app': 'customer'},
      );
      if (!mounted) return;
      setState(() => _notifications = const []);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Notifications cleared')),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Could not clear notifications. Try again.'),
        ),
      );
    } finally {
      if (mounted) setState(() => _clearing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: profileCanvasColor(context),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _load,
          color: profileAccentColor(context),
          child: _body(context),
        ),
      ),
    );
  }

  Widget _header() {
    return ProfilePageTopBar(
      title: 'Notifications',
      subtitle: _notifications.isEmpty
          ? 'Your account updates will appear here'
          : '${_notifications.length} account updates',
      actions: [
        if (_notifications.isNotEmpty) ...[
          const SizedBox(width: 12),
          ProfileRoundButton(
            icon: _clearing ? LucideIcons.loader : LucideIcons.trash_2,
            color: profileButtonColor(context),
            onTap: _clearing ? null : _clearAll,
          ),
        ],
      ],
    );
  }

  Widget _body(BuildContext context) {
    if (_loading) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(18, 14, 18, 32),
        children: [
          _header(),
          const SizedBox(height: 140),
          const Center(child: CircularProgressIndicator()),
        ],
      );
    }

    if (_error != null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(18, 14, 18, 32),
        children: [
          _header(),
          const SizedBox(height: 24),
          ProfileSurfaceCard(
            padding: const EdgeInsets.all(24),
            child: Column(
              children: [
                ProfileAccentIcon(
                  icon: LucideIcons.cloud_off,
                  size: 66,
                  iconSize: 30,
                  radius: 20,
                ),
                const SizedBox(height: 16),
                Text(
                  'Unable to load notifications',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: profileTextColor(context),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Pull down or try again to refresh your account updates.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: profileMutedColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 18),
                ElevatedButton(
                  onPressed: _load,
                  style: FoodFlowTheme.zomatoPrimaryButton(
                    color: profileButtonColor(context),
                    foregroundColor: profileOnButtonColor(context),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 12,
                    ),
                    radius: 14,
                  ),
                  child: Text('Try again'),
                ),
              ],
            ),
          ),
        ],
      );
    }

    if (_notifications.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(18, 14, 18, 32),
        children: [
          _header(),
          const SizedBox(height: 24),
          ProfileSurfaceCard(
            padding: const EdgeInsets.all(24),
            child: Column(
              children: [
                ProfileAccentIcon(
                  icon: LucideIcons.bell,
                  size: 66,
                  iconSize: 30,
                  radius: 20,
                ),
                SizedBox(height: 16),
                Text(
                  'No notifications yet',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: profileTextColor(context),
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 8),
                Text(
                  'Order updates, offers and support replies will show up here.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: profileMutedColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      );
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(18, 14, 18, 32),
      itemCount: _notifications.length + 2,
      itemBuilder: (context, index) {
        if (index == 0) return _header();
        if (index == 1) {
          return const Padding(
            padding: EdgeInsets.fromLTRB(0, 22, 0, 10),
            child: ProfileSectionLabel(title: 'Recent updates'),
          );
        }

        final item = _notifications[index - 2];
        final data = item['data'] is Map
            ? Map<String, dynamic>.from(item['data'] as Map)
            : <String, dynamic>{};
        final title = '${data['title'] ?? 'Notification'}';
        final body = '${data['body'] ?? data['message'] ?? ''}';
        final unread = item['read_at'] == null;

        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: InkWell(
            onTap: () => _open(item),
            borderRadius: BorderRadius.circular(20),
            child: ProfileSurfaceCard(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              child: Row(
                children: [
                  ProfileAccentIcon(
                    icon: LucideIcons.bell,
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: profileTextColor(context),
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        if (body.isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Text(
                            body,
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: profileMutedColor(context),
                              fontSize: 12,
                              fontWeight: FontWeight.w500,
                              height: 1.35,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  unread
                      ? Container(
                          width: 9,
                          height: 9,
                          decoration: BoxDecoration(
                            color: profileAccentColor(context),
                            shape: BoxShape.circle,
                          ),
                        )
                      : Icon(
                          LucideIcons.chevron_right,
                          color: profileMutedColor(context),
                          size: 24,
                        ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
