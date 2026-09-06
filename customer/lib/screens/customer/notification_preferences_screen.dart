import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../widgets/customer/profile_screen_chrome.dart';

class NotificationPreferencesScreen extends StatefulWidget {
  const NotificationPreferencesScreen({super.key});

  @override
  State<NotificationPreferencesScreen> createState() =>
      _NotificationPreferencesScreenState();
}

class _NotificationPreferencesScreenState
    extends State<NotificationPreferencesScreen> {
  final ApiService _api = ApiService();
  bool _loading = true;
  bool _orderUpdates = true;
  bool _offersPromotions = true;
  String? _savingKey;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final response = await _api.get(ApiConstants.notificationPreferences);
      final data = response is Map ? response['data'] : null;
      if (data is Map) {
        setState(() {
          _orderUpdates = data['notify_order_updates'] != false;
          _offersPromotions = data['notify_offers_promotions'] != false;
        });
      }
    } catch (error) {
      debugPrint('Could not load notification preferences: $error');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _update(String key, bool value) async {
    setState(() {
      _savingKey = key;
      if (key == 'notify_order_updates') {
        _orderUpdates = value;
      } else {
        _offersPromotions = value;
      }
    });

    try {
      await _api.put(ApiConstants.notificationPreferences, data: {key: value});
    } catch (error) {
      debugPrint('Could not update notification preference: $error');
      if (!mounted) return;
      setState(() {
        if (key == 'notify_order_updates') {
          _orderUpdates = !value;
        } else {
          _offersPromotions = !value;
        }
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Could not save your preference. Try again.'),
        ),
      );
    } finally {
      if (mounted) setState(() => _savingKey = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: profileCanvasColor(context),
      body: SafeArea(
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 14, 18, 32),
          children: [
            ProfilePageTopBar(
              title: 'Notification Preferences',
              subtitle: 'Choose what you want to be notified about',
            ),
            const SizedBox(height: 24),
            if (_loading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 40),
                child: Center(child: CircularProgressIndicator()),
              )
            else ...[
              _PreferenceCard(
                icon: LucideIcons.package,
                title: 'Order & Account Updates',
                subtitle:
                    'Order status, delivery updates and account activity',
                value: _orderUpdates,
                saving: _savingKey == 'notify_order_updates',
                onChanged: (value) => _update('notify_order_updates', value),
              ),
              const SizedBox(height: 14),
              _PreferenceCard(
                icon: LucideIcons.tag,
                title: 'Offers & Promotions',
                subtitle: 'Deals, discounts and personalised offers',
                value: _offersPromotions,
                saving: _savingKey == 'notify_offers_promotions',
                onChanged: (value) =>
                    _update('notify_offers_promotions', value),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _PreferenceCard extends StatelessWidget {
  const _PreferenceCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.value,
    required this.saving,
    required this.onChanged,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final bool value;
  final bool saving;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return ProfileSurfaceCard(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          ProfileAccentIcon(icon: icon),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: profileTextColor(context),
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
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
          const SizedBox(width: 12),
          saving
              ? const SizedBox.square(
                  dimension: 22,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Switch(
                  value: value,
                  onChanged: onChanged,
                  activeColor: profileAccentColor(context),
                ),
        ],
      ),
    );
  }
}
